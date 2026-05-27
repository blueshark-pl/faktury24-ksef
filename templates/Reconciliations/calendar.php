<?php
/**
 * @var \App\View\AppView $this
 * @var string $ym
 * @var int    $year
 * @var int    $month
 * @var string $mode
 * @var string $view
 * @var string $show
 * @var bool   $showTx
 * @var array  $weeks
 * @var array  $byDate
 * @var string $firstDay
 * @var string $lastDay
 * @var string $today
 * @var string $prevYM
 * @var string $nextYM
 * @var string $gridStart
 * @var string $gridEnd
 * @var string $filterNip
 * @var string $filterCurrency
 * @var string $filterType
 * @var bool   $filterOverdue
 * @var array  $contractorsForFilter
 * @var array  $summary
 * @var float  $maxOverduePln
 */

$this->assign('title', 'Kalendarz rozliczeń');

$polishMonths = ['','styczeń','luty','marzec','kwiecień','maj','czerwiec','lipiec','sierpień','wrzesień','październik','listopad','grudzień'];
$polishDays = ['Pon','Wt','Śr','Czw','Pt','Sob','Nd'];

$fnum  = static fn ($v) => number_format((float)$v, 2, ',', ' ');
$fnum0 = static fn ($v) => number_format((float)$v, 0, ',', ' ');

// Helper budujący URL ze zachowaniem filtrów
$buildUrl = function (array $changes = []) use ($ym, $mode, $view, $show, $showTx, $filterNip, $filterCurrency, $filterType, $filterOverdue) {
    $params = array_merge([
        'mode'            => $mode,
        'view'            => $view,
        'show'            => $show,
        'show_tx'         => $showTx ? '1' : '',
        'contractor_nip'  => $filterNip,
        'currency'        => $filterCurrency,
        'type'            => $filterType,
        'only_overdue'    => $filterOverdue ? '1' : '',
    ], $changes);
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
    return ['action' => 'calendar', $params['_ym'] ?? $ym, '?' => array_diff_key($params, ['_ym' => 1])];
};

// Statystyki dnia
$dayStats = function (string $date) use ($byDate) {
    $list = $byDate[$date] ?? [];
    $inv_paid = $inv_overdue = $inv_pending = 0;
    $pay_count = 0;
    $tx_matched = $tx_proposed = $tx_unmatched = $tx_ignored = 0;
    $totalPln = $totalEur = $payPln = $payEur = $overduePln = 0.0;
    $txPln = $txEur = 0.0;
    foreach ($list as $i) {
        $curr = strtoupper($i['currency'] ?: 'PLN');
        if ($curr !== 'EUR') $curr = 'PLN';
        $k = $i['kind'] ?? 'invoice';
        if ($k === 'payment') {
            $pay_count++;
            if ($curr === 'EUR') $payEur += (float)$i['amount'];
            else $payPln += (float)$i['amount'];
        } elseif ($k === 'transfer') {
            $ms = $i['match_status'] ?? 'unmatched';
            if ($ms === 'matched')        $tx_matched++;
            elseif ($ms === 'proposed')   $tx_proposed++;
            elseif ($ms === 'ignored')    $tx_ignored++;
            else                          $tx_unmatched++;
            // tylko credit (wpłaty) wliczamy do sum
            if (($i['direction'] ?? 'C') === 'C') {
                if ($curr === 'EUR') $txEur += (float)$i['amount'];
                else $txPln += (float)$i['amount'];
            }
        } else {
            if ($i['paymentstate'] === 'paid') $inv_paid++;
            elseif ($i['is_overdue']) {
                $inv_overdue++;
                if ($curr === 'EUR') $overduePln += (float)$i['remaining'] * 4.3;
                else $overduePln += (float)$i['remaining'];
            } else $inv_pending++;
            if ($curr === 'EUR') $totalEur += (float)$i['remaining'];
            else $totalPln += (float)$i['remaining'];
        }
    }
    $tx_count = $tx_matched + $tx_proposed + $tx_unmatched + $tx_ignored;
    return compact('list', 'inv_paid', 'inv_overdue', 'inv_pending', 'pay_count',
                   'tx_count', 'tx_matched', 'tx_proposed', 'tx_unmatched', 'tx_ignored',
                   'totalPln', 'totalEur', 'payPln', 'payEur', 'overduePln', 'txPln', 'txEur');
};

// Color helper dla heatmap density
$heatmapBg = function (float $overdue) use ($maxOverduePln) {
    if ($overdue <= 0 || $maxOverduePln <= 0) return '';
    $ratio = min(1, $overdue / max($maxOverduePln, 1));
    // 0..1 → red intensity (HSL hue=0, sat=70%, light from 96 to 70)
    $light = 96 - ($ratio * 26);
    return 'background:hsl(0, 70%, ' . $light . '%) !important;';
};
?>

<div class="container-fluid py-3" id="calendarPage" data-grid-start="<?= h($gridStart) ?>" data-grid-end="<?= h($gridEnd) ?>">

    <!-- ── Top Toolbar ────────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
        <h4 class="mb-0">
            <i class="ri-calendar-line text-primary me-1"></i>
            Kalendarz
        </h4>

        <!-- View toggle: Month / Week / Day -->
        <div class="btn-group btn-group-sm" role="group">
            <a href="<?= $this->Url->build($buildUrl(['view' => 'month'])) ?>"
               class="btn <?= $view === 'month' ? 'btn-primary' : 'btn-outline-secondary' ?>"
               title="Widok miesięczny">
                <i class="ri-layout-grid-line"></i> Miesiąc
            </a>
            <a href="<?= $this->Url->build($buildUrl(['view' => 'week', 'day' => $today])) ?>"
               class="btn <?= $view === 'week' ? 'btn-primary' : 'btn-outline-secondary' ?>"
               title="Widok tygodniowy">
                <i class="ri-table-line"></i> Tydzień
            </a>
            <a href="<?= $this->Url->build($buildUrl(['view' => 'day', 'day' => $today])) ?>"
               class="btn <?= $view === 'day' ? 'btn-primary' : 'btn-outline-secondary' ?>"
               title="Widok dnia">
                <i class="ri-calendar-event-line"></i> Dzień
            </a>
        </div>

        <!-- Date nav -->
        <?php if ($view === 'month'): ?>
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?= $this->Url->build($buildUrl(['_ym' => $prevYM])) ?>" class="btn btn-outline-secondary"><i class="ri-arrow-left-s-line"></i></a>
                <span class="btn btn-light disabled fw-semibold" style="min-width:160px"><?= h(ucfirst($polishMonths[$month])) ?> <?= $year ?></span>
                <a href="<?= $this->Url->build($buildUrl(['_ym' => $nextYM])) ?>" class="btn btn-outline-secondary"><i class="ri-arrow-right-s-line"></i></a>
                <a href="<?= $this->Url->build($buildUrl(['_ym' => date('Y-m')])) ?>" class="btn btn-outline-primary">Dziś</a>
            </div>
        <?php elseif ($view === 'week'): ?>
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?= $this->Url->build($buildUrl(['day' => $this->request->getQuery('prev_week') ?: date('Y-m-d', strtotime($gridStart . ' -7 days'))])) ?>" class="btn btn-outline-secondary"><i class="ri-arrow-left-s-line"></i></a>
                <span class="btn btn-light disabled fw-semibold"><?= h(date('d.m', strtotime($gridStart))) ?> — <?= h(date('d.m.Y', strtotime($gridEnd))) ?></span>
                <a href="<?= $this->Url->build($buildUrl(['day' => date('Y-m-d', strtotime($gridStart . ' +7 days'))])) ?>" class="btn btn-outline-secondary"><i class="ri-arrow-right-s-line"></i></a>
                <a href="<?= $this->Url->build($buildUrl(['day' => $today])) ?>" class="btn btn-outline-primary">Dziś</a>
            </div>
        <?php elseif ($view === 'day'): ?>
            <?php $dayParam = $this->request->getQuery('day', $today); ?>
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?= $this->Url->build($buildUrl(['day' => date('Y-m-d', strtotime($dayParam . ' -1 day'))])) ?>" class="btn btn-outline-secondary"><i class="ri-arrow-left-s-line"></i></a>
                <span class="btn btn-light disabled fw-semibold"><?= h(date('l, d.m.Y', strtotime($dayParam))) ?></span>
                <a href="<?= $this->Url->build($buildUrl(['day' => date('Y-m-d', strtotime($dayParam . ' +1 day'))])) ?>" class="btn btn-outline-secondary"><i class="ri-arrow-right-s-line"></i></a>
                <a href="<?= $this->Url->build($buildUrl(['day' => $today])) ?>" class="btn btn-outline-primary">Dziś</a>
            </div>
        <?php endif; ?>

        <!-- Show toggle: Invoices / Payments / Both -->
        <div class="btn-group btn-group-sm" role="group">
            <a href="<?= $this->Url->build($buildUrl(['show' => 'invoices'])) ?>"
               class="btn <?= $show === 'invoices' ? 'btn-warning' : 'btn-outline-warning' ?>"
               title="Tylko faktury">
                <i class="ri-file-list-3-line"></i> Faktury
            </a>
            <a href="<?= $this->Url->build($buildUrl(['show' => 'payments'])) ?>"
               class="btn <?= $show === 'payments' ? 'btn-success' : 'btn-outline-success' ?>"
               title="Tylko wpłaty">
                <i class="ri-money-dollar-circle-line"></i> Wpłaty
            </a>
            <a href="<?= $this->Url->build($buildUrl(['show' => 'both'])) ?>"
               class="btn <?= $show === 'both' ? 'btn-primary' : 'btn-outline-primary' ?>"
               title="Faktury + wpłaty">
                <i class="ri-funds-line"></i> Oba
            </a>
        </div>

        <!-- Show transfers toggle (independent) -->
        <a href="<?= $this->Url->build($buildUrl(['show_tx' => $showTx ? '' : '1'])) ?>"
           class="btn btn-sm <?= $showTx ? 'btn-info' : 'btn-outline-info' ?>"
           title="<?= $showTx ? 'Ukryj przelewy bankowe' : 'Pokaż przelewy bankowe na kalendarzu' ?>">
            <i class="ri-bank-line"></i> Przelewy
            <?php if ($showTx && !empty($summary['transfers_count'])): ?>
                <span class="badge bg-light text-dark"><?= (int)$summary['transfers_count'] ?></span>
            <?php endif; ?>
        </a>

        <!-- Mode toggle (effective vs paymentdate) -->
        <?php if ($show !== 'payments'): ?>
        <div class="btn-group btn-group-sm" role="group">
            <a href="<?= $this->Url->build($buildUrl(['mode' => 'paymentdate'])) ?>"
               class="btn <?= $mode === 'paymentdate' ? 'btn-info' : 'btn-outline-info' ?>"
               title="Termin z faktury">
                <i class="ri-file-list-3-line"></i> z faktury
            </a>
            <a href="<?= $this->Url->build($buildUrl(['mode' => 'effective'])) ?>"
               class="btn <?= $mode === 'effective' ? 'btn-info' : 'btn-outline-info' ?>"
               title="Termin liczony od wysyłki dokumentów">
                <i class="ri-truck-line"></i> efektywny
            </a>
        </div>
        <?php endif; ?>

        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary ms-auto">
            <i class="ri-arrow-left-line me-1"></i>Powrót
        </a>
    </div>

    <!-- ── Summary bar ─────────────────────────────────────────────────────── -->
    <div class="card mb-2 shadow-sm">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
            <span class="text-muted small">Podsumowanie <?= $view === 'month' ? 'miesiąca' : ($view === 'week' ? 'tygodnia' : 'dnia') ?>:</span>

            <?php if (($summary['invoices_count'] ?? 0) > 0): ?>
                <div class="d-flex flex-column" style="font-size:.75rem">
                    <span class="text-muted text-uppercase" style="letter-spacing:.04em;font-size:.6rem">Faktury</span>
                    <span><strong><?= (int)$summary['invoices_count'] ?></strong>
                        <span class="text-success">✓ <?= (int)$summary['paid_count'] ?></span>
                        <span class="text-warning">⌛ <?= (int)$summary['pending_count'] ?></span>
                        <span class="text-danger">⚠ <?= (int)$summary['overdue_count'] ?></span>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (($summary['payments_count'] ?? 0) > 0): ?>
                <div class="d-flex flex-column" style="font-size:.75rem">
                    <span class="text-muted text-uppercase" style="letter-spacing:.04em;font-size:.6rem">Wpłaty</span>
                    <span><strong><?= (int)$summary['payments_count'] ?>×</strong>
                        <?php if ($summary['payment_amount']['PLN'] > 0): ?>
                            <span class="text-primary fw-semibold"><?= $fnum0($summary['payment_amount']['PLN']) ?> PLN</span>
                        <?php endif; ?>
                        <?php if ($summary['payment_amount']['EUR'] > 0): ?>
                            <span class="text-success fw-semibold"><?= $fnum0($summary['payment_amount']['EUR']) ?> EUR</span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (($summary['transfers_count'] ?? 0) > 0): ?>
                <div class="d-flex flex-column" style="font-size:.75rem">
                    <span class="text-muted text-uppercase" style="letter-spacing:.04em;font-size:.6rem">Przelewy bankowe</span>
                    <span><strong><?= (int)$summary['transfers_count'] ?>×</strong>
                        <?php if (($summary['transfers_matched_count'] ?? 0) > 0): ?>
                            <span class="text-success">✓ <?= (int)$summary['transfers_matched_count'] ?></span>
                        <?php endif; ?>
                        <?php if (($summary['transfers_unmatched_count'] ?? 0) > 0): ?>
                            <span class="text-danger">! <?= (int)$summary['transfers_unmatched_count'] ?> bez przyp.</span>
                        <?php endif; ?>
                        <?php if (($summary['transfer_amount']['PLN'] ?? 0) > 0): ?>
                            <span class="text-info fw-semibold"><?= $fnum0($summary['transfer_amount']['PLN']) ?> PLN</span>
                        <?php endif; ?>
                        <?php if (($summary['transfer_amount']['EUR'] ?? 0) > 0): ?>
                            <span class="text-info fw-semibold"><?= $fnum0($summary['transfer_amount']['EUR']) ?> EUR</span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($summary['overdue_amount']['PLN'] > 0 || $summary['overdue_amount']['EUR'] > 0): ?>
                <div class="d-flex flex-column" style="font-size:.75rem">
                    <span class="text-muted text-uppercase" style="letter-spacing:.04em;font-size:.6rem">Przeterminowane</span>
                    <span>
                        <?php if ($summary['overdue_amount']['PLN'] > 0): ?>
                            <span class="text-danger fw-bold"><?= $fnum0($summary['overdue_amount']['PLN']) ?> PLN</span>
                        <?php endif; ?>
                        <?php if ($summary['overdue_amount']['EUR'] > 0): ?>
                            <span class="text-danger fw-bold"><?= $fnum0($summary['overdue_amount']['EUR']) ?> EUR</span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($summary['invoice_amount']['PLN'] > 0 || $summary['invoice_amount']['EUR'] > 0): ?>
                <div class="d-flex flex-column" style="font-size:.75rem">
                    <span class="text-muted text-uppercase" style="letter-spacing:.04em;font-size:.6rem">Do zapłaty łącznie</span>
                    <span>
                        <?php if ($summary['invoice_amount']['PLN'] > 0): ?>
                            <span class="text-primary fw-semibold"><?= $fnum0($summary['invoice_amount']['PLN']) ?> PLN</span>
                        <?php endif; ?>
                        <?php if ($summary['invoice_amount']['EUR'] > 0): ?>
                            <span class="text-success fw-semibold"><?= $fnum0($summary['invoice_amount']['EUR']) ?> EUR</span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Toggle filtry button -->
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="btnToggleFilters" title="Pokaż/ukryj filtry">
                <i class="ri-filter-line"></i> Filtry
                <?php
                    $activeFilters = ($filterNip !== '' ? 1 : 0) + ($filterCurrency !== '' ? 1 : 0)
                        + ($filterType !== '' ? 1 : 0) + ($filterOverdue ? 1 : 0);
                ?>
                <?php if ($activeFilters > 0): ?>
                    <span class="badge bg-primary"><?= $activeFilters ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <div class="row g-2">
        <!-- ── Sidebar z filtrami ──────────────────────────────────────────── -->
        <div class="col-lg-3" id="filterSidebar">
            <div class="card shadow-sm" style="position:sticky;top:1rem">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-filter-line text-primary"></i>
                    <strong class="small">Filtry</strong>
                    <?php if ($activeFilters > 0): ?>
                        <a href="<?= $this->Url->build($buildUrl(['contractor_nip' => '', 'currency' => '', 'type' => '', 'only_overdue' => ''])) ?>"
                           class="btn btn-sm btn-link p-0 ms-auto text-danger" style="font-size:.75rem">
                            <i class="ri-close-circle-line"></i> Wyczyść
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body small">
                    <form method="get" id="filterForm">
                        <!-- Preserve view/mode -->
                        <input type="hidden" name="mode" value="<?= h($mode) ?>">
                        <input type="hidden" name="view" value="<?= h($view) ?>">
                        <input type="hidden" name="show" value="<?= h($show) ?>">
                        <input type="hidden" name="show_tx" value="<?= $showTx ? '1' : '' ?>">

                        <!-- Contractor -->
                        <div class="mb-2">
                            <label class="form-label form-label-sm text-muted">Kontrahent</label>
                            <select name="contractor_nip" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">— wszyscy —</option>
                                <?php foreach ($contractorsForFilter as $c): ?>
                                    <option value="<?= h($c['nip']) ?>" <?= $filterNip === $c['nip'] ? 'selected' : '' ?>>
                                        <?= h(mb_strimwidth($c['name'], 0, 40, '…')) ?> (<?= (int)$c['cnt'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Currency -->
                        <div class="mb-2">
                            <label class="form-label form-label-sm text-muted">Waluta</label>
                            <select name="currency" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">— wszystkie —</option>
                                <?php foreach (['PLN', 'EUR', 'USD'] as $cur): ?>
                                    <option value="<?= h($cur) ?>" <?= $filterCurrency === $cur ? 'selected' : '' ?>><?= h($cur) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Type -->
                        <div class="mb-2">
                            <label class="form-label form-label-sm text-muted">Typ faktury</label>
                            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">— wszystkie —</option>
                                <?php $typeLabels = ['vat' => 'VAT', 'currency' => 'Walutowa', 'novat' => 'Bez VAT',
                                    'correction' => 'Korekta', 'proforma' => 'Proforma', 'credit_note' => 'NU']; ?>
                                <?php foreach ($typeLabels as $tv => $tl): ?>
                                    <option value="<?= h($tv) ?>" <?= $filterType === $tv ? 'selected' : '' ?>><?= h($tl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Only overdue -->
                        <div class="form-check mb-2">
                            <input type="checkbox" name="only_overdue" value="1" id="filterOverdue"
                                class="form-check-input" <?= $filterOverdue ? 'checked' : '' ?>
                                onchange="this.form.submit()">
                            <label for="filterOverdue" class="form-check-label">Tylko przeterminowane</label>
                        </div>

                        <!-- Day param dla week/day view -->
                        <?php if (in_array($view, ['week', 'day'], true) && $this->request->getQuery('day')): ?>
                            <input type="hidden" name="day" value="<?= h($this->request->getQuery('day')) ?>">
                        <?php endif; ?>
                    </form>

                    <hr>
                    <div class="small">
                        <div class="text-muted mb-1">Legenda:</div>
                        <div><span class="cal-dot bg-danger d-inline-block">×</span> przeterminowane</div>
                        <div><span class="cal-dot bg-warning text-dark d-inline-block">×</span> oczekujące</div>
                        <div><span class="cal-dot bg-success d-inline-block">×</span> zapłacone</div>
                        <div><span class="cal-dot bg-info d-inline-block">×</span> wpłaty (invoice_payments)</div>
                        <?php if ($showTx): ?>
                            <div class="mt-1 pt-1 border-top">
                                <div class="text-muted small mb-1">Przelewy (MT940):</div>
                                <div><span class="cal-dot bg-success d-inline-block">✓</span> powiązany</div>
                                <div><span class="cal-dot bg-warning text-dark d-inline-block">?</span> propozycja</div>
                                <div><span class="cal-dot bg-danger d-inline-block">!</span> bez przypisania</div>
                                <div><span class="cal-dot bg-secondary d-inline-block">×</span> ignorowany</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Calendar grid ───────────────────────────────────────────────── -->
        <div class="col-lg-9" id="calendarMain">
            <?php if ($view === 'day'): ?>
                <!-- Day view -->
                <?php $dayParam = $this->request->getQuery('day', $today); $stats = $dayStats($dayParam); ?>
                <div class="card shadow-sm">
                    <div class="card-header py-2 d-flex align-items-center gap-2">
                        <i class="ri-calendar-event-line text-primary"></i>
                        <strong><?= h(date('l, d.m.Y', strtotime($dayParam))) ?></strong>
                        <span class="badge bg-secondary-subtle text-secondary ms-auto"><?= count($stats['list']) ?> rekordów</span>
                    </div>
                    <?php if (empty($stats['list'])): ?>
                        <div class="card-body text-muted small fst-italic text-center py-4">
                            <i class="ri-calendar-line fs-4 d-block mb-2"></i>
                            Brak rekordów na ten dzień.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <?php
                                $txStatusBadge = function (string $ms): array {
                                    return match ($ms) {
                                        'matched'   => ['success', 'Powiązany', '✓'],
                                        'proposed'  => ['warning', 'Propozycja', '?'],
                                        'ignored'   => ['secondary', 'Ignorowany', '×'],
                                        default     => ['danger', 'Bez przypisania', '!'],
                                    };
                                };
                                ?>
                                <?php foreach ($stats['list'] as $it): ?>
                                    <?php if ($it['kind'] === 'invoice'): ?>
                                        <?php $stateCol = $it['is_overdue'] ? 'danger' : ($it['paymentstate'] === 'paid' ? 'success' : 'warning'); ?>
                                        <tr>
                                            <td style="width:50px"><i class="ri-file-list-3-line text-warning fs-5" title="Faktura"></i></td>
                                            <td>
                                                <a href="/invoices/view/<?= h($it['id']) ?>" class="fw-semibold text-dark text-decoration-none"><?= h($it['fullnumber']) ?></a>
                                                <div class="text-muted small"><?= h($it['contractor']) ?></div>
                                            </td>
                                            <td class="text-end">
                                                <div class="fw-bold"><?= $fnum($it['remaining']) ?> <?= h($it['currency']) ?></div>
                                                <div class="text-muted small">brutto: <?= $fnum($it['total']) ?></div>
                                            </td>
                                            <td><span class="badge bg-<?= $stateCol ?>-subtle text-<?= $stateCol ?> border"><?= h($it['paymentstate']) ?><?= $it['is_overdue'] ? ' ⚠' : '' ?></span></td>
                                        </tr>
                                    <?php elseif ($it['kind'] === 'payment'): ?>
                                        <tr>
                                            <td style="width:50px"><i class="ri-money-dollar-circle-line text-success fs-5" title="Wpłata"></i></td>
                                            <td>
                                                <?php if ($it['invoice_id']): ?>
                                                    <a href="/invoices/view/<?= h($it['invoice_id']) ?>" class="fw-semibold text-dark text-decoration-none"><?= h($it['fullnumber']) ?></a>
                                                <?php endif; ?>
                                                <div class="text-muted small"><?= h($it['contractor']) ?></div>
                                            </td>
                                            <td class="text-end">
                                                <div class="fw-bold text-success">+<?= $fnum($it['amount']) ?> <?= h($it['currency']) ?></div>
                                                <div class="text-muted small"><?= h($it['method']) ?></div>
                                            </td>
                                            <td><span class="badge bg-success-subtle text-success border">Wpłata</span></td>
                                        </tr>
                                    <?php else: /* transfer */ ?>
                                        <?php
                                        [$txCol, $txLbl, $txGlyph] = $txStatusBadge($it['match_status']);
                                        $isCredit = ($it['direction'] ?? 'C') === 'C';
                                        ?>
                                        <tr>
                                            <td style="width:50px"><i class="ri-bank-line text-info fs-5" title="Przelew bankowy"></i></td>
                                            <td>
                                                <div class="fw-semibold text-dark">
                                                    <?= h($it['party_name']) ?: '<em class="text-muted">brak nadawcy</em>' ?>
                                                </div>
                                                <?php if (!empty($it['invoice_fullnumber']) || !empty($it['allocations'])): ?>
                                                    <div class="text-muted small">
                                                        <i class="ri-link"></i>
                                                        <?php if (!empty($it['allocations'])): ?>
                                                            <?php foreach ($it['allocations'] as $a): ?>
                                                                <a href="/invoices/view/<?= h($a['invoice_id']) ?>" class="text-decoration-none"><?= h($a['fullnumber']) ?></a> (<?= $fnum($a['amount']) ?>)
                                                            <?php endforeach; ?>
                                                        <?php elseif (!empty($it['invoice_fullnumber'])): ?>
                                                            <a href="/invoices/view/<?= h($it['invoice_id']) ?>" class="text-decoration-none"><?= h($it['invoice_fullnumber']) ?></a>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($it['title'])): ?>
                                                    <div class="text-muted small text-truncate" style="max-width:400px" title="<?= h($it['title']) ?>"><?= h(mb_strimwidth($it['title'], 0, 80, '…')) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="fw-bold text-<?= $isCredit ? 'info' : 'secondary' ?>">
                                                    <?= $isCredit ? '+' : '−' ?><?= $fnum($it['amount']) ?> <?= h($it['currency']) ?>
                                                </div>
                                                <?php if ($it['match_confidence'] > 0): ?>
                                                    <div class="text-muted small">conf: <?= (int)$it['match_confidence'] ?>%</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $txCol ?>-subtle text-<?= $txCol ?> border" title="<?= h($txLbl) ?>">
                                                    <?= $txGlyph ?> <?= h($txLbl) ?>
                                                </span>
                                                <div class="mt-1">
                                                    <a href="/wyciagi/transakcje?id=<?= h($it['id']) ?>" class="small text-decoration-none" title="Otwórz w wyciągach"><i class="ri-external-link-line"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Month / Week view -->
                <div class="card shadow-sm">
                    <div class="card-body p-2">
                        <table class="table table-bordered mb-0 calendar-table" style="table-layout:fixed">
                            <thead>
                                <tr class="bg-light">
                                    <?php foreach ($polishDays as $d): ?>
                                        <th class="text-center text-muted py-1" style="width:14.28%;font-size:.85rem"><?= h($d) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($weeks as $week): ?>
                                    <tr>
                                        <?php foreach ($week as $date):
                                            $stats = $dayStats($date);
                                            $dayNum = (int)substr($date, -2);
                                            $isCurrentMonth = (int)substr($date, 5, 2) === $month;
                                            $isToday = $date === $today;
                                            $hasInvoices = $stats['inv_paid'] + $stats['inv_overdue'] + $stats['inv_pending'] > 0;
                                            $hasPayments = $stats['pay_count'] > 0;
                                            $hasTransfers= ($stats['tx_count'] ?? 0) > 0;
                                            $hasContent  = $hasInvoices || $hasPayments || $hasTransfers;
                                            $cellClass = 'cal-cell align-top';
                                            if (!$isCurrentMonth && $view === 'month') $cellClass .= ' bg-light text-muted';
                                            if ($isToday) $cellClass .= ' cal-today';
                                            if ($hasContent) $cellClass .= ' cal-clickable';
                                            $bgStyle = $heatmapBg($stats['overduePln']);
                                        ?>
                                            <td class="<?= $cellClass ?>"
                                                style="<?= $bgStyle ?> <?= $view === 'week' ? 'height:300px' : 'height:120px' ?>"
                                                <?php if ($hasContent): ?>
                                                    data-date="<?= h($date) ?>"
                                                    data-day-label="<?= h(date('d.m.Y', strtotime($date))) ?>"
                                                <?php endif; ?>>

                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <span class="cal-day-num <?= $isToday ? 'fw-bold text-primary' : '' ?>" style="font-size:.85rem"><?= $dayNum ?></span>
                                                    <?php if ($view === 'week'): ?>
                                                        <span class="text-muted small"><?= h(substr($date, 5, 5)) ?></span>
                                                    <?php elseif ($hasContent): ?>
                                                        <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.62rem"><?= count($stats['list']) ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($hasContent): ?>
                                                    <!-- Dots -->
                                                    <div class="d-flex gap-1 mb-1 flex-wrap">
                                                        <?php if ($stats['inv_overdue'] > 0): ?>
                                                            <span class="cal-dot bg-danger" title="Przeterminowane: <?= $stats['inv_overdue'] ?>"><?= $stats['inv_overdue'] ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($stats['inv_pending'] > 0): ?>
                                                            <span class="cal-dot bg-warning text-dark" title="Oczekujące: <?= $stats['inv_pending'] ?>"><?= $stats['inv_pending'] ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($stats['inv_paid'] > 0): ?>
                                                            <span class="cal-dot bg-success" title="Zapłacone: <?= $stats['inv_paid'] ?>"><?= $stats['inv_paid'] ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($stats['pay_count'] > 0): ?>
                                                            <span class="cal-dot bg-info" title="Wpłaty: <?= $stats['pay_count'] ?>">💰<?= $stats['pay_count'] ?></span>
                                                        <?php endif; ?>
                                                        <?php if (($stats['tx_matched'] ?? 0) > 0): ?>
                                                            <span class="cal-dot bg-success" title="Przelewy powiązane: <?= $stats['tx_matched'] ?>" style="background:#0ea5e9 !important">🏦<?= $stats['tx_matched'] ?></span>
                                                        <?php endif; ?>
                                                        <?php if (($stats['tx_unmatched'] ?? 0) + ($stats['tx_proposed'] ?? 0) > 0): ?>
                                                            <?php $u = ($stats['tx_unmatched'] ?? 0) + ($stats['tx_proposed'] ?? 0); ?>
                                                            <span class="cal-dot bg-danger" title="Przelewy bez przypisania: <?= $u ?>">🏦?<?= $u ?></span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Week view: lista skompresowana -->
                                                    <?php if ($view === 'week'): ?>
                                                        <div class="cal-week-list" style="font-size:.65rem;line-height:1.3;max-height:230px;overflow-y:auto">
                                                            <?php foreach (array_slice($stats['list'], 0, 12) as $it): ?>
                                                                <?php $k = $it['kind'] ?? 'invoice'; ?>
                                                                <?php if ($k === 'invoice'): ?>
                                                                    <?php $col = $it['is_overdue'] ? 'danger' : ($it['paymentstate'] === 'paid' ? 'success' : 'warning'); ?>
                                                                    <div class="text-truncate text-<?= $col ?>" title="<?= h($it['fullnumber'] . ' · ' . $it['contractor']) ?>">
                                                                        <i class="ri-file-list-3-line"></i> <?= h($it['fullnumber']) ?>
                                                                        — <?= $fnum0($it['remaining']) ?> <?= h($it['currency']) ?>
                                                                    </div>
                                                                <?php elseif ($k === 'payment'): ?>
                                                                    <div class="text-truncate text-info" title="<?= h($it['fullnumber'] . ' · ' . $it['contractor']) ?>">
                                                                        <i class="ri-money-dollar-circle-line"></i> +<?= $fnum0($it['amount']) ?> <?= h($it['currency']) ?>
                                                                    </div>
                                                                <?php else: /* transfer */ ?>
                                                                    <?php
                                                                    $txCol = match ($it['match_status'] ?? '') {
                                                                        'matched' => 'success',
                                                                        'proposed' => 'warning',
                                                                        'ignored' => 'secondary',
                                                                        default => 'danger',
                                                                    };
                                                                    $isCredit = ($it['direction'] ?? 'C') === 'C';
                                                                    ?>
                                                                    <div class="text-truncate text-<?= $txCol ?>" title="<?= h($it['party_name'] . ' · ' . $it['title']) ?>">
                                                                        <i class="ri-bank-line"></i>
                                                                        <?= $isCredit ? '+' : '−' ?><?= $fnum0($it['amount']) ?> <?= h($it['currency']) ?>
                                                                        — <?= h(mb_strimwidth($it['party_name'] ?: 'tx', 0, 18, '…')) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                            <?php if (count($stats['list']) > 12): ?>
                                                                <div class="text-muted">+ <?= count($stats['list']) - 12 ?> więcej…</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Month view: tylko sumy -->
                                                        <div class="cal-sum" style="font-size:.7rem;line-height:1.2">
                                                            <?php if ($stats['totalPln'] > 0): ?>
                                                                <div class="text-primary fw-semibold"><?= $fnum0($stats['totalPln']) ?> PLN</div>
                                                            <?php endif; ?>
                                                            <?php if ($stats['totalEur'] > 0): ?>
                                                                <div class="text-success fw-semibold"><?= $fnum0($stats['totalEur']) ?> EUR</div>
                                                            <?php endif; ?>
                                                            <?php if ($stats['pay_count'] > 0): ?>
                                                                <?php if ($stats['payPln'] > 0): ?>
                                                                    <div class="text-info"><i class="ri-arrow-up-circle-line"></i> <?= $fnum0($stats['payPln']) ?> PLN</div>
                                                                <?php endif; ?>
                                                                <?php if ($stats['payEur'] > 0): ?>
                                                                    <div class="text-info"><i class="ri-arrow-up-circle-line"></i> <?= $fnum0($stats['payEur']) ?> EUR</div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                            <?php if (($stats['txPln'] ?? 0) > 0): ?>
                                                                <div style="color:#0ea5e9"><i class="ri-bank-line"></i> <?= $fnum0($stats['txPln']) ?> PLN</div>
                                                            <?php endif; ?>
                                                            <?php if (($stats['txEur'] ?? 0) > 0): ?>
                                                                <div style="color:#0ea5e9"><i class="ri-bank-line"></i> <?= $fnum0($stats['txEur']) ?> EUR</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Modal day click ─────────────────────────────────────────────────── -->
<div class="modal fade" id="calendarDayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="calendarDayTitle">Faktury z dnia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="calendarDayBody"></div>
        </div>
    </div>
</div>

<style>
.calendar-table td.cal-cell {
    padding: 6px 8px;
    vertical-align: top;
    transition: background-color .15s;
}
.calendar-table td.cal-clickable:hover { filter: brightness(0.95); }
.calendar-table td.cal-today { border: 2px solid #0d6efd !important; }
.cal-dot {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; padding: 0 4px; border-radius: 10px;
    font-size: .65rem; font-weight: 700; color: #fff;
}
.cal-dot.bg-warning { color: #78350f !important; }
.cal-dot.bg-info { color: #fff !important; }
.cal-sum { border-top: 1px dashed #e5e7eb; padding-top: 4px; margin-top: 2px; }

@media (max-width: 991px) {
    #filterSidebar { display: none; }
    #filterSidebar.show { display: block; position: fixed; top: 60px; left: 0; right: 0; z-index: 1050;
                          background: #fff; border-bottom: 2px solid #0d6efd; padding: 1rem; }
}
</style>

<script>
(function () {
    'use strict';
    var modal = new bootstrap.Modal(document.getElementById('calendarDayModal'));
    var byDate = <?= json_encode($byDate, JSON_UNESCAPED_UNICODE) ?>;

    function fmtAmount(v) {
        return parseFloat(v || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ').replace('.', ',');
    }
    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    var stateLabels = { 'paid': 'Zapłacona', 'partial': 'Częściowo', 'unpaid': 'Do zapłaty' };
    var stateColors = { 'paid': 'success', 'partial': 'warning', 'unpaid': 'secondary' };
    var txStatusMeta = {
        'matched':   { col: 'success',   lbl: 'Powiązany',         glyph: '✓' },
        'proposed':  { col: 'warning',   lbl: 'Propozycja',        glyph: '?' },
        'ignored':   { col: 'secondary', lbl: 'Ignorowany',        glyph: '×' },
        'unmatched': { col: 'danger',    lbl: 'Bez przypisania',   glyph: '!' }
    };
    function txMeta(s) { return txStatusMeta[s] || txStatusMeta['unmatched']; }

    function renderDayModal(date, label) {
        var list = byDate[date] || [];
        document.getElementById('calendarDayTitle').textContent = label + ' — ' + list.length + ' rekordów';

        if (!list.length) {
            document.getElementById('calendarDayBody').innerHTML = '<div class="p-3 text-muted small fst-italic">Brak rekordów.</div>';
            return;
        }

        var html = '<div class="table-responsive"><table class="table table-sm table-hover small mb-0">';
        list.forEach(function (it) {
            var kind = it.kind || 'invoice';
            if (kind === 'invoice') {
                var stateCol = it.is_overdue ? 'danger' : (stateColors[it.paymentstate] || 'secondary');
                var stateLbl = stateLabels[it.paymentstate] || it.paymentstate;
                if (it.is_overdue && it.paymentstate !== 'paid') stateLbl += ' (przeterm.)';
                var effHtml = it.effective_due && it.effective_due !== it.paymentdate
                    ? '<div class="text-info small">ef: ' + esc(it.effective_due) + '</div>'
                    : '';
                html += '<tr>'
                     + '<td style="width:40px"><i class="ri-file-list-3-line text-warning fs-5"></i></td>'
                     + '<td><a href="/invoices/view/' + esc(it.id) + '" class="fw-semibold text-dark text-decoration-none">' + esc(it.fullnumber) + '</a>'
                     +   '<div class="text-muted small text-truncate" style="max-width:300px" title="' + esc(it.contractor) + '">' + esc(it.contractor) + '</div>'
                     +   effHtml
                     + '</td>'
                     + '<td class="text-end"><div class="fw-bold">' + fmtAmount(it.remaining) + ' ' + esc(it.currency) + '</div>'
                     +   '<div class="text-muted small">brutto ' + fmtAmount(it.total) + '</div></td>'
                     + '<td><span class="badge bg-' + stateCol + '-subtle text-' + stateCol + ' border" style="font-size:.65rem">' + esc(stateLbl) + '</span></td>'
                     + '</tr>';
            } else if (kind === 'payment') {
                html += '<tr>'
                     + '<td style="width:40px"><i class="ri-money-dollar-circle-line text-success fs-5"></i></td>'
                     + '<td>'
                     +   (it.invoice_id ? '<a href="/invoices/view/' + esc(it.invoice_id) + '" class="fw-semibold text-dark text-decoration-none">' + esc(it.fullnumber) + '</a>' : esc(it.fullnumber))
                     +   '<div class="text-muted small">' + esc(it.contractor) + '</div>'
                     + '</td>'
                     + '<td class="text-end"><div class="fw-bold text-success">+' + fmtAmount(it.amount) + ' ' + esc(it.currency) + '</div>'
                     +   '<div class="text-muted small">' + esc(it.method) + '</div></td>'
                     + '<td><span class="badge bg-success-subtle text-success border" style="font-size:.65rem">Wpłata</span></td>'
                     + '</tr>';
            } else if (kind === 'transfer') {
                var m = txMeta(it.match_status);
                var isCredit = (it.direction || 'C') === 'C';
                var party = it.party_name || '<em class="text-muted">brak nadawcy</em>';
                var allocHtml = '';
                if (it.allocations && it.allocations.length) {
                    allocHtml = '<div class="text-muted small"><i class="ri-link"></i> ';
                    it.allocations.forEach(function (a, idx) {
                        if (idx > 0) allocHtml += ', ';
                        allocHtml += '<a href="/invoices/view/' + esc(a.invoice_id) + '" class="text-decoration-none">' + esc(a.fullnumber) + '</a> (' + fmtAmount(a.amount) + ')';
                    });
                    allocHtml += '</div>';
                } else if (it.invoice_id && it.invoice_fullnumber) {
                    allocHtml = '<div class="text-muted small"><i class="ri-link"></i> <a href="/invoices/view/' + esc(it.invoice_id) + '" class="text-decoration-none">' + esc(it.invoice_fullnumber) + '</a></div>';
                }
                var titleHtml = it.title ? '<div class="text-muted small text-truncate" style="max-width:350px" title="' + esc(it.title) + '">' + esc(it.title) + '</div>' : '';
                var confHtml = (it.match_confidence > 0) ? '<div class="text-muted small">conf: ' + it.match_confidence + '%</div>' : '';
                html += '<tr>'
                     + '<td style="width:40px"><i class="ri-bank-line text-info fs-5" title="Przelew bankowy"></i></td>'
                     + '<td><div class="fw-semibold text-dark">' + party + '</div>'
                     +   allocHtml + titleHtml
                     + '</td>'
                     + '<td class="text-end"><div class="fw-bold text-' + (isCredit ? 'info' : 'secondary') + '">'
                     +   (isCredit ? '+' : '−') + fmtAmount(it.amount) + ' ' + esc(it.currency) + '</div>'
                     +   confHtml
                     + '</td>'
                     + '<td><span class="badge bg-' + m.col + '-subtle text-' + m.col + ' border" style="font-size:.65rem" title="' + esc(m.lbl) + '">' + m.glyph + ' ' + esc(m.lbl) + '</span>'
                     +   '<div class="mt-1"><a href="/wyciagi/transakcje?id=' + esc(it.id) + '" class="small text-decoration-none" title="Otwórz w wyciągach"><i class="ri-external-link-line"></i></a></div>'
                     + '</td>'
                     + '</tr>';
            }
        });
        html += '</table></div>';
        document.getElementById('calendarDayBody').innerHTML = html;
    }

    document.querySelectorAll('.calendar-table td.cal-clickable').forEach(function (td) {
        td.addEventListener('click', function () {
            var d = this.dataset.date;
            var l = this.dataset.dayLabel;
            if (!d) return;
            renderDayModal(d, l);
            modal.show();
        });
    });

    // Toggle filters sidebar on mobile
    var btnToggle = document.getElementById('btnToggleFilters');
    if (btnToggle) {
        btnToggle.addEventListener('click', function () {
            document.getElementById('filterSidebar').classList.toggle('show');
        });
    }
})();
</script>

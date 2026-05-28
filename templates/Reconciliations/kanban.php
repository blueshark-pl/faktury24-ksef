<?php
/**
 * @var \App\View\AppView $this
 * @var array $buckets
 * @var array $stats
 * @var string $today
 * @var string $filterNip
 * @var string $filterCurrency
 * @var float  $filterMinAmount
 * @var string $filterAssigned
 * @var bool   $showSnoozed
 * @var bool   $showArchived
 * @var bool   $compactMode
 * @var array  $contractorsForFilter
 * @var array  $usersForFilter
 */

$this->assign('title', 'Kanban rozliczeń');

$fnum  = static fn ($v) => number_format((float)$v, 2, ',', ' ');
$fnum0 = static fn ($v) => number_format((float)$v, 0, ',', ' ');

$columns = [
    'in_term'  => ['label' => 'W terminie',       'icon' => 'ri-time-line',           'color' => 'info',    'hint' => 'Termin > 7 dni'],
    'sent'     => ['label' => 'Wysłane',          'icon' => 'ri-mail-send-line',      'color' => 'primary', 'hint' => 'Czekamy na potwierdzenie ≤3d od wysyłki'],
    'due_soon' => ['label' => 'Za 7 dni',         'icon' => 'ri-alarm-warning-line',  'color' => 'warning', 'hint' => 'Termin ≤ 7 dni'],
    'overdue'  => ['label' => 'Przeterminowane',  'icon' => 'ri-error-warning-fill',  'color' => 'danger',  'hint' => 'Termin minął'],
    'dispute'  => ['label' => 'Spór / windykacja','icon' => 'ri-scales-3-line',       'color' => 'dark',    'hint' => 'Oznaczone ręcznie'],
    'paid'     => ['label' => 'Opłacone',         'icon' => 'ri-checkbox-circle-fill','color' => 'success', 'hint' => 'Ostatnie 30 dni'],
];

$severityColors = [
    'critical' => '#7f1d1d',
    'high'     => '#dc2626',
    'medium'   => '#f97316',
    'low'      => '#facc15',
    'none'     => '#e5e7eb',
];

$buildUrl = function (array $changes = []) use ($filterNip, $filterCurrency, $filterMinAmount, $filterAssigned, $showSnoozed, $showArchived, $compactMode) {
    $params = array_merge([
        'contractor_nip' => $filterNip,
        'currency'       => $filterCurrency,
        'min_amount'     => $filterMinAmount > 0 ? $filterMinAmount : '',
        'assigned'       => $filterAssigned,
        'show_snoozed'   => $showSnoozed ? '1' : '',
        'show_archived'  => $showArchived ? '1' : '',
        'compact'        => $compactMode ? '1' : '',
    ], $changes);
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
    return ['action' => 'kanban', '?' => $params];
};

$activeFilters = ($filterNip !== '' ? 1 : 0) + ($filterCurrency !== '' ? 1 : 0)
    + ($filterMinAmount > 0 ? 1 : 0) + ($filterAssigned !== '' ? 1 : 0);
?>

<div class="kanban-page-wrap" id="kanbanPage">

    <!-- ── Toolbar ───────────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
        <h4 class="mb-0">
            <i class="ri-layout-column-line text-primary me-1"></i>
            Kanban rozliczeń
        </h4>

        <!-- Nawigacja widoków -->
        <div class="btn-group btn-group-sm ms-3" role="group">
            <a href="<?= $this->Url->build(['action' => 'indexKsef']) ?>" class="btn btn-outline-secondary">
                <i class="ri-list-check"></i> Lista
            </a>
            <a href="<?= $this->Url->build(['action' => 'calendar']) ?>" class="btn btn-outline-secondary">
                <i class="ri-calendar-line"></i> Kalendarz
            </a>
            <a href="<?= $this->Url->build(['action' => 'insights']) ?>" class="btn btn-outline-secondary">
                <i class="ri-bar-chart-2-line"></i> Insights
            </a>
            <span class="btn btn-primary"><i class="ri-layout-column-line"></i> Kanban</span>
        </div>

        <!-- Compact mode toggle -->
        <a href="<?= $this->Url->build($buildUrl(['compact' => $compactMode ? '' : '1'])) ?>"
           class="btn btn-sm <?= $compactMode ? 'btn-secondary' : 'btn-outline-secondary' ?>"
           title="<?= $compactMode ? 'Pełny widok' : 'Tryb kompaktowy' ?>">
            <i class="ri-<?= $compactMode ? 'expand-diagonal-line' : 'contract-line' ?>"></i>
            <?= $compactMode ? 'Pełny' : 'Kompakt' ?>
        </a>

        <!-- Pokaż odłożone / archiwum -->
        <a href="<?= $this->Url->build($buildUrl(['show_snoozed' => $showSnoozed ? '' : '1'])) ?>"
           class="btn btn-sm <?= $showSnoozed ? 'btn-warning' : 'btn-outline-warning' ?>" title="Pokaż odłożone karty">
            <i class="ri-zzz-line"></i> Odłożone
        </a>
        <a href="<?= $this->Url->build($buildUrl(['show_archived' => $showArchived ? '' : '1'])) ?>"
           class="btn btn-sm <?= $showArchived ? 'btn-secondary' : 'btn-outline-secondary' ?>" title="Pokaż starsze niż 30d opłacone">
            <i class="ri-archive-line"></i> Archiwum
        </a>

        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="offcanvas" data-bs-target="#kanbanFilters">
            <i class="ri-filter-line"></i> Filtry
            <?php if ($activeFilters > 0): ?><span class="badge bg-primary"><?= $activeFilters ?></span><?php endif; ?>
        </button>
    </div>

    <!-- ── Mini stats ───────────────────────────────────────────────────── -->
    <div class="card shadow-sm mb-3" id="kanbanStats">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-4">
            <div>
                <div class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">DSO bieżący</div>
                <div class="fw-bold fs-5"><?= $fnum0($stats['dso']) ?> <small class="text-muted">dni</small></div>
            </div>
            <div class="vr"></div>
            <div>
                <div class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">Inkaso bieżący miesiąc</div>
                <div class="fw-bold fs-5 text-success"><?= $fnum0($stats['collected_month']) ?> PLN</div>
            </div>
            <div class="vr"></div>
            <div>
                <div class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">Należności (aktywne)</div>
                <div class="fw-bold fs-5">
                    <?= $fnum0($stats['sum_pln']) ?> <small class="text-muted">PLN</small>
                    <?php if ($stats['sum_eur'] > 0): ?>
                        · <?= $fnum0($stats['sum_eur']) ?> <small class="text-muted">EUR</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="vr"></div>
            <div>
                <div class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">At-risk (overdue + spór)</div>
                <div class="fw-bold fs-5 text-danger">
                    <?= $fnum0($stats['at_risk_pln']) ?> <small class="text-muted">PLN</small>
                    <?php if ($stats['at_risk_eur'] > 0): ?>
                        · <?= $fnum0($stats['at_risk_eur']) ?> <small class="text-muted">EUR</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="vr"></div>
            <div>
                <div class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">Przeterminowane / Spór</div>
                <div class="fw-bold fs-5">
                    <span class="text-danger"><?= (int)$stats['overdue_count'] ?></span> ·
                    <span class="text-dark"><?= (int)$stats['dispute_count'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if (($stats['overdue_count'] ?? 0) > 15): ?>
        <div class="alert alert-danger py-2 mb-3 d-flex align-items-center gap-2">
            <i class="ri-error-warning-fill fs-5"></i>
            <strong>Masz <?= (int)$stats['overdue_count'] ?> faktur po terminie</strong> — sprawdź kolumnę „Przeterminowane".
        </div>
    <?php endif; ?>

    <!-- ── Pipeline funnel ───────────────────────────────────────────────── -->
    <?php if (($stats['funnel_total'] ?? 0) > 0): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 d-flex align-items-center gap-2 bg-light" data-bs-toggle="collapse" data-bs-target="#kanbanFunnel" style="cursor:pointer">
                <i class="ri-funnel-line text-primary"></i>
                <strong class="small">Pipeline rozliczeń</strong>
                <span class="text-muted small ms-auto"><?= (int)$stats['funnel_total'] ?> kart łącznie · klik aby rozwinąć/zwinąć</span>
                <i class="ri-arrow-down-s-line text-muted"></i>
            </div>
            <div class="collapse" id="kanbanFunnel">
                <div class="card-body py-3">
                    <div class="kanban-funnel">
                        <?php
                        $funnelColors = [
                            'in_term'  => '#0ea5e9',
                            'sent'     => '#3b82f6',
                            'due_soon' => '#f59e0b',
                            'overdue'  => '#dc2626',
                            'dispute'  => '#1e293b',
                            'paid'     => '#16a34a',
                        ];
                        $maxCount = max(array_column($stats['funnel'], 'count')) ?: 1;
                        foreach ($stats['funnel'] as $stage):
                            $color = $funnelColors[$stage['key']] ?? '#6b7280';
                            $widthPct = max(8, round(($stage['count'] / $maxCount) * 100));
                        ?>
                            <div class="kanban-funnel-row">
                                <div class="kanban-funnel-label small text-muted"><?= h($stage['label']) ?></div>
                                <div class="kanban-funnel-bar-wrap">
                                    <div class="kanban-funnel-bar" style="width:<?= $widthPct ?>%;background:<?= h($color) ?>">
                                        <?php if ($stage['count'] > 0): ?>
                                            <span class="kanban-funnel-count"><?= (int)$stage['count'] ?> · <?= $stage['pct'] ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    $paidCount = $stats['funnel'][5]['count'] ?? 0;
                    $totalCount = $stats['funnel_total'] ?? 0;
                    $conversionRate = $totalCount > 0 ? round(($paidCount / $totalCount) * 100, 1) : 0;
                    ?>
                    <div class="mt-3 d-flex flex-wrap gap-4 small">
                        <div>
                            <span class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">Konwersja na opłacone</span>
                            <div class="fw-bold fs-6 <?= $conversionRate >= 80 ? 'text-success' : ($conversionRate >= 50 ? 'text-warning' : 'text-danger') ?>">
                                <?= $conversionRate ?>% <small class="text-muted">(<?= (int)$paidCount ?> z <?= (int)$totalCount ?>)</small>
                            </div>
                        </div>
                        <div>
                            <span class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">Wąskie gardło</span>
                            <?php
                            $bottleneck = null;
                            $bottleneckCount = 0;
                            foreach (['overdue', 'dispute', 'due_soon'] as $col) {
                                foreach ($stats['funnel'] as $st) {
                                    if ($st['key'] === $col && $st['count'] > $bottleneckCount) {
                                        $bottleneck = $st;
                                        $bottleneckCount = $st['count'];
                                    }
                                }
                            }
                            ?>
                            <div class="fw-bold fs-6">
                                <?php if ($bottleneck): ?>
                                    <?= h($bottleneck['label']) ?>
                                    <small class="text-danger"><?= (int)$bottleneck['count'] ?></small>
                                <?php else: ?>
                                    <span class="text-success">brak ✓</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── Kanban board ──────────────────────────────────────────────────── -->
    <div class="kanban-scroll">
    <div class="kanban-board" id="kanbanBoard">
        <?php foreach ($columns as $key => $col): ?>
            <?php
            $cards = $buckets[$key] ?? [];
            $colCount = (int)($stats['count_by_col'][$key] ?? 0);
            $colSumPln = (float)($stats['count_by_col'][$key . '_sum_pln'] ?? 0);
            $colSumEur = (float)($stats['count_by_col'][$key . '_sum_eur'] ?? 0);
            $wipWarning = $key === 'overdue' && $colCount > 15;
            ?>
            <div class="kanban-col kanban-col-<?= h($key) ?>" data-col="<?= h($key) ?>">
                <div class="kanban-col-header bg-<?= h($col['color']) ?>-subtle border-<?= h($col['color']) ?>">
                    <div class="d-flex align-items-center gap-2">
                        <i class="<?= h($col['icon']) ?> text-<?= h($col['color']) ?>"></i>
                        <strong class="text-<?= h($col['color']) ?>"><?= h($col['label']) ?></strong>
                        <span class="badge bg-<?= h($col['color']) ?>"><?= $colCount ?></span>
                        <?php if ($wipWarning): ?>
                            <i class="ri-alarm-warning-fill text-danger ms-1" title="WIP-limit przekroczony"></i>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mt-1" title="<?= h($col['hint']) ?>">
                        <?php if ($colSumPln > 0): ?><?= $fnum0($colSumPln) ?> PLN<?php endif; ?>
                        <?php if ($colSumEur > 0): ?> · <?= $fnum0($colSumEur) ?> EUR<?php endif; ?>
                    </div>
                </div>
                <div class="kanban-col-body" data-col="<?= h($key) ?>">
                    <?php if (empty($cards)): ?>
                        <div class="kanban-empty text-muted small fst-italic">
                            <i class="ri-inbox-line"></i> brak kart
                        </div>
                    <?php endif; ?>
                    <?php foreach ($cards as $card): ?>
                        <?php $this->element('Reconciliations/kanban_card', ['card' => $card, 'severityColors' => $severityColors, 'compactMode' => $compactMode]); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($showSnoozed && !empty($buckets['snoozed'])): ?>
            <div class="kanban-col kanban-col-snoozed" data-col="snoozed">
                <div class="kanban-col-header bg-warning-subtle border-warning">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-zzz-line text-warning"></i>
                        <strong class="text-warning">Odłożone</strong>
                        <span class="badge bg-warning text-dark"><?= count($buckets['snoozed']) ?></span>
                    </div>
                </div>
                <div class="kanban-col-body" data-col="snoozed">
                    <?php foreach ($buckets['snoozed'] as $card): ?>
                        <?php $this->element('Reconciliations/kanban_card', ['card' => $card, 'severityColors' => $severityColors, 'compactMode' => $compactMode]); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div><!-- /kanban-board -->
    </div><!-- /kanban-scroll -->
</div>

<!-- ── Sidebar filtry ───────────────────────────────────────────────────── -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="kanbanFilters">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title"><i class="ri-filter-line"></i> Filtry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form method="get" id="kanbanFilterForm">
            <input type="hidden" name="compact" value="<?= $compactMode ? '1' : '' ?>">
            <input type="hidden" name="show_snoozed" value="<?= $showSnoozed ? '1' : '' ?>">
            <input type="hidden" name="show_archived" value="<?= $showArchived ? '1' : '' ?>">

            <div class="mb-3">
                <label class="form-label small text-muted">Kontrahent</label>
                <select name="contractor_nip" class="form-select form-select-sm" id="kanbanFilterContractor" style="width:100%">
                    <option value="">— wszyscy —</option>
                    <?php foreach ($contractorsForFilter as $c): ?>
                        <option value="<?= h($c['nip']) ?>" data-nip="<?= h($c['nip']) ?>" <?= $filterNip === $c['nip'] ? 'selected' : '' ?>>
                            <?= h($c['name']) ?> · NIP <?= h($c['nip']) ?> (<?= (int)$c['cnt'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small text-muted">Waluta</label>
                <select name="currency" class="form-select form-select-sm">
                    <option value="">— wszystkie —</option>
                    <?php foreach (['PLN', 'EUR', 'USD'] as $cur): ?>
                        <option value="<?= $cur ?>" <?= $filterCurrency === $cur ? 'selected' : '' ?>><?= $cur ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label small text-muted">Kwota brutto powyżej</label>
                <input type="number" name="min_amount" class="form-control form-control-sm" min="0" step="100"
                       value="<?= $filterMinAmount > 0 ? (int)$filterMinAmount : '' ?>" placeholder="np. 1000">
            </div>

            <div class="mb-3">
                <label class="form-label small text-muted">Przypisana do</label>
                <select name="assigned" class="form-select form-select-sm">
                    <option value="">— wszyscy —</option>
                    <option value="me" <?= $filterAssigned === 'me' ? 'selected' : '' ?>>Tylko moje</option>
                    <option value="unassigned" <?= $filterAssigned === 'unassigned' ? 'selected' : '' ?>>Bez przypisania</option>
                    <?php foreach ($usersForFilter as $u): ?>
                        <option value="<?= h($u['id']) ?>" <?= $filterAssigned === $u['id'] ? 'selected' : '' ?>>
                            <?= h($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="ri-check-line"></i> Zastosuj
                </button>
                <a href="<?= $this->Url->build(['action' => 'kanban']) ?>" class="btn btn-outline-secondary btn-sm">
                    Wyczyść
                </a>
            </div>

            <hr class="my-3">

            <h6 class="text-muted small text-uppercase">Zapisane widoki</h6>
            <div id="kanbanSavedViews" class="d-flex flex-column gap-1">
                <!-- ładowane dynamicznie z localStorage -->
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2 w-100" id="btnSaveView">
                <i class="ri-bookmark-line"></i> Zapisz bieżące jako preset
            </button>
        </form>
    </div>
</div>

<!-- ── Modale (note / snooze / dispute / assign / AI suggest) ──────────── -->
<div class="modal fade" id="kanbanCardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="kanbanCardModalTitle">Faktura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="kanbanCardModalBody"></div>
        </div>
    </div>
</div>

<!-- ── Bulk action bar (pojawia się gdy >0 zaznaczonych) ────────────────── -->
<div class="kanban-bulk-bar" id="kanbanBulkBar" style="display:none">
    <span><i class="ri-checkbox-multiple-line"></i> Zaznaczono: <strong id="kanbanBulkCount">0</strong></span>
    <button class="btn btn-sm btn-warning" data-bulk="snooze"><i class="ri-zzz-line"></i> Odłóż</button>
    <button class="btn btn-sm btn-dark" data-bulk="dispute"><i class="ri-scales-3-line"></i> Spór</button>
    <button class="btn btn-sm btn-info" data-bulk="assign"><i class="ri-user-line"></i> Przypisz</button>
    <button class="btn btn-sm btn-outline-light" id="kanbanBulkClear"><i class="ri-close-line"></i> Anuluj</button>
</div>

<!-- ── Toast container ─────────────────────────────────────────────────── -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="kanbanToasts"></div>

<style>
/* ── Page wrap — używamy pełnej szerokości viewportu ────────────────── */
.kanban-page-wrap {
    padding: 14px 16px 14px 16px;
    max-width: 100%;
}
.kanban-page-wrap .card.shadow-sm { border: 1px solid #e5e7eb; }

/* ── Scroll wrapper — osobny element trzymający overflow ─────────────── */
.kanban-scroll {
    overflow-x: auto;
    overflow-y: visible;
    /* Wyjść poza padding container-fluid layoutu Velzon (lewy/prawy gutter) */
    margin-left: calc(-1 * var(--bs-gutter-x, 1.5rem) * 0.5);
    margin-right: calc(-1 * var(--bs-gutter-x, 1.5rem) * 0.5);
    padding: 4px calc(var(--bs-gutter-x, 1.5rem) * 0.5) 18px calc(var(--bs-gutter-x, 1.5rem) * 0.5);
    scrollbar-width: thin;
}
.kanban-scroll::-webkit-scrollbar { height: 10px; }
.kanban-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 5px; }
.kanban-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.kanban-scroll::-webkit-scrollbar-track { background: transparent; }

/* ── Board (kontener kolumn) — naturalna szerokość treści ──────────── */
.kanban-board {
    display: flex;
    gap: 14px;
    /* Naturalna szerokość = suma kolumn + gapy. Nigdy mniej niż 100% (żeby tło zajmowało całe pole). */
    width: max-content;
    min-width: 100%;
    min-height: 72vh;
    padding: 4px 0 8px 0;
}

/* ── Kolumna ─────────────────────────────────────────────────────────── */
.kanban-col {
    flex: 0 0 340px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 200px);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
    border: 1px solid #e5e7eb;
    transition: box-shadow .2s ease;
}
.kanban-col:hover { box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08); }

.kanban-col-header {
    padding: 12px 14px 10px 14px;
    border-radius: 10px 10px 0 0;
    flex-shrink: 0;
    position: sticky; top: 0; z-index: 2;
    background-color: inherit;
}
.kanban-col-header strong {
    font-size: .82rem;
    letter-spacing: .01em;
}
.kanban-col-header .badge {
    font-size: .68rem;
    font-weight: 700;
    padding: .25em .55em;
}
.kanban-col-header .text-muted.small {
    font-size: .72rem;
    margin-top: 4px;
    font-weight: 500;
}

/* ── Body kolumny — lista kart ─────────────────────────────────────── */
.kanban-col-body {
    flex: 1;
    overflow-y: auto;
    padding: 10px 10px 12px 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 80px;
    scrollbar-width: thin;
}
.kanban-col-body::-webkit-scrollbar { width: 6px; }
.kanban-col-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
.kanban-col-body::-webkit-scrollbar-track { background: transparent; }

.kanban-empty {
    text-align: center;
    padding: 28px 12px;
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    background: #fff;
    color: #94a3b8 !important;
}
.kanban-empty i { font-size: 1.5rem; display: block; margin-bottom: 4px; opacity: .5; }

/* ── Karta ───────────────────────────────────────────────────────────── */
.kanban-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-left: 4px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 12px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    cursor: grab;
    position: relative;
    font-size: .82rem;
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.kanban-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.06);
    border-color: #cbd5e1;
}
.kanban-card:active { cursor: grabbing; }
.kanban-card.sortable-ghost { opacity: .35; background: #dbeafe; border-color: #3b82f6; }
.kanban-card.sortable-drag { transform: rotate(2deg); box-shadow: 0 12px 24px rgba(15,23,42,.18); }
.kanban-card.selected {
    background: #eff6ff;
    border-color: #3b82f6;
    border-left-color: #3b82f6 !important;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
}
.kanban-card.compact { padding: 6px 10px; font-size: .76rem; }
.kanban-card.compact .kanban-card-num { font-size: .78rem; }

.kanban-card.pinned {
    border-top: 2px solid #f59e0b;
}
.kanban-card.pinned::after {
    content: "📌"; position: absolute; top: -8px; right: -6px;
    font-size: .9rem;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,.2));
}

/* Severity — subtelny ton tła zamiast tylko border-left */
.kanban-card[data-severity="critical"] {
    background: linear-gradient(135deg, #fff 0%, #fff5f5 100%);
}
.kanban-card[data-severity="high"] {
    background: linear-gradient(135deg, #fff 0%, #fff7ed 100%);
}

.kanban-card.stale {
    animation: pulse-red 2s infinite;
}
@keyframes pulse-red {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220,38,38,0.4); }
    50%      { box-shadow: 0 0 0 6px rgba(220,38,38,0.0); }
}

/* ── Treść karty ────────────────────────────────────────────────────── */
.kanban-card-num {
    font-weight: 700;
    font-size: .88rem;
    color: #1e293b;
    line-height: 1.25;
    letter-spacing: .005em;
}
.kanban-card-contractor {
    color: #64748b;
    font-size: .74rem;
    line-height: 1.3;
    margin-top: 2px;
}
.kanban-card-amount {
    font-weight: 700;
    font-size: .95rem;
    color: #0f172a;
    letter-spacing: -.01em;
}
.kanban-card-amount.text-success { color: #16a34a !important; }

.kanban-card-due {
    font-size: .72rem;
    color: #64748b;
    margin-top: 5px;
    line-height: 1.3;
}
.kanban-card-due.overdue { color: #dc2626; font-weight: 600; }

.kanban-progress {
    height: 4px;
    background: #e2e8f0;
    border-radius: 3px;
    margin-top: 6px;
    overflow: hidden;
}
.kanban-progress > div {
    height: 100%;
    background: linear-gradient(90deg, #16a34a 0%, #22c55e 100%);
    transition: width .3s ease;
    border-radius: 3px;
}

.kanban-card-actions {
    display: flex; gap: 4px; margin-top: 8px; flex-wrap: wrap;
}
.kanban-card-actions .btn {
    padding: 2px 8px; font-size: .68rem; line-height: 1.4;
}

.kanban-card-meta {
    display: flex; gap: 6px; align-items: center; margin-top: 6px;
    padding-top: 6px;
    border-top: 1px dashed #e5e7eb;
    font-size: .68rem; color: #64748b;
}
.kanban-card-avatar {
    width: 20px; height: 20px; border-radius: 50%;
    background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
    color: #fff; font-size: .58rem; font-weight: 700;
    display: inline-flex; align-items: center; justify-content: center;
    box-shadow: 0 1px 2px rgba(0,0,0,.08);
}
.kanban-card-checkbox {
    cursor: pointer;
    accent-color: #3b82f6;
}

/* ── Bulk action bar (przyklejony dół) ─────────────────────────────── */
.kanban-bulk-bar {
    position: fixed; bottom: 18px; left: 50%; transform: translateX(-50%);
    background: #1e293b; color: #fff;
    padding: 10px 18px; border-radius: 12px;
    display: flex; align-items: center; gap: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.28);
    z-index: 1080;
    font-size: .85rem;
}
.kanban-bulk-bar .btn { font-size: .78rem; padding: 4px 12px; }

/* ── Stats card (na górze) ─────────────────────────────────────────── */
#kanbanStats .card-body { gap: 18px !important; }
#kanbanStats .vr { background-color: #e5e7eb; }

/* ── Min-width na karty wewnątrz kolumny — żeby nigdy nie były węższe niż 280 ─ */
.kanban-col-body > * { min-width: 0; max-width: 100%; }

/* ── Lepsze kolory kolumn — każda ma subtelny accent w headerze ─── */
.kanban-col-in_term  .kanban-col-header { background: linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%); border-bottom: 1px solid #bae6fd; }
.kanban-col-sent     .kanban-col-header { background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%); border-bottom: 1px solid #bfdbfe; }
.kanban-col-due_soon .kanban-col-header { background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%); border-bottom: 1px solid #fde68a; }
.kanban-col-overdue  .kanban-col-header { background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%); border-bottom: 1px solid #fecaca; }
.kanban-col-dispute  .kanban-col-header { background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%); border-bottom: 1px solid #cbd5e1; }
.kanban-col-paid     .kanban-col-header { background: linear-gradient(180deg, #f0fdf4 0%, #dcfce7 100%); border-bottom: 1px solid #bbf7d0; }
.kanban-col-snoozed  .kanban-col-header { background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%); border-bottom: 1px solid #fde68a; }

/* ── Responsywne: na małych ekranach kolumny zachowują się ok ──────── */
@media (max-width: 768px) {
    .kanban-col { flex: 0 0 88vw; }
    .kanban-page-wrap { padding: 8px; }
}

/* Pipeline funnel */
.kanban-funnel { display: flex; flex-direction: column; gap: 6px; }
.kanban-funnel-row { display: flex; align-items: center; gap: 10px; }
.kanban-funnel-label { width: 100px; flex-shrink: 0; text-align: right; }
.kanban-funnel-bar-wrap { flex: 1; background: #f1f5f9; border-radius: 4px; overflow: hidden; height: 22px; }
.kanban-funnel-bar {
    height: 100%;
    display: flex; align-items: center; padding: 0 8px;
    color: #fff; font-weight: 600; font-size: .72rem;
    border-radius: 4px;
    transition: width .3s ease;
    min-width: 2px;
}
.kanban-funnel-count { white-space: nowrap; }

/* Select2 — Kanban offcanvas */
#kanbanFilters .select2-container--default .select2-selection--single {
    height: calc(1.5em + 0.5rem + 2px); line-height: 1.5; font-size: .875rem; padding: 0 .5rem;
}
#kanbanFilters .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: calc(1.5em + 0.4rem); padding-left: 0; }
#kanbanFilters .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(1.5em + 0.5rem); }
.select2-dropdown { z-index: 1090 !important; }
</style>

<?= $this->Html->script('https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js', ['block' => true]); ?>

<script>
(function () {
    'use strict';

    var csrfToken = '<?= h($this->request->getAttribute('csrfToken') ?? '') ?>';
    var fmt = function (v) { return parseFloat(v||0).toFixed(2).replace('.', ','); };
    var esc = function (s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

    function showToast(msg, type) {
        type = type || 'success';
        var bg = { success:'bg-success', danger:'bg-danger', warning:'bg-warning', info:'bg-info' }[type] || 'bg-secondary';
        var el = document.createElement('div');
        el.className = 'toast align-items-center text-white ' + bg + ' border-0';
        el.setAttribute('role', 'alert');
        el.innerHTML = '<div class="d-flex"><div class="toast-body">' + esc(msg) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        document.getElementById('kanbanToasts').appendChild(el);
        var t = new bootstrap.Toast(el, { delay: 3500 });
        t.show();
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    }

    // ── SortableJS drag-drop ────────────────────────────────────────
    document.querySelectorAll('.kanban-col-body').forEach(function (col) {
        new Sortable(col, {
            group: 'kanban',
            animation: 150,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            handle: '.kanban-card',
            filter: '.kanban-card-actions, .kanban-card-checkbox, button, a, input, select',
            preventOnFilter: false,
            onEnd: function (evt) {
                var card = evt.item;
                var newCol = evt.to.dataset.col;
                var oldCol = evt.from.dataset.col;
                if (newCol === oldCol) return;

                var invoiceId = card.dataset.invoiceId;
                if (!invoiceId) return;

                var fd = new FormData();
                fd.append('target_column', newCol);

                // Dla 'dispute' zapytaj o powód
                if (newCol === 'dispute') {
                    var reason = prompt('Powód oznaczenia jako spór / windykacja:');
                    if (reason === null) {
                        evt.from.appendChild(card); // rollback
                        return;
                    }
                    fd.append('reason', reason);
                }
                // Dla 'snoozed' zapytaj o datę
                if (newCol === 'snoozed') {
                    var days = prompt('Odłóż na ile dni? (np. 7)', '7');
                    if (days === null) {
                        evt.from.appendChild(card);
                        return;
                    }
                    var d = new Date();
                    d.setDate(d.getDate() + (parseInt(days, 10) || 7));
                    var dStr = d.toISOString().substring(0, 10);
                    fd.append('snooze_until', dStr);
                }

                fetch('/rozliczenia/kanban/move/' + invoiceId, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, body: d }; }); })
                .then(function (res) {
                    if (!res.ok) {
                        showToast(res.body.error || 'Błąd przeniesienia', 'danger');
                        evt.from.appendChild(card);
                        return;
                    }
                    showToast('Przeniesiono do "' + newCol + '"', 'success');
                })
                .catch(function (e) {
                    showToast('Błąd sieci: ' + e.message, 'danger');
                    evt.from.appendChild(card);
                });
            }
        });
    });

    // ── Bulk select ─────────────────────────────────────────────────
    var bulkSelection = new Set();
    function updateBulkBar() {
        var bar = document.getElementById('kanbanBulkBar');
        document.getElementById('kanbanBulkCount').textContent = bulkSelection.size;
        bar.style.display = bulkSelection.size > 0 ? '' : 'none';
    }

    document.addEventListener('click', function (e) {
        var cb = e.target.closest('.kanban-card-checkbox');
        if (!cb) return;
        var card = cb.closest('.kanban-card');
        var id = card.dataset.invoiceId;
        if (cb.checked) {
            bulkSelection.add(id);
            card.classList.add('selected');
        } else {
            bulkSelection.delete(id);
            card.classList.remove('selected');
        }
        updateBulkBar();
    });

    document.getElementById('kanbanBulkClear').addEventListener('click', function () {
        bulkSelection.clear();
        document.querySelectorAll('.kanban-card.selected').forEach(function (c) { c.classList.remove('selected'); });
        document.querySelectorAll('.kanban-card-checkbox:checked').forEach(function (cb) { cb.checked = false; });
        updateBulkBar();
    });

    document.querySelectorAll('[data-bulk]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.dataset.bulk;
            if (bulkSelection.size === 0) return;
            var payload = {};
            if (action === 'snooze') {
                var days = prompt('Odłóż wszystkie zaznaczone na ile dni?', '7');
                if (days === null) return;
                var d = new Date(); d.setDate(d.getDate() + (parseInt(days, 10) || 7));
                payload.until = d.toISOString().substring(0, 10);
            } else if (action === 'dispute') {
                payload.reason = prompt('Powód sporu (wspólny):', 'Bulk action');
                if (payload.reason === null) return;
            } else if (action === 'assign') {
                var users = window.kanbanUsers || [];
                if (!users.length) { showToast('Brak użytkowników do przypisania', 'warning'); return; }
                var opts = users.map(function (u) { return u.id + ' = ' + u.name; }).join('\n');
                var pick = prompt('Wpisz ID użytkownika (puste = bez przypisania):\n\n' + opts);
                if (pick === null) return;
                payload.user_id = pick.trim();
            }

            var fd = new FormData();
            fd.append('action', action);
            Array.from(bulkSelection).forEach(function (id) { fd.append('ids[]', id); });
            Object.keys(payload).forEach(function (k) { fd.append('payload[' + k + ']', payload[k]); });

            fetch('/rozliczenia/kanban/bulk-action', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error) { showToast(d.error, 'danger'); return; }
                showToast('Zastosowano do ' + d.affected + ' faktur', 'success');
                setTimeout(function () { location.reload(); }, 800);
            });
        });
    });

    // Lista użytkowników dla bulk assign
    window.kanbanUsers = <?= json_encode($usersForFilter, JSON_UNESCAPED_UNICODE) ?>;

    // ── Card actions (kebab menu) ──────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-card-action]');
        if (!btn) return;
        e.preventDefault();
        var card = btn.closest('.kanban-card');
        var invoiceId = card.dataset.invoiceId;
        var action = btn.dataset.cardAction;

        if (action === 'open') {
            window.open('/invoices/view/' + invoiceId, '_blank');
            return;
        }
        if (action === 'note') {
            openCardModal(invoiceId, card, 'notes');
            return;
        }
        if (action === 'snooze') {
            var days = prompt('Odłóż na ile dni?', '7');
            if (days === null) return;
            var d = new Date(); d.setDate(d.getDate() + (parseInt(days, 10) || 7));
            fetch('/rozliczenia/kanban/snooze/' + invoiceId, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ until: d.toISOString().substring(0, 10) })
            })
            .then(function (r) { return r.json(); })
            .then(function () { showToast('Odłożono', 'success'); setTimeout(function () { location.reload(); }, 500); });
            return;
        }
        if (action === 'pin') {
            fetch('/rozliczenia/kanban/pin/' + invoiceId, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                card.classList.toggle('pinned', d.pinned);
                showToast(d.pinned ? 'Przypięto' : 'Odpięto', 'success');
            });
            return;
        }
        if (action === 'ai-suggest') {
            openCardModal(invoiceId, card, 'ai');
            return;
        }
        if (action === 'assign') {
            openCardModal(invoiceId, card, 'assign');
            return;
        }
        if (action === 'reminder') {
            openCardModal(invoiceId, card, 'reminder');
            return;
        }
    });

    function openCardModal(invoiceId, card, tab) {
        var num = card.querySelector('.kanban-card-num')?.textContent || '';
        document.getElementById('kanbanCardModalTitle').textContent = num;
        var body = document.getElementById('kanbanCardModalBody');
        body.innerHTML = '<div class="p-3 text-center"><div class="spinner-border spinner-border-sm"></div> ładowanie…</div>';

        var modal = new bootstrap.Modal(document.getElementById('kanbanCardModal'));
        modal.show();

        if (tab === 'notes') {
            renderNotesTab(body, invoiceId);
        } else if (tab === 'ai') {
            renderAiTab(body, invoiceId);
        } else if (tab === 'assign') {
            renderAssignTab(body, invoiceId, card);
        } else if (tab === 'reminder') {
            renderReminderTab(body, invoiceId);
        }
    }

    function renderReminderTab(body, invoiceId) {
        fetch('/rozliczenia/kanban/reminder-info/' + invoiceId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error) { body.innerHTML = '<div class="p-3 text-danger">' + esc(d.error) + '</div>'; return; }

                // Sugerowana treść maila — różna dla overdue vs pending
                var suggested = '';
                if (d.days_overdue > 0) {
                    suggested = 'Szanowni Państwo,\n\nUprzejmie informujemy, że upłynął termin płatności faktury '
                              + d.fullnumber + ' (termin: ' + d.paymentdate + ').\n\nProsimy o jak najszybsze uregulowanie należności w wysokości '
                              + fmt(d.amount) + ' ' + d.currency + '.\n\nZ poważaniem';
                } else if (d.days_to_due === 0) {
                    suggested = 'Szanowni Państwo,\n\nUprzejmie przypominamy, że dziś upływa termin płatności faktury '
                              + d.fullnumber + ' na kwotę ' + fmt(d.amount) + ' ' + d.currency + '.\n\nZ poważaniem';
                } else {
                    suggested = 'Szanowni Państwo,\n\nUprzejmie przypominamy o zbliżającym się terminie płatności faktury '
                              + d.fullnumber + ' (termin: ' + d.paymentdate + ') na kwotę ' + fmt(d.amount) + ' ' + d.currency + '.\n\nZ poważaniem';
                }

                var html = '<div class="p-3">';
                html += '<div class="alert alert-info py-2 small mb-3">';
                html += '<strong>' + esc(d.fullnumber) + '</strong> · ' + esc(d.contractor_name);
                html += ' · <strong>' + fmt(d.amount) + ' ' + esc(d.currency) + '</strong> do zapłaty';
                if (d.days_overdue > 0) html += ' · <span class="text-danger">' + d.days_overdue + ' dni po terminie</span>';
                else if (d.days_to_due === 0) html += ' · <span class="text-warning">dziś termin</span>';
                else html += ' · termin za ' + d.days_to_due + ' dni';
                html += '</div>';

                html += '<form id="kanbanReminderForm">';
                html += '<div class="mb-2"><label class="form-label small text-muted">Adres email odbiorcy</label>';
                html += '<input type="email" name="email" class="form-control form-control-sm" required value="' + esc(d.default_email) + '" placeholder="email@firma.pl">';
                if (!d.default_email) html += '<div class="text-warning small mt-1"><i class="ri-error-warning-line"></i> Brak emaila w bazie kontrahenta — wpisz ręcznie.</div>';
                html += '</div>';
                html += '<div class="mb-2"><label class="form-label small text-muted">Własna wiadomość (opcjonalne, zastąpi domyślny tekst)</label>';
                html += '<textarea name="message" class="form-control form-control-sm" rows="8" placeholder="Pozostaw puste aby użyć szablonu domyślnego…">' + esc(suggested) + '</textarea>';
                html += '<div class="form-text small">Domyślnie email zawiera nr faktury, kwotę, termin i statusowy banner. Tu możesz dodać dodatkową treść.</div>';
                html += '</div>';
                html += '<div class="d-flex gap-2 mt-3">';
                html += '<button type="submit" class="btn btn-warning btn-sm"><i class="ri-mail-send-line"></i> Wyślij przypomnienie</button>';
                html += '<button type="button" class="btn btn-link btn-sm" id="kanbanReminderUseDefault">Użyj szablonu (bez własnej wiadomości)</button>';
                html += '</div>';
                html += '</form>';
                html += '</div>';
                body.innerHTML = html;

                document.getElementById('kanbanReminderUseDefault').addEventListener('click', function () {
                    body.querySelector('textarea[name=message]').value = '';
                });

                document.getElementById('kanbanReminderForm').addEventListener('submit', function (ev) {
                    ev.preventDefault();
                    var fd = new FormData(ev.target);
                    var btn = ev.target.querySelector('button[type=submit]');
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Wysyłam…';
                    fetch('/rozliczenia/kanban/send-reminder/' + invoiceId, {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, body: d }; }); })
                    .then(function (res) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ri-mail-send-line"></i> Wyślij przypomnienie';
                        if (!res.ok) {
                            showToast(res.body.error || 'Błąd wysyłki', 'danger');
                            return;
                        }
                        showToast('Wysłano: ' + (res.body.subject || 'OK'), 'success');
                        bootstrap.Modal.getInstance(document.getElementById('kanbanCardModal'))?.hide();
                    });
                });
            });
    }

    function renderNotesTab(body, invoiceId) {
        fetch('/rozliczenia/kanban/notes/' + invoiceId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var html = '<div class="p-3">';
                html += '<form id="kanbanNoteForm" class="mb-3 d-flex gap-2">';
                html += '<select name="note_type" class="form-select form-select-sm" style="width:130px">';
                html += '<option value="note">Notatka</option>';
                html += '<option value="phone_call">Rozmowa</option>';
                html += '<option value="email">Email</option>';
                html += '<option value="reminder">Przypomnienie</option>';
                html += '</select>';
                html += '<input type="text" name="body" class="form-control form-control-sm" placeholder="Wpisz notatkę…" required>';
                html += '<button type="submit" class="btn btn-sm btn-primary"><i class="ri-add-line"></i></button>';
                html += '</form>';
                html += '<div class="list-group list-group-flush" id="kanbanNotesList">';
                if (!d.notes || !d.notes.length) {
                    html += '<div class="text-muted small fst-italic p-2">Brak notatek.</div>';
                } else {
                    d.notes.forEach(function (n) {
                        var typeBadge = {
                            'note': ['secondary', 'Notatka'],
                            'system': ['light', 'System'],
                            'reminder': ['warning', 'Przypomnienie'],
                            'phone_call': ['info', 'Rozmowa'],
                            'email': ['primary', 'Email'],
                        }[n.note_type] || ['secondary', n.note_type];
                        html += '<div class="list-group-item py-2">';
                        html += '<div class="d-flex align-items-center gap-2 mb-1">';
                        html += '<span class="badge bg-' + typeBadge[0] + '-subtle text-' + typeBadge[0] + ' border" style="font-size:.65rem">' + typeBadge[1] + '</span>';
                        html += '<span class="small text-muted">' + esc(n.user_name) + ' · ' + esc(n.created) + '</span>';
                        html += '</div>';
                        html += '<div class="small">' + esc(n.body) + '</div>';
                        html += '</div>';
                    });
                }
                html += '</div></div>';
                body.innerHTML = html;

                document.getElementById('kanbanNoteForm').addEventListener('submit', function (ev) {
                    ev.preventDefault();
                    var fd = new FormData(ev.target);
                    fetch('/rozliczenia/kanban/note/' + invoiceId, {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (rd) {
                        if (rd.success) {
                            renderNotesTab(body, invoiceId);
                            showToast('Notatka dodana', 'success');
                        } else {
                            showToast(rd.error || 'Błąd', 'danger');
                        }
                    });
                });
            });
    }

    function renderAiTab(body, invoiceId) {
        body.innerHTML = '<div class="p-3 text-center"><div class="spinner-border"></div><div class="mt-2 small text-muted">AI analizuje sytuację…</div></div>';
        fetch('/rozliczenia/kanban/ai-suggest/' + invoiceId, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, body: d }; }); })
        .then(function (res) {
            if (!res.ok) {
                body.innerHTML = '<div class="p-3 text-danger small"><i class="ri-error-warning-line"></i> ' + esc(res.body.error || 'Błąd AI') + '</div>';
                return;
            }
            var html = '<div class="p-3">';
            html += '<div class="alert alert-info py-2 small"><i class="ri-sparkling-line"></i> ' + esc(res.body.summary || '—') + '</div>';
            (res.body.suggestions || []).forEach(function (s) {
                var col = { high: 'danger', medium: 'warning', low: 'secondary' }[s.urgency] || 'secondary';
                html += '<div class="card mb-2"><div class="card-body py-2">';
                html += '<div class="d-flex justify-content-between mb-1">';
                html += '<strong class="small">' + esc(s.action) + '</strong>';
                html += '<span class="badge bg-' + col + '-subtle text-' + col + ' border">' + esc(s.urgency) + '</span>';
                html += '</div>';
                html += '<div class="small text-muted">' + esc(s.description) + '</div>';
                html += '</div></div>';
            });
            html += '</div>';
            body.innerHTML = html;
        });
    }

    function renderAssignTab(body, invoiceId, card) {
        var users = window.kanbanUsers || [];
        var currentAssigned = card.dataset.assignedTo || '';
        var html = '<div class="p-3"><h6>Przypisz odpowiedzialność</h6>';
        html += '<div class="list-group">';
        html += '<button class="list-group-item list-group-item-action" data-assign-user-id=""><i class="ri-user-unfollow-line"></i> Bez przypisania' + (currentAssigned === '' ? ' <i class="ri-check-line text-success ms-2"></i>' : '') + '</button>';
        users.forEach(function (u) {
            html += '<button class="list-group-item list-group-item-action" data-assign-user-id="' + esc(u.id) + '">' + esc(u.name);
            html += ' <small class="text-muted">' + esc(u.email) + '</small>';
            if (u.id === currentAssigned) html += ' <i class="ri-check-line text-success ms-2"></i>';
            html += '</button>';
        });
        html += '</div></div>';
        body.innerHTML = html;

        body.querySelectorAll('[data-assign-user-id]').forEach(function (b) {
            b.addEventListener('click', function () {
                var uid = b.dataset.assignUserId;
                var fd = new FormData();
                fd.append('user_id', uid);
                fetch('/rozliczenia/kanban/assign/' + invoiceId, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                })
                .then(function (r) { return r.json(); })
                .then(function () {
                    showToast('Przypisanie zmienione', 'success');
                    setTimeout(function () { location.reload(); }, 500);
                });
            });
        });
    }

    // ── Saved views (localStorage) ───────────────────────────────────
    function renderSavedViews() {
        var raw = localStorage.getItem('kanbanSavedViews') || '[]';
        var views = JSON.parse(raw);
        var container = document.getElementById('kanbanSavedViews');
        if (!container) return;
        if (!views.length) {
            container.innerHTML = '<div class="text-muted small fst-italic">Brak zapisanych presetów.</div>';
            return;
        }
        container.innerHTML = views.map(function (v, i) {
            return '<div class="d-flex gap-1"><a href="' + esc(v.url) + '" class="btn btn-sm btn-outline-primary flex-grow-1 text-start"><i class="ri-bookmark-fill"></i> ' + esc(v.name) + '</a><button class="btn btn-sm btn-outline-danger" data-delete-view="' + i + '"><i class="ri-close-line"></i></button></div>';
        }).join('');

        container.querySelectorAll('[data-delete-view]').forEach(function (b) {
            b.addEventListener('click', function () {
                var idx = parseInt(b.dataset.deleteView, 10);
                views.splice(idx, 1);
                localStorage.setItem('kanbanSavedViews', JSON.stringify(views));
                renderSavedViews();
            });
        });
    }

    document.getElementById('btnSaveView').addEventListener('click', function () {
        var name = prompt('Nazwa presetu:', 'Mój widok');
        if (!name) return;
        var raw = localStorage.getItem('kanbanSavedViews') || '[]';
        var views = JSON.parse(raw);
        views.push({ name: name, url: location.search ? location.pathname + location.search : location.pathname });
        localStorage.setItem('kanbanSavedViews', JSON.stringify(views));
        renderSavedViews();
        showToast('Preset zapisany', 'success');
    });

    renderSavedViews();

    // ── Select2 dla filtra kontrahenta ───────────────────────────────
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('#kanbanFilterContractor').select2({
            placeholder: '— wszyscy —',
            allowClear: true,
            width: '100%',
            language: { noResults: function () { return 'Brak wyników'; }, searching: function () { return 'Szukam…'; } },
            matcher: function (params, data) {
                if (!params.term) return data;
                if (!data.id) return null;
                var term = params.term.toLowerCase();
                var txt = (data.text || '').toLowerCase();
                var nip = jQuery(data.element).data('nip') || '';
                return (txt.indexOf(term) > -1 || String(nip).indexOf(term) > -1) ? data : null;
            }
        });
    }
})();
</script>

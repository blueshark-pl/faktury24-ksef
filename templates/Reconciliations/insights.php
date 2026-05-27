<?php
/**
 * @var \App\View\AppView $this
 * @var array $kpi
 * @var array $aging
 * @var array $dsoTrend
 * @var array $heatmap
 * @var array $cashflow
 * @var array $topDebtors
 * @var int   $debtorsTotal
 * @var array $debtorTotals
 * @var array $paymentDays
 * @var array $capital
 * @var array $recentUnmatched
 * @var array $notifications
 * @var string $period
 * @var array  $periodRange
 */

$this->assign('title', 'Insights — rozliczenia');

$fnum  = static fn ($v) => number_format((float)$v, 2, ',', ' ');
$fnum0 = static fn ($v) => number_format((float)$v, 0, ',', ' ');
$fdate = static function ($v): string {
    if ($v === null) return '—';
    if ($v instanceof \DateTimeInterface) return $v->format('d.m.Y');
    if (is_object($v) && method_exists($v, 'format')) return $v->format('d.m.Y');
    return substr((string)$v, 0, 10);
};

// Helper: render kwoty per waluta jako horizontal lista
$renderCurrencies = function (array $perCurr) use ($fnum) {
    if (empty($perCurr)) return '<span class="text-muted">—</span>';
    $orderedKeys = array_unique(array_merge(['PLN', 'EUR'], array_keys($perCurr)));
    $parts = [];
    foreach ($orderedKeys as $c) {
        if (!isset($perCurr[$c])) continue;
        $isPln = $c === 'PLN';
        $cls   = $isPln ? 'text-primary' : 'text-success';
        $parts[] = '<span class="fw-semibold ' . $cls . '">' . $fnum($perCurr[$c]) . '&nbsp;' . h($c) . '</span>';
    }
    return implode(' <span class="text-muted">+</span> ', $parts);
};

// Helper dla trendu DSO
$dsoTrendBadge = function ($delta) {
    if ($delta === null) return '';
    if ($delta === 0) return '<span class="badge bg-secondary-subtle text-secondary border ms-1" style="font-size:.65em">→ stabilne</span>';
    $sign  = $delta > 0 ? '+' : '';
    $icon  = $delta > 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line';
    // Wzrost DSO = wolniejsze płatności = źle
    $cls   = $delta > 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success';
    return '<span class="badge ' . $cls . ' border ms-1" style="font-size:.65em"><i class="' . $icon . '"></i> ' . $sign . $delta . ' dni</span>';
};

// Helper dla trendu inkasa
$collectedTrend = function (array $current, array $prev) use ($fnum) {
    if (empty($current) && empty($prev)) return '';
    // Suma w PLN (proste przybliżenie)
    $sumCur  = ($current['PLN'] ?? 0) + ($current['EUR'] ?? 0) * 4.3;
    $sumPrev = ($prev['PLN'] ?? 0) + ($prev['EUR'] ?? 0) * 4.3;
    if ($sumPrev <= 0) return '';
    $pct = (($sumCur - $sumPrev) / $sumPrev) * 100;
    $sign = $pct > 0 ? '+' : '';
    $cls  = $pct >= 0 ? 'text-success' : 'text-danger';
    return '<span class="' . $cls . ' fw-semibold" style="font-size:.75em">' . $sign . number_format($pct, 0) . '% vs poprz.</span>';
};

$periods = [
    '7d'  => '7 dni',
    '30d' => '30 dni',
    '3m'  => '3 mies.',
    '6m'  => '6 mies.',
    '1y'  => '1 rok',
    'fy'  => 'Rok obrotowy',
    'all' => 'Cały okres',
];
?>

<div class="container-fluid py-3">
    <!-- ── Toolbar ────────────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <h4 class="mb-0">
            <i class="ri-bar-chart-2-line text-primary me-1"></i>
            Insights
        </h4>
        <small class="text-muted"><?= h($periodRange['label'] ?? '') ?> · <?= h($periodRange['from'] ?? '') ?> → <?= h($periodRange['to'] ?? '') ?></small>

        <!-- Period selector -->
        <div class="btn-group btn-group-sm ms-auto" role="group">
            <?php foreach ($periods as $key => $label): ?>
                <a href="?period=<?= h($key) ?>"
                   class="btn btn-sm <?= $period === $key ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= h($label) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <a href="<?= $this->Url->build(['action' => 'index']) ?>"
           class="btn btn-sm btn-outline-secondary">
            <i class="ri-arrow-left-line me-1"></i>Powrót
        </a>
    </div>

    <!-- ── HERO KPI cards ─────────────────────────────────────────────────── -->
    <div class="row g-2 mb-3">
        <!-- Należności -->
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="ri-wallet-3-line text-primary fs-5"></i>
                        <small class="text-muted text-uppercase" style="letter-spacing:.04em;font-size:.7rem">Należności</small>
                    </div>
                    <div class="fw-bold">
                        <?= $renderCurrencies($kpi['receivables'] ?? []) ?>
                    </div>
                    <small class="text-muted">do zapłaty łącznie</small>
                </div>
            </div>
        </div>

        <!-- Przeterminowane -->
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100 <?= !empty($kpi['overdue']) ? 'border-danger' : '' ?>">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="ri-time-line text-danger fs-5"></i>
                        <small class="text-muted text-uppercase" style="letter-spacing:.04em;font-size:.7rem">Przeterminowane</small>
                        <?php if (($kpi['overdue_count'] ?? 0) > 0): ?>
                            <span class="badge bg-danger ms-auto"><?= (int)$kpi['overdue_count'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="fw-bold">
                        <?= $renderCurrencies($kpi['overdue'] ?? []) ?>
                    </div>
                    <small class="text-muted">wymagają działania</small>
                </div>
            </div>
        </div>

        <!-- DSO -->
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="ri-calendar-check-line text-info fs-5"></i>
                        <small class="text-muted text-uppercase" style="letter-spacing:.04em;font-size:.7rem">DSO</small>
                        <small class="text-muted ms-auto" style="font-size:.65rem" title="Days Sales Outstanding">?</small>
                    </div>
                    <div class="fw-bold fs-4">
                        <?php if ($kpi['dso'] !== null): ?>
                            <?= (int)$kpi['dso'] ?> <span class="text-muted fs-6">dni</span>
                            <?= $dsoTrendBadge($kpi['dso_trend']) ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted">średni czas inkasa</small>
                </div>
            </div>
        </div>

        <!-- Inkaso w okresie -->
        <div class="col-md-6 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="ri-funds-line text-success fs-5"></i>
                        <small class="text-muted text-uppercase" style="letter-spacing:.04em;font-size:.7rem">Inkaso</small>
                        <?php if (($kpi['collected_count'] ?? 0) > 0): ?>
                            <span class="badge bg-success-subtle text-success ms-auto"><?= (int)$kpi['collected_count'] ?>×</span>
                        <?php endif; ?>
                    </div>
                    <div class="fw-bold">
                        <?= $renderCurrencies($kpi['collected'] ?? []) ?>
                    </div>
                    <small class="text-muted">
                        w okresie · <?= $collectedTrend($kpi['collected'] ?? [], $kpi['collected_prev'] ?? []) ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Powiadomienia ──────────────────────────────────────────────────── -->
    <?php if (!empty($notifications)): ?>
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-warning-subtle py-2 d-flex align-items-center gap-2">
                <i class="ri-notification-3-line text-warning"></i>
                <strong>Powiadomienia (<?= count($notifications) ?>)</strong>
            </div>
            <div class="card-body p-0">
                <?php foreach ($notifications as $n): ?>
                    <?php $color = $n['type'] === 'warning' ? 'warning' : ($n['type'] === 'danger' ? 'danger' : 'info'); ?>
                    <div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom small">
                        <i class="<?= h($n['icon']) ?> text-<?= $color ?> fs-6 flex-shrink-0 mt-1"></i>
                        <div>
                            <strong><?= h($n['title']) ?></strong>
                            <span class="text-muted">— <?= h($n['text']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- ── Aging buckets ───────────────────────────────────────────────── -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-hourglass-line text-danger"></i>
                    <strong>Wiekowanie zaległości</strong>
                    <small class="text-muted ms-auto">tylko przeterminowane</small>
                </div>
                <div class="card-body">
                    <?php
                        $totalOverdue = array_sum(array_map(fn($b) => $b['amount_pln'], $aging));
                    ?>
                    <?php if ($totalOverdue <= 0): ?>
                        <div class="text-muted small fst-italic text-center py-4">
                            <i class="ri-checkbox-circle-line fs-4 d-block mb-2 text-success"></i>
                            Brak zaległości 🎉
                        </div>
                    <?php else: ?>
                        <div style="height:180px"><canvas id="agingChart"></canvas></div>
                        <table class="table table-sm small mt-3 mb-0">
                            <?php
                                $colors = ['0_30' => 'warning', '31_60' => 'orange', '61_90' => 'danger', '90p' => 'dark'];
                                foreach ($aging as $key => $b):
                                    $col = $colors[$key] ?? 'secondary';
                            ?>
                                <tr>
                                    <td style="width:120px">
                                        <span class="badge bg-<?= $col ?>-subtle text-<?= $col ?> border" style="font-size:.7rem">
                                            <?= h($b['label']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        <?php
                                            $perCurr = [];
                                            if ($b['amount_pln'] > 0) $perCurr['PLN'] = $b['amount_pln'];
                                            if ($b['amount_eur'] > 0) $perCurr['EUR'] = $b['amount_eur'];
                                            echo $renderCurrencies($perCurr);
                                        ?>
                                    </td>
                                    <td class="text-end text-muted" style="width:60px"><?= (int)$b['count'] ?>×</td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Cashflow forecast ───────────────────────────────────────────── -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-line-chart-line text-success"></i>
                    <strong>Prognoza wpływów</strong>
                    <small class="text-muted ms-auto">oczekiwane wg paymentdate</small>
                </div>
                <div class="card-body">
                    <div style="height:180px"><canvas id="cashflowChart"></canvas></div>
                    <table class="table table-sm small mt-3 mb-0">
                        <?php
                            $cfLabels = [
                                'overdue' => ['label' => '⏰ Już przeterminowane',  'col' => 'danger'],
                                'next_30' => ['label' => 'Do 30 dni',               'col' => 'warning'],
                                'next_60' => ['label' => 'Do 60 dni',               'col' => 'info'],
                                'next_90' => ['label' => 'Do 90 dni',               'col' => 'primary'],
                                'later'   => ['label' => 'Później (> 90 dni)',     'col' => 'secondary'],
                            ];
                            foreach ($cfLabels as $k => $cfg):
                                $b = $cashflow[$k] ?? null;
                                if (!$b || ($b['amount_pln'] + $b['amount_eur'] == 0)) continue;
                        ?>
                            <tr>
                                <td style="width:170px">
                                    <span class="badge bg-<?= $cfg['col'] ?>-subtle text-<?= $cfg['col'] ?> border" style="font-size:.7rem">
                                        <?= h($cfg['label']) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-semibold">
                                    <?php
                                        $perCurr = [];
                                        if ($b['amount_pln'] > 0) $perCurr['PLN'] = $b['amount_pln'];
                                        if ($b['amount_eur'] > 0) $perCurr['EUR'] = $b['amount_eur'];
                                        echo $renderCurrencies($perCurr);
                                    ?>
                                </td>
                                <td class="text-end text-muted" style="width:50px"><?= (int)$b['count'] ?>×</td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── DSO trend chart ─────────────────────────────────────────────── -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-calendar-check-line text-info"></i>
                    <strong>Trend DSO (12 miesięcy)</strong>
                    <small class="text-muted ms-auto">Days Sales Outstanding — mniej = lepiej</small>
                </div>
                <div class="card-body">
                    <?php if (empty(array_filter(array_column($dsoTrend, 'dso')))): ?>
                        <div class="text-muted small fst-italic text-center py-3">Brak danych — za mało faktur w ostatnich 12 miesiącach.</div>
                    <?php else: ?>
                        <div style="height:200px"><canvas id="dsoChart"></canvas></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Heatmapa ────────────────────────────────────────────────────── -->
        <?php if (!empty($heatmap['contractors'])): ?>
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-layout-grid-line text-primary"></i>
                    <strong>Heatmapa płatności kontrahentów</strong>
                    <small class="text-muted ms-auto">% faktur opłaconych w danym miesiącu · zielone = lepsze, czerwone = gorsze · klik = profil</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 small" style="font-size:.7rem">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:200px;position:sticky;left:0;background:#f3f4f6">Kontrahent</th>
                                    <?php foreach ($heatmap['months'] as $m): ?>
                                        <th class="text-center" style="min-width:48px"><?= h(substr($m, 5)) ?>/<?= h(substr($m, 2, 2)) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($heatmap['contractors'] as $c): ?>
                                    <tr>
                                        <td class="text-truncate" style="max-width:240px;position:sticky;left:0;background:#fff" title="<?= h($c['name']) ?>">
                                            <a href="#" class="text-dark text-decoration-none btn-contractor-profile" data-nip="<?= h($c['nip']) ?>">
                                                <?= h($c['name']) ?: '<em class="text-muted">—</em>' ?>
                                            </a>
                                        </td>
                                        <?php foreach ($heatmap['months'] as $m):
                                            $cell = $c['cells'][$m] ?? null;
                                            $r = $cell['ratio'] ?? null;
                                            if ($r === null) {
                                                $bg   = '#f3f4f6';
                                                $text = '—';
                                                $tip  = 'Brak faktur';
                                            } else {
                                                // 0..1 → kolor red → green
                                                $pct = (int)round($r * 100);
                                                $h_   = (int)round($r * 120);  // 0=red, 120=green
                                                $bg   = 'hsl(' . $h_ . ', 60%, 80%)';
                                                $text = $pct . '%';
                                                $tip  = ($cell['on_time'] ?? 0) . ' / ' . ($cell['total'] ?? 0) . ' zapłacone';
                                            }
                                        ?>
                                            <td class="text-center" style="background:<?= $bg ?>;font-weight:600" title="<?= h($tip) ?>">
                                                <?= h($text) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Top dłużnicy ────────────────────────────────────────────────── -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex align-items-center gap-2 flex-wrap">
                    <i class="ri-user-warning-line text-danger"></i>
                    <strong>Top dłużnicy</strong>
                    <span class="badge bg-secondary-subtle text-secondary"><?= (int)$debtorsTotal ?></span>
                    <small class="text-muted ms-auto">sortowane wg PLN-ekwiwalent · klik = profil</small>
                </div>

                <?php if (!empty($debtorTotals)): ?>
                    <div class="px-3 py-2 bg-light border-bottom d-flex flex-wrap gap-3 align-items-center">
                        <span class="small text-muted">Łącznie niezapłacone:</span>
                        <?php
                            $orderedKeys = array_unique(array_merge(['PLN', 'EUR'], array_keys($debtorTotals)));
                            foreach ($orderedKeys as $curr) {
                                if (!isset($debtorTotals[$curr])) continue;
                                $isPln = $curr === 'PLN';
                                $cls   = $isPln ? 'text-primary' : 'text-success';
                        ?>
                            <div class="d-flex flex-column">
                                <span class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em"><?= h($curr) ?></span>
                                <span class="fw-bold <?= $cls ?>"><?= $fnum($debtorTotals[$curr]) ?>&nbsp;<?= h($curr) ?></span>
                            </div>
                        <?php } ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($topDebtors)): ?>
                    <div class="card-body text-muted small fst-italic">Brak dłużników — wszystko zapłacone 🎉</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 small" id="debtorsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:30px">#</th>
                                    <th>Kontrahent</th>
                                    <th class="text-end" style="width:60px">Faktury</th>
                                    <th class="text-end" style="width:80px">Przeterm.</th>
                                    <th class="text-end">Niezapłacone</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topDebtors as $i => $d): ?>
                                    <tr>
                                        <td class="text-muted"><?= $i + 1 ?></td>
                                        <td class="text-truncate" style="max-width:280px" title="<?= h($d['name']) ?>">
                                            <a href="#" class="text-dark text-decoration-none btn-contractor-profile" data-nip="<?= h($d['nip']) ?>">
                                                <?= h($d['name']) ?: '—' ?>
                                            </a>
                                            <?php if (($d['nip'] ?? '') !== '_unknown_'): ?>
                                                <div class="text-muted" style="font-size:.7em">NIP <?= h($d['nip']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= (int)$d['unpaid_count'] ?></td>
                                        <td class="text-end">
                                            <?php if (($d['overdue_count'] ?? 0) > 0): ?>
                                                <span class="badge bg-danger-subtle text-danger border" style="font-size:.65rem">
                                                    <?= (int)$d['overdue_count'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?= $renderCurrencies($d['by_currency']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($debtorsTotal > count($topDebtors)): ?>
                        <div class="card-footer py-2 text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnLoadMoreDebtors"
                                    data-offset="<?= count($topDebtors) ?>"
                                    data-total="<?= (int)$debtorsTotal ?>">
                                <i class="ri-arrow-down-line me-1"></i>
                                Załaduj więcej (<?= $debtorsTotal - count($topDebtors) ?> pozostało)
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Kapitał w czasie ────────────────────────────────────────────── -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-bar-chart-2-line text-primary"></i>
                    <strong>Kapitał w czasie (12 miesięcy)</strong>
                    <small class="text-muted ms-auto">wystawiono · zapłacono · pozostało (PLN raw sum)</small>
                </div>
                <div class="card-body">
                    <?php if (empty($capital)): ?>
                        <div class="text-muted small fst-italic">Brak danych.</div>
                    <?php else: ?>
                        <div style="height:260px"><canvas id="capitalChart"></canvas></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Najwolniej płacący + Ostatnie niewykorzystane ──────────────── -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-time-line text-warning"></i>
                    <strong>Najwolniej płacący</strong>
                    <small class="text-muted ms-auto">min. 3 wpłaty</small>
                </div>
                <?php if (empty($paymentDays)): ?>
                    <div class="card-body text-muted small fst-italic">Za mało potwierdzonych wpłat.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Kontrahent</th>
                                    <th class="text-end">Próbka</th>
                                    <th class="text-end">Śr. dni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentDays as $p): ?>
                                    <tr>
                                        <td class="text-truncate" style="max-width:200px" title="<?= h($p['name']) ?>">
                                            <a href="#" class="text-dark text-decoration-none btn-contractor-profile" data-nip="<?= h($p['nip']) ?>">
                                                <?= h($p['name']) ?: '—' ?>
                                            </a>
                                        </td>
                                        <td class="text-end"><?= (int)$p['sample_size'] ?></td>
                                        <td class="text-end">
                                            <?php
                                                $days = (int)round((float)$p['avg_days']);
                                                $cls  = $days > 30 ? 'text-danger fw-bold' : ($days > 14 ? 'text-warning' : 'text-success');
                                            ?>
                                            <span class="<?= $cls ?>"><?= $days ?> dni</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-exchange-line text-info"></i>
                    <strong>Niewykorzystane przelewy</strong>
                    <small class="text-muted ms-auto">±30 dni</small>
                </div>
                <?php if (empty($recentUnmatched)): ?>
                    <div class="card-body text-muted small fst-italic">Wszystko powiązane 👌</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Nadawca</th>
                                    <th class="text-end">Kwota</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUnmatched as $t): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= h($fdate($t->value_date)) ?></td>
                                        <td class="text-truncate" style="max-width:160px" title="<?= h($t->party_name ?? '') ?>">
                                            <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'BankTransactions', 'action' => 'transactions', '?' => ['q' => $t->parsed_inv ?: ($t->party_name ?? '')]]) ?>"
                                               class="text-dark text-decoration-none">
                                                <?= h($t->party_name ?? '—') ?>
                                            </a>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            <?= $fnum($t->amount) ?> <?= h($t->currency ?? 'PLN') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal profil kontrahenta ──────────────────────────────────────────── -->
<div class="modal fade" id="contractorProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="contractorProfileTitle">Profil kontrahenta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contractorProfileBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    function fmtPL(v) {
        return parseFloat(v || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ').replace('.', ',');
    }
    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Aging chart ───────────────────────────────────────────────────────
    var agingEl = document.getElementById('agingChart');
    if (agingEl) {
        var aging = <?= json_encode($aging, JSON_UNESCAPED_UNICODE) ?>;
        new Chart(agingEl, {
            type: 'doughnut',
            data: {
                labels: Object.values(aging).map(b => b.label),
                datasets: [{
                    data: Object.values(aging).map(b => parseFloat(b.amount_pln)),
                    backgroundColor: ['#fbbf24', '#f97316', '#ef4444', '#7f1d1d'],
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ctx.label + ': ' + fmtPL(ctx.parsed) + ' PLN' } }
                }
            }
        });
    }

    // ── Cashflow chart ────────────────────────────────────────────────────
    var cfEl = document.getElementById('cashflowChart');
    if (cfEl) {
        var cf = <?= json_encode($cashflow, JSON_UNESCAPED_UNICODE) ?>;
        var keys = ['overdue', 'next_30', 'next_60', 'next_90', 'later'];
        var labels = ['Przeterm.', '≤30d', '≤60d', '≤90d', '>90d'];
        var colors = ['#dc2626', '#f59e0b', '#3b82f6', '#10b981', '#94a3b8'];
        new Chart(cfEl, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: keys.map(k => (cf[k]?.amount_pln || 0)),
                    backgroundColor: colors,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => fmtPL(ctx.parsed.y) + ' PLN' } } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('pl-PL') } } }
            }
        });
    }

    // ── DSO trend chart ───────────────────────────────────────────────────
    var dsoEl = document.getElementById('dsoChart');
    if (dsoEl) {
        var dso = <?= json_encode($dsoTrend, JSON_UNESCAPED_UNICODE) ?>;
        new Chart(dsoEl, {
            type: 'line',
            data: {
                labels: dso.map(p => p.month),
                datasets: [{
                    label: 'DSO (dni)',
                    data: dso.map(p => p.dso),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, .15)',
                    fill: true,
                    tension: 0.3,
                    spanGaps: true,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 10 } } }
            }
        });
    }

    // ── Kapitał chart ─────────────────────────────────────────────────────
    var capEl = document.getElementById('capitalChart');
    if (capEl) {
        var cap = <?= json_encode($capital, JSON_UNESCAPED_UNICODE) ?>;
        new Chart(capEl, {
            type: 'bar',
            data: {
                labels: cap.map(r => r.month),
                datasets: [
                    { label: 'Wystawiono', data: cap.map(r => parseFloat(r.billed_total)),    backgroundColor: 'rgba(59, 130, 246, .7)' },
                    { label: 'Zapłacono',  data: cap.map(r => parseFloat(r.paid_total)),     backgroundColor: 'rgba(22, 163, 74, .7)' },
                    { label: 'Pozostało',  data: cap.map(r => parseFloat(r.remaining_total)),backgroundColor: 'rgba(220, 38, 38, .7)' },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('pl-PL') } } },
                plugins: { legend: { position: 'top', align: 'end' } }
            }
        });
    }

    // ── Załaduj więcej dłużników ──────────────────────────────────────────
    var btnLoadMore = document.getElementById('btnLoadMoreDebtors');
    if (btnLoadMore) {
        var tbody = document.querySelector('#debtorsTable tbody');
        function renderDebtorRow(d, i) {
            var orderedKeys = Array.from(new Set(['PLN', 'EUR'].concat(Object.keys(d.by_currency || {}))));
            var parts = [];
            orderedKeys.forEach(function (c) {
                if (!d.by_currency || d.by_currency[c] === undefined) return;
                var cls = c === 'PLN' ? 'text-primary' : 'text-success';
                parts.push('<span class="fw-semibold ' + cls + '">' + fmtPL(d.by_currency[c]) + ' ' + esc(c) + '</span>');
            });
            var amountsHtml = parts.join(' <span class="text-muted">+</span> ');
            var nipHtml = (d.nip && d.nip !== '_unknown_')
                ? '<div class="text-muted" style="font-size:.7em">NIP ' + esc(d.nip) + '</div>'
                : '';
            var overdueHtml = (d.overdue_count > 0)
                ? '<span class="badge bg-danger-subtle text-danger border" style="font-size:.65rem">' + d.overdue_count + '</span>'
                : '<span class="text-muted">—</span>';
            return '<tr>'
                 + '<td class="text-muted">' + (i + 1) + '</td>'
                 + '<td class="text-truncate" style="max-width:280px" title="' + esc(d.name) + '">'
                 +   '<a href="#" class="text-dark text-decoration-none btn-contractor-profile" data-nip="' + esc(d.nip) + '">' + (esc(d.name) || '—') + '</a>'
                 +   nipHtml
                 + '</td>'
                 + '<td class="text-end">' + d.unpaid_count + '</td>'
                 + '<td class="text-end">' + overdueHtml + '</td>'
                 + '<td class="text-end">' + amountsHtml + '</td>'
                 + '</tr>';
        }
        btnLoadMore.addEventListener('click', function () {
            if (this.disabled) return;
            var offset = parseInt(this.dataset.offset, 10) || 0;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Ładowanie…';
            fetch('/rozliczenia/ksef/insights/top-debtors?offset=' + offset + '&limit=10', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(d => {
                    (d.debtors || []).forEach((deb, idx) => tbody.insertAdjacentHTML('beforeend', renderDebtorRow(deb, offset + idx)));
                    var newOffset = offset + (d.debtors || []).length;
                    btnLoadMore.dataset.offset = newOffset;
                    if (!d.has_more) btnLoadMore.remove();
                    else {
                        btnLoadMore.disabled = false;
                        btnLoadMore.innerHTML = '<i class="ri-arrow-down-line me-1"></i>Załaduj więcej (' + ((d.total || 0) - newOffset) + ' pozostało)';
                    }
                });
        });
    }

    // ── Drill-down: profil kontrahenta ───────────────────────────────────
    var profileModal = new bootstrap.Modal(document.getElementById('contractorProfileModal'));
    document.addEventListener('click', function (e) {
        var link = e.target.closest('.btn-contractor-profile');
        if (!link) return;
        e.preventDefault();
        var nip = link.dataset.nip;
        if (!nip || nip === '_unknown_') return;

        document.getElementById('contractorProfileTitle').textContent = 'Profil kontrahenta — ' + (link.textContent || '').trim();
        document.getElementById('contractorProfileBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        profileModal.show();

        fetch('/rozliczenia/ksef/insights/contractor/' + encodeURIComponent(nip), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => renderProfile(d));
    });

    function renderProfile(d) {
        var body = document.getElementById('contractorProfileBody');
        if (!d || !d.invoices) {
            body.innerHTML = '<div class="text-muted">Brak danych</div>';
            return;
        }
        var stats = d.stats || {};
        var sumsHtml = Object.keys(stats.sums || {}).map(c =>
            '<div class="d-flex flex-column"><span class="text-muted" style="font-size:.65rem;text-transform:uppercase">' + esc(c) + '</span>'
            + '<span class="fw-bold ' + (c === 'PLN' ? 'text-primary' : 'text-success') + '">' + fmtPL(stats.sums[c]) + ' ' + esc(c) + '</span></div>'
        ).join('');

        var stateLabels = { 'paid': 'Zapłacona', 'partial': 'Częściowo', 'unpaid': 'Nieopłacona' };
        var stateColors = { 'paid': 'success', 'partial': 'warning', 'unpaid': 'danger' };

        var rowsHtml = (d.invoices || []).map(function (inv) {
            var col = stateColors[inv.paymentstate] || 'secondary';
            return '<tr>'
                 + '<td class="small">'
                 +   '<a href="/invoices/view/' + esc(inv.id) + '" class="text-dark text-decoration-none fw-semibold">' + esc(inv.fullnumber) + '</a>'
                 + '</td>'
                 + '<td class="text-nowrap small text-muted">' + esc(inv.date) + '</td>'
                 + '<td class="text-nowrap small">' + esc(inv.paymentdate || '—') + '</td>'
                 + '<td class="text-end small">' + fmtPL(inv.total) + ' ' + esc(inv.currency) + '</td>'
                 + '<td class="text-end small">' + fmtPL(inv.remaining) + ' ' + esc(inv.currency) + '</td>'
                 + '<td class="small"><span class="badge bg-' + col + '-subtle text-' + col + ' border" style="font-size:.65rem">' + (stateLabels[inv.paymentstate] || inv.paymentstate) + '</span></td>'
                 + '</tr>';
        }).join('');

        body.innerHTML = ''
            + '<div class="row g-2 mb-3">'
            +   '<div class="col-md-3"><div class="card bg-light h-100"><div class="card-body p-2"><small class="text-muted">NIP</small><div class="fw-bold">' + esc(d.nip) + '</div></div></div></div>'
            +   '<div class="col-md-3"><div class="card bg-light h-100"><div class="card-body p-2"><small class="text-muted">Faktury</small><div class="fw-bold">' + stats.total_count + ' (' + stats.unpaid_count + ' niezapł.)</div></div></div></div>'
            +   '<div class="col-md-3"><div class="card bg-light h-100"><div class="card-body p-2"><small class="text-muted">Przeterminowane</small><div class="fw-bold text-danger">' + stats.overdue_count + '</div></div></div></div>'
            +   '<div class="col-md-3"><div class="card bg-light h-100"><div class="card-body p-2"><small class="text-muted">Śr. zwłoka</small><div class="fw-bold">' + (stats.avg_days_late !== null ? stats.avg_days_late + ' dni' : '—') + '</div></div></div></div>'
            + '</div>'
            + (sumsHtml ? ('<div class="mb-3 d-flex flex-wrap gap-3 px-2 py-2 bg-light rounded">' + '<span class="small text-muted">Niezapłacone:</span>' + sumsHtml + '</div>') : '')
            + '<div class="table-responsive"><table class="table table-sm table-hover small mb-0"><thead class="table-light"><tr>'
            +   '<th>Faktura</th><th>Wystawiona</th><th>Termin</th><th class="text-end">Brutto</th><th class="text-end">Pozostało</th><th>Status</th>'
            + '</tr></thead><tbody>' + rowsHtml + '</tbody></table></div>';
    }
})();
</script>

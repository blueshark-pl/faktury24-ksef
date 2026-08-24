<?php
/**
 * @var \App\View\AppView $this
 * @var array $stats
 * @var int $totalActive
 * @var float $totalValue
 * @var int $wonCount
 * @var int $lostCount
 * @var float $conversion
 * @var array $ranking
 * @var array $activityByDay
 * @var array $sourceRows
 * @var int $days
 */
$this->assign('title', __('CRM – Dashboard'));

$stageLabels = ['new'=>__('Nowy'),'contact'=>__('Kontakt'),'inquiry'=>__('Zapytanie'),
    'offer'=>__('Oferta'),'order'=>__('Zlecenie'),'lost'=>__('Utracone')];
$stageColors = ['new'=>'#0d6efd','contact'=>'#0dcaf0','inquiry'=>'#f59e0b',
    'offer'=>'#7c3aed','order'=>'#198754','lost'=>'#adb5bd'];

// Prep activity heatmap data (JSON dla Chart.js)
$labels = [];
$values = [];
$end = new \DateTimeImmutable('today');
$start = $end->modify("-{$days} days");
$cur = $start;
while ($cur <= $end) {
    $key = $cur->format('Y-m-d');
    $labels[] = $cur->format('d.m');
    $values[] = (int)($activityByDay[$key] ?? 0);
    $cur = $cur->modify('+1 day');
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="ri-dashboard-3-line me-1 text-info"></i> <?= __('CRM Dashboard') ?></h4>
        <div class="text-muted small"><?= sprintf(__('Aktywność z ostatnich %d dni'), $days) ?></div>
    </div>
    <div class="btn-group btn-group-sm">
        <?php foreach ([30, 90, 180, 365] as $d): ?>
            <a href="?days=<?= $d ?>" class="btn <?= $days === $d ? 'btn-primary' : 'btn-outline-primary' ?>"><?= $d ?>d</a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Global KPI -->
<div class="row g-2 mb-3">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body">
                <div class="small text-muted"><?= __('Aktywne leady') ?></div>
                <div class="fs-2 fw-bold text-primary"><?= $totalActive ?></div>
                <div class="small text-muted"><?= __('bez utraconych') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body">
                <div class="small text-muted"><?= __('Wartość pipeline') ?></div>
                <div class="fs-2 fw-bold text-success"><?= number_format($totalValue, 0, ',', ' ') ?> zł</div>
                <div class="small text-muted"><?= __('suma value_pln') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body">
                <div class="small text-muted"><?= __('Konwersja') ?></div>
                <div class="fs-2 fw-bold text-info"><?= number_format($conversion, 1) ?>%</div>
                <div class="small text-muted"><?= sprintf(__('%d wygranych / %d utraconych'), $wonCount, $lostCount) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body">
                <div class="small text-muted"><?= __('Wygrane zlecenia') ?></div>
                <div class="fs-2 fw-bold text-warning"><?= $wonCount ?></div>
                <div class="small text-muted"><?= __('stage=order') ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Pipeline funnel -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">
                <i class="ri-funnels-line"></i> <?= __('Pipeline funnel') ?>
            </div>
            <div class="card-body">
                <?php $maxCount = max(array_map(fn($s) => $s['count'], $stats)) ?: 1; ?>
                <?php foreach (['new','contact','inquiry','offer','order','lost'] as $s):
                    $cnt = (int)$stats[$s]['count'];
                    $val = (float)$stats[$s]['value_pln'];
                    $pct = $cnt / $maxCount * 100;
                    $col = $stageColors[$s];
                ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><span class="badge" style="background:<?= $col ?>; color:#fff;"><?= h($stageLabels[$s]) ?></span></span>
                        <span><strong><?= $cnt ?></strong> · <?= number_format($val, 0, ',', ' ') ?> zł</span>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" role="progressbar" style="width:<?= $pct ?>%; background:<?= $col ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Activity heatmap -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">
                <i class="ri-line-chart-line"></i> <?= __('Aktywność (nowe activity per dzień)') ?>
            </div>
            <div class="card-body">
                <canvas id="activity-chart" height="120"></canvas>
            </div>
        </div>
    </div>

    <!-- Ranking handlowców -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white fw-semibold">
                <i class="ri-trophy-line"></i> <?= __('Ranking handlowców (per assigned_to_user_id)') ?>
            </div>
            <div class="table-responsive">
                <?php if (empty($ranking)): ?>
                    <div class="text-center text-muted py-4"><?= __('Brak przypisanych leadów.') ?></div>
                <?php else: ?>
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th><?= __('Handlowiec') ?></th>
                                <th class="text-end"><?= __('Leady') ?></th>
                                <th class="text-end"><?= __('Pipeline') ?></th>
                                <th class="text-end"><?= __('Wygrane') ?></th>
                                <th class="text-end"><?= __('Utracone') ?></th>
                                <th class="text-end"><?= __('Konwersja') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ranking as $i => $r): ?>
                            <tr>
                                <td class="fw-bold"><?= $i + 1 ?></td>
                                <td>
                                    <span class="rounded-circle d-inline-flex justify-content-center align-items-center"
                                          style="width:26px; height:26px; background:#94C81F; color:#fff; font-size:11px; font-weight:700; margin-right:6px;">
                                        <?= h(strtoupper(mb_substr($r['name'], 0, 2))) ?>
                                    </span>
                                    <?= h($r['name']) ?>
                                </td>
                                <td class="text-end fw-semibold"><?= (int)$r['total'] ?></td>
                                <td class="text-end fw-semibold"><?= number_format($r['value'], 0, ',', ' ') ?> zł</td>
                                <td class="text-end text-success fw-semibold"><?= $r['won'] ?></td>
                                <td class="text-end text-muted"><?= $r['lost'] ?></td>
                                <td class="text-end">
                                    <span class="badge <?= $r['conversion'] >= 50 ? 'bg-success' : ($r['conversion'] >= 25 ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                        <?= $r['conversion'] ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sources -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">
                <i class="ri-pie-chart-line"></i> <?= sprintf(__('Źródła nowych leadów (ost. %d dni)'), $days) ?>
            </div>
            <div class="card-body">
                <?php $totalSrc = array_sum(array_map(fn($s) => (int)$s['cnt'], $sourceRows)) ?: 1; ?>
                <?php if (empty($sourceRows)): ?>
                    <div class="text-center text-muted py-3"><?= __('Brak nowych leadów w tym okresie.') ?></div>
                <?php else: ?>
                    <?php foreach ($sourceRows as $r):
                        $src = $r['source'] ?: '—';
                        $cnt = (int)$r['cnt'];
                        $pct = round($cnt / $totalSrc * 100, 1);
                    ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span><?= h($src) ?></span>
                            <span><strong><?= $cnt ?></strong> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-info" style="width:<?= $pct ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('activity-chart');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: '<?= __('Nowe activity per dzień') ?>',
                data: <?= json_encode($values) ?>,
                borderColor: '#94C81F',
                backgroundColor: 'rgba(148,200,31,0.15)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointRadius: 2,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
})();
</script>

<?php
/**
 * FALA 22: Executive Dashboard dla managera.
 * @var \App\View\AppView $this
 * @var array $forecast KPI weighted + total + per_pipeline
 * @var array $forecastPerRep [userId => [total, weighted, count]]
 * @var array $userMap [userId => 'First Last']
 * @var array $velocityStats [pipeline => [median, count]]
 * @var array $velocityRepStats [userId => [name, median_days, won_count]]
 * @var array $cohorts [YYYY-MM => [stages, total, won, lost]]
 * @var array $revenueAttribution [source => [value, count]]
 * @var array $monthlyForecast [YYYY-MM => [weighted, count]]
 */
$this->assign('title', __('CRM – Manager Dashboard'));
$fmt = fn($n) => number_format((float)$n, 0, ',', ' ');
$pipelineLabels = \App\Model\Table\LeadsTable::PIPELINE_LABELS;
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="ri-bar-chart-box-line text-primary"></i> <?= __('Executive Dashboard') ?></h4>
        <div class="small text-muted"><?= __('Prognoza przychodu, sales velocity, cohort analysis - dla managera') ?></div>
    </div>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line"></i> <?= __('Wróć do CRM') ?>
    </a>
</div>

<!-- KPI weighted forecast -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 h-100" style="background: linear-gradient(135deg, #94C81F 0%, #6b8f14 100%); color: #fff;">
            <div class="card-body">
                <div class="small opacity-75"><?= __('Weighted Forecast') ?></div>
                <div class="fw-bold" style="font-size: 26px;"><?= h($fmt($forecast['weighted'])) ?> <small style="opacity: 0.75;">PLN</small></div>
                <div class="small opacity-75 mt-1">
                    <i class="ri-price-tag-3-line"></i> Wartość × prawdopodobieństwo
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 h-100" style="background: #f8fafc;">
            <div class="card-body">
                <div class="small text-muted"><?= __('Suma pipeline (100%)') ?></div>
                <div class="fw-bold text-dark" style="font-size: 26px;"><?= h($fmt($forecast['total'])) ?> <small class="text-muted">PLN</small></div>
                <div class="small text-muted mt-1">
                    <?= (int)$forecast['count'] ?> <?= __('aktywnych leadów') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 h-100" style="background: #f0f9ff;">
            <div class="card-body">
                <div class="small text-muted"><?= __('Ratio realny/max') ?></div>
                <div class="fw-bold text-primary" style="font-size: 26px;">
                    <?= $forecast['total'] > 0 ? round(100 * $forecast['weighted'] / $forecast['total']) : 0 ?>%
                </div>
                <div class="small text-muted mt-1">
                    <?= __('Średnia probability wszystkich') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 h-100" style="background: #fff7ed;">
            <div class="card-body">
                <div class="small text-muted"><?= __('Median velocity') ?></div>
                <?php
                $allDays = [];
                foreach ($velocityStats as $st) if ($st['median']) $allDays[] = $st['median'];
                $overallMedian = !empty($allDays) ? array_sum($allDays) / count($allDays) : null;
                ?>
                <div class="fw-bold text-warning" style="font-size: 26px;">
                    <?= $overallMedian !== null ? round($overallMedian) . ' <small>dni</small>' : '<small>brak danych</small>' ?>
                </div>
                <div class="small text-muted mt-1">
                    <?= __('Od utworzenia do wygranej (6 mies)') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Forecast per pipeline -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="ri-funnels-line"></i> <?= __('Prognoza per pipeline') ?></h6>
                <table class="table table-sm">
                    <thead><tr><th>Pipeline</th><th class="text-end">Leadów</th><th class="text-end">Total</th><th class="text-end">Weighted</th></tr></thead>
                    <tbody>
                        <?php foreach (\App\Model\Table\LeadsTable::PIPELINE_TYPES as $pt): $d = $forecast['per_pipeline'][$pt] ?? ['total' => 0, 'weighted' => 0, 'count' => 0]; ?>
                        <tr>
                            <td><strong><?= h($pipelineLabels[$pt] ?? $pt) ?></strong></td>
                            <td class="text-end"><?= (int)$d['count'] ?></td>
                            <td class="text-end text-muted"><?= h($fmt($d['total'])) ?></td>
                            <td class="text-end text-success fw-bold"><?= h($fmt($d['weighted'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Rep leaderboard -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="ri-trophy-line text-warning"></i> <?= __('Ranking handlowców — weighted') ?></h6>
                <?php if (empty($forecastPerRep)): ?>
                    <div class="text-muted small"><?= __('Brak przypisanych leadów.') ?></div>
                <?php else: uasort($forecastPerRep, fn($a, $b) => $b['weighted'] <=> $a['weighted']); ?>
                    <table class="table table-sm">
                        <thead><tr><th>Handlowiec</th><th class="text-end">Leadów</th><th class="text-end">Weighted PLN</th></tr></thead>
                        <tbody>
                            <?php $i = 0; foreach (array_slice($forecastPerRep, 0, 10, true) as $uid => $d): $i++; ?>
                            <tr>
                                <td>
                                    <?php if ($i <= 3): ?><span style="font-size: 14px;"><?= ['🥇','🥈','🥉'][$i - 1] ?></span> <?php endif; ?>
                                    <?= h($userMap[$uid] ?? 'Unknown') ?>
                                </td>
                                <td class="text-end"><?= (int)$d['count'] ?></td>
                                <td class="text-end fw-bold text-success"><?= h($fmt($d['weighted'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Sales velocity per rep -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="ri-speed-up-line text-primary"></i> <?= __('Sales velocity per handlowiec') ?></h6>
                <?php if (empty($velocityRepStats)): ?>
                    <div class="text-muted small"><?= __('Brak wygranych leadów w ostatnich 6 miesiącach.') ?></div>
                <?php else: ?>
                    <table class="table table-sm">
                        <thead><tr><th>Handlowiec</th><th class="text-end">Wygrane</th><th class="text-end">Mediana dni</th></tr></thead>
                        <tbody>
                            <?php foreach ($velocityRepStats as $uid => $s): ?>
                            <tr>
                                <td><?= h($s['name']) ?></td>
                                <td class="text-end"><?= (int)$s['won_count'] ?></td>
                                <td class="text-end fw-bold" style="color: <?= $s['median_days'] < 14 ? '#059669' : ($s['median_days'] < 30 ? '#b45309' : '#dc2626') ?>;">
                                    <?= (int)$s['median_days'] ?> dni
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="small text-muted mt-2">
                        <i class="ri-information-line"></i> <?= __('Niższa liczba = szybszy cykl sprzedażowy') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Revenue attribution -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="ri-pie-chart-line text-info"></i> <?= __('Revenue Attribution — źródła') ?></h6>
                <?php if (empty($revenueAttribution)): ?>
                    <div class="text-muted small"><?= __('Brak wygranych leadów.') ?></div>
                <?php else: ?>
                    <?php $maxVal = max(array_column($revenueAttribution, 'value')) ?: 1; ?>
                    <?php foreach ($revenueAttribution as $src => $d): $pct = 100 * $d['value'] / $maxVal; ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <strong><?= h(ucfirst($src)) ?></strong>
                                <span class="text-muted"><?= (int)$d['count'] ?> leadów · <?= h($fmt($d['value'])) ?> PLN</span>
                            </div>
                            <div style="height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?= $pct ?>%; height: 100%; background: linear-gradient(90deg, #94C81F, #6b8f14);"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Cohort table -->
<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="ri-line-chart-line text-success"></i> <?= __('Cohort analysis — leady z miesiąca → aktualny status') ?></h6>
        <?php if (empty($cohorts)): ?>
            <div class="text-muted small"><?= __('Brak leadów w ostatnich 6 miesiącach.') ?></div>
        <?php else: ?>
            <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th><?= __('Miesiąc') ?></th>
                        <th class="text-end"><?= __('Utworzono') ?></th>
                        <th class="text-end text-success"><?= __('Wygrane') ?></th>
                        <th class="text-end text-danger"><?= __('Utracone') ?></th>
                        <th class="text-end text-muted"><?= __('W toku') ?></th>
                        <th class="text-end"><?= __('Konwersja %') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cohorts as $month => $c): $inProgress = $c['total'] - $c['won'] - $c['lost']; $conv = $c['total'] > 0 ? round(100 * $c['won'] / $c['total']) : 0; ?>
                    <tr>
                        <td><strong><?= h($month) ?></strong></td>
                        <td class="text-end"><?= (int)$c['total'] ?></td>
                        <td class="text-end text-success fw-bold"><?= (int)$c['won'] ?></td>
                        <td class="text-end text-danger"><?= (int)$c['lost'] ?></td>
                        <td class="text-end text-muted"><?= (int)$inProgress ?></td>
                        <td class="text-end fw-bold" style="color: <?= $conv >= 30 ? '#059669' : ($conv >= 15 ? '#b45309' : '#dc2626') ?>;">
                            <?= $conv ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Monthly forecast bar chart -->
<div class="card">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="ri-bar-chart-line text-info"></i> <?= __('Weighted forecast — trend miesięczny') ?></h6>
        <canvas id="forecastChart" height="80"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    var data = <?= json_encode($monthlyForecast) ?>;
    var labels = Object.keys(data).sort();
    var values = labels.map(function(m) { return data[m].weighted; });
    var ctx = document.getElementById('forecastChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Weighted forecast PLN',
                data: values,
                backgroundColor: 'rgba(148, 200, 31, 0.7)',
                borderColor: '#6b8f14',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return v.toLocaleString('pl-PL') + ' PLN'; } } } }
        }
    });
})();
</script>

<?php
/**
 * @var \App\View\AppView $this
 * @var array $kpi
 * @var array $topRoutes
 * @var array $topClients
 * @var array $monthlyTrend
 * @var array $invoicesTrend
 * @var array $eventStats
 * @var int $days
 */
$this->assign('title', __('Analytics'));

$fmtMoney = static fn ($v) => number_format((float)$v, 2, ',', ' ') . ' PLN';
$fmtNum = static fn ($v) => number_format((float)$v, 0, ',', ' ');
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= __('Analytics') ?></h1>
        <p class="text-muted small mb-0">
            <?= __('Ostatnie :d dni', [':d' => $days]) ?> · <?= __('dane z speed_orders + invoices + operational_events') ?>
        </p>
    </div>
    <?= $this->Form->create(null, ['type' => 'get', 'class' => 'd-flex gap-2 align-items-center']) ?>
        <select name="days" class="form-select form-select-sm" style="width:120px" onchange="this.form.submit()">
            <option value="30"  <?= $days === 30  ? 'selected' : '' ?>>30 dni</option>
            <option value="90"  <?= $days === 90  ? 'selected' : '' ?>>90 dni</option>
            <option value="180" <?= $days === 180 ? 'selected' : '' ?>>180 dni</option>
            <option value="365" <?= $days === 365 ? 'selected' : '' ?>>1 rok</option>
        </select>
    <?= $this->Form->end() ?>
</div>

<!-- KPI kafelki -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted"><?= __('Zleceń') ?></div>
                <div class="fs-2 fw-bold text-primary"><?= h($fmtNum($kpi['orders_total'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted"><?= __('Wystawionych faktur') ?></div>
                <div class="fs-2 fw-bold text-info"><?= h($fmtNum($kpi['invoices_total'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="small text-muted"><?= __('Suma faktur') ?></div>
                <div class="fs-4 fw-bold text-success"><?= h($fmtMoney($kpi['invoices_sum_pln'])) ?></div>
                <div class="small text-muted"><?= __('Śr:') ?> <?= h($fmtMoney($kpi['avg_order_price_pln'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body">
                <div class="small text-muted"><?= __('Nieopłacone') ?></div>
                <div class="fs-4 fw-bold text-warning"><?= h($fmtMoney($kpi['unpaid_pln'])) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Top 10 tras -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="ri-route-line me-1"></i><?= __('Top 10 tras') ?></div>
            <div class="card-body p-0">
                <?php if (empty($topRoutes)): ?>
                    <p class="text-muted small p-3 mb-0"><?= __('Brak danych.') ?></p>
                <?php else:
                    $maxCount = max(array_column($topRoutes, 'count')) ?: 1;
                ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topRoutes as $r):
                            $pct = round($r['count'] / $maxCount * 100);
                        ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small"><?= h($r['route']) ?></span>
                                    <strong><?= (int)$r['count'] ?></strong>
                                </div>
                                <div class="progress" style="height:6px">
                                    <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                                </div>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        </div>
    </div>

    <!-- Top 10 klientów -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="ri-user-star-line me-1"></i><?= __('Top 10 klientów') ?></div>
            <div class="card-body p-0">
                <?php if (empty($topClients)): ?>
                    <p class="text-muted small p-3 mb-0"><?= __('Brak danych.') ?></p>
                <?php else:
                    $maxCount = max(array_column($topClients, 'orders_count')) ?: 1;
                ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topClients as $c):
                            $pct = round($c['orders_count'] / $maxCount * 100);
                        ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small">
                                        <?= h($c['name'] ?: $c['nip']) ?>
                                        <?php if ($c['nip']): ?><span class="text-muted">· NIP <?= h($c['nip']) ?></span><?php endif ?>
                                    </span>
                                    <strong><?= (int)$c['orders_count'] ?></strong>
                                </div>
                                <div class="progress" style="height:6px">
                                    <div class="progress-bar bg-info" style="width:<?= $pct ?>%"></div>
                                </div>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        </div>
    </div>

    <!-- Trend miesięczny faktur -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="ri-bar-chart-line me-1"></i><?= __('Trend miesięczny — suma faktur (PLN)') ?></div>
            <div class="card-body">
                <?php if (empty($invoicesTrend)): ?>
                    <p class="text-muted small mb-0"><?= __('Brak danych.') ?></p>
                <?php else:
                    $maxSum = max(array_column($invoicesTrend, 'sum_pln')) ?: 1;
                ?>
                    <div class="d-flex align-items-end gap-1" style="height:200px">
                        <?php foreach ($invoicesTrend as $t):
                            $h = round($t['sum_pln'] / $maxSum * 180);
                        ?>
                            <div class="text-center flex-fill" title="<?= h($t['month']) ?>: <?= h($fmtMoney($t['sum_pln'])) ?>">
                                <div style="height:180px;display:flex;flex-direction:column;justify-content:flex-end">
                                    <div style="background:linear-gradient(to top,#3b82f6,#93c5fd);height:<?= $h ?>px;border-radius:4px 4px 0 0"></div>
                                </div>
                                <div class="small text-muted mt-1" style="font-size:.7rem"><?= h($t['month']) ?></div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <!-- Aktywność operacyjna -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="ri-pulse-line me-1"></i><?= __('Aktywność operacyjna') ?></div>
            <div class="card-body p-0">
                <?php if (empty($eventStats)): ?>
                    <p class="text-muted small p-3 mb-0"><?= __('Brak danych.') ?></p>
                <?php else: ?>
                    <ul class="list-group list-group-flush" style="max-height:220px;overflow-y:auto">
                        <?php foreach ($eventStats as $e): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span class="small">
                                    <code style="font-size:.7rem"><?= h($e['entity']) ?></code>
                                    <?= h($e['event']) ?>
                                </span>
                                <span class="badge bg-secondary"><?= (int)$e['count'] ?></span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

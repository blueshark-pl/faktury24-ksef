<?php
/**
 * @var \App\View\AppView $this
 * @var string $period
 * @var array<string,array<string,int>> $speedByUser
 * @var array<int,array> $invoiceStats
 * @var array<int,array> $loginStats
 * @var array{speed_pol:int,speed_pod:int,invoices:int,logins:int} $stats
 * @var float $avgPolPod
 * @var array<int,array> $topInvoiceUsers
 * @var array<string,int> $topPolUsers
 */
$this->assign('title', __('Wydajność pracowników'));
$periods = [
    '7d'  => __('7 dni'),
    '30d' => __('30 dni'),
    '90d' => __('90 dni'),
    'all' => __('Wszystkie'),
];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-bar-chart-2-line me-1"></i><?= __('Wydajność pracowników') ?>
    </h4>
    <div class="btn-group btn-group-sm" role="group">
        <?php foreach ($periods as $code => $label): ?>
            <a href="<?= $this->Url->build(['action' => 'index', '?' => ['period' => $code]]) ?>"
               class="btn <?= $period === $code ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Główne KPI -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><i class="ri-truck-line me-1"></i><?= __('POL oznaczeń') ?></div>
        <div class="fs-3 fw-bold text-primary"><?= number_format($stats['speed_pol'], 0, ',', ' ') ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><i class="ri-map-pin-2-line me-1"></i><?= __('POD oznaczeń') ?></div>
        <div class="fs-3 fw-bold text-success"><?= number_format($stats['speed_pod'], 0, ',', ' ') ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><i class="ri-file-text-line me-1"></i><?= __('Wystawionych faktur') ?></div>
        <div class="fs-3 fw-bold text-info"><?= number_format($stats['invoices'], 0, ',', ' ') ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><i class="ri-login-circle-line me-1"></i><?= __('Logowań') ?></div>
        <div class="fs-3 fw-bold text-warning"><?= number_format($stats['logins'], 0, ',', ' ') ?></div>
    </div></div></div>
</div>

<!-- Średni czas + Top users -->
<div class="row g-2 mb-3">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-time-line me-1"></i><?= __('Średni czas POL → POD') ?></strong></div>
            <div class="card-body text-center py-3">
                <?php if ($avgPolPod > 0): ?>
                    <div class="fs-2 fw-bold text-primary">
                        <?php if ($avgPolPod < 24): ?>
                            <?= number_format($avgPolPod, 1, ',', ' ') ?> <small class="text-muted">h</small>
                        <?php else: ?>
                            <?= number_format($avgPolPod / 24, 1, ',', ' ') ?> <small class="text-muted">d</small>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mt-2"><?= __('Średni czas transportu w wybranym okresie') ?></div>
                <?php else: ?>
                    <div class="text-muted py-3">—</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-trophy-line me-1"></i><?= __('Top wystawcy faktur') ?></strong></div>
            <div class="card-body py-2">
                <?php if (empty($topInvoiceUsers)): ?>
                    <div class="text-muted small py-2 text-center"><?= __('Brak danych') ?></div>
                <?php else: ?>
                    <ol class="mb-0 ps-3" style="font-size:.85rem">
                        <?php foreach ($topInvoiceUsers as $u): ?>
                            <li class="d-flex justify-content-between py-1">
                                <span class="text-truncate me-2" style="max-width:220px"><?= h($u['username']) ?></span>
                                <span class="badge bg-info-subtle text-info border"><?= number_format((int)$u['cnt'], 0, ',', ' ') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-truck-line me-1"></i><?= __('Top oznaczający POL') ?></strong></div>
            <div class="card-body py-2">
                <?php if (empty($topPolUsers)): ?>
                    <div class="text-muted small py-2 text-center"><?= __('Brak danych') ?></div>
                <?php else: ?>
                    <ol class="mb-0 ps-3" style="font-size:.85rem">
                        <?php foreach ($topPolUsers as $username => $cnt): ?>
                            <li class="d-flex justify-content-between py-1">
                                <span class="text-truncate me-2" style="max-width:220px"><?= h($username) ?></span>
                                <span class="badge bg-primary-subtle text-primary border"><?= number_format($cnt, 0, ',', ' ') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Pełna tabela Speed orders per user -->
<div class="card shadow-sm mb-3">
    <div class="card-header py-2">
        <strong><i class="ri-team-line me-1"></i><?= __('Statystyki zleceń per pracownik') ?></strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.85rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3"><?= __('Pracownik') ?></th>
                    <th class="text-end" style="width:100px">POL</th>
                    <th class="text-end" style="width:100px">POD</th>
                    <th class="text-end" style="width:100px">FK</th>
                    <th class="text-end" style="width:100px">FS</th>
                    <th class="text-end pe-3" style="width:120px"><?= __('Razem') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Sortuj po sumie
                uksort($speedByUser, function ($a, $b) use ($speedByUser) {
                    return array_sum($speedByUser[$b]) <=> array_sum($speedByUser[$a]);
                });
                ?>
                <?php if (empty($speedByUser)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        <?= __('Brak oznaczeń POL/POD/FK/FS w wybranym okresie.') ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($speedByUser as $username => $counts):
                        $pol = $counts['POL'] ?? 0;
                        $pod = $counts['POD'] ?? 0;
                        $fk  = $counts['FK']  ?? 0;
                        $fs  = $counts['FS']  ?? 0;
                        $tot = $pol + $pod + $fk + $fs;
                    ?>
                        <tr>
                            <td class="ps-3 fw-semibold"><?= h($username) ?></td>
                            <td class="text-end"><?= $pol > 0 ? number_format($pol, 0, ',', ' ') : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end"><?= $pod > 0 ? number_format($pod, 0, ',', ' ') : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end"><?= $fk  > 0 ? number_format($fk,  0, ',', ' ') : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end"><?= $fs  > 0 ? number_format($fs,  0, ',', ' ') : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end pe-3 fw-bold"><?= number_format($tot, 0, ',', ' ') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Logowania per user -->
<div class="card shadow-sm">
    <div class="card-header py-2">
        <strong><i class="ri-login-circle-line me-1"></i><?= __('Logowania per pracownik') ?></strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.85rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3"><?= __('Pracownik') ?></th>
                    <th class="text-end pe-3" style="width:140px"><?= __('Liczba logowań') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loginStats)): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3"><?= __('Brak danych logowań w okresie') ?></td></tr>
                <?php else: ?>
                    <?php foreach (array_slice($loginStats, 0, 20) as $row): ?>
                        <tr>
                            <td class="ps-3"><?= h($row['username']) ?></td>
                            <td class="text-end pe-3 fw-semibold"><?= number_format((int)$row['cnt'], 0, ',', ' ') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

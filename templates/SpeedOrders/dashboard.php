<?php
/**
 * @var \App\View\AppView $this
 * @var array $stats
 * @var array $nlCounts
 * @var iterable $overdue
 * @var iterable $recent
 * @var string $today
 */

$this->assign('title', 'Control Tower — Zlecenia');

$nlStatusMap = [
    1 => ['label' => 'Przyjęte',     'cls' => 'bg-warning',  'icon' => 'ri-inbox-line'],
    2 => ['label' => 'Zaplanowane',  'cls' => 'bg-info',     'icon' => 'ri-calendar-check-line'],
    3 => ['label' => 'Załadowane',   'cls' => 'bg-purple',   'icon' => 'ri-truck-line'],
    4 => ['label' => 'Zrealizowane', 'cls' => 'bg-success',  'icon' => 'ri-checkbox-circle-line'],
    5 => ['label' => 'Zafakturowane','cls' => 'bg-primary',  'icon' => 'ri-file-text-line'],
];

$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';
$listUrl = fn(string $s) => $this->Url->build(['action' => 'index', '?' => ['status' => $s]]);
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i> Lista zleceń
    </a>
    <h4 class="mb-0 fw-semibold ms-2"><i class="ri-dashboard-line me-2 text-primary"></i>Control Tower</h4>
    <span class="text-muted small ms-2">Dziś: <?= h($today) ?></span>
</div>

<!-- ── Kafelki statystyk ── -->
<div class="row g-3 mb-4">

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= $listUrl('') ?>" class="text-decoration-none">
        <div class="card text-center h-100 border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-dark"><?= number_format($stats['total'], 0, ',', ' ') ?></div>
                <div class="small text-muted mt-1"><i class="ri-list-check me-1"></i>Wszystkich</div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= $listUrl('niezafakt') ?>" class="text-decoration-none">
        <div class="card text-center h-100 border-0 shadow-sm border-start border-4 border-primary">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-primary"><?= number_format($stats['bez_faktury'], 0, ',', ' ') ?></div>
                <div class="small text-muted mt-1"><i class="ri-file-add-line me-1"></i>Niezafakturowane</div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= $listUrl('brak_pod') ?>" class="text-decoration-none">
        <div class="card text-center h-100 border-0 shadow-sm border-start border-4 border-warning">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-warning"><?= number_format($stats['bez_pod'], 0, ',', ' ') ?></div>
                <div class="small text-muted mt-1"><i class="ri-alarm-warning-line me-1"></i>Bez POD</div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= $listUrl('brak_fk') ?>" class="text-decoration-none">
        <div class="card text-center h-100 border-0 shadow-sm border-start border-4 border-danger">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-danger"><?= number_format($stats['bez_fk'], 0, ',', ' ') ?></div>
                <div class="small text-muted mt-1"><i class="ri-file-damage-line me-1"></i>Bez FK</div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= $listUrl('przetermin') ?>" class="text-decoration-none">
        <div class="card text-center h-100 border-0 shadow-sm border-start border-4 border-danger">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-danger"><?= number_format($stats['przetermin'], 0, ',', ' ') ?></div>
                <div class="small text-muted mt-1"><i class="ri-time-line me-1"></i>Przeterminowane</div>
            </div>
        </div>
        </a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="card text-center h-100 border-0 shadow-sm border-start border-4 border-success">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success"><?= number_format($stats['kompletne'], 0, ',', ' ') ?></div>
                <div class="small text-muted mt-1"><i class="ri-check-double-line me-1"></i>Kompletne</div>
            </div>
        </div>
    </div>

</div>

<div class="row g-3">

<!-- ── Rozkład statusów Nordlogis ── -->
<div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-header fw-semibold bg-white border-bottom">
            <i class="ri-bar-chart-line me-1 text-primary"></i> Statusy operacyjne
        </div>
        <div class="card-body">
            <?php
            $nlTotal = array_sum($nlCounts);
            foreach ($nlStatusMap as $val => $s):
                $cnt = $nlCounts[$val] ?? 0;
                $pct = $nlTotal > 0 ? round($cnt / $nlTotal * 100) : 0;
            ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-medium">
                        <i class="<?= $s['icon'] ?> me-1"></i><?= h($s['label']) ?>
                    </span>
                    <span class="small text-muted"><?= $cnt ?> (<?= $pct ?>%)</span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar <?= $s['cls'] ?>"
                         role="progressbar" style="width:<?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Przeterminowane ── -->
<div class="col-lg-7">
    <div class="card border-0 shadow-sm border-danger h-100">
        <div class="card-header fw-semibold bg-danger-subtle text-danger border-bottom d-flex align-items-center justify-content-between">
            <span><i class="ri-time-line me-1"></i>Przeterminowane (brak POD po terminie)</span>
            <a href="<?= $listUrl('przetermin') ?>" class="btn btn-sm btn-outline-danger py-0">
                Pokaż wszystkie
            </a>
        </div>
        <?php $overdueArr = $overdue->toArray(); ?>
        <?php if (empty($overdueArr)): ?>
        <div class="card-body text-center text-success py-4">
            <i class="ri-check-double-line fs-2"></i>
            <p class="mt-2 mb-0 fw-medium">Brak przeterminowanych zleceń</p>
        </div>
        <?php else: ?>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Symbol</th>
                        <th>Zleceniodawca</th>
                        <th>Termin rozł.</th>
                        <th>Opóźnienie</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($overdueArr as $o):
                    $delivStr = $o->date_delivery instanceof \DateTimeInterface
                        ? $o->date_delivery->format('Y-m-d')
                        : substr((string)($o->date_delivery ?? ''), 0, 10);
                    $daysLate = 0;
                    if ($delivStr) {
                        $diff = (new \DateTimeImmutable($today))->diff(new \DateTimeImmutable($delivStr));
                        $daysLate = $diff->days;
                    }
                ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <a href="<?= $this->Url->build(['action' => 'view', $o->id]) ?>"
                           class="text-decoration-none"><?= h($o->symbol) ?></a>
                    </td>
                    <td class="text-truncate" style="max-width:160px"><?= h($o->buyer_name) ?></td>
                    <td class="text-danger fw-semibold"><?= h($fdate($o->date_delivery)) ?></td>
                    <td><span class="badge bg-danger"><?= $daysLate ?>d</span></td>
                    <td>
                        <a href="<?= $this->Url->build(['action' => 'view', $o->id]) ?>"
                           class="btn btn-xs btn-outline-secondary py-0 px-1">
                            <i class="ri-eye-line"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Ostatnio zmienione ── -->
<div class="col-12">
    <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold bg-white border-bottom">
            <i class="ri-history-line me-1 text-primary"></i> Ostatnio zmienione
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Symbol</th>
                        <th>Zleceniodawca</th>
                        <th>Data rozładunku</th>
                        <th>Status</th>
                        <th>POL</th>
                        <th>POD</th>
                        <th>FK</th>
                        <th>FS</th>
                        <th>Zmieniono</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $o):
                    $ns = $nlStatusMap[(int)($o->nordlogis_status ?? 1)] ?? $nlStatusMap[1];
                    $chk = fn($v) => $v ? '<span class="text-success">✔</span>' : '<span class="text-muted">·</span>';
                    $modifiedStr = $o->modified instanceof \DateTimeInterface
                        ? $o->modified->format('d.m H:i')
                        : substr((string)($o->modified ?? ''), 0, 16);
                ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <a href="<?= $this->Url->build(['action' => 'view', $o->id]) ?>"
                           class="text-decoration-none"><?= h($o->symbol) ?></a>
                    </td>
                    <td class="text-truncate" style="max-width:180px"><?= h($o->buyer_name) ?></td>
                    <td><?= h($fdate($o->date_delivery)) ?></td>
                    <td>
                        <span class="badge <?= $ns['cls'] ?> text-white" style="font-size:.7rem">
                            <?= h($ns['label']) ?>
                        </span>
                    </td>
                    <td class="text-center"><?= $chk($o->pol_at) ?></td>
                    <td class="text-center"><?= $chk($o->pod_at) ?></td>
                    <td class="text-center"><?= $chk($o->fk_at) ?></td>
                    <td class="text-center"><?= $chk($o->fs_at) ?></td>
                    <td class="text-muted small"><?= h($modifiedStr) ?></td>
                    <td>
                        <a href="<?= $this->Url->build(['action' => 'view', $o->id]) ?>"
                           class="btn btn-xs btn-outline-secondary py-0 px-1">
                            <i class="ri-eye-line"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div><!-- /row -->

<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $records
 * @var string $filter
 * @var string $type
 * @var array $stats
 */
$this->assign('title', __('Serwisy, przeglądy i ubezpieczenia'));

$typeLabels = [
    'technical_inspection' => ['Badanie techniczne', 'bg-warning text-dark'],
    'service'              => ['Serwis',              'bg-info'],
    'tacho_calibration'    => ['Kalibracja tacho',   'bg-primary'],
    'adr_cert'             => ['Certyfikat ADR',     'bg-danger'],
    'insurance'            => ['Ubezpieczenie',      'bg-success'],
    'oc'                   => ['OC',                  'bg-success'],
    'ac'                   => ['AC',                  'bg-success'],
    'extinguisher'         => ['Gaśnica',            'bg-secondary'],
    'first_aid'            => ['Apteczka',           'bg-secondary'],
    'other'                => ['Inne',                'bg-dark'],
];

$fmtDate = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d.m.Y') : (string)$v;

$today = new \DateTime('today');
$in30 = (new \DateTime('today'))->modify('+30 days');
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= __('Serwisy, przeglądy i ubezpieczenia') ?></h1>
        <p class="text-muted small mb-0">
            <?= __('Historia wykonania + terminy ważności. Alertujemy 30 dni przed wygaśnięciem.') ?>
        </p>
    </div>
    <?= $this->Html->link(
        '<i class="ri-add-line"></i> ' . __('Nowy wpis'),
        ['action' => 'add'],
        ['class' => 'btn btn-primary btn-sm', 'escape' => false]
    ) ?>
</div>

<!-- Alert bar z statystykami -->
<div class="row g-2 mb-3">
    <div class="col-md-4">
        <div class="card border-danger">
            <div class="card-body py-2">
                <div class="small text-muted"><?= __('Wygasłe') ?></div>
                <div class="fs-3 fw-bold text-danger"><?= (int)$stats['expired'] ?></div>
                <?php if ($stats['expired'] > 0): ?>
                    <?= $this->Html->link(__('Pokaż') . ' →', ['action' => 'index', '?' => ['filter' => 'expired']], ['class' => 'small text-danger']) ?>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body py-2">
                <div class="small text-muted"><?= __('Wygasają w 30 dniach') ?></div>
                <div class="fs-3 fw-bold text-warning"><?= (int)$stats['expiring_soon'] ?></div>
                <?php if ($stats['expiring_soon'] > 0): ?>
                    <?= $this->Html->link(__('Pokaż') . ' →', ['action' => 'index', '?' => ['filter' => 'expiring']], ['class' => 'small text-warning-emphasis']) ?>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body py-2 d-flex align-items-center">
                <div class="flex-grow-1">
                    <div class="small text-muted"><?= __('Filtr') ?></div>
                    <div class="d-flex gap-1 flex-wrap mt-1">
                        <?= $this->Html->link(__('Wszystkie'), ['action' => 'index'],
                            ['class' => 'btn btn-sm ' . ($filter === '' ? 'btn-primary' : 'btn-outline-secondary')]) ?>
                        <?= $this->Html->link(__('Aktywne'), ['action' => 'index', '?' => ['filter' => 'valid']],
                            ['class' => 'btn btn-sm ' . ($filter === 'valid' ? 'btn-success' : 'btn-outline-secondary')]) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (count($records) === 0): ?>
    <div class="alert alert-info"><?= __('Brak wpisów.') ?></div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= __('Pojazd / Naczepa') ?></th>
                        <th><?= __('Typ') ?></th>
                        <th><?= __('Wykonano') ?></th>
                        <th><?= __('Ważne do') ?></th>
                        <th><?= __('Dni') ?></th>
                        <th><?= __('Dostawca') ?></th>
                        <th><?= __('Koszt') ?></th>
                        <th class="text-end" style="width:110px"><?= __('Akcje') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r):
                        [$tLabel, $tCls] = $typeLabels[(string)$r->maintenance_type] ?? [(string)$r->maintenance_type, 'bg-secondary'];
                        $vu = $r->valid_until;
                        $daysLeft = null; $isExpired = false; $isExpiring = false;
                        if ($vu instanceof \DateTimeInterface) {
                            $isExpired = $vu < $today;
                            $isExpiring = !$isExpired && $vu <= $in30;
                            $diff = $today->diff($vu);
                            $daysLeft = $diff->days * ($isExpired ? -1 : 1);
                        }
                        $rowCls = $isExpired ? 'table-danger' : ($isExpiring ? 'table-warning' : '');
                    ?>
                        <tr class="<?= $rowCls ?>">
                            <td>
                                <?php if (!empty($r->vehicle)): ?>
                                    <i class="ri-truck-line text-muted me-1"></i>
                                    <strong><?= h($r->vehicle->name) ?></strong>
                                    <?php if ($r->vehicle->plate): ?>
                                        <span class="badge bg-secondary-subtle text-body ms-1"><?= h($r->vehicle->plate) ?></span>
                                    <?php endif ?>
                                <?php elseif (!empty($r->trailer)): ?>
                                    <i class="ri-roadster-line text-muted me-1"></i>
                                    <strong><?= h($r->trailer->name) ?></strong>
                                    <?php if ($r->trailer->plate): ?>
                                        <span class="badge bg-secondary-subtle text-body ms-1"><?= h($r->trailer->plate) ?></span>
                                    <?php endif ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>
                            <td><span class="badge <?= $tCls ?>"><?= h($tLabel) ?></span></td>
                            <td class="small"><?= h($fmtDate($r->performed_at)) ?></td>
                            <td class="small"><?= h($fmtDate($r->valid_until)) ?></td>
                            <td class="small text-nowrap">
                                <?php if ($daysLeft !== null): ?>
                                    <?php if ($isExpired): ?>
                                        <span class="text-danger fw-bold"><?= __('Wygasło :d dni temu', [':d' => abs($daysLeft)]) ?></span>
                                    <?php elseif ($isExpiring): ?>
                                        <span class="text-warning fw-bold"><?= __(':d dni', [':d' => $daysLeft]) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted"><?= __(':d dni', [':d' => $daysLeft]) ?></span>
                                    <?php endif ?>
                                <?php endif ?>
                            </td>
                            <td class="small text-muted"><?= h($r->supplier ?? '') ?></td>
                            <td class="small text-nowrap">
                                <?php if ($r->cost !== null): ?>
                                    <?= number_format((float)$r->cost, 2, ',', ' ') ?> <?= h($r->currency ?? 'PLN') ?>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <?= $this->Html->link('<i class="ri-edit-line"></i>', ['action' => 'edit', $r->id],
                                    ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false]) ?>
                                <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>', ['action' => 'delete', $r->id],
                                    ['class' => 'btn btn-sm btn-outline-danger', 'escape' => false,
                                     'confirm' => __('Usunąć wpis?')]) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">
        <ul class="pagination pagination-sm">
            <?= $this->Paginator->prev() ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next() ?>
        </ul>
    </div>
<?php endif ?>

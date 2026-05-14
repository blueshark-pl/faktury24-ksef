<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Trailer> $rows
 * @var string $q
 * @var string $type
 */
$this->assign('title', __('Naczepy'));

$typeLabel = [
    'curtain'   => __('Firanka'),
    'box'       => __('Box / skrzynia'),
    'fridge'    => __('Chłodnia'),
    'tanker'    => __('Cysterna'),
    'flatbed'   => __('Platforma'),
    'drawbar'   => __('Drawbar (przyczepa)'),
    'mega'      => __('Mega'),
    'silo'      => __('Silos'),
    'container' => __('Do kontenerów'),
    'tipper'    => __('Wywrotka'),
];
$csrf = (string)$this->request->getAttribute('csrfToken');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-roadster-line me-1"></i><?= __('Naczepy / przyczepy') ?>
    </h4>
    <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-primary btn-sm">
        <i class="ri-add-line me-1"></i><?= __('Dodaj naczepę') ?>
    </a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1"><?= __('Szukaj') ?></label>
                <input type="search" name="q" class="form-control form-control-sm"
                       value="<?= h($q) ?>" placeholder="<?= __('Nazwa, rejestracja, VIN…') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1"><?= __('Typ') ?></label>
                <select name="type" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie') ?></option>
                    <?php foreach ($typeLabel as $k => $v): ?>
                        <option value="<?= h($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary btn-sm w-100"><i class="ri-filter-line me-1"></i><?= __('Filtruj') ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3"><?= __('Nazwa') ?></th>
                    <th><?= __('Rejestracja') ?></th>
                    <th><?= __('Typ') ?></th>
                    <th class="text-end"><?= __('DMC') ?></th>
                    <th class="text-end"><?= __('Wymiary') ?></th>
                    <th class="text-end"><?= __('Osie') ?></th>
                    <th class="text-center"><?= __('Status') ?></th>
                    <th class="text-end pe-3" style="width:120px"><?= __('Akcje') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $emptyResult = empty($rows) || $rows->count() === 0; ?>
                <?php if ($emptyResult): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="ri-roadster-line d-block mb-2" style="font-size:2.4rem"></i>
                        <?= __('Brak naczep. Dodaj pierwszą aby planować kombinacje ciągnik+naczepa.') ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $t): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold"><?= h($t->name) ?></div>
                                <?php if (!empty($t->vin)): ?>
                                    <div class="text-muted small">VIN: <?= h($t->vin) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($t->plate)): ?>
                                    <span class="badge bg-light text-dark border" style="font-family:monospace;font-size:.8em"><?= h($t->plate) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info border"><?= h($typeLabel[$t->type] ?? $t->type) ?></span>
                                <?php if (!empty($t->adr_certified)): ?>
                                    <span class="badge bg-danger-subtle text-danger border ms-1">ADR</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($t->gross_weight_kg): ?>
                                    <?= number_format($t->gross_weight_kg / 1000, 1, ',', ' ') ?> <small class="text-muted">t</small>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap small">
                                <?php if ($t->length_cm || $t->width_cm || $t->height_cm): ?>
                                    <?= $t->length_cm ? number_format($t->length_cm / 100, 2, ',', '') . 'm' : '–' ?>
                                    · <?= $t->width_cm ? number_format($t->width_cm / 100, 2, ',', '') . 'm' : '–' ?>
                                    · <?= $t->height_cm ? number_format($t->height_cm / 100, 2, ',', '') . 'm' : '–' ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= h($t->axle_count ?? '—') ?></td>
                            <td class="text-center">
                                <?php if ($t->is_default): ?>
                                    <span class="badge bg-primary"><i class="ri-star-fill"></i> <?= __('domyślna') ?></span>
                                <?php elseif ($t->is_active): ?>
                                    <span class="badge bg-success-subtle text-success border"><?= __('aktywna') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= __('nieaktywna') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <a href="<?= $this->Url->build(['action' => 'edit', $t->id]) ?>" class="btn btn-sm btn-outline-secondary"><i class="ri-edit-line"></i></a>
                                <form method="post" action="<?= $this->Url->build(['action' => 'delete', $t->id]) ?>" style="display:inline"
                                      onsubmit="return confirm('<?= __('Usunąć naczepę?') ?>')">
                                    <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

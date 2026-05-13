<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Vehicle> $rows
 * @var string $q
 * @var string $type
 */
$this->assign('title', __('Pojazdy'));

$typeLabel = [
    'truck'   => __('Samochód ciężarowy'),
    'tractor' => __('Ciągnik siodłowy'),
    'van'     => __('Van / dostawczy'),
    'trailer' => __('Naczepa / przyczepa'),
];
$emissionLabel = [
    'euro_1' => 'Euro 1', 'euro_2' => 'Euro 2', 'euro_3' => 'Euro 3',
    'euro_4' => 'Euro 4', 'euro_5' => 'Euro 5', 'euro_6' => 'Euro 6',
    'eev'    => 'EEV',
];
$csrf = (string)$this->request->getAttribute('csrfToken');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-truck-line me-1"></i><?= __('Pojazdy') ?>
    </h4>
    <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-primary btn-sm">
        <i class="ri-add-line me-1"></i><?= __('Dodaj pojazd') ?>
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
                <label class="form-label small mb-1"><?= __('Typ pojazdu') ?></label>
                <select name="type" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie') ?></option>
                    <?php foreach ($typeLabel as $k => $v): ?>
                        <option value="<?= h($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-primary btn-sm w-100">
                    <i class="ri-filter-line me-1"></i><?= __('Filtruj') ?>
                </button>
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
                    <th><?= __('Emisja') ?></th>
                    <th class="text-center"><?= __('Status') ?></th>
                    <th class="text-end pe-3" style="width:120px"><?= __('Akcje') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $emptyResult = empty($rows) || $rows->count() === 0; ?>
                <?php if ($emptyResult): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5">
                        <i class="ri-truck-line d-block mb-2" style="font-size:2.4rem"></i>
                        <?= __('Brak pojazdów. Dodaj pierwszy aby liczyć trasy z profilem truck.') ?>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $v): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="bg-primary-subtle text-primary border d-inline-flex align-items-center justify-content-center"
                                          style="width:32px;height:32px;border-radius:50%">
                                        <i class="ri-truck-line"></i>
                                    </span>
                                    <div>
                                        <div class="fw-semibold"><?= h($v->name) ?></div>
                                        <?php if (!empty($v->vin)): ?>
                                            <div class="text-muted small">VIN: <?= h($v->vin) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($v->plate)): ?>
                                    <span class="badge bg-light text-dark border" style="font-family:monospace;font-size:.8em"><?= h($v->plate) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?= h($typeLabel[$v->type] ?? $v->type) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if ($v->gross_weight_kg): ?>
                                    <?= number_format($v->gross_weight_kg / 1000, 1, ',', ' ') ?> <small class="text-muted">t</small>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap small">
                                <?php if ($v->length_cm || $v->width_cm || $v->height_cm): ?>
                                    <?= $v->length_cm ? number_format($v->length_cm / 100, 1, ',', '') . 'm' : '–' ?>
                                    · <?= $v->width_cm ? number_format($v->width_cm / 100, 1, ',', '') . 'm' : '–' ?>
                                    · <?= $v->height_cm ? number_format($v->height_cm / 100, 1, ',', '') . 'm' : '–' ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($v->emission_class)): ?>
                                    <span class="badge bg-success-subtle text-success border"><?= h($emissionLabel[$v->emission_class] ?? $v->emission_class) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($v->is_default): ?>
                                    <span class="badge bg-primary" title="<?= __('Domyślny pojazd') ?>"><i class="ri-star-fill"></i> <?= __('domyślny') ?></span>
                                <?php elseif ($v->is_active): ?>
                                    <span class="badge bg-success-subtle text-success border"><?= __('aktywny') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= __('nieaktywny') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <a href="<?= $this->Url->build(['action' => 'edit', $v->id]) ?>"
                                   class="btn btn-sm btn-outline-secondary" title="<?= __('Edytuj') ?>">
                                    <i class="ri-edit-line"></i>
                                </a>
                                <form method="post" action="<?= $this->Url->build(['action' => 'delete', $v->id]) ?>"
                                      style="display:inline"
                                      onsubmit="return confirm('<?= __('Usunąć pojazd?') ?>')">
                                    <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= __('Usuń') ?>">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

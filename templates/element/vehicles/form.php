<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Vehicle $entity
 */
$typeOptions = [
    'truck'   => __('Samochód ciężarowy'),
    'tractor' => __('Ciągnik siodłowy'),
    'van'     => __('Van / dostawczy'),
    'trailer' => __('Naczepa / przyczepa'),
];
$emissionOptions = [
    'euro_6' => 'Euro 6',
    'euro_5' => 'Euro 5',
    'euro_4' => 'Euro 4',
    'euro_3' => 'Euro 3',
    'euro_2' => 'Euro 2',
    'euro_1' => 'Euro 1',
    'eev'    => 'EEV',
];
$tunnelOptions = ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E'];
?>
<?= $this->Form->create($entity, ['type' => 'post']) ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-truck-line me-1"></i><?= __('Identyfikacja') ?></strong></div>
            <div class="card-body">
                <div class="mb-2">
                    <label class="form-label small mb-1"><?= __('Nazwa') ?> *</label>
                    <input name="name" class="form-control" required value="<?= h($entity->name ?? '') ?>"
                           placeholder="<?= __('np. Volvo FH 16 #4') ?>">
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Rejestracja') ?></label>
                        <input name="plate" class="form-control" value="<?= h($entity->plate ?? '') ?>"
                               style="font-family:monospace;text-transform:uppercase" placeholder="KR12345">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">VIN</label>
                        <input name="vin" class="form-control" value="<?= h($entity->vin ?? '') ?>" maxlength="32">
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label small mb-1"><?= __('Typ pojazdu') ?></label>
                    <select name="type" class="form-select">
                        <?php foreach ($typeOptions as $k => $v): ?>
                            <option value="<?= h($k) ?>" <?= ($entity->type ?? 'truck') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mt-3 d-flex gap-3 flex-wrap">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                               <?= !empty($entity->is_active) || !isset($entity->id) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active"><?= __('Aktywny') ?></label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_default" value="0">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default"
                               <?= !empty($entity->is_default) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_default"><?= __('Domyślny pojazd firmy') ?></label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-scales-3-line me-1"></i><?= __('Masa i wymiary') ?></strong></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('DMC (kg)') ?></label>
                        <input name="gross_weight_kg" type="number" min="0" class="form-control"
                               value="<?= h($entity->gross_weight_kg ?? '') ?>" placeholder="40000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Liczba osi') ?></label>
                        <input name="axle_count" type="number" min="0" max="20" class="form-control"
                               value="<?= h($entity->axle_count ?? '') ?>" placeholder="5">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Max nacisk na oś (kg)') ?></label>
                        <input name="axle_load_kg" type="number" min="0" class="form-control"
                               value="<?= h($entity->axle_load_kg ?? '') ?>" placeholder="11500">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Wysokość (cm)') ?></label>
                        <input name="height_cm" type="number" min="0" class="form-control"
                               value="<?= h($entity->height_cm ?? '') ?>" placeholder="400">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Szerokość (cm)') ?></label>
                        <input name="width_cm" type="number" min="0" class="form-control"
                               value="<?= h($entity->width_cm ?? '') ?>" placeholder="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Długość (cm)') ?></label>
                        <input name="length_cm" type="number" min="0" class="form-control"
                               value="<?= h($entity->length_cm ?? '') ?>" placeholder="1650">
                    </div>
                    <div class="col-md-12 mt-2 pt-2 border-top">
                        <label class="form-label small mb-1">
                            <i class="ri-money-pound-circle-line text-warning me-1"></i>
                            <?= __('Stawka frachtu (PLN/km)') ?>
                        </label>
                        <input name="rate_per_km" type="number" step="0.01" min="0" class="form-control"
                               value="<?= h($entity->rate_per_km ?? '') ?>" placeholder="4.50">
                        <div class="form-text" style="font-size:.7rem">
                            <?= __('Używane w planerze /trasy do kalkulacji ceny frachtu (stawka × km + opłaty).') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-leaf-line me-1"></i><?= __('Emisja i ADR') ?></strong></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Norma emisji') ?></label>
                        <select name="emission_class" class="form-select">
                            <option value=""><?= __('— wybierz —') ?></option>
                            <?php foreach ($emissionOptions as $k => $v): ?>
                                <option value="<?= h($k) ?>" <?= ($entity->emission_class ?? '') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Kategoria tunelu (ADR)') ?></label>
                        <select name="tunnel_category" class="form-select">
                            <option value=""><?= __('— brak —') ?></option>
                            <?php foreach ($tunnelOptions as $k => $v): ?>
                                <option value="<?= h($k) ?>" <?= ($entity->tunnel_category ?? '') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check mt-2">
                            <input type="hidden" name="hazardous_goods" value="0">
                            <input class="form-check-input" type="checkbox" name="hazardous_goods" value="1" id="haz"
                                   <?= !empty($entity->hazardous_goods) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="haz"><?= __('Przewozi materiały niebezpieczne (ADR)') ?></label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-sticky-note-line me-1"></i><?= __('Notatki') ?></strong></div>
            <div class="card-body">
                <textarea name="notes" class="form-control" rows="6"
                          placeholder="<?= __('Dodatkowe informacje o pojeździe, ograniczenia, uwagi…') ?>"><?= h($entity->notes ?? '') ?></textarea>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between mt-3">
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i><?= __('Anuluj') ?>
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line me-1"></i><?= __('Zapisz') ?>
    </button>
</div>

<?= $this->Form->end() ?>

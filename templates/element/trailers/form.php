<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Trailer $entity
 */
$typeOptions = [
    'curtain'   => __('Firanka (kurtynowa)'),
    'box'       => __('Box / skrzynia'),
    'fridge'    => __('Chłodnia (refrigerated)'),
    'tanker'    => __('Cysterna'),
    'flatbed'   => __('Platforma / lawety'),
    'drawbar'   => __('Drawbar (przyczepa do tandem)'),
    'mega'      => __('Mega (low-deck, max objętość)'),
    'silo'      => __('Silos'),
    'container' => __('Pod kontenery'),
    'tipper'    => __('Wywrotka'),
];
// Presety per typ — auto-fill w JS
$presets = [
    'curtain' => ['length_m'=>13.62, 'width_m'=>2.55, 'height_m'=>2.70, 'axle_count'=>3, 'gross_weight_kg'=>35000, 'volume_m3'=>91],
    'box'     => ['length_m'=>13.60, 'width_m'=>2.55, 'height_m'=>2.70, 'axle_count'=>3, 'gross_weight_kg'=>35000, 'volume_m3'=>88],
    'fridge'  => ['length_m'=>13.50, 'width_m'=>2.60, 'height_m'=>2.65, 'axle_count'=>3, 'gross_weight_kg'=>35000, 'volume_m3'=>86],
    'tanker'  => ['length_m'=>13.00, 'width_m'=>2.55, 'height_m'=>3.60, 'axle_count'=>3, 'gross_weight_kg'=>40000, 'volume_m3'=>34],
    'flatbed' => ['length_m'=>13.62, 'width_m'=>2.55, 'height_m'=>0.00, 'axle_count'=>3, 'gross_weight_kg'=>35000, 'volume_m3'=>0],
    'drawbar' => ['length_m'=>7.82,  'width_m'=>2.55, 'height_m'=>2.70, 'axle_count'=>2, 'gross_weight_kg'=>18000, 'volume_m3'=>50],
    'mega'    => ['length_m'=>13.62, 'width_m'=>2.55, 'height_m'=>3.00, 'axle_count'=>3, 'gross_weight_kg'=>35000, 'volume_m3'=>100],
];
?>
<?= $this->Form->create($entity, ['type' => 'post']) ?>

<?php
// Ostrzezenie krytyczne dla klasyfikacji tolls w planerze — brakujace pola
// zaniza opłaty i moze powodowac blednie zaklasyfikowany zestaw w HERE.
$missing = [];
if (empty($entity->axle_count))      $missing[] = __('liczba osi');
if (empty($entity->gross_weight_kg)) $missing[] = __('DMC');
if (!empty($missing) && !empty($entity->id)):
?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
    <i class="ri-alert-line fs-4"></i>
    <div class="flex-grow-1">
        <strong><?= __('Uwaga: brakuje kluczowych parametrów!') ?></strong><br>
        <small>
            <?= __('Brak pola: :fields. To powoduje że planer /trasy przy wyborze tej naczepy WYSYŁA DO HERE zaniżoną liczbę osi i DMC — opłaty (tolls) będą policzone za mało. Uzupełnij poniżej.', [
                ':fields' => '<strong>' . implode(', ', $missing) . '</strong>',
            ]) ?>
        </small>
    </div>
</div>
<?php endif ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-roadster-line me-1"></i><?= __('Identyfikacja') ?></strong></div>
            <div class="card-body">
                <div class="mb-2">
                    <label class="form-label small mb-1"><?= __('Nazwa') ?> *</label>
                    <input name="name" class="form-control" required value="<?= h($entity->name ?? '') ?>"
                           placeholder="<?= __('np. Schmitz #1') ?>">
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Rejestracja') ?></label>
                        <input name="plate" class="form-control" value="<?= h($entity->plate ?? '') ?>"
                               style="font-family:monospace;text-transform:uppercase" placeholder="WPK 12345">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">VIN</label>
                        <input name="vin" class="form-control" value="<?= h($entity->vin ?? '') ?>" maxlength="32">
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label small mb-1"><?= __('Typ naczepy') ?> *</label>
                    <select name="type" id="trailer_type" class="form-select" required>
                        <?php foreach ($typeOptions as $k => $v): ?>
                            <option value="<?= h($k) ?>" <?= ($entity->type ?? 'curtain') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" style="font-size:.7rem">
                        <i class="ri-magic-line me-1 text-warning"></i>
                        <?= __('Wybór typu auto-wypełni typowe wymiary i pojemność.') ?>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-3 flex-wrap">
                    <div class="form-check form-switch">
                        <input type="hidden" name="adr_certified" value="0">
                        <input class="form-check-input" type="checkbox" name="adr_certified" value="1" id="adr_certified"
                               <?= !empty($entity->adr_certified) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="adr_certified"><?= __('Certyfikat ADR') ?></label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                               <?= !empty($entity->is_active) || !isset($entity->id) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active"><?= __('Aktywna') ?></label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_default" value="0">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default"
                               <?= !empty($entity->is_default) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_default"><?= __('Domyślna') ?></label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-scales-3-line me-1"></i><?= __('Masa, osie, wymiary') ?></strong></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('DMC naczepy (kg)') ?></label>
                        <input name="gross_weight_kg" type="number" min="0" class="form-control"
                               value="<?= h($entity->gross_weight_kg ?? '') ?>" placeholder="35000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Ładowność (kg)') ?></label>
                        <input name="payload_kg" type="number" min="0" class="form-control"
                               value="<?= h($entity->payload_kg ?? '') ?>" placeholder="24000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Liczba osi') ?></label>
                        <input name="axle_count" type="number" min="0" max="6" class="form-control"
                               value="<?= h($entity->axle_count ?? '') ?>" placeholder="3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Max nacisk na oś (kg)') ?></label>
                        <input name="axle_load_kg" type="number" min="0" class="form-control"
                               value="<?= h($entity->axle_load_kg ?? '') ?>" placeholder="9000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1"><?= __('Długość (m)') ?></label>
                        <div class="input-group">
                            <input name="length_m" type="number" step="0.01" min="0" max="25" class="form-control"
                                   value="<?= $entity->length_cm ? number_format($entity->length_cm / 100, 2, '.', '') : '' ?>" placeholder="13.62">
                            <span class="input-group-text">m</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1"><?= __('Szerokość (m)') ?></label>
                        <div class="input-group">
                            <input name="width_m" type="number" step="0.01" min="0" max="3" class="form-control"
                                   value="<?= $entity->width_cm ? number_format($entity->width_cm / 100, 2, '.', '') : '' ?>" placeholder="2.55">
                            <span class="input-group-text">m</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1"><?= __('Wysokość (m)') ?></label>
                        <div class="input-group">
                            <input name="height_m" type="number" step="0.01" min="0" max="5" class="form-control"
                                   value="<?= $entity->height_cm ? number_format($entity->height_cm / 100, 2, '.', '') : '' ?>" placeholder="2.70">
                            <span class="input-group-text">m</span>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small mb-1"><?= __('Objętość ładunkowa (m³)') ?></label>
                        <input name="volume_m3" type="number" step="0.1" min="0" max="200" class="form-control"
                               value="<?= h($entity->volume_m3 ?? '') ?>" placeholder="91.0">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-money-pound-circle-line me-1 text-warning"></i><?= __('Koszty') ?></strong></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Amortyzacja (PLN/dzień)') ?></label>
                        <input name="amortization_per_day_pln" type="number" step="0.01" min="0" class="form-control"
                               value="<?= h($entity->amortization_per_day_pln ?? '') ?>" placeholder="180.00">
                        <div class="form-text" style="font-size:.7rem"><?= __('Zakup lub leasing / liczba dni roboczych') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Ubezpieczenie (PLN/rok)') ?></label>
                        <input name="insurance_per_year_pln" type="number" step="0.01" min="0" class="form-control"
                               value="<?= h($entity->insurance_per_year_pln ?? '') ?>" placeholder="6500.00">
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
                          placeholder="<?= __('Dodatkowe informacje o naczepie…') ?>"><?= h($entity->notes ?? '') ?></textarea>
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

<script>
(function () {
    var sel = document.getElementById('trailer_type');
    if (!sel) return;
    var PRESETS = <?= json_encode($presets) ?>;
    sel.addEventListener('change', function () {
        var p = PRESETS[sel.value];
        if (!p) return;
        Object.keys(p).forEach(function (k) {
            var input = document.querySelector('[name="' + k + '"]');
            if (input && !input.value) input.value = p[k];
        });
    });
})();
</script>

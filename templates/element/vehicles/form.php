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
// Typ zestawu — kategoria z auto-fill wymiarów + automatyczną klasyfikacją toll
$combinationOptions = [
    ''         => '— ' . __('wybierz typ zestawu') . ' —',
    'standard' => __('Zestaw standard (16,5 m × 2,55 m × 4,0 m, 40t, 5 osi)'),
    'mega'     => __('Mega (niska naczepa 3,0 m wys., maks. objętość ~100 m³)'),
    'fridge'   => __('Chłodnia (2,60 m szer. dozwolone, 40t, 5 osi)'),
    'tandem'   => __('Tandem (ciężarówka + przyczepa drawbar, 18,75 m max)'),
    'solo'     => __('Solo (pojedyncza ciężarówka 12 m, 2-3 osi)'),
    'bus'      => __('Autobus'),
    'oversize' => __('Ponadnormatywny (wymaga zezwolenia)'),
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

<?php
// Ostrzezenie dla planera /trasy — brakujace pola powoduja ze do HERE ida
// niekompletne parametry i klasyfikacja pojazdu jest zaniżona (mniej osi = tansze tolls).
$missing = [];
if (empty($entity->axle_count))      $missing[] = __('liczba osi');
if (empty($entity->gross_weight_kg)) $missing[] = __('DMC (masa całkowita)');
if (empty($entity->axle_load_kg))    $missing[] = __('nacisk na oś');
if (empty($entity->height_cm))       $missing[] = __('wysokość');
if (empty($entity->width_cm))        $missing[] = __('szerokość');
if (empty($entity->length_cm))       $missing[] = __('długość');
if (!empty($missing) && !empty($entity->id)):
?>
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">
    <i class="ri-alert-line fs-4 mt-1"></i>
    <div class="flex-grow-1">
        <strong><?= __('Uwaga — brakuje kluczowych parametrów pojazdu!') ?></strong>
        <div class="small mt-1">
            <?= __('Puste pola: :fields', [':fields' => '<strong>' . implode(', ', $missing) . '</strong>']) ?>
        </div>
        <div class="small mt-1">
            <?= __('Planer :link wysyła te parametry do HERE Routing API dla klasyfikacji pojazdu. Puste pola powodują że HERE traktuje pojazd jak osobowe lub najprostszą ciężarówkę → opłaty (tolls) są zaniżone. Uzupełnij wszystkie pola poniżej dla poprawnej kalkulacji.', [
                ':link' => '<a href="/trasy" target="_blank">/trasy</a>',
            ]) ?>
        </div>
    </div>
</div>
<?php endif ?>

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
                <div class="mt-2">
                    <label class="form-label small mb-1">
                        <i class="ri-stack-line text-primary me-1"></i>
                        <?= __('Typ zestawu') ?>
                    </label>
                    <select name="combination_type" id="combination_type" class="form-select">
                        <?php foreach ($combinationOptions as $k => $v): ?>
                            <option value="<?= h($k) ?>" <?= ($entity->combination_type ?? '') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" style="font-size:.7rem">
                        <i class="ri-magic-line me-1 text-warning"></i>
                        <?= __('Wybór typu auto-wypełni wymiary, masę i osie zgodnie ze standardami EU.') ?>
                    </div>
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
                        <label class="form-label small mb-1"><?= __('DMC zestawu (kg)') ?></label>
                        <input name="gross_weight_kg" type="number" min="0" class="form-control"
                               value="<?= h($entity->gross_weight_kg ?? '') ?>" placeholder="40000">
                        <div class="form-text" style="font-size:.7rem">
                            <?= __('Wpisz DMC CAŁEGO zestawu (ciągnik + naczepa z ładunkiem). Standard: 40000, mega/chłodnia: 40000.') ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Liczba osi ciągnika') ?></label>
                        <input name="axle_count" type="number" min="0" max="20" class="form-control"
                               value="<?= h($entity->axle_count ?? '') ?>" placeholder="2">
                        <div class="form-text" style="font-size:.7rem">
                            <?= __('Tylko osie ciągnika (typowo 2 dla 4×2, 3 dla 6×2). Osie naczepy dodadzą się osobno.') ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Max nacisk na oś (kg)') ?></label>
                        <input name="axle_load_kg" type="number" min="0" class="form-control"
                               value="<?= h($entity->axle_load_kg ?? '') ?>" placeholder="11500">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Wysokość (m)') ?></label>
                        <div class="input-group">
                            <input name="height_m" type="number" step="0.01" min="0" max="5" class="form-control"
                                   value="<?= $entity->height_cm ? number_format($entity->height_cm / 100, 2, '.', '') : '' ?>" placeholder="4.00">
                            <span class="input-group-text">m</span>
                        </div>
                        <div class="form-text" style="font-size:.7rem"><?= __('EU max 4,00 m') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Szerokość (m)') ?></label>
                        <div class="input-group">
                            <input name="width_m" type="number" step="0.01" min="0" max="3" class="form-control"
                                   value="<?= $entity->width_cm ? number_format($entity->width_cm / 100, 2, '.', '') : '' ?>" placeholder="2.55">
                            <span class="input-group-text">m</span>
                        </div>
                        <div class="form-text" style="font-size:.7rem"><?= __('EU max 2,55 m (2,60 chłodnie)') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Długość (m)') ?></label>
                        <div class="input-group">
                            <input name="length_m" type="number" step="0.01" min="0" max="25" class="form-control"
                                   value="<?= $entity->length_cm ? number_format($entity->length_cm / 100, 2, '.', '') : '' ?>" placeholder="16.50">
                            <span class="input-group-text">m</span>
                        </div>
                        <div class="form-text" style="font-size:.7rem"><?= __('EU max 16,50 m (zestaw) / 12,00 m (solo) / 18,75 m (tandem)') ?></div>
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
                    <div class="col-md-8 mt-2 pt-2 border-top">
                        <label class="form-label small mb-1">
                            <i class="ri-gas-station-line text-danger me-1"></i>
                            <?= __('Średnie spalanie (l/100km)') ?>
                        </label>
                        <input name="fuel_consumption_l_per_100km" type="number" step="0.1" min="0" max="100"
                               class="form-control"
                               value="<?= h($entity->fuel_consumption_l_per_100km ?? '') ?>" placeholder="30">
                        <div class="form-text" style="font-size:.7rem">
                            <?= __('Auto-fill w planerze przy wyborze pojazdu. Typowo: ciągnik 25-35, van 8-12, solo 15-20.') ?>
                        </div>
                    </div>
                    <div class="col-md-4 mt-2 pt-2 border-top">
                        <label class="form-label small mb-1"><?= __('Rodzaj paliwa') ?></label>
                        <select name="fuel_type" class="form-select">
                            <?php $ft = (string)($entity->fuel_type ?? 'diesel'); ?>
                            <option value="diesel"  <?= $ft === 'diesel' ? 'selected' : '' ?>><?= __('Diesel') ?></option>
                            <option value="petrol"  <?= $ft === 'petrol' ? 'selected' : '' ?>><?= __('Benzyna') ?></option>
                            <option value="lng"     <?= $ft === 'lng'    ? 'selected' : '' ?>>LNG</option>
                            <option value="electric"<?= $ft === 'electric'? 'selected' : '' ?>><?= __('Elektryczny') ?></option>
                            <option value="hybrid"  <?= $ft === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                        </select>
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

<script>
// Auto-fill wymiarów + masy + osi po wyborze typu zestawu
(function () {
    var sel = document.getElementById('combination_type');
    if (!sel) return;
    // Definicje EU normatywnych zestawów (Dyr. 96/53/EC)
    var COMBINATION_PRESETS = {
        'standard': { length_m: 16.50, width_m: 2.55, height_m: 4.00, gross_weight_kg: 40000, axle_count: 5, axle_load_kg: 11500 },
        'mega':     { length_m: 16.50, width_m: 2.55, height_m: 3.00, gross_weight_kg: 40000, axle_count: 5, axle_load_kg: 11500 },
        'fridge':   { length_m: 16.50, width_m: 2.60, height_m: 4.00, gross_weight_kg: 40000, axle_count: 5, axle_load_kg: 11500 },
        'tandem':   { length_m: 18.75, width_m: 2.55, height_m: 4.00, gross_weight_kg: 40000, axle_count: 6, axle_load_kg: 11500 },
        'solo':     { length_m: 12.00, width_m: 2.55, height_m: 4.00, gross_weight_kg: 18000, axle_count: 3, axle_load_kg: 11500 },
        'bus':      { length_m: 13.50, width_m: 2.55, height_m: 4.00, gross_weight_kg: 18000, axle_count: 3, axle_load_kg: 11500 },
        // oversize: nie auto-fill, user wypełnia ręcznie
    };
    function applyPreset(p) {
        if (!p) return;
        var map = {
            'length_m': p.length_m, 'width_m': p.width_m, 'height_m': p.height_m,
            'gross_weight_kg': p.gross_weight_kg, 'axle_count': p.axle_count, 'axle_load_kg': p.axle_load_kg,
        };
        Object.keys(map).forEach(function (name) {
            var input = document.querySelector('[name="' + name + '"]');
            if (input && !input.value) input.value = map[name];
        });
    }
    sel.addEventListener('change', function () {
        var preset = COMBINATION_PRESETS[sel.value];
        if (preset) applyPreset(preset);
    });
})();
</script>

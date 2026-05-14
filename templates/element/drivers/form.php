<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Driver $entity
 */
$licenseOptions = ['B', 'C', 'C1', 'C+E', 'C1+E', 'D', 'D1', 'D+E', 'T'];
$languageOptions = ['pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch', 'ua' => 'Українська', 'ru' => 'Русский', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano'];
$currentLicenses = $entity->license_categories ? array_map('trim', explode(',', $entity->license_categories)) : [];
$currentLangs = $entity->languages ? array_map('trim', explode(',', $entity->languages)) : [];
?>
<?= $this->Form->create($entity, ['type' => 'post']) ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-user-3-line me-1"></i><?= __('Dane osobowe') ?></strong></div>
            <div class="card-body">
                <div class="mb-2">
                    <label class="form-label small mb-1"><?= __('Imię i nazwisko') ?> *</label>
                    <input name="full_name" class="form-control" required value="<?= h($entity->full_name ?? '') ?>"
                           placeholder="<?= __('np. Jan Kowalski') ?>">
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Telefon') ?></label>
                        <input name="phone" class="form-control" value="<?= h($entity->phone ?? '') ?>" placeholder="+48 600 100 200">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Email') ?></label>
                        <input name="email" type="email" class="form-control" value="<?= h($entity->email ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Data urodzenia') ?></label>
                        <input name="birth_date" type="date" class="form-control" value="<?= $entity->birth_date ? $entity->birth_date->format('Y-m-d') : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Data zatrudnienia') ?></label>
                        <input name="hire_date" type="date" class="form-control" value="<?= $entity->hire_date ? $entity->hire_date->format('Y-m-d') : '' ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small mb-1"><?= __('Lata doświadczenia') ?></label>
                        <input name="experience_years" type="number" min="0" max="50" class="form-control" value="<?= h($entity->experience_years ?? '') ?>" placeholder="10">
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
                        <label class="form-check-label" for="is_default"><?= __('Domyślny') ?></label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-id-card-line me-1"></i><?= __('Uprawnienia') ?></strong></div>
            <div class="card-body">
                <div class="mb-2">
                    <label class="form-label small mb-1"><?= __('Kategorie prawa jazdy') ?></label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($licenseOptions as $cat): ?>
                            <div class="form-check">
                                <input class="form-check-input license-cat" type="checkbox" value="<?= h($cat) ?>" id="lic_<?= h($cat) ?>"
                                       <?= in_array($cat, $currentLicenses, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="lic_<?= h($cat) ?>"><?= h($cat) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="license_categories" id="license_categories_input" value="<?= h($entity->license_categories ?? '') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1"><?= __('Ważność prawa jazdy') ?></label>
                    <input name="license_expiry" type="date" class="form-control" value="<?= $entity->license_expiry ? $entity->license_expiry->format('Y-m-d') : '' ?>">
                </div>
                <hr>
                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="adr_certified" value="0">
                    <input class="form-check-input" type="checkbox" name="adr_certified" value="1" id="adr_certified"
                           <?= !empty($entity->adr_certified) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="adr_certified"><?= __('Certyfikat ADR') ?></label>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1"><?= __('Ważność ADR') ?></label>
                    <input name="adr_expiry" type="date" class="form-control" value="<?= $entity->adr_expiry ? $entity->adr_expiry->format('Y-m-d') : '' ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1"><?= __('Języki') ?></label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($languageOptions as $code => $name): ?>
                            <div class="form-check">
                                <input class="form-check-input lang-opt" type="checkbox" value="<?= h($code) ?>" id="lang_<?= h($code) ?>"
                                       <?= in_array($code, $currentLangs, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="lang_<?= h($code) ?>"><?= h($name) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="languages" id="languages_input" value="<?= h($entity->languages ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2"><strong><i class="ri-money-pound-circle-line me-1 text-warning"></i><?= __('Stawki') ?></strong></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Stawka godzinowa (PLN)') ?></label>
                        <input name="hourly_rate_pln" type="number" step="0.01" min="0" class="form-control"
                               value="<?= h($entity->hourly_rate_pln ?? '') ?>" placeholder="50.00">
                        <div class="form-text" style="font-size:.7rem"><?= __('Używane w kalkulatorze trasy zamiast pola "Stawka kierowcy".') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Dieta dzienna (PLN)') ?></label>
                        <input name="per_diem_pln" type="number" step="0.01" min="0" class="form-control"
                               value="<?= h($entity->per_diem_pln ?? '') ?>" placeholder="60.00">
                        <div class="form-text" style="font-size:.7rem"><?= __('Doliczana przy trasach >8h w delegacji') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1"><?= __('Stawka kilometrowa (PLN/km)') ?></label>
                        <input name="km_rate_pln" type="number" step="0.01" min="0" class="form-control"
                               value="<?= h($entity->km_rate_pln ?? '') ?>" placeholder="0.50">
                        <div class="form-text" style="font-size:.7rem"><?= __('Alternatywnie do stawki godzinowej') ?></div>
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
                          placeholder="<?= __('Dodatkowe informacje, preferencje, ograniczenia…') ?>"><?= h($entity->notes ?? '') ?></textarea>
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
// Zbieraj wybrane checkboxy kategorii / języków do CSV w ukrytym polu
(function () {
    function syncCsv(checkboxClass, hiddenId) {
        var inputs = document.querySelectorAll('.' + checkboxClass);
        function update() {
            var vals = Array.from(inputs).filter(function (i) { return i.checked; }).map(function (i) { return i.value; });
            document.getElementById(hiddenId).value = vals.join(',');
        }
        inputs.forEach(function (i) { i.addEventListener('change', update); });
        update();
    }
    syncCsv('license-cat', 'license_categories_input');
    syncCsv('lang-opt', 'languages_input');
})();
</script>

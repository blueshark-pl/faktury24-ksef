<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Lead $lead
 * @var iterable $users
 * @var bool $isEdit
 */
$this->assign('title', $isEdit ? __('Edytuj lead') : __('Nowy lead'));
$branches = [
    'road' => 'Drogowy',
    'road_reefer' => 'Drogowy chłodnia',
    'road_adr' => 'Drogowy ADR',
    'road_oversize' => 'Drogowy Oversize',
    'sea' => 'Morski',
    'rail' => 'Kolejowy',
    'air' => 'Lotniczy',
    'intermodal' => 'Intermodalny',
    'any' => 'Dowolna',
];
$stages = [
    'new' => 'Nowy lead', 'contact' => 'Kontakt', 'inquiry' => 'Zapytanie',
    'offer' => 'Oferta', 'order' => 'Zlecenie', 'lost' => 'Utracone',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-<?= $isEdit ? 'edit' : 'add' ?>-line me-1"></i>
        <?= $isEdit ? __('Edytuj lead') : __('Nowy lead') ?>
    </h4>
    <a href="<?= $this->Url->build($isEdit ? ['action' => 'view', $lead->id] : ['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line"></i> <?= __('Anuluj') ?>
    </a>
</div>

<?php
// FALA 21 fix: explicit URL + method zeby ominac auto-generacja z Form->create(entity)
// ktora moze isc na PUT / fallback /leads/edit zamiast naszej trasy /crm/edytuj
$formUrl = $isEdit
    ? ['action' => 'edit', $lead->id]
    : ['action' => 'add'];
?>
<?= $this->Form->create($lead, ['type' => 'post', 'url' => $formUrl]) ?>
<div class="row g-3">
    <!-- Firma -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="ri-building-line"></i> <?= __('Dane firmy') ?></h6>
                <div class="mb-2">
                    <label class="form-label small"><?= __('Nazwa firmy') ?> *</label>
                    <input name="company_name" required class="form-control" value="<?= h($lead->company_name) ?>">
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small">NIP / VAT</label>
                        <div class="input-group">
                            <input name="nip" id="lead-nip" class="form-control text-uppercase" value="<?= h($lead->nip) ?>">
                            <button type="button" class="btn btn-outline-primary" id="btn-lead-gus" title="<?= __('Pobierz z GUS i sprawdź duplikaty') ?>">
                                <i class="ri-download-line"></i> GUS
                            </button>
                        </div>
                        <div id="lead-gus-msg" class="small mt-1"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small"><?= __('Kraj') ?></label>
                        <input name="country_code" maxlength="2" class="form-control text-uppercase" value="<?= h($lead->country_code) ?>" placeholder="PL">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small"><?= __('Kod') ?></label>
                        <input name="postal_code" class="form-control" value="<?= h($lead->postal_code) ?>">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Miasto') ?></label>
                        <input name="city" class="form-control" value="<?= h($lead->city) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Ulica') ?></label>
                        <input name="street" class="form-control" value="<?= h($lead->street) ?>">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Branża klienta') ?></label>
                        <?php
                        $industries = [];
                        $vehicleTypes = [];
                        $selectedVehicleTypeIds = [];
                        try {
                            $conn = \Cake\Datasource\ConnectionManager::get('default');
                            $tables = $conn->getSchemaCollection()->listTables();
                            if (in_array('lead_industries', $tables, true)) {
                                $industries = $this->fetchTable('LeadIndustries')->find()
                                    ->where(['company_id' => $this->request->getAttribute('identity')?->get('company_id')])
                                    ->orderByAsc('sort_order')->orderByAsc('name')->all()->toArray();
                            }
                            if (in_array('lead_vehicle_types', $tables, true)) {
                                $vehicleTypes = $this->fetchTable('LeadVehicleTypes')->find()
                                    ->where(['company_id' => $this->request->getAttribute('identity')?->get('company_id')])
                                    ->orderByAsc('sort_order')->orderByAsc('name')->all()->toArray();
                                if ($isEdit) {
                                    $selectedVehicleTypeIds = array_map(fn($v) => (string)$v->id, $lead->lead_vehicle_types ?? []);
                                }
                            }
                        } catch (\Throwable $e) {}
                        ?>
                        <select name="industry_id" class="form-select">
                            <option value=""><?= __('— brak / nie wybrano —') ?></option>
                            <?php foreach ($industries as $ind): ?>
                                <option value="<?= h($ind->id) ?>" <?= ($lead->industry_id ?? '') === $ind->id ? 'selected' : '' ?>><?= h($ind->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">
                            <a href="<?= $this->Url->build(['controller' => 'LeadIndustries', 'action' => 'index']) ?>" target="_blank"><i class="ri-external-link-line"></i> <?= __('Zarządzaj słownikiem branż') ?></a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Rodzaje taboru (multi)') ?></label>
                        <div style="max-height: 120px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 6px; padding: 6px;">
                            <?php if (empty($vehicleTypes)): ?>
                                <span class="text-muted small"><?= __('Brak. Dodaj w słowniku.') ?></span>
                            <?php else: foreach ($vehicleTypes as $vt): $checked = in_array((string)$vt->id, $selectedVehicleTypeIds, true); ?>
                                <label class="d-block small mb-1" style="cursor: pointer;">
                                    <input type="checkbox" name="vehicle_type_ids[]" value="<?= h($vt->id) ?>" <?= $checked ? 'checked' : '' ?>>
                                    <?= h($vt->name) ?>
                                </label>
                            <?php endforeach; endif; ?>
                        </div>
                        <div class="form-text small">
                            <a href="<?= $this->Url->build(['controller' => 'LeadVehicleTypes', 'action' => 'index']) ?>" target="_blank"><i class="ri-external-link-line"></i> <?= __('Zarządzaj rodzajami taboru') ?></a>
                        </div>
                    </div>
                </div>
                <!-- DEPRECATED branch_type - zachowane jako hidden dla backward compat -->
                <input type="hidden" name="branch_type" value="<?= h($lead->branch_type ?? '') ?>">
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Źródło') ?></label>
                        <select name="source" class="form-select">
                            <option value="manual"          <?= ($lead->source ?? 'manual') === 'manual' ? 'selected' : '' ?>><?= __('Ręczny') ?></option>
                            <option value="website"         <?= $lead->source === 'website' ? 'selected' : '' ?>><?= __('WWW') ?></option>
                            <option value="recommendation"  <?= $lead->source === 'recommendation' ? 'selected' : '' ?>><?= __('Polecenie') ?></option>
                            <option value="cold_call"       <?= $lead->source === 'cold_call' ? 'selected' : '' ?>><?= __('Cold call') ?></option>
                            <option value="import_csv"      <?= $lead->source === 'import_csv' ? 'selected' : '' ?>><?= __('Import CSV') ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kontakt -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="ri-user-line"></i> <?= __('Osoba kontaktowa') ?></h6>
                <div class="row g-2">
                    <div class="col-md-7">
                        <label class="form-label small"><?= __('Imię i nazwisko') ?></label>
                        <input name="contact_person" class="form-control" value="<?= h($lead->contact_person) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small"><?= __('Stanowisko') ?></label>
                        <input name="contact_role" class="form-control" value="<?= h($lead->contact_role) ?>">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Telefon') ?></label>
                        <input name="phone" class="form-control" value="<?= h($lead->phone) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Email</label>
                        <input name="email" type="email" class="form-control" value="<?= h($lead->email) ?>">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small">
                            <i class="ri-linkedin-box-fill" style="color:#0a66c2;"></i> LinkedIn osoby
                        </label>
                        <input name="linkedin_url" type="url" class="form-control" value="<?= h($lead->linkedin_url ?? '') ?>"
                               placeholder="https://linkedin.com/in/jan-kowalski">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">
                            <i class="ri-linkedin-box-fill" style="color:#0a66c2;"></i> LinkedIn firmy
                        </label>
                        <input name="linkedin_company_url" type="url" class="form-control" value="<?= h($lead->linkedin_company_url ?? '') ?>"
                               placeholder="https://linkedin.com/company/silesian-flour">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Preferowany kanał') ?></label>
                        <select name="contact_channel" class="form-select">
                            <option value=""><?= __('— dowolny —') ?></option>
                            <option value="phone"   <?= $lead->contact_channel === 'phone' ? 'selected' : '' ?>><?= __('Telefon') ?></option>
                            <option value="email"   <?= $lead->contact_channel === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="meeting" <?= $lead->contact_channel === 'meeting' ? 'selected' : '' ?>><?= __('Spotkanie') ?></option>
                            <option value="any"     <?= $lead->contact_channel === 'any' ? 'selected' : '' ?>><?= __('Dowolny') ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Opiekun') ?></label>
                        <select name="assigned_to_user_id" class="form-select">
                            <option value=""><?= __('— nieprzypisany —') ?></option>
                            <?php foreach ($users as $u):
                                $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->email ?? $u->id);
                            ?>
                                <option value="<?= h($u->id) ?>" <?= $lead->assigned_to_user_id === $u->id ? 'selected' : '' ?>><?= h($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pipeline + wartość -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="ri-funnels-line"></i> <?= __('Pipeline + wartość') ?></h6>
                <!-- FALA 21: Multi-pipeline selektor -->
                <div class="row g-2 mb-2">
                    <div class="col-md-12">
                        <label class="form-label small"><?= __('Typ pipeline') ?></label>
                        <select name="pipeline_type" id="lead-pipeline-type" class="form-select">
                            <?php foreach (\App\Model\Table\LeadsTable::PIPELINE_LABELS as $pt => $lbl): ?>
                                <option value="<?= h($pt) ?>" <?= ($lead->pipeline_type ?? 'spot') === $pt ? 'selected' : '' ?>><?= h($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">
                            <?= __('Long-term: kontrakty miesięczne · Spot: pojedyncze zlecenia · Recurring: klient regularny') ?>
                        </div>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Etap') ?></label>
                        <select name="stage" id="lead-stage-select" class="form-select">
                            <?php
                            $currentPipeline = $lead->pipeline_type ?? 'spot';
                            $availableStages = \App\Model\Table\LeadsTable::stagesForPipeline($currentPipeline);
                            $stageHumanLabels = [
                                'new' => 'Nowy', 'contact' => 'Kontakt', 'inquiry' => 'Zapytanie',
                                'offer' => 'Oferta', 'order' => 'Zlecenie', 'lost' => 'Utracone',
                                'qualification' => 'Kwalifikacja', 'proposal' => 'Propozycja',
                                'negotiation' => 'Negocjacje', 'contract' => 'Kontrakt', 'active' => 'Aktywny',
                                'prospect' => 'Prospekt', 'trial' => 'Trial', 'churned' => 'Churned',
                            ];
                            foreach ($availableStages as $k):
                                $v = $stageHumanLabels[$k] ?? $k;
                            ?>
                                <option value="<?= h($k) ?>" <?= ($lead->stage ?? $availableStages[0]) === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Skuteczność %') ?></label>
                        <input name="probability" type="number" min="0" max="100" class="form-control"
                               value="<?= h($lead->probability ?? 10) ?>">
                        <div class="form-text small"><?= __('Auto-preset przy zmianie etapu (10/25/50/75/100)') ?></div>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Szacowana wartość') ?></label>
                        <div class="input-group">
                            <input name="value_pln" type="number" step="0.01" class="form-control" value="<?= h($lead->value_pln) ?>">
                            <span class="input-group-text">zł</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Waluta') ?></label>
                        <input name="currency" maxlength="3" class="form-control text-uppercase" value="<?= h($lead->currency ?? 'PLN') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notatka + next action -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="ri-sticky-note-line"></i> <?= __('Notatka + follow-up') ?></h6>
                <div class="mb-2">
                    <label class="form-label small"><?= __('Notatka wewnętrzna') ?></label>
                    <textarea name="note" class="form-control" rows="3"><?= h($lead->note) ?></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label small"><?= __('Termin następnej akcji') ?></label>
                        <input name="next_action_at" type="datetime-local" class="form-control"
                               value="<?= $lead->next_action_at ? $lead->next_action_at->format('Y-m-d\\TH:i') : '' ?>">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small"><?= __('Opis akcji') ?></label>
                        <input name="next_action_description" class="form-control" value="<?= h($lead->next_action_description) ?>"
                               placeholder="<?= __('np. Zadzwoń do Daniela ws. oferty') ?>">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Odłóż do (snooze)') ?></label>
                        <input name="snooze_until" type="date" class="form-control"
                               value="<?= $lead->snooze_until ? $lead->snooze_until->format('Y-m-d') : '' ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kanban_pinned" value="1"
                                   id="kp" <?= $lead->kanban_pinned ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="kp"><?= __('Przypięty na górze Kanban') ?></label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 text-end">
        <a href="<?= $this->Url->build($isEdit ? ['action' => 'view', $lead->id] : ['action' => 'index']) ?>" class="btn btn-outline-secondary">
            <?= __('Anuluj') ?>
        </a>
        <button type="submit" class="btn btn-success">
            <i class="ri-save-line"></i> <?= __('Zapisz') ?>
        </button>
    </div>
</div>
<?= $this->Form->end() ?>

<script>
(function() {
    var csrf = '<?= $this->request->getAttribute('csrfToken') ?>';
    var $btn = document.getElementById('btn-lead-gus');
    var $nip = document.getElementById('lead-nip');
    var $msg = document.getElementById('lead-gus-msg');
    if (!$btn || !$nip) return;

    // Automatyczny dedup-check po blur (bez pobierania GUS)
    $nip.addEventListener('blur', function() {
        var digits = ($nip.value || '').replace(/[^A-Z0-9]/gi, '').toUpperCase();
        if (digits.length < 10) return;
        var fd = new FormData();
        fd.append('nip', digits);
        fd.append('_csrfToken', csrf);
        // Ciche wywolanie tylko dla dedup-check
        fetch('<?= $this->Url->build(['action' => 'gusLookupJson']) ?>', {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(j) {
            if (j && j.duplicate) {
                var url = '<?= $this->Url->build(['action' => 'view']) ?>/' + j.duplicate.id;
                $msg.innerHTML = '<div class="alert alert-warning py-1 mb-0"><i class="ri-alert-line"></i> '
                    + '<?= __("Istnieje już lead z tym NIP") ?>: <a href="' + url + '"><strong>'
                    + (j.duplicate.company_name || '') + '</strong></a> (etap: ' + (j.duplicate.stage || '?') + ')</div>';
            }
        })
        .catch(function() {});
    });

    // GUS lookup po kliknieciu
    $btn.addEventListener('click', function() {
        var digits = ($nip.value || '').replace(/[^A-Z0-9]/gi, '').toUpperCase();
        if (digits.length !== 10) {
            $msg.innerHTML = '<span class="text-danger"><?= __("NIP musi mieć 10 cyfr (PL)") ?></span>';
            return;
        }
        $msg.innerHTML = '<span class="text-muted"><i class="ri-loader-4-line"></i> <?= __("Pobieram z GUS…") ?></span>';
        var fd = new FormData();
        fd.append('nip', digits);
        fd.append('_csrfToken', csrf);
        fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'gusLookup']) ?>', {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) {
                $msg.innerHTML = '<span class="text-danger">' + (j.message || '<?= __("Błąd pobierania z GUS") ?>') + '</span>';
                return;
            }
            var c = j.contractor || {};
            var form = document.querySelector('form');
            if (c.name && form.elements.company_name && !form.elements.company_name.value) form.elements.company_name.value = c.name;
            if (c.street && form.elements.street) form.elements.street.value = c.street;
            if (c.zip && form.elements.postal_code) form.elements.postal_code.value = c.zip;
            if (c.city && form.elements.city) form.elements.city.value = c.city;
            if (form.elements.country_code && !form.elements.country_code.value) form.elements.country_code.value = 'PL';
            var vatBadge = j.vat && j.vat.statusVat === 'Czynny'
                ? '<span class="badge bg-success-subtle text-success ms-1">VAT czynny</span>'
                : (j.vat && j.vat.statusVat ? '<span class="badge bg-warning-subtle text-warning ms-1">VAT ' + j.vat.statusVat + '</span>' : '');
            $msg.innerHTML = '<span class="text-success"><i class="ri-check-line"></i> <?= __("Uzupełnione z GUS") ?></span> ' + vatBadge;
        })
        .catch(function(e) {
            $msg.innerHTML = '<span class="text-danger"><?= __("Błąd sieciowy") ?>: ' + e.message + '</span>';
        });
    });
})();
</script>

<script>
// FALA 21: Dynamiczne stages per pipeline_type (bez reload strony)
(function() {
    var pipelineStages = <?= json_encode(\App\Model\Table\LeadsTable::PIPELINE_STAGES) ?>;
    var stageLabels = {
        'new':'Nowy','contact':'Kontakt','inquiry':'Zapytanie','offer':'Oferta','order':'Zlecenie','lost':'Utracone',
        'qualification':'Kwalifikacja','proposal':'Propozycja','negotiation':'Negocjacje','contract':'Kontrakt','active':'Aktywny',
        'prospect':'Prospekt','trial':'Trial','churned':'Churned'
    };
    var $pt = document.getElementById('lead-pipeline-type');
    var $st = document.getElementById('lead-stage-select');
    if (!$pt || !$st) return;
    $pt.addEventListener('change', function() {
        var stages = pipelineStages[$pt.value] || [];
        var prev = $st.value;
        $st.innerHTML = '';
        stages.forEach(function(s) {
            var opt = document.createElement('option');
            opt.value = s; opt.textContent = stageLabels[s] || s;
            if (s === prev) opt.selected = true;
            $st.appendChild(opt);
        });
        // Jesli poprzedni stage nie istnieje w nowym pipeline - wybierz pierwszy
        if (stages.indexOf(prev) === -1 && stages.length) $st.value = stages[0];
    });
})();
</script>

<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Lead> $leads
 * @var array $stats
 * @var string $q
 * @var string $stage
 * @var string $branch
 * @var string $country
 * @var bool   $mine
 * @var int    $totalCount
 * @var int    $avgProb
 * @var string $sortCol
 * @var string $sortDir
 * @var iterable $users
 */
$this->assign('title', __('CRM – Leady'));
$currentQuery = $this->request->getQuery();
$sortLink = function($col, $label) use ($currentQuery, $sortCol, $sortDir) {
    $newDir = ($sortCol === $col && $sortDir === 'asc') ? 'desc' : 'asc';
    $arrow = $sortCol !== $col ? '' : ($sortDir === 'asc' ? ' <i class="ri-arrow-up-s-line"></i>' : ' <i class="ri-arrow-down-s-line"></i>');
    $q = array_merge($currentQuery, ['sort' => $col, 'dir' => $newDir]);
    return '<a href="?' . http_build_query($q) . '" class="text-decoration-none text-dark">' . h($label) . $arrow . '</a>';
};

$stageLabels = [
    'new'     => __('Nowy lead'),
    'contact' => __('Kontakt'),
    'inquiry' => __('Zapytanie'),
    'offer'   => __('Oferta'),
    'order'   => __('Zlecenie'),
    'lost'    => __('Utracone'),
];
$stageBg = [
    'new' => 'bg-primary', 'contact' => 'bg-info', 'inquiry' => 'bg-warning text-dark',
    'offer' => 'bg-purple', 'order' => 'bg-success', 'lost' => 'bg-secondary',
];
?>
<style>
.bg-purple { background-color: #7c3aed !important; color: #fff; }
.crm-avatar { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center;
    justify-content: center; font-size: 11px; font-weight: 700; color: #fff; }
.crm-flag { display: inline-block; width: 22px; height: 15px; border-radius: 2px; margin-right: 6px;
    vertical-align: middle; box-shadow: 0 0 0 1px rgba(0,0,0,0.08); }
.crm-flag-pl { background: linear-gradient(180deg, #fff 50%, #DC143C 50%); }
.crm-flag-de { background: linear-gradient(180deg, #000 33%, #DD0000 33%, #DD0000 66%, #FFCE00 66%); }
.crm-flag-it { background: linear-gradient(90deg, #009246 33%, #fff 33%, #fff 66%, #CE2B37 66%); }
.crm-flag-be { background: linear-gradient(90deg, #000 33%, #FDDA24 33%, #FDDA24 66%, #EF3340 66%); }
.crm-flag-nl { background: linear-gradient(180deg, #AE1C28 33%, #fff 33%, #fff 66%, #21468B 66%); }
.crm-flag-cz { background: linear-gradient(180deg, #fff 50%, #D7141A 50%); }
.crm-flag-fr { background: linear-gradient(90deg, #002395 33%, #fff 33%, #fff 66%, #ED2939 66%); }
.crm-flag-at { background: linear-gradient(180deg, #ED2939 33%, #fff 33%, #fff 66%, #ED2939 66%); }
.crm-stage-cells { display: inline-flex; gap: 4px; }
.crm-stage-cells .c { width: 18px; height: 18px; border-radius: 4px; background: #f1f3f5;
    display: inline-flex; align-items: center; justify-content: center; font-size: 10px; color: #adb5bd; }
.crm-stage-cells .c.on { background: rgba(148,200,31,0.2); color: #6b8f14; font-weight: 700; }
.crm-progress { width: 60px; height: 5px; background: #f1f3f5; border-radius: 3px; overflow: hidden; display: inline-block; vertical-align: middle; }
.crm-progress > div { height: 100%; }
.crm-kpi { border-radius: 12px; padding: 12px 16px; background: #fff; }
.crm-kpi .n { font-size: 22px; font-weight: 700; }
.crm-kpi .l { font-size: 11px; text-transform: uppercase; letter-spacing: 0.4px; color: #6c757d; font-weight: 600; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="mb-1 fw-semibold"><i class="ri-user-search-line me-1"></i><?= __('CRM – Leady') ?></h4>
        <div class="text-muted small">
            <?= sprintf(__('%d leadów · %d aktywnych · średnia skuteczność %d%%'),
                $totalCount,
                $totalCount - ($stats['lost']['count'] ?? 0),
                $avgProb) ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= $this->Url->build(['action' => 'dashboard']) ?>" class="btn btn-sm btn-outline-info">
            <i class="ri-dashboard-3-line me-1"></i><?= __('Dashboard') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'myTasks']) ?>" class="btn btn-sm btn-outline-warning">
            <i class="ri-checkbox-line me-1"></i><?= __('Moje zadania') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'kanban']) ?>" class="btn btn-sm btn-outline-primary">
            <i class="ri-layout-column-line me-1"></i><?= __('Kanban') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'importCsv']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-upload-cloud-2-line me-1"></i><?= __('Import CSV') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-success">
            <i class="ri-add-line me-1"></i><?= __('Nowy lead') ?>
        </a>
    </div>
</div>

<!-- KPI pipeline -->
<div class="row g-2 mb-3">
    <?php foreach (['new', 'contact', 'inquiry', 'offer', 'order'] as $s): ?>
        <div class="col">
            <div class="crm-kpi border-start border-3"
                 style="border-color: <?= ['new'=>'#0d6efd','contact'=>'#0dcaf0','inquiry'=>'#f59e0b','offer'=>'#7c3aed','order'=>'#198754'][$s] ?> !important;">
                <div class="l"><?= h($stageLabels[$s]) ?></div>
                <div class="n"><?= (int)($stats[$s]['count'] ?? 0) ?></div>
                <div class="text-muted small"><?= number_format((float)($stats[$s]['value_pln'] ?? 0), 0, ',', ' ') ?> zł</div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Saved filters (localStorage) -->
<div class="mb-2 small d-flex align-items-center gap-2 flex-wrap">
    <span class="text-muted"><i class="ri-bookmark-line"></i> <?= __('Zapisane widoki:') ?></span>
    <div id="saved-views" class="d-flex gap-1 flex-wrap"></div>
    <button type="button" class="btn btn-sm btn-link p-0" id="save-view-btn"><i class="ri-add-line"></i> <?= __('Zapisz aktualny widok') ?></button>
</div>

<!-- Filtry -->
<div class="card mb-3">
    <div class="card-body py-2">
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'row g-2 align-items-center']) ?>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                    <input type="text" name="q" class="form-control" value="<?= h($q) ?>"
                           placeholder="<?= __('Firma, NIP, kontakt, email, miasto…') ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="stage" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie etapy') ?></option>
                    <?php foreach ($stageLabels as $sk => $sv): ?>
                        <option value="<?= h($sk) ?>" <?= $stage === $sk ? 'selected' : '' ?>><?= h($sv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="branch" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie gałęzie') ?></option>
                    <option value="road"          <?= $branch==='road' ? 'selected':'' ?>><?= __('Drogowy') ?></option>
                    <option value="road_reefer"   <?= $branch==='road_reefer' ? 'selected':'' ?>><?= __('Drogowy chłodnia') ?></option>
                    <option value="road_adr"      <?= $branch==='road_adr' ? 'selected':'' ?>><?= __('Drogowy ADR') ?></option>
                    <option value="road_oversize" <?= $branch==='road_oversize' ? 'selected':'' ?>><?= __('Drogowy Oversize') ?></option>
                    <option value="sea"           <?= $branch==='sea' ? 'selected':'' ?>><?= __('Morski') ?></option>
                    <option value="rail"          <?= $branch==='rail' ? 'selected':'' ?>><?= __('Kolejowy') ?></option>
                    <option value="air"           <?= $branch==='air' ? 'selected':'' ?>><?= __('Lotniczy') ?></option>
                    <option value="intermodal"    <?= $branch==='intermodal' ? 'selected':'' ?>><?= __('Intermodalny') ?></option>
                </select>
            </div>
            <div class="col-md-1">
                <input type="text" name="country" class="form-control form-control-sm text-uppercase"
                       maxlength="2" value="<?= h($country) ?>" placeholder="<?= __('Kraj') ?>">
            </div>
            <div class="col-md-1">
                <input type="text" name="postal" class="form-control form-control-sm"
                       maxlength="10" value="<?= h($postal ?? '') ?>" placeholder="<?= __('Kod poczt.') ?>">
            </div>
            <?php if (!empty($industriesForFilter)): ?>
            <div class="col-md-2">
                <select name="industry" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie branże') ?></option>
                    <?php foreach ($industriesForFilter as $ind): ?>
                        <option value="<?= h($ind->id) ?>" <?= ($industryId ?? '') === $ind->id ? 'selected' : '' ?>><?= h($ind->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if (!empty($vehicleTypesForFilter)): ?>
            <div class="col-md-2">
                <select name="vehicle_type" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie tabor') ?></option>
                    <?php foreach ($vehicleTypesForFilter as $vt): ?>
                        <option value="<?= h($vt->id) ?>" <?= ($vehicleTypeId ?? '') === $vt->id ? 'selected' : '' ?>><?= h($vt->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-1">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="mine" value="1" id="fm" <?= $mine ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="fm"><?= __('Moje') ?></label>
                </div>
            </div>
            <div class="col-md-2">
                <?php $af = $archivedFilter ?? 'hide'; ?>
                <select name="archived" class="form-select form-select-sm">
                    <option value="hide" <?= $af === 'hide' ? 'selected' : '' ?>><?= __('Archiwum: ukryj') ?></option>
                    <option value="show" <?= $af === 'show' ? 'selected' : '' ?>><?= __('Archiwum: pokaż') ?></option>
                    <option value="only" <?= $af === 'only' ? 'selected' : '' ?>><?= __('Archiwum: tylko') ?></option>
                </select>
            </div>
            <div class="col-md-2 text-end">
                <button class="btn btn-sm btn-primary"><?= __('Filtruj') ?></button>
                <?php if ($q || $stage || $branch || $country || $mine || ($af ?? 'hide') !== 'hide'): ?>
                    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary"><?= __('Wyczyść') ?></a>
                <?php endif; ?>
            </div>
        <?= $this->Form->end() ?>
    </div>
</div>

<?= $this->Form->create(null, ['url' => ['action' => 'bulk'], 'id' => 'bulk-form']) ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th style="width:32px;"><input type="checkbox" id="chk-all" class="form-check-input"></th>
                    <th><?= $sortLink('company_name', __('Firma')) ?></th>
                    <th><?= $sortLink('city', __('Kraj / Miasto')) ?></th>
                    <th><?= $sortLink('postal_code', __('Kod poczt.')) ?></th>
                    <th><?= __('Osoba') ?></th>
                    <th><?= __('Kontakt') ?></th>
                    <th><?= __('Branża') ?></th>
                    <th><?= __('Tabor') ?></th>
                    <th class="text-center" title="Kontakt · Zapytanie · Oferta · Zlecenie">K·Z·O·Zl</th>
                    <th><?= $sortLink('stage', __('Etap')) ?></th>
                    <th><?= __('Opiekun') ?></th>
                    <th class="text-end"><?= $sortLink('value_pln', __('Wartość')) ?></th>
                    <th><?= $sortLink('probability', __('Skut.')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($leads) === 0): ?>
                    <tr><td colspan="14" class="text-center text-muted py-4"><?= __('Brak leadów spełniających filtry.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead):
                        $flagCls = 'crm-flag crm-flag-' . strtolower((string)$lead->country_code);
                        $prob = (int)$lead->probability;
                        $probColor = $prob >= 75 ? '#198754' : ($prob >= 50 ? '#94C81F' : ($prob >= 25 ? '#f59e0b' : '#dc3545'));
                        $assignedName = trim(($lead->assigned_user?->first_name ?? '') . ' ' . ($lead->assigned_user?->last_name ?? ''));
                        $initials = strtoupper(mb_substr($lead->assigned_user?->first_name ?? '?', 0, 1)
                            . mb_substr($lead->assigned_user?->last_name ?? '', 0, 1));
                    ?>
                    <tr onclick="if(event.target.type!=='checkbox') location.href='<?= $this->Url->build(['action' => 'view', $lead->id]) ?>'" style="cursor:pointer;">
                        <td onclick="event.stopPropagation();">
                            <input type="checkbox" name="ids[]" value="<?= h($lead->id) ?>" class="form-check-input chk-row">
                        </td>
                        <td>
                            <div class="fw-semibold"><?= h($lead->company_name) ?></div>
                            <?php if ($lead->nip): ?>
                                <div class="text-muted small">NIP <?= h($lead->nip) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lead->country_code): ?>
                                <span class="<?= h($flagCls) ?>"></span>
                            <?php endif; ?>
                            <?= h(strtoupper((string)$lead->country_code)) ?>
                            <?php if ($lead->city): ?> · <?= h($lead->city) ?><?php endif; ?>
                        </td>
                        <td class="small"><?= h($lead->postal_code ?: '—') ?></td>
                        <td><?= h($lead->contact_person ?: '—') ?></td>
                        <td class="small text-muted">
                            <?php if ($lead->phone): ?><i class="ri-phone-line"></i> <?= h($lead->phone) ?><br><?php endif; ?>
                            <?php if ($lead->email): ?><i class="ri-mail-line"></i> <?= h($lead->email) ?><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($lead->lead_industry)): ?>
                                <span class="badge bg-info"><?= h($lead->lead_industry->name) ?></span>
                            <?php elseif ($lead->branch_type): ?>
                                <span class="badge bg-light text-dark border" title="Legacy branch_type"><?= h($lead->branch_type) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($lead->lead_vehicle_types)): foreach ($lead->lead_vehicle_types as $vt): ?>
                                <span class="badge bg-secondary" style="font-size: 10px;"><?= h($vt->name) ?></span>
                            <?php endforeach; else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="crm-stage-cells">
                                <span class="c <?= $lead->flag_contact ? 'on' : '' ?>" title="Kontakt">✓</span>
                                <span class="c <?= $lead->flag_inquiry ? 'on' : '' ?>" title="Zapytanie">✓</span>
                                <span class="c <?= $lead->flag_offer   ? 'on' : '' ?>" title="Oferta">✓</span>
                                <span class="c <?= $lead->flag_order   ? 'on' : '' ?>" title="Zlecenie">✓</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= $stageBg[$lead->stage] ?? 'bg-secondary' ?>">
                                <?= h($stageLabels[$lead->stage] ?? $lead->stage) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($assignedName):
                                $avatarUrl = trim((string)($lead->assigned_user->avatar ?? ''));
                                $avatarColors = ['#7c3aed', '#059669', '#dc2626', '#ea580c', '#2563eb', '#b45309', '#db2777', '#0891b2'];
                                $avatarBg = $avatarColors[crc32((string)($lead->assigned_user->email ?? $lead->assigned_user->id)) % count($avatarColors)];
                            ?>
                                <?php if ($avatarUrl !== ''): ?>
                                    <img src="<?= h($avatarUrl) ?>" alt="<?= h($assignedName) ?>"
                                         class="crm-avatar" style="object-fit: cover;"
                                         title="<?= h($assignedName) ?>"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                    <span class="crm-avatar" style="background: <?= $avatarBg ?>; display: none;" title="<?= h($assignedName) ?>"><?= h($initials) ?></span>
                                <?php else: ?>
                                    <span class="crm-avatar" style="background: <?= $avatarBg ?>;" title="<?= h($assignedName) ?>"><?= h($initials) ?></span>
                                <?php endif; ?>
                                <span class="small"><?= h($assignedName) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold">
                            <?php if ($lead->value_pln): ?>
                                <?= number_format((float)$lead->value_pln, 0, ',', ' ') ?> zł
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <div class="crm-progress">
                                    <div style="width:<?= $prob ?>%; background:<?= $probColor ?>;"></div>
                                </div>
                                <span class="small fw-semibold" style="color:<?= $probColor ?>;"><?= $prob ?>%</span>
                            </div>
                        </td>
                        <td class="text-end" onclick="event.stopPropagation();">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                    <i class="ri-more-2-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= $this->Url->build(['action' => 'view', $lead->id]) ?>">
                                        <i class="ri-eye-line me-1"></i><?= __('Zobacz') ?>
                                    </a></li>
                                    <li><a class="dropdown-item" href="<?= $this->Url->build(['action' => 'edit', $lead->id]) ?>">
                                        <i class="ri-pencil-line me-1"></i><?= __('Edytuj') ?>
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= $this->Form->postLink(
                                        '<i class="ri-delete-bin-line me-1"></i>' . __('Usuń'),
                                        ['action' => 'delete', $lead->id],
                                        ['escape' => false, 'class' => 'dropdown-item text-danger',
                                         'confirm' => __('Usunąć leada?')]
                                    ) ?></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bulk action bar (sticky, wyświetla się gdy zaznaczono >0) -->
<div id="bulk-bar" class="position-fixed" style="display:none; bottom:20px; left:50%; transform:translateX(-50%);
     background:#1a1d29; color:#fff; padding:10px 18px; border-radius:12px; box-shadow:0 10px 30px rgba(20,25,50,.3);
     z-index:1050; min-width:600px;">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <span><strong id="bulk-count">0</strong> <?= __('zaznaczonych') ?></span>
        <span style="opacity:0.4;">|</span>

        <div class="d-flex gap-1 align-items-center">
            <label class="small mb-0"><?= __('Etap:') ?></label>
            <select name="stage" class="form-select form-select-sm" style="width:auto; background:#2d3140; color:#fff; border-color:#4b5063;">
                <option value=""><?= __('— zmień na —') ?></option>
                <?php foreach ($stageLabels as $sk => $sv): ?>
                    <option value="<?= h($sk) ?>"><?= h($sv) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="bulk_action" value="change_stage" class="btn btn-sm btn-primary"
                    onclick="return confirm('<?= __('Zmienić etap dla zaznaczonych?') ?>');"><?= __('Zastosuj') ?></button>
        </div>

        <div class="d-flex gap-1 align-items-center">
            <label class="small mb-0"><?= __('Opiekun:') ?></label>
            <select name="assigned_to_user_id" class="form-select form-select-sm" style="width:auto; background:#2d3140; color:#fff; border-color:#4b5063;">
                <option value=""><?= __('— nieprzypisany —') ?></option>
                <?php foreach ($users as $u):
                    $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->email ?? $u->id);
                ?>
                    <option value="<?= h($u->id) ?>"><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="bulk_action" value="assign" class="btn btn-sm btn-primary"
                    onclick="return confirm('<?= __('Przypisać zaznaczone?') ?>');"><?= __('Przypisz') ?></button>
        </div>

        <button type="submit" name="bulk_action" value="delete" class="btn btn-sm btn-danger"
                onclick="return confirm('<?= __('USUNĄĆ zaznaczonych leadów? Nie da się cofnąć.') ?>');">
            <i class="ri-delete-bin-line"></i> <?= __('Usuń') ?>
        </button>

        <button type="button" class="btn btn-sm btn-link text-white ms-auto" id="bulk-clear">
            <?= __('Odznacz wszystko') ?>
        </button>
    </div>
</div>

<?= $this->Form->end() ?>

<script>
(function() {
    // === Bulk actions ===
    var $chkAll = document.getElementById('chk-all');
    var $rows = document.querySelectorAll('.chk-row');
    var $bar = document.getElementById('bulk-bar');
    var $count = document.getElementById('bulk-count');

    function updateBulkBar() {
        var checked = document.querySelectorAll('.chk-row:checked').length;
        $count.textContent = checked;
        $bar.style.display = checked > 0 ? 'block' : 'none';
    }

    if ($chkAll) {
        $chkAll.addEventListener('change', function() {
            $rows.forEach(function(c) { c.checked = $chkAll.checked; });
            updateBulkBar();
        });
    }
    $rows.forEach(function(c) {
        c.addEventListener('change', updateBulkBar);
    });
    document.getElementById('bulk-clear')?.addEventListener('click', function() {
        $rows.forEach(function(c) { c.checked = false; });
        if ($chkAll) $chkAll.checked = false;
        updateBulkBar();
    });

    // === Saved views (localStorage) ===
    var STORAGE_KEY = 'crm_leads_saved_views';
    var $savedViews = document.getElementById('saved-views');
    var $saveBtn = document.getElementById('save-view-btn');

    function getViews() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); }
        catch (e) { return []; }
    }
    function setViews(v) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(v)); }
        catch (e) {}
    }
    function currentQueryString() {
        var qs = window.location.search.substring(1);
        return qs;
    }
    function renderViews() {
        var views = getViews();
        $savedViews.innerHTML = '';
        views.forEach(function(v, idx) {
            var isActive = v.query === currentQueryString();
            var wrap = document.createElement('span');
            wrap.className = 'badge ' + (isActive ? 'bg-primary' : 'bg-light text-dark border') + ' d-inline-flex align-items-center gap-1';
            wrap.innerHTML = '<a href="?' + v.query + '" class="text-decoration-none ' + (isActive ? 'text-white' : 'text-dark') + '">' +
                escapeHtml(v.name) + '</a>' +
                '<a href="#" class="del-view text-danger" data-idx="' + idx + '" style="text-decoration:none;">×</a>';
            $savedViews.appendChild(wrap);
        });
        $savedViews.querySelectorAll('.del-view').forEach(function(a) {
            a.addEventListener('click', function(e) {
                e.preventDefault();
                var idx = parseInt(a.dataset.idx);
                var views = getViews();
                views.splice(idx, 1);
                setViews(views);
                renderViews();
            });
        });
    }
    function escapeHtml(s) {
        return String(s).replace(/[&<>"]/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
    }
    if ($saveBtn) {
        $saveBtn.addEventListener('click', function() {
            var name = prompt('<?= __('Nazwa widoku:') ?>');
            if (!name) return;
            var views = getViews();
            views.push({ name: name, query: currentQueryString() });
            setViews(views);
            renderViews();
        });
    }
    renderViews();
})();
</script>

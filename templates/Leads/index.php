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
 */
$this->assign('title', __('CRM – Leady'));

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
            <div class="col-md-2">
                <input type="text" name="country" class="form-control form-control-sm text-uppercase"
                       maxlength="2" value="<?= h($country) ?>" placeholder="<?= __('Kraj (PL, DE...)') ?>">
            </div>
            <div class="col-md-1">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="mine" value="1" id="fm" <?= $mine ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="fm"><?= __('Moje') ?></label>
                </div>
            </div>
            <div class="col-md-2 text-end">
                <button class="btn btn-sm btn-primary"><?= __('Filtruj') ?></button>
                <?php if ($q || $stage || $branch || $country || $mine): ?>
                    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary"><?= __('Wyczyść') ?></a>
                <?php endif; ?>
            </div>
        <?= $this->Form->end() ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th><?= __('Firma') ?></th>
                    <th><?= __('Kraj / Miasto') ?></th>
                    <th><?= __('Osoba') ?></th>
                    <th><?= __('Kontakt') ?></th>
                    <th><?= __('Gałąź') ?></th>
                    <th class="text-center" title="Kontakt · Zapytanie · Oferta · Zlecenie">K·Z·O·Zl</th>
                    <th><?= __('Etap') ?></th>
                    <th><?= __('Opiekun') ?></th>
                    <th class="text-end"><?= __('Wartość') ?></th>
                    <th><?= __('Skut.') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($leads) === 0): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4"><?= __('Brak leadów spełniających filtry.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead):
                        $flagCls = 'crm-flag crm-flag-' . strtolower((string)$lead->country_code);
                        $prob = (int)$lead->probability;
                        $probColor = $prob >= 75 ? '#198754' : ($prob >= 50 ? '#94C81F' : ($prob >= 25 ? '#f59e0b' : '#dc3545'));
                        $assignedName = trim(($lead->assigned_user?->first_name ?? '') . ' ' . ($lead->assigned_user?->last_name ?? ''));
                        $initials = strtoupper(mb_substr($lead->assigned_user?->first_name ?? '?', 0, 1)
                            . mb_substr($lead->assigned_user?->last_name ?? '', 0, 1));
                    ?>
                    <tr onclick="location.href='<?= $this->Url->build(['action' => 'view', $lead->id]) ?>'" style="cursor:pointer;">
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
                        <td><?= h($lead->contact_person ?: '—') ?></td>
                        <td class="small text-muted">
                            <?php if ($lead->phone): ?><i class="ri-phone-line"></i> <?= h($lead->phone) ?><br><?php endif; ?>
                            <?php if ($lead->email): ?><i class="ri-mail-line"></i> <?= h($lead->email) ?><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($lead->branch_type): ?>
                                <span class="badge bg-light text-dark border"><?= h($lead->branch_type) ?></span>
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
                            <?php if ($assignedName): ?>
                                <span class="crm-avatar" style="background:#94C81F;"><?= h($initials) ?></span>
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

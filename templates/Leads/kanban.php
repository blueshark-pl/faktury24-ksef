<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, iterable<\App\Model\Entity\Lead>> $columns
 * @var array $stats
 * @var string $pipelineType
 * @var array $pipelineCounts
 * @var array $displayStages
 * @var array $stageLabels
 * @var bool $onlyMine
 */
$this->assign('title', __('CRM – Kanban leadów') . ' (' . h(\App\Model\Table\LeadsTable::PIPELINE_LABELS[$pipelineType] ?? $pipelineType) . ')');

// stageColors: kolor + kolor-tla (bg light) + kolor-tekstu-badge per stage
$stageColors = [
    // spot legacy
    'new'           => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'label' => '#1e40af'],
    'contact'       => ['color' => '#0891b2', 'bg' => '#cffafe', 'label' => '#155e75'],
    'inquiry'       => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => '#b45309'],
    'offer'         => ['color' => '#8b5cf6', 'bg' => '#ede9fe', 'label' => '#6d28d9'],
    'order'         => ['color' => '#10b981', 'bg' => '#d1fae5', 'label' => '#065f46'],
    // long_term
    'qualification' => ['color' => '#0891b2', 'bg' => '#cffafe', 'label' => '#155e75'],
    'proposal'      => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => '#b45309'],
    'negotiation'   => ['color' => '#8b5cf6', 'bg' => '#ede9fe', 'label' => '#6d28d9'],
    'contract'      => ['color' => '#10b981', 'bg' => '#d1fae5', 'label' => '#065f46'],
    'active'        => ['color' => '#059669', 'bg' => '#a7f3d0', 'label' => '#064e3b'],
    // recurring
    'prospect'      => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'label' => '#1e40af'],
    'trial'         => ['color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => '#b45309'],
];
$colsCount = count($displayStages);
$pipelineIcons = [
    'long_term' => 'ri-building-line', 'spot' => 'ri-flashlight-line', 'recurring' => 'ri-repeat-line',
];
// Avatar helper - realny avatar z DB jesli jest, fallback do initials
$avatarColors = ['#7c3aed', '#059669', '#dc2626', '#ea580c', '#2563eb', '#b45309', '#db2777', '#0891b2'];
$avatarFor = function($user) use ($avatarColors) {
    if (!$user) return null;
    $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
    if ($name === '') $name = $user->email ?? '?';
    $initials = '';
    foreach (explode(' ', $name) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    $avatarUrl = trim((string)($user->avatar ?? ''));
    return [
        'url' => $avatarUrl,
        'initials' => $initials,
        'name' => $name,
        'bg' => $avatarColors[crc32($user->email ?? $user->id) % count($avatarColors)],
    ];
};
?>
<style>
/* Trello-style Kanban board */
body { background: #f4f5f7; }
.crm-kb { display: grid; grid-template-columns: repeat(<?= $colsCount ?>, minmax(275px, 1fr)); gap: 12px; overflow-x: auto; padding: 4px 4px 20px; }
.pipe-tabs { display: flex; gap: 4px; margin-bottom: 12px; border-bottom: 2px solid #dee2e6; }
.pipe-tab { padding: 8px 16px; border-radius: 6px 6px 0 0; text-decoration: none; color: #6c757d; font-weight: 600; font-size: 13px;
    border: 2px solid transparent; border-bottom: none; margin-bottom: -2px; display: flex; align-items: center; gap: 6px; }
.pipe-tab:hover { color: #212529; background: #f8f9fa; }
.pipe-tab.active { color: #94C81F; border-color: #dee2e6 #dee2e6 #fff; background: #fff; }
.pipe-tab .cnt { background: #f1f3f5; color: #495057; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.pipe-tab.active .cnt { background: rgba(148,200,31,0.15); color: #6b8f14; }

/* Kolumna - Trello style: szare tło, header sticky */
.crm-col { background: #ebecf0; border-radius: 12px; min-height: 400px; padding: 10px 8px; display: flex; flex-direction: column; }
.crm-col-hdr { display: flex; justify-content: space-between; align-items: center; padding: 6px 8px 12px;
    font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #172b4d; }
.crm-col-hdr .stage-name { display: flex; align-items: center; gap: 6px; }
.crm-col-hdr .stage-dot { width: 10px; height: 10px; border-radius: 3px; }
.crm-col-badge { background: rgba(9,30,66,0.08); padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;
    color: #172b4d; text-transform: none; letter-spacing: 0; }
.crm-cards { display: flex; flex-direction: column; gap: 8px; min-height: 200px; flex: 1; }

/* Karta - Trello style */
.crm-card { background: #fff; border-radius: 8px; padding: 8px 12px 10px; cursor: grab;
    box-shadow: 0 1px 0 rgba(9,30,66,0.25); transition: box-shadow 0.15s, transform 0.15s; overflow: hidden; }
.crm-card:hover { box-shadow: 0 4px 8px -2px rgba(9,30,66,0.25), 0 0 0 1px rgba(9,30,66,0.08); background: #fafbfc; }
.crm-card.sortable-ghost { opacity: 0.4; background: #dfe1e6; }

/* Kolorowy pasek label u góry karty (jak Trello labels) */
.crm-label-strip { display: flex; gap: 4px; margin: -8px -12px 6px; padding: 0; }
.crm-label { height: 8px; flex: 0 0 auto; min-width: 40px; border-radius: 0 0 4px 4px; margin-top: 0; }

.crm-card-title { font-weight: 600; font-size: 14px; color: #172b4d; line-height: 1.4; word-break: break-word;
    margin-bottom: 4px; }
.crm-card-title .crm-pin { color: #f59e0b; font-size: 13px; }

/* Meta chips */
.crm-card-meta { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 8px; align-items: center; }
.crm-chip { font-size: 11px; padding: 3px 8px; border-radius: 4px; background: #f4f5f7; color: #5e6c84; font-weight: 500;
    display: inline-flex; align-items: center; gap: 3px; }
.crm-chip i { font-size: 12px; }
.crm-chip.danger { background: #ffebe6; color: #bf2600; }
.crm-chip.warning { background: #fffae6; color: #ff8b00; }
.crm-chip.success { background: #e3fcef; color: #006644; }
.crm-chip.pipe-value { background: rgba(148,200,31,0.15); color: #5e7d0f; font-weight: 700; }

/* Avatar bottom-right jak Trello */
.crm-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
.crm-avatar { width: 28px; height: 28px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; letter-spacing: 0.3px; flex-shrink: 0;
    box-shadow: 0 0 0 2px #fff, 0 1px 2px rgba(0,0,0,0.15); }
.crm-avatar.unassigned { background: #dfe1e6; color: #a5adba; }
.crm-avatar.mine { box-shadow: 0 0 0 2px #94C81F, 0 1px 2px rgba(0,0,0,0.15); }

/* Location + kontakt */
.crm-loc, .crm-contact { color: #5e6c84; font-size: 11px; margin-top: 2px; }
.crm-loc i, .crm-contact i { color: #97a0af; }

/* Filter toolbar */
.crm-toolbar { display: flex; gap: 6px; align-items: center; margin-bottom: 4px; }
.crm-mine-btn { padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none;
    border: 1px solid #dfe1e6; color: #5e6c84; background: #fff; display: inline-flex; align-items: center; gap: 4px; }
.crm-mine-btn:hover { background: #f4f5f7; color: #172b4d; }
.crm-mine-btn.active { background: #94C81F; color: #fff; border-color: #94C81F; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="ri-layout-column-line me-1"></i><?= __('Kanban leadów') ?></h4>
        <div class="text-muted small">
            <?= sprintf(__('Łącznie: %d aktywnych, wartość pipeline: %s zł'),
                array_sum(array_map(fn($c) => count($c), $columns)),
                number_format(array_sum(array_map(fn($s) => $s['value_pln'], $stats)) - ($stats['lost']['value_pln'] ?? 0), 0, ',', ' ')
            ) ?>
        </div>
    </div>
    <div class="crm-toolbar">
        <?php
        $mineParams = ['pipeline' => $pipelineType];
        if (!$onlyMine) $mineParams['mine'] = 1;
        ?>
        <a href="<?= $this->Url->build(['action' => 'kanban', '?' => $mineParams]) ?>"
           class="crm-mine-btn <?= $onlyMine ? 'active' : '' ?>">
            <i class="ri-user-star-line"></i>
            <?= $onlyMine ? __('Widok: Moje') : __('Tylko moje') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="crm-mine-btn">
            <i class="ri-table-line"></i> <?= __('Tabela') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-success">
            <i class="ri-add-line"></i> <?= __('Nowy lead') ?>
        </a>
    </div>
</div>

<!-- FALA 21: Pipeline tabs -->
<div class="pipe-tabs">
    <?php foreach (\App\Model\Table\LeadsTable::PIPELINE_TYPES as $pt): ?>
        <a href="<?= $this->Url->build(['action' => 'kanban', '?' => ['pipeline' => $pt]]) ?>"
           class="pipe-tab <?= $pt === $pipelineType ? 'active' : '' ?>">
            <i class="<?= h($pipelineIcons[$pt] ?? 'ri-flow-chart') ?>"></i>
            <?= h(\App\Model\Table\LeadsTable::PIPELINE_LABELS[$pt] ?? $pt) ?>
            <span class="cnt"><?= (int)($pipelineCounts[$pt] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php
$identity = $this->request->getAttribute('identity');
$myUserId = $identity?->get('id');
?>
<div class="crm-kb">
    <?php foreach ($columns as $stage => $items):
        $count = count($items);
        $sum = 0;
        foreach ($items as $it) { $sum += (float)$it->value_pln; }
        $sc = $stageColors[$stage] ?? ['color' => '#5e6c84', 'bg' => '#f4f5f7', 'label' => '#5e6c84'];
    ?>
    <div class="crm-col" data-stage="<?= h($stage) ?>">
        <div class="crm-col-hdr">
            <div class="stage-name">
                <span class="stage-dot" style="background: <?= $sc['color'] ?>;"></span>
                <span class="crm-col-title" style="color: <?= $sc['label'] ?>;"><?= h($stageLabels[$stage]) ?></span>
            </div>
            <span class="crm-col-badge"><?= $count ?><?php if ($sum > 0): ?> · <?= number_format($sum, 0, ',', ' ') ?> zł<?php endif; ?></span>
        </div>
        <div class="crm-cards" data-column="<?= h($stage) ?>">
            <?php foreach ($items as $lead):
                $u = $lead->assigned_user ?? null;
                $avatar = $avatarFor($u);
                $isMine = $u && ((string)$u->id === (string)$myUserId);
                $days = $lead->getDaysInStage();
                // Labels u góry karty (Trello style)
                $labels = [];
                $labels[] = ['color' => $sc['color']]; // stage color
                if ($lead->kanban_pinned) $labels[] = ['color' => '#f59e0b']; // pinned
                if ($days !== null && $days > 14) $labels[] = ['color' => '#dc2626']; // stale
                elseif ($days !== null && $days > 7) $labels[] = ['color' => '#f59e0b'];
                if ($lead->value_pln && (float)$lead->value_pln >= 10000) $labels[] = ['color' => '#10b981']; // hi-value
            ?>
            <div class="crm-card" data-id="<?= h($lead->id) ?>"
                 onclick="if(!event.target.closest('a,button')) window.openLeadPeek('<?= h($lead->id) ?>');">
                <?php
                // FALA extras: user labels z lead_labels overrideuje auto-labels systemowe
                $userLabels = $lead->lead_labels ?? [];
                ?>
                <?php if (!empty($userLabels)): ?>
                <div class="crm-label-strip" style="gap: 4px; padding: 0 0 6px; margin: -8px -12px 0;">
                    <?php foreach ($userLabels as $ul): ?>
                        <span style="background: <?= h($ul->color) ?>; color: #fff; font-size: 10px; font-weight: 700;
                                     padding: 2px 8px; border-radius: 3px; margin: 0 0 4px 4px; display: inline-block;"
                              title="<?= h($ul->name) ?>"><?= h($ul->name) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php elseif (!empty($labels)): ?>
                <div class="crm-label-strip">
                    <?php foreach ($labels as $lbl): ?>
                        <span class="crm-label" style="background: <?= $lbl['color'] ?>;"></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="crm-card-title">
                    <?php if ($lead->kanban_pinned): ?><i class="ri-pushpin-fill crm-pin"></i> <?php endif; ?>
                    <?= h($lead->company_name) ?>
                </div>

                <?php if ($lead->country_code || $lead->city): ?>
                    <div class="crm-loc">
                        <i class="ri-map-pin-2-line"></i>
                        <?= h(strtoupper((string)$lead->country_code)) ?><?php if ($lead->city): ?> · <?= h($lead->city) ?><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($lead->contact_person): ?>
                    <div class="crm-contact"><i class="ri-user-line"></i> <?= h($lead->contact_person) ?></div>
                <?php endif; ?>

                <div class="crm-card-meta">
                    <?php if ($days !== null): ?>
                        <span class="crm-chip <?= $days > 14 ? 'danger' : ($days > 7 ? 'warning' : '') ?>">
                            <i class="ri-time-line"></i> <?= $days ?>d
                        </span>
                    <?php endif; ?>
                    <?php if ($lead->value_pln): ?>
                        <span class="crm-chip <?= (float)$lead->value_pln >= 10000 ? 'success' : '' ?>">
                            <?= number_format((float)$lead->value_pln, 0, ',', ' ') ?> zł
                        </span>
                    <?php endif; ?>
                    <span class="crm-chip pipe-value"><?= (int)$lead->probability ?>%</span>
                </div>

                <div class="crm-card-footer">
                    <div style="flex: 1;"></div>
                    <?php if ($avatar): ?>
                        <?php if (!empty($avatar['url'])): ?>
                            <img src="<?= h($avatar['url']) ?>" alt="<?= h($avatar['name']) ?>"
                                 class="crm-avatar <?= $isMine ? 'mine' : '' ?>"
                                 style="object-fit: cover;"
                                 title="<?= h($avatar['name']) ?><?= $isMine ? ' (Ty)' : '' ?>"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div class="crm-avatar <?= $isMine ? 'mine' : '' ?>" style="background: <?= $avatar['bg'] ?>; display: none;"
                                 title="<?= h($avatar['name']) ?><?= $isMine ? ' (Ty)' : '' ?>">
                                <?= h($avatar['initials']) ?>
                            </div>
                        <?php else: ?>
                            <div class="crm-avatar <?= $isMine ? 'mine' : '' ?>" style="background: <?= $avatar['bg'] ?>;"
                                 title="<?= h($avatar['name']) ?><?= $isMine ? ' (Ty)' : '' ?>">
                                <?= h($avatar['initials']) ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="crm-avatar unassigned" title="<?= __('Nieprzypisany') ?>">
                            <i class="ri-user-line"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Trello-style Lead Peek Modal (FULL DETAIL) -->
<div class="modal fade" id="leadPeekModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="background: #f4f5f7;">
            <div class="modal-header" id="peek-header" style="background: #fff; border-bottom: 1px solid #dfe1e6;">
                <div class="flex-grow-1">
                    <div class="small text-muted" id="peek-stage-label" style="text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; font-size: 11px;"></div>
                    <h5 class="modal-title mb-0 mt-1" id="peek-title" style="color: #172b4d;"></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body p-0" id="peek-body">
                <div class="text-center py-5">
                    <i class="ri-loader-4-line" style="font-size: 32px; color: #5e6c84; animation: spin 1s linear infinite;"></i>
                </div>
            </div>
            <div class="modal-footer" style="background: #fff; border-top: 1px solid #dfe1e6;">
                <a href="#" id="peek-view-full" class="btn btn-sm btn-outline-primary" target="_blank">
                    <i class="ri-external-link-line"></i> <?= __('Pełny widok') ?>
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"><?= __('Zamknij') ?></button>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }

/* Trello peek modal - full detail */
.peek-container { display: grid; grid-template-columns: 1fr 260px; gap: 0; min-height: 500px; }
.peek-main { padding: 20px; overflow-y: auto; }
.peek-sidebar { background: #fff; padding: 16px; border-left: 1px solid #dfe1e6; }
.peek-sidebar h6 { text-transform: uppercase; letter-spacing: 0.5px; font-size: 11px; color: #5e6c84;
    font-weight: 700; margin: 12px 0 6px; }
.peek-sidebar h6:first-child { margin-top: 0; }
.peek-sidebar .btn { display: flex; align-items: center; gap: 6px; text-align: left; font-size: 13px;
    background: #f4f5f7; border: 0; color: #172b4d; padding: 6px 10px; border-radius: 3px; width: 100%; }
.peek-sidebar .btn:hover { background: #dfe1e6; }

/* Label picker dropdown */
.label-picker-dropdown { position: relative; }
.label-picker-menu { position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff;
    border-radius: 6px; box-shadow: 0 8px 16px rgba(0,0,0,0.2); padding: 8px; z-index: 1055;
    max-height: 280px; overflow-y: auto; display: none; }
.label-picker-menu.show { display: block; }
.label-picker-item { display: flex; align-items: center; gap: 8px; padding: 6px 8px; cursor: pointer;
    border-radius: 4px; font-size: 12px; }
.label-picker-item:hover { background: #f4f5f7; }
.label-picker-item .label-swatch { flex-grow: 1; padding: 4px 12px; border-radius: 4px; color: #fff;
    font-weight: 600; font-size: 11px; }
.label-picker-item input { margin: 0; }

/* Drop zone */
.attach-dropzone { border: 2px dashed #dfe1e6; border-radius: 6px; padding: 24px; text-align: center;
    color: #5e6c84; font-size: 13px; margin-top: 8px; transition: all 0.15s; cursor: pointer; }
.attach-dropzone:hover { border-color: #94C81F; background: #fafffa; color: #6b8f14; }
.attach-dropzone.drag-over { border-color: #94C81F; background: #f0f9e0; color: #6b8f14; transform: scale(1.02); }
.attach-item { display: flex; align-items: center; gap: 8px; padding: 8px; background: #fff;
    border-radius: 4px; margin-bottom: 6px; }
.attach-item .filename { flex-grow: 1; font-size: 13px; color: #172b4d; word-break: break-all; }

/* Timeline w peek */
.peek-timeline { display: flex; flex-direction: column; gap: 6px; }
.peek-tl-item { display: flex; gap: 8px; padding: 8px; background: #fff; border-radius: 4px; }
.peek-tl-ico { width: 24px; height: 24px; border-radius: 50%; background: #dfe1e6; color: #5e6c84;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 12px; }
.peek-tl-body { flex-grow: 1; font-size: 12px; color: #172b4d; }
.peek-tl-body .subj { font-weight: 600; }
.peek-tl-body .date { color: #5e6c84; font-size: 10px; margin-top: 2px; }

/* Responsive - full width on mobile */
@media (max-width: 768px) {
    .peek-container { grid-template-columns: 1fr; }
    .peek-sidebar { border-left: 0; border-top: 1px solid #dfe1e6; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(function() {
    var csrf = '<?= $this->request->getAttribute('csrfToken') ?>';
    // Stage color map dla live update label po drag
    var stageColorMap = <?= json_encode(array_column(array_map(fn($k) => [$k, $stageColors[$k]['color'] ?? '#5e6c84'], array_keys($stageColors)), 1, 0)) ?>;
    var stageLabelMap = <?= json_encode($stageLabels) ?>;

    document.querySelectorAll('.crm-cards').forEach(function(list) {
        new Sortable(list, {
            group: 'crm-leads',
            animation: 160,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt) {
                var card = evt.item;
                var toCol = evt.to.closest('.crm-col');
                var newStage = toCol.dataset.stage;
                var id = card.dataset.id;
                var form = new FormData();
                form.append('_csrfToken', csrf);
                form.append('stage', newStage);
                fetch('<?= $this->Url->build(['action' => 'kanbanMove']) ?>/' + id, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    body: form,
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    if (!j.ok) {
                        alert('<?= __('Błąd zmiany etapu') ?>: ' + (j.error || 'unknown'));
                        // Cofnij pozycję
                        evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null);
                        return;
                    }
                    // LIVE UPDATE: zmien pierwsze label kolor na nowy stage color
                    var firstLabel = card.querySelector('.crm-label-strip .crm-label');
                    if (firstLabel && stageColorMap[newStage]) {
                        firstLabel.style.background = stageColorMap[newStage];
                    }
                })
                .catch(function() {
                    alert('<?= __('Błąd sieciowy') ?>');
                    evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null);
                });
            }
        });
    });

    // === MODAL PEEK (Trello-style quick view) ===
    var peekModal = new bootstrap.Modal(document.getElementById('leadPeekModal'));
    var peekBody = document.getElementById('peek-body');
    var peekTitle = document.getElementById('peek-title');
    var peekStageLabel = document.getElementById('peek-stage-label');
    var peekViewFull = document.getElementById('peek-view-full');
    var peekHeader = document.getElementById('peek-header');
    var URL_PEEK = '<?= $this->Url->build(['action' => 'peekJson']) ?>';
    var URL_VIEW = '<?= $this->Url->build(['action' => 'view']) ?>';

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

    var currentLeadId = null;
    var currentLabelIds = []; // aktualnie przypisane

    // Ikony per activity type
    var activityIcons = {
        phone_call: 'ri-phone-line', email_out: 'ri-mail-send-line', email_in: 'ri-mail-download-line',
        meeting: 'ri-calendar-event-line', note: 'ri-sticky-note-line', task: 'ri-checkbox-line',
        file: 'ri-attachment-2', stage_change: 'ri-arrow-right-up-line', assignment: 'ri-user-shared-line',
        offer_sent: 'ri-file-paper-line', order_won: 'ri-trophy-line', order_lost: 'ri-close-circle-line',
        quote_request: 'ri-file-list-3-line',
    };

    window.openLeadPeek = function(leadId) {
        currentLeadId = leadId;
        peekTitle.textContent = '';
        peekStageLabel.textContent = '';
        peekBody.innerHTML = '<div class="text-center py-5"><i class="ri-loader-4-line" style="font-size:32px;color:#5e6c84;animation:spin 1s linear infinite;"></i></div>';
        peekViewFull.href = URL_VIEW + '/' + leadId;
        peekModal.show();

        fetch(URL_PEEK + '/' + leadId, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(j) {
                if (!j.ok) {
                    peekBody.innerHTML = '<div class="alert alert-danger m-3">' + esc(j.error || 'Blad') + '</div>';
                    return;
                }
                renderPeek(j.lead);
            })
            .catch(function(e) {
                peekBody.innerHTML = '<div class="alert alert-danger m-3">Blad sieci: ' + esc(e.message) + '</div>';
            });
    };

    function renderPeek(l) {
        currentLabelIds = (l.labels || []).map(function(lb) { return lb.id; });
        var stageColor = stageColorMap[l.stage] || '#5e6c84';
        var stageLbl = stageLabelMap[l.stage] || l.stage;

        peekTitle.textContent = l.company_name || '(bez nazwy)';
        peekStageLabel.innerHTML = '<span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:' + stageColor + ';margin-right:6px;vertical-align:middle;"></span>' + esc(stageLbl);
        peekHeader.style.borderTop = '4px solid ' + stageColor;

        var html = '<div class="peek-container">';

        // === MAIN COLUMN ===
        html += '<div class="peek-main">';

        // KPI kafelki
        html += '<div class="row g-2 mb-3">';
        html += '<div class="col-4"><div class="p-2 rounded text-center" style="background:#fff;">'
            + '<div class="fw-bold" style="font-size:22px;color:' + stageColor + ';">' + (l.probability || 0) + '%</div>'
            + '<div class="small text-muted">Skuteczność</div></div></div>';
        html += '<div class="col-4"><div class="p-2 rounded text-center" style="background:#fff;">'
            + '<div class="fw-bold" style="font-size:22px;color:#172b4d;">' + (l.value_pln ? Number(l.value_pln).toLocaleString('pl-PL') + ' zł' : '—') + '</div>'
            + '<div class="small text-muted">Wartość</div></div></div>';
        html += '<div class="col-4"><div class="p-2 rounded text-center" style="background:#fff;">'
            + '<div class="fw-bold" style="font-size:22px;color:#172b4d;">' + (l.days_in_stage != null ? l.days_in_stage + 'd' : '—') + '</div>'
            + '<div class="small text-muted">W etapie</div></div></div>';
        html += '</div>';

        // ETYKIETY (Trello labels) - kolorowe badges
        html += '<div class="mb-3">';
        html += '<h6 class="fw-bold mb-2 text-uppercase small" style="color:#5e6c84;letter-spacing:0.5px;"><i class="ri-price-tag-3-line"></i> Etykiety</h6>';
        html += '<div id="peek-labels-display" class="d-flex flex-wrap gap-2">';
        if (l.labels && l.labels.length) {
            l.labels.forEach(function(lb) {
                html += '<span style="background:' + esc(lb.color) + ';color:#fff;padding:4px 12px;border-radius:4px;font-size:12px;font-weight:600;">' + esc(lb.name) + '</span>';
            });
        } else {
            html += '<span class="text-muted small">Brak etykiet</span>';
        }
        html += '</div></div>';

        // Dane firmy
        html += '<div class="mb-3 p-3" style="background:#fff;border-radius:6px;">';
        html += '<h6 class="fw-bold mb-2"><i class="ri-building-line"></i> Dane firmy</h6>';
        if (l.nip)          html += '<div class="small"><strong>NIP:</strong> ' + esc(l.nip) + '</div>';
        if (l.country_code || l.city) html += '<div class="small"><i class="ri-map-pin-2-line text-muted"></i> ' + esc((l.country_code || '') + ' ' + (l.postal_code || '') + ' ' + (l.city || '')) + '</div>';
        if (l.street)       html += '<div class="small">' + esc(l.street) + '</div>';
        if (l.branch_type)  html += '<div class="small mt-1"><span class="badge bg-secondary">' + esc(l.branch_type) + '</span></div>';
        html += '</div>';

        // Kontakt
        if (l.contact_person || l.email || l.phone) {
            html += '<div class="mb-3 p-3" style="background:#fff;border-radius:6px;">';
            html += '<h6 class="fw-bold mb-2"><i class="ri-user-line"></i> Kontakt</h6>';
            if (l.contact_person) html += '<div class="small fw-semibold">' + esc(l.contact_person) + '</div>';
            if (l.email) html += '<div class="small"><a href="mailto:' + esc(l.email) + '"><i class="ri-mail-line"></i> ' + esc(l.email) + '</a></div>';
            if (l.phone) html += '<div class="small"><a href="tel:' + esc(l.phone) + '"><i class="ri-phone-line"></i> ' + esc(l.phone) + '</a></div>';
            html += '</div>';
        }

        // Notatka
        if (l.note) {
            html += '<div class="mb-3 p-3" style="background:#fffae6;border-radius:6px;border-left:3px solid #f59e0b;">';
            html += '<h6 class="fw-bold mb-2"><i class="ri-sticky-note-line"></i> Notatka</h6>';
            html += '<div class="small" style="white-space:pre-wrap;">' + esc(l.note) + '</div>';
            html += '</div>';
        }

        // ZAŁĄCZNIKI + DROP ZONE
        html += '<div class="mb-3">';
        html += '<h6 class="fw-bold mb-2 text-uppercase small" style="color:#5e6c84;letter-spacing:0.5px;"><i class="ri-attachment-2"></i> Załączniki'
             + ((l.attachments && l.attachments.length) ? ' (' + l.attachments.length + ')' : '') + '</h6>';
        html += '<div id="peek-attachments-list">';
        if (l.attachments && l.attachments.length) {
            l.attachments.forEach(function(a) {
                var mm = a.mime || '';
                var ico = 'ri-file-line', col = '#5e6c84';
                if (mm.indexOf('image/') === 0) { ico = 'ri-image-line'; col = '#2563eb'; }
                else if (mm === 'application/pdf') { ico = 'ri-file-pdf-line'; col = '#dc2626'; }
                else if (mm.indexOf('spreadsheet') !== -1 || mm.indexOf('excel') !== -1) { ico = 'ri-file-excel-line'; col = '#059669'; }
                else if (mm.indexOf('word') !== -1) { ico = 'ri-file-word-line'; col = '#2563eb'; }
                html += '<div class="attach-item">'
                    + '<i class="' + ico + '" style="color:' + col + ';font-size:18px;"></i>'
                    + '<a class="filename" href="' + esc(a.url) + '" target="_blank">' + esc(a.filename) + '</a>'
                    + '<span class="text-muted small">' + Math.round(a.size / 1024) + 'KB</span>'
                    + '<a href="/crm/attachment-file/' + esc(a.id) + '?download=1" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Pobierz"><i class="ri-download-line"></i></a>'
                    + '</div>';
            });
        }
        html += '</div>';
        html += '<div class="attach-dropzone" id="peek-dropzone">'
            + '<i class="ri-upload-cloud-2-line" style="font-size:32px;display:block;margin-bottom:6px;"></i>'
            + 'Upuść pliki tutaj lub kliknij aby wybrać'
            + '<input type="file" id="peek-file-input" multiple style="display:none;">'
            + '</div>';
        html += '<div id="peek-upload-status" class="small mt-2"></div>';
        html += '</div>';

        // TIMELINE (ostatnie 10)
        if (l.activities && l.activities.length) {
            html += '<div class="mb-3">';
            html += '<h6 class="fw-bold mb-2 text-uppercase small" style="color:#5e6c84;letter-spacing:0.5px;"><i class="ri-history-line"></i> Timeline (' + l.activities.length + ')</h6>';
            html += '<div class="peek-timeline">';
            l.activities.forEach(function(a) {
                var ico = activityIcons[a.type] || 'ri-more-line';
                html += '<div class="peek-tl-item">'
                    + '<div class="peek-tl-ico"><i class="' + ico + '"></i></div>'
                    + '<div class="peek-tl-body">'
                    +   '<span class="badge bg-light text-dark border">' + esc(a.type) + '</span> '
                    +   '<span class="subj">' + esc(a.subject || '') + '</span>'
                    +   (a.body ? '<div style="color:#5e6c84;margin-top:2px;">' + esc(a.body) + '</div>' : '')
                    +   '<div class="date">' + esc(a.date || '') + '</div>'
                    + '</div></div>';
            });
            html += '</div></div>';
        }

        html += '</div>'; // /peek-main

        // === SIDEBAR ===
        html += '<div class="peek-sidebar">';

        // Opiekun
        if (l.assigned_user) {
            html += '<h6>Opiekun</h6>';
            html += '<div class="d-flex gap-2 align-items-center mb-2">';
            if (l.assigned_user.avatar) {
                html += '<img src="' + esc(l.assigned_user.avatar) + '" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">';
            } else {
                html += '<div style="width:32px;height:32px;border-radius:50%;background:#7c3aed;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;">' + esc((l.assigned_user.name || '?').charAt(0)) + '</div>';
            }
            html += '<div style="min-width:0;flex:1;"><div class="small fw-semibold text-truncate">' + esc(l.assigned_user.name || '') + '</div></div>';
            html += '</div>';
        } else {
            html += '<h6>Opiekun</h6><div class="small text-muted mb-2">Nieprzypisany</div>';
        }

        // ADD TO CARD (Trello-style)
        html += '<h6>Dodaj do karty</h6>';
        html += '<div class="d-flex flex-column gap-1 mb-3">';
        html += '<div class="label-picker-dropdown">'
            + '<button class="btn" type="button" id="peek-labels-toggle"><i class="ri-price-tag-3-line"></i> Etykiety</button>'
            + '<div class="label-picker-menu" id="peek-labels-menu"></div>'
            + '</div>';
        html += '<button class="btn" type="button" onclick="document.getElementById(\'peek-file-input\').click();"><i class="ri-attachment-2"></i> Załącznik</button>';
        html += '</div>';

        // AKCJE (Trello-style)
        html += '<h6>Akcje</h6>';
        html += '<div class="d-flex flex-column gap-1">';
        html += '<a class="btn" href="' + URL_VIEW + '/' + esc(l.id) + '"><i class="ri-external-link-line"></i> Pełny widok</a>';
        if (l.email) html += '<a class="btn" href="mailto:' + esc(l.email) + '"><i class="ri-mail-line"></i> Napisz</a>';
        if (l.phone) html += '<a class="btn" href="tel:' + esc(l.phone) + '"><i class="ri-phone-line"></i> Zadzwoń</a>';
        html += '</div>';

        html += '</div>'; // /peek-sidebar

        html += '</div>'; // /peek-container

        peekBody.innerHTML = html;

        // Wire up event handlers
        wireLabelsPicker(l.id);
        wireDropzone(l.id);
    }

    // === LABELS PICKER ===
    var URL_LABELS_ALL = '<?= $this->Url->build(['action' => 'labelsAllJson']) ?>';
    var URL_ASSIGN = '<?= $this->Url->build(['action' => 'assignLabels']) ?>';

    function wireLabelsPicker(leadId) {
        var toggle = document.getElementById('peek-labels-toggle');
        var menu = document.getElementById('peek-labels-menu');
        if (!toggle || !menu) return;
        var loaded = false;
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!loaded) {
                fetch(URL_LABELS_ALL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function(r){ return r.json(); })
                    .then(function(j) {
                        if (!j.ok || !j.labels) { menu.innerHTML = '<div class="small text-muted p-2">Brak etykiet. <a href="/crm/etykiety" target="_blank">Utwórz</a></div>'; loaded = true; menu.classList.add('show'); return; }
                        if (j.labels.length === 0) { menu.innerHTML = '<div class="small text-muted p-2">Brak etykiet. <a href="/crm/etykiety" target="_blank">Utwórz pierwszą</a></div>'; loaded = true; menu.classList.add('show'); return; }
                        var html = '';
                        j.labels.forEach(function(lb) {
                            var checked = currentLabelIds.indexOf(lb.id) !== -1 ? 'checked' : '';
                            html += '<label class="label-picker-item">'
                                + '<input type="checkbox" value="' + esc(lb.id) + '" ' + checked + '>'
                                + '<span class="label-swatch" style="background:' + esc(lb.color) + ';">' + esc(lb.name) + '</span>'
                                + '</label>';
                        });
                        html += '<div class="mt-2 pt-2 border-top small"><a href="/crm/etykiety" target="_blank"><i class="ri-add-line"></i> Zarządzaj etykietami</a></div>';
                        menu.innerHTML = html;
                        // Wire checkbox change - toggle assignment
                        menu.querySelectorAll('input[type="checkbox"]').forEach(function(chk) {
                            chk.addEventListener('change', function() {
                                var id = chk.value;
                                if (chk.checked) {
                                    if (currentLabelIds.indexOf(id) === -1) currentLabelIds.push(id);
                                } else {
                                    currentLabelIds = currentLabelIds.filter(function(x) { return x !== id; });
                                }
                                saveLabels(leadId);
                            });
                        });
                        loaded = true;
                        menu.classList.add('show');
                    });
            } else {
                menu.classList.toggle('show');
            }
        });
        document.addEventListener('click', function(e) {
            if (menu.classList.contains('show') && !menu.contains(e.target) && e.target !== toggle) {
                menu.classList.remove('show');
            }
        });
    }

    function saveLabels(leadId) {
        var fd = new FormData();
        fd.append('_csrfToken', csrf);
        currentLabelIds.forEach(function(id) { fd.append('label_ids[]', id); });
        fetch(URL_ASSIGN.replace(/\/$/, '') + '/' + leadId, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
        }).then(function(r) { return r.json(); }).then(function(j) {
            if (j.ok) {
                // Update display: pobierz labels z menu, zbuduj badge display
                var menu = document.getElementById('peek-labels-menu');
                var display = document.getElementById('peek-labels-display');
                if (!display) return;
                var newHtml = '';
                menu.querySelectorAll('input[type="checkbox"]:checked').forEach(function(chk) {
                    var swatch = chk.parentElement.querySelector('.label-swatch');
                    newHtml += '<span style="background:' + swatch.style.background + ';color:#fff;padding:4px 12px;border-radius:4px;font-size:12px;font-weight:600;">' + swatch.textContent + '</span>';
                });
                display.innerHTML = newHtml || '<span class="text-muted small">Brak etykiet</span>';
                // Update karty w Kanban
                var card = document.querySelector('.crm-card[data-id="' + leadId + '"]');
                if (card) {
                    var strip = card.querySelector('.crm-label-strip');
                    if (strip) {
                        var stripHtml = '';
                        menu.querySelectorAll('input[type="checkbox"]:checked').forEach(function(chk) {
                            var sw = chk.parentElement.querySelector('.label-swatch');
                            stripHtml += '<span style="background:' + sw.style.background + ';color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:3px;margin:0 0 4px 4px;display:inline-block;">' + sw.textContent + '</span>';
                        });
                        strip.innerHTML = stripHtml;
                    }
                }
            }
        });
    }

    // === DROPZONE UPLOAD ===
    function wireDropzone(leadId) {
        var dz = document.getElementById('peek-dropzone');
        var fi = document.getElementById('peek-file-input');
        var status = document.getElementById('peek-upload-status');
        if (!dz || !fi) return;

        dz.addEventListener('click', function() { fi.click(); });
        dz.addEventListener('dragover', function(e) { e.preventDefault(); dz.classList.add('drag-over'); });
        dz.addEventListener('dragleave', function() { dz.classList.remove('drag-over'); });
        dz.addEventListener('drop', function(e) {
            e.preventDefault();
            dz.classList.remove('drag-over');
            handleFiles(leadId, e.dataTransfer.files, status);
        });
        fi.addEventListener('change', function() {
            handleFiles(leadId, fi.files, status);
        });
    }

    function handleFiles(leadId, files, status) {
        var total = files.length;
        var done = 0, failed = 0;
        var list = document.getElementById('peek-attachments-list');

        Array.from(files).forEach(function(file) {
            var fd = new FormData();
            fd.append('_csrfToken', csrf);
            fd.append('file', file);

            status.innerHTML = '<i class="ri-loader-4-line"></i> Uploaduje ' + (done + 1) + '/' + total + ': ' + esc(file.name) + '...';
            status.style.color = '#5e6c84';

            fetch('/crm/' + leadId + '/attachments/upload', {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
            }).then(function(r){ return r.json(); }).then(function(j) {
                if (j.ok && j.attachment) {
                    done++;
                    // Append do listy
                    var a = j.attachment;
                    var mm = a.mime || '';
                    var ico = 'ri-file-line', col = '#5e6c84';
                    if (mm.indexOf('image/') === 0) { ico = 'ri-image-line'; col = '#2563eb'; }
                    else if (mm === 'application/pdf') { ico = 'ri-file-pdf-line'; col = '#dc2626'; }
                    else if (mm.indexOf('spreadsheet') !== -1) { ico = 'ri-file-excel-line'; col = '#059669'; }
                    var div = document.createElement('div');
                    div.className = 'attach-item';
                    div.innerHTML = '<i class="' + ico + '" style="color:' + col + ';font-size:18px;"></i>'
                        + '<a class="filename" href="' + a.url + '" target="_blank">' + esc(a.filename) + '</a>'
                        + '<span class="text-muted small">' + Math.round(a.size / 1024) + 'KB</span>'
                        + '<a href="/crm/attachment-file/' + a.id + '?download=1" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Pobierz"><i class="ri-download-line"></i></a>';
                    list.appendChild(div);
                    status.innerHTML = '<i class="ri-check-line text-success"></i> ' + done + '/' + total + ' uploaded' + (failed ? ' (' + failed + ' bledow)' : '');
                    if (done + failed >= total) status.style.color = '#059669';
                } else {
                    failed++;
                    status.innerHTML = '<i class="ri-error-warning-line text-danger"></i> Blad: ' + esc(j.error || 'unknown') + ' (' + esc(file.name) + ')';
                    status.style.color = '#dc2626';
                }
            }).catch(function(e) {
                failed++;
                status.innerHTML = '<i class="ri-error-warning-line text-danger"></i> Blad sieci: ' + esc(e.message);
                status.style.color = '#dc2626';
            });
        });
    }
})();
</script>

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
                 onclick="if(!event.target.closest('a,button')) location.href='<?= $this->Url->build(['action' => 'view', $lead->id]) ?>'">
                <?php if (!empty($labels)): ?>
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

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(function() {
    var csrf = '<?= $this->request->getAttribute('csrfToken') ?>';
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
                    }
                })
                .catch(function() {
                    alert('<?= __('Błąd sieciowy') ?>');
                    evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null);
                });
            }
        });
    });
})();
</script>

<?php
/**
 * @var \App\View\AppView $this
 * @var array<string, iterable<\App\Model\Entity\Lead>> $columns
 * @var array $stats
 */
$this->assign('title', __('CRM – Kanban leadów'));

$stageLabels = [
    'new'     => __('Nowy lead'),
    'contact' => __('Kontakt'),
    'inquiry' => __('Zapytanie'),
    'offer'   => __('Oferta'),
    'order'   => __('Zlecenie'),
];
$stageColors = [
    'new' => '#0d6efd', 'contact' => '#0dcaf0', 'inquiry' => '#f59e0b',
    'offer' => '#7c3aed', 'order' => '#198754',
];
?>
<style>
.crm-kb { display: grid; grid-template-columns: repeat(5, minmax(250px, 1fr)); gap: 12px; overflow-x: auto; padding-bottom: 12px; }
.crm-col { background: #f5f6fa; border-radius: 10px; min-height: 400px; padding: 10px; }
.crm-col-hdr { display: flex; justify-content: space-between; align-items: center; padding: 4px 8px 10px; }
.crm-col-hdr .dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; vertical-align: middle; }
.crm-col-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #495057; }
.crm-col-badge { background: #fff; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; color: #495057; }
.crm-cards { display: flex; flex-direction: column; gap: 8px; min-height: 300px; }
.crm-card { background: #fff; border-radius: 8px; padding: 10px 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.06); cursor: grab; }
.crm-card:hover { box-shadow: 0 4px 10px rgba(0,0,0,0.09); }
.crm-card.sortable-ghost { opacity: 0.4; background: #e9ecef; }
.crm-card-title { font-weight: 700; font-size: 13px; color: #212529; margin-bottom: 4px; }
.crm-card-meta { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 6px; }
.crm-chip { font-size: 10px; padding: 2px 6px; border-radius: 4px; background: #f1f3f5; color: #495057; font-weight: 600; }
.crm-pin { color: #f59e0b; }
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
    <div>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-table-line me-1"></i><?= __('Tabela') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-success">
            <i class="ri-add-line me-1"></i><?= __('Nowy lead') ?>
        </a>
    </div>
</div>

<div class="crm-kb">
    <?php foreach ($columns as $stage => $items):
        $count = count($items);
        $sum = 0;
        foreach ($items as $it) { $sum += (float)$it->value_pln; }
    ?>
    <div class="crm-col" data-stage="<?= h($stage) ?>">
        <div class="crm-col-hdr">
            <div>
                <span class="dot" style="background: <?= $stageColors[$stage] ?>;"></span>
                <span class="crm-col-title"><?= h($stageLabels[$stage]) ?></span>
            </div>
            <span class="crm-col-badge"><?= $count ?> · <?= number_format($sum, 0, ',', ' ') ?></span>
        </div>
        <div class="crm-cards" data-column="<?= h($stage) ?>">
            <?php foreach ($items as $lead):
                $assigned = trim(($lead->assigned_user?->first_name ?? '') . ' ' . mb_substr(($lead->assigned_user?->last_name ?? ''), 0, 1) . '.');
                $days = $lead->getDaysInStage();
            ?>
            <div class="crm-card" data-id="<?= h($lead->id) ?>"
                 onclick="location.href='<?= $this->Url->build(['action' => 'view', $lead->id]) ?>'">
                <div class="crm-card-title">
                    <?php if ($lead->kanban_pinned): ?><i class="ri-pushpin-fill crm-pin"></i> <?php endif; ?>
                    <?= h($lead->company_name) ?>
                </div>
                <?php if ($lead->country_code || $lead->city): ?>
                    <div class="text-muted small">
                        <i class="ri-map-pin-2-line"></i>
                        <?= h(strtoupper((string)$lead->country_code)) ?><?php if ($lead->city): ?> · <?= h($lead->city) ?><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($lead->contact_person): ?>
                    <div class="text-muted small"><i class="ri-user-line"></i> <?= h($lead->contact_person) ?></div>
                <?php endif; ?>
                <div class="crm-card-meta">
                    <?php if ($days !== null): ?>
                        <span class="crm-chip <?= $days > 7 ? 'text-danger' : '' ?>"><i class="ri-time-line"></i> <?= $days ?>d</span>
                    <?php endif; ?>
                    <?php if ($lead->value_pln): ?>
                        <span class="crm-chip"><?= number_format((float)$lead->value_pln, 0, ',', ' ') ?> zł</span>
                    <?php endif; ?>
                    <?php if ($assigned): ?>
                        <span class="crm-chip"><i class="ri-user-star-line"></i> <?= h(trim($assigned)) ?></span>
                    <?php endif; ?>
                    <span class="crm-chip" style="background:rgba(148,200,31,0.15);color:#6b8f14;"><?= (int)$lead->probability ?>%</span>
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

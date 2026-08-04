<?php
/**
 * Kanban operacyjny zlecen.
 * @var \App\View\AppView $this
 * @var array<int, array{label:string, color:string, items:\App\Model\Entity\SpeedOrder[]}> $columns
 * @var string $contract
 * @var string $source
 * @var string $search
 * @var array $contractsList
 */
$this->assign('title', __('Kanban zleceń'));
$csrf = (string)$this->request->getAttribute('csrfToken');
?>
<?= $this->Html->script('https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js', ['block' => true]) ?>

<style>
.kb-wrap { overflow-x: auto; padding-bottom: 1rem; }
.kb-cols { display: flex; gap: 12px; min-width: 1400px; }
.kb-col { flex: 1 1 0; min-width: 260px; background: #f8fafc; border-radius: 8px; padding: 8px; }
.kb-col-hdr { display: flex; justify-content: space-between; align-items: center; padding: 4px 8px; border-radius: 6px; font-weight: 600; margin-bottom: 8px; font-size: .85rem; }
.kb-col-1 { background: #dbeafe; color: #1e40af; }
.kb-col-2 { background: #cffafe; color: #155e75; }
.kb-col-3 { background: #fef3c7; color: #92400e; }
.kb-col-4 { background: #d1fae5; color: #065f46; }
.kb-col-5 { background: #e5e7eb; color: #374151; }
.kb-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px; margin-bottom: 6px; cursor: grab; transition: box-shadow .15s; }
.kb-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.kb-card.sortable-ghost { opacity: .5; }
.kb-card-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.kb-card-symbol { font-weight: 600; font-size: .82rem; }
.kb-card-buyer { font-size: .78rem; color: #111; margin-bottom: 2px; }
.kb-card-route { font-size: .72rem; color: #6b7280; }
.kb-card-amount { font-size: .78rem; font-weight: 600; color: #4338ca; margin-top: 4px; }
.kb-card-meta { font-size: .68rem; color: #9ca3af; margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap; }
.kb-empty { color: #9ca3af; text-align: center; padding: 12px; font-size: .8rem; }
.kb-count { font-size: .7rem; opacity: .85; }
.badge-src-M { background:#eef2ff;color:#4338ca }
.badge-src-S { background:#e5e7eb;color:#374151 }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-kanban-view me-1 text-primary"></i><?= __('Kanban zleceń') ?>
    </h4>
    <div class="d-flex gap-2">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-list-check me-1"></i><?= __('Lista') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-outline-primary">
            <i class="ri-add-line me-1"></i><?= __('Nowe zlecenie') ?>
        </a>
    </div>
</div>

<!-- Filtry -->
<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" class="form-control form-control-sm" value="<?= h($search) ?>" placeholder="<?= __('Symbol / klient / referencja') ?>">
    </div>
    <div class="col-md-2">
        <select name="contract" class="form-select form-select-sm">
            <option value="">— <?= __('Wszystkie kontrakty') ?> —</option>
            <?php foreach ($contractsList as $c): ?>
                <option value="<?= h($c) ?>" <?= $contract === $c ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="source" class="form-select form-select-sm">
            <option value=""><?= __('Wszystkie źródła') ?></option>
            <option value="speed"  <?= $source === 'speed'  ? 'selected' : '' ?>>Speed</option>
            <option value="manual" <?= $source === 'manual' ? 'selected' : '' ?>><?= __('Ręczne') ?></option>
        </select>
    </div>
    <div class="col-md-1">
        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="ri-search-line"></i></button>
    </div>
    <div class="col-md-1">
        <?php if ($search !== '' || $contract !== '' || $source !== ''): ?>
            <a href="<?= $this->Url->build(['action' => 'kanban']) ?>" class="btn btn-sm btn-outline-secondary w-100">
                <i class="ri-close-line"></i>
            </a>
        <?php endif; ?>
    </div>
</form>

<div class="kb-wrap">
    <div class="kb-cols">
        <?php foreach ($columns as $statusId => $col): ?>
            <div class="kb-col">
                <div class="kb-col-hdr kb-col-<?= $statusId ?>">
                    <span><i class="ri-flag-line me-1"></i><?= h($col['label']) ?></span>
                    <span class="kb-count"><?= count($col['items']) ?></span>
                </div>
                <div class="kb-col-body" data-status="<?= $statusId ?>">
                    <?php if (empty($col['items'])): ?>
                        <div class="kb-empty"><?= __('brak zleceń') ?></div>
                    <?php else: ?>
                        <?php foreach ($col['items'] as $o): ?>
                            <div class="kb-card" data-order-id="<?= (int)$o->id ?>">
                                <div class="kb-card-hdr">
                                    <a href="<?= $this->Url->build(['action' => 'view', $o->id]) ?>" class="kb-card-symbol text-decoration-none">
                                        <?= h($o->symbol) ?>
                                    </a>
                                    <span class="badge badge-src-<?= ($o->source ?? 'speed') === 'manual' ? 'M' : 'S' ?>" style="font-size:.55rem">
                                        <?= ($o->source ?? 'speed') === 'manual' ? 'M' : 'S' ?>
                                    </span>
                                </div>
                                <div class="kb-card-buyer"><?= h($o->buyer_name ?? '—') ?></div>
                                <div class="kb-card-route">
                                    <?= h(($o->load_city ?? '') . ' → ' . ($o->unload_city ?? '')) ?>
                                </div>
                                <div class="kb-card-amount">
                                    <?= number_format((float)$o->netto, 2, ',', ' ') ?> <?= h($o->currency) ?>
                                </div>
                                <div class="kb-card-meta">
                                    <?php if ($o->date_delivery): ?>
                                        <span><i class="ri-calendar-line me-1"></i><?= h($o->date_delivery->format('m-d')) ?></span>
                                    <?php endif; ?>
                                    <?php if ($o->driver): ?>
                                        <span><i class="ri-user-line me-1"></i><?= h(explode(' ', $o->driver)[0] ?? '') ?></span>
                                    <?php endif; ?>
                                    <?php if ($o->vehicle_reg): ?>
                                        <span><i class="ri-truck-line me-1"></i><?= h($o->vehicle_reg) ?></span>
                                    <?php endif; ?>
                                    <?php if (($o->approval_status ?? '') === 'pending'): ?>
                                        <span class="text-warning" title="<?= __('Oczekuje akceptacji') ?>"><i class="ri-time-line"></i></span>
                                    <?php elseif (($o->approval_status ?? '') === 'rejected'): ?>
                                        <span class="text-danger" title="<?= __('Odrzucone') ?>"><i class="ri-close-circle-line"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function(){
    var CSRF = <?= json_encode($csrf) ?>;
    document.querySelectorAll('.kb-col-body').forEach(function(col){
        new Sortable(col, {
            group: 'kb-orders',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function(evt){
                var card = evt.item;
                var newCol = evt.to;
                var orderId = card.dataset.orderId;
                var newStatus = parseInt(newCol.dataset.status, 10);
                if (!orderId || !newStatus) return;
                if (evt.from === evt.to) return; // same column - no change

                var fd = new FormData();
                fd.append('_csrfToken', CSRF);
                fd.append('to', newStatus);
                fetch('/zlecenia/kanban/przenies/' + orderId, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' }
                })
                .then(function(r){ return r.json(); })
                .then(function(j){
                    if (!j.ok) {
                        alert('Blad: ' + (j.error || 'nieznany'));
                        // Cofnij drag
                        evt.from.insertBefore(card, evt.from.children[evt.oldIndex]);
                    } else {
                        // Update licznikow
                        document.querySelectorAll('.kb-col').forEach(function(colEl){
                            var body = colEl.querySelector('.kb-col-body');
                            var cnt = colEl.querySelector('.kb-count');
                            if (body && cnt) cnt.textContent = body.querySelectorAll('.kb-card').length;
                        });
                    }
                });
            }
        });
    });
})();
</script>

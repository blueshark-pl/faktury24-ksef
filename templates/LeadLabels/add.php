<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\LeadLabel $label
 */
$isEdit = !empty($label->id);
$this->assign('title', $isEdit ? __('Edytuj etykietę') : __('Nowa etykieta'));
$palette = [
    '#94C81F', '#059669', '#0891b2', '#3b82f6', '#7c3aed',
    '#db2777', '#dc2626', '#ea580c', '#f59e0b', '#78716c',
    '#22c55e', '#06b6d4', '#8b5cf6', '#ec4899', '#eab308',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold">
        <i class="ri-price-tag-3-line"></i>
        <?= $isEdit ? __('Edytuj etykietę') : __('Nowa etykieta') ?>
    </h4>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line"></i> <?= __('Wróć') ?>
    </a>
</div>

<?= $this->Form->create($label, [
    'type' => 'post',
    'url' => $isEdit ? ['action' => 'edit', $label->id] : ['action' => 'add'],
]) ?>
<div class="card">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= __('Nazwa') ?> *</label>
                <input name="name" required maxlength="60" class="form-control" value="<?= h($label->name) ?>"
                       placeholder="np. ADR, Pilne, Duży kontrakt">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= __('Sortowanie') ?></label>
                <input name="sort_order" type="number" class="form-control" value="<?= (int)($label->sort_order ?? 100) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= __('Kolor (hex)') ?></label>
                <div class="input-group">
                    <input name="color" id="color-input" required maxlength="7" class="form-control text-uppercase"
                           value="<?= h($label->color ?: '#94C81F') ?>" placeholder="#94C81F">
                    <input id="color-picker" type="color" class="form-control form-control-color"
                           value="<?= h($label->color ?: '#94C81F') ?>" style="max-width: 60px;">
                </div>
            </div>
        </div>

        <div class="mt-3">
            <label class="form-label small"><?= __('Szybki wybór palety') ?></label>
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($palette as $c): ?>
                    <button type="button" class="palette-btn" data-color="<?= h($c) ?>"
                            style="width: 32px; height: 32px; border-radius: 4px; background: <?= h($c) ?>; border: 2px solid #fff; box-shadow: 0 0 0 1px #dee2e6; cursor: pointer;"
                            title="<?= h($c) ?>"></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-4 p-3 rounded" style="background: #f8f9fa;">
            <label class="form-label small text-muted"><?= __('Podgląd') ?></label>
            <div>
                <span id="preview-badge" style="display: inline-block; padding: 6px 16px; border-radius: 4px; background: <?= h($label->color ?: '#94C81F') ?>; color: #fff; font-weight: 700;">
                    <?= h($label->name ?: __('Nowa etykieta')) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary"><?= __('Anuluj') ?></a>
        <button type="submit" class="btn btn-primary"><?= __('Zapisz') ?></button>
    </div>
</div>
<?= $this->Form->end() ?>

<script>
(function() {
    var $ci = document.getElementById('color-input');
    var $cp = document.getElementById('color-picker');
    var $pb = document.getElementById('preview-badge');
    var $ni = document.querySelector('input[name="name"]');
    function sync(color) {
        $ci.value = color.toUpperCase();
        $cp.value = color;
        $pb.style.background = color;
    }
    $cp.addEventListener('input', function(){ sync($cp.value); });
    $ci.addEventListener('input', function(){
        var v = $ci.value.trim();
        if (/^#[0-9a-f]{6}$/i.test(v)) sync(v);
    });
    document.querySelectorAll('.palette-btn').forEach(function(btn){
        btn.addEventListener('click', function(){ sync(btn.dataset.color); });
    });
    $ni.addEventListener('input', function(){
        $pb.textContent = $ni.value || 'Nowa etykieta';
    });
})();
</script>

<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\LeadIndustry $item */
$isEdit = !empty($item->id);
$this->assign('title', $isEdit ? __('Edytuj branzę') : __('Nowa branża'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="ri-briefcase-line"></i> <?= $isEdit ? __('Edytuj branżę') : __('Nowa branża') ?></h4>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary"><i class="ri-arrow-left-line"></i> <?= __('Wróć') ?></a>
</div>
<?= $this->Form->create($item, ['type' => 'post', 'url' => $isEdit ? ['action' => 'edit', $item->id] : ['action' => 'add']]) ?>
<div class="card"><div class="card-body">
    <div class="row g-3">
        <div class="col-md-9">
            <label class="form-label"><?= __('Nazwa branży') ?> *</label>
            <input name="name" required maxlength="100" class="form-control" value="<?= h($item->name) ?>" placeholder="np. hutnicza, piekarnia, spożywcza, farmacja, przemysł ciężki">
        </div>
        <div class="col-md-3">
            <label class="form-label"><?= __('Sortowanie') ?></label>
            <input name="sort_order" type="number" class="form-control" value="<?= (int)($item->sort_order ?? 100) ?>">
        </div>
    </div>
</div>
<div class="card-footer text-end">
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary"><?= __('Anuluj') ?></a>
    <button type="submit" class="btn btn-primary"><?= __('Zapisz') ?></button>
</div></div>
<?= $this->Form->end() ?>

<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Vehicle $entity */
$this->assign('title', __('Edycja pojazdu'));
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-edit-line me-1"></i><?= __('Edycja pojazdu') ?>: <?= h($entity->name) ?>
    </h4>
</div>

<?= $this->element('vehicles/form', ['entity' => $entity]) ?>

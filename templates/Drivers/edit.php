<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Driver $entity */
$this->assign('title', __('Edytuj kierowcę'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-edit-line me-1"></i><?= __('Edytuj kierowcę') ?>: <?= h($entity->full_name) ?>
    </h4>
</div>
<?= $this->element('drivers/form', ['entity' => $entity]) ?>

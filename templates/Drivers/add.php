<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Driver $entity */
$this->assign('title', __('Dodaj kierowcę'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-add-line me-1"></i><?= __('Dodaj kierowcę') ?>
    </h4>
</div>
<?= $this->element('drivers/form', ['entity' => $entity]) ?>

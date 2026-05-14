<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Trailer $entity */
$this->assign('title', __('Dodaj naczepę'));
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-add-line me-1"></i><?= __('Dodaj naczepę') ?>
    </h4>
</div>

<?= $this->element('trailers/form', ['entity' => $entity]) ?>

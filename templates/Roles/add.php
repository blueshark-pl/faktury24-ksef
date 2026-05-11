<?php
/** @var \App\View\AppView $this @var \App\Model\Entity\Role $role */
$this->assign('title', __('Nowa rola'));
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="ri-shield-user-line me-1"></i><?= __('Nowa rola') ?></h4>
        <div class="text-muted small mt-1"><?= __('Po utworzeniu roli przypiszesz jej uprawnienia w edycji.') ?></div>
    </div>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i><?= __('Wróć') ?>
    </a>
</div>

<?= $this->Flash->render() ?>

<div class="card shadow-sm">
    <div class="card-body" style="max-width: 720px">
        <?= $this->Form->create($role) ?>

        <div class="mb-3">
            <label class="form-label"><?= __('Kod roli') ?> <span class="text-danger">*</span></label>
            <?= $this->Form->control('code', [
                'label'       => false,
                'class'       => 'form-control',
                'placeholder' => 'np. kierownik_floty',
                'required'    => true,
            ]) ?>
            <div class="form-text"><?= __('Tylko litery, cyfry i podkreślenia. Po zapisie kod można jeszcze zmienić, ale wpływa na permissions.php.') ?></div>
        </div>

        <div class="mb-3">
            <label class="form-label"><?= __('Nazwa') ?> <span class="text-danger">*</span></label>
            <?= $this->Form->control('name', [
                'label'       => false,
                'class'       => 'form-control',
                'placeholder' => __('Wyświetlana nazwa roli'),
                'required'    => true,
            ]) ?>
        </div>

        <div class="mb-3">
            <label class="form-label"><?= __('Opis') ?></label>
            <?= $this->Form->control('description', [
                'label' => false,
                'class' => 'form-control',
                'type'  => 'textarea',
                'rows'  => 3,
            ]) ?>
        </div>

        <div class="form-check mb-3">
            <?= $this->Form->checkbox('is_active', ['class' => 'form-check-input', 'id' => 'is_active', 'checked' => true]) ?>
            <label class="form-check-label" for="is_active"><?= __('Aktywna') ?></label>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="ri-save-line me-1"></i><?= __('Utwórz rolę') ?></button>
            <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary">
                <?= __('Anuluj') ?>
            </a>
        </div>

        <?= $this->Form->end() ?>
    </div>
</div>

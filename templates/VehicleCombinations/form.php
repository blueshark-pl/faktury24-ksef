<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\VehicleCombination $entity
 * @var array $vehicleOptions
 * @var array $trailerOptions
 * @var array $driverOptions
 * @var string $title
 */
$this->assign('title', $title);
?>
<div class="d-flex align-items-center mb-3">
    <h1 class="h4 mb-0"><?= h($title) ?></h1>
</div>

<?= $this->Form->create($entity, ['class' => 'card p-4']) ?>
<div class="row g-3">
    <div class="col-md-8">
        <?= $this->Form->control('name', [
            'label' => __('Nazwa zestawu'),
            'class' => 'form-control',
            'required' => true,
            'placeholder' => __('np. "Volvo FH-16 + Krone Cool Liner + Kowalski"'),
        ]) ?>
    </div>

    <div class="col-md-2">
        <?= $this->Form->control('is_active', [
            'label' => __('Aktywne'),
            'type' => 'checkbox',
            'class' => 'form-check-input',
            'default' => true,
        ]) ?>
    </div>

    <div class="col-md-2">
        <?= $this->Form->control('is_default', [
            'label' => __('Domyślny'),
            'type' => 'checkbox',
            'class' => 'form-check-input',
        ]) ?>
        <small class="text-muted"><?= __('Autoselect w planerze') ?></small>
    </div>

    <div class="col-md-4">
        <?= $this->Form->control('vehicle_id', [
            'label' => __('Ciągnik / pojazd'),
            'type' => 'select',
            'options' => $vehicleOptions,
            'empty' => __('— brak —'),
            'class' => 'form-select',
        ]) ?>
    </div>

    <div class="col-md-4">
        <?= $this->Form->control('trailer_id', [
            'label' => __('Naczepa / przyczepa'),
            'type' => 'select',
            'options' => $trailerOptions,
            'empty' => __('— brak —'),
            'class' => 'form-select',
        ]) ?>
    </div>

    <div class="col-md-4">
        <?= $this->Form->control('driver_id', [
            'label' => __('Kierowca'),
            'type' => 'select',
            'options' => $driverOptions,
            'empty' => __('— brak —'),
            'class' => 'form-select',
        ]) ?>
    </div>

    <div class="col-12">
        <?= $this->Form->control('notes', [
            'label' => __('Notatki (opcjonalne)'),
            'type' => 'textarea',
            'class' => 'form-control',
            'rows' => 3,
        ]) ?>
    </div>
</div>

<div class="mt-4 d-flex align-items-center">
    <?= $this->Form->button('<i class="ri-save-line"></i> ' . __('Zapisz'), [
        'class' => 'btn btn-primary',
        'escapeTitle' => false,
    ]) ?>
    <?= $this->Html->link(__('Anuluj'), ['action' => 'index'], ['class' => 'btn btn-link']) ?>
</div>
<?= $this->Form->end() ?>

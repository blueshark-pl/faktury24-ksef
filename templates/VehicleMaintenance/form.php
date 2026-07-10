<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\VehicleMaintenance $entity
 * @var array $vehicleOptions
 * @var array $trailerOptions
 * @var string $title
 */
$this->assign('title', $title);

$typeOptions = [
    'technical_inspection' => __('Badanie techniczne'),
    'service'              => __('Serwis'),
    'tacho_calibration'    => __('Kalibracja tachografu'),
    'adr_cert'             => __('Certyfikat ADR'),
    'insurance'            => __('Ubezpieczenie (ogólne)'),
    'oc'                   => __('OC'),
    'ac'                   => __('AC'),
    'extinguisher'         => __('Gaśnica'),
    'first_aid'            => __('Apteczka'),
    'other'                => __('Inne'),
];

$fmtDate = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (substr((string)$v, 0, 10) ?: '');
?>
<div class="d-flex align-items-center mb-3">
    <h1 class="h4 mb-0"><?= h($title) ?></h1>
</div>

<?= $this->Form->create($entity, ['class' => 'card p-4']) ?>
<div class="row g-3">
    <div class="col-md-6">
        <?= $this->Form->control('vehicle_id', [
            'label' => __('Pojazd (zostaw puste jeżeli wpis dla naczepy)'),
            'type' => 'select', 'options' => $vehicleOptions,
            'empty' => __('— brak —'), 'class' => 'form-select',
        ]) ?>
    </div>
    <div class="col-md-6">
        <?= $this->Form->control('trailer_id', [
            'label' => __('Naczepa (zostaw puste jeżeli wpis dla pojazdu)'),
            'type' => 'select', 'options' => $trailerOptions,
            'empty' => __('— brak —'), 'class' => 'form-select',
        ]) ?>
    </div>
    <div class="col-12">
        <small class="text-muted"><?= __('Podaj DOKŁADNIE jedno: pojazd LUB naczepę.') ?></small>
    </div>

    <div class="col-md-6">
        <?= $this->Form->control('maintenance_type', [
            'label' => __('Typ wpisu'),
            'type' => 'select', 'options' => $typeOptions,
            'empty' => __('— wybierz —'), 'class' => 'form-select', 'required' => true,
        ]) ?>
    </div>

    <div class="col-md-3">
        <label class="form-label" for="performed_at"><?= __('Wykonano') ?></label>
        <input type="date" id="performed_at" name="performed_at" class="form-control"
               value="<?= h($fmtDate($entity->performed_at)) ?>">
    </div>

    <div class="col-md-3">
        <label class="form-label" for="valid_until"><?= __('Ważne do') ?></label>
        <input type="date" id="valid_until" name="valid_until" class="form-control"
               value="<?= h($fmtDate($entity->valid_until)) ?>">
    </div>

    <div class="col-md-4">
        <?= $this->Form->control('supplier', [
            'label' => __('Dostawca / warsztat'),
            'class' => 'form-control',
            'placeholder' => __('np. SKP Warszawa, Volvo Serwis…'),
        ]) ?>
    </div>

    <div class="col-md-3">
        <?= $this->Form->control('cost', [
            'label' => __('Koszt'),
            'type' => 'number', 'step' => '0.01', 'min' => '0',
            'class' => 'form-control',
        ]) ?>
    </div>

    <div class="col-md-2">
        <?= $this->Form->control('currency', [
            'label' => __('Waluta'),
            'type' => 'select',
            'options' => ['PLN' => 'PLN', 'EUR' => 'EUR', 'USD' => 'USD'],
            'class' => 'form-select',
        ]) ?>
    </div>

    <div class="col-md-3">
        <?= $this->Form->control('reminder_days', [
            'label' => __('Alert (dni przed)'),
            'type' => 'number', 'min' => '1', 'max' => '365',
            'class' => 'form-control',
            'default' => 30,
        ]) ?>
    </div>

    <div class="col-12">
        <?= $this->Form->control('notes', [
            'label' => __('Notatki'),
            'type' => 'textarea', 'class' => 'form-control', 'rows' => 3,
        ]) ?>
    </div>

    <div class="col-12">
        <?= $this->Form->control('is_active', [
            'label' => __('Aktywny wpis (odznacz gdy zastąpiony nowszym)'),
            'type' => 'checkbox', 'class' => 'form-check-input',
        ]) ?>
    </div>
</div>

<div class="mt-4 d-flex align-items-center">
    <?= $this->Form->button('<i class="ri-save-line"></i> ' . __('Zapisz'), [
        'class' => 'btn btn-primary', 'escapeTitle' => false,
    ]) ?>
    <?= $this->Html->link(__('Anuluj'), ['action' => 'index'], ['class' => 'btn btn-link']) ?>
</div>
<?= $this->Form->end() ?>

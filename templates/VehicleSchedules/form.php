<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\VehicleSchedule $entity
 * @var array $vehicleOptions
 * @var array $trailerOptions
 * @var string $title
 */
$this->assign('title', $title);

$typeOptions = [
    'assignment'  => __('Zlecenie'),
    'maintenance' => __('Serwis'),
    'inspection'  => __('Przegląd / badanie techniczne'),
    'unavailable' => __('Niedostępny (inne)'),
];

$fmtDT = static function ($v) {
    if (!$v) return '';
    return $v instanceof \DateTimeInterface ? $v->format('Y-m-d\TH:i') : substr((string)$v, 0, 16);
};
?>
<div class="d-flex align-items-center mb-3">
    <h1 class="h4 mb-0"><?= h($title) ?></h1>
</div>

<?= $this->Form->create($entity, ['class' => 'card p-4', 'novalidate' => false]) ?>
<div class="row g-3">
    <div class="col-md-6">
        <?= $this->Form->control('vehicle_id', [
            'label' => __('Pojazd (zostaw puste jeżeli wpis dla naczepy)'),
            'type' => 'select',
            'options' => $vehicleOptions,
            'empty' => __('— brak —'),
            'class' => 'form-select',
        ]) ?>
    </div>
    <div class="col-md-6">
        <?= $this->Form->control('trailer_id', [
            'label' => __('Naczepa (zostaw puste jeżeli wpis dla pojazdu)'),
            'type' => 'select',
            'options' => $trailerOptions,
            'empty' => __('— brak —'),
            'class' => 'form-select',
        ]) ?>
    </div>
    <div class="col-12">
        <small class="text-muted"><?= __('Podaj DOKŁADNIE jedno: pojazd LUB naczepę.') ?></small>
    </div>

    <div class="col-md-6">
        <?= $this->Form->control('entry_type', [
            'label' => __('Typ wpisu'),
            'type' => 'select',
            'options' => $typeOptions,
            'empty' => __('— wybierz —'),
            'class' => 'form-select',
            'required' => true,
        ]) ?>
    </div>

    <div class="col-md-6"></div>

    <div class="col-md-6">
        <label class="form-label" for="starts_at"><?= __('Od') ?></label>
        <input type="datetime-local" id="starts_at" name="starts_at" class="form-control"
               value="<?= h($fmtDT($entity->starts_at)) ?>" required>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="ends_at"><?= __('Do') ?></label>
        <input type="datetime-local" id="ends_at" name="ends_at" class="form-control"
               value="<?= h($fmtDT($entity->ends_at)) ?>" required>
    </div>

    <div class="col-12">
        <?= $this->Form->control('notes', [
            'label' => __('Notatka'),
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

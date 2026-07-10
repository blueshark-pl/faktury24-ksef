<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\DriverTimeLog $entity
 * @var array $driverOptions
 * @var string $title
 */
$this->assign('title', $title);

$fmtDate = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (substr((string)$v, 0, 10) ?: '');
?>
<div class="d-flex align-items-center mb-3">
    <h1 class="h4 mb-0"><?= h($title) ?></h1>
</div>

<?= $this->Form->create($entity, ['class' => 'card p-4']) ?>
<div class="row g-3">
    <div class="col-md-6">
        <?= $this->Form->control('driver_id', [
            'label' => __('Kierowca'),
            'type' => 'select', 'options' => $driverOptions,
            'empty' => __('— wybierz —'), 'class' => 'form-select', 'required' => true,
        ]) ?>
    </div>

    <div class="col-md-3">
        <label class="form-label" for="log_date"><?= __('Data (doba pracy)') ?></label>
        <input type="date" id="log_date" name="log_date" class="form-control"
               value="<?= h($fmtDate($entity->log_date)) ?>" required>
    </div>

    <div class="col-md-3">
        <?= $this->Form->control('source', [
            'label' => __('Źródło'),
            'type' => 'select', 'options' => [
                'manual' => __('Ręczny'),
                'tachograph' => __('Tachograf'),
                'estimated' => __('Oszacowany'),
                'import_ddd' => __('Import DDD'),
                'import_csv' => __('Import CSV'),
            ],
            'class' => 'form-select',
        ]) ?>
    </div>

    <div class="col-12"><hr><strong><?= __('Czas (w minutach)') ?></strong></div>

    <div class="col-md-3">
        <?= $this->Form->control('driving_min', [
            'label' => __('Jazda') . ' <small class="text-muted">(max 540)</small>',
            'type' => 'number', 'min' => '0', 'max' => '1440', 'step' => '15',
            'class' => 'form-control', 'default' => 0,
            'escape' => false,
        ]) ?>
    </div>
    <div class="col-md-3">
        <?= $this->Form->control('rest_min', [
            'label' => __('Odpoczynek'),
            'type' => 'number', 'min' => '0', 'max' => '1440', 'step' => '15',
            'class' => 'form-control', 'default' => 0,
        ]) ?>
    </div>
    <div class="col-md-3">
        <?= $this->Form->control('other_work_min', [
            'label' => __('Inna praca'),
            'type' => 'number', 'min' => '0', 'max' => '1440', 'step' => '15',
            'class' => 'form-control', 'default' => 0,
        ]) ?>
    </div>
    <div class="col-md-3">
        <?= $this->Form->control('availability_min', [
            'label' => __('Dyżur / gotowość'),
            'type' => 'number', 'min' => '0', 'max' => '1440', 'step' => '15',
            'class' => 'form-control', 'default' => 0,
        ]) ?>
    </div>

    <div class="col-12"><hr><strong><?= __('Flagi compliance') ?></strong></div>

    <div class="col-md-3">
        <?= $this->Form->control('daily_rest_ok', [
            'label' => __('Odpoczynek 11h OK'),
            'type' => 'checkbox', 'class' => 'form-check-input',
        ]) ?>
    </div>
    <div class="col-md-3">
        <?= $this->Form->control('weekly_rest_ok', [
            'label' => __('Odpoczynek tyg. 45h OK'),
            'type' => 'checkbox', 'class' => 'form-check-input',
        ]) ?>
    </div>
    <div class="col-md-3">
        <?= $this->Form->control('extended_driving_used', [
            'label' => __('Rozszerzenie do 10h (max 2/tydz.)'),
            'type' => 'checkbox', 'class' => 'form-check-input',
        ]) ?>
    </div>
    <div class="col-md-3">
        <?= $this->Form->control('reduced_daily_rest_used', [
            'label' => __('Redukcja odp. do 9h (max 3/tydz.)'),
            'type' => 'checkbox', 'class' => 'form-check-input',
        ]) ?>
    </div>

    <div class="col-12">
        <?= $this->Form->control('notes', [
            'label' => __('Notatki'),
            'type' => 'textarea', 'class' => 'form-control', 'rows' => 2,
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

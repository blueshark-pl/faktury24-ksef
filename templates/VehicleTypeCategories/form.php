<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\VehicleTypeCategory $entity
 * @var string $title
 */
$this->assign('title', $title);

$typeOptions = [
    'standard'  => __('Standard (ciągnik 4x2 + naczepa 3-os., >18t)'),
    'mega'      => __('Mega'),
    'fridge'    => __('Chłodnia'),
    'tandem'    => __('Tandem (ciągnik + przyczepa)'),
    'solo'      => __('Solo'),
    'bus'       => __('Bus/Van'),
    'oversize'  => __('Ponadgabaryt'),
];

$countryHints = [
    'PL' => __('Polska (A2 AWSA, e-TOLL, A4 Katowice-Kraków)'),
    'DE' => __('Niemcy (Toll Collect)'),
    'AT' => __('Austria (GO-Box / ASFINAG)'),
    'CH' => __('Szwajcaria (LSVA)'),
    'CZ' => __('Czechy (MYTO CZ)'),
    'SK' => __('Słowacja (MYTO SK)'),
    'HU' => __('Węgry (HU-GO)'),
    'IT' => __('Włochy (Telepass)'),
    'FR' => __('Francja (ASFA, TIS-PL)'),
    'ES' => __('Hiszpania (Bip&Drive)'),
    'BE' => __('Belgia (Viapass)'),
    'NL' => __('Holandia (Eurovignette)'),
    'RO' => __('Rumunia (Rovinieta)'),
    'BG' => __('Bułgaria (BG-TOLL)'),
    'SI' => __('Słowenia (DarsGo)'),
    'HR' => __('Chorwacja (HAC)'),
];
?>
<div class="d-flex align-items-center mb-3">
    <h1 class="h4 mb-0"><?= h($title) ?></h1>
</div>

<?= $this->Form->create($entity, ['class' => 'card p-4']) ?>
<div class="row g-3">
    <div class="col-md-6">
        <?= $this->Form->control('vehicle_type_code', [
            'label' => __('Typ zestawu'),
            'type' => 'select',
            'options' => $typeOptions,
            'class' => 'form-select',
            'required' => true,
            'empty' => __('— wybierz —'),
        ]) ?>
        <small class="text-muted"><?= __('Zgodny z polem combination_type na pojeździe.') ?></small>
    </div>

    <div class="col-md-3">
        <?= $this->Form->control('country_code', [
            'label' => __('Kod kraju (ISO)'),
            'class' => 'form-control text-uppercase',
            'required' => true,
            'maxlength' => 2,
            'placeholder' => 'PL',
            'style' => 'text-transform:uppercase',
        ]) ?>
    </div>

    <div class="col-md-3">
        <?= $this->Form->control('is_active', [
            'label' => __('Aktywne'),
            'type' => 'checkbox',
            'class' => 'form-check-input',
            'default' => true,
        ]) ?>
    </div>

    <div class="col-md-6">
        <?= $this->Form->control('system_name', [
            'label' => __('System tolls'),
            'class' => 'form-control',
            'required' => true,
            'placeholder' => __('np. A2 AWSA / Toll Collect / MYTO CZ'),
        ]) ?>
    </div>

    <div class="col-md-6">
        <?= $this->Form->control('category_label', [
            'label' => __('Etykieta kategorii'),
            'class' => 'form-control',
            'required' => true,
            'placeholder' => __('np. „kat. 4" / „Achsklasse 5+"'),
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

<?php if (!empty($countryHints)): ?>
<div class="card mt-3">
    <div class="card-header bg-light small text-muted">
        <?= __('Podpowiedzi krajów i systemów tolls') ?>
    </div>
    <div class="card-body small">
        <div class="row row-cols-1 row-cols-md-2 g-1">
            <?php foreach ($countryHints as $code => $desc): ?>
                <div class="col">
                    <span class="badge bg-info-subtle text-info-emphasis me-1"><?= h($code) ?></span>
                    <?= h($desc) ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
<?php endif ?>

<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Contractor $contractor
 * @var \Cake\Collection\CollectionInterface|string[] $companies
 */
$this->assign('title', 'Dodaj kontrahenta');
?>

<!-- Page header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2"><?= __('Dodaj kontrahenta') ?></h1>
    <nav>
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><?= $this->Html->link('Start', '/') ?></li>
        <li class="breadcrumb-item"><?= $this->Html->link('Kontrahenci', ['action' => 'index']) ?></li>
        <li class="breadcrumb-item active" aria-current="page"><?= __('Dodaj') ?></li>
      </ol>
    </nav>
  </div>
  <div class="btn-list">
    <?= $this->Html->link('<i class="ri-list-check-2"></i> Lista kontrahentów', ['action' => 'index'], [
          'class' => 'btn btn-secondary-light btn-wave',
          'escape' => false
    ]) ?>
  </div>
</div>

<div class="row">
  <div class="col-xl-12">
    <div class="card custom-card">
      <div class="card-header">
        <div class="card-title"><?= __('Formularz kontrahenta') ?></div>
      </div>
      <div class="card-body">
        <?= $this->Form->create($contractor, ['class' => 'row g-3']) ?>

          <div class="col-md-6">
            <?= $this->Form->control('company_id', [
              'label' => 'Firma (właściciel kontrahenta)',
              'options' => $companies,
              'class' => 'form-select'
            ]) ?>
          </div>

          <div class="col-md-6">
            <?= $this->Form->control('name', [
              'label' => 'Nazwa kontrahenta',
              'class' => 'form-control',
              'required' => true
            ]) ?>
          </div>

          <div class="col-md-6">
            <?= $this->Form->control('altname', [
              'label' => 'Nazwa skrócona / alternatywna',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-3">
            <?= $this->Form->control('nip', [
              'label' => 'NIP',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-3">
            <?= $this->Form->control('regon', [
              'label' => 'REGON',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-3">
            <?= $this->Form->control('eu_vat', [
              'label' => 'EU VAT',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-3">
            <?= $this->Form->control('country', [
              'label' => 'Kraj',
              'class' => 'form-control',
              'default' => 'PL'
            ]) ?>
          </div>

          <div class="col-md-3">
            <?= $this->Form->control('postal_code', [
              'label' => 'Kod pocztowy',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-3">
            <?= $this->Form->control('city', [
              'label' => 'Miasto',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('street', [
              'label' => 'Ulica',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-2">
            <?= $this->Form->control('local_number', [
              'label' => 'Nr lokalu',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('phone', [
              'label' => 'Telefon',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('email', [
              'label' => 'Email',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-12">
            <?= $this->Form->control('notes', [
              'label' => 'Notatki',
              'class' => 'form-control',
              'type' => 'textarea',
              'rows' => 3
            ]) ?>
          </div>

          <div class="col-md-3">
            <?= $this->Form->control('is_active', [
              'label' => 'Aktywny?',
              'type' => 'checkbox',
              'class' => 'form-check-input'
            ]) ?>
          </div>

          <div class="col-12">
            <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', [
              'class' => 'btn btn-primary',
              'escapeTitle' => false, // <— kluczowa zmiana
            ]) ?>
          </div>

        <?= $this->Form->end() ?>
      </div>
    </div>
  </div>
</div>

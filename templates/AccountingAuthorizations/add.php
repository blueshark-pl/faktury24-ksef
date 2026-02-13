<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AccountingAuthorization $auth
 */
$this->assign('title', __('Integracja z systemem księgowym – dodaj token'));
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2"><?= __('Integracja z systemem księgowym') ?></h1>
    <nav><ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><?= $this->Html->link(__('Integracje'), ['controller'=>'Integrations','action'=>'index']) ?></li>
      <li class="breadcrumb-item"><?= $this->Html->link(__('System księgowy'), ['action'=>'index']) ?></li>
      <li class="breadcrumb-item active"><?= __('Dodaj token') ?></li>
    </ol></nav>
  </div>
  <div class="btn-list">
    <?= $this->Html->link('<i class="ri-arrow-go-back-line align-middle"></i> '.__('Lista tokenów'), ['action'=>'index'], [
      'escape'=>false,'class'=>'btn btn-secondary-light btn-wave'
    ]) ?>
  </div>
</div>

<?= $this->Form->create($auth, ['novalidate'=>true, 'autocomplete'=>'off']) ?>

<div class="row g-3">
  <div class="col-12 col-xl-8">
    <div class="card custom-card border-primary shadow-sm">
      <div class="card-header bg-primary text-white d-flex align-items-center">
        <i class="ri-key-line me-2 fs-5"></i>
        <div class="card-title mb-0"><?= __('Połącz z systemem księgowym – wklej token') ?></div>
      </div>
      <div class="card-body">
        <p class="text-muted mb-4">
          Wklej <strong>token autoryzacyjny</strong> wygenerowany w panelu Twojego systemu księgowego (np. wFirma, inFakt, enova365, Optima).
        </p>

        <ol class="small mb-4">
          <li>Przejdź do panelu Twojego systemu księgowego.</li>
          <li>W sekcji <strong>Integracje / API / Tokeny</strong> utwórz nowy token.</li>
          <li>Skopiuj wygenerowany token i wklej go poniżej.</li>
        </ol>

        <div class="row g-3">
          <div class="col-12">
            <?= $this->Form->control('token', [
              'label'=>false,
              'type'=>'text',
              'class'=>'form-control form-control-lg text-monospace',
              'placeholder'=>__('Wklej tutaj token autoryzacyjny z Twojego systemu księgowego'),
              'required'=>true,
              'maxlength'=>512,
              'autocomplete'=>'off',
            ]) ?>
            <div class="form-text mt-2">
              <?= __('Token zostanie zaszyfrowany i przypisany do Twojej firmy. 
                W interfejsie pokażemy wyłącznie jego końcówkę (ostatnie 4 znaki).') ?>
            </div>
          </div>
        </div>

        <!-- przekazujemy firmę -->
        <?= $this->Form->hidden('company_id', ['value' => $this->Identity->get('company_id')]) ?>
      </div>
    </div>
  </div>

  <!-- ADMIN: testowe ustawienia -->
  <div class="col-12 col-xl-4">
    <div class="card custom-card border-warning shadow-sm">
      <div class="card-header bg-warning-subtle d-flex align-items-center">
        <i class="ri-alert-line text-warning me-2 fs-5"></i>
        <div class="card-title mb-0"><?= __('Administracyjne (tylko testowo)') ?></div>
      </div>
      <div class="card-body">
        <div class="alert alert-warning small">
          <strong><?= __('Uwaga') ?>:</strong>
          <?= __('Poniższe ustawienia są dostępne wyłącznie w środowisku testowym i nie będą dostępne w produkcji.') ?>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <?= $this->Form->control('provider', [
              'label'=>__('Dostawca (opcjonalnie)'),
              'type'=>'select',
              'empty'=>__('— wybierz —'),
              'options'=>[
                'partnersc' => 'Partner S.C.',
                'wfirma' => 'wFirma',
                'infakt' => 'inFakt',
                'enova365' => 'enova365',
                'optima' => 'Comarch Optima',
                'insert' => 'InsERT (Subiekt/GT/Nexo)',
              ],
              'class'=>'form-select',
            ]) ?>
          </div>

          <div class="col-12">
            <?= $this->Form->control('environment', [
              'label'=>__('Środowisko'),
              'type'=>'select',
              'options'=>['prod'=>__('Produkcyjne'), 'test'=>__('Testowe')],
              'default'=>'prod',
              'class'=>'form-select'
            ]) ?>
          </div>

          <div class="col-12 col-md-6">
            <?= $this->Form->control('valid_from', [
              'label'=>__('Ważny od'),
              'type'=>'datetime-local',
              'empty'=>true,'class'=>'form-control'
            ]) ?>
          </div>
          <div class="col-12 col-md-6">
            <?= $this->Form->control('expires_at', [
              'label'=>__('Wygasa'),
              'type'=>'datetime-local',
              'empty'=>true,'class'=>'form-control'
            ]) ?>
          </div>

          <div class="col-12">
            <?= $this->Form->control('scopes', [
              'label'=>__('Zakres uprawnień (JSON, opcjonalnie)'),
              'type'=>'textarea', 'rows'=>3, 'class'=>'form-control',
              'placeholder'=>'{"customers:read":true,"invoices:export":true}'
            ]) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- sticky actions -->
<div class="card border-0 shadow position-sticky bottom-0 z-3 mt-3" style="background: var(--bs-body-bg);">
  <div class="card-body py-2 d-flex gap-2 justify-content-end">
    <?= $this->Form->button('<i class="ri-save-3-line align-middle me-1"></i> '.__('Zapisz token'), [
      'escapeTitle'=>false,'class'=>'btn btn-primary btn-wave'
    ]) ?>
    <?= $this->Html->link('<i class="ri-close-line align-middle me-1"></i> '.__('Anuluj'), ['action'=>'index'], [
      'escape'=>false,'class'=>'btn btn-secondary-light btn-wave'
    ]) ?>
  </div>
</div>

<?= $this->Form->end() ?>

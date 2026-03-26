<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Product $product
 * @var \Cake\Collection\CollectionInterface|string[] $companies
 * @var \Cake\Collection\CollectionInterface|string[] $units
 * @var \Cake\Collection\CollectionInterface|string[] $vats
 */
$this->assign('title', 'Dodaj produkt / usługę');
?>

<div class="container-fluid px-0">
  <div class="d-flex align-items-center gap-2 mb-3">
    <?= $this->Html->link('<i class="ri-arrow-left-line"></i>', ['action' => 'index'], ['escape' => false, 'class' => 'btn btn-sm btn-outline-secondary']) ?>
    <h4 class="mb-0">Nowy produkt / usługa</h4>
  </div>

  <?= $this->Form->create($product, ['class' => 'row g-3']) ?>

    <!-- Podstawowe -->
    <div class="col-md-8">
      <?= $this->Form->control('name', ['label' => 'Nazwa *', 'class' => 'form-control', 'required' => true]) ?>
    </div>
    <div class="col-md-4">
      <?= $this->Form->control('code', ['label' => 'Kod / symbol', 'class' => 'form-control', 'required' => false]) ?>
    </div>

    <div class="col-12">
      <?= $this->Form->control('description', ['label' => 'Opis', 'class' => 'form-control', 'type' => 'textarea', 'rows' => 2, 'required' => false]) ?>
    </div>

    <div class="col-md-3">
      <?= $this->Form->control('is_service', [
        'label' => 'Typ',
        'type' => 'select',
        'class' => 'form-select',
        'options' => [0 => 'Towar', 1 => 'Usługa'],
      ]) ?>
    </div>
    <div class="col-md-3">
      <?= $this->Form->control('unit_id', ['label' => 'Jednostka miary', 'options' => $units, 'class' => 'form-select', 'empty' => '— wybierz —']) ?>
    </div>
    <div class="col-md-3">
      <?= $this->Form->control('vat_id', ['label' => 'Stawka VAT', 'options' => $vats, 'class' => 'form-select', 'empty' => '— wybierz —']) ?>
    </div>
    <div class="col-md-3">
      <?= $this->Form->control('currency', [
        'label' => 'Waluta',
        'type' => 'select',
        'class' => 'form-select',
        'options' => ['PLN' => 'PLN', 'EUR' => 'EUR', 'USD' => 'USD', 'GBP' => 'GBP'],
        'default' => 'PLN',
      ]) ?>
    </div>

    <div class="col-md-4">
      <?= $this->Form->control('net_price', ['label' => 'Cena netto', 'type' => 'number', 'step' => '0.01', 'class' => 'form-control', 'required' => false]) ?>
    </div>
    <div class="col-md-4">
      <?= $this->Form->control('pkwiu', ['label' => 'PKWiU', 'class' => 'form-control', 'required' => false]) ?>
    </div>
    <div class="col-md-2">
      <?= $this->Form->control('gtu_code', ['label' => 'Kod GTU', 'class' => 'form-control', 'required' => false, 'placeholder' => 'np. GTU_01']) ?>
    </div>
    <div class="col-md-2">
      <?= $this->Form->control('barcode', ['label' => 'Kod kreskowy', 'class' => 'form-control', 'required' => false]) ?>
    </div>

    <!-- Zaawansowane (JPK / KSeF) -->
    <div class="col-12">
      <div class="accordion" id="acc-advanced">
        <div class="accordion-item border">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed fw-semibold" type="button"
                    data-bs-toggle="collapse" data-bs-target="#pane-advanced" aria-expanded="false">
              <i class="ri-settings-3-line me-2"></i> Zaawansowane (JPK / KSeF)
              <span class="ms-2 badge bg-secondary fw-normal" style="font-size:.75rem;">opcjonalne</span>
            </button>
          </h2>
          <div id="pane-advanced" class="accordion-collapse collapse" data-bs-parent="#acc-advanced">
            <div class="accordion-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <?= $this->Form->control('gtin', ['label' => 'GTIN (kod handlowy)', 'class' => 'form-control', 'required' => false]) ?>
                  <small class="text-muted">Globalny Numer Jednostki Handlowej (EAN-13, ISBN itp.).</small>
                </div>
                <div class="col-md-4">
                  <?= $this->Form->control('cn_code', ['label' => 'Kod CN (Nomenklatura Scalona)', 'class' => 'form-control', 'required' => false]) ?>
                  <small class="text-muted">Kod taryfy celnej UE — wymagany przy WDT/eksporcie towarów.</small>
                </div>
                <div class="col-md-4">
                  <?= $this->Form->control('pkob', ['label' => 'PKOB (klasyfikacja budowlana)', 'class' => 'form-control', 'required' => false]) ?>
                  <small class="text-muted">Polska Klasyfikacja Obiektów Budowlanych — dla usług budowlanych.</small>
                </div>
                <div class="col-md-4">
                  <?= $this->Form->control('excise_amount', [
                    'label' => 'Podatek akcyzowy',
                    'type' => 'number', 'step' => '0.01',
                    'class' => 'form-control', 'required' => false,
                    'placeholder' => '0.00',
                  ]) ?>
                  <small class="text-muted">Kwota podatku akcyzowego wliczonego w cenę (pole P_11Ak w FA(3)).</small>
                </div>
                <div class="col-md-8">
                  <?= $this->Form->control('procedure_marking', [
                    'label' => 'Oznaczenie procedury',
                    'type' => 'select',
                    'class' => 'form-select',
                    'options' => [
                      '' => '— brak —',
                      'WSTO_EE'        => 'WSTO_EE — sprzedaż na odległość (UE)',
                      'IED'            => 'IED — dostawa przez interfejs elektroniczny',
                      'TT_D'           => 'TT_D — transakcja trójstronna uproszczona',
                      'I_42'           => 'I_42 — WDT po imporcie (proc. celna 42)',
                      'I_63'           => 'I_63 — WDT po imporcie (proc. celna 63)',
                      'B_SPV'          => 'B_SPV — transfer bonu jednego przeznaczenia',
                      'B_SPV_DOSTAWA'  => 'B_SPV_DOSTAWA — dostawa dot. bonu jedn. przezn.',
                      'B_MPV_PROWIZJA' => 'B_MPV_PROWIZJA — prowizja dot. bonu różn. przezn.',
                    ],
                    'empty' => false,
                  ]) ?>
                  <small class="text-muted">Specjalne oznaczenia JPK_VAT wymagane dla wybranych procedur sprzedaży.</small>
                </div>
                <div class="col-md-4 d-flex align-items-center">
                  <div class="form-check mt-2">
                    <?= $this->Form->control('is_attachment15', [
                      'type' => 'checkbox',
                      'label' => 'Towar/usługa z zał. 15 (obowiązkowy MPP)',
                      'class' => 'form-check-input',
                    ]) ?>
                  </div>
                </div>
                <div class="col-12 d-flex gap-3 align-items-center mt-2">
                  <div class="form-check">
                    <?= $this->Form->control('is_active', ['type' => 'checkbox', 'label' => 'Aktywny', 'class' => 'form-check-input']) ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex gap-2">
      <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz produkt', ['type' => 'submit', 'escape' => false, 'class' => 'btn btn-primary']) ?>
      <?= $this->Html->link('Anuluj', ['action' => 'index'], ['class' => 'btn btn-outline-secondary']) ?>
    </div>

  <?= $this->Form->end() ?>
</div>

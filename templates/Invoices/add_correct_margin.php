<?php
/**
    <!-- Karty ustawień przeniesione ponad Create Invoice -->
    <div class="card custom-card">
 * @var \App\Model\Entity\Invoice $invoice
 * @var array $vats id => "Nazwa (x%)"
 * @var array $vatRatesMap id => rate
 * @var array|null $recentContractors [['id'=>..,'label'=>..,'name'=>..,'nip'=>..,'street'=>..,'zip'=>..,'city'=>..,'country'=>..,'email'=>..,'phone'=>..], ...]
 */
$this->assign('title', 'Create Invoice');
$_identity = $this->request->getAttribute('identity');
$_role = strtolower((string)($_identity?->get('role') ?? ''));
$_isAdmin = (bool)($_identity?->get('is_admin') ?? false);
// Termin płatności — przy edycji oblicz rzeczywistą liczbę dni
$__paymentDateVal = '';
$__dueDaysPreset  = 7;
$__dueDaysCustom  = false;
if (!empty($invoice->paymentdate)) {
    $pd = $invoice->paymentdate instanceof \DateTimeInterface
        ? $invoice->paymentdate
        : new \DateTime((string)$invoice->paymentdate);
    $__paymentDateVal = $pd->format('Y-m-d');
    $id_ = !empty($invoice->date)
        ? ($invoice->date instanceof \DateTimeInterface ? $invoice->date : new \DateTime((string)$invoice->date))
        : new \DateTime();
    $diff = (int)$id_->diff($pd)->days * ($pd >= $id_ ? 1 : -1);
    if (in_array($diff, [0, 7, 14, 30, 60, 90], true)) {
        $__dueDaysPreset = $diff;
    } else {
        $__dueDaysCustom = true;
    }
}
$__ksefModeEnabled = false;

// pre-render VAT select do klonowania w wierszach
$vatSelectHtml = '<select class="form-select item-vatcode" name="items[0][vat_code_id]" required>';
foreach ($vats as $id => $label) {
  $vatSelectHtml .= '<option value="' . h($id) . '">' . h($label) . '</option>';
}
$vatSelectHtml .= '</select>';

// pre-render GTU select do klonowania (opcje możesz podać z kontrolera jako $gtuOptions)
$gtuOptions = $gtuOptions ?? [
  '' => 'brak',
  'GTU_01' => 'GTU_01 – napoje alkoholowe',
  'GTU_02' => 'GTU_02 – paliwa',
  'GTU_03' => 'GTU_03 – oleje opałowe',
  'GTU_04' => 'GTU_04 – wyroby tytoniowe',
  'GTU_05' => 'GTU_05 – odpady',
  'GTU_06' => 'GTU_06 – urządzenia elektroniczne',
  'GTU_07' => 'GTU_07 – pojazdy/części',
  'GTU_08' => 'GTU_08 – metale szlachetne',
  'GTU_09' => 'GTU_09 – leki/wyroby med.',
  'GTU_10' => 'GTU_10 – budowlanka',
  'GTU_11' => 'GTU_11 – drukowane nośniki',
  'GTU_12' => 'GTU_12 – usługi niematerialne',
  'GTU_13' => 'GTU_13 – transport i gospodarka magazynowa',
];
$gtuSelectHtml = '<select class="form-select item-gtu" name="items[0][gtu_code]">';
foreach ($gtuOptions as $val => $label) {
  $gtuSelectHtml .= '<option value="'.h($val).'">'.h($label).'</option>';
}
$gtuSelectHtml .= '</select>';
?>

<!-- Flag Icons CSS for SVG country flags in currency selector -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons/css/flag-icons.min.css">

<?= $this->Form->create($invoice, ['class' => 'needs-validation', 'novalidate' => true]) ?>
<?= $this->Form->hidden('kind', ['value' => 'correction']) ?>
<?= $this->Form->hidden('parent_id', ['value' => isset($original) ? ($original->id ?? null) : null]) ?>

<!-- Start::page-header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Korekta faktury marża</h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item" aria-current="page"><a href="javascript:void(0);">Faktury</a></li>
      <li class="breadcrumb-item active" aria-current="page">Korekta (marża)</li>
    </ol>
  </div>
  <div class="btn-list">
    <button type="button" id="btn-export" class="btn btn-secondary-light btn-wave">
      <i class="ri-upload-cloud-line align-middle me-1"></i> Export Draft
    </button>
  </div>
</div>
<!-- End::page-header -->
<div class="row">
<!-- KARTY USTAWIEŃ: Podstawowe / Księgowe / Zaawansowane (przeniesione ponad Create Invoice) -->
<div class="col-xxl-12">
    <div class="card custom-card">
      <div class="card-header d-flex align-items-center justify-content-between pe-2">
        <ul class="nav nav-tabs card-header-tabs flex-grow-1" id="invTabs" role="tablist">
          <li class="nav-item"><button class="nav-link active" id="tab-basic" data-bs-toggle="tab" data-bs-target="#pane-basic" type="button" role="tab">Podstawowe</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-accounting" data-bs-toggle="tab" data-bs-target="#pane-accounting" type="button" role="tab">Księgowe</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-adv" data-bs-toggle="tab" data-bs-target="#pane-adv" type="button" role="tab">Zaawansowane</button></li>
        </ul>
        <?php //if ($this->Identity->hasRole('admin')): ?>
        <div class="dropdown ms-2 flex-shrink-0">
          <button class="btn btn-sm btn-outline-secondary" type="button" id="inv-extra-tabs-btn" data-bs-toggle="dropdown" aria-expanded="false" title="Dodatkowe opcje">
            <i class="ri-settings-3-line"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="inv-extra-tabs-btn">
            <li><span class="dropdown-header text-muted small px-3 py-1">Dodatkowe zakładki</span></li>
            <li>
              <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-extra-tab="pane-annotations">
                <i class="ri-file-text-line"></i> Adnotacje
              </button>
            </li>
            <li>
              <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-extra-tab="pane-fa3ext">
                <i class="ri-government-line"></i> KSeF FA(3)
              </button>
            </li>
          </ul>
        </div>
        <?php //endif; ?>
        <script>
        $(function(){
          var $gearBtn = $("#inv-extra-tabs-btn");
          $(document).on("click", "[data-extra-tab]", function(){
            var paneId = $(this).data("extra-tab");
            $("#invTabs .nav-link").removeClass("active").attr("aria-selected", "false");
            $("#invTabs .nav-link").each(function(){ $(this).attr("tabindex", "-1"); });
            $(".tab-content > .tab-pane").removeClass("show active");
            $("#" + paneId).addClass("show active");
            $gearBtn.removeClass("btn-outline-secondary").addClass("btn-secondary");
          });
          $("#invTabs").on("click", ".nav-link", function(){
            $gearBtn.removeClass("btn-secondary").addClass("btn-outline-secondary");
            $("[data-extra-tab]").each(function(){ $("#" + $(this).data("extra-tab")).removeClass("show active"); });
          });
        });
        </script>
      </div>

      <div class="card-body tab-content">
        <div class="mb-3">
            <div class="invoice-pills d-flex flex-wrap gap-2">
                <div class="pill">
                  <span class="pill-label">Zakup</span>
                  <span class="pill-value" id="pill-purchase">0,00</span>
                </div>
                <div class="pill">
                  <span class="pill-label">Marża</span>
                  <span class="pill-value" id="pill-margin">0,00</span>
                </div>
                <div class="pill pill-accent">
                  <span class="pill-label">Sprzedaż</span>
                  <span class="pill-value" id="pill-sales">0,00</span>
                </div>
            </div>
            <div class="mt-1 small text-muted">
              Marża = Sprzedaż brutto (pozycje) − Zakup (suma kosztów). VAT od marży liczony jest wyłącznie od marży i nie jest prezentowany na fakturze.
            </div>
        </div>

        <!-- PODSTAWOWE -->
        <div class="tab-pane fade show active" id="pane-basic" role="tabpanel" aria-labelledby="tab-basic">
          <div class="row g-3">
            <div class="col-lg-4">
              <?= $this->Form->control('fullnumber', [
                'label' => 'Numer faktury', 'class' => 'form-control', 'placeholder' => 'auto',
                'id' => 'invoice-number'
              ]) ?>
              <small class="text-muted" id="invoice-number-hint" style="display: none;">
                <i class="ri-information-line"></i> Numer faktury: <span id="invoice-number-suggestion"></span>
              </small>
            </div>
            <div class="col-lg-4">
              <?= $this->Form->control('series', [
                'label' => 'Seria',
                'type'  => 'select',
                'empty' => true,
                'class' => 'form-select',
                'id'    => 'series-select',
            // opcjonalnie: ustaw bieżącą wartość jeśli istnieje
            'value' => $invoice->series ?? null,
            ]) ?>
            <?= $this->Form->hidden('invoice_series_id', ['id' => 'invoice-series-id-hidden', 'value' => $invoice->invoice_series_id ?? null]) ?>
            </div>

            <div class="col-lg-2">
            <?= $this->Form->control('date', [
              'type' => 'date', 'label' => 'Data wystawienia', 'class' => 'form-control', 'id' => 'issue-date', 'required' => true,
              'value' => (!empty($isEdit) && !empty($invoice->date)) ? $invoice->date->format('Y-m-d') : date('Y-m-d')
            ]) ?>
            </div>

            <div class="col-lg-2">
            <?= $this->Form->control('sold_date', [
              'type' => 'date', 'label' => 'Data sprzedaży', 'class' => 'form-control', 'id' => 'sold-date',
              'value' => (!empty($isEdit) && !empty($invoice->sold_date)) ? $invoice->sold_date->format('Y-m-d') : (!empty($original->sold_date) ? $original->sold_date->format('Y-m-d') : (!empty($original->date) ? $original->date->format('Y-m-d') : date('Y-m-d')))
            ]) ?>
            </div>

            
<div class="row g-2">
            <div class="col-lg-2">
            <?= $this->Form->control('paymentmethod', [
              'label' => 'Metoda płatności', 'type' => 'select',
              'options' => ['transfer' => 'Przelew', 'cash' => 'Gotówka', 'card' => 'Karta'],
              'class' => 'form-select', 'value' => 'transfer'
            ]) ?>
            </div>
            
              <div class="col-lg-2">
                <?= $this->Form->control('alreadypaid', [
                  'label' => 'Zapłacono (kwota)', 'type' => 'number', 'step' => '0.01', 'class' => 'form-control', 'value' => $invoice->alreadypaid ?? 0
                ]) ?>
              </div>
              
              <div class="col-lg-2 d-flex align-items-end">
                <div class="form-check">
                  <?php $__isPaid = (($invoice->paymentstate ?? '') === 'paid'); ?>
                  <input class="form-check-input" type="checkbox" value="1" id="is-paid-check"<?= $__isPaid ? ' checked' : '' ?>>
                  <label class="form-check-label" for="is-paid-check">Oznacz jako opłacone</label>
                </div>
              </div>
              <div class="col-lg-2">
                <div id="paid-at-group" style="display:<?= $__isPaid ? '' : 'none' ?>;">
                  <?= $this->Form->control('paid_at', ['type' => 'date', 'label' => 'Data zapłaty', 'class' => 'form-control', 'value' => $invoice->paid_at?->i18nFormat('yyyy-MM-dd') ?? '']) ?>
                </div>
              </div>
              
            </div>
              
            <div class="col-lg-6">
              <label class="form-label mb-1">Termin płatności</label>
              <div class="border rounded p-2">
                <div class="row g-2 align-items-center" id="due-combined">
                  <div class="col-7">
                    <select id="due-days-preset" class="form-select" aria-label="Termin płatności — preset dni">
                      <?php foreach ([0,7,14,30,60,90] as $d): ?>
                        <option value="<?= $d ?>"<?= (!$__dueDaysCustom && $d === $__dueDaysPreset) ? ' selected' : '' ?>><?= $d ?> dni</option>
                      <?php endforeach; ?>
                      <option value="_custom"<?= $__dueDaysCustom ? ' selected' : '' ?>>Inna liczba…</option>
                    </select>
                  </div>
                  <div class="col-5">
                    <div class="input-group">
                      <input type="date" id="payment-date" name="paymentdate" class="form-control" value="<?= h($__paymentDateVal) ?>">
                      <span class="input-group-text"><i class="ri-calendar-line"></i></span>
                    </div>
                  </div>
                </div>
                <small class="text-muted d-block mt-2">
                  Obliczony termin: <span id="due-preview" class="fw-medium">—</span>
                </small>
              </div>
            </div>
          </div>
        </div>

        <!-- KSIĘGOWE -->
        <div class="tab-pane fade" id="pane-accounting" role="tabpanel" aria-labelledby="tab-accounting">
          <div class="vstack gap-3">
            <!-- Usunięto FP/MPP dla procedury marży -->

            <!-- Pola korekty -->
            <div class="row mt-1">
              <div class="col-12 col-lg-6">
                <?= $this->Form->control('correction_type', [
                  'label' => 'Skutek korekty w ewidencji VAT (TypKorekty)',
                  'type' => 'select',
                  'options' => [
                    '1' => '1 — w dacie ujęcia faktury pierwotnej (błąd pierwotny, korygujemy wstecz)',
                    '2' => '2 — w dacie wystawienia faktury korygującej (rabat, uzgodnienie — bieżący okres)',
                    '3' => '3 — w dacie innej / różne daty dla różnych pozycji',
                  ],
                  'empty' => '— Wybierz skutek —',
                  'id' => 'correction-type',
                  'class' => 'form-select',
                  'value' => in_array((string)($invoice->correction_type ?? ''), ['1','2','3']) ? $invoice->correction_type : ''
                ]) ?>
                <small class="text-muted">Wymagane przez KSeF (FA(3)). Typ 1: wina sprzedawcy — cofasz do okresu pierwotnego. Typ 2: uzgodnienie z nabywcą — rozliczasz w bieżącym okresie.</small>
              </div>
              <div class="col-12">
                <?= $this->Form->control('correction_reason', [
                  'label' => 'Powód korekty',
                  'type' => 'textarea',
                  'rows' => 3,
                  'class' => 'form-control',
                  'placeholder' => 'Krótko opisz przyczynę korekty…',
                  'required' => true
                ]) ?>
              </div>
            </div>

            <!-- Procedura marży: typy -->
            <div class="row mt-1">
              <div class="col-12 col-lg-5">
                <?= $this->Form->control('margin_type', [
                  'label' => 'Procedura marży',
                  'type' => 'select',
                  'options' => [
                    'used_goods' => 'Towary używane',
                    'art' => 'Dzieła sztuki',
                    'collectibles' => 'Przedmioty kolekcjonerskie',
                    'travel' => 'Usługi turystyki'
                  ],
                  'empty' => '— wybierz —',
                  'required' => true,
                  'id' => 'margin-type',
                  'class' => 'form-select'
                ]) ?>
              </div>
            </div>
            <?= $this->Form->hidden('margin_vat_rate', ['value' => 23, 'id' => 'margin-vat-rate']) ?>
            <script>
            // Utrzymaj poprawne wyliczenie VAT od marży (ukryty margin_vat_rate)
            (function(){
              $(document).on('change', '#margin-type', function(){
                var t = $(this).val()||'';
                var rate = (t === 'art') ? 8 : 23; // domyślnie 23%, dla sztuki 8%
                $('#margin-vat-rate').val(rate).trigger('change');
            });})();
            </script>
          </div>
        </div>

        <!-- ZAAWANSOWANE -->
        <div class="tab-pane fade" id="pane-adv" role="tabpanel" aria-labelledby="tab-adv">
          <div class="row g-2">
            <div class="col-2 d-flex align-items-center gap-2">
              <?= $this->Form->control('currency', [
                'label' => 'Waluta', 'class' => 'form-select', 'id' => 'currency', 'value' => 'PLN',
                'options' => ['PLN'=>'PLN','EUR'=>'EUR','USD'=>'USD','GBP'=>'GBP','CZK'=>'CZK']
              ]) ?>
              <button type="button" class="btn btn-link p-0 align-baseline" id="currency-help" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="left"
                title="Waluta i kurs"
                data-bs-content="
                  <div class='small text-start'>
                    <p>Po wyborze waluty obcej możesz podać kurs pomocniczy do przeliczeń <em>poglądowo</em> — nie wpływa on na poprawność księgowania.</p>
                    <p>Na wydruku pojawi się automatycznie średni kurs NBP z ostatniego dnia roboczego poprzedzającego datę wystawienia/sprzedaży (wg typu transakcji).</p>
                  </div>
                ">
                <i class="ri-question-line"></i>
              </button>
            </div>

          </div>
          <div class="row">
            <div class="col-lg-3 mt-2">
              <?php /* Język faktury – zawsze polski, pole ukryte
              <?= $this->Form->control('lang', [
                'label' => 'Język faktury', 'type' => 'select', 'class' => 'form-select',
                'options' => ['pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch', 'cs' => 'Čeština'], 'value' => 'pl'
              ]) ?>
              */ ?>
              <?= $this->Form->hidden('lang', ['value' => 'pl']) ?>
              <small class="text-muted d-block mt-1">
                &nbsp;
              </small>
            </div>
            <div class="col-lg-6">
              <label class="form-label">Rachunek na fakturze</label>
              <select id="bank-account-select" class="form-select" data-placeholder="Wybierz rachunek lub wyszukaj"></select>
              <?= $this->Form->hidden('invoice_company_detail.bank_account', ['id' => 'bank-account-hidden']) ?>
              <?= $this->Form->hidden('company_bank_account_id', ['id' => 'bank-account-id-hidden']) ?>
              <small class="text-muted d-block mt-1">
                Rachunki firmy dodasz w <em>Ustawienia → Moja firma → Rachunki bankowe</em> lub bezpośrednio tutaj przyciskiem „Dodaj rachunek”.
              </small>
            </div>
            <div class="col-lg-3">
              <?= $this->Form->control('issuer', [
                'label' => 'Wystawca (issuer)', 'class' => 'form-control',
                'placeholder' => 'np. Jan Kowalski'
              ]) ?>
            </div>
          </div>
        </div>

        <!-- ADNOTACJE -->
        <?= $this->element('Invoices/tab_annotations') ?>

        <!-- IDENTYFIKATORY MIĘDZYNARODOWE -->
        <?= $this->element('Invoices/tab_identifiers') ?>

        <!-- KSeF FA(3) ROZSZERZONY -->
        <?= $this->element('Invoices/tab_fa3_extended') ?>

      </div>
    </div>
  </div>


  <div class="col-xxl-12">
    <div class="card custom-card">
      <div class="card-header d-md-flex d-block">
        <div class="card-title">Korekta faktury – marża</div>
        <div class="ms-auto mt-md-0 mt-2">
          <?php if ($_isAdmin || $_role !== 'user'): ?>
            <?= $this->Form->button('Zapisz jako roboczą', [
              'class' => 'btn btn-sm btn-outline-secondary', 'name' => 'save_draft'
            ]) ?>
            <?php if ($__ksefModeEnabled): ?>
              <?= $this->Form->button('Zapisz i wyślij do KSeF <i class="ri-send-plane-line ms-1 align-middle d-inline-block"></i>', [
                'class' => 'btn btn-sm btn-primary ms-1', 'escapeTitle' => false, 'name' => 'save_and_send_ksef'
              ]) ?>
            <?php else: ?>
              <?= $this->Form->button('Zapisz i wystaw', [
                'class' => 'btn btn-sm btn-primary ms-1', 'name' => 'save_only'
              ]) ?>
            <?php endif; ?>
          <?php else: ?>
            <?= $this->Form->button('Zapisz jako roboczą', ['class' => 'btn btn-sm btn-primary ms-1', 'name' => 'save_draft']) ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="card-body">
        <div class="p-3 bg-light border rounded mb-3">
          <?php if (isset($original)): ?>
          <div class="alert alert-info mb-3">
            <i class="ri-information-line me-1"></i>
            <?php $__corrDoc = $correctedOriginal ?? $original; ?>
            Korekta do faktury pierwotnej: <strong><?= h($__corrDoc->fullnumber ?? $__corrDoc->id) ?></strong>
          </div>
          <?php endif; ?>
          <div class="row g-3 align-items-start">
            <div class="col-xl-8">
              <label class="form-label mb-1">Nabywca</label>

              <!-- Nabywca: Select2 -->
              <div class="contractor-picker mb-2">
                <select id="contractor-select" class="form-select" data-placeholder="Wpisz nazwę kontrahenta lub NIP"></select>
                <?= $this->Form->control('contractor_id', ['type' => 'hidden', 'id' => 'contractor-id-input']) ?>
              </div>
              <div class="ctr-toolbar gap-1 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openCatalog()">
                        <i class="ri-search-line me-1"></i> Szukaj w katalogu
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#contractor-create-modal">
                        <i class="ri-user-add-line me-1"></i> Nowy kontrahent
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#gus-modal">
                        <i class="ri-database-2-line me-1"></i> GUS z NIP
                    </button>
                    <div class="ms-auto d-flex align-items-center small text-muted">
                        <i class="ri-shield-check-line me-1"></i> dane wypełnisz w 2 kliknięcia
                    </div>
                </div>

                <!-- ODBIORCA (opcjonalny) -->
                <div class="mt-2" id="recipient-add-wrap"<?= !empty($invoice->invoice_recipient->name) ? ' style="display:none;"' : '' ?>>
                  <button type="button" class="btn btn-sm btn-outline-primary" id="recipient-add-btn"><i class="ri-user-add-line me-1"></i> Dodaj odbiorcę (inny podmiot)</button>
                </div>
                <div id="recipient-snapshot" class="mt-3 border rounded p-2"<?= !empty($invoice->invoice_recipient->name) ? '' : ' style="display:none;"' ?>>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">Odbiorca</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="recipient-edit-btn"><i class="ri-edit-2-line"></i> Edytuj</button>
                </div>
                <div class="row g-2">
                    <div class="col-12 col-md-8">
                    <?= $this->Form->control('invoice_recipient.name', ['label' => 'Nazwa odbiorcy', 'class' => 'form-control']) ?>
                    </div>
                    <div class="col-12 col-md-4">
                    <?= $this->Form->control('invoice_recipient.nip', ['label' => 'NIP', 'class' => 'form-control']) ?>
                    </div>
                    <div class="col-8"><?= $this->Form->control('invoice_recipient.street', ['label' => 'Ulica', 'class' => 'form-control']) ?></div>
                    <div class="col-4"><?= $this->Form->control('invoice_recipient.zip', ['label' => 'Kod', 'class' => 'form-control']) ?></div>
                    <div class="col-6"><?= $this->Form->control('invoice_recipient.city', ['label' => 'Miasto', 'class' => 'form-control']) ?></div>
                    <div class="col-6"><?= $this->Form->control('invoice_recipient.country', ['label' => 'Kraj', 'class' => 'form-control', 'value' => 'PL']) ?></div>
                    <div class="col-6"><?= $this->Form->control('invoice_recipient.email', ['label' => 'Email', 'class' => 'form-control']) ?></div>
                    <div class="col-6"><?= $this->Form->control('invoice_recipient.phone', ['label' => 'Telefon', 'class' => 'form-control']) ?></div>
                    <div class="col-12">
                        <?= $this->Form->control('invoice_recipient.rola', [
                            'label'   => 'Rola podmiotu (KSeF)',
                            'type'    => 'select',
                            'class'   => 'form-select',
                            'options' => [
                                1  => '1 – Faktor',
                                2  => '2 – Odbiorca',
                                3  => '3 – Podmiot pierwotny',
                                4  => '4 – Dodatkowy nabywca',
                                5  => '5 – Wystawca faktury',
                                6  => '6 – Dokonujący płatności',
                                7  => '7 – JST – wystawca',
                                8  => '8 – JST – odbiorca',
                                9  => '9 – Członek grupy VAT – wystawca',
                                10 => '10 – Członek grupy VAT – odbiorca',
                                11 => '11 – Pracownik',
                            ],
                            'default' => 2,
                            'value'   => $invoice->invoice_recipient->rola ?? 2,
                        ]) ?>
                    </div>
                </div>
                </div>

              <!-- Snapshot kontrahenta (invoice_contractors) — UKRYTY NA START -->
              <div id="contractor-snapshot" class="mt-2" style="display:none;">
                <div class="row g-2">
                  <div class="col-12 col-md-8">
                    <?= $this->Form->control('invoice_contractor.name', ['label' => 'Nazwa', 'class' => 'form-control', 'required' => true]) ?>
                  </div>
                  <div class="col-12 col-md-4">
                    <?= $this->Form->control('invoice_contractor.nip', ['label' => 'NIP', 'class' => 'form-control']) ?>
                  </div>
                  <div class="col-8"><?= $this->Form->control('invoice_contractor.street', ['label' => 'Ulica', 'class' => 'form-control']) ?></div>
                  <div class="col-4"><?= $this->Form->control('invoice_contractor.zip', ['label' => 'Kod', 'class' => 'form-control']) ?></div>
                  <div class="col-6"><?= $this->Form->control('invoice_contractor.city', ['label' => 'Miasto', 'class' => 'form-control']) ?></div>
                  <div class="col-6"><?= $this->element('Invoices/contractor_country_select', ['value' => $invoice->invoice_contractor->country ?? 'PL']) ?></div>
                  <div class="col-6"><?= $this->Form->control('invoice_contractor.email', ['label' => 'Email', 'class' => 'form-control']) ?></div>
                  <div class="col-6"><?= $this->Form->control('invoice_contractor.phone', ['label' => 'Telefon', 'class' => 'form-control']) ?></div>

                  <!-- Identyfikatory UE / zagraniczne nabywcy (flagi + blokady, wspólny element) -->
                  <?= $this->element('Invoices/intl_ids_section', ['cc' => $invoice->invoice_contractor ?? null]) ?>
                </div>

                <!-- Checkbox: zapisz do katalogu + popover info -->
                <div class="mt-2 d-flex align-items-center gap-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="save-to-catalog" name="save_to_catalog">
                    <label class="form-check-label" for="save-to-catalog">Zapisz zmiany do katalogu kontrahentów</label>
                  </div>

                  <button
                    type="button"
                    class="btn btn-link p-0 align-baseline text-decoration-none"
                    id="catalog-help"
                    data-bs-toggle="popover"
                    data-bs-placement="right"
                    title="Katalog kontrahentów — jak działa?"
                    data-bs-html="true"
                    data-bs-content="
                      <div class='small text-start'>
                        <p><strong>Katalog kontrahentów</strong> służy przyspieszeniu wystawiania faktur i innych dokumentów księgowych.</p>
                        <ul class='mb-2 ps-3'>
                          <li>Zamiast ręcznie wpisywać dane — wybierasz z katalogu.</li>
                          <li>Wyszukiwanie: <em>NIP</em>, fragment/cała <em>nazwa</em>.</li>
                          <li>Dodawanie/edycja/usuwanie w: <em>CRM → Kontrahenci</em>.</li>
                          <li>Możesz też dodać podczas wystawiania faktury — zaznaczając tę opcję.</li>
                        </ul>
                        <p>Dodatkowe korzyści:</p>
                        <ul class='mb-0 ps-3'>
                          <li>Możliwość zdefiniowania e-maila do wysyłki faktur i przypomnień.</li>
                          <li>Podgląd historii faktur i płatności danego kontrahenta.</li>
                        </ul>
                      </div>
                    ">
                    <i class="ri-question-line"></i><span class="ms-1">Co to daje?</span>
                  </button>

                  <small id="save-to-catalog-hint" class="text-success d-none">
                    <i class="ri-check-line"></i> Zmiany zostaną zapisane w katalogu
                  </small>
                </div>
                <div class="form-check mt-1">
                  <input class="form-check-input" type="checkbox" id="auto-send" name="auto_send" value="1"<?= !empty($invoice->auto_send) ? ' checked' : '' ?>>
                  <label class="form-check-label" for="auto-send">Automatyczna wysyłka na e-mail nabywcy</label>
                  <button type="button" class="btn btn-link p-0 align-baseline" id="autosend-help" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
                    title="Automatyczna wysyłka"
                    data-bs-content="
                      <div class='small text-start'>
                        Jeśli zaznaczysz tę opcję, dokument trafi do kolejki wysyłki. Wysyłka obejmuje tylko dokumenty, które nie zostały wcześniej wysłane ręcznie.
                      </div>
                    ">
                    <i class="ri-question-line"></i>
                  </button>
                </div>
              </div>

        				<?php
        				// Źródło prefillu: faktura pierwotna (przy tworzeniu korekty) lub sama korekta (przy edycji roboczej).
        				$__prefillSrc = !empty($isEdit) ? ($invoice ?? null) : (isset($original) ? $original : null);
        				if (!empty($__prefillSrc)):
        				?>
                <?php
                  // Normalize source contractor to plain array for JS
                  $origCtrArr = null;
                  if (!empty($__prefillSrc->invoice_contractor)) {
                    $c = $__prefillSrc->invoice_contractor;
                    $origCtrArr = [
                      'name' => (string)($c->name ?? ''),
                      'nip' => (string)($c->nip ?? ''),
                      'street' => (string)($c->street ?? ''),
                      'zip' => (string)($c->zip ?? ''),
                      'city' => (string)($c->city ?? ''),
                      'country' => (string)($c->country ?? 'PL'),
                      'email' => (string)($c->email ?? ''),
                      'phone' => (string)($c->phone ?? ''),
                      'vat_prefix' => (string)($c->vat_prefix ?? ''),
                      'vat_eu' => (string)($c->vat_eu ?? ''),
                      'eori' => (string)($c->eori ?? ''),
                      'tax_id_other' => (string)($c->tax_id_other ?? ''),
                      'tax_id_other_country' => (string)($c->tax_id_other_country ?? ''),
                    ];
                  }
                  // Normalize original contents to plain arrays for JS
                  $origItemsArr = [];
                  if (!empty($__prefillSrc->invoice_contents)) {
                    foreach ($__prefillSrc->invoice_contents as $it) {
                      $origItemsArr[] = [
                        'product_id' => isset($it->product_id) ? (string)$it->product_id : null,
                        'code'       => isset($it->product_code) ? (string)$it->product_code : null,
                        'name'       => (string)($it->name ?? ''),
                        'quantity'   => (float)($it->quantity ?? 1),
                        'unit'       => (string)($it->unit ?? 'szt.'),
                        // Prefer stored brutto sum if present; otherwise fall back to price * qty
                        'brutto'     => isset($it->brutto) ? (float)$it->brutto : ((float)($it->price ?? 0) * (float)($it->quantity ?? 1)),
                        // Purchase gross (internal) if stored in custom field
                        'purchase_price' => isset($it->purchase_price) ? (float)$it->purchase_price : 0.0,
                      ];
                    }
                  }
                ?>
                <script>
                // Expose a prefill function to run after rows/select2 init
                window.applyOriginalPrefill = function(origCtr, items){
                  // Prefill contractor from original snapshot
                  if (origCtr) {
                    $('#contractor-snapshot').show();
                    $('#contractor-id-input').val(<?= json_encode($__prefillSrc->contractor_id ?? null) ?>);
                    $('[name="invoice_contractor[name]"]').val(origCtr.name||'');
                    $('[name="invoice_contractor[nip]"]').val(origCtr.nip||'');
                    $('[name="invoice_contractor[street]"]').val(origCtr.street||'');
                    $('[name="invoice_contractor[zip]"]').val(origCtr.zip||'');
                    $('[name="invoice_contractor[city]"]').val(origCtr.city||'');
                    $('[name="invoice_contractor[country]"]').val(origCtr.country||'PL');
                    $('[name="invoice_contractor[email]"]').val(origCtr.email||'');
                    $('[name="invoice_contractor[phone]"]').val(origCtr.phone||'');
                    // Kraj + identyfikatory UE — pewne ustawienie (Select2 kraju + sekcja corr-intl).
                    (function(){
                      var country = (origCtr.country ? String(origCtr.country).toUpperCase() : '');
                      if (!country) {
                        var _vp = String(origCtr.vat_prefix||'').toUpperCase();
                        if (_vp && _vp !== 'NONE') { country = (_vp === 'EL' ? 'GR' : _vp); }
                        else if (origCtr.tax_id_other_country) { country = String(origCtr.tax_id_other_country).toUpperCase(); }
                      }
                      function applyCountry(){
                        var $c2 = $('[name="invoice_contractor[country]"]');
                        if (!country || !$c2.length) return;
                        $c2.val(country);
                        try { $c2.trigger('change'); } catch(e){}
                        try { $c2.trigger('change.select2'); } catch(e){} // wymuś re-render tekstu Select2
                      }
                      applyCountry(); setTimeout(applyCountry, 250); setTimeout(applyCountry, 700);
                      // Identyfikatory UE — przez helper elementu intl_ids_section (hidden + widgety flag).
                      (function tryApply(tries){
                        if (typeof window.applyIntlIds === 'function') { window.applyIntlIds(origCtr); return; }
                        if ((tries||0) < 40) setTimeout(function(){ tryApply((tries||0)+1); }, 150);
                      })(0);
                    })();
                  }

                  // Prefill items for margin correction: name, quantity, sale gross (price), purchase gross
                  var $tbody = $('#items-body');
                  function addItemRow(idx, it){
                    // Trigger add-item to create a new row when idx>0
                    if (idx > 0) { $('#btn-add-item').trigger('click'); }
                    // pick the nth item row that contains a product select
                    var $rows = $tbody.find('tr').filter(function(){ return $(this).find('.item-product-select').length; });
                    var $tr = $rows.eq(idx);
                    // Ensure we target item row (skip helper rows)
                    var $sel = $tr.find('.item-product-select');
                    if (!$sel.length) { $tr = $tbody.find('tr').filter(function(){ return $(this).find('.item-product-select').length; }).eq(idx); }
                    // Ensure Select2 initialized before setting option
                    try { initProductSelectForRow($tr); } catch(_) {}
                    // Fill fields
                    var name = it.name || it.product_name || '';
                    $tr.find('.item-name-hidden').val(name);
                    // Inject option into select2 with text label
                    try {
                      var value = it.product_id || name || '';
                      var label = name || (it.code ? (it.code+' - '+(it.name||'')) : (it.text || name));
                      // Ensure Select2 has the option and select it
                      $sel.find('option[value="'+value+'"]').remove();
                      var opt = new Option(label, value, false, false);
                      $sel.append(opt);
                      $sel.val(value).trigger('change');
                    } catch(_){}
                    var q = (parseFloat(it.quantity)||1);
                    var lineGross = parseFloat(it.brutto||it.gross||it.price||0) || 0;
                    var unitSaleGross = q > 0 ? (lineGross / q) : lineGross;
                    var linePurchaseGross = parseFloat(it.purchase_price||it.buy_gross||0) || 0;
                    var unitPurchaseGross = q > 0 ? (linePurchaseGross / q) : linePurchaseGross;
                    // Assign by class for UI and by exact name for POST payload
                    $tr.find('.item-qty').val(q).trigger('input');
                    $tr.find('.item-amount').val(unitSaleGross.toFixed(2)).trigger('input');
                    $tr.find('.item-purchase').val(unitPurchaseGross.toFixed(2)).trigger('input');
                    // Ensure underlying inputs named items[idx][...] are set
                    $tr.find('[name="items['+idx+'][quantity]"]').val(q);
                    $tr.find('[name="items['+idx+'][price]"]').val(unitSaleGross.toFixed(2));
                    $tr.find('[name="items['+idx+'][purchase_price]"]').val(unitPurchaseGross.toFixed(2));
                    $tr.find('.item-pkwiu').val(it.pkwiu || '');
                    $tr.find('.item-gtin').val(it.gtin || '');
                    $tr.find('.item-cn-code').val(it.cn_code || '');
                    $tr.find('.item-excise').val(it.excise_amount || '');
                    $tr.find('.item-procedure').val(it.procedure_marking || '');
                    // Recalc sums
                    try { allCalc(); } catch(_) {}
                  }
                  if (Array.isArray(items) && items.length){ items.forEach(function(it, idx){ addItemRow(idx, it); }); }
                };
                // Store data and defer execution until rows are initialized
                window._origPrefillData = {
                  ctr: <?= json_encode($origCtrArr) ?>,
                  items: <?= json_encode($origItemsArr) ?>
                };
                </script>
                <?php endif; ?>
            </div>

            <!-- (PRZENIESIONE na prawą kartę — numer, waluta, data wystawienia) -->
            <div class="col-xl-4">
              <!-- puste – prawa kolumna ma teraz karty -->
            </div>
          </div>
        </div>

        <div class="card custom-card invoice-compact">
          <!-- Pozycje -->
          <div class="table-responsive">
            <table class="table nowrap text-nowrap border mt-3" id="items-table">
        <thead>
        <tr>
          <th style="min-width:260px;">PRODUKT</th>
          <th style="width:120px;">ILOŚĆ</th>
          <th style="width:80px;">JM</th>
          <th style="width:120px;">GTU
          <button type="button"
            id="gtu-help"
            class="btn btn-link p-0 ms-1 align-baseline"
            data-bs-toggle="popover"
            data-bs-placement="left"
            aria-label="Pomoc: GTU"
            title="Kody GTU — pomoc">
            <i class="ri-question-line fs-6"></i>
          </button>
          </th>
          <th style="width:160px;">WARTOŚĆ BRUTTO</th>
          <th style="width:180px;">CENA NABYCIA (BRUTTO)</th>
          <th style="width:120px;">AKCJA</th>
        </tr>
        </thead>

              <tbody id="items-body">
                <!-- pierwszy (wymagany) wiersz -->
                <tr>
  <td>
    <select class="form-select item-product-select" data-index="0" data-placeholder="Wybierz lub wpisz produkt"></select>
    <input type="hidden" name="items[0][name]" class="item-name-hidden">
    <input type="hidden" name="items[0][pkwiu]" class="item-pkwiu" value="">
    <input type="hidden" name="items[0][gtin]" class="item-gtin" value="">
    <input type="hidden" name="items[0][cn_code]" class="item-cn-code" value="">
    <input type="hidden" name="items[0][excise_amount]" class="item-excise" value="">
    <input type="hidden" name="items[0][procedure_marking]" class="item-procedure" value="">
  </td>
  <td><input name="items[0][quantity]" type="number" step="0.001" value="1" class="form-control text-end item-qty" required></td>
  <td><input name="items[0][unit]" type="text" value="szt." class="form-control item-unit" style="width:70px;" list="prod-units-list" autocomplete="off"></td>
  <td class="gtu-cell"><?= $gtuSelectHtml ?></td>
  <td><input name="items[0][price]" type="number" step="0.01" value="0" class="form-control text-end item-amount" required></td>
  <td><input name="items[0][purchase_price]" type="number" step="0.01" value="0" class="form-control text-end item-purchase" placeholder="wew."></td>
  <td>
    <button type="button" class="btn btn-sm btn-icon btn-danger-light btn-remove" title="Usuń"><i class="ri-delete-bin-5-line"></i></button>
  </td>
</tr>


                <!-- wiersz: Add Product -->
               <tr>
  <td colspan="6" class="border-bottom-0">
    <button type="button" class="btn btn-light" id="btn-add-item"><i class="bi bi-plus-lg"></i> Dodaj produkt</button>
  </td>
</tr>


                <!-- wiersz: Sumy -->
               <tr>
  <td colspan="5"></td>
  <td colspan="2">
    <table class="table table-sm text-nowrap mb-0 table-borderless">
      <tbody>
        <tr>
          <th scope="row"><div class="fw-medium">Zakup (suma kosztów) <span class="sum-currency-label text-muted fw-normal"></span>:</div></th>
          <td><input type="text" id="sum-net" class="form-control invoice-amount-input text-end" value="0.00" readonly></td>
        </tr>
        <tr class="d-none" aria-hidden="true">
          <th scope="row"><div class="fw-medium">VAT od marży <span class="sum-currency-label text-muted fw-normal"></span>:</div></th>
          <td><input type="text" id="sum-tax" class="form-control invoice-amount-input text-end" value="0.00" readonly></td>
        </tr>
        <tr>
          <th scope="row"><div class="fs-14 fw-medium">Sprzedaż brutto <span class="sum-currency-label text-muted fw-normal"></span>:</div></th>
          <td><input type="text" id="sum-gross" class="form-control invoice-amount-input text-end" value="0.00" readonly></td>
        </tr>
      </tbody>
    </table>
  </td>
</tr>

              </tbody>
            </table>
          </div>
        </div>

        <div class="mt-3">
          <?= $this->Form->control('description', ['label' => 'Uwagi', 'type' => 'textarea', 'rows' => 3, 'class' => 'form-control']) ?>
        </div>
      </div>

      <div class="card-footer text-end">
        <?php if ($_isAdmin || $_role !== 'user'): ?>
          <?php if ($__ksefModeEnabled): ?>
            <?= $this->Form->button('Zapisz jako roboczą', [
              'class' => 'btn btn-outline-primary m-1',
              'name' => 'save_draft'
            ]) ?>
            <?= $this->Form->button('Zapisz i wyślij do KSeF <i class="ri-send-plane-line ms-1 align-middle d-inline-block"></i>', [
              'class' => 'btn btn-primary m-1', 'escapeTitle' => false, 'name' => 'save_and_send_ksef'
            ]) ?>
          <?php else: ?>
            <?= $this->Form->button('Zapisz jako roboczą', [
              'class' => 'btn btn-outline-secondary m-1', 'name' => 'save_draft'
            ]) ?>
            <?= $this->Form->button('Zapisz i wystaw', [
              'class' => 'btn btn-primary m-1',
              'name' => 'save_only'
            ]) ?>
          <?php endif; ?>
        <?php else: ?>
          <?= $this->Form->button('Zapisz jako roboczą', ['class' => 'btn btn-primary m-1', 'name' => 'save_draft']) ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?= $this->Form->end() ?>
<!-- Modal: KSeF confirm send -->
<div class="modal fade" id="ksef-confirm-modal" tabindex="-1" aria-labelledby="ksefConfirmLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ksefConfirmLabel">Wyślij do KSeF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Czy na pewno chcesz zapisać tę fakturę i natychmiast wysłać ją do KSeF?</p>
        <div class="row g-3 align-items-center">
          <div class="col-sm-5">
            <label for="ksef-env" class="col-form-label">Środowisko</label>
          </div>
          <div class="col-sm-7">
            <select id="ksef-env" class="form-select form-select-sm">
              <option value="test">Test</option>
              <option value="prod">Production</option>
            </select>
          </div>
          <div class="col-12">
            <div class="form-text">Domyślnie używane będzie środowisko testowe. Zmień na Production tylko jeśli masz pewność.</div>
          </div>
        </div>
        <hr>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
        <button type="button" class="btn btn-primary" id="ksef-confirm-send-btn">Wyślij do KSeF</button>
      </div>
    </div>
  </div>
  <!-- Hidden fields injected to the main form on confirm -->
  <input type="hidden" name="ksef_send" id="ksef-send-flag" value="0">
  <input type="hidden" name="ksef_env" id="ksef-env-hidden" value="test">
</div>

<script>
// KSeF confirm modal for "Zapisz i wyślij do KSeF" (procedura marży)
$(function(){
  var $form = $('form.needs-validation').first();
  if (!$form.length) return;
  // intercept both header and footer KSeF buttons
  $(document).on('click', 'button[name="save_and_send_ksef"]', function(e){
    e.preventDefault();
    var modalEl = document.getElementById('ksef-confirm-modal');
    if (!modalEl) { $form.trigger('submit'); return; }
    try { $('#ksef-confirm-modal').modal('show'); } catch(_){ new bootstrap.Modal(modalEl).show(); }
  });
  // confirm send
  $(document).on('click', '#ksef-confirm-send-btn', function(){
    var env = ($('#ksef-env').val()||'test');
    if ($form.find('input[name="ksef_env"]').length === 0) {
      $('<input>').attr({type:'hidden', name:'ksef_env', value: env}).appendTo($form);
    } else {
      $form.find('input[name="ksef_env"]').val(env);
    }
    if ($form.find('input[name="ksef_send"]').length === 0) {
      $('<input>').attr({type:'hidden', name:'ksef_send', value: '1'}).appendTo($form);
    } else {
      $form.find('input[name="ksef_send"]').val('1');
    }
    var modalEl = document.getElementById('ksef-confirm-modal');
    try { $('#ksef-confirm-modal').modal('hide'); } catch(_){ if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide(); }
    $form.trigger('submit');
  });
});
</script>
<div class="modal fade" id="recipient-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Dodaj odbiorcę</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Nazwa*</label>
          <input type="text" class="form-control" id="recipient-name" required>
        </div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">NIP</label>
            <input type="text" class="form-control" id="recipient-nip">
          </div>
          <div class="col-6">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="recipient-email">
          </div>
        </div>
        <div class="row g-2 mt-1">
          <div class="col-6">
            <label class="form-label">Telefon</label>
            <input type="text" class="form-control" id="recipient-phone">
          </div>
          <div class="col-6">
            <label class="form-label">Kod</label>
            <input type="text" class="form-control" id="recipient-zip">
          </div>
        </div>
        <div class="mt-1">
          <label class="form-label">Ulica</label>
          <input type="text" class="form-control" id="recipient-street">
        </div>
        <div class="row g-2 mt-1">
          <div class="col-6">
            <label class="form-label">Miasto</label>
            <input type="text" class="form-control" id="recipient-city">
          </div>
          <div class="col-6">
            <label class="form-label">Kraj</label>
            <input type="text" class="form-control" id="recipient-country" value="PL">
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label">Rola podmiotu (KSeF)</label>
          <select class="form-select" id="recipient-rola">
            <option value="1">1 – Faktor</option>
            <option value="2" selected>2 – Odbiorca</option>
            <option value="3">3 – Podmiot pierwotny</option>
            <option value="4">4 – Dodatkowy nabywca</option>
            <option value="5">5 – Wystawca faktury</option>
            <option value="6">6 – Dokonujący płatności</option>
            <option value="7">7 – JST – wystawca</option>
            <option value="8">8 – JST – odbiorca</option>
            <option value="9">9 – Członek grupy VAT – wystawca</option>
            <option value="10">10 – Członek grupy VAT – odbiorca</option>
            <option value="11">11 – Pracownik</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <button class="btn btn-primary" id="recipient-save-btn"><i class="ri-save-line me-1"></i>Zapisz</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal: Nowa seria -->
<div class="modal fade" id="series-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Dodaj serię</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <?= $this->Form->create(null, [
        'url' => ['controller' => 'InvoiceSeries', 'action' => 'add'],
        'data-ajax' => '1',
        'id' => 'series-create-form'
      ]) ?>

      <div class="modal-body">
        <?= $this->Form->control('name', [
          'label' => 'Nazwa/oznaczenie serii*',
          'required' => true,
          'class' => 'form-control',
          'id' => 'series-name'
        ]) ?>
        <?= $this->Form->control('series_template', [
          'label' => 'Wzór numeracji (opcjonalnie)',
          'class' => 'form-control',
          'placeholder' => '[numer]/[rok]',
          'id' => 'series-template'
        ]) ?>
        
        <div class="alert alert-info small">
          <strong>Instrukcja wzorców numeracji:</strong><br>
          Możliwe jest definiowanie własnych wzorców serii dokumentów.<br><br>
          
          <strong>Przykładowe wzorce:</strong><br>
          • <code>[numer]/[rok]</code><br>
          • <code>FS [numer]/[miesiąc]/[rok]</code><br>
          • <code>[numer] - [miesiąc] - [rok] JK</code><br><br>
          
          <strong>Dostępne znaczniki:</strong><br>
          • <code>[numer]</code> - generowany podczas wystawiania dokumentu<br>
          • <code>[dzień]</code> - dzień miesiąca z daty wystawienia<br>
          • <code>[miesiąc]</code> - miesiąc z daty wystawienia<br>
          • <code>[kwartał]</code> - kwartał z daty wystawienia<br>
          • <code>[rok]</code> - rok z daty wystawienia (czterocyfrowy) np. 2025<br>
          • <code>[dzień_roku]</code> - dzień roku z daty wystawienia<br>
          • <code>[rok:format_dwucyfrowy]</code> - rok z daty wystawienia (dwucyfrowy) np. 25<br><br>
          
          <strong>Zera wiodące:</strong><br>
          • <code>[numer:zera_wiodące=3]</code> → 001<br>
          • <code>[miesiąc:zera_wiodące=2]</code> → 02
        </div>
        <?= $this->Form->control('starting_number', [
          'label' => 'Numer początkowy',
          'type' => 'number',
          'class' => 'form-control',
          'min' => 1,
          'value' => 1,
          'id' => 'series-starting-number'
        ]) ?>
        
        <div class="row">
          <div class="col-md-6">
            <?= $this->Form->control('invoice_series_type_id', [
              'label' => 'Typ serii (opcjonalnie)',
              'type' => 'select',
              'empty' => '-- Wybierz typ --',
              'class' => 'form-select',
              'id' => 'series-type-select'
            ]) ?>
          </div>
          <div class="col-md-6">
            <?= $this->Form->control('invoice_series_period_id', [
              'label' => 'Okres serii (opcjonalnie)',
              'type' => 'select',
              'empty' => '-- Wybierz okres --',
              'class' => 'form-select',
              'id' => 'series-period-select'
            ]) ?>
          </div>
        </div>
        
        <div class="form-check mt-3">
          <?= $this->Form->checkbox('is_default', [
            'class' => 'form-check-input',
            'id' => 'series-is-default'
          ]) ?>
          <label class="form-check-label" for="series-is-default">
            Ustaw jako domyślną serię
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', [
          'class' => 'btn btn-primary',
          'escapeTitle' => false
        ]) ?>
      </div>

      <?= $this->Form->end() ?>
    </div>
  </div>
</div>

<!-- Modal: Katalog kontrahentów -->
<div class="modal fade" id="contractor-catalog-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Katalog kontrahentów</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-sm-6">
            <input type="text" id="catalog-search" class="form-control" placeholder="Szukaj po nazwie, NIP, mieście...">
          </div>
          <div class="col-sm-6 text-end">
            <small class="text-muted" id="catalog-meta"></small>
          </div>
        </div>

        <div class="table-responsive border rounded">
          <table class="table table-hover mb-0" id="contractors-table">
            <thead class="table-light">
              <tr>
                <th style="width:40%;">Nazwa</th>
                <th style="width:18%;">NIP</th>
                <th style="width:30%;">Adres</th>
                <th style="width:12%;">Miasto</th>
              </tr>
            </thead>
            <tbody><!-- rows renderowane JS --></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Dodaj kontrahenta -->
<div class="modal fade" id="contractor-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Dodaj kontrahenta</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <?= $this->Form->create(null, ['url' => ['controller' => 'Contractors','action' => 'add'], 'data-ajax' => '1', 'id' => 'contractor-create-form']) ?>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-12">
            <?= $this->Form->control('name', [
              'label' => 'Nazwa kontrahenta*',
              'required' => true,
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-6">
            <?= $this->Form->control('altname', [
              'label' => 'Nazwa skrócona',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-6">
            <?= $this->Form->control('nip', [
              'label' => 'NIP',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('regon', [
              'label' => 'REGON',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('eu_vat', [
              'label' => 'EU VAT',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('country', [
              'label' => 'Kraj',
              'class' => 'form-control',
              'value' => 'PL'
            ]) ?>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('postal_code', [
              'label' => 'Kod pocztowy',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-8">
            <?= $this->Form->control('city', [
              'label' => 'Miasto',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-8">
            <?= $this->Form->control('street', [
              'label' => 'Ulica',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('local_number', [
              'label' => 'Nr lokalu',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-6">
            <?= $this->Form->control('phone', [
              'label' => 'Telefon',
              'class' => 'form-control'
            ]) ?>
          </div>

          <div class="col-md-6">
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
              'rows' => 2
            ]) ?>
          </div>
        </div>
        
        <?= $this->Form->hidden('is_active', ['value' => 1]) ?>
        <?= $this->Form->hidden('deleted', ['value' => 0]) ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', ['class' => 'btn btn-primary', 'escapeTitle' => false]) ?>
      </div>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>

<!-- Modal: GUS (pobranie po NIP) -->
<div class="modal fade" id="gus-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Pobierz dane z GUS</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">NIP</label>
        <input type="text" class="form-control" id="gus-nip" placeholder="np. 5210082546">
        <small class="text-muted">Podaj NIP, a dane nabywcy zostaną uzupełnione danymi z rejestru REGON (GUS).</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <button type="button" class="btn btn-primary" id="gus-fetch-btn">
          <span class="spinner-border spinner-border-sm me-1 d-none" id="gus-spinner"></span>
          Pobierz
        </button>
      </div>
    </div>
  </div>
</div>

<?= $this->element('Invoices/product_create_modal') ?>

<!-- Modal: Dodaj typ serii -->
<div class="modal fade" id="series-type-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Dodaj typ serii</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <?= $this->Form->create(null, [
        'url' => ['controller' => 'InvoiceSeriesTypes', 'action' => 'add'],
        'data-ajax' => '1',
        'id' => 'series-type-create-form'
      ]) ?>

      <div class="modal-body">
        <?= $this->Form->control('name', [
          'label' => 'Nazwa typu serii*',
          'required' => true,
          'class' => 'form-control',
          'id' => 'series-type-name'
        ]) ?>
        
        <?= $this->Form->control('invoice_series_period_id', [
          'label' => 'Okres serii (opcjonalnie)',
          'type' => 'select',
          'empty' => '-- Wybierz okres --',
          'class' => 'form-select',
          'id' => 'series-type-period-select'
        ]) ?>
        
        <?= $this->Form->control('series_template', [
          'label' => 'Szablon serii (opcjonalnie)',
          'class' => 'form-control',
          'placeholder' => 'np. {YYYY}/{MM}/{NR}',
          'id' => 'series-type-template'
        ]) ?>
        
        <?= $this->Form->control('starting_number', [
          'label' => 'Numer początkowy (opcjonalnie)',
          'type' => 'number',
          'class' => 'form-control',
          'min' => 1,
          'id' => 'series-type-starting-number'
        ]) ?>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', [
          'class' => 'btn btn-primary',
          'escapeTitle' => false
        ]) ?>
      </div>

      <?= $this->Form->end() ?>
    </div>
  </div>
</div>

<!-- Modal: Dodaj okres serii -->
<div class="modal fade" id="series-period-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Dodaj okres serii</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <?= $this->Form->create(null, [
        'url' => ['controller' => 'InvoiceSeriesPeriods', 'action' => 'add'],
        'data-ajax' => '1',
        'id' => 'series-period-create-form'
      ]) ?>

      <div class="modal-body">
        <?= $this->Form->control('name', [
          'label' => 'Nazwa okresu serii*',
          'required' => true,
          'class' => 'form-control',
          'id' => 'series-period-name',
          'placeholder' => 'np. Miesięczny, Roczny, Ciągły'
        ]) ?>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', [
          'class' => 'btn btn-primary',
          'escapeTitle' => false
        ]) ?>
      </div>

      <?= $this->Form->end() ?>
    </div>
  </div>
</div>

<!-- Toasts -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
  <div id="app-toast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="app-toast-body">…</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<style>
  #contractors-table tbody tr.catalog-row{ cursor:pointer; }
  #contractors-table tbody tr.catalog-row:hover{ background:#f5f7fb; }

  /* Nagłówki grup Select2 */
  .select2-results__group{
    font-size: .75rem;
    color: #6c757d;
    text-transform: uppercase;
    padding: .35rem .75rem;
    border-top: 1px solid #eef1f4;
    background: #f8fafc;
    position: sticky; top: 0; z-index: 1;
  }
  .select2-results__options > .select2-results__group:first-child{ border-top: none; }
  .select2-results__option .s2-action { padding: 2px 0; }
  .select2-results__option .s2-recent { color: #0d6efd; }

  .ctr-toolbar{
    position: sticky;
    top: 0;
    z-index: 2;
    background: #fff;
    padding: .5rem .5rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    gap: .25rem;
  }
  .select2-dropdown{ overflow: visible; }
  .select2-results{ max-height: 260px; }
  .select2-container--open { z-index: 1055; }

  /* ===== Tryb kompaktowy faktury ===== */
  .invoice-compact .table { --bs-table-bg: transparent; }
  .invoice-compact .table thead th{
    font-weight: 600;
    font-size: .78rem;
    letter-spacing:.02em;
    color:#6c757d;
    padding-top:.35rem; padding-bottom:.35rem;
    vertical-align: middle;
  }
  .invoice-compact #items-table tbody > tr > td{
    padding:.35rem .45rem;
    vertical-align: middle;
  }
  .invoice-compact .form-control,
  .invoice-compact .form-select{
    height: 34px;
    padding: .25rem .5rem;
    font-size: .875rem;
  }
  .invoice-compact .form-control[readonly]{ background:#f7f8fa; color:#495057; }
  .invoice-compact .btn{ --bs-btn-padding-y:.25rem; --bs-btn-padding-x:.45rem; --bs-btn-font-size:.8rem; }
  .invoice-compact .btn-sm{ padding:.2rem .45rem; font-size:.78rem; }
  .invoice-compact .btn-icon{ width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; }
  .select2-container--default.select2-compact .select2-selection--single{ height:34px; }
  .select2-container--default.select2-compact .select2-selection__rendered{ line-height:32px; padding-left:.5rem; }
  .select2-container--default.select2-compact .select2-selection__arrow{ height:32px; right:.35rem; }
  .invoice-compact td .text-end,
  .invoice-compact input[type="number"]{ text-align:right; }
  .invoice-compact th[style*="min-width:260px"]{ min-width:220px !important; }
  .invoice-compact th, .invoice-compact td{ white-space: nowrap; }

  .invoice-compact #items-body tr:last-child table td,
  .invoice-compact #items-body tr:last-child table th{ padding: .2rem .25rem; }
  .invoice-compact .invoice-amount-input{ height: 34px; font-weight:600; }

  .invoice-compact #vat-help i{ opacity:.7; }
  .invoice-compact #vat-help:hover i{ opacity:1; }

  @media (max-width: 992px){
    .invoice-compact .table{ font-size:.9rem; }
    .invoice-compact .nowrap{ white-space: normal !important; }
  }

  .popover{ max-width:520px; }
  .popover .popover-body{ max-height:360px; overflow:auto; }
  .vat-badge{ display:inline-block; min-width:3rem; text-align:center; }

  /* drobne */
  .btn-link.align-baseline { font-size:.875rem; }
  #due-preview { font-variant-numeric: tabular-nums; }

  /* --- pastylki podsumowania --- */
.invoice-pills .pill{
  display:flex; align-items:center; gap:.5rem;
  background:#f8fafc; border:1px solid #eef1f4; border-radius:12px;
  padding:.5rem .75rem;
}
.invoice-pills .pill-accent{ background:#eef6ff; border-color:#d7e9ff; }
.invoice-pills .pill-label{ font-size:.75rem; color:#6c757d; }
.invoice-pills .pill-value{ font-weight:700; font-variant-numeric: tabular-nums; }
.invoice-pills .pill-badge{
  font-size:.75rem; font-weight:600; padding:.15rem .5rem; border-radius:999px;
  background:#fff3cd; color:#9a6700; border:1px solid #ffe69c;
}
.invoice-pills .pill-badge[data-status="paid"]{
  background:#d1e7dd; color:#0f5132; border-color:#badbcc;
}
.invoice-pills .pill-badge[data-status="unpaid"]{
  background:#fde2e1; color:#8a1c1c; border-color:#f5c2c7;
}

/* --- tabela pozycji: delikatne zebra + hover --- */
#items-table tbody tr:not(.table-ghost):nth-child(odd) > td{ background:#fcfdff; }
#items-table tbody tr:hover > td{ background:#f5f8ff; }

/* --- przyciski ikonowe równy rozmiar --- */
.btn-icon{ width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; }

/* --- Select2 kompakt --- */
.select2-container--default.select2-compact .select2-selection--single{ height:34px; }
.select2-container--default.select2-compact .select2-selection__rendered{ line-height:32px; padding-left:.5rem; }
.select2-container--default.select2-compact .select2-selection__arrow{ height:32px; right:.35rem; }

/* --- tooltip/popover większa czytelność --- */
.popover{ max-width:520px; }
.popover .popover-body{ max-height:360px; overflow:auto; }

/* --- input number: wygląd readonly --- */
.invoice-compact .form-control[readonly]{ background:#f7f8fa; color:#495057; }

/* --- toolbar pod nabywcą --- */
.ctr-toolbar{ position:sticky; top:0; z-index:2; background:#fff; padding:.5rem; border:1px dashed #e9ecef; border-radius:.5rem; }

</style>
<script>
(function(){
  // — Select2: kompaktowy wygląd po otwarciu
  $(document).on('select2:open', () => {
    document.querySelectorAll('.select2-container').forEach(c => c.classList.add('select2-compact'));
  });

  // — Blokada przypadkowej zmiany wartości na input[type=number]
  const numInputs = document.querySelectorAll('input[type="number"]');
  numInputs.forEach(inp=>{
    inp.addEventListener('wheel', e=>{ if (document.activeElement === inp) e.preventDefault(); }, {passive:false});
    inp.addEventListener('keydown', e=>{ if (e.key === 'ArrowUp' || e.key === 'ArrowDown') e.preventDefault(); });
  });

  // — Duplikacja wiersza pozycji
  $('#items-body').on('click', '.btn-dup', function(){
    const $tr = $(this).closest('tr');
    const $clone = $tr.clone(true);
    const idx = $('#items-body tr').length + Math.floor(Math.random()*100);
    $clone.find('[name]').each(function(){
      const n = $(this).attr('name'); if (!n) return;
      $(this).attr('name', n.replace(/\[\d+\]/, '['+idx+']'));
    });
    $clone.find('.item-net, .item-gross').val('0.00');
    $tr.after($clone.addClass('table-ghost'));
    setTimeout(()=> $clone.removeClass('table-ghost'), 200);
  });

  // — Skróty klawiaturowe: Alt+N (nowa pozycja), Alt+S (submit)
  document.addEventListener('keydown', (e)=>{
    if (e.altKey && (e.key==='n' || e.key==='N')) { e.preventDefault(); document.getElementById('btn-add-item')?.click(); }
    if (e.altKey && (e.key==='s' || e.key==='S')) { e.preventDefault(); document.querySelector('form.needs-validation')?.requestSubmit(); }
  });

  // — Pastylki sum: dla procedury marży (Zakup / Marża / Sprzedaż)
  window.mirrorSums = function(){
    const net = parseFloat(document.getElementById('sum-net')?.value || '0') || 0;   // Zakup (suma kosztów)
    const gro = parseFloat(document.getElementById('sum-gross')?.value || '0') || 0; // Sprzedaż brutto
    const mar = gro - net; // Marża brutto (poglądowo)
    const nf = new Intl.NumberFormat('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const pp = document.getElementById('pill-purchase');
    const pm = document.getElementById('pill-margin');
    const ps = document.getElementById('pill-sales');
    if (pp) pp.textContent = nf.format(net);
    if (pm) pm.textContent = nf.format(mar);
    if (ps) ps.textContent = nf.format(gro);
  };
})();
</script>
<script>
// AJAX validation for invoice form (no page reload on errors)
(function(){
  const $form = $('form.needs-validation').first();
  if (!$form.length) return;
  const csrf = $('meta[name="csrfToken"]').attr('content') || '';

  function clearErrors(){
    $form.find('.is-invalid').removeClass('is-invalid');
    $form.find('.invalid-feedback').remove();
    $('#form-errors-box').remove();
  }
  function addErrorByName(name, msg){
    const $inp = $form.find(`[name="${name}"]`);
    if ($inp.length){
      $inp.addClass('is-invalid');
      if (!$inp.parent().find('.invalid-feedback').length){
        $('<div class="invalid-feedback"></div>').text(msg).appendTo($inp.parent());
      }
    }
  }
  function showSummary(errors){
    const $box = $('<div id="form-errors-box" class="alert alert-danger mt-2"><ul class="mb-0"></ul></div>');
    Object.values(errors).forEach(function(m){ $box.find('ul').append($('<li></li>').text(m)); });
    $('.card.custom-card .card-body').first().prepend($box);
  }

  async function runValidation(){
    clearErrors();
    try{
      const res = await fetch('<?= $this->Url->build(["controller"=>"Invoices","action"=>"validateAjax"]) ?>', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: new URLSearchParams($form.serialize())
      });
      const json = await res.json();
      if (!json.success){
        // Field mapping
        for (const [path, msg] of Object.entries(json.errors||{})){
          if (path.startsWith('items.')){
            const m = path.match(/^items\.(\d+)\.(\w+)$/);
            if (m){
              const i=m[1], field=m[2];
              const name = field==='vat_code_id' ? `items[${i}][vat_code_id]` : `items[${i}][${field}]`;
              addErrorByName(name, msg);
            }
          } else if (path === 'invoice_contractor.name'){
            addErrorByName('invoice_contractor[name]', msg);
          } else {
            addErrorByName(path, msg);
          }
        }
        showSummary(json.errors||{});
      }

      // Uaktualnij pastylki lokalnie wg bieżących sum (procedura marży)
      (function(){
        const net = parseFloat($('#sum-net').val()||'0')||0;
        const gro = parseFloat($('#sum-gross').val()||'0')||0;
        const mar = gro - net;
        const nf = new Intl.NumberFormat('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        $('#pill-purchase').text(nf.format(net));
        $('#pill-margin').text(nf.format(mar));
        $('#pill-sales').text(nf.format(gro));
      })();

      return !!json.success;
    }catch(err){
      console.error('validateAjax error', err);
      return false;
    }
  }

  $('#btn-validate').on('click', async function(e){
    e.preventDefault();
    const ok = await runValidation();
    if (ok){
      window.showToast('Formularz wygląda poprawnie. Możesz zapisać fakturę.', 'success');
    } else {
      window.showToast('Formularz zawiera błędy. Sprawdź zaznaczone pola.', 'danger');
    }
  });

  $form.on('submit', async function(e){
    const ok = await runValidation();
    if (!ok){ e.preventDefault(); e.stopPropagation(); }
  });
})();
</script>

<script>
$(function () {
  // ====== CONFIG / HELPERS ======
  var csrf = $('meta[name="csrfToken"]').attr('content') || '';
  var vatRates = <?= json_encode($vatRatesMap ?? []) ?>;
  var vatSelectHtml = <?= json_encode($vatSelectHtml) ?>;
  var contractorUrl = '<?= $this->Url->build(['controller'=>'Contractors','action'=>'search','_ext'=>'json']) ?>';
  var productUrl    = '<?= $this->Url->build(['controller'=>'Products','action'=>'search','_ext'=>'json']) ?>';
  var gusUrl        = '<?= $this->Url->build(['controller'=>'Contractors','action'=>'gusLookup','_ext'=>'json']) ?>';
  var bankSearchUrl = '<?= $this->Url->build(['controller'=>'CompanyBankAccounts','action'=>'search','_ext'=>'json']) ?>';
  var bankAddUrl    = '<?= $this->Url->build(['controller'=>'CompanyBankAccounts','action'=>'add','_ext'=>'json']) ?>';
  var seriesSearchUrl = '<?= $this->Url->build(['controller'=>'InvoiceSeries','action'=>'search','_ext'=>'json']) ?>';
  var seriesAddUrl    = '<?= $this->Url->build(['controller'=>'InvoiceSeries','action'=>'add','_ext'=>'json']) ?>';
  var seriesTypesUrl  = '<?= $this->Url->build(['controller'=>'InvoiceSeriesTypes','action'=>'search','_ext'=>'json']) ?>';
  var seriesPeriodsUrl = '<?= $this->Url->build(['controller'=>'InvoiceSeriesPeriods','action'=>'search','_ext'=>'json']) ?>';
  var seriesTypesAddUrl = '<?= $this->Url->build(['controller'=>'InvoiceSeriesTypes','action'=>'add','_ext'=>'json']) ?>';
  var seriesPeriodsAddUrl = '<?= $this->Url->build(['controller'=>'InvoiceSeriesPeriods','action'=>'add','_ext'=>'json']) ?>';
  var nbpRateUrl = '<?= $this->Url->build(["controller"=>"Invoices","action"=>"nbpRate","_ext"=>"json"]) ?>';
  var nbpCurrenciesUrl = '<?= $this->Url->build(["controller"=>"Invoices","action"=>"nbpCurrencies","_ext"=>"json"]) ?>';
  var seriesNextNumberUrl = '<?= $this->Url->build(['controller'=>'InvoiceSeries','action'=>'nextNumber','_ext'=>'json']) ?>';
  var seriesPeriods = {
    '2b4003f9-06ec-4a97-b8ce-9a1b767d1f7a': 'roczny',
    '803fdc39-7c49-4921-a6ac-6c929fc0b6f5': 'ciągły', 
    'ba09a024-35d6-4049-af1d-811ffcc84f5c': 'miesięczny'
  };
  var $form = $('form.needs-validation').first();
  var $itemsBody = $('#items-body');
  var idx = 1;
  var currentProductRow = null;

  console.log('URLs initialized:', {
    contractorUrl: contractorUrl,
    productUrl: productUrl,
    gusUrl: gusUrl
  });

  // ===== GTU Popover =====
  var gtuHelpHtml = '\
  <div class="small text-start">\
    <p><strong>Wybór rodzajów kodów GTU</strong> możliwy jest w zakładce <em>USTAWIENIA » PODATKI » RODZAJ TRANSAKCJI W JPK V7</em>.</p>\
    <p class="mb-1"><strong>Towary</strong></p>\
    <ul class="mb-2 ps-3">\
      <li><strong>GTU_01</strong> – napoje alkoholowe…</li>\
      <li><strong>GTU_02</strong> – art. 103 ust. 5aa ustawy o VAT…</li>\
      <li><strong>GTU_03</strong> – oleje opałowe / smarowe…</li>\
      <li><strong>GTU_04</strong> – wyroby tytoniowe, susz…</li>\
      <li><strong>GTU_05</strong> – odpady (poz. 79–91 zał. 15)…</li>\
      <li><strong>GTU_06</strong> – urządzenia elektroniczne (poz. 7–9, 59–63, 65, 66, 69, 94–96 zał. 15)…</li>\
      <li><strong>GTU_07</strong> – pojazdy oraz części (CN 8701–8708, 8708 10)…</li>\
      <li><strong>GTU_08</strong> – metale szlachetne i nieszlachetne (wybrane pozycje)…</li>\
      <li><strong>GTU_09</strong> – leki oraz wyroby medyczne…</li>\
      <li><strong>GTU_10</strong> – budynki, budowle i grunty.</li>\
    </ul>\
    <p class="mb-1"><strong>Usługi</strong></p>\
    <ul class="mb-0 ps-3">\
      <li><strong>GTU_11</strong> – przenoszenie uprawnień do emisji CO₂,</li>\
      <li><strong>GTU_12</strong> – usługi niematerialne (doradcze, księgowe, prawne, szkoleniowe, marketingowe itd.),</li>\
      <li><strong>GTU_13</strong> – usługi transportowe i gospodarki magazynowej.</li>\
    </ul>\
  </div>';
  if (document.getElementById('gtu-help')) {
    new bootstrap.Popover(document.getElementById('gtu-help'), {
      html: true, content: gtuHelpHtml, trigger: 'click', container: 'body', sanitize: false
    });
  }

  // === VAT Help Popover (HTML) ===
  var vatHelpHtml = '\
    <div class="small">\
      <dl class="mb-0">\
      <dt><span class="badge bg-primary-subtle text-primary border vat-badge">23%</span></dt>\
      <dd>Stawka krajowa. Przy braku aktywnego VAT kontrahenta z UE. Netto → pole 19, VAT → pole 20 deklaracji.</dd>\
      <dt class="mt-2"><span class="badge bg-primary-subtle text-primary border vat-badge">8%</span></dt>\
      <dd>Stawka obniżona krajowa. Netto → poz. 17, VAT → poz. 18.</dd>\
      <dt class="mt-2"><span class="badge bg-primary-subtle text-primary border vat-badge">5%</span></dt>\
      <dd>Stawka obniżona krajowa. Netto → poz. 15, VAT → poz. 16.</dd>\
      <dt class="mt-2"><span class="badge bg-success-subtle text-success border vat-badge">0%</span></dt>\
      <dd>Sprzedaż krajowa w szczególnych przypadkach z ustawy o VAT. Netto → pole 13.</dd>\
      <dt class="mt-2"><span class="badge bg-success-subtle text-success border vat-badge">0% WDT</span></dt>\
      <dd>WDT (UE). Wymagany czynny VAT UE po obu stronach. Netto → pole 21; transakcje w VAT-UE miesięcznie/kwartalnie.</dd>\
      <dt class="mt-2"><span class="badge bg-success-subtle text-success border vat-badge">0% EXP</span></dt>\
      <dd>Eksport poza UE. Brak podatku należnego przy posiadaniu SAD. Netto → pole 22. Bez SAD – jak sprzedaż krajowa; po uzyskaniu SAD korekta do 0% EXP.</dd>\
      <dt class="mt-2"><span class="badge bg-secondary-subtle text-secondary border vat-badge">nie podl.</span></dt>\
      <dd>Usługi poza terytorium kraju na rzecz kontrahenta spoza UE (B2B/B2C). Netto → pole 11.</dd>\
      <dt class="mt-2"><span class="badge bg-secondary-subtle text-secondary border vat-badge">nie podl. UE</span></dt>\
      <dd>Wewnątrzwspólnotowe świadczenie usług (art. 28b–28n):\
          <ul class="mb-1">\
          <li>B2B – miejsce siedziby usługobiorcy,</li>\
          <li>B2C – miejsce siedziby usługodawcy.</li>\
          </ul>\
      </dd>\
      <dt class="mt-2"><span class="badge bg-warning-subtle text-warning border vat-badge">zw</span></dt>\
      <dd>Zwolnienie (np. znaczki pocztowe). Nie wpływa na VAT należny.</dd>\
      <dt class="mt-2"><span class="badge bg-danger-subtle text-danger border vat-badge">VAT nabywca</span></dt>\
      <dd>Odwrotne obciążenie – nabywca rozlicza VAT. M.in.:\
          <ul class="mb-0">\
          <li>Zał. 11 (z wył. 28a–c) – bez progu wartości,</li>\
          <li>Zał. 11 poz. 28a–c – gdy jednolita transakcja &gt; 20&nbsp;000&nbsp;zł.</li>\
          </ul>\
      </dd>\
      </dl>\
    </div>';
  if (document.getElementById('vat-help')) {
    new bootstrap.Popover(document.getElementById('vat-help'), {
      html: true, content: vatHelpHtml, trigger: 'click', container: 'body', sanitize: false
    });
  }

  function toast(msg, type){ var $body=$('#app-toast-body'); var safe=$('<div>').text(msg).html().replace(/\n/g,'<br>'); $body.html(safe); var $toast=$('#app-toast'); $toast.removeClass('text-bg-danger text-bg-success text-bg-warning'); if(type==='danger') $toast.addClass('text-bg-danger'); new bootstrap.Toast($toast[0]).show(); }
  function closeSelect2Then(fn){
    var $sel = $('#contractor-select');
    try { $sel.select2('close'); } catch(_){}
    setTimeout(fn, 0);
  }

  // ====== KONTRAHENT SNAPSHOT ======
  function showContractorSnapshot(){ var $b=$('#contractor-snapshot'); if($b.is(':hidden')) $b.stop(true,true).slideDown(120); }
  function hideContractorSnapshot(){ $('#contractor-snapshot').stop(true,true).slideUp(120); }
  function fillContractorSnapshot(c){
    console.log('fillContractorSnapshot called with:', c);
    var data = { name:c.name||c.label||'', nip:c.nip||'', street:c.street||'', zip:c.zip||c.postal_code||c.postalCode||'', city:c.city||'', country:c.country||'PL', email:c.email||'', phone:c.phone||'' };
    console.log('Contractor data to fill:', data);
    function setField(key, val){
      var $targets = $('[name="invoice_contractor['+key+']"],[name="invoice_contractor.'+key+'"],#invoice-contractor-'+key+',#invoice_contractor_'+key);
      console.log('Setting field', key, 'to', val, 'targets found:', $targets.length);
      if ($targets.length) { $targets.val(val==null?'':val); $targets.trigger('change'); }
      else { var $any=$('[name="'+key+'"], #'+key); if ($any.length) $any.val(val==null?'':val).trigger('change'); }
    }
    Object.keys(data).forEach(function(k){ setField(k, data[k]); });
    setTimeout(function(){ var $c=$('[name="invoice_contractor[country]"]'); if($c.length && $c.val()!==data.country) $c.val(data.country).trigger('change'); }, 50);
  }
  function applyContractor(c) {
    console.log('applyContractor called with:', c);
    if (!c) return;
    fillContractorSnapshot(c); showContractorSnapshot();
    if ($.fn && $.fn.select2) {
      var $sel = $('#contractor-select');
      console.log('Select2 element found:', $sel.length);
      var label = c.label||c.name|| (c.nip ? (c.name+' ('+c.nip+')') : c.name) || 'Kontrahent';
      var value = c.id || ('LS:'+ (c.nip || (c.name||'').slice(0,30)));
      console.log('Setting contractor - label:', label, 'value:', value);
      $sel.find('option[value="'+value+'"]').remove();
      var opt = new Option(label, value, true, true);
      $sel.append(opt).trigger('change');
      $('#contractor-id-input').val(c.id || '');
      console.log('Contractor applied to Select2');
    }
    saveRecent({id: c.id || ('LS:'+ (c.nip || (c.name||''))), text: c.name || c.label});
  }
  function clearContractorSnapshot(){
    ['name','nip','street','zip','city','country','email','phone'].forEach(function(f){
      $('[name="invoice_contractor['+f+']"]').val(f==='country'?'PL':'');
    });
    $('#contractor-id-input').val('');
  }

  // ====== OSTATNIO WYBIERANI ======
  function getRecent(){ try{return JSON.parse(localStorage.getItem('recentContractors')||'[]');}catch(e){return [];} }
  function saveRecent(c){
    var arr=getRecent().filter(x=>x.id!==c.id);
    arr.unshift({id:c.id,text:c.text||c.label||c.name});
    if(arr.length>8) arr=arr.slice(0,8);
    localStorage.setItem('recentContractors', JSON.stringify(arr));
  }

  // ====== SELECT2 KONTRAHENTA ======
  if ($.fn && $.fn.select2) {
    var $contractor = $('#contractor-select').select2({
      placeholder: $('#contractor-select').data('placeholder') || 'Wpisz nazwę kontrahenta lub NIP',
      allowClear: true, minimumInputLength: 1, dropdownAutoWidth: true, width: '100%',
      ajax: {
        url: contractorUrl, dataType: 'json', delay: 200, cache: true,
        data: function (params) { return { q: (params.term || '') }; },
        transport: function (params, success, failure) {
          var q = (params.data && (params.data.q||'')).trim();
          if (q === '') { success([]); return null; }
          var request = $.ajax(params); request.then(success, failure); return request;
        },
        processResults: function (data) {
          var results = $.map(data||[], function(c){ return $.extend({ id:c.id, text:(c.label||c.name) }, c); });
          return { results: results };
        }
      },
      matcher: function(params, data){
        var term = $.trim(params.term || '');
        if (!term) return data;
        if (typeof data.text === 'string' && data.text.toLowerCase().indexOf(term.toLowerCase()) > -1) return data;
        if (data.nip && String(data.nip).indexOf(term) > -1) return data;
        return null;
      },
      language: { inputTooShort: ()=> 'Wpisz co najmniej 1 znak', searching: ()=> 'Szukam…', noResults: ()=> 'Brak wyników' },
      escapeMarkup: function (m) { return m; },
      templateResult: function (d) { var extra = d.nip ? ' <span class="text-muted">'+d.nip+'</span>' : ''; return $('<span>'+d.text+extra+'</span>')[0]; },
      templateSelection: function (d) { return d.text || d.id || ''; }
    })
    .on('select2:open', function(){ showContractorSnapshot(); injectContractorToolbar(); })
    .on('select2:select', function(e){ var d=e.params.data||{}; fillContractorSnapshot(d); showContractorSnapshot(); saveRecent(d); $('#contractor-id-input').val(d.id || ''); })
    .on('select2:clear', function(){ clearContractorSnapshot(); hideContractorSnapshot(); });
  }

  // ====== SELECT2 TYPÓW SERII ======
  if ($.fn && $.fn.select2) {
    $('#series-type-select').select2({
      placeholder: 'Wybierz typ serii',
      allowClear: true,
      ajax: {
        url: seriesTypesUrl,
        dataType: 'json',
        delay: 200,
        cache: true,
        processResults: function (data) {
          return { results: data.results || [] };
        }
      },
      language: { 
        noResults: ()=> 'Brak typów serii',
        searching: ()=> 'Szukam…'
      }
    });
  }

  // ====== SELECT2 OKRESÓW SERII ======
  if ($.fn && $.fn.select2) {
    $('#series-period-select').select2({
      placeholder: 'Wybierz okres serii',
      allowClear: true,
      ajax: {
        url: seriesPeriodsUrl,
        dataType: 'json',
        delay: 200,
        cache: true,
        processResults: function (data) {
          return { results: data.results || [] };
        }
      },
      language: { 
        noResults: ()=> 'Brak okresów serii',
        searching: ()=> 'Szukam…'
      }
    });
  }

  // ====== SPRAWDZANIE AKTUALNEGO NUMERU FAKTURY ======
  function updateInvoiceNumberHint() {
    var series = $('#series-select').val();
    var $hint = $('#invoice-number-hint');
    var $suggestion = $('#invoice-number-suggestion');
    var $template = $('#invoice-number-template');
    
    console.log('updateInvoiceNumberHint called with series:', series);
    
    if (!series || series === '') {
      $hint.hide();
      return;
    }
    
    var issueDate = $('#issue-date').val() || '';
    
    $.ajax({
      url: seriesNextNumberUrl,
      method: 'GET',
      data: { series: series, date: issueDate },
      headers: { 'Accept': 'application/json' }
    }).done(function(res) {
      console.log('nextNumber API response:', res);
      console.log('Template from API:', res.template);
      console.log('Formatted from API:', res.formatted);
      if (res && res.success) {
        $suggestion.text(res.formatted);
        $hint.show();
      } else {
        $hint.hide();
      }
    }).fail(function(xhr, status, error) {
      console.log('nextNumber API failed:', status, error);
      $hint.hide();
    });
  }

  // Sprawdź numer przy zmianie serii
  $(document).on('change', '#series-select', function() {
    updateInvoiceNumberHint();
  });

  // Sprawdź numer przy zmianie daty wystawienia
  $(document).on('change', '#issue-date', function() {
    updateInvoiceNumberHint();
  });

  // Sprawdź numer przy załadowaniu strony (jeśli seria jest już wybrana)
  $(document).ready(function() {
    updateInvoiceNumberHint();
  });

  // === Panel akcji w dropdownie (Kontrahent) ===
  function injectContractorToolbar(){
    var $dd = $('.select2-container--open .select2-dropdown');
    if (!$dd.length || $dd.find('.ctr-toolbar').length) return;
    var $search = $dd.find('.select2-search--dropdown');
    var toolbar = $(
      '<div class="ctr-toolbar">'+
        '<div class="btn-group" role="group" aria-label="Akcje kontrahenta">'+
          '<button type="button" class="btn btn-sm btn-outline-primary ctr-act-add"><i class="ri-add-line"></i> Dodaj nowego</button>'+
          '<button type="button" class="btn btn-sm btn-outline-primary ctr-act-gus"><i class="ri-download-2-line"></i> Pobierz z GUS</button>'+
          '<button type="button" class="btn btn-sm btn-outline-primary ctr-act-cat"><i class="ri-search-line"></i> Szukaj w katalogu</button>'+
          '<button type="button" class="btn btn-sm btn-outline-secondary ctr-act-rec"><i class="ri-user-add-line"></i> Dodaj odbiorcę</button>'+
        '</div>'+
        '<button type="button" class="btn btn-sm btn-outline-secondary ms-2 ctr-act-clear" title="Wyczyść"><i class="ri-close-line"></i></button>'+
      '</div>'
    );
    $search.after(toolbar);
    $dd.find('.ctr-act-add').on('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); closeSelect2Then(()=> $('#contractor-create-modal').modal('show')); });
    $dd.find('.ctr-act-gus').on('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); closeSelect2Then(()=> $('#gus-modal').modal('show')); });
    $dd.find('.ctr-act-cat').on('mousedown', function(e){ 
      e.preventDefault(); 
      e.stopPropagation(); 
      console.log('Catalog button clicked in Select2 dropdown');
      closeSelect2Then(()=> {
        console.log('About to call openCatalog from Select2');
        openCatalog();
      }); 
    });
    $dd.find('.ctr-act-clear').on('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); $('#contractor-select').val(null).trigger('change'); clearContractorSnapshot(); hideContractorSnapshot(); });
    $dd.find('.ctr-act-rec').on('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); closeSelect2Then(()=> $('#recipient-create-modal').modal('show')); });
  }

  // ===== Odbiorca (modal + snapshot) =====
  $('#recipient-add-btn').on('click', function(){
    $('#recipient-name,#recipient-nip,#recipient-email,#recipient-phone,#recipient-zip,#recipient-street,#recipient-city').val('');
    $('#recipient-country').val('PL');
    $('#recipient-rola').val('2');
    $('#recipient-create-modal').modal('show');
  });
  $('#recipient-save-btn').on('click', function(){
    var data = {
      name:$('#recipient-name').val()||'', nip:$('#recipient-nip').val()||'',
      email:$('#recipient-email').val()||'', phone:$('#recipient-phone').val()||'',
      zip:$('#recipient-zip').val()||'', street:$('#recipient-street').val()||'',
      city:$('#recipient-city').val()||'', country:$('#recipient-country').val()||'PL', rola:$('#recipient-rola').val()||'2'
    };
    Object.keys(data).forEach(function(k){
      var $t = $('[name="invoice_recipient['+k+']"], [name="invoice_recipient.'+k+']');
      if ($t.length) $t.val(data[k]).trigger('change');
    });
    $('#recipient-snapshot').slideDown(120);
    $('#recipient-add-wrap').hide();
    $('#recipient-create-modal').modal('hide');
  });
  $('#recipient-edit-btn').on('click', function(){
    $('#recipient-name').val($('[name="invoice_recipient[name]"]').val()||'');
    $('#recipient-nip').val($('[name="invoice_recipient[nip]"]').val()||'');
    $('#recipient-email').val($('[name="invoice_recipient[email]"]').val()||'');
    $('#recipient-phone').val($('[name="invoice_recipient[phone]"]').val()||'');
    $('#recipient-zip').val($('[name="invoice_recipient[zip]"]').val()||'');
    $('#recipient-street').val($('[name="invoice_recipient[street]"]').val()||'');
    $('#recipient-city').val($('[name="invoice_recipient[city]"]').val()||'');
    $('#recipient-country').val($('[name="invoice_recipient[country]"]').val()||'PL');
    $('#recipient-rola').val($('[name="invoice_recipient[rola]"]').val()||'2');
    $('#recipient-create-modal').modal('show');
  });
// ====== GUS modal ======
$('#gus-fetch-btn').on('click', function(){
  var nip = ($('#gus-nip').val()||'').replace(/\D/g,'');
  if(nip.length!==10){ toast('Podaj prawidłowy 10-cyfrowy NIP.'); return; }
  $('#gus-spinner').removeClass('d-none');

  $.ajax({
    url: gusUrl,
    method: 'POST',
    data: JSON.stringify({ nip: nip }),
    contentType: 'application/json',
    headers: { 'X-CSRF-Token': csrf, 'Accept':'application/json' }
  }).done(function(res){
    if(res && res.success){
      var c = res.contractor || {};
      c.name = c.name || c.fullName || c.company || '';
      c.country = c.country || 'PL';

      fillContractorSnapshot(c);
      showContractorSnapshot();

      if ($.fn && $.fn.select2) {
        var label = c.label || c.name || (c.nip ? (c.name+' ('+c.nip+')') : c.name) || 'Kontrahent';
        var value = c.id || ('GUS:'+ (c.nip || nip));
        var $sel = $('#contractor-select');
        $sel.find('option[value="'+value+'"]').remove();
        var opt = new Option(label, value, true, true);
        $sel.append(opt).trigger('change');
      }

      saveRecent({id: c.id || ('GUS:'+(c.nip||nip)), text: c.name || ('GUS ' + nip)});
      $('#gus-modal').modal('hide');
    } else {
      toast(res && res.message ? res.message : 'Nie znaleziono danych w GUS.');
    }
  }).fail(function(xhr){
    console.error('gus fail', xhr.status, xhr.responseText);
    toast('Błąd podczas pobierania z GUS.');
  }).always(function(){
    $('#gus-spinner').addClass('d-none');
  });
});

  // ====== POZYCJE – OBLICZENIA ======
  function toNum(v, def){ var n=parseFloat(v); return isFinite(n)?n:(def||0); }
  function rowCalc($tr){
    // W trybie marży uproszczonym pole "Wartość brutto" jest wartością pozycji (brutto)
    var amount = toNum($tr.find('.item-amount').val(), 0);
    // nic nie wyliczamy na poziomie wiersza – sumowanie nastąpi w allCalc()
    return amount;
  }

  // ===== Termin płatności — połączony preset + data =====
  const $issue = $('#issue-date');
  const $duePreset = $('#due-days-preset');
  const $dueDate = $('#payment-date');
  const $duePreview = $('#due-preview');
  function parseISO(d){ return d ? new Date(d+'T00:00:00') : null; }
  function fmtISO(date){ if(!date) return ''; const y=date.getFullYear(), m=('0'+(date.getMonth()+1)).slice(-2), d=('0'+date.getDate()).slice(-2); return `${y}-${m}-${d}`; }
  function addDays(base, days){ const dt=new Date(base.getTime()); dt.setDate(dt.getDate()+days); return dt; }
  const PRESETS = [0,7,14,30,60,90];
  function recomputeFromPreset(){
    const base = parseISO($issue.val()) || new Date();
    const v = $duePreset.val();
    if (v === '_custom') return;
    const days = parseInt(v||'0',10)||0;
    const due = addDays(base, days);
    $dueDate.val(fmtISO(due)); // Usuń .trigger('change') żeby nie wywoływać recomputeFromDate
    $duePreview.text(fmtISO(due)+' ('+days+' dni)');
  }
  function recomputeFromDate(){
    const base = parseISO($issue.val()) || new Date();
    const due  = parseISO($dueDate.val());
    if (!due){ $duePreview.text('—'); return; }
    const diffDays = Math.round((due.getTime()-base.getTime())/86400000);
    $duePreview.text(fmtISO(due)+' ('+diffDays+' dni)');
    if (PRESETS.indexOf(diffDays)>-1) {
      $duePreset.val(String(diffDays)); 
    } else {
      $duePreset.val('_custom');
    }
  }

  // ===== Guard & sumy =====
  function allCalc(){
    var totalSales = 0, totalPurchase = 0;
    $itemsBody.find('tr').each(function(){
      var $tr = $(this);
      if ($tr.find('.item-amount').length) {
        var q = toNum($tr.find('.item-qty').val(),0);
        var saleUnit = toNum($tr.find('.item-amount').val(),0);
        var buyUnit  = toNum($tr.find('.item-purchase').val(),0);
        totalSales    += q * saleUnit;
        totalPurchase += q * buyUnit;
      }
    });
    var marginGross = +(totalSales - totalPurchase).toFixed(2);
    if (marginGross < 0) marginGross = 0; // brak VAT gdy marża ujemna
    var rate = toNum($('#margin-vat-rate').val(),0);
    var vatOnMargin = rate > 0 ? +(marginGross * (rate/(100+rate))).toFixed(2) : 0;
    $('#sum-net').val(totalPurchase.toFixed(2));
    $('#sum-tax').val(vatOnMargin.toFixed(2));
    $('#sum-gross').val(totalSales.toFixed(2));
    $('.sum-currency-label').text('PLN');
    // Nie przesuwaj istniejącego terminu płatności (edycja/korekta) — przelicz z presetu
    // tylko gdy pole terminu jest puste (nowa faktura). Inaczej tylko odśwież podgląd.
    if (!$dueDate.val()) { if ($duePreset.val() !== '_custom') recomputeFromPreset(); } else { recomputeFromDate(); }
    if (typeof mirrorSums === 'function') mirrorSums();
  }
  function guardMinRows(){
    var rows = $itemsBody.find('tr').length - 2; // - add + sum
    $itemsBody.find('.btn-remove').prop('disabled', rows <= 1).attr('title', rows <= 1 ? 'Musi pozostać co najmniej 1 pozycja' : 'Usuń');
  }

  // ====== OSTATNIO UŻYWANE PRODUKTY ======
  function getRecentProducts(){
    try { return JSON.parse(localStorage.getItem('recentProducts')||'[]'); } catch(e){ return []; }
  }
  function saveRecentProduct(d){
    if (!d || !d.id) return;
    if (String(d.id).indexOf('NEW:') === 0) return;
    var list = getRecentProducts().filter(function(x){ return x.id !== d.id; });
    var entry = {
      id: d.id,
      text: d.text || d.name || '',
      price: (typeof d.price !== 'undefined') ? Number(d.price) : (typeof d.net_price !== 'undefined' ? Number(d.net_price) : null),
      vat_id: d.vat_id || d.vat_code_id || null,
      unit: d.unit || '',
      gtu_code: d.gtu_code || '',
      pkwiu: d.pkwiu || '',
      gtin: d.gtin || '',
      cn_code: d.cn_code || '',
      excise_amount: (d.excise_amount !== null && d.excise_amount !== undefined) ? d.excise_amount : '',
      procedure_marking: d.procedure_marking || ''
    };
    list.unshift(entry);
    if (list.length > 8) list = list.slice(0,8);
    try { localStorage.setItem('recentProducts', JSON.stringify(list)); } catch(e){}
  }
  function injectProductRecentToolbar($dd, $tr, $sel){
    if (!$dd.length || $dd.find('.prod-recent').length) return;
    var rec = getRecentProducts();
    if (!rec.length) return;
    var $search = $dd.find('.select2-search--dropdown');
    var $wrap = $('<div class="prod-recent p-2 border-bottom bg-white small"></div>');
    $wrap.append('<div class="text-muted mb-1">Ostatnio używane</div>');
    var $row = $('<div class="d-flex flex-wrap gap-1"></div>');
    rec.forEach(function(p){
      var label = $('<div>').text(p.text || '').html();
      var $btn = $('<button type="button" class="btn btn-light btn-sm"></button>').html(label);
      $btn.on('mousedown', function(ev){
        ev.preventDefault(); ev.stopPropagation();
        try { $sel.select2('close'); } catch(_){}
        $tr.find('.item-name-hidden').val(p.text || '');
        if (p.vat_id) { $tr.find('.item-vatcode').val(p.vat_id); }
        if (p.unit) { $tr.find('.item-unit').val(p.unit); }
        var netPrice = toNum(p.price, 0);
        $tr.find('.item-amount').val(netPrice.toFixed(2));
        // Classification fields
        if (p.gtu_code) { $tr.find('.item-gtu').val(p.gtu_code); }
        $tr.find('.item-pkwiu').val(p.pkwiu || '');
        $tr.find('.item-gtin').val(p.gtin || '');
        $tr.find('.item-cn-code').val(p.cn_code || '');
        $tr.find('.item-excise').val(p.excise_amount || '');
        $tr.find('.item-procedure').val(p.procedure_marking || '');
        // Ensure the select shows chosen product
        var opt = new Option(p.text || '', p.id, true, true);
        $sel.find('option[value="'+p.id+'"]').remove();
        $sel.append(opt).trigger('change');
        rowCalc($tr); allCalc();
      });
      $row.append($btn);
    });
    $wrap.append($row);
    $search.after($wrap);
  }

  // ====== PRODUKT: INIT SELECT2 DLA WIERSZA ======
  function initProductSelectForRow($tr){
    if (!($.fn && $.fn.select2)) return;
    var $sel = $tr.find('.item-product-select');
    var $nameHidden = $tr.find('.item-name-hidden');

    $sel.select2({
      placeholder: $sel.data('placeholder') || 'Wybierz lub wpisz produkt',
      ajax: {
        url: productUrl, dataType: 'json', delay: 200,
        data: function (p) { return { q: p.term }; },
        processResults: function (data) {
          if (data && data.success && data.results) {
            return { 
              results: $.map(data.results, function (p) { 
                return $.extend({ 
                  id: p.id, 
                  text: p.name || p.text 
                }, p); 
              }) 
            };
          }
          return { results: [] };
        }
      },
      minimumInputLength: 1,
      tags: true,
      createTag: function (params) { var term=$.trim(params.term||''); if(!term) return null; return { id:'NEW:'+term, text:term, isNew:true }; },
      language: {
        noResults: function(){
          return ''+
            '<div class="p-2 text-center">'+
              '<div class="small text-muted mb-1">Brak produktów</div>'+
              '<button type="button" class="btn btn-sm btn-primary add-product-inline">'+
                '<i class="ri-add-line"></i> Dodaj produkt'+
              '</button>'+
            '</div>';
        }
      },
      escapeMarkup: function (m) { return m; },
      width: '100%'
    })
    .on('select2:open', function(){
      var $dd = $('.select2-container--open .select2-dropdown');
      if ($dd.find('.prod-toolbar').length === 0) {
        var $search = $dd.find('.select2-search--dropdown');
        $search.after(
          '<div class="prod-toolbar p-2 border-bottom bg-white">'+
            '<button type="button" class="btn btn-sm btn-outline-primary s2-add-product"><i class="ri-add-line"></i> Dodaj produkt</button>'+
          '</div>'
        );
        $dd.on('mousedown', '.s2-add-product', function(e){
          e.preventDefault(); e.stopPropagation();
          var inst = $sel.data('select2');
          var query = inst && inst.dropdown && inst.dropdown.$search ? inst.dropdown.$search.val() : '';
          $('#product-create-name').val(query || '');
          $('#product-create-modal').modal('show');
          currentProductRow = $tr;
          $sel.select2('close');
        });
      }
      // Inject recent products bar
      injectProductRecentToolbar($dd, $tr, $sel);
    })
    .on('select2:select', function (e) {
      var d = (e.params && e.params.data) || {};
      if (String(d.id || '').indexOf('NEW:') === 0) {
        var name = d.text || String(d.id).slice(4);
        $nameHidden.val(name);
      } else {
        $nameHidden.val(d.name || d.text || '');
        if (typeof d.price !== 'undefined' && d.price !== null && d.price !== '') {
          $tr.find('.item-price').val(Number(d.price).toFixed(2));
        }
        // Use correct VAT field name - should be vat_id from the search response
        var $vat = $tr.find('.item-vatcode');
        if ($vat.length && d.vat_id) $vat.val(d.vat_id);
        if (d.unit) { $tr.find('.item-unit').val(d.unit); }
        if (d.gtu_code && $tr.find('.item-gtu').length) { $tr.find('.item-gtu').val(d.gtu_code); }
        $tr.find('.item-pkwiu').val(d.pkwiu || '');
        $tr.find('.item-gtin').val(d.gtin || '');
        $tr.find('.item-cn-code').val(d.cn_code || '');
        $tr.find('.item-excise').val(d.excise_amount !== null && d.excise_amount !== undefined ? d.excise_amount : '');
        $tr.find('.item-procedure').val(d.procedure_marking || '');
        rowCalc($tr); allCalc();
      }
    });

    // „Dodaj produkt” w „Brak wyników”
    $(document).off('mousedown.addProductInline').on('mousedown.addProductInline', '.add-product-inline', function (ev) {
      ev.preventDefault();
      var inst = $sel.data('select2');
      var query = inst && inst.dropdown && inst.dropdown.$search ? inst.dropdown.$search.val() : '';
      $('#product-create-name').val(query || '');
      $('#product-create-modal').modal('show');
      currentProductRow = $tr;
      $sel.select2('close');
    });
  }

  // ====== PIERWSZY WIERSZ ======
  (function initFirstRow(){
    var $tr = $itemsBody.find('tr').first();
    initProductSelectForRow($tr);
    $tr.on('input change', '.item-qty,.item-amount,.item-purchase', function(){ allCalc(); });
    $tr.find('.btn-remove').on('click', function(){ var rows=$itemsBody.find('tr').length-2; if(rows>1){ $tr.remove(); allCalc(); guardMinRows(); } });
    allCalc(); guardMinRows();
    // Run original prefill now that rows/select2 initialized
    try { if (window.applyOriginalPrefill && window._origPrefillData) { window.applyOriginalPrefill(window._origPrefillData.ctr, window._origPrefillData.items); } } catch(_){}
  })();

  // ====== DODAJ WIERSZ ======
  $('#btn-add-item').on('click', function () {
    var $addRow = $('#btn-add-item').closest('tr');
    var html = '' +
      '<tr>' +
        '<td>' +
          '<select class="form-select item-product-select" data-index="'+idx+'" data-placeholder="Wybierz lub wpisz produkt"></select>' +
          '<input type="hidden" name="items['+idx+'][name]" class="item-name-hidden">' +
          '<input type="hidden" name="items['+idx+'][pkwiu]" class="item-pkwiu" value="">' +
          '<input type="hidden" name="items['+idx+'][gtin]" class="item-gtin" value="">' +
          '<input type="hidden" name="items['+idx+'][cn_code]" class="item-cn-code" value="">' +
          '<input type="hidden" name="items['+idx+'][excise_amount]" class="item-excise" value="">' +
          '<input type="hidden" name="items['+idx+'][procedure_marking]" class="item-procedure" value="">' +
        '</td>' +
        '<td><input name="items['+idx+'][quantity]" type="number" step="0.001" value="1" class="form-control text-end item-qty" required></td>' +
        '<td><input name="items['+idx+'][unit]" type="text" value="szt." class="form-control item-unit" style="width:70px;" list="prod-units-list" autocomplete="off"></td>' +
        '<td class="gtu-cell"><?= str_replace(["\\","'"], ["\\\\","\\'"], $gtuSelectHtml) ?></td>' +
        '<td><input name="items['+idx+'][price]" type="number" step="0.01" value="0" class="form-control text-end item-amount" required></td>' +
        '<td><input name="items['+idx+'][purchase_price]" type="number" step="0.01" value="0" class="form-control text-end item-purchase" placeholder="wew."></td>' +
        '<td><button type="button" class="btn btn-sm btn-icon btn-danger-light btn-remove" title="Usuń"><i class="ri-delete-bin-5-line"></i></button></td>' +
      '</tr>';
    $addRow.before(html.replaceAll('items[0][gtu_code]', 'items['+idx+'][gtu_code]'));
    var $tr = $addRow.prev();
    initProductSelectForRow($tr);
    $tr.on('input change', '.item-qty,.item-amount,.item-purchase', function(){ allCalc(); });
    $tr.find('.btn-remove').on('click', function(){ var rows=$itemsBody.find('tr').length-2; if(rows>1){ $tr.remove(); allCalc(); guardMinRows(); } });
    allCalc(); guardMinRows();
    idx++;
  });

  // Global safeguard: recalc when purchase price changes anywhere
  $itemsBody.on('input change', '.item-purchase', function(){ allCalc(); });

  // Recompute when hidden VAT-on-margin rate changes (set by margin type)
  $(document).on('change', '#margin-vat-rate', function(){ allCalc(); });

  // ====== „Dodaj produkt” (ikona w wierszu) ======
  $itemsBody.on('click', '.btn-new-product', function(){ currentProductRow = $(this).closest('tr'); $('#product-create-modal').modal('show'); });

  // Auto-generate product code from name
  $(document).on('input', '#product-create-name', function() {
    var name = $(this).val();
    var $codeField = $('#product-create-code');
    if (name && !$codeField.val()) {
      var code = name
        .toUpperCase()
        .replace(/[^A-Z0-9\s]/g, '') // Remove special chars except spaces
        .replace(/\s+/g, '') // Remove spaces
        .substring(0, 10); // Max 10 chars
      $codeField.val(code);
    }
  });

    // ====== PRODUKT: MODAL AJAX ADD ======
  $('#product-create-form').on('submit', function (e) {
    e.preventDefault();
    var $f    = $(this);
    var $btn  = $('#product-create-submit');
    var $spin = $('#prod-submit-spinner');
    var $icon = $('#prod-submit-icon');

    // Walidacja po stronie JS
    var name = $.trim($f.find('[name="name"]').val());
    if (!name) {
      $f.find('[name="name"]').addClass('is-invalid').trigger('focus');
      return;
    }
    $f.find('[name="name"]').removeClass('is-invalid');

    // Auto-code jesli puste
    var code = $f.find('[name="code"]').val();
    if (!code && name) {
      $f.find('[name="code"]').val(name.replace(/[^a-zA-Z0-9]/g, '').substring(0, 10).toUpperCase());
    }

    // Loading state
    $btn.prop('disabled', true);
    $spin.removeClass('d-none');
    $icon.addClass('d-none');

    var resetBtn = function () {
      $btn.prop('disabled', false);
      $spin.addClass('d-none');
      $icon.removeClass('d-none');
    };

    $.ajax({
      url: $f.attr('action'),
      method: 'POST',
      data: new FormData(this),
      processData: false, contentType: false,
      headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
    }).done(function (data) {
      resetBtn();
      if (data && data.success && currentProductRow && data.product) {
        var product  = data.product;
        var prodName = product.name || name;
        var netPrice = parseFloat(product.net_price || '0') || 0;

        // Nazwa
        currentProductRow.find('.item-name-hidden').val(prodName);

        // VAT + cena
        var $vat = currentProductRow.find('.item-vatcode');
        if ($vat.length && product.vat_id) $vat.val(product.vat_id);
        var mode           = (currentProductRow.find('.item-price-mode').val() || 'net');
        var effectiveVatId = currentProductRow.find('.item-vatcode').val();
        var rate           = toNum(vatRates[effectiveVatId], 0);
        var disp           = (mode === 'gross') ? +(netPrice * (1 + rate / 100)).toFixed(2) : +netPrice.toFixed(2);
        currentProductRow.find('.item-amount').val(disp.toFixed(2));

        // GTU – przepisz z produktu do wiersza
        if (product.gtu_code && currentProductRow.find('.item-gtu').length) {
          currentProductRow.find('.item-gtu').val(product.gtu_code);
        }

        // Select2 – pokaz nazwe produktu
        if ($.fn && $.fn.select2) {
          var $sel        = currentProductRow.find('.item-product-select');
          var displayText = prodName;
          if (product.is_service) displayText += ' (usluga)';
          var opt = new Option(displayText, product.id, true, true);
          $sel.append(opt).trigger('change');
        }

        // Zapamietaj w ostatnich
        if (typeof saveRecentProduct === 'function') saveRecentProduct({ id: product.id, text: prodName, price: netPrice, vat_id: product.vat_id });

        rowCalc(currentProductRow);
        allCalc();
        $('#product-create-modal').modal('hide');
        $f[0].reset();
        // Przywroc domyslna jednostke po resecie
        $f.find('[name="unit_name"]').val('szt.');
        toast(data.message || 'Produkt zostal dodany.');
      } else {
        var fieldLabels = {
          name: 'Nazwa', net_price: 'Cena netto', vat_id: 'Stawka VAT',
          unit_id: 'Jednostka', code: 'Kod produktu',
          currency: 'Waluta', is_service: 'Typ', is_active: 'Status'
        };
        var errorLabels = {
          _empty: 'pole wymagane', _required: 'pole wymagane',
          maxLength: 'za dluga wartosc', uuid: 'nieprawidlowy format',
          decimal: 'nieprawidlowa liczba', _isUnique: 'taka wartosc juz istnieje'
        };
        var lines = [data.message || 'Nie udalo sie dodac produktu.'];
        if (data.errors && typeof data.errors === 'object') {
          $.each(data.errors, function(field, errs) {
            if (field === 'company_id' && errs && errs._isUnique) {
              lines.push('Produkt z taka nazwa lub kodem juz istnieje.');
              return;
            }
            var label = fieldLabels[field] || field;
            var msgs = [];
            if (typeof errs === 'object') {
              $.each(errs, function(rule, msg) {
                msgs.push(errorLabels[rule] || msg);
              });
            } else {
              msgs.push(errs);
            }
            lines.push(label + ': ' + msgs.join(', '));
          });
        }
        toast(lines.join('\n'), 'danger');
      }
    }).fail(function (xhr) {
      resetBtn();
      console.error('product add fail', xhr.status, xhr.responseText);
      toast('Blad komunikacji przy dodawaniu produktu.', 'danger');
    });
  });

  // Czysc walidacje przy wpisywaniu
  $('#product-create-form').on('input', '[name="name"]', function () {
    $(this).removeClass('is-invalid');
  });

  // Reset jednostki po zamknieciu modalu
  $('#product-create-modal').on('hidden.bs.modal', function () {
    $('#product-create-form')[0].reset();
    $('#product-create-form [name="unit_name"]').val('szt.');
    $('#product-create-form [name="name"]').removeClass('is-invalid');
    $('#prod-ksef-section').collapse('hide');
  });
  
  // ====== CONTRACTOR: MODAL AJAX ADD ======
  $('#contractor-create-form').on('submit', function (e) {
    e.preventDefault();
    var $f = $(this);
    $.ajax({
      url: $f.attr('action'),
      method: 'POST',
      data: new FormData(this),
      processData: false, contentType: false,
      headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
    }).done(function (data) {
      if (data && data.success && data.contractor) {
        var contractor = data.contractor;
        
        // Apply the new contractor to the form
        applyContractor(contractor);
        
        // Close modal and reset form
        $('#contractor-create-modal').modal('hide');
        $f[0].reset();
        
        toast(data.message || 'Kontrahent został dodany i przypisany.');
      } else {
        toast(data.message || 'Nie udało się dodać kontrahenta.');
      }
    }).fail(function (xhr) {
      console.error('contractor add fail', xhr.status, xhr.responseText);
      toast('Błąd komunikacji przy dodawaniu kontrahenta.');
    });
  });

  // ====== KONTRAHENT: MODAL AJAX ADD ======
  $('#contractor-create-form').on('submit', function (e) {
    e.preventDefault();
    var $f = $(this);
    $.ajax({
      url: $f.attr('action'),
      method: 'POST',
      data: new FormData(this),
      processData: false, contentType: false,
      headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
    }).done(function (data) {
      if (data && data.success && data.contractor) {
        var contractor = data.contractor;
        
        // Apply contractor to invoice form
        applyContractor(contractor);
        
        // Close modal and reset form
        $('#contractor-create-modal').modal('hide');
        $f[0].reset();
        
        // Refresh catalog data if modal is open
        catalogData = [];
        
        toast(data.message || 'Kontrahent został dodany.');
      } else {
        toast(data.message || 'Nie udało się dodać kontrahenta.');
      }
    }).fail(function (xhr) {
      console.error('contractor add fail', xhr.status, xhr.responseText);
      toast('Błąd komunikacji przy dodawaniu kontrahenta.');
    });
  });

  // ====== WALIDACJA: min 1 wiersz ======
  function syncItemNamesBeforeSubmit(){
    $itemsBody.find('tr').each(function(){
      var $tr = $(this);
      if (!$tr.find('.item-name-hidden').length) return;
      var $nameHidden = $tr.find('.item-name-hidden');
      var current = ($nameHidden.val() || '').toString().trim();
      if (current !== '') return;
      var selectedText = ($tr.find('.item-product-select option:selected').text() || '').toString().trim();
      if (!selectedText || selectedText === 'Wybierz lub wpisz produkt') return;
      $nameHidden.val(selectedText);
    });
  }
  $form.on('submit', function (e) {
    syncItemNamesBeforeSubmit();
    var rows = $itemsBody.find('tr').length - 2;
    if (rows < 1) { e.preventDefault(); e.stopPropagation(); toast('Dodaj co najmniej jedną pozycję.'); return false; }
    $form.addClass('was-validated');
  });

  // ====== „Zapłacono (kwota)” ======
  var $paidCheck = $('#is-paid-check');
  var $paidAtGrp = $('#paid-at-group');
  var $paidInput = $('[name="alreadypaid"]');
  function getTotal(){ return parseFloat($('#sum-gross').val() || '0') || 0; }
  function syncPaidAmountIfLocked(){ if ($paidCheck.is(':checked')) { $paidInput.val(getTotal().toFixed(2)); } }
  function togglePaidLock(){
    if ($paidCheck.is(':checked')) { $paidAtGrp.stop(true,true).slideDown(120); $paidInput.prop('readOnly', true).addClass('bg-light'); syncPaidAmountIfLocked(); }
    else { $paidAtGrp.stop(true,true).slideUp(120).find('input').val(''); $paidInput.prop('readOnly', false).removeClass('bg-light'); }
  }
  $paidInput.on('input change', function(){ if ($paidCheck.is(':checked')) { syncPaidAmountIfLocked(); } });
  $paidCheck.on('change', togglePaidLock);
  togglePaidLock(); // init

  // ====== Katalog: fetch/render/handlers ======
  var catalogData = [];
  function fetchContractorsForCatalog(){
    console.log('Fetching contractors from:', contractorUrl);
    var ajaxSettings = { 
      url: contractorUrl, 
      dataType: 'json', 
      data: { q: '', limit: 1000, all: 'true' }, 
      headers: { 'Accept': 'application/json' } 
    };
    console.log('AJAX settings:', ajaxSettings);
    
    return $.ajax(ajaxSettings)
      .always(function(data, textStatus, jqXHR) {
        console.log('AJAX always callback:', { data, textStatus, jqXHR });
      });
  }
  function renderCatalog(list){
    console.log('Rendering catalog with data:', list);
    catalogData = Array.isArray(list) ? list : [];
    var $tb = $('#contractors-table tbody'); 
    console.log('Table tbody found:', $tb.length);
    if (!$tb.length) {
      console.error('contractors-table tbody not found!');
      return;
    }
    var rows = catalogData.map(function(c){
      var name = c.label || c.name || '';
      var nip  = c.nip || '';
      var addr = $.grep([c.street, c.zip], Boolean).join(', ');
      var city = c.city || '';
      var jsonStr = JSON.stringify(c);
      var dataAttr = jsonStr.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
      return '<tr class="catalog-row" data-json=\''+ jsonStr.replace(/'/g, '&#39;') +'\'><td>'+ $('<div>').text(name).html() +'</td><td>'+ $('<div>').text(nip).html() +'</td><td>'+ $('<div>').text(addr).html() +'</td><td>'+ $('<div>').text(city).html() +'</td></tr>';
    }).join('');
    $tb.html(rows || '<tr><td colspan="4" class="text-center text-muted">Brak danych</td></tr>');
    $('#catalog-meta').text('Łącznie: ' + catalogData.length);
    console.log('Catalog rendered, rows count:', catalogData.length);
  }
  function openCatalog(){
    console.log('Opening catalog modal');
    $('#contractor-catalog-modal').modal('show');
    if (catalogData.length) return;
    fetchContractorsForCatalog().done(function(data){ 
      console.log('Catalog fetch success:', data);
      renderCatalog(data || []); 
    }).fail(function(xhr, status, error){ 
      console.error('Catalog fetch failed:', xhr, status, error);
      toast('Nie udało się pobrać katalogu kontrahentów.'); 
    });
  }
  
  // Make openCatalog globally accessible
  window.openCatalog = openCatalog;
  $(document).on('input', '#catalog-search', function(){
    var term = (this.value || '').toLowerCase().trim();
    $('#contractors-table tbody tr').each(function(){ var txt=$(this).text().toLowerCase(); $(this).toggle(txt.indexOf(term)>-1); });
  });
  $(document).on('click', '#contractors-table tbody tr.catalog-row', function(){
    console.log('Catalog row clicked');
    var raw = $(this).attr('data-json') || '{}';
    console.log('Raw data-json:', raw);
    var decoded = raw.replace(/&#39;/g, "'").replace(/&quot;/g, '"');
    console.log('Decoded JSON:', decoded);
    var c = {}; try { c = JSON.parse(decoded); } catch(e){ console.warn('parse fail', e); return; }
    console.log('Parsed contractor:', c);
    if (!c || (!c.id && !c.name && !c.label)) {
      console.warn('Invalid contractor data');
      return;
    }
    applyContractor(c);
    $('#contractor-catalog-modal').modal('hide');
  });
  $(document).on('click', '.contractor-menu .action-open-catalog', function(e){ e.preventDefault(); openCatalog(); });

  // Inicjalizacja popoverów informacyjnych (inne)
  ['catalog-help','fp-help','currency-help','autosend-help'].forEach(function(id){
    var el = document.getElementById(id); if (el) new bootstrap.Popover(el, {container:'body', sanitize:false, trigger:'click'});
  });

  // Waluta (pokazanie pola kursu)
  const $currency = $('#currency');
  const $fxGroup  = $('#fx-rate-group');
  const $fxInput  = $('#fx-rate');
  const $issueDate= $('#issue-date');
  const $soldDate = $('#sold-date');
  function onCurrencyChange(){ const cur = ($currency.val()||'PLN').toUpperCase(); $fxGroup.toggle(cur !== 'PLN'); }
  $currency.on('change', onCurrencyChange); onCurrencyChange();

  // Fetch NBP rate and prefill fx-rate for foreign currency
  async function fetchNbpRate(){
    var cur = ($currency.val()||'PLN').toUpperCase();
    if (cur === 'PLN') { $fxInput.val(''); return; }
    try {
      var params = new URLSearchParams({ currency: cur, date: $issueDate.val()||'', sold_date: $soldDate.val()||'' });
      var res = await fetch(nbpRateUrl + '?' + params.toString(), { headers: { 'Accept':'application/json' }});
      var json = await res.json();
      if (json && json.success && json.rate){
        $fxInput.val(Number(json.rate).toFixed(4));
        // Optional: show a small hint
        var hintId = 'fx-rate-hint';
        var $hint = $('#'+hintId);
        var text = 'Średni kurs NBP ('+(json.table||'?')+') z dnia '+(json.effectiveDate||'')+': '+Number(json.rate).toFixed(4)+' '+cur+'/PLN';
        if ($hint.length) { $hint.text(text); } else { $fxGroup.after('<small id="'+hintId+'" class="text-muted d-block mt-1">'+text+'</small>'); }
      }
    } catch (e) { /* ignore */ }
  }
  $currency.on('change', fetchNbpRate);
  $issueDate.on('change', fetchNbpRate);
  $soldDate.on('change', fetchNbpRate);
  setTimeout(fetchNbpRate, 0);

  // Enhance currency select with Select2 + NBP currencies (filterable)
  if ($.fn && $.fn.select2) {
    var curVal = $currency.val() || 'PLN';
    // Map currency → flag-icons country code (ISO 3166-1 alpha-2 or special "eu")
    var currencyToCountry = {
      'PLN':'pl','EUR':'eu','USD':'us','GBP':'gb','CZK':'cz','CHF':'ch','JPY':'jp','CNY':'cn','SEK':'se','NOK':'no','DKK':'dk',
      'AUD':'au','CAD':'ca','NZD':'nz','HUF':'hu','RON':'ro','BGN':'bg','TRY':'tr','ZAR':'za','UAH':'ua','HRK':'hr','ISK':'is',
      'RSD':'rs','BRL':'br','MXN':'mx','ILS':'il','INR':'in','KRW':'kr','HKD':'hk','SGD':'sg','THB':'th','PHP':'ph','MYR':'my',
      'IDR':'id','AED':'ae','SAR':'sa','QAR':'qa','KWD':'kw','MAD':'ma','TND':'tn','EGP':'eg','BHD':'bh','NGN':'ng','VND':'vn',
      'LKR':'lk','PKR':'pk','GEL':'ge','AMD':'am','AZN':'az','BYN':'by','MKD':'mk','BAM':'ba','ALL':'al','KZT':'kz','UZS':'uz'
    };
    function flagHtmlForCurrency(cur){
      var code = (cur||'').toUpperCase();
      var cc = currencyToCountry[code];
      if (!cc) return '';
      return '<span class="fi fi-'+ cc +'"></span>';
    }
    $currency.select2({
      placeholder: 'Wybierz walutę',
      allowClear: false,
      width: '100%',
      ajax: {
        url: nbpCurrenciesUrl,
        dataType: 'json', delay: 150, cache: true,
        data: function (params) { return { q: (params.term||'') }; },
        processResults: function (data) {
          var items = $.map((data && data.results) || [], function (r) { return { id: r.id, text: r.text, code:r.code, name:r.name }; });
          return { results: items };
        }
      },
      minimumInputLength: 0,
      templateResult: function (d) {
        if(!d.id) return d.text;
        var code = (d.code||d.id||'').toUpperCase();
        var flag = flagHtmlForCurrency(code);
        return $('<div>'+ flag +' <strong>'+ code +'</strong> <span class="text-muted">'+ (d.name||'') +'</span></div>')[0];
      },
      templateSelection: function (d) {
        var code = (d.id||'').toUpperCase();
        var flag = flagHtmlForCurrency(code);
        return $('<span>'+ flag +' '+ code +'</span>')[0];
      }
    })
    .on('select2:select', function(){ onCurrencyChange(); fetchNbpRate(); })
    .on('select2:open', function(){ /* no-op */ });

    // Ensure current value is visible (e.g., PLN) without waiting for AJAX
    if (curVal) {
      var present = $currency.find('option[value="'+curVal+'"]').length > 0;
      if (!present) {
        var label = curVal === 'PLN' ? 'PLN - Złoty polski' : curVal;
        var opt = new Option(label, curVal, true, true);
        $currency.append(opt).trigger('change');
      }
    }
  }

  // ====== RACHUNEK: Select2 + toolbar + prefill ======
  if ($.fn && $.fn.select2) {
    var $bankSel = $('#bank-account-select').select2({
      placeholder: $('#bank-account-select').data('placeholder') || 'Wybierz rachunek lub wyszukaj',
      allowClear: true,
      width: '100%',
      ajax: {
        url: bankSearchUrl,
        dataType: 'json', delay: 200, cache: true,
        data: function (params) { return { q: (params.term||''), limit: 20, currency: $('#currency').val()||'' }; },
        processResults: function (data) {
          var items = $.map((data && data.results) || data || [], function (r) {
            var label = r.text || (r.bank_name ? (r.bank_name + ' ' + (r.iban||'')) : (r.iban||''));
            return $.extend({ id: r.id, text: label }, r);
          });
          // jeśli brak wyboru – spróbuj zaznaczyć domyślny przy pierwszym załadowaniu wyników
          setTimeout(function(){
            var $sel = $('#bank-account-select');
            if (!$sel.val() && items.length){
              var def = items.find(function(i){ return i.is_default; });
              if (def){ 
                var opt=new Option(def.text, def.id, true, true); 
                $sel.append(opt).trigger('change');
                // ustaw hiddeny (snapshot IBAN i ID)
                $('#bank-account-hidden').val(def.iban || def.text || '').trigger('change');
                $('#bank-account-id-hidden').val(def.id || '').trigger('change');
              }
            }
          },0);
          return { results: items };
        }
      },
      minimumInputLength: 0,
      escapeMarkup: function (m) { return m; },
      templateResult: function (d) { if(!d.id) return d.text; var meta=[]; if(d.currency) meta.push('<span class="text-muted small">'+d.currency+'</span>'); return $('<div>'+ $('<div>').text(d.text).html() +' '+ (meta.join(' ')||'') +'</div>')[0]; }
    })
    .on('select2:open', function(){ injectBankToolbar(); })
    .on('select2:select', function(e){
      var d = e.params && e.params.data || {};
      $('#bank-account-hidden').val(d.iban || d.text || '').trigger('change');
      $('#bank-account-id-hidden').val(d.id || '').trigger('change');
    })
    .on('select2:clear', function(){
      $('#bank-account-hidden').val('').trigger('change');
      $('#bank-account-id-hidden').val('').trigger('change');
    });

    function injectBankToolbar(){
      var $dd = $('.select2-container--open .select2-dropdown');
      if (!$dd.length || $dd.find('.bank-toolbar').length) return;
      var $search = $dd.find('.select2-search--dropdown');
      var toolbar = $(
        '<div class="bank-toolbar p-2 border-bottom bg-white d-flex justify-content-between align-items-center">'+
          '<button type="button" class="btn btn-sm btn-outline-primary s2-add-bank"><i class="ri-add-line"></i> Dodaj rachunek</button>'+
          '<span class="text-muted small">Brak na liście? Utwórz nowy.</span>'+
        '</div>'
      );
      $search.after(toolbar);
      $dd.on('mousedown', '.s2-add-bank', function(e){ e.preventDefault(); e.stopPropagation(); try{$('#bank-account-select').select2('close');}catch(_){}; $('#bank-account-create-modal').modal('show'); });
    }

    // Prefill default on initial load (no search term)
    // Trigger a silent query to load first results and pick default if available
    setTimeout(function(){
      var $sel = $('#bank-account-select');
      if (!$sel.val()) {
        // Force an initial fetch by opening and closing programmatically
        try { $sel.select2('open'); } catch(_){ }
        setTimeout(function(){ try { $sel.select2('close'); } catch(_){ } }, 0);
      }
    }, 0);
  }

  // Handlery terminu płatności (połączony preset + data)
  $issue.on('change', function(){ if ($duePreset.val() !== '_custom') recomputeFromPreset(); else recomputeFromDate(); });
  $duePreset.on('change', function(){ 
    if ($(this).val() === '_custom') { 
      $dueDate.focus(); 
      return; 
    } 
    recomputeFromPreset(); 
  });
  $dueDate.on('change', recomputeFromDate);
  setTimeout(function(){
    if ($dueDate.val()) {
      recomputeFromDate();
    } else if ($duePreset.val() && $duePreset.val() !== '_custom') {
      recomputeFromPreset();
    }
  }, 0);
// ====== SELECT2: Seria faktury ======
if ($.fn && $.fn.select2) {
  var $series = $('#series-select').select2({
    placeholder: 'Wybierz serię',
    allowClear: true,
    width: '100%',
    ajax: {
      url: seriesSearchUrl,
      dataType: 'json',
      delay: 200,
      data: function (params) { return { q: (params.term || ''), limit: 20, type: 'kor' }; },
      processResults: function (data) {
        // oczekiwany format: [{id: '2025/10', text:'2025/10', pattern:'...', next_no:123}, ...]
        var items = $.map(data.results || data || [], function (s) {
          return {
            id: s.id,
            text: s.text || s.name || s.id,
            name: s.name || s.text,
            pattern: s.template || s.pattern,
            next_no: s.start_no || s.next_no,
            is_default: s.is_default || false
          };
        });
        
        // Automatycznie wybierz domyślną serię przy pierwszym załadowaniu (tylko type=kor)
        if (!$('#series-select').val() && items.length > 0) {
          var defaultItem = items.find(function(item) { return item.is_default; });
          var pick = defaultItem || items[0];
          if (pick) {
            setTimeout(function() {
              var opt = new Option(pick.text, pick.id, true, true);
              $('#series-select').append(opt).trigger('change');
            }, 100);
          }
        }
        
        return { results: items };
      }
    },
    minimumInputLength: 0,
    escapeMarkup: function (m) { return m; },
    templateResult: function (d) {
      if (!d.id) return d.text;
      var meta = [];
      if (d.pattern) meta.push('<span class="text-muted small">wzór: '+$('<div>').text(d.pattern).html()+'</span>');
      if (d.next_no) meta.push('<span class="text-muted small ms-2">nast.: '+d.next_no+'</span>');
      return $('<div>'+ $('<div>').text(d.text).html() +' '+ (meta.join(' ')||'') +'</div>')[0];
    }
  })
  .on('select2:open', function(){ injectSeriesToolbar(); })
  .on('select2:select', function(e){
    var d = (e.params && e.params.data) || {};
    $('#invoice-series-id-hidden').val(d.id || '');
  })
  .on('select2:clear', function(){ $('#invoice-series-id-hidden').val(''); });

  // Preload: edycja — przywróć UUID; nowa faktura — auto-załaduj domyślną
  (function preloadSeries(){
    var curId   = '<?= h($invoice->invoice_series_id ?? '') ?>';
    var curName = '<?= h($invoice->series ?? '') ?>';
    if (curId) {
      var opt = new Option(curName || curId, curId, true, true);
      $series.append(opt).trigger('change');
      $('#invoice-series-id-hidden').val(curId);
    } else {
      loadDefaultSeries();
    }
  })();

  // Ładowanie domyślnej serii — typ kor, fallback do wszystkich
  function loadDefaultSeries() {
    $.ajax({ url: seriesSearchUrl, dataType: 'json', data: { q: '', limit: 50, type: 'kor' } })
      .done(function(data) {
        var items = data.results || data || [];
        if (!items.length) {
          $.ajax({ url: seriesSearchUrl, dataType: 'json', data: { q: '', limit: 50 } })
            .done(function(d2) { applyDefaultSeries(d2.results || d2 || []); });
          return;
        }
        applyDefaultSeries(items);
      });
  }
  function applyDefaultSeries(items) {
    var pick = items.find(function(i){ return i.is_default; }) || items[0] || null;
    if (pick) {
      var opt = new Option(pick.text || pick.name, pick.id, true, true);
      $series.append(opt).trigger('change');
      $('#invoice-series-id-hidden').val(pick.id);
    }
  }
}

// toolbar w dropdownie: „Dodaj serię”
function injectSeriesToolbar(){
  var $dd = $('.select2-container--open .select2-dropdown');
  if (!$dd.length || $dd.find('.series-toolbar').length) return;
  var $search = $dd.find('.select2-search--dropdown');
  var toolbar = $(
    '<div class="series-toolbar p-2 border-bottom bg-white d-flex justify-content-between align-items-center">'+
      '<button type="button" class="btn btn-sm btn-outline-primary s2-add-series"><i class="ri-add-line"></i> Dodaj serię</button>'+
      '<span class="text-muted small">Brak na liście? Utwórz nową.</span>'+
    '</div>'
  );
  $search.after(toolbar);

  $dd.on('mousedown', '.s2-add-series', function(e){
    e.preventDefault(); e.stopPropagation();
    // wstępnie podstaw wpisane wyszukiwanie do pola „nazwa”
    var inst = $('#series-select').data('select2');
    var query = inst && inst.dropdown && inst.dropdown.$search ? inst.dropdown.$search.val() : '';
    $('#series-name').val(query || '');
    $('#series-create-modal').modal('show');
    try { $('#series-select').select2('close'); } catch(_) {}
  });
}
// ====== Seria: AJAX add ======
$('#series-create-form').on('submit', function(e){
  e.preventDefault();
  var $f = $(this);
  $.ajax({
    url: seriesAddUrl,
    method: 'POST',
    data: new FormData(this),
    processData: false,
    contentType: false,
    headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
  }).done(function(res){
    // oczekiwany response: { success:true, id:'2025/10', text:'2025/10', pattern:'...', next_no:1 }
    if (res && res.success) {
      var id = res.id || res.name || res.text;
      var text = res.text || res.name || id;
      var $series = $('#series-select');
      // usuń ewentualny duplikat i dodaj/wybierz nową opcję
      $series.find('option[value="'+id+'"]').remove();
      var opt = new Option(text, id, true, true);
      $series.append(opt).trigger('change');
      $('#series-create-modal').modal('hide');
      $f[0].reset();
      toast('Seria zapisana.');
    } else {
      toast(res && res.message ? res.message : 'Nie udało się zapisać serii.');
    }
  }).fail(function(xhr){
    console.error('series add fail', xhr.status, xhr.responseText);
    toast('Błąd komunikacji przy zapisie serii.');
  });
});

  // „Zapisz zmiany do katalogu” — zielona podpowiedź
  $('#save-to-catalog').on('change', function(){ $('#save-to-catalog-hint').toggleClass('d-none', !this.checked); });
});

// ====== OBSŁUGA FORMULARZA TYPU SERII ======
$('#series-type-create-form').on('submit', function(e){
  e.preventDefault();
  var $f = $(this);
  $.ajax({
    url: seriesTypesAddUrl,
    method: 'POST',
    data: new FormData(this),
    processData: false,
    contentType: false,
    headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
  }).done(function(res){
    if (res && res.success) {
      var $select = $('#series-type-select');
      $select.find('option[value="'+res.id+'"]').remove();
      var opt = new Option(res.text, res.id, true, true);
      $select.append(opt).trigger('change');
      $('#series-type-create-modal').modal('hide');
      $f[0].reset();
      toast('Typ serii zapisany.');
    } else {
      toast(res && res.message ? res.message : 'Nie udało się zapisać typu serii.');
    }
  }).fail(function(xhr){
    console.error('series type add fail', xhr.status, xhr.responseText);
    toast('Błąd komunikacji przy zapisie typu serii.');
  });
});

// ====== OBSŁUGA FORMULARZA OKRESU SERII ======
$('#series-period-create-form').on('submit', function(e){
  e.preventDefault();
  var $f = $(this);
  $.ajax({
    url: seriesPeriodsAddUrl,
    method: 'POST',
    data: new FormData(this),
    processData: false,
    contentType: false,
    headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
  }).done(function(res){
    if (res && res.success) {
      var $select = $('#series-period-select');
      $select.find('option[value="'+res.id+'"]').remove();
      var opt = new Option(res.text, res.id, true, true);
      $select.append(opt).trigger('change');
      $('#series-period-create-modal').modal('hide');
      $f[0].reset();
      toast('Okres serii zapisany.');
    } else {
      toast(res && res.message ? res.message : 'Nie udało się zapisać okresu serii.');
    }
  }).fail(function(xhr){
    console.error('series period add fail', xhr.status, xhr.responseText);
    toast('Błąd komunikacji przy zapisie okresu serii.');
  });
});
</script>

<style>
  .select2-results__option .fi{ margin-right:.35rem; vertical-align: -0.1em; }
  .select2-selection__rendered .fi{ margin-right:.35rem; vertical-align: -0.1em; }
</style>

<!-- Modal: Dodaj rachunek bankowy -->
<div class="modal fade" id="bank-account-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Dodaj rachunek bankowy</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <?= $this->Form->create(null, ['url' => ['controller' => 'CompanyBankAccounts','action' => 'add'], 'data-ajax' => '1', 'id' => 'bank-account-create-form']) ?>
      <div class="modal-body">
        <div class="mb-2"><?= $this->Form->control('iban', ['label' => 'IBAN*', 'required' => true, 'class' => 'form-control', 'placeholder' => 'PLxx xxxx xxxx xxxx xxxx xxxx xxxx']) ?></div>
        <div class="mb-2"><?= $this->Form->control('bank_name', ['label' => 'Nazwa banku', 'class' => 'form-control']) ?></div>
        <div class="row g-2">
          <div class="col-6"><?= $this->Form->control('currency', ['label' => 'Waluta', 'type' => 'select', 'options' => ['PLN'=>'PLN','EUR'=>'EUR','USD'=>'USD','GBP'=>'GBP','CZK'=>'CZK'], 'value' => 'PLN', 'class' => 'form-select']) ?></div>
          <div class="col-6"><?= $this->Form->control('label', ['label' => 'Etykieta (opcjonalnie)', 'class' => 'form-control']) ?></div>
        </div>
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" value="1" id="bank-is-default" name="is_default">
          <label class="form-check-label" for="bank-is-default">Ustaw jako domyślny</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', ['class' => 'btn btn-primary', 'escapeTitle' => false]) ?>
      </div>
      <?= $this->Form->end() ?>
    </div>
  </div>
  <script>
    $('#bank-account-create-form').on('submit', function(e){
      e.preventDefault();
      var $f = $(this);
      $.ajax({
        url: $f.attr('action'), method: 'POST', data: new FormData(this),
        processData: false, contentType: false,
        headers: { 'X-CSRF-Token': $('meta[name="csrfToken"]').attr('content') || '', 'Accept': 'application/json' }
      }).done(function(res){
        if(res && res.success && res.account){
          var a = res.account; var opt = new Option(a.text, a.id, true, true);
          $('#bank-account-select').find('option[value="'+a.id+'"]').remove();
          $('#bank-account-select').append(opt).trigger('change');
          $('#bank-account-hidden').val(a.iban||a.text||'');
          $('#bank-account-id-hidden').val(a.id||'');
          $('#bank-account-create-modal').modal('hide'); $f[0].reset();
          toast(res.message || 'Rachunek dodany.');
        } else { toast((res && res.message) || 'Nie udało się dodać rachunku.'); }
      }).fail(function(xhr){ console.error('bank add fail', xhr.status, xhr.responseText); toast('Błąd komunikacji przy dodawaniu rachunku.'); });
    });
  </script>
</div>
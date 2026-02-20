<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Invoice $invoice
 * @var array $vats id => "Nazwa (x%)"
 * @var array $vatRatesMap id => rate
 * @var array|null $recentContractors [['id'=>..,'label'=>..,'name'=>..,'nip'=>..,'street'=>..,'zip'=>..,'city'=>..,'country'=>..,'email'=>..,'phone'=>..], ...]
 */
$this->assign('title', 'Wystaw fakturę');

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

<?= $this->Form->create($invoice, ['class' => 'needs-validation', 'novalidate' => true, 'type' => 'file']) ?>
<?php if (!empty($original?->id)): ?>
  <?= $this->Form->hidden('parent_id', ['value' => $original->id]) ?>
<?php endif; ?>

<!-- Start::page-header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2"><?= !empty($original) ? 'Wystaw korektę' : 'Wystaw fakturę' ?></h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item" aria-current="page"><a href="javascript:void(0);">Faktury</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= !empty($original) ? 'Wystaw korektę' : 'Wystaw fakturę' ?></li>
    </ol>
  </div>
  <div class="btn-list">
    
  </div>
</div>
<!-- End::page-header -->

<?php if (!empty($original)): ?>
  <div class="alert alert-info d-flex align-items-center" role="alert">
    <i class="ri-information-line me-2 fs-18"></i>
    <div>
      Wystawiasz korektę VAT do dokumentu:
      <strong><?= h($original->fullnumber ?? ('#'.$original->id)) ?></strong>
      z dnia <?= h($original->date ? $original->date->format('Y-m-d') : '') ?>.
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($original) && empty($isEdit)): ?>
<script>
  window._originalPrefill = {
    contractor: {
      id: <?= json_encode($original->contractor_id ?? null) ?>,
      name: <?= json_encode($original->invoice_contractor->name ?? '') ?>,
      nip: <?= json_encode($original->invoice_contractor->nip ?? '') ?>,
      street: <?= json_encode($original->invoice_contractor->street ?? '') ?>,
      zip: <?= json_encode($original->invoice_contractor->zip ?? ($original->invoice_contractor->postal_code ?? '')) ?>,
      city: <?= json_encode($original->invoice_contractor->city ?? '') ?>,
      country: <?= json_encode($original->invoice_contractor->country ?? 'PL') ?>,
      email: <?= json_encode($original->invoice_contractor->email ?? '') ?>,
      phone: <?= json_encode($original->invoice_contractor->phone ?? '') ?>,
      label: <?= json_encode((($original->invoice_contractor->name ?? '') . ((($original->invoice_contractor->nip ?? '') !== '') ? (' (' . $original->invoice_contractor->nip . ')') : ''))) ?>
    },
    items: <?= json_encode(array_map(function($it){
      return [
        'name' => (string)($it->name ?? ''),
        'quantity' => (float)($it->quantity ?? 1),
        'price' => isset($it->price) ? (float)$it->price : 0,
        'price_mode' => (string)($it->price_mode ?? 'net'),
        'vat_code_id' => isset($it->vat_code_id) ? (string)$it->vat_code_id : '',
        'discount_percent' => isset($it->discount_percent) ? (float)$it->discount_percent : 0,
        'gtu_code' => (string)($it->gtu_code ?? '')
      ];
    }, (array)($original->invoice_contents ?? []))) ?>
  };

  $(function(){
    try {
      var d = window._originalPrefill || {};
      function applyOriginalContractor(){
        if (!(d.contractor && (d.contractor.name || d.contractor.nip))) return;
        var $sel = $('#contractor-select');
        var hasSelect = $sel.length > 0;
        var hasS2 = hasSelect && !!$sel.data('select2');
        var fnReady  = (typeof applyContractor === 'function') || (typeof fillContractorSnapshot === 'function');
        // If helper functions not ready yet, try minimal direct fill, then retry until helpers load
        if (!fnReady) {
          try {
            var c = d.contractor;
            $('[name="invoice_contractor[name]"]').val(c.name||'');
            $('[name="invoice_contractor[nip]"]').val(c.nip||'');
            $('[name="invoice_contractor[street]"]').val(c.street||'');
            $('[name="invoice_contractor[zip]"]').val(c.zip||c.postal_code||'');
            $('[name="invoice_contractor[city]"]').val(c.city||'');
            $('[name="invoice_contractor[country]"]').val(c.country||'PL');
            $('[name="invoice_contractor[email]"]').val(c.email||'');
            $('[name="invoice_contractor[phone]"]').val(c.phone||'');
            $('#contractor-id-input').val(c.id||'');
            $('#contractor-snapshot').show();
            if (hasSelect) {
              var label = c.label||c.name|| (c.nip ? (c.name+' ('+c.nip+')') : c.name) || 'Kontrahent';
              var value = c.id || ('LS:'+ (c.nip || (c.name||'').slice(0,30)));
              $sel.find('option[value="'+value+'"]').remove();
              var opt = new Option(label, value, true, true);
              $sel.append(opt).trigger('change');
            }
          } catch(e){ console.warn('direct contractor prefill failed', e); }
          setTimeout(applyOriginalContractor, 150);
          return;
        }
        // Helpers ready: use the main path
        try {
          if (typeof applyContractor === 'function') { applyContractor(d.contractor); }
          else if (typeof fillContractorSnapshot === 'function') { fillContractorSnapshot(d.contractor); }
        } catch(e){ console.warn('applyOriginalContractor failed', e); }
        // verify fields got filled; if not, retry once more shortly
        var nameVal = $('[name="invoice_contractor[name]"]').val();
        if (!nameVal) { setTimeout(applyOriginalContractor, 200); }
      }
      applyOriginalContractor();

      if (Array.isArray(d.items) && d.items.length) {
        var items = d.items;
        var $tbody = $('#items-body');
        function fillRow($tr, item){
          try {
            $tr.find('.item-name-hidden').val(item.name || '');
            $tr.find('[name$="[quantity]"]').val(item.quantity ?? 1);
            $tr.find('.item-price').val((item.price ?? 0).toFixed ? (Number(item.price)||0).toFixed(2) : (item.price ?? 0));
            if (item.price_mode) { $tr.find('.item-price-mode').val(item.price_mode); }
            if (item.vat_code_id) { $tr.find('.item-vatcode').val(String(item.vat_code_id)); }
            $tr.find('[name$="[discount_percent]"]').val(item.discount_percent ?? 0);
            if (item.gtu_code !== undefined) { $tr.find('.gtu-cell select').val(item.gtu_code || ''); }
            // Ensure product select shows the name
            var $psel = $tr.find('.item-product-select');
            if ($psel.length) {
              var pid = 'NEW:' + (item.name || 'Pozycja');
              var opt = new Option(item.name || 'Pozycja', pid, true, true);
              $psel.find('option').remove();
              $psel.append(opt).trigger('change');
            }
            if (typeof rowCalc === 'function') { rowCalc($tr); }
          } catch (e) { console.warn('Prefill row error', e); }
        }
        // ensure enough rows
        for (var i=1; i<items.length; i++) { $('#btn-add-item').trigger('click'); }
        // fill rows
        var rows = $tbody.find('tr.item-row');
        for (var i=0; i<items.length; i++) {
          var $tr = $(rows[i]);
          if ($tr && $tr.length) { fillRow($tr, items[i]); }
        }
        if (typeof allCalc === 'function') { allCalc(); }
      }
    } catch (e) { console.warn('Original prefill failed', e); }
  });
</script>
<?php endif; ?>

<div class="row">
  <div class="col-xxl-12">
    <div class="card custom-card">
      <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="invTabs" role="tablist">
          <li class="nav-item"><button class="nav-link active" id="tab-basic" data-bs-toggle="tab" data-bs-target="#pane-basic" type="button" role="tab">Podstawowe</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-accounting" data-bs-toggle="tab" data-bs-target="#pane-accounting" type="button" role="tab">Księgowe</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-adv" data-bs-toggle="tab" data-bs-target="#pane-adv" type="button" role="tab">Zaawansowane</button></li>
        </ul>
      </div>

      <div class="card-body tab-content">
        <div class="mb-3">
            <div class="invoice-pills d-flex flex-wrap gap-2">
                <div class="pill">
                <span class="pill-label">Netto</span>
                <span class="pill-value" id="pill-net">0,00</span>
                </div>
                <div class="pill">
                <span class="pill-label">VAT</span>
                <span class="pill-value" id="pill-vat">0,00</span>
                </div>
                <div class="pill pill-accent">
                <span class="pill-label">Brutto</span>
                <span class="pill-value" id="pill-gross">0,00</span>
                </div>
            </div>
        </div>

        <!-- PODSTAWOWE -->
<div class="tab-pane fade show active" id="pane-basic" role="tabpanel" aria-labelledby="tab-basic">
          <div class="row g-3">
            <div class="col-lg-4">
              <?= $this->Form->control('fullnumber', [
                'label' => 'Invoice No', 'class' => 'form-control', 'placeholder' => 'auto',
                'id' => 'invoice-number'
              ]) ?>
              <small class="text-muted" id="invoice-number-hint" style="display: none;">
                <i class="ri-information-line"></i> Numer faktury: <span id="invoice-number-suggestion"></span>
              </small>
            </div>
            <div class="col-lg-4">
      <?= $this->Form->control('series', [
        'label' => 'Schemat numeracji',
                'type'  => 'select',
                'empty' => true,
                'class' => 'form-select',
                'id'    => 'series-select',
            // opcjonalnie: ustaw bieżącą wartość jeśli istnieje
            'value' => $invoice->series ?? null,
            ]) ?>
            </div>

            <div class="col-lg-2">
            <?= $this->Form->control('date', [
              'type' => 'date', 'label' => 'Data wystawienia', 'class' => 'form-control', 'id' => 'issue-date', 'required' => true,
              'value' => date('Y-m-d')
            ]) ?>
            </div>

            <div class="col-lg-2">
            <?= $this->Form->control('sold_date', [
              'type' => 'date', 'label' => 'Data sprzedaży', 'class' => 'form-control', 'id' => 'sold-date',
              'value' => date('Y-m-d')
            ]) ?>
            </div>

            
<div class="row g-2">
            <div class="col-lg-2">
            <?= $this->Form->control('paymentmethod', [
              'label' => 'Metoda płatności', 'type' => 'select',
              'options' => [
                'voucher'  => 'Bon',
                'cheque'   => 'Czek',
                'card'     => 'Karta',
                'credit'   => 'Kredyt',
                'mobile'   => 'Mobilna',
                'other'    => 'Płatność inna',
                'transfer' => 'Przelew',
                'cash'     => 'Gotówka'
              ],
              'empty' => '— Wybierz metodę —',
              'value' => 'transfer',
              'class' => 'form-select', 'required' => true
            ]) ?>
            </div>
            
              <div class="col-lg-2">
                <?= $this->Form->control('alreadypaid', [
                  'label' => 'Zapłacono (kwota)', 'type' => 'number', 'step' => '0.01', 'class' => 'form-control', 'value' => 0
                ]) ?>
              </div>
              <div class="col-lg-2">
                <div id="partial-paid-at-group" style="display:none;">
                  <?= $this->Form->control('partial_paid_at', ['type' => 'date', 'label' => 'Data częściowej płatności', 'class' => 'form-control']) ?>
                </div>
              </div>
              
              <div class="col-lg-2 d-flex align-items-end">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1" id="is-paid-check">
                  <label class="form-check-label" for="is-paid-check">Oznacz jako opłacone</label>
                </div>
              </div>
              <div class="col-lg-2">
                <div id="paid-at-group" style="display:none;">
                  <?= $this->Form->control('paid_at', ['type' => 'date', 'label' => 'Data zapłaty', 'class' => 'form-control']) ?>
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
                        <option value="<?= $d ?>"<?= $d == 7 ? ' selected' : '' ?>><?= $d ?> dni</option>
                      <?php endforeach; ?>
                      <option value="_custom">Inna liczba…</option>
                    </select>
                  </div>
                  <div class="col-5">
                    <div class="input-group">
                      <input type="date" id="payment-date" name="paymentdate" class="form-control">
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
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="flag-fp" name="flags[fp]" value="1">
              <label class="form-check-label" for="flag-fp">Faktura do paragonu (FP)</label>
              <button type="button" class="btn btn-link p-0 align-baseline" id="fp-help" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right"
                title="Faktura do paragonu (FP)"
                data-bs-content="
                  <div class='small text-start'>
                    <p>Po zaznaczeniu faktura otrzyma oznaczenie <strong>FP</strong> i będzie wykazana wyłącznie w części ewidencyjnej JPK_V7 (bez księgowania).</p>
                    <p><strong>Uwaga:</strong> od 1.01.2020 wystawienie firmie faktury do paragonu bez NIP nabywcy skutkuje <strong>100% sankcją VAT</strong>.</p>
                  </div>
                ">
                <i class="ri-question-line"></i>
              </button>
            </div>

            <!-- Dostawa towarów/usług zwolnionych od podatku -->
            <div class="d-flex align-items-start gap-2">
              <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" id="supply-goods" name="annotations[supply_goods]" value="1">
                <label class="form-check-label" for="supply-goods">Dostawa towarów/usług zwolnionych od podatku</label>
              </div>
              <button type="button" class="btn btn-link p-0 align-baseline" id="supply-goods-help"
                data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right" title="Zwolnienie z VAT"
                data-bs-content="
                  <div class='small text-start'>
                    Zaznacz, gdy faktura dotyczy dostawy towarów lub świadczenia usług zwolnionych na podstawie art. 43 ust. 1, art. 113 ust. 1 i 9, przepisów wydanych na podstawie art. 82 ust. 3 lub innych przepisów.
                  </div>">
                <i class="ri-question-line"></i>
              </button>
            </div>
            <div id="tax-free-extra" class="row g-2 ms-1" style="display:none;">
              <div class="col-lg-6 col-12">
                <label class="form-label" for="annotations-tax-free">Podstawa zwolnienia od podatku</label>
                <select class="form-select" id="annotations-tax-free" name="annotations_tax_free">
                  <option value="" selected>-- Wybierz podstawę --</option>
                  <option value="ustawa">Przepis ustawy albo aktu wydanego na podstawie ustawy, na podstawie którego podatnik stosuje zwolnienie od podatku</option>
                  <option value="dyrektywa">Przepis dyrektywy 2006/112/WE, który zwalnia od podatku taką dostawę towarów lub takie świadczenie usług</option>
                  <option value="inna">Inna podstawa prawna wskazującą na to, że dostawa towarów lub świadczenie usług korzysta ze zwolnienia</option>
                </select>
                <div class="form-text">Pole obowiązkowe po zaznaczeniu powyższego checkboxa.</div>
              </div>
              <div class="col-lg-6 col-12">
                <label class="form-label" for="annotations-tax-free-field">Przepis dyrektywy 2006/112/WE, który zwalnia od podatku taką dostawę towarów lub takie świadczenie usług</label>
                <textarea class="form-control" rows="3" id="annotations-tax-free-field" name="annotations_tax_free_field" placeholder="Wpisz treść przepisu lub aktu stanowiącego podstawę zwolnienia"></textarea>
                <div class="form-text">Pole obowiązkowe po zaznaczeniu powyższego checkboxa.</div>
              </div>
            </div>

            <!-- Mechanizm podzielonej płatności (MPP) -->
            <div class="d-flex align-items-center flex-wrap gap-2">
              <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="is-split-payment" name="is_split_payment" value="1">
                <label class="form-check-label" for="is-split-payment">Mechanizm podzielonej płatności (MPP)</label>
              </div>
              <button type="button" class="btn btn-link p-0 align-baseline" id="mpp-help"
                data-bs-toggle="popover" data-bs-html="true" data-bs-placement="right" title="Kiedy MPP?"
                data-bs-content="
                  <div class='small text-start'>
                    <p>MPP jest wymagany przede wszystkim, gdy:</p>
                    <ul class='mb-2 ps-3'>
                      <li>płatność B2B przelewem,</li>
                      <li>faktura obejmuje towary/usługi z załącznika 15,</li>
                      <li>wartość brutto faktury przekracza 15&nbsp;000&nbsp;PLN.</li>
                    </ul>
                    <p>Włącz MPP przy większych przelewach i wrażliwych towarach/usługach.</p>
                  </div>">
                <i class="ri-question-line"></i>
              </button>
              <small id="mpp-auto-note" class="text-muted ms-1"></small>
            </div>

            <!-- Status nabywcy -->
            <div class="row g-3 mt-1">
              <div class="col-12">
                <label class="form-label fw-semibold">Status nabywcy</label>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="buyer-is-jst" name="buyer_is_jst" value="1">
                  <label class="form-check-label" for="buyer-is-jst">Czy faktura dotyczy jednostki samorządu terytorialnego?</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="buyer-in-vat-group" name="buyer_in_vat_group" value="1">
                  <label class="form-check-label" for="buyer-in-vat-group">Czy faktura dotyczy członka grupy VAT?</label>
                </div>
              </div>
            </div>

            <!-- Faktura do paragonu: pola paragonu (pokazywane przy zaznaczonym FP) -->
            <div id="receipt-extra" class="row g-2 ms-1" style="display:none;">
              <div class="col-8">
                <?= $this->Form->control('receipt_number', ['label' => 'Nr paragonu', 'class' => 'form-control']) ?>
              </div>
              <div class="col-4">
                <?= $this->Form->control('receipt_date', ['label' => 'Data paragonu', 'type' => 'date', 'class' => 'form-control']) ?>
              </div>
            </div>

            <script>
            (function(){
              const $chk = $('#flag-fp');
              function toggleReceiptExtra(){
                const on = $chk.is(':checked');
                $('#receipt-extra').toggle(on);
                if (!on) {
                  $('[name="receipt_number"]').val('');
                  $('[name="receipt_date"]').val('');
                }
              }
              $(document).on('change', '#flag-fp', toggleReceiptExtra);
              // initialize on load
              $(toggleReceiptExtra);

              // Supply goods tax-free toggle
              const $supply = $('#supply-goods');
              function toggleTaxFreeExtra(){
                const on = $supply.is(':checked');
                $('#tax-free-extra').toggle(on);
                const $sel = $('#annotations-tax-free');
                if (on){
                  $sel.attr('required', true);
                } else {
                  $sel.removeAttr('required');
                  $sel.val('');
                }
              }
              $(document).on('change', '#supply-goods', toggleTaxFreeExtra);
              $(toggleTaxFreeExtra);
            })();
            </script>
          </div>
        </div>

        <!-- ZAAWANSOWANE -->
        <div class="tab-pane fade" id="pane-adv" role="tabpanel" aria-labelledby="tab-adv">
          <div class="row g-3">

            <?= $this->Form->control('lang', [
              'label' => 'Język faktury', 'type' => 'select', 'class' => 'form-select',
              'options' => ['pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch', 'cs' => 'Čeština'], 'value' => 'pl'
            ]) ?>

            <label class="form-label">Rachunek na fakturze</label>
            <select id="bank-account-select" class="form-select" data-placeholder="Wybierz rachunek lub wyszukaj"></select>
            <?= $this->Form->hidden('invoice_company_detail.bank_account', ['id' => 'bank-account-hidden']) ?>
            <?= $this->Form->hidden('company_bank_account_id', ['id' => 'bank-account-id-hidden']) ?>
            <small class="text-muted d-block mt-1">
              Rachunki firmy dodasz w <em>Ustawienia → Moja firma → Rachunki bankowe</em> lub bezpośrednio tutaj przyciskiem „Dodaj rachunek”.
            </small>

            <?= $this->Form->control('template', [
              'label' => 'Szablon wydruku', 'type' => 'select', 'class' => 'form-select',
              'options' => ['default' => 'Domyślny', 'compact' => 'Kompaktowy', 'pro' => 'PRO'],
              'value' => 'default'
            ]) ?>

            <?= $this->Form->control('issuer', [
              'label' => 'Wystawca (issuer)', 'class' => 'form-control',
              'placeholder' => 'np. Jan Kowalski'
            ]) ?>

            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="auto-send" name="auto_send" value="1">
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
        </div>

      </div>
    </div>
  </div>
  <div class="col-xxl-12">
    <div class="card custom-card">
      <div class="card-header d-md-flex d-block">
        <div class="card-title">Wystaw fakturę</div>
        <div class="ms-auto mt-md-0 mt-2">
          
          <?= $this->Form->button('Zapisz i wyślij do KSeF <i class="ri-send-plane-line ms-1 align-middle d-inline-block"></i>', [
            'class' => 'btn btn-sm btn-primary', 'escapeTitle' => false, 'name' => 'save_and_send_ksef'
          ]) ?>
        </div>
      </div>

      <div class="card-body">
        <div class="p-3 bg-light border rounded mb-3">
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
                <div id="recipient-snapshot" class="mt-3 border rounded p-2" style="display:none;">
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
                </div>
                </div>

              <!-- Snapshot kontrahenta (invoice_contractors) — UKRYTY NA START -->
              <div id="contractor-snapshot" class="mt-2" style="display:none;">
                <?= $this->Form->hidden('contractor_source', ['value' => '']) ?>
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
                  <div class="col-6"><?= $this->Form->control('invoice_contractor.country', ['label' => 'Kraj', 'class' => 'form-control', 'value' => 'PL']) ?></div>
                  <div class="col-6"><?= $this->Form->control('invoice_contractor.email', ['label' => 'Email', 'class' => 'form-control']) ?></div>
                  <div class="col-6"><?= $this->Form->control('invoice_contractor.phone', ['label' => 'Telefon', 'class' => 'form-control']) ?></div>
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
              </div>
            </div>

            <!-- (PRZENIESIONE na prawą kartę — numer, waluta, data wystawienia) -->
            <div class="col-xl-4">
              <!-- puste – prawa kolumna ma teraz karty -->
            </div>
          </div>
        </div>

        <div class="card custom-card invoice-compact">
          <!-- Global price mode toolbar -->
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted small">Domyślny tryb ceny:</span>
              <div class="btn-group btn-group-sm" role="group" aria-label="Domyślny tryb ceny" id="price-mode-toggle">
                <button type="button" class="btn btn-outline-secondary" data-mode="net">Netto</button>
                <button type="button" class="btn btn-outline-secondary" data-mode="gross">Brutto</button>
              </div>
            </div>
            
          </div>
          <!-- Pozycje -->
          <div class="table-responsive">
            <table class="table nowrap text-nowrap border mt-3" id="items-table">
              <thead>
                <tr>
                    <th style="min-width:260px;">PRODUKT</th>
                    <th style="width:120px;">ILOŚĆ</th>
                    <th style="width:180px;">CENA</th>
                    <th style="width:170px;">STAWKA VAT
                    <button type="button"
                        id="vat-help"
                        class="btn btn-link p-0 ms-1 align-baseline"
                        data-bs-toggle="popover"
                        data-bs-placement="left"
                        aria-label="Pomoc: stawki VAT"
                        title="Stawki VAT — pomoc">
                        <i class="ri-question-line fs-6"></i>
                    </button>
                    </th>
                    <th style="width:120px;">RABAT %</th>
                    <th style="width:140px;">NETTO</th>
                    <th style="width:140px;">BRUTTO</th>
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
                    <th style="width:120px;">AKCJA</th>
                </tr>
                </thead>

              <tbody id="items-body">
                <!-- pierwszy (wymagany) wiersz -->
                  <tr class="item-row" draggable="true">
  <td>
      <div class="d-flex align-items-center gap-1">
        <span class="drag-handle text-muted" title="Przeciągnij, aby zmienić kolejność" role="button"><i class="ri-drag-move-2-line"></i></span>
        <select class="form-select item-product-select" data-index="0" data-placeholder="Wybierz lub wpisz produkt"></select>
      </div>
    <input type="hidden" name="items[0][name]" class="item-name-hidden">
  </td>
  <td><input name="items[0][quantity]" type="number" step="0.001" value="1" class="form-control text-end item-qty" required></td>
  <td>
    <div class="d-flex align-items-center gap-1">
      <input name="items[0][price]" type="number" step="0.01" value="0" class="form-control text-end item-price" required>
      <select name="items[0][price_mode]" class="form-select item-price-mode" style="width:auto; min-width:92px">
        <option value="net" selected>Netto</option>
        <option value="gross">Brutto</option>
      </select>
    </div>
  </td>
  <td class="vat-cell"><?= $vatSelectHtml ?></td>
  <td><input name="items[0][discount_percent]" type="number" step="0.01" value="0" class="form-control text-end item-disc"></td>
  <td><input class="form-control text-end item-net" value="0.00" readonly></td>
  <td><input class="form-control text-end item-gross" value="0.00" readonly></td>
  <td class="gtu-cell"><?= $gtuSelectHtml ?></td>
  <td>
    <div class="d-flex gap-1">
      <button type="button" class="btn btn-sm btn-icon btn-secondary-light btn-duplicate" title="Duplikuj"><i class="ri-file-copy-line"></i></button>
      <button type="button" class="btn btn-sm btn-icon btn-danger-light btn-remove" title="Usuń"><i class="ri-delete-bin-5-line"></i></button>
    </div>
  </td>
</tr>


                <!-- wiersz: Add Product -->
               <tr>
  <td colspan="8" class="border-bottom-0">
    <button type="button" class="btn btn-light" id="btn-add-item"><i class="bi bi-plus-lg"></i> Dodaj produkt</button>
  </td>
</tr>


                <!-- wiersz: Sumy -->
               <tr>
  <td colspan="5"></td>
  <td colspan="4">
    <table class="table table-sm text-nowrap mb-0 table-borderless">
      <tbody>
        <tr>
          <th scope="row"><div class="fw-medium">Razem netto :</div></th>
          <td><input type="text" id="sum-net" class="form-control invoice-amount-input text-end" value="0.00" readonly></td>
        </tr>
        <tr>
          <th scope="row"><div class="fw-medium">Razem VAT :</div></th>
          <td><input type="text" id="sum-tax" class="form-control invoice-amount-input text-end" value="0.00" readonly></td>
        </tr>
        <tr>
          <th scope="row"><div class="fs-14 fw-medium">Razem brutto :</div></th>
          <td><input type="text" id="sum-gross" class="form-control invoice-amount-input text-end" value="0.00" readonly></td>
        </tr>
      </tbody>
    </table>
  </td>
</tr>

               <!-- wiersz: VAT breakdown (stawki) -->
              <tr>
  <td colspan="9" class="pt-0">
    <div id="vat-breakdown" class="d-flex flex-wrap gap-2 align-items-center mt-2"></div>
  </td>
</tr>

               <!-- wiersz: Kwota słownie -->
              <tr>
  <td colspan="9" class="pt-0">
    <div id="amount-in-words" class="text-muted fst-italic mt-1"></div>
  </td>
</tr>

<!-- Modal: Price Recalc Confirmation -->
<div class="modal fade" id="price-recalc-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Przeliczyć ceny pozycji?</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Zmieniasz domyślny tryb ceny. Czy przeliczyć ceny istniejących pozycji do wybranego trybu?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
        <button type="button" class="btn btn-primary" id="price-recalc-confirm">Przelicz</button>
      </div>
    </div>
  </div>
 </div>

              </tbody>
            </table>
          </div>
        </div>

        <div class="mt-3">
          <?= $this->Form->control('description', ['label' => 'Uwagi', 'type' => 'textarea', 'rows' => 3, 'class' => 'form-control']) ?>
        </div>
        <?php if (!empty($original)): ?>
        <div class="mt-3">
          <?= $this->Form->control('correction_reason', [
            'label' => 'Powód korekty', 'type' => 'textarea', 'rows' => 2, 'class' => 'form-control',
            'placeholder' => 'Krótko opisz przyczynę korekty (np. błędna ilość/cena, pomyłka w stawce VAT, zwrot towaru)'
          ]) ?>
        </div>
        <div class="mt-2">
          <?= $this->Form->control('correction_type', [
            'label' => 'Typ korekty (skutek w ewidencji VAT)', 'type' => 'select', 'class' => 'form-select',
            'options' => [
              '' => '— Wybierz typ —',
              'vat' => 'Zmiana wartości VAT',
              'net' => 'Zmiana podstawy opodatkowania (netto)',
              'gross' => 'Zmiana wartości brutto',
              'quantity' => 'Zmiana ilości',
              'rate' => 'Zmiana stawki VAT',
              'return' => 'Zwrot/odebranie towaru/usługi'
            ],
            'empty' => false,
            'value' => $invoice->correction_type ?? 'vat'
          ]) ?>
          <small class="text-muted">Pole wpływa na raportowanie w ewidencji/JPK. Dobierz zgodnie ze skutkiem podatkowym.</small>
        </div>
        <?php endif; ?>
      </div>

      <div class="card-footer text-end">
        <button type="button" id="btn-calc-toggle" class="btn btn-light m-1">
          <i class="ri-calculator-line me-1"></i> Kalkulator
        </button>
        <button type="button" id="btn-validate" class="btn btn-outline-secondary m-1">
          <i class="ri-shield-check-line me-1"></i> Sprawdź poprawność
        </button>
        
        <?= $this->Form->button('Zapisz i wyślij do KSeF <i class="ri-send-plane-line ms-1 align-middle d-inline-block"></i>', [
          'class' => 'btn btn-primary m-1', 'escapeTitle' => false, 'name' => 'save_and_send_ksef'
        ]) ?>
      </div>
    </div>
  </div>

  <!-- PRAWA KOLUMNA: karty -->
  
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
        <button type="button" class="btn btn-primary" id="ksef-confirm-send-btn">
          Wyślij do KSeF
        </button>
      </div>
    </div>
  </div>
  <!-- Hidden fields injected to the main form on confirm -->
  <input type="hidden" name="ksef_send" id="ksef-send-flag" value="0">
  <input type="hidden" name="ksef_env" id="ksef-env-hidden" value="test">
</div>
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
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <button class="btn btn-primary" id="recipient-save-btn"><i class="ri-save-line me-1"></i>Zapisz</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal: Nowy schemat numeracji -->
<div class="modal fade" id="series-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Dodaj schemat numeracji</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <?= $this->Form->create(null, [
        'url' => ['controller' => 'InvoiceSeries', 'action' => 'add'],
        'data-ajax' => '1',
        'id' => 'series-create-form'
      ]) ?>

      <div class="modal-body">
        <?= $this->Form->control('name', [
          'label' => 'Nazwa/oznaczenie schematu numeracji*',
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
          Możliwe jest definiowanie własnych wzorców numeracji dokumentów.<br><br>
          
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
              'label' => 'Typ numeracji (opcjonalnie)',
              'type' => 'select',
              'empty' => '-- Wybierz typ --',
              'class' => 'form-select',
              'id' => 'series-type-select'
            ]) ?>
          </div>
          <div class="col-md-6">
            <?= $this->Form->control('invoice_series_period_id', [
              'label' => 'Okres numeracji (opcjonalnie)',
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
            Ustaw jako domyślny schemat numeracji
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

<!-- Modal: Dodaj produkt -->
<div class="modal fade" id="product-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Dodaj produkt/usługę</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <?= $this->Form->create(null, ['url' => ['controller' => 'Products','action' => 'add'], 'data-ajax' => '1', 'id' => 'product-create-form']) ?>
      <div class="modal-body">
        <?= $this->Form->control('name', ['label' => 'Nazwa*', 'required' => true, 'class' => 'form-control', 'id' => 'product-create-name']) ?>
        
        <div class="row g-2">
          <div class="col-6"><?= $this->Form->control('code', ['label' => 'Kod', 'class' => 'form-control', 'id' => 'product-create-code']) ?></div>
          <div class="col-6">
            <?= $this->Form->control('is_service', [
              'label' => 'Typ',
              'type' => 'select',
              'options' => [0 => 'Produkt', 1 => 'Usługa'],
              'class' => 'form-select',
              'value' => 0
            ]) ?>
          </div>
        </div>
        
        <div class="row g-2">
          <div class="col-4"><?= $this->Form->control('unit_name', ['label' => 'Jedn.', 'class' => 'form-control', 'value' => 'szt.', 'name' => 'unit_name']) ?></div>
          <div class="col-4"><?= $this->Form->control('net_price', ['label' => 'Cena netto', 'type' => 'number', 'step' => '0.01', 'class' => 'form-control']) ?></div>
          <div class="col-4"><?= $this->Form->control('vat_id', ['label' => 'Stawka VAT', 'type' => 'select', 'options' => $vats, 'class' => 'form-select']) ?></div>
        </div>
        
        <div class="row g-2">
          <div class="col-6"><?= $this->Form->control('pkwiu', ['label' => 'PKWiU', 'class' => 'form-control', 'placeholder' => 'np. 62.01.10.0']) ?></div>
          <div class="col-6"><?= $this->Form->control('gtu_code', [
            'label' => 'GTU',
            'type' => 'select',
            'options' => [
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
            ],
            'class' => 'form-select',
            'empty' => false
          ]) ?></div>
        </div>
        
        <?= $this->Form->control('description', ['label' => 'Opis', 'type' => 'textarea', 'rows' => 2, 'class' => 'form-control']) ?>
        <?= $this->Form->control('barcode', ['label' => 'Kod kreskowy', 'class' => 'form-control']) ?>
        
        <?= $this->Form->hidden('unit_id', ['value' => 1]) ?>
        <?= $this->Form->hidden('currency', ['value' => 'PLN']) ?>
        <?= $this->Form->hidden('is_active', ['value' => 1]) ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', ['class' => 'btn btn-primary', 'escapeTitle' => false]) ?>
      </div>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>

<!-- Modal: Dodaj typ numeracji -->
<div class="modal fade" id="series-type-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Dodaj typ numeracji</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <?= $this->Form->create(null, [
        'url' => ['controller' => 'InvoiceSeriesTypes', 'action' => 'add'],
        'data-ajax' => '1',
        'id' => 'series-type-create-form'
      ]) ?>

      <div class="modal-body">
        <?= $this->Form->control('name', [
          'label' => 'Nazwa typu numeracji*',
          'required' => true,
          'class' => 'form-control',
          'id' => 'series-type-name'
        ]) ?>
        
        <?= $this->Form->control('invoice_series_period_id', [
          'label' => 'Okres numeracji (opcjonalnie)',
          'type' => 'select',
          'empty' => '-- Wybierz okres --',
          'class' => 'form-select',
          'id' => 'series-type-period-select'
        ]) ?>
        
        <?= $this->Form->control('series_template', [
          'label' => 'Szablon numeracji (opcjonalnie)',
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

<!-- Modal: Dodaj okres numeracji -->
<div class="modal fade" id="series-period-create-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Dodaj okres numeracji</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <?= $this->Form->create(null, [
        'url' => ['controller' => 'InvoiceSeriesPeriods', 'action' => 'add'],
        'data-ajax' => '1',
        'id' => 'series-period-create-form'
      ]) ?>

      <div class="modal-body">
        <?= $this->Form->control('name', [
          'label' => 'Nazwa okresu numeracji*',
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

  // — Pastylki sum: tylko definicja (wywołasz ją z allCalc() w drugim bloku)
  window.mirrorSums = function(){
    const net = document.getElementById('sum-net')?.value || '0.00';
    const vat = document.getElementById('sum-tax')?.value || '0.00';
    const gro = document.getElementById('sum-gross')?.value || '0.00';
    const pn = document.getElementById('pill-net');
    const pv = document.getElementById('pill-vat');
    const pg = document.getElementById('pill-gross');
    if (pn) pn.textContent = net;
    if (pv) pv.textContent = vat;
    if (pg) pg.textContent = gro;
  };
})();
</script>
<script>
(function(){
  // Pokaż datę częściowej płatności jeśli wpisano kwotę zapłaconą > 0
  function togglePartialPaid(){
    var v = parseFloat(($('[name="alreadypaid"]').val()||'0').replace(',','.'));
    var on = isFinite(v) && v > 0;
    $('#partial-paid-at-group').toggle(on);
    if (!on){
      $('[name="partial_paid_at"]').val('');
      $('[name="partial_paid_at"]').removeAttr('required');
    }
    if (on){
      $('[name="partial_paid_at"]').attr('required', true);
    }
  }
  $(document).on('input change', '[name="alreadypaid"]', togglePartialPaid);
  $(togglePartialPaid);
})();
</script>

<script>
// KSeF confirm modal for "Zapisz i wyślij do KSeF"
$(function(){
  var $form = $('form.needs-validation').first();
  if (!$form.length) return;
  // open modal instead of direct submit
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
    // close modal and submit
    var modalEl = document.getElementById('ksef-confirm-modal');
    try { $('#ksef-confirm-modal').modal('hide'); } catch(_){ if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide(); }
    $form.trigger('submit');
  });
});
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

      if (json.totals){
        const nf = new Intl.NumberFormat('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        $('#pill-net').text(nf.format(json.totals.netto||0));
        $('#pill-vat').text(nf.format(json.totals.tax||0));
        $('#pill-gross').text(nf.format(json.totals.gross||0));
      }

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
      const $ok = $('<div class="alert alert-success mt-2">Formularz wygląda poprawnie. Możesz zapisać fakturę.</div>');
      $('.card.custom-card .card-body').first().prepend($ok);
      setTimeout(()=> $ok.remove(), 3000);
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
      html: true, content: gtuHelpHtml, trigger: 'focus', container: 'body', sanitize: false
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
      html: true, content: vatHelpHtml, trigger: 'focus', container: 'body', sanitize: false
    });
  }

  // === MPP Help Popover ===
  if (document.getElementById('mpp-help')) {
    new bootstrap.Popover(document.getElementById('mpp-help'), {
      html: true, trigger: 'focus', container: 'body', sanitize: false
    });
  }

  function toast(msg){ $('#app-toast-body').text(msg); new bootstrap.Toast('#app-toast').show(); }
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
      if ($targets.length) $targets.val(val==null?'':val).trigger('change');
      else { var $any=$('[name="'+key+'"], #'+key); if ($any.length) $any.val(val==null?'':val).trigger('change'); }
    }
    Object.keys(data).forEach(function(k){ setField(k, data[k]); });
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
      placeholder: 'Wybierz typ numeracji',
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
        noResults: ()=> 'Brak typów numeracji',
        searching: ()=> 'Szukam…'
      }
    });
  }

  // ====== SELECT2 OKRESÓW SERII ======
  if ($.fn && $.fn.select2) {
    $('#series-period-select').select2({
      placeholder: 'Wybierz okres numeracji',
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
        noResults: ()=> 'Brak okresów numeracji',
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
    $dd.find('.ctr-act-gus').on('mousedown', function(e){
      e.preventDefault();
      e.stopPropagation();
      var typed = ($dd.find('.select2-search__field').val()||'').toString();
      var nipFromSearch = typed.replace(/\D/g,'');
      closeSelect2Then(()=> {
        if (nipFromSearch && nipFromSearch.length === 10) {
          $('#gus-nip').val(nipFromSearch);
        }
        $('#gus-modal').modal('show');
      });
    });
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
  $('#recipient-save-btn').on('click', function(){
    var data = {
      name:$('#recipient-name').val()||'', nip:$('#recipient-nip').val()||'',
      email:$('#recipient-email').val()||'', phone:$('#recipient-phone').val()||'',
      zip:$('#recipient-zip').val()||'', street:$('#recipient-street').val()||'',
      city:$('#recipient-city').val()||'', country:$('#recipient-country').val()||'PL'
    };
    Object.keys(data).forEach(function(k){
      var $t = $('[name="invoice_recipient['+k+']"], [name="invoice_recipient.'+k+']');
      if ($t.length) $t.val(data[k]).trigger('change');
    });
    $('#recipient-snapshot').slideDown(120);
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
    $('#recipient-create-modal').modal('show');
  });
// ====== GUS modal ======
// Prefill GUS modal NIP from buyer/recipient when opening
$('#gus-modal').on('shown.bs.modal', function(){
  var nip = ($('[name="invoice_contractor[nip]"]').val()||$('[name="invoice_contractor.nip"]').val()||'').replace(/\D/g,'');
  if(!nip){ nip = ($('#recipient-nip').val()||$('[name="invoice_recipient[nip]"]').val()||$('[name="invoice_recipient.nip"]').val()||'').replace(/\D/g,''); }
  if(nip){ $('#gus-nip').val(nip); }
});
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
      // Auto-enable saving contractor to catalog when fetched from GUS
      $('#save-to-catalog').prop('checked', true);
      $('#save-to-catalog-hint').removeClass('d-none');
      $('[name="contractor_source"]').val('gus');

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
  function countItemRows(){
    var n = 0;
    $itemsBody.find('tr').each(function(){ if ($(this).find('.item-net').length) n++; });
    return n;
  }
  function getDefaultPriceMode(){ try{ return (localStorage.getItem('invoice_price_mode_default')||'net'); }catch(e){ return 'net'; } }
  function setDefaultPriceMode(mode){ try{ localStorage.setItem('invoice_price_mode_default', mode); }catch(e){} }
  function updatePriceModeToolbar(){
    var cur = getDefaultPriceMode();
    $('#price-mode-toggle [data-mode]')
      .removeClass('btn-primary').addClass('btn-outline-secondary')
      .attr('aria-pressed','false');
    $('#price-mode-toggle [data-mode="'+cur+'"]').removeClass('btn-outline-secondary').addClass('btn-primary').attr('aria-pressed','true');
  }
  function convertRowPriceMode($tr, newMode){
    var oldMode = ($tr.find('.item-price-mode').val()||'net');
    if (oldMode === newMode) { $tr.find('.item-price-mode').val(newMode); return; }
    var p = toNum($tr.find('.item-price').val(), 0);
    var vatCode = $tr.find('.item-vatcode').val();
    var rate = toNum(vatRates[vatCode], 0);
    var newPrice = p;
    if (newMode === 'gross' && oldMode === 'net'){
      newPrice = rate > 0 ? +(p * (1 + rate/100)).toFixed(2) : p;
    } else if (newMode === 'net' && oldMode === 'gross'){
      newPrice = rate > 0 ? +(p / (1 + rate/100)).toFixed(2) : p;
    }
    $tr.find('.item-price-mode').val(newMode);
    $tr.find('.item-price').val((newPrice||0).toFixed(2));
    rowCalc($tr);
  }
  function rowCalc($tr){
    var q = toNum($tr.find('.item-qty').val(), 0);
    var p = toNum($tr.find('.item-price').val(), 0);
    var disc = toNum($tr.find('.item-disc').val(), 0);
    var vatCode = $tr.find('.item-vatcode').val();
    var rate = toNum(vatRates[vatCode], 0);
    var mode = ($tr.find('.item-price-mode').val() || 'net');
    var net, tax, gross;
    if (mode === 'gross') {
      // Treat input price as unit gross, apply discount on gross, then derive net/tax
      var unitGrossAfterDisc = p * (1 - disc/100);
      var grossSum = +(q * unitGrossAfterDisc).toFixed(2);
      if (rate > 0) {
        net = +(grossSum / (1 + rate/100)).toFixed(2);
        tax = +(grossSum - net).toFixed(2);
      } else {
        net = grossSum;
        tax = 0;
      }
      gross = grossSum;
    } else {
      // Default: input price is unit net, apply discount on net, then add VAT
      var unitNetAfterDisc = p * (1 - disc/100);
      net  = +(q * unitNetAfterDisc).toFixed(2);
      tax  = +(net * (rate/100)).toFixed(2);
      gross= +(net + tax).toFixed(2);
    }
    $tr.find('.item-net').val(net.toFixed(2));
    $tr.find('.item-gross').val(gross.toFixed(2));
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
    var sn = 0, sg = 0;
    $itemsBody.find('tr').each(function(){
      var $tr = $(this);
      if ($tr.find('.item-net').length) {
        sn += toNum($tr.find('.item-net').val(),0);
        sg += toNum($tr.find('.item-gross').val(),0);
      }
    });
    var st = +(sg - sn).toFixed(2);
    $('#sum-net').val(sn.toFixed(2));
    $('#sum-tax').val(st.toFixed(2));
    $('#sum-gross').val(sg.toFixed(2));
    // odśwież termin (np. po zmianie daty wystawienia)
    if ($duePreset.val() !== '_custom') recomputeFromPreset(); else recomputeFromDate();
    if (typeof mirrorSums === 'function') mirrorSums();
    // render VAT breakdown chips
    if (typeof renderVatBreakdown === 'function') renderVatBreakdown();
    // render amount in words
    if (typeof renderAmountInWords === 'function') renderAmountInWords();
    // auto MPP (jeśli nie nadpisano ręcznie)
    if (typeof autoApplyMpp === 'function') autoApplyMpp();
  }
  function guardMinRows(){
    var rows = countItemRows();
    $itemsBody.find('.btn-remove').prop('disabled', rows <= 1).attr('title', rows <= 1 ? 'Musi pozostać co najmniej 1 pozycja' : 'Usuń');
  }

  // ===== MPP auto-enable (PLN >= 15 000) =====
  var mppTouched = false;
  var $mpp = $('#is-split-payment');
  var $mppNote = $('#mpp-auto-note');
  $(document).on('change', '#is-split-payment', function(){
    mppTouched = true;
    if ($mppNote.length) $mppNote.text('Ustawiono ręcznie.');
  });
  function getInvoiceCurrency(){
    var v = ($('#invoice-currency').val() || $('[name="currency"]').val() || 'PLN');
    return String(v||'PLN').toUpperCase();
  }
  function getPaymentMethod(){
    // try typical ids/names
    var v = ($('#paymentmethod').val() || $('[name="paymentmethod"]').val() || 'transfer');
    return String(v||'transfer');
  }
  function autoApplyMpp(){
    if (!$mpp.length) return;
    var curr = getInvoiceCurrency();
    if (curr !== 'PLN') { if ($mppNote.length) $mppNote.text(''); return; }
    var gross = toNum($('#sum-gross').val(), 0);
    var paym = getPaymentMethod();
    var should = (gross >= 15000) && (paym === 'transfer');
    if (!mppTouched){
      $mpp.prop('checked', !!should);
      if ($mppNote.length) $mppNote.text(should ? 'Automatycznie włączono MPP (brutto ≥ 15 000 PLN).' : '');
    } else {
      // respect manual choice; keep note minimal
      if ($mppNote.length && !$mppNote.text()) $mppNote.text('Ustawiono ręcznie.');
    }
  }

  // initial evaluate + react to possible drivers
  $(autoApplyMpp);
  $(document).on('change', '#paymentmethod, [name="paymentmethod"]', autoApplyMpp);
  $(document).on('change', '#invoice-currency, [name="currency"]', autoApplyMpp);

  // ===== VAT breakdown (stawki) =====
  var activeVatFilter = null; // stores vat_code_id when filtered
  function computeVatBreakdown(){
    var map = {}; // key: vat_code_id
    $itemsBody.find('tr').each(function(){
      var $tr = $(this);
      var $net = $tr.find('.item-net');
      var $gro = $tr.find('.item-gross');
      var $vat = $tr.find('.item-vatcode');
      if (!$net.length || !$gro.length || !$vat.length) return;
      var code = $vat.val();
      if (!code) return;
      var net = toNum($net.val(), 0);
      var gro = toNum($gro.val(), 0);
      var tax = +(gro - net).toFixed(2);
      var rate = toNum(vatRates[code], 0);
      if (!map[code]) {
        map[code] = { code: code, rate: rate, net: 0, tax: 0, gross: 0, count: 0 };
      }
      map[code].net += net;
      map[code].tax += tax;
      map[code].gross += gro;
      map[code].count += 1;
    });
    // round
    Object.keys(map).forEach(function(k){
      var o = map[k];
      o.net = +o.net.toFixed(2);
      o.tax = +o.tax.toFixed(2);
      o.gross = +o.gross.toFixed(2);
    });
    // to array, sort by rate desc
    return Object.values(map).sort(function(a,b){ return (b.rate||0) - (a.rate||0); });
  }
  function renderVatBreakdown(){
    var data = computeVatBreakdown();
    var $box = $('#vat-breakdown');
    if (!$box.length) return;
    $box.empty();
    if (!data.length) return;
    // helper formatting
    function rateLabel(o){ return (o.rate > 0 ? (o.rate + '%') : '0%'); }
    // master chip "Wszystkie"
    var allActive = !activeVatFilter;
    var $all = $('<button type="button" class="btn btn-sm rounded-pill me-1 mb-1"></button>')
      .addClass(allActive ? 'btn-primary' : 'btn-outline-secondary')
      .attr('data-vat-code','')
      .text('Wszystkie stawki');
    $box.append($all);
    // per rate chips
    data.forEach(function(o){
      var $chip = $('<button type="button" class="btn btn-sm rounded-pill me-1 mb-1"></button>')
        .addClass(activeVatFilter === o.code ? 'btn-primary' : 'btn-outline-secondary')
        .attr('data-vat-code', o.code)
        .attr('title', 'Netto: '+ o.net.toFixed(2) +' • VAT: '+ o.tax.toFixed(2) +' • Brutto: '+ o.gross.toFixed(2))
        .text(rateLabel(o) + ' • ' + o.gross.toFixed(2));
      $box.append($chip);
    });
  }

  // click handler for chips (use delegation)
  $(document).on('click', '#vat-breakdown [data-vat-code]', function(){
    var code = $(this).attr('data-vat-code') || '';
    activeVatFilter = code || null;
    // highlight matching rows
    $itemsBody.find('tr').removeClass('highlight-vat-row');
    if (activeVatFilter) {
      $itemsBody.find('tr').each(function(){
        var $tr = $(this);
        var v = $tr.find('.item-vatcode').val();
        if (v && v === activeVatFilter) { $tr.addClass('highlight-vat-row'); }
      });
    }
    renderVatBreakdown();
  });

  // ===== Kwota słownie (PL) =====
  function pluralForm(n, forms){
    // forms: [one, few, many]
    n = Math.abs(n);
    if (n === 1) return forms[0];
    var n10 = n % 10, n100 = n % 100;
    if (n10 >= 2 && n10 <= 4 && (n100 < 12 || n100 > 14)) return forms[1];
    return forms[2];
  }
  function threeDigitsToWordsPL(n){
    var S = ['','jeden','dwa','trzy','cztery','pięć','sześć','siedem','osiem','dziewięć'];
    var TEEN = ['dziesięć','jedenaście','dwanaście','trzynaście','czternaście','piętnaście','szesnaście','siedemnaście','osiemnaście','dziewiętnaście'];
    var T = ['','dziesięć','dwadzieścia','trzydzieści','czterdzieści','pięćdziesiąt','sześćdziesiąt','siedemdziesiąt','osiemdziesiąt','dziewięćdziesiąt'];
    var H = ['','sto','dwieście','trzysta','czterysta','pięćset','sześćset','siedemset','osiemset','dziewięćset'];
    var h = Math.floor(n/100), d = Math.floor((n%100)/10), u = n%10;
    var parts = [];
    if (h) parts.push(H[h]);
    if (d === 1) { parts.push(TEEN[u]); return parts.join(' '); }
    if (d) parts.push(T[d]);
    if (u) parts.push(S[u]);
    return parts.join(' ');
  }
  function numberToWordsPLInt(n){
    if (n === 0) return 'zero';
    var scales = [
      { one:'', few:'', many:'' },
      { one:'tysiąc', few:'tysiące', many:'tysięcy' },
      { one:'milion', few:'miliony', many:'milionów' },
      { one:'miliard', few:'miliardy', many:'miliardów' }
    ];
    var words = [];
    var i = 0;
    while (n > 0) {
      var chunk = n % 1000;
      if (chunk) {
        var chunkWords = threeDigitsToWordsPL(chunk);
        var form = pluralForm(chunk, [scales[i].one, scales[i].few, scales[i].many]);
        if (i === 1 && chunk === 1) { // 1 tysiąc (bez "jeden")
          words.unshift(form);
        } else if (i > 0 && form) {
          words.unshift((chunkWords ? (chunkWords+' ') : '') + form);
        } else {
          words.unshift(chunkWords);
        }
      }
      n = Math.floor(n/1000); i++;
    }
    return words.filter(Boolean).join(' ').replace(/\s+/g,' ').trim();
  }
  function renderAmountInWords(){
    var $box = $('#amount-in-words'); if(!$box.length) return;
    var grossStr = $('#sum-gross').val()||'0,00';
    var gross = parseFloat(String(grossStr).replace(',','.')||'0')||0;
    var cur = ($('#currency').val()||'PLN').toUpperCase();
    var intPart = Math.floor(gross + 0.0000001);
    var frac = Math.round((gross - intPart) * 100);
    if (cur === 'PLN'){
      var zlForms = ['złoty','złote','złotych'];
      var grForms = ['grosz','grosze','groszy'];
      var words = numberToWordsPLInt(intPart);
      var zl = pluralForm(intPart, zlForms);
      var gr = pluralForm(frac, grForms);
      var text = (words ? (words + ' ' + zl) : 'zero ' + zl) + ' ' + (frac<10?('0'+frac):frac) + ' ' + gr;
      $box.text('Kwota słownie: ' + text);
    } else {
      // Fallback: pokaż kwotę liczbowo z kodem waluty
      var formatted = gross.toFixed(2).replace('.', ',');
      $box.text('Kwota słownie: ' + formatted + ' ' + cur);
    }
  }

  // ====== PRODUKT: INIT SELECT2 DLA WIERSZA ======
  // Ostatnio używane produkty (localStorage)
  function getRecentProducts(){
    try { return JSON.parse(localStorage.getItem('recentProducts')||'[]'); } catch(e){ return []; }
  }
  function saveRecentProduct(d){
    if (!d || !d.id) return;
    // ignore placeholder "NEW:" ids
    if (String(d.id).indexOf('NEW:') === 0) return;
    var list = getRecentProducts().filter(function(x){ return x.id !== d.id; });
    var entry = {
      id: d.id,
      text: d.text || d.name || '',
      price: (typeof d.price !== 'undefined') ? Number(d.price) : (typeof d.net_price !== 'undefined' ? Number(d.net_price) : null),
      vat_id: d.vat_id || d.vat_code_id || null
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
        // Apply selection: set Select2 value and row fields
        // Update hidden name
        $tr.find('.item-name-hidden').val(p.text || '');
        // VAT first
        if (p.vat_id) { $tr.find('.item-vatcode').val(p.vat_id); }
        // Price according to current mode
        var mode = ($tr.find('.item-price-mode').val() || 'net');
        var rate = toNum(vatRates[p.vat_id], 0);
        var netPrice = toNum(p.price, 0);
        var disp = (mode === 'gross') ? +(netPrice * (1 + rate/100)).toFixed(2) : +netPrice.toFixed(2);
        $tr.find('.item-price').val(disp.toFixed(2));
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
                  text: p.text || (p.code ? p.code + ' - ' + p.name : p.name) 
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
      // Track manual typing so we can persist name if user closes without selecting
      var $searchField = $dd.find('.select2-search__field');
      $searchField.off('input.manualName').on('input.manualName', function(){ $sel.data('lastQuery', (this.value||'').toString()); });
      $searchField.off('blur.manualName').on('blur.manualName', function(){
        var q = ($sel.data('lastQuery')||'').trim();
        if (!q) return;
        if (!$nameHidden.val()) {
          $nameHidden.val(q);
          var optVal = 'NEW:'+q;
          $sel.find('option[value="'+optVal+'"]').remove();
          var opt = new Option(q, optVal, true, true);
          $sel.append(opt).trigger('change');
        }
      });
    })
    .on('select2:close', function(){
      var q = ($sel.data('lastQuery')||'').trim();
      if (!q) return;
      if (!$nameHidden.val()) {
        $nameHidden.val(q);
        var optVal = 'NEW:'+q;
        $sel.find('option[value="'+optVal+'"]').remove();
        var opt = new Option(q, optVal, true, true);
        $sel.append(opt).trigger('change');
      }
    })
    .on('select2:select', function (e) {
      var d = (e.params && e.params.data) || {};
      if (String(d.id || '').indexOf('NEW:') === 0) {
        var name = d.text || String(d.id).slice(4);
        $nameHidden.val(name);
      } else {
        $nameHidden.val(d.name || d.text || '');
        // Set VAT first (if provided)
        var $vat = $tr.find('.item-vatcode');
        if ($vat.length && d.vat_id) { $vat.val(d.vat_id); }
        // Adjust displayed price based on selected mode (net/gross)
        if (typeof d.price !== 'undefined' && d.price !== null && d.price !== '') {
          var netPrice = Number(d.price) || 0;
          var mode = ($tr.find('.item-price-mode').val() || 'net');
          var vatId = $tr.find('.item-vatcode').val();
          var rate = toNum(vatRates[vatId], 0);
          var disp = (mode === 'gross') ? +(netPrice * (1 + rate/100)).toFixed(2) : +netPrice.toFixed(2);
          $tr.find('.item-price').val(disp.toFixed(2));
        }
        // Save to recent products
        saveRecentProduct(d);
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
  $tr.on('input change', '.item-qty,.item-price,.item-disc,.item-vatcode,.item-price-mode', function(){ rowCalc($tr); allCalc(); });
    // apply stored default mode to first row
    $tr.find('.item-price-mode').val(getDefaultPriceMode()).trigger('change');
    $tr.find('.btn-remove').on('click', function(){ var rows=countItemRows(); if(rows>1){ $tr.remove(); allCalc(); guardMinRows(); } });
    rowCalc($tr); allCalc(); guardMinRows();
  })();

  // ====== DODAJ WIERSZ ======
  $('#btn-add-item').on('click', function () {
    var $addRow = $('#btn-add-item').closest('tr');
    var html = '' +
      '<tr class="item-row" draggable="true">' +
        '<td>' +
          '<div class="d-flex align-items-center gap-1">'+
            '<span class="drag-handle text-muted" title="Przeciągnij, aby zmienić kolejność" role="button"><i class="ri-drag-move-2-line"></i></span>'+
            '<select class="form-select item-product-select" data-index="'+idx+'" data-placeholder="Wybierz lub wpisz produkt"></select>' +
          '</div>'+
          '<input type="hidden" name="items['+idx+'][name]" class="item-name-hidden">' +
        '</td>' +
        '<td><input name="items['+idx+'][quantity]" type="number" step="0.001" value="1" class="form-control text-end item-qty" required></td>' +
        '<td>'+
          '<div class="d-flex align-items-center gap-1">'+
            '<input name="items['+idx+'][price]" type="number" step="0.01" value="0" class="form-control text-end item-price" required>'+
            '<select name="items['+idx+'][price_mode]" class="form-select item-price-mode" style="width:auto; min-width:92px">'+
              '<option value="net" selected>Netto</option>'+
              '<option value="gross">Brutto</option>'+
            '</select>'+
          '</div>'+
        '</td>' +
        '<td class="vat-cell"><?= str_replace(["\\","'"], ["\\\\","\\'"], $vatSelectHtml) ?></td>' +
        '<td><input name="items['+idx+'][discount_percent]" type="number" step="0.01" value="0" class="form-control text-end item-disc"></td>' +
        '<td><input class="form-control text-end item-net" value="0.00" readonly></td>' +
        '<td><input class="form-control text-end item-gross" value="0.00" readonly></td>' +
        '<td class="gtu-cell"><?= str_replace(["\\","'"], ["\\\\","\\'"], $gtuSelectHtml) ?></td>' +
        '<td><div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-icon btn-secondary-light btn-duplicate" title="Duplikuj"><i class="ri-file-copy-line"></i></button><button type="button" class="btn btn-sm btn-icon btn-danger-light btn-remove" title="Usuń"><i class="ri-delete-bin-5-line"></i></button></div></td>' +
      '</tr>';
    $addRow.before(html.replaceAll('items[0][vat_code_id]', 'items['+idx+'][vat_code_id]').replaceAll('items[0][gtu_code]', 'items['+idx+'][gtu_code]'));
    var $tr = $addRow.prev();
    initProductSelectForRow($tr);
  $tr.on('input change', '.item-qty,.item-price,.item-disc,.item-vatcode,.item-price-mode', function(){ rowCalc($tr); allCalc(); });
    // default mode for new row
    $tr.find('.item-price-mode').val(getDefaultPriceMode()).trigger('change');
  $tr.find('.btn-remove').on('click', function(){ var rows=countItemRows(); if(rows>1){ $tr.remove(); allCalc(); guardMinRows(); } });
    rowCalc($tr); allCalc(); guardMinRows();
    idx++;
  });

  // Toolbar handlers
  updatePriceModeToolbar();
  $('#price-mode-toggle').on('click', '[data-mode]', function(){
    var mode = $(this).data('mode');
    var needRecalc = false;
    $itemsBody.find('tr').each(function(){
      var $tr = $(this);
      if ($tr.find('.item-price-mode').length){
        var cur = ($tr.find('.item-price-mode').val() || 'net');
        if (cur !== mode) { needRecalc = true; return false; }
      }
    });
    setDefaultPriceMode(mode);
    updatePriceModeToolbar();
    if (!needRecalc) { return; }
    // Show confirmation modal; on confirm, recalc all rows to selected mode
    var $modal = $('#price-recalc-modal');
    if ($modal.length){
      $modal.data('target-mode', mode).modal('show');
    } else {
      // Fallback: immediate recalc if modal is not present
      $itemsBody.find('tr').each(function(){
        var $tr = $(this);
        if ($tr.find('.item-price-mode').length){ convertRowPriceMode($tr, mode); }
      });
      allCalc();
    }
  });

  // Confirm modal: recalc rows to selected global mode
  $(document).on('click', '#price-recalc-confirm', function(){
    var $modal = $('#price-recalc-modal');
    var mode = $modal.data('target-mode') || getDefaultPriceMode();
    $itemsBody.find('tr').each(function(){
      var $tr = $(this);
      if ($tr.find('.item-price-mode').length){ convertRowPriceMode($tr, mode); }
    });
    allCalc();
    $modal.modal('hide');
  });

  // ====== „Dodaj produkt” (ikona w wierszu) ======
  $itemsBody.on('click', '.btn-new-product', function(){ currentProductRow = $(this).closest('tr'); $('#product-create-modal').modal('show'); });

  // ====== Duplikuj wiersz ======
  $itemsBody.on('click', '.btn-duplicate', function(){
    var $src = $(this).closest('tr');
    var $addRow = $('#btn-add-item').closest('tr');
    // Dodaj nowy pusty wiersz (jak przy plusie)
    $('#btn-add-item').trigger('click');
    var $dst = $addRow.prev();
    // Skopiuj wartości z wiersza źródłowego
    $dst.find('.item-qty').val($src.find('.item-qty').val());
    $dst.find('.item-price').val($src.find('.item-price').val());
    $dst.find('.item-price-mode').val($src.find('.item-price-mode').val());
    $dst.find('.item-disc').val($src.find('.item-disc').val());
    $dst.find('.item-vatcode').val($src.find('.item-vatcode').val());
    $dst.find('.item-name-hidden').val($src.find('.item-name-hidden').val());
    // Select2 produktu: ustaw taką samą opcję
    var $srcSel = $src.find('.item-product-select');
    var pid = $srcSel.val();
    var ptext = ($srcSel.find('option:selected').text() || $src.find('.item-name-hidden').val() || '').trim();
    var $dstSel = $dst.find('.item-product-select');
    if (pid) {
      $dstSel.find('option[value="'+pid+'"]').remove();
      var opt = new Option(ptext, pid, true, true);
      $dstSel.append(opt).trigger('change');
    }
    rowCalc($dst); allCalc(); guardMinRows();
  });

  // ====== Drag & drop reorder ======
  var dragArmed = false, dragSrc = null;
  $itemsBody.on('mousedown', '.drag-handle', function(){ dragArmed = true; });
  $itemsBody.on('dragstart', 'tr.item-row', function(e){
    if (!dragArmed) { e.preventDefault(); return; }
    dragSrc = this;
    e.originalEvent.dataTransfer.effectAllowed = 'move';
    $(this).addClass('dragging');
  });
  $(document).on('mouseup', function(){ dragArmed = false; });
  $itemsBody.on('dragover', 'tr.item-row', function(e){ e.preventDefault(); e.originalEvent.dataTransfer.dropEffect='move'; });
  $itemsBody.on('drop', 'tr.item-row', function(e){
    e.preventDefault();
    var src = dragSrc; var dst = this;
    if (!src || src === dst) return;
    var $dst = $(dst);
    var mid = $dst.offset().top + $dst.outerHeight()/2;
    if (e.originalEvent.clientY > mid) { $dst.after(src); } else { $dst.before(src); }
    $(src).removeClass('dragging'); dragSrc = null; dragArmed = false;
    allCalc(); guardMinRows();
  });
  $itemsBody.on('dragend', 'tr.item-row', function(){ $(this).removeClass('dragging'); dragSrc=null; dragArmed=false; });

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
    var $f = $(this);
    
    // Generate a simple code if not provided
    var name = $f.find('[name="name"]').val() || '';
    var code = $f.find('[name="code"]').val();
    if (!code && name) {
      code = name.replace(/[^a-zA-Z0-9]/g, '').substring(0, 10).toUpperCase();
      $f.find('[name="code"]').val(code);
    }
    
    $.ajax({
      url: $f.attr('action'),
      method: 'POST',
      data: new FormData(this),
      processData: false, contentType: false,
      headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
    }).done(function (data) {
      if (data && data.success && currentProductRow && data.product) {
  var product = data.product;
  var name = product.name || name;
  var netPrice = parseFloat(product.net_price || '0') || 0;
        
  // Update the current row with product data
  currentProductRow.find('.item-name-hidden').val(name);
        
  // Set VAT if provided first, then compute displayed price based on mode
  var $vat = currentProductRow.find('.item-vatcode'); 
  var vatId = product.vat_id;
  if ($vat.length && vatId) $vat.val(vatId);
  var mode = (currentProductRow.find('.item-price-mode').val() || 'net');
  var effectiveVatId = currentProductRow.find('.item-vatcode').val();
  var rate = toNum(vatRates[effectiveVatId], 0);
  var disp = (mode === 'gross') ? +(netPrice * (1 + rate/100)).toFixed(2) : +netPrice.toFixed(2);
  currentProductRow.find('.item-price').val(disp.toFixed(2));
        
        // Update Select2 with new product
        if ($.fn && $.fn.select2) { 
          var $sel = currentProductRow.find('.item-product-select'); 
          var displayText = '';
          if (product.code) {
            displayText = product.code + ' - ' + name;
          } else {
            displayText = name;
          }
          if (product.is_service) {
            displayText += ' (usługa)';
          }
          var opt = new Option(displayText, product.id, true, true); 
          $sel.append(opt).trigger('change'); 
        }
        
        // Save to recent products
        saveRecentProduct({ id: product.id, text: name, price: netPrice, vat_id: product.vat_id });

        rowCalc(currentProductRow); 
        allCalc(); 
        $('#product-create-modal').modal('hide'); 
        $f[0].reset();
        toast(data.message || 'Produkt został dodany.');
      } else { 
        toast(data.message || 'Nie udało się dodać produktu.'); 
      }
    }).fail(function (xhr) { 
      console.error('product add fail', xhr.status, xhr.responseText); 
      toast('Błąd komunikacji przy dodawaniu produktu.'); 
    });
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
  $form.on('submit', function (e) {
    var rows = countItemRows();
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
    var el = document.getElementById(id); if (el) new bootstrap.Popover(el, {container:'body', sanitize:false});
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
    // Jeśli preset jest wybrany (nie "_custom"), użyj preset-u
    if ($duePreset.val() && $duePreset.val() !== '_custom') {
      recomputeFromPreset(); 
    } else if ($dueDate.val()) {
      recomputeFromDate(); 
    } else {
      recomputeFromPreset(); 
    }
  }, 0);
// ====== SELECT2: Seria faktury ======
if ($.fn && $.fn.select2) {
  var kind = '<?= h($kind ?? "vat") ?>';
  var $series = $('#series-select').select2({
    placeholder: 'Wybierz serię',
    allowClear: true,
    width: '100%',
    ajax: {
      url: seriesSearchUrl,
      dataType: 'json',
      delay: 200,
      data: function (params) {
        var payload = { q: (params.term || ''), limit: 20 };
        if (kind === 'currency') payload.type = 'currency';
        else if (kind === 'vat') payload.type = 'vat';
        return payload;
      },
      processResults: function (data) {
        // oczekiwany format: [{id: '2025/10', text:'2025/10', pattern:'...', next_no:123}, ...]
        var items = $.map(data.results || data || [], function (s) {
          return { 
            id: s.id || s.name || s.text, 
            text: s.text || s.name || s.id, 
            pattern: s.template || s.pattern, 
            next_no: s.start_no || s.next_no,
            is_default: s.is_default || false
          };
        });
        
        // Automatycznie wybierz domyślną serię przy pierwszym załadowaniu
        if (!$('#series-select').val() && items.length > 0) {
          var defaultItem = items.find(function(item) { return item.is_default; });
          if (defaultItem) {
            setTimeout(function() {
              var opt = new Option(defaultItem.text, defaultItem.id, true, true);
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
  .on('select2:open', function(){
    injectSeriesToolbar();
  });

  // jeśli mamy w entity bieżącą wartość „series” i nie ma jej na liście, dodać jako wybraną opcję:
  (function preloadSeries(){
    var cur = $series.data('current') || $series.val() || '<?= h($invoice->series ?? '') ?>';
    if (cur) {
      var opt = new Option(cur, cur, true, true);
      $series.append(opt).trigger('change');
    } else {
      // Jeśli nie ma wcześniej wybranej serii, spróbuj załadować domyślną
      loadDefaultSeries();
    }
  })();

  // Funkcja ładowania domyślnej serii
  function loadDefaultSeries() {
    $.ajax({
      url: seriesSearchUrl,
      dataType: 'json',
      data: (function(){
        var d = { q: '', limit: 50 };
        if (kind === 'currency') d.type = 'currency';
        else if (kind === 'vat') d.type = 'vat';
        return d;
      })()
    }).done(function(data) {
      var items = data.results || data || [];
      var defaultItem = items.find(function(item) { return item.is_default; });
      if (defaultItem) {
        var opt = new Option(defaultItem.text || defaultItem.name, defaultItem.id || defaultItem.name, true, true);
        $series.append(opt).trigger('change');
      }
    });
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
  /* Highlight rows when filtering by VAT chip */
  #items-body tr.highlight-vat-row {
    background-color: rgba(255, 193, 7, 0.15); /* bootstrap warning tint */
  }
  .drag-handle{ cursor: grab; }
  .item-row.dragging{ opacity: .7; }
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

<!-- Floating Calculator Panel -->
<div id="calc-panel" class="calc-panel shadow rounded d-none" role="dialog" aria-label="Kalkulator">
  <div class="calc-header d-flex justify-content-between align-items-center px-2 py-1">
    <div class="fw-medium"><i class="ri-calculator-line me-1"></i> Kalkulator</div>
    <div class="d-flex align-items-center gap-1">
      <button type="button" class="btn btn-sm btn-light" id="calc-insert" title="Wstaw do aktywnego pola"><i class="ri-input-cursor-move"></i></button>
      <button type="button" class="btn btn-sm btn-light" id="calc-close" title="Zamknij"><i class="ri-close-line"></i></button>
    </div>
  </div>
  <div class="calc-body p-2">
    <input type="text" id="calc-input" class="form-control mb-2" placeholder="Wpisz działanie, np. 10*1.23" autocomplete="off">
    <div class="calc-grid">
      <button class="btn btn-light" data-key="7">7</button>
      <button class="btn btn-light" data-key="8">8</button>
      <button class="btn btn-light" data-key="9">9</button>
      <button class="btn btn-secondary" data-key="/">/</button>

      <button class="btn btn-light" data-key="4">4</button>
      <button class="btn btn-light" data-key="5">5</button>
      <button class="btn btn-light" data-key="6">6</button>
      <button class="btn btn-secondary" data-key="*">*</button>

      <button class="btn btn-light" data-key="1">1</button>
      <button class="btn btn-light" data-key="2">2</button>
      <button class="btn btn-light" data-key="3">3</button>
      <button class="btn btn-secondary" data-key="-">-</button>

  <button class="btn btn-warning" id="calc-clear" title="Wyczyść">C</button>
  <button class="btn btn-light" data-key="0">0</button>
  <button class="btn btn-secondary" data-key="%" title="Procent">%</button>
  <button class="btn btn-secondary" data-key="+">+</button>

  <button class="btn btn-light" data-key="(">(</button>
  <button class="btn btn-light" data-key=")">)</button>
  <button class="btn btn-light" id="calc-back" title="Kasuj znak">⌫</button>
  <button class="btn btn-primary" id="calc-eq" title="Oblicz">=</button>
    </div>
  </div>
</div>

<script>
$(function(){
  // Calculator panel logic
  var $panel = $('#calc-panel');
  var $input = $('#calc-input');
  var lastFocused = null;

  // Restore position and visibility
  (function initCalc(){
    try{
      var pos = JSON.parse(localStorage.getItem('calcPanelPos')||'null');
      if (pos && typeof pos.left==='number' && typeof pos.top==='number') {
        // Switch to left/top positioning explicitly
        $panel.css({ right:'auto', bottom:'auto', left: pos.left+'px', top: pos.top+'px' });
      }
      var open = localStorage.getItem('calcPanelOpen') === '1';
      if (open) { $panel.removeClass('d-none'); }
    }catch(e){}
  })();

  // Toggle via footer button
  $('#btn-calc-toggle').on('click', function(){
    $panel.toggleClass('d-none');
    // If becoming visible and no stored left/top, keep default right/bottom; focus input
    try{ localStorage.setItem('calcPanelOpen', $panel.hasClass('d-none') ? '0' : '1'); }catch(e){}
    if (!$panel.hasClass('d-none')) { $input.focus().select(); }
  });
  $('#calc-close').on('click', function(){
    $panel.addClass('d-none');
    try{ localStorage.setItem('calcPanelOpen','0'); }catch(e){}
  });

  // Track last focused input (outside calculator)
  $(document).on('focusin', 'input,textarea', function(){
    if ($(this).closest('#calc-panel').length) return;
    lastFocused = this;
  });

  // Insert into active field
  $('#calc-insert').on('click', function(){
    if (!lastFocused) { if (typeof toast==='function') toast('Brak aktywnego pola.'); return; }
    var val = ($input.val()||'').trim();
    if (!val) return;
    try { $(lastFocused).val(val).trigger('change'); } catch(e){}
  });

  // Keypad clicks
  $panel.on('click', '[data-key]', function(){
    var k = $(this).data('key');
    if (typeof k === 'undefined') return;
    var s = $input.val() || '';
    $input.val(s + String(k));
    $input.focus();
  });

  // Backspace
  $('#calc-back').on('click', function(){
    var s = $input.val() || '';
    $input.val(s.slice(0, -1)).focus();
  });

  // Clear
  $('#calc-clear').on('click', function(){ $input.val('').focus(); });

  // Evaluate
  function evalExpr(expr){
    var src = String(expr||'').replace(/\s+/g,' ').trim();
    if (!src) return '';
    // allow only digits, operators, parentheses, comma/dot
  if (!/^[0-9+\-*/().,%\s]+$/.test(src)) throw new Error('Niedozwolone znaki');
  src = src.replace(/,/g, '.');
  // Support percent: replace number% with (number/100)
  src = src.replace(/(\d+(?:\.\d+)?)%/g, '($1/100)');
    var res = Function('return ('+src+')')();
    if (!isFinite(res)) throw new Error('Błąd obliczeń');
    return res;
  }
  function doEval(){
    try{
      var res = evalExpr($input.val());
      if (res === '') return;
      // Keep as typed precision; format to up to 8 decimals
      var out = (Math.round(res * 100000000) / 100000000).toString();
      $input.val(out);
    } catch(e){
      $input.addClass('is-invalid');
      setTimeout(function(){ $input.removeClass('is-invalid'); }, 600);
      if (typeof toast==='function') toast(e.message || 'Błąd');
    }
  }
  $('#calc-eq').on('click', doEval);
  $input.on('keydown', function(ev){
    if (ev.key === 'Enter') { ev.preventDefault(); doEval(); }
    if (ev.key === 'Escape') { $panel.addClass('d-none'); try{ localStorage.setItem('calcPanelOpen','0'); }catch(e){} }
  });

  // Dragging
  (function enableDrag(){
    var dragging = false, sx=0, sy=0, sl=0, st=0;
    function rectPos(){ var r = $panel[0].getBoundingClientRect(); return { left: r.left, top: r.top }; }
    function save(){
      var r = rectPos();
      try{ localStorage.setItem('calcPanelPos', JSON.stringify({ left: Math.round(r.left), top: Math.round(r.top) })); }catch(e){}
    }
    $panel.find('.calc-header').on('mousedown', function(ev){
      dragging = true; sx = ev.clientX; sy = ev.clientY;
      // Use current viewport coords; ensure switching to left/top positioning
      var r = rectPos(); sl = r.left; st = r.top;
      $panel.css({ right:'auto', bottom:'auto', left: sl+'px', top: st+'px' });
      $('body').addClass('user-select-none');
      ev.preventDefault();
    });
    $(document).on('mousemove.calcdrag', function(ev){
      if (!dragging) return;
      var dx = ev.clientX - sx, dy = ev.clientY - sy;
      var nl = sl + dx, nt = st + dy;
      // clamp within viewport
      var vw = $(window).width(), vh = $(window).height();
      var pw = $panel.outerWidth(), ph = $panel.outerHeight();
      nl = Math.max(8, Math.min(vw - pw - 8, nl));
      nt = Math.max(8, Math.min(vh - ph - 8, nt));
      $panel.css({ left: nl+'px', top: nt+'px' });
    });
    $(document).on('mouseup.calcdrag', function(){ if (dragging){ dragging=false; $('body').removeClass('user-select-none'); save(); } });
  })();
});
</script>

<style>
  .calc-panel{ position: fixed; right: 24px; bottom: 96px; width: 280px; background:#fff; z-index: 1055; border:1px solid rgba(0,0,0,.1); box-sizing: border-box; overflow: hidden; }
  .calc-header{ cursor: move; background:#f8f9fa; border-bottom:1px solid rgba(0,0,0,.1); }
  .calc-grid{ display:grid; grid-template-columns: repeat(4, 1fr); gap:.35rem; }
  .calc-grid .btn{ padding: .45rem .5rem; }
  .user-select-none{ user-select: none !important; }
</style>
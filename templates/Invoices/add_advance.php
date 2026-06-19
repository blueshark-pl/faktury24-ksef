<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Invoice $invoice
 * @var array $vats id => label
 * @var array $vatRatesMap id => rate
 * @var bool|null $isEdit
 */
$__isEdit = !empty($isEdit) || !$invoice->isNew();
$__isFinal = (strtolower((string)($invoice->type ?? '')) === 'final') || (!empty($invoice->is_final) && (int)$invoice->is_final === 1);
$__pageTitle = $__isEdit ? ($__isFinal ? 'Edytuj fakturę końcową' : 'Edytuj fakturę zaliczkową') : 'Faktura zaliczkowa';
$this->assign('title', $__pageTitle);
?>

<?= $this->Form->create($invoice, ['class' => 'needs-validation', 'novalidate' => true]) ?>
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2"><?= h($__pageTitle) ?></h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item" aria-current="page"><a href="javascript:void(0);">Faktury</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= $__isFinal ? 'Końcowa' : 'Zaliczka' ?></li>
    </ol>
  </div>
  <div class="btn-list">
    <?= $this->Form->button('Zapisz', ['class' => 'btn btn-primary', 'id' => 'save-btn']) ?>
  </div>
</div>

<div class="row">
  <div class="col-xxl-8">
    <div class="card custom-card">
      <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="invTabs" role="tablist">
          <li class="nav-item"><button class="nav-link active" id="tab-basic" data-bs-toggle="tab" data-bs-target="#pane-basic" type="button" role="tab">Podstawowe</button></li>
          <li class="nav-item"><button class="nav-link" id="tab-annotations" data-bs-toggle="tab" data-bs-target="#pane-annotations" type="button" role="tab">Adnotacje</button></li>
        </ul>
      </div>
      <div class="card-body tab-content">
        <div class="tab-pane fade show active" id="pane-basic" role="tabpanel" aria-labelledby="tab-basic">
        <div class="vstack gap-3">
        <div class="mb-2">
          <div class="invoice-pills d-flex flex-wrap gap-2">
            <div class="pill pill-accent">
              <span class="pill-label">Brutto</span>
              <span class="pill-value" id="pill-gross">0,00</span>
            </div>
          </div>
        </div>

        <div>
          <label class="form-label">Wybierz proformę / ofertę</label>
          <select id="proforma-select" class="form-select" data-placeholder="Szukaj po numerze lub ID"></select>
          <?= $this->Form->hidden('proforma_id', ['id' => 'proforma-id', 'value' => $invoice->parent_id ?? '']) ?>
          <small class="text-muted d-block mt-1">Proforma musi być wystawiona wcześniej. Faktura zaliczkowa zostanie z nią powiązana.</small>
        </div>

  <div id="proforma-summary" class="border rounded p-2" style="display:none;"></div>
  <div id="final-lockout" class="alert alert-warning mt-2" style="display:none;"></div>
  <div id="contractor-changed-notice" class="alert alert-warning d-flex align-items-start gap-2 mt-2 mb-0 py-2" role="alert" style="display:none!important;">
    <i class="ri-alert-line fs-5 flex-shrink-0 mt-1"></i>
    <div>
      <strong>Dane kontrahenta mogą być nieaktualne.</strong>
      Kontrahent zmienił dane po wystawieniu proformy. Faktura zaliczkowa zostanie wystawiona z danymi z proformy (snapshot).
      <div id="contractor-diff-detail" class="mt-1 small font-monospace"></div>
    </div>
  </div>

  <div id="adv-locked" class="locked-region">

        <div class="row g-2 align-items-end">
          <div class="col-md-5">
            <?= $this->Form->control('advance_gross', [
              'label' => $__isFinal ? 'Kwota końcowa (brutto)' : 'Kwota zaliczki (brutto)',
              'type' => 'number',
              'step' => '0.01',
              'class' => 'form-control',
              'value' => $invoice->isNew() ? 0 : ($invoice->advance_gross ?? ($invoice->total ?? 0))
            ]) ?>
            <div id="overpay-warning" class="alert alert-warning mt-2 p-2 mb-0" style="display:none; font-size:13px;">
              <div class="d-flex align-items-start gap-2">
                <i class="ri-error-warning-line fs-16 mt-1 flex-shrink-0"></i>
                <div>
                  <strong>Kwota przekracza wartość oferty</strong>
                  <div class="mt-1">Aktualna wartość oferty: <strong id="overpay-proforma-total">—</strong>.
                    Możesz zaktualizować wartość oferty do wpisanej kwoty i wystawić dokument.</div>
                  <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="update-proforma-total-chk">
                    <label class="form-check-label" for="update-proforma-total-chk">
                      Zaktualizuj wartość oferty do <strong id="overpay-new-total">—</strong>
                    </label>
                  </div>
                </div>
              </div>
            </div>
            <input type="hidden" name="update_proforma_total" id="update-proforma-total" value="0">
            <input type="hidden" name="new_proforma_total" id="new-proforma-total" value="0">
          </div>
          <div class="col-12 d-flex gap-2 align-items-center mt-2">
            <?= $this->Form->hidden('is_final', ['id' => 'is-final', 'value' => $__isFinal ? 1 : 0]) ?>
            <button type="button" id="btn-finalize" class="btn btn-outline-success btn-sm" <?= $__isEdit ? 'disabled' : '' ?>>
              <i class="ri-check-double-line me-1"></i> Zapłacono całość (ustaw pozostałą kwotę)
            </button>
            <small class="text-muted"><?= $__isEdit ? 'W trybie edycji nie przełączamy typu dokumentu; możesz ręcznie skorygować kwotę.' : 'Ustawi kwotę na pozostałą do rozliczenia. Przy 100% wybierzesz: faktura zaliczkowa 100% lub rozliczeniowa/końcowa.' ?></small>
            <span id="final-badge" class="badge bg-success" style="<?= $__isFinal ? '' : 'display:none;' ?>">Faktura końcowa</span>
          </div>
          <div id="hundred-choice" class="alert alert-info mt-2 mb-0 py-2" role="alert" style="display:none;">
            <div class="fw-medium mb-2">
              <i class="ri-question-line me-1"></i>
              Kwota pokrywa 100% wartości proformy. <strong>Czy towar został już wydany lub usługa wykonana?</strong>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="hundred_choice" id="hundred-no" value="no" checked>
              <label class="form-check-label" for="hundred-no">
                <strong>Nie</strong> — wystaw <strong>fakturę zaliczkową 100%</strong> (towar nie został wydany / usługa niewykonana). Podaj datę otrzymania zapłaty.
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="hundred_choice" id="hundred-yes" value="yes">
              <label class="form-check-label" for="hundred-yes">
                <strong>Tak</strong> — wystaw <strong>fakturę rozliczeniową/końcową</strong> (wymagana data dokonania dostawy / wykonania usługi).
              </label>
            </div>
          </div>
          <!-- Zaliczki pokrywają 100% — możliwość późniejszej faktury rozliczeniowej (art. 106f ust. 3) -->
          <div id="settlement-prepaid" class="alert alert-secondary mt-2 mb-0 py-2" role="alert" style="display:none;">
            <div class="fw-medium mb-1"><i class="ri-information-line me-1"></i> Wcześniejsze zaliczki rozliczają 100% wartości proformy.</div>
            <div class="small mb-2">Możesz wystawić fakturę rozliczeniową/końcową, która rozlicza wystawione zaliczki (kwota pozostała do zapłaty = 0).</div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="settlement-prepaid-chk">
              <label class="form-check-label fw-medium" for="settlement-prepaid-chk">
                Wystaw fakturę rozliczeniową/końcową (rozlicza zaliczki)
              </label>
            </div>
            <div class="small text-muted mt-1">Wymagana data dokonania dostawy / wykonania usługi.</div>
          </div>
        </div>


        <div class="row g-3 mt-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Seria</label>
            <?= $this->Form->hidden('invoice_series_id', [
              'id'    => 'invoice-series-id-hidden',
              'value' => $invoice->invoice_series_id ?? null,
            ]) ?>
            <div class="input-group">
              <select id="series-select" name="series" class="form-select" data-placeholder="Wybierz serię"></select>
            </div>
            <small class="text-muted" id="invoice-number-hint" style="display:none;">
              <i class="ri-information-line"></i> Proponowany numer: <span id="invoice-number-suggestion"></span>
            </small>
          </div>
          <div class="col-md-2">
            <?= $this->Form->control('date', ['type' => 'date', 'label' => 'Data wystawienia', 'class' => 'form-control', 'id' => 'issue-date', 'value' => $invoice->isNew() ? date('Y-m-d') : ($invoice->date ? $invoice->date->format('Y-m-d') : date('Y-m-d'))]) ?>
            <small class="text-muted">&nbsp;</small>
          </div>
          <div class="col-md-3" id="sold-date-wrap" style="<?= $__isFinal ? '' : 'display:none;' ?>">
            <?= $this->Form->control('sold_date', [
              'type' => 'date',
              'label' => 'Data dokonania / zakończenia dostawy lub wykonania usługi',
              'class' => 'form-control', 'id' => 'sold-date',
              'value' => !empty($invoice->sold_date) ? $invoice->sold_date->format('Y-m-d') : date('Y-m-d'),
            ]) ?>
            <small class="text-muted">Wymagane dla faktury rozliczeniowej/końcowej.</small>
          </div>
          <div class="col-md-4">
            <?= $this->Form->control('paymentdate', [
              'type' => 'date',
              'label' => 'Termin płatności',
              'class' => 'form-control',
              'id' => 'payment-date',
              'value' => $invoice->isNew() ? date('Y-m-d', strtotime('+7 days')) : ($invoice->paymentdate ? $invoice->paymentdate->format('Y-m-d') : date('Y-m-d', strtotime('+7 days')))
            ]) ?>
            <small class="text-muted">Możesz zmienić domyślny termin (7 dni).</small>
          </div>
        </div>

        <!-- Płatność / data otrzymania zaliczki -->
        <?php $__isPaid = !empty($invoice->advance_received_date) || (string)($invoice->paymentstate ?? '') === 'paid'; ?>
        <div class="row g-3 mt-1 align-items-end">
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="adv-paid-chk" name="advance_paid"
                     value="1" <?= $__isPaid ? 'checked' : '' ?>>
              <label class="form-check-label fw-medium" for="adv-paid-chk">
                <?= $__isFinal ? 'Należność końcowa opłacona' : 'Zaliczka opłacona' ?>
              </label>
            </div>
          </div>
          <div id="adv-paid-fields" class="col-md-4" style="<?= $__isPaid ? '' : 'display:none;' ?>">
            <?= $this->Form->control('advance_received_date', [
              'type'  => 'date',
              'label' => $__isFinal ? 'Data opłacenia faktury końcowej' : 'Data otrzymania zaliczki',
              'class' => 'form-control',
              'id'    => 'advance-received-date',
              'value' => !empty($invoice->advance_received_date) ? $invoice->advance_received_date->format('Y-m-d') : date('Y-m-d'),
            ]) ?>
            <small class="text-muted"><?= $__isFinal ? 'Data zapłaty — wysyłana do KSeF (DataZaplaty)' : 'Wymagane dla KSeF (P_6) — data wpływu zaliczki' ?></small>
          </div>
          <div class="col-12" id="adv-unpaid-hint" style="<?= $__isPaid ? 'display:none;' : '' ?>">
            <small class="text-muted"><i class="ri-information-line"></i> Zaznacz powyżej gdy zaliczka wpłynęła — data zostanie wysłana do KSeF jako P_6.</small>
          </div>
        </div>

        <?= $this->Form->control('description', ['label' => 'Uwagi (opcjonalnie)', 'type' => 'textarea', 'rows' => 2, 'class' => 'form-control']) ?>
  </div><!-- /#adv-locked -->
        </div><!-- /.vstack -->
        </div><!-- /#pane-basic -->

        <!-- ADNOTACJE -->
        <?= $this->element('Invoices/tab_annotations') ?>

        <!-- IDENTYFIKATORY MIĘDZYNARODOWE -->
        <?= $this->element('Invoices/tab_identifiers') ?>


      </div><!-- /.card-body.tab-content -->
    </div>
  </div>
  <div class="col-xxl-4">
    <div class="card custom-card">
      <div class="card-header"><div class="card-title">Nabywca</div></div>
      <div class="card-body">
        <?php $__ic = $invoice->invoice_contractor ?? null; ?>
        <div id="ctr-empty-hint" class="small text-muted mb-2<?= $__ic ? ' d-none' : '' ?>">Wybierz proformę, aby wczytać dane nabywcy.</div>
        <div class="vstack gap-2">
          <div>
            <label class="form-label mb-1">Nazwa <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" name="invoice_contractor[name]" id="ctr-hidden-name" value="<?= h($__ic?->name ?? '') ?>" required>
          </div>
          <div>
            <label class="form-label mb-1">NIP</label>
            <input type="text" class="form-control form-control-sm" name="invoice_contractor[nip]" id="ctr-hidden-nip" value="<?= h($__ic?->nip ?? '') ?>">
          </div>
          <div>
            <label class="form-label mb-1">Ulica</label>
            <input type="text" class="form-control form-control-sm" name="invoice_contractor[street]" id="ctr-hidden-street" value="<?= h($__ic?->street ?? '') ?>">
          </div>
          <div class="row g-2">
            <div class="col-5">
              <label class="form-label mb-1">Kod pocztowy</label>
              <input type="text" class="form-control form-control-sm" name="invoice_contractor[zip]" id="ctr-hidden-zip" value="<?= h($__ic?->zip ?? '') ?>">
            </div>
            <div class="col-7">
              <label class="form-label mb-1">Miasto</label>
              <input type="text" class="form-control form-control-sm" name="invoice_contractor[city]" id="ctr-hidden-city" value="<?= h($__ic?->city ?? '') ?>">
            </div>
          </div>
          <div>
            <?= $this->element('Invoices/contractor_country_select', ['value' => $__ic?->country ?? 'PL', 'selectId' => 'ctr-hidden-country']) ?>
          </div>
          <div>
            <label class="form-label mb-1">E-mail</label>
            <input type="email" class="form-control form-control-sm" name="invoice_contractor[email]" id="ctr-hidden-email" value="<?= h($__ic?->email ?? '') ?>">
          </div>
          <div>
            <label class="form-label mb-1">Telefon</label>
            <input type="text" class="form-control form-control-sm" name="invoice_contractor[phone]" id="ctr-hidden-phone" value="<?= h($__ic?->phone ?? '') ?>">
          </div>
          <div>
            <label class="form-label mb-1">Nr konta</label>
            <input type="text" class="form-control form-control-sm" name="invoice_contractor[account_number]" id="ctr-hidden-account-number" value="<?= h($__ic?->account_number ?? '') ?>">
          </div>
        </div>
      </div>
    </div>
  </div>
<?= $this->Form->end() ?>

<!-- Toast container -->
<div id="toast-area" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1100;"></div>
              <div id="proforma-issued" class="border rounded p-2 mt-2" style="display:none;"></div>

<!-- Modal: Nowa seria (skrócona wersja) -->
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
        'id' => 'series-create-form-adv'
      ]) ?>
      <div class="modal-body">
        <?= $this->Form->control('name', [
          'label' => 'Nazwa/oznaczenie serii*', 'required' => true, 'class' => 'form-control'
        ]) ?>
        <?= $this->Form->control('series_template', [
          'label' => 'Wzór numeracji (opcjonalnie)', 'class' => 'form-control', 'placeholder' => '[numer]/[rok]'
        ]) ?>
        <div class="row mt-2">
          <div class="col-md-6">
            <?= $this->Form->control('starting_number', [
              'label' => 'Numer początkowy', 'type' => 'number', 'class' => 'form-control', 'min' => 1, 'value' => 1
            ]) ?>
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check mt-3">
              <?= $this->Form->checkbox('is_default', [ 'class' => 'form-check-input', 'id' => 'series-is-default-adv' ]) ?>
              <label class="form-check-label" for="series-is-default-adv">Ustaw jako domyślną</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', ['class' => 'btn btn-primary', 'escapeTitle' => false]) ?>
      </div>
      <?= $this->Form->end() ?>
    </div>
  </div>
  </div>

<script>
(function(){
  var isEdit = <?= json_encode($__isEdit) ?>;
  var currentIsFinal = <?= json_encode($__isFinal) ?>;
  var currentInvoiceId = <?= json_encode($__isEdit ? (string)($invoice->id ?? '') : '') ?>;
  var csrf = $('meta[name="csrfToken"]').attr('content') || '';
  var searchUrl = '<?= $this->Url->build(["controller"=>"Invoices","action"=>"proformaSearch","_ext"=>"json"]) ?>';
  var detailsUrlBase = '<?= $this->Url->build(["controller"=>"Invoices","action"=>"proformaDetails"]) ?>';
  var printUrlBase   = '<?= $this->Url->build(["controller"=>"Invoices","action"=>"print"]) ?>';
  var seriesSearchUrl = '<?= $this->Url->build(["controller"=>"InvoiceSeries","action"=>"search","_ext"=>"json"]) ?>';
  var seriesAddUrl    = '<?= $this->Url->build(["controller"=>"InvoiceSeries","action"=>"add","_ext"=>"json"]) ?>';
  var seriesNextNumberUrl = '<?= $this->Url->build(["controller"=>"InvoiceSeries","action"=>"nextNumber","_ext"=>"json"]) ?>';
  var proformaTotal = 0;
  var advancesTotal = 0;
  var remainingTotal = 0;
  // Pamiętamy ostatnio używaną "zaliczkową" serię, aby móc do niej wrócić po odznaczeniu końcowej
  var lastAdvanceSeries = null; // { id, text }
  var finalExists = false;

  function setFormLocked(locked){
    if (isEdit) {
      // W edycji nie blokujemy formularza na podstawie istnienia końcowej
      return;
    }
    var $form = $('form');
    var $controls = $form.find('input, select, textarea, button');
    // leave proforma select enabled to allow switching offers
    var $keepEnabled = $('#proforma-select');
    if (locked) {
      $controls.prop('disabled', true);
      $keepEnabled.prop('disabled', false);
      $('#save-btn').prop('disabled', true);
      $('#btn-finalize').prop('disabled', true).attr('title', 'Zablokowane — końcowa już wystawiona').attr('data-bs-toggle','tooltip');
      // ensure select2 reflects disabled state
      $('#series-select').prop('disabled', true).trigger('change.select2');
      setLockedVisuals(true);
    } else {
      $controls.prop('disabled', false);
      $('#btn-finalize').prop('disabled', false).removeAttr('title').removeAttr('data-bs-toggle');
      $('#series-select').prop('disabled', false).trigger('change.select2');
      setLockedVisuals(false);
    }
  }

  // Toast helper (Bootstrap 5)
  function showToast(message, variant){
    try {
      var $area = $('#toast-area');
      if (!$area.length) return;
      var v = variant || 'info';
      var id = 't_'+Date.now()+Math.random().toString(16).slice(2);
      var $toast = $('<div class="toast align-items-center text-bg-'+ v +' border-0" role="alert" aria-live="assertive" aria-atomic="true" id="'+id+'">\
        <div class="d-flex">\
          <div class="toast-body">'+ $('<div>').text(message||'').html() +'</div>\
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>\
        </div>\
      </div>');
      $area.append($toast);
      var toast = new bootstrap.Toast($toast[0], { delay: 2600 });
      toast.show();
      $toast.on('hidden.bs.toast', function(){ $(this).remove(); });
    } catch(_){ /* ignore if bootstrap not available */ }
  }

  if ($.fn && $.fn.select2) {
    $('#proforma-select').select2({
      placeholder: $('#proforma-select').data('placeholder') || 'Szukaj proformy',
      allowClear: true, width: '100%', dropdownAutoWidth: true,
      ajax: {
        url: searchUrl, dataType: 'json', delay: 200, cache: true,
        data: function (params) { return { q: params.term||'' }; },
        processResults: function (data) { return { results: data.results||[] }; }
      }
    }).on('select2:select', function(e){
      var id = e.params && e.params.data && e.params.data.id;
      $('#proforma-id').val(id);
      if (id) { loadDetails(id); }
    }).on('select2:clear', function(){
      $('#proforma-id').val('');
      $('#proforma-summary').hide().empty();
      $('#contractor-changed-notice').css('display','none');
      $('#contractor-diff-detail').empty();
      $('#ctr-hidden-name,#ctr-hidden-nip,#ctr-hidden-street,#ctr-hidden-zip,#ctr-hidden-city,#ctr-hidden-email,#ctr-hidden-phone,#ctr-hidden-account-number').val('');
      $('#ctr-hidden-country').val('PL');
      $('#ctr-empty-hint').removeClass('d-none');
    });
    // Series select
    $('#series-select').select2({
      placeholder: $('#series-select').data('placeholder') || 'Wybierz serię',
      allowClear: true, width: '100%', dropdownAutoWidth: true,
      ajax: {
        url: seriesSearchUrl, dataType: 'json', delay: 200, cache: true,
        data: function (params) { return { q: (params.term||'') }; },
        processResults: function (data) { return { results: (data.results||[]) }; }
      }
    }).on('select2:select', function(){ rememberAdvanceCandidate(); updateNumberHint(); })
      .on('select2:clear', function(){ updateNumberHint(); });
  }

  function loadDetails(id, preserveExisting){
    var detailsUrl = detailsUrlBase + '/' + encodeURIComponent(id) + '.json';
    if (isEdit && currentInvoiceId) { detailsUrl += '?exclude_id=' + encodeURIComponent(currentInvoiceId); }
    $.ajax({ url: detailsUrl, method:'GET', headers:{ 'Accept':'application/json' } })
      .done(function(res){
        if (!res || !res.success) return;
        var p = res.proforma || {};
        finalExists = !!p.final_exists;
        // totals and remaining
        proformaTotal = parseFloat(p.total||0) || 0;
        advancesTotal = parseFloat(p.advances_total||0) || 0;
        remainingTotal = Math.max(0, proformaTotal - advancesTotal);

        if (!isEdit && !preserveExisting) {
          if (remainingTotal > 0) {
            $('[name="advance_gross"]').val(remainingTotal.toFixed(2));
          } else {
            $('[name="advance_gross"]').val('0.00');
          }
          $('#is-final').val(0); // nie oznaczaj automatycznie jako końcowej
          $('#final-badge').hide();
          // finalize button + global lockout
          $('#btn-finalize').prop('disabled', !!finalExists);
          if (finalExists) {
            $('#final-lockout').html('<i class="ri-error-warning-line me-1"></i> Faktura końcowa została już wystawiona dla tej oferty. Formularz zablokowany.').show();
            setFormLocked(true);
          } else {
            $('#final-lockout').hide().empty();
            setFormLocked(false);
          }
        } else {
          // edit mode: tylko informacyjnie
          $('#final-lockout').hide().empty();
          if (currentIsFinal) {
            $('#is-final').val(1);
            $('#final-badge').show();
          }
        }
        recompute();
        // summary
        var html = ''+
          '<div><strong>'+ (p.fullnumber||'') +'</strong> z dnia '+ (p.date||'') +'</div>'+
          '<div>Waluta: '+ (p.currency||'PLN') +', suma brutto: '+ (p.total||0).toFixed ? (p.total).toFixed(2) : p.total +'</div>'+
          '<div>Pozostało do rozliczenia: <strong>'+ (remainingTotal.toFixed ? remainingTotal.toFixed(2) : remainingTotal) +'</strong></div>';
        $('#proforma-summary').html(html).show();
        // wypełnij pola nabywcy z danych proformy
        var c = p.contractor||{};
        $('#ctr-hidden-name').val(c.name||'');
        $('#ctr-hidden-nip').val(c.nip||'');
        $('#ctr-hidden-street').val(c.street||'');
        $('#ctr-hidden-zip').val(c.zip||'');
        $('#ctr-hidden-city').val(c.city||'');
        $('#ctr-hidden-country').val(c.country||'PL');
        $('#ctr-hidden-email').val(c.email||'');
        $('#ctr-hidden-phone').val(c.phone||'');
        $('#ctr-hidden-account-number').val(c.account_number||'');
        if (c.name) { $('#ctr-empty-hint').addClass('d-none'); }

        // Ostrzeżenie o nieaktualnym snapshotu kontrahenta
        if (p.contractor_changed && p.contractor_current) {
          var cc = p.contractor_current;
          var diffHtml = '<strong>Aktualne dane:</strong> ' + (cc.name||'') + ', ' + (cc.street||'') + ', ' + (cc.zip||'') + ' ' + (cc.city||'');
          var diffHtml2 = '<br><strong>Dane z proformy:</strong> ' + (c.name||'') + ', ' + (c.street||'') + ', ' + (c.zip||'') + ' ' + (c.city||'');
          $('#contractor-diff-detail').html(diffHtml + diffHtml2);
          $('#contractor-changed-notice').css('display', 'flex');
        } else {
          $('#contractor-changed-notice').css('display', 'none');
          $('#contractor-diff-detail').empty();
        }
        updateNumberHint();

        // already issued for this offer (children)
        var issued = p.children || [];
        if (issued.length) {
          // helper to format to 2 decimals
          var fmt2 = function(n){ var x = (typeof n === 'number') ? n : parseFloat(n||0) || 0; return x.toFixed(2); };
          var rows = issued.map(function(ch){
            var tLabel = (ch.type === 'final') ? 'Końcowa' : 'Zaliczka';
            var amount = (typeof ch.total === 'number') ? ch.total.toFixed(2) : (ch.total||'');
            var d = ch.date || '';
            var num = ch.fullnumber || ch.id;
            var download = '<a href="'+ printUrlBase + '/' + encodeURIComponent(ch.id) + '?download=1"\
                              target="_blank" class="ms-2" title="Pobierz PDF">\
                              <i class="ri-printer-line"></i></a>';
            // payment state badge
            var state = (ch.paymentstate||'').toLowerCase();
            var badgeClass = 'bg-light text-muted';
            var badgeText  = '—';
            if (state === 'paid') { badgeClass = 'bg-success'; badgeText = 'Opłacona'; }
            else if (state === 'partial') { badgeClass = 'bg-warning'; badgeText = 'Częściowa'; }
            else if (state === 'overdue') { badgeClass = 'bg-dark'; badgeText = 'Po terminie'; }
            else if (state === 'unpaid') { badgeClass = 'bg-danger'; badgeText = 'Nieopłacona'; }
            var badge = '<span class="badge '+ badgeClass +' ms-2">'+ badgeText +'</span>';

            // due info line
            var dueInfo = '';
            if (ch.paymentdate) {
              try {
                var today = new Date();
                var todayMid = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                var due = new Date(ch.paymentdate + 'T00:00:00');
                var diffMs = due.getTime() - todayMid.getTime();
                var diffDays = Math.round(diffMs / 86400000);
                var dueText = '';
                var dueClass = 'text-muted';
                if (diffDays > 0) { dueText = 'Za ' + diffDays + ' dni'; dueClass = (diffDays <= 3 ? 'text-warning' : 'text-muted'); }
                else if (diffDays === 0) { dueText = 'Dziś'; dueClass = 'text-warning'; }
                else { dueText = 'Przeterminowana o ' + Math.abs(diffDays) + ' dni'; dueClass = 'text-danger'; }
                dueInfo = '<div class="small '+ dueClass +'">Termin: ' + $('<div>').text(ch.paymentdate).html() + ' (' + dueText + ')</div>';
              } catch(_) {}
            }

            // paid/remaining info line
            var prInfo = '<div class="small text-muted text-end">Wpłacono: ' + fmt2(ch.alreadypaid) + ' | Pozostało: ' + fmt2(ch.remaining) + '</div>';
            return '<li class="list-group-item d-flex justify-content-between align-items-center">'
              + '<span><span class="badge bg-secondary me-2">'+ tLabel +'</span> '
              + $('<div>').text(num).html() + ' — ' + $('<div>').text(d).html() + badge + dueInfo + '</span>'
              + '<span class="text-end">'
              +   '<div class="fw-semibold">'+ amount + download + '</div>'
              +   prInfo
              + '</span>'
              + '</li>';
          }).join('');
          var box = '<div class="d-flex justify-content-between align-items-center mb-1">'
                  + '<strong>Wystawione dokumenty do tej oferty</strong>'
                  + (p.final_exists ? '<span class="badge bg-success">Końcowa już wystawiona</span>' : '')
                  + '</div>'
                  + '<ul class="list-group list-group-flush">'+ rows +'</ul>';
          $('#proforma-issued').html(box).show();
        } else {
          $('#proforma-issued').hide().empty();
        }
      });
  }

  function recompute(){
    var gross = parseFloat($('[name="advance_gross"]').val()||'0')||0;
    // VAT/netto split computed server-side from proforma; JS shows only gross in pills
    const nf = new Intl.NumberFormat('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    $('#pill-gross').text(nf.format(gross));

    // Overpayment client-side validation
    var overpay = (remainingTotal > 0) && (gross - remainingTotal > 0.005);
    var $amount = $('[name="advance_gross"]');
    var $warn = $('#overpay-warning');
    var $save = $('#save-btn');
    var nf2 = new Intl.NumberFormat('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (overpay) {
      $amount.addClass('is-invalid');
      // Wypełnij dane w panelu
      var newProformaRequired = advancesTotal + gross;
      $('#overpay-proforma-total').text(nf2.format(proformaTotal) + ' ' + ($('[name="currency"]').val() || 'PLN'));
      $('#overpay-new-total').text(nf2.format(newProformaRequired) + ' ' + ($('[name="currency"]').val() || 'PLN'));
      $('#new-proforma-total').val(newProformaRequired.toFixed(2));
      $warn.show();
      // Zezwalaj na zapis tylko jeśli user zaakceptował aktualizację
      var accepted = $('#update-proforma-total-chk').is(':checked');
      $('#update-proforma-total').val(accepted ? '1' : '0');
      $save.prop('disabled', !accepted);
    } else {
      $amount.removeClass('is-invalid');
      $warn.hide();
      $('#update-proforma-total-chk').prop('checked', false);
      $('#update-proforma-total').val('0');
      $save.prop('disabled', false);
    }

    if (!isEdit) {
      $('#settlement-prepaid').hide();
      if (!overpay && remainingTotal > 0) {
        var equalRemaining = (Math.abs(gross - remainingTotal) <= 0.005 && gross > 0);
        if (equalRemaining && !finalExists) {
          // 100% wartości — NIE oznaczamy automatycznie jako końcowej.
          // Pokaż wybór: zaliczkowa 100% (Nie) vs rozliczeniowa/końcowa (Tak).
          $('#hundred-choice').show();
          applyHundredChoice();
        } else {
          // Poniżej 100% — zawsze zaliczkowa
          $('#hundred-choice').hide();
          $('#is-final').val(0);
          $('#final-badge').hide();
          setSoldDateVisible(false);
          ensureAdvanceSeries();
        }
      } else if (remainingTotal <= 0 && advancesTotal > 0 && !finalExists) {
        // Zaliczki pokrywają już 100% — zaproponuj fakturę rozliczeniową (art. 106f ust. 3).
        $('#hundred-choice').hide();
        $('#settlement-prepaid').show();
        applySettlementPrepaid();
      } else {
        // Nadpłata — schowaj wybory (nadpłatę obsługuje osobny panel)
        $('#hundred-choice').hide();
      }
    }
  }

  // Faktura rozliczeniowa po 100% zaliczek — toggle.
  function applySettlementPrepaid(){
    if (isEdit) { return; }
    var on = $('#settlement-prepaid-chk').is(':checked');
    if (on) {
      $('#is-final').val(1);
      $('#final-badge').show();
      setSoldDateVisible(true);
      $('[name="advance_gross"]').val('0.00'); // kwota pozostała do zapłaty = 0
      ensureFinalSeries();
    } else {
      $('#is-final').val(0);
      $('#final-badge').hide();
      setSoldDateVisible(false);
      ensureAdvanceSeries();
    }
  }
  $(document).on('change', '#settlement-prepaid-chk', applySettlementPrepaid);

  // Pokaż/ukryj pole „Data dostawy/wykonania" i ustaw atrybut required.
  function setSoldDateVisible(visible){
    $('#sold-date-wrap').toggle(!!visible);
    var $sd = $('#sold-date');
    if (visible) {
      $sd.attr('required', 'required');
      if (!$sd.val()) { $sd.val(new Date().toISOString().slice(0, 10)); }
    } else {
      $sd.removeAttr('required');
    }
  }

  // Zastosuj wybór z radio „Czy towar wydany / usługa wykonana?".
  function applyHundredChoice(){
    if (isEdit) { return; }
    var yes = $('#hundred-yes').is(':checked');
    if (yes) {
      $('#is-final').val(1);
      $('#final-badge').show();
      setSoldDateVisible(true);
      ensureFinalSeries();
    } else {
      $('#is-final').val(0);
      $('#final-badge').hide();
      setSoldDateVisible(false);
      ensureAdvanceSeries();
    }
  }
  $(document).on('change', 'input[name="hundred_choice"]', applyHundredChoice);
  $(document).on('input change', '[name="advance_gross"]', recompute);
  $(document).on('change', '#update-proforma-total-chk', function(){
    $('#update-proforma-total').val(this.checked ? '1' : '0');
    $('#save-btn').prop('disabled', !this.checked);
  });
  recompute();

  // Next number hint for selected series and date
  function updateNumberHint(){
    var series = $('#series-select').val();
    var $hint = $('#invoice-number-hint');
    var $suggestion = $('#invoice-number-suggestion');
    var issueDate = $('#issue-date').val() || '';
    if (!series) { $hint.hide(); return; }
    $.ajax({ url: seriesNextNumberUrl, method:'GET', data:{ series: series, date: issueDate }, headers:{ 'Accept':'application/json' } })
      .done(function(res){ if(res && res.success){ $suggestion.text(res.formatted||''); $hint.show(); } else { $hint.hide(); } })
      .fail(function(){ $hint.hide(); });
  }
  $(document).on('change', '#issue-date', updateNumberHint);
  $(updateNumberHint);

  // Sync minimum payment date with issue date
  function syncPaymentMin(){
    var $issue = $('#issue-date');
    var $pay   = $('#payment-date');
    if ($pay.length && $issue.length) {
      var d = $issue.val();
      if (d) {
        $pay.attr('min', d);
        if ($pay.val() && $pay.val() < d) { $pay.val(d); }
      }
    }
  }
  $(document).on('change', '#issue-date', syncPaymentMin);
  $(syncPaymentMin);

  // Finalization button
  $('#btn-finalize').on('click', function(){
    if (isEdit) {
      return;
    }
    if (finalExists) {
      showToast('Końcowa już wystawiona — nie można utworzyć kolejnej.', 'warning');
      return;
    }
    if (remainingTotal > 0) {
      $('[name="advance_gross"]').val(remainingTotal.toFixed(2));
      // Nie wymuszamy końcowej — recompute() pokaże wybór (zaliczkowa 100% / rozliczeniowa).
      recompute();
      showToast('Ustawiono pełną pozostałą kwotę — wybierz rodzaj faktury poniżej.', 'info');
    }
  });

  // Checkbox "Zaliczka opłacona" — show/hide advance_received_date
  $('#adv-paid-chk').on('change', function(){
    var checked = $(this).is(':checked');
    $('#adv-paid-fields').toggle(checked);
    $('#adv-unpaid-hint').toggle(!checked);
    if (!checked) {
      // wyczyść datę żeby nie trafiła do XML/paymentstate
      $('#advance-received-date').val('');
    } else {
      // ustaw dzisiaj jeśli pole puste
      if (!$('#advance-received-date').val()) {
        $('#advance-received-date').val(new Date().toISOString().slice(0, 10));
      }
    }
  });

  // Init for edit: if proforma already set, load details and preselect
  $(function(){
    var pid = $('#proforma-id').val();
    if (pid) {
      loadDetails(pid, true);
      // try to show it as selected in select2
      try {
        $.ajax({ url: detailsUrlBase + '/' + encodeURIComponent(pid) + '.json', method:'GET', headers:{ 'Accept':'application/json' } })
          .done(function(res){
            var p = (res && res.success) ? (res.proforma||{}) : {};
            var label = (p.fullnumber || p.id || pid);
            var opt = new Option(label, pid, true, true);
            $('#proforma-select').append(opt).trigger('change');
          });
      } catch(_){ }
    }
    if (currentIsFinal) {
      $('#final-badge').show();
    }
  });

  // Series select — identical UX to add.php (search, default pick, toolbar to add series)
  if ($.fn && $.fn.select2) {
    var $series = $('#series-select');
    try { $series.select2('destroy'); } catch(_){ }
    $series.select2({
      placeholder: $series.data('placeholder') || 'Wybierz serię',
      allowClear: true,
      width: '100%',
      ajax: {
        url: seriesSearchUrl,
        dataType: 'json', delay: 200, cache: true,
        data: function (params) { return { q: (params.term||''), limit: 20 }; },
        processResults: function (data) {
          var items = $.map((data.results||data||[]), function (s) {
            return {
              id: s.id,   // UUID — wysyłamy jako invoice_series_id
              text: s.text || s.name || s.id,
              name: s.name || s.text,
              pattern: s.template || s.pattern,
              next_no: s.start_no || s.next_no,
              is_default: !!s.is_default,
              type: s.type || ''
            };
          });
          return { results: items };
        }
      },
      minimumInputLength: 0,
      escapeMarkup: function (m) { return m; },
      templateResult: function (d) {
        if (!d.id) return d.text;
        var meta = [];
        if (d.pattern) meta.push('<span class="text-muted small">wzór: '+ $('<div>').text(d.pattern).html() +'</span>');
        if (d.next_no) meta.push('<span class="text-muted small ms-2">nast.: '+ d.next_no +'</span>');
        return $('<div>'+ $('<div>').text(d.text).html() +' '+ (meta.join(' ')||'') +'</div>')[0];
      }
    }).on('select2:open', function(){ injectSeriesToolbar(); })
      .on('select2:select', function(e){
        var d = (e.params && e.params.data) || {};
        $('#invoice-series-id-hidden').val(d.id || '');
        // also fill series name field for display/hint purposes
        var $nameInput = $('[name="series"]').not('#series-select');
        if ($nameInput.length) { $nameInput.val(d.name || d.text || ''); }
        rememberAdvanceCandidate(); updateNumberHint();
      })
      .on('select2:clear', function(){
        $('#invoice-series-id-hidden').val('');
        updateNumberHint();
      });
    $series.on('change', function(){
      if (!$(this).val()) { $('#invoice-series-id-hidden').val(''); }
    });

    // Auto-load default series on page load — preferuje serię typu 'advance'
    function loadDefaultSeriesAdv() {
      $.ajax({ url: seriesSearchUrl, dataType: 'json', data: { q: '', limit: 50, type: 'advance' } })
        .done(function(data) {
          var items = data.results || data || [];
          if (!items.length) {
            // Fallback: pobierz wszystkie serie i szukaj zaliczkowej po nazwie
            $.ajax({ url: seriesSearchUrl, dataType: 'json', data: { q: '', limit: 50 } })
              .done(function(d2) { applyDefaultSeriesAdv(d2.results || d2 || []); })
              .fail(function(){ $series.prop('disabled', false); });
            return;
          }
          applyDefaultSeriesAdv(items);
        })
        .fail(function(){ $series.prop('disabled', false); });
    }
    function applyDefaultSeriesAdv(items) {
      if (!items.length) { $series.prop('disabled', false); return; }
      var mapped = $.map(items, function(s) {
        return { id: s.id, text: s.text || s.name, name: s.name || s.text, pattern: s.template||s.pattern, next_no: s.start_no||s.next_no, is_default: !!s.is_default, type: s.type||'' };
      });
      var existingId = '<?= h($invoice->invoice_series_id ?? '') ?>';
      var existingName = '<?= h($invoice->series ?? '') ?>';
      var pick = null;
      if (existingId) {
        pick = mapped.find(function(i){ return i.id === existingId; });
      }
      if (!pick && existingName) {
        pick = mapped.find(function(i){ return i.name === existingName || i.text === existingName; });
      }
      // Preferuj serię zaliczkową z flagą default, potem zaliczkową, potem default, potem pierwszą
      if (!pick) { pick = mapped.find(function(i){ return looksAdvanceObj(i) && i.is_default; }); }
      if (!pick) { pick = mapped.find(function(i){ return looksAdvanceObj(i); }); }
      if (!pick) { pick = mapped.find(function(i){ return i.is_default; }); }
      if (!pick) { pick = mapped[0]; }
      if (pick) {
        var opt = new Option(pick.text, pick.id, true, true);
        $(opt).data('data', pick);
        $series.append(opt).trigger('change');
        $('#invoice-series-id-hidden').val(pick.id);
        rememberAdvanceCandidate();
        updateNumberHint();
      }
    }
    // Only auto-load on new invoice; on edit the series is pre-set
    if (!isEdit) {
      loadDefaultSeriesAdv();
    } else {
      // Edit mode: restore existing series as Select2 option
      var curId   = '<?= h($invoice->invoice_series_id ?? '') ?>';
      var curName = '<?= h($invoice->series ?? '') ?>';
      if (curId && curName) {
        var opt = new Option(curName, curId, true, true);
        $series.append(opt).trigger('change');
        $('#invoice-series-id-hidden').val(curId);
        rememberAdvanceCandidate();
      }
    }

    // Helpery detekcji typu serii po nazwie/etykiecie
    function isFinalText(txt){ return /(końcowa|koncowa|final)/i.test(txt||''); }
    function isAdvanceText(txt){ return /(zaliczka|zaliczkow|advance)/i.test(txt||''); }
    function looksFinalObj(s){
      if (!s) return false;
      if (s.type && /(final)/i.test(s.type)) return true;
      var n = (s.text||s.name||'');
      return /(końcowa|koncowa|final)/i.test(n);
    }
    function looksAdvanceObj(s){
      if (!s) return false;
      if (s.type && /(zaliczka|advance)/i.test(s.type)) return true;
      var n = (s.text||s.name||'');
      return /(zaliczka|zaliczkow|advance)/i.test(n);
    }

    // Zapamiętaj aktualnie wybraną serię jako potencjalną zaliczkową
    function rememberAdvanceCandidate(){
      var t = ($series.find('option:selected').text()||'');
      var v = ($series.val()||'');
      if (!t) return;
      // Preferujemy serie, które wyglądają na zaliczkowe
      if (isAdvanceText(t) && !isFinalText(t)) {
        lastAdvanceSeries = { id: v, text: t };
      }
    }

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
        var inst = $series.data('select2');
        var query = inst && inst.dropdown && inst.dropdown.$search ? inst.dropdown.$search.val() : '';
        $('#series-create-modal').modal('show');
        $('#series-create-form-adv input[name="name"]').val(query || '').trigger('input');
        try { $series.select2('close'); } catch(_){}
      });
    }

    // Try to switch to a 'final' series automatically when final is selected
    function ensureFinalSeries(){
      if (finalExists) { showToast('Końcowa już wystawiona — utrzymuję serię zaliczkową.', 'secondary'); return; }
      var currentText = ($series.find('option:selected').text()||'').toLowerCase();
      var isFinalLike = /końcowa|koncowa|final/.test(currentText);
      if (isFinalLike) { updateNumberHint(); return; }
      // Zanim przełączymy na końcową, zapamiętaj obecną serię jako zaliczkową (jeśli spełnia kryteria)
      rememberAdvanceCandidate();
      // fetch series and pick a suitable final-like one
      $.ajax({ url: seriesSearchUrl, dataType: 'json', data: { q: '', limit: 50 } })
        .done(function(data){
          var items = (data && data.results) || [];
          if (!items.length) return;
    var pick = items.find(function(s){ return s.is_default && looksFinalObj(s); })
      || items.find(function(s){ return looksFinalObj(s); });
          if (pick) {
            var id = pick.id || pick.name || pick.text;
            var text = pick.text || pick.name || id;
            var before = $series.val();
            if (before != id) {
              $series.find('option[value="'+id+'"]').remove();
              var opt = new Option(text, id, true, true);
              $series.append(opt).trigger('change');
              showToast('Seria przełączona na końcową.', 'success');
            }
            $('#invoice-series-id-hidden').val(pick.id || '');
            updateNumberHint();
          }
        });
    }
    function ensureAdvanceSeries(){
      var currentText = ($series.find('option:selected').text()||'').toLowerCase();
      var isFinalLike = /końcowa|koncowa|final/.test(currentText);
      if (!isFinalLike) { return; }
      // 1) Spróbuj wrócić do ostatniej zaliczkowej serii
      if (lastAdvanceSeries && lastAdvanceSeries.id) {
        var id1 = lastAdvanceSeries.id; var text1 = lastAdvanceSeries.text || lastAdvanceSeries.id;
        var before1 = $series.val();
        if (before1 != id1) {
          $series.find('option[value="'+id1+'"]').remove();
          var opt1 = new Option(text1, id1, true, true);
          $series.append(opt1).trigger('change');
          showToast('Seria przywrócona na zaliczkową.', 'info');
        }
        $('#invoice-series-id-hidden').val(id1 || '');
        updateNumberHint();
        return;
      }
      // 2) Jeśli nie mamy zapamiętanej – pobierz listę i wybierz najlepszą kandydatkę
      $.ajax({ url: seriesSearchUrl, dataType: 'json', data: { q: '', limit: 50 } })
        .done(function(data){
          var items = (data && data.results) || [];
          if (!items.length) return;
          var pick = items.find(function(s){ return looksAdvanceObj(s); });
          if (pick) {
            var id = pick.id || pick.name || pick.text;
            var text = pick.text || pick.name || id;
            var before = $series.val();
            if (before != id) {
              $series.find('option[value="'+id+'"]').remove();
              var opt = new Option(text, id, true, true);
              $series.append(opt).trigger('change');
              showToast('Seria przełączona na zaliczkową.', 'info');
            }
            $('#invoice-series-id-hidden').val(pick.id || '');
            updateNumberHint();
          } else {
            showToast('Brak serii zaliczkowej na liście — pozostawiono bieżącą.', 'secondary');
          }
        });
    }
  }

  // Client-side validation before submit
  function validateAdvanceForm(){
    var errors = 0;
    var $proforma = $('#proforma-id');
    var $amount = $('[name="advance_gross"]');
    var $series = $('#series-select');
    var $date = $('#issue-date');

    function markInvalid($el){ $el.addClass('is-invalid'); errors++; }
    function unmark($el){ $el.removeClass('is-invalid'); }
    function markInvalidSelect2($sel){ $sel.addClass('is-invalid'); $sel.next('.select2-container').addClass('is-invalid-select'); errors++; }
    function unmarkSelect2($sel){ $sel.removeClass('is-invalid'); $sel.next('.select2-container').removeClass('is-invalid-select'); }

    unmark($amount); unmark($date); unmarkSelect2($series);

    if (!$proforma.val()) { markInvalid($('#proforma-select')); }
    var gross = parseFloat($amount.val()||'0')||0;
    // Faktura rozliczeniowa po 100% zaliczek ma kwotę pozostałą = 0 — wtedy 0 jest dozwolone.
    var isSettlementPrepaid = ($('#is-final').val() === '1') && (remainingTotal <= 0);
    if (gross <= 0 && !isSettlementPrepaid) { markInvalid($amount); }
    var overpayAllowed = $('#update-proforma-total-chk').is(':checked');
    if (remainingTotal > 0 && gross - remainingTotal > 0.005 && !overpayAllowed) { markInvalid($amount); }
    if (!$series.val()) { markInvalidSelect2($series); }
    if (!$date.val()) { markInvalid($date); }
    // Faktura rozliczeniowa/końcowa wymaga daty dostawy/wykonania usługi
    if ($('#is-final').val() === '1') {
      var $sold = $('#sold-date');
      unmark($sold);
      if (!$sold.val()) { markInvalid($sold); }
    }

    if (errors > 0) {
      showToast('Uzupełnij wymagane pola ('+errors+').', 'danger');
      return false;
    }
    return true;
  }

  $('form').on('submit', function(e){
    if (!validateAdvanceForm()) { e.preventDefault(); e.stopImmediatePropagation(); return false; }
  });

  // Create series inline (modal)
  $('#series-create-form-adv').on('submit', function(e){
    e.preventDefault();
    var $f = $(this);
    $.ajax({ url: $f.attr('action'), method:'POST', data: new FormData(this), processData:false, contentType:false, headers:{ 'X-CSRF-Token': csrf, 'Accept':'application/json' } })
      .done(function(data){
        if (data && data.success && data.series) {
          var name = data.series.name || '';
          // Insert/Select new series
          var $sel = $('#series-select');
          var opt = new Option(name, name, true, true);
          $sel.append(opt).trigger('change');
          $('#series-create-modal').modal('hide');
        } else {
          alert(data && data.message ? data.message : 'Nie udało się dodać serii.');
        }
      })
      .fail(function(){ alert('Błąd komunikacji przy dodawaniu serii.'); });
  });

  // Visuals: dim and mark fields as locked with tooltip icons
  function setLockedVisuals(locked){
    var $region = $('#adv-locked');
    if (!$region.length) return;
    $region.toggleClass('locked', !!locked);
    // Clean previous icons and tooltips
    $region.find('.form-label .lock-icon').each(function(){
      var inst = bootstrap && bootstrap.Tooltip ? bootstrap.Tooltip.getInstance(this) : null;
      if (inst) inst.dispose();
      $(this).remove();
    });
    if (locked) {
      var tooltipText = 'Zablokowane — faktura końcowa została już wystawiona';
      $region.find('.form-label').each(function(){
        var $lbl = $(this);
        // Avoid duplicate
        if ($lbl.find('.lock-icon').length) return;
        var $icon = $('<i class="ri-lock-line lock-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="'+ tooltipText +'"></i>');
        $lbl.append($icon);
      });
      // Init tooltips
      if (window.bootstrap && bootstrap.Tooltip) {
        [].slice.call(document.querySelectorAll('#adv-locked .lock-icon[data-bs-toggle="tooltip"]')).forEach(function(el){
          new bootstrap.Tooltip(el);
        });
      }
    }
  }
})();
</script>

<style>
.invoice-pills .pill{ display:flex; align-items:center; gap:.5rem; background:#f8fafc; border:1px solid #eef1f4; border-radius:12px; padding:.5rem .75rem; }
.invoice-pills .pill-accent{ background:#eef6ff; border-color:#d7e9ff; }
.invoice-pills .pill-label{ font-size:.75rem; color:#6c757d; }
.invoice-pills .pill-value{ font-weight:700; font-variant-numeric: tabular-nums; }
/* highlight select2 when invalid */
.is-invalid-select .select2-selection { border-color: #dc3545 !important; box-shadow: 0 0 0 .25rem rgba(220,53,69,.25); }
/* Locked visuals */
.locked-region.locked { opacity: .6; transition: opacity .2s ease; }
.form-label .lock-icon { color:#6c757d; font-size:.9em; margin-left:.35rem; cursor: help; }
</style>

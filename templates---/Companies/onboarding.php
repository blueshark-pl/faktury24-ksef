<?php
/**
 * @var \App\Model\Entity\Company $company
 * @var \App\Model\Entity\CompanyBankAccount $bankAccount
 */
$this->assign('title', 'Onboarding firmy');
?>

<!-- Page Header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Ustawienia profilu firmy</h1>
    <nav>
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
        <li class="breadcrumb-item active" aria-current="page">Onboarding firmy</li>
      </ol>
    </nav>
  </div>
  <div class="btn-list"></div>
</div>

<?= $this->Flash->render() ?>

<!-- Top info alert -->
<div class="alert alert-primary alert-dismissible fade show custom-alert-icon shadow-sm mb-4" role="alert">
  <svg class="svg-primary me-2" xmlns="http://www.w3.org/2000/svg" height="1.25rem" viewBox="0 0 24 24" width="1.25rem" fill="currentColor">
    <path d="M0 0h24v24H0z" fill="none"/><path d="M12 2a10 10 0 1010 10A10.011 10.011 0 0012 2zm1 15h-2v-6h2zm0-8h-2V7h2z"/>
  </svg>
  <strong>Uzupełnij dane firmy</strong> – to jednorazowy krok wymagany do korzystania z Faktury24.
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"><i class="bi bi-x"></i></button>
</div>

<!-- Steps -->
<div class="card custom-card mb-4">
  <div class="card-body py-3">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="d-flex align-items-center gap-2">
        <span class="badge rounded-pill bg-primary">1</span><span class="fw-medium">Dane firmy</span>
      </div>
      <div class="text-muted">—</div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge rounded-pill bg-secondary">2</span><span class="fw-medium">Rachunek bankowy</span>
      </div>
      <div class="text-muted">—</div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge rounded-pill bg-secondary">3</span><span class="fw-medium">Preferencje</span>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Left -->
  <div class="col-xl-3">
    <div class="position-sticky" style="top: 88px;">
      <div class="card custom-card">
        <div class="card-body">
          <ul class="nav flex-column gap-2 nav-pills tab-style-7" role="tablist">
            <li class="nav-item me-0" role="presentation">
              <a class="nav-link d-inline-flex w-100 mb-1 bg-light active" id="account" data-bs-toggle="tab"
                 data-bs-target="#account-pane" role="tab" aria-controls="account-pane">
                <i class="ri-building-4-line me-2"></i>Dane firmy
              </a>
            </li>
            <li class="nav-item me-0" role="presentation">
              <a class="nav-link bg-light d-inline-flex w-100 mb-1" id="bank-tab" data-bs-toggle="tab"
                 data-bs-target="#bank-tab-pane" role="tab" aria-controls="bank-tab-pane">
                <i class="ri-bank-line me-2"></i>Rachunek bankowy
              </a>
            </li>
            <li class="nav-item me-0" role="presentation">
              <a class="nav-link bg-light d-inline-flex w-100" id="preferences-tab" data-bs-toggle="tab"
                 data-bs-target="#preferences-tab-pane" role="tab" aria-controls="preferences-tab-pane">
                <i class="ri-settings-3-line me-2"></i>Preferencje
              </a>
            </li>
          </ul>
        </div>
      </div>

      <div class="card custom-card">
        <div class="card-header justify-content-between">
          <div class="card-title">Podpowiedzi</div>
        </div>
        <div class="card-body small text-muted">
          <div class="mb-2"><i class="ri-information-line me-1"></i> Wymagane: <strong>Nazwa</strong> (oraz <strong>NIP</strong> jeżeli VAT).</div>
          <div><i class="ri-time-line me-1"></i> Rachunek bankowy możesz dodać później w „Konta bankowe”.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right -->
  <div class="col-xl-9">
    <div class="card custom-card">
      <?= $this->Form->create(null, [
           'url' => ['controller' => 'Companies', 'action' => 'saveOnboarding'],
           'class' => 'p-0 needs-validation', // bootstrap validation
           'novalidate' => true,
           'autocomplete' => 'on',
      ]) ?>

      <div class="p-3 border-bottom border-top border-block-end-dashed tab-content">

        <!-- Dane firmy -->
        <div class="tab-pane show active overflow-hidden p-0 border-0" id="account-pane" role="tabpanel" aria-labelledby="account" tabindex="0">
          <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-1">
            <div class="fw-semibold d-block fs-15">Dane firmy</div>
            <span class="badge bg-light text-muted">krok 1/3</span>
          </div>

          <div class="row gy-3">
            <div class="col-xl-8">
              <?= $this->Form->control('company.name', [
                    'label' => 'Nazwa firmy <span class="text-danger" data-bs-toggle="tooltip" title="Pole wymagane">*</span>',
                    'escape' => false,
                    'required' => true,
                    'class' => 'form-control',
                    'placeholder' => 'np. ACME Sp. z o.o.',
                    'value' => $company->name ?? '',
              ]) ?>
              <div class="form-text">Pełna nazwa zgodna z dokumentami rejestrowymi.</div>
              <div class="invalid-feedback">Podaj nazwę firmy.</div>
            </div>
            <div class="col-xl-4">
              <?= $this->Form->control('company.altname', [
                    'label' => 'Nazwa skrócona',
                    'class' => 'form-control',
                    'placeholder' => 'np. ACME',
              ]) ?>
            </div>

            <div class="col-xl-4">
              <label class="form-label">NIP</label>
              <div class="input-group">
                <?= $this->Form->control('company.nip', [
                      'label' => false,
                      'class' => 'form-control',
                      'placeholder' => '6571234567',
                      'maxlength' => 10,
                      'pattern' => '\\d{10}',
                      'templates' => ['inputContainer' => '{{content}}']
                ]) ?>
                <button class="btn btn-outline-secondary" type="button" id="company-gus-fetch">
                  <span class="spinner-border spinner-border-sm me-1 d-none" id="company-gus-spin"></span>
                  <i class="ri-database-2-line me-1"></i> Pobierz z GUS
                </button>
              </div>
              <div class="form-text">10 cyfr (bez myślników). Dla zagranicznych pozostaw puste. Możesz też pobrać dane z GUS.</div>
              <div class="invalid-feedback">NIP powinien mieć 10 cyfr.</div>
            </div>
            <div class="col-xl-4">
              <?= $this->Form->control('company.regon', [
                    'label' => 'REGON',
                    'class' => 'form-control',
                    'maxlength' => 14,
              ]) ?>
            </div>
            <div class="col-xl-4">
              <?= $this->Form->control('company.country', [
                    'label' => 'Kraj',
                    'class' => 'form-control',
                    'value' => 'PL',
              ]) ?>
            </div>

            <div class="col-xl-3">
              <?= $this->Form->control('company.postal_code', [
                    'label' => 'Kod pocztowy',
                    'class' => 'form-control',
                    'placeholder' => '25-111',
                    'pattern' => '\d{2}-\d{3}',
              ]) ?>
              <div class="invalid-feedback">Format: 12-345.</div>
            </div>
            <div class="col-xl-5">
              <?= $this->Form->control('company.city', [
                    'label' => 'Miasto',
                    'class' => 'form-control',
              ]) ?>
            </div>
            <div class="col-xl-4">
              <?= $this->Form->control('company.street', [
                    'label' => 'Ulica',
                    'class' => 'form-control',
                    'placeholder' => 'np. Lipowa',
              ]) ?>
            </div>
            <div class="col-xl-4">
              <?= $this->Form->control('company.local_number', [
                    'label' => 'Nr lokalu',
                    'class' => 'form-control',
                    'placeholder' => 'np. 12A',
              ]) ?>
            </div>

            <div class="col-xl-4">
              <?= $this->Form->control('company.phone', [
                    'label' => 'Telefon',
                    'class' => 'form-control',
                    'placeholder' => '+48 600 000 000',
              ]) ?>
            </div>
            <div class="col-xl-8">
              <?= $this->Form->control('company.issuer', [
                    'label' => 'Wystawca na fakturze',
                    'class' => 'form-control',
                    'placeholder' => 'np. Jan Kowalski',
              ]) ?>
              <div class="form-text">Imię i nazwisko osoby widocznej na fakturze.</div>
            </div>

            <div class="col-xl-12">
              <div class="form-check">
                <?= $this->Form->control('company.vat_payer', [
                      'type' => 'checkbox',
                      'label' => 'Jestem czynnym podatnikiem VAT',
                      'class' => 'form-check-input me-2',
                      'templates' => [
                        'inputContainer' => '<div class="form-check">{{content}}</div>',
                      ],
                ]) ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Bank -->
        <!-- Banki (wiele) -->
        <div class="tab-pane overflow-hidden p-0 border-0" id="bank-tab-pane" role="tabpanel" aria-labelledby="bank-tab" tabindex="0">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-1">
            <div class="fw-semibold d-block fs-15">Rachunki bankowe (opcjonalnie)</div>
            <span class="badge bg-light text-muted">możesz dodać kilka</span>
        </div>

        <div class="alert alert-secondary shadow-sm small mb-3" role="alert">
            Możesz dodać wiele rachunków w różnych walutach. Zaznacz <strong>domyślny</strong>, który będzie drukowany na fakturach.
        </div>

        <!-- radio wskazuje indeks domyślnego -->
        <input type="hidden" name="banks_default" id="banks_default" value="0">

        <div id="banks-list" class="d-flex flex-column gap-3">
            <!-- Wiersz #0 -->
            <div class="bank-item card border-0 shadow-xs" data-index="0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <input class="form-check-input me-1 bank-default" type="radio" name="banks_default_radio" value="0" checked>
                    <span class="badge bg-primary-soft text-primary">Domyślny</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger bank-remove" disabled title="Nie można usunąć jedynego wiersza">
                    <i class="ri-delete-bin-line"></i> Usuń
                </button>
                </div>

                <div class="row gy-3">
                <div class="col-xl-8">
                    <label class="form-label">IBAN</label>
                    <input type="text" name="banks[0][iban]" class="form-control iban-input" placeholder="PL00 0000 0000 0000 0000 0000 0000" maxlength="34">
                    <div class="form-text">np. <code>PL</code> + 26 cyfr. Spacje dozwolone – usuniemy przy zapisie.</div>
                </div>
                <div class="col-xl-4">
                    <label class="form-label">Waluta</label>
                    <select name="banks[0][currency]" class="form-control">
                    <option value="PLN" selected>PLN</option>
                    <option value="EUR">EUR</option>
                    <option value="USD">USD</option>
                    <option value="GBP">GBP</option>
                    </select>
                </div>
                <div class="col-xl-6">
                    <label class="form-label">Nazwa banku</label>
                    <input type="text" name="banks[0][bank_name]" class="form-control" placeholder="np. mBank S.A.">
                </div>
                <div class="col-xl-6">
                    <label class="form-label">Etykieta rachunku</label>
                    <input type="text" name="banks[0][label]" class="form-control" placeholder="Rachunek główny">
                </div>
                </div>
            </div>
            </div>
        </div>

        <div class="mt-3">
            <button type="button" class="btn btn-outline-primary btn-sm" id="bank-add">
            <i class="ri-add-line"></i> Dodaj kolejny rachunek
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="bank-add-clipboard">
                <i class="ri-clipboard-line"></i> Dodaj z schowka
            </button>
            <script>
            (function(){
                const btn = document.getElementById('bank-add-clipboard');
                const addBtn = document.getElementById('bank-add');
                const list = document.getElementById('banks-list');
                if (!btn || !addBtn || !list || !navigator.clipboard) return;
                btn.addEventListener('click', async ()=>{
                    try{
                    const txt = await navigator.clipboard.readText();
                    addBtn.click();
                    const last = list.querySelector('.bank-item:last-child .iban-input');
                    if (last){ last.value = txt; last.dispatchEvent(new Event('input',{bubbles:true})); last.focus(); }
                    }catch(e){ console.warn('Clipboard niedostępny', e); }
                });
            })();
            </script>

        </div>
        </div>


        <!-- Preferencje -->
        <div class="tab-pane overflow-hidden p-0 border-0" id="preferences-tab-pane" role="tabpanel" aria-labelledby="preferences-tab" tabindex="0">
          <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-1">
            <div class="fw-semibold d-block fs-15">Preferencje faktur</div>
            <span class="badge bg-light text-muted">krok 3/3</span>
          </div>

          <div class="row gy-3">
            <div class="col-xl-6">
              <?= $this->Form->control('company.invoice_template', [
                    'label' => 'Szablon faktury',
                    'class' => 'form-control',
                    'type' => 'select',
                    'options' => ['default' => 'Domyślny', 'modern' => 'Modern', 'pro' => 'Pro'],
                    'empty' => false,
                    'value' => 'default',
              ]) ?>
            </div>
            <div class="col-xl-6">
              <?= $this->Form->control('company.subscription_end', [
                    'label' => 'Ważność abonamentu (info)',
                    'type' => 'date',
                    'class' => 'form-control',
              ]) ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card-footer border-top-0 d-flex justify-content-between align-items-center">
  <div class="small text-success">
    <i class="ri-shield-check-line me-1"></i> Twoje dane są bezpieczne.
  </div>        <div class="btn-list">
          <a href="<?= $this->Url->build('/') ?>" class="btn btn-secondary btn-wave">Anuluj</a>
          <button id="onboarding-submit" class="btn btn-primary btn-wave">
            <span class="submit-text">Zapisz i przejdź dalej</span>
            <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
          </button>
        </div>
      </div>
        <?php
            $this->Form->unlockField('banks');
            $this->Form->unlockField('banks_default');
        ?>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>

<script>
(function() {
  // tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });

  // scroll to top on tab show
  document.querySelectorAll('.nav-pills [data-bs-toggle="tab"]').forEach((el) => {
    el.addEventListener('shown.bs.tab', () => window.scrollTo({top: 0, behavior: 'smooth'}));
  });

  // IBAN formatting & simple validation
//   const ibanInput = document.querySelector('input[name="bank[iban]"]');
//   if (ibanInput) {
//     ibanInput.addEventListener('input', (e) => {
//       // keep letters+digits, uppercase, group by 4 for readability
//       let v = e.target.value.replace(/\s+/g,'').toUpperCase();
//       v = v.replace(/[^A-Z0-9]/g,'');
//       e.target.value = v.replace(/(.{4})/g,'$1 ').trim();
//       // quick check: starts with 2 letters + digits length 15-32 (after removing spaces)
//       const raw = v;
//       if (raw.length >= 16 && /^[A-Z]{2}[0-9A-Z]{14,32}$/.test(raw)) {
//         e.target.classList.remove('is-invalid');
//       } else if (raw.length > 0) {
//         e.target.classList.add('is-invalid');
//       } else {
//         e.target.classList.remove('is-invalid');
//       }
//     });
//   }

  // NIP numeric only (10 digits)
  const nipInput = document.querySelector('input[name="company[nip]"]');
  if (nipInput) {
    nipInput.addEventListener('input', (e) => {
      let v = e.target.value.replace(/\D/g,'').slice(0,10);
      e.target.value = v;
      if (v.length > 0 && v.length !== 10) {
        e.target.classList.add('is-invalid');
      } else {
        e.target.classList.remove('is-invalid');
      }
    });
  }

  // GUS fetch for company
  const CSRF_TOKEN = document.querySelector('meta[name="csrfToken"]')?.getAttribute('content') || '';
  const btnGus = document.getElementById('company-gus-fetch');
  const spin   = document.getElementById('company-gus-spin');
  btnGus?.addEventListener('click', async () => {
    const nip = (nipInput?.value || '').replace(/\D+/g,'');
    if (!nip || nip.length !== 10) {
      // simple toast replacement: use alert for now if no toaster in this view
      try { window.toastr?.info('Podaj prawidłowy NIP (10 cyfr).'); } catch(e) {}
      return;
    }
    try {
      btnGus.disabled = true; spin.classList.remove('d-none');
      const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'gusLookup']) ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({ nip })
      });
      const data = await res.json();
      if (data.success) {
        const c = data.contractor || {};
        const setVal = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val ?? ''; };
        setVal('input[name="company[name]"]',        c.name);
        setVal('input[name="company[city]"]',        c.city);
        setVal('input[name="company[postal_code]"]', c.zip);
        setVal('input[name="company[country]"]',     c.country || 'PL');
        setVal('input[name="company[street]"]',      c.street);
        try { window.toastr?.success('Pobrano dane z GUS.'); } catch(e) {}
      } else {
        try { window.toastr?.warning(data.message || 'Brak danych w GUS.'); } catch(e) {}
      }
    } catch (err) {
      try { window.toastr?.error('Błąd pobierania z GUS.'); } catch(e) {}
    } finally {
      btnGus.disabled = false; spin.classList.add('d-none');
    }
  });

  // postal code 99-999
  const pcInput = document.querySelector('input[name="company[postal_code]"]');
  if (pcInput) {
    pcInput.addEventListener('input', (e) => {
      let v = e.target.value.replace(/\D/g,'').slice(0,5);
      if (v.length >= 3) v = v.slice(0,2) + '-' + v.slice(2);
      e.target.value = v;
      const ok = /^\d{2}-\d{3}$/.test(e.target.value) || e.target.value === '';
      e.target.classList.toggle('is-invalid', !ok);
    });
  }

  // bootstrap client-side validation & submit lock
  const form = document.querySelector('.needs-validation');
  const submitBtn = document.getElementById('onboarding-submit');
  form.addEventListener('submit', function (event) {
    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      return;
    }
    // lock button
    submitBtn.disabled = true;
    submitBtn.querySelector('.spinner-border').classList.remove('d-none');
    submitBtn.querySelector('.submit-text').textContent = 'Zapisywanie...';
  }, false);
})();
// --- BANKS DYNAMIC (self-init)
(function initBanks() {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBanks, { once: true });
    return;
  }

  const list = document.getElementById('banks-list');
  const addBtn = document.getElementById('bank-add');
  const defaultHidden = document.getElementById('banks_default');

  if (!list || !addBtn || !defaultHidden) {
    console.warn('Bank accounts UI not found – check ids: #banks-list, #bank-add, #banks_default');
    return;
  }

  // wskazanie domyślnego
  list.addEventListener('change', (e) => {
    if (e.target.classList.contains('bank-default')) {
      defaultHidden.value = e.target.value;
    }
  });

  // usuwanie wiersza
  list.addEventListener('click', (e) => {
    const removeBtn = e.target.closest('.bank-remove');
    if (!removeBtn) return;

    const items = list.querySelectorAll('.bank-item');
    if (items.length <= 1) return; // nie usuwaj jedynego

    const item = removeBtn.closest('.bank-item');
    const wasDefault = item.querySelector('.bank-default').checked;
    item.remove();

    renumberBanks();

    if (wasDefault) {
      const firstRadio = list.querySelector('.bank-item .bank-default');
      if (firstRadio) {
        firstRadio.checked = true;
        defaultHidden.value = firstRadio.value;
      }
    }
    toggleRemoveButtons();
  });

  // dodawanie wiersza
  addBtn.addEventListener('click', (e) => {
    e.preventDefault();

    const src = list.querySelector('.bank-item');
    if (!src) return;

    const idx = list.querySelectorAll('.bank-item').length;
    const tpl = src.cloneNode(true);

    tpl.setAttribute('data-index', String(idx));

    // wyczyść wartości i podmień indeksy w name
    tpl.querySelectorAll('[name]').forEach((el) => {
      el.name = el.name.replace(/\bbanks\[\d+\]/g, 'banks[' + idx + ']');

      if (el.classList.contains('bank-default')) {
        el.checked = false;
        el.value = String(idx);
      } else if (el.tagName === 'SELECT') {
        el.value = 'PLN';
      } else {
        el.value = '';
      }
      el.classList.remove('is-invalid');
    });

    // odblokuj przycisk Usuń w nowych wierszach
    const removeBtn = tpl.querySelector('.bank-remove');
    if (removeBtn) {
      removeBtn.disabled = false;
      removeBtn.title = 'Usuń rachunek';
    }

    list.appendChild(tpl);
    toggleRemoveButtons();
  });

  // autoformat IBAN (delegacja)
  list.addEventListener('input', (e) => {
    if (!e.target.classList.contains('iban-input')) return;
    let v = e.target.value.replace(/\s+/g,'').toUpperCase().replace(/[^A-Z0-9]/g,'');
    e.target.value = v.replace(/(.{4})/g,'$1 ').trim();
    const ok = v.length === 0 || (/^[A-Z]{2}[0-9A-Z]{14,32}$/.test(v));
    e.target.classList.toggle('is-invalid', !ok);
  });

  function toggleRemoveButtons() {
    const items = list.querySelectorAll('.bank-item');
    items.forEach((wrap) => {
      const btn = wrap.querySelector('.bank-remove');
      if (!btn) return;
      const lock = (items.length <= 1);
      btn.disabled = lock;
      btn.title = lock ? 'Nie można usunąć jedynego wiersza' : 'Usuń rachunek';
    });
  }

  function renumberBanks() {
    list.querySelectorAll('.bank-item').forEach((item, i) => {
      item.dataset.index = String(i);
      item.querySelectorAll('[name]').forEach((el) => {
        el.name = el.name.replace(/\bbanks\[\d+\]/g, 'banks[' + i + ']');
      });
      const radio = item.querySelector('.bank-default');
      if (radio) radio.value = String(i);
    });
  }

  toggleRemoveButtons();
})();
</script>
<script>
(function enhanceBanks(){
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', enhanceBanks, {once:true}); return; }

  const list = document.getElementById('banks-list');
  const addBtn = document.getElementById('bank-add');
  const defaultHidden = document.getElementById('banks_default');
  const submitBtn = document.getElementById('onboarding-submit');
  const form = document.querySelector('.needs-validation');

  if (!list || !addBtn || !defaultHidden) return;

  // --- 1) Prawdziwa walidacja IBAN (Mod-97) + czyszczenie wklejki
  function normalizeIban(v){ return (v||'').replace(/\s+/g,'').toUpperCase(); }
  function formatIban(v){ return normalizeIban(v).replace(/(.{4})/g,'$1 ').trim(); }
  function ibanValid(v){
    v = normalizeIban(v);
    if (!/^[A-Z]{2}[0-9A-Z]{14,32}$/.test(v)) return false;
    // algorytm: przesuń 4 znaki na koniec, zamień litery na liczby (A=10...Z=35), mod 97 == 1
    const rearr = v.slice(4) + v.slice(0,4);
    const expanded = rearr.replace(/[A-Z]/g, ch => (ch.charCodeAt(0)-55).toString());
    // mod 97 w kawałkach (bez bigInt)
    let remainder = 0;
    for (let i=0;i<expanded.length;i+=7){
      const part = remainder.toString() + expanded.substr(i, 7);
      remainder = parseInt(part,10) % 97;
    }
    return remainder === 1;
  }

  // delegacja inputów IBAN + paste
  list.addEventListener('input', (e)=>{
    if (!e.target.classList.contains('iban-input')) return;
    const raw = normalizeIban(e.target.value);
    e.target.value = formatIban(raw);
    const siblings = [...list.querySelectorAll('.iban-input')].filter(x => x !== e.target);
    const duplicate = siblings.some(x => normalizeIban(x.value) === raw && raw !== '');
    const ok = (raw==='' || (ibanValid(raw) && !duplicate));
    e.target.classList.toggle('is-invalid', !ok);
    e.target.dataset.duplicate = duplicate ? '1' : '0';
    if (duplicate) e.target.setCustomValidity('Duplikat IBAN w formularzu');
    else e.target.setCustomValidity('');
  });

  list.addEventListener('paste', (e)=>{
    if (!e.target.classList.contains('iban-input')) return;
    const clipboard = (e.clipboardData || window.clipboardData).getData('text') || '';
    if (clipboard) {
      e.preventDefault();
      e.target.value = formatIban(clipboard);
      e.target.dispatchEvent(new Event('input', {bubbles:true}));
    }
  });

  // --- 2) Dodawanie z podpowiedzią etykiety + autofocus + scroll
  addBtn.addEventListener('click', (e)=>{
    e.preventDefault();
    const src = list.querySelector('.bank-item');
    if (!src) return;

    const idx = list.querySelectorAll('.bank-item').length;
    const tpl = src.cloneNode(true);
    tpl.setAttribute('data-index', String(idx));

    // wyczyść i przemapuj nazwy
    tpl.querySelectorAll('[name]').forEach((el)=>{
      el.name = el.name.replace(/\bbanks\[\d+\]/g, 'banks['+idx+']');
      if (el.classList.contains('bank-default')) {
        el.checked = false; el.value = String(idx);
      } else if (el.tagName === 'SELECT') {
        el.value = 'PLN';
      } else {
        el.value = '';
      }
      el.classList.remove('is-invalid');
      el.setCustomValidity('');
    });

    // aktywuj Usuń
    const removeBtn = tpl.querySelector('.bank-remove');
    if (removeBtn){ removeBtn.disabled = false; removeBtn.title = 'Usuń rachunek'; }

    // podpowiedź etykiety
    const labelInput = tpl.querySelector('input[name="banks['+idx+'][label]"]');
    const currencySel = tpl.querySelector('select[name="banks['+idx+'][currency]"]');
    if (labelInput && currencySel) {
      const countSameCurrency = [...list.querySelectorAll('select[name^="banks["]')]
        .filter(s=>s.value===currencySel.value).length + 1;
      labelInput.placeholder = `Rachunek ${currencySel.value} #${countSameCurrency}`;
    }

    list.appendChild(tpl);

    // autofocus w nowym IBAN
    const newIban = tpl.querySelector('.iban-input'); if (newIban) newIban.focus();

    // płynny scroll do nowego
    tpl.scrollIntoView({behavior:'smooth', block:'center'});

    toggleRemoveButtons();
  });

  // --- 3) Zmiana „Domyślny” → aktualizacja hidden + badge
  list.addEventListener('change', (e)=>{
    if (e.target.classList.contains('bank-default')) {
      defaultHidden.value = e.target.value;
      // przestaw badge graficzny
      list.querySelectorAll('.bank-item').forEach(it=>{
        const badge = it.querySelector('.badge');
        const radio = it.querySelector('.bank-default');
        if (badge && radio) {
          if (radio.checked) { badge.className='badge bg-primary-soft text-primary'; badge.textContent='Domyślny'; }
          else { badge.className='badge bg-light text-muted'; badge.textContent='—'; }
        }
      });
    }
  });

  // --- 4) Usuwanie + renumeracja jak u Ciebie
  list.addEventListener('click', (e)=>{
    const removeBtn = e.target.closest('.bank-remove'); if (!removeBtn) return;
    const items = list.querySelectorAll('.bank-item'); if (items.length<=1) return;

    const item = removeBtn.closest('.bank-item');
    const wasDefault = item.querySelector('.bank-default')?.checked;
    item.remove();
    renumberBanks();

    if (wasDefault) {
      const firstRadio = list.querySelector('.bank-item .bank-default');
      if (firstRadio){ firstRadio.checked = true; defaultHidden.value = firstRadio.value; }
    }
    toggleRemoveButtons();
  });

  function toggleRemoveButtons(){
    const items = list.querySelectorAll('.bank-item');
    items.forEach(w=>{
      const btn = w.querySelector('.bank-remove');
      if (!btn) return;
      const lock = (items.length<=1);
      btn.disabled = lock;
      btn.title = lock ? 'Nie można usunąć jedynego wiersza' : 'Usuń rachunek';
    });
  }

  function renumberBanks(){
    list.querySelectorAll('.bank-item').forEach((item, i)=>{
      item.dataset.index = String(i);
      item.querySelectorAll('[name]').forEach((el)=>{
        el.name = el.name.replace(/\bbanks\[\d+\]/g, 'banks['+i+']');
      });
      const radio = item.querySelector('.bank-default'); if (radio) radio.value = String(i);
    });
  }

  toggleRemoveButtons();

  // --- 5) Skróty klawiaturowe (Power-user)
  document.addEventListener('keydown', (e)=>{
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase()==='enter') { e.preventDefault(); submitBtn?.click(); }
    if (mod && e.shiftKey && e.key.toLowerCase()==='b') { e.preventDefault(); addBtn?.click(); }
  });

  // --- 6) Przed wysłaniem: ostateczne odszumianie IBAN (bez spacji)
  form?.addEventListener('submit', ()=>{
    list.querySelectorAll('.iban-input').forEach(inp=>{
      inp.value = normalizeIban(inp.value); // backend ma czysto
    });
  });

  // --- 7) Drobny „quality-of-life”: przy zmianie waluty sugeruj etykietę jeśli puste
  list.addEventListener('change', (e)=>{
    const sel = e.target.closest('select[name^="banks["][name$="[currency]"]');
    if (!sel) return;
    const wrap = sel.closest('.bank-item');
    const labelInput = wrap?.querySelector('input[name^="banks["][name$="[label]"]');
    if (labelInput && !labelInput.value) {
      const countSame = [...list.querySelectorAll('select[name^="banks["]')].filter(s=>s.value===sel.value).length;
      labelInput.placeholder = `Rachunek ${sel.value} #${Math.max(1,countSame)}`;
    }
  });

})();
</script>

<style>
  /* delikatny glow dla błędnych pól */
  .form-control.is-invalid {
    box-shadow: 0 0 0 .2rem rgba(220,53,69,.15);
  }
  /* hover na kartach banków */
  .bank-item.card {
    transition: box-shadow .2s ease, transform .12s ease;
  }
  .bank-item.card:hover {
    transform: translateY(-1px);
    box-shadow: 0 .3rem 1rem rgba(10, 20, 30, .06);
  }
  /* badge domyślnego z lekkim podkreśleniem */
  .badge.bg-primary-soft {
    background: rgba(13,110,253,.08);
    border: 1px solid rgba(13,110,253,.15);
  }
</style>

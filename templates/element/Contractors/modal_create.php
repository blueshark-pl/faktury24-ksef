<?php
/**
 * Element: modal dodawania kontrahenta (pełny, identyczny jak w contractors/index).
 *
 * Zmienne opcjonalne:
 *   $contractorModalMode  — 'standalone' (domyślnie) lub 'invoice'
 *   $companyId            — company_id zalogowanego użytkownika
 *
 * Tryb 'standalone':  po zapisie odświeżenie strony (jak w contractors/index).
 * Tryb 'invoice':     po zapisie wywołuje window.onContractorCreated(contractor)
 *                     zamiast przeładowania — używać w szablonach faktur.
 */

$contractorModalMode = $contractorModalMode ?? 'standalone';
if (!isset($companyId)) {
    $identity  = $this->getRequest()->getAttribute('identity');
    $companyId = $identity?->get('company_id');
}
?>

<!-- Modal: Dodaj kontrahenta -->
<div class="modal fade" id="contractor-create" tabindex="-1" aria-hidden="true" data-mode="add"
     data-bs-scroll="true" data-bs-backdrop="false">
  <div class="modal-dialog modal-dialog-scrollable modal-xl">
    <div class="modal-content">

      <div class="modal-header border-bottom-0 pb-0">
        <div class="d-flex align-items-center gap-2">
          <div class="avatar avatar-sm bg-primary-transparent rounded" id="contractor-modal-icon-wrap">
            <i class="ri-contacts-book-line fs-16 text-primary" id="contractor-modal-icon"></i>
          </div>
          <div>
            <h6 class="modal-title mb-0" id="contractor-modal-title">Dodaj kontrahenta</h6>
            <small class="text-muted" id="contractor-modal-subtitle">Wypełnij dane i zapisz kontrahenta</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>

      <?= $this->Form->create(null, [
            'url' => ['controller' => 'Contractors', 'action' => 'add'],
            'class' => 'needs-validation', 'novalidate' => true, 'id' => 'contractor-form'
      ]) ?>
      <?= $this->Form->hidden('id', ['id' => 'contractor-id']) ?>
      <?= $this->Form->hidden('_method', ['value' => 'POST', 'id' => 'contractor-method']) ?>
      <?= $this->Form->hidden('contractor_settings_enabled', ['value' => '1', 'id' => 'contractor-settings-enabled']) ?>

      <div class="modal-body">
        <?= $this->Form->hidden('company_id', ['value' => $companyId]) ?>

        <div class="vstack gap-3">

          <!-- Przełącznik typu -->
          <div class="border rounded p-3" id="type-switch-section">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <strong class="small"><i class="ri-building-line me-1 text-primary"></i>Typ kontrahenta</strong>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="use-pesel">
                <label class="form-check-label" for="use-pesel">Osoba fizyczna</label>
              </div>
            </div>
            <small class="text-muted">W trybie osoby fizycznej podaj imię i nazwisko. PESEL jest opcjonalny.</small>
          </div>

          <!-- Dane podstawowe -->
          <div class="border rounded p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small"><i class="ri-id-card-line me-1 text-primary"></i>Dane podstawowe</strong>
              <div class="form-check form-switch mb-0 ms-auto">
                <?= $this->Form->control('is_active', [
                  'type' => 'checkbox', 'label' => 'Aktywny', 'checked' => true,
                  'class' => 'form-check-input',
                  'templates' => ['inputContainer' => '<div class="form-check">{{content}}</div>']
                ]) ?>
              </div>
            </div>
            <small class="text-muted d-block mb-2">Aktywny = wyłączenie ukrywa go na liście i blokuje użycie w nowych dokumentach.</small>
            <div class="row g-3">
              <div class="col-md-6" id="nip-group">
                <div class="form-group">
                  <label for="nip">NIP</label>
                  <div class="input-group">
                    <?= $this->Form->control('nip', [
                      'label' => false, 'id' => 'nip',
                      'class' => 'form-control', 'maxlength' => 20,
                      'placeholder' => '6571234567',
                      'templates' => ['inputContainer' => '{{content}}']
                    ]) ?>
                    <button class="btn btn-outline-secondary" type="button" id="gus-fetch">
                      <span class="spinner-border spinner-border-sm me-1 d-none" id="gus-spin"></span>
                      <i class="ri-database-2-line me-1"></i> Pobierz z GUS
                    </button>
                  </div>
                  <div class="help-slot">
                    <small class="text-muted">Krok 1: wpisz NIP i pobierz dane z GUS.</small>
                    <div id="contractor-vat-status" class="small mt-1 d-none"></div>
                  </div>
                </div>
              </div>

              <!-- Pola: firma -->
              <div id="company-fields" class="row g-3">
                <div class="col-md-8">
                  <div class="form-group">
                    <?= $this->Form->control('name', [
                      'label' => 'Nazwa*', 'class' => 'form-control',
                      'placeholder' => 'np. ACME Sp. z o.o.',
                      'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                    ]) ?>
                    <div class="help-slot"></div>
                  </div>
                </div>
              </div>

              <!-- Pola: osoba fizyczna -->
              <div id="person-fields" class="row g-3 d-none">
                <div class="col-md-6">
                  <div class="form-group">
                    <?= $this->Form->control('first_name', [
                      'label' => 'Imię*', 'class' => 'form-control',
                      'placeholder' => 'Jan',
                      'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                    ]) ?>
                    <div class="help-slot"></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <?= $this->Form->control('last_name', [
                      'label' => 'Nazwisko*', 'class' => 'form-control',
                      'placeholder' => 'Kowalski',
                      'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                    ]) ?>
                    <div class="help-slot"></div>
                  </div>
                </div>
                <div class="col-12 d-none" id="nip-toggle-row">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="show-nip">
                    <label class="form-check-label" for="show-nip">Podaj NIP (opcjonalnie)</label>
                  </div>
                </div>
                <div class="col-md-6 d-none" id="person-nip-group">
                  <div class="form-group">
                    <label for="person-nip" class="form-label">NIP</label>
                    <input type="text" id="person-nip" class="form-control" maxlength="20" placeholder="6571234567">
                    <div class="help-slot"></div>
                  </div>
                </div>
              </div>

              <div class="col-12 d-none" id="pesel-toggle-row">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="show-pesel">
                  <label class="form-check-label" for="show-pesel">Podaj PESEL (opcjonalnie)</label>
                </div>
              </div>
              <div class="col-md-6 d-none" id="pesel-group">
                <div class="form-group">
                  <?= $this->Form->control('pesel', [
                    'label' => 'PESEL', 'id' => 'pesel',
                    'class' => 'form-control', 'maxlength' => 11,
                    'placeholder' => '12345678901',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot">
                    <small class="text-muted">PESEL wykorzystywany wyłącznie do celów podatkowych i księgowych.</small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Dane kontaktowe -->
          <div class="border rounded p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small"><i class="ri-phone-line me-1 text-primary"></i>Dane kontaktowe</strong>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="form-group">
                  <?= $this->Form->control('email', [
                    'label' => 'Email', 'class' => 'form-control', 'type' => 'email', 'id' => 'contractor-email',
                    'placeholder' => 'biuro@firma.pl',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot"></div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <?= $this->Form->control('phone', [
                    'label' => 'Telefon', 'class' => 'form-control', 'id' => 'phone',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Dane adresowe -->
          <div class="border rounded p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small"><i class="ri-map-pin-line me-1 text-primary"></i>Dane adresowe</strong>
            </div>
            <div class="row g-3">
              <div class="col-md-5">
                <div class="form-group">
                  <?= $this->Form->control('city', [
                    'label' => 'Miejscowość', 'class' => 'form-control',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot"></div>
                </div>
              </div>
              <div class="col-md-5">
                <div class="form-group">
                  <?= $this->Form->control('street', [
                    'label' => 'Ulica i nr', 'class' => 'form-control',
                    'placeholder' => 'ul. i nr',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot"></div>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <?= $this->Form->control('postal_code', [
                    'label' => 'Kod', 'class' => 'form-control',
                    'placeholder' => '00-000',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot"></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <?= $this->Form->hidden('country', ['value' => 'PL', 'id' => 'country-hidden']) ?>
                  <label for="country-ui" class="form-label mb-0">Kraj</label>
                  <input type="text" id="country-ui" class="form-control" placeholder="Wybierz kraj">
                </div>
              </div>
            </div>
          </div>

          <!-- Adres korespondencyjny -->
          <div class="border rounded p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small"><i class="ri-mail-line me-1 text-primary"></i>Adres korespondencyjny (opcjonalnie)</strong>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="use-correspondence-address">
                <label class="form-check-label" for="use-correspondence-address">Dodać adres korespondencyjny?</label>
              </div>
            </div>
            <div class="row g-3 d-none" id="correspondence-address-fields">
              <div class="col-md-4">
                <label for="correspondence-city" class="form-label">Miejscowość</label>
                <input type="text" id="correspondence-city" name="correspondence_city" class="form-control">
              </div>
              <div class="col-md-4">
                <label for="correspondence-street" class="form-label">Ulica i nr</label>
                <input type="text" id="correspondence-street" name="correspondence_street" class="form-control">
              </div>
              <div class="col-md-2">
                <label for="correspondence-postal-code" class="form-label">Kod pocztowy</label>
                <input type="text" id="correspondence-postal-code" name="correspondence_postal_code" class="form-control" placeholder="00-000">
              </div>
              <div class="col-md-2">
                <label for="correspondence-country" class="form-label">Kraj</label>
                <input type="text" id="correspondence-country" name="correspondence_country" class="form-control" value="PL" maxlength="2">
              </div>
            </div>
          </div>

          <!-- Odbiorca (tylko tryb firma + dodawanie) -->
          <div class="border rounded p-3" id="recipient-section">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small"><i class="ri-user-received-line me-1 text-primary"></i>Odbiorca</strong>
            </div>
            <div class="row g-3">
              <div class="col-12" id="recipient-toggle-row">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="add-recipient" name="add_recipient" value="1">
                  <label class="form-check-label" for="add-recipient">Dodaj odbiorcę (tylko dla firm)</label>
                </div>
              </div>
              <div class="col-12 p-0 d-none" id="recipient-fields">
                <div class="row g-3">
                  <div class="col-md-8">
                    <label for="recipient-name" class="form-label">Nazwa odbiorcy</label>
                    <input type="text" id="recipient-name" name="recipient_name" class="form-control" placeholder="np. Dział Zakupów">
                  </div>
                  <div class="col-md-4">
                    <label for="recipient-email" class="form-label">Email odbiorcy</label>
                    <input type="email" id="recipient-email" name="recipient_email" class="form-control" placeholder="odbiorca@firma.pl">
                  </div>
                  <div class="col-md-4">
                    <label for="recipient-phone" class="form-label">Telefon odbiorcy</label>
                    <input type="text" id="recipient-phone" name="recipient_phone" class="form-control">
                  </div>
                  <div class="col-md-8">
                    <label for="recipient-nip" class="form-label">NIP odbiorcy</label>
                    <div class="input-group">
                      <input type="text" id="recipient-nip" name="recipient_nip" class="form-control" maxlength="20" placeholder="6571234567">
                      <button class="btn btn-outline-secondary" type="button" id="recipient-gus-fetch">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="recipient-gus-spin"></span>
                        <i class="ri-database-2-line me-1"></i> Pobierz z GUS
                      </button>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <label for="recipient-city" class="form-label">Miejscowość</label>
                    <input type="text" id="recipient-city" name="recipient_city" class="form-control">
                  </div>
                  <div class="col-md-4">
                    <label for="recipient-street" class="form-label">Ulica i nr</label>
                    <input type="text" id="recipient-street" name="recipient_street" class="form-control">
                  </div>
                  <div class="col-md-4">
                    <label for="recipient-postal" class="form-label">Kod pocztowy</label>
                    <input type="text" id="recipient-postal" name="recipient_postal_code" class="form-control" placeholder="00-000">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Identyfikatory międzynarodowe -->
          <div class="border rounded p-3" id="intl-ids-section">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small"><i class="ri-global-line me-1 text-primary"></i>Identyfikatory międzynarodowe (opcjonalnie)</strong>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="use-intl-ids">
                <label class="form-check-label" for="use-intl-ids">Wypełnij</label>
              </div>
            </div>
            <small class="text-muted d-block mb-2">Potrzebne do KSeF gdy nabywca jest z UE lub ma inny identyfikator podatkowy.</small>
            <div class="row g-3 d-none" id="intl-ids-fields">
              <div class="col-md-3">
                <label for="ctr-vat-prefix" class="form-label">Prefiks VAT UE</label>
                <?= $this->Form->control('vat_prefix', [
                  'label' => false, 'id' => 'ctr-vat-prefix',
                  'class' => 'form-control', 'maxlength' => 8,
                  'placeholder' => 'np. DE',
                  'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                ]) ?>
              </div>
              <div class="col-md-5">
                <label for="ctr-vat-eu" class="form-label">Numer VAT UE</label>
                <?= $this->Form->control('vat_eu', [
                  'label' => false, 'id' => 'ctr-vat-eu',
                  'class' => 'form-control', 'maxlength' => 32,
                  'placeholder' => 'np. 123456789',
                  'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                ]) ?>
              </div>
              <div class="col-md-4">
                <label for="ctr-eori" class="form-label">EORI</label>
                <?= $this->Form->control('eori', [
                  'label' => false, 'id' => 'ctr-eori',
                  'class' => 'form-control', 'maxlength' => 32,
                  'placeholder' => 'np. PL1234567890',
                  'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                ]) ?>
              </div>
              <div class="col-md-8">
                <label for="ctr-tax-id-other" class="form-label">Inny identyfikator podatkowy</label>
                <?= $this->Form->control('tax_id_other', [
                  'label' => false, 'id' => 'ctr-tax-id-other',
                  'class' => 'form-control', 'maxlength' => 64,
                  'placeholder' => 'Numer identyfikacyjny (KodKraju+NrID w FA3)',
                  'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                ]) ?>
              </div>
              <div class="col-md-4">
                <label for="ctr-tax-id-country" class="form-label">Kod kraju (NrID)</label>
                <?= $this->Form->control('tax_id_other_country', [
                  'label' => false, 'id' => 'ctr-tax-id-country',
                  'class' => 'form-control', 'maxlength' => 8,
                  'placeholder' => 'np. GB',
                  'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                ]) ?>
              </div>
            </div>
          </div>

          <!-- Notatki -->
          <div class="border rounded p-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <strong class="small"><i class="ri-sticky-note-line me-1 text-primary"></i>Notatki</strong>
            </div>
            <div class="form-group">
              <?= $this->Form->control('notes', [
                'label' => false, 'type' => 'textarea', 'rows' => 2, 'class' => 'form-control',
                'placeholder' => 'Opcjonalne notatki wewnętrzne...',
                'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
              ]) ?>
            </div>
          </div>

        </div>
      </div><!-- /modal-body -->

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <button type="submit" class="btn btn-primary" id="contractor-submit-btn">
          <span class="spinner-border spinner-border-sm me-1 d-none" id="contractor-form-spinner" role="status" aria-hidden="true"></span>
          <i class="ri-save-line me-1" id="contractor-submit-icon"></i>
          <span id="contractor-submit-label">Zapisz</span>
        </button>
      </div>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>

<!-- mini-toast dla modalu kontrahenta -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1090; pointer-events:none;">
  <div id="ctr-modal-toast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true" style="pointer-events:auto;">
    <div class="d-flex">
      <div class="toast-body" id="ctr-modal-toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<style>
  #contractor-create .modal-dialog { max-height: calc(100vh - 3.5rem); margin-top: 1.75rem; margin-bottom: 1.75rem; }
  #contractor-create .modal-content { max-height: calc(100vh - 3.5rem); overflow: hidden; }
  #contractor-create form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden; }
  #contractor-create .form-group { display: block; }
  #contractor-create .help-slot { min-height: 1rem; line-height: 1rem; }
  #contractor-create .form-text { margin-top: .25rem; }
  #contractor-create .input-group > .form-control { min-width: 0; }
  #contractor-create .modal-body .row > [class*="col-"] { align-self: start; }
  #contractor-create .modal-header { align-items: flex-start; }
  #contractor-create .avatar { width: 2.5rem; height: 2.5rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  #contractor-create .modal-header .modal-title { font-size: .95rem; font-weight: 600; }
  #contractor-create .modal-header small { font-size: .78rem; }
  #contractor-create .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: scroll; }
  #contractor-create .modal-body::-webkit-scrollbar { width: 8px; }
  #contractor-create .modal-body::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
  #contractor-create .modal-body::-webkit-scrollbar-thumb { background: #adb5bd; border-radius: 4px; }
  #contractor-create .modal-body::-webkit-scrollbar-thumb:hover { background: #6c757d; }
</style>

<script>
(function () {
  'use strict';

  const MODAL_MODE = <?= json_encode($contractorModalMode) ?>;
  const GUS_URL    = <?= json_encode($this->Url->build(['controller' => 'Contractors', 'action' => 'gusLookup'])) ?>;
  const VAT_URL    = <?= json_encode($this->Url->build(['controller' => 'Contractors', 'action' => 'vatStatusLookup'])) ?>;
  const ADD_URL    = <?= json_encode($this->Url->build(['controller' => 'Contractors', 'action' => 'add'])) ?>;
  const EDIT_URL   = <?= json_encode($this->Url->build(['controller' => 'Contractors', 'action' => 'edit'])) ?>;
  const VIEW_URL   = <?= json_encode($this->Url->build(['controller' => 'Contractors', 'action' => 'viewJson'])) ?>;

  // ── toast helper ────────────────────────────────────────────────────────────
  const toastEl   = document.getElementById('ctr-modal-toast');
  const toastBody = document.getElementById('ctr-modal-toast-body');
  const bsToast   = toastEl ? new bootstrap.Toast(toastEl, { delay: 2500 }) : null;
  function showToast(msg) { if (toastBody) toastBody.textContent = msg; bsToast?.show(); }

  // ── refs ─────────────────────────────────────────────────────────────────────
  const modalEl   = document.getElementById('contractor-create');
  if (!modalEl) return;
  const bsModal   = new bootstrap.Modal(modalEl);
  const form      = document.getElementById('contractor-form');
  const titleEl   = document.getElementById('contractor-modal-title');
  const subtitleEl= document.getElementById('contractor-modal-subtitle');
  const iconEl    = document.getElementById('contractor-modal-icon');
  const idField   = document.getElementById('contractor-id');
  const methodFld = document.getElementById('contractor-method');
  const submitBtn    = document.getElementById('contractor-submit-btn');
  const submitSpinner= document.getElementById('contractor-form-spinner');
  const submitIcon   = document.getElementById('contractor-submit-icon');
  const submitLabel  = document.getElementById('contractor-submit-label');

  const usePesel   = document.getElementById('use-pesel');
  const nipGroup   = document.getElementById('nip-group');
  const peselGroup = document.getElementById('pesel-group');
  const peselToggleRow = document.getElementById('pesel-toggle-row');
  const showPesel  = document.getElementById('show-pesel');
  const nipToggleRow = document.getElementById('nip-toggle-row');
  const showNip    = document.getElementById('show-nip');
  const personNipGroup  = document.getElementById('person-nip-group');
  const personNipInput  = document.getElementById('person-nip');
  const contractorVatStatus = document.getElementById('contractor-vat-status');
  const useCorrespondenceAddress  = document.getElementById('use-correspondence-address');
  const correspondenceAddressFields = document.getElementById('correspondence-address-fields');
  const companyFields = document.getElementById('company-fields');
  const personFields  = document.getElementById('person-fields');
  const nipInput   = document.getElementById('nip');
  const peselInput = document.getElementById('pesel');
  const gusBtn     = document.getElementById('gus-fetch');
  const gusSpin    = document.getElementById('gus-spin');
  const countryHidden = document.getElementById('country-hidden');
  const countryUI     = document.getElementById('country-ui');
  const cityInput     = form?.querySelector('input[name="city"]');
  const streetInput   = form?.querySelector('input[name="street"]');
  const postalInput   = form?.querySelector('input[name="postal_code"]');
  const phoneInput    = document.getElementById('phone');
  const useIntlIds    = document.getElementById('use-intl-ids');
  const intlIdsFields = document.getElementById('intl-ids-fields');
  const addRecipientToggle = document.getElementById('add-recipient');
  const recipientFields    = document.getElementById('recipient-fields');
  const recipientName      = document.getElementById('recipient-name');
  const recipientEmail     = document.getElementById('recipient-email');
  const recipientNip       = document.getElementById('recipient-nip');
  const recipientGusBtn    = document.getElementById('recipient-gus-fetch');
  const recipientGusSpin   = document.getElementById('recipient-gus-spin');
  const recipientPhone     = document.getElementById('recipient-phone');
  const recipientCity      = document.getElementById('recipient-city');
  const recipientStreet    = document.getElementById('recipient-street');
  const recipientPostal    = document.getElementById('recipient-postal');

  // ── helpers ──────────────────────────────────────────────────────────────────
  function onlyDigits(v) { return (v || '').replace(/\D+/g, ''); }

  function validateNIP(nip) {
    const s = onlyDigits(nip);
    if (s.length !== 10) return false;
    const w = [6,5,7,2,3,4,5,6,7];
    const d = s.split('').map(Number);
    return (w.reduce((a, wi, i) => a + wi * d[i], 0) % 11) === d[9];
  }

  function validatePESEL(pesel) {
    const s = onlyDigits(pesel);
    if (s.length !== 11) return false;
    const w = [1,3,7,9,1,3,7,9,1,3];
    const d = s.split('').map(Number);
    return ((10 - (w.reduce((a, wi, i) => a + wi * d[i], 0) % 10)) % 10) === d[10];
  }

  function setInvalid(el, msg) {
    if (!el) return;
    el.classList.add('is-invalid');
    let fb = el.nextElementSibling;
    if (!fb || !fb.classList.contains('invalid-feedback')) {
      fb = document.createElement('div');
      fb.className = 'invalid-feedback';
      el.insertAdjacentElement('afterend', fb);
    }
    fb.textContent = msg;
  }

  function clearInvalid(el) {
    if (!el) return;
    el.classList.remove('is-invalid');
    const fb = el.nextElementSibling;
    if (fb && fb.classList.contains('invalid-feedback')) fb.remove();
  }

  function setVal(selector, value) {
    const el = form?.querySelector(selector);
    if (el) el.value = value ?? '';
  }

  function setBtnLoading(loading) {
    if (!submitBtn) return;
    submitBtn.disabled = loading;
    submitSpinner?.classList.toggle('d-none', !loading);
    submitIcon?.classList.toggle('d-none', loading);
    if (submitLabel) submitLabel.textContent = loading ? 'Zapisywanie…' : 'Zapisz';
  }

  // ── VAT status ───────────────────────────────────────────────────────────────
  let vatTimer = null;

  function clearVatStatus() {
    if (!contractorVatStatus) return;
    contractorVatStatus.classList.add('d-none');
    contractorVatStatus.classList.remove('text-success', 'text-warning', 'text-muted');
    contractorVatStatus.textContent = '';
  }

  function showVatStatus(vat) {
    if (!contractorVatStatus) return;
    const raw = String(vat?.statusVat || '').trim();
    const status = raw.toLowerCase();
    const cnt = Array.isArray(vat?.accountNumbers) ? vat.accountNumbers.length : 0;
    contractorVatStatus.classList.remove('d-none', 'text-success', 'text-warning', 'text-muted');
    contractorVatStatus.classList.add(status === 'czynny' ? 'text-success' : (raw ? 'text-warning' : 'text-muted'));
    contractorVatStatus.textContent = 'Status VAT (Biała lista): ' + (raw || 'brak danych') + (cnt > 0 ? ', rachunki: ' + cnt : '');
  }

  async function checkVatStatus(nip) {
    if (!nip || nip.length !== 10) { clearVatStatus(); return; }
    try {
      const csrf = document.querySelector('meta[name="csrfToken"]')?.content
                || document.querySelector('[name="_csrfToken"]')?.value
                || (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
      const res = await fetch(VAT_URL, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ nip })
      });
      const data = await res.json();
      if (data?.success) showVatStatus(data?.vat || {});
    } catch {}
  }

  // ── country ──────────────────────────────────────────────────────────────────
  function applyCountryRequirements() {
    const isPL = (countryHidden?.value || '').toUpperCase() === 'PL';
    if (cityInput)   cityInput.required   = isPL;
    if (streetInput) streetInput.required = isPL;
    if (postalInput) postalInput.required = isPL;
    const mark = (id, req) => {
      const lbl = form?.querySelector(`label[for="${id}"]`);
      if (!lbl) return;
      let star = lbl.querySelector('.req-star');
      if (req) {
        if (!star) { star = document.createElement('span'); star.className = 'req-star text-danger ms-1'; star.textContent = '*'; lbl.appendChild(star); }
      } else { star?.remove(); }
    };
    mark('city', isPL); mark('street', isPL); mark('postal_code', isPL); mark('postal-code', isPL);
  }

  // ── correspondence address ───────────────────────────────────────────────────
  function applyCorrespondenceUI() {
    if (!correspondenceAddressFields || !useCorrespondenceAddress) return;
    const on = !!useCorrespondenceAddress.checked;
    correspondenceAddressFields.classList.toggle('d-none', !on);
    if (!on) correspondenceAddressFields.querySelectorAll('input,select,textarea').forEach(el => { el.value = ''; });
  }

  // ── intl ids toggle ──────────────────────────────────────────────────────────
  function applyIntlIdsUI() {
    if (!intlIdsFields || !useIntlIds) return;
    intlIdsFields.classList.toggle('d-none', !useIntlIds.checked);
  }

  // ── mode UI (firma / osoba) ──────────────────────────────────────────────────
  function applyModeUI() {
    const person = !!usePesel?.checked;
    if (!person) {
      nipGroup?.classList.remove('d-none');
      personNipGroup?.classList.add('d-none');
    } else {
      nipGroup?.classList.add('d-none');
      personNipGroup?.classList.toggle('d-none', !showNip?.checked);
    }
    peselGroup?.classList.toggle('d-none', !person);
    companyFields?.classList.toggle('d-none', person);
    personFields?.classList.toggle('d-none', !person);
    if (nipInput)   nipInput.required   = !person;
    if (peselInput) peselInput.required = false;
    const nameInput    = form?.querySelector('input[name="name"]');
    const altnameInput = form?.querySelector('input[name="altname"]');
    const firstName    = form?.querySelector('input[name="first_name"]');
    const lastName     = form?.querySelector('input[name="last_name"]');
    if (nameInput)    nameInput.required    = !person;
    if (altnameInput) altnameInput.required = false;
    if (firstName)    firstName.required    = person;
    if (lastName)     lastName.required     = person;
    if (gusBtn) gusBtn.disabled = person;
    if (person) clearVatStatus();
    peselToggleRow?.classList.toggle('d-none', !person);
    if (person) { if (showPesel) showPesel.checked = false; peselGroup?.classList.add('d-none'); }
    nipToggleRow?.classList.toggle('d-none', !person);
    if (!person) { if (showNip) showNip.checked = false; }
    document.getElementById('recipient-section')?.classList.toggle('d-none', person);
    document.getElementById('recipient-toggle-row')?.classList.toggle('d-none', person);
    if (person && addRecipientToggle) { addRecipientToggle.checked = false; recipientFields?.classList.add('d-none'); }
    clearInvalid(nipInput); clearInvalid(peselInput);
    clearInvalid(nameInput); clearInvalid(firstName); clearInvalid(lastName);
    if (person && useCorrespondenceAddress) { useCorrespondenceAddress.checked = false; applyCorrespondenceUI(); }
    // w trybie edit zawsze chowaj odbiorcę
    if (modalEl?.dataset?.mode === 'edit') {
      document.getElementById('recipient-section')?.classList.add('d-none');
      document.getElementById('recipient-fields')?.classList.add('d-none');
      if (addRecipientToggle) { addRecipientToggle.checked = false; addRecipientToggle.disabled = true; }
    }
  }

  // ── reset do trybu dodawania ─────────────────────────────────────────────────
  function resetFormToAdd() {
    form.reset();
    clearVatStatus();
    idField.value = '';
    methodFld.value = 'POST';
    form.setAttribute('action', ADD_URL);
    modalEl.dataset.mode = 'add';
    if (titleEl) titleEl.textContent = 'Dodaj kontrahenta';
    if (subtitleEl) subtitleEl.textContent = 'Wypełnij dane i zapisz kontrahenta';
    if (iconEl) iconEl.className = 'ri-contacts-book-line fs-16 text-primary';
    setBtnLoading(false);
    form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(i => i.remove());
    form.classList.remove('was-validated');
    const usePeselEl = document.getElementById('use-pesel');
    if (usePeselEl) { usePeselEl.disabled = false; usePeselEl.closest('.form-check')?.classList.remove('d-none'); usePeselEl.checked = false; }
    document.getElementById('recipient-section')?.classList.remove('d-none');
    document.getElementById('recipient-toggle-row')?.classList.remove('d-none');
    recipientFields?.classList.add('d-none');
    if (addRecipientToggle) { addRecipientToggle.disabled = false; addRecipientToggle.checked = false; }
    if (useCorrespondenceAddress) useCorrespondenceAddress.checked = false;
    applyCorrespondenceUI();
    if (useIntlIds) useIntlIds.checked = false;
    applyIntlIdsUI();
    try { applyModeUI(); } catch {}
  }

  // ── otwarcie w trybie add ─────────────────────────────────────────────────────
  document.querySelectorAll('[data-bs-target="#contractor-create"]').forEach(btn => {
    btn.addEventListener('click', resetFormToAdd);
  });

  // ── obsługa submit (AJAX) ────────────────────────────────────────────────────
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }
    form.classList.add('was-validated');
    setBtnLoading(true);
    const csrf = document.querySelector('meta[name="csrfToken"]')?.content
              || document.querySelector('[name="_csrfToken"]')?.value
              || (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
    try {
      const res = await fetch(form.getAttribute('action'), {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (data && data.success && data.contractor) {
        bsModal.hide();
        resetFormToAdd();
        if (MODAL_MODE === 'invoice' && typeof window.onContractorCreated === 'function') {
          window.onContractorCreated(data.contractor);
        } else {
          showToast(data.message || 'Kontrahent został zapisany.');
          setTimeout(() => window.location.reload(), 700);
        }
      } else {
        setBtnLoading(false);
        // pokaż błędy walidacji
        const errors = data.errors || {};
        const msgs = Object.values(errors).flatMap(v => typeof v === 'object' ? Object.values(v) : [v]);
        const clean = msgs.map(m => String(m).replace(/^\w+:\s*/u, '').trim()).filter(Boolean);
        showToast(clean.length ? clean[0] : (data.message || 'Nie udało się zapisać kontrahenta.'));
        // zaznacz pola z błędem
        Object.keys(errors).forEach(field => {
          const el = form.querySelector(`[name="${field}"]`);
          if (el) setInvalid(el, clean[0] || 'Błąd');
        });
      }
    } catch (err) {
      setBtnLoading(false);
      showToast('Błąd komunikacji. Spróbuj ponownie.');
    }
  });

  // ── otwarcie w trybie edit (tylko standalone) ─────────────────────────────────
  if (MODAL_MODE === 'standalone') {
    document.querySelectorAll('.js-edit').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        if (!id) return;
        resetFormToAdd();
        const editName = btn.dataset.name || ('#' + id);
        if (titleEl) titleEl.textContent = 'Edytuj: ' + editName;
        if (subtitleEl) subtitleEl.textContent = editName;
        if (iconEl) iconEl.className = 'ri-edit-2-line fs-16 text-warning';
        modalEl.dataset.mode = 'edit';
        idField.value = id;
        methodFld.value = 'PUT';
        form.setAttribute('action', EDIT_URL + '/' + id);
        bsModal.show();

        // zablokuj zmianę typu w edycji
        document.getElementById('type-switch-section')?.classList.add('d-none');
        ['recipient-section','recipient-toggle-row','recipient-fields'].forEach(sid => {
          document.getElementById(sid)?.classList.add('d-none');
        });
        if (addRecipientToggle) { addRecipientToggle.checked = false; addRecipientToggle.disabled = true; }

        try {
          const res = await fetch(VIEW_URL + '/' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const data = await res.json();
          if (!data.success) throw new Error('Brak danych');
          const c = data.contractor || {};
          setVal('input[name="name"]',        c.name);
          setVal('input[name="altname"]',     c.altname);
          setVal('input[name="first_name"]',  c.first_name);
          setVal('input[name="last_name"]',   c.last_name);
          setVal('input[name="nip"]',         c.nip);
          setVal('input[name="pesel"]',       c.pesel);
          setVal('input[name="email"]',       c.email);
          setVal('input[name="phone"]',       c.phone);
          setVal('input[name="country"]',     c.country || 'PL');
          setVal('input[name="postal_code"]', c.postal_code);
          setVal('input[name="city"]',        c.city);
          setVal('input[name="street"]',      c.street);
          setVal('input[name="correspondence_country"]',     c.correspondence_country || 'PL');
          setVal('input[name="correspondence_postal_code"]', c.correspondence_postal_code);
          setVal('input[name="correspondence_city"]',        c.correspondence_city);
          setVal('input[name="correspondence_street"]',      c.correspondence_street);
          const hasCorr = !!(c.correspondence_city || c.correspondence_street || c.correspondence_postal_code);
          if (useCorrespondenceAddress) { useCorrespondenceAddress.checked = hasCorr; applyCorrespondenceUI(); }
          setVal('input[name="vat_prefix"]',          c.vat_prefix);
          setVal('input[name="vat_eu"]',               c.vat_eu);
          setVal('input[name="eori"]',                 c.eori);
          setVal('input[name="tax_id_other"]',         c.tax_id_other);
          setVal('input[name="tax_id_other_country"]', c.tax_id_other_country);
          const hasIntl = !!(c.vat_prefix || c.vat_eu || c.eori || c.tax_id_other || c.tax_id_other_country);
          if (useIntlIds) { useIntlIds.checked = hasIntl; applyIntlIdsUI(); }
          const notes = form.querySelector('textarea[name="notes"]'); if (notes) notes.value = c.notes ?? '';
          const chk = form.querySelector('input[name="is_active"]'); if (chk) chk.checked = Number(c.is_active) === 1;
          const usePeselEl = document.getElementById('use-pesel');
          if (usePeselEl) {
            const isPerson = !!(c.pesel && !c.nip) || (!!c.first_name || !!c.last_name);
            usePeselEl.checked = isPerson;
            usePeselEl.disabled = true;
            usePeselEl.closest('.form-check')?.classList.add('d-none');
            try { applyModeUI(); } catch {}
          }
          if (c.pesel || c.first_name || c.last_name) {
            form.querySelectorAll('#company-fields input, #nip-group input, #gus-fetch').forEach(el => { el.disabled = true; });
          } else {
            form.querySelectorAll('#person-fields input, #person-nip-group input, #show-nip').forEach(el => { el.disabled = true; });
          }
          try {
            if (countryHidden && c.country) countryHidden.value = c.country;
            if (window.jQuery && jQuery.fn.countrySelect && countryUI && c.country) {
              jQuery(countryUI).countrySelect('selectCountry', (c.country || 'PL').toLowerCase());
            }
            if (window.itiPhone && c.country) window.itiPhone.setCountry((c.country || 'PL').toLowerCase());
            applyCountryRequirements();
          } catch {}
        } catch {
          showToast('Nie udało się wczytać danych kontrahenta.');
        }
      });
    });
  }

  // ── eventy toggles ────────────────────────────────────────────────────────────
  usePesel?.addEventListener('change', applyModeUI);
  showPesel?.addEventListener('change', () => peselGroup?.classList.toggle('d-none', !showPesel.checked));
  showNip?.addEventListener('change', () => {
    personNipGroup?.classList.toggle('d-none', !showNip.checked);
    if (gusBtn) gusBtn.disabled = !!usePesel?.checked;
  });
  addRecipientToggle?.addEventListener('change', () => recipientFields?.classList.toggle('d-none', !addRecipientToggle.checked));
  useCorrespondenceAddress?.addEventListener('change', applyCorrespondenceUI);

  // ── live NIP / PESEL listeners ────────────────────────────────────────────────
  nipInput?.addEventListener('input', () => {
    clearInvalid(nipInput);
    if (vatTimer) clearTimeout(vatTimer);
    const v = onlyDigits(nipInput.value);
    if (v.length === 10) vatTimer = setTimeout(() => checkVatStatus(v), 350);
    else clearVatStatus();
  });
  nipInput?.addEventListener('blur', () => { clearInvalid(nipInput); });
  personNipInput?.addEventListener('input', () => { clearInvalid(personNipInput); });
  peselInput?.addEventListener('input', () => {
    const v = onlyDigits(peselInput.value); peselInput.value = v;
    clearInvalid(peselInput);
    if (v.length === 11 && !validatePESEL(v)) setInvalid(peselInput, 'Nieprawidłowy PESEL — sprawdź cyfrę kontrolną.');
  });
  recipientNip?.addEventListener('input', () => { clearInvalid(recipientNip); });

  // ── GUS fetch (firma) ─────────────────────────────────────────────────────────
  gusBtn?.addEventListener('click', async () => {
    const nip = onlyDigits(nipInput?.value || '');
    if (!nip || nip.length !== 10) { showToast('Podaj prawidłowy NIP (10 cyfr).'); return; }
    const csrf = document.querySelector('meta[name="csrfToken"]')?.content
              || document.querySelector('[name="_csrfToken"]')?.value
              || (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
    try {
      gusBtn.disabled = true; gusSpin?.classList.remove('d-none');
      const res = await fetch(GUS_URL, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ nip })
      });
      const data = await res.json();
      if (data.success) {
        const c = data.contractor || {};
        setVal('input[name="name"]',        c.name);
        setVal('input[name="city"]',        c.city);
        setVal('input[name="postal_code"]', c.zip);
        setVal('input[name="country"]',     c.country || 'PL');
        setVal('input[name="street"]',      c.street);
        showVatStatus(data.vat || {});
        showToast('Pobrano dane z GUS.');
      } else {
        checkVatStatus(nip);
        showToast(data.message || 'Brak danych w GUS.');
      }
    } catch { showToast('Błąd pobierania z GUS.'); }
    finally { gusBtn.disabled = false; gusSpin?.classList.add('d-none'); }
  });

  // ── GUS fetch (odbiorca) ──────────────────────────────────────────────────────
  recipientGusBtn?.addEventListener('click', async () => {
    const nip = onlyDigits(recipientNip?.value || '');
    if (!nip || nip.length !== 10) { showToast('Podaj prawidłowy NIP odbiorcy (10 cyfr).'); return; }
    const csrf = document.querySelector('meta[name="csrfToken"]')?.content
              || document.querySelector('[name="_csrfToken"]')?.value
              || (typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
    try {
      recipientGusBtn.disabled = true; recipientGusSpin?.classList.remove('d-none');
      const res = await fetch(GUS_URL, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ nip })
      });
      const data = await res.json();
      if (data.success) {
        const c = data.contractor || {};
        if (recipientName)   recipientName.value   = c.name || recipientName.value;
        if (recipientCity)   recipientCity.value   = c.city || recipientCity.value;
        if (recipientPostal) recipientPostal.value = c.zip  || recipientPostal.value;
        if (recipientStreet) recipientStreet.value = c.street || recipientStreet.value;
        showToast('Pobrano dane odbiorcy z GUS.');
      } else { showToast(data.message || 'Brak danych w GUS dla NIP odbiorcy.'); }
    } catch { showToast('Błąd pobierania z GUS dla odbiorcy.'); }
    finally { recipientGusBtn.disabled = false; recipientGusSpin?.classList.add('d-none'); }
  });

  // ── intl-tel-input ────────────────────────────────────────────────────────────
  let iti = null;
  if (window.intlTelInput && phoneInput) {
    try {
      iti = window.intlTelInput(phoneInput, {
        initialCountry: (countryHidden?.value || 'PL').toLowerCase(),
        preferredCountries: ['pl','de','cz','sk','gb','us'],
        separateDialCode: true, nationalMode: true,
        utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/js/utils.js',
      });
      window.itiPhone = iti;
      try { phoneInput.setAttribute('placeholder', ''); } catch {}
      if (window.jQuery && jQuery.fn.countrySelect && countryUI) {
        const $ui = jQuery(countryUI);
        $ui.countrySelect({ defaultCountry: (countryHidden?.value || 'PL').toLowerCase() });
        try { const init = $ui.countrySelect('getSelectedCountryData'); if (init?.iso2) countryHidden.value = init.iso2.toUpperCase(); } catch {}
        $ui.on('change', () => {
          try {
            const d = $ui.countrySelect('getSelectedCountryData');
            if (d?.iso2) { countryHidden.value = d.iso2.toUpperCase(); iti?.setCountry(d.iso2); applyCountryRequirements(); }
          } catch {}
        });
      }
      phoneInput.addEventListener('countrychange', () => {
        const d = iti.getSelectedCountryData();
        if (d?.iso2) {
          try { jQuery(countryUI).countrySelect('selectCountry', d.iso2); } catch {}
          if (countryHidden) countryHidden.value = d.iso2.toUpperCase();
          applyCountryRequirements();
        }
      });
      phoneInput.addEventListener('blur', () => {
        clearInvalid(phoneInput);
        if (phoneInput.value && !iti.isValidNumber()) setInvalid(phoneInput, 'Nieprawidłowy numer telefonu.');
      });
    } catch {}
  }

  // ── re-enable button on modal close ──────────────────────────────────────────
  modalEl.addEventListener('hidden.bs.modal', () => {
    setBtnLoading(false);
    form?.classList.remove('was-validated');
  });

  // ── inicjalizacja ─────────────────────────────────────────────────────────────
  useIntlIds?.addEventListener('change', applyIntlIdsUI);
  useCorrespondenceAddress?.addEventListener('change', applyCorrespondenceUI);
  applyModeUI();
  applyCorrespondenceUI();
  applyIntlIdsUI();
  applyCountryRequirements();
  modalEl.addEventListener('shown.bs.modal', applyModeUI);

})();
</script>

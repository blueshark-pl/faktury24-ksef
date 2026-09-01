<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface|\Cake\Collection\CollectionInterface $invoices
 * @var array $stats
 */
use Cake\Utility\Text;
use Cake\Core\Configure;
$this->assign('title', 'Faktury');
?>

<style>
.table th {
    font-weight: 600;
}
.btn-group .btn {
    margin-right: 2px;
}
.btn-group .btn:last-child {
    margin-right: 0;
}

/* Payment Modal Styles */
.bg-success-light {
    background-color: #d1edff !important;
}
.bg-warning-light {
    background-color: #fff3cd !important;
}
.bg-info-light {
    background-color: #cfe2ff !important;
}
.bg-danger-light {
    background-color: #f8d7da !important;
}
.cursor-pointer {
    cursor: pointer !important;
}
/* Email status alert animation */
@keyframes pulse-alert {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
.email-status-alert {
    animation: pulse-alert 2s infinite;
}
.modal-lg {
    max-width: 900px;
}
.card {
    border: 1px solid #e3e6f0;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}
.card-header {
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
}
.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.025);
}
.text-success {
    color: #1cc88a !important;
}
.text-warning {
    color: #f6c23e !important;
}
/* Pagination polish */
.pagination .page-link {

  border: 1px solid #e3e6f0;
  margin: 0 3px;
  color: #495057;
}
.pagination .page-link:hover {
  background-color: #f8f9fc;
}
.pagination .page-item.active .page-link {
  color: #fff;
  font-weight: 700;
  box-shadow: 0 0.25rem 0.75rem rgba(78,115,223,.35);
  pointer-events: none; /* nieklikalna aktywna strona */
}
.pagination .page-item.active .page-link:hover { color: #fff; }
.pagination .page-item.disabled .page-link {
  color: #a1a5b7;
  background-color: #f5f5f9;
}

/* Tabela podsumowania po walutach - minimal Bootstrap style */
.currency-summary-table thead th {
  padding: 16px 12px !important;
}
.currency-summary-table tbody td {
  padding: 14px 12px !important;
}
.currency-summary-table td.amount {
  text-align: right;
}
.currency-summary-table thead th.text-end {
  text-align: right;
}
.currency-summary-table .badge-count {
  margin-left: 4px;
}
.currency-summary-table .progress-mini {
  height: 3px;
  margin-top: 4px;
}
.currency-summary-table .progress-mini-bar {
  background-color: #10b981;
}

/* Ukryj stare kafelki statystyk */
.row-cols-xxl-5.row-cols-xl-3 {
  display: none;
}
</style>
<style>
/* Column resizer styles */
#invoices-table thead th.resizable { position: relative; }
#invoices-table thead th[draggable="true"] { cursor: move; }
#invoices-table .col-resize-handle {
  position: absolute;
  top: 0; right: 0; bottom: 0;
  width: 8px;
  cursor: col-resize;
  user-select: none;
}
#invoices-table .col-resize-handle::after {
  content: "";
  position: absolute;
  top: 0; bottom: 0; left: 3px;
  width: 2px;
  background: transparent;
}
#invoices-table thead th.resizable:hover .col-resize-handle::after,
#invoices-table thead th.resizing .col-resize-handle::after {
  background: rgba(0,0,0,0.08);
}

/* Truncation: prevent overflow when content is wider than column */
#invoices-table { table-layout: fixed; }
#invoices-table th, #invoices-table td {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

<?php

// CSRF token meta for AJAX requests
echo $this->Html->meta('csrfToken', $this->request->getAttribute('csrfToken'));

// filtry z query
$q           = trim((string)$this->request->getQuery('q'));
$state       = $this->request->getQuery('state');
$from        = $this->request->getQuery('from');
$to          = $this->request->getQuery('to');
$currency    = $this->request->getQuery('currency');
$emailStatus = $this->request->getQuery('email_status');

$money = function($amount, $currency = 'PLN') {
    return number_format($amount, 2, ',', ' ') . ' ' . $currency;
};

$badge = function($state) {
    $badges = [
        'paid' => '<span class="badge bg-success">Opłacona</span>',
        'unpaid' => '<span class="badge bg-danger">Nieopłacona</span>',
        'partial' => '<span class="badge bg-warning">Częściowa</span>',
        'overdue' => '<span class="badge bg-dark">Przeterminowana</span>'
    ];
    return $badges[$state] ?? '<span class="badge bg-light text-dark">' . h($state) . '</span>';
};

$isDemo = (bool)(Configure::read('App.demo') ?? false);
?>

<!-- Start::page-header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Faktury</h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item active" aria-current="page">Faktury</li>
    </ol>
  </div>
  <div class="btn-list">
    <?= $this->Html->link(
      '<i class="ri-bar-chart-line align-middle me-1"></i> Dashboard',
      ['action' => 'dashboard'],
      ['class' => 'btn btn-primary btn-wave me-0', 'escape' => false, 'title' => 'Wykresy i statystyki']
    ) ?>
    <?= $this->Html->link(
      '<i class="ri-draft-line align-middle me-1"></i> Robocze',
      ['action' => 'drafts'],
      ['class' => 'btn btn-outline-warning btn-wave me-0', 'escape' => false, 'title' => 'Lista faktur roboczych']
    ) ?>
    <?= $this->Html->link(
      '<i class="ri-upload-cloud-line align-middle me-1"></i> Eksportuj raport do CSV',
      ['action' => 'export', '?' => $this->request->getQueryParams()],
      ['class' => 'btn btn-dark btn-wave me-0', 'escape' => false, 'title' => 'Eksport do CSV z uwzględnieniem filtrów']
    ) ?>
  </div>
</div>
<!-- End::page-header -->



<div class="card">
  <div class="card-body p-0">
    <!-- Bulk Actions Bar -->
    <div class="p-3 border-bottom d-none" id="bulk-actions-bar">
      <div class="row g-3 align-items-center">
        <div class="col-auto">
          <span class="text-muted">Wybrano: <span id="selected-count">0</span> faktur(y)</span>
        </div>
        <div class="col-auto">
          <div class="btn-group btn-group-sm">
            <!-- <button type="submit" name="action" value="print_selected" class="btn btn-primary">
              <i class="ri-printer-line me-1"></i>Drukuj wybrane
            </button> -->
            <button type="submit" name="action" value="mark_paid" class="btn btn-success">
              <i class="ri-check-line me-1"></i>Oznacz jako opłacone
            </button>
            <button type="button" id="bulk-send-email-btn" class="btn btn-primary" onclick="bulkSendEmail()">
              <i class="ri-mail-send-line me-1"></i>Wyślij e-mailem
            </button>
            <!-- <button type="submit" name="action" value="send_reminder" class="btn btn-warning">
              <i class="ri-mail-send-line me-1"></i>Wyślij przypomnienie
            </button> -->
          </div>
          <div id="bulk-send-email-info" class="text-muted small mt-1 d-none">
            <?php if (!empty($ksefModeEnabled)): ?>
              <i class="ri-information-line"></i> Wysyłane będą tylko faktury z nadanym numerem KSeF (proformy i rachunki bez wymagań KSeF).
            <?php else: ?>
              <i class="ri-information-line"></i> Wysyłane będą wszystkie zaznaczone faktury (kontrahent musi mieć adres e-mail).
            <?php endif; ?>
          </div>
        </div>
        <!-- Opcje płatności dla akcji 'Oznacz jako opłacone' -->
        <div class="col-md-auto">
          <label class="form-label mb-1 d-block text-muted small">Forma płatności</label>
          <select name="payment_method" id="bulk-payment-method" class="form-select form-select-sm" style="min-width: 160px;">
            <option value="transfer">Przelew</option>
            <option value="cash">Gotówka</option>
            <option value="card">Karta</option>
            <option value="other">Inna</option>
          </select>
        </div>
        <div class="col-md-auto">
          <label class="form-label mb-1 d-block text-muted small">Data płatności</label>
          <div class="d-flex align-items-center gap-2">
            <select name="date_mode" id="bulk-date-mode" class="form-select form-select-sm" style="min-width: 200px;">
              <option value="today">Dzisiaj</option>
              <option value="due">Termin z faktury</option>
              <option value="custom">Wybierz datę…</option>
            </select>
            <input type="date" name="payment_date_custom" id="bulk-payment-date" class="form-control form-control-sm d-none" style="min-width: 160px;" />
          </div>
        </div>
        <div class="col-auto ms-auto">
          <button type="button" class="btn btn-light btn-sm" onclick="clearSelection()">
            <i class="ri-close-line me-1"></i>Anuluj
          </button>
        </div>
      </div>
    </div>
    

<div class="card custom-card">
  <div class="card-header justify-content-between flex-wrap gap-2">
    <div class="card-title">Zarządzaj fakturami</div>
    <div class="d-flex align-items-center gap-2">
      <?php
// ZAMIANA: zamiast pojedynczego linku "Utwórz fakturę" wstaw dropdown:

?>
<div class="dropdown">
  <button class="btn btn-sm btn-primary btn-wave dropdown-toggle d-flex align-items-center"
          type="button" id="addInvoiceDropdown" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="ri-add-line fw-medium align-middle me-1"></i>
    Utwórz…
  </button>
  <ul class="dropdown-menu dropdown-menu-end dropdown-menu-sm-start" aria-labelledby="addInvoiceDropdown" style="min-width: 260px; z-index: 9999;">
    <li>
      <?= $this->Html->link('<i class="ri-file-2-line me-2"></i> Faktura VAT',
        ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'vat']],
        ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'vatInvoice']) ?>
    </li>
    <?php if (!$isDemo): ?>
      <!-- <li>
        <?= $this->Html->link('<i class="ri-file-2-line me-2"></i> Rachunek',
          ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'novat']],
          ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'vatInvoice']) ?>
      </li> -->
      <li>
        <?= $this->Html->link('<i class="ri-price-tag-3-line me-2"></i> Proforma',
          ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'proforma']],
          ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'proforma']) ?>
      </li>
      <li>
        <?= $this->Html->link('<i class="ri-money-dollar-circle-line me-2"></i> Faktura walutowa',
          ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'currency']],
          ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'currencyInvoice']) ?>
      </li>
      <li>
        <?= $this->Html->link('<i class="ri-percent-line me-2"></i> Faktura marża',
          ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'margin']],
          ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'marginInvoice']) ?>
      </li>
      <li>
        <?= $this->Html->link('<i class="ri-money-euro-circle-line me-2"></i> Faktura zaliczkowa',
          ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'advance']],
          ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'advanceInvoice']) ?>
      </li>
    <?php endif; ?>
    <!-- <li>
      <?= $this->Html->link('<i class="ri-edit-2-line me-2"></i> Faktura korygująca',
        ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'correction']],
        ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'correctionInvoice']) ?>
    </li> -->
    <!-- <li>
      <?= $this->Html->link('<i class="ri-global-line me-2"></i> Faktura OSS',
        ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'oss']],
        ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'ossInvoice']) ?>
    </li> -->

    <?php if (!empty($rentalEnabled) && !$isDemo): ?>
      <li><hr class="dropdown-divider my-2"></li>
      <li>
        <?= $this->Html->link('<i class="ri-home-2-line me-2"></i> Faktura najmu prywatnego',
          ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'rental']],
          ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'rentalInvoice']) ?>
      </li>
    <?php endif; ?>
    <?php if ($isDemo): ?>
      <li><hr class="dropdown-divider my-2"></li>
      <li>
        <div class="px-3 py-1 small text-muted">Wersja demo: dostępna tylko Faktura VAT</div>
      </li>
    <?php endif; ?>
  </ul>
</div>
<?php
// KONIEC ZAMIANY
?>

      <!-- Column chooser -->
      <div class="dropdown">
        <button class="btn btn-sm btn-light btn-wave dropdown-toggle d-flex align-items-center" type="button" id="columnsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="ri-layout-grid-line fw-medium align-middle me-1"></i>
          Kolumny
        </button>
        <div class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="columnsDropdown" style="min-width: 260px; max-height: 360px; overflow: auto;">
          <div class="small text-muted mb-2">Wybierz kolumny tabeli</div>
          <?php
            $columns = [
              ['key' => 'col-contractor',  'label' => 'Kontrahent',        'def' => true],
              ['key' => 'col-number',      'label' => 'Numer',             'def' => true],
              ['key' => 'col-type',        'label' => 'Typ',               'def' => true],
              ['key' => 'col-date',        'label' => 'Data wystawienia',  'def' => true],
              ['key' => 'col-amount',      'label' => 'Kwota',             'def' => true],
              ['key' => 'col-paystate',    'label' => 'Status płatności',  'def' => true],
              ['key' => 'col-paydate',     'label' => 'Termin płatności',  'def' => true],
              ['key' => 'col-ksef_status', 'label' => 'KSeF status',       'def' => true],
              ['key' => 'col-ksef_number', 'label' => 'KSeF nr',           'def' => true],
              ['key' => 'col-ksef_desc',   'label' => 'KSeF opis',         'def' => false],
            ];
          ?>
          <?php foreach ($columns as $c): ?>
            <div class="form-check">
              <input class="form-check-input inv-col-toggle" type="checkbox" value="1" data-col="<?= h($c['key']) ?>" id="chk-<?= h($c['key']) ?>">
              <label class="form-check-label" for="chk-<?= h($c['key']) ?>"><?= h($c['label']) ?></label>
            </div>
          <?php endforeach; ?>
          <div class="d-flex justify-content-between mt-2">
            <button type="button" class="btn btn-sm btn-light" id="cols-reset">Reset domyślne</button>
            <button type="button" class="btn btn-sm btn-primary" id="cols-save">Zapisz</button>
          </div>
          <div class="mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="cols-order-reset">Reset kolejności kolumn</button>
          </div>
          <div class="mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="cols-widths-reset">Reset szerokości kolumn</button>
          </div>
        </div>
      </div>


      <?= $this->Form->create(null, ['type' => 'get', 'class' => 'd-flex flex-wrap gap-2 ms-2', 'role' => 'search', 'aria-label' => 'Filtry faktur']) ?>
        <?= $this->Form->control('q', [
          'label' => false,
          'placeholder' => 'Szukaj: numer / kontrahent / NIP',
          'value' => $q,
          'class' => 'form-control form-control-sm',
          'aria-label' => 'Szukaj'
        ]) ?>
        <?= $this->Form->control('state', [
          'type' => 'select',
          'label' => false,
          'empty' => 'Status',
          'options' => [
            'unpaid'  => 'Nieopłacona',
            'partial' => 'Częściowo opłacona',
            'paid'    => 'Opłacona',
            'overdue' => 'Po terminie',
          ],
          'value' => $state,
          'class' => 'form-select form-select-sm',
          'aria-label' => 'Status płatności'
        ]) ?>
        <?= $this->Form->control('currency', [
          'type' => 'text',
          'label' => false,
          'placeholder' => 'Waluta (np. PLN)',
          'value' => $currency,
          'class' => 'form-control form-control-sm',
          'style' => 'width:110px',
          'aria-label' => 'Waluta'
        ]) ?>
        <?= $this->Form->control('from', [
          'type' => 'date',
          'label' => false,
          'value' => $from,
          'class' => 'form-control form-control-sm',
          'aria-label' => 'Data od'
        ]) ?>
        <?= $this->Form->control('to', [
          'type' => 'date',
          'label' => false,
          'value' => $to,
          'class' => 'form-control form-control-sm',
          'aria-label' => 'Data do'
        ]) ?>
        <?= $this->Form->control('email_status', [
          'type' => 'select',
          'label' => false,
          'empty' => 'Status wysyłki',
          'options' => [
            'not_sent' => 'Nie wysłano',
            'pending'  => 'Oczekuje',
            'sending'  => 'Wysyłanie',
            'sent'     => 'Wysłano',
            'failed'   => 'Błąd',
          ],
          'value' => $emailStatus,
          'class' => 'form-select form-select-sm',
          'aria-label' => 'Status wysyłki'
        ]) ?>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-primary btn-wave" type="submit" title="Zastosuj filtry">
            <i class="ri-search-line me-1"></i>Filtruj
          </button>
          <?= $this->Html->link('<i class="ri-refresh-line"></i>', ['action' => 'index'], [
            'class' => 'btn btn-light', 'escape' => false, 'title' => 'Wyczyść filtry'
          ]) ?>
        </div>
      <?= $this->Form->end() ?>
    </div>
  </div>

  <div class="card-body p-0">
    <?= $this->Form->create(null, [
        'type' => 'post',
        'url' => ['action' => 'bulkAction'],
        'id' => 'bulk-actions-form'
    ]) ?>
    <input type="hidden" name="bulk_action" id="bulk-action-input" value="">
    <div class="table-responsive">
      <table class="table text-nowrap align-middle" id="invoices-table">
        <thead>
          <tr>
            <th class="col-select">
              <input type="checkbox" class="form-check-input" id="selectAll" title="Zaznacz wszystkie">
            </th>
            <th class="col-contractor"><?= $this->Paginator->sort('InvoiceContractors.name', 'Kontrahent') ?></th>
            <th class="col-number"><?= $this->Paginator->sort('fullnumber', 'Numer') ?></th>
            <th class="col-type"><?= $this->Paginator->sort('type', 'Typ') ?></th>
            <th class="col-date"><?= $this->Paginator->sort('date', 'Data wystawienia') ?></th>
            <th class="text-end col-amount"><?= $this->Paginator->sort('total', 'Kwota') ?></th>
            <th class="col-paystate"><?= $this->Paginator->sort('paymentstate', 'Status płatności') ?></th>
            <th class="col-paydate"><?= $this->Paginator->sort('paymentdate', 'Termin płatności') ?></th>
            <th class="col-ksef_status">KSeF status</th>
            <th class="col-ksef_number">KSeF nr</th>
            <th class="col-ksef_desc">KSeF opis</th>
            <th class="col-email_status">Status wysyłki</th>
            <th class="text-end col-actions">Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
          <?php
            $__invEmail      = trim((string)($inv->invoice_contractor->email ?? ''));
            $__invKsefNumber = trim((string)($inv->ksef_number ?? ''));
            $__invType       = (string)($inv->type ?? '');
            $__ksefExempt    = in_array($__invType, ['proforma','novat','rental'], true);
          ?>
          <tr class="invoice-list">
            <td class="col-select">
              <input type="checkbox" class="form-check-input invoice-checkbox"
                name="selected[]"
                value="<?= $inv->id ?>"
                data-email="<?= h($__invEmail) ?>"
                data-ksef-number="<?= h($__invKsefNumber) ?>"
                data-ksef-exempt="<?= $__ksefExempt ? '1' : '0' ?>"
                data-inv-type="<?= h($__invType) ?>">
            </td>
            <td class="col-contractor">
              <div>
                <?php
                  $ct = $inv->invoice_contractor ?? null;
                  $tipLines = [];
                  if ($ct) {
                    $addrParts = array_filter([
                      (string)($ct->address ?? ''),
                      trim(((string)($ct->zip ?? '')) . ' ' . ((string)($ct->city ?? '')))
                    ]);
                    if (!empty($addrParts)) { $tipLines[] = 'Adres: ' . h(implode(', ', $addrParts)); }
                    if (!empty($ct->phone)) { $tipLines[] = 'Telefon: ' . h((string)$ct->phone); }
                    if (!empty($ct->email)) { $tipLines[] = 'Email: ' . h((string)$ct->email); }
                  }
                  $tipHtml = implode('<br>', $tipLines);
                ?>
                <p class="mb-0 fw-medium">
                  <span <?= $tipHtml ? 'data-bs-toggle="tooltip" data-bs-html="true" title="' . $tipHtml . '"' : '' ?>>
                    <?= h($ct->name ?? '—') ?>
                  </span>
                </p>
                <?php if (!empty($ct->nip)): ?>
                  <p class="mb-0 fs-11 text-muted">NIP: <?= h($ct->nip) ?>
                    <span role="button" tabindex="0" class="ms-1 text-muted copy-btn" data-copy="<?= h($ct->nip) ?>" title="Kopiuj NIP" data-bs-toggle="tooltip">
                      <i class="ri-file-copy-line"></i>
                    </span>
                  </p>
                <?php elseif (!empty($ct->email)): ?>
                  <p class="mb-0 fs-11 text-muted"><?= h($ct->email) ?></p>
                <?php endif; ?>
              </div>
            </td>
            <td class="col-number">
              <?= $this->Html->link(h($inv->fullnumber ?: $inv->id), ['action' => 'view', $inv->id], ['class' => 'fw-medium text-primary', 'title' => 'Podgląd faktury']) ?>
              <span role="button" tabindex="0" class="ms-1 text-muted copy-btn" data-copy="<?= h((string)($inv->fullnumber ?: $inv->id)) ?>" title="Kopiuj numer" data-bs-toggle="tooltip">
                <i class="ri-file-copy-line"></i>
              </span>
              <?php if ($inv->description): ?>
                <br><small class="text-muted"><?= h(Text::truncate((string)$inv->description, 40, ['ellipsis' => '...', 'exact' => false])) ?></small>
              <?php endif; ?>
            </td>
            <td class="col-type">
              <?php
              $typeLabels = [
                'vat' => '<span class="badge bg-primary">VAT</span>',
                'novat' => '<span class="badge bg-secondary">Rachunek</span>',
                'proforma' => '<span class="badge bg-info">Proforma</span>',
                'advance' => '<span class="badge bg-warning">Zaliczka</span>',
                'correction' => '<span class="badge bg-danger">Korekta</span>',
                'margin' => '<span class="badge bg-success">Marża</span>',
                'internal' => '<span class="badge bg-dark">Wewnętrzna</span>',
                'oss' => '<span class="badge bg-purple">OSS</span>',
                'currency' => '<span class="badge bg-info">Walutowa</span>',
                'final' => '<span class="badge bg-dark">Końcowa</span>',
              ];
              echo $typeLabels[$inv->type] ?? '<span class="badge bg-light text-dark">' . h($inv->type) . '</span>';
              ?>
              <?php if (($inv->type ?? null) === 'proforma'): ?>
                <?php if (!empty($advancesByProforma[$inv->id])): ?>
                  <br>
                  <small class="text-muted">Zaliczki:
                    <?php
                      $icons = [];
                      foreach ($advancesByProforma[$inv->id] as $a) {
                        $title = 'Pobierz PDF zaliczki' . ($a['fullnumber'] ? (' ' . $a['fullnumber']) : '');
                        $icons[] = $this->Html->link(
                          '<i class="ri-printer-line"></i>',
                          ['action' => 'print', $a['id'], '?' => ['download' => 1]],
                          [
                            'escape' => false,
                            'target' => '_blank',
                            'title'  => $title,
                            'class'  => 'text-muted me-1'
                          ]
                        );
                      }
                      echo implode(' ', $icons);
                    ?>
                  </small>
                  <?php
                    // Subtle summary: sum of advances (if amounts available) and remaining to final
                    $advList    = $advancesByProforma[$inv->id] ?? [];
                    $advSum     = 0.0;
                    $hasAmounts = false;
                    $curCode    = strtoupper((string)($inv->currency ?? 'PLN'));
                    if (is_array($advList)) {
                      foreach ($advList as $a) {
                        if (is_array($a) && isset($a['total'])) {
                          $advSum += (float)$a['total'];
                          $hasAmounts = true;
                          if (!empty($a['currency'])) { $curCode = strtoupper((string)$a['currency']); }
                        }
                      }
                    }
                    $proTotal = (float)($inv->total ?? 0);
                    $toFinal  = max(0, round($proTotal - $advSum, 2));
                    $hasFinal = !empty($finalByProforma[$inv->id] ?? null);
                    $finalPaid = $hasFinal && (($finalByProforma[$inv->id]['paymentstate'] ?? '') === 'paid');
                    $tipLines = [
                      'Proforma: ' . $money($proTotal, $inv->currency ?? 'PLN'),
                      'Zaliczki: ' . (is_array($advList) ? count($advList) : 0) . ($hasAmounts ? (' (' . $money($advSum, $curCode) . ')') : ''),
                      'Do końcowej: ' . ($hasAmounts ? $money($toFinal, $curCode) : '—'),
                      'Końcowa: ' . ($hasFinal ? 'tak' : 'nie'),
                    ];
                    $tipText = h(implode("\n", $tipLines));
                  ?>
                  <br>
                  <small class="text-muted">
                    <?php if ($hasAmounts): ?>
                      <?php
                        // Badge-style remaining with optional PLN approximation
                        $fx  = (float)($inv->currency_exchange ?? 0);
                        $fxd = $inv->currency_date ?? null;
                        $fxDateText = '';
                        if ($fxd) { $fxDateText = is_object($fxd) && method_exists($fxd, 'format') ? $fxd->format('Y-m-d') : (string)$fxd; }
                        $titleFx = 'Kurs: 1 ' . h($curCode) . ' = ' . ($fx>0?number_format($fx, 4, ',', ' '):'—') . ' PLN' . ($fxDateText ? (' (NBP: ' . h($fxDateText) . ')') : '');
                        $plnRem = ($curCode !== 'PLN' && $fx > 0) ? round($toFinal * $fx, 2) : null;
                      ?>
                      <?php if ($finalPaid): ?>
                        <span class="badge bg-success-transparent text-success">
                          <i class="ri-check-line me-1"></i>Końcowa opłacona
                        </span>
                      <?php else: ?>
                        <span class="badge bg-secondary-transparent">
                          Pozostało do końcowej: <?= $money($toFinal, $curCode) ?>
                          <?php if ($plnRem !== null): ?>
                            <span class="ms-1 text-muted">≈ <?= $money($plnRem, 'PLN') ?></span>
                            <span class="ms-1 text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= h($titleFx) ?>">
                              <i class="ri-information-line"></i>
                            </span>
                          <?php endif; ?>
                        </span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge bg-secondary-transparent">Proforma: <?= $money($proTotal, $inv->currency ?? 'PLN') ?></span>
                    <?php endif; ?>
                    <span class="ms-1 text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $tipText ?>">
                      <i class="ri-information-line"></i>
                    </span>
                  </small>
                <?php elseif (!empty($advanceCounts[$inv->id])): ?>
                  <br><small class="text-muted">Zaliczki: <?= (int)$advanceCounts[$inv->id] ?></small>
                  <?php
                    // Also show compact tooltip summary when we don't have full list here
                    $proTotal = (float)($inv->total ?? 0);
                    $hasFinal = !empty($finalByProforma[$inv->id] ?? null);
                    $tipLines = [
                      'Proforma: ' . $money($proTotal, $inv->currency ?? 'PLN'),
                      'Zaliczki: ' . (int)$advanceCounts[$inv->id],
                      'Końcowa: ' . ($hasFinal ? 'tak' : 'nie'),
                    ];
                    $tipText = h(implode("\n", $tipLines));
                  ?>
                  <span class="ms-1 text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $tipText ?>">
                    <i class="ri-information-line"></i>
                  </span>
                <?php endif; ?>
                <?php if (!empty($finalByProforma[$inv->id])): ?>
                  <?php $__fn = $finalByProforma[$inv->id]['fullnumber'] ?: 'faktura'; ?>
                  <?php $finalPaid = (($finalByProforma[$inv->id]['paymentstate'] ?? '') === 'paid'); ?>
                  <br><small class="<?= $finalPaid ? 'text-success' : 'text-success' ?>">Końcowa:
                    <?= $this->Html->link(
                          '<i class="ri-printer-line"></i>',
                          ['action' => 'print', $finalByProforma[$inv->id]['id'], '?' => ['download' => 1]],
                          [
                            'escape' => false,
                            'class'  => 'text-success',
                            'target' => '_blank',
                            'title'  => 'Pobierz PDF końcowej ' . h($__fn)
                          ]
                        )
                    ?>
                    <?php if ($finalPaid): ?>
                      <span class="badge bg-success-transparent text-success ms-1"><i class="ri-check-line me-1"></i>Opłacona</span>
                    <?php endif; ?>
                  </small>
                <?php else: ?>
                  <br><small class="text-muted">Końcowa: —</small>
                <?php endif; ?>
              <?php elseif (($inv->type ?? null) === 'final' && !empty($inv->parent_id)): ?>
                <?php $pid = $inv->parent_id; ?>
                <?php if (!empty($advancesByProforma[$pid])): ?>
                  <br>
                  <small class="text-muted">Zaliczki:
                    <?php
                      $icons = [];
                      foreach ($advancesByProforma[$pid] as $a) {
                        $title = 'Pobierz PDF zaliczki' . ($a['fullnumber'] ? (' ' . $a['fullnumber']) : '');
                        $icons[] = $this->Html->link(
                          '<i class="ri-printer-line"></i>',
                          ['action' => 'print', $a['id'], '?' => ['download' => 1]],
                          [
                            'escape' => false,
                            'target' => '_blank',
                            'title'  => $title,
                            'class'  => 'text-muted me-1'
                          ]
                        );
                      }
                      echo implode(' ', $icons);
                    ?>
                  </small>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td class="col-date">
              <?= $inv->date?->format('d.m.Y') ?>
              <br><small class="text-muted">Utworzona: <?= $inv->created?->format('d.m.Y H:i') ?></small>
            </td>
            <td class="text-end col-amount">
              <strong><?= $money($inv->total, $inv->currency) ?></strong>
              <?php
                $totalV = (float)($inv->total ?? 0);
                $paidV  = max(0.0, (float)($inv->alreadypaid ?? 0));
                $pct    = $totalV > 0 ? (int)round(min(100, ($paidV / $totalV) * 100)) : 0;
                $barCls = $pct >= 99 ? 'bg-success' : ($pct > 0 ? 'bg-warning' : 'bg-secondary');
              ?>
              <div class="progress mt-1" style="height:4px;">
                <div class="progress-bar <?= $barCls ?>" style="width: <?= $pct ?>%"></div>
              </div>
              <small class="text-muted"><?= $pct ?>%</small>
              <?php
                $cur = strtoupper((string)($inv->currency ?? 'PLN'));
                $fx  = (float)($inv->currency_exchange ?? 0);
                if ($cur && $cur !== 'PLN' && $fx > 0) {
                  $pln = round((float)($inv->total ?? 0) * $fx, 2);
                  $fxDate = $inv->currency_date ?? null;
                  $fxDateText = '';
                  if ($fxDate) { $fxDateText = is_object($fxDate) && method_exists($fxDate, 'format') ? $fxDate->format('Y-m-d') : (string)$fxDate; }
                  $title = 'Kurs: 1 ' . h($cur) . ' = ' . number_format($fx, 4, ',', ' ') . ' PLN' . ($fxDateText ? (' (NBP: ' . h($fxDateText) . ')') : '');
              ?>
                <br><small class="text-muted">≈ <?= $money($pln, 'PLN') ?>
                  <span class="ms-1 text-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= $title ?>">
                    <i class="ri-information-line"></i>
                  </span>
                </small>
              <?php } ?>
              <?php if ($inv->alreadypaid > 0): ?>
                <br><small class="text-success">Wpłacono: <?= $money($inv->alreadypaid, $inv->currency) ?></small>
              <?php endif; ?>
              <?php if ($inv->remaining > 0): ?>
                <br><small class="text-warning">Pozostało: <?= $money($inv->remaining, $inv->currency) ?></small>
              <?php endif; ?>
            </td>
            <td class="col-paystate">
              <?= $badge($inv->paymentstate) ?>
              <?php if ($inv->paymentstate === 'overdue'): ?>
                <?php
                $today = \Cake\I18n\Date::now();
                $daysOverdue = $inv->paymentdate ? $inv->paymentdate->diffInDays($today, false) : 0;
                ?>
                <br><small class="text-danger"><?= abs($daysOverdue) ?> dni po terminie</small>
              <?php endif; ?>
            </td>
            <td class="col-paydate">
              <?= $inv->paymentdate?->format('d.m.Y') ?>
              <?php if ($inv->paymentdate): ?>
                <?php
                $today = \Cake\I18n\Date::now();
                $daysUntil = $inv->paymentdate->diffInDays($today);
                $isOverdue = $inv->paymentdate->isPast();
                $hasPayments = ((float)($inv->alreadypaid ?? 0)) > 0;
                $isFullyPaid = ((float)($inv->remaining ?? 0)) <= 0 || (($inv->paymentstate ?? null) === 'paid');
                ?>
                <?php if ($isOverdue): ?>
                  <?php if (!$hasPayments): ?>
                    <br><small class="text-danger">Przeterminowana</small>
                  <?php elseif (!$isFullyPaid): ?>
                    <br><small class="text-danger">Po terminie (częściowo zapłacona)</small>
                  <?php endif; ?>
                <?php else: ?>
                  <br><small class="<?= ($daysUntil <= 3 ? 'text-warning' : 'text-muted') ?>">
                    <?= $daysUntil == 0 ? 'Dziś' : "Za {$daysUntil} dni" ?>
                  </small>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td class="col-ksef_status">
              <?php
                $status = trim((string)($inv->ksef_status ?? ''));
                $map = [
                  'accepted' => ['badge' => 'bg-success', 'label' => 'Przyjęta'],
                  'approved' => ['badge' => 'bg-success', 'label' => 'Przyjęta'],
                  'sent'     => ['badge' => 'bg-info',    'label' => 'Wysłana'],
                  'queued'   => ['badge' => 'bg-secondary','label' => 'Kolejka'],
                  'rejected' => ['badge' => 'bg-danger',  'label' => 'Odrzucona'],
                  'error'    => ['badge' => 'bg-danger',  'label' => 'Błąd'],
                ];
                $key = strtolower($status);
                if ($inv->ksef_status === null || $status === '') {
                  echo '<span class="badge bg-secondary">Nie wysłano</span>';
                } elseif (preg_match('/^\d{3}$/', $status) === 1) {
                  $httpCode = (int)$status;
                  if ($httpCode === 200) {
                    echo '<span class="badge bg-success">Wysłane</span>';
                  } else {
                    echo '<span class="badge bg-danger">Błąd wysyłki</span>';
                  }
                } elseif (isset($map[$key])) {
                  $m = $map[$key];
                  echo '<span class="badge ' . h($m['badge']) . '">' . h($m['label']) . '</span>';
                } else {
                  echo '<span class="badge bg-light text-dark">' . h($status) . '</span>';
                }
              ?>
            </td>
            <td class="col-ksef_number">
              <?php
                $__ksefNum = trim((string)($inv->ksef_number ?? ''));
                echo ($__ksefNum !== '') ? h($__ksefNum) : '<span class="text-muted">brak</span>';
                // Wysyłka mogła przejść mimo błędu (np. HTTP 150 „przetwarzanie", potem 440 „duplikat")
                // — pokaż przycisk synchronizacji numeru KSeF, gdy brak numeru a status wskazuje problem.
                $__ksefErr = ((string)($inv->workflow_status ?? '') === 'error')
                  || (preg_match('/^\d{3}$/', $status) === 1 && (int)$status !== 200)
                  || in_array($key, ['error', 'rejected'], true);
                if ($__ksefNum === '' && $__ksefErr):
              ?>
                <button type="button" class="btn btn-sm btn-outline-primary btn-icon ms-1 ksef-sync-btn"
                        data-url="<?= h($this->Url->build(['action' => 'refreshKsefNumber', $inv->id])) ?>"
                        title="Sprawdź w KSeF i uzupełnij numer — wysyłka mogła przejść mimo zgłoszonego błędu">
                  <i class="ri-refresh-line"></i>
                </button>
              <?php endif; ?>
            </td>
            <td class="col-ksef_desc">
              <?= $inv->ksef_desc ? h(Text::truncate((string)$inv->ksef_desc, 40, ['ellipsis' => '…', 'exact' => false])) : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="col-email_status text-center">
              <?php
                $statusBadges = [
                  'pending' => '<i class="ri-time-line me-1"></i>Oczekuje',
                  'sending' => '<i class="ri-mail-send-line me-1"></i>Wysyłanie',
                  'sent' => '<i class="ri-mail-check-line me-1"></i>Wysłano',
                  'failed' => '<i class="ri-mail-close-line me-1"></i>Błąd',
                ];

                $badgeClasses = [
                  'pending' => 'bg-warning-light text-dark',
                  'sending' => 'bg-info-light text-dark',
                  'sent' => 'bg-success-light text-dark',
                  'failed' => 'bg-danger-light text-dark email-status-alert',
                ];

                $popoverContent = '';
                if (!empty($inv->invoice_email_queue) && count($inv->invoice_email_queue) > 0) {
                  $emailCount = count($inv->invoice_email_queue);
                  $popoverContent = '<div class="small">';
                  $lastStatus = null;
                  $lastEmail = null;
                  foreach ($inv->invoice_email_queue as $eq) {
                    $statusLabel = [
                      'pending' => 'Oczekuje',
                      'sending' => 'Wysyłanie',
                      'sent' => 'Wysłano',
                      'failed' => 'Błąd',
                    ][$eq->status] ?? $eq->status;
                    $dateFormatted = is_object($eq->created) && method_exists($eq->created, 'format')
                      ? $eq->created->format('d.m.Y H:i')
                      : (string)$eq->created;
                    $popoverContent .= '<div class="mb-2"><strong>' . h($eq->email) . '</strong><br>';
                    $popoverContent .= '<small class="text-muted">' . h($statusLabel) . '<br>' . h($dateFormatted) . '</small></div>';
                    if ($lastStatus === null) {
                      $lastStatus = $eq->status;
                      $lastEmail = $eq->email;
                    }
                  }
                  // Dodaj przyciski akcji
                  $popoverContent .= '<div class="mt-2 pt-2" style="border-top: 1px solid #dee2e6;">';
                  if ($lastStatus === 'failed') {
                    $popoverContent .= '<button type="button" class="btn btn-sm btn-warning w-100 mb-1 resend-email-btn" data-invoice-id="' . h($inv->id) . '" data-email="' . h($lastEmail) . '" style="font-size: 0.75rem;">';
                    $popoverContent .= '<i class="ri-mail-send-line me-1"></i>Wyślij ponownie</button>';
                  }
                  $popoverContent .= '<button type="button" class="btn btn-sm btn-primary w-100 send-to-other-email-btn" data-invoice-id="' . h($inv->id) . '" style="font-size: 0.75rem;">';
                  $popoverContent .= '<i class="ri-mail-line me-1"></i>Wyślij na inny email</button>';
                  $popoverContent .= '</div>';
                  $popoverContent .= '</div>';

                  $lastEmail = $inv->invoice_email_queue[0];
                  $lastDateFormatted = is_object($lastEmail->created) && method_exists($lastEmail->created, 'format')
                    ? $lastEmail->created->format('d.m.Y H:i')
                    : (string)$lastEmail->created;

                  // Oblicz jak dawno była ostatnia wysyłka
                  $lastDate = is_object($lastEmail->created) ? $lastEmail->created : new \DateTime($lastEmail->created);
                  $now = new \DateTime();
                  $interval = $lastDate->diff($now);

                  $timeAgo = '';
                  if ($interval->days > 0) {
                    $timeAgo = $interval->days === 1 ? 'wczoraj' : ($interval->days . ' dni temu');
                  } elseif ($interval->h > 0) {
                    $timeAgo = $interval->h === 1 ? 'godzinę temu' : ($interval->h . ' h temu');
                  } elseif ($interval->i > 0) {
                    $timeAgo = $interval->i === 1 ? 'minutę temu' : ($interval->i . ' min temu');
                  } else {
                    $timeAgo = 'chwilę temu';
                  }

                  $badgeClass = $badgeClasses[$lastEmail->status] ?? 'bg-light text-dark';
                  echo '<span class="email-status-badge ' . $badgeClass . ' badge cursor-pointer" data-bs-toggle="popover" data-bs-html="true" data-bs-content="' . h($popoverContent) . '" data-bs-trigger="hover focus">';
                  echo $statusBadges[$lastEmail->status] ?? $lastEmail->status;
                  echo ' (' . $emailCount . ')<br><small class="fw-normal" style="font-size: 0.75rem;">' . h($timeAgo) . '</small>';
                  echo '</span>';
                } else {
                  echo '<span class="badge bg-light text-muted"><i class="ri-mail-line me-1"></i>Nie wysłano</span>';
                }
              ?>
            </td>
            <td class="text-end col-actions">
              <div class="dropdown position-static">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" title="Akcje">
                  <i class="ri-settings-3-line me-1 fs-16"></i>
                  <span class="d-none d-md-inline">Akcje</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                  <li>
                    <?= $this->Html->link('<i class="ri-eye-line me-2"></i> Podgląd', ['action' => 'view', $inv->id], [
                      'class' => 'dropdown-item', 'escape' => false, 'title' => 'Podgląd'
                    ]) ?>
                  </li>
                  <?php
                    $__ksefNumForEdit = trim((string)($inv->ksef_number ?? ''));
                    $__ksefStatusForEdit = trim((string)($inv->ksef_status ?? ''));
                    $__workflowStatusForEdit = strtolower(trim((string)($inv->workflow_status ?? '')));
                    $__ksefStatusKey = strtolower($__ksefStatusForEdit);
                    $__ksefNonEditable = ['queued', 'sent', 'accepted', 'approved'];
                    $__ksefSentOk = (preg_match('/^\d{3}$/', $__ksefStatusForEdit) === 1 && (int)$__ksefStatusForEdit === 200);
                    $__workflowLocked = in_array($__workflowStatusForEdit, ['sending', 'sent'], true);
                    $__invoiceEditable = ($__ksefNumForEdit === '') && !$__ksefSentOk && !in_array($__ksefStatusKey, $__ksefNonEditable, true) && !$__workflowLocked;

                    $__typeKeyForEdit = strtolower((string)($inv->type ?? ''));
                    $__editActionByType = [
                      'vat' => 'editVat',
                      'currency' => 'editCurrency',
                      'novat' => 'editNoVat',
                      'proforma' => 'editProforma',
                      'advance' => 'editAdvance',
                      'correction' => 'editCorrection',
                      'margin' => 'editMargin',
                      'internal' => 'editInternal',
                      'internalevidence' => 'editInternalEvidence',
                      'oss' => 'editOss',
                    ];
                    $__editAction = $__editActionByType[$__typeKeyForEdit] ?? 'edit';
                  ?>
                  <?php if ($__invoiceEditable): ?>
                  <li>
                    <?= $this->Html->link('<i class="ri-edit-2-line me-2"></i> Edytuj', ['action' => $__editAction, $inv->id], [
                      'class' => 'dropdown-item', 'escape' => false, 'title' => 'Edytuj'
                    ]) ?>
                  </li>
                  <?php endif; ?>
                  <li>
                    <a href="#" class="dropdown-item btn-pdf-lang"
                       data-url-pl="<?= $this->Url->build(['action' => 'print', $inv->id]) ?>"
                       data-url-en="<?= $this->Url->build(['action' => 'print', $inv->id, '?' => ['lang' => 'en']]) ?>">
                      <i class="ri-printer-line me-2"></i> Pobierz PDF
                    </a>
                  </li>
                  <?php if (in_array($__typeKeyForEdit, ['vat', 'proforma', 'currency', 'margin'], true)): ?>
                  <li>
                    <a href="#" class="dropdown-item" onclick="duplicateInvoiceDialog('<?= h($inv->id) ?>', '<?= h($inv->fullnumber ?: $inv->id) ?>'); return false;" title="Duplikuj fakturę">
                      <i class="ri-file-copy-line me-2"></i> Duplikuj
                    </a>
                  </li>
                  <?php endif; ?>
                  <?php if ($__invEmail !== '' && (!$ksefModeEnabled || $__ksefExempt || $__invKsefNumber !== '')): ?>
                  <li>
                    <a href="#" class="dropdown-item inv-email-btn"
                       data-id="<?= h($inv->id) ?>"
                       data-number="<?= h($inv->fullnumber ?: $inv->id) ?>"
                       title="Wyślij do klienta">
                      <i class="ri-mail-send-line me-2"></i> Wyślij do klienta
                    </a>
                  </li>
                  <?php endif; ?>
                  <?php if ($__invKsefNumber !== ''): ?>
                  <li>
                    <?= $this->Html->link(
                      '<i class="ri-file-download-line me-2"></i> Pobierz UPO',
                      ['action' => 'downloadUpoByInvoice', $inv->id],
                      ['class' => 'dropdown-item', 'escape' => false, 'title' => 'Pobierz Urzędowe Poświadczenie Odbioru']
                    ) ?>
                  </li>
                  <?php endif; ?>
                  <li>
                    <a href="#" class="dropdown-item inv-ksef-log-btn"
                       data-id="<?= h($inv->id) ?>"
                       data-number="<?= h($inv->fullnumber ?: $inv->id) ?>"
                       title="Historia KSeF">
                      <i class="ri-history-line me-2"></i> Historia KSeF
                    </a>
                  </li>
                  <li>
                    <a href="#" class="dropdown-item" title="Rozliczenia" onclick="openPaymentModal('<?= $inv->id ?>', '<?= h($inv->fullnumber ?: $inv->id) ?>', <?= $inv->total ?>, <?= $inv->alreadypaid ?>, <?= $inv->remaining ?>, '<?= $inv->currency ?>'); return false;">
                      <i class="ri-wallet-line me-2"></i> Rozliczenia
                    </a>
                  </li>
                  <?php if (in_array(($inv->type ?? ''), ['vat','advance','final','correction'])): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <?= $this->Html->link('<i class="ri-scissors-line me-2"></i> Wystaw korektę' . (($inv->type ?? '') === 'correction' ? ' do korekty' : ' VAT'), ['action' => 'addCorrection', $inv->id], [
                      'class' => 'dropdown-item', 'escape' => false, 'title' => 'Wystaw korektę VAT'
                    ]) ?>
                  </li>
                  <?php endif; ?>
                  <?php if (($inv->type ?? null) === 'novat'): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <?= $this->Html->link('<i class="ri-scissors-line me-2"></i> Wystaw korektę bez VAT', ['action' => 'addCorrection', $inv->id], [
                      'class' => 'dropdown-item', 'escape' => false, 'title' => 'Wystaw korektę bez VAT'
                    ]) ?>
                  </li>
                  <?php endif; ?>
                  <?php if (($inv->type ?? null) === 'margin'): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <?= $this->Html->link('<i class="ri-scissors-line me-2"></i> Wystaw korektę marży', ['action' => 'addCorrection', $inv->id], [
                      'class' => 'dropdown-item', 'escape' => false, 'title' => 'Wystaw korektę marży'
                    ]) ?>
                  </li>
                  <?php endif; ?>
                  <?php if (in_array(($inv->type ?? ''), ['currency'])): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <?= $this->Html->link('<i class="ri-scissors-line me-2"></i> Wystaw korektę walutową', ['action' => 'addCorrection', $inv->id], [
                      'class' => 'dropdown-item', 'escape' => false, 'title' => 'Wystaw korektę walutową'
                    ]) ?>
                  </li>
                  <?php endif; ?>
                </ul>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>

          <?php if (!count($invoices)): ?>
          <tr>
            <td colspan="12" class="text-center text-muted py-4">
              Brak faktur dla wybranych filtrów. Zmień kryteria lub utwórz pierwszą fakturę.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= $this->Form->end() ?>
  </div>

  <div class="card-footer border-top-0">
    <?php
  $this->Paginator->setTemplates([
  'first'        => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Pierwsza" title="Pierwsza" data-bs-toggle="tooltip"><i class="ri-skip-left-line"></i></a></li>',
  'prevActive'   => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Poprzednia" title="Poprzednia" data-bs-toggle="tooltip"><i class="ri-arrow-left-line"></i></a></li>',
  'prevDisabled' => '<li class="page-item disabled"><a class="page-link disabled" href="javascript:void(0);" tabindex="-1" aria-disabled="true" aria-label="Poprzednia" title="Poprzednia" data-bs-toggle="tooltip"><i class="ri-arrow-left-line"></i></a></li>',
    'number'       => '<li class="page-item"><a class="page-link" href="{{url}}">{{text}}</a></li>',
    'current'      => '<li class="page-item active" aria-current="page"><a class="page-link" href="javascript:void(0);">{{text}}</a></li>',
  'nextActive'   => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Następna" title="Następna" data-bs-toggle="tooltip"><i class="ri-arrow-right-line"></i></a></li>',
  'nextDisabled' => '<li class="page-item disabled"><a class="page-link disabled" href="javascript:void(0);" tabindex="-1" aria-disabled="true" aria-label="Następna" title="Następna" data-bs-toggle="tooltip"><i class="ri-arrow-right-line"></i></a></li>',
  'last'         => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Ostatnia" title="Ostatnia" data-bs-toggle="tooltip"><i class="ri-skip-right-line"></i></a></li>',
    'ellipsis'     => '<li class="page-item disabled"><span class="page-link">…</span></li>'
  ]);
?>
<?php
// CakePHP 5: paging params from PaginatorHelper (PaginatedInterface)
$__params = $this->Paginator->params();
$__pageCount = (int)($__params['pageCount'] ?? 1);
?>
<?php if ($__pageCount > 1): ?>
<div class="mx-auto mt-3">
  <nav aria-label="Nawigacja stron">
    <ul class="pagination justify-content-center">
      <?= $this->Paginator->first(__('Pierwsza')) ?>
      <?= $this->Paginator->prev(__('Poprzednia')) ?>
      <?= $this->Paginator->numbers() ?>
      <?= $this->Paginator->next(__('Następna')) ?>
      <?= $this->Paginator->last(__('Ostatnia')) ?>
    </ul>
  </nav>
</div>
<?php endif; ?>
<div class="col-lg-12 text-center">
    <?php
      $__pageN   = (int)($__params['currentPage'] ?? $__params['page'] ?? 1);
      $__pagesN  = $__pageCount;
      $__currentN= (int)($__params['count'] ?? (is_iterable($invoices) ? count($invoices) : 0));
      $__countN  = (int)($__params['totalCount'] ?? $__currentN);
      $__accWord = function($n){ $n = abs((int)$n); $n10 = $n % 10; $n100 = $n % 100; if ($n === 1) return 'fakturę'; if ($n10 >= 2 && $n10 <= 4 && ($n100 < 12 || $n100 > 14)) return 'faktury'; return 'faktur'; };
      $__genWord = function($n){ return ((int)$n === 1) ? 'faktury' : 'faktur'; };
    ?>
    <?php if ($__pagesN <= 1): ?>
      <p>
        <?php if ($__countN === 0): ?>
          Brak faktur
        <?php else: ?>
          Wyświetlono <?= $__countN ?> <?= $__accWord($__countN) ?>
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p>Strona <?= $__pageN ?> z <?= $__pagesN ?>, wyświetlono <?= $__currentN ?> <?= $__accWord($__currentN) ?> z <?= $__countN ?> <?= $__genWord($__countN) ?></p>
    <?php endif; ?>
</div>
  </div>
</div>
</div>
</div>
<!-- Start::row-1 (karty statystyk) - przeniesione nad kartę -->
<?php
// Etykieta okresu podsumowania: gdy aktywny filtr from/to — pokaż zakres, inaczej bieżący rok.
$__periodLabel = !empty($stats['filtered_period'])
    ? ('okres ' . h($stats['range_from']) . ' – ' . h($stats['range_to']))
    : 'bieżący rok';

// „Suma widocznych faktur" — dokładnie suma total wierszy pasujących do aktywnych filtrów (1:1 z listą).
$__visSums  = $stats['visible_sums'] ?? [];
$__visCount = (int)($stats['visible_count'] ?? 0);
$__visParts = [];
foreach ($__visSums as $__c => $__v) {
    $__visParts[] = $this->Number->format($__v, ['places' => 2]) . ' ' . h($__c);
}
$__visText   = $__visParts ? implode('  +  ', $__visParts) : '0,00';
$__anyFilter = (!empty($q) || !empty($state) || !empty($from) || !empty($to) || !empty($currency));
?>
<div class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2 mt-4 mb-0 py-2">
  <div>
    <i class="ri-sum-line me-1"></i>
    <strong>Suma widocznych faktur</strong>
    <?php if ($__anyFilter): ?><span class="badge bg-primary-transparent ms-1">wg aktywnych filtrów</span><?php endif; ?>
    <span class="text-muted ms-2 small">(<?= $__visCount ?> dok. — korekty netowane: liczy się finalna wartość, oryginał+korekta nie podwaja)</span>
  </div>
  <div class="fs-16 fw-semibold d-flex align-items-center gap-2">
    <span><?= $__visText ?></span>
    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" data-bs-toggle="modal" data-bs-target="#visible-sum-modal" title="Jak wyliczono tę kwotę?">
      <i class="ri-information-line"></i> Szczegóły
    </button>
  </div>
</div>

<?php
// ===== Modal: jak wyliczono „Sumę widocznych faktur" =====
$__typeLabels = [
    'vat' => 'Faktura VAT', 'proforma' => 'Proforma', 'advance' => 'Zaliczkowa',
    'final' => 'Rozliczeniowa / końcowa', 'correction' => 'Korekta', 'margin' => 'Faktura marża',
    'currency' => 'Walutowa', 'novat' => 'Bez VAT (rachunek)', 'internal' => 'Wewnętrzna',
    'internalEvidence' => 'Wewnętrzny dowód', 'oss' => 'OSS', 'rental' => 'Najem',
];
$__breakdown = $stats['visible_breakdown'] ?? [];
$__excluded  = $stats['visible_excluded'] ?? [];
// Grupuj rozbicie po walucie.
$__byCur = [];
foreach ($__breakdown as $__b) { $__byCur[$__b['currency']][] = $__b; }
$__exclByCur = [];
foreach ($__excluded as $__e) { $__exclByCur[$__e['currency']] = $__e; }
?>
<div class="modal fade" id="visible-sum-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ri-sum-line me-1"></i> Jak wyliczono „Sumę widocznych faktur"</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">
          Sumowane są dokumenty pasujące do aktywnych filtrów<?= $__anyFilter ? '' : ' (bez filtra — całość)' ?>.
          Korekty są <strong>netowane</strong>: liczy się finalna wartość — skorygowany oryginał jest pomijany,
          a wliczana jest korekta. Kwot w różnych walutach nie sumujemy razem.
        </p>

        <?php if (empty($__byCur)): ?>
          <div class="alert alert-secondary mb-0">Brak dokumentów w sumie dla bieżących filtrów.</div>
        <?php else: ?>
          <?php foreach ($__byCur as $__cur => $__rows): ?>
            <?php
              $__curTotal = 0.0;
              foreach ($__rows as $__r) { $__curTotal += (float)$__r['sum']; }
              // sort malejąco po kwocie
              usort($__rows, fn($a, $b) => $b['sum'] <=> $a['sum']);
            ?>
            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">Waluta: <?= h($__cur) ?></span>
                <span class="fw-semibold"><?= $this->Number->format($__curTotal, ['places' => 2]) ?> <?= h($__cur) ?></span>
              </div>
              <table class="table table-sm table-bordered mb-1">
                <thead class="table-light">
                  <tr><th>Typ dokumentu</th><th class="text-end" style="width:110px;">Liczba</th><th class="text-end" style="width:160px;">Kwota</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($__rows as $__r): ?>
                    <tr>
                      <td><?= h($__typeLabels[$__r['type']] ?? ($__r['type'] ?: '—')) ?></td>
                      <td class="text-end"><?= (int)$__r['count'] ?></td>
                      <td class="text-end"><?= $this->Number->format($__r['sum'], ['places' => 2]) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr class="fw-semibold">
                    <td>Razem (<?= h($__cur) ?>)</td>
                    <td class="text-end"><?= array_sum(array_map(fn($x) => (int)$x['count'], $__rows)) ?></td>
                    <td class="text-end"><?= $this->Number->format($__curTotal, ['places' => 2]) ?></td>
                  </tr>
                </tfoot>
              </table>
              <?php if (!empty($__exclByCur[$__cur]) && (int)$__exclByCur[$__cur]['count'] > 0): ?>
                <div class="small text-muted">
                  <i class="ri-information-line me-1"></i>
                  Pominięto <strong><?= (int)$__exclByCur[$__cur]['count'] ?></strong> skorygowanych dokumentów
                  (na <?= $this->Number->format($__exclByCur[$__cur]['sum'], ['places' => 2]) ?> <?= h($__cur) ?>) —
                  ich wartość zastępuje korekta (finalna wartość).
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>
<div class="row row-cols-xxl-5 row-cols-xl-3 row-cols-md-2 row-cols-1 mt-4">
  <div class="col">
    <div class="card custom-card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <span class="avatar avatar-md bg-primary text-white" data-bs-toggle="tooltip" title="Przychód (<?= h($__periodLabel) ?>) — bez proform/zaliczek, korekty netowane">
            <i class="ri-file-list-3-line" style="font-size: 1.35rem;"></i>
          </span>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <span class="d-block mb-1 mt-2 text-muted">Przychód (<?= h($__periodLabel) ?>)</span>
            <div class="d-flex align-items-center gap-2">
              <h4 class="fw-medium mb-0"><?= h($stats['currency']) . ' ' . $this->Number->format($stats['year_total'] ?? 0, ['places' => 2]) ?></h4>
              <span class="badge bg-primary-transparent" title="Liczba dokumentów"><?= (int)($stats['year_count'] ?? 0) ?></span>
            </div>
          </div>
          <div class="text-end">
            <span class="fw-semibold mb-1 text-muted">Opłacone</span>
            <span class="d-block text-success fs-13"><?= h($stats['currency']) . ' ' . $this->Number->format($stats['year_paid'] ?? 0, ['places' => 2]) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card custom-card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <span class="avatar avatar-md bg-secondary text-white" data-bs-toggle="tooltip" title="Łącznie opłacone (suma)">
            <i class="ri-check-double-line" style="font-size: 1.35rem;"></i>
          </span>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <span class="d-block mb-1 mt-2 text-muted">Łącznie opłacone</span>
            <div class="d-flex align-items-center gap-2">
              <h4 class="fw-medium mb-0"><?= h($stats['currency']) . ' ' . $this->Number->format($stats['paid_total'] ?? 0, ['places' => 2]) ?></h4>
              <span class="badge bg-success-transparent" title="Liczba opłaconych"><?= (int)($stats['paid_count'] ?? 0) ?></span>
            </div>
          </div>
          <div class="text-end">
            <span class="fw-semibold mb-1 text-muted">Średnia</span>
            <span class="d-block fs-13"><?= h($stats['currency']) . ' ' . $this->Number->format($stats['paid_avg'] ?? 0, ['places' => 2]) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card custom-card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <span class="avatar avatar-md bg-success text-white" data-bs-toggle="tooltip" title="Do opłacenia (liczba dokumentów)">
            <i class="ri-hand-coin-line" style="font-size: 1.35rem;"></i>
          </span>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <span class="d-block mb-1 mt-2 text-muted">Do opłacenia (liczba)</span>
            <div class="d-flex align-items-center gap-2">
              <h4 class="fw-medium mb-0"><?= (int)($stats['pending_count'] ?? 0) ?></h4>
              <span class="badge bg-success-transparent" title="Kwota do opłacenia"><?= h($stats['currency']) . ' ' . $this->Number->format($stats['pending_total'] ?? 0, ['places' => 2]) ?></span>
            </div>
          </div>
          <div class="text-end">
            <span class="fw-semibold mb-1 text-muted">Pozostało</span>
            <span class="d-block fs-13"><?= h($stats['currency']) . ' ' . $this->Number->format($stats['remaining_total'] ?? 0, ['places' => 2]) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card custom-card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <span class="avatar avatar-md bg-info text-white" data-bs-toggle="tooltip" title="Po terminie (liczba dokumentów)">
            <i class="ri-timer-flash-line" style="font-size: 1.35rem;"></i>
          </span>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <span class="d-block mb-1 mt-2 text-muted">Po terminie (liczba)</span>
            <div class="d-flex align-items-center gap-2">
              <h4 class="fw-medium mb-0"><?= (int)($stats['overdue_count'] ?? 0) ?></h4>
              <span class="badge bg-danger-transparent" title="Kwota po terminie"><?= h($stats['currency']) . ' ' . $this->Number->format($stats['overdue_total'] ?? 0, ['places' => 2]) ?></span>
            </div>
          </div>
          <div class="text-end">
            <span class="fw-semibold mb-1 text-muted">Maks. opóźnienie</span>
            <span class="d-block fs-13"><?= (int)($stats['overdue_max_days'] ?? 0) ?> dni</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col">
    <div class="card custom-card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <span class="avatar avatar-md bg-warning text-white" data-bs-toggle="tooltip" title="Opłacone faktury (liczba)">
            <i class="ri-shield-check-line" style="font-size: 1.35rem;"></i>
          </span>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <span class="d-block mb-1 mt-2 text-muted">Opłacone faktury</span>
            <div class="d-flex align-items-center gap-2">
              <h4 class="fw-medium mb-0"><?= (int)($stats['paid_count'] ?? 0) ?></h4>
              <span class="badge bg-success-transparent" title="Suma opłaconych"><?= h($stats['currency']) . ' ' . $this->Number->format($stats['paid_total'] ?? 0, ['places' => 2]) ?></span>
            </div>
          </div>
          <div class="text-end">
            <span class="fw-semibold mb-1 text-muted">W tym miesiącu</span>
            <span class="d-block fs-13"><?= (int)($stats['month_paid_count'] ?? 0) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- End::row-1 -->

<!-- Podsumowanie po walutach -->
<?php if (!empty($stats['by_currency'])): ?>
<div class="row mt-4">
  <div class="col-12">
    <div class="card custom-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
          <i class="ri-exchange-rate-line me-2"></i>Podsumowanie po walutach
        </h6>
        <small class="text-muted">Kliknij na walutę aby filtrować</small>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle text-nowrap mb-0 currency-summary-table" id="currencySummaryTable">
          <thead>
            <tr>
              <th data-sort="currency">Waluta</th>
              <th class="text-end" data-sort="invoice_count">Faktury</th>
              <th class="text-end" data-sort="year_netto">Netto (<?= !empty($stats['filtered_period']) ? 'okres' : 'rok' ?>)</th>
              <th class="text-end" data-sort="year_brutto">Brutto (<?= !empty($stats['filtered_period']) ? 'okres' : 'rok' ?>)</th>
              <th class="text-end" data-sort="paid_brutto">Opłacone</th>
              <th class="text-end" data-sort="pending_brutto">Do zapłaty</th>
              <th class="text-end" data-sort="overdue_brutto">Po terminie</th>
              <th class="text-end" data-sort="paid_percent">% Opłacenia</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $sumInvoiceCount = 0;
              $sumNetto = 0;
              $sumBrutto = 0;
              $sumPaid = 0;
              $sumPending = 0;
              $sumOverdue = 0;
              $totalPaidPercent = 0;

              foreach ($stats['by_currency'] as $cs):
                $sumInvoiceCount += $cs['invoice_count'];
                $sumNetto += $cs['year_netto'];
                $sumBrutto += $cs['year_brutto'];
                $sumPaid += $cs['paid_brutto'];
                $sumPending += $cs['pending_brutto'];
                $sumOverdue += $cs['overdue_brutto'];
            ?>
            <tr class="currency-row" data-currency="<?= h($cs['currency']) ?>">
              <td>
                <span class="badge bg-light text-dark me-1"><?= h($cs['currency']) ?></span>
              </td>
              <td class="amount">
                <span class="badge bg-secondary-transparent"><?= (int)$cs['invoice_count'] ?></span>
              </td>
              <td class="amount"><?= $this->Number->format($cs['year_netto'], ['places' => 0]) ?></td>
              <td class="amount"><?= $this->Number->format($cs['year_brutto'], ['places' => 0]) ?></td>
              <td class="amount text-success">
                <span><?= $this->Number->format($cs['paid_brutto'], ['places' => 0]) ?></span>
                <span class="badge bg-success-transparent text-success ms-1"><?= (int)$cs['paid_count'] ?></span>
              </td>
              <td class="amount text-warning">
                <span><?= $this->Number->format($cs['pending_brutto'], ['places' => 0]) ?></span>
                <span class="badge bg-warning-transparent text-warning ms-1"><?= (int)$cs['pending_count'] ?></span>
              </td>
              <td class="amount text-danger">
                <span><?= $this->Number->format($cs['overdue_brutto'], ['places' => 0]) ?></span>
                <span class="badge bg-danger-transparent text-danger ms-1"><?= (int)$cs['overdue_count'] ?></span>
              </td>
              <td class="amount">
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <span class="fw-semibold"><?= $cs['paid_percent'] ?>%</span>
                  <div class="progress currency-summary-table progress-mini" style="width: 50px;">
                    <div class="progress-bar progress-mini-bar" style="width: <?= min($cs['paid_percent'], 100) ?>%"></div>
                  </div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <tr class="table-light fw-semibold border-top">
              <td>RAZEM</td>
              <td class="amount"><?= (int)$sumInvoiceCount ?></td>
              <td class="amount"><?= $this->Number->format($sumNetto, ['places' => 0]) ?></td>
              <td class="amount"><?= $this->Number->format($sumBrutto, ['places' => 0]) ?></td>
              <td class="amount text-success"><?= $this->Number->format($sumPaid, ['places' => 0]) ?></td>
              <td class="amount text-warning"><?= $this->Number->format($sumPending, ['places' => 0]) ?></td>
              <td class="amount text-danger"><?= $this->Number->format($sumOverdue, ['places' => 0]) ?></td>
              <td class="amount">
                <?php $totalPaidPercent = $sumBrutto > 0 ? round(($sumPaid / $sumBrutto) * 100, 1) : 0; ?>
                <div class="d-flex align-items-center justify-content-end gap-2">
                  <span class="fw-semibold"><?= $totalPaidPercent ?>%</span>
                  <div class="progress currency-summary-table progress-mini" style="width: 50px;">
                    <div class="progress-bar progress-mini-bar" style="width: <?= min($totalPaidPercent, 100) ?>%"></div>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Initialize Bootstrap tooltips for info icons (conversion hint)
document.addEventListener('DOMContentLoaded', function(){
  var els = document.querySelectorAll('[data-bs-toggle="tooltip"]');
  els.forEach(function(el){ if (window.bootstrap && bootstrap.Tooltip) { try { new bootstrap.Tooltip(el); } catch(_) {} } });
  // Copy-to-clipboard handler with quick feedback
  document.body.addEventListener('click', function(e){
    var t = e.target.closest('.copy-btn');
    if (!t) return;
    var text = t.getAttribute('data-copy') || '';
    if (!text) return;
    try {
      navigator.clipboard.writeText(text).then(function(){
        var icon = t.querySelector('i');
        if (icon) {
          var old = icon.className;
          icon.className = 'ri-check-line text-success';
          setTimeout(function(){ icon.className = old; }, 1000);
        }
        if (window.bootstrap && bootstrap.Tooltip) {
          try {
            var tip = bootstrap.Tooltip.getOrCreateInstance(t);
            var original = t.getAttribute('data-bs-original-title') || t.getAttribute('title') || '';
            t.setAttribute('data-bs-original-title', 'Skopiowano');
            tip.show();
            setTimeout(function(){
              tip.hide();
              t.setAttribute('data-bs-original-title', original || 'Kopiuj');
            }, 800);
          } catch(_) {}
        }
      });
    } catch(_) {}
  });
  // Column visibility from localStorage
  (function(){
    const STORAGE_KEY = 'invoices_table_columns.v1';
    const defaults = {
      'col-contractor': true,
      'col-number': true,
      'col-type': true,
      'col-date': true,
      'col-amount': true,
      'col-paystate': true,
      'col-paydate': true,
      'col-ksef_status': true,
      'col-ksef_number': true,
      'col-ksef_desc': false,
      // always-on or infra columns (not user-controlled):
      'col-select': true,
      'col-actions': true,
    };
    function loadState(){
      try { return JSON.parse(localStorage.getItem(STORAGE_KEY)||'{}'); } catch(e){ return {}; }
    }
    function saveState(state){
      try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch(e) {}
    }
    function getState(){
      const stored = loadState();
      return Object.assign({}, defaults, stored);
    }
    function apply(state){
      Object.keys(state).forEach(function(key){
        const on = !!state[key];
        // TH
        document.querySelectorAll('th.'+key).forEach(function(el){ el.classList.toggle('d-none', !on); });
        // TD
        document.querySelectorAll('td.'+key).forEach(function(el){ el.classList.toggle('d-none', !on); });
        // Checkbox in dropdown, if present
        const chk = document.querySelector('.inv-col-toggle[data-col="'+key+'"]');
        if (chk) chk.checked = on;
      });
    }
    // Initialize
    let state = getState();
    apply(state);
    // Save button
    const saveBtn = document.getElementById('cols-save');
    if (saveBtn) saveBtn.addEventListener('click', function(){
      // Read current checkboxes
      document.querySelectorAll('.inv-col-toggle').forEach(function(chk){
        const key = chk.getAttribute('data-col');
        if (key) state[key] = chk.checked;
      });
      saveState(state);
      apply(state);
      // Close dropdown if open
      const menu = document.getElementById('columnsDropdown');
      if (menu && window.bootstrap && bootstrap.Dropdown){
        try { bootstrap.Dropdown.getOrCreateInstance(menu).hide(); } catch(_) {}
      }
    });
    // Reset button
    const resetBtn = document.getElementById('cols-reset');
    if (resetBtn) resetBtn.addEventListener('click', function(){
      state = Object.assign({}, defaults);
      saveState(state);
      apply(state);
    });
    // Live toggle on checkbox change (for instant preview)
    document.querySelectorAll('.inv-col-toggle').forEach(function(chk){
      chk.addEventListener('change', function(){
        const key = this.getAttribute('data-col');
        if (!key) return;
        state[key] = this.checked;
        apply(state);
      });
    });
  })();

  // Resizable columns (persist widths in localStorage)
  (function(){
    const TABLE_ID = 'invoices-table';
    const STORAGE_KEY = 'invoices_table_colwidths.v1';
    const MIN_DEFAULT = 80; // px
    const MIN_WIDTHS = {
      'col-select': 40,
      'col-actions': 120,
      'col-amount': 110,
      'col-paydate': 120,
    };

    function loadWidths(){
      try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch(e) { return {}; }
    }
    function saveWidths(map){
      try { localStorage.setItem(STORAGE_KEY, JSON.stringify(map)); } catch(e) {}
    }
    function firstColKey(el){
      for (const cls of el.classList) { if (cls.indexOf('col-') === 0) return cls; }
      return null;
    }
    function applyWidth(key, px){
      const sel = `#${TABLE_ID} th.${key}, #${TABLE_ID} td.${key}`;
      document.querySelectorAll(sel).forEach(function(node){
        node.style.width = px + 'px';
        node.style.minWidth = px + 'px';
        node.style.maxWidth = px + 'px';
      });
    }
    function applyAll(){
      const map = loadWidths();
      Object.keys(map).forEach(function(k){ applyWidth(k, map[k]); });
    }

    applyAll();

    const ths = document.querySelectorAll(`#${TABLE_ID} thead th`);
    ths.forEach(function(th){
      const key = firstColKey(th);
      if (!key) return;
      th.classList.add('resizable');
      const handle = document.createElement('span');
      handle.className = 'col-resize-handle';
      th.appendChild(handle);

      handle.addEventListener('mousedown', function(e){
        e.preventDefault();
        e.stopPropagation();
        const startX = e.pageX;
        const startWidth = th.offsetWidth;
        const minW = MIN_WIDTHS[key] || MIN_DEFAULT;
        const widths = loadWidths();
        th.classList.add('resizing');

        function onMove(ev){
          const dx = ev.pageX - startX;
          const newW = Math.max(minW, Math.round(startWidth + dx));
          applyWidth(key, newW);
        }
        function onUp(ev){
          const dx = ev.pageX - startX;
          const newW = Math.max(minW, Math.round(startWidth + dx));
          widths[key] = newW;
          saveWidths(widths);
          th.classList.remove('resizing');
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup', onUp);
        }
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
      });

      // Optional: double-click to clear saved width
      handle.addEventListener('dblclick', function(e){
        e.stopPropagation();
        const widths = loadWidths();
        delete widths[key];
        saveWidths(widths);
        // remove inline widths
        const sel = `#${TABLE_ID} th.${key}, #${TABLE_ID} td.${key}`;
        document.querySelectorAll(sel).forEach(function(node){
          node.style.width = '';
          node.style.minWidth = '';
          node.style.maxWidth = '';
        });
      });
    });
  })();

  // Reset column widths button handler
  (function(){
    const btn = document.getElementById('cols-widths-reset');
    if (!btn) return;
    const TABLE_ID = 'invoices-table';
    const STORAGE_KEY = 'invoices_table_colwidths.v1';
    btn.addEventListener('click', function(){
      // Remove persisted widths
      try { localStorage.removeItem(STORAGE_KEY); } catch(e) {}
      // Clear inline widths on all column cells
      document.querySelectorAll(`#${TABLE_ID} th[class*="col-"], #${TABLE_ID} td[class*="col-"]`).forEach(function(node){
        node.style.width = '';
        node.style.minWidth = '';
        node.style.maxWidth = '';
      });
      // Optionally close dropdown
      const menu = document.getElementById('columnsDropdown');
      if (menu && window.bootstrap && bootstrap.Dropdown){
        try { bootstrap.Dropdown.getOrCreateInstance(menu).hide(); } catch(_) {}
      }
    });
  })();

  // Reorder columns via drag & drop (persist order in localStorage)
  (function(){
    const TABLE_ID = 'invoices-table';
    const STORAGE_KEY = 'invoices_table_colorder.v1';

    function firstColKey(el){
      for (const cls of el.classList) { if (cls.indexOf('col-') === 0) return cls; }
      return null;
    }
    function getCurrentOrder(){
      const ths = document.querySelectorAll(`#${TABLE_ID} thead th`);
      const arr = [];
      ths.forEach(th => { const k = firstColKey(th); if (k) arr.push(k); });
      return arr;
    }
    function saveOrder(order){
      try { localStorage.setItem(STORAGE_KEY, JSON.stringify(order)); } catch(e) {}
    }
    function loadOrder(){
      try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch(e) { return []; }
    }
    function applyOrder(order){
      if (!order || !order.length) return;
      const headRow = document.querySelector(`#${TABLE_ID} thead tr`);
      if (!headRow) return;
      const thMap = {};
      headRow.querySelectorAll('th').forEach(th => { const k = firstColKey(th); if (k) thMap[k] = th; });
      const frag = document.createDocumentFragment();
      order.forEach(k => { if (thMap[k]) frag.appendChild(thMap[k]); });
      // Append any remaining headers not in saved order
      headRow.querySelectorAll('th').forEach(th => { if (!frag.contains(th)) frag.appendChild(th); });
      headRow.appendChild(frag);

      // Reorder body rows
      document.querySelectorAll(`#${TABLE_ID} tbody tr`).forEach(tr => {
        const tdMap = {};
        tr.querySelectorAll('td').forEach(td => { const k = firstColKey(td); if (k) tdMap[k] = td; });
        const tdFrag = document.createDocumentFragment();
        order.forEach(k => { if (tdMap[k]) tdFrag.appendChild(tdMap[k]); });
        tr.querySelectorAll('td').forEach(td => { if (!tdFrag.contains(td)) tdFrag.appendChild(td); });
        tr.appendChild(tdFrag);
      });
    }

  // Capture initial DOM order before any changes
  const initialOrder = getCurrentOrder();
  // Apply saved order on load
  const savedOrder = loadOrder();
  if (savedOrder && savedOrder.length) applyOrder(savedOrder);

    // Make headers draggable
    document.querySelectorAll(`#${TABLE_ID} thead th`).forEach(th => {
      const key = firstColKey(th);
      if (!key) return;
      // lock dragging for selection and actions columns
      if (key === 'col-select' || key === 'col-actions') return;
      th.setAttribute('draggable', 'true');
      th.addEventListener('dragstart', function(e){
        e.dataTransfer.setData('text/plain', key);
        e.dataTransfer.effectAllowed = 'move';
      });
      th.addEventListener('dragover', function(e){
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
      });
      th.addEventListener('drop', function(e){
        e.preventDefault();
        const sourceKey = e.dataTransfer.getData('text/plain');
        const targetKey = key;
        if (!sourceKey || !targetKey || sourceKey === targetKey) return;
        let order = loadOrder();
        if (!order || !order.length) order = getCurrentOrder();
        const from = order.indexOf(sourceKey);
        const to = order.indexOf(targetKey);
        if (from === -1 || to === -1) return;
        const after = (e.offsetX > (th.clientWidth / 2));
        order.splice(from, 1);
        const targetIndex = order.indexOf(targetKey);
        const insertIndex = after ? (targetIndex + 1) : targetIndex;
        order.splice(insertIndex, 0, sourceKey);
        saveOrder(order);
        applyOrder(order);
      });
    });

    // Reset order button
    const resetBtn = document.getElementById('cols-order-reset');
    if (resetBtn) {
      resetBtn.addEventListener('click', function(){
        try { localStorage.removeItem(STORAGE_KEY); } catch(e) {}
        if (initialOrder && initialOrder.length) {
          applyOrder(initialOrder);
        } else {
          // fallback: reload
          location.reload();
        }
      });
    }
  })();
});
</script>

<script>
(function() {
  'use strict';

  const KSEF_MODE  = <?= !empty($ksefModeEnabled) ? 'true' : 'false' ?>;
  const BULK_URL   = <?= json_encode($this->Url->build(['action' => 'bulkAction'])) ?>;
  const CSRF_TOKEN_BULK = (document.querySelector('meta[name="csrfToken"]') || {}).getAttribute?.('content') || '';

  /* ── helpers ── */
  function getChecked() {
    return Array.from(document.querySelectorAll('.invoice-checkbox:checked'));
  }
  function getEligibleForEmail(checked) {
    return checked.filter(cb => {
      if (!cb.dataset.email) return false;
      if (KSEF_MODE && cb.dataset.ksefExempt !== '1' && !cb.dataset.ksefNumber) return false;
      return true;
    });
  }
  function swalToast(type, msg) {
    Swal.fire({ toast: true, position: 'top-end', icon: type, title: msg,
      showConfirmButton: false, timer: 4000, timerProgressBar: true });
  }

  /* ---------- PDF language chooser ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-pdf-lang');
    if (!btn) return;
    e.preventDefault();
    Swal.fire({
      title: 'Pobierz PDF',
      html: '<p class="mb-3">Wybierz język faktury:</p>'
          + '<div class="d-flex justify-content-center gap-3">'
          + '  <button id="swal-pdf-pl" class="btn btn-outline-primary btn-lg px-4">'
          + '    <i class="fi fi-pl me-2" style="font-size:1.2em"></i>Polski'
          + '  </button>'
          + '  <button id="swal-pdf-en" class="btn btn-outline-primary btn-lg px-4">'
          + '    <i class="fi fi-gb me-2" style="font-size:1.2em"></i>English'
          + '  </button>'
          + '</div>',
      showConfirmButton: false,
      showCloseButton: true,
      didOpen: function () {
        document.getElementById('swal-pdf-pl').addEventListener('click', function () {
          window.open(btn.dataset.urlPl, '_blank');
          Swal.close();
        });
        document.getElementById('swal-pdf-en').addEventListener('click', function () {
          window.open(btn.dataset.urlEn, '_blank');
          Swal.close();
        });
      }
    });
  });
  function setBtnLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
      btn._origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Przetwarzanie…';
    } else {
      btn.disabled = false;
      btn.innerHTML = btn._origHtml || btn.innerHTML;
    }
  }

  /* ── AJAX bulk submit ── */
  function bulkAjax(action, extraData, btn) {
    const checked = getChecked();
    if (!checked.length) {
      Swal.fire({ icon: 'warning', title: 'Brak zaznaczonych', text: 'Zaznacz co najmniej jedną fakturę.' });
      return;
    }
    setBtnLoading(btn, true);
    const fd = new FormData();
    fd.append('bulk_action', action);
    fd.append('_csrfToken', CSRF_TOKEN_BULK);
    checked.forEach(cb => fd.append('selected[]', cb.value));
    if (extraData) Object.entries(extraData).forEach(([k,v]) => fd.append(k, v));

    fetch(BULK_URL, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
      .then(r => r.json())
      .then(data => {
        setBtnLoading(btn, false);
        const icon = data.type === 'success' ? 'success' : (data.type === 'warning' ? 'warning' : (data.type === 'info' ? 'info' : 'error'));
        swalToast(icon, data.message || (data.success ? 'Gotowe.' : 'Błąd.'));
        if (data.success) {
          window.clearSelection();
        }
      })
      .catch(() => {
        setBtnLoading(btn, false);
        swalToast('error', 'Błąd połączenia. Spróbuj ponownie.');
      });
  }

  /* ── Zaznaczanie ── */
  document.addEventListener('DOMContentLoaded', function () {
    const selectAll      = document.getElementById('selectAll');
    const bar            = document.getElementById('bulk-actions-bar');
    const countEl        = document.getElementById('selected-count');
    const emailInfoEl    = document.getElementById('bulk-send-email-info');

    function updateBar() {
      const n = getChecked().length;
      countEl.textContent = n;
      bar.classList.toggle('d-none', n === 0);
      if (emailInfoEl) emailInfoEl.classList.toggle('d-none', n === 0);
    }
    function updateSelectAll() {
      const all = document.querySelectorAll('.invoice-checkbox');
      const n   = getChecked().length;
      selectAll.indeterminate = n > 0 && n < all.length;
      selectAll.checked       = n > 0 && n === all.length;
    }
    selectAll.addEventListener('change', () => {
      document.querySelectorAll('.invoice-checkbox').forEach(cb => cb.checked = selectAll.checked);
      updateBar(); updateSelectAll();
    });
    document.querySelectorAll('.invoice-checkbox').forEach(cb => {
      cb.addEventListener('change', () => { updateSelectAll(); updateBar(); });
    });
    window.clearSelection = function () {
      document.querySelectorAll('.invoice-checkbox').forEach(cb => cb.checked = false);
      selectAll.checked = false; selectAll.indeterminate = false;
      updateBar();
    };

    /* ── Oznacz jako opłacone ── */
    const btnMarkPaid = document.querySelector('#bulk-actions-bar button[value="mark_paid"]');
    if (btnMarkPaid) {
      btnMarkPaid.addEventListener('click', function (e) {
        e.preventDefault();
        const checked = getChecked();
        if (!checked.length) return;
        Swal.fire({
          title: `Oznacz ${checked.length} faktur(y) jako opłacone`,
          html: `
            <div class="text-start">
              <div class="mb-3">
                <label class="form-label fw-semibold">Forma płatności</label>
                <select id="swal-payment-method" class="form-select form-select-sm">
                  <option value="transfer">Przelew bankowy</option>
                  <option value="cash">Gotówka</option>
                  <option value="card">Karta płatnicza</option>
                  <option value="other">Inna</option>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label fw-semibold">Data płatności</label>
                <select id="swal-date-mode" class="form-select form-select-sm">
                  <option value="today">Dzisiaj</option>
                  <option value="due">Termin z faktury</option>
                  <option value="custom">Wybierz datę…</option>
                </select>
              </div>
              <div id="swal-date-custom-wrap" class="d-none">
                <input type="date" id="swal-date-custom" class="form-control form-control-sm" value="${new Date().toISOString().split('T')[0]}">
              </div>
            </div>`,
          showCancelButton: true,
          confirmButtonText: '<i class="ri-check-line me-1"></i> Oznacz jako opłacone',
          cancelButtonText: 'Anuluj',
          confirmButtonColor: '#198754',
          didOpen: () => {
            document.getElementById('swal-date-mode').addEventListener('change', function () {
              document.getElementById('swal-date-custom-wrap').classList.toggle('d-none', this.value !== 'custom');
            });
          },
          preConfirm: () => {
            const dateMode = document.getElementById('swal-date-mode').value;
            if (dateMode === 'custom' && !document.getElementById('swal-date-custom').value) {
              Swal.showValidationMessage('Wybierz datę płatności.');
              return false;
            }
            return {
              payment_method:       document.getElementById('swal-payment-method').value,
              date_mode:            dateMode,
              payment_date_custom:  document.getElementById('swal-date-custom').value,
            };
          }
        }).then(result => {
          if (!result.isConfirmed) return;
          bulkAjax('mark_paid', result.value, btnMarkPaid);
        });
      });
    }

    /* ── Wyślij e-mailem ── */
    window.bulkSendEmail = function () {
      const checked  = getChecked();
      if (!checked.length) {
        Swal.fire({ icon: 'warning', title: 'Brak zaznaczonych', text: 'Zaznacz co najmniej jedną fakturę.' });
        return;
      }
      const eligible  = getEligibleForEmail(checked);
      const noEmail   = checked.filter(cb => !cb.dataset.email).length;
      const noKsef    = KSEF_MODE ? checked.filter(cb => cb.dataset.ksefExempt !== '1' && !cb.dataset.ksefNumber).length : 0;
      const btnEmail  = document.getElementById('bulk-send-email-btn');

      if (!eligible.length) {
        let html = '<p>Żadna z wybranych faktur nie spełnia warunków wysyłki.</p><ul class="text-start">';
        if (noEmail) html += `<li>${noEmail} faktur(y) — brak adresu e-mail kontrahenta</li>`;
        if (noKsef)  html += `<li>${noKsef} faktur(y) — brak numeru KSeF (wymagany przy włączonym trybie KSeF)</li>`;
        html += '</ul>';
        Swal.fire({ icon: 'warning', title: 'Brak faktur do wysyłki', html });
        return;
      }

      let html = `<p>Do kolejki wysyłki zostanie dodanych <strong>${eligible.length}</strong> faktur.</p>`;
      if (noEmail || noKsef) {
        html += '<ul class="text-start text-muted small">';
        if (noEmail) html += `<li>${noEmail} pominiętych — brak e-mail kontrahenta</li>`;
        if (noKsef)  html += `<li>${noKsef} pominiętych — brak numeru KSeF</li>`;
        html += '</ul>';
      }
      <?php if (!empty($ksefModeEnabled)): ?>
      html += '<p class="small text-muted mt-2"><i class="ri-information-line"></i> Tryb KSeF: wysyłane są faktury z nadanym numerem KSeF (proformy i rachunki bez tego wymogu).</p>';
      <?php endif; ?>

      Swal.fire({
        title: 'Wysyłka e-mailem',
        html,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="ri-mail-send-line me-1"></i> Dodaj do kolejki',
        cancelButtonText: 'Anuluj',
        confirmButtonColor: '#0d6efd',
      }).then(result => {
        if (!result.isConfirmed) return;
        bulkAjax('send_email', null, btnEmail);
      });
    };

    /* ── Ukryj stary formularz submit (teraz wszystko przez AJAX) ── */
    const bulkForm = document.getElementById('bulk-actions-form');
    if (bulkForm) {
      bulkForm.addEventListener('submit', e => e.preventDefault());
    }
  });
})();

// Payment Modal Functions
// Read CSRF token from meta tag for protected AJAX requests
var __csrfMeta = document.querySelector('meta[name="csrfToken"]');
var CSRF_TOKEN = __csrfMeta ? __csrfMeta.getAttribute('content') : '';
function openPaymentModal(invoiceId, invoiceNumber, total, alreadyPaid, remaining, currency) {
    // Set modal data
    document.getElementById('payment-modal-title').textContent = `Rozliczenia faktury: ${invoiceNumber}`;
    document.getElementById('payment-invoice-id').value = invoiceId;
    document.getElementById('payment-total').textContent = formatMoney(total, currency);
    document.getElementById('payment-already-paid').textContent = formatMoney(alreadyPaid, currency);
    document.getElementById('payment-remaining').textContent = formatMoney(remaining, currency);
    
    // Reset form
    document.getElementById('payment-form').reset();
    document.getElementById('payment-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('payment-amount').value = remaining.toFixed(2);
    
    // Load existing payments
    loadPayments(invoiceId);
    
    // Show modal
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function formatMoney(amount, currency = 'PLN') {
    return new Intl.NumberFormat('pl-PL', {
        style: 'decimal',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount) + ' ' + currency;
}

function loadPayments(invoiceId) {
    const tbody = document.getElementById('payments-list');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center"><i class="ri-loader-4-line"></i> Ładowanie...</td></tr>';
    
    fetch(`/invoice-payments?invoice_id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPayments(data.payments);
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Błąd: ${data.message}</td></tr>`;
            }
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Błąd ładowania płatności</td></tr>';
            console.error('Error:', error);
        });
}

function displayPayments(payments) {
    const tbody = document.getElementById('payments-list');
    
    if (payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Brak płatności</td></tr>';
        return;
    }
    
    tbody.innerHTML = payments.map(payment => `
        <tr>
            <td>${payment.payment_date}</td>
            <td class="text-end fw-medium">${formatMoney(payment.amount)}</td>
            <td>${payment.payment_method}</td>
            <td>${payment.description || '-'}</td>
            <td class="text-end">
                <button type="button" class="btn btn-danger btn-sm" onclick="deletePayment('${payment.id}')" title="Usuń płatność">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function addPayment() {
    const form = document.getElementById('payment-form');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Show loading
    const submitBtn = document.getElementById('payment-submit');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="ri-loader-4-line"></i> Dodawanie...';
    
  fetch('/invoice-payments/add', {
        method: 'POST',
    credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Refresh payments list
            loadPayments(data.invoice_id);
            
            // Update invoice totals in modal
            document.getElementById('payment-already-paid').textContent = formatMoney(result.invoice.alreadypaid);
            document.getElementById('payment-remaining').textContent = formatMoney(result.invoice.remaining);
            
            // Reset form
            form.reset();
            document.getElementById('payment-date').value = new Date().toISOString().split('T')[0];
            
            // Update main table if visible
            location.reload();
        } else {
            alert('Błąd dodawania płatności: ' + result.message);
        }
    })
    .catch(error => {
        alert('Błąd dodawania płatności');
        console.error('Error:', error);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

/* ===== Wyślij do klienta ===== */
(function(){
  var CSRF = (document.querySelector('meta[name="csrfToken"]') || {}).content || '';

  document.body.addEventListener('click', function(e){
    var btn = e.target.closest('.inv-email-btn');
    if (!btn) return;
    e.preventDefault();

    var invId     = btn.dataset.id;
    var invNumber = btn.dataset.number;

    // Najpierw pobierz e-maile z serwera (snapshot + contractors lookup)
    Swal.fire({ title: 'Ładowanie...', allowOutsideClick: false, didOpen: function(){ Swal.showLoading(); } });
    fetch('/invoices/contractor-email-lookup/' + invId, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r){ return r.json(); })
      .catch(function(){ return { emails: [] }; })
      .then(function(lookup){
        var foundEmails = (lookup.emails && lookup.emails.length) ? lookup.emails : [''];
        openEmailSwal(invId, invNumber, foundEmails);
      });
  });

  function openEmailSwal(invId, invNumber, initialEmails){
    Swal.fire({
      title: 'Wyślij fakturę do klienta',
      html:
        '<div style="text-align:left">' +
        '<p class="mb-2" style="font-size:13px;color:#666;">Faktura: <strong>' + invNumber + '</strong></p>' +
        '<label class="form-label fw-semibold mb-1">Adresy e-mail odbiorców</label>' +
        '<div id="swal-email-list"></div>' +
        '<button type="button" id="swal-add-email" class="btn btn-sm btn-outline-secondary mt-2">' +
          '<i class="ri-add-line me-1"></i>Dodaj kolejny adres' +
        '</button>' +
        '</div>',
      showCancelButton: true,
      confirmButtonText: '<i class="ri-mail-send-line me-1"></i>Wyślij',
      cancelButtonText: 'Anuluj',
      confirmButtonColor: '#0d6efd',
      focusConfirm: false,
      didOpen: function(){
        initialEmails.forEach(function(e){ addEmailRow(e); });
        document.getElementById('swal-add-email').addEventListener('click', function(){
          addEmailRow('');
        });
      },
      preConfirm: function(){
        var inputs = document.querySelectorAll('#swal-email-list .swal-email-input');
        var emails = [];
        var invalid = false;
        inputs.forEach(function(inp){
          var v = inp.value.trim();
          if (v === '') return;
          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
            inp.classList.add('is-invalid');
            invalid = true;
          } else {
            inp.classList.remove('is-invalid');
            emails.push(v);
          }
        });
        if (invalid) { Swal.showValidationMessage('Popraw nieprawidłowe adresy e-mail.'); return false; }
        if (emails.length === 0) { Swal.showValidationMessage('Wpisz co najmniej jeden adres e-mail.'); return false; }
        return emails;
      }
    }).then(function(result){
      if (!result.isConfirmed) return;
      var emails = result.value;

      Swal.fire({ title: 'Wysyłanie...', allowOutsideClick: false, didOpen: function(){ Swal.showLoading(); } });

      var body = new URLSearchParams();
      emails.forEach(function(e){ body.append('emails[]', e); });

      fetch('/invoices/email-invoice/' + invId, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: body
      })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.success) {
          Swal.fire({ icon: 'success', title: 'Wysłano!', text: 'Faktura została wysłana na: ' + data.sent_to.join(', '), timer: 3000, showConfirmButton: false });
        } else {
          Swal.fire({ icon: 'error', title: 'Błąd', text: data.error || 'Nie udało się wysłać faktury.' });
        }
      })
      .catch(function(){
        Swal.fire({ icon: 'error', title: 'Błąd', text: 'Błąd połączenia z serwerem.' });
      });
    });
  }

  function addEmailRow(value){
    var list = document.getElementById('swal-email-list');
    if (!list) return;
    var row = document.createElement('div');
    row.className = 'd-flex gap-2 mb-2 align-items-center';
    row.innerHTML =
      '<input type="email" class="form-control form-control-sm swal-email-input" placeholder="adres@firma.pl" value="' + escHtml(value) + '" style="flex:1">' +
      '<button type="button" class="btn btn-sm btn-outline-danger swal-remove-email" title="Usuń" style="flex-shrink:0"><i class="ri-close-line"></i></button>';
    row.querySelector('.swal-remove-email').addEventListener('click', function(){
      if (document.querySelectorAll('#swal-email-list .swal-email-input').length > 1) {
        row.remove();
      }
    });
    list.appendChild(row);
    row.querySelector('input').focus();
  }

  function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
})();

function deletePayment(paymentId) {
    if (!confirm('Na pewno usunąć tę płatność?')) {
        return;
    }
    
  fetch(`/invoice-payments/delete/${paymentId}`, {
        method: 'DELETE',
    credentials: 'same-origin',
        headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': CSRF_TOKEN
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            const invoiceId = document.getElementById('payment-invoice-id').value;
            loadPayments(invoiceId);
            // Refresh page to update totals
            location.reload();
        } else {
            alert('Błąd usuwania płatności: ' + result.message);
        }
    })
    .catch(error => {
        alert('Błąd usuwania płatności');
        console.error('Error:', error);
    });
}

function duplicateInvoiceDialog(invoiceId, invoiceNumber) {
  Swal.fire({
    title: 'Duplikować fakturę?',
    html: `Zduplikować fakturę <strong>${invoiceNumber}</strong>? Nowa będzie w trybie roboczym.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Tak, duplikuj',
    cancelButtonText: 'Anuluj',
    confirmButtonColor: '#0d6efd',
    cancelButtonColor: '#6c757d',
  }).then((result) => {
    if (result.isConfirmed) {
      duplicateInvoiceSubmit(invoiceId);
    }
  });
}

function duplicateInvoiceSubmit(invoiceId) {
  // First ask about payment status
  Swal.fire({
    title: 'Jaki status płatności?',
    html: '<p style="text-align: left; margin-bottom: 1.5rem;">Wybierz status płatności dla duplikatu:</p>',
    input: 'radio',
    inputOptions: {
      'unpaid': 'Niezapłacono (reset)',
      'original': 'Jak oryginał (skopiuj)'
    },
    inputValue: 'unpaid',
    confirmButtonText: 'Duplikuj',
    confirmButtonColor: '#0d6efd',
    showCancelButton: true,
    cancelButtonText: 'Anuluj',
    cancelButtonColor: '#6c757d',
  }).then((result) => {
    if (result.isConfirmed) {
      const paymentStatus = result.value;
      duplicateInvoicePerform(invoiceId, paymentStatus);
    }
  });
}

function duplicateInvoicePerform(invoiceId, paymentStatus) {
  Swal.fire({
    title: 'Duplikowanie...',
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });

  const fd = new FormData();
  fd.append('paymentStatus', paymentStatus);

  fetch('<?= $this->Url->build(['action' => 'duplicateInvoice']) ?>/' + invoiceId, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': CSRF_TOKEN || document.querySelector('input[name="_csrfToken"]')?.value || ''
    },
    body: fd
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        title: 'Faktura zduplikowana ✓',
        html: `<div style="text-align: left; font-size: 0.95rem;">
          <p><strong>Nowa faktura jest w trybie roboczym.</strong></p>
          <p style="margin-bottom: 1rem; color: #666;">Pamiętaj:</p>
          <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
            <li><strong>Data wystawienia:</strong> <em>dzisiejsza</em></li>
            <li><strong>Data sprzedaży:</strong> <em>zachowana z oryginału</em></li>
            <li><strong>Status płatności:</strong> <em>${paymentStatus === 'unpaid' ? 'niezapłacono (reset)' : 'jak oryginał'}</em></li>
            <li><strong>Bez numeracji KSeF</strong></li>
          </ul>
        </div>`,
        icon: 'success',
        confirmButtonText: 'Przejdź do faktury',
        confirmButtonColor: '#0d6efd',
        allowOutsideClick: false,
        allowEscapeKey: false,
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = '<?= $this->Url->build(['action' => 'view']) ?>/' + data.duplicatedInvoiceId;
        }
      });
    } else {
      throw new Error(data.message || 'Błąd duplikacji');
    }
  })
  .catch(error => {
    Swal.fire({
      icon: 'error',
      title: 'Błąd',
      text: error.message || 'Nie udało się zduplikować faktury. Spróbuj ponownie.'
    });
    console.error('Error:', error);
  });
}
</script>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="payment-modal-title">Rozliczenia faktury</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Invoice Summary -->
        <div class="row mb-4">
          <div class="col-md-4">
            <div class="card bg-light">
              <div class="card-body text-center">
                <h6 class="text-muted mb-1">Wartość faktury</h6>
                <h5 class="mb-0" id="payment-total">0,00 PLN</h5>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card bg-success-light">
              <div class="card-body text-center">
                <h6 class="text-muted mb-1">Już zapłacono</h6>
                <h5 class="mb-0 text-success" id="payment-already-paid">0,00 PLN</h5>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card bg-warning-light">
              <div class="card-body text-center">
                <h6 class="text-muted mb-1">Pozostało do zapłaty</h6>
                <h5 class="mb-0 text-warning" id="payment-remaining">0,00 PLN</h5>
              </div>
            </div>
          </div>
        </div>

        <!-- Add Payment Form -->
        <div class="card mb-4">
          <div class="card-header">
            <h6 class="mb-0"><i class="ri-add-line me-2"></i>Dodaj płatność</h6>
          </div>
          <div class="card-body">
            <form id="payment-form" onsubmit="event.preventDefault(); addPayment();">
              <input type="hidden" id="payment-invoice-id" name="invoice_id">
              <div class="row">
                <div class="col-md-3">
                  <div class="mb-3">
                    <label for="payment-date" class="form-label">Data płatności</label>
                    <input type="date" class="form-control" id="payment-date" name="payment_date" required>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="mb-3">
                    <label for="payment-amount" class="form-label">Kwota</label>
                    <input type="number" step="0.01" class="form-control" id="payment-amount" name="amount" required>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="mb-3">
                    <label for="payment-method" class="form-label">Metoda płatności</label>
                    <select class="form-select" id="payment-method" name="payment_method" required>
                      <option value="transfer">Przelew</option>
                      <option value="cash">Gotówka</option>
                      <option value="card">Karta</option>
                      <option value="blik">BLIK</option>
                      <option value="check">Czek</option>
                      <option value="other">Inne</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="mb-3">
                    <label for="payment-description" class="form-label">Opis</label>
                    <input type="text" class="form-control" id="payment-description" name="description" placeholder="Opcjonalny opis">
                  </div>
                </div>
              </div>
              <button type="submit" class="btn btn-success" id="payment-submit">
                <i class="ri-add-line me-1"></i>Dodaj płatność
              </button>
            </form>
          </div>
        </div>

        <!-- Existing Payments -->
        <div class="card">
          <div class="card-header">
            <h6 class="mb-0"><i class="ri-list-check me-2"></i>Historia płatności</h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Data</th>
                    <th class="text-end">Kwota</th>
                    <th>Metoda</th>
                    <th>Opis</th>
                    <th class="text-end">Akcje</th>
                  </tr>
                </thead>
                <tbody id="payments-list">
                  <tr>
                    <td colspan="5" class="text-center text-muted">Ładowanie...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>

<!-- KSeF Send Logs Modal -->
<div class="modal fade" id="ksefLogsModal" tabindex="-1" aria-labelledby="ksefLogsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ksefLogsModalLabel">
          <i class="ri-history-line me-2"></i>Historia KSeF
          <span class="text-muted fw-normal small ms-2" id="ksef-log-invoice-number"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="ksef-log-status-bar" class="alert alert-light py-2 px-3 mb-3 d-none">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>
              <span class="text-muted small">Status KSeF:</span>
              <span id="ksef-log-status-badge" class="badge ms-1"></span>
            </div>
            <div id="ksef-log-number-wrap" class="d-none">
              <span class="text-muted small">Numer KSeF:</span>
              <code id="ksef-log-ksef-number" class="ms-1 small user-select-all"></code>
            </div>
          </div>
        </div>
        <div id="ksef-log-spinner" class="text-center py-4">
          <div class="spinner-border text-primary" role="status"></div>
          <div class="text-muted small mt-2">Ładowanie historii…</div>
        </div>
        <div id="ksef-log-empty" class="text-center text-muted py-4 d-none">
          <i class="ri-inbox-line fs-1 d-block mb-2 opacity-50"></i>
          Brak wpisów w historii KSeF dla tej faktury.
        </div>
        <div id="ksef-log-timeline" class="d-none">
          <ul class="list-unstyled mb-0" id="ksef-log-list"></ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Wyślij fakturę na inny email -->
<div class="modal fade" id="sendEmailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Wyślij fakturę na email</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="send-email-form">
        <div class="modal-body">
          <div class="mb-3">
            <label for="send-email-input" class="form-label">Adres email</label>
            <input type="email" id="send-email-input" class="form-control" placeholder="np. klient@example.com" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" class="btn btn-primary">Wyślij</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
#ksef-log-list li { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f0f0f0; }
#ksef-log-list li:last-child { border-bottom:none; }
#ksef-log-list .kl-icon { flex-shrink:0; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; }
#ksef-log-list .kl-body { flex:1; min-width:0; }
#ksef-log-list .kl-label { font-weight:600; font-size:.875rem; }
#ksef-log-list .kl-time  { font-size:.75rem; color:#888; white-space:nowrap; }
#ksef-log-list .kl-msg   { font-size:.8rem; color:#555; margin-top:2px; word-break:break-word; }
#ksef-log-list .kl-meta  { font-size:.75rem; color:#999; margin-top:2px; }
</style>

<script>
(function () {
  var ksefLogModal = document.getElementById('ksefLogsModal');
  var bsModal = null;

  var colorMap = {
    success:   { bg:'#d1fae5', color:'#065f46' },
    danger:    { bg:'#fee2e2', color:'#991b1b' },
    warning:   { bg:'#fef3c7', color:'#92400e' },
    info:      { bg:'#dbeafe', color:'#1e40af' },
    secondary: { bg:'#f1f5f9', color:'#475569' },
  };
  var statusLabels = {
    sent:    { text:'Wysłano',     cls:'bg-success' },
    error:   { text:'Błąd',        cls:'bg-danger' },
    pending: { text:'Oczekuje',    cls:'bg-warning text-dark' },
    blocked: { text:'Zablokowano', cls:'bg-secondary' },
  };

  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  document.body.addEventListener('click', function (e) {
    var btn = e.target.closest('.inv-ksef-log-btn');
    if (!btn) return;
    e.preventDefault();

    var id     = btn.dataset.id;
    var number = btn.dataset.number;

    document.getElementById('ksef-log-invoice-number').textContent = number ? ('— ' + number) : '';
    document.getElementById('ksef-log-status-bar').classList.add('d-none');
    document.getElementById('ksef-log-spinner').classList.remove('d-none');
    document.getElementById('ksef-log-empty').classList.add('d-none');
    document.getElementById('ksef-log-timeline').classList.add('d-none');
    document.getElementById('ksef-log-list').innerHTML = '';
    document.getElementById('ksef-log-number-wrap').classList.add('d-none');

    if (!bsModal) bsModal = new bootstrap.Modal(ksefLogModal);
    bsModal.show();

    fetch('/invoices/ksef-send-logs/' + id + '.json', {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      document.getElementById('ksef-log-spinner').classList.add('d-none');
      if (!data.success) { document.getElementById('ksef-log-empty').classList.remove('d-none'); return; }

      // status bar
      var sm = statusLabels[data.ksef_status] || { text: data.ksef_status || 'Brak', cls: 'bg-light text-muted border' };
      var badge = document.getElementById('ksef-log-status-badge');
      badge.textContent = sm.text;
      badge.className = 'badge ms-1 ' + sm.cls;
      document.getElementById('ksef-log-status-bar').classList.remove('d-none');
      if (data.ksef_number) {
        document.getElementById('ksef-log-ksef-number').textContent = data.ksef_number;
        document.getElementById('ksef-log-number-wrap').classList.remove('d-none');
      }

      if (!data.logs || !data.logs.length) { document.getElementById('ksef-log-empty').classList.remove('d-none'); return; }

      var ul = document.getElementById('ksef-log-list');
      data.logs.forEach(function (log) {
        var p = colorMap[log.color] || colorMap.secondary;
        var meta = [];
        if (log.env)             meta.push('Środowisko: <strong>' + escHtml(log.env) + '</strong>');
        if (log.status_code)     meta.push('Kod HTTP: <strong>' + escHtml(log.status_code) + '</strong>');
        if (log.http_code)       meta.push('Kod HTTP: <strong>' + escHtml(log.http_code) + '</strong>');
        if (log.ksef_number)     meta.push('Nr KSeF: <strong>' + escHtml(log.ksef_number) + '</strong>');
        if (log.exception_class) meta.push('Klasa: <strong>' + escHtml(log.exception_class) + '</strong>');
        if (log.file)            meta.push('Plik: <code style="font-size:.7rem">' + escHtml(log.file) + '</code>');

        var li = document.createElement('li');
        li.innerHTML =
          '<div class="kl-icon" style="background:' + p.bg + ';color:' + p.color + '">'
            + '<i class="' + escHtml(log.icon) + '"></i></div>'
          + '<div class="kl-body">'
            + '<div class="d-flex align-items-baseline gap-2 flex-wrap">'
              + '<span class="kl-label" style="color:' + p.color + '">' + escHtml(log.label) + '</span>'
              + '<span class="kl-time">' + escHtml(log.created) + '</span>'
            + '</div>'
            + (log.message ? '<div class="kl-msg">' + escHtml(log.message) + '</div>' : '')
            + (meta.length  ? '<div class="kl-meta">' + meta.join(' &nbsp;·&nbsp; ') + '</div>' : '')
          + '</div>';
        ul.appendChild(li);
      });
      document.getElementById('ksef-log-timeline').classList.remove('d-none');
    })
    .catch(function () {
      document.getElementById('ksef-log-spinner').classList.add('d-none');
      document.getElementById('ksef-log-empty').classList.remove('d-none');
    });
  });

  // Initialize email status popovers
  (function() {
    if (typeof bootstrap !== 'undefined') {
      document.querySelectorAll('.email-status-badge').forEach(function(el) {
        new bootstrap.Popover(el, {
          html: true,
          trigger: 'hover focus',
          placement: 'left'
        });
      });
    }
  })();
  // Email send actions
  (function() {
    var sendModal = null;
    var currentInvoiceId = null;

    // Resend email
    document.body.addEventListener('click', function(e) {
      var btn = e.target.closest('.resend-email-btn');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();

      var invoiceId = btn.dataset.invoiceId;
      var email = btn.dataset.email;

      if (!email) {
        alert('Brak adresu email do ponownej wysyłki');
        return;
      }

      if (!confirm('Wysłać fakturę na adres: ' + email + '?')) return;

      var formData = new FormData();
      formData.append('emails[]', email);

      fetch('/invoices/email-invoice/' + invoiceId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          alert('Faktura wysłana na: ' + email);
          location.reload();
        } else {
          alert('Błąd: ' + (data.error || 'Nie udało się wysłać'));
        }
      })
      .catch(err => alert('Błąd sieci: ' + err));
    });

    // Send to other email
    document.body.addEventListener('click', function(e) {
      var btn = e.target.closest('.send-to-other-email-btn');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();

      currentInvoiceId = btn.dataset.invoiceId;
      document.getElementById('send-email-input').value = '';

      if (!sendModal) {
        sendModal = new bootstrap.Modal(document.getElementById('sendEmailModal'));
      }
      sendModal.show();
    });

    // Submit send email form
    document.getElementById('send-email-form')?.addEventListener('submit', function(e) {
      e.preventDefault();

      var email = document.getElementById('send-email-input').value.trim();
      if (!email) {
        alert('Wpisz adres email');
        return;
      }

      var formData = new FormData();
      formData.append('emails[]', email);

      fetch('/invoices/email-invoice/' + currentInvoiceId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(data => {
        sendModal.hide();
        if (data.success) {
          alert('Faktura wysłana na: ' + email);
          location.reload();
        } else {
          alert('Błąd: ' + (data.error || 'Nie udało się wysłać'));
        }
      })
      .catch(err => alert('Błąd sieci: ' + err));
    });

    // Currency Summary Table: Sorting
    (function() {
      const table = document.getElementById('currencySummaryTable');
      if (!table) return;

      let sortColumn = null;
      let sortDirection = 'asc';

      Array.from(table.querySelectorAll('thead th[data-sort]')).forEach(th => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function() {
          const newSort = this.dataset.sort;
          if (sortColumn === newSort) {
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
          } else {
            sortColumn = newSort;
            sortDirection = 'asc';
          }
          updateSort();
        });
      });

      function updateSort() {
        if (!sortColumn) return;

        const rows = Array.from(table.querySelectorAll('tbody tr.currency-row'));
        const tbody = table.querySelector('tbody');
        const totalRow = tbody.querySelector('tr:last-child');

        rows.sort((a, b) => {
          const aIdx = sortColumn === 'currency' ? 0 : Array.from(table.querySelectorAll('thead th[data-sort]')).findIndex(th => th.dataset.sort === sortColumn);
          const aCells = a.querySelectorAll('td');
          const bCells = b.querySelectorAll('td');

          let aVal = (aCells[aIdx]?.textContent || '').trim();
          let bVal = (bCells[aIdx]?.textContent || '').trim();

          // Dla kolumn numerycznych
          if (sortColumn !== 'currency') {
            let aNum = parseFloat(aVal.replace(/[^\d.,\-]/g, '').replace(/\s/g, '').replace(',', '.'));
            let bNum = parseFloat(bVal.replace(/[^\d.,\-]/g, '').replace(/\s/g, '').replace(',', '.'));

            if (isNaN(aNum)) aNum = 0;
            if (isNaN(bNum)) bNum = 0;

            return sortDirection === 'asc' ? aNum - bNum : bNum - aNum;
          }

          return sortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });

        rows.forEach(row => tbody.insertBefore(row, totalRow));
      }
    })();
  })();
})();
</script>
<script>
// ===== Synchronizacja numeru KSeF (faktury z błędem wysyłki, które KSeF jednak przyjął) =====
(function(){
  var meta = document.querySelector('meta[name="csrfToken"]');
  var token = meta ? meta.getAttribute('content') : '';
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.ksef-sync-btn');
    if (!btn) return;
    e.preventDefault();
    var url = btn.getAttribute('data-url');
    if (!url || btn.disabled) return;
    btn.disabled = true;
    fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': token, 'Accept': 'application/json' } })
      .then(function(r){ return r.json().catch(function(){ return { success: false, message: 'Nieoczekiwana odpowiedź serwera.' }; }); })
      .then(function(d){
        if (d.success) {
          if (window.Swal) {
            Swal.fire({ icon: 'success', title: 'Numer KSeF uzupełniony', text: d.ksef_number || d.message, timer: 2500, showConfirmButton: false })
              .then(function(){ location.reload(); });
          } else { alert(d.message || 'Uzupełniono numer KSeF.'); location.reload(); }
        } else {
          if (window.Swal) { Swal.fire({ icon: 'info', title: 'Synchronizacja z KSeF', text: d.message || 'Nie udało się uzupełnić numeru.' }); }
          else { alert(d.message || 'Nie udało się uzupełnić numeru KSeF.'); }
          btn.disabled = false;
        }
      })
      .catch(function(err){
        if (window.Swal) { Swal.fire({ icon: 'error', title: 'Błąd synchronizacji', text: String(err) }); }
        else { alert('Błąd synchronizacji: ' + err); }
        btn.disabled = false;
      });
  });
})();
</script>

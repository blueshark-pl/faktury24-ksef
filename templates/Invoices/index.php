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
$q        = trim((string)$this->request->getQuery('q'));
$state    = $this->request->getQuery('state');
$from     = $this->request->getQuery('from');
$to       = $this->request->getQuery('to');
$currency = $this->request->getQuery('currency');

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
      '<i class="ri-upload-cloud-line align-middle me-1"></i> Eksportuj raport do CSV',
      ['action' => 'export', '?' => $this->request->getQueryParams()],
      ['class' => 'btn btn-dark btn-wave me-0', 'escape' => false, 'title' => 'Eksport do CSV z uwzględnieniem filtrów']
    ) ?>
  </div>
</div>
<!-- End::page-header -->



<div class="card">
  <div class="card-body p-0">
  <?= $this->Form->create(null, [
        'type' => 'post', 
        'url' => ['action' => 'bulkAction'], 
        'id' => 'bulk-actions-form'
    ]) ?>
  <input type="hidden" name="bulk_action" id="bulk-action-input" value="">
    
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
            <!-- <button type="submit" name="action" value="send_reminder" class="btn btn-warning">
              <i class="ri-mail-send-line me-1"></i>Wyślij przypomnienie
            </button> -->
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
  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="addInvoiceDropdown" style="min-width: 260px">
    <li>
      <?= $this->Html->link('<i class="ri-file-2-line me-2"></i> Faktura VAT',
        ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'vat']],
        ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'vatInvoice']) ?>
    </li>
    <?php if (!$isDemo): ?>
      <li>
        <?= $this->Html->link('<i class="ri-file-2-line me-2"></i> Faktura bez VAT',
          ['controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'novat']],
          ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'vatInvoice']) ?>
      </li>
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

    <li><hr class="dropdown-divider my-2"></li>

    <li>
      <?= $this->Html->link('<i class="ri-calendar-schedule-line me-2"></i> Zaplanuj fakturę VAT',
        ['controller' => 'ScheduledInvoices', 'action' => 'add', '?' => ['type' => 'vat']],
        ['class' => 'dropdown-item d-flex align-items-center', 'escape' => false, 'data-testid' => 'scheduledInvoice']) ?>
    </li>
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
          'default' => $state,
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
            <th class="text-end col-actions">Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
          <tr class="invoice-list">
            <td class="col-select">
              <input type="checkbox" class="form-check-input invoice-checkbox" name="selected[]" value="<?= $inv->id ?>">
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
                'novat' => '<span class="badge bg-secondary">Bez VAT</span>',
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
              ?>
            </td>
            <td class="col-ksef_desc">
              <?= $inv->ksef_desc ? h(Text::truncate((string)$inv->ksef_desc, 40, ['ellipsis' => '…', 'exact' => false])) : '<span class="text-muted">—</span>' ?>
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
                    $__ksefStatusKey = strtolower($__ksefStatusForEdit);
                    $__ksefNonEditable = ['queued', 'sent', 'accepted', 'approved'];
                    $__ksefSentOk = (preg_match('/^\d{3}$/', $__ksefStatusForEdit) === 1 && (int)$__ksefStatusForEdit === 200);
                    $__invoiceEditable = ($__ksefNumForEdit === '') && !$__ksefSentOk && !in_array($__ksefStatusKey, $__ksefNonEditable, true);
                  ?>
                  <?php if ($__invoiceEditable): ?>
                  <li>
                    <?= $this->Html->link('<i class="ri-edit-2-line me-2"></i> Edytuj', ['action' => 'edit', $inv->id], [
                      'class' => 'dropdown-item', 'escape' => false, 'title' => 'Edytuj'
                    ]) ?>
                  </li>
                  <?php endif; ?>
                  <li>
                    <?= $this->Html->link('<i class="ri-printer-line me-2"></i> Drukuj PDF', ['action' => 'print', $inv->id], [
                      'class' => 'dropdown-item', 'escape' => false, 'title' => 'Drukuj PDF', 'target' => '_blank'
                    ]) ?>
                  </li>
                  <li>
                    <a href="#" class="dropdown-item" title="Rozliczenia" onclick="openPaymentModal('<?= $inv->id ?>', '<?= h($inv->fullnumber ?: $inv->id) ?>', <?= $inv->total ?>, <?= $inv->alreadypaid ?>, <?= $inv->remaining ?>, '<?= $inv->currency ?>'); return false;">
                      <i class="ri-wallet-line me-2"></i> Rozliczenia
                    </a>
                  </li>
                  <?php if (in_array(($inv->type ?? ''), ['vat','advance','final'])): ?>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <?= $this->Html->link('<i class="ri-scissors-line me-2"></i> Wystaw korektę VAT', ['action' => 'addCorrection', $inv->id], [
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
// Determine total page count from request paging data (CakePHP helper-agnostic)
$__paging = (array)$this->request->getAttribute('paging');
$__model = $__paging ? array_keys($__paging)[0] : null;
$__pageCount = $__model && !empty($__paging[$__model]['pageCount']) ? (int)$__paging[$__model]['pageCount'] : 1;
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
      $__paging2 = (array)$this->request->getAttribute('paging');
      $__model2  = $__paging2 ? array_keys($__paging2)[0] : null;
      $__info2   = $__model2 ? ($__paging2[$__model2] ?? []) : [];
      $__pageN   = (int)($__info2['page'] ?? 1);
      $__pagesN  = (int)($__info2['pageCount'] ?? 1);
      $__currentN= (int)($__info2['current'] ?? (is_iterable($invoices) ? count($invoices) : 0));
      $__countN  = (int)($__info2['count'] ?? $__currentN);
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
<div class="row row-cols-xxl-5 row-cols-xl-3 row-cols-md-2 row-cols-1 mt-4">
  <div class="col">
    <div class="card custom-card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <span class="avatar avatar-md bg-primary text-white" data-bs-toggle="tooltip" title="Suma (bieżący rok)">
            <i class="ri-file-list-3-line" style="font-size: 1.35rem;"></i>
          </span>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <span class="d-block mb-1 mt-2 text-muted">Suma (bieżący rok)</span>
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
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
    const bulkActionsBar = document.getElementById('bulk-actions-bar');
    const selectedCount = document.getElementById('selected-count');
    
    // Handle "Select All" checkbox
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        invoiceCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        updateBulkActionsBar();
    });
    
    // Handle individual checkboxes
    invoiceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllState();
            updateBulkActionsBar();
        });
    });
    
    function updateSelectAllState() {
        const checkedCount = document.querySelectorAll('.invoice-checkbox:checked').length;
        const totalCount = invoiceCheckboxes.length;
        
        selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
        selectAllCheckbox.checked = checkedCount === totalCount && totalCount > 0;
    }
    
    function updateBulkActionsBar() {
        const checkedCount = document.querySelectorAll('.invoice-checkbox:checked').length;
        
        if (checkedCount > 0) {
            bulkActionsBar.classList.remove('d-none');
            selectedCount.textContent = checkedCount;
        } else {
            bulkActionsBar.classList.add('d-none');
        }
    }
    
    // Clear selection function
    window.clearSelection = function() {
        invoiceCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
        updateBulkActionsBar();
    };
    
    // Handle bulk actions form submission
    document.getElementById('bulk-actions-form').addEventListener('submit', function(e) {
        const checkedBoxes = document.querySelectorAll('.invoice-checkbox:checked');
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('Proszę wybrać co najmniej jedną fakturę.');
            return false;
        }
        
    // Determine action value reliably across browsers
    const hiddenAction = document.getElementById('bulk-action-input');
    const action = (e.submitter && e.submitter.value) ? e.submitter.value : (hiddenAction ? hiddenAction.value : '');
      // Usuwanie faktur wyłączone — brak akcji delete_selected
        
        // Show loading state
        e.submitter.disabled = true;
        e.submitter.innerHTML = '<i class="ri-loader-4-line"></i> Przetwarzanie...';
    });

  // Keep hidden action in sync when clicking bulk buttons
  document.querySelectorAll('#bulk-actions-bar button[type="submit"][name="action"]').forEach(btn => {
    btn.addEventListener('click', function(){
      const hidden = document.getElementById('bulk-action-input');
      if (hidden) hidden.value = this.value;
    });
  });

  // Toggle custom date input visibility
  const dateModeSel = document.getElementById('bulk-date-mode');
  const dateInput   = document.getElementById('bulk-payment-date');
  if (dateModeSel && dateInput) {
    const syncDateVisibility = () => {
      if (dateModeSel.value === 'custom') {
        dateInput.classList.remove('d-none');
        dateInput.required = true;
      } else {
        dateInput.classList.add('d-none');
        dateInput.required = false;
        dateInput.value = '';
      }
    };
    dateModeSel.addEventListener('change', syncDateVisibility);
    syncDateVisibility();
  }

  // Validate on submit for mark_paid + custom date
  const bulkForm = document.getElementById('bulk-actions-form');
  if (bulkForm) {
    bulkForm.addEventListener('submit', function(e){
      const hiddenAction = document.getElementById('bulk-action-input');
      const actionVal = (e.submitter && e.submitter.value) ? e.submitter.value : (hiddenAction ? hiddenAction.value : '');
      if (actionVal === 'mark_paid' && dateModeSel && dateModeSel.value === 'custom' && dateInput && !dateInput.value) {
        e.preventDefault();
        alert('Wybierz datę płatności.');
        return false;
      }
    });
  }
});

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

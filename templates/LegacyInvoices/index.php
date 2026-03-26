<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', 'Archiwum faktur (faktury24)');

// Obecny rok i miesiąc jako domyślne
$defaultYear  = (int)date('Y');
$defaultMonth = (int)date('n');
// Limit: max 2026/03
if ($defaultYear > 2026) { $defaultYear = 2026; $defaultMonth = 3; }
if ($defaultYear === 2026 && $defaultMonth > 3) $defaultMonth = 3;

$months = [
    1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
    5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
    9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień',
];
?>

<!-- Start::page-header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Archiwum faktur</h1>
    <nav>
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
        <li class="breadcrumb-item active" aria-current="page">Archiwum faktur (faktury24)</li>
      </ol>
    </nav>
  </div>
</div>
<!-- End::page-header -->

<div class="row">
  <div class="col-xl-12">

    <!-- Karta z filtrami -->
    <div class="card custom-card mb-3">
      <div class="card-body py-3">
        <div class="d-flex align-items-end gap-3 flex-wrap">
          <div>
            <label class="form-label small text-muted mb-1">Rok</label>
            <select id="filter-year" class="form-select form-select-sm" style="min-width:90px;">
              <?php for ($y = 2026; $y >= 2010; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $defaultYear ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div>
            <label class="form-label small text-muted mb-1">Miesiąc</label>
            <select id="filter-month" class="form-select form-select-sm" style="min-width:130px;">
              <?php foreach ($months as $num => $name): ?>
                <option value="<?= $num ?>" <?= $num === $defaultMonth ? 'selected' : '' ?>><?= $name ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <button id="btn-fetch" class="btn btn-primary btn-sm">
              <span id="fetch-spinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
              <i class="ri-search-line me-1" id="fetch-icon"></i>
              Wyszukaj
            </button>
          </div>
          <div class="ms-auto">
            <span class="text-muted small" id="result-summary"></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Karta z tabelą -->
    <div class="card custom-card">
      <!-- Stan: ładowanie -->
      <div id="state-loading" class="card-body d-flex align-items-center justify-content-center py-5 gap-3 d-none">
        <div class="spinner-border text-primary" style="width:2rem;height:2rem;" role="status"></div>
        <span class="text-muted">Pobieranie faktur…</span>
      </div>

      <!-- Stan: błąd -->
      <div id="state-error" class="card-body d-none">
        <div class="alert alert-danger mb-0" id="error-msg"></div>
      </div>

      <!-- Stan: pusto -->
      <div id="state-empty" class="card-body text-center text-muted py-5 d-none">
        <i class="ri-file-search-line fs-3 d-block mb-2"></i>
        Brak faktur dla wybranego okresu.
      </div>

      <!-- Stan: brak wyboru (startowy) -->
      <div id="state-idle" class="card-body text-center text-muted py-5">
        <i class="ri-history-line fs-3 d-block mb-2"></i>
        Wybierz rok i miesiąc, a następnie kliknij <strong>Wyszukaj</strong>.
      </div>

      <!-- Stan: tabela -->
      <div id="state-table" class="d-none">
        <div class="card-body pb-0 pt-3 px-3">
          <!-- Podsumowanie kwot -->
          <div class="row g-2 mb-3" id="summary-boxes">
            <div class="col-6 col-md-3">
              <div class="border rounded p-2 text-center">
                <div class="small text-muted">Netto</div>
                <div class="fw-semibold" id="sum-net">—</div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="border rounded p-2 text-center">
                <div class="small text-muted">VAT</div>
                <div class="fw-semibold" id="sum-vat">—</div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="border rounded p-2 text-center">
                <div class="small text-muted">Brutto</div>
                <div class="fw-semibold" id="sum-gross">—</div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="border rounded p-2 text-center">
                <div class="small text-muted">Pozostało</div>
                <div class="fw-semibold" id="sum-remaining">—</div>
              </div>
            </div>
          </div>
        </div>
        <div class="table-responsive" style="max-height:65vh;overflow-y:auto;">
          <table class="table table-hover table-sm align-middle mb-0" id="invoices-table">
            <thead class="table-light position-sticky" style="top:0;z-index:1;">
              <tr>
                <th>Numer</th>
                <th>Typ</th>
                <th>Nabywca</th>
                <th>Data wystawienia</th>
                <th>Termin płatności</th>
                <th class="text-end">Netto</th>
                <th class="text-end">VAT</th>
                <th class="text-end">Brutto</th>
                <th class="text-end">Zapłacono</th>
                <th class="text-end">Pozostało</th>
                <th class="text-center">Status</th>
                <th class="text-center">Akcje</th>
              </tr>
            </thead>
            <tbody id="invoices-tbody"></tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Modal: szczegóły faktury -->
<div class="modal fade" id="legacyInvoiceModal" tabindex="-1" aria-labelledby="legacyInvoiceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="legacyInvoiceModalLabel"><span id="md-number">—</span></h5>
          <small class="text-muted" id="md-type-label"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <!-- Sprzedawca / Nabywca -->
          <div class="col-md-6">
            <div class="card border-0 bg-body-secondary h-100">
              <div class="card-body">
                <h6 class="text-muted fw-normal mb-3"><i class="ri-store-2-line me-1"></i>Sprzedawca</h6>
                <div class="fw-semibold" id="md-seller-name">—</div>
                <div class="small text-muted">NIP: <span id="md-seller-tin">—</span></div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border-0 bg-body-secondary h-100">
              <div class="card-body">
                <h6 class="text-muted fw-normal mb-3"><i class="ri-user-3-line me-1"></i>Nabywca</h6>
                <div class="fw-semibold" id="md-buyer-name">—</div>
                <div class="small text-muted">NIP: <span id="md-buyer-tin">—</span></div>
              </div>
            </div>
          </div>

          <!-- Daty -->
          <div class="col-md-6">
            <div class="card border-0 bg-body-secondary">
              <div class="card-body">
                <h6 class="text-muted fw-normal mb-3"><i class="ri-calendar-line me-1"></i>Daty</h6>
                <dl class="row mb-0 small">
                  <dt class="col-6 text-muted fw-normal">Wystawienia</dt>
                  <dd class="col-6 mb-2" id="md-issue-date">—</dd>
                  <dt class="col-6 text-muted fw-normal">Termin płatności</dt>
                  <dd class="col-6 mb-2" id="md-due-date">—</dd>
                  <dt class="col-6 text-muted fw-normal">Ostatnia wpłata</dt>
                  <dd class="col-6 mb-0" id="md-last-payment">—</dd>
                </dl>
              </div>
            </div>
          </div>

          <!-- Kwoty -->
          <div class="col-md-6">
            <div class="card border-0 bg-body-secondary">
              <div class="card-body">
                <h6 class="text-muted fw-normal mb-3"><i class="ri-money-dollar-circle-line me-1"></i>Kwoty</h6>
                <dl class="row mb-0 small">
                  <dt class="col-6 text-muted fw-normal">Netto</dt>
                  <dd class="col-6 mb-2" id="md-net">—</dd>
                  <dt class="col-6 text-muted fw-normal">VAT</dt>
                  <dd class="col-6 mb-2" id="md-vat">—</dd>
                  <dt class="col-6 text-muted fw-normal">Brutto</dt>
                  <dd class="col-6 fw-semibold mb-2" id="md-gross">—</dd>
                  <dt class="col-6 text-muted fw-normal">Zapłacono</dt>
                  <dd class="col-6 mb-2" id="md-paid">—</dd>
                  <dt class="col-6 text-muted fw-normal">Pozostało</dt>
                  <dd class="col-6 mb-0 fw-semibold" id="md-remaining">—</dd>
                </dl>
              </div>
            </div>
          </div>

          <!-- Historia płatności -->
          <div class="col-12" id="md-payments-section">
            <div class="card border-0 bg-body-secondary">
              <div class="card-body">
                <h6 class="text-muted fw-normal mb-3"><i class="ri-exchange-funds-line me-1"></i>Historia płatności</h6>
                <div id="md-payments-body">
                  <span class="text-muted small">Brak płatności.</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const FETCH_URL   = '<?= $this->Url->build(['controller' => 'LegacyInvoices', 'action' => 'fetch']) ?>';
  const PDF_URL     = '<?= $this->Url->build(['controller' => 'LegacyInvoices', 'action' => 'downloadPdf']) ?>';
  const MAX_YEAR    = 2026;
  const MAX_MONTH   = 3; // marzec 2026

  const selYear   = document.getElementById('filter-year');
  const selMonth  = document.getElementById('filter-month');
  const btnFetch  = document.getElementById('btn-fetch');
  const spinner   = document.getElementById('fetch-spinner');
  const fetchIcon = document.getElementById('fetch-icon');
  const summary   = document.getElementById('result-summary');

  const stateIdle    = document.getElementById('state-idle');
  const stateLoading = document.getElementById('state-loading');
  const stateError   = document.getElementById('state-error');
  const stateEmpty   = document.getElementById('state-empty');
  const stateTable   = document.getElementById('state-table');
  const errorMsg     = document.getElementById('error-msg');
  const tbody        = document.getElementById('invoices-tbody');

  const sumNet       = document.getElementById('sum-net');
  const sumVat       = document.getElementById('sum-vat');
  const sumGross     = document.getElementById('sum-gross');
  const sumRemaining = document.getElementById('sum-remaining');

  // Modal
  const modalEl = document.getElementById('legacyInvoiceModal');
  const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;

  // Ogranicz dostępne miesiące gdy wybrany rok = 2026
  function enforceMonthLimit() {
    const yr = parseInt(selYear.value, 10);
    Array.from(selMonth.options).forEach(opt => {
      const m = parseInt(opt.value, 10);
      opt.disabled = (yr === MAX_YEAR && m > MAX_MONTH);
    });
    if (yr === MAX_YEAR && parseInt(selMonth.value, 10) > MAX_MONTH) {
      selMonth.value = String(MAX_MONTH);
    }
  }
  selYear.addEventListener('change', enforceMonthLimit);
  enforceMonthLimit();

  function showState(name) {
    [stateIdle, stateLoading, stateError, stateEmpty, stateTable].forEach(el => el?.classList.add('d-none'));
    document.getElementById('state-' + name)?.classList.remove('d-none');
  }

  function money(v, cur) {
    const n = parseFloat(v);
    if (isNaN(n)) return '—';
    return n.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (cur || 'PLN');
  }
  function dash(v) { return (v !== null && v !== undefined && v !== '') ? v : '—'; }
  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  let currentPayload = [];

  async function doFetch() {
    const rok     = selYear.value;
    const miesiac = selMonth.value;

    btnFetch.disabled = true;
    spinner.classList.remove('d-none');
    fetchIcon.classList.add('d-none');
    showState('loading');
    summary.textContent = '';

    try {
      const url = FETCH_URL + '?rok=' + rok + '&miesiac=' + miesiac;
      const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await resp.json();

      if (!data.success) {
        errorMsg.textContent = data.error || 'Nieznany błąd.';
        showState('error');
        summary.textContent = '';
        return;
      }

      currentPayload = data.payload || [];

      if (!currentPayload.length) {
        showState('empty');
        summary.textContent = 'Brak wyników.';
        return;
      }

      renderTable(currentPayload);
      showState('table');
      summary.textContent = currentPayload.length + ' faktur';
    } catch (e) {
      errorMsg.textContent = 'Błąd połączenia: ' + (e?.message || e);
      showState('error');
    } finally {
      btnFetch.disabled = false;
      spinner.classList.add('d-none');
      fetchIcon.classList.remove('d-none');
    }
  }

  function renderTable(rows) {
    let totNet = 0, totVat = 0, totGross = 0, totRemaining = 0;

    tbody.innerHTML = rows.map((inv, idx) => {
      const net       = parseFloat(inv.net_total)   || 0;
      const vat       = parseFloat(inv.vat_total)   || 0;
      const gross     = parseFloat(inv.grand_total)  || 0;
      const paid      = parseFloat(inv.total_paid)   || 0;
      const remaining = parseFloat(inv.remaining)    || 0;
      totNet       += net;
      totVat       += vat;
      totGross     += gross;
      totRemaining += remaining;

      const cur = inv.currency || 'PLN';
      const isOverdue = (inv.overdue === true || inv.overdue === 't') && remaining > 0;
      const isPaid    = remaining <= 0;

      const buyerName = inv.ih_buyer_name || inv.buyer_name || '—';
      const buyerTin  = inv.ih_buyer_tin  || inv.buyer_tin  || '';

      const statusBadge = isPaid
        ? '<span class="badge bg-success-transparent"><i class="ri-check-line me-1"></i>Opłacona</span>'
        : (isOverdue
            ? '<span class="badge bg-danger-transparent"><i class="ri-alarm-warning-line me-1"></i>Po terminie</span>'
            : '<span class="badge bg-warning-transparent"><i class="ri-time-line me-1"></i>Oczekuje</span>');

      return `<tr>
        <td><strong>${escHtml(inv.ih_number || '—')}</strong></td>
        <td><span class="badge bg-secondary-transparent">${escHtml(inv.invoice_type || '—')}</span></td>
        <td>
          <div class="fw-semibold">${escHtml(buyerName)}</div>
          ${buyerTin ? '<div class="small text-muted">NIP: ' + escHtml(buyerTin) + '</div>' : ''}
        </td>
        <td>${escHtml(inv.issue_date || '—')}</td>
        <td class="${isOverdue ? 'text-danger fw-semibold' : ''}">${escHtml(inv.due_date || '—')}</td>
        <td class="text-end">${money(inv.net_total, cur)}</td>
        <td class="text-end">${money(inv.vat_total, cur)}</td>
        <td class="text-end fw-semibold">${money(inv.grand_total, cur)}</td>
        <td class="text-end">${money(inv.total_paid, cur)}</td>
        <td class="text-end ${remaining > 0 ? (isOverdue ? 'text-danger' : 'text-warning') : 'text-success'}">${money(inv.remaining, cur)}</td>
        <td class="text-center">${statusBadge}</td>
        <td class="text-center">
          <div class="btn-list">
            <button class="btn btn-sm btn-primary-light btn-icon js-invoice-detail" data-idx="${idx}" title="Szczegóły">
              <i class="ri-eye-line"></i>
            </button>
            <a class="btn btn-sm btn-success-light btn-icon" href="${PDF_URL}?uuid=${encodeURIComponent(inv.ih_uuid || '')}" target="_blank" title="Pobierz PDF">
              <i class="ri-file-pdf-2-line"></i>
            </a>
          </div>
        </td>
      </tr>`;
    }).join('');

    // Sumy
    const cur = rows[0]?.currency || 'PLN';
    sumNet.textContent       = money(totNet, cur);
    sumVat.textContent       = money(totVat, cur);
    sumGross.textContent     = money(totGross, cur);
    sumRemaining.textContent = money(totRemaining, cur);

    // Podpięcie przycisków szczegółów
    tbody.querySelectorAll('.js-invoice-detail').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx, 10);
        openDetail(currentPayload[idx]);
      });
    });
  }

  function openDetail(inv) {
    if (!bsModal || !inv) return;
    const cur = inv.currency || 'PLN';

    document.getElementById('md-number').textContent      = inv.ih_number || '—';
    document.getElementById('md-type-label').textContent  = inv.invoice_type ? ('Typ: ' + inv.invoice_type) : '';
    document.getElementById('md-seller-name').textContent = (inv.ih_seller_name || inv.seller_name || '—').replace(/\n/g, ', ');
    document.getElementById('md-seller-tin').textContent  = inv.ih_seller_tin  || inv.seller_tin  || '—';
    document.getElementById('md-buyer-name').textContent  = (inv.ih_buyer_name  || inv.buyer_name  || '—').replace(/\n/g, ', ');
    document.getElementById('md-buyer-tin').textContent   = inv.ih_buyer_tin   || inv.buyer_tin   || '—';
    document.getElementById('md-issue-date').textContent  = dash(inv.issue_date);
    document.getElementById('md-due-date').textContent    = dash(inv.due_date);
    document.getElementById('md-last-payment').textContent= dash(inv.last_payment_date);
    document.getElementById('md-net').textContent         = money(inv.net_total, cur);
    document.getElementById('md-vat').textContent         = money(inv.vat_total, cur);
    document.getElementById('md-gross').textContent       = money(inv.grand_total, cur);
    document.getElementById('md-paid').textContent        = money(inv.total_paid, cur);
    document.getElementById('md-remaining').textContent   = money(inv.remaining, cur);

    // Historia płatności
    const paymentsEl = document.getElementById('md-payments-body');
    const payments   = inv.payments || [];
    if (!payments.length) {
      paymentsEl.innerHTML = '<span class="text-muted small">Brak płatności.</span>';
    } else {
      paymentsEl.innerHTML = `<table class="table table-sm mb-0 small">
        <thead><tr>
          <th>Data</th><th class="text-end">Kwota</th><th>Uwagi</th>
        </tr></thead>
        <tbody>
          ${payments.map(p => `<tr>
            <td>${escHtml(p.payment_date || '—')}</td>
            <td class="text-end fw-semibold">${money(p.amount, cur)}</td>
            <td class="text-muted">${escHtml(p.notes || '')}</td>
          </tr>`).join('')}
        </tbody>
      </table>`;
    }

    bsModal.show();
  }

  btnFetch.addEventListener('click', doFetch);

  // Automatyczne wyszukanie przy wejściu na stronę
  doFetch();
});
</script>

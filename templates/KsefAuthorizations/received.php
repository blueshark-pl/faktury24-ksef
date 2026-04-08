<?php
/**
 * @var \App\View\AppView $this
 * @var array $invoices
 * @var array $apiInfo
 * @var array $certInfo
 */
$this->assign('title', 'Dokumenty otrzymane z KSeF');
$env   = (string)$this->getRequest()->getQuery('env', 'prod');
$page  = max(1, (int)$this->getRequest()->getQuery('page', 1));
$total = (int)($apiInfo['total'] ?? 0);

$money = function ($amount, $cur = 'PLN') {
    return number_format((float)$amount, 2, ',', "\u{00a0}") . ' ' . $cur;
};

// Statystyki per waluta (z bieżącej strony)
$currencies = [];
if (!empty($invoices)) {
    foreach ($invoices as $inv) {
        $c = strtoupper($inv['currency'] ?? 'PLN');
        if (!isset($currencies[$c])) {
            $currencies[$c] = ['count' => 0, 'sum' => 0.0];
        }
        $currencies[$c]['count']++;
        $currencies[$c]['sum'] += (float)($inv['total'] ?? 0);
    }
}
?>

<style>
/* ---------- KSeF Received – page styles ---------- */
.ksef-table th {
    font-weight: 600;
    font-size: .8125rem;
    text-transform: uppercase;
    letter-spacing: .025em;
    color: #6c757d;
    white-space: nowrap;
}
.ksef-table td { vertical-align: middle; }
.ksef-table tbody tr { transition: background-color .12s ease; }
.ksef-table tbody tr:hover { background-color: rgba(var(--bs-primary-rgb), .04); }
.ksef-number-badge {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: .75rem;
}
.stat-card { border: 0; border-radius: .75rem; }
.stat-card .stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
.stat-card .stat-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; }
a.icon-clip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: .25rem;
    color: var(--bs-secondary-color);
    text-decoration: none;
    transition: background-color .12s;
}
a.icon-clip:hover { background-color: var(--bs-tertiary-bg); }
a.icon-clip i { font-size: .85rem; }
.toast-container.received-toasts { z-index: 1080; }
.env-badge { font-size: .65rem; padding: .2em .5em; }
.btn-xs { font-size: .7rem; padding: .15em .45em; }
</style>

<!-- Start::page-header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Dokumenty otrzymane z KSeF</h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item">Księgowość</li>
      <li class="breadcrumb-item active" aria-current="page">Otrzymane</li>
    </ol>
  </div>
  <div class="btn-list d-flex align-items-center gap-2">
    <?php if (!empty($certInfo)): ?>
      <span class="badge bg-success-subtle text-success border env-badge">
        <i class="ri-shield-check-line me-1"></i><?= h($certInfo['subjectCN'] ?? 'Cert aktywny') ?>
        <?php if (!empty($certInfo['nip'])): ?> &middot; NIP <?= h($certInfo['nip']) ?><?php endif; ?>
      </span>
    <?php endif; ?>
    <span class="badge bg-<?= $env === 'prod' ? 'primary' : 'warning' ?>-subtle text-<?= $env === 'prod' ? 'primary' : 'warning' ?> border env-badge">
      <?= $env === 'prod' ? 'Produkcja' : 'Test' ?>
    </span>
    <a class="btn btn-sm btn-outline-primary btn-wave" href="<?= $this->Url->build(['action' => 'issued', '?' => $this->getRequest()->getQueryParams()]) ?>">
      <i class="ri-send-plane-line me-1"></i>Wystawione
    </a>
  </div>
</div>
<!-- End::page-header -->

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card stat-card shadow-sm">
      <div class="card-body py-3">
        <div class="stat-label mb-1">Dokumentów na stronie</div>
        <div class="stat-value text-primary"><?= count($invoices ?? []) ?></div>
        <?php if ($total > 0): ?>
          <div class="small text-muted mt-1">Łącznie w API: <?= number_format($total) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php $idx = 0; foreach ($currencies as $cur => $s): if ($idx >= 3) break; ?>
  <div class="col-sm-6 col-lg-3">
    <div class="card stat-card shadow-sm">
      <div class="card-body py-3">
        <div class="stat-label mb-1">Suma <?= h($cur) ?> (<?= $s['count'] ?>)</div>
        <div class="stat-value"><?= $money($s['sum'], $cur) ?></div>
      </div>
    </div>
  </div>
  <?php $idx++; endforeach; ?>
  <?php if (!empty($apiInfo['hasMore']) || !empty($apiInfo['isTruncated'])): ?>
  <div class="col-sm-6 col-lg-3">
    <div class="card stat-card shadow-sm border-warning">
      <div class="card-body py-3">
        <div class="stat-label mb-1 text-warning">Więcej wyników</div>
        <div class="small text-muted">API KSeF zwróciło częściowe wyniki. Zawęź zakres dat.</div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Filters -->
<?= $this->element('Ksef/filters', [
    'currentAction' => 'received',
    'peerAction' => 'issued',
    'storageKey' => 'ksef_filters_received',
]) ?>

<!-- Debug trace (only when enabled) -->
<?php if (!empty($ksefTraceEnabled)): ?>
  <?php
    $diag = is_array($ksefDiag ?? null) ? $ksefDiag : [];
    $trace = is_array($ksefTrace ?? null) ? $ksefTrace : [];
  ?>
  <div class="card shadow-sm mb-4 border-warning">
    <div class="card-header py-2 bg-warning-subtle d-flex align-items-center gap-2 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#ksef-debug-body" role="button">
      <i class="ri-bug-line"></i>
      <span class="fw-semibold small">Debug KSeF</span>
      <span class="badge bg-warning text-dark ms-auto"><?= count($trace) ?> req</span>
    </div>
    <div class="collapse" id="ksef-debug-body">
      <div class="card-body small">
        <?php if (!empty($diag)): ?>
          <div class="mb-2">
            <span class="text-muted">Env:</span> <?= h($diag['environment'] ?? '') ?>
            &middot; <span class="text-muted">API:</span> <?= h($diag['apiUrl'] ?? '') ?>
            &middot; <span class="text-muted">Auth:</span> <?= h($diag['authMethod'] ?? '') ?>
            &middot; <span class="text-muted">NIP:</span> <?= h($diag['identifierNip'] ?? '') ?>
            <?php if (!empty($diag['certPresent'])): ?>
              &middot; <span class="text-muted">Cert:</span> <?= h($diag['certFile'] ?? '') ?>
              (<?= ($diag['certReadable'] ?? null) === true ? 'OK' : 'BRAK' ?>)
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($trace)): ?>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead><tr><th style="width:140px">Czas</th><th style="width:80px">Metoda</th><th>URL</th><th style="width:80px">Status</th></tr></thead>
              <tbody>
                <?php foreach ($trace as $step): ?>
                  <?php $status = $step['status'] ?? null; $isError = isset($step['exceptionClass']) || (is_int($status) && $status >= 400); ?>
                  <tr class="<?= $isError ? 'table-danger' : '' ?>">
                    <td><?= h($step['ts'] ?? '') ?></td>
                    <td><code><?= h($step['method'] ?? '') ?></code></td>
                    <td class="text-break" style="max-width:400px">
                      <?= h($step['uri'] ?? '') ?>
                      <?php if (isset($step['exceptionClass'])): ?>
                        <div class="text-danger"><strong><?= h($step['exceptionClass']) ?>:</strong> <?= h($step['exceptionMessage'] ?? '') ?></div>
                      <?php endif; ?>
                      <?php if (!empty($step['responseBody'])): ?>
                        <details class="mt-1"><summary>Response</summary><pre class="mb-0" style="white-space:pre-wrap;font-size:.75rem"><?= h($step['responseBody']) ?></pre></details>
                      <?php endif; ?>
                    </td>
                    <td><?= $status !== null ? h((string)$status) : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-muted">Brak trace (nie wykonano requestów HTTP).</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Main table card -->
<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 ksef-table">
        <thead class="table-light">
          <tr>
            <th style="width:100px">Data</th>
            <th>Numer faktury</th>
            <th>Kontrahent</th>
            <th class="text-end" style="width:140px">Kwota brutto</th>
            <th style="width:60px">Waluta</th>
            <th style="width:130px">Typ</th>
            <th style="width:110px" class="text-center">Akcje</th>
            <th style="width:140px">Księgowanie</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($invoices)): ?>
            <?php foreach ($invoices as $row): ?>
              <tr>
                <!-- Data -->
                <td class="text-nowrap"><?= $row['date']?->i18nFormat('yyyy-MM-dd') ?? '' ?></td>

                <!-- Numer -->
                <td>
                  <div class="fw-semibold"><?= h($row['fullnumber'] ?? '') ?></div>
                  <?php if (!empty($row['ksef_number'])): ?>
                    <div class="d-flex align-items-center gap-1 mt-1">
                      <span class="badge bg-light text-dark border ksef-number-badge">KSeF: <?= h($row['ksef_number']) ?></span>
                      <a href="#" class="copy-ksef icon-clip" data-ksef="<?= h($row['ksef_number']) ?>" title="Kopiuj numer KSeF">
                        <i class="bi bi-clipboard"></i>
                      </a>
                    </div>
                  <?php endif; ?>
                </td>

                <!-- Kontrahent -->
                <td>
                  <div><?= h($row['InvoiceContractors']['name'] ?? '') ?></div>
                  <?php if (!empty($row['InvoiceContractors']['tax_id'])): ?>
                    <div class="small text-muted">NIP: <?= h($row['InvoiceContractors']['tax_id']) ?></div>
                  <?php endif; ?>
                </td>

                <!-- Kwota brutto -->
                <td class="text-end fw-semibold text-nowrap"><?= $money($row['total'] ?? 0, $row['currency'] ?? 'PLN') ?></td>

                <!-- Waluta -->
                <td><span class="badge bg-light text-dark border"><?= h($row['currency'] ?? 'PLN') ?></span></td>

                <!-- Typ / Status -->
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    <?php if (!empty($row['invoiceType'])): ?>
                      <?php
                        $typeBg = match($row['invoiceType']) {
                            'KOR' => 'danger',
                            'ZAL' => 'info',
                            'ROZ' => 'warning',
                            default => 'secondary',
                        };
                      ?>
                      <span class="badge bg-<?= $typeBg ?>-subtle text-<?= $typeBg ?> border"><?= h($row['invoiceType']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['invoicingMode'])): ?>
                      <span class="badge bg-secondary-subtle text-secondary border"><?= h($row['invoicingMode']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($row['isSelfInvoicing'])): ?>
                      <span class="badge bg-info-subtle text-info border">Self-billing</span>
                    <?php endif; ?>
                  </div>
                </td>

                <!-- Akcje -->
                <td class="text-center">
                  <?php if (!empty($row['ksef_number'])): ?>
                    <div class="btn-group btn-group-sm">
                      <a class="btn btn-sm btn-outline-primary" href="<?= $this->Url->build(['action' => 'download', $row['ksef_number'], '?' => ['env' => $env]]) ?>" title="Pobierz XML">
                        <i class="ri-download-2-line"></i>
                      </a>
                      <a class="btn btn-sm btn-outline-danger" href="<?= $this->Url->build(['action' => 'downloadPdf', $row['ksef_number'], '?' => ['env' => $env]]) ?>" title="Pobierz PDF">
                        <i class="ri-file-pdf-2-line"></i>
                      </a>
                      <a class="btn btn-sm btn-outline-secondary preview-xml" href="#" data-ksef="<?= h($row['ksef_number']) ?>" data-env="<?= h($env) ?>" title="Podgląd XML">
                        <i class="ri-code-s-slash-line"></i>
                      </a>
                    </div>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>

                <!-- Księgowanie -->
                <td>
                  <?php if (!empty($row['ksef_number'])): ?>
                    <button type="button" class="btn btn-sm btn-outline-success open-booking" data-ksef="<?= h($row['ksef_number']) ?>" data-env="<?= h($env) ?>">
                      <i class="ri-book-2-line me-1"></i>Księguj
                    </button>
                    <div class="text-muted small mt-1 booking-summary" data-ksef="<?= h($row['ksef_number']) ?>" data-env="<?= h($env) ?>"></div>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="text-center py-5">
                <div class="text-muted">
                  <i class="ri-inbox-line" style="font-size:2rem"></i>
                  <div class="mt-2">Brak dokumentów dla wybranych filtrów</div>
                  <div class="small mt-1">Zmień zakres dat lub kryteria wyszukiwania</div>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php
    $qp = $this->getRequest()->getQueryParams();
    $prevUrl = $this->Url->build(['action' => 'received', '?' => array_merge($qp, ['page' => max(1, $page - 1)])]);
    $nextUrl = $this->Url->build(['action' => 'received', '?' => array_merge($qp, ['page' => $page + 1])]);
    $hasMore = !empty($apiInfo['hasMore']) || count($invoices ?? []) >= 25;
  ?>
  <div class="card-footer bg-transparent d-flex justify-content-between align-items-center py-2">
    <a class="btn btn-sm btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= $page <= 1 ? '#' : $prevUrl ?>">
      <i class="ri-arrow-left-s-line me-1"></i>Poprzednia
    </a>
    <span class="text-muted small">Strona <?= (int)$page ?></span>
    <a class="btn btn-sm btn-outline-secondary<?= !$hasMore ? ' disabled' : '' ?>" href="<?= $hasMore ? $nextUrl : '#' ?>">
      Następna<i class="ri-arrow-right-s-line ms-1"></i>
    </a>
  </div>
</div>

<?= $this->element('Ksef/xml_modal', ['env' => $env]) ?>
<?= $this->element('Ksef/booking_modal', ['env' => $env]) ?>

<input type="hidden" id="csrf-token" value="<?= h((string)($this->getRequest()->getAttribute('csrfToken') ?? '')) ?>" />

<script>
(function(){
  // ── Toast helper ──
  function showToast(message){
    try {
      let container = document.querySelector('.toast-container.received-toasts');
      if (!container){
        container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3 received-toasts';
        document.body.appendChild(container);
      }
      const el = document.createElement('div');
      el.className = 'toast align-items-center text-bg-dark border-0';
      el.setAttribute('role','status'); el.setAttribute('aria-live','polite'); el.setAttribute('aria-atomic','true');
      el.innerHTML = '<div class="d-flex">\
        <div class="toast-body">' + (message || 'Skopiowano do schowka') + '</div>\
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Zamknij"></button>\
      </div>';
      container.appendChild(el);
      if (window.bootstrap && window.bootstrap.Toast){
        const t = new bootstrap.Toast(el, { delay: 2000 });
        el.addEventListener('hidden.bs.toast', () => el.remove());
        t.show();
      } else {
        el.style.display = 'block';
        setTimeout(() => { el.remove(); }, 2000);
      }
    } catch(_) {}
  }

  function copyTextToClipboard(text){
    if (navigator.clipboard && navigator.clipboard.writeText){
      return navigator.clipboard.writeText(text);
    }
    return new Promise((resolve, reject) => {
      try {
        const ta = document.createElement('textarea');
        ta.value = text; ta.style.position='fixed'; ta.style.top='-1000px';
        document.body.appendChild(ta); ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        ok ? resolve() : reject(new Error('copy failed'));
      } catch(e){ reject(e); }
    });
  }

  // ── Copy KSeF number ──
  function initCopyIcons(){
    document.addEventListener('click', function(e){
      const a = e.target.closest('a.copy-inv, a.copy-ksef');
      if (!a) return;
      e.preventDefault();
      e.stopPropagation();
      const inv = a.getAttribute('data-inv');
      const ksef= a.getAttribute('data-ksef');
      const val = inv || ksef || '';
      if (!val) return;
      const icon = a.querySelector('i');
      const prev = icon ? icon.className : '';
      copyTextToClipboard(val).then(() => {
        if (icon){ icon.className = 'bi bi-clipboard-check'; }
        showToast(inv ? 'Skopiowano numer faktury' : 'Skopiowano numer KSeF');
        setTimeout(() => { if (icon) icon.className = prev || 'bi bi-clipboard'; }, 1500);
      }).catch(() => {
        showToast('Nie udało się skopiować');
      });
    });
  }

  // ── Booking modal ──
  function initBooking(){
    const buttons = document.querySelectorAll('.open-booking');
    if (!buttons.length) return;

    buttons.forEach(btn => {
      btn.addEventListener('click', async function(){
        const ksef = this.getAttribute('data-ksef');
        const env  = this.getAttribute('data-env') || 'prod';
        if (!window.bootstrap) return;
        const modalEl = document.getElementById('bookingModal');
        const list    = document.getElementById('booking-list');
        const info    = document.getElementById('booking-info');
        const saveBtn = document.getElementById('booking-save');
        const badgeK  = document.getElementById('booking-ksef');
        const badgeE  = document.getElementById('booking-env');
        const spinner = document.getElementById('booking-spinner');
        if (!modalEl || !list || !saveBtn) return;
        list.innerHTML = '';
        info.innerHTML = '';
        badgeK.textContent = ksef;
        badgeE.textContent = env.toUpperCase();
        spinner.classList.remove('d-none');
        spinner.style.setProperty('display', 'flex', 'important');
        const bs = new bootstrap.Modal(modalEl, {backdrop: 'static', keyboard: false});
        bs.show();

        try {
          const catsUrl = <?= json_encode($this->Url->build(['controller' => 'KsefAuthorizations','action' => 'costCategories','_full' => true])) ?>;
          const [catsResp, linesResp] = await Promise.all([
            fetch(catsUrl, { headers: { 'Accept': 'application/json' } }),
            fetch(<?= json_encode($this->Url->build(['controller' => 'KsefAuthorizations','action' => 'lines','_full' => true])) ?> + '/' + encodeURIComponent(ksef) + '?env=' + encodeURIComponent(env), { headers: { 'Accept': 'application/json' } })
          ]);
          const cats = await catsResp.json().catch(() => ({ items: [] }));
          const resp = linesResp;
          const data = await resp.json();
          spinner.style.setProperty('display', 'none', 'important');
          if (!resp.ok) {
            info.innerHTML = '<div class="alert alert-danger">Nie udało się pobrać pozycji: ' + (data && data.error ? data.error : ('HTTP ' + resp.status)) + '</div>';
            return;
          }

          // Populate default cost select
          const defSel = document.getElementById('booking-default-cost');
          if (defSel && cats && Array.isArray(cats.items)) {
            defSel.innerHTML = '<option value="">— wybierz —</option>';
            cats.items.forEach(it => {
              const opt = document.createElement('option');
              opt.value = it.code || it.name || '';
              opt.textContent = it.name || it.code || '';
              defSel.appendChild(opt);
            });
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
              const $def = window.jQuery(defSel);
              $def.addClass('select2-selection--sm');
              $def.select2({ width: '16rem', placeholder: '— wybierz —', allowClear: true });
            }
          }

          const items = Array.isArray(data.items) ? data.items : [];
          if (!items.length) {
            info.innerHTML = '<div class="alert alert-warning">Brak pozycji do wyświetlenia.</div>';
          }

          const fmt = (v) => {
            if (typeof v === 'number' && !isNaN(v)) return v.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (typeof v === 'string' && v !== '' && !isNaN(parseFloat(v))) return Number(v).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            return v ?? '';
          };

          // Render list
          items.forEach((it, idx) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex align-items-start justify-content-between flex-wrap gap-2';
            const left = document.createElement('div');
            left.className = 'flex-grow-1';
            const cb = document.createElement('input');
            cb.type = 'checkbox'; cb.className = 'form-check-input me-2 booking-cb'; cb.checked = true; cb.dataset.index = (it.index ?? idx);
            const title = document.createElement('div');
            title.className = 'fw-semibold d-flex align-items-center gap-2';
            const titleIcon = document.createElement('i'); titleIcon.className = 'bi bi-receipt';
            const titleText = document.createTextNode(it.name || it.description || 'Pozycja');
            title.appendChild(titleIcon); title.appendChild(titleText);

            const qty = it.quantity ?? '';
            const unit = it.unit ?? '';
            const price = it.unit_price ?? '';
            const net = it.net_amount ?? '';
            const vatRate = it.vat_rate ?? '';
            const vatAmt = it.vat_amount ?? '';
            const gross = it.gross_amount ?? '';
            const currency = it.currency ?? '';
            const lineId = it.line_id || it.index || '';

            const detailsGrid = document.createElement('div');
            detailsGrid.className = 'kv-grid';
            const addKV = (label, iconClass, value, titleAttr, isNumber) => {
              if (value === null || value === undefined || value === '') return;
              const span = document.createElement('span'); span.className = 'kv-pair';
              if (titleAttr) span.title = titleAttr;
              const lab = document.createElement('span'); lab.className = 'text-muted';
              if (iconClass) { const ic = document.createElement('i'); ic.className = 'bi ' + iconClass + ' me-1'; lab.appendChild(ic); }
              lab.appendChild(document.createTextNode(label + ':'));
              const val = document.createElement('span'); val.className = 'fw-semibold' + (isNumber ? ' kv-value-number' : '');
              val.textContent = value;
              span.appendChild(lab); span.appendChild(val);
              detailsGrid.appendChild(span);
            };
            if (qty !== '' || unit) { addKV('Ilość', 'bi-calculator', (qty !== '' ? String(qty) : '') + (unit ? (' ' + unit) : ''), 'FA: P_8A/P_8B', false); }
            addKV('Cena netto', 'bi-cash-coin', (price !== '' ? (fmt(price) + (currency ? ' ' + currency : '')) : ''), 'FA: P_9A', true);
            addKV('Wartość netto', 'bi-cash-coin', (net !== '' ? (fmt(net) + (currency ? ' ' + currency : '')) : ''), 'FA: P_11', true);
            addKV('Stawka VAT', 'bi-percent', (vatRate || ''), 'FA: P_12', false);
            addKV('Kwota VAT', 'bi-cash-coin', (vatAmt !== '' ? (fmt(vatAmt) + (currency ? ' ' + currency : '')) : ''), 'FA: (KwotaVat)', true);
            addKV('Brutto', 'bi-cash-coin', (gross !== '' ? (fmt(gross) + (currency ? ' ' + currency : '')) : ''), 'FA: (WartoscBrutto)', true);
            addKV('Nr wiersza', 'bi-hash', (lineId || ''), 'FA: NrWierszaFa');

            // Header badges + toggle
            const hdrBar = document.createElement('div');
            hdrBar.className = 'small text-muted d-flex align-items-center gap-2 mt-1 item-header';
            if (gross !== ''){
              const bg = document.createElement('span'); bg.className = 'badge bg-secondary-subtle border text-secondary';
              const lab = document.createElement('span'); lab.className = 'text-muted'; lab.textContent = 'Brutto:';
              const val = document.createElement('span'); val.className = 'badge-num ms-1'; val.textContent = fmt(gross);
              bg.appendChild(lab); bg.appendChild(val);
              if (currency) { const cur = document.createElement('span'); cur.className = 'ms-1'; cur.textContent = currency; bg.appendChild(cur); }
              hdrBar.appendChild(bg);
            }
            if (vatRate){ const bv = document.createElement('span'); bv.className = 'badge bg-secondary-subtle border text-secondary'; bv.textContent = 'VAT ' + vatRate; hdrBar.appendChild(bv); }
            const togg = document.createElement('button');
            togg.type = 'button'; togg.className = 'btn btn-link btn-sm p-0 ms-1 toggle-details';
            togg.setAttribute('aria-expanded', 'true'); togg.title = 'Szczegóły';
            const toggIcon = document.createElement('i'); toggIcon.className = 'bi bi-chevron-up';
            togg.appendChild(toggIcon);
            togg.addEventListener('click', () => {
              li.classList.toggle('open');
              const expanded = li.classList.contains('open');
              togg.setAttribute('aria-expanded', expanded ? 'true' : 'false');
              if (expanded) details.classList.remove('d-none');
              toggIcon.className = expanded ? 'bi bi-chevron-up me-1' : 'bi bi-chevron-down me-1';
            });
            hdrBar.appendChild(togg);
            const rm = document.createElement('button');
            rm.type = 'button'; rm.className = 'btn btn-link btn-sm text-danger p-0 ms-3'; rm.textContent = 'Nie księguj tej pozycji';
            rm.addEventListener('click', () => { cb.checked = false; li.classList.add('opacity-50'); updateCounter(); });
            hdrBar.appendChild(rm);
            left.appendChild(title); left.appendChild(hdrBar);
            const details = document.createElement('div'); details.className = 'item-details w-100 mt-2';
            details.appendChild(detailsGrid);

            const right = document.createElement('div'); right.className = 'd-flex align-items-center gap-2 flex-wrap';
            const costWrap = document.createElement('div'); costWrap.className = 'd-flex flex-column';
            const costLbl = document.createElement('label'); costLbl.className = 'form-label small mb-1'; costLbl.textContent = 'Rodzaj kosztu';
            const costSel = document.createElement('select'); costSel.className = 'form-select form-select-sm booking-cost-type';
            const opts = (cats && Array.isArray(cats.items)) ? cats.items : [];
            costSel.innerHTML = '<option value="">— wybierz —</option>';
            opts.forEach(o => { const opt = document.createElement('option'); opt.value = o.code || o.name || ''; opt.textContent = o.name || o.code || ''; costSel.appendChild(opt); });
            if (it.cost_type) costSel.value = it.cost_type;
            costWrap.appendChild(costLbl); costWrap.appendChild(costSel);
            right.appendChild(costWrap);
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
              setTimeout(() => { const $sel = window.jQuery(costSel); $sel.addClass('select2-selection--sm'); $sel.select2({ width: '16rem', placeholder: '— wybierz —', allowClear: true }); }, 0);
            }

            const noteRow = document.createElement('div'); noteRow.className = 'mt-2 w-100';
            const noteLbl = document.createElement('label'); noteLbl.className = 'form-label small mb-1 d-block'; noteLbl.textContent = 'Uwagi';
            const noteInp = document.createElement('input'); noteInp.type = 'text'; noteInp.className = 'form-control form-control-sm booking-note';
            if (it.note) noteInp.value = it.note;
            if (it.saved) { const saved = document.createElement('span'); saved.className = 'badge bg-success-subtle border text-success'; saved.textContent = 'Dodane do księgowania'; right.appendChild(saved); }
            li.prepend(cb); li.appendChild(left); li.appendChild(right);
            li.dataset.payload = JSON.stringify(it);
            noteRow.appendChild(noteLbl); noteRow.appendChild(noteInp);
            details.appendChild(noteRow); left.appendChild(details);
            li.classList.add('open');
            list.appendChild(li);
          });

          // ── Toolbar logic ──
          const counterEl = document.getElementById('booking-counter');
          function updateCounter(){
            const all = list.querySelectorAll('li').length;
            const sel = list.querySelectorAll('input.booking-cb:checked').length;
            if (counterEl) counterEl.textContent = 'Wybrane: ' + sel + ' / ' + all;
          }
          list.addEventListener('change', (e) => {
            if (e.target && e.target.classList && e.target.classList.contains('booking-cb')) {
              const li = e.target.closest('li');
              if (li) li.classList.toggle('opacity-50', !e.target.checked);
              updateCounter();
            }
          });
          const btnAll = document.getElementById('booking-select-all');
          const btnNone = document.getElementById('booking-unselect-all');
          if (btnAll) btnAll.onclick = function(){ list.querySelectorAll('input.booking-cb').forEach(cb => { cb.checked = true; cb.closest('li')?.classList.remove('opacity-50'); }); updateCounter(); };
          if (btnNone) btnNone.onclick = function(){ list.querySelectorAll('input.booking-cb').forEach(cb => { cb.checked = false; cb.closest('li')?.classList.add('opacity-50'); }); updateCounter(); };
          const search = document.getElementById('booking-search');
          if (search) {
            search.oninput = function(){ const q = (this.value || '').toLowerCase().trim(); list.querySelectorAll('li').forEach(li => { li.style.display = q === '' || li.innerText.toLowerCase().includes(q) ? '' : 'none'; }); };
          }
          updateCounter();

          // ── Compact mode ──
          const compactBtn = document.getElementById('booking-toggle-compact');
          const expandAllBtn = document.getElementById('booking-expand-all');
          const collapseAllBtn = document.getElementById('booking-collapse-all');
          const COMPACT_PREF_KEY = 'ksef_booking_compact_mode';
          let isCompact = false;

          function updateAllTogglesIcon(){
            list.querySelectorAll('.toggle-details').forEach(btn => {
              const li = btn.closest('li'); const icon = btn.querySelector('i.bi');
              if (!icon || !li) return;
              icon.className = li.classList.contains('open') ? 'bi bi-chevron-up me-1' : 'bi bi-chevron-down me-1';
              btn.setAttribute('aria-expanded', li.classList.contains('open') ? 'true' : 'false');
            });
          }

          function setCompactMode(flag){
            isCompact = !!flag;
            list.classList.toggle('compact', isCompact);
            list.querySelectorAll('li').forEach(li => { if (isCompact) li.classList.remove('open'); else li.classList.add('open'); });
            if (compactBtn) compactBtn.textContent = isCompact ? 'Widok: szczegółowy' : 'Widok: kompaktowy';
            try { localStorage.setItem(COMPACT_PREF_KEY, isCompact ? 'compact' : 'detailed'); } catch {}
            updateAllTogglesIcon();
          }

          (function initCompact(){
            let pref = null; try { pref = localStorage.getItem(COMPACT_PREF_KEY); } catch {}
            if (pref === 'compact') setCompactMode(true);
            else if (pref === 'detailed') setCompactMode(false);
            else setCompactMode(items.length > 30);
          })();

          if (compactBtn) compactBtn.onclick = function(){ setCompactMode(!isCompact); };
          if (expandAllBtn) expandAllBtn.onclick = function(){ setCompactMode(false); };
          if (collapseAllBtn) collapseAllBtn.onclick = function(){ setCompactMode(true); };
          updateAllTogglesIcon();

          // ── Recent categories ──
          const recentBox = document.getElementById('booking-recent');
          const RECENT_KEY = 'ksef_recent_cost_categories';
          function getRecent(){ try { return JSON.parse(localStorage.getItem(RECENT_KEY)||'[]')||[]; } catch { return []; } }
          function pushRecent(val){ if(!val) return; let arr=getRecent(); arr=arr.filter(v=>v!==val); arr.unshift(val); if(arr.length>6) arr=arr.slice(0,6); localStorage.setItem(RECENT_KEY, JSON.stringify(arr)); renderRecent(arr); }
          function renderRecent(arr){
            if(!recentBox) return; recentBox.innerHTML='';
            if(!arr||!arr.length) return;
            const lbl=document.createElement('div'); lbl.className='text-muted small'; lbl.textContent='Ostatnio wybierane:'; recentBox.appendChild(lbl);
            arr.forEach(v=>{ const chip=document.createElement('span'); chip.className='badge-chip'; chip.textContent=v;
              chip.onclick=()=>{
                const active = document.activeElement && document.activeElement.tagName==='SELECT' && document.activeElement.classList.contains('booking-cost-type') ? document.activeElement : null;
                if (active) { active.value = v; if (window.jQuery?.fn?.select2 && window.jQuery(active).data('select2')) window.jQuery(active).val(v).trigger('change'); }
                else if (defSel) { defSel.value = v; if (window.jQuery?.fn?.select2 && window.jQuery(defSel).data('select2')) window.jQuery(defSel).val(v).trigger('change'); }
              };
              recentBox.appendChild(chip);
            });
          }
          renderRecent(getRecent());

          // ── Apply default cost ──
          const applyAllBtn = document.getElementById('booking-apply-all');
          if (applyAllBtn) {
            applyAllBtn.onclick = function(){
              const defSel2 = document.getElementById('booking-default-cost');
              const val = defSel2 ? defSel2.value : '';
              if (!val) return;
              list.querySelectorAll('select.booking-cost-type').forEach(s => { s.value = val; });
              if (window.jQuery && window.jQuery.fn) {
                list.querySelectorAll('select.booking-cost-type').forEach(s => { const $s = window.jQuery(s); if ($s.data('select2')) $s.val(val).trigger('change'); });
              }
            };
          }

          // ── Save handler ──
          saveBtn.onclick = async function(){
            list.querySelectorAll('select.booking-cost-type').forEach(s => s.classList.remove('is-invalid'));
            const selectedLis = Array.from(list.querySelectorAll('li')).filter(li => li.querySelector('.booking-cb')?.checked);
            let invalid = false;
            selectedLis.forEach(li => { const sel = li.querySelector('select.booking-cost-type'); if (sel && !sel.value) { sel.classList.add('is-invalid'); invalid = true; } });
            if (invalid) { info.innerHTML = '<div class="alert alert-danger">Wybierz kategorię kosztu dla wszystkich zaznaczonych pozycji.</div>'; return; }

            const selected = selectedLis.map(li => {
              let obj; try { obj = JSON.parse(li.dataset.payload || '{}'); } catch { obj = {}; }
              const sel = li.querySelector('.booking-cost-type'); if (sel) obj.cost_type = sel.value || '';
              const n = li.querySelector('.booking-note'); if (n) obj.note = n.value || '';
              return obj;
            }).filter(x => x && Object.keys(x).length);
            try { const uniq = Array.from(new Set(selected.map(o => o.cost_type).filter(Boolean))); uniq.forEach(pushRecent); } catch {}

            const postUrl = <?= json_encode($this->Url->build(['controller' => 'KsefAuthorizations','action' => 'saveBookingItems','_full' => true])) ?>;
            const csrf = document.getElementById('csrf-token')?.value || '';
            const resp2 = await fetch(postUrl, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
              body: JSON.stringify({ env: env, ksef_number: ksef, items: selected })
            });
            const out = await resp2.json().catch(() => null);
            if (!resp2.ok || !out || out.success === false) {
              info.innerHTML = '<div class="alert alert-danger">Błąd zapisu pozycji: ' + (out && out.error ? out.error : ('HTTP ' + resp2.status)) + '</div>';
              return;
            }
            info.innerHTML = '<div class="alert alert-success">Zapisano ' + (out.count ?? selected.length) + ' pozycji do księgowania.</div>';
            try { loadSummaries(); } catch(_) {}
            setTimeout(() => { bs.hide(); }, 800);
          };
        } catch (e) {
          spinner.style.setProperty('display', 'none', 'important');
          info.innerHTML = '<div class="alert alert-danger">Błąd: ' + (e && e.message ? e.message : e) + '</div>';
        }
      });
    });
  }

  // ── Booking summaries ──
  function loadSummaries(){
    const nodes = document.querySelectorAll('.booking-summary');
    nodes.forEach(async (el) => {
      const ksef = el.getAttribute('data-ksef');
      const env = el.getAttribute('data-env') || 'prod';
      const url = <?= json_encode($this->Url->build(['controller' => 'KsefAuthorizations','action' => 'bookingSummary','_full' => true])) ?> + '/' + encodeURIComponent(ksef) + '?env=' + encodeURIComponent(env);
      try {
        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const j = await r.json();
        if (r.ok && j && typeof j.count !== 'undefined' && j.count > 0) {
          el.innerHTML = '<span class="badge bg-success-subtle text-success border"><i class="bi bi-check-circle me-1"></i>' + j.count + ' poz.</span>';
        }
      } catch(e) {}
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ initCopyIcons(); initBooking(); loadSummaries(); });
  } else {
    initCopyIcons(); initBooking(); loadSummaries();
  }
})();
</script>

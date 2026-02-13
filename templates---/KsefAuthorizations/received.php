<?php
/**
 * @var \App\View\AppView $this
 * @var array $invoices
 * @var array $stats
 */
$this->assign('title', 'Faktury otrzymane (KSeF)');
$env = (string)$this->getRequest()->getQuery('env', 'test');
?>

<?= $this->element('Ksef/filters', [
  'currentAction' => 'received',
  'peerAction' => 'issued',
  'storageKey' => 'ksef_filters_received',
]) ?>

<?= $this->element('Ksef/info') ?>
<?= $this->element('Ksef/legend') ?>

<!-- INFO: połączenie z KSeF jest konfigurowane certyfikatem/tokenem w ustawieniach integracji. -->

<div class="table-responsive">
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>Data</th>
        <th>Numer</th>
        <th>Kontrahent</th>
        <th>NIP</th>
        <th class="text-end">Kwota brutto</th>
        <th>Waluta</th>
        <th>Status</th>
  <th>Plik</th>
  <th>Księgowanie</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($invoices)): ?>
        <?php foreach ($invoices as $row): ?>
          <tr>
            <td><?= $row['date']?->i18nFormat('yyyy-MM-dd') ?? '' ?></td>
            <td>
              <div><?= h($row['fullnumber'] ?? '') ?></div>
              
              <?php if (!empty($row['ksef_number'])): ?>
                <div class="small text-muted mt-1 d-flex align-items-center gap-2">
                  <span class="badge bg-secondary-subtle border text-secondary">KSeF: <?= h($row['ksef_number']) ?></span>
                  <a href="#" class="copy-ksef icon-clip" data-ksef="<?= h($row['ksef_number']) ?>" aria-label="Kopiuj numer KSeF" title="Kopiuj numer KSeF">
                    <i class="bi bi-clipboard"></i>
                  </a>
                </div>
              <?php endif; ?>
            </td>
            <td><?= h($row['InvoiceContractors']['name'] ?? '') ?></td>
            <td><?= h($row['InvoiceContractors']['tax_id'] ?? '') ?></td>
            <td class="text-end"><?= number_format((float)($row['total'] ?? 0), 2, ',', ' ') ?></td>
            <td><?= h($row['currency'] ?? 'PLN') ?></td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  <?php if (!empty($row['invoiceType'])): ?>
                    <span class="badge bg-secondary-subtle border text-secondary"><?= h($row['invoiceType']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($row['invoicingMode'])): ?>
                    <span class="badge bg-secondary-subtle border text-secondary"><?= h($row['invoicingMode']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($row['isSelfInvoicing'])): ?>
                    <span class="badge bg-info-subtle border text-info">Self-billing</span>
                  <?php endif; ?>
                  <?php if (!empty($row['hasAttachment'])): ?>
                    <span class="badge bg-secondary-subtle border text-secondary" title="Załączniki">📎</span>
                  <?php endif; ?>
                </div>
              </td>
            <td>
              <?php if (!empty($row['ksef_number'])): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= $this->Url->build(['action' => 'download', $row['ksef_number'], '?' => ['env' => $env]]) ?>">Pobierz XML</a>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($row['ksef_number'])): ?>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <button type="button" class="btn btn-sm btn-outline-success open-booking" data-ksef="<?= h($row['ksef_number']) ?>" data-env="<?= h($env) ?>">
                    Wybierz pozycje
                  </button>
                  <span class="text-muted small booking-summary" data-ksef="<?= h($row['ksef_number']) ?>" data-env="<?= h($env) ?>">—</span>
                </div>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="9" class="text-center text-muted py-4">Brak wyników dla wybranych filtrów.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

<?php
$page = max(1, (int)$this->getRequest()->getQuery('page', 1));
$prevUrl = $this->Url->build(['action' => 'received'] + array_merge($this->getRequest()->getQueryParams(), ['page' => max(1, $page - 1)]));
$nextUrl = $this->Url->build(['action' => 'received'] + array_merge($this->getRequest()->getQueryParams(), ['page' => $page + 1]));
?>
<div class="d-flex justify-content-between my-3">
  <a class="btn btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= $page <= 1 ? '#' : $prevUrl ?>">&laquo; Poprzednia</a>
  <span class="text-muted">Strona <?= (int)$page ?></span>
  <a class="btn btn-outline-secondary" href="<?= $nextUrl ?>">Następna &raquo;</a>
</div>

<?= $this->element('Ksef/xml_modal', ['env' => $env]) ?>
<?= $this->element('Ksef/booking_modal', ['env' => $env]) ?>

<input type="hidden" id="csrf-token" value="<?= h((string)($this->getRequest()->getAttribute('csrfToken') ?? '')) ?>" />

<script>
(function(){
  // Lightweight toast helper using Bootstrap Toast when available
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
        // Fallback minimal behavior
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
      const icon = a.querySelector('i.bi');
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

  function initBooking(){
    const buttons = document.querySelectorAll('.open-booking');
    if (!buttons.length) return;

    buttons.forEach(btn => {
      btn.addEventListener('click', async function(){
        const ksef = this.getAttribute('data-ksef');
        const env  = this.getAttribute('data-env') || 'test';
        if (!window.bootstrap) return;
        const modalEl = document.getElementById('bookingModal');
        const title   = document.getElementById('bookingModalLabel');
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
  // Force visible without flicker
  spinner.classList.remove('d-none');
  spinner.style.setProperty('display', 'flex', 'important');
        const bs = new bootstrap.Modal(modalEl, {backdrop: 'static', keyboard: false});
        bs.show();

        try {
          // Load categories first
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
            // Enhance default with Select2 if available
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
          // Render list with checkboxes, per-item details (collapsible), cost-type selector, and quick remove
          items.forEach((it, idx) => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex align-items-start justify-content-between flex-wrap gap-2';
            const left = document.createElement('div');
            left.className = 'flex-grow-1';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'form-check-input me-2 booking-cb';
            cb.checked = true;
            cb.dataset.index = (it.index ?? idx);
            // Summary text
            const title = document.createElement('div');
            title.className = 'fw-semibold d-flex align-items-center gap-2';
            const titleIcon = document.createElement('i');
            titleIcon.className = 'bi bi-receipt';
            const titleText = document.createTextNode(it.name || it.description || 'Pozycja');
            title.appendChild(titleIcon);
            title.appendChild(titleText);
            const small = document.createElement('div');
            small.className = 'small text-muted mt-1';
            const qty = (typeof it.quantity === 'number') ? it.quantity : (it.quantity ?? '');
            const unit = it.unit ?? '';
            const price = (typeof it.unit_price === 'number') ? it.unit_price : (it.unit_price ?? '');
            const net = (typeof it.net_amount === 'number') ? it.net_amount : (it.net_amount ?? '');
            const vatRate = (it.vat_rate ?? '');
            const vatAmt = (typeof it.vat_amount === 'number') ? it.vat_amount : (it.vat_amount ?? '');
            const gross = (typeof it.gross_amount === 'number') ? it.gross_amount : (it.gross_amount ?? '');
            const currency = (it.currency ?? '');
            const lineId = (it.line_id || it.index || '');
            const fmt = (v) => {
              if (typeof v === 'number' && !isNaN(v)) return v.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
              if (typeof v === 'string' && v !== '' && !isNaN(parseFloat(v))) return Number(v).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
              return v ?? '';
            };
            const detailsGrid = document.createElement('div');
            detailsGrid.className = 'kv-grid';
            const addKV = (label, iconClass, value, titleAttr, isNumber) => {
              if (value === null || value === undefined || value === '') return;
              const span = document.createElement('span');
              span.className = 'kv-pair';
              if (titleAttr) span.title = titleAttr;
              const lab = document.createElement('span');
              lab.className = 'text-muted';
              if (iconClass) {
                const ic = document.createElement('i'); ic.className = 'bi ' + iconClass + ' me-1'; lab.appendChild(ic);
              }
              lab.appendChild(document.createTextNode(label + ':'));
              const val = document.createElement('span');
              val.className = 'fw-semibold' + (isNumber ? ' kv-value-number' : '');
              val.textContent = value;
              span.appendChild(lab); span.appendChild(val);
              detailsGrid.appendChild(span);
            };
            // Row of clearly labeled values so it's obvious what's what
            if (qty !== '' || unit) {
              const qtyText = (qty !== '' ? String(qty) : '') + (unit ? (' ' + unit) : '');
              addKV('Ilość', 'bi-calculator', qtyText, 'FA: P_8A/P_8B', false);
            }
            addKV('Cena netto', 'bi-cash-coin', (price !== '' ? (fmt(price) + (currency ? ' ' + currency : '')) : ''), 'FA: P_9A', true);
            addKV('Wartość netto', 'bi-cash-coin', (net !== '' ? (fmt(net) + (currency ? ' ' + currency : '')) : ''), 'FA: P_11', true);
            addKV('Stawka VAT', 'bi-percent', (vatRate || ''), 'FA: P_12', false);
            addKV('Kwota VAT', 'bi-cash-coin', (vatAmt !== '' ? (fmt(vatAmt) + (currency ? ' ' + currency : '')) : ''), 'FA: (KwotaVat)', true);
            addKV('Brutto', 'bi-cash-coin', (gross !== '' ? (fmt(gross) + (currency ? ' ' + currency : '')) : ''), 'FA: (WartoscBrutto)', true);
            if (!currency && (price || net || gross || vatAmt)) {
              // If we didn't add currency within values, show it explicitly
              addKV('Waluta', 'bi-currency-exchange', (it.currency || 'PLN'));
            }
            addKV('Nr wiersza', 'bi-hash', (lineId || ''), 'FA: NrWierszaFa');
            // Header summary badges and toggle
            const hdrBar = document.createElement('div');
            hdrBar.className = 'small text-muted d-flex align-items-center gap-2 mt-1 item-header';
            if (gross !== ''){
              const bg = document.createElement('span');
              bg.className = 'badge bg-secondary-subtle border text-secondary';
              const lab = document.createElement('span'); lab.className = 'text-muted'; lab.textContent = 'Brutto:';
              const val = document.createElement('span'); val.className = 'badge-num ms-1'; val.textContent = fmt(gross);
              bg.appendChild(lab);
              bg.appendChild(val);
              if (currency) { const cur = document.createElement('span'); cur.className = 'ms-1'; cur.textContent = currency; bg.appendChild(cur); }
              hdrBar.appendChild(bg);
            }
            if (vatRate){
              const bv = document.createElement('span');
              bv.className = 'badge bg-secondary-subtle border text-secondary';
              bv.textContent = 'VAT ' + vatRate;
              hdrBar.appendChild(bv);
            }
            const togg = document.createElement('button');
            togg.type = 'button';
            togg.className = 'btn btn-link btn-sm p-0 ms-1 toggle-details';
            togg.setAttribute('aria-expanded', 'true');
            togg.setAttribute('aria-label', 'Szczegóły');
            togg.title = 'Szczegóły';
            const toggIcon = document.createElement('i');
            toggIcon.className = 'bi bi-chevron-up';
            togg.appendChild(toggIcon);
            togg.addEventListener('click', () => {
              li.classList.toggle('open');
              const expanded = li.classList.contains('open');
              togg.setAttribute('aria-expanded', expanded ? 'true' : 'false');
              // If in compact mode, ensure this item's details show when opened
              if (expanded) { details.classList.remove('d-none'); }
              // update chevron icon
              toggIcon.className = expanded ? 'bi bi-chevron-up me-1' : 'bi bi-chevron-down me-1';
            });
            hdrBar.appendChild(togg);
            // "Nie księguj tej pozycji" przeniesione do nagłówka jako link
            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn btn-link btn-sm text-danger p-0 ms-3';
            rm.textContent = 'Nie księguj tej pozycji';
            rm.title = 'Wyłącz pozycję z księgowania';
            rm.addEventListener('click', () => { cb.checked = false; li.classList.add('opacity-50'); updateCounter(); });
            hdrBar.appendChild(rm);
            left.appendChild(title);
            left.appendChild(hdrBar);
            const details = document.createElement('div');
            details.className = 'item-details w-100 mt-2';
            details.appendChild(detailsGrid);
            left.appendChild(details);
            const right = document.createElement('div');
            right.className = 'd-flex align-items-center gap-2 flex-wrap';
            // Cost type selector
            const costWrap = document.createElement('div');
            costWrap.className = 'd-flex flex-column';
            const costLbl = document.createElement('label');
            costLbl.className = 'form-label small mb-1';
            costLbl.textContent = 'Rodzaj kosztu';
            const costSel = document.createElement('select');
            costSel.className = 'form-select form-select-sm booking-cost-type';
            // Options from categories
            const opts = (cats && Array.isArray(cats.items)) ? cats.items : [];
            const buildOptions = () => {
              costSel.innerHTML = '';
              const opt0 = document.createElement('option');
              opt0.value = '';
              opt0.textContent = '— wybierz —';
              costSel.appendChild(opt0);
              opts.forEach(o => {
                const opt = document.createElement('option');
                opt.value = o.code || o.name || '';
                opt.textContent = o.name || o.code || '';
                costSel.appendChild(opt);
              });
            };
            buildOptions();
            if (it.cost_type) {
              costSel.value = it.cost_type;
            }
            costWrap.appendChild(costLbl);
            costWrap.appendChild(costSel);
            right.appendChild(costWrap);
            // Select2 enhance when available
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
              setTimeout(() => {
                const $sel = window.jQuery(costSel);
                $sel.addClass('select2-selection--sm');
                $sel.select2({ width: '16rem', placeholder: '— wybierz —', allowClear: true });
              }, 0);
            }
            // Note input – full width row under the entire item, inside details
            const noteRow = document.createElement('div');
            noteRow.className = 'mt-2 w-100';
            const noteLbl = document.createElement('label');
            noteLbl.className = 'form-label small mb-1 d-block';
            noteLbl.textContent = 'Uwagi';
            const noteInp = document.createElement('input');
            noteInp.type = 'text';
            noteInp.className = 'form-control form-control-sm booking-note';
            if (it.note) noteInp.value = it.note;
            if (it.saved) {
              const saved = document.createElement('span');
              saved.className = 'badge bg-success-subtle border text-success';
              saved.textContent = 'Dodane do księgowania';
              right.appendChild(saved);
            }
            // usunięto przycisk z sekcji prawej – przeniesiony do nagłówka
            li.prepend(cb);
            li.appendChild(left);
            li.appendChild(right);
            // attach serialized payload for save
            li.dataset.payload = JSON.stringify(it);
            // append full-width note row at the bottom of the li
            noteRow.appendChild(noteLbl);
            noteRow.appendChild(noteInp);
            details.appendChild(noteRow);
            // open by default
            li.classList.add('open');
            list.appendChild(li);
          });

          // Toolbar logic: counter, select-all, unselect-all, search filter
          const counterEl = document.getElementById('booking-counter');
          function updateCounter(){
            const all = list.querySelectorAll('li').length;
            const sel = list.querySelectorAll('input.booking-cb:checked').length;
            if (counterEl) counterEl.textContent = `Wybrane: ${sel} / ${all}`;
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
          if (btnAll) btnAll.onclick = function(){
            list.querySelectorAll('input.booking-cb').forEach(cb => { cb.checked = true; cb.closest('li')?.classList.remove('opacity-50'); });
            updateCounter();
          };
          if (btnNone) btnNone.onclick = function(){
            list.querySelectorAll('input.booking-cb').forEach(cb => { cb.checked = false; cb.closest('li')?.classList.add('opacity-50'); });
            updateCounter();
          };
          const search = document.getElementById('booking-search');
          if (search) {
            search.oninput = function(){
              const q = (this.value || '').toLowerCase().trim();
              list.querySelectorAll('li').forEach(li => {
                const text = li.innerText.toLowerCase();
                li.style.display = q === '' || text.includes(q) ? '' : 'none';
              });
            };
          }
          updateCounter();

          // Global compact view toggle + expand/collapse all + auto-compact + persistence
          const compactBtn = document.getElementById('booking-toggle-compact');
          const expandAllBtn = document.getElementById('booking-expand-all');
          const collapseAllBtn = document.getElementById('booking-collapse-all');
          const COMPACT_PREF_KEY = 'ksef_booking_compact_mode'; // 'compact' | 'detailed'
          const AUTO_COMPACT_THRESHOLD = 30;
          let isCompact = false;

          function updateAllTogglesIcon(){
            list.querySelectorAll('.toggle-details').forEach(btn => {
              const li = btn.closest('li');
              const icon = btn.querySelector('i.bi');
              if (!icon || !li) return;
              const expanded = li.classList.contains('open');
              icon.className = expanded ? 'bi bi-chevron-up me-1' : 'bi bi-chevron-down me-1';
              btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
          }

          function setCompactMode(flag){
            isCompact = !!flag;
            list.classList.toggle('compact', isCompact);
            // synchronize open state for all items for clarity
            list.querySelectorAll('li').forEach(li => {
              if (isCompact) li.classList.remove('open'); else li.classList.add('open');
            });
            if (compactBtn) compactBtn.textContent = isCompact ? 'Widok: szczegółowy' : 'Widok: kompaktowy';
            try { localStorage.setItem(COMPACT_PREF_KEY, isCompact ? 'compact' : 'detailed'); } catch {}
            updateAllTogglesIcon();
          }

          // Apply initial mode: prefer stored pref, else auto-compact if many items
          (function initCompact(){
            let pref = null; try { pref = localStorage.getItem(COMPACT_PREF_KEY); } catch {}
            if (pref === 'compact') setCompactMode(true);
            else if (pref === 'detailed') setCompactMode(false);
            else setCompactMode(items.length > AUTO_COMPACT_THRESHOLD);
          })();

          if (compactBtn) {
            compactBtn.onclick = function(){ setCompactMode(!isCompact); };
          }
          if (expandAllBtn) {
            expandAllBtn.onclick = function(){ setCompactMode(false); };
          }
          if (collapseAllBtn) {
            collapseAllBtn.onclick = function(){ setCompactMode(true); };
          }
          // Initial chevron state
          updateAllTogglesIcon();

          // Recent categories from localStorage (up to 6)
          const recentBox = document.getElementById('booking-recent');
          const RECENT_KEY = 'ksef_recent_cost_categories';
          function getRecent(){ try { return JSON.parse(localStorage.getItem(RECENT_KEY)||'[]')||[]; } catch { return []; } }
          function pushRecent(val){ if(!val) return; let arr=getRecent(); arr=arr.filter(v=>v!==val); arr.unshift(val); if(arr.length>6) arr=arr.slice(0,6); localStorage.setItem(RECENT_KEY, JSON.stringify(arr)); renderRecent(arr); }
          function renderRecent(arr){ if(!recentBox) return; recentBox.innerHTML=''; if(!arr||!arr.length) return; const lbl=document.createElement('div'); lbl.className='text-muted small'; lbl.textContent='Ostatnio wybierane:'; recentBox.appendChild(lbl); arr.forEach(v=>{ const chip=document.createElement('span'); chip.className='badge-chip'; chip.textContent=v; chip.onclick=()=>{ // apply to focused select if any, else to default
              const active = document.activeElement && document.activeElement.tagName==='SELECT' && document.activeElement.classList.contains('booking-cost-type') ? document.activeElement : null;
              if (active) { active.value = v; if (window.jQuery && window.jQuery.fn && window.jQuery(active).data('select2')) { window.jQuery(active).val(v).trigger('change'); } }
              else { if (defSel){ defSel.value = v; if (window.jQuery && window.jQuery.fn && window.jQuery(defSel).data('select2')) { window.jQuery(defSel).val(v).trigger('change'); } } }
            }; recentBox.appendChild(chip); }); }
          renderRecent(getRecent());

          // Apply default cost to all lines
          const applyAllBtn = document.getElementById('booking-apply-all');
          if (applyAllBtn) {
            applyAllBtn.onclick = function(){
              const defSel2 = document.getElementById('booking-default-cost');
              const val = defSel2 ? defSel2.value : '';
              if (!val) return;
              list.querySelectorAll('select.booking-cost-type').forEach(s => { s.value = val; });
              if (window.jQuery && window.jQuery.fn) {
                list.querySelectorAll('select.booking-cost-type').forEach(s => {
                  const $s = window.jQuery(s);
                  if ($s.data('select2')) $s.val(val).trigger('change');
                });
              }
            };
          }

          // Save handler
          saveBtn.onclick = async function(){
            // Clear previous validation styling
            list.querySelectorAll('select.booking-cost-type').forEach(s => s.classList.remove('is-invalid'));

            const selectedLis = Array.from(list.querySelectorAll('li')).filter(li => li.querySelector('.booking-cb')?.checked);
            // Validate: require cost_type for each selected item
            let invalid = false;
            selectedLis.forEach(li => {
              const sel = li.querySelector('select.booking-cost-type');
              if (sel && !sel.value) {
                sel.classList.add('is-invalid');
                invalid = true;
              }
            });
            if (invalid) {
              info.innerHTML = '<div class="alert alert-danger">Wybierz kategorię kosztu dla wszystkich zaznaczonych pozycji.</div>';
              return;
            }

            const selected = selectedLis.map(li => {
              let obj; try { obj = JSON.parse(li.dataset.payload || '{}'); } catch { obj = {}; }
              const sel = li.querySelector('.booking-cost-type');
              if (sel) { obj.cost_type = sel.value || ''; }
              const n = li.querySelector('.booking-note');
              if (n) { obj.note = n.value || ''; }
              return obj;
            }).filter(x => x && Object.keys(x).length);
            // Save selected categories to recent
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
            // Refresh row summaries after save
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
  function loadSummaries(){
    const nodes = document.querySelectorAll('.booking-summary');
    nodes.forEach(async (el) => {
      const ksef = el.getAttribute('data-ksef');
      const env = el.getAttribute('data-env') || 'test';
      const url = <?= json_encode($this->Url->build(['controller' => 'KsefAuthorizations','action' => 'bookingSummary','_full' => true])) ?> + '/' + encodeURIComponent(ksef) + '?env=' + encodeURIComponent(env);
      try {
        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const j = await r.json();
        if (r.ok && j && typeof j.count !== 'undefined') {
          const parts = [];
          parts.push('zapisane: ' + j.count);
          if (typeof j.without_category !== 'undefined') parts.push('bez kategorii: ' + j.without_category);
          if (j.first_note) parts.push('uwagi: ' + j.first_note);
          el.textContent = parts.join(' • ');
        } else {
          el.textContent = '—';
        }
      } catch(e) {
        el.textContent = '—';
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ initCopyIcons(); initBooking(); loadSummaries(); });
  } else {
    initCopyIcons(); initBooking(); loadSummaries();
  }
})();
</script>
<style>
  /* Icon-only copy link */
  a.icon-clip{ display:inline-flex; align-items:center; justify-content:center; width:1.75rem; height:1.75rem; border-radius:.375rem; color: var(--bs-secondary-color); text-decoration:none; }
  a.icon-clip:hover{ background-color: var(--bs-tertiary-bg); color: var(--bs-secondary-color); }
  a.icon-clip i{ font-size: 1rem; }
  .toast-container.received-toasts{ z-index: 1080; }
</style>

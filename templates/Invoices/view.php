<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Invoice $invoice
 */

$money = function($amount, $currency = 'PLN') {
    return number_format($amount, 2, ',', ' ') . ' ' . $currency;
};

$formatDate = function($date) {
    return $date ? $date->format('d.m.Y') : '';
};

$formatDateTime = function($date) {
    return $date ? $date->format('d.m.Y H:i') : '';
};
// Extract session_reference (S=...) from ksef_desc if present
$sessionRef = '';
try {
    // Najpierw spróbuj z kolumny, potem z ksef_desc
    if (!empty($invoice->ksef_session_reference)) {
        $sessionRef = (string)$invoice->ksef_session_reference;
    } else {
        $desc = (string)($invoice->ksef_desc ?? '');
        if ($desc !== '' && preg_match('/S=([A-Z0-9\-]+)/i', $desc, $m)) {
            $sessionRef = (string)$m[1];
        }
    }
} catch (\Throwable $e) { /* ignore */ }

// Helper flag: proforma documents are not sent to KSeF and use inline preview element
$isProforma = (strtolower((string)($invoice->type ?? '')) === 'proforma');
$isNovat    = (strtolower((string)($invoice->type ?? '')) === 'novat');
$__ksefModeEnabled = isset($ksefModeEnabled) ? (bool)$ksefModeEnabled : true;
// Typy dokumentów które nigdy nie trafiają do KSeF
$canSendToKsef = $__ksefModeEnabled && !$isProforma && !$isNovat;

// Mapa typ faktury → akcja edycji
$editActionMap = [
    'vat'              => 'editVat',
    'novat'            => 'editNoVat',
    'proforma'         => 'editProforma',
    'currency'         => 'editCurrency',
    'advance'          => 'editAdvance',
    'final'            => 'editAdvance',
    'correction'       => 'editCorrection',
    'margin'           => 'editMargin',
    'internal'         => 'editInternal',
    'internalevidence' => 'editInternalEvidence',
    'oss'              => 'editVat',
    'rental'           => 'editVat',
];
$invoiceType   = strtolower((string)($invoice->type ?? 'vat'));
$editAction    = $editActionMap[$invoiceType] ?? 'editVat';
$workflowStatus = strtolower(trim((string)($invoice->workflow_status ?? '')));
$canEdit = !in_array($workflowStatus, ['sending', 'sent'], true);
?>

<!-- Actions Bar -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
  <h4 class="fw-medium mb-0 d-flex align-items-center">
    <i class="ri-file-text-line me-2 fs-20"></i>
    Podgląd Faktury:&nbsp;<span id="invoice-fullnumber-display">
      <?php if ($invoice->workflow_status === 'draft' && !$invoice->fullnumber): ?>
        <span class="draft-number-loader">
          <span class="spinner-border spinner-border-sm text-primary" role="status" aria-label="Ładowanie"></span>
          <span id="draft-number-text">Ładowanie numeru…</span>
        </span>
      <?php else: ?>
        <?= h($invoice->fullnumber ?: $invoice->id) ?>
      <?php endif; ?>
    </span>
  </h4>
  <div class="d-flex align-items-center gap-2">
    
        <?php if ($isProforma): ?>
            <button type="button" class="btn btn-outline-primary btn-sm" title="Wydrukuj podgląd" onclick="window.print()">
                <i class="ri-printer-line me-1"></i>Drukuj
            </button>
        <?php endif; ?>
    <?php if (!$isProforma): ?>
        <a href="#" class="btn btn-primary btn-sm btn-pdf-lang"
           data-url-pl="<?= $this->Url->build(['action' => 'print', $invoice->id]) ?>"
           data-url-en="<?= $this->Url->build(['action' => 'print', $invoice->id, '?' => ['lang' => 'en']]) ?>">
          <i class="ri-printer-line me-1"></i>Pobierz PDF
        </a>
        <?php if ($canEdit): ?>
        <?= $this->Html->link(
            '<i class="ri-edit-line me-1"></i>' . ($workflowStatus === 'draft' ? 'Edytuj szkic' : 'Edytuj fakturę'),
            ['action' => $editAction, $invoice->id],
            ['class' => 'btn btn-outline-secondary btn-sm', 'escape' => false]
        ) ?>
        <?php endif; ?>
        <?php if (in_array($invoiceType, ['vat', 'proforma', 'currency', 'margin'], true)): ?>
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="duplicateInvoiceDialog('<?= h($invoice->id) ?>', '<?= h($invoice->fullnumber ?: $invoice->id) ?>')" title="Duplikuj fakturę">
            <i class="ri-file-copy-line me-1"></i>Duplikuj
          </button>
        <?php endif; ?>
        <?php if ($canSendToKsef): ?>
          <button id="btn-send-ksef-test"
                  class="btn btn-primary btn-lg fw-semibold"
                  data-url="<?= h($this->Url->build(['action' => 'sendToKsef', $invoice->id, '?' => ['env' => 'prod', '_ext' => 'json']])) ?>"
                  title="Wyślij do KSeF"
                  <?= ((string)($invoice->ksef_status ?? '')) === '200' ? 'disabled' : '' ?>>
            <i class="ri-send-plane-fill me-1"></i>Wyślij do KSeF
          </button>
          <?= $this->Form->postLink('<i class="ri-refresh-line me-1"></i>Odśwież status',
              ['action' => 'refreshKsefStatus', $invoice->id, '?' => ['env' => 'prod']],
              ['class' => 'btn btn-outline-secondary btn-sm', 'escape' => false, 'title' => 'Sprawdź status przez próbę pobrania z KSeF']) ?>
          <?php if (empty($invoice->ksef_number) && !empty($invoice->fullnumber) && (($invoice->workflow_status ?? '') !== 'draft')): ?>
            <button type="button" class="btn btn-outline-primary btn-sm ksef-sync-btn"
                    data-url="<?= h($this->Url->build(['action' => 'refreshKsefNumber', $invoice->id])) ?>"
                    title="Sprawdź w KSeF, czy faktura została przyjęta (np. po błędzie „Trwa przetwarzanie&quot;/„Duplikat&quot;) i uzupełnij numer KSeF">
              <i class="ri-refresh-line me-1"></i>Uzupełnij nr KSeF
            </button>
          <?php endif; ?>
          <?= $this->Html->link('<i class="ri-download-line me-1"></i>Pobierz FA(3) XML',
              ['action' => 'downloadFa3Xml', $invoice->id],
              ['class' => 'btn btn-outline-success btn-sm', 'escape' => false, 'title' => 'Wygeneruj i pobierz FA(3) XML']) ?>
          <?php if (!empty($invoice->ksef_number)): ?>
            <?= $this->Html->link('<i class="ri-file-pdf-line me-1"></i>Pobierz UPO',
                ['action' => 'downloadUpo', '?' => ['env' => 'prod', 'ksef_number' => $invoice->ksef_number] + ($sessionRef ? ['session_reference' => $sessionRef] : [])],
                ['class' => 'btn btn-outline-danger btn-sm upo-link-needs-session', 'data-session-ref' => $sessionRef, 'escape' => false, 'title' => 'Pobierz UPO jako PDF']) ?>
          <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
  </div>
  
</div>

<?php if (($invoice->workflow_status ?? '') === 'draft'): ?>
<div class="alert alert-warning d-flex align-items-start gap-3 shadow-sm mb-4" role="alert">
  <i class="ri-draft-line fs-2 flex-shrink-0 mt-1"></i>
  <div class="flex-grow-1">
    <div class="fw-bold fs-5 mb-1">Ta faktura jest robocza</div>
    <div class="mb-2">Sprawdź poprawność danych, a następnie wyślij fakturę do KSeF, aby została zaewidencjonowana w systemie Ministerstwa Finansów.</div>
    <?php if (!empty($isPersonInvoice)): ?>
      <div class="mt-2">
        <?= $this->Form->postLink(
          '<i class="ri-arrow-right-line me-1"></i>Przenieś do faktur (bez wysyłki do KSeF)',
          ['action' => 'promoteToIssued', $invoice->id],
          [
            'escape'  => false,
            'class'   => 'btn btn-warning btn-sm fw-semibold',
            'confirm' => 'Przenieść tę fakturę na listę faktur z nadanym numerem? Faktura NIE zostanie wysłana do KSeF.',
          ]
        ) ?>
        <small class="text-muted ms-2">Faktura wystawiona osobie fizycznej — można pominąć KSeF.</small>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Invoice Preview: 1:1 z print.php -->
<style>
@keyframes ksef-shimmer { 0% { background-position: -1000px 0; } 100% { background-position: 1000px 0; } }
.skeleton .sk-line, .skeleton .sk-cell {
    background: linear-gradient(90deg, #f8f9fa 0, #e9ecef 50%, #f8f9fa 100%);
    background-size: 1000px 100%;
    animation: ksef-shimmer 1.5s infinite linear;
    border-radius: 4px;
    height: 12px;
}
.skeleton .sk-line { margin-top: 8px; }
.skeleton .sk-table { margin-top: 12px; }
.skeleton .sk-row { display: grid; grid-template-columns: 1fr 3fr 1fr 1fr; gap: 10px; align-items: center; margin-top: 8px; }
.skeleton .sk-head .sk-cell { height: 14px; }
.skeleton .sk-body .sk-cell { height: 12px; }

.draft-number-loader {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.draft-number-loader .spinner-border {
  width: 1rem;
  height: 1rem;
  border-width: 0.2em;
}
</style>

<div class="mt-3" id="invoice-preview">
    <?php if ($isProforma): ?>
        <?= $this->element('Invoices/print_preview', ['invoice' => $invoice]) ?>
    <?php else: ?>
        <div id="invoice-preview-loader" class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="spinner-border text-primary" role="status" aria-label="Ładowanie"></div>
                    <div>
                        <div class="fw-semibold">Trwa generowanie podglądu faktury…</div>
                        <div class="text-muted small">To może potrwać kilka sekund.</div>
                    </div>
                </div>

                <div class="skeleton mt-3">
                    <div class="sk-line w-50"></div>
                    <div class="sk-line w-25"></div>
                    <div class="sk-line w-75 mt-2"></div>
                    <div class="sk-table">
                        <div class="sk-row sk-head">
                            <div class="sk-cell"></div>
                            <div class="sk-cell"></div>
                            <div class="sk-cell"></div>
                            <div class="sk-cell"></div>
                        </div>
                        <div class="sk-row sk-body"><div class="sk-cell"></div><div class="sk-cell"></div><div class="sk-cell"></div><div class="sk-cell"></div></div>
                        <div class="sk-row sk-body"><div class="sk-cell"></div><div class="sk-cell"></div><div class="sk-cell"></div><div class="sk-cell"></div></div>
                        <div class="sk-row sk-body"><div class="sk-cell"></div><div class="sk-cell"></div><div class="sk-cell"></div><div class="sk-cell"></div></div>
                        <div class="sk-row sk-body"><div class="sk-cell"></div><div class="sk-cell"></div><div class="sk-cell"></div><div class="sk-cell"></div></div>
                    </div>
                </div>

                <div class="text-muted small mt-3">Podgląd 1:1 jak wydruk (układ i style z szablonu drukowania).</div>
            </div>
        </div>
        <div id="invoice-preview-frame" class="mt-3" style="display:none;">
            <iframe id="invoice-pdf-frame" title="Podgląd faktury" style="width:100%;height:75vh;border:1px solid #dee2e6;border-radius:6px;"></iframe>
        </div>
    <?php endif; ?>
</div>

<?php if (!$isProforma): ?>
<script>
// Hook: jeśli inny skrypt wygeneruje podgląd, wywołaj:
// document.dispatchEvent(new CustomEvent('invoice-preview-ready'));
(function(){
    const loader = document.getElementById('invoice-preview-loader');
    const frameWrap = document.getElementById('invoice-preview-frame');
    const iframe = document.getElementById('invoice-pdf-frame');

    async function loadPreview(){
        try {
            const url = "<?= h($this->Url->build(['action' => 'print', $invoice->id, '?' => ['download' => 0]])) ?>";
            // Użyj blob URL dla osadzenia PDF w iframe
            const resp = await fetch(url, { headers: { 'Accept': 'application/pdf' } });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const blob = await resp.blob();
            const pdfUrl = URL.createObjectURL(blob);
            iframe.src = pdfUrl;
            if (loader) loader.style.display = 'none';
            if (frameWrap) frameWrap.style.display = '';
        } catch (e) {
            if (loader) loader.innerHTML = '<div class="card-body"><div class="alert alert-danger" role="alert">Nie udało się wygenerować podglądu: ' + (e && e.message ? e.message : e) + '</div></div>';
        }
    }

    window.reloadInvoicePreview = loadPreview;

    // Opóźnij ładowanie PDF dopiero po pełnym załadowaniu strony + małe opóźnienie
    function deferredLoad() {
        setTimeout(loadPreview, 300); // 300ms po DOMContentLoaded
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', deferredLoad);
    } else if (document.readyState === 'interactive') {
        // Jeśli skrypt załadował się przed complete, poczekaj na pełne załadowanie
        window.addEventListener('load', () => setTimeout(loadPreview, 100));
    } else {
        // Już w stanie complete
        deferredLoad();
    }
})();
</script>
<?php endif; ?>

<hr/>
<?php if (!$isProforma): ?>
<div id="ksef-status" class="alert alert-info d-flex align-items-center justify-content-between" role="alert">
    <div>
        <strong>Status KSeF:</strong>
        <span id="ksef-status-code"><?= h((string)($invoice->ksef_status ?? '—')) ?></span>
        <span id="ksef-status-desc" class="ms-2"><?= h((string)($invoice->ksef_desc ?? '')) ?></span>
        <?php if (!empty($invoice->ksef_number)): ?>
            <span class="ms-3"><strong>Nr KSeF:</strong> <span id="ksef-number"><?= h((string)$invoice->ksef_number) ?></span></span>
        <?php else: ?>
            <span class="ms-3"><strong>Nr KSeF:</strong> <span id="ksef-number">—</span></span>
        <?php endif; ?>
    </div>
    <div id="ksef-links" class="d-flex gap-2"></div>
    <div id="ksef-spinner" class="ms-3" style="display:none;">
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        <span class="ms-1">Wysyłanie...</span>
    </div>
    <input type="hidden" id="ksef-csrf" value="<?= h((string)($this->getRequest()->getAttribute('csrfToken') ?? '')) ?>" />
    <?php
        $appEnv = 'prod';
        $isProdEnv = strtolower((string)$appEnv) === 'prod' || strtolower((string)$appEnv) === 'production';
        $envParam = $isProdEnv ? 'prod' : 'test';
    ?>
    <input type="hidden" id="ksef-download-ksef-base" value="<?= h($this->Url->build(['action' => 'downloadKsef', '?' => ['env' => $envParam, 'ksef_number' => '']])) ?>" />
    <input type="hidden" id="ksef-download-upo-base" value="<?= h($this->Url->build(['action' => 'downloadUpo', '?' => ['env' => $envParam, 'ksef_number' => '']])) ?>" />
    <input type="hidden" id="ksef-env" value="<?= h($envParam) ?>" />
    <input type="hidden" id="ksef-invoice-id" value="<?= h((string)$invoice->id) ?>" />
    <input type="hidden" id="ksef-view-url" value="<?= h($this->Url->build(['action' => 'view', $invoice->id])) ?>" />
    <input type="hidden" id="ksef-session-ref" value="<?= h($sessionRef) ?>" />
    <?php
      // Brak danych nabywcy (nazwa/NIP/VAT UE/inny ID) → okno wysyłki wymaga świadomego potwierdzenia.
      $__nb = $invoice->invoice_contractor ?? null;
      $__noBuyer = $__nb === null || (
          trim((string)($__nb->name ?? '')) === '' && trim((string)($__nb->nip ?? '')) === ''
          && trim((string)($__nb->vat_eu ?? '')) === '' && trim((string)($__nb->tax_id_other ?? '')) === ''
      );
    ?>
    <input type="hidden" id="ksef-no-buyer" value="<?= $__noBuyer ? '1' : '0' ?>" />
    <input type="hidden" id="ksef-issue-date" value="<?= h($invoice->date ? $invoice->date->format('Y-m-d') : '') ?>" />
</div>

<script>
(function(){
  function init(){
    const btn   = document.getElementById('btn-send-ksef-test');
    if (!btn) return;
    const statusCode = document.getElementById('ksef-status-code');
    const statusDesc = document.getElementById('ksef-status-desc');
    const numberSpan = document.getElementById('ksef-number');
    const linksBox   = document.getElementById('ksef-links');
    const spinner    = document.getElementById('ksef-spinner');
    const csrf       = document.getElementById('ksef-csrf').value;

    // Modal elements (resolve after DOM is fully loaded)
    let bsModal = null;
    const modalEl = document.getElementById('ksefModal');
    if (window.bootstrap && modalEl) {
        bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
    }
    let bsConfirm = null;
    const confirmEl = document.getElementById('ksefConfirmModal');
    if (window.bootstrap && confirmEl) {
        bsConfirm = new bootstrap.Modal(confirmEl, { backdrop: 'static', keyboard: true });
    }
    const mProgress       = document.getElementById('ksef-modal-progress');
    const mResult         = document.getElementById('ksef-modal-result');
    const mLinks          = document.getElementById('ksef-modal-links');
    const mTimeline       = document.getElementById('ksef-modal-timeline');
    const mDetailsWrap    = document.getElementById('ksef-details-toggle-wrap');
    const mDetailsBtn     = document.getElementById('ksef-details-toggle');
    const mDetailsChevron = document.getElementById('ksef-details-chevron');
    const envInput        = document.getElementById('ksef-env');
    const envLabel        = document.getElementById('ksef-env-label');

    if (mDetailsBtn) {
      mDetailsBtn.addEventListener('click', function(){
        const visible = mTimeline.style.display !== 'none';
        mTimeline.style.display = visible ? 'none' : '';
        mDetailsBtn.childNodes[1] && (mDetailsBtn.childNodes[1].nodeValue = visible ? 'Pokaż szczegóły' : 'Ukryj szczegóły');
        if (mDetailsChevron) mDetailsChevron.className = visible ? 'ri-arrow-down-s-line me-1' : 'ri-arrow-up-s-line me-1';
      });
    }

    // If already sent (status 200), disable send right away
    const initialStatus = (statusCode && statusCode.textContent ? statusCode.textContent.trim() : '');
    if (initialStatus === '200') {
        btn.disabled = true;
        btn.title = 'Faktura została już wysłana do KSeF';
    }

    const ksefNoBuyer = (document.getElementById('ksef-no-buyer') || {}).value === '1';
    async function doSend(url) {
        let sendSuccess = false;
        if (ksefNoBuyer) {
            const cb = document.getElementById('ksef-confirm-no-buyer');
            const confirmed = (cb && cb.checked) || (!cb && window.confirm('Faktura nie ma danych nabywcy (nazwa, NIP, adres). Nabywca nie zobaczy jej w KSeF. Wysłać mimo to?'));
            if (!confirmed) { btn.disabled = false; return; }
            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'confirm_no_buyer=1';
        }
        btn.disabled = true;
        spinner.style.display = '';
        linksBox.innerHTML = '';
        if (bsModal) {
            // Reset modal state and open
            mProgress.style.display = '';
            mProgress.removeAttribute('hidden');
            mProgress.classList.remove('d-none');
            mResult.innerHTML = '';
            mLinks.innerHTML = '';
            if (mTimeline) { mTimeline.innerHTML = ''; mTimeline.style.display = 'none'; }
            if (mDetailsWrap) mDetailsWrap.style.display = 'none';
            if (mDetailsBtn) { mDetailsBtn.childNodes[1] && (mDetailsBtn.childNodes[1].nodeValue = 'Pokaż szczegóły'); }
            if (mDetailsChevron) mDetailsChevron.className = 'ri-arrow-down-s-line me-1';
            bsModal.show();
        }
        try {
            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrf || ''
                }
            });
            const data = await resp.json();
            if (!resp.ok || !data || data.success === false) {
                const msg = (data && data.error) ? data.error : ('HTTP ' + resp.status);
                statusCode.textContent = 'ERR';
                statusDesc.textContent = 'Błąd wysyłki: ' + msg;
                if (mResult) {
                    mProgress.style.display = 'none';
                    mResult.innerHTML = '<div class="alert alert-danger" role="alert">Błąd wysyłki: ' + (msg || '') + '</div>';
                }
                return;
            }
            statusCode.textContent = (data.statusCode || '').toString();
            statusDesc.textContent = data.statusDesc || '';
            // If semantic error 450 on TEST env, append maintenance info
            const isTestEnv = (envInput && envInput.value) ? (envInput.value === 'test') : false;
            if (String(data.statusCode) === '450' && isTestEnv) {
                const note = 'Trwa aktualnie przerwa techniczna w dostępie do https://ksef.mf.gov.pl/';
                const base = data.statusDesc || '';
                statusDesc.textContent = base ? (base + ' — ' + note) : note;
            }
            // If we have a final 200 status, stop the loader immediately
            const isOk = String(data.statusCode) === '200';
            if (isOk) {
                if (mProgress) {
                    mProgress.style.display = 'none';
                    mProgress.style.setProperty('display', 'none', 'important');
                    mProgress.classList.add('d-none');
                    mProgress.setAttribute('hidden', 'true');
                }
                if (spinner) {
                    spinner.style.display = 'none';
                    spinner.style.setProperty('display', 'none', 'important');
                    spinner.classList.add('d-none');
                }
                const modalTitle = document.getElementById('ksefModalLabel');
                if (modalTitle) {
                    modalTitle.textContent = 'Wysłano do KSeF';
                }
                // Block re-sending after success 200
                sendSuccess = true;
                btn.disabled = true;
                btn.title = 'Faktura została już wysłana do KSeF';
                if (btnBanner) { btnBanner.disabled = true; btnBanner.title = 'Faktura została już wysłana do KSeF'; }
            }
            if (mTimeline && Array.isArray(data.messages)) {
                mTimeline.innerHTML = '';
                data.messages.forEach(function(msg){
                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-start';
                    const left = document.createElement('div');
                    left.className = 'ms-2 me-auto';
                    const title = document.createElement('div');
                    title.className = 'fw-semibold';
                    title.textContent = (msg.stage || '').toString();
                    left.appendChild(title);
                    const small = document.createElement('small');
                    small.textContent = (msg.message || '');
                    left.appendChild(small);
                    const badge = document.createElement('span');
                    const lvl = (msg.level || 'info');
                    badge.className = 'badge rounded-pill ' + (lvl === 'success' ? 'bg-success' : (lvl === 'warning' ? 'bg-warning text-dark' : (lvl === 'error' ? 'bg-danger' : 'bg-secondary')));
                    badge.textContent = (msg.ts ? new Date(msg.ts).toLocaleTimeString() : '');
                    li.appendChild(left);
                    li.appendChild(badge);
                    mTimeline.appendChild(li);
                });
            }
            if (data.fullnumber) {
                const titleSpan = document.getElementById('invoice-fullnumber-display');
                if (titleSpan) titleSpan.textContent = data.fullnumber;
                document.title = document.title.replace(/:\s*[^\s].*$/, ': ' + data.fullnumber);
            }
            if (data.ksefNumber && typeof window.reloadInvoicePreview === 'function') {
                setTimeout(window.reloadInvoicePreview, 800);
            }
            if (data.ksefNumber) {
                numberSpan.textContent = data.ksefNumber;
                // Build limited links: only download from KSeF and UPO PDF
                const aInv = document.createElement('a');
                // Korzystaj z naszej metody print() do pobrania/preview PDF
                const localPrintUrl = "<?= h($this->Url->build(['action' => 'print', $invoice->id, '?' => ['download' => 0]])) ?>";
                aInv.href = localPrintUrl;
                aInv.className = 'btn btn-sm btn-success';
                aInv.innerHTML = '<i class="ri-download-2-line me-1"></i>Pobierz z KSeF';
                aInv.target = '_blank';

                const aUpoPdf = document.createElement('a');
                // Build href from our Cake route: downloadUpo with optional session_reference
                const upoBase = document.getElementById('ksef-download-upo-base');
                const sessInp = document.getElementById('ksef-session-ref');
                const sessRef = sessInp && sessInp.value ? sessInp.value.trim() : '';
                const ksefNo = String(data.ksefNumber || '<?= h((string)($invoice->ksef_number ?? '')) ?>');
                let upoHref = '#';
                if (upoBase && upoBase.value && ksefNo) {
                    upoHref = upoBase.value + encodeURIComponent(ksefNo);
                } else if (data.links && data.links.downloadUpoPdfTest) {
                    upoHref = data.links.downloadUpoPdfTest;
                }
                // Normalize HTML-escaped ampersands to plain '&' in href
                if (upoHref.indexOf('&amp;') !== -1) {
                    upoHref = upoHref.replace(/&amp;/g, '&');
                }
                if (upoHref && sessRef) {
                    // Append session_reference param
                    try {
                        const urlObj = new URL(upoHref, window.location.origin);
                        urlObj.searchParams.set('session_reference', sessRef);
                        upoHref = urlObj.toString();
                    } catch(e) { /* ignore URL build error */ }
                }
                aUpoPdf.href = upoHref;
                aUpoPdf.className = 'btn btn-sm btn-outline-danger upo-link-needs-session';
                aUpoPdf.setAttribute('data-session-ref', sessRef);
                aUpoPdf.setAttribute('title', 'Pobierz UPO jako PDF');
                aUpoPdf.innerHTML = '<i class="ri-file-pdf-line me-1"></i>Pobierz UPO (PDF)';
                aUpoPdf.target = '_blank';

                linksBox.appendChild(aInv);
                linksBox.appendChild(aUpoPdf);

                if (mLinks) {
                    // Clone nodes for modal (so both places have their own links)
                    const aInv2 = aInv.cloneNode(true);
                    const aUpoPdf2 = aUpoPdf.cloneNode(true);
                    mLinks.appendChild(aInv2);
                    mLinks.appendChild(aUpoPdf2);
                }
            }
            if (mResult) {
                mProgress.style.display = 'none';
                const ok = !!data.ksefNumber && (data.statusCode === 200);
                const badge = '<span class="badge ' + (ok ? 'bg-success' : 'bg-warning') + '">' + (data.statusCode || '') + '</span>';
                let maintenanceHtml = '';
                if (String(data.statusCode) === '450' && isTestEnv) {
                    maintenanceHtml = '<div class="mt-2 small text-muted">Trwa aktualnie przerwa techniczna w dost\u0119pie do <a href="https://ksef.mf.gov.pl/" target="_blank" rel="noopener">ksef.mf.gov.pl</a>.</div>';
                }
                mResult.innerHTML = '<div class="alert ' + (ok ? 'alert-success' : 'alert-warning') + '" role="alert">' +
                    '<div><strong>Status:</strong> ' + badge + ' ' + (data.statusDesc || '') + '</div>' +
                    (data.ksefNumber ? ('<div class="mt-2"><strong>Numer KSeF:</strong> ' + data.ksefNumber + '</div>') : '') +
                    maintenanceHtml +
                    '</div>';
                if (mDetailsWrap && mTimeline && mTimeline.children.length > 0) {
                    mDetailsWrap.style.display = '';
                }
            }
        } catch (e) {
            statusCode.textContent = 'ERR';
            statusDesc.textContent = 'Błąd wysyłki: ' + (e && e.message ? e.message : e);
            if (mResult) {
                mProgress.style.display = 'none';
                mResult.innerHTML = '<div class="alert alert-danger" role="alert">Błąd wysyłki: ' + (e && e.message ? e.message : e) + '</div>';
            }
        } finally {
            spinner.style.display = 'none';
            if (!sendSuccess) btn.disabled = false;
        }
    }

    // Confirmation flow
    const confirmSendBtn = document.getElementById('ksef-confirm-send');
    const dateWarningEl  = document.getElementById('ksef-date-warning');
    const issueDateInput = document.getElementById('ksef-issue-date');

    const previewWrap   = document.getElementById('ksef-preview-number-wrap');
    const previewNumber = document.getElementById('ksef-preview-number');
    const previewNote   = document.getElementById('ksef-preview-number-note');
    const invoiceId     = <?= json_encode((string)$invoice->id) ?>;

    function fetchPreviewNumber() {
        if (!previewWrap || !previewNumber) return;
        previewNumber.textContent = '…';
        previewNote.textContent = '';
        previewWrap.style.display = '';
        fetch('/invoices/' + invoiceId + '/preview-ksef-number', {
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf || '' }
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data && data.success) {
                previewNumber.textContent = data.fullnumber;
                previewNote.textContent = data.already_assigned ? '(już nadany)' : '(zostanie nadany przy wysyłce)';
            } else {
                previewWrap.style.display = 'none';
            }
        })
        .catch(function(){ previewWrap.style.display = 'none'; });
    }

    btn.addEventListener('click', function(){
        if (bsConfirm) {
            // Ustaw etykietę środowiska w potwierdzeniu
            const env = (envInput && envInput.value) ? envInput.value : 'prod';
            if (envLabel) {
                envLabel.textContent = (env === 'prod' ? 'PROD' : 'TEST');
            }
            // Sprawdź datę wystawienia — KSeF wymaga dokumentów z datą wystawienia = dzisiaj
            if (dateWarningEl) {
                const issueDate = issueDateInput ? issueDateInput.value : '';
                const todayStr = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
                if (issueDate && issueDate !== todayStr) {
                    const [y, m, d] = issueDate.split('-');
                    const issueFmt = d + '.' + m + '.' + y;
                    const [ty, tm, td] = todayStr.split('-');
                    const todayFmt = td + '.' + tm + '.' + ty;
                    dateWarningEl.innerHTML = '<div class="alert alert-warning mt-3 mb-0" role="alert">'
                        + '<i class="ri-error-warning-line me-1"></i>'
                        + '<strong>Uwaga:</strong> Data wystawienia faktury to <strong>' + issueFmt + '</strong>, '
                        + 'a dzisiaj jest <strong>' + todayFmt + '</strong>. '
                        + 'KSeF wymaga, aby data wystawienia faktury była zgodna z datą jej wysyłki. '
                        + 'Wysyłka może zostać odrzucona.'
                        + '</div>';
                } else {
                    dateWarningEl.innerHTML = '';
                }
            }
            // Ostrzeżenie o braku danych nabywcy + wymagane potwierdzenie (checkbox)
            const buyerWarnEl = document.getElementById('ksef-buyer-warning');
            if (buyerWarnEl) {
                if (ksefNoBuyer) {
                    buyerWarnEl.innerHTML = '<div class="alert alert-danger py-2 mb-2">'
                        + '<strong>Faktura nie ma danych nabywcy</strong> (nazwa, NIP, adres). '
                        + 'Trafi do KSeF jako faktura bez identyfikatora nabywcy — kontrahent <strong>nie zobaczy jej</strong> w swoim KSeF. '
                        + 'Jeśli to pomyłka, anuluj i uzupełnij nabywcę w edycji faktury.'
                        + '<div class="form-check mt-2 mb-0"><input class="form-check-input" type="checkbox" id="ksef-confirm-no-buyer">'
                        + '<label class="form-check-label fw-semibold" for="ksef-confirm-no-buyer">Rozumiem, wysyłam fakturę bez danych nabywcy</label></div>'
                        + '</div>';
                    if (confirmSendBtn) confirmSendBtn.disabled = true;
                    const cb = document.getElementById('ksef-confirm-no-buyer');
                    if (cb) cb.addEventListener('change', function(){ if (confirmSendBtn) confirmSendBtn.disabled = !cb.checked; });
                } else {
                    buyerWarnEl.innerHTML = '';
                    if (confirmSendBtn) confirmSendBtn.disabled = false;
                }
            }
            fetchPreviewNumber();
            bsConfirm.show();
        } else {
            const url = btn.getAttribute('data-url');
            doSend(url);
        }
    });
    if (confirmSendBtn) {
        confirmSendBtn.addEventListener('click', function(){
            const url = btn.getAttribute('data-url');
            if (bsConfirm) bsConfirm.hide();
            doSend(url);
        });
    }

    // Banner draft button — delegates to main btn click so the full confirm flow runs
    const btnBanner = document.getElementById('btn-send-ksef-draft-banner');
    if (btnBanner) {
        btnBanner.addEventListener('click', function(){ btn.click(); });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

<script>
// Prompt for session_reference if missing when clicking static UPO PDF buttons
(function(){
    function attach(){
        document.querySelectorAll('a.upo-link-needs-session').forEach(function(a){
            a.addEventListener('click', function(ev){
                try {
                    var url = new URL(a.href, window.location.origin);
                    var hasRef = url.searchParams.get('session_reference');
                    var dataRef = a.getAttribute('data-session-ref') || '';
                    if (!hasRef && !dataRef) {
                        ev.preventDefault();
                        var inp = window.prompt('Podaj session_reference (S=...) dla UPO:', '');
                        if (inp && inp.trim() !== '') {
                            url.searchParams.set('session_reference', inp.trim());
                            window.open(url.toString(), '_blank');
                        }
                    }
                } catch(e) { /* ignore */ }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attach);
    } else {
        attach();
    }
})();
</script>
<?php endif; ?>

<!-- Modal: KSeF send progress -->
<?php if ($canSendToKsef): ?>
<div class="modal fade" id="ksefModal" tabindex="-1" aria-labelledby="ksefModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ksefModalLabel">Wysyłka do KSeF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                <div id="ksef-modal-progress" class="d-flex align-items-center gap-2">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    <span>Trwa wysyłanie i sprawdzanie statusu…</span>
                </div>
                <div id="ksef-modal-result" class="mt-3"></div>
                <div class="mt-2" id="ksef-details-toggle-wrap" style="display:none">
                    <button type="button" class="btn btn-link btn-sm p-0 text-muted" id="ksef-details-toggle">
                        <i class="ri-arrow-down-s-line me-1" id="ksef-details-chevron"></i>Pokaż szczegóły
                    </button>
                </div>
                <ol id="ksef-modal-timeline" class="list-group list-group-numbered mt-2" style="display:none"></ol>
            </div>
            <div class="modal-footer d-flex align-items-center justify-content-between">
                <div id="ksef-modal-links" class="d-flex gap-2"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
    </div>
<!-- Modal: Confirm send to KSeF -->
<div class="modal fade" id="ksefConfirmModal" tabindex="-1" aria-labelledby="ksefConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ksefConfirmModalLabel">Potwierdź wysyłkę do KSeF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                <p>
                    Czy na pewno wysłać fakturę
                    <strong><?= h((string)($invoice->fullnumber ?? $invoice->id)) ?></strong>
                    do KSeF (środowisko: <strong id="ksef-env-label">PROD</strong>)?
                </p>
                <div id="ksef-preview-number-wrap" class="alert alert-info py-2 mb-2" style="display:none">
                    <i class="ri-hashtag me-1"></i>
                    Numer faktury w XML: <strong id="ksef-preview-number">…</strong>
                    <span id="ksef-preview-number-note" class="text-muted small ms-1"></span>
                </div>
                <ul class="mb-0">
                    <li>Wyślemy dokładnie oryginalny XML FA(3) bez modyfikacji.</li>
                    <li>Po zakończeniu zobaczysz status, numer KSeF oraz linki do pobrania dokumentu i UPO.</li>
                </ul>
                <div id="ksef-date-warning"></div>
                <div id="ksef-buyer-warning"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                <button type="button" id="ksef-confirm-send" class="btn btn-primary">
                    <i class="ri-send-plane-line me-1"></i>Wyślij teraz
                </button>
            </div>
        </div>
    </div>
    </div>
<?php endif; ?>

<script>
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

// ===== LOAD DRAFT NUMBER FOR DRAFT VIEW =====
document.addEventListener('DOMContentLoaded', function() {
  const displaySpan = document.getElementById('invoice-fullnumber-display');
  const draftNumberText = document.getElementById('draft-number-text');

  // Only load if showing loader
  if (!draftNumberText) return;

  const invoiceId = '<?= h($invoice->id) ?>';
  const seriesId = '<?= h($invoice->invoice_series_id) ?>';
  const invoiceDate = '<?= $invoice->date ? $invoice->date->format('Y-m-d') : date('Y-m-d') ?>';

  fetch('<?= $this->Url->build(['action' => 'ajaxGetDraftNumber']) ?>', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': document.querySelector('input[name="_csrfToken"]')?.value || ''
    },
    body: JSON.stringify({
      invoice_id: invoiceId,
      series_id: seriesId,
      date: invoiceDate
    })
  })
  .then(response => {
    if (!response.ok) throw new Error('Failed to get draft number');
    return response.json();
  })
  .then(data => {
    if (data.fullnumber) {
      // Replace loader with actual number + info icon
      displaySpan.innerHTML = '<strong>' + escapeHtml(data.fullnumber) + '</strong>'
        + ' <i class="ri-information-line" style="color: #6c757d; margin-left: 4px;" '
        + 'data-bs-toggle="tooltip" data-bs-placement="top" '
        + 'title="Numer proponowany na podstawie schematu serii"></i>';
      // Initialize Bootstrap tooltip
      new bootstrap.Tooltip(displaySpan.querySelector('[data-bs-toggle="tooltip"]'));
    }
  })
  .catch(error => {
    console.error('Error loading draft number:', error);
    draftNumberText.textContent = 'Nie udało się wczytać numeru';
  });

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
});
</script>
<script>
// ===== Uzupełnij nr KSeF (wysyłka przeszła w KSeF, ale numer nie zapisał się lokalnie) =====
(function(){
  var meta = document.querySelector('meta[name="csrfToken"]');
  var token = meta ? meta.getAttribute('content') : (document.getElementById('ksef-csrf')?.value || '');
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.ksef-sync-btn');
    if (!btn) return;
    e.preventDefault();
    var url = btn.getAttribute('data-url');
    if (!url || btn.disabled) return;
    btn.disabled = true;
    fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': token, 'Accept': 'application/json' } })
      .then(function(r){
        return r.text().then(function(t){
          try { return JSON.parse(t); }
          catch(e){
            var snippet = (t || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300);
            return { success: false, message: 'Nieoczekiwana odpowiedź serwera (HTTP ' + r.status + '): ' + snippet };
          }
        });
      })
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
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\CostInvoice $invoice
 */
$this->assign('title', 'Faktura kosztowa ' . h($invoice->invoice_number ?: $invoice->ksef_number));

$statusMap = [
    'received' => ['label' => 'Otrzymana',    'cls' => 'bg-secondary text-white', 'next' => 'verified'],
    'verified' => ['label' => 'Zweryfikowana','cls' => 'bg-info text-white',      'next' => 'paid'],
    'paid'     => ['label' => 'Zapłacona',    'cls' => 'bg-success text-white',   'next' => null],
];
$srcMap = [
    'ksef'   => ['label' => 'KSeF',   'cls' => 'bg-primary-subtle text-primary',  'icon' => 'ri-government-line'],
    'manual' => ['label' => 'Ręczna', 'cls' => 'bg-warning-subtle text-warning',  'icon' => 'ri-edit-line'],
];
$st  = $statusMap[$invoice->status] ?? $statusMap['received'];
$src = $srcMap[$invoice->source]    ?? $srcMap['manual'];
$fnum  = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';
$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';

$csrfToken     = $this->request->getAttribute('csrfToken');
$setStatusUrl  = $this->Url->build(['action' => 'setStatus']);
$unassignUrl   = $this->Url->build(['action' => 'unassignOrder']);
?>

<!-- Nagłówek -->
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i> Lista
    </a>
    <h4 class="mb-0 fw-semibold ms-2">
        <?= h($invoice->invoice_number ?: '(brak numeru)') ?>
    </h4>
    <span class="badge <?= $st['cls'] ?>"><?= $st['label'] ?></span>
    <span class="badge <?= $src['cls'] ?>"><i class="<?= $src['icon'] ?> me-1"></i><?= $src['label'] ?></span>
    <div class="ms-auto d-flex gap-2">
        <?php if ($st['next']): ?>
        <button class="btn btn-sm btn-outline-primary" id="btn-next-status"
                data-status="<?= $st['next'] ?>"
                data-label="<?= h($statusMap[$st['next']]['label']) ?>">
            <i class="ri-arrow-right-circle-line me-1"></i>
            Oznacz jako: <?= h($statusMap[$st['next']]['label']) ?>
        </button>
        <?php endif; ?>
        <a href="<?= $this->Url->build(['action' => 'edit', $invoice->id]) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-edit-line me-1"></i> Edytuj
        </a>
    </div>
</div>

<div class="row g-3">

<!-- Dane faktury -->
<div class="col-lg-6">
<div class="card h-100">
    <div class="card-header fw-semibold">Dane faktury</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <tr><th class="ps-3" style="width:45%">Numer</th>
                    <td class="fw-semibold"><?= h($invoice->invoice_number ?: '—') ?></td></tr>
                <?php if ($invoice->ksef_number): ?>
                <tr><th class="ps-3">Numer KSeF</th>
                    <td class="small text-muted" style="word-break:break-all"><?= h($invoice->ksef_number) ?></td></tr>
                <?php endif; ?>
                <tr><th class="ps-3">Miesiąc rozliczeniowy</th>
                    <td><span class="badge bg-dark-subtle text-dark"><?= h($invoice->accounting_month ?: '—') ?></span></td></tr>
                <tr><th class="ps-3">Data wystawienia</th><td><?= $fdate($invoice->issue_date) ?></td></tr>
                <tr><th class="ps-3">Data wpływu</th><td><?= $fdate($invoice->receipt_date) ?></td></tr>
                <tr><th class="ps-3">Status</th>
                    <td><span class="badge <?= $st['cls'] ?>"><?= $st['label'] ?></span></td></tr>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- Przewoźnik + kwoty -->
<div class="col-lg-6">
<div class="card mb-3">
    <div class="card-header fw-semibold">Przewoźnik</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <tr><th class="ps-3" style="width:40%">Nazwa</th>
                    <td class="fw-semibold"><?= h($invoice->contractor_name ?: '—') ?></td></tr>
                <?php if ($invoice->contractor_nip): ?>
                <tr><th class="ps-3">NIP</th><td><?= h($invoice->contractor_nip) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="card">
    <div class="card-header fw-semibold">Kwoty</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <tr><th class="ps-3" style="width:40%">Netto</th><td><?= $fnum($invoice->netto) ?> <?= h($invoice->currency) ?></td></tr>
                <tr><th class="ps-3">VAT</th><td><?= $fnum($invoice->vat) ?> <?= h($invoice->currency) ?></td></tr>
                <tr><th class="ps-3">Brutto</th><td class="fw-bold"><?= $fnum($invoice->brutto) ?> <?= h($invoice->currency) ?></td></tr>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- PDF -->
<?php if ($invoice->pdf_path): ?>
<div class="col-12">
<div class="card border-danger-subtle">
    <div class="card-body py-2 d-flex align-items-center gap-3">
        <i class="ri-file-pdf-line fs-3 text-danger"></i>
        <div>Dokument PDF jest dostępny.</div>
        <a href="/<?= h($invoice->pdf_path) ?>" target="_blank" class="btn btn-sm btn-outline-danger ms-auto">
            <i class="ri-download-line me-1"></i> Pobierz PDF
        </a>
    </div>
</div>
</div>
<?php endif; ?>

<!-- Uwagi -->
<?php if ($invoice->notes): ?>
<div class="col-12">
<div class="card">
    <div class="card-header fw-semibold">Uwagi</div>
    <div class="card-body"><p class="mb-0" style="white-space:pre-wrap"><?= h($invoice->notes) ?></p></div>
</div>
</div>
<?php endif; ?>

<!-- Powiązane zlecenia -->
<div class="col-12">
<div class="card">
    <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
        <span><i class="ri-links-line me-1 text-primary"></i>Powiązane zlecenia transportowe</span>
        <span class="badge bg-primary-subtle text-primary"><?= count($invoice->speed_orders ?? []) ?></span>
    </div>
    <div class="card-body p-0" id="orders-list">
    <?php if (empty($invoice->speed_orders)): ?>
        <div class="text-center text-muted py-3 small" id="no-orders-msg">
            Brak powiązanych zleceń.
        </div>
    <?php else: ?>
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Symbol</th>
                    <th>Zleceniodawca</th>
                    <th>Data rozładunku</th>
                    <th>Wartość zlecenia</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="orders-tbody">
            <?php foreach ($invoice->speed_orders as $so): ?>
            <tr data-order-id="<?= $so->id ?>">
                <td class="ps-3 fw-semibold">
                    <a href="<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'view', $so->id]) ?>"
                       class="text-decoration-none"><?= h($so->symbol) ?></a>
                </td>
                <td><?= h($so->buyer_name) ?></td>
                <td><?= $so->date_delivery instanceof \DateTimeInterface ? $so->date_delivery->format('d.m.Y') : h(substr((string)($so->date_delivery ?? ''), 0, 10)) ?></td>
                <td><?= $so->netto !== null ? number_format((float)$so->netto, 2, ',', ' ') . ' ' . h($so->currency) : '—' ?></td>
                <td class="text-end">
                    <button class="btn btn-xs btn-outline-danger py-0 px-1 btn-unassign"
                            data-order-id="<?= $so->id ?>"
                            data-symbol="<?= h($so->symbol) ?>"
                            title="Odepnij zlecenie">
                        <i class="ri-unlink"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>
</div>

</div><!-- /row -->

<?php $this->append('scriptBottom'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var invoiceId  = <?= (int)$invoice->id ?>;
    var csrfToken  = '<?= h($csrfToken) ?>';
    var setStatusUrl = '<?= $setStatusUrl ?>';
    var unassignUrl  = '<?= $unassignUrl ?>';

    function showToast(msg, ok) {
        if (window.Swal) {
            Swal.fire({ toast: true, position: 'bottom-end', icon: ok ? 'success' : 'error',
                title: msg, showConfirmButton: false, timer: 2500, timerProgressBar: true });
        } else { alert(msg); }
    }

    function post(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload),
        }).then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }

    // Zmiana statusu
    var btnNext = document.getElementById('btn-next-status');
    if (btnNext) {
        btnNext.addEventListener('click', function() {
            var ns = this.dataset.status;
            post(setStatusUrl, { id: invoiceId, status: ns }).then(function(data) {
                if (data.success) {
                    showToast('Status zmieniony', true);
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    showToast(data.error || 'Błąd', false);
                }
            }).catch(function(e) { showToast('Błąd: ' + e.message, false); });
        });
    }

    // Odepnij zlecenie
    document.querySelectorAll('.btn-unassign').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var ordId  = parseInt(this.dataset.orderId);
            var symbol = this.dataset.symbol;
            if (!confirm('Odpiąć zlecenie ' + symbol + ' od tej faktury?')) return;
            post(unassignUrl, { cost_invoice_id: invoiceId, speed_order_id: ordId })
            .then(function(data) {
                if (data.success) {
                    var tr = document.querySelector('tr[data-order-id="' + ordId + '"]');
                    if (tr) tr.remove();
                    showToast('Odpięto zlecenie ' + symbol, true);
                } else {
                    showToast(data.error || 'Błąd', false);
                }
            }).catch(function(e) { showToast('Błąd: ' + e.message, false); });
        });
    });
});
</script>
<?php $this->end(); ?>

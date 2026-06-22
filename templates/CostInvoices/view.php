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
$markPaidUrl   = $this->Url->build(['action' => 'markPaid']);
$unmarkPaidUrl = $this->Url->build(['action' => 'unmarkPaid']);

// Helpery płatności
$paidAmount = (float)($invoice->paid_amount ?? 0);
$brutto     = (float)($invoice->brutto ?? 0);
$remaining  = max(0, round($brutto - $paidAmount, 2));
$isPaid     = $invoice->status === 'paid';
$isPartial  = !$isPaid && $paidAmount > 0;
$today      = date('Y-m-d');

$pdStr = $invoice->payment_date instanceof \DateTimeInterface
    ? $invoice->payment_date->format('Y-m-d')
    : substr((string)($invoice->payment_date ?? ''), 0, 10);
$paidAtStr = $invoice->paid_at instanceof \DateTimeInterface
    ? $invoice->paid_at->format('Y-m-d')
    : substr((string)($invoice->paid_at ?? ''), 0, 10);

$daysOverdue = 0;
$daysToDue = null;
if ($pdStr && !$isPaid) {
    $diff = (int)floor((strtotime($pdStr) - strtotime($today)) / 86400);
    if ($diff < 0) $daysOverdue = abs($diff);
    else $daysToDue = $diff;
}

$methodLabels = [
    'transfer'     => 'Przelew',
    'cash'         => 'Gotówka',
    'card'         => 'Karta',
    'compensation' => 'Kompensata',
    'other'        => 'Inna',
];
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

<!-- Płatność -->
<div class="col-12">
<div class="card border-<?= $isPaid ? 'success' : ($daysOverdue > 0 ? 'danger' : 'warning') ?>-subtle">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <i class="ri-bank-card-line text-primary"></i>
        Płatność
        <?php if ($isPaid): ?>
            <span class="badge bg-success ms-2"><i class="ri-checkbox-circle-line me-1"></i>Opłacona</span>
        <?php elseif ($isPartial): ?>
            <span class="badge bg-warning text-dark ms-2"><i class="ri-time-line me-1"></i>Częściowo (<?= round(($paidAmount / max($brutto, 0.01)) * 100) ?>%)</span>
        <?php elseif ($daysOverdue > 0): ?>
            <span class="badge bg-danger ms-2"><i class="ri-error-warning-line me-1"></i>Przeterminowana o <?= $daysOverdue ?> dni</span>
        <?php elseif ($daysToDue !== null): ?>
            <span class="badge bg-warning text-dark ms-2"><i class="ri-alarm-warning-line me-1"></i>Termin za <?= $daysToDue ?> dni</span>
        <?php else: ?>
            <span class="badge bg-secondary ms-2"><i class="ri-question-line me-1"></i>Bez terminu</span>
        <?php endif; ?>

        <div class="ms-auto">
            <?php if ($isPaid): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-unmark-paid">
                    <i class="ri-arrow-go-back-line me-1"></i> Cofnij oznaczenie
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#payForm">
                    <i class="ri-checkbox-circle-line me-1"></i> Oznacz jako zapłaconą
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body py-2 px-3">
        <div class="row g-3 mb-2">
            <div class="col-md-3">
                <div class="text-muted small text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Termin płatności</div>
                <div class="fw-semibold"><?= $pdStr ? $fdate($pdStr) : '<span class="text-muted">— brak —</span>' ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Wpłacono</div>
                <div class="fw-semibold <?= $isPaid ? 'text-success' : ($isPartial ? 'text-warning' : '') ?>">
                    <?= $fnum($paidAmount) ?> <?= h($invoice->currency) ?>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Pozostało</div>
                <div class="fw-semibold <?= $remaining > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= $fnum($remaining) ?> <?= h($invoice->currency) ?>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small text-uppercase" style="font-size:.65rem;letter-spacing:.04em">Data zapłaty</div>
                <div class="fw-semibold">
                    <?= $paidAtStr ? $fdate($paidAtStr) : '<span class="text-muted">— brak —</span>' ?>
                    <?php if (!empty($invoice->payment_method)): ?>
                        <span class="badge bg-info-subtle text-info border ms-1" style="font-size:.65rem">
                            <?= h($methodLabels[$invoice->payment_method] ?? $invoice->payment_method) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Formularz oznaczenia jako zapłacona -->
        <div class="collapse <?= $isPaid ? '' : '' ?>" id="payForm">
            <hr class="my-2">
            <form id="payFormForm" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Data zapłaty</label>
                    <input type="date" name="paid_at" class="form-control form-control-sm" value="<?= h($today) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Kwota</label>
                    <input type="number" name="paid_amount" step="0.01" min="0" class="form-control form-control-sm"
                           value="<?= number_format($remaining > 0 ? $remaining : $brutto, 2, '.', '') ?>" required>
                    <div class="form-text small">Brutto: <?= $fnum($brutto) ?> <?= h($invoice->currency) ?></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Metoda</label>
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="transfer">Przelew</option>
                        <option value="cash">Gotówka</option>
                        <option value="card">Karta</option>
                        <option value="compensation">Kompensata</option>
                        <option value="other">Inna</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success btn-sm w-100">
                        <i class="ri-check-line me-1"></i> Zapisz
                    </button>
                </div>
            </form>
        </div>
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

<!-- Historia wpłat -->
<div class="col-12">
<div class="card">
    <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
        <span><i class="ri-history-line me-1 text-primary"></i>Historia wpłat</span>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-info" id="btn-link-bank-tx"
                    title="Dopnij przelew z banku (direction=D, kontrahent dopasowany po NIP/nazwa)">
                <i class="ri-bank-line me-1"></i> Z banku
            </button>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#payAddForm">
                <i class="ri-add-line me-1"></i> Dodaj wpłatę
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <!-- Form dodawania ręcznej wpłaty -->
        <div class="collapse" id="payAddForm">
            <form id="payAddFormForm" class="p-3 border-bottom bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Data</label>
                        <input type="date" name="payment_date" class="form-control form-control-sm"
                               value="<?= $today ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Kwota</label>
                        <input type="number" name="amount" step="0.01" min="0.01" class="form-control form-control-sm"
                               value="<?= number_format($remaining > 0 ? $remaining : $brutto, 2, '.', '') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Metoda</label>
                        <select name="payment_method" class="form-select form-select-sm">
                            <option value="transfer">Przelew</option>
                            <option value="cash">Gotówka</option>
                            <option value="card">Karta</option>
                            <option value="compensation">Kompensata</option>
                            <option value="other">Inna</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Notatka</label>
                        <input type="text" name="note" class="form-control form-control-sm" placeholder="(opcjonalnie)">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="ri-check-line me-1"></i> Zapisz
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php $payments = $invoice->cost_invoice_payments ?? []; ?>
        <?php if (empty($payments)): ?>
            <div class="text-center text-muted py-3 small">
                <i class="ri-inbox-line me-1"></i>Brak wpłat. Dodaj ręcznie lub dopnij przelew z banku.
            </div>
        <?php else: ?>
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Data</th>
                        <th class="text-end">Kwota</th>
                        <th>Metoda</th>
                        <th>Źródło</th>
                        <th>Notatka</th>
                        <th>Dodał</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="payments-tbody">
                <?php foreach ($payments as $p):
                    $methodLbl = $methodLabels[$p->payment_method ?? ''] ?? ($p->payment_method ?: '—');
                    $btx = $p->bank_transaction ?? null;
                    $u   = $p->user ?? null;
                    $userName = $u ? trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: (string)$u->email : 'system';
                ?>
                <tr data-payment-id="<?= h($p->id) ?>">
                    <td class="ps-3"><?= $fdate($p->payment_date) ?></td>
                    <td class="text-end fw-semibold"><?= $fnum($p->amount) ?> <?= h($p->currency) ?></td>
                    <td><?= h($methodLbl) ?></td>
                    <td>
                        <?php if ($p->payment_type === 'bank' && $btx): ?>
                            <span class="badge bg-info-subtle text-info border" title="<?= h($btx->title ?? '') ?>">
                                <i class="ri-bank-line"></i> bank · <?= h($btx->party_name ?? '') ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted border">ręczna</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= h($p->note ?? '') ?></td>
                    <td class="small text-muted"><?= h($userName) ?></td>
                    <td class="text-end">
                        <button class="btn btn-xs btn-outline-danger py-0 px-1 btn-del-payment"
                                data-payment-id="<?= h($p->id) ?>" title="Usuń wpłatę">
                            <i class="ri-delete-bin-line"></i>
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

<!-- Modal: wybór przelewu z banku -->
<div class="modal fade" id="bankTxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title"><i class="ri-bank-line me-1 text-info"></i> Wybierz przelew z banku</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bankTxModalBody"></div>
        </div>
    </div>
</div>

<?php $this->append('scriptBottom'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var invoiceId  = <?= (int)$invoice->id ?>;
    var csrfToken  = '<?= h($csrfToken) ?>';
    var setStatusUrl = '<?= $setStatusUrl ?>';
    var unassignUrl  = '<?= $unassignUrl ?>';
    var markPaidUrl   = '<?= $markPaidUrl ?>';
    var unmarkPaidUrl = '<?= $unmarkPaidUrl ?>';
    var addPaymentUrl    = '<?= $this->Url->build(['action' => 'addPayment', $invoice->id]) ?>';
    var bankTxUrl        = '<?= $this->Url->build(['action' => 'bankTxForCost', $invoice->id]) ?>';
    var deletePaymentBase = '<?= rtrim($this->Url->build(['action' => 'deletePayment']), '/') ?>';

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

    // ── Płatność: form oznaczenia jako zapłacona
    var payForm = document.getElementById('payFormForm');
    if (payForm) {
        payForm.addEventListener('submit', function(ev) {
            ev.preventDefault();
            var fd = new FormData(ev.target);
            var payload = {
                id: invoiceId,
                paid_at: fd.get('paid_at'),
                paid_amount: parseFloat(fd.get('paid_amount') || 0),
                payment_method: fd.get('payment_method') || ''
            };
            var btn = ev.target.querySelector('button[type=submit]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>zapisuję…';
            post(markPaidUrl, payload).then(function(data) {
                if (data.success) {
                    showToast(data.is_full_paid ? 'Oznaczono jako zapłaconą' : 'Zapisano częściową wpłatę', true);
                    setTimeout(function(){ location.reload(); }, 700);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="ri-check-line me-1"></i> Zapisz';
                    showToast(data.error || 'Błąd', false);
                }
            }).catch(function(e) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-check-line me-1"></i> Zapisz';
                showToast('Błąd: ' + e.message, false);
            });
        });
    }

    // ── Dodaj wpłatę ręczną
    var payAddForm = document.getElementById('payAddFormForm');
    if (payAddForm) {
        payAddForm.addEventListener('submit', function(ev) {
            ev.preventDefault();
            var fd = new FormData(ev.target);
            post(addPaymentUrl, {
                payment_date: fd.get('payment_date'),
                amount: parseFloat(fd.get('amount') || 0),
                payment_method: fd.get('payment_method'),
                note: fd.get('note') || ''
            }).then(function(data) {
                if (data.success) {
                    showToast('Wpłata dodana', true);
                    setTimeout(function(){ location.reload(); }, 600);
                } else {
                    showToast(data.error || 'Błąd', false);
                }
            }).catch(function(e) { showToast('Błąd: ' + e.message, false); });
        });
    }

    // ── Usuń wpłatę
    document.querySelectorAll('.btn-del-payment').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var pid = this.dataset.paymentId;
            if (!confirm('Usunąć tę wpłatę?')) return;
            post(deletePaymentBase + '/' + pid + '/delete', {}).then(function(data) {
                if (data.success) {
                    showToast('Wpłata usunięta', true);
                    setTimeout(function(){ location.reload(); }, 500);
                } else {
                    showToast(data.error || 'Błąd', false);
                }
            }).catch(function(e) { showToast('Błąd: ' + e.message, false); });
        });
    });

    // ── Dopnij przelew z banku
    var btnLinkBank = document.getElementById('btn-link-bank-tx');
    if (btnLinkBank) {
        btnLinkBank.addEventListener('click', function() {
            var modal = document.getElementById('bankTxModal');
            var bodyEl = document.getElementById('bankTxModalBody');
            bodyEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>ładowanie kandydatów…</div>';
            new bootstrap.Modal(modal).show();
            fetch(bankTxUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (!d.success) { bodyEl.innerHTML = '<div class="text-danger p-3">' + (d.error || 'Błąd') + '</div>'; return; }
                    if (!d.results || !d.results.length) {
                        bodyEl.innerHTML = '<div class="text-muted text-center py-3 fst-italic">Brak pasujących wypłat (direction=D) dla tego kontrahenta. Sprawdź czy banki są zaimportowane.</div>';
                        return;
                    }
                    var html = '<div class="alert alert-info py-2 small mb-2">'
                             + '<i class="ri-information-line me-1"></i> Brutto faktury: <strong>' + parseFloat(d.brutto).toFixed(2).replace('.', ',') + ' ' + d.currency + '</strong>. Kliknij wiersz aby dopiąć przelew jako wpłatę.</div>';
                    html += '<table class="table table-sm table-hover mb-0"><thead class="table-light"><tr>'
                          + '<th>Data</th><th class="text-end">Kwota</th><th>Kontrahent</th><th>Tytuł</th><th></th>'
                          + '</tr></thead><tbody>';
                    d.results.forEach(function(tx) {
                        var amt = parseFloat(tx.amount).toFixed(2).replace('.', ',');
                        var match = (tx.parsed_nip && '<i class="ri-checkbox-circle-fill text-success" title="NIP zgodny"></i> ') || '';
                        html += '<tr class="bank-tx-row" data-tx-id="' + tx.id + '" data-amount="' + tx.amount + '" data-date="' + tx.value_date + '" style="cursor:pointer">'
                              + '<td>' + tx.value_date + '</td>'
                              + '<td class="text-end fw-semibold">' + amt + ' ' + tx.currency + '</td>'
                              + '<td>' + match + (tx.party_name || '<em class="text-muted">brak</em>') + '</td>'
                              + '<td class="small text-muted text-truncate" style="max-width:280px" title="' + (tx.title || '').replace(/"/g, '&quot;') + '">' + (tx.title || '') + '</td>'
                              + '<td class="text-end"><button class="btn btn-xs btn-primary py-0 px-2">Dopnij</button></td>'
                              + '</tr>';
                    });
                    html += '</tbody></table>';
                    bodyEl.innerHTML = html;

                    bodyEl.querySelectorAll('.bank-tx-row').forEach(function(row) {
                        row.addEventListener('click', function() {
                            var txId = this.dataset.txId;
                            var amt  = parseFloat(this.dataset.amount);
                            var dt   = this.dataset.date;
                            post(addPaymentUrl, {
                                payment_date: dt,
                                amount: amt,
                                payment_method: 'transfer',
                                bank_transaction_id: txId,
                                note: 'Dopięty z banku'
                            }).then(function(data) {
                                if (data.success) {
                                    showToast('Przelew dopięty', true);
                                    setTimeout(function(){ location.reload(); }, 600);
                                } else {
                                    showToast(data.error || 'Błąd', false);
                                }
                            }).catch(function(e) { showToast('Błąd: ' + e.message, false); });
                        });
                    });
                })
                .catch(function(e) { bodyEl.innerHTML = '<div class="text-danger p-3">Błąd: ' + e.message + '</div>'; });
        });
    }

    // ── Cofnij oznaczenie zapłaty
    var btnUnmark = document.getElementById('btn-unmark-paid');
    if (btnUnmark) {
        btnUnmark.addEventListener('click', function() {
            if (!confirm('Cofnąć oznaczenie jako zapłacona? Status wróci do "Zweryfikowana".')) return;
            post(unmarkPaidUrl, { id: invoiceId }).then(function(data) {
                if (data.success) {
                    showToast('Cofnięto', true);
                    setTimeout(function(){ location.reload(); }, 600);
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

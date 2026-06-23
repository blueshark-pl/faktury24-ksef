<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\CostInvoice $invoice
 * @var array $costStatusLabels  1..9 => label
 * @var array $costStatusColors  1..9 => color name
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
$setCostStatusUrl = $this->Url->build(['action' => 'setCostStatus']);
$getLinesUrl     = $this->Url->build(['action' => 'getLines', $invoice->id]);
$saveLinesUrl    = $this->Url->build(['action' => 'saveLines', $invoice->id]);

// Workflow FV
$cs    = (int)($invoice->cost_status ?? 1);
$csLbl = $costStatusLabels[$cs] ?? '—';
$csCol = $costStatusColors[$cs] ?? 'secondary';

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
    <span class="badge bg-<?= h($csCol) ?>-subtle text-<?= h($csCol) ?> border" title="Workflow FV (status operatora)">
        <i class="ri-flow-chart me-1"></i><?= h($csLbl) ?>
    </span>
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

<!-- Workflow FV (status operatora) -->
<div class="col-12">
<div class="card border-<?= h($csCol) ?>-subtle">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <i class="ri-flow-chart text-<?= h($csCol) ?>"></i>
        Status FV (workflow)
        <span class="badge bg-<?= h($csCol) ?>-subtle text-<?= h($csCol) ?> border ms-2"><?= h($csLbl) ?></span>
        <div class="ms-auto">
            <div class="dropdown" id="costStatusDropdown" data-cost-invoice-id="<?= (int)$invoice->id ?>">
                <button type="button"
                        class="btn btn-sm btn-outline-<?= h($csCol) ?> dropdown-toggle"
                        data-bs-toggle="dropdown" data-current-status="<?= $cs ?>">
                    <i class="ri-edit-line me-1"></i> Zmień status
                </button>
                <ul class="dropdown-menu shadow-sm">
                    <?php foreach ($costStatusLabels as $sv => $sl):
                        $sc = $costStatusColors[$sv] ?? 'secondary';
                        $dotColor = match($sc) {
                            'warning' => '#f59e0b', 'primary' => '#3b82f6',
                            'success' => '#22c55e', 'info' => '#06b6d4',
                            'danger' => '#ef4444', 'dark' => '#1f2937',
                            'secondary' => '#6b7280', 'orange' => '#f97316',
                            default => '#9ca3af',
                        };
                    ?>
                    <li>
                        <button type="button"
                                class="dropdown-item d-flex align-items-center gap-2 cost-status-pick <?= $sv === $cs ? 'fw-semibold' : '' ?>"
                                data-status="<?= $sv ?>" data-cost-invoice-id="<?= (int)$invoice->id ?>">
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $dotColor ?>"></span>
                            <?= h($sl) ?>
                            <?php if ($sv === $cs): ?><i class="ri-check-line ms-auto text-success"></i><?php endif; ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="card-body py-2 px-3">
        <?php if ($cs == 7 && !empty($invoice->rejection_reason)): ?>
            <div class="alert alert-dark py-2 small mb-0">
                <strong><i class="ri-error-warning-line me-1"></i>Powód odrzucenia:</strong> <?= h($invoice->rejection_reason) ?>
            </div>
        <?php elseif ($cs == 7): ?>
            <div class="text-muted small fst-italic">Faktura odrzucona. Podaj powód:</div>
            <form id="rejectionReasonForm" class="d-flex gap-2 mt-1">
                <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Powód odrzucenia…">
                <button type="submit" class="btn btn-sm btn-dark"><i class="ri-save-line"></i> Zapisz</button>
            </form>
        <?php elseif ($cs >= 4 && $cs <= 6 && $pdStr): ?>
            <div class="text-muted small">
                <i class="ri-calendar-check-line me-1"></i>
                Termin płatności: <strong><?= h($pdStr) ?></strong>
                <?php if ($daysOverdue > 0): ?>
                    <span class="badge bg-danger ms-1">+<?= $daysOverdue ?> dni</span>
                <?php elseif ($daysToDue !== null): ?>
                    <span class="badge bg-info-subtle text-info border ms-1">za <?= $daysToDue ?> dni</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-muted small fst-italic">Wybierz status workflow z dropdown po prawej.</div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Dekretacja (pozycje + kategorie) -->
<div class="col-12">
<div class="card">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <i class="ri-table-line text-primary"></i>
        Dekretacja pozycji
        <span class="text-muted small ms-1">klasyfikacja kosztu</span>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="btn-dekretuj">
            <i class="ri-edit-line me-1"></i> Otwórz dekretację
        </button>
    </div>
    <div class="card-body p-0" id="dekretacjaSummary">
        <div class="text-muted text-center small fst-italic py-3">
            <i class="ri-loader-line me-1"></i>ładuje pozycje…
        </div>
    </div>
</div>
</div>

<!-- Modal dekretacji -->
<div class="modal fade" id="dekretacjaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">
                    <i class="ri-table-line me-1 text-primary"></i>
                    Dekretacja: <?= h($invoice->invoice_number ?: '#' . $invoice->id) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="dekretacjaModalBody">
                <div class="text-center p-4"><div class="spinner-border spinner-border-sm me-2"></div>ładuje…</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-lines">
                    <i class="ri-save-line me-1"></i> Zapisz dekretację
                </button>
            </div>
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

<!-- Activity log + notatki -->
<div class="col-12">
<div class="card">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <i class="ri-history-line text-primary"></i>
        Notatki i historia
    </div>
    <div class="card-body py-2">
        <form id="addNoteForm" class="d-flex gap-2 mb-2">
            <select name="note_type" class="form-select form-select-sm" style="width:130px">
                <option value="note">Notatka</option>
                <option value="phone_call">Rozmowa</option>
                <option value="email">Email</option>
                <option value="reminder">Przypomnienie</option>
            </select>
            <input type="text" name="body" class="form-control form-control-sm" placeholder="Dodaj komentarz…" required>
            <button type="submit" class="btn btn-sm btn-primary"><i class="ri-add-line"></i></button>
        </form>
        <div id="notesList" class="list-group list-group-flush" style="max-height:400px;overflow-y:auto">
            <div class="text-muted small fst-italic text-center py-3">
                <i class="ri-loader-line me-1"></i>ładowanie…
            </div>
        </div>
    </div>
</div>
</div>

<!-- Powiązane zlecenia -->
<div class="col-12">
<div class="card">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <i class="ri-links-line text-primary"></i>
        Powiązane zlecenia transportowe
        <span class="badge bg-primary-subtle text-primary"><?= count($invoice->speed_orders ?? []) ?></span>
        <button type="button" class="btn btn-sm btn-primary ms-auto" id="btn-assign-order">
            <i class="ri-add-line me-1"></i> Powiąż zlecenie
        </button>
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

<!-- Modal: wybór zlecenia transportowego -->
<div class="modal fade" id="assignOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title"><i class="ri-truck-line me-1 text-primary"></i> Powiąż zlecenie transportowe z fakturą</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" id="assignOrderSearch"
                           placeholder="Szukaj po symbolu, kliencie, NIP, miejscu załadunku/rozładunku…" autocomplete="off">
                    <div class="form-text small">
                        <i class="ri-information-line me-1"></i>Pokazuje ostatnie 30 zleceń. Wpisz frazę aby zawęzić.
                    </div>
                </div>
                <div id="assignOrderResults">
                    <div class="text-center py-3 text-muted small"><div class="spinner-border spinner-border-sm me-2"></div>ładuję…</div>
                </div>
            </div>
        </div>
    </div>
</div>

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
    var getLinesUrl   = '<?= $getLinesUrl ?>';
    var saveLinesUrl  = '<?= $saveLinesUrl ?>';
    var aiSuggestUrl  = '<?= $this->Url->build(['action' => 'aiSuggestLines', $invoice->id]) ?>';
    var getNotesUrl   = '<?= $this->Url->build(['action' => 'getNotes', $invoice->id]) ?>';
    var addNoteUrl    = '<?= $this->Url->build(['action' => 'addNote', $invoice->id]) ?>';
    var deleteNoteBase= '<?= rtrim($this->Url->build(['action' => 'deleteNote']), '/') ?>';
    var addPaymentUrl    = '<?= $this->Url->build(['action' => 'addPayment', $invoice->id]) ?>';
    var assignOrderUrl   = '<?= $this->Url->build(['action' => 'assignOrder']) ?>';
    var ordersSearchUrl  = '<?= $this->Url->build(['action' => 'searchOrdersForCost', $invoice->id]) ?>';
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

    // ── Dekretacja: lazy summary + modal ───────────────────────────
    var dekretacjaLines = [];
    var dekretacjaCategories = [];

    function fmtNum(v) { return v !== null && v !== undefined && v !== '' ? parseFloat(v).toFixed(2).replace('.', ',') : ''; }
    function escHtml(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function loadLines() {
        fetch(getLinesUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success) { renderSummaryError(d.error || 'Błąd'); return; }
                dekretacjaLines = d.lines || [];
                dekretacjaCategories = d.categories || [];
                renderSummary(d);
            })
            .catch(function(e) { renderSummaryError(e.message); });
    }

    function renderSummary(d) {
        var box = document.getElementById('dekretacjaSummary');
        if (!d.lines || !d.lines.length) {
            box.innerHTML = '<div class="text-muted text-center small fst-italic py-3">'
                + '<i class="ri-inbox-line me-1"></i>Brak pozycji. Kliknij <strong>Otwórz dekretację</strong> aby dodać.'
                + '</div>';
            return;
        }
        var html = '<table class="table table-sm mb-0"><thead class="table-light"><tr>'
                 + '<th class="ps-3">#</th><th>Nazwa</th><th class="text-end">Netto</th>'
                 + '<th class="text-end">VAT</th><th class="text-end">Brutto</th>'
                 + '<th>Kategoria</th><th>Notatka</th>'
                 + '</tr></thead><tbody>';
        d.lines.forEach(function(l, idx) {
            html += '<tr>'
                  + '<td class="ps-3 text-muted">' + (idx + 1) + '</td>'
                  + '<td>' + escHtml(l.name) + '</td>'
                  + '<td class="text-end">' + fmtNum(l.net_amount) + '</td>'
                  + '<td class="text-end">' + fmtNum(l.vat_amount) + '</td>'
                  + '<td class="text-end fw-semibold">' + fmtNum(l.gross_amount) + '</td>'
                  + '<td>' + (l.cost_category_name ? '<span class="badge bg-info-subtle text-info border" style="font-size:.7rem">' + escHtml(l.cost_category_name) + '</span>' : '<span class="text-muted small">—</span>') + '</td>'
                  + '<td class="small text-muted">' + escHtml(l.note || '') + '</td>'
                  + '</tr>';
        });
        html += '</tbody></table>';
        if (d.auto_from_ksef) {
            html = '<div class="alert alert-warning py-1 px-2 small mb-0 rounded-0">'
                 + '<i class="ri-information-line me-1"></i>Pozycje wczytane automatycznie z faktury KSeF — '
                 + 'kliknij <strong>Otwórz dekretację</strong> aby przypisać kategorie i zapisać.'
                 + '</div>' + html;
        }
        box.innerHTML = html;
    }

    function renderSummaryError(msg) {
        document.getElementById('dekretacjaSummary').innerHTML =
            '<div class="text-danger small p-3"><i class="ri-error-warning-line me-1"></i>' + escHtml(msg) + '</div>';
    }

    // Modal dekretacji
    document.getElementById('btn-dekretuj').addEventListener('click', function() {
        var body = document.getElementById('dekretacjaModalBody');
        body.innerHTML = renderDekretacjaForm();
        wireDekretacjaForm();
        new bootstrap.Modal(document.getElementById('dekretacjaModal')).show();
    });

    function renderDekretacjaForm() {
        var catOptions = dekretacjaCategories.map(function(c) {
            return '<option value="' + escHtml(c.id) + '" data-name="' + escHtml(c.name) + '">' + escHtml(c.name) + '</option>';
        }).join('');

        var html = '<div class="alert alert-info py-2 small mb-2 d-flex align-items-center gap-2">'
                 + '<i class="ri-information-line"></i>'
                 + '<span>Każda pozycja może mieć kategorię i notę dekretacyjną.</span>'
                 + '<button type="button" class="btn btn-sm btn-primary ms-auto" id="btn-ai-suggest-all">'
                 +   '<i class="ri-sparkling-2-line me-1"></i>🪄 AI: zasugeruj kategorie'
                 + '</button>'
                 + '</div>';
        html += '<table class="table table-sm align-middle" id="dekretacjaTable"><thead class="table-light"><tr>'
              + '<th style="width:32px">#</th><th>Nazwa pozycji</th>'
              + '<th class="text-end" style="width:100px">Netto</th>'
              + '<th class="text-end" style="width:80px">VAT %</th>'
              + '<th class="text-end" style="width:100px">VAT</th>'
              + '<th class="text-end" style="width:100px">Brutto</th>'
              + '<th style="width:200px">Kategoria</th>'
              + '<th>Notatka</th><th style="width:60px"></th>'
              + '</tr></thead><tbody id="dekretacjaTbody"></tbody></table>';
        html += '<button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-line"><i class="ri-add-line me-1"></i>Dodaj pozycję</button>';
        // Przechowujemy catOptions w window — żaden HTML embed nie jest potrzebny
        window.__dekrCatOptions = catOptions;
        return html;
    }

    function wireDekretacjaForm() {
        var tbody = document.getElementById('dekretacjaTbody');
        var catOptions = window.__dekrCatOptions || '';

        function addRow(line) {
            line = line || { name: '', quantity: '', unit: '', unit_price: '', net_amount: '', vat_rate: '', vat_amount: '', gross_amount: '', cost_category_id: '', cost_category_name: '', note: '' };
            var idx = tbody.children.length;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="text-muted">' + (idx + 1) + '</td>'
                + '<td><input type="text" class="form-control form-control-sm" name="name" value="' + escHtml(line.name) + '" required></td>'
                + '<td><input type="number" step="0.01" class="form-control form-control-sm text-end" name="net_amount" value="' + (line.net_amount ?? '') + '"></td>'
                + '<td><input type="text" class="form-control form-control-sm text-end" name="vat_rate" value="' + escHtml(line.vat_rate || '') + '" placeholder="23"></td>'
                + '<td><input type="number" step="0.01" class="form-control form-control-sm text-end" name="vat_amount" value="' + (line.vat_amount ?? '') + '"></td>'
                + '<td><input type="number" step="0.01" class="form-control form-control-sm text-end" name="gross_amount" value="' + (line.gross_amount ?? '') + '"></td>'
                + '<td><select class="form-select form-select-sm" name="cost_category_id">'
                +     '<option value="">— wybierz —</option>' + catOptions
                + '</select></td>'
                + '<td><input type="text" class="form-control form-control-sm" name="note" value="' + escHtml(line.note || '') + '"></td>'
                + '<td class="text-nowrap">'
                +   '<button type="button" class="btn btn-xs btn-outline-primary btn-ai-suggest-row me-1" title="🪄 AI: sugeruj kategorię dla tej pozycji">'
                +     '<i class="ri-sparkling-2-line"></i>'
                +   '</button>'
                +   '<button type="button" class="btn btn-xs btn-outline-danger btn-del-row" title="Usuń">'
                +     '<i class="ri-delete-bin-line"></i>'
                +   '</button>'
                + '</td>';
            tbody.appendChild(tr);
            // Ustaw selected na kategorii
            if (line.cost_category_id) {
                var sel = tr.querySelector('select[name=cost_category_id]');
                if (sel) sel.value = line.cost_category_id;
            }
        }

        dekretacjaLines.forEach(addRow);
        if (dekretacjaLines.length === 0) addRow();

        document.getElementById('btn-add-line').addEventListener('click', function() { addRow(); });
        tbody.addEventListener('click', function(ev) {
            var del = ev.target.closest('.btn-del-row');
            if (del) { del.closest('tr').remove(); return; }
            var sug = ev.target.closest('.btn-ai-suggest-row');
            if (sug) {
                ev.preventDefault();
                aiSuggestForRow(sug.closest('tr'));
            }
        });

        // Bulk: AI sugeruj dla wszystkich
        var btnAll = document.getElementById('btn-ai-suggest-all');
        if (btnAll) {
            btnAll.addEventListener('click', function() {
                aiSuggestAll();
            });
        }
    }

    function collectLinesFromForm() {
        var rows = document.querySelectorAll('#dekretacjaTbody tr');
        var out = [];
        rows.forEach(function(tr, idx) {
            var get = function(name) { var el = tr.querySelector('[name="' + name + '"]'); return el ? el.value : ''; };
            out.push({
                idx: idx,
                name: get('name'),
                net_amount: get('net_amount'),
                vat_amount: get('vat_amount'),
                gross_amount: get('gross_amount'),
            });
        });
        return out;
    }

    function applySuggestion(tr, sug) {
        // Ustaw select kategorii
        var sel = tr.querySelector('select[name=cost_category_id]');
        if (!sel) return;
        if (sug.cost_category_id) {
            sel.value = sug.cost_category_id;
        } else if (sug.cost_category_name) {
            // gdy AI zwróciło tylko nazwę — znajdź po data-name
            for (var i = 0; i < sel.options.length; i++) {
                if ((sel.options[i].dataset.name || sel.options[i].textContent.trim()) === sug.cost_category_name) {
                    sel.selectedIndex = i;
                    break;
                }
            }
        }
        // Wskazówka w notatce: confidence + reasoning (gdy puste)
        var noteEl = tr.querySelector('input[name=note]');
        if (noteEl && !noteEl.value.trim() && sug.reasoning) {
            noteEl.value = '🪄 ' + sug.reasoning + ' (' + sug.confidence + '%)';
        }
        // Wizualne highlight
        tr.style.background = '#ecfdf5';
        setTimeout(function() { tr.style.background = ''; }, 1500);
    }

    function aiSuggestAll() {
        var btn = document.getElementById('btn-ai-suggest-all');
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>AI analizuje…';

        var lines = collectLinesFromForm();
        var fd = new FormData();
        lines.forEach(function(l, idx) {
            Object.keys(l).forEach(function(k) {
                fd.append('lines[' + idx + '][' + k + ']', l[k] === null || l[k] === undefined ? '' : l[k]);
            });
        });

        fetch(aiSuggestUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.text().then(function(txt) {
            var d; try { d = JSON.parse(txt); } catch (e) { throw new Error('Nie JSON: ' + txt.substring(0, 100)); }
            return d;
        }); })
        .then(function(d) {
            btn.disabled = false;
            btn.innerHTML = orig;
            if (!d.success) { showToast(d.error || 'Błąd AI', false); return; }
            var rows = document.querySelectorAll('#dekretacjaTbody tr');
            (d.suggestions || []).forEach(function(s) {
                var tr = rows[s.line_index];
                if (tr) applySuggestion(tr, s);
            });
            var msg = 'AI zasugerowało ' + (d.suggestions || []).length + ' kategorii';
            if (d.history_count > 0) msg += ' · historia: ' + d.history_count + ' poprzednich dekretacji';
            showToast(msg, true);
        })
        .catch(function(e) {
            btn.disabled = false;
            btn.innerHTML = orig;
            showToast('Błąd AI: ' + e.message, false);
        });
    }

    function aiSuggestForRow(tr) {
        // Wykonaj suggest dla jednej linii (przekazujemy tylko ją)
        var get = function(name) { var el = tr.querySelector('[name="' + name + '"]'); return el ? el.value : ''; };
        var fd = new FormData();
        fd.append('lines[0][idx]', 0);
        fd.append('lines[0][name]', get('name'));
        fd.append('lines[0][net_amount]', get('net_amount'));
        fd.append('lines[0][gross_amount]', get('gross_amount'));

        var btn = tr.querySelector('.btn-ai-suggest-row');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.7rem;height:.7rem"></span>'; }

        fetch(aiSuggestUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ri-sparkling-2-line"></i>'; }
            if (!d.success) { showToast(d.error || 'Błąd AI', false); return; }
            var sug = (d.suggestions || [])[0];
            if (sug) {
                applySuggestion(tr, sug);
                showToast('Sugestia: ' + sug.cost_category_name + ' (' + sug.confidence + '%)', true);
            } else {
                showToast('AI nie zaproponowało kategorii', 'warning');
            }
        })
        .catch(function(e) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ri-sparkling-2-line"></i>'; }
            showToast('Błąd: ' + e.message, false);
        });
    }

    // Zapisz dekretację
    document.getElementById('btn-save-lines').addEventListener('click', function() {
        var rows = document.querySelectorAll('#dekretacjaTbody tr');
        var lines = [];
        rows.forEach(function(tr) {
            var get = function(name) { var el = tr.querySelector('[name="' + name + '"]'); return el ? el.value : ''; };
            var getOpt = function(name) {
                var s = tr.querySelector('select[name="' + name + '"]');
                if (!s) return '';
                var opt = s.options[s.selectedIndex];
                return opt ? (opt.dataset.name || opt.textContent.trim()) : '';
            };
            if (!get('name').trim()) return;
            lines.push({
                name: get('name'),
                net_amount: get('net_amount'),
                vat_rate: get('vat_rate'),
                vat_amount: get('vat_amount'),
                gross_amount: get('gross_amount'),
                cost_category_id: get('cost_category_id'),
                cost_category_name: getOpt('cost_category_id'),
                note: get('note'),
            });
        });

        var btn = this;
        btn.disabled = true;
        var orig = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>zapisuję…';

        var fd = new FormData();
        fd.append('lines_json', JSON.stringify(lines)); // backup
        lines.forEach(function(l, idx) {
            Object.keys(l).forEach(function(k) {
                fd.append('lines[' + idx + '][' + k + ']', l[k] === null || l[k] === undefined ? '' : l[k]);
            });
        });

        fetch(saveLinesUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        })
        .then(function(r) { return r.text().then(function(txt) {
            var d; try { d = JSON.parse(txt); } catch (e) { throw new Error('Nie JSON: ' + txt.substring(0, 100)); }
            return d;
        }); })
        .then(function(d) {
            btn.disabled = false;
            btn.innerHTML = orig;
            if (d.success) {
                showToast('Zapisano ' + d.saved + ' pozycji', true);
                bootstrap.Modal.getInstance(document.getElementById('dekretacjaModal'))?.hide();
                loadLines();
            } else {
                showToast(d.error || 'Błąd', false);
            }
        })
        .catch(function(e) {
            btn.disabled = false;
            btn.innerHTML = orig;
            showToast('Błąd: ' + e.message, false);
        });
    });

    // Auto-load przy załadowaniu widoku
    loadLines();

    // ── Activity log / notatki ──────────────────────────────────────
    function loadNotes() {
        var listEl = document.getElementById('notesList');
        if (!listEl) return;
        fetch(getNotesUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success) { listEl.innerHTML = '<div class="text-danger small p-2">Błąd</div>'; return; }
                if (!d.notes || !d.notes.length) {
                    listEl.innerHTML = '<div class="text-muted small fst-italic text-center py-3"><i class="ri-chat-off-line me-1"></i>Brak notatek. Dodaj pierwszą powyżej.</div>';
                    return;
                }
                var typeMeta = {
                    'note':       { col: 'secondary', lbl: 'Notatka',       ico: 'ri-chat-1-line' },
                    'system':     { col: 'light',     lbl: 'System',        ico: 'ri-settings-3-line' },
                    'reminder':   { col: 'warning',   lbl: 'Przypomnienie', ico: 'ri-mail-send-line' },
                    'phone_call': { col: 'info',      lbl: 'Rozmowa',       ico: 'ri-phone-line' },
                    'email':      { col: 'primary',   lbl: 'Email',         ico: 'ri-mail-line' }
                };
                var html = '';
                d.notes.forEach(function(n) {
                    var m = typeMeta[n.note_type] || typeMeta['note'];
                    html += '<div class="list-group-item py-2 px-3" data-note-id="' + escHtml(n.id) + '">';
                    html += '<div class="d-flex align-items-center gap-2 mb-1">';
                    html += '<i class="' + m.ico + ' text-' + m.col + '"></i>';
                    html += '<span class="badge bg-' + m.col + '-subtle text-' + m.col + ' border" style="font-size:.62rem">' + m.lbl + '</span>';
                    html += '<span class="ms-auto small text-muted">' + escHtml(n.user_name) + ' · ' + escHtml(n.created) + '</span>';
                    if (n.note_type !== 'system') {
                        html += '<button type="button" class="btn btn-xs btn-link text-danger p-0 ms-1 btn-del-note" style="line-height:1" title="Usuń"><i class="ri-close-line"></i></button>';
                    }
                    html += '</div>';
                    html += '<div class="small">' + escHtml(n.body) + '</div>';
                    html += '</div>';
                });
                listEl.innerHTML = html;
            })
            .catch(function(e) { listEl.innerHTML = '<div class="text-danger small p-2">Błąd: ' + e.message + '</div>'; });
    }

    var noteForm = document.getElementById('addNoteForm');
    if (noteForm) {
        noteForm.addEventListener('submit', function(ev) {
            ev.preventDefault();
            var fd = new FormData(ev.target);
            fetch(addNoteUrl, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    ev.target.elements.body.value = '';
                    loadNotes();
                    showToast('Notatka dodana', true);
                } else {
                    showToast(d.error || 'Błąd', false);
                }
            });
        });
    }

    document.getElementById('notesList').addEventListener('click', function(ev) {
        var del = ev.target.closest('.btn-del-note');
        if (!del) return;
        var item = del.closest('[data-note-id]');
        if (!confirm('Usunąć tę notatkę?')) return;
        var nid = item.dataset.noteId;
        fetch(deleteNoteBase + '/' + nid + '/delete', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) { item.remove(); showToast('Usunięto', true); }
            else showToast(d.error || 'Błąd', false);
        });
    });

    loadNotes();

    // ── Workflow FV — zmiana cost_status przez AJAX
    document.addEventListener('click', function(ev) {
        var pick = ev.target.closest('.cost-status-pick');
        if (!pick) return;
        ev.preventDefault();
        var newStatus = parseInt(pick.dataset.status, 10);
        var id = parseInt(pick.dataset.costInvoiceId, 10);
        if (!id || newStatus < 1 || newStatus > 9) return;

        // Jeśli wybrano 7 (Odrzucona) — zapytaj o powód
        var payload = { id: id, cost_status: newStatus };
        if (newStatus === 7) {
            var reason = prompt('Powód odrzucenia faktury:', '');
            if (reason === null) return;
            payload.rejection_reason = reason;
        }

        fetch(setCostStatusUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload)
        })
        .then(function(r) { return r.text().then(function(txt) {
            var d; try { d = JSON.parse(txt); } catch (e) { throw new Error('Nie JSON: ' + txt.substring(0, 100)); }
            return d;
        }); })
        .then(function(d) {
            if (d.success) {
                showToast('Status zmieniony: ' + d.cost_status_label, true);
                setTimeout(function(){ location.reload(); }, 500);
            } else {
                showToast(d.error || 'Błąd', false);
            }
        })
        .catch(function(e) { showToast('Błąd: ' + e.message, false); });
    });

    // ── Form powodu odrzucenia (gdy cost_status = 7 i brak reason)
    var rejForm = document.getElementById('rejectionReasonForm');
    if (rejForm) {
        rejForm.addEventListener('submit', function(ev) {
            ev.preventDefault();
            var reason = ev.target.elements.rejection_reason.value.trim();
            if (!reason) return;
            fetch(setCostStatusUrl, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id: invoiceId, cost_status: 7, rejection_reason: reason })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) location.reload();
                else showToast(d.error || 'Błąd', false);
            });
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

    // ── Powiąż zlecenie z FK kosztową ──────────────────────────────
    var assignModal;
    var assignSearchTimer;

    function renderOrderResults(d) {
        var box = document.getElementById('assignOrderResults');
        var rows = d.results || [];
        if (!rows.length) {
            box.innerHTML = '<div class="text-muted text-center fst-italic py-4"><i class="ri-search-line me-1"></i>Brak pasujących zleceń.</div>';
            return;
        }
        var fkNip = (d.invoice_contractor_nip || '').replace(/\D/g, '');
        var html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr>'
                 + '<th class="ps-3">Symbol</th><th>Klient</th><th>Trasa</th>'
                 + '<th>Data wyst.</th><th>Rozładunek</th>'
                 + '<th class="text-end">Netto</th>'
                 + '<th></th>'
                 + '</tr></thead><tbody>';
        rows.forEach(function(o) {
            var nipMatch = fkNip && (o.buyer_nip || '').replace(/\D/g, '') === fkNip;
            html += '<tr class="order-pick-row" data-order-id="' + o.id + '" data-symbol="' + escHtml(o.symbol) + '" style="cursor:pointer">'
                  + '<td class="ps-3 fw-semibold">' + escHtml(o.symbol)
                  +   (nipMatch ? ' <i class="ri-checkbox-circle-fill text-success" title="NIP klienta zgodny"></i>' : '')
                  + '</td>'
                  + '<td><div>' + escHtml(o.buyer_name || '—') + '</div>'
                  +     (o.buyer_nip ? '<div class="small text-muted">' + escHtml(o.buyer_nip) + '</div>' : '')
                  + '</td>'
                  + '<td class="small">' + escHtml(o.route || '—') + '</td>'
                  + '<td class="small">' + escHtml(o.date_doc || '') + '</td>'
                  + '<td class="small">' + escHtml(o.date_delivery || '') + '</td>'
                  + '<td class="text-end fw-semibold">' + (o.netto !== null ? fmtNum(o.netto) + ' ' + escHtml(o.currency) : '—') + '</td>'
                  + '<td class="text-end"><button class="btn btn-xs btn-primary py-0 px-2 btn-pick-order">Powiąż</button></td>'
                  + '</tr>';
        });
        html += '</tbody></table></div>';
        box.innerHTML = html;
    }

    function loadOrders(q) {
        var box = document.getElementById('assignOrderResults');
        box.innerHTML = '<div class="text-center py-3 text-muted small"><div class="spinner-border spinner-border-sm me-2"></div>ładuję…</div>';
        var url = ordersSearchUrl + (q ? '?q=' + encodeURIComponent(q) : '');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success) {
                    box.innerHTML = '<div class="text-danger small p-3">' + escHtml(d.error || 'Błąd') + '</div>';
                    return;
                }
                renderOrderResults(d);
            })
            .catch(function(e) { box.innerHTML = '<div class="text-danger small p-3">' + escHtml(e.message) + '</div>'; });
    }

    var btnAssign = document.getElementById('btn-assign-order');
    if (btnAssign) {
        btnAssign.addEventListener('click', function() {
            assignModal = new bootstrap.Modal(document.getElementById('assignOrderModal'));
            document.getElementById('assignOrderSearch').value = '';
            loadOrders('');
            assignModal.show();
        });
    }

    var assignSearch = document.getElementById('assignOrderSearch');
    if (assignSearch) {
        assignSearch.addEventListener('input', function() {
            clearTimeout(assignSearchTimer);
            var v = this.value.trim();
            assignSearchTimer = setTimeout(function() { loadOrders(v); }, 280);
        });
    }

    document.getElementById('assignOrderResults').addEventListener('click', function(ev) {
        var btn = ev.target.closest('.btn-pick-order') || ev.target.closest('.order-pick-row');
        if (!btn) return;
        var row = btn.closest('.order-pick-row') || btn;
        var ordId = parseInt(row.dataset.orderId, 10);
        var symbol = row.dataset.symbol || '';
        if (!ordId) return;

        post(assignOrderUrl, { cost_invoice_id: invoiceId, speed_order_id: ordId })
        .then(function(d) {
            if (d.success) {
                showToast('Powiązano: ' + symbol, true);
                setTimeout(function(){ location.reload(); }, 500);
            } else {
                showToast(d.error || 'Błąd', false);
            }
        })
        .catch(function(e) { showToast('Błąd: ' + e.message, false); });
    });

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

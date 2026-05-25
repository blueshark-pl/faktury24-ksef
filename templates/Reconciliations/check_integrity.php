<?php
/**
 * @var \App\View\AppView $this
 * @var array $orphanPayments
 * @var array $txsWithoutAlloc
 * @var array $orphanAllocs
 */

$this->assign('title', 'Sprawdzenie integralności — rozliczenia');

$totalIssues = count($orphanPayments) + count($txsWithoutAlloc) + count($orphanAllocs);
$fdate = static function ($d): string {
    if ($d === null) return '—';
    if ($d instanceof \DateTimeInterface) return $d->format('Y-m-d');
    if (is_object($d) && method_exists($d, 'format')) return $d->format('Y-m-d');
    return substr((string)$d, 0, 10);
};
?>
<div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-3 mb-3">
        <h4 class="mb-0">
            <i class="ri-shield-check-line text-primary me-1"></i>
            Sprawdzenie integralności rozliczeń
        </h4>
        <span class="badge bg-<?= $totalIssues > 0 ? 'danger' : 'success' ?>-subtle text-<?= $totalIssues > 0 ? 'danger' : 'success' ?> border">
            <?= $totalIssues > 0 ? $totalIssues . ' ' . __n('problem', 'problemy', $totalIssues) : 'OK — brak problemów' ?>
        </span>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary ms-auto">
            <i class="ri-arrow-left-line me-1"></i>Powrót do rozliczeń
        </a>
    </div>

    <div class="alert alert-info py-2 small mb-3">
        <i class="ri-information-line me-1"></i>
        Skanuje łańcuch <code>bank_transactions</code> ↔ <code>bank_transaction_allocations</code> ↔ <code>invoice_payments</code>.
        Wykrywa sieroty (rekordy bez powiązania) i pomaga je naprawić — łącząc po <code>invoice_id</code>, kwocie i dacie.
    </div>

    <?php if ($totalIssues > 0): ?>
        <?= $this->Form->create(null, ['url' => ['action' => 'fixIntegrity']]) ?>
            <button type="submit" class="btn btn-warning mb-3"
                    onclick="return confirm('Spróbować naprawić wszystkie wykryte problemy? Akcja utworzy brakujące alokacje i payment-y.')">
                <i class="ri-tools-line me-1"></i>
                Napraw wszystkie (<?= $totalIssues ?>)
            </button>
        <?= $this->Form->end() ?>
    <?php endif; ?>

    <!-- A: orphan invoice_payments -->
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <strong>A. Wpłaty bez alokacji</strong>
            <span class="badge bg-secondary-subtle text-secondary"><?= count($orphanPayments) ?></span>
            <small class="text-muted ms-2">— <code>invoice_payments</code> z <code>payment_method='transfer'</code> ale <code>bank_transaction_allocation_id IS NULL</code></small>
        </div>
        <?php if (empty($orphanPayments)): ?>
            <div class="card-body py-2 small text-muted">Brak problemów</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>ID wpłaty</th>
                            <th>Faktura</th>
                            <th class="text-end">Kwota</th>
                            <th>Waluta</th>
                            <th>Typ</th>
                            <th>Data</th>
                            <th>Opis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orphanPayments as $p): ?>
                            <tr>
                                <td><code style="font-size:.7rem"><?= h(substr($p->id, 0, 8)) ?>…</code></td>
                                <td><code style="font-size:.7rem"><?= h(substr($p->invoice_id, 0, 8)) ?>…</code></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$p->amount, 2, ',', ' ') ?></td>
                                <td><?= h(strtoupper($p->currency ?? 'PLN')) ?></td>
                                <td><span class="badge bg-light text-dark"><?= h($p->payment_type ?? 'gross') ?></span></td>
                                <td class="text-nowrap"><?= h($fdate($p->payment_date)) ?></td>
                                <td class="text-muted" style="font-size:.72rem"><?= h(mb_substr($p->description ?? '', 0, 60)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- B: matched bank_txs without allocation -->
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <strong>B. Przelewy potwierdzone bez alokacji</strong>
            <span class="badge bg-secondary-subtle text-secondary"><?= count($txsWithoutAlloc) ?></span>
            <small class="text-muted ms-2">— <code>bank_transactions</code> z <code>match_status='matched'</code>, <code>match_confidence>=100</code>, ale brak rekordu w <code>bank_transaction_allocations</code></small>
        </div>
        <?php if (empty($txsWithoutAlloc)): ?>
            <div class="card-body py-2 small text-muted">Brak problemów</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>ID przelewu</th>
                            <th>Faktura</th>
                            <th class="text-end">Kwota</th>
                            <th>Waluta</th>
                            <th>Data</th>
                            <th>Nadawca</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($txsWithoutAlloc as $t): ?>
                            <tr>
                                <td><code style="font-size:.7rem"><?= h(substr($t->id, 0, 8)) ?>…</code></td>
                                <td><code style="font-size:.7rem"><?= h(substr($t->invoice_id, 0, 8)) ?>…</code></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$t->amount, 2, ',', ' ') ?></td>
                                <td><?= h(strtoupper($t->currency ?? 'PLN')) ?></td>
                                <td class="text-nowrap"><?= h($fdate($t->value_date)) ?></td>
                                <td class="text-muted text-truncate" style="max-width:240px"><?= h(mb_substr($t->party_name ?? '', 0, 40)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- C: orphan allocations (no invoice_payment_id) -->
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <strong>C. Alokacje bez back-linku do wpłaty</strong>
            <span class="badge bg-secondary-subtle text-secondary"><?= count($orphanAllocs) ?></span>
            <small class="text-muted ms-2">— <code>bank_transaction_allocations</code> z <code>invoice_payment_id IS NULL</code></small>
        </div>
        <?php if (empty($orphanAllocs)): ?>
            <div class="card-body py-2 small text-muted">Brak problemów</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>ID alokacji</th>
                            <th>Przelew</th>
                            <th>Faktura</th>
                            <th class="text-end">Kwota</th>
                            <th>Waluta</th>
                            <th>Typ</th>
                            <th>Utworzono</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orphanAllocs as $a): ?>
                            <tr>
                                <td><code style="font-size:.7rem"><?= h(substr($a->id, 0, 8)) ?>…</code></td>
                                <td><code style="font-size:.7rem"><?= h(substr($a->bank_transaction_id, 0, 8)) ?>…</code></td>
                                <td><code style="font-size:.7rem"><?= h(substr($a->invoice_id, 0, 8)) ?>…</code></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$a->allocated_amount, 2, ',', ' ') ?></td>
                                <td><?= h(strtoupper($a->currency ?? 'PLN')) ?></td>
                                <td><span class="badge bg-light text-dark"><?= h($a->allocation_type ?? 'gross') ?></span></td>
                                <td class="text-nowrap"><?= h($fdate($a->created)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalIssues === 0): ?>
        <div class="alert alert-success py-2 small">
            <i class="ri-checkbox-circle-line me-1"></i>
            Wszystkie powiązania są spójne — bank_transaction ↔ allocation ↔ invoice_payment.
        </div>
    <?php endif; ?>
</div>

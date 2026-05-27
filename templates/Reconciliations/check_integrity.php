<?php
/**
 * @var \App\View\AppView $this
 * @var array $orphanPayments
 * @var array $txsWithoutAlloc
 * @var array $orphanAllocs
 * @var array $currencyMismatches
 * @var array $autoMatched
 * @var array $stats
 */

$this->assign('title', 'Sprawdzenie integralności — rozliczenia');

$totalIssues = count($orphanPayments) + count($txsWithoutAlloc) + count($orphanAllocs)
             + count($currencyMismatches) + count($autoMatched);
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

    <!-- Statystyki stanu w bazie -->
    <div class="card mb-3 bg-light">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-3 small">
                <div><strong>Σ matched:</strong> <?= (int)$stats['total_matched'] ?></div>
                <div><strong>matched + invoice_id:</strong> <?= (int)$stats['matched_with_invoice'] ?></div>
                <div class="text-success"><strong>Ręcznie (confidence=100):</strong> <?= (int)$stats['manual_matched'] ?></div>
                <div class="text-warning"><strong>Auto (confidence&lt;100):</strong> <?= (int)$stats['auto_matched'] ?></div>
                <div class="text-muted"><strong>proposed:</strong> <?= (int)$stats['proposed'] ?></div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php if ($totalIssues > 0): ?>
            <?= $this->Form->create(null, ['url' => ['action' => 'fixIntegrity'], 'class' => 'mb-0']) ?>
                <button type="submit" class="btn btn-warning"
                        onclick="return confirm('Spróbować naprawić wszystkie wykryte problemy? Akcja utworzy brakujące alokacje i payment-y.')">
                    <i class="ri-tools-line me-1"></i>
                    Napraw wszystkie (<?= $totalIssues ?>)
                </button>
            <?= $this->Form->end() ?>
        <?php endif; ?>

        <?= $this->Form->create(null, ['url' => ['action' => 'refreshAllPaymentStates'], 'class' => 'mb-0']) ?>
            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('Przeliczyć paymentstate/alreadypaid/remaining dla WSZYSTKICH faktur w bazie?\n\nUżywa currency-aware logiki — naprawia ujemne remaining z faktur walutowych. Bezpieczne, tylko aktualizuje cached pola.')">
                <i class="ri-refresh-line me-1"></i>
                Przelicz alreadypaid/remaining wszystkich faktur
            </button>
        <?= $this->Form->end() ?>

        <?= $this->Form->create(null, ['url' => ['action' => 'backfillIbanHistory'], 'class' => 'mb-0']) ?>
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('Wypełnić contractor_iban_history z istniejących potwierdzonych alokacji?\n\nAnalizuje wszystkie bank_transaction_allocations + invoice_contractors w bazie i buduje mapę IBAN↔NIP. Dla każdej pary inkrementuje confirmed_count. Bezpieczne — tylko INSERT/UPDATE w nowej tabeli.')">
                <i class="ri-database-2-line me-1"></i>
                Backfill IBAN history (z historycznych matchów)
            </button>
        <?= $this->Form->end() ?>
    </div>

    <?php
        $renderFixBtn = function (string $type, string $id) {
            return '<button type="button" class="btn btn-sm btn-warning py-0 px-2 btn-fix-one"'
                 . ' data-fix-type="' . h($type) . '" data-fix-id="' . h($id) . '"'
                 . ' title="Spróbuj naprawić ten wpis"><i class="ri-tools-line"></i> Napraw</button>';
        };
    ?>

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
                            <th class="text-end">Akcja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orphanPayments as $p): ?>
                            <tr data-row-id="<?= h($p->id) ?>">
                                <td><code style="font-size:.7rem"><?= h(substr($p->id, 0, 8)) ?>…</code></td>
                                <td><code style="font-size:.7rem"><?= h(substr($p->invoice_id, 0, 8)) ?>…</code></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$p->amount, 2, ',', ' ') ?></td>
                                <td><?= h(strtoupper($p->currency ?? 'PLN')) ?></td>
                                <td><span class="badge bg-light text-dark"><?= h($p->payment_type ?? 'gross') ?></span></td>
                                <td class="text-nowrap"><?= h($fdate($p->payment_date)) ?></td>
                                <td class="text-muted" style="font-size:.72rem"><?= h(mb_substr($p->description ?? '', 0, 60)) ?></td>
                                <td class="text-end"><?= $renderFixBtn('payment', (string)$p->id) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- B: matched bank_txs without allocation -->
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2 flex-wrap">
            <strong>B. Przelewy potwierdzone bez alokacji</strong>
            <span class="badge bg-secondary-subtle text-secondary"><?= count($txsWithoutAlloc) ?></span>
            <small class="text-muted ms-2">— <code>bank_transactions</code> z <code>match_status='matched'</code>, <code>match_confidence>=100</code>, ale brak rekordu w <code>bank_transaction_allocations</code></small>
            <?php if (!empty($txsWithoutAlloc)): ?>
                <?= $this->Form->create(null, [
                    'url' => ['action' => 'unlinkAllCategory', 'B'],
                    'class' => 'ms-auto mb-0',
                ]) ?>
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Odpiąć WSZYSTKIE <?= count($txsWithoutAlloc) ?> przelewów z kategorii B?\n\nTo usunie powiązane wpłaty/alokacje i zresetuje bank_transactions do unmatched. Operacja nieodwracalna.')">
                        <i class="ri-link-unlink me-1"></i>
                        Odepnij wszystkie B (<?= count($txsWithoutAlloc) ?>)
                    </button>
                <?= $this->Form->end() ?>
            <?php endif; ?>
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
                            <th class="text-end">Akcja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($txsWithoutAlloc as $t): ?>
                            <tr data-row-id="<?= h($t->id) ?>">
                                <td><code style="font-size:.7rem"><?= h(substr($t->id, 0, 8)) ?>…</code></td>
                                <td><code style="font-size:.7rem"><?= h(substr($t->invoice_id, 0, 8)) ?>…</code></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$t->amount, 2, ',', ' ') ?></td>
                                <td><?= h(strtoupper($t->currency ?? 'PLN')) ?></td>
                                <td class="text-nowrap"><?= h($fdate($t->value_date)) ?></td>
                                <td class="text-muted text-truncate" style="max-width:240px"><?= h(mb_substr($t->party_name ?? '', 0, 40)) ?></td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?= $renderFixBtn('tx', (string)$t->id) ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-fix-one"
                                                data-fix-type="unlink" data-fix-id="<?= h($t->id) ?>"
                                                title="Całkowicie odepnij ten przelew od faktury">
                                            <i class="ri-link-unlink"></i>
                                        </button>
                                    </div>
                                </td>
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
                            <th class="text-end">Akcja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orphanAllocs as $a): ?>
                            <tr data-row-id="<?= h($a->id) ?>">
                                <td><code style="font-size:.7rem"><?= h(substr($a->id, 0, 8)) ?>…</code></td>
                                <td><code style="font-size:.7rem"><?= h(substr($a->bank_transaction_id, 0, 8)) ?>…</code></td>
                                <td><code style="font-size:.7rem"><?= h(substr($a->invoice_id, 0, 8)) ?>…</code></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$a->allocated_amount, 2, ',', ' ') ?></td>
                                <td><?= h(strtoupper($a->currency ?? 'PLN')) ?></td>
                                <td><span class="badge bg-light text-dark"><?= h($a->allocation_type ?? 'gross') ?></span></td>
                                <td class="text-nowrap"><?= h($fdate($a->created)) ?></td>
                                <td class="text-end"><?= $renderFixBtn('alloc', (string)$a->id) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        var csrf = document.querySelector('input[name="_csrfToken"]')
                || document.querySelector('meta[name="csrfToken"]');
        var csrfToken = csrf ? (csrf.value || csrf.content || '') : '';

        document.querySelectorAll('.btn-fix-one').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (this.disabled) return;
                var type = this.dataset.fixType;
                var id   = this.dataset.fixId;
                // Operacje destrukcyjne wymagają potwierdzenia
                if (type === 'unlink') {
                    if (!confirm('Odpiąć ten przelew od faktury?\n\nTo usunie powiązaną wpłatę i alokację, oraz zresetuje przelew do stanu początkowego. Operacja nieodwracalna.')) {
                        return;
                    }
                }
                var row  = this.closest('tr');
                var orig = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                var fd = new FormData();
                fd.append('_csrfToken', csrfToken);

                fetch('/admin/rozliczenia/napraw-integralnosc/' + type + '/' + id, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: fd
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.ok) {
                        // Fade out wiersza + info
                        if (row) {
                            row.style.transition = 'background-color .4s, opacity .6s';
                            row.style.backgroundColor = '#d1fae5';
                            setTimeout(function () { row.style.opacity = '0.3'; }, 800);
                        }
                        showToast(res.message || 'Naprawiono.', 'success');
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = orig;
                        showToast(res.message || 'Nie udało się naprawić.', 'error');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    showToast('Błąd sieci — spróbuj ponownie.', 'error');
                });
            });
        });

        function showToast(msg, type) {
            var color = type === 'success' ? 'success' : 'danger';
            var icon  = type === 'success' ? 'ri-check-line' : 'ri-error-warning-line';
            var div = document.createElement('div');
            div.className = 'alert alert-' + color + ' alert-dismissible py-2 small mb-0';
            div.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:1100;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:280px;max-width:480px';
            div.innerHTML = '<i class="' + icon + ' me-1"></i>' + msg
                + '<button type="button" class="btn-close ms-2" data-bs-dismiss="alert"></button>';
            document.body.appendChild(div);
            setTimeout(function () { div.remove(); }, 4500);
        }
    })();
    </script>

    <!-- D: currency mismatch (payment.currency != tx.currency) -->
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2">
            <strong>D. Niezgodność waluty payment vs przelew</strong>
            <span class="badge bg-secondary-subtle text-secondary"><?= count($currencyMismatches) ?></span>
            <small class="text-muted ms-2">— <code>invoice_payments.currency</code> ≠ <code>bank_transactions.currency</code> (najczęściej stare PLN-domyślki zapisane zanim pole było accessible)</small>
        </div>
        <?php if (empty($currencyMismatches)): ?>
            <div class="card-body py-2 small text-muted">Brak problemów</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>ID wpłaty</th>
                            <th>Faktura</th>
                            <th class="text-end">Kwota DB</th>
                            <th>Waluta DB</th>
                            <th class="text-end">Kwota tx</th>
                            <th>Waluta tx</th>
                            <th>Data</th>
                            <th class="text-end">Akcja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($currencyMismatches as $p): ?>
                            <tr data-row-id="<?= h($p->id) ?>">
                                <td><code style="font-size:.7rem"><?= h(substr($p->id, 0, 8)) ?>…</code></td>
                                <td><code style="font-size:.7rem"><?= h(substr($p->invoice_id, 0, 8)) ?>…</code></td>
                                <td class="text-end"><?= number_format((float)$p->amount, 2, ',', ' ') ?></td>
                                <td><span class="badge bg-danger-subtle text-danger border"><?= h(strtoupper($p->currency ?? 'PLN')) ?></span></td>
                                <td class="text-end fw-semibold"><?= number_format($p->_real_amount, 2, ',', ' ') ?></td>
                                <td><span class="badge bg-success-subtle text-success border"><?= h($p->_real_currency) ?></span></td>
                                <td class="text-nowrap"><?= h($fdate($p->payment_date)) ?></td>
                                <td class="text-end"><?= $renderFixBtn('currency', (string)$p->id) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- E: auto-matched (confidence < 100) — błędnie oznaczone jako Wpłata -->
    <div class="card mb-3">
        <div class="card-header py-2 d-flex align-items-center gap-2 flex-wrap">
            <strong>E. Auto-matched przelewy (do odpięcia)</strong>
            <span class="badge bg-secondary-subtle text-secondary"><?= count($autoMatched) ?></span>
            <small class="text-muted ms-2">— <code>bank_transactions</code> z <code>match_status='matched'</code> ale <code>match_confidence &lt; 100</code> (automatyczne dopasowanie z importu MT940, nie odklikane przez usera)</small>
            <?php if (!empty($autoMatched)): ?>
                <?= $this->Form->create(null, [
                    'url' => ['action' => 'unlinkAllCategory', 'E'],
                    'class' => 'ms-auto mb-0',
                ]) ?>
                    <button type="submit" class="btn btn-sm btn-danger"
                            onclick="return confirm('Odpiąć WSZYSTKIE <?= count($autoMatched) ?> auto-matched z kategorii E?\n\nOperacja nieodwracalna.')">
                        <i class="ri-link-unlink me-1"></i>
                        Odepnij wszystkie E (<?= count($autoMatched) ?>)
                    </button>
                <?= $this->Form->end() ?>
            <?php endif; ?>
        </div>
        <?php if (empty($autoMatched)): ?>
            <div class="card-body py-2 small text-muted">Brak problemów</div>
        <?php else: ?>
            <div class="alert alert-warning py-2 small mb-0 m-2">
                <i class="ri-alert-line me-1"></i>
                Odpięcie usunie powiązaną <code>invoice_payment</code> i <code>bank_transaction_allocation</code>,
                zresetuje <code>bank_transaction</code> do stanu początkowego (<code>match_status='unmatched'</code>)
                i przeliczy <code>paymentstate</code> faktury. <strong>Operacja nieodwracalna.</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>ID przelewu</th>
                            <th>Faktura</th>
                            <th class="text-end">Kwota</th>
                            <th>Waluta</th>
                            <th>Data</th>
                            <th>Confidence</th>
                            <th>Reason</th>
                            <th>Nadawca</th>
                            <th class="text-end">Akcja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($autoMatched as $t): ?>
                            <tr data-row-id="<?= h($t->id) ?>">
                                <td><code style="font-size:.7rem"><?= h(substr($t->id, 0, 8)) ?>…</code></td>
                                <td><code style="font-size:.7rem"><?= h(substr($t->invoice_id, 0, 8)) ?>…</code></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$t->amount, 2, ',', ' ') ?></td>
                                <td><?= h(strtoupper($t->currency ?? 'PLN')) ?></td>
                                <td class="text-nowrap"><?= h($fdate($t->value_date)) ?></td>
                                <td>
                                    <span class="badge bg-info-subtle text-info border" style="font-size:.65rem">
                                        <?= (int)$t->match_confidence ?>
                                    </span>
                                </td>
                                <td class="text-muted" style="font-size:.7rem"><?= h($t->match_reason ?? '') ?></td>
                                <td class="text-muted text-truncate" style="max-width:180px"><?= h(mb_substr($t->party_name ?? '', 0, 30)) ?></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-danger py-0 px-2 btn-fix-one"
                                            data-fix-type="unlink" data-fix-id="<?= h($t->id) ?>"
                                            title="Odepnij ten przelew od faktury (usuwa wpłatę i alokację)">
                                        <i class="ri-link-unlink"></i> Odepnij
                                    </button>
                                </td>
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

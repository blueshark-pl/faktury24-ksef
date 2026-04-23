<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\BankTransaction[] $transactions
 * @var string $search
 * @var string $direction
 * @var string $matchStatus
 * @var string $dateFrom
 * @var string $dateTo
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var int $limit
 * @var array $statusCounts
 * @var string $title
 */
$this->assign('title', $title ?? 'Historia transakcji');

$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';
$fnum  = fn($v)  => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';

$currentUrl = function(array $extra = []) use ($search, $direction, $matchStatus, $dateFrom, $dateTo, $limit, $page): array {
    $base = ['q' => $search, 'dir' => $direction, 'status' => $matchStatus, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'limit' => $limit, 'page' => $page];
    $params = array_filter(array_merge($base, $extra), fn($v) => $v !== '' && $v !== null);
    return ['action' => 'transactions', '?' => $params];
};

$statusBadge = function(?string $status, ?int $conf = null): string {
    return match($status ?? 'unmatched') {
        'matched'   => '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="ri-checkbox-circle-line me-1"></i>Dopasowana</span>',
        'proposed'  => '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="ri-question-line me-1"></i>Do potwierdzenia</span>',
        'ignored'   => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="ri-eye-off-line me-1"></i>Ignorowana</span>',
        default     => '<span class="badge bg-light text-secondary border">Niedopasowana</span>',
    };
};
?>

<!-- Nagłówek -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">Wyciągi bankowe <span class="text-muted fs-6 fw-normal">MT940</span></h4>
    <?= $this->Html->link(
        '<i class="ri-upload-2-line me-1"></i> Importuj MT940',
        ['action' => 'import'],
        ['class' => 'btn btn-primary btn-sm', 'escape' => false]
    ) ?>
</div>

<!-- Zakładki -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'index']) ?>">
            <i class="ri-archive-line me-1"></i> Importy
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="<?= $this->Url->build(['action' => 'transactions']) ?>">
            <i class="ri-exchange-line me-1"></i> Wszystkie transakcje
            <?php if ($total > 0): ?>
                <span class="badge bg-secondary ms-1"><?= number_format($total, 0, ',', ' ') ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>

<?= $this->Flash->render() ?>

<!-- Szybkie filtry statusu -->
<div class="d-flex flex-wrap gap-2 mb-2">
    <?php
    $statusLabels = [
        ''          => ['label' => 'Wszystkie',        'icon' => 'ri-list-check',        'cls' => 'btn-outline-secondary'],
        'unmatched' => ['label' => 'Niedopasowane',    'icon' => 'ri-question-mark',     'cls' => 'btn-outline-secondary'],
        'proposed'  => ['label' => 'Do potwierdzenia', 'icon' => 'ri-alert-line',        'cls' => 'btn-outline-warning'],
        'matched'   => ['label' => 'Dopasowane',       'icon' => 'ri-checkbox-circle-line', 'cls' => 'btn-outline-success'],
        'ignored'   => ['label' => 'Ignorowane',       'icon' => 'ri-eye-off-line',      'cls' => 'btn-outline-secondary'],
    ];
    foreach ($statusLabels as $val => $meta):
        $count = $statusCounts[$val] ?? ($val === '' ? array_sum($statusCounts) : null);
        $active = ($matchStatus === $val) ? ' active' : '';
        $url = $this->Url->build(['action' => 'transactions', '?' => array_filter(['q' => $search, 'dir' => $direction, 'status' => $val, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'limit' => $limit], fn($v) => $v !== '' && $v !== null)]);
    ?>
        <a href="<?= $url ?>" class="btn btn-sm <?= $meta['cls'] ?><?= $active ?>">
            <i class="<?= $meta['icon'] ?> me-1"></i><?= $meta['label'] ?>
            <?php if ($count !== null): ?>
                <span class="badge bg-secondary ms-1"><?= $count ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Filtry -->
<form method="get" action="<?= $this->Url->build(['action' => 'transactions']) ?>" class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <input type="hidden" name="status" value="<?= h($matchStatus) ?>">
    <input type="text" name="q" value="<?= h($search) ?>"
           class="form-control form-control-sm" style="max-width:260px;"
           placeholder="Szukaj: kontrahent, tytuł, referencja…">
    <select name="dir" class="form-select form-select-sm" style="max-width:150px;">
        <option value="">Wszystkie</option>
        <option value="C" <?= $direction === 'C' ? 'selected' : '' ?>>Wpływy (C)</option>
        <option value="D" <?= $direction === 'D' ? 'selected' : '' ?>>Wypływy (D)</option>
    </select>
    <input type="date" name="date_from" value="<?= h($dateFrom) ?>"
           class="form-control form-control-sm" style="max-width:150px;" title="Od daty">
    <input type="date" name="date_to" value="<?= h($dateTo) ?>"
           class="form-control form-control-sm" style="max-width:150px;" title="Do daty">
    <select name="limit" class="form-select form-select-sm" style="max-width:100px;">
        <?php foreach ([25, 50, 100, 200] as $l): ?>
            <option value="<?= $l ?>" <?= $limit == $l ? 'selected' : '' ?>><?= $l ?> / str.</option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-primary" type="submit"><i class="ri-search-line"></i></button>
    <?php if ($search || $direction || $dateFrom || $dateTo): ?>
        <?= $this->Html->link(
            '<i class="ri-close-line"></i>',
            ['action' => 'transactions', '?' => array_filter(['status' => $matchStatus], fn($v) => $v !== '')],
            ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => 'Wyczyść filtry']
        ) ?>
    <?php endif; ?>
    <span class="text-muted small ms-auto">
        <?php if ($total > 0): ?>
            <?= number_format($total, 0, ',', ' ') ?> transakcji
        <?php endif; ?>
    </span>
</form>

<!-- Tabela -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-3 text-nowrap">Data waluty</th>
                    <th class="text-center">Typ</th>
                    <th class="text-end">Kwota</th>
                    <th>Kontrahent</th>
                    <th>Tytuł / opis</th>
                    <th>Dopasowanie</th>
                    <th class="pe-3">Import</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            Brak transakcji<?= ($search || $direction || $dateFrom || $dateTo || $matchStatus) ? ' dla podanych filtrów' : '' ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td class="ps-3 text-nowrap small">
                            <?= $fdate($tx->value_date) ?>
                            <?php if ($tx->booking_date && $tx->booking_date != $tx->value_date): ?>
                                <br><span class="text-muted" style="font-size:.75em"><?= $fdate($tx->booking_date) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($tx->direction === 'C'): ?>
                                <span class="badge bg-success-subtle text-success" title="Wpływ">
                                    <i class="ri-arrow-down-line"></i> WP
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger" title="Wypływ">
                                    <i class="ri-arrow-up-line"></i> WY
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="fw-semibold <?= $tx->direction === 'C' ? 'text-success' : 'text-danger' ?>">
                                <?= $tx->direction === 'D' ? '−' : '+' ?><?= $fnum($tx->amount) ?>
                                <span class="text-muted fw-normal small"><?= h($tx->currency) ?></span>
                            </div>
                            <?php if (!empty($txAllocMap[$tx->id])): ?>
                            <div class="tx-alloc-amt" id="tx-alloc-amt-<?= h($tx->id) ?>">
                                <span class="text-primary"><i class="ri-link-m"></i> <?= $fnum($txAllocMap[$tx->id]['allocated']) ?></span>
                                <span class="text-muted"><?= h($tx->currency) ?></span>
                            </div>
                            <?php else: ?>
                            <div class="tx-alloc-amt" id="tx-alloc-amt-<?= h($tx->id) ?>" style="display:none"></div>
                            <?php endif; ?>
                        </td>
                        <td class="small" style="max-width:180px;">
                            <?php if ($tx->party_name): ?>
                                <span class="fw-semibold"><?= h($tx->party_name) ?></span>
                                <?php if ($tx->party_account): ?>
                                    <br><code class="text-muted" style="font-size:.75em"><?= h($tx->party_account) ?></code>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small" style="max-width:240px;">
                            <?php $t = $tx->title ?? ''; ?>
                            <?php if ($t): ?>
                                <span class="text-truncate d-inline-block" style="max-width:230px;" title="<?= h($t) ?>"><?= h(mb_strimwidth($t, 0, 80, '…')) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                            <?php if ($tx->tx_type_code): ?>
                                <br><span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:.7em"><?= h($tx->tx_type_code) ?></span>
                            <?php endif; ?>
                        </td>

                        <!-- Kolumna dopasowania -->
                        <td class="small" style="min-width:200px;">
                            <?= $statusBadge($tx->match_status, $tx->match_confidence) ?>

                            <?php if ($tx->match_status === 'matched' && $tx->invoice): ?>
                                <div class="mt-1">
                                    <?= $this->Html->link(
                                        '<i class="ri-file-text-line me-1"></i>' . h($tx->invoice->fullnumber ?? ''),
                                        ['controller' => 'Invoices', 'action' => 'view', $tx->invoice_id],
                                        ['class' => 'small text-success text-decoration-none', 'escape' => false]
                                    ) ?>
                                </div>

                            <?php elseif ($tx->match_status === 'proposed' && $tx->invoice): ?>
                                <div class="mt-1">
                                    <span class="text-muted small d-block mb-1">
                                        <?= $this->Html->link(
                                            '<i class="ri-file-text-line me-1"></i>' . h($tx->invoice->fullnumber ?? ''),
                                            ['controller' => 'Invoices', 'action' => 'view', $tx->invoice_id],
                                            ['class' => 'text-warning text-decoration-none', 'escape' => false]
                                        ) ?>
                                        <span class="text-muted">(<?= $tx->match_confidence ?>%)</span>
                                    </span>
                                    <div class="d-flex gap-1">
                                        <?= $this->Form->postLink(
                                            '<i class="ri-check-line me-1"></i>Potwierdź',
                                            ['action' => 'confirmMatch', $tx->id],
                                            [
                                                'class'  => 'btn btn-xs btn-success',
                                                'escape' => false,
                                                'data'   => ['invoice_id' => $tx->invoice_id, 'redirect' => $this->request->getRequestTarget()],
                                                'confirm' => 'Potwierdzić dopasowanie i oznaczyć fakturę jako opłaconą?',
                                            ]
                                        ) ?>
                                        <?= $this->Form->postLink(
                                            '<i class="ri-close-line"></i>',
                                            ['action' => 'ignoreTransaction', $tx->id],
                                            [
                                                'class'  => 'btn btn-xs btn-outline-secondary',
                                                'escape' => false,
                                                'data'   => ['redirect' => $this->request->getRequestTarget()],
                                                'title'  => 'Ignoruj tę transakcję',
                                            ]
                                        ) ?>
                                    </div>
                                </div>

                            <?php elseif ($tx->match_status === 'unmatched'): ?>
                                <?php if ($tx->parsed_inv): ?>
                                    <div class="text-muted small mt-1">
                                        <i class="ri-file-search-line me-1"></i>INV: <?= h($tx->parsed_inv) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($tx->parsed_nip): ?>
                                    <div class="text-muted small">NIP: <?= h($tx->parsed_nip) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($tx->match_reason && $tx->match_status === 'proposed'): ?>
                                <div class="text-muted" style="font-size:.7em"><?= h($tx->match_reason) ?></div>
                            <?php endif; ?>

                            <?php /* Faktury z alokacji ręcznych */ ?>
                            <?php if (!empty($txAllocMap[$tx->id]['invoices'])): ?>
                            <div class="tx-alloc-invs mt-1 d-flex flex-wrap gap-1" id="tx-alloc-invs-<?= h($tx->id) ?>">
                                <?php foreach ($txAllocMap[$tx->id]['invoices'] as $fn): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:.7em">
                                    <i class="ri-link-m me-1"></i><?= h($fn) ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="tx-alloc-invs mt-1" id="tx-alloc-invs-<?= h($tx->id) ?>"></div>
                            <?php endif; ?>
                        </td>

                        <td class="pe-3 small text-muted">
                            <?php if ($tx->bank_statement_import): ?>
                                <code style="font-size:.75em;"><?= h($tx->bank_statement_import->account_number ?? '') ?></code>
                                <br>
                                <?= $this->Html->link(
                                    h(mb_strimwidth($tx->bank_statement_import->filename ?? '—', 0, 22, '…')),
                                    ['action' => 'view', $tx->import_id],
                                    ['class' => 'text-muted text-decoration-none small', 'title' => h($tx->bank_statement_import->filename ?? '')]
                                ) ?>
                            <?php endif; ?>
                        </td>
                        <td class="pe-2 text-end">
                            <?php if ($tx->direction === 'C'): ?>
                            <button type="button"
                                class="btn btn-sm btn-outline-primary py-0 btn-tx-settle"
                                data-tx-id="<?= h($tx->id) ?>"
                                data-tx-amount="<?= h($tx->amount) ?>"
                                data-tx-currency="<?= h($tx->currency) ?>"
                                data-tx-date="<?= h($tx->value_date instanceof \DateTimeInterface ? $tx->value_date->format('d.m.Y') : substr((string)$tx->value_date, 0, 10)) ?>"
                                data-tx-party="<?= h($tx->party_name ?? '') ?>"
                                data-tx-iban="<?= h($tx->party_account ?? '') ?>"
                                title="Przypisz do faktur / rozlicz">
                                <i class="ri-link me-1"></i>Rozlicz
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center py-2">
        <small class="text-muted">
            <?= ($page - 1) * $limit + 1 ?>–<?= min($page * $limit, $total) ?> z <?= number_format($total, 0, ',', ' ') ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <?= $this->Html->link('‹', $currentUrl(['page' => $page - 1]), ['class' => 'page-link']) ?>
                    </li>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <?= $this->Html->link((string)$p, $currentUrl(['page' => $p]), ['class' => 'page-link']) ?>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <?= $this->Html->link('›', $currentUrl(['page' => $page + 1]), ['class' => 'page-link']) ?>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════
     Modal rozliczania przelewu
     ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="settleModal" tabindex="-1" aria-labelledby="settleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content d-flex flex-column">

            <!-- Header: dane przelewu -->
            <div class="modal-header py-2 border-bottom bg-white" style="flex-shrink:0">
                <div class="d-flex align-items-center gap-3 flex-grow-1 min-width-0">
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                            <i class="ri-arrow-down-line me-1"></i>WP
                        </span>
                    </div>
                    <div class="min-width-0">
                        <div class="fw-semibold lh-sm text-truncate" id="sm-party" style="max-width:400px">—</div>
                        <div class="text-muted small" id="sm-meta">—</div>
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-4 flex-shrink-0">
                        <div class="text-end">
                            <div class="fw-bold fs-5 text-success lh-1 mb-1" id="sm-amount">—</div>
                            <div class="text-muted small" id="sm-date">—</div>
                        </div>
                        <!-- Pasek progresu w headerze -->
                        <div style="width:160px">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Alokowano</span>
                                <span id="sm-pct-lbl" class="fw-semibold">0%</span>
                            </div>
                            <div class="progress" style="height:8px;border-radius:4px">
                                <div class="progress-bar" id="sm-progress" style="width:0%;transition:width .3s"></div>
                            </div>
                            <div class="d-flex justify-content-between small mt-1">
                                <span class="text-muted">pozostało</span>
                                <span id="sm-remaining-lbl" class="fw-semibold text-danger">—</span>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
                    </div>
                </div>
            </div>

            <!-- Body: 2 kolumny -->
            <div class="modal-body p-0 flex-grow-1 overflow-hidden d-flex" style="min-height:0">

                <!-- Lewa: szukaj + formularz alokacji -->
                <div class="d-flex flex-column border-end" style="width:56%;min-width:0;background:#fff">
                    <!-- Wyszukiwarka -->
                    <div class="p-3 border-bottom" style="flex-shrink:0">
                        <div class="sm-section-label"><i class="ri-search-line"></i>Znajdź fakturę do przypisania</div>
                        <div class="d-flex gap-2">
                            <input type="text" id="sm-search" class="form-control form-control-sm flex-grow-1"
                                   placeholder="Nr faktury, kontrahent, NIP…" autocomplete="off">
                            <select id="sm-search-source" class="form-select form-select-sm" style="max-width:120px">
                                <option value="all">Wszystkie</option>
                                <option value="system">Systemowe</option>
                                <option value="legacy">Archiwum</option>
                            </select>
                        </div>
                    </div>
                    <!-- Wyniki wyszukiwania -->
                    <div id="sm-search-results" class="overflow-y-auto" style="flex:1 1 0;min-height:0">
                        <div id="sm-search-hint" class="text-muted small fst-italic p-3">
                            <i class="ri-lightbulb-line me-1"></i>Wpisz min. 2 znaki, aby znaleźć fakturę.
                        </div>
                    </div>
                    <!-- Formularz alokacji (pojawia się po wyborze faktury) -->
                    <div id="sm-alloc-form" class="p-3 border-top bg-light" style="flex-shrink:0;display:none">
                        <!-- wypełniany przez JS -->
                    </div>
                </div>

                <!-- Prawa: saldo + lista alokacji -->
                <div class="d-flex flex-column overflow-y-auto p-3 gap-3" style="width:44%;background:#f8fafc">
                    <!-- Saldo -->
                    <div class="bg-white border rounded-3 p-3">
                        <div class="sm-section-label"><i class="ri-scales-line"></i>Saldo przelewu</div>
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <span class="text-muted small">Kwota przelewu</span>
                            <span class="fw-bold fs-6" id="sm-tx-total">—</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-baseline small mb-1">
                            <span class="text-muted">Przydzielono</span>
                            <span id="sm-allocated-lbl" class="text-success fw-semibold">—</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-baseline small">
                            <span class="text-muted">Pozostało</span>
                            <span id="sm-remaining-lbl2" class="fw-bold text-danger">ładowanie…</span>
                        </div>
                    </div>
                    <!-- Lista alokacji -->
                    <div>
                        <div class="sm-section-label"><i class="ri-link"></i>Przypisane faktury</div>
                        <div id="sm-alloc-list">
                            <div class="text-muted small fst-italic">ładowanie…</div>
                        </div>
                    </div>
                </div>

            </div><!-- /modal-body -->
        </div><!-- /modal-content -->
    </div>
</div>

<style>
.btn-xs { padding: .125rem .375rem; font-size: .75rem; border-radius: .2rem; }

/* Nagłówek sekcji wewnątrz modala */
.sm-section-label {
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: #6b7280;
    margin-bottom: .6rem;
    display: flex;
    align-items: center;
    gap: .3rem;
}

/* Przydzielona kwota pod kwotą w tabeli */
.tx-alloc-amt { font-size: .75rem; margin-top: .15rem; }
/* Wyniki wyszukiwania */
.inv-result-row {
    cursor: pointer;
    padding: .65rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background .1s;
    display: flex;
    align-items: center;
    gap: .75rem;
}
.inv-result-row:last-child { border-bottom: none; }
.inv-result-row:hover    { background: #eff6ff; }
.inv-result-row.selected { background: #dbeafe; border-left: 3px solid #3b82f6; padding-left: calc(1rem - 3px); }
.inv-result-row .inv-num   { font-weight: 600; font-size: .85rem; color: #1e3a5f; }
.inv-result-row .inv-contr { font-size: .78rem; color: #6b7280; }
.inv-result-row .inv-amt   { text-align: right; white-space: nowrap; font-size: .82rem; margin-left: auto; }

/* Karty alokacji w prawej kolumnie */
.alloc-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: .5rem;
    padding: .5rem .75rem;
    display: flex;
    align-items: center;
    gap: .6rem;
    font-size: .82rem;
    margin-bottom: .5rem;
}
.alloc-card .alloc-num  { font-weight: 600; color: #1e3a5f; }
.alloc-card .alloc-amt  { font-weight: 700; color: #16a34a; margin-left: auto; white-space: nowrap; }
.alloc-card .alloc-type { font-size: .68rem; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: .25rem; padding: .1rem .35rem; color: #64748b; }

/* Szybkie przyciski kwot */
.btn-quick-amt {
    font-size: .75rem;
    padding: .3rem .6rem;
    border-radius: .375rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #374151;
    line-height: 1.3;
    transition: all .1s;
    cursor: pointer;
}
.btn-quick-amt:hover  { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
.btn-quick-amt.active { background: #dbeafe; border-color: #3b82f6; color: #1d4ed8; font-weight: 600; }
.btn-quick-amt small  { display: block; font-size: .7rem; opacity: .75; }
</style>

<script>
(function () {
'use strict';

var urlAddAllocation    = '<?= $this->Url->build(['controller' => 'Reconciliations', 'action' => 'addAllocation']) ?>';
var urlDeleteAllocation = '<?= $this->Url->build(['controller' => 'Reconciliations', 'action' => 'deleteAllocation', '_ext' => false]) ?>';
var csrfToken = '<?= h($this->request->getAttribute('csrfToken') ?? '') ?>';

function getCsrf() { return csrfToken; }

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmt(v)           { return parseFloat(v || 0).toFixed(2).replace('.', ','); }
function fmtC(v, c)       { return fmt(v) + '\u202f' + esc(c || 'PLN'); }

// ── Stan modala ───────────────────────────────────────────────────────────────
var currentTxId     = null;
var currentTxAmount = 0;
var currentTxCurr   = 'PLN';
var selectedInvoice = null;

var smEl    = document.getElementById('settleModal');
var smModal = bootstrap.Modal.getOrCreateInstance(smEl);

// ── Otwórz modal po kliknięciu "Rozlicz" ─────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-tx-settle');
    if (!btn) return;

    currentTxId     = btn.dataset.txId;
    currentTxAmount = parseFloat(btn.dataset.txAmount || 0);
    currentTxCurr   = btn.dataset.txCurrency || 'PLN';
    selectedInvoice = null;

    // Wypełnij nagłówek modala
    var party = btn.dataset.txParty || '';
    var date  = btn.dataset.txDate  || '';
    document.getElementById('sm-party').textContent    = party || '—';
    document.getElementById('sm-meta').textContent     = btn.dataset.txIban || '';
    document.getElementById('sm-amount').textContent   = fmtC(currentTxAmount, currentTxCurr);
    document.getElementById('sm-date').textContent     = date;
    document.getElementById('sm-tx-total').textContent = fmtC(currentTxAmount, currentTxCurr);

    // Wyczyść poprzedni stan
    document.getElementById('sm-search').value         = '';
    document.getElementById('sm-search-results').innerHTML =
        '<div id="sm-search-hint" class="text-muted small fst-italic p-3">'
      + '<i class="ri-lightbulb-line me-1"></i>Wpisz min. 2 znaki, aby znaleźć fakturę.</div>';
    document.getElementById('sm-alloc-form').style.display = 'none';
    document.getElementById('sm-alloc-form').innerHTML = '';
    updateModalProgress(0, currentTxAmount, currentTxCurr);

    smModal.show();
    loadModalAllocations();

    // Focus na szukajkę po otwarciu modala
    smEl.addEventListener('shown.bs.modal', function focusCb() {
        document.getElementById('sm-search').focus();
        smEl.removeEventListener('shown.bs.modal', focusCb);
    });
});

// ── Czyść stan po zamknięciu ──────────────────────────────────────────────────
smEl.addEventListener('hidden.bs.modal', function () {
    currentTxId     = null;
    selectedInvoice = null;
});

// ── Pasek progresu w nagłówku ─────────────────────────────────────────────────
function updateModalProgress(allocated, txAmt, curr) {
    var pct = txAmt > 0 ? Math.min(100, Math.round(allocated / txAmt * 100)) : 0;
    var remaining = Math.max(0, txAmt - allocated);
    var bar = document.getElementById('sm-progress');
    bar.style.width = pct + '%';
    bar.className   = 'progress-bar ' + (pct >= 100 ? 'bg-success' : pct > 0 ? 'bg-primary' : 'bg-secondary');
    document.getElementById('sm-pct-lbl').textContent      = pct + '%';
    document.getElementById('sm-remaining-lbl').textContent  = fmtC(remaining, curr);
    document.getElementById('sm-remaining-lbl2').textContent = fmtC(remaining, curr);
    var remEl = document.getElementById('sm-remaining-lbl');
    var remEl2 = document.getElementById('sm-remaining-lbl2');
    var cls = remaining > 0.005 ? 'fw-semibold text-warning' : 'fw-semibold text-success';
    if (remEl)  remEl.className  = cls;
    if (remEl2) remEl2.className = 'fw-bold ' + (remaining > 0.005 ? 'text-warning' : 'text-success');
    document.getElementById('sm-allocated-lbl').textContent = fmtC(allocated, curr);
}

// ── Wczytaj i wyrenderuj alokacje ─────────────────────────────────────────────
function loadModalAllocations() {
    if (!currentTxId) return;
    fetch('/wyciagi/tx-allocations/' + currentTxId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { renderModalAllocations(d); });
}

function renderModalAllocations(d) {
    var allocated = d.allocated_amount || 0;
    updateModalProgress(allocated, d.tx_amount || currentTxAmount, d.tx_currency || currentTxCurr);

    // Aktualizuj dane w wierszu tabeli (za modalem)
    if (currentTxId) {
        var amtEl  = document.getElementById('tx-alloc-amt-'  + currentTxId);
        var invsEl = document.getElementById('tx-alloc-invs-' + currentTxId);
        if (amtEl) {
            if (allocated > 0) {
                amtEl.innerHTML = '<span class="text-primary"><i class="ri-link-m"></i> ' + fmt(allocated) + '</span>'
                                + ' <span class="text-muted">' + esc(d.tx_currency || currentTxCurr) + '</span>';
                amtEl.style.display = '';
            } else {
                amtEl.style.display = 'none';
            }
        }
        if (invsEl && d.allocations) {
            var nums = [...new Set(d.allocations.map(function (a) { return a.fullnumber; }).filter(Boolean))];
            invsEl.innerHTML = nums.map(function (fn) {
                return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1" style="font-size:.7em">'
                     + '<i class="ri-link-m me-1"></i>' + esc(fn) + '</span>';
            }).join('');
        }
    }

    var listEl = document.getElementById('sm-alloc-list');
    if (!d.allocations || !d.allocations.length) {
        listEl.innerHTML = '<div class="text-muted small fst-italic">Brak przypisanych faktur.</div>';
        return;
    }
    var html = '';
    d.allocations.forEach(function (a) {
        var srcCls = a.source === 'legacy' ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary';
        html += '<div class="alloc-card">'
              + '<span class="badge ' + srcCls + ' border" style="font-size:.65em">' + (a.source === 'legacy' ? 'arch' : 'sys') + '</span>'
              + '<div class="flex-grow-1 min-width-0">'
              +   '<div class="alloc-num text-truncate">' + esc(a.fullnumber) + '</div>'
              +   '<span class="alloc-type">' + esc(a.type_label) + '</span>'
              +   (a.note ? ' <span class="text-muted ms-1" style="font-size:.72em">' + esc(a.note) + '</span>' : '')
              + '</div>'
              + '<span class="alloc-amt">' + fmtC(a.allocated_amount, a.currency) + '</span>'
              + '<button type="button" class="btn btn-xs btn-outline-danger flex-shrink-0 btn-del-alloc ms-1"'
              + ' data-alloc-id="' + esc(a.id) + '" title="Usuń alokację"><i class="ri-delete-bin-line"></i></button>'
              + '</div>';
    });
    listEl.innerHTML = html;
}

// ── Usuń alokację ─────────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-del-alloc');
    if (!btn) return;
    if (!confirm('Usunąć to przypisanie? Powiązana wpłata też zostanie usunięta.')) return;
    var allocId = btn.dataset.allocId;
    fetch(urlDeleteAllocation + '/' + allocId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': getCsrf() },
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.success) loadModalAllocations();
        else alert(d.error || 'Błąd usuwania.');
    });
});

// ── Wyszukiwanie faktur ───────────────────────────────────────────────────────
var searchTimer = null;
document.getElementById('sm-search').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(runSearch, 280);
});
document.getElementById('sm-search-source').addEventListener('change', runSearch);

function runSearch() {
    var q   = document.getElementById('sm-search').value.trim();
    var src = document.getElementById('sm-search-source').value;
    if (q.length < 2) {
        document.getElementById('sm-search-results').innerHTML =
            '<div class="text-muted small fst-italic p-3"><i class="ri-lightbulb-line me-1"></i>Wpisz min. 2 znaki.</div>';
        return;
    }
    fetch('/wyciagi/invoice-search?q=' + encodeURIComponent(q) + '&source=' + encodeURIComponent(src),
          { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { renderSearchResults(d.results || []); });
}

function renderSearchResults(results) {
    var resEl = document.getElementById('sm-search-results');
    if (!results.length) {
        resEl.innerHTML = '<div class="text-muted small p-3 text-center fst-italic">'
                        + '<i class="ri-file-search-line me-1"></i>Brak wyników.</div>';
        return;
    }
    var html = '';
    results.forEach(function (inv) {
        var srcCls    = inv.source === 'legacy' ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary';
        var srcTxt    = inv.source === 'legacy' ? 'arch' : 'sys';
        var stClr     = inv.paymentstate === 'paid' ? 'text-success' : inv.paymentstate === 'partial' ? 'text-warning' : 'text-danger';
        var stIco     = inv.paymentstate === 'paid' ? 'ri-checkbox-circle-fill' : inv.paymentstate === 'partial' ? 'ri-time-line' : 'ri-error-warning-line';
        var pct       = inv.total > 0 ? Math.min(100, Math.round((inv.total - inv.remaining) / inv.total * 100)) : 0;
        var invJson   = esc(JSON.stringify(inv));

        html += '<div class="inv-result-row" data-inv=\'' + invJson + '\'>'
              + '<div class="flex-shrink-0"><span class="badge ' + srcCls + ' border" style="font-size:.65em">' + srcTxt + '</span></div>'
              + '<div class="flex-grow-1 min-width-0">'
              +   '<div class="inv-num">' + esc(inv.fullnumber) + '</div>'
              +   '<div class="inv-contr text-truncate">' + esc(inv.contractor) + (inv.nip ? ' · ' + esc(inv.nip) : '') + '</div>'
              +   '<div class="mt-1" style="height:3px;background:#e5e7eb;border-radius:2px">'
              +     '<div style="height:3px;width:' + pct + '%;background:' + (pct >= 100 ? '#16a34a' : '#3b82f6') + ';border-radius:2px"></div>'
              +   '</div>'
              + '</div>'
              + '<div class="inv-amt">'
              +   '<div class="' + stClr + ' fw-semibold small"><i class="' + stIco + ' me-1"></i>' + fmtC(inv.remaining, inv.currency) + '</div>'
              +   '<div class="text-muted" style="font-size:.7em">z ' + fmtC(inv.total, inv.currency) + '</div>'
              + '</div>'
              + '</div>';
    });
    resEl.innerHTML = html;
}

// ── Wybór faktury → formularz ─────────────────────────────────────────────────
document.getElementById('sm-search-results').addEventListener('click', function (e) {
    var row = e.target.closest('.inv-result-row');
    if (!row) return;
    this.querySelectorAll('.inv-result-row').forEach(function (r) { r.classList.remove('selected'); });
    row.classList.add('selected');
    selectedInvoice = JSON.parse(row.dataset.inv || '{}');
    renderAllocForm(selectedInvoice);
});

// ── Formularz alokacji ────────────────────────────────────────────────────────
function renderAllocForm(inv) {
    var formEl  = document.getElementById('sm-alloc-form');
    var isEur   = inv.currency && inv.currency !== 'PLN';
    var defAmt  = inv.remaining > 0 ? inv.remaining.toFixed(2) : (inv.total || 0).toFixed(2);
    var defCurr = inv.currency || 'PLN';

    var stClr  = inv.paymentstate === 'paid' ? '#16a34a' : inv.paymentstate === 'partial' ? '#d97706' : '#dc2626';
    var stTxt  = inv.paymentstate === 'paid' ? 'opłacona' : inv.paymentstate === 'partial' ? 'częściowo' : 'nieopłacona';
    var pct    = inv.total > 0 ? Math.min(100, Math.round((inv.total - inv.remaining) / inv.total * 100)) : 0;

    // Karta wybranej faktury
    var html = '<div class="d-flex align-items-start gap-3 mb-3">'
             + '<div class="flex-grow-1 min-width-0">'
             +   '<div class="fw-bold" style="font-size:.95rem">' + esc(inv.fullnumber) + '</div>'
             +   '<div class="text-muted small text-truncate">' + esc(inv.contractor) + (inv.nip ? ' · ' + esc(inv.nip) : '') + '</div>'
             +   (isEur && inv.exchange_rate
                    ? '<div class="text-muted mt-1" style="font-size:.72rem">Kurs: ' + parseFloat(inv.exchange_rate).toFixed(4)
                    + ' · brutto PLN ≈ ' + fmtC(Math.round(inv.total * inv.exchange_rate * 100) / 100, 'PLN') + '</div>'
                    : '')
             +   '<div class="mt-2 mb-1" style="height:4px;background:#e5e7eb;border-radius:2px">'
             +     '<div style="height:4px;width:' + pct + '%;background:' + (pct >= 100 ? '#16a34a' : '#3b82f6') + ';border-radius:2px;transition:width .3s"></div>'
             +   '</div>'
             + '</div>'
             + '<div class="text-end flex-shrink-0">'
             +   '<div class="fw-bold" style="color:' + stClr + ';font-size:.95rem">' + fmtC(inv.remaining, inv.currency) + '</div>'
             +   '<div style="color:' + stClr + ';font-size:.72rem">' + stTxt + '</div>'
             + '</div>'
             + '</div>';

    // Szybkie przyciski kwot
    var qAmts = [];
    if (isEur) {
        if (inv.remaining > 0 && inv.remaining !== inv.total) qAmts.push({ label: 'Pozostałe', amt: inv.remaining, curr: inv.currency, type: 'gross' });
        if (inv.total > 0)  qAmts.push({ label: 'Brutto ' + inv.currency, amt: inv.total,  curr: inv.currency, type: 'gross' });
        if (inv.netto > 0)  qAmts.push({ label: 'Netto '  + inv.currency, amt: inv.netto,  curr: inv.currency, type: 'net'   });
        if (inv.vat   > 0)  qAmts.push({ label: 'VAT PLN', amt: Math.round(inv.vat * (inv.exchange_rate || 1) * 100) / 100, curr: 'PLN', type: 'vat' });
    } else {
        if (inv.remaining > 0 && inv.remaining !== inv.total) qAmts.push({ label: 'Pozostałe',  amt: inv.remaining, curr: 'PLN', type: 'gross' });
        if (inv.total > 0)  qAmts.push({ label: 'Brutto PLN', amt: inv.total, curr: 'PLN', type: 'gross' });
        if (inv.netto > 0)  qAmts.push({ label: 'Netto PLN',  amt: inv.netto, curr: 'PLN', type: 'net'   });
        if (inv.vat   > 0)  qAmts.push({ label: 'VAT PLN',    amt: inv.vat,   curr: 'PLN', type: 'vat'   });
    }

    html += '<div class="sm-section-label"><i class="ri-money-euro-circle-line"></i>Kwota alokacji</div>'
          + '<div class="d-flex flex-wrap gap-2 mb-3">';
    qAmts.forEach(function (qa) {
        html += '<button type="button" class="btn-quick-amt" data-amt="' + qa.amt + '" data-curr="' + esc(qa.curr) + '" data-type="' + esc(qa.type) + '">'
              + esc(qa.label) + '<small>' + fmtC(qa.amt, qa.curr) + '</small>'
              + '</button>';
    });
    html += '</div>';

    html += '<div class="row g-2 mb-2 align-items-end">'
          + '<div class="col-5"><label class="form-label small text-muted mb-1" style="font-size:.72rem">Kwota</label>'
          +   '<input type="number" class="form-control form-control-sm" id="sm-alloc-amt" value="' + esc(defAmt) + '" step="0.01" min="0.01"></div>'
          + '<div class="col-3"><label class="form-label small text-muted mb-1" style="font-size:.72rem">Waluta</label>'
          +   '<select class="form-select form-select-sm" id="sm-alloc-curr">'
          +   ['PLN','EUR','USD','GBP'].map(function (c) { return '<option' + (c === defCurr ? ' selected' : '') + '>' + c + '</option>'; }).join('')
          +   '</select></div>'
          + '<div class="col-4"><label class="form-label small text-muted mb-1" style="font-size:.72rem">Typ płatności</label>'
          +   '<select class="form-select form-select-sm" id="sm-alloc-type">'
          +   '<option value="gross">Brutto</option><option value="net">Netto</option><option value="vat">VAT (MPP)</option>'
          +   '</select></div>'
          + '</div>'
          + '<div class="mb-3"><input type="text" class="form-control form-control-sm" id="sm-alloc-note" placeholder="Uwaga opcjonalna (np. MPP – VAT)"></div>'
          + '<button type="button" class="btn btn-primary w-100 btn-do-sm-alloc">'
          +   '<i class="ri-link me-1"></i>Przypisz do faktury</button>';

    formEl.innerHTML = html;
    formEl.style.display = '';

    // Auto-klik pierwszego przycisku kwoty
    var first = formEl.querySelector('.btn-quick-amt');
    if (first) first.click();
}

// ── Szybkie kwoty ─────────────────────────────────────────────────────────────
document.getElementById('sm-alloc-form').addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-quick-amt');
    if (!btn) return;
    document.getElementById('sm-alloc-amt').value  = parseFloat(btn.dataset.amt).toFixed(2);
    document.getElementById('sm-alloc-curr').value = btn.dataset.curr;
    document.getElementById('sm-alloc-type').value = btn.dataset.type;
    this.querySelectorAll('.btn-quick-amt').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
});

// ── Wykonaj alokację ──────────────────────────────────────────────────────────
document.getElementById('sm-alloc-form').addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-do-sm-alloc');
    if (!btn) return;
    if (!selectedInvoice) { alert('Wybierz fakturę z listy wyników.'); return; }

    var amt  = parseFloat(document.getElementById('sm-alloc-amt')?.value || 0);
    var curr = document.getElementById('sm-alloc-curr')?.value || 'PLN';
    var type = document.getElementById('sm-alloc-type')?.value || 'gross';
    var note = document.getElementById('sm-alloc-note')?.value || '';

    if (amt <= 0) { alert('Podaj kwotę większą od 0.'); return; }

    var body = new URLSearchParams({
        bank_transaction_id: currentTxId,
        allocated_amount:    amt.toFixed(2),
        currency:            curr,
        allocation_type:     type,
        note:                note,
        _csrfToken:          getCsrf(),
    });
    if (selectedInvoice.source === 'legacy') body.set('legacy_invoice_id', selectedInvoice.id);
    else                                     body.set('invoice_id',        selectedInvoice.id);

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Zapisywanie…';
    fetch(urlAddAllocation, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-link me-1"></i>Przypisz do faktury';
        if (d.success) {
            // Wyczyść zaznaczenie i ukryj formularz
            document.getElementById('sm-search-results').querySelectorAll('.inv-result-row')
                .forEach(function (r) { r.classList.remove('selected'); });
            document.getElementById('sm-alloc-form').style.display = 'none';
            document.getElementById('sm-search').value = '';
            document.getElementById('sm-search-results').innerHTML =
                '<div class="text-muted small fst-italic p-3"><i class="ri-check-line text-success me-1"></i>'
              + 'Przypisano. Wyszukaj kolejną fakturę.</div>';
            selectedInvoice = null;
            loadModalAllocations();
        } else {
            alert(d.error || 'Błąd przypisywania.');
        }
    })
    .catch(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-link me-1"></i>Przypisz do faktury';
        alert('Błąd połączenia.');
    });
});

}());
</script>

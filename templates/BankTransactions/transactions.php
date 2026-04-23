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
                        <td class="text-end text-nowrap fw-semibold <?= $tx->direction === 'C' ? 'text-success' : 'text-danger' ?>">
                            <?= $tx->direction === 'D' ? '−' : '+' ?><?= $fnum($tx->amount) ?>
                            <span class="text-muted fw-normal small"><?= h($tx->currency) ?></span>
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
                                data-tx-date="<?= h($tx->value_date instanceof \DateTimeInterface ? $tx->value_date->format('Y-m-d') : substr((string)$tx->value_date, 0, 10)) ?>"
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

<style>
.btn-xs { padding: .125rem .375rem; font-size: .75rem; border-radius: .2rem; }

/* Panel rozliczeniowy */
.settle-panel-td { padding: 0 !important; }
.settle-panel {
    background: linear-gradient(to bottom, #f0f4ff, #f8fafc);
    border-top: 2px solid #3b82f6;
    border-bottom: 1px solid #dee2e6;
}
.settle-col {
    border-right: 1px solid #e2e8f0;
    padding: 1rem 1.25rem;
}
.settle-col:last-child { border-right: none; }
.settle-col-label {
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: #6b7280;
    margin-bottom: .5rem;
    display: flex;
    align-items: center;
    gap: .3rem;
}

/* Karty alokacji */
.alloc-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: .5rem;
    padding: .5rem .75rem;
    display: flex;
    align-items: center;
    gap: .6rem;
    font-size: .82rem;
}
.alloc-card .alloc-num  { font-weight: 600; color: #1e3a5f; }
.alloc-card .alloc-amt  { font-weight: 700; color: #16a34a; margin-left: auto; white-space: nowrap; }
.alloc-card .alloc-type { font-size: .68rem; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: .25rem; padding: .1rem .35rem; color: #64748b; }

/* Wyniki wyszukiwania */
.inv-result-row {
    cursor: pointer;
    padding: .6rem .85rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background .12s;
    display: flex;
    align-items: center;
    gap: .75rem;
}
.inv-result-row:last-child { border-bottom: none; }
.inv-result-row:hover { background: #eff6ff; }
.inv-result-row.selected {
    background: #dbeafe;
    border-left: 3px solid #3b82f6;
    padding-left: calc(.85rem - 3px);
}
.inv-result-row .inv-num  { font-weight: 600; font-size: .85rem; color: #1e3a5f; }
.inv-result-row .inv-contr { font-size: .78rem; color: #6b7280; }
.inv-result-row .inv-amt  { text-align: right; white-space: nowrap; font-size: .82rem; }

/* Szybkie przyciski kwot */
.btn-quick-amt {
    font-size: .75rem;
    padding: .25rem .55rem;
    border-radius: .375rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #374151;
    line-height: 1.3;
    transition: all .12s;
}
.btn-quick-amt:hover  { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
.btn-quick-amt.active { background: #dbeafe; border-color: #3b82f6; color: #1d4ed8; font-weight: 600; }
.btn-quick-amt small  { display: block; font-size: .7rem; opacity: .8; }

/* Pasek salda */
.settle-balance-bar {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: .5rem;
    padding: .6rem .85rem;
    margin-bottom: .85rem;
}
.settle-balance-bar .progress { height: 6px; border-radius: 3px; }
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
function fmt(v) {
    return parseFloat(v || 0).toFixed(2).replace('.', ',');
}
function fmtCurrency(v, c) {
    return fmt(v) + '\u202f' + esc(c || 'PLN');
}
function stateLabel(s) {
    if (s === 'paid')    return '<span class="badge bg-success-subtle text-success border" style="font-size:.7em">opłacona</span>';
    if (s === 'partial') return '<span class="badge bg-warning-subtle text-warning border" style="font-size:.7em">częściowo</span>';
    return '<span class="badge bg-danger-subtle text-danger border" style="font-size:.7em">nieopłacona</span>';
}

// ── Stan paneli ──────────────────────────────────────────────────────────────
var openPanels = {};   // txId → { selectedInvoice, selectedSource }

// ── Kliknięcie "Rozlicz" ─────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-tx-settle');
    if (!btn) return;
    var txId     = btn.dataset.txId;
    var txAmount = parseFloat(btn.dataset.txAmount || 0);
    var txCurr   = btn.dataset.txCurrency || 'PLN';
    var txDate   = btn.dataset.txDate || '';
    var tr       = btn.closest('tr');

    // Toggle
    var existing = document.getElementById('settle-panel-' + txId);
    if (existing) {
        existing.closest('tr').remove();
        delete openPanels[txId];
        btn.classList.remove('active');
        return;
    }
    btn.classList.add('active');
    openPanels[txId] = { invoice: null, source: null };

    var panelTr = document.createElement('tr');
    panelTr.id  = 'settle-tr-' + txId;
    panelTr.innerHTML =
        '<td colspan="8" class="settle-panel-td">'
      + '<div id="settle-panel-' + esc(txId) + '" class="settle-panel">'
      + '<div class="d-flex" style="min-height:280px">'

      // ── Kolumna 1: saldo + alokacje ───────────────────────────────────────
      + '<div class="settle-col" style="width:32%;min-width:240px">'
      +   '<div class="settle-col-label"><i class="ri-scales-line"></i>Saldo przelewu</div>'
      +   '<div class="settle-balance-bar" id="tx-balance-box-' + esc(txId) + '">'
      +     '<div class="d-flex justify-content-between align-items-baseline mb-1">'
      +       '<span class="text-muted small">Kwota przelewu</span>'
      +       '<span class="fw-bold">' + fmtCurrency(txAmount, txCurr) + '</span>'
      +     '</div>'
      +     '<div class="progress mb-1"><div class="progress-bar bg-success" id="tx-progress-' + esc(txId) + '" style="width:0%"></div></div>'
      +     '<div class="d-flex justify-content-between small">'
      +       '<span class="text-muted">Przydzielono</span>'
      +       '<span id="tx-allocated-lbl-' + esc(txId) + '" class="text-success fw-semibold">—</span>'
      +     '</div>'
      +     '<div class="d-flex justify-content-between small mt-1">'
      +       '<span class="text-muted">Pozostało</span>'
      +       '<span id="tx-remaining-lbl-' + esc(txId) + '" class="fw-bold text-danger">ładowanie…</span>'
      +     '</div>'
      +   '</div>'
      +   '<div class="settle-col-label mt-2"><i class="ri-link"></i>Przypisane faktury</div>'
      +   '<div id="tx-alloc-list-' + esc(txId) + '"></div>'
      + '</div>'

      // ── Kolumna 2: szukaj faktury ─────────────────────────────────────────
      + '<div class="settle-col" style="width:34%;min-width:260px">'
      +   '<div class="settle-col-label"><i class="ri-search-line"></i>Znajdź fakturę</div>'
      +   '<div class="d-flex gap-1 mb-2">'
      +     '<input type="text" class="form-control form-control-sm tx-inv-search flex-grow-1"'
      +       ' id="tx-search-' + esc(txId) + '" data-tx-id="' + esc(txId) + '"'
      +       ' placeholder="Nr faktury, kontrahent, NIP…" autocomplete="off">'
      +     '<select class="form-select form-select-sm" id="tx-search-source-' + esc(txId) + '" style="max-width:105px">'
      +       '<option value="all">Wszystkie</option>'
      +       '<option value="system">Systemowe</option>'
      +       '<option value="legacy">Archiwum</option>'
      +     '</select>'
      +   '</div>'
      +   '<div id="tx-search-results-' + esc(txId) + '"'
      +     ' class="border rounded bg-white" style="max-height:240px;overflow-y:auto;display:none"></div>'
      +   '<div id="tx-search-hint-' + esc(txId) + '" class="text-muted small fst-italic mt-2">'
      +     '<i class="ri-lightbulb-line me-1"></i>Wpisz min. 2 znaki aby wyszukać.'
      +   '</div>'
      + '</div>'

      // ── Kolumna 3: formularz alokacji ─────────────────────────────────────
      + '<div class="settle-col flex-grow-1" id="tx-alloc-form-' + esc(txId) + '">'
      +   '<div class="text-muted small fst-italic d-flex align-items-center gap-2 h-100 justify-content-center">'
      +     '<i class="ri-arrow-left-line opacity-50"></i>Wybierz fakturę z listy'
      +   '</div>'
      + '</div>'

      + '</div>' // /d-flex
      + '</div></td>';

    tr.after(panelTr);

    // Wczytaj istniejące alokacje
    loadTxAllocations(txId);
});

// ── Wczytaj alokacje dla przelewu ────────────────────────────────────────────
function loadTxAllocations(txId) {
    fetch('/wyciagi/tx-allocations/' + txId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { renderTxAllocations(txId, d); });
}

function renderTxAllocations(txId, d) {
    var listEl    = document.getElementById('tx-alloc-list-'    + txId);
    var remLbl    = document.getElementById('tx-remaining-lbl-' + txId);
    var allocLbl  = document.getElementById('tx-allocated-lbl-' + txId);
    var progressEl= document.getElementById('tx-progress-'     + txId);
    if (!listEl) return;

    var txAmt     = d.tx_amount || 0;
    var allocated = d.allocated_amount || 0;
    var remaining = d.remaining_amount != null ? d.remaining_amount : txAmt;
    var pct       = txAmt > 0 ? Math.min(100, Math.round(allocated / txAmt * 100)) : 0;

    if (remLbl)   { remLbl.textContent = fmtCurrency(remaining, d.tx_currency); remLbl.className = remaining > 0.005 ? 'fw-bold text-warning' : 'fw-bold text-success'; }
    if (allocLbl) allocLbl.textContent = fmtCurrency(allocated, d.tx_currency);
    if (progressEl) { progressEl.style.width = pct + '%'; progressEl.className = 'progress-bar ' + (pct >= 100 ? 'bg-success' : 'bg-primary'); }

    if (!d.allocations || !d.allocations.length) {
        listEl.innerHTML = '<div class="text-muted small fst-italic py-1">Brak przypisanych faktur.</div>';
        return;
    }

    var html = '<div class="d-flex flex-column gap-2">';
    d.allocations.forEach(function (a) {
        var srcCls = a.source === 'legacy' ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary';
        html += '<div class="alloc-card">'
              + '<span class="badge ' + srcCls + ' border" style="font-size:.65em">' + (a.source === 'legacy' ? 'arch' : 'sys') + '</span>'
              + '<div class="flex-grow-1 min-width-0">'
              +   '<div class="alloc-num text-truncate">' + esc(a.fullnumber) + '</div>'
              +   '<span class="alloc-type">' + esc(a.type_label) + '</span>'
              +   (a.note ? ' <span class="text-muted" style="font-size:.72em">' + esc(a.note) + '</span>' : '')
              + '</div>'
              + '<span class="alloc-amt">' + fmtCurrency(a.allocated_amount, a.currency) + '</span>'
              + '<button type="button" class="btn btn-xs btn-outline-danger flex-shrink-0 btn-del-alloc"'
              + ' data-alloc-id="' + esc(a.id) + '" data-tx-id="' + esc(txId) + '" title="Usuń alokację">'
              + '<i class="ri-delete-bin-line"></i></button>'
              + '</div>';
    });
    html += '</div>';
    listEl.innerHTML = html;
}

// ── Usuwanie alokacji ────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-del-alloc');
    if (!btn) return;
    if (!confirm('Usunąć to przypisanie? Powiązana wpłata też zostanie usunięta.')) return;
    var allocId = btn.dataset.allocId;
    var txId    = btn.dataset.txId;
    fetch(urlDeleteAllocation + '/' + allocId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': getCsrf() },
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.success) loadTxAllocations(txId);
        else alert(d.error || 'Błąd.');
    });
});

// ── Wyszukiwanie faktur ──────────────────────────────────────────────────────
var searchTimers = {};
document.addEventListener('input', function (e) {
    var input = e.target.closest('.tx-inv-search');
    if (!input) return;
    var txId = input.dataset.txId;
    clearTimeout(searchTimers[txId]);
    searchTimers[txId] = setTimeout(function () { runInvoiceSearch(txId); }, 300);
});

function runInvoiceSearch(txId) {
    var input  = document.getElementById('tx-search-' + txId);
    var source = document.getElementById('tx-search-source-' + txId);
    var resEl  = document.getElementById('tx-search-results-' + txId);
    if (!input || !resEl) return;
    var q = input.value.trim();
    if (q.length < 2) { resEl.style.display = 'none'; return; }

    var url = '/wyciagi/invoice-search?q=' + encodeURIComponent(q)
            + '&source=' + encodeURIComponent(source ? source.value : 'all');
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { renderSearchResults(txId, d.results || []); });
}

function renderSearchResults(txId, results) {
    var resEl  = document.getElementById('tx-search-results-' + txId);
    var hintEl = document.getElementById('tx-search-hint-'    + txId);
    if (!resEl) return;
    if (hintEl) hintEl.style.display = 'none';

    if (!results.length) {
        resEl.innerHTML = '<div class="text-muted small px-3 py-3 text-center fst-italic">'
                        + '<i class="ri-file-search-line me-1"></i>Brak wyników.</div>';
        resEl.style.display = '';
        return;
    }
    var html = '';
    results.forEach(function (inv) {
        var srcCls = inv.source === 'legacy' ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary';
        var srcTxt = inv.source === 'legacy' ? 'arch' : 'sys';
        var stateColor = inv.paymentstate === 'paid' ? 'text-success' : inv.paymentstate === 'partial' ? 'text-warning' : 'text-danger';
        var stateIcon  = inv.paymentstate === 'paid' ? 'ri-checkbox-circle-fill' : inv.paymentstate === 'partial' ? 'ri-time-line' : 'ri-error-warning-line';
        // Pasek progresu zapłacenia
        var pct = inv.total > 0 ? Math.min(100, Math.round((inv.total - inv.remaining) / inv.total * 100)) : 0;

        html += '<div class="inv-result-row" data-tx-id="' + esc(txId) + '" data-inv=\'' + esc(JSON.stringify(inv)) + '\'>'
              + '<div class="flex-shrink-0">'
              +   '<span class="badge ' + srcCls + ' border" style="font-size:.65em">' + srcTxt + '</span>'
              + '</div>'
              + '<div class="flex-grow-1 min-width-0">'
              +   '<div class="inv-num">' + esc(inv.fullnumber) + '</div>'
              +   '<div class="inv-contr text-truncate">' + esc(inv.contractor) + (inv.nip ? ' · ' + esc(inv.nip) : '') + '</div>'
              +   '<div class="mt-1" style="height:3px;background:#e5e7eb;border-radius:2px">'
              +     '<div style="height:3px;width:' + pct + '%;background:' + (pct >= 100 ? '#16a34a' : '#3b82f6') + ';border-radius:2px"></div>'
              +   '</div>'
              + '</div>'
              + '<div class="inv-amt">'
              +   '<div class="' + stateColor + ' fw-semibold"><i class="' + stateIcon + ' me-1"></i>' + fmtCurrency(inv.remaining, inv.currency) + '</div>'
              +   '<div class="text-muted" style="font-size:.72em">pozostało z ' + fmtCurrency(inv.total, inv.currency) + '</div>'
              + '</div>'
              + '</div>';
    });
    resEl.innerHTML = html;
    resEl.style.display = '';
}

// ── Wybór faktury z wyników ──────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var row = e.target.closest('.inv-result-row');
    if (!row) return;
    var txId = row.dataset.txId;
    var inv  = JSON.parse(row.dataset.inv || '{}');

    // Highlight
    row.closest('div').querySelectorAll('.inv-result-row').forEach(function (r) { r.classList.remove('selected'); });
    row.classList.add('selected');

    openPanels[txId] = { invoice: inv, source: inv.source };
    renderAllocForm(txId, inv);
});

// ── Formularz alokacji ───────────────────────────────────────────────────────
function renderAllocForm(txId, inv) {
    var formEl = document.getElementById('tx-alloc-form-' + txId);
    if (!formEl) return;

    var isEur     = inv.currency && inv.currency !== 'PLN';
    var defAmount = inv.remaining > 0 ? inv.remaining.toFixed(2) : inv.total.toFixed(2);
    var defCurr   = inv.currency || 'PLN';

    // Karta wybranej faktury
    var stateColor = inv.paymentstate === 'paid' ? '#16a34a' : inv.paymentstate === 'partial' ? '#d97706' : '#dc2626';
    var stateText  = inv.paymentstate === 'paid' ? 'opłacona' : inv.paymentstate === 'partial' ? 'częściowo' : 'nieopłacona';
    var pct = inv.total > 0 ? Math.min(100, Math.round((inv.total - inv.remaining) / inv.total * 100)) : 0;

    var html = '<div class="settle-col-label"><i class="ri-file-text-line"></i>Wybrana faktura</div>'
             + '<div class="border rounded p-2 mb-3 bg-white" style="border-left:3px solid #3b82f6!important">'
             + '<div class="d-flex align-items-start gap-2">'
             + '<div class="flex-grow-1">'
             +   '<div class="fw-bold" style="font-size:.9rem">' + esc(inv.fullnumber) + '</div>'
             +   '<div class="text-muted" style="font-size:.78rem">' + esc(inv.contractor) + '</div>'
             + '</div>'
             + '<div class="text-end">'
             +   '<div class="fw-semibold" style="font-size:.85rem;color:' + stateColor + '">' + fmtCurrency(inv.remaining, inv.currency) + '</div>'
             +   '<div style="font-size:.68rem;color:' + stateColor + '">' + stateText + '</div>'
             + '</div>'
             + '</div>'
             + (isEur && inv.exchange_rate ? '<div class="text-muted mt-1" style="font-size:.7rem">Kurs: ' + parseFloat(inv.exchange_rate).toFixed(4) + ' · brutto PLN: ' + fmtCurrency(Math.round(inv.total * inv.exchange_rate * 100)/100, 'PLN') + '</div>' : '')
             + '<div class="mt-2" style="height:4px;background:#e5e7eb;border-radius:2px">'
             +   '<div style="height:4px;width:' + pct + '%;background:' + (pct >= 100 ? '#16a34a' : '#3b82f6') + ';border-radius:2px;transition:width .3s"></div>'
             + '</div>'
             + '</div>';

    // Szybkie przyciski kwot
    var quickAmounts = [];
    if (isEur) {
        if (inv.remaining > 0 && inv.remaining !== inv.total) quickAmounts.push({ label: 'Pozostałe', amt: inv.remaining, curr: inv.currency, type: 'gross' });
        if (inv.total > 0)  quickAmounts.push({ label: 'Brutto ' + inv.currency, amt: inv.total,  curr: inv.currency, type: 'gross' });
        if (inv.netto > 0)  quickAmounts.push({ label: 'Netto '  + inv.currency, amt: inv.netto,  curr: inv.currency, type: 'net'   });
        if (inv.vat   > 0)  quickAmounts.push({ label: 'VAT PLN', amt: Math.round(inv.vat * (inv.exchange_rate || 1) * 100)/100, curr: 'PLN', type: 'vat' });
    } else {
        if (inv.remaining > 0 && inv.remaining !== inv.total) quickAmounts.push({ label: 'Pozostałe', amt: inv.remaining, curr: 'PLN', type: 'gross' });
        if (inv.total > 0)  quickAmounts.push({ label: 'Brutto PLN', amt: inv.total, curr: 'PLN', type: 'gross' });
        if (inv.netto > 0)  quickAmounts.push({ label: 'Netto PLN',  amt: inv.netto, curr: 'PLN', type: 'net'   });
        if (inv.vat   > 0)  quickAmounts.push({ label: 'VAT PLN',    amt: inv.vat,   curr: 'PLN', type: 'vat'   });
    }

    html += '<div class="settle-col-label mt-1"><i class="ri-money-euro-circle-line"></i>Kwota alokacji</div>'
          + '<div class="d-flex flex-wrap gap-2 mb-3">';
    quickAmounts.forEach(function (qa) {
        html += '<button type="button" class="btn-quick-amt" data-amt="' + qa.amt + '" data-curr="' + esc(qa.curr) + '" data-type="' + esc(qa.type) + '" data-tx-id="' + esc(txId) + '">'
              + esc(qa.label) + '<small>' + fmtCurrency(qa.amt, qa.curr) + '</small>'
              + '</button>';
    });
    html += '</div>';

    html += '<div class="row g-2 mb-2 align-items-end">'
          + '<div class="col-5">'
          +   '<label class="form-label small text-muted mb-1" style="font-size:.7rem">Kwota</label>'
          +   '<input type="number" class="form-control form-control-sm" id="alloc-amt-' + esc(txId) + '" value="' + esc(defAmount) + '" step="0.01" min="0.01">'
          + '</div>'
          + '<div class="col-3">'
          +   '<label class="form-label small text-muted mb-1" style="font-size:.7rem">Waluta</label>'
          +   '<select class="form-select form-select-sm" id="alloc-curr-' + esc(txId) + '">'
          +   ['PLN','EUR','USD','GBP'].map(function (c) { return '<option' + (c === defCurr ? ' selected' : '') + '>' + c + '</option>'; }).join('')
          +   '</select>'
          + '</div>'
          + '<div class="col-4">'
          +   '<label class="form-label small text-muted mb-1" style="font-size:.7rem">Typ</label>'
          +   '<select class="form-select form-select-sm" id="alloc-type-' + esc(txId) + '">'
          +   '<option value="gross">Brutto</option><option value="net">Netto</option><option value="vat">VAT</option>'
          +   '</select>'
          + '</div>'
          + '</div>'
          + '<div class="mb-3">'
          +   '<input type="text" class="form-control form-control-sm" id="alloc-note-' + esc(txId) + '" placeholder="Uwaga opcjonalna (np. MPP – VAT)">'
          + '</div>'
          + '<button type="button" class="btn btn-primary w-100 btn-do-tx-alloc" data-tx-id="' + esc(txId) + '">'
          + '<i class="ri-link me-1"></i>Przypisz do faktury</button>';

    formEl.innerHTML = html;
    formEl.style.display = '';

    // Auto-aktywuj pierwszy szybki przycisk
    var firstQuick = formEl.querySelector('.btn-quick-amt');
    if (firstQuick) firstQuick.click();
}

// ── Szybkie kwoty ────────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-quick-amt');
    if (!btn) return;
    var txId = btn.dataset.txId;
    var amtEl  = document.getElementById('alloc-amt-'  + txId);
    var currEl = document.getElementById('alloc-curr-' + txId);
    var typeEl = document.getElementById('alloc-type-' + txId);
    if (amtEl)  amtEl.value  = parseFloat(btn.dataset.amt).toFixed(2);
    if (currEl) currEl.value = btn.dataset.curr;
    if (typeEl) typeEl.value = btn.dataset.type;
    btn.closest('.d-flex').querySelectorAll('.btn-quick-amt').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
});

// ── Wykonaj alokację ─────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-do-tx-alloc');
    if (!btn) return;
    var txId  = btn.dataset.txId;
    var state = openPanels[txId];
    if (!state || !state.invoice) { alert('Wybierz fakturę z listy wyników.'); return; }

    var inv   = state.invoice;
    var amt   = parseFloat(document.getElementById('alloc-amt-'  + txId)?.value || 0);
    var curr  = document.getElementById('alloc-curr-' + txId)?.value || 'PLN';
    var type  = document.getElementById('alloc-type-' + txId)?.value || 'gross';
    var note  = document.getElementById('alloc-note-' + txId)?.value || '';

    if (amt <= 0) { alert('Podaj kwotę większą od 0.'); return; }

    var body = new URLSearchParams({
        bank_transaction_id: txId,
        allocated_amount:    amt.toFixed(2),
        currency:            curr,
        allocation_type:     type,
        note:                note,
        _csrfToken:          getCsrf(),
    });
    if (inv.source === 'legacy') body.set('legacy_invoice_id', inv.id);
    else                         body.set('invoice_id',        inv.id);

    btn.disabled = true;
    fetch(urlAddAllocation, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        btn.disabled = false;
        if (d.success) {
            // Wyczyść zaznaczenie i formularz
            var resEl = document.getElementById('tx-search-results-' + txId);
            var formEl = document.getElementById('tx-alloc-form-' + txId);
            var searchEl = document.getElementById('tx-search-' + txId);
            if (resEl)   { resEl.style.display = 'none'; resEl.innerHTML = ''; }
            if (formEl)  { formEl.style.display = 'none'; formEl.innerHTML = ''; }
            if (searchEl) searchEl.value = '';
            openPanels[txId] = { invoice: null, source: null };
            // Odśwież listę alokacji
            loadTxAllocations(txId);
        } else {
            alert(d.error || 'Błąd przypisywania.');
        }
    })
    .catch(function () { btn.disabled = false; alert('Błąd połączenia.'); });
});

// Zamknij wyniki po kliknięciu poza
document.addEventListener('click', function (e) {
    if (e.target.closest('.tx-inv-search') || e.target.closest('[id^="tx-search-results-"]')) return;
    document.querySelectorAll('[id^="tx-search-results-"]').forEach(function (el) {
        // Nie ukrywaj jeśli jest zaznaczone
        if (!el.querySelector('.selected')) el.style.display = 'none';
    });
});

}());
</script>

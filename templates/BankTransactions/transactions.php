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
.settle-panel { background: #f8fafc; border-top: 2px solid #0d6efd22; }
.settle-panel .inv-result-row { cursor: pointer; transition: background .1s; }
.settle-panel .inv-result-row:hover { background: #e8f0fe; }
.settle-panel .inv-result-row.selected { background: #dbeafe; border-left: 3px solid #0d6efd; }
.alloc-badge { font-size: .7em; }
</style>

<script>
(function () {
'use strict';

var urlAddAllocation    = '<?= $this->Url->build(['controller' => 'Reconciliations', 'action' => 'addAllocation']) ?>';
var urlDeleteAllocation = '<?= $this->Url->build(['controller' => 'Reconciliations', 'action' => 'deleteAllocation', '_ext' => false]) ?>';
var csrfToken = (document.cookie.match(/csrfToken=([^;]+)/) || [])[1] || '';

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

function getCsrf() {
    var m = document.cookie.match(/csrfToken=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

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
        '<td colspan="8" class="p-0 settle-panel">'
      + '<div id="settle-panel-' + esc(txId) + '" class="px-4 py-3">'
      + '<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">'
      +   '<span class="fw-semibold"><i class="ri-link me-1 text-primary"></i>Rozlicz przelew</span>'
      +   '<span class="text-muted small">Kwota: <strong class="text-dark">' + fmtCurrency(txAmount, txCurr) + '</strong></span>'
      +   '<span class="badge bg-primary-subtle text-primary" id="tx-remaining-badge-' + esc(txId) + '">ładowanie…</span>'
      + '</div>'
      // Sekcja: istniejące alokacje
      + '<div id="tx-alloc-list-' + esc(txId) + '" class="mb-3"></div>'
      // Sekcja: szukaj faktury
      + '<div class="row g-2 mb-2">'
      +   '<div class="col-md-6">'
      +     '<label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.7rem">Szukaj faktury (nr, kontrahent, NIP)</label>'
      +     '<div class="input-group input-group-sm">'
      +       '<span class="input-group-text"><i class="ri-search-line"></i></span>'
      +       '<input type="text" class="form-control tx-inv-search" id="tx-search-' + esc(txId) + '" data-tx-id="' + esc(txId) + '" placeholder="FV/2026/…, nazwa, NIP…" autocomplete="off">'
      +       '<select class="form-select" id="tx-search-source-' + esc(txId) + '" style="max-width:110px">'
      +         '<option value="all">Wszystkie</option>'
      +         '<option value="system">Systemowe</option>'
      +         '<option value="legacy">Archiwum</option>'
      +       '</select>'
      +     '</div>'
      +     '<div id="tx-search-results-' + esc(txId) + '" class="border rounded mt-1" style="max-height:220px;overflow-y:auto;display:none"></div>'
      +   '</div>'
      +   '<div class="col-md-6" id="tx-alloc-form-' + esc(txId) + '" style="display:none"></div>'
      + '</div>'
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
    var listEl = document.getElementById('tx-alloc-list-' + txId);
    var badgeEl = document.getElementById('tx-remaining-badge-' + txId);
    if (!listEl) return;

    var remaining = d.remaining_amount != null ? d.remaining_amount : d.tx_amount;
    if (badgeEl) {
        badgeEl.textContent = 'Pozostało: ' + fmtCurrency(remaining, d.tx_currency);
        badgeEl.className = remaining > 0.005
            ? 'badge bg-warning-subtle text-warning border'
            : 'badge bg-success-subtle text-success border';
    }

    if (!d.allocations || !d.allocations.length) {
        listEl.innerHTML = '<div class="text-muted small fst-italic">Brak przypisanych faktur do tego przelewu.</div>';
        return;
    }

    var html = '<div class="small fw-semibold text-muted text-uppercase mb-1" style="font-size:.7rem">Przypisane faktury</div>'
             + '<div class="d-flex flex-wrap gap-2">';
    d.allocations.forEach(function (a) {
        var srcBadge = a.source === 'legacy'
            ? '<span class="badge bg-secondary-subtle text-secondary border alloc-badge me-1">archiwum</span>'
            : '<span class="badge bg-primary-subtle text-primary border alloc-badge me-1">sys</span>';
        html += '<div class="d-flex align-items-center gap-2 border rounded px-2 py-1 bg-white">'
              + srcBadge
              + '<span class="fw-semibold">' + esc(a.fullnumber) + '</span>'
              + '<span class="text-muted alloc-badge">' + esc(a.type_label) + '</span>'
              + '<span class="fw-semibold text-success">' + fmtCurrency(a.allocated_amount, a.currency) + '</span>'
              + (a.note ? '<span class="text-muted alloc-badge">' + esc(a.note) + '</span>' : '')
              + '<button type="button" class="btn btn-xs btn-outline-danger btn-del-alloc" data-alloc-id="' + esc(a.id) + '" data-tx-id="' + esc(txId) + '" title="Usuń">'
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
    var resEl = document.getElementById('tx-search-results-' + txId);
    if (!resEl) return;
    if (!results.length) {
        resEl.style.display = '';
        resEl.innerHTML = '<div class="text-muted small px-3 py-2 fst-italic">Brak wyników.</div>';
        return;
    }
    var html = '';
    results.forEach(function (inv) {
        var srcBadge = inv.source === 'legacy'
            ? '<span class="badge bg-secondary-subtle text-secondary border me-1" style="font-size:.7em">archiwum</span>'
            : '<span class="badge bg-primary-subtle text-primary border me-1" style="font-size:.7em">sys</span>';
        html += '<div class="inv-result-row d-flex align-items-center gap-2 px-3 py-2 border-bottom"'
              + ' data-tx-id="' + esc(txId) + '"'
              + ' data-inv=\'' + esc(JSON.stringify(inv)) + '\'>'
              + '<div class="flex-grow-1 min-width-0">'
              +   srcBadge
              +   '<span class="fw-semibold">' + esc(inv.fullnumber) + '</span>'
              +   ' <span class="text-muted small">' + esc(inv.contractor) + '</span>'
              + '</div>'
              + '<div class="text-end text-nowrap small">'
              +   stateLabel(inv.paymentstate)
              +   '<div class="fw-semibold">' + fmtCurrency(inv.remaining, inv.currency) + '</div>'
              +   '<div class="text-muted" style="font-size:.75em">z ' + fmtCurrency(inv.total, inv.currency) + '</div>'
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

    var isEur = inv.currency && inv.currency !== 'PLN';
    var defAmount = inv.remaining > 0 ? inv.remaining.toFixed(2) : inv.total.toFixed(2);
    var defCurr   = inv.currency || 'PLN';

    var html = '<label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.7rem">Kwota alokacji</label>'
             + '<div class="d-flex gap-2 flex-wrap mb-2">';

    // Szybkie przyciski kwot
    var quickAmounts = [];
    if (isEur) {
        if (inv.total   > 0) quickAmounts.push({ label: 'Brutto ' + inv.currency, amt: inv.total,  curr: inv.currency, type: 'gross' });
        if (inv.netto   > 0) quickAmounts.push({ label: 'Netto ' + inv.currency, amt: inv.netto,  curr: inv.currency, type: 'net' });
        if (inv.vat     > 0) quickAmounts.push({ label: 'VAT PLN', amt: Math.round(inv.vat * (inv.exchange_rate || 1) * 100) / 100, curr: 'PLN', type: 'vat' });
    } else {
        if (inv.total   > 0) quickAmounts.push({ label: 'Brutto PLN', amt: inv.total,  curr: 'PLN', type: 'gross' });
        if (inv.netto   > 0) quickAmounts.push({ label: 'Netto PLN',  amt: inv.netto,  curr: 'PLN', type: 'net' });
        if (inv.vat     > 0) quickAmounts.push({ label: 'VAT PLN',    amt: inv.vat,    curr: 'PLN', type: 'vat' });
    }
    if (inv.remaining > 0 && inv.remaining !== inv.total) {
        quickAmounts.unshift({ label: 'Pozostałe', amt: inv.remaining, curr: inv.currency, type: 'gross' });
    }

    quickAmounts.forEach(function (qa) {
        html += '<button type="button" class="btn btn-xs btn-outline-secondary btn-quick-amt"'
              + ' data-amt="' + qa.amt + '" data-curr="' + esc(qa.curr) + '" data-type="' + esc(qa.type) + '"'
              + ' data-tx-id="' + esc(txId) + '">'
              + esc(qa.label) + '<br><small>' + fmtCurrency(qa.amt, qa.curr) + '</small>'
              + '</button>';
    });
    html += '</div>';

    html += '<div class="row g-2 mb-2">'
          + '<div class="col-5"><input type="number" class="form-control form-control-sm" id="alloc-amt-' + esc(txId) + '"'
          + ' value="' + esc(defAmount) + '" step="0.01" min="0.01" placeholder="Kwota"></div>'
          + '<div class="col-3"><select class="form-select form-select-sm" id="alloc-curr-' + esc(txId) + '">'
          + ['PLN','EUR','USD','GBP'].map(function (c) { return '<option value="' + c + '"' + (c === defCurr ? ' selected' : '') + '>' + c + '</option>'; }).join('')
          + '</select></div>'
          + '<div class="col-4"><select class="form-select form-select-sm" id="alloc-type-' + esc(txId) + '">'
          + '<option value="gross">Brutto</option><option value="net">Netto</option><option value="vat">VAT</option>'
          + '</select></div>'
          + '</div>'
          + '<div class="mb-2"><input type="text" class="form-control form-control-sm" id="alloc-note-' + esc(txId) + '"'
          + ' placeholder="Uwaga (opcja)"></div>'
          + '<button type="button" class="btn btn-sm btn-primary btn-do-tx-alloc" data-tx-id="' + esc(txId) + '">'
          + '<i class="ri-check-line me-1"></i>Przypisz do faktury</button>';

    formEl.innerHTML = html;
    formEl.style.display = '';
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

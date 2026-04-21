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
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
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
</style>

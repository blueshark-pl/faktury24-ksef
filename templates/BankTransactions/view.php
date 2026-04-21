<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\BankStatementImport $import
 * @var \App\Model\Entity\BankTransaction[] $transactions
 * @var string $search
 * @var string $direction
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var int $limit
 * @var string $title
 */
$this->assign('title', $title ?? 'Transakcje importu');

$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';
$fnum  = fn($v)  => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';

$currentUrl = fn(array $extra = []) => array_merge(
    ['action' => 'view', $import->id],
    ['?' => array_filter(array_merge(
        ['q' => $search, 'dir' => $direction, 'page' => $page],
        $extra
    ), fn($v) => $v !== '' && $v !== null)]
);
?>

<!-- Nagłówek -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <?= $this->Html->link(
            '<i class="ri-arrow-left-line"></i>',
            ['action' => 'index'],
            ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => 'Wróć do listy importów']
        ) ?>
        <div>
            <h5 class="mb-0 fw-semibold"><?= h($import->filename ?? 'Import') ?></h5>
            <small class="text-muted">
                <?php if ($import->account_number): ?>
                    Rachunek: <code><?= h($import->account_number) ?></code> &nbsp;|&nbsp;
                <?php endif; ?>
                <?php if ($import->statement_from && $import->statement_to): ?>
                    <?= $fdate($import->statement_from) ?> – <?= $fdate($import->statement_to) ?> &nbsp;|&nbsp;
                <?php endif; ?>
                <strong><?= $import->new_count ?></strong> nowych / <?= $import->transaction_count ?> łącznie
            </small>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php if ($import->opening_balance !== null): ?>
            <span class="badge bg-light text-dark border small fw-normal">
                Otwarcie: <strong><?= $fnum($import->opening_balance) ?></strong> <?= h($import->currency ?? 'PLN') ?>
            </span>
        <?php endif; ?>
        <?php if ($import->closing_balance !== null): ?>
            <span class="badge bg-light text-dark border small fw-normal">
                Zamknięcie: <strong><?= $fnum($import->closing_balance) ?></strong> <?= h($import->currency ?? 'PLN') ?>
            </span>
        <?php endif; ?>
        <?= $this->Form->postLink(
            '<i class="ri-delete-bin-line me-1"></i> Usuń import',
            ['action' => 'delete', $import->id],
            [
                'class'   => 'btn btn-sm btn-outline-danger',
                'escape'  => false,
                'confirm' => 'Usunąć import „' . addslashes($import->filename ?? $import->id) . '" i wszystkie jego transakcje?',
            ]
        ) ?>
    </div>
</div>

<?= $this->Flash->render() ?>

<!-- Filtry -->
<form method="get" class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <input type="text" name="q" value="<?= h($search) ?>"
           class="form-control form-control-sm" style="max-width:280px;"
           placeholder="Szukaj: kontrahent, tytuł, referencja…">
    <select name="dir" class="form-select form-select-sm" style="max-width:150px;">
        <option value="">Wszystkie</option>
        <option value="C" <?= $direction === 'C' ? 'selected' : '' ?>>Wpływy (C)</option>
        <option value="D" <?= $direction === 'D' ? 'selected' : '' ?>>Wypływy (D)</option>
    </select>
    <button class="btn btn-sm btn-primary" type="submit"><i class="ri-search-line"></i></button>
    <?php if ($search || $direction): ?>
        <?= $this->Html->link(
            '<i class="ri-close-line"></i>',
            ['action' => 'view', $import->id],
            ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => 'Wyczyść filtry']
        ) ?>
    <?php endif; ?>
    <span class="text-muted small ms-auto"><?= $total ?> transakcji</span>
</form>

<!-- Tabela transakcji -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="ps-3 text-nowrap">Data waluty</th>
                    <th class="text-nowrap">Data ks.</th>
                    <th class="text-center">Typ</th>
                    <th class="text-end">Kwota</th>
                    <th>Kontrahent</th>
                    <th>Tytuł / opis</th>
                    <th class="pe-3">Referencja</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            Brak transakcji<?= ($search || $direction) ? ' dla podanych filtrów' : '' ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td class="ps-3 text-nowrap small"><?= $fdate($tx->value_date) ?></td>
                        <td class="text-nowrap small text-muted"><?= $fdate($tx->booking_date) ?></td>
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
                        <td class="small" style="max-width:200px;">
                            <?php if ($tx->party_name): ?>
                                <span class="fw-semibold"><?= h($tx->party_name) ?></span>
                                <?php if ($tx->party_account): ?>
                                    <br><code class="text-muted" style="font-size:.75em;"><?= h($tx->party_account) ?></code>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small" style="max-width:300px;">
                            <?php $t = $tx->title ?? ''; ?>
                            <?php if ($t): ?>
                                <span title="<?= h($t) ?>"><?= h(mb_strimwidth($t, 0, 90, '…')) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-3 small text-muted text-nowrap">
                            <?= h($tx->bank_reference ?? $tx->customer_reference ?? '—') ?>
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
            <?= ($page - 1) * $limit + 1 ?>–<?= min($page * $limit, $total) ?> z <?= $total ?>
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

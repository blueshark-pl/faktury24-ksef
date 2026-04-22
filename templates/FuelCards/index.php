<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $transactions
 * @var \Cake\ORM\ResultSet $accounts
 * @var int $total
 * @var int $page
 * @var int $limit
 * @var string $accountId
 * @var string $search
 * @var string $dateFrom
 * @var string $dateTo
 * @var string $cardFilter
 * @var string $autoFilter
 * @var string $stationFilter
 * @var object|null $sumData
 * @var string $title
 */
$this->assign('title', $title);

$fdate     = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i') : substr((string)$v, 0, 16)) : '—';
$fnum      = fn($v, $d = 2) => $v !== null ? number_format((float)$v, $d, ',', ' ') : '—';

$pages   = $limit > 0 ? (int)ceil($total / $limit) : 1;
$baseUrl = $this->Url->build(['action' => 'index']);

$urlWith = function (array $extra) use ($accountId, $search, $dateFrom, $dateTo, $cardFilter, $autoFilter, $stationFilter, $limit, $page) {
    return array_merge([
        'account_id' => $accountId,
        'q'          => $search,
        'date_from'  => $dateFrom,
        'date_to'    => $dateTo,
        'card'       => $cardFilter,
        'auto'       => $autoFilter,
        'station'    => $stationFilter,
        'limit'      => $limit,
        'page'       => $page,
    ], $extra);
};

// Liczba aktywnych filtrów
$activeFilters = 0;
if ($accountId !== '') $activeFilters++;
if ($search !== '') $activeFilters++;
if ($dateFrom !== '') $activeFilters++;
if ($dateTo !== '') $activeFilters++;
if ($cardFilter !== '') $activeFilters++;
if ($autoFilter !== '') $activeFilters++;
if ($stationFilter !== '') $activeFilters++;
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-gas-station-line me-1 text-warning"></i> Karty paliwowe E100
        <span class="text-muted fs-6 fw-normal">Transakcje</span>
    </h4>
    <div class="d-flex gap-2 flex-wrap">
        <!-- Salda kont -->
        <?php if (!$accounts->isEmpty()): ?>
            <div id="balance-badges" class="d-flex gap-1 flex-wrap">
                <?php foreach ($accounts as $acc): ?>
                    <span class="badge bg-light text-dark border balance-badge"
                          data-account-id="<?= h($acc->id) ?>"
                          style="cursor:pointer"
                          title="Kliknij aby odświeżyć saldo">
                        <i class="ri-bank-card-line me-1"></i><?= h($acc->label) ?>: <span class="bal-value">…</span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?= $this->Html->link(
            '<i class="ri-refresh-line me-1"></i> Synchronizuj',
            '#',
            ['class' => 'btn btn-outline-success btn-sm', 'escape' => false, 'id' => 'btn-sync']
        ) ?>
        <?= $this->Html->link(
            '<i class="ri-download-2-line me-1"></i> CSV',
            array_merge(['action' => 'exportCsv'], $urlWith([])),
            ['class' => 'btn btn-outline-secondary btn-sm', 'escape' => false]
        ) ?>
        <?= $this->Html->link(
            '<i class="ri-settings-3-line me-1"></i> Konta',
            ['action' => 'accounts'],
            ['class' => 'btn btn-outline-secondary btn-sm', 'escape' => false]
        ) ?>
    </div>
</div>

<!-- Zakładki nawigacyjne -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link active" href="<?= $this->Url->build(['action' => 'index']) ?>">
            <i class="ri-list-check me-1"></i> Transakcje
            <span class="badge bg-warning text-dark ms-1"><?= number_format($total, 0, ',', ' ') ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'cards']) ?>">
            <i class="ri-bank-card-2-line me-1"></i> Karty
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'limits']) ?>">
            <i class="ri-funds-line me-1"></i> Limity
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'stations']) ?>">
            <i class="ri-map-pin-2-line me-1"></i> Stacje
        </a>
    </li>
</ul>

<!-- Filtry -->
<div class="card mb-3 shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold small">
            <i class="ri-filter-3-line me-1"></i> Filtry
            <?php if ($activeFilters > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $activeFilters ?></span>
            <?php endif; ?>
        </span>
        <button class="btn btn-link btn-sm p-0" type="button" data-bs-toggle="collapse" data-bs-target="#filters-collapse">
            <i class="ri-arrow-down-s-line" id="filters-chevron"></i>
        </button>
    </div>
    <div class="collapse show" id="filters-collapse">
        <div class="card-body py-2">
            <form method="get" action="<?= $baseUrl ?>">
                <div class="row g-2 align-items-end">
                    <?php if (count($accounts) > 1): ?>
                        <div class="col-md-2">
                            <label class="form-label mb-1 small">Konto</label>
                            <select name="account_id" class="form-select form-select-sm">
                                <option value="">Wszystkie</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= h($acc->id) ?>"<?= $accountId === $acc->id ? ' selected' : '' ?>><?= h($acc->label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="account_id" value="<?= h($accountId) ?>">
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label mb-1 small">Data od</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= h($dateFrom) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 small">Data do</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= h($dateTo) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 small">Karta</label>
                        <input type="text" name="card" class="form-control form-control-sm" placeholder="Nr karty…" value="<?= h($cardFilter) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 small">Pojazd</label>
                        <input type="text" name="auto" class="form-control form-control-sm" placeholder="Nr rej.…" value="<?= h($autoFilter) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 small">Szukaj</label>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="Szukaj…" value="<?= h($search) ?>">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ri-search-line me-1"></i> Filtruj
                        </button>
                        <?php if ($activeFilters > 0): ?>
                            <a href="<?= $baseUrl ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-close-line me-1"></i> Wyczyść
                            </a>
                        <?php endif; ?>
                        <select name="limit" class="form-select form-select-sm ms-auto" style="width:auto" onchange="this.form.submit()">
                            <?php foreach ([25, 50, 100, 200] as $l): ?>
                                <option value="<?= $l ?>"<?= $l === $limit ? ' selected' : '' ?>><?= $l ?>/str.</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Podsumowanie -->
<?php if ($sumData): ?>
    <div class="d-flex gap-3 mb-3 flex-wrap small">
        <span class="text-muted">
            <i class="ri-bill-line me-1"></i>Suma:
            <strong><?= $fnum($sumData->total_sum ?? 0) ?> EUR</strong>
        </span>
        <span class="text-muted">
            <i class="ri-gas-station-line me-1"></i>Łączna ilość:
            <strong><?= $fnum($sumData->total_volume ?? 0, 2) ?> l</strong>
        </span>
        <span class="text-muted">Rekordów: <strong><?= number_format($total, 0, ',', ' ') ?></strong></span>
    </div>
<?php endif; ?>

<!-- Tabela transakcji -->
<?php if ($transactions->isEmpty()): ?>
    <div class="alert alert-info">
        <i class="ri-information-line me-1"></i>
        Brak transakcji.
        <?php if ($accounts->isEmpty()): ?>
            <a href="<?= $this->Url->build(['action' => 'addAccount']) ?>">Dodaj konto E100</a>, aby synchronizować dane.
        <?php else: ?>
            Kliknij <strong>Synchronizuj</strong>, aby pobrać transakcje z E100.
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle small">
            <thead class="table-light sticky-top">
                <tr>
                    <th>Data</th>
                    <th>Konto</th>
                    <th>Karta</th>
                    <th>Pojazd</th>
                    <th>Stacja</th>
                    <th>Usługa</th>
                    <th class="text-end">Ilość</th>
                    <th class="text-end">Cena</th>
                    <th class="text-end">Suma</th>
                    <th class="text-end">Rabat</th>
                    <th>Faktura E100</th>
                    <th>Kierowca</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                    <tr>
                        <td class="text-nowrap"><?= $fdate($tx->date) ?></td>
                        <td class="text-nowrap text-muted"><?= h($tx->e100_account->label ?? '—') ?></td>
                        <td class="text-nowrap font-monospace" style="font-size:.75rem">
                            <?= h($tx->card_shortname ?? $tx->card ?? '—') ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($tx->auto): ?>
                                <span class="badge bg-light text-dark border"><?= h($tx->auto) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($tx->station_id): ?>
                                <strong><?= h($tx->station_id) ?></strong>
                                <?php if ($tx->brand): ?><br><span class="text-muted"><?= h($tx->brand) ?></span><?php endif; ?>
                                <?php if ($tx->address): ?><br><small class="text-muted"><?= h(mb_strimwidth($tx->address, 0, 40, '…')) ?></small><?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= h($tx->service_name ?? '—') ?></td>
                        <td class="text-end"><?= $tx->volume !== null ? $fnum($tx->volume, 3) . ' l' : '—' ?></td>
                        <td class="text-end text-nowrap"><?= $tx->price !== null ? $fnum($tx->price, 4) . ' ' . h($tx->currency ?? 'EUR') : '—' ?></td>
                        <td class="text-end text-nowrap fw-semibold">
                            <?php if ($tx->sum !== null): ?>
                                <?= $fnum($tx->sum) ?> <?= h($tx->currency ?? 'EUR') ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if (($tx->discount ?? 0) > 0): ?>
                                <span class="text-success">-<?= $fnum($tx->discount) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($tx->invoice_ref): ?>
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                    <?= h($tx->invoice_ref) ?>
                                </span>
                                <?php if ($tx->invoice_date): ?>
                                    <br><small class="text-muted"><?= $tx->invoice_date->format('d.m.Y') ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($tx->driver ?? $tx->card_driver ?? '—') ?></td>
                        <td>
                            <?php if ($tx->confirmed): ?>
                                <i class="ri-check-line text-success" title="Potwierdzona"></i>
                            <?php endif; ?>
                            <?php if ($tx->exposed): ?>
                                <i class="ri-file-text-line text-info" title="Zafakturowana"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginacja -->
    <?php if ($pages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center flex-wrap">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <li class="page-item<?= $p === $page ? ' active' : '' ?>">
                        <a class="page-link" href="<?= $this->Url->build(array_merge(['action' => 'index'], $urlWith(['page' => $p]))) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<!-- Modal sync -->
<div class="modal fade" id="syncModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-refresh-line me-1"></i> Synchronizuj transakcje E100</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <?php if (count($accounts) > 1): ?>
                        <div class="col-12">
                            <label class="form-label small">Konto</label>
                            <select id="sync-account-id" class="form-select form-select-sm">
                                <option value="">Wszystkie aktywne</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= h($acc->id) ?>"><?= h($acc->label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-6">
                        <label class="form-label small">Data od</label>
                        <input type="date" id="sync-date-from" class="form-control form-control-sm"
                               value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Data do</label>
                        <input type="date" id="sync-date-to" class="form-control form-control-sm"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div id="sync-result" class="mt-3 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                <button type="button" class="btn btn-success btn-sm" id="btn-sync-confirm">
                    <i class="ri-refresh-line me-1"></i> Synchronizuj
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrfToken"]')?.content
                   || '<?= $this->request->getAttribute('csrfToken') ?>';

    // Otwórz modal sync
    document.getElementById('btn-sync')?.addEventListener('click', function (e) {
        e.preventDefault();
        new bootstrap.Modal(document.getElementById('syncModal')).show();
    });

    // Wykonaj sync
    document.getElementById('btn-sync-confirm')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Synchronizuję…';

        const formData = new FormData();
        formData.append('_csrfToken', csrfToken);
        formData.append('date_from', document.getElementById('sync-date-from').value);
        formData.append('date_to',   document.getElementById('sync-date-to').value);
        const accId = document.getElementById('sync-account-id')?.value;
        if (accId) formData.append('account_id', accId);

        fetch('<?= $this->Url->build(['action' => 'sync']) ?>', {
            method: 'POST',
            body: formData,
        })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('sync-result');
            el.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
            el.classList.add('alert', data.success ? 'alert-success' : 'alert-danger');
            el.innerHTML = '<i class="ri-information-line me-1"></i>' + data.message;
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line me-1"></i> Synchronizuj';
            if (data.imported > 0) {
                setTimeout(() => location.reload(), 2000);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line me-1"></i> Synchronizuj';
        });
    });

    // Pobierz salda
    document.querySelectorAll('.balance-badge').forEach(function (badge) {
        const accountId = badge.dataset.accountId;
        const valEl     = badge.querySelector('.bal-value');

        fetch('<?= $this->Url->build(['action' => 'balance']) ?>?account_id=' + encodeURIComponent(accountId))
            .then(r => r.json())
            .then(d => {
                if (d.success && d.balance) {
                    valEl.textContent = parseFloat(d.balance.sum).toLocaleString('pl-PL', {minimumFractionDigits: 2}) + ' ' + d.balance.currency;
                    const sum = parseFloat(d.balance.sum);
                    badge.classList.remove('bg-light');
                    badge.classList.add(sum < 0 ? 'bg-danger-subtle' : 'bg-success-subtle');
                } else {
                    valEl.textContent = 'błąd';
                }
            })
            .catch(() => { valEl.textContent = 'N/A'; });
    });
});
</script>

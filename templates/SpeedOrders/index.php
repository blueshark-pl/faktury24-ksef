<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $orders
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $limit
 * @var string $search
 * @var string $status
 * @var string $dateFrom
 * @var string $dateTo
 */

$this->assign('title', 'Zlecenia Speed');

// Status badge helper
$statusBadge = function(int $s): string {
    return match($s) {
        1 => '<span class="badge bg-secondary-transparent">Nowe</span>',
        2 => '<span class="badge bg-warning-transparent">W realizacji</span>',
        3 => '<span class="badge bg-success-transparent">Zrealizowane</span>',
        4 => '<span class="badge bg-danger-transparent">Anulowane</span>',
        default => '<span class="badge bg-light text-dark">' . h($s) . '</span>',
    };
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">Zlecenia <span class="text-muted fs-6 fw-normal">Speed ERP</span></h4>
    <button class="btn btn-primary btn-sm" id="btn-sync-orders">
        <i class="ri-refresh-line me-1"></i> Synchronizuj ze Speed
    </button>
</div>

<!-- Sync progress modal -->
<div class="modal fade" id="syncModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Synchronizacja zleceń</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <div id="sync-status" class="mb-2 text-muted small"></div>
        <div class="progress">
          <div id="sync-bar" class="progress-bar progress-bar-striped progress-bar-animated"
               role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div id="sync-result" class="mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>

<!-- Filtry -->
<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Szukaj (symbol, nabywca, trasa, tytuł)…" value="<?= h($search) ?>">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
            <option value="">-- Status --</option>
            <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Nowe</option>
            <option value="2" <?= $status === '2' ? 'selected' : '' ?>>W realizacji</option>
            <option value="3" <?= $status === '3' ? 'selected' : '' ?>>Zrealizowane</option>
            <option value="4" <?= $status === '4' ? 'selected' : '' ?>>Anulowane</option>
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm"
               placeholder="Data od" value="<?= h($dateFrom) ?>">
    </div>
    <div class="col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm"
               placeholder="Data do" value="<?= h($dateTo) ?>">
    </div>
    <div class="col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-outline-primary flex-grow-1">
            <i class="ri-search-line"></i> Filtruj
        </button>
        <?php if ($search !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>"
           class="btn btn-sm btn-outline-secondary" title="Wyczyść filtry">
            <i class="ri-close-line"></i>
        </a>
        <?php endif; ?>
    </div>
</form>

<p class="text-muted small mb-2">
    Znaleziono: <strong><?= number_format($total, 0, ',', ' ') ?></strong> zleceń
    <?php if ($total > $limit): ?> (strona <?= $page ?> z <?= $pages ?>)<?php endif; ?>
</p>

<div class="table-responsive">
<table class="table table-hover table-sm align-middle mb-0">
    <thead class="table-light">
        <tr>
            <th>Symbol</th>
            <th>Nabywca</th>
            <th>Trasa</th>
            <th>Data dok.</th>
            <th class="text-end">Netto</th>
            <th>Waluta</th>
            <th>Status</th>
            <th>Wystawił</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if ($orders->count() === 0): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">
            <?= $total === 0 ? 'Brak zleceń w bazie. Kliknij „Synchronizuj ze Speed", aby pobrać dane.' : 'Brak wyników dla podanych kryteriów.' ?>
        </td></tr>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
        <tr>
            <td>
                <a href="<?= $this->Url->build(['action' => 'view', $order->id]) ?>"
                   class="fw-semibold text-decoration-none"><?= h($order->symbol) ?></a>
                <?php if (!empty($order->ozn)): ?>
                    <div class="text-muted small"><?= h($order->ozn) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?= h($order->buyer_name) ?>
                <?php if (!empty($order->buyer_nip)): ?>
                    <div class="text-muted small"><?= h($order->buyer_nip) ?></div>
                <?php endif; ?>
            </td>
            <td class="text-truncate" style="max-width:240px" title="<?= h($order->route_description) ?>">
                <?= h($order->route_description) ?>
            </td>
            <td class="text-nowrap">
                <?= $order->date_doc ? h($order->date_doc->format('d.m.Y')) : '—' ?>
            </td>
            <td class="text-end text-nowrap">
                <?= $order->netto !== null ? number_format((float)$order->netto, 2, ',', ' ') : '—' ?>
            </td>
            <td><?= h($order->currency ?? 'PLN') ?></td>
            <td><?= $statusBadge((int)$order->status) ?></td>
            <td class="text-muted small"><?= h($order->nick_created) ?></td>
            <td class="text-end">
                <?php
                    $cur = strtoupper(trim((string)($order->currency ?? 'PLN')));
                    $fvAction = ($cur !== '' && $cur !== 'PLN') ? 'addCurrency' : 'addVat';
                    $fvUrl = $this->Url->build([
                        'controller' => 'Invoices',
                        'action'     => $fvAction,
                        '?'          => ['from_order_id' => $order->id],
                    ]);
                ?>
                <a href="<?= $this->Url->build(['action' => 'view', $order->id]) ?>"
                   class="btn btn-xs btn-outline-secondary py-0 px-1" title="Szczegóły">
                    <i class="ri-eye-line"></i>
                </a>
                <a href="<?= h($fvUrl) ?>"
                   class="btn btn-xs btn-outline-primary py-0 px-1 ms-1"
                   title="Wystaw fakturę na podstawie zlecenia">
                    <i class="ri-file-add-line"></i>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3">
<ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
        <a class="page-link" href="<?= $this->Url->build(['action' => 'index', '?' => array_merge(
            $search !== '' ? ['q' => $search] : [],
            $status !== '' ? ['status' => $status] : [],
            $dateFrom !== '' ? ['date_from' => $dateFrom] : [],
            $dateTo !== '' ? ['date_to' => $dateTo] : [],
            ['page' => $p]
        )]) ?>"><?= $p ?></a>
    </li>
    <?php endfor; ?>
</ul>
</nav>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnSync = document.getElementById('btn-sync-orders');
    const modal   = new bootstrap.Modal(document.getElementById('syncModal'));
    const bar     = document.getElementById('sync-bar');
    const status  = document.getElementById('sync-status');
    const result  = document.getElementById('sync-result');
    const csrfToken = '<?= $this->request->getAttribute('csrfToken') ?>';
    const syncUrl   = '<?= $this->Url->build(['action' => 'sync']) ?>';

    btnSync.addEventListener('click', function () {
        bar.style.width = '0%';
        bar.setAttribute('aria-valuenow', 0);
        status.textContent = 'Uruchamianie synchronizacji…';
        result.innerHTML = '';
        modal.show();

        let totalSaved   = 0;
        let totalUpdated = 0;
        let totalErrors  = [];
        let totalPages   = 1;

        function syncPage(page) {
            status.textContent = 'Pobieranie strony ' + page + ' z ' + totalPages + '…';
            const pct = totalPages > 1 ? Math.round((page - 1) / totalPages * 100) : 50;
            bar.style.width = pct + '%';
            bar.setAttribute('aria-valuenow', pct);

            fetch(syncUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ page: page }),
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    result.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Nieznany błąd') + '</div>';
                    bar.classList.add('bg-danger');
                    bar.classList.remove('progress-bar-animated');
                    return;
                }

                totalPages   = data.totalPages || 1;
                totalSaved  += data.saved   || 0;
                totalUpdated+= data.updated || 0;
                if (data.errors && data.errors.length) {
                    totalErrors = totalErrors.concat(data.errors);
                }

                if (page < totalPages) {
                    syncPage(page + 1);
                } else {
                    bar.style.width = '100%';
                    bar.setAttribute('aria-valuenow', 100);
                    bar.classList.remove('progress-bar-animated');

                    let html = '<div class="alert alert-success mb-2">'
                        + 'Zakończono! Nowe: <strong>' + totalSaved + '</strong>, '
                        + 'zaktualizowane: <strong>' + totalUpdated + '</strong>.'
                        + '</div>';
                    if (totalErrors.length) {
                        html += '<div class="alert alert-warning small">'
                            + 'Błędy (' + totalErrors.length + '):<br>'
                            + totalErrors.slice(0, 10).map(e => h(e)).join('<br>')
                            + (totalErrors.length > 10 ? '<br>…i więcej' : '')
                            + '</div>';
                    }
                    result.innerHTML = html;
                    status.textContent = 'Gotowe.';
                }
            })
            .catch(err => {
                result.innerHTML = '<div class="alert alert-danger">Błąd sieci: ' + err.message + '</div>';
                bar.classList.add('bg-danger');
                bar.classList.remove('progress-bar-animated');
            });
        }

        syncPage(1);
    });

    function h(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }
});
</script>

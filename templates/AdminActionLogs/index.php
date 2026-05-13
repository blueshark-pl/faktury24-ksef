<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserActionLog[] $logs
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $limit
 * @var string $q
 * @var string $controller
 * @var string $method
 * @var string $dateFrom
 * @var string $dateTo
 * @var string[] $controllers
 * @var array{total:int,today:int,last_24h:int,unique_users_7d:int} $stats
 */
$this->assign('title', __('Audyt akcji'));
$fdt = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i:s') : substr((string)$v, 0, 19)) : '—';
$methodColor = function (string $m): string {
    return match (strtoupper($m)) {
        'POST'   => 'bg-success-subtle text-success',
        'PUT', 'PATCH' => 'bg-info-subtle text-info',
        'DELETE' => 'bg-danger-subtle text-danger',
        default  => 'bg-secondary-subtle text-secondary',
    };
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-history-line me-1"></i><?= __('Audyt akcji (CRUD)') ?>
    </h4>
    <div class="text-muted small"><?= number_format($total, 0, ',', ' ') ?> <?= __('wpisów') ?></div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><?= __('Ogółem') ?></div>
        <div class="fs-4 fw-bold"><?= number_format($stats['total'], 0, ',', ' ') ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><?= __('Dzisiaj') ?></div>
        <div class="fs-4 fw-bold text-primary"><?= number_format($stats['today'], 0, ',', ' ') ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><?= __('Ostatnie 24h') ?></div>
        <div class="fs-4 fw-bold text-info"><?= number_format($stats['last_24h'], 0, ',', ' ') ?></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body py-2">
        <div class="small text-muted"><?= __('Aktywnych user. 7d') ?></div>
        <div class="fs-4 fw-bold text-success"><?= number_format($stats['unique_users_7d'], 0, ',', ' ') ?></div>
    </div></div></div>
</div>

<form method="get" class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-0"><?= __('Szukaj') ?></label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= h($q) ?>"
                       placeholder="<?= __('User, URL, entity_id…') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0"><?= __('Kontroler') ?></label>
                <select name="controller" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie') ?></option>
                    <?php foreach ($controllers as $c): ?>
                        <option value="<?= h($c) ?>" <?= $controller === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0"><?= __('Metoda') ?></label>
                <select name="method" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie') ?></option>
                    <?php foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $m): ?>
                        <option value="<?= $m ?>" <?= $method === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0"><?= __('Od') ?></label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= h($dateFrom) ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="ri-filter-3-line me-1"></i><?= __('Filtruj') ?>
                </button>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.85rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:160px"><?= __('Data') ?></th>
                    <th><?= __('Użytkownik') ?></th>
                    <th style="width:80px"><?= __('Metoda') ?></th>
                    <th><?= __('URL / Akcja') ?></th>
                    <th style="width:130px"><?= __('IP') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total === 0): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">
                        <i class="ri-inbox-2-line" style="font-size:2.5em"></i><br>
                        <div class="mt-2"><?= __('Brak wpisów.') ?></div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="ps-3 text-muted text-nowrap"><?= h($fdt($log->created)) ?></td>
                        <td>
                            <span class="fw-semibold"><?= h($log->username ?: '—') ?></span>
                            <?php if ($log->role): ?>
                                <span class="badge bg-light border text-muted ms-1" style="font-size:.62em"><?= h($log->role) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $methodColor((string)$log->method) ?> border" style="font-size:.65em">
                                <?= h($log->method) ?>
                            </span>
                        </td>
                        <td style="max-width:480px">
                            <div class="text-truncate small" title="<?= h($log->url) ?>"><?= h($log->url) ?></div>
                            <div class="text-muted small">
                                <?= h($log->controller) ?>::<?= h($log->action) ?>
                                <?php if ($log->entity_id): ?>
                                    <span class="text-muted">— <code style="font-size:.85em"><?= h(mb_substr($log->entity_id, 0, 36)) ?></code></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php if ($log->ip): ?><code style="font-size:.78em"><?= h($log->ip) ?></code><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center py-2">
        <small class="text-muted">
            <?= ($page - 1) * $limit + 1 ?>–<?= min($page * $limit, $total) ?>
            <?= __('z') ?> <?= number_format($total, 0, ',', ' ') ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php $qs = fn(array $extra) => array_filter(array_merge(['q' => $q, 'controller' => $controller, 'method' => $method, 'date_from' => $dateFrom, 'date_to' => $dateTo], $extra), fn($v) => $v !== '' && $v !== null); ?>
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= $this->Url->build(['action' => 'index', '?' => $qs(['page' => $page - 1])]) ?>">‹</a></li>
                <?php endif; ?>
                <li class="page-item active"><span class="page-link"><?= $page ?> / <?= $pages ?></span></li>
                <?php if ($page < $pages): ?>
                    <li class="page-item"><a class="page-link" href="<?= $this->Url->build(['action' => 'index', '?' => $qs(['page' => $page + 1])]) ?>">›</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php
/**
 * @var \App\View\AppView                       $this
 * @var \App\Model\Entity\UserLoginLog[]        $logs
 * @var int                                     $total
 * @var int                                     $page
 * @var int                                     $pages
 * @var int                                     $limit
 * @var string                                  $q
 * @var string                                  $role
 * @var string                                  $dateFrom
 * @var string                                  $dateTo
 * @var string[]                                $roles
 * @var array<string,string>                    $avatarMap user_id → URL avatara (z file_exists guardem)
 * @var array{total:int,today:int,last_24h:int,unique_7d:int} $stats
 */
$this->assign('title', __('Historia logowań'));
$fdt = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i:s') : substr((string)$v, 0, 19)) : '—';
$roleColor = function (string $r): string {
    return match (strtolower($r)) {
        'admin'             => 'bg-danger-subtle text-danger border border-danger-subtle',
        'client'            => 'bg-info-subtle text-info border border-info-subtle',
        'sales_manager',
        'spedycja_manager'  => 'bg-warning-subtle text-warning border border-warning-subtle',
        default             => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
    };
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-login-circle-line me-1"></i><?= __('Historia logowań') ?>
    </h4>
    <div class="text-muted small"><?= number_format($total, 0, ',', ' ') ?> <?= __('wpisów') ?></div>
</div>

<!-- KPI -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body py-2">
                <div class="small text-muted"><?= __('Logowań ogółem') ?></div>
                <div class="fs-4 fw-bold"><?= number_format($stats['total'], 0, ',', ' ') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body py-2">
                <div class="small text-muted"><?= __('Dzisiaj') ?></div>
                <div class="fs-4 fw-bold text-primary"><?= number_format($stats['today'], 0, ',', ' ') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body py-2">
                <div class="small text-muted"><?= __('Ostatnie 24h') ?></div>
                <div class="fs-4 fw-bold text-info"><?= number_format($stats['last_24h'], 0, ',', ' ') ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body py-2">
                <div class="small text-muted"><?= __('Unikalnych userów (7 dni)') ?></div>
                <div class="fs-4 fw-bold text-success"><?= number_format($stats['unique_7d'], 0, ',', ' ') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filtry -->
<form method="get" class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-0"><?= __('Szukaj') ?></label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= h($q) ?>"
                       placeholder="<?= __('Login, e-mail lub IP…') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0"><?= __('Rola') ?></label>
                <select name="role" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie') ?></option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= h($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= h($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0"><?= __('Od') ?></label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= h($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0"><?= __('Do') ?></label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= h($dateTo) ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="ri-filter-3-line me-1"></i><?= __('Filtruj') ?>
                </button>
                <?php if ($q !== '' || $role !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                    <a href="<?= $this->Url->build(['action' => 'index']) ?>"
                       class="btn btn-outline-secondary btn-sm" title="<?= __('Wyczyść') ?>">
                        <i class="ri-close-line"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<!-- Lista -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.875rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:170px"><?= __('Data i godzina') ?></th>
                    <th><?= __('Użytkownik') ?></th>
                    <th style="width:130px"><?= __('Rola') ?></th>
                    <th style="width:140px"><?= __('IP') ?></th>
                    <th><?= __('Przeglądarka') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total === 0): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="ri-inbox-2-line" style="font-size:2.5em"></i><br>
                            <div class="mt-2"><?= __('Brak logowań pasujących do filtrów.') ?></div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <?php
                        $avatarUrl = ($log->user_id && !empty($avatarMap[(string)$log->user_id]))
                            ? $avatarMap[(string)$log->user_id]
                            : null;
                        // Inicjał z pierwszego znaku username (lub e-mail)
                        $initial = mb_strtoupper(mb_substr((string)$log->username, 0, 1));
                    ?>
                    <tr>
                        <td class="ps-3 text-muted text-nowrap"><?= h($fdt($log->logged_at)) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($avatarUrl): ?>
                                    <img src="<?= h($avatarUrl) ?>" alt=""
                                         style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:1px solid #e5e7eb">
                                <?php else: ?>
                                    <span class="d-inline-flex align-items-center justify-content-center"
                                          style="width:28px;height:28px;border-radius:50%;background:rgba(var(--primary-rgb),.15);color:rgb(var(--primary-rgb));font-size:.75rem;font-weight:700;flex-shrink:0">
                                        <?= h($initial) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="fw-semibold"><?= h($log->username) ?></span>
                                <?php if ($log->user_id): ?>
                                    <a href="<?= $this->Url->build(['controller' => 'AdminUsers', 'action' => 'edit', $log->user_id]) ?>"
                                       class="text-muted" title="<?= __('Edytuj użytkownika') ?>">
                                        <i class="ri-external-link-line"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($log->role): ?>
                                <span class="badge <?= $roleColor((string)$log->role) ?>" style="font-size:.7em">
                                    <?= h($log->role) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log->ip): ?>
                                <code style="font-size:.78em"><?= h($log->ip) ?></code>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-truncate" style="max-width:300px"
                            title="<?= h((string)($log->user_agent ?? '')) ?>">
                            <span class="text-muted small"><?= h(mb_substr((string)($log->user_agent ?? '—'), 0, 80)) ?></span>
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
            <?= ($page - 1) * $limit + 1 ?>–<?= min($page * $limit, $total) ?>
            <?= __('z') ?> <?= number_format($total, 0, ',', ' ') ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $qs = function (array $extra) use ($q, $role, $dateFrom, $dateTo): array {
                    return array_filter(array_merge([
                        'q' => $q, 'role' => $role, 'date_from' => $dateFrom, 'date_to' => $dateTo,
                    ], $extra), fn($v) => $v !== '' && $v !== null);
                };
                ?>
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $this->Url->build(['action' => 'index', '?' => $qs(['page' => $page - 1])]) ?>">‹</a>
                    </li>
                <?php endif; ?>
                <li class="page-item active"><span class="page-link"><?= $page ?> / <?= $pages ?></span></li>
                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $this->Url->build(['action' => 'index', '?' => $qs(['page' => $page + 1])]) ?>">›</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

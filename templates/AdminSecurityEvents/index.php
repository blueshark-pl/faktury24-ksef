<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\UserSecurityEvent[] $events
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $limit
 * @var string $q
 * @var string $type
 * @var string $dateFrom
 * @var string $dateTo
 * @var string[] $types
 * @var array{total:int,last_24h:int,failed_logins:int,unique_ips_24h:int} $stats
 */
$this->assign('title', __('Wydarzenia bezpieczeństwa'));

$fdt = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i:s') : substr((string)$v, 0, 19)) : '—';

$typeLabels = [
    'failed_login'        => ['lbl' => __('Błędne logowanie'),     'cls' => 'bg-danger-subtle text-danger',     'icon' => 'ri-shield-cross-line'],
    'login_locked'        => ['lbl' => __('Konto zablokowane'),    'cls' => 'bg-danger text-white',             'icon' => 'ri-lock-line'],
    '2fa_enabled'         => ['lbl' => __('2FA włączone'),         'cls' => 'bg-success-subtle text-success',   'icon' => 'ri-shield-check-line'],
    '2fa_disabled'        => ['lbl' => __('2FA wyłączone'),        'cls' => 'bg-warning-subtle text-warning',   'icon' => 'ri-shield-cross-line'],
    '2fa_failed'          => ['lbl' => __('Błędny kod 2FA'),       'cls' => 'bg-warning-subtle text-warning',   'icon' => 'ri-shield-keyhole-line'],
    'webauthn_added'      => ['lbl' => __('Dodano klucz WebAuthn'),'cls' => 'bg-info-subtle text-info',         'icon' => 'ri-key-line'],
    'webauthn_removed'    => ['lbl' => __('Usunięto klucz WebAuthn'),'cls' => 'bg-secondary-subtle text-secondary','icon' => 'ri-key-2-line'],
    'password_changed'    => ['lbl' => __('Zmiana hasła'),         'cls' => 'bg-info-subtle text-info',         'icon' => 'ri-lock-password-line'],
    'password_reset_sent' => ['lbl' => __('Wysłano reset hasła'),  'cls' => 'bg-secondary-subtle text-secondary','icon' => 'ri-mail-lock-line'],
];
$renderType = function (string $t) use ($typeLabels): string {
    $info = $typeLabels[$t] ?? ['lbl' => $t, 'cls' => 'bg-light text-dark border', 'icon' => 'ri-information-line'];
    return '<span class="badge ' . $info['cls'] . ' border" style="font-size:.7em">'
         . '<i class="' . $info['icon'] . ' me-1"></i>' . h($info['lbl']) . '</span>';
};
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-shield-flash-line me-1"></i><?= __('Wydarzenia bezpieczeństwa') ?>
    </h4>
    <div class="text-muted small"><?= number_format($total, 0, ',', ' ') ?> <?= __('wpisów') ?></div>
</div>

<!-- KPI -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted"><?= __('Ogółem') ?></div>
            <div class="fs-4 fw-bold"><?= number_format($stats['total'], 0, ',', ' ') ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted"><?= __('Ostatnie 24h') ?></div>
            <div class="fs-4 fw-bold text-info"><?= number_format($stats['last_24h'], 0, ',', ' ') ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted"><?= __('Failed logins 24h') ?></div>
            <div class="fs-4 fw-bold text-danger"><?= number_format($stats['failed_logins'], 0, ',', ' ') ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100"><div class="card-body py-2">
            <div class="small text-muted"><?= __('Unikalnych IP 24h') ?></div>
            <div class="fs-4 fw-bold text-warning"><?= number_format($stats['unique_ips_24h'], 0, ',', ' ') ?></div>
        </div></div>
    </div>
</div>

<!-- Filtry -->
<form method="get" class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-0"><?= __('Szukaj') ?></label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= h($q) ?>"
                       placeholder="<?= __('User / IP…') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0"><?= __('Typ') ?></label>
                <select name="type" class="form-select form-select-sm">
                    <option value=""><?= __('Wszystkie') ?></option>
                    <?php foreach ($types as $tp): ?>
                        <option value="<?= h($tp) ?>" <?= $type === $tp ? 'selected' : '' ?>>
                            <?= h($typeLabels[$tp]['lbl'] ?? $tp) ?>
                        </option>
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
                <?php if ($q !== '' || $type !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                    <a href="<?= $this->Url->build(['action' => 'index']) ?>"
                       class="btn btn-outline-secondary btn-sm" title="<?= __('Wyczyść') ?>">
                        <i class="ri-close-line"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.875rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:170px"><?= __('Data') ?></th>
                    <th style="width:200px"><?= __('Typ wydarzenia') ?></th>
                    <th><?= __('Użytkownik') ?></th>
                    <th style="width:140px"><?= __('IP') ?></th>
                    <th><?= __('Szczegóły') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total === 0): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">
                        <i class="ri-shield-line" style="font-size:2.5em"></i><br>
                        <div class="mt-2"><?= __('Brak wydarzeń.') ?></div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($events as $ev): ?>
                    <tr>
                        <td class="ps-3 text-muted text-nowrap"><?= h($fdt($ev->created)) ?></td>
                        <td><?= $renderType((string)$ev->event_type) ?></td>
                        <td>
                            <?php if ($ev->username): ?>
                                <span class="fw-semibold"><?= h($ev->username) ?></span>
                                <?php if ($ev->user_id): ?>
                                    <a href="<?= $this->Url->build(['controller' => 'AdminUsers', 'action' => 'edit', $ev->user_id]) ?>"
                                       class="text-muted ms-1"><i class="ri-external-link-line"></i></a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php if ($ev->ip): ?><code style="font-size:.78em"><?= h($ev->ip) ?></code><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                        <td class="text-muted small"><?= h(mb_substr((string)($ev->details ?? '—'), 0, 120)) ?></td>
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
                $qs = fn(array $extra) => array_filter(array_merge(['q' => $q, 'type' => $type, 'date_from' => $dateFrom, 'date_to' => $dateTo], $extra), fn($v) => $v !== '' && $v !== null);
                ?>
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

<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Notification> $notifications
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $limit
 * @var string $filter
 * @var int $unreadCount
 */
$this->assign('title', __('Powiadomienia'));

$sevIcon = function (string $s): string {
    return match (strtolower($s)) {
        'error', 'critical' => 'ri-error-warning-line',
        'warning', 'warn'   => 'ri-alert-line',
        'success'           => 'ri-checkbox-circle-line',
        default             => 'ri-information-line',
    };
};
$sevCls = function (string $s): string {
    return match (strtolower($s)) {
        'error', 'critical' => 'bg-danger-subtle text-danger',
        'warning', 'warn'   => 'bg-warning-subtle text-warning',
        'success'           => 'bg-success-subtle text-success',
        default             => 'bg-info-subtle text-info',
    };
};

$csrf = (string)$this->request->getAttribute('csrfToken');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-notification-3-line me-1"></i><?= __('Powiadomienia') ?>
        <?php if ($unreadCount > 0): ?>
            <span class="badge bg-danger ms-2"><?= (int)$unreadCount ?> <?= __('nieprzeczytanych') ?></span>
        <?php endif; ?>
    </h4>
    <div class="d-flex gap-2 align-items-center">
        <div class="btn-group btn-group-sm" role="group">
            <a href="<?= $this->Url->build(['action' => 'index', '?' => ['filter' => 'all']]) ?>"
               class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= __('Wszystkie') ?></a>
            <a href="<?= $this->Url->build(['action' => 'index', '?' => ['filter' => 'unread']]) ?>"
               class="btn <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= __('Nieprzeczytane') ?></a>
        </div>
        <?php if ($unreadCount > 0): ?>
            <form method="post" action="<?= $this->Url->build(['action' => 'markAllRead']) ?>" style="display:inline">
                <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="ri-check-double-line me-1"></i><?= __('Oznacz wszystkie jako przeczytane') ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <?php if (empty($total)): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="ri-notification-off-line d-block mb-2" style="font-size:2.4rem"></i>
            <?= __('Brak powiadomień') ?>
        </div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($notifications as $n):
                $unread = !$n->is_read;
                $created = $n->created instanceof \DateTimeInterface
                    ? $n->created->format('Y-m-d H:i')
                    : (string)$n->created;
            ?>
                <li class="list-group-item <?= $unread ? 'bg-light' : '' ?>">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="<?= $sevCls((string)$n->severity) ?> border d-inline-flex align-items-center justify-content-center"
                             style="width:42px;height:42px;border-radius:50%;flex-shrink:0">
                            <i class="<?= $sevIcon((string)$n->severity) ?>" style="font-size:1.2rem"></i>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex justify-content-between align-items-start mb-1 gap-2">
                                <strong class="<?= $unread ? 'text-dark' : 'text-muted' ?>"><?= h($n->title) ?></strong>
                                <span class="text-muted small text-nowrap"><?= h($created) ?></span>
                            </div>
                            <?php if (!empty($n->message)): ?>
                                <div class="text-muted small mb-1"><?= nl2br(h($n->message)) ?></div>
                            <?php endif; ?>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <?php if (!empty($n->type)): ?>
                                    <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.65em"><?= h($n->type) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($n->action_url)): ?>
                                    <a href="<?= h($n->action_url) ?>" class="small text-primary text-decoration-none">
                                        <?= h(!empty($n->action_label) ? $n->action_label : __('Otwórz')) ?>
                                        <i class="ri-arrow-right-line"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex gap-1 align-items-center" style="flex-shrink:0">
                            <?php if ($unread): ?>
                                <form method="post" action="<?= $this->Url->build(['action' => 'markRead', $n->id]) ?>" style="display:inline">
                                    <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary"
                                            title="<?= __('Oznacz jako przeczytane') ?>">
                                        <i class="ri-check-line"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= $this->Url->build(['action' => 'delete', $n->id]) ?>" style="display:inline"
                                  onsubmit="return confirm('<?= __('Usunąć to powiadomienie?') ?>')">
                                <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>">
                                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger"
                                        title="<?= __('Usuń') ?>">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($pages > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    <?= __('Strona') ?> <?= (int)$page ?> <?= __('z') ?> <?= (int)$pages ?>
                    · <?= (int)$total ?> <?= __('powiadomień') ?>
                </div>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $mkUrl = function (int $p) use ($filter) {
                        return $this->Url->build(['action' => 'index', '?' => array_filter(['page' => $p, 'filter' => $filter])]);
                    };
                    ?>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page > 1 ? $mkUrl($page - 1) : '#' ?>">&laquo;</a>
                    </li>
                    <?php
                    $start = max(1, $page - 3);
                    $end   = min($pages, $page + 3);
                    for ($p = $start; $p <= $end; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $mkUrl($p) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page < $pages ? $mkUrl($page + 1) : '#' ?>">&raquo;</a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

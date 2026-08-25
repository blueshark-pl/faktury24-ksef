<?php
/**
 * @var \App\View\AppView $this
 * @var array $mentions
 * @var bool $showRead
 */
$this->assign('title', __('Wspomniano mnie'));
$activityIcons = [
    'phone_call' => 'ri-phone-line', 'email_out' => 'ri-mail-send-line', 'email_in' => 'ri-mail-download-line',
    'meeting' => 'ri-calendar-event-line', 'note' => 'ri-sticky-note-line', 'task' => 'ri-checkbox-line',
    'file' => 'ri-attachment-2', 'stage_change' => 'ri-arrow-right-up-line',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="ri-at-line text-primary"></i> <?= __('Wspomniano mnie') ?></h4>
        <div class="small text-muted"><?= __('Aktywności w których wpisano @twoj_login/@twoj_email — nowe automatycznie oznaczają się jako przeczytane po otwarciu.') ?></div>
    </div>
    <div>
        <a href="?<?= $showRead ? '' : 'all=1' ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-<?= $showRead ? 'inbox-line' : 'archive-line' ?>"></i>
            <?= $showRead ? __('Tylko nieprzeczytane') : __('Pokaż wszystkie') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary"><i class="ri-arrow-left-line"></i> <?= __('Wróć do CRM') ?></a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($mentions)): ?>
            <div class="text-center text-muted py-5">
                <i class="ri-at-line" style="font-size: 32px; color: #10b981;"></i>
                <div class="mt-2"><?= $showRead ? __('Brak wspomnień w twojej historii.') : __('Brak nowych wspomnień — wszystko przeczytane 🎉') ?></div>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($mentions as $m):
                    $act = $m->lead_activity ?? null;
                    $lead = $m->lead ?? null;
                    $byUser = $m->by_user ?? null;
                    $byName = $byUser ? trim(($byUser->first_name ?? '') . ' ' . ($byUser->last_name ?? '')) : __('Nieznany');
                    $ico = $act ? ($activityIcons[$act->activity_type] ?? 'ri-message-3-line') : 'ri-message-3-line';
                    $isUnread = !$m->seen_at;
                ?>
                <a href="<?= $this->Url->build(['action' => 'view', $m->lead_id, '#' => 'act-' . $m->activity_id]) ?>"
                   class="list-group-item list-group-item-action" style="<?= $isUnread ? 'background: #eff6ff; border-left: 3px solid #2563eb;' : '' ?>">
                    <div class="d-flex gap-3 align-items-start">
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: <?= $isUnread ? '#2563eb' : '#e2e8f0' ?>; color: <?= $isUnread ? '#fff' : '#5e6c84' ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="<?= $ico ?>"></i>
                        </div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?= h($lead?->company_name ?? '?') ?></strong>
                                    <?php if ($act): ?><span class="text-muted small">· <?= h($act->activity_type) ?></span><?php endif; ?>
                                </div>
                                <div class="small text-muted"><?= h($m->created->format('d.m.Y H:i')) ?></div>
                            </div>
                            <?php if ($act && $act->subject): ?>
                                <div class="fw-semibold"><?= h($act->subject) ?></div>
                            <?php endif; ?>
                            <?php if ($act && $act->body): ?>
                                <div class="small text-muted" style="max-height: 60px; overflow: hidden;">
                                    <?= h(mb_substr($act->body, 0, 200)) ?><?= mb_strlen($act->body) > 200 ? '…' : '' ?>
                                </div>
                            <?php endif; ?>
                            <div class="small text-muted mt-1">
                                <i class="ri-user-line"></i> <?= __('Od:') ?> <?= h($byName) ?>
                                <?php if ($isUnread): ?><span class="badge bg-primary ms-2">Nowe</span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

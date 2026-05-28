<?php
/**
 * @var \App\View\AppView $this
 * @var array $card
 * @var array $severityColors
 * @var bool $compactMode
 */
$fnum  = static fn ($v) => number_format((float)$v, 2, ',', ' ');
$fnum0 = static fn ($v) => number_format((float)$v, 0, ',', ' ');

$severity = $card['severity'] ?? 'none';
$borderColor = $severityColors[$severity] ?? '#e5e7eb';

$dueLabel = '';
$dueClass = 'kanban-card-due';
if ($card['paymentstate'] === 'paid') {
    $dueLabel = 'Opłacona' . ($card['paid_at_str'] ? ' · ' . substr($card['paid_at_str'], 0, 10) : '');
} elseif ($card['is_snoozed']) {
    $dueLabel = '💤 Odłożona do ' . $card['snooze_until_str'];
} elseif ($card['days_to_due'] !== null) {
    $d = $card['days_to_due'];
    if ($d < 0) {
        $dueLabel = '⚠ ' . abs($d) . ' dni po terminie';
        $dueClass .= ' overdue';
    } elseif ($d === 0) {
        $dueLabel = '📅 Dziś termin';
        $dueClass .= ' overdue';
    } else {
        $dueLabel = '📅 Za ' . $d . ' dni (' . $card['paymentdate_str'] . ')';
    }
}

$contractorInitials = '';
if (!empty($card['contractor'])) {
    $parts = preg_split('/\s+/', trim($card['contractor']));
    $contractorInitials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
}

$cardClasses = ['kanban-card'];
if ($compactMode) $cardClasses[] = 'compact';
if ($card['pinned']) $cardClasses[] = 'pinned';
if ($card['is_stale']) $cardClasses[] = 'stale';
?>
<div class="<?= h(implode(' ', $cardClasses)) ?>"
     style="border-left-color: <?= h($borderColor) ?>"
     data-invoice-id="<?= h($card['id']) ?>"
     data-fullnumber="<?= h($card['fullnumber']) ?>"
     data-contractor="<?= h($card['contractor']) ?>"
     data-nip="<?= h($card['nip']) ?>"
     data-total="<?= h($card['total']) ?>"
     data-remaining="<?= h($card['remaining']) ?>"
     data-alreadypaid="<?= h($card['alreadypaid']) ?>"
     data-currency="<?= h($card['currency']) ?>"
     data-type="<?= h($card['type']) ?>"
     data-paymentstate="<?= h($card['paymentstate']) ?>"
     data-paymentdate="<?= h($card['paymentdate_str'] ?? '') ?>"
     data-snooze-until="<?= h($card['snooze_until_str'] ?? '') ?>"
     data-dispute-flag="<?= $card['dispute_flag'] ? '1' : '0' ?>"
     data-dispute-reason="<?= h($card['dispute_reason'] ?? '') ?>"
     data-assigned-to="<?= h($card['assigned_to_user_id'] ?? '') ?>"
     data-pinned="<?= $card['pinned'] ? '1' : '0' ?>"
     data-severity="<?= h($severity) ?>"
     data-days-to-due="<?= h($card['days_to_due'] ?? '') ?>"
     data-progress-pct="<?= h($card['progress_pct']) ?>">

    <div class="d-flex justify-content-between align-items-start gap-1">
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center gap-1">
                <input type="checkbox" class="kanban-card-checkbox form-check-input" style="margin:0;width:13px;height:13px">
                <div class="kanban-card-num text-truncate" title="<?= h($card['fullnumber']) ?>">
                    <?= h($card['fullnumber']) ?>
                </div>
            </div>
            <?php if (!$compactMode): ?>
                <div class="kanban-card-contractor text-truncate" title="<?= h($card['contractor']) ?>">
                    <?= h($card['contractor']) ?>
                    <?php if (!empty($card['nip'])): ?> · <?= h($card['nip']) ?><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="dropdown">
            <button type="button" class="btn btn-sm btn-link text-muted p-0 px-1" data-bs-toggle="dropdown"
                    style="line-height:1" aria-label="Akcje">
                <i class="ri-more-2-fill"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="font-size:.8rem">
                <li><a class="dropdown-item" href="#" data-card-action="open"><i class="ri-external-link-line"></i> Otwórz fakturę</a></li>
                <li><a class="dropdown-item" href="#" data-card-action="reminder"><i class="ri-mail-send-line text-warning"></i> Wyślij przypomnienie…</a></li>
                <li><a class="dropdown-item" href="#" data-card-action="note"><i class="ri-chat-1-line"></i> Notatki / log</a></li>
                <li><a class="dropdown-item" href="#" data-card-action="snooze"><i class="ri-zzz-line"></i> Odłóż…</a></li>
                <li><a class="dropdown-item" href="#" data-card-action="assign"><i class="ri-user-line"></i> Przypisz…</a></li>
                <li><a class="dropdown-item" href="#" data-card-action="pin"><i class="ri-pushpin-line"></i> <?= $card['pinned'] ? 'Odepnij' : 'Przypnij' ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" data-card-action="ai-suggest"><i class="ri-sparkling-2-line text-primary"></i> AI: następna akcja</a></li>
            </ul>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-baseline mt-1">
        <span class="kanban-card-amount<?= $card['currency'] === 'EUR' ? ' text-success' : '' ?>">
            <?= $fnum($card['remaining']) ?> <?= h($card['currency']) ?>
        </span>
        <?php if (!$compactMode && $card['progress_pct'] > 0 && $card['progress_pct'] < 100): ?>
            <span class="small text-muted"><?= $card['progress_pct'] ?>%</span>
        <?php endif; ?>
    </div>

    <?php if (!$compactMode && $card['progress_pct'] > 0): ?>
        <div class="kanban-progress">
            <div style="width: <?= $card['progress_pct'] ?>%"></div>
        </div>
    <?php endif; ?>

    <div class="<?= $dueClass ?> mt-1"><?= h($dueLabel) ?></div>

    <?php if (!empty($card['dispute_reason'])): ?>
        <div class="text-dark small fst-italic mt-1" title="<?= h($card['dispute_reason']) ?>">
            <i class="ri-scales-3-line"></i> <?= h(mb_strimwidth($card['dispute_reason'], 0, 40, '…')) ?>
        </div>
    <?php endif; ?>

    <?php if (!$compactMode): ?>
        <div class="kanban-card-meta">
            <?php if ($contractorInitials !== ''): ?>
                <span class="kanban-card-avatar" title="<?= h($card['contractor']) ?>"><?= h($contractorInitials) ?></span>
            <?php endif; ?>
            <?php if ($card['is_stale']): ?>
                <span class="text-danger" title="Nieruszana ponad 7 dni"><i class="ri-fire-line"></i></span>
            <?php endif; ?>
            <?php if (!empty($card['assigned_to_user_id'])): ?>
                <span class="text-info" title="Przypisana"><i class="ri-user-fill"></i></span>
            <?php endif; ?>
            <span class="ms-auto"><?= h(strtoupper($card['type'])) ?></span>
        </div>
    <?php endif; ?>
</div>

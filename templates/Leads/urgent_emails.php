<?php
/**
 * @var \App\View\AppView $this
 * @var array $byLead
 * @var array $stats
 * @var bool $onlyMine
 * @var bool $onlyUrgent
 */
$this->assign('title', __('Pilne emaile') . ' (' . $stats['total'] . ')');

$sentColors = [
    'positive' => ['bg' => '#d1fae5', 'text' => '#059669', 'label' => 'Pozytywny', 'emoji' => '😊'],
    'neutral'  => ['bg' => '#e2e8f0', 'text' => '#475569', 'label' => 'Neutralny', 'emoji' => '😐'],
    'negative' => ['bg' => '#fee2e2', 'text' => '#dc2626', 'label' => 'Negatywny', 'emoji' => '😠'],
    'urgent'   => ['bg' => '#fed7aa', 'text' => '#ea580c', 'label' => 'PILNE',     'emoji' => '🚨'],
];
$intentLabels = [
    'quote_request' => 'Zapytanie o wycenę', 'complaint' => 'Reklamacja', 'follow_up' => 'Follow-up',
    'thank_you' => 'Podziękowanie', 'inquiry' => 'Zapytanie', 'payment' => 'Płatność',
    'spam' => 'Spam', 'other' => 'Inne',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="ri-alarm-warning-line text-danger"></i> <?= __('Pilne emaile') ?></h4>
        <div class="small text-muted"><?= __('AI kolejka - wymaga uwagi handlu (urgency ≥4 lub action_required)') ?></div>
    </div>
    <div class="d-flex gap-2">
        <div class="btn-group btn-group-sm" role="group">
            <a href="?" class="btn <?= !$onlyMine && !$onlyUrgent ? 'btn-primary' : 'btn-outline-primary' ?>"><?= __('Wszystkie') ?></a>
            <a href="?mine=1" class="btn <?= $onlyMine ? 'btn-primary' : 'btn-outline-primary' ?>"><?= __('Tylko moje') ?></a>
            <a href="?urgent=1" class="btn <?= $onlyUrgent ? 'btn-danger' : 'btn-outline-danger' ?>"><?= __('Tylko urgency=5') ?></a>
        </div>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-arrow-left-line"></i> <?= __('Wróć do CRM') ?>
        </a>
    </div>
</div>

<!-- KPI kafelki -->
<div class="row g-2 mb-4">
    <div class="col-md-3">
        <div class="card border-0" style="background: #fef3c7;">
            <div class="card-body text-center py-3">
                <div class="fw-bold" style="font-size: 28px; color: #b45309;"><?= (int)$stats['total'] ?></div>
                <div class="small text-muted"><?= __('W kolejce') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0" style="background: #fee2e2;">
            <div class="card-body text-center py-3">
                <div class="fw-bold" style="font-size: 28px; color: #dc2626;"><?= (int)$stats['urgent'] ?></div>
                <div class="small text-muted"><?= __('Urgency ≥4') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0" style="background: #fed7aa;">
            <div class="card-body text-center py-3">
                <div class="fw-bold" style="font-size: 28px; color: #ea580c;"><?= (int)$stats['action'] ?></div>
                <div class="small text-muted"><?= __('Wymaga akcji') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0" style="background: #fee2e2;">
            <div class="card-body text-center py-3">
                <div class="fw-bold" style="font-size: 28px; color: #dc2626;"><?= (int)$stats['complaint'] ?></div>
                <div class="small text-muted"><?= __('Reklamacje') ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($byLead)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="ri-checkbox-multiple-line" style="font-size: 48px; color: #10b981;"></i>
            <h5 class="mt-3"><?= __('Kolejka pusta - wszystko załatwione!') ?></h5>
            <p class="text-muted small"><?= __('Nowe emaile pojawią się tu automatycznie po polling z Gmail (co 5 min lub Poll NOW).') ?></p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($byLead as $lid => $group): $lead = $group['lead']; ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center" style="background: #fafbfc;">
                <div>
                    <a href="<?= $this->Url->build(['action' => 'view', $lead->id]) ?>" class="fw-bold text-decoration-none">
                        <?= h($lead->company_name) ?>
                    </a>
                    <span class="badge bg-light text-dark ms-2"><?= h($lead->stage) ?></span>
                    <?php if ($lead->city || $lead->country_code): ?>
                        <span class="small text-muted ms-2"><i class="ri-map-pin-line"></i> <?= h($lead->city) ?> <?= h($lead->country_code) ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <?php if ($lead->phone): ?>
                        <a href="tel:<?= h($lead->phone) ?>" class="btn btn-sm btn-outline-success"><i class="ri-phone-line"></i> <?= h($lead->phone) ?></a>
                    <?php endif; ?>
                    <?php if ($lead->email): ?>
                        <a href="mailto:<?= h($lead->email) ?>" class="btn btn-sm btn-outline-info"><i class="ri-mail-line"></i></a>
                    <?php endif; ?>
                    <a href="<?= $this->Url->build(['action' => 'view', $lead->id]) ?>" class="btn btn-sm btn-primary">
                        <i class="ri-eye-line"></i> <?= __('Otwórz') ?>
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php foreach ($group['activities'] as $act): $cls = $act->_classification; $s = $sentColors[$cls['sentiment']] ?? $sentColors['neutral']; $u = (int)$cls['urgency']; ?>
                    <div class="p-3" style="border-bottom: 1px solid #f1f3f5;">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <div class="fw-semibold" style="font-size: 14px;"><?= h($act->subject ?: '(bez tematu)') ?></div>
                            <div class="small text-muted"><?= h($act->created->format('d.m.Y H:i')) ?></div>
                        </div>
                        <div class="d-flex gap-1 flex-wrap mb-2">
                            <span class="badge" style="background: <?= $s['bg'] ?>; color: <?= $s['text'] ?>;">
                                <?= $s['emoji'] ?> <?= $s['label'] ?>
                            </span>
                            <span class="badge bg-secondary"><?= h($intentLabels[$cls['intent']] ?? $cls['intent']) ?></span>
                            <span class="badge" style="background: <?= $u >= 4 ? '#fee2e2' : '#fef3c7' ?>; color: <?= $u >= 4 ? '#dc2626' : '#b45309' ?>;">
                                <?= str_repeat('!', $u) ?> <?= (int)$u ?>/5
                            </span>
                            <?php if (!empty($cls['action_required'])): ?>
                                <span class="badge bg-danger"><i class="ri-alarm-warning-line"></i> AKCJA</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($cls['summary'])): ?>
                            <div class="small mb-1" style="background: #f8fafc; padding: 6px 10px; border-radius: 4px;">
                                <strong>💬</strong> <?= h($cls['summary']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($cls['suggested_action'])): ?>
                            <div class="small" style="background: #fff7ed; padding: 6px 10px; border-radius: 4px; border-left: 3px solid #ea580c;">
                                <strong>👉 <?= __('Akcja:') ?></strong> <?= h($cls['suggested_action']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

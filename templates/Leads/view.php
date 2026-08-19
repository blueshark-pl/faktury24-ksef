<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Lead $lead
 */
$this->assign('title', __('Lead: ') . $lead->company_name);

$stageLabels = [
    'new' => __('Nowy lead'), 'contact' => __('Kontakt'), 'inquiry' => __('Zapytanie'),
    'offer' => __('Oferta'), 'order' => __('Zlecenie'), 'lost' => __('Utracone'),
];
$stageBg = [
    'new' => 'bg-primary', 'contact' => 'bg-info', 'inquiry' => 'bg-warning text-dark',
    'offer' => 'bg-purple', 'order' => 'bg-success', 'lost' => 'bg-secondary',
];
$stages = ['new', 'contact', 'inquiry', 'offer', 'order'];
$currentIdx = array_search($lead->stage, $stages, true);
$activityIcons = [
    'phone_call' => ['ri-phone-line', 'green'],
    'email_out'  => ['ri-mail-send-line', 'purple'],
    'email_in'   => ['ri-mail-download-line', 'purple'],
    'meeting'    => ['ri-calendar-event-line', 'blue'],
    'note'       => ['ri-sticky-note-line', 'yellow'],
    'task'       => ['ri-checkbox-line', 'orange'],
    'file'       => ['ri-attachment-2', 'slate'],
    'stage_change' => ['ri-arrow-right-up-line', 'slate'],
    'assignment' => ['ri-user-shared-line', 'blue'],
    'offer_sent' => ['ri-file-paper-line', 'purple'],
    'order_won'  => ['ri-trophy-line', 'green'],
    'order_lost' => ['ri-close-circle-line', 'red'],
];
?>
<style>
.bg-purple { background-color: #7c3aed !important; color: #fff; }
.crm-stepper { display: flex; align-items: center; gap: 4px; }
.crm-step { flex: 1; text-align: center; position: relative; }
.crm-step-dot { width: 28px; height: 28px; border-radius: 50%; margin: 0 auto 4px; display: flex;
    align-items: center; justify-content: center; font-size: 12px; font-weight: 700;
    background: #f1f3f5; color: #adb5bd; border: 2px solid #f1f3f5; }
.crm-step.done .crm-step-dot { background: #94C81F; color: #fff; border-color: #94C81F; }
.crm-step.active .crm-step-dot { background: #fff; color: #94C81F; border-color: #94C81F;
    box-shadow: 0 0 0 4px rgba(148,200,31,0.15); }
.crm-step-lbl { font-size: 10px; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: 0.4px; }
.crm-step.done .crm-step-lbl, .crm-step.active .crm-step-lbl { color: #212529; }
.crm-step-line { position: absolute; top: 14px; left: calc(50% + 20px); right: calc(-50% + 20px);
    height: 2px; background: #dee2e6; z-index: 0; }
.crm-step.done .crm-step-line { background: #94C81F; }

.tl-item { display: flex; gap: 12px; padding-bottom: 16px; position: relative; }
.tl-item:last-child { padding-bottom: 0; }
.tl-item::before { content: ''; position: absolute; left: 15px; top: 32px; bottom: -4px; width: 2px; background: #e9ecef; }
.tl-item:last-child::before { display: none; }
.tl-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center;
    justify-content: center; flex-shrink: 0; z-index: 1; border: 3px solid #fff; font-size: 14px; }
.tl-icon.green  { background:#d1fae5; color:#059669; }
.tl-icon.purple { background:#ede9fe; color:#7c3aed; }
.tl-icon.blue   { background:#dbeafe; color:#2563eb; }
.tl-icon.yellow { background:#fef3c7; color:#b45309; }
.tl-icon.orange { background:#fed7aa; color:#ea580c; }
.tl-icon.red    { background:#fee2e2; color:#dc2626; }
.tl-icon.slate  { background:#e2e8f0; color:#475569; }
.info-row { display: grid; grid-template-columns: 130px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f1f3f5; font-size: 13px; }
.info-row:last-child { border: none; }
.info-label { color: #6c757d; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <nav aria-label="breadcrumb" class="small text-muted">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>">CRM</a> ·
        <a href="<?= $this->Url->build(['action' => 'kanban']) ?>">Pipeline</a> ·
        <span><?= h($lead->company_name) ?></span>
    </nav>
    <div class="d-flex gap-2">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-arrow-left-line"></i> <?= __('Wróć') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'edit', $lead->id]) ?>" class="btn btn-sm btn-outline-primary">
            <i class="ri-pencil-line"></i> <?= __('Edytuj') ?>
        </a>
        <?php if (!$lead->contractor_id): ?>
            <?= $this->Form->postLink(
                '<i class="ri-user-add-line"></i> ' . __('Utwórz kontrahenta'),
                ['action' => 'convertToContractor', $lead->id],
                ['escape' => false, 'class' => 'btn btn-sm btn-outline-success',
                 'confirm' => __('Utworzyć kontrahenta z tego leada?')]
            ) ?>
        <?php endif; ?>
    </div>
</div>

<!-- Hero card -->
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex gap-3 align-items-start">
            <div style="width: 60px; height: 60px; background: rgba(148,200,31,0.14); color: #6b8f14;
                        border-radius: 12px; display: flex; align-items: center; justify-content: center;
                        font-weight: 800; font-size: 20px;">
                <?= h(strtoupper(mb_substr($lead->company_name, 0, 2))) ?>
            </div>
            <div class="flex-grow-1">
                <h4 class="mb-1"><?= h($lead->company_name) ?>
                    <span class="badge <?= $stageBg[$lead->stage] ?? 'bg-secondary' ?> ms-2">
                        <?= h($stageLabels[$lead->stage] ?? $lead->stage) ?>
                    </span>
                </h4>
                <div class="text-muted small">
                    <?php if ($lead->postal_code || $lead->city): ?>
                        <i class="ri-map-pin-2-line"></i> <?= h(trim(($lead->postal_code ?? '') . ' ' . ($lead->city ?? ''))) ?>
                        <?php if ($lead->street): ?> · <?= h($lead->street) ?><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($lead->country_code): ?> · <?= h(strtoupper($lead->country_code)) ?><?php endif; ?>
                    <?php if ($lead->nip): ?> · <span class="text-muted">NIP <?= h($lead->nip) ?></span><?php endif; ?>
                </div>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <?php if ($lead->phone): ?>
                        <a href="tel:<?= h($lead->phone) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="ri-phone-line"></i> <?= h($lead->phone) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($lead->email): ?>
                        <a href="mailto:<?= h($lead->email) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="ri-mail-line"></i> <?= h($lead->email) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2 text-center">
                <div class="p-2 rounded bg-light">
                    <div class="fw-bold fs-4"><?= (int)$lead->probability ?>%</div>
                    <div class="small text-muted"><?= __('Skuteczność') ?></div>
                </div>
                <div class="p-2 rounded bg-light">
                    <div class="fw-bold fs-4"><?= $lead->value_pln ? number_format((float)$lead->value_pln, 0, ',', ' ') : '—' ?></div>
                    <div class="small text-muted"><?= __('Wartość (zł)') ?></div>
                </div>
                <div class="p-2 rounded bg-light">
                    <div class="fw-bold fs-4"><?= count($lead->lead_activities ?? []) ?></div>
                    <div class="small text-muted"><?= __('Aktywności') ?></div>
                </div>
            </div>
        </div>

        <!-- Stepper -->
        <hr class="my-3">
        <div class="crm-stepper">
            <?php foreach ($stages as $i => $s): ?>
                <div class="crm-step <?= $currentIdx !== false && $i < $currentIdx ? 'done' : ($s === $lead->stage ? 'active' : '') ?>">
                    <?php if ($i < count($stages) - 1): ?>
                        <div class="crm-step-line"></div>
                    <?php endif; ?>
                    <div class="crm-step-dot"><?= $currentIdx !== false && $i < $currentIdx ? '✓' : ($i + 1) ?></div>
                    <div class="crm-step-lbl"><?= h($stageLabels[$s]) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- LEFT: dane + next action -->
    <div class="col-lg-4">
        <?php if ($lead->next_action_at): ?>
        <div class="card mb-3" style="border: 1px solid #94C81F; background: linear-gradient(135deg, #f6ffed 0%, #fefce8 100%);">
            <div class="card-body">
                <div class="small fw-bold text-uppercase mb-1" style="color: #6b8f14; letter-spacing: 0.5px;">
                    <i class="ri-time-line"></i> <?= __('Następna akcja') ?>
                </div>
                <div class="fw-bold"><?= h($lead->next_action_description ?: '—') ?></div>
                <div class="small text-muted"><?= h($lead->next_action_at->format('d.m.Y H:i')) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <div class="fw-bold mb-2"><?= __('Dane firmy') ?></div>
                <div class="info-row"><div class="info-label"><?= __('Nazwa') ?></div><div><?= h($lead->company_name) ?></div></div>
                <?php if ($lead->nip): ?>
                    <div class="info-row"><div class="info-label">NIP</div><div><?= h($lead->nip) ?></div></div>
                <?php endif; ?>
                <?php if ($lead->country_code): ?>
                    <div class="info-row"><div class="info-label"><?= __('Kraj') ?></div><div><?= h(strtoupper($lead->country_code)) ?></div></div>
                <?php endif; ?>
                <?php if ($lead->postal_code || $lead->city): ?>
                    <div class="info-row"><div class="info-label"><?= __('Adres') ?></div><div>
                        <?= h(trim(($lead->postal_code ?? '') . ' ' . ($lead->city ?? ''))) ?>
                        <?php if ($lead->street): ?><br><?= h($lead->street) ?><?php endif; ?>
                    </div></div>
                <?php endif; ?>
                <?php if ($lead->branch_type): ?>
                    <div class="info-row"><div class="info-label"><?= __('Gałąź') ?></div><div><?= h($lead->branch_type) ?></div></div>
                <?php endif; ?>
                <?php if ($lead->source): ?>
                    <div class="info-row"><div class="info-label"><?= __('Źródło') ?></div><div><?= h($lead->source) ?></div></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($lead->contact_person || $lead->phone || $lead->email): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="fw-bold mb-2"><?= __('Osoba kontaktowa') ?></div>
                <?php if ($lead->contact_person): ?>
                    <div class="info-row"><div class="info-label"><?= __('Osoba') ?></div><div><?= h($lead->contact_person) ?></div></div>
                <?php endif; ?>
                <?php if ($lead->contact_role): ?>
                    <div class="info-row"><div class="info-label"><?= __('Stanowisko') ?></div><div><?= h($lead->contact_role) ?></div></div>
                <?php endif; ?>
                <?php if ($lead->phone): ?>
                    <div class="info-row"><div class="info-label"><?= __('Telefon') ?></div><div><a href="tel:<?= h($lead->phone) ?>"><?= h($lead->phone) ?></a></div></div>
                <?php endif; ?>
                <?php if ($lead->email): ?>
                    <div class="info-row"><div class="info-label"><?= __('Email') ?></div><div><a href="mailto:<?= h($lead->email) ?>"><?= h($lead->email) ?></a></div></div>
                <?php endif; ?>
                <?php if ($lead->contact_channel): ?>
                    <div class="info-row"><div class="info-label"><?= __('Preferencja') ?></div><div><?= h($lead->contact_channel) ?></div></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($lead->note): ?>
        <div class="card mb-3" style="background: #fffbeb; border: 1px solid #fef3c7;">
            <div class="card-body">
                <div class="small fw-bold text-uppercase mb-1" style="color: #b45309;">
                    <i class="ri-sticky-note-line"></i> <?= __('Notatka') ?>
                </div>
                <div style="color: #78350f;"><?= nl2br(h($lead->note)) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($lead->assigned_user): ?>
        <div class="card">
            <div class="card-body">
                <div class="small text-muted"><?= __('Opiekun') ?></div>
                <div class="fw-semibold"><?= h(trim(($lead->assigned_user->first_name ?? '') . ' ' . ($lead->assigned_user->last_name ?? ''))) ?></div>
                <div class="small text-muted"><?= h($lead->assigned_user->email ?? '') ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: timeline + add activity -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="fw-bold mb-3"><i class="ri-history-line"></i> <?= __('Timeline aktywności') ?></div>

                <!-- Compose form -->
                <?= $this->Form->create(null, ['url' => ['action' => 'activityAdd', $lead->id], 'class' => 'mb-4 p-3 bg-light rounded']) ?>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <select name="activity_type" class="form-select form-select-sm">
                                <option value="phone_call"><?= __('📞 Rozmowa') ?></option>
                                <option value="email_out"><?= __('📤 Email wysłany') ?></option>
                                <option value="email_in"><?= __('📥 Email otrzymany') ?></option>
                                <option value="meeting"><?= __('📅 Spotkanie') ?></option>
                                <option value="note" selected><?= __('📝 Notatka') ?></option>
                                <option value="task"><?= __('✅ Zadanie/przypomnienie') ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="subject" class="form-control form-control-sm" placeholder="<?= __('Temat / tytuł') ?>">
                        </div>
                        <div class="col-md-3">
                            <input type="datetime-local" name="happened_at" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="duration_min" class="form-control form-control-sm" placeholder="<?= __('min') ?>">
                        </div>
                        <div class="col-12">
                            <textarea name="body" class="form-control form-control-sm" rows="2" placeholder="<?= __('Treść / opis') ?>"></textarea>
                        </div>
                        <div class="col-md-4">
                            <input type="datetime-local" name="due_at" class="form-control form-control-sm" placeholder="<?= __('Termin (dla zadań)') ?>">
                            <div class="form-text small"><?= __('Wypełnij tylko dla zadań/przypomnień') ?></div>
                        </div>
                        <div class="col-md-8 text-end">
                            <button class="btn btn-sm btn-primary"><i class="ri-add-line"></i> <?= __('Dodaj aktywność') ?></button>
                        </div>
                    </div>
                <?= $this->Form->end() ?>

                <?php if (empty($lead->lead_activities)): ?>
                    <div class="alert alert-info small mb-0">
                        <?= __('Brak aktywności. Zapisz pierwszą rozmowę, email lub notatkę powyżej.') ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($lead->lead_activities as $a):
                        [$icon, $tone] = $activityIcons[$a->activity_type] ?? ['ri-more-line', 'slate'];
                        $author = trim(($a->user?->first_name ?? '') . ' ' . ($a->user?->last_name ?? ''));
                    ?>
                    <div class="tl-item">
                        <div class="tl-icon <?= $tone ?>"><i class="<?= $icon ?>"></i></div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-baseline">
                                <div class="small">
                                    <?php if ($author): ?><strong><?= h($author) ?></strong><?php else: ?><span class="text-muted"><?= __('System') ?></span><?php endif; ?>
                                    · <?= h($a->activity_type) ?>
                                    <?php if ($a->subject): ?> · <span class="fw-semibold"><?= h($a->subject) ?></span><?php endif; ?>
                                    <?php if ($a->duration_min): ?> · <span class="text-muted"><?= (int)$a->duration_min ?> min</span><?php endif; ?>
                                </div>
                                <div class="text-muted small">
                                    <?= h(($a->happened_at ?? $a->created)->format('d.m.Y H:i')) ?>
                                    <?= $this->Form->postLink(
                                        '<i class="ri-close-line"></i>',
                                        ['action' => 'activityDelete', $a->id],
                                        ['escape' => false, 'class' => 'btn btn-sm btn-link text-muted p-0 ms-1',
                                         'confirm' => __('Usunąć wpis?')]
                                    ) ?>
                                </div>
                            </div>
                            <?php if ($a->body): ?>
                                <div class="mt-1" style="font-size: 13px;"><?= nl2br(h($a->body)) ?></div>
                            <?php endif; ?>
                            <?php if ($a->due_at): ?>
                                <div class="mt-1 small text-warning">
                                    <i class="ri-alarm-line"></i> <?= __('Termin:') ?> <?= h($a->due_at->format('d.m.Y H:i')) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

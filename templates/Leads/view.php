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
    'quote_request' => ['ri-file-list-3-line', 'green'],
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
        <button type="button" class="btn btn-sm btn-outline-info" id="btn-ai-summarize" title="<?= __('GPT AI: podsumuj historie + rekomenduj') ?>">
            <i class="ri-magic-line"></i> AI Podsumuj
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#offerModal">
            <i class="ri-mail-send-line"></i> <?= __('Utwórz ofertę') ?>
        </button>
        <a href="<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'add', '?' => ['lead_id' => $lead->id]]) ?>"
           class="btn btn-sm btn-success">
            <i class="ri-truck-line"></i> <?= __('Utwórz zlecenie') ?>
        </a>
    </div>
</div>

<!-- Modal: Utwórz ofertę -->
<div class="modal fade" id="offerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= $this->Form->create(null, ['url' => ['action' => 'createOfferFromLead', $lead->id]]) ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-mail-send-line"></i> <?= __('Nowa oferta dla') ?>: <?= h($lead->company_name) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label small"><?= __('Odbiorca (email)') ?> *</label>
                            <input name="sent_to_email" type="email" required class="form-control" value="<?= h($lead->email) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small"><?= __('Imię i nazwisko') ?></label>
                            <input name="sent_to_name" class="form-control" value="<?= h($lead->contact_person) ?>">
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small"><?= __('Temat') ?></label>
                        <input name="subject" class="form-control"
                               value="<?= h(sprintf(__('Oferta transportowa dla %s'), $lead->company_name)) ?>">
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-5">
                            <label class="form-label small"><?= __('Cena netto') ?> *</label>
                            <div class="input-group">
                                <input name="price" type="number" step="0.01" required class="form-control"
                                       value="<?= h($lead->value_pln) ?>">
                                <span class="input-group-text"><?= h($lead->currency ?: 'PLN') ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small"><?= __('Waluta') ?></label>
                            <input name="currency" maxlength="3" class="form-control text-uppercase" value="<?= h($lead->currency ?: 'PLN') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">VAT %</label>
                            <input name="vat_rate" type="number" min="0" max="100" class="form-control" value="23">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small"><?= __('Płatność (dni)') ?></label>
                            <input name="payment_days" type="number" min="0" class="form-control" value="30">
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small"><?= __('Ważność oferty do') ?></label>
                        <input name="valid_until" type="date" class="form-control"
                               value="<?= (new \DateTimeImmutable('+14 days'))->format('Y-m-d') ?>">
                    </div>
                    <div class="mt-2">
                        <label class="form-label small"><?= __('Treść wiadomości (opcjonalnie)') ?></label>
                        <textarea name="message_body" class="form-control" rows="3"><?= h(sprintf(__("Dzień dobry,\n\nzgodnie z ustaleniami przesyłam ofertę.\n\nPozdrawiam")) ) ?></textarea>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="ri-information-line"></i>
                        <?= __('Utworzy się oferta w statusie „draft" bez konkretnej trasy. Możesz ją potem uzupełnić w /oferty przed wysyłką.') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-check-line"></i> <?= __('Utwórz ofertę i przejdź do wysyłki') ?>
                    </button>
                </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<?php
// FALA 16 diagnostyka: krotki hint w górze widoku żeby user widział czy sync działa
$__totalAct = count($lead->lead_activities ?? []);
$__quoteAct = 0; $__emailAct = 0;
foreach ($lead->lead_activities as $__a) {
    if ($__a->activity_type === 'quote_request') $__quoteAct++;
    if ($__a->activity_type === 'email_in') $__emailAct++;
}
?>
<div class="mb-2 small text-muted" style="font-size: 11px;">
    <i class="ri-database-2-line"></i>
    Aktywności: <strong><?= $__totalAct ?></strong>
    · email_in: <strong><?= $__emailAct ?></strong>
    · quote_request: <strong class="<?= $__quoteAct > 0 ? 'text-success' : 'text-muted' ?>"><?= $__quoteAct ?></strong>
    · Wiadomości email: <strong><?= count($emailMessages ?? []) ?></strong>
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

        <!-- KRS Enrichment Panel -->
        <?php if (strtoupper((string)$lead->country_code) === 'PL' || empty($lead->country_code)): ?>
        <div class="card mb-3" style="border-left: 4px solid #7c3aed;">
            <div class="card-body">
                <div class="fw-bold mb-2 d-flex justify-content-between align-items-center">
                    <span><i class="ri-building-4-line text-purple"></i> <?= __('KRS enrichment') ?></span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-krs-fetch">
                        <i class="ri-download-line"></i> <?= __('Pobierz z KRS') ?>
                    </button>
                </div>
                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text">KRS</span>
                    <input id="krs-input" class="form-control" placeholder="0000123456 (10 cyfr)" maxlength="10" autocomplete="off">
                </div>
                <div id="krs-hint" class="small text-muted">
                    <?= __('Nie znasz KRS? Znajdz na') ?> <a href="https://wyszukiwarka-krs.ms.gov.pl/" target="_blank">wyszukiwarka-krs.ms.gov.pl</a>
                </div>
                <div id="krs-panel" style="display:none;"></div>
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
                <div class="mt-2 d-flex gap-2 flex-wrap" id="li-buttons">
                    <?php if (!empty($lead->linkedin_url)): ?>
                        <a href="<?= h($lead->linkedin_url) ?>" target="_blank" rel="noopener"
                           class="btn btn-sm text-white" style="background:#0a66c2;">
                            <i class="ri-linkedin-box-fill"></i> <?= __('Profil osoby') ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($lead->linkedin_company_url)): ?>
                        <a href="<?= h($lead->linkedin_company_url) ?>" target="_blank" rel="noopener"
                           class="btn btn-sm btn-outline-primary" style="border-color:#0a66c2; color:#0a66c2;">
                            <i class="ri-building-line"></i> <?= __('Profil firmy') ?>
                        </a>
                    <?php endif; ?>
                    <?php if (empty($lead->linkedin_url) && !empty($lead->contact_person)): ?>
                        <button type="button" class="btn btn-sm btn-primary" id="li-search-person"
                                data-mode="person" style="background:#0a66c2; border-color:#0a66c2;">
                            <i class="ri-search-eye-line"></i> <?= __('Znajdź profil auto') ?>
                        </button>
                    <?php endif; ?>
                    <?php if (empty($lead->linkedin_company_url)): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="li-search-company"
                                data-mode="company" style="border-color:#0a66c2; color:#0a66c2;">
                            <i class="ri-search-eye-line"></i> <?= __('Znajdź firmę auto') ?>
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($lead->contact_person) || !empty($lead->company_name)): ?>
                        <a href="https://www.google.com/search?q=<?= urlencode(($lead->contact_person ?? '') . ' ' . ($lead->company_name ?? '') . ' linkedin') ?>"
                           target="_blank" rel="noopener" class="btn btn-sm btn-link text-muted small" title="<?= __('Google search fallback') ?>">
                            <i class="ri-external-link-line"></i> Google
                        </a>
                    <?php endif; ?>
                </div>
                <div id="li-results" class="mt-2" style="display:none;"></div>
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

    <!-- RIGHT: quote_requests aggregate + timeline + add activity -->
    <div class="col-lg-8">
        <?php
        // FALA 15+16: Agregat wykrytych zlecen (quote_request) - DUZY panel u gory
        $qrList = $quoteRequests ?? [];
        $shipAll = $allShipments ?? [];
        $ordersDone = (int)($totalOrdersCreated ?? 0);

        // Debug fallback: sprawdz czy w timeline SA quote_request activities
        // (ale kontroler ich nie zagregowal - to znaczy problem parse payload_json)
        $rawQuoteCount = 0;
        $rawSamplePayload = null;
        foreach ($lead->lead_activities as $__a) {
            if ($__a->activity_type === 'quote_request') {
                $rawQuoteCount++;
                if ($rawSamplePayload === null) $rawSamplePayload = $__a->payload_json;
            }
        }
        if (empty($qrList) && $rawQuoteCount > 0):
        ?>
        <div class="alert alert-warning small mb-3">
            <strong>⚠️ Panel „Zapytania o wycenę" nie zrenderowany, ale w timeline JEST <?= $rawQuoteCount ?> quote_request activities.</strong><br>
            Prawdopodobnie <code>payload_json</code> nie zawiera pola <code>shipments</code> lub jest niepoprawny JSON.<br>
            Pierwsze 300 znakow payload_json (dla debug):
            <pre style="background:#fff; padding:6px; margin-top:6px; font-size:11px; max-height:200px; overflow:auto;"><?= h(mb_substr((string)$rawSamplePayload, 0, 300)) ?></pre>
        </div>
        <?php elseif (empty($qrList) && $rawQuoteCount === 0): ?>
        <div class="alert alert-secondary small mb-3">
            <i class="ri-information-line"></i>
            <?= __('Ten lead nie ma jeszcze wykrytych zapytań o wycenę z emaili.') ?>
            <?= __('Wysłać maila z zapytaniem transportowym na skrzynkę Gmail lub sprawdzić czy Poll emails działa.') ?>
            <a href="<?= $this->Url->build(['controller' => 'CrmAdmin', 'action' => 'analyzeLastEmail', '?' => ['lead_id' => $lead->id]]) ?>" target="_blank" class="ms-2">
                Diagnostyka FALA 15 →
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($qrList)):
            $shipCount = count($shipAll);
            $shipRemaining = max(0, $shipCount - $ordersDone);
        ?>
        <div class="card mb-3" style="border-left: 5px solid #94C81F;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="fw-bold" style="font-size: 16px;">
                            <i class="ri-file-list-3-line text-success"></i>
                            <?= __('Zapytania o wycenę wykryte przez AI') ?>
                        </div>
                        <div class="small text-muted mt-1">
                            <?= __('GPT rozpoznał zapytania w emailach (body + załączniki via Vision).') ?>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('qr-details').style.display = document.getElementById('qr-details').style.display === 'none' ? 'block' : 'none';">
                        <i class="ri-arrow-up-down-line"></i> <?= __('Zwiń/rozwiń tabelę') ?>
                    </button>
                </div>

                <!-- KPI kafelki -->
                <div class="row g-2 mb-3">
                    <div class="col">
                        <div class="p-2 rounded text-center" style="background: #e8f5e9;">
                            <div class="fw-bold text-success" style="font-size: 22px;"><?= (int)$shipCount ?></div>
                            <div class="small text-muted"><?= __('Wszystkich zleceń') ?></div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2 rounded text-center" style="background: #e3f2fd;">
                            <div class="fw-bold text-primary" style="font-size: 22px;"><?= (int)$ordersDone ?></div>
                            <div class="small text-muted"><?= __('Utworzone w bazie') ?></div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2 rounded text-center" style="background: #fff3e0;">
                            <div class="fw-bold text-warning" style="font-size: 22px;"><?= (int)$shipRemaining ?></div>
                            <div class="small text-muted"><?= __('Jeszcze do utworzenia') ?></div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-2 rounded text-center" style="background: #f3e5f5;">
                            <div class="fw-bold" style="font-size: 22px; color: #7c3aed;"><?= count($qrList) ?></div>
                            <div class="small text-muted"><?= __('Zapytań (emaili)') ?></div>
                        </div>
                    </div>
                </div>

                <div id="qr-details" style="display: block;">
                <?php foreach ($qrList as $qi => $qr): ?>
                    <div class="mb-3 p-2" style="background: #fafbfc; border: 1px solid #e9ecef; border-radius: 6px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="small">
                                <strong><i class="ri-mail-download-line"></i> <?= h($qr['from_email'] ?: __('nieznany nadawca')) ?></strong>
                                <?php if (!empty($qr['customer_name'])): ?>
                                    · <?= h($qr['customer_name']) ?>
                                <?php endif; ?>
                                <span class="badge bg-success ms-2"><?= $qr['shipments_count'] ?> <?= __('zleceń') ?></span>
                                <?php if ($qr['orders_created_count'] > 0): ?>
                                    <span class="badge bg-primary"><?= (int)$qr['orders_created_count'] ?> <?= __('utworzonych') ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-1">
                                <?php if ($qr['orders_created_count'] < $qr['shipments_count']): ?>
                                    <?= $this->Form->postLink(
                                        '<i class="ri-add-circle-line"></i> ' . __('Utwórz zlecenia w bazie'),
                                        ['action' => 'createOrdersFromQuote', $qr['activity_id']],
                                        ['escape' => false, 'class' => 'btn btn-sm btn-success',
                                         'confirm' => __('Utworzyć {0} zleceń manualnych z tego emaila?', $qr['shipments_count'])]
                                    ) ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="ri-check-double-line"></i> <?= __('wszystkie utworzone') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0" style="font-size: 11px;">
                                <thead style="position: sticky; top: 0; background: #fff; z-index: 2;">
                                    <tr>
                                        <th style="width:30px;">#</th>
                                        <th><?= __('Ref') ?></th>
                                        <th><?= __('Załadunek') ?></th>
                                        <th><?= __('Rozładunek') ?></th>
                                        <th><?= __('Data') ?></th>
                                        <th><?= __('Kg') ?></th>
                                        <th><?= __('Palet') ?></th>
                                        <th><?= __('Sprzęt') ?></th>
                                        <th><?= __('Uwagi') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($qr['shipments'] as $i => $s): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td class="fw-semibold text-primary" style="max-width: 90px; word-break: break-all;"><?= h($s['customer_order_ref'] ?? '') ?></td>
                                        <td>
                                            <?= h(trim(($s['from_postal'] ?? '') . ' ' . ($s['from_city'] ?? '') . ' ' . ($s['from_country'] ?? ''))) ?>
                                            <?php if (!empty($s['from_company'])): ?><br><span class="text-muted small"><?= h($s['from_company']) ?></span><?php endif; ?>
                                        </td>
                                        <td>
                                            <?= h(trim(($s['to_postal'] ?? '') . ' ' . ($s['to_city'] ?? '') . ' ' . ($s['to_country'] ?? ''))) ?>
                                            <?php if (!empty($s['to_company'])): ?><br><span class="text-muted small"><?= h($s['to_company']) ?></span><?php endif; ?>
                                        </td>
                                        <td>
                                            <?= h($s['load_date'] ?? '') ?><?= !empty($s['load_time']) ? ' ' . h($s['load_time']) : '' ?>
                                            <?php if (!empty($s['unload_date'])): ?><br><span class="text-muted">→ <?= h($s['unload_date']) ?></span><?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= !empty($s['weight_kg']) ? h(number_format((int)$s['weight_kg'], 0, ',', ' ')) : '-' ?></td>
                                        <td class="text-end"><?= !empty($s['pallets']) ? (int)$s['pallets'] . ' ' . h($s['pallet_type'] ?? '') : '-' ?></td>
                                        <td><?php if (!empty($s['vehicle_type'])): ?><span class="badge bg-secondary"><?= h($s['vehicle_type']) ?></span><?php endif; ?></td>
                                        <td style="max-width: 200px; word-wrap: break-word;">
                                            <?php if (!empty($s['cargo_type'])): ?><span class="badge bg-info"><?= h($s['cargo_type']) ?></span> <?php endif; ?>
                                            <?= h($s['notes'] ?? '') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
                            <button type="button" class="btn btn-sm btn-outline-info me-1" id="btn-ai-draft" title="<?= __('GPT AI: napisz odpowiedź na ostatni email klienta') ?>">
                                <i class="ri-magic-line"></i> AI Draft odpowiedzi
                            </button>
                            <button class="btn btn-sm btn-primary"><i class="ri-add-line"></i> <?= __('Dodaj aktywność') ?></button>
                        </div>
                    </div>
                <?= $this->Form->end() ?>

                <!-- Modal: AI Draft response -->
                <div class="modal fade" id="aiDraftModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="ri-magic-line text-info"></i> AI Draft odpowiedzi email</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div id="ai-draft-loading" style="display:none;" class="text-center py-4">
                                    <i class="ri-loader-4-line fs-2 text-info"></i>
                                    <div class="mt-2">GPT-4o generuje odpowiedź na podstawie historii korespondencji…</div>
                                </div>
                                <div id="ai-draft-content" style="display:none;">
                                    <div class="mb-2">
                                        <label class="form-label small">Ton:</label>
                                        <select id="ai-tone" class="form-select form-select-sm" style="max-width:250px; display:inline-block;">
                                            <option value="professional">Profesjonalny (default)</option>
                                            <option value="friendly">Przyjazny</option>
                                            <option value="urgent">Zdecydowany/pilny</option>
                                            <option value="formal">Bardzo formalny</option>
                                        </select>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-1" id="ai-regen">
                                            <i class="ri-refresh-line"></i> Regeneruj
                                        </button>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Dodatkowy kontekst (opcjonalnie):</label>
                                        <input type="text" id="ai-extra" class="form-control form-control-sm" placeholder="np. 'zaproponuj cenę 5000 zł' albo 'zapytaj o termin'">
                                    </div>
                                    <hr>
                                    <div class="mb-2">
                                        <label class="form-label small">Temat:</label>
                                        <input type="text" id="ai-subject" class="form-control">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Treść (edytuj przed skopiowaniem):</label>
                                        <textarea id="ai-body" class="form-control" rows="12" style="font-family:monospace; font-size:13px;"></textarea>
                                    </div>
                                    <div class="small text-muted" id="ai-meta"></div>
                                </div>
                                <div id="ai-draft-error" class="alert alert-warning" style="display:none;"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zamknij</button>
                                <button type="button" class="btn btn-success" id="ai-copy">
                                    <i class="ri-clipboard-line"></i> Kopiuj do schowka
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal: AI Summary -->
                <div class="modal fade" id="aiSummaryModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="ri-magic-line text-info"></i> AI Podsumowanie leada</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" id="ai-summary-body">
                                <div class="text-center py-4"><i class="ri-loader-4-line fs-2 text-info"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

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
                            <?php
                            // FALA 16: dla email_in - pelny body toggle + lista zalacznikow z crm_email_messages
                            if ($a->activity_type === 'email_in' && !empty($a->payload_json)):
                                $ep = json_decode($a->payload_json, true);
                                // Znajdz odpowiadajaca wiadomosc w emailMessages (po gmail_id / from)
                                $matchedMsg = null;
                                if (!empty($emailMessages)) {
                                    foreach ($emailMessages as $key => $mm) {
                                        // dopasowanie po dokladnym subject + from lub message_id z payload
                                        if ((string)$mm->subject === (string)$a->subject
                                            && (string)strtolower((string)$mm->from_email) === (string)strtolower((string)($ep['from'] ?? ''))) {
                                            $matchedMsg = $mm;
                                            break;
                                        }
                                    }
                                }
                                $attList = [];
                                $fullBody = null;
                                if ($matchedMsg) {
                                    $attList = json_decode((string)$matchedMsg->attachments_json, true) ?: [];
                                    $fullBody = (string)($matchedMsg->body_text ?: $matchedMsg->body_html);
                                }
                            ?>
                                <?php
                                // FALA 17: Gmail-style email viewer
                                $fromEmail = (string)($ep['from'] ?? '');
                                $fromName = $matchedMsg?->from_name ?: strstr($fromEmail, '@', true) ?: '?';
                                $avatarInitial = strtoupper(mb_substr($fromName, 0, 1));
                                // FALA 18: klasyfikacja AI z payload_json (moze byc null jesli jeszcze nie sklasyfikowano)
                                $cls = $ep['classification'] ?? null;
                                $avatarColors = ['#7c3aed', '#059669', '#dc2626', '#ea580c', '#2563eb', '#b45309'];
                                $avatarBg = $avatarColors[crc32($fromEmail) % count($avatarColors)];
                                $bodyHtml = $matchedMsg?->body_html ? (string)$matchedMsg->body_html : '';
                                $bodyText = $matchedMsg?->body_text ? (string)$matchedMsg->body_text : '';
                                $emailId = 'mail-' . h($a->id);
                                // Wykryj zacytowana czesc (forwarded) - odejmiemy z body zeby pokazac tylko rzeczywista wiadomosc
                                $quoteMarkers = ['---------- Forwarded message', '--- Treść przekazanej', 'Weitergeleitete Nachricht',
                                    'From:', 'Von:', 'Nadawca:', 'On ', 'W dniu ', 'Am ', 'Le '];
                                ?>
                                <div class="mt-2" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                                    <!-- Gmail-style header -->
                                    <div class="d-flex gap-2 p-3" style="border-bottom: 1px solid #f1f3f5;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: <?= $avatarBg ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;">
                                            <?= h($avatarInitial) ?>
                                        </div>
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="d-flex justify-content-between align-items-baseline">
                                                <div>
                                                    <span class="fw-semibold" style="font-size: 14px;"><?= h($fromName) ?></span>
                                                    <span class="text-muted small">&lt;<?= h($fromEmail) ?>&gt;</span>
                                                </div>
                                                <div class="text-muted small" style="white-space: nowrap;">
                                                    <?php if (!empty($matchedMsg?->received_at)): ?>
                                                        <i class="ri-time-line"></i> <?= h($matchedMsg->received_at->format('d.m.Y H:i')) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <span class="text-muted"><?= __('do:') ?></span> <?= h($matchedMsg?->to_emails ?: '?') ?>
                                                <?php if ($matchedMsg?->cc_emails): ?>
                                                    · <span class="text-muted">cc:</span> <?= h($matchedMsg->cc_emails) ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($cls): ?>
                                            <div class="mt-2 d-flex gap-1 flex-wrap align-items-center">
                                                <?php
                                                $sentColors = [
                                                    'positive' => ['bg' => '#d1fae5', 'text' => '#059669', 'label' => 'Pozytywny', 'emoji' => '😊'],
                                                    'neutral'  => ['bg' => '#e2e8f0', 'text' => '#475569', 'label' => 'Neutralny', 'emoji' => '😐'],
                                                    'negative' => ['bg' => '#fee2e2', 'text' => '#dc2626', 'label' => 'Negatywny', 'emoji' => '😠'],
                                                    'urgent'   => ['bg' => '#fed7aa', 'text' => '#ea580c', 'label' => 'PILNE',     'emoji' => '🚨'],
                                                ];
                                                $intentLabels = [
                                                    'quote_request' => 'Zapytanie o wycenę',
                                                    'complaint' => 'Reklamacja',
                                                    'follow_up' => 'Follow-up',
                                                    'thank_you' => 'Podziękowanie',
                                                    'inquiry' => 'Zapytanie',
                                                    'payment' => 'Płatność',
                                                    'spam' => 'Spam',
                                                    'other' => 'Inne',
                                                ];
                                                $s = $sentColors[$cls['sentiment']] ?? $sentColors['neutral'];
                                                $u = (int)($cls['urgency'] ?? 2);
                                                ?>
                                                <span class="badge" style="background: <?= $s['bg'] ?>; color: <?= $s['text'] ?>; font-weight: 600;">
                                                    <?= $s['emoji'] ?> <?= $s['label'] ?>
                                                </span>
                                                <span class="badge bg-secondary"><?= h($intentLabels[$cls['intent']] ?? $cls['intent']) ?></span>
                                                <span class="badge" style="background: <?= $u >= 4 ? '#fee2e2' : ($u >= 3 ? '#fef3c7' : '#e2e8f0') ?>; color: <?= $u >= 4 ? '#dc2626' : ($u >= 3 ? '#b45309' : '#475569') ?>;" title="Pilnosc 1-5">
                                                    <?= str_repeat('!', $u) ?> Urgency <?= $u ?>/5
                                                </span>
                                                <?php if (!empty($cls['action_required'])): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="ri-alarm-warning-line"></i> AKCJA WYMAGANA
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($cls['summary'])): ?>
                                                <div class="mt-2 small" style="background: #f8fafc; padding: 6px 10px; border-radius: 4px; border-left: 3px solid #94C81F;">
                                                    <strong>💬 <?= __('AI podsumowanie:') ?></strong> <?= h($cls['summary']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($cls['suggested_action'])): ?>
                                                <div class="mt-1 small" style="background: #fff7ed; padding: 6px 10px; border-radius: 4px; border-left: 3px solid #ea580c;">
                                                    <strong>👉 <?= __('Sugerowana akcja:') ?></strong> <?= h($cls['suggested_action']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Body: prawdziwy HTML w iframe sandbox (izolacja XSS/JS/cookies) -->
                                    <div class="p-3">
                                        <?php if ($bodyHtml !== ''):
                                            // Sanitize inline via iframe sandbox (bez allow-scripts, bez allow-same-origin)
                                            // srcdoc z ESC HTML (& -> &amp; " -> &quot;) zeby atrybut nie zerwal
                                            $safeSrc = htmlspecialchars($bodyHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        ?>
                                        <iframe id="<?= $emailId ?>-frame" sandbox="" srcdoc="<?= $safeSrc ?>"
                                                style="width: 100%; border: none; min-height: 100px; background: #fff;"
                                                onload="try{this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';}catch(e){this.style.height='400px';}"></iframe>
                                        <?php elseif ($bodyText !== ''): ?>
                                            <?php
                                            // Text-only: podziel na main + quote (forwarded content)
                                            $mainBody = $bodyText;
                                            $quotePart = '';
                                            foreach ($quoteMarkers as $marker) {
                                                $pos = strpos($bodyText, $marker);
                                                if ($pos !== false && $pos > 20) {
                                                    $mainBody = trim(substr($bodyText, 0, $pos));
                                                    $quotePart = trim(substr($bodyText, $pos));
                                                    break;
                                                }
                                            }
                                            ?>
                                            <div style="font-size: 13px; white-space: pre-wrap; word-wrap: break-word;"><?= nl2br(h($mainBody)) ?></div>
                                            <?php if ($quotePart !== ''): ?>
                                                <div class="mt-2">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size: 11px; padding: 2px 8px;"
                                                            onclick="var el=document.getElementById('<?= $emailId ?>-quote'); if(el){el.style.display=(el.style.display==='none'?'block':'none');}">
                                                        <i class="ri-more-line"></i> <?= __('Pokaż/ukryj cytowaną wiadomość') ?>
                                                    </button>
                                                    <div id="<?= $emailId ?>-quote" style="display:none; margin-top: 8px; padding: 10px; border-left: 3px solid #cbd5e1; background: #f8fafc; color: #64748b; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;"><?= nl2br(h($quotePart)) ?></div>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="text-muted small fst-italic">(<?= __('Brak treści') ?>)</div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($attList)): ?>
                                    <!-- Attachments strip - Gmail style -->
                                    <div class="p-3" style="border-top: 1px solid #f1f3f5; background: #fafbfc;">
                                        <div class="small text-muted mb-2">
                                            <i class="ri-attachment-2"></i> <strong><?= count($attList) ?> <?= __('załączników') ?></strong>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($attList as $att): $mm = $att['mime'] ?? ''; ?>
                                                <div class="d-flex align-items-center gap-2 p-2" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; min-width: 200px;" title="<?= h($mm) ?>">
                                                    <div style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 4px; font-size: 20px;
                                                        <?php if (str_starts_with($mm, 'image/')): ?>background: #dbeafe; color: #2563eb;
                                                        <?php elseif ($mm === 'application/pdf'): ?>background: #fee2e2; color: #dc2626;
                                                        <?php elseif (str_contains($mm, 'spreadsheet') || str_contains($mm, 'excel')): ?>background: #d1fae5; color: #059669;
                                                        <?php elseif (str_contains($mm, 'word')): ?>background: #dbeafe; color: #2563eb;
                                                        <?php else: ?>background: #e2e8f0; color: #64748b;<?php endif; ?>">
                                                        <?php if (str_starts_with($mm, 'image/')): ?><i class="ri-image-line"></i>
                                                        <?php elseif ($mm === 'application/pdf'): ?><i class="ri-file-pdf-line"></i>
                                                        <?php elseif (str_contains($mm, 'spreadsheet') || str_contains($mm, 'excel')): ?><i class="ri-file-excel-line"></i>
                                                        <?php elseif (str_contains($mm, 'word')): ?><i class="ri-file-word-line"></i>
                                                        <?php else: ?><i class="ri-file-line"></i><?php endif; ?>
                                                    </div>
                                                    <div style="min-width: 0; flex: 1;">
                                                        <div class="text-truncate fw-semibold" style="font-size: 12px; max-width: 180px;"><?= h($att['filename'] ?? '?') ?></div>
                                                        <div class="text-muted" style="font-size: 10px;">
                                                            <?= isset($att['size']) ? round($att['size'] / 1024, 1) : '?' ?>KB · <?= h($mm) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Action bar -->
                                    <div class="p-2 d-flex gap-2" style="border-top: 1px solid #f1f3f5; background: #f8fafc;">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-ai-draft" data-email-body="<?= h(mb_substr($bodyText, 0, 2000)) ?>" data-email-subject="<?= h($a->subject) ?>">
                                            <i class="ri-reply-line"></i> <?= __('Odpowiedz z AI') ?>
                                        </button>
                                        <?php if ($bodyHtml !== '' && $bodyText !== ''): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                onclick="var el=document.getElementById('<?= $emailId ?>-plain'); if(el){el.style.display=(el.style.display==='none'?'block':'none');}">
                                            <i class="ri-code-line"></i> <?= __('Zobacz plain text') ?>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($bodyHtml !== '' && $bodyText !== ''): ?>
                                        <div id="<?= $emailId ?>-plain" style="display:none; padding: 12px; border-top: 1px solid #f1f3f5; background: #f8fafc; font-family: monospace; font-size: 11px; white-space: pre-wrap; max-height: 300px; overflow: auto;"><?= h($bodyText) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php
                            // FALA 15: widget dla activity_type='quote_request' - lista shipments z payload_json
                            if ($a->activity_type === 'quote_request' && !empty($a->payload_json)):
                                $payload = json_decode($a->payload_json, true);
                                $shipments = $payload['shipments'] ?? [];
                                if (!empty($shipments) && is_array($shipments)):
                            ?>
                                <div class="mt-2 p-2" style="background: #f8f9fa; border-radius: 6px; border-left: 3px solid #94C81F;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="small fw-semibold text-success">
                                            <i class="ri-truck-line"></i> <?= __('Wykryte zlecenia') ?> (<?= count($shipments) ?>)
                                            <?php if (!empty($payload['customer_name'])): ?>
                                                · <?= h($payload['customer_name']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-success btn-toggle-shipments" data-target="ship-<?= h($a->id) ?>">
                                            <i class="ri-eye-line"></i> <?= __('Pokaż/ukryj') ?>
                                        </button>
                                    </div>
                                    <div id="ship-<?= h($a->id) ?>" style="display:none; max-height: 400px; overflow-y: auto;">
                                        <table class="table table-sm table-hover mb-2" style="font-size: 11px;">
                                            <thead style="position: sticky; top: 0; background: #fff;">
                                                <tr>
                                                    <th>#</th>
                                                    <th><?= __('Ref') ?></th>
                                                    <th><?= __('Z') ?></th>
                                                    <th><?= __('Do') ?></th>
                                                    <th><?= __('Data') ?></th>
                                                    <th><?= __('Kg') ?></th>
                                                    <th><?= __('Palet') ?></th>
                                                    <th><?= __('Uwagi') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($shipments as $i => $s): ?>
                                                <tr>
                                                    <td><?= $i + 1 ?></td>
                                                    <td class="fw-semibold text-primary" style="max-width:80px; word-break:break-all;"><?= h($s['customer_order_ref'] ?? '') ?></td>
                                                    <td><?= h(trim(($s['from_postal'] ?? '') . ' ' . ($s['from_city'] ?? '') . ' ' . ($s['from_country'] ?? ''))) ?><?php if (!empty($s['from_company'])): ?><br><span class="text-muted"><?= h($s['from_company']) ?></span><?php endif; ?></td>
                                                    <td><?= h(trim(($s['to_postal'] ?? '') . ' ' . ($s['to_city'] ?? '') . ' ' . ($s['to_country'] ?? ''))) ?><?php if (!empty($s['to_company'])): ?><br><span class="text-muted"><?= h($s['to_company']) ?></span><?php endif; ?></td>
                                                    <td><?= h(($s['load_date'] ?? '') . ($s['load_time'] ? ' ' . $s['load_time'] : '')) ?><?php if (!empty($s['unload_date'])): ?><br><span class="text-muted">→ <?= h($s['unload_date']) ?></span><?php endif; ?></td>
                                                    <td><?= !empty($s['weight_kg']) ? h(number_format($s['weight_kg'], 0, ',', ' ')) : '-' ?></td>
                                                    <td><?= !empty($s['pallets']) ? (int)$s['pallets'] . ' ' . h($s['pallet_type'] ?? '') : '-' ?></td>
                                                    <td style="max-width:200px; word-wrap:break-word;">
                                                        <?php if (!empty($s['vehicle_type'])): ?><span class="badge bg-secondary"><?= h($s['vehicle_type']) ?></span> <?php endif; ?>
                                                        <?php if (!empty($s['cargo_type'])): ?><span class="badge bg-info"><?= h($s['cargo_type']) ?></span> <?php endif; ?>
                                                        <?= h($s['notes'] ?? '') ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <div class="d-flex gap-2 flex-wrap mt-2">
                                            <?= $this->Form->postLink(
                                                '<i class="ri-add-circle-line"></i> ' . __('Utwórz wszystkie zlecenia w bazie'),
                                                ['action' => 'createOrdersFromQuote', $a->id],
                                                ['escape' => false, 'class' => 'btn btn-sm btn-success',
                                                 'confirm' => __('Utworzyć zlecenia typu manual dla każdego wpisu? Kontrahent zostanie automatycznie użyty z tego leada.')]
                                            ) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; endif; ?>
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

<script>
(function() {
    var $btn = document.getElementById('btn-krs-fetch');
    var $input = document.getElementById('krs-input');
    var $panel = document.getElementById('krs-panel');
    var $hint = document.getElementById('krs-hint');
    if (!$btn || !$input || !$panel) return;
    var csrf = '<?= $this->request->getAttribute('csrfToken') ?>';
    var leadId = '<?= h($lead->id) ?>';
    var leadNip = '<?= h($lead->nip ?? '') ?>';
    var URL_KRS = '<?= $this->Url->build(['action' => 'krsLookupJson']) ?>';

    function tryCacheByNip() {
        if (!leadNip) return;
        var fd = new FormData();
        fd.append('_csrfToken', csrf);
        fd.append('nip', leadNip);
        fd.append('lead_id', leadId);
        fetch(URL_KRS, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
        }).then(function(r){return r.json();}).then(function(j) {
            if (j.ok && j.data) {
                $input.value = j.data.krs;
                render(j.data);
            } else if (j.hint) {
                $hint.textContent = j.hint;
            }
        }).catch(function(){});
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"]/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
    }

    function render(d) {
        if (!d) { $panel.style.display = 'none'; return; }
        var kapital = d.kapital_zakladowy > 0
            ? new Intl.NumberFormat('pl-PL').format(d.kapital_zakladowy) + ' ' + (d.waluta_kapitalu || 'PLN')
            : '<em class="text-muted">brak</em>';
        var status = d.status_dzialajaca
            ? '<span class="badge bg-success">Aktywna</span>'
            : '<span class="badge bg-danger">Nieaktywna/Upadłość</span>';
        var wspolnicyHtml = '';
        if (d.wspolnicy && d.wspolnicy.length) {
            wspolnicyHtml = '<div class="mt-2"><strong>Wspólnicy:</strong><ul class="small mb-0">';
            d.wspolnicy.slice(0, 5).forEach(function(w) {
                var name = ((w.imie || '') + ' ' + (w.nazwisko || '')).trim() || w.firma || '?';
                wspolnicyHtml += '<li>' + escapeHtml(name);
                if (w.udzialy_liczba) wspolnicyHtml += ' (' + w.udzialy_liczba + ' udziałów)';
                wspolnicyHtml += '</li>';
            });
            wspolnicyHtml += '</ul></div>';
        }
        var zarzadHtml = '';
        if (d.reprezentacja && d.reprezentacja.czlonkowie && d.reprezentacja.czlonkowie.length) {
            zarzadHtml = '<div class="mt-2"><strong>Zarząd:</strong><ul class="small mb-0">';
            d.reprezentacja.czlonkowie.slice(0, 5).forEach(function(z) {
                zarzadHtml += '<li>' + escapeHtml(((z.imie || '') + ' ' + (z.nazwisko || '')).trim()) +
                    (z.funkcja ? ' <span class="text-muted">— ' + escapeHtml(z.funkcja) + '</span>' : '') + '</li>';
            });
            zarzadHtml += '</ul></div>';
        }
        var pkdHtml = '';
        if (d.pkd_glowne_kod) {
            pkdHtml = '<div class="mt-2 small"><strong>PKD gł:</strong> ' +
                escapeHtml(d.pkd_glowne_kod) + ' ' + escapeHtml(d.pkd_glowne_opis || '') + '</div>';
        }
        var applyBtn = '<button type="button" class="btn btn-sm btn-success mt-2" id="btn-krs-apply"><i class="ri-check-line"></i> Auto-fill do leada</button>';

        $panel.innerHTML =
            '<div class="alert alert-info small mt-2 mb-0">' +
                '<div class="d-flex justify-content-between align-items-start">' +
                    '<div><strong>' + escapeHtml(d.nazwa) + '</strong> ' + status + '<br>' +
                        '<span class="text-muted">' + escapeHtml(d.forma_prawna) + '</span></div>' +
                    '<div class="text-end small text-muted">KRS ' + escapeHtml(d.krs) + '<br>NIP ' + escapeHtml(d.nip) + '</div>' +
                '</div>' +
                '<div class="mt-2 small">' +
                    '<i class="ri-map-pin-2-line"></i> ' +
                    escapeHtml(d.kod_pocztowy + ' ' + d.miejscowosc) + ' · ' +
                    escapeHtml(d.ulica + ' ' + d.nr_domu + (d.nr_lokalu ? '/' + d.nr_lokalu : '')) +
                '</div>' +
                '<div class="mt-1 small"><strong>Kapitał:</strong> ' + kapital +
                    (d.data_wpisu ? ' · <strong>Wpis:</strong> ' + escapeHtml(d.data_wpisu) : '') + '</div>' +
                pkdHtml + zarzadHtml + wspolnicyHtml + applyBtn +
            '</div>';
        $panel.style.display = 'block';

        var $apply = document.getElementById('btn-krs-apply');
        if ($apply) {
            $apply.addEventListener('click', function() {
                doFetch($input.value, true);
            });
        }
    }

    function doFetch(krs, apply) {
        krs = (krs || '').replace(/[^0-9]/g, '');
        if (krs.length !== 10) { alert('KRS musi mieć 10 cyfr'); return; }
        $btn.disabled = true;
        $btn.innerHTML = '<i class="ri-loader-4-line"></i> Pobieram…';
        var fd = new FormData();
        fd.append('_csrfToken', csrf);
        fd.append('krs', krs);
        fd.append('lead_id', leadId);
        if (apply) fd.append('apply', '1');
        fetch(URL_KRS, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
        }).then(function(r){return r.json();}).then(function(j) {
            $btn.disabled = false;
            $btn.innerHTML = '<i class="ri-download-line"></i> Pobierz z KRS';
            if (j.ok && j.data) {
                render(j.data);
                if (apply && j.applied && j.applied.length) {
                    setTimeout(function() {
                        alert('Zaktualizowano pola: ' + j.applied.join(', ') + '. Odświeżam…');
                        location.reload();
                    }, 200);
                }
            } else {
                $panel.innerHTML = '<div class="alert alert-warning small mt-2 mb-0"><i class="ri-error-warning-line"></i> ' +
                    escapeHtml(j.hint || j.error || 'Nie znaleziono danych.') + '</div>';
                $panel.style.display = 'block';
            }
        }).catch(function(e) {
            $btn.disabled = false;
            $btn.innerHTML = '<i class="ri-download-line"></i> Pobierz z KRS';
            alert('Błąd sieciowy: ' + e.message);
        });
    }

    $btn.addEventListener('click', function() { doFetch($input.value, false); });
    $input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); doFetch($input.value, false); }
    });

    tryCacheByNip();
})();
</script>

<script>
(function() {
    var csrf = '<?= $this->request->getAttribute('csrfToken') ?>';
    var leadId = '<?= h($lead->id) ?>';
    var URL_LI = '<?= $this->Url->build(['action' => 'linkedinSearchJson']) ?>';
    var $results = document.getElementById('li-results');

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"]/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
    }

    function search(mode, $btn) {
        var origHtml = $btn.innerHTML;
        $btn.disabled = true;
        $btn.innerHTML = '<i class="ri-loader-4-line"></i> Szukam…';
        var fd = new FormData();
        fd.append('_csrfToken', csrf);
        fd.append('lead_id', leadId);
        fd.append('mode', mode);
        fetch(URL_LI, {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
        }).then(function(r){return r.json();}).then(function(j) {
            $btn.disabled = false;
            $btn.innerHTML = origHtml;
            if (!j.ok) {
                $results.innerHTML = '<div class="alert alert-warning small py-2 mb-0">' +
                    '<i class="ri-error-warning-line"></i> ' + escapeHtml(j.hint || j.error || 'Blad wyszukiwania') + '</div>';
                $results.style.display = 'block';
                return;
            }
            if (!j.results || j.results.length === 0) {
                $results.innerHTML = '<div class="alert alert-info small py-2 mb-0">' +
                    '<i class="ri-search-line"></i> Nie znaleziono profilu na LinkedIn. Sprobuj Google fallback.</div>';
                $results.style.display = 'block';
                return;
            }
            var html = '<div class="small text-muted mb-1">Znaleziono ' + j.results.length + ' wynik(ów) via ' + j.provider + ':</div>';
            html += '<div class="list-group list-group-flush">';
            j.results.forEach(function(r, i) {
                html += '<div class="list-group-item px-2 py-2">' +
                    '<div class="d-flex justify-content-between align-items-start gap-2">' +
                        '<div class="flex-grow-1 small">' +
                            '<a href="' + escapeHtml(r.url) + '" target="_blank" rel="noopener" style="color:#0a66c2;" class="fw-semibold">' +
                                '<i class="ri-linkedin-box-fill"></i> ' + escapeHtml(r.title) + '</a>' +
                            '<div class="text-muted small mt-1">' + escapeHtml(r.snippet) + '</div>' +
                            '<div class="text-muted small"><code style="font-size:11px;">' + escapeHtml(r.url) + '</code></div>' +
                        '</div>' +
                        '<button type="button" class="btn btn-sm btn-success save-li" ' +
                            'data-url="' + escapeHtml(r.url) + '" data-mode="' + mode + '" title="Zapisz do leada">' +
                            '<i class="ri-check-line"></i>' +
                        '</button>' +
                    '</div>' +
                '</div>';
            });
            html += '</div>';
            $results.innerHTML = html;
            $results.style.display = 'block';

            // Save button handler
            $results.querySelectorAll('.save-li').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var fd2 = new FormData();
                    fd2.append('_csrfToken', csrf);
                    fd2.append('lead_id', leadId);
                    fd2.append('mode', btn.dataset.mode);
                    fd2.append('save', '1');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="ri-loader-4-line"></i>';
                    fetch(URL_LI, {
                        method: 'POST', body: fd2, credentials: 'same-origin',
                        headers: { 'X-CSRF-Token': csrf }
                    }).then(function(r){return r.json();}).then(function(j2) {
                        if (j2.ok && j2.saved) {
                            btn.innerHTML = '<i class="ri-check-double-line"></i>';
                            btn.classList.replace('btn-success', 'btn-outline-success');
                            setTimeout(function() { location.reload(); }, 800);
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="ri-check-line"></i>';
                            alert(j2.hint || 'Nie zapisano - moze pole juz wypelnione?');
                        }
                    });
                });
            });
        }).catch(function(e) {
            $btn.disabled = false;
            $btn.innerHTML = origHtml;
            $results.innerHTML = '<div class="alert alert-danger small py-2 mb-0">Blad sieciowy: ' + escapeHtml(e.message) + '</div>';
            $results.style.display = 'block';
        });
    }

    ['li-search-person', 'li-search-company'].forEach(function(id) {
        var $btn = document.getElementById(id);
        if ($btn) {
            $btn.addEventListener('click', function() { search($btn.dataset.mode, $btn); });
        }
    });
})();
</script>

<script>
// FALA 11: AI Draft response + AI Summary
(function() {
    var csrf = '<?= $this->request->getAttribute('csrfToken') ?>';
    var leadId = '<?= h($lead->id) ?>';
    var URL_DRAFT = '<?= $this->Url->build(['action' => 'aiDraftResponseJson']) ?>';
    var URL_SUMMARY = '<?= $this->Url->build(['action' => 'aiSummarizeJson']) ?>';

    function esc(s) {
        return String(s || '').replace(/[&<>"]/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
        });
    }

    // ===== AI DRAFT =====
    var $btnDraft = document.getElementById('btn-ai-draft');
    if ($btnDraft) {
        var modalEl = document.getElementById('aiDraftModal');
        var modal = new bootstrap.Modal(modalEl);
        var $loading = document.getElementById('ai-draft-loading');
        var $content = document.getElementById('ai-draft-content');
        var $error = document.getElementById('ai-draft-error');
        var $tone = document.getElementById('ai-tone');
        var $extra = document.getElementById('ai-extra');
        var $subject = document.getElementById('ai-subject');
        var $body = document.getElementById('ai-body');
        var $meta = document.getElementById('ai-meta');
        var $copy = document.getElementById('ai-copy');
        var $regen = document.getElementById('ai-regen');

        function generate() {
            $content.style.display = 'none';
            $error.style.display = 'none';
            $loading.style.display = 'block';
            var fd = new FormData();
            fd.append('_csrfToken', csrf);
            fd.append('lead_id', leadId);
            fd.append('tone', $tone.value);
            fd.append('context', $extra.value);
            fetch(URL_DRAFT, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrf }
            }).then(function(r){return r.json();}).then(function(j) {
                $loading.style.display = 'none';
                if (!j.ok) {
                    $error.textContent = j.hint || j.error || 'Błąd AI';
                    $error.style.display = 'block';
                    return;
                }
                $subject.value = j.draft_subject || '';
                $body.value = j.draft_body || '';
                $meta.innerHTML = 'Bazujące na ' + (j.thread_count || 0) + ' wiadomościach. Ostatnia: ' +
                    esc(j.last_msg_from || '?') + ' z ' + esc(j.last_msg_date || '?');
                $content.style.display = 'block';
            }).catch(function(e) {
                $loading.style.display = 'none';
                $error.textContent = 'Błąd sieciowy: ' + e.message;
                $error.style.display = 'block';
            });
        }

        $btnDraft.addEventListener('click', function() {
            modal.show();
            generate();
        });
        // FALA 17: buttony w timeline (email_in card) tez triggeruja ten modal
        document.querySelectorAll('.btn-ai-draft').forEach(function(btn) {
            btn.addEventListener('click', function() {
                modal.show();
                generate();
            });
        });
        $regen.addEventListener('click', generate);
        $copy.addEventListener('click', function() {
            var full = 'Temat: ' + $subject.value + '\n\n' + $body.value;
            navigator.clipboard.writeText(full).then(function() {
                $copy.innerHTML = '<i class="ri-check-double-line"></i> Skopiowane!';
                setTimeout(function() {
                    $copy.innerHTML = '<i class="ri-clipboard-line"></i> Kopiuj do schowka';
                }, 2000);
            });
        });
    }

    // ===== AI SUMMARY =====
    var $btnSum = document.getElementById('btn-ai-summarize');
    if ($btnSum) {
        var sumModal = new bootstrap.Modal(document.getElementById('aiSummaryModal'));
        var $sumBody = document.getElementById('ai-summary-body');

        $btnSum.addEventListener('click', function() {
            sumModal.show();
            $sumBody.innerHTML = '<div class="text-center py-4"><i class="ri-loader-4-line fs-2 text-info"></i><div class="mt-2 small">GPT analizuje historię leada…</div></div>';
            var fd = new FormData();
            fd.append('_csrfToken', csrf);
            fd.append('lead_id', leadId);
            fetch(URL_SUMMARY, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-CSRF-Token': csrf }
            }).then(function(r){return r.json();}).then(function(j) {
                if (!j.ok) {
                    $sumBody.innerHTML = '<div class="alert alert-warning">' + esc(j.hint || j.error) + '</div>';
                    return;
                }
                var sentColor = {positive:'success',neutral:'secondary',negative:'danger',urgent:'warning'}[j.sentiment] || 'secondary';
                var stepsHtml = '';
                (j.next_steps || []).forEach(function(s) {
                    stepsHtml += '<li>' + esc(s) + '</li>';
                });
                $sumBody.innerHTML =
                    '<div class="mb-3">' +
                        '<span class="badge bg-' + sentColor + ' me-1">Sentyment: ' + esc(j.sentiment) + '</span>' +
                        (j.probability_hint > 0 ? '<span class="badge bg-info">AI sugeruje probability: ' + j.probability_hint + '%</span>' : '') +
                    '</div>' +
                    '<div class="mb-3"><strong>Podsumowanie:</strong><p class="mt-1">' + esc(j.summary).replace(/\n/g, '<br>') + '</p></div>' +
                    (stepsHtml ? '<div><strong>Rekomendowane następne kroki:</strong><ol class="mt-1">' + stepsHtml + '</ol></div>' : '');
            }).catch(function(e) {
                $sumBody.innerHTML = '<div class="alert alert-danger">Błąd sieciowy: ' + esc(e.message) + '</div>';
            });
        });
    }
})();
</script>

<script>
// FALA 15: Toggle listy zlecen z quote_request activity
(function() {
    document.querySelectorAll('.btn-toggle-shipments').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var t = document.getElementById(btn.getAttribute('data-target'));
            if (t) t.style.display = (t.style.display === 'none' ? 'block' : 'none');
        });
    });
})();
</script>

<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\RouteOffer $offer
 */
$this->assign('title', $offer->subject ?? 'Oferta');

$fmtMoney = static fn ($v, $cur = 'PLN') => number_format((float)$v, 2, ',', ' ') . ' ' . $cur;
$fmtDate  = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d.m.Y') : (string)$v;
$fmtDT    = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i') : (string)$v;

$accessUrl = $this->request->getUri()->getScheme() . '://' . $this->request->getUri()->getHost() . '/oferty/wglad/' . $offer->access_token;

$statusInfo = [
    'draft'    => ['Szkic',        'bg-secondary'],
    'sent'     => ['Wysłana',      'bg-info'],
    'viewed'   => ['Otwarta przez klienta', 'bg-primary'],
    'accepted' => ['Zaakceptowana',  'bg-success'],
    'rejected' => ['Odrzucona',     'bg-danger'],
    'expired'  => ['Wygasła',       'bg-dark'],
];
[$sLabel, $sCls] = $statusInfo[(string)$offer->status] ?? [(string)$offer->status, 'bg-secondary'];
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= h($offer->subject ?? 'Oferta') ?></h1>
        <div><span class="badge <?= $sCls ?>"><?= h($sLabel) ?></span></div>
    </div>
    <div class="d-flex gap-2">
        <?= $this->Html->link('<i class="ri-arrow-left-line me-1"></i>' . __('Powrót'), ['action' => 'index'], ['class' => 'btn btn-outline-secondary btn-sm', 'escape' => false]) ?>
        <?php if ($offer->status === 'draft' && !empty($offer->sent_to_email)): ?>
            <?= $this->Form->postLink('<i class="ri-mail-send-line me-1"></i>' . __('Wyślij do klienta'),
                ['action' => 'send', $offer->id],
                ['class' => 'btn btn-primary btn-sm', 'escape' => false, 'confirm' => __('Wysłać ofertę na :email?', [':email' => $offer->sent_to_email])]
            ) ?>
        <?php endif ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><?= __('Szczegóły oferty') ?></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-4 text-muted"><?= __('Kwota') ?></dt>
                    <dd class="col-8"><strong class="fs-5"><?= h($fmtMoney($offer->price, $offer->currency)) ?></strong> netto
                        <?php if ($offer->vat_rate !== null): ?>
                            <span class="text-muted small"> + VAT <?= h($offer->vat_rate) ?>%</span>
                        <?php endif ?>
                    </dd>

                    <dt class="col-4 text-muted"><?= __('Termin płatności') ?></dt>
                    <dd class="col-8"><?= h($offer->payment_days ?? '—') ?> dni</dd>

                    <dt class="col-4 text-muted"><?= __('Ważna do') ?></dt>
                    <dd class="col-8"><?= h($fmtDate($offer->valid_until)) ?></dd>

                    <dt class="col-4 text-muted"><?= __('Utworzona') ?></dt>
                    <dd class="col-8"><?= h($fmtDT($offer->created)) ?></dd>

                    <dt class="col-4 text-muted"><?= __('Wysłana') ?></dt>
                    <dd class="col-8"><?= h($fmtDT($offer->sent_at)) ?> <?php if ($offer->sent_to_email): ?>→ <?= h($offer->sent_to_email) ?><?php endif ?></dd>

                    <dt class="col-4 text-muted"><?= __('Otwarta przez klienta') ?></dt>
                    <dd class="col-8"><?= h($fmtDT($offer->viewed_at)) ?></dd>

                    <dt class="col-4 text-muted"><?= __('Decyzja') ?></dt>
                    <dd class="col-8"><?= h($fmtDT($offer->decided_at)) ?></dd>

                    <?php if (!empty($offer->decision_reason)): ?>
                        <dt class="col-4 text-muted"><?= __('Powód decyzji') ?></dt>
                        <dd class="col-8"><?= h($offer->decision_reason) ?></dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>

        <?php if (!empty($offer->message_body)): ?>
            <div class="card mt-3">
                <div class="card-header"><?= __('Wiadomość') ?></div>
                <div class="card-body"><?= $this->Text->autoParagraph(h($offer->message_body)) ?></div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-md-4">
        <?php if (!empty($offer->contractor)): ?>
            <div class="card mb-3">
                <div class="card-header"><?= __('Kontrahent') ?></div>
                <div class="card-body">
                    <div class="fw-medium"><?= h($offer->contractor->name) ?></div>
                    <?php if (!empty($offer->contractor->nip)): ?>
                        <div class="small">NIP: <?= h($offer->contractor->nip) ?></div>
                    <?php endif ?>
                    <?php if (!empty($offer->sent_to_email)): ?>
                        <div class="small text-muted mt-2"><i class="ri-mail-line me-1"></i><?= h($offer->sent_to_email) ?></div>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>

        <?php if (!empty($offer->route_plan)): ?>
            <div class="card mb-3">
                <div class="card-header"><?= __('Plan trasy') ?></div>
                <div class="card-body">
                    <div class="fw-medium"><?= h($offer->route_plan->name) ?></div>
                    <?php if (!empty($offer->route_plan->distance_km)): ?>
                        <div class="small text-muted mt-1"><i class="ri-road-map-line me-1"></i><?= number_format((float)$offer->route_plan->distance_km, 0, ',', ' ') ?> km</div>
                    <?php endif ?>
                    <?php if (!empty($offer->route_plan->duration_min)): ?>
                        <div class="small text-muted"><i class="ri-time-line me-1"></i><?= h((int)($offer->route_plan->duration_min / 60)) ?>h <?= h($offer->route_plan->duration_min % 60) ?>min</div>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>

        <div class="card">
            <div class="card-header"><?= __('Link dla klienta') ?></div>
            <div class="card-body">
                <div class="small text-muted mb-2">
                    <?= __('Klient akceptuje ofertę bez logowania:') ?>
                </div>
                <input type="text" class="form-control form-control-sm" value="<?= h($accessUrl) ?>" readonly onclick="this.select()">
                <button class="btn btn-outline-primary btn-sm w-100 mt-2" onclick="navigator.clipboard.writeText('<?= h($accessUrl) ?>').then(() => this.innerHTML = '<i class=&quot;ri-check-line&quot;></i> <?= __('Skopiowano!') ?>')">
                    <i class="ri-clipboard-line me-1"></i><?= __('Kopiuj link') ?>
                </button>
            </div>
        </div>
    </div>
</div>

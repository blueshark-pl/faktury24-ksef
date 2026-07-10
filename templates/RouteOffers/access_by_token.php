<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\RouteOffer $offer
 * @var bool $isExpired
 */
$this->assign('title', $offer->subject ?? 'Oferta');

$fmtMoney = static fn ($v, $cur = 'PLN') => number_format((float)$v, 2, ',', ' ') . ' ' . strtoupper($cur);
$fmtDate  = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d.m.Y') : (string)$v;

$isAccepted = $offer->status === 'accepted';
$isRejected = $offer->status === 'rejected';
$canDecide  = in_array($offer->status, ['sent', 'viewed'], true) && !$isExpired;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($offer->subject ?? 'Oferta transportowa') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css">
    <style>
        body { background: #f3f4f6; padding: 20px; font-family: system-ui, -apple-system, sans-serif; }
        .offer-container { max-width: 800px; margin: 0 auto; }
        .offer-header { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 32px; border-radius: 14px 14px 0 0; }
        .offer-price { font-size: 3rem; font-weight: 700; letter-spacing: -0.02em; }
        .offer-body { background: white; padding: 24px 32px; border-radius: 0 0 14px 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .status-badge-hero { display: inline-block; padding: 8px 20px; border-radius: 30px; font-weight: 600; margin-bottom: 8px; }
        .btn-accept { background: #22c55e; border: none; padding: 14px 28px; font-size: 1.1rem; font-weight: 600; }
        .btn-accept:hover { background: #16a34a; }
        .btn-reject { background: transparent; color: #ef4444; border: 2px solid #ef4444; padding: 12px 24px; }
    </style>
</head>
<body>

<div class="offer-container">
    <?= $this->Flash->render() ?>

    <!-- Header -->
    <div class="offer-header">
        <?php if ($isAccepted): ?>
            <div class="status-badge-hero bg-white text-success">
                <i class="ri-check-double-line me-1"></i><?= __('Oferta zaakceptowana') ?>
            </div>
        <?php elseif ($isRejected): ?>
            <div class="status-badge-hero bg-white text-danger">
                <i class="ri-close-line me-1"></i><?= __('Oferta odrzucona') ?>
            </div>
        <?php elseif ($isExpired): ?>
            <div class="status-badge-hero bg-white text-warning">
                <i class="ri-time-line me-1"></i><?= __('Oferta wygasła') ?>
            </div>
        <?php else: ?>
            <div class="status-badge-hero bg-white text-primary">
                <i class="ri-truck-line me-1"></i><?= __('Oferta transportowa') ?>
            </div>
        <?php endif ?>

        <h1 class="mb-2"><?= h($offer->subject ?? 'Oferta') ?></h1>
        <?php if (!empty($offer->sent_to_name)): ?>
            <div class="small opacity-75"><?= __('Dla') ?>: <?= h($offer->sent_to_name) ?></div>
        <?php endif ?>

        <div class="offer-price mt-4"><?= h($fmtMoney($offer->price, $offer->currency)) ?></div>
        <?php if ($offer->vat_rate !== null): ?>
            <div class="opacity-75"><?= __('netto + VAT') ?> <?= h($offer->vat_rate) ?>%</div>
        <?php else: ?>
            <div class="opacity-75"><?= __('cena netto') ?></div>
        <?php endif ?>
    </div>

    <!-- Body -->
    <div class="offer-body">
        <?php if (!empty($offer->message_body)): ?>
            <div class="mb-4">
                <?= $this->Text->autoParagraph(h($offer->message_body)) ?>
            </div>
            <hr>
        <?php endif ?>

        <div class="row g-3 my-3">
            <?php if (!empty($offer->route_plan)): ?>
                <div class="col-md-6">
                    <div class="text-muted small"><?= __('Trasa') ?></div>
                    <div class="fw-medium"><?= h($offer->route_plan->name) ?></div>
                    <?php if (!empty($offer->route_plan->distance_km)): ?>
                        <div class="small"><i class="ri-road-map-line me-1"></i><?= number_format((float)$offer->route_plan->distance_km, 0, ',', ' ') ?> km</div>
                    <?php endif ?>
                </div>
            <?php endif ?>

            <div class="col-md-6">
                <div class="text-muted small"><?= __('Termin płatności') ?></div>
                <div class="fw-medium"><?= h($offer->payment_days ?? '—') ?> <?= __('dni') ?></div>
            </div>

            <?php if (!empty($offer->valid_until)): ?>
                <div class="col-md-6">
                    <div class="text-muted small"><?= __('Ważność oferty do') ?></div>
                    <div class="fw-medium"><?= h($fmtDate($offer->valid_until)) ?></div>
                </div>
            <?php endif ?>
        </div>

        <?php if ($canDecide): ?>
            <hr>
            <div class="text-center py-3">
                <p class="mb-3 fw-medium fs-5"><?= __('Czy akceptujesz tę ofertę?') ?></p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <?= $this->Form->postLink(
                        '<i class="ri-check-double-line me-1"></i>' . __('Akceptuję ofertę'),
                        ['action' => 'accept', $offer->access_token],
                        [
                            'class' => 'btn btn-accept text-white',
                            'escape' => false,
                            'confirm' => __('Potwierdzasz akceptację oferty na kwotę :price?', [':price' => $fmtMoney($offer->price, $offer->currency)]),
                        ]
                    ) ?>

                    <button type="button" class="btn btn-reject" data-bs-toggle="collapse" data-bs-target="#reject-form">
                        <i class="ri-close-line me-1"></i><?= __('Odrzucam') ?>
                    </button>
                </div>

                <div class="collapse mt-4" id="reject-form">
                    <?= $this->Form->create(null, ['url' => ['action' => 'reject', $offer->access_token]]) ?>
                        <label class="form-label small text-muted"><?= __('Krótki powód (opcjonalne)') ?>:</label>
                        <textarea name="reason" class="form-control mb-2" rows="2" placeholder="<?= __('np. za drogo, znaleźliśmy inną firmę…') ?>"></textarea>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="ri-close-line me-1"></i><?= __('Potwierdź odrzucenie') ?>
                        </button>
                    <?= $this->Form->end() ?>
                </div>
            </div>
        <?php elseif ($isAccepted): ?>
            <div class="alert alert-success text-center">
                <i class="ri-check-double-line me-1"></i>
                <?= __('Dziękujemy! Skontaktujemy się z Państwem wkrótce w sprawie realizacji.') ?>
            </div>
        <?php elseif ($isExpired): ?>
            <div class="alert alert-warning text-center">
                <i class="ri-time-line me-1"></i>
                <?= __('Ta oferta straciła ważność. Prosimy o kontakt aby otrzymać nową.') ?>
            </div>
        <?php elseif ($isRejected): ?>
            <div class="alert alert-danger text-center">
                <?= __('Oferta została odrzucona.') ?>
            </div>
        <?php endif ?>
    </div>

    <div class="text-center text-muted small mt-3">
        <?= __('Wygenerowano przez faktury24.com') ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

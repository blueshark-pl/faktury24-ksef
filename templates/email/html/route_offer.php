<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\RouteOffer $offer
 * @var string $accessUrl
 */

$fmtMoney = static fn ($v, $cur = 'PLN') => number_format((float)$v, 2, ',', ' ') . ' ' . strtoupper($cur);
?>
<div style="font-family: system-ui, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 30px; border-radius: 12px 12px 0 0;">
        <h1 style="margin: 0 0 8px; font-size: 22px;"><?= h($offer->subject ?? 'Oferta transportowa') ?></h1>
        <div style="font-size: 32px; font-weight: 700; margin-top: 16px;"><?= h($fmtMoney($offer->price, $offer->currency)) ?></div>
        <div style="opacity: 0.8;">
            <?= __('cena netto') ?>
            <?php if ($offer->vat_rate !== null): ?>
                + VAT <?= h($offer->vat_rate) ?>%
            <?php endif ?>
        </div>
    </div>

    <div style="background: white; padding: 24px; border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 12px 12px;">
        <?php if (!empty($offer->sent_to_name)): ?>
            <p><?= __('Szanowni Państwo :name', [':name' => $offer->sent_to_name]) ?>,</p>
        <?php else: ?>
            <p><?= __('Dzień dobry') ?>,</p>
        <?php endif ?>

        <?php if (!empty($offer->message_body)): ?>
            <div style="white-space: pre-wrap; margin: 16px 0;"><?= h($offer->message_body) ?></div>
        <?php else: ?>
            <p><?= __('Przesyłamy ofertę transportową zgodnie z ustaleniami. Szczegóły w linku poniżej.') ?></p>
        <?php endif ?>

        <table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; color: #6b7280;"><?= __('Termin płatności') ?></td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;"><?= h($offer->payment_days ?? '—') ?> dni</td>
            </tr>
            <?php if (!empty($offer->valid_until)): ?>
                <tr>
                    <td style="padding: 8px 0; color: #6b7280;"><?= __('Ważność') ?></td>
                    <td style="padding: 8px 0; text-align: right; font-weight: 600;">
                        do <?= h($offer->valid_until instanceof \DateTimeInterface ? $offer->valid_until->format('d.m.Y') : (string)$offer->valid_until) ?>
                    </td>
                </tr>
            <?php endif ?>
        </table>

        <div style="text-align: center; margin: 30px 0;">
            <a href="<?= h($accessUrl) ?>"
               style="background: #3b82f6; color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block;">
                <?= __('Zobacz ofertę i akceptuj') ?>
            </a>
        </div>

        <p style="color: #6b7280; font-size: 12px; text-align: center; margin-top: 20px;">
            <?= __('Link nie wymaga logowania — możesz otworzyć na telefonie lub przekazać osobie decyzyjnej.') ?>
        </p>
    </div>

    <div style="text-align: center; color: #9ca3af; font-size: 12px; margin-top: 16px;">
        <?= __('Wygenerowano przez faktury24.com') ?>
    </div>
</div>

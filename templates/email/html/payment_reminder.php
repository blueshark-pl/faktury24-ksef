<?php
/**
 * @var \App\View\AppView $this
 * @var string $fullnumber
 * @var float  $remaining
 * @var string $currency
 * @var string $paymentdate
 * @var int    $days_overdue
 * @var int    $days_to_due
 * @var string $seller_name
 * @var string $contractor_name
 * @var string $custom_message
 */
$amount = number_format((float)$remaining, 2, ',', ' ');
$accentColor = $days_overdue > 0 ? '#dc2626' : ($days_to_due <= 7 ? '#f59e0b' : '#3b82f6');
$lightBg = $days_overdue > 0 ? '#fee2e2' : ($days_to_due <= 7 ? '#fef3c7' : '#dbeafe');
$statusLabel = $days_overdue > 0
    ? 'PRZETERMINOWANE O ' . $days_overdue . ' ' . ($days_overdue === 1 ? 'DZIEŃ' : 'DNI')
    : ($days_to_due === 0 ? 'DZIŚ TERMIN' : 'TERMIN ZA ' . $days_to_due . ' ' . ($days_to_due === 1 ? 'DZIEŃ' : 'DNI'));
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Przypomnienie o płatności</title>
</head>
<body style="margin:0;padding:0;background:#f9fafb;font-family:Arial,Helvetica,sans-serif;color:#1f2937">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f9fafb;padding:24px 0">
    <tr><td align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08)">

            <!-- Header -->
            <tr><td style="padding:24px 28px;background:<?= h($accentColor) ?>;color:#fff">
                <div style="font-size:12px;letter-spacing:0.05em;text-transform:uppercase;opacity:0.85">Przypomnienie</div>
                <div style="font-size:22px;font-weight:bold;margin-top:4px">Faktura <?= h($fullnumber) ?></div>
            </td></tr>

            <!-- Status banner -->
            <tr><td style="padding:12px 28px;background:<?= h($lightBg) ?>;text-align:center">
                <span style="display:inline-block;padding:6px 16px;background:<?= h($accentColor) ?>;color:#fff;border-radius:14px;font-size:11px;font-weight:bold;letter-spacing:0.04em">
                    <?= h($statusLabel) ?>
                </span>
            </td></tr>

            <!-- Body -->
            <tr><td style="padding:28px">
                <p style="margin:0 0 14px;font-size:15px">
                    Szanowni Państwo<?= $contractor_name ? ' <strong>' . h($contractor_name) . '</strong>' : '' ?>,
                </p>

                <?php if ($custom_message !== ''): ?>
                    <div style="padding:14px;background:#f3f4f6;border-radius:6px;margin:14px 0;white-space:pre-wrap">
                        <?= h($custom_message) ?>
                    </div>
                <?php else: ?>
                    <p style="margin:0 0 14px;font-size:15px;line-height:1.55">
                        <?php if ($days_overdue > 0): ?>
                            Uprzejmie informujemy, że termin płatności faktury <strong><?= h($fullnumber) ?></strong>
                            upłynął <strong><?= h($days_overdue) ?> <?= $days_overdue === 1 ? 'dzień' : 'dni' ?> temu</strong>
                            (termin: <?= h($paymentdate) ?>).
                            Prosimy o jak najszybsze uregulowanie należności.
                        <?php elseif ($days_to_due === 0): ?>
                            Uprzejmie przypominamy, że <strong>dziś</strong> upływa termin płatności faktury
                            <strong><?= h($fullnumber) ?></strong>.
                        <?php else: ?>
                            Uprzejmie przypominamy, że za <strong><?= h($days_to_due) ?>
                            <?= $days_to_due === 1 ? 'dzień' : 'dni' ?></strong>
                            (<?= h($paymentdate) ?>) upływa termin płatności faktury <strong><?= h($fullnumber) ?></strong>.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <!-- Kwota -->
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:18px 0;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden">
                    <tr><td style="padding:14px;background:#f9fafb">
                        <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em">Pozostało do zapłaty</div>
                        <div style="font-size:26px;font-weight:bold;color:#1f2937;margin-top:4px">
                            <?= h($amount) ?> <?= h($currency) ?>
                        </div>
                        <div style="font-size:12px;color:#6b7280;margin-top:4px">
                            Termin: <strong><?= h($paymentdate) ?></strong>
                        </div>
                    </td></tr>
                </table>

                <p style="margin:0 0 14px;font-size:14px;line-height:1.55;color:#4b5563">
                    Prosimy o przelew na wskazany rachunek bankowy podany na fakturze.
                    W razie pytań lub gdy płatność została już uregulowana — prosimy o kontakt.
                </p>

                <p style="margin:24px 0 0;font-size:14px">
                    Z poważaniem,<br>
                    <strong><?= h($seller_name) ?></strong>
                </p>
            </td></tr>

            <!-- Footer -->
            <tr><td style="padding:14px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;font-size:11px;color:#9ca3af">
                Wiadomość wygenerowana automatycznie z systemu rozliczeń.
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>

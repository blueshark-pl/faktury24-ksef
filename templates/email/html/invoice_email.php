<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Invoice $invoice
 * @var string $fullnumber
 * @var string $sellerName
 */
$paymentMethodLabels = [
    'transfer'     => 'Przelew bankowy',
    'cash'         => 'Gotówka',
    'card'         => 'Karta płatnicza',
    'cheque'       => 'Czek',
    'credit'       => 'Kredyt',
    'barter'       => 'Barter',
    'compensation' => 'Kompensata',
    'other'        => 'Inna forma płatności',
];

$seller     = $invoice->invoice_company_detail ?? null;
$buyer      = $invoice->invoice_contractor ?? null;

$sellerNameStr  = trim((string)($seller?->name   ?? $sellerName ?? ''));
$sellerNip      = preg_replace('/\D+/', '', (string)($seller?->nip ?? ''));
$sellerStreet   = trim((string)($seller?->street  ?? ''));
$sellerZip      = trim((string)($seller?->zip     ?? ''));
$sellerCity     = trim((string)($seller?->city    ?? ''));
$sellerEmail    = trim((string)($seller?->email   ?? ''));
$sellerPhone    = trim((string)($seller?->phone   ?? ''));
$sellerAddress  = trim(implode(', ', array_filter([$sellerStreet, $sellerZip . ($sellerZip && $sellerCity ? ' ' : '') . $sellerCity])));

$buyerName      = trim((string)($buyer?->name  ?? ''));
$buyerNip       = preg_replace('/\D+/', '', (string)($buyer?->vatid ?? $buyer?->nip ?? ''));
$buyerStreet    = trim((string)($buyer?->street ?? ''));
$buyerZip       = trim((string)($buyer?->zip    ?? ''));
$buyerCity      = trim((string)($buyer?->city   ?? ''));
$buyerAddress   = trim(implode(', ', array_filter([$buyerStreet, $buyerZip . ($buyerZip && $buyerCity ? ' ' : '') . $buyerCity])));

$invDate        = $invoice->date        ? $invoice->date->format('d.m.Y')        : '';
$invPaydate     = $invoice->paymentdate ? $invoice->paymentdate->format('d.m.Y') : '';
$invTotal       = number_format((float)($invoice->total ?? 0), 2, ',', ' ');
$invNetto       = number_format((float)($invoice->netto ?? 0), 2, ',', ' ');
$invTax         = number_format((float)($invoice->tax   ?? 0), 2, ',', ' ');
$invCurrency    = strtoupper((string)($invoice->currency ?? 'PLN'));
$invMethodRaw   = (string)($invoice->paymentmethod ?? '');
$invMethod      = $paymentMethodLabels[$invMethodRaw] ?? ($invMethodRaw !== '' ? ucfirst($invMethodRaw) : '');
$invAlreadypaid = (float)($invoice->alreadypaid ?? 0);
$invRemaining   = (float)($invoice->remaining   ?? $invoice->total ?? 0);
$formattedRemaining = number_format($invRemaining, 2, ',', ' ');

$isPaid         = $invRemaining <= 0;
$statusLabel    = $isPaid ? 'OPŁACONA' : 'DO OPŁACENIA';
$statusBg       = $isPaid ? '#22c55e'  : '#f59e0b';

$accentColor    = '#6fc14b';
$lightBg        = '#f2f9ee';
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="format-detection" content="telephone=no">
    <!--[if mso]><style>* { font-family: sans-serif !important; }</style><![endif]-->
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700&display=swap&subset=latin-ext" rel="stylesheet">
    <style type="text/css">
        * { -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        html, body { width: 100% !important; margin: 0; padding: 0; font-family: 'Poppins', Arial, sans-serif; background: #e8edf5; }
        #outlook a { padding: 0; }
        .ExternalClass, .ExternalClass p, .ExternalClass span,
        .ExternalClass font, .ExternalClass td, .ExternalClass div { line-height: 100%; }
        table, td { mso-table-lspace: 0pt !important; mso-table-rspace: 0pt !important; }
        img { -ms-interpolation-mode: bicubic; border: none; display: block; }
        div[style*="margin: 16px 0"] { margin: 0 !important; }
        .a6S { display: none !important; opacity: 0.01 !important; }
        img.g-img + div { display: none !important; }
    </style>
    <title>Faktura <?= h($fullnumber) ?></title>
</head>
<body style="-webkit-text-size-adjust:none; background:#e8edf5; padding:0; margin:0;">
<div style="background:#e8edf5;">
<!-- WRAPPER -->
<table width="100%" style="table-layout:fixed; background:#e8edf5;" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<!-- INNER -->
<table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px; margin:0 auto;">

    <!-- SPACER TOP -->
    <tr><td height="30" style="height:30px; line-height:30px;">&nbsp;</td></tr>

    <!-- HEADER BAR -->
    <tr>
        <td style="width:600px; border-radius:8px 8px 0 0; background:<?= $accentColor ?>; padding:28px 40px;" bgcolor="<?= $accentColor ?>">
            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="color:#ffffff; font-family:'Poppins',Arial,sans-serif;">
                        <div style="font-size:11px; letter-spacing:2px; text-transform:uppercase; opacity:.75; line-height:1.4;">Dokument finansowy</div>
                        <div style="font-size:24px; font-weight:700; margin-top:6px; line-height:1.2;"><?= h($fullnumber) ?></div>
                        <?php if ($sellerNameStr): ?>
                        <div style="font-size:13px; margin-top:6px; opacity:.85; line-height:1.4;">od: <?= h($sellerNameStr) ?></div>
                        <?php endif; ?>
                    </td>
                    <td align="right" valign="middle" style="padding-left:20px;">
                        <div style="background:<?= $statusBg ?>; color:#fff; font-size:11px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; padding:6px 14px; border-radius:20px; display:inline-block; white-space:nowrap; line-height:1.4;">
                            <?= $statusLabel ?>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- WHITE BODY -->
    <tr>
        <td bgcolor="#ffffff" style="background:#ffffff; padding:0 40px 32px;">

            <!-- SPACER -->
            <table width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td height="28" style="height:28px;">&nbsp;</td></tr></table>

            <!-- FROM / TO -->
            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr valign="top">
                    <!-- SPRZEDAWCA -->
                    <td width="250" style="width:250px; vertical-align:top;">
                        <div style="font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:<?= $accentColor ?>; margin-bottom:8px; line-height:1.4;">Sprzedawca</div>
                        <?php if ($sellerNameStr): ?>
                        <div style="font-size:14px; font-weight:600; color:#1a1a1a; line-height:1.4; margin-bottom:4px;"><?= h($sellerNameStr) ?></div>
                        <?php endif; ?>
                        <?php if ($sellerNip): ?>
                        <div style="font-size:12px; color:#555; line-height:1.5;">NIP: <?= h($sellerNip) ?></div>
                        <?php endif; ?>
                        <?php if ($sellerAddress): ?>
                        <div style="font-size:12px; color:#555; line-height:1.5;"><?= h($sellerAddress) ?></div>
                        <?php endif; ?>
                        <?php if ($sellerEmail): ?>
                        <div style="font-size:12px; color:#555; line-height:1.5;"><?= h($sellerEmail) ?></div>
                        <?php endif; ?>
                        <?php if ($sellerPhone): ?>
                        <div style="font-size:12px; color:#555; line-height:1.5;"><?= h($sellerPhone) ?></div>
                        <?php endif; ?>
                    </td>
                    <!-- DIVIDER -->
                    <td width="20" style="width:20px;"></td>
                    <!-- NABYWCA -->
                    <td width="250" style="width:250px; vertical-align:top; border-left:2px solid #e8edf5; padding-left:20px;">
                        <div style="font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:#6b7280; margin-bottom:8px; line-height:1.4;">Nabywca</div>
                        <?php if ($buyerName): ?>
                        <div style="font-size:14px; font-weight:600; color:#1a1a1a; line-height:1.4; margin-bottom:4px;"><?= h($buyerName) ?></div>
                        <?php endif; ?>
                        <?php if ($buyerNip): ?>
                        <div style="font-size:12px; color:#555; line-height:1.5;">NIP: <?= h($buyerNip) ?></div>
                        <?php endif; ?>
                        <?php if ($buyerAddress): ?>
                        <div style="font-size:12px; color:#555; line-height:1.5;"><?= h($buyerAddress) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <!-- HR -->
            <table width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td height="24" style="height:24px;">&nbsp;</td></tr></table>
            <table width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td bgcolor="#e8edf5" height="1" style="height:1px; line-height:1px; font-size:1px;">&nbsp;</td></tr></table>
            <table width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td height="20" style="height:20px;">&nbsp;</td></tr></table>

            <!-- DETAILS GRID -->
            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="33%" style="vertical-align:top; padding-right:10px;">
                        <div style="font-size:10px; color:#9ca3af; text-transform:uppercase; letter-spacing:1px; line-height:1.4; margin-bottom:4px;">Data wystawienia</div>
                        <div style="font-size:14px; font-weight:600; color:#1a1a1a; line-height:1.4;"><?= h($invDate) ?: '—' ?></div>
                    </td>
                    <?php if ($invPaydate): ?>
                    <td width="33%" style="vertical-align:top; padding:0 5px;">
                        <div style="font-size:10px; color:#9ca3af; text-transform:uppercase; letter-spacing:1px; line-height:1.4; margin-bottom:4px;">Termin płatności</div>
                        <div style="font-size:14px; font-weight:600; color:#1a1a1a; line-height:1.4;"><?= h($invPaydate) ?></div>
                    </td>
                    <?php endif; ?>
                    <?php if ($invMethod): ?>
                    <td width="<?= $invPaydate ? '33%' : '66%' ?>" style="vertical-align:top; padding-left:10px;">
                        <div style="font-size:10px; color:#9ca3af; text-transform:uppercase; letter-spacing:1px; line-height:1.4; margin-bottom:4px;">Forma płatności</div>
                        <div style="font-size:14px; font-weight:600; color:#1a1a1a; line-height:1.4;"><?= h($invMethod) ?></div>
                    </td>
                    <?php endif; ?>
                </tr>
            </table>

            <!-- HR -->
            <table width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td height="20" style="height:20px;">&nbsp;</td></tr></table>
            <table width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td bgcolor="#e8edf5" height="1" style="height:1px; line-height:1px; font-size:1px;">&nbsp;</td></tr></table>
            <table width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td height="20" style="height:20px;">&nbsp;</td></tr></table>

            <!-- AMOUNT BOX -->
            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td bgcolor="<?= $lightBg ?>" style="background:<?= $lightBg ?>; border-radius:6px; padding:20px 24px;">
                        <table width="100%" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size:12px; color:#6b7280; line-height:1.8;">Netto</td>
                                <td align="right" style="font-size:12px; color:#374151; font-weight:500; line-height:1.8;"><?= h($invNetto) ?> <?= h($invCurrency) ?></td>
                            </tr>
                            <tr>
                                <td style="font-size:12px; color:#6b7280; line-height:1.8;">VAT</td>
                                <td align="right" style="font-size:12px; color:#374151; font-weight:500; line-height:1.8;"><?= h($invTax) ?> <?= h($invCurrency) ?></td>
                            </tr>
                            <tr>
                                <td colspan="2" bgcolor="#d1ddf5" height="1" style="height:1px; line-height:1px; font-size:1px;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td style="font-size:15px; font-weight:700; color:#1a1a1a; padding-top:8px; line-height:1.4;">Razem brutto</td>
                                <td align="right" style="font-size:20px; font-weight:700; color:<?= $accentColor ?>; padding-top:8px; line-height:1.4;"><?= h($invTotal) ?> <?= h($invCurrency) ?></td>
                            </tr>
                            <?php if (!$isPaid && $invAlreadypaid > 0): ?>
                            <tr>
                                <td style="font-size:12px; color:#6b7280; padding-top:4px; line-height:1.8;">Zapłacono</td>
                                <td align="right" style="font-size:12px; color:#22c55e; font-weight:600; padding-top:4px; line-height:1.8;"><?= h(number_format($invAlreadypaid, 2, ',', ' ')) ?> <?= h($invCurrency) ?></td>
                            </tr>
                            <tr>
                                <td style="font-size:13px; font-weight:700; color:#f59e0b; line-height:1.8;">Pozostało do zapłaty</td>
                                <td align="right" style="font-size:15px; font-weight:700; color:#f59e0b; line-height:1.8;"><?= h($formattedRemaining) ?> <?= h($invCurrency) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- HR -->
            <table width="100%" border="0" cellpadding="0" cellspacing="0"><tr><td height="24" style="height:24px;">&nbsp;</td></tr></table>

            <!-- ATTACHMENT NOTE -->
            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-size:13px; color:#6b7280; line-height:1.6; text-align:center; border:1px dashed #cbd5e1; border-radius:6px; padding:14px 20px;">
                        📎 Faktura <strong style="color:#1a1a1a;"><?= h($fullnumber) ?></strong> została dołączona do tej wiadomości jako plik PDF.
                    </td>
                </tr>
            </table>

        </td>
    </tr>

    <!-- ACCENT BOTTOM BAR -->
    <tr>
        <td bgcolor="<?= $accentColor ?>" height="6" style="height:6px; line-height:6px; background:<?= $accentColor ?>;">&nbsp;</td>
    </tr>

    <!-- FOOTER -->
    <tr>
        <td bgcolor="#2d3748" style="background:#2d3748; padding:20px 40px; border-radius:0 0 8px 8px;">
            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-size:12px; color:#a0aec0; line-height:1.6; text-align:center;">
                        <?php if ($sellerNameStr): ?>
                        <strong style="color:#e2e8f0;"><?= h($sellerNameStr) ?></strong>
                        <?php if ($sellerNip): ?> &bull; NIP: <?= h($sellerNip) ?><?php endif; ?>
                        <br>
                        <?php endif; ?>
                        <?php if ($sellerAddress): ?><?= h($sellerAddress) ?><?php if ($sellerEmail || $sellerPhone): ?><br><?php endif; ?><?php endif; ?>
                        <?php if ($sellerEmail): ?><?= h($sellerEmail) ?><?php endif; ?>
                        <?php if ($sellerEmail && $sellerPhone): ?> &bull; <?php endif; ?>
                        <?php if ($sellerPhone): ?><?= h($sellerPhone) ?><?php endif; ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- SPACER BOTTOM -->
    <tr><td height="30" style="height:30px; line-height:30px;">&nbsp;</td></tr>

</table>
<!-- END INNER -->
</td></tr>
</table>
<!-- END WRAPPER -->
</div>
</body>
</html>

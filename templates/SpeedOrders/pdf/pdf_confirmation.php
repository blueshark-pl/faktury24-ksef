<?php
/**
 * PDF potwierdzenia zlecenia transportowego (DomPdf).
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\SpeedOrder $order
 */
$fmt = function ($v) { return $v ? h($v) : '-'; };
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 20mm 15mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; font-size: 11pt; line-height: 1.4; }
        h1 { color: #0d6efd; font-size: 20pt; margin: 0 0 4pt 0; }
        h2 { color: #4338ca; font-size: 12pt; margin: 12pt 0 6pt 0; text-transform: uppercase; letter-spacing: 0.3pt; border-bottom: 1px solid #d1d5db; padding-bottom: 2pt; }
        .header { border-bottom: 3px solid #0d6efd; padding-bottom: 10pt; margin-bottom: 16pt; }
        .header .sym { color: #6b7280; font-size: 11pt; margin-top: 2pt; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; padding: 2pt 0; }
        .label { color: #6b7280; width: 35%; }
        .val { color: #111; font-weight: 500; }
        .route-box { background: #ecfdf5; padding: 10pt; border-left: 3px solid #10b981; margin: 8pt 0; }
        .price-box { background: #eef2ff; padding: 12pt; border-left: 3px solid #6366f1; text-align: right; margin: 8pt 0; }
        .price-box .amount { font-size: 18pt; font-weight: bold; color: #4338ca; }
        .price-box .small { font-size: 9pt; color: #6b7280; margin-top: 2pt; }
        .two-col { width: 100%; }
        .two-col td { width: 50%; padding: 0 6pt; vertical-align: top; }
        .foot { border-top: 1px solid #d1d5db; padding-top: 8pt; margin-top: 20pt; color: #6b7280; font-size: 9pt; text-align: center; }
        .notes { background: #fef3c7; padding: 10pt; border-radius: 3pt; margin-top: 8pt; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Potwierdzenie zlecenia transportowego</h1>
        <div class="sym">
            <strong><?= h($order->symbol) ?></strong>
            <?= $order->date_doc ? ' &middot; data: ' . h($order->date_doc->format('Y-m-d')) : '' ?>
            <?= $order->contract ? ' &middot; kontrakt: ' . h($order->contract) : '' ?>
        </div>
    </div>

    <table class="two-col">
        <tr>
            <td>
                <h2>Zleceniodawca</h2>
                <table class="grid">
                    <tr><td class="label">Nazwa:</td><td class="val"><?= $fmt($order->buyer_name) ?></td></tr>
                    <tr><td class="label">NIP:</td><td class="val"><?= $fmt($order->buyer_nip) ?></td></tr>
                    <tr><td class="label">Adres:</td><td class="val"><?= $fmt(trim(($order->buyer_street ?? '') . ', ' . ($order->buyer_postal_code ?? '') . ' ' . ($order->buyer_city ?? ''), ', ')) ?></td></tr>
                    <?php if ($order->buyer_country): ?><tr><td class="label">Kraj:</td><td class="val"><?= $fmt($order->buyer_country) ?></td></tr><?php endif; ?>
                    <?php if ($order->buyer_email): ?><tr><td class="label">Email:</td><td class="val"><?= $fmt($order->buyer_email) ?></td></tr><?php endif; ?>
                </table>
            </td>
            <td>
                <h2>Wykonawca</h2>
                <table class="grid">
                    <tr><td class="label">Nazwa:</td><td class="val"><?= $fmt($order->company_name) ?></td></tr>
                    <tr><td class="label">NIP:</td><td class="val"><?= $fmt($order->company_nip) ?></td></tr>
                    <?php if ($order->carrier): ?><tr><td class="label">Przewoźnik:</td><td class="val"><?= $fmt($order->carrier) ?></td></tr><?php endif; ?>
                </table>
            </td>
        </tr>
    </table>

    <h2>Trasa</h2>
    <div class="route-box">
        <table class="grid">
            <tr><td class="label" style="color:#065f46">ZAŁADUNEK:</td>
                <td class="val"><strong><?= $fmt(trim(($order->load_country ?? '') . ' ' . ($order->load_postal_code ?? '') . ' ' . ($order->load_city ?? ''))) ?></strong></td></tr>
            <?php if ($order->date_deadline): ?>
                <tr><td class="label">Data:</td><td class="val"><?= h($order->date_deadline->format('Y-m-d H:i')) ?></td></tr>
            <?php endif; ?>
            <tr><td class="label" style="color:#991b1b">ROZŁADUNEK:</td>
                <td class="val"><strong><?= $fmt(trim(($order->unload_country ?? '') . ' ' . ($order->unload_city ?? ''))) ?></strong>
                    <?= $order->unload_name ? '<br><em>' . h($order->unload_name) . '</em>' : '' ?></td></tr>
            <?php if ($order->date_delivery): ?>
                <tr><td class="label">Data:</td><td class="val"><?= h($order->date_delivery->format('Y-m-d H:i')) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <h2>Ładunek</h2>
    <table class="grid">
        <?php if ($order->title1): ?><tr><td class="label">Nr referencyjny klienta:</td><td class="val"><?= $fmt($order->title1) ?></td></tr><?php endif; ?>
        <?php if ($order->title2): ?><tr><td class="label">Opis ładunku:</td><td class="val"><?= $fmt($order->title2) ?></td></tr><?php endif; ?>
        <?php if ($order->cargo_type): ?><tr><td class="label">Typ frachtu:</td><td class="val"><?= $fmt($order->cargo_type) ?></td></tr><?php endif; ?>
        <?php if ($order->transport_type): ?><tr><td class="label">Rodzaj transportu:</td><td class="val"><?= $fmt($order->transport_type) ?></td></tr><?php endif; ?>
    </table>

    <h2>Transport</h2>
    <table class="grid">
        <?php if ($order->driver): ?><tr><td class="label">Kierowca:</td><td class="val"><?= $fmt($order->driver) ?></td></tr><?php endif; ?>
        <?php if ($order->vehicle_reg): ?><tr><td class="label">Pojazd:</td><td class="val"><?= $fmt($order->vehicle_reg) ?></td></tr><?php endif; ?>
    </table>

    <h2>Warunki finansowe</h2>
    <div class="price-box">
        <div class="amount"><?= number_format((float)$order->netto, 2, ',', ' ') ?> <?= h($order->currency) ?></div>
        <div class="small">netto &middot; <?= h($order->payment_terms ?: 'przelew') ?></div>
    </div>

    <?php if ($order->notes): ?>
        <h2>Uwagi</h2>
        <div class="notes"><?= h($order->notes) ?></div>
    <?php endif; ?>

    <div class="foot">
        Dokument wygenerowany automatycznie <?= date('Y-m-d H:i') ?>
        <?php if ($order->company_name): ?> &middot; <?= h($order->company_name) ?><?php endif; ?>
    </div>
</body>
</html>

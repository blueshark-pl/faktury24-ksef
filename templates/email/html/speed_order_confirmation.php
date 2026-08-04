<?php
/**
 * Email potwierdzenia zlecenia transportowego.
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
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.5; margin: 0; padding: 20px; background: #f5f5f5; }
        .wrap { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.05); }
        .hdr { background: linear-gradient(90deg, #0d6efd 0%, #6366f1 100%); color: #fff; padding: 24px; }
        .hdr h1 { margin: 0; font-size: 22px; }
        .hdr .sym { font-size: 14px; opacity: .85; margin-top: 4px; }
        .content { padding: 24px; }
        .section { margin-bottom: 20px; }
        .section h3 { color: #0d6efd; font-size: 14px; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: .5px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table td { padding: 6px 0; border-bottom: 1px solid #eee; vertical-align: top; }
        table td.label { color: #666; width: 40%; }
        table td.val { color: #111; font-weight: 500; }
        .route { background: #ecfdf5; padding: 16px; border-radius: 6px; border-left: 3px solid #10b981; }
        .price { background: #eef2ff; padding: 16px; border-radius: 6px; border-left: 3px solid #6366f1; font-size: 18px; font-weight: bold; color: #4338ca; text-align: right; }
        .foot { padding: 16px 24px; background: #f8fafc; color: #6b7280; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hdr">
            <h1>Potwierdzenie zlecenia transportowego</h1>
            <div class="sym"><?= h($order->symbol) ?> · <?= h($order->date_doc ? $order->date_doc->format('Y-m-d') : '') ?></div>
        </div>
        <div class="content">
            <p>Szanowni Państwo,</p>
            <p>Potwierdzamy przyjęcie zlecenia transportowego <strong><?= h($order->symbol) ?></strong>.
               Poniżej szczegóły:</p>

            <div class="section route">
                <h3>Trasa</h3>
                <table>
                    <tr><td class="label">Załadunek:</td><td class="val"><?= $fmt(trim(($order->load_country ?? '') . ' ' . ($order->load_postal_code ?? '') . ' ' . ($order->load_city ?? ''))) ?></td></tr>
                    <?php if ($order->date_deadline): ?>
                        <tr><td class="label">Data załadunku:</td><td class="val"><?= h($order->date_deadline->format('Y-m-d H:i')) ?></td></tr>
                    <?php endif; ?>
                    <tr><td class="label">Rozładunek:</td><td class="val"><?= $fmt(trim(($order->unload_country ?? '') . ' ' . ($order->unload_city ?? ''))) ?><?= $order->unload_name ? ' (' . h($order->unload_name) . ')' : '' ?></td></tr>
                    <?php if ($order->date_delivery): ?>
                        <tr><td class="label">Data rozładunku:</td><td class="val"><?= h($order->date_delivery->format('Y-m-d H:i')) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="section">
                <h3>Ładunek</h3>
                <table>
                    <?php if ($order->title2): ?><tr><td class="label">Opis:</td><td class="val"><?= $fmt($order->title2) ?></td></tr><?php endif; ?>
                    <?php if ($order->cargo_type): ?><tr><td class="label">Typ frachtu:</td><td class="val"><?= $fmt($order->cargo_type) ?></td></tr><?php endif; ?>
                    <?php if ($order->transport_type): ?><tr><td class="label">Rodzaj transportu:</td><td class="val"><?= $fmt($order->transport_type) ?></td></tr><?php endif; ?>
                    <?php if ($order->title1): ?><tr><td class="label">Nr referencyjny:</td><td class="val"><?= $fmt($order->title1) ?></td></tr><?php endif; ?>
                </table>
            </div>

            <div class="section">
                <h3>Warunki finansowe</h3>
                <div class="price">
                    <?= number_format((float)$order->netto, 2, ',', ' ') ?> <?= h($order->currency) ?>
                    <div style="font-size: 12px; font-weight: normal; color: #6b7280; margin-top: 4px">
                        netto · <?= h($order->payment_terms ?: 'przelew') ?>
                    </div>
                </div>
            </div>

            <?php if ($order->notes): ?>
            <div class="section">
                <h3>Uwagi</h3>
                <p style="white-space: pre-wrap"><?= h($order->notes) ?></p>
            </div>
            <?php endif; ?>

            <p style="margin-top: 24px">W razie pytań proszę o kontakt.</p>
            <p><strong><?= h($order->company_name ?? '') ?></strong></p>
        </div>
        <div class="foot">
            Ta wiadomość została wygenerowana automatycznie.
        </div>
    </div>
</body>
</html>

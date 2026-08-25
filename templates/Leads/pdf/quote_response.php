<?php
/**
 * FALA 20: PDF wyceny z quote_request activity.
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\LeadActivity $act
 * @var \App\Model\Entity\Lead $lead
 * @var array $shipments
 * @var float $total
 * @var string $currency
 * @var string $quoteNumber
 * @var \Cake\I18n\Date $issueDate
 * @var \Cake\I18n\Date $validUntil
 * @var array $payload
 */
$identity = $this->request->getAttribute('identity');
$authorName = trim(($identity?->get('first_name') ?? '') . ' ' . ($identity?->get('last_name') ?? ''));
$authorEmail = $identity?->get('email') ?? '';
$authorPhone = $identity?->get('phone') ?? '';

$fmtMoney = function ($v, $cur) {
    return number_format((float)$v, 2, ',', ' ') . ' ' . $cur;
};
$fmtRoute = function ($country, $postal, $city) {
    $parts = array_filter([$country, $postal, $city]);
    return implode(' ', $parts) ?: '-';
};
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 15mm 12mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; font-size: 10pt; line-height: 1.4; }
        h1 { color: #6b8f14; font-size: 22pt; margin: 0 0 4pt 0; letter-spacing: 0.3pt; }
        h2 { color: #4338ca; font-size: 11pt; margin: 12pt 0 4pt 0; text-transform: uppercase; letter-spacing: 0.5pt; border-bottom: 1px solid #d1d5db; padding-bottom: 2pt; }
        .header { border-bottom: 3px solid #94C81F; padding-bottom: 12pt; margin-bottom: 14pt; }
        .header .num { color: #6b7280; font-size: 10pt; margin-top: 4pt; }
        .header .num strong { color: #111; font-size: 12pt; }
        .two-col { width: 100%; margin: 6pt 0; }
        .two-col td { width: 50%; padding: 0 6pt; vertical-align: top; }
        .box { background: #f8fafc; padding: 8pt; border-left: 3px solid #94C81F; margin: 4pt 0; }
        .box .title { color: #6b7280; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4pt; margin-bottom: 2pt; }
        .box strong { color: #111; font-size: 10pt; }

        table.ship { width: 100%; border-collapse: collapse; margin-top: 6pt; font-size: 8.5pt; }
        table.ship thead th { background: #94C81F; color: #fff; padding: 5pt 4pt; text-align: left; font-weight: bold; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.2pt; }
        table.ship tbody td { padding: 4pt; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.ship tbody tr:nth-child(even) td { background: #f9fafb; }
        table.ship .num { text-align: right; font-weight: 600; color: #4338ca; white-space: nowrap; }
        table.ship .idx { text-align: center; color: #9ca3af; }

        .total-box { background: #eef2ff; padding: 14pt; border-left: 4px solid #4338ca; margin: 12pt 0; text-align: right; }
        .total-box .label { font-size: 9pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.4pt; }
        .total-box .amt { font-size: 22pt; font-weight: bold; color: #4338ca; margin-top: 2pt; }
        .total-box .small { font-size: 8pt; color: #6b7280; margin-top: 4pt; }

        .terms { background: #fef3c7; padding: 8pt; border-radius: 3pt; margin-top: 12pt; font-size: 9pt; }
        .terms strong { color: #b45309; }

        .signature { margin-top: 20pt; }
        .signature .name { font-weight: bold; font-size: 11pt; color: #111; }
        .signature .role { color: #6b7280; font-size: 9pt; }

        .foot { border-top: 1px solid #d1d5db; padding-top: 6pt; margin-top: 16pt; color: #6b7280; font-size: 8pt; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>WYCENA TRANSPORTU</h1>
        <div class="num">
            Numer: <strong><?= h($quoteNumber) ?></strong>
            &middot; Data wystawienia: <strong><?= h($issueDate->format('Y-m-d')) ?></strong>
            &middot; Ważna do: <strong><?= h($validUntil->format('Y-m-d')) ?></strong>
        </div>
    </div>

    <table class="two-col">
        <tr>
            <td>
                <h2>Odbiorca</h2>
                <div class="box">
                    <div class="title">Firma</div>
                    <strong><?= h($lead->company_name ?: '-') ?></strong>
                    <?php if (!empty($lead->nip)): ?><br>NIP: <?= h($lead->nip) ?><?php endif; ?>
                    <?php if (!empty($lead->postal_code) || !empty($lead->city)): ?>
                        <br><?= h(trim(($lead->postal_code ?? '') . ' ' . ($lead->city ?? ''))) ?>
                    <?php endif; ?>
                    <?php if (!empty($lead->street)): ?><br><?= h($lead->street) ?><?php endif; ?>
                    <?php if (!empty($lead->country_code)): ?><br>Kraj: <?= h($lead->country_code) ?><?php endif; ?>
                    <?php if (!empty($lead->contact_person)): ?>
                        <br><br>Kontakt: <strong><?= h($lead->contact_person) ?></strong>
                        <?php if (!empty($lead->email)): ?><br>Email: <?= h($lead->email) ?><?php endif; ?>
                        <?php if (!empty($lead->phone)): ?><br>Tel: <?= h($lead->phone) ?><?php endif; ?>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <h2>Wykonawca</h2>
                <div class="box">
                    <div class="title">Firma</div>
                    <strong>NordLogis / Booklio TMS</strong>
                    <br>Wystawił: <strong><?= h($authorName ?: 'Handel NordLogis') ?></strong>
                    <?php if ($authorEmail): ?><br>Email: <?= h($authorEmail) ?><?php endif; ?>
                    <?php if ($authorPhone): ?><br>Tel: <?= h($authorPhone) ?><?php endif; ?>
                </div>
            </td>
        </tr>
    </table>

    <h2>Zakres wyceny — <?= count($shipments) ?> zlecenie(a)</h2>
    <table class="ship">
        <thead>
            <tr>
                <th style="width: 4%;">Lp</th>
                <th style="width: 12%;">Referencja</th>
                <th style="width: 22%;">Załadunek</th>
                <th style="width: 22%;">Rozładunek</th>
                <th style="width: 10%;">Data</th>
                <th style="width: 8%;">Waga</th>
                <th style="width: 8%;">Palet</th>
                <th style="width: 14%;">Cena netto</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($shipments as $i => $s): $qp = (float)($s['_quote_price'] ?? 0); $qc = strtoupper((string)($s['_quote_currency'] ?? $currency)); ?>
            <tr>
                <td class="idx"><?= $i + 1 ?></td>
                <td><?= h((string)($s['customer_order_ref'] ?? '')) ?></td>
                <td>
                    <?= h($fmtRoute($s['from_country'] ?? '', $s['from_postal'] ?? '', $s['from_city'] ?? '')) ?>
                    <?php if (!empty($s['from_company'])): ?><br><span style="color:#6b7280; font-size:7.5pt;"><?= h($s['from_company']) ?></span><?php endif; ?>
                </td>
                <td>
                    <?= h($fmtRoute($s['to_country'] ?? '', $s['to_postal'] ?? '', $s['to_city'] ?? '')) ?>
                    <?php if (!empty($s['to_company'])): ?><br><span style="color:#6b7280; font-size:7.5pt;"><?= h($s['to_company']) ?></span><?php endif; ?>
                </td>
                <td>
                    <?= h((string)($s['load_date'] ?? '')) ?>
                    <?php if (!empty($s['unload_date'])): ?><br><span style="color:#6b7280;">→ <?= h($s['unload_date']) ?></span><?php endif; ?>
                </td>
                <td class="num"><?= !empty($s['weight_kg']) ? number_format((int)$s['weight_kg'], 0, ',', ' ') . ' kg' : '-' ?></td>
                <td class="num"><?= !empty($s['pallets']) ? (int)$s['pallets'] . ' ' . h((string)($s['pallet_type'] ?? '')) : '-' ?></td>
                <td class="num"><?= $qp > 0 ? h($fmtMoney($qp, $qc)) : '<span style="color:#dc2626;">brak</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-box">
        <div class="label">Suma netto</div>
        <div class="amt"><?= h($fmtMoney($total, $currency)) ?></div>
        <div class="small">+ podatek VAT wg stawki</div>
    </div>

    <div class="terms">
        <strong>Warunki wyceny:</strong>
        <br>&bull; Cena netto, do doliczenia podatek VAT wg obowiązującej stawki.
        <br>&bull; Płatność: 30 dni od daty wystawienia faktury (chyba że umowa stanowi inaczej).
        <br>&bull; Wycena ważna do dnia: <strong><?= h($validUntil->format('Y-m-d')) ?></strong>.
        <br>&bull; Realizacja: zgodnie z warunkami INCOTERMS ustalonymi w zleceniu.
        <br>&bull; W razie pytań lub zmian prosimy o kontakt.
    </div>

    <div class="signature">
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%; text-align: left;">
                    <div class="name">Z poważaniem,</div>
                    <div style="margin-top: 20pt;" class="name"><?= h($authorName ?: 'Handel NordLogis') ?></div>
                    <div class="role">Dział handlowy NordLogis</div>
                </td>
                <td style="width: 50%; text-align: right; color: #9ca3af; font-size: 8pt;">
                    Wygenerowano: <?= h((new \DateTime())->format('Y-m-d H:i')) ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="foot">
        Wycena wygenerowana automatycznie z systemu CRM Booklio TMS na podstawie zapytania e-mail
        <?php if (!empty($payload['from_email'])): ?>od <?= h($payload['from_email']) ?><?php endif; ?>
        &middot; Wszelkie prawa zastrzeżone
    </div>
</body>
</html>

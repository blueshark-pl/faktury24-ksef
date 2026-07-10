<?php
/**
 * @var \App\View\AppView $this
 * @var object $company
 * @var iterable $records
 * @var int $days
 */

$typeLabels = [
    'technical_inspection' => 'Badanie techniczne',
    'service'              => 'Serwis',
    'tacho_calibration'    => 'Kalibracja tachografu',
    'adr_cert'             => 'Certyfikat ADR',
    'insurance'            => 'Ubezpieczenie',
    'oc'                   => 'OC',
    'ac'                   => 'AC',
    'extinguisher'         => 'Gaśnica',
    'first_aid'            => 'Apteczka',
    'other'                => 'Inne',
];

$fmt = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d.m.Y') : (string)$v;
?>
<div style="font-family: system-ui, Arial, sans-serif; max-width: 700px; margin: 0 auto; padding: 20px;">

    <div style="background: linear-gradient(135deg, #dc2626, #f97316); color: white; padding: 24px; border-radius: 12px 12px 0 0;">
        <h1 style="margin: 0 0 8px; font-size: 22px;">⚠️ Alert: dokumenty pojazdów wygasają</h1>
        <div style="opacity: 0.9;"><?= h($company->name ?? '') ?></div>
    </div>

    <div style="background: white; padding: 24px; border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 12px 12px;">
        <p>Dzień dobry,</p>
        <p>W ciągu najbliższych <strong><?= (int)$days ?> dni</strong> wygasają następujące dokumenty pojazdów lub naczep:</p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <thead>
                <tr style="background: #f3f4f6;">
                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb;">Asset</th>
                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb;">Typ</th>
                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e5e7eb;">Wygasa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #e5e7eb;">
                            <?php if (!empty($r->vehicle)): ?>
                                🚛 <?= h($r->vehicle->name) ?> <?php if ($r->vehicle->plate): ?><em>(<?= h($r->vehicle->plate) ?>)</em><?php endif ?>
                            <?php elseif (!empty($r->trailer)): ?>
                                🚚 <?= h($r->trailer->name) ?> <?php if ($r->trailer->plate): ?><em>(<?= h($r->trailer->plate) ?>)</em><?php endif ?>
                            <?php endif ?>
                        </td>
                        <td style="padding: 10px; border-bottom: 1px solid #e5e7eb;">
                            <?= h($typeLabels[$r->maintenance_type] ?? $r->maintenance_type) ?>
                        </td>
                        <td style="padding: 10px; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #dc2626;">
                            <?= h($fmt($r->valid_until)) ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <p style="color: #6b7280; font-size: 14px; margin-top: 20px;">
            Zaplanuj wykonanie badań/wznowienie ubezpieczeń przed terminem, żeby pojazdy nie zostały wyłączone z eksploatacji.
        </p>

        <p style="text-align: center; margin-top: 24px;">
            <a href="https://faktury24.com/serwisy?filter=expiring" style="background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block;">
                Otwórz listę serwisów
            </a>
        </p>
    </div>

    <div style="text-align: center; color: #9ca3af; font-size: 12px; margin-top: 16px;">
        Automatyczny alert · faktury24.com
    </div>
</div>

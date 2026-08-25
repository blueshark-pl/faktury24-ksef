<?php
/**
 * @var array $contracts
 * @var int $daysWindow
 * @var bool $testMode
 * @var string|null $originalTo
 */
?>
<div style="font-family: system-ui, Arial, sans-serif; max-width: 620px; margin: 0 auto; padding: 20px; color: #1a1d29;">
    <?php if (!empty($testMode)): ?>
    <div style="background: #fff7ed; border: 2px solid #ea580c; padding: 10px; border-radius: 6px; margin-bottom: 12px; color: #7c2d12; font-size: 12px;">
        <strong>⚠ TRYB TESTOWY</strong> — mail miał iść do: <strong><?= h($originalTo ?? '?') ?></strong>
    </div>
    <?php endif; ?>
    <div style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; padding: 24px; border-radius: 12px 12px 0 0;">
        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9;">Booklio CRM · Kontrakty ramowe</div>
        <h1 style="margin: 8px 0 0; font-size: 22px;">
            ⏰ <?= count($contracts) ?> kontraktów wygasa w ciągu <?= (int)$daysWindow ?> dni
        </h1>
    </div>
    <div style="background: #fff; padding: 24px; border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 12px 12px;">
        <p>Poniżej lista kontraktów ramowych, których termin ważności zbliża się do końca. Czas na odnowienie/renegocjację warunków z klientem.</p>

        <table style="width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px;">
            <thead>
                <tr style="background: #f9fafb;">
                    <th style="padding: 8px; text-align: left; border-bottom: 2px solid #d1d5db;">Klient / Nazwa</th>
                    <th style="padding: 8px; text-align: left; border-bottom: 2px solid #d1d5db;">Trasa</th>
                    <th style="padding: 8px; text-align: right; border-bottom: 2px solid #d1d5db;">Cena</th>
                    <th style="padding: 8px; text-align: right; border-bottom: 2px solid #d1d5db;">Wygasa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contracts as $c):
                    $today = new \DateTimeImmutable('today');
                    $validTo = $c->valid_to ? new \DateTimeImmutable($c->valid_to->format('Y-m-d')) : null;
                    $daysLeft = $validTo ? (int)$today->diff($validTo)->days : null;
                    $color = $daysLeft < 14 ? '#dc2626' : ($daysLeft < 30 ? '#f59e0b' : '#059669');
                ?>
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #f1f3f5;">
                        <strong><?= h($c->contractor_name ?? '?') ?></strong>
                        <br><span style="color: #6b7280; font-size: 11px;"><?= h($c->name ?? '') ?></span>
                        <?php if (!empty($c->contractor_nip)): ?>
                            <br><span style="color: #9ca3af; font-size: 10px;">NIP: <?= h($c->contractor_nip) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 8px; border-bottom: 1px solid #f1f3f5; font-size: 12px;">
                        <?= h(trim(($c->from_country ?? '') . ' ' . ($c->from_city ?? ''))) ?>
                        <?php if (!empty($c->to_country) || !empty($c->to_city)): ?>
                            <br>→ <?= h(trim(($c->to_country ?? '') . ' ' . ($c->to_city ?? ''))) ?>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 8px; border-bottom: 1px solid #f1f3f5; text-align: right; font-weight: 600;">
                        <?= number_format((float)$c->price_netto, 2, ',', ' ') ?> <?= h($c->currency ?: 'PLN') ?>
                    </td>
                    <td style="padding: 8px; border-bottom: 1px solid #f1f3f5; text-align: right;">
                        <span style="background: <?= $color ?>; color: #fff; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 11px;">
                            <?= $daysLeft !== null ? $daysLeft . 'd' : '?' ?>
                        </span>
                        <br><span style="color: #6b7280; font-size: 10px;"><?= $validTo ? $validTo->format('Y-m-d') : '?' ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px; padding: 12px; background: #f0f9ff; border-left: 3px solid #0891b2; border-radius: 4px; font-size: 12px;">
            <strong>💡 Sugerowana akcja:</strong> Zadzwoń do klientów z najbliższą datą wygaśnięcia i uzgodnij warunki odnowienia. Odnowienie kontraktu ramowego zabezpiecza wolumen na kolejne miesiące i chroni marżę.
        </div>

        <p style="margin-top: 20px; text-align: center;">
            <a href="https://booklio.pl/kontrakty" style="background: #94C81F; color: #fff; padding: 10px 24px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                Otwórz listę kontraktów
            </a>
        </p>

        <div style="margin-top: 24px; padding-top: 14px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #9ca3af;">
            To jest automatyczne powiadomienie (cron: `bin/cake crm_contract_renewals`). Aby wyłączyć: usuń cron job lub ustaw <code>--dry</code>.
        </div>
    </div>
</div>

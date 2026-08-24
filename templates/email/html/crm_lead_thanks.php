<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Lead $lead
 */
$contactName = trim((string)($lead->contact_person ?? '')) ?: '';
?>
<div style="font-family: system-ui, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #1a1d29;">

    <div style="background: linear-gradient(135deg, #94C81F, #6b8f14); color: white; padding: 30px; border-radius: 12px 12px 0 0;">
        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.85;">Booklio TMS</div>
        <h1 style="margin: 8px 0 0; font-size: 24px;">Dziękujemy za zaufanie 🙏</h1>
    </div>

    <div style="background: #fff; padding: 28px; border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 12px 12px;">
        <?php if ($contactName): ?>
            <p>Szanowny/a <strong><?= h($contactName) ?></strong>,</p>
        <?php else: ?>
            <p>Dzień dobry,</p>
        <?php endif; ?>

        <p>bardzo dziękujemy za powierzenie nam zlecenia transportowego – to dla nas ogromna wartość
           móc obsługiwać firmę <strong><?= h($lead->company_name) ?></strong>.</p>

        <p>Nasz zespół rozpoczyna właśnie realizację zamówienia. Wkrótce otrzymają Państwo
           potwierdzenie zlecenia z numerem CMR oraz danymi kierowcy.</p>

        <?php if ($lead->assigned_user ?? null): ?>
            <?php $ownerName = trim(($lead->assigned_user->first_name ?? '') . ' ' . ($lead->assigned_user->last_name ?? '')); ?>
            <div style="background: #f9fafb; border-left: 4px solid #94C81F; padding: 14px 16px; margin: 20px 0; border-radius: 6px;">
                <div style="font-weight: 600; margin-bottom: 4px;">Państwa opiekun handlowy:</div>
                <div><?= h($ownerName) ?></div>
                <?php if (!empty($lead->assigned_user->email)): ?>
                    <div><a href="mailto:<?= h($lead->assigned_user->email) ?>" style="color: #6b8f14;"><?= h($lead->assigned_user->email) ?></a></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p>W razie jakichkolwiek pytań – jesteśmy do Państwa dyspozycji.</p>

        <p style="margin-top: 32px;">Z pozdrowieniami,<br>
            <strong>Zespół Booklio TMS</strong>
        </p>

        <div style="margin-top: 32px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #9ca3af;">
            To jest automatyczne powiadomienie wysyłane po zaakceptowaniu zlecenia w systemie CRM.
            Jeśli otrzymali Państwo tę wiadomość omyłkowo, prosimy o kontakt zwrotny.
        </div>
    </div>
</div>

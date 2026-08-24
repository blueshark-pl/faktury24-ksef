<?php
/**
 * @var \App\View\AppView $this
 * @var string $userName
 * @var array $overdue    array of ['type' => 'task'|'followup', 'item' => Entity]
 * @var array $todayList
 * @var array $upcoming
 * @var array $stale     array of Lead entities - leady bez aktywnosci
 * @var int $staleDays
 * @var int $days
 * @var string $baseUrl
 */
$stale = $stale ?? [];
$staleDays = $staleDays ?? 14;

$renderRow = function($group, $baseUrl): string {
    $out = '';
    foreach ($group as $g) {
        $item = $g['item'];
        if ($g['type'] === 'task') {
            $due = $item->due_at;
            $leadId = $item->lead->id ?? '';
            $leadName = $item->lead->company_name ?? '?';
            $stage = $item->lead->stage ?? '?';
            $subject = $item->subject ?: '(bez tytułu)';
            $body = $item->body ? mb_substr($item->body, 0, 200) : '';
        } else {
            $due = $item->next_action_at;
            $leadId = $item->id;
            $leadName = $item->company_name;
            $stage = $item->stage;
            $subject = $item->next_action_description ?: '(follow-up)';
            $body = '';
        }
        $viewUrl = $baseUrl . '/crm/view/' . h($leadId);
        $out .= '<tr style="border-bottom: 1px solid #f1f3f5;">'
            . '<td style="padding: 10px 8px; vertical-align: top; white-space: nowrap; color: #6b7280; font-size: 13px;">'
            . h($due->format('d.m H:i')) . '</td>'
            . '<td style="padding: 10px 8px;">'
            . '<a href="' . h($viewUrl) . '" style="font-weight: 600; color: #1a1d29; text-decoration: none;">'
            . h($leadName) . '</a>'
            . ' <span style="background: #f3f4f6; padding: 1px 6px; border-radius: 4px; font-size: 11px; color: #4b5563; margin-left: 4px;">'
            . h($stage) . '</span>'
            . '<div style="font-size: 13px; color: #374151; margin-top: 4px;">' . h($subject) . '</div>'
            . ($body ? '<div style="font-size: 12px; color: #6b7280; margin-top: 2px;">' . h($body) . '</div>' : '')
            . '</td>'
            . '<td style="padding: 10px 8px; vertical-align: top; text-align: right;">'
            . '<a href="' . h($viewUrl) . '" style="background: #94C81F; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600;">Otwórz</a>'
            . '</td>'
            . '</tr>';
    }
    return $out;
};
?>
<div style="font-family: system-ui, Arial, sans-serif; max-width: 640px; margin: 0 auto; padding: 20px; color: #1a1d29;">

    <div style="background: linear-gradient(135deg, #94C81F, #6b8f14); color: white; padding: 26px; border-radius: 12px 12px 0 0;">
        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.85;">CRM Booklio · <?= h((new \DateTimeImmutable('today'))->format('l, d.m.Y')) ?></div>
        <h1 style="margin: 6px 0 0; font-size: 22px;">Dzień dobry <?= h($userName) ?> 👋</h1>
        <div style="opacity: 0.9; margin-top: 6px;">
            Masz <?= count($overdue) + count($todayList) ?> zadań do zrobienia dziś
            + <?= count($upcoming) ?> zaplanowanych do <?= (int)$days ?> dni.
        </div>
    </div>

    <div style="background: #fff; padding: 24px; border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 12px 12px;">

        <?php if (!empty($overdue)): ?>
            <div style="background: #fee2e2; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                <div style="font-weight: 700; color: #b91c1c; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                    ⚠ Przeterminowane (<?= count($overdue) ?>)
                </div>
            </div>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                <?= $renderRow($overdue, $baseUrl) ?>
            </table>
        <?php endif; ?>

        <?php if (!empty($todayList)): ?>
            <div style="background: #fef3c7; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                <div style="font-weight: 700; color: #b45309; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                    ⏰ Dzisiaj (<?= count($todayList) ?>)
                </div>
            </div>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                <?= $renderRow($todayList, $baseUrl) ?>
            </table>
        <?php endif; ?>

        <?php if (!empty($upcoming)): ?>
            <div style="background: #dbeafe; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                <div style="font-weight: 700; color: #1e40af; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                    📅 Do <?= (int)$days ?> dni (<?= count($upcoming) ?>)
                </div>
            </div>
            <table style="width: 100%; border-collapse: collapse;">
                <?= $renderRow($upcoming, $baseUrl) ?>
            </table>
        <?php endif; ?>

        <?php if (!empty($stale)): ?>
            <div style="background: #f3f4f6; padding: 12px 16px; border-radius: 8px; margin-top: 24px; margin-bottom: 16px;">
                <div style="font-weight: 700; color: #4b5563; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                    💤 Zapomniane leady - brak aktywności ponad <?= (int)$staleDays ?> dni (<?= count($stale) ?>)
                </div>
            </div>
            <table style="width: 100%; border-collapse: collapse;">
                <?php foreach ($stale as $sl):
                    $viewUrl = $baseUrl . '/crm/view/' . h($sl->id);
                    $lastAt = $sl->last_contacted_at
                        ? $sl->last_contacted_at->format('d.m.Y')
                        : 'nigdy';
                ?>
                    <tr style="border-bottom: 1px solid #f1f3f5;">
                        <td style="padding: 10px 8px; vertical-align: top; white-space: nowrap; color: #9ca3af; font-size: 12px;">
                            <?= h($lastAt) ?>
                        </td>
                        <td style="padding: 10px 8px;">
                            <a href="<?= h($viewUrl) ?>" style="font-weight: 600; color: #1a1d29; text-decoration: none;">
                                <?= h($sl->company_name) ?>
                            </a>
                            <span style="background: #f3f4f6; padding: 1px 6px; border-radius: 4px; font-size: 11px; color: #4b5563; margin-left: 4px;">
                                <?= h($sl->stage) ?>
                            </span>
                            <?php if ($sl->contact_person): ?>
                                <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                                    <?= h($sl->contact_person) ?><?php if ($sl->phone): ?> · <?= h($sl->phone) ?><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px 8px; vertical-align: top; text-align: right;">
                            <a href="<?= h($viewUrl) ?>" style="background: #6b7280; color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600;">Otwórz</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <div style="margin-top: 32px; text-align: center;">
            <a href="<?= h($baseUrl . '/crm/zadania') ?>"
               style="background: #94C81F; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; display: inline-block;">
                Otwórz „Moje zadania" w CRM
            </a>
        </div>

        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center;">
            Ten email został wygenerowany automatycznie przez cron <code>bin/cake crm_tasks_digest</code>.
        </div>
    </div>
</div>

<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\SupportTicket $ticket
 * @var string $userEmail
 */
$categories = \App\Model\Table\SupportTicketsTable::CATEGORIES;
$viewUrl = \Cake\Routing\Router::url(['controller' => 'Invoices', 'action' => 'adminSupportView', $ticket->id], true);
?>
<!DOCTYPE html>
<html lang="pl">
<head><meta charset="UTF-8"><title>Nowe zgłoszenie</title></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333;background:#f5f5f5;margin:0;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
  <div style="background:#4f46e5;padding:24px 32px;color:#fff">
    <h2 style="margin:0;font-size:20px">Nowe zgłoszenie support #<?= $ticket->id ?></h2>
  </div>
  <div style="padding:28px 32px">
    <p>Otrzymano nowe zgłoszenie od użytkownika <strong><?= h($userEmail) ?></strong>.</p>
    <table style="width:100%;border-collapse:collapse;margin:16px 0">
      <tr><td style="padding:8px 0;color:#666;width:120px">Tytuł</td><td style="padding:8px 0;font-weight:bold"><?= h($ticket->title) ?></td></tr>
      <tr style="background:#f9f9f9"><td style="padding:8px 4px;color:#666">Kategoria</td><td style="padding:8px 4px"><?= h($categories[$ticket->category] ?? $ticket->category) ?></td></tr>
      <tr><td style="padding:8px 0;color:#666">Data</td><td style="padding:8px 0"><?= h(date('Y-m-d H:i', strtotime((string)$ticket->created))) ?></td></tr>
    </table>
    <div style="background:#f9f9f9;border-left:4px solid #4f46e5;padding:12px 16px;margin:16px 0;border-radius:0 4px 4px 0">
      <p style="margin:0;white-space:pre-wrap"><?= h($ticket->description) ?></p>
    </div>
    <div style="margin-top:24px;text-align:center">
      <a href="<?= h($viewUrl) ?>" style="display:inline-block;background:#4f46e5;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold">Przejdź do zgłoszenia</a>
    </div>
  </div>
  <div style="padding:16px 32px;background:#f5f5f5;text-align:center;color:#999;font-size:12px">
    Faktury24 — system zgłoszeń
  </div>
</div>
</body>
</html>

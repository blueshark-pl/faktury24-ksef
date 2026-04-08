<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\SupportTicket $ticket
 * @var \App\Model\Entity\SupportTicketReply $reply
 */
$viewUrl = \Cake\Routing\Router::url(['controller' => 'SupportTickets', 'action' => 'view', $ticket->id], true);
?>
<!DOCTYPE html>
<html lang="pl">
<head><meta charset="UTF-8"><title>Odpowiedź na zgłoszenie</title></head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#333;background:#f5f5f5;margin:0;padding:20px">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
  <div style="background:#4f46e5;padding:24px 32px;color:#fff">
    <h2 style="margin:0;font-size:20px">Odpowiedź na Twoje zgłoszenie #<?= $ticket->id ?></h2>
  </div>
  <div style="padding:28px 32px">
    <p>Otrzymałeś(-aś) odpowiedź na zgłoszenie: <strong><?= h($ticket->title) ?></strong></p>
    <div style="background:#eef2ff;border-left:4px solid #4f46e5;padding:12px 16px;margin:16px 0;border-radius:0 4px 4px 0">
      <p style="margin:0 0 6px;font-size:12px;color:#666">Administrator napisał(a):</p>
      <p style="margin:0;white-space:pre-wrap"><?= h($reply->message) ?></p>
    </div>
    <div style="margin-top:24px;text-align:center">
      <a href="<?= h($viewUrl) ?>" style="display:inline-block;background:#4f46e5;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold">Zobacz zgłoszenie</a>
    </div>
  </div>
  <div style="padding:16px 32px;background:#f5f5f5;text-align:center;color:#999;font-size:12px">
    Faktury24 — system zgłoszeń
  </div>
</div>
</body>
</html>

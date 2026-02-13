<?php
/**
 * Email: reset password (HTML)
 * @var string $activationUrl
 * @var string|null $first_name
 */

use Cake\Core\Configure;
use Cake\Routing\Router;

$appName = trim((string)(Configure::read('App.name') ?? ''));
if ($appName === '') {
  $appName = 'Faktury24';
}
$primary = '#94c81f';
$primaryLink = '#6a8f00';
$firstName = (string)($first_name ?? '');
$greeting = $firstName !== '' ? 'Cześć ' . $firstName . ',' : 'Cześć,';
$link = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;

$this->assign('preheader', 'Link do ustawienia nowego hasła do Twojego konta.');
?>

<div style="font-size:14px; line-height:1.6; color:#0f172a;">
  <div style="font-size:18px; font-weight:700; margin:0 0 8px;">Ustaw nowe hasło</div>
  <p style="margin:0 0 14px;"><?= h($greeting) ?></p>
  <p style="margin:0 0 16px; color:#334155;">Otrzymaliśmy prośbę o reset hasła do Twojego konta w <?= h($appName) ?>. Jeśli to była Twoja prośba, kliknij poniżej, aby ustawić nowe hasło.</p>

  <div style="margin: 18px 0 18px;">
    <?= $this->element('email/cta_button', [
      'url' => $link,
      'label' => 'Ustaw nowe hasło',
      'bg' => $primary,
      'textColor' => '#ffffff',
        'radius' => 4,
    ]) ?>
  </div>

  <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px; margin: 0 0 16px;">
    <div style="font-weight:800; font-size:13px; margin:0 0 6px;">Co dalej</div>
    <div style="font-size:13px; color:#334155;">
      1) Otwórz link z przycisku • 2) Ustaw nowe hasło • 3) Zaloguj się ponownie.
    </div>
  </div>

  <p style="margin:0 0 10px; color:#334155;">Jeśli przycisk nie działa, użyj linku:</p>
  <p style="margin:0 0 0; word-break:break-all;">
    <a href="<?= h($link) ?>" style="color:<?= h($primaryLink) ?>; text-decoration:underline;"><?= h($link) ?></a>
  </p>

  <hr style="border:none; border-top:1px solid #e2e8f0; margin:18px 0;">

  <p style="margin:0 0 8px; font-size:12px; color:#64748b;">Jeśli nie prosiłeś o zmianę hasła, zignoruj tę wiadomość — Twoje konto pozostanie bez zmian.</p>
  <p style="margin:0; font-size:12px; color:#64748b;">Wskazówka bezpieczeństwa: nigdy nie udostępniaj tego linku ani kodów logowania osobom trzecim.</p>
</div>

<?php
/**
 * Email: social account validation (HTML)
 * @var \Cake\Datasource\EntityInterface $user
 * @var \Cake\Datasource\EntityInterface $socialAccount
 * @var array|string $activationUrl
 */

use Cake\Core\Configure;
use Cake\Routing\Router;

$appName = (string)(Configure::read('App.name') ?? '');
$primary = '#94c81f';
$primaryLink = '#6a8f00';
$firstName = (string)($user->get('first_name') ?? '');
$greeting = $firstName !== '' ? 'Cześć ' . $firstName . ',' : 'Cześć,';
$link = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;
$provider = (string)($socialAccount->get('provider') ?? '');

$this->assign('preheader', 'Potwierdź logowanie kontem społecznościowym i kontynuuj.');
?>

<div style="font-size:14px; line-height:1.6; color:#0f172a;">
  <div style="font-size:18px; font-weight:700; margin:0 0 8px;">Potwierdź konto społecznościowe</div>
  <p style="margin:0 0 14px;"><?= h($greeting) ?></p>
  <p style="margin:0 0 10px; color:#334155;">Otrzymaliśmy prośbę o potwierdzenie logowania kontem społecznościowym<?= $provider !== '' ? ' (' . h($provider) . ')' : '' ?> w <?= h($appName) ?>.</p>
  <p style="margin:0 0 16px; color:#334155;">Jeśli to Ty, kliknij poniżej, aby dokończyć logowanie.</p>

  <div style="margin: 18px 0 18px;">
    <?= $this->element('email/cta_button', [
      'url' => $link,
      'label' => 'Potwierdź i kontynuuj',
      'bg' => $primary,
      'textColor' => '#ffffff',
        'radius' => 4,
    ]) ?>
  </div>

  <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px; margin: 0 0 16px;">
    <div style="font-weight:800; font-size:13px; margin:0 0 6px;">Dlaczego prosimy o potwierdzenie?</div>
    <div style="font-size:13px; color:#334155;">
      To dodatkowy krok bezpieczeństwa — chroni konto przed nieautoryzowanym dostępem.
    </div>
  </div>

  <p style="margin:0 0 10px; color:#334155;">Jeśli przycisk nie działa, użyj linku:</p>
  <p style="margin:0 0 0; word-break:break-all;">
    <a href="<?= h($link) ?>" style="color:<?= h($primaryLink) ?>; text-decoration:underline;"><?= h($link) ?></a>
  </p>

  <hr style="border:none; border-top:1px solid #e2e8f0; margin:18px 0;">

  <p style="margin:0 0 8px; font-size:12px; color:#64748b;">Jeśli to nie Ty próbowałeś się zalogować, zignoruj tę wiadomość.</p>
  <p style="margin:0; font-size:12px; color:#64748b;">Wskazówka bezpieczeństwa: nie udostępniaj linków logowania — są jednorazowe i przypisane do konta.</p>
</div>

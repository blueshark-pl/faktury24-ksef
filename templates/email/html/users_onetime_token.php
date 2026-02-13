<?php
/**
 * Email: one-time token (HTML)
 * @var \Cake\Datasource\EntityInterface $user
 * @var string $loginLink
 * @var string $token
 */

use Cake\Core\Configure;

$appName = (string)(Configure::read('App.name') ?? '');
$primary = '#94c81f';
$primaryLink = '#6a8f00';
$firstName = (string)($user->get('first_name') ?? '');
$greeting = $firstName !== '' ? 'Cześć ' . $firstName . ',' : 'Cześć,';

$this->assign('preheader', 'Jednorazowy link do logowania — bez hasła.');
?>

<div style="font-size:14px; line-height:1.6; color:#0f172a;">
  <div style="font-size:18px; font-weight:700; margin:0 0 8px;">Jednorazowe logowanie</div>
  <p style="margin:0 0 14px;"><?= h($greeting) ?></p>
  <p style="margin:0 0 10px; color:#334155;">Oto jednorazowy link do logowania do <?= h($appName) ?> — bez podawania hasła.</p>
  <p style="margin:0 0 16px; color:#334155;">Kliknij przycisk poniżej, aby przejść bezpośrednio do panelu (link jest jednorazowy).</p>

  <div style="margin: 18px 0 18px;">
    <?= $this->element('email/cta_button', [
      'url' => $loginLink,
      'label' => 'Zaloguj się',
      'bg' => $primary,
      'textColor' => '#ffffff',
        'radius' => 4,
    ]) ?>
  </div>

  <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px; margin: 0 0 16px;">
    <div style="font-weight:800; font-size:13px; margin:0 0 6px;">Bezpieczeństwo</div>
    <div style="font-size:13px; color:#334155;">
      Ten link działa tylko raz i jest przypisany do Twojego konta. Jeśli nie prosiłeś o link logowania, zignoruj tę wiadomość.
    </div>
  </div>

  <p style="margin:0 0 10px; color:#334155;">Jeśli przycisk nie działa, użyj linku:</p>
  <p style="margin:0 0 0; word-break:break-all;">
    <a href="<?= h($loginLink) ?>" style="color:<?= h($primaryLink) ?>; text-decoration:underline;"><?= h($loginLink) ?></a>
  </p>

  <hr style="border:none; border-top:1px solid #e2e8f0; margin:18px 0;">

  <p style="margin:0; font-size:12px; color:#64748b;">Jeśli to nie Ty prosiłeś o link logowania, zignoruj tę wiadomość.</p>
</div>

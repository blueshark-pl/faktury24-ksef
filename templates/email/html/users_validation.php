<?php
/**
 * Email: validation (HTML)
 * @var string $activationUrl
 * @var string|null $first_name
 */

use Cake\Core\Configure;
use Cake\Routing\Router;

$appName = (string)(Configure::read('App.name') ?? '');
$primary = '#94c81f';
$primaryLink = '#6a8f00';
$firstName = (string)($first_name ?? '');
$greeting = $firstName !== '' ? 'Cześć ' . $firstName . ',' : 'Cześć,';
$link = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;

$this->assign('preheader', 'Potwierdź rejestrację i aktywuj konto.');
?>

<div style="font-size:14px; line-height:1.6; color:#0f172a;">
  <div style="font-size:18px; font-weight:700; margin:0 0 8px;">Potwierdź rejestrację</div>
  <p style="margin:0 0 14px;"><?= h($greeting) ?></p>
  <p style="margin:0 0 10px; color:#334155;">Dziękujemy za rejestrację w <?= h($appName) ?>.</p>
  <p style="margin:0 0 16px; color:#334155;">Aby dokończyć rejestrację i aktywować konto, kliknij poniższy przycisk:</p>

  <div style="margin: 18px 0 18px;">
  <div style="margin: 18px 0 18px;">
    <?= $this->element('email/cta_button', [
      'url' => $link,
      'label' => 'Aktywuj konto',
      'bg' => $primary,
      'textColor' => '#ffffff',
         'radius' => 4,
    ]) ?>
  </div>

  <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px; margin: 0 0 16px;">
    <div style="font-weight:800; font-size:13px; margin:0 0 6px;">Po aktywacji</div>
    <div style="font-size:13px; color:#334155;">
      Zyskasz dostęp do panelu i funkcji aplikacji — m.in. wystawiania dokumentów, danych kontrahentów i historii operacji.
    </div>
  </div>

  <p style="margin:0 0 10px; color:#334155;">Jeśli przycisk nie działa, użyj linku:</p>
  <p style="margin:0 0 0; word-break:break-all;">
    <a href="<?= h($link) ?>" style="color:<?= h($primaryLink) ?>; text-decoration:underline;"><?= h($link) ?></a>
  </p>

  <hr style="border:none; border-top:1px solid #e2e8f0; margin:18px 0;">

  <p style="margin:0 0 8px; font-size:12px; color:#64748b;">Jeśli to nie Ty zakładałeś konto, zignoruj tę wiadomość.</p>
  <p style="margin:0; font-size:12px; color:#64748b;">Wskazówka bezpieczeństwa: aktywacja działa tylko przez unikalny link — nie udostępniaj go osobom trzecim.</p>
</div>

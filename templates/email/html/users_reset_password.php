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
  $appName = 'Booklio TMS';
}
$brand     = '#1b5998';
$brandDark = '#13406d';
$firstName = (string)($first_name ?? '');
$greeting  = $firstName !== '' ? __('Cześć {0},', $firstName) : __('Cześć,');
$link      = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;

$this->assign('preheader', __('Link do ustawienia nowego hasła do Twojego konta.'));
?>

<div style="font-size:15px; line-height:1.65; color:#0f172a;">

  <!-- Ikona -->
  <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 18px;">
    <tr>
      <td style="width:48px; height:48px; background:rgba(27,89,152,.10); border-radius:14px; text-align:center; vertical-align:middle;">
        <span style="display:inline-block; font-size:22px; line-height:48px; color:<?= h($brand) ?>;">&#128274;</span>
      </td>
    </tr>
  </table>

  <h1 style="margin:0 0 8px; font-size:22px; font-weight:700; color:#0f172a; line-height:1.3;"><?= __('Ustaw nowe hasło') ?></h1>
  <p style="margin:0 0 16px; font-size:14px; color:#475569;"><?= h($greeting) ?></p>
  <p style="margin:0 0 20px; color:#334155;">
    <?= __('Otrzymaliśmy prośbę o reset hasła do Twojego konta w {0}.', '<strong>' . h($appName) . '</strong>') ?>
    <?= __('Jeśli to była Twoja prośba, kliknij przycisk poniżej, aby ustawić nowe hasło.') ?>
  </p>

  <div style="margin: 24px 0;">
    <?= $this->element('email/cta_button', [
      'url' => $link,
      'label' => __('Ustaw nowe hasło'),
      'bg' => $brand,
      'textColor' => '#ffffff',
      'radius' => 8,
    ]) ?>
  </div>

  <!-- Info: co dalej -->
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:8px 0 20px;">
    <tr>
      <td style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px 18px;">
        <div style="font-weight:700; font-size:13px; color:#0f172a; margin:0 0 8px; text-transform:uppercase; letter-spacing:.4px;"><?= __('Co dalej?') ?></div>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
          <tr>
            <td style="vertical-align:top; padding:2px 8px 2px 0; font-weight:700; color:<?= h($brand) ?>; font-size:13px;">1.</td>
            <td style="font-size:13px; color:#334155;"><?= __('Otwórz link z przycisku powyżej') ?></td>
          </tr>
          <tr>
            <td style="vertical-align:top; padding:2px 8px 2px 0; font-weight:700; color:<?= h($brand) ?>; font-size:13px;">2.</td>
            <td style="font-size:13px; color:#334155;"><?= __('Ustaw nowe hasło (min. 8 znaków)') ?></td>
          </tr>
          <tr>
            <td style="vertical-align:top; padding:2px 8px 2px 0; font-weight:700; color:<?= h($brand) ?>; font-size:13px;">3.</td>
            <td style="font-size:13px; color:#334155;"><?= __('Zaloguj się ponownie') ?></td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- Fallback link -->
  <p style="margin:18px 0 6px; font-size:12px; color:#64748b;"><?= __('Jeśli przycisk nie działa, skopiuj poniższy link do przeglądarki:') ?></p>
  <p style="margin:0 0 0; word-break:break-all; font-size:12px;">
    <a href="<?= h($link) ?>" style="color:<?= h($brand) ?>; text-decoration:underline;"><?= h($link) ?></a>
  </p>

  <hr style="border:none; border-top:1px solid #e2e8f0; margin:24px 0 16px;">

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
      <td style="vertical-align:top; padding-right:10px; width:28px;">
        <span style="display:inline-block; width:24px; height:24px; line-height:24px; text-align:center; background:#fef3c7; color:#b45309; border-radius:50%; font-size:13px; font-weight:700;">!</span>
      </td>
      <td>
        <div style="font-size:12px; color:#64748b;">
          <?= __('Jeśli nie prosiłeś o zmianę hasła — zignoruj tę wiadomość. Twoje konto pozostanie bez zmian.') ?>
          <?= __('Nigdy nie udostępniaj tego linku ani danych logowania osobom trzecim.') ?>
        </div>
      </td>
    </tr>
  </table>
</div>

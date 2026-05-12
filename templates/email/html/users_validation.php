<?php
/**
 * Email: validation (HTML) — potwierdzenie rejestracji.
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
$firstName = (string)($first_name ?? '');
$greeting  = $firstName !== '' ? 'Cześć ' . $firstName . ',' : 'Cześć,';
$link      = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;

$this->assign('preheader', 'Potwierdź rejestrację i aktywuj konto w ' . $appName . '.');
?>

<div style="font-size:15px; line-height:1.65; color:#0f172a;">

  <!-- Ikona -->
  <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 18px;">
    <tr>
      <td style="width:48px; height:48px; background:rgba(27,89,152,.10); border-radius:14px; text-align:center; vertical-align:middle;">
        <span style="display:inline-block; font-size:22px; line-height:48px; color:<?= h($brand) ?>;">&#9989;</span>
      </td>
    </tr>
  </table>

  <h1 style="margin:0 0 8px; font-size:22px; font-weight:700; color:#0f172a; line-height:1.3;">Aktywuj konto</h1>
  <p style="margin:0 0 16px; font-size:14px; color:#475569;"><?= h($greeting) ?></p>
  <p style="margin:0 0 20px; color:#334155;">
    Dziękujemy za rejestrację w <strong><?= h($appName) ?></strong>.
    Aby dokończyć proces i aktywować konto, kliknij przycisk poniżej.
  </p>

  <div style="margin: 24px 0;">
    <?= $this->element('email/cta_button', [
      'url' => $link,
      'label' => 'Aktywuj konto',
      'bg' => $brand,
      'textColor' => '#ffffff',
      'radius' => 8,
    ]) ?>
  </div>

  <!-- Info: po aktywacji -->
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:8px 0 20px;">
    <tr>
      <td style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:14px 18px;">
        <div style="font-weight:700; font-size:13px; color:#0f172a; margin:0 0 8px; text-transform:uppercase; letter-spacing:.4px;">Po aktywacji</div>
        <div style="font-size:13px; color:#334155;">
          Zyskasz dostęp do panelu Booklio TMS — zleceń transportowych, kontrahentów, faktur i pełnej historii operacji.
        </div>
      </td>
    </tr>
  </table>

  <!-- Fallback link -->
  <p style="margin:18px 0 6px; font-size:12px; color:#64748b;">Jeśli przycisk nie działa, skopiuj poniższy link do przeglądarki:</p>
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
          Jeśli to nie Ty zakładałeś konto — zignoruj tę wiadomość.
          Link aktywacyjny jest unikalny — nie udostępniaj go osobom trzecim.
        </div>
      </td>
    </tr>
  </table>
</div>

<?php
/**
 * Email HTML layout (Users)
 * Plik: templates/layout/email/html/users.php
 *
 * @var \Cake\View\View $this
 * @var string $content
 */

use Cake\Core\Configure;

$appName = trim((string)(Configure::read('App.name') ?? ''));
if ($appName === '') {
  $appName = 'Booklio TMS';
}
$brand     = '#1b5998';     // Booklio primary blue
$brandDark = '#13406d';     // hover/darker
$preheader = (string)$this->fetch('preheader');
$year      = (int)date('Y');
$logoUrl     = trim((string)(Configure::read('App.emailLogoUrl') ?? ''));
$logoDataUri = trim((string)(Configure::read('App.emailLogoDataUri') ?? ''));
$brandSite   = 'booklio.pl';
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="color-scheme" content="light dark">
  <meta name="supported-color-schemes" content="light dark">
  <title><?= h($appName) ?></title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; color:#0f172a;">

  <?php if ($preheader !== ''): ?>
    <div style="display:none; font-size:1px; color:#f1f5f9; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
      <?= h($preheader) ?>
    </div>
  <?php endif; ?>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f1f5f9" style="background:#f1f5f9;">
    <tr>
      <td align="center" style="padding: 28px 12px 36px;">

        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px; max-width:100%;">

          <!-- Logo / brand header -->
          <tr>
            <td align="center" style="padding: 4px 0 22px;">
              <?php if ($logoDataUri !== ''): ?>
                <img src="<?= h($logoDataUri) ?>" width="140" alt="<?= h($appName) ?>" style="display:block; border:0; outline:none; text-decoration:none; width:140px; max-width:60%; height:auto;">
              <?php elseif ($logoUrl !== ''): ?>
                <img src="<?= h($logoUrl) ?>" width="140" alt="<?= h($appName) ?>" style="display:block; border:0; outline:none; text-decoration:none; width:140px; max-width:60%; height:auto;">
              <?php else: ?>
                <!-- Placeholder z literą B na okrągłym tle, jeśli logo nie skonfigurowane -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                  <tr>
                    <td style="width:52px; height:52px; background:<?= h($brand) ?>; border-radius:14px; text-align:center; vertical-align:middle;">
                      <span style="display:inline-block; font-family:'Segoe UI',Arial,sans-serif; font-size:22px; font-weight:900; color:#ffffff; line-height:52px;">B</span>
                    </td>
                    <td style="width:8px;"></td>
                    <td style="vertical-align:middle; text-align:left;">
                      <div style="font-family:'Segoe UI',Arial,sans-serif; font-size:18px; font-weight:700; color:#0f172a; line-height:1.1;"><?= h($appName) ?></div>
                      <div style="font-family:'Segoe UI',Arial,sans-serif; font-size:11px; color:#64748b; letter-spacing:.5px; text-transform:uppercase;">Transport Management System</div>
                    </td>
                  </tr>
                </table>
              <?php endif; ?>
            </td>
          </tr>

          <!-- Card -->
          <tr>
            <td style="background:#ffffff; border-radius:16px; box-shadow:0 4px 24px rgba(15,23,42,.06);">
              <!-- Akcent (cienki pasek u góry) -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td style="height:4px; background:<?= h($brand) ?>; line-height:4px; font-size:0; border-top-left-radius:16px; border-top-right-radius:16px;">&nbsp;</td>
                </tr>
                <tr>
                  <td style="padding: 32px 32px 12px;">
                    <?= $this->fetch('content') ?: ($content ?? '') ?>
                  </td>
                </tr>
                <tr>
                  <td style="padding: 0 32px;">
                    <div style="border-top: 1px solid #eef2f7; height: 1px; line-height: 1px; font-size: 0;">&nbsp;</div>
                  </td>
                </tr>
                <tr>
                  <td style="padding: 14px 32px 24px; font-size: 12px; color: #94a3b8; line-height: 1.6;">
                    Ta wiadomość została wysłana automatycznie. Prosimy na nią nie odpowiadać.
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td align="center" style="padding: 22px 0 0;">
              <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; font-size:12px; color:#94a3b8; line-height:1.7;">
                <div>© <?= $year ?> <strong style="color:#64748b;"><?= h($appName) ?></strong> — system zarządzania transportem</div>
                <div style="margin-top:6px;">
                  <a href="https://<?= h($brandSite) ?>" style="color:<?= h($brand) ?>; text-decoration:none; font-weight:600;"><?= h($brandSite) ?></a>
                </div>
              </div>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>

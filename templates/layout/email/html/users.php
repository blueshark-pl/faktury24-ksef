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
  $appName = 'Faktury24';
}
$primary = '#94c81f';
$preheader = (string)$this->fetch('preheader');
$year = (int)date('Y');
$logoUrl = trim((string)(Configure::read('App.emailLogoUrl') ?? ''));
$logoDataUri = trim((string)(Configure::read('App.emailLogoDataUri') ?? ''));
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <title><?= h($appName) ?></title>
</head>
<body style="margin:0; padding:0; background:#ffffff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; color:#111827;">

  <?php if ($preheader !== ''): ?>
    <div style="display:none; font-size:1px; color:#ffffff; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
      <?= h($preheader) ?>
    </div>
  <?php endif; ?>

  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#ffffff" style="background:#ffffff;">
    <tr>
      <td align="center" style="padding: 22px 12px 32px;">

        <table role="presentation" width="620" cellspacing="0" cellpadding="0" border="0" style="width:620px; max-width:100%;">

          <tr>
            <td align="center" style="padding: 6px 0 14px;">
              <?php if ($logoDataUri !== ''): ?>
                <img src="<?= h($logoDataUri) ?>" width="80" alt="<?= h($appName) ?>" style="display:block; border:0; outline:none; text-decoration:none; width:80px; max-width:100%; height:auto;">
              <?php elseif ($logoUrl !== ''): ?>
                <img src="<?= h($logoUrl) ?>" width="80" alt="<?= h($appName) ?>" style="display:block; border:0; outline:none; text-decoration:none; width:80px; max-width:100%; height:auto;">
              <?php else: ?>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                  <tr>
                    <td style="width:44px; height:44px; background:<?= h($primary) ?>; border-radius:12px; text-align:center; vertical-align:middle;">
                      <span style="display:inline-block; font-family: 'Segoe UI', Arial, sans-serif; font-size:18px; font-weight:900; color:#ffffff; line-height:44px;">F</span>
                    </td>
                  </tr>
                </table>
              <?php endif; ?>
            </td>
          </tr>

          <tr>
            <td style="border: 1px solid #d8dde6; background:#ffffff;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                <tr>
                  <td style="padding: 22px 24px 18px;">
                    <?= $this->fetch('content') ?: ($content ?? '') ?>
                  </td>
                </tr>
                <tr>
                  <td style="padding: 0 24px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">
                      <tr>
                        <td style="border-top: 1px solid #e8edf3; height: 1px; line-height: 1px; font-size: 0;">&nbsp;</td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <td style="padding: 14px 24px 18px; font-size: 12px; color: #6b7280; line-height: 1.5;">
                    Ta wiadomość została wysłana automatycznie. Jeśli to nie Ty — zignoruj ją.
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td align="center" style="padding: 18px 0 0;">
              <div style="font-size:12px; color:#9aa4b2; line-height:1.6;">
                <div style="font-weight:700; color:#6b7280;">Biuro Rachunkowe &quot;PARTNER&quot; s.c.</div>
                <div>01-402 Warszawa, ul. Ciołka 10</div>
                <div>NIP: 527-251-12-37, REGON: 140584751</div>
                <div style="margin-top:6px;">Infolinia: 801 002 292</div>
                <div>pon. - pt.: 7:00 - 15:00</div>
                <div style="margin-top:10px;">© <?= $year ?> <?= h($appName) ?></div>
              </div>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>

<?php
/**
 * Bulletproof CTA button (email)
 *
 * Variables:
 * @var string $url
 * @var string $label
 * @var string|null $bg
 * @var string|null $textColor
 * @var int|null $radius
 */

$url = (string)($url ?? '');
$label = (string)($label ?? '');
$bg = (string)($bg ?? '#94c81f');
$textColor = (string)($textColor ?? '#ffffff');
$radius = (int)($radius ?? 4);

if ($url === '' || $label === '') {
    return;
}
?>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto;">
  <tr>
    <td align="center" bgcolor="<?= h($bg) ?>" style="border-radius:<?= h((string)$radius) ?>px;">
      <!--[if mso]>
      <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="<?= h($url) ?>" style="height:42px;v-text-anchor:middle;width:260px;" arcsize="6%" strokecolor="<?= h($bg) ?>" fillcolor="<?= h($bg) ?>">
        <w:anchorlock/>
        <center style="color:<?= h($textColor) ?>;font-family:Segoe UI, Arial, sans-serif;font-size:14px;font-weight:600;">
          <?= h($label) ?>
        </center>
      </v:roundrect>
      <![endif]-->
      <!--[if !mso]><!-- -->
      <a href="<?= h($url) ?>"
        style="display:inline-block;background:<?= h($bg) ?>;color:<?= h($textColor) ?>;text-decoration:none;padding:11px 18px;border-radius:<?= h((string)$radius) ?>px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:14px;font-weight:600;line-height:20px;">
        <?= h($label) ?>
      </a>
      <!--<![endif]-->
    </td>
  </tr>
</table>

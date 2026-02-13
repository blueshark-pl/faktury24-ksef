<?php
/** @var \\Cake\\View\\View $this */

if (empty($enabled)) {
    return;
}

$statusText = (string)($statusText ?? '');
$statusClass = (string)($statusClass ?? 'text-muted');
$messageTitle = $messageTitle ?? null;
$tooltip = $tooltip ?? null;
?>
<span class="mx-2">•</span>
<span class="small text-muted"<?= $tooltip ? ' title="' . h($tooltip) . '"' : '' ?>>
  KSeF:
  <span class="fw-semibold <?= h($statusClass) ?>"><?= h($statusText) ?></span>
  <?php if (is_string($messageTitle) && trim($messageTitle) !== ''): ?>
    <span class="mx-1">—</span>
    <span class="text-muted"><?= h(mb_strlen($messageTitle) > 80 ? (mb_substr($messageTitle, 0, 79) . '…') : $messageTitle) ?></span>
  <?php endif; ?>
</span>

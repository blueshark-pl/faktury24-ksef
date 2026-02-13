<?php
/** @var \\Cake\\View\\View $this */

if (empty($enabled)) {
    return;
}

$environment = (string)($environment ?? 'test');
$certText = (string)($certText ?? '');
$certClass = (string)($certClass ?? 'text-muted');
$grantsHintText = $grantsHintText ?? null;
$grantsHintClass = (string)($grantsHintClass ?? 'text-warning');
$tooltip = $tooltip ?? null;
$connText = $connText ?? null;
$connClass = (string)($connClass ?? 'text-muted');
$connTooltip = $connTooltip ?? null;

$fullTooltipParts = [];
if (is_string($tooltip) && trim($tooltip) !== '') {
    $fullTooltipParts[] = trim($tooltip);
}
if (is_string($connTooltip) && trim($connTooltip) !== '') {
    $fullTooltipParts[] = trim($connTooltip);
}
$fullTooltip = $fullTooltipParts ? implode("\n\n", $fullTooltipParts) : null;
?>
<span class="mx-2">•</span>
<span class="small text-muted"<?= $fullTooltip ? ' title="' . h($fullTooltip) . '"' : '' ?>>
  KSeF (firma):
  <span class="fw-semibold <?= h($certClass) ?>"><?= h($certText) ?></span>
  <?php if (is_string($grantsHintText) && trim($grantsHintText) !== ''): ?>
    <span class="mx-1">·</span>
    <span class="<?= h($grantsHintClass) ?>"><?= h($grantsHintText) ?></span>
  <?php endif; ?>
  <?php if (is_string($connText) && trim($connText) !== ''): ?>
    <span class="mx-1">·</span>
    <span class="<?= h($connClass) ?>"><?= h($connText) ?></span>
  <?php endif; ?>
  <span class="mx-1">·</span>
  <span class="text-muted"><?= h(strtoupper($environment)) ?></span>
</span>

<?php
/** @var \\Cake\\View\\View $this */

if (empty($enabled)) {
    return;
}

$environment = (string)($environment ?? 'prod');
$certText = (string)($certText ?? '');
$certClass = (string)($certClass ?? 'text-muted');
$grantsHintText = $grantsHintText ?? null;
$grantsHintClass = (string)($grantsHintClass ?? 'text-warning');
$tooltip = $tooltip ?? null;
$connText = $connText ?? null;
$connClass = (string)($connClass ?? 'text-muted');
$connTooltip = $connTooltip ?? null;
$invoiceWriteOk = (bool)($invoiceWriteOk ?? false);

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
<span id="ksef-auth-context" class="small text-muted" data-ksef-env="<?= h($environment) ?>"<?= $fullTooltip ? ' title="' . h($fullTooltip) . '"' : '' ?>>
  KSeF:

  <span id="ksef-auth-grants-sep" class="mx-1"<?= (is_string($grantsHintText) && trim($grantsHintText) !== '') ? '' : ' style="display:none"' ?>>·</span>
  <span id="ksef-auth-grants" class="<?= h($grantsHintClass) ?>"<?= (is_string($grantsHintText) && trim($grantsHintText) !== '') ? '' : ' style="display:none"' ?>><?= h((string)$grantsHintText) ?></span>

  <span id="ksef-auth-conn-sep" class="mx-1"<?= (is_string($connText) && trim($connText) !== '') ? '' : ' style="display:none"' ?>>·</span>
  <span id="ksef-auth-conn" class="<?= h($connClass) ?>"<?= (is_string($connText) && trim($connText) !== '') ? '' : ' style="display:none"' ?>><?= h((string)$connText) ?></span>

    <span id="ksef-auth-invoicewrite-msg-sep" class="mx-1"<?= $invoiceWriteOk ? '' : ' style="display:none"' ?>>·</span>
    <span id="ksef-auth-invoicewrite-msg" class="text-success"<?= $invoiceWriteOk ? '' : ' style="display:none"' ?>>Autoryzacja KSeF aktywna – wystawianie faktur w Twoim imieniu jest włączone.</span>

  <span class="mx-1">·</span>
  <span id="ksef-auth-env" class="text-muted"><?= h(strtoupper($environment)) ?></span>
</span>

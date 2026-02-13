<?php
/** @var \Cake\View\View $this */

if (empty($enabled) || empty($showBanner) || !is_array($important ?? null)) {
    return;
}

$important = (array)$important;
$title = trim((string)($important['title'] ?? ''));
$text = trim((string)($important['text'] ?? ''));
$start = $important['_start'] ?? null;
$end = $important['_end'] ?? null;

$now = \Cake\I18n\FrozenTime::now();
$isUpcoming = ($start instanceof \Cake\I18n\FrozenTime) ? $start->gt($now) : false;

$status = strtoupper((string)($status ?? ''));
$alertClass = 'alert-info';
if (in_array($status, ['FAILURE', 'TOTAL_FAILURE'], true)) {
    $alertClass = 'alert-danger';
} elseif ($isUpcoming || $status === 'MAINTENANCE') {
    $alertClass = 'alert-warning';
}

$fmt = function ($t): string {
  if ($t instanceof \Cake\I18n\FrozenTime) {
    return $t->i18nFormat('yyyy-MM-dd HH:mm');
  }
  return '';
};

$range = '';
if ($start instanceof \Cake\I18n\FrozenTime || $end instanceof \Cake\I18n\FrozenTime) {
    $range = trim(($start ? $fmt($start) : '') . ($end ? (' → ' . $fmt($end)) : ''));
}
?>

<div class="alert <?= h($alertClass) ?> d-flex align-items-start gap-3 mb-3" role="alert">
  <div class="flex-grow-1">
    <div class="fw-semibold mb-1">
      <?= $isUpcoming ? 'Planowane zdarzenie KSeF (komunikat MF)' : 'Komunikat MF – KSeF' ?>
    </div>

    <?php if ($title !== ''): ?>
      <div class="mb-1"><strong><?= h($title) ?></strong></div>
    <?php endif; ?>

    <?php if ($range !== ''): ?>
      <div class="small text-muted mb-2">
        <?= $isUpcoming ? 'Planowane:' : 'Okres:' ?> <?= h($range) ?>
      </div>
    <?php endif; ?>

    <?php if ($text !== ''): ?>
      <div class="small" style="white-space: pre-wrap;"><?= h(mb_strlen($text) > 600 ? (mb_substr($text, 0, 599) . '…') : $text) ?></div>
    <?php endif; ?>
  </div>

  <div class="ms-auto">
    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ksefMfMessagesModal">
      Zobacz wszystkie
    </button>
  </div>
</div>

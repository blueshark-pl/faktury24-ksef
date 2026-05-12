<?php
/** @var \\Cake\\View\\View $this */

if (empty($enabled)) {
    return;
}

$statusText = (string)($statusText ?? '');
$statusClass = (string)($statusClass ?? 'text-muted');
$messageTitle = $messageTitle ?? null;
$tooltip = $tooltip ?? null;
$messages = is_array($messages ?? null) ? $messages : [];
$activeMessages = is_array($activeMessages ?? null) ? $activeMessages : [];
$upcomingMessages = is_array($upcomingMessages ?? null) ? $upcomingMessages : [];

$modalId = 'ksefMfMessagesModal';
$modalLabelId = 'ksefMfMessagesModalLabel';

$hasAnyMessages = count($messages) > 0;
$badgeClass = ($activeMessages !== [] || $upcomingMessages !== []) ? 'text-warning' : 'text-muted';

static $modalRendered = false;

/** @return string */
$fmt = function ($t): string {
  if ($t instanceof \Cake\I18n\FrozenTime) {
    return $t->i18nFormat('yyyy-MM-dd HH:mm');
  }
  return '';
};
?>
<span class="mx-2">•</span>
<span class="small text-muted"<?= $tooltip ? ' title="' . h($tooltip) . '"' : '' ?>>
  KSeF:
  <span class="fw-semibold <?= h($statusClass) ?>"><?= h($statusText) ?></span>
  <?php if (is_string($messageTitle) && trim($messageTitle) !== ''): ?>
    <span class="mx-1">—</span>
    <span class="text-muted"><?= h(mb_strlen($messageTitle) > 80 ? (mb_substr($messageTitle, 0, 79) . '…') : $messageTitle) ?></span>
  <?php endif; ?>
  <span class="mx-1">·</span>
  <a href="#" role="button"
     data-bs-toggle="modal" data-bs-target="#<?= h($modalId) ?>"
     class="text-decoration-none <?= h($badgeClass) ?>">
    <?= __('Komunikaty MF') ?><?= $hasAnyMessages ? ' (' . (int)count($messages) . ')' : '' ?>
  </a>
</span>

<?php if (!$modalRendered): $modalRendered = true; ?>
  <div class="modal fade" id="<?= h($modalId) ?>" tabindex="-1" aria-labelledby="<?= h($modalLabelId) ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title" id="<?= h($modalLabelId) ?>"><?= __('Komunikaty MF – Latarnia KSeF') ?></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Zamknij') ?>"></button>
        </div>
        <div class="modal-body">
          <?php if (!$hasAnyMessages): ?>
            <div class="text-muted"><?= __('Brak komunikatów.') ?></div>
          <?php else: ?>

            <?php if ($activeMessages !== []): ?>
              <div class="mb-3">
                <div class="fw-semibold mb-2"><?= __('Aktywne') ?></div>
                <div class="list-group">
                  <?php foreach ($activeMessages as $m): ?>
                    <?php
                      $title = trim((string)($m['title'] ?? ''));
                      $text = trim((string)($m['text'] ?? ''));
                      $start = $m['_start'] ?? null;
                      $end = $m['_end'] ?? null;
                      $range = '';
                      if ($start instanceof \Cake\I18n\FrozenTime || $end instanceof \Cake\I18n\FrozenTime) {
                        $range = trim(($start ? $fmt($start) : '') . ($end ? (' → ' . $fmt($end)) : ''));
                      }
                    ?>
                    <div class="list-group-item">
                      <div class="d-flex align-items-start justify-content-between gap-3">
                        <div class="flex-grow-1">
                          <?php if ($title !== ''): ?>
                            <div class="fw-semibold"><?= h($title) ?></div>
                          <?php endif; ?>
                          <?php if ($range !== ''): ?>
                            <div class="small text-muted mb-2"><?= __('Okres') ?>: <?= h($range) ?></div>
                          <?php endif; ?>
                          <?php if ($text !== ''): ?>
                            <div class="small" style="white-space: pre-wrap;"><?= h($text) ?></div>
                          <?php endif; ?>
                        </div>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= __('Aktywne') ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($upcomingMessages !== []): ?>
              <div class="mb-3">
                <div class="fw-semibold mb-2"><?= __('Nadchodzące') ?></div>
                <div class="list-group">
                  <?php foreach ($upcomingMessages as $m): ?>
                    <?php
                      $title = trim((string)($m['title'] ?? ''));
                      $text = trim((string)($m['text'] ?? ''));
                      $start = $m['_start'] ?? null;
                      $end = $m['_end'] ?? null;
                      $range = '';
                      if ($start instanceof \Cake\I18n\FrozenTime || $end instanceof \Cake\I18n\FrozenTime) {
                        $range = trim(($start ? $fmt($start) : '') . ($end ? (' → ' . $fmt($end)) : ''));
                      }
                    ?>
                    <div class="list-group-item">
                      <div class="d-flex align-items-start justify-content-between gap-3">
                        <div class="flex-grow-1">
                          <?php if ($title !== ''): ?>
                            <div class="fw-semibold"><?= h($title) ?></div>
                          <?php endif; ?>
                          <?php if ($range !== ''): ?>
                            <div class="small text-muted mb-2"><?= __('Planowane') ?>: <?= h($range) ?></div>
                          <?php endif; ?>
                          <?php if ($text !== ''): ?>
                            <div class="small" style="white-space: pre-wrap;"><?= h($text) ?></div>
                          <?php endif; ?>
                        </div>
                        <span class="badge bg-info-subtle text-info border border-info-subtle"><?= __('Nadchodzące') ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php
              $other = [];
              $activeIds = array_map(fn($m) => (string)($m['id'] ?? ''), $activeMessages);
              $upcomingIds = array_map(fn($m) => (string)($m['id'] ?? ''), $upcomingMessages);
              foreach ($messages as $m) {
                $id = (string)($m['id'] ?? '');
                if ($id !== '' && (in_array($id, $activeIds, true) || in_array($id, $upcomingIds, true))) {
                  continue;
                }
                $other[] = $m;
              }
            ?>
            <?php if ($other !== []): ?>
              <details>
                <summary class="fw-semibold"><?= __('Pozostałe ({0})', (int)count($other)) ?></summary>
                <div class="list-group mt-2">
                  <?php foreach ($other as $m): ?>
                    <?php
                      $title = trim((string)($m['title'] ?? ''));
                      $text = trim((string)($m['text'] ?? ''));
                      $start = $m['_start'] ?? null;
                      $end = $m['_end'] ?? null;
                      $range = '';
                      if ($start instanceof \Cake\I18n\FrozenTime || $end instanceof \Cake\I18n\FrozenTime) {
                        $range = trim(($start ? $fmt($start) : '') . ($end ? (' → ' . $fmt($end)) : ''));
                      }
                    ?>
                    <div class="list-group-item">
                      <?php if ($title !== ''): ?>
                        <div class="fw-semibold"><?= h($title) ?></div>
                      <?php endif; ?>
                      <?php if ($range !== ''): ?>
                        <div class="small text-muted mb-2"><?= __('Okres') ?>: <?= h($range) ?></div>
                      <?php endif; ?>
                      <?php if ($text !== ''): ?>
                        <div class="small" style="white-space: pre-wrap;"><?= h($text) ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php endif; ?>

          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="https://ksef.mf.gov.pl/"><?= __('Strona KSeF (MF)') ?></a>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __('Zamknij') ?></button>
        </div>
      </div>
    </div>
  </div>
  <script>
    (function () {
      var modalId = '<?= h($modalId) ?>';

      function ensureModalAtBody() {
        var modalEl = document.getElementById(modalId);
        if (!modalEl) {
          return;
        }
        if (modalEl.parentElement !== document.body) {
          document.body.appendChild(modalEl);
        }
      }

      document.addEventListener('DOMContentLoaded', ensureModalAtBody);
      document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) {
          return;
        }
        var trigger = target.closest('[data-bs-target="#' + modalId + '"]');
        if (!trigger) {
          return;
        }
        ensureModalAtBody();
      }, true);
    })();
  </script>
<?php endif; ?>

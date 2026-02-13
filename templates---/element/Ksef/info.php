<?php
/**
 * KSeF info panel element
 * Expects: $apiInfo (array), $ksefEnv (string), $certInfo (array|null)
 */
?>
<?php if (isset($apiInfo)): ?>
  <div class="d-flex gap-3 align-items-center small text-muted mb-2">
    <span>Wyników łącznie: <span class="badge bg-secondary-subtle border text-secondary"><?= (int)($apiInfo['total'] ?? 0) ?></span></span>
    <?php if (!empty($apiInfo['hasMore'])): ?>
      <span class="badge bg-warning-subtle border text-warning">hasMore</span>
    <?php endif; ?>
    <?php if (!empty($apiInfo['isTruncated'])): ?>
      <span class="badge bg-warning-subtle border text-warning">isTruncated</span>
    <?php endif; ?>
    <?php if (!empty($certInfo)): ?>
      <span>Cert: <span class="badge bg-success-subtle border text-success"><?= h($certInfo['subjectCN'] ?? $certInfo['cn'] ?? 'aktywny') ?></span>
        <?php if (!empty($certInfo['nip'])): ?>
          <span class="badge bg-success-subtle border text-success">NIP <?= h($certInfo['nip']) ?></span>
        <?php endif; ?>
      </span>
    <?php endif; ?>
  </div>
<?php endif; ?>

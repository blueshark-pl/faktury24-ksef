<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AccountingAuthorization $auth
 */
$this->assign('title', __('Integracja księgowa – szczegóły'));
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2"><?= __('Integracja księgowa – szczegóły') ?></h1>
    <nav><ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><?= $this->Html->link(__('System księgowy'), ['action'=>'index']) ?></li>
      <li class="breadcrumb-item active"><?= __('Podgląd') ?></li>
    </ol></nav>
  </div>
  <div class="btn-list">
    <?= $this->Html->link('<i class="ri-list-check-2 align-middle"></i> '.__('Lista tokenów'), ['action'=>'index'], [
      'escape'=>false,'class'=>'btn btn-secondary-light btn-wave'
    ]) ?>

    <?php if ($auth->is_active): ?>
      <button type="button"
              class="btn btn-danger-light btn-wave"
              data-bs-toggle="modal"
              data-bs-target="#deactivateModal"
              data-url="<?= $this->Url->build(['action'=>'deactivate', $auth->id]) ?>"
              data-label="•••• <?= h($auth->token_last4) ?>">
        <i class="ri-lock-line align-middle me-1"></i><?= __('Dezaktywuj token') ?>
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card custom-card border-primary shadow-sm">
      <div class="card-header bg-primary text-white d-flex align-items-center">
        <i class="ri-key-line fs-5 me-2"></i>
        <div class="card-title mb-0"><?= __('Szczegóły tokenu integracji księgowej') ?></div>
      </div>
      <div class="card-body">
        <dl class="row mb-0 align-items-center">
          <dt class="col-sm-4"><?= __('Status tokenu') ?></dt>
          <dd class="col-sm-8">
            <?php if ($auth->status === 'active'): ?>
              <span class="badge bg-success-subtle text-success px-3 py-2">
                <i class="ri-checkbox-circle-line me-1"></i><?= __('Aktywny') ?>
              </span>
            <?php elseif ($auth->status === 'revoked'): ?>
              <span class="badge bg-danger-subtle text-danger px-3 py-2">
                <i class="ri-close-circle-line me-1"></i><?= __('Unieważniony') ?>
              </span>
            <?php elseif ($auth->status === 'expired'): ?>
              <span class="badge bg-warning-subtle text-warning px-3 py-2">
                <i class="ri-time-line me-1"></i><?= __('Wygasły') ?>
              </span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-muted px-3 py-2"><?= h($auth->status) ?></span>
            <?php endif; ?>
            <?php if ($auth->is_active): ?>
              <small class="text-muted ms-2">(<?= __('aktywny w systemie') ?>)</small>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-4"><?= __('Dostawca') ?></dt>
          <dd class="col-sm-8"><?= h($auth->provider ?: '—') ?></dd>

          <dt class="col-sm-4"><?= __('Środowisko') ?></dt>
          <dd class="col-sm-8">
            <?= $auth->environment === 'prod'
              ? '<span class="badge bg-primary-subtle text-primary px-3 py-2"><i class="ri-building-line me-1"></i>Produkcyjne</span>'
              : '<span class="badge bg-info-subtle text-info px-3 py-2"><i class="ri-flask-line me-1"></i>Testowe</span>' ?>
          </dd>

          <dt class="col-sm-4"><?= __('Odcisk tokenu') ?></dt>
          <dd class="col-sm-8"><code>•••• <?= h($auth->token_last4) ?></code></dd>

          <dt class="col-sm-4"><?= __('Ważność') ?></dt>
          <dd class="col-sm-8">
            <?= $auth->valid_from?->i18nFormat('yyyy-MM-dd HH:mm') ?: '—' ?> /
            <?= $auth->expires_at?->i18nFormat('yyyy-MM-dd HH:mm') ?: '—' ?>
          </dd>

          <dt class="col-sm-4"><?= __('Utworzono / Zmodyfikowano') ?></dt>
          <dd class="col-sm-8">
            <?= $auth->created?->i18nFormat('yyyy-MM-dd HH:mm') ?> /
            <?= $auth->modified?->i18nFormat('yyyy-MM-dd HH:mm') ?>
          </dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card custom-card border-0 shadow-sm">
      <div class="card-header bg-light d-flex align-items-center">
        <i class="ri-shield-check-line text-primary fs-5 me-2"></i>
        <div class="card-title mb-0"><?= __('Bezpieczeństwo i informacje') ?></div>
      </div>
      <div class="card-body small text-muted">
        <p>Token jest przechowywany w formie zaszyfrowanej. W interfejsie widoczny jest wyłącznie jego końcowy fragment (ostatnie 4 znaki).</p>
        <p>Dane tokenu są wykorzystywane wyłącznie do komunikacji z wybranym systemem księgowym.</p>
        <ul class="small mb-0">
          <li><?= __('Nowy token dezaktywuje poprzednie aktywne dla tej firmy.') ?></li>
          <li><?= __('Dostęp mają tylko użytkownicy o roli administracyjnej.') ?></li>
          <li><?= __('Klucze ochrony przechowywane są poza bazą danych.') ?></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php $csrf = $this->request->getAttribute('csrfToken'); ?>
<div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="deactivateLabel">
          <i class="ri-lock-line me-1 text-danger"></i> <?= __('Dezaktywuj token') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Zamknij') ?>"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2"><?= __('Czy na pewno chcesz dezaktywować ten token?') ?></p>
        <p class="text-muted small mb-0">
          <?= __('System przestanie używać tego tokenu do połączeń z systemem księgowym. Odcisk:') ?>
          <strong id="deactivateTokenLabel">••••</strong>
        </p>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary-light" data-bs-dismiss="modal">
          <i class="ri-close-line align-middle me-1"></i><?= __('Anuluj') ?>
        </button>
        <form id="deactivateForm" method="post" class="d-inline">
          <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>">
          <button type="submit" class="btn btn-danger">
            <i class="ri-shield-check-line align-middle me-1"></i><?= __('Tak, dezaktywuj') ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('deactivateModal');
  const form  = document.getElementById('deactivateForm');
  const label = document.getElementById('deactivateTokenLabel');

  modal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    if (!btn) return;
    form.setAttribute('action', btn.getAttribute('data-url'));
    label.textContent = btn.getAttribute('data-label') || '••••';
  });
});
</script>

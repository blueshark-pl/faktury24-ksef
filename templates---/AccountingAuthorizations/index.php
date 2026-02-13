<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface|\Cake\Collection\CollectionInterface $authorizations
 */
$this->assign('title', __('Integracja księgowa – tokeny'));
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2"><?= __('Integracja księgowa – tokeny') ?></h1>
    <nav><ol class="breadcrumb mb-0">
      <li class="breadcrumb-item active"><?= __('Lista') ?></li>
    </ol></nav>
  </div>
  <div class="btn-list">
    <?= $this->Html->link('<i class="ri-add-line align-middle"></i> '.__('Dodaj token'), ['action'=>'add'], [
      'escape'=>false,'class'=>'btn btn-primary btn-wave'
    ]) ?>
  </div>
</div>

<div class="card custom-card shadow-sm">
  <div class="card-header"><div class="card-title mb-0"><?= __('Tokeny systemu księgowego') ?></div></div>
  <div class="card-body table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th><?= __('Dostawca') ?></th>
          <th><?= __('Środowisko') ?></th>
          <th><?= __('Status') ?></th>
          <th><?= __('Odcisk') ?></th>
          <th class="text-end"><?= __('Akcje') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($authorizations as $row): ?>
          <tr>
            <td><?= h($row->provider ?: '—') ?></td>
            <td>
              <?= $row->environment === 'prod'
                ? '<span class="badge bg-primary-subtle text-primary px-3 py-2"><i class="ri-building-line me-1"></i>Produkcyjne</span>'
                : '<span class="badge bg-info-subtle text-info px-3 py-2"><i class="ri-flask-line me-1"></i>Testowe</span>' ?>
            </td>
            <td>
              <?php if ($row->status === 'active'): ?>
                <span class="badge bg-success-subtle text-success px-3 py-2">
                  <i class="ri-checkbox-circle-line me-1"></i><?= __('Aktywny') ?>
                </span>
              <?php elseif ($row->status === 'revoked'): ?>
                <span class="badge bg-danger-subtle text-danger px-3 py-2">
                  <i class="ri-close-circle-line me-1"></i><?= __('Unieważniony') ?>
                </span>
              <?php elseif ($row->status === 'expired'): ?>
                <span class="badge bg-warning-subtle text-warning px-3 py-2">
                  <i class="ri-time-line me-1"></i><?= __('Wygasły') ?>
                </span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-muted px-3 py-2"><?= h($row->status) ?></span>
              <?php endif; ?>
              <?php if ($row->is_active): ?>
                <small class="text-muted ms-2">(<?= __('aktywny w systemie') ?>)</small>
              <?php endif; ?>
            </td>
            <td><code>•••• <?= h($row->token_last4) ?></code></td>
            <td class="text-end">
              <div class="btn-group">
                <?= $this->Html->link('<i class="ri-eye-line"></i>', ['action'=>'view', $row->id], [
                  'escape'=>false, 'class'=>'btn btn-sm btn-secondary-light', 'title'=>__('Podgląd')
                ]) ?>
                <?php if ($row->is_active): ?>
                  <button type="button"
                          class="btn btn-sm btn-danger-light"
                          title="<?= __('Dezaktywuj token') ?>"
                          data-bs-toggle="modal"
                          data-bs-target="#deactivateModal"
                          data-url="<?= $this->Url->build(['action'=>'deactivate', $row->id]) ?>"
                          data-label="•••• <?= h($row->token_last4) ?>">
                    <i class="ri-lock-line"></i>
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
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
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Notification $notification
 */

$this->assign('title', __('Powiadomienie'));

$sevIcon = [
  'success' => 'ri-check-line',
  'warning' => 'ri-alert-line',
  'danger'  => 'ri-error-warning-line',
  'info'    => 'ri-information-line',
];

$sevBadge = [
  'success' => 'bg-success-transparent',
  'warning' => 'bg-warning-transparent',
  'danger'  => 'bg-danger-transparent',
  'info'    => 'bg-info-transparent',
];

$chanLabel = [
  'email' => 'EMAIL',
  'push'  => 'PUSH',
  'sms'   => 'SMS',
];

$icon = $sevIcon[$notification->severity] ?? 'ri-notification-3-line';
$sev  = $sevBadge[$notification->severity] ?? 'bg-secondary-transparent';
$chan = $chanLabel[$notification->channel] ?? strtoupper((string)$notification->channel);

$tz = 'Europe/Warsaw';
$created  = $notification->created?->setTimezone($tz)->i18nFormat('yyyy-MM-dd HH:mm');
$modified = $notification->modified?->setTimezone($tz)->i18nFormat('yyyy-MM-dd HH:mm');
$readAt   = $notification->read_at?->setTimezone($tz)->i18nFormat('yyyy-MM-dd HH:mm');
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2"><?= __('Powiadomienie') ?></h1>
    <nav><ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><?= $this->Html->link(__('Powiadomienia'), ['action'=>'index']) ?></li>
      <li class="breadcrumb-item active"><?= h($notification->title) ?></li>
    </ol></nav>
  </div>
  <div class="btn-list">
    <?= $this->Html->link('<i class="ri-arrow-go-back-line align-middle"></i> '.__('Wróć do listy'), ['action'=>'index'], [
      'escape'=>false,'class'=>'btn btn-secondary-light btn-wave'
    ]) ?>
    <?= $this->Html->link('<i class="ri-edit-2-line align-middle"></i> '.__('Edytuj'), ['action'=>'edit', $notification->id], [
      'escape'=>false,'class'=>'btn btn-primary btn-wave'
    ]) ?>
    <?= $this->Form->postLink('<i class="ri-delete-bin-6-line align-middle"></i> '.__('Usuń'), ['action'=>'delete', $notification->id], [
      'escape'=>false,'class'=>'btn btn-danger-light btn-wave',
      'confirm'=>__('Czy na pewno usunąć powiadomienie #{0}?', $notification->id)
    ]) ?>
    <?= $this->Html->link('<i class="ri-add-line align-middle"></i> '.__('Nowe'), ['action'=>'add'], [
      'escape'=>false,'class'=>'btn btn-success-light btn-wave'
    ]) ?>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex align-items-start gap-3 mb-3">
      <span class="avatar avatar-lg avatar-rounded <?= h($sev) ?>">
        <i class="<?= h($icon) ?> fs-24"></i>
      </span>
      <div class="flex-grow-1">
        <h3 class="mb-1"><?= h($notification->title) ?></h3>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge <?= h($sev) ?>">
            <?php
              echo match ($notification->severity) {
                'success' => __('Sukces'),
                'warning' => __('Ostrzeżenie'),
                'danger'  => __('Błąd'),
                default   => __('Informacja'),
              };
            ?>
          </span>
          <span class="badge bg-secondary-transparent"><?= h($chan) ?></span>
          <span class="badge bg-light text-muted border"><?= h($notification->type) ?></span>
          <?php if ($notification->is_read): ?>
            <span class="badge bg-success"><i class="ri-eye-line me-1"></i><?= __('Przeczytane') ?></span>
          <?php else: ?>
            <span class="badge bg-warning"><i class="ri-eye-off-line me-1"></i><?= __('Nieprzeczytane') ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($notification->message)): ?>
      <div class="alert alert-secondary border-start border-2 mt-3">
        <div class="text-muted small mb-1"><?= __('Treść powiadomienia') ?></div>
        <div class="fs-14">
          <?= $this->Text->autoParagraph(h($notification->message)) ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="row g-3 mt-2">
      <div class="col-md-6">
        <div class="card shadow-none border">
          <div class="card-body">
            <h6 class="fw-semibold mb-3"><?= __('Szczegóły') ?></h6>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <tr>
                  <th class="w-25 text-muted"><?= __('ID') ?></th>
                  <td><?= h($notification->id) ?></td>
                </tr>
                <tr>
                  <th class="text-muted"><?= __('Użytkownik') ?></th>
                  <td>
                    <?php if ($notification->hasValue('user') && $notification->user): ?>
                      <?= $this->Html->link(h($notification->user->username ?? $notification->user->id), ['controller'=>'Users','action'=>'view',$notification->user->id]) ?>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <th class="text-muted"><?= __('Kanał') ?></th>
                  <td><?= h($notification->channel) ?></td>
                </tr>
                <tr>
                  <th class="text-muted"><?= __('Typ') ?></th>
                  <td><?= h($notification->type) ?></td>
                </tr>
                <tr>
                  <th class="text-muted"><?= __('Priorytet') ?></th>
                  <td><?= h($notification->severity) ?></td>
                </tr>
                <tr>
                  <th class="text-muted"><?= __('Adres akcji') ?></th>
                  <td>
                    <?php if (!empty($notification->action_url)): ?>
                      <?= $this->Html->link(h($notification->action_url), $notification->action_url, ['target'=>'_self']) ?>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <th class="text-muted"><?= __('Etykieta akcji') ?></th>
                  <td><?= !empty($notification->action_label) ? h($notification->action_label) : '<span class="text-muted">—</span>' ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card shadow-none border">
          <div class="card-body">
            <h6 class="fw-semibold mb-3"><?= __('Status i daty') ?></h6>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <tr>
                  <th class="w-25 text-muted"><?= __('Przeczytane') ?></th>
                  <td><?= $notification->is_read ? __('Tak') : __('Nie') ?></td>
                </tr>
                <tr>
                  <th class="text-muted"><?= __('Przeczytano') ?></th>
                  <td><?= $readAt ?: '—' ?></td>
                </tr>
                <tr>
                  <th class="text-muted"><?= __('Utworzono') ?></th>
                  <td><?= $created ?: '—' ?></td>
                </tr>
                <tr>
                  <th class="text-muted"><?= __('Zmieniono') ?></th>
                  <td><?= $modified ?: '—' ?></td>
                </tr>
              </table>
            </div>

            <?php if (!empty($notification->action_url) && !empty($notification->action_label)): ?>
              <div class="mt-3">
                <?= $this->Html->link(
                  h($notification->action_label).' <i class="ri-arrow-right-up-line ms-1"></i>',
                  $notification->action_url,
                  ['escape'=>false,'class'=>'btn btn-outline-primary btn-wave']
                ) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

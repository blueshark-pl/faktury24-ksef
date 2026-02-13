<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface|\Cake\Collection\CollectionInterface $notifications
 * @var int $unreadCount
 */

$this->assign('title', 'Powiadomienia');

$q        = trim((string)$this->request->getQuery('q'));
$channel  = $this->request->getQuery('channel');
$severity = $this->request->getQuery('severity');
$read     = $this->request->getQuery('read');
$type     = $this->request->getQuery('type');

$badgeMap = [
  'info'    => 'bg-info-transparent',
  'success' => 'bg-success-transparent',
  'warning' => 'bg-warning-transparent',
  'danger'  => 'bg-danger-transparent',
];

$channelBadge = [
  'email' => 'EMAIL',
  'push'  => 'PUSH',
  'sms'   => 'SMS',
];
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2"><?= __('Powiadomienia') ?></h1>
    <nav><ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><?= $this->Html->link(__('Dashboard'), ['controller'=>'Pages','action'=>'display','home']) ?></li>
      <li class="breadcrumb-item active"><?= __('Powiadomienia') ?></li>
    </ol></nav>
  </div>
  <div class="btn-list">
    <span class="badge bg-secondary-transparent"><?= $unreadCount ?> <?= __('nieprzeczytanych') ?></span>
    <?= $this->Html->link('<i class="ri-refresh-line align-middle"></i> '.__('Odśwież'), ['action'=>'index'], [
      'escape'=>false,'class'=>'btn btn-secondary-light btn-wave'
    ]) ?>
  </div>
</div>

<div class="card">
  <div class="card-body pb-2">
    <?= $this->Form->create(null, ['type'=>'get','class'=>'row g-2 align-items-end']) ?>
      <div class="col-md-3">
        <?= $this->Form->control('q', [
          'label'=>__('Szukaj'),
          'value'=>$q,
          'placeholder'=>__('tytuł / treść / typ...'),
          'class'=>'form-control'
        ]) ?>
      </div>
      <div class="col-md-2">
        <?= $this->Form->control('channel', [
          'label'=>__('Kanał'),
          'empty'=>__('Wszystkie'),
          'options'=>['email'=>'E-mail','push'=>'Push','sms'=>'SMS'],
          'value'=>$channel,
          'class'=>'form-select'
        ]) ?>
      </div>
      <div class="col-md-2">
        <?= $this->Form->control('severity', [
          'label'=>__('Priorytet'),
          'empty'=>__('Wszystkie'),
          'options'=>['info'=>'Info','success'=>'Sukces','warning'=>'Ostrzeżenie','danger'=>'Błąd'],
          'value'=>$severity,
          'class'=>'form-select'
        ]) ?>
      </div>
      <div class="col-md-2">
        <?= $this->Form->control('read', [
          'label'=>__('Status'),
          'empty'=>__('Wszystkie'),
          'options'=>['0'=>'Nieprzeczytane','1'=>'Przeczytane'],
          'value'=>$read,
          'class'=>'form-select'
        ]) ?>
      </div>
      <div class="col-md-2">
        <?= $this->Form->control('type', [
          'label'=>__('Typ'),
          'placeholder'=>__('np. invoice_received'),
          'value'=>$type,
          'class'=>'form-control'
        ]) ?>
      </div>
      <div class="col-md-1 d-grid">
        <button class="btn btn-primary btn-wave"><i class="ri-search-2-line me-1"></i><?= __('Filtruj') ?></button>
      </div>
    <?= $this->Form->end() ?>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th><?= __('Tytuł') ?></th>
          <th class="text-nowrap"><?= __('Kanał') ?></th>
          <th class="text-nowrap"><?= __('Typ') ?></th>
          <th class="text-nowrap"><?= __('Priorytet') ?></th>
          <th class="text-nowrap"><?= __('Status') ?></th>
          <th class="text-nowrap"><?= __('Utworzono') ?></th>
          <th class="text-end"><?= __('Akcje') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($notifications as $n): ?>
        <?php
          $sevClass = $badgeMap[$n->severity] ?? 'bg-secondary-transparent';
          $isUnread = !$n->is_read;
        ?>
        <tr class="<?= $isUnread ? 'table-warning-subtle' : '' ?>">
          <td>
            <div class="d-flex align-items-start gap-3">
              <span class="avatar avatar-md avatar-rounded <?= h($sevClass) ?>">
                <i class="ri-notification-3-line fs-16"></i>
              </span>
              <div>
                <div class="fw-semibold"><?= h($n->title) ?></div>
                <?php if (!empty($n->message)): ?>
                  <div class="text-muted small text-truncate-2" style="max-width: 560px;">
                    <?= $this->Text->autoParagraph(h($n->message)) ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($n->action_url) && !empty($n->action_label)): ?>
                  <div class="mt-1">
                    <?= $this->Html->link(h($n->action_label).' <i class="ri-arrow-right-up-line ms-1"></i>', $n->action_url, [
                      'escape'=>false,'class'=>'small text-primary text-decoration-underline'
                    ]) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td class="text-nowrap">
            <span class="badge bg-secondary-transparent"><?= $channelBadge[$n->channel] ?? strtoupper(h($n->channel)) ?></span>
          </td>
          <td class="text-nowrap"><span class="badge bg-light text-muted border"><?= h($n->type) ?></span></td>
          <td class="text-nowrap">
            <?php
              $label = match ($n->severity) {
                'success' => 'Sukces',
                'warning' => 'Ostrzeżenie',
                'danger'  => 'Błąd',
                default   => 'Info',
              };
            ?>
            <span class="badge <?= h($sevClass) ?>"><?= $label ?></span>
          </td>
          <td class="text-nowrap">
            <?php if ($isUnread): ?>
              <span class="badge bg-warning"><?= __('Nieprzeczytane') ?></span>
            <?php else: ?>
              <span class="badge bg-success"><?= __('Przeczytane') ?></span>
            <?php endif; ?>
          </td>
          <td class="text-nowrap"><?= $n->created?->setTimezone('Europe/Warsaw')->i18nFormat('yyyy-MM-dd HH:mm') ?></td>
          <td class="text-end">
            <!-- Pod przyszłe akcje (np. oznacz przeczytane) -->
            <div class="btn-list">
              <?= $this->Html->link('<i class="ri-eye-line"></i>', ['action'=>'view', $n->id], [
                'class'=>'btn btn-sm btn-icon btn-secondary-light', 'escape'=>false, 'title'=>__('Podgląd')
              ]) ?>
              <!-- Tu w przyszłości: POST link markAsRead/markAsUnread -->
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card-footer">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="text-muted small">
        <?= $this->Paginator->counter('{{start}}–{{end}} z {{count}}') ?>
      </div>
      <div>
        <ul class="pagination mb-0">
          <?= $this->Paginator->first('<<') ?>
          <?= $this->Paginator->prev('<') ?>
          <?= $this->Paginator->numbers() ?>
          <?= $this->Paginator->next('>') ?>
          <?= $this->Paginator->last('>>') ?>
        </ul>
      </div>
    </div>
  </div>
</div>

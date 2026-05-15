<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\SupportTicket $ticket
 */
$this->assign('title', 'Zgłoszenie #' . $ticket->id);

$statusBadge = match((string)$ticket->status) {
    'nowe'       => '<span class="badge bg-primary">Nowe</span>',
    'w_toku'     => '<span class="badge bg-warning text-dark">W toku</span>',
    'rozwiazane' => '<span class="badge bg-success">Rozwiązane</span>',
    'zamkniete'  => '<span class="badge bg-secondary">Zamknięte</span>',
    default      => '<span class="badge bg-secondary">' . h((string)$ticket->status) . '</span>',
};
$categories = \App\Model\Table\SupportTicketsTable::CATEGORIES;
$types      = \App\Model\Table\SupportTicketsTable::TYPES;
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Zgłoszenie #<?= $ticket->id ?></h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item"><a href="<?= $this->Url->build(['action' => 'index']) ?>">Zgłoszenia</a></li>
      <li class="breadcrumb-item active">#<?= $ticket->id ?></li>
    </ol>
  </div>
  <?= $this->Html->link('<i class="ri-arrow-left-line me-1"></i>Powrót',
    ['action' => 'index'],
    ['escape' => false, 'class' => 'btn btn-light btn-sm']
  ) ?>
</div>

<div class="row g-3">
  <!-- Lewa kolumna: wiadomości -->
  <div class="col-lg-8">
    <!-- Oryginalne zgłoszenie -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <span class="fw-semibold"><?= h($ticket->title) ?></span>
          <span class="ms-2"><?= $statusBadge ?></span>
          <?php if (!empty($ticket->type)): ?>
            <span class="badge bg-dark ms-1"><?= h($types[$ticket->type] ?? $ticket->type) ?></span>
          <?php endif ?>
        </div>
        <small class="text-muted"><?= h(date('Y-m-d H:i', strtotime((string)$ticket->created))) ?></small>
      </div>
      <div class="card-body">
        <div class="mb-2">
          <span class="badge bg-light text-dark border"><?= h($categories[$ticket->category] ?? $ticket->category) ?></span>
        </div>
        <p class="mb-0" style="white-space:pre-wrap"><?= h($ticket->description) ?></p>
        <?php if (!empty($ticket->attachment)): ?>
          <div class="mt-3">
            <a href="<?= $this->Url->build(['action' => 'download', $ticket->id]) ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
              <i class="ri-attachment-2 me-1"></i><?= h($ticket->attachment_name ?: $ticket->attachment) ?>
            </a>
          </div>
        <?php endif ?>
      </div>
    </div>

    <!-- Odpowiedzi -->
    <?php foreach ($ticket->support_ticket_replies as $reply): ?>
    <?php $isAdmin = (bool)$reply->is_admin_reply; ?>
    <div class="card mb-2 <?= $isAdmin ? 'border-primary' : '' ?>">
      <div class="card-header d-flex align-items-center justify-content-between py-2 <?= $isAdmin ? 'bg-primary bg-opacity-10' : '' ?>">
        <div class="d-flex align-items-center gap-2">
          <?php if ($isAdmin): ?>
            <i class="ri-shield-user-line text-primary"></i>
            <span class="fw-semibold text-primary">Administrator</span>
          <?php else: ?>
            <i class="ri-user-line text-muted"></i>
            <span class="fw-semibold"><?= h($reply->author_name ?: 'Ty') ?></span>
          <?php endif ?>
        </div>
        <small class="text-muted"><?= h(date('Y-m-d H:i', strtotime((string)$reply->created))) ?></small>
      </div>
      <div class="card-body py-2">
        <p class="mb-0" style="white-space:pre-wrap"><?= h($reply->message) ?></p>
      </div>
    </div>
    <?php endforeach ?>

    <!-- Formularz odpowiedzi -->
    <?php if ($ticket->status !== 'zamkniete' && $ticket->status !== 'rozwiazane'): ?>
    <div class="card mt-3">
      <div class="card-header">
        <h6 class="mb-0">Dodaj odpowiedź</h6>
      </div>
      <div class="card-body">
        <?= $this->Form->create(null, ['url' => ['action' => 'view', $ticket->id]]) ?>
        <div class="mb-3">
          <?= $this->Form->textarea('message', [
            'class'       => 'form-control',
            'rows'        => 4,
            'placeholder' => 'Napisz odpowiedź lub dodatkowe informacje...',
            'required'    => true,
          ]) ?>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="ri-send-plane-line me-1"></i>Wyślij
        </button>
        <?= $this->Form->end() ?>
      </div>
    </div>
    <?php elseif ($ticket->status === 'zamkniete'): ?>
      <div class="alert alert-secondary mt-3">Zgłoszenie zostało zamknięte.</div>
    <?php elseif ($ticket->status === 'rozwiazane'): ?>
      <div class="alert alert-success mt-3">Zgłoszenie zostało rozwiązane.</div>
    <?php endif ?>
  </div>

  <!-- Prawa kolumna: szczegóły -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h6 class="mb-0">Szczegóły zgłoszenia</h6></div>
      <div class="card-body">
        <dl class="row mb-0 fs-13">
          <dt class="col-5 text-muted">ID</dt>
          <dd class="col-7">#<?= $ticket->id ?></dd>
          <dt class="col-5 text-muted">Status</dt>
          <dd class="col-7"><?= $statusBadge ?></dd>
          <dt class="col-5 text-muted">Kategoria</dt>
          <dd class="col-7"><?= h($categories[$ticket->category] ?? $ticket->category) ?></dd>
          <?php if (!empty($ticket->type)): ?>
          <dt class="col-5 text-muted">Typ tech.</dt>
          <dd class="col-7"><span class="badge bg-dark"><?= h($types[$ticket->type] ?? $ticket->type) ?></span></dd>
          <?php endif ?>
          <dt class="col-5 text-muted">Utworzono</dt>
          <dd class="col-7"><?= h(date('Y-m-d H:i', strtotime((string)$ticket->created))) ?></dd>
          <dt class="col-5 text-muted">Odpowiedzi</dt>
          <dd class="col-7"><?= count($ticket->support_ticket_replies) ?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($taskUpdates)): ?>
<div class="card mt-3" style="max-width:800px">
  <div class="card-header fw-semibold d-flex align-items-center gap-2">
    <i class="bi bi-kanban text-primary"></i> Status realizacji
  </div>
  <ul class="list-group list-group-flush">
    <?php foreach ($taskUpdates as $upd): ?>
      <li class="list-group-item py-2 px-3" style="font-size:13px">
        <div><?= h($upd->message) ?></div>
        <div class="text-muted" style="font-size:11px"><?= $upd->created->format('Y-m-d H:i') ?></div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

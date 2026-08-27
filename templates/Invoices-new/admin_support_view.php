<?php
/**
 * Admin: szczegóły zgłoszenia
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\SupportTicket $ticket
 * @var array $statuses
 * @var array $types
 * @var array $categories
 */
$this->assign('title', 'Zgłoszenie #' . $ticket->id);

$statusBadge = match((string)$ticket->status) {
    'nowe'       => '<span class="badge bg-primary">Nowe</span>',
    'w_toku'     => '<span class="badge bg-warning text-dark">W toku</span>',
    'rozwiazane' => '<span class="badge bg-success">Rozwiązane</span>',
    'zamkniete'  => '<span class="badge bg-secondary">Zamknięte</span>',
    default      => '<span class="badge bg-secondary">' . h((string)$ticket->status) . '</span>',
};
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Zgłoszenie #<?= $ticket->id ?> <?= $statusBadge ?></h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item"><a href="<?= $this->Url->build(['action' => 'adminSupport']) ?>">Zgłoszenia</a></li>
      <li class="breadcrumb-item active">#<?= $ticket->id ?></li>
    </ol>
  </div>
  <div class="d-flex gap-2">
    <?= $this->Html->link('<i class="bi bi-kanban me-1"></i>Utwórz task',
      ['controller' => 'Tasks', 'action' => 'add', '?' => ['ticket_id' => $ticket->id]],
      ['escape' => false, 'class' => 'btn btn-primary btn-sm']
    ) ?>
    <?= $this->Html->link('<i class="ri-arrow-left-line me-1"></i>Powrót',
      ['action' => 'adminSupport'],
      ['escape' => false, 'class' => 'btn btn-light btn-sm']
    ) ?>
  </div>
</div>

<div class="row g-3">

  <!-- Lewa: historia rozmowy -->
  <div class="col-lg-8">

    <!-- Oryginalne zgłoszenie -->
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= h($ticket->title) ?></span>
        <small class="text-muted"><?= h(date('Y-m-d H:i', strtotime((string)$ticket->created))) ?></small>
      </div>
      <div class="card-body">
        <span class="badge bg-light text-dark border mb-2"><?= h($categories[$ticket->category] ?? $ticket->category) ?></span>
        <p style="white-space:pre-wrap" class="mb-0"><?= h($ticket->description) ?></p>
        <?php if (!empty($ticket->attachment)): ?>
          <div class="mt-3">
            <a href="<?= $this->Url->build(['controller' => 'SupportTickets', 'action' => 'download', $ticket->id]) ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
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
      <div class="card-header d-flex justify-content-between align-items-center py-2 <?= $isAdmin ? 'bg-primary bg-opacity-10' : '' ?>">
        <div class="d-flex align-items-center gap-2">
          <?php if ($isAdmin): ?>
            <i class="ri-shield-user-line text-primary"></i>
            <span class="fw-semibold text-primary">Administrator</span>
          <?php else: ?>
            <i class="ri-user-line text-muted"></i>
            <span class="fw-semibold"><?= h($reply->author_name ?: 'Użytkownik') ?></span>
          <?php endif ?>
        </div>
        <small class="text-muted"><?= h(date('Y-m-d H:i', strtotime((string)$reply->created))) ?></small>
      </div>
      <div class="card-body py-2">
        <p style="white-space:pre-wrap" class="mb-0"><?= h($reply->message) ?></p>
      </div>
    </div>
    <?php endforeach ?>

    <!-- Formularz odpowiedzi admina -->
    <div class="card mt-3">
      <div class="card-header"><h6 class="mb-0">Odpowiedz użytkownikowi</h6></div>
      <div class="card-body">
        <?= $this->Form->create(null, ['url' => ['action' => 'adminSupportView', $ticket->id]]) ?>
        <input type="hidden" name="_action" value="reply">
        <div class="mb-3">
          <?= $this->Form->textarea('message', [
            'class'       => 'form-control',
            'rows'        => 5,
            'placeholder' => 'Napisz odpowiedź dla użytkownika…',
            'required'    => true,
          ]) ?>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="ri-send-plane-line me-1"></i>Wyślij odpowiedź
        </button>
        <?= $this->Form->end() ?>
      </div>
    </div>

  </div>

  <!-- Prawa: panel admina -->
  <div class="col-lg-4">

    <!-- Zmiana statusu / typu / notatki -->
    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0">Zarządzanie zgłoszeniem</h6></div>
      <div class="card-body">
        <?= $this->Form->create(null, ['url' => ['action' => 'adminSupportView', $ticket->id]]) ?>
        <input type="hidden" name="_action" value="update">

        <div class="mb-3">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" class="form-select form-select-sm">
            <?php foreach ($statuses as $val => $lbl): ?>
              <option value="<?= $val ?>"<?= $ticket->status === $val ? ' selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Typ techniczny <small class="text-muted">(tylko admin)</small></label>
          <select name="type" class="form-select form-select-sm">
            <option value="">— nie określono —</option>
            <?php foreach ($types as $val => $lbl): ?>
              <option value="<?= $val ?>"<?= ($ticket->type ?? '') === $val ? ' selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Notatka wewnętrzna <small class="text-muted">(niewidoczna dla usera)</small></label>
          <?= $this->Form->textarea('admin_note', [
            'class' => 'form-control form-control-sm',
            'rows'  => 3,
            'value' => $ticket->admin_note ?? '',
          ]) ?>
        </div>

        <div class="mb-3">
          <input type="hidden" name="is_for_accountant" value="0">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch"
              name="is_for_accountant" value="1" id="is-for-accountant"
              <?= !empty($ticket->is_for_accountant) ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="is-for-accountant">
              <i class="ri-calculator-line me-1 text-primary"></i>Dotyczy księgowego
            </label>
          </div>
          <div class="text-muted small mt-1">Zaznacz, jeśli zgłoszenie wymaga uwagi księgowego-admina.</div>
        </div>

        <button type="submit" class="btn btn-outline-primary btn-sm w-100">
          <i class="ri-save-line me-1"></i>Zapisz zmiany
        </button>
        <?= $this->Form->end() ?>
      </div>
    </div>

    <!-- Kto pisze (firma + user) -->
    <?php $company = $ticket->company ?? null; $user = $ticket->user ?? null; ?>
    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0"><i class="ri-building-line me-1"></i>Firma zgłaszająca</h6></div>
      <div class="card-body">
        <?php if ($company): ?>
          <div class="fw-semibold mb-1"><?= h($company->name ?? '—') ?></div>
          <?php if (!empty($company->altname)): ?>
            <div class="text-muted small mb-1"><?= h($company->altname) ?></div>
          <?php endif ?>
          <dl class="row mb-0 fs-13">
            <?php if (!empty($company->nip)): ?>
              <dt class="col-5 text-muted">NIP</dt>
              <dd class="col-7"><?= h($company->nip) ?></dd>
            <?php endif ?>
            <?php if (!empty($company->city) || !empty($company->postal_code)): ?>
              <dt class="col-5 text-muted">Adres</dt>
              <dd class="col-7"><?= h(trim(($company->postal_code ?? '') . ' ' . ($company->city ?? ''))) ?></dd>
            <?php endif ?>
          </dl>
        <?php else: ?>
          <div class="text-muted small">Brak przypisanej firmy.</div>
        <?php endif ?>

        <hr class="my-2">

        <div class="fw-semibold mb-1"><i class="ri-user-line me-1"></i>Użytkownik</div>
        <?php if ($user): ?>
          <dl class="row mb-0 fs-13">
            <?php if (!empty($user->email)): ?>
              <dt class="col-5 text-muted">Email</dt>
              <dd class="col-7"><a href="mailto:<?= h($user->email) ?>"><?= h($user->email) ?></a></dd>
            <?php endif ?>
            <?php $fullName = trim(((string)($user->first_name ?? '')) . ' ' . ((string)($user->last_name ?? ''))); ?>
            <?php if ($fullName !== ''): ?>
              <dt class="col-5 text-muted">Imię i nazwisko</dt>
              <dd class="col-7"><?= h($fullName) ?></dd>
            <?php endif ?>
          </dl>
        <?php else: ?>
          <div class="text-muted small">Użytkownik nie istnieje.</div>
        <?php endif ?>
      </div>
    </div>

    <!-- Szczegóły -->
    <div class="card">
      <div class="card-header"><h6 class="mb-0">Szczegóły zgłoszenia</h6></div>
      <div class="card-body">
        <dl class="row mb-0 fs-13">
          <dt class="col-5 text-muted">ID</dt>
          <dd class="col-7">#<?= $ticket->id ?></dd>
          <dt class="col-5 text-muted">Kategoria</dt>
          <dd class="col-7"><?= h($categories[$ticket->category] ?? $ticket->category) ?></dd>
          <dt class="col-5 text-muted">Odpowiedzi</dt>
          <dd class="col-7"><?= count($ticket->support_ticket_replies) ?></dd>
          <dt class="col-5 text-muted">Utworzono</dt>
          <dd class="col-7"><?= h(date('Y-m-d H:i', strtotime((string)$ticket->created))) ?></dd>
          <dt class="col-5 text-muted">Zmieniono</dt>
          <dd class="col-7"><?= h(date('Y-m-d H:i', strtotime((string)$ticket->modified))) ?></dd>
        </dl>
        <details class="mt-2">
          <summary class="text-muted small" style="cursor:pointer">Identyfikatory techniczne</summary>
          <dl class="row mb-0 fs-13 mt-2">
            <dt class="col-5 text-muted">User ID</dt>
            <dd class="col-7 text-truncate" style="max-width:0"><small><?= h($ticket->user_id) ?></small></dd>
            <dt class="col-5 text-muted">Firma ID</dt>
            <dd class="col-7 text-truncate" style="max-width:0"><small><?= h($ticket->company_id ?: '—') ?></small></dd>
          </dl>
        </details>
      </div>
    </div>

  </div>
</div>

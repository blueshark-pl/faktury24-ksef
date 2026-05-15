<?php
/**
 * Admin: lista zgłoszeń support
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $tickets
 * @var int $newCount
 * @var array $statuses
 * @var array $types
 * @var array $categories
 */
$this->assign('title', 'Zgłoszenia użytkowników');

$this->Paginator->setTemplates([
    'nextActive'     => '<li class="page-item"><a class="page-link" href="{{url}}">›</a></li>',
    'nextDisabled'   => '<li class="page-item disabled"><a class="page-link" href="#">›</a></li>',
    'prevActive'     => '<li class="page-item"><a class="page-link" href="{{url}}">‹</a></li>',
    'prevDisabled'   => '<li class="page-item disabled"><a class="page-link" href="#">‹</a></li>',
    'number'         => '<li class="page-item"><a class="page-link" href="{{url}}">{{text}}</a></li>',
    'current'        => '<li class="page-item active"><a class="page-link" href="{{url}}">{{text}}</a></li>',
    'first'          => '<li class="page-item"><a class="page-link" href="{{url}}">««</a></li>',
    'last'           => '<li class="page-item"><a class="page-link" href="{{url}}">»»</a></li>',
]);

$statusBadge = fn(string $s): string => match($s) {
    'nowe'       => '<span class="badge bg-primary">Nowe</span>',
    'w_toku'     => '<span class="badge bg-warning text-dark">W toku</span>',
    'rozwiazane' => '<span class="badge bg-success">Rozwiązane</span>',
    'zamkniete'  => '<span class="badge bg-secondary">Zamknięte</span>',
    default      => '<span class="badge bg-secondary">' . h($s) . '</span>',
};

$q      = trim((string)$this->request->getQuery('q'));
$status = $this->request->getQuery('status');
$type   = $this->request->getQuery('type');
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">
      Zgłoszenia użytkowników
      <?php if ($newCount > 0): ?>
        <span class="badge bg-danger ms-2"><?= $newCount ?> nowych</span>
      <?php endif ?>
    </h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item active">Admin — zgłoszenia</li>
    </ol>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <?= $this->Form->create(null, ['type' => 'get', 'url' => ['action' => 'adminSupport'], 'class' => 'd-flex flex-wrap gap-2 align-items-end']) ?>
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Szukaj po tytule lub opisie…" value="<?= h($q) ?>" style="width:220px">
      <select name="status" class="form-select form-select-sm" style="width:140px">
        <option value="">Wszystkie statusy</option>
        <?php foreach ($statuses as $val => $lbl): ?>
          <option value="<?= $val ?>"<?= $status === $val ? ' selected' : '' ?>><?= h($lbl) ?></option>
        <?php endforeach ?>
      </select>
      <select name="type" class="form-select form-select-sm" style="width:140px">
        <option value="">Wszystkie typy</option>
        <?php foreach ($types as $val => $lbl): ?>
          <option value="<?= $val ?>"<?= $type === $val ? ' selected' : '' ?>><?= h($lbl) ?></option>
        <?php endforeach ?>
      </select>
      <div class="btn-group btn-group-sm">
        <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i>Filtruj</button>
        <?= $this->Html->link('<i class="ri-refresh-line"></i>', ['action' => 'adminSupport'], ['escape' => false, 'class' => 'btn btn-light', 'title' => 'Wyczyść']) ?>
      </div>
    <?= $this->Form->end() ?>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0 fs-13">
        <thead class="table-light">
          <tr>
            <th style="width:4%">#</th>
            <th style="width:20%">Tytuł</th>
            <th style="width:12%">Kategoria</th>
            <th style="width:10%">Typ tech.</th>
            <th style="width:10%">Status</th>
            <th style="width:14%">Firma / User</th>
            <th style="width:8%">Odp.</th>
            <th style="width:12%">Data</th>
            <th style="width:8%" class="text-end">Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $ticket): ?>
          <tr<?= $ticket->status === 'nowe' ? ' class="table-warning"' : '' ?>>
            <td class="text-muted"><?= $ticket->id ?></td>
            <td class="fw-medium text-truncate" style="max-width:0">
              <span class="d-block text-truncate"><?= h($ticket->title) ?></span>
              <?php if (!empty($ticket->is_for_accountant)): ?>
                <span class="badge bg-primary-transparent text-primary border border-primary" style="font-size:.7rem">
                  <i class="ri-calculator-line me-1"></i>Księgowy
                </span>
              <?php endif ?>
            </td>
            <td><?= h($categories[$ticket->category] ?? $ticket->category) ?></td>
            <td>
              <?php if (!empty($ticket->type)): ?>
                <span class="badge bg-dark"><?= h($types[$ticket->type] ?? $ticket->type) ?></span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif ?>
            </td>
            <td><?= $statusBadge((string)$ticket->status) ?></td>
            <td class="text-muted small text-truncate" style="max-width:0"><?= h($ticket->company_id ?: '—') ?></td>
            <td class="text-center">
              <?php
                $repliesCount = is_countable($ticket->support_ticket_replies ?? []) ? count($ticket->support_ticket_replies ?? []) : 0;
              ?>
              <?= $repliesCount > 0 ? '<span class="badge bg-light text-dark border">' . $repliesCount . '</span>' : '—' ?>
            </td>
            <td class="text-muted text-nowrap"><?= h(date('Y-m-d H:i', strtotime((string)$ticket->created))) ?></td>
            <td class="text-end">
              <?= $this->Html->link('<i class="ri-eye-line"></i>',
                ['action' => 'adminSupportView', $ticket->id],
                ['escape' => false, 'class' => 'btn btn-light btn-sm', 'title' => 'Szczegóły']
              ) ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if ($tickets->count() === 0): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Brak zgłoszeń.</td></tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-center">
    <ul class="pagination pagination-sm mb-0">
      <?= $this->Paginator->first() ?>
      <?= $this->Paginator->prev() ?>
      <?= $this->Paginator->numbers(['modulus' => 5]) ?>
      <?= $this->Paginator->next() ?>
      <?= $this->Paginator->last() ?>
    </ul>
  </div>
</div>

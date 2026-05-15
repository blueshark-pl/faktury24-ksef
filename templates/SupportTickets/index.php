<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $tickets
 */
$this->assign('title', 'Moje zgłoszenia');

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

$categoryLabel = \App\Model\Table\SupportTicketsTable::CATEGORIES;
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Moje zgłoszenia</h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item active">Zgłoszenia</li>
    </ol>
  </div>
  <?= $this->Html->link('<i class="ri-add-line me-1"></i>Nowe zgłoszenie',
    ['action' => 'add'],
    ['escape' => false, 'class' => 'btn btn-primary btn-sm']
  ) ?>
</div>

<div class="card">
  <div class="card-body p-0">
    <?php if ($tickets->count() === 0): ?>
      <div class="text-center py-5 text-muted">
        <i class="ri-inbox-2-line fs-1 d-block mb-2"></i>
        Nie masz jeszcze żadnych zgłoszeń.
        <?= $this->Html->link('Utwórz pierwsze zgłoszenie', ['action' => 'add'], ['class' => 'd-block mt-2']) ?>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0 fs-13">
        <thead class="table-light">
          <tr>
            <th style="width:4%">#</th>
            <th>Tytuł</th>
            <th style="width:14%">Kategoria</th>
            <th style="width:10%">Status</th>
            <th style="width:13%">Data</th>
            <th style="width:8%" class="text-end">Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $ticket): ?>
          <tr>
            <td class="text-muted"><?= $ticket->id ?></td>
            <td class="fw-medium"><?= h($ticket->title) ?></td>
            <td><?= h($categoryLabel[$ticket->category] ?? $ticket->category) ?></td>
            <td><?= $statusBadge((string)$ticket->status) ?></td>
            <td class="text-muted text-nowrap"><?= h(date('Y-m-d H:i', strtotime((string)$ticket->created))) ?></td>
            <td class="text-end">
              <?= $this->Html->link('<i class="ri-eye-line"></i>',
                ['action' => 'view', $ticket->id],
                ['escape' => false, 'class' => 'btn btn-light btn-sm', 'title' => 'Szczegóły']
              ) ?>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
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
    <?php endif ?>
  </div>
</div>

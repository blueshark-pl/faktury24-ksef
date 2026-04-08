<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Task $task
 * @var \App\Model\Entity\SupportTicket|null $ticket
 * @var array $columns
 * @var array $statuses
 * @var array $priorities
 * @var iterable $labels
 */
$this->assign('title', 'Nowy task');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0 fw-semibold">Nowy task</h4>
  <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-kanban"></i> Tablica
  </a>
</div>

<?php if ($ticket): ?>
<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-link-45deg"></i> Task tworzony na podstawie zgłoszenia
  <strong>#<?= $ticket->id ?> – <?= h($ticket->title) ?></strong>
</div>
<?php endif; ?>

<div class="card" style="max-width:700px">
  <div class="card-body">
    <?= $this->Form->create($task, ['url' => ['action' => 'add']]) ?>
      <?php if ($ticket): ?>
        <input type="hidden" name="support_ticket_id" value="<?= (int)$ticket->id ?>">
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label fw-semibold">Tytuł <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control <?= $task->getError('title') ? 'is-invalid' : '' ?>"
               value="<?= h($task->title) ?>" required autofocus>
        <?php if ($task->getError('title')): ?>
          <div class="invalid-feedback"><?= h(implode(', ', $task->getError('title'))) ?></div>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-sm-4">
          <label class="form-label fw-semibold">Kolumna</label>
          <select name="kanban_column" class="form-select">
            <?php foreach ($columns as $k => $v): ?>
              <option value="<?= h($k) ?>" <?= ($task->kanban_column ?? 'proposal') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-4">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" class="form-select">
            <?php foreach ($statuses as $k => $v): ?>
              <option value="<?= h($k) ?>" <?= ($task->status ?? 'todo') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-4">
          <label class="form-label fw-semibold">Priorytet</label>
          <select name="priority" class="form-select">
            <?php foreach ($priorities as $k => $v): ?>
              <option value="<?= h($k) ?>" <?= ($task->priority ?? 'normal') === $k ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Etykiety</label>
        <div class="d-flex flex-wrap gap-3">
          <?php foreach ($labels as $lbl): ?>
            <div class="form-check">
              <input type="checkbox" class="form-check-input" name="task_labels[_ids][]"
                     id="lbl<?= $lbl->id ?>" value="<?= $lbl->id ?>">
              <label class="form-check-label" for="lbl<?= $lbl->id ?>">
                <span class="badge rounded-pill" style="background:<?= h($lbl->color) ?>;color:#fff"><?= h($lbl->name) ?></span>
              </label>
            </div>
          <?php endforeach; ?>
          <?php if ($labels->count() === 0): ?>
            <span class="text-muted small">Brak etykiet — <a href="<?= $this->Url->build(['action' => 'labels']) ?>">dodaj etykiety</a></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Opis</label>
        <textarea name="description" class="form-control" rows="5"><?= h($task->description) ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Utwórz task</button>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-secondary">Anuluj</a>
      </div>
    <?= $this->Form->end() ?>
  </div>
</div>

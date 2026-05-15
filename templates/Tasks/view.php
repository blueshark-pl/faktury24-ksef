<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Task $task
 * @var int $totalMinutes
 * @var \App\Model\Entity\TaskTimeEntry|null $activeTimer
 * @var array $columns
 * @var array $statuses
 * @var array $priorities
 * @var iterable $allLabels
 */

$this->assign('title', $task->number_formatted . ' – ' . $task->title);

$totalStr = $totalMinutes > 0
    ? ($totalMinutes >= 60 ? floor($totalMinutes/60).'h '.($totalMinutes%60).'m' : $totalMinutes.'m')
    : '—';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <span class="text-muted small"><?= h($task->number_formatted) ?></span>
    <h4 class="mb-0 fw-semibold"><?= h($task->title) ?></h4>
  </div>
  <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-kanban"></i> Powrót do tablicy
  </a>
</div>

<div class="row g-3">
  <!-- Lewa kolumna: szczegóły + edycja -->
  <div class="col-lg-8">

    <!-- Szczegóły + formularz edycji -->
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Szczegóły</span>
        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editForm">
          <i class="bi bi-pencil"></i> Edytuj
        </button>
      </div>
      <div class="card-body">
        <div class="row g-2 mb-2">
          <div class="col-sm-4">
            <div class="text-muted small">Status</div>
            <span class="badge bg-<?= h($task->status_badge_class) ?>"><?= h($task->status_label) ?></span>
          </div>
          <div class="col-sm-4">
            <div class="text-muted small">Kolumna</div>
            <span class="badge bg-secondary"><?= h($task->column_label) ?></span>
          </div>
          <div class="col-sm-4">
            <div class="text-muted small">Priorytet</div>
            <span class="badge bg-<?= h($task->priority_badge_class) ?>"><?= h($task->priority_label) ?></span>
          </div>
        </div>
        <?php if (!empty($task->task_labels)): ?>
        <div class="mb-2">
          <div class="text-muted small mb-1">Etykiety</div>
          <div class="d-flex flex-wrap gap-1">
            <?php foreach ($task->task_labels as $lbl): ?>
              <span class="badge rounded-pill" style="background:<?= h($lbl->color) ?>;color:#fff"><?= h($lbl->name) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($task->description)): ?>
        <div>
          <div class="text-muted small mb-1">Opis</div>
          <div style="white-space:pre-wrap;font-size:13px"><?= h($task->description) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($task->support_ticket_id): ?>
        <div class="mt-2">
          <a href="<?= $this->Url->build([
            'controller' => 'Invoices',
            'action' => 'adminSupportView',
            $task->support_ticket_id
          ]) ?>" class="small text-decoration-none">
            <i class="bi bi-link-45deg"></i> Powiązane zgłoszenie #<?= $task->support_ticket_id ?>
          </a>
        </div>
        <?php endif; ?>
      </div>

      <!-- Formularz edycji (collapsed) -->
      <div class="collapse" id="editForm">
        <div class="card-body border-top" style="background:#f8fafc">
          <?= $this->Form->create(null, ['url' => ['action' => 'view', $task->id]]) ?>
            <?= $this->Form->hidden('_action', ['value' => 'edit']) ?>

            <div class="row g-2">
              <div class="col-12">
                <label class="form-label small fw-semibold">Tytuł</label>
                <input type="text" name="title" class="form-control form-control-sm" value="<?= h($task->title) ?>" required>
              </div>
              <div class="col-sm-4">
                <label class="form-label small fw-semibold">Status</label>
                <select name="status" class="form-select form-select-sm">
                  <?php foreach ($statuses as $k => $v): ?>
                    <option value="<?= h($k) ?>" <?= $task->status === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-4">
                <label class="form-label small fw-semibold">Kolumna</label>
                <select name="kanban_column" class="form-select form-select-sm">
                  <?php foreach ($columns as $k => $v): ?>
                    <option value="<?= h($k) ?>" <?= $task->kanban_column === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-4">
                <label class="form-label small fw-semibold">Priorytet</label>
                <select name="priority" class="form-select form-select-sm">
                  <?php foreach ($priorities as $k => $v): ?>
                    <option value="<?= h($k) ?>" <?= $task->priority === $k ? 'selected' : '' ?>><?= h($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Etykiety</label>
                <div class="d-flex flex-wrap gap-2">
                  <?php
                  $taskLabelIds = array_map(fn($l) => $l->id, $task->task_labels ?? []);
                  foreach ($allLabels as $lbl): ?>
                    <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="task_labels[_ids][]"
                             id="lbl<?= $lbl->id ?>" value="<?= $lbl->id ?>"
                             <?= in_array($lbl->id, $taskLabelIds) ? 'checked' : '' ?>>
                      <label class="form-check-label small" for="lbl<?= $lbl->id ?>">
                        <span class="badge rounded-pill" style="background:<?= h($lbl->color) ?>;color:#fff"><?= h($lbl->name) ?></span>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Opis</label>
                <textarea name="description" class="form-control form-control-sm" rows="4"><?= h($task->description) ?></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm">Zapisz</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#editForm">Anuluj</button>
              </div>
            </div>
          <?= $this->Form->end() ?>
        </div>
      </div>
    </div>

    <!-- Aktualizacje dla użytkownika -->
    <div class="card mb-3">
      <div class="card-header fw-semibold">Aktualizacje <small class="text-muted">(widoczne dla użytkownika zgłoszenia)</small></div>
      <div class="card-body p-0">
        <?php if (empty($task->task_updates)): ?>
          <p class="text-muted small m-3">Brak aktualizacji.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($task->task_updates as $upd): ?>
              <li class="list-group-item py-2 px-3" style="font-size:13px">
                <div><?= h($upd->message) ?></div>
                <div class="text-muted" style="font-size:11px"><?= $upd->created->format('Y-m-d H:i') ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="card-footer p-2">
        <?= $this->Form->create(null, ['url' => ['action' => 'view', $task->id]]) ?>
          <?= $this->Form->hidden('_action', ['value' => 'add_update']) ?>
          <div class="input-group input-group-sm">
            <input type="text" name="message" class="form-control" placeholder="Nowa aktualizacja dla użytkownika…" required>
            <button type="submit" class="btn btn-outline-primary">Dodaj</button>
          </div>
        <?= $this->Form->end() ?>
      </div>
    </div>
  </div>

  <!-- Prawa kolumna: czas -->
  <div class="col-lg-4">
    <!-- Timer -->
    <div class="card mb-3">
      <div class="card-header fw-semibold">
        <i class="bi bi-stopwatch"></i> Czas pracy
        <span class="float-end text-muted small">Łącznie: <?= h($totalStr) ?></span>
      </div>
      <div class="card-body text-center">
        <?php if ($activeTimer): ?>
          <!-- Timer aktywny -->
          <div id="timer-display" class="display-6 fw-bold text-primary mb-2" data-started="<?= $activeTimer->started_at->format('U') ?>">
            00:00:00
          </div>
          <form id="stop-form">
            <input type="text" name="note" class="form-control form-control-sm mb-2" placeholder="Notatka (opcjonalnie)">
            <button type="submit" class="btn btn-danger btn-sm w-100"><i class="bi bi-stop-fill"></i> Stop</button>
          </form>
        <?php else: ?>
          <p class="text-muted small mb-2">Timer zatrzymany.</p>
          <button id="btn-start-timer" class="btn btn-success btn-sm w-100"><i class="bi bi-play-fill"></i> Start</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Lista wpisów czasu -->
    <div class="card">
      <div class="card-header fw-semibold small">Historia czasu</div>
      <div class="card-body p-0">
        <?php if (empty($task->task_time_entries)): ?>
          <p class="text-muted small m-3">Brak wpisów.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($task->task_time_entries as $entry): ?>
              <?php if (!$entry->stopped_at) continue; ?>
              <li class="list-group-item py-2 px-3 d-flex justify-content-between align-items-center" style="font-size:12px">
                <div>
                  <div><?= $entry->started_at->format('d.m H:i') ?> – <?= $entry->stopped_at->format('H:i') ?></div>
                  <?php if ($entry->note): ?><div class="text-muted"><?= h($entry->note) ?></div><?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <span class="fw-semibold">
                    <?= $entry->minutes >= 60 ? floor($entry->minutes/60).'h '.($entry->minutes%60).'m' : $entry->minutes.'m' ?>
                  </span>
                  <?= $this->Form->create(null, ['url' => ['action' => 'view', $task->id], 'onsubmit' => 'return confirm(\'Usunąć?\')']) ?>
                    <?= $this->Form->hidden('_action', ['value' => 'delete_time']) ?>
                    <?= $this->Form->hidden('entry_id', ['value' => $entry->id]) ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" style="font-size:11px"><i class="bi bi-trash3"></i></button>
                  <?= $this->Form->end() ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  const taskId = <?= (int)$task->id ?>;
  const baseUrl = '<?= $this->Url->build(['action' => 'index']) ?>'.replace('/tasks', '');

  // ── Timer aktywny ─────────────────────────────────────────────────────────
  const display = document.getElementById('timer-display');
  if (display) {
    const started = parseInt(display.dataset.started) * 1000;
    function tick() {
      const elapsed = Math.floor((Date.now() - started) / 1000);
      const h = String(Math.floor(elapsed / 3600)).padStart(2, '0');
      const m = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
      const s = String(elapsed % 60).padStart(2, '0');
      display.textContent = h + ':' + m + ':' + s;
    }
    tick();
    setInterval(tick, 1000);

    document.getElementById('stop-form').addEventListener('submit', function(e) {
      e.preventDefault();
      const note = this.note.value;
      fetch('<?= $this->Url->build(['action' => 'stopTimer', '_args' => ['__ID__']]) ?>'.replace('__ID__', taskId), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': '<?= $this->request->getAttribute('csrfToken') ?>',
        },
        body: JSON.stringify({ note }),
      }).then(() => location.reload());
    });
  }

  // ── Start timer ───────────────────────────────────────────────────────────
  const btnStart = document.getElementById('btn-start-timer');
  if (btnStart) {
    btnStart.addEventListener('click', function() {
      btnStart.disabled = true;
      fetch('<?= $this->Url->build(['action' => 'startTimer', '_args' => ['__ID__']]) ?>'.replace('__ID__', taskId), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': '<?= $this->request->getAttribute('csrfToken') ?>',
        },
        body: '{}',
      }).then(() => location.reload());
    });
  }
})();
</script>

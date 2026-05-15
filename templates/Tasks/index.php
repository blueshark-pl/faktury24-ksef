<?php
/**
 * @var \App\View\AppView $this
 * @var array $board   ['column' => Task[]]
 * @var array $columns ['column' => 'Label']
 * @var iterable $labels
 */

$this->assign('title', 'Tablica Kanban');

// Kolory kolumn
$colColors = [
    'proposal'  => '#94a3b8',
    'icebox'    => '#67e8f9',
    'p2'        => '#86efac',
    'p1'        => '#fbbf24',
    'emergency' => '#f87171',
    'testing'   => '#c084fc',
    'complete'  => '#34d399',
    'archive'   => '#d1d5db',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0 fw-semibold">Tablica Kanban</h4>
  <div class="d-flex gap-2">
    <a href="<?= $this->Url->build(['action' => 'labels']) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-tags"></i> Etykiety
    </a>
    <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-primary">
      <i class="bi bi-plus-lg"></i> Nowy task
    </a>
  </div>
</div>

<!-- Kanban board — horizontal scroll -->
<div id="kanban-board" class="d-flex gap-3 pb-3" style="overflow-x:auto;min-height:70vh;align-items:flex-start">
<?php foreach ($columns as $colKey => $colLabel): ?>
  <?php $tasks = $board[$colKey] ?? []; ?>
  <div class="kanban-column flex-shrink-0" data-column="<?= h($colKey) ?>"
       style="width:240px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0">
    <!-- Column header -->
    <div class="kanban-col-header d-flex justify-content-between align-items-center px-3 py-2"
         style="border-radius:10px 10px 0 0;background:<?= h($colColors[$colKey] ?? '#e2e8f0') ?>;color:#1e293b">
      <span class="fw-semibold small"><?= h($colLabel) ?></span>
      <span class="badge bg-white text-dark" style="font-size:11px"><?= count($tasks) ?></span>
    </div>
    <!-- Cards container (Sortable target) -->
    <div class="kanban-cards px-2 py-2" data-column="<?= h($colKey) ?>" style="min-height:60px">
      <?php foreach ($tasks as $task): ?>
        <?php include __DIR__ . '/element_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<script>
// ── Sortable.js Kanban ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  const containers = document.querySelectorAll('.kanban-cards');
  containers.forEach(function (el) {
    new Sortable(el, {
      group: 'kanban',
      animation: 150,
      ghostClass: 'sortable-ghost',
      onEnd: function (evt) {
        const taskId = evt.item.dataset.taskId;
        const column = evt.to.dataset.column;
        // Zbierz nową kolejność w kolumnie docelowej
        const order = Array.from(evt.to.children).map(c => c.dataset.taskId);

        fetch('<?= $this->Url->build(['action' => 'move']) ?>', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= $this->request->getAttribute('csrfToken') ?>',
          },
          body: JSON.stringify({ task_id: taskId, column: column, order: order }),
        });

        // Zaktualizuj licznik kolumn
        document.querySelectorAll('.kanban-column').forEach(function(col) {
          const cnt = col.querySelector('.kanban-cards').children.length;
          col.querySelector('.badge').textContent = cnt;
        });
      }
    });
  });
});
</script>

<style>
.sortable-ghost { opacity: .4; }
.kanban-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.12); transform: translateY(-1px); }
.kanban-card { transition: box-shadow .15s, transform .15s; }
</style>
<?php
// Ładuj Sortable.js tylko na tej stronie
$this->append('script', '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>');
?>

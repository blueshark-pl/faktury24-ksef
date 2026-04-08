<?php
/**
 * @var \App\Model\Entity\Task $task
 */
$priorityColor = match($task->priority) {
    'critical' => '#ef4444',
    'high'     => '#f59e0b',
    'normal'   => '#3b82f6',
    'low'      => '#10b981',
    default    => '#94a3b8',
};
$totalMin = $task->total_minutes ?? 0;
$timeStr  = $totalMin > 0
    ? ($totalMin >= 60 ? floor($totalMin/60).'h '.($totalMin%60).'m' : $totalMin.'m')
    : '';
?>
<div class="kanban-card bg-white rounded-3 mb-2 p-2 cursor-grab"
     data-task-id="<?= $task->id ?>"
     style="border:1px solid #e2e8f0;border-left:4px solid <?= $priorityColor ?>;font-size:13px">
  <div class="d-flex justify-content-between align-items-start mb-1">
    <span class="text-muted" style="font-size:11px"><?= h($task->number_formatted) ?></span>
    <?php if ($timeStr): ?>
      <span class="text-muted" style="font-size:11px"><i class="bi bi-clock"></i> <?= h($timeStr) ?></span>
    <?php endif; ?>
  </div>
  <a href="<?= $this->Url->build(['action' => 'view', $task->id]) ?>"
     class="d-block fw-semibold text-dark text-decoration-none lh-sm mb-1"
     style="font-size:13px"><?= h($task->title) ?></a>
  <?php if (!empty($task->task_labels)): ?>
    <div class="d-flex flex-wrap gap-1 mt-1">
      <?php foreach ($task->task_labels as $lbl): ?>
        <span class="badge rounded-pill"
              style="background:<?= h($lbl->color) ?>;font-size:10px;color:#fff">
          <?= h($lbl->name) ?>
        </span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="d-flex justify-content-between align-items-center mt-2">
    <span class="badge bg-<?= h($task->status_badge_class) ?>" style="font-size:10px">
      <?= h($task->status_label) ?>
    </span>
    <?php if (!empty($task->support_ticket_id)): ?>
      <span class="text-muted" style="font-size:10px" title="Powiązane zgłoszenie #<?= $task->support_ticket_id ?>">
        <i class="bi bi-link-45deg"></i>#<?= $task->support_ticket_id ?>
      </span>
    <?php endif; ?>
  </div>
</div>

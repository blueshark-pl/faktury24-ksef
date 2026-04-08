<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $labels
 */
$this->assign('title', 'Etykiety tasków');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0 fw-semibold">Etykiety tasków</h4>
  <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-kanban"></i> Tablica
  </a>
</div>

<div class="row g-3">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header fw-semibold">Dodaj etykietę</div>
      <div class="card-body">
        <?= $this->Form->create(null, ['url' => ['action' => 'labels']]) ?>
          <input type="hidden" name="_action" value="add">
          <div class="mb-2">
            <label class="form-label small fw-semibold">Nazwa</label>
            <input type="text" name="name" class="form-control form-control-sm" required maxlength="80">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Kolor</label>
            <input type="color" name="color" class="form-control form-control-sm form-control-color" value="#6366f1">
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Dodaj</button>
        <?= $this->Form->end() ?>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <div class="card">
      <div class="card-header fw-semibold">Istniejące etykiety</div>
      <div class="card-body p-0">
        <?php if ($labels->count() === 0): ?>
          <p class="text-muted small m-3">Brak etykiet.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($labels as $lbl): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                <span class="badge rounded-pill" style="background:<?= h($lbl->color) ?>;color:#fff;font-size:13px">
                  <?= h($lbl->name) ?>
                </span>
                <?= $this->Form->create(null, ['url' => ['action' => 'labels'], 'onsubmit' => 'return confirm(\'Usunąć etykietę?\')']) ?>
                  <input type="hidden" name="_action" value="delete">
                  <input type="hidden" name="id" value="<?= $lbl->id ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                    <i class="bi bi-trash3"></i>
                  </button>
                <?= $this->Form->end() ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$this->assign('title', 'Kategorie kosztów');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Kategorie kosztów (admin)</h4>
  <div>
    <?= $this->Html->link('Dodaj', ['action' => 'index'], ['class' => 'btn btn-sm btn-outline-secondary', 'onclick' => 'document.getElementById("add-costcat").classList.toggle("d-none"); return false;']) ?>
  </div>
</div>

<div id="add-costcat" class="card card-body mb-3 d-none">
  <?= $this->Form->create(null) ?>
    <div class="row g-2">
      <div class="col-md-5">
        <?= $this->Form->control('name', ['label' => 'Nazwa', 'class' => 'form-control', 'required' => true]) ?>
      </div>
      <div class="col-md-5">
        <?= $this->Form->control('code', ['label' => 'Kod (opcjonalnie)', 'class' => 'form-control']) ?>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100" type="submit">Zapisz</button>
      </div>
    </div>
  <?= $this->Form->end() ?>
</div>

<div class="table-responsive">
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>Nazwa</th>
        <th>Kod</th>
        <th>Aktywna</th>
        <th>Sort</th>
        <th class="text-end">Akcje</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td><?= h((string)$c->name) ?></td>
          <td><?= h((string)($c->code ?? '')) ?></td>
          <td><?= $c->is_active ? 'tak' : 'nie' ?></td>
          <td><?= (int)($c->sort_order ?? 0) ?></td>
          <td class="text-end">
            <?= $this->Html->link('Edytuj', ['action' => 'edit', $c->id], ['class' => 'btn btn-sm btn-outline-primary']) ?>
            <?= $this->Form->postLink('Usuń', ['action' => 'delete', $c->id], ['confirm' => 'Usunąć kategorię?', 'class' => 'btn btn-sm btn-outline-danger']) ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= $this->element('pagination') ?>

<?php
$this->assign('title', 'Edytuj kategorię kosztów');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Edytuj kategorię</h4>
  <div>
    <?= $this->Html->link('Powrót', ['action' => 'index'], ['class' => 'btn btn-sm btn-outline-secondary']) ?>
  </div>
</div>

<div class="card card-body">
  <?= $this->Form->create($cat) ?>
    <div class="row g-3">
      <div class="col-md-6">
        <?= $this->Form->control('name', ['label' => 'Nazwa', 'class' => 'form-control', 'required' => true]) ?>
      </div>
      <div class="col-md-6">
        <?= $this->Form->control('code', ['label' => 'Kod', 'class' => 'form-control']) ?>
      </div>
      <div class="col-md-3">
        <?= $this->Form->control('is_active', ['type' => 'checkbox', 'label' => 'Aktywna']) ?>
      </div>
      <div class="col-md-3">
        <?= $this->Form->control('sort_order', ['label' => 'Sort', 'class' => 'form-control', 'type' => 'number']) ?>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" type="submit">Zapisz</button>
      </div>
    </div>
  <?= $this->Form->end() ?>
</div>

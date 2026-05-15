<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\SupportTicket $ticket
 * @var array $categories
 */
$this->assign('title', 'Nowe zgłoszenie');
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Nowe zgłoszenie</h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item"><a href="<?= $this->Url->build(['action' => 'index']) ?>">Zgłoszenia</a></li>
      <li class="breadcrumb-item active">Nowe</li>
    </ol>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Formularz zgłoszenia</h5>
      </div>
      <div class="card-body">
        <?= $this->Form->create($ticket, ['type' => 'file', 'novalidate' => true]) ?>

        <div class="mb-3">
          <?= $this->Form->label('title', 'Tytuł zgłoszenia <span class="text-danger">*</span>', ['escape' => false]) ?>
          <?= $this->Form->text('title', ['class' => 'form-control' . ($ticket->getError('title') ? ' is-invalid' : ''), 'placeholder' => 'Krótki opis problemu lub pomysłu']) ?>
          <?php if ($ticket->getError('title')): ?>
            <div class="invalid-feedback"><?= h(implode(', ', (array)$ticket->getError('title'))) ?></div>
          <?php endif ?>
        </div>

        <div class="mb-3">
          <?= $this->Form->label('category', 'Kategoria <span class="text-danger">*</span>', ['escape' => false]) ?>
          <?= $this->Form->select('category', $categories, [
            'class'  => 'form-select' . ($ticket->getError('category') ? ' is-invalid' : ''),
            'empty'  => '— wybierz kategorię —',
          ]) ?>
          <?php if ($ticket->getError('category')): ?>
            <div class="invalid-feedback"><?= h(implode(', ', (array)$ticket->getError('category'))) ?></div>
          <?php endif ?>
        </div>

        <div class="mb-3">
          <?= $this->Form->label('description', 'Opis <span class="text-danger">*</span>', ['escape' => false]) ?>
          <?= $this->Form->textarea('description', [
            'class' => 'form-control' . ($ticket->getError('description') ? ' is-invalid' : ''),
            'rows'  => 7,
            'placeholder' => "Opisz dokładnie problem, kroki do jego odtworzenia, lub szczegóły swojego pomysłu...",
          ]) ?>
          <?php if ($ticket->getError('description')): ?>
            <div class="invalid-feedback"><?= h(implode(', ', (array)$ticket->getError('description'))) ?></div>
          <?php endif ?>
        </div>

        <div class="mb-4">
          <?= $this->Form->label('attachment', 'Załącznik (screenshot / PDF)') ?>
          <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf">
          <div class="form-text text-muted">Opcjonalnie. Maks. 5 MB. Formaty: JPG, PNG, GIF, PDF.</div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="ri-send-plane-line me-1"></i>Wyślij zgłoszenie
          </button>
          <?= $this->Html->link('Anuluj', ['action' => 'index'], ['class' => 'btn btn-light']) ?>
        </div>

        <?= $this->Form->end() ?>
      </div>
    </div>
  </div>
</div>

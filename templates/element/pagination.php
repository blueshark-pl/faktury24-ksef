<?php
// Generic pagination element styled with Bootstrap 5
?>
<nav aria-label="Paginacja" class="mt-3">
  <ul class="pagination justify-content-center mb-2">
    <?php if ($this->Paginator->hasPrev()): ?>
      <li class="page-item"><?= $this->Paginator->prev('«', ['class' => 'page-link']) ?></li>
    <?php else: ?>
      <li class="page-item disabled"><span class="page-link">«</span></li>
    <?php endif; ?>

    <?= $this->Paginator->numbers([
      'before' => false,
      'after' => false,
      'templates' => [
        'number' => '<li class="page-item"><a class="page-link" href="{{url}}">{{text}}</a></li>',
        'current' => '<li class="page-item active" aria-current="page"><span class="page-link">{{text}}</span></li>'
      ]
    ]) ?>

    <?php if ($this->Paginator->hasNext()): ?>
      <li class="page-item"><?= $this->Paginator->next('»', ['class' => 'page-link']) ?></li>
    <?php else: ?>
      <li class="page-item disabled"><span class="page-link">»</span></li>
    <?php endif; ?>
  </ul>
  <p class="text-muted small text-center mb-0">
    <?= $this->Paginator->counter('Strona {{page}} z {{pages}}, rekordy {{start}}–{{end}} z {{count}}') ?>
  </p>
</nav>

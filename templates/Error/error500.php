<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 * @var string $url
 * @var string $errorCode
 */
use Cake\Core\Configure;
use Cake\Error\Debugger;

$this->layout = 'error';

if (Configure::read('debug')) :
    $this->layout = 'dev_error';

    $this->assign('title', $message);
    $this->assign('templateName', 'error500.php');

    $this->start('file');
?>
<?php if ($error instanceof Error) : ?>
    <?php $file = $error->getFile() ?>
    <?php $line = $error->getLine() ?>
    <strong>Error in: </strong>
    <?= $this->Html->link(sprintf('%s, line %s', Debugger::trimPath($file), $line), Debugger::editorUrl($file, $line)); ?>
<?php endif; ?>
<?php
    echo $this->element('auto_table_warning');

    $this->end();
endif;
?>

<?php if (!Configure::read('debug')): ?>
<div class="text-center py-5">
    <div class="mb-4">
        <i class="bi bi-x-circle" style="font-size:4rem;color:#d9534f;"></i>
    </div>
    <h2 class="mb-3">Wystąpił błąd</h2>
    <p class="text-muted mb-4">Przepraszamy, coś poszło nie tak po naszej stronie. Pracujemy nad rozwiązaniem problemu.</p>
    <div class="alert alert-secondary d-inline-block">
        <small>Kod błędu: <strong><?= h($errorCode ?? '—') ?></strong></small>
    </div>
    <p class="mt-4 text-muted"><small>Jeśli problem się powtarza, skontaktuj się z administratorem i podaj powyższy kod błędu.</small></p>
    <div class="mt-4">
        <a href="javascript:history.back()" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Wróć</a>
        <a href="/" class="btn btn-primary"><i class="bi bi-house"></i> Strona główna</a>
    </div>
</div>
<?php else: ?>
<h2><?= __d('cake', 'An Internal Error Has Occurred.') ?></h2>
<p class="error">
    <strong><?= __d('cake', 'Error') ?>: </strong>
    <?= h($message) ?>
</p>
<p><small>Kod błędu: <code><?= h($errorCode ?? '—') ?></code></small></p>
<?php endif; ?>

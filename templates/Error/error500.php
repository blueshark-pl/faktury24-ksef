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
<div class="error-icon danger">
    <i class="ri-emotion-sad-line" style="color:#dc3545;"></i>
</div>
<h2 class="error-title mb-2">500</h2>
<p class="error-subtitle mb-4">Coś poszło nie tak po naszej stronie.<br>Pracujemy nad rozwiązaniem problemu.</p>
<div class="mb-3">
    <span class="error-code-badge"><?= h($errorCode ?? '—') ?></span>
</div>
<div class="error-actions mt-4 mb-3">
    <a href="/" class="btn btn-primary"><i class="ri-home-4-line me-1"></i> Strona główna</a>
</div>
<p class="error-footer mt-3 mb-0">Podaj kod błędu kontaktując się z&nbsp;administratorem.</p>
<?php else: ?>
<h2><?= __d('cake', 'An Internal Error Has Occurred.') ?></h2>
<p class="error">
    <strong><?= __d('cake', 'Error') ?>: </strong>
    <?= h($message) ?>
</p>
<p><small>Kod błędu: <code><?= h($errorCode ?? '—') ?></code></small></p>
<?php endif; ?>

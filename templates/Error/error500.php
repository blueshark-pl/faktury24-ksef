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
<div class="err-visual">
    <span class="err-code" style="color:#dc3545">500</span>
    <i class="ri-truck-line err-icon-overlay" style="color:#dc3545"></i>
</div>

<h1 class="err-title"><?= __('Awaria w drodze') ?></h1>
<p class="err-desc">
    <?= __('Coś poszło nie tak po naszej stronie — kierowca dał już znać dyspozytorowi. Spróbuj ponownie za chwilę lub wróć do panelu.') ?>
</p>

<?php if (!empty($errorCode)): ?>
<div class="err-chip" style="background:rgba(220,53,69,.08);border-color:rgba(220,53,69,.22);color:#dc3545">
    <i class="ri-barcode-line"></i>
    <?= __('Kod błędu') ?>: <strong style="margin-left:.2rem"><?= h($errorCode) ?></strong>
</div>
<?php endif; ?>

<div class="err-actions">
    <a href="javascript:history.back()" class="btn btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i> <?= __('Wróć') ?>
    </a>
    <a href="/" class="btn btn-primary-booklio">
        <i class="ri-truck-line me-1"></i> <?= __('Do panelu') ?>
    </a>
</div>

<p class="err-contact">
    <?= __('Jeśli problem się powtarza, napisz do nas:') ?>
    <a href="mailto:kontakt@booklio.pl">kontakt@booklio.pl</a>
    <?php if (!empty($errorCode)): ?><?= __('i podaj powyższy kod błędu') ?><?php endif; ?>.
</p>

<?php else: ?>
<h2><?= __d('cake', 'An Internal Error Has Occurred.') ?></h2>
<p class="error">
    <strong><?= __d('cake', 'Error') ?>: </strong>
    <?= h($message) ?>
</p>
<p><small>Kod błędu: <code><?= h($errorCode ?? '—') ?></code></small></p>
<?php endif; ?>

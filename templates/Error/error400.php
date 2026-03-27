<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 * @var string $url
 * @var string $errorCode
 */
use Cake\Core\Configure;

$this->layout = 'error';

if (Configure::read('debug')) :
    $this->layout = 'dev_error';

    $this->assign('title', $message);
    $this->assign('templateName', 'error400.php');

    $this->start('file');
    echo $this->element('auto_table_warning');
    $this->end();
endif;
?>

<?php if (!Configure::read('debug')): ?>
<div class="error-icon warn">
    <i class="ri-file-search-line" style="color:#f0ad4e;"></i>
</div>
<h2 class="error-title mb-2">404</h2>
<p class="error-subtitle mb-4">Strona, której szukasz, nie istnieje<br>lub nie masz do niej dostępu.</p>
<div class="mb-3">
    <span class="error-code-badge"><?= h($errorCode ?? '—') ?></span>
</div>
<div class="error-actions mt-4 mb-3">
    <a href="/" class="btn btn-primary"><i class="ri-home-4-line me-1"></i> Strona główna</a>
</div>
<p class="error-footer mt-3 mb-0">Podaj kod błędu kontaktując się z&nbsp;administratorem.</p>
<?php else: ?>
<h2><?= h($message) ?></h2>
<p class="error">
    <strong><?= __d('cake', 'Error') ?>: </strong>
    <?= __d('cake', 'The requested address {0} was not found on this server.', "<strong>'{$url}'</strong>") ?>
</p>
<p><small>Kod błędu: <code><?= h($errorCode ?? '—') ?></code></small></p>
<?php endif; ?>

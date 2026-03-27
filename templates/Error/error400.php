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
<div class="text-center py-5">
    <div class="mb-4">
        <i class="bi bi-exclamation-triangle" style="font-size:4rem;color:#f0ad4e;"></i>
    </div>
    <h2 class="mb-3">Strona nie została znaleziona</h2>
    <p class="text-muted mb-4">Żądany adres nie istnieje lub nie masz do niego dostępu.</p>
    <div class="alert alert-secondary d-inline-block">
        <small>Kod błędu: <strong><?= h($errorCode ?? '—') ?></strong></small>
    </div>
    <p class="mt-4 text-muted"><small>Jeśli problem się powtarza, skontaktuj się z nami: <a href="mailto:partnersc@partnersc.com">partnersc@partnersc.com</a> i podaj powyższy kod błędu.</small></p>
    <div class="mt-4">
        <a href="javascript:history.back()" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Wróć</a>
        <a href="/" class="btn btn-primary"><i class="bi bi-house"></i> Strona główna</a>
    </div>
</div>
<?php else: ?>
<h2><?= h($message) ?></h2>
<p class="error">
    <strong><?= __d('cake', 'Error') ?>: </strong>
    <?= __d('cake', 'The requested address {0} was not found on this server.', "<strong>'{$url}'</strong>") ?>
</p>
<p><small>Kod błędu: <code><?= h($errorCode ?? '—') ?></code></small></p>
<?php endif; ?>

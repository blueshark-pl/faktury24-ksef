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
<div class="err-visual">
    <span class="err-code">404</span>
    <i class="ri-road-map-line err-icon-overlay"></i>
</div>

<h1 class="err-title"><?= __('Trasa nieodnaleziona') ?></h1>
<p class="err-desc">
    <?= __('Tej strony nie ma na naszej mapie — być może zlecenie zostało już zrealizowane, a link wygasł. Sprawdź adres lub wróć do panelu.') ?>
</p>

<?php if (!empty($errorCode)): ?>
<div class="err-chip">
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
<h2><?= h($message) ?></h2>
<p class="error">
    <strong><?= __d('cake', 'Error') ?>: </strong>
    <?= __d('cake', 'The requested address {0} was not found on this server.', "<strong>'{$url}'</strong>") ?>
</p>
<p><small>Kod błędu: <code><?= h($errorCode ?? '—') ?></code></small></p>
<?php endif; ?>

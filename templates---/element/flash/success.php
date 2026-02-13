<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="alert svg-success alert-success alert-dismissible fade show custom-alert-icon shadow-sm" role="alert">
    <svg xmlns="http://www.w3.org/2000/svg" height="1.5rem" viewBox="0 0 24 24" width="1.5rem" fill="#198754">
        <path d="M0 0h24v24H0z" fill="none"/>
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10
                 10-4.48 10-10S17.52 2 12 2zm-2 14.59L6.41 
                 13l1.42-1.42L10 13.76l6.17-6.17 
                 1.41 1.41L10 16.59z"/>
    </svg>
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
        <i class="bi bi-x"></i>
    </button>
</div>

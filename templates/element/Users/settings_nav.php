<?php
/**
 * Wspólna nawigacja ustawień użytkownika: Konto / Zmień hasło / 2FA.
 * Używana w widokach CakeDC/Users oraz w app TwoFactor.
 *
 * @var \Cake\View\View $this
 */

$request = $this->getRequest();
$plugin = (string)($request->getParam('plugin') ?? '');
$controller = (string)($request->getParam('controller') ?? '');
$action = (string)($request->getParam('action') ?? '');

$isAccount = ($plugin === 'CakeDC/Users' && $controller === 'Users' && $action === 'profile');

// Traktujemy wszystkie akcje związane ze zmianą hasła jako "Zmień hasło"
$isSecurity = ($plugin === 'CakeDC/Users' && $controller === 'Users' && in_array($action, [
    'changePassword',
    'requestResetPassword',
    'resetPassword',
], true));

$isTwoFactor  = ($controller === 'TwoFactor');
$isApiTokens  = ($controller === 'ApiTokens');

$items = [
    [
        'label' => 'Konto',
        'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'profile'],
        'active' => $isAccount,
    ],
    [
        'label' => 'Zmień hasło',
        'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'changePassword'],
        'active' => $isSecurity,
    ],
    [
        'label' => 'Ustawienia 2FA',
        'url' => ['plugin' => false, 'controller' => 'TwoFactor', 'action' => 'index'],
        'active' => $isTwoFactor,
    ],
    [
        'label' => 'Tokeny API',
        'url' => ['plugin' => false, 'controller' => 'ApiTokens', 'action' => 'index'],
        'active' => $isApiTokens,
    ],
];
?>

<ul class="nav flex-column gap-1 nav-pills tab-style-7" role="tablist">
    <?php foreach ($items as $item): ?>
        <li class="nav-item me-0" role="presentation">
            <?php if (!empty($item['active'])): ?>
                <a class="nav-link d-inline-flex w-100 mb-0 bg-light active" aria-current="page">
                    <?= h($item['label']) ?>
                </a>
            <?php else: ?>
                <a class="nav-link bg-light d-inline-flex w-100 mb-0" href="<?= $this->Url->build($item['url']) ?>">
                    <?= h($item['label']) ?>
                </a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

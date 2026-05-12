<?php
/**
 * Email: social account validation (TEXT)
 * @var \Cake\Datasource\EntityInterface $user
 * @var \Cake\Datasource\EntityInterface $socialAccount
 * @var array|string $activationUrl
 */

use Cake\Core\Configure;
use Cake\Routing\Router;

$appName = trim((string)(Configure::read('App.name') ?? ''));
if ($appName === '') {
	$appName = 'Booklio TMS';
}
$firstName = (string)($user->get('first_name') ?? '');
$greeting  = $firstName !== '' ? __('Cześć {0},', $firstName) : __('Cześć,');
$link      = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;
$provider  = (string)($socialAccount->get('provider') ?? '');

echo "{$greeting}\n\n";
if ($provider !== '') {
    echo __('Otrzymaliśmy prośbę o potwierdzenie logowania kontem społecznościowym ({0}) w {1}.', $provider, $appName) . "\n";
} else {
    echo __('Otrzymaliśmy prośbę o potwierdzenie logowania kontem społecznościowym w {0}.', $appName) . "\n";
}
echo __('Jeśli to Ty, otwórz poniższy link, aby dokończyć logowanie:') . "\n\n";
echo "{$link}\n\n";
echo "---\n";
echo __('Jeśli to nie Ty próbowałeś się zalogować — zignoruj tę wiadomość.') . "\n";
echo __('Linki logowania są jednorazowe i przypisane do konta.') . "\n\n";
echo "© " . date('Y') . " {$appName}\n";

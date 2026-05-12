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
$greeting  = $firstName !== '' ? "Cześć {$firstName}," : 'Cześć,';
$link      = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;
$provider  = (string)($socialAccount->get('provider') ?? '');
$providerTxt = $provider !== '' ? " ({$provider})" : '';

echo "{$greeting}\n\n";
echo "Otrzymaliśmy prośbę o potwierdzenie logowania kontem społecznościowym{$providerTxt} w {$appName}.\n";
echo "Jeśli to Ty, otwórz poniższy link, aby dokończyć logowanie:\n\n";
echo "{$link}\n\n";
echo "---\n";
echo "Jeśli to nie Ty próbowałeś się zalogować — zignoruj tę wiadomość.\n";
echo "Linki logowania są jednorazowe i przypisane do konta.\n\n";
echo "© " . date('Y') . " {$appName}\n";

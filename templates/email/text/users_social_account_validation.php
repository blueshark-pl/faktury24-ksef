<?php
/**
 * Email: social account validation (TEXT)
 * @var \Cake\Datasource\EntityInterface $user
 * @var \Cake\Datasource\EntityInterface $socialAccount
 * @var array|string $activationUrl
 */

use Cake\Core\Configure;
use Cake\Routing\Router;

$appName = (string)(Configure::read('App.name') ?? '');
$firstName = (string)($user->get('first_name') ?? '');
$greeting = $firstName !== '' ? "Cześć {$firstName}," : 'Cześć,';
$link = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;
$provider = (string)($socialAccount->get('provider') ?? '');

$providerTxt = $provider !== '' ? " ({$provider})" : '';

echo $greeting . "\n\n";
echo "Otrzymaliśmy prośbę o potwierdzenie logowania kontem społecznościowym{$providerTxt} w {$appName}.\n";
echo "Jeśli to Ty, otwórz link, aby dokończyć logowanie:\n";
echo $link . "\n\n";
echo "Jeśli to nie Ty próbowałeś się zalogować, zignoruj tę wiadomość.\n";
echo "Wskazówka bezpieczeństwa: nie udostępniaj linków logowania osobom trzecim.\n";

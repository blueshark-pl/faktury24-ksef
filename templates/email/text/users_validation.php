<?php
/**
 * Email: validation (TEXT)
 * @var string $activationUrl
 * @var string|null $first_name
 */

use Cake\Core\Configure;
use Cake\Routing\Router;

$appName = (string)(Configure::read('App.name') ?? '');
$firstName = (string)($first_name ?? '');
$greeting = $firstName !== '' ? "Cześć {$firstName}," : 'Cześć,';
$link = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;

echo $greeting . "\n\n";
echo "Dziękujemy za rejestrację w {$appName}.\n";
echo "Aby dokończyć rejestrację i aktywować konto, otwórz link:\n";
echo $link . "\n\n";
echo "Po aktywacji uzyskasz dostęp do panelu i funkcji aplikacji.\n\n";
echo "Jeśli to nie Ty zakładałeś konto, zignoruj tę wiadomość.\n";
echo "Wskazówka bezpieczeństwa: nie udostępniaj linku aktywacyjnego osobom trzecim.\n";

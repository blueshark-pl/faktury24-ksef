<?php
/**
 * Email: reset password (TEXT)
 * @var string $activationUrl
 * @var string|null $first_name
 */

use Cake\Core\Configure;
use Cake\Routing\Router;

$appName = trim((string)(Configure::read('App.name') ?? ''));
if ($appName === '') {
	$appName = 'Booklio TMS';
}
$firstName = (string)($first_name ?? '');
$greeting  = $firstName !== '' ? "Cześć {$firstName}," : 'Cześć,';
$link      = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;

echo "{$greeting}\n\n";
echo "Otrzymaliśmy prośbę o reset hasła do Twojego konta w {$appName}.\n\n";
echo "Co dalej:\n";
echo "  1. Otwórz link poniżej\n";
echo "  2. Ustaw nowe hasło (min. 8 znaków)\n";
echo "  3. Zaloguj się ponownie\n\n";
echo "Link do ustawienia hasła:\n{$link}\n\n";
echo "---\n";
echo "Jeśli nie prosiłeś o zmianę hasła — zignoruj tę wiadomość. Twoje konto pozostanie bez zmian.\n";
echo "Nigdy nie udostępniaj tego linku ani danych logowania osobom trzecim.\n\n";
echo "© " . date('Y') . " {$appName}\n";

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
$greeting  = $firstName !== '' ? __('Cześć {0},', $firstName) : __('Cześć,');
$link      = is_array($activationUrl) ? Router::url($activationUrl, true) : (string)$activationUrl;

echo "{$greeting}\n\n";
echo __('Otrzymaliśmy prośbę o reset hasła do Twojego konta w {0}.', $appName) . "\n\n";
echo __('Co dalej:') . "\n";
echo '  1. ' . __('Otwórz link poniżej') . "\n";
echo '  2. ' . __('Ustaw nowe hasło (min. 8 znaków)') . "\n";
echo '  3. ' . __('Zaloguj się ponownie') . "\n\n";
echo __('Link do ustawienia hasła:') . "\n{$link}\n\n";
echo "---\n";
echo __('Jeśli nie prosiłeś o zmianę hasła — zignoruj tę wiadomość. Twoje konto pozostanie bez zmian.') . "\n";
echo __('Nigdy nie udostępniaj tego linku ani danych logowania osobom trzecim.') . "\n\n";
echo "© " . date('Y') . " {$appName}\n";

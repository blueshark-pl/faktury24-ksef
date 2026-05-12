<?php
/**
 * Email: one-time token (TEXT)
 * @var \Cake\Datasource\EntityInterface $user
 * @var string $loginLink
 * @var string $token
 */

use Cake\Core\Configure;

$appName = trim((string)(Configure::read('App.name') ?? ''));
if ($appName === '') {
	$appName = 'Booklio TMS';
}
$firstName = (string)($user->get('first_name') ?? '');
$greeting  = $firstName !== '' ? "Cześć {$firstName}," : 'Cześć,';

echo "{$greeting}\n\n";
echo "Oto jednorazowy link do logowania w {$appName} — bez podawania hasła.\n";
echo "Link działa tylko raz i jest przypisany do Twojego konta.\n\n";
echo "Link logowania:\n{$loginLink}\n\n";
echo "---\n";
echo "Jeśli to nie Ty prosiłeś o link logowania — zignoruj tę wiadomość.\n";
echo "Nie udostępniaj tego linku osobom trzecim.\n\n";
echo "© " . date('Y') . " {$appName}\n";

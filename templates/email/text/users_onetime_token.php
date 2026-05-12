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
$greeting  = $firstName !== '' ? __('Cześć {0},', $firstName) : __('Cześć,');

echo "{$greeting}\n\n";
echo __('Oto jednorazowy link do logowania w {0} — bez podawania hasła.', $appName) . "\n";
echo __('Link działa tylko raz i jest przypisany do Twojego konta. Po wygaśnięciu poproś o nowy z ekranu logowania.') . "\n\n";
echo __('Link logowania:') . "\n{$loginLink}\n\n";
echo "---\n";
echo __('Jeśli to nie Ty prosiłeś o link logowania — zignoruj tę wiadomość.') . "\n";
echo __('Nigdy nie udostępniaj tego linku ani danych logowania osobom trzecim.') . "\n\n";
echo "© " . date('Y') . " {$appName}\n";

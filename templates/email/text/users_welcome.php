<?php
/**
 * Email: welcome (TEXT)
 *
 * @var \Cake\Datasource\EntityInterface $user
 * @var string $firstName
 * @var string $role
 * @var string $resetUrl
 * @var string $lang
 */

use Cake\Core\Configure;

$appName = trim((string)(Configure::read('App.name') ?? ''));
if ($appName === '') {
    $appName = 'Booklio TMS';
}
$greeting = $firstName !== '' ? __('Cześć {0},', $firstName) : __('Cześć,');

$roleLabels = [
    'admin'              => __('Administrator'),
    'user'               => __('Pracownik (spedytor)'),
    'client'             => __('Klient portalu'),
    'asystent_spedytora' => __('Asystent spedytora'),
    'mlodszy_spedytor'   => __('Młodszy spedytor'),
    'spedycja_manager'   => __('Kierownik Spedycji'),
    'sales_manager'      => __('Kierownik Działu Handlowego'),
];
$roleLabel = $roleLabels[$role] ?? __('Pracownik (spedytor)');

echo "{$greeting}\n\n";
echo __('Administrator założył dla Ciebie konto w {0} — systemie zarządzania transportem.', $appName) . "\n\n";
echo __('Twoja rola w systemie') . ": {$roleLabel}\n\n";
echo __('Polityka bezpieczeństwa') . ":\n";
echo __('Przed pierwszym logowaniem musisz ustawić własne hasło. Hasło utworzone przez administratora jest tymczasowe i wygaśnie po ustanowieniu nowego.') . "\n\n";
echo __('Ustaw hasło klikając w link') . ":\n";
echo "{$resetUrl}\n\n";
echo __('Login (e-mail)') . ': ' . $user->get('email') . "\n\n";
echo "---\n";
echo __('Link do ustawienia hasła wygaśnie po 7 dniach.') . "\n";
echo __('Jeśli to nie Ty powinieneś otrzymać tę wiadomość, zignoruj ją lub poinformuj administratora.') . "\n\n";
echo "© " . date('Y') . " {$appName}\n";

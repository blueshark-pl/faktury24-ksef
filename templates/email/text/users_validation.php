<?php
/**
 * Email: validation (TEXT)
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
echo "Dziękujemy za rejestrację w {$appName}.\n";
echo "Aby dokończyć proces i aktywować konto, otwórz poniższy link:\n\n";
echo "{$link}\n\n";
echo "Po aktywacji zyskasz dostęp do panelu — zleceń, kontrahentów, faktur i pełnej historii operacji.\n\n";
echo "---\n";
echo "Jeśli to nie Ty zakładałeś konto — zignoruj tę wiadomość.\n";
echo "Link aktywacyjny jest unikalny — nie udostępniaj go osobom trzecim.\n\n";
echo "© " . date('Y') . " {$appName}\n";

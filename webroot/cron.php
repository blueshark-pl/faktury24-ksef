<?php
/**
 * FALA extras: Standalone webhook script dla cronu bez CLI.
 *
 * Bootstrap Cake bez middleware stack (Authentication/Authorization/CSRF),
 * bezposrednio wykonuje App\Command\* i zwraca plain output.
 *
 * Uzycie w cron:
 *   *5 * * * *  curl -s "https://booklio.pl/cron.php?cmd=crm_email_poll&token=XXX"
 *
 * Query params:
 *   cmd    - crm_email_poll | crm_workflow_run | crm_tasks_digest | alerts
 *   token  - Configure Crm.cronToken (musi zgadzac sie constant-time)
 *   force  - 1 (optional) - przekazuje --force do command
 *   dry    - 1 (optional) - przekazuje --dry
 *
 * Bezpieczenstwo: token wymagany, tylko GET, whitelist commands.
 * Bypassuje Authentication/Authorization/CSRF - ten skrypt jest DEDYKOWANY dla cronu.
 */
declare(strict_types=1);

@set_time_limit(600);
@ini_set('max_execution_time', '600');

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

// Ochrona przed nie-GET
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo "Method Not Allowed - use GET\n";
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap Cake config (bez middleware, bez routes matching)
try {
    $app = new App\Application(dirname(__DIR__) . '/config');
    // Wywoluje bootstrap() ktore laduje app_local.php, plugins, itd.
    $app->bootstrap();
} catch (\Throwable $e) {
    http_response_code(500);
    echo "Bootstrap failed: " . $e->getMessage() . "\n";
    exit;
}

// Token check
$expectedToken = trim((string)\Cake\Core\Configure::read('Crm.cronToken'));
$providedToken = trim((string)($_GET['token'] ?? ''));
if ($expectedToken === '') {
    http_response_code(500);
    echo "Configure 'Crm.cronToken' not set in app_local.php\n";
    exit;
}
if (!hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    echo "Invalid token\n";
    exit;
}

// Whitelist commands
$command = trim((string)($_GET['cmd'] ?? ''));
$classMap = [
    'crm_email_poll'    => \App\Command\CrmEmailPollCommand::class,
    'crm_workflow_run'  => \App\Command\CrmWorkflowRunCommand::class,
    'crm_tasks_digest'  => \App\Command\CrmTasksDigestCommand::class,
    'alerts'            => \App\Command\AlertsCommand::class,
];
if (!isset($classMap[$command])) {
    http_response_code(400);
    echo "Command '{$command}' not allowed. Allowed: " . implode(', ', array_keys($classMap)) . "\n";
    exit;
}
$class = $classMap[$command];
if (!class_exists($class)) {
    http_response_code(500);
    echo "Command class not found: {$class}\n";
    exit;
}

// Optional opts
$options = [];
if (($_GET['force'] ?? '') === '1') $options['force'] = true;
if (($_GET['dry'] ?? '') === '1') $options['dry'] = true;

echo "=== CRON WEBHOOK: {$command} ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $cmd = new $class();
    $stubOutput = new \Cake\Console\TestSuite\StubConsoleOutput();
    $stubErr    = new \Cake\Console\TestSuite\StubConsoleOutput();
    $io = new \Cake\Console\ConsoleIo(
        $stubOutput,
        $stubErr,
        new \Cake\Console\TestSuite\StubConsoleInput([])
    );
    $args = new \Cake\Console\Arguments([], $options, []);
    $exit = $cmd->execute($args, $io);
    $lines = array_merge($stubOutput->messages(), $stubErr->messages());
    echo implode("\n", $lines);
    echo "\n\nEXIT: {$exit}\n";
    echo "Finished: " . date('Y-m-d H:i:s') . "\n";
} catch (\Throwable $e) {
    http_response_code(500);
    echo "EXCEPTION: " . $e->getMessage() . "\n\n";
    echo $e->getTraceAsString() . "\n";
}

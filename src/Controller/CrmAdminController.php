<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Console\ConsoleIo;
use Cake\Console\Arguments;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\TestSuite\StubConsoleInput;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Filesystem\Folder;
use Migrations\Migrations;

/**
 * CRM Admin Tools - webowe uruchamianie migracji / cron / cache clear.
 * Zabezpieczone auth (dostep tylko dla rol manager/admin).
 *
 * Endpointy:
 *   GET  /crm/admin/tools           - HTML page z buttonami
 *   POST /crm/admin/migrate         - uruchom migracje bazy
 *   POST /crm/admin/migration-status - lista migracji up/down
 *   POST /crm/admin/clear-cache     - wyczysc tmp/cache i tmp/sessions
 *   POST /crm/admin/poll-emails     - uruchom crm_email_poll manualnie
 *   POST /crm/admin/run-cron/{name} - uruchom dowolny cron command
 */
class CrmAdminController extends AppController
{
    public function tools(): void
    {
        $this->request->allowMethod(['get']);

        // Info o aktualnie zainstalowanym kodzie
        $gitInfo = $this->getGitInfo();
        $this->set('gitInfo', $gitInfo);
    }

    /**
     * GET /crm/admin/file-check - diagnostyka aktualnej wersji CrmEmailPollCommand.php
     */
    public function fileCheck(): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== FILE CHECK: CrmEmailPollCommand.php ===\n\n";

        $file = ROOT . DS . 'src' . DS . 'Command' . DS . 'CrmEmailPollCommand.php';
        $out .= "Path: {$file}\n";
        $out .= "Exists: " . (file_exists($file) ? 'TAK' : 'NIE') . "\n";
        if (!file_exists($file)) {
            $this->set('title', 'File check');
            $this->set('output', $out);
            $this->render('output');
            return;
        }
        $content = file_get_contents($file);
        $out .= "Size: " . number_format(strlen($content)) . " bytes\n";
        $out .= "Modified: " . date('Y-m-d H:i:s', filemtime($file)) . "\n";
        $out .= "SHA-256: " . hash('sha256', $content) . "\n\n";

        // Sprawdz WERSJE - nowa ma warning zamiast error
        $hasOldVersion = strpos($content, "PHP IMAP extension nie jest zainstalowane. Zainstaluj php-imap.") !== false;
        $hasNewVersion = strpos($content, "PHP IMAP extension nie jest dostepne - konta auth_type=imap zostana pominiete") !== false;
        $hasGmailBranch = strpos($content, 'syncGmailOauth') !== false;

        $out .= "WERSJA:\n";
        $out .= "  Stara (return error): " . ($hasOldVersion ? '<span style="color:red;">TAK</span>' : 'NIE') . "\n";
        $out .= "  Nowa (warning+continue): " . ($hasNewVersion ? '<span style="color:green;">TAK</span>' : '<span style="color:red;">NIE - STARY PLIK!</span>') . "\n";
        $out .= "  syncGmailOauth (FALA 13): " . ($hasGmailBranch ? '<span style="color:green;">TAK</span>' : '<span style="color:red;">NIE - STARY PLIK!</span>') . "\n\n";

        // Sekcja execute() - pokaz pierwsze 20 linii
        preg_match('/public function execute\(.*?\).*?\{(.*?)^\s{4}\}/sm', $content, $m);
        $execBody = $m[1] ?? '';
        $execFirstLines = implode("\n", array_slice(explode("\n", $execBody), 0, 20));
        $out .= "=== execute() first 20 lines ===\n";
        $out .= $execFirstLines . "\n\n";

        // OPcache status
        $out .= "=== OPCACHE ===\n";
        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if ($status) {
                $out .= "Enabled: " . ($status['opcache_enabled'] ?? '?') . "\n";
                $out .= "Cached scripts: " . ($status['opcache_statistics']['num_cached_scripts'] ?? '?') . "\n";
                $scripts = @opcache_get_status(true)['scripts'] ?? [];
                if (isset($scripts[$file])) {
                    $s = $scripts[$file];
                    $out .= "Ten plik W OPCACHE:\n";
                    $out .= "  timestamp: " . date('Y-m-d H:i:s', $s['timestamp'] ?? 0) . "\n";
                    $out .= "  hits: " . ($s['hits'] ?? '?') . "\n";
                } else {
                    $out .= "Ten plik NIE jest w opcache\n";
                }
            } else {
                $out .= "opcache_get_status() zwrocil false (moze zablokowane)\n";
            }
        } else {
            $out .= "opcache_get_status() niedostepne\n";
        }

        if ($hasOldVersion || !$hasNewVersion) {
            $out .= "\n<strong style='color:red;'>PROBLEM: plik na serwerze to STARA wersja!</strong>\n\n";
            $out .= "MOZLIWE PRZYCZYNY:\n";
            $out .= " 1. Git pull nie zdeployowal tego pliku (mimo ze pokazuje 'juz najnowszy')\n";
            $out .= " 2. Wgrales pliki recznie ale ten sie NIE zaladowal (moze conflict, moze skip)\n";
            $out .= " 3. Filesystem cache trzyma stara wersje\n\n";
            $out .= "FIX:\n";
            $out .= " - Wgraj plik ROZNICE recznie przez FTP/SFTP na serwer\n";
            $out .= " - Skopiuj z /src/Command/CrmEmailPollCommand.php z lokalu do serwera\n";
            $out .= " - Potem: /crm/admin/nuclear-clear (usunie ALL cache + regeneruje autoload)\n";
        }

        $this->set('title', 'File check: CrmEmailPollCommand.php');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * POST /crm/admin/nuclear-clear - brute force cache reset (opcache + autoload)
     */
    public function nuclearClear(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== NUCLEAR CACHE CLEAR ===\n\n";

        try {
            // 1. Cake cache
            \Cake\Cache\Cache::clearAll();
            $out .= "✓ Cake Cache::clearAll()\n";

            // 2. Wszystkie pliki w tmp/ rekursywnie
            $tmpDir = ROOT . DS . 'tmp';
            $count = 0;
            if (is_dir($tmpDir)) {
                $iter = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iter as $f) {
                    $basename = $f->getBasename();
                    if ($basename === 'empty' || $basename === '.htaccess') continue;
                    if ($f->isFile()) {
                        @unlink($f->getPathname());
                        $count++;
                    }
                }
            }
            $out .= "✓ tmp/**: {$count} plikow usunieto (rekursywnie)\n";

            // 3. OPcache
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $out .= "✓ opcache_reset()\n";
            }
            if (function_exists('opcache_invalidate')) {
                $file = ROOT . DS . 'src' . DS . 'Command' . DS . 'CrmEmailPollCommand.php';
                if (file_exists($file)) {
                    opcache_invalidate($file, true);
                    $out .= "✓ opcache_invalidate(CrmEmailPollCommand.php, force=true)\n";
                }
            }

            // 4. Touch wszystkie .php w src/ zeby OPcache widzial jako 'zmienione'
            $srcDir = ROOT . DS . 'src';
            $touched = 0;
            if (is_dir($srcDir)) {
                $iter = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iter as $f) {
                    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                        @touch($f->getPathname());
                        $touched++;
                    }
                }
            }
            $out .= "✓ touch: {$touched} plikow .php w src/ (OPcache invalidation trigger)\n";

            // 5. Composer autoload regeneracja - via shell
            $composerCmd = "cd " . escapeshellarg(ROOT) . " && composer dump-autoload -o 2>&1";
            $out .= "\n> {$composerCmd}\n";
            $composerOut = @shell_exec($composerCmd);
            $out .= ($composerOut ?: "(brak output - composer moze niedostepny)") . "\n";

            $out .= "\n✓ ZAKONCZONO. Sprobuj teraz /crm/admin/file-check zeby zobaczyc czy plik jest zaktualizowany.\n";
            $out .= "Jesli plik dalej stary - trzeba go recznie wgrac przez FTP.\n";
        } catch (\Throwable $e) {
            $out .= "❌ EXCEPTION: " . $e->getMessage() . "\n";
        }

        $this->set('title', 'Nuclear clear');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * POST /crm/admin/git-pull - odpali git pull na serwerze
     */
    public function gitPull(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== GIT PULL ===\n\n";

        $rootDir = ROOT;
        $gitBinary = trim((string)shell_exec('which git 2>&1')) ?: 'git';

        // Sprawdz czy .git istnieje
        if (!is_dir($rootDir . DS . '.git')) {
            $out .= "❌ Brak katalogu .git w " . $rootDir . "\n";
            $out .= "Ten projekt nie jest git repo albo git dir jest gdzie indziej.\n";
        } else {
            // Aktualny commit przed pull
            $currentBefore = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} rev-parse HEAD 2>&1"));
            $out .= "Commit przed pull: {$currentBefore}\n\n";

            // Git pull
            $cmd = "cd " . escapeshellarg($rootDir) . " && {$gitBinary} pull 2>&1";
            $out .= "> {$cmd}\n\n";
            $pullOutput = (string)shell_exec($cmd);
            $out .= $pullOutput . "\n";

            // Commit po pull
            $currentAfter = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} rev-parse HEAD 2>&1"));
            $out .= "\nCommit po pull:   {$currentAfter}\n";

            if ($currentBefore !== $currentAfter) {
                $out .= "\n✓ Zaktualizowano! Teraz kliknij 'Clear cache' zeby PHP przeladowal klasy.\n";
                $out .= "  Bez clear cache OPcache dalej trzyma stary kod w pamieci.\n";
            } else {
                $out .= "\n= Brak zmian - juz miales najnowszy commit.\n";
            }
        }

        $this->set('title', 'Git pull');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * Zwraca info o aktualnym commit dla wyswietlania.
     */
    private function getGitInfo(): array
    {
        $rootDir = ROOT;
        if (!is_dir($rootDir . DS . '.git')) return ['available' => false];

        $gitBinary = trim((string)shell_exec('which git 2>&1')) ?: 'git';
        $commit = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} rev-parse --short HEAD 2>&1"));
        $branch = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} rev-parse --abbrev-ref HEAD 2>&1"));
        $date   = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} log -1 --format='%ci' 2>&1"));
        $msg    = trim((string)shell_exec("cd " . escapeshellarg($rootDir) . " && {$gitBinary} log -1 --format='%s' 2>&1"));

        return [
            'available' => true,
            'commit'    => $commit,
            'branch'    => $branch,
            'date'      => $date,
            'message'   => $msg,
        ];
    }

    /**
     * POST /crm/admin/migrate - odpali `bin/cake migrations migrate`
     */
    public function migrate(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $this->viewBuilder()->setLayout('ajax');

        $out = "=== MIGRATIONS MIGRATE ===\n\n";
        try {
            $migrations = new Migrations();
            $ok = $migrations->migrate(['connection' => 'default']);
            $out .= $ok ? "OK - wszystkie migracje uruchomione\n\n" : "Migracje uruchomione (partial)\n\n";
            $status = $migrations->status(['connection' => 'default']);
            foreach ($status as $m) {
                $mark = ($m['status'] ?? '') === 'up' ? '✓' : '✗';
                $out .= sprintf("  %s  %s  %s\n",
                    $mark,
                    str_pad((string)($m['id'] ?? ''), 20),
                    (string)($m['name'] ?? '')
                );
            }
        } catch (\Throwable $e) {
            $out .= "❌ BLAD: " . $e->getMessage() . "\n\n";
            $out .= $e->getTraceAsString() . "\n";
        }
        $this->set('title', 'Migracje');
        $this->set('output', $out);
        $this->render('output');
    }

    public function migrationStatus(): void
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== MIGRATIONS STATUS ===\n\n";
        try {
            $migrations = new Migrations();
            $status = $migrations->status(['connection' => 'default']);
            $pending = 0;
            foreach ($status as $m) {
                $s = ($m['status'] ?? '');
                $mark = $s === 'up' ? '✓' : '✗';
                if ($s !== 'up') $pending++;
                $out .= sprintf("  %s  %s  %s\n",
                    $mark,
                    str_pad((string)($m['id'] ?? ''), 20),
                    (string)($m['name'] ?? '')
                );
            }
            $out .= "\nPENDING: {$pending}\n";
            if ($pending > 0) $out .= "\n→ Uruchom /crm/admin/migrate zeby dodac brakujace tabele.\n";
        } catch (\Throwable $e) {
            $out .= "❌ BLAD: " . $e->getMessage() . "\n";
        }
        $this->set('title', 'Migration status');
        $this->set('output', $out);
        $this->render('output');
    }

    public function clearCache(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== CLEAR CACHE ===\n\n";
        try {
            // Cake cache
            \Cake\Cache\Cache::clearAll();
            $out .= "✓ Cake\\Cache::clearAll()\n";

            // tmp/cache/*
            foreach (['tmp/cache/models', 'tmp/cache/persistent', 'tmp/cache/views'] as $dir) {
                $path = ROOT . DS . $dir;
                if (is_dir($path)) {
                    $files = glob($path . DS . '*');
                    $count = 0;
                    foreach ($files ?: [] as $f) {
                        if (is_file($f) && basename($f) !== 'empty') {
                            @unlink($f);
                            $count++;
                        }
                    }
                    $out .= "✓ {$dir}: {$count} plikow usunieto\n";
                }
            }

            // OPcache reset
            if (function_exists('opcache_reset')) {
                opcache_reset();
                $out .= "✓ opcache_reset()\n";
            } else {
                $out .= "⚠ opcache_reset() niedostepny\n";
            }

            // ORM schema cache
            try {
                $connection = \Cake\Datasource\ConnectionManager::get('default');
                $connection->getSchemaCollection()->getCacher()->clear();
                $out .= "✓ ORM schema cache clear\n";
            } catch (\Throwable $e) {}
        } catch (\Throwable $e) {
            $out .= "❌ BLAD: " . $e->getMessage() . "\n";
        }
        $this->set('title', 'Cache clear');
        $this->set('output', $out);
        $this->render('output');
    }

    /**
     * POST /crm/admin/poll-emails - uruchom crm_email_poll (bez dry)
     */
    public function pollEmails(): void
    {
        $this->runCommand('crm_email_poll');
    }

    /**
     * POST /crm/admin/run-cron/{name}?force=1&dry=1
     */
    public function runCron(string $name): void
    {
        $allowed = ['crm_email_poll', 'crm_workflow_run', 'crm_tasks_digest', 'alerts'];
        if (!in_array($name, $allowed, true)) {
            $this->Flash->error('Command niedozwolony');
            $this->redirect(['action' => 'tools']);
            return;
        }
        // Zbieramy opcje z query string
        $options = [];
        if ($this->request->getQuery('force') === '1') $options['force'] = true;
        if ($this->request->getQuery('dry') === '1')   $options['dry'] = true;
        $this->runCommand($name, $options);
    }

    private function runCommand(string $commandName, array $options = []): void
    {
        $this->viewBuilder()->setLayout('ajax');
        $out = "=== CRON: bin/cake {$commandName} ===\n\n";

        try {
            $classMap = [
                'crm_email_poll'    => \App\Command\CrmEmailPollCommand::class,
                'crm_workflow_run'  => \App\Command\CrmWorkflowRunCommand::class,
                'crm_tasks_digest'  => \App\Command\CrmTasksDigestCommand::class,
                'alerts'            => \App\Command\AlertsCommand::class,
            ];
            $class = $classMap[$commandName] ?? null;
            if (!$class || !class_exists($class)) {
                throw new \RuntimeException("Command class nie istnieje: {$commandName}");
            }

            $command = new $class();
            $stubOutput = new StubConsoleOutput();
            $stubErr = new StubConsoleOutput();
            $io = new ConsoleIo($stubOutput, $stubErr, new StubConsoleInput([]));

            // Zbuduj ConsoleOptionParser + Arguments
            $parser = new ConsoleOptionParser($commandName);
            if (method_exists($command, 'buildOptionParser')) {
                $refl = new \ReflectionClass($command);
                $method = $refl->getMethod('buildOptionParser');
                $method->setAccessible(true);
                $parser = $method->invoke($command, $parser);
            }
            // Zbuduj Arguments z opcjami (np. force, dry)
            $args = new Arguments([], $options, []);

            $exitCode = $command->execute($args, $io);
            $lines = array_merge($stubOutput->messages(), $stubErr->messages());
            $out .= implode("\n", $lines);
            $out .= "\n\nEXIT CODE: {$exitCode}\n";
        } catch (\Throwable $e) {
            $out .= "❌ EXCEPTION: " . $e->getMessage() . "\n\n";
            $out .= $e->getTraceAsString() . "\n";
        }

        $this->set('title', "Cron: {$commandName}");
        $this->set('output', $out);
        $this->render('output');
    }
}

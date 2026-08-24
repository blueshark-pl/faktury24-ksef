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
     * POST /crm/admin/run-cron/{name}
     */
    public function runCron(string $name): void
    {
        // Whitelist commands
        $allowed = ['crm_email_poll', 'crm_workflow_run', 'crm_tasks_digest', 'alerts'];
        if (!in_array($name, $allowed, true)) {
            $this->Flash->error('Command niedozwolony');
            $this->redirect(['action' => 'tools']);
            return;
        }
        $this->runCommand($name);
    }

    private function runCommand(string $commandName): void
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
            $args = new Arguments([], [], []);

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

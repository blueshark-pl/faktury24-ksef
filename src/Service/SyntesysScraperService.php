<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Serwis scrapowania danych kredytu kupieckiego z Syntesys (Allianz Trade).
 *
 * Wywołuje skrypt Node.js bin/syntesys-scraper.js przez proc_open
 * i zapisuje pobrane dane do tabeli credit_checks.
 *
 * Konfiguracja (config/app_local.php):
 *   'Syntesys' => [
 *       'username'    => 'login@firma.pl',
 *       'password'    => 'haslo',
 *       'node_path'   => 'node',   // ścieżka do node (domyślnie `node`)
 *   ],
 */
class SyntesysScraperService
{
    private const SCRIPT_PATH = ROOT . DS . 'bin' . DS . 'syntesys-scraper.js';
    private const TIMEOUT     = 120; // sekund

    /**
     * Uruchamia scraper i zapisuje wyniki do DB.
     *
     * @param string $list  'all' | 'done' | 'waiting' | 'no-advice' | 'error'
     * @return array{success: bool, message: string, inserted: int, updated: int, errors: int}
     */
    public function sync(string $list = 'all'): array
    {
        $username = (string)(Configure::read('Syntesys.username') ?? '');
        $password = (string)(Configure::read('Syntesys.password') ?? '');
        $nodePath = (string)(Configure::read('Syntesys.node_path') ?? 'node');

        if ($username === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Brak konfiguracji Syntesys.username / Syntesys.password w config/app_local.php',
                'inserted' => 0, 'updated' => 0, 'errors' => 0,
            ];
        }

        if (!file_exists(self::SCRIPT_PATH)) {
            return [
                'success' => false,
                'message' => 'Skrypt scrapera nie istnieje: ' . self::SCRIPT_PATH,
                'inserted' => 0, 'updated' => 0, 'errors' => 0,
            ];
        }

        // Zbuduj komendę
        $cmd = escapeshellcmd($nodePath) . ' ' . escapeshellarg(self::SCRIPT_PATH) . ' ' . escapeshellarg($list);

        Log::info("SyntesysScraperService: uruchamiam scraper: {$cmd}", ['scope' => 'syntesys']);

        // Zmienne środowiskowe z credentials (nie podajemy przez CLI args — bezpieczniejsze)
        $env = array_merge(getenv() ?: [], [
            'SYNTESYS_USER' => $username,
            'SYNTESYS_PASS' => $password,
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $proc = proc_open($cmd, $descriptors, $pipes, ROOT, $env);
        if (!is_resource($proc)) {
            return [
                'success' => false,
                'message' => 'Nie udało się uruchomić procesu Node.js',
                'inserted' => 0, 'updated' => 0, 'errors' => 0,
            ];
        }

        fclose($pipes[0]);

        // Czytaj output z timeoutem
        $stdout = '';
        $stderr = '';
        $start  = time();

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $chunk  = fread($pipes[1], 8192);
            $errChunk = fread($pipes[2], 8192);
            if ($chunk !== false)    $stdout  .= $chunk;
            if ($errChunk !== false) $stderr  .= $errChunk;

            $status = proc_get_status($proc);
            if (!$status['running']) break;

            if (time() - $start > self::TIMEOUT) {
                proc_terminate($proc);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                Log::warning('SyntesysScraperService: timeout po ' . self::TIMEOUT . 's', ['scope' => 'syntesys']);
                return [
                    'success' => false,
                    'message' => 'Timeout scrapera po ' . self::TIMEOUT . ' sekundach',
                    'inserted' => 0, 'updated' => 0, 'errors' => 0,
                ];
            }

            usleep(100000); // 100ms
        }

        // Odczytaj resztę buforów
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($stderr !== '') {
            Log::debug('SyntesysScraperService stderr: ' . $stderr, ['scope' => 'syntesys']);
        }

        if ($stdout === '') {
            Log::error('SyntesysScraperService: pusty stdout (exit=' . $exitCode . ')', ['scope' => 'syntesys']);
            return [
                'success' => false,
                'message' => 'Scraper nie zwrócił danych (exit code: ' . $exitCode . ')',
                'inserted' => 0, 'updated' => 0, 'errors' => 0,
            ];
        }

        $result = json_decode($stdout, true);
        if (!is_array($result)) {
            Log::error('SyntesysScraperService: nieprawidłowy JSON: ' . substr($stdout, 0, 500), ['scope' => 'syntesys']);
            return [
                'success' => false,
                'message' => 'Scraper zwrócił nieprawidłowy JSON',
                'inserted' => 0, 'updated' => 0, 'errors' => 0,
            ];
        }

        if (!($result['success'] ?? false)) {
            $errMsg = $result['error'] ?? 'Nieznany błąd scrapera';
            Log::error('SyntesysScraperService: błąd scrapera: ' . $errMsg, ['scope' => 'syntesys']);
            return [
                'success' => false,
                'message' => $errMsg,
                'inserted' => 0, 'updated' => 0, 'errors' => 0,
            ];
        }

        // Zapisz do bazy
        $data         = $result['data'] ?? [];
        $CreditChecks = TableRegistry::getTableLocator()->get('CreditChecks');

        $totalInserted = 0;
        $totalUpdated  = 0;
        $totalErrors   = 0;

        foreach ($data as $listStatus => $items) {
            if (!is_array($items)) continue;
            $stats = $CreditChecks->upsertFromApi($items, $listStatus);
            $totalInserted += $stats['inserted'];
            $totalUpdated  += $stats['updated'];
            $totalErrors   += $stats['errors'];
        }

        Log::info(
            sprintf(
                'SyntesysScraperService: sync zakończony — inserted=%d, updated=%d, errors=%d',
                $totalInserted, $totalUpdated, $totalErrors
            ),
            ['scope' => 'syntesys']
        );

        return [
            'success'  => true,
            'message'  => sprintf(
                'Synchronizacja zakończona: %d nowych, %d zaktualizowanych, %d błędów',
                $totalInserted, $totalUpdated, $totalErrors
            ),
            'inserted' => $totalInserted,
            'updated'  => $totalUpdated,
            'errors'   => $totalErrors,
        ];
    }
}

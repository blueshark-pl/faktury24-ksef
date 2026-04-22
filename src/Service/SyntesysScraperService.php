<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Serwis kredytu kupieckiego (Allianz Trade / Syntesys).
 *
 * Wywołuje standalone mikroserwis Node.js (services/syntesys/) przez HTTP
 * i zapisuje pobrane dane do tabeli credit_checks.
 *
 * Konfiguracja (config/app_local.php):
 *   'Syntesys' => [
 *       'service_url' => 'http://localhost:3400',  // URL mikroserwisu
 *       'api_key'     => 'sekretny-klucz',          // musi pasować do env API_KEY
 *   ],
 */
class SyntesysScraperService
{
    private const TIMEOUT = 120; // sekund na wywołanie HTTP

    /**
     * Pobiera dane z mikroserwisu i zapisuje do bazy MySQL.
     *
     * @param string $list  'all' | 'done' | 'waiting' | 'no-advice' | 'error'
     * @return array{success: bool, message: string, inserted: int, updated: int, errors: int}
     */
    public function sync(string $list = 'all'): array
    {
        $serviceUrl = rtrim((string)(Configure::read('Syntesys.service_url') ?? ''), '/');
        $apiKey     = (string)(Configure::read('Syntesys.api_key')     ?? '');

        if ($serviceUrl === '') {
            return $this->err('Brak konfiguracji Syntesys.service_url w config/app_local.php');
        }

        $url = $serviceUrl . '/fetch';

        Log::info("SyntesysScraperService: POST {$url} list={$list}", ['scope' => 'syntesys']);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['list' => $list]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => array_filter([
                'Content-Type: application/json',
                'Accept: application/json',
                $apiKey !== '' ? "X-Api-Key: {$apiKey}" : null,
            ]),
        ]);

        $body     = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            Log::error("SyntesysScraperService: cURL error: {$curlErr}", ['scope' => 'syntesys']);
            return $this->err("Błąd połączenia z mikroserwisem: {$curlErr}");
        }

        if ($httpCode === 409) {
            return $this->err('Synchronizacja już trwa — spróbuj za chwilę');
        }

        if ($httpCode !== 200) {
            Log::error("SyntesysScraperService: HTTP {$httpCode}", ['scope' => 'syntesys']);
            return $this->err("Mikroserwis zwrócił HTTP {$httpCode}");
        }

        $result = json_decode((string)$body, true);
        if (!is_array($result)) {
            Log::error('SyntesysScraperService: nieprawidłowy JSON', ['scope' => 'syntesys']);
            return $this->err('Mikroserwis zwrócił nieprawidłowy JSON');
        }

        if (!($result['success'] ?? false)) {
            $errMsg = $result['error'] ?? 'Nieznany błąd mikroserwisu';
            Log::error("SyntesysScraperService: błąd: {$errMsg}", ['scope' => 'syntesys']);
            return $this->err($errMsg);
        }

        // Zapisz do MySQL via ORM
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

    /** @return array{success: bool, message: string, inserted: int, updated: int, errors: int} */
    private function err(string $msg): array
    {
        return ['success' => false, 'message' => $msg, 'inserted' => 0, 'updated' => 0, 'errors' => 0];
    }

    /**
     * Sprawdza opinię kredytową dla podanego NIP.
     * Wywołuje mikroserwis /check-opinion, czeka na wynik (maks. 120s).
     *
     * @param string $nip  Numer NIP (tylko cyfry)
     * @return array{success: bool, message: string, result: array|null}
     */
    public function checkOpinion(string $nip): array
    {
        $serviceUrl = rtrim((string)(Configure::read('Syntesys.service_url') ?? ''), '/');
        $apiKey     = (string)(Configure::read('Syntesys.api_key') ?? '');

        if ($serviceUrl === '') {
            return ['success' => false, 'message' => 'Brak konfiguracji Syntesys.service_url', 'result' => null];
        }

        $url = $serviceUrl . '/check-opinion';
        Log::info("SyntesysScraperService: POST {$url} nip={$nip}", ['scope' => 'syntesys']);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['nip' => $nip]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => array_filter([
                'Content-Type: application/json',
                'Accept: application/json',
                $apiKey !== '' ? "X-Api-Key: {$apiKey}" : null,
            ]),
        ]);

        $body     = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            return ['success' => false, 'message' => "Błąd połączenia: {$curlErr}", 'result' => null];
        }

        if ($httpCode !== 200) {
            $decoded = json_decode((string)$body, true);
            $errMsg  = is_array($decoded) ? ($decoded['error'] ?? "HTTP {$httpCode}") : "HTTP {$httpCode}";
            return ['success' => false, 'message' => $errMsg, 'result' => null];
        }

        $data = json_decode((string)$body, true);
        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Nieprawidłowy JSON z mikroserwisu', 'result' => null];
        }

        if (!($data['success'] ?? false)) {
            return ['success' => false, 'message' => $data['error'] ?? 'Nieznany błąd mikroserwisu', 'result' => null];
        }

        return ['success' => true, 'message' => 'OK', 'result' => $data['result'] ?? null];
    }
}


<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\E100Account;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Serwis integracji z E100 Public API v1.0.
 *
 * Dokumentacja: https://publicapi.e100.eu/
 *
 * Obsługuje:
 *  - autentykację i odświeżanie tokenu (POST /token)
 *  - transakcje (POST /transactions — paginacja, delta sync)
 *  - saldo agenta (GET /agents/:clientCode/balance)
 *  - info o karcie (GET /cards/info/card/:card)
 *  - blokada karty (POST /cards/stop-lists/block)
 *  - limit klienta (GET/PUT /agents/:clientCode/limit)
 *  - stacje (POST /stations/details)
 *  - ceny stacji (POST /stations/prices)
 */
class E100ApiService
{
    private const API_BASE = 'https://publicapi.e100.eu';
    private const CIPHER   = 'aes-256-cbc';

    // =========================================================================
    // Szyfrowanie haseł
    // =========================================================================

    /**
     * Szyfruje hasło przed zapisem do bazy.
     * Klucz: Security.salt z config.
     */
    public function encryptPassword(string $plain): string
    {
        $key  = mb_substr(Configure::read('Security.salt'), 0, 32, '8bit');
        $iv   = random_bytes(16);
        $enc  = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $enc);
    }

    /**
     * Odszyfrowuje hasło.
     */
    public function decryptPassword(string $encoded): string
    {
        $key  = mb_substr(Configure::read('Security.salt'), 0, 32, '8bit');
        $raw  = base64_decode($encoded, true);
        if ($raw === false || mb_strlen($raw, '8bit') < 17) {
            return '';
        }
        $iv  = mb_substr($raw, 0, 16, '8bit');
        $enc = mb_substr($raw, 16, null, '8bit');

        return (string)openssl_decrypt($enc, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    }

    // =========================================================================
    // Token — autentykacja
    // =========================================================================

    /**
     * Zwraca ważny access_token dla konta.
     * Jeśli token wygasł lub go nie ma, pobiera nowy i zapisuje do DB.
     *
     * @throws \RuntimeException gdy nie uda się zalogować
     */
    public function getValidToken(E100Account $account): string
    {
        // Sprawdź czy token nadal ważny (z 60s marginesem)
        if (
            !empty($account->access_token) &&
            $account->token_expires_at instanceof DateTime &&
            $account->token_expires_at->getTimestamp() > time() + 60
        ) {
            return (string)$account->access_token;
        }

        return $this->authenticate($account);
    }

    /**
     * POST /token — pobiera nowy access_token i zapisuje do DB.
     */
    public function authenticate(E100Account $account): string
    {
        $password = $this->decryptPassword((string)$account->password_enc);

        $payload = [
            'grant_type' => 'password',
            'client_id'  => 'external_app',
            'username'   => $account->username,
            'password'   => $password,
        ];

        $response = $this->request('POST', '/token', [], $payload, null);

        $data = $response['data'] ?? $response;
        if (empty($data['access_token'])) {
            $err = $data['error_description'] ?? $data['error'] ?? 'Brak access_token';
            throw new \RuntimeException("E100 auth error: {$err}");
        }

        $expiresAt = new DateTime('+' . (int)($data['expires_in'] ?? 32400) . ' seconds');

        // Zapisz token do bazy
        $E100Accounts = TableRegistry::getTableLocator()->get('E100Accounts');
        $E100Accounts->patchEntity($account, [
            'access_token'    => $data['access_token'],
            'refresh_token'   => $data['refresh_token'] ?? null,
            'token_expires_at'=> $expiresAt,
            'client_code'     => $data['code'] ?? $account->client_code,
            'fullname'        => $data['fullname'] ?? $account->fullname,
            'defcur'          => $data['defcur'] ?? $account->defcur,
        ]);
        $E100Accounts->save($account);

        return (string)$data['access_token'];
    }

    // =========================================================================
    // Transakcje
    // =========================================================================

    /**
     * Pobiera listę transakcji (z paginacją, max 2000/req).
     *
     * @param array{from?: string, to?: string, page_number?: int, page_size?: int, cards?: array, auto_numbers?: array, regions?: array, services?: array} $filters
     * @return array{page_number: int, page_count: int, row_count: int, transactions: array}
     */
    public function getTransactions(E100Account $account, array $filters = []): array
    {
        $token = $this->getValidToken($account);

        $payload = array_merge([
            'page_number' => 1,
            'page_size'   => 2000,
            'lang'        => 'pl',
            'regions'     => [],
            'services'    => [],
            'cards'       => [],
            'auto_numbers'=> [],
            'stations'    => [],
        ], $filters);

        return $this->request('POST', '/transactions', [], $payload, $token);
    }

    /**
     * Pobiera wszystkie transakcje delta od ostatniego row_version za pomocą
     * GET /agents/transactions (agent endpoint, infinite scroll po RowVersion).
     *
     * @return array Lista transakcji
     */
    public function getAgentTransactionsDelta(E100Account $account, ?string $rowVersion = null): array
    {
        $token   = $this->getValidToken($account);
        $payload = [];
        if ($rowVersion !== null) {
            $payload['RowVersion'] = $rowVersion;
        }

        return $this->request('POST', '/agents/transactions', [], $payload, $token);
    }

    // =========================================================================
    // Saldo konta
    // =========================================================================

    /**
     * GET /agents/:clientCode/balance
     */
    public function getBalance(E100Account $account): array
    {
        $token      = $this->getValidToken($account);
        $clientCode = $account->client_code;
        if (empty($clientCode)) {
            throw new \RuntimeException('Brak client_code dla konta E100 #' . $account->id);
        }

        return $this->request('GET', "/agents/{$clientCode}/balance", [], null, $token);
    }

    // =========================================================================
    // Karty
    // =========================================================================

    /**
     * GET /cards/info/card/:card
     */
    public function getCardInfo(E100Account $account, string $cardNumber): array
    {
        $token = $this->getValidToken($account);

        return $this->request('GET', '/cards/info/card/' . urlencode($cardNumber), [], null, $token);
    }

    /**
     * POST /cards/stop-lists/block — blokuje kartę.
     *
     * @throws \RuntimeException
     */
    public function blockCard(E100Account $account, string $cardNumber): bool
    {
        $token = $this->getValidToken($account);
        $this->request('POST', '/cards/stop-lists/block', [], ['card_number' => $cardNumber], $token, 204);

        return true;
    }

    // =========================================================================
    // Limity klienta
    // =========================================================================

    /**
     * GET /agents/:clientCode/limit
     */
    public function getLimit(E100Account $account, string $clientCode): array
    {
        $token = $this->getValidToken($account);

        return $this->request('GET', "/agents/{$clientCode}/limit", [], null, $token);
    }

    /**
     * PUT /agents/:clientCode/limit
     */
    public function setLimit(E100Account $account, string $clientCode, float $credit): bool
    {
        $token = $this->getValidToken($account);
        $this->request('PUT', "/agents/{$clientCode}/limit", ['credit' => $credit], ['credit' => $credit], $token, 204);

        return true;
    }

    // =========================================================================
    // Stacje
    // =========================================================================

    /**
     * POST /stations/details — lista stacji z filtrami.
     *
     * @param array{page_number?: int, countries?: array, brand_ids?: array, category_ids?: array} $filters
     */
    public function getStations(E100Account $account, array $filters = []): array
    {
        $token = $this->getValidToken($account);
        $payload = array_merge(['page_number' => 1], $filters);

        return $this->request('POST', '/stations/details', [], $payload, $token);
    }

    /**
     * POST /stations/prices?from=...
     *
     * @param string[] $stationIds Lista ID stacji (max 100)
     */
    public function getStationPrices(E100Account $account, array $stationIds, ?string $from = null): array
    {
        $token = $this->getValidToken($account);
        $query = $from ? ['from' => $from] : [];

        return $this->request('POST', '/stations/prices', $query, $stationIds, $token);
    }

    // =========================================================================
    // HTTP core
    // =========================================================================

    /**
     * Wykonuje request do E100 API.
     *
     * @param string      $method       HTTP method (GET, POST, PUT)
     * @param string      $path         Ścieżka API np. '/token'
     * @param array       $queryParams  Query string params
     * @param mixed       $body         Payload (null = brak body)
     * @param string|null $token        Access token (null = bez nagłówka)
     * @param int         $expectedCode Oczekiwany kod odpowiedzi
     * @return array
     * @throws \RuntimeException
     */
    private function request(
        string $method,
        string $path,
        array $queryParams = [],
        mixed $body = null,
        ?string $token = null,
        int $expectedCode = 200
    ): array {
        $url = self::API_BASE . $path;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Accept-Language: pl'];
        if ($token !== null) {
            $headers[] = 'access-token: ' . $token;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, '');   // obsługa gzip automatycznie

        if ($body !== null && in_array($method, ['POST', 'PUT'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $responseBody = curl_exec($ch);
        $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            Log::error("E100 cURL error [{$method} {$path}]: {$curlError}");
            throw new \RuntimeException("E100 API niedostępne: {$curlError}");
        }

        // 204 No Content — sukces bez body
        if ($httpCode === 204 && $expectedCode === 204) {
            return [];
        }

        $decoded = json_decode((string)$responseBody, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = is_array($decoded) ? (implode('; ', $decoded) ?: $responseBody) : (string)$responseBody;
            Log::warning("E100 API [{$method} {$path}] HTTP {$httpCode}: {$msg}");
            throw new \RuntimeException("E100 API error {$httpCode}: {$msg}");
        }

        return is_array($decoded) ? $decoded : [];
    }
}

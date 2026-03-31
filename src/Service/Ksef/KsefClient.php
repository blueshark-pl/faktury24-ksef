<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use Cake\Http\Client;
use Cake\Http\Client\Response;
use RuntimeException;

/**
 * @deprecated Zastąpione przez adapter bazujący na pakiecie n1ebieski/ksef-php-client (N1KsefService).
 * Pozostawione tymczasowo dla kompatybilności wstecznej.
 */
final class KsefClient
{
    private Client $http;
    private string $baseUrl;
    private string $ksefPublicKeyPem;
    private KsefTokenStorageInterface $storage;

    public function __construct(
        ?KsefTokenStorageInterface $storage = null,
        ?string $baseUrl = null,
        ?Client $http = null
    ) {
        $this->baseUrl         = rtrim($baseUrl ?? (string)env('KSEF_BASE_URL', 'https://api.ksef.mf.gov.pl/v2'), '/');
        $this->ksefPublicKeyPem= (string)env('KSEF_PUBLIC_KEY_PEM');
        $timeout               = (int)(env('KSEF_HTTP_TIMEOUT', 30));

        $this->http    = $http ?? new Client(['timeout' => $timeout]);
        $this->storage = $storage ?? new DbKsefTokenStorage();
    }

    /* ============= AUTH ============= */

    public function getAuthChallenge(): array
    {
        $resp = $this->http->post("{$this->baseUrl}/auth/challenge", []);
        $this->ensureOk($resp, 'auth/challenge');
        $b = (array)$resp->getJson();

        return [
            'challenge' => $b['challenge'] ?? '',
            'timestamp' => $b['timestamp'] ?? null,
        ];
    }

    public function submitXadesSignature(string $signedXml, bool $verifyCertChain = false): array
    {
        $payload = [
            'signedDocument'         => $signedXml,
            'verifyCertificateChain' => $verifyCertChain,
        ];
        $resp = $this->http->post("{$this->baseUrl}/auth/xades-signature", $payload, ['type' => 'json']);
        $this->ensureOk($resp, 'auth/xades-signature');
        $b = (array)$resp->getJson();

        return [
            'authenticationToken' => $b['authenticationToken']['token'] ?? '',
            'referenceNumber'     => $b['referenceNumber'] ?? '',
        ];
    }

    /**
     * Uwierzytelnienie tokenem KSeF.
     * $context: ['type'=>'nip'|'internalId'|'nipVatUe', 'value'=>'...']
     */
    public function authenticateByKsefToken(string $challenge, int|string $timestamp, array $context, string $ksefToken, ?array $authorizationPolicy = null): array
    {
        $encryptedB64 = $this->encryptKsefTokenWithPublicKey("{$ksefToken}|{$timestamp}");

        $payload = [
            'challenge'         => $challenge,
            'contextIdentifier' => ['type' => $context['type'], 'value' => $context['value']],
            'encryptedToken'    => $encryptedB64,
        ];
        if ($authorizationPolicy) {
            $payload['authorizationPolicy'] = $authorizationPolicy;
        }

        $resp = $this->http->post("{$this->baseUrl}/auth/ksef-token", $payload, ['type' => 'json']);
        $this->ensureOk($resp, 'auth/ksef-token');
        $b = (array)$resp->getJson();

        return [
            'authenticationToken' => $b['authenticationToken']['token'] ?? '',
            'referenceNumber'     => $b['referenceNumber'] ?? '',
        ];
    }

    public function getAuthStatus(string $referenceNumber, string $authenticationToken): array
    {
        $resp = $this->http->get(
            "{$this->baseUrl}/auth/{$referenceNumber}",
            [],
            ['headers' => ['Authorization' => "Bearer {$authenticationToken}"]]
        );
        $this->ensureOk($resp, 'auth/{referenceNumber}');
        return (array)$resp->getJson();
    }

    public function redeemAccessAndRefreshTokens(string $authenticationToken): array
    {
        $resp = $this->http->post(
            "{$this->baseUrl}/auth/token/redeem",
            [],
            ['type' => 'json', 'headers' => ['Authorization' => "Bearer {$authenticationToken}"]]
        );
        $this->ensureOk($resp, 'auth/token/redeem');
        $b = (array)$resp->getJson();

        return [
            'accessToken'  => $b['accessToken']['token']  ?? '',
            'refreshToken' => $b['refreshToken']['token'] ?? null,
        ];
    }

    public function refreshAccessToken(string $refreshToken): string
    {
        $resp = $this->http->post(
            "{$this->baseUrl}/auth/token/refresh",
            [],
            ['type' => 'json', 'headers' => ['Authorization' => "Bearer {$refreshToken}"]]
        );
        $this->ensureOk($resp, 'auth/token/refresh');
        $b = (array)$resp->getJson();
        return (string)($b['accessToken']['token'] ?? '');
    }

    public function storeTokens(string $contextKey, string $accessToken, ?string $refreshToken): void
    {
        $exp = $this->extractJwtExp($accessToken);
        $this->storage->saveTokens($contextKey, $accessToken, $refreshToken, $exp);
    }

    public function ensureAccessToken(string $contextKey): string
    {
        $tokens = $this->storage->getTokens($contextKey);
        if (!$tokens) {
            throw new RuntimeException("Brak tokenów KSeF dla kontekstu: {$contextKey}");
        }

        $access = (string)$tokens['accessToken'];
        $exp    = $tokens['accessExp'] ?? null;

        // prosty bufor 15s
        if ($exp && time() >= ((int)$exp - 15)) {
            $refresh = $tokens['refreshToken'] ?? null;
            if (!$refresh) {
                throw new RuntimeException('Brak refreshToken — wymagana ponowna autoryzacja.');
            }
            $newAccess = $this->refreshAccessToken($refresh);
            if (!$newAccess) {
                throw new RuntimeException('Nie udało się odświeżyć accessToken.');
            }
            $this->storeTokens($contextKey, $newAccess, $refresh);
            return $newAccess;
        }

        return $access;
    }

    /* ============= INVOICES ============= */

    /** GET /invoices/ksef/{ksefReferenceNumber} — zwraca body (XML/PDF) jako string */
    public function getInvoiceByKsefNumber(string $contextKey, string $ksefReferenceNumber): string
    {
        $access = $this->ensureAccessToken($contextKey);
        $resp = $this->http->get(
            "{$this->baseUrl}/invoices/ksef/{$ksefReferenceNumber}",
            [],
            ['headers' => ['Authorization' => "Bearer {$access}"]]
        );
        $this->ensureOk($resp, 'invoices/ksef/{ksefReferenceNumber}');
        return $resp->getStringBody();
    }

    /** GET /invoices/upo/{ksefReferenceNumber} — zwraca UPO (XML/PDF) jako string */
    public function getUpoByKsefNumber(string $contextKey, string $ksefReferenceNumber): string
    {
        $access = $this->ensureAccessToken($contextKey);
        $resp = $this->http->get(
            "{$this->baseUrl}/invoices/upo/{$ksefReferenceNumber}",
            [],
            ['headers' => ['Authorization' => "Bearer {$access}"]]
        );
        $this->ensureOk($resp, 'invoices/upo/{ksefReferenceNumber}');
        return $resp->getStringBody();
    }

    /**
     * POST /invoices/query/metadata
     * $filters przykład:
     * [
     *   'subjectType' => 'Subject1',
     *   'dateRange' => ['from' => '2025-09-01T00:00:00Z','to' => '2025-10-01T00:00:00Z','dateType' => 'Issue']
     * ]
     */
    public function queryInvoiceMetadata(
        string $contextKey,
        array $filters,
        int $pageOffset = 0,
        int $pageSize = 50,
        bool $includeMetadataFeatureHeader = true
    ): array {
        $access = $this->ensureAccessToken($contextKey);
        $url = "{$this->baseUrl}/invoices/query/metadata?pageOffset={$pageOffset}&pageSize={$pageSize}";

        $headers = ['Authorization' => "Bearer {$access}"];
        if ($includeMetadataFeatureHeader) {
            $headers['X-KSeF-Feature'] = 'include-metadata';
        }

        $resp = $this->http->post($url, $filters, ['type' => 'json', 'headers' => $headers]);
        $this->ensureOk($resp, 'invoices/query/metadata');
        return (array)$resp->getJson();
    }

    /**
     * POST /invoices/exports — inicjacja eksportu asynchronicznego.
     * $encryption: ['encryptedSymmetricKey'=>b64, 'initializationVector'=>b64]
     */
    public function startInvoicesExport(string $contextKey, array $filters, array $encryption): array
    {
        $access = $this->ensureAccessToken($contextKey);
        $payload = ['encryption' => $encryption, 'filters' => $filters];

        $resp = $this->http->post(
            "{$this->baseUrl}/invoices/exports",
            $payload,
            ['type' => 'json', 'headers' => ['Authorization' => "Bearer {$access}"]]
        );
        $this->ensureOk($resp, 'invoices/exports');
        $b = (array)$resp->getJson();

        return ['operationReferenceNumber' => $b['operationReferenceNumber'] ?? ($b['referenceNumber'] ?? '')];
    }

    /** GET /invoices/exports/{operationReferenceNumber} — status eksportu */
    public function getInvoicesExportStatus(string $contextKey, string $operationReferenceNumber): array
    {
        $access = $this->ensureAccessToken($contextKey);
        $resp = $this->http->get(
            "{$this->baseUrl}/invoices/exports/{$operationReferenceNumber}",
            [],
            ['headers' => ['Authorization' => "Bearer {$access}"]]
        );
        $this->ensureOk($resp, 'invoices/exports/{operationReferenceNumber}');
        return (array)$resp->getJson();
    }

    /* ============= ENCRYPTION HELPERS (RSA-OAEP-256) ============= */

    /** "token|timestamp" → RSA-OAEP-256 → Base64 */
    private function encryptKsefTokenWithPublicKey(string $plain): string
    {
        $pubKey = openssl_pkey_get_public($this->ksefPublicKeyPem);
        if (!$pubKey) {
            throw new RuntimeException('Nieprawidłowy KSEF_PUBLIC_KEY_PEM.');
        }
        $ok = openssl_public_encrypt(
            $plain,
            $encrypted,
            $pubKey,
            OPENSSL_PKCS1_OAEP_PADDING,
            '',
            'sha256',
            'sha256'
        );
        if (!$ok) {
            throw new RuntimeException('RSA-OAEP-256: błąd szyfrowania (token).');
        }
        return base64_encode($encrypted);
    }

    /**
     * Generator klucza sym. i IV do eksportów asynchronicznych.
     * Zwraca:
     *  ['encryptedSymmetricKey'=>b64,'initializationVector'=>b64,'rawKey'=>bytes,'rawIv'=>bytes]
     */
    public function makeExportEncryptionData(): array
    {
        $rawKey = random_bytes(32); // AES-256
        $rawIv  = random_bytes(12); // IV 96-bit (typowe dla GCM; KSeF wykorzysta po swojej stronie)
        $pubKey = openssl_pkey_get_public($this->ksefPublicKeyPem);
        if (!$pubKey) {
            throw new RuntimeException('Nieprawidłowy KSEF_PUBLIC_KEY_PEM.');
        }
        $ok = openssl_public_encrypt(
            $rawKey,
            $encrypted,
            $pubKey,
            OPENSSL_PKCS1_OAEP_PADDING,
            '',
            'sha256',
            'sha256'
        );
        if (!$ok) {
            throw new RuntimeException('RSA-OAEP-256: błąd szyfrowania (klucz sym.).');
        }
        return [
            'encryptedSymmetricKey' => base64_encode($encrypted),
            'initializationVector'  => base64_encode($rawIv),
            'rawKey'                => $rawKey,
            'rawIv'                 => $rawIv,
        ];
    }

    /* ============= UTILS ============= */

    private function ensureOk(Response $resp, string $where): void
    {
        if ($resp->isOk()) {
            return;
        }
        $body = $resp->getStringBody();
        throw new RuntimeException("KSeF {$where} HTTP {$resp->getStatusCode()}: {$body}");
    }

    private function extractJwtExp(string $jwt): ?int
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payloadJson = $this->b64url_decode($parts[1]);
        if ($payloadJson === false) {
            return null;
        }
        $payload = json_decode($payloadJson, true);
        return isset($payload['exp']) ? (int)$payload['exp'] : null;
    }

    private function b64url_decode(string $data): string|false
    {
        $rem = strlen($data) % 4;
        if ($rem) {
            $data .= str_repeat('=', 4 - $rem);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

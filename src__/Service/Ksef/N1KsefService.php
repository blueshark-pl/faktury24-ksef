<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use App\Model\Table\CompaniesTable;
use Cake\Core\Configure;
use Cake\Log\Log;
use GuzzleHttp\Client as GuzzleClient;
use N1ebieski\KSEFClient\ClientBuilder;
use N1ebieski\KSEFClient\Factories\EncryptionKeyFactory;
use N1ebieski\KSEFClient\Resources\ClientResource;
use N1ebieski\KSEFClient\Support\Utility;
use N1ebieski\KSEFClient\ValueObjects\Mode;

use function getenv;
use function ini_get;
use function ini_set;
use function is_file;
use function putenv;

/**
 * Integracja z n1ebieski/ksef-php-client w stylu README:
 * - Auto mapping: requesty tablicą (tam gdzie się da)
 * - Jasna kolejność auth: cert -> access/refresh -> ksef token
 * - Stabilny encryption key per (company, env)
 */
final class N1KsefService
{
    public function __construct(
        private readonly DbKsefTokenStorage $storage,
        private readonly ?CertificateStorage $certs = null,
        private readonly ?CompaniesTable $companies = null,
    ) {}

    /**
     * Buduje klienta zgodnie z README + Twoimi źródłami tokenów/certyfikatów.
     * Zwraca gotowy ClientResource.
     */
    public function buildClient(
        string $companyId,
        string $environment = 'test',
        ?string $overrideIdentifierNip = null,
        bool $needsEncryptionKey = false
    ): ClientResource {
        $ctx = $this->ctx($companyId, $environment);

        // TLS verify / CA bundle (Windows)
        $verifySetting = $this->ensureCaBundleConfigured($environment);

        $mode = $this->mapMode($environment);

        $builder = (new ClientBuilder())
            ->withMode($mode);

        // Opcjonalne: custom API URL (np. proxy / demo / różne endpointy)
        $apiUrl = (string)($this->cfg('Ksef.apiUrl') ?? getenv('KSEF_API_URL') ?? '');
        if ($apiUrl !== '') {
            $builder = $builder->withApiUrl($apiUrl);
        }

        // PSR-18 (README: guzzle)
        $builder = $builder->withHttpClient(new GuzzleClient([
            'verify' => $verifySetting,
            // 'timeout' => 30,
        ]));

        // Logging wg README: logPath + level
        $logPath = (string)($this->cfg('Ksef.logPath') ?? getenv('KSEF_LOG_PATH') ?? '');
        $logLevel = $this->cfg('Ksef.logLevel') ?? getenv('KSEF_LOG_LEVEL') ?? null;
        if ($logPath !== '') {
            $builder = $builder->withLogPath($logPath, $logLevel);
        }

        // Walidacja XSD (README)
        $validateXml = (bool)($this->cfg('Ksef.validateXml') ?? getenv('KSEF_VALIDATE_XML') ?? true);
        if (method_exists($builder, 'withValidateXml')) {
            $builder = $builder->withValidateXml($validateXml);
        }

        // Verify certificate chain (README)
        $verifyCertChain = (bool)($this->cfg('Ksef.verifyCertificateChain') ?? getenv('KSEF_VERIFY_CERT_CHAIN') ?? true);
        if (method_exists($builder, 'withVerifyCertificateChain')) {
            $builder = $builder->withVerifyCertificateChain($verifyCertChain);
        }

        // Async concurrency (README)
        $async = (int)($this->cfg('Ksef.asyncMaxConcurrency') ?? getenv('KSEF_ASYNC_MAX_CONCURRENCY') ?? 8);
        if (method_exists($builder, 'withAsyncMaxConcurrency') && $async > 0) {
            $builder = $builder->withAsyncMaxConcurrency($async);
        }

        // Identifier (NIP) – preferuj Companies.nip, potem creds, a na końcu override jeśli podany
        $creds = $this->storage->getSystemCreds($ctx);     // sysToken + nip
        $nipSource = null;
        $nip = $overrideIdentifierNip !== null && $overrideIdentifierNip !== ''
            ? preg_replace('/\D+/', '', $overrideIdentifierNip)
            : (string)($this->resolveCompanyNip($companyId, $creds, $nipSource) ?? '');

        if ($nip !== '') {
            $builder = $builder->withIdentifier($nip);
        }

        // Jeśli endpointy fakturowe/sesje: encryption key MUSI być ustawiony
        if ($needsEncryptionKey) {
            $encryptionKey = $this->getOrCreateEncryptionKey($ctx);
            $builder = $builder->withEncryptionKey($encryptionKey);
        }

        // Auth: cert -> access/refresh -> ksef token
        $tokens = $this->storage->getTokens($ctx);

        $certApplied = $this->applyCertificateAuth($builder, $companyId, $environment);
        if ($certApplied !== null) {
            $builder = $certApplied;
            // ważne: nie dokładamy tokenów, gdy cert jest użyty
            return $builder->build();
        }

        // Access token (gdy jest – README: auto authorization skipped)
        if (!empty($tokens['accessToken']) && !empty($tokens['accessExp'])) {
            $builder = $builder->withAccessToken((string)$tokens['accessToken'], (int)$tokens['accessExp']);
        }

        // Refresh token (jeśli jest – README: auto refresh enabled)
        if (!empty($tokens['refreshToken']) && !empty($tokens['refreshExp'])) {
            $builder = $builder->withRefreshToken((string)$tokens['refreshToken'], (int)$tokens['refreshExp']);
        }

        // Jeśli nie mamy access tokena – użyj KSeF token + identifier (auto authorization)
        if (empty($tokens['accessToken']) && !empty($creds['sysToken'])) {
            $builder = $builder->withKsefToken((string)$creds['sysToken']);
            // identifier już ustawiony powyżej
        }

        return $builder->build();
    }

    /**
     * Pobieranie metadanych faktur (invoices/query/metadata) – auto mapping.
     * Wersja "dokowa": request tablicą, zgodnie z Auto mapping w README.
     */
    public function queryReceivedMetadata(
        string $companyId,
        string $environment,
        array $filters,
        int $pageOffset,
        int $pageSize,
        ?string $overrideIdentifierNip = null
    ): array {
        $client = $this->buildClient(
            companyId: $companyId,
            environment: $environment,
            overrideIdentifierNip: $overrideIdentifierNip,
            needsEncryptionKey: false
        );

        // Minimalny, zgodny z API v2 payload.
        // Jeśli biblioteka zmieni nazwy pól DTO – tablica nadal przejdzie przez auto mapping.
        $payload = [
            'subjectType' => (string)($filters['subjectType'] ?? 'Subject1'),
            'dateRange' => [
                'dateType' => (string)($filters['dateRange']['dateType'] ?? 'Issue'),
                'from' => (string)($filters['dateRange']['from'] ?? date('Y-m-01T00:00:00')),
                // 'to' jest opcjonalne
            ],
            'pageSize' => $pageSize,
            'pageOffset' => $pageOffset,
        ];

        if (!empty($filters['dateRange']['to'])) {
            $payload['dateRange']['to'] = (string)$filters['dateRange']['to'];
        }
        if (!empty($filters['ksefNumber'])) {
            $payload['ksefNumber'] = (string)$filters['ksefNumber'];
        }
        if (!empty($filters['invoiceNumber'])) {
            $payload['invoiceNumber'] = (string)$filters['invoiceNumber'];
        }
        if (!empty($filters['sellerNip'])) {
            $payload['sellerNip'] = preg_replace('/\D+/', '', (string)$filters['sellerNip']);
        }
        if (!empty($filters['buyerNip'])) {
            $payload['buyerIdentifier'] = [
                'type' => 'nip',
                'identifier' => preg_replace('/\D+/', '', (string)$filters['buyerNip']),
            ];
        }

        $resp = $client->invoices()->query()->metadata($payload)->object();

        // Ujednolicenie do array
        return json_decode(json_encode($resp, JSON_UNESCAPED_UNICODE), true) ?? [];
    }

    /**
     * Wysyła XML faktury (FA(3)) w trybie interaktywnym (sessions online).
     * Wersja maksymalnie z README: open/send/close + Utility::retry dla status().
     */
    public function sendInvoiceXml(string $companyId, string $environment, string $xml): array
    {
        $client = $this->buildClient(
            companyId: $companyId,
            environment: $environment,
            overrideIdentifierNip: null,
            needsEncryptionKey: true
        );

        // 1) OPEN
        $open = $client->sessions()->online()->open([
            'formCode' => 'FA (3)',
        ])->object();

        $sessionRef = (string)($open->referenceNumber ?? '');
        if ($sessionRef === '') {
            throw new \RuntimeException('Nie udało się otworzyć sesji online (brak referenceNumber).');
        }

        // 2) SEND (XML)
        $send = $client->sessions()->online()->send([
            'referenceNumber' => $sessionRef,
            'faktura' => $xml,
        ])->object();

        $invoiceRef = (string)($send->referenceNumber ?? '');

        // 3) CLOSE (best-effort)
        try {
            $client->sessions()->online()->close([
                'referenceNumber' => $sessionRef,
            ])->status();
        } catch (\Throwable $e) {
            $this->logDebug('Close session failed (non-fatal): ' . $e->getMessage());
        }

        // 4) STATUS (retry) – dokładnie jak w README (200=OK, >=400=throw)
        $status = Utility::retry(function () use ($client, $sessionRef, $invoiceRef) {
            $st = $client->sessions()->invoices()->status([
                'referenceNumber' => $sessionRef,
                'invoiceReferenceNumber' => $invoiceRef,
            ])->object();

            $code = (int)($st->status->code ?? 0);

            if ($code === 200) {
                return $st;
            }
            if ($code >= 400) {
                throw new \RuntimeException((string)($st->status->description ?? 'KSeF error'), $code);
            }

            // 100/other: retry
            return null;
        });

        $code = (int)($status->status->code ?? 0);
        $desc = (string)($status->status->description ?? '');
        $ksefNumber = (string)($status->ksefNumber ?? '');

        return [
            'ok' => ($code === 200 && $ksefNumber !== ''),
            'statusCode' => $code,
            'statusDesc' => $desc,
            'ksefNumber' => $ksefNumber,
            'sessionReference' => $sessionRef,
            'invoiceReference' => $invoiceRef,
            'statusRaw' => json_encode($status, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ];
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function applyCertificateAuth(ClientBuilder $builder, string $companyId, string $environment): ?ClientBuilder
    {
        if ($this->certs === null) {
            return null;
        }

        // Opcja "master cert" – jak u Ciebie
        $masterCertCompanyId = (string)($this->cfg('Ksef.masterCertCompanyId') ?? getenv('KSEF_MASTER_CERT_COMPANY_ID') ?? '');
        $certCompanyId = $masterCertCompanyId !== '' ? $masterCertCompanyId : $companyId;

        $cert = $this->certs->getCertificateFor($certCompanyId, $environment);
        if (!$cert || empty($cert['path']) || !is_file((string)$cert['path'])) {
            return null;
        }

        // README: withCertificatePath / withCertificate
        if (str_ends_with((string)$cert['path'], '.p12')) {
            return $builder->withCertificatePath((string)$cert['path'], $cert['passphrase'] ?? null);
        }

        // Jeśli masz inne formaty – konwertuj do p12
        $p12 = $this->certs->ensurePkcs12($certCompanyId, $environment);
        if ($p12 && !empty($p12['path']) && is_file((string)$p12['path'])) {
            return $builder->withCertificatePath((string)$p12['path'], $p12['passphrase'] ?? null);
        }

        return null;
    }

    /**
     * Stabilny encryption key per kontekst.
     *
     * W DbKsefTokenStorage dodaj:
     * - getEncryptionKey(string $ctx): ?string
     * - saveEncryptionKey(string $ctx, string $key): void
     */
    private function getOrCreateEncryptionKey(string $ctx): string
    {
        $existing = $this->storage->getEncryptionKey($ctx);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $key = EncryptionKeyFactory::makeRandom();
        $this->storage->saveEncryptionKey($ctx, $key);

        return $key;
    }

    /**
     * Preferencja: Companies.nip -> creds['nip'].
     */
    private function resolveCompanyNip(string $companyId, ?array $creds, ?string &$source = null): ?string
    {
        $creds = $creds ?? [];

        // 1) Companies.nip
        try {
            $companies = $this->companies;
            if ($companies !== null) {
                $row = $companies->find()
                    ->select(['id', 'nip'])
                    ->where(['id' => $companyId])
                    ->first();
                if ($row && !empty($row->nip)) {
                    $source = 'companies';
                    return preg_replace('/\D+/', '', (string)$row->nip);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 2) fallback z ksef_authorizations
        $nip = $creds['nip'] ?? null;
        if (is_string($nip) && $nip !== '') {
            $source = 'ksef_authorizations';
            return preg_replace('/\D+/', '', $nip);
        }

        return null;
    }

    /**
     * CA bundle / TLS verify (zostawiłem Twoją logikę, bo na Windows to realnie ratuje życie).
     */
    private function ensureCaBundleConfigured(string $environment): string|bool
    {
        $skipVerify = (bool)($this->cfg('Ksef.skipTlsVerify') ?? getenv('KSEF_SKIP_TLS_VERIFY') ?? false);
        if ($skipVerify && $environment !== 'prod') {
            @ini_set('openssl.verify_peer', '0');
            @ini_set('openssl.verify_peer_name', '0');
            $this->logDebug('TLS verify disabled for TEST environment (diagnostic mode).');
            return false;
        }

        $existingCurl = (string)(ini_get('curl.cainfo') ?: '');
        $existingOpenSsl = (string)(ini_get('openssl.cafile') ?: '');

        if ($existingCurl !== '' && is_file($existingCurl)) {
            putenv('CURL_CA_BUNDLE=' . $existingCurl);
            putenv('SSL_CERT_FILE=' . $existingCurl);
            return $existingCurl;
        }

        if ($existingOpenSsl !== '' && is_file($existingOpenSsl)) {
            putenv('CURL_CA_BUNDLE=' . $existingOpenSsl);
            putenv('SSL_CERT_FILE=' . $existingOpenSsl);
            return $existingOpenSsl;
        }

        $bundle = (string)($this->cfg('Ksef.caBundle')
            ?? getenv('KSEF_CA_BUNDLE')
            ?? $this->defaultCaBundlePath());

        if ($bundle !== '' && is_file($bundle)) {
            @ini_set('curl.cainfo', $bundle);
            @ini_set('openssl.cafile', $bundle);
            putenv('CURL_CA_BUNDLE=' . $bundle);
            putenv('SSL_CERT_FILE=' . $bundle);
            return $bundle;
        }

        return true;
    }

    private function defaultCaBundlePath(): string
    {
        $root = dirname(__DIR__, 3);
        return $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'cacert.pem';
    }

    private function mapMode(string $environment): Mode
    {
        // zgodnie z README: Test, Demo, Production
        return $environment === 'prod' ? Mode::Production : Mode::Test;
    }

    private function ctx(string $companyId, string $environment): string
    {
        $env = ($environment === 'prod') ? 'prod' : 'test';
        return "company:{$companyId}:{$env}";
    }

    private function cfg(string $key): mixed
    {
        return class_exists(Configure::class) ? Configure::read($key) : null;
    }

    private function logDebug(string $message): void
    {
        if (class_exists(Log::class)) {
            Log::debug('[KSeF] ' . $message);
        }
    }
}

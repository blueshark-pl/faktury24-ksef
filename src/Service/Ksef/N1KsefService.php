<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use N1ebieski\KSEFClient\ClientBuilder;
use N1ebieski\KSEFClient\ValueObjects\Mode;
use Cake\ORM\TableRegistry;
use N1ebieski\KSEFClient\Requests\Invoices\Query\Metadata\MetadataRequest;
use N1ebieski\KSEFClient\DTOs\Requests\Invoices\DateRange;
use N1ebieski\KSEFClient\ValueObjects\Requests\Invoices\SubjectType as SubjectTypeVO;
use N1ebieski\KSEFClient\ValueObjects\Requests\Invoices\DateType as DateTypeVO;
use N1ebieski\KSEFClient\ValueObjects\Requests\Invoices\DateRangeFrom;
use N1ebieski\KSEFClient\ValueObjects\Requests\Invoices\DateRangeTo;
use N1ebieski\KSEFClient\ValueObjects\Requests\PageOffset as PageOffsetVO;
use N1ebieski\KSEFClient\ValueObjects\Requests\PageSize as PageSizeVO;
use N1ebieski\KSEFClient\ValueObjects\Requests\InvoiceNumber as InvoiceNumberVO;
use N1ebieski\KSEFClient\ValueObjects\Requests\KsefNumber as KsefNumberVO;
use N1ebieski\KSEFClient\DTOs\Requests\Invoices\BuyerIdentifier as BuyerIdentifierDTO;
use N1ebieski\KSEFClient\ValueObjects\NIP as NipVO;
use N1ebieski\KSEFClient\ValueObjects\Requests\SortOrder as SortOrderVO;
use N1ebieski\KSEFClient\Factories\EncryptionKeyFactory;
use N1ebieski\KSEFClient\Requests\Sessions\Online\Open\OpenRequest;
use N1ebieski\KSEFClient\Requests\Sessions\Online\Send\SendXmlRequest;
use N1ebieski\KSEFClient\Requests\Sessions\Online\Close\CloseRequest;
use N1ebieski\KSEFClient\Requests\Sessions\Invoices\Status\StatusRequest as InvoicesStatusRequest;
use N1ebieski\KSEFClient\ValueObjects\Requests\Sessions\FormCode as FormCodeVO;
use N1ebieski\KSEFClient\ValueObjects\Requests\ReferenceNumber as ReferenceNumberVO;
use function getenv;
use function ini_get;
use function ini_set;
use function putenv;

/**
 * Adapter oparty o pakiet n1ebieski/ksef-php-client.
 *
 * Kontrakt (skrót):
 * - buildClient(companyId, environment) → instancja klienta z automatyczną autoryzacją.
 * - Tryb wybierany na podstawie 'environment': prod → Production, pozostałe → Test.
 * - Pobiera sysToken/NIP i (jeśli dostępne) access/refresh + exp z DbKsefTokenStorage.
 * - Po stronie biblioteki odświeżanie access token odbywa się automatycznie,
 *   przy czym w razie braku tokenów startowych korzystamy z sysToken+NIP.
 */
final class N1KsefService
{
    public function __construct(
        private readonly DbKsefTokenStorage $storage,
        private readonly ?CertificateStorage $certs = null
    ) {}

    /**
     * Zwraca zbudowanego klienta N1ebieski\KSEFClient z auto-autoryzacją.
     */
    public function buildClient(
        string $companyId,
        string $environment = 'test',
        ?string $overrideIdentifierNip = null,
        ?string $overrideApiUrl = null,
        bool $enableTrace = false
    ): \N1ebieski\KSEFClient\Resources\ClientResource
    {
        $builder = $this->makeClientBuilder(
            companyId: $companyId,
            environment: $environment,
            overrideIdentifierNip: $overrideIdentifierNip,
            overrideApiUrl: $overrideApiUrl,
            withEncryptionKey: false,
            enableTrace: $enableTrace
        );

        return $builder->build();
    }

    /**
     * Buduje skonfigurowany ClientBuilder używany we wszystkich ścieżkach (metadata/send/permissions).
     * Dzięki temu maksymalnie korzystamy z dodatku n1ebieski i unikamy duplikacji logiki.
     */
    private function makeClientBuilder(
        string $companyId,
        string $environment,
        ?string $overrideIdentifierNip,
        ?string $overrideApiUrl,
        bool $withEncryptionKey,
        bool $enableTrace
    ): ClientBuilder {
        $traceEnabled = $enableTrace || $this->isAppDebugEnabled();
        $contextKey = $this->ctx($companyId, $environment);

        // Zapewnij poprawny CA bundle dla cURL/openssl (Windows często go nie ma domyślnie)
        $verifySetting = $this->ensureCaBundleConfigured($environment);

        // Tokeny/system creds trzymamy w storage
        $tokens = $this->storage->getTokens($contextKey);
        $creds  = $this->storage->getSystemCreds($contextKey);

        $builder = (new ClientBuilder())
            ->withMode($this->mapMode($environment));

        if ($withEncryptionKey) {
            $builder = $builder->withEncryptionKey(EncryptionKeyFactory::makeRandom());
        }

        // Base URL: ujednolicone dla TEST/PROD
        $apiUrl = $overrideApiUrl ?: $this->resolveApiUrl($environment);
        if ($apiUrl !== null && method_exists($builder, 'withApiUrl')) {
            $builder = $builder->withApiUrl($apiUrl);
        }

        if ($traceEnabled) {
            try {
                KsefHttpTrace::add([
                    'ts' => date('Y-m-d H:i:s'),
                    'stage' => 'builder',
                    'message' => 'Using apiUrl=' . (string)$apiUrl,
                ]);
            } catch (\Throwable) {
                // ignore
            }
        }

        // Walidacja XML wg XSD (jeśli wspierana w tej wersji klienta)
        if (method_exists($builder, 'withValidateXml')) {
            $builder = $builder->withValidateXml(true);
        }

        // Preferuj certyfikat, ale wspieraj też tokeny
        $certUsed = false;
        $masterCertCompanyId = (string)($this->readConfig('Ksef.masterCertCompanyId') ?? getenv('KSEF_MASTER_CERT_COMPANY_ID') ?? '');
        $certCompanyId = $masterCertCompanyId !== '' ? $masterCertCompanyId : $companyId;
        if ($masterCertCompanyId !== '') {
            $this->logDebug('Using master certificate from companyId=' . $masterCertCompanyId . ' (env=' . $environment . ').');
        }

        $cert = $this->certs?->getCertificateFor($certCompanyId, $environment);
        if ($cert && is_file($cert['path'])) {
            if (str_ends_with($cert['path'], '.p12')) {
                $opensslErrors = [];
                if ($this->canReadPkcs12($cert['path'], $cert['passphrase'] ?? null, $opensslErrors)) {
                    $builder = $builder->withCertificatePath($cert['path'], $cert['passphrase'] ?? null);
                    $certUsed = true;
                } else {
                    $this->logDebug('Certificate present but unreadable by OpenSSL (will fallback to token auth). ' . ($opensslErrors ? ('OpenSSL: ' . implode(' | ', $opensslErrors)) : ''));
                    if ($traceEnabled) {
                        try {
                            KsefHttpTrace::add([
                                'ts' => date('Y-m-d H:i:s'),
                                'stage' => 'builder',
                                'message' => 'Certificate unreadable; fallback to token auth. ' . ($opensslErrors ? ('OpenSSL: ' . implode(' | ', $opensslErrors)) : ''),
                            ]);
                        } catch (\Throwable) {
                            // ignore
                        }
                    }
                }
            } else {
                $p12 = $this->certs?->ensurePkcs12($certCompanyId, $environment);
                if ($p12 && is_file($p12['path'])) {
                    $opensslErrors = [];
                    if ($this->canReadPkcs12($p12['path'], $p12['passphrase'] ?? null, $opensslErrors)) {
                        $builder = $builder->withCertificatePath($p12['path'], $p12['passphrase'] ?? null);
                        $certUsed = true;
                    } else {
                        $this->logDebug('Converted PKCS#12 present but unreadable by OpenSSL (will fallback to token auth). ' . ($opensslErrors ? ('OpenSSL: ' . implode(' | ', $opensslErrors)) : ''));
                        if ($traceEnabled) {
                            try {
                                KsefHttpTrace::add([
                                    'ts' => date('Y-m-d H:i:s'),
                                    'stage' => 'builder',
                                    'message' => 'Converted PKCS#12 unreadable; fallback to token auth. ' . ($opensslErrors ? ('OpenSSL: ' . implode(' | ', $opensslErrors)) : ''),
                                ]);
                            } catch (\Throwable) {
                                // ignore
                            }
                        }
                    }
                }
            }
        }

        if (!$certUsed) {
            if (!empty($tokens['accessToken']) && !empty($tokens['accessExp'])) {
                $builder = $builder->withAccessToken((string)$tokens['accessToken'], (int)$tokens['accessExp']);
            }
            if (!empty($tokens['refreshToken']) && !empty($tokens['accessExp'])) {
                $builder = $builder->withRefreshToken((string)$tokens['refreshToken'], (int)$tokens['accessExp']);
            }
            if (!empty($creds['sysToken']) && !empty($creds['nip'])) {
                $builder = $builder->withKsefToken((string)$creds['sysToken']);
                $nip = $overrideIdentifierNip ? preg_replace('/\D+/', '', (string)$overrideIdentifierNip) : (string)$creds['nip'];
                if (!empty($nip)) {
                    $builder = $builder->withIdentifier($nip);
                    $this->logDebug('Using identifier (NIP) for token auth: ' . $nip . ($overrideIdentifierNip ? ' (override)' : ''));
                }
            }
        } else {
            $this->logDebug('Certificate present: skipping token-based authentication for this request.');
            $source = null;
            $nip = $overrideIdentifierNip ? preg_replace('/\D+/', '', (string)$overrideIdentifierNip) : $this->resolveCompanyNip($companyId, $creds, $source);
            if (!empty($nip)) {
                $builder = $builder->withIdentifier($nip);
                $this->logDebug('Using identifier (NIP) for certificate auth: ' . $nip . ($overrideIdentifierNip ? ' (override)' : ($source ? ' (source: ' . $source . ')' : '')));
            }
        }

        // Wstrzyknij HTTP client z verify + debug wrapper
        if (class_exists('GuzzleHttp\\Client')) {
            $httpClient = new \GuzzleHttp\Client(['verify' => $verifySetting]);
            if ($traceEnabled) {
                $root = defined('ROOT') ? ROOT : dirname(__DIR__, 3);
                $logFile = $root . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'ksef' . DIRECTORY_SEPARATOR . 'http-debug-redacted.log';
                $httpClient = new Psr18DebugClient($httpClient, $logFile, 12000, true, [KsefHttpTrace::class, 'add']);
            }
            if (method_exists($builder, 'withHttpClient')) {
                $builder = $builder->withHttpClient($httpClient);
            }
        }

        return $builder;
    }

    /**
     * Preflight: sprawdza czy plik PKCS#12 (.p12/.pfx) daje się odczytać przez OpenSSL używany przez PHP.
     * Na środowiskach z OpenSSL 3 certyfikaty zaszyfrowane legacy algorytmami mogą zwracać
     * np. "error:0308010C:digital envelope routines::unsupported".
     */
    private function canReadPkcs12(string $path, ?string $passphrase, array &$opensslErrors = []): bool
    {
        $opensslErrors = [];

        if (!is_file($path)) {
            $opensslErrors[] = 'file_not_found';
            return false;
        }
        if (!function_exists('openssl_pkcs12_read')) {
            $opensslErrors[] = 'openssl_pkcs12_read_unavailable';
            return false;
        }

        $data = @file_get_contents($path);
        if (!is_string($data) || $data === '') {
            $opensslErrors[] = 'file_read_failed';
            return false;
        }

        // Wyczyść kolejkę błędów OpenSSL przed próbą
        if (function_exists('openssl_error_string')) {
            while (openssl_error_string() !== false) {
                // drain
            }
        }

        $certs = [];
        $ok = @openssl_pkcs12_read($data, $certs, (string)($passphrase ?? ''));
        if ($ok === true) {
            return true;
        }

        if (function_exists('openssl_error_string')) {
            while (($e = openssl_error_string()) !== false) {
                $opensslErrors[] = $e;
            }
        }

        return false;
    }

    private function resolveApiUrl(string $environment): ?string
    {
        $env = ($environment === 'prod') ? 'prod' : 'test';

        // 1) Konfiguracja aplikacji / env
        // - Ksef.baseUrl (string)
        // - Ksef.baseUrlTest / Ksef.baseUrlProd
        // - ENV: KSEF_BASE_URL_TEST / KSEF_BASE_URL_PROD / KSEF_BASE_URL
        $cfgTest = $this->readConfig('Ksef.baseUrlTest') ?? getenv('KSEF_BASE_URL_TEST') ?: null;
        $cfgProd = $this->readConfig('Ksef.baseUrlProd') ?? getenv('KSEF_BASE_URL_PROD') ?: null;
        $cfgAny  = $this->readConfig('Ksef.baseUrl') ?? getenv('KSEF_BASE_URL') ?: null;

        $raw = null;
        if ($env === 'prod') {
            $raw = is_string($cfgProd) && $cfgProd !== '' ? $cfgProd : (is_string($cfgAny) && $cfgAny !== '' ? $cfgAny : null);
        } else {
            $raw = is_string($cfgTest) && $cfgTest !== '' ? $cfgTest : (is_string($cfgAny) && $cfgAny !== '' ? $cfgAny : null);
        }

        // 2) Sensowny fallback
        if (!is_string($raw) || $raw === '') {
            $raw = ($env === 'prod')
                ? 'https://api.ksef.mf.gov.pl/v2'
                : 'https://api-test.ksef.mf.gov.pl/v2';
        }

        // 3) Normalizacja ścieżki bazowej (ważne!):
        // - Dla hostów api*.ksef.mf.gov.pl dokumentacja wskazuje bazę /v2
        //   (np. https://api.ksef.mf.gov.pl/v2/invoices/query/metadata).
        // - Dla hostów ksef-*.mf.gov.pl biblioteka domyślnie działa na /api/v2.
        $raw = rtrim($raw, '/');
        $isApiHost = str_contains($raw, '://api.ksef.mf.gov.pl') || str_contains($raw, '://api-test.ksef.mf.gov.pl');
        $isKsefHost = str_contains($raw, '://ksef.mf.gov.pl') || str_contains($raw, '://ksef-test.mf.gov.pl') || str_contains($raw, '://ksef-demo.mf.gov.pl');

        if ($isApiHost) {
            if (str_ends_with($raw, '/api/v2')) {
                $raw = substr($raw, 0, -7) . '/v2';
            }
            if (!str_ends_with($raw, '/v2')) {
                $raw .= '/v2';
            }
            return $raw;
        }

        if ($isKsefHost) {
            if (str_ends_with($raw, '/v2')) {
                $raw = substr($raw, 0, -3) . '/api/v2';
            }
            if (!str_ends_with($raw, '/api/v2')) {
                $raw .= '/api/v2';
            }
            return $raw;
        }

        // Nieznany host – zachowaj dotychczasowe zachowanie (preferuj /api/v2)
        if (!str_ends_with($raw, '/api/v2') && !str_ends_with($raw, '/v2')) {
            $raw .= '/api/v2';
        }

        return $raw;
    }

    private function alternateApiUrl(string $environment, string $current): ?string
    {
        $current = rtrim($current, '/');
        $isProd = $environment === 'prod';

        // Warianty spotykane w dokumentacji / wdrożeniach:
        // - api*.ksef.mf.gov.pl/v2
        // - ksef*.mf.gov.pl/api/v2
        $apiHost = $isProd ? 'https://api.ksef.mf.gov.pl/v2' : 'https://api-test.ksef.mf.gov.pl/v2';
        $ksefHost = $isProd ? 'https://ksef.mf.gov.pl/api/v2' : 'https://ksef-test.mf.gov.pl/api/v2';

        if (str_starts_with($current, 'https://api.ksef.mf.gov.pl') || str_starts_with($current, 'https://api-test.ksef.mf.gov.pl')) {
            return $ksefHost;
        }
        if (str_starts_with($current, 'https://ksef.mf.gov.pl') || str_starts_with($current, 'https://ksef-test.mf.gov.pl')) {
            return $apiHost;
        }

        return null;
    }

    private function isAppDebugEnabled(): bool
    {
        $debug = $this->readConfig('debug');
        return (bool)$debug;
    }

    /**
     * Ustala NIP firmy do użycia jako identifier.
     * Preferencja zgodnie z prośbą: NAJPIERW z tabeli Companies.nip, a dopiero potem z credów.
     */
    private function resolveCompanyNip(string $companyId, ?array $creds, ?string &$source = null): ?string
    {
        $creds = $creds ?? [];
        // 1) Companies.nip – preferowane źródło
        try {
            $companies = TableRegistry::getTableLocator()->get('Companies');
            $row = $companies->find()
                ->select(['id', 'nip'])
                ->where(['id' => $companyId])
                ->first();
            if ($row && !empty($row->nip)) {
                $source = 'companies';
                return preg_replace('/\D+/', '', (string)$row->nip);
            }
        } catch (\Throwable $e) {
            // brak Companies lub błąd – ignoruj, zostaw null
        }
        // 2) Fallback: z zapisanych credów (ksef_authorizations payload v2)
        $nip = $creds['nip'] ?? null;
        if (is_string($nip) && $nip !== '') {
            $source = 'ksef_authorizations';
            return preg_replace('/\D+/', '', $nip);
        }
        return null;
    }

    /**
     * Ustawia ścieżkę do CA bundle dla cURL/openssl, jeśli nie jest skonfigurowana.
     * Szuka w kolejności: env KSEF_CA_BUNDLE → Configure('Ksef.caBundle') → resources/cacert.pem
     * Dodatkowo pozwala tymczasowo pominąć weryfikację w trybie test przy fladze Ksef.skipTlsVerify.
     */
    private function ensureCaBundleConfigured(string $environment): string|bool
    {
        // Tymczasowy tryb debug: pominąć weryfikację TLS tylko gdy explicite włączone i środowisko testowe.
        $skipVerify = (bool)($this->readConfig('Ksef.skipTlsVerify') ?? getenv('KSEF_SKIP_TLS_VERIFY') ?? false);
        if ($skipVerify && $environment !== 'prod') {
            // OSTRZEŻENIE: tylko do diagnostyki! Nie używać na produkcji.
            @ini_set('openssl.verify_peer', '0');
            @ini_set('openssl.verify_peer_name', '0');
            $this->logDebug('TLS verify disabled for TEST environment (diagnostic mode).');
            return false; // Guzzle verify=false
        }

        $existingCurl = (string)(ini_get('curl.cainfo') ?: '');
        $existingOpenSsl = (string)(ini_get('openssl.cafile') ?: '');
        if ($existingCurl !== '' && is_file($existingCurl)) {
            // Już skonfigurowane globalnie
            putenv('CURL_CA_BUNDLE=' . $existingCurl);
            putenv('SSL_CERT_FILE=' . $existingCurl);
            $this->logDebug('Using existing curl.cainfo CA bundle at: ' . $existingCurl);
            return $existingCurl;
        }
        if ($existingOpenSsl !== '' && is_file($existingOpenSsl)) {
            putenv('CURL_CA_BUNDLE=' . $existingOpenSsl);
            putenv('SSL_CERT_FILE=' . $existingOpenSsl);
            $this->logDebug('Using existing openssl.cafile CA bundle at: ' . $existingOpenSsl);
            return $existingOpenSsl; // Już skonfigurowane globalnie
        }

        $bundle = (string)($this->readConfig('Ksef.caBundle')
            ?? getenv('KSEF_CA_BUNDLE')
            ?? $this->defaultCaBundlePath());

        if (is_string($bundle) && $bundle !== '' && is_file($bundle)) {
            @ini_set('curl.cainfo', $bundle);
            @ini_set('openssl.cafile', $bundle);
            putenv('CURL_CA_BUNDLE=' . $bundle);
            putenv('SSL_CERT_FILE=' . $bundle);
            $this->logDebug('Configured CA bundle from config/env at: ' . $bundle);
            return $bundle;
        }

        $this->logDebug('No CA bundle set explicitly; relying on system defaults with verify=true.');
        return true; // nie znaleziono pliku, pozostaw systemowe ustawienia; verify=true
    }

    private function logDebug(string $message): void
    {
        if (class_exists('Cake\\Log\\Log')) {
            \Cake\Log\Log::debug('[KSeF TLS] ' . $message);
        }
    }

    /**
     * Zwraca diagnostykę kontekstu autoryzacji: źródło certyfikatu, metoda auth, użyty NIP i jego źródło.
     */
    public function diagnoseAuthContext(string $companyId, string $environment, ?string $overrideIdentifierNip = null): array
    {
        $ctx = $this->ctx($companyId, $environment);
        $tokens = $this->storage->getTokens($ctx);
        $creds  = $this->storage->getSystemCreds($ctx);

        $masterCertCompanyId = (string)($this->readConfig('Ksef.masterCertCompanyId') ?? getenv('KSEF_MASTER_CERT_COMPANY_ID') ?? '');
        $certCompanyId = $masterCertCompanyId !== '' ? $masterCertCompanyId : $companyId;

        $cert = $this->certs?->getCertificateFor($certCompanyId, $environment);
        $certSource = $cert['source'] ?? ($masterCertCompanyId !== '' ? 'master' : 'company');

        // Określ faktycznie używany plik certyfikatu (dla połączenia zawsze PKCS#12, jeśli dostępne).
        $certPresent = (bool)$cert;
        $certReadable = null;
        $certUsedPath = null;
        $certOriginalPath = null;
        $certOpenSslErrors = [];
        if (is_array($cert) && !empty($cert['path']) && is_string($cert['path'])) {
            $certOriginalPath = (string)$cert['path'];

            $path = (string)$cert['path'];
            $pass = isset($cert['passphrase']) && is_string($cert['passphrase']) ? $cert['passphrase'] : null;

            if (is_file($path)) {
                if (preg_match('/\.(p12|pfx)$/i', $path) === 1) {
                    $certUsedPath = $path;
                    $certReadable = $this->canReadPkcs12($path, $pass, $certOpenSslErrors);
                } else {
                    // Jeśli to PEM/CRT, spróbuj użyć skonwertowanego .p12 (tak jak builder).
                    $p12 = $this->certs?->ensurePkcs12($certCompanyId, $environment);
                    if (is_array($p12) && !empty($p12['path']) && is_string($p12['path']) && is_file((string)$p12['path'])) {
                        $certUsedPath = (string)$p12['path'];
                        $p12Pass = isset($p12['passphrase']) && is_string($p12['passphrase']) ? $p12['passphrase'] : null;
                        $certReadable = $this->canReadPkcs12((string)$p12['path'], $p12Pass, $certOpenSslErrors);
                    } else {
                        $certReadable = false;
                    }
                }
            } else {
                $certReadable = false;
            }
        }

        // authMethod powinno odzwierciedlać realne zachowanie buildera: cert tylko jeśli jest czytelny.
        $authMethod = ($certPresent && $certReadable === true) ? 'certificate' : 'token';

        $nipSource = null;
        $nip = null;
        if ($overrideIdentifierNip !== null && $overrideIdentifierNip !== '') {
            $nip = preg_replace('/\D+/', '', (string)$overrideIdentifierNip);
            if ($nip !== '') { $nipSource = 'override'; }
        }
        if ($nip === null || $nip === '') {
            $nip = $this->resolveCompanyNip($companyId, $creds, $nipSource);
        }

        return [
            'environment' => $environment,
            'apiUrl' => $this->resolveApiUrl($environment),
            'authMethod' => $authMethod,
            'certPresent' => $certPresent,
            'certSource' => $certPresent ? $certSource : null,
            'certCompanyId' => $certCompanyId,
            'masterCertCompanyId' => $masterCertCompanyId !== '' ? $masterCertCompanyId : null,
            'certFile' => $certUsedPath ? basename((string)$certUsedPath) : ($certOriginalPath ? basename((string)$certOriginalPath) : null),
            'certOriginalFile' => $certOriginalPath ? basename((string)$certOriginalPath) : null,
            'certReadable' => $certReadable,
            'certUsed' => ($certPresent && $certReadable === true),
            'certOpenSslErrors' => !empty($certOpenSslErrors) ? array_slice($certOpenSslErrors, 0, 5) : [],
            'identifierNip' => $nip,
            'identifierSource' => $nipSource,
            'hasAccessToken' => !empty($tokens['accessToken']) && !empty($tokens['accessExp']),
            'hasRefreshToken' => !empty($tokens['refreshToken']) && !empty($tokens['accessExp'])
        ];
    }

    private function readConfig(string $key): mixed
    {
        // Lazy dependency to Cake Configure without hard requirement
        if (class_exists('Cake\\Core\\Configure')) {
            /** @var callable $reader */
            $reader = ['Cake\\Core\\Configure', 'read'];
            return $reader($key);
        }
        return null;
    }

    private function defaultCaBundlePath(): string
    {
        // {projectRoot}/resources/cacert.pem
        $root = dirname(__DIR__, 3);
        return $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'cacert.pem';
    }

    private function mapMode(string $environment): Mode
    {
        return $environment === 'prod' ? Mode::Production : Mode::Test;
    }

    private function ctx(string $companyId, string $environment): string
    {
        $env = ($environment === 'prod') ? 'prod' : 'test';
        return "company:{$companyId}:{$env}";
    }

    /**
     * Zapytanie o metadane faktur otrzymanych (kontekst: firma jako nabywca) z paginacją.
     * Zwraca surowe dane z biblioteki (array) – mapowanie do widoku po stronie kontrolera.
     *
     * Uwaga: Zakładamy API v2 i możliwość filtrowania przez dateRange i subjectType.
     */
    public function queryReceivedMetadata(
        string $companyId,
        string $environment,
        array $filters,
        int $pageOffset,
        int $pageSize,
        ?string $overrideIdentifierNip = null,
        bool $enableTrace = false
    ): array {
        $apiUrl = $this->resolveApiUrl($environment);
        $client = $this->buildClient($companyId, $environment, $overrideIdentifierNip, $apiUrl, $enableTrace);
        if ($this->isAppDebugEnabled() && class_exists('Cake\\Log\\Log')) {
            \Cake\Log\Log::debug('[KSeF] queryReceivedMetadata filters: ' . json_encode($filters, JSON_UNESCAPED_UNICODE));
        }
        // Zbuduj VO zgodnie z wersją biblioteki: invoices()->query()->metadata(new MetadataRequest(...))
        $subjectStr = (string)($filters['subjectType'] ?? 'Subject1');
        // Zabezpieczenie na różne wielkości liter
        $subject = SubjectTypeVO::from(match ($subjectStr) {
            'Subject1' => SubjectTypeVO::Subject1->value,
            'Subject2' => SubjectTypeVO::Subject2->value,
            'Subject3' => SubjectTypeVO::Subject3->value,
            default    => SubjectTypeVO::Subject1->value,
        });

        $dateTypeStr = (string)($filters['dateRange']['dateType'] ?? 'PermanentStorage');
        $dateType = DateTypeVO::from(match ($dateTypeStr) {
            'Issue' => DateTypeVO::Issue->value,
            'Invoicing' => DateTypeVO::Invoicing->value,
            'PermanentStorage' => DateTypeVO::PermanentStorage->value,
            default => DateTypeVO::Issue->value,
        });
        // Uwaga: w PHP format "T" w date() to skrót strefy (np. CET), a nie separator ISO.
        // To psuje parsowanie DateTimeImmutable i kończy się błędem 400 "Nieprawidłowe żądanie".
        $defaultFrom = (new \DateTimeImmutable('first day of this month 00:00:00', new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s\\Z');
        $from = (string)($filters['dateRange']['from'] ?? $defaultFrom);
        $toOpt = $filters['dateRange']['to'] ?? null;

        $dateRange = new DateRange(
            $dateType,
            DateRangeFrom::from($from),
            $toOpt ? DateRangeTo::from((string)$toOpt) : new \N1ebieski\KSEFClient\Support\Optional()
        );

        $request = new MetadataRequest(
            subjectType: $subject,
            dateRange: $dateRange,
            ksefNumber: !empty($filters['ksefNumber']) ? KsefNumberVO::from((string)$filters['ksefNumber']) : new \N1ebieski\KSEFClient\Support\Optional(),
            invoiceNumber: !empty($filters['invoiceNumber']) ? InvoiceNumberVO::from((string)$filters['invoiceNumber']) : new \N1ebieski\KSEFClient\Support\Optional(),
            sellerNip: !empty($filters['sellerNip']) ? NipVO::from((string)$filters['sellerNip']) : new \N1ebieski\KSEFClient\Support\Optional(),
            buyerIdentifier: !empty($filters['buyerNip']) ? new BuyerIdentifierDTO(NipVO::from((string)$filters['buyerNip'])) : new \N1ebieski\KSEFClient\Support\Optional(),
            // KSeF v2: sortOrder jest parametrem query (domyślnie Asc), ale część wdrożeń zwraca 404 gdy go brakuje.
            // Dla scenariusza przyrostowego (PermanentStorage) wymagane jest Asc.
            sortOrder: SortOrderVO::Asc,
            pageSize: \N1ebieski\KSEFClient\ValueObjects\Requests\Invoices\PageSize::from($pageSize),
            pageOffset: PageOffsetVO::from($pageOffset)
        );

        // Wywołanie zgodne z README/tests: invoices()->query()->metadata(MetadataRequest)->object()/array()
        try {
            $resp = $client->invoices()->query()->metadata($request);
        } catch (\Throwable $e) {
            // Na PROD spotykane są rozjazdy host/basePath (auth działa, ale invoices endpoint zwraca 404).
            // Spróbujmy jednorazowo alternatywnego base URL przy 404.
            if ((int)$e->getCode() === 404 && is_string($apiUrl) && $apiUrl !== '') {
                $alt = $this->alternateApiUrl($environment, $apiUrl);
                if ($alt !== null && $alt !== $apiUrl) {
                    if ($enableTrace || $this->isAppDebugEnabled()) {
                        try {
                            KsefHttpTrace::add([
                                'ts' => date('Y-m-d H:i:s'),
                                'stage' => 'retry',
                                'message' => 'Metadata 404; retry with alternate apiUrl=' . $alt,
                            ]);
                        } catch (\Throwable) {
                            // ignore
                        }
                    }
                    $client2 = $this->buildClient($companyId, $environment, $overrideIdentifierNip, $alt, $enableTrace);
                    $resp = $client2->invoices()->query()->metadata($request);
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }
        if ($this->isAppDebugEnabled() && class_exists('Cake\\Log\\Log')) {
            \Cake\Log\Log::debug('[KSeF] queryReceivedMetadata response type: ' . (is_object($resp) ? $resp::class : gettype($resp)));
        }
        if (is_object($resp) && method_exists($resp, 'array')) {
            return $resp->array();
        }
        if (is_array($resp)) {
            return $resp;
        }

        if (is_object($resp) && method_exists($resp, 'object')) {
            $obj = $resp->object();
            return json_decode(json_encode($obj, JSON_UNESCAPED_UNICODE), true) ?? [];
        }

        throw new \RuntimeException('Nieoczekiwany typ odpowiedzi biblioteki przy invoices()->query()->metadata().');
    }

    /**
     * Zapytanie o własne uprawnienia (personal grants) dla uwierzytelnionego klienta.
     *
     * Endpoint (KSeF v2): POST permissions/query/personal/grants?pageOffset=0&pageSize=10
     * Body (opcjonalne filtry):
     * - contextIdentifier: { type: 'Nip', value: '...' }
     * - targetIdentifier: { type: 'Nip'|'InternalId'|'AllPartners', value: '...' }
     * - permissionTypes: ['InvoiceRead', 'InvoiceWrite', ...]
     * - permissionState: np. 'Active'
     *
     * Zwraca surowy response jako array (zwykle: permissions[], hasMore).
     */
    public function queryPersonalGrants(
        string $companyId,
        string $environment,
        array $filters,
        int $pageOffset,
        int $pageSize,
        ?string $overrideIdentifierNip = null,
        bool $enableTrace = false
    ): array {
        $apiUrl = $this->resolveApiUrl($environment);
        $client = $this->buildClient($companyId, $environment, $overrideIdentifierNip, $apiUrl, $enableTrace);

        $accessToken = $client->getAccessToken();
        if ($accessToken === null || empty($accessToken->token)) {
            throw new \RuntimeException('Brak access token po autoryzacji – nie można wykonać zapytania o uprawnienia.');
        }

        $verifySetting = $this->ensureCaBundleConfigured($environment);

        $httpClient = new \GuzzleHttp\Client([
            'verify' => $verifySetting,
        ]);

        if ($enableTrace || $this->isAppDebugEnabled()) {
            $root = defined('ROOT') ? ROOT : dirname(__DIR__, 3);
            $logFile = $root . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'ksef' . DIRECTORY_SEPARATOR . 'http-debug-redacted.log';
            $httpClient = new Psr18DebugClient($httpClient, $logFile, 12000, true, [KsefHttpTrace::class, 'add']);
        }

        $payload = [];
        if (!empty($filters['contextIdentifier']) && is_array($filters['contextIdentifier'])) {
            $payload['contextIdentifier'] = $filters['contextIdentifier'];
        }
        if (!empty($filters['targetIdentifier']) && is_array($filters['targetIdentifier'])) {
            $payload['targetIdentifier'] = $filters['targetIdentifier'];
        }
        if (!empty($filters['permissionTypes']) && is_array($filters['permissionTypes'])) {
            $payload['permissionTypes'] = array_values(array_filter($filters['permissionTypes'], fn($v) => is_string($v) && $v !== ''));
        }
        if (!empty($filters['permissionState']) && is_string($filters['permissionState'])) {
            $payload['permissionState'] = $filters['permissionState'];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Nie można zbudować JSON body dla zapytania o uprawnienia.');
        }

        $base = is_string($apiUrl) && $apiUrl !== '' ? rtrim($apiUrl, '/') : '';
        if ($base === '') {
            throw new \RuntimeException('Brak base API URL dla KSeF.');
        }

        $path = 'permissions/query/personal/grants';
        $qs = http_build_query([
            'pageSize' => $pageSize,
            'pageOffset' => $pageOffset,
        ]);

        $url = $base . '/' . $path . ($qs !== '' ? ('?' . $qs) : '');

        $request = new \GuzzleHttp\Psr7\Request(
            'POST',
            $url,
            [
                'Authorization' => 'Bearer ' . $accessToken->token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            $json
        );

        try {
            $response = $httpClient->sendRequest($request);
        } catch (\Throwable $e) {
            // Jeśli to 404, spróbuj alternatywnego base URL (analogicznie jak dla invoices/query/metadata)
            if ((int)$e->getCode() === 404 && is_string($apiUrl) && $apiUrl !== '') {
                $alt = $this->alternateApiUrl($environment, $apiUrl);
                if ($alt !== null && $alt !== $apiUrl) {
                    if ($this->isAppDebugEnabled()) {
                        try {
                            KsefHttpTrace::add([
                                'ts' => date('Y-m-d H:i:s'),
                                'stage' => 'retry',
                                'message' => 'Personal grants 404; retry with alternate apiUrl=' . $alt,
                            ]);
                        } catch (\Throwable) {
                            // ignore
                        }
                    }

                    $base2 = rtrim($alt, '/');
                    $url2 = $base2 . '/' . $path . ($qs !== '' ? ('?' . $qs) : '');
                    $request2 = $request->withUri(new \GuzzleHttp\Psr7\Uri($url2));
                    $response = $httpClient->sendRequest($request2);
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }

        $status = $response->getStatusCode();
        $body = (string)$response->getBody();

        // Jeżeli status >= 400, wyrzuć wyjątek z code równym HTTP status – kontroler już to ładnie opakuje.
        if ($status >= 400) {
            $snippet = $body;
            if (strlen($snippet) > 2000) {
                $snippet = substr($snippet, 0, 2000) . '...';
            }
            throw new \RuntimeException('KSeF permissions/query/personal/grants: HTTP ' . $status . ' ' . $snippet, $status);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            // Czasem KSeF zwraca application/problem+json lub nietypowy JSON – pokaż chociaż surowe body.
            return ['raw' => $body];
        }

        return $decoded;
    }

    /**
     * Zapytanie o uprawnienia podmiotowe (authorizations grants) w bieżącym kontekście logowania.
     *
     * Endpoint (KSeF v2): POST permissions/query/authorizations/grants?pageOffset=0&pageSize=10
     * Body (filtry):
     * - queryType: 'Received' | 'Granted'
     * - authorizingIdentifier (dla queryType=Received): { type: 'Nip', value: '...' }
     * - authorizedIdentifier (dla queryType=Granted): { type: 'Nip', value: '...' }
     * - permissionTypes: ['SelfInvoicing', 'TaxRepresentative', 'RRInvoicing', 'PefInvoicing']
     */
    public function queryAuthorizationGrants(
        string $companyId,
        string $environment,
        array $filters,
        int $pageOffset,
        int $pageSize,
        ?string $overrideIdentifierNip = null,
        bool $enableTrace = false
    ): array {
        $apiUrl = $this->resolveApiUrl($environment);
        $client = $this->buildClient($companyId, $environment, $overrideIdentifierNip, $apiUrl, $enableTrace);

        $accessToken = $client->getAccessToken();
        if ($accessToken === null || empty($accessToken->token)) {
            throw new \RuntimeException('Brak access token po autoryzacji – nie można wykonać zapytania o uprawnienia podmiotowe.');
        }

        $verifySetting = $this->ensureCaBundleConfigured($environment);

        $httpClient = new \GuzzleHttp\Client([
            'verify' => $verifySetting,
        ]);

        if ($enableTrace || $this->isAppDebugEnabled()) {
            $root = defined('ROOT') ? ROOT : dirname(__DIR__, 3);
            $logFile = $root . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'ksef' . DIRECTORY_SEPARATOR . 'http-debug-redacted.log';
            $httpClient = new Psr18DebugClient($httpClient, $logFile, 12000, true, [KsefHttpTrace::class, 'add']);
        }

        $payload = [];
        if (!empty($filters['queryType']) && is_string($filters['queryType'])) {
            $payload['queryType'] = $filters['queryType'];
        }
        if (!empty($filters['authorizingIdentifier']) && is_array($filters['authorizingIdentifier'])) {
            $payload['authorizingIdentifier'] = $filters['authorizingIdentifier'];
        }
        if (!empty($filters['authorizedIdentifier']) && is_array($filters['authorizedIdentifier'])) {
            $payload['authorizedIdentifier'] = $filters['authorizedIdentifier'];
        }
        if (!empty($filters['permissionTypes']) && is_array($filters['permissionTypes'])) {
            $payload['permissionTypes'] = array_values(array_filter($filters['permissionTypes'], fn($v) => is_string($v) && $v !== ''));
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Nie można zbudować JSON body dla zapytania o uprawnienia podmiotowe.');
        }

        $base = is_string($apiUrl) && $apiUrl !== '' ? rtrim($apiUrl, '/') : '';
        if ($base === '') {
            throw new \RuntimeException('Brak base API URL dla KSeF.');
        }

        $path = 'permissions/query/authorizations/grants';
        $qs = http_build_query([
            'pageSize' => $pageSize,
            'pageOffset' => $pageOffset,
        ]);

        $url = $base . '/' . $path . ($qs !== '' ? ('?' . $qs) : '');

        $request = new \GuzzleHttp\Psr7\Request(
            'POST',
            $url,
            [
                'Authorization' => 'Bearer ' . $accessToken->token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            $json
        );

        try {
            $response = $httpClient->sendRequest($request);
        } catch (\Throwable $e) {
            if ((int)$e->getCode() === 404 && is_string($apiUrl) && $apiUrl !== '') {
                $alt = $this->alternateApiUrl($environment, $apiUrl);
                if ($alt !== null && $alt !== $apiUrl) {
                    if ($this->isAppDebugEnabled()) {
                        try {
                            KsefHttpTrace::add([
                                'ts' => date('Y-m-d H:i:s'),
                                'stage' => 'retry',
                                'message' => 'Authorizations grants 404; retry with alternate apiUrl=' . $alt,
                            ]);
                        } catch (\Throwable) {
                            // ignore
                        }
                    }

                    $base2 = rtrim($alt, '/');
                    $url2 = $base2 . '/' . $path . ($qs !== '' ? ('?' . $qs) : '');
                    $request2 = $request->withUri(new \GuzzleHttp\Psr7\Uri($url2));
                    $response = $httpClient->sendRequest($request2);
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }

        $status = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($status >= 400) {
            $snippet = $body;
            if (strlen($snippet) > 2000) {
                $snippet = substr($snippet, 0, 2000) . '...';
            }
            throw new \RuntimeException('KSeF permissions/query/authorizations/grants: HTTP ' . $status . ' ' . $snippet, $status);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return ['raw' => $body];
        }

        return $decoded;
    }

    /**
     * Wysyła pojedynczy dokument XML (FA) do KSeF trybem interaktywnym (online session).
     * Zwraca tablicę z informacją o statusie i nadanym numerze KSeF (jeśli sukces).
     * Uwaga: wymagany jest certyfikat (.p12 lub skonwertowany) lub token – preferowany certyfikat.
     */
    public function sendInvoiceXml(string $companyId, string $environment, string $xml): array
    {
        $contextKey = $this->ctx($companyId, $environment);
        $messages = [];
        $nowTs = fn() => date('c');
        $this->ensureCaBundleConfigured($environment);
        $messages[] = ['stage' => 'setup', 'level' => 'info', 'ts' => $nowTs(), 'message' => 'Konfiguracja środowiska TLS'];

        // Zbuduj klienta JEDNORAZOWO z kluczem szyfrowania (wymagany dla zasobów invoices/sessions)
        $tokens = $this->storage->getTokens($contextKey);
        $creds  = $this->storage->getSystemCreds($contextKey);

        $builder = $this->makeClientBuilder(
            companyId: $companyId,
            environment: $environment,
            overrideIdentifierNip: null,
            overrideApiUrl: null,
            withEncryptionKey: true
        );
        // Tokeny/creds są już ustawione przez makeClientBuilder(); tutaj zachowujemy tylko komunikaty diagnostyczne.
        $messages[] = ['stage' => 'setup', 'level' => 'info', 'ts' => $nowTs(), 'message' => 'Zbudowano klienta KSeF'];

        $client = $builder->build();
        $messages[] = ['stage' => 'setup', 'level' => 'success', 'ts' => $nowTs(), 'message' => 'Klient gotowy'];

        // Zbierz oczekiwany NIP (sprzedawcy) – wykorzystamy do ewentualnej korekty XML
        $expectedNipSource = null;
        $expectedNip = $this->resolveCompanyNip($companyId, $creds, $expectedNipSource);
        $sellerNip = $this->extractSellerNipFromXml($xml);
        // Zapisz migawkę oryginalnego XML (dla diagnostyki)
        try {
            $p2No = $this->extractInvoiceNumberFromXml($xml);
            $this->dumpXmlSnapshot($xml, 'orig', $companyId, $environment, $p2No);
        } catch (\Throwable $e) { /* ignore snapshot errors */ }
        if (!empty($expectedNip) && !empty($sellerNip) && $expectedNip !== $sellerNip) {
            $this->logDebug(sprintf('Seller NIP mismatch detected. XML=%s, expected=%s – will adjust XML before send.', $sellerNip, $expectedNip));
        }

        // 1) Otwórz sesję online (typed request/VO)
        $messages[] = ['stage' => 'open', 'level' => 'info', 'ts' => $nowTs(), 'message' => 'Otwieranie sesji online'];
        $open = $client->sessions()->online()->open(
            new OpenRequest(FormCodeVO::Fa3)
        )->object();

        $sessionRef = $open->referenceNumber ?? null;
        if (!$sessionRef) {
            throw new \RuntimeException('Nie udało się otworzyć sesji online (brak referenceNumber).');
        }
        $messages[] = ['stage' => 'open', 'level' => 'success', 'ts' => $nowTs(), 'message' => 'Sesja otwarta: ' . (string)$sessionRef];

        // 2) Wyślij dokument w postaci XML (typed request)
        // Na prośbę: wysyłamy ORYGINALNY XML bez sanitizacji.
        // Jeśli kiedykolwiek będzie potrzeba wrócić do sanitize, można to uczynić warunkowo przez config.
        $xmlToSend = $xml;
        $messages[] = ['stage' => 'send', 'level' => 'info', 'ts' => $nowTs(), 'message' => 'Wysyłanie dokumentu FA(3)'];
        $send = $client->sessions()->online()->send(
            new SendXmlRequest(
                referenceNumber: ReferenceNumberVO::from((string)$sessionRef),
                faktura: $xmlToSend
            )
        )->object();
        $messages[] = ['stage' => 'send', 'level' => 'success', 'ts' => $nowTs(), 'message' => 'Wysłano dokument'];
        // Zapisz migawkę XML faktycznie wysłanego
        try {
            $p2NoAfter = $this->extractInvoiceNumberFromXml($xmlToSend) ?: $p2No ?? null;
            $this->dumpXmlSnapshot($xmlToSend, 'sent', $companyId, $environment, $p2NoAfter);
        } catch (\Throwable $e) { /* ignore snapshot errors */ }

        $invoiceRef = $send->referenceNumber ?? null;
        if (!empty($invoiceRef)) {
            $messages[] = ['stage' => 'send', 'level' => 'info', 'ts' => $nowTs(), 'message' => 'Ref. dokumentu: ' . (string)$invoiceRef];
        }

        // 3) Zamknij sesję (niezależnie od powodzenia wysyłki)
        try {
            $messages[] = ['stage' => 'close', 'level' => 'info', 'ts' => $nowTs(), 'message' => 'Zamykanie sesji'];
            $client->sessions()->online()->close(
                new CloseRequest(ReferenceNumberVO::from((string)$sessionRef))
            );
            $messages[] = ['stage' => 'close', 'level' => 'success', 'ts' => $nowTs(), 'message' => 'Sesja zamknięta'];
        } catch (\Throwable $e) { /* non-fatal */ }

        // 4) Poll status do skutku (kilka prób co 1s)
        $status = null; $tries = 0; $max = 8; $sleepUs = 500000; // do ~4s łącznie
        $messages[] = ['stage' => 'status', 'level' => 'info', 'ts' => $nowTs(), 'message' => 'Sprawdzanie statusu dokumentu'];
        while ($tries++ < $max) {
            try {
                $status = $client->sessions()->invoices()->status(
                    new InvoicesStatusRequest(
                        referenceNumber: ReferenceNumberVO::from((string)$sessionRef),
                        invoiceReferenceNumber: ReferenceNumberVO::from((string)$invoiceRef)
                    )
                )->object();
            } catch (\Throwable $e) {
                // krótkie odczekanie i ponów
                usleep($sleepUs);
                continue;
            }
            $code = (int)($status->status->code ?? 0);
            if ($code === 200 || $code >= 400) {
                break;
            }
            usleep($sleepUs);
        }

        $code = (int)($status->status->code ?? 0);
        $desc = (string)($status->status->description ?? '');
        $ksefNumber = (string)($status->ksefNumber ?? '');
        $messages[] = ['stage' => 'status', 'level' => $code === 200 ? 'success' : 'warning', 'ts' => $nowTs(), 'message' => 'Status: ' . $code . ' ' . $desc];
        // Przy błędach semantycznych zapisz pełną odpowiedź do logów (ułatwia diagnostykę)
            if ($code >= 400 && class_exists('Cake\\Log\\Log')) {
            try {
                $raw = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                    \Cake\Log\Log::info('[KSeF status error] ' . ($raw ?: 'unable to encode status'));
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return [
            'ok' => $code === 200 && $ksefNumber !== '',
            'statusCode' => $code,
            'statusDesc' => $desc,
            'ksefNumber' => $ksefNumber,
            'sessionReference' => $sessionRef,
            'invoiceReference' => (string)$invoiceRef,
            'statusRaw' => isset($raw) ? (string)$raw : null,
            'messages' => $messages,
        ];
    }

    /**
     * Buduje dane dla widoku "received" (metadane faktur otrzymanych) tak, aby kontroler mógł być cienki.
     * Zwraca gotowe: invoices/stats/apiInfo/trace/diag/status/certInfo.
     */
    public function buildReceivedViewModel(string $companyId, string $environment, array $queryParams): array
    {
        $showTrace = (string)($queryParams['ksef_trace'] ?? '') === '1';
        if ($showTrace) {
            KsefHttpTrace::clear();
        }

        $q         = trim((string)($queryParams['q'] ?? ''));
        $ksefNo    = trim((string)($queryParams['ksef'] ?? ''));
        $invNo     = trim((string)($queryParams['inv'] ?? ''));
        $sellerNip = preg_replace('/\D/', '', (string)($queryParams['seller_nip'] ?? ''));
        $buyerNip  = preg_replace('/\D/', '', (string)($queryParams['buyer_nip'] ?? ''));
        $from      = $queryParams['from'] ?? null;
        $to        = $queryParams['to'] ?? null;
        $currency  = $queryParams['currency'] ?? null;
        $asNip     = preg_replace('/\D/', '', (string)($queryParams['as_nip'] ?? ''));

        $filters = [];
        if ($from || $to) {
            $fromIso = null;
            $toIso = null;
            try {
                if (is_string($from) && $from !== '') {
                    $fromIso = (new \DateTimeImmutable($from . ' 00:00:00', new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s\\Z');
                }
                if (is_string($to) && $to !== '') {
                    $toIso = (new \DateTimeImmutable($to . ' 23:59:59', new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s\\Z');
                }
            } catch (\Throwable) {
                $fromIso = null;
                $toIso = null;
            }
            $filters['dateRange'] = array_filter([
                'from' => $fromIso,
                'to' => $toIso,
                'dateType' => 'PermanentStorage',
            ]);
        }
        $filters['subjectType'] = 'Subject2';
        if ($ksefNo !== '') { $filters['ksefNumber'] = $ksefNo; }
        if ($invNo  !== '') { $filters['invoiceNumber'] = $invNo; }
        if ($sellerNip !== '') { $filters['sellerNip'] = $sellerNip; }
        if ($buyerNip  !== '') { $filters['buyerNip'] = $buyerNip; }

        $limit = 25;
        $page = max(1, (int)($queryParams['page'] ?? 1));
        $pageOffset = $page - 1;
        $pageSize = $limit;

        $diag = null;
        try {
            $diag = $this->diagnoseAuthContext($companyId, $environment, $asNip ?: null);
        } catch (\Throwable) {
            $diag = null;
        }

        $usingMaster = true;
        $usingMasterMode = null;
        $identifierNip = null;
        if (is_array($diag)) {
            $identifierNip = (string)($diag['identifierNip'] ?? '') ?: null;
            if (($diag['authMethod'] ?? '') === 'certificate') {
                if (!empty($diag['masterCertCompanyId']) && ($diag['certCompanyId'] ?? '') !== $companyId) {
                    $usingMaster = true;
                    $usingMasterMode = 'configured';
                } elseif (($diag['certSource'] ?? null) === 'master') {
                    $usingMaster = true;
                    $usingMasterMode = 'fallback';
                }
            }
        }

        $result = null;
        $error = null;
        $errorDetails = null;
        try {
            $result = $this->queryReceivedMetadata($companyId, $environment, $filters, $pageOffset, $pageSize, $asNip ?: null, $showTrace);
        } catch (\Throwable $e) {
            $errorDetails = $e->getMessage();
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                try {
                    $res = $e->getResponse();
                    $status = $res?->getStatusCode();
                    $body = (string)$res?->getBody();
                    if ($body !== '') {
                        $errorDetails = 'HTTP ' . $status . ' - ' . $e->getMessage() . ' | body: ' . substr($body, 0, 500);
                    } else {
                        $errorDetails = 'HTTP ' . $status . ' - ' . $e->getMessage();
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
            $error = 'Błąd połączenia z KSeF: ' . $errorDetails;
            if (class_exists('Cake\\Log\\Log')) {
                try {
                    \Cake\Log\Log::error('[KSeF] Błąd autoryzacji/zapytania: ' . $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                } catch (\Throwable) {
                    // ignore
                }
            }
            $result = ['items' => [], 'total' => 0];
        }

        $items = self::extractKsefItemsStatic(is_array($result) ? $result : []);
        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $items = array_values(array_filter($items, function ($row) use ($qLower) {
                $num  = (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefReferenceNumber'] ?? '');
                $sell = (string)($row['sellerName'] ?? $row['supplierName'] ?? '');
                $nip  = (string)($row['sellerNip'] ?? $row['supplierNip'] ?? '');
                return str_contains(mb_strtolower($num), $qLower)
                    || str_contains(mb_strtolower($sell), $qLower)
                    || str_contains(mb_strtolower($nip), $qLower);
            }));
        }

        $invoices = array_map(function ($row) {
            $date = (string)($row['issueDate'] ?? $row['publishedDate'] ?? $row['date'] ?? null);
            $total = (float)($row['grossAmount'] ?? $row['totalGross'] ?? $row['gross'] ?? $row['total'] ?? 0);
            $sellerArr = is_array($row['seller'] ?? null) ? ($row['seller'] ?? []) : [];
            $contractorName = (string)($row['sellerName'] ?? $row['supplierName'] ?? $sellerArr['name'] ?? $sellerArr['fullName'] ?? '');
            $contractorNip  = (string)($row['sellerNip'] ?? $row['supplierNip'] ?? $sellerArr['nip'] ?? $sellerArr['taxId'] ?? '');
            $dateObj = null;
            if ($date) {
                try {
                    $dateObj = new \Cake\I18n\FrozenDate(substr($date, 0, 10));
                } catch (\Throwable) {
                    $dateObj = null;
                }
            }
            return [
                'fullnumber' => (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'ksef_number'=> (string)($row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'date'       => $dateObj,
                'total'      => $total,
                'currency'   => (string)($row['currency'] ?? 'PLN'),
                'paymentstate'=> null,
                'paymentdate'=> null,
                'invoicingMode'   => (string)($row['invoicingMode'] ?? ''),
                'invoiceType'     => (string)($row['invoiceType'] ?? ''),
                'isSelfInvoicing' => (bool)($row['isSelfInvoicing'] ?? false),
                'hasAttachment'   => (bool)($row['hasAttachment'] ?? false),
                'InvoiceContractors' => [
                    'name'   => $contractorName,
                    'tax_id' => $contractorNip,
                ],
            ];
        }, $items);

        $fetched = count($invoices);
        $totalFromApi = (int)($result['result']['count'] ?? $result['total'] ?? $result['count'] ?? 0);
        if ($totalFromApi === 0 && isset($result['invoices']) && is_array($result['invoices'])) {
            $totalFromApi = count($result['invoices']);
        }
        $apiInfo = [
            'total' => $totalFromApi,
            'hasMore' => (bool)($result['hasMore'] ?? $result['result']['hasMore'] ?? false),
            'isTruncated' => (bool)($result['isTruncated'] ?? $result['result']['isTruncated'] ?? false),
        ];

        $statsCurrency = $currency ? strtoupper((string)$currency) : 'PLN';
        $yearTotal = array_reduce($invoices, fn($c, $i) => $c + (float)($i['total'] ?? 0), 0.0);
        $yearCount = count($invoices);
        $stats = [
            'currency'         => $statsCurrency,
            'year_total'       => $yearTotal,
            'year_count'       => $yearCount,
            'year_paid'        => 0.0,
            'paid_total'       => 0.0,
            'paid_count'       => 0,
            'paid_avg'         => 0.0,
            'pending_count'    => 0,
            'pending_total'    => 0.0,
            'remaining_total'  => 0.0,
            'overdue_count'    => 0,
            'overdue_total'    => 0.0,
            'overdue_max_days' => 0,
            'month_paid_count' => 0,
        ];

        $certInfo = null;
        try {
            $metaPath = ROOT . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'ksef_certs' . DIRECTORY_SEPARATOR . 'company_' . $companyId . DIRECTORY_SEPARATOR . (($environment === 'prod') ? 'prod' : 'test') . DIRECTORY_SEPARATOR . 'meta.json';
            if (is_file($metaPath)) {
                $metaJson = @file_get_contents($metaPath);
                $certInfo = json_decode((string)$metaJson, true) ?: null;
            }
        } catch (\Throwable) {
            $certInfo = null;
        }

        $status = [
            'active' => $error === null,
            'env' => $environment,
            'ts' => time(),
            'lastError' => $errorDetails,
            'usingMaster' => $usingMaster,
            'usingMasterMode' => $usingMasterMode,
            'identifierNip' => $identifierNip,
            'authMethod' => is_array($diag) ? ($diag['authMethod'] ?? null) : null,
            'certSource' => is_array($diag) ? ($diag['certSource'] ?? null) : null,
        ];

        $trace = $showTrace ? KsefHttpTrace::all() : [];

        $flash = null;
        if ($error !== null) {
            $flash = ['type' => 'error', 'message' => $error];
        } elseif ($fetched > 0) {
            $flash = ['type' => 'success', 'message' => sprintf('Pobrano %d pozycji z KSeF (strona %d).%s', $fetched, $page, $totalFromApi ? " Łącznie: $totalFromApi." : '')];
        } else {
            $flash = ['type' => 'info', 'message' => 'Brak faktur w KSeF dla wybranych filtrów. Zmień filtry i spróbuj ponownie.'];
        }

        $debugEnabled = (string)($queryParams['debug'] ?? '') !== '';
        $ksefRaw = $debugEnabled ? $result : null;

        return [
            'invoices' => $invoices,
            'stats' => $stats,
            'apiInfo' => $apiInfo,
            'certInfo' => $certInfo,
            'ksefEnv' => $environment,
            'ksefTraceEnabled' => $showTrace,
            'ksefTrace' => $trace,
            'ksefDiag' => $showTrace ? $diag : null,
            'ksefRaw' => $ksefRaw,
            'status' => $status,
            'flash' => $flash,
        ];
    }

    /**
     * Widok-model dla listy "issued" (faktury wystawione).
     * Technicznie to ten sam endpoint metadata (biblioteka n1ebieski), różni się perspektywa/mapowanie.
     */
    public function buildIssuedViewModel(string $companyId, string $environment, array $queryParams): array
    {
        $showTrace = (string)($queryParams['ksef_trace'] ?? '') === '1';
        if ($showTrace) {
            KsefHttpTrace::clear();
        }

        $q         = trim((string)($queryParams['q'] ?? ''));
        $ksefNo    = trim((string)($queryParams['ksef'] ?? ''));
        $invNo     = trim((string)($queryParams['inv'] ?? ''));
        $sellerNip = preg_replace('/\D/', '', (string)($queryParams['seller_nip'] ?? ''));
        $buyerNip  = preg_replace('/\D/', '', (string)($queryParams['buyer_nip'] ?? ''));
        $from      = $queryParams['from'] ?? null;
        $to        = $queryParams['to'] ?? null;
        $currency  = $queryParams['currency'] ?? null;
        $asNip     = preg_replace('/\D/', '', (string)($queryParams['as_nip'] ?? ''));

        $filters = [];
        if ($from || $to) {
            $fromIso = null;
            $toIso = null;
            try {
                if (is_string($from) && $from !== '') {
                    $fromIso = (new \DateTimeImmutable($from . ' 00:00:00', new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s\\Z');
                }
                if (is_string($to) && $to !== '') {
                    $toIso = (new \DateTimeImmutable($to . ' 23:59:59', new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s\\Z');
                }
            } catch (\Throwable) {
                $fromIso = null;
                $toIso = null;
            }
            $filters['dateRange'] = array_filter([
                'from' => $fromIso,
                'to' => $toIso,
                'dateType' => 'PermanentStorage',
            ]);
        }
        // Perspektywa sprzedawcy
        $filters['subjectType'] = 'Subject1';
        if ($ksefNo !== '') { $filters['ksefNumber'] = $ksefNo; }
        if ($invNo  !== '') { $filters['invoiceNumber'] = $invNo; }
        if ($sellerNip !== '') { $filters['sellerNip'] = $sellerNip; }
        if ($buyerNip  !== '') { $filters['buyerNip'] = $buyerNip; }

        $limit = 25;
        $page = max(1, (int)($queryParams['page'] ?? 1));
        $pageOffset = $page - 1;
        $pageSize = $limit;

        $diag = null;
        try {
            $diag = $this->diagnoseAuthContext($companyId, $environment, $asNip ?: null);
        } catch (\Throwable) {
            $diag = null;
        }

        $usingMaster = false;
        $usingMasterMode = null;
        $identifierNip = null;
        if (is_array($diag)) {
            $identifierNip = (string)($diag['identifierNip'] ?? '') ?: null;
            if (($diag['authMethod'] ?? '') === 'certificate') {
                if (!empty($diag['masterCertCompanyId']) && ($diag['certCompanyId'] ?? '') !== $companyId) {
                    $usingMaster = true;
                    $usingMasterMode = 'configured';
                } elseif (($diag['certSource'] ?? null) === 'master') {
                    $usingMaster = true;
                    $usingMasterMode = 'fallback';
                }
            }
        }

        $result = null;
        $error = null;
        $errorDetails = null;
        try {
            $result = $this->queryReceivedMetadata($companyId, $environment, $filters, $pageOffset, $pageSize, $asNip ?: null, $showTrace);
        } catch (\Throwable $e) {
            $errorDetails = $e->getMessage();
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                try {
                    $res = $e->getResponse();
                    $status = $res?->getStatusCode();
                    $body = (string)$res?->getBody();
                    if ($body !== '') {
                        $errorDetails = 'HTTP ' . $status . ' - ' . $e->getMessage() . ' | body: ' . substr($body, 0, 500);
                    } else {
                        $errorDetails = 'HTTP ' . $status . ' - ' . $e->getMessage();
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
            $error = 'Błąd połączenia z KSeF: ' . $errorDetails;
            if (class_exists('Cake\\Log\\Log')) {
                try {
                    \Cake\Log\Log::error('[KSeF] Błąd autoryzacji/zapytania (issued): ' . $e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                } catch (\Throwable) {
                    // ignore
                }
            }
            $result = ['items' => [], 'total' => 0];
        }

        $items = self::extractKsefItemsStatic(is_array($result) ? $result : []);
        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $items = array_values(array_filter($items, function ($row) use ($qLower) {
                $num  = (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefReferenceNumber'] ?? '');
                $buyer = (string)($row['buyerName'] ?? $row['purchaserName'] ?? '');
                $nip  = (string)($row['buyerNip'] ?? $row['purchaserNip'] ?? '');
                return str_contains(mb_strtolower($num), $qLower)
                    || str_contains(mb_strtolower($buyer), $qLower)
                    || str_contains(mb_strtolower($nip), $qLower);
            }));
        }

        $invoices = array_map(function ($row) {
            $date = (string)($row['issueDate'] ?? $row['publishedDate'] ?? $row['date'] ?? null);
            $total = (float)($row['grossAmount'] ?? $row['totalGross'] ?? $row['gross'] ?? $row['total'] ?? 0);
            $buyerArr = is_array($row['buyer'] ?? null) ? ($row['buyer'] ?? []) : [];
            $buyerName = (string)($row['buyerName'] ?? $row['purchaserName'] ?? $buyerArr['name'] ?? $buyerArr['fullName'] ?? '');
            $buyerNip  = (string)($row['buyerNip'] ?? $row['purchaserNip'] ?? $buyerArr['nip'] ?? $buyerArr['taxId'] ?? '');
            $dateObj = null;
            if ($date) {
                try {
                    $dateObj = new \Cake\I18n\FrozenDate(substr($date, 0, 10));
                } catch (\Throwable) {
                    $dateObj = null;
                }
            }
            return [
                'fullnumber' => (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'ksef_number'=> (string)($row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'date'       => $dateObj,
                'total'      => $total,
                'currency'   => (string)($row['currency'] ?? 'PLN'),
                'paymentstate'=> null,
                'paymentdate'=> null,
                'invoicingMode'   => (string)($row['invoicingMode'] ?? ''),
                'invoiceType'     => (string)($row['invoiceType'] ?? ''),
                'isSelfInvoicing' => (bool)($row['isSelfInvoicing'] ?? false),
                'hasAttachment'   => (bool)($row['hasAttachment'] ?? false),
                'InvoiceContractors' => [
                    'name'   => $buyerName,
                    'tax_id' => $buyerNip,
                ],
            ];
        }, $items);

        $fetched = count($invoices);
        $totalFromApi = (int)($result['result']['count'] ?? $result['total'] ?? $result['count'] ?? 0);
        if ($totalFromApi === 0 && isset($result['invoices']) && is_array($result['invoices'])) {
            $totalFromApi = count($result['invoices']);
        }
        $apiInfo = [
            'total' => $totalFromApi,
            'hasMore' => (bool)($result['hasMore'] ?? $result['result']['hasMore'] ?? false),
            'isTruncated' => (bool)($result['isTruncated'] ?? $result['result']['isTruncated'] ?? false),
        ];

        $statsCurrency = $currency ? strtoupper((string)$currency) : 'PLN';
        $yearTotal = array_reduce($invoices, fn($c, $i) => $c + (float)($i['total'] ?? 0), 0.0);
        $yearCount = count($invoices);
        $stats = [
            'currency'         => $statsCurrency,
            'year_total'       => $yearTotal,
            'year_count'       => $yearCount,
            'year_paid'        => 0.0,
            'paid_total'       => 0.0,
            'paid_count'       => 0,
            'paid_avg'         => 0.0,
            'pending_count'    => 0,
            'pending_total'    => 0.0,
            'remaining_total'  => 0.0,
            'overdue_count'    => 0,
            'overdue_total'    => 0.0,
            'overdue_max_days' => 0,
            'month_paid_count' => 0,
        ];

        $certInfo = null;
        try {
            $metaPath = ROOT . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'ksef_certs' . DIRECTORY_SEPARATOR . 'company_' . $companyId . DIRECTORY_SEPARATOR . (($environment === 'prod') ? 'prod' : 'test') . DIRECTORY_SEPARATOR . 'meta.json';
            if (is_file($metaPath)) {
                $metaJson = @file_get_contents($metaPath);
                $certInfo = json_decode((string)$metaJson, true) ?: null;
            }
        } catch (\Throwable) {
            $certInfo = null;
        }

        $status = [
            'active' => $error === null,
            'env' => $environment,
            'ts' => time(),
            'lastError' => $errorDetails,
            'usingMaster' => $usingMaster,
            'usingMasterMode' => $usingMasterMode,
            'identifierNip' => $identifierNip,
            'authMethod' => is_array($diag) ? ($diag['authMethod'] ?? null) : null,
            'certSource' => is_array($diag) ? ($diag['certSource'] ?? null) : null,
        ];

        $trace = $showTrace ? KsefHttpTrace::all() : [];

        $flash = null;
        if ($error !== null) {
            $flash = ['type' => 'error', 'message' => $error];
        } elseif ($fetched > 0) {
            $flash = ['type' => 'success', 'message' => sprintf('Pobrano %d wystawionych z KSeF (strona %d).%s', $fetched, $page, $totalFromApi ? " Łącznie: $totalFromApi." : '')];
        } else {
            $flash = ['type' => 'info', 'message' => 'Brak wystawionych w KSeF dla wybranych filtrów.'];
        }

        $debugEnabled = (string)($queryParams['debug'] ?? '') !== '';
        $ksefRaw = $debugEnabled ? $result : null;

        return [
            'invoices' => $invoices,
            'stats' => $stats,
            'apiInfo' => $apiInfo,
            'certInfo' => $certInfo,
            'ksefEnv' => $environment,
            'ksefTraceEnabled' => $showTrace,
            'ksefTrace' => $trace,
            'ksefDiag' => $showTrace ? $diag : null,
            'ksefRaw' => $ksefRaw,
            'status' => $status,
            'flash' => $flash,
        ];
    }
    
    /**
     * Buduje payload dla API listy "received" (JSON), aby kontroler nie miał logiki KSeF.
     * Zwraca ['payload' => array, 'status' => array|null].
     */
    public function buildReceivedApiResult(string $companyId, array $queryParams): array
    {
        return $this->buildInvoicesApiResult(
            companyId: $companyId,
            queryParams: $queryParams,
            kind: 'received'
        );
    }
    
    /**
     * Buduje payload dla API listy "issued" (JSON), aby kontroler nie miał logiki KSeF.
     * Zwraca ['payload' => array, 'status' => array|null].
     */
    public function buildIssuedApiResult(string $companyId, array $queryParams): array
    {
        return $this->buildInvoicesApiResult(
            companyId: $companyId,
            queryParams: $queryParams,
            kind: 'issued'
        );
    }

    /**
     * Buduje payload dla API uprawnień (personal grants) – JSON.
     * Zwraca ['payload' => array, 'status' => array|null].
     */
    public function buildPersonalGrantsApiResult(string $companyId, array $queryParams): array
    {
        return $this->buildPermissionsApiResult(
            companyId: $companyId,
            queryParams: $queryParams,
            kind: 'personal'
        );
    }

    /**
     * Buduje payload dla API uprawnień podmiotowych (authorizations grants) – JSON.
     * Zwraca ['payload' => array, 'status' => array|null].
     */
    public function buildAuthorizationsGrantsApiResult(string $companyId, array $queryParams): array
    {
        return $this->buildPermissionsApiResult(
            companyId: $companyId,
            queryParams: $queryParams,
            kind: 'authorizations'
        );
    }

    /**
     * Buduje dane dla widoku listy uprawnień (personal grants) tak, aby kontroler był cienki.
     */
    public function buildPersonalGrantsViewModel(string $companyId, string $environment, array $queryParams): array
    {
        return $this->buildPermissionsViewModel(
            companyId: $companyId,
            environment: $environment,
            queryParams: $queryParams,
            kind: 'personal'
        );
    }

    /**
     * Buduje dane dla widoku listy uprawnień podmiotowych (authorizations grants) tak, aby kontroler był cienki.
     */
    public function buildAuthorizationsGrantsViewModel(string $companyId, string $environment, array $queryParams): array
    {
        return $this->buildPermissionsViewModel(
            companyId: $companyId,
            environment: $environment,
            queryParams: $queryParams,
            kind: 'authorizations'
        );
    }
    
    /**
     * Wspólna implementacja dla receivedApi/issuedApi.
     * Zachowuje obecny kształt odpowiedzi JSON z kontrolera (success/error, env/page/limit/total/fetched/filters/items).
     */
    private function buildInvoicesApiResult(string $companyId, array $queryParams, string $kind): array
    {
        $showTrace = (string)($queryParams['ksef_trace'] ?? '') === '1';
        if ($showTrace) {
            KsefHttpTrace::clear();
        }

        $q         = trim((string)($queryParams['q'] ?? ''));
        $ksefNo    = trim((string)($queryParams['ksef'] ?? ''));
        $invNo     = trim((string)($queryParams['inv'] ?? ''));
        $sellerNip = preg_replace('/\D/', '', (string)($queryParams['seller_nip'] ?? ''));
        $buyerNip  = preg_replace('/\D/', '', (string)($queryParams['buyer_nip'] ?? ''));
        $from      = $queryParams['from'] ?? null;
        $to        = $queryParams['to'] ?? null;
        $currency  = $queryParams['currency'] ?? null;
        $asNip     = preg_replace('/\D/', '', (string)($queryParams['as_nip'] ?? ''));
        
        $envRaw = (string)($queryParams['env'] ?? 'test');
        $environment = $envRaw;
        if ($kind === 'received') {
            $environment = ($envRaw === 'prod') ? 'prod' : 'test';
        }
        
        $filters = [];
        if ($from || $to) {
            if ($kind === 'received') {
                $fromIso = $from ? (new \Cake\I18n\FrozenDate((string)$from))->format('Y-m-d') . 'T00:00:00' : null;
                $toIso   = $to   ? (new \Cake\I18n\FrozenDate((string)$to))->format('Y-m-d')   . 'T23:59:59' : null;
            } else {
                $fromIso = $from ? (new \Cake\I18n\FrozenDate((string)$from))->format('Y-m-d') . 'T00:00:00Z' : null;
                $toIso   = $to   ? (new \Cake\I18n\FrozenDate((string)$to))->format('Y-m-d')   . 'T23:59:59Z' : null;
            }
            $filters['dateRange'] = array_filter([
                'from'     => $fromIso,
                'to'       => $toIso,
                'dateType' => 'Issue',
            ]);
        }
        $filters['subjectType'] = ($kind === 'received') ? 'Subject2' : 'Subject1';
        if ($ksefNo !== '') { $filters['ksefNumber'] = $ksefNo; }
        if ($invNo  !== '') { $filters['invoiceNumber'] = $invNo; }
        if ($sellerNip !== '') { $filters['sellerNip'] = $sellerNip; }
        if ($buyerNip  !== '') { $filters['buyerNip'] = $buyerNip; }
        
        $limit = 25;
        $page  = max(1, (int)($queryParams['page'] ?? 1));
        // Ujednolicenie: `page` to numer strony (1-based), a do KSeF przekazujemy offset stron (0-based).
        // To jest spójne z buildReceivedViewModel()/buildIssuedViewModel().
        $pageOffset = $page - 1;
        $pageSize = $limit;
        
        $diag = null;
        $usingMaster = false;
        $usingMasterMode = null;
        $identifierNip = null;
        try {
            $diag = $this->diagnoseAuthContext($companyId, $environment, $asNip ?: null);
            if (is_array($diag)) {
                $identifierNip = (string)($diag['identifierNip'] ?? '') ?: null;
                if (($diag['authMethod'] ?? '') === 'certificate') {
                    if (!empty($diag['masterCertCompanyId']) && ($diag['certCompanyId'] ?? '') !== $companyId) {
                        $usingMaster = true;
                        $usingMasterMode = 'configured';
                    } elseif (($diag['certSource'] ?? null) === 'master') {
                        $usingMaster = true;
                        $usingMasterMode = 'fallback';
                    }
                }
            }
        } catch (\Throwable) {
            $diag = null;
        }
        
        try {
            $result = $this->queryReceivedMetadata($companyId, $environment, $filters, $pageOffset, $pageSize, $asNip ?: null, $showTrace);
        } catch (\Throwable $e) {
            $details = $e->getMessage();
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                try {
                    $res = $e->getResponse();
                    $status = $res?->getStatusCode();
                    $body = (string)$res?->getBody();
                    $details = $body !== ''
                        ? ('HTTP ' . $status . ' - ' . $e->getMessage() . ' | body: ' . substr($body, 0, 500))
                        : ('HTTP ' . $status . ' - ' . $e->getMessage());
                } catch (\Throwable) {
                    // ignore
                }
            }
            
            return [
                'payload' => [
                    'success' => false,
                    'error' => $details,
                ],
                'status' => [
                    'active' => false,
                    'env' => $environment,
                    'ts' => time(),
                    'lastError' => $details,
                    'usingMaster' => $usingMaster,
                    'usingMasterMode' => $usingMasterMode,
                    'identifierNip' => $identifierNip,
                    'authMethod' => is_array($diag) ? ($diag['authMethod'] ?? null) : null,
                    'certSource' => is_array($diag) ? ($diag['certSource'] ?? null) : null,
                ],
            ];
        }
        
        $items = self::extractKsefItemsStatic(is_array($result) ? $result : []);
        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $items = array_values(array_filter($items, function ($row) use ($qLower, $kind) {
                $num  = (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefReferenceNumber'] ?? '');
                if ($kind === 'received') {
                    $name = (string)($row['sellerName'] ?? $row['supplierName'] ?? '');
                    $nip  = (string)($row['sellerNip'] ?? $row['supplierNip'] ?? '');
                } else {
                    $name = (string)($row['buyerName'] ?? $row['purchaserName'] ?? '');
                    $nip  = (string)($row['buyerNip'] ?? $row['purchaserNip'] ?? '');
                }
                return str_contains(mb_strtolower($num), $qLower)
                    || str_contains(mb_strtolower($name), $qLower)
                    || str_contains(mb_strtolower($nip), $qLower);
            }));
        }
        
        $invoices = array_map(function ($row) use ($kind) {
            $raw = is_array($row) ? $row : [];
            $date = (string)($row['issueDate'] ?? $row['publishedDate'] ?? $row['date'] ?? null);
            $total = (float)($row['grossAmount'] ?? $row['totalGross'] ?? $row['gross'] ?? $row['total'] ?? 0);
            $invoiceType = trim((string)($row['invoiceType'] ?? $row['invoice_type'] ?? ''));
            if ($invoiceType === '') {
                $invoiceType = null;
            }
            
            if ($kind === 'received') {
                $arr = is_array($row['seller'] ?? null) ? ($row['seller'] ?? []) : [];
                $contractorName = (string)($row['sellerName'] ?? $row['supplierName'] ?? $arr['name'] ?? $arr['fullName'] ?? '');
                $contractorNip  = (string)($row['sellerNip'] ?? $row['supplierNip'] ?? $arr['nip'] ?? $arr['taxId'] ?? '');
            } else {
                $arr = is_array($row['buyer'] ?? null) ? ($row['buyer'] ?? []) : [];
                $contractorName = (string)($row['buyerName'] ?? $row['purchaserName'] ?? $arr['name'] ?? $arr['fullName'] ?? '');
                $contractorNip  = (string)($row['buyerNip'] ?? $row['purchaserNip'] ?? $arr['nip'] ?? $arr['taxId'] ?? '');
            }
            
            $base = [
                'fullnumber' => (string)($row['invoiceNumber'] ?? $row['number'] ?? $row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'ksef_number'=> (string)($row['ksefNumber'] ?? $row['ksefReferenceNumber'] ?? ''),
                'date'       => $date ? substr($date, 0, 10) : null,
                'total'      => $total,
                'currency'   => (string)($row['currency'] ?? 'PLN'),
                'invoice_type' => $invoiceType,
                'InvoiceContractors' => [
                    'name'   => $contractorName,
                    'tax_id' => $contractorNip,
                ],
            ];
            
            return $raw + $base;
        }, $items);
        
        $totalFromApi = (int)($result['result']['count'] ?? $result['total'] ?? $result['count'] ?? 0);
        if ($totalFromApi === 0 && isset($result['invoices']) && is_array($result['invoices'])) {
            $totalFromApi = count($result['invoices']);
        }
        
        return [
            'payload' => [
                'success' => true,
                'env' => $environment,
                'page' => $page,
                'limit' => $limit,
                'total' => $totalFromApi,
                'fetched' => count($invoices),
                'filters' => [
                    'q' => $q,
                    'ksef' => $ksefNo,
                    'inv' => $invNo,
                    'seller_nip' => $sellerNip,
                    'buyer_nip' => $buyerNip,
                    'from' => $from,
                    'to' => $to,
                    'currency' => $currency,
                    'as_nip' => $asNip,
                ],
                'items' => $invoices,
            ],
            'status' => [
                'active' => true,
                'env' => $environment,
                'ts' => time(),
                'lastError' => null,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => is_array($diag) ? ($diag['authMethod'] ?? null) : null,
                'certSource' => is_array($diag) ? ($diag['certSource'] ?? null) : null,
            ],
        ];
    }

    /**
     * Wspólna implementacja dla API uprawnień.
     * `page` jest 1-based, a do KSeF przekazujemy `pageOffset` jako indeks strony 0-based.
     */
    private function buildPermissionsApiResult(string $companyId, array $queryParams, string $kind): array
    {
        $envRaw = (string)($queryParams['env'] ?? 'test');
        $environment = ($envRaw === 'prod') ? 'prod' : 'test';

        $asNip = preg_replace('/\D/', '', (string)($queryParams['as_nip'] ?? ''));

        $limit = (int)($queryParams['limit'] ?? 10);
        if ($limit <= 0) {
            $limit = 10;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $page = max(1, (int)($queryParams['page'] ?? 1));
        $pageOffset = $page - 1;
        $pageSize = $limit;

        $filters = [];
        if ($kind === 'personal') {
            $contextNip = preg_replace('/\D/', '', (string)($queryParams['context_nip'] ?? ''));
            if ($contextNip !== '') {
                $filters['contextIdentifier'] = ['type' => 'Nip', 'value' => $contextNip];
            }

            $targetType = (string)($queryParams['target_type'] ?? '');
            $targetValue = (string)($queryParams['target_value'] ?? '');
            if ($targetType !== '') {
                $filters['targetIdentifier'] = [
                    'type' => $targetType,
                    'value' => $targetValue,
                ];
            }

            $permissionState = (string)($queryParams['permission_state'] ?? '');
            if ($permissionState !== '') {
                $filters['permissionState'] = $permissionState;
            }

            $permissionTypes = $queryParams['permission_types'] ?? $queryParams['permissionTypes'] ?? [];
            if (is_string($permissionTypes) && $permissionTypes !== '') {
                $permissionTypes = preg_split('/\s*,\s*/', $permissionTypes) ?: [];
            }
            if (is_array($permissionTypes)) {
                $filters['permissionTypes'] = array_values(array_filter($permissionTypes, fn($v) => is_string($v) && $v !== ''));
            }
        } else {
            $queryType = (string)($queryParams['query_type'] ?? $queryParams['queryType'] ?? '');
            if ($queryType !== '') {
                $filters['queryType'] = $queryType;
            }

            $authorizingNip = preg_replace('/\D/', '', (string)($queryParams['authorizing_nip'] ?? ''));
            if ($authorizingNip !== '') {
                $filters['authorizingIdentifier'] = ['type' => 'Nip', 'value' => $authorizingNip];
            }

            $authorizedNip = preg_replace('/\D/', '', (string)($queryParams['authorized_nip'] ?? ''));
            if ($authorizedNip !== '') {
                $filters['authorizedIdentifier'] = ['type' => 'Nip', 'value' => $authorizedNip];
            }

            $permissionTypes = $queryParams['permission_types'] ?? $queryParams['permissionTypes'] ?? [];
            if (is_string($permissionTypes) && $permissionTypes !== '') {
                $permissionTypes = preg_split('/\s*,\s*/', $permissionTypes) ?: [];
            }
            if (is_array($permissionTypes)) {
                $filters['permissionTypes'] = array_values(array_filter($permissionTypes, fn($v) => is_string($v) && $v !== ''));
            }
        }

        $diag = null;
        $usingMaster = false;
        $usingMasterMode = null;
        $identifierNip = null;
        try {
            $diag = $this->diagnoseAuthContext($companyId, $environment, $asNip ?: null);
            if (is_array($diag)) {
                $identifierNip = (string)($diag['identifierNip'] ?? '') ?: null;
                if (($diag['authMethod'] ?? '') === 'certificate') {
                    if (!empty($diag['masterCertCompanyId']) && ($diag['certCompanyId'] ?? '') !== $companyId) {
                        $usingMaster = true;
                        $usingMasterMode = 'configured';
                    } elseif (($diag['certSource'] ?? null) === 'master') {
                        $usingMaster = true;
                        $usingMasterMode = 'fallback';
                    }
                }
            }
        } catch (\Throwable) {
            $diag = null;
        }

        try {
            if ($kind === 'personal') {
                $resp = $this->queryPersonalGrants($companyId, $environment, $filters, $pageOffset, $pageSize, $asNip ?: null);
            } else {
                $resp = $this->queryAuthorizationGrants($companyId, $environment, $filters, $pageOffset, $pageSize, $asNip ?: null);
            }
        } catch (\Throwable $e) {
            $details = $e->getMessage();
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                try {
                    $res = $e->getResponse();
                    $status = $res?->getStatusCode();
                    $body = (string)$res?->getBody();
                    $details = $body !== ''
                        ? ('HTTP ' . $status . ' - ' . $e->getMessage() . ' | body: ' . substr($body, 0, 500))
                        : ('HTTP ' . $status . ' - ' . $e->getMessage());
                } catch (\Throwable) {
                    // ignore
                }
            }

            return [
                'payload' => [
                    'success' => false,
                    'error' => $details,
                ],
                'status' => [
                    'active' => false,
                    'env' => $environment,
                    'ts' => time(),
                    'lastError' => $details,
                    'usingMaster' => $usingMaster,
                    'usingMasterMode' => $usingMasterMode,
                    'identifierNip' => $identifierNip,
                    'authMethod' => is_array($diag) ? ($diag['authMethod'] ?? null) : null,
                    'certSource' => is_array($diag) ? ($diag['certSource'] ?? null) : null,
                ],
            ];
        }

        $items = [];
        if (is_array($resp)) {
            if (isset($resp['permissions']) && is_array($resp['permissions'])) {
                $items = $resp['permissions'];
            } elseif (isset($resp['items']) && is_array($resp['items'])) {
                $items = $resp['items'];
            }
        }
        $hasMore = (bool)($resp['hasMore'] ?? false);

        return [
            'payload' => [
                'success' => true,
                'env' => $environment,
                'kind' => $kind,
                'page' => $page,
                'limit' => $limit,
                'hasMore' => $hasMore,
                'fetched' => is_array($items) ? count($items) : 0,
                'filters' => $filters,
                'items' => $items,
            ],
            'status' => [
                'active' => true,
                'env' => $environment,
                'ts' => time(),
                'lastError' => null,
                'usingMaster' => $usingMaster,
                'usingMasterMode' => $usingMasterMode,
                'identifierNip' => $identifierNip,
                'authMethod' => is_array($diag) ? ($diag['authMethod'] ?? null) : null,
                'certSource' => is_array($diag) ? ($diag['certSource'] ?? null) : null,
            ],
        ];
    }

    /**
     * Widok-model dla UI uprawnień (personal/authorizations).
     * Zwraca: items/apiInfo/trace/diag/status/flash + parametry formularza.
     */
    private function buildPermissionsViewModel(string $companyId, string $environment, array $queryParams, string $kind): array
    {
        $showTrace = (string)($queryParams['ksef_trace'] ?? '') === '1';
        if ($showTrace) {
            KsefHttpTrace::clear();
        }

        $env = ($environment === 'prod') ? 'prod' : 'test';
        $asNip = preg_replace('/\D/', '', (string)($queryParams['as_nip'] ?? ''));

        $limit = (int)($queryParams['limit'] ?? 10);
        if ($limit <= 0) { $limit = 10; }
        if ($limit > 100) { $limit = 100; }

        $page = max(1, (int)($queryParams['page'] ?? 1));
        $pageOffset = $page - 1;
        $pageSize = $limit;

        $filters = [];
        if ($kind === 'personal') {
            $contextNip = preg_replace('/\D/', '', (string)($queryParams['context_nip'] ?? ''));
            if ($contextNip !== '') {
                $filters['contextIdentifier'] = ['type' => 'Nip', 'value' => $contextNip];
            }

            $targetType = (string)($queryParams['target_type'] ?? '');
            $targetValue = (string)($queryParams['target_value'] ?? '');
            if ($targetType !== '') {
                $filters['targetIdentifier'] = ['type' => $targetType, 'value' => $targetValue];
            }

            $permissionState = (string)($queryParams['permission_state'] ?? '');
            if ($permissionState !== '') {
                $filters['permissionState'] = $permissionState;
            }

            $permissionTypes = $queryParams['permission_types'] ?? $queryParams['permissionTypes'] ?? [];
            if (is_string($permissionTypes) && $permissionTypes !== '') {
                $permissionTypes = preg_split('/\s*,\s*/', $permissionTypes) ?: [];
            }
            if (is_array($permissionTypes)) {
                $filters['permissionTypes'] = array_values(array_filter($permissionTypes, fn($v) => is_string($v) && $v !== ''));
            }
        } else {
            $queryType = (string)($queryParams['query_type'] ?? $queryParams['queryType'] ?? '');
            if ($queryType !== '') {
                $filters['queryType'] = $queryType;
            }

            $authorizingNip = preg_replace('/\D/', '', (string)($queryParams['authorizing_nip'] ?? ''));
            if ($authorizingNip !== '') {
                $filters['authorizingIdentifier'] = ['type' => 'Nip', 'value' => $authorizingNip];
            }

            $authorizedNip = preg_replace('/\D/', '', (string)($queryParams['authorized_nip'] ?? ''));
            if ($authorizedNip !== '') {
                $filters['authorizedIdentifier'] = ['type' => 'Nip', 'value' => $authorizedNip];
            }

            $permissionTypes = $queryParams['permission_types'] ?? $queryParams['permissionTypes'] ?? [];
            if (is_string($permissionTypes) && $permissionTypes !== '') {
                $permissionTypes = preg_split('/\s*,\s*/', $permissionTypes) ?: [];
            }
            if (is_array($permissionTypes)) {
                $filters['permissionTypes'] = array_values(array_filter($permissionTypes, fn($v) => is_string($v) && $v !== ''));
            }
        }

        $diag = null;
        try {
            $diag = $this->diagnoseAuthContext($companyId, $env, $asNip ?: null);
        } catch (\Throwable) {
            $diag = null;
        }

        $usingMaster = false;
        $usingMasterMode = null;
        $identifierNip = null;
        if (is_array($diag)) {
            $identifierNip = (string)($diag['identifierNip'] ?? '') ?: null;
            if (($diag['authMethod'] ?? '') === 'certificate') {
                if (!empty($diag['masterCertCompanyId']) && ($diag['certCompanyId'] ?? '') !== $companyId) {
                    $usingMaster = true;
                    $usingMasterMode = 'configured';
                } elseif (($diag['certSource'] ?? null) === 'master') {
                    $usingMaster = true;
                    $usingMasterMode = 'fallback';
                }
            }
        }

        $resp = null;
        $errorDetails = null;
        try {
            if ($kind === 'personal') {
                $resp = $this->queryPersonalGrants($companyId, $env, $filters, $pageOffset, $pageSize, $asNip ?: null, $showTrace);
            } else {
                $resp = $this->queryAuthorizationGrants($companyId, $env, $filters, $pageOffset, $pageSize, $asNip ?: null, $showTrace);
            }
        } catch (\Throwable $e) {
            $errorDetails = $e->getMessage();
            if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
                try {
                    $res = $e->getResponse();
                    $status = $res?->getStatusCode();
                    $body = (string)$res?->getBody();
                    $errorDetails = $body !== ''
                        ? ('HTTP ' . $status . ' - ' . $e->getMessage() . ' | body: ' . substr($body, 0, 500))
                        : ('HTTP ' . $status . ' - ' . $e->getMessage());
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        $items = [];
        $hasMore = false;
        if (is_array($resp)) {
            if (isset($resp['permissions']) && is_array($resp['permissions'])) {
                $items = $resp['permissions'];
            } elseif (isset($resp['items']) && is_array($resp['items'])) {
                $items = $resp['items'];
            }
            $hasMore = (bool)($resp['hasMore'] ?? false);
        }

        $status = [
            'active' => $errorDetails === null,
            'env' => $env,
            'ts' => time(),
            'lastError' => $errorDetails,
            'usingMaster' => $usingMaster,
            'usingMasterMode' => $usingMasterMode,
            'identifierNip' => $identifierNip,
            'authMethod' => is_array($diag) ? ($diag['authMethod'] ?? null) : null,
            'certSource' => is_array($diag) ? ($diag['certSource'] ?? null) : null,
        ];

        $flash = null;
        if ($errorDetails !== null) {
            $flash = ['type' => 'error', 'message' => 'Błąd połączenia z KSeF: ' . $errorDetails];
        } elseif (!empty($items)) {
            $flash = ['type' => 'success', 'message' => sprintf('Pobrano %d pozycji uprawnień (strona %d).', count($items), $page)];
        } else {
            $flash = ['type' => 'info', 'message' => 'Brak wyników dla wybranych filtrów.'];
        }

        $trace = $showTrace ? KsefHttpTrace::all() : [];

        return [
            'items' => $items,
            'apiInfo' => [
                'total' => 0,
                'hasMore' => $hasMore,
                'isTruncated' => false,
            ],
            'ksefEnv' => $env,
            'ksefTraceEnabled' => $showTrace,
            'ksefTrace' => $trace,
            'ksefDiag' => $showTrace ? $diag : null,
            'status' => $status,
            'flash' => $flash,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
            'asNip' => $asNip,
            'kind' => $kind,
            'hasMore' => $hasMore,
        ];
    }

    /**
     * Normalizuje strukturę odpowiedzi KSeF na listę elementów.
     */
    private static function extractKsefItemsStatic(array $result): array
    {
        if (isset($result['result']) && is_array($result['result'])) {
            if (isset($result['result']['elements']) && is_array($result['result']['elements'])) {
                return $result['result']['elements'];
            }
            if (isset($result['result']['invoices']) && is_array($result['result']['invoices'])) {
                return $result['result']['invoices'];
            }
        }
        if (isset($result['elements']) && is_array($result['elements'])) {
            return $result['elements'];
        }
        if (isset($result['invoices']) && is_array($result['invoices'])) {
            return $result['invoices'];
        }
        if (isset($result['items']) && is_array($result['items'])) {
            return $result['items'];
        }
        if ($result !== [] && array_keys($result) === range(0, count($result) - 1)) {
            return $result;
        }
        return [];
    }

    /**
     * Prosty debug XML FA(3) przed wysyłką: sprawdza podstawowe warunki semantyczne i zwraca raport.
     * Nie wykonuje żadnych wywołań do KSeF.
     *
     * Zwraca m.in. wykryte problemy (issues[]) i podstawowe wyliczenia sum.
     */
    public function debugInvoiceXml(string $companyId, string $environment, string $xml): array
    {
        $creds = $this->storage->getSystemCreds($this->ctx($companyId, $environment));
        $expectedSource = null;
        $expectedNip = $this->resolveCompanyNip($companyId, $creds, $expectedSource);

        $report = [
            'ok' => true,
            'issues' => [],
            'sellerNip' => null,
            'buyerNip' => null,
            'expectedNip' => $expectedNip,
            'expectedNipSource' => $expectedSource,
            'hasAdnotacje' => false,
            'hasJst' => false,
            'hasGv' => false,
            'p2' => null,
            'p1' => null,
            'p6' => null,
            'lines' => 0,
            'providedTotals' => [
                '23' => ['net' => null, 'vat' => null],
                '8' => ['net' => null, 'vat' => null],
                '5' => ['net' => null, 'vat' => null],
                'sumGross' => null
            ],
            'computedTotals' => [
                '23' => ['net' => 0.0, 'vat' => 0.0],
                '8' => ['net' => 0.0, 'vat' => 0.0],
                '5' => ['net' => 0.0, 'vat' => 0.0],
                'sumGross' => 0.0
            ],
        ];

        try {
            $doc = new \DOMDocument();
            if (@$doc->loadXML($xml) === false || $doc->documentElement === null) {
                $report['ok'] = false;
                $report['issues'][] = 'xml_parse_error';
                return $report;
            }

            $ns = $doc->documentElement->namespaceURI ?: 'http://crd.gov.pl/wzor/2025/06/25/13775/';
            $xp = new \DOMXPath($doc);
            $xp->registerNamespace('k', $ns);

            // Seller NIP
            $node = $xp->query('//k:Faktura/k:Podmiot1/k:DaneIdentyfikacyjne/k:NIP')->item(0);
            $sellerNip = $node?->textContent ? preg_replace('/\D+/', '', $node->textContent) : null;
            $report['sellerNip'] = $sellerNip ?: null;

            // Buyer NIP (if exists)
            $node = $xp->query('//k:Faktura/k:Podmiot2/k:DaneIdentyfikacyjne/k:NIP')->item(0);
            $buyerNip = $node?->textContent ? preg_replace('/\D+/', '', $node->textContent) : null;
            $report['buyerNip'] = $buyerNip ?: null;

            // P_1, P_2, P_6
            $report['p1'] = $xp->query('//k:Faktura/k:Fa/k:P_1')->item(0)?->textContent;
            $report['p2'] = $xp->query('//k:Faktura/k:Fa/k:P_2')->item(0)?->textContent;
            $report['p6'] = $xp->query('//k:Faktura/k:Fa/k:P_6')->item(0)?->textContent;

            // Flags: Adnotacje / JST / GV
            $report['hasAdnotacje'] = ($xp->query('//k:Faktura/k:Fa/k:Adnotacje')->length > 0);
            $report['hasJst'] = ($xp->query('//k:Faktura/k:Podmiot2/k:JST')->length > 0);
            $report['hasGv'] = ($xp->query('//k:Faktura/k:Podmiot2/k:GV')->length > 0);

            // Provided totals (if present)
            $getDecimal = function($expr) use ($xp): ?float {
                $n = $xp->query($expr)->item(0)?->textContent;
                return ($n === null || $n === '') ? null : (float)str_replace(',', '.', $n);
            };
            $report['providedTotals']['23']['net'] = $getDecimal('//k:Faktura/k:Fa/k:P_13_1');
            $report['providedTotals']['23']['vat'] = $getDecimal('//k:Faktura/k:Fa/k:P_14_1');
            $report['providedTotals']['8']['net'] = $getDecimal('//k:Faktura/k:Fa/k:P_13_2');
            $report['providedTotals']['8']['vat'] = $getDecimal('//k:Faktura/k:Fa/k:P_14_2');
            $report['providedTotals']['5']['net'] = $getDecimal('//k:Faktura/k:Fa/k:P_13_3');
            $report['providedTotals']['5']['vat'] = $getDecimal('//k:Faktura/k:Fa/k:P_14_3');
            $report['providedTotals']['sumGross'] = $getDecimal('//k:Faktura/k:Fa/k:P_15');

            // Compute totals from lines (per-line VAT rounding 2 dp, then sum)
            $lines = $xp->query('//k:Faktura/k:Fa/k:FaWiersz');
            $report['lines'] = $lines?->length ?? 0;
            $sumGross = 0.0;
            if ($lines) {
                foreach ($lines as $line) {
                    $p11 = $xp->query('k:P_11', $line)->item(0)?->textContent;
                    $p12 = $xp->query('k:P_12', $line)->item(0)?->textContent; // rate
                    $net = $p11 !== null ? (float)str_replace(',', '.', $p11) : 0.0;
                    $rate = $p12 !== null ? trim($p12) : '';
                    $vat = 0.0;
                    if (in_array($rate, ['23','8','5'], true)) {
                        $vat = round($net * ((float)$rate) / 100, 2);
                        $report['computedTotals'][$rate]['net'] += $net;
                        $report['computedTotals'][$rate]['vat'] += $vat;
                    }
                    $sumGross += $net + $vat;
                }
            }
            $report['computedTotals']['sumGross'] = round($sumGross, 2);

            // Checks
            if (!empty($expectedNip) && !empty($sellerNip) && $expectedNip !== $sellerNip) {
                $report['ok'] = false;
                $report['issues'][] = 'nip_mismatch';
            }
            if ($report['hasAdnotacje']) {
                $report['issues'][] = 'has_adnotacje';
            }
            if ($report['hasJst']) {
                $report['issues'][] = 'has_jst';
            }
            if ($report['hasGv']) {
                $report['issues'][] = 'has_gv';
            }
            if (empty($report['p2'])) {
                $report['issues'][] = 'missing_P_2';
            }
            // Totals mismatch tolerance 0.01
            $tol = 0.01;
            foreach (['23','8','5'] as $r) {
                $pn = $report['providedTotals'][$r]['net'];
                $pv = $report['providedTotals'][$r]['vat'];
                $cn = round($report['computedTotals'][$r]['net'], 2);
                $cv = round($report['computedTotals'][$r]['vat'], 2);
                if ($pn !== null && abs($pn - $cn) > $tol) {
                    $report['issues'][] = "sum_net_{$r}_mismatch";
                }
                if ($pv !== null && abs($pv - $cv) > $tol) {
                    $report['issues'][] = "sum_vat_{$r}_mismatch";
                }
            }
            $pg = $report['providedTotals']['sumGross'];
            if ($pg !== null && abs($pg - $report['computedTotals']['sumGross']) > $tol) {
                $report['issues'][] = 'sum_gross_mismatch';
            }

        } catch (\Throwable $e) {
            $report['ok'] = false;
            $report['issues'][] = 'unexpected_exception:' . $e->getMessage();
        }

        // Log for quick review
        if (class_exists('Cake\\Log\\Log')) {
            try {
                \Cake\Log\Log::debug('[KSeF XML debug] ' . json_encode($report, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $report;
    }

    /**
     * Minimalny, bezpieczny sanitizer FA(3): domyślnie NIE usuwa opcjonalnych pól (Adnotacje, JST, GV),
     * aby nie naruszać wymagań semantycznych po stronie KSeF. Wykonuje tylko łagodne korekty:
     * - ewentualne zgranie NIP sprzedawcy z identyfikatorem autoryzacji, dodanie PrefiksPodatnika=PL,
     * - normalizacja liczb (kropka, 2 miejsca).
     *
     * Usuwanie opcjonalnych bloków można wymusić ustawieniem Ksef.preserveOptionalNodes=false (lub env KSEF_PRESERVE_OPTIONAL_NODES=0),
     * ale domyślnie jest włączone zachowanie (preserve) tych węzłów.
     */
    private function sanitizeInvoiceXml(string $xml, ?string $expectedNip = null): string
    {
        try {
            $doc = new \DOMDocument();
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = false;
            if (@$doc->loadXML($xml) === false || $doc->documentElement === null) {
                return $xml; // nie parsuje – zostaw oryginał
            }

            $ns = $doc->documentElement->namespaceURI ?: 'http://crd.gov.pl/wzor/2025/06/25/13775/';
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('k', $ns);

            $changed = false;
            // Domyślnie zachowujemy węzły Adnotacje/JST/GV (preserve=true). Można wyłączyć przez config/env.
            $preserveOptional = (bool)($this->readConfig('Ksef.preserveOptionalNodes') ?? getenv('KSEF_PRESERVE_OPTIONAL_NODES') ?? true);
            $paths = [
                '//k:Faktura/k:Fa/k:Adnotacje',
                '//k:Faktura/k:Podmiot2/k:JST',
                '//k:Faktura/k:Podmiot2/k:GV',
            ];
            if (!$preserveOptional) {
                foreach ($paths as $path) {
                    foreach ($xpath->query($path) ?: [] as $node) {
                        if ($node instanceof \DOMNode && $node->parentNode) {
                            $node->parentNode->removeChild($node);
                            $changed = true;
                        }
                    }
                }
            }

            // Ujednolić NIP sprzedawcy z oczekiwanym (autoryzacja) – tylko jeśli podany
            if (!empty($expectedNip)) {
                $sellerNipNode = $xpath->query('//k:Faktura/k:Podmiot1/k:DaneIdentyfikacyjne/k:NIP')->item(0);
                if ($sellerNipNode instanceof \DOMElement) {
                    $cur = preg_replace('/\D+/', '', (string)$sellerNipNode->textContent);
                    if ($cur !== $expectedNip) {
                        $sellerNipNode->nodeValue = $expectedNip;
                        $changed = true;
                        $this->logDebug('Sanitized FA(3) XML: adjusted seller NIP to expected.');
                    }
                }
                // Upewnij się, że istnieje PrefiksPodatnika=PL
                $prefNode = $xpath->query('//k:Faktura/k:Podmiot1/k:PrefiksPodatnika')->item(0);
                if ($prefNode instanceof \DOMElement) {
                    if (trim($prefNode->textContent) !== 'PL') {
                        $prefNode->nodeValue = 'PL';
                        $changed = true;
                    }
                } else {
                    $pod1 = $xpath->query('//k:Faktura/k:Podmiot1')->item(0);
                    if ($pod1 instanceof \DOMElement) {
                        $newPref = $doc->createElementNS($ns, 'PrefiksPodatnika', 'PL');
                        // wstaw PrefiksPodatnika przed DaneIdentyfikacyjne
                        $di = $xpath->query('k:DaneIdentyfikacyjne', $pod1)->item(0);
                        if ($di instanceof \DOMElement && $di->parentNode) {
                            $di->parentNode->insertBefore($newPref, $di);
                        } else {
                            $pod1->insertBefore($newPref, $pod1->firstChild);
                        }
                        $changed = true;
                    }
                }
            }

            // Normalizacja pól liczbowych z częścią dziesiętną (kropka, 2 miejsca):
            // P_8B, P_9A, P_11, P_13_1..P_13_3, P_14_1..P_14_3, P_15
            $decimalPaths = [
                '//k:Faktura/k:Fa/k:FaWiersz/k:P_8B',
                '//k:Faktura/k:Fa/k:FaWiersz/k:P_9A',
                '//k:Faktura/k:Fa/k:FaWiersz/k:P_11',
                '//k:Faktura/k:Fa/k:P_13_1',
                '//k:Faktura/k:Fa/k:P_13_2',
                '//k:Faktura/k:Fa/k:P_13_3',
                '//k:Faktura/k:Fa/k:P_14_1',
                '//k:Faktura/k:Fa/k:P_14_2',
                '//k:Faktura/k:Fa/k:P_14_3',
                '//k:Faktura/k:Fa/k:P_15',
            ];
            foreach ($decimalPaths as $p) {
                $nodes = $xpath->query($p);
                if (!$nodes) { continue; }
                foreach ($nodes as $n) {
                    if (!($n instanceof \DOMElement)) { continue; }
                    $raw = trim((string)$n->textContent);
                    if ($raw === '') { continue; }
                    $val = (float)str_replace(',', '.', $raw);
                    $fmt = number_format($val, 2, '.', '');
                    if ($fmt !== $raw) {
                        $n->nodeValue = $fmt;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $this->logDebug('Sanitized FA(3) XML: adjusted optional nodes/NIP/decimals.');
                return $doc->saveXML() ?: $xml;
            }
            return $xml;
        } catch (\Throwable $e) {
            // w razie problemów z sanitacją wyślij oryginał
            return $xml;
        }
    }

    /**
     * Pobiera NIP sprzedawcy (Podmiot1/DaneIdentyfikacyjne/NIP) z FA(3) XML. Zwraca 10 cyfr lub null.
     */
    private function extractSellerNipFromXml(string $xml): ?string
    {
        try {
            $doc = new \DOMDocument();
            if (@$doc->loadXML($xml) === false || $doc->documentElement === null) {
                return null;
            }
            $ns = $doc->documentElement->namespaceURI ?: 'http://crd.gov.pl/wzor/2025/06/25/13775/';
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('k', $ns);
            $nodeList = $xpath->query('//k:Faktura/k:Podmiot1/k:DaneIdentyfikacyjne/k:NIP');
            if (!$nodeList || $nodeList->length === 0) {
                return null;
            }
            $nip = trim((string)$nodeList->item(0)?->textContent);
            $nip = preg_replace('/\D+/', '', $nip ?? '');
            if ($nip === '') {
                return null;
            }
            return $nip;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Ekstrakcja numeru P_2 (numer faktury) z FA(3) XML.
     */
    private function extractInvoiceNumberFromXml(string $xml): ?string
    {
        try {
            $doc = new \DOMDocument();
            if (@$doc->loadXML($xml) === false || $doc->documentElement === null) {
                return null;
            }
            $ns = $doc->documentElement->namespaceURI ?: 'http://crd.gov.pl/wzor/2025/06/25/13775/';
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('k', $ns);
            $node = $xpath->query('//k:Faktura/k:Fa/k:P_2')->item(0);
            $val = trim((string)($node?->textContent ?? ''));
            return $val !== '' ? $val : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Zapisuje migawkę XML do katalogu logów (logs/ksef). Nazwa pliku zawiera środowisko, company, znacznik czasu i etykietę.
     */
    private function dumpXmlSnapshot(string $xml, string $label, string $companyId, string $environment, ?string $invoiceNumber = null): void
    {
        $root = dirname(__DIR__, 3);
        $logsBase = defined('LOGS') ? rtrim((string)LOGS, DIRECTORY_SEPARATOR) : $root . DIRECTORY_SEPARATOR . 'logs';
        $dir = $logsBase . DIRECTORY_SEPARATOR . 'ksef';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ts = date('Ymd_His');
        $safeCompany = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)$companyId);
        $safeInv = $invoiceNumber ? preg_replace('/[^a-zA-Z0-9_\-\/]+/', '-', $invoiceNumber) : 'noP2';
        $file = sprintf('%s%s%s_%s_%s_%s.xml', $dir, DIRECTORY_SEPARATOR, $environment, $safeCompany, $ts, $label);
        if ($invoiceNumber) {
            $file = sprintf('%s%s%s_%s_%s_%s_%s.xml', $dir, DIRECTORY_SEPARATOR, $environment, $safeCompany, $ts, $label, str_replace('/', '-', $safeInv));
        }
        @file_put_contents($file, $xml);
    }
}

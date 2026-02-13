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
    public function buildClient(string $companyId, string $environment = 'test', ?string $overrideIdentifierNip = null): \N1ebieski\KSEFClient\Resources\ClientResource
    {
        $contextKey = $this->ctx($companyId, $environment);

        // Zapewnij poprawny CA bundle dla cURL/openssl (Windows często go nie ma domyślnie)
        $verifySetting = $this->ensureCaBundleConfigured($environment);

        // 1) jeśli mamy już access/refresh w storage, użyjemy ich z exp
        $tokens = $this->storage->getTokens($contextKey);
        // 2) sysToken + NIP (źródło: najnowszy wpis w ksef_authorizations → payload v2)
        $creds  = $this->storage->getSystemCreds($contextKey);

        $builder = (new ClientBuilder())
            ->withMode($this->mapMode($environment));
        // Włącz walidację XML wg XSD, jeśli dostępna w aktualnej wersji klienta
        if (method_exists($builder, 'withValidateXml')) {
            $builder = $builder->withValidateXml(true);
        }

        // Preferuj autoryzację certyfikatem, jeśli załadowano i dostępny (zalecane przez KSeF dla pewnych scenariuszy)
        // Cert preferowany: biblioteka wymaga .p12. Jeśli mamy PEM/combined, spróbujmy zrobić p12.
        $certUsed = false;
        // Obsługa "master cert" – pozwala użyć certyfikatu z jednej, wskazanej firmy dla wszystkich firm
        $masterCertCompanyId = (string)($this->readConfig('Ksef.masterCertCompanyId') ?? getenv('KSEF_MASTER_CERT_COMPANY_ID') ?? '');
        $certCompanyId = $masterCertCompanyId !== '' ? $masterCertCompanyId : $companyId;
        if ($masterCertCompanyId !== '') {
            $this->logDebug('Using master certificate from companyId=' . $masterCertCompanyId . ' (env=' . $environment . ').');
        }
        $cert = $this->certs?->getCertificateFor($certCompanyId, $environment);
        if ($cert && is_file($cert['path'])) {
            if (str_ends_with($cert['path'], '.p12')) {
                $builder = $builder->withCertificatePath($cert['path'], $cert['passphrase'] ?? null);
                $certUsed = true;
            } else {
                // podejmij próbę wytworzenia p12 na żądanie
                $p12 = $this->certs?->ensurePkcs12($companyId, $environment);
                if ($p12 && is_file($p12['path'])) {
                    $builder = $builder->withCertificatePath($p12['path'], $p12['passphrase'] ?? null);
                    $certUsed = true;
                }
            }
        }

        if (!$certUsed) {
            // Tokeny używamy tylko gdy nie ma dostępnego certyfikatu.
            if (!empty($tokens['accessToken']) && !empty($tokens['accessExp'])) {
                $builder = $builder->withAccessToken((string)$tokens['accessToken'], (int)$tokens['accessExp']);
            }
            if (!empty($tokens['refreshToken']) && !empty($tokens['accessExp'])) {
                // biblioteka oczekuje „valid until” również dla refresh — używamy accessExp jako przybliżenie,
                // jeśli nie mamy osobnego pola; w razie potrzeby rozbudować storage o refreshExp.
                $builder = $builder->withRefreshToken((string)$tokens['refreshToken'], (int)$tokens['accessExp']);
            }
            // Jeśli mamy sysToken+NIP, ustaw – biblioteka przeprowadzi auto-autoryzację tokenową.
            if (!empty($creds['sysToken']) && !empty($creds['nip'])) {
                $builder = $builder->withKsefToken((string)$creds['sysToken']);
                $nip = $overrideIdentifierNip ? preg_replace('/\D+/', '', (string)$overrideIdentifierNip) : (string)$creds['nip'];
                if (!empty($nip)) {
                    $builder = $builder->withIdentifier($nip);
                    $this->logDebug('Using identifier (NIP) for token auth: ' . $nip . ($overrideIdentifierNip ? ' (override)' : ''));
                }
            }
        } else {
            // W trybie certyfikatowym nie dołączaj tokenów/systemowych, aby uniknąć błędu "invalid token".
            $this->logDebug('Certificate present: skipping token-based authentication for this request.');
            // Dodatkowo, jeśli znamy NIP, przekaż go zawsze (część bibliotek/endpointów tego wymaga).
            $source = null;
            $nip = $overrideIdentifierNip ? preg_replace('/\D+/', '', (string)$overrideIdentifierNip) : $this->resolveCompanyNip($companyId, $creds, $source);
            if (!empty($nip)) {
                $builder = $builder->withIdentifier($nip);
                $this->logDebug('Using identifier (NIP) for certificate auth: ' . $nip . ($overrideIdentifierNip ? ' (override)' : ($source ? ' (source: ' . $source . ')' : '')));
            }
        }

        // Zawsze wstrzyknij klienta HTTP z ustawionym verify – to najbardziej niezawodny sposób wymuszenia CA na różnych wersjach biblioteki
        if (class_exists('GuzzleHttp\\Client')) {
            $httpClient = new \GuzzleHttp\Client([
                'verify' => $verifySetting,
            ]);
            if (method_exists($builder, 'withHttpClient')) {
                $builder = $builder->withHttpClient($httpClient);
            }
        }

        return $builder->build();
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
    $authMethod = $cert ? 'certificate' : 'token';
    $certSource = $cert['source'] ?? ($masterCertCompanyId !== '' ? 'master' : 'company');

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
            'authMethod' => $authMethod,
            'certPresent' => (bool)$cert,
            'certSource' => (bool)$cert ? $certSource : null,
            'certCompanyId' => $certCompanyId,
            'masterCertCompanyId' => $masterCertCompanyId !== '' ? $masterCertCompanyId : null,
            'certFile' => $cert && isset($cert['path']) ? basename((string)$cert['path']) : null,
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
        ?string $overrideIdentifierNip = null
    ): array {
        $client = $this->buildClient($companyId, $environment, $overrideIdentifierNip);

        // Zbuduj VO zgodnie z wersją biblioteki: invoices()->query()->metadata(new MetadataRequest(...))
        $subjectStr = (string)($filters['subjectType'] ?? 'Subject1');
        // Zabezpieczenie na różne wielkości liter
        $subject = SubjectTypeVO::from(match ($subjectStr) {
            'Subject1' => SubjectTypeVO::Subject1->value,
            'Subject2' => SubjectTypeVO::Subject2->value,
            'Subject3' => SubjectTypeVO::Subject3->value,
            default    => SubjectTypeVO::Subject1->value,
        });

        $dateTypeStr = (string)($filters['dateRange']['dateType'] ?? 'Issue');
        $dateType = DateTypeVO::from(match ($dateTypeStr) {
            'Issue' => DateTypeVO::Issue->value,
            'Invoicing' => DateTypeVO::Invoicing->value,
            'PermanentStorage' => DateTypeVO::PermanentStorage->value,
            default => DateTypeVO::Issue->value,
        });
        $from = (string)($filters['dateRange']['from'] ?? date('Y-m-01T00:00:00'));
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
            pageSize: \N1ebieski\KSEFClient\ValueObjects\Requests\Invoices\PageSize::from($pageSize),
            pageOffset: PageOffsetVO::from($pageOffset)
        );

        // Wywołanie zgodne z README/tests: invoices()->query()->metadata(MetadataRequest)->object()/array()
        $resp = $client->invoices()->query()->metadata($request);

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
     * Wysyła pojedynczy dokument XML (FA) do KSeF trybem interaktywnym (online session).
     * Zwraca tablicę z informacją o statusie i nadanym numerze KSeF (jeśli sukces).
     * Uwaga: wymagany jest certyfikat (.p12 lub skonwertowany) lub token – preferowany certyfikat.
     */
    public function sendInvoiceXml(string $companyId, string $environment, string $xml): array
    {
        $contextKey = $this->ctx($companyId, $environment);
        $messages = [];
        $nowTs = fn() => date('c');
        $verifySetting = $this->ensureCaBundleConfigured($environment);
        $messages[] = ['stage' => 'setup', 'level' => 'info', 'ts' => $nowTs(), 'message' => 'Konfiguracja środowiska TLS'];

        // Zbuduj klienta JEDNORAZOWO z kluczem szyfrowania (wymagany dla zasobów invoices/sessions)
        $tokens = $this->storage->getTokens($contextKey);
        $creds  = $this->storage->getSystemCreds($contextKey);

        $builder = (new ClientBuilder())
            ->withMode($this->mapMode($environment))
            ->withEncryptionKey(EncryptionKeyFactory::makeRandom());
        // Włącz walidację XML wg XSD również dla ścieżki wysyłki, jeśli dostępna
        if (method_exists($builder, 'withValidateXml')) {
            $builder = $builder->withValidateXml(true);
        }
        $messages[] = ['stage' => 'setup', 'level' => 'info', 'ts' => $nowTs(), 'message' => 'Zbudowano klienta KSeF'];

        $certUsed = false;
        $cert = $this->certs?->getCertificateFor($companyId, $environment);
        if ($cert && is_file($cert['path'])) {
            if (str_ends_with($cert['path'], '.p12')) {
                $builder = $builder->withCertificatePath($cert['path'], $cert['passphrase'] ?? null);
                $certUsed = true;
            } else {
                $p12 = $this->certs?->ensurePkcs12($companyId, $environment);
                if ($p12 && is_file($p12['path'])) {
                    $builder = $builder->withCertificatePath($p12['path'], $p12['passphrase'] ?? null);
                    $certUsed = true;
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
                $builder = $builder
                    ->withKsefToken((string)$creds['sysToken'])
                    ->withIdentifier((string)$creds['nip']);
            }
        } else {
            $this->logDebug('Certificate present for send: skipping token-based auth.');
            $src = null; $nip = $this->resolveCompanyNip($companyId, $creds, $src);
            if (!empty($nip)) { $builder = $builder->withIdentifier($nip); }
        }

        if (class_exists('GuzzleHttp\\Client')) {
            $httpClient = new \GuzzleHttp\Client(['verify' => $verifySetting]);
            if (method_exists($builder, 'withHttpClient')) {
                $builder = $builder->withHttpClient($httpClient);
            }
        }

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

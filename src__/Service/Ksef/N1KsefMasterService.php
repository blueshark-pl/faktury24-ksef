<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use Cake\Core\Configure;
use GuzzleHttp\Client as GuzzleClient;
use N1ebieski\KSEFClient\ClientBuilder;
use N1ebieski\KSEFClient\Factories\EncryptionKeyFactory;
use N1ebieski\KSEFClient\Resources\ClientResource;
use N1ebieski\KSEFClient\Support\Utility;
use N1ebieski\KSEFClient\ValueObjects\Mode;

final class N1KsefMasterService
{
    public function __construct(
        private readonly MasterCertProvider $certProvider,
        private readonly FileMetaStorage $meta,
    ) {}

    /**
     * Buduje klienta: zawsze MASTER cert + identifier = podany NIP.
     * $needsEncryptionKey ustaw na true dla sesji/faktur.
     */
    public function buildClientByNip(string $nip, string $environment = 'test', bool $needsEncryptionKey = false): ClientResource
    {
        $nip = preg_replace('/\D+/', '', $nip ?? '');
        if ($nip === '') {
            throw new \InvalidArgumentException('Brak NIP.');
        }

        $verifySetting = $this->ensureCaBundleConfigured($environment);

        $builder = (new ClientBuilder())
            ->withMode($environment === 'prod' ? Mode::Production : Mode::Test);

        // optional api url override
        $apiUrl = (string)(Configure::read('Ksef.apiUrl') ?? getenv('KSEF_API_URL') ?? '');
        if ($apiUrl !== '') {
            $builder = $builder->withApiUrl($apiUrl);
        }

        // HTTP client
        $builder = $builder->withHttpClient(new GuzzleClient([
            'verify' => $verifySetting,
        ]));

        // builder options
        if (method_exists($builder, 'withValidateXml')) {
            $builder = $builder->withValidateXml((bool)(Configure::read('Ksef.validateXml') ?? true));
        }
        if (method_exists($builder, 'withVerifyCertificateChain')) {
            $builder = $builder->withVerifyCertificateChain((bool)(Configure::read('Ksef.verifyCertificateChain') ?? true));
        }
        if (method_exists($builder, 'withAsyncMaxConcurrency')) {
            $builder = $builder->withAsyncMaxConcurrency((int)(Configure::read('Ksef.asyncMaxConcurrency') ?? 8));
        }

        // MASTER cert
        $p12Path = $this->certProvider->getP12Path($environment);
        $passphrase = $this->certProvider->getPassphrase($environment);
        $builder = $builder->withCertificatePath($p12Path, $passphrase);

        // identifier = target NIP
        $builder = $builder->withIdentifier($nip);

        // encryption key (stabilny per env+nip)
        if ($needsEncryptionKey) {
            $key = $this->meta->getEncryptionKey($environment, $nip);
            if (!$key) {
                $key = EncryptionKeyFactory::makeRandom();
                $this->meta->saveEncryptionKey($environment, $nip, $key);
            }
            $builder = $builder->withEncryptionKey($key);
        }

        return $builder->build();
    }

    /**
     * Wysyłka XML FA(3) – open/send/close/status – request tablicą (auto mapping).
     */
    public function sendInvoiceXmlByNip(string $nip, string $environment, string $xml): array
    {
        $client = $this->buildClientByNip($nip, $environment, true);

        $open = $client->sessions()->online()->open([
            'formCode' => 'FA (3)',
        ])->object();

        $sessionRef = (string)($open->referenceNumber ?? '');
        if ($sessionRef === '') {
            throw new \RuntimeException('Nie udało się otworzyć sesji online (brak referenceNumber).');
        }

        $send = $client->sessions()->online()->send([
            'referenceNumber' => $sessionRef,
            'faktura' => $xml,
        ])->object();

        $invoiceRef = (string)($send->referenceNumber ?? '');

        // best-effort close
        try {
            $client->sessions()->online()->close([
                'referenceNumber' => $sessionRef,
            ])->status();
        } catch (\Throwable $e) {
            // ignore
        }

        $status = Utility::retry(function () use ($client, $sessionRef, $invoiceRef) {
            $st = $client->sessions()->invoices()->status([
                'referenceNumber' => $sessionRef,
                'invoiceReferenceNumber' => $invoiceRef,
            ])->object();

            $code = (int)($st->status->code ?? 0);
            if ($code === 200) return $st;
            if ($code >= 400) {
                throw new \RuntimeException((string)($st->status->description ?? 'KSeF error'), $code);
            }
            return null; // retry for pending
        });

        $code = (int)($status->status->code ?? 0);
        $desc = (string)($status->status->description ?? '');
        $ksefNumber = (string)($status->ksefNumber ?? '');

        return [
            'ok' => $code === 200 && $ksefNumber !== '',
            'statusCode' => $code,
            'statusDesc' => $desc,
            'ksefNumber' => $ksefNumber,
            'sessionReference' => $sessionRef,
            'invoiceReference' => $invoiceRef,
        ];
    }

    /**
     * Metadane faktur – auto mapping przez tablicę.
     */
    public function queryMetadataByNip(string $nip, string $environment, array $filters, int $pageOffset, int $pageSize): array
    {
        $client = $this->buildClientByNip($nip, $environment, false);

        $payload = [
            'subjectType' => (string)($filters['subjectType'] ?? 'Subject1'),
            'dateRange' => [
                'dateType' => (string)($filters['dateRange']['dateType'] ?? 'Issue'),
                'from' => (string)($filters['dateRange']['from'] ?? date('Y-m-01T00:00:00')),
            ],
            'pageSize' => $pageSize,
            'pageOffset' => $pageOffset,
        ];

        if (!empty($filters['dateRange']['to'])) {
            $payload['dateRange']['to'] = (string)$filters['dateRange']['to'];
        }
        if (!empty($filters['ksefNumber'])) $payload['ksefNumber'] = (string)$filters['ksefNumber'];
        if (!empty($filters['invoiceNumber'])) $payload['invoiceNumber'] = (string)$filters['invoiceNumber'];
        if (!empty($filters['sellerNip'])) $payload['sellerNip'] = preg_replace('/\D+/', '', (string)$filters['sellerNip']);
        if (!empty($filters['buyerNip'])) {
            $payload['buyerIdentifier'] = [
                'type' => 'nip',
                'identifier' => preg_replace('/\D+/', '', (string)$filters['buyerNip']),
            ];
        }

        $resp = $client->invoices()->query()->metadata($payload)->object();
        return json_decode(json_encode($resp, JSON_UNESCAPED_UNICODE), true) ?? [];
    }

    private function ensureCaBundleConfigured(string $environment): string|bool
    {
        $skipVerify = (bool)(Configure::read('Ksef.skipTlsVerify') ?? getenv('KSEF_SKIP_TLS_VERIFY') ?? false);
        if ($skipVerify && $environment !== 'prod') {
            @ini_set('openssl.verify_peer', '0');
            @ini_set('openssl.verify_peer_name', '0');
            return false;
        }

        $bundle = (string)(Configure::read('Ksef.caBundle') ?? getenv('KSEF_CA_BUNDLE') ?? '');
        if ($bundle !== '' && is_file($bundle)) {
            @ini_set('curl.cainfo', $bundle);
            @ini_set('openssl.cafile', $bundle);
            putenv('CURL_CA_BUNDLE=' . $bundle);
            putenv('SSL_CERT_FILE=' . $bundle);
            return $bundle;
        }

        return true;
    }
}

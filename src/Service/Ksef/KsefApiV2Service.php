<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use Cake\Core\Configure;
use GuzzleHttp\Client as GuzzleClient;
use Intermedia\Ksef\Apiv2;
use Intermedia\Ksef\Apiv2\Models\Operations;

use function rtrim;

final class KsefApiV2Service
{
    private Apiv2\Client $client;

    public function __construct(?string $bearerToken = null, ?string $environment = null)
    {
        $token = $bearerToken ?? (string)Configure::read('Ksef.bearerToken', '');

        $env = strtolower((string)($environment ?? Configure::read('Ksef.env', 'test')));
        if (!in_array($env, ['test', 'prod', 'demo'], true)) {
            $env = 'test';
        }
        // debug($env);
        //$baseUrl = (string)Configure::read('Ksef.baseUrl', 'as');
        // if ($baseUrl === '') {
        //     $baseUrl = match ($env) {
        //         // U Ciebie DNS nie rozwiązuje api.ksef.mf.gov.pl, więc defaultujemy do stabilnego "legacy" hosta.
        //         // Jeśli chcesz używać nowych hostów api*.ksef.mf.gov.pl, ustaw jawnie Ksef.baseUrl.
        //         'prod' => 'https:/ksef.mf.gov.pl',
        //         'demo' => 'https://api-demo.ksef.mf.gov.pl',
        //         default => 'https://ksef-test.mf.gov.pl/api',
        //     };
        // }
        debug($baseUrl);
        $serverUrl = $this->normalizeServerUrl("https://ksef.mf.gov.pl");
        debug($serverUrl);
        $skipTlsVerify = (bool)Configure::read('Ksef.skipTlsVerify', false);
        $caBundle = ROOT . DS . 'resources' . DS . 'public-ca.pem';
        $proxy = (string)(Configure::read('Ksef.proxy', '') ?? '');
        if ($proxy === '') {
            // W sieciach firmowych często wymagany jest proxy dla HTTPS.
            $proxy = (string)(getenv('HTTPS_PROXY') ?: getenv('HTTP_PROXY') ?: '');
        }

        $guzzleOptions = [
            'timeout' => 60,
        ];
        if ($proxy !== '') {
            $guzzleOptions['proxy'] = $proxy;
        }
        if ($skipTlsVerify) {
            $guzzleOptions['verify'] = false;
        } elseif ($caBundle !== '') {
            $guzzleOptions['verify'] = $caBundle;
        }

        $builder = Apiv2\Client::builder();

        // SDK domyślnie ma tylko DEMO w SERVERS; test/prod ustawiamy przez serverUrl.
        $builder = $builder
            ->setServerUrl($serverUrl)
            ->setClient(new GuzzleClient($guzzleOptions));

        if ($token !== '') {
            $builder = $builder->setSecurity($token);
        }

        $this->client = $builder->build();
    }

    public function getCurrentSessions(int $pageSize = 10): Operations\GetCurrentSessionsResponse
    {
        return $this->client->auth->getCurrentSessions(pageSize: $pageSize);
    }

    public function challenge(): Operations\ChallengeResponse
    {
        return $this->client->auth->challenge();
    }

    public function authenticateWithXades(string $signedXml, ?bool $verifyCertificateChain = null): Operations\AuthWithXadesResponse
    {
        return $this->client->auth->withXades($signedXml, $verifyCertificateChain);
    }

    public function sdk(): Apiv2\Client
    {
        return $this->client;
    }

    private function normalizeServerUrl(string $baseUrl): string
    {
        $url = rtrim(trim($baseUrl), '/');

        // SDK endpointy są pod /v2 (nowy host) lub /api/v2 (stary host).
        if (str_ends_with($url, '/v2') || str_ends_with($url, '/api/v2')) {
            return $url;
        }

        $url .= '/v2';

        return $url;
    }
}

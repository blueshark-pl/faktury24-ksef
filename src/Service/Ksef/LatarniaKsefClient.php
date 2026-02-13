<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use Cake\Http\Client;
use Cake\Http\Client\Response;
use RuntimeException;

/**
 * Klient publicznego API "Latarnia KSeF" (bez autoryzacji).
 */
final class LatarniaKsefClient
{
    private Client $http;
    private string $baseUrl;

    public function __construct(?string $baseUrl = null, ?Client $http = null, ?int $timeout = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string)env('LATARNIA_KSEF_BASE_URL', 'https://api-latarnia.ksef.mf.gov.pl'), '/');
        $timeout ??= (int)env('LATARNIA_KSEF_HTTP_TIMEOUT', 4);

        $this->http = $http ?? new Client([
            'timeout' => $timeout,
        ]);
    }

    /**
     * GET /status
     * Zwraca m.in. ['status' => 'AVAILABLE|MAINTENANCE|FAILURE|TOTAL_FAILURE', 'messages' => [...]]
     */
    public function fetchStatus(): array
    {
        $resp = $this->http->get("{$this->baseUrl}/status", [], ['type' => 'json']);
        $this->ensureOk($resp, 'status');

        return (array)$resp->getJson();
    }

    /**
     * GET /messages
     */
    public function fetchMessages(): array
    {
        $resp = $this->http->get("{$this->baseUrl}/messages", [], ['type' => 'json']);
        $this->ensureOk($resp, 'messages');

        return (array)$resp->getJson();
    }

    private function ensureOk(Response $resp, string $endpoint): void
    {
        if ($resp->isOk()) {
            return;
        }

        $code = $resp->getStatusCode();
        $body = (string)$resp->getStringBody();
        $body = mb_substr($body, 0, 800);

        throw new RuntimeException("Latarnia KSeF API error ({$endpoint}): HTTP {$code}. Body: {$body}");
    }
}

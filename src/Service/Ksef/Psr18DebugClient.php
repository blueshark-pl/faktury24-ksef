<?php
declare(strict_types=1);

namespace App\Service\Ksef;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Psr18DebugClient implements ClientInterface
{
    public function __construct(
        private readonly ClientInterface $inner,
        private readonly string $logFile,
        private readonly int $maxBodyBytes = 12000,
        private readonly bool $logAll = false,
        ?callable $collector = null
    ) {
        $this->collector = $collector !== null ? \Closure::fromCallable($collector) : null;
    }

    /** @var (\Closure(array): void)|null */
    private readonly ?\Closure $collector;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->inner->sendRequest($request);
        } catch (\Throwable $e) {
            try {
                $this->appendExceptionLog($request, $e);
            } catch (\Throwable) {
                // ignore
            }
            throw $e;
        }

        try {
            [$response, $contents] = $this->captureResponseBody($response);

            if ($this->shouldLog($response, $contents)) {
                $this->appendLog($request, $response, $contents);
            }
        } catch (\Throwable) {
            // Best-effort diagnostics only.
        }

        return $response;
    }

    /**
     * @return array{0: ResponseInterface, 1: string}
     */
    private function captureResponseBody(ResponseInterface $response): array
    {
        $stream = $response->getBody();
        $contents = '';

        if ($stream->isSeekable()) {
            $stream->rewind();
            $contents = $stream->getContents();
            $stream->rewind();
        } else {
            $contents = (string)$stream;
        }

        // Replace body with a fresh stream so downstream can read it again.
        $response = $response->withBody(Utils::streamFor($contents));

        return [$response, $contents];
    }

    private function shouldLog(ResponseInterface $response, string $contents): bool
    {
        if ($this->logAll) {
            return true;
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            return true;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        $trimmed = ltrim($contents);
        $firstChar = $trimmed !== '' ? $trimmed[0] : '';

        // Log when it's very likely not JSON (common root cause for JsonException: Syntax error).
        if ($contentType !== '' && !str_contains($contentType, 'application/json')) {
            return true;
        }

        if ($firstChar !== '' && $firstChar !== '{' && $firstChar !== '[') {
            return true;
        }

        return false;
    }

    private function appendLog(RequestInterface $request, ResponseInterface $response, string $contents): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $status = $response->getStatusCode();
        $contentType = $response->getHeaderLine('Content-Type');
        $uri = (string)$request->getUri();
        $method = $request->getMethod();

        $reqHeaders = $this->sanitizeHeaders($request->getHeaders());
        $reqBodySnippet = $this->captureRequestBodySnippet($request);

        $contents = $this->redactBodyIfJson($contents);
        $snippet = $this->truncate($contents, $this->maxBodyBytes);

        $entry = sprintf(
            "[%s] %s %s\nRequest-Headers: %s\nRequest-Body (truncated):\n%s\nStatus: %d\nContent-Type: %s\nBody (truncated):\n%s\n\n",
            date('Y-m-d H:i:s'),
            $method,
            $uri,
            json_encode($reqHeaders, JSON_UNESCAPED_SLASHES),
            $reqBodySnippet,
            $status,
            $contentType !== '' ? $contentType : '-',
            $snippet
        );

        $this->collect([
            'ts' => date('Y-m-d H:i:s'),
            'method' => $method,
            'uri' => $uri,
            'requestHeaders' => $reqHeaders,
            'requestBody' => $reqBodySnippet,
            'status' => $status,
            'contentType' => $contentType !== '' ? $contentType : null,
            'responseBody' => $snippet,
        ]);

        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    private function appendExceptionLog(RequestInterface $request, \Throwable $e): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $uri = (string)$request->getUri();
        $method = $request->getMethod();
        $reqHeaders = $this->sanitizeHeaders($request->getHeaders());
        $reqBodySnippet = $this->captureRequestBodySnippet($request);

        $entry = sprintf(
            "[%s] %s %s\nRequest-Headers: %s\nRequest-Body (truncated):\n%s\nEXCEPTION: %s: %s\n\n",
            date('Y-m-d H:i:s'),
            $method,
            $uri,
            json_encode($reqHeaders, JSON_UNESCAPED_SLASHES),
            $reqBodySnippet,
            $e::class,
            $e->getMessage()
        );

        $this->collect([
            'ts' => date('Y-m-d H:i:s'),
            'method' => $method,
            'uri' => $uri,
            'requestHeaders' => $reqHeaders,
            'requestBody' => $reqBodySnippet,
            'exceptionClass' => $e::class,
            'exceptionMessage' => $e->getMessage(),
        ]);

        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string, mixed> $entry */
    private function collect(array $entry): void
    {
        if ($this->collector === null) {
            return;
        }

        try {
            ($this->collector)($entry);
        } catch (\Throwable) {
            // best-effort only
        }
    }

    private function truncate(string $text, int $maxBytes): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if ($maxBytes <= 0) {
            return '';
        }

        if (strlen($text) <= $maxBytes) {
            return $text;
        }

        $cut = substr($text, 0, $maxBytes);
        $remaining = strlen($text) - $maxBytes;

        return $cut . "\n... [truncated {$remaining} bytes]";
    }

    /** @param array<string, array<int, string>> $headers */
    private function sanitizeHeaders(array $headers): array
    {
        $redact = [
            'authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'x-auth-token',
        ];

        $out = [];
        foreach ($headers as $name => $values) {
            $lower = strtolower($name);
            if (in_array($lower, $redact, true)) {
                $out[$name] = ['[redacted]'];
            } else {
                $out[$name] = $values;
            }
        }
        return $out;
    }

    private function captureRequestBodySnippet(RequestInterface $request): string
    {
        try {
            $stream = $request->getBody();
            if (!$stream->isSeekable()) {
                return '[unavailable: non-seekable stream]';
            }

            $stream->rewind();
            $body = $stream->getContents();
            $stream->rewind();

            if ($body === '') {
                return '';
            }

            $body = $this->redactBodyIfJson($body);
            return $this->truncate($body, $this->maxBodyBytes);
        } catch (\Throwable) {
            return '[unavailable]';
        }
    }

    private function redactBodyIfJson(string $body): string
    {
        $trimmed = ltrim($body);
        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return $body;
        }

        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $body;
        }

        $decoded = $this->redactRecursive($decoded);

        try {
            return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $body;
        }
    }

    private function redactRecursive(mixed $value): mixed
    {
        $sensitiveKeys = [
            'token',
            'ksefToken',
            'sysToken',
            'accessToken',
            'refreshToken',
            'encryptedKey',
            'signature',
            'xades',
            'passphrase',
            'password',
        ];

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (is_string($k) && in_array($k, $sensitiveKeys, true)) {
                    $out[$k] = '[redacted]';
                } else {
                    $out[$k] = $this->redactRecursive($v);
                }
            }
            return $out;
        }

        return $value;
    }
}

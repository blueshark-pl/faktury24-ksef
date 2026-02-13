<?php
declare(strict_types=1);

namespace App\View\Cell;

use App\Service\Ksef\LatarniaKsefClient;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use Cake\View\Cell;

final class KsefStatusCell extends Cell
{
    public function display(): void
    {
        $this->set($this->buildViewData());
    }

    /**
     * @return array{enabled:bool,status:string,statusText:string,statusClass:string,messageTitle:?string,messageText:?string,tooltip:?string}
     */
    private function buildViewData(): array
    {
        $config = (array)(Configure::read('LatarniaKsef') ?? []);
        $baseUrl = trim((string)($config['baseUrl'] ?? ''));

        if ($baseUrl === '') {
            return [
                'enabled' => false,
                'status' => '',
                'statusText' => '',
                'statusClass' => 'text-muted',
                'messageTitle' => null,
                'messageText' => null,
                'tooltip' => null,
            ];
        }

        $timeout = (int)($config['timeout'] ?? 4);
        $cacheConfig = (string)($config['cacheConfig'] ?? 'latarniaKsef');

        $payload = $this->readOrFetchStatus($baseUrl, $timeout, $cacheConfig);

        $status = strtoupper((string)($payload['status'] ?? ''));
        [$statusText, $statusClass] = $this->mapStatus($status);

        $activeMessage = $this->pickActiveMessage((array)($payload['messages'] ?? []));
        $messageTitle = $activeMessage ? (string)($activeMessage['title'] ?? '') : '';
        $messageText = $activeMessage ? (string)($activeMessage['text'] ?? '') : '';

        $messageTitle = trim($messageTitle) !== '' ? trim($messageTitle) : null;
        $messageText = trim($messageText) !== '' ? trim($messageText) : null;

        $tooltipParts = [];
        if ($statusText !== '') {
            $tooltipParts[] = 'KSeF: ' . $statusText;
        }
        if ($messageTitle) {
            $tooltipParts[] = $messageTitle;
        }
        if ($messageText) {
            $tooltipParts[] = $this->truncate($messageText, 280);
        }
        $tooltip = $tooltipParts ? implode("\n", $tooltipParts) : null;

        return [
            'enabled' => true,
            'status' => $status,
            'statusText' => $statusText,
            'statusClass' => $statusClass,
            'messageTitle' => $messageTitle,
            'messageText' => $messageText,
            'tooltip' => $tooltip,
        ];
    }

    private function readOrFetchStatus(string $baseUrl, int $timeout, string $cacheConfig): array
    {
        try {
            $cached = Cache::read('latarnia_status', $cacheConfig);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Ignore cache errors and try live fetch.
            Log::warning('Latarnia KSeF cache read failed: ' . $e->getMessage());
        }

        try {
            $client = new LatarniaKsefClient($baseUrl, null, $timeout);
            $payload = $client->fetchStatus();

            if (is_array($payload) && $payload !== []) {
                try {
                    Cache::write('latarnia_status', $payload, $cacheConfig);
                } catch (\Throwable $e) {
                    Log::warning('Latarnia KSeF cache write failed: ' . $e->getMessage());
                }

                return $payload;
            }
        } catch (\Throwable $e) {
            Log::notice('Latarnia KSeF fetchStatus failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function mapStatus(string $status): array
    {
        return match ($status) {
            'AVAILABLE' => ['Dostępny', 'text-success'],
            'MAINTENANCE' => ['Przerwa techniczna', 'text-warning'],
            'FAILURE' => ['Awaria', 'text-danger'],
            'TOTAL_FAILURE' => ['Niedostępny', 'text-danger'],
            default => ['Brak danych', 'text-muted'],
        };
    }

    private function pickActiveMessage(array $messages): ?array
    {
        if ($messages === []) {
            return null;
        }

        $now = FrozenTime::now();

        $active = [];
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            if ($this->isMessageActive($msg, $now)) {
                $active[] = $msg;
            }
        }

        if ($active === []) {
            return null;
        }

        usort($active, function (array $a, array $b): int {
            $ap = (string)($a['published'] ?? $a['start'] ?? '');
            $bp = (string)($b['published'] ?? $b['start'] ?? '');
            return strcmp($bp, $ap);
        });

        return $active[0];
    }

    private function isMessageActive(array $msg, FrozenTime $now): bool
    {
        foreach (['published', 'start', 'end'] as $key) {
            if (!isset($msg[$key]) || !is_string($msg[$key]) || trim($msg[$key]) === '') {
                continue;
            }

            try {
                $msg[$key] = new FrozenTime($msg[$key]);
            } catch (\Throwable $e) {
                // ignore invalid date
                unset($msg[$key]);
            }
        }

        if (isset($msg['published']) && $msg['published'] instanceof FrozenTime) {
            if ($msg['published']->isFuture()) {
                return false;
            }
        }

        if (isset($msg['start']) && $msg['start'] instanceof FrozenTime) {
            if ($msg['start']->gt($now)) {
                return false;
            }
        }

        if (isset($msg['end']) && $msg['end'] instanceof FrozenTime) {
            if ($msg['end']->lt($now)) {
                return false;
            }
        }

        return true;
    }

    private function truncate(string $text, int $maxLen): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLen - 1)) . '…';
    }
}

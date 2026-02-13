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
        $this->set($this->buildViewData(false));
    }

    public function banner(): void
    {
        $this->set($this->buildViewData(true));
    }

    /**
     * @return array{
     *   enabled:bool,
     *   status:string,
     *   statusText:string,
     *   statusClass:string,
     *   messageTitle:?string,
     *   messageText:?string,
     *   tooltip:?string,
     *   messages:array,
     *   activeMessages:array,
     *   upcomingMessages:array,
     *   important:?array,
     *   showBanner:bool
     * }
     */
    private function buildViewData(bool $forBanner): array
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
                'messages' => [],
                'activeMessages' => [],
                'upcomingMessages' => [],
                'important' => null,
                'showBanner' => false,
            ];
        }

        $timeout = (int)($config['timeout'] ?? 4);
        $cacheConfig = (string)($config['cacheConfig'] ?? 'latarniaKsef');

        $payload = $this->readOrFetchPayload($baseUrl, $timeout, $cacheConfig);

        $status = strtoupper((string)($payload['status'] ?? ''));
        [$statusText, $statusClass] = $this->mapStatus($status);

        $messages = $this->normalizeMessages((array)($payload['messages'] ?? []));
        $activeMessages = $this->filterActiveMessages($messages);
        $upcomingMessages = $this->filterUpcomingMessages($messages);

        $activeMessage = $activeMessages[0] ?? null;
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

        $important = $this->pickImportantMessage($activeMessages, $upcomingMessages);

        // On login page we want a clearly visible message if there is anything important.
        $showBanner = $forBanner && is_array($important);

        return [
            'enabled' => true,
            'status' => $status,
            'statusText' => $statusText,
            'statusClass' => $statusClass,
            'messageTitle' => $messageTitle,
            'messageText' => $messageText,
            'tooltip' => $tooltip,
            'messages' => $messages,
            'activeMessages' => $activeMessages,
            'upcomingMessages' => $upcomingMessages,
            'important' => $important,
            'showBanner' => $showBanner,
        ];
    }

    private function readOrFetchPayload(string $baseUrl, int $timeout, string $cacheConfig): array
    {
        try {
            $cached = Cache::read('latarnia_payload_v1', $cacheConfig);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Ignore cache errors and try live fetch.
            Log::warning('Latarnia KSeF cache read failed: ' . $e->getMessage());
        }

        try {
            $client = new LatarniaKsefClient($baseUrl, null, $timeout);
            $statusPayload = $client->fetchStatus();
            $messages = (array)($statusPayload['messages'] ?? []);

            // Ensure we have messages regardless of status, to show MF communications on click.
            if ($messages === []) {
                try {
                    $messagesPayload = $client->fetchMessages();
                    $messages = is_array($messagesPayload) ? $messagesPayload : [];
                } catch (\Throwable $e) {
                    Log::notice('Latarnia KSeF fetchMessages failed: ' . $e->getMessage());
                }
            }

            $payload = [
                'status' => $statusPayload['status'] ?? null,
                'messages' => $messages,
                'fetchedAt' => time(),
            ];

            if (is_array($payload) && $payload !== []) {
                try {
                    Cache::write('latarnia_payload_v1', $payload, $cacheConfig);
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
        $msg = $this->withParsedDates($msg);

        if (isset($msg['_published']) && $msg['_published'] instanceof FrozenTime && $msg['_published']->isFuture()) {
            return false;
        }
        if (isset($msg['_start']) && $msg['_start'] instanceof FrozenTime && $msg['_start']->gt($now)) {
            return false;
        }
        if (isset($msg['_end']) && $msg['_end'] instanceof FrozenTime && $msg['_end']->lt($now)) {
            return false;
        }

        return true;
    }

    private function normalizeMessages(array $messages): array
    {
        if ($messages === []) {
            return [];
        }

        $out = [];
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $msg = $this->withParsedDates($msg);
            $out[] = $msg;
        }

        // Sort: active first, then upcoming, then others; within group newest first.
        $now = FrozenTime::now();
        usort($out, function (array $a, array $b) use ($now): int {
            $ag = $this->messageGroup($a, $now);
            $bg = $this->messageGroup($b, $now);
            if ($ag !== $bg) {
                return $ag <=> $bg;
            }
            $ap = (string)($a['published'] ?? $a['start'] ?? $a['end'] ?? '');
            $bp = (string)($b['published'] ?? $b['start'] ?? $b['end'] ?? '');
            return strcmp($bp, $ap);
        });

        return $out;
    }

    private function filterActiveMessages(array $messages): array
    {
        $now = FrozenTime::now();
        $active = [];
        foreach ($messages as $msg) {
            if ($this->isMessageActive($msg, $now)) {
                $active[] = $msg;
            }
        }
        return $active;
    }

    private function filterUpcomingMessages(array $messages): array
    {
        $now = FrozenTime::now();
        $upcoming = [];
        foreach ($messages as $msg) {
            $msg = $this->withParsedDates($msg);
            if (!isset($msg['_start']) || !$msg['_start'] instanceof FrozenTime) {
                continue;
            }
            if ($msg['_start']->lte($now)) {
                continue;
            }
            $upcoming[] = $msg;
        }

        usort($upcoming, function (array $a, array $b): int {
            /** @var FrozenTime|null $as */
            $as = $a['_start'] ?? null;
            /** @var FrozenTime|null $bs */
            $bs = $b['_start'] ?? null;
            if (!$as instanceof FrozenTime) {
                return 1;
            }
            if (!$bs instanceof FrozenTime) {
                return -1;
            }
            return $as->getTimestamp() <=> $bs->getTimestamp();
        });

        return $upcoming;
    }

    private function pickImportantMessage(array $activeMessages, array $upcomingMessages): ?array
    {
        if ($activeMessages !== []) {
            return $activeMessages[0];
        }

        if ($upcomingMessages === []) {
            return null;
        }

        // Show upcoming message if it starts within 7 days.
        $now = FrozenTime::now();
        $candidate = $upcomingMessages[0];
        $candidate = $this->withParsedDates($candidate);

        /** @var FrozenTime|null $start */
        $start = $candidate['_start'] ?? null;
        if (!$start instanceof FrozenTime) {
            return null;
        }

        if ($start->lte($now->addDays(7))) {
            return $candidate;
        }

        return null;
    }

    private function withParsedDates(array $msg): array
    {
        foreach (['published' => '_published', 'start' => '_start', 'end' => '_end'] as $src => $dst) {
            if (!isset($msg[$src]) || !is_string($msg[$src]) || trim($msg[$src]) === '') {
                continue;
            }
            if (isset($msg[$dst]) && $msg[$dst] instanceof FrozenTime) {
                continue;
            }
            try {
                $msg[$dst] = new FrozenTime($msg[$src]);
            } catch (\Throwable $e) {
                unset($msg[$dst]);
            }
        }

        return $msg;
    }

    private function messageGroup(array $msg, FrozenTime $now): int
    {
        // 0 - active, 1 - upcoming, 2 - other
        if ($this->isMessageActive($msg, $now)) {
            return 0;
        }

        $msg = $this->withParsedDates($msg);
        if (isset($msg['_start']) && $msg['_start'] instanceof FrozenTime && $msg['_start']->gt($now)) {
            return 1;
        }

        return 2;
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

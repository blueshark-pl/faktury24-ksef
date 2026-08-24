<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\I18n\DateTime;
use Cake\Log\Log;

/**
 * Wrapper na Gmail API v1 przez REST + OAuth 2.0.
 *
 * Wymaga w config/app_local.php:
 *   'Google' => [
 *       'clientId' => 'XXX.apps.googleusercontent.com',
 *       'clientSecret' => 'XXX',
 *       'redirectUri' => 'https://booklio.pl/crm/email-accounts/google-callback',
 *   ]
 *
 * Setup w Google Cloud Console:
 *   1. Utworz projekt: console.cloud.google.com
 *   2. Enable Gmail API: APIs & Services -> Library -> Gmail API -> Enable
 *   3. OAuth consent screen: External (dla osobistego Gmail) lub Internal (Workspace)
 *      Scopes: https://www.googleapis.com/auth/gmail.readonly + userinfo.email
 *   4. Credentials -> Create OAuth 2.0 Client ID -> Web application
 *      Authorized redirect URIs: https://booklio.pl/crm/email-accounts/google-callback
 *   5. Skopiuj client_id + client_secret do app_local.php
 *
 * Scope 'gmail.readonly' - tylko czytamy, nie modyfikujemy folderow/labeli.
 */
class GmailApiService
{
    private const AUTH_URL   = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
    private const API_BASE   = 'https://gmail.googleapis.com/gmail/v1/users/me';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    private const SCOPES = [
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
    ];

    /**
     * Zbuduj URL do OAuth consent screen.
     * User klika -> Google prosi o zgode -> redirect z ?code=X do callback.
     *
     * @param string $state CSRF token/marker (przekazywany przez url state param)
     */
    public function getAuthorizationUrl(string $state = ''): string
    {
        $clientId = (string)Configure::read('Google.clientId', '');
        $redirect = (string)Configure::read('Google.redirectUri', '');
        if ($clientId === '' || $redirect === '') {
            throw new \RuntimeException('Brak Google.clientId lub Google.redirectUri w app_local.php');
        }
        $params = [
            'client_id'     => $clientId,
            'redirect_uri'  => $redirect,
            'response_type' => 'code',
            'scope'         => implode(' ', self::SCOPES),
            'access_type'   => 'offline',   // wymagane zeby dostac refresh_token
            'prompt'        => 'consent',   // zawsze pokaz consent (dla refresh_token)
            'state'         => $state,
        ];
        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Wymien authorization code na access_token + refresh_token.
     * @return array{access_token:string, refresh_token:string, expires_in:int, id_token?:string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $client = new Client(['timeout' => 15]);
        $response = $client->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => (string)Configure::read('Google.clientId'),
            'client_secret' => (string)Configure::read('Google.clientSecret'),
            'redirect_uri'  => (string)Configure::read('Google.redirectUri'),
            'grant_type'    => 'authorization_code',
        ]);
        $body = json_decode((string)$response->getBody(), true);
        if (!$response->isOk() || empty($body['access_token'])) {
            throw new \RuntimeException('Token exchange failed: ' . $response->getStringBody());
        }
        return $body;
    }

    /**
     * Odswiez access_token przez refresh_token.
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $client = new Client(['timeout' => 15]);
        $response = $client->post(self::TOKEN_URL, [
            'refresh_token' => $refreshToken,
            'client_id'     => (string)Configure::read('Google.clientId'),
            'client_secret' => (string)Configure::read('Google.clientSecret'),
            'grant_type'    => 'refresh_token',
        ]);
        $body = json_decode((string)$response->getBody(), true);
        if (!$response->isOk() || empty($body['access_token'])) {
            throw new \RuntimeException('Token refresh failed: ' . $response->getStringBody());
        }
        return $body; // {access_token, expires_in, ...} - bez refresh_token (ten zostaje staly)
    }

    /**
     * Pobierz email z konta OAuth (dla zapisania jako username w koncie).
     */
    public function getUserEmail(string $accessToken): ?string
    {
        try {
            $client = new Client(['timeout' => 10]);
            $response = $client->get(self::USERINFO_URL, [], [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            ]);
            if (!$response->isOk()) return null;
            $data = json_decode((string)$response->getBody(), true);
            return $data['email'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Lista wiadomosci po historyId (incremental sync) lub najnowszych 100.
     * @return array [historyId, [message_ids]]
     */
    public function listMessages(string $accessToken, ?string $historyId = null, int $maxResults = 100): array
    {
        // Gmail API limits: /messages max=500, /history max=500. Guard.
        $maxResults = max(1, min(500, $maxResults));

        $client = new Client(['timeout' => 30]);
        $ids = [];
        $newHistoryId = null;

        if ($historyId) {
            // Incremental: /history?startHistoryId=X
            $response = $client->get(self::API_BASE . '/history', [
                'startHistoryId' => $historyId,
                'historyTypes'   => 'messageAdded',
                'maxResults'     => $maxResults, // INT, nie string!
            ], [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json'],
            ]);
            if ($response->getStatusCode() === 404) {
                // History za stara - fallback do fresh list
                Log::info('Gmail history 404 - fallback do fresh list');
                return $this->listMessages($accessToken, null, $maxResults);
            }
            if (!$response->isOk()) {
                throw new \RuntimeException('Gmail history HTTP ' . $response->getStatusCode() . ': ' . $response->getStringBody());
            }
            $body = json_decode((string)$response->getBody(), true);
            $newHistoryId = $body['historyId'] ?? null;
            foreach ($body['history'] ?? [] as $h) {
                foreach ($h['messagesAdded'] ?? [] as $ma) {
                    if (!empty($ma['message']['id'])) $ids[] = $ma['message']['id'];
                }
            }
        } else {
            // Fresh: /messages?q=in:inbox newer_than:30d
            $response = $client->get(self::API_BASE . '/messages', [
                'maxResults' => $maxResults, // INT, nie string!
                'q'          => 'in:inbox newer_than:30d',
            ], [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json'],
            ]);
            if (!$response->isOk()) {
                throw new \RuntimeException('Gmail messages HTTP ' . $response->getStatusCode() . ': ' . $response->getStringBody());
            }
            $body = json_decode((string)$response->getBody(), true);
            foreach ($body['messages'] ?? [] as $m) {
                if (!empty($m['id'])) $ids[] = $m['id'];
            }
            // Pobierz aktualny historyId z profilu
            $profile = $client->get(self::API_BASE . '/profile', [], [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            ]);
            if ($profile->isOk()) {
                $pb = json_decode((string)$profile->getBody(), true);
                $newHistoryId = $pb['historyId'] ?? null;
            }
        }

        return [$newHistoryId, array_unique($ids)];
    }

    /**
     * Pobierz pelna wiadomosc: envelope + body + attachments.
     * Zwraca strukture podobna do IMAP wynikow, zeby latwo zmapowac na
     * crm_email_messages.
     */
    public function getMessage(string $accessToken, string $msgId): ?array
    {
        try {
            $client = new Client(['timeout' => 30]);
            $response = $client->get(self::API_BASE . '/messages/' . $msgId, [
                'format' => 'full',
            ], [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json'],
            ]);
            if (!$response->isOk()) {
                Log::warning('Gmail getMessage HTTP ' . $response->getStatusCode() . ' dla ' . $msgId);
                return null;
            }
            $msg = json_decode((string)$response->getBody(), true);
            return $this->parseMessage($msg);
        } catch (\Throwable $e) {
            Log::warning('Gmail getMessage failed dla ' . $msgId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * FALA 16: Pobierz zawartosc pojedynczego zalacznika (base64url encoded).
     * Zwraca binarna zawartosc (decoded z base64url) lub null przy bledzie.
     *
     * @param string $accessToken OAuth token
     * @param string $msgId Gmail message ID
     * @param string $attachmentId ID z attachments[]['attachment_id']
     * @param int $maxSize max bajtow do pobrania (dla ochrony przed OOM)
     */
    public function getAttachment(string $accessToken, string $msgId, string $attachmentId, int $maxSize = 10485760): ?string
    {
        try {
            $client = new Client(['timeout' => 60]);
            $response = $client->get(
                self::API_BASE . '/messages/' . $msgId . '/attachments/' . $attachmentId,
                [],
                ['headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json']]
            );
            if (!$response->isOk()) {
                Log::warning('Gmail getAttachment HTTP ' . $response->getStatusCode() . ' dla ' . $msgId . '/' . $attachmentId);
                return null;
            }
            $data = json_decode((string)$response->getBody(), true);
            $b64 = $data['data'] ?? '';
            $size = (int)($data['size'] ?? 0);
            if ($b64 === '') return null;
            if ($size > $maxSize) {
                Log::warning('Gmail attachment za duzy (' . $size . 'B > ' . $maxSize . 'B), skipping');
                return null;
            }
            return base64_decode(strtr($b64, '-_', '+/'));
        } catch (\Throwable $e) {
            Log::warning('Gmail getAttachment failed dla ' . $msgId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parsuje Gmail API message payload -> plaska struktura.
     */
    private function parseMessage(array $msg): array
    {
        $headers = [];
        foreach ($msg['payload']['headers'] ?? [] as $h) {
            $headers[strtolower($h['name'])] = $h['value'];
        }

        $fromRaw = $headers['from'] ?? '';
        [$fromName, $fromEmail] = $this->parseAddress($fromRaw);

        $bodyText = '';
        $bodyHtml = '';
        $attachments = [];
        $this->walkGmailParts($msg['payload'] ?? [], $bodyText, $bodyHtml, $attachments);

        if ($bodyText === '' && $bodyHtml !== '') {
            $bodyText = trim(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $bodyHtml)));
        }

        // Received date - preferujemy Date header, fallback internalDate
        $receivedAt = null;
        if (!empty($headers['date'])) {
            try { $receivedAt = new DateTime($headers['date']); } catch (\Throwable $e) {}
        }
        if (!$receivedAt && !empty($msg['internalDate'])) {
            $receivedAt = new DateTime('@' . ((int)($msg['internalDate'] / 1000)));
        }

        return [
            'gmail_id'    => (string)($msg['id'] ?? ''),
            'thread_id'   => substr((string)($msg['threadId'] ?? ''), 0, 30),
            'message_id'  => trim((string)($headers['message-id'] ?? ''), '<>'),
            'in_reply_to' => trim((string)($headers['in-reply-to'] ?? ''), '<>'),
            'from_email'  => strtolower($fromEmail),
            'from_name'   => $fromName,
            'to_emails'   => $headers['to'] ?? null,
            'cc_emails'   => $headers['cc'] ?? null,
            'subject'     => $headers['subject'] ?? '',
            'received_at' => $receivedAt,
            'body_text'   => $bodyText,
            'body_html'   => $bodyHtml,
            'attachments' => $attachments,
        ];
    }

    private function walkGmailParts(array $part, string &$text, string &$html, array &$attachments): void
    {
        $mime = $part['mimeType'] ?? '';
        $filename = $part['filename'] ?? '';

        // Multipart - rekurencja
        if (!empty($part['parts']) && is_array($part['parts'])) {
            foreach ($part['parts'] as $sub) {
                $this->walkGmailParts($sub, $text, $html, $attachments);
            }
            return;
        }

        // Attachment (ma filename)
        if ($filename !== '') {
            $attachments[] = [
                'filename'      => $filename,
                'mime'          => $mime,
                'size'          => (int)($part['body']['size'] ?? 0),
                'attachment_id' => $part['body']['attachmentId'] ?? null,
            ];
            return;
        }

        // Body - text lub html
        $data = $part['body']['data'] ?? '';
        if ($data === '') return;
        // Gmail zwraca base64url encoded
        $decoded = base64_decode(strtr($data, '-_', '+/'));

        if (str_starts_with($mime, 'text/plain')) {
            $text .= $decoded;
        } elseif (str_starts_with($mime, 'text/html')) {
            $html .= $decoded;
        }
    }

    /**
     * Parsuj "Jan Kowalski <jan@example.com>" -> [name, email]
     */
    private function parseAddress(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return ['', ''];
        if (preg_match('/^(.*)<([^>]+)>$/', $raw, $m)) {
            return [trim($m[1], ' "\''), trim($m[2])];
        }
        // Sam email
        return ['', $raw];
    }
}

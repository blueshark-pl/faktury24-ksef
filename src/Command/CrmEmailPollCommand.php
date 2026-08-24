<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Cron IMAP polling dla CRM Email 2-way sync.
 *
 * Dla kazdego aktywnego konta z crm_email_accounts:
 *   - Loguje sie przez IMAP (imap_open)
 *   - Pobiera nowe UID > last_seen_uid (imap_search UID SINCE)
 *   - Dla kazdej wiadomosci: parsuje FROM, matchuje po email w leads
 *   - Jesli match: loguje activity_type=email_in z subject + fragment body
 *   - Aktualizuje last_seen_uid + last_synced_at + counters
 *
 * Wymagania: extension imap w PHP.
 *
 * Usage (cron every 5 min):
 *   bin/cake crm_email_poll
 *   bin/cake crm_email_poll --dry
 *   bin/cake crm_email_poll --account=<uuid>
 *   bin/cake crm_email_poll --company=<uuid>
 *   bin/cake crm_email_poll --max=50    (max wiadomosci per konto na jeden run)
 */
class CrmEmailPollCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Poll IMAP - pobiera nowe emaile i loguje jako activity email_in.')
            ->addOption('dry', ['boolean' => true, 'default' => false,
                'help' => 'Preview mode - loguje co by zostalo utworzone, nie zapisuje.'])
            ->addOption('account', ['default' => null, 'help' => 'Konkretne konto (uuid)'])
            ->addOption('company', ['default' => null, 'help' => 'Ogranicz do jednej firmy'])
            ->addOption('max', ['default' => 100, 'help' => 'Max wiadomosci per konto na jeden run'])
            ->addOption('force', ['boolean' => true, 'default' => false,
                'help' => 'Ignoruj cooldown sync_frequency_min - wymus sync wszystkich aktywnych kont']);
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        // FALA 13: ext-imap wymagane tylko dla auth_type='imap' kont.
        // Konta gmail_oauth uzywaja Gmail API i nie potrzebuja rozszerzenia.
        if (!function_exists('imap_open')) {
            $io->warning('PHP IMAP extension nie jest dostepne - konta auth_type=imap zostana pominiete. Konta gmail_oauth beda dzialac normalnie.');
        }

        $dry = (bool)$args->getOption('dry');
        $accountId = $args->getOption('account');
        $companyFilter = $args->getOption('company');
        $max = (int)$args->getOption('max');
        $force = (bool)$args->getOption('force');

        $EA = TableRegistry::getTableLocator()->get('CrmEmailAccounts');
        $Leads = TableRegistry::getTableLocator()->get('Leads');
        $Acts = TableRegistry::getTableLocator()->get('LeadActivities');

        if ($accountId) {
            $accounts = [$EA->get($accountId)];
        } elseif ($force) {
            // --force: bierz wszystkie aktywne konta niezaleznie od cooldown
            $q = $EA->find()->where(['is_active' => true]);
            if ($companyFilter) $q->where(['company_id' => $companyFilter]);
            $accounts = $q->all()->toArray();
            $io->out('FORCE mode - ignoruje cooldown sync_frequency_min');
        } else {
            $accounts = $EA->findDueForSync($companyFilter);
        }

        $io->out(sprintf('Znaleziono %d kont do sync.', count($accounts)));
        if ($dry) $io->warning('DRY-RUN - nie zapisujemy.');

        $totalMsg = 0;
        $totalAct = 0;
        foreach ($accounts as $acc) {
            $authType = $acc->auth_type ?? 'imap';
            $io->out(sprintf('[%s] %s (%s, auth=%s)', $acc->id, $acc->label, $acc->username, $authType));

            // FALA 13: Gmail OAuth path
            if ($authType === 'gmail_oauth') {
                try {
                    [$msgs, $acts] = $this->syncGmailOauth($acc, $EA, $Leads, $Acts, $io, $dry, $max);
                    $totalMsg += $msgs;
                    $totalAct += $acts;
                } catch (\Throwable $e) {
                    $io->error('  Gmail OAuth exception: ' . $e->getMessage());
                    if (!$dry) {
                        $acc->last_error = substr($e->getMessage(), 0, 500);
                        $acc->last_synced_at = new DateTime();
                        $EA->save($acc);
                    }
                }
                continue;
            }

            // ==== IMAP path (FALA 6) ====
            if (!function_exists('imap_open')) {
                $io->error('  IMAP ext niedostepny - pomin lub przelacz konto na Gmail OAuth.');
                continue;
            }

            $password = $EA->decryptPassword($acc->password_encrypted);
            if (!$password) {
                $io->error('  Nie mozna zdekodowac hasla - pomijam.');
                continue;
            }

            $ssl = $acc->use_ssl ? '/ssl' : '';
            $mailbox = sprintf('{%s:%d/imap%s}%s', $acc->imap_host, $acc->imap_port, $ssl, $acc->folder);

            try {
                $conn = @imap_open($mailbox, $acc->username, $password, OP_READONLY);
                if (!$conn) {
                    $err = imap_last_error() ?: 'unknown';
                    $io->error('  IMAP login failed: ' . $err);
                    $acc->last_error = $err;
                    $acc->last_synced_at = new DateTime();
                    if (!$dry) $EA->save($acc);
                    continue;
                }

                // Znajdz nowe wiadomosci (UID > last_seen_uid)
                $lastUid = (int)($acc->last_seen_uid ?? 0);
                $searchQ = $lastUid > 0 ? ($lastUid + 1) . ':*' : '1:*';
                $uids = @imap_search($conn, 'UID ' . $searchQ, SE_UID);
                if (!$uids || !is_array($uids)) $uids = [];

                // Deduplikacja + sort + limit
                $uids = array_unique(array_map('intval', $uids));
                $uids = array_filter($uids, fn($u) => $u > $lastUid);
                sort($uids);
                $uids = array_slice($uids, 0, $max);

                $io->out(sprintf('  Nowe wiadomosci: %d (UID > %d, max %d)', count($uids), $lastUid, $max));

                $newLastUid = $lastUid;
                $accMsgCount = 0;
                $accActCount = 0;

                foreach ($uids as $uid) {
                    try {
                        $header = @imap_headerinfo($conn, imap_msgno($conn, $uid));
                        if (!$header) { $newLastUid = max($newLastUid, $uid); continue; }

                        $fromEmail = '';
                        if (!empty($header->from) && is_array($header->from)) {
                            $f = $header->from[0];
                            $fromEmail = strtolower(($f->mailbox ?? '') . '@' . ($f->host ?? ''));
                        }
                        $subject = self::decodeMime($header->subject ?? '');
                        $date    = $header->date ?? '';
                        $receivedAt = $date ? new DateTime($date) : new DateTime();

                        $accMsgCount++;

                        // Znajdz leada po email (from)
                        $lead = null;
                        if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                            $lead = $Leads->find()
                                ->where([
                                    'company_id' => $acc->company_id,
                                    'LOWER(email)' => $fromEmail,
                                ])
                                ->first();
                        }

                        if (!$lead) {
                            $io->out(sprintf('    UID %d: from=%s - brak leada, pomijam', $uid, $fromEmail));
                            $newLastUid = max($newLastUid, $uid);
                            continue;
                        }

                        // Pobierz PELNE body: text + HTML + attachments (FALA 11)
                        [$bodyText, $bodyHtml, $attachments] = $this->fetchFullBody($conn, $uid);
                        $bodySnippet = $bodyText ? mb_substr($bodyText, 0, 500) : '';

                        $messageId = trim((string)($header->message_id ?? ''));
                        $inReplyTo = trim((string)($header->references ?? '') ?: (string)($header->in_reply_to ?? ''));
                        $threadId  = $this->makeThreadId($subject, $inReplyTo);
                        $fromName  = self::decodeMime((string)($header->from[0]->personal ?? ''));
                        $toEmails  = self::extractEmails($header->to ?? []);
                        $ccEmails  = self::extractEmails($header->cc ?? []);

                        $io->out(sprintf('    UID %d: from=%s -> lead %s (%s), body %d chars, %d attach',
                            $uid, $fromEmail, $lead->id, $lead->company_name,
                            strlen($bodyText), count($attachments)));

                        if (!$dry) {
                            $Acts->logSystem(
                                (string)$acc->company_id, (string)$lead->id, 'email_in',
                                $subject ?: '(bez tematu)',
                                $bodySnippet,
                                ['imap_uid' => $uid, 'from' => $fromEmail, 'account_id' => $acc->id],
                                null
                            );

                            // Zapisz PELNA wiadomosc do crm_email_messages
                            try {
                                $Messages = TableRegistry::getTableLocator()->get('CrmEmailMessages');
                                $existsQ = $Messages->find()->where(['account_id' => $acc->id, 'imap_uid' => $uid]);
                                if ($messageId !== '') {
                                    $existsQ->orWhere(['message_id' => $messageId]);
                                }
                                if ($existsQ->count() === 0) {
                                    $Messages->save($Messages->newEntity([
                                        'id'          => \Cake\Utility\Text::uuid(),
                                        'company_id'  => $acc->company_id,
                                        'account_id'  => $acc->id,
                                        'lead_id'     => $lead->id,
                                        'imap_uid'    => $uid,
                                        'message_id'  => $messageId ?: null,
                                        'in_reply_to' => $inReplyTo ?: null,
                                        'thread_id'   => $threadId,
                                        'direction'   => 'in',
                                        'from_email'  => $fromEmail,
                                        'from_name'   => $fromName ?: null,
                                        'to_emails'   => $toEmails,
                                        'cc_emails'   => $ccEmails,
                                        'subject'     => mb_substr($subject, 0, 500),
                                        'received_at' => $receivedAt,
                                        'body_text'   => mb_substr($bodyText, 0, 500000),
                                        'body_html'   => mb_substr($bodyHtml, 0, 500000),
                                        'body_length' => strlen($bodyText),
                                        'attachments_json' => json_encode($attachments, JSON_UNESCAPED_UNICODE),
                                        'attachments_count' => count($attachments),
                                    ]));
                                }
                            } catch (\Throwable $e) {
                                \Cake\Log\Log::warning('CrmEmailMessages save failed: ' . $e->getMessage());
                            }

                            $lead->last_contacted_at = $receivedAt;
                            $Leads->save($lead);
                        }
                        $accActCount++;
                        $newLastUid = max($newLastUid, $uid);
                    } catch (\Throwable $e) {
                        $io->error(sprintf('    UID %d: exception - %s', $uid, $e->getMessage()));
                    }
                }

                @imap_close($conn);

                if (!$dry) {
                    $acc->last_seen_uid = $newLastUid;
                    $acc->last_synced_at = new DateTime();
                    $acc->last_error = null;
                    $acc->messages_synced_total = (int)$acc->messages_synced_total + $accMsgCount;
                    $acc->activities_created_total = (int)$acc->activities_created_total + $accActCount;
                    $EA->save($acc);
                }

                $totalMsg += $accMsgCount;
                $totalAct += $accActCount;
                $io->success(sprintf('  Konto %s: %d wiadomosci, %d activities.',
                    $acc->label, $accMsgCount, $accActCount));
            } catch (\Throwable $e) {
                $io->error('  Exception: ' . $e->getMessage());
                if (!$dry) {
                    $acc->last_error = substr($e->getMessage(), 0, 500);
                    $acc->last_synced_at = new DateTime();
                    $EA->save($acc);
                }
            }
        }

        $io->success(sprintf('Zakonczono. Wiadomosci sprawdzonych: %d, activities utworzonych: %d.',
            $totalMsg, $totalAct));
        return static::CODE_SUCCESS;
    }

    private static function decodeMime(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';
        if (!function_exists('imap_mime_header_decode')) return $s;
        $parts = imap_mime_header_decode($s);
        $out = '';
        foreach ($parts as $p) {
            $charset = $p->charset ?? 'utf-8';
            if ($charset === 'default') $charset = 'utf-8';
            $text = $p->text;
            if (strtolower($charset) !== 'utf-8' && function_exists('iconv')) {
                $conv = @iconv($charset, 'UTF-8//IGNORE', $text);
                if ($conv !== false) $text = $conv;
            }
            $out .= $text;
        }
        return $out;
    }

    /**
     * Pobiera pelne body wiadomosci (text + HTML) + attachments meta.
     * Iteruje po MIME parts przez imap_fetchstructure.
     *
     * @return array [body_text, body_html, [attachments_meta]]
     */
    private function fetchFullBody($conn, int $uid): array
    {
        $bodyText = '';
        $bodyHtml = '';
        $attachments = [];

        try {
            $structure = @imap_fetchstructure($conn, $uid, FT_UID);
            if (!$structure) return ['', '', []];

            $this->walkParts($conn, $uid, $structure, '', $bodyText, $bodyHtml, $attachments);

            // Jesli nie ma text ale jest HTML - zrob strip
            if ($bodyText === '' && $bodyHtml !== '') {
                $bodyText = trim(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $bodyHtml)));
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('fetchFullBody exception UID ' . $uid . ': ' . $e->getMessage());
        }
        return [$bodyText, $bodyHtml, $attachments];
    }

    private function walkParts($conn, int $uid, $part, string $section, string &$bodyText, string &$bodyHtml, array &$attachments): void
    {
        // Multipart - rekurencja po sub-parts
        if (!empty($part->parts) && is_array($part->parts)) {
            foreach ($part->parts as $i => $sub) {
                $newSection = $section === '' ? (string)($i + 1) : $section . '.' . ($i + 1);
                $this->walkParts($conn, $uid, $sub, $newSection, $bodyText, $bodyHtml, $attachments);
            }
            return;
        }

        $type = $part->type ?? 0;      // 0=text, 5=image, 4=audio, 7=video, 3=app
        $subtype = strtoupper((string)($part->subtype ?? ''));
        $encoding = $part->encoding ?? 0;
        $filename = '';
        $isAttachment = false;

        // Sprawdz disposition
        if (!empty($part->dparameters) && is_array($part->dparameters)) {
            foreach ($part->dparameters as $p) {
                if (strtolower((string)$p->attribute) === 'filename') {
                    $filename = self::decodeMime((string)$p->value);
                    $isAttachment = true;
                }
            }
        }
        if (!empty($part->parameters) && is_array($part->parameters)) {
            foreach ($part->parameters as $p) {
                if (strtolower((string)$p->attribute) === 'name' && $filename === '') {
                    $filename = self::decodeMime((string)$p->value);
                    $isAttachment = true;
                }
            }
        }
        if (isset($part->disposition) && strtolower((string)$part->disposition) === 'attachment') {
            $isAttachment = true;
        }

        $secKey = $section === '' ? '1' : $section;
        $raw = @imap_fetchbody($conn, $uid, $secKey, FT_UID | FT_PEEK);

        // Decode wg encoding
        if ($encoding === 3) $raw = base64_decode((string)$raw);
        elseif ($encoding === 4) $raw = quoted_printable_decode((string)$raw);

        // Charset
        $charset = 'utf-8';
        if (!empty($part->parameters) && is_array($part->parameters)) {
            foreach ($part->parameters as $p) {
                if (strtolower((string)$p->attribute) === 'charset') $charset = strtolower((string)$p->value);
            }
        }
        if ($charset !== 'utf-8' && $charset !== '' && function_exists('iconv') && !$isAttachment) {
            $conv = @iconv($charset, 'UTF-8//IGNORE', (string)$raw);
            if ($conv !== false) $raw = $conv;
        }

        if ($isAttachment) {
            $attachments[] = [
                'filename' => $filename ?: 'attachment_' . $secKey,
                'mime'     => strtolower(($this->mimeTypeName($type)) . '/' . $subtype),
                'size'     => strlen((string)$raw),
            ];
            return;
        }

        // Body - text/html lub text/plain
        if ($type === 0) {
            if ($subtype === 'HTML') {
                $bodyHtml .= (string)$raw;
            } else {
                $bodyText .= (string)$raw;
            }
        }
    }

    private function mimeTypeName(int $type): string
    {
        return ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'][$type] ?? 'other';
    }

    /**
     * Grupowanie watku po znormalizowanym subject (bez Re:/Fwd:) + reply chain.
     */
    private function makeThreadId(string $subject, string $inReplyTo): string
    {
        $norm = preg_replace('/^(re|fwd|fw|odp)\s*:\s*/i', '', trim($subject));
        $norm = preg_replace('/\s+/', ' ', $norm);
        // Jesli mamy reply chain (References/In-Reply-To) - uzywaj pierwszego msg id
        if ($inReplyTo !== '') {
            preg_match('/<([^>]+)>/', $inReplyTo, $m);
            $first = $m[1] ?? '';
            if ($first !== '') return substr(md5($first), 0, 16);
        }
        return substr(md5(strtolower($norm)), 0, 16);
    }

    private static function extractEmails($addresses): ?string
    {
        if (!is_array($addresses)) return null;
        $out = [];
        foreach ($addresses as $a) {
            if (!empty($a->mailbox) && !empty($a->host)) {
                $out[] = strtolower($a->mailbox . '@' . $a->host);
            }
        }
        return $out ? implode(',', $out) : null;
    }

    /**
     * FALA 14: Auto-create lead z emaila przez GPT.
     * Analizuje treść + podpis, wyciąga dane firmy (nazwa/NIP/adres/telefon/osoba),
     * tworzy lead z source='email_inbound'.
     *
     * Aktywne tylko gdy Configure Crm.autoCreateLeadsFromEmail = true.
     *
     * @return mixed Zapisany lead Entity lub null
     */
    private function tryCreateLeadFromEmail(array $data, $acc, $Leads, ConsoleIo $io)
    {
        $fromEmail = (string)($data['from_email'] ?? '');
        $fromName  = (string)($data['from_name'] ?? '');
        $subject   = (string)($data['subject'] ?? '');
        $bodyText  = (string)($data['body_text'] ?? '');

        // Filter: pomin auto-reply / notifications / vacation replies
        $skipPatterns = ['noreply', 'no-reply', 'notifications', 'mailer-daemon',
            'postmaster', 'no.reply', 'auto-confirm', 'newsletter', 'donotreply'];
        foreach ($skipPatterns as $p) {
            if (stripos($fromEmail, $p) !== false) {
                $io->out("    Auto-skip (system email): {$fromEmail}");
                return null;
            }
        }
        // Filter: pomin jeśli from = własny adres skrzynki (nie tworzymy leada z siebie)
        if (strtolower($fromEmail) === strtolower((string)$acc->username)) {
            $io->out("    Auto-skip (self): {$fromEmail}");
            return null;
        }
        // Filter: pomin gdy body zbyt krotkie (probably auto-response)
        if (strlen($bodyText) < 20) {
            $io->out("    Auto-skip (body too short): " . strlen($bodyText) . ' chars');
            return null;
        }

        // Dedup: jesli lead z tym emailem juz istnieje (od poprzedniego auto-create)
        $existing = $Leads->find()->where([
            'company_id' => $acc->company_id,
            'LOWER(email)' => strtolower($fromEmail),
        ])->first();
        if ($existing) {
            $io->out("    Lead juz istnieje (auto-created wczesniej): {$existing->id}");
            return $existing;
        }

        // GPT extract danych firmy
        $io->out("    Auto-create: GPT analizuje email od {$fromEmail}...");
        try {
            $svc = new \App\Service\Ai\OpenAiService();
            $system = "Jestes analitykiem sprzedazy w polskiej firmie spedycyjnej. "
                . "Analizuj otrzymany email i wyciagaj dane firmy nadawcy z tresci i podpisu. "
                . "Zwroc JSON: {"
                . "\"is_inquiry\": bool (czy to zapytanie handlowe/ofertowe/prosba o kontakt, nie spam/newsletter/personal), "
                . "\"company_name\": string (nazwa firmy z podpisu lub tresci - np. '3CK Software', 'ABC Sp. z o.o.'), "
                . "\"contact_person\": string (imie i nazwisko osoby), "
                . "\"phone\": string (numer tel. w formacie miedzynarodowym lub polskim), "
                . "\"nip\": string (NIP jesli podany, 10 cyfr bez mysnikow), "
                . "\"city\": string, "
                . "\"street\": string (z numerem), "
                . "\"postal_code\": string (format 00-000), "
                . "\"branch_type\": string (jesli mozesz zgadnac: 'road'/'road_reefer'/'road_adr'/'sea'/'air'/'rail'/'intermodal'/'road_oversize'), "
                . "\"message_summary\": string (2-3 zdania po polsku streszczajace zapytanie klienta)"
                . "}"
                . "Puste pola zwroc jako \"\". is_inquiry=false gdy niepewne.";

            $user = "Email od: {$fromName} <{$fromEmail}>\n"
                . "Temat: {$subject}\n\n"
                . "Tresc:\n" . mb_substr($bodyText, 0, 4000);

            $extracted = $svc->chatJson($system, $user, 800);
        } catch (\Throwable $e) {
            $io->error('    GPT extract failed: ' . $e->getMessage());
            return null;
        }

        if (empty($extracted['is_inquiry'])) {
            $io->out("    GPT: nie zapytanie (is_inquiry=false), pomijam");
            return null;
        }

        // Normalize
        $nip = preg_replace('/[^0-9]/', '', (string)($extracted['nip'] ?? ''));
        $lead = $Leads->newEntity([
            'company_id'      => $acc->company_id,
            'company_name'    => trim((string)($extracted['company_name'] ?? '')) ?: ('Nieznany (' . $fromEmail . ')'),
            'nip'             => strlen($nip) === 10 ? $nip : null,
            'country_code'    => 'PL',
            'city'            => trim((string)($extracted['city'] ?? '')) ?: null,
            'street'          => trim((string)($extracted['street'] ?? '')) ?: null,
            'postal_code'     => trim((string)($extracted['postal_code'] ?? '')) ?: null,
            'contact_person'  => trim((string)($extracted['contact_person'] ?? '')) ?: ($fromName ?: null),
            'email'           => strtolower($fromEmail),
            'phone'           => trim((string)($extracted['phone'] ?? '')) ?: null,
            'branch_type'     => trim((string)($extracted['branch_type'] ?? '')) ?: null,
            'contact_channel' => 'email',
            'source'          => 'email_inbound',
            'stage'           => 'new',
            'note'            => trim((string)($extracted['message_summary'] ?? '')) ?: mb_substr($bodyText, 0, 500),
            'next_action_at'  => new DateTime('+1 day'),
            'next_action_description' => 'Odpowiedz na zapytanie z email (auto-utworzony przez CRM)',
            'last_contacted_at' => $data['received_at'] ?? new DateTime(),
        ]);

        if (!$Leads->save($lead)) {
            $io->error('    GPT extracted OK ale save failed: ' . json_encode($lead->getErrors()));
            return null;
        }

        $io->success("    ✓ Auto-utworzono lead: {$lead->company_name} ({$lead->id})");
        return $lead;
    }

    /**
     * FALA 13: Gmail OAuth sync przez Gmail API v1 (zamiast IMAP).
     * @return array [msgCount, actCount]
     */
    private function syncGmailOauth($acc, $EA, $Leads, $Acts, ConsoleIo $io, bool $dry, int $max): array
    {
        $service = new \App\Service\GmailApiService();

        // Sprawdz access_token expiry - jesli za < 60s -> refresh
        $accessToken = $EA->decryptPassword((string)$acc->oauth_access_token);
        $refreshToken = $EA->decryptPassword((string)($acc->oauth_refresh_token ?? ''));
        $needsRefresh = false;
        if ($acc->oauth_expires_at) {
            $expiresIn = $acc->oauth_expires_at->getTimestamp() - time();
            if ($expiresIn < 60) $needsRefresh = true;
        }
        if (!$accessToken || $needsRefresh) {
            if (!$refreshToken) {
                throw new \RuntimeException('Brak refresh_token - user musi ponownie autoryzowac przez /crm/email-accounts/google-auth');
            }
            $io->out('  Refreshing access_token...');
            $tokens = $service->refreshAccessToken($refreshToken);
            $accessToken = $tokens['access_token'];
            $expiresIn = (int)($tokens['expires_in'] ?? 3600);
            if (!$dry) {
                $acc->oauth_access_token = $EA->encryptPassword($accessToken);
                $acc->oauth_expires_at = new DateTime('+' . $expiresIn . ' seconds');
                $EA->save($acc);
            }
        }

        // Lista nowych wiadomosci (incremental via historyId lub fresh)
        [$newHistoryId, $msgIds] = $service->listMessages($accessToken, $acc->oauth_history_id, $max);
        $io->out(sprintf('  Nowych wiadomosci: %d (historyId: %s -> %s)',
            count($msgIds), $acc->oauth_history_id ?? 'null', $newHistoryId ?? '?'));

        $Messages = TableRegistry::getTableLocator()->get('CrmEmailMessages');
        $msgCount = 0;
        $actCount = 0;

        foreach ($msgIds as $gmailId) {
            try {
                $data = $service->getMessage($accessToken, $gmailId);
                if (!$data) continue;
                $data['gmail_id'] = $gmailId;
                $msgCount++;

                // Match po from_email do leada
                $fromEmail = (string)$data['from_email'];
                $lead = null;
                if ($fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                    $lead = $Leads->find()->where([
                        'company_id' => $acc->company_id,
                        'LOWER(email)' => $fromEmail,
                    ])->first();
                }
                // FALA 14: Auto-create lead z email jesli enabled w config
                if (!$lead && (bool)\Cake\Core\Configure::read('Crm.autoCreateLeadsFromEmail', false)) {
                    $lead = $this->tryCreateLeadFromEmail($data, $acc, $Leads, $io);
                }
                if (!$lead) {
                    $io->out(sprintf('    %s: from=%s - brak leada', $gmailId, $fromEmail));
                    continue;
                }
                $io->out(sprintf('    %s: from=%s -> lead %s (body %d chars, %d attach)',
                    $gmailId, $fromEmail, $lead->company_name,
                    strlen($data['body_text']), count($data['attachments'])));

                if ($dry) continue;

                // Log activity + zapisz pelna wiadomosc
                $subject = (string)$data['subject'];
                $bodySnippet = mb_substr($data['body_text'], 0, 500);
                $Acts->logSystem(
                    (string)$acc->company_id, (string)$lead->id, 'email_in',
                    $subject ?: '(bez tematu)',
                    $bodySnippet,
                    ['gmail_id' => $gmailId, 'from' => $fromEmail, 'account_id' => $acc->id],
                    null
                );
                $actCount++; // liczymy zaraz po logSystem - blad ponizej nie moze cofnac tego

                // Dedup po message_id (Gmail: naturalny unikat). Best-effort - blad nie przerywa flow.
                try {
                    $existsQ = $Messages->find()->where(['account_id' => $acc->id]);
                    if (!empty($data['message_id'])) {
                        $existsQ->where(['message_id' => $data['message_id']]);
                    } else {
                        // Bez message_id - dedup przez subject+received_at
                        $existsQ->where([
                            'received_at' => $data['received_at'],
                            'subject' => mb_substr($subject, 0, 500),
                        ]);
                    }
                    if ($existsQ->count() === 0) {
                        $Messages->save($Messages->newEntity([
                            'id'          => \Cake\Utility\Text::uuid(),
                            'company_id'  => $acc->company_id,
                            'account_id'  => $acc->id,
                            'lead_id'     => $lead->id,
                            'imap_uid'    => null, // Gmail nie ma UID - NULL (dopuszczalne wielokrotnie w unique)
                            'message_id'  => $data['message_id'] ?: null,
                            'in_reply_to' => $data['in_reply_to'] ?: null,
                            'thread_id'   => $data['thread_id'] ?: null,
                            'direction'   => 'in',
                            'from_email'  => $fromEmail,
                            'from_name'   => $data['from_name'] ?: null,
                            'to_emails'   => $data['to_emails'],
                            'cc_emails'   => $data['cc_emails'],
                            'subject'     => mb_substr($subject, 0, 500),
                            'received_at' => $data['received_at'],
                            'body_text'   => mb_substr($data['body_text'], 0, 500000),
                            'body_html'   => mb_substr($data['body_html'], 0, 500000),
                            'body_length' => strlen($data['body_text']),
                            'attachments_json' => json_encode($data['attachments'], JSON_UNESCAPED_UNICODE),
                            'attachments_count' => count($data['attachments']),
                        ]));
                    }
                } catch (\Throwable $ex) {
                    $io->out('    Messages->save skipped: ' . $ex->getMessage());
                }

                try {
                    $lead->last_contacted_at = $data['received_at'];
                    $Leads->save($lead);
                } catch (\Throwable $ex) {
                    $io->out('    Leads update skipped: ' . $ex->getMessage());
                }

                // FALA 15: Wykryj czy email zawiera zapytanie o wycene zlecen (multi-shipment quote)
                try {
                    $extraCount = $this->tryExtractQuoteRequest($data, $lead, $Acts, $Leads, $io);
                    $actCount += $extraCount;
                } catch (\Throwable $ee) {
                    $io->out('    quote-extract skipped: ' . $ee->getMessage());
                }
            } catch (\Throwable $e) {
                $io->error(sprintf('    %s: exception - %s', $gmailId, $e->getMessage()));
            }
        }

        if (!$dry) {
            if ($newHistoryId) $acc->oauth_history_id = $newHistoryId;
            $acc->last_synced_at = new DateTime();
            $acc->last_error = null;
            $acc->messages_synced_total = (int)$acc->messages_synced_total + $msgCount;
            $acc->activities_created_total = (int)$acc->activities_created_total + $actCount;
            $EA->save($acc);
        }

        $io->success(sprintf('  Gmail: %d wiadomosci, %d activities.', $msgCount, $actCount));
        return [$msgCount, $actCount];
    }

    /**
     * FALA 15: Wykryj czy email zawiera zapytanie o wycene zlecen (multi-shipment quote).
     * GPT ekstraktor:
     *  - Rozpoznaje forwarded/kierowane maile (WG:/FW:/---Weitergeleitet---)
     *  - Ekstraktuje liste zlecen (from/to/date/weight/pallets/notes/customer_order_ref)
     *  - Zwraca minimum 1 zlecenie -> loguje activity_type='quote_request' z payload_json
     *  - Auto podnosi stage z new/contact -> inquiry
     *
     * Bezpieczne (best-effort try/catch, nie wywala polling'u).
     *
     * @return int liczba dodanych activities (0 lub 1)
     */
    private function tryExtractQuoteRequest(array $data, $lead, $Acts, $Leads, ConsoleIo $io): int
    {
        $subject  = (string)($data['subject'] ?? '');
        $bodyText = (string)($data['body_text'] ?? '');

        // Filtry pre-GPT: pomin krótkie / niezawierajace zadnych sygnalow shipmentu
        if (strlen($bodyText) < 100) {
            return 0;
        }
        // Heurystyka: sygnaly zapytania o transport (jesli brak - pomijamy zeby oszczedzic tokeny)
        $signals = ['liefern', 'lieferung', 'transport', 'zlecenie', 'wycen', 'oferta',
            'quote', 'shipment', 'ladunek', 'zaladu', 'rozladu', 'anbieten',
            'offerte', 'preis', 'stawka', 'palet', 'kg', 'tonn', 'ldm',
            'kundenbestellnummer', 'transportauftrag', 'frachtbrief',
            'from:', 'to:', 'load:', 'unload:', 'pickup', 'delivery',
            'trasa', 'zaladunek', 'rozladunek', 'przewoz'];
        $bodyLc = mb_strtolower($bodyText);
        $matched = 0;
        foreach ($signals as $s) {
            if (strpos($bodyLc, $s) !== false) { $matched++; }
            if ($matched >= 2) break;
        }
        if ($matched < 2) {
            return 0; // za malo sygnalow - to prawdopodobnie nie zapytanie o transport
        }

        // Dedup: sprawdz czy juz mamy quote_request dla tego message_id
        try {
            $existing = $Acts->find()
                ->where([
                    'lead_id' => $lead->id,
                    'activity_type' => 'quote_request',
                    'payload_json LIKE' => '%' . ($data['message_id'] ?: $data['subject']) . '%',
                ])->first();
            if ($existing) {
                $io->out('    quote-extract: already exists for this message');
                return 0;
            }
        } catch (\Throwable $e) {
            // ignoruj
        }

        $io->out('    Quote-extract: GPT analizuje email pod katem listy zlecen...');
        try {
            $svc = new \App\Service\Ai\OpenAiService();
            $system = "Jestes spedytorem analizujacym maile z zapytaniami o transport. "
                . "Wyciagnij ze zrodla WSZYSTKIE zlecenia transportowe (mozna wiele w jednym mailu - np. tabela Excel wklejona w body, "
                . "lista zaladunkow, forwarded WG:/FW:/Weitergeleitete Nachricht). "
                . "Ignoruj podpisy, stopki, zaznaczenia zaufania, boilerplate. "
                . "Zwroc STRICT JSON: {"
                . "\"is_quote_request\": bool (czy email zawiera konkretne zapytanie o wycene/przewoz - nie same 'chetnie ofertuj' bez trasy), "
                . "\"customer_name\": string (kto pyta o wycene - z podpisu/tresci), "
                . "\"customer_contact\": string (osoba kontaktowa), "
                . "\"shipments_count\": int (liczba zlecen wykrytych), "
                . "\"shipments\": [ {"
                . "  \"customer_order_ref\": string (numer zamowienia klienta jesli podany, np. Kundenbestellnummer/PO/BE26002538), "
                . "  \"from_country\": string (2-znak ISO np. DE/PL), "
                . "  \"from_postal\": string, "
                . "  \"from_city\": string, "
                . "  \"from_company\": string, "
                . "  \"to_country\": string, "
                . "  \"to_postal\": string, "
                . "  \"to_city\": string, "
                . "  \"to_company\": string, "
                . "  \"load_date\": string (ISO YYYY-MM-DD jesli mozna wyliczyc), "
                . "  \"load_time\": string (HH:MM lub okno 'HH:MM-HH:MM'), "
                . "  \"unload_date\": string (ISO), "
                . "  \"unload_time\": string, "
                . "  \"weight_kg\": int, "
                . "  \"pallets\": int, "
                . "  \"pallet_type\": string ('EUR'/'IND'/'PET' etc), "
                . "  \"cargo_type\": string ('napoje','szklo','stal','ADR-klasa-X' etc), "
                . "  \"vehicle_type\": string ('plandeka'/'chlodnia'/'mega'/'cysterna'), "
                . "  \"notes\": string (uwagi z tresci - okna czasowe, wymagania sprzetu, referencje) "
                . "} ]"
                . "}"
                . "Puste pola = \"\" lub 0. is_quote_request=false jesli to zwykla korespondencja bez konkretnych zlecen.";

            // Ekstract tresc + email metadane
            $user = "Temat: {$subject}\n\n"
                . "Nadawca: " . ($data['from_name'] ?? '') . " <" . ($data['from_email'] ?? '') . ">\n\n"
                . "Tresc emaila (moze zawierac forwarded/tabele/HTML converted):\n"
                . mb_substr($bodyText, 0, 8000);

            $extracted = $svc->chatJson($system, $user, 2500);
        } catch (\Throwable $e) {
            $io->out('    Quote GPT failed: ' . $e->getMessage());
            return 0;
        }

        if (empty($extracted['is_quote_request']) || empty($extracted['shipments']) || !is_array($extracted['shipments'])) {
            $io->out('    Quote GPT: nie zapytanie o wycene (is_quote_request=false lub brak shipments)');
            return 0;
        }

        // Filter valid shipments (musi miec choc from lub to)
        $valid = [];
        foreach ($extracted['shipments'] as $s) {
            $hasFrom = !empty($s['from_city']) || !empty($s['from_country']);
            $hasTo   = !empty($s['to_city']) || !empty($s['to_country']);
            if ($hasFrom || $hasTo) {
                $valid[] = $s;
            }
        }
        if (empty($valid)) {
            $io->out('    Quote GPT: 0 poprawnych shipments (brak from/to)');
            return 0;
        }

        $count = count($valid);
        $subj  = sprintf('Zapytanie o wycene: %d zlecen (%s)',
            $count,
            $extracted['customer_name'] ?? $lead->company_name);

        // Body summary dla activity - lista skrocona
        $body = "Wykryto {$count} zlecen do wyceny w mailu:\n\n";
        foreach ($valid as $i => $s) {
            if ($i >= 20) { $body .= sprintf("... +%d wiecej\n", $count - $i); break; }
            $from = trim(($s['from_postal'] ?? '') . ' ' . ($s['from_city'] ?? '') . ' ' . ($s['from_country'] ?? ''));
            $to   = trim(($s['to_postal'] ?? '') . ' ' . ($s['to_city'] ?? '') . ' ' . ($s['to_country'] ?? ''));
            $extra = [];
            if (!empty($s['load_date'])) $extra[] = 'zal. ' . $s['load_date'];
            if (!empty($s['weight_kg'])) $extra[] = $s['weight_kg'] . 'kg';
            if (!empty($s['pallets']))   $extra[] = $s['pallets'] . 'x' . ($s['pallet_type'] ?? 'pal');
            if (!empty($s['customer_order_ref'])) $extra[] = 'ref:' . $s['customer_order_ref'];
            $body .= sprintf("%d. %s -> %s%s\n", $i + 1, $from, $to,
                $extra ? ' (' . implode(', ', $extra) . ')' : '');
        }

        // Payload: pelna lista z metadanymi - do widoku szczegolowego + button "Utworz zlecenia"
        $payload = [
            'source' => 'email_gpt_extract',
            'gmail_id' => $data['gmail_id'] ?? null,
            'message_id' => $data['message_id'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'customer_name' => $extracted['customer_name'] ?? '',
            'customer_contact' => $extracted['customer_contact'] ?? '',
            'shipments_count' => $count,
            'shipments' => $valid,
            'extracted_at' => date('Y-m-d H:i:s'),
        ];

        $Acts->logSystem(
            (string)$lead->company_id,
            (string)$lead->id,
            'quote_request',
            $subj,
            $body,
            $payload,
            null
        );

        // Auto-podnies stage z new/contact -> inquiry (zapytanie = etap oferty)
        if (in_array($lead->stage, ['new', 'contact'], true)) {
            $oldStage = $lead->stage;
            $lead->stage = 'inquiry';
            try {
                $Leads->save($lead);
                $Acts->logSystem(
                    (string)$lead->company_id,
                    (string)$lead->id,
                    'stage_change',
                    'Automat: stage inquiry (zapytanie o wycene z mailem)',
                    "Przeniesiono z '{$oldStage}' -> 'inquiry' na podstawie wykrytego zapytania o wycene.",
                    ['auto' => true, 'old' => $oldStage, 'new' => 'inquiry', 'trigger' => 'quote_request_detected'],
                    null
                );
            } catch (\Throwable $e) {
                $io->out('    Auto-stage change failed: ' . $e->getMessage());
            }
        }

        $io->success("    ✓ Quote-request wykryty: {$count} zlecen zalogowano do timeline");
        return 1;
    }
}

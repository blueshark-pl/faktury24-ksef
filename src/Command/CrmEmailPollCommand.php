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
            ->addOption('max', ['default' => 100, 'help' => 'Max wiadomosci per konto na jeden run']);
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        if (!function_exists('imap_open')) {
            $io->error('PHP IMAP extension nie jest zainstalowane. Zainstaluj php-imap.');
            return static::CODE_ERROR;
        }

        $dry = (bool)$args->getOption('dry');
        $accountId = $args->getOption('account');
        $companyFilter = $args->getOption('company');
        $max = (int)$args->getOption('max');

        $EA = TableRegistry::getTableLocator()->get('CrmEmailAccounts');
        $Leads = TableRegistry::getTableLocator()->get('Leads');
        $Acts = TableRegistry::getTableLocator()->get('LeadActivities');

        if ($accountId) {
            $accounts = [$EA->get($accountId)];
        } else {
            $accounts = $EA->findDueForSync($companyFilter);
        }

        $io->out(sprintf('Znaleziono %d kont do sync.', count($accounts)));
        if ($dry) $io->warning('DRY-RUN - nie zapisujemy.');

        $totalMsg = 0;
        $totalAct = 0;
        foreach ($accounts as $acc) {
            $io->out(sprintf('[%s] %s (%s@%s:%d)', $acc->id, $acc->label,
                $acc->username, $acc->imap_host, $acc->imap_port));

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
}

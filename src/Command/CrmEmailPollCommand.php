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

                        // Pobierz krotki fragment body (100 znakow)
                        $body = '';
                        try {
                            $raw = @imap_fetchbody($conn, $uid, 1, FT_UID | FT_PEEK);
                            if ($raw) $body = mb_substr(strip_tags(quoted_printable_decode($raw)), 0, 500);
                        } catch (\Throwable $e) {}

                        $io->out(sprintf('    UID %d: from=%s -> lead %s (%s)',
                            $uid, $fromEmail, $lead->id, $lead->company_name));

                        if (!$dry) {
                            $Acts->logSystem(
                                (string)$acc->company_id, (string)$lead->id, 'email_in',
                                $subject ?: '(bez tematu)',
                                $body,
                                ['imap_uid' => $uid, 'from' => $fromEmail, 'account_id' => $acc->id],
                                null
                            );
                            // Aktualizuj last_contacted_at leada
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
}

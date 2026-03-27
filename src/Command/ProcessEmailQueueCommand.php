<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;
use Cake\Mailer\Mailer;

/**
 * Przetwarza kolejkę wysyłki faktur e-mailem (tabela invoice_email_queue).
 *
 * Uruchomienie: bin/cake process_email_queue
 * Cron (co minutę): * * * * * php /path/to/bin/cake.php process_email_queue
 *
 * Wymaga:
 *  - APP_KSEF_SCHEDULER_KEY w .env (ten sam co dla runPlannedDrafts)
 *  - APP_URL w .env, np. https://faktura.example.com
 */
class ProcessEmailQueueCommand extends Command
{
    private const MAX_ATTEMPTS = 3;
    private const BATCH_SIZE   = 20;

    public static function defaultName(): string
    {
        return 'process_email_queue';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Wysyła faktury z kolejki invoice_email_queue.');
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        /** @var \App\Model\Table\InvoiceEmailQueueTable $Queue */
        $Queue = $this->fetchTable('InvoiceEmailQueue');
        /** @var \Cake\ORM\Table $Invoices */
        $Invoices = $this->fetchTable('Invoices');

        $schedulerKey = (string)(Configure::read('App.ksefSchedulerKey') ?? '');
        $appUrl       = rtrim((string)(getenv('APP_URL') ?: Configure::read('App.fullBaseUrl') ?: ''), '/');

        $pending = $Queue->find()
            ->where([
                'status'          => 'pending',
                'attempts <'      => self::MAX_ATTEMPTS,
                'scheduled_at <=' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ])
            ->orderAsc('scheduled_at')
            ->limit(self::BATCH_SIZE)
            ->all();

        if (!$pending->count()) {
            $io->out('Brak faktur do wysyłki.');
            return self::CODE_SUCCESS;
        }

        $io->out('Przetwarzam ' . $pending->count() . ' wpisów...');

        foreach ($pending as $entry) {
            // Oznacz jako "w trakcie"
            $entry->status   = 'sending';
            $entry->attempts = $entry->attempts + 1;
            $Queue->save($entry);

            // Pobierz podstawowe dane faktury (numer, ksef_number itd.)
            try {
                $invoice = $Invoices->get($entry->invoice_id, [
                    'contain' => ['Companies', 'InvoiceCompanyDetails'],
                ]);
            } catch (\Throwable $e) {
                $this->markFailed($Queue, $entry, 'Faktura nie znaleziona: ' . $e->getMessage());
                $io->error('  [' . $entry->invoice_id . '] Faktura nie znaleziona.');
                continue;
            }

            // Generuj PDF przez wewnętrzny endpoint
            $pdfContent = '';
            if ($schedulerKey !== '' && $appUrl !== '') {
                $pdfUrl = $appUrl . '/invoices/generate-pdf-internal/' . $entry->invoice_id
                    . '?key=' . urlencode($schedulerKey)
                    . '&company_id=' . urlencode($entry->company_id);
                try {
                    $http = new Client(['timeout' => 60]);
                    $resp = $http->get($pdfUrl);
                    if ($resp->getStatusCode() === 200 && str_starts_with($resp->getHeaderLine('Content-Type'), 'application/pdf')) {
                        $pdfContent = (string)$resp->getBody();
                    } else {
                        $errBody = (string)$resp->getBody();
                        $this->markFailed($Queue, $entry, 'PDF endpoint error ' . $resp->getStatusCode() . ': ' . mb_substr($errBody, 0, 200));
                        $io->error('  [' . ($invoice->fullnumber ?? $entry->invoice_id) . '] Błąd PDF endpoint: ' . $resp->getStatusCode());
                        continue;
                    }
                } catch (\Throwable $e) {
                    $this->markFailed($Queue, $entry, 'PDF HTTP error: ' . $e->getMessage());
                    $io->error('  [' . ($invoice->fullnumber ?? $entry->invoice_id) . '] Błąd HTTP PDF: ' . $e->getMessage());
                    continue;
                }
            } else {
                $this->markFailed($Queue, $entry, 'Brak APP_KSEF_SCHEDULER_KEY lub APP_URL w konfiguracji.');
                $io->error('  Brak konfiguracji APP_KSEF_SCHEDULER_KEY / APP_URL — pomijam.');
                continue;
            }

            // Wyślij e-mail
            $fullnumber = (string)($invoice->fullnumber ?: $invoice->id);
            $filename   = 'faktura_' . preg_replace('/[\/\\\\:*?"<>|]/', '_', $fullnumber) . '.pdf';
            $sellerName = trim((string)($invoice->invoice_company_detail?->name ?? $invoice->company?->name ?? ''));
            $subject    = 'Faktura ' . $fullnumber . ($sellerName !== '' ? ' od ' . $sellerName : '');

            try {
                $mailer = new Mailer('default');
                $mailer->setTo($entry->email);
                $mailer->setSubject($subject);
                $mailer->setEmailFormat('html');
                $mailer->setAttachments([
                    $filename => ['data' => $pdfContent, 'mimetype' => 'application/pdf'],
                ]);
                $mailer->viewBuilder()->setLayout('default')->setTemplate('invoice_email');
                $mailer->setViewVars([
                    'invoice'    => $invoice,
                    'fullnumber' => $fullnumber,
                    'sellerName' => $sellerName,
                ]);
                $mailer->deliver();

                $entry->status  = 'sent';
                $entry->sent_at = new \DateTimeImmutable();
                $Queue->save($entry);

                $io->out('  [OK] ' . $fullnumber . ' → ' . $entry->email);
                Log::info('[ProcessEmailQueue] sent invoice=' . $entry->invoice_id . ' to=' . $entry->email);
            } catch (\Throwable $e) {
                $this->markFailed($Queue, $entry, $e->getMessage());
                $io->error('  [BŁĄD] ' . $fullnumber . ': ' . $e->getMessage());
            }
        }

        $io->out('Gotowe.');
        return self::CODE_SUCCESS;
    }

    private function markFailed(object $Queue, object $entry, string $error): void
    {
        $entry->status     = $entry->attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
        $entry->last_error = mb_substr($error, 0, 1000);
        $Queue->save($entry);
        Log::error('[ProcessEmailQueue] failed entry=' . $entry->id . ': ' . $error);
    }
}

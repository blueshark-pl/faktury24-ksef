<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\E100Account;
use App\Service\E100ApiService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;

/**
 * Synchronizacja transakcji z E100 API.
 *
 * Użycie:
 *   bin/cake sync_e100
 *   bin/cake sync_e100 --account-id=<uuid>
 *   bin/cake sync_e100 --date-from=2025-01-01 --date-to=2025-01-31
 */
class SyncE100Command extends Command
{
    private E100ApiService $e100;

    public function initialize(): void
    {
        parent::initialize();
        $this->e100 = new E100ApiService();
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Synchronizuje transakcje z API E100 dla aktywnych kont.');
        $parser->addOption('account-id', [
            'help'    => 'UUID konta E100 do synchronizacji (opcjonalnie; domyślnie: wszystkie aktywne)',
            'default' => null,
        ]);
        $parser->addOption('date-from', [
            'help'    => 'Data od (YYYY-MM-DD)',
            'default' => null,
        ]);
        $parser->addOption('date-to', [
            'help'    => 'Data do (YYYY-MM-DD)',
            'default' => null,
        ]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $accountId = $args->getOption('account-id');
        $dateFrom  = $args->getOption('date-from');
        $dateTo    = $args->getOption('date-to');

        /** @var \App\Model\Table\E100AccountsTable $E100Accounts */
        $E100Accounts = TableRegistry::getTableLocator()->get('E100Accounts');

        $query = $E100Accounts->find()
            ->where(['E100Accounts.is_active' => true]);

        if ($accountId) {
            $query->where(['E100Accounts.id' => $accountId]);
        }

        $accounts = $query->all();

        if ($accounts->count() === 0) {
            $io->warning('Brak aktywnych kont E100 do synchronizacji.');
            return self::CODE_SUCCESS;
        }

        $totalImported = 0;
        $totalSkipped  = 0;
        $errors        = [];

        foreach ($accounts as $account) {
            /** @var E100Account $account */
            $io->out(sprintf(
                '[%s] Synchronizuję konto: %s (%s)',
                date('H:i:s'),
                $account->label ?: $account->username,
                $account->id
            ));

            try {
                [$imported, $skipped] = $this->syncAccount($account, $dateFrom, $dateTo, $io);
                $totalImported += $imported;
                $totalSkipped  += $skipped;
                $io->out(sprintf('  → Zaimportowano: %d, pominięto: %d', $imported, $skipped));
            } catch (\Throwable $e) {
                $msg = sprintf('  ✗ Błąd konta %s: %s', $account->id, $e->getMessage());
                $io->error($msg);
                $errors[] = $msg;
            }
        }

        $io->out('');
        $io->success(sprintf(
            'Gotowe. Łącznie zaimportowano: %d, pominięto: %d, błędów: %d',
            $totalImported,
            $totalSkipped,
            count($errors)
        ));

        return self::CODE_SUCCESS;
    }

    /**
     * Synchronizuje jedno konto. Zwraca [imported, skipped].
     *
     * @return array{int, int}
     */
    private function syncAccount(E100Account $account, ?string $dateFrom, ?string $dateTo, ConsoleIo $io): array
    {
        /** @var \App\Model\Table\E100TransactionsTable $E100Transactions */
        $E100Transactions = TableRegistry::getTableLocator()->get('E100Transactions');

        $filters = [];
        if ($dateFrom) {
            $filters['dateFrom'] = $dateFrom;
        }
        if ($dateTo) {
            $filters['dateTo'] = $dateTo;
        }

        $imported = 0;
        $skipped  = 0;
        $page     = 1;

        do {
            $filters['page']     = $page;
            $filters['pageSize'] = 2000;

            $result = $this->e100->getTransactions($account, $filters);

            $items     = $result['transactions'] ?? $result['items'] ?? $result['data'] ?? [];
            $pageCount = (int)($result['pageCount'] ?? $result['page_count'] ?? 1);

            foreach ($items as $row) {
                $unId = $row['unId'] ?? $row['un_id'] ?? null;
                if (!$unId) {
                    continue;
                }

                $exists = $E100Transactions->exists([
                    'un_id'      => (string)$unId,
                    'company_id' => $account->company_id,
                ]);

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $tx = $E100Transactions->newEntity([
                    'id'                       => Text::uuid(),
                    'company_id'               => $account->company_id,
                    'e100_account_id'           => $account->id,
                    'un_id'                    => (string)($row['unId'] ?? ''),
                    'card'                     => $row['card'] ?? null,
                    'card_shortname'           => $row['cardShortname'] ?? $row['card_shortname'] ?? null,
                    'auto'                     => $row['auto'] ?? null,
                    'date'                     => isset($row['date']) ? new DateTime($row['date']) : null,
                    'datetime_insert'          => isset($row['datetimeInsert']) ? new DateTime($row['datetimeInsert']) : null,
                    'station_id'               => $row['stationId'] ?? $row['station_id'] ?? null,
                    'address'                  => $row['address'] ?? null,
                    'brand'                    => $row['brand'] ?? null,
                    'service_id'               => $row['serviceId'] ?? $row['service_id'] ?? null,
                    'service_name'             => $row['serviceName'] ?? $row['service_name'] ?? null,
                    'volume'                   => isset($row['volume']) ? (float)$row['volume'] : null,
                    'price'                    => isset($row['price']) ? (float)$row['price'] : null,
                    'currency'                 => $row['currency'] ?? null,
                    'sum'                      => isset($row['sum']) ? (float)$row['sum'] : null,
                    'discount'                 => isset($row['discount']) ? (float)$row['discount'] : null,
                    'discount_percentage'      => isset($row['discountPercentage']) ? (float)$row['discountPercentage'] : null,
                    'amount_without_discount'  => isset($row['amountWithoutDiscount']) ? (float)$row['amountWithoutDiscount'] : null,
                    'excise'                   => isset($row['excise']) ? (float)$row['excise'] : null,
                    'ticket'                   => $row['ticket'] ?? null,
                    'confirmed'                => (bool)($row['confirmed'] ?? false),
                    'exposed'                  => (bool)($row['exposed'] ?? false),
                    'invoice_ref'              => $row['invoiceRef'] ?? $row['invoice_ref'] ?? null,
                    'invoice_date'             => isset($row['invoiceDate']) ? new DateTime($row['invoiceDate']) : null,
                    'driver'                   => $row['driver'] ?? null,
                    'card_driver'              => $row['cardDriver'] ?? $row['card_driver'] ?? null,
                    'category'                 => $row['category'] ?? null,
                    'row_version'              => $row['rowVersion'] ?? $row['row_version'] ?? null,
                ]);

                if ($E100Transactions->save($tx)) {
                    $imported++;
                } else {
                    $io->warning(sprintf('    Nie można zapisać transakcji un_id=%s', $unId));
                }
            }

            $page++;
        } while ($page <= $pageCount);

        // Zaktualizuj last_sync_at
        /** @var \App\Model\Table\E100AccountsTable $E100Accounts */
        $E100Accounts = TableRegistry::getTableLocator()->get('E100Accounts');
        $patch = $E100Accounts->get($account->id);
        $patch->last_sync_at = new DateTime();
        $E100Accounts->save($patch);

        return [$imported, $skipped];
    }
}

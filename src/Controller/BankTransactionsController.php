<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\BankMatchingService;
use App\Service\Mt940ParserService;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Import wyciągów bankowych MT940 i przeglądanie transakcji.
 *
 * Akcje:
 *  - index        : lista importów z podsumowaniem
 *  - transactions : historia wszystkich transakcji (cross-import)
 *  - view         : transakcje z jednego importu
 *  - import       : formularz + obsługa uploadu pliku MT940
 *  - delete       : usunięcie importu (z kaskadą transakcji)
 */
class BankTransactionsController extends AppController
{
    // -------------------------------------------------------------------------
    // Lista importów
    // -------------------------------------------------------------------------

    public function index(): void
    {
        $this->request->allowMethod(['get']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $BankStatementImports = $this->fetchTable('BankStatementImports');

        $imports = $BankStatementImports->find()
            ->where(['company_id' => $companyId])
            ->orderByDesc('created')
            ->all();

        // Statystyki dopasowania
        $BankTransactions = $this->fetchTable('BankTransactions');
        $stats = [
            'proposed'  => $BankTransactions->find()->where(['company_id' => $companyId, 'match_status' => 'proposed'])->count(),
            'unmatched' => $BankTransactions->find()->where(['company_id' => $companyId, 'match_status' => 'unmatched'])->count(),
            'matched'   => $BankTransactions->find()->where(['company_id' => $companyId, 'match_status' => 'matched'])->count(),
            'total'     => $BankTransactions->find()->where(['company_id' => $companyId])->count(),
        ];

        $this->set(compact('imports', 'stats'));
        $this->set('title', 'Wyciągi bankowe MT940');
    }

    // -------------------------------------------------------------------------
    // Historia wszystkich transakcji (cross-import)
    // -------------------------------------------------------------------------

    public function transactions(): void
    {
        $this->request->allowMethod(['get']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $BankTransactions = $this->fetchTable('BankTransactions');

        $search      = trim((string)$this->request->getQuery('q', ''));
        $direction   = $this->request->getQuery('dir', '');
        $matchStatus = $this->request->getQuery('status', '');
        $dateFrom    = $this->request->getQuery('date_from', '');
        $dateTo      = $this->request->getQuery('date_to', '');
        $page        = max(1, (int)$this->request->getQuery('page', 1));
        $limit       = (int)$this->request->getQuery('limit', 50);
        if (!in_array($limit, [25, 50, 100, 200], true)) {
            $limit = 50;
        }

        $query = $BankTransactions->find()
            ->contain([
                'BankStatementImports' => ['fields' => ['id', 'filename', 'account_number']],
                'Invoices'             => ['fields' => ['id', 'fullnumber', 'total', 'paymentstate']],
            ])
            ->where(['BankTransactions.company_id' => $companyId])
            ->orderByDesc('BankTransactions.value_date')
            ->orderByDesc('BankTransactions.created');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(['OR' => [
                'BankTransactions.party_name LIKE'         => $like,
                'BankTransactions.title LIKE'              => $like,
                'BankTransactions.bank_reference LIKE'     => $like,
                'BankTransactions.customer_reference LIKE' => $like,
            ]]);
        }

        if ($direction === 'C' || $direction === 'D') {
            $query->where(['BankTransactions.direction' => $direction]);
        }

        if (in_array($matchStatus, ['unmatched', 'proposed', 'matched', 'ignored'], true)) {
            $query->where(['BankTransactions.match_status' => $matchStatus]);
        }

        if ($dateFrom !== '') {
            $query->where(['BankTransactions.value_date >=' => $dateFrom]);
        }

        if ($dateTo !== '') {
            $query->where(['BankTransactions.value_date <=' => $dateTo]);
        }

        $total        = (clone $query)->count();
        $transactions = $query->limit($limit)->offset(($page - 1) * $limit)->all();
        $pages        = (int)ceil($total / $limit);

        // Liczniki po statusie (dla filtrów)
        $countQuery = $BankTransactions->find();
        $rows = $countQuery
            ->where(['company_id' => $companyId])
            ->select(['match_status', 'cnt' => $countQuery->func()->count('*')])
            ->group('match_status')
            ->disableHydration()
            ->all()
            ->toArray();
        $statusCounts = [];
        foreach ($rows as $row) {
            $statusCounts[(string)($row['match_status'] ?? 'unmatched')] = (int)$row['cnt'];
        }

        $this->set(compact('transactions', 'search', 'direction', 'matchStatus', 'dateFrom', 'dateTo', 'page', 'pages', 'total', 'limit', 'statusCounts'));
        $this->set('title', 'Historia transakcji');
    }

    // -------------------------------------------------------------------------
    // Szczegóły importu — lista transakcji
    // -------------------------------------------------------------------------

    public function view(string $id): void
    {
        $this->request->allowMethod(['get']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $BankStatementImports = $this->fetchTable('BankStatementImports');
        $import = $BankStatementImports->get($id, [
            'conditions' => ['company_id' => $companyId],
        ]);

        $BankTransactions = $this->fetchTable('BankTransactions');

        $search    = trim((string)$this->request->getQuery('q', ''));
        $direction = $this->request->getQuery('dir', '');
        $page      = max(1, (int)$this->request->getQuery('page', 1));
        $limit     = 100;

        $query = $BankTransactions->find()
            ->where(['import_id' => $id])
            ->orderByAsc('value_date')
            ->orderByAsc('created');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(['OR' => [
                'party_name LIKE'  => $like,
                'title LIKE'       => $like,
                'bank_reference LIKE' => $like,
                'customer_reference LIKE' => $like,
            ]]);
        }

        if ($direction === 'C' || $direction === 'D') {
            $query->where(['direction' => $direction]);
        }

        $total        = (clone $query)->count();
        $transactions = $query->limit($limit)->offset(($page - 1) * $limit)->all();
        $pages        = (int)ceil($total / $limit);

        $this->set(compact('import', 'transactions', 'search', 'direction', 'page', 'pages', 'total', 'limit'));
        $this->set('title', 'Import: ' . ($import->filename ?? $id));
    }

    // -------------------------------------------------------------------------
    // Import pliku MT940
    // -------------------------------------------------------------------------

    public function import(): ?Response
    {
        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        if ($this->request->is('post')) {
            $file = $this->request->getUploadedFile('mt940_file');

            if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
                $this->Flash->error('Nie wybrano pliku lub wystąpił błąd uploadu.');
                return null;
            }

            $originalName = $file->getClientFilename() ?? 'wyciag.sta';
            $content      = (string)$file->getStream();

            if (trim($content) === '') {
                $this->Flash->error('Plik jest pusty.');
                return null;
            }

            // Parsuj
            $parser = new Mt940ParserService();
            $result = $parser->parse($content);

            $statement    = $result['statement'];
            $transactions = $result['transactions'];

            if (empty($transactions)) {
                $this->Flash->warning('Plik nie zawiera żadnych transakcji (lub nie jest poprawnym MT940).');
                if (!empty($result['errors'])) {
                    $this->Flash->warning('Szczegóły: ' . implode('; ', array_slice($result['errors'], 0, 5)));
                }
                return null;
            }

            $BankStatementImports = $this->fetchTable('BankStatementImports');
            $BankTransactions     = $this->fetchTable('BankTransactions');

            // Daty graniczne wyciągu
            $dates = array_filter(array_column($transactions, 'value_date'));
            sort($dates);
            $dateFrom = $dates[0] ?? null;
            $dateTo   = end($dates) ?: null;

            // Utwórz rekord importu
            $import = $BankStatementImports->newEntity([
                'id'                => Text::uuid(),
                'company_id'        => $companyId,
                'filename'          => $originalName,
                'account_number'    => $statement['account_number'] ?? null,
                'currency'          => $statement['currency'] ?? 'PLN',
                'statement_from'    => $dateFrom,
                'statement_to'      => $dateTo,
                'opening_balance'   => $statement['opening_balance'] ?? null,
                'closing_balance'   => $statement['closing_balance'] ?? null,
                'transaction_count' => count($transactions),
                'new_count'         => 0,
                'duplicate_count'   => 0,
                'imported_by'       => $this->request->getAttribute('identity')?->getIdentifier(),
            ]);

            if (!$BankStatementImports->save($import)) {
                $this->Flash->error('Nie udało się zapisać nagłówka importu.');
                return null;
            }

            // Zapisz transakcje (z deduplikacją po import_hash)
            $newCount  = 0;
            $dupCount  = 0;
            $errCount  = 0;

            foreach ($transactions as $txData) {
                $hash = $txData['import_hash'];

                if ($BankTransactions->existsByHash($hash)) {
                    $dupCount++;
                    continue;
                }

                $tx = $BankTransactions->newEntity(array_merge($txData, [
                    'id'         => Text::uuid(),
                    'company_id' => $companyId,
                    'import_id'  => $import->id,
                ]));

                if ($BankTransactions->save($tx)) {
                    $newCount++;

                    // Auto-dopasowanie do faktury
                    $matchResult = (new BankMatchingService())->match($txData, $companyId);
                    $tx->match_status     = $matchResult['match_status'];
                    $tx->match_confidence = $matchResult['match_confidence'];
                    $tx->match_reason     = $matchResult['match_reason'];
                    $tx->parsed_inv       = $matchResult['parsed_inv'];
                    $tx->parsed_nip       = $matchResult['parsed_nip'];
                    $tx->parsed_vat       = $matchResult['parsed_vat'];
                    $tx->tx_type_code     = $matchResult['tx_type_code'];
                    if ($matchResult['invoice_id'] !== null) {
                        $tx->invoice_id = $matchResult['invoice_id'];
                        $tx->is_matched = $matchResult['match_status'] === 'matched';
                    }
                    $BankTransactions->save($tx);

                    // Jeśli pewność ≥ 90 — automatycznie potwierdź płatność
                    if ($matchResult['match_status'] === 'matched' && $matchResult['invoice_id'] !== null) {
                        (new BankMatchingService())->confirmMatch((string)$tx->id, $matchResult['invoice_id'], $companyId);
                    }
                } else {
                    $errCount++;
                }
            }

            // Zaktualizuj liczniki w imporcie
            $import->new_count       = $newCount;
            $import->duplicate_count = $dupCount;
            $BankStatementImports->save($import);

            // Komunikat
            if ($newCount > 0) {
                $this->Flash->success(sprintf(
                    'Import zakończony. Nowych transakcji: %d. Duplikatów (pominiętych): %d.%s',
                    $newCount,
                    $dupCount,
                    $errCount > 0 ? " Błędów zapisu: $errCount." : ''
                ));
            } else {
                $this->Flash->warning(sprintf(
                    'Wszystkie transakcje (%d) zostały już wcześniej zaimportowane (duplikaty).',
                    $dupCount
                ));
            }

            return $this->redirect(['action' => 'view', $import->id]);
        }

        $this->set('title', 'Import wyciągu MT940');
        return null;
    }

    // -------------------------------------------------------------------------
    // Potwierdź dopasowanie transakcji → faktura
    // -------------------------------------------------------------------------

    public function confirmMatch(string $id): Response
    {
        $this->request->allowMethod(['post']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $BankTransactions = $this->fetchTable('BankTransactions');
        $tx = $BankTransactions->find()
            ->where(['id' => $id, 'company_id' => $companyId])
            ->first();

        if ($tx === null) {
            $this->Flash->error('Transakcja nie istnieje.');
            return $this->redirect(['action' => 'transactions']);
        }

        $invoiceId = $this->request->getData('invoice_id') ?? $tx->invoice_id;

        if (empty($invoiceId)) {
            $this->Flash->error('Brak faktury do potwierdzenia.');
            return $this->redirect(['action' => 'transactions']);
        }

        $ok = (new BankMatchingService())->confirmMatch($id, (string)$invoiceId, $companyId);

        if ($ok) {
            $this->Flash->success('Dopasowanie potwierdzone — faktura oznaczona jako opłacona.');
        } else {
            $this->Flash->error('Nie udało się potwierdzić dopasowania.');
        }

        $redirect = $this->request->getData('redirect') ?? $this->referer(['action' => 'transactions']);
        return $this->redirect($redirect);
    }

    // -------------------------------------------------------------------------
    // Ignoruj transakcję (wyklucz z dopasowywania)
    // -------------------------------------------------------------------------

    public function ignoreTransaction(string $id): Response
    {
        $this->request->allowMethod(['post']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $BankTransactions = $this->fetchTable('BankTransactions');
        $tx = $BankTransactions->find()
            ->where(['id' => $id, 'company_id' => $companyId])
            ->first();

        if ($tx !== null) {
            $tx->match_status = 'ignored';
            $tx->invoice_id   = null;
            $tx->is_matched   = false;
            $BankTransactions->save($tx);
            $this->Flash->success('Transakcja oznaczona jako ignorowana.');
        } else {
            $this->Flash->error('Transakcja nie istnieje.');
        }

        $redirect = $this->request->getData('redirect') ?? $this->referer(['action' => 'transactions']);
        return $this->redirect($redirect);
    }

    // -------------------------------------------------------------------------
    // Usunięcie importu
    // -------------------------------------------------------------------------

    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post', 'delete']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $BankStatementImports = $this->fetchTable('BankStatementImports');
        $import = $BankStatementImports->get($id, [
            'conditions' => ['company_id' => $companyId],
        ]);

        if ($BankStatementImports->delete($import)) {
            $this->Flash->success('Import i powiązane transakcje zostały usunięte.');
        } else {
            $this->Flash->error('Nie udało się usunąć importu.');
        }

        return $this->redirect(['action' => 'index']);
    }
}

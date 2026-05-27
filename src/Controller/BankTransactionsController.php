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

        // Zbiorcze alokacje dla widocznych transakcji (jeden SQL)
        $txAllocMap = [];
        $txIds = array_map(fn($t) => (string)$t->id, $transactions->toArray());
        if (!empty($txIds)) {
            $ph   = implode(',', array_fill(0, count($txIds), '?'));
            $db   = $BankTransactions->getConnection();
            $stmt = $db->execute(
                "SELECT bta.bank_transaction_id,
                        COUNT(*) AS alloc_count,
                        SUM(bta.allocated_amount) AS total_allocated,
                        GROUP_CONCAT(DISTINCT COALESCE(i.fullnumber, li.fullnumber)
                                     ORDER BY bta.created SEPARATOR '||') AS fullnumbers
                 FROM bank_transaction_allocations bta
                 LEFT JOIN invoices i      ON i.id  = bta.invoice_id
                 LEFT JOIN legacy_invoices li ON li.id = bta.legacy_invoice_id
                 WHERE bta.bank_transaction_id IN ({$ph}) AND bta.company_id = ?
                 GROUP BY bta.bank_transaction_id",
                array_merge($txIds, [$companyId])
            );
            foreach ($stmt->fetchAll('assoc') as $row) {
                $txAllocMap[(string)$row['bank_transaction_id']] = [
                    'count'     => (int)$row['alloc_count'],
                    'allocated' => round((float)$row['total_allocated'], 2),
                    'invoices'  => array_values(array_filter(explode('||', (string)($row['fullnumbers'] ?? '')))),
                ];
            }
        }

        // ── Auto-podpowiedzi: dla każdej kredytowej tx bez alokacji szukamy
        //    pasujących nierozliczonych faktur (system + legacy) bez czekania na klik.
        $candidatesByTx = $this->_buildCandidatesByTx($transactions, $txAllocMap, $companyId);

        $this->set(compact('transactions', 'txAllocMap', 'candidatesByTx',
            'search', 'direction', 'matchStatus', 'dateFrom', 'dateTo',
            'page', 'pages', 'total', 'limit', 'statusCounts'));
        $this->set('title', 'Historia transakcji');
    }

    /**
     * Dla każdej tx kredytowej (C) bez pełnej alokacji znajduje pasujące
     * nierozliczone faktury (system + legacy) po:
     *   - parsed_inv / title vs fullnumber (z wariantami formatu)
     *   - party_name vs contractor_name (znaczące słowa)
     *   - parsed_nip vs contractor.nip
     *   - kwocie (matching remaining ±10%)
     * Zwraca array [txId => array of candidate invoices]
     */
    private function _buildCandidatesByTx(iterable $transactions, array $txAllocMap, string $companyId): array
    {
        $candidatesByTx = [];

        // Lista tx do przeszukania (tylko credit, bez alokacji)
        $relevantTxs = [];
        foreach ($transactions as $tx) {
            if (($tx->direction ?? '') !== 'C') continue;
            if (!empty($txAllocMap[(string)$tx->id]['count'])) continue;
            if (in_array($tx->match_status, ['matched', 'ignored'], true)) continue;
            $relevantTxs[(string)$tx->id] = $tx;
        }
        if (empty($relevantTxs)) return $candidatesByTx;

        // ── Prefetch nierozliczone faktury systemowe ────────────────────────
        $Invoices = $this->fetchTable('Invoices');
        $sysInvoices = $Invoices->find()
            ->contain([
                'InvoiceContractors' => function ($q) {
                    return $q->select(['id', 'invoice_id', 'name', 'nip']);
                },
            ])
            ->where(['Invoices.company_id' => $companyId])
            // Workflow: NULL lub != 'draft' (jak w ReconciliationsController::index)
            ->where(['OR' => [
                ['Invoices.workflow_status IS' => null],
                ['Invoices.workflow_status !=' => 'draft'],
            ]])
            // Paymentstate: NULL/unpaid/partial
            ->where(['OR' => [
                ['Invoices.paymentstate IS' => null],
                ['Invoices.paymentstate IN' => ['unpaid', 'partial']],
            ]])
            ->select(['Invoices.id', 'Invoices.fullnumber', 'Invoices.total', 'Invoices.netto',
                      'Invoices.remaining', 'Invoices.alreadypaid', 'Invoices.currency',
                      'Invoices.currency_exchange', 'Invoices.paymentstate',
                      'Invoices.paymentdate', 'Invoices.date'])
            ->orderByDesc('Invoices.date')
            ->limit(500)
            ->all()->toArray();

        // ── Prefetch nierozliczone faktury legacy ────────────────────────────
        $legacyInvoices = $this->fetchTable('LegacyInvoices')->find()
            ->where(['company_id' => $companyId])
            ->where(['OR' => [
                ['paymentstate IS' => null],
                ['paymentstate IN' => ['unpaid', 'partial']],
            ]])
            ->select(['id', 'fullnumber', 'contractor_name', 'contractor_nip',
                      'total', 'netto', 'remaining', 'remaining_wal', 'currency',
                      'exchange_rate', 'paymentstate', 'date'])
            ->orderByDesc('date')
            ->limit(500)
            ->all()->toArray();

        // Generator wariantów numeru faktury (FW/17/04/2026 → FW17/4/26, itp.)
        $fnVariants = static function (string $fn): array {
            if ($fn === '') return [];
            $v = [$fn];
            $v[] = str_replace('/', '', $fn);
            $sy = preg_replace('/(\d{2})(\d{2})$/', '$2', $fn);
            if ($sy && $sy !== $fn) { $v[] = $sy; $v[] = str_replace('/', '', $sy); }
            $nl = preg_replace('#/0(\d)(?=/|$)#', '/$1', $fn);
            if ($nl && $nl !== $fn) {
                $v[] = $nl; $v[] = str_replace('/', '', $nl);
                $sy2 = preg_replace('/(\d{2})(\d{2})$/', '$2', $nl);
                if ($sy2 && $sy2 !== $nl) { $v[] = $sy2; $v[] = str_replace('/', '', $sy2); }
            }
            return array_unique($v);
        };

        $stopWords = ['sp', 'zoo', 'oo', 'spzoo', 'sa', 'gmbh', 'ltd',
                      'spolka', 'firma', 'group', 'grupa', 'holding'];

        // ── Per-tx matching ──────────────────────────────────────────────────
        foreach ($relevantTxs as $txId => $tx) {
            $txAmount   = (float)$tx->amount;
            $txCurrency = strtoupper((string)($tx->currency ?? 'PLN'));
            $txTitle    = strtolower((string)($tx->title ?? ''));
            $txParty    = mb_strtolower((string)($tx->party_name ?? ''));
            $parsedInv  = (string)($tx->parsed_inv ?? '');
            $parsedNip  = (string)($tx->parsed_nip ?? '');

            $matches = [];

            // System invoices
            foreach ($sysInvoices as $inv) {
                $score    = 0;
                $reasons  = [];
                $fn       = (string)$inv->fullnumber;
                $variants = $fnVariants($fn);

                // Numer faktury (najmocniejszy)
                $invMatched = false;
                foreach ($variants as $v) {
                    if ($parsedInv !== '' && strcasecmp($parsedInv, $v) === 0) {
                        $score += 45; $reasons[] = '🎯 nr w /INV/'; $invMatched = true; break;
                    }
                }
                if (!$invMatched) {
                    foreach ($variants as $v) {
                        if ($txTitle !== '' && stripos($txTitle, strtolower($v)) !== false) {
                            $score += 35; $reasons[] = '🎯 nr w tytule'; break;
                        }
                    }
                }

                // NIP
                $contractorNip = (string)($inv->invoice_contractor->nip ?? '');
                if ($parsedNip !== '' && $contractorNip !== '' && $parsedNip === $contractorNip) {
                    $score += 25; $reasons[] = '🪪 NIP';
                }

                // Nazwa kontrahenta
                $contractorName = (string)($inv->invoice_contractor->name ?? '');
                if ($contractorName !== '' && $txParty !== '') {
                    $words = preg_split('/[\s\.,\-\/()]+/u', mb_strtolower($contractorName));
                    $sig   = array_filter($words, fn($w) => mb_strlen($w) >= 3 && !in_array($w, $stopWords, true));
                    $hits  = 0;
                    foreach (array_slice($sig, 0, 5) as $w) {
                        if (mb_strpos($txParty, $w) !== false) $hits++;
                    }
                    if ($hits > 0) { $score += min($hits * 5, 20); $reasons[] = '👤 nazwa ×' . $hits; }
                }

                // Kwota (uwzględnia waluty — konwertujemy tx do invoice currency)
                $invRemaining = (float)$inv->remaining;
                $invTotal     = (float)$inv->total;
                $invCurr      = strtoupper((string)($inv->currency ?? 'PLN'));
                $invRate      = (float)($inv->currency_exchange ?? 0);
                $txInInvCurr  = $txAmount;
                if ($txCurrency !== $invCurr && $invRate > 0) {
                    if ($txCurrency === 'PLN' && $invCurr !== 'PLN')      $txInInvCurr = $txAmount / $invRate;
                    elseif ($invCurr === 'PLN' && $txCurrency !== 'PLN') $txInInvCurr = $txAmount * $invRate;
                }
                $target = $invRemaining > 0.01 ? $invRemaining : $invTotal;
                if ($target > 0) {
                    $diff = abs($txInInvCurr - $target) / $target;
                    if ($diff < 0.001)     { $score += 35; $reasons[] = '💰 dokładna'; }
                    elseif ($diff <= 0.05) { $score += 30; $reasons[] = '💰 ±5%'; }
                    elseif ($diff <= 0.10) { $score += 20; $reasons[] = '💰 ±10%'; }
                }

                if ($score >= 15) {
                    $matches[] = [
                        'id'           => (string)$inv->id,
                        'source'       => 'system',
                        'fullnumber'   => $fn,
                        'contractor'   => $contractorName,
                        'nip'          => $contractorNip,
                        'total'        => $invTotal,
                        'remaining'    => $invRemaining,
                        'currency'     => $invCurr,
                        'paymentstate' => (string)($inv->paymentstate ?? 'unpaid'),
                        'date'         => $inv->date instanceof \DateTimeInterface ? $inv->date->format('Y-m-d') : substr((string)$inv->date, 0, 10),
                        'score'        => $score,
                        'reasons'      => $reasons,
                    ];
                }
            }

            // Legacy invoices (uproszczona logika)
            foreach ($legacyInvoices as $inv) {
                $score    = 0;
                $reasons  = [];
                $fn       = (string)$inv->fullnumber;
                $variants = $fnVariants($fn);

                $invMatched = false;
                foreach ($variants as $v) {
                    if ($parsedInv !== '' && strcasecmp($parsedInv, $v) === 0) {
                        $score += 45; $reasons[] = '🎯 nr w /INV/'; $invMatched = true; break;
                    }
                }
                if (!$invMatched) {
                    foreach ($variants as $v) {
                        if ($txTitle !== '' && stripos($txTitle, strtolower($v)) !== false) {
                            $score += 35; $reasons[] = '🎯 nr w tytule'; break;
                        }
                    }
                }

                $contractorNip = (string)($inv->contractor_nip ?? '');
                if ($parsedNip !== '' && $contractorNip !== '' && $parsedNip === $contractorNip) {
                    $score += 25; $reasons[] = '🪪 NIP';
                }

                $contractorName = (string)($inv->contractor_name ?? '');
                if ($contractorName !== '' && $txParty !== '') {
                    $words = preg_split('/[\s\.,\-\/()]+/u', mb_strtolower($contractorName));
                    $sig   = array_filter($words, fn($w) => mb_strlen($w) >= 3 && !in_array($w, $stopWords, true));
                    $hits  = 0;
                    foreach (array_slice($sig, 0, 5) as $w) {
                        if (mb_strpos($txParty, $w) !== false) $hits++;
                    }
                    if ($hits > 0) { $score += min($hits * 5, 20); $reasons[] = '👤 nazwa ×' . $hits; }
                }

                // Kwota — uproszczona dla legacy
                $invCurr   = strtoupper((string)($inv->currency ?? 'PLN'));
                $invTotal  = (float)$inv->total;
                $invRemPln = (float)$inv->remaining;
                $invRemWal = (float)($inv->remaining_wal ?? 0);
                $target    = ($invCurr !== 'PLN' && $invRemWal > 0.01) ? $invRemWal : $invRemPln;
                if ($target > 0) {
                    $diff = abs($txAmount - $target) / $target;
                    if ($diff < 0.001)     { $score += 35; $reasons[] = '💰 dokładna'; }
                    elseif ($diff <= 0.05) { $score += 30; $reasons[] = '💰 ±5%'; }
                    elseif ($diff <= 0.10) { $score += 20; $reasons[] = '💰 ±10%'; }
                }

                if ($score >= 15) {
                    $matches[] = [
                        'id'           => (string)$inv->id,
                        'source'       => 'legacy',
                        'fullnumber'   => $fn,
                        'contractor'   => $contractorName,
                        'nip'          => $contractorNip,
                        'total'        => $invTotal,
                        'remaining'    => $target,
                        'currency'     => $invCurr,
                        'paymentstate' => (string)($inv->paymentstate ?? 'unpaid'),
                        'date'         => $inv->date instanceof \DateTimeInterface ? $inv->date->format('Y-m-d') : substr((string)$inv->date, 0, 10),
                        'score'        => $score,
                        'reasons'      => $reasons,
                    ];
                }
            }

            // Sort by score DESC + top 6
            usort($matches, fn($a, $b) => $b['score'] - $a['score']);
            $candidatesByTx[$txId] = array_slice($matches, 0, 6);
        }

        return $candidatesByTx;
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

        // AJAX: zwracaj JSON żeby modal mógł odświeżyć się w miejscu bez full page reload
        if ($this->request->is('ajax') || $this->request->is('json')
            || $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            $this->disableAutoRender();
            // Pobierz świeże dane faktury dla update'u listy
            $invoiceData = null;
            if ($ok) {
                $inv = $this->fetchTable('Invoices')->find()
                    ->select(['id', 'paymentstate', 'alreadypaid', 'remaining', 'currency'])
                    ->where(['id' => $invoiceId, 'company_id' => $companyId])
                    ->first();
                if ($inv) {
                    $invoiceData = [
                        'id'           => (string)$inv->id,
                        'paymentstate' => (string)$inv->paymentstate,
                        'alreadypaid'  => (float)$inv->alreadypaid,
                        'remaining'    => (float)$inv->remaining,
                        'currency'     => (string)$inv->currency,
                    ];
                }
            }
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'ok'      => $ok,
                    'message' => $ok ? __('Dopasowanie potwierdzone — faktura zaktualizowana.')
                                     : __('Nie udało się potwierdzić dopasowania.'),
                    'invoice' => $invoiceData,
                ], JSON_UNESCAPED_UNICODE));
        }

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

    // ── Alokacje: wyszukiwanie faktur ─────────────────────────────────────────

    /**
     * AJAX GET: szukaj faktur (system + legacy) po numerze / nazwie kontrahenta.
     * Zwraca listę trafień z danymi potrzebnymi do alokacji.
     */
    public function invoiceSearch(): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId  = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;
        $q          = trim((string)$this->request->getQuery('q', ''));
        $source     = (string)$this->request->getQuery('source', 'all'); // all|system|legacy
        $unpaidOnly = (string)$this->request->getQuery('unpaid', '') === '1';
        $showAll    = $q === ''; // gdy pusty q — zwracaj listę nieopłaconych

        $results = [];

        // Warianty zapytania — dla numerów faktur typu fw/5/04/2026 vs FW/05/04/2026
        $qVariants = [$q];
        if ($q !== '') {
            $qVariants[] = str_replace('/', '', $q);
            // Dodaj zera wiodące do pojedynczych cyfr w środkowych segmentach
            $withZeros = preg_replace_callback('#/(\d)(?=/|$)#', function ($m) { return '/0' . $m[1]; }, $q);
            if ($withZeros && $withZeros !== $q) {
                $qVariants[] = $withZeros;
                $qVariants[] = str_replace('/', '', $withZeros);
            }
            // Skrócony rok 2026 → 26
            $shortYear = preg_replace('/(\d{2})(\d{2})$/', '$2', $q);
            if ($shortYear && $shortYear !== $q) {
                $qVariants[] = $shortYear;
                $qVariants[] = str_replace('/', '', $shortYear);
            }
            $qVariants = array_unique($qVariants);
        }

        if ($q !== '' || $showAll) {
            // ── Faktury systemowe ──────────────────────────────────────────
            if ($source !== 'legacy') {
                $query = $this->fetchTable('Invoices')->find()
                    ->contain([
                        'InvoiceContractors' => function (\Cake\ORM\Query\SelectQuery $q2) {
                            return $q2->select(['id', 'invoice_id', 'name', 'nip']);
                        },
                    ])
                    ->where(['Invoices.company_id' => $companyId])
                    // Workflow: NULL lub != 'draft' (legacy data ma NULL)
                    ->where(['OR' => [
                        ['Invoices.workflow_status IS' => null],
                        ['Invoices.workflow_status !=' => 'draft'],
                    ]])
                    ->select([
                        'Invoices.id', 'Invoices.fullnumber', 'Invoices.total', 'Invoices.netto',
                        'Invoices.remaining', 'Invoices.currency',
                        'Invoices.currency_exchange', 'Invoices.paymentstate',
                        'Invoices.paymentdate', 'Invoices.date',
                    ])
                    ->orderByDesc('Invoices.date')
                    ->limit($showAll ? 30 : 15);

                // Filtr unpaid działa tylko gdy user JAWNIE go zażąda (unpaid=1)
                // lub gdy nie wpisuje nic (showAll = default lista nieopłaconych).
                // Gdy user wpisuje query — zwracamy WSZYSTKIE (też zapłacone),
                // bo czasem trzeba podpiąć fakturę już opłaconą do innego przelewu.
                if ($showAll && $unpaidOnly) {
                    $query->where(['OR' => [
                        ['Invoices.paymentstate IS' => null],
                        ['Invoices.paymentstate IN' => ['unpaid', 'partial']],
                    ]]);
                }
                if (!$showAll) {
                    $searchOr = [];
                    foreach ($qVariants as $v) {
                        $searchOr[]['Invoices.fullnumber LIKE']     = '%' . $v . '%';
                        $searchOr[]['InvoiceContractors.name LIKE'] = '%' . $v . '%';
                    }
                    $searchOr[]['InvoiceContractors.nip'] = $q;
                    $query->where(['OR' => $searchOr]);
                }

                $rows = $query->all();

                foreach ($rows as $inv) {
                    // invoices.total / netto / remaining są W WALUCIE FAKTURY
                    // (potwierdza /rozliczenia/ksef bankTransactions endpoint).
                    // Nie dzielimy przez kurs — to było źródło bugu 307,85 vs 1313,26.
                    $currency      = (string)($inv->currency ?? 'PLN');
                    $rate          = (float)($inv->currency_exchange ?? 0);
                    $total         = (float)$inv->total;
                    $netto         = (float)$inv->netto;
                    $remaining     = (float)$inv->remaining;
                    $vat           = round($total - $netto, 2);

                    $results[] = [
                        'id'            => (string)$inv->id,
                        'source'        => 'system',
                        'fullnumber'    => (string)$inv->fullnumber,
                        'contractor'    => (string)($inv->invoice_contractor->name ?? ''),
                        'nip'           => (string)($inv->invoice_contractor->nip ?? ''),
                        'total'         => $total,
                        'netto'         => $netto,
                        'vat'           => $vat,
                        'remaining'     => $remaining,
                        'currency'      => $currency,
                        'exchange_rate' => $rate > 0 ? $rate : null,
                        'paymentstate'  => (string)($inv->paymentstate ?? 'unpaid'),
                        'date'          => $inv->date instanceof \DateTimeInterface ? $inv->date->format('Y-m-d') : substr((string)$inv->date, 0, 10),
                    ];
                }
            }

            // ── Faktury legacy ─────────────────────────────────────────────
            if ($source !== 'system') {
                $legacyQuery = $this->fetchTable('LegacyInvoices')->find()
                    ->where(['company_id' => $companyId])
                    ->select(['id', 'fullnumber', 'contractor_name', 'contractor_nip',
                              'total', 'netto', 'remaining', 'remaining_wal', 'total_wal',
                              'currency', 'exchange_rate', 'paymentstate', 'date'])
                    ->orderByDesc('date')
                    ->limit($showAll ? 30 : 15);

                if ($showAll && $unpaidOnly) {
                    $legacyQuery->where(['OR' => [
                        ['paymentstate IS' => null],
                        ['paymentstate IN' => ['unpaid', 'partial']],
                    ]]);
                }
                if (!$showAll) {
                    $searchOr = [];
                    foreach ($qVariants as $v) {
                        $searchOr[]['fullnumber LIKE']      = '%' . $v . '%';
                        $searchOr[]['contractor_name LIKE'] = '%' . $v . '%';
                    }
                    $searchOr[]['contractor_nip'] = $q;
                    $legacyQuery->where(['OR' => $searchOr]);
                }

                $legacyRows = $legacyQuery->all();

                foreach ($legacyRows as $inv) {
                    $total    = (float)$inv->total;
                    $netto    = (float)$inv->netto;
                    $currency = (string)($inv->currency ?? 'PLN');
                    $rate     = (float)($inv->exchange_rate ?? 0);
                    $results[] = [
                        'id'            => (string)$inv->id,
                        'source'        => 'legacy',
                        'fullnumber'    => (string)$inv->fullnumber,
                        'contractor'    => (string)($inv->contractor_name ?? ''),
                        'nip'           => (string)($inv->contractor_nip ?? ''),
                        'total'         => $total,
                        'netto'         => $netto,
                        'vat'           => round($total - $netto, 2),
                        'remaining'     => $currency !== 'PLN' ? (float)$inv->remaining_wal : (float)$inv->remaining,
                        'remaining_pln' => (float)$inv->remaining,
                        'currency'      => $currency,
                        'exchange_rate' => $rate > 0 ? $rate : null,
                        'paymentstate'  => (string)($inv->paymentstate ?? 'unpaid'),
                        'date'          => $inv->date instanceof \DateTimeInterface ? $inv->date->format('Y-m-d') : substr((string)$inv->date, 0, 10),
                    ];
                }
            }
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['results' => $results]));
    }

    /**
     * AJAX GET: zwraca alokacje przypisane do konkretnego przelewu + saldo.
     */
    public function txAllocations(string $txId): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $tx = $this->fetchTable('BankTransactions')->find()
            ->where(['id' => $txId, 'company_id' => $companyId])
            ->select(['id', 'amount', 'currency'])
            ->first();

        if ($tx === null) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Przelew nie istnieje.']));
        }

        // Raw SQL — nie wymaga BankTransactionAllocationsTable class
        $db    = $this->fetchTable('BankTransactions')->getConnection();
        $stmt  = $db->execute(
            'SELECT bta.id, bta.invoice_id, bta.legacy_invoice_id, bta.invoice_payment_id,
                    bta.allocated_amount, bta.currency, bta.allocation_type, bta.note, bta.created
             FROM bank_transaction_allocations bta
             WHERE bta.bank_transaction_id = ? AND bta.company_id = ?
             ORDER BY bta.created DESC',
            [$txId, $companyId]
        );
        $rows = $stmt->fetchAll('assoc');

        $txAmount  = (float)$tx->amount;
        $allocated = array_sum(array_column($rows, 'allocated_amount'));
        $remaining = round($txAmount - $allocated, 4);

        // Dozbieranie numerów faktur
        $invIds    = array_filter(array_column($rows, 'invoice_id'));
        $legIds    = array_filter(array_column($rows, 'legacy_invoice_id'));
        $invNumbers = [];

        if ($invIds) {
            $invRows = $this->fetchTable('Invoices')->find()
                ->where(['id IN' => $invIds])
                ->select(['id', 'fullnumber'])->all();
            foreach ($invRows as $r) { $invNumbers[(string)$r->id] = $r->fullnumber; }
        }
        if ($legIds) {
            $legRows = $this->fetchTable('LegacyInvoices')->find()
                ->where(['id IN' => $legIds])
                ->select(['id', 'fullnumber'])->all();
            foreach ($legRows as $r) { $invNumbers[(string)$r->id] = $r->fullnumber; }
        }

        $typeLabels = ['gross' => 'brutto', 'net' => 'netto', 'vat' => 'VAT'];
        $mapped = array_map(static function (array $row) use ($invNumbers, $typeLabels): array {
            $fk = $row['invoice_id'] ?: $row['legacy_invoice_id'];
            return [
                'id'               => (string)$row['id'],
                'invoice_id'       => $row['invoice_id'] ?: null,
                'legacy_invoice_id'=> $row['legacy_invoice_id'] ?: null,
                'invoice_payment_id' => $row['invoice_payment_id'] ?: null,
                'fullnumber'       => $invNumbers[$fk] ?? '—',
                'source'           => $row['invoice_id'] ? 'system' : 'legacy',
                'allocated_amount' => round((float)$row['allocated_amount'], 2),
                'currency'         => (string)$row['currency'],
                'allocation_type'  => (string)$row['allocation_type'],
                'type_label'       => $typeLabels[$row['allocation_type']] ?? $row['allocation_type'],
                'note'             => (string)($row['note'] ?? ''),
                'created'          => substr((string)($row['created'] ?? ''), 0, 10),
            ];
        }, $rows);

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'tx_id'            => $txId,
                'tx_amount'        => $txAmount,
                'tx_currency'      => (string)$tx->currency,
                'allocated_amount' => round($allocated, 2),
                'remaining_amount' => $remaining,
                'allocations'      => $mapped,
            ]));
    }

    /**
     * AJAX POST: wysyła `title` + `description` przelewu do OpenAI
     * i prosi o wyciągnięcie numerów faktur. Zwraca posortowaną listę
     * numerów (system FW/FV/FK i legacy 0000/MM/YYYY).
     *
     * Konwencja numerów:
     *  - System (od 2026-04-01): FW/1/4/2026, FV/12/4/2026, FK/3/4/2026 (prefix/index/month/year)
     *  - Legacy (przed 2026-04-01): 0102/03/2026 (NNNN/MM/YYYY)
     */
    public function aiParseTitle(string $txId): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $tx = $this->fetchTable('BankTransactions')->find()
            ->where(['id' => $txId, 'company_id' => $companyId])
            ->select(['id', 'title', 'description', 'party_name', 'parsed_inv'])
            ->first();

        if ($tx === null) {
            return $this->response->withType('application/json')
                ->withStatus(404)
                ->withStringBody(json_encode(['error' => 'Przelew nie istnieje.']));
        }

        $title = trim((string)$tx->title);
        $desc  = trim((string)$tx->description);
        $payload = trim($title . "\n" . ($desc !== $title ? $desc : ''));

        if ($payload === '') {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['numbers' => [], 'note' => 'Tytuł i opis są puste.']));
        }

        $system = <<<SYS
Jesteś asystentem księgowym. Z tekstu tytułu/opisu przelewu wyciągasz NUMERY FAKTUR.

Obowiązują DWIE konwencje numeracji:
1) System od 2026-04-01: PREFIX/INDEX/MIESIĄC/ROK
   - PREFIX: FW (walutowa), FV (VAT), FK (korekta), FZ (zaliczka), PRO (proforma), NU (no-VAT)
   - INDEX: 1+ cyfr (np. 1, 12, 234)
   - MIESIĄC: 1-12 (jedno- lub dwucyfrowy: 4 albo 04)
   - ROK: 4 cyfry (2026, 2025)
   - Przykłady: FW/1/4/2026, FV/12/04/2026, FK/3/4/2026
2) Legacy przed 2026-04-01: NNNN/MM/YYYY
   - Przykłady: 0102/03/2026, 0035/12/2025

NORMALIZUJ:
- Dodaj zera wiodące w miesiącu nie wymagane (zostaw jak jest w tekście).
- Małe litery zamień na duże (fw → FW).
- Numery oddzielone spacjami, średnikami, przecinkami, "i", "oraz", "+" — to OSOBNE wpisy.
- Ignoruj kwoty, NIP, daty bez kontekstu numeru, identyfikatory płatności (np. /PAY/, /VAT/), nr CMR.
- Jeśli widzisz tylko fragment (np. "FW1" bez ukośników) — zwróć tylko jeśli możesz to sensownie zinterpretować jako PREFIX+INDEX (np. "FW1/4/2026").

ZWRÓĆ JSON:
{"numbers": [{"raw": "FW1/4/2026", "normalized": "FW/1/4/2026", "type": "system|legacy", "confidence": 0..100}], "note": "krótkie uzasadnienie"}

Posortuj według malejącej pewności. Jeśli nic nie znaleziono — zwróć pusty array.
SYS;

        $user = "Tytuł przelewu:\n{$title}\n\n";
        if ($desc !== '' && $desc !== $title) {
            $user .= "Opis dodatkowy:\n{$desc}\n\n";
        }
        if (!empty($tx->party_name)) {
            $user .= "Nadawca: {$tx->party_name}\n";
        }
        if (!empty($tx->parsed_inv)) {
            $user .= "Wstępnie wyparsowane przez regex: {$tx->parsed_inv}\n";
        }

        try {
            $ai = new \App\Service\Ai\OpenAiService();
            $result = $ai->chatJson($system, $user, 800);
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('[ai-parse-title] ' . $e->getMessage());
            return $this->response->withType('application/json')
                ->withStatus(502)
                ->withStringBody(json_encode([
                    'error' => 'Błąd AI: ' . $e->getMessage(),
                ]));
        }

        $numbers = is_array($result['numbers'] ?? null) ? $result['numbers'] : [];
        // Deduplikacja po `normalized`
        $seen = [];
        $clean = [];
        foreach ($numbers as $n) {
            $norm = trim((string)($n['normalized'] ?? $n['raw'] ?? ''));
            if ($norm === '' || isset($seen[$norm])) continue;
            $seen[$norm] = true;
            $clean[] = [
                'raw'        => (string)($n['raw'] ?? $norm),
                'normalized' => $norm,
                'type'       => (string)($n['type'] ?? 'system'),
                'confidence' => max(0, min(100, (int)($n['confidence'] ?? 50))),
            ];
        }
        usort($clean, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'numbers' => $clean,
                'note'    => (string)($result['note'] ?? ''),
                'tx_id'   => $txId,
            ], JSON_UNESCAPED_UNICODE));
    }

    // ── Usunięcie importu ─────────────────────────────────────────────────────

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

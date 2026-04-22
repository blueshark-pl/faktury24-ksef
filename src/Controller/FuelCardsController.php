<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\E100ApiService;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Utility\Text;

/**
 * Moduł kart paliwowych E100.
 *
 * Akcje:
 *  - index          GET  /karty-paliwowe               — lista transakcji
 *  - accounts       GET  /karty-paliwowe/konta          — zarządzanie kontami E100
 *  - addAccount     GET/POST /karty-paliwowe/konta/dodaj
 *  - editAccount    GET/POST /karty-paliwowe/konta/edytuj/:id
 *  - deleteAccount  POST /karty-paliwowe/konta/usun/:id
 *  - sync           POST /karty-paliwowe/sync           — syncronizacja transakcji (AJAX)
 *  - cards          GET  /karty-paliwowe/karty          — lista kart
 *  - cardInfo       GET  /karty-paliwowe/karty/info     — info o karcie (AJAX)
 *  - blockCard      POST /karty-paliwowe/karty/blokuj   — blokada karty
 *  - balance        GET  /karty-paliwowe/saldo          — saldo konta E100 (AJAX)
 *  - limits         GET  /karty-paliwowe/limity         — limity klientów
 *  - setLimit       POST /karty-paliwowe/limity/ustaw   — ustaw limit
 *  - stations       GET  /karty-paliwowe/stacje         — przeglądarka stacji
 *  - exportCsv      GET  /karty-paliwowe/export-csv     — eksport transakcji CSV
 */
class FuelCardsController extends AppController
{
    private E100ApiService $e100;

    public function initialize(): void
    {
        parent::initialize();
        $this->e100 = new E100ApiService();
    }

    // =========================================================================
    // Helpers prywatne
    // =========================================================================

    private function currentCompanyId(): string
    {
        return (string)($this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId);
    }

    /**
     * Pobiera aktywne konto E100 według id lub pierwsze aktywne.
     * Rzuca NotFoundException gdy brak.
     */
    private function requireAccount(?string $accountId = null): \App\Model\Entity\E100Account
    {
        $E100Accounts = $this->fetchTable('E100Accounts');
        $companyId    = $this->currentCompanyId();

        $cond = ['company_id' => $companyId, 'is_active' => true];
        if ($accountId !== null) {
            $cond['id'] = $accountId;
        }

        $account = $E100Accounts->find()->where($cond)->first();
        if ($account === null) {
            throw new \Cake\Http\Exception\NotFoundException('Brak aktywnego konta E100');
        }

        return $account;
    }

    // =========================================================================
    // Transakcje — lista główna
    // =========================================================================

    public function index(): void
    {
        $companyId = $this->currentCompanyId();
        $E100Tx    = $this->fetchTable('E100Transactions');
        $E100Acc   = $this->fetchTable('E100Accounts');

        $accounts     = $E100Acc->find()->where(['company_id' => $companyId, 'is_active' => true])->all();
        $accountId    = $this->request->getQuery('account_id', '');
        $search       = trim((string)$this->request->getQuery('q', ''));
        $dateFrom     = $this->request->getQuery('date_from', '');
        $dateTo       = $this->request->getQuery('date_to', '');
        $cardFilter   = trim((string)$this->request->getQuery('card', ''));
        $autoFilter   = trim((string)$this->request->getQuery('auto', ''));
        $stationFilter= trim((string)$this->request->getQuery('station', ''));
        $page         = max(1, (int)$this->request->getQuery('page', 1));
        $limit        = (int)$this->request->getQuery('limit', 50);
        if (!in_array($limit, [25, 50, 100, 200], true)) {
            $limit = 50;
        }

        $cond = ['E100Transactions.company_id' => $companyId];

        if ($accountId !== '') {
            $cond['E100Transactions.e100_account_id'] = $accountId;
        }
        if ($dateFrom !== '') {
            $cond['E100Transactions.date >='] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $cond['E100Transactions.date <='] = $dateTo . ' 23:59:59';
        }
        if ($cardFilter !== '') {
            $cond['E100Transactions.card LIKE'] = '%' . $cardFilter . '%';
        }
        if ($autoFilter !== '') {
            $cond['E100Transactions.auto LIKE'] = '%' . $autoFilter . '%';
        }
        if ($stationFilter !== '') {
            $cond['E100Transactions.station_id LIKE'] = '%' . $stationFilter . '%';
        }
        if ($search !== '') {
            $cond['OR'] = [
                'E100Transactions.auto LIKE'         => '%' . $search . '%',
                'E100Transactions.card LIKE'         => '%' . $search . '%',
                'E100Transactions.service_name LIKE' => '%' . $search . '%',
                'E100Transactions.station_id LIKE'   => '%' . $search . '%',
                'E100Transactions.address LIKE'      => '%' . $search . '%',
                'E100Transactions.brand LIKE'        => '%' . $search . '%',
                'E100Transactions.driver LIKE'       => '%' . $search . '%',
            ];
        }

        $total = $E100Tx->find()->contain(['E100Accounts' => function ($q) {
            return $q->select(['id', 'label']);
        }])->where($cond)->count();

        $transactions = $E100Tx->find()
            ->contain(['E100Accounts' => function ($q) {
                return $q->select(['id', 'label']);
            }])
            ->where($cond)
            ->orderByDesc('E100Transactions.date')
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->all();

        // Podsumowanie przefiltrowanych
        $sumQuery = $E100Tx->find()->where($cond);
        $sumData  = $sumQuery->select([
            'total_sum'    => $sumQuery->func()->sum('sum'),
            'total_volume' => $sumQuery->func()->sum('volume'),
        ])->first();

        $this->set(compact(
            'transactions', 'accounts', 'total', 'page', 'limit',
            'accountId', 'search', 'dateFrom', 'dateTo',
            'cardFilter', 'autoFilter', 'stationFilter',
            'sumData'
        ));
        $this->set('title', 'Karty paliwowe E100 — transakcje');
    }

    // =========================================================================
    // Eksport CSV
    // =========================================================================

    public function exportCsv(): Response
    {
        $companyId = $this->currentCompanyId();
        $E100Tx    = $this->fetchTable('E100Transactions');

        $accountId  = $this->request->getQuery('account_id', '');
        $dateFrom   = $this->request->getQuery('date_from', '');
        $dateTo     = $this->request->getQuery('date_to', '');
        $cardFilter = trim((string)$this->request->getQuery('card', ''));
        $autoFilter = trim((string)$this->request->getQuery('auto', ''));

        $cond = ['E100Transactions.company_id' => $companyId];
        if ($accountId !== '') { $cond['E100Transactions.e100_account_id'] = $accountId; }
        if ($dateFrom !== '')  { $cond['E100Transactions.date >='] = $dateFrom . ' 00:00:00'; }
        if ($dateTo !== '')    { $cond['E100Transactions.date <='] = $dateTo . ' 23:59:59'; }
        if ($cardFilter !== '') { $cond['E100Transactions.card LIKE'] = '%' . $cardFilter . '%'; }
        if ($autoFilter !== '') { $cond['E100Transactions.auto LIKE'] = '%' . $autoFilter . '%'; }

        $rows = $E100Tx->find()->where($cond)->orderByDesc('E100Transactions.date')->all();

        $csv  = "Data;Karta;Pojazd;Stacja;Adres;Usługa;Ilość;Cena;Waluta;Suma;Rabat;Faktura E100;Kierowca\n";
        foreach ($rows as $r) {
            $csv .= implode(';', [
                $r->date?->format('Y-m-d H:i') ?? '',
                $r->card ?? '',
                $r->auto ?? '',
                $r->station_id ?? '',
                str_replace(';', ',', $r->address ?? ''),
                str_replace(';', ',', $r->service_name ?? ''),
                number_format((float)($r->volume ?? 0), 4, '.', ''),
                number_format((float)($r->price ?? 0), 4, '.', ''),
                $r->currency ?? '',
                number_format((float)($r->sum ?? 0), 2, '.', ''),
                number_format((float)($r->discount ?? 0), 2, '.', ''),
                $r->invoice_ref ?? '',
                str_replace(';', ',', $r->driver ?? ''),
            ]) . "\n";
        }

        $filename = 'e100_transakcje_' . date('Ymd_His') . '.csv';

        return $this->response
            ->withType('text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStringBody("\xEF\xBB\xBF" . $csv); // BOM UTF-8 dla Excela
    }

    // =========================================================================
    // Konta E100
    // =========================================================================

    public function accounts(): void
    {
        $companyId = $this->currentCompanyId();
        $E100Acc   = $this->fetchTable('E100Accounts');

        $accounts = $E100Acc->find()
            ->where(['company_id' => $companyId])
            ->orderByDesc('created')
            ->all();

        $this->set(compact('accounts'));
        $this->set('title', 'Konta E100');
    }

    public function addAccount(): void
    {
        $companyId = $this->currentCompanyId();
        $E100Acc   = $this->fetchTable('E100Accounts');
        $account   = $E100Acc->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = (array)$this->request->getData();

            $plain = trim((string)($data['password_plain'] ?? ''));
            if ($plain === '') {
                $this->Flash->error('Podaj hasło.');
                $this->set(compact('account'));
                $this->set('title', 'Dodaj konto E100');

                return;
            }

            $account = $E100Acc->patchEntity($account, [
                'id'           => Text::uuid(),
                'company_id'   => $companyId,
                'label'        => trim((string)($data['label'] ?? '')),
                'username'     => trim((string)($data['username'] ?? '')),
                'password_enc' => $this->e100->encryptPassword($plain),
                'is_active'    => (bool)($data['is_active'] ?? true),
            ]);

            if ($E100Acc->save($account)) {
                // Przetestuj połączenie — pobierz token
                try {
                    $this->e100->authenticate($account);
                    $this->Flash->success('Konto E100 dodane i połączenie zweryfikowane.');
                } catch (\RuntimeException $e) {
                    $this->Flash->warning('Konto zapisane, ale weryfikacja połączenia nie powiodła się: ' . $e->getMessage());
                }
                $this->redirect(['action' => 'accounts']);

                return;
            }

            $this->Flash->error('Nie udało się zapisać konta.');
        }

        $this->set(compact('account'));
        $this->set('title', 'Dodaj konto E100');
    }

    public function editAccount(string $id): void
    {
        $companyId = $this->currentCompanyId();
        $E100Acc   = $this->fetchTable('E100Accounts');
        $account   = $E100Acc->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();

        if ($this->request->is(['post', 'put'])) {
            $data  = (array)$this->request->getData();
            $patch = [
                'label'     => trim((string)($data['label'] ?? '')),
                'username'  => trim((string)($data['username'] ?? '')),
                'is_active' => (bool)($data['is_active'] ?? false),
            ];

            $plain = trim((string)($data['password_plain'] ?? ''));
            if ($plain !== '') {
                $patch['password_enc'] = $this->e100->encryptPassword($plain);
                // Unieważnij token po zmianie hasła
                $patch['access_token']    = null;
                $patch['token_expires_at']= null;
            }

            $account = $E100Acc->patchEntity($account, $patch);

            if ($E100Acc->save($account)) {
                $this->Flash->success('Konto zaktualizowane.');
                $this->redirect(['action' => 'accounts']);

                return;
            }

            $this->Flash->error('Błąd zapisu.');
        }

        $this->set(compact('account'));
        $this->set('title', 'Edytuj konto E100');
    }

    public function deleteAccount(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $companyId = $this->currentCompanyId();
        $E100Acc   = $this->fetchTable('E100Accounts');
        $account   = $E100Acc->find()->where(['id' => $id, 'company_id' => $companyId])->firstOrFail();

        if ($E100Acc->delete($account)) {
            $this->Flash->success('Konto usunięte wraz z transakcjami.');
        } else {
            $this->Flash->error('Nie można usunąć konta.');
        }

        return $this->redirect(['action' => 'accounts']);
    }

    // =========================================================================
    // Synchronizacja — AJAX
    // =========================================================================

    /**
     * POST /karty-paliwowe/sync
     * Params: account_id (opcjonalne), date_from, date_to
     *
     * Pobiera transakcje z E100 i zapisuje do lokalnej bazy (upsert po un_id).
     * Zwraca JSON: {success, imported, skipped, errors}
     */
    public function sync(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName('Json');
        $this->response = $this->response->withType('application/json');

        $companyId = $this->currentCompanyId();
        $accountId = $this->request->getData('account_id', '');
        $dateFrom  = $this->request->getData('date_from', '');
        $dateTo    = $this->request->getData('date_to', '');

        if ($dateFrom === '') {
            $dateFrom = (new \DateTime('first day of this month'))->format('Y-m-d');
        }
        if ($dateTo === '') {
            $dateTo = (new \DateTime())->format('Y-m-d');
        }

        $E100Acc = $this->fetchTable('E100Accounts');
        $cond    = ['company_id' => $companyId, 'is_active' => true];
        if ($accountId !== '') {
            $cond['id'] = $accountId;
        }
        $accounts = $E100Acc->find()->where($cond)->all();

        if ($accounts->isEmpty()) {
            $this->set('_serialize', ['success', 'message']);
            $this->set(['success' => false, 'message' => 'Brak aktywnych kont E100']);

            return $this->response;
        }

        $totalImported = 0;
        $totalSkipped  = 0;
        $errors        = [];

        foreach ($accounts as $account) {
            try {
                [$imp, $skip] = $this->syncAccountTransactions($account, $companyId, $dateFrom, $dateTo);
                $totalImported += $imp;
                $totalSkipped  += $skip;

                // Zaktualizuj last_sync_at
                $E100Acc->patchEntity($account, ['last_sync_at' => new DateTime()]);
                $E100Acc->save($account);
            } catch (\RuntimeException $e) {
                $errors[] = $account->label . ': ' . $e->getMessage();
            }
        }

        $result = [
            'success'  => empty($errors),
            'imported' => $totalImported,
            'skipped'  => $totalSkipped,
            'errors'   => $errors,
            'message'  => "Zaimportowano: {$totalImported}, pominięto: {$totalSkipped}" . (empty($errors) ? '' : ' | Błędy: ' . implode('; ', $errors)),
        ];

        $this->set($result);
        $this->set('_serialize', array_keys($result));

        return $this->response;
    }

    /**
     * Synchronizuje transakcje jednego konta. Zwraca [imported, skipped].
     */
    private function syncAccountTransactions(
        \App\Model\Entity\E100Account $account,
        string $companyId,
        string $dateFrom,
        string $dateTo
    ): array {
        $E100Tx  = $this->fetchTable('E100Transactions');
        $page    = 1;
        $imported= 0;
        $skipped = 0;

        do {
            $resp = $this->e100->getTransactions($account, [
                'from'        => $dateFrom,
                'to'          => $dateTo,
                'page_number' => $page,
                'page_size'   => 2000,
            ]);

            $txList    = $resp['transactions'] ?? [];
            $pageCount = (int)($resp['page_count'] ?? 1);

            foreach ($txList as $tx) {
                $unId = (string)($tx['UnID'] ?? '');
                if ($unId === '') {
                    continue;
                }

                // Sprawdź duplikat
                $exists = $E100Tx->find()
                    ->where(['un_id' => $unId, 'company_id' => $companyId])
                    ->count();

                if ($exists > 0) {
                    $skipped++;
                    continue;
                }

                $dateRaw     = $tx['date'] ?? null;
                $dateInsRaw  = $tx['datetime_insert'] ?? null;
                $invDateRaw  = $tx['invoice_date'] ?? null;

                $entity = $E100Tx->newEntity([
                    'id'                    => Text::uuid(),
                    'company_id'            => $companyId,
                    'e100_account_id'       => $account->id,
                    'un_id'                 => $unId,
                    'card'                  => $tx['card'] ?? null,
                    'card_shortname'        => (string)($tx['card_shortname'] ?? ''),
                    'auto'                  => $tx['auto'] ?? null,
                    'date'                  => $dateRaw ? new DateTime($dateRaw) : null,
                    'datetime_insert'       => $dateInsRaw ? new DateTime($dateInsRaw) : null,
                    'station_id'            => $tx['station_id'] ?? null,
                    'address'               => $tx['address'] ?? null,
                    'brand'                 => $tx['brand'] ?? null,
                    'service_id'            => isset($tx['service_id']) ? (int)$tx['service_id'] : null,
                    'service_name'          => $tx['service_name'] ?? null,
                    'volume'                => isset($tx['volume']) ? (float)$tx['volume'] : null,
                    'price'                 => isset($tx['price']) ? (float)$tx['price'] : null,
                    'currency'              => $tx['currency'] ?? 'EUR',
                    'sum'                   => isset($tx['sum']) ? (float)$tx['sum'] : null,
                    'discount'              => isset($tx['discount']) ? (float)$tx['discount'] : null,
                    'discount_percentage'   => isset($tx['discount_percentage']) ? (float)$tx['discount_percentage'] : null,
                    'amount_without_discount'=> isset($tx['amount_without_discount']) ? (float)$tx['amount_without_discount'] : null,
                    'excise'                => isset($tx['excise']) ? (float)$tx['excise'] : null,
                    'ticket'                => (string)($tx['ticket'] ?? ''),
                    'confirmed'             => (bool)($tx['confirmed'] ?? false),
                    'exposed'               => (bool)($tx['exposed'] ?? false),
                    'invoice_ref'           => isset($tx['invoice_id']) ? (string)$tx['invoice_id'] : null,
                    'invoice_date'          => $invDateRaw ? new \Cake\I18n\Date($invDateRaw) : null,
                    'driver'                => $tx['driver'] ?? null,
                    'card_driver'           => $tx['card_driver'] ?? null,
                    'category'              => isset($tx['category']) ? (int)$tx['category'] : null,
                    'row_version'           => $tx['row_version'] ?? null,
                ]);

                if ($E100Tx->save($entity)) {
                    $imported++;
                }
            }

            $page++;
        } while ($page <= $pageCount);

        return [$imported, $skipped];
    }

    // =========================================================================
    // Karty
    // =========================================================================

    public function cards(): void
    {
        $companyId = $this->currentCompanyId();
        $E100Tx    = $this->fetchTable('E100Transactions');
        $E100Acc   = $this->fetchTable('E100Accounts');

        $accounts = $E100Acc->find()->where(['company_id' => $companyId, 'is_active' => true])->all();

        // Unikalne karty zapamiętane lokalnie (z transakcji)
        $cardsQuery = $E100Tx->find()
            ->select(['card', 'card_shortname', 'e100_account_id', 'auto',
                'tx_count'   => $E100Tx->find()->func()->count('*'),
                'last_date'  => $E100Tx->find()->func()->max('date'),
                'total_sum'  => $E100Tx->find()->func()->sum('sum'),
            ])
            ->where(['E100Transactions.company_id' => $companyId, 'E100Transactions.card IS NOT' => null])
            ->groupBy(['card', 'card_shortname', 'e100_account_id', 'auto'])
            ->orderByDesc('last_date')
            ->all();

        $this->set(compact('accounts', 'cardsQuery'));
        $this->set('title', 'Karty paliwowe E100');
    }

    /**
     * GET /karty-paliwowe/karty/info?account_id=...&card=... — JSON
     */
    public function cardInfo(): Response
    {
        $this->viewBuilder()->setClassName('Json');
        $accountId  = $this->request->getQuery('account_id', '');
        $cardNumber = trim((string)$this->request->getQuery('card', ''));

        try {
            $account = $this->requireAccount($accountId ?: null);
            $info    = $this->e100->getCardInfo($account, $cardNumber);
            $this->set(['success' => true, 'info' => $info]);
            $this->set('_serialize', ['success', 'info']);
        } catch (\RuntimeException $e) {
            $this->set(['success' => false, 'message' => $e->getMessage()]);
            $this->set('_serialize', ['success', 'message']);
        }

        return $this->response->withType('application/json');
    }

    /**
     * POST /karty-paliwowe/karty/blokuj
     */
    public function blockCard(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName('Json');

        $accountId  = (string)$this->request->getData('account_id', '');
        $cardNumber = trim((string)$this->request->getData('card_number', ''));

        try {
            $account = $this->requireAccount($accountId ?: null);
            $this->e100->blockCard($account, $cardNumber);
            $this->set(['success' => true, 'message' => 'Karta ' . h($cardNumber) . ' zablokowana.']);
            $this->set('_serialize', ['success', 'message']);
        } catch (\RuntimeException $e) {
            $this->set(['success' => false, 'message' => $e->getMessage()]);
            $this->set('_serialize', ['success', 'message']);
        }

        return $this->response->withType('application/json');
    }

    // =========================================================================
    // Saldo
    // =========================================================================

    /**
     * GET /karty-paliwowe/saldo?account_id=... — JSON AJAX
     */
    public function balance(): Response
    {
        $this->viewBuilder()->setClassName('Json');
        $accountId = $this->request->getQuery('account_id', '');

        try {
            $account = $this->requireAccount($accountId ?: null);
            $data    = $this->e100->getBalance($account);
            $this->set(['success' => true, 'balance' => $data, 'label' => $account->label]);
            $this->set('_serialize', ['success', 'balance', 'label']);
        } catch (\RuntimeException $e) {
            $this->set(['success' => false, 'message' => $e->getMessage()]);
            $this->set('_serialize', ['success', 'message']);
        }

        return $this->response->withType('application/json');
    }

    // =========================================================================
    // Limity
    // =========================================================================

    public function limits(): void
    {
        $companyId = $this->currentCompanyId();
        $E100Acc   = $this->fetchTable('E100Accounts');
        $accounts  = $E100Acc->find()->where(['company_id' => $companyId, 'is_active' => true])->all();

        $this->set(compact('accounts'));
        $this->set('title', 'Limity klientów E100');
    }

    /**
     * GET /karty-paliwowe/limity/pobierz — AJAX JSON
     */
    public function getLimit(): Response
    {
        $this->viewBuilder()->setClassName('Json');
        $accountId  = $this->request->getQuery('account_id', '');
        $clientCode = trim((string)$this->request->getQuery('client_code', ''));

        try {
            $account = $this->requireAccount($accountId ?: null);
            $data    = $this->e100->getLimit($account, $clientCode ?: (string)$account->client_code);
            $this->set(['success' => true, 'limit' => $data]);
            $this->set('_serialize', ['success', 'limit']);
        } catch (\RuntimeException $e) {
            $this->set(['success' => false, 'message' => $e->getMessage()]);
            $this->set('_serialize', ['success', 'message']);
        }

        return $this->response->withType('application/json');
    }

    /**
     * POST /karty-paliwowe/limity/ustaw — AJAX JSON
     */
    public function setLimit(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName('Json');

        $accountId  = (string)$this->request->getData('account_id', '');
        $clientCode = trim((string)$this->request->getData('client_code', ''));
        $credit     = (float)$this->request->getData('credit', 0);

        try {
            $account = $this->requireAccount($accountId ?: null);
            $this->e100->setLimit($account, $clientCode ?: (string)$account->client_code, $credit);
            $this->set(['success' => true, 'message' => 'Limit ustawiony.']);
            $this->set('_serialize', ['success', 'message']);
        } catch (\RuntimeException $e) {
            $this->set(['success' => false, 'message' => $e->getMessage()]);
            $this->set('_serialize', ['success', 'message']);
        }

        return $this->response->withType('application/json');
    }

    // =========================================================================
    // Stacje
    // =========================================================================

    public function stations(): void
    {
        $companyId = $this->currentCompanyId();
        $E100Acc   = $this->fetchTable('E100Accounts');
        $accounts  = $E100Acc->find()->where(['company_id' => $companyId, 'is_active' => true])->all();

        $stationsData = null;
        $pricesData   = null;
        $accountId    = $this->request->getQuery('account_id', '');
        $country      = $this->request->getQuery('country', '');
        $stationSearch= trim((string)$this->request->getQuery('station_q', ''));

        if ($this->request->is('post') || $accountId !== '') {
            try {
                $account      = $this->requireAccount($accountId ?: null);
                $filters      = [];
                if ($country !== '') {
                    $filters['countries'] = [strtoupper($country)];
                }
                $stationsData = $this->e100->getStations($account, $filters);
            } catch (\RuntimeException $e) {
                $this->Flash->error('Błąd pobierania stacji: ' . $e->getMessage());
            }
        }

        $this->set(compact('accounts', 'stationsData', 'pricesData', 'accountId', 'country', 'stationSearch'));
        $this->set('title', 'Stacje paliw E100');
    }
}

<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\LegacyInvoiceSyncService;
use Cake\Http\Response;
use Cake\Utility\Text;

/**
 * Moduł rozliczeń — lista faktur + dodawanie wpłat (invoice_payments).
 */
class ReconciliationsController extends AppController
{
    public function index(): void
    {
        $this->request->allowMethod(['get']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $search    = trim((string)$this->request->getQuery('q', ''));
        $status    = $this->request->getQuery('status', '');
        $dateFrom  = $this->request->getQuery('date_from', '');
        $dateTo    = $this->request->getQuery('date_to', '');
        $typeFilter = $this->request->getQuery('type', '');
        $sort      = $this->request->getQuery('sort', 'paymentdate');
        $dir       = $this->request->getQuery('dir', 'asc');
        $page      = max(1, (int)$this->request->getQuery('page', 1));
        $limit     = (int)$this->request->getQuery('limit', 50);
        if (!in_array($limit, [25, 50, 100, 200], true)) {
            $limit = 50;
        }

        $today    = date('Y-m-d');
        $Invoices = $this->fetchTable('Invoices');

        // Filtr źródła — '' | 'system' | 'legacy'
        $sourceFilter = $this->request->getQuery('source', '');

        // ── Warunki WHERE wspólne dla głównego zapytania i statystyk ─────────
        $baseConditions = ['Invoices.company_id' => $companyId];

        // Wyszukiwanie po kontrahencie — podzapytanie
        if ($search !== '') {
            $like = '%' . $search . '%';
            $InvoiceContractors = $this->fetchTable('InvoiceContractors');
            $matchIds = $InvoiceContractors->find()
                ->select(['invoice_id'])
                ->where(['OR' => ['name LIKE' => $like, 'nip LIKE' => $like]])
                ->all()->extract('invoice_id')->toList();

            $orCond = ['Invoices.fullnumber LIKE' => $like];
            if (!empty($matchIds)) {
                $orCond['Invoices.id IN'] = $matchIds;
            }
            $baseConditions['OR'] = $orCond;
        }

        // Zakres dat
        if ($dateFrom !== '') {
            $baseConditions['Invoices.date >='] = $dateFrom;
        }
        if ($dateTo !== '') {
            $baseConditions['Invoices.date <='] = $dateTo;
        }

        // Typ faktury
        $validTypes = ['vat', 'novat', 'currency', 'proforma', 'advance', 'final', 'correction', 'margin', 'rental', 'oss', 'internal', 'internalEvidence'];
        if (in_array($typeFilter, $validTypes, true)) {
            $baseConditions['Invoices.type'] = $typeFilter;
        }

        // Warunki dla statystyk (kafelki) — BEZ filtra statusu, żeby kafelki były zawsze globalne
        $baseStatsConditions = $baseConditions;

        // Status płatności — dodaj tylko do warunków listy, NIE do statystyk
        if ($status === 'overdue') {
            $baseConditions['Invoices.paymentstate !='] = 'paid';
            $baseConditions['Invoices.paymentdate <']   = $today;
        } elseif (in_array($status, ['unpaid', 'partial', 'paid'], true)) {
            $baseConditions['Invoices.paymentstate'] = $status;
        }

        // ── Statystyki i lista — system (pomijamy gdy source=legacy) ─────────
        $stats    = ['count' => 0, 'totalReceivables' => 0.0, 'totalPaid' => 0.0, 'totalRemaining' => 0.0, 'overdue' => 0.0, 'overdueCount' => 0];
        $total    = 0;
        $invoices = [];
        $pages    = 1;

        if ($sourceFilter !== 'legacy') {
            // ── Statystyki (prosta agregacja SQL bez contain) — globalne, bez filtra statusu ──
            $statsRows = $Invoices->find()
                ->select([
                    'total'        => 'Invoices.total',
                    'alreadypaid'  => 'Invoices.alreadypaid',
                    'remaining'    => 'Invoices.remaining',
                    'paymentstate' => 'Invoices.paymentstate',
                    'paymentdate'  => 'Invoices.paymentdate',
                ])
                ->where($baseStatsConditions)
                ->where([
                    'OR' => [
                        ['Invoices.workflow_status IS'  => null],
                        ['Invoices.workflow_status !=' => 'draft'],
                    ],
                ])
                ->disableHydration()
                ->all()
                ->toArray();

            $stats = $this->_computeStats($statsRows, $today);

            // ── Główne zapytanie z kontrahentem ───────────────────────────────
            $allowedSort = ['paymentdate', 'date', 'total', 'remaining', 'fullnumber'];
            $sortCol = in_array($sort, $allowedSort, true) ? $sort : 'paymentdate';
            $sortDir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

            $invoiceQuery = $Invoices->find()
                ->contain([
                    'InvoiceContractors' => function (\Cake\ORM\Query\SelectQuery $q) {
                        return $q->select(['id', 'invoice_id', 'name', 'nip']);
                    },
                ])
                ->select([
                    'Invoices.id', 'Invoices.fullnumber', 'Invoices.date',
                    'Invoices.paymentdate', 'Invoices.paymentstate', 'Invoices.paymentmethod',
                    'Invoices.total', 'Invoices.alreadypaid', 'Invoices.remaining',
                    'Invoices.currency', 'Invoices.type', 'Invoices.created',
                    'Invoices.sent_at',
                ])
                ->where($baseConditions)
                ->where([
                    'OR' => [
                        ['Invoices.workflow_status IS'  => null],
                        ['Invoices.workflow_status !=' => 'draft'],
                    ],
                ])
                ->orderBy(['Invoices.' . $sortCol => $sortDir, 'Invoices.created' => 'DESC']);

            $total    = (clone $invoiceQuery)->count();
            $invoices = $invoiceQuery->limit($limit)->offset(($page - 1) * $limit)->all()->toArray();
            $pages    = (int)ceil($total / $limit);
        } else {
            // Gdy source=legacy — $sortDir potrzebne dla legacy query
            $allowedSort = ['paymentdate', 'date', 'total', 'remaining', 'fullnumber'];
            $sortDir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        }

        // ── Przelewy bankowe (per faktura) ──────────────────────────────────
        $bankByInvoice = [];
        if (!empty($invoices)) {
            $invoiceIds = array_column($invoices, 'id');

            $bankRows = $this->fetchTable('BankTransactions')->find()
                ->where([
                    'company_id'     => $companyId,
                    'invoice_id IN'  => $invoiceIds,
                    'match_status IN' => ['matched', 'proposed'],
                ])
                ->select(['id', 'invoice_id', 'match_status', 'amount', 'value_date', 'party_name'])
                ->orderByDesc('value_date')
                ->all()->toArray();

            foreach ($bankRows as $bt) {
                $iid = (string)$bt->invoice_id;
                $bankByInvoice[$iid] ??= $bt;
            }

            // ── Zlecenia Speed (lista per faktura) ───────────────────────────
            $speedByInvoice = [];
            $speedRows = $this->fetchTable('SpeedOrders')->find()
                ->where(['invoice_id IN' => $invoiceIds])
                ->select(['id', 'invoice_id', 'symbol', 'date_delivery', 'date_ship'])
                ->orderByAsc('symbol')
                ->all()->toArray();

            foreach ($speedRows as $so) {
                $iid = (string)$so->invoice_id;
                $speedByInvoice[$iid][] = $so;
            }
        } else {
            $speedByInvoice = [];
        }

        // ── Faktury archiwalne (legacy) ───────────────────────────────────────
        $legacyInvoices = [];
        $legacyTotal    = 0;
        $legacyPages    = 1;
        $legacyPage     = max(1, (int)$this->request->getQuery('lpage', 1));

        if ($sourceFilter !== 'system' && $typeFilter === '') {
            $LegacyInvoices = $this->fetchTable('LegacyInvoices');

            $legacyConditions = ['LegacyInvoices.company_id' => $companyId];

            if ($search !== '') {
                $like = '%' . $search . '%';
                $legacyConditions['OR'] = [
                    'LegacyInvoices.fullnumber LIKE'      => $like,
                    'LegacyInvoices.contractor_name LIKE' => $like,
                    'LegacyInvoices.contractor_nip LIKE'  => $like,
                ];
            }

            // Warunki dla statystyk (kafelki) — BEZ filtra statusu, żeby kafelki były zawsze globalne
            $legacyStatsConditions = $legacyConditions;

            if ($status === 'overdue') {
                $legacyConditions['LegacyInvoices.paymentstate !='] = 'paid';
                // Łapiemy albo te z paymentdate < dziś, albo te z platnosc-based terminem
                // Pobieramy wszystkie nieopłacone i filtrujemy po obliczonym terminie w PHP
            } elseif (in_array($status, ['unpaid', 'partial', 'paid'], true)) {
                $legacyConditions['LegacyInvoices.paymentstate'] = $status;
            }

            if ($dateFrom !== '') {
                $legacyConditions['LegacyInvoices.date >='] = $dateFrom;
                $legacyStatsConditions['LegacyInvoices.date >='] = $dateFrom;
            }
            if ($dateTo !== '') {
                $legacyConditions['LegacyInvoices.date <='] = $dateTo;
                $legacyStatsConditions['LegacyInvoices.date <='] = $dateTo;
            }

            // Statystyki legacy — używamy $legacyStatsConditions (bez filtra statusu)
            $legacyStatsRows = $LegacyInvoices->find()
                ->select([
                    'total'        => 'LegacyInvoices.total',
                    'alreadypaid'  => 'LegacyInvoices.alreadypaid',
                    'remaining'    => 'LegacyInvoices.remaining',
                    'paymentstate' => 'LegacyInvoices.paymentstate',
                    'paymentdate'  => 'LegacyInvoices.paymentdate',
                    'date'         => 'LegacyInvoices.date',
                    'platnosc'     => 'LegacyInvoices.platnosc',
                ])
                ->where($legacyStatsConditions)
                ->disableHydration()
                ->all()
                ->toArray();

            // Uzupełnij paymentdate z platnosc dla rekordów z null
            foreach ($legacyStatsRows as &$srow) {
                if (empty($srow['paymentdate']) && !empty($srow['platnosc'])) {
                    if (preg_match('/(\d+)\s*dni/i', (string)$srow['platnosc'], $pm)) {
                        $days  = (int)$pm[1];
                        $dStr  = $srow['date'] ? substr((string)$srow['date'], 0, 10) : '';
                        $dObj  = $dStr ? \DateTime::createFromFormat('Y-m-d', $dStr) : false;
                        if ($dObj) {
                            $dObj->modify("+{$days} days");
                            $srow['paymentdate'] = $dObj->format('Y-m-d');
                        }
                    }
                }
            }
            unset($srow);
            // _computeStats już wewnętrznie filtruje: state !== 'paid' && pdate < today
            $legacyStats = $this->_computeStats($legacyStatsRows, $today);

            // Merge statystyk
            $stats['count']            += $legacyStats['count'];
            $stats['totalReceivables'] += $legacyStats['totalReceivables'];
            $stats['totalPaid']        += $legacyStats['totalPaid'];
            $stats['totalRemaining']   += $legacyStats['totalRemaining'];
            $stats['overdue']          += $legacyStats['overdue'];
            $stats['overdueCount']     += $legacyStats['overdueCount'];

            // Zapytanie legacyInvoices z paginacją
            $legacyAllowedSort = ['paymentdate', 'date', 'total', 'remaining', 'fullnumber'];
            $legacySortCol = in_array($sort, $legacyAllowedSort, true) ? $sort : 'paymentdate';

            $legacyQ = $LegacyInvoices->find()
                ->where($legacyConditions)
                ->orderBy(['LegacyInvoices.' . $legacySortCol => $sortDir, 'LegacyInvoices.synced_at' => 'DESC']);

            if ($status === 'overdue') {
                // Dla "przeterminowane" pobieramy wszystkich nieopłaconych i filtrujemy w PHP,
                // bo część terminów pochodzi z pola platnosc (null paymentdate).
                $allUnpaid = $legacyQ->all()->toArray();
                $allUnpaid = array_values(array_filter($allUnpaid, function ($leg) use ($today): bool {
                    $pdate = null;
                    if ($leg->paymentdate !== null) {
                        try { $pdate = $leg->paymentdate->format('Y-m-d'); } catch (\Throwable $e) {
                            $pdate = substr((string)$leg->paymentdate, 0, 10) ?: null;
                        }
                    }
                    if (!$pdate && !empty($leg->platnosc) && preg_match('/(\d+)\s*dni/i', (string)$leg->platnosc, $m)) {
                        try { $ds = $leg->date->format('Y-m-d'); } catch (\Throwable $e) {
                            $ds = substr((string)$leg->date, 0, 10);
                        }
                        if (!empty($ds)) {
                            $c = \DateTime::createFromFormat('Y-m-d', $ds);
                            if ($c) { $c->modify("+{$m[1]} days"); $pdate = $c->format('Y-m-d'); }
                        }
                    }
                    return $pdate !== null && $pdate < $today;
                }));
                $legacyTotal  = count($allUnpaid);
                $legacyPages  = (int)ceil($legacyTotal / $limit) ?: 1;
                $legacyInvoices = array_slice($allUnpaid, ($legacyPage - 1) * $limit, $limit);
            } else {
                $legacyTotal    = (clone $legacyQ)->count();
                $legacyPages    = (int)ceil($legacyTotal / $limit) ?: 1;
                $legacyInvoices = $legacyQ->limit($limit)->offset(($legacyPage - 1) * $limit)->all()->toArray();
            }

            // ── Lokalne wpłaty per faktura legacy ────────────────────────────
            $legacyPaymentsByInvoiceId = [];
            if (!empty($legacyInvoices)) {
                $legacyIds = array_column($legacyInvoices, 'id');
                $legacyPaymentRows = $this->fetchTable('LegacyInvoicePayments')->find()
                    ->where(['legacy_invoice_id IN' => $legacyIds])
                    ->select(['id', 'legacy_invoice_id', 'payment_date', 'amount', 'payment_method', 'description'])
                    ->orderByAsc('payment_date')
                    ->all()->toArray();
                foreach ($legacyPaymentRows as $lp) {
                    $legacyPaymentsByInvoiceId[(string)$lp->legacy_invoice_id][] = $lp;
                }
            }
        } else {
            $legacyPaymentsByInvoiceId = [];
        }

        // Ostatnia synchronizacja legacy — dla UI
        $lastSync = null;
        if ($typeFilter === '') {
            $lastSync = $this->fetchTable('LegacySyncLogs')->find()
                ->where(['company_id' => $companyId])
                ->select(['rejestr', 'rok', 'mc', 'records_fetched', 'records_upserted', 'synced_at', 'status'])
                ->orderByDesc('synced_at')
                ->first();
        }

        $this->set(compact(
            'invoices', 'total', 'pages', 'page', 'limit',
            'search', 'status', 'dateFrom', 'dateTo', 'typeFilter', 'sort', 'dir',
            'stats', 'bankByInvoice', 'speedByInvoice',
            'legacyInvoices', 'legacyTotal', 'legacyPages', 'legacyPage',
            'legacyPaymentsByInvoiceId', 'sourceFilter', 'lastSync'
        ));
        $this->set('title', 'Rozliczenia');
    }

    // ── Dodaj wpłatę do faktury archiwalnej (AJAX / POST) ───────────────────

    public function addLegacyPayment(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $legacyInvoiceId = (string)($this->request->getData('legacy_invoice_id') ?? '');
        $amount          = (float)($this->request->getData('amount') ?? 0);
        $paymentDate     = (string)($this->request->getData('payment_date') ?? date('Y-m-d'));
        $method          = (string)($this->request->getData('payment_method') ?? 'transfer');
        $description     = (string)($this->request->getData('description') ?? '');
        $isAjax          = $this->request->is('ajax') || $this->request->getData('ajax') === '1';

        if ($legacyInvoiceId === '' || $amount <= 0) {
            if ($isAjax) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['error' => 'Nieprawidłowe dane wpłaty.']));
            }
            $this->Flash->error('Nieprawidłowe dane wpłaty.');
            return $this->redirect(['action' => 'index']);
        }

        $LegacyInvoices = $this->fetchTable('LegacyInvoices');
        $invoice = $LegacyInvoices->find()
            ->where(['id' => $legacyInvoiceId, 'company_id' => $companyId])
            ->select(['id', 'fullnumber', 'currency', 'total', 'remaining'])
            ->first();

        if ($invoice === null) {
            if ($isAjax) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['error' => 'Faktura nie istnieje lub brak uprawnień.']));
            }
            $this->Flash->error('Faktura archiwalna nie istnieje lub brak uprawnień.');
            return $this->redirect(['action' => 'index']);
        }

        $LegacyInvoicePayments = $this->fetchTable('LegacyInvoicePayments');
        $payment = $LegacyInvoicePayments->newEntity([
            'id'                => \Cake\Utility\Text::uuid(),
            'legacy_invoice_id' => $legacyInvoiceId,
            'company_id'        => $companyId,
            'payment_date'      => $paymentDate,
            'amount'            => $amount,
            'payment_method'    => $method ?: 'transfer',
            'description'       => $description ?: null,
        ]);

        if ($LegacyInvoicePayments->save($payment)) {
            $this->_refreshLegacyPaymentState($legacyInvoiceId);
            if ($isAjax) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['success' => true, 'message' => 'Wpłata zapisana.']));
            }
            $this->Flash->success(sprintf(
                'Wpłata %.2f %s zarejestrowana dla faktury archiwalnej %s.',
                $amount,
                h($invoice->currency ?? 'PLN'),
                h($invoice->fullnumber ?? $legacyInvoiceId)
            ));
        } else {
            if ($isAjax) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['error' => 'Nie udało się zapisać wpłaty.']));
            }
            $this->Flash->error('Nie udało się zapisać wpłaty.');
        }

        $redirect = $this->request->getData('redirect') ?? $this->referer(['action' => 'index']);
        return $this->redirect($redirect);
    }

    // ── Usuń wpłatę z faktury archiwalnej ────────────────────────────────────

    public function deleteLegacyPayment(string $paymentId): Response
    {
        $this->request->allowMethod(['post']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $LegacyInvoicePayments = $this->fetchTable('LegacyInvoicePayments');
        $payment = $LegacyInvoicePayments->find()
            ->where(['id' => $paymentId, 'company_id' => $companyId])
            ->first();

        if ($payment === null) {
            $this->Flash->error('Wpłata nie istnieje lub brak uprawnień.');
            return $this->redirect($this->request->getData('redirect') ?? ['action' => 'index']);
        }

        $legacyInvoiceId = (string)$payment->legacy_invoice_id;
        if ($LegacyInvoicePayments->delete($payment)) {
            $this->_refreshLegacyPaymentState($legacyInvoiceId);
            $this->Flash->success('Wpłata archiwalna usunięta.');
        } else {
            $this->Flash->error('Nie udało się usunąć wpłaty.');
        }

        return $this->redirect($this->request->getData('redirect') ?? $this->referer(['action' => 'index']));
    }

    // ── Przelicz stan faktury archiwalnej po zmianie wpłat ───────────────────

    private function _refreshLegacyPaymentState(string $legacyInvoiceId): void
    {
        $LegacyInvoices        = $this->fetchTable('LegacyInvoices');
        $LegacyInvoicePayments = $this->fetchTable('LegacyInvoicePayments');

        $invoice = $LegacyInvoices->find()
            ->where(['id' => $legacyInvoiceId])
            ->select(['id', 'total', 'alreadypaid', 'remaining', 'paymentstate'])
            ->first();
        if ($invoice === null) {
            return;
        }

        $localPaid = (float)($LegacyInvoicePayments->find()
            ->select(['s' => $LegacyInvoicePayments->find()->func()->sum('amount')])
            ->where(['legacy_invoice_id' => $legacyInvoiceId])
            ->first()?->s ?? 0);

        $total = (float)$invoice->total;

        $invoice->alreadypaid  = min($localPaid, $total);
        $invoice->remaining    = max(0.0, $total - $localPaid);
        $invoice->paymentstate = match (true) {
            $localPaid <= 0             => 'unpaid',
            $localPaid >= $total - 0.01 => 'paid',
            default                     => 'partial',
        };

        $LegacyInvoices->save($invoice);
    }

    // ── Synchronizacja faktur legacy z zewnętrznego API (AJAX POST) ─────────

    public function syncLegacy(): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;
        $userId    = $this->request->getAttribute('identity')?->get('id');
        $userName  = $this->request->getAttribute('identity')?->get('username') ?? '';

        $rejestr = (int)($this->request->getData('rejestr', 130));
        $rok     = (int)($this->request->getData('rok', (int)date('Y')));
        $mc      = $this->request->getData('mc');
        $mc      = ($mc !== '' && $mc !== null) ? (int)$mc : null;

        // Walidacja wejścia
        if ($rejestr <= 0 || $rok < 2010 || $rok > (int)date('Y') + 1) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Nieprawidłowe parametry synchronizacji.']));
        }
        if ($mc !== null && ($mc < 1 || $mc > 12)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Nieprawidłowy miesiąc (1-12).']));
        }

        $LegacySyncLogs = $this->fetchTable('LegacySyncLogs');
        $nowStr = date('Y-m-d H:i:s');

        try {
            $service = new LegacyInvoiceSyncService();
            $result  = $service->syncMonth($rejestr, $rok, $mc, $companyId);

            // Zapisz log sukcesu
            $log = $LegacySyncLogs->newEntity([
                'id'                => Text::uuid(),
                'company_id'        => $companyId,
                'rejestr'           => $rejestr,
                'rok'               => $rok,
                'mc'                => $mc,
                'synced_by_user_id' => $userId,
                'synced_by_name'    => $userName,
                'status'            => 'success',
                'records_fetched'   => $result['fetched'],
                'records_upserted'  => $result['upserted'],
                'records_changed'   => $result['changed'],
                'changes_detail'    => !empty($result['changes']) ? json_encode($result['changes']) : null,
                'error_message'     => null,
                'synced_at'         => $nowStr,
            ]);
            $LegacySyncLogs->save($log);

            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success'   => true,
                    'fetched'   => $result['fetched'],
                    'upserted'  => $result['upserted'],
                    'changed'   => $result['changed'],
                    'changes'   => $result['changes'],
                    'synced_at' => $nowStr,
                    'message'   => sprintf(
                        'Pobrano %d dokumentów, zapisano %d, zmienił stan: %d.',
                        $result['fetched'],
                        $result['upserted'],
                        $result['changed']
                    ),
                ]));
        } catch (\Throwable $e) {
            // Zapisz log błędu
            $log = $LegacySyncLogs->newEntity([
                'id'                => Text::uuid(),
                'company_id'        => $companyId,
                'rejestr'           => $rejestr,
                'rok'               => $rok,
                'mc'                => $mc,
                'synced_by_user_id' => $userId,
                'synced_by_name'    => $userName,
                'status'            => 'error',
                'records_fetched'   => 0,
                'records_upserted'  => 0,
                'records_changed'   => 0,
                'changes_detail'    => null,
                'error_message'     => $e->getMessage(),
                'synced_at'         => $nowStr,
            ]);
            $LegacySyncLogs->save($log);

            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'error'   => 'Błąd synchronizacji: ' . $e->getMessage(),
                    'success' => false,
                ]));
        }
    }

    // ── Przelewy bankowe dla faktury archiwalnej (AJAX, GET) ─────────────────

    public function legacyBankTransactions(string $legacyInvoiceId): Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $LegacyInvoices = $this->fetchTable('LegacyInvoices');
        $invoice = $LegacyInvoices->find()
            ->where(['id' => $legacyInvoiceId, 'company_id' => $companyId])
            ->select(['id', 'fullnumber', 'contractor_name', 'contractor_nip', 'date', 'remaining', 'total'])
            ->first();

        if ($invoice === null) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Faktura nie istnieje lub brak uprawnień']));
        }

        $nip            = $invoice->contractor_nip ?? null;
        $contractorName = $invoice->contractor_name ?? null;
        $invoiceRemaining = (float)($invoice->remaining ?? 0);
        $invoiceTotal     = (float)($invoice->total ?? 0);
        // Kwota referencyjna: jeśli coś już zapłacono — porównujemy do remaining, wpp do total
        $refAmount = $invoiceRemaining > 0.01 ? $invoiceRemaining : $invoiceTotal;

        $BankTransactions = $this->fetchTable('BankTransactions');

        $fmtDate = static function ($v): string {
            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
            return $v ? substr((string)$v, 0, 10) : '';
        };

        $mapTx = static function ($tx) use ($fmtDate, $refAmount): array {
            $amount     = (float)$tx->amount;
            $amountDiff = $refAmount > 0 ? abs($amount - $refAmount) : null;
            // Trafienie kwotowe: różnica < 1% lub < 1 PLN
            $amountMatch = $amountDiff !== null && ($amountDiff < 1.0 || ($refAmount > 0 && $amountDiff / $refAmount < 0.01));
            return [
                'id'               => (string)$tx->id,
                'value_date'       => $fmtDate($tx->value_date),
                'amount'           => $amount,
                'direction'        => (string)($tx->direction ?? 'C'),
                'party_name'       => (string)($tx->party_name ?? ''),
                'title'            => (string)($tx->title ?? ''),
                'match_status'     => (string)($tx->match_status ?? 'unmatched'),
                'match_confidence' => (int)($tx->match_confidence ?? 0),
                'match_reason'     => (string)($tx->match_reason ?? ''),
                'parsed_inv'       => (string)($tx->parsed_inv ?? ''),
                'amount_match'     => $amountMatch,
                'amount_diff'      => $amountDiff !== null ? round($amountDiff, 2) : null,
                'legacy'           => true,
            ];
        };

        // Kandydaci: przelewy pasujące do nazwy/NIP kontrahenta
        $nameOrConditions = [];

        if ($contractorName !== null && $contractorName !== '') {
            $stopWords   = ['sp', 'zoo', 'o.o', 'ltd', 'gmbh', 's.a', 'z', 'i', 'oraz', 'the'];
            $words       = preg_split('/[\s\.,]+/', mb_strtolower($contractorName));
            $significant = array_values(array_filter($words, fn($w) => strlen($w) >= 3 && !in_array($w, $stopWords, true)));
            foreach (array_slice($significant, 0, 3) as $word) {
                $nameOrConditions[] = ['BankTransactions.party_name LIKE' => '%' . $word . '%'];
            }
        }

        if (empty($nameOrConditions) && $nip !== null && $nip !== '') {
            $nameOrConditions[] = ['BankTransactions.parsed_nip' => $nip];
        } elseif ($nip !== null && $nip !== '') {
            $nameOrConditions[] = ['BankTransactions.parsed_nip' => $nip];
        }

        $rawDate = $invoice->date;
        if ($rawDate instanceof \DateTimeInterface) {
            $invoiceDateStr = $rawDate->format('Y-m-d');
        } elseif ($rawDate) {
            $s  = substr((string)$rawDate, 0, 10);
            $dt = \DateTime::createFromFormat('d.m.Y', $s) ?: \DateTime::createFromFormat('Y-m-d', $s);
            $invoiceDateStr = $dt ? $dt->format('Y-m-d') : '';
        } else {
            $invoiceDateStr = '';
        }

        $candidates = [];
        if (!empty($nameOrConditions)) {
            $conditions = [
                'BankTransactions.company_id' => $companyId,
                'OR'                          => $nameOrConditions,
            ];
            if ($invoiceDateStr !== '') {
                $conditions['BankTransactions.value_date >='] = $invoiceDateStr;
            }
            $candidates = $BankTransactions->find()
                ->where($conditions)
                ->where(['BankTransactions.direction' => 'C'])
                ->select(['id', 'value_date', 'amount', 'direction', 'party_name', 'title',
                          'match_status', 'match_confidence', 'match_reason', 'parsed_inv'])
                ->orderByDesc('value_date')
                ->limit(50)
                ->all()->toArray();
        }

        $mappedCandidates = array_map($mapTx, $candidates);

        // Sortuj: trafienia kwotowe na górze, potem wg daty desc
        usort($mappedCandidates, static function (array $a, array $b): int {
            if ($a['amount_match'] !== $b['amount_match']) {
                return $a['amount_match'] ? -1 : 1;
            }
            return strcmp($b['value_date'], $a['value_date']);
        });

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'nip'        => $nip,
                'contractor' => $contractorName,
                'legacy'     => true,
                'ref_amount' => $refAmount,
                'linked'     => [],
                'candidates' => $mappedCandidates,
            ]));
    }

    // ── Przelewy bankowe dla kontrahenta faktury (AJAX) ──────────────────────

    public function bankTransactions(string $invoiceId): Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        // Faktura + kontrahent
        $Invoices = $this->fetchTable('Invoices');
        $invoice  = $Invoices->find()
            ->contain([
                'InvoiceContractors' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'invoice_id', 'name', 'nip']);
                },
            ])
            ->select(['Invoices.id', 'Invoices.fullnumber', 'Invoices.total', 'Invoices.remaining', 'Invoices.currency', 'Invoices.date'])
            ->where(['Invoices.id' => $invoiceId, 'Invoices.company_id' => $companyId])
            ->first();

        if ($invoice === null) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Faktura nie istnieje lub brak uprawnień']));
        }

        $nip            = $invoice->invoice_contractor->nip ?? null;
        $contractorName = $invoice->invoice_contractor->name ?? null;

        $BankTransactions = $this->fetchTable('BankTransactions');

        $fmtDate = static function ($v): string {
            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
            return $v ? substr((string)$v, 0, 10) : '';
        };

        $mapTx = static function ($tx) use ($fmtDate): array {
            return [
                'id'               => (string)$tx->id,
                'value_date'       => $fmtDate($tx->value_date),
                'amount'           => (float)$tx->amount,
                'direction'        => (string)($tx->direction ?? 'C'),
                'party_name'       => (string)($tx->party_name ?? ''),
                'title'            => (string)($tx->title ?? ''),
                'match_status'     => (string)($tx->match_status ?? 'unmatched'),
                'match_confidence' => (int)($tx->match_confidence ?? 0),
                'match_reason'     => (string)($tx->match_reason ?? ''),
                'parsed_inv'       => (string)($tx->parsed_inv ?? ''),
            ];
        };

        // Transakcje już powiązane z tą fakturą
        $linked = $BankTransactions->find()
            ->where(['company_id' => $companyId, 'invoice_id' => $invoiceId])
            ->select(['id', 'value_date', 'amount', 'direction', 'party_name', 'title',
                      'match_status', 'match_confidence', 'match_reason', 'parsed_inv'])
            ->orderByDesc('value_date')
            ->all()->toArray();

        // Kandydaci — niedopasowane/proponowane pasujące do nazwy kontrahenta lub NIP
        $candidates = [];
        $linkedIds  = array_column($linked, 'id');

        // Buduj warunki wyszukiwania po nazwie (party_name LIKE) + opcjonalnie NIP
        $nameOrConditions = [];

        // Wyciągnij znaczące słowa z nazwy kontrahenta (dł. >= 3, pomijaj "sp.", "z", "o.o." itp.)
        if ($contractorName !== null && $contractorName !== '') {
            $stopWords = ['sp', 'zoo', 'o.o', 'ltd', 'gmbh', 's.a', 'z', 'i', 'oraz', 'the'];
            $words = preg_split('/[\s\.,]+/', mb_strtolower($contractorName));
            $significant = array_values(array_filter($words, fn($w) => strlen($w) >= 3 && !in_array($w, $stopWords, true)));
            foreach (array_slice($significant, 0, 3) as $word) {
                $nameOrConditions[] = ['BankTransactions.party_name LIKE' => '%' . $word . '%'];
            }
        }

        // Fallback na NIP jeśli nie ma nazwy
        if (empty($nameOrConditions) && $nip !== null && $nip !== '') {
            $nameOrConditions[] = ['BankTransactions.parsed_nip' => $nip];
        }

        // Data wystawienia faktury — przelew musi być >= tej daty
        // Normalizuj do Y-m-d niezależnie od formatu (DateTimeInterface lub string d.m.Y)
        $rawDate = $invoice->date;
        if ($rawDate instanceof \DateTimeInterface) {
            $invoiceDateStr = $rawDate->format('Y-m-d');
        } elseif ($rawDate) {
            $s  = substr((string)$rawDate, 0, 10);
            $dt = \DateTime::createFromFormat('d.m.Y', $s) ?: \DateTime::createFromFormat('Y-m-d', $s);
            $invoiceDateStr = $dt ? $dt->format('Y-m-d') : '';
        } else {
            $invoiceDateStr = '';
        }

        if (!empty($nameOrConditions)) {
            $conditions = [
                'BankTransactions.company_id'      => $companyId,
                'BankTransactions.match_status IN' => ['unmatched', 'proposed'],
                'OR'                               => $nameOrConditions,
            ];
            if ($invoiceDateStr !== '') {
                $conditions['BankTransactions.value_date >='] = $invoiceDateStr;
            }
            if (!empty($linkedIds)) {
                $conditions['BankTransactions.id NOT IN'] = $linkedIds;
            }
            $candidates = $BankTransactions->find()
                ->where($conditions)
                ->where(['BankTransactions.direction' => 'C'])
                ->select(['id', 'value_date', 'amount', 'direction', 'party_name', 'title',
                          'match_status', 'match_confidence', 'match_reason', 'parsed_inv'])
                ->orderByDesc('value_date')
                ->limit(30)
                ->all()->toArray();
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'nip'        => $nip,
                'contractor' => $contractorName,
                'linked'     => array_map($mapTx, $linked),
                'candidates' => array_map($mapTx, $candidates),
            ]));
    }

    // ── Dodaj wpłatę ─────────────────────────────────────────────────────────

    public function addPayment(): Response
    {
        $this->request->allowMethod(['post']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $invoiceId   = (string)($this->request->getData('invoice_id') ?? '');
        $amount      = (float)($this->request->getData('amount') ?? 0);
        $paymentDate = (string)($this->request->getData('payment_date') ?? date('Y-m-d'));
        $method      = (string)($this->request->getData('payment_method') ?? 'transfer');
        $description = (string)($this->request->getData('description') ?? '');

        if ($invoiceId === '' || $amount <= 0) {
            $this->Flash->error('Nieprawidłowe dane wpłaty.');
            return $this->redirect(['action' => 'index']);
        }

        $Invoices = $this->fetchTable('Invoices');
        $invoice  = $Invoices->find()
            ->where(['id' => $invoiceId, 'company_id' => $companyId])
            ->select(['id', 'fullnumber', 'currency'])
            ->first();

        if ($invoice === null) {
            $this->Flash->error('Faktura nie istnieje lub brak uprawnień.');
            return $this->redirect(['action' => 'index']);
        }

        $InvoicePayments = $this->fetchTable('InvoicePayments');
        $payment = $InvoicePayments->newEntity([
            'id'             => Text::uuid(),
            'invoice_id'     => $invoiceId,
            'payment_date'   => $paymentDate,
            'amount'         => $amount,
            'payment_method' => $method ?: 'transfer',
            'description'    => $description ?: null,
        ]);

        if ($InvoicePayments->save($payment)) {
            $this->_refreshPaymentState($invoiceId);
            $this->Flash->success(sprintf(
                'Wpłata %.2f %s zarejestrowana dla faktury %s.',
                $amount,
                h($invoice->currency ?? 'PLN'),
                h($invoice->fullnumber ?? $invoiceId)
            ));
        } else {
            $this->Flash->error('Nie udało się zapisać wpłaty: ' . implode(', ', array_keys($payment->getErrors())));
        }

        $redirect = $this->request->getData('redirect') ?? $this->referer(['action' => 'index']);
        return $this->redirect($redirect);
    }

    // ── Usuń wpłatę ──────────────────────────────────────────────────────────

    public function deletePayment(string $paymentId): Response
    {
        $this->request->allowMethod(['post']);

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $InvoicePayments = $this->fetchTable('InvoicePayments');
        $payment = $InvoicePayments->find()
            ->contain(['Invoices' => ['fields' => ['id', 'company_id']]])
            ->where(['InvoicePayments.id' => $paymentId])
            ->first();

        if ($payment === null || (string)$payment->invoice->company_id !== (string)$companyId) {
            $this->Flash->error('Wpłata nie istnieje lub brak uprawnień.');
            return $this->redirect($this->request->getData('redirect') ?? ['action' => 'index']);
        }

        $invoiceId = $payment->invoice_id;
        if ($InvoicePayments->delete($payment)) {
            $this->_refreshPaymentState($invoiceId);
            $this->Flash->success('Wpłata usunięta.');
        } else {
            $this->Flash->error('Nie udało się usunąć wpłaty.');
        }

        return $this->redirect($this->request->getData('redirect') ?? $this->referer(['action' => 'index']));
    }

    // ── Przelicz stan faktury po zmianie wpłat ───────────────────────────────

    private function _refreshPaymentState(string $invoiceId): void
    {
        $Invoices        = $this->fetchTable('Invoices');
        $InvoicePayments = $this->fetchTable('InvoicePayments');

        $invoice = $Invoices->get($invoiceId, ['fields' => ['id', 'total', 'paymentstate', 'alreadypaid', 'remaining']]);

        $paid = (float)($InvoicePayments->find()
            ->where(['invoice_id' => $invoiceId])
            ->select(['s' => $InvoicePayments->find()->func()->sum('amount')])
            ->first()?->s ?? 0);

        $total = (float)$invoice->total;

        $invoice->alreadypaid  = min($paid, $total);
        $invoice->remaining    = max(0.0, $total - $paid);
        $invoice->paymentstate = match (true) {
            $paid <= 0             => 'unpaid',
            $paid >= $total - 0.01 => 'paid',
            default                => 'partial',
        };

        $Invoices->save($invoice);
    }

    // ── Statystyki ───────────────────────────────────────────────────────────

    // ── Info o kontrahencie z bazy contractors (AJAX) ────────────────────────

    public function contractorInfo(string $invoiceId): Response
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        // Pobierz dane kontrahenta z faktury
        $invoice = $this->fetchTable('Invoices')->find()
            ->contain([
                'InvoiceContractors' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'invoice_id', 'name', 'nip', 'street', 'city', 'zip', 'country', 'email', 'phone']);
                },
            ])
            ->select(['Invoices.id', 'Invoices.fullnumber'])
            ->where(['Invoices.id' => $invoiceId, 'Invoices.company_id' => $companyId])
            ->first();

        if ($invoice === null) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Faktura nie istnieje']));
        }

        $ic = $invoice->invoice_contractor;
        if ($ic === null) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Brak danych kontrahenta na fakturze']));
        }

        // Szukaj w tabeli contractors po NIP + company
        $contractor = null;
        $contractorId = null;
        if (!empty($ic->nip)) {
            $found = $this->fetchTable('Contractors')->find()
                ->where(['company_id' => $companyId, 'nip' => $ic->nip])
                ->select(['id', 'name', 'nip', 'email', 'phone', 'street', 'city', 'postal_code', 'country', 'is_active'])
                ->first();

            if ($found !== null) {
                $contractorId = (string)$found->id;
                $contractor   = [
                    'id'          => $contractorId,
                    'name'        => (string)($found->name ?? ''),
                    'nip'         => (string)($found->nip ?? ''),
                    'email'       => (string)($found->email ?? ''),
                    'phone'       => (string)($found->phone ?? ''),
                    'street'      => (string)($found->street ?? ''),
                    'city'        => (string)($found->city ?? ''),
                    'postal_code' => (string)($found->postal_code ?? ''),
                    'country'     => (string)($found->country ?? ''),
                    'is_active'   => (bool)$found->is_active,
                ];
            }
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'invoice_id'   => $invoiceId,
                'fullnumber'   => (string)($invoice->fullnumber ?? ''),
                'invoice_contractor' => [
                    'name'    => (string)($ic->name ?? ''),
                    'nip'     => (string)($ic->nip ?? ''),
                    'street'  => (string)($ic->street ?? ''),
                    'city'    => (string)($ic->city ?? ''),
                    'zip'     => (string)($ic->zip ?? ''),
                    'country' => (string)($ic->country ?? ''),
                    'email'   => (string)($ic->email ?? ''),
                    'phone'   => (string)($ic->phone ?? ''),
                ],
                'contractor_id' => $contractorId,
                'contractor'    => $contractor,
            ]));
    }

    // ── Utwórz kontrahenta z danych faktury (AJAX POST) ──────────────────────

    public function createContractorFromInvoice(string $invoiceId): Response
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->disableAutoLayout();

        $companyId = $this->request->getAttribute('identity')?->get('company_id') ?? $this->currentCompanyId;

        $invoice = $this->fetchTable('Invoices')->find()
            ->contain([
                'InvoiceContractors' => function (\Cake\ORM\Query\SelectQuery $q) {
                    return $q->select(['id', 'invoice_id', 'name', 'nip', 'street', 'city', 'zip', 'country', 'email', 'phone', 'vat_prefix', 'vat_eu']);
                },
            ])
            ->select(['Invoices.id'])
            ->where(['Invoices.id' => $invoiceId, 'Invoices.company_id' => $companyId])
            ->first();

        if ($invoice === null) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Faktura nie istnieje']));
        }

        $ic = $invoice->invoice_contractor;
        if ($ic === null || empty($ic->name)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Brak danych kontrahenta na fakturze']));
        }

        $Contractors = $this->fetchTable('Contractors');

        // Sprawdź czy już istnieje (zabezpieczenie przed duplikate)
        if (!empty($ic->nip)) {
            $existing = $Contractors->find()
                ->where(['company_id' => $companyId, 'nip' => $ic->nip])
                ->select(['id'])
                ->first();

            if ($existing !== null) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode([
                        'success'       => true,
                        'contractor_id' => (string)$existing->id,
                        'message'       => 'Kontrahent już istnieje w bazie.',
                        'already_existed' => true,
                    ]));
            }
        }

        $contractor = $Contractors->newEntity([
            'id'          => \Cake\Utility\Text::uuid(),
            'company_id'  => $companyId,
            'name'        => $ic->name,
            'nip'         => $ic->nip ?: null,
            'vat_prefix'  => $ic->vat_prefix ?: null,
            'vat_eu'      => $ic->vat_eu ?: null,
            'email'       => $ic->email ?: null,
            'phone'       => $ic->phone ?: null,
            'street'      => $ic->street ?: null,
            'city'        => $ic->city ?: null,
            'postal_code' => $ic->zip ?: null,
            'country'     => $ic->country ?: 'PL',
            'is_active'   => true,
        ]);

        if (!$Contractors->save($contractor)) {
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'error'  => 'Nie udało się zapisać kontrahenta.',
                    'errors' => $contractor->getErrors(),
                ]));
        }

        return $this->response->withType('application/json')
            ->withStringBody(json_encode([
                'success'       => true,
                'contractor_id' => (string)$contractor->id,
                'message'       => 'Kontrahent został dodany do bazy.',
                'already_existed' => false,
            ]));
    }

    private function _computeStats(array $rows, string $today): array
    {
        $totalReceivables = 0.0;
        $totalPaid        = 0.0;
        $totalRemaining   = 0.0;
        $overdue          = 0.0;
        $overdueCount     = 0;

        foreach ($rows as $r) {
            // total/alreadypaid/remaining są zawsze w PLN (dla legacy: GLO_BRUTTO/GLO_ZL_ZAPLATA/POZOSTALO_PLN)
            $total     = (float)($r['total']       ?? 0);
            $remaining = (float)($r['remaining']   ?? 0);
            $paid      = (float)($r['alreadypaid'] ?? 0);
            $state     = (string)($r['paymentstate'] ?? 'unpaid');
            $pdate     = isset($r['paymentdate']) && $r['paymentdate']
                ? (string)(is_string($r['paymentdate'])
                    ? substr($r['paymentdate'], 0, 10)
                    : $r['paymentdate']->format('Y-m-d'))
                : '';

            $totalReceivables += $total;
            $totalPaid        += $paid;
            $totalRemaining   += $remaining;

            if ($state !== 'paid' && $pdate !== '' && $pdate < $today) {
                $overdue += $remaining;
                $overdueCount++;
            }
        }

        return [
            'count'            => count($rows),
            'totalReceivables' => $totalReceivables,
            'totalPaid'        => $totalPaid,
            'totalRemaining'   => $totalRemaining,
            'overdue'          => $overdue,
            'overdueCount'     => $overdueCount,
        ];
    }
}

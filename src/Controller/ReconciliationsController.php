<?php
declare(strict_types=1);

namespace App\Controller;

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

        // Status płatności
        if ($status === 'overdue') {
            $baseConditions['Invoices.paymentstate !='] = 'paid';
            $baseConditions['Invoices.paymentdate <']   = $today;
        } elseif (in_array($status, ['unpaid', 'partial', 'paid'], true)) {
            $baseConditions['Invoices.paymentstate'] = $status;
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

        // ── Statystyki (prosta agregacja SQL bez contain) ────────────────────
        $statsRows = $Invoices->find()
            ->select([
                'total'        => 'Invoices.total',
                'alreadypaid'  => 'Invoices.alreadypaid',
                'remaining'    => 'Invoices.remaining',
                'paymentstate' => 'Invoices.paymentstate',
                'paymentdate'  => 'Invoices.paymentdate',
            ])
            ->where($baseConditions)
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

        // ── Główne zapytanie z kontrahentem ───────────────────────────────────
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

            // ── Zlecenia Speed (data dostawy) ────────────────────────────────
            $speedByInvoice = [];
            $speedRows = $this->fetchTable('SpeedOrders')->find()
                ->where(['invoice_id IN' => $invoiceIds])
                ->select(['invoice_id', 'date_delivery', 'date_ship', 'symbol'])
                ->all()->toArray();

            foreach ($speedRows as $so) {
                $iid = (string)$so->invoice_id;
                $speedByInvoice[$iid] ??= $so;
            }
        } else {
            $speedByInvoice = [];
        }

        $this->set(compact(
            'invoices', 'total', 'pages', 'page', 'limit',
            'search', 'status', 'dateFrom', 'dateTo', 'typeFilter', 'sort', 'dir',
            'stats', 'bankByInvoice', 'speedByInvoice'
        ));
        $this->set('title', 'Rozliczenia');
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
            ->select(['Invoices.id', 'Invoices.fullnumber', 'Invoices.total', 'Invoices.remaining', 'Invoices.currency'])
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

        // Kandydaci — niedopasowane/proponowane dla tego samego NIP
        $candidates = [];
        if ($nip !== null && $nip !== '') {
            $linkedIds  = array_column($linked, 'id');
            $conditions = [
                'company_id'      => $companyId,
                'match_status IN' => ['unmatched', 'proposed'],
                'parsed_nip'      => $nip,
            ];
            if (!empty($linkedIds)) {
                $conditions['id NOT IN'] = $linkedIds;
            }
            $candidates = $BankTransactions->find()
                ->where($conditions)
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

    private function _computeStats(array $rows, string $today): array
    {
        $totalReceivables = 0.0;
        $totalPaid        = 0.0;
        $totalRemaining   = 0.0;
        $overdue          = 0.0;
        $overdueCount     = 0;

        foreach ($rows as $r) {
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

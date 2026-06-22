<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Ksef\N1KsefService;
use App\Service\Ksef\DbKsefTokenStorage;
use App\Service\Ksef\CertificateStorage;
use Cake\Log\Log;

/**
 * Faktury kosztowe (od przewoźników).
 *
 * Źródła:
 *  - KSeF received  — przeglądaj i importuj do bazy
 *  - Ręczne         — formularz + opcjonalny PDF
 *
 * Akcje:
 *  index        GET  /koszty
 *  view         GET  /koszty/view/{id}
 *  add          GET/POST /koszty/add
 *  edit         GET/POST /koszty/edit/{id}
 *  delete       POST /koszty/delete/{id}
 *  importKsef   GET  /koszty/import-ksef          — przeglądarka KSeF
 *  doImportKsef POST /koszty/do-import-ksef       — zapis wybranych z KSeF
 *  searchAjax   GET  /koszty/search               — AJAX wyszukiwarka (dla modalu w zleceniu)
 *  assignOrder  POST /koszty/assign-order         — przypisz FK do zlecenia
 *  unassignOrder POST /koszty/unassign-order      — odepnij FK od zlecenia
 */
class CostInvoicesController extends AppController
{
    // -------------------------------------------------------------------------
    // Lista FK
    // -------------------------------------------------------------------------
    public function index(): void
    {
        $this->request->allowMethod(['get']);

        $search       = trim((string)$this->request->getQuery('q', ''));
        $month        = trim((string)$this->request->getQuery('month', ''));   // YYYY-MM
        $status       = trim((string)$this->request->getQuery('status', ''));
        $source       = trim((string)$this->request->getQuery('source', ''));
        $paymentState = trim((string)$this->request->getQuery('payment_state', '')); // unpaid|partial|paid|overdue
        $hasOrder     = trim((string)$this->request->getQuery('has_order', ''));     // with|without
        $dateFrom     = trim((string)$this->request->getQuery('date_from', ''));     // issue_date >=
        $dateTo       = trim((string)$this->request->getQuery('date_to', ''));       // issue_date <=
        $contractorNip = trim((string)$this->request->getQuery('contractor_nip', ''));
        $page         = max(1, (int)$this->request->getQuery('page', 1));
        $limit        = 50;
        $today        = date('Y-m-d');

        $CI = $this->fetchTable('CostInvoices');
        $query = $CI->find()->orderByDesc('CostInvoices.issue_date');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(['OR' => [
                'CostInvoices.invoice_number LIKE'  => $like,
                'CostInvoices.contractor_name LIKE' => $like,
                'CostInvoices.contractor_nip LIKE'  => $like,
                'CostInvoices.ksef_number LIKE'     => $like,
            ]]);
        }
        if ($month  !== '') $query->where(['CostInvoices.accounting_month' => $month]);
        if ($status !== '') $query->where(['CostInvoices.status' => $status]);
        if ($source !== '') $query->where(['CostInvoices.source' => $source]);
        if ($contractorNip !== '') $query->where(['CostInvoices.contractor_nip' => $contractorNip]);
        if ($dateFrom !== '') $query->where(['CostInvoices.issue_date >=' => $dateFrom]);
        if ($dateTo   !== '') $query->where(['CostInvoices.issue_date <=' => $dateTo]);

        // Stan płatności
        if ($paymentState === 'paid') {
            $query->where(['CostInvoices.status' => 'paid']);
        } elseif ($paymentState === 'unpaid') {
            $query->where([
                'CostInvoices.status !=' => 'paid',
                'CostInvoices.paid_amount' => 0,
            ]);
        } elseif ($paymentState === 'partial') {
            $query->where([
                'CostInvoices.status !=' => 'paid',
                'CostInvoices.paid_amount >' => 0,
            ]);
        } elseif ($paymentState === 'overdue') {
            $query->where([
                'CostInvoices.status !=' => 'paid',
                'CostInvoices.payment_date IS NOT' => null,
                'CostInvoices.payment_date <' => $today,
            ]);
        }

        // Filtr powiązania ze zleceniem
        if ($hasOrder === 'with') {
            $sub = $this->fetchTable('CostInvoiceOrders')->find()
                ->select(['cost_invoice_id'])->distinct(['cost_invoice_id']);
            $query->where(['CostInvoices.id IN' => $sub]);
        } elseif ($hasOrder === 'without') {
            $sub = $this->fetchTable('CostInvoiceOrders')->find()
                ->select(['cost_invoice_id'])->distinct(['cost_invoice_id']);
            $query->where(['CostInvoices.id NOT IN' => $sub]);
        }

        $total = (clone $query)->count();
        // Statystyki przed paginacją — sklonowane zapytanie zachowuje filtry
        // ale jeszcze nie ma limit/offset.
        $statsQuery = clone $query;
        $pages = max(1, (int)ceil($total / $limit));
        $page  = min($page, $pages);
        $invoices = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        // Miesiące dostępne do filtra
        $months = $CI->find()
            ->select(['accounting_month'])
            ->where(['accounting_month IS NOT' => null])
            ->groupBy('accounting_month')
            ->orderByDesc('accounting_month')
            ->all()
            ->map(fn($r) => $r->accounting_month)
            ->toArray();

        // Lista kontrahentów (top 50 po liczbie FK)
        $contractors = [];
        try {
            $db = $CI->getConnection();
            $rows = $db->execute(
                "SELECT contractor_nip, contractor_name, COUNT(*) AS cnt
                 FROM cost_invoices
                 WHERE contractor_nip IS NOT NULL AND contractor_nip != ''
                 GROUP BY contractor_nip
                 ORDER BY cnt DESC, contractor_name ASC
                 LIMIT 50"
            )->fetchAll('assoc');
            $contractors = $rows ?: [];
        } catch (\Throwable) { /* ignore */ }

        // Statystyki — agregaty z całego filtra (bez limit/offset)
        $stats = $this->_buildCostInvoiceStats($statsQuery, $today);

        // Mapa: cost_invoice_id => liczba powiązanych zleceń (dla bieżącej strony)
        $invIds = array_map(fn($i) => $i->id, $invoices->toArray());
        $orderCounts = [];
        if (!empty($invIds)) {
            try {
                $rows = $this->fetchTable('CostInvoiceOrders')->find()
                    ->select(['cost_invoice_id', 'cnt' => $this->fetchTable('CostInvoiceOrders')->find()->func()->count('*')])
                    ->where(['cost_invoice_id IN' => $invIds])
                    ->groupBy('cost_invoice_id')
                    ->all();
                foreach ($rows as $r) {
                    $orderCounts[(int)$r->cost_invoice_id] = (int)$r->cnt;
                }
            } catch (\Throwable) { /* ignore */ }
        }

        $this->set(compact('invoices', 'total', 'page', 'pages', 'limit',
            'search', 'month', 'status', 'source', 'paymentState', 'hasOrder',
            'dateFrom', 'dateTo', 'contractorNip',
            'months', 'contractors', 'stats', 'orderCounts', 'today'));
    }

    /**
     * Agregaty dla bieżącego filtra (bez paginacji): count, sumy brutto/paid/remaining
     * i podział po stanach (paid/unpaid/partial/overdue) w grupach walutowych.
     */
    private function _buildCostInvoiceStats($query, string $today): array
    {
        try {
            $rows = $query->select([
                'currency', 'status', 'brutto', 'paid_amount', 'payment_date',
            ], true /* override */)->disableAutoFields()->all();
        } catch (\Throwable) {
            return ['count' => 0, 'total_pln' => 0, 'total_eur' => 0];
        }

        $s = [
            'count'         => 0,
            'total_pln'     => 0.0,
            'total_eur'     => 0.0,
            'paid_pln'      => 0.0,
            'paid_eur'      => 0.0,
            'remaining_pln' => 0.0,
            'remaining_eur' => 0.0,
            'overdue_count' => 0,
            'overdue_pln'   => 0.0,
            'overdue_eur'   => 0.0,
        ];

        foreach ($rows as $r) {
            $cur = strtoupper((string)($r->currency ?? 'PLN'));
            $key = $cur === 'EUR' ? 'eur' : 'pln';
            $brutto = (float)($r->brutto ?? 0);
            $paid   = (float)($r->paid_amount ?? 0);
            $rem    = max(0, round($brutto - $paid, 2));
            $isPaid = $r->status === 'paid';

            $s['count']++;
            $s['total_' . $key]     += $brutto;
            $s['paid_' . $key]      += $paid;
            $s['remaining_' . $key] += $rem;

            if (!$isPaid && !empty($r->payment_date)) {
                $pd = $r->payment_date instanceof \DateTimeInterface
                    ? $r->payment_date->format('Y-m-d')
                    : substr((string)$r->payment_date, 0, 10);
                if ($pd < $today) {
                    $s['overdue_count']++;
                    $s['overdue_' . $key] += $rem;
                }
            }
        }
        foreach (['total_pln', 'total_eur', 'paid_pln', 'paid_eur', 'remaining_pln', 'remaining_eur', 'overdue_pln', 'overdue_eur'] as $k) {
            $s[$k] = round($s[$k], 2);
        }
        return $s;
    }

    // -------------------------------------------------------------------------
    // Szczegóły FK
    // -------------------------------------------------------------------------
    public function view(int $id): void
    {
        $this->request->allowMethod(['get']);

        $CI = $this->fetchTable('CostInvoices');
        $invoice = $CI->get($id, contain: [
            'SpeedOrders',
            'CostInvoicePayments' => function ($q) {
                return $q->orderByDesc('payment_date')->orderByDesc('created')
                         ->contain(['BankTransactions' => function ($qb) {
                             return $qb->select(['id', 'value_date', 'booking_date', 'amount', 'currency', 'party_name', 'title']);
                         }, 'Users' => function ($qu) {
                             return $qu->select(['id', 'first_name', 'last_name', 'email']);
                         }]);
            },
        ]);

        $this->set(compact('invoice'));
    }

    // -------------------------------------------------------------------------
    // Dodaj FK ręcznie
    // -------------------------------------------------------------------------
    public function add(): ?\Cake\Http\Response
    {
        $CI = $this->fetchTable('CostInvoices');
        $invoice = $CI->newEmptyEntity();

        // Domyślny miesiąc rozliczeniowy = bieżący
        $invoice->set('accounting_month', date('Y-m'));
        $invoice->set('source', 'manual');
        $invoice->set('status', 'received');
        $invoice->set('currency', 'PLN');
        $invoice->set('receipt_date', date('Y-m-d'));

        if ($this->request->is('post')) {
            $data = (array)$this->request->getData();

            // Upload PDF
            $pdf = $this->request->getUploadedFile('pdf_file');
            if ($pdf && $pdf->getError() === UPLOAD_ERR_OK) {
                $ext      = strtolower(pathinfo((string)$pdf->getClientFilename(), PATHINFO_EXTENSION));
                $filename = 'cost_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dir      = WWW_ROOT . 'files' . DS . 'cost_invoices' . DS;
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $pdf->moveTo($dir . $filename);
                $data['pdf_path'] = 'files/cost_invoices/' . $filename;
            }
            unset($data['pdf_file']);

            $invoice = $CI->patchEntity($invoice, $data);
            if ($CI->save($invoice)) {
                $this->Flash->success('Faktura kosztowa została zapisana.');
                return $this->redirect(['action' => 'view', $invoice->id]);
            }
            $this->Flash->error('Błąd zapisu. Sprawdź formularz.');
        }

        $this->set(compact('invoice'));
        return null;
    }

    // -------------------------------------------------------------------------
    // Edytuj FK
    // -------------------------------------------------------------------------
    public function edit(int $id): ?\Cake\Http\Response
    {
        $CI = $this->fetchTable('CostInvoices');
        $invoice = $CI->get($id);

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = (array)$this->request->getData();

            $pdf = $this->request->getUploadedFile('pdf_file');
            if ($pdf && $pdf->getError() === UPLOAD_ERR_OK) {
                $ext      = strtolower(pathinfo((string)$pdf->getClientFilename(), PATHINFO_EXTENSION));
                $filename = 'cost_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dir      = WWW_ROOT . 'files' . DS . 'cost_invoices' . DS;
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $pdf->moveTo($dir . $filename);
                $data['pdf_path'] = 'files/cost_invoices/' . $filename;
            }
            unset($data['pdf_file']);

            $invoice = $CI->patchEntity($invoice, $data);
            if ($CI->save($invoice)) {
                $this->Flash->success('Zapisano zmiany.');
                return $this->redirect(['action' => 'view', $invoice->id]);
            }
            $this->Flash->error('Błąd zapisu.');
        }

        $this->set(compact('invoice'));
        return null;
    }

    // -------------------------------------------------------------------------
    // Usuń FK
    // -------------------------------------------------------------------------
    public function delete(int $id): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $CI = $this->fetchTable('CostInvoices');
        $invoice = $CI->get($id);
        if ($CI->delete($invoice)) {
            $this->Flash->success('Faktura kosztowa usunięta.');
        } else {
            $this->Flash->error('Nie udało się usunąć.');
        }
        return $this->redirect(['action' => 'index']);
    }

    // -------------------------------------------------------------------------
    // Przeglądarka KSeF received → import
    // -------------------------------------------------------------------------
    public function importKsef(): void
    {
        $this->request->allowMethod(['get']);

        $identity  = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        // Pobierz listę z KSeF (przez istniejący serwis)
        $ksefInvoices = [];
        $ksefError    = null;
        $ksefTotal    = 0;
        $ksefPage     = max(1, (int)$this->request->getQuery('page', 1));

        // Środowisko: default 'prod' (tak jak w KsefAuthorizationsController::received).
        // BEZ tego buildReceivedApiResult schodzi do 'test', a certyfikaty są w 'prod'
        // → błąd 21115 "Nieprawidłowy certyfikat".
        $ksefEnv = (string)$this->request->getQuery('env', 'prod');
        $ksefEnv = ($ksefEnv === 'test') ? 'test' : 'prod';

        if ($companyId !== '') {
            try {
                $ksef   = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
                $result = $ksef->buildReceivedApiResult($companyId, array_merge(
                    $this->request->getQueryParams(),
                    ['page' => $ksefPage, 'env' => $ksefEnv]
                ));
                $payload = $result['payload'] ?? [];
                if (!empty($payload['success'])) {
                    $ksefInvoices = $payload['invoices'] ?? [];
                    $ksefTotal    = (int)($payload['total'] ?? 0);
                } else {
                    $ksefError = $payload['error'] ?? 'Błąd KSeF';
                }
            } catch (\Throwable $e) {
                $ksefError = $e->getMessage();
            }
        }

        // Sprawdź które ksef_number są już w bazie
        $CI = $this->fetchTable('CostInvoices');
        $existingKsefNumbers = [];
        if (!empty($ksefInvoices)) {
            $nums = array_filter(array_column($ksefInvoices, 'ksef_number'));
            if (!empty($nums)) {
                $existingKsefNumbers = $CI->find()
                    ->where(['ksef_number IN' => $nums])
                    ->all()
                    ->map(fn($r) => $r->ksef_number)
                    ->toArray();
                $existingKsefNumbers = array_flip($existingKsefNumbers);
            }
        }

        $this->set(compact('ksefInvoices', 'ksefError', 'ksefTotal', 'ksefPage', 'existingKsefNumbers', 'ksefEnv'));
    }

    // -------------------------------------------------------------------------
    // Zapisz wybrane faktury z KSeF do bazy
    // -------------------------------------------------------------------------
    public function doImportKsef(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $items = (array)($this->request->getData('items') ?? []);
        if (empty($items)) {
            $this->jsonResp(['success' => false, 'error' => 'Brak danych.']);
            return;
        }

        $CI      = $this->fetchTable('CostInvoices');
        $saved   = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($items as $item) {
            $ksefNumber = trim((string)($item['ksef_number'] ?? ''));
            if ($ksefNumber === '') { $skipped++; continue; }

            // Nie importuj duplikatów
            if ($CI->find()->where(['ksef_number' => $ksefNumber])->count() > 0) {
                $skipped++;
                continue;
            }

            $issueDate = trim((string)($item['date'] ?? ''));
            $accMonth  = $issueDate !== '' ? substr($issueDate, 0, 7) : date('Y-m');

            $entity = $CI->newEntity([
                'source'           => 'ksef',
                'ksef_number'      => $ksefNumber,
                'invoice_number'   => trim((string)($item['fullnumber'] ?? '')),
                'contractor_name'  => trim((string)($item['InvoiceContractors']['name'] ?? '')),
                'contractor_nip'   => trim((string)($item['InvoiceContractors']['tax_id'] ?? '')),
                'issue_date'       => $issueDate ?: null,
                'receipt_date'     => date('Y-m-d'),
                'accounting_month' => $accMonth,
                'brutto'           => (float)($item['total'] ?? 0),
                'netto'            => 0.0,
                'vat'              => 0.0,
                'currency'         => strtoupper(trim((string)($item['currency'] ?? 'PLN'))) ?: 'PLN',
                'status'           => 'received',
                'ksef_raw_json'    => json_encode($item, JSON_UNESCAPED_UNICODE),
            ]);

            if ($CI->save($entity)) {
                $saved++;
            } else {
                $errors[] = 'Błąd zapisu: ' . $ksefNumber;
            }
        }

        $this->jsonResp(['success' => true, 'saved' => $saved, 'skipped' => $skipped, 'errors' => $errors]);
    }

    // -------------------------------------------------------------------------
    // AJAX: wyszukiwarka FK (dla modalu w zleceniu)
    // GET /koszty/search?q=...&month=...
    // -------------------------------------------------------------------------
    public function searchAjax(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['get']);

        $q     = trim((string)$this->request->getQuery('q', ''));
        $month = trim((string)$this->request->getQuery('month', ''));

        $CI = $this->fetchTable('CostInvoices');
        $query = $CI->find()->orderByDesc('issue_date')->limit(30);

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(['OR' => [
                'invoice_number LIKE'  => $like,
                'contractor_name LIKE' => $like,
                'contractor_nip LIKE'  => $like,
                'ksef_number LIKE'     => $like,
            ]]);
        }
        if ($month !== '') {
            $query->where(['accounting_month' => $month]);
        }

        $results = array_map(fn($r) => [
            'id'               => $r->id,
            'invoice_number'   => $r->invoice_number,
            'ksef_number'      => $r->ksef_number,
            'contractor_name'  => $r->contractor_name,
            'contractor_nip'   => $r->contractor_nip,
            'issue_date'       => $r->issue_date instanceof \DateTimeInterface
                                    ? $r->issue_date->format('Y-m-d')
                                    : substr((string)($r->issue_date ?? ''), 0, 10),
            'accounting_month' => $r->accounting_month,
            'brutto'           => (float)($r->brutto ?? 0),
            'currency'         => $r->currency,
            'status'           => $r->status,
            'source'           => $r->source,
        ], $query->all()->toArray());

        $this->jsonResp(['success' => true, 'results' => $results]);
    }

    // -------------------------------------------------------------------------
    // Przypisz FK do zlecenia (AJAX POST)
    // { cost_invoice_id: X, speed_order_id: Y }
    // -------------------------------------------------------------------------
    public function assignOrder(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $ciId  = (int)$this->request->getData('cost_invoice_id', 0);
        $ordId = (int)$this->request->getData('speed_order_id', 0);

        if ($ciId === 0 || $ordId === 0) {
            $this->jsonResp(['success' => false, 'error' => 'Brak danych.']);
            return;
        }

        $CI     = $this->fetchTable('CostInvoices');
        $Orders = $this->fetchTable('SpeedOrders');
        $Pivot  = $this->fetchTable('CostInvoiceOrders');

        $ci    = $CI->find()->where(['id' => $ciId])->first();
        $order = $Orders->find()->where(['id' => $ordId])->first();

        if (!$ci || !$order) {
            $this->jsonResp(['success' => false, 'error' => 'Nie znaleziono rekordu.']);
            return;
        }

        // Sprawdź czy już przypisane
        if ($Pivot->find()->where(['cost_invoice_id' => $ciId, 'speed_order_id' => $ordId])->count() > 0) {
            $this->jsonResp(['success' => false, 'error' => 'Faktura już jest przypisana do tego zlecenia.']);
            return;
        }

        // Zapisz pivot
        $pivot = $Pivot->newEntity(['cost_invoice_id' => $ciId, 'speed_order_id' => $ordId]);
        if (!$Pivot->save($pivot)) {
            $this->jsonResp(['success' => false, 'error' => 'Błąd zapisu.']);
            return;
        }

        // Ustaw fk_at na zleceniu jeśli puste
        $now = date('Y-m-d H:i:s');
        if (empty($order->fk_at)) {
            $order->set('fk_at', $now);
            // Automatyczne przejście statusu
            $this->applyAutoNlStatus($order);
            $Orders->save($order);
        }

        $this->jsonResp([
            'success'           => true,
            'cost_invoice_id'   => $ciId,
            'speed_order_id'    => $ordId,
            'invoice_number'    => $ci->invoice_number ?: $ci->ksef_number,
            'contractor_name'   => $ci->contractor_name,
            'fk_at'             => $order->fk_at ? (string)$order->fk_at : $now,
        ]);
    }

    // -------------------------------------------------------------------------
    // Odepnij FK od zlecenia (AJAX POST)
    // { cost_invoice_id: X, speed_order_id: Y }
    // -------------------------------------------------------------------------
    public function unassignOrder(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $ciId  = (int)$this->request->getData('cost_invoice_id', 0);
        $ordId = (int)$this->request->getData('speed_order_id', 0);

        $Pivot  = $this->fetchTable('CostInvoiceOrders');
        $Orders = $this->fetchTable('SpeedOrders');

        $pivot = $Pivot->find()->where(['cost_invoice_id' => $ciId, 'speed_order_id' => $ordId])->first();
        if (!$pivot) {
            $this->jsonResp(['success' => false, 'error' => 'Nie znaleziono przypisania.']);
            return;
        }

        $Pivot->delete($pivot);

        // Jeśli to było ostatnie przypisanie FK dla zlecenia — wyczyść fk_at
        $remaining = $Pivot->find()->where(['speed_order_id' => $ordId])->count();
        if ($remaining === 0) {
            $order = $Orders->find()->where(['id' => $ordId])->first();
            if ($order) {
                $order->set('fk_at', null);
                $this->applyAutoNlStatus($order);
                $Orders->save($order);
            }
        }

        $this->jsonResp(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // Zmień status FK (received → verified → paid)
    // Przy ustawieniu 'paid' i pustym paid_at — automatycznie ustaw paid_at=dziś
    // i paid_amount=brutto (jeśli paid_amount było 0).
    // -------------------------------------------------------------------------
    public function setStatus(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $id     = (int)$this->request->getData('id', 0);
        $status = trim((string)$this->request->getData('status', ''));
        $allowed = ['received', 'verified', 'paid'];

        if (!in_array($status, $allowed, true)) {
            $this->jsonResp(['success' => false, 'error' => 'Nieprawidłowy status.']);
            return;
        }

        $CI = $this->fetchTable('CostInvoices');
        $ci = $CI->find()->where(['id' => $id])->first();
        if (!$ci) {
            $this->jsonResp(['success' => false, 'error' => 'Nie znaleziono.']);
            return;
        }

        $ci->set('status', $status);

        // Przy paid: zapisz datę i pełną kwotę jeśli brak
        if ($status === 'paid') {
            if (empty($ci->paid_at)) {
                $ci->set('paid_at', date('Y-m-d'));
            }
            if ((float)($ci->paid_amount ?? 0) <= 0) {
                $ci->set('paid_amount', (float)$ci->brutto);
            }
        }
        // Przy zmianie z paid na inny: NIE czyścimy paid_at/paid_amount —
        // user może to zrobić jawnie przez unmarkPaid, bez utraty historii.

        if ($CI->save($ci)) {
            $this->jsonResp(['success' => true, 'status' => $status]);
        } else {
            $this->jsonResp(['success' => false, 'error' => 'Błąd zapisu.']);
        }
    }

    // -------------------------------------------------------------------------
    // Oznacz fakturę jako zapłaconą z konkretną datą i kwotą
    // POST /koszty/mark-paid
    // body: { id, paid_at (YYYY-MM-DD), paid_amount, payment_method? }
    // -------------------------------------------------------------------------
    public function markPaid(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $id = (int)$this->request->getData('id', 0);
        $paidAt = trim((string)$this->request->getData('paid_at', ''));
        $paidAmount = (float)$this->request->getData('paid_amount', 0);
        $method = trim((string)$this->request->getData('payment_method', ''));

        if ($paidAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt)) {
            $this->jsonResp(['success' => false, 'error' => 'Nieprawidłowy format daty.']);
            return;
        }
        $allowedMethods = ['transfer', 'cash', 'card', 'compensation', 'other', ''];
        if (!in_array($method, $allowedMethods, true)) {
            $this->jsonResp(['success' => false, 'error' => 'Nieprawidłowa metoda płatności.']);
            return;
        }

        $CI = $this->fetchTable('CostInvoices');
        $ci = $CI->find()->where(['id' => $id])->first();
        if (!$ci) {
            $this->jsonResp(['success' => false, 'error' => 'Nie znaleziono.']);
            return;
        }

        $brutto = (float)($ci->brutto ?? 0);
        if ($paidAmount <= 0) {
            $paidAmount = $brutto; // default: cała kwota
        }
        // Clamp do brutto + tolerancja groszowa
        if ($paidAmount > $brutto + 0.01 && $brutto > 0) {
            $paidAmount = $brutto;
        }

        $ci->set('paid_at', $paidAt ?: date('Y-m-d'));
        $ci->set('paid_amount', $paidAmount);
        if ($method !== '') {
            $ci->set('payment_method', $method);
        }
        // Status: full paid → 'paid', częściowo → zostaw verified
        $isFullPaid = $brutto > 0 && abs($paidAmount - $brutto) < 0.01;
        if ($isFullPaid) {
            $ci->set('status', 'paid');
        } elseif ($ci->status === 'received') {
            $ci->set('status', 'verified');
        }

        if ($CI->save($ci)) {
            $this->jsonResp([
                'success'        => true,
                'status'         => $ci->status,
                'paid_at'        => $ci->paid_at instanceof \DateTimeInterface
                                       ? $ci->paid_at->format('Y-m-d')
                                       : substr((string)$ci->paid_at, 0, 10),
                'paid_amount'    => (float)$ci->paid_amount,
                'is_full_paid'   => $isFullPaid,
            ]);
        } else {
            $this->jsonResp(['success' => false, 'error' => 'Błąd zapisu.']);
        }
    }

    // -------------------------------------------------------------------------
    // Cofnij oznaczenie jako zapłacona — wyczyść paid_at / paid_amount,
    // status → verified.
    // POST /koszty/unmark-paid { id }
    // -------------------------------------------------------------------------
    public function unmarkPaid(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $id = (int)$this->request->getData('id', 0);
        $CI = $this->fetchTable('CostInvoices');
        $ci = $CI->find()->where(['id' => $id])->first();
        if (!$ci) {
            $this->jsonResp(['success' => false, 'error' => 'Nie znaleziono.']);
            return;
        }

        $ci->set('paid_at', null);
        $ci->set('paid_amount', 0.00);
        $ci->set('payment_method', null);
        $ci->set('status', 'verified');

        if ($CI->save($ci)) {
            $this->jsonResp(['success' => true, 'status' => 'verified']);
        } else {
            $this->jsonResp(['success' => false, 'error' => 'Błąd zapisu.']);
        }
    }

    // -------------------------------------------------------------------------
    // Dodaj wpłatę do faktury kosztowej
    // POST /koszty/{id}/add-payment
    // body: { payment_date, amount, payment_method, note, bank_transaction_id? }
    // -------------------------------------------------------------------------
    public function addPayment(int $id): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $CI = $this->fetchTable('CostInvoices');
        $ci = $CI->find()->where(['id' => $id])->first();
        if (!$ci) { $this->jsonResp(['success' => false, 'error' => 'Nie znaleziono faktury.']); return; }

        $paymentDate = trim((string)$this->request->getData('payment_date'));
        $amount      = (float)$this->request->getData('amount', 0);
        $method      = trim((string)$this->request->getData('payment_method', ''));
        $note        = trim((string)$this->request->getData('note', ''));
        $bankTxId    = trim((string)$this->request->getData('bank_transaction_id', ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            $this->jsonResp(['success' => false, 'error' => 'Nieprawidłowy format daty.']); return;
        }
        if ($amount <= 0) {
            $this->jsonResp(['success' => false, 'error' => 'Kwota musi być > 0.']); return;
        }
        $allowedMethods = ['transfer', 'cash', 'card', 'compensation', 'other', ''];
        if (!in_array($method, $allowedMethods, true)) {
            $this->jsonResp(['success' => false, 'error' => 'Nieprawidłowa metoda.']); return;
        }
        if ($bankTxId !== '' && !preg_match('/^[0-9a-f-]{36}$/i', $bankTxId)) {
            $this->jsonResp(['success' => false, 'error' => 'Nieprawidłowy ID przelewu.']); return;
        }

        $userId = $this->request->getAttribute('identity')?->get('id');

        $CIP = $this->fetchTable('CostInvoicePayments');
        $payment = $CIP->newEntity([
            'id'                  => \Cake\Utility\Text::uuid(),
            'cost_invoice_id'     => $id,
            'payment_date'        => $paymentDate,
            'amount'              => $amount,
            'currency'            => (string)$ci->currency ?: 'PLN',
            'payment_method'      => $method ?: null,
            'payment_type'        => $bankTxId !== '' ? 'bank' : 'manual',
            'bank_transaction_id' => $bankTxId ?: null,
            'user_id'             => $userId,
            'note'                => $note ?: null,
        ]);
        if (!$CIP->save($payment)) {
            $this->jsonResp(['success' => false, 'error' => 'Błąd zapisu: ' . json_encode($payment->getErrors())]);
            return;
        }

        // Przelicz paid_amount + status
        $newPaid = $this->_recalcCostInvoicePayments($id);

        $this->jsonResp([
            'success'     => true,
            'payment_id'  => (string)$payment->id,
            'paid_amount' => $newPaid['paid_amount'],
            'remaining'   => $newPaid['remaining'],
            'status'      => $newPaid['status'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Usuń wpłatę
    // POST /koszty/payment/{id}/delete
    // -------------------------------------------------------------------------
    public function deletePayment(string $paymentId): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $CIP = $this->fetchTable('CostInvoicePayments');
        $p   = $CIP->find()->where(['id' => $paymentId])->first();
        if (!$p) { $this->jsonResp(['success' => false, 'error' => 'Wpłata nie istnieje.']); return; }

        $costInvoiceId = (int)$p->cost_invoice_id;
        if (!$CIP->delete($p)) {
            $this->jsonResp(['success' => false, 'error' => 'Błąd usuwania.']); return;
        }

        $newPaid = $this->_recalcCostInvoicePayments($costInvoiceId);

        $this->jsonResp([
            'success'     => true,
            'paid_amount' => $newPaid['paid_amount'],
            'remaining'   => $newPaid['remaining'],
            'status'      => $newPaid['status'],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /koszty/{id}/bank-transactions
    // Zwraca listę pasujących wypłat (direction=D) — kandydatów do alokacji.
    // Match po: parsed_nip = contractor_nip || party_name contains contractor_name
    // -------------------------------------------------------------------------
    public function bankTxForCost(int $id): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['get']);

        $CI = $this->fetchTable('CostInvoices');
        $ci = $CI->find()->where(['id' => $id])->first();
        if (!$ci) { $this->jsonResp(['success' => false, 'error' => 'Nie znaleziono.']); return; }

        $companyId = $this->request->getAttribute('identity')?->get('company_id');
        if (!$companyId) { $this->jsonResp(['success' => false, 'error' => 'Brak company_id.']); return; }

        // ID już dopiętych przelewów (żeby nie pokazywać duplikatów)
        $alreadyUsed = $this->fetchTable('CostInvoicePayments')->find()
            ->where(['cost_invoice_id' => $id, 'bank_transaction_id IS NOT' => null])
            ->all()
            ->map(fn($r) => (string)$r->bank_transaction_id)
            ->toArray();

        $BankTx = $this->fetchTable('BankTransactions');
        $q = $BankTx->find()
            ->where([
                'BankTransactions.company_id' => $companyId,
                'BankTransactions.direction'  => 'D', // tylko wypłaty
            ])
            ->select(['BankTransactions.id', 'BankTransactions.value_date', 'BankTransactions.booking_date',
                      'BankTransactions.amount', 'BankTransactions.currency',
                      'BankTransactions.party_name', 'BankTransactions.title',
                      'BankTransactions.parsed_nip'])
            ->orderByDesc('BankTransactions.value_date')
            ->limit(50);

        if (!empty($alreadyUsed)) {
            $q->where(['BankTransactions.id NOT IN' => $alreadyUsed]);
        }

        // Heurystyka dopasowania kontrahenta
        $nip = trim((string)($ci->contractor_nip ?? ''));
        $name = trim((string)($ci->contractor_name ?? ''));
        $or = [];
        if ($nip !== '') {
            $or['BankTransactions.parsed_nip'] = preg_replace('/\D/', '', $nip);
        }
        if ($name !== '') {
            $or['BankTransactions.party_name LIKE'] = '%' . $name . '%';
        }
        if (!empty($or)) {
            $q->where(['OR' => $or]);
        }

        $rows = [];
        foreach ($q->all() as $tx) {
            $vd = $tx->value_date instanceof \DateTimeInterface ? $tx->value_date->format('Y-m-d') : substr((string)$tx->value_date, 0, 10);
            $bd = $tx->booking_date instanceof \DateTimeInterface ? $tx->booking_date->format('Y-m-d') : substr((string)$tx->booking_date, 0, 10);
            $rows[] = [
                'id'           => (string)$tx->id,
                'value_date'   => $vd,
                'booking_date' => $bd,
                'amount'       => (float)$tx->amount,
                'currency'     => (string)$tx->currency,
                'party_name'   => (string)($tx->party_name ?? ''),
                'title'        => (string)($tx->title ?? ''),
                'parsed_nip'   => (string)($tx->parsed_nip ?? ''),
            ];
        }

        $this->jsonResp([
            'success' => true,
            'results' => $rows,
            'brutto'  => (float)$ci->brutto,
            'currency'=> (string)$ci->currency,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Przelicza paid_amount na cost_invoices na podstawie sumy z
     * cost_invoice_payments + dostosowuje status (paid/verified/received).
     */
    private function _recalcCostInvoicePayments(int $costInvoiceId): array
    {
        $CI = $this->fetchTable('CostInvoices');
        $CIP = $this->fetchTable('CostInvoicePayments');

        $ci = $CI->find()->where(['id' => $costInvoiceId])->first();
        if (!$ci) {
            return ['paid_amount' => 0.0, 'remaining' => 0.0, 'status' => 'received'];
        }

        $sumRow = $CIP->find()
            ->select(['s' => $CIP->find()->func()->sum('amount')])
            ->where(['cost_invoice_id' => $costInvoiceId])
            ->first();
        $totalPaid = $sumRow ? (float)$sumRow->s : 0.0;

        $brutto = (float)($ci->brutto ?? 0);
        $isFullPaid = $brutto > 0 && abs($totalPaid - $brutto) < 0.01;
        $isOverPaid = $totalPaid > $brutto + 0.01 && $brutto > 0;
        $remaining = max(0, round($brutto - $totalPaid, 2));

        // Wybierz najnowszą wpłatę żeby ustawić paid_at + method
        $latest = $CIP->find()
            ->where(['cost_invoice_id' => $costInvoiceId])
            ->orderByDesc('payment_date')
            ->orderByDesc('created')
            ->first();

        $ci->set('paid_amount', round($totalPaid, 2));
        if ($totalPaid > 0 && $latest) {
            $pd = $latest->payment_date instanceof \DateTimeInterface
                ? $latest->payment_date->format('Y-m-d')
                : substr((string)$latest->payment_date, 0, 10);
            $ci->set('paid_at', $pd);
            if (!empty($latest->payment_method)) {
                $ci->set('payment_method', $latest->payment_method);
            }
        } elseif ($totalPaid <= 0) {
            $ci->set('paid_at', null);
            $ci->set('payment_method', null);
        }

        // Status: paid jeśli pełna kwota, verified jeśli częściowa, w pozostałych
        // przypadkach zostaw bieżący (chyba że był 'paid' a teraz nie ma pełnej —
        // wtedy zejdź do verified).
        if ($isFullPaid || $isOverPaid) {
            $ci->set('status', 'paid');
        } elseif ($totalPaid > 0) {
            if ($ci->status === 'received') $ci->set('status', 'verified');
            if ($ci->status === 'paid')     $ci->set('status', 'verified');
        } else {
            if ($ci->status === 'paid') $ci->set('status', 'verified');
        }

        $CI->save($ci);

        return [
            'paid_amount' => round($totalPaid, 2),
            'remaining'   => $remaining,
            'status'      => (string)$ci->status,
            'over_paid'   => $isOverPaid,
        ];
    }

    private function applyAutoNlStatus(\App\Model\Entity\SpeedOrder $entity): void
    {
        $ns = (int)($entity->nordlogis_status ?? 1);
        if (!empty($entity->pol_at) && $ns < 3) $ns = 3;
        if (!empty($entity->pod_at) && $ns < 4) $ns = 4;
        if (!empty($entity->fs_at)  && $ns < 5) $ns = 5;
        $entity->set('nordlogis_status', $ns);
        $entity->set('is_complete',
            !empty($entity->pol_at) && !empty($entity->pod_at) &&
            !empty($entity->fk_at)  && !empty($entity->fs_at)
        );
    }

    private function jsonResp(array $data): void
    {
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}

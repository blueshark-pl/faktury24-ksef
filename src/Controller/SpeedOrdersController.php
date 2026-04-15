<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Client;

/**
 * Zlecenia z systemu Speed ERP.
 *
 * Akcje:
 *  - index  : lista zleceń (z lokalnej DB + opcja synchronizacji)
 *  - view   : szczegóły pojedynczego zlecenia
 *  - sync   : AJAX POST — pobiera/aktualizuje zlecenia z Speed API (paginowane)
 */
class SpeedOrdersController extends AppController
{
    // -------------------------------------------------------------------------
    // Lista zleceń
    // -------------------------------------------------------------------------
    public function index(): void
    {
        $this->request->allowMethod(['get']);

        $search       = trim((string)$this->request->getQuery('q', ''));
        $status       = $this->request->getQuery('status', '');
        $currency     = strtoupper(trim((string)$this->request->getQuery('currency', '')));
        $amountMin    = $this->request->getQuery('amount_min', '');
        $amountMax    = $this->request->getQuery('amount_max', '');
        $deliveryFrom = $this->request->getQuery('delivery_from', '');
        $deliveryTo   = $this->request->getQuery('delivery_to', '');
        $page         = max(1, (int)$this->request->getQuery('page', 1));
        $limit        = (int)$this->request->getQuery('limit', 50);
        if (!in_array($limit, [25, 50, 100, 200, 500], true)) {
            $limit = 50;
        }

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $query = $SpeedOrders->find()
            ->orderByDesc('SpeedOrders.date_doc');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(['OR' => [
                'SpeedOrders.symbol LIKE'      => $like,
                'SpeedOrders.buyer_name LIKE'  => $like,
                'SpeedOrders.buyer_nip LIKE'   => $like,
                'SpeedOrders.title1 LIKE'      => $like,
                'SpeedOrders.title2 LIKE'      => $like,
                'SpeedOrders.route_description LIKE' => $like,
            ]]);
        }

        if ($status !== '') {
            if (str_starts_with($status, 'nl_')) {
                $query->where(['SpeedOrders.nordlogis_status' => (int)substr($status, 3)]);
            } elseif (str_starts_with($status, 'sp_')) {
                $query->where(['SpeedOrders.status' => (int)substr($status, 3)]);
            } elseif ($status === 'brak_pod') {
                $query->where(['SpeedOrders.pod_at IS' => null, 'SpeedOrders.invoice_id IS' => null]);
            } elseif ($status === 'brak_fk') {
                $query->where(['SpeedOrders.fk_at IS' => null]);
            } elseif ($status === 'niezafakt') {
                $query->where(['SpeedOrders.invoice_id IS' => null]);
            } elseif ($status === 'przetermin') {
                $query->where([
                    'SpeedOrders.date_delivery <'  => date('Y-m-d'),
                    'SpeedOrders.pod_at IS'        => null,
                    'SpeedOrders.invoice_id IS'    => null,
                ]);
            } else {
                $query->where(['SpeedOrders.status' => (int)$status]);
            }
        }

        if ($currency !== '') {
            $query->where(['SpeedOrders.currency' => $currency]);
        }

        if ($amountMin !== '') {
            $query->where(['SpeedOrders.netto >=' => (float)$amountMin]);
        }

        if ($amountMax !== '') {
            $query->where(['SpeedOrders.netto <=' => (float)$amountMax]);
        }

        if ($deliveryFrom !== '') {
            $query->where(['SpeedOrders.date_delivery >=' => $deliveryFrom]);
        }

        if ($deliveryTo !== '') {
            $query->where(['SpeedOrders.date_delivery <=' => $deliveryTo]);
        }

        $total  = (clone $query)->count();
        $pages  = max(1, (int)ceil($total / $limit));
        $page   = min($page, $pages);
        $orders = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        $this->set(compact('orders', 'total', 'page', 'pages', 'limit', 'search', 'status', 'currency', 'amountMin', 'amountMax', 'deliveryFrom', 'deliveryTo'));
    }

    // -------------------------------------------------------------------------
    // Eksport listy zleceń do CSV
    // -------------------------------------------------------------------------
    public function exportCsv(): void
    {
        $this->request->allowMethod(['get']);
        $this->disableAutoRender();

        $search   = trim((string)$this->request->getQuery('q', ''));
        $status   = $this->request->getQuery('status', '');
        $dateFrom = $this->request->getQuery('date_from', '');
        $dateTo   = $this->request->getQuery('date_to', '');
        $deliveryFrom = $this->request->getQuery('delivery_from', '');
        $deliveryTo   = $this->request->getQuery('delivery_to', '');

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $query = $SpeedOrders->find()->orderByDesc('SpeedOrders.date_doc');

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(['OR' => [
                'SpeedOrders.symbol LIKE'      => $like,
                'SpeedOrders.buyer_name LIKE'  => $like,
                'SpeedOrders.buyer_nip LIKE'   => $like,
            ]]);
        }
        if ($status !== '') {
            if (str_starts_with($status, 'nl_'))       $query->where(['SpeedOrders.nordlogis_status' => (int)substr($status, 3)]);
            elseif (str_starts_with($status, 'sp_'))   $query->where(['SpeedOrders.status' => (int)substr($status, 3)]);
            elseif ($status === 'brak_pod')             $query->where(['SpeedOrders.pod_at IS' => null, 'SpeedOrders.invoice_id IS' => null]);
            elseif ($status === 'brak_fk')              $query->where(['SpeedOrders.fk_at IS' => null]);
            elseif ($status === 'niezafakt')            $query->where(['SpeedOrders.invoice_id IS' => null]);
            elseif ($status === 'przetermin')           $query->where(['SpeedOrders.date_delivery <' => date('Y-m-d'), 'SpeedOrders.pod_at IS' => null, 'SpeedOrders.invoice_id IS' => null]);
        }
        if ($dateFrom !== '') $query->where(['SpeedOrders.date_doc >=' => $dateFrom]);
        if ($dateTo   !== '') $query->where(['SpeedOrders.date_doc <=' => $dateTo]);
        if ($deliveryFrom !== '') $query->where(['SpeedOrders.date_delivery >=' => $deliveryFrom]);
        if ($deliveryTo   !== '') $query->where(['SpeedOrders.date_delivery <=' => $deliveryTo]);

        $nlLabels = [1=>'Przyjęte',2=>'Zaplanowane',3=>'Załadowane',4=>'Zrealizowane',5=>'Zafakturowane'];
        $fdt = fn($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i') : substr((string)($v ?? ''), 0, 16);

        $out = fopen('php://output', 'w');
        // BOM dla Excel UTF-8
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Symbol','Data dok.','Zleceniodawca','NIP','Załadunek kraj','Załadunek miejsce',
            'Rozładunek kraj','Rozładunek miejsce','Kierowca','Przewoźnik',
            'Netto','Waluta','Status Nordlogis','POL','POD','FK','FS','Kompletne','Faktura ID','Data faktury',
        ], ';');

        foreach ($query->all() as $o) {
            fputcsv($out, [
                $o->symbol,
                $o->date_doc instanceof \DateTimeInterface ? $o->date_doc->format('Y-m-d') : substr((string)($o->date_doc ?? ''), 0, 10),
                $o->buyer_name,
                $o->buyer_nip,
                $o->load_country,
                trim(($o->load_postal_code ?? '') . ' ' . ($o->load_city ?? '')),
                $o->unload_country,
                trim(($o->unload_name ?? '') . ' ' . ($o->unload_city ?? '')),
                $o->driver,
                $o->carrier,
                str_replace('.', ',', (string)($o->netto ?? '')),
                $o->currency,
                $nlLabels[(int)($o->nordlogis_status ?? 1)] ?? '',
                $fdt($o->pol_at),
                $fdt($o->pod_at),
                $fdt($o->fk_at),
                $fdt($o->fs_at),
                $o->is_complete ? 'TAK' : 'NIE',
                $o->invoice_id ?? '',
                $fdt($o->invoiced_at),
            ], ';');
        }
        fclose($out);

        $filename = 'zlecenia-' . date('Y-m-d') . '.csv';
        $this->response = $this->response
            ->withType('text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    // -------------------------------------------------------------------------
    // Dashboard / control tower
    // -------------------------------------------------------------------------
    public function dashboard(): void
    {
        $this->request->allowMethod(['get']);
        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $today = date('Y-m-d');

        // Agregaty jednym zapytaniem
        $base = $SpeedOrders->find()->where(['SpeedOrders.invoice_id IS' => null]);

        $stats = [
            'total'        => $SpeedOrders->find()->count(),
            'bez_faktury'  => (clone $base)->count(),
            'bez_pod'      => (clone $base)->where(['SpeedOrders.pod_at IS' => null])->count(),
            'bez_fk'       => $SpeedOrders->find()->where(['SpeedOrders.fk_at IS' => null])->count(),
            'bez_fs'       => $SpeedOrders->find()->where(['SpeedOrders.fs_at IS' => null, 'SpeedOrders.invoice_id IS' => null])->count(),
            'przetermin'   => $SpeedOrders->find()->where([
                'SpeedOrders.date_delivery <' => $today,
                'SpeedOrders.pod_at IS'       => null,
                'SpeedOrders.invoice_id IS'   => null,
            ])->count(),
            'kompletne'    => $SpeedOrders->find()->where(['SpeedOrders.is_complete' => true])->count(),
        ];

        // Statusy Nordlogis — rozkład
        $nlCounts = [];
        for ($i = 1; $i <= 5; $i++) {
            $nlCounts[$i] = $SpeedOrders->find()->where(['SpeedOrders.nordlogis_status' => $i])->count();
        }

        // Przeterminowane — lista (max 20)
        $overdue = $SpeedOrders->find()
            ->where([
                'SpeedOrders.date_delivery <' => $today,
                'SpeedOrders.pod_at IS'       => null,
                'SpeedOrders.invoice_id IS'   => null,
            ])
            ->orderByAsc('SpeedOrders.date_delivery')
            ->limit(20)
            ->all();

        // Ostatnio zmodyfikowane — 10 zleceń
        $recent = $SpeedOrders->find()
            ->orderByDesc('SpeedOrders.modified')
            ->limit(10)
            ->all();

        $this->set(compact('stats', 'nlCounts', 'overdue', 'recent', 'today'));
    }

    // -------------------------------------------------------------------------
    // Szczegóły zlecenia
    // -------------------------------------------------------------------------
    public function view(int $id): void
    {
        $this->request->allowMethod(['get']);

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $order = $SpeedOrders->get($id);
        $rawData = null;
        if (!empty($order->raw_json)) {
            $rawData = json_decode($order->raw_json, true);
        }

        // Załaduj przypisane faktury kosztowe
        $CostInvoiceOrders = $this->fetchTable('CostInvoiceOrders');
        $pivotRows = $CostInvoiceOrders->find()
            ->where(['speed_order_id' => $id])
            ->all()
            ->map(fn($r) => $r->cost_invoice_id)
            ->toArray();

        $costInvoices = [];
        if (!empty($pivotRows)) {
            $costInvoices = $this->fetchTable('CostInvoices')
                ->find()
                ->where(['id IN' => $pivotRows])
                ->orderByDesc('issue_date')
                ->all()
                ->toArray();
        }

        // Historia zmian statusów (tabela może nie istnieć przed migracją)
        try {
            $statusLogs = $this->fetchTable('SpeedOrderStatusLogs')
                ->find()
                ->where(['speed_order_id' => $id])
                ->orderByAsc('created')
                ->all()
                ->toArray();
        } catch (\Throwable) {
            $statusLogs = [];
        }

        $this->set(compact('order', 'rawData', 'costInvoices', 'statusLogs'));
    }

    // -------------------------------------------------------------------------
    // Synchronizacja z Speed API (AJAX POST)
    // -------------------------------------------------------------------------
    public function sync(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $apiUrl   = rtrim((string)(
            getenv('SPEED_API_URL') ?: Configure::read('Speed.apiUrl') ?: ''
        ), '/');
        $apiToken = (string)(
            getenv('SPEED_API_TOKEN') ?: Configure::read('Speed.apiToken') ?: ''
        );

        if ($apiUrl === '') {
            $this->jsonResp(['success' => false, 'error' => 'Brak konfiguracji SPEED_API_URL.']);
            return;
        }

        $startPage = max(1, (int)$this->request->getData('page', 1));
        $limit     = 100;

        try {
            $client  = new Client();
            $headers = $apiToken !== '' ? ['Authorization' => 'Bearer ' . $apiToken] : [];

            $resp = $client->get(
                $apiUrl . '/zlecenia',
                ['page' => $startPage, 'limit' => $limit],
                ['headers' => $headers, 'timeout' => 30]
            );

            if ($resp->getStatusCode() !== 200) {
                $this->jsonResp(['success' => false, 'error' => 'Speed API HTTP ' . $resp->getStatusCode()]);
                return;
            }

            $json = $resp->getJson();
            if (!is_array($json) || !isset($json['data'])) {
                $this->jsonResp(['success' => false, 'error' => 'Nieoczekiwana odpowiedź Speed API (brak "data").']);
                return;
            }

            $payload    = (array)$json['data'];
            $totalPages = (int)($json['totalPages'] ?? 1);
            $total      = (int)($json['total'] ?? 0);

            $saved   = 0;
            $updated = 0;
            $errors  = [];
            $now     = date('Y-m-d H:i:s');
            $SpeedOrders = $this->fetchTable('SpeedOrders');

            foreach ($payload as $r) {
                $speedId = (int)($r['GLO_ID'] ?? 0);
                if ($speedId === 0) {
                    continue;
                }

                // Sklejenie adresu trasy
                $routeDesc = trim((string)($r['GLO_NAZ9'] ?? ''));

                // parse dates
                $dateDoc      = $this->parseSpeedDate($r['GLO_DATA_DOK'] ?? null);
                $dateShip     = $this->parseSpeedDate($r['GLO_DATA_WYS'] ?? null);
                $dateDeadline = $this->parseSpeedDate($r['GLO_DATA_TER'] ?? null);
                $dateDelivery = $this->parseSpeedDate($r['GLO_DATA_ZAK'] ?? null);
                $speedModAt   = $this->parseSpeedDate($r['GLO_DATA_ZMI'] ?? null);

                // Załadunek
                $loadCountry = trim((string)($r['GLO_MIE_KRAJ']    ?? ''));
                $loadCode    = trim((string)($r['GLO_MIE_KOD']     ?? ''));
                $loadCity    = trim((string)($r['GLO_MIE_POCZTA']  ?? ''));

                // Rozładunek
                $unloadN1    = trim((string)($r['GLO_MIE_NAZWA1']  ?? ''));
                $unloadN2    = trim((string)($r['GLO_MIE_NAZWA2']  ?? ''));
                $unloadCity  = trim((string)($r['GLO_MIE_MIEJSC']  ?? ''));
                $unloadName  = implode(', ', array_filter([$unloadN1, $unloadN2])) ?: null;

                $data = [
                    'speed_id'          => $speedId,
                    'company_nip'       => trim((string)($r['GLO_FIR_NIP'] ?? '')),
                    'company_name'      => trim((string)($r['GLO_FIR_NAZWA1'] ?? '')),
                    'symbol'            => trim((string)($r['GLO_SYMBOL'] ?? '')),
                    'ozn'               => trim((string)($r['GLO_OZN'] ?? '')),
                    'numer'             => (int)($r['GLO_NUMER'] ?? 0) ?: null,
                    'rok'               => trim((string)($r['GLO_ROK'] ?? '')),
                    'mc'                => trim((string)($r['GLO_MC'] ?? '')),
                    'teczka'            => trim((string)($r['GLO_TECZKA'] ?? '')),
                    'status'            => (int)($r['GLO_STATUS'] ?? 1),
                    'buyer_speed_id'    => (int)($r['GLO_ODB_ID'] ?? 0) ?: null,
                    'buyer_nip'         => trim((string)($r['GLO_ODB_NIP'] ?? '')),
                    'buyer_name'        => trim((string)($r['GLO_ODB_NAZWA1'] ?? '')),
                    'buyer_street'      => trim((string)($r['GLO_ODB_ULICA'] ?? '')),
                    'buyer_postal_code' => trim((string)($r['GLO_ODB_KOD'] ?? '')),
                    'buyer_city'        => trim((string)($r['GLO_ODB_MIEJSC'] ?? '')) ?: trim((string)($r['GLO_ODB_POCZTA'] ?? '')),
                    'buyer_country'     => trim((string)($r['GLO_ODB_KRAJ'] ?? '')),
                    'buyer_email'       => trim((string)($r['GLO_ODB_EMAIL'] ?? '')),
                    // Załadunek
                    'place_from_name'   => $unloadN1, // GLO_MIE_NAZWA1 = miejsce załadunku
                    'place_from_country'=> $loadCountry,
                    'load_country'      => $loadCountry ?: null,
                    'load_postal_code'  => $loadCode    ?: null,
                    'load_city'         => $loadCity    ?: null,
                    // Rozładunek
                    'place_to_name'     => $unloadN2,  // GLO_MIE_NAZWA2 = miejsce rozładunku
                    'place_to_country'  => $loadCountry, // fallback — Speed nie daje osobnego kraju rozładunku
                    'unload_name'       => $unloadName,
                    'unload_city'       => $unloadCity  ?: null,
                    'unload_country'    => null, // brak w API Speed — uzupełniamy ręcznie
                    // Trasa / opis
                    'route_description' => $routeDesc ?: null,
                    'title1'            => trim((string)($r['GLO_TYT1']    ?? '')),
                    'title2'            => trim((string)($r['GLO_TYT2']    ?? '')),
                    'cargo_type'        => trim((string)($r['GLO_NAZ10']   ?? '')),
                    // Transport
                    'driver'            => trim((string)($r['GLO_JEDNOSTKA'] ?? '')) ?: null,
                    'carrier'           => trim((string)($r['GLO_NAZ8']      ?? '')) ?: null,
                    'transport_type'    => trim((string)($r['GLO_MIE_RODZAJ'] ?? '')) ?: null,
                    'vehicle_reg'       => trim((string)($r['GLO_KONTO']      ?? '')) ?: null,
                    // Finansowe
                    'notes'             => ($r['GLO_UWAGI'] ?? null) !== null ? (string)$r['GLO_UWAGI'] : null,
                    'payment_terms'     => trim((string)($r['GLO_PLATNOSC']  ?? '')) ?: null,
                    'our_ref'           => trim((string)($r['GLO_POD']       ?? '')) ?: null,
                    'date_doc'          => $dateDoc,
                    'date_ship'         => $dateShip,
                    'date_deadline'     => $dateDeadline,
                    'date_delivery'     => $dateDelivery,
                    'currency'          => trim((string)($r['GLO_WALUTA'] ?? 'PLN')) ?: 'PLN',
                    'netto'             => (float)($r['GLO_NETTO'] ?? 0),
                    'vat'               => (float)($r['GLO_VAT'] ?? 0),
                    'brutto'            => (float)($r['GLO_BRUTTO'] ?? 0),
                    'exchange_rate'     => ($r['GLO_WAL_PRZEL'] ?? null) !== null ? (float)$r['GLO_WAL_PRZEL'] : null,
                    'exchange_table'    => trim((string)($r['GLO_WAL_TABELA'] ?? '')),
                    'nick_created'      => trim((string)($r['GLO_NICK_WYS']  ?? '')),
                    'nick_modified'     => trim((string)($r['GLO_NICK_ZMI']  ?? '')),
                    'raw_json'          => json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'imported_at'       => date('Y-m-d H:i:s'),
                    'speed_modified_at' => $speedModAt,
                ];

                // ── Auto-POL/POD na podstawie dat ze Speed ──
                // POL: GLO_DATA_TER (data załadunku) < dziś → załadunek zakończony
                $autoPol = ($dateDeadline !== null && $dateDeadline < date('Y-m-d'));
                // POD: GLO_DATA_ZAK (data rozładunku) < dziś → rozładunek zakończony
                $autoPod = ($dateDelivery !== null && $dateDelivery < date('Y-m-d'));

                // Upsert po speed_id
                $existing = $SpeedOrders->find()->where(['speed_id' => $speedId])->first();
                if ($existing) {
                    $entity = $SpeedOrders->patchEntity($existing, $data);
                    // Nie cofamy ręcznie ustawionych dat — uzupełniamy tylko jeśli puste
                    if ($autoPol && empty($entity->pol_at)) {
                        $entity->set('pol_at', $now);
                    }
                    if ($autoPod && empty($entity->pod_at)) {
                        $entity->set('pod_at', $now);
                    }
                    $this->applyAutoNlStatus($entity);
                    if ($SpeedOrders->save($entity)) {
                        $updated++;
                    } else {
                        $errors[] = 'Błąd aktualizacji GLO_ID=' . $speedId;
                    }
                } else {
                    $entity = $SpeedOrders->newEntity($data);
                    if ($autoPol) $entity->set('pol_at', $now);
                    if ($autoPod) $entity->set('pod_at', $now);
                    $this->applyAutoNlStatus($entity);
                    if ($SpeedOrders->save($entity)) {
                        $saved++;
                    } else {
                        $errors[] = 'Błąd zapisu GLO_ID=' . $speedId;
                    }
                }
            }

            $this->jsonResp([
                'success'    => true,
                'page'       => $startPage,
                'totalPages' => $totalPages,
                'total'      => $total,
                'saved'      => $saved,
                'updated'    => $updated,
                'errors'     => $errors,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResp(['success' => false, 'error' => 'Błąd: ' . $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function parseSpeedDate(mixed $val): ?string
    {
        if ($val === null || $val === '' || $val === false) {
            return null;
        }
        $s = (string)$val;
        // ISO 8601 datetime: 2026-04-08T00:00:00.000Z  →  "2026-04-08 00:00:00"
        // Cake DateTimeType::marshal() wymaga formatu z czasem (Y-m-d H:i:s),
        // sam Y-m-d nie pasuje do żadnego _marshalFormats i zwraca null.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})/', $s, $m)) {
            return $m[1] . ' ' . $m[2]; // "2026-04-08 00:00:00"
        }
        // fallback: data bez czasu (dla kolumn typu `date`)
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            return $m[1];
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Wsadowe tworzenie faktur z wielu zleceń (AJAX POST, JSON)
    // Payload: { groups: [ { order_ids: [1,2,3], currency: 'EUR', delivery_date: '...' }, ... ] }
    // Odpowiedź: { success: true, invoices: [ { url: '...', contractor: '...', count: N }, ... ] }
    // -------------------------------------------------------------------------
    public function createBatchInvoices(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $body   = (array)$this->request->getData();
        $groups = (array)($body['groups'] ?? []);

        if (empty($groups)) {
            $this->jsonResp(['success' => false, 'error' => 'Brak danych do przetworzenia.']);
            return;
        }

        $invoices = [];

        foreach ($groups as $group) {
            $orderIds = array_filter(array_map('intval', (array)($group['order_ids'] ?? [])));
            if (empty($orderIds)) continue;

            $currency = strtoupper(trim((string)($group['currency'] ?? 'PLN')));
            $action   = ($currency !== '' && $currency !== 'PLN') ? 'addCurrency' : 'addVat';

            // Buduj URL z wieloma order_ids (np. ?from_order_ids[]=1&from_order_ids[]=2)
            $queryParams = [];
            foreach ($orderIds as $id) {
                $queryParams['from_order_ids'][] = $id;
            }

            $url = \Cake\Routing\Router::url([
                'controller' => 'Invoices',
                'action'     => $action,
                '?'          => $queryParams,
            ], true);

            $invoices[] = [
                'url'          => $url,
                'contractor'   => (string)($group['contractor'] ?? ''),
                'delivery_date'=> (string)($group['delivery_date'] ?? ''),
                'currency'     => $currency,
                'count'        => count($orderIds),
                'order_ids'    => array_values($orderIds),
            ];
        }

        $this->jsonResp(['success' => true, 'invoices' => $invoices]);
    }

    // -------------------------------------------------------------------------
    // Zmiana statusu Nordlogis / checkboxów POL/POD/FK/FS (AJAX POST)
    // Payload: { id: 1, nordlogis_status: 3 } lub { id: 1, pol: true/false }
    // -------------------------------------------------------------------------
    public function updateStatus(): void
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);

        $id   = (int)$this->request->getData('id', 0);
        $SpeedOrders = $this->fetchTable('SpeedOrders');

        /** @var \App\Model\Entity\SpeedOrder|null $order */
        $order = $SpeedOrders->find()->where(['id' => $id])->first();
        if (!$order || $id === 0) {
            $this->jsonResp(['success' => false, 'error' => 'Nie znaleziono zlecenia.']);
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Identyfikacja zalogowanego usera
        $identity  = $this->request->getAttribute('identity');
        $userId    = $identity ? (int)$identity->getIdentifier() : null;
        $username  = $identity
            ? (string)($identity->get('username') ?? $identity->get('email') ?? $identity->getIdentifier())
            : 'system';

        $Logs = $this->fetchTable('SpeedOrderStatusLogs');
        $logEntries = [];

        // Zmiana statusu operacyjnego
        if ($this->request->getData('nordlogis_status') !== null) {
            $ns = (int)$this->request->getData('nordlogis_status');
            if ($ns >= 1 && $ns <= 5) {
                $oldNs = (int)($order->nordlogis_status ?? 1);
                if ($oldNs !== $ns) {
                    $logEntries[] = ['field' => 'nordlogis_status', 'old' => (string)$oldNs, 'new' => (string)$ns];
                }
                $order->set('nordlogis_status', $ns);
            }
        }

        // Checkboxy — ustawiamy datę, pole *_by i null
        foreach (['pol', 'pod', 'fk', 'fs'] as $chk) {
            $val = $this->request->getData($chk);
            if ($val !== null) {
                $fieldAt = $chk . '_at';
                $fieldBy = $chk . '_by';
                $checked = (bool)$val;
                $oldVal  = $order->{$fieldAt} ? (string)$order->{$fieldAt} : null;
                $newVal  = $checked ? $now : null;

                $order->set($fieldAt, $newVal);
                // *_by: ustawiamy przy zaznaczeniu, czyścimy przy odznaczeniu
                $order->set($fieldBy, $checked ? $username : null);

                if ($chk === 'fs' && $checked) {
                    $order->set('invoiced_at', $now);
                }

                if ($oldVal !== $newVal) {
                    $logEntries[] = [
                        'field' => $fieldAt,
                        'old'   => $oldVal ? substr($oldVal, 0, 16) : null,
                        'new'   => $newVal ? substr($newVal, 0, 16) : null,
                    ];
                }
            }
        }

        // Automatyczne przejścia statusu + is_complete
        $this->applyAutoNlStatus($order);

        if ($SpeedOrders->save($order)) {
            // Zapisz logi
            foreach ($logEntries as $entry) {
                $log = $Logs->newEntity([
                    'speed_order_id' => $order->id,
                    'field'          => $entry['field'],
                    'old_value'      => $entry['old'],
                    'new_value'      => $entry['new'],
                    'user_id'        => $userId,
                    'username'       => $username,
                    'created'        => $now,
                ]);
                $Logs->save($log);
            }

            $this->jsonResp([
                'success'          => true,
                'nordlogis_status' => $order->nordlogis_status,
                'is_complete'      => $order->is_complete,
                'pol_at'           => $order->pol_at ? (string)$order->pol_at : null,
                'pol_by'           => $order->pol_by,
                'pod_at'           => $order->pod_at ? (string)$order->pod_at : null,
                'pod_by'           => $order->pod_by,
                'fk_at'            => $order->fk_at ? (string)$order->fk_at : null,
                'fk_by'            => $order->fk_by,
                'fs_at'            => $order->fs_at ? (string)$order->fs_at : null,
                'fs_by'            => $order->fs_by,
            ]);
        } else {
            $this->jsonResp(['success' => false, 'error' => 'Błąd zapisu.']);
        }
    }

    /**
     * Automatyczne przejścia statusu Nordlogis na podstawie POL/POD/FS.
     * Wywołuj po ustawieniu pol_at / pod_at / fs_at na encji.
     */
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

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

        $search  = trim((string)$this->request->getQuery('q', ''));
        $status  = $this->request->getQuery('status', '');
        $dateFrom = $this->request->getQuery('date_from', '');
        $dateTo   = $this->request->getQuery('date_to', '');
        $page    = max(1, (int)$this->request->getQuery('page', 1));
        $limit   = 50;

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
            $query->where(['SpeedOrders.status' => (int)$status]);
        }


        if ($dateFrom !== '') {
            $query->where(['SpeedOrders.date_doc >=' => $dateFrom]);
        }

        if ($dateTo !== '') {
            $query->where(['SpeedOrders.date_doc <=' => $dateTo]);
        }

        $total  = (clone $query)->count();
        $pages  = max(1, (int)ceil($total / $limit));
        $page   = min($page, $pages);
        $orders = $query->limit($limit)->offset(($page - 1) * $limit)->all();

        $this->set(compact('orders', 'total', 'page', 'pages', 'limit', 'search', 'status', 'dateFrom', 'dateTo'));
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

        $this->set(compact('order', 'rawData'));
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
                    'buyer_city'        => trim((string)($r['GLO_ODB_MIEJSC'] ?? $r['GLO_ODB_POCZTA'] ?? '')),
                    'buyer_country'     => trim((string)($r['GLO_ODB_KRAJ'] ?? '')),
                    'buyer_email'       => trim((string)($r['GLO_ODB_EMAIL'] ?? '')),
                    'place_from_name'   => trim((string)($r['GLO_MIE_NAZWA1'] ?? '')),
                    'place_from_country'=> trim((string)($r['GLO_MIE_KRAJ'] ?? '')),
                    'place_to_name'     => trim((string)($r['GLO_MIE_NAZWA2'] ?? '')),
                    'place_to_country'  => trim((string)($r['GLO_MIE_KRAJ'] ?? '')),
                    'route_description' => $routeDesc ?: null,
                    'title1'            => trim((string)($r['GLO_TYT1'] ?? '')),
                    'title2'            => trim((string)($r['GLO_TYT2'] ?? '')),
                    'cargo_type'        => trim((string)($r['GLO_NAZ10'] ?? '')),
                    'notes'             => ($r['GLO_UWAGI'] ?? null) !== null ? (string)$r['GLO_UWAGI'] : null,
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
                    'nick_created'      => trim((string)($r['GLO_NICK_WYS'] ?? '')),
                    'nick_modified'     => trim((string)($r['GLO_NICK_ZMI'] ?? '')),
                    'raw_json'          => json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'imported_at'       => date('Y-m-d H:i:s'),
                    'speed_modified_at' => $speedModAt,
                ];

                // Upsert po speed_id
                $existing = $SpeedOrders->find()->where(['speed_id' => $speedId])->first();
                if ($existing) {
                    $entity = $SpeedOrders->patchEntity($existing, $data);
                    if ($SpeedOrders->save($entity)) {
                        $updated++;
                    } else {
                        $errors[] = 'Błąd aktualizacji GLO_ID=' . $speedId;
                    }
                } else {
                    $entity = $SpeedOrders->newEntity($data);
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
        // ISO 8601 datetime: 2026-04-08T00:00:00.000Z
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            return $m[1]; // zwróć date część, Cake/MySQL zaakceptuje też datetime
        }
        return null;
    }

    private function jsonResp(array $data): void
    {
        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}

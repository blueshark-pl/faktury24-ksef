<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Routing\HereRoutingService;
use Cake\Http\Response;

/**
 * Planner tras truck — globalny (/trasy) i AJAX endpoint dla widoków zleceń.
 *
 * Akcje:
 *  - index()    : strona z formularzem + mapą (Leaflet) — JS pobiera trasę przez AJAX
 *  - calculate(): AJAX POST/GET — zwraca JSON z trasą (km, czas, opłaty, polyline)
 *  - geocode()  : AJAX — geocoding adresu tekstowego
 *  - forOrder(): AJAX — automatyczne liczenie trasy dla zlecenia speed_orders
 */
class RoutePlannerController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        $identity = $this->request->getAttribute('identity');
        if (!$identity) {
            $event->setResult($this->redirect('/users/login'));
            return;
        }
        if (strtolower((string)$identity->get('role')) === 'client') {
            $event->setResult($this->response->withStatus(403));
        }
    }

    public function index(): void
    {
        $companyId = (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
        $vehicles = [];
        if ($companyId !== '') {
            $vehicles = $this->fetchTable('Vehicles')->find()
                ->where(['company_id' => $companyId, 'is_active' => true])
                ->orderByDesc('is_default')
                ->orderByAsc('name')
                ->all()
                ->toArray();
        }
        $hereApiKey = (string)\Cake\Core\Configure::read('Here.apiKey');
        $this->set(compact('vehicles', 'hereApiKey'));
    }

    public function calculate(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['get', 'post']);

        // Multipoint waypoints: tablica punktów (>=2)
        // [{address, lat?, lng?}, ...]
        $points = (array)($this->request->getData('points', $this->request->getQuery('points', [])));
        // Wsteczna zgodność: legacy from/to (planer per zlecenie)
        if (empty($points)) {
            $fromAddr = trim((string)$this->request->getData('from', $this->request->getQuery('from', '')));
            $toAddr   = trim((string)$this->request->getData('to',   $this->request->getQuery('to', '')));
            if ($fromAddr !== '' && $toAddr !== '') {
                $points = [['address' => $fromAddr], ['address' => $toAddr]];
            }
        }
        if (count($points) < 2) {
            return $this->jsonError(__('Podaj co najmniej dwa punkty.'));
        }

        $vehicleId = (string)$this->request->getData('vehicle_id', $this->request->getQuery('vehicle_id', ''));
        $avoid     = (array)$this->request->getData('avoid', $this->request->getQuery('avoid', []));
        $currency  = (string)$this->request->getData('currency', $this->request->getQuery('currency', 'EUR'));
        $alts      = (int)$this->request->getData('alternatives', $this->request->getQuery('alternatives', 0));
        $instr     = (bool)$this->request->getData('instructions', $this->request->getQuery('instructions', false));
        $departure = (string)$this->request->getData('departure_time', $this->request->getQuery('departure_time', ''));

        try {
            $here = new HereRoutingService();
            // Geocode wszystkich punktów które nie mają jeszcze lat/lng
            $resolved = [];
            foreach ($points as $i => $p) {
                $p = (array)$p;
                if (!empty($p['lat']) && !empty($p['lng'])) {
                    $resolved[] = [
                        'lat'   => (float)$p['lat'],
                        'lng'   => (float)$p['lng'],
                        'label' => (string)($p['label'] ?? $p['address'] ?? ''),
                    ];
                    continue;
                }
                $addr = trim((string)($p['address'] ?? ''));
                if ($addr === '') {
                    return $this->jsonError(__('Punkt #') . ($i + 1) . __(' jest pusty.'));
                }
                $geo = $here->geocode($addr);
                if (!$geo) return $this->jsonError(__('Nie znaleziono adresu: ') . $addr);
                $resolved[] = $geo;
            }

            $vehicleData = null;
            if ($vehicleId !== '') {
                $vehicle = $this->fetchTable('Vehicles')->find()->where(['id' => $vehicleId])->first();
                if ($vehicle) {
                    $vehicleData = $vehicle->toArray();
                }
            }

            $origin = array_shift($resolved);
            $dest   = array_pop($resolved);
            $vias   = $resolved;

            // Zbieramy oryginalne adresy (po geocode mamy labels)
            $origin['address'] = $points[0]['address'] ?? $origin['label'];
            $dest['address']   = $points[count($points) - 1]['address'] ?? $dest['label'];

            $result = $here->routeMulti($origin, $dest, $vias, $vehicleData, [
                'avoid'              => $avoid,
                'currency'           => $currency,
                'alternatives'       => $alts,
                'returnInstructions' => $instr,
                'departureTime'      => $departure !== '' ? $departure : null,
            ]);

            $result['points'] = array_merge([$origin], $vias, [$dest]);

            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function autosuggest(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['get']);
        $q = trim((string)$this->request->getQuery('q', ''));
        $proximity = null;
        $lat = $this->request->getQuery('lat');
        $lng = $this->request->getQuery('lng');
        if ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
            $proximity = ['lat' => (float)$lat, 'lng' => (float)$lng];
        }

        try {
            $here = new HereRoutingService();
            $items = $here->autosuggest($q, $proximity);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['items' => $items], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function forOrder(string $orderId): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['get']);

        $SpeedOrders = $this->fetchTable('SpeedOrders');
        $order = $SpeedOrders->find()
            ->where(['SpeedOrders.id' => $orderId])
            ->first();
        if (!$order) {
            return $this->jsonError(__('Zlecenie nie istnieje.'), 404);
        }

        $fromAddr = $this->orderAddress($order, 'from');
        $toAddr   = $this->orderAddress($order, 'to');
        if ($fromAddr === '' || $toAddr === '') {
            return $this->jsonError(__('Zlecenie nie ma kompletnych adresów załadunku/rozładunku.'));
        }

        // Wybierz pojazd: query param > domyślny firmy
        $vehicleId = (string)$this->request->getQuery('vehicle_id', '');
        $companyId = (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
        $Vehicles = $this->fetchTable('Vehicles');
        $vehicle = null;
        if ($vehicleId !== '') {
            $vehicle = $Vehicles->find()->where(['id' => $vehicleId, 'company_id' => $companyId])->first();
        }
        if (!$vehicle && $companyId !== '') {
            $vehicle = $Vehicles->find()
                ->where(['company_id' => $companyId, 'is_default' => true, 'is_active' => true])
                ->first();
        }
        $vehicleData = $vehicle ? $vehicle->toArray() : null;

        try {
            $here = new HereRoutingService();
            $from = $here->geocode($fromAddr);
            $to   = $here->geocode($toAddr);
            if (!$from) return $this->jsonError(__('Nie udało się zlokalizować adresu załadunku: ') . $fromAddr);
            if (!$to)   return $this->jsonError(__('Nie udało się zlokalizować adresu rozładunku: ') . $toAddr);

            $result = $here->route($from, $to, $vehicleData);
            unset($result['raw']);
            $result['from'] = $from;
            $result['to']   = $to;
            $result['vehicle'] = $vehicle ? [
                'id' => $vehicle->id, 'name' => $vehicle->name, 'plate' => $vehicle->plate
            ] : null;

            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    private function orderAddress(object $order, string $which): string
    {
        if ($which === 'from') {
            $parts = [
                trim((string)($order->place_from_name ?? '')),
                trim((string)($order->place_from_zip ?? '')),
                trim((string)($order->place_from_country ?? '')),
            ];
        } else {
            $parts = [
                trim((string)($order->place_to_name ?? '')),
                trim((string)($order->place_to_zip ?? '')),
                trim((string)($order->place_to_country ?? '')),
            ];
        }
        return trim(implode(', ', array_filter($parts)));
    }

    private function jsonError(string $msg, int $status = 400): Response
    {
        return $this->response
            ->withType('application/json')
            ->withStatus($status)
            ->withStringBody(json_encode(['error' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE));
    }
}

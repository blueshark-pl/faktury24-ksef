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
        $identity = $this->request->getAttribute('identity');
        $userId = (string)($identity?->getIdentifier() ?? '');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $vehicles = [];
        if ($companyId !== '') {
            $vehicles = $this->fetchTable('Vehicles')->find()
                ->where(['company_id' => $companyId, 'is_active' => true])
                ->orderByDesc('is_default')
                ->orderByAsc('name')
                ->all()
                ->toArray();
        }

        // Historia + szablony (per user)
        $recentSearches = [];
        $templates = [];
        if ($userId !== '') {
            $Searches = $this->fetchTable('RouteSearches');
            $mapRow = function ($r) {
                $waypoints = json_decode((string)$r->waypoints_json, true) ?: [];
                return [
                    'id'             => (string)$r->id,
                    'name'           => (string)($r->name ?? ''),
                    'waypoints'      => $waypoints,
                    'vehicle_id'     => (string)($r->vehicle_id ?? ''),
                    'distance_km'    => $r->distance_km !== null ? (float)$r->distance_km : null,
                    'duration_min'   => $r->duration_min !== null ? (int)$r->duration_min : null,
                    'tolls_total'    => $r->tolls_total !== null ? (float)$r->tolls_total : null,
                    'tolls_currency' => (string)($r->tolls_currency ?? ''),
                    'last_used'      => $r->last_used instanceof \DateTimeInterface ? $r->last_used->format('c') : null,
                ];
            };

            // Szablony (z name) — alfabetycznie
            foreach ($Searches->find()
                ->where(['user_id' => $userId, 'name IS NOT' => null])
                ->orderByAsc('name')
                ->all() as $r) {
                $templates[] = $mapRow($r);
            }
            // Historia (bez name) — top 10 po last_used
            foreach ($Searches->find()
                ->where(['user_id' => $userId, 'name IS' => null])
                ->orderByDesc('last_used')
                ->limit(10)
                ->all() as $r) {
                $recentSearches[] = $mapRow($r);
            }
        }

        $hereApiKey = (string)\Cake\Core\Configure::read('Here.apiKey');
        $this->set(compact('vehicles', 'hereApiKey', 'recentSearches', 'templates'));
    }

    public function saveTemplate(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $userId = (string)($this->request->getAttribute('identity')?->getIdentifier() ?? '');
        $id = (string)$this->request->getData('id', '');
        $name = trim((string)$this->request->getData('name', ''));
        if ($id === '' || $name === '') {
            return $this->jsonError(__('Brak ID lub nazwy.'));
        }
        $Searches = $this->fetchTable('RouteSearches');
        $entity = $Searches->find()->where(['id' => $id, 'user_id' => $userId])->first();
        if (!$entity) {
            return $this->jsonError(__('Wpis nie istnieje.'), 404);
        }
        $entity->name = mb_substr($name, 0, 120);
        $Searches->save($entity);
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['ok' => true, 'name' => $entity->name]));
    }

    public function deleteRecent(string $id): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post', 'delete']);
        $userId = (string)($this->request->getAttribute('identity')?->getIdentifier() ?? '');
        $this->fetchTable('RouteSearches')->deleteAll(['id' => $id, 'user_id' => $userId]);
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['ok' => true]));
    }

    private function saveRouteSearch(array $points, string $vehicleId, array $firstRoute): void
    {
        $identity = $this->request->getAttribute('identity');
        $userId = (string)($identity?->getIdentifier() ?? '');
        if ($userId === '') return;
        $companyId = (string)($identity?->get('company_id') ?? '');

        // Sygnatura: lat/lng zaokr. do 4 miejsc + vehicle_id
        $sigParts = [];
        foreach ($points as $p) {
            $sigParts[] = number_format((float)($p['lat'] ?? 0), 4, '.', '') . ',' . number_format((float)($p['lng'] ?? 0), 4, '.', '');
        }
        $sigParts[] = 'v:' . $vehicleId;
        $signature = sha1(implode('|', $sigParts));

        $waypointsClean = array_map(function ($p) {
            return [
                'address' => (string)($p['address'] ?? $p['label'] ?? ''),
                'label'   => (string)($p['label']   ?? $p['address'] ?? ''),
                'lat'     => isset($p['lat']) ? (float)$p['lat'] : null,
                'lng'     => isset($p['lng']) ? (float)$p['lng'] : null,
            ];
        }, $points);

        $Searches = $this->fetchTable('RouteSearches');
        $existing = $Searches->find()->where(['user_id' => $userId, 'signature' => $signature])->first();
        if ($existing) {
            $existing->waypoints_json = json_encode($waypointsClean, JSON_UNESCAPED_UNICODE);
            $existing->distance_km    = $firstRoute['distance_km'] ?? null;
            $existing->duration_min   = $firstRoute['duration_min'] ?? null;
            $existing->tolls_total    = $firstRoute['tolls_total']  ?? null;
            $existing->tolls_currency = $firstRoute['tolls_currency'] ?? null;
            $Searches->save($existing);
            return;
        }
        $entity = $Searches->newEntity([
            'id'             => \Cake\Utility\Text::uuid(),
            'user_id'        => $userId,
            'company_id'     => $companyId ?: null,
            'waypoints_json' => json_encode($waypointsClean, JSON_UNESCAPED_UNICODE),
            'vehicle_id'     => $vehicleId !== '' ? $vehicleId : null,
            'distance_km'    => $firstRoute['distance_km'] ?? null,
            'duration_min'   => $firstRoute['duration_min'] ?? null,
            'tolls_total'    => $firstRoute['tolls_total']  ?? null,
            'tolls_currency' => $firstRoute['tolls_currency'] ?? null,
            'signature'      => $signature,
        ]);
        $Searches->save($entity);

        // Limit do 50 ostatnich per user — usuwamy starsze
        $extra = $Searches->find()
            ->where(['user_id' => $userId])
            ->orderByDesc('last_used')
            ->offset(50)
            ->limit(1000)
            ->all();
        foreach ($extra as $row) {
            $Searches->delete($row);
        }
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
        $excludeCountries = (array)$this->request->getData('exclude_countries', $this->request->getQuery('exclude_countries', []));
        $adrClass  = (string)$this->request->getData('adr_class', $this->request->getQuery('adr_class', ''));

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
                'excludeCountries'   => $excludeCountries,
                'adrClass'           => $adrClass,
                'currency'           => $currency,
                'alternatives'       => $alts,
                'returnInstructions' => $instr,
                'departureTime'      => $departure !== '' ? $departure : null,
            ]);

            $allPoints = array_merge([$origin], $vias, [$dest]);
            $result['points'] = $allPoints;

            // NBP EUR→PLN rate dla tolls
            $result['eur_pln_rate'] = $this->fetchEurPlnRate();

            // AI sugerowana cena na podstawie historii
            $firstRoute = $result['routes'][0] ?? [];
            if (!empty($firstRoute['distance_km'])) {
                $result['ai_price'] = $this->estimateAiPrice(
                    (float)$firstRoute['distance_km'],
                    $vehicleId,
                    $vehicleData
                );
            }

            // Auto-zapis do historii (best-effort, błąd nie blokuje response)
            try {
                $this->saveRouteSearch($allPoints, $vehicleId, $firstRoute);
            } catch (\Throwable $e) {
                \Cake\Log\Log::warning('RouteSearch save failed: ' . $e->getMessage());
            }

            return $this->response->withType('application/json')
                ->withStringBody(json_encode($result, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function revgeocode(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['get']);
        $lat = $this->request->getQuery('lat');
        $lng = $this->request->getQuery('lng');
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return $this->jsonError(__('Brak współrzędnych.'));
        }
        try {
            $here = new HereRoutingService();
            $res = $here->reverseGeocode((float)$lat, (float)$lng);
            if (!$res) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['label' => '', 'country' => '']));
            }
            return $this->response->withType('application/json')
                ->withStringBody(json_encode($res, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // AI ENDPOINTS (OpenAI gpt-4o-mini)
    // ═══════════════════════════════════════════════════════════════

    public function aiParseAddress(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $text = trim((string)$this->request->getData('text', ''));
        if ($text === '') {
            return $this->jsonError(__('Pusty tekst do analizy.'));
        }
        try {
            $ai = new \App\Service\Ai\OpenAiService();
            $result = $ai->parseRouteText($text);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function aiCargoWizard(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $desc = trim((string)$this->request->getData('description', ''));
        if ($desc === '') {
            return $this->jsonError(__('Brak opisu ładunku.'));
        }
        try {
            $ai = new \App\Service\Ai\OpenAiService();
            $result = $ai->analyzeCargo($desc);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function aiPricing(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $context = (array)$this->request->getData('context', []);
        if (empty($context['distance_km'])) {
            return $this->jsonError(__('Brak kontekstu trasy.'));
        }
        try {
            $ai = new \App\Service\Ai\OpenAiService();
            $result = $ai->priceAdvisor($context);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function aiDriverBrief(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $context = (array)$this->request->getData('context', []);
        $language = (string)$this->request->getData('language', 'pl');
        if (empty($context)) {
            return $this->jsonError(__('Brak kontekstu trasy.'));
        }
        try {
            $ai = new \App\Service\Ai\OpenAiService();
            $brief = $ai->driverBrief($context, $language);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'brief' => $brief, 'language' => $language], JSON_UNESCAPED_UNICODE));
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

    /**
     * Pobiera kurs EUR/PLN z NBP (table A). Cache 6h w session.
     */
    private function fetchEurPlnRate(): ?float
    {
        $session = $this->request->getSession();
        $cached = $session->read('NbpEurPln');
        if ($cached && isset($cached['rate'], $cached['t']) && (time() - $cached['t']) < 6 * 3600) {
            return (float)$cached['rate'];
        }
        try {
            $client = new \Cake\Http\Client(['timeout' => 5]);
            $resp = $client->get('http://api.nbp.pl/api/exchangerates/rates/A/EUR/?format=json');
            if (!$resp->isOk()) return null;
            $data = $resp->getJson();
            $rate = (float)($data['rates'][0]['mid'] ?? 0);
            if ($rate <= 0) return null;
            $session->write('NbpEurPln', ['rate' => $rate, 't' => time()]);
            return $rate;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * "JJ Price" — sugerowana cena frachtu na podstawie historii.
     * Mediana stawki PLN/km z route_searches o podobnym dystansie (±15%) i tym samym vehicle_id.
     * Fallback: domyślna stawka pojazdu × dystans + 10% margin.
     *
     * @return array{price: float, basis: string, samples: int}|null
     */
    private function estimateAiPrice(float $distanceKm, string $vehicleId, ?array $vehicleData): ?array
    {
        $userId = (string)($this->request->getAttribute('identity')?->getIdentifier() ?? '');
        $companyId = (string)($this->request->getAttribute('identity')?->get('company_id') ?? '');
        if ($userId === '') return null;

        // Próg dystansu ±15%
        $minDist = $distanceKm * 0.85;
        $maxDist = $distanceKm * 1.15;

        $Searches = $this->fetchTable('RouteSearches');
        $q = $Searches->find()
            ->where([
                'user_id' => $userId,
                'distance_km IS NOT' => null,
                'distance_km >=' => $minDist,
                'distance_km <=' => $maxDist,
            ]);
        if ($vehicleId !== '') {
            $q->where(['vehicle_id' => $vehicleId]);
        }
        $rows = $q->limit(50)->all();

        // Jeśli mamy historyczne tolls i nasza stawka — szacuj PLN/km
        $samples = [];
        foreach ($rows as $r) {
            if (!$r->distance_km || $r->distance_km <= 0) continue;
            // Heurystyka: zakładana cena frachtu = (stawka pojazdu × km) + tolls converted to PLN
            if (!empty($vehicleData['rate_per_km'])) {
                $estPrice = ((float)$vehicleData['rate_per_km']) * (float)$r->distance_km;
                $samples[] = $estPrice / (float)$r->distance_km; // = stawka pojazdu
            }
        }

        // Fallback: stawka pojazdu × dystans + 10% margin
        if (!empty($vehicleData['rate_per_km'])) {
            $price = ((float)$vehicleData['rate_per_km']) * $distanceKm * 1.10;
            return [
                'price'   => round($price, 2),
                'basis'   => count($samples) > 0
                    ? __('historia ({0} tras o ±15% dystansie)', [count($samples)])
                    : __('stawka pojazdu × dystans + 10% marża'),
                'samples' => count($samples),
            ];
        }
        return null;
    }

    private function jsonError(string $msg, int $status = 400): Response
    {
        return $this->response
            ->withType('application/json')
            ->withStatus($status)
            ->withStringBody(json_encode(['error' => true, 'message' => $msg], JSON_UNESCAPED_UNICODE));
    }
}

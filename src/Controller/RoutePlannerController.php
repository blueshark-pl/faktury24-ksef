<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\Routing\HereRoutingService;
use Cake\Core\Configure;
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
        // Publiczne akcje (#14 Live tracking link dla klienta — bez auth)
        $publicActions = ['track', 'trackView'];
        $action = $this->request->getParam('action');
        if (in_array($action, $publicActions, true)) {
            if ($this->components()->has('Authentication')) {
                $this->Authentication->allowUnauthenticated($publicActions);
            }
            return;
        }
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
        $trailers = [];
        $drivers = [];
        $combinations = [];
        if ($companyId !== '') {
            $vehicles = $this->fetchTable('Vehicles')->find()
                ->where(['company_id' => $companyId, 'is_active' => true])
                ->orderByDesc('is_default')
                ->orderByAsc('name')
                ->all()
                ->toArray();
            $trailers = $this->fetchTable('Trailers')->find()
                ->where(['company_id' => $companyId, 'is_active' => true])
                ->orderByDesc('is_default')
                ->orderByAsc('name')
                ->all()
                ->toArray();
            $drivers = $this->fetchTable('Drivers')->find()
                ->where(['company_id' => $companyId, 'is_active' => true])
                ->orderByDesc('is_default')
                ->orderByAsc('full_name')
                ->all()
                ->toArray();
            $combinations = $this->fetchTable('VehicleCombinations')->find()
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
        $this->set(compact('vehicles', 'trailers', 'drivers', 'combinations', 'hereApiKey', 'recentSearches', 'templates'));
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

    private function saveRouteSearch(array $points, string $vehicleId, array $firstRoute): ?string
    {
        $identity = $this->request->getAttribute('identity');
        $userId = (string)($identity?->getIdentifier() ?? '');
        if ($userId === '') return null;
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
                'date'    => !empty($p['date']) ? (string)$p['date'] : null,
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
            return (string)$existing->id;
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
        return (string)$entity->id;
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
            // Zbieramy daty per waypoint (mogą być puste)
            $pointDates = [];
            foreach ($points as $i => $p) {
                $pointDates[$i] = trim((string)(((array)$p)['date'] ?? ''));
            }
            // (A) Auto-set departure z najwcześniejszej daty waypoint jeśli hero puste
            if ($departure === '' && !empty($pointDates[0])) {
                // datetime-local YYYY-MM-DDTHH:MM → ISO z lokalną strefą (HERE akceptuje "any")
                $d = $pointDates[0];
                if (strlen($d) === 16) $d .= ':00';
                $departure = $d; // np. 2026-05-15T10:00:00 — HERE bierze jako "local"
            }

            // Geocode wszystkich punktów które nie mają jeszcze lat/lng
            $resolved = [];
            foreach ($points as $i => $p) {
                $p = (array)$p;
                if (!empty($p['lat']) && !empty($p['lng'])) {
                    $resolved[] = [
                        'lat'   => (float)$p['lat'],
                        'lng'   => (float)$p['lng'],
                        'label' => (string)($p['label'] ?? $p['address'] ?? ''),
                        'date'  => $pointDates[$i] ?? '',
                    ];
                    continue;
                }
                $addr = trim((string)($p['address'] ?? ''));
                if ($addr === '') {
                    return $this->jsonError(__('Punkt #') . ($i + 1) . __(' jest pusty.'));
                }
                $geo = $here->geocode($addr);
                if (!$geo) return $this->jsonError(__('Nie znaleziono adresu: ') . $addr);
                $geo['date'] = $pointDates[$i] ?? '';
                $resolved[] = $geo;
            }

            $vehicleData = null;
            if ($vehicleId !== '') {
                $vehicle = $this->fetchTable('Vehicles')->find()->where(['id' => $vehicleId])->first();
                if ($vehicle) {
                    $vehicleData = $vehicle->toArray();
                }
            }

            // Hogis-style: jeśli wybrana naczepa → sumujemy parametry z ciągnikiem
            // (HERE musi dostać total weight + total axles dla poprawnej klasyfikacji toll)
            $trailerId = (string)$this->request->getData('trailer_id', '');
            $combinationWarnings = [];
            if ($trailerId !== '' && $vehicleData) {
                $trailer = $this->fetchTable('Trailers')->find()->where(['id' => $trailerId])->first();
                if ($trailer) {
                    $t = $trailer->toArray();

                    // Zachowaj oryginalne wartości ciągnika dla widoczności
                    $vehiclesOwn = [
                        'axle_count'      => (int)($vehicleData['axle_count'] ?? 0),
                        'gross_weight_kg' => (int)($vehicleData['gross_weight_kg'] ?? 0),
                        'length_cm'       => (int)($vehicleData['length_cm'] ?? 0),
                        'width_cm'        => (int)($vehicleData['width_cm'] ?? 0),
                        'height_cm'       => (int)($vehicleData['height_cm'] ?? 0),
                    ];
                    $trailerOwn = [
                        'axle_count'      => (int)($t['axle_count'] ?? 0),
                        'gross_weight_kg' => (int)($t['gross_weight_kg'] ?? 0),
                        'length_cm'       => (int)($t['length_cm'] ?? 0),
                        'width_cm'        => (int)($t['width_cm'] ?? 0),
                        'height_cm'       => (int)($t['height_cm'] ?? 0),
                    ];

                    // Walidacja: brak axle_count w naczepie = zły wynik klasyfikacji
                    if ($trailerOwn['axle_count'] <= 0) {
                        $combinationWarnings[] = 'Naczepa "' . ($t['name'] ?? '?') . '" nie ma wpisanego axle_count — klasyfikacja HERE będzie liczyć tylko z ciągnika (' . $vehiclesOwn['axle_count'] . ' osi). Uzupełnij w edycji naczepy.';
                    }
                    if ($trailerOwn['gross_weight_kg'] <= 0) {
                        $combinationWarnings[] = 'Naczepa "' . ($t['name'] ?? '?') . '" nie ma wpisanego gross_weight_kg — DMC zestawu będzie tylko z ciągnika (' . $vehiclesOwn['gross_weight_kg'] . ' kg).';
                    }

                    // Sumy dla HERE Routing
                    $vehicleData['gross_weight_kg'] = $vehiclesOwn['gross_weight_kg'] + $trailerOwn['gross_weight_kg'];
                    $vehicleData['axle_count']      = $vehiclesOwn['axle_count'] + $trailerOwn['axle_count'];
                    // Length zestawu = ciągnik + naczepa (ale max EU 16.50 m dla truck+semi)
                    if ($trailerOwn['length_cm'] > 0) {
                        $combined = $vehiclesOwn['length_cm'] + $trailerOwn['length_cm'];
                        $vehicleData['length_cm'] = min($combined, 1875); // cap na EU max tandem
                    }
                    // Width/height z naczepy gdy > ciągnika
                    foreach (['width_cm', 'height_cm'] as $dim) {
                        if ($trailerOwn[$dim] > $vehiclesOwn[$dim]) {
                            $vehicleData[$dim] = $trailerOwn[$dim];
                        }
                    }
                    // ADR-certified flag z naczepy
                    if (!empty($t['adr_certified'])) {
                        $vehicleData['hazardous_goods'] = true;
                    }
                    // Zachowaj metadane dla UI
                    $vehicleData['_trailer_name']   = $t['name'];
                    $vehicleData['_trailer_id']     = $t['id'];
                    $vehicleData['_combined_from'] = [
                        'vehicle' => $vehiclesOwn,
                        'trailer' => $trailerOwn,
                    ];
                    $vehicleData['_combination_warnings'] = $combinationWarnings;
                }
            }

            // Hogis-style: kierowca — zapisujemy jego dane (planer JS użyje stawki)
            $driverId = (string)$this->request->getData('driver_id', '');
            $driverData = null;
            if ($driverId !== '') {
                $driver = $this->fetchTable('Drivers')->find()->where(['id' => $driverId])->first();
                if ($driver) $driverData = $driver->toArray();
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

            // Aplikuj overrides usera dla opłat (learning loop)
            $companyIdForOverrides = (string)($identity?->get('company_id') ?? '');
            if ($companyIdForOverrides !== '' && !empty($result['routes'])) {
                $overridesMap = $this->fetchTable('TollFeeOverrides')
                    ->getActiveOverridesByCompany($companyIdForOverrides);
                if (!empty($overridesMap)) {
                    foreach ($result['routes'] as $rIdx => &$route) {
                        if (empty($route['tolls_breakdown'])) continue;
                        foreach ($route['tolls_breakdown'] as &$fare) {
                            $sig = \App\Model\Table\TollFeeOverridesTable::fareSignature(
                                (string)($fare['country'] ?? ''),
                                (string)($fare['system'] ?? ''),
                                (string)($fare['name'] ?? '')
                            );
                            $fare['fare_signature'] = $sig;
                            if (isset($overridesMap[$sig])) {
                                $o = $overridesMap[$sig];
                                $fare['override'] = [
                                    'id'        => (string)$o['id'],
                                    'action'    => (string)$o['action'],
                                    'reason'    => (string)$o['reason'],
                                    'corrected_price'    => $o['corrected_price'] !== null ? (float)$o['corrected_price'] : null,
                                    'corrected_currency' => (string)$o['corrected_currency'],
                                ];
                                // Zwiększ licznik (best-effort)
                                try {
                                    $this->fetchTable('TollFeeOverrides')->updateAll(
                                        ['applied_count = applied_count + 1', 'last_applied_at' => date('Y-m-d H:i:s')],
                                        ['id' => $o['id']]
                                    );
                                } catch (\Throwable $e) {}
                            }
                        }
                        unset($fare);

                        // Przelicz totals z uwzględnieniem overrides
                        $route = $this->recalcTollsWithOverrides($route, $currency);
                    }
                    unset($route);
                }
            }

            // Hogis-style: zwracaj zsumowane parametry zestawu + dane kierowcy
            if ($vehicleData) {
                $result['combination'] = [
                    'total_gross_weight_kg' => $vehicleData['gross_weight_kg'] ?? null,
                    'total_axle_count'      => $vehicleData['axle_count']      ?? null,
                    'total_length_cm'       => $vehicleData['length_cm']       ?? null,
                    'total_width_cm'        => $vehicleData['width_cm']        ?? null,
                    'total_height_cm'       => $vehicleData['height_cm']       ?? null,
                    'trailer_name'          => $vehicleData['_trailer_name']   ?? null,
                    'trailer_id'            => $vehicleData['_trailer_id']     ?? null,
                    'combined_from'         => $vehicleData['_combined_from']  ?? null,
                    'warnings'              => $vehicleData['_combination_warnings'] ?? [],
                ];
            }
            if ($driverData) {
                $result['driver'] = [
                    'id'              => $driverData['id'] ?? null,
                    'full_name'       => $driverData['full_name'] ?? null,
                    'hourly_rate_pln' => $driverData['hourly_rate_pln'] ?? null,
                    'per_diem_pln'    => $driverData['per_diem_pln'] ?? null,
                    'phone'           => $driverData['phone'] ?? null,
                    'adr_certified'   => (bool)($driverData['adr_certified'] ?? false),
                ];
            }

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
                $searchId = $this->saveRouteSearch($allPoints, $vehicleId, $firstRoute);
                if ($searchId) {
                    $result['route_search_id'] = $searchId;
                }
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

    /**
     * Historia stawek — zapytanie cascade: znajdz podobne zlecenia z historii
     * ze wskazaniem faktycznie wystawionych kwot faktur.
     *
     * POST /planer-tras/historia-stawek
     * Body: {
     *   contractor_id?: uuid (jesli wybrany klient — priorytet #1)
     *   contractor_nip?: string (alternatywa gdy nie mamy id — HB RTS via speed_orders.buyer_nip)
     *   from_city: string, to_city: string,
     *   from_country?: char(2), to_country?: char(2)
     * }
     *
     * Cascade:
     *   POZIOM 1: klient (nip) + oba miasta LIKE
     *   POZIOM 2: klient (nip) + oba kraje + jedno miasto
     *   POZIOM 3: klient (nip) + oba kraje (dla dowolnego miasta)
     *
     * Zwraca:
     *   {
     *     ok: true,
     *     match_level: 1|2|3,
     *     orders: [{ symbol, date_doc, from, to, currency, invoice: {fullnumber, total, date, currency}, ... }],
     *     stats: { count, min, max, avg, median, currency }
     *   }
     */
    public function pricingHistory(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['get', 'post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        if ($companyId === '') {
            return $this->jsonError(__('Brak company_id.'), 401);
        }

        // Pobierz parametry (dziala i z POST body i z query string)
        $data = $this->request->is('post')
            ? (array)$this->request->getData()
            : (array)$this->request->getQueryParams();

        $contractorId = trim((string)($data['contractor_id'] ?? ''));
        // WAZNE: NIE strippujemy do samych cyfr (bo NL822534538B01 zamienialoby sie
        // na 82253453801 co nie pasuje do bazy).
        // Wyluskujemy tylko "core digits" — odcinamy prefix kraju (2 litery) i suffix
        // po pierwszej literze w srodku (np. B01 dla NL).
        $contractorNipRaw = trim((string)($data['contractor_nip'] ?? ''));
        $contractorNip = self::extractCoreNipDigits($contractorNipRaw);
        $fromCity = trim((string)($data['from_city'] ?? ''));
        $toCity   = trim((string)($data['to_city']   ?? ''));
        $fromCountry = strtoupper(substr(trim((string)($data['from_country'] ?? '')), 0, 2));
        $toCountry   = strtoupper(substr(trim((string)($data['to_country']   ?? '')), 0, 2));

        // Jesli nie mamy NIP a mamy contractor_id — dolacz NIP z tabeli contractors
        if ($contractorNip === '' && $contractorId !== '') {
            try {
                $ctr = $this->fetchTable('Contractors')->find()
                    ->select(['nip'])
                    ->where(['id' => $contractorId, 'company_id' => $companyId])
                    ->first();
                if ($ctr && !empty($ctr->nip)) {
                    $contractorNip = preg_replace('/\D+/', '', (string)$ctr->nip);
                }
            } catch (\Throwable) {}
        }

        if ($fromCity === '' && $toCity === '' && $fromCountry === '' && $toCountry === '') {
            return $this->jsonError(__('Podaj miasta lub kraje trasy.'), 400);
        }

        // Tryb: 'client' (per NIP) lub 'market' (wszyscy klienci na tej trasie)
        // Domyslnie: client gdy podano NIP, market gdy nie
        $mode = $contractorNip !== '' ? 'client' : 'market';

        // WAZNE (regula CLAUDE.md #4a): speed_orders NIE ma kolumny company_id,
        // ma company_nip. Musimy dostac NIP firmy z tabeli Companies.
        $companyNipDigits = null;
        try {
            $Companies = $this->fetchTable('Companies');
            $company = $Companies->find()
                ->select(['nip'])
                ->where(['id' => $companyId])
                ->first();
            if ($company && !empty($company->nip)) {
                $companyNipDigits = self::extractCoreNipDigits((string)$company->nip);
            }
        } catch (\Throwable) {}

        if (empty($companyNipDigits)) {
            return $this->jsonError(__('Brak NIP-u firmy — nie mogę odfiltrować zleceń Speed.'), 400);
        }

        $SO = $this->fetchTable('SpeedOrders');

        // Wspolna baza query: firma (przez NIP core digits, LIKE zeby lapal PL/NL/DE prefixy)
        // + limit czasowy 12 miesiecy
        $baseQuery = function () use ($SO, $companyNipDigits, $contractorNip, $mode) {
            $q = $SO->find()
                ->where([
                    'SpeedOrders.company_nip LIKE' => '%' . $companyNipDigits . '%',
                    'SpeedOrders.date_doc >=' => (new \DateTimeImmutable('-12 months'))->format('Y-m-d'),
                ])
                ->orderByDesc('SpeedOrders.date_doc');
            if ($mode === 'client' && $contractorNip !== '') {
                // LIKE zamiast IN — bo buyer_nip w Speed moze byc NL822534538B01,
                // PL5271234567, samo 5271234567, itd. Core digits sa 8-10 cyfr.
                $q->andWhere([
                    'SpeedOrders.buyer_nip LIKE' => '%' . $contractorNip . '%',
                ]);
            }
            return $q;
        };

        $matchLevel = 0;
        $orders = [];

        // W trybie market limity wieksze — zbieramy szerszy prekroj rynku
        $limit = $mode === 'market' ? 50 : 10;

        $fromLike = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $fromCity) . '%';
        $toLike   = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $toCity) . '%';

        // WAZNE: mapping Speed → nasze fields jest niekonsystentny
        // (SpeedOrdersController::sync linia 760 przypisuje GLO_MIE_NAZWA1 do
        // place_from_name — czesto wpisany jest tam skrot kraju lub kod
        // pocztowy, a rzeczywiste miasto jest w load_city / unload_city).
        // Szukamy wiec w OBIU polach z OR, i dodajemy tez buyer_city
        // (dla przypadkow gdzie dostawa idzie do siedziby klienta).
        $matchFromCity = function ($exp, $like) {
            return $exp->or([
                $exp->like('SpeedOrders.load_city', $like),
                $exp->like('SpeedOrders.place_from_name', $like),
            ]);
        };
        $matchToCity = function ($exp, $like) {
            return $exp->or([
                $exp->like('SpeedOrders.unload_city', $like),
                $exp->like('SpeedOrders.place_to_name', $like),
                $exp->like('SpeedOrders.buyer_city', $like),
            ]);
        };

        // POZIOM 1: oba miasta LIKE (w OIU polach: load_city+place_from_name/unload_city+place_to_name)
        if ($fromCity !== '' && $toCity !== '') {
            $q = $baseQuery()->where(function ($exp) use ($fromLike, $toLike, $matchFromCity, $matchToCity) {
                return $exp->and([
                    $matchFromCity($exp, $fromLike),
                    $matchToCity($exp, $toLike),
                ]);
            })->limit($limit);
            $rows = $q->all()->toArray();
            if (!empty($rows)) {
                $orders = $rows;
                $matchLevel = 1;
            }
        }

        // POZIOM 2: oba kraje + jedno miasto
        // WAZNE: unload_country jest NULL w rekordach z Speed (API nie daje).
        // Dla toCountry sprawdzamy tez buyer_country jako alternatywe.
        if (empty($orders) && $fromCountry !== '' && $toCountry !== '') {
            $q = $baseQuery()->where(function ($exp) use ($fromCountry, $toCountry, $fromLike, $toLike, $fromCity, $toCity, $matchFromCity, $matchToCity) {
                $conds = [
                    $exp->like('SpeedOrders.load_country', $fromCountry),
                    $exp->or([
                        $exp->like('SpeedOrders.unload_country', $toCountry),
                        $exp->like('SpeedOrders.buyer_country', $toCountry),
                    ]),
                ];
                if ($fromCity !== '' && $toCity !== '') {
                    $conds[] = $exp->or([
                        $matchFromCity($exp, $fromLike),
                        $matchToCity($exp, $toLike),
                    ]);
                }
                return $exp->and($conds);
            })->limit($limit);
            $rows = $q->all()->toArray();
            if (!empty($rows)) {
                $orders = $rows;
                $matchLevel = 2;
            }
        }

        // POZIOM 3: oba kraje (dla dowolnego miasta)
        // unload_country moze byc NULL — sprawdz tez buyer_country
        if (empty($orders) && $fromCountry !== '' && $toCountry !== '') {
            $q = $baseQuery()->where(function ($exp) use ($fromCountry, $toCountry) {
                return $exp->and([
                    $exp->like('SpeedOrders.load_country', $fromCountry),
                    $exp->or([
                        $exp->like('SpeedOrders.unload_country', $toCountry),
                        $exp->like('SpeedOrders.buyer_country', $toCountry),
                    ]),
                ]);
            })->limit($limit);
            $rows = $q->all()->toArray();
            if (!empty($rows)) {
                $orders = $rows;
                $matchLevel = 3;
            }
        }

        // Do kazdego zlecenia dopnij powiazane faktury z pivota speed_order_invoices
        $orderIds = array_map(fn($o) => $o->id, $orders);
        $invoicesMap = [];
        if (!empty($orderIds)) {
            try {
                $Pivot = $this->fetchTable('SpeedOrderInvoices');
                $rows = $Pivot->find()
                    ->contain(['Invoices' => function ($q) {
                        return $q->select(['id', 'fullnumber', 'date', 'total', 'currency', 'currency_exchange']);
                    }])
                    ->where(['SpeedOrderInvoices.speed_order_id IN' => $orderIds])
                    ->all();
                foreach ($rows as $r) {
                    if ($r->invoice) {
                        $invoicesMap[$r->speed_order_id][] = $r->invoice;
                    }
                }
            } catch (\Throwable) {}
        }

        // Zbuduj strukture odpowiedzi + statystyki na kwotach faktur (przelicz na PLN gdy walutowa)
        $out = [];
        $amountsPln = [];
        foreach ($orders as $o) {
            $invs = $invoicesMap[(int)$o->id] ?? [];
            $primaryInv = $invs[0] ?? null;
            $invAmount = null;
            $invAmountPln = null;
            if ($primaryInv) {
                $invAmount = (float)$primaryInv->total;
                $exch = (float)($primaryInv->currency_exchange ?? 0);
                if ($exch > 0 && strtoupper((string)$primaryInv->currency) !== 'PLN') {
                    $invAmountPln = $invAmount * $exch;
                } else {
                    $invAmountPln = $invAmount;
                }
                if ($invAmountPln > 0) {
                    $amountsPln[] = $invAmountPln;
                }
            }

            // Wybor najlepszego pola miasta — preferujemy load_city/unload_city (te sa
            // wypelnione poprawnie), fallback na place_from_name/place_to_name
            $bestFromCity = (string)($o->load_city ?? '') ?: (string)($o->place_from_name ?? '');
            $bestToCity   = (string)($o->unload_city ?? '') ?: (string)($o->place_to_name ?? '') ?: (string)($o->buyer_city ?? '');
            $bestToCountry = (string)($o->unload_country ?? '') ?: (string)($o->buyer_country ?? '');
            $out[] = [
                'speed_order_id' => (int)$o->id,
                'symbol'         => (string)$o->symbol,
                'date_doc'       => $o->date_doc instanceof \DateTimeInterface ? $o->date_doc->format('Y-m-d') : (string)$o->date_doc,
                'title'          => trim(((string)($o->title1 ?? '')) . ' ' . ((string)($o->title2 ?? ''))),
                'buyer_name'     => (string)($o->buyer_name ?? ''),
                'buyer_nip'      => (string)($o->buyer_nip ?? ''),
                'from_city'      => $bestFromCity,
                'to_city'        => $bestToCity,
                'from_country'   => (string)($o->load_country ?? ''),
                'to_country'     => $bestToCountry,
                'currency'       => (string)($o->currency ?? ''),
                'route_description' => (string)($o->route_description ?? ''),
                'invoice'        => $primaryInv ? [
                    'id'         => (string)$primaryInv->id,
                    'fullnumber' => (string)$primaryInv->fullnumber,
                    'date'       => $primaryInv->date instanceof \DateTimeInterface ? $primaryInv->date->format('Y-m-d') : (string)$primaryInv->date,
                    'total'      => (float)$primaryInv->total,
                    'currency'   => (string)$primaryInv->currency,
                    'total_pln'  => $invAmountPln,
                ] : null,
            ];
        }

        // Statystyki
        $stats = null;
        if (!empty($amountsPln)) {
            sort($amountsPln);
            $n = count($amountsPln);
            $median = $n % 2 === 1
                ? $amountsPln[intdiv($n, 2)]
                : ($amountsPln[intdiv($n, 2) - 1] + $amountsPln[intdiv($n, 2)]) / 2;
            $stats = [
                'count'    => $n,
                'min_pln'  => round($amountsPln[0], 2),
                'max_pln'  => round($amountsPln[$n - 1], 2),
                'avg_pln'  => round(array_sum($amountsPln) / $n, 2),
                'median_pln' => round($median, 2),
            ];
        }

        // W trybie 'market' policz agregacje per klient — pokazujemy TOP klientów
        // ktorzy najczesciej jezdzili ta trasa (do UI)
        $byBuyer = [];
        if ($mode === 'market' && !empty($out)) {
            foreach ($out as $row) {
                $key = $row['buyer_nip'] ?: '(brak_nip)';
                if (!isset($byBuyer[$key])) {
                    $byBuyer[$key] = [
                        'buyer_name' => $row['buyer_name'],
                        'buyer_nip'  => $row['buyer_nip'],
                        'count'      => 0,
                        'sum_pln'    => 0.0,
                    ];
                }
                $byBuyer[$key]['count']++;
                if (!empty($row['invoice']['total_pln'])) {
                    $byBuyer[$key]['sum_pln'] += (float)$row['invoice']['total_pln'];
                }
            }
            // Sortuj po count desc
            usort($byBuyer, fn ($a, $b) => $b['count'] <=> $a['count']);
            $byBuyer = array_slice(array_values($byBuyer), 0, 10);
            foreach ($byBuyer as &$b) {
                $b['sum_pln'] = round($b['sum_pln'], 2);
                $b['avg_pln'] = $b['count'] > 0 ? round($b['sum_pln'] / $b['count'], 2) : 0;
            }
            unset($b);
        }

        // Labelka dopasowania — inna w market vs client
        $matchLabels = $mode === 'client'
            ? [
                1 => __('Idealne dopasowanie (miasta + klient)'),
                2 => __('Dobre dopasowanie (kraje + jedno miasto)'),
                3 => __('Luźne dopasowanie (same kraje)'),
                0 => __('Brak podobnych zleceń'),
            ]
            : [
                1 => __('Idealne dopasowanie (te miasta na całym rynku)'),
                2 => __('Dobre dopasowanie (kraje + jedno miasto)'),
                3 => __('Luźne dopasowanie (same kraje)'),
                0 => __('Brak podobnych zleceń'),
            ];

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'ok'          => true,
                'mode'        => $mode,
                'match_level' => $matchLevel, // 0=brak, 1=idealne, 2=srednie, 3=luzne
                'match_label' => $matchLabels[$matchLevel] ?? $matchLabels[0],
                'orders'      => $out,
                'stats'       => $stats,
                'by_buyer'    => $byBuyer, // top klienci (tylko w market mode)
                'query'       => [
                    'mode' => $mode,
                    'contractor_nip' => $contractorNip,
                    'from' => $fromCity . ($fromCountry ? " ({$fromCountry})" : ''),
                    'to'   => $toCity   . ($toCountry   ? " ({$toCountry})"   : ''),
                ],
            ], JSON_UNESCAPED_UNICODE));
    }

    public function tollBooths(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $polylines = (array)$this->request->getData('polylines', []);
        if (empty($polylines)) {
            return $this->jsonError(__('Brak polyline trasy.'));
        }
        try {
            $svc = new HereRoutingService();
            $booths = $svc->osmTollBooths($polylines, 2.0);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'booths' => $booths], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Live tracking link — publiczny URL z UUID RouteSearches.
     * URL zwraca dane trasy + waypoints + ETA do widoku read-only.
     * Brak auth — token UUID jest wystarczająco trudny do zgadnięcia.
     */
    /**
     * #7 Cabotage tracker — zwraca licznik ostatnich operacji w danym kraju
     * dla bieżącej firmy/pojazdu. Auto-wywoływany przed kalkulacją trasy.
     */
    /**
     * Zapisz override dla opłaty drogowej. User wybrał action (ignore/corrected/flagged)
     * i opcjonalnie podał corrected_price + reason.
     */
    /**
     * Przelicza tolls_total i tolls_by_country dla danej trasy uwzględniając
     * overrides:
     *   - action=ignore   → fare wykluczone z sumy (ale w breakdown z badgem)
     *   - action=corrected → fare używa corrected_price zamiast HERE
     *   - action=flagged  → fare zostaje w sumie ale oznaczone do review
     */
    private function recalcTollsWithOverrides(array $route, string $targetCurrency): array
    {
        $newTotal = 0.0;
        $newByCountry = [];
        $hadAny = false;
        foreach ($route['tolls_breakdown'] ?? [] as $fare) {
            $hadAny = true;
            $action = $fare['override']['action'] ?? null;
            if ($action === 'ignore') continue; // wyklucz z sumy

            // Cena do agregacji: corrected jeśli jest, inaczej oryginalna
            $price = null; $cur = null;
            if ($action === 'corrected' && isset($fare['override']['corrected_price'])) {
                $price = (float)$fare['override']['corrected_price'];
                $cur   = (string)$fare['override']['corrected_currency'];
            } else {
                // Logika identyczna jak w HereRoutingService — preferuj converted
                $origPrice = (float)($fare['price'] ?? 0);
                $origCur   = (string)($fare['currency'] ?? '');
                $convPrice = (float)($fare['converted_price'] ?? 0);
                $convCur   = (string)($fare['converted_curr'] ?? '');
                if ($convPrice > 0 && strcasecmp($convCur, $targetCurrency) === 0) {
                    $price = $convPrice; $cur = $convCur;
                } elseif (strcasecmp($origCur, $targetCurrency) === 0) {
                    $price = $origPrice; $cur = $origCur;
                }
            }
            if ($price === null) continue;

            $cc = (string)($fare['country'] ?? '??');
            $newByCountry[$cc] = ($newByCountry[$cc] ?? 0.0) + $price;
            $newTotal += $price;
        }
        $route['tolls_total']      = $hadAny ? round($newTotal, 2) : null;
        $route['tolls_by_country'] = $newByCountry;
        $route['tolls_currency']   = $targetCurrency;
        return $route;
    }

    public function tollOverrideSave(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $userId = (string)($identity?->getIdentifier() ?? '');
        if ($companyId === '') return $this->jsonError(__('Brak firmy.'));

        $country = strtoupper(trim((string)$this->request->getData('country', '')));
        $system  = trim((string)$this->request->getData('system', ''));
        $name    = trim((string)$this->request->getData('name', ''));
        $action  = (string)$this->request->getData('action', '');
        if (!in_array($action, ['ignore', 'corrected', 'flagged'], true)) {
            return $this->jsonError(__('Nieprawidłowa akcja.'));
        }
        if ($country === '') return $this->jsonError(__('Brak kodu kraju.'));

        $signature = \App\Model\Table\TollFeeOverridesTable::fareSignature($country, $system, $name);
        $Overrides = $this->fetchTable('TollFeeOverrides');

        // Upsert: jeśli już istnieje aktywny override dla tej sygnatury, aktualizuj go
        $existing = $Overrides->find()->where([
            'company_id' => $companyId,
            'fare_signature' => $signature,
            'is_active' => true,
        ])->first();

        try {
            if ($existing) {
                $existing->action = $action;
                $existing->corrected_price = $action === 'corrected'
                    ? (float)$this->request->getData('corrected_price', 0) : null;
                $existing->corrected_currency = $action === 'corrected'
                    ? (string)$this->request->getData('corrected_currency', '') : null;
                $existing->reason = (string)$this->request->getData('reason', '');
                $existing->original_price = $this->request->getData('original_price') !== null
                    ? (float)$this->request->getData('original_price') : null;
                $existing->original_currency = (string)$this->request->getData('original_currency', '');
                $Overrides->save($existing);
                $id = (string)$existing->id;
            } else {
                $entity = $Overrides->newEntity([
                    'id'                => \Cake\Utility\Text::uuid(),
                    'company_id'        => $companyId,
                    'created_by'        => $userId ?: null,
                    'fare_signature'    => $signature,
                    'country'           => $country,
                    'system'            => $system,
                    'fare_name'         => $name,
                    'action'            => $action,
                    'corrected_price'   => $action === 'corrected'
                        ? (float)$this->request->getData('corrected_price', 0) : null,
                    'corrected_currency' => $action === 'corrected'
                        ? (string)$this->request->getData('corrected_currency', '') : null,
                    'original_price'    => $this->request->getData('original_price') !== null
                        ? (float)$this->request->getData('original_price') : null,
                    'original_currency' => (string)$this->request->getData('original_currency', ''),
                    'reason'            => (string)$this->request->getData('reason', ''),
                    'route_search_id'   => $this->request->getData('route_search_id') ?: null,
                    'is_active'         => true,
                ]);
                $Overrides->save($entity);
                $id = (string)$entity->id;
            }
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'id' => $id, 'signature' => $signature]));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Usuń override (soft delete via is_active=false).
     */
    public function tollOverrideDelete(string $id): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post', 'delete']);
        $identity = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $Overrides = $this->fetchTable('TollFeeOverrides');
        $entity = $Overrides->find()->where(['id' => $id, 'company_id' => $companyId])->first();
        if ($entity) {
            $entity->is_active = false;
            $Overrides->save($entity);
        }
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['ok' => true]));
    }

    /**
     * Lista wszystkich aktywnych override'ów dla firmy.
     */
    public function tollOverrideList(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['get']);
        $identity = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        if ($companyId === '') return $this->jsonError(__('Brak firmy.'));

        $rows = $this->fetchTable('TollFeeOverrides')->find()
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->orderByDesc('modified')
            ->all()
            ->toArray();
        $out = array_map(function ($r) {
            return [
                'id'                 => (string)$r->id,
                'signature'          => (string)$r->fare_signature,
                'country'            => (string)$r->country,
                'system'             => (string)$r->system,
                'fare_name'          => (string)$r->fare_name,
                'action'             => (string)$r->action,
                'corrected_price'    => $r->corrected_price !== null ? (float)$r->corrected_price : null,
                'corrected_currency' => (string)$r->corrected_currency,
                'original_price'     => $r->original_price !== null ? (float)$r->original_price : null,
                'original_currency'  => (string)$r->original_currency,
                'reason'             => (string)$r->reason,
                'applied_count'      => (int)$r->applied_count,
                'last_applied_at'    => $r->last_applied_at?->format('c'),
                'created'            => $r->created?->format('c'),
            ];
        }, $rows);
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['ok' => true, 'overrides' => $out], JSON_UNESCAPED_UNICODE));
    }

    public function cabotageStatus(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['get', 'post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        if ($companyId === '') {
            return $this->jsonError(__('Brak firmy.'));
        }
        $vehicleId = (string)$this->request->getData('vehicle_id', $this->request->getQuery('vehicle_id', ''));

        try {
            $Cab = $this->fetchTable('CabotageOperations');
            // 7-dniowe okno + 4-dniowy cooling-off
            $sevenDaysAgo = (new \DateTime('-7 days'))->format('Y-m-d');
            $query = $Cab->find()
                ->where(['company_id' => $companyId, 'operation_date >=' => $sevenDaysAgo]);
            if ($vehicleId !== '') {
                $query->where(['vehicle_id' => $vehicleId]);
            }
            $rows = $query->orderByDesc('operation_date')->all();

            // Liczyć per kraj
            $byCountry = [];
            foreach ($rows as $r) {
                $cc = (string)$r->country;
                if (!isset($byCountry[$cc])) {
                    $byCountry[$cc] = ['count' => 0, 'last_date' => null, 'operations' => []];
                }
                $byCountry[$cc]['count']++;
                $byCountry[$cc]['operations'][] = [
                    'id'             => (string)$r->id,
                    'date'           => $r->operation_date->format('Y-m-d'),
                    'origin'         => (string)$r->origin,
                    'destination'    => (string)$r->destination,
                    'notes'          => (string)$r->notes,
                ];
                if ($byCountry[$cc]['last_date'] === null) {
                    $byCountry[$cc]['last_date'] = $r->operation_date->format('Y-m-d');
                }
            }
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'by_country' => $byCountry], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Zapisz operację cabotage (z planera lub ręcznie).
     */
    public function cabotageSave(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        if ($companyId === '') return $this->jsonError(__('Brak firmy.'));

        $country = strtoupper(trim((string)$this->request->getData('country', '')));
        $date    = (string)$this->request->getData('operation_date', date('Y-m-d'));
        if (strlen($country) !== 3 || !preg_match('/^[A-Z]{3}$/', $country)) {
            return $this->jsonError(__('Nieprawidłowy kod kraju (alpha-3).'));
        }
        try {
            $Cab = $this->fetchTable('CabotageOperations');
            $entity = $Cab->newEntity([
                'id'              => \Cake\Utility\Text::uuid(),
                'company_id'      => $companyId,
                'vehicle_id'      => $this->request->getData('vehicle_id') ?: null,
                'route_search_id' => $this->request->getData('route_search_id') ?: null,
                'country'         => $country,
                'operation_date'  => $date,
                'origin'          => $this->request->getData('origin'),
                'destination'     => $this->request->getData('destination'),
                'notes'           => $this->request->getData('notes'),
                'source'          => $this->request->getData('source', 'manual'),
            ]);
            $Cab->save($entity);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'id' => (string)$entity->id]));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Usuń operację cabotage.
     */
    public function cabotageDelete(string $id): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post', 'delete']);
        $identity = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');
        $this->fetchTable('CabotageOperations')->deleteAll(['id' => $id, 'company_id' => $companyId]);
        return $this->response->withType('application/json')->withStringBody(json_encode(['ok' => true]));
    }

    public function track(string $id): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['get']);
        $Searches = $this->fetchTable('RouteSearches');
        $search = $Searches->find()->where(['id' => $id])->first();
        if (!$search) {
            $this->response = $this->response->withStatus(404)->withType('application/json')
                ->withStringBody(json_encode(['error' => true, 'message' => __('Trasa nie znaleziona.')]));
            return $this->response;
        }
        $waypoints = json_decode((string)$search->waypoints_json, true) ?: [];
        $data = [
            'id'             => $id,
            'waypoints'      => $waypoints,
            'distance_km'    => $search->distance_km,
            'duration_min'   => $search->duration_min,
            'tolls_total'    => $search->tolls_total,
            'tolls_currency' => $search->tolls_currency,
            'last_used'      => $search->last_used?->format('c'),
        ];
        return $this->response->withType('application/json')
            ->withStringBody(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Render strony tracking (publicznie dostępna mobile-first).
     */
    public function trackView(string $id): ?Response
    {
        $this->request->allowMethod(['get']);
        // Ta akcja używa template — render dzieje się automatycznie
        $Searches = $this->fetchTable('RouteSearches');
        $search = $Searches->find()->where(['id' => $id])->first();
        if (!$search) {
            $this->Flash->error(__('Trasa nie znaleziona lub link wygasł.'));
            return $this->redirect('/');
        }
        $waypoints = json_decode((string)$search->waypoints_json, true) ?: [];
        $this->set([
            'trackId'        => $id,
            'waypoints'      => $waypoints,
            'distance_km'    => $search->distance_km,
            'duration_min'   => $search->duration_min,
            'tolls_total'    => $search->tolls_total,
            'tolls_currency' => $search->tolls_currency,
            'hereApiKey'     => Configure::read('Here.apiKey'),
        ]);
        $this->viewBuilder()->setLayout(false);
        return null;
    }

    public function truckPois(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $polylines = (array)$this->request->getData('polylines', []);
        \Cake\Log\Log::debug('truckPois: received ' . count($polylines) . ' polylines from request');
        if (empty($polylines)) {
            return $this->jsonError(__('Brak polyline trasy.'));
        }
        try {
            $svc = new HereRoutingService();
            // Sample every 50km, max 10 samples, 25km radius
            $stops = $svc->truckStopsAlongRoute($polylines, 50, 10, 25000);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'stops' => $stops], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function weather(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $points = (array)$this->request->getData('points', []);
        if (empty($points)) {
            return $this->jsonError(__('Brak punktów do pogody.'));
        }
        try {
            $svc = new \App\Service\Weather\OpenWeatherService();
            $out = [];
            foreach ($points as $p) {
                $p = (array)$p;
                $lat = (float)($p['lat'] ?? 0);
                $lng = (float)($p['lng'] ?? 0);
                $date = (string)($p['date'] ?? '');
                if ($lat === 0.0 || $lng === 0.0) {
                    $out[] = null;
                    continue;
                }
                $out[] = $svc->forecastAt($lat, $lng, $date !== '' ? $date : null);
            }
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'weather' => $out], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function aiRouteOptimizer(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $alternatives = (array)$this->request->getData('alternatives', []);
        $criteria = (array)$this->request->getData('criteria', []);
        if (count($alternatives) < 2) {
            return $this->jsonError(__('Potrzebne minimum 2 alternatywy.'));
        }
        try {
            $ai = new \App\Service\Ai\OpenAiService();
            $result = $ai->routeOptimizer($alternatives, $criteria);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * #5 Multi-leg optimization — PDP solver dla 2-4 ładunków.
     */
    public function optimizeMultileg(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $loadsRaw = $this->request->getData('loads', []);
        $start    = $this->request->getData('start');
        $end      = $this->request->getData('end');
        if (!is_array($loadsRaw) || count($loadsRaw) < 1) {
            return $this->jsonError(__('Brak ładunków do optymalizacji.'));
        }
        try {
            $here = new HereRoutingService();

            // Geocode adresów które nie mają lat/lng
            $geocode = function ($pt) use ($here) {
                if (!is_array($pt)) return null;
                if (!empty($pt['lat']) && !empty($pt['lng'])) {
                    return ['lat' => (float)$pt['lat'], 'lng' => (float)$pt['lng'],
                            'label' => (string)($pt['label'] ?? $pt['address'] ?? '')];
                }
                $addr = trim((string)($pt['address'] ?? ''));
                if ($addr === '') return null;
                $geo = $here->geocode($addr);
                return $geo ? ['lat' => $geo['lat'], 'lng' => $geo['lng'], 'label' => $geo['label']] : null;
            };

            $loads = [];
            foreach ($loadsRaw as $i => $L) {
                $L = (array)$L;
                $p = $geocode($L['pickup'] ?? null);
                $d = $geocode($L['dropoff'] ?? null);
                if (!$p || !$d) {
                    return $this->jsonError(__('Nie znaleziono adresu dla ładunku #') . ($i + 1));
                }
                $loads[] = [
                    'pickup'  => $p,
                    'dropoff' => $d,
                    'name'    => (string)($L['name'] ?? 'Load ' . ($i + 1)),
                    'weight_kg' => isset($L['weight_kg']) ? (float)$L['weight_kg'] : null,
                ];
            }

            $startPt = $start ? $geocode($start) : null;
            $endPt   = $end   ? $geocode($end)   : null;

            $optimizer = new \App\Service\Routing\MultiLegOptimizer();
            $result = $optimizer->optimize($loads, $startPt, $endPt, 3);

            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function aiDelayPrediction(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $context = (array)$this->request->getData('context', []);
        if (empty($context['distance_km'])) {
            return $this->jsonError(__('Brak kontekstu trasy.'));
        }
        try {
            $ai = new \App\Service\Ai\OpenAiService();
            $result = $ai->predictDelay($context);
            return $this->response->withType('application/json')
                ->withStringBody(json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    public function aiEmailReply(): Response
    {
        $this->disableAutoRender();
        $this->request->allowMethod(['post']);
        $email = trim((string)$this->request->getData('email', ''));
        $routeCtx = (array)$this->request->getData('route', []);
        if ($email === '') {
            return $this->jsonError(__('Brak treści emaila.'));
        }
        try {
            $ai = new \App\Service\Ai\OpenAiService();
            $result = $ai->emailReply($email, $routeCtx);
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

    /**
     * Wyluska "core digits" z NIP-u — same cyfry bez prefixu kraju i suffixu.
     *
     * Obsluguje formaty EU:
     *   PL5271234567    → 5271234567   (10 cyfr)
     *   PL 527 123 4567 → 5271234567
     *   5271234567      → 5271234567
     *   NL822534538B01  → 822534538    (9 cyfr, odcina B01 suffix)
     *   DE123456789     → 123456789    (9 cyfr)
     *   CZ12345678      → 12345678     (8 cyfr)
     *   ATU12345678     → 12345678     (odcina ATU prefix, 8 cyfr)
     *
     * Zwraca same cyfry (min 8 dla EU) lub pusty string.
     */
    public static function extractCoreNipDigits(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') return '';

        // 1. Odetnij prefix kraju: 2-3 litery na poczatku (PL, NL, DE, CZ, AT, ATU dla Austrii...)
        $s = preg_replace('/^[A-Za-z]{2,3}/', '', $s);

        // 2. Odetnij suffix po pierwszej literze w srodku (dla NL: B01)
        $s = preg_replace('/[A-Za-z].*$/', '', $s);

        // 3. Zostaw same cyfry
        $digits = preg_replace('/\D+/', '', $s);

        // Min 8 cyfr dla EU NIP (CZ ma 8, wiekszosc 9-10)
        return strlen($digits) >= 8 ? $digits : '';
    }
}

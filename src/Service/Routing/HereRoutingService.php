<?php
declare(strict_types=1);

namespace App\Service\Routing;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;
use RuntimeException;

/**
 * Wrapper na HERE Maps API (Geocoding + Routing v8) z profilem truck.
 *
 * https://developer.here.com/documentation/routing-api/8.69.0/dev_guide/index.html
 * Klucz API w app_local.php['Here']['apiKey'].
 */
class HereRoutingService
{
    private const GEOCODE_URL    = 'https://geocode.search.hereapi.com/v1/geocode';
    private const ROUTING_URL    = 'https://router.hereapi.com/v8/routes';
    private const REVGEO_URL     = 'https://revgeocode.search.hereapi.com/v1/revgeocode';
    private const AUTOSUGGEST_URL = 'https://autosuggest.search.hereapi.com/v1/autosuggest';

    private string $apiKey;
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $key = (string)Configure::read('Here.apiKey');
        if ($key === '') {
            throw new RuntimeException('Klucz HERE API nie jest skonfigurowany (Here.apiKey).');
        }
        $this->apiKey = $key;
        $this->client = $client ?? new Client(['timeout' => 15]);
    }

    /**
     * Geocoding: adres tekstowy → współrzędne lat,lng.
     * Zwraca null jeśli nie znalazł.
     *
     * @return array{lat: float, lng: float, label: string, country: string}|null
     */
    public function geocode(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }
        try {
            $resp = $this->client->get(self::GEOCODE_URL, [
                'q'       => $address,
                'limit'   => 1,
                'apiKey'  => $this->apiKey,
                'lang'    => 'pl-PL',
            ]);
            if (!$resp->isOk()) {
                Log::warning('HERE geocode HTTP ' . $resp->getStatusCode() . ': ' . $resp->getStringBody());
                return null;
            }
            $data = $resp->getJson();
            $item = $data['items'][0] ?? null;
            if (!$item || empty($item['position'])) {
                return null;
            }
            return [
                'lat'     => (float)$item['position']['lat'],
                'lng'     => (float)$item['position']['lng'],
                'label'   => (string)($item['title'] ?? $address),
                'country' => (string)($item['address']['countryCode'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::error('HERE geocode error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Liczy trasę między dwoma punktami z opcjonalnym profilem truck.
     *
     * @param array{lat:float,lng:float} $from
     * @param array{lat:float,lng:float} $to
     * @param array<string,mixed>|null $vehicle  parametry truck'a (waga, osie, wymiary, emisja)
     * @param array<string,mixed> $opts          dodatkowe opcje: avoid, departureTime, currency
     * @return array{
     *   distance_km: float,
     *   duration_min: int,
     *   tolls_total: float|null,
     *   tolls_currency: string,
     *   tolls_by_country: array<string,float>,
     *   polyline: string,
     *   raw: array
     * }
     */
    public function route(array $from, array $to, ?array $vehicle = null, array $opts = []): array
    {
        $mode = $vehicle ? 'truck' : 'car';
        $params = [
            'transportMode' => $mode,
            'origin'        => $from['lat'] . ',' . $from['lng'],
            'destination'   => $to['lat']   . ',' . $to['lng'],
            'return'        => 'summary,polyline,tolls',
            'currency'      => (string)($opts['currency'] ?? 'EUR'),
            'apiKey'        => $this->apiKey,
            'lang'          => 'pl-PL',
        ];
        if (!empty($opts['departureTime'])) {
            $params['departureTime'] = (string)$opts['departureTime']; // ISO 8601
        }
        if (!empty($opts['avoid'])) {
            // np. 'tollRoad', 'ferry', 'controlledAccessHighway'
            $params['avoid[features]'] = is_array($opts['avoid']) ? implode(',', $opts['avoid']) : (string)$opts['avoid'];
        }
        if ($vehicle) {
            // HERE Routing v8: vehicle[xxx]
            $map = [
                'grossWeight'      => 'gross_weight_kg',
                'weightPerAxle'    => 'axle_load_kg',
                'height'           => 'height_cm',     // cm → cm (HERE chce cm)
                'width'            => 'width_cm',
                'length'           => 'length_cm',
                'axleCount'        => 'axle_count',
                'tunnelCategory'   => 'tunnel_category',
                'shippedHazardousGoods' => null, // specjalny
                'emissionType'     => 'emission_class',
            ];
            foreach ($map as $hereKey => $vKey) {
                if ($vKey && !empty($vehicle[$vKey])) {
                    $params["vehicle[$hereKey]"] = (string)$vehicle[$vKey];
                }
            }
            if (!empty($vehicle['hazardous_goods'])) {
                $params['vehicle[shippedHazardousGoods]'] = 'explosive,gas,flammable,combustible,organic,poison,radioactive,corrosive,poisonousInhalation,harmfulToWater,other';
            }
        }

        try {
            $resp = $this->client->get(self::ROUTING_URL, $params);
            if (!$resp->isOk()) {
                $body = $resp->getStringBody();
                Log::warning('HERE routing HTTP ' . $resp->getStatusCode() . ': ' . $body);
                throw new RuntimeException('HERE Routing API zwróciło błąd ' . $resp->getStatusCode() . '. ' . $body);
            }
            $data = $resp->getJson();
            $section = $data['routes'][0]['sections'][0] ?? null;
            if (!$section) {
                throw new RuntimeException('Brak trasy w odpowiedzi HERE API.');
            }
            $summary = $section['summary'] ?? [];
            $distM = (int)($summary['length'] ?? 0);
            $durS  = (int)($summary['duration'] ?? 0);
            $polyline = (string)($section['polyline'] ?? '');

            // Toll'e per kraj
            $tollsByCountry = [];
            $tollsTotal = null;
            $tollsCurrency = (string)($opts['currency'] ?? 'EUR');
            if (!empty($section['tolls']) && is_array($section['tolls'])) {
                $tollsTotal = 0.0;
                foreach ($section['tolls'] as $toll) {
                    $country = (string)($toll['countryCode'] ?? '??');
                    foreach (($toll['fares'] ?? []) as $fare) {
                        $price = (float)($fare['price']['value'] ?? 0);
                        $cur = (string)($fare['price']['currency'] ?? $tollsCurrency);
                        $tollsByCountry[$country] = ($tollsByCountry[$country] ?? 0.0) + $price;
                        $tollsTotal += $price;
                        $tollsCurrency = $cur;
                    }
                }
            }

            return [
                'distance_km'      => round($distM / 1000, 1),
                'duration_min'     => (int)round($durS / 60),
                'tolls_total'      => $tollsTotal !== null ? round($tollsTotal, 2) : null,
                'tolls_currency'   => $tollsCurrency,
                'tolls_by_country' => $tollsByCountry,
                'polyline'         => $polyline,
                'raw'              => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('HERE routing error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reverse geocoding — współrzędne → adres tekstowy.
     * @return array{lat: float, lng: float, label: string, country: string}|null
     */
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        try {
            $resp = $this->client->get(self::REVGEO_URL, [
                'at'     => $lat . ',' . $lng,
                'limit'  => 1,
                'apiKey' => $this->apiKey,
                'lang'   => 'pl-PL',
            ]);
            if (!$resp->isOk()) return null;
            $data = $resp->getJson();
            $item = $data['items'][0] ?? null;
            if (!$item) return null;
            $pos = $item['position'] ?? ['lat' => $lat, 'lng' => $lng];
            return [
                'lat'     => (float)$pos['lat'],
                'lng'     => (float)$pos['lng'],
                'label'   => (string)($item['address']['label'] ?? ($item['title'] ?? '')),
                'country' => (string)($item['address']['countryCode'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::error('HERE reverseGeocode error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Autosuggest — szybkie podpowiedzi adresów do input'a (typeahead).
     * @param string $q  częściowy adres (min 1 znak)
     * @param array{lat: float, lng: float}|null $proximity centrum geograficzne dla rankingu
     * @return array<int, array{title:string,label:string,lat:?float,lng:?float,country:string,id:string}>
     */
    public function autosuggest(string $q, ?array $proximity = null): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 1) return [];
        $params = [
            'q'      => $q,
            'limit'  => 8,
            'apiKey' => $this->apiKey,
            'lang'   => 'pl-PL',
            'at'     => $proximity
                ? ($proximity['lat'] . ',' . $proximity['lng'])
                : '52.0,19.0', // domyślnie środek Polski
            'in'     => 'countryCode:POL,DEU,CZE,SVK,UKR,LTU,LVA,EST,BLR,AUT,HUN,FRA,ESP,ITA,NLD,BEL,DNK,SWE,NOR,FIN,GBR,IRL,CHE,ROU,BGR,GRC,PRT,SVN,HRV,LUX',
        ];
        try {
            $resp = $this->client->get(self::AUTOSUGGEST_URL, $params);
            if (!$resp->isOk()) {
                Log::warning('HERE autosuggest HTTP ' . $resp->getStatusCode());
                return [];
            }
            $data = $resp->getJson();
            $out = [];
            foreach (($data['items'] ?? []) as $item) {
                $pos = $item['position'] ?? null;
                $out[] = [
                    'id'      => (string)($item['id'] ?? ''),
                    'title'   => (string)($item['title'] ?? ''),
                    'label'   => (string)($item['address']['label'] ?? ($item['title'] ?? '')),
                    'lat'     => $pos ? (float)$pos['lat'] : null,
                    'lng'     => $pos ? (float)$pos['lng'] : null,
                    'country' => (string)($item['address']['countryCode'] ?? ''),
                    'type'    => (string)($item['resultType'] ?? ''),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::error('HERE autosuggest error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Trasa z wieloma punktami (waypoints) i opcjonalnie alternatywami.
     * Zwraca tablicę tras (każda z polyline, summary, tolls, instructions).
     *
     * @param array{lat:float,lng:float}        $from
     * @param array{lat:float,lng:float}        $to
     * @param array<int,array{lat:float,lng:float}>  $vias  pośrednie punkty
     * @param array<string,mixed>|null          $vehicle
     * @param array{alternatives?:int,avoid?:array|string,currency?:string,departureTime?:string,returnInstructions?:bool}  $opts
     * @return array{
     *   routes: array<int, array{
     *     distance_km: float, duration_min: int,
     *     tolls_total: float|null, tolls_currency: string, tolls_by_country: array<string,float>,
     *     polyline: string,
     *     instructions: array<int, array{text:string,distance_m:int,duration_s:int}>,
     *     sections: array<int, array{from_lat:float,from_lng:float,to_lat:float,to_lng:float,distance_km:float,duration_min:int}>
     *   }>
     * }
     */
    public function routeMulti(array $from, array $to, array $vias = [], ?array $vehicle = null, array $opts = []): array
    {
        $alternatives = max(0, min(6, (int)($opts['alternatives'] ?? 0)));
        $currency = (string)($opts['currency'] ?? 'EUR');
        $returnInstr = !empty($opts['returnInstructions']);

        // HERE Routing v8 — valid `return` values include: summary, polyline, tolls, actions, instructions.
        // tollSystems/tollLocations to były ZŁE nazwy (HERE 400 E605001). Locations bramek
        // przychodzą wewnątrz każdego toll[] jako tollSegments[].entry/exit jeśli są dostępne.
        $returns = ['summary', 'polyline', 'tolls'];
        if ($returnInstr) {
            $returns[] = 'actions';
            $returns[] = 'instructions';
        }

        $params = [
            'transportMode' => $vehicle ? 'truck' : 'car',
            'origin'        => $from['lat'] . ',' . $from['lng'],
            'destination'   => $to['lat']   . ',' . $to['lng'],
            'return'        => implode(',', $returns),
            'currency'      => $currency,
            'apiKey'        => $this->apiKey,
            'lang'          => 'pl-PL',
            'alternatives'  => (string)$alternatives,
        ];
        if (!empty($opts['departureTime'])) {
            $params['departureTime'] = (string)$opts['departureTime'];
        }
        if (!empty($opts['avoid'])) {
            $params['avoid[features]'] = is_array($opts['avoid']) ? implode(',', $opts['avoid']) : (string)$opts['avoid'];
        }
        if (!empty($opts['excludeCountries'])) {
            // ISO 3166-1 alpha-3, np. POL,DEU,CZE
            $countries = is_array($opts['excludeCountries']) ? implode(',', $opts['excludeCountries']) : (string)$opts['excludeCountries'];
            if ($countries !== '') {
                $params['exclude[countries]'] = $countries;
            }
        }
        if (!empty($opts['adrClass'])) {
            // ADR class 1-9 → mapping na HERE shippedHazardousGoods
            $adrMap = [
                '1' => 'explosive',
                '2' => 'gas',
                '3' => 'flammable',
                '4' => 'combustible',
                '5' => 'organic',
                '6' => 'poison',
                '7' => 'radioactive',
                '8' => 'corrosive',
                '9' => 'other',
            ];
            $adr = (string)$opts['adrClass'];
            if (isset($adrMap[$adr])) {
                $params['vehicle[shippedHazardousGoods]'] = $adrMap[$adr];
            }
        }
        if ($vehicle) {
            $map = [
                'grossWeight'    => 'gross_weight_kg',
                'weightPerAxle'  => 'axle_load_kg',
                'height'         => 'height_cm',
                'width'          => 'width_cm',
                'length'         => 'length_cm',
                'axleCount'      => 'axle_count',
                'tunnelCategory' => 'tunnel_category',
                'emissionType'   => 'emission_class',
            ];
            foreach ($map as $hereKey => $vKey) {
                if (!empty($vehicle[$vKey])) {
                    $params["vehicle[$hereKey]"] = (string)$vehicle[$vKey];
                }
            }
            if (!empty($vehicle['hazardous_goods'])) {
                $params['vehicle[shippedHazardousGoods]'] = 'explosive,gas,flammable,combustible,organic,poison,radioactive,corrosive,poisonousInhalation,harmfulToWater,other';
            }
        }

        // HERE expects repeated `via=lat,lng` params (NOT via[0]=, via[1]=)
        // Cake Client uses http_build_query which produces array syntax, so
        // build the URL manually here.
        $queryParts = [];
        foreach ($params as $k => $v) {
            $queryParts[] = rawurlencode($k) . '=' . rawurlencode((string)$v);
        }
        foreach ($vias as $via) {
            if (!empty($via['lat']) && !empty($via['lng'])) {
                $queryParts[] = 'via=' . rawurlencode($via['lat'] . ',' . $via['lng']);
            }
        }
        $url = self::ROUTING_URL . '?' . implode('&', $queryParts);

        try {
            $resp = $this->client->get($url);
            if (!$resp->isOk()) {
                $body = $resp->getStringBody();
                Log::warning('HERE routing HTTP ' . $resp->getStatusCode() . ': ' . $body);
                throw new RuntimeException('HERE Routing API zwróciło błąd ' . $resp->getStatusCode() . '. ' . $body);
            }
            $data = $resp->getJson();
            if (empty($data['routes'])) {
                throw new RuntimeException('Brak tras w odpowiedzi HERE API.');
            }

            $routesOut = [];
            foreach ($data['routes'] as $route) {
                $polylines = [];
                $totalDist = 0;
                $totalDur  = 0;
                $tollsTotal = null;
                $tollsByCountry = [];
                $tollsCurrency = $currency;
                $instructions = [];
                $sections = [];
                $tollsBreakdown = [];   // L1: szczegółowe opłaty
                $vignettes = [];         // L2: winiety
                $tollLocations = [];     // L3: lokalizacje bramek na mapie

                foreach (($route['sections'] ?? []) as $sect) {
                    $summary = $sect['summary'] ?? [];
                    $sd = (int)($summary['length'] ?? 0);
                    $st = (int)($summary['duration'] ?? 0);
                    $totalDist += $sd;
                    $totalDur  += $st;
                    if (!empty($sect['polyline'])) {
                        $polylines[] = (string)$sect['polyline'];
                    }
                    $dep = $sect['departure']['place']['location'] ?? null;
                    $arr = $sect['arrival']['place']['location'] ?? null;
                    if ($dep && $arr) {
                        $sections[] = [
                            'from_lat'       => (float)$dep['lat'],
                            'from_lng'       => (float)$dep['lng'],
                            'to_lat'         => (float)$arr['lat'],
                            'to_lng'         => (float)$arr['lng'],
                            'distance_km'    => round($sd / 1000, 1),
                            'duration_min'   => (int)round($st / 60),
                            'departure_time' => (string)($sect['departure']['time'] ?? ''),
                            'arrival_time'   => (string)($sect['arrival']['time']   ?? ''),
                        ];
                    }

                    foreach (($sect['tolls'] ?? []) as $toll) {
                        $cc = (string)($toll['countryCode'] ?? '??');
                        $tollSystem  = (string)($toll['tollSystem'] ?? '');
                        $paymentMethods = (array)($toll['paymentMethods'] ?? []);

                        // L3: lokalizacje bramek — HERE czasem zwraca je w toll.tollSegments[]
                        foreach (($toll['tollSegments'] ?? []) as $seg) {
                            foreach (['entry', 'exit'] as $point) {
                                $loc = $seg[$point]['location'] ?? null;
                                if ($loc && isset($loc['lat'], $loc['lng'])) {
                                    $tollLocations[] = [
                                        'lat'     => (float)$loc['lat'],
                                        'lng'     => (float)$loc['lng'],
                                        'country' => $cc,
                                        'name'    => (string)($seg['name'] ?? ($point === 'entry' ? 'Wjazd' : 'Wyjazd')),
                                        'system'  => $tollSystem,
                                    ];
                                }
                            }
                        }
                        foreach (($toll['fares'] ?? []) as $fare) {
                            $price = (float)($fare['price']['value'] ?? 0);
                            $cur   = (string)($fare['price']['currency'] ?? $currency);
                            $convPrice = (float)($fare['convertedPrice']['value'] ?? 0);
                            $convCur   = (string)($fare['convertedPrice']['currency'] ?? $cur);
                            $reason = (string)($fare['reason'] ?? '');
                            $name   = (string)($fare['name'] ?? '');
                            $fpm    = (string)($fare['paymentMethod'] ?? (is_array($paymentMethods) && count($paymentMethods) ? implode(',', $paymentMethods) : ''));
                            $passValidity = (string)($fare['passValidityPeriod'] ?? '');

                            $isVignette = (stripos($reason, 'vignette') !== false) || $passValidity !== '';

                            $tollsByCountry[$cc] = ($tollsByCountry[$cc] ?? 0.0) + $price;
                            $tollsTotal = ($tollsTotal ?? 0.0) + $price;
                            $tollsCurrency = $cur;

                            // L1: szczegółowy wpis (per fare)
                            $tollsBreakdown[] = [
                                'country'          => $cc,
                                'system'           => $tollSystem,
                                'name'             => $name,
                                'price'            => round($price, 2),
                                'currency'         => $cur,
                                'converted_price'  => $convPrice > 0 ? round($convPrice, 2) : null,
                                'converted_curr'   => $convPrice > 0 ? $convCur : null,
                                'payment_method'   => $fpm,
                                'reason'           => $reason,
                                'is_vignette'      => $isVignette,
                                'pass_validity'    => $passValidity,
                            ];

                            // L2: winieta — zbieramy dedup'owane (country + system + validity)
                            if ($isVignette) {
                                $vkey = $cc . '|' . $tollSystem . '|' . $passValidity;
                                if (!isset($vignettes[$vkey])) {
                                    $vignettes[$vkey] = [
                                        'country'        => $cc,
                                        'system'         => $tollSystem,
                                        'name'           => $name,
                                        'price'          => round($price, 2),
                                        'currency'       => $cur,
                                        'pass_validity'  => $passValidity,
                                        'payment_method' => $fpm,
                                    ];
                                }
                            }
                        }
                    }


                    if ($returnInstr) {
                        foreach (($sect['actions'] ?? []) as $act) {
                            $instructions[] = [
                                'text'       => (string)($act['instruction'] ?? ''),
                                'distance_m' => (int)($act['length'] ?? 0),
                                'duration_s' => (int)($act['duration'] ?? 0),
                            ];
                        }
                    }
                }

                $routesOut[] = [
                    'distance_km'      => round($totalDist / 1000, 1),
                    'duration_min'     => (int)round($totalDur / 60),
                    'tolls_total'      => $tollsTotal !== null ? round($tollsTotal, 2) : null,
                    'tolls_currency'   => $tollsCurrency,
                    'tolls_by_country' => $tollsByCountry,
                    'tolls_breakdown'  => $tollsBreakdown,       // L1
                    'vignettes'        => array_values($vignettes), // L2
                    'toll_locations'   => $tollLocations,         // L3
                    'polylines'        => $polylines,
                    'instructions'     => $instructions,
                    'sections'         => $sections,
                ];
            }

            return ['routes' => $routesOut];
        } catch (\Throwable $e) {
            Log::error('HERE routing error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Szukaj truck-friendly POI (parkingi/stacje) wzdłuż trasy.
     *
     * HERE `route=` parametr powodował HTTP 414 (URL za długi). Używamy sample points
     * + Browse z `in=circle:lat,lng;r=R` (Browse respektuje circle, Discover czasem nie).
     *
     * @param array<int, string> $polylines      Flexible polylines z routeMulti
     * @param int                $sampleEveryKm  Co ile km próbkujemy
     * @param int                $maxSamples     Max liczba sample (limit API calls)
     * @param int                $radiusM        Promień przeszukiwania per sample
     * @return array<int, array{id:string,title:string,address:string,lat:float,lng:float,category:string,category_id:string,distance:?int,type:string}>
     */
    public function truckStopsAlongRoute(array $polylines, int $sampleEveryKm = 50, int $maxSamples = 10, int $radiusM = 25000): array
    {
        Log::debug('truckStopsAlongRoute: input polylines=' . count($polylines)
            . ', sampleEveryKm=' . $sampleEveryKm . ', maxSamples=' . $maxSamples . ', radiusM=' . $radiusM);
        if (empty($polylines)) return [];

        // Sample punktów wzdłuż trasy (haversine)
        $points = [];
        foreach ($polylines as $poly) {
            $decoded = self::decodePolyline($poly);
            if (empty($decoded)) continue;
            $points[] = $decoded[0];
            $lastSampleKm = 0.0;
            $cumKm = 0.0;
            for ($i = 1; $i < count($decoded); $i++) {
                $cumKm += $this->haversineKm($decoded[$i - 1], $decoded[$i]);
                if ($cumKm - $lastSampleKm >= $sampleEveryKm) {
                    $points[] = $decoded[$i];
                    $lastSampleKm = $cumKm;
                    if (count($points) >= $maxSamples) break 2;
                }
            }
            if (count($points) < $maxSamples) {
                $points[] = end($decoded);
            }
        }
        Log::debug('Sample points: ' . count($points));

        $stops = [];
        $seen = [];
        $browseUrl = 'https://browse.search.hereapi.com/v1/browse';
        $categoryGroups = [
            ['ids' => '700-7900-0107,550-5510-0202',          'type' => 'truck_stop'],
            ['ids' => '700-7600-0000,700-7600-0116,700-7600','type' => 'fuel_station'],
            ['ids' => '700-7900,700-7900-0000',               'type' => 'parking'],
        ];

        foreach ($points as $ptIdx => $pt) {
            foreach ($categoryGroups as $cg) {
                try {
                    $resp = $this->client->get($browseUrl, [
                        'at'         => $pt['lat'] . ',' . $pt['lng'],
                        'in'         => 'circle:' . $pt['lat'] . ',' . $pt['lng'] . ';r=' . $radiusM,
                        'categories' => $cg['ids'],
                        'limit'      => 15,
                        'apiKey'     => $this->apiKey,
                        'lang'       => 'pl-PL',
                    ]);
                    if (!$resp->isOk()) {
                        Log::warning('HERE Browse HTTP ' . $resp->getStatusCode() . ' (cat=' . $cg['type'] . ', sample#' . $ptIdx . '): ' . substr($resp->getStringBody(), 0, 200));
                        continue;
                    }
                    $data = $resp->getJson();
                    $items = $data['items'] ?? [];
                    if (!empty($items)) {
                        Log::debug('  Browse cat=' . $cg['type'] . ' sample#' . $ptIdx
                            . ' (' . round($pt['lat'], 2) . ',' . round($pt['lng'], 2) . '): '
                            . count($items) . ' items, first=' . ($items[0]['title'] ?? '?')
                            . ' @ ' . round($items[0]['position']['lat'] ?? 0, 2) . ',' . round($items[0]['position']['lng'] ?? 0, 2));
                    }
                    foreach ($items as $item) {
                        $id = (string)($item['id'] ?? '');
                        if ($id === '' || isset($seen[$id])) continue;
                        $seen[$id] = true;
                        $stops[] = [
                            'id'          => $id,
                            'title'       => (string)($item['title'] ?? ''),
                            'address'     => (string)($item['address']['label'] ?? ''),
                            'lat'         => (float)($item['position']['lat'] ?? 0),
                            'lng'         => (float)($item['position']['lng'] ?? 0),
                            'category'    => (string)($item['categories'][0]['name'] ?? ''),
                            'category_id' => (string)($item['categories'][0]['id'] ?? ''),
                            'distance'    => $item['distance'] ?? null,
                            'type'        => $cg['type'],
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('HERE Browse error: ' . $e->getMessage());
                }
            }
        }
        Log::debug('Truck POI search: ' . count($points) . ' samples × 3 cat groups → ' . count($stops) . ' unique results');
        return $stops;
    }

    /**
     * Haversine — dystans w km między dwoma punktami.
     */
    private function haversineKm(array $a, array $b): float
    {
        $earth = 6371.0;
        $lat1 = deg2rad($a['lat']);
        $lat2 = deg2rad($b['lat']);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($b['lng'] - $a['lng']);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        return 2 * $earth * asin(min(1.0, sqrt($h)));
    }

    /**
     * Dekoduje flexible polyline HERE → tablica punktów [{lat,lng}, ...].
     * Algorytm: https://github.com/heremaps/flexible-polyline/blob/master/README.md
     *
     * @return array<int, array{lat: float, lng: float}>
     */
    public static function decodePolyline(string $encoded): array
    {
        if ($encoded === '') return [];
        // Decoder zaimplementowany lokalnie (bez zewnętrznych zależności)
        $decoded = self::decodeFlexiblePolyline($encoded);
        return array_map(fn($p) => ['lat' => $p[0], 'lng' => $p[1]], $decoded);
    }

    /**
     * Pełna implementacja Flexible Polyline decoder.
     * Port z oficjalnej referencji HERE (JS/Python).
     */
    private static function decodeFlexiblePolyline(string $encoded): array
    {
        // Oficjalna tabela HERE flexible-polyline (port z heremaps/flexible-polyline JS).
        // Każdy ASCII znak (od 45 '-') → indeks w alfabecie 'A-Za-z0-9-_'.
        $DECODING_TABLE = [
            62,-1,-1,52,53,54,55,56,57,58,59,60,61,-1,-1,-1,  // ASCII 45..60 (- . / 0..9 : ; < )
            -1,-1,-1,-1, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9,10,11,  // ASCII 61..76 (= > ? @ A..L)
            12,13,14,15,16,17,18,19,20,21,22,23,24,25,-1,-1,  // ASCII 77..92 (M..Z [ \)
            -1,-1,-1,63,-1,26,27,28,29,30,31,32,33,34,35,36,  // ASCII 93..108 (] ^ _ ` a..l)
            37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,     // ASCII 109..123 (m..z {)
        ];
        $FORMAT_VERSION = 1;
        $idx = 0;
        $len = strlen($encoded);

        $decodeChar = function ($c) use ($DECODING_TABLE) {
            $code = ord($c) - 45;
            return ($code < 0 || $code >= count($DECODING_TABLE)) ? -1 : $DECODING_TABLE[$code];
        };
        $decodeUnsignedVarint = function () use (&$idx, $len, $encoded, $decodeChar) {
            $shift = 0; $result = 0;
            while ($idx < $len) {
                $c = $decodeChar($encoded[$idx++]);
                if ($c < 0) return false;
                $result |= ($c & 0x1F) << $shift;
                if (($c & 0x20) === 0) return $result;
                $shift += 5;
            }
            return false;
        };
        $toSigned = function ($val) {
            if ($val & 1) $val = ~$val;
            return $val >> 1;
        };

        $version = $decodeUnsignedVarint();
        if ($version !== $FORMAT_VERSION) return [];
        $header = $decodeUnsignedVarint();
        $precision = $header & 15;
        $thirdDimPrecision = ($header >> 7) & 15;
        $thirdDim = ($header >> 4) & 7;
        $factor = pow(10, $precision);
        $thirdFactor = pow(10, $thirdDimPrecision);

        $lat = 0; $lng = 0; $z = 0;
        $points = [];
        while ($idx < $len) {
            $dLat = $decodeUnsignedVarint(); if ($dLat === false) break;
            $dLng = $decodeUnsignedVarint(); if ($dLng === false) break;
            $lat += $toSigned($dLat);
            $lng += $toSigned($dLng);
            if ($thirdDim) {
                $dZ = $decodeUnsignedVarint();
                if ($dZ === false) break;
                $z += $toSigned($dZ);
                $points[] = [$lat / $factor, $lng / $factor, $z / $thirdFactor];
            } else {
                $points[] = [$lat / $factor, $lng / $factor];
            }
        }
        return $points;
    }
}

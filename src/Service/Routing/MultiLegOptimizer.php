<?php
declare(strict_types=1);

namespace App\Service\Routing;

use Cake\Log\Log;
use RuntimeException;

/**
 * Optymalizator multi-leg (Pickup and Delivery Problem):
 *   - User wprowadza N ładunków, każdy = pickup + dropoff
 *   - Algorytm znajduje OPTYMALNĄ kolejność punktów (PDP-constrained)
 *     z respektowaniem zasady: pickup MUSI być przed dropoff dla każdego ładunku
 *
 * Strategia:
 *   1. Generacja wszystkich PDP-valid permutacji punktów
 *   2. Scoring każdej via Haversine (bardzo szybkie, bez API calls)
 *   3. Zwracanie top-N orderingów z metrykami
 *
 * Złożoność: N ładunków = (2N)! / 2^N permutacji.
 *   N=2 → 6, N=3 → 90, N=4 → 2520, N=5 → 113400 — limit MAX_LOADS = 4.
 */
class MultiLegOptimizer
{
    public const MAX_LOADS = 4;

    /**
     * @param array<int, array{pickup: array{lat:float,lng:float,label?:string}, dropoff: array{lat:float,lng:float,label?:string}, weight_kg?:float, name?:string}>  $loads
     * @param array{lat:float,lng:float,label?:string}|null $start  Punkt startowy (np. baza pojazdu); null = pierwszy pickup
     * @param array{lat:float,lng:float,label?:string}|null $end    Punkt powrotu (np. baza pojazdu); null = ostatni dropoff
     * @param int $topN  Ile najlepszych orderingów zwrócić
     * @return array{best: array, alternatives: array, baseline_km: float}
     */
    public function optimize(array $loads, ?array $start = null, ?array $end = null, int $topN = 3): array
    {
        $n = count($loads);
        if ($n < 1) throw new RuntimeException('Brak ładunków do optymalizacji.');
        if ($n > self::MAX_LOADS) throw new RuntimeException('Max ' . self::MAX_LOADS . ' ładunków na raz.');

        // Generuj wszystkie PDP-valid permutacje
        // Indeksy: 0..n-1 = pickups, n..2n-1 = dropoffs (pickup_i ↔ dropoff_{i+n})
        $totalPoints = 2 * $n;
        $allOrderings = $this->generatePdpValidPermutations($n);

        // Baseline = naive order (P1 D1 P2 D2 P3 D3 ...) — bez optymalizacji
        $baselineOrder = [];
        for ($i = 0; $i < $n; $i++) { $baselineOrder[] = $i; $baselineOrder[] = $i + $n; }
        $baselineKm = $this->scoreOrdering($baselineOrder, $loads, $start, $end);

        // Score every ordering
        $scored = [];
        foreach ($allOrderings as $ord) {
            $km = $this->scoreOrdering($ord, $loads, $start, $end);
            $scored[] = ['order' => $ord, 'distance_km' => $km];
        }
        usort($scored, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        // Format top-N + baseline metrics
        $top = array_slice($scored, 0, $topN);
        $formatted = array_map(function ($s) use ($loads, $start, $end, $baselineKm, $n) {
            return $this->formatOrdering($s['order'], $loads, $start, $end, $s['distance_km'], $baselineKm);
        }, $top);

        return [
            'best'         => $formatted[0] ?? null,
            'alternatives' => array_slice($formatted, 1),
            'baseline_km'  => round($baselineKm, 1),
            'best_savings_km'  => $formatted[0] ? round($baselineKm - $formatted[0]['distance_km'], 1) : 0,
            'best_savings_pct' => $formatted[0] && $baselineKm > 0
                ? round(($baselineKm - $formatted[0]['distance_km']) / $baselineKm * 100, 1) : 0,
            'loads_count'  => $n,
            'orderings_evaluated' => count($allOrderings),
        ];
    }

    /**
     * Generuj wszystkie PDP-valid permutacje indeksów dla N ładunków.
     * Indexing: pickup_i = i, dropoff_i = i + n
     * Reguła: indeks i musi pojawić się przed (i + n).
     */
    private function generatePdpValidPermutations(int $n): array
    {
        $total = 2 * $n;
        $items = range(0, $total - 1);
        $result = [];
        $this->permute($items, 0, $result);
        // Filtr PDP-validity
        return array_values(array_filter($result, function ($perm) use ($n) {
            $positions = [];
            foreach ($perm as $pos => $idx) $positions[$idx] = $pos;
            for ($i = 0; $i < $n; $i++) {
                if (!isset($positions[$i]) || !isset($positions[$i + $n])) return false;
                if ($positions[$i] >= $positions[$i + $n]) return false;
            }
            return true;
        }));
    }

    private function permute(array $items, int $start, array &$result): void
    {
        $n = count($items);
        if ($start === $n - 1) { $result[] = $items; return; }
        for ($i = $start; $i < $n; $i++) {
            [$items[$start], $items[$i]] = [$items[$i], $items[$start]];
            $this->permute($items, $start + 1, $result);
            [$items[$start], $items[$i]] = [$items[$i], $items[$start]];
        }
    }

    /**
     * Oblicz całkowity dystans (km) dla danej kolejności.
     */
    private function scoreOrdering(array $order, array $loads, ?array $start, ?array $end): float
    {
        $points = [];
        if ($start) $points[] = $start;
        $n = count($loads);
        foreach ($order as $idx) {
            $points[] = ($idx < $n) ? $loads[$idx]['pickup'] : $loads[$idx - $n]['dropoff'];
        }
        if ($end) $points[] = $end;
        $total = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $total += $this->haversineKm($points[$i - 1], $points[$i]);
        }
        return $total;
    }

    /**
     * Format pojedynczego orderingu z metadanymi.
     */
    private function formatOrdering(array $order, array $loads, ?array $start, ?array $end, float $distanceKm, float $baselineKm): array
    {
        $n = count($loads);
        $waypoints = [];
        if ($start) $waypoints[] = ['type' => 'start', 'address' => $start['label'] ?? '', 'lat' => $start['lat'], 'lng' => $start['lng']];
        foreach ($order as $idx) {
            $loadIdx = $idx < $n ? $idx : ($idx - $n);
            $isPickup = $idx < $n;
            $pt = $isPickup ? $loads[$loadIdx]['pickup'] : $loads[$loadIdx]['dropoff'];
            $waypoints[] = [
                'type'      => $isPickup ? 'pickup' : 'dropoff',
                'load_idx'  => $loadIdx,
                'load_name' => $loads[$loadIdx]['name'] ?? ('Load ' . ($loadIdx + 1)),
                'address'   => $pt['label'] ?? '',
                'lat'       => $pt['lat'],
                'lng'       => $pt['lng'],
            ];
        }
        if ($end) $waypoints[] = ['type' => 'end', 'address' => $end['label'] ?? '', 'lat' => $end['lat'], 'lng' => $end['lng']];

        return [
            'order'       => $order,
            'waypoints'   => $waypoints,
            'distance_km' => round($distanceKm, 1),
            'savings_km'  => round($baselineKm - $distanceKm, 1),
            'savings_pct' => $baselineKm > 0 ? round(($baselineKm - $distanceKm) / $baselineKm * 100, 1) : 0,
        ];
    }

    private function haversineKm(array $a, array $b): float
    {
        $earth = 6371.0;
        $lat1 = deg2rad((float)$a['lat']);
        $lat2 = deg2rad((float)$b['lat']);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float)$b['lng'] - (float)$a['lng']);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        return 2 * $earth * asin(min(1.0, sqrt($h)));
    }
}

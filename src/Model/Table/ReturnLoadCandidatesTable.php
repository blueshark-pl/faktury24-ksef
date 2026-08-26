<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ReturnLoadCandidatesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('return_load_candidates');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('RoutePlans', ['foreignKey' => 'route_plan_id']);
        $this->belongsTo('SpeedOrders', ['foreignKey' => 'speed_order_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create');
        $validator
            ->uuid('company_id')->notEmptyString('company_id');
        $validator
            ->uuid('route_plan_id')->notEmptyString('route_plan_id');
        $validator
            ->scalar('candidate_type')->inList('candidate_type', ['internal', 'market', 'manual']);
        $validator
            ->scalar('status')->inList('status', ['suggested', 'dismissed', 'combined']);

        return $validator;
    }

    /**
     * Wyliczenie deadhead km na krzywej ziemi (haversine).
     */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371.0; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($R * $c, 2);
    }

    /**
     * Wylicz match_score dla kandydata (0-100).
     * Wieksza punktacja gdy:
     *  - blisko konca trasy (deadhead km male)
     *  - dobre okno czasowe (gap godzin male)
     *  - ma cene (nie null)
     */
    public static function calcMatchScore(?float $deadheadKm, ?float $timeGapHours, ?float $price): float
    {
        $score = 0.0;
        // Deadhead: 0 km = 50 pkt, 100 km = 25 pkt, 200 km = 0 pkt
        if ($deadheadKm !== null) {
            $score += max(0, 50 - $deadheadKm * 0.25);
        }
        // Time gap: 0h = 30 pkt, 24h = 15 pkt, 72h = 0 pkt
        if ($timeGapHours !== null) {
            $abs = abs($timeGapHours);
            $score += max(0, 30 - $abs * 0.416);
        }
        // Ma cene = +20 pkt
        if ($price !== null && $price > 0) {
            $score += 20;
        }
        return round(min(100.0, $score), 2);
    }

    /**
     * FALA extras: Backhaul matching dla speed_order (nie plan).
     * Dla zlecenia A → B szuka kandydatow na powrot z regionu B (unload)
     * do dowolnego kraju/miasta, w oknie czasowym +6h .. +5 dni po delivery.
     *
     * @param string $companyId
     * @param string $companyNipDigits NIP wlasnej firmy (do speed_orders.company_nip)
     * @param object $sourceOrder speed_order (musi miec unload_country/city + date_delivery)
     * @param float $maxDeadheadKm max odleglosc od unload (bez GPS = fallback nazwa miasta LIKE)
     * @param int $maxCandidates limit wynikow (default 5)
     * @return array {order, deadhead_km, gap_hours, score, reason}
     */
    public function findBackhaulForOrder(string $companyId, string $companyNipDigits, $sourceOrder, float $maxDeadheadKm = 200, int $maxCandidates = 5): array
    {
        $endCountry = strtoupper((string)($sourceOrder->unload_country ?? ''));
        $endCity = mb_strtolower(trim((string)($sourceOrder->place_to_name ?? $sourceOrder->unload_city ?? '')));
        $endTime = $sourceOrder->date_delivery;
        if (!$endCountry && !$endCity) return [];

        $endTimeImm = $endTime instanceof \DateTimeInterface
            ? new \DateTimeImmutable($endTime->format('Y-m-d H:i:s'))
            : new \DateTimeImmutable('+1 day');
        $windowStart = $endTimeImm->modify('+6 hours'); // kierowca musi wyladowac + odpoczac
        $windowEnd   = $endTimeImm->modify('+5 days');

        $SO = \Cake\ORM\TableRegistry::getTableLocator()->get('SpeedOrders');
        $companyNipList = [$companyNipDigits, 'PL' . $companyNipDigits];

        // Szukaj OTWARTYCH zlecen (nie zafakturowane) z zaladunkiem w oknie + z pobliskiej lokalizacji
        $whereGroup = ['SpeedOrders.company_nip IN' => $companyNipList,
            'SpeedOrders.invoice_id IS' => null,
            'SpeedOrders.id !=' => (int)$sourceOrder->id,
            'SpeedOrders.date_load >=' => $windowStart->format('Y-m-d H:i:s'),
            'SpeedOrders.date_load <=' => $windowEnd->format('Y-m-d H:i:s'),
        ];

        // Match: ten sam kraj zaladunku co unload zrodla LUB nazwa miasta LIKE
        $orGroup = [];
        if ($endCountry) {
            $orGroup[] = ['SpeedOrders.load_country' => $endCountry];
        }
        if ($endCity && strlen($endCity) >= 3) {
            $orGroup[] = ['LOWER(SpeedOrders.place_from_name) LIKE' => '%' . $endCity . '%'];
        }
        if (empty($orGroup)) return [];

        $orders = $SO->find()
            ->where($whereGroup)->where(['OR' => $orGroup])
            ->select(['id', 'symbol', 'title1', 'place_from_name', 'place_to_name',
                'load_country', 'unload_country', 'date_load', 'date_delivery',
                'currency', 'netto', 'buyer_nip'])
            ->orderByAsc('date_load')
            ->limit(50)
            ->all()->toArray();

        $out = [];
        foreach ($orders as $o) {
            $matchesCity = $endCity && str_contains(mb_strtolower((string)$o->place_from_name), $endCity);
            $sameCountry = $endCountry && strtoupper((string)$o->load_country) === $endCountry;
            $deadhead = $matchesCity ? 0.0 : ($sameCountry ? 100.0 : 300.0);
            if ($deadhead > $maxDeadheadKm && !$matchesCity) continue;

            $loadTime = $o->date_load instanceof \DateTimeInterface
                ? new \DateTimeImmutable($o->date_load->format('Y-m-d H:i:s'))
                : new \DateTimeImmutable((string)$o->date_load);
            $gapH = abs(($loadTime->getTimestamp() - $endTimeImm->getTimestamp()) / 3600);

            $score = self::calcMatchScore($deadhead, $gapH, $o->netto ? (float)$o->netto : null);

            $reasons = [];
            if ($matchesCity) $reasons[] = 'to samo miasto zaladunku';
            elseif ($sameCountry) $reasons[] = "kraj {$endCountry}";
            $reasons[] = sprintf('deadhead ~%d km', (int)$deadhead);
            $reasons[] = sprintf('gap %.0fh', $gapH);
            if ($o->netto > 0) $reasons[] = sprintf('%s %s', number_format((float)$o->netto, 0, ',', ' '), $o->currency);

            $out[] = [
                'order' => $o,
                'deadhead_km' => $deadhead,
                'gap_hours' => round($gapH, 1),
                'score' => $score,
                'reason' => implode(' · ', $reasons),
            ];
        }

        // Sortuj po score DESC
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, $maxCandidates);
    }
}

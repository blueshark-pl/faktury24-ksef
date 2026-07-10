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
}

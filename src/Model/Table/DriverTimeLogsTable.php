<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class DriverTimeLogsTable extends Table
{
    // UE 561/2006 limity (w minutach)
    public const MAX_DRIVING_DAILY = 540;      // 9h
    public const MAX_DRIVING_DAILY_EXT = 600;  // 10h — max 2x w tyg
    public const MAX_DRIVING_WEEKLY = 3360;    // 56h
    public const MAX_DRIVING_BIWEEKLY = 5400;  // 90h
    public const MIN_DAILY_REST = 660;         // 11h
    public const MIN_DAILY_REST_REDUCED = 540; // 9h — max 3x w tyg
    public const MIN_WEEKLY_REST = 2700;       // 45h

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('driver_time_logs');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Drivers', ['foreignKey' => 'driver_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create');
        $validator
            ->uuid('company_id')->notEmptyString('company_id');
        $validator
            ->uuid('driver_id')->notEmptyString('driver_id');
        $validator
            ->date('log_date')->notEmptyDate('log_date');
        $validator
            ->integer('driving_min')->range('driving_min', [0, 1440]);
        $validator
            ->scalar('source')
            ->inList('source', ['tachograph', 'manual', 'estimated', 'import_ddd', 'import_csv']);

        return $validator;
    }

    /**
     * Wylicz suma jazdy w tygodniu ISO.
     */
    public function sumDrivingInWeek(string $driverId, string $weekIso): int
    {
        $row = $this->find()
            ->select(['total' => 'SUM(driving_min)'])
            ->where(['driver_id' => $driverId, 'week_iso' => $weekIso])
            ->disableHydration()
            ->first();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Sprawdz czy kierowca ma budzet czasu jazdy na dodatkowe X minut w danym tygodniu.
     */
    public function hasBudgetInWeek(string $driverId, string $weekIso, int $additionalMinutes): bool
    {
        $used = $this->sumDrivingInWeek($driverId, $weekIso);
        return ($used + $additionalMinutes) <= self::MAX_DRIVING_WEEKLY;
    }

    /**
     * Status tygodniowy kierowcy — do panelu w planerze.
     * Zwraca: {used_min, remaining_min, weekly_limit, biweekly_used, extended_used_count, reduced_rest_count}
     */
    public function weeklyStatus(string $driverId, string $weekIso): array
    {
        $used = $this->sumDrivingInWeek($driverId, $weekIso);
        $remaining = max(0, self::MAX_DRIVING_WEEKLY - $used);

        // Poprzedni tydzien dla biweekly
        try {
            // 2026-W29 → 2026-W28
            $prev = preg_replace_callback('/^(\d{4})-W(\d{2})$/', function ($m) {
                $y = (int)$m[1]; $w = (int)$m[2] - 1;
                if ($w < 1) { $y--; $w = 52; }
                return sprintf('%04d-W%02d', $y, $w);
            }, $weekIso);
            $prevUsed = $this->sumDrivingInWeek($driverId, $prev);
        } catch (\Throwable) {
            $prevUsed = 0;
        }
        $biweeklyUsed = $used + $prevUsed;

        // Ile razy w tygodniu extended/reduced
        $rows = $this->find()
            ->select(['extended_driving_used', 'reduced_daily_rest_used'])
            ->where(['driver_id' => $driverId, 'week_iso' => $weekIso])
            ->all()
            ->toArray();
        $extCount = count(array_filter($rows, fn ($r) => !empty($r->extended_driving_used)));
        $redCount = count(array_filter($rows, fn ($r) => !empty($r->reduced_daily_rest_used)));

        return [
            'week_iso'            => $weekIso,
            'used_min'            => $used,
            'remaining_min'       => $remaining,
            'weekly_limit'        => self::MAX_DRIVING_WEEKLY,
            'biweekly_used'       => $biweeklyUsed,
            'biweekly_limit'      => self::MAX_DRIVING_BIWEEKLY,
            'extended_used_count' => $extCount,
            'extended_max_count'  => 2,
            'reduced_rest_count'  => $redCount,
            'reduced_max_count'   => 3,
            'is_at_risk'          => $used >= self::MAX_DRIVING_WEEKLY * 0.9,
            'is_over_limit'       => $used >= self::MAX_DRIVING_WEEKLY,
        ];
    }

    public function beforeSave(\Cake\Event\EventInterface $event, $entity, \ArrayObject $options)
    {
        // Auto-fill week_iso z log_date
        if (!empty($entity->log_date)) {
            $date = $entity->log_date instanceof \DateTimeInterface
                ? $entity->log_date
                : new \DateTime((string)$entity->log_date);
            $entity->week_iso = $date->format('o-\WW');
        }
        return true;
    }
}

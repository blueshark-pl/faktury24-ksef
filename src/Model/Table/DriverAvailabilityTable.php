<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class DriverAvailabilityTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('driver_availability');
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
            ->integer('day_of_week')->range('day_of_week', [1, 7]);

        return $validator;
    }

    /**
     * Sprawdz czy kierowca w danym dniu tygodnia jest dostepny (ma zdefiniowany
     * shift_start != null).
     * Zwraca true rowniez gdy brak wzorca (domyslnie dostepny — trzeba wpisac zeby zablokowac).
     */
    public function isAvailableOnDayOfWeek(string $driverId, int $dow): bool
    {
        $row = $this->find()
            ->where(['driver_id' => $driverId, 'day_of_week' => $dow])
            ->first();
        if (!$row) return true; // brak wzorca = domyslnie dostepny
        return !empty($row->shift_start);
    }

    /**
     * Get wszystkich wzorcow jednego kierowcy (7 dni).
     */
    public function forDriver(string $driverId): array
    {
        $rows = $this->find()
            ->where(['driver_id' => $driverId])
            ->orderByAsc('day_of_week')
            ->all()
            ->toArray();
        return $rows;
    }
}

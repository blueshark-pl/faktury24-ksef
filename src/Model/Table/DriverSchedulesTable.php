<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class DriverSchedulesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('driver_schedules');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Drivers', ['foreignKey' => 'driver_id']);
        $this->belongsTo('SpeedOrders', ['foreignKey' => 'speed_order_id', 'joinType' => 'LEFT']);
        $this->belongsTo('RoutePlans', ['foreignKey' => 'route_plan_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Vehicles', ['foreignKey' => 'vehicle_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Trailers', ['foreignKey' => 'trailer_id', 'joinType' => 'LEFT']);
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
            ->dateTime('starts_at')->notEmptyDateTime('starts_at');

        $validator
            ->dateTime('ends_at')->notEmptyDateTime('ends_at');

        $validator
            ->scalar('entry_type')
            ->inList('entry_type', ['assignment', 'time_off', 'sickness', 'training', 'blocked']);

        return $validator;
    }

    /**
     * Znajdz kierowcow WOLNYCH w oknie czasowym (bez konfliktu z grafiku).
     * Ignoruje time_off jesli allowOverride=true (czasem trzeba nadpisac).
     */
    public function findAvailableInWindow(string $companyId, \DateTimeInterface $start, \DateTimeInterface $end, bool $allowOverrideTimeOff = false): \Cake\ORM\Query\SelectQuery
    {
        $Drivers = $this->getAssociation('Drivers')->getTarget();

        $conflictTypes = $allowOverrideTimeOff
            ? ['assignment', 'blocked', 'sickness']
            : ['assignment', 'blocked', 'sickness', 'time_off', 'training'];

        $busyDriverIds = $this->find()
            ->select(['driver_id'])
            ->where([
                'company_id' => $companyId,
                'entry_type IN' => $conflictTypes,
                'starts_at <'  => $end,
                'ends_at >'    => $start,
            ]);

        return $Drivers->find()
            ->where([
                'Drivers.company_id' => $companyId,
                'Drivers.is_active' => true,
                'Drivers.id NOT IN' => $busyDriverIds,
            ])
            ->orderByAsc('Drivers.full_name');
    }
}

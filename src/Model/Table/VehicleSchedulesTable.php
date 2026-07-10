<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class VehicleSchedulesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('vehicle_schedules');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Vehicles', ['foreignKey' => 'vehicle_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Trailers', ['foreignKey' => 'trailer_id', 'joinType' => 'LEFT']);
        $this->belongsTo('SpeedOrders', ['foreignKey' => 'speed_order_id', 'joinType' => 'LEFT']);
        $this->belongsTo('RoutePlans', ['foreignKey' => 'route_plan_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create');

        $validator
            ->uuid('company_id')->notEmptyString('company_id');

        $validator
            ->dateTime('starts_at')->notEmptyDateTime('starts_at');

        $validator
            ->dateTime('ends_at')->notEmptyDateTime('ends_at');

        $validator
            ->scalar('entry_type')
            ->inList('entry_type', ['assignment', 'maintenance', 'inspection', 'unavailable']);

        // XOR: dokladnie jedno z (vehicle_id, trailer_id)
        $validator->add('vehicle_id', 'xor', [
            'rule' => function ($value, $context) {
                $v = !empty($value);
                $t = !empty($context['data']['trailer_id'] ?? null);
                return ($v && !$t) || (!$v && $t) ? true : 'Podaj DOKLADNIE jedno: pojazd LUB naczepe';
            },
        ]);

        return $validator;
    }

    /**
     * Pojazdy WOLNE w oknie czasowym.
     */
    public function findAvailableVehiclesInWindow(string $companyId, \DateTimeInterface $start, \DateTimeInterface $end): \Cake\ORM\Query\SelectQuery
    {
        $Vehicles = $this->getAssociation('Vehicles')->getTarget();

        $busyIds = $this->find()
            ->select(['vehicle_id'])
            ->where([
                'company_id'  => $companyId,
                'vehicle_id IS NOT' => null,
                'starts_at <' => $end,
                'ends_at >'   => $start,
            ]);

        return $Vehicles->find()
            ->where([
                'Vehicles.company_id' => $companyId,
                'Vehicles.is_active' => true,
                'Vehicles.id NOT IN' => $busyIds,
            ])
            ->orderByAsc('Vehicles.name');
    }

    /**
     * Naczepy WOLNE w oknie czasowym.
     */
    public function findAvailableTrailersInWindow(string $companyId, \DateTimeInterface $start, \DateTimeInterface $end): \Cake\ORM\Query\SelectQuery
    {
        $Trailers = $this->getAssociation('Trailers')->getTarget();

        $busyIds = $this->find()
            ->select(['trailer_id'])
            ->where([
                'company_id'  => $companyId,
                'trailer_id IS NOT' => null,
                'starts_at <' => $end,
                'ends_at >'   => $start,
            ]);

        return $Trailers->find()
            ->where([
                'Trailers.company_id' => $companyId,
                'Trailers.is_active' => true,
                'Trailers.id NOT IN' => $busyIds,
            ])
            ->orderByAsc('Trailers.name');
    }
}

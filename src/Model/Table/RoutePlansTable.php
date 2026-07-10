<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class RoutePlansTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('route_plans');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->hasMany('RoutePlanLegs', [
            'foreignKey' => 'route_plan_id',
            'sort'       => ['RoutePlanLegs.leg_index' => 'ASC'],
        ]);
        $this->belongsTo('Contractors', ['foreignKey' => 'contractor_id', 'joinType' => 'LEFT']);
        $this->belongsTo('SpeedOrders', ['foreignKey' => 'speed_order_id', 'joinType' => 'LEFT']);
        $this->belongsTo('VehicleCombinations', ['foreignKey' => 'vehicle_combination_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Vehicles', ['foreignKey' => 'vehicle_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Trailers', ['foreignKey' => 'trailer_id', 'joinType' => 'LEFT']);
        $this->belongsTo('Drivers', ['foreignKey' => 'driver_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->uuid('company_id')
            ->notEmptyString('company_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 200)
            ->notEmptyString('name');

        $validator
            ->scalar('status')
            ->inList('status', ['draft', 'offered', 'accepted', 'rejected', 'converted', 'archived']);

        $validator
            ->scalar('currency')
            ->maxLength('currency', 3)
            ->notEmptyString('currency');

        return $validator;
    }
}

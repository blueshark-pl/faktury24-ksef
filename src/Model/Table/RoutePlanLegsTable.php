<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class RoutePlanLegsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('route_plan_legs');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('RoutePlans', ['foreignKey' => 'route_plan_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->uuid('route_plan_id')
            ->notEmptyString('route_plan_id');

        $validator
            ->integer('leg_index')
            ->notEmptyString('leg_index');

        $validator
            ->scalar('leg_type')
            ->inList('leg_type', ['pickup', 'loaded', 'positioning', 'return_load', 'home']);

        return $validator;
    }
}

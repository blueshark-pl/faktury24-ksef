<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class SpeedOrderStopsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('speed_order_stops');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('SpeedOrders', ['foreignKey' => 'speed_order_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->integer('speed_order_id')->notEmptyString('speed_order_id')
            ->integer('stop_index')
            ->scalar('stop_type')
            ->inList('stop_type', ['pickup', 'delivery', 'transit']);

        return $validator;
    }
}

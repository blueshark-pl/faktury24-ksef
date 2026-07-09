<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class VehicleCombinationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('vehicle_combinations');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Vehicles', [
            'foreignKey' => 'vehicle_id',
            'joinType'   => 'LEFT',
        ]);
        $this->belongsTo('Trailers', [
            'foreignKey' => 'trailer_id',
            'joinType'   => 'LEFT',
        ]);
        $this->belongsTo('Drivers', [
            'foreignKey' => 'driver_id',
            'joinType'   => 'LEFT',
        ]);
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
            ->maxLength('name', 150)
            ->notEmptyString('name');

        $validator
            ->uuid('vehicle_id')
            ->allowEmptyString('vehicle_id');

        $validator
            ->uuid('trailer_id')
            ->allowEmptyString('trailer_id');

        $validator
            ->uuid('driver_id')
            ->allowEmptyString('driver_id');

        return $validator;
    }
}

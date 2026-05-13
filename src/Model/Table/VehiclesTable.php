<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class VehiclesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('vehicles');
        $this->setPrimaryKey('id');
        $this->setDisplayField('name');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')->maxLength('name', 120)
            ->notEmptyString('name', 'Nazwa pojazdu jest wymagana')
            ->allowEmptyString('plate')
            ->allowEmptyString('vin')
            ->inList('type', ['truck', 'tractor', 'van', 'trailer'], 'Nieprawidłowy typ pojazdu')
            ->integer('gross_weight_kg')->allowEmptyString('gross_weight_kg')
            ->integer('axle_count')->allowEmptyString('axle_count')
            ->integer('axle_load_kg')->allowEmptyString('axle_load_kg')
            ->integer('height_cm')->allowEmptyString('height_cm')
            ->integer('width_cm')->allowEmptyString('width_cm')
            ->integer('length_cm')->allowEmptyString('length_cm')
            ->allowEmptyString('emission_class')
            ->allowEmptyString('tunnel_category')
            ->boolean('hazardous_goods')
            ->boolean('is_active')
            ->boolean('is_default');
        return $validator;
    }
}

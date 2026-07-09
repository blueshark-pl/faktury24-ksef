<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class VehicleTypeCategoriesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('vehicle_type_categories');
        $this->setDisplayField('category_label');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
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
            ->scalar('vehicle_type_code')
            ->maxLength('vehicle_type_code', 20)
            ->inList('vehicle_type_code', ['standard', 'mega', 'fridge', 'tandem', 'solo', 'bus', 'oversize']);

        $validator
            ->scalar('country_code')
            ->maxLength('country_code', 2)
            ->minLength('country_code', 2);

        $validator
            ->scalar('system_name')
            ->maxLength('system_name', 60)
            ->notEmptyString('system_name');

        $validator
            ->scalar('category_label')
            ->maxLength('category_label', 100)
            ->notEmptyString('category_label');

        return $validator;
    }
}

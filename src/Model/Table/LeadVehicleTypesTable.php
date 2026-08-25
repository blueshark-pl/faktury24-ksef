<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class LeadVehicleTypesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('lead_vehicle_types');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsToMany('Leads', [
            'joinTable' => 'leads_lead_vehicle_types',
            'foreignKey' => 'vehicle_type_id',
            'targetForeignKey' => 'lead_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->uuid('company_id')->notEmptyString('company_id')
            ->scalar('name')->notEmptyString('name')->maxLength('name', 60)
            ->integer('sort_order');
        return $validator;
    }
}

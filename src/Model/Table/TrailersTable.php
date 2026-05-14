<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class TrailersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('trailers');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->scalar('company_id')->notEmptyString('company_id')
            ->scalar('name')->maxLength('name', 120)->notEmptyString('name')
            ->allowEmptyString('plate')
            ->allowEmptyString('vin')
            ->scalar('type')->inList('type', ['curtain','box','fridge','tanker','flatbed','drawbar','mega','silo','container','tipper']);
        return $validator;
    }
}

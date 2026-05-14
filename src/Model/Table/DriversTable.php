<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class DriversTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('drivers');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->scalar('company_id')->notEmptyString('company_id')
            ->scalar('full_name')->maxLength('full_name', 120)->notEmptyString('full_name')
            ->email('email', false)->allowEmptyString('email')
            ->allowEmptyString('phone');
        return $validator;
    }
}

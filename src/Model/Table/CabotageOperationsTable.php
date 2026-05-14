<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class CabotageOperationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('cabotage_operations');
        $this->setPrimaryKey('id');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->scalar('company_id')->notEmptyString('company_id')
            ->scalar('country')->maxLength('country', 3)->notEmptyString('country')
            ->date('operation_date')->notEmptyDate('operation_date');
        return $validator;
    }
}

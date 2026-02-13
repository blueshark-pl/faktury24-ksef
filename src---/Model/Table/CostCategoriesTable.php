<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class CostCategoriesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('cost_categories');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator->requirePresence('company_id', 'create')->notEmptyString('company_id');
        $validator->requirePresence('name', 'create')->notEmptyString('name');
        $validator->allowEmptyString('code');

        return $validator;
    }
}

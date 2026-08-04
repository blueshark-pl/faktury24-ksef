<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ContractorCreditLimitsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('contractor_credit_limits');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Contractors', ['foreignKey' => 'contractor_id', 'joinType' => 'LEFT']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')->allowEmptyString('id', null, 'create')
            ->uuid('company_id')->notEmptyString('company_id')
            ->scalar('contractor_nip')->notEmptyString('contractor_nip')
            ->maxLength('contractor_nip', 30)
            ->decimal('credit_limit_pln')->notEmptyString('credit_limit_pln')
            ->integer('warning_threshold_pct')
            ->range('warning_threshold_pct', [10, 100]);

        return $validator;
    }
}

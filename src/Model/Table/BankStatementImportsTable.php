<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class BankStatementImportsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('bank_statement_imports');
        $this->setDisplayField('filename');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('BankTransactions', [
            'foreignKey' => 'import_id',
            'dependent'  => true,
        ]);

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
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

        return $validator;
    }
}

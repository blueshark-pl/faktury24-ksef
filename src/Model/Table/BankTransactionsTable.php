<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class BankTransactionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('bank_transactions');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('BankStatementImports', [
            'foreignKey' => 'import_id',
        ]);

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
        ]);

        $this->belongsTo('Invoices', [
            'foreignKey' => 'invoice_id',
        ]);

        $this->hasMany('BankTransactionAllocations', [
            'foreignKey' => 'bank_transaction_id',
            'dependent'  => true,
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
            ->uuid('import_id')
            ->notEmptyString('import_id');

        $validator
            ->date('value_date')
            ->notEmptyString('value_date');

        $validator
            ->inList('direction', ['C', 'D'])
            ->notEmptyString('direction');

        $validator
            ->decimal('amount')
            ->notEmptyString('amount');

        $validator
            ->scalar('import_hash')
            ->maxLength('import_hash', 64)
            ->notEmptyString('import_hash');

        return $validator;
    }

    /**
     * Sprawdza, czy transakcja o danym hashu już istnieje (deduplication).
     */
    public function existsByHash(string $hash): bool
    {
        return $this->exists(['import_hash' => $hash]);
    }
}

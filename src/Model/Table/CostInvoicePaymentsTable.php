<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class CostInvoicePaymentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('cost_invoice_payments');
        $this->setDisplayField('amount');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('CostInvoices', [
            'foreignKey' => 'cost_invoice_id',
        ]);
        $this->belongsTo('BankTransactions', [
            'foreignKey' => 'bank_transaction_id',
        ]);
        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('cost_invoice_id')
            ->notEmptyString('cost_invoice_id');

        $validator
            ->date('payment_date')
            ->notEmptyDate('payment_date');

        $validator
            ->decimal('amount')
            ->greaterThanOrEqual('amount', 0);

        return $validator;
    }
}

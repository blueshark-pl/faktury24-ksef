<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class BankTransactionAllocationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('bank_transaction_allocations');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('BankTransactions', [
            'foreignKey' => 'bank_transaction_id',
        ]);
        $this->belongsTo('Invoices', [
            'foreignKey' => 'invoice_id',
        ]);
        $this->belongsTo('LegacyInvoices', [
            'foreignKey' => 'legacy_invoice_id',
        ]);
        $this->belongsTo('InvoicePayments', [
            'foreignKey' => 'invoice_payment_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('company_id')
            ->notEmptyString('bank_transaction_id')
            ->requirePresence('allocated_amount', 'create')
            ->decimal('allocated_amount')
            ->inList('allocation_type', ['gross', 'net', 'vat'])
            ->allowEmptyString('note');

        return $validator;
    }
}

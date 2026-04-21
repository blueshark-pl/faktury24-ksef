<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class LegacyInvoicePaymentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('legacy_invoice_payments');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('LegacyInvoices', [
            'foreignKey' => 'legacy_invoice_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('legacy_invoice_id')
            ->notEmptyString('company_id')
            ->decimal('amount')
            ->greaterThan('amount', 0);

        return $validator;
    }
}

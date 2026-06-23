<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class CostInvoicesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('cost_invoices');
        $this->setEntityClass('App\Model\Entity\CostInvoice');

        $this->addBehavior('Timestamp');

        $this->belongsToMany('SpeedOrders', [
            'through'          => 'CostInvoiceOrders',
            'foreignKey'       => 'cost_invoice_id',
            'targetForeignKey' => 'speed_order_id',
            'joinTable'        => 'cost_invoice_orders',
        ]);

        $this->hasMany('CostInvoicePayments', [
            'foreignKey' => 'cost_invoice_id',
            'dependent'  => true,
            'cascadeCallbacks' => true,
        ]);

        $this->hasMany('CostInvoiceLines', [
            'foreignKey' => 'cost_invoice_id',
            'dependent'  => true,
            'cascadeCallbacks' => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notEmptyString('source')
            ->allowEmptyString('invoice_number')
            ->allowEmptyString('contractor_name')
            ->allowEmptyString('contractor_nip')
            ->allowEmptyString('accounting_month')
            ->decimal('netto')
            ->decimal('vat')
            ->decimal('brutto');

        return $validator;
    }
}

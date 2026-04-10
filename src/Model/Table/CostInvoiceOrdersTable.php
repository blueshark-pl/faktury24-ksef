<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class CostInvoiceOrdersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('cost_invoice_orders');
        $this->setEntityClass('App\Model\Entity\CostInvoiceOrder');

        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);

        $this->belongsTo('CostInvoices', ['foreignKey' => 'cost_invoice_id']);
        $this->belongsTo('SpeedOrders',  ['foreignKey' => 'speed_order_id']);
    }
}

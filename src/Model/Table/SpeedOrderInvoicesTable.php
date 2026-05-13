<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Pivot M:N: speed_orders ⇄ invoices.
 *
 * Każde zlecenie może mieć wiele faktur sprzedażowych (zaliczkowe, końcowe,
 * korekty, dodatkowe pozycje). Wszystkie równoprawne — nie ma pola 'role'.
 *
 * Backfill z speed_orders.invoice_id wykonany w migracji
 * 20260513180000_CreateSpeedOrderInvoices.
 */
class SpeedOrderInvoicesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('speed_order_invoices');
        $this->setEntityClass('App\Model\Entity\SpeedOrderInvoice');

        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);

        $this->belongsTo('SpeedOrders', ['foreignKey' => 'speed_order_id']);
        $this->belongsTo('Invoices',    ['foreignKey' => 'invoice_id']);
    }
}

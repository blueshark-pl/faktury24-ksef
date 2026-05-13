<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class SpeedOrdersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('speed_orders');
        $this->setEntityClass('App\Model\Entity\SpeedOrder');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        // Legacy 1:1 — pozostawione na czas migracji (kompatybilność wsteczna).
        // Faza C usunie pole speed_orders.invoice_id i tę asocjację.
        $this->belongsTo('Invoices', [
            'foreignKey' => 'invoice_id',
            'joinType'   => 'LEFT',
        ]);

        // M:N — wszystkie faktury powiązane ze zleceniem przez pivot
        // speed_order_invoices. Użycie: $order->invoices (array Invoice entities).
        $this->belongsToMany('AllInvoices', [
            'className'        => 'Invoices',
            'joinTable'        => 'speed_order_invoices',
            'foreignKey'       => 'speed_order_id',
            'targetForeignKey' => 'invoice_id',
            'through'          => 'SpeedOrderInvoices',
            'propertyName'     => 'invoices',
            'saveStrategy'     => 'append',
            'dependent'        => false,
        ]);

        $this->hasMany('SpeedOrderStatusLogs', [
            'foreignKey' => 'speed_order_id',
            'order'      => ['SpeedOrderStatusLogs.created' => 'ASC'],
            'dependent'  => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('speed_id')
            ->requirePresence('speed_id', 'create')
            ->notEmptyString('speed_id');

        return $validator;
    }
}

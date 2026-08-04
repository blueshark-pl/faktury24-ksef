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

        // [DEPRECATED] Legacy 1:1 przez speed_orders.invoice_id.
        // Alias na PIERWSZĄ fakturę zlecenia, trzymany dla wstecznej kompatybilności
        // (zewnętrzne raporty, eksporty CSV, legacy SQL queries z 'invoice_id IS NULL').
        // Nowe kody powinny używać $order->invoices (M:N pivot) — patrz asocjacja
        // 'AllInvoices' poniżej.
        $this->belongsTo('Invoices', [
            'foreignKey' => 'invoice_id',
            'joinType'   => 'LEFT',
        ]);

        // M:N — wszystkie faktury powiązane ze zleceniem przez pivot
        // speed_order_invoices. ŹRÓDŁO PRAWDY o powiązaniach.
        // Użycie: $order->invoices (array Invoice entities).
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

        // Stopy posrednie (multi-stop A->B->C->D)
        $this->hasMany('SpeedOrderStops', [
            'foreignKey' => 'speed_order_id',
            'order'      => ['SpeedOrderStops.stop_index' => 'ASC'],
            'dependent'  => true,
            'cascadeCallbacks' => true,
            'saveStrategy' => 'replace',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        // speed_id wymagany tylko dla source='speed'; dla manual jest NULL.
        $validator
            ->integer('speed_id')
            ->allowEmptyString('speed_id')
            ->add('speed_id', 'requiredForSpeed', [
                'rule' => function ($value, $context) {
                    $source = $context['data']['source'] ?? 'speed';
                    if ($source === 'speed' && ($value === null || $value === '')) {
                        return 'speed_id jest wymagany dla source=speed';
                    }
                    return true;
                },
            ]);

        $validator
            ->scalar('source')
            ->requirePresence('source', 'create')
            ->inList('source', ['speed', 'manual'], 'source musi byc "speed" lub "manual"');

        // Dla manual walidacja podstawowych p�l biznesowych.
        $validator
            ->scalar('symbol')
            ->allowEmptyString('symbol')
            ->add('symbol', 'requiredForManual', [
                'rule' => function ($value, $context) {
                    $source = $context['data']['source'] ?? 'speed';
                    if ($source === 'manual' && ($value === null || $value === '')) {
                        return 'symbol jest wymagany dla source=manual';
                    }
                    return true;
                },
            ]);

        return $validator;
    }
}

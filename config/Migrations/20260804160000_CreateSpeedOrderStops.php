<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Punkty posrednie trasy (multi-stop) dla zlecen A->B->C->D.
 *
 * Podstawowe zaladunek i rozladunek dalej trzymamy w kolumnach
 * speed_orders.load_* i unload_* (stop_index 0 = load, ostatni = unload).
 * Tutaj zapisujemy DODATKOWE stopy posrednie (drop-off / pickup w trasie),
 * ktorych nie da sie wcisnac w klasyczny model A->B.
 *
 * stop_type:
 *  - 'pickup'     - dodatkowy zaladunek (LTL, dobior towaru w trasie)
 *  - 'delivery'   - dodatkowy rozladunek
 *  - 'transit'    - postoj techniczny (parking/tankowanie/CMR)
 */
class CreateSpeedOrderStops extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('speed_order_stops')) return;
        $this->table('speed_order_stops', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('speed_order_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('stop_index', 'integer', ['null' => false, 'default' => 0,
                'comment' => 'Kolejnosc w trasie 1..N (0 = primary load, ostatni = primary unload sa juz w speed_orders)'])
            ->addColumn('stop_type', 'string', ['limit' => 20, 'null' => false, 'default' => 'delivery',
                'comment' => 'pickup | delivery | transit'])
            ->addColumn('country_code', 'string', ['limit' => 5, 'null' => true])
            ->addColumn('postal_code', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('address', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('place_name', 'string', ['limit' => 200, 'null' => true,
                'comment' => 'Nazwa magazynu / hurtowni / sklepu'])
            ->addColumn('planned_at', 'datetime', ['null' => true])
            ->addColumn('actual_at', 'datetime', ['null' => true])
            ->addColumn('contact_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('contact_phone', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('cargo_notes', 'text', ['null' => true,
                'comment' => 'Ile palet dobrac/dostawic, waga, uwagi kierowcy'])
            ->addColumn('completed_at', 'datetime', ['null' => true,
                'comment' => 'Data ukonczenia stopu (przez kierowce)'])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['speed_order_id', 'stop_index'], ['name' => 'BY_ORDER_INDEX'])
            ->addIndex(['stop_type'], ['name' => 'BY_STOP_TYPE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('speed_order_stops')->drop()->save();
    }
}

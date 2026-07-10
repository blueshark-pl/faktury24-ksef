<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Legi trasy — kazdy odcinek osobno z rola (pickup/loaded/positioning/return_load/home).
 * Pozwala odroznic co jest fakturowane (loaded) od kosztu wewnetrznego (pickup, positioning).
 * Rola decyduje o wliczeniu do przychodu, obliczeniu marzy i kabotazu.
 */
class CreateRoutePlanLegs extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('route_plan_legs', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('route_plan_id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('leg_index', 'integer', ['null' => false,
            'comment' => 'Kolejnosc leg-a w trasie (0-based)',
        ]);
        $table->addColumn('leg_type', 'string', ['limit' => 20, 'null' => false,
            'comment' => 'pickup|loaded|positioning|return_load|home — decyduje o wliczeniu do przychodu',
        ]);

        $table->addColumn('from_json', 'text', ['null' => true,
            'comment' => 'Punkt start: {address, lat, lng, country}',
        ]);
        $table->addColumn('to_json', 'text', ['null' => true,
            'comment' => 'Punkt cel: {address, lat, lng, country}',
        ]);

        $table->addColumn('distance_km', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true]);
        $table->addColumn('duration_min', 'integer', ['null' => true]);

        $table->addColumn('is_billed', 'boolean', ['null' => false, 'default' => true,
            'comment' => 'true = leg wliczony do przychodu; false = koszt wewnetrzny',
        ]);
        $table->addColumn('country_code', 'char', ['limit' => 2, 'null' => true,
            'comment' => 'Glowny kraj tego leg-a — dla kabotazu i podliczen per kraj',
        ]);

        $table->addColumn('toll_cost', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true]);
        $table->addColumn('fuel_cost', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true]);
        $table->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'PLN']);

        $table->addColumn('planned_start_at', 'datetime', ['null' => true]);
        $table->addColumn('planned_end_at', 'datetime', ['null' => true]);
        $table->addColumn('notes', 'text', ['null' => true]);

        $table->addTimestamps('created', 'modified');

        $table->addIndex(['route_plan_id'], ['name' => 'BY_ROUTE_PLAN']);
        $table->addIndex(['route_plan_id', 'leg_index'], ['unique' => true, 'name' => 'UQ_PLAN_INDEX']);
        $table->addIndex(['leg_type'], ['name' => 'BY_LEG_TYPE']);
        $table->addIndex(['country_code'], ['name' => 'BY_COUNTRY']);

        $table->create();
    }
}

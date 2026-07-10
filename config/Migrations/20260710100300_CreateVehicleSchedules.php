<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Grafik pojazdu/naczepy — kiedy zajete. Osobno bo pojazd moze byc zajety
 * bez kierowcy (serwis, przeglad) a kierowca moze byc zajety bez pojazdu
 * (szkolenie). Klucz: dokladnie JEDNO z (vehicle_id, trailer_id) wypelnione.
 */
class CreateVehicleSchedules extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('vehicle_schedules', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);

        // Wypelnione DOKLADNIE jedno — pojazd lub naczepa
        $table->addColumn('vehicle_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('trailer_id', 'char', ['limit' => 36, 'null' => true]);

        $table->addColumn('starts_at', 'datetime', ['null' => false]);
        $table->addColumn('ends_at', 'datetime', ['null' => false]);

        $table->addColumn('entry_type', 'string', ['limit' => 20, 'null' => false,
            'comment' => 'assignment|maintenance|inspection|unavailable — decyduje o dostepnosci',
        ]);

        $table->addColumn('speed_order_id', 'integer', ['null' => true]);
        $table->addColumn('route_plan_id', 'char', ['limit' => 36, 'null' => true]);

        $table->addColumn('created_by_user_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('notes', 'text', ['null' => true]);

        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);
        $table->addIndex(['vehicle_id', 'starts_at', 'ends_at'], ['name' => 'BY_VEHICLE_WINDOW']);
        $table->addIndex(['trailer_id', 'starts_at', 'ends_at'], ['name' => 'BY_TRAILER_WINDOW']);
        $table->addIndex(['speed_order_id'], ['name' => 'BY_SPEED_ORDER']);
        $table->addIndex(['route_plan_id'], ['name' => 'BY_ROUTE_PLAN']);
        $table->addIndex(['entry_type'], ['name' => 'BY_TYPE']);

        $table->create();
    }
}

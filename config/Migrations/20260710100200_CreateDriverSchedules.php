<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Grafik kierowcy — bloki dostepnosci/niedostepnosci w czasie.
 * Kluczowe zapytanie planera: "kto jest wolny w oknie X-Y".
 * Precyzja: minuta (datetime), nie dzien — pozwala na planowanie slotow zaladunku.
 */
class CreateDriverSchedules extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('driver_schedules', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('driver_id', 'char', ['limit' => 36, 'null' => false]);

        $table->addColumn('starts_at', 'datetime', ['null' => false]);
        $table->addColumn('ends_at', 'datetime', ['null' => false]);

        $table->addColumn('entry_type', 'string', ['limit' => 20, 'null' => false,
            'comment' => 'assignment|time_off|sickness|training|blocked — decyduje o dostepnosci',
        ]);

        // Powiazania (nullable — zalezy od typu)
        $table->addColumn('speed_order_id', 'integer', ['null' => true,
            'comment' => 'FK do speed_orders.id gdy entry_type=assignment i zlecenie juz istnieje',
        ]);
        $table->addColumn('route_plan_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'FK do route_plans.id gdy jeszcze etap planowania',
        ]);
        $table->addColumn('vehicle_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'Ktora ciezarowka kierowca prowadzi (dla blokady zestawu)',
        ]);
        $table->addColumn('trailer_id', 'char', ['limit' => 36, 'null' => true]);

        $table->addColumn('created_by_user_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'Kto zaplanowal',
        ]);
        $table->addColumn('notes', 'text', ['null' => true]);

        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);
        $table->addIndex(['driver_id', 'starts_at', 'ends_at'], ['name' => 'BY_DRIVER_WINDOW']);
        $table->addIndex(['company_id', 'starts_at', 'ends_at'], ['name' => 'BY_COMPANY_WINDOW']);
        $table->addIndex(['speed_order_id'], ['name' => 'BY_SPEED_ORDER']);
        $table->addIndex(['route_plan_id'], ['name' => 'BY_ROUTE_PLAN']);
        $table->addIndex(['entry_type'], ['name' => 'BY_TYPE']);

        $table->create();
    }
}

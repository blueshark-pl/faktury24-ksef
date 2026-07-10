<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Planowany plan trasy — rozszerzenie starego 'route_searches' o pełen kontekst
 * operacyjny: klient, ceny, statusy, wersjonowanie, snapshot kalkulacji P&L,
 * powiazanie z docelowym zleceniem transportowym.
 *
 * Cykl zycia: draft → offered → accepted → converted → archived.
 *
 * Rozne od route_searches:
 *  - route_searches to historia zapytan usera (do quick-redo)
 *  - route_plans to nazwany, wersjonowany PLAN z ktorego robimy oferty/zlecenia
 */
class CreateRoutePlans extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('route_plans', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('author_user_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'FK do users.id — kto planowal',
        ]);
        $table->addColumn('contractor_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'FK do contractors.id — dla ktorego klienta',
        ]);
        $table->addColumn('name', 'string', ['limit' => 200, 'null' => false,
            'comment' => 'Robocza nazwa planu, np. "HB RTS Warszawa→Berlin lipiec"',
        ]);
        $table->addColumn('status', 'string', ['limit' => 20, 'null' => false,
            'default' => 'draft',
            'comment' => 'draft|offered|accepted|rejected|converted|archived',
        ]);

        // Snapshot geografii
        $table->addColumn('waypoints_json', 'text', ['null' => true,
            'comment' => 'Pełna lista punktów trasy (start + stopy + koniec) w JSON',
        ]);
        $table->addColumn('pickup_json', 'text', ['null' => true,
            'comment' => 'Podjazd (leg pusty) — {address, lat, lng, distance_km, duration_min}',
        ]);
        $table->addColumn('return_load_json', 'text', ['null' => true,
            'comment' => 'Sugerowany ladunek powrotny (opcjonalne)',
        ]);

        // Snapshot z HERE Routing
        $table->addColumn('distance_km', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true]);
        $table->addColumn('duration_min', 'integer', ['null' => true]);
        $table->addColumn('co2_kg', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true,
            'comment' => 'Emisja CO2 z HERE — dla ETS/raportow',
        ]);

        // Kalkulacja P&L
        $table->addColumn('calc_cost_json', 'text', ['null' => true,
            'comment' => 'Pelny snapshot P&L: paliwo, myto, kierowca, amortyzacja, rezerwa serwisowa',
        ]);
        $table->addColumn('suggested_price', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true,
            'comment' => 'Cena zaproponowana z historii stawek',
        ]);
        $table->addColumn('accepted_price', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true,
            'comment' => 'Cena zaakceptowana przez klienta',
        ]);
        $table->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'PLN']);

        // Powiazanie z docelowym zleceniem
        $table->addColumn('speed_order_id', 'integer', ['null' => true,
            'comment' => 'FK do speed_orders.id gdy plan zamienil sie w zlecenie',
        ]);

        // Wersjonowanie
        $table->addColumn('parent_plan_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'FK do wczesniejszej wersji tego samego planu',
        ]);
        $table->addColumn('valid_until', 'date', ['null' => true,
            'comment' => 'Do kiedy oferta jest wazna',
        ]);

        // Przypisany zestaw (denormalizacja z vehicle_combinations dla szybkiego lookup)
        $table->addColumn('vehicle_combination_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'FK do vehicle_combinations.id — jesli operator wybral gotowy zestaw',
        ]);
        $table->addColumn('vehicle_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('trailer_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('driver_id', 'char', ['limit' => 36, 'null' => true]);

        // Planowane okno realizacji (potrzebne dla grafiku i return-load)
        $table->addColumn('planned_start_at', 'datetime', ['null' => true]);
        $table->addColumn('planned_end_at', 'datetime', ['null' => true]);

        $table->addColumn('notes', 'text', ['null' => true]);

        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);
        $table->addIndex(['company_id', 'status'], ['name' => 'BY_COMPANY_STATUS']);
        $table->addIndex(['company_id', 'contractor_id'], ['name' => 'BY_COMPANY_CONTRACTOR']);
        $table->addIndex(['author_user_id'], ['name' => 'BY_AUTHOR']);
        $table->addIndex(['speed_order_id'], ['name' => 'BY_SPEED_ORDER']);
        $table->addIndex(['parent_plan_id'], ['name' => 'BY_PARENT']);
        $table->addIndex(['driver_id'], ['name' => 'BY_DRIVER']);
        $table->addIndex(['vehicle_id'], ['name' => 'BY_VEHICLE']);
        $table->addIndex(['planned_start_at', 'planned_end_at'], ['name' => 'BY_PLANNED_WINDOW']);

        $table->create();
    }
}

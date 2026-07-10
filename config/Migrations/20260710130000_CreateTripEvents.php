<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Timeline zdarzen w trakcie realizacji zlecenia transportowego.
 * Zrodla:
 *  - dyspozytor z UI (rezygnacja, opoznienie)
 *  - kierowca z telefonu przez publiczny link z tokenem (POD upload, arrival)
 *  - cron/webhook GPS (departure, position update — future)
 *
 * Kluczowe eventy z ktorych materializuje sie stan zlecenia:
 *  - loading_completed → speed_orders.actual_load_at
 *  - unloading_completed → speed_orders.actual_delivery_at
 *  - pod_uploaded → speed_orders + speed_order_attachments
 */
class CreateTripEvents extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('trip_events', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);

        // Kluczowe powiazania — event zawsze ma zlecenie
        $table->addColumn('speed_order_id', 'integer', ['null' => false,
            'comment' => 'FK do speed_orders — zlecenie ktore ten event opisuje',
        ]);
        $table->addColumn('route_plan_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'FK do route_plans (jesli zlecenie pochodzi z planera)',
        ]);
        $table->addColumn('driver_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'Kierowca ktory zglosil event',
        ]);

        $table->addColumn('event_type', 'string', ['limit' => 30, 'null' => false,
            'comment' => 'departure|arrival|loading_started|loading_completed|unloading_started|unloading_completed|border_crossed|delay_reported|pod_uploaded|cmr_signed|incident|note',
        ]);
        $table->addColumn('happened_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
            'comment' => 'Kiedy sie wydarzylo (nie kiedy zapisano)',
        ]);

        // Kontekst geograficzny
        $table->addColumn('location_lat', 'decimal', ['precision' => 10, 'scale' => 7, 'null' => true]);
        $table->addColumn('location_lng', 'decimal', ['precision' => 10, 'scale' => 7, 'null' => true]);
        $table->addColumn('location_address', 'string', ['limit' => 500, 'null' => true]);
        $table->addColumn('location_country', 'char', ['limit' => 2, 'null' => true]);

        // Opoznienie (jesli event to delay_reported)
        $table->addColumn('delay_minutes', 'integer', ['null' => true,
            'comment' => 'Ile min opoznienia (nulla dla eventow ktore nie sa opoznieniami)',
        ]);
        $table->addColumn('delay_reason', 'string', ['limit' => 200, 'null' => true,
            'comment' => 'krotki opis: korki|kontrola|awaria|kolejka_na_granicy|inne',
        ]);

        // Zawartosc
        $table->addColumn('notes', 'text', ['null' => true]);
        $table->addColumn('photo_path', 'string', ['limit' => 500, 'null' => true,
            'comment' => 'Zdjecie z telefonu kierowcy (POD scan, uszkodzenie)',
        ]);
        $table->addColumn('attachment_path', 'string', ['limit' => 500, 'null' => true]);

        // Zrodlo
        $table->addColumn('source', 'string', ['limit' => 20, 'null' => false, 'default' => 'operator',
            'comment' => 'operator|driver_mobile|gps_track|api_webhook|system',
        ]);
        $table->addColumn('reported_by_user_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'null gdy kierowca via token',
        ]);
        $table->addColumn('reported_by_name', 'string', ['limit' => 200, 'null' => true,
            'comment' => 'Ludzka nazwa (dla driver_mobile gdy brak user_id)',
        ]);

        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);
        $table->addIndex(['speed_order_id', 'happened_at'], ['name' => 'BY_ORDER_TIME']);
        $table->addIndex(['route_plan_id'], ['name' => 'BY_PLAN']);
        $table->addIndex(['driver_id'], ['name' => 'BY_DRIVER']);
        $table->addIndex(['event_type'], ['name' => 'BY_TYPE']);
        $table->addIndex(['company_id', 'happened_at'], ['name' => 'BY_COMPANY_TIME']);

        $table->create();
    }
}

<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Event bus dla operacji — centralny log wszystkich zdarzen na planach, ofertach,
 * zleceniach, przypisaniach, briefach. Zbiera dane do analytics, audytu (kto co zmienil),
 * timeline'ow, alerty. Zapisujemy nawet gdy dashboardy jeszcze nie istnieja — dane
 * beda gotowe.
 *
 * Design: append-only log (nie modyfikujemy nigdy). Update robimy przez nowy wpis
 * z 'event_name=updated' i payload_json.
 */
class CreateOperationalEvents extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('operational_events', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);

        $table->addColumn('entity_type', 'string', ['limit' => 40, 'null' => false,
            'comment' => 'route_plan|route_offer|speed_order|driver_schedule|vehicle_schedule|driver_brief|trip_event|invoice',
        ]);
        $table->addColumn('entity_id', 'string', ['limit' => 40, 'null' => false,
            'comment' => 'ID w tabeli — string aby wspierac int (speed_orders) i char36 (uuid)',
        ]);

        $table->addColumn('event_name', 'string', ['limit' => 40, 'null' => false,
            'comment' => 'created|updated|status_changed|sent|viewed|accepted|rejected|deleted|assigned itd.',
        ]);

        $table->addColumn('user_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'Kto wywolal event (null gdy system/cron)',
        ]);
        $table->addColumn('impersonated_by_user_id', 'char', ['limit' => 36, 'null' => true,
            'comment' => 'Jesli admin wcielil sie w usera, to jego id',
        ]);

        $table->addColumn('payload_json', 'text', ['null' => true,
            'comment' => 'Metadane: before/after state, ID powiazanych encji, dodatkowe kontekst',
        ]);
        $table->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true]);
        $table->addColumn('user_agent', 'string', ['limit' => 255, 'null' => true]);

        // Log-only — bez modified
        $table->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['company_id', 'created'], ['name' => 'BY_COMPANY_TIME']);
        $table->addIndex(['entity_type', 'entity_id'], ['name' => 'BY_ENTITY']);
        $table->addIndex(['company_id', 'entity_type', 'created'], ['name' => 'BY_COMPANY_TYPE_TIME']);
        $table->addIndex(['user_id', 'created'], ['name' => 'BY_USER_TIME']);
        $table->addIndex(['event_name'], ['name' => 'BY_EVENT']);

        $table->create();
    }
}

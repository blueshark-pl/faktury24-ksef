<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Ostrzezenia compliance wygenerowane przy planowaniu (kabotaz, ADR, sankcje,
 * przekroczenia czasu pracy, brakujace dokumenty). Log-tylko append (nie modyfikujemy).
 *
 * Rozniczka od operational_events:
 *  - operational_events = wszystko co sie dzieje (audit, dashboard)
 *  - compliance_events  = specyficznie RYZYKO PRAWNE do audytu ITD/urzedu skarbowego
 *
 * Kazde ostrzezenie mozna "dismiss" (operator wie o ryzyku i mimo to jedzie) —
 * powod jest zapisany do audytu.
 */
class CreateComplianceEvents extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('compliance_events', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);

        // Do czego sie odnosi (nullable — mozna zapisac bez powiazania)
        $table->addColumn('route_plan_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('route_offer_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('speed_order_id', 'integer', ['null' => true]);
        $table->addColumn('driver_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('vehicle_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('trailer_id', 'char', ['limit' => 36, 'null' => true]);

        $table->addColumn('event_type', 'string', ['limit' => 40, 'null' => false,
            'comment' => 'cabotage_limit|cabotage_hard_limit|adr_missing|driver_hours_exceeded|weekly_rest_missing|daily_rest_missing|oversize_no_permit|sanction_country|expired_inspection|expired_insurance|missing_permit|other',
        ]);
        $table->addColumn('severity', 'string', ['limit' => 10, 'null' => false, 'default' => 'warning',
            'comment' => 'info|warning|error',
        ]);
        $table->addColumn('description', 'text', ['null' => false,
            'comment' => 'Ludzki tekst opisujacy ryzyko',
        ]);
        $table->addColumn('context_json', 'text', ['null' => true,
            'comment' => 'Dodatkowe metadane (kraj, kwoty limity itd.)',
        ]);

        // Rozstrzygniecie ryzyka
        $table->addColumn('is_dismissed', 'boolean', ['null' => false, 'default' => false,
            'comment' => 'Operator zaakceptowal ryzyko i mimo to jedzie',
        ]);
        $table->addColumn('dismissed_by_user_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('dismissed_at', 'datetime', ['null' => true]);
        $table->addColumn('dismissal_reason', 'text', ['null' => true,
            'comment' => 'Uzasadnienie akceptacji ryzyka (do audytu ITD)',
        ]);

        $table->addColumn('detected_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP']);

        $table->addIndex(['company_id', 'detected_at'], ['name' => 'BY_COMPANY_TIME']);
        $table->addIndex(['company_id', 'is_dismissed'], ['name' => 'BY_COMPANY_DISMISSED']);
        $table->addIndex(['company_id', 'severity'], ['name' => 'BY_COMPANY_SEVERITY']);
        $table->addIndex(['route_plan_id'], ['name' => 'BY_PLAN']);
        $table->addIndex(['speed_order_id'], ['name' => 'BY_ORDER']);
        $table->addIndex(['driver_id'], ['name' => 'BY_DRIVER']);
        $table->addIndex(['event_type'], ['name' => 'BY_TYPE']);

        $table->create();
    }
}

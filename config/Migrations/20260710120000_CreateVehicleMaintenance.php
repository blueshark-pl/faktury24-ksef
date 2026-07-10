<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Historia przegladow, badan technicznych, kalibracji tachografu, ADR-ow, ubezpieczen
 * dla pojazdow i naczep. Kluczowe zapytanie: "co wygasa w nastepnych 30 dniach".
 *
 * XOR: dokladnie jedno z (vehicle_id, trailer_id) wypelnione.
 */
class CreateVehicleMaintenance extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('vehicle_maintenance', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table->addColumn('id', 'char', ['limit' => 36, 'null' => false]);
        $table->addColumn('company_id', 'char', ['limit' => 36, 'null' => false]);

        // XOR: pojazd lub naczepa
        $table->addColumn('vehicle_id', 'char', ['limit' => 36, 'null' => true]);
        $table->addColumn('trailer_id', 'char', ['limit' => 36, 'null' => true]);

        $table->addColumn('maintenance_type', 'string', ['limit' => 30, 'null' => false,
            'comment' => 'technical_inspection|service|tacho_calibration|adr_cert|insurance|oc|ac|extinguisher|first_aid|other',
        ]);

        $table->addColumn('performed_at', 'date', ['null' => true,
            'comment' => 'Kiedy wykonano (badanie/serwis)',
        ]);
        $table->addColumn('valid_until', 'date', ['null' => true,
            'comment' => 'Do kiedy wazne — kluczowe dla alertow',
        ]);
        $table->addColumn('reminder_days', 'integer', ['null' => false, 'default' => 30,
            'comment' => 'Ile dni przed valid_until wysylac alert (domyslnie 30)',
        ]);

        $table->addColumn('cost', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => true]);
        $table->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'PLN']);
        $table->addColumn('supplier', 'string', ['limit' => 200, 'null' => true,
            'comment' => 'Kto wykonal serwis (SKP, warsztat, ubezpieczyciel)',
        ]);

        $table->addColumn('document_path', 'string', ['limit' => 500, 'null' => true,
            'comment' => 'Sciezka do skanu dokumentu (PDF/JPG)',
        ]);
        $table->addColumn('cost_invoice_id', 'integer', ['null' => true,
            'comment' => 'FK do cost_invoices — faktura kosztowa serwisu',
        ]);

        $table->addColumn('notes', 'text', ['null' => true]);
        $table->addColumn('created_by_user_id', 'char', ['limit' => 36, 'null' => true]);

        // Flagi alertowe
        $table->addColumn('alert_sent_at', 'datetime', ['null' => true,
            'comment' => 'Kiedy wyslano powiadomienie o wygasajacej waznosci (idempotent — nie wysylamy 2x)',
        ]);
        $table->addColumn('is_active', 'boolean', ['null' => false, 'default' => true,
            'comment' => 'Aktywny wpis (false gdy zastapiony nowszym)',
        ]);

        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);
        $table->addIndex(['vehicle_id'], ['name' => 'BY_VEHICLE']);
        $table->addIndex(['trailer_id'], ['name' => 'BY_TRAILER']);
        $table->addIndex(['company_id', 'valid_until'], ['name' => 'BY_COMPANY_VALID']);
        $table->addIndex(['company_id', 'is_active', 'valid_until'], ['name' => 'BY_COMPANY_ACTIVE_VALID']);
        $table->addIndex(['maintenance_type'], ['name' => 'BY_TYPE']);
        $table->addIndex(['cost_invoice_id'], ['name' => 'BY_COST_INVOICE']);

        $table->create();
    }
}

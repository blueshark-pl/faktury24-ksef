<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Kierowcy — osobna encja z parametrami i stawkami.
 *
 * Powiązanie z trasą/zleceniem: w planerze user wybiera kierowcę,
 * jego stawka i dieta wchodzą do kalkulacji kosztu zamiast (lub obok)
 * globalnego pola "Stawka kierowcy" w Kosztach operacyjnych.
 *
 * Future scope: tabela `driver_country_visits` dla śledzenia cabotage
 * per kierowca (zamiast per company/vehicle).
 */
class CreateDrivers extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('drivers')) return;
        $this->table('drivers', ['id' => false, 'primary_key' => ['id'], 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('full_name', 'string', ['limit' => 120, 'null' => false])

            ->addColumn('phone', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 120, 'null' => true])

            ->addColumn('license_categories', 'string', ['limit' => 64, 'null' => true,
                'comment' => 'CSV np. "C+E,C,B" — uprawnienia prawa jazdy'])
            ->addColumn('license_expiry', 'date', ['null' => true,
                'comment' => 'Data ważności prawa jazdy'])

            // Koszty
            ->addColumn('hourly_rate_pln', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true,
                'comment' => 'Stawka godzinowa kierowcy'])
            ->addColumn('per_diem_pln', 'decimal', ['precision' => 8, 'scale' => 2, 'null' => true,
                'comment' => 'Dieta dzienna (przy delegacjach >8h)'])
            ->addColumn('km_rate_pln', 'decimal', ['precision' => 6, 'scale' => 2, 'null' => true,
                'comment' => 'Stawka kilometrowa (alternatywnie do godzinowej)'])

            // Kompetencje
            ->addColumn('adr_certified', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('adr_expiry', 'date', ['null' => true])
            ->addColumn('languages', 'string', ['limit' => 64, 'null' => true,
                'comment' => 'CSV: pl,en,de,ua,ru'])
            ->addColumn('experience_years', 'integer', ['null' => true])

            ->addColumn('birth_date', 'date', ['null' => true])
            ->addColumn('hire_date', 'date', ['null' => true])

            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('is_default', 'boolean', ['null' => false, 'default' => false])

            ->addColumn('created', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['company_id', 'is_active'])
            ->create();
    }
}

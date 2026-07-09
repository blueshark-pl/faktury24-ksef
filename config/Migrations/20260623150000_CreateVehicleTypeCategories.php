<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Mapowanie: typ zestawu → kategoria w konkretnym systemie mytniczym.
 * Pozwala firmie zdefiniować "Standard w PL A2 AWSA = kat. 4" itp. bez
 * zgadywania przez planer.
 *
 * Jeśli user zdefiniował kategorię dla typu i kraju/systemu →
 * planer użyje jej w klasyfikatorze zamiast wyliczać po axle_count.
 */
class CreateVehicleTypeCategories extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('vehicle_type_categories', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'uuid', ['null' => false]);
        $table->addColumn('company_id', 'uuid', ['null' => false]);
        $table->addColumn('vehicle_type_code', 'string', [
            'limit' => 20, 'null' => false,
            'comment' => 'standard|mega|fridge|tandem|solo|bus|oversize (zgodne z vehicles.combination_type)',
        ]);
        $table->addColumn('country_code', 'char', [
            'limit' => 2, 'null' => false,
            'comment' => 'ISO 3166-1 alpha-2 (PL, DE, AT, CZ, IT, FR, CH, NL...)',
        ]);
        $table->addColumn('system_name', 'string', [
            'limit' => 60, 'null' => false,
            'comment' => 'A2 AWSA / DE Maut / MYTO CZ / e-TOLL / ASFA / GO-Box / itp.',
        ]);
        $table->addColumn('category_label', 'string', [
            'limit' => 100, 'null' => false,
            'comment' => 'Etykieta kategorii do wyświetlenia (np. "kat. 4", "Achsklasse 5+")',
        ]);
        $table->addColumn('notes', 'text', ['null' => true, 'default' => null]);
        $table->addColumn('is_active', 'boolean', ['null' => false, 'default' => true]);
        $table->addTimestamps('created', 'modified');

        $table->addIndex(['company_id'], ['name' => 'BY_COMPANY']);
        $table->addIndex(['company_id', 'vehicle_type_code'], ['name' => 'BY_COMPANY_TYPE']);
        $table->addIndex(['company_id', 'country_code'], ['name' => 'BY_COMPANY_COUNTRY']);
        $table->addIndex(['company_id', 'vehicle_type_code', 'country_code', 'system_name'], [
            'unique' => true, 'name' => 'UQ_COMPANY_TYPE_COUNTRY_SYSTEM',
        ]);

        $table->create();
    }
}

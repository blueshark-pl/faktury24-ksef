<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * FALA extras: Rodzaje taboru (multi-select per lead) + deprecate branch_type.
 * Domyslne (seed): Frigo, Tautliner, Gabaryt, Mega, Tandem.
 * Admin moze dopisywac nowe.
 *
 * Zamiast starego pola branch_type (road/road_reefer/road_adr/...) - deprecated.
 */
class CreateLeadVehicleTypes extends AbstractMigration
{
    public function up(): void
    {
        // Katalog rodzajow taboru per firma
        $t = $this->table('lead_vehicle_types', ['id' => false, 'primary_key' => ['id']]);
        $t->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 60, 'null' => false,
                'comment' => 'Nazwa np. Frigo, Tautliner, Mega, Tandem, Gabaryt'])
            ->addColumn('sort_order', 'integer', ['limit' => 5, 'null' => false, 'default' => 100])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['company_id', 'sort_order'], ['name' => 'BY_COMPANY_SORT'])
            ->addIndex(['company_id', 'name'], ['unique' => true, 'name' => 'UNQ_COMPANY_NAME'])
            ->create();

        // Pivot many-to-many
        $p = $this->table('leads_lead_vehicle_types', ['id' => false, 'primary_key' => ['lead_id', 'vehicle_type_id']]);
        $p->addColumn('lead_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('vehicle_type_id', 'char', ['limit' => 36, 'null' => false])
            ->addIndex(['lead_id'], ['name' => 'BY_LEAD'])
            ->addIndex(['vehicle_type_id'], ['name' => 'BY_TYPE'])
            ->create();

        // Deprecate branch_type - dodaj komentarz do kolumny (nie usuwamy zeby zachowac dane)
        $this->execute("ALTER TABLE leads MODIFY COLUMN branch_type VARCHAR(50) NULL DEFAULT NULL COMMENT 'DEPRECATED - uzywaj lead_vehicle_types m2m. Zachowane dla legacy.'");

        // Seed default vehicle types per company - dla kazdej istniejacej firmy
        // Uwaga: to jest jednorazowy seed, nowe firmy tez dostaja domyslnie w Controller::add
        $companies = $this->fetchAll("SELECT DISTINCT company_id FROM leads WHERE company_id IS NOT NULL LIMIT 100");
        $defaults = [
            ['name' => 'Frigo',     'sort_order' => 10],
            ['name' => 'Tautliner', 'sort_order' => 20],
            ['name' => 'Mega',      'sort_order' => 30],
            ['name' => 'Tandem',    'sort_order' => 40],
            ['name' => 'Gabaryt',   'sort_order' => 50],
        ];
        foreach ($companies as $co) {
            $companyId = $co['company_id'];
            foreach ($defaults as $d) {
                $existing = $this->fetchAll(sprintf(
                    "SELECT id FROM lead_vehicle_types WHERE company_id = '%s' AND name = '%s'",
                    $companyId, $d['name']
                ));
                if (empty($existing)) {
                    $this->execute(sprintf(
                        "INSERT INTO lead_vehicle_types (id, company_id, name, sort_order, created, modified) VALUES (UUID(), '%s', '%s', %d, NOW(), NOW())",
                        $companyId, $d['name'], $d['sort_order']
                    ));
                }
            }
        }
    }

    public function down(): void
    {
        $this->table('leads_lead_vehicle_types')->drop()->save();
        $this->table('lead_vehicle_types')->drop()->save();
    }
}

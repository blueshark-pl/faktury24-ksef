<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Tabela pojazdów floty firmy — używana w kalkulatorze trasy (HERE Routing v8)
 * do uwzględnienia profilu truck'a (waga/wymiary/osie/emisja/ADR).
 */
class CreateVehicles extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('vehicles')) {
            return;
        }

        $this->table('vehicles', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])

            // Identyfikacja
            ->addColumn('name', 'string', ['limit' => 120, 'null' => false, 'comment' => 'np. Volvo FH 16'])
            ->addColumn('plate', 'string', ['limit' => 24, 'null' => true, 'comment' => 'rejestracja'])
            ->addColumn('vin', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('type', 'string', ['limit' => 20, 'default' => 'truck', 'comment' => 'truck/tractor/van/trailer'])

            // Wymiary / waga (jednostki: kg, cm)
            ->addColumn('gross_weight_kg', 'integer', ['null' => true, 'comment' => 'DMC'])
            ->addColumn('axle_count', 'integer', ['null' => true])
            ->addColumn('axle_load_kg', 'integer', ['null' => true, 'comment' => 'max nacisk na oś'])
            ->addColumn('height_cm', 'integer', ['null' => true])
            ->addColumn('width_cm', 'integer', ['null' => true])
            ->addColumn('length_cm', 'integer', ['null' => true])

            // Norma emisji + ADR
            ->addColumn('emission_class', 'string', ['limit' => 16, 'null' => true, 'comment' => 'euro_1..euro_6, eev'])
            ->addColumn('tunnel_category', 'string', ['limit' => 4, 'null' => true, 'comment' => 'ADR: A..E'])
            ->addColumn('hazardous_goods', 'boolean', ['default' => false])

            // Meta
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('is_default', 'boolean', ['default' => false, 'comment' => 'domyślny dla zleceń bez wybranego pojazdu'])

            ->addTimestamps('created', 'modified')
            ->addIndex(['company_id'])
            ->addIndex(['plate'])
            ->create();
    }
}

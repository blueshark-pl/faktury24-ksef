<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateProducts extends AbstractMigration
{
    public function change(): void
    {
        // Główna tabela produktów/usług
        $table = $this->table('products', [
            'id'          => false,
            'primary_key' => ['id'],
            'engine'      => 'InnoDB',
            'encoding'    => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
        ]);

        $table
            ->addColumn('id', 'char', [
                'limit'   => 36,
                'null'    => false,
            ])
            ->addColumn('company_id', 'char', [
                'limit' => 36,
                'null'  => false,
                'comment' => 'FK -> companies.id (char(36))',
            ])
            ->addColumn('code', 'string', [
                'limit'   => 64,
                'null'    => true,
                'comment' => 'SKU/kod produktu unikatowy w ramach firmy',
            ])
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null'  => false,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
            ])
            ->addColumn('is_service', 'boolean', [
                'default' => 0,
                'null'    => false,
                'comment' => '1=usługa, 0=produkt',
            ])
            ->addColumn('unit_id', 'integer', [
                'null'    => false,
                'comment' => 'FK -> units.id (int)',
            ])
            ->addColumn('vat_id', 'char', [
                'limit'   => 36,
                'null'    => false,
                'comment' => 'FK -> vats.id (char(36))',
            ])
            ->addColumn('net_price', 'decimal', [
                'precision' => 15,
                'scale'     => 2,
                'null'      => false,
                'default'   => '0.00',
                'comment'   => 'Cena netto w walucie currency',
            ])
            ->addColumn('currency', 'char', [
                'limit'   => 3,
                'null'    => false,
                'default' => 'PLN',
            ])
            ->addColumn('pkwiu', 'string', [
                'limit'   => 32,
                'null'    => true,
            ])
            ->addColumn('gtu_code', 'string', [
                'limit'   => 16,
                'null'    => true,
                'comment' => 'np. GTU_01…GTU_13; jeśli używane',
            ])
            ->addColumn('barcode', 'string', [
                'limit' => 64,
                'null'  => true,
            ])
            ->addColumn('is_active', 'boolean', [
                'default' => 1,
                'null'    => false,
            ])
            ->addColumn('deleted', 'boolean', [
                'default' => 0,
                'null'    => false,
                'comment' => 'soft-delete',
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])

            // Indeksy
            ->addIndex(['company_id'])
            ->addIndex(['unit_id'])
            ->addIndex(['vat_id'])
            ->addIndex(['is_active'])
            ->addIndex(['deleted'])
            ->addIndex(['name'])
            ->addIndex(['barcode'])
            ->addIndex(['company_id', 'code'], ['unique' => true, 'name' => 'ux_products_company_code'])

            ->create();

        // Klucze obce (jeśli masz companies/units/vats; w razie potrzeby usuń)
        $this->table('products')
            ->addForeignKey('unit_id', 'units', 'id', [
                'update' => 'CASCADE',
                'delete' => 'RESTRICT',
                'constraint' => 'fk_products_units',
            ])
            ->addForeignKey('vat_id', 'vats', 'id', [
                'update' => 'CASCADE',
                'delete' => 'RESTRICT',
                'constraint' => 'fk_products_vats',
            ])
            // Odkomentuj, jeśli istnieje tabela companies z id char(36)
            // ->addForeignKey('company_id', 'companies', 'id', [
            //     'update' => 'CASCADE',
            //     'delete' => 'RESTRICT',
            //     'constraint' => 'fk_products_companies',
            // ])
            ->update();
    }
}

<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Katalog typow palet (dla oznaczania cargo items):
 *  - Standardowe (EUR, EPAL, industrial)
 *  - TOSCA (H1, L1, CR3, COMBO 285 seria, itp.) - pooling polimerowy
 *  - IFCO, Chep - inne systemy pooling
 *  - Custom (per company)
 *
 * Rozmiary standard vs custom - mieszane. Wymiary w mm.
 * Zdjecia - opcjonalne (image_path).
 */
class CreatePalletTypes extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('pallet_types')) return;
        $this->table('pallet_types', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'NULL = katalog globalny (widoczny wszystkim); UUID = per firma custom'])
            ->addColumn('code', 'string', ['limit' => 30, 'null' => false,
                'comment' => 'Skrocony kod: EUR, H1, L1, CR3, COMBO-285-BD-5R'])
            ->addColumn('name', 'string', ['limit' => 150, 'null' => false,
                'comment' => 'Pelna nazwa: Standard EUR pallet, TOSCA H1 800x1200'])
            ->addColumn('manufacturer', 'string', ['limit' => 50, 'null' => true,
                'comment' => 'TOSCA / EPAL / CHEP / IFCO / IPP / inne'])
            ->addColumn('length_mm', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('width_mm', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('height_mm', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('weight_empty_kg', 'decimal', ['precision' => 6, 'scale' => 2, 'null' => true,
                'comment' => 'Waga pustej palety'])
            ->addColumn('load_capacity_kg', 'integer', ['null' => true, 'signed' => false,
                'comment' => 'Nosnosc dynamiczna (kg)'])
            ->addColumn('material', 'string', ['limit' => 30, 'null' => true,
                'comment' => 'wood / plastic / metal / composite / cardboard'])
            ->addColumn('color', 'string', ['limit' => 30, 'null' => true,
                'comment' => 'niebieski / czerwony / czarny / natural / etc'])
            ->addColumn('is_pooling', 'boolean', ['null' => false, 'default' => false,
                'comment' => 'Czy paleta poolingowa (wynajem, zwrot)'])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('image_path', 'string', ['limit' => 500, 'null' => true,
                'comment' => 'Sciezka do zdjecia (webroot lub URL)'])
            ->addColumn('external_url', 'string', ['limit' => 500, 'null' => true,
                'comment' => 'Link do produktu producenta / dokumentacji'])
            ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('sort_order', 'integer', ['null' => false, 'default' => 0])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['code'], ['name' => 'BY_CODE'])
            ->addIndex(['company_id', 'is_active'], ['name' => 'BY_COMPANY_ACTIVE'])
            ->addIndex(['manufacturer'], ['name' => 'BY_MANUFACTURER'])
            ->create();
    }

    public function down(): void
    {
        $this->table('pallet_types')->drop()->save();
    }
}

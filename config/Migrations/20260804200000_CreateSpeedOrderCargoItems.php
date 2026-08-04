<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Pozycje ladunku (line items) per zlecenie. Standard w spedycji - konkretne
 * produkty na palecie z ilosciami, wagami, typem opakowania.
 *
 * Wzor: TOSCA Collection Note, DHL/DB Schenker booking, Trans/Timocom.
 *
 * Kolumny checkboxow (Dry/Wrapping/Strapping/Sort Only) - opcjonalne
 * flagi opakowania/wysylki (nie kazdy klient ich uzywa).
 */
class CreateSpeedOrderCargoItems extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('speed_order_cargo_items')) return;
        $this->table('speed_order_cargo_items', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('speed_order_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('line_index', 'integer', ['null' => false, 'default' => 0,
                'comment' => 'Kolejnosc pozycji w cargo manifest (1..N)'])

            // Identyfikacja produktu
            ->addColumn('product_code', 'string', ['limit' => 60, 'null' => true,
                'comment' => 'Kod / SKU / ID produktu (np. "17" albo "COMBO-285-BD-5R")'])
            ->addColumn('product_name', 'string', ['limit' => 255, 'null' => true,
                'comment' => 'Nazwa/opis produktu (np. "COMBO 285 BD 5R")'])

            // Flagi opakowania
            ->addColumn('is_dry', 'boolean', ['null' => false, 'default' => false,
                'comment' => 'Dry - suchy transport'])
            ->addColumn('is_wrapped', 'boolean', ['null' => false, 'default' => false,
                'comment' => 'Wrapping - foliowany'])
            ->addColumn('is_strapped', 'boolean', ['null' => false, 'default' => false,
                'comment' => 'Strapping - taśmowany'])
            ->addColumn('is_sort_only', 'boolean', ['null' => false, 'default' => false,
                'comment' => 'Sort Only - tylko sortowanie'])

            // Ilosci + waga
            ->addColumn('stack_height', 'integer', ['null' => true, 'signed' => false,
                'comment' => 'Ile palet na sobie (stack height)'])
            ->addColumn('qty_advised', 'integer', ['null' => true, 'signed' => false,
                'comment' => 'Deklarowana ilosc (Advised)'])
            ->addColumn('qty_real', 'integer', ['null' => true, 'signed' => false,
                'comment' => 'Rzeczywista ilosc (po zaladunku)'])
            ->addColumn('weight_kg', 'decimal', ['precision' => 10, 'scale' => 3, 'null' => true,
                'comment' => 'Waga pozycji (kg)'])
            ->addColumn('unit', 'string', ['limit' => 20, 'null' => true, 'default' => 'szt',
                'comment' => 'Jednostka: szt / kg / m3 / palety / kartony'])

            ->addColumn('notes', 'string', ['limit' => 255, 'null' => true])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['speed_order_id', 'line_index'], ['name' => 'BY_ORDER_INDEX'])
            ->addIndex(['product_code'], ['name' => 'BY_PRODUCT_CODE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('speed_order_cargo_items')->drop()->save();
    }
}

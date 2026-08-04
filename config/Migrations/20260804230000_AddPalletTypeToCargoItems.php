<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Przypisanie palety (z katalogu pallet_types) do pozycji ladunku.
 *  - pallet_type_id: FK do pallet_types (nullable - opcjonalne)
 *  - pallet_code: string fallback gdy paleta jest custom / spoza katalogu
 */
class AddPalletTypeToCargoItems extends BaseMigration
{
    public function up(): void
    {
        $this->table('speed_order_cargo_items')
            ->addColumn('pallet_type_id', 'char', [
                'limit' => 36, 'null' => true, 'after' => 'product_name',
                'comment' => 'FK do pallet_types (opcjonalne - paleta z katalogu)',
            ])
            ->addColumn('pallet_code', 'string', [
                'limit' => 30, 'null' => true, 'after' => 'pallet_type_id',
                'comment' => 'Free-text kod palety gdy nie ma w katalogu (np. AI wyciagnal "L1" ale bez FK)',
            ])
            ->addIndex(['pallet_type_id'], ['name' => 'BY_PALLET_TYPE'])
            ->update();
    }

    public function down(): void
    {
        $this->table('speed_order_cargo_items')
            ->removeIndexByName('BY_PALLET_TYPE')
            ->removeColumn('pallet_code')
            ->removeColumn('pallet_type_id')
            ->update();
    }
}

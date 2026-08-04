<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Adres (ulica + numer) dla pickup (zaladunek) i delivery (rozladunek).
 * Kluczowe dla spedycji - sam kraj/miasto/kod nie wystarcza kierowcy zeby
 * dojechac. Adres pelny (ulica + numer + ew. nr lokalu) osobne pole.
 */
class AddAddressToPickupDelivery extends BaseMigration
{
    public function up(): void
    {
        $this->table('speed_orders')
            ->addColumn('load_address', 'string', [
                'limit' => 255, 'null' => true,
                'after' => 'load_city',
                'comment' => 'Ulica + numer dla miejsca zaladunku (np. Wielicka 22)',
            ])
            ->addColumn('unload_address', 'string', [
                'limit' => 255, 'null' => true,
                'after' => 'unload_city',
                'comment' => 'Ulica + numer dla miejsca rozladunku',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('speed_orders')
            ->removeColumn('unload_address')
            ->removeColumn('load_address')
            ->update();
    }
}

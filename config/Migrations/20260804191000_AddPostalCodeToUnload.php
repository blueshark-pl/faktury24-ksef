<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * unload_postal_code - brakujace pole. load_postal_code juz istnieje
 * (ExpandSpeedOrders 2026-04-09), ale unload nie mial - przeoczenie.
 */
class AddPostalCodeToUnload extends BaseMigration
{
    public function up(): void
    {
        $this->table('speed_orders')
            ->addColumn('unload_postal_code', 'string', [
                'limit' => 20, 'null' => true,
                'after' => 'unload_country',
                'comment' => 'Kod pocztowy miejsca rozladunku',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('speed_orders')
            ->removeColumn('unload_postal_code')
            ->update();
    }
}

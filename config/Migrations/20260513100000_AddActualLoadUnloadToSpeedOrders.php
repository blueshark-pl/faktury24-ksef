<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * speed_orders.actual_load_at / actual_unload_at — rzeczywisty czas
 * załadunku i rozładunku (datetime), niezależny od POL/POD documents.
 *
 * Backfill: dla zleceń z date_doc <= 2026-05-08 (historia) → wypełniamy
 * z pól planowanych (date_deadline / date_delivery). Dla zleceń nowszych
 * pola zostają NULL — uzupełniane ręcznie przez spedytora.
 */
class AddActualLoadUnloadToSpeedOrders extends BaseMigration
{
    public function up(): void
    {
        $this->table('speed_orders')
            ->addColumn('actual_load_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'after'   => 'date_delivery',
                'comment' => 'Rzeczywisty czas załadunku (niezależny od POL document)',
            ])
            ->addColumn('actual_unload_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'after'   => 'actual_load_at',
                'comment' => 'Rzeczywisty czas rozładunku (niezależny od POD document)',
            ])
            ->update();

        // Backfill: zlecenia historyczne (date_doc <= 2026-05-08) dostają
        // wartości z planowanych dat. Nowsze zostają NULL.
        $cutoff = '2026-05-08';
        $this->execute(
            "UPDATE speed_orders
             SET actual_load_at = date_deadline
             WHERE date_doc <= '{$cutoff}'
               AND actual_load_at IS NULL
               AND date_deadline IS NOT NULL"
        );
        $this->execute(
            "UPDATE speed_orders
             SET actual_unload_at = date_delivery
             WHERE date_doc <= '{$cutoff}'
               AND actual_unload_at IS NULL
               AND date_delivery IS NOT NULL"
        );
    }

    public function down(): void
    {
        $this->table('speed_orders')
            ->removeColumn('actual_load_at')
            ->removeColumn('actual_unload_at')
            ->update();
    }
}

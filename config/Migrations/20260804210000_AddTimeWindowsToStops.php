<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Multi-stop = pickup/delivery: te same pola.
 *  - time_from / time_to (okno czasowe magazynu)
 *  - contact_email (obok contact_name i contact_phone)
 */
class AddTimeWindowsToStops extends BaseMigration
{
    public function up(): void
    {
        $this->table('speed_order_stops')
            ->addColumn('time_from', 'time', [
                'null' => true, 'after' => 'planned_at',
                'comment' => 'Okno czasowe od (godzina otwarcia magazynu)',
            ])
            ->addColumn('time_to', 'time', [
                'null' => true, 'after' => 'time_from',
                'comment' => 'Okno czasowe do (godzina zamkniecia)',
            ])
            ->addColumn('contact_email', 'string', [
                'limit' => 180, 'null' => true, 'after' => 'contact_phone',
                'comment' => 'Email osoby do kontaktu na miejscu',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('speed_order_stops')
            ->removeColumn('contact_email')
            ->removeColumn('time_to')
            ->removeColumn('time_from')
            ->update();
    }
}

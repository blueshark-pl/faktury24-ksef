<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Powód i notatka przy zmianie statusu — gdy spedytor zamyka POL/POD z datą
 * niezgodną z planowaną, musi podać powód (dropdown) i/lub notatkę.
 */
class AddReasonNoteToStatusLogs extends AbstractMigration
{
    public function change(): void
    {
        $this->table('speed_order_status_logs')
            ->addColumn('reason', 'string', [
                'limit'   => 100,
                'null'    => true,
                'default' => null,
                'comment' => 'Kod powodu: downtime|cargo_not_ready|driver_late|no_avisation|custom',
                'after'   => 'new_value',
            ])
            ->addColumn('note', 'text', [
                'null'    => true,
                'default' => null,
                'comment' => 'Wolny tekst notatki uzupełniającej',
                'after'   => 'reason',
            ])
            ->update();
    }
}

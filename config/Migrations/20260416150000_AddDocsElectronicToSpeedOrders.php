<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddDocsElectronicToSpeedOrders extends AbstractMigration
{
    public function up(): void
    {
        $this->table('speed_orders')
            ->addColumn('docs_electronic_only', 'boolean', [
                'null'    => false,
                'default' => false,
                'comment' => 'Dokumenty tylko elektronicznie — brak wysyłki papierowej',
                'after'   => 'is_complete',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('speed_orders')
            ->removeColumn('docs_electronic_only')
            ->update();
    }
}

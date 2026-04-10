<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddInvoiceIdToSpeedOrders extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('speed_orders');
        $table
            ->addColumn('invoice_id', 'string', [
                'limit'   => 36,
                'null'    => true,
                'default' => null,
                'comment' => 'UUID faktury wystawionej na podstawie tego zlecenia',
                'after'   => 'speed_modified_at',
            ])
            ->addColumn('invoiced_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'comment' => 'Kiedy wystawiono fakturę',
                'after'   => 'invoice_id',
            ])
            ->addIndex(['invoice_id'])
            ->update();
    }
}

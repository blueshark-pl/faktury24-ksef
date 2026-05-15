<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddAdvanceReceivedDateToInvoices extends AbstractMigration
{
    public function change(): void
    {
        $this->table('invoices')
            ->addColumn('advance_received_date', 'date', [
                'null'    => true,
                'default' => null,
                'after'   => 'sold_date',
                'comment' => 'Data otrzymania zaliczki (DataOtrzym w KSeF FA(3)) — tylko dla type=advance/ZAL',
            ])
            ->update();
    }
}

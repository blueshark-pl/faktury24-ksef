<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddOverrideNextNumberToInvoiceSeries extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('invoice_series');
        $table->addColumn('override_next_number', 'integer', [
            'null'    => true,
            'default' => null,
            'after'   => 'starting_number',
        ]);
        $table->update();
    }
}

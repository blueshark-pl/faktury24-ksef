<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Dodaje kolumnę `position` do `invoice_contents` —
 * determinuje kolejność wierszy faktury (NrWierszaFa w XML KSeF).
 *
 * Bez tej kolumny wiersze ładowane są w kolejności UUID (losowej),
 * co powoduje błędne przypisanie DodatkowyOpis do linii (nr_wiersza).
 */
class AddPositionToInvoiceContents extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('invoice_contents');
        if (!$table->hasColumn('position')) {
            $table->addColumn('position', 'integer', [
                'default' => 0,
                'null'    => false,
                'after'   => 'invoice_id',
            ]);
            $table->addIndex(['invoice_id', 'position'], [
                'name' => 'idx_invoice_contents_position',
            ]);
            $table->update();
        }
    }
}

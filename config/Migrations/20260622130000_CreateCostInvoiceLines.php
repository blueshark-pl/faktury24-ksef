<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Pozycje faktur kosztowych — odpowiednik `ksef_booking_items` ale per
 * `cost_invoices` (nie per ksef_number — bo manualne faktury nie mają KSeF nr).
 *
 * Pozwala dekretować (klasyfikować) koszt per linia: każda pozycja może mieć
 * przypisaną kategorię (`cost_category_id` → `cost_categories`).
 */
class CreateCostInvoiceLines extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('cost_invoice_lines', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'uuid', ['null' => false]);
        $table->addColumn('cost_invoice_id', 'integer', ['null' => false]);
        $table->addColumn('line_index', 'integer', ['null' => false, 'default' => 0]);
        $table->addColumn('line_id', 'string', ['limit' => 64, 'null' => true, 'default' => null,
            'comment' => 'Identyfikator z XML (FaWiersz NrWierszaFa)']);
        $table->addColumn('name', 'string', ['limit' => 500, 'null' => false, 'default' => '']);
        $table->addColumn('quantity', 'decimal', ['precision' => 14, 'scale' => 4, 'null' => true, 'default' => null]);
        $table->addColumn('unit', 'string', ['limit' => 32, 'null' => true, 'default' => null]);
        $table->addColumn('unit_price', 'decimal', ['precision' => 14, 'scale' => 4, 'null' => true, 'default' => null]);
        $table->addColumn('net_amount', 'decimal', ['precision' => 14, 'scale' => 2, 'null' => true, 'default' => null]);
        $table->addColumn('vat_rate', 'string', ['limit' => 8, 'null' => true, 'default' => null]);
        $table->addColumn('vat_amount', 'decimal', ['precision' => 14, 'scale' => 2, 'null' => true, 'default' => null]);
        $table->addColumn('gross_amount', 'decimal', ['precision' => 14, 'scale' => 2, 'null' => true, 'default' => null]);
        $table->addColumn('currency', 'char', ['limit' => 3, 'null' => true, 'default' => null]);

        // Dekretacja
        $table->addColumn('cost_category_id', 'uuid', ['null' => true, 'default' => null,
            'comment' => 'FK do cost_categories — kategoria kosztu (klasyfikacja)']);
        $table->addColumn('cost_category_name', 'string', ['limit' => 255, 'null' => true, 'default' => null,
            'comment' => 'Snapshot nazwy kategorii (gdy kategoria zostanie usunięta)']);
        $table->addColumn('note', 'text', ['null' => true, 'default' => null,
            'comment' => 'Uwagi operatora do tej pozycji (np. opis dekretacji)']);

        $table->addColumn('source_json', 'text', ['null' => true, 'default' => null,
            'comment' => 'Surowe dane z faktury KSeF (FaWiersz fragment)']);
        $table->addTimestamps('created', 'modified');

        $table->addIndex(['cost_invoice_id'], ['name' => 'BY_COST_INVOICE']);
        $table->addIndex(['cost_category_id'], ['name' => 'BY_COST_CATEGORY']);

        $table->addForeignKey('cost_invoice_id', 'cost_invoices', 'id', [
            'delete' => 'CASCADE', 'update' => 'NO_ACTION',
        ]);
        // cost_category_id → cost_categories.id SET NULL (kategoria może być usunięta)
        $table->addForeignKey('cost_category_id', 'cost_categories', 'id', [
            'delete' => 'SET_NULL', 'update' => 'NO_ACTION',
        ]);

        $table->create();
    }
}

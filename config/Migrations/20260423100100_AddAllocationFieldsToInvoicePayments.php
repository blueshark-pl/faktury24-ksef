<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddAllocationFieldsToInvoicePayments extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('invoice_payments');

        // Powiązanie z alokacją przelewu (jeśli wpłata pochodzi z przypisania przelewu)
        $table->addColumn('bank_transaction_allocation_id', 'char', [
            'limit'   => 36,
            'null'    => true,
            'default' => null,
            'after'   => 'invoice_id',
        ]);

        // Typ płatności: brutto (całość) / netto (bez VAT) / vat (sam VAT)
        $table->addColumn('payment_type', 'string', [
            'limit'   => 10,
            'null'    => false,
            'default' => 'gross',
            'after'   => 'bank_transaction_allocation_id',
            'comment' => 'gross|net|vat',
        ]);

        // Waluta wpłaty (może się różnić od waluty faktury przy MPP)
        $table->addColumn('currency', 'char', [
            'limit'   => 3,
            'null'    => false,
            'default' => 'PLN',
            'after'   => 'payment_type',
        ]);

        $table->addIndex(['bank_transaction_allocation_id'], ['name' => 'BY_ALLOCATION']);

        $table->update();
    }
}

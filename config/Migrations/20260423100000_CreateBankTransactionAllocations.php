<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateBankTransactionAllocations extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('bank_transaction_allocations', ['id' => false, 'primary_key' => ['id']]);

        $table->addColumn('id', 'uuid', ['null' => false]);
        $table->addColumn('company_id', 'uuid', ['null' => false]);
        $table->addColumn('bank_transaction_id', 'uuid', ['null' => false]);

        // FK do faktury — system lub legacy (dokładnie jedno wypełnione)
        $table->addColumn('invoice_id', 'uuid', ['null' => true, 'default' => null]);
        $table->addColumn('legacy_invoice_id', 'char', ['limit' => 36, 'null' => true, 'default' => null]);

        // Opcjonalne powiązanie z rekordem wpłaty (tworzone automatycznie)
        $table->addColumn('invoice_payment_id', 'uuid', ['null' => true, 'default' => null]);

        // Ile z przelewu przypisano do tej faktury
        $table->addColumn('allocated_amount', 'decimal', [
            'precision' => 12,
            'scale'     => 4,
            'null'      => false,
        ]);
        $table->addColumn('currency', 'char', ['limit' => 3, 'null' => false, 'default' => 'PLN']);

        // Typ płatności: brutto / netto (bez VAT) / sam VAT
        $table->addColumn('allocation_type', 'string', [
            'limit'   => 10,
            'null'    => false,
            'default' => 'gross',
            'comment' => 'gross|net|vat',
        ]);

        $table->addColumn('note', 'string', ['limit' => 255, 'null' => true, 'default' => null]);
        $table->addTimestamps('created', 'modified');

        $table->addIndex(['bank_transaction_id'], ['name' => 'BY_BANK_TX']);
        $table->addIndex(['invoice_id'],          ['name' => 'BY_INVOICE']);
        $table->addIndex(['legacy_invoice_id'],   ['name' => 'BY_LEGACY_INVOICE']);
        $table->addIndex(['company_id'],          ['name' => 'BY_COMPANY']);

        $table->addForeignKey('bank_transaction_id', 'bank_transactions', 'id', [
            'delete' => 'CASCADE', 'update' => 'NO_ACTION',
        ]);
        $table->addForeignKey('invoice_id', 'invoices', 'id', [
            'delete' => 'CASCADE', 'update' => 'NO_ACTION',
        ]);
        $table->addForeignKey('invoice_payment_id', 'invoice_payments', 'id', [
            'delete' => 'SET_NULL', 'update' => 'NO_ACTION',
        ]);

        $table->create();
    }
}

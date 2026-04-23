<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Dodaje FK do bank_transaction_allocations.
 * Osobna migracja ponieważ pierwsza próba nie dodała FK z powodu błędu typu kolumny.
 * Tabela istnieje już z właściwymi kolumnami CHAR(36) kompatybilnymi z UUID.
 */
class AddForeignKeysToBankTransactionAllocations extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('bank_transaction_allocations');

        $table->addForeignKey('bank_transaction_id', 'bank_transactions', 'id', [
            'delete' => 'CASCADE', 'update' => 'NO_ACTION',
        ]);
        $table->addForeignKey('invoice_id', 'invoices', 'id', [
            'delete' => 'CASCADE', 'update' => 'NO_ACTION',
        ]);
        $table->addForeignKey('invoice_payment_id', 'invoice_payments', 'id', [
            'delete' => 'SET_NULL', 'update' => 'NO_ACTION',
        ]);

        $table->update();
    }
}

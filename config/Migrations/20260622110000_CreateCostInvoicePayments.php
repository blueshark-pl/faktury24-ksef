<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Historia wpłat dla faktur kosztowych — analog `invoice_payments` ale dla
 * `cost_invoices`. Pozwala śledzić każdą wpłatę osobno (data, kwota, metoda)
 * z opcjonalnym back-linkiem do konkretnego przelewu z `bank_transactions`.
 *
 * Pole `paid_amount` na cost_invoices jest przeliczane jako SUM(amount) z tej
 * tabeli po każdej zmianie.
 */
class CreateCostInvoicePayments extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('cost_invoice_payments', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'uuid', ['null' => false]);
        $table->addColumn('cost_invoice_id', 'integer', ['null' => false]);
        $table->addColumn('payment_date', 'date', ['null' => false]);
        $table->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false, 'default' => '0.00']);
        $table->addColumn('currency', 'char', ['limit' => 3, 'null' => false, 'default' => 'PLN']);
        $table->addColumn('payment_method', 'string', ['limit' => 20, 'null' => true, 'default' => null,
            'comment' => 'transfer|cash|card|compensation|other']);
        $table->addColumn('payment_type', 'string', ['limit' => 10, 'null' => false, 'default' => 'manual',
            'comment' => 'manual|bank — czy z banku auto czy ręcznie']);
        $table->addColumn('bank_transaction_id', 'uuid', ['null' => true, 'default' => null,
            'comment' => 'FK do bank_transactions gdy wpłata pochodzi z banku (direction=D)']);
        $table->addColumn('user_id', 'uuid', ['null' => true, 'default' => null,
            'comment' => 'Kto dodał wpłatę']);
        $table->addColumn('note', 'string', ['limit' => 255, 'null' => true, 'default' => null]);
        $table->addTimestamps('created', 'modified');

        $table->addIndex(['cost_invoice_id'], ['name' => 'BY_COST_INVOICE']);
        $table->addIndex(['bank_transaction_id'], ['name' => 'BY_BANK_TX']);
        $table->addIndex(['payment_date'], ['name' => 'BY_PAYMENT_DATE']);

        $table->addForeignKey('cost_invoice_id', 'cost_invoices', 'id', [
            'delete' => 'CASCADE', 'update' => 'NO_ACTION',
        ]);
        $table->addForeignKey('bank_transaction_id', 'bank_transactions', 'id', [
            'delete' => 'SET_NULL', 'update' => 'NO_ACTION',
        ]);

        $table->create();
    }
}

<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateInvoicePayments extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('invoice_payments', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'char', [
            'limit' => 36,
            'null' => false,
        ]);
        $table->addColumn('invoice_id', 'char', [
            'limit' => 36,
            'null' => false,
        ]);
        $table->addColumn('payment_date', 'date', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('amount', 'decimal', [
            'default' => null,
            'null' => false,
            'precision' => 10,
            'scale' => 2,
        ]);
        $table->addColumn('payment_method', 'string', [
            'default' => 'transfer',
            'limit' => 50,
            'null' => false,
        ]);
        $table->addColumn('description', 'text', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addIndex([
            'invoice_id',
        ], [
            'name' => 'BY_INVOICE_ID',
        ]);
        $table->addIndex([
            'payment_date',
        ], [
            'name' => 'BY_PAYMENT_DATE',
        ]);
        $table->addForeignKey('invoice_id', 'invoices', 'id', [
            'delete' => 'CASCADE',
            'update' => 'NO_ACTION'
        ]);
        $table->create();
    }
}

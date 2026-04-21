<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateBankTransactions extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('bank_transactions', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('import_id', 'uuid', ['null' => false])
            ->addColumn('account_number', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('value_date', 'date', ['null' => false])
            ->addColumn('booking_date', 'date', ['null' => true, 'default' => null])
            ->addColumn('direction', 'string', ['limit' => 2, 'null' => false, 'comment' => 'C=credit D=debit'])
            ->addColumn('amount', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false])
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'PLN'])
            ->addColumn('transaction_code', 'string', ['limit' => 10, 'null' => true, 'default' => null])
            ->addColumn('customer_reference', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('bank_reference', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addColumn('party_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('party_account', 'string', ['limit' => 60, 'null' => true, 'default' => null])
            ->addColumn('title', 'text', ['null' => true, 'default' => null])
            ->addColumn('import_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('invoice_id', 'uuid', ['null' => true, 'default' => null, 'comment' => 'future: matched invoice'])
            ->addColumn('is_matched', 'boolean', ['null' => false, 'default' => false])
            ->addTimestamps('created', 'modified')
            ->addIndex(['company_id'])
            ->addIndex(['import_id'])
            ->addIndex(['value_date'])
            ->addIndex(['import_hash'], ['unique' => true])
            ->addIndex(['invoice_id'])
            ->create();
    }
}

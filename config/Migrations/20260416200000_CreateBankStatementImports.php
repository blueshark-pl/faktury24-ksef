<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateBankStatementImports extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('bank_statement_imports', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('filename', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('account_number', 'string', ['limit' => 50, 'null' => true, 'default' => null])
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => true, 'default' => 'PLN'])
            ->addColumn('statement_from', 'date', ['null' => true, 'default' => null])
            ->addColumn('statement_to', 'date', ['null' => true, 'default' => null])
            ->addColumn('opening_balance', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('closing_balance', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('transaction_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('new_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('duplicate_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('imported_by', 'uuid', ['null' => true, 'default' => null])
            ->addTimestamps('created', 'modified')
            ->addIndex(['company_id'])
            ->addIndex(['account_number'])
            ->create();
    }
}

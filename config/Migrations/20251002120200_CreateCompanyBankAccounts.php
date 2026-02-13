<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateCompanyBankAccounts extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('company_bank_accounts', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid')
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('iban', 'string', ['limit' => 34, 'null' => false]) // IBAN (PL: 28 znaków, ale zostaw 34)
            ->addColumn('bank_name', 'string', ['limit' => 160, 'null' => true, 'default' => null])
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'PLN'])
            ->addColumn('is_default', 'boolean', ['default' => 0, 'null' => false])
            ->addColumn('label', 'string', ['limit' => 120, 'null' => true, 'default' => null]) // np. "Rachunek bieżący"
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['company_id'], ['name' => 'IDX_cba_company_id'])
            ->addIndex(['company_id', 'is_default'], ['name' => 'IDX_cba_company_default'])
            ->addIndex(['iban'], ['unique' => false, 'name' => 'IDX_cba_iban'])
            ->create();

        // FK
        $this->execute("
            ALTER TABLE `company_bank_accounts`
            ADD CONSTRAINT `FK_cba_company`
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`)
            ON UPDATE CASCADE ON DELETE CASCADE
        ");
    }
}

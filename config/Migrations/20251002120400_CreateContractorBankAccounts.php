<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateContractorBankAccounts extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('contractor_bank_accounts', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid')
            ->addColumn('contractor_id', 'uuid', ['null' => false])
            ->addColumn('iban', 'string', ['limit' => 34, 'null' => false])
            ->addColumn('bank_name', 'string', ['limit' => 160, 'null' => true, 'default' => null])
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'PLN'])
            ->addColumn('is_default', 'boolean', ['default' => 0, 'null' => false])
            ->addColumn('label', 'string', ['limit' => 120, 'null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['contractor_id'], ['name' => 'IDX_ctba_contractor_id'])
            ->addIndex(['contractor_id', 'is_default'], ['name' => 'IDX_ctba_contractor_default'])
            ->addIndex(['iban'], ['name' => 'IDX_ctba_iban'])
            ->create();

        $this->execute("
            ALTER TABLE `contractor_bank_accounts`
            ADD CONSTRAINT `FK_ctba_contractor`
            FOREIGN KEY (`contractor_id`) REFERENCES `contractors`(`id`)
            ON UPDATE CASCADE ON DELETE CASCADE
        ");
    }
}

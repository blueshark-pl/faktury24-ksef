<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateContractors extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('contractors', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid')
            ->addColumn('company_id', 'uuid', ['null' => false]) // właściciel (tenant)
            ->addColumn('name', 'string', ['limit' => 200, 'null' => false])
            ->addColumn('altname', 'string', ['limit' => 160, 'null' => true, 'default' => null])
            ->addColumn('nip', 'string', ['limit' => 20, 'null' => true, 'default' => null]) // zagraniczne formaty bywają dłuższe
            ->addColumn('regon', 'string', ['limit' => 14, 'null' => true, 'default' => null])
            ->addColumn('eu_vat', 'boolean', ['default' => 0, 'null' => false]) // podatnik VAT-UE
            ->addColumn('country', 'string', ['limit' => 2, 'null' => true, 'default' => 'PL'])
            ->addColumn('postal_code', 'string', ['limit' => 16, 'null' => true, 'default' => null])
            ->addColumn('city', 'string', ['limit' => 120, 'null' => true, 'default' => null])
            ->addColumn('street', 'string', ['limit' => 160, 'null' => true, 'default' => null])
            ->addColumn('local_number', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('phone', 'string', ['limit' => 40, 'null' => true, 'default' => null])
            ->addColumn('email', 'string', ['limit' => 160, 'null' => true, 'default' => null])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('is_active', 'boolean', ['default' => 1, 'null' => false])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['company_id'], ['name' => 'IDX_contractors_company_id'])
            ->addIndex(['company_id', 'name'], ['name' => 'IDX_contractors_company_name'])
            ->addIndex(['company_id', 'nip'], ['name' => 'IDX_contractors_company_nip'])
            ->create();

        // FK
        $this->execute("
            ALTER TABLE `contractors`
            ADD CONSTRAINT `FK_contractors_company`
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`)
            ON UPDATE CASCADE ON DELETE CASCADE
        ");
    }
}

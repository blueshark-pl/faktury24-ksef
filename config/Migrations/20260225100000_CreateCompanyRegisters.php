<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateCompanyRegisters extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('company_registers', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid')
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 160, 'null' => true, 'default' => null])
            ->addColumn('krs', 'string', ['limit' => 20, 'null' => true, 'default' => null])
            ->addColumn('regon', 'string', ['limit' => 20, 'null' => true, 'default' => null])
            ->addColumn('bdo', 'string', ['limit' => 20, 'null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['company_id'], ['name' => 'IDX_cr_company_id'])
            ->create();

        // FK
        $this->execute("
            ALTER TABLE `company_registers`
            ADD CONSTRAINT `FK_cr_company`
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`)
            ON UPDATE CASCADE ON DELETE CASCADE
        ");
    }
}

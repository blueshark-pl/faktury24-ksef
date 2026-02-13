<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddCompanyIdToUsers extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('users');
        if (!$table->hasColumn('company_id')) {
            $table->addColumn('company_id', 'uuid', ['null' => true, 'default' => null])->update();
        }

        $this->table('users')
            ->addIndex(['company_id'], ['name' => 'IDX_users_company_id'])
            ->update();

        // FK
        $this->execute("
            ALTER TABLE `users`
            ADD CONSTRAINT `FK_users_company_id`
            FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`)
            ON UPDATE CASCADE ON DELETE SET NULL
        ");
    }

    public function down(): void
    {
        // drop FK if exists
        $this->execute("ALTER TABLE `users` DROP FOREIGN KEY `FK_users_company_id`");
        $table = $this->table('users');
        if ($table->hasIndex('IDX_users_company_id')) {
            $table->removeIndexByName('IDX_users_company_id')->update();
        }
        if ($table->hasColumn('company_id')) {
            $table->removeColumn('company_id')->update();
        }
    }
}

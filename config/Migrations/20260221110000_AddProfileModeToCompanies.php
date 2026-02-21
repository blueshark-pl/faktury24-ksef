<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddProfileModeToCompanies extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('companies');

        if (!$table->hasColumn('profile_mode')) {
            $table
                ->addColumn('profile_mode', 'string', [
                    'limit' => 32,
                    'null' => false,
                    'default' => 'business',
                    'after' => 'invoice_template',
                ])
                ->addIndex(['profile_mode'], ['name' => 'IDX_companies_profile_mode'])
                ->update();
        }
    }
}

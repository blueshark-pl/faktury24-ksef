<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateCurrencyFavorites extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('currency_favorites')) {
            return;
        }
        $table = $this->table('currency_favorites', [
            'id' => 'uuid',
            'collation' => 'utf8mb4_polish_ci',
        ]);
        $table
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('code', 'string', ['limit' => 3, 'null' => false])
            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['company_id', 'code'], ['unique' => true, 'name' => 'uniq_company_code'])
            ->create();
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateApiTokens extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('api_tokens')) {
            return;
        }

        $this->table('api_tokens', [
            'id'          => false,
            'primary_key' => ['id'],
            'collation'   => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('user_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 128, 'null' => false, 'default' => 'Domyślny token'])
            ->addColumn('token_hash', 'char', ['limit' => 64, 'null' => false, 'comment' => 'SHA-256 of raw token'])
            ->addColumn('token_prefix', 'char', ['limit' => 8, 'null' => false, 'comment' => 'First 8 chars for display'])
            ->addColumn('last_used_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('expires_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['company_id'])
            ->addIndex(['user_id'])
            ->addIndex(['token_hash'], ['unique' => true])
            ->create();
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateE100Accounts extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('e100_accounts', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('label', 'string', ['limit' => 100, 'null' => false, 'comment' => 'Nazwa konta np. "E100 Główne"'])
            ->addColumn('username', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('password_enc', 'text', ['null' => false, 'comment' => 'Szyfrowane hasło (openssl)'])
            ->addColumn('client_code', 'string', ['limit' => 50, 'null' => true, 'default' => null, 'comment' => 'Kod klienta E100 (z tokenu)'])
            ->addColumn('fullname', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('defcur', 'string', ['limit' => 3, 'null' => true, 'default' => 'EUR'])
            ->addColumn('access_token', 'text', ['null' => true, 'default' => null])
            ->addColumn('refresh_token', 'text', ['null' => true, 'default' => null])
            ->addColumn('token_expires_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('last_sync_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('last_sync_row_version', 'string', ['limit' => 20, 'null' => true, 'default' => null, 'comment' => 'RowVersion do sync delta'])
            ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
            ->addTimestamps('created', 'modified')
            ->addIndex(['company_id'])
            ->addIndex(['username'])
            ->create();
    }
}

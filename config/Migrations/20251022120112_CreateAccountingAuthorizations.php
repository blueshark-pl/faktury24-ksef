<?php
use Migrations\AbstractMigration;

class CreateAccountingAuthorizations extends AbstractMigration
{
    public function change(): void
    {
        $this->table('accounting_authorizations', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('company_id', 'uuid', ['null' => false, 'comment' => 'FK -> companies.id'])
            ->addColumn('provider', 'string', ['limit' => 40, 'null' => true, 'comment' => 'np. wfirma|infakt|optima|enova365'])
            ->addColumn('environment', 'string', ['limit' => 10, 'default' => 'prod', 'comment' => 'prod|test'])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active', 'comment' => 'active|revoked|expired|invalid|pending'])
            ->addColumn('is_active', 'boolean', ['default' => true])
            ->addColumn('valid_from', 'datetime', ['null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => true])

            // bezpieczne przechowanie
            ->addColumn('token_cipher', 'text', ['null' => false, 'comment' => 'zaszyfrowany token'])
            ->addColumn('token_last4', 'string', ['limit' => 8, 'null' => true])

            // metadane / przydatne do integracji
            ->addColumn('scopes', 'text', ['null' => true, 'comment' => 'opcjonalny JSON'])
            ->addColumn('last_synced_at', 'datetime', ['null' => true])

            ->addTimestamps('created', 'modified')
            ->addIndex(['company_id'])
            ->addIndex(['company_id', 'is_active'])
            ->addIndex(['provider'])
            ->create();
    }
};

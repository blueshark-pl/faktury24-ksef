<?php

// config/Migrations/20251022xxxxxx_CreateKsefAuthorizations.php
use Migrations\AbstractMigration;

class CreateKsefAuthorizations extends AbstractMigration
{
    public function change(): void
    {
        $this->table('ksef_authorizations', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('company_id', 'uuid', ['null' => false, 'comment' => 'FK -> companies.id'])
            ->addColumn('environment', 'string', ['limit' => 10, 'default' => 'prod', 'comment' => 'prod|test'])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active', 'comment' => 'active|revoked|expired|invalid|pending'])
            ->addColumn('is_active', 'boolean', ['default' => true, 'comment' => 'aplikacyjnie pilnujemy, aby max 1/firmę'])
            ->addColumn('auth_method', 'string', ['limit' => 30, 'null' => true, 'comment' => 'pz|qualified_sign|qualified_seal'])
            ->addColumn('scopes', 'text', ['null' => true, 'comment' => 'opcjonalnie JSON z uprawnieniami z KSeF'])
            ->addColumn('valid_from', 'datetime', ['null' => true])
            ->addColumn('expires_at', 'datetime', ['null' => true])

            // BEZPIECZEŃSTWO: token przechowujemy zaszyfrowany + szybki fingerprint do podglądu
            ->addColumn('token_cipher', 'text', ['null' => false, 'comment' => 'szyfrogram (np. sodium)'])
            ->addColumn('token_last4', 'string', ['limit' => 8, 'null' => true])

            ->addColumn('last_verified_at', 'datetime', ['null' => true])
            ->addColumn('revoked_at', 'datetime', ['null' => true])
            ->addColumn('created_by', 'uuid', ['null' => true])
            ->addColumn('revoked_by', 'uuid', ['null' => true])

            ->addTimestamps('created', 'modified')
            ->addIndex(['company_id'])
            ->addIndex(['company_id', 'is_active'])
            ->create();
    }
}

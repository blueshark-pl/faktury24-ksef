<?php

// config/Migrations/20251022xxxxxx_CreateKsefDocuments.php
use Migrations\AbstractMigration;

class CreateKsefDocuments extends AbstractMigration
{
    public function change(): void
    {
        $this->table('ksef_documents', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('invoice_id', 'uuid', ['null' => true, 'comment' => 'FK -> invoices.id (jeśli masz)'])
            ->addColumn('authorization_id', 'uuid', ['null' => false, 'comment' => 'FK -> ksef_authorizations.id'])
            ->addColumn('reference_number', 'string', ['limit' => 64, 'null' => true, 'comment' => 'Numer KSeF (KSeFReferenceNumber)'])
            ->addColumn('status', 'string', ['limit' => 30, 'default' => 'queued', 'comment' => 'queued|sent|accepted|rejected|delivered|error'])
            ->addColumn('sent_at', 'datetime', ['null' => true])
            ->addColumn('last_checked_at', 'datetime', ['null' => true])
            ->addColumn('last_error', 'text', ['null' => true])
            ->addColumn('last_response', 'text', ['null' => true, 'comment' => 'raw JSON z KSeF'])
            ->addTimestamps('created', 'modified')
            ->addIndex(['company_id'])
            ->addIndex(['invoice_id'])
            ->addIndex(['authorization_id'])
            ->addIndex(['reference_number'])
            ->create();
    }
}

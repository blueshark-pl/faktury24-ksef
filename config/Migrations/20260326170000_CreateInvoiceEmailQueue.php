<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateInvoiceEmailQueue extends AbstractMigration
{
    public function change(): void
    {
        $this->table('invoice_email_queue', [
            'id'          => false,
            'primary_key' => ['id'],
            'collation'   => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => false])
        ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
        ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
        // pending / sending / sent / failed
        ->addColumn('status', 'string', ['limit' => 16, 'null' => false, 'default' => 'pending'])
        ->addColumn('attempts', 'integer', ['null' => false, 'default' => 0])
        ->addColumn('last_error', 'text', ['null' => true, 'default' => null])
        ->addColumn('sent_at', 'datetime', ['null' => true, 'default' => null])
        ->addColumn('scheduled_at', 'datetime', ['null' => false])
        ->addTimestamps('created', 'modified')
        ->addIndex(['invoice_id'])
        ->addIndex(['company_id'])
        ->addIndex(['status', 'scheduled_at'])
        ->create();
    }
}

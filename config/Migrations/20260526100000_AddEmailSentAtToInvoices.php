<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddEmailSentAtToInvoices extends AbstractMigration
{
    public function change(): void
    {
        $this->table('invoices')
            ->addColumn('email_sent_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'comment' => 'Kiedy ostatnio wysłano fakturę e-mailem do klienta',
                'after'   => 'workflow_status',
            ])
            ->update();
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddLabelAndSentFieldsToInvoices extends AbstractMigration
{
    public function up(): void
    {
        $this->table('invoices')
            ->addColumn('sent_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'comment' => 'Data wysłania faktury do klienta',
                'after'   => 'paid_at',
            ])
            ->addColumn('sent_by', 'string', [
                'limit'   => 100,
                'null'    => true,
                'default' => null,
                'comment' => 'Kto wysłał fakturę do klienta',
                'after'   => 'sent_at',
            ])
            ->addColumn('label_data', 'text', [
                'null'    => true,
                'default' => null,
                'comment' => 'JSON z danymi etykiety pocztowej (nadawca/odbiorca/edytowane pola)',
                'after'   => 'sent_by',
            ])
            ->addColumn('label_generated_at', 'datetime', [
                'null'    => true,
                'default' => null,
                'comment' => 'Data ostatniego wygenerowania etykiety PDF',
                'after'   => 'label_data',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('invoices')
            ->removeColumn('sent_at')
            ->removeColumn('sent_by')
            ->removeColumn('label_data')
            ->removeColumn('label_generated_at')
            ->update();
    }
}

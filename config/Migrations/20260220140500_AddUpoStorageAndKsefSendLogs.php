<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddUpoStorageAndKsefSendLogs extends AbstractMigration
{
    public function change(): void
    {
        $invoices = $this->table('invoices');

        if (!$invoices->hasColumn('upo_xml')) {
            $invoices->addColumn('upo_xml', 'text', [
                'null' => true,
                'default' => null,
                'comment' => 'Odebrane UPO XML',
                'after' => 'planned_ksef_send_at',
            ]);
        }

        if (!$invoices->hasColumn('upo_downloaded_at')) {
            $invoices->addColumn('upo_downloaded_at', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => 'Kiedy odebrano UPO',
                'after' => 'upo_xml',
            ]);
        }

        $invoices->update();

        if (!$this->hasTable('ksef_send_logs')) {
            $this->table('ksef_send_logs', [
                'id' => false,
                'primary_key' => ['id'],
                'collation' => 'utf8mb4_unicode_ci',
            ])
                ->addColumn('id', 'char', ['limit' => 36])
                ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
                ->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => false])
                ->addColumn('event_type', 'string', ['limit' => 40, 'null' => false])
                ->addColumn('status_code', 'string', ['limit' => 16, 'null' => true, 'default' => null])
                ->addColumn('message', 'text', ['null' => true, 'default' => null])
                ->addColumn('payload', 'text', ['null' => true, 'default' => null])
                ->addColumn('created', 'datetime', ['null' => false])
                ->addIndex(['company_id'])
                ->addIndex(['invoice_id'])
                ->addIndex(['event_type'])
                ->addIndex(['created'])
                ->create();
        }
    }
}

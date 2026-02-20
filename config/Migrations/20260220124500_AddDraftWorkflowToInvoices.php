<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddDraftWorkflowToInvoices extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('invoices');

        if (!$table->hasColumn('workflow_status')) {
            $table->addColumn('workflow_status', 'string', [
                'null' => true,
                'default' => 'issued',
                'limit' => 24,
                'comment' => 'Invoice workflow: draft|issued|sent|error',
                'after' => 'is_api',
            ]);
        }

        if (!$table->hasColumn('planned_ksef_send_at')) {
            $table->addColumn('planned_ksef_send_at', 'date', [
                'null' => true,
                'default' => null,
                'comment' => 'Planned date for KSeF send (draft workflow)',
                'after' => 'workflow_status',
            ]);
        }

        $table->update();

        $table = $this->table('invoices');
        if (!$table->hasIndex(['workflow_status'])) {
            $table->addIndex(['workflow_status'], ['name' => 'idx_invoices_workflow_status']);
        }
        if (!$table->hasIndex(['planned_ksef_send_at'])) {
            $table->addIndex(['planned_ksef_send_at'], ['name' => 'idx_invoices_planned_ksef_send_at']);
        }
        $table->update();
    }
}

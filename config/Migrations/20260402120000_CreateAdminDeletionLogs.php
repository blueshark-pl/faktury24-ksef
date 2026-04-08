<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateAdminDeletionLogs extends AbstractMigration
{
    public function change(): void
    {
        $this->table('admin_deletion_logs', [
            'id'          => false,
            'primary_key' => ['id'],
            'collation'   => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('invoice_id', 'char', ['limit' => 36, 'comment' => 'Oryginalne UUID faktury (już usunięte)'])
        ->addColumn('deleted_by_user_id', 'char', ['limit' => 36, 'null' => true, 'comment' => 'users.id admina'])
        ->addColumn('deleted_by_username', 'string', ['limit' => 128, 'null' => true, 'comment' => 'login/email admina w chwili usunięcia'])
        ->addColumn('company_id', 'char', ['limit' => 36, 'null' => true, 'comment' => 'company_id właściciela faktury'])
        ->addColumn('company_name', 'string', ['limit' => 255, 'null' => true])
        ->addColumn('fullnumber', 'string', ['limit' => 64, 'null' => true])
        ->addColumn('invoice_type', 'string', ['limit' => 24, 'null' => true])
        ->addColumn('invoice_date', 'date', ['null' => true])
        ->addColumn('total', 'decimal', ['precision' => 18, 'scale' => 2, 'null' => true])
        ->addColumn('currency', 'string', ['limit' => 8, 'null' => true])
        ->addColumn('contractor_name', 'string', ['limit' => 255, 'null' => true])
        ->addColumn('contractor_nip', 'string', ['limit' => 32, 'null' => true])
        ->addColumn('snapshot', 'text', ['null' => true, 'comment' => 'Pełny JSON faktury z pozycjami, do ewentualnego odtworzenia'])
        ->addColumn('created', 'datetime', ['null' => false])
        ->addIndex(['invoice_id'])
        ->addIndex(['company_id'])
        ->addIndex(['deleted_by_user_id'])
        ->addIndex(['created'])
        ->create();
    }
}

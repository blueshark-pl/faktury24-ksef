<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Log synchronizacji faktur z zewnętrznego systemu legacy.
 * Przechowuje historię synchronizacji i zmiany paymentstate.
 */
class CreateLegacySyncLogs extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('legacy_sync_logs', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('rejestr', 'integer', ['null' => false])
            ->addColumn('rok', 'integer', ['null' => false])
            ->addColumn('mc', 'integer', ['null' => true, 'default' => null, 'comment' => 'null = cały rok'])
            ->addColumn('synced_by_user_id', 'uuid', ['null' => true, 'default' => null])
            ->addColumn('synced_by_name', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('status', 'string', ['limit' => 20, 'null' => false, 'default' => 'success', 'comment' => 'success/error'])
            ->addColumn('records_fetched', 'integer', ['null' => false, 'default' => 0, 'comment' => 'Liczba rekordów z API'])
            ->addColumn('records_upserted', 'integer', ['null' => false, 'default' => 0, 'comment' => 'Liczba zapisanych (nowych + zaktualizowanych)'])
            ->addColumn('records_changed', 'integer', ['null' => false, 'default' => 0, 'comment' => 'Liczba wierszy ze zmianą paymentstate'])
            ->addColumn('changes_detail', 'text', ['null' => true, 'default' => null, 'comment' => 'JSON — lista zmian paymentstate'])
            ->addColumn('error_message', 'text', ['null' => true, 'default' => null])
            ->addColumn('synced_at', 'datetime', ['null' => false])
            ->addIndex(['company_id'])
            ->addIndex(['synced_at'])
            ->create();
    }
}

<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Notatki wewnetrzne per zlecenie transportowe.
 * Analog invoice_notes ale dla speed_orders. Wykorzystywane do rozmow
 * miedzy spedytorami, activity log akcji, przypomnien.
 */
class CreateSpeedOrderNotes extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('speed_order_notes')) return;
        $this->table('speed_order_notes', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('speed_order_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('user_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'Autor (NULL = system)'])
            ->addColumn('note_type', 'string', ['limit' => 20, 'null' => false, 'default' => 'note',
                'comment' => 'note | system | reminder | phone_call | email'])
            ->addColumn('body', 'text', ['null' => false])
            ->addColumn('payload_json', 'text', ['null' => true,
                'comment' => 'Metadata akcji (action, old/new values, ids)'])
            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['speed_order_id', 'created'], ['name' => 'BY_ORDER_TIME'])
            ->addIndex(['company_id'], ['name' => 'BY_COMPANY'])
            ->addIndex(['user_id'], ['name' => 'BY_USER'])
            ->create();
    }

    public function down(): void
    {
        $this->table('speed_order_notes')->drop()->save();
    }
}

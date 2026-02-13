<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateNotifications extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('notifications', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        $table
            ->addColumn('id', 'uuid')
            ->addColumn('user_id', 'uuid', ['null' => true, 'comment' => 'opcjonalnie, jeśli notyfikacja należy do usera'])
            ->addColumn('channel', 'string', ['limit' => 16, 'null' => false, 'default' => 'push']) // email|push|sms
            ->addColumn('type', 'string', ['limit' => 64, 'null' => false, 'default' => 'generic']) // np. invoice_received
            ->addColumn('severity', 'string', ['limit' => 16, 'null' => false, 'default' => 'info']) // info|success|warning|danger
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('message', 'text', ['null' => true])
            ->addColumn('action_url', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('action_label', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('is_read', 'boolean', ['default' => false])
            ->addColumn('read_at', 'datetime', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => true])

            ->addIndex(['user_id'])
            ->addIndex(['is_read'])
            ->addIndex(['channel'])
            ->addIndex(['type'])
            ->addIndex(['severity'])
            ->create();
    }
}

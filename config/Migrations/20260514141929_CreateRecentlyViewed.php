<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateRecentlyViewed extends AbstractMigration
{
    public function change(): void
    {
        $this->table('recently_viewed', [
            'id'          => false,
            'primary_key' => ['id'],
            'collation'   => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('user_id', 'char', ['limit' => 36, 'comment' => 'users.id właściciela rekordu'])
        ->addColumn('entity_type', 'string', ['limit' => 16, 'comment' => "'invoices' | 'contractors'"])
        ->addColumn('entity_id', 'char', ['limit' => 36, 'comment' => 'UUID faktury/kontrahenta'])
        ->addColumn('label', 'string', ['limit' => 160])
        ->addColumn('sub', 'string', ['limit' => 160, 'null' => true])
        ->addColumn('url', 'string', ['limit' => 255])
        ->addColumn('created', 'datetime', ['null' => false])
        ->addColumn('modified', 'datetime', ['null' => false])
        ->addIndex(['user_id', 'entity_type', 'modified'], ['name' => 'idx_rv_user_type_modified'])
        ->addIndex(['user_id', 'entity_type', 'entity_id'], ['unique' => true, 'name' => 'uniq_rv_user_type_entity'])
        ->create();
    }
}

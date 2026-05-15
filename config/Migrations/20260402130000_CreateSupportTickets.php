<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateSupportTickets extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('support_tickets', ['id' => true, 'primary_key' => 'id']);
        $table
            ->addColumn('user_id', 'string', ['limit' => 36, 'null' => false, 'comment' => 'FK do users.id'])
            ->addColumn('company_id', 'string', ['limit' => 36, 'null' => true, 'default' => null])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('category', 'string', ['limit' => 32, 'null' => false, 'default' => 'other', 'comment' => 'problem|idea|question|other'])
            ->addColumn('description', 'text', ['null' => false])
            ->addColumn('status', 'string', ['limit' => 32, 'null' => false, 'default' => 'nowe', 'comment' => 'nowe|w_toku|rozwiazane|zamkniete'])
            ->addColumn('type', 'string', ['limit' => 32, 'null' => true, 'default' => null, 'comment' => 'bug|proposal|question|other — tylko admin'])
            ->addColumn('attachment', 'string', ['limit' => 500, 'null' => true, 'default' => null, 'comment' => 'ścieżka względem webroot/uploads/support/'])
            ->addColumn('attachment_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('admin_note', 'text', ['null' => true, 'default' => null, 'comment' => 'wewnętrzna notatka admina'])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['user_id'])
            ->addIndex(['company_id'])
            ->addIndex(['status'])
            ->create();

        $replies = $this->table('support_ticket_replies', ['id' => true, 'primary_key' => 'id']);
        $replies
            ->addColumn('support_ticket_id', 'integer', ['null' => false])
            ->addColumn('user_id', 'string', ['limit' => 36, 'null' => true, 'default' => null, 'comment' => 'NULL = odpowiedź admina'])
            ->addColumn('author_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('is_admin_reply', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('message', 'text', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['support_ticket_id'])
            ->create();
    }
}

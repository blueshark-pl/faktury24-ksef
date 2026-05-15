<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateTasks extends AbstractMigration
{
    public function change(): void
    {
        // Etykiety (słownik)
        $this->table('task_labels')
            ->addColumn('name', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('color', 'string', ['limit' => 7, 'default' => '#6366f1', 'null' => false, 'comment' => 'hex color'])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->create();

        // Taski
        $this->table('tasks')
            ->addColumn('number', 'integer', ['null' => false, 'signed' => false, 'comment' => 'auto-increment ticket number T-XXX'])
            ->addColumn('support_ticket_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('kanban_column', 'string', ['limit' => 20, 'default' => 'proposal', 'null' => false,
                'comment' => 'proposal|icebox|p2|p1|emergency|testing|complete|archive'])
            ->addColumn('kanban_position', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'todo', 'null' => false,
                'comment' => 'todo|in_progress|done'])
            ->addColumn('priority', 'string', ['limit' => 10, 'default' => 'normal', 'null' => false,
                'comment' => 'low|normal|high|critical'])
            ->addColumn('assigned_to', 'uuid', ['null' => true])
            ->addColumn('created_by', 'uuid', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['number'], ['unique' => true])
            ->addIndex(['kanban_column', 'kanban_position'])
            ->addIndex(['support_ticket_id'])
            ->create();

        // Pivot: task ↔ label
        $this->table('task_label_assignments', ['id' => false, 'primary_key' => ['task_id', 'task_label_id']])
            ->addColumn('task_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('task_label_id', 'integer', ['null' => false, 'signed' => false])
            ->create();

        // Wpisy czasu
        $this->table('task_time_entries')
            ->addColumn('task_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('user_id', 'uuid', ['null' => true])
            ->addColumn('started_at', 'datetime', ['null' => false])
            ->addColumn('stopped_at', 'datetime', ['null' => true])
            ->addColumn('minutes', 'integer', ['null' => true, 'comment' => 'filled on stop'])
            ->addColumn('note', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['task_id'])
            ->create();

        // Aktualizacje widoczne dla użytkownika zgłoszenia
        $this->table('task_updates')
            ->addColumn('task_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('support_ticket_id', 'integer', ['null' => true, 'signed' => false])
            ->addColumn('message', 'string', ['limit' => 500, 'null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['task_id'])
            ->addIndex(['support_ticket_id'])
            ->create();
    }
}

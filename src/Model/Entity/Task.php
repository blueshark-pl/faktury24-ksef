<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Table\TasksTable;

class Task extends Entity
{
    protected array $_accessible = [
        'title'             => true,
        'description'       => true,
        'support_ticket_id' => true,
        'kanban_column'     => true,
        'kanban_position'   => true,
        'status'            => true,
        'priority'          => true,
        'assigned_to'       => true,
        'created_by'        => true,
        'task_labels'       => true,
    ];

    protected function _getNumberFormatted(): string
    {
        return 'T-' . str_pad((string)$this->number, 3, '0', STR_PAD_LEFT);
    }

    protected function _getStatusLabel(): string
    {
        return TasksTable::STATUSES[$this->status] ?? $this->status;
    }

    protected function _getPriorityLabel(): string
    {
        return TasksTable::PRIORITIES[$this->priority] ?? $this->priority;
    }

    protected function _getColumnLabel(): string
    {
        return TasksTable::COLUMNS[$this->kanban_column] ?? $this->kanban_column;
    }

    protected function _getStatusBadgeClass(): string
    {
        return match($this->status) {
            'todo'        => 'secondary',
            'in_progress' => 'primary',
            'done'        => 'success',
            default       => 'secondary',
        };
    }

    protected function _getPriorityBadgeClass(): string
    {
        return match($this->priority) {
            'low'      => 'success',
            'normal'   => 'info',
            'high'     => 'warning',
            'critical' => 'danger',
            default    => 'secondary',
        };
    }
}

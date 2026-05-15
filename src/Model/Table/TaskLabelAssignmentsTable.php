<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class TaskLabelAssignmentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('task_label_assignments');
        $this->setPrimaryKey(['task_id', 'task_label_id']);
    }
}

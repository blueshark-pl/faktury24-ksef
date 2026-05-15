<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Event\EventInterface;
use ArrayObject;

class TasksTable extends Table
{
    public const COLUMNS = [
        'proposal'  => 'Proposal',
        'icebox'    => 'Ice Box',
        'p2'        => 'P2',
        'p1'        => 'P1',
        'emergency' => 'Emergency',
        'testing'   => 'Testing',
        'complete'  => 'Complete',
        'archive'   => 'Archive',
    ];

    public const STATUSES = [
        'todo'        => 'Do zrobienia',
        'in_progress' => 'W trakcie',
        'done'        => 'Gotowe',
    ];

    public const PRIORITIES = [
        'low'      => 'Niski',
        'normal'   => 'Normalny',
        'high'     => 'Wysoki',
        'critical' => 'Krytyczny',
    ];

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('tasks');
        $this->addBehavior('Timestamp');

        $this->belongsToMany('TaskLabels', [
            'through'         => 'TaskLabelAssignments',
            'foreignKey'      => 'task_id',
            'targetForeignKey'=> 'task_label_id',
        ]);
        $this->hasMany('TaskTimeEntries', [
            'foreignKey' => 'task_id',
            'dependent'  => true,
        ]);
        $this->hasMany('TaskUpdates', [
            'foreignKey' => 'task_id',
            'dependent'  => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('title', 'create')->notEmptyString('title');
        $validator->maxLength('title', 255);
        $validator->allowEmptyString('description');
        $validator->inList('kanban_column', array_keys(self::COLUMNS));
        $validator->inList('status', array_keys(self::STATUSES));
        $validator->inList('priority', array_keys(self::PRIORITIES));
        return $validator;
    }

    /** Auto-assign next task number before save */
    public function beforeSave(EventInterface $event, $entity, ArrayObject $options): void
    {
        if ($entity->isNew() && empty($entity->number)) {
            $max = $this->find()->select(['max_num' => $this->find()->func()->max('number')])->first();
            $entity->number = (int)($max?->max_num ?? 0) + 1;
        }
    }

    /** Total minutes logged for a task */
    public function totalMinutes(int $taskId): int
    {
        $result = $this->TaskTimeEntries->find()
            ->where(['task_id' => $taskId, 'stopped_at IS NOT' => null])
            ->select(['total' => $this->TaskTimeEntries->find()->func()->sum('minutes')])
            ->first();
        return (int)($result?->total ?? 0);
    }
}

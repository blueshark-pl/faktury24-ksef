<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class TaskUpdatesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('task_updates');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Tasks', ['foreignKey' => 'task_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('task_id', 'create')->notEmptyString('task_id');
        $validator->requirePresence('message', 'create')->notEmptyString('message');
        return $validator;
    }
}

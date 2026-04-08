<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class TaskLabelsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('task_labels');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('name', 'create')->notEmptyString('name');
        $validator->maxLength('name', 80);
        $validator->requirePresence('color', 'create')->notEmptyString('color');
        return $validator;
    }
}

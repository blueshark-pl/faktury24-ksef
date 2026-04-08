<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class TaskTimeEntry extends Entity
{
    protected array $_accessible = [
        'task_id'    => true,
        'user_id'    => true,
        'started_at' => true,
        'stopped_at' => true,
        'minutes'    => true,
        'note'       => true,
    ];
}

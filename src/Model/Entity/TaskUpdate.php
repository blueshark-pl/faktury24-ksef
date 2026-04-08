<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class TaskUpdate extends Entity
{
    protected array $_accessible = [
        'task_id'           => true,
        'support_ticket_id' => true,
        'message'           => true,
    ];
}

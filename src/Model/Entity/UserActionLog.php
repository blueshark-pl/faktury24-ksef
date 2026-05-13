<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class UserActionLog extends Entity
{
    protected array $_accessible = [
        'user_id'     => true,
        'username'    => true,
        'role'        => true,
        'method'      => true,
        'controller'  => true,
        'action'      => true,
        'url'         => true,
        'entity_id'   => true,
        'status_code' => true,
        'ip'          => true,
        'user_agent'  => true,
        'created'     => true,
    ];
}

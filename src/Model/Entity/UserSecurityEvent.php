<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class UserSecurityEvent extends Entity
{
    protected array $_accessible = [
        'user_id'    => true,
        'username'   => true,
        'event_type' => true,
        'ip'         => true,
        'user_agent' => true,
        'details'    => true,
        'created'    => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class UserImpersonationLog extends Entity
{
    protected array $_accessible = [
        'admin_user_id'   => true,
        'admin_username'  => true,
        'target_user_id'  => true,
        'target_username' => true,
        'target_role'     => true,
        'ip'              => true,
        'user_agent'      => true,
        'started_at'      => true,
        'ended_at'        => true,
    ];
}

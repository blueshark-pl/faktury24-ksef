<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string|null $user_id
 * @property string $username
 * @property string|null $role
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string $kind
 * @property \Cake\I18n\DateTime $logged_at
 */
class UserLoginLog extends Entity
{
    protected array $_accessible = [
        'user_id'    => true,
        'username'   => true,
        'role'       => true,
        'ip'         => true,
        'user_agent' => true,
        'kind'       => true,
        'logged_at'  => true,
    ];
}

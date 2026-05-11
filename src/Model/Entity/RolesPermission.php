<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int                         $id
 * @property int                         $role_id
 * @property int                         $permission_id
 * @property \Cake\I18n\DateTime|null    $created
 */
class RolesPermission extends Entity
{
    protected array $_accessible = [
        'role_id'       => true,
        'permission_id' => true,
        'created'       => true,
    ];
}

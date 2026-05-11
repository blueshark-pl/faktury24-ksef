<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int                         $id
 * @property string                      $code
 * @property string                      $name
 * @property string|null                 $description
 * @property bool                        $is_system
 * @property bool                        $is_active
 * @property \Cake\I18n\DateTime|null    $created
 * @property \Cake\I18n\DateTime|null    $modified
 * @property \App\Model\Entity\Permission[] $permissions
 */
class Role extends Entity
{
    protected array $_accessible = [
        'code'        => true,
        'name'        => true,
        'description' => true,
        'is_system'   => true,
        'is_active'   => true,
        'permissions' => true,
        'created'     => true,
        'modified'    => true,
    ];
}

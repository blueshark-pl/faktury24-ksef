<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int                         $id
 * @property string                      $code
 * @property string                      $name
 * @property string                      $category
 * @property string|null                 $description
 * @property \Cake\I18n\DateTime|null    $created
 * @property \Cake\I18n\DateTime|null    $modified
 */
class Permission extends Entity
{
    protected array $_accessible = [
        'code'        => true,
        'name'        => true,
        'category'    => true,
        'description' => true,
        'created'     => true,
        'modified'    => true,
    ];
}

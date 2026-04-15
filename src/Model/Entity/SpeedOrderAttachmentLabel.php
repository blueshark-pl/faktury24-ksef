<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * SpeedOrderAttachmentLabel Entity
 *
 * @property int    $id
 * @property string $name
 * @property string $slug
 * @property int    $sort
 * @property \Cake\I18n\DateTime $created
 */
class SpeedOrderAttachmentLabel extends Entity
{
    protected array $_accessible = [
        'name'    => true,
        'slug'    => true,
        'sort'    => true,
        'created' => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int         $id
 * @property int         $speed_order_id
 * @property string      $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $reason
 * @property string|null $note
 * @property int|null    $user_id
 * @property string|null $username
 * @property \Cake\I18n\DateTime $created
 */
class SpeedOrderStatusLog extends Entity
{
    protected array $_accessible = [
        'speed_order_id' => true,
        'field'          => true,
        'old_value'      => true,
        'new_value'      => true,
        'reason'         => true,
        'note'           => true,
        'user_id'        => true,
        'username'       => true,
        'created'        => true,
    ];
}

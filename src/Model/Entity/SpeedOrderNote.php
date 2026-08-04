<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string      $id
 * @property string      $company_id
 * @property int         $speed_order_id
 * @property string|null $user_id
 * @property string      $note_type
 * @property string      $body
 * @property string|null $payload_json
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class SpeedOrderNote extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string      $id
 * @property string      $company_id
 * @property string      $name
 * @property string|null $description
 * @property bool        $is_favorite
 * @property string      $payload_json
 * @property int         $usage_count
 * @property \Cake\I18n\DateTime|null $last_used_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class SpeedOrderTemplate extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];
}

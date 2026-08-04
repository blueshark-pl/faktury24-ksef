<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string      $id
 * @property int         $speed_order_id
 * @property int         $stop_index
 * @property string      $stop_type
 * @property string|null $country_code
 * @property string|null $postal_code
 * @property string|null $city
 * @property float|null  $lat
 * @property float|null  $lng
 * @property string|null $address
 * @property string|null $place_name
 * @property \Cake\I18n\DateTime|null $planned_at
 * @property \Cake\I18n\DateTime|null $actual_at
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $cargo_notes
 * @property \Cake\I18n\DateTime|null $completed_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class SpeedOrderStop extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];
}

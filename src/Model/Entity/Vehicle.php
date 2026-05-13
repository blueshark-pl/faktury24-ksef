<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string|null $plate
 * @property string|null $vin
 * @property string $type
 * @property int|null $gross_weight_kg
 * @property int|null $axle_count
 * @property int|null $axle_load_kg
 * @property int|null $height_cm
 * @property int|null $width_cm
 * @property int|null $length_cm
 * @property string|null $emission_class
 * @property string|null $tunnel_category
 * @property bool $hazardous_goods
 * @property string|null $notes
 * @property bool $is_active
 * @property bool $is_default
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class Vehicle extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
        'company_id' => false,
    ];
}

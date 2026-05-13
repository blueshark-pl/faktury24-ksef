<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $company_id
 * @property string $waypoints_json
 * @property string|null $vehicle_id
 * @property float|null $distance_km
 * @property int|null $duration_min
 * @property float|null $tolls_total
 * @property string|null $tolls_currency
 * @property string $signature
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $last_used
 */
class RouteSearch extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}

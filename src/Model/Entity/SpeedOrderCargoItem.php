<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string      $id
 * @property int         $speed_order_id
 * @property int         $line_index
 * @property string|null $product_code
 * @property string|null $product_name
 * @property bool        $is_dry
 * @property bool        $is_wrapped
 * @property bool        $is_strapped
 * @property bool        $is_sort_only
 * @property int|null    $stack_height
 * @property int|null    $qty_advised
 * @property int|null    $qty_real
 * @property float|null  $weight_kg
 * @property string|null $unit
 * @property string|null $notes
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class SpeedOrderCargoItem extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];
}

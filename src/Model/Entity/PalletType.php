<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string      $id
 * @property string|null $company_id
 * @property string      $code
 * @property string      $name
 * @property string|null $manufacturer
 * @property int|null    $length_mm
 * @property int|null    $width_mm
 * @property int|null    $height_mm
 * @property float|null  $weight_empty_kg
 * @property int|null    $load_capacity_kg
 * @property string|null $material
 * @property string|null $color
 * @property bool        $is_pooling
 * @property string|null $description
 * @property string|null $image_path
 * @property string|null $external_url
 * @property bool        $is_active
 * @property int         $sort_order
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class PalletType extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];

    /**
     * Dimensions display: 1200x800x144 mm
     */
    public function _getDimensionsLabel(): string
    {
        if (!$this->length_mm || !$this->width_mm) return '';
        $parts = [$this->length_mm, $this->width_mm];
        if ($this->height_mm) $parts[] = $this->height_mm;
        return implode('x', $parts) . ' mm';
    }
}

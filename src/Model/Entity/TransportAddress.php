<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string                    $id
 * @property string                    $name
 * @property string|null               $address
 * @property string|null               $city
 * @property string|null               $postal_code
 * @property string                    $country
 * @property string                    $address_type  loading|unloading|both
 * @property int                       $times_used
 * @property bool                      $is_active
 * @property \Cake\I18n\DateTime|null  $created
 * @property \Cake\I18n\DateTime|null  $modified
 */
class TransportAddress extends Entity
{
    protected array $_accessible = [
        'name'         => true,
        'address'      => true,
        'city'         => true,
        'postal_code'  => true,
        'country'      => true,
        'address_type' => true,
        'times_used'   => true,
        'is_active'    => true,
        'created'      => true,
        'modified'     => true,
    ];

    /**
     * Pełna etykieta do autocomplete / listy: 'Nazwa — 00-000 Miasto, PL'.
     */
    protected function _getFullLabel(): string
    {
        $parts = [$this->name];
        $loc = trim(((string)($this->postal_code ?? '')) . ' ' . ((string)($this->city ?? '')));
        if ($loc !== '') {
            $parts[] = $loc;
        }
        if ($this->country) {
            $parts[count($parts) - 1] .= ', ' . $this->country;
        }
        return implode(' — ', $parts);
    }

    protected array $_virtual = ['full_label'];
}

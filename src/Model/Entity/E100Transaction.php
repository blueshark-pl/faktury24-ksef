<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $company_id
 * @property string $e100_account_id
 * @property string $un_id
 * @property string|null $card
 * @property string|null $card_shortname
 * @property string|null $auto
 * @property \Cake\I18n\DateTime|null $date
 * @property \Cake\I18n\DateTime|null $datetime_insert
 * @property string|null $station_id
 * @property string|null $address
 * @property string|null $brand
 * @property int|null $service_id
 * @property string|null $service_name
 * @property float|null $volume
 * @property float|null $price
 * @property string|null $currency
 * @property float|null $sum
 * @property float|null $discount
 * @property float|null $discount_percentage
 * @property float|null $amount_without_discount
 * @property float|null $excise
 * @property string|null $ticket
 * @property bool $confirmed
 * @property bool $exposed
 * @property string|null $invoice_ref
 * @property \Cake\I18n\Date|null $invoice_date
 * @property string|null $driver
 * @property string|null $card_driver
 * @property int|null $category
 * @property string|null $row_version
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\E100Account $e100_account
 */
class E100Transaction extends Entity
{
    protected array $_accessible = [
        'company_id'             => true,
        'e100_account_id'        => true,
        'un_id'                  => true,
        'card'                   => true,
        'card_shortname'         => true,
        'auto'                   => true,
        'date'                   => true,
        'datetime_insert'        => true,
        'station_id'             => true,
        'address'                => true,
        'brand'                  => true,
        'service_id'             => true,
        'service_name'           => true,
        'volume'                 => true,
        'price'                  => true,
        'currency'               => true,
        'sum'                    => true,
        'discount'               => true,
        'discount_percentage'    => true,
        'amount_without_discount'=> true,
        'excise'                 => true,
        'ticket'                 => true,
        'confirmed'              => true,
        'exposed'                => true,
        'invoice_ref'            => true,
        'invoice_date'           => true,
        'driver'                 => true,
        'card_driver'            => true,
        'category'               => true,
        'row_version'            => true,
    ];
}

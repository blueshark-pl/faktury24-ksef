<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Rekord zlecenia zaimportowanego z systemu Speed ERP.
 *
 * @property int         $id
 * @property int         $speed_id
 * @property string|null $company_nip
 * @property string|null $company_name
 * @property string|null $symbol
 * @property string|null $ozn
 * @property int|null    $numer
 * @property string|null $rok
 * @property string|null $mc
 * @property string|null $teczka
 * @property int         $status
 * @property int|null    $buyer_speed_id
 * @property string|null $buyer_nip
 * @property string|null $buyer_name
 * @property string|null $buyer_street
 * @property string|null $buyer_postal_code
 * @property string|null $buyer_city
 * @property string|null $buyer_country
 * @property string|null $buyer_email
 * @property string|null $place_from_name
 * @property string|null $place_from_country
 * @property string|null $load_country
 * @property string|null $load_postal_code
 * @property string|null $load_city
 * @property string|null $place_to_name
 * @property string|null $place_to_country
 * @property string|null $unload_name
 * @property string|null $unload_city
 * @property string|null $unload_country
 * @property string|null $route_description
 * @property string|null $title1
 * @property string|null $title2
 * @property string|null $cargo_type
 * @property string|null $driver
 * @property string|null $carrier
 * @property string|null $transport_type
 * @property string|null $vehicle_reg
 * @property string|null $notes
 * @property \Cake\I18n\Date|null     $date_doc
 * @property \Cake\I18n\DateTime|null $date_ship
 * @property \Cake\I18n\DateTime|null $date_deadline
 * @property \Cake\I18n\DateTime|null $date_delivery
 * @property string      $currency
 * @property float       $netto
 * @property float       $vat
 * @property float       $brutto
 * @property float|null  $exchange_rate
 * @property string|null $payment_terms
 * @property string|null $our_ref
 * @property string|null $exchange_table
 * @property string|null $nick_created
 * @property string|null $nick_modified
 * @property string|null $raw_json
 * @property int         $nordlogis_status
 * @property string|null $invoice_id
 * @property \Cake\I18n\DateTime|null $invoiced_at
 * @property \Cake\I18n\DateTime|null $pol_at
 * @property \Cake\I18n\DateTime|null $pod_at
 * @property \Cake\I18n\DateTime|null $fk_at
 * @property \Cake\I18n\DateTime|null $fs_at
 * @property bool        $is_complete
 * @property \Cake\I18n\DateTime|null $imported_at
 * @property \Cake\I18n\DateTime|null $speed_modified_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class SpeedOrder extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];
}

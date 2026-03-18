<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceAuthorizedEntity — PodmiotUpowazniony.
 *
 * @property string $id
 * @property string $invoice_id
 * @property string|null $nr_eori
 * @property string|null $nip
 * @property string|null $name
 * @property string|null $country_code
 * @property string|null $address_l1
 * @property string|null $address_l2
 * @property string|null $gln
 * @property string|null $koresp_country_code
 * @property string|null $koresp_address_l1
 * @property string|null $koresp_address_l2
 * @property string|null $email
 * @property string|null $phone
 * @property int $rola
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoiceAuthorizedEntity extends Entity
{
    protected array $_accessible = [
        'invoice_id' => true,
        'nr_eori' => true,
        'nip' => true,
        'name' => true,
        'country_code' => true,
        'address_l1' => true,
        'address_l2' => true,
        'gln' => true,
        'koresp_country_code' => true,
        'koresp_address_l1' => true,
        'koresp_address_l2' => true,
        'email' => true,
        'phone' => true,
        'rola' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceCompanyDetail Entity
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $name
 * @property string|null $nip
 * @property string|null $street
 * @property string|null $city
 * @property string|null $zip
 * @property string $country
 * @property string|null $bank_account
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $krs
 * @property string|null $regon
 * @property string|null $bdo
 * @property string|null $bank_name
 * @property string|null $bank_desc
 * @property string|null $swift
 * @property string|null $bank_correspondent
 * @property string|null $registers_json
 * @property string|null $gln
 * @property string|null $country_code
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoiceCompanyDetail extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'invoice_id' => true,
        'name' => true,
        'nip' => true,
        'street' => true,
        'city' => true,
        'zip' => true,
        'country' => true,
        'bank_account' => true,
        'email' => true,
        'phone' => true,
        'krs' => true,
        'regon' => true,
        'bdo' => true,
        'bank_name' => true,
        'bank_desc' => true,
        'swift' => true,
        'bank_correspondent' => true,
        'registers_json' => true,
        'gln' => true,
        'country_code' => true,
        'koresp_country_code' => true,
        'koresp_address_l1' => true,
        'koresp_address_l2' => true,
        'koresp_gln' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

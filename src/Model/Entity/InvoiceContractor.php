<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceContractor Entity
 *
 * @property string $id
 * @property string $invoice_id
 * @property string $name
 * @property string|null $nip
 * @property string|null $street
 * @property string|null $city
 * @property string|null $zip
 * @property string $country
 * @property string|null $account_number
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $gln
 * @property string|null $nr_klienta
 * @property string|null $vat_prefix
 * @property string|null $vat_eu
 * @property string|null $eori
 * @property string|null $tax_id_other
 * @property string|null $tax_id_other_country
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoiceContractor extends Entity
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
        'account_number' => true,
        'email' => true,
        'phone' => true,
        'gln' => true,
        'nr_klienta' => true,
        'vat_prefix' => true,
        'vat_eu' => true,
        'eori' => true,
        'tax_id_other' => true,
        'tax_id_other_country' => true,
        'koresp_country_code' => true,
        'koresp_address_l1' => true,
        'koresp_address_l2' => true,
        'koresp_gln' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

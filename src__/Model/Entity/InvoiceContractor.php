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
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceRecipient Entity — per-invoice snapshot of the recipient (Odbiorca).
 *
 * @property string $id
 * @property string $invoice_id
 * @property string|null $name
 * @property string|null $nip
 * @property string|null $street
 * @property string|null $city
 * @property string|null $zip
 * @property string $country
 * @property string|null $email
 * @property string|null $phone
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoiceRecipient extends Entity
{
    protected array $_accessible = [
        'invoice_id' => true,
        'name' => true,
        'nip' => true,
        'street' => true,
        'city' => true,
        'zip' => true,
        'country' => true,
        'email' => true,
        'phone' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

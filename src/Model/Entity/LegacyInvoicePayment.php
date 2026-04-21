<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $legacy_invoice_id
 * @property string $company_id
 * @property \Cake\I18n\Date|string $payment_date
 * @property float $amount
 * @property string $payment_method
 * @property string|null $description
 * @property \Cake\I18n\DateTime $created
 */
class LegacyInvoicePayment extends Entity
{
    protected array $_accessible = [
        'legacy_invoice_id' => true,
        'company_id'        => true,
        'payment_date'      => true,
        'amount'            => true,
        'payment_method'    => true,
        'description'       => true,
        'created'           => true,
    ];
}

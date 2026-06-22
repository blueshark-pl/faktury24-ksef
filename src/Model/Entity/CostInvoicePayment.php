<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Wpłata przypisana do faktury kosztowej.
 *
 * @property string $id
 * @property int    $cost_invoice_id
 * @property \Cake\I18n\Date $payment_date
 * @property float  $amount
 * @property string $currency
 * @property string|null $payment_method
 * @property string $payment_type
 * @property string|null $bank_transaction_id
 * @property string|null $user_id
 * @property string|null $note
 */
class CostInvoicePayment extends Entity
{
    protected array $_accessible = [
        '*'  => true,
        'id' => false,
    ];
}

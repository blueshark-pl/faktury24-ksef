<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property string $id
 * @property string $company_id
 * @property string $bank_transaction_id
 * @property string|null $invoice_id
 * @property string|null $legacy_invoice_id
 * @property string|null $invoice_payment_id
 * @property float $allocated_amount
 * @property string $currency
 * @property string $allocation_type   gross|net|vat
 * @property string|null $note
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\BankTransaction $bank_transaction
 * @property \App\Model\Entity\Invoice|null $invoice
 * @property \App\Model\Entity\LegacyInvoice|null $legacy_invoice
 * @property \App\Model\Entity\InvoicePayment|null $invoice_payment
 */
class BankTransactionAllocation extends Entity
{
    protected array $_accessible = [
        'company_id'          => true,
        'bank_transaction_id' => true,
        'invoice_id'          => true,
        'legacy_invoice_id'   => true,
        'invoice_payment_id'  => true,
        'allocated_amount'    => true,
        'currency'            => true,
        'allocation_type'     => true,
        'note'                => true,
    ];
}

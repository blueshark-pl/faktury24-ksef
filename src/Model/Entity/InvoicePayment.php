<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoicePayment Entity
 *
 * @property string $id
 * @property string $invoice_id
 * @property \Cake\I18n\Date $payment_date
 * @property string $amount
 * @property string $payment_method
 * @property string|null $description
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoicePayment extends Entity
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
        'bank_transaction_allocation_id' => true,
        'payment_type' => true,
        'currency' => true,
        'payment_date' => true,
        'amount' => true,
        'payment_method' => true,
        'description' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

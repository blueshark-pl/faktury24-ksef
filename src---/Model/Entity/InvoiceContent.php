<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceContent Entity
 *
 * @property string $id
 * @property string $invoice_id
 * @property string|null $vat_code_id
 * @property string $name
 * @property string|null $product_desc
 * @property string $quantity
 * @property string|null $unit
 * @property string $price
 * @property string|null $purchase_price
 * @property string $discount_percent
 * @property string $netto
 * @property string $brutto
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoiceContent extends Entity
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
        'vat_code_id' => true,
        'name' => true,
        'product_desc' => true,
        'quantity' => true,
        'unit' => true,
        'gtu_code' => true,
        'price' => true,
        'purchase_price' => true,
        'discount_percent' => true,
        'netto' => true,
        'brutto' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

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
 * @property string|null $discount_amount
 * @property string $netto
 * @property string $brutto
 * @property string|null $gtin
 * @property string|null $cn_code
 * @property string|null $pkob
 * @property bool $is_attachment15
 * @property string|null $excise_amount
 * @property string|null $procedure_marking
 * @property string|null $gtu_code
 * @property string|null $uu_id
 * @property string|null $vat_amount
 * @property \Cake\I18n\Date|null $line_date
 * @property string|null $pkwiu
 * @property string|null $gross_unit_price
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
        'discount_amount' => true,
        'netto' => true,
        'brutto' => true,
        'gtin' => true,
        'cn_code' => true,
        'pkob' => true,
        'is_attachment15' => true,
        'excise_amount' => true,
        'procedure_marking' => true,
        'uu_id' => true,
        'vat_amount' => true,
        'line_date' => true,
        'pkwiu' => true,
        'gross_unit_price' => true,
        'kurs_waluty' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

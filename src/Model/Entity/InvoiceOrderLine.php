<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InvoiceOrderLine Entity — Zamówienie → ZamowienieWiersz.
 *
 * For advance invoices (ZAL). FA(3) KSeF structure.
 *
 * @property string $id
 * @property string $invoice_id
 * @property int $nr_wiersza
 * @property string|null $uu_id
 * @property string|null $name
 * @property string|null $indeks
 * @property string|null $gtin
 * @property string|null $pkwiu
 * @property string|null $cn_code
 * @property string|null $pkob
 * @property string|null $unit
 * @property string|null $quantity
 * @property string|null $price
 * @property string|null $netto
 * @property string|null $vat_amount
 * @property string|null $vat_rate
 * @property string|null $vat_rate_xii
 * @property bool $is_attachment15
 * @property string|null $gtu_code
 * @property string|null $procedure_marking
 * @property string|null $excise_amount
 * @property bool $is_before_correction
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Invoice $invoice
 */
class InvoiceOrderLine extends Entity
{
    protected array $_accessible = [
        'invoice_id' => true,
        'nr_wiersza' => true,
        'uu_id' => true,
        'name' => true,
        'indeks' => true,
        'gtin' => true,
        'pkwiu' => true,
        'cn_code' => true,
        'pkob' => true,
        'unit' => true,
        'quantity' => true,
        'price' => true,
        'netto' => true,
        'vat_amount' => true,
        'vat_rate' => true,
        'vat_rate_xii' => true,
        'is_attachment15' => true,
        'gtu_code' => true,
        'procedure_marking' => true,
        'excise_amount' => true,
        'is_before_correction' => true,
        'created' => true,
        'modified' => true,
        'invoice' => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Product Entity
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_service
 * @property int $unit_id
 * @property string $vat_id
 * @property string $net_price
 * @property string $currency
 * @property string|null $pkwiu
 * @property string|null $gtu_code
 * @property string|null $barcode
 * @property string|null $gtin
 * @property string|null $cn_code
 * @property string|null $pkob
 * @property string|null $excise_amount
 * @property string|null $procedure_marking
 * @property bool $is_attachment15
 * @property bool $is_active
 * @property bool $deleted
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Company $company
 * @property \App\Model\Entity\Unit $unit
 * @property \App\Model\Entity\Vat $vat
 */
class Product extends Entity
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
        'company_id' => true,
        'code' => true,
        'name' => true,
        'description' => true,
        'is_service' => true,
        'unit_id' => true,
        'vat_id' => true,
        'net_price' => true,
        'currency' => true,
        'pkwiu' => true,
        'gtu_code' => true,
        'barcode' => true,
        'gtin' => true,
        'cn_code' => true,
        'pkob' => true,
        'excise_amount' => true,
        'procedure_marking' => true,
        'is_attachment15' => true,
        'is_active' => true,
        'deleted' => true,
        'created' => true,
        'modified' => true,
        'company' => true,
        'unit' => true,
        'vat' => true,
    ];
}

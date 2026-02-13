<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * CompanyBankAccount Entity
 *
 * @property string $id
 * @property string $company_id
 * @property string $iban
 * @property string|null $bank_name
 * @property string $currency
 * @property bool $is_default
 * @property string|null $label
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Company $company
 */
class CompanyBankAccount extends Entity
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
        'iban' => true,
        'bank_name' => true,
        'currency' => true,
        'is_default' => true,
        'label' => true,
        'created' => true,
        'modified' => true,
        'company' => true,
    ];
}

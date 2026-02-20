<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Company Entity
 *
 * @property string $id
 * @property string $name
 * @property string|null $altname
 * @property string|null $nip
 * @property string|null $regon
 * @property string|null $country
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $street
 * @property string|null $local_number
 * @property string|null $phone
 * @property string|null $bank_name
 * @property string|null $bank_account
 * @property string|null $logo_url
 * @property string|null $issuer
 * @property bool $vat_payer
 * @property bool $ksef_mode_enabled
 * @property \Cake\I18n\Date|null $register_date
 * @property \Cake\I18n\Date|null $subscription_end
 * @property bool $is_active
 * @property string|null $invoice_template
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\CompanyBankAccount[] $company_bank_accounts
 * @property \App\Model\Entity\Contractor[] $contractors
 * @property \App\Model\Entity\InvoiceSeries[] $invoice_series
 * @property \App\Model\Entity\Invoice[] $invoices
 * @property \App\Model\Entity\Service[] $services
 * @property \CakeDC\Users\Model\Entity\User[] $users
 */
class Company extends Entity
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
        'name' => true,
        'altname' => true,
        'nip' => true,
        'regon' => true,
        'country' => true,
        'postal_code' => true,
        'city' => true,
        'street' => true,
        'local_number' => true,
        'phone' => true,
        'bank_name' => true,
        'bank_account' => true,
        'logo_url' => true,
        'issuer' => true,
        'vat_payer' => true,
        'ksef_mode_enabled' => true,
        'register_date' => true,
        'subscription_end' => true,
        'is_active' => true,
        'invoice_template' => true,
        'created' => true,
        'modified' => true,
        'company_bank_accounts' => true,
        'contractors' => true,
        'invoice_series' => true,
        'invoices' => true,
        'services' => true,
        'users' => true,
    ];
}

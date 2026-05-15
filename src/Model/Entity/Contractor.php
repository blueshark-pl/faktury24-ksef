<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Contractor Entity
 *
 * @property string $id
 * @property string $company_id
 * @property string $name
 * @property string|null $altname
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $nip
 * @property string|null $pesel
 * @property string|null $regon
 * @property bool $eu_vat
 * @property string|null $vat_prefix
 * @property string|null $vat_eu
 * @property string|null $eori
 * @property string|null $tax_id_other
 * @property string|null $tax_id_other_country
 * @property string|null $country
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $street
 * @property string|null $correspondence_street
 * @property string|null $correspondence_postal_code
 * @property string|null $correspondence_city
 * @property string|null $correspondence_country
 * @property string|null $local_number
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $notes
 * @property bool|null $privacy_consent
 * @property string|null $privacy_basis
 * @property bool $is_active
 * @property bool $is_person
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Company $company
 * @property \App\Model\Entity\ContractorBankAccount[] $contractor_bank_accounts
 */
class Contractor extends Entity
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
        'name' => true,
        'altname' => true,
        'first_name' => true,
        'last_name' => true,
        'nip' => true,
        'pesel' => true,
        'regon' => true,
        'eu_vat' => true,
        'vat_prefix' => true,
        'vat_eu' => true,
        'eori' => true,
        'tax_id_other' => true,
        'tax_id_other_country' => true,
        'country' => true,
        'postal_code' => true,
        'city' => true,
        'street' => true,
        'correspondence_street' => true,
        'correspondence_postal_code' => true,
        'correspondence_city' => true,
        'correspondence_country' => true,
        'local_number' => true,
        'phone' => true,
        'email' => true,
        'notes' => true,
        'privacy_consent' => true,
        'privacy_basis' => true,
        'is_active' => true,
        'is_person' => true,
        'created' => true,
        'modified' => true,
        'company' => true,
        'contractor_bank_accounts' => true,
    ];
}

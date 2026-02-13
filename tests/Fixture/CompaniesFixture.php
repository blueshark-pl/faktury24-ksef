<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * CompaniesFixture
 */
class CompaniesFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => '231ea2d8-2594-4737-86d3-53985abf949e',
                'name' => 'Lorem ipsum dolor sit amet',
                'altname' => 'Lorem ipsum dolor sit amet',
                'nip' => 'Lorem ip',
                'regon' => 'Lorem ipsum ',
                'country' => 'Lo',
                'postal_code' => 'Lore',
                'city' => 'Lorem ipsum dolor sit amet',
                'street' => 'Lorem ipsum dolor sit amet',
                'local_number' => 'Lorem ipsum dolor sit amet',
                'phone' => 'Lorem ipsum dolor sit amet',
                'bank_name' => 'Lorem ipsum dolor sit amet',
                'bank_account' => 'Lorem ipsum dolor sit amet',
                'logo_url' => 'Lorem ipsum dolor sit amet',
                'issuer' => 'Lorem ipsum dolor sit amet',
                'vat_payer' => 1,
                'register_date' => '2025-10-02',
                'subscription_end' => '2025-10-02',
                'is_active' => 1,
                'invoice_template' => 'Lorem ipsum dolor sit amet',
                'created' => '2025-10-02 11:34:44',
                'modified' => '2025-10-02 11:34:44',
            ],
        ];
        parent::init();
    }
}

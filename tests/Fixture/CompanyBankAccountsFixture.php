<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * CompanyBankAccountsFixture
 */
class CompanyBankAccountsFixture extends TestFixture
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
                'id' => 'a1f82b21-8803-42d9-a5ef-bd828ddf36cf',
                'company_id' => '03d829a5-9ea2-4986-828c-921300beb821',
                'iban' => 'Lorem ipsum dolor sit amet',
                'bank_name' => 'Lorem ipsum dolor sit amet',
                'currency' => 'L',
                'is_default' => 1,
                'label' => 'Lorem ipsum dolor sit amet',
                'created' => '2025-10-02 11:35:17',
                'modified' => '2025-10-02 11:35:17',
            ],
        ];
        parent::init();
    }
}

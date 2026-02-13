<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ContractorBankAccountsFixture
 */
class ContractorBankAccountsFixture extends TestFixture
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
                'id' => 'ed13364d-f3aa-4c0b-88bd-23e9da062184',
                'contractor_id' => '0c2a0ec7-5055-401b-a537-0b5b719df4c7',
                'iban' => 'Lorem ipsum dolor sit amet',
                'bank_name' => 'Lorem ipsum dolor sit amet',
                'currency' => 'L',
                'is_default' => 1,
                'label' => 'Lorem ipsum dolor sit amet',
                'created' => '2025-10-02 11:35:32',
                'modified' => '2025-10-02 11:35:32',
            ],
        ];
        parent::init();
    }
}

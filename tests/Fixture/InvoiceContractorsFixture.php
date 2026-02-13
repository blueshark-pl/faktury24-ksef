<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * InvoiceContractorsFixture
 */
class InvoiceContractorsFixture extends TestFixture
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
                'id' => 'ed313968-57b7-4803-81a1-ad9991fcbac8',
                'invoice_id' => '6725c05c-434a-4b5b-8d03-3c69a5d98697',
                'name' => 'Lorem ipsum dolor sit amet',
                'nip' => 'Lorem ipsum dolor sit amet',
                'street' => 'Lorem ipsum dolor sit amet',
                'city' => 'Lorem ipsum dolor sit amet',
                'zip' => 'Lorem ipsum do',
                'country' => 'Lorem ipsum dolor sit amet',
                'account_number' => 'Lorem ipsum dolor sit amet',
                'created' => 1759413390,
                'modified' => 1759413390,
            ],
        ];
        parent::init();
    }
}

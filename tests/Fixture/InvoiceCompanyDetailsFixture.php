<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * InvoiceCompanyDetailsFixture
 */
class InvoiceCompanyDetailsFixture extends TestFixture
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
                'id' => 'c59c5383-7256-4c31-8929-e4fa727c9d7c',
                'invoice_id' => '1de7421d-c7e0-4496-b030-406a53cbf543',
                'name' => 'Lorem ipsum dolor sit amet',
                'nip' => 'Lorem ipsum dolor sit amet',
                'street' => 'Lorem ipsum dolor sit amet',
                'city' => 'Lorem ipsum dolor sit amet',
                'zip' => 'Lorem ipsum do',
                'country' => 'Lorem ipsum dolor sit amet',
                'bank_account' => 'Lorem ipsum dolor sit amet',
                'created' => 1759414110,
                'modified' => 1759414110,
            ],
        ];
        parent::init();
    }
}

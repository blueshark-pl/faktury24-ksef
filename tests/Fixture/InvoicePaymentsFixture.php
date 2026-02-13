<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * InvoicePaymentsFixture
 */
class InvoicePaymentsFixture extends TestFixture
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
                'id' => '336fa71f-ab3f-4364-acf0-0e8d106bd054',
                'invoice_id' => '754619ed-8090-4d4e-8860-5a6bd454f7ad',
                'payment_date' => '2025-10-28',
                'amount' => 1.5,
                'payment_method' => 'Lorem ipsum dolor sit amet',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'created' => '2025-10-28 07:59:23',
                'modified' => '2025-10-28 07:59:23',
            ],
        ];
        parent::init();
    }
}

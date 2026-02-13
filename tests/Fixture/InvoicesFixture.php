<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * InvoicesFixture
 */
class InvoicesFixture extends TestFixture
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
                'id' => '96d32418-70e0-4615-9401-0505d2242a98',
                'hash' => 'Lorem ipsum dolor sit amet',
                'company_id' => 'db36cc19-aa6d-4832-b984-62f50f260c4f',
                'parent_id' => 'd3c08be7-7509-4f5c-89ff-90d9f89cd8fe',
                'type' => 'Lorem ipsum dolor sit ',
                'correction_type' => 'Lorem ipsum dolor sit ',
                'simplified_invoice' => 1,
                'paymentmethod' => 'Lorem ipsum dolor sit amet',
                'paymentdate' => '2025-10-02',
                'paymentstate' => 'Lorem ipsum do',
                'date' => '2025-10-02',
                'total' => 1.5,
                'netto' => 1.5,
                'tax' => 1.5,
                'alreadypaid' => 1.5,
                'remaining' => 1.5,
                'fullnumber' => 'Lorem ipsum dolor sit amet',
                'currency' => 'Lorem ',
                'currency_date' => '2025-10-02',
                'currency_exchange' => 1.5,
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'is_print' => 1,
                'is_sent' => 1,
                'is_api' => 1,
                'created' => 1759413073,
                'modified' => 1759413073,
            ],
        ];
        parent::init();
    }
}

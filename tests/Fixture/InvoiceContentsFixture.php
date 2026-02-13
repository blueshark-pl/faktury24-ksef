<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * InvoiceContentsFixture
 */
class InvoiceContentsFixture extends TestFixture
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
                'id' => 'a771af83-dd84-4b9e-a03f-a1e8fbfdb56e',
                'invoice_id' => '2f4d3e9b-9295-4c77-be2d-1c0ad04fa31e',
                'vat_code_id' => '7ae436c2-af75-467d-9b4c-7a16afed4ae1',
                'name' => 'Lorem ipsum dolor sit amet',
                'product_desc' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'quantity' => 1.5,
                'unit' => 'Lorem ipsum do',
                'price' => 1.5,
                'discount_percent' => 1.5,
                'netto' => 1.5,
                'brutto' => 1.5,
                'created' => 1759414131,
                'modified' => 1759414131,
            ],
        ];
        parent::init();
    }
}

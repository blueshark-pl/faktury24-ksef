<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ProductsFixture
 */
class ProductsFixture extends TestFixture
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
                'id' => '6453a486-d01e-4e29-86ff-90c361ba5b5e',
                'company_id' => 'fcd31477-95b8-43e0-ba9f-ccdbb96c1b90',
                'code' => 'Lorem ipsum dolor sit amet',
                'name' => 'Lorem ipsum dolor sit amet',
                'description' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'is_service' => 1,
                'unit_id' => 1,
                'vat_id' => 'bd6c854a-2d61-47ff-b7b0-52b4dc9be70e',
                'net_price' => 1.5,
                'currency' => '',
                'pkwiu' => 'Lorem ipsum dolor sit amet',
                'gtu_code' => 'Lorem ipsum do',
                'barcode' => 'Lorem ipsum dolor sit amet',
                'is_active' => 1,
                'deleted' => 1,
                'created' => '2025-10-22 09:57:47',
                'modified' => '2025-10-22 09:57:47',
            ],
        ];
        parent::init();
    }
}

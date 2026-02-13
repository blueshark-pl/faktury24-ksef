<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ContractorsFixture
 */
class ContractorsFixture extends TestFixture
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
                'id' => '8de1550a-1f95-480e-aed5-a914d5f041c5',
                'company_id' => '4669f907-789f-4f81-9e62-0b10710b222d',
                'name' => 'Lorem ipsum dolor sit amet',
                'altname' => 'Lorem ipsum dolor sit amet',
                'nip' => 'Lorem ipsum dolor ',
                'regon' => 'Lorem ipsum ',
                'eu_vat' => 1,
                'country' => 'Lo',
                'postal_code' => 'Lorem ipsum do',
                'city' => 'Lorem ipsum dolor sit amet',
                'street' => 'Lorem ipsum dolor sit amet',
                'local_number' => 'Lorem ipsum dolor sit amet',
                'phone' => 'Lorem ipsum dolor sit amet',
                'email' => 'Lorem ipsum dolor sit amet',
                'notes' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'is_active' => 1,
                'created' => '2025-10-02 11:35:24',
                'modified' => '2025-10-02 11:35:24',
            ],
        ];
        parent::init();
    }
}

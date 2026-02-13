<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * VatsFixture
 */
class VatsFixture extends TestFixture
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
                'id' => 'aee0c48e-7bee-4bdc-8c56-150cbf5a4485',
                'name' => 'Lorem ipsum dolor sit amet',
                'rate' => 1.5,
                'deleted' => 1,
                'created' => 1759416140,
                'modified' => 1759416140,
            ],
        ];
        parent::init();
    }
}

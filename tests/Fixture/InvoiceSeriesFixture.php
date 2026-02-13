<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * InvoiceSeriesFixture
 */
class InvoiceSeriesFixture extends TestFixture
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
                'id' => '7a49833c-bb87-4db7-8cc8-01ee22d49107',
                'company_id' => '629b36d7-ad0e-4657-85ac-db95312e34c2',
                'invoice_series_type_id' => 'f3c80439-f74c-409a-8e87-6ea33e29fa08',
                'invoice_series_period_id' => '6a49f60e-c209-4af6-83fd-35251f56326a',
                'is_default' => 1,
                'name' => 'Lorem ipsum dolor sit amet',
                'series_template' => 'Lorem ipsum dolor sit amet',
                'starting_number' => 1,
                'created' => 1759477945,
                'modified' => 1759477945,
            ],
        ];
        parent::init();
    }
}

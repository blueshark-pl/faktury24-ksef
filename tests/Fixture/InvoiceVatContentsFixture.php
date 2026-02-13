<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * InvoiceVatContentsFixture
 */
class InvoiceVatContentsFixture extends TestFixture
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
                'id' => 'f50c2324-ccfe-4f14-aefe-b9b9b7c5cb33',
                'invoice_id' => '3be19ab7-755f-41ed-a16d-3dc78b8e0188',
                'vat_code_id' => 'c8d46cd6-c91c-4732-ba2e-2cc635bab69f',
                'netto' => 1.5,
                'tax' => 1.5,
                'brutto' => 1.5,
                'created' => 1759414146,
                'modified' => 1759414146,
            ],
        ];
        parent::init();
    }
}

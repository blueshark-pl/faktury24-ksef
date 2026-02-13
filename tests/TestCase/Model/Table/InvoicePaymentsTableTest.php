<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\InvoicePaymentsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\InvoicePaymentsTable Test Case
 */
class InvoicePaymentsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\InvoicePaymentsTable
     */
    protected $InvoicePayments;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.InvoicePayments',
        'app.Invoices',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('InvoicePayments') ? [] : ['className' => InvoicePaymentsTable::class];
        $this->InvoicePayments = $this->getTableLocator()->get('InvoicePayments', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->InvoicePayments);

        parent::tearDown();
    }
}

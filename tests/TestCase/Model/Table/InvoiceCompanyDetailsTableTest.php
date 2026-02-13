<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\InvoiceCompanyDetailsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\InvoiceCompanyDetailsTable Test Case
 */
class InvoiceCompanyDetailsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\InvoiceCompanyDetailsTable
     */
    protected $InvoiceCompanyDetails;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.InvoiceCompanyDetails',
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
        $config = $this->getTableLocator()->exists('InvoiceCompanyDetails') ? [] : ['className' => InvoiceCompanyDetailsTable::class];
        $this->InvoiceCompanyDetails = $this->getTableLocator()->get('InvoiceCompanyDetails', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->InvoiceCompanyDetails);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\InvoiceCompanyDetailsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\InvoiceCompanyDetailsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}

<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\CompanyBankAccountsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\CompanyBankAccountsTable Test Case
 */
class CompanyBankAccountsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\CompanyBankAccountsTable
     */
    protected $CompanyBankAccounts;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.CompanyBankAccounts',
        'app.Companies',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('CompanyBankAccounts') ? [] : ['className' => CompanyBankAccountsTable::class];
        $this->CompanyBankAccounts = $this->getTableLocator()->get('CompanyBankAccounts', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->CompanyBankAccounts);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\CompanyBankAccountsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\CompanyBankAccountsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}

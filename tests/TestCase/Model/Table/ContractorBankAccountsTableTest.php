<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ContractorBankAccountsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\ContractorBankAccountsTable Test Case
 */
class ContractorBankAccountsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\ContractorBankAccountsTable
     */
    protected $ContractorBankAccounts;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.ContractorBankAccounts',
        'app.Contractors',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('ContractorBankAccounts') ? [] : ['className' => ContractorBankAccountsTable::class];
        $this->ContractorBankAccounts = $this->getTableLocator()->get('ContractorBankAccounts', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->ContractorBankAccounts);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\ContractorBankAccountsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\ContractorBankAccountsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}

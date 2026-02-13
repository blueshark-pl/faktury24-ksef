<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\VatsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\VatsTable Test Case
 */
class VatsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\VatsTable
     */
    protected $Vats;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Vats',
        'app.Services',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Vats') ? [] : ['className' => VatsTable::class];
        $this->Vats = $this->getTableLocator()->get('Vats', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Vats);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\VatsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}

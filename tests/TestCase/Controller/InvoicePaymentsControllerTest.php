<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\InvoicePaymentsController;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\InvoicePaymentsController Test Case
 *
 * @link \App\Controller\InvoicePaymentsController
 */
class InvoicePaymentsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.InvoicePayments',
        'app.Invoices',
    ];
}

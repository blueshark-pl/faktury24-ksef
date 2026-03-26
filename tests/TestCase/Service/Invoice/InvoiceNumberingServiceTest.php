<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Invoice;

use App\Service\Invoice\InvoiceNumberingService;
use Cake\TestSuite\TestCase;

/**
 * Testy jednostkowe dla InvoiceNumberingService.
 */
class InvoiceNumberingServiceTest extends TestCase
{
    private InvoiceNumberingService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new InvoiceNumberingService();
    }

    // ── extractNumberFromFullnumber ──────────────────────────────────────────

    public function testExtractNumberSimplePrefix(): void
    {
        // PREFIX/NUMER/miesiąc/rok
        $this->assertSame(5, $this->svc->extractNumberFromFullnumber('FV/5/01/2025'));
        $this->assertSame(1, $this->svc->extractNumberFromFullnumber('EPIC/01/10/2025'));
        $this->assertSame(123, $this->svc->extractNumberFromFullnumber('FZ/123/2025'));
    }

    public function testExtractNumberFallbackFirstNumeric(): void
    {
        $this->assertSame(42, $this->svc->extractNumberFromFullnumber('42'));
        $this->assertSame(7, $this->svc->extractNumberFromFullnumber('Faktura nr 7/2025'));
    }

    public function testExtractNumberReturnsZeroForEmpty(): void
    {
        $this->assertSame(0, $this->svc->extractNumberFromFullnumber('brak-cyfr'));
        $this->assertSame(0, $this->svc->extractNumberFromFullnumber(''));
    }

    public function testExtractNumberLeadingZeros(): void
    {
        $this->assertSame(1, $this->svc->extractNumberFromFullnumber('FV/001/2025'));
        $this->assertSame(10, $this->svc->extractNumberFromFullnumber('FV/010/2025'));
    }

    // ── formatPattern ────────────────────────────────────────────────────────

    public function testFormatPatternBasicPlaceholders(): void
    {
        $result = $this->svc->formatPattern('[numer]/[rok]', 5, '2025-10-01');
        $this->assertSame('5/2025', $result);
    }

    public function testFormatPatternMonth(): void
    {
        $result = $this->svc->formatPattern('[numer]/[miesiąc]/[rok]', 3, '2025-07-15');
        $this->assertSame('3/07/2025', $result);
    }

    public function testFormatPatternLeadingZeroNumber(): void
    {
        $result = $this->svc->formatPattern('[numer:zera_wiodące=4]/[rok]', 7, '2025-01-01');
        $this->assertSame('0007/2025', $result);
    }

    public function testFormatPatternTwoDigitYear(): void
    {
        $result = $this->svc->formatPattern('[numer]/[rok:format_dwucyfrowy]', 1, '2025-06-01');
        $this->assertSame('1/25', $result);
    }

    public function testFormatPatternKwartal(): void
    {
        $result = $this->svc->formatPattern('Q[kwartał]/[rok]', 1, '2025-04-01');
        $this->assertSame('Q2/2025', $result);

        $result = $this->svc->formatPattern('Q[kwartał]/[rok]', 1, '2025-01-01');
        $this->assertSame('Q1/2025', $result);

        $result = $this->svc->formatPattern('Q[kwartał]/[rok]', 1, '2025-12-31');
        $this->assertSame('Q4/2025', $result);
    }

    public function testFormatPatternDayPlaceholder(): void
    {
        $result = $this->svc->formatPattern('[dzień]/[miesiąc]/[rok]', 1, '2025-03-05');
        $this->assertSame('05/03/2025', $result);
    }

    public function testFormatPatternFallbackNoPlaceholder(): void
    {
        // Wzorzec bez placeholdera — zwraca bez zmian
        $result = $this->svc->formatPattern('STAŁY-NR', 99, '2025-01-01');
        $this->assertSame('STAŁY-NR', $result);
    }
}

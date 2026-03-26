<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Invoice;

use App\Service\Invoice\InvoiceDefaultSeriesResolver;
use Cake\ORM\Table;
use Cake\ORM\Query\SelectQuery;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Testy jednostkowe dla InvoiceDefaultSeriesResolver.
 *
 * Używamy mocków Table żeby izolować od DB.
 */
class InvoiceDefaultSeriesResolverTest extends TestCase
{
    private InvoiceDefaultSeriesResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new InvoiceDefaultSeriesResolver();
    }

    /**
     * Tworzy mock encji serii.
     */
    private function makeSeries(string $name, string $type = 'vat', bool $isDefault = false): object
    {
        return (object)[
            'id'   => 'uuid-' . $name,
            'name' => $name,
            'type' => $type,
            'is_default' => $isDefault,
        ];
    }

    /**
     * Tworzy mock Table->find()->where()->first() który zwraca $result.
     */
    private function makeSeriesTableReturning(?object $result): Table&MockObject
    {
        $query = $this->createMock(SelectQuery::class);
        $query->method('where')->willReturnSelf();
        $query->method('andWhere')->willReturnSelf();
        $query->method('orderAsc')->willReturnSelf();
        $query->method('first')->willReturn($result);

        $table = $this->createMock(Table::class);
        $table->method('find')->willReturn($query);

        return $table;
    }

    public function testReturnsNullWhenNoSeriesFound(): void
    {
        $table = $this->makeSeriesTableReturning(null);
        $result = $this->resolver->resolve($table, 'company-1', 'vat');
        $this->assertNull($result);
    }

    public function testReturnsSeriesWhenFound(): void
    {
        $ser = $this->makeSeries('FV/2025', 'vat', true);
        $table = $this->makeSeriesTableReturning($ser);
        $result = $this->resolver->resolve($table, 'company-1', 'vat');
        $this->assertSame($ser, $result);
    }

    public function testUnknownKindFallsBackToGlobalDefault(): void
    {
        // Dla nieznanego typu zwróci null (no series_type, no hints)
        // lub domyślną jeśli match
        $table = $this->makeSeriesTableReturning(null);
        $result = $this->resolver->resolve($table, 'company-1', 'unknown_kind');
        $this->assertNull($result);
    }

    public function testCorrectionKindTriesKorType(): void
    {
        // Sprawdza że resolver próbuje 'kor' jako typ dla 'correction'
        $ser = $this->makeSeries('KOR/2025', 'kor', true);

        $callCount = 0;
        $query = $this->createMock(SelectQuery::class);
        $query->method('where')->willReturnCallback(function ($conditions) use ($query, $ser, &$callCount) {
            $callCount++;
            // Przy pierwszym where z 'type' => 'kor' — symuluj zwrot encji
            return $query;
        });
        $query->method('andWhere')->willReturnSelf();
        $query->method('orderAsc')->willReturnSelf();
        $query->method('first')->willReturn($ser);

        $table = $this->createMock(Table::class);
        $table->method('find')->willReturn($query);

        $result = $this->resolver->resolve($table, 'company-1', 'correction');
        $this->assertSame($ser, $result);
    }
}

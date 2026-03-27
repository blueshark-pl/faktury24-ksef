<?php
declare(strict_types=1);

namespace App\Service\Invoice;

use Cake\ORM\Table;

/**
 * Serwis wyboru domyślnej serii numeracyjnej dla nowej faktury.
 *
 * Wyekstrahowany z InvoicesController::handleAdd() (commit 2026-03-18).
 *
 * Strategia wyboru (dla każdego typu):
 *  1) Seria domyślna (is_default=1) z pasującym typem
 *  2) Dowolna seria z pasującym typem
 *  3) Globalna seria domyślna (is_default=1)
 *  4) Seria z pasującą nazwą (hint)
 */
class InvoiceDefaultSeriesResolver
{
    /**
     * Mapa: typ faktury → typ serii + list hinty nazw do fallbacku.
     */
    private const TYPE_CONFIG = [
        'vat' => [
            'series_type' => 'vat',
            'hints'       => ['%vat%'],
        ],
        'novat' => [
            'series_type' => 'novat',
            'hints'       => ['%no vat%', '%bez vat%', '%bezvat%', '%novat%'],
        ],
        'currency' => [
            'series_type' => 'currency',
            'hints'       => ['%walut%'],
        ],
        'correction' => [
            'series_type' => 'kor',
            'hints'       => ['%korekt%', '%koryg%', '%kor/%', '%/kor%'],
        ],
        'proforma' => [
            'series_type' => 'proforma',
            'hints'       => ['%proforma%'],
        ],
        'advance' => [
            'series_type' => 'advance',
            'hints'       => ['%zaliczka%', '%advance%'],
        ],
        'final' => [
            'series_type' => 'final',
            'hints'       => ['%konc%', '%rozlicz%'],
        ],
        'margin' => [
            'series_type' => 'margin',
            'hints'       => ['%marż%', '%marz%', '%margin%'],
        ],
        'internal' => [
            'series_type' => 'internal',
            'hints'       => ['%wewnętrz%', '%wewn%', '%internal%'],
        ],
        'internalEvidence' => [
            'series_type' => 'internalevidence',
            'hints'       => ['%dowód%', '%evidence%'],
        ],
        'oss' => [
            'series_type' => 'oss',
            'hints'       => ['%oss%'],
        ],
        'rental' => [
            'series_type' => 'rental',
            'hints'       => ['%najem%', '%rent%', '%fnp%'],
        ],
    ];

    /**
     * Zwraca najlepiej pasującą serię dla danego typu faktury lub null, jeśli nie znaleziono.
     *
     * @param Table  $seriesTable Tabela InvoiceSeries
     * @param string $companyId   UUID firmy
     * @param string $kind        Typ faktury (vat, currency, proforma, correction, advance, margin, novat, ...)
     *
     * @return object|null Encja InvoiceSeries lub null
     */
    public function resolve(Table $seriesTable, string $companyId, string $kind): ?object
    {
        $config = self::TYPE_CONFIG[$kind] ?? null;
        $seriesType = $config['series_type'] ?? null;
        $hints      = $config['hints'] ?? [];

        // 1) Domyślna + pasujący typ
        if ($seriesType !== null) {
            $ser = $seriesTable->find()
                ->where(['company_id' => $companyId, 'type' => $seriesType, 'is_default' => 1])
                ->first();
            if ($ser) {
                return $ser;
            }

            // 2) Dowolna z pasującym typem
            $ser = $seriesTable->find()
                ->where(['company_id' => $companyId, 'type' => $seriesType])
                ->orderAsc('name')
                ->first();
            if ($ser) {
                return $ser;
            }
        }

        // 3) Globalna domyślna + hint w nazwie
        foreach ($hints as $hint) {
            $ser = $seriesTable->find()
                ->where(['company_id' => $companyId, 'is_default' => 1])
                ->andWhere(fn($exp) => $exp->like('LOWER(name)', $hint))
                ->first();
            if ($ser) {
                return $ser;
            }
        }

        // 4) Globalna domyślna (fallback)
        $ser = $seriesTable->find()
            ->where(['company_id' => $companyId, 'is_default' => 1])
            ->first();
        if ($ser) {
            return $ser;
        }

        // 5) Dowolna z hintem nazwy
        foreach ($hints as $hint) {
            $ser = $seriesTable->find()
                ->where(['company_id' => $companyId])
                ->andWhere(fn($exp) => $exp->like('LOWER(name)', $hint))
                ->first();
            if ($ser) {
                return $ser;
            }
        }

        return null;
    }
}

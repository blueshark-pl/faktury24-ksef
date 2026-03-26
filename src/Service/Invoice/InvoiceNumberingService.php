<?php
declare(strict_types=1);

namespace App\Service\Invoice;

use Cake\ORM\Table;
use Cake\Log\Log;

/**
 * Serwis numeracji faktur.
 *
 * Odpowiada za:
 * - wyciąganie numeru porządkowego z pełnego numeru faktury,
 * - formatowanie wzorca numeracji z placeholderami,
 * - wyznaczanie kolejnego numeru dla danej serii/okresu/firmy.
 *
 * Wyekstrahowany z InvoicesController (commit 2026-03-18).
 */
class InvoiceNumberingService
{
    /**
     * Wyciąga numer porządkowy z pełnego numeru faktury (np. "FV/5/01/2025" → 5).
     */
    public function extractNumberFromFullnumber(string $fullnumber): int
    {
        Log::debug('InvoiceNumberingService::extractNumberFromFullnumber: ' . $fullnumber);

        // Wzorzec: PREFIX/NUMER/miesiąc/rok lub PREFIX/NUMER/rok
        if (preg_match('/^[A-Za-z]+\/0*(\d+)\//', $fullnumber, $matches)) {
            $extracted = (int)$matches[1];
            Log::debug('Extracted (prefix/number/...): ' . $extracted);
            return $extracted;
        }

        // Fallback — pierwszy numer w stringu
        if (preg_match('/\b0*(\d+)\b/', $fullnumber, $matches)) {
            $extracted = (int)$matches[1];
            Log::debug('Extracted (first found): ' . $extracted);
            return $extracted;
        }

        Log::debug('No number found, returning 0');
        return 0;
    }

    /**
     * Formatuje wzorzec numeracji z zaawansowanymi placeholderami.
     *
     * Obsługiwane placeholdery:
     *   [numer], [rok], [miesiac], [miesiąc], [dzien], [dzień]
     *   [numer:zera_wiodące=N], [miesiąc:zera_wiodące=N], [dzień:zera_wiodące=N]
     *   [rok:format_dwucyfrowy], [kwartał]
     */
    public function formatPattern(string $template, int $number, string $issueDate): string
    {
        $date = new \DateTime($issueDate);

        $replacements = [
            '[numer]'    => (string)$number,
            '[rok]'      => $date->format('Y'),
            '[miesiac]'  => $date->format('m'),
            '[miesiąc]'  => $date->format('m'),
            '[dzien]'    => $date->format('d'),
            '[dzień]'    => $date->format('d'),
        ];

        $result = str_replace(array_keys($replacements), array_values($replacements), $template);

        // [numer:zera_wiodące=N]
        $result = preg_replace_callback('/\[numer:zera_wiodące=(\d+)\]/', static function ($m) use ($number): string {
            return str_pad((string)$number, (int)$m[1], '0', STR_PAD_LEFT);
        }, $result);

        // [miesiąc:zera_wiodące=N]
        $result = preg_replace_callback('/\[miesiąc:zera_wiodące=(\d+)\]/', static function ($m) use ($date): string {
            return str_pad($date->format('m'), (int)$m[1], '0', STR_PAD_LEFT);
        }, $result);

        // [dzień:zera_wiodące=N]
        $result = preg_replace_callback('/\[dzień:zera_wiodące=(\d+)\]/', static function ($m) use ($date): string {
            return str_pad($date->format('d'), (int)$m[1], '0', STR_PAD_LEFT);
        }, $result);

        // [rok:format_dwucyfrowy]
        $result = preg_replace_callback('/\[rok:format_dwucyfrowy\]/', static function () use ($date): string {
            return $date->format('y');
        }, $result);

        // [kwartał]
        $result = preg_replace_callback('/\[kwartał\]/', static function () use ($date): string {
            return (string)ceil((int)$date->format('n') / 3);
        }, $result);

        return (string)$result;
    }

    /**
     * Wyznacza kolejny numer dla serii z uwzględnieniem okresu (miesięczny/roczny/ciągły).
     *
     * @param Table  $invoicesTable  Tabela invoices (do zapytania o ostatnią fakturę).
     * @param object $series         Encja InvoiceSeries (wymaga: id, starting_number, series_template, invoice_series_period->name).
     * @param string $companyId      UUID firmy.
     * @param string $issueDate      Data wystawienia (Y-m-d).
     * @param string|null $excludeId UUID faktury do wykluczenia (edycja).
     *
     * @return array{nextNumber: int, fullnumber: string}
     */
    public function computeNextNumber(
        Table $invoicesTable,
        object $series,
        string $companyId,
        string $issueDate,
        ?string $excludeId = null,
    ): array {
        $dateObject = new \DateTimeImmutable($issueDate);
        $year  = (int)$dateObject->format('Y');
        $month = (int)$dateObject->format('m');

        $where = [
            'company_id'       => $companyId,
            'invoice_series_id' => $series->id,
            'fullnumber IS NOT' => null,
        ];
        if ($excludeId !== null) {
            $where['id !='] = $excludeId;
        }

        $periodName = (string)($series->invoice_series_period->name ?? '');
        if (stripos($periodName, 'miesięczn') !== false || stripos($periodName, 'monthly') !== false) {
            $where['year']  = $year;
            $where['month'] = $month;
        } elseif (stripos($periodName, 'roczn') !== false || stripos($periodName, 'yearly') !== false) {
            $where['year'] = $year;
        }

        $lastInvoice = $invoicesTable->find()
            ->where($where)
            ->order(['number' => 'DESC', 'id' => 'DESC'])
            ->first();

        // Jednorazowy override numeru (np. przy migracji z innego systemu)
        $overrideNext = isset($series->override_next_number) ? $series->override_next_number : null;
        if ($overrideNext !== null && $overrideNext > 0) {
            $nextNumber = (int)$overrideNext;
            // Wyczyść po użyciu — jednorazowe
            $invoicesTable->getConnection()->execute(
                'UPDATE invoice_series SET override_next_number = NULL WHERE id = ?',
                [$series->id]
            );
        } elseif ($lastInvoice) {
            $extractedNumber = !empty($lastInvoice->number)
                ? (int)$lastInvoice->number
                : $this->extractNumberFromFullnumber((string)$lastInvoice->fullnumber);
            $nextNumber = $extractedNumber + 1;
        } else {
            $nextNumber = (int)($series->starting_number ?: 1);
        }

        $template  = (string)($series->series_template ?: '[numer]');
        $fullnumber = $this->formatPattern($template, $nextNumber, $issueDate);

        return ['nextNumber' => $nextNumber, 'fullnumber' => $fullnumber];
    }
}

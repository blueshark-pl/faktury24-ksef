<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Inserts system series (is_system=1) for all standard invoice types.
 * These rows are copied for every company by copySystemSeriesForCompany().
 * Using stable UUIDs in the 00000000-0000-0000-0001-xxxxxxxxxxxx range
 * so the INSERT IGNORE is fully idempotent.
 */
class AddAllSystemSeries extends AbstractMigration
{
    private const ROWS = [
        // [uuid_suffix, type, name, template]
        ['000000000001', 'vat',              'Faktura VAT',                     'FV/[numer]/[rok]'],
        ['000000000002', 'novat',            'Rachunek',                        'R/[numer]/[rok]'],
        ['000000000003', 'currency',         'Faktura walutowa',                'FW/[numer]/[rok]'],
        ['000000000004', 'margin',           'Faktura marża',                   'FM/[numer]/[rok]'],
        ['000000000005', 'proforma',         'Faktura proforma',                'PRO/[numer]/[rok]'],
        ['000000000006', 'advance',          'Faktura zaliczkowa',              'FZ/[numer]/[rok]'],
        ['000000000007', 'correction',       'Faktura korygująca',              'FK/[numer]/[rok]'],
        ['000000000008', 'internal',         'Dowód wewnętrzny',                'DW/[numer]/[rok]'],
        ['000000000009', 'internalevidence', 'Dowód wewnętrzny (ewidencja)',    'DWE/[numer]/[rok]'],
        ['000000000010', 'oss',              'Faktura OSS',                     'OSS/[numer]/[rok]'],
        // rental was added in 20260326150000; keep it here too for completeness (IGNORE skips duplicate)
        ['000000000099', 'rental',           'Faktura najmu prywatnego',        'FNP/[numer]/[rok]'],
    ];

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach (self::ROWS as [$suffix, $type, $name, $template]) {
            $uuid = '00000000-0000-0000-0001-' . $suffix;
            $name = addslashes($name);
            $this->execute("
                INSERT IGNORE INTO invoice_series
                    (id, company_id, invoice_series_type_id, invoice_series_period_id,
                     is_default, name, type, series_template, starting_number,
                     is_system, is_blocked, parent_id, created, modified)
                VALUES
                    ('{$uuid}', '{$uuid}', NULL, NULL,
                     1, '{$name}', '{$type}', '{$template}', 1,
                     1, 1, NULL, '{$now}', '{$now}')
            ");
        }
    }

    public function down(): void
    {
        $ids = implode("','", array_map(
            fn($r) => '00000000-0000-0000-0001-' . $r[0],
            self::ROWS
        ));
        $this->execute("DELETE FROM invoice_series WHERE id IN ('{$ids}')");
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddRentalSystemSeries extends AbstractMigration
{
    public function up(): void
    {
        // Insert a system series for "Faktura najmu prywatnego" (rental type).
        // System rows use is_system=1 so copySystemSeriesForCompany() picks them up
        // and copies them to each new/existing company.
        // We use a stable UUID (version-4 style) so re-running is idempotent.
        $uuid = '00000000-0000-0000-0000-000000000099';
        $now  = date('Y-m-d H:i:s');

        $this->execute("
            INSERT IGNORE INTO invoice_series
                (id, company_id, invoice_series_type_id, invoice_series_period_id,
                 is_default, name, type, series_template, starting_number,
                 is_system, is_blocked, parent_id, created, modified)
            VALUES
                ('{$uuid}', '{$uuid}', NULL, NULL,
                 1, 'Faktura najmu prywatnego', 'rental', 'FNP/[numer]/[rok]', 1,
                 1, 1, NULL, '{$now}', '{$now}')
        ");
    }

    public function down(): void
    {
        $this->execute("DELETE FROM invoice_series WHERE id = '00000000-0000-0000-0000-000000000099'");
    }
}

<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Adds registers_json (TEXT, nullable) to invoice_company_details.
 * Stores a JSON snapshot of all company_registers rows at invoice creation time.
 * The old krs/regon/bdo columns are kept for backward-compat but will no longer be
 * filled from company.regon — only from registers_json.
 */
class AddRegistersJsonToInvoiceCompanyDetails extends BaseMigration
{
    public function up(): void
    {
        $icd = $this->table('invoice_company_details');
        if (!$icd->hasColumn('registers_json')) {
            $icd->addColumn('registers_json', 'text', [
                'null'    => true,
                'default' => null,
                'comment' => 'JSON snapshot of company_registers at invoice creation',
                'after'   => 'bdo',
            ])->update();
        }
    }

    public function down(): void
    {
        $icd = $this->table('invoice_company_details');
        if ($icd->hasColumn('registers_json')) {
            $icd->removeColumn('registers_json')->update();
        }
    }
}

<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Adds bank_desc and bank_correspondent to company_bank_accounts.
 * Adds bank_correspondent to invoice_company_details (snapshot).
 */
class AddBankDescAndCorrespondentFields extends BaseMigration
{
    public function up(): void
    {
        // company_bank_accounts: bank_desc, bank_correspondent
        $cba = $this->table('company_bank_accounts');
        if (!$cba->hasColumn('bank_desc')) {
            $cba->addColumn('bank_desc', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'comment' => 'Opis rachunku bankowego',
                'after'   => 'bank_name',
            ]);
        }
        if (!$cba->hasColumn('bank_correspondent')) {
            $cba->addColumn('bank_correspondent', 'string', [
                'limit'   => 64,
                'null'    => true,
                'default' => null,
                'comment' => 'Rachunek własny banku (korespondencyjny)',
                'after'   => 'swift',
            ]);
        }
        $cba->update();

        // invoice_company_details: bank_correspondent (snapshot)
        $icd = $this->table('invoice_company_details');
        if (!$icd->hasColumn('bank_correspondent')) {
            $icd->addColumn('bank_correspondent', 'string', [
                'limit'   => 64,
                'null'    => true,
                'default' => null,
                'comment' => 'Rachunek własny banku – snapshot',
                'after'   => 'swift',
            ]);
            $icd->update();
        }
    }

    public function down(): void
    {
        $cba = $this->table('company_bank_accounts');
        if ($cba->hasColumn('bank_correspondent')) {
            $cba->removeColumn('bank_correspondent');
        }
        if ($cba->hasColumn('bank_desc')) {
            $cba->removeColumn('bank_desc');
        }
        $cba->update();

        $icd = $this->table('invoice_company_details');
        if ($icd->hasColumn('bank_correspondent')) {
            $icd->removeColumn('bank_correspondent')->update();
        }
    }
}

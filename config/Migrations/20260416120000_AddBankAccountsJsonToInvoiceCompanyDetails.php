<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddBankAccountsJsonToInvoiceCompanyDetails extends AbstractMigration
{
    public function change(): void
    {
        $this->table('invoice_company_details')
            ->addColumn('bank_accounts_json', 'text', [
                'null'    => true,
                'default' => null,
                'comment' => 'JSON snapshot wszystkich rachunków bankowych firmy w momencie wystawienia faktury',
            ])
            ->update();
    }
}

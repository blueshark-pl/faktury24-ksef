<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateCompanies extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('companies', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid')
            ->addColumn('name', 'string', ['limit' => 160, 'null' => false])
            ->addColumn('altname', 'string', ['limit' => 160, 'null' => true, 'default' => null])
            ->addColumn('nip', 'string', ['limit' => 10, 'null' => true, 'default' => null]) // PL: 10 cyfr
            ->addColumn('regon', 'string', ['limit' => 14, 'null' => true, 'default' => null])
            ->addColumn('country', 'string', ['limit' => 2, 'null' => true, 'default' => 'PL'])
            ->addColumn('postal_code', 'string', ['limit' => 6, 'null' => true, 'default' => null]) // NN-NNN
            ->addColumn('city', 'string', ['limit' => 120, 'null' => true, 'default' => null])
            ->addColumn('street', 'string', ['limit' => 160, 'null' => true, 'default' => null])
            ->addColumn('local_number', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('phone', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('bank_name', 'string', ['limit' => 120, 'null' => true, 'default' => null]) // legacy, można nie używać gdy są konta wielokrotne
            ->addColumn('bank_account', 'string', ['limit' => 34, 'null' => true, 'default' => null]) // legacy
            ->addColumn('logo_url', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('issuer', 'string', ['limit' => 160, 'null' => true, 'default' => null])
            ->addColumn('vat_payer', 'boolean', ['default' => 1, 'null' => false])
            ->addColumn('register_date', 'date', ['null' => true, 'default' => null])
            ->addColumn('subscription_end', 'date', ['null' => true, 'default' => null])
            ->addColumn('is_active', 'boolean', ['default' => 1, 'null' => false])
            ->addColumn('invoice_template', 'string', ['limit' => 64, 'null' => true, 'default' => 'default'])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['nip'], ['unique' => false, 'name' => 'IDX_companies_nip']) // unikalność bywa problematyczna dla pustych wartości i oddziałów
            ->addIndex(['regon'], ['unique' => false, 'name' => 'IDX_companies_regon'])
            ->addIndex(['subscription_end'], ['name' => 'IDX_companies_subscription_end'])
            ->create();
    }
}

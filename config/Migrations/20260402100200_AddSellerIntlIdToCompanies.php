<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add seller international identifier fields to companies table.
 *
 * These fields replace the per-invoice seller_vat_prefix / seller_vat_eu /
 * seller_eori inputs that lived in tab_identifiers.php. Values are now
 * managed globally in the company profile and copied to invoices on save.
 */
class AddSellerIntlIdToCompanies extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('companies');

        if (!$table->hasColumn('seller_vat_prefix')) {
            $table->addColumn('seller_vat_prefix', 'string', [
                'limit'   => 8,
                'null'    => true,
                'default' => null,
                'comment' => 'Prefiks VAT UE sprzedawcy (np. PL, DE)',
                'after'   => 'nip',
            ]);
        }
        if (!$table->hasColumn('seller_vat_eu')) {
            $table->addColumn('seller_vat_eu', 'string', [
                'limit'   => 32,
                'null'    => true,
                'default' => null,
                'comment' => 'Numer VAT-UE sprzedawcy',
                'after'   => 'seller_vat_prefix',
            ]);
        }
        if (!$table->hasColumn('seller_eori')) {
            $table->addColumn('seller_eori', 'string', [
                'limit'   => 32,
                'null'    => true,
                'default' => null,
                'comment' => 'Numer EORI sprzedawcy',
                'after'   => 'seller_vat_eu',
            ]);
        }

        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('companies');
        foreach (['seller_vat_prefix', 'seller_vat_eu', 'seller_eori'] as $col) {
            if ($table->hasColumn($col)) {
                $table->removeColumn($col);
            }
        }
        $table->update();
    }
}

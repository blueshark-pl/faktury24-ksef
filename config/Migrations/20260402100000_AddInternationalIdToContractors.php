<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add international identifier fields to contractors table.
 *
 * These fields mirror invoice_contractors and allow storing
 * EU VAT prefix/number, EORI, and other country tax IDs
 * directly on the contractor master record.
 */
class AddInternationalIdToContractors extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('contractors');

        if (!$table->hasColumn('vat_prefix')) {
            $table->addColumn('vat_prefix', 'string', [
                'limit'   => 8,
                'null'    => true,
                'default' => null,
                'comment' => 'Prefiks VAT UE (np. DE, FR)',
                'after'   => 'eu_vat',
            ]);
        }
        if (!$table->hasColumn('vat_eu')) {
            $table->addColumn('vat_eu', 'string', [
                'limit'   => 32,
                'null'    => true,
                'default' => null,
                'comment' => 'Numer VAT UE bez prefiksu kraju',
                'after'   => 'vat_prefix',
            ]);
        }
        if (!$table->hasColumn('eori')) {
            $table->addColumn('eori', 'string', [
                'limit'   => 32,
                'null'    => true,
                'default' => null,
                'comment' => 'Numer EORI',
                'after'   => 'vat_eu',
            ]);
        }
        if (!$table->hasColumn('tax_id_other')) {
            $table->addColumn('tax_id_other', 'string', [
                'limit'   => 64,
                'null'    => true,
                'default' => null,
                'comment' => 'Inny identyfikator podatkowy (NrID w XSD FA3)',
                'after'   => 'eori',
            ]);
        }
        if (!$table->hasColumn('tax_id_other_country')) {
            $table->addColumn('tax_id_other_country', 'string', [
                'limit'   => 8,
                'null'    => true,
                'default' => null,
                'comment' => 'Kod kraju dla tax_id_other (KodKraju w XSD FA3)',
                'after'   => 'tax_id_other',
            ]);
        }

        $table->update();
    }

    public function down(): void
    {
        $table = $this->table('contractors');
        foreach (['vat_prefix', 'vat_eu', 'eori', 'tax_id_other', 'tax_id_other_country'] as $col) {
            if ($table->hasColumn($col)) {
                $table->removeColumn($col);
            }
        }
        $table->update();
    }
}

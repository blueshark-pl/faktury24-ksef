<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add all missing invoice-related columns / tables that exist in the UI form
 * (templates/Invoices/add.php) but were never persisted to the database.
 *
 * Covers:
 *  - invoices: sold_date, paid_at, partial_paid_at, lang, auto_send,
 *              buyer_is_jst, buyer_in_vat_group, annotations (JSON),
 *              annotations_tax_free, annotations_tax_free_field,
 *              seller_vat_prefix, seller_vat_eu, seller_eori,
 *              buyer_vat_prefix, buyer_vat_eu, buyer_eori,
 *              buyer_tax_id_other, buyer_tax_id_other_country,
 *              company_bank_account_id
 *  - invoice_contents: gtin, cn_code, pkob, is_attachment15, excise_amount,
 *                      procedure_marking
 *  - invoice_contractors: email, phone
 *  - invoice_recipients (new table): per-invoice snapshot of recipient
 */
class AddMissingInvoiceFields extends AbstractMigration
{
    public function change(): void
    {
        // ==================== INVOICES ====================
        $inv = $this->table('invoices');

        $invColumns = [
            'sold_date' => ['type' => 'date', 'opts' => ['null' => true, 'default' => null, 'comment' => 'Data sprzedaży / wykonania usługi']],
            'paid_at' => ['type' => 'date', 'opts' => ['null' => true, 'default' => null, 'comment' => 'Data pełnej zapłaty']],
            'partial_paid_at' => ['type' => 'date', 'opts' => ['null' => true, 'default' => null, 'comment' => 'Data częściowej zapłaty']],
            'lang' => ['type' => 'string', 'opts' => ['limit' => 8, 'null' => true, 'default' => 'pl', 'comment' => 'Język faktury (pl/en/de…)']],
            'auto_send' => ['type' => 'boolean', 'opts' => ['default' => false, 'null' => false, 'comment' => 'Auto-wysyłka e-mail po zapisie']],
            'buyer_is_jst' => ['type' => 'boolean', 'opts' => ['default' => false, 'null' => false, 'comment' => 'Nabywca jest JST']],
            'buyer_in_vat_group' => ['type' => 'boolean', 'opts' => ['default' => false, 'null' => false, 'comment' => 'Nabywca w grupie VAT']],
            'annotations' => ['type' => 'json', 'opts' => ['null' => true, 'default' => null, 'comment' => 'KSeF adnotacje JSON: cash_method, reverse_charge, triangular, oss, tp, excise_return']],
            'annotations_tax_free' => ['type' => 'string', 'opts' => ['limit' => 24, 'null' => true, 'default' => null, 'comment' => 'Zwolnienie z VAT: art/directive/other/none']],
            'annotations_tax_free_field' => ['type' => 'text', 'opts' => ['null' => true, 'default' => null, 'comment' => 'Podstawa prawna zwolnienia']],
            'seller_vat_prefix' => ['type' => 'string', 'opts' => ['limit' => 8, 'null' => true, 'default' => null, 'comment' => 'Prefix VAT sprzedawcy (PL, DE…)']],
            'seller_vat_eu' => ['type' => 'string', 'opts' => ['limit' => 32, 'null' => true, 'default' => null, 'comment' => 'Nr VAT-UE sprzedawcy']],
            'seller_eori' => ['type' => 'string', 'opts' => ['limit' => 32, 'null' => true, 'default' => null, 'comment' => 'Nr EORI sprzedawcy']],
            'buyer_vat_prefix' => ['type' => 'string', 'opts' => ['limit' => 8, 'null' => true, 'default' => null, 'comment' => 'Prefix VAT nabywcy']],
            'buyer_vat_eu' => ['type' => 'string', 'opts' => ['limit' => 32, 'null' => true, 'default' => null, 'comment' => 'Nr VAT-UE nabywcy']],
            'buyer_eori' => ['type' => 'string', 'opts' => ['limit' => 32, 'null' => true, 'default' => null, 'comment' => 'Nr EORI nabywcy']],
            'buyer_tax_id_other' => ['type' => 'string', 'opts' => ['limit' => 64, 'null' => true, 'default' => null, 'comment' => 'Inny nr podatkowy nabywcy']],
            'buyer_tax_id_other_country' => ['type' => 'string', 'opts' => ['limit' => 8, 'null' => true, 'default' => null, 'comment' => 'Kraj innego nr podatkowego']],
            'company_bank_account_id' => ['type' => 'char', 'opts' => ['limit' => 36, 'null' => true, 'default' => null, 'comment' => 'FK do company_bank_accounts']],
        ];

        foreach ($invColumns as $col => $def) {
            if (!$inv->hasColumn($col)) {
                $inv->addColumn($col, $def['type'], $def['opts']);
            }
        }
        $inv->update();

        // ==================== INVOICE_CONTENTS ====================
        $ic = $this->table('invoice_contents');

        $icColumns = [
            'gtin' => ['type' => 'string', 'opts' => ['limit' => 32, 'null' => true, 'default' => null, 'comment' => 'GTIN / EAN']],
            'cn_code' => ['type' => 'string', 'opts' => ['limit' => 16, 'null' => true, 'default' => null, 'comment' => 'Kod CN (Nomenklatura Scalona)']],
            'pkob' => ['type' => 'string', 'opts' => ['limit' => 16, 'null' => true, 'default' => null, 'comment' => 'PKOB']],
            'is_attachment15' => ['type' => 'boolean', 'opts' => ['default' => false, 'null' => false, 'comment' => 'Załącznik nr 15 do ustawy o VAT']],
            'excise_amount' => ['type' => 'decimal', 'opts' => ['precision' => 18, 'scale' => 2, 'null' => true, 'default' => null, 'comment' => 'Kwota akcyzy (KwotaAkcyzy)']],
            'procedure_marking' => ['type' => 'string', 'opts' => ['limit' => 16, 'null' => true, 'default' => null, 'comment' => 'Oznaczenie procedury (OznaczenieProcedury)']],
        ];

        foreach ($icColumns as $col => $def) {
            if (!$ic->hasColumn($col)) {
                $ic->addColumn($col, $def['type'], $def['opts']);
            }
        }
        $ic->update();

        // ==================== INVOICE_CONTRACTORS ====================
        $icc = $this->table('invoice_contractors');

        if (!$icc->hasColumn('email')) {
            $icc->addColumn('email', 'string', ['limit' => 128, 'null' => true, 'default' => null, 'comment' => 'E-mail nabywcy (snapshot)']);
        }
        if (!$icc->hasColumn('phone')) {
            $icc->addColumn('phone', 'string', ['limit' => 40, 'null' => true, 'default' => null, 'comment' => 'Telefon nabywcy (snapshot)']);
        }
        $icc->update();

        // ==================== INVOICE_RECIPIENTS (new table) ====================
        if (!$this->hasTable('invoice_recipients')) {
            $this->table('invoice_recipients', [
                'id' => false,
                'primary_key' => ['id'],
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('nip', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('street', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('zip', 'string', ['limit' => 16, 'null' => true])
            ->addColumn('country', 'string', ['limit' => 64, 'default' => 'PL'])
            ->addColumn('email', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 40, 'null' => true])
            ->addTimestamps('created', 'modified')
            ->addIndex(['invoice_id'], ['unique' => true])
            ->create();
        }
    }
}

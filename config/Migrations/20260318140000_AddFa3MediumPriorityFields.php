<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * FA(3) KSeF — MEDIUM priority fields.
 *
 * 1) invoice_additional_descriptions  — nowa tabela (DodatkowyOpis: klucz+wartość)
 * 2) invoice_payments — upewnienie się, że tabela istnieje (była create → drop → FK)
 * 3) company_bank_accounts.swift — kod SWIFT banku
 * 4) invoice_company_details.swift — snapshot SWIFT sprzedawcy
 * 5) companies.gln, contractors.gln — GLN sprzedawcy / nabywcy
 * 6) invoice_company_details.gln, invoice_contractors.gln — snapshot GLN
 * 7) invoice_contractors.nr_klienta — numer klienta nabywcy
 * 8) invoice_contents.discount_amount — P_10 (kwota rabatu)
 */
class AddFa3MediumPriorityFields extends BaseMigration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────
        // 1) invoice_additional_descriptions (DodatkowyOpis)
        // ──────────────────────────────────────────────
        if (!$this->hasTable('invoice_additional_descriptions')) {
            $table = $this->table('invoice_additional_descriptions', [
                'id' => false,
                'primary_key' => ['id'],
                'collation' => 'utf8mb4_unicode_ci',
            ]);
            $table
                ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
                ->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => false])
                ->addColumn('nr_wiersza', 'integer', ['null' => true, 'default' => null, 'comment' => 'NrWierszaFa — powiązanie z wierszem faktury (opcjonalne)'])
                ->addColumn('klucz', 'string', ['limit' => 256, 'null' => false, 'comment' => 'Klucz opisu (wymagane)'])
                ->addColumn('wartosc', 'string', ['limit' => 256, 'null' => false, 'comment' => 'Wartość opisu (wymagane)'])
                ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
                ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
                ->addIndex(['invoice_id'])
                ->addForeignKey('invoice_id', 'invoices', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                ->create();
        }

        // ──────────────────────────────────────────────
        // 2) invoice_payments — upewnienie się, że tabela istnieje
        //    (migracje: create → drop → FK — tabela może nie istnieć)
        // ──────────────────────────────────────────────
        if (!$this->hasTable('invoice_payments')) {
            $table = $this->table('invoice_payments', [
                'id' => false,
                'primary_key' => ['id'],
                'collation' => 'utf8mb4_unicode_ci',
            ]);
            $table
                ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
                ->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => false])
                ->addColumn('payment_date', 'date', ['null' => false])
                ->addColumn('amount', 'decimal', ['precision' => 18, 'scale' => 2, 'null' => false])
                ->addColumn('payment_method', 'string', ['limit' => 64, 'null' => false, 'default' => '6'])
                ->addColumn('description', 'string', ['limit' => 256, 'null' => true, 'default' => null])
                ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
                ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
                ->addIndex(['invoice_id'])
                ->addForeignKey('invoice_id', 'invoices', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                ->create();
        }

        // ──────────────────────────────────────────────
        // 3) company_bank_accounts.swift
        // ──────────────────────────────────────────────
        $cba = $this->table('company_bank_accounts');
        if (!$cba->hasColumn('swift')) {
            $cba->addColumn('swift', 'string', ['limit' => 11, 'null' => true, 'default' => null, 'after' => 'iban', 'comment' => 'Kod SWIFT banku (8 lub 11 znaków)']);
            $cba->update();
        }

        // ──────────────────────────────────────────────
        // 4) invoice_company_details.swift
        // ──────────────────────────────────────────────
        $icd = $this->table('invoice_company_details');
        if (!$icd->hasColumn('swift')) {
            $icd->addColumn('swift', 'string', ['limit' => 11, 'null' => true, 'default' => null, 'after' => 'bank_desc', 'comment' => 'Snapshot SWIFT banku sprzedawcy']);
            $icd->update();
        }

        // ──────────────────────────────────────────────
        // 5) GLN sprzedawcy / nabywcy (master data)
        // ──────────────────────────────────────────────
        $companies = $this->table('companies');
        if (!$companies->hasColumn('gln')) {
            $companies->addColumn('gln', 'string', ['limit' => 13, 'null' => true, 'default' => null, 'comment' => 'GLN sprzedawcy (Global Location Number)']);
            $companies->update();
        }

        $contractors = $this->table('contractors');
        if (!$contractors->hasColumn('gln')) {
            $contractors->addColumn('gln', 'string', ['limit' => 13, 'null' => true, 'default' => null, 'comment' => 'GLN nabywcy']);
            $contractors->update();
        }

        // ──────────────────────────────────────────────
        // 6) Snapshoty GLN na fakturze
        // ──────────────────────────────────────────────
        if (!$icd->hasColumn('gln')) {
            $icd = $this->table('invoice_company_details');
            $icd->addColumn('gln', 'string', ['limit' => 13, 'null' => true, 'default' => null, 'comment' => 'Snapshot GLN sprzedawcy']);
            $icd->update();
        }

        $ic = $this->table('invoice_contractors');
        if (!$ic->hasColumn('gln')) {
            $ic->addColumn('gln', 'string', ['limit' => 13, 'null' => true, 'default' => null, 'comment' => 'Snapshot GLN nabywcy']);
            $ic->update();
        }

        // ──────────────────────────────────────────────
        // 7) invoice_contractors.nr_klienta
        // ──────────────────────────────────────────────
        if (!$ic->hasColumn('nr_klienta')) {
            $ic = $this->table('invoice_contractors');
            $ic->addColumn('nr_klienta', 'string', ['limit' => 64, 'null' => true, 'default' => null, 'comment' => 'NrKlienta — numer klienta nabywcy']);
            $ic->update();
        }

        // ──────────────────────────────────────────────
        // 8) invoice_contents.discount_amount (P_10)
        // ──────────────────────────────────────────────
        $contents = $this->table('invoice_contents');
        if (!$contents->hasColumn('discount_amount')) {
            $contents->addColumn('discount_amount', 'decimal', ['precision' => 18, 'scale' => 2, 'null' => true, 'default' => null, 'after' => 'discount_percent', 'comment' => 'P_10 — kwota opustu/obniżki (w PLN)']);
            $contents->update();
        }
    }

    public function down(): void
    {
        // invoice_additional_descriptions
        if ($this->hasTable('invoice_additional_descriptions')) {
            $this->table('invoice_additional_descriptions')->drop()->save();
        }

        // company_bank_accounts.swift
        $cba = $this->table('company_bank_accounts');
        if ($cba->hasColumn('swift')) {
            $cba->removeColumn('swift')->update();
        }

        // invoice_company_details.swift, gln
        $icd = $this->table('invoice_company_details');
        foreach (['swift', 'gln'] as $col) {
            if ($icd->hasColumn($col)) {
                $icd->removeColumn($col);
            }
        }
        $icd->update();

        // companies.gln
        $companies = $this->table('companies');
        if ($companies->hasColumn('gln')) {
            $companies->removeColumn('gln')->update();
        }

        // contractors.gln
        $contractors = $this->table('contractors');
        if ($contractors->hasColumn('gln')) {
            $contractors->removeColumn('gln')->update();
        }

        // invoice_contractors.gln, nr_klienta
        $ic = $this->table('invoice_contractors');
        foreach (['gln', 'nr_klienta'] as $col) {
            if ($ic->hasColumn($col)) {
                $ic->removeColumn($col);
            }
        }
        $ic->update();

        // invoice_contents.discount_amount
        $contents = $this->table('invoice_contents');
        if ($contents->hasColumn('discount_amount')) {
            $contents->removeColumn('discount_amount')->update();
        }
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateInvoicingSchema extends AbstractMigration
{
    public function change(): void
    {
        // ================= INVOICES =================
        $this->table('invoices', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('hash', 'string', ['limit' => 64, 'null' => true])
        ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])
        ->addColumn('parent_id', 'char', ['limit' => 36, 'null' => true])
        ->addColumn('type', 'string', ['limit' => 24, 'null' => true]) // invoice/proforma/correction/bill
        ->addColumn('correction_type', 'string', ['limit' => 24, 'null' => true])
        ->addColumn('simplified_invoice', 'boolean', ['default' => 0])
        ->addColumn('paymentmethod', 'string', ['limit' => 32, 'null' => true])
        ->addColumn('paymentdate', 'date', ['null' => true])
        ->addColumn('paymentstate', 'string', ['limit' => 16, 'null' => true]) // unpaid/paid/partial
        ->addColumn('date', 'date', ['null' => false])
        ->addColumn('total', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addColumn('netto', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addColumn('tax', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addColumn('alreadypaid', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addColumn('remaining', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addColumn('fullnumber', 'string', ['limit' => 64, 'null' => true])
        ->addColumn('currency', 'string', ['limit' => 8, 'default' => 'PLN'])
        ->addColumn('currency_date', 'date', ['null' => true])
        ->addColumn('currency_exchange', 'decimal', ['precision' => 18, 'scale' => 6, 'default' => 1])
        ->addColumn('description', 'text', ['null' => true])
        ->addColumn('is_print', 'boolean', ['default' => 0])
        ->addColumn('is_sent', 'boolean', ['default' => 0])
        ->addColumn('is_api', 'boolean', ['default' => 0])
        ->addTimestamps('created', 'modified')
        ->addIndex(['company_id'])
        ->addIndex(['parent_id'])
        ->addIndex(['fullnumber'])
        ->create();

        // ================= INVOICE_COMPANY_DETAILS =================
        $this->table('invoice_company_details', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('invoice_id', 'char', ['limit' => 36])
        ->addColumn('name', 'string', ['limit' => 255])
        ->addColumn('nip', 'string', ['limit' => 32, 'null' => true])
        ->addColumn('street', 'string', ['limit' => 255, 'null' => true])
        ->addColumn('city', 'string', ['limit' => 255, 'null' => true])
        ->addColumn('zip', 'string', ['limit' => 16, 'null' => true])
        ->addColumn('country', 'string', ['limit' => 64, 'default' => 'PL'])
        ->addColumn('bank_account', 'string', ['limit' => 64, 'null' => true])
        ->addTimestamps('created', 'modified')
        ->addIndex(['invoice_id'], ['unique' => true])
        ->create();

        // ================= INVOICE_CONTRACTORS =================
        $this->table('invoice_contractors', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('invoice_id', 'char', ['limit' => 36])
        ->addColumn('name', 'string', ['limit' => 255])
        ->addColumn('nip', 'string', ['limit' => 32, 'null' => true])
        ->addColumn('street', 'string', ['limit' => 255, 'null' => true])
        ->addColumn('city', 'string', ['limit' => 255, 'null' => true])
        ->addColumn('zip', 'string', ['limit' => 16, 'null' => true])
        ->addColumn('country', 'string', ['limit' => 64, 'default' => 'PL'])
        ->addColumn('account_number', 'string', ['limit' => 64, 'null' => true])
        ->addTimestamps('created', 'modified')
        ->addIndex(['invoice_id'], ['unique' => true])
        ->create();

        // ================= INVOICE_CONTENTS =================
        $this->table('invoice_contents', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('invoice_id', 'char', ['limit' => 36])
        ->addColumn('vat_code_id', 'char', ['limit' => 36, 'null' => true])
        ->addColumn('name', 'string', ['limit' => 255])
        ->addColumn('product_desc', 'text', ['null' => true])
        ->addColumn('quantity', 'decimal', ['precision' => 18, 'scale' => 3, 'default' => 0])
        ->addColumn('unit', 'string', ['limit' => 16, 'null' => true])
        ->addColumn('price', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addColumn('discount_percent', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0])
        ->addColumn('netto', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addColumn('brutto', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addTimestamps('created', 'modified')
        ->addIndex(['invoice_id'])
        ->create();

        // ================= INVOICE_VAT_CONTENTS =================
        $this->table('invoice_vat_contents', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('invoice_id', 'char', ['limit' => 36])
        ->addColumn('vat_code_id', 'char', ['limit' => 36, 'null' => true])
        ->addColumn('netto', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addColumn('tax', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addColumn('brutto', 'decimal', ['precision' => 18, 'scale' => 2, 'default' => 0])
        ->addTimestamps('created', 'modified')
        ->addIndex(['invoice_id'])
        ->create();

        // ================= VATS =================
        $this->table('vats', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('name', 'string', ['limit' => 32])
        ->addColumn('rate', 'decimal', ['precision' => 5, 'scale' => 2])
        ->addColumn('deleted', 'boolean', ['default' => 0])
        ->addTimestamps('created', 'modified')
        ->create();

        // ================= INVOICE_SERIES =================
        $this->table('invoice_series', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('company_id', 'char', ['limit' => 36])
        ->addColumn('invoice_series_type_id', 'char', ['limit' => 36, 'null' => true])
        ->addColumn('invoice_series_period_id', 'char', ['limit' => 36, 'null' => true])
        ->addColumn('is_default', 'boolean', ['default' => 0])
        ->addColumn('name', 'string', ['limit' => 128])
        ->addColumn('series_template', 'string', ['limit' => 128])
        ->addColumn('starting_number', 'integer', ['default' => 1])
        ->addTimestamps('created', 'modified')
        ->addIndex(['company_id'])
        ->create();

        $this->table('invoice_series_periods', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('name', 'string', ['limit' => 64])
        ->addTimestamps('created', 'modified')
        ->create();

        $this->table('invoice_series_types', [
            'id' => false,
            'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
        ->addColumn('id', 'char', ['limit' => 36])
        ->addColumn('invoice_series_period_id', 'char', ['limit' => 36])
        ->addColumn('name', 'string', ['limit' => 64])
        ->addColumn('series_template', 'string', ['limit' => 128])
        ->addColumn('starting_number', 'integer', ['default' => 1])
        ->addTimestamps('created', 'modified')
        ->create();
    }
}

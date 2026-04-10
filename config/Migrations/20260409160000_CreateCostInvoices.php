<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateCostInvoices extends AbstractMigration
{
    public function change(): void
    {
        // ── Faktury kosztowe (od przewoźników) ──────────────────────────────
        $this->table('cost_invoices', ['id' => true, 'primary_key' => ['id']])
            ->addColumn('source', 'string', ['limit' => 10, 'default' => 'manual', 'comment' => 'ksef|manual'])
            ->addColumn('ksef_number', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'Numer KSeF'])
            ->addColumn('invoice_number', 'string', ['limit' => 100, 'null' => true, 'default' => null])
            ->addColumn('contractor_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('contractor_nip', 'string', ['limit' => 20, 'null' => true, 'default' => null])
            ->addColumn('issue_date', 'date', ['null' => true, 'default' => null, 'comment' => 'Data wystawienia'])
            ->addColumn('receipt_date', 'date', ['null' => true, 'default' => null, 'comment' => 'Data wpływu'])
            ->addColumn('accounting_month', 'string', ['limit' => 7, 'null' => true, 'default' => null, 'comment' => 'Miesiąc rozliczeniowy YYYY-MM'])
            ->addColumn('netto', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('vat', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('brutto', 'decimal', ['precision' => 12, 'scale' => 2, 'default' => '0.00'])
            ->addColumn('currency', 'string', ['limit' => 5, 'default' => 'PLN'])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'received', 'comment' => 'received|verified|paid'])
            ->addColumn('pdf_path', 'string', ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('xml_path', 'string', ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('ksef_raw_json', 'text', ['null' => true, 'default' => null, 'comment' => 'Surowe dane z KSeF API'])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['contractor_nip'])
            ->addIndex(['accounting_month'])
            ->addIndex(['status'])
            ->addIndex(['ksef_number'], ['unique' => true, 'name' => 'uq_cost_invoices_ksef_number'])
            ->create();

        // ── Pivot: FK ↔ Zlecenie (wiele-do-wielu) ───────────────────────────
        $this->table('cost_invoice_orders', ['id' => true, 'primary_key' => ['id']])
            ->addColumn('cost_invoice_id', 'integer', ['null' => false])
            ->addColumn('speed_order_id', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['cost_invoice_id'])
            ->addIndex(['speed_order_id'])
            ->addIndex(['cost_invoice_id', 'speed_order_id'], ['unique' => true, 'name' => 'uq_cost_invoice_order'])
            ->create();
    }
}

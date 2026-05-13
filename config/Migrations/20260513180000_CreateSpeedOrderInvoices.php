<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * M:N pivot: wiele faktur na jedno zlecenie speed_orders.
 *
 * Wzorzec skopiowany z cost_invoice_orders (M:N dla faktur kosztowych).
 *
 * Strategia:
 *  1. Tworzymy pivot speed_order_invoices (źródło prawdy o powiązaniach)
 *  2. Backfill: każdy speed_orders.invoice_id IS NOT NULL → wpis w pivot
 *  3. Pole speed_orders.invoice_id ZOSTAJE jako DEPRECATED alias (patrz
 *     migracja MarkInvoiceIdDeprecated) — utrzymane dla wstecznej
 *     kompatybilności ze starszymi eksportami/raportami/SQL queries.
 *     Nie planowane do dropowania.
 */
class CreateSpeedOrderInvoices extends AbstractMigration
{
    public function up(): void
    {
        // 1. Pivot table — analogiczna struktura jak cost_invoice_orders
        $this->table('speed_order_invoices', ['id' => true, 'primary_key' => ['id']])
            ->addColumn('speed_order_id', 'integer', ['null' => false])
            ->addColumn('invoice_id',     'uuid',    ['null' => false, 'comment' => 'FK do invoices.id (UUID)'])
            ->addColumn('created',        'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['speed_order_id'])
            ->addIndex(['invoice_id'])
            ->addIndex(['speed_order_id', 'invoice_id'], ['unique' => true, 'name' => 'uq_speed_order_invoice'])
            ->create();

        // 2. Backfill istniejących powiązań — każda nie-pusta speed_orders.invoice_id
        //    trafia jako wpis w pivot. WAŻNE: wykonujemy SUREST tylko gdy obie strony
        //    naprawdę istnieją (LEFT JOIN check), inaczej dostalibyśmy FK violation.
        $this->execute(
            'INSERT INTO speed_order_invoices (speed_order_id, invoice_id, created)
             SELECT so.id, so.invoice_id, COALESCE(so.invoiced_at, NOW())
             FROM speed_orders so
             INNER JOIN invoices i ON i.id = so.invoice_id
             WHERE so.invoice_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        $this->table('speed_order_invoices')->drop()->save();
    }
}

<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Oznacza speed_orders.invoice_id jako DEPRECATED.
 *
 * Po wprowadzeniu M:N pivot speed_order_invoices (migracja 20260513180000)
 * legacy kolumna invoice_id nie jest już źródłem prawdy o powiązanych
 * fakturach — pełni tylko rolę aliasu na PIERWSZĄ fakturę zlecenia dla
 * starszych eksportów/raportów/SQL queries.
 *
 * Nowe kody powinny korzystać z pivota: $order->invoices (M:N).
 *
 * Kolumna zostaje na czas nieokreślony — przerwane drop'owanie ze względu
 * na koszty refactoringu zewnętrznych narzędzi/raportów.
 */
class MarkInvoiceIdDeprecated extends AbstractMigration
{
    public function up(): void
    {
        $this->table('speed_orders')
            ->changeColumn('invoice_id', 'uuid', [
                'null'    => true,
                'default' => null,
                'comment' => '[DEPRECATED] Alias pierwszej faktury powiązanej z tym zleceniem. Źródło prawdy: speed_order_invoices (M:N pivot). Nowy kod używa SpeedOrders->AllInvoices.',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('speed_orders')
            ->changeColumn('invoice_id', 'uuid', [
                'null'    => true,
                'default' => null,
                'comment' => '',
            ])
            ->update();
    }
}

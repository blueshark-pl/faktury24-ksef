<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Lokalne wpłaty do faktur archiwalnych.
 * Dane te persystują przez kolejne synchronizacje z API legacy —
 * są odejmowane od API-remaining podczas sync (API jest źródłem prawdy dla total/netto,
 * ale lokalne wpłaty korygują remaining/paymentstate po stronie wyświetlania).
 */
class CreateLegacyInvoicePayments extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('legacy_invoice_payments', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('legacy_invoice_id', 'uuid', ['null' => false])
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('payment_date', 'date', ['null' => false])
            ->addColumn('amount', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false])
            ->addColumn('payment_method', 'string', ['limit' => 30, 'null' => false, 'default' => 'transfer'])
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['legacy_invoice_id'])
            ->addIndex(['company_id'])
            ->create();
    }
}

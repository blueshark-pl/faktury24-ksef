<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Pola płatności dla faktur kosztowych.
 *
 * Wcześniej status 'paid' istniał ale bez szczegółów (kiedy, ile, jakim
 * sposobem). Te kolumny pozwalają śledzić termin, faktyczną datę zapłaty,
 * kwotę wpłaconą (dla częściowych) i pozostałą do zapłaty.
 */
class AddPaymentFieldsToCostInvoices extends BaseMigration
{
    public function change(): void
    {
        $this->table('cost_invoices')
            ->addColumn('payment_date', 'date', [
                'null' => true, 'default' => null,
                'after' => 'receipt_date',
                'comment' => 'Termin płatności (deadline)',
            ])
            ->addColumn('paid_at', 'date', [
                'null' => true, 'default' => null,
                'after' => 'payment_date',
                'comment' => 'Faktyczna data zapłaty',
            ])
            ->addColumn('paid_amount', 'decimal', [
                'precision' => 12, 'scale' => 2,
                'null' => false, 'default' => '0.00',
                'after' => 'paid_at',
                'comment' => 'Suma wpłacona (≤ brutto, częściowe płatności)',
            ])
            ->addColumn('payment_method', 'string', [
                'limit' => 20, 'null' => true, 'default' => null,
                'after' => 'paid_amount',
                'comment' => 'transfer|cash|card|compensation|other',
            ])
            ->addIndex(['payment_date'], ['name' => 'BY_PAYMENT_DATE'])
            ->addIndex(['paid_at'], ['name' => 'BY_PAID_AT'])
            ->save();
    }
}

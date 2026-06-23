<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Workflow status FV kosztowej — analog `cost_status` z `ksef_invoice_statuses`,
 * ale natywnie na tabeli `cost_invoices`. Zakres 1-9:
 *   1 = FV do potwierdzenia       (default po imporcie / dodaniu)
 *   2 = Oczekuje na dokumenty
 *   3 = Gotowa
 *   4 = Zaakceptowana             (startuje termin płatności)
 *   5 = Do opłacenia
 *   6 = Przeterminowana
 *   7 = Odrzucona
 *   8 = Wstrzymana
 *   9 = Do wyjaśnienia
 *
 * UWAGA: pole `status` (received/verified/paid) zostaje — to jest stan
 * "księgowy" (czy zaakceptowana i zapłacona). `cost_status` jest stanem
 * workflow operatora (kto kiedy ją widzi, kiedy startuje termin płatności).
 */
class AddCostStatusToCostInvoices extends BaseMigration
{
    public function change(): void
    {
        $this->table('cost_invoices')
            ->addColumn('cost_status', 'integer', [
                'limit' => 3, 'null' => false, 'default' => 1, 'signed' => false,
                'after' => 'status',
                'comment' => 'Workflow FV: 1-9 (analog ksef_invoice_statuses.cost_status)',
            ])
            ->addColumn('rejection_reason', 'string', [
                'limit' => 512, 'null' => true, 'default' => null,
                'after' => 'cost_status',
                'comment' => 'Powód odrzucenia (cost_status=7)',
            ])
            ->addIndex(['cost_status'], ['name' => 'BY_COST_STATUS'])
            ->save();
    }
}

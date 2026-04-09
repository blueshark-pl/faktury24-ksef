<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Tabela statusów workflow dla faktur kosztowych z KSeF (NDL PL).
 *
 * Jeden rekord per (company_id, environment, ksef_number).
 *
 * Słownik cost_status:
 *  1 = FV DO POTWIERDZENIA         (domyślny)
 *  2 = FV OCZEKUJĄCA NA DOKUMENTY
 *  3 = FV GOTOWA DO AKCEPTACJI
 *  4 = FV ZAAKCEPTOWANA            (tu startuje termin płatności – data docs_received_at)
 *  5 = FV DO OPŁACENIA
 *  6 = FV PRZETERMINOWANA
 *  7 = FV ODRZUCONA
 *  8 = FV WSTRZYMANA               (spór/reklamacja)
 *  9 = FV DO WYJAŚNIENIA           (różnice w kwocie/km/stawce)
 */
class CreateKsefInvoiceStatuses extends AbstractMigration
{
    public bool $autoId = false;

    public function change(): void
    {
        $this->table('ksef_invoice_statuses', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid', ['null' => false])

            // Powiązanie z firmą i środowiskiem
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('environment', 'string', ['limit' => 8, 'default' => 'prod', 'null' => false])

            // Identyfikator faktury w KSeF
            ->addColumn('ksef_number', 'string', ['limit' => 128, 'null' => false])

            // GŁÓWNY STATUS WORKFLOW
            ->addColumn('cost_status', 'integer', [
                'limit' => 1,
                'default' => 1,
                'null' => false,
                'signed' => false,
                'comment' => '1=DO POTWIERDZENIA, 2=CZEKA NA DOCS, 3=GOTOWA, 4=ZAAKCEPTOWANA, 5=DO OPŁACENIA, 6=PRZETERMINOWANA, 7=ODRZUCONA, 8=WSTRZYMANA, 9=DO WYJAŚNIENIA',
            ])

            // Kluczowe daty
            ->addColumn('docs_received_at', 'date', [
                'null' => true,
                'comment' => 'Data kompletności dokumentów — START terminu płatności (status >= 4)',
            ])
            ->addColumn('payment_due_date', 'date', [
                'null' => true,
                'comment' => 'Obliczony termin płatności (opcjonalnie uzupełniany ręcznie lub z faktury)',
            ])

            // Powód odrzucenia / uwagi
            ->addColumn('rejection_reason', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true, 'comment' => 'Notatki wewnętrzne'])

            // Kto zmienił status
            ->addColumn('changed_by', 'string', ['limit' => 128, 'null' => true, 'comment' => 'user login'])

            ->addTimestamps('created', 'modified')

            // Unique: jeden wpis per faktura per firma per środowisko
            ->addIndex(['company_id', 'environment', 'ksef_number'], ['unique' => true, 'name' => 'idx_ksef_inv_status_unique'])
            ->addIndex(['company_id', 'environment', 'cost_status'], ['name' => 'idx_ksef_inv_status_filter'])
            ->addIndex(['payment_due_date'], ['name' => 'idx_ksef_inv_status_due'])

            ->create();
    }
}

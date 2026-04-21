<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Tabela cache dla faktur z zewnętrznego systemu legacy (API ai-nordlogis).
 * Dane są synchronizowane per rok/miesiąc/rejestr — API jest źródłem prawdy.
 */
class CreateLegacyInvoices extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('legacy_invoices', ['id' => false, 'primary_key' => ['id']]);
        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addColumn('company_id', 'uuid', ['null' => false])
            ->addColumn('glo_id', 'integer', ['null' => false, 'comment' => 'PK z zewnętrznego systemu (GLO_ID)'])
            ->addColumn('rejestr', 'integer', ['null' => false, 'default' => 130, 'comment' => 'Numer rejestru (GLO_REJESTR)'])
            // Dane dokumentu
            ->addColumn('fullnumber', 'string', ['limit' => 100, 'null' => false, 'comment' => 'GLO_SYMBOL np. 0001/03/2026'])
            ->addColumn('date', 'date', ['null' => false, 'comment' => 'Data dokumentu (GLO_DATA_DOK)'])
            ->addColumn('paymentdate', 'date', ['null' => true, 'default' => null, 'comment' => 'Termin płatności (TERMIN)'])
            ->addColumn('glo_tyt1', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Referencja/tytuł (GLO_TYT1)'])
            ->addColumn('poz_naz7', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Nr powiązanego dokumentu (POZ_NAZ7)'])
            ->addColumn('poz_nazwa', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Opis pozycji (POZ_NAZWA)'])
            // Kontrahent
            ->addColumn('contractor_name', 'string', ['limit' => 255, 'null' => false, 'comment' => 'GLO_ODB_NAZWA1'])
            ->addColumn('contractor_nip', 'string', ['limit' => 30, 'null' => true, 'default' => null, 'comment' => 'GLO_ODB_NIP'])
            ->addColumn('contractor_city', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'GLO_ODB_POCZTA'])
            ->addColumn('contractor_country', 'string', ['limit' => 10, 'null' => true, 'default' => null, 'comment' => 'GLO_ODB_KRAJ'])
            ->addColumn('contractor_skrot', 'string', ['limit' => 30, 'null' => true, 'default' => null, 'comment' => 'GLO_ODB_SKROT'])
            // Wartości PLN
            ->addColumn('total', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false, 'default' => 0, 'comment' => 'GLO_BRUTTO'])
            ->addColumn('netto', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false, 'default' => 0, 'comment' => 'GLO_NETTO'])
            ->addColumn('alreadypaid', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false, 'default' => 0, 'comment' => 'GLO_ZL_ZAPLATA'])
            ->addColumn('remaining', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false, 'default' => 0, 'comment' => 'POZOSTALO_PLN'])
            // Wartości walutowe
            ->addColumn('currency', 'string', ['limit' => 10, 'null' => false, 'default' => 'PLN', 'comment' => 'GLO_WALUTA'])
            ->addColumn('total_wal', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false, 'default' => 0, 'comment' => 'GLO_WAL_WARTOSC'])
            ->addColumn('alreadypaid_wal', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false, 'default' => 0, 'comment' => 'GLO_WAL_ZAPLATA'])
            ->addColumn('remaining_wal', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => false, 'default' => 0, 'comment' => 'POZOSTALO_WAL'])
            ->addColumn('exchange_rate', 'decimal', ['precision' => 10, 'scale' => 4, 'null' => true, 'default' => null, 'comment' => 'GLO_WAL_PRZEL'])
            // Status płatności — computed podczas sync
            ->addColumn('paymentstate', 'string', ['limit' => 20, 'null' => false, 'default' => 'unpaid', 'comment' => 'paid/partial/unpaid — obliczane z POZOSTALO_PLN'])
            ->addColumn('dnit', 'integer', ['null' => true, 'default' => null, 'comment' => 'DNIT — liczba dni po terminie (ujemna = po terminie)'])
            // Metadane
            ->addColumn('platnosc', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'comment' => 'Warunki płatności (GLO_PLATNOSC)'])
            ->addColumn('teczka', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'Opiekun (GLO_TECZKA)'])
            ->addColumn('glo_rozrach', 'string', ['limit' => 100, 'null' => true, 'default' => null, 'comment' => 'GLO_ROZRACH'])
            ->addColumn('synced_at', 'datetime', ['null' => false])
            ->addIndex(['company_id'])
            ->addIndex(['glo_id', 'company_id'], ['unique' => true, 'name' => 'uq_legacy_glo_id_company'])
            ->addIndex(['paymentstate'])
            ->addIndex(['date'])
            ->addIndex(['paymentdate'])
            ->addIndex(['contractor_nip'])
            ->create();
    }
}

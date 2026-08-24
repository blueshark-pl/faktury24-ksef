<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CRM/TSL - kontrakty ramowe z cenami dla powtarzalnych klientow.
 *
 * Uzycie: przy tworzeniu SpeedOrder wyszukujemy aktywny kontrakt dla
 * (buyer_nip, from_country, from_city, to_country, to_city) i auto-prefill
 * ceny netto + waluty.
 *
 * Match jest fuzzy: bierzemy najlepiej pasujacy kontrakt (city LIKE lub
 * country match jesli city puste).
 */
class CreateCrmContracts extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('crm_contracts')) return;

        $this->table('crm_contracts', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false])

            // Klient (contractor lub tylko NIP)
            ->addColumn('contractor_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'FK do contractors (nullable - matchujemy tez po NIP)'])
            ->addColumn('contractor_nip', 'string', ['limit' => 30, 'null' => true,
                'comment' => 'NIP klienta - klucz do matchowania z buyer_nip w SpeedOrders'])
            ->addColumn('contractor_name', 'string', ['limit' => 255, 'null' => true,
                'comment' => 'Snapshot nazwy klienta dla wyswietlenia'])

            // Nazwa kontraktu (opcjonalna - dla listy)
            ->addColumn('name', 'string', ['limit' => 200, 'null' => false,
                'comment' => 'np. "PL-DE stala trasa 2026"'])

            // Trasa (moze byc czesciowa - np. tylko kraj)
            ->addColumn('from_country', 'string', ['limit' => 2, 'null' => true])
            ->addColumn('from_postal_code', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('from_city', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('to_country', 'string', ['limit' => 2, 'null' => true])
            ->addColumn('to_postal_code', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('to_city', 'string', ['limit' => 100, 'null' => true])

            // Cenniki
            ->addColumn('price_netto', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => false,
                'comment' => 'Cena netto za trase (jednostkowa)'])
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'PLN'])
            ->addColumn('vat_rate', 'integer', ['limit' => 3, 'null' => true, 'default' => 23])
            ->addColumn('payment_days', 'integer', ['limit' => 3, 'null' => true, 'default' => 30])

            // Rodzaj transportu (opcjonalny filtr)
            ->addColumn('required_vehicle_type', 'string', ['limit' => 20, 'null' => true,
                'comment' => 'plandeka|mega|chlodnia|adr|oversize|any'])

            // Wolumen (do sledzenia realizacji)
            ->addColumn('committed_volume', 'integer', ['null' => true,
                'comment' => 'Ilosc transportow w umowie (roczna/miesieczna)'])
            ->addColumn('volume_period', 'string', ['limit' => 10, 'null' => true, 'default' => 'month',
                'comment' => 'month|quarter|year'])
            ->addColumn('used_volume', 'integer', ['null' => false, 'default' => 0,
                'comment' => 'Ile juz zrealizowano (auto-inc)'])

            // Waznosc
            ->addColumn('valid_from', 'date', ['null' => true])
            ->addColumn('valid_to', 'date', ['null' => true,
                'comment' => 'Kiedy kontrakt wygasa - alert 30 dni przed'])
            ->addColumn('is_active', 'boolean', ['default' => true])

            ->addColumn('notes', 'text', ['null' => true])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['company_id', 'contractor_nip'], ['name' => 'BY_COMPANY_NIP'])
            ->addIndex(['company_id', 'is_active', 'valid_to'], ['name' => 'BY_ACTIVE_VALID'])
            ->addIndex(['from_country', 'to_country'], ['name' => 'BY_ROUTE'])
            ->create();
    }

    public function down(): void
    {
        $this->table('crm_contracts')->drop()->save();
    }
}

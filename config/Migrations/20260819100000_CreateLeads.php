<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * CRM - tabela leadow (potencjalnych klientow).
 *
 * Multi-tenant przez company_id. Lead moze byc podpiety do istniejacego
 * contractors (jesli zostal juz utworzony jako klient) lub istniec samodzielnie.
 *
 * Pipeline stages: new / contact / inquiry / offer / order
 * Skutecznosc: 0-100 (procent), przy zmianie stage auto-preset.
 */
class CreateLeads extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('leads')) return;

        $this->table('leads', [
            'id' => false, 'primary_key' => ['id'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('company_id', 'char', ['limit' => 36, 'null' => false,
                'comment' => 'Multi-tenant - firma ktora prowadzi lead'])

            // Podpiecie do contractors (opcjonalne - lead moze byc "cold" bez rekordu w bazie)
            ->addColumn('contractor_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'FK do contractors gdy lead skonwertowany na klienta'])

            // Dane firmy (z Excela klienta)
            ->addColumn('company_name', 'string', ['limit' => 255, 'null' => false,
                'comment' => 'Nazwa firmy leada'])
            ->addColumn('nip', 'string', ['limit' => 30, 'null' => true,
                'comment' => 'NIP/VAT - klucz do dedup + matchowanie z contractors'])
            ->addColumn('country_code', 'string', ['limit' => 2, 'null' => true,
                'comment' => 'ISO 3166-1 alpha-2 (PL, DE, IT, BE, NL, CZ, ...)'])
            ->addColumn('postal_code', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('street', 'string', ['limit' => 255, 'null' => true])

            // Kontakt
            ->addColumn('contact_person', 'string', ['limit' => 150, 'null' => true,
                'comment' => 'Imie i nazwisko osoby kontaktowej'])
            ->addColumn('contact_role', 'string', ['limit' => 100, 'null' => true,
                'comment' => 'Stanowisko (Dyrektor sprzedazy, Logistyk, ...)'])
            ->addColumn('phone', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('contact_channel', 'string', ['limit' => 30, 'null' => true,
                'comment' => 'Preferowany kanal: phone / email / meeting / any'])

            // Segmentacja
            ->addColumn('branch_type', 'string', ['limit' => 50, 'null' => true,
                'comment' => 'Galaz transportu: road / road_reefer / road_adr / road_oversize / sea / rail / air / intermodal / any'])

            // Pipeline
            ->addColumn('stage', 'string', ['limit' => 20, 'null' => false, 'default' => 'new',
                'comment' => 'new / contact / inquiry / offer / order / lost'])
            ->addColumn('probability', 'integer', ['limit' => 3, 'null' => false, 'default' => 10,
                'comment' => 'Skutecznosc/prawdopodobienstwo 0-100 %'])
            ->addColumn('value_pln', 'decimal', ['precision' => 12, 'scale' => 2, 'null' => true,
                'comment' => 'Szacowana wartosc oferty w PLN (netto)'])
            ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'PLN'])

            // Rownolegle checkboxy z Excela (dla widoku tabelarycznego jak arkusz klienta)
            ->addColumn('flag_contact', 'boolean', ['default' => false,
                'comment' => 'Excel: Kontakt - byl pierwszy kontakt'])
            ->addColumn('flag_inquiry', 'boolean', ['default' => false,
                'comment' => 'Excel: Zapytanie - klient poprosil o wycene'])
            ->addColumn('flag_offer', 'boolean', ['default' => false,
                'comment' => 'Excel: Oferta - wyslana'])
            ->addColumn('flag_order', 'boolean', ['default' => false,
                'comment' => 'Excel: Zlecenie - zaakceptowane'])

            // Assignment
            ->addColumn('assigned_to_user_id', 'char', ['limit' => 36, 'null' => true,
                'comment' => 'FK do users - kto pilnuje leada (handlowiec)'])
            ->addColumn('source', 'string', ['limit' => 50, 'null' => true,
                'comment' => 'Zrodlo leada: manual / import_csv / website / recommendation / cold_call'])

            // Kanban helper (jak invoices Kanban)
            ->addColumn('kanban_pinned', 'boolean', ['default' => false])
            ->addColumn('snooze_until', 'date', ['null' => true,
                'comment' => 'Karta ukryta do tej daty (NULL = aktywna)'])

            // Notatka wewnetrzna (krotka, na widok tabelaryczny)
            ->addColumn('note', 'text', ['null' => true])

            // Timestampy biznesowe
            ->addColumn('next_action_at', 'datetime', ['null' => true,
                'comment' => 'Data nastepnej zaplanowanej akcji (follow-up)'])
            ->addColumn('next_action_description', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('last_contacted_at', 'datetime', ['null' => true])
            ->addColumn('stage_changed_at', 'datetime', ['null' => true,
                'comment' => 'Kiedy karta trafila do aktualnego etapu (do KPI "days in stage")'])
            ->addColumn('lost_reason', 'string', ['limit' => 500, 'null' => true,
                'comment' => 'Powod utraty (gdy stage = lost)'])

            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'])

            ->addIndex(['company_id', 'stage'], ['name' => 'BY_COMPANY_STAGE'])
            ->addIndex(['company_id', 'assigned_to_user_id'], ['name' => 'BY_COMPANY_USER'])
            ->addIndex(['contractor_id'], ['name' => 'BY_CONTRACTOR'])
            ->addIndex(['nip'], ['name' => 'BY_NIP'])
            ->addIndex(['next_action_at'], ['name' => 'BY_NEXT_ACTION'])
            ->create();
    }

    public function down(): void
    {
        $this->table('leads')->drop()->save();
    }
}

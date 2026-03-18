<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * FA(3) KSeF — LOW priority fields (10 items).
 *
 *  1) invoice_new_transports    — NoweSrodkiTransportu — dane pojazdów/łodzi/stat. powietrznych
 *  2) invoice_transaction_conds — WarunkiTransakcji — umowy, zamówienia, transport, Incoterms
 *  3) invoice_order_lines       — Zamówienie — linie zamówienia dla faktur zaliczkowych (ZAL)
 *  4) invoice_charges           — Rozliczenie: Obciążenia + Odliczenia
 *  5) invoices: skonto          — Skonto (WarunkiSkonta + WysokoscSkonta)
 *  6) invoice_contents: kurs_waluty — KursWaluty per-wiersz
 *  7) invoice_factor_banks      — RachunekBankowyFaktora
 *  8) Adres korespondencyjny    — kolumny na invoice_company_details, invoice_contractors
 *  9) invoices: status_info_podatnika — StatusInfoPodatnika
 * 10) invoice_authorized_entities — PodmiotUpowazniony
 */
class AddFa3LowPriorityFields extends BaseMigration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════
        // 1) invoice_new_transports — NoweSrodkiTransportu
        //    Builder already exists, reads from dynamic properties.
        //    This table provides the real DB backing.
        // ═══════════════════════════════════════════════
        if (!$this->hasTable('invoice_new_transports')) {
            $this->table('invoice_new_transports', [
                'id' => false,
                'primary_key' => ['id'],
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('p_22a', 'string', ['limit' => 64, 'null' => true, 'comment' => 'Data dopuszczenia do użytku'])
            ->addColumn('p_nrwierszanst', 'integer', ['null' => true, 'comment' => 'Nr wiersza faktury'])
            ->addColumn('p_22bmk', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Marka'])
            ->addColumn('p_22bmd', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Model'])
            ->addColumn('p_22bk', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Kolor'])
            ->addColumn('p_22bnr', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Nr rejestracyjny'])
            ->addColumn('p_22brp', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Rok produkcji'])
            ->addColumn('p_22b', 'string',  ['limit' => 256, 'null' => true, 'comment' => 'Przebieg (pojazdy lądowe)'])
            ->addColumn('p_22b1', 'string', ['limit' => 256, 'null' => true, 'comment' => 'VIN'])
            ->addColumn('p_22b2', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Nr nadwozia'])
            ->addColumn('p_22b3', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Nr podwozia'])
            ->addColumn('p_22b4', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Nr ramy'])
            ->addColumn('p_22bt', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Typ środka transportu'])
            ->addColumn('p_22c', 'string',  ['limit' => 256, 'null' => true, 'comment' => 'Godziny (jed. pływające)'])
            ->addColumn('p_22c1', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Nr kadłuba'])
            ->addColumn('p_22d', 'string',  ['limit' => 256, 'null' => true, 'comment' => 'Godziny (stat. powietrzne)'])
            ->addColumn('p_22d1', 'string', ['limit' => 256, 'null' => true, 'comment' => 'Nr fabryczny'])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['invoice_id'])
            ->addForeignKey('invoice_id', 'invoices', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
        }

        // Kolumny sterujące na invoices: is_new_transport_wdt, p_42_5
        $inv = $this->table('invoices');
        if (!$inv->hasColumn('is_new_transport_wdt')) {
            $inv->addColumn('is_new_transport_wdt', 'boolean', ['default' => false, 'null' => false, 'after' => 'payment_link', 'comment' => 'WDT nowych środków transportu']);
        }
        if (!$inv->hasColumn('p_42_5')) {
            $inv->addColumn('p_42_5', 'string', ['limit' => 1, 'null' => true, 'default' => null, 'after' => 'is_new_transport_wdt', 'comment' => 'Art. 42 ust. 5 (1/2)']);
        }

        // ═══════════════════════════════════════════════
        // 5) Skonto — WarunkiSkonta + WysokoscSkonta
        // ═══════════════════════════════════════════════
        if (!$inv->hasColumn('skonto_conditions')) {
            $inv->addColumn('skonto_conditions', 'string', ['limit' => 512, 'null' => true, 'default' => null, 'comment' => 'WarunkiSkonta']);
        }
        if (!$inv->hasColumn('skonto_amount')) {
            $inv->addColumn('skonto_amount', 'string', ['limit' => 256, 'null' => true, 'default' => null, 'comment' => 'WysokoscSkonta']);
        }

        // ═══════════════════════════════════════════════
        // 9) StatusInfoPodatnika — 1/2/3/4
        // ═══════════════════════════════════════════════
        if (!$inv->hasColumn('status_info_podatnika')) {
            $inv->addColumn('status_info_podatnika', 'integer', ['null' => true, 'default' => null, 'comment' => 'StatusInfoPodatnika: 1=likwidacja,2=restrukturyzacja,3=upadłość,4=spadek']);
        }

        $inv->update();

        // ═══════════════════════════════════════════════
        // 2) invoice_transaction_conditions — WarunkiTransakcji
        //    Umowy, Zamówienia, NrPartiiTowaru, WarunkiDostawy,
        //    KursUmowny, WalutaUmowna, Transport, PodmiotPosredniczacy
        //    — dość złożona struktura, ale rzadko używana
        //    — przechowujemy jako JSON w 1 kolumnie na invoices (pragmatyczne podejście)
        // ═══════════════════════════════════════════════
        $inv2 = $this->table('invoices');
        if (!$inv2->hasColumn('transaction_conditions_json')) {
            $inv2->addColumn('transaction_conditions_json', 'text', ['null' => true, 'default' => null, 'comment' => 'WarunkiTransakcji — JSON z umowami, zamówieniami, transportem, Incoterms']);
            $inv2->update();
        }

        // ═══════════════════════════════════════════════
        // 3) invoice_order_lines — Zamówienie (ZAL)
        // ═══════════════════════════════════════════════
        if (!$this->hasTable('invoice_order_lines')) {
            $this->table('invoice_order_lines', [
                'id' => false,
                'primary_key' => ['id'],
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('nr_wiersza', 'integer', ['null' => false, 'comment' => 'NrWierszaZam'])
            ->addColumn('uu_id', 'string', ['limit' => 50, 'null' => true, 'comment' => 'UU_IDZ'])
            ->addColumn('name', 'string', ['limit' => 512, 'null' => true, 'comment' => 'P_7Z'])
            ->addColumn('indeks', 'string', ['limit' => 50, 'null' => true, 'comment' => 'IndeksZ'])
            ->addColumn('gtin', 'string', ['limit' => 20, 'null' => true, 'comment' => 'GTINZ'])
            ->addColumn('pkwiu', 'string', ['limit' => 50, 'null' => true, 'comment' => 'PKWiUZ'])
            ->addColumn('cn_code', 'string', ['limit' => 50, 'null' => true, 'comment' => 'CNZ'])
            ->addColumn('pkob', 'string', ['limit' => 50, 'null' => true, 'comment' => 'PKOBZ'])
            ->addColumn('unit', 'string', ['limit' => 256, 'null' => true, 'comment' => 'P_8AZ'])
            ->addColumn('quantity', 'decimal', ['precision' => 22, 'scale' => 6, 'null' => true, 'comment' => 'P_8BZ'])
            ->addColumn('price', 'decimal', ['precision' => 18, 'scale' => 2, 'null' => true, 'comment' => 'P_9AZ'])
            ->addColumn('netto', 'decimal', ['precision' => 18, 'scale' => 2, 'null' => true, 'comment' => 'P_11NettoZ'])
            ->addColumn('vat_amount', 'decimal', ['precision' => 18, 'scale' => 2, 'null' => true, 'comment' => 'P_11VatZ'])
            ->addColumn('vat_rate', 'string', ['limit' => 10, 'null' => true, 'comment' => 'P_12Z stawka'])
            ->addColumn('vat_rate_xii', 'decimal', ['precision' => 6, 'scale' => 2, 'null' => true, 'comment' => 'P_12Z_XII'])
            ->addColumn('is_attachment15', 'boolean', ['default' => false, 'comment' => 'P_12Z_Zal_15'])
            ->addColumn('gtu_code', 'string', ['limit' => 16, 'null' => true, 'comment' => 'GTUZ'])
            ->addColumn('procedure_marking', 'string', ['limit' => 32, 'null' => true, 'comment' => 'ProceduraZ'])
            ->addColumn('excise_amount', 'decimal', ['precision' => 18, 'scale' => 2, 'null' => true, 'comment' => 'KwotaAkcyzyZ'])
            ->addColumn('is_before_correction', 'boolean', ['default' => false, 'comment' => 'StanPrzedZ'])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['invoice_id'])
            ->addForeignKey('invoice_id', 'invoices', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
        }

        // Kolumna WartoscZamowienia na invoices
        $inv3 = $this->table('invoices');
        if (!$inv3->hasColumn('order_total_gross')) {
            $inv3->addColumn('order_total_gross', 'decimal', ['precision' => 18, 'scale' => 2, 'null' => true, 'default' => null, 'comment' => 'WartoscZamowienia (brutto z VAT)']);
            $inv3->update();
        }

        // ═══════════════════════════════════════════════
        // 4) invoice_charges — Rozliczenie (Obciążenia + Odliczenia)
        // ═══════════════════════════════════════════════
        if (!$this->hasTable('invoice_charges')) {
            $this->table('invoice_charges', [
                'id' => false,
                'primary_key' => ['id'],
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('type', 'string', ['limit' => 16, 'null' => false, 'comment' => 'obciazenie / odliczenie'])
            ->addColumn('kwota', 'decimal', ['precision' => 18, 'scale' => 2, 'null' => false])
            ->addColumn('powod', 'string', ['limit' => 512, 'null' => false])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['invoice_id'])
            ->addForeignKey('invoice_id', 'invoices', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
        }

        // ═══════════════════════════════════════════════
        // 6) KursWaluty per-wiersz na invoice_contents
        // ═══════════════════════════════════════════════
        $ic = $this->table('invoice_contents');
        if (!$ic->hasColumn('kurs_waluty')) {
            $ic->addColumn('kurs_waluty', 'decimal', ['precision' => 22, 'scale' => 6, 'null' => true, 'default' => null, 'comment' => 'KursWaluty per-wiersz (dział VI ustawy)']);
            $ic->update();
        }

        // ═══════════════════════════════════════════════
        // 7) invoice_factor_banks — RachunekBankowyFaktora
        // ═══════════════════════════════════════════════
        if (!$this->hasTable('invoice_factor_banks')) {
            $this->table('invoice_factor_banks', [
                'id' => false,
                'primary_key' => ['id'],
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('nr_rb', 'string', ['limit' => 64, 'null' => false, 'comment' => 'NrRB — pełny numer rachunku'])
            ->addColumn('swift', 'string', ['limit' => 11, 'null' => true, 'comment' => 'SWIFT'])
            ->addColumn('rachunek_wlasny_banku', 'string', ['limit' => 10, 'null' => true, 'comment' => 'RachunekWlasnyBanku'])
            ->addColumn('nazwa_banku', 'string', ['limit' => 256, 'null' => true, 'comment' => 'NazwaBanku'])
            ->addColumn('opis_rachunku', 'string', ['limit' => 256, 'null' => true, 'comment' => 'OpisRachunku'])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['invoice_id'])
            ->addForeignKey('invoice_id', 'invoices', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
        }

        // ═══════════════════════════════════════════════
        // 8) Adres korespondencyjny — kolumny na snapshotach
        //    invoice_company_details + invoice_contractors
        //    TAdres: KodKraju, AdresL1, AdresL2, GLN
        // ═══════════════════════════════════════════════
        $icd = $this->table('invoice_company_details');
        foreach (['koresp_country_code', 'koresp_address_l1', 'koresp_address_l2', 'koresp_gln'] as $col) {
            if (!$icd->hasColumn($col)) {
                $limit = $col === 'koresp_country_code' ? 2 : ($col === 'koresp_gln' ? 13 : 256);
                $icd->addColumn($col, 'string', ['limit' => $limit, 'null' => true, 'default' => null]);
            }
        }
        $icd->update();

        $ico = $this->table('invoice_contractors');
        foreach (['koresp_country_code', 'koresp_address_l1', 'koresp_address_l2', 'koresp_gln'] as $col) {
            if (!$ico->hasColumn($col)) {
                $limit = $col === 'koresp_country_code' ? 2 : ($col === 'koresp_gln' ? 13 : 256);
                $ico->addColumn($col, 'string', ['limit' => $limit, 'null' => true, 'default' => null]);
            }
        }
        $ico->update();

        // ═══════════════════════════════════════════════
        // 10) invoice_authorized_entities — PodmiotUpowazniony
        // ═══════════════════════════════════════════════
        if (!$this->hasTable('invoice_authorized_entities')) {
            $this->table('invoice_authorized_entities', [
                'id' => false,
                'primary_key' => ['id'],
                'collation' => 'utf8mb4_unicode_ci',
            ])
            ->addColumn('id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('invoice_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('nr_eori', 'string', ['limit' => 256, 'null' => true])
            ->addColumn('nip', 'string', ['limit' => 32, 'null' => true, 'comment' => 'NIP / IdWew — DaneIdentyfikacyjne'])
            ->addColumn('name', 'string', ['limit' => 512, 'null' => true, 'comment' => 'Nazwa / ImieNazwisko'])
            ->addColumn('country_code', 'string', ['limit' => 2, 'null' => true, 'default' => 'PL'])
            ->addColumn('address_l1', 'string', ['limit' => 256, 'null' => true])
            ->addColumn('address_l2', 'string', ['limit' => 256, 'null' => true])
            ->addColumn('gln', 'string', ['limit' => 13, 'null' => true])
            ->addColumn('koresp_country_code', 'string', ['limit' => 2, 'null' => true])
            ->addColumn('koresp_address_l1', 'string', ['limit' => 256, 'null' => true])
            ->addColumn('koresp_address_l2', 'string', ['limit' => 256, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 256, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 32, 'null' => true])
            ->addColumn('rola', 'integer', ['null' => false, 'comment' => 'RolaPU: 1=organ egzekucyjny, 2=komornik sądowy, 3=przedstawiciel podatkowy'])
            ->addColumn('created', 'datetime', ['null' => true])
            ->addColumn('modified', 'datetime', ['null' => true])
            ->addIndex(['invoice_id'])
            ->addForeignKey('invoice_id', 'invoices', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
        }
    }

    public function down(): void
    {
        // Tabele
        foreach (['invoice_authorized_entities', 'invoice_factor_banks', 'invoice_charges', 'invoice_order_lines', 'invoice_new_transports'] as $tbl) {
            if ($this->hasTable($tbl)) {
                $this->table($tbl)->drop()->save();
            }
        }

        // Kolumny na invoices
        $inv = $this->table('invoices');
        foreach (['is_new_transport_wdt', 'p_42_5', 'skonto_conditions', 'skonto_amount', 'status_info_podatnika', 'transaction_conditions_json', 'order_total_gross'] as $col) {
            if ($inv->hasColumn($col)) {
                $inv->removeColumn($col);
            }
        }
        $inv->update();

        // Kolumna na invoice_contents
        $ic = $this->table('invoice_contents');
        if ($ic->hasColumn('kurs_waluty')) {
            $ic->removeColumn('kurs_waluty');
            $ic->update();
        }

        // Kolumny adresu korespondencyjnego
        foreach (['invoice_company_details', 'invoice_contractors'] as $tbl) {
            $t = $this->table($tbl);
            foreach (['koresp_country_code', 'koresp_address_l1', 'koresp_address_l2', 'koresp_gln'] as $col) {
                if ($t->hasColumn($col)) {
                    $t->removeColumn($col);
                }
            }
            $t->update();
        }
    }
}

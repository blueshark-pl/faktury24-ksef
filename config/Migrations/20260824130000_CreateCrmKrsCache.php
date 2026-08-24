<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Cache pelnych wypisow KRS (API MS-KRS).
 *
 * Klucz PK to KRS. Dodatkowo indeksujemy po NIP dla lookup NIP -> KRS.
 * TTL 30 dni - potem refetch przy nastepnym lookup.
 *
 * Response JSON zapisujemy calosciowo (raw) w danych, a wyciagniete kluczowe
 * pola cache-ujemy w kolumnach dla szybkiego SQL query bez parsowania JSON.
 */
class CreateCrmKrsCache extends BaseMigration
{
    public function up(): void
    {
        if ($this->hasTable('crm_krs_cache')) return;

        $this->table('crm_krs_cache', [
            'id' => false, 'primary_key' => ['krs'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('krs', 'string', ['limit' => 10, 'null' => false,
                'comment' => 'Numer KRS - 10 cyfr z leading zeros'])
            ->addColumn('nip', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('regon', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('nazwa', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('forma_prawna', 'string', ['limit' => 100, 'null' => true,
                'comment' => 'SPOLKA Z OGRANICZONA ODPOWIEDZIALNOSCIA / SPOLKA AKCYJNA / SPOLKA JAWNA / SP. Z O.O. SPOLKA KOMANDYTOWA'])
            ->addColumn('kod_pocztowy', 'string', ['limit' => 10, 'null' => true])
            ->addColumn('miejscowosc', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('ulica', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('nr_domu', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('nr_lokalu', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('kraj', 'string', ['limit' => 50, 'null' => true, 'default' => 'POLSKA'])
            ->addColumn('kapital_zakladowy', 'decimal', ['precision' => 15, 'scale' => 2, 'null' => true])
            ->addColumn('waluta_kapitalu', 'string', ['limit' => 3, 'null' => true, 'default' => 'PLN'])
            ->addColumn('data_wpisu', 'date', ['null' => true,
                'comment' => 'Data pierwszego wpisu KRS - wskazuje wiek firmy'])
            ->addColumn('data_zakonczenia', 'date', ['null' => true,
                'comment' => 'Data zakonczenia dzialalnosci (rzadko wypelniona)'])
            ->addColumn('status_dzialajaca', 'boolean', ['default' => true,
                'comment' => 'False jesli w postepowaniu upadlosciowym lub zakonczona'])
            ->addColumn('pkd_glowne_kod', 'string', ['limit' => 10, 'null' => true,
                'comment' => 'Kod PKD glownej dzialalnosci (np. 49.41.Z transport drogowy towarow)'])
            ->addColumn('pkd_glowne_opis', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('pkd_wszystkie_json', 'text', ['null' => true,
                'comment' => 'Wszystkie kody PKD jako JSON array [{kod, opis}]'])
            ->addColumn('reprezentacja_json', 'text', ['null' => true,
                'comment' => 'Sposob reprezentacji + lista czlonkow zarzadu JSON'])
            ->addColumn('wspolnicy_json', 'text', ['null' => true,
                'comment' => 'Wspolnicy dla sp. z o.o. JSON [{imie, nazwisko, udzialy}]'])
            ->addColumn('raw_json', 'text', ['null' => true,
                'limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM,
                'comment' => 'Pelen response z MS-KRS API dla debugowania (MEDIUMTEXT do 16MB)'])
            ->addColumn('fetched_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('fetch_error', 'text', ['null' => true])
            ->addIndex(['nip'], ['name' => 'BY_NIP'])
            ->addIndex(['nazwa'], ['name' => 'BY_NAZWA'])
            ->create();
    }

    public function down(): void
    {
        $this->table('crm_krs_cache')->drop()->save();
    }
}

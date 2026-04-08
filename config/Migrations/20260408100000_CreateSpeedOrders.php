<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Tabela zleceń importowanych z systemu Speed ERP.
 * Kluczowe pola biznesowe + pełny JSON dla pól dodatkowych.
 */
class CreateSpeedOrders extends AbstractMigration
{
    public function change(): void
    {
        $this->table('speed_orders')
            // Klucz obcy z Speed
            ->addColumn('speed_id',          'integer',  ['null' => false, 'signed' => false, 'comment' => 'GLO_ID z Speed ERP'])

            // Firma (wystawiający)
            ->addColumn('company_nip',        'string',  ['limit' => 30,  'null' => true])
            ->addColumn('company_name',       'string',  ['limit' => 255, 'null' => true])

            // Numer / identyfikacja
            ->addColumn('symbol',             'string',  ['limit' => 40,  'null' => true,  'comment' => 'GLO_SYMBOL np. 0099/04/2026'])
            ->addColumn('ozn',                'string',  ['limit' => 10,  'null' => true,  'comment' => 'GLO_OZN typ dokumentu np. ZT'])
            ->addColumn('numer',              'integer', ['null' => true,  'signed' => false])
            ->addColumn('rok',                'string',  ['limit' => 4,   'null' => true])
            ->addColumn('mc',                 'string',  ['limit' => 2,   'null' => true])
            ->addColumn('teczka',             'string',  ['limit' => 80,  'null' => true,  'comment' => 'opiekun/folder'])

            // Status
            ->addColumn('status',             'tinyinteger', ['default' => 1, 'null' => false, 'comment' => '1=aktywne, 0=zamknięte'])

            // Odbiorca (nabywca)
            ->addColumn('buyer_speed_id',     'integer',  ['null' => true, 'signed' => false, 'comment' => 'GLO_ODB_ID'])
            ->addColumn('buyer_nip',          'string',  ['limit' => 30,  'null' => true])
            ->addColumn('buyer_name',         'string',  ['limit' => 255, 'null' => true])
            ->addColumn('buyer_street',       'string',  ['limit' => 255, 'null' => true])
            ->addColumn('buyer_postal_code',  'string',  ['limit' => 20,  'null' => true])
            ->addColumn('buyer_city',         'string',  ['limit' => 120, 'null' => true])
            ->addColumn('buyer_country',      'string',  ['limit' => 5,   'null' => true])
            ->addColumn('buyer_email',        'string',  ['limit' => 180, 'null' => true])

            // Miejsce załadunku / rozładunku
            ->addColumn('place_from_name',    'string',  ['limit' => 255, 'null' => true, 'comment' => 'GLO_MIE_NAZWA1'])
            ->addColumn('place_from_country', 'string',  ['limit' => 5,   'null' => true])
            ->addColumn('place_to_name',      'string',  ['limit' => 255, 'null' => true, 'comment' => 'GLO_MIE_NAZWA2'])
            ->addColumn('place_to_country',   'string',  ['limit' => 5,   'null' => true])
            ->addColumn('route_description',  'string',  ['limit' => 500, 'null' => true, 'comment' => 'GLO_NAZ9 trasa'])

            // Tytuł / opis
            ->addColumn('title1',             'string',  ['limit' => 255, 'null' => true, 'comment' => 'GLO_TYT1 np. ES-975377'])
            ->addColumn('title2',             'string',  ['limit' => 255, 'null' => true, 'comment' => 'GLO_TYT2 np. TOWAR PALETOWY'])
            ->addColumn('cargo_type',         'string',  ['limit' => 120, 'null' => true, 'comment' => 'GLO_NAZ10 typ frachtu'])
            ->addColumn('notes',              'text',    ['null' => true,  'comment' => 'GLO_UWAGI'])

            // Daty
            ->addColumn('date_doc',           'date',    ['null' => true,  'comment' => 'GLO_DATA_DOK data dokumentu'])
            ->addColumn('date_ship',          'datetime',['null' => true,  'comment' => 'GLO_DATA_WYS planowana wysyłka'])
            ->addColumn('date_deadline',      'datetime',['null' => true,  'comment' => 'GLO_DATA_TER termin'])
            ->addColumn('date_delivery',      'datetime',['null' => true,  'comment' => 'GLO_DATA_ZAK planowane zakończenie'])

            // Finansowe
            ->addColumn('currency',           'string',  ['limit' => 5,   'default' => 'PLN', 'null' => false])
            ->addColumn('netto',              'decimal', ['precision' => 14, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('vat',                'decimal', ['precision' => 14, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('brutto',             'decimal', ['precision' => 14, 'scale' => 2, 'default' => '0.00', 'null' => false])
            ->addColumn('exchange_rate',      'decimal', ['precision' => 12, 'scale' => 6, 'default' => '1.000000', 'null' => true])
            ->addColumn('exchange_table',     'string',  ['limit' => 120, 'null' => true])

            // Osoba / nick
            ->addColumn('nick_created',       'string',  ['limit' => 60,  'null' => true, 'comment' => 'GLO_NICK_WYS'])
            ->addColumn('nick_modified',      'string',  ['limit' => 60,  'null' => true, 'comment' => 'GLO_NICK_ZMI'])

            // Pozostałe pola jako JSON (pełna kopia rekordu Speed)
            ->addColumn('raw_json',           'text',    ['null' => true,  'comment' => 'pełny rekord JSON z Speed ERP'])

            // Metadane importu
            ->addColumn('imported_at',        'datetime',['null' => true])
            ->addColumn('speed_modified_at',  'datetime',['null' => true, 'comment' => 'GLO_DATA_ZMI z Speed'])

            // CakePHP timestamps
            ->addColumn('created',            'datetime',['null' => false])
            ->addColumn('modified',           'datetime',['null' => false])

            ->addIndex(['speed_id'], ['unique' => true])
            ->addIndex(['symbol'])
            ->addIndex(['buyer_nip'])
            ->addIndex(['date_doc'])
            ->addIndex(['status'])
            ->create();
    }
}

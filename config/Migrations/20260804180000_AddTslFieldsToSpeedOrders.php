<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * TSL (Transport-Spedycja-Logistyka) pola realnej spedycji:
 *  - Time windows za\/roz\ (magazyny maja sztywne godziny)
 *  - payment_days + auto payment_due_date (standard TSL - liczba dni od issue_date)
 *  - required_vehicle_type + walidacja cargo_weight vs DMC
 *  - Palety wymienne EUR/EPAL + termin zwrotu dokumentow
 *  - Kontakty na miejscu (osobno za\/roz\) - kluczowe dla kierowcy
 *  - driver_instructions - osobne pole dla kierowcy (nie mieszane z notes)
 */
class AddTslFieldsToSpeedOrders extends BaseMigration
{
    public function up(): void
    {
        $t = $this->table('speed_orders');

        // Time windows dla zaladunku i rozladunku
        $t->addColumn('load_time_from', 'time', [
                'null' => true, 'after' => 'load_lng',
                'comment' => 'Okno zaladunku - od (godzina)',
            ])
            ->addColumn('load_time_to', 'time', [
                'null' => true, 'after' => 'load_time_from',
                'comment' => 'Okno zaladunku - do (godzina)',
            ])
            ->addColumn('unload_time_from', 'time', [
                'null' => true, 'after' => 'unload_lng',
                'comment' => 'Okno rozladunku - od (godzina)',
            ])
            ->addColumn('unload_time_to', 'time', [
                'null' => true, 'after' => 'unload_time_from',
                'comment' => 'Okno rozladunku - do (godzina)',
            ]);

        // Payment terms - dni + auto data
        $t->addColumn('payment_days', 'integer', [
                'null' => true, 'signed' => false, 'after' => 'payment_terms',
                'comment' => 'Liczba dni platnosci od issue_date (standard TSL, np. 30/45/60)',
            ])
            ->addColumn('payment_due_date', 'date', [
                'null' => true, 'after' => 'payment_days',
                'comment' => 'Auto-wyliczona data platnosci = date_doc + payment_days',
            ]);

        // Wymagany typ pojazdu
        $t->addColumn('required_vehicle_type', 'string', [
                'limit' => 30, 'null' => true, 'after' => 'transport_type',
                'comment' => 'plandeka / mega / chlodnia / cysterna / wywrotka / bus / kontener / oversize',
            ]);

        // Palety wymienne + termin zwrotu dokumentow
        $t->addColumn('pallets_exchange', 'boolean', [
                'null' => false, 'default' => false, 'after' => 'cargo_pallet_type',
                'comment' => 'Palety wymienne (EUR/EPAL)',
            ])
            ->addColumn('pallets_exchange_count', 'integer', [
                'null' => true, 'signed' => false, 'after' => 'pallets_exchange',
                'comment' => 'Ilosc palet do wymiany',
            ])
            ->addColumn('docs_return_days', 'integer', [
                'null' => true, 'signed' => false, 'after' => 'cmr_number',
                'comment' => 'Termin zwrotu dokumentow CMR/WZ (dni od rozladunku)',
            ]);

        // Kontakty na miejscu - zaladunek
        $t->addColumn('load_contact_name', 'string', [
                'limit' => 120, 'null' => true, 'after' => 'load_time_to',
                'comment' => 'Osoba do kontaktu na zaladunku',
            ])
            ->addColumn('load_contact_phone', 'string', [
                'limit' => 40, 'null' => true, 'after' => 'load_contact_name',
            ])
            ->addColumn('load_contact_email', 'string', [
                'limit' => 180, 'null' => true, 'after' => 'load_contact_phone',
            ]);

        // Kontakty na miejscu - rozladunek
        $t->addColumn('unload_contact_name', 'string', [
                'limit' => 120, 'null' => true, 'after' => 'unload_time_to',
                'comment' => 'Osoba do kontaktu na rozladunku',
            ])
            ->addColumn('unload_contact_phone', 'string', [
                'limit' => 40, 'null' => true, 'after' => 'unload_contact_name',
            ])
            ->addColumn('unload_contact_email', 'string', [
                'limit' => 180, 'null' => true, 'after' => 'unload_contact_phone',
            ]);

        // Osobne instrukcje dla kierowcy (nie mieszane z ogolnymi notes)
        $t->addColumn('driver_instructions', 'text', [
                'null' => true, 'after' => 'notes',
                'comment' => 'Instrukcje dla kierowcy: kod bramy, wjazd, gdzie parkowac, EPI, itp.',
            ]);

        $t->addIndex(['payment_due_date'], ['name' => 'BY_PAYMENT_DUE'])
          ->addIndex(['required_vehicle_type'], ['name' => 'BY_REQ_VEHICLE_TYPE']);

        $t->update();
    }

    public function down(): void
    {
        $t = $this->table('speed_orders');
        $t->removeIndexByName('BY_REQ_VEHICLE_TYPE')
          ->removeIndexByName('BY_PAYMENT_DUE')
          ->removeColumn('driver_instructions')
          ->removeColumn('unload_contact_email')
          ->removeColumn('unload_contact_phone')
          ->removeColumn('unload_contact_name')
          ->removeColumn('load_contact_email')
          ->removeColumn('load_contact_phone')
          ->removeColumn('load_contact_name')
          ->removeColumn('docs_return_days')
          ->removeColumn('pallets_exchange_count')
          ->removeColumn('pallets_exchange')
          ->removeColumn('required_vehicle_type')
          ->removeColumn('payment_due_date')
          ->removeColumn('payment_days')
          ->removeColumn('unload_time_to')
          ->removeColumn('unload_time_from')
          ->removeColumn('load_time_to')
          ->removeColumn('load_time_from')
          ->update();
    }
}

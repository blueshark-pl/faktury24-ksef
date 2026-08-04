<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Rozszerzenie speed_orders o pola spedycyjne (ladunek + logistyka).
 * Pola opcjonalne, wypelniane per zlecenie w formularzu.
 */
class AddCargoFieldsToSpeedOrders extends BaseMigration
{
    public function up(): void
    {
        $t = $this->table('speed_orders');

        // Wymiary/waga ladunku
        $t->addColumn('cargo_weight_kg', 'integer', [
                'null' => true, 'signed' => false, 'after' => 'cargo_type',
                'comment' => 'Waga ladunku (kg)',
            ])
            ->addColumn('cargo_volume_m3', 'decimal', [
                'precision' => 8, 'scale' => 2, 'null' => true, 'after' => 'cargo_weight_kg',
                'comment' => 'Objetosc ladunku (m3)',
            ])
            ->addColumn('cargo_ldm', 'decimal', [
                'precision' => 5, 'scale' => 2, 'null' => true, 'after' => 'cargo_volume_m3',
                'comment' => 'Loading meters (LDM)',
            ])
            ->addColumn('cargo_pallets', 'integer', [
                'null' => true, 'signed' => false, 'after' => 'cargo_ldm',
                'comment' => 'Ilosc palet',
            ])
            ->addColumn('cargo_pallet_type', 'string', [
                'limit' => 20, 'null' => true, 'after' => 'cargo_pallets',
                'comment' => 'EUR / PLA / BOX / inne',
            ]);

        // ADR + temperatura chlodni
        $t->addColumn('adr_class', 'string', [
                'limit' => 10, 'null' => true, 'after' => 'cargo_pallet_type',
                'comment' => 'ADR klasa: 1-9 lub null dla non-ADR',
            ])
            ->addColumn('adr_un', 'string', [
                'limit' => 10, 'null' => true, 'after' => 'adr_class',
                'comment' => 'ADR UN number np. UN1230',
            ])
            ->addColumn('temperature_min', 'decimal', [
                'precision' => 4, 'scale' => 1, 'null' => true, 'after' => 'adr_un',
                'comment' => 'Min. temperatura chlodni w C',
            ])
            ->addColumn('temperature_max', 'decimal', [
                'precision' => 4, 'scale' => 1, 'null' => true, 'after' => 'temperature_min',
                'comment' => 'Max. temperatura chlodni w C',
            ]);

        // INCOTERMS + CMR
        $t->addColumn('incoterms', 'string', [
                'limit' => 10, 'null' => true, 'after' => 'temperature_max',
                'comment' => 'EXW / FCA / CPT / DAP / DDP itd.',
            ])
            ->addColumn('incoterms_place', 'string', [
                'limit' => 100, 'null' => true, 'after' => 'incoterms',
                'comment' => 'Miejsce dla INCOTERMS (np. DAP Hamburg)',
            ])
            ->addColumn('cmr_number', 'string', [
                'limit' => 50, 'null' => true, 'after' => 'incoterms_place',
                'comment' => 'Numer listu przewozowego CMR',
            ]);

        // Ubezpieczenie
        $t->addColumn('insurance_value', 'decimal', [
                'precision' => 12, 'scale' => 2, 'null' => true, 'after' => 'cmr_number',
                'comment' => 'Wartosc ubezpieczenia ladunku',
            ])
            ->addColumn('insurance_currency', 'string', [
                'limit' => 5, 'null' => true, 'after' => 'insurance_value',
                'comment' => 'Waluta ubezpieczenia',
            ]);

        $t->addIndex(['adr_class'], ['name' => 'BY_ADR_CLASS'])
          ->addIndex(['cmr_number'], ['name' => 'BY_CMR_NUMBER']);

        $t->update();
    }

    public function down(): void
    {
        $t = $this->table('speed_orders');
        $t->removeIndexByName('BY_CMR_NUMBER')
          ->removeIndexByName('BY_ADR_CLASS')
          ->removeColumn('insurance_currency')
          ->removeColumn('insurance_value')
          ->removeColumn('cmr_number')
          ->removeColumn('incoterms_place')
          ->removeColumn('incoterms')
          ->removeColumn('temperature_max')
          ->removeColumn('temperature_min')
          ->removeColumn('adr_un')
          ->removeColumn('adr_class')
          ->removeColumn('cargo_pallet_type')
          ->removeColumn('cargo_pallets')
          ->removeColumn('cargo_ldm')
          ->removeColumn('cargo_volume_m3')
          ->removeColumn('cargo_weight_kg')
          ->update();
    }
}

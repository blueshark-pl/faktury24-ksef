<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Seed najpopularniejszych palet:
 *  - Standardowe EU (EUR/EPAL, industrial)
 *  - TOSCA pooling (H1, L1, CR3, COMBO 285 seria)
 *  - Inne poolingi (CHEP, IFCO, IPP)
 *
 * Wszystkie z company_id = NULL (globalny katalog widoczny wszystkim firmom).
 * Firma moze dodac swoje custom przez /admin/palety.
 */
class SeedPalletTypes extends BaseMigration
{
    public function up(): void
    {
        $rows = [
            // === EUR / EPAL - standardowe europejskie ===
            ['EUR',    'EUR pallet (EPAL 1) standard',       'EPAL', 1200, 800, 144, 25, 1500, 'wood',    'natural', false, 'Standardowa paleta europejska EUR/EPAL 1200x800 mm. Najczestsza w Europie.', null],
            ['EPAL-2', 'EPAL 2 (przemyslowa)',              'EPAL', 1200, 1000, 162, 35, 1500, 'wood',    'natural', false, 'Paleta przemyslowa 1200x1000 mm, mocniejsza wersja EUR.', null],
            ['EPAL-3', 'EPAL 3 (Dusseldorfer)',             'EPAL', 1000, 800, 144, 20, 1000, 'wood',    'natural', false, 'Paleta Dusseldorfer 1000x800 mm.', null],
            ['EPAL-6', 'EPAL 6 (Dusseldorfer 1/2)',         'EPAL', 800, 600, 144, 9, 500,    'wood',    'natural', false, 'Polowa palety EUR - 800x600 mm.', null],
            ['DISP',   'Paleta jednorazowa (dispensa)',      null,   1200, 800, 144, 15, 800, 'wood',    'natural', false, 'Paleta jednorazowa, lekka, tania.', null],

            // === TOSCA - pooling polymer ===
            ['H1',           'TOSCA H1 800x1200 (Hygienic)',                'TOSCA', 1200, 800, 160, 20, 1250, 'plastic', 'niebieski', true,  'Paleta higieniczna do produktow swiezych. HDPE, latwe czyszczenie, brak drzazg.', 'https://www.toscaltd.com/'],
            ['L1',           'TOSCA L1 800x1200 (Logistics 1)',             'TOSCA', 1200, 800, 160, 21, 1500, 'plastic', 'niebieski', true,  'Najczestsza paleta TOSCA - logistics. Universal use.', 'https://www.toscaltd.com/'],
            ['L2',           'TOSCA L2 1000x1200 (Logistics 2)',            'TOSCA', 1200, 1000, 160, 26, 1500, 'plastic', 'niebieski', true,  'Paleta przemyslowa TOSCA 1200x1000 mm.', 'https://www.toscaltd.com/'],
            ['CR3',          'TOSCA CR3 1000x1200 (Combi Retail 3)',        'TOSCA', 1200, 1000, 160, 28, 1500, 'plastic', 'niebieski', true,  'Combi Retail 3 - dla FMCG / retail. Wysoka wytrzymalosc.', 'https://www.toscaltd.com/'],
            ['CR4',          'TOSCA CR4 800x1200 (Combi Retail 4)',         'TOSCA', 1200, 800, 160, 22, 1250, 'plastic', 'niebieski', true,  'Combi Retail 4 - manutensione retail.', 'https://www.toscaltd.com/'],
            ['COMBO-285-BD-5R',   'COMBO 285 BD 5R (BeverageDisplay)',     'TOSCA', 1200, 800, 285, 18, 500,  'plastic', 'czarny',    true,  'BeverageDisplay - do napojow. Height 285mm, 5R = design.', 'https://www.toscaltd.com/'],
            ['COMBO-285-BDDD-3R', 'COMBO 285 BD/DD 3R (BeverageDisplay/DD)','TOSCA', 1200, 800, 285, 18, 500,  'plastic', 'czarny',    true,  'BeverageDisplay + DD 3R.', 'https://www.toscaltd.com/'],
            ['COMBO-285-LID',     'COMBO 285 LID',                          'TOSCA', 1200, 800, 30,  4,  100, 'plastic', 'czarny',    true,  'Pokrywa (LID) do systemu COMBO 285.', 'https://www.toscaltd.com/'],
            ['TOSCA-INSERT',      'TOSCA Insert (wkladka)',                  'TOSCA', null, null, null, null, null, 'plastic', 'czarny',    true,  'Wkladka do palet TOSCA - stabilizator', 'https://www.toscaltd.com/'],

            // === CHEP ===
            ['CHEP-B12',    'CHEP B1210 (EUR)',              'CHEP', 1200, 800, 138, 24, 1250, 'wood',    'niebieski', true, 'CHEP niebieska EUR 1200x800.', 'https://www.chep.com/'],
            ['CHEP-A12',    'CHEP A1210 (przemyslowa)',      'CHEP', 1200, 1000, 138, 26, 1500, 'wood',   'niebieski', true, 'CHEP przemyslowa 1200x1000.', 'https://www.chep.com/'],

            // === IFCO / IPP ===
            ['IFCO-6410',   'IFCO 6410 (RPC)',                'IFCO', 600, 400, 199, 1.9, 30, 'plastic', 'zielony',   true, 'Reusable Plastic Container do warzyw/owocow.', 'https://www.ifco.com/'],
            ['IFCO-6416',   'IFCO 6416 (RPC deep)',           'IFCO', 600, 400, 165, 1.6, 25, 'plastic', 'zielony',   true, 'RPC glebszy do FMCG.', 'https://www.ifco.com/'],

            // === Custom / inne ===
            ['CARDBOARD',   'Karton kwadratowy',              null, null, null, null, null, null,  'cardboard', 'natural', false, 'Karton bez konkretnych wymiarow.', null],
            ['MIX',         'Mieszane / inne',                null, null, null, null, null, null,  null,        null,      false, 'Ladunek mieszany bez konkretnego typu palety.', null],
        ];

        $sortOrder = 10;
        $now = date('Y-m-d H:i:s');
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'id'               => \Cake\Utility\Text::uuid(),
                'company_id'       => null,
                'code'             => $r[0],
                'name'             => $r[1],
                'manufacturer'     => $r[2],
                'length_mm'        => $r[3],
                'width_mm'         => $r[4],
                'height_mm'        => $r[5],
                'weight_empty_kg'  => $r[6],
                'load_capacity_kg' => $r[7],
                'material'         => $r[8],
                'color'            => $r[9],
                'is_pooling'       => $r[10] ? 1 : 0,
                'description'      => $r[11],
                'external_url'     => $r[12],
                'is_active'        => 1,
                'sort_order'       => $sortOrder,
                'created'          => $now,
                'modified'         => $now,
            ];
            $sortOrder += 10;
        }
        $this->table('pallet_types')->insert($data)->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM pallet_types WHERE company_id IS NULL");
    }
}

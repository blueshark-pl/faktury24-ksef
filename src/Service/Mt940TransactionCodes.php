<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Słownik kodów transakcji MT940 dla pola :86: / :61:.
 *
 * Źródło: oficjalna dokumentacja mBank "MT940 - wyciągi dzienne i miesięczne
 * w części detalicznej mBanku" (PDF).
 *
 * Format kodu mBank: PREFIX (1 litera) + 3-cyfrowy kod operacji.
 *  - N = przelew zwykły (normal)
 *  - S = zlecenie stałe / wpłata własna
 *  - F = przelew przyszły / inne
 *  - G = operacja grupowa
 *  - W = operacja walutowa
 *  - C = BLIK / karty
 *
 * Inne banki (PKO, Santander) używają 3-znakowych kodów typu SWIFT
 * (A61, D50, N94 itd.) — także obsługiwane.
 */
final class Mt940TransactionCodes
{
    /**
     * Kategoria po pierwszej literze prefiksu.
     */
    private const PREFIX_LABELS = [
        'N' => 'przelew zwykły',
        'S' => 'zlecenie stałe / wpłata własna',
        'F' => 'przelew przyszły / inny',
        'G' => 'operacja grupowa',
        'W' => 'operacja walutowa',
        'C' => 'BLIK / karty',
        'A' => 'korekta / księgowanie administracyjne',
        'D' => 'obciążenie',
        'Z' => 'storno / zwrot',
    ];

    /**
     * 3-cyfrowe kody mBank z PDF (najczęściej spotykane w obrocie firmowym).
     * Pełna lista to ~500 pozycji — wybrałem te które realnie pojawiają się
     * w wyciągach z fakturami.
     */
    private const NUMERIC_CODES = [
        // Wpłaty / wypłaty gotówkowe
        '100' => 'Wpłata (wrzutnia)',
        '113' => 'Wypłata w bankomacie',
        '234' => 'Wpłata gotówki w mBanku',
        '235' => 'Wpłata gotówki w mBanku',
        '236' => 'Wpłata gotówki w mBanku',
        '237' => 'Wpłata gotówki w mBanku',
        '238' => 'Wpłata gotówki w mBanku',
        '239' => 'Wypłata gotówki w mBanku',
        '240' => 'Wypłata gotówki w mBanku',
        '241' => 'Wypłata gotówki w mBanku',
        '242' => 'Wypłata gotówki w mBanku',
        '243' => 'Wypłata gotówki w mBanku',
        '633' => 'Wpłata PB',
        '634' => 'Wypłata PB',

        // Karty
        '105' => 'Wypłata kartą w oddziale',
        '114' => 'Zakup przy użyciu karty',
        '117' => 'Zwrot środków / anulacja zakupu kartą',
        '121' => 'POS — zwrot towaru',
        '123' => 'POS — anulacja zwrotu towaru',
        '124' => 'Zakup kartą z wypłatą gotówki',
        '125' => 'Anulacja zakupu kartą z wypłatą',
        '265' => 'Przelew na kartę kredytową',
        '266' => 'Przelew z karty kredytowej',
        '288' => 'Przelew przychodzący MoneySend',
        '588' => 'Ręczna spłata karty kredytowej',
        '589' => 'Automatyczna spłata karty',
        '590' => 'Spłata karty kredytowej',

        // Przelewy zewnętrzne
        '150' => 'Przelew zewnętrzny przychodzący',
        '152' => 'Przelew zewnętrzny wychodzący',
        '176' => 'Przelew zewnętrzny wychodzący',
        '193' => 'Przelew zewnętrzny wychodzący',
        '297' => 'Przelew zewnętrzny wychodzący',
        '300' => 'Przelew zewnętrzny wychodzący',
        '305' => 'Przelew zewnętrzny wychodzący',
        '306' => 'Przelew zewnętrzny wychodzący',
        '309' => 'Przelew zewnętrzny wychodzący',
        '323' => 'Przelew zewnętrzny przychodzący',
        '324' => 'Przelew zewnętrzny wychodzący',
        '327' => 'Przelew zewnętrzny wychodzący',
        '338' => 'Przelew zewnętrzny wychodzący',
        '350' => 'Przelew zewnętrzny wychodzący',
        '356' => 'Przelew zewnętrzny wychodzący',
        '358' => 'Przelew zewnętrzny wychodzący',
        '370' => 'Przelew zewnętrzny wychodzący',
        '371' => 'Przelew zewnętrzny wychodzący',
        '500' => 'Przelew zewnętrzny wychodzący',

        // Przelewy wewnętrzne
        '160' => 'Przelew wewnętrzny przychodzący',
        '163' => 'Przelew wewnętrzny z rachunku (automat)',
        '164' => 'Przelew wewnętrzny na rachunek (automat)',
        '169' => 'Przelew wewnętrzny wychodzący',
        '198' => 'Przelew z rachunku technicznego',
        '295' => 'Przelew własny',
        '303' => 'Przelew wewnętrzny wychodzący',
        '460' => 'Przelew wewnętrzny przychodzący (mDM)',
        '469' => 'Przelew wewnętrzny wychodzący (mDM)',

        // Przelewy ekspresowe / SORBNET
        '220' => 'Przelew ekspresowy',
        '296' => 'Przelew expressowy — przychodzący',
        '377' => 'Przelew SORBNET wychodzący',
        '380' => 'Przelew SORBNET przychodzący',

        // mBank — przelewy międzykrajowe
        '330' => 'Przelew do mBanku PL',
        '331' => 'Przelew przychodzący z mBanku PL',
        '332' => 'Przelew do mBanku CZ',
        '333' => 'Przelew przychodzący z mBanku CZ',
        '334' => 'Przelew do mBanku SK',
        '335' => 'Przelew przychodzący z mBanku SK',
        '336' => 'Przelew do mBanku UK',
        '337' => 'Przelew przychodzący z mBanku UK',

        // mTransfer / Płacimierz
        '261' => 'Przelew mTransfer wychodzący',
        '262' => 'Przelew mTransfer przychodzący',
        '304' => 'Przelew mTransfer wychodzący',
        '396' => 'Płacimierz — przelew przychodzący wewnętrzny',
        '397' => 'Płacimierz — przelew wychodzący wewnętrzny',
        '398' => 'Płacimierz — przelew wychodzący zewnętrzny',

        // Walutowe
        '221' => 'Przelew walutowy wychodzący',
        '222' => 'Przelew walutowy przychodzący',
        '223' => 'Przelew walutowy przychodzący — korekta',
        '224' => 'Przelew walutowy wychodzący — korekta',
        '281' => 'Przelew wewnętrzny walutowy wychodzący (FX)',
        '282' => 'Przelew wewnętrzny walutowy przychodzący (FX)',
        '440' => 'Przelew walutowy przychodzący',
        '441' => 'Przelew walutowy wychodzący',
        '442' => 'Przelew walutowy wychodzący',
        '443' => 'Przelew walutowy przychodzący',

        // SEPA
        '340' => 'Przelew SEPA wychodzący',
        '341' => 'Przelew SEPA przychodzący',
        '342' => 'Zwrot przelewu SEPA',
        '345' => 'Anulowanie przelewu SEPA',
        '410' => 'PZ SEPA — obciążenie',
        '411' => 'PZ SEPA — odwołanie',
        '414' => 'PZ SEPA — uznanie',

        // Podatki / ZUS
        '122' => 'Przelew podatkowy',
        '153' => 'Przelew zewnętrzny do ZUS',
        '197' => 'Prowizja za przelew do ZUS',
        '209' => 'Przelew przyszły do ZUS',
        '210' => 'Przelew podatkowy',
        '211' => 'Przelew przyszły podatkowy',

        // Polecenie zapłaty
        '204' => 'Polecenie zapłaty — obciążenie',
        '206' => 'Polecenie zapłaty — odwołanie',
        '207' => 'Polecenie zapłaty — obciążenie',
        '208' => 'Polecenie zapłaty — uznanie',
        '214' => 'Polecenie zapłaty — odwołanie odsetek',
        '215' => 'Polecenie zapłaty — uznanie',

        // Zlecenia stałe / oszczędzanie
        '322' => 'Opłata za zlecenie stałe zewnętrzne',
        '339' => 'Opłata za zlecenie stałe zewnętrzne',
        '359' => 'Opłata za zlecenie stałe zewnętrzne',
        '433' => 'Przelew "Regularne oszczędzanie"',
        '434' => 'Przelew "Regularne oszczędzanie"',

        // Odsetki
        '178' => 'Odsetki lokat terminowych',
        '180' => 'Odsetki — uznanie',
        '182' => 'Odsetki lokat terminowych',
        '185' => 'Odsetki za niedopuszczalny debet',
        '362' => 'Odsetki lokaty inwestycyjnej',
        '680' => 'Odsetki — obciążenie',
        '682' => 'Korekta odsetek lokaty terminowej',

        // Prowizje / opłaty
        '155' => 'Prowizja za przelew',
        '181' => 'Prowizje',
        '187' => 'Wydatki brokerskie',
        '191' => 'Prowizja za rozpatrzenie wniosku',
        '192' => 'Prowizja za udzielenie kredytu',
        '194' => 'Opłata za przelew podatkowy',
        '195' => 'Prowizja za autoryzację operacji',
        '196' => 'Anulowanie prowizji za autoryzację',
        '218' => 'Opłata za duplikat / kopię dokumentu',
        '244' => 'Opłata — przelew ekspresowy',
        '252' => 'Opłata — przelew ekspresowy',
        '259' => 'Opłata za lokatę za miesiąc',
        '273' => 'Prowizja od niewykorzystanego limitu',
        '274' => 'Opłata za operację kasową mBank',
        '275' => 'Opłata',
        '276' => 'Opłata za przelew przychodzący SEPA',
        '343' => 'Prowizja za przelew SEPA wychodzący',
        '425' => 'Opłata — zerwanie lokaty',
        '436' => 'Opłata za przelew walutowy przychodzący',
        '444' => 'Prowizja za przelew zagraniczny i walutowy',
        '449' => 'Opłata za komunikat SWIFT',
        '451' => 'Prowizja za przelew zagraniczny i walutowy',
        '703' => 'Opłata za wyciąg wysłany e-mailem',

        // Kredyty
        '14'  => 'Kredyt — uznanie',
        '18'  => 'Kredyt — wydatek / opłata',
        '20'  => 'Kredyt — spłata raty',
        '21'  => 'Kredyt — prowizja (wniosek)',
        '22'  => 'Kredyt — prowizja (uruchomienie)',
        '23'  => 'Kredyt — prowizja (transza)',
        '24'  => 'Kredyt — prowizja (prolongata)',
        '28'  => 'Kredyt — przewalutowanie',
        '51'  => 'Kredyt — anulowanie uznania',
        '55'  => 'Kredyt — anulowanie opłaty',
        '70'  => 'Kredyt — anulowanie spłaty raty',
        '75'  => 'Kredyt — anulowanie prowizji',
        '90'  => 'Inny kredyt',
        '700' => 'Kredyt — wcześniejsza spłata',
        '701' => 'Kredyt — anulowanie wcześniejszej spłaty',

        // Storna / anulacje / zwroty
        '200' => 'Sytuacja wyjątkowa — uznanie',
        '201' => 'Sytuacja wyjątkowa — obciążenie',
        '212' => 'Ręczne uznanie',
        '213' => 'Ręczne obciążenie',
        '263' => 'Anulowanie wpłaty w mBanku',
        '264' => 'Anulowanie wypłaty w mBanku',
        '286' => 'Zwrot z tytułu promocji MoneyBack',
        '287' => 'Anulowanie zwrotu MoneyBack',
        '289' => 'Przelew przychodzący MoneySend — odwołanie',
        '344' => 'Zwrot prowizji za przelew SEPA',
        '346' => 'Anulowanie opłaty za przelew wewnętrzny',
        '347' => 'Anulowanie opłaty za przelew zewnętrzny',
        '348' => 'Anulowanie opłaty za przelew SEPA',
        '349' => 'Anulowanie opłaty za przelew SWIFT',
        '391' => 'Anulowanie prowizji za wpłatę/wypłatę w mBanku',
        '491' => 'Zwrot opłaty za prowadzenie rachunku',
        '533' => 'Anulacja prowizji — własny ATM',
        '534' => 'Anulacja prowizji — krajowy ATM',
        '535' => 'Anulacja prowizji — zagraniczny ATM',
        '540' => 'Bankomat — odwołanie prowizji',
        '650' => 'Anulowanie obciążenia',
        '651' => 'Storno obciążenia',
        '652' => 'Anulowanie uznania',
        '653' => 'Storno uznania',
        '657' => 'Storno uznania',
        '658' => 'Storno obciążenia',

        // Konta / lokaty
        '140' => 'Zamknięcie rachunku',
        '190' => 'Zerwanie lokaty terminowej',
        '202' => 'Otwarcie rachunku — wpłata',
        '203' => 'Zamknięcie rachunku — wypłata',
        '260' => 'Transfer środków z IKE / PPE',
        '268' => 'Produkt dodatkowy do rachunku',
        '290' => 'Produkt dodatkowy do rachunku',
        '360' => 'Przelew wewnętrzny — otwarcie lokaty inwestycyjnej',
        '364' => 'Wygaśnięcie lokaty inwestycyjnej',
        '366' => 'Zerwanie lokaty inwestycyjnej',
        '656' => 'Otwarcie rachunku',
        '664' => 'Wygaśnięcie lokaty terminowej',
        '666' => 'Zerwanie lokaty terminowej',

        // Windykacja
        '670' => 'Windykacja — opł. za ostateczne wezwanie do zapłaty',
        '676' => 'Opłata sądowa',
        '677' => 'Opłata komornicza',
        '678' => 'Opłata specjalna',
        '684' => 'Windykacja — opłata za wysłanie monitu',
        '686' => 'Windykacja — opłata za monit telefoniczny',
        '688' => 'Windykacja — opłata sądowa',
        '690' => 'Windykacja — opłata komornicza',
        '696' => 'Windykacja — opłata specjalna',
        '698' => 'Windykacja — opł. za wezwanie do zapłaty',
    ];

    /**
     * Legacy 3-znakowe kody SWIFT (PKO, Santander, ING — w starszym formacie).
     */
    private const SWIFT_3CHAR = [
        'C20' => 'Wpłata gotówkowa (uznanie)',
        'C44' => 'Czek otrzymany',
        'C50' => 'Przelew otrzymany (zwykły)',
        'C57' => 'Czek skupowy',
        'C61' => 'Przelew otrzymany',
        'C62' => 'Przelew zagraniczny otrzymany',
        'C95' => 'Uznanie — opłata bankowa',
        'D20' => 'Wypłata gotówkowa (obciążenie)',
        'D44' => 'Czek wystawiony',
        'D50' => 'Przelew wychodzący (zwykły)',
        'D57' => 'Czek rozliczeniowy',
        'D61' => 'Przelew własny',
        'D62' => 'Przelew zagraniczny wychodzący',
        'D94' => 'Przelew krajowy (Elixir wychodzący)',
        'D95' => 'Obciążenie — opłata bankowa',
        'D99' => 'Inne obciążenie',
        'N20' => 'Wpłata gotówkowa zewnętrzna',
        'N31' => 'Przelew SEPA Credit Transfer',
        'N32' => 'Przelew SEPA Direct Debit',
        'N44' => 'Odsetki',
        'N50' => 'Przelew SEPA wychodzący',
        'N57' => 'Przelew międzybankowy SEPA',
        'N94' => 'Przelew krajowy Elixir',
        'N95' => 'Opłata bankowa (zewnętrzna)',
        'S20' => 'Wpłata własna gotówkowa',
        'S50' => 'Przelew okresowy / zlecenie stałe',
        'S61' => 'Polecenie zapłaty',
        'A61' => 'Korekta / księgowanie administracyjne (uznanie)',
        'A95' => 'Opłaty bankowe (uznanie kredytowe)',
        'F50' => 'Express Elixir / przelew natychmiastowy',
        'F94' => 'Przelew natychmiastowy krajowy',
        'Z61' => 'Storno przelewu',
        'Z50' => 'Zwrot przelewu',
        'G50' => 'Przelew gwarancyjny',
        'W50' => 'Przewalutowanie',
    ];

    /**
     * Zwraca opis kodu MT940. Próbuje:
     * 1) dokładne dopasowanie 3-znakowe SWIFT (A61, D50…)
     * 2) 4-znakowe mBank: prefix + 3-cyfrowy kod → "Opis (kategoria)"
     * 3) fallback na samą kategorię z prefiksu.
     */
    public static function describe(?string $code): string
    {
        if (!$code) return '';
        $code = strtoupper(trim($code));
        if ($code === '') return '';

        // 1. Dokładny SWIFT 3-znakowy
        if (isset(self::SWIFT_3CHAR[$code])) {
            return self::SWIFT_3CHAR[$code];
        }

        // 2. mBank 4-znakowy: pierwsza litera + 3 cyfry
        if (preg_match('/^([A-Z])(\d{3})$/', $code, $m)) {
            $prefix = $m[1];
            $num    = $m[2];
            $numDesc  = self::NUMERIC_CODES[$num]    ?? self::NUMERIC_CODES[ltrim($num, '0')] ?? null;
            $prefDesc = self::PREFIX_LABELS[$prefix] ?? null;

            if ($numDesc !== null && $prefDesc !== null) {
                return $numDesc . ' (' . $prefDesc . ')';
            }
            if ($numDesc !== null) return $numDesc;
            if ($prefDesc !== null) return ucfirst($prefDesc) . ' — kod ' . $num;
        }

        // 3. Czysto numeryczny kod (1-3 cyfry)
        if (preg_match('/^\d{1,3}$/', $code)) {
            $numDesc = self::NUMERIC_CODES[$code] ?? self::NUMERIC_CODES[ltrim($code, '0')] ?? null;
            if ($numDesc !== null) return $numDesc;
        }

        // 4. Sama pierwsza litera daje kategorię
        $first = substr($code, 0, 1);
        if (isset(self::PREFIX_LABELS[$first])) {
            return ucfirst(self::PREFIX_LABELS[$first]) . ' — kod ' . substr($code, 1);
        }

        return 'Kod MT940: ' . $code . ' (opis nieznany)';
    }
}

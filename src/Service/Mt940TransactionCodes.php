<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Słownik kodów transakcji MT940 dla pola :86: / :61:.
 *
 * Źródło: WYŁĄCZNIE oficjalna dokumentacja mBank "MT940 — wyciągi dzienne
 * i miesięczne w części detalicznej mBanku" (PDF).
 *
 * Format mBank: kod może mieć postać:
 *  - 3-znakowy alfanumeryczny: A61, B55, C97, FB1, 10A, 27A (głównie operacje
 *    specjalne: BLIK, SEPA DD, transakcje walutowe natychmiastowe, opłaty)
 *  - 3-cyfrowy numeryczny: 100, 150, 220, 341 (główny zbiór operacji)
 *  - 1-2 cyfrowy: 14, 18, 88 (kredyty, karty)
 *  - 4-znakowy z prefiksem SWIFT: N+3cyfry, S+3cyfry, F+3cyfry — prefix to
 *    metakategoria SWIFT (N=normal/zwykły, S=zlecenie stałe, F=przyszły)
 *
 * **WAŻNE**: ten serwis pokrywa tylko mBank. Dla innych banków (PKO, Santander,
 * ING) trzeba dopisać osobne mapy na podstawie ich dokumentacji.
 * NIE dodawaj kodów na podstawie ogólnej wiedzy o SWIFT — różne banki używają
 * tego samego kodu (np. A61) z różnym znaczeniem.
 */
final class Mt940TransactionCodes
{
    /**
     * Metakategoria SWIFT — prefiks N/S/F/G/W w 4-znakowych kodach.
     * Sama litera bez 3-cyfrowego kodu operacji jest niewystarczająca.
     */
    private const SWIFT_PREFIX = [
        'N' => 'przelew zwykły',
        'S' => 'zlecenie stałe',
        'F' => 'przelew przyszły',
        'G' => 'operacja grupowa',
        'W' => 'operacja walutowa',
    ];

    /**
     * Alfanumeryczne kody mBank (3 znaki, prefix-litera + 2 znaki).
     * Wzięte 1:1 z oficjalnego PDF mBanku.
     */
    private const ALPHANUM_CODES = [
        // A — głównie transakcje walutowe natychmiastowe + przelewy wewnętrzne
        'A13' => 'Prowizja — przelew zagraniczny walutowy zdefiniowany',
        'A14' => 'Anulowanie prowizji — przelew walutowy zdefiniowany',
        'A15' => 'Przelew przyszły do ZUS',
        'A16' => 'Opłata za zlecenie stałe do ZUS',
        'A17' => 'Przelew przyszły podatkowy',
        'A18' => 'Opłata za zlecenie stałe podatkowe',
        'A19' => 'Opłata za zlecenie stałe — inny organ podatkowy',
        'A43' => 'Prowizja — przelew SEPA zdefiniowany',
        'A44' => 'Prowizja — przelew systemowy walutowy zdefiniowany',
        'A45' => 'Prowizja przelew SEPA zdefiniowany — anulowanie',
        'A60' => 'Obciążenie — natychmiastowa transakcja walutowa',
        'A61' => 'Uznanie — natychmiastowa transakcja walutowa',
        'A62' => 'Anulowanie obciążenia natychmiastowej transakcji walutowej',
        'A63' => 'Anulowanie uznania natychmiastowej transakcji walutowej',
        'A64' => 'Uznanie — transakcja walutowa D+1/D+2',
        'A65' => 'Obciążenie — transakcja walutowa D+1/D+2',
        'A66' => 'Prowizja — obciążenie natychmiastowej transakcji walutowej',
        'A67' => 'Anulowanie prowizji natychmiastowej transakcji walutowej',
        'A68' => 'Prowizja — obciążenie transakcji D+1/D+2',
        'A69' => 'Prowizja — uznanie transakcji D+1/D+2',
        'A70' => 'Anulowanie prowizji za obciążenie transakcji D+1/D+2',
        'A71' => 'Anulowanie prowizji za uznanie transakcji D+1/D+2',
        'A72' => 'Przelew wewnętrzny wychodzący',
        'A73' => 'Opłata — przelew wewnętrzny dowolny bieżący',
        'A74' => 'Opłata — przelew wewnętrzny zdefiniowany bieżący',
        'A75' => 'Przelew wewnętrzny wychodzący',
        'A76' => 'Przelew wewnętrzny wychodzący',
        'A77' => 'Przelew wewnętrzny wychodzący',
        'A78' => 'Opłata za przelew wewnętrzny',
        'A79' => 'Opłata za przelew wewnętrzny dowolny',
        'A80' => 'Opłata za przelew wewnętrzny',
        'A81' => 'Opłata — przelew wewnętrzny zdefiniowany',
        'A82' => 'Przelew wewnętrzny wychodzący',
        'A83' => 'Przelew wewnętrzny wychodzący',
        'A84' => 'Opłata — przelew wewnętrzny dowolny przyszły',
        'A85' => 'Opłata — przelew wewnętrzny zdefiniowany przyszły',
        'A86' => 'Opłata za zlecenie stałe wewnętrzne',
        'A87' => 'Przelew podatkowy',
        'A88' => 'Opłata za przelew — inny organ podatkowy',
        'A89' => 'Przelew przyszły podatkowy',
        'A90' => 'Opłata za przelew przyszły — inny organ podatkowy',
        'A91' => 'Przelew wewnętrzny przychodzący',

        // B — przelewy + SEPA Direct Debit + ubezpieczenia
        'B00' => 'Przelew wewnętrzny wychodzący',
        'B01' => 'Opłata — przelew wewnętrzny zdefiniowany',
        'B02' => 'Opłata — przelew wewnętrzny dowolny',
        'B03' => 'Opłata — przelew wewnętrzny zdefiniowany',
        'B04' => 'Opłata — przelew wewnętrzny dowolny',
        'B21' => 'Przelew wewnętrzny wychodzący',
        'B22' => 'Przelew zewnętrzny wychodzący',
        'B23' => 'Przelew zewnętrzny wychodzący',
        'B24' => 'Przelew wewnętrzny wychodzący',
        'B25' => 'Przelew wewnętrzny przychodzący',
        'B31' => 'Opłata — przelew wewnętrzny zdefiniowany',
        'B32' => 'Opłata — przelew wewnętrzny dowolny',
        'B33' => 'Opłata za przelew zewnętrzny',
        'B34' => 'Opłata za przelew zewnętrzny',
        'B35' => 'Opłata — przelew wewnętrzny dowolny',
        'B36' => 'Opłata — przelew wewnętrzny zdefiniowany',
        'B37' => 'Przelew zewnętrzny przychodzący',
        'B38' => 'Przelew zewnętrzny przychodzący',
        'B39' => 'Przelew zewnętrzny wychodzący',
        'B40' => 'Przelew zewnętrzny wychodzący',
        'B41' => 'Przelew zewnętrzny wychodzący',
        'B42' => 'Przelew zewnętrzny wychodzący',
        'B43' => 'Przelew zewnętrzny wychodzący',
        'B44' => 'Przelew zewnętrzny wychodzący',
        'B45' => 'Przelew zewnętrzny wychodzący',
        'B51' => 'PZ SEPA — obciążenie',
        'B52' => 'PZ SEPA — obciążenie',
        'B53' => 'PZ SEPA — odwołanie',
        'B54' => 'PZ SEPA — odwołanie',
        'B55' => 'PZ SEPA — uznanie',
        'B56' => 'PZ SEPA — uznanie',
        'B81' => 'Składka za ubezpieczenie mTransfer',
        'B82' => 'Anulowanie ubezpieczenia mTransferu',

        // C — BLIK (zakupy, wypłaty, prowizje, reklamacje)
        'C01' => 'BLIK — zakup',
        'C02' => 'BLIK — korekta zakupu',
        'C03' => 'BLIK — anulacja zakupu',
        'C04' => 'BLIK — zakup z cash back',
        'C05' => 'BLIK — korekta zakupu z cash back',
        'C06' => 'BLIK — anulacja zakupu z cash back',
        'C07' => 'BLIK — wypłata z banku',
        'C08' => 'BLIK — korekta wypłaty z banku',
        'C09' => 'BLIK — anulacja wypłaty z banku',
        'C10' => 'BLIK — wypłata z banku krajowego',
        'C11' => 'BLIK — korekta wypłaty z banku krajowego',
        'C12' => 'BLIK — anulacja wypłaty z banku krajowego',
        'C13' => 'BLIK — wypłata ATM własny',
        'C14' => 'BLIK — korekta wypłaty ATM własny',
        'C15' => 'BLIK — anulacja wypłaty ATM własny',
        'C16' => 'BLIK — wypłata ATM wskazanej sieci',
        'C17' => 'BLIK — korekta wypłaty ATM wskazanej sieci',
        'C18' => 'BLIK — anulacja wypłaty ATM wskazanej sieci',
        'C19' => 'BLIK — wypłata ATM krajowy',
        'C20' => 'BLIK — korekta wypłaty ATM krajowy',
        'C21' => 'BLIK — anulacja wypłaty ATM krajowy',
        'C22' => 'BLIK — zakup e-commerce',
        'C23' => 'BLIK — korekta zakupu e-commerce',
        'C24' => 'BLIK — anulacja zakupu e-commerce',
        'C25' => 'BLIK — prowizja zakup',
        'C26' => 'BLIK — korekta prowizji zakup',
        'C27' => 'BLIK — anulacja prowizji zakup',
        // C28-C96 to dalsze prowizje/korekty BLIK — generujemy opis ogólny
        'C97' => 'Uznanie reklamacyjne BLIK',
        'C98' => 'Obciążenie reklamacyjne RT BLIK',
        'C99' => 'Obciążenie reklamacyjne BLIK',
        'C9A' => 'Uznanie reklamacyjne RT BLIK',

        // F — promocje / MOKAZJE
        'FB1' => 'MOKAZJE — uznanie',
        'FB2' => 'MOKAZJE — korekta',

        // Mieszane (cyfra + litera)
        '00A' => 'Opłata za pakiet do eKonta',
        '00B' => 'Przelew zewnętrzny wychodzący',
        '00C' => 'Przelew zewnętrzny wychodzący',
        '00D' => 'Opłata za przelew zewn. dowolny',
        '00E' => 'Opłata za przelew zewn. zdefiniowany',
        '00F' => 'Opłata za przelew zewn. dowolny',
        '01A' => 'Opłata za przelew zewn. zdefiniowany',
        '01B' => 'Promo-odsetki za nowe środki',
        '10A' => 'Opłata za mKonto',
        '10B' => 'Opłata za mKonto — anulowanie',
        '27A' => 'Opłata za prowadzenie rachunku',
        '78A' => 'Opłata za przelew przyszły — inny organ podatkowy',
        '85A' => 'Opłata za zawieszenie PZ',
        '85B' => 'Anulowanie opłaty — zawieszenie PZ',
        '85C' => 'Opłata za wznowienie PZ',
        '85D' => 'Anulowanie opłaty — wznowienie PZ',
        '86A' => 'Opłata za odnowienie PZ',
        '86B' => 'Anulowanie opłaty — odnowienie PZ',
        '86C' => 'Opłata za przywrócenie PZ',
        '86D' => 'Anulowanie opłaty — przywrócenie PZ',
        '91A' => 'Raporty MT940 — opłata za dostęp',
        '91B' => 'Raporty MT940 — opłata, anulowanie',
        '4A2' => 'Korekta odsetek',
        '4A3' => 'Anulowanie korekty odsetek',
    ];

    /**
     * 3-cyfrowe kody mBank z PDF (zbiór najczęstszych dla operacji firmowych).
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
     * Zwraca opis kodu MT940.
     * Priorytet: alfanumeryczny exact → 4-char SWIFT prefix + numeric →
     * sam numeric → ogólny fallback.
     */
    public static function describe(?string $code): string
    {
        if (!$code) return '';
        $code = strtoupper(trim($code));
        if ($code === '') return '';

        // 1. Dokładny match w alfanumerycznej mapie mBanku (A61, B55, C97, FB1, 10A...)
        if (isset(self::ALPHANUM_CODES[$code])) {
            return self::ALPHANUM_CODES[$code];
        }

        // 2. 4-znakowy SWIFT (N+3cyfry, S+3cyfry, F+3cyfry, G+3cyfry, W+3cyfry)
        if (preg_match('/^([NSFGW])(\d{3})$/', $code, $m)) {
            $prefix = $m[1];
            $num    = $m[2];
            $numDesc  = self::NUMERIC_CODES[$num] ?? self::NUMERIC_CODES[ltrim($num, '0')] ?? null;
            $prefDesc = self::SWIFT_PREFIX[$prefix] ?? null;

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

        // 4. C-prefiks (zwykle BLIK) bez exact match — zaznacz że to BLIK
        if (str_starts_with($code, 'C') && preg_match('/^C\d{2}$/', $code)) {
            return 'BLIK — operacja (kod ' . $code . ', sprawdź dokumentację mBanku)';
        }

        // 5. Sama litera SWIFT bez 3-cyfrowego kodu operacji — zwracamy tylko meta-kategorię
        $first = substr($code, 0, 1);
        if (isset(self::SWIFT_PREFIX[$first]) && strlen($code) > 1) {
            return ucfirst(self::SWIFT_PREFIX[$first]) . ' — kod ' . substr($code, 1)
                . ' (opis pełny niedostępny — dodaj do mapy)';
        }

        return 'Kod MT940: ' . $code . ' (opis nieznany — sprawdź dokumentację banku)';
    }
}

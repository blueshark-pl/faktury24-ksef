<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Parser wyciągów bankowych w formacie SWIFT MT940.
 *
 * Obsługuje:
 * - Banki polskie: PKO BP, mBank (BRE), ING, Santander, BNP Paribas, Alior, Pekao
 * - Enkodowanie UTF-8 i Windows-1250 (auto-detect)
 * - Zakończenia linii CRLF i LF
 * - Znacznik tagów :86: z formatem /TAG/wartość lub wolnym tekstem
 */
class Mt940ParserService
{
    /** @var array<string,mixed> Przetworzone dane wyciągu */
    private array $statement = [];

    /** @var array<int,array<string,mixed>> Lista transakcji */
    private array $transactions = [];

    /** @var string[] Błędy/ostrzeżenia parsowania */
    private array $errors = [];

    // -------------------------------------------------------------------------
    // Główne API
    // -------------------------------------------------------------------------

    /**
     * Parsuje zawartość pliku MT940.
     *
     * @param  string $content Surowy tekst pliku
     * @return array{statement: array<string,mixed>, transactions: array<int,array<string,mixed>>, errors: string[]}
     */
    public function parse(string $content): array
    {
        $this->statement    = [];
        $this->transactions = [];
        $this->errors       = [];

        $content = $this->normalize($content);
        $blocks  = $this->splitBlocks($content);

        foreach ($blocks as $block) {
            $this->parseBlock($block);
        }

        return [
            'statement'    => $this->statement,
            'transactions' => $this->transactions,
            'errors'       => $this->errors,
        ];
    }

    // -------------------------------------------------------------------------
    // Normalizacja i podział
    // -------------------------------------------------------------------------

    private function normalize(string $content): string
    {
        // Usuń UTF-8 BOM jeśli obecny
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Autodetekcja Windows-1250
        if (!mb_check_encoding($content, 'UTF-8')) {
            $converted = mb_convert_encoding($content, 'UTF-8', 'Windows-1250');
            if ($converted !== false) {
                $content = $converted;
            }
        }

        // Ujednolicenie końców linii
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        return $content;
    }

    /**
     * Dzieli plik na bloki — jeden blok = jeden wyciąg (od :20: do -)
     * Plik może zawierać wiele wyciągów pod rząd.
     */
    private function splitBlocks(string $content): array
    {
        // Usuń opcjonalne nagłówki SWIFT {1:...}{2:...}{4:\n ... \n-}
        // i wyodrębnij zawartość bloków {4: ... -}
        $blocks = [];

        // Próba wyodrębnienia przez nagłówek Swift {4:
        if (preg_match_all('/\{4:(.*?)\n?-\}/s', $content, $m)) {
            foreach ($m[1] as $b) {
                $blocks[] = trim($b);
            }
            return array_filter($blocks);
        }

        // Brak nagłówków Swift — podziel po :20: (każde wystąpienie = nowy wyciąg)
        $parts = preg_split('/(?=:20:)/', $content, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $p) {
            $p = trim($p);
            // Usuń końcowe "-" lub "-}"
            $p = rtrim($p, " \n\t-");
            if ($p !== '') {
                $blocks[] = $p;
            }
        }

        return $blocks;
    }

    // -------------------------------------------------------------------------
    // Parsowanie bloku
    // -------------------------------------------------------------------------

    private function parseBlock(string $block): void
    {
        $fields = $this->extractFields($block);

        $accountNumber   = null;
        $currency        = 'PLN';
        $openingBalance  = null;
        $openingDir      = null;
        $closingBalance  = null;
        $closingDir      = null;
        $statementNumber = null;
        $transRef        = null;

        foreach ($fields as ['tag' => $tag, 'value' => $value]) {
            switch ($tag) {
                case '20':
                    $transRef = trim($value);
                    break;

                case '25':
                    // :25: może zawierać walutę po "/" lub IBAN
                    $accountNumber = $this->cleanAccountNumber($value);
                    break;

                case '28C':
                    $statementNumber = trim($value);
                    break;

                case '60F':
                case '60M':
                    [$openingDir, , $currency, $openingBalance] = $this->parseBalanceLine($value);
                    break;

                case '62F':
                case '62M':
                    [$closingDir, , , $closingBalance] = $this->parseBalanceLine($value);
                    break;

                case '61':
                    $nextTag = null; // :86: może po nim nastąpić
                    // transakcja — obsłużona razem z :86: niżej
                    break;
            }
        }

        // Zapisz dane wyciągu (pierwszy blok wygrywa jeśli kilka)
        if (empty($this->statement)) {
            $this->statement = [
                'account_number'  => $accountNumber,
                'currency'        => $currency,
                'opening_balance' => $openingBalance,
                'opening_dir'     => $openingDir,
                'closing_balance' => $closingBalance,
                'closing_dir'     => $closingDir,
                'statement_number'=> $statementNumber,
                'transaction_ref' => $transRef,
            ];
        }

        // Przetwórz pary :61: + opcjonalne :86:
        $this->extractTransactions($fields, $accountNumber ?? '', $currency);
    }

    // -------------------------------------------------------------------------
    // Wyodrębnienie par :61: + :86:
    // -------------------------------------------------------------------------

    private function extractTransactions(array $fields, string $accountNumber, string $currency): void
    {
        $i = 0;
        $count = count($fields);

        while ($i < $count) {
            $field = $fields[$i];

            if ($field['tag'] !== '61') {
                $i++;
                continue;
            }

            $line61 = $field['value'];

            // Zbierz WSZYSTKIE kolejne :86: po :61: i sklej je
            $line86Parts = [];
            while (isset($fields[$i + 1]) && $fields[$i + 1]['tag'] === '86') {
                $line86Parts[] = $fields[$i + 1]['value'];
                $i++;
            }
            $line86 = $line86Parts ? implode("\n", $line86Parts) : null;

            $tx = $this->parseTransaction($line61, $line86, $accountNumber, $currency);
            if ($tx !== null) {
                $this->transactions[] = $tx;
            }

            $i++;
        }
    }

    // -------------------------------------------------------------------------
    // Parsowanie transakcji (:61: + :86:)
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null
     */
    private function parseTransaction(string $line61, ?string $line86, string $accountNumber, string $currency): ?array
    {
        /*
         * Format :61:
         *   YYMMDD[MMDD]D/C[X]<kwota>N<kod><ref klienta>[//<ref banku>[\n<inf.dodatkowe>]]
         *
         * Przykłady:
         *   2601020103CR500,00NTRFPAYMENT-REF//BANK123
         *   260102D1000,00NTRFNONREF
         *   2601020103C500,00NCHK
         */
        $raw = trim($line61);
        $pos = 0;

        // Value date: YYMMDD (6 znaków)
        if (strlen($raw) < 6) {
            $this->errors[] = "Zbyt krótki wiersz :61:: $raw";
            return null;
        }
        $valueDateStr = substr($raw, 0, 6);
        $pos = 6;

        // Entry date (opcjonalne): MMDD (4 znaki, tylko cyfry po value date przed D/C)
        $bookingDateStr = null;
        if (preg_match('/^\d{4}/', substr($raw, $pos))) {
            $bookingDateStr = substr($raw, $pos, 4);
            $pos += 4;
        }

        // D/C indicator: D, C, RD, RC
        $dirMatch = null;
        if (preg_match('/^(RD|RC|D|C)/', substr($raw, $pos), $m)) {
            $dirMatch = $m[1];
            $pos += strlen($dirMatch);
        } else {
            $this->errors[] = "Nie rozpoznano kierunku (D/C) w :61:: $raw";
            return null;
        }

        // Opcjonalny jeden znak waluty (trzecia litera kodu waluty) — ignorujemy
        if (preg_match('/^[A-Z]/', substr($raw, $pos)) && !preg_match('/^\d/', substr($raw, $pos))) {
            $pos++;
        }

        // Kwota: cyfry + opcjonalnie przecinek/kropka + cyfry, do litery N lub T lub końca cyfr
        if (!preg_match('/^([\d,\.]+)/', substr($raw, $pos), $mAmt)) {
            $this->errors[] = "Nie rozpoznano kwoty w :61:: $raw";
            return null;
        }
        $amountStr = $mAmt[1];
        $pos += strlen($amountStr);

        // Kod transakcji: litera (N lub F lub G lub S lub W) + 3 znaki alfanumeryczne
        $transactionCode = null;
        if (preg_match('/^([A-Z][A-Z0-9]{3})/', substr($raw, $pos), $mCode)) {
            $transactionCode = $mCode[1];
            $pos += 4;
        }

        // Reszta wiersza — referencja klienta i banku
        $rest = substr($raw, $pos);
        $customerRef = null;
        $bankRef      = null;

        // Podział po // (referencja banku)
        if (str_contains($rest, '//')) {
            [$refPart, $bankRefRaw] = explode('//', $rest, 2);
            $customerRef = trim($refPart);
            $bankRef     = trim(strtok($bankRefRaw, "\n"));
        } else {
            $customerRef = trim(strtok($rest, "\n"));
        }

        // Pomiń NONREF jako referencję
        if (in_array(strtoupper((string)$customerRef), ['NONREF', 'NOTPROVIDED', ''], true)) {
            $customerRef = null;
        }

        // Parsuj :86: — szczegóły transakcji
        $direction  = str_contains($dirMatch, 'D') ? 'D' : 'C';
        $amount     = (float)str_replace(',', '.', str_replace('.', '', str_replace(',', '.', $amountStr)));
        // Poprawna zamiana: europejski format 1.234,56 → 1234.56
        $amount     = $this->parseEuropeanAmount($amountStr);
        $valueDate  = $this->parseDate6($valueDateStr);
        $bookingDate = $bookingDateStr ? $this->parseDate4($bookingDateStr, $valueDate) : null;

        [$partyName, $partyAccount, $title, $description] = $this->parse86($line86);

        // Oblicz hash deduplikacji
        $hashInput  = implode('|', [
            $accountNumber,
            $valueDate ?? '',
            $direction,
            number_format($amount, 2, '.', ''),
            $bankRef ?? $customerRef ?? '',
        ]);
        $importHash = hash('sha256', $hashInput);

        return [
            'account_number'     => $accountNumber ?: null,
            'value_date'         => $valueDate,
            'booking_date'       => $bookingDate,
            'direction'          => $direction,
            'amount'             => $amount,
            'currency'           => $currency,
            'transaction_code'   => $transactionCode,
            'customer_reference' => $customerRef,
            'bank_reference'     => $bankRef,
            'description'        => $description,
            'party_name'         => $partyName,
            'party_account'      => $partyAccount,
            'title'              => $title,
            'import_hash'        => $importHash,
        ];
    }

    // -------------------------------------------------------------------------
    // Parsowanie pola :86:
    // -------------------------------------------------------------------------

    /**
     * Próba wyodrębnienia danych z pola :86: dla różnych formatów polskich banków.
     *
     * @return array{string|null, string|null, string|null, string|null}
     *         [partyName, partyAccount, title, rawDescription]
     */
    private function parse86(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [null, null, null, null];
        }

        $raw = trim($raw);

        $partyName   = null;
        $partyAcct   = null;
        $title       = null;

        // ── Format A: ~XX (standard Deutsche/German MT940, BNP Paribas PL, mBank) ──
        // Struktura: ~00 typ, ~20-~29 tytuł (segmenty), ~30 BIC, ~31 konto, ~32+~33 nazwa,
        //            ~34 kod, ~38 pełny IBAN, ~62+~63 adres
        if (str_contains($raw, '~')) {
            // preg_split z PREG_SPLIT_DELIM_CAPTURE zwraca:
            // [0]=tekst-przed, [1]=kod, [2]=wartość, [3]=kod, [4]=wartość, ...
            $tildeParts = preg_split('/~(\d{2})/', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
            $tildeFields = [];
            // Pary zaczynają się od indeksu 1 (idx 0 = tekst przed pierwszym ~XX)
            $ti = 1;
            while ($ti + 1 < count($tildeParts)) {
                $code = $tildeParts[$ti];
                $val  = trim($tildeParts[$ti + 1]);
                if (ctype_digit($code) && strlen($code) === 2) {
                    $tildeFields[(int)$code] = $val;
                }
                $ti += 2;
            }

            // ~32 + ~33 = nazwa kontrahenta (dwie linie, sklejone)
            $name32 = trim($tildeFields[32] ?? '');
            $name33 = trim($tildeFields[33] ?? '');
            $combined = trim($name32 . $name33); // bez spacji — to dosłowna kontynuacja
            if ($combined !== '') {
                $partyName = $combined;
            }

            // ~38 = pełny IBAN kontrahenta (preferowany), fallback ~29
            $iban38 = trim($tildeFields[38] ?? '');
            $iban29 = trim($tildeFields[29] ?? '');
            $ibanRaw = $iban38 !== '' ? $iban38 : $iban29;
            if ($ibanRaw !== '') {
                $partyAcct = $this->cleanAccountNumber($ibanRaw);
            }

            // ~20-~27 = segmenty tytułu płatności (konkatenacja bez spacji)
            // ~28 = referencja (end-to-end), ~29 = konto nadawcy — nie wchodzą do tytułu
            $purposeParts = [];
            for ($tc = 20; $tc <= 27; $tc++) {
                $v = trim($tildeFields[$tc] ?? '');
                if ($v !== '') {
                    $purposeParts[] = $v;
                }
            }
            if (!empty($purposeParts)) {
                $title = implode('', $purposeParts);
            }
        }

        // ── Format B: /TAG/wartość (PKO BP, ING, Santander, mBank) ──
        if ($partyName === null && str_contains($raw, '/')) {
            $namePatterns  = ['/NAME/', '/NAZ/', '/PŁATNIK/', '/PLATNIK/', '/ODBC/', '/NADAWCA/', '/WIERZYCIEL/', '/BENEFICJENT/'];
            $acctPatterns  = ['/NRB/', '/RACHUNEK/', '/ACCT/', '/IBAN/', '/IB/'];
            $titlePatterns = ['/TYTUL/', '/TYTUŁ/', '/TITL/', '/REMIC/', '/REM/', '/DETAIL/', '/SZCZEGOLY/', '/SZCZEGÓŁY/'];

            $segments = preg_split('/(?=\/[A-ZĄĆĘŁŃÓŚŹŻ]{2,}\/)/u', $raw, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($segments as $seg) {
                $seg = trim($seg);
                if (!preg_match('/^\/([^\/]+)\/(.*)$/su', $seg, $m)) {
                    continue;
                }
                $tagName = strtoupper(trim($m[1]));
                $tagVal  = trim(str_replace("\n", ' ', $m[2]));

                foreach ($namePatterns as $np) {
                    if ('/' . $tagName . '/' === $np) {
                        $partyName = $partyName === null ? $tagVal : $partyName;
                        break;
                    }
                }
                foreach ($acctPatterns as $ap) {
                    if ('/' . $tagName . '/' === $ap) {
                        $partyAcct = $partyAcct === null ? $this->cleanAccountNumber($tagVal) : $partyAcct;
                        break;
                    }
                }
                foreach ($titlePatterns as $tp) {
                    if ('/' . $tagName . '/' === $tp) {
                        $title = $title === null ? $tagVal : $title;
                        break;
                    }
                }
            }
        }

        // ── Format C: wolny tekst (fallback) ──
        if ($title === null && $partyName === null) {
            $title = mb_substr(str_replace("\n", ' ', $raw), 0, 500);
        }

        return [$partyName, $partyAcct, $title, $raw];
    }

    // -------------------------------------------------------------------------
    // Pomocnicze
    // -------------------------------------------------------------------------

    /**
     * Wyodrębnia wszystkie pola MT940 w formie [{tag, value}].
     * Tagi mogą zajmować wiele linii (kontynuacja = linia nie zaczynająca się od :).
     */
    private function extractFields(string $block): array
    {
        $lines  = explode("\n", $block);
        $fields = [];
        $curTag = null;
        $curVal = '';

        foreach ($lines as $line) {
            if (preg_match('/^:([0-9]{2}[A-Z]?):(.*)$/', $line, $m)) {
                if ($curTag !== null) {
                    $fields[] = ['tag' => $curTag, 'value' => rtrim($curVal)];
                }
                $curTag = $m[1];
                $curVal = $m[2];
            } else {
                // Kontynuacja poprzedniego tagu
                if ($curTag !== null) {
                    $curVal .= "\n" . $line;
                }
            }
        }

        if ($curTag !== null) {
            $fields[] = ['tag' => $curTag, 'value' => rtrim($curVal)];
        }

        return $fields;
    }

    /**
     * Parsuje linię salda: C/D + YYMMDD + waluta (3) + kwota
     * Np: C260101PLN1234,56
     *
     * @return array{string, string, string, float}  [direction, date, currency, amount]
     */
    private function parseBalanceLine(string $line): array
    {
        $line = trim($line);
        $dir  = 'C';

        if (str_starts_with($line, 'D')) {
            $dir  = 'D';
            $line = substr($line, 1);
        } elseif (str_starts_with($line, 'C')) {
            $dir  = 'C';
            $line = substr($line, 1);
        }

        $date = $this->parseDate6(substr($line, 0, 6));
        $line = substr($line, 6);

        $currency = strtoupper(substr($line, 0, 3));
        $line     = substr($line, 3);

        $amount = $this->parseEuropeanAmount($line);

        return [$dir, $date, $currency, $amount];
    }

    /** Zamienia europejski format liczb (1.234,56 lub 1234,56) na float */
    private function parseEuropeanAmount(string $s): float
    {
        $s = trim($s);
        // Jeśli przecinek jest separatorem dziesiętnym (1234,56 lub 1.234,56)
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $s)) {
            // format europejski: usuń kropki (separator tysięcy), zamień przecinek na kropkę
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\d+(,\d+)?$/', $s)) {
            $s = str_replace(',', '.', $s);
        }
        return (float)$s;
    }

    /** Parsuje datę YYMMDD → Y-m-d */
    private function parseDate6(string $s): ?string
    {
        $s = trim($s);
        if (strlen($s) !== 6 || !ctype_digit($s)) {
            return null;
        }
        $yy = (int)substr($s, 0, 2);
        $mm = (int)substr($s, 2, 2);
        $dd = (int)substr($s, 4, 2);
        $yyyy = $yy >= 50 ? 1900 + $yy : 2000 + $yy;
        if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $yyyy, $mm, $dd);
    }

    /** Parsuje datę MMDD → Y-m-d (rok z value date) */
    private function parseDate4(string $s, ?string $valueDateBase): ?string
    {
        $s = trim($s);
        if (strlen($s) !== 4 || !ctype_digit($s) || $valueDateBase === null) {
            return null;
        }
        $year = substr($valueDateBase, 0, 4);
        $mm   = (int)substr($s, 0, 2);
        $dd   = (int)substr($s, 2, 2);
        if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) {
            return null;
        }
        return sprintf('%s-%02d-%02d', $year, $mm, $dd);
    }

    /** Czyści numer rachunku — usuwa spacje, myślniki, wiodące "/" i kody banku po "/" */
    private function cleanAccountNumber(string $s): string
    {
        $s = ltrim(trim($s), '/');          // usuń wiodące ukośniki (np. /PL16...)
        if (str_contains($s, '/')) {
            // usuń sufiks po "/" (np. "PL12345.../EUR" → "PL12345...")
            $s = trim(explode('/', $s)[0]);
        }
        return strtoupper(preg_replace('/[\s\-]/', '', $s));
    }
}

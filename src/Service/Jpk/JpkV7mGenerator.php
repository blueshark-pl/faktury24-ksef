<?php
declare(strict_types=1);

namespace App\Service\Jpk;

/**
 * Generator pliku JPK_V7M wg schematu MF (wariant 3, obowiązuje od 2026-02-01).
 * Namespace: http://crd.gov.pl/wzor/2025/12/19/14090/
 *
 * Wariant SALES-ONLY:
 *  - Naglowek + Podmiot1
 *  - Ewidencja: SprzedazWiersz[] + SprzedazCtrl + pusta sekcja zakupów (ZakupCtrl=0)
 *  - Deklaracja pominięta (minOccurs="0")
 *
 * Mapowanie kwot per stawka VAT do pól K_xx Ewidencji Sprzedaży:
 *   K_15 = netto 5%, K_16 = VAT 5%
 *   K_17 = netto 7%/8%, K_18 = VAT 7%/8%
 *   K_19 = netto 22%/23%, K_20 = VAT 22%/23%
 *   K_13 = netto 0% (sprzedaż w PL)
 *   K_10 = zwolnienie
 *
 * Różnice względem V7M(2):
 *  - Nowy namespace (2025/12/19/14090)
 *  - WariantFormularza = 3
 *  - Usunięto oznaczenia TP, MPP, EE, BSP, BRAK_TP_IFP (mechanika zastąpiona)
 *  - Dodano KorektaPodstawyOpodt + TerminPlatnosci/DataZaplaty (ulga na złe długi, opcjonalne)
 *  - DataWytworzeniaJPK musi być >= 2026-02-01
 */
class JpkV7mGenerator
{
    // Namespace V7M(3) — Schemat_JPK_V7M(3)_v1-0E.xsd (publikacja MF 2025-12-19)
    private const NS_TNS = 'http://crd.gov.pl/wzor/2025/12/19/14090/';
    private const NS_ETD = 'http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/09/13/eD/DefinicjeTypy/';
    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const SCHEMA_FILE = 'Schemat_JPK_V7M(3)_v1-0E.xsd';

    /**
     * @param object $company  Encja firmy sprzedawcy (wymagane: nip, name; opcjonalnie: email, phone)
     * @param iterable $invoices Faktury z contain InvoiceContractors + InvoiceContents (Vats)
     * @param int $rok          Rok deklaracji (np. 2026)
     * @param int $miesiac      Miesiąc deklaracji (1-12)
     * @param string $kodUrzedu 4-znakowy kod urzędu skarbowego
     * @param string $celZlozenia 1 = pierwotne złożenie, 2 = korekta
     * @param string $email     Email kontaktowy podatnika (opcjonalny — Schema pozwala pominąć dla Podmiot1)
     * @param string $telefon   Telefon kontaktowy
     */
    public function generate(
        object $company,
        iterable $invoices,
        int $rok,
        int $miesiac,
        string $kodUrzedu = '0000',
        string $celZlozenia = '1',
        string $email = '',
        string $telefon = ''
    ): string {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        // Bez xsi:schemaLocation — oficjalne walidatory MF (e-mikrofirma, e-jpk.pl)
        // używają WBUDOWANYCH schematów na podstawie xmlns. Zewnętrzne online toole,
        // które nie znają formatu, próbowały pobrać schemat z crd.gov.pl i czasem
        // dostawały HTML zamiast XSD ("file_0.xsd: <!doctype html>") → wywalenie się walidacji.
        $xml .= '<tns:JPK'
            . ' xmlns:tns="' . self::NS_TNS . '"'
            . ' xmlns:etd="' . self::NS_ETD . '"'
            . '>';

        // ── Nagłówek ─────────────────────────────────────────────────────────
        $xml .= '<tns:Naglowek>';
        $xml .= '<tns:KodFormularza kodSystemowy="JPK_V7M (3)" wersjaSchemy="1-0E">JPK_VAT</tns:KodFormularza>';
        $xml .= '<tns:WariantFormularza>3</tns:WariantFormularza>';
        $xml .= '<tns:DataWytworzeniaJPK>' . date('Y-m-d\TH:i:s\Z', time()) . '</tns:DataWytworzeniaJPK>';
        $xml .= '<tns:NazwaSystemu>faktury24.com</tns:NazwaSystemu>';
        $xml .= '<tns:CelZlozenia poz="P_7">' . $this->esc($celZlozenia) . '</tns:CelZlozenia>';
        $xml .= '<tns:KodUrzedu>' . $this->esc(substr($kodUrzedu, 0, 4) ?: '0000') . '</tns:KodUrzedu>';
        $xml .= '<tns:Rok>' . $rok . '</tns:Rok>';
        $xml .= '<tns:Miesiac>' . $miesiac . '</tns:Miesiac>';
        $xml .= '</tns:Naglowek>';

        // ── Podmiot1 ─────────────────────────────────────────────────────────
        // Email jest WYMAGANY przez XSD (TAdresEmail) — fallback jeśli brak
        $effectiveEmail = $email !== ''
            ? $email
            : (!empty($company->email) ? (string)$company->email : 'brak@example.com');

        $xml .= '<tns:Podmiot1 rola="Podatnik">';
        $xml .= '<tns:OsobaNiefizyczna>';
        $xml .= '<tns:NIP>' . $this->esc($this->stripNip((string)($company->nip ?? ''))) . '</tns:NIP>';
        $xml .= '<tns:PelnaNazwa>' . $this->esc($this->truncate((string)($company->name ?? ''), 240)) . '</tns:PelnaNazwa>';
        $xml .= '<tns:Email>' . $this->esc($effectiveEmail) . '</tns:Email>';
        if ($telefon !== '' || !empty($company->phone)) {
            $xml .= '<tns:Telefon>' . $this->esc(substr($telefon !== '' ? $telefon : (string)$company->phone, 0, 16)) . '</tns:Telefon>';
        }
        $xml .= '</tns:OsobaNiefizyczna>';
        $xml .= '</tns:Podmiot1>';

        // ── (Deklaracja PomINIĘTA — minOccurs="0" w XSD; pełna deklaracja
        //     wymaga ~30 wymaganych pól P_xx i danych zakupowych których nie mamy.
        //     "Tylko sprzedaż" → wysyłamy samą Ewidencję) ──

        // Zbierz wiersze sprzedaży (kwoty jako float z 2 miejsc — typ TKwotowy XSD)
        $sprzedazWiersze = [];
        $totalVatNalezny = 0.0;
        $lp = 0;

        foreach ($invoices as $inv) {
            $lp++;
            $buckets = $this->buildInvoiceBuckets($inv);

            $netK15 = round($buckets['5']['net']  ?? 0, 2);
            $vatK16 = round($buckets['5']['vat']  ?? 0, 2);
            $netK17 = round($buckets['8']['net']  ?? 0, 2);
            $vatK18 = round($buckets['8']['vat']  ?? 0, 2);
            $netK19 = round($buckets['23']['net'] ?? 0, 2);
            $vatK20 = round($buckets['23']['vat'] ?? 0, 2);
            $netK13 = round($buckets['0']['net']  ?? 0, 2);
            $netK10 = round($buckets['zw']['net'] ?? 0, 2);

            $totalVatNalezny += $vatK16 + $vatK18 + $vatK20;

            $buyer = $inv->invoice_contractor ?? null;
            $invType = strtolower((string)($inv->type ?? ''));
            $sprzedazWiersze[] = [
                'lp'           => $lp,
                'kod_kraju'    => $this->resolveCountryCode($buyer),
                'nip'          => $this->stripNip((string)($buyer->nip ?? '')),
                'nazwa'        => $this->truncate((string)($buyer->name ?? 'BRAK'), 256),
                'dowod'        => $this->truncate((string)($inv->fullnumber ?? ''), 256),
                # Pole `ksef_number` (Invoice entity _accessible) — numer KSeF w formacie TNumerKSeF.
                # ksef_session_reference + ksef_invoice_reference to wewnętrzne ID sesji/faktury KSeF.
                'ksef_number'  => trim((string)($inv->ksef_number ?? '')),
                'data_wyst'    => $this->formatDate($inv->date),
                # sold_date = "Data sprzedaży / wykonania usługi" (migracja 20260318100000_AddMissingInvoiceFields)
                # Emitujemy DataSprzedazy TYLKO gdy != DataWystawienia (wg XSD documentation).
                'data_sprz'    => $this->formatDate($inv->sold_date ?? null),
                'typ_dok'      => $this->resolveTypDokumentu($inv),
                'gtu_codes'    => $this->collectGtuCodes($inv),
                'margin_kind'  => $invType === 'margin' ? $this->resolveMarginKind($inv) : '',
                'is_tp'        => $this->resolveTpFlag($inv),
                'K_10'         => $netK10,
                'K_13'         => $netK13,
                'K_15'         => $netK15, 'K_16' => $vatK16,
                'K_17'         => $netK17, 'K_18' => $vatK18,
                'K_19'         => $netK19, 'K_20' => $vatK20,
            ];
        }

        // ── Ewidencja ────────────────────────────────────────────────────────
        $xml .= '<tns:Ewidencja>';

        foreach ($sprzedazWiersze as $w) {
            $xml .= '<tns:SprzedazWiersz>';
            $xml .= '<tns:LpSprzedazy>' . $w['lp'] . '</tns:LpSprzedazy>';
            // KodKrajuNadaniaTIN (opcjonalny) + NrKontrahenta (wymagany)
            if ($w['nip'] !== '') {
                $xml .= '<tns:KodKrajuNadaniaTIN>' . $this->esc($w['kod_kraju']) . '</tns:KodKrajuNadaniaTIN>';
                $xml .= '<tns:NrKontrahenta>' . $this->esc($w['nip']) . '</tns:NrKontrahenta>';
            } else {
                // Schemat dopuszcza "brak" dla sprzedaży osobom fizycznym
                $xml .= '<tns:NrKontrahenta>brak</tns:NrKontrahenta>';
            }
            $xml .= '<tns:NazwaKontrahenta>' . $this->esc($w['nazwa']) . '</tns:NazwaKontrahenta>';
            $xml .= '<tns:DowodSprzedazy>' . $this->esc($w['dowod']) . '</tns:DowodSprzedazy>';
            $xml .= '<tns:DataWystawienia>' . $this->esc($w['data_wyst']) . '</tns:DataWystawienia>';
            // DataSprzedazy: XSD documentation MF zaleca pomijać gdy == DataWystawienia, ale to
            // tylko opis tekstowy (xsd:documentation) — walidator XSD tego nie egzekwuje.
            // Emitujemy ZAWSZE gdy sold_date jest w bazie — lepsza jednoznaczność dla audytu.
            if ($w['data_sprz'] !== '') {
                $xml .= '<tns:DataSprzedazy>' . $this->esc($w['data_sprz']) . '</tns:DataSprzedazy>';
            }
            // V7M(3) wymaga DOKŁADNIE JEDNEGO z choice (KSeF):
            //   NrKSeF — faktura zarejestrowana w KSeF z numerem (format TNumerKSeF: NIP-RRRRMMDD-6HEX-6HEX-2HEX)
            //   OFF    — faktura wystawiona offline w trybie art. 106nf ust. 1 (czeka na numer KSeF)
            //   BFK    — faktura elektroniczna lub papierowa BEZ rejestracji w KSeF
            //   DI     — dowód INNY niż faktura (raport okresowy z kasy, dowód wewnętrzny)
            // Mapowanie:
            //   ksef_number niepuste + poprawny format → NrKSeF
            //   TypDokumentu RO/WEW                     → DI
            //   pozostałe (faktura zwykła, FP, korekta, marża, walutowa, zaliczkowa) → BFK
            $typDok = (string)$w['typ_dok'];
            $ksefNum = (string)($w['ksef_number'] ?? '');
            $ksefPattern = '/^([1-9]((\d[1-9])|([1-9]\d))\d{7}|M\d{9}|[A-Z]{3}\d{7})-(20[2-9][0-9]|2[1-9][0-9]{2}|[3-9][0-9]{3})(0[1-9]|1[0-2])(0[1-9]|[1-2][0-9]|3[0-1])-([0-9A-F]{6})-?([0-9A-F]{6})-([0-9A-F]{2})$/';
            if ($ksefNum !== '' && preg_match($ksefPattern, $ksefNum)) {
                $xml .= '<tns:NrKSeF>' . $this->esc($ksefNum) . '</tns:NrKSeF>';
            } elseif ($typDok === 'RO' || $typDok === 'WEW') {
                $xml .= '<tns:DI>1</tns:DI>';
            } else {
                $xml .= '<tns:BFK>1</tns:BFK>';
            }
            if ($typDok !== '') {
                $xml .= '<tns:TypDokumentu>' . $this->esc($typDok) . '</tns:TypDokumentu>';
            }
            // Sekcja "Oznaczenia dostawy" (XSD: GTU_01 → GTU_02 → ... → GTU_13).
            // Emitujemy unikalne kody zebrane ze wszystkich pozycji faktury.
            if (!empty($w['gtu_codes'])) {
                foreach ($w['gtu_codes'] as $g) {
                    $xml .= '<tns:GTU_' . sprintf('%02d', $g) . '>1</tns:GTU_' . sprintf('%02d', $g) . '>';
                }
            }
            // Sekcja "Oznaczenia dotyczące procedur" — kolejność wg XSD V7M(3) (linie 844-861):
            //   WSTO_EE → IED → TP → TT_WNT → TT_D → MR_T → MR_UZ → I_42 → I_63 → B_SPV → B_SPV_DOSTAWA → B_MPV_PROWIZJA
            // V7M(3) względem V7M(2): USUNIĘTO MPP, EE, BSP, BRAK_TP_IFP. TP/TT_WNT/TT_D zostały zachowane.

            // TP — transakcja z podmiotem powiązanym (z annotations.tp w JSON)
            if (!empty($w['is_tp'])) {
                $xml .= '<tns:TP>1</tns:TP>';
            }
            // Faktura marża — rozróżnienie MR_T (turystyka) vs MR_UZ (towary używane / dzieła sztuki / kolekcje)
            // Wg XSD: MR_T przed MR_UZ. Faktura nigdy nie ma obu naraz.
            if ($w['margin_kind'] === 'MR_T') {
                $xml .= '<tns:MR_T>1</tns:MR_T>';
            } elseif ($w['margin_kind'] === 'MR_UZ') {
                $xml .= '<tns:MR_UZ>1</tns:MR_UZ>';
            }
            // K_xx — wszystkie opcjonalne; emitujemy tylko niezerowe (w tym ujemne dla korekt zmniejszających).
            // Typ TKwotowy (decimal, 2 miejsca, znak dopuszczalny).
            if (abs($w['K_10']) >= 0.005) $xml .= '<tns:K_10>' . $this->money($w['K_10']) . '</tns:K_10>';
            if (abs($w['K_13']) >= 0.005) $xml .= '<tns:K_13>' . $this->money($w['K_13']) . '</tns:K_13>';
            if (abs($w['K_15']) >= 0.005) $xml .= '<tns:K_15>' . $this->money($w['K_15']) . '</tns:K_15>';
            if (abs($w['K_16']) >= 0.005) $xml .= '<tns:K_16>' . $this->money($w['K_16']) . '</tns:K_16>';
            if (abs($w['K_17']) >= 0.005) $xml .= '<tns:K_17>' . $this->money($w['K_17']) . '</tns:K_17>';
            if (abs($w['K_18']) >= 0.005) $xml .= '<tns:K_18>' . $this->money($w['K_18']) . '</tns:K_18>';
            if (abs($w['K_19']) >= 0.005) $xml .= '<tns:K_19>' . $this->money($w['K_19']) . '</tns:K_19>';
            if (abs($w['K_20']) >= 0.005) $xml .= '<tns:K_20>' . $this->money($w['K_20']) . '</tns:K_20>';
            $xml .= '</tns:SprzedazWiersz>';
        }

        $xml .= '<tns:SprzedazCtrl>';
        $xml .= '<tns:LiczbaWierszySprzedazy>' . count($sprzedazWiersze) . '</tns:LiczbaWierszySprzedazy>';
        $xml .= '<tns:PodatekNalezny>' . $this->money($totalVatNalezny) . '</tns:PodatekNalezny>';
        $xml .= '</tns:SprzedazCtrl>';

        // Sekcja zakupów — pusta (tylko CTRL z zerami)
        $xml .= '<tns:ZakupCtrl>';
        $xml .= '<tns:LiczbaWierszyZakupow>0</tns:LiczbaWierszyZakupow>';
        $xml .= '<tns:PodatekNaliczony>0.00</tns:PodatekNaliczony>';
        $xml .= '</tns:ZakupCtrl>';

        $xml .= '</tns:Ewidencja>';
        $xml .= '</tns:JPK>';
        return $xml;
    }

    /**
     * Główny orchestrator — buduje kubełki K_xx dla danej faktury z uwzględnieniem:
     *  - waluty obcej (przelicznik na PLN przez currency_exchange),
     *  - procedury marży (VAT od marży, nie od pełnej sprzedaży),
     *  - korekt (delta vs faktura pierwotna),
     *  - faktur końcowych (delta po odjęciu sumy zaliczek).
     */
    private function buildInvoiceBuckets(object $inv): array
    {
        $type   = strtolower((string)($inv->type ?? ''));
        $fxRate = $this->getFxRate($inv);

        // Marża: VAT liczony od marży (sprzedaż - cena nabycia) per pozycja
        if ($type === 'margin') {
            return $this->buildMarginBuckets($inv, $fxRate);
        }

        // Standardowa agregacja netto/VAT
        $buckets = $this->aggregateVatBuckets($inv, $fxRate);

        // Korekta — delta vs pierwotna (zachowuje znak: +/-)
        if ($type === 'correction' && !empty($inv->parent_invoice)) {
            $parentFx       = $this->getFxRate($inv->parent_invoice);
            $parentBuckets  = $this->aggregateVatBuckets($inv->parent_invoice, $parentFx);
            $buckets = $this->subtractBuckets($buckets, $parentBuckets);
        }

        // Faktura końcowa — delta po odjęciu sumy zaliczek (siblings po parent_id)
        if ($type === 'final' && !empty($inv->sibling_advances)) {
            $sumAdvBuckets = [];
            foreach ($inv->sibling_advances as $adv) {
                $advFx       = $this->getFxRate($adv);
                $advBuckets  = $this->aggregateVatBuckets($adv, $advFx);
                $sumAdvBuckets = $this->addBuckets($sumAdvBuckets, $advBuckets);
            }
            $buckets = $this->subtractBuckets($buckets, $sumAdvBuckets);
        }

        return $buckets;
    }

    /**
     * Suma netto/VAT z invoice_contents per stawka VAT, z opcjonalnym przelicznikiem walutowym.
     */
    private function aggregateVatBuckets(object $inv, float $fxRate = 1.0): array
    {
        $buckets = [];
        foreach (($inv->invoice_contents ?? []) as $row) {
            $rateKey = $this->normalizeVatRate($row->vat->rate ?? null, $row->vat->name ?? null);
            if (!isset($buckets[$rateKey])) {
                $buckets[$rateKey] = ['net' => 0.0, 'vat' => 0.0];
            }
            $net = (float)($row->netto ?? 0);
            $vat = (float)($row->vat_amount ?? (($row->brutto ?? 0) - ($row->netto ?? 0)));
            $buckets[$rateKey]['net'] += $net * $fxRate;
            $buckets[$rateKey]['vat'] += $vat * $fxRate;
        }
        return $buckets;
    }

    /**
     * Procedura marży: VAT od marży (cena sprzedaży - cena nabycia) per pozycja.
     * Cena sprzedaży = quantity * price; cena nabycia = quantity * purchase_price.
     * Marża zł.brutto → netto = marża / (1+rate/100); vat = marża - netto.
     * Marża ujemna (strata) → 0 (nie redukuje VAT-u).
     */
    private function buildMarginBuckets(object $inv, float $fxRate = 1.0): array
    {
        $buckets = [];
        foreach (($inv->invoice_contents ?? []) as $row) {
            $rateKey = $this->normalizeVatRate($row->vat->rate ?? null, $row->vat->name ?? null);
            $rate    = (float)($row->vat->rate ?? 0);
            $qty     = (float)($row->quantity ?? 1);
            $sale    = (float)($row->price ?? 0) * $qty;
            $buy     = (float)($row->purchase_price ?? 0) * $qty;
            $marginBrutto = max(0.0, $sale - $buy) * $fxRate;
            $marginNet    = $rate > 0 ? round($marginBrutto / (1 + $rate / 100), 2) : $marginBrutto;
            $marginVat    = round($marginBrutto - $marginNet, 2);
            if (!isset($buckets[$rateKey])) {
                $buckets[$rateKey] = ['net' => 0.0, 'vat' => 0.0];
            }
            $buckets[$rateKey]['net'] += $marginNet;
            $buckets[$rateKey]['vat'] += $marginVat;
        }
        return $buckets;
    }

    /**
     * Zwraca kurs walutowy — 1.0 dla PLN, inaczej currency_exchange z faktury.
     */
    private function getFxRate(object $inv): float
    {
        $cur = strtoupper((string)($inv->currency ?? 'PLN'));
        if ($cur === 'PLN' || $cur === '') return 1.0;
        $rate = (float)($inv->currency_exchange ?? 0);
        return $rate > 0 ? $rate : 1.0;
    }

    private function subtractBuckets(array $a, array $b): array
    {
        foreach (['5', '8', '23', '0', 'zw'] as $rate) {
            $a[$rate]['net'] = ($a[$rate]['net'] ?? 0) - ($b[$rate]['net'] ?? 0);
            $a[$rate]['vat'] = ($a[$rate]['vat'] ?? 0) - ($b[$rate]['vat'] ?? 0);
        }
        return $a;
    }

    private function addBuckets(array $a, array $b): array
    {
        foreach (['5', '8', '23', '0', 'zw'] as $rate) {
            $a[$rate]['net'] = ($a[$rate]['net'] ?? 0) + ($b[$rate]['net'] ?? 0);
            $a[$rate]['vat'] = ($a[$rate]['vat'] ?? 0) + ($b[$rate]['vat'] ?? 0);
        }
        return $a;
    }

    private function normalizeVatRate($rate, ?string $name): string
    {
        if ($name !== null) {
            $n = strtolower(trim($name));
            if (str_contains($n, 'zw')) return 'zw';
            if (str_contains($n, 'np')) return 'np';
        }
        $r = (float)$rate;
        if ($r >= 22.5) return '23';
        if ($r >= 7.5)  return '8';
        if ($r >= 4.5)  return '5';
        return '0';
    }

    /**
     * Mapuje typ faktury na TypDokumentu w ewidencji JPK_V7M.
     * Pusta wartość = zwykła faktura sprzedażowa.
     *   FP  = faktura do paragonu
     *   RO  = dokument zbiorczy z kas fiskalnych (raport okresowy)
     *   WEW = dowód wewnętrzny
     */
    /**
     * Zwraca typ procedury marży dla JPK_V7M:
     *   "MR_T"  — usługi turystyki (margin_type=travel)
     *   "MR_UZ" — towary używane / dzieła sztuki / przedmioty kolekcjonerskie
     *             (margin_type=used_goods / art / collectibles)
     *   ""      — brak procedury marży
     * Domyślnie (gdy margin_type nieustawione) → MR_UZ (najczęstszy przypadek).
     */
    private function resolveMarginKind(object $inv): string
    {
        $type = strtolower(trim((string)($inv->margin_type ?? '')));
        if ($type === 'travel') return 'MR_T';
        if (in_array($type, ['used_goods', 'art', 'collectibles'], true)) return 'MR_UZ';
        // Brak rozróżnienia w danych → defaultem MR_UZ (najpopularniejsza marża)
        return 'MR_UZ';
    }

    /**
     * Zbiera unikalne kody GTU z pozycji faktury.
     * Zwraca tablicę liczb 1-13 (np. [1, 7, 13]) gotową do emisji <GTU_xx>1</GTU_xx>.
     */
    private function collectGtuCodes(object $inv): array
    {
        $found = [];
        foreach (($inv->invoice_contents ?? []) as $row) {
            $g = trim((string)($row->gtu_code ?? ''));
            if ($g === '') continue;
            if (preg_match('/^GTU_?(\d{1,2})$/i', $g, $m)) {
                $n = (int)$m[1];
                if ($n >= 1 && $n <= 13) $found[$n] = true;
            }
        }
        $codes = array_keys($found);
        sort($codes, SORT_NUMERIC);
        return $codes;
    }

    /**
     * Czy faktura jest oznaczona TP (transakcja z podmiotem powiązanym).
     * faktury24 trzyma to w invoices.annotations (JSON): {"tp": "1"}.
     */
    private function resolveTpFlag(object $inv): bool
    {
        $ann = $inv->annotations ?? null;
        if (is_string($ann)) {
            $ann = json_decode($ann, true);
        }
        return is_array($ann) && isset($ann['tp']) && (string)$ann['tp'] === '1';
    }

    private function resolveTypDokumentu(object $inv): string
    {
        $type = strtolower((string)($inv->type ?? ''));
        if ($type === 'correction')           return '';   // korekty traktujemy jak normalne (znak +/- w kwotach)
        if (!empty($inv->is_receipt_invoice)) return 'FP';
        if ($type === 'internal' || $type === 'internalevidence') return 'WEW';
        return '';
    }

    private function resolveCountryCode(?object $buyer): string
    {
        if (!$buyer) return 'PL';

        // Źródło: invoice_contractors.country (string, może być ISO "PL" lub nazwa "Polska")
        $country = strtoupper(trim((string)($buyer->country ?? '')));
        if ($country === '' || $country === 'POLSKA' || $country === 'POLAND') return 'PL';
        // GR (Grecja) → EL w JPK (XSD TKodKrajuJPK wyklucza GR, używa EL zgodnie z prefiksem VAT)
        if ($country === 'GR' || $country === 'GRECJA' || $country === 'GREECE') return 'EL';

        // 2-literowy kod ISO — sprawdź czy zgodny z TKodKrajuJPK pattern
        if (strlen($country) === 2 && $this->isValidJpkCountryCode($country)) return $country;

        // Mapowanie popularnych nazw krajów (PL i EN) → ISO
        $map = [
            'NIEMCY' => 'DE', 'GERMANY' => 'DE',
            'CZECHY' => 'CZ', 'CZECHIA' => 'CZ',
            'SŁOWACJA' => 'SK', 'SLOWACJA' => 'SK', 'SLOVAKIA' => 'SK',
            'WIELKA BRYTANIA' => 'GB', 'WIELKABRYTANIA' => 'GB', 'UNITED KINGDOM' => 'GB',
            'FRANCJA' => 'FR', 'FRANCE' => 'FR',
            'WŁOCHY' => 'IT', 'WLOCHY' => 'IT', 'ITALY' => 'IT', 'ITALIA' => 'IT',
            'HISZPANIA' => 'ES', 'SPAIN' => 'ES',
            'AUSTRIA' => 'AT',
            'HOLANDIA' => 'NL', 'NIDERLANDY' => 'NL', 'NETHERLANDS' => 'NL',
            'BELGIA' => 'BE', 'BELGIUM' => 'BE',
            'WĘGRY' => 'HU', 'WEGRY' => 'HU', 'HUNGARY' => 'HU',
            'LITWA' => 'LT', 'LITHUANIA' => 'LT',
            'ŁOTWA' => 'LV', 'LOTWA' => 'LV', 'LATVIA' => 'LV',
            'ESTONIA' => 'EE',
            'IRLANDIA' => 'IE', 'IRELAND' => 'IE',
            'DANIA' => 'DK', 'DENMARK' => 'DK',
            'SZWECJA' => 'SE', 'SWEDEN' => 'SE',
            'FINLANDIA' => 'FI', 'FINLAND' => 'FI',
            'NORWEGIA' => 'NO', 'NORWAY' => 'NO',
            'SZWAJCARIA' => 'CH', 'SWITZERLAND' => 'CH',
            'USA' => 'US', 'STANY ZJEDNOCZONE' => 'US', 'UNITED STATES' => 'US',
            'KANADA' => 'CA', 'CANADA' => 'CA',
            'UKRAINA' => 'UA', 'UKRAINE' => 'UA',
            'RUMUNIA' => 'RO', 'ROMANIA' => 'RO',
            'BUŁGARIA' => 'BG', 'BULGARIA' => 'BG',
            'CHORWACJA' => 'HR', 'CROATIA' => 'HR',
            'SŁOWENIA' => 'SI', 'SLOWENIA' => 'SI', 'SLOVENIA' => 'SI',
            'PORTUGALIA' => 'PT', 'PORTUGAL' => 'PT',
            'BIAŁORUŚ' => 'BY', 'BIALORUS' => 'BY', 'BELARUS' => 'BY',
            'TURCJA' => 'TR', 'TURKEY' => 'TR',
            'JAPONIA' => 'JP', 'JAPAN' => 'JP',
            'CHINY' => 'CN', 'CHINA' => 'CN',
        ];
        return $map[$country] ?? 'PL';
    }

    /**
     * Walidacja kodu kraju wg XSD TKodKrajuJPK (2 wielkie litery, GR wykluczony).
     */
    private function isValidJpkCountryCode(string $code): bool
    {
        return (bool)preg_match('/^([A-FH-Z][A-Z]|[A-Z][A-QS-Z])$/', $code);
    }

    private function formatAddress(?object $entity): string
    {
        if (!$entity) return '';
        $parts = [];
        if (!empty($entity->street))      $parts[] = $entity->street;
        if (!empty($entity->zip))         $parts[] = $entity->zip;
        if (!empty($entity->city))        $parts[] = $entity->city;
        return implode(', ', $parts);
    }

    private function formatDate($date): string
    {
        if ($date === null || $date === '') return '';
        # Cake\I18n\Date (Chronos\ChronosDate) NIE implementuje DateTimeInterface — to date-only type.
        # Dlatego sprawdzamy ogólnie: każdy obiekt z metodą format() (DateTime, DateTimeImmutable,
        # Chronos, ChronosDate, Cake\I18n\Date, Cake\I18n\DateTime).
        if (is_object($date) && method_exists($date, 'format')) {
            return $date->format('Y-m-d');
        }
        if (is_string($date)) return substr($date, 0, 10);
        return '';
    }

    private function stripNip(string $nip): string
    {
        return preg_replace('/\D+/', '', $nip);
    }

    /**
     * Format kwoty zgodny z TKwotowy XSD: decimal z dokładnie 2 miejscami po przecinku.
     */
    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * Skraca string do max długości (typ TZnakowyJPK = max 256 znaków).
     */
    private function truncate(string $s, int $maxLen): string
    {
        if (mb_strlen($s) <= $maxLen) return $s;
        return mb_substr($s, 0, $maxLen);
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

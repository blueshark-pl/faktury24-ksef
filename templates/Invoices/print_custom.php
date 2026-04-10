<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Invoice $invoice
 * @var string  $cur              — kod waluty np. 'EUR'
 * @var float   $fxRate           — kurs waluty do PLN (0 jeśli PLN)
 * @var mixed   $fxDate           — data kursu
 * @var string  $fxTable          — tabela NBP np. 'A/012/2026'
 * @var string  $lang             — 'pl' lub 'en'
 * @var array   $ann              — adnotacje faktury
 * @var bool    $hasReverseCharge — odwrotne obciążenie
 */

/* ─── słownik tłumaczeń PL/EN ─── */
$t = ($lang === 'en') ? [
    'inv_type_suffix' => [
        'vat'=>'VAT INVOICE','novat'=>'INVOICE','proforma'=>'PROFORMA INVOICE',
        'advance'=>'ADVANCE INVOICE','correction'=>'CREDIT NOTE','margin'=>'MARGIN INVOICE',
        'internal'=>'INTERNAL INVOICE','oss'=>'OSS INVOICE','final'=>'FINAL INVOICE','currency'=>'INVOICE',
    ],
    'original'        => 'Original',
    'draft'           => 'DRAFT — document not approved',
    'issue_date'      => 'Issue date',
    'sold_date'       => 'Sale date',
    'due_date'        => 'Payment due',
    'seller'          => 'Seller',
    'buyer'           => 'Buyer',
    'no'              => 'No.',
    'item_name'       => 'Description',
    'qty'             => 'Qty',
    'unit'            => 'Unit',
    'price_net'       => 'Unit price',
    'vat_rate'        => 'VAT',
    'net'             => 'Net',
    'vat_amt'         => 'VAT amt',
    'gross'           => 'Gross',
    'total_due'       => 'TOTAL DUE',
    'vat_rate_col'    => 'VAT rate',
    'net_total'       => 'Net total',
    'vat_total'       => 'VAT total',
    'gross_total'     => 'Gross total',
    'overall'         => 'TOTAL',
    'add_info'        => 'Additional information',
    'payment_info'    => 'Payment information',
    'pay_method'      => 'Payment method',
    'pay_due'         => 'Due date',
    'paid'            => 'Amount paid',
    'remaining'       => 'Balance due',
    'bank'            => 'Bank',
    'account'         => 'Account no.',
    'in_words'        => 'In words',
    'notes'           => 'Notes',
    'fx_title'        => 'Exchange rate',
    'fx_rate'         => 'Rate',
    'fx_date'         => 'NBP date',
    'fx_table'        => 'NBP table',
    'fx_vat_pln'      => 'VAT (PLN)',
    'fx_gross_pln'    => 'Gross (PLN)',
    'fx_legal'        => 'Amounts shown in document currency (%s). VAT in PLN pursuant to Art. 106e(11) of the Polish VAT Act.',
    'fx_no_rate'      => 'No NBP exchange rate — please enter rate manually.',
    'rc_title'        => 'REVERSE CHARGE',
    'rc_text'         => 'VAT to be accounted for by the buyer pursuant to Art. 17(1)(7) or 17(1)(8) of the Polish VAT Act.',
    'rc_buyer_ref'    => 'Buyer\'s VAT No.',
    'pay_transfer'    => 'Bank transfer',
    'pay_cash'        => 'Cash',
    'pay_compensation'=> 'Set-off',
    'sig_receiver'    => 'Authorised signatory\n(buyer)',
    'sig_date'        => 'Date of receipt',
    'sig_issuer'      => 'Authorised signatory\n(seller)',
    'print_btn'       => '🖨 Print / Save PDF',
    'close_btn'       => '✕ Close',
    'lang_pl'         => '🇵🇱 Polski',
    'lang_en'         => '🇬🇧 English',
    'margin_note'     => 'VAT margin scheme – VAT is calculated on the margin only (Art. 120 Polish VAT Act).',
    'draft_footer'    => 'DRAFT — for review only',
] : [
    'inv_type_suffix' => [
        'vat'=>'FAKTURA VAT','novat'=>'FAKTURA','proforma'=>'FAKTURA PROFORMA',
        'advance'=>'FAKTURA ZALICZKOWA','correction'=>'FAKTURA KORYGUJĄCA','margin'=>'FAKTURA MARŻY',
        'internal'=>'FAKTURA WEWNĘTRZNA','oss'=>'FAKTURA OSS','final'=>'FAKTURA KOŃCOWA','currency'=>'FAKTURA WALUTOWA',
    ],
    'original'        => 'Oryginał',
    'draft'           => 'WERSJA ROBOCZA — dokument niezatwierdony',
    'issue_date'      => 'Data wystawienia',
    'sold_date'       => 'Data sprzedaży',
    'due_date'        => 'Termin płatności',
    'seller'          => 'Sprzedawca',
    'buyer'           => 'Nabywca',
    'no'              => 'Lp',
    'item_name'       => 'Nazwa towaru / usługi',
    'qty'             => 'Ilość',
    'unit'            => 'J.m.',
    'price_net'       => 'Cena netto',
    'vat_rate'        => 'VAT',
    'net'             => 'Wartość netto',
    'vat_amt'         => 'Kwota VAT',
    'gross'           => 'Brutto',
    'total_due'       => 'RAZEM DO ZAPŁATY',
    'vat_rate_col'    => 'Stawka VAT',
    'net_total'       => 'Wartość netto',
    'vat_total'       => 'Kwota VAT',
    'gross_total'     => 'Wartość brutto',
    'overall'         => 'OGÓŁEM',
    'add_info'        => 'Dodatkowe informacje',
    'payment_info'    => 'Informacje o płatności',
    'pay_method'      => 'Sposób płatności',
    'pay_due'         => 'Termin płatności',
    'paid'            => 'Zapłacono',
    'remaining'       => 'Pozostało do zapłaty',
    'bank'            => 'Bank',
    'account'         => 'Nr konta',
    'in_words'        => 'Słownie',
    'notes'           => 'Uwagi',
    'fx_title'        => 'Kurs waluty',
    'fx_rate'         => 'Kurs',
    'fx_date'         => 'Data kursu (NBP)',
    'fx_table'        => 'Tabela NBP',
    'fx_vat_pln'      => 'VAT (PLN)',
    'fx_gross_pln'    => 'Brutto (PLN)',
    'fx_legal'        => 'Kwoty netto i brutto wykazane są w walucie dokumentu (%s). VAT należny wykazany w PLN zgodnie z art. 106e ust. 11 ustawy o VAT.',
    'fx_no_rate'      => 'Brak kursu NBP — wprowadź kurs ręcznie w ustawieniach faktury.',
    'rc_title'        => 'ODWROTNE OBCIĄŻENIE',
    'rc_text'         => 'Podatek VAT rozlicza nabywca zgodnie z art. 17 ust. 1 pkt 7 lub 8 ustawy o VAT.',
    'rc_buyer_ref'    => 'Nr VAT nabywcy',
    'pay_transfer'    => 'Przelew bankowy',
    'pay_cash'        => 'Gotówka',
    'pay_compensation'=> 'Kompensata',
    'sig_receiver'    => "Podpis osoby upoważnionej\ndo odbioru faktury",
    'sig_date'        => 'Data odbioru',
    'sig_issuer'      => "Podpis i pieczęć\nwystawcy",
    'print_btn'       => '🖨 Drukuj / Zapisz PDF',
    'close_btn'       => '✕ Zamknij',
    'lang_pl'         => '🇵🇱 Polski',
    'lang_en'         => '🇬🇧 English',
    'margin_note'     => 'Sprzedaż w procedurze marży. VAT naliczany wyłącznie od marży zgodnie z art. 120 ustawy o VAT.',
    'draft_footer'    => 'WERSJA ROBOCZA — tylko do podglądu',
];

/* ─── helpers ─── */
$fdate = function($v) use ($lang): string {
    if (!$v) return '—';
    if ($v instanceof \DateTimeInterface) return $lang === 'en' ? $v->format('Y-m-d') : $v->format('d.m.Y');
    $s = substr((string)$v, 0, 10);
    if ($lang === 'en') return $s;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) return $m[3].'.'.$m[2].'.'.$m[1];
    return $s;
};
$money = fn($v, $c = 'PLN') => number_format((float)$v, 2, ',', ' ') . ' ' . strtoupper((string)$c);
$num4  = fn($v) => number_format((float)$v, 4, ',', ' ');
$num2  = fn($v) => number_format((float)$v, 2, ',', ' ');

/* ─── dane stron ─── */
$seller = $invoice->invoice_company_details ?? $invoice->invoice_company_detail ?? $invoice->company ?? null;
$buyer  = $invoice->invoice_contractor ?? null;
$bankName    = $seller->bank_name    ?? null;
$bankAccount = $seller->bank_account ?? null;

/* ─── typ faktury ─── */
$typeName = $t['inv_type_suffix'][$invoice->type ?? ''] ?? ($t['inv_type_suffix']['vat']);
$isMargin  = ($invoice->type ?? '') === 'margin';
$isNoVat   = ($invoice->type ?? '') === 'novat';
$isForeign = ($cur !== 'PLN');

/* ─── odwrotne obciążenie — dodatkowe dane ─── */
$rcBuyerVat = $buyer->vat_eu ?? $buyer->vat_prefix ?? null;
if ($rcBuyerVat === null && !empty($buyer->nip) && !empty($buyer->vat_prefix ?? null)) {
    $rcBuyerVat = ($buyer->vat_prefix ?? '') . $buyer->nip;
}

/* ─── dodatkowe opisy — grupowanie wg nr_wiersza ─── */
$addDescsByRow = [];
foreach (($invoice->invoice_additional_descriptions ?? []) as $d) {
    $nr = (int)($d->nr_wiersza ?? 0);
    $addDescsByRow[$nr][] = $d;
}

/* ─── VAT summary ─── */
$vatSummary = [];
if (!$isMargin && !empty($invoice->invoice_contents)) {
    foreach ($invoice->invoice_contents as $it) {
        $rate = isset($it->vat->rate) ? (float)$it->vat->rate : 0.0;
        $name = $it->vat->name ?? '0%';
        if (!isset($vatSummary[$rate])) $vatSummary[$rate] = ['name'=>$name,'netto'=>0.0,'vat'=>0.0,'brutto'=>0.0];
        $net  = (float)$it->netto;
        $brut = (float)$it->brutto;
        $vatSummary[$rate]['netto']  += $net;
        $vatSummary[$rate]['vat']    += ($brut - $net);
        $vatSummary[$rate]['brutto'] += $brut;
    }
    ksort($vatSummary);
}

/* ─── kwota słownie ─── */
$__pl_words = function(int $n): string {
    $u=['','jeden','dwa','trzy','cztery','pięć','sześć','siedem','osiem','dziewięć'];
    $t=['dziesięć','jedenaście','dwanaście','trzynaście','czternaście','piętnaście','szesnaście','siedemnaście','osiemnaście','dziewiętnaście'];
    $d=['','dziesięć','dwadzieścia','trzydzieści','czterdzieści','pięćdziesiąt','sześćdziesiąt','siedemdziesiąt','osiemdziesiąt','dziewięćdziesiąt'];
    $h=['','sto','dwieście','trzysta','czterysta','pięćset','sześćset','siedemset','osiemset','dziewięćset'];
    $g=[['','',''],['tysiąc','tysiące','tysięcy'],['milion','miliony','milionów']];
    if ($n===0) return 'zero';
    $out=[]; $grp=0;
    while ($n>0 && $grp<count($g)) {
        $x=$n%1000; $n=intdiv($n,1000);
        if ($x>0) {
            $hh=intdiv($x,100); $dd=$x%100; $dd2=intdiv($dd,10); $uu=$dd%10;
            $c=[];
            if ($hh) $c[]=$h[$hh];
            if ($dd>=10&&$dd<20) $c[]=$t[$dd-10];
            else { if ($dd2) $c[]=$d[$dd2]; if ($uu) $c[]=$u[$uu]; }
            if ($grp>0) {
                $f=2;
                if ($dd==1) $f=0;
                elseif ($dd%10>=2&&$dd%10<=4&&!($dd%100>=12&&$dd%100<=14)) $f=1;
                $c[]=$g[$grp][$f];
            }
            array_unshift($out,implode(' ',$c));
        }
        $grp++;
    }
    return implode(' ',$out);
};
$__en_words = function(int $n) use (&$__en_words): string {
    if ($n === 0) return 'zero';
    $u = ['','one','two','three','four','five','six','seven','eight','nine',
          'ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen'];
    $d = ['','','twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];
    if ($n < 20)  return $u[$n];
    if ($n < 100) return $d[intdiv($n,10)] . ($n%10 ? '-'.$u[$n%10] : '');
    if ($n < 1000) return $u[intdiv($n,100)].' hundred'.($n%100 ? ' '.$__en_words($n%100) : '');
    if ($n < 1000000) return $__en_words(intdiv($n,1000)).' thousand'.($n%1000 ? ' '.$__en_words($n%1000) : '');
    return $__en_words(intdiv($n,1000000)).' million'.($n%1000000 ? ' '.$__en_words($n%1000000) : '');
};
$amountInWords = function($amount, $currency='PLN') use ($__pl_words, $__en_words, $lang): string {
    $currency = strtoupper((string)$currency);
    $amt = (float)$amount;
    $int = (int)floor($amt + 1e-8);
    $frac= (int)round(($amt - $int) * 100);
    $cents = sprintf('%02d', $frac);
    if ($lang === 'en') {
        $map = ['EUR'=>['euro','euro cent'],'USD'=>['dollar','cent'],'GBP'=>['pound','penny'],'PLN'=>['zloty','grosz']];
        $names = $map[$currency] ?? [strtolower($currency), 'cent'];
        return ucfirst($__en_words($int)) . ' ' . $names[0] . ' ' . $cents . '/100 ' . $names[1];
    }
    $decl = function(int $n, array $f): string {
        $n1=$n%10; $n2=$n%100;
        if ($n==1) return $f[0];
        if ($n1>=2&&$n1<=4&&!($n2>=12&&$n2<=14)) return $f[1];
        return $f[2];
    };
    if ($currency==='PLN') return trim($__pl_words($int).' '.$decl($int,['złoty','złote','złotych']).' '.$cents.'/100 '.$decl($frac,['grosz','grosze','groszy']));
    if ($currency==='EUR') return trim($__pl_words($int).' euro '.$cents.'/100 '.$decl($frac,['eurocent','eurocenty','eurocentów']));
    if ($currency==='USD') return trim($__pl_words($int).' '.$decl($int,['dolar','dolary','dolarów']).' '.$cents.'/100 '.$decl($frac,['cent','centy','centów']));
    return trim($__pl_words($int).' '.strtoupper($currency).' '.$cents.'/100');
};

/* ─── tłumaczenie fraz PL→EN w nazwach pozycji ─── */
$__phrasesEN = [
    // usługi spedycyjne / transportowe
    'Usługa spedycyjna'          => 'Freight forwarding service',
    'Usługa transportowa'        => 'Transport service',
    'Usługa kurierska'           => 'Courier service',
    'Usługa logistyczna'         => 'Logistics service',
    'Załadunek'                  => 'Loading',
    'Rozładunek'                 => 'Unloading',
    'Przeładunek'                => 'Transshipment',
    'Zlecenie'                   => 'Order',
    'Zlecenie transportowe'      => 'Transport order',
    'Zlecenie spedycyjne'        => 'Forwarding order',
    'Transport'                  => 'Transport',
    'Fracht'                     => 'Freight',
    'Fracht morski'              => 'Sea freight',
    'Fracht lotniczy'            => 'Air freight',
    'Fracht drogowy'             => 'Road freight',
    'Magazynowanie'              => 'Warehousing',
    'Składowanie'                => 'Storage',
    'Odprawa celna'              => 'Customs clearance',
    'Ubezpieczenie'              => 'Insurance',
    'Ubezpieczenie ładunku'      => 'Cargo insurance',
    'Opłata drogowa'             => 'Road toll',
    'Opłata manipulacyjna'       => 'Handling fee',
    'Opłata terminalowa'         => 'Terminal fee',
    'Dopłata paliwowa'           => 'Fuel surcharge',
    'Dopłata drogowa'            => 'Road surcharge',
    'Data załadunku'             => 'Loading date',
    'Data rozładunku'            => 'Unloading date',
    'Miejsce załadunku'          => 'Loading place',
    'Miejsce rozładunku'         => 'Unloading place',
    'Dostawa'                    => 'Delivery',
    'Odbiór'                     => 'Collection',
    'Przewóz'                    => 'Carriage',
    'Kierowca'                   => 'Driver',
    'Pojazd'                     => 'Vehicle',
    'Naczepa'                    => 'Semi-trailer',
    'Kontener'                   => 'Container',
    'Paleta'                     => 'Pallet',
    // ogólne
    'Usługa'                     => 'Service',
    'Towar'                      => 'Goods',
    'Produkt'                    => 'Product',
    'Wynagrodzenie'              => 'Remuneration',
    'Prowizja'                   => 'Commission',
    'Abonament'                  => 'Subscription',
    'Faktura'                    => 'Invoice',
    'Zaliczka'                   => 'Advance payment',
    'Przedpłata'                 => 'Prepayment',
    'Netto'                      => 'Net',
    'Brutto'                     => 'Gross',
    'Razem'                      => 'Total',
];
$translatePhrase = function(string $text) use ($__phrasesEN, $lang): string {
    if ($lang !== 'en') return $text;
    // najpierw spróbuj dopasować pełną frazę (case-insensitive)
    foreach ($__phrasesEN as $pl => $en) {
        if (mb_strtolower($text) === mb_strtolower($pl)) return $en;
    }
    // zamień pasujące podfrazy (dłuższe pierwsze, żeby uniknąć częściowych zastąpień)
    $sorted = $__phrasesEN;
    uksort($sorted, fn($a,$b) => mb_strlen($b) <=> mb_strlen($a));
    foreach ($sorted as $pl => $en) {
        $text = preg_replace('/\b'.preg_quote($pl,'/').'(?=[,.:;\s]|$)/ui', $en, $text);
    }
    return $text;
};

/* ─── kurs — VAT/brutto w PLN ─── */
$taxPln   = $isForeign && $fxRate > 0 ? round((float)($invoice->tax   ?? 0) * $fxRate, 2) : null;
$totalPln = $isForeign && $fxRate > 0 ? round((float)($invoice->total ?? 0) * $fxRate, 2) : null;
$fxDateStr = $fxDate instanceof \DateTimeInterface ? $fxDate->format('Y-m-d') : ($fxDate ? substr((string)$fxDate, 0, 10) : null);

/* ─── URL przełączenia języka ─── */
$urlPl = $this->Url->build(['action' => 'printCustom', $invoice->id, '?' => ['lang' => 'pl']]);
$urlEn = $this->Url->build(['action' => 'printCustom', $invoice->id, '?' => ['lang' => 'en']]);
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title><?= h($typeName) ?> <?= h($invoice->fullnumber ?: $invoice->id) ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; }
@page { size: A4 portrait; margin: .8cm 1cm 1.2cm 1cm; }
@media print {
    body { margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
}
@media screen {
    body { background: #f0f2f5; }
    .sheet { box-shadow: 0 4px 32px rgba(0,0,0,.12); margin: 24px auto; }
}
body { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 10pt; color: #222; line-height: 1.4; }
table, th, td, tr, thead, tbody, tfoot, span, div, p, strong, b { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; }
.sheet { background: #fff; max-width: 21cm; padding: .8cm 1cm; }

/* Nagłówek */
.hdr { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:.8cm; border-bottom:2.5px solid #1e40af; padding-bottom:.4cm; }
.hdr img { max-height:60px; max-width:200px; object-fit:contain; display:block; margin-left:auto; }
.inv-type { font-size:15pt; font-weight:700; color:#1e40af; text-transform:uppercase; letter-spacing:.04em; }
.inv-number { font-size:12pt; font-weight:700; color:#111; margin-top:3px; }
.inv-meta { font-size:8.5pt; color:#555; margin-top:4px; line-height:1.7; }
.badge-draft { display:inline-block; background:#fef3c7; color:#92400e; font-size:7.5pt; font-weight:700; padding:2px 7px; border-radius:3px; border:1px solid #fcd34d; margin-top:5px; }

/* Strony */
.parties { display:flex; gap:1cm; margin-bottom:.7cm; }
.party { flex:1; }
.party-label { font-size:7pt; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#6b7280; border-bottom:1px solid #e5e7eb; padding-bottom:2px; margin-bottom:4px; }
.party-name { font-size:9pt; font-weight:700; color:#111; word-break:break-word; }
.party-detail { font-size:8pt; color:#444; line-height:1.5; }

/* Summary */
.summary-section { display:flex; gap:.5cm; margin-bottom:.5cm; align-items:flex-start; }
.vat-table-wrap { flex:1; }

/* Tabela pozycji */
.items-table { width:100%; border-collapse:collapse; margin-bottom:.5cm; font-size:8.5pt; }
.items-table thead th { background:#1e40af; color:#fff; padding:4px 5px; text-align:center; font-weight:600; vertical-align:middle; font-size:8pt; }
.items-table thead th.left { text-align:left; }
.items-table tbody tr { page-break-inside:avoid; }
.items-table tbody td { padding:4px 5px; border-bottom:1px solid #e5e7eb; vertical-align:top; text-align:center; font-size:8pt; }
.items-table tbody td.left { text-align:left; word-break:break-word; }
.items-table tbody tr:nth-child(even) { background:#f8fafc; }
.items-table tfoot td { padding:6px; font-weight:700; background:#e0e7ff; border-top:2px solid #1e40af; color:#1e40af; white-space:nowrap; font-size:9.5pt; }

/* Opis dodatkowy pod pozycją */
.item-descs { border-left:3px solid #3b82f6; margin:3px 0 2px 2px; padding:3px 8px; background:#eff6ff; border-radius:0 4px 4px 0; font-family:'DejaVu Sans',Arial,sans-serif; }
.item-desc-row { display:flex; gap:8px; font-size:7.8pt; color:#334155; line-height:1.5; font-family:'DejaVu Sans',Arial,sans-serif; }
.item-desc-key { color:#6b7280; white-space:nowrap; min-width:90px; font-family:'DejaVu Sans',Arial,sans-serif; }
.item-desc-val { font-weight:600; flex:1; font-family:'DejaVu Sans',Arial,sans-serif; }

/* VAT summary */
.vat-table { width:100%; border-collapse:collapse; font-size:8.5pt; }
.vat-table th { background:#f1f5f9; padding:4px 6px; font-weight:600; border:1px solid #e5e7eb; text-align:center; }
.vat-table td { padding:4px 6px; border:1px solid #e5e7eb; text-align:right; }
.vat-table td.left { text-align:left; }
.vat-table tfoot td { font-weight:700; background:#e0e7ff; color:#1e40af; }
.total-box { min-width:5.5cm; text-align:right; }
.total-label { font-size:8pt; color:#6b7280; }
.total-amount { font-size:12pt; font-weight:700; color:#1e40af; line-height:1.2; white-space:nowrap; }
.total-words { font-size:7pt; color:#555; margin-top:3px; white-space:nowrap; }

/* Odwrotne obciążenie */
.rc-box { border:2px solid #dc2626; background:#fff5f5; border-radius:6px; padding:10px 14px; margin-bottom:.5cm; }
.rc-box-title { font-size:11pt; font-weight:700; color:#dc2626; letter-spacing:.04em; margin-bottom:5px; }
.rc-text { font-size:9pt; color:#7f1d1d; line-height:1.5; }
.rc-buyer { margin-top:6px; font-size:8.5pt; }

/* Kurs walut */
.fx-box { border:1.5px solid #bfdbfe; background:#eff6ff; border-radius:6px; padding:8px 12px; margin-bottom:.5cm; font-size:8.5pt; }
.fx-box-title { font-weight:700; color:#1e40af; font-size:9pt; margin-bottom:5px; }
.fx-grid { display:flex; gap:1.5cm; flex-wrap:wrap; }
.fx-item-label { color:#6b7280; font-size:7.8pt; }
.fx-item-val { font-weight:700; font-size:9.5pt; color:#111; }
.fx-legal { font-size:7.5pt; color:#6b7280; margin-top:5px; border-top:1px solid #bfdbfe; padding-top:5px; }

/* Dodatkowe opisy faktury */
.invoice-descs { border-left:3px solid #6366f1; background:#f5f3ff; border-radius:0 6px 6px 0; padding:8px 12px; margin-bottom:.5cm; font-family:'DejaVu Sans',Arial,sans-serif; }
.invoice-descs-title { font-weight:700; color:#4f46e5; font-size:8.5pt; margin-bottom:5px; font-family:'DejaVu Sans',Arial,sans-serif; }
.invoice-desc-row { display:flex; gap:8px; font-size:8pt; color:#334155; line-height:1.6; border-bottom:1px solid #ede9fe; font-family:'DejaVu Sans',Arial,sans-serif; }
.invoice-desc-row:last-child { border-bottom:none; }

/* Płatność */
.payment-section { border:1px solid #e5e7eb; border-radius:6px; padding:8px 12px; margin-bottom:.5cm; font-size:8.5pt; }
.payment-grid { display:flex; gap:1cm; flex-wrap:wrap; margin-bottom:6px; }
.payment-item { min-width:4cm; }
.payment-label { color:#6b7280; font-size:7.8pt; }
.payment-val { font-weight:600; font-size:9pt; }
.bank-account { font-family:monospace; font-size:9pt; letter-spacing:.05em; }
.remaining-highlight { color:#dc2626; }

/* Uwagi */
.notes-box { background:#fafafa; border:1px solid #e5e7eb; border-radius:4px; padding:6px 10px; font-size:8.5pt; color:#444; margin-bottom:.4cm; }

/* Podpisy */
.signatures { display:flex; justify-content:space-between; margin-top:1.5cm; font-size:8pt; }
.sig-block { width:30%; text-align:center; }
.sig-line { border-top:1px solid #999; padding-top:5px; color:#6b7280; white-space:pre-line; }

/* Stopka robocza */
.draft-watermark { text-align:center; color:#fca5a5; font-size:8pt; font-weight:700; margin-top:.5cm; letter-spacing:.1em; }

/* Toolbar ekranowy */
.print-toolbar { position:fixed; top:16px; right:20px; display:flex; gap:6px; z-index:100; align-items:center; }
.print-btn { padding:7px 16px; border-radius:6px; border:none; cursor:pointer; font-size:9.5pt; font-weight:600; text-decoration:none; display:inline-block; }
.print-btn-primary { background:#1e40af; color:#fff; }
.print-btn-secondary { background:#f1f5f9; color:#374151; border:1px solid #d1d5db; }
.print-btn-active { background:#166534; color:#fff; }
.lang-sep { color:#d1d5db; font-size:10pt; }
<?php if (!empty($renderPdf)): ?>
/* ── DomPdf overrides: flex → table layout ── */
.hdr            { display:table; width:100%; border-bottom:2.5px solid #1e40af; margin-bottom:.8cm; padding-bottom:.4cm; }
.hdr > div      { display:table-cell; vertical-align:top; }
.hdr > div:last-child { text-align:right; width:220px; }
.hdr img        { max-height:55px; max-width:190px; }
.parties        { display:table; width:100%; margin-bottom:.7cm; }
.party          { display:table-cell; vertical-align:top; width:50%; padding-right:1cm; }
.party:last-child { padding-right:0; }
.summary-section { display:table; width:100%; margin-bottom:.5cm; }
.vat-table-wrap { display:table-cell; vertical-align:top; }
.total-box      { display:table-cell; vertical-align:top; text-align:right; width:6cm; padding-left:.5cm; }
.fx-grid        { display:table; width:100%; }
.fx-item        { display:table-cell; vertical-align:top; padding-right:1cm; }
.fx-item:last-child { padding-right:0; }
.payment-grid   { display:table; width:100%; margin-bottom:6px; }
.payment-item   { display:table-cell; vertical-align:top; padding-right:.8cm; }
.payment-item:last-child { padding-right:0; }
.signatures     { display:table; width:100%; margin-top:1.5cm; }
.sig-block      { display:table-cell; width:33%; text-align:center; }
.item-desc-row  { display:table; width:100%; font-family:'DejaVu Sans',Arial,sans-serif; }
.item-desc-key  { display:table-cell; color:#6b7280; white-space:nowrap; width:90px; padding-right:8px; font-family:'DejaVu Sans',Arial,sans-serif; }
.item-desc-val  { display:table-cell; font-weight:600; font-family:'DejaVu Sans',Arial,sans-serif; }
.invoice-desc-row { display:table; width:100%; border-bottom:1px solid #ede9fe; font-family:'DejaVu Sans',Arial,sans-serif; }
.invoice-desc-row > span:first-child { display:table-cell; width:130px; color:#6b7280; font-size:7.8pt; padding-right:8px; font-family:'DejaVu Sans',Arial,sans-serif; }
.invoice-desc-row > span:last-child  { display:table-cell; font-weight:600; font-family:'DejaVu Sans',Arial,sans-serif; }
.total-amount   { font-size:12pt; white-space:nowrap; }
<?php endif; ?>
</style>
</head>
<body>

<!-- ── Toolbar (tylko HTML, ukryty przy renderowaniu PDF) ── -->
<?php if (empty($renderPdf)): ?>
<div class="print-toolbar no-print">
    <a href="<?= h($urlPl) ?>" class="print-btn <?= $lang === 'pl' ? 'print-btn-active' : 'print-btn-secondary' ?>"><?= h($t['lang_pl']) ?></a>
    <span class="lang-sep">|</span>
    <a href="<?= h($urlEn) ?>" class="print-btn <?= $lang === 'en' ? 'print-btn-active' : 'print-btn-secondary' ?>"><?= h($t['lang_en']) ?></a>
    <span class="lang-sep">|</span>
    <button class="print-btn print-btn-secondary" onclick="window.close()"><?= h($t['close_btn']) ?></button>
    <button class="print-btn print-btn-primary" onclick="window.print()"><?= h($t['print_btn']) ?></button>
</div>
<?php endif; ?>

<div class="sheet">

<!-- ════ NAGŁÓWEK ════ -->
<div class="hdr">
    <div>
        <div class="inv-type"><?= h($typeName) ?></div>
        <div class="inv-number">Nr: <?= h($invoice->fullnumber ?: ('ID-'.$invoice->id)) ?></div>
        <div class="inv-meta"><?= h($t['original']) ?><?php if ($isForeign): ?> &nbsp;·&nbsp; <span style="background:#dbeafe;color:#1e40af;padding:1px 7px;border-radius:3px;font-weight:700"><?= h($cur) ?></span><?php endif; ?></div>
        <?php if (($invoice->workflow_status ?? '') === 'draft'): ?>
        <div><span class="badge-draft"><?= h($t['draft']) ?></span></div>
        <?php endif; ?>
    </div>
    <div style="text-align:right" class="inv-meta">
        <div style="margin-bottom:8px"><?= $this->element('logo') ?></div>
        <div><strong><?= h($t['issue_date']) ?>:</strong> <?= $fdate($invoice->date ?? $invoice->created ?? null) ?></div>
        <div><strong><?= h($t['sold_date']) ?>:</strong> <?= $fdate($invoice->sold_date ?? $invoice->date ?? null) ?></div>
        <?php if (!empty($invoice->paymentdate)): ?>
        <div><strong><?= h($t['due_date']) ?>:</strong> <?= $fdate($invoice->paymentdate) ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- ════ STRONY ════ -->
<div class="parties">
    <div class="party">
        <div class="party-label"><?= h($t['seller']) ?></div>
        <div class="party-name"><?= h($seller->name ?? '—') ?></div>
        <div class="party-detail">
            <?php
            $selAddr = trim(implode(', ', array_filter([
                trim(($seller->street ?? '') . ' ' . ($seller->building_number ?? '') . (isset($seller->flat_number) && $seller->flat_number ? '/'.$seller->flat_number : '')),
                trim(($seller->zip ?? $seller->postal_code ?? '') . ' ' . ($seller->city ?? '')),
            ])));
            echo h($selAddr) ?: '—';
            ?>
            <?php if (!empty($seller->nip)): ?><br>NIP: <?= h($seller->nip) ?><?php endif; ?>
            <?php
            $regs = json_decode((string)($seller->registers_json ?? ''), true) ?: [];
            foreach ($regs as $reg):
                if (!empty($reg['krs']))   echo '<br>KRS: '.h($reg['krs']);
                if (!empty($reg['regon'])) echo '<br>REGON: '.h($reg['regon']);
            endforeach;
            ?>
        </div>
    </div>
    <div class="party">
        <div class="party-label"><?= h($t['buyer']) ?></div>
        <div class="party-name"><?= h($buyer->name ?? '—') ?></div>
        <div class="party-detail">
            <?php if (!empty($buyer->street)): ?><?= h($buyer->street) ?><br><?php endif; ?>
            <?= h(trim(($buyer->zip ?? '') . ' ' . ($buyer->city ?? ''))) ?>
            <?php if (!empty($buyer->nip)): ?><br>NIP: <?= h($buyer->nip) ?><?php endif; ?>
            <?php if (!empty($buyer->vat_eu)): ?><br>VAT-EU: <?= h($buyer->vat_eu) ?><?php endif; ?>
            <?php if (!empty($buyer->country) && $buyer->country !== 'PL'): ?><br><?= h($buyer->country) ?><?php endif; ?>
        </div>
    </div>
</div>

<!-- ════ TABELA POZYCJI ════ -->
<?php if (!$isMargin): ?>
<table class="items-table">
    <thead>
        <tr>
            <th style="width:3%"><?= h($t['no']) ?></th>
            <th class="left" style="width:<?= $isNoVat ? '46%' : '32%' ?>"><?= h($t['item_name']) ?></th>
            <th style="width:6%"><?= h($t['qty']) ?></th>
            <th style="width:5%"><?= h($t['unit']) ?></th>
            <th style="width:9%"><?= h($t['price_net']) ?></th>
            <?php if (!$isNoVat): ?>
            <th style="width:5%"><?= h($t['vat_rate']) ?></th>
            <th style="width:8%"><?= h($t['net']) ?></th>
            <th style="width:7%"><?= h($t['vat_amt']) ?></th>
            <th style="width:9%"><?= h($t['gross']) ?></th>
            <?php else: ?>
            <th style="width:10%"><?= h($t['gross']) ?></th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php $rowIdx = 0; foreach ($invoice->invoice_contents as $it): $rowIdx++;
        $qty    = (float)($it->quantity ?? $it->count ?? 1);
        $unit   = $it->unit ?? ($lang === 'en' ? 'pcs' : 'szt.');
        $net    = (float)$it->netto;
        $brut   = (float)$it->brutto;
        $vatAmt = $brut - $net;
        $netUnit= $qty ? $net / $qty : $net;
        $rateName = $it->vat->name ?? (isset($it->vat->rate) ? $it->vat->rate.'%' : '0%');
        $rowDescs = $addDescsByRow[$rowIdx] ?? [];
    ?>
    <tr>
        <td><?= $rowIdx ?></td>
        <td class="left">
            <strong><?= h($translatePhrase($it->name ?? '')) ?></strong>
            <?php if (!empty($it->product_desc)): ?>
            <br><span style="color:#6b7280;font-size:7.8pt"><?= h($translatePhrase($it->product_desc)) ?></span>
            <?php endif; ?>
            <?php if (!empty($rowDescs)): ?>
            <table style="width:100%;border-left:3px solid #3b82f6;margin:3px 0 2px 2px;background:#eff6ff;font-size:7.8pt;font-family:'DejaVu Sans',Arial,sans-serif;border-collapse:collapse"><tbody>
                <?php foreach ($rowDescs as $d):
                    $k = trim((string)($d->klucz ?? ''));
                    $v = trim((string)($d->wartosc ?? ''));
                    if ($k===''&&$v==='') continue;
                ?>
                <tr style="font-family:'DejaVu Sans',Arial,sans-serif">
                    <td style="color:#6b7280;white-space:nowrap;width:90px;padding:1px 6px 1px 6px;font-family:'DejaVu Sans',Arial,sans-serif"><?= h($translatePhrase($k)) ?>:</td>
                    <td style="font-weight:600;padding:1px 4px;font-family:'DejaVu Sans',Arial,sans-serif"><?= h($translatePhrase($v)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </td>
        <td><?= $num2($qty) ?></td>
        <td><?= h($unit) ?></td>
        <td><?= $num2($netUnit) ?></td>
        <?php if (!$isNoVat): ?>
        <td><?= h($rateName) ?></td>
        <td><?= $num2($net) ?></td>
        <td><?= $num2($vatAmt) ?></td>
        <td style="font-weight:700"><?= $num2($brut) ?></td>
        <?php else: ?>
        <td style="font-weight:700"><?= $num2($net) ?></td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="<?= $isNoVat ? '5' : '6' ?>" style="text-align:right;font-size:10pt">
                <?= h($t['total_due']) ?>:
            </td>
            <?php if (!$isNoVat): ?>
            <td style="text-align:right"><?= $num2($invoice->netto ?? 0) ?></td>
            <td style="text-align:right"><?= $num2($invoice->tax ?? 0) ?></td>
            <?php endif; ?>
            <td style="text-align:right;font-size:9.5pt;white-space:nowrap"><?= $money($invoice->total ?? 0, $cur) ?></td>
        </tr>
    </tfoot>
</table>
<?php else: /* MARŻA */ ?>
<table class="items-table">
    <thead>
        <tr>
            <th style="width:4%"><?= h($t['no']) ?></th>
            <th class="left" style="width:60%"><?= h($t['item_name']) ?></th>
            <th style="width:8%"><?= h($t['qty']) ?></th>
            <th style="width:8%"><?= h($t['unit']) ?></th>
            <th style="width:10%"><?= h($t['price_net']) ?></th>
            <th style="width:10%"><?= h($t['gross']) ?></th>
        </tr>
    </thead>
    <tbody>
    <?php $rowIdx=0; foreach ($invoice->invoice_contents as $it): $rowIdx++;
        $qty  = (float)($it->quantity ?? 1);
        $unit = $it->unit ?? ($lang === 'en' ? 'pcs' : 'szt.');
        $brut = (float)$it->brutto;
        $unitG= $qty ? $brut/$qty : $brut;
        $rowDescs = $addDescsByRow[$rowIdx] ?? [];
    ?>
    <tr>
        <td><?= $rowIdx ?></td>
        <td class="left">
            <strong><?= h($translatePhrase($it->name ?? '')) ?></strong>
            <?php if (!empty($it->product_desc)): ?><br><span style="color:#6b7280;font-size:7.8pt"><?= h($translatePhrase($it->product_desc)) ?></span><?php endif; ?>
            <?php if (!empty($rowDescs)): ?>
            <table style="width:100%;border-left:3px solid #3b82f6;margin:3px 0 2px 2px;background:#eff6ff;font-size:7.8pt;font-family:'DejaVu Sans',Arial,sans-serif;border-collapse:collapse"><tbody>
                <?php foreach ($rowDescs as $d): $k=trim((string)($d->klucz??'')); $v=trim((string)($d->wartosc??'')); if($k===''&&$v==='') continue; ?>
                <tr style="font-family:'DejaVu Sans',Arial,sans-serif">
                    <td style="color:#6b7280;white-space:nowrap;width:90px;padding:1px 6px 1px 6px;font-family:'DejaVu Sans',Arial,sans-serif"><?= h($translatePhrase($k)) ?>:</td>
                    <td style="font-weight:600;padding:1px 4px;font-family:'DejaVu Sans',Arial,sans-serif"><?= h($translatePhrase($v)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody></table>
            <?php endif; ?>
        </td>
        <td><?= $num2($qty) ?></td>
        <td><?= h($unit) ?></td>
        <td><?= $num2($unitG) ?></td>
        <td style="font-weight:700"><?= $num2($brut) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" style="text-align:right;font-size:10pt"><?= h($t['total_due']) ?>:</td>
            <td style="text-align:right;font-size:9.5pt;white-space:nowrap"><?= $money($invoice->total ?? 0, $cur) ?></td>
        </tr>
    </tfoot>
</table>
<p style="font-size:8pt;color:#6b7280;margin-top:4px"><?= h($t['margin_note']) ?></p>
<?php endif; ?>

<!-- ════ PODSUMOWANIE VAT ════ -->
<?php if (!$isMargin && !$isNoVat && !empty($vatSummary)): ?>
<div class="summary-section">
    <div class="vat-table-wrap">
        <table class="vat-table">
            <thead>
                <tr>
                    <th class="left"><?= h($t['vat_rate_col']) ?></th>
                    <th><?= h($t['net_total']) ?></th>
                    <th><?= h($t['vat_total']) ?></th>
                    <th><?= h($t['gross_total']) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vatSummary as $row): ?>
            <tr>
                <td class="left"><?= h($row['name']) ?></td>
                <td><?= $num2($row['netto']) ?></td>
                <td><?= $num2($row['vat']) ?></td>
                <td><?= $num2($row['brutto']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="left"><?= h($t['overall']) ?></td>
                    <td><?= $num2($invoice->netto ?? 0) ?></td>
                    <td><?= $num2($invoice->tax ?? 0) ?></td>
                    <td><?= $num2($invoice->total ?? 0) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="total-box">
        <div class="total-label"><?= h($t['total_due']) ?>:</div>
        <div class="total-amount"><?= $money($invoice->total ?? 0, $cur) ?></div>
        <div class="total-words"><?= h($amountInWords($invoice->total ?? 0, $cur)) ?></div>
    </div>
</div>
<?php else: ?>
<div style="text-align:right;margin-bottom:.5cm">
    <div style="font-size:8pt;color:#6b7280"><?= h($t['total_due']) ?>:</div>
    <div style="font-size:16pt;font-weight:700;color:#1e40af"><?= $money($invoice->total ?? 0, $cur) ?></div>
    <div style="font-size:7.5pt;color:#555;margin-top:3px"><?= h($amountInWords($invoice->total ?? 0, $cur)) ?></div>
</div>
<?php endif; ?>

<!-- ════ ODWROTNE OBCIĄŻENIE ════ -->
<?php if ($hasReverseCharge): ?>
<div class="rc-box">
    <div class="rc-box-title">⟳ <?= h($t['rc_title']) ?></div>
    <div class="rc-text"><?= h($t['rc_text']) ?></div>
    <?php
    $rcVatNo = $buyer->vat_eu ?? null;
    if (!$rcVatNo && !empty($buyer->vat_prefix ?? null)) {
        $rcVatNo = ($buyer->vat_prefix ?? '') . ($buyer->nip ?? '');
    }
    if (!$rcVatNo && !empty($ann['buyer_vat_eu'] ?? null)) {
        $rcVatNo = $ann['buyer_vat_eu'];
    }
    if ($rcVatNo): ?>
    <div class="rc-buyer"><strong><?= h($t['rc_buyer_ref']) ?>:</strong> <?= h($rcVatNo) ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ════ KURS WALUT ════ -->
<?php if ($isForeign): ?>
<div class="fx-box">
    <div class="fx-box-title">💱 <?= h($t['fx_title']) ?> — <?= h($cur) ?></div>
    <div class="fx-grid">
        <?php if ($fxRate > 0): ?>
        <div class="fx-item">
            <div class="fx-item-label"><?= h($t['fx_rate']) ?></div>
            <div class="fx-item-val">1 <?= h($cur) ?> = <?= $num4($fxRate) ?> PLN</div>
        </div>
        <?php endif; ?>
        <?php if ($fxDateStr): ?>
        <div class="fx-item">
            <div class="fx-item-label"><?= h($t['fx_date']) ?></div>
            <div class="fx-item-val"><?= h($fxDateStr) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($fxTable !== ''): ?>
        <div class="fx-item">
            <div class="fx-item-label"><?= h($t['fx_table']) ?></div>
            <div class="fx-item-val"><?= h($fxTable) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($taxPln !== null): ?>
        <div class="fx-item">
            <div class="fx-item-label"><?= h($t['fx_vat_pln']) ?></div>
            <div class="fx-item-val" style="color:#1e40af"><?= $num2($taxPln) ?> PLN</div>
        </div>
        <?php endif; ?>
        <?php if ($totalPln !== null): ?>
        <div class="fx-item">
            <div class="fx-item-label"><?= h($t['fx_gross_pln']) ?></div>
            <div class="fx-item-val" style="color:#1e40af"><?= $num2($totalPln) ?> PLN</div>
        </div>
        <?php endif; ?>
    </div>
    <div class="fx-legal">
        <?php if ($fxRate > 0): ?>
        <?= h(sprintf($t['fx_legal'], $cur)) ?>
        <?php if ($fxDateStr): ?> (<?= h($fxDateStr) ?><?= $fxTable ? ', '.h($fxTable) : '' ?>)<?php endif; ?>
        <?php else: ?>
        <span style="color:#dc2626"><?= h($t['fx_no_rate']) ?></span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ════ DODATKOWE OPISY FAKTURY (nr_wiersza = 0) ════ -->
<?php $invoiceDescs = $addDescsByRow[0] ?? []; ?>
<?php if (!empty($invoiceDescs)): ?>
<div class="invoice-descs">
    <div class="invoice-descs-title"><?= h($t['add_info']) ?></div>
    <?php foreach ($invoiceDescs as $d):
        $k = trim((string)($d->klucz ?? ''));
        $v = trim((string)($d->wartosc ?? ''));
        if ($k===''&&$v==='') continue;
    ?>
    <div class="invoice-desc-row">
        <span style="min-width:120px;color:#6b7280;font-size:7.8pt"><?= h($k) ?>:</span>
        <span style="font-weight:600;flex:1"><?= h($v) ?></span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ════ UWAGI ════ -->
<?php if (!empty($invoice->description)): ?>
<div class="notes-box"><strong><?= h($t['notes']) ?>:</strong> <?= h($invoice->description) ?></div>
<?php endif; ?>

<!-- ════ PŁATNOŚĆ ════ -->
<?php
$alreadyPaid = (float)($invoice->alreadypaid ?? 0);
$remaining   = max(0.0, (float)($invoice->total ?? 0) - $alreadyPaid);
$payMethod   = $invoice->paymentmethod ?: 'transfer';
$methodLabel = [
    'transfer'    => $t['pay_transfer'],
    'cash'        => $t['pay_cash'],
    'compensation'=> $t['pay_compensation'],
][$payMethod] ?? ucfirst($payMethod);
$bankAccFormatted = $bankAccount ?? '—';
if ($bankAccount && strlen($bankAccount) >= 26) {
    $bankAccFormatted = trim(chunk_split($bankAccount, 4, ' '));
}
?>
<div class="payment-section">
    <div style="font-weight:700;color:#374151;margin-bottom:6px;font-size:9pt"><?= h($t['payment_info']) ?></div>
    <div class="payment-grid">
        <div class="payment-item">
            <div class="payment-label"><?= h($t['pay_method']) ?></div>
            <div class="payment-val"><?= h($methodLabel) ?></div>
        </div>
        <div class="payment-item">
            <div class="payment-label"><?= h($t['pay_due']) ?></div>
            <div class="payment-val"><?= $fdate($invoice->paymentdate ?? $invoice->disposaldate ?? null) ?></div>
        </div>
        <div class="payment-item">
            <div class="payment-label"><?= h($t['paid']) ?></div>
            <div class="payment-val"><?= $money($alreadyPaid, $cur) ?></div>
        </div>
        <div class="payment-item">
            <div class="payment-label"><?= h($t['remaining']) ?></div>
            <div class="payment-val <?= $remaining > 0 ? 'remaining-highlight' : '' ?>"><?= $money($remaining, $cur) ?></div>
        </div>
    </div>
    <?php if ($bankName || $bankAccount): ?>
    <div style="padding-top:6px;border-top:1px solid #e5e7eb;font-size:8.5pt">
        <?php if ($bankName): ?><span class="payment-label"><?= h($t['bank']) ?>: </span><strong><?= h($bankName) ?></strong>&nbsp;&nbsp;<?php endif; ?>
        <?php if ($bankAccount): ?><span class="payment-label"><?= h($t['account']) ?>: </span><span class="bank-account"><?= h($bankAccFormatted) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
    <div style="margin-top:6px;font-size:8pt;color:#555">
        <?= h($t['in_words']) ?>: <?= h($amountInWords($invoice->total ?? 0, $cur)) ?>
    </div>
</div>

<!-- ════ PODPISY ════ -->
<div class="signatures">
    <div class="sig-block">
        <div style="height:32px"></div>
        <div class="sig-line"><?= nl2br(h($t['sig_receiver'])) ?></div>
    </div>
    <div class="sig-block">
        <div style="height:32px"></div>
        <div class="sig-line"><?= h($t['sig_date']) ?></div>
    </div>
    <div class="sig-block">
        <div style="height:32px;text-align:right;font-size:8pt;color:#555;padding-right:4px"><?= h($invoice->user_name ?? '') ?></div>
        <div class="sig-line"><?= nl2br(h($t['sig_issuer'])) ?></div>
    </div>
</div>

<?php if (($invoice->workflow_status ?? '') === 'draft'): ?>
<div class="draft-watermark"><?= h($t['draft_footer']) ?></div>
<?php endif; ?>

</div><!-- /sheet -->
<?php if (empty($renderPdf)): ?>
<script>
if (new URLSearchParams(location.search).get('autoprint') === '1') {
    window.addEventListener('load', function() { setTimeout(function() { window.print(); }, 400); });
}
</script>
<?php endif; ?>
</body>
</html>

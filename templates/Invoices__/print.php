

<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Invoice $invoice
 */

/* ========== Helpers ========== */
$money = function($amount, $currency = 'PLN') {
    return number_format((float)$amount, 2, ',', ' ') . ' ' . strtoupper((string)$currency);
};
$formatDate = function($date) { return $date ? $date->format('Y-m-d') : '—'; };

/* PL słownie (int) + grosze XX/100 z odmianą waluty */
$__pl_words = function($n) {
    $units=['','jeden','dwa','trzy','cztery','pięć','sześć','siedem','osiem','dziewięć'];
    $teens=['dziesięć','jedenaście','dwanaście','trzynaście','czternaście','piętnaście','szesnaście','siedemnaście','osiemnaście','dziewiętnaście'];
    $tens=['','dziesięć','dwadzieścia','trzydzieści','czterdzieści','pięćdziesiąt','sześćdziesiąt','siedemdziesiąt','osiemdziesiąt','dziewięćdziesiąt'];
    $hunds=['','sto','dwieście','trzysta','czterysta','pięćset','sześćset','siedemset','osiemset','dziewięćset'];
    $groups=[
        ['','',''],
        ['tysiąc','tysiące','tysięcy'],
        ['milion','miliony','milionów'],
        ['miliard','miliardy','miliardów']
    ];
    if ($n == 0) return 'zero';
    $out=[]; $grp=0;
    while ($n > 0 && $grp < count($groups)) {
        $x=$n%1000; $n=intdiv($n,1000);
        if ($x>0) {
            $h=intdiv($x,100); $dd=$x%100; $d=intdiv($dd,10); $u=$dd%10;
            $chunk=[];
            if ($h) $chunk[]=$hunds[$h];
            if ($dd>=10 && $dd<20) { $chunk[]=$teens[$dd-10]; }
            else {
                if ($d) $chunk[]=$tens[$d];
                if ($u) $chunk[]=$units[$u];
            }
            if ($grp>0) {
                $form=2;
                if ($dd==1) $form=0;
                elseif ($dd%10>=2 && $dd%10<=4 && !($dd%100>=12 && $dd%100<=14)) $form=1;
                $chunk[]=$groups[$grp][$form];
            }
            array_unshift($out, implode(' ', $chunk));
        }
        $grp++;
    }
    return implode(' ', $out);
};
$amountInWords = function($amount, $currency='PLN') use ($__pl_words) {
    $currency=strtoupper((string)$currency);
    $amt=(float)$amount; $int=(int)floor($amt+1e-8); $frac=(int)round(($amt-$int)*100);
    $decl=function($n,$forms){ $n=(int)$n; $n1=$n%10; $n2=$n%100; if($n==1)return $forms[0]; if($n1>=2&&$n1<=4&&!($n2>=12&&$n2<=14))return $forms[1]; return $forms[2]; };
    if ($currency==='PLN') {
        return trim($__pl_words($int).' '.$decl($int,['złoty','złote','złotych']).' '.sprintf('%02d',$frac).'/100 '.$decl($frac,['grosz','grosze','groszy']));
    }
    if ($currency==='EUR') {
        return trim($__pl_words($int).' euro '.sprintf('%02d',$frac).'/100 '.$decl($frac,['eurocent','eurocenty','eurocentów']));
    }
    if ($currency==='USD') {
        return trim($__pl_words($int).' '.$decl($int,['dolar','dolary','dolarów']).' '.sprintf('%02d',$frac).'/100 '.$decl($frac,['cent','centy','centów']));
    }
    return trim($__pl_words($int).' '.strtoupper($currency).' '.sprintf('%02d',$frac).'/100');
};

/* ========== Dane sprzedawcy/nabywcy (snapshot > live) ========== */
$seller  = $invoice->invoice_company_details ?? $invoice->invoice_company_detail ?? $invoice->company ?? null;
$buyer   = $invoice->invoice_contractor ?? null;

$bankName    = $seller->bank_name    ?? null;
$bankAccount = $seller->bank_account ?? null;

/* ========== VAT grupowanie (jeśli nie masz $invoice->vat_contents) ========== */
$isMargin = (string)($invoice->type ?? '') === 'margin';
// VAT summary only for non-margin invoices
$vatSummary = [];
if (!$isMargin && !empty($invoice->invoice_contents)) {
    foreach ($invoice->invoice_contents as $it) {
        $rate = isset($it->vat) && isset($it->vat->rate) ? (float)$it->vat->rate : 0.0;
        $name = isset($it->vat) && isset($it->vat->name) ? (string)$it->vat->name : 'Bez VAT';
        if (!isset($vatSummary[$rate])) {
            $vatSummary[$rate] = ['name'=>$name,'netto'=>0.0,'tax'=>0.0,'brutto'=>0.0];
        }
        $net   = (float)$it->netto;
        $brut  = (float)$it->brutto;
        $vat   = $brut - $net;
        $vatSummary[$rate]['netto']  += $net;
        $vatSummary[$rate]['tax']    += $vat;
        $vatSummary[$rate]['brutto'] += $brut;
    }
    ksort($vatSummary);
}
?>
<!doctype html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Faktura</title>
<style>
    /* Dompdf-safe layout with page numbers */
    @page { 
        margin: 0.8cm; 
        size: auto;
    }
    @media print { 
        body { margin: 0; -webkit-print-color-adjust: exact; } 
    }

    body {
        margin: 0;
        font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
        color: #555;
        font-size: 14px;
    }
    .invoice-box {
        max-width: 800px;
        margin: auto;
        padding: 20px;
        padding-bottom: 50px;
    }
    table { width:100%; line-height: inherit; text-align:left; border-collapse: collapse; }
    td { padding:8px; vertical-align: top; }
    th { padding:8px; vertical-align: top; }

    /* Top */
    .top td { padding-bottom:20px; }
    .title {
        font-size:45px; line-height:45px; color:#333; text-transform:uppercase; font-weight:300;
    }
    .centered-id { text-align:center; font-weight:700; }
    .muted { color:#666; font-size:12px; text-align:right; }

    /* Parties */
    .information td { padding-bottom: 30px; }
    .information { page-break-inside: avoid; } /* Nie dziel sekcji sprzedawca/nabywca */
    .label-upper { font-weight:bold; font-size:8pt; text-transform:uppercase; letter-spacing:.3px; color:#444; margin-bottom:4px; }
    .party-block { font-size:12px; }
    .party-block div { margin: 1px 0; }

    /* Tables */
    .head td, .head th { background:#eee; border-bottom:1px solid #ddd; font-weight:bold; }
    .thin, .thin td, .thin th { border: .5px solid #ddd; }
    .invoice-items { page-break-inside: auto; }
    .invoice-items tr { page-break-inside: avoid; page-break-after: auto; }
    .invoice-items thead { display: table-header-group; } /* Powtarzaj nagłówek na nowych stronach */
    .invoice-items td, .invoice-items th { font-size:10px; text-align:center; vertical-align: middle; }

    /* Summary block (right) */
    .summary-right { border-top: 2px solid #eee; text-align:right; float:right; padding:5pt; }
    .summary-right .invoice-total { font-weight:700; }
    .summary-right .invoice-final { font-weight:300; padding-top:8pt; }
    .summary-right .invoice-exchange { font-weight:300; font-size:12px; }

    /* Big green bar */
  

    /* Payment block */
    .invoice-payment td, .invoice-payment th { font-size:10px; text-align:center; vertical-align: middle; }
    .invoice-payment { margin-top:20px; page-break-inside: avoid; } /* Nie dziel bloku płatności */

    /* Footer / signatures */
    .signatures { margin-top: 80px; font-size:10px; page-break-inside: avoid; } /* Nie dziel podpisów */
    .signatures td { padding: 8px 5px; }
    .sig-line { border-top: .5px solid #ddd; padding-top:8px; margin-top:40px; }
    .sig-left { text-align: left; width: 35%; }
    .sig-center { text-align: center; width: 30%; }
    .sig-right { text-align: right; width: 35%; }
    footer { 
        position: fixed; 
        font-size: 7px; 
        text-align: center; 
        bottom: 5px; 
        left: 0; 
        right: 0; 
        color: #bbb;
        padding: 5px 0;
        border-top: 1px solid #eee;
    }
    .page-number:before {
        content: counter(page);
    }
    .page-total:before {
        content: counter(pages);
    }

    /* Bigger "Razem do zapłaty" text */
    .to-pay-bar {
        background:#a7cf4c; color:#fff; font-size:20px!important; padding:12px 8px; font-weight:700;
    }
</style>
</head>
<body>

<?= $this->element('Invoices/print_preview', ['invoice' => $invoice, 'showPreviewFooter' => false]) ?>

<!-- Page numbers via Dompdf canvas -->
<script type="text/php">
if (isset($pdf)) {
    // Draw page numbers at bottom-right: "Strona {PAGE_NUM} z {PAGE_COUNT}"
    $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
    $size = 8;
    $text = 'Strona {PAGE_NUM} z {PAGE_COUNT}';
    $width = $pdf->get_width();
    $height = $pdf->get_height();
    $w = $fontMetrics->get_text_width($text, $font, $size);
    $x = $width - $w - 20;   // 20pt from right edge
    $y = $height - 18;       // 18pt from bottom edge
    $pdf->page_text($x, $y, $text, $font, $size, [0.47, 0.47, 0.47]);
}
</script>

</body>
</html>

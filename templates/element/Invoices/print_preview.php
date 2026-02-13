<?php
/**
 * Element: Invoices/print_preview
 * Renders the same markup as print.php without outer HTML/body, for on-screen preview.
 * Expects: $invoice (\App\Model\Entity\Invoice)
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

/* ========== VAT grupowanie ========== */
$isMargin = (string)($invoice->type ?? '') === 'margin';
$isNoVat  = (string)($invoice->type ?? '') === 'novat';
$isCorrection = (string)($invoice->type ?? '') === 'correction';
// Spróbujmy odczytać fakturę źródłową (korygowaną), jeśli jest dostępna w encji
$original = $invoice->parent ?? ($invoice->original ?? ($invoice->base_invoice ?? null));
$vatSummary = [];
if (!$isMargin && !$isCorrection && !empty($invoice->invoice_contents)) {
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
// Control preview-specific footer visibility (default true for on-screen view)
$showPreviewFooter = $showPreviewFooter ?? true;
?>

<style>
    /* Styles copied from print.php to ensure 1:1 preview */
    .invoice-box { max-width: 800px; margin: auto; padding: 20px; padding-bottom: 50px; background:#fff; }
    /* Subtelny cień tylko w podglądzie na ekranie (nie drukujemy cienia) */
    @media screen {
        .invoice-box { box-shadow: 0 8px 32px rgba(0,0,0,0.08); border: 1px solid #eee; border-radius: 4px; }
        body { background: #f5f6f8; }
    }
    table { width:100%; line-height: inherit; text-align:left; border-collapse: collapse; }
    td { padding:8px; vertical-align: top; }
    th { padding:8px; vertical-align: top; }
    .top td { padding-bottom:20px; }
    .title { font-size:45px; line-height:45px; color:#333; text-transform:uppercase; font-weight:300; }
    .centered-id { text-align:center; font-weight:700; }
    .muted { color:#666; font-size:12px; text-align:right; }
    .information td { padding-bottom: 30px; }
    .information { page-break-inside: avoid; }
    .label-upper { font-weight:bold; font-size:8pt; text-transform:uppercase; letter-spacing:.3px; color:#444; margin-bottom:4px; }
    .party-block { font-size:12px; }
    .party-block div { margin: 1px 0; }
    .head td, .head th { background:#eee; border-bottom:1px solid #ddd; font-weight:bold; }
    .thin, .thin td, .thin th { border: .5px solid #ddd; }
    .invoice-items { page-break-inside: auto; }
    .invoice-items tr { page-break-inside: avoid; page-break-after: auto; }
    .invoice-items thead { display: table-header-group; }
    .invoice-items td, .invoice-items th { font-size:10px; text-align:center; vertical-align: middle; }
    .summary-right { border-top: 2px solid #eee; text-align:right; float:right; padding:5pt; }
    .summary-right .invoice-total { font-weight:700; }
    .summary-right .invoice-final { font-weight:300; padding-top:8pt; }
    .summary-right .invoice-exchange { font-weight:300; font-size:12px; }
    .invoice-payment td, .invoice-payment th { font-size:10px; text-align:center; vertical-align: middle; }
    .invoice-payment { margin-top:20px; page-break-inside: avoid; }
    .signatures { margin-top: 80px; font-size:10px; page-break-inside: avoid; }
    .signatures td { padding: 8px 5px; }
    .sig-line { border-top: .5px solid #ddd; padding-top:8px; margin-top:40px; }
    .sig-left { text-align: left; width: 35%; }
    .sig-center { text-align: center; width: 30%; }
    .sig-right { text-align: right; width: 35%; }
    footer { font-size: 7px; text-align: center; color: #bbb; padding: 5px 0; border-top: 1px solid #eee; }
    .to-pay-bar { background:#a7cf4c; color:#fff; font-size:20px!important; padding:12px 8px; font-weight:700; }
</style>

<div class="invoice-box">
    <!-- TOP -->
    <table cellpadding="0" cellspacing="0">
        <tr class="top">
            <td colspan="3">
                <table>
                    <tr>
                        <td style="width:33%"></td>
                        <td style="width:33%">
                            <div class="centered-id"  style="font-size:16px; font-weight:700;">
                                <?php
                                $typeNames = [
                                    'vat'=>'FAKTURA VAT','novat'=>'FAKTURA','proforma'=>'FAKTURA PROFORMA','advance'=>'FAKTURA ZALICZKOWA',
                                    'correction'=>'FAKTURA KORYGUJĄCA','margin'=>'FAKTURA MARŻY','internal'=>'FAKTURA WEWNĘTRZNA','oss'=>'FAKTURA OSS','final'=>'FAKTURA KOŃCOWA'
                                ];
                                echo $typeNames[$invoice->type] ?? 'FAKTURA';
                                ?>
                            </div>
                            <div class="centered-id" style="font-size:16px;">Nr: <?= h($invoice->fullnumber ?: $invoice->id) ?></div>
                            <div class="centered-id" style="font-size:11px; color:#666;"><?= $invoice->type === 'proforma' ? 'Dokument proforma' : 'Oryginał' ?></div>
                            <?php if ($isCorrection): ?>
                                <div class="centered-id" style="font-size:11px; color:#888; margin-top:4px;">
                                    <?php
                                        $origNumber = null; $origDate = null;
                                        if ($original) {
                                            $origNumber = $original->fullnumber ?? $original->id ?? null;
                                            $origDate   = $original->date ?? $original->created ?? null;
                                        }
                                    ?>
                                    <?php if ($origNumber): ?>
                                        Korygowana: nr <?= h($origNumber) ?><?= $origDate ? ' z dnia '.h($formatDate($origDate)) : '' ?>
                                    <?php elseif (!empty($invoice->parent_id)): ?>
                                        Korygowana: ID <?= h($invoice->parent_id) ?>
                                    <?php else: ?>
                                        Korygowana: —
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="width:33%;vertical-align:top; padding-top:10px;">
                            <div class="muted" style="text-align:right; margin-bottom:4px;">Data wystawienia: <?= $formatDate($invoice->date ?? $invoice->created ?? null) ?></div>
                            <div class="muted" style="text-align:right;">Data sprzedaży: <?= $formatDate($invoice->date ?? null) ?></div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- PARTIES -->
        <tr class="information">
            <td colspan="3">
                <table>
                    <tr>
                        <td style="width:50%">
                            <div class="label-upper">Sprzedawca</div>
                            <div class="party-block">
                                <div><strong><?= h($seller->name ?? '—') ?></strong></div>
                                <div><?= h(($seller->city ?? '').' '.($seller->street ?? '').' '.($seller->building_number ?? '').(isset($seller->flat_number) && $seller->flat_number ? '/'.$seller->flat_number : '')) ?></div>
                                <div><?= h(($seller->zip ?? ($seller->postal_code ?? '')) .' '. ($seller->post ?? ($seller->city ?? ''))) ?></div>
                                <div><?= $seller && ($seller->nip ?? null) ? 'NIP: '.h($seller->nip) : '' ?></div>
                            </div>
                        </td>
                        <td></td>
                        <td style="width:50%">
                            <div class="label-upper">Nabywca</div>
                            <div class="party-block">
                                <div><strong><?= h($buyer->name ?? '—') ?></strong></div>
                                <div><?= h($buyer->street ?? '') ?></div>
                                <div><?= h(($buyer->zip ?? '').' '.($buyer->city ?? '')) ?></div>
                                <?php if (!empty($buyer->nip)): ?><div><?= 'NIP: '.h($buyer->nip) ?></div><?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ITEMS -->
    <?php if ($isCorrection && $original): ?>
        <?php
            // Przygotowanie danych: porównanie pozycji "było" vs "jest" oraz różnic.
            $origItems = !empty($original->invoice_contents) ? $original->invoice_contents : [];
            $newItems  = !empty($invoice->invoice_contents) ? $invoice->invoice_contents : [];

            $maxRows = max(count($origItems), count($newItems));

            $rowVals = function($it) {
                if (!$it) return [0.0, 0.0, 0.0, '0%'];
                $net  = (float)($it->netto ?? 0);
                $brut = (float)($it->brutto ?? 0);
                $vat  = $brut - $net;
                $rateName = '0%';
                if (isset($it->vat)) {
                    if (isset($it->vat->name)) $rateName = (string)$it->vat->name;
                    elseif (isset($it->vat->rate)) $rateName = ((float)$it->vat->rate).'%' ;
                }
                return [$net, $vat, $brut, $rateName];
            };

            $sumBlock = function($items) {
                $sNet=0.0; $sVat=0.0; $sTot=0.0; foreach ($items as $it) { $n=(float)($it->netto ?? 0); $b=(float)($it->brutto ?? 0); $sNet += $n; $sTot += $b; $sVat += ($b - $n); } return [$sNet,$sVat,$sTot];
            };

            // VAT podsumowania osobno dla BYŁO/JEST
            $vatSummaryOrig = [];
            foreach ($origItems as $it) {
                $rate = isset($it->vat) && isset($it->vat->rate) ? (float)$it->vat->rate : 0.0;
                $name = isset($it->vat) && isset($it->vat->name) ? (string)$it->vat->name : 'Bez VAT';
                if (!isset($vatSummaryOrig[$rate])) { $vatSummaryOrig[$rate] = ['name'=>$name,'netto'=>0.0,'tax'=>0.0,'brutto'=>0.0]; }
                $net=(float)($it->netto ?? 0); $brut=(float)($it->brutto ?? 0); $vat=$brut-$net;
                $vatSummaryOrig[$rate]['netto']+=$net; $vatSummaryOrig[$rate]['tax']+=$vat; $vatSummaryOrig[$rate]['brutto']+=$brut;
            }
            ksort($vatSummaryOrig);
            $vatSummaryNew = [];
            foreach ($newItems as $it) {
                $rate = isset($it->vat) && isset($it->vat->rate) ? (float)$it->vat->rate : 0.0;
                $name = isset($it->vat) && isset($it->vat->name) ? (string)$it->vat->name : 'Bez VAT';
                if (!isset($vatSummaryNew[$rate])) { $vatSummaryNew[$rate] = ['name'=>$name,'netto'=>0.0,'tax'=>0.0,'brutto'=>0.0]; }
                $net=(float)($it->netto ?? 0); $brut=(float)($it->brutto ?? 0); $vat=$brut-$net;
                $vatSummaryNew[$rate]['netto']+=$net; $vatSummaryNew[$rate]['tax']+=$vat; $vatSummaryNew[$rate]['brutto']+=$brut;
            }
            ksort($vatSummaryNew);

            [$sumNetO,$sumVatO,$sumTotO] = $sumBlock($origItems);
            [$sumNetN,$sumVatN,$sumTotN] = $sumBlock($newItems);
            $sumNetD = $sumNetN - $sumNetO; $sumVatD = $sumVatN - $sumVatO; $sumTotD = $sumTotN - $sumTotO;
        ?>

        <!-- Info o przyczynie korekty -->
        <div style="margin:8px 0 12px; font-size:11px; color:#444;">
            <strong>Przyczyna korekty:</strong> <?= h($invoice->description ?: '—') ?>
        </div>

        <table class="invoice-items thin" cellpadding="0" cellspacing="0">
            <tr class="head">
                <th rowspan="2" style="width:4%">Lp</th>
                <th rowspan="2" style="text-align:left">Nazwa towaru lub usługi</th>
                <th colspan="3">Było</th>
                <th colspan="3">Jest</th>
                <th colspan="3">Różnica</th>
            </tr>
            <tr class="head">
                <th>Netto</th><th>VAT</th><th>Brutto</th>
                <th>Netto</th><th>VAT</th><th>Brutto</th>
                <th>Netto</th><th>VAT</th><th>Brutto</th>
            </tr>

            <?php for ($i=0; $i<$maxRows; $i++):
                $o = $origItems[$i] ?? null; $n = $newItems[$i] ?? null;
                [$on,$ov,$ob,$orate] = $rowVals($o);
                [$nn,$nv,$nb,$nrate] = $rowVals($n);
                $dn = $nn - $on; $dv = $nv - $ov; $db = $nb - $ob;
                $name = $n->name ?? ($o->name ?? '—');
            ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td style="text-align:left;">
                    <?= h($name) ?>
                    <?php $desc = $n->product_desc ?? ($o->product_desc ?? null); if ($desc): ?><br><small><?= h($desc) ?></small><?php endif; ?>
                </td>
                <td><?= number_format($on, 2, ',', ' ') ?></td>
                <td><?= number_format($ov, 2, ',', ' ') ?></td>
                <td><?= number_format($ob, 2, ',', ' ') ?></td>
                <td><?= number_format($nn, 2, ',', ' ') ?></td>
                <td><?= number_format($nv, 2, ',', ' ') ?></td>
                <td><?= number_format($nb, 2, ',', ' ') ?></td>
                <td><?= number_format($dn, 2, ',', ' ') ?></td>
                <td><?= number_format($dv, 2, ',', ' ') ?></td>
                <td><?= number_format($db, 2, ',', ' ') ?></td>
            </tr>
            <?php endfor; ?>

            <!-- PODSUMOWANIE BYŁO/JEST/RÓŻNICA -->
            <tr class="head">
                <td colspan="2" style="text-align:right">RAZEM</td>
                <td><?= number_format($sumNetO, 2, ',', ' ') ?></td>
                <td><?= number_format($sumVatO, 2, ',', ' ') ?></td>
                <td><?= number_format($sumTotO, 2, ',', ' ') ?></td>
                <td><?= number_format($sumNetN, 2, ',', ' ') ?></td>
                <td><?= number_format($sumVatN, 2, ',', ' ') ?></td>
                <td><?= number_format($sumTotN, 2, ',', ' ') ?></td>
                <td><?= number_format($sumNetD, 2, ',', ' ') ?></td>
                <td><?= number_format($sumVatD, 2, ',', ' ') ?></td>
                <td><?= number_format($sumTotD, 2, ',', ' ') ?></td>
            </tr>

            <!-- ZIELONY PASEK DLA "PO KOREKCIE" -->
            <tr>
                <td colspan="11" class="to-pay-bar">
                    Razem do zapłaty (po korekcie): <strong><?= number_format((float)$invoice->total, 2, ',', ' ') . ' ' . strtoupper((string)$invoice->currency) ?></strong>
                </td>
            </tr>
        </table>

        <!-- PODSUMOWANIA VAT (Było / Jest / Różnica) -->
        <table class="thin" cellpadding="0" cellspacing="0" style="margin-top:8px;">
            <tr class="head">
                <td></td>
                <td>Netto</td>
                <td>Stawka</td>
                <td>VAT</td>
                <td>Brutto</td>
                <td style="width:8%"></td>
                <td>Netto</td>
                <td>Stawka</td>
                <td>VAT</td>
                <td>Brutto</td>
                <td style="width:8%"></td>
                <td>Netto</td>
                <td>VAT</td>
                <td>Brutto</td>
            </tr>
            <?php
                // Złóż listę stawek występujących w obu zestawieniach
                $rates = array_unique(array_merge(array_keys($vatSummaryOrig), array_keys($vatSummaryNew)));
                sort($rates);
                foreach ($rates as $rate):
                    $oRow = $vatSummaryOrig[$rate] ?? ['name'=>($vatSummaryNew[$rate]['name'] ?? '—'),'netto'=>0.0,'tax'=>0.0,'brutto'=>0.0];
                    $nRow = $vatSummaryNew[$rate]  ?? ['name'=>($vatSummaryOrig[$rate]['name'] ?? '—'),'netto'=>0.0,'tax'=>0.0,'brutto'=>0.0];
                    $dNet = $nRow['netto'] - $oRow['netto'];
                    $dVat = $nRow['tax']   - $oRow['tax'];
                    $dTot = $nRow['brutto']- $oRow['brutto'];
            ?>
            <tr>
                <td>Było</td>
                <td><?= number_format($oRow['netto'], 2, ',', ' ') ?></td>
                <td><?= h($oRow['name']) ?></td>
                <td><?= number_format($oRow['tax'], 2, ',', ' ') ?></td>
                <td><?= number_format($oRow['brutto'], 2, ',', ' ') ?></td>
                <td></td>
                <td><?= number_format($nRow['netto'], 2, ',', ' ') ?></td>
                <td><?= h($nRow['name']) ?></td>
                <td><?= number_format($nRow['tax'], 2, ',', ' ') ?></td>
                <td><?= number_format($nRow['brutto'], 2, ',', ' ') ?></td>
                <td></td>
                <td><?= number_format($dNet, 2, ',', ' ') ?></td>
                <td><?= number_format($dVat, 2, ',', ' ') ?></td>
                <td><?= number_format($dTot, 2, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="head">
                <td>OGÓŁEM</td>
                <td><?= number_format($sumNetO, 2, ',', ' ') ?></td>
                <td>—</td>
                <td><?= number_format($sumVatO, 2, ',', ' ') ?></td>
                <td><?= number_format($sumTotO, 2, ',', ' ') ?></td>
                <td></td>
                <td><?= number_format($sumNetN, 2, ',', ' ') ?></td>
                <td>—</td>
                <td><?= number_format($sumVatN, 2, ',', ' ') ?></td>
                <td><?= number_format($sumTotN, 2, ',', ' ') ?></td>
                <td></td>
                <td><?= number_format($sumNetD, 2, ',', ' ') ?></td>
                <td><?= number_format($sumVatD, 2, ',', ' ') ?></td>
                <td><?= number_format($sumTotD, 2, ',', ' ') ?></td>
            </tr>
        </table>

    <?php elseif (!$isMargin && !$isNoVat): ?>
        <table class="invoice-items thin" cellpadding="0" cellspacing="0">
            <tr class="head">
                <th rowspan="2">Lp</th>
                <th rowspan="2">Nazwa towaru lub usługi</th>
                <th rowspan="2">Symbol SWW/KU</th>
                <th rowspan="2">Ilość jedn.m.</th>
                <th rowspan="2">Cena netto bez rabatu</th>
                <th rowspan="2">Rabat (%)</th>
                <th rowspan="2">Cena jedn. netto</th>
                <th rowspan="2">Wartość netto</th>
                <th colspan="2">Podatek</th>
                <th rowspan="2">Wartość brutto</th>
            </tr>
            <tr class="head">
                <th>st</th>
                <th>Kwota</th>
            </tr>

            <?php
            $i=1;
            foreach ($invoice->invoice_contents as $it):
                $qty   = (float)($it->quantity ?? $it->count ?? 1);
                $unit  = $it->unit ?? 'szt.';
                $net   = (float)$it->netto;
                $brut  = (float)$it->brutto;
                $discP = (float)($it->discount_percent ?? 0);
                $netUnit = $qty ? $net / $qty : $net;
                $rateName = $it->vat->name ?? (isset($it->vat->rate)? ($it->vat->rate.'%') : '0%');
                $vatAmt   = $brut - $net;

                // Cena netto bez rabatu (wyliczana wstecz jeśli był rabat)
                $netBeforeDisc = $discP > 0 ? ($net / (1 - ($discP/100))) : $net;
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td style="text-align:left">
                    <?= h($it->name) ?>
                    <?php if (!empty($it->product_desc)): ?><br><small><?= h($it->product_desc) ?></small><?php endif; ?>
                </td>
                <td>—</td>
                <td><?= number_format($qty, 2, ',', ' ') . ' ' . h($unit) ?></td>
                <td><?= number_format($netBeforeDisc, 2, ',', ' ') ?></td>
                <td><?= number_format($discP, 2, ',', ' ') ?></td>
                <td><?= number_format($netUnit, 2, ',', ' ') ?></td>
                <td><?= number_format($net, 2, ',', ' ') ?></td>
                <td><?= h($rateName) ?></td>
                <td><?= number_format($vatAmt, 2, ',', ' ') ?></td>
                <td><?= number_format($brut, 2, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>

            <!-- SUMARYCZNY ZIELONY PASEK -->
            <tr>
                <td colspan="6" rowspan="2" class="to-pay-bar">
                    Razem do zapłaty: <strong><?= number_format((float)$invoice->total, 2, ',', ' ') . ' ' . strtoupper((string)$invoice->currency) ?></strong>
                </td>
                <td class="head">Razem</td>
                <td colspan="3"></td>
                <td><?= number_format((float)$invoice->total, 2, ',', ' ') ?></td>
            </tr>

            <!-- PODSUMOWANIE VAT PRAWA KOLUMNA -->
            <tr>
                <td colspan="5" style="padding:0;">
                    <table class="thin" cellpadding="0" cellspacing="0">
                        <tr class="head">
                            <td></td>
                            <td>Netto</td>
                            <td>st</td>
                            <td>VAT</td>
                            <td>Brutto</td>
                        </tr>
                        <?php foreach ($vatSummary as $rate => $row): ?>
                            <tr>
                                <td></td>
                                <td><?= number_format($row['netto'], 2, ',', ' ') ?></td>
                                <td><?= h($row['name']) ?></td>
                                <td><?= number_format($row['tax'], 2, ',', ' ') ?></td>
                                <td><?= number_format($row['brutto'], 2, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="head">
                            <td>OGÓŁEM</td>
                            <td><?= number_format((float)$invoice->netto, 2, ',', ' ') ?></td>
                            <td>—</td>
                            <td><?= number_format((float)$invoice->tax, 2, ',', ' ') ?></td>
                            <td><?= number_format((float)$invoice->total, 2, ',', ' ') ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    <?php elseif ($isNoVat): ?>
        <!-- NO-VAT PRINT (no tax columns) -->
        <table class="invoice-items thin" cellpadding="0" cellspacing="0">
            <tr class="head">
                <th>Lp</th>
                <th>Nazwa towaru lub usługi</th>
                <th>Symbol SWW/KU</th>
                <th>Ilość jedn.m.</th>
                <th>Cena bez rabatu</th>
                <th>Rabat (%)</th>
                <th>Cena jedn.</th>
                <th>Wartość netto</th>
                <th>Wartość brutto</th>
            </tr>

            <?php
            $i=1;
            foreach ($invoice->invoice_contents as $it):
                $qty   = (float)($it->quantity ?? $it->count ?? 1);
                $unit  = $it->unit ?? 'szt.';
                $net   = (float)$it->netto;
                $brut  = (float)$it->brutto;
                $discP = (float)($it->discount_percent ?? 0);
                $netUnit = $qty ? $net / $qty : $net;
                // Cena bez rabatu (wstecznie z wartości netto)
                $netBeforeDisc = $discP > 0 ? ($net / (1 - ($discP/100))) : $net;
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td style="text-align:left">
                    <?= h($it->name) ?>
                    <?php if (!empty($it->product_desc)): ?><br><small><?= h($it->product_desc) ?></small><?php endif; ?>
                </td>
                <td>—</td>
                <td><?= number_format($qty, 2, ',', ' ') . ' ' . h($unit) ?></td>
                <td><?= number_format($netBeforeDisc, 2, ',', ' ') ?></td>
                <td><?= number_format($discP, 2, ',', ' ') ?></td>
                <td><?= number_format($netUnit, 2, ',', ' ') ?></td>
                <td><?= number_format($net, 2, ',', ' ') ?></td>
                <td><?= number_format($brut, 2, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>

            <!-- SUMARYCZNY ZIELONY PASEK (bez podsumowania VAT) -->
            <tr>
                <td colspan="9" class="to-pay-bar">
                    Razem do zapłaty: <strong><?= number_format((float)$invoice->total, 2, ',', ' ') . ' ' . strtoupper((string)$invoice->currency) ?></strong>
                </td>
            </tr>
        </table>
    <?php else: ?>
        <!-- MARGIN PROCEDURE PRINT (no VAT breakdown for buyer) -->
        <?php
            $marginTypeLabel = null;
            $map = [
                'used_goods'   => 'towary używane',
                'art'          => 'dzieła sztuki',
                'collectibles' => 'przedmioty kolekcjonerskie',
                'travel'       => 'usługi turystyki',
            ];
            $mt = (string)($invoice->margin_type ?? '');
            if ($mt !== '') { $marginTypeLabel = $map[$mt] ?? $mt; }
        ?>
        <table class="invoice-items thin" cellpadding="0" cellspacing="0">
            <tr class="head">
                <th style="width:5%">Lp</th>
                <th style="width:60%">Nazwa towaru lub usługi</th>
                <th style="width:10%">Ilość</th>
                <th style="width:12%">Cena jedn. brutto</th>
                <th style="width:13%">Wartość brutto</th>
            </tr>
            <?php $i=1; foreach ($invoice->invoice_contents as $it):
                $qty   = (float)($it->quantity ?? 1);
                $unit  = $it->unit ?? 'szt.';
                $brut  = (float)$it->brutto;
                $unitGross = isset($it->price) ? (float)$it->price : ($qty ? ($brut / $qty) : $brut);
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td style="text-align:left">
                    <?= h($it->name) ?>
                    <?php if (!empty($it->product_desc)): ?><br><small><?= h($it->product_desc) ?></small><?php endif; ?>
                </td>
                <td><?= number_format($qty, 2, ',', ' ') . ' ' . h($unit) ?></td>
                <td><?= number_format($unitGross, 2, ',', ' ') ?></td>
                <td><?= number_format($brut, 2, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>

            <!-- SUMARYCZNY ZIELONY PASEK (no extra VAT columns) -->
            <tr>
                <td colspan="5" class="to-pay-bar">
                    Razem do zapłaty: <strong><?= number_format((float)$invoice->total, 2, ',', ' ') . ' ' . strtoupper((string)$invoice->currency) ?></strong>
                </td>
            </tr>
        </table>

        <!-- Margin legal note -->
        <div style="margin-top:8px; font-size:11px; color:#666;">
            Sprzedaż w procedurze marży<?= $marginTypeLabel ? ' – ' . h($marginTypeLabel) : '' ?>.
            Podatek VAT naliczany jest wyłącznie od marży zgodnie z art. 120 ustawy o VAT i nie jest wyszczególniany na fakturze.
        </div>
    <?php endif; ?>

    <?php
        // Informacja zgodna z prawem dla faktury walutowej: VAT w PLN wg kursu NBP
        $cur = strtoupper((string)($invoice->currency ?? 'PLN'));
        $isForeign = ($cur !== 'PLN');
        $fx = (float)($invoice->currency_exchange ?? 0);
        $fxDate = $invoice->currency_date ?? null;
    if (!$isMargin && !$isNoVat && $isForeign) {
            $rateText = '';
            if ($fx > 0) {
                $rateText = 'Kurs: 1 ' . h($cur) . ' = ' . number_format($fx, 4, ',', ' ') . ' PLN' . ($fxDate ? ' (NBP: ' . h((string)$fxDate) . ')' : '');
            }
            $taxPln = round(((float)($invoice->tax ?? 0)) * ($fx > 0 ? $fx : 1), 2);
    ?>
        <div style="margin-top:8px; font-size:11px; color:#444;">
            Kwoty netto i brutto wykazane są w walucie dokumentu (<?= h($cur) ?>).<br>
            VAT należny wykazany w PLN zgodnie z art. 106e ust. 11 ustawy o VAT.
            <?php if ($rateText): ?><br><em><?= $rateText ?></em><?php endif; ?>
            <div style="margin-top:4px;"><strong>VAT należny (PLN): <?= number_format($taxPln, 2, ',', ' ') ?> PLN</strong></div>
        </div>
    <?php } ?>

    <!-- PŁATNOŚĆ -->
    <?php
        $alreadyPaid = (float)($invoice->alreadypaid ?? 0);
        $remaining   = isset($invoice->remaining) ? (float)$invoice->remaining : max(0.0, (float)$invoice->total - $alreadyPaid);
        $payMethod   = $invoice->paymentmethod ?: 'transfer';
        $methodLabel = [
            'transfer' => 'Przelew',
            'compensation' => 'Kompensata',
            'cash' => 'Gotówka'
        ][$payMethod] ?? ucfirst((string)$payMethod);
        $payUntil    = $formatDate($invoice->paymentdate ?? $invoice->disposaldate ?? null);
    ?>
    <table class="invoice-payment thin" cellpadding="0" cellspacing="0">
        <tr class="head">
            <td style="width:25%;">Słownie:</td>
            <td colspan="3" style="text-align:left;"><?= h($amountInWords($invoice->total, $invoice->currency)) ?></td>
        </tr>
        <tr>
            <td class="head">Zapłacono:</td>
            <td><?= $money($alreadyPaid, $invoice->currency) ?></td>
            <td class="head">Pozostało:</td>
            <td><?= $money($remaining, $invoice->currency) ?></td>
        </tr>
        <tr>
            <td class="head">Sposób zapłaty:</td>
            <td><?= h($methodLabel) ?></td>
            <td class="head">Termin płatności:</td>
            <td><?= h($payUntil) ?></td>
        </tr>
        <tr>
            <td class="head">Bank:</td>
            <td colspan="3" style="text-align:left;"><?= h($bankName ?: '—') ?></td>
        </tr>
        <tr>
            <td class="head">Nr konta:</td>
            <td colspan="3" style="text-align:left;">
                <?php 
                    $formatted = $bankAccount ?: '—';
                    if ($bankAccount && strlen($bankAccount) >= 26) {
                        $formatted = chunk_split($bankAccount, 4, ' ');
                        $formatted = trim($formatted);
                    }
                    echo h($formatted);
                ?>
            </td>
        </tr>
        <tr>
            <td class="head">Uwagi:</td>
            <td colspan="3" style="text-align:left;"><?= h($invoice->description ?: '—') ?></td>
        </tr>
    </table>

    <!-- PODPISY -->
    <table class="signatures" cellpadding="0" cellspacing="0">
        <tr>
            <td class="sig-left"></td>
            <td class="sig-center"></td>
            <td class="sig-right"><?= h($invoice->user_name ?? '') ?></td>
        </tr>
        <tr>
            <td class="sig-left sig-line"><small>podpis osoby upoważnionej<br>do odbioru faktury VAT</small></td>
            <td class="sig-center sig-line"><small>data odbioru</small></td>
            <td class="sig-right sig-line"><small>podpis i pieczęć osoby upoważnionej<br>do wystawienia faktury VAT</small></td>
        </tr>
    </table>

    <!-- STOPKA (informacyjna) -->
    <?php if ($showPreviewFooter): ?>
        <footer>
            <div class="footer-content">
                <div class="footer-left">
                    Podgląd faktury – układ jak do druku
                </div>
                <div class="footer-right"></div>
            </div>
        </footer>
    <?php endif; ?>
</div>

<?php // end element ?>

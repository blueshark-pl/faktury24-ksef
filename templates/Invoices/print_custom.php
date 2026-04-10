<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Invoice $invoice
 * @var string  $cur      — kod waluty np. 'EUR'
 * @var float   $fxRate   — kurs waluty do PLN (0 jeśli PLN)
 * @var mixed   $fxDate   — data kursu (string lub DateTimeInterface lub null)
 * @var string  $fxTable  — tabela NBP np. 'A/012/2026'
 */

/* ─── helpers ─── */
$fdate = function($v): string {
    if (!$v) return '—';
    if ($v instanceof \DateTimeInterface) return $v->format('d.m.Y');
    $s = substr((string)$v, 0, 10);
    // Y-m-d → d.m.Y
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
$typeNames = [
    'vat'        => 'FAKTURA VAT',
    'novat'      => 'FAKTURA',
    'proforma'   => 'FAKTURA PROFORMA',
    'advance'    => 'FAKTURA ZALICZKOWA',
    'correction' => 'FAKTURA KORYGUJĄCA',
    'margin'     => 'FAKTURA MARŻY',
    'internal'   => 'FAKTURA WEWNĘTRZNA',
    'oss'        => 'FAKTURA OSS',
    'final'      => 'FAKTURA KOŃCOWA',
    'currency'   => 'FAKTURA WALUTOWA',
];
$typeName = $typeNames[$invoice->type ?? ''] ?? 'FAKTURA';
$isMargin = ($invoice->type ?? '') === 'margin';
$isNoVat  = ($invoice->type ?? '') === 'novat';
$isForeign = ($cur !== 'PLN');

/* ─── dodatkowe opisy — grupowanie wg nr_wiersza ─── */
// nr_wiersza = 0 lub null → opis do całej faktury
// nr_wiersza > 0           → opis do pozycji o tym numerze
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

/* ─── kwota słownie (uproszczona) ─── */
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
$amountInWords = function($amount, $currency='PLN') use ($__pl_words): string {
    $currency = strtoupper((string)$currency);
    $amt = (float)$amount;
    $int = (int)floor($amt + 1e-8);
    $frac= (int)round(($amt - $int) * 100);
    $decl = function(int $n, array $f): string {
        $n1=$n%10; $n2=$n%100;
        if ($n==1) return $f[0];
        if ($n1>=2&&$n1<=4&&!($n2>=12&&$n2<=14)) return $f[1];
        return $f[2];
    };
    $cents = sprintf('%02d', $frac);
    if ($currency==='PLN') return trim($__pl_words($int).' '.$decl($int,['złoty','złote','złotych']).' '.$cents.'/100 '.$decl($frac,['grosz','grosze','groszy']));
    if ($currency==='EUR') return trim($__pl_words($int).' euro '.$cents.'/100 '.$decl($frac,['eurocent','eurocenty','eurocentów']));
    if ($currency==='USD') return trim($__pl_words($int).' '.$decl($int,['dolar','dolary','dolarów']).' '.$cents.'/100 '.$decl($frac,['cent','centy','centów']));
    return trim($__pl_words($int).' '.strtoupper($currency).' '.$cents.'/100');
};

/* ─── kurs — oblicz VAT w PLN jeśli walutowa ─── */
$taxPln = $isForeign && $fxRate > 0 ? round((float)($invoice->tax ?? 0) * $fxRate, 2) : null;
$totalPln = $isForeign && $fxRate > 0 ? round((float)($invoice->total ?? 0) * $fxRate, 2) : null;
$fxDateStr = $fxDate instanceof \DateTimeInterface ? $fxDate->format('d.m.Y') : ($fxDate ? substr((string)$fxDate, 0, 10) : null);
?>
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<title>Faktura <?= h($invoice->fullnumber ?: $invoice->id) ?></title>
<style>
/* ════════════════════════════════════════════
   CUSTOM PRINT TEMPLATE — drukuje przez przeglądarkę
   Zgodny z @page CSS, bez zależności od bibliotek
════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; }

@page {
    size: A4 portrait;
    margin: 1.5cm 1.5cm 2cm 1.5cm;
}
@media print {
    body { margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
    .page-break { page-break-before: always; }
}
@media screen {
    body { background: #f0f2f5; }
    .sheet { box-shadow: 0 4px 32px rgba(0,0,0,.12); margin: 24px auto; }
}

body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10pt;
    color: #222;
    line-height: 1.4;
}

.sheet {
    background: #fff;
    max-width: 21cm;
    padding: 1.2cm 1.3cm;
}

/* ── Nagłówek ── */
.hdr { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: .9cm; border-bottom: 2px solid #3b82f6; padding-bottom: .4cm; }
.hdr-left { flex: 1; }
.hdr-right { text-align: right; }
.inv-type { font-size: 15pt; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: .04em; line-height: 1.2; }
.inv-number { font-size: 12pt; font-weight: 700; color: #111; margin-top: 2px; }
.inv-meta { font-size: 8.5pt; color: #555; margin-top: 4px; line-height: 1.6; }
.badge-draft { display:inline-block; background:#fef3c7; color:#92400e; font-size:7.5pt; font-weight:700; padding:1px 6px; border-radius:3px; border:1px solid #fcd34d; margin-top:4px; }

/* ── Strony (sprzedawca / nabywca) ── */
.parties { display: flex; gap: 1cm; margin-bottom: .7cm; }
.party { flex: 1; }
.party-label { font-size: 7pt; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding-bottom: 2px; margin-bottom: 4px; }
.party-name { font-size: 11pt; font-weight: 700; color: #111; }
.party-detail { font-size: 8.5pt; color: #444; line-height: 1.5; }

/* ── Tabela pozycji ── */
.items-table { width: 100%; border-collapse: collapse; margin-bottom: .5cm; font-size: 8.5pt; }
.items-table thead th { background: #1e40af; color: #fff; padding: 5px 6px; text-align: center; font-weight: 600; vertical-align: middle; }
.items-table thead th.left { text-align: left; }
.items-table tbody tr { page-break-inside: avoid; }
.items-table tbody td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; text-align: center; }
.items-table tbody td.left { text-align: left; }
.items-table tbody tr:nth-child(even) { background: #f8fafc; }
.items-table tfoot td { padding: 6px; font-weight: 700; background: #f1f5f9; border-top: 2px solid #3b82f6; }

/* ── Opis dodatkowy pod pozycją ── */
.item-descs { border-left: 3px solid #3b82f6; margin: 3px 0 4px 4px; padding: 3px 8px; background: #eff6ff; border-radius: 0 4px 4px 0; }
.item-desc-row { display: flex; gap: 8px; font-size: 7.8pt; color: #334155; line-height: 1.5; }
.item-desc-key { color: #6b7280; white-space: nowrap; min-width: 90px; }
.item-desc-val { font-weight: 600; flex: 1; }

/* ── Blok VAT + suma ── */
.summary-section { display: flex; gap: .5cm; margin-bottom: .6cm; align-items: flex-start; }
.vat-table-wrap { flex: 1; }
.vat-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
.vat-table th { background: #f1f5f9; padding: 4px 6px; font-weight: 600; border: 1px solid #e5e7eb; text-align: center; }
.vat-table td { padding: 4px 6px; border: 1px solid #e5e7eb; text-align: right; }
.vat-table td.left { text-align: left; }
.vat-table tfoot td { font-weight: 700; background: #e0e7ff; }
.total-box { min-width: 5.5cm; text-align: right; }
.total-label { font-size: 8pt; color: #6b7280; }
.total-amount { font-size: 16pt; font-weight: 700; color: #1e40af; line-height: 1.1; }
.total-words { font-size: 7.5pt; color: #555; margin-top: 3px; line-height: 1.4; }

/* ── Blok kursu walut ── */
.fx-box { border: 1.5px solid #bfdbfe; background: #eff6ff; border-radius: 6px; padding: 8px 12px; margin-bottom: .5cm; font-size: 8.5pt; }
.fx-box-title { font-weight: 700; color: #1e40af; font-size: 9pt; margin-bottom: 4px; }
.fx-grid { display: flex; gap: 1.5cm; flex-wrap: wrap; }
.fx-item { }
.fx-item-label { color: #6b7280; font-size: 7.8pt; }
.fx-item-val { font-weight: 700; font-size: 9.5pt; color: #111; }
.fx-plnamount { margin-top: 6px; padding-top: 6px; border-top: 1px solid #bfdbfe; font-size: 8pt; }
.fx-plnamount strong { color: #1e40af; }
.fx-legal { font-size: 7.5pt; color: #6b7280; margin-top: 4px; }

/* ── Blok dodatkowych opisów faktury ── */
.invoice-descs { border-left: 3px solid #6366f1; background: #f5f3ff; border-radius: 0 6px 6px 0; padding: 8px 12px; margin-bottom: .5cm; }
.invoice-descs-title { font-weight: 700; color: #4f46e5; font-size: 8.5pt; margin-bottom: 5px; }
.invoice-desc-row { display: flex; gap: 8px; font-size: 8pt; color: #334155; line-height: 1.6; border-bottom: 1px solid #e9d8fd; }
.invoice-desc-row:last-child { border-bottom: none; }

/* ── Płatność ── */
.payment-section { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 12px; margin-bottom: .5cm; font-size: 8.5pt; }
.payment-grid { display: flex; gap: 1cm; flex-wrap: wrap; }
.payment-item { min-width: 4cm; }
.payment-label { color: #6b7280; font-size: 7.8pt; }
.payment-val { font-weight: 600; font-size: 9pt; }
.bank-account { font-family: monospace; font-size: 9pt; letter-spacing: .05em; }
.remaining-highlight { color: #dc2626; font-weight: 700; }

/* ── Uwagi / dodatkowe opisy (nr_wiersza=0) ── */
.notes-box { background: #fafafa; border: 1px solid #e5e7eb; border-radius: 4px; padding: 6px 10px; font-size: 8.5pt; color: #444; margin-bottom: .4cm; }

/* ── Podpisy ── */
.signatures { display: flex; justify-content: space-between; margin-top: 1.5cm; font-size: 8pt; }
.sig-block { width: 30%; text-align: center; }
.sig-line { border-top: 1px solid #999; padding-top: 5px; color: #6b7280; }

/* ── Przyciski ekranowe ── */
.print-toolbar { position: fixed; top: 16px; right: 24px; display: flex; gap: 8px; z-index: 100; }
.print-btn { padding: 8px 20px; border-radius: 6px; border: none; cursor: pointer; font-size: 10pt; font-weight: 600; }
.print-btn-primary { background: #1e40af; color: #fff; }
.print-btn-secondary { background: #f1f5f9; color: #374151; border: 1px solid #d1d5db; }
</style>
</head>
<body>

<!-- ── Toolbar (tylko na ekranie) ── -->
<div class="print-toolbar no-print">
    <button class="print-btn print-btn-secondary" onclick="window.close()">✕ Zamknij</button>
    <button class="print-btn print-btn-primary" onclick="window.print()">🖨 Drukuj / Zapisz PDF</button>
</div>

<div class="sheet">

    <!-- ════ NAGŁÓWEK ════ -->
    <div class="hdr">
        <div class="hdr-left">
            <div class="inv-type"><?= h($typeName) ?></div>
            <div class="inv-number">Nr: <?= h($invoice->fullnumber ?: ('ID-'.$invoice->id)) ?></div>
            <?php if (($invoice->workflow_status ?? '') === 'draft'): ?>
            <div><span class="badge-draft">WERSJA ROBOCZA — dokument niezatwierdony</span></div>
            <?php endif; ?>
            <div class="inv-meta">
                <?php if ($isForeign): ?>
                <span style="background:#dbeafe;color:#1e40af;padding:1px 6px;border-radius:3px;font-weight:700;font-size:7.5pt"><?= h($cur) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="hdr-right inv-meta">
            <div><strong>Data wystawienia:</strong> <?= $fdate($invoice->date ?? $invoice->created ?? null) ?></div>
            <div><strong>Data sprzedaży:</strong> <?= $fdate($invoice->sold_date ?? $invoice->date ?? null) ?></div>
            <?php if (!empty($invoice->paymentdate)): ?>
            <div><strong>Termin płatności:</strong> <?= $fdate($invoice->paymentdate) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ════ STRONY ════ -->
    <div class="parties">
        <div class="party">
            <div class="party-label">Sprzedawca</div>
            <div class="party-name"><?= h($seller->name ?? '—') ?></div>
            <div class="party-detail">
                <?php
                $addr = trim(implode(', ', array_filter([
                    trim(($seller->street ?? '') . ' ' . ($seller->building_number ?? '') . (isset($seller->flat_number) && $seller->flat_number ? '/'.$seller->flat_number : '')),
                    trim(($seller->zip ?? $seller->postal_code ?? '') . ' ' . ($seller->city ?? '')),
                ])));
                echo h($addr) ?: '—';
                ?>
                <?php if (!empty($seller->nip)): ?><br>NIP: <?= h($seller->nip) ?><?php endif; ?>
                <?php
                $regs = json_decode((string)($seller->registers_json ?? ''), true) ?: [];
                foreach ($regs as $reg):
                    if (!empty($reg['krs']))   echo '<br>KRS: ' . h($reg['krs']);
                    if (!empty($reg['regon'])) echo '<br>REGON: ' . h($reg['regon']);
                endforeach;
                ?>
            </div>
        </div>
        <div class="party">
            <div class="party-label">Nabywca</div>
            <div class="party-name"><?= h($buyer->name ?? '—') ?></div>
            <div class="party-detail">
                <?php if (!empty($buyer->street)): ?><?= h($buyer->street) ?><br><?php endif; ?>
                <?= h(trim(($buyer->zip ?? '') . ' ' . ($buyer->city ?? ''))) ?>
                <?php if (!empty($buyer->nip)): ?><br>NIP: <?= h($buyer->nip) ?><?php endif; ?>
                <?php if (!empty($buyer->vat_eu)): ?><br>VAT-UE: <?= h($buyer->vat_eu) ?><?php endif; ?>
                <?php if (!empty($buyer->country) && $buyer->country !== 'PL'): ?><br><?= h($buyer->country) ?><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ════ TABELA POZYCJI ════ -->
    <?php if (!$isMargin): ?>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:3%">#</th>
                <th class="left" style="width:<?= $isNoVat ? '44%' : '32%' ?>">Nazwa towaru / usługi</th>
                <th style="width:7%">Ilość</th>
                <th style="width:6%">J.m.</th>
                <th style="width:9%">Cena netto</th>
                <?php if (!$isNoVat): ?>
                <th style="width:5%">VAT</th>
                <th style="width:8%">Wartość netto</th>
                <th style="width:7%">Kwota VAT</th>
                <th style="width:9%">Brutto</th>
                <?php else: ?>
                <th style="width:10%">Wartość</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php
        $rowIdx = 0;
        foreach ($invoice->invoice_contents as $it):
            $rowIdx++;
            $qty    = (float)($it->quantity ?? $it->count ?? 1);
            $unit   = $it->unit ?? 'szt.';
            $net    = (float)$it->netto;
            $brut   = (float)$it->brutto;
            $vatAmt = $brut - $net;
            $netUnit= $qty ? $net / $qty : $net;
            $rateName = $it->vat->name ?? (isset($it->vat->rate) ? $it->vat->rate.'%' : '0%');

            // Dodatkowe opisy powiązane z tym wierszem (nr_wiersza = $rowIdx)
            $rowDescs = $addDescsByRow[$rowIdx] ?? [];
        ?>
        <tr>
            <td><?= $rowIdx ?></td>
            <td class="left">
                <strong><?= h($it->name) ?></strong>
                <?php if (!empty($it->product_desc)): ?>
                <br><span style="color:#6b7280;font-size:7.8pt"><?= h($it->product_desc) ?></span>
                <?php endif; ?>
                <?php if (!empty($rowDescs)): ?>
                <div class="item-descs">
                    <?php foreach ($rowDescs as $d):
                        $klucz   = trim((string)($d->klucz ?? ''));
                        $wartosc = trim((string)($d->wartosc ?? ''));
                        if ($klucz === '' && $wartosc === '') continue;
                    ?>
                    <div class="item-desc-row">
                        <span class="item-desc-key"><?= h($klucz) ?>:</span>
                        <span class="item-desc-val"><?= h($wartosc) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
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
                <td colspan="<?= $isNoVat ? '5' : '6' ?>" style="text-align:right;font-weight:600;background:#e0e7ff;color:#1e40af;font-size:10pt">
                    RAZEM DO ZAPŁATY:
                </td>
                <?php if (!$isNoVat): ?>
                <td style="text-align:right"><?= $num2($invoice->netto ?? 0) ?></td>
                <td style="text-align:right"><?= $num2($invoice->tax ?? 0) ?></td>
                <?php endif; ?>
                <td style="text-align:right;font-size:11pt;color:#1e40af">
                    <?= $money($invoice->total ?? 0, $cur) ?>
                </td>
            </tr>
        </tfoot>
    </table>
    <?php else: /* MARŻA */ ?>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:4%">#</th>
                <th class="left" style="width:60%">Nazwa towaru / usługi</th>
                <th style="width:8%">Ilość</th>
                <th style="width:8%">J.m.</th>
                <th style="width:10%">Cena brutto</th>
                <th style="width:10%">Wartość brutto</th>
            </tr>
        </thead>
        <tbody>
        <?php $rowIdx=0; foreach ($invoice->invoice_contents as $it):
            $rowIdx++;
            $qty  = (float)($it->quantity ?? 1);
            $unit = $it->unit ?? 'szt.';
            $brut = (float)$it->brutto;
            $unitGross = $qty ? $brut / $qty : $brut;
            $rowDescs = $addDescsByRow[$rowIdx] ?? [];
        ?>
        <tr>
            <td><?= $rowIdx ?></td>
            <td class="left">
                <strong><?= h($it->name) ?></strong>
                <?php if (!empty($it->product_desc)): ?>
                <br><span style="color:#6b7280;font-size:7.8pt"><?= h($it->product_desc) ?></span>
                <?php endif; ?>
                <?php if (!empty($rowDescs)): ?>
                <div class="item-descs">
                    <?php foreach ($rowDescs as $d):
                        $k = trim((string)($d->klucz ?? ''));
                        $v = trim((string)($d->wartosc ?? ''));
                        if ($k===''&&$v==='') continue;
                    ?>
                    <div class="item-desc-row">
                        <span class="item-desc-key"><?= h($k) ?>:</span>
                        <span class="item-desc-val"><?= h($v) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </td>
            <td><?= $num2($qty) ?></td>
            <td><?= h($unit) ?></td>
            <td><?= $num2($unitGross) ?></td>
            <td style="font-weight:700"><?= $num2($brut) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align:right;font-weight:600;background:#e0e7ff;color:#1e40af">RAZEM:</td>
                <td style="text-align:right;font-size:11pt;color:#1e40af"><?= $money($invoice->total ?? 0, $cur) ?></td>
            </tr>
        </tfoot>
    </table>
    <p style="font-size:8pt;color:#6b7280;margin-top:4px">
        Sprzedaż w procedurze marży. VAT naliczany wyłącznie od marży zgodnie z art. 120 ustawy o VAT.
    </p>
    <?php endif; ?>

    <!-- ════ PODSUMOWANIE VAT ════ -->
    <?php if (!$isMargin && !$isNoVat && !empty($vatSummary)): ?>
    <div class="summary-section">
        <div class="vat-table-wrap">
            <table class="vat-table">
                <thead>
                    <tr>
                        <th class="left">Stawka VAT</th>
                        <th>Wartość netto</th>
                        <th>Kwota VAT</th>
                        <th>Wartość brutto</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($vatSummary as $rate => $row): ?>
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
                        <td class="left">OGÓŁEM</td>
                        <td><?= $num2($invoice->netto ?? 0) ?></td>
                        <td><?= $num2($invoice->tax ?? 0) ?></td>
                        <td><?= $num2($invoice->total ?? 0) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="total-box">
            <div class="total-label">Do zapłaty:</div>
            <div class="total-amount"><?= $money($invoice->total ?? 0, $cur) ?></div>
            <div class="total-words"><?= h($amountInWords($invoice->total ?? 0, $cur)) ?></div>
        </div>
    </div>
    <?php else: ?>
    <div style="text-align:right;margin-bottom:.5cm">
        <div class="total-label" style="font-size:8pt;color:#6b7280">Do zapłaty:</div>
        <div class="total-amount" style="font-size:16pt;font-weight:700;color:#1e40af"><?= $money($invoice->total ?? 0, $cur) ?></div>
        <div class="total-words" style="font-size:7.5pt;color:#555;margin-top:3px"><?= h($amountInWords($invoice->total ?? 0, $cur)) ?></div>
    </div>
    <?php endif; ?>

    <!-- ════ KURS WALUT ════ -->
    <?php if ($isForeign): ?>
    <div class="fx-box">
        <div class="fx-box-title"><span style="font-size:11pt">💱</span> Kurs waluty — <?= h($cur) ?></div>
        <div class="fx-grid">
            <?php if ($fxRate > 0): ?>
            <div class="fx-item">
                <div class="fx-item-label">Kurs</div>
                <div class="fx-item-val">1 <?= h($cur) ?> = <?= $num4($fxRate) ?> PLN</div>
            </div>
            <?php endif; ?>
            <?php if ($fxDateStr): ?>
            <div class="fx-item">
                <div class="fx-item-label">Data kursu (NBP)</div>
                <div class="fx-item-val"><?= h($fxDateStr) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($fxTable !== ''): ?>
            <div class="fx-item">
                <div class="fx-item-label">Tabela NBP</div>
                <div class="fx-item-val"><?= h($fxTable) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($taxPln !== null): ?>
            <div class="fx-item">
                <div class="fx-item-label">VAT (PLN)</div>
                <div class="fx-item-val" style="color:#1e40af"><?= $num2($taxPln) ?> PLN</div>
            </div>
            <?php endif; ?>
            <?php if ($totalPln !== null): ?>
            <div class="fx-item">
                <div class="fx-item-label">Brutto (PLN)</div>
                <div class="fx-item-val" style="color:#1e40af"><?= $num2($totalPln) ?> PLN</div>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($fxRate > 0): ?>
        <div class="fx-legal">
            Kwoty netto i brutto wykazane są w walucie dokumentu (<?= h($cur) ?>).
            VAT należny wykazany w PLN zgodnie z art. 106e ust. 11 ustawy o VAT.
            <?php if ($fxDateStr): ?>Kurs z dnia <?= h($fxDateStr) ?><?= $fxTable ? ', tabela NBP '.h($fxTable) : '' ?>.<?php endif; ?>
        </div>
        <?php else: ?>
        <div class="fx-legal" style="color:#dc2626">Brak kursu NBP — wprowadź kurs ręcznie w ustawieniach faktury.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ════ DODATKOWE OPISY FAKTURY (nr_wiersza = 0) ════ -->
    <?php $invoiceDescs = $addDescsByRow[0] ?? []; ?>
    <?php if (!empty($invoiceDescs)): ?>
    <div class="invoice-descs">
        <div class="invoice-descs-title">Dodatkowe informacje</div>
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

    <!-- ════ UWAGI (description) ════ -->
    <?php if (!empty($invoice->description)): ?>
    <div class="notes-box"><strong>Uwagi:</strong> <?= h($invoice->description) ?></div>
    <?php endif; ?>

    <!-- ════ PŁATNOŚĆ ════ -->
    <?php
    $alreadyPaid = (float)($invoice->alreadypaid ?? 0);
    $remaining   = max(0.0, (float)($invoice->total ?? 0) - $alreadyPaid);
    $payMethod   = $invoice->paymentmethod ?: 'transfer';
    $methodLabel = ['transfer'=>'Przelew bankowy','cash'=>'Gotówka','compensation'=>'Kompensata'][$payMethod] ?? ucfirst($payMethod);
    $bankAccFormatted = $bankAccount ?? '—';
    if ($bankAccount && strlen($bankAccount) >= 26) {
        $bankAccFormatted = trim(chunk_split($bankAccount, 4, ' '));
    }
    ?>
    <div class="payment-section">
        <div style="font-weight:700;color:#374151;margin-bottom:6px;font-size:9pt">Informacje o płatności</div>
        <div class="payment-grid">
            <div class="payment-item">
                <div class="payment-label">Sposób płatności</div>
                <div class="payment-val"><?= h($methodLabel) ?></div>
            </div>
            <div class="payment-item">
                <div class="payment-label">Termin płatności</div>
                <div class="payment-val"><?= $fdate($invoice->paymentdate ?? $invoice->disposaldate ?? null) ?></div>
            </div>
            <div class="payment-item">
                <div class="payment-label">Zapłacono</div>
                <div class="payment-val"><?= $money($alreadyPaid, $cur) ?></div>
            </div>
            <div class="payment-item">
                <div class="payment-label">Pozostało do zapłaty</div>
                <div class="payment-val <?= $remaining > 0 ? 'remaining-highlight' : '' ?>"><?= $money($remaining, $cur) ?></div>
            </div>
        </div>
        <?php if ($bankName || $bankAccount): ?>
        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #e5e7eb">
            <?php if ($bankName): ?><span style="color:#6b7280;font-size:8pt">Bank: </span><strong><?= h($bankName) ?></strong>&nbsp;&nbsp;<?php endif; ?>
            <?php if ($bankAccount): ?><span style="color:#6b7280;font-size:8pt">Nr konta: </span><span class="bank-account"><?= h($bankAccFormatted) ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
        <div style="margin-top:6px;font-size:8pt;color:#555">
            Słownie: <?= h($amountInWords($invoice->total ?? 0, $cur)) ?>
        </div>
    </div>

    <!-- ════ PODPISY ════ -->
    <div class="signatures">
        <div class="sig-block">
            <div style="height:30px"></div>
            <div class="sig-line">Podpis osoby upoważnionej<br>do odbioru faktury</div>
        </div>
        <div class="sig-block">
            <div style="height:30px"></div>
            <div class="sig-line">Data odbioru</div>
        </div>
        <div class="sig-block">
            <div style="height:30px;text-align:right;padding-right:4px;font-size:8pt;color:#555"><?= h($invoice->user_name ?? '') ?></div>
            <div class="sig-line">Podpis i pieczęć wystawcy</div>
        </div>
    </div>

</div><!-- /sheet -->

<script>
// Auto-print jeśli URL zawiera ?autoprint=1
if (new URLSearchParams(location.search).get('autoprint') === '1') {
    window.addEventListener('load', function() {
        setTimeout(function() { window.print(); }, 400);
    });
}
</script>
</body>
</html>

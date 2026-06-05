<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Collection\CollectionInterface|\App\Model\Entity\Invoice[] $invoices
 */

$money = function($amount, $currency = 'PLN') {
    return number_format($amount, 2, ',', ' ') . ' ' . $currency;
};

$formatDate = function($date) {
    return $date ? $date->format('d.m.Y') : '';
};

$isFirst = true;
?>

<?php foreach ($invoices as $invoice): ?>
    <?php if (!$isFirst): ?>
        <div class="page-break"></div>
    <?php endif; ?>
    
    <div class="invoice-container">
        <!-- Watermark for unpaid invoices -->
        <?php if ($invoice->paymentstate === 'unpaid' || $invoice->paymentstate === 'overdue'): ?>
            <div class="watermark">NIEOPŁACONE</div>
        <?php elseif ($invoice->paymentstate === 'partial'): ?>
            <div class="watermark">CZĘŚCIOWO OPŁACONE</div>
        <?php endif; ?>

        <!-- Header -->
        <div class="header">
            <h1>
                <?php
                $typeNames = [
                    'vat' => 'FAKTURA VAT',
                    'novat' => 'FAKTURA',
                    'proforma' => 'FAKTURA PROFORMA',
                    'advance' => 'FAKTURA ZALICZKOWA',
                    'correction' => 'FAKTURA KORYGUJĄCA',
                    'margin' => 'FAKTURA MARŻY',
                    'internal' => 'FAKTURA WEWNĘTRZNA',
                    'oss' => 'FAKTURA OSS'
                ];
                echo $typeNames[$invoice->type] ?? 'FAKTURA';
                ?>
            </h1>
        </div>

        <!-- Invoice Info -->
        <div class="invoice-info">
            <!-- Seller -->
            <div class="seller">
                <div class="company-box">
                    <div class="company-title">Sprzedawca</div>
                    <?php $seller = $invoice->invoice_company_details ?? $invoice->company ?? null; ?>
                    <div class="company-name"><?= h($seller->name ?? 'Nazwa firmy') ?></div>
                    <div class="company-details">
                        <?php if ($seller && ($seller->street ?? null)): ?>
                            <?= h($seller->street) ?>
                            <?php if ($seller && property_exists($seller, 'local_number') && $seller->local_number): ?>
                                <?= h($seller->local_number) ?>
                            <?php endif; ?>
                            <br>
                        <?php endif; ?>
                        <?php
                            $zip = $seller->postal_code ?? ($seller->zip ?? null);
                            $city = $seller->city ?? null;
                        ?>
                        <?php if ($zip || $city): ?>
                            <?= h((string)$zip) ?> <?= h((string)$city) ?><br>
                        <?php endif; ?>
                        <?php if ($seller && ($seller->nip ?? null)): ?>
                            <strong>NIP:</strong> <?= h($seller->nip) ?><br>
                        <?php endif; ?>
                        <?php
                        $__regs = [];
                        $__regsJson = trim((string)($seller->registers_json ?? ''));
                        if ($__regsJson !== '') { $__regs = json_decode($__regsJson, true) ?: []; }
                        foreach ($__regs as $__reg):
                            if (!empty($__reg['name'])): ?><strong><?= h($__reg['name']) ?></strong><br><?php endif;
                            if (!empty($__reg['krs'])): ?><strong>KRS:</strong> <?= h($__reg['krs']) ?><br><?php endif;
                            if (!empty($__reg['regon'])): ?><strong>REGON:</strong> <?= h($__reg['regon']) ?><br><?php endif;
                            if (!empty($__reg['bdo'])): ?><strong>BDO:</strong> <?= h($__reg['bdo']) ?><br><?php endif;
                        endforeach; ?>
                        <?php if ($seller && property_exists($seller, 'phone') && $seller->phone): ?>
                            <strong>Tel:</strong> <?= h($seller->phone) ?><br>
                        <?php endif; ?>
                        <?php if ($seller && property_exists($seller, 'bank_account') && $seller->bank_account): ?>
                            <strong>Konto bankowe:</strong> <?= h($seller->bank_account) ?><br>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Buyer -->
            <div class="buyer">
                <div class="company-box">
                    <div class="company-title">Nabywca</div>
                    <div class="company-name"><?= h($invoice->invoice_contractor->name ?? 'Nazwa kontrahenta') ?></div>
                    <div class="company-details">
                        <?php if ($invoice->invoice_contractor->street): ?>
                            <?= h($invoice->invoice_contractor->street) ?><br>
                        <?php endif; ?>
                        <?php if ($invoice->invoice_contractor->zip || $invoice->invoice_contractor->city): ?>
                            <?= h($invoice->invoice_contractor->zip) ?> <?= h($invoice->invoice_contractor->city) ?><br>
                        <?php endif; ?>
                        <?php if ($invoice->invoice_contractor->country): ?>
                            <?= h($invoice->invoice_contractor->country) ?><br>
                        <?php endif; ?>
                        <?php if ($invoice->invoice_contractor->nip): ?>
                            <strong>NIP:</strong> <?= h($invoice->invoice_contractor->nip) ?><br>
                        <?php endif; ?>
                        <?php if ($invoice->invoice_contractor->account_number): ?>
                            <strong>Nr konta:</strong> <?= h($invoice->invoice_contractor->account_number) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="invoice-details">
            <div class="invoice-number">
                <div class="detail-row">
                    <span class="label">Numer faktury:</span> 
                    <strong><?= h($invoice->fullnumber ?: $invoice->id) ?></strong>
                </div>
                <?php if ($invoice->description): ?>
                    <div class="detail-row">
                        <span class="label">Opis:</span> <?= h($invoice->description) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="invoice-dates">
                <div class="detail-row">
                    <span class="label">Data wystawienia:</span> <?= $formatDate($invoice->date) ?>
                </div>
                <div class="detail-row">
                    <span class="label">Data sprzedaży:</span> <?= $formatDate($invoice->date) ?>
                </div>
                <div class="detail-row">
                    <span class="label">Termin płatności:</span> 
                    <span class="<?= $invoice->paymentdate && $invoice->paymentdate->isPast() && $invoice->paymentstate !== 'paid' ? 'highlight' : '' ?>">
                        <?= $formatDate($invoice->paymentdate) ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="label">Sposób płatności:</span> <?= h($invoice->paymentmethod ?: 'Przelew') ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%">Lp.</th>
                    <th style="width: 40%">Nazwa towaru/usługi</th>
                    <th style="width: 8%">Ilość</th>
                    <th style="width: 5%">j.m.</th>
                    <th style="width: 10%">Cena netto</th>
                    <th style="width: 10%">Wartość netto</th>
                    <th style="width: 7%">VAT</th>
                    <th style="width: 10%">Kwota VAT</th>
                    <th style="width: 10%">Wartość brutto</th>
                </tr>
            </thead>
            <tbody>
                <?php $itemNumber = 1; ?>
                <?php foreach ($invoice->invoice_contents as $item): ?>
                    <tr>
                        <td><?= $itemNumber++ ?></td>
                        <td class="text-left">
                            <strong><?= h($item->name) ?></strong>
                            <?php if ($item->product_desc): ?>
                                <br><small><?= h($item->product_desc) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($item->quantity, 2, ',', ' ') ?></td>
                        <td><?= h($item->unit ?: 'szt.') ?></td>
                        <td class="text-right"><?= $money($item->price, $invoice->currency) ?></td>
                        <td class="text-right"><?= $money($item->netto, $invoice->currency) ?></td>
                        <td><?= number_format($item->vat ? $item->vat->rate : 0, 0) ?>%</td>
                        <td class="text-right"><?= $money(($item->brutto - $item->netto), $invoice->currency) ?></td>
                        <td class="text-right"><strong><?= $money($item->brutto, $invoice->currency) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- VAT Summary -->
        <?php 
        // Grupowanie pozycji według stawek VAT
        $vatSummary = [];
        if (!empty($invoice->invoice_contents)) {
            foreach ($invoice->invoice_contents as $item) {
                $vatRate = $item->vat ? $item->vat->rate : 0;
                $vatName = $item->vat ? $item->vat->name : 'Bez VAT';
                
                if (!isset($vatSummary[$vatRate])) {
                    $vatSummary[$vatRate] = [
                        'name' => $vatName,
                        'rate' => $vatRate,
                        'netto' => 0,
                        'tax' => 0,
                        'brutto' => 0
                    ];
                }
                
                $vatSummary[$vatRate]['netto'] += $item->netto;
                $vatSummary[$vatRate]['tax'] += ($item->brutto - $item->netto);
                $vatSummary[$vatRate]['brutto'] += $item->brutto;
            }
        }
        
        // Sortowanie według stawki VAT
        ksort($vatSummary);
        ?>
        
        <?php if (!empty($vatSummary) && count($vatSummary) > 1): ?>
        <div class="vat-summary">
            <h4>Podsumowanie VAT</h4>
            <table class="vat-summary-table">
                <thead>
                    <tr>
                        <th>Stawka VAT</th>
                        <th style="text-align: right;">Netto</th>
                        <th style="text-align: right;">VAT</th>
                        <th style="text-align: right;">Brutto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vatSummary as $vat): ?>
                    <tr>
                        <td><?= h($vat['name']) ?></td>
                        <td style="text-align: right;"><?= $money($vat['netto'], $invoice->currency) ?></td>
                        <td style="text-align: right;"><?= $money($vat['tax'], $invoice->currency) ?></td>
                        <td style="text-align: right;"><?= $money($vat['brutto'], $invoice->currency) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Totals -->
        <div class="totals">
            <table class="totals-table">
                <tr>
                    <th>Suma netto:</th>
                    <td><span class="currency"><?= $money($invoice->netto, $invoice->currency) ?></span></td>
                </tr>
                <tr>
                    <th>Suma VAT:</th>
                    <td><span class="currency"><?= $money($invoice->tax, $invoice->currency) ?></span></td>
                </tr>
                <tr class="total-final">
                    <th>SUMA BRUTTO:</th>
                    <td><span class="currency"><?= $money($invoice->total, $invoice->currency) ?></span></td>
                </tr>
            </table>
        </div>

        <!-- Payment Information -->
        <div class="payment-info">
            <div class="payment-title">Informacje o płatności</div>
            
            <div style="display: table; width: 100%;">
                <div style="display: table-cell; width: 50%;">
                    <?php 
                        $snapshotBank = isset($invoice->invoice_company_details) ? ($invoice->invoice_company_details->bank_account ?? null) : null;
                        $liveBank     = $invoice->company->bank_account ?? null;
                        $bankAccount  = $snapshotBank ?: $liveBank;
                    ?>
                    <?php if ($bankAccount): ?>
                        <strong>Numer konta:</strong> <?= h($bankAccount) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($invoice->company->bank_name)): ?>
                        <strong>Bank:</strong> <?= h($invoice->company->bank_name) ?><br>
                    <?php endif; ?>
                    <strong>Waluta:</strong> <?= h($invoice->currency) ?>
                </div>
                
                <div style="display: table-cell; width: 50%; text-align: right;">
                    <strong>Do zapłaty:</strong> 
                    <span class="currency" style="font-size: 16px;">
                        <?= $money($invoice->remaining > 0 ? $invoice->remaining : $invoice->total, $invoice->currency) ?>
                    </span>
                    
                    <?php if ($invoice->alreadypaid > 0): ?>
                        <br><small>Wpłacono: <?= $money($invoice->alreadypaid, $invoice->currency) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($nbpMap[$invoice->id]) && !empty($nbpMap[$invoice->id]['success']) && strtoupper((string)$invoice->currency) !== 'PLN'): ?>
                <?php $n = $nbpMap[$invoice->id]; ?>
                <div class="mt-2 small text-muted">
                    Średni kurs NBP (tabela <?= h($n['table'] ?? '') ?>) z dnia <?= h($n['effectiveDate'] ?? '') ?>:
                    <strong><?= number_format((float)($n['rate'] ?? 0), 4, ',', ' ') ?></strong> <?= h(strtoupper((string)$invoice->currency)) ?>/PLN
                </div>
            <?php endif; ?>
        </div>

        <!-- Signatures -->
        <div class="signature-area">
            <div class="signature-box">
                Osoba upoważniona<br>do wystawiania faktur
            </div>
            
            <div class="signature-box signature-right">
                Osoba upoważniona<br>do odbioru faktur
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Faktura wygenerowana automatycznie w systemie Faktury24
            • Data wygenerowania: <?= date('d.m.Y H:i') ?>
            
            <?php if ($invoice->created): ?>
                • Data utworzenia faktury: <?= $invoice->created->format('d.m.Y H:i') ?>
            <?php endif; ?>
        </div>
    </div>

    <?php $isFirst = false; ?>
<?php endforeach; ?>
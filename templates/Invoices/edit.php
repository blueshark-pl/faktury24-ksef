<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Invoice $invoice
 * @var array $vats id => "Nazwa (x%)"
 * @var array $vatRatesMap id => rate
 */
$this->assign('title', 'Edit Invoice');

// pre-render VAT select do klonowania w wierszach (z pustą opcją na start)
$vatSelectHtml = '<select class="form-select item-vatcode" name="items[0][vat_code_id]" required>';
foreach ($vats as $id => $label) {
    $vatSelectHtml .= '<option value="' . h($id) . '">' . h($label) . '</option>';
}
$vatSelectHtml .= '</select>';

// GTU select do klonowania (opcje możesz podać z kontrolera jako $gtuOptions)
$gtuOptions = $gtuOptions ?? [
    '' => 'brak',
    'GTU_01' => 'GTU_01 – napoje alkoholowe',
    'GTU_02' => 'GTU_02 – paliwa',
    'GTU_03' => 'GTU_03 – oleje opałowe',
    'GTU_04' => 'GTU_04 – wyroby tytoniowe',
    'GTU_05' => 'GTU_05 – odpady',
    'GTU_06' => 'GTU_06 – urządzenia elektroniczne',
    'GTU_07' => 'GTU_07 – pojazdy/części',
    'GTU_08' => 'GTU_08 – metale szlachetne',
    'GTU_09' => 'GTU_09 – leki/wyroby med.',
    'GTU_10' => 'GTU_10 – budowlanka',
    'GTU_11' => 'GTU_11 – drukowane nośniki',
    'GTU_12' => 'GTU_12 – usługi niematerialne',
    'GTU_13' => 'GTU_13 – transport i gospodarka magazynowa',
];
$gtuSelectHtml = '<select class="form-select item-gtu" name="items[0][gtu_code]">';
foreach ($gtuOptions as $val => $label) {
    $gtuSelectHtml .= '<option value="'.h($val).'">'.h($label).'</option>';
}
$gtuSelectHtml .= '</select>';

// Wstępne sumy z pozycji (jeśli są)
$sumNet = 0.0; $sumGross = 0.0; $sumTax = 0.0;
foreach ((array)($invoice->invoice_contents ?? []) as $c) {
    $sumNet += (float)($c->netto ?? 0);
    $sumGross += (float)($c->brutto ?? 0);
}
$sumTax = round($sumGross - $sumNet, 2);
?>

<?= $this->Form->create($invoice, ['class' => 'needs-validation', 'novalidate' => true]) ?>

<!-- Start::page-header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h1 class="page-title fw-medium fs-18 mb-2">Edit Invoice</h1>
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
            <li class="breadcrumb-item" aria-current="page"><a href="javascript:void(0);">Invoice</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Invoice</li>
        </ol>
    </div>
    <div class="btn-list">
        <?= $this->Form->button('Zapisz zmiany <i class="ri-save-3-line ms-1 align-middle d-inline-block"></i>', [
            'class' => 'btn btn-primary', 'escapeTitle' => false
        ]) ?>
    </div>
</div>
<!-- End::page-header -->

<div class="row">
    <div class="col-xxl-9">
        <div class="card custom-card">
            <div class="card-header d-md-flex d-block">
                <div class="card-title">Edit Invoice</div>
                <div class="ms-auto mt-md-0 mt-2">
                    <?= $this->Form->button('Zapisz zmiany <i class="ri-save-3-line ms-1 align-middle d-inline-block"></i>', [
                        'class' => 'btn btn-sm btn-primary', 'escapeTitle' => false
                    ]) ?>
                </div>
            </div>

            <div class="card-body">
                <div class="p-3 bg-light border rounded mb-3">
                    <div class="row g-3 align-items-start">
                        <div class="col-xl-8">
                            <label class="form-label mb-1">Nabywca</label>

                            <!-- Nabywca: Select2 -->
                            <div class="contractor-picker mb-2">
                                <select id="contractor-select" class="form-select" data-placeholder="Wpisz nazwę kontrahenta lub NIP"></select>
                                <?= $this->Form->control('contractor_id', ['type' => 'hidden', 'id' => 'contractor-id-input']) ?>
                            </div>
                            <div class="ctr-toolbar gap-1 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openCatalog()">
                                    <i class="ri-search-line me-1"></i> Szukaj w katalogu
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#contractor-create-modal">
                                    <i class="ri-user-add-line me-1"></i> Nowy kontrahent
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#gus-modal">
                                    <i class="ri-database-2-line me-1"></i> GUS z NIP
                                </button>
                                <div class="ms-auto d-flex align-items-center small text-muted">
                                    <i class="ri-shield-check-line me-1"></i> dane wypełnisz w 2 kliknięcia
                                </div>
                            </div>

                            <!-- Snapshot kontrahenta (invoice_contractors) — POKAŻ jeśli są dane -->
                            <?php $showCtr = !empty($invoice->invoice_contractor); ?>
                            <div id="contractor-snapshot" class="mt-2" style="<?= $showCtr ? '' : 'display:none;' ?>">
                                <div class="row g-2">
                                    <div class="col-12 col-md-8">
                                        <?= $this->Form->control('invoice_contractor.name', ['label' => 'Nazwa', 'class' => 'form-control', 'required' => true]) ?>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <?= $this->Form->control('invoice_contractor.nip', ['label' => 'NIP', 'class' => 'form-control']) ?>
                                    </div>
                                    <div class="col-8"><?= $this->Form->control('invoice_contractor.street', ['label' => 'Ulica', 'class' => 'form-control']) ?></div>
                                    <div class="col-4"><?= $this->Form->control('invoice_contractor.zip', ['label' => 'Kod', 'class' => 'form-control']) ?></div>
                                    <div class="col-6"><?= $this->Form->control('invoice_contractor.city', ['label' => 'Miasto', 'class' => 'form-control']) ?></div>
                                    <div class="col-6"><?= $this->Form->control('invoice_contractor.country', ['label' => 'Kraj', 'class' => 'form-control', 'value' => 'PL']) ?></div>
                                    <div class="col-6"><?= $this->Form->control('invoice_contractor.email', ['label' => 'Email', 'class' => 'form-control']) ?></div>
                                    <div class="col-6"><?= $this->Form->control('invoice_contractor.phone', ['label' => 'Telefon', 'class' => 'form-control']) ?></div>
                                </div>
                            </div>
                            
                            <!-- ODBIORCA (opcjonalny) -->
                            <div id="recipient-snapshot" class="mt-3 border rounded p-2" style="display:none;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold">Odbiorca</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="recipient-edit-btn"><i class="ri-edit-2-line"></i> Edytuj</button>
                                </div>
                                <div class="row g-2">
                                    <div class="col-12 col-md-8">
                                        <?= $this->Form->control('invoice_recipient.name', ['label' => 'Nazwa odbiorcy', 'class' => 'form-control']) ?>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <?= $this->Form->control('invoice_recipient.nip', ['label' => 'NIP', 'class' => 'form-control']) ?>
                                    </div>
                                    <div class="col-8"><?= $this->Form->control('invoice_recipient.street', ['label' => 'Ulica', 'class' => 'form-control']) ?></div>
                                    <div class="col-4"><?= $this->Form->control('invoice_recipient.zip', ['label' => 'Kod', 'class' => 'form-control']) ?></div>
                                    <div class="col-6"><?= $this->Form->control('invoice_recipient.city', ['label' => 'Miasto', 'class' => 'form-control']) ?></div>
                                    <div class="col-6"><?= $this->Form->control('invoice_recipient.country', ['label' => 'Kraj', 'class' => 'form-control', 'value' => 'PL']) ?></div>
                                    <div class="col-6"><?= $this->Form->control('invoice_recipient.email', ['label' => 'Email', 'class' => 'form-control']) ?></div>
                                    <div class="col-6"><?= $this->Form->control('invoice_recipient.phone', ['label' => 'Telefon', 'class' => 'form-control']) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- (PRZENIESIONE na prawą kartę — numer, waluta, data wystawienia) -->
                        <div class="col-xl-4"></div>
                    </div>
                </div>

                <div class="card custom-card invoice-compact">
                    <!-- Pozycje -->
                    <div class="table-responsive">
                        <table class="table nowrap text-nowrap border mt-3" id="items-table">
                            <thead>
                                <tr>
                                    <th style="min-width:260px;">PRODUKT</th>
                                    <th style="width:120px;">ILOŚĆ</th>
                                    <th style="width:140px;">CENA NETTO</th>
                                    <th style="width:170px;">STAWKA VAT</th>
                                    <th style="width:120px;">RABAT %</th>
                                    <th style="width:140px;">NETTO</th>
                                    <th style="width:140px;">BRUTTO</th>
                                    <th style="width:120px;">GTU</th>
                                    <th style="width:120px;">AKCJA</th>
                                </tr>
                                </thead>

                            <tbody id="items-body">
                                <?php
                                $rows = (array)($invoice->invoice_contents ?? []);
                                if (count($rows) === 0): ?>
                                    <tr class="item-row">
                                        <td>
                                            <select class="form-select item-product-select" data-index="0" data-placeholder="Wybierz lub wpisz produkt"></select>
                                            <input type="hidden" name="items[0][name]" class="item-name-hidden">
                                        </td>
                                        <td><input name="items[0][quantity]" type="number" step="0.001" value="1" class="form-control text-end item-qty" required></td>
                                        <td><input name="items[0][price]" type="number" step="0.01" value="0" class="form-control text-end item-price" required></td>
                                        <td class="vat-cell"><?= $vatSelectHtml ?></td>
                                        <td><input name="items[0][discount_percent]" type="number" step="0.01" value="0" class="form-control text-end item-disc"></td>
                                        <td><input class="form-control text-end item-net" value="0.00" readonly></td>
                                        <td><input class="form-control text-end item-gross" value="0.00" readonly></td>
                                        <td class="gtu-cell"><?= $gtuSelectHtml ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-icon btn-danger-light btn-remove" title="Usuń"><i class="ri-delete-bin-5-line"></i></button>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $i => $c):
                                        $vatOptions = '';
                                        foreach ($vats as $vid => $vlabel) {
                                            $sel = ((string)$vid === (string)($c->vat_code_id ?? '')) ? ' selected' : '';
                                            $vatOptions .= '<option value="'.h($vid).'"'.$sel.'>'.h($vlabel).'</option>';
                                        }
                                        $gtuOptionsHtml = '';
                                        foreach (($gtuOptions ?? []) as $gval => $glabel) {
                                            $sel = ((string)$gval === (string)($c->gtu_code ?? '')) ? ' selected' : '';
                                            $gtuOptionsHtml .= '<option value="'.h($gval).'"'.$sel.'>'.h($glabel).'</option>';
                                        }
                                        $name = (string)($c->name ?? '');
                                        $newOpt = $name !== '' ? '<option value="'.h('NEW:'.$name).'" selected>'.h($name).'</option>' : '';
                                    ?>
                                    <tr class="item-row">
                                        <td>
                                            <select class="form-select item-product-select" data-index="<?= (int)$i ?>" data-placeholder="Wybierz lub wpisz produkt">
                                                <?= $newOpt ?>
                                            </select>
                                            <input type="hidden" name="items[<?= (int)$i ?>][name]" class="item-name-hidden" value="<?= h($name) ?>">
                                        </td>
                                        <td><input name="items[<?= (int)$i ?>][quantity]" type="number" step="0.001" value="<?= h((float)$c->quantity) ?>" class="form-control text-end item-qty" required></td>
                                        <td><input name="items[<?= (int)$i ?>][price]" type="number" step="0.01" value="<?= h((float)$c->price) ?>" class="form-control text-end item-price" required></td>
                                        <td class="vat-cell">
                                            <select class="form-select item-vatcode" name="items[<?= (int)$i ?>][vat_code_id]" required>
                                                <?= $vatOptions ?>
                                            </select>
                                        </td>
                                        <td><input name="items[<?= (int)$i ?>][discount_percent]" type="number" step="0.01" value="<?= h((float)($c->discount_percent ?? 0)) ?>" class="form-control text-end item-disc"></td>
                                        <td><input class="form-control text-end item-net" value="<?= number_format((float)($c->netto ?? 0), 2, '.', '') ?>" readonly></td>
                                        <td><input class="form-control text-end item-gross" value="<?= number_format((float)($c->brutto ?? 0), 2, '.', '') ?>" readonly></td>
                                        <td class="gtu-cell">
                                            <select class="form-select item-gtu" name="items[<?= (int)$i ?>][gtu_code]">
                                                <?= $gtuOptionsHtml ?>
                                            </select>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-icon btn-danger-light btn-remove" title="Usuń"><i class="ri-delete-bin-5-line"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- SZABLON WIERSZA (ukryty do klonowania) -->
                                <tr id="row-template" class="item-row" style="display:none;">
                                    <td>
                                        <select class="form-select item-product-select" data-index="__INDEX__" data-placeholder="Wybierz lub wpisz produkt"></select>
                                        <input type="hidden" name="items[__INDEX__][name]" class="item-name-hidden">
                                    </td>
                                    <td><input name="items[__INDEX__][quantity]" type="number" step="0.001" value="1" class="form-control text-end item-qty" required></td>
                                    <td><input name="items[__INDEX__][price]" type="number" step="0.01" value="0" class="form-control text-end item-price" required></td>
                                    <td class="vat-cell"><?= str_replace('[0]', '[__INDEX__]', $vatSelectHtml) ?></td>
                                    <td><input name="items[__INDEX__][discount_percent]" type="number" step="0.01" value="0" class="form-control text-end item-disc"></td>
                                    <td><input class="form-control text-end item-net" value="0.00" readonly></td>
                                    <td><input class="form-control text-end item-gross" value="0.00" readonly></td>
                                    <td class="gtu-cell"><?= str_replace('[0]', '[__INDEX__]', $gtuSelectHtml) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-icon btn-danger-light btn-remove" title="Usuń"><i class="ri-delete-bin-5-line"></i></button>
                                    </td>
                                </tr>

                                <!-- wiersz: Add Product -->
                                <tr>
                                    <td colspan="8" class="border-bottom-0">
                                        <button type="button" class="btn btn-light" id="btn-add-item"><i class="bi bi-plus-lg"></i> Dodaj produkt</button>
                                    </td>
                                </tr>

                                <!-- wiersz: Sumy -->
                                <tr>
                                    <td colspan="5"></td>
                                    <td colspan="4">
                                        <table class="table table-sm text-nowrap mb-0 table-borderless">
                                            <tbody>
                                                <tr>
                                                    <th scope="row"><div class="fw-medium">Sub Total :</div></th>
                                                    <td><input type="text" id="sum-net" class="form-control invoice-amount-input text-end" value="<?= number_format($sumNet, 2, '.', '') ?>" readonly></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row"><div class="fw-medium">VAT :</div></th>
                                                    <td><input type="text" id="sum-tax" class="form-control invoice-amount-input text-end" value="<?= number_format($sumTax, 2, '.', '') ?>" readonly></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row"><div class="fs-14 fw-medium">Total :</div></th>
                                                    <td><input type="text" id="sum-gross" class="form-control invoice-amount-input text-end" value="<?= number_format($sumGross, 2, '.', '') ?>" readonly></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mb-3">
                    <?= $this->Form->control('description', ['label' => 'Uwagi', 'type' => 'textarea', 'rows' => 3, 'class' => 'form-control']) ?>
                </div>
            </div>

            <div class="card-footer text-end">
                <button type="button" id="btn-validate" class="btn btn-outline-secondary m-1">
                    <i class="ri-shield-check-line me-1"></i> Sprawdź poprawność
                </button>
                <?= $this->Form->button('Zapisz zmiany <i class="ri-save-3-line ms-1 align-middle d-inline-block"></i>', [
                    'class' => 'btn btn-primary m-1', 'escapeTitle' => false
                ]) ?>
            </div>
        </div>
    </div>

    <!-- PRAWA KOLUMNA: karty -->
    <div class="col-xxl-3">
        <div class="card custom-card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="invTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" id="tab-basic" data-bs-toggle="tab" data-bs-target="#pane-basic" type="button" role="tab">Podstawowe</button></li>
                    <li class="nav-item"><button class="nav-link" id="tab-accounting" data-bs-toggle="tab" data-bs-target="#pane-accounting" type="button" role="tab">Księgowe</button></li>
                    <li class="nav-item"><button class="nav-link" id="tab-annotations" data-bs-toggle="tab" data-bs-target="#pane-annotations" type="button" role="tab">Adnotacje</button></li>
                    <li class="nav-item"><button class="nav-link" id="tab-adv" data-bs-toggle="tab" data-bs-target="#pane-adv" type="button" role="tab">Zaawansowane</button></li>
                    <li class="nav-item"><button class="nav-link" id="tab-intl" data-bs-toggle="tab" data-bs-target="#pane-intl" type="button" role="tab">Identyfikatory międz.</button></li>
                    <li class="nav-item"><button class="nav-link" id="tab-fa3ext" data-bs-toggle="tab" data-bs-target="#pane-fa3ext" type="button" role="tab">KSeF FA(3)</button></li>
                </ul>
            </div>

            <div class="card-body tab-content">
                <div class="mb-3">
                        <div class="invoice-pills d-flex flex-wrap gap-2">
                                <div class="pill">
                                <span class="pill-label">Netto</span>
                                <span class="pill-value" id="pill-net"><?= number_format($sumNet, 2, ',', ' ') ?></span>
                                </div>
                                <div class="pill">
                                <span class="pill-label">VAT</span>
                                <span class="pill-value" id="pill-vat"><?= number_format($sumTax, 2, ',', ' ') ?></span>
                                </div>
                                <div class="pill pill-accent">
                                <span class="pill-label">Brutto</span>
                                <span class="pill-value" id="pill-gross"><?= number_format($sumGross, 2, ',', ' ') ?></span>
                                </div>
                        </div>
                </div>

                <!-- PODSTAWOWE -->
                <div class="tab-pane fade show active" id="pane-basic" role="tabpanel" aria-labelledby="tab-basic">
                    <div class="row g-3">
                        <div class="col-lg-12">
                            <?= $this->Form->control('fullnumber', [
                                'label' => 'Invoice No', 'class' => 'form-control', 'placeholder' => 'auto',
                                'id' => 'invoice-number'
                            ]) ?>
                            <small class="text-muted" id="invoice-number-hint" style="display: none;">
                                <i class="ri-information-line"></i> Numer faktury: <span id="invoice-number-suggestion"></span>
                            </small>
                        </div>
                        

                        <div class="col-lg-12">
                        <?= $this->Form->control('date', [
                            'type' => 'date', 'label' => 'Data wystawienia', 'class' => 'form-control', 'id' => 'issue-date', 'required' => true
                        ]) ?>
                        </div>

                        <div class="col-lg-12">
                        <?= $this->Form->control('sold_date', [
                            'type' => 'date', 'label' => 'Data sprzedaży', 'class' => 'form-control', 'id' => 'sold-date'
                        ]) ?>
                        </div>

                        <div class="col-12">
    <label class="form-label mb-1">Termin płatności</label>
    <div class="border rounded p-2">
        <div class="row g-2 align-items-center" id="due-combined">
            <div class="col-7">
                <select id="due-days-preset" class="form-select" aria-label="Termin płatności — preset dni">
                    <?php foreach ([0,7,14,30,60,90] as $d): ?>
                        <option value="<?= $d ?>"<?= $d == 7 ? ' selected' : '' ?>><?= $d ?> dni</option>
                    <?php endforeach; ?>
                    <option value="_custom">Inna liczba…</option>
                </select>
            </div>
            <div class="col-5">
                <div class="input-group">
                    <input type="date" id="payment-date" name="paymentdate" class="form-control" value="<?= h($invoice->paymentdate?->i18nFormat('yyyy-MM-dd') ?? '') ?>">
                    <span class="input-group-text"><i class="ri-calendar-line"></i></span>
                </div>
            </div>
        </div>
        <small class="text-muted d-block mt-2">
            Obliczony termin: <span id="due-preview" class="fw-medium">—</span>
        </small>
    </div>
</div>


                        <?= $this->Form->control('paymentmethod', [
                            'label' => 'Metoda płatności', 'type' => 'select',
                            'options' => ['transfer' => 'Przelew', 'cash' => 'Gotówka', 'card' => 'Karta'],
                            'class' => 'form-select', 'value' => $invoice->paymentmethod ?? 'transfer'
                        ]) ?>

                        <div class="row g-2">
                            <div class="col-6">
                                <?= $this->Form->control('alreadypaid', [
                                    'label' => 'Zapłacono (kwota)', 'type' => 'number', 'step' => '0.01', 'class' => 'form-control', 'value' => $invoice->alreadypaid ?? 0
                                ]) ?>
                            </div>
                            <div class="col-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="is-paid-check"<?= ($invoice->paymentstate === 'paid') ? ' checked' : '' ?>>
                                    <label class="form-check-label" for="is-paid-check">Oznacz jako opłacone</label>
                                </div>
                            </div>
                        </div>

                        <div id="paid-at-group" style="display:<?= ($invoice->paymentstate === 'paid') ? '' : 'none' ?>;">
                            <?= $this->Form->control('paid_at', ['type' => 'date', 'label' => 'Data zapłaty', 'class' => 'form-control', 'value' => $invoice->paid_at?->i18nFormat('yyyy-MM-dd') ?? '' ]) ?>
                        </div>
                    </div>
                </div>

                <!-- KSIĘGOWE -->
                <div class="tab-pane fade" id="pane-accounting" role="tabpanel" aria-labelledby="tab-accounting">
                    <div class="vstack gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="flag-fp" name="flags[fp]" value="1"<?= !empty($invoice->is_receipt_invoice) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="flag-fp">Faktura do paragonu (FP)</label>
                        </div>
                    </div>
                </div>

                <!-- ZAAWANSOWANE -->
                <div class="tab-pane fade" id="pane-adv" role="tabpanel" aria-labelledby="tab-adv">
                    <div class="row g-3">
                        <div class="col-12 d-flex align-items-center gap-2">
                            <?= $this->Form->control('currency', [
                                'label' => 'Waluta', 'class' => 'form-select', 'id' => 'currency', 'value' => $invoice->currency ?? 'PLN',
                                'options' => ['PLN'=>'PLN','EUR'=>'EUR','USD'=>'USD','GBP'=>'GBP','CZK'=>'CZK']
                            ]) ?>
                        </div>

                        <div class="col-12" id="fx-rate-group" style="display:none;">
                            <?= $this->Form->control('fx_rate', [
                                'label' => 'Kurs (poglądowo)', 'type' => 'number', 'step' => '0.0001',
                                'class' => 'form-control', 'id' => 'fx-rate', 'value' => $invoice->fx_rate ?? ''
                            ]) ?>
                        </div>

                        <?= $this->Form->control('lang', [
                            'label' => 'Język faktury', 'type' => 'select', 'class' => 'form-select',
                            'options' => ['pl' => 'Polski', 'en' => 'English', 'de' => 'Deutsch', 'cs' => 'Čeština'], 'value' => $invoice->lang ?? 'pl'
                        ]) ?>

                        <?= $this->Form->control('invoice_company_detail.bank_account', [
                            'label' => 'Rachunek na fakturze', 'class' => 'form-control',
                            'placeholder' => 'PLxx xxxx xxxx xxxx xxxx xxxx xxxx', 'id' => 'bank-account', 'value' => $invoice->invoice_company_detail->bank_account ?? ''
                        ]) ?>

                        <?= $this->Form->control('template', [
                            'label' => 'Szablon wydruku', 'type' => 'select', 'class' => 'form-select',
                            'options' => ['default' => 'Domyślny', 'compact' => 'Kompaktowy', 'pro' => 'PRO'],
                            'value' => $invoice->template ?? 'default'
                        ]) ?>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto-send" name="auto_send" value="1"<?= !empty($invoice->auto_send) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="auto-send">Automatyczna wysyłka na e-mail nabywcy</label>
                        </div>
                    </div>
                </div>

                <!-- ADNOTACJE -->
                <?= $this->element('Invoices/tab_annotations') ?>

                <!-- IDENTYFIKATORY MIĘDZYNARODOWE -->
                <?= $this->element('Invoices/tab_identifiers') ?>

                <!-- KSeF FA(3) ROZSZERZONY -->
                <?= $this->element('Invoices/tab_fa3_extended') ?>

            </div>
        </div>
    </div>
</div>

<?= $this->Form->end() ?>

<!-- Modal: Katalog kontrahentów -->
<div class="modal fade" id="contractor-catalog-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Katalog kontrahentów</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <input type="text" id="catalog-search" class="form-control" placeholder="Szukaj po nazwie, NIP, mieście...">
                    </div>
                    <div class="col-sm-6 text-end">
                        <small class="text-muted" id="catalog-meta"></small>
                    </div>
                </div>

                <div class="table-responsive border rounded">
                    <table class="table table-hover mb-0" id="contractors-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40%;">Nazwa</th>
                                <th style="width:18%;">NIP</th>
                                <th style="width:30%;">Adres</th>
                                <th style="width:12%;">Miasto</th>
                            </tr>
                        </thead>
                        <tbody><!-- rows renderowane JS --></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
    </div>

<!-- Modal: Dodaj kontrahenta -->
<div class="modal fade" id="contractor-create-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Dodaj kontrahenta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <?= $this->Form->create(null, ['url' => ['controller' => 'Contractors','action' => 'add'], 'data-ajax' => '1', 'id' => 'contractor-create-form']) ?>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-12">
                        <?= $this->Form->control('name', [ 'label' => 'Nazwa kontrahenta*', 'required' => true, 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-6">
                        <?= $this->Form->control('altname', [ 'label' => 'Nazwa skrócona', 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-6">
                        <?= $this->Form->control('nip', [ 'label' => 'NIP', 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-4">
                        <?= $this->Form->control('regon', [ 'label' => 'REGON', 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-4">
                        <?= $this->Form->control('eu_vat', [ 'label' => 'EU VAT', 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-4">
                        <?= $this->Form->control('country', [ 'label' => 'Kraj', 'class' => 'form-control', 'value' => 'PL' ]) ?>
                    </div>
                    <div class="col-md-4">
                        <?= $this->Form->control('postal_code', [ 'label' => 'Kod pocztowy', 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-8">
                        <?= $this->Form->control('city', [ 'label' => 'Miasto', 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-8">
                        <?= $this->Form->control('street', [ 'label' => 'Ulica', 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-4">
                        <?= $this->Form->control('local_number', [ 'label' => 'Nr lokalu', 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-6">
                        <?= $this->Form->control('phone', [ 'label' => 'Telefon', 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-6">
                        <?= $this->Form->control('email', [ 'label' => 'Email', 'class' => 'form-control' ]) ?>
                    </div>
                    <div class="col-md-12">
                        <?= $this->Form->control('notes', [ 'label' => 'Notatki', 'class' => 'form-control', 'type' => 'textarea', 'rows' => 2 ]) ?>
                    </div>
                </div>
                <?= $this->Form->hidden('is_active', ['value' => 1]) ?>
                <?= $this->Form->hidden('deleted', ['value' => 0]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', ['class' => 'btn btn-primary', 'escapeTitle' => false]) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<!-- Modal: GUS (pobranie po NIP) -->
<div class="modal fade" id="gus-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Pobierz dane z GUS</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">NIP</label>
                <input type="text" class="form-control" id="gus-nip" placeholder="np. 5210082546">
                <small class="text-muted">Podaj NIP, a dane nabywcy zostaną uzupełnione danymi z rejestru REGON (GUS).</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                <button type="button" class="btn btn-primary" id="gus-fetch-btn">
                    <span class="spinner-border spinner-border-sm me-1 d-none" id="gus-spinner"></span>
                    Pobierz
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Dodaj produkt -->
<div class="modal fade" id="product-create-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Dodaj produkt/usługę</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <?= $this->Form->create(null, ['url' => ['controller' => 'Products','action' => 'add'], 'data-ajax' => '1', 'id' => 'product-create-form']) ?>
            <div class="modal-body">
                <?= $this->Form->control('name', ['label' => 'Nazwa*', 'required' => true, 'class' => 'form-control', 'id' => 'product-create-name']) ?>
                <div class="row g-2">
                    <div class="col-6"><?= $this->Form->control('code', ['label' => 'Kod', 'class' => 'form-control', 'id' => 'product-create-code']) ?></div>
                    <div class="col-6">
                        <?= $this->Form->control('is_service', [ 'label' => 'Typ', 'type' => 'select', 'options' => [0 => 'Produkt', 1 => 'Usługa'], 'class' => 'form-select', 'value' => 0 ]) ?>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-4"><?= $this->Form->control('unit_name', ['label' => 'Jedn.', 'class' => 'form-control', 'value' => 'szt.', 'name' => 'unit_name']) ?></div>
                    <div class="col-4"><?= $this->Form->control('net_price', ['label' => 'Cena netto', 'type' => 'number', 'step' => '0.01', 'class' => 'form-control']) ?></div>
                    <div class="col-4"><?= $this->Form->control('vat_id', ['label' => 'Stawka VAT', 'type' => 'select', 'options' => $vats, 'class' => 'form-select']) ?></div>
                </div>
                <div class="row g-2">
                    <div class="col-6"><?= $this->Form->control('pkwiu', ['label' => 'PKWiU', 'class' => 'form-control', 'placeholder' => 'np. 62.01.10.0']) ?></div>
                    <div class="col-6"><?= $this->Form->control('gtu_code', [ 'label' => 'GTU', 'type' => 'select', 'options' => [ '' => 'brak', 'GTU_01' => 'GTU_01 – napoje alkoholowe','GTU_02' => 'GTU_02 – paliwa','GTU_03' => 'GTU_03 – oleje opałowe','GTU_04' => 'GTU_04 – wyroby tytoniowe','GTU_05' => 'GTU_05 – odpady','GTU_06' => 'GTU_06 – urządzenia elektroniczne','GTU_07' => 'GTU_07 – pojazdy/części','GTU_08' => 'GTU_08 – metale szlachetne','GTU_09' => 'GTU_09 – leki/wyroby med.','GTU_10' => 'GTU_10 – budowlanka','GTU_11' => 'GTU_11 – drukowane nośniki','GTU_12' => 'GTU_12 – usługi niematerialne','GTU_13' => 'GTU_13 – transport i gospodarka magazynowa', ], 'class' => 'form-select', 'empty' => false ]) ?></div>
                </div>
                <?= $this->Form->control('description', ['label' => 'Opis', 'type' => 'textarea', 'rows' => 2, 'class' => 'form-control']) ?>
                <?= $this->Form->control('barcode', ['label' => 'Kod kreskowy', 'class' => 'form-control']) ?>
                <?= $this->Form->hidden('unit_id', ['value' => 1]) ?>
                <?= $this->Form->hidden('currency', ['value' => 'PLN']) ?>
                <?= $this->Form->hidden('is_active', ['value' => 1]) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', ['class' => 'btn btn-primary', 'escapeTitle' => false]) ?>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<!-- Toasts -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
    <div id="app-toast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="app-toast-body">…</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- Modal: Dodaj odbiorcę -->
<div class="modal fade" id="recipient-create-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Dodaj odbiorcę</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Nazwa*</label>
                    <input type="text" class="form-control" id="recipient-name" required>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">NIP</label>
                        <input type="text" class="form-control" id="recipient-nip">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="recipient-email">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label">Telefon</label>
                        <input type="text" class="form-control" id="recipient-phone">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Kod</label>
                        <input type="text" class="form-control" id="recipient-zip">
                    </div>
                </div>
                <div class="mt-1">
                    <label class="form-label">Ulica</label>
                    <input type="text" class="form-control" id="recipient-street">
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label">Miasto</label>
                        <input type="text" class="form-control" id="recipient-city">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Kraj</label>
                        <input type="text" class="form-control" id="recipient-country" value="PL">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
                <button class="btn btn-primary" id="recipient-save-btn"><i class="ri-save-line me-1"></i>Zapisz</button>
            </div>
        </div>
    </div>
</div>

<!-- Skrócona walidacja AJAX jak w add.php -->
<script>
(function(){
    const $form = $('form.needs-validation').first();
    if (!$form.length) return;
    const csrf = $('meta[name="csrfToken"]').attr('content') || '';

    async function runValidation(){
        try{
            const res = await fetch('<?= $this->Url->build(["controller"=>"Invoices","action"=>"validateAjax"]) ?>', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: new URLSearchParams($form.serialize())
            });
            const json = await res.json();
            return !!(json && json.success);
        }catch(e){ return false; }
    }
    $('#btn-validate').on('click', async function(e){ e.preventDefault(); const ok = await runValidation(); window.showToast(ok ? 'Formularz wygląda poprawnie. Możesz zapisać fakturę.' : 'Formularz zawiera błędy. Sprawdź zaznaczone pola.', ok ? 'success' : 'danger'); });
})();
</script>

<!-- Logika formularza: dodawanie/usuwanie pozycji, kalkulacje, termin płatności, pigułki -->
<script>
$(function(){
    function toNum(v, def){ var n=parseFloat(v); return isFinite(n)?n:(def||0); }
    var vatRates = <?= json_encode($vatRatesMap ?? []) ?>;

    function rowCalc($tr){
        var q = toNum($tr.find('.item-qty').val(), 0);
        var p = toNum($tr.find('.item-price').val(), 0);
        var disc = toNum($tr.find('.item-disc').val(), 0);
        var vatCode = $tr.find('.item-vatcode').val();
        var rate = toNum(vatRates[vatCode], 0);
        var unit = p * (1 - (disc/100));
        var net = +(q * unit).toFixed(2);
        var tax = +(net * (rate/100)).toFixed(2);
        var gro = +(net + tax).toFixed(2);
        $tr.find('.item-net').val(net.toFixed(2));
        $tr.find('.item-gross').val(gro.toFixed(2));
    }
    function mirrorSums(){
        var sn = $('#sum-net').val() || '0.00';
        var st = $('#sum-tax').val() || '0.00';
        var sg = $('#sum-gross').val() || '0.00';
        $('#pill-net').text(parseFloat(sn).toLocaleString('pl-PL', {minimumFractionDigits:2}));
        $('#pill-vat').text(parseFloat(st).toLocaleString('pl-PL', {minimumFractionDigits:2}));
        $('#pill-gross').text(parseFloat(sg).toLocaleString('pl-PL', {minimumFractionDigits:2}));
    }
    function allCalc(){
        var sn=0, sg=0; $('#items-body tr.item-row').each(function(){ var $tr=$(this); if($tr.find('.item-net').length){ sn+=toNum($tr.find('.item-net').val(),0); sg+=toNum($tr.find('.item-gross').val(),0);} });
        var st=+(sg-sn).toFixed(2);
        $('#sum-net').val(sn.toFixed(2)); $('#sum-tax').val(st.toFixed(2)); $('#sum-gross').val(sg.toFixed(2));
        mirrorSums();
    }
    function bindRow($tr){
        $tr.on('input change', '.item-qty,.item-price,.item-disc,.item-vatcode', function(){ rowCalc($tr); allCalc(); });
        $tr.on('click', '.btn-remove', function(){
            var $rows = $('#items-body tr.item-row').not('#row-template');
            if ($rows.length > 1) {
                $tr.remove();
                renumberRows();
                allCalc();
                guardMinRows();
            }
        });
        // Jeśli produkt jest wpisany ręcznie (np. NEW:), zapewnij zapis nazwy w ukrytym polu
        $tr.on('change', '.item-product-select', function(){
            var $sel=$(this), idx=$sel.data('index');
            var val = $sel.find('option:selected').text() || $sel.val() || '';
            $tr.find('.item-name-hidden').val(val.replace(/^NEW:/,''));
        });
        rowCalc($tr);
    }
    function newRow(index){
        var html = $('#row-template').prop('outerHTML')
            .replace(/__INDEX__/g, index)
            .replace('style="display:none;"','');
        var $row = $(html);
        bindRow($row);
        return $row;
    }
    function renumberRows(){
        $('#items-body tr.item-row').each(function(i){
            var $tr=$(this);
            $tr.find('[name^="items["]').each(function(){
                var name=$(this).attr('name');
                if(!name)return;
                name = name.replace(/items\[[0-9]+\]/,'items['+i+']');
                $(this).attr('name', name);
            });
            $tr.find('.item-product-select').attr('data-index', i);
        });
    }

    // Blokada usuwania, gdy jest tylko jeden wiersz pozycji
    function guardMinRows(){
        var count = $('#items-body tr.item-row').not('#row-template').length;
        var disable = count <= 1;
        $('#items-body .btn-remove').prop('disabled', disable)
            .attr('title', disable ? 'Musi pozostać co najmniej 1 pozycja' : 'Usuń');
    }

    // Init existing rows
    $('#items-body tr.item-row').each(function(){ bindRow($(this)); });
    allCalc();
    guardMinRows();

    // Add row
    $('#btn-add-item').on('click', function(){
        var idx = $('#items-body tr.item-row').length;
        var $row = newRow(idx);
        $('#items-body tr#row-template').before($row);
        renumberRows();
        allCalc();
        guardMinRows();
    });

    // Paid toggle
    $('#is-paid-check').on('change', function(){
        $('#paid-at-group').toggle(this.checked);
    });

    // Currency FX rate show/hide
    function updateFx(){ var cur=$('#currency').val(); $('#fx-rate-group').toggle(cur && cur !== 'PLN'); }
    $('#currency').on('change', updateFx); updateFx();

    // Due date preset: calculate preview and set payment date
    function fmt(d){ return d.toISOString().slice(0,10); }
    function calcDue(){
        var issue = $('#issue-date').val();
        var preset = $('#due-days-preset').val();
        if(!issue) return;
        var base = new Date(issue+'T00:00:00');
        var days = 0;
        if(preset && preset !== '_custom'){ days = parseInt(preset||'0',10)||0; }
        var due = new Date(base.getTime() + days*24*3600*1000);
        $('#due-preview').text(fmt(due));
        // Ustaw też payment-date jeśli preset != _custom
        if(preset !== '_custom'){ $('#payment-date').val(fmt(due)); }
    }
    $('#issue-date, #due-days-preset').on('change', calcDue);
    calcDue();
});
</script>

<style>
    /* Select2 and compact invoice tweaks */
    .select2-results__group{ font-size:.75rem; color:#6c757d; text-transform:uppercase; padding:.35rem .75rem; border-top:1px solid #eef1f4; background:#f8fafc; position:sticky; top:0; z-index:1; }
    .select2-results__options > .select2-results__group:first-child{ border-top:none; }
    .select2-dropdown{ overflow: visible; }
    .select2-results{ max-height: 260px; }
    .select2-container--open { z-index: 1055; }
    .select2-container--default.select2-compact .select2-selection--single{ height:34px; }
    .select2-container--default.select2-compact .select2-selection__rendered{ line-height:32px; padding-left:.5rem; }
    .select2-container--default.select2-compact .select2-selection__arrow{ height:32px; right:.35rem; }
    .invoice-compact .form-control[readonly]{ background:#f7f8fa; color:#495057; }
    #contractors-table tbody tr.catalog-row{ cursor:pointer; }
    #contractors-table tbody tr.catalog-row:hover{ background:#f5f7fb; }
        /* --- pastylki podsumowania --- */
        .invoice-pills .pill{ display:flex; align-items:center; gap:.5rem; background:#f8fafc; border:1px solid #eef1f4; border-radius:12px; padding:.5rem .75rem; }
        .invoice-pills .pill-accent{ background:#eef6ff; border-color:#d7e9ff; }
        .invoice-pills .pill-label{ font-size:.75rem; color:#6c757d; }
        .invoice-pills .pill-value{ font-weight:700; font-variant-numeric: tabular-nums; }
</style>

<!-- Select2 contractor/product + modals/parity with add.php -->
<script>
(function(){
    // Compact select2 when open
    $(document).on('select2:open', () => {
        document.querySelectorAll('.select2-container').forEach(c => c.classList.add('select2-compact'));
    });

    function toast(msg){
        var el = document.getElementById('app-toast');
        if (el && window.bootstrap && bootstrap.Toast){
            $('#app-toast-body').text(msg||'');
            new bootstrap.Toast(el).show();
        } else {
            alert(msg);
        }
    }

    // URLs and helpers
    var csrf = $('meta[name="csrfToken"]').attr('content') || '';
    var vatRates = <?= json_encode($vatRatesMap ?? []) ?>;
    var contractorUrl = '<?= $this->Url->build(['controller'=>'Contractors','action'=>'search','_ext'=>'json']) ?>';
    var productUrl    = '<?= $this->Url->build(['controller'=>'Products','action'=>'search','_ext'=>'json']) ?>';
    var gusUrl        = '<?= $this->Url->build(['controller'=>'Contractors','action'=>'gusLookup','_ext'=>'json']) ?>';
  

    // Contractor snapshot
    function showContractorSnapshot(){ var $b=$('#contractor-snapshot'); if($b.is(':hidden')) $b.stop(true,true).slideDown(120); }
    function hideContractorSnapshot(){ $('#contractor-snapshot').stop(true,true).slideUp(120); }
    function fillContractorSnapshot(c){
        var data = { name:c.name||c.label||'', nip:c.nip||'', street:c.street||'', zip:c.zip||c.postal_code||c.postalCode||'', city:c.city||'', country:c.country||'PL', email:c.email||'', phone:c.phone||'' };
        function setField(key, val){ var $t=$('[name="invoice_contractor['+key+']"],[name="invoice_contractor.'+key+']'); if($t.length) $t.val(val==null?'':val).trigger('change'); }
        Object.keys(data).forEach(function(k){ setField(k, data[k]); });
    }
    function applyContractor(c){
        if (!c) return; fillContractorSnapshot(c); showContractorSnapshot();
        if ($.fn && $.fn.select2){ var $sel=$('#contractor-select'); var label=c.label||c.name|| (c.nip ? (c.name+' ('+c.nip+')') : c.name) || 'Kontrahent'; var value=c.id || ('LS:'+(c.nip || (c.name||'').slice(0,30))); $sel.find('option[value="'+value+'"]').remove(); var opt=new Option(label, value, true, true); $sel.append(opt).trigger('change'); }
        $('#contractor-id-input').val(c.id || '');
    }
    function clearContractorSnapshot(){ ['name','nip','street','zip','city','country','email','phone'].forEach(function(f){ $('[name="invoice_contractor['+f+']"]').val(f==='country'?'PL':''); }); $('#contractor-id-input').val(''); }

    // Contractor Select2 + toolbar
    if ($.fn && $.fn.select2){
        var $contractor = $('#contractor-select').select2({
            placeholder: $('#contractor-select').data('placeholder') || 'Wpisz nazwę kontrahenta lub NIP',
            allowClear: true, minimumInputLength: 1, dropdownAutoWidth: true, width: '100%',
            ajax: { url: contractorUrl, dataType: 'json', delay: 200, cache: true, data: function(p){ return { q:(p.term||'') }; }, processResults: function (data) { var results = $.map(data||[], function(c){ return $.extend({ id:c.id, text:(c.label||c.name) }, c); }); return { results: results }; } },
            matcher: function(params, data){ var term=$.trim(params.term||''); if(!term) return data; if (typeof data.text==='string' && data.text.toLowerCase().indexOf(term.toLowerCase())>-1) return data; if (data.nip && String(data.nip).indexOf(term)>-1) return data; return null; },
            language: { inputTooShort: ()=> 'Wpisz co najmniej 1 znak', searching: ()=> 'Szukam…', noResults: ()=> 'Brak wyników' },
            escapeMarkup: function (m) { return m; }
        })
        .on('select2:open', function(){ injectContractorToolbar(); showContractorSnapshot(); })
        .on('select2:select', function(e){ var d=e.params.data||{}; fillContractorSnapshot(d); showContractorSnapshot(); $('#contractor-id-input').val(d.id || ''); })
        .on('select2:clear', function(){ clearContractorSnapshot(); hideContractorSnapshot(); });
    }

    function injectContractorToolbar(){
        var $dd = $('.select2-container--open .select2-dropdown');
        if (!$dd.length || $dd.find('.ctr-toolbar').length) return;
        var $search = $dd.find('.select2-search--dropdown');
        var toolbar = $(
            '<div class="ctr-toolbar">'+
                '<div class="btn-group" role="group" aria-label="Akcje kontrahenta">'+
                    '<button type="button" class="btn btn-sm btn-outline-primary ctr-act-add"><i class="ri-add-line"></i> Dodaj nowego</button>'+
                    '<button type="button" class="btn btn-sm btn-outline-primary ctr-act-gus"><i class="ri-download-2-line"></i> Pobierz z GUS</button>'+
                    '<button type="button" class="btn btn-sm btn-outline-primary ctr-act-cat"><i class="ri-search-line"></i> Szukaj w katalogu</button>'+
                    '<button type="button" class="btn btn-sm btn-outline-secondary ctr-act-rec"><i class="ri-user-add-line"></i> Dodaj odbiorcę</button>'+
                '</div>'+
                '<button type="button" class="btn btn-sm btn-outline-secondary ms-2 ctr-act-clear" title="Wyczyść"><i class="ri-close-line"></i></button>'+
            '</div>'
        );
        $search.after(toolbar);
        $dd.find('.ctr-act-add').on('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); try{$('#contractor-select').select2('close');}catch(_){ } $('#contractor-create-modal').modal('show'); });
        $dd.find('.ctr-act-gus').on('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); try{$('#contractor-select').select2('close');}catch(_){ } $('#gus-modal').modal('show'); });
        $dd.find('.ctr-act-cat').on('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); try{$('#contractor-select').select2('close');}catch(_){ } openCatalog(); });
        $dd.find('.ctr-act-rec').on('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); try{$('#contractor-select').select2('close');}catch(_){ } $('#recipient-create-modal').modal('show'); });
        $dd.find('.ctr-act-clear').on('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); $('#contractor-select').val(null).trigger('change'); clearContractorSnapshot(); hideContractorSnapshot(); });
    }

    // GUS lookup
    $('#gus-fetch-btn').on('click', function(){
        var nip = ($('#gus-nip').val()||'').replace(/\D/g,'');
        if(nip.length!==10){ toast('Podaj prawidłowy 10-cyfrowy NIP.'); return; }
        $('#gus-spinner').removeClass('d-none');
        $.ajax({ url: gusUrl, method: 'POST', data: JSON.stringify({ nip: nip }), contentType: 'application/json', headers: { 'X-CSRF-Token': csrf, 'Accept':'application/json' } })
        .done(function(res){ if(res && res.success){ var c = res.contractor || {}; c.name = c.name || c.fullName || c.company || ''; c.country = c.country || 'PL'; fillContractorSnapshot(c); showContractorSnapshot(); if ($.fn && $.fn.select2){ var label=c.label||c.name||(c.nip?(c.name+' ('+c.nip+')'):c.name)||'Kontrahent'; var value=c.id||('GUS:'+(c.nip||nip)); var $sel=$('#contractor-select'); $sel.find('option[value="'+value+'"]').remove(); var opt=new Option(label, value, true, true); $sel.append(opt).trigger('change'); } $('#gus-modal').modal('hide'); } else { toast(res && res.message ? res.message : 'Nie znaleziono danych w GUS.'); } })
        .fail(function(xhr){ console.error('gus fail', xhr.status, xhr.responseText); toast('Błąd podczas pobierania z GUS.'); })
        .always(function(){ $('#gus-spinner').addClass('d-none'); });
    });

    // Product select2 per row
    function initProductSelectForRow($tr){
        if (!($.fn && $.fn.select2)) return;
        var $sel = $tr.find('.item-product-select');
        var $nameHidden = $tr.find('.item-name-hidden');
        $sel.select2({
            placeholder: $sel.data('placeholder') || 'Wybierz lub wpisz produkt',
            ajax: { url: productUrl, dataType: 'json', delay: 200, data: function(p){ return { q:p.term }; }, processResults: function (data) { if (data && data.success && data.results) { return { results: $.map(data.results, function (p) { return $.extend({ id:p.id, text: p.text || (p.code ? p.code + ' - ' + p.name : p.name) }, p); }) }; } return { results: [] }; } },
            minimumInputLength: 1,
            tags: true,
            createTag: function (params) { var term=$.trim(params.term||''); if(!term) return null; return { id:'NEW:'+term, text:term, isNew:true }; },
            language: { noResults: function(){ return '<div class="p-2 text-center"><div class="small text-muted mb-1">Brak produktów</div><button type="button" class="btn btn-sm btn-primary add-product-inline"><i class="ri-add-line"></i> Dodaj produkt</button></div>'; } },
            escapeMarkup: function (m) { return m; },
            width: '100%'
        })
        .on('select2:open', function(){ var $dd = $('.select2-container--open .select2-dropdown'); if ($dd.find('.prod-toolbar').length===0){ var $search = $dd.find('.select2-search--dropdown'); $search.after('<div class="prod-toolbar p-2 border-bottom bg-white"><button type="button" class="btn btn-sm btn-outline-primary s2-add-product"><i class="ri-add-line"></i> Dodaj produkt</button></div>'); $dd.on('mousedown', '.s2-add-product', function(e){ e.preventDefault(); e.stopPropagation(); var inst=$sel.data('select2'); var query=inst && inst.dropdown && inst.dropdown.$search ? inst.dropdown.$search.val() : ''; $('#product-create-name').val(query||''); $('#product-create-modal').modal('show'); try{ $sel.select2('close'); }catch(_){ } }); } })
        .on('select2:select', function(e){ var d=e.params && e.params.data || {}; if (String(d.id||'').indexOf('NEW:')===0){ var name = d.text || String(d.id).slice(4); $nameHidden.val(name); } else { $nameHidden.val(d.name || d.text || ''); if (typeof d.price!=='undefined' && d.price!==null && d.price!==''){ $tr.find('.item-price').val(Number(d.price).toFixed(2)); } var $vat=$tr.find('.item-vatcode'); if ($vat.length && d.vat_id) $vat.val(d.vat_id); } $tr.find('.item-price,.item-qty,.item-disc,.item-vatcode').trigger('change'); });

        // „Dodaj produkt” z noResults
        $(document).off('mousedown.addProductInline').on('mousedown.addProductInline', '.add-product-inline', function (ev) { ev.preventDefault(); var inst=$sel.data('select2'); var query=inst && inst.dropdown && inst.dropdown.$search ? inst.dropdown.$search.val() : ''; $('#product-create-name').val(query||''); $('#product-create-modal').modal('show'); try{ $sel.select2('close'); }catch(_){ } });
    }

    // Initialize product Select2 for existing rows
    $('#items-body tr.item-row').each(function(){ initProductSelectForRow($(this)); });

    // Initialize for new rows after they are added (hook existing handler)
    $(document).on('click', '#btn-add-item', function(){ setTimeout(function(){ var $last = $('#items-body tr.item-row').not('#row-template').last(); initProductSelectForRow($last); }, 0); });

    // Product modal AJAX add
    $('#product-create-form').on('submit', function (e) {
        e.preventDefault(); var $f=$(this);
        // Generate simple code if missing
        var name = $f.find('[name="name"]').val()||''; var code=$f.find('[name="code"]').val(); if (!code && name){ code = name.replace(/[^a-zA-Z0-9]/g,'').substring(0,10).toUpperCase(); $f.find('[name="code"]').val(code); }
        $.ajax({ url: $f.attr('action'), method: 'POST', data: new FormData(this), processData:false, contentType:false, headers: { 'X-CSRF-Token': csrf, 'Accept':'application/json' } })
        .done(function(data){ if (data && data.success && data.product){ var product=data.product; var displayName=product.name||name; var price=parseFloat(product.net_price||'0')||0; var $row = $('#items-body tr.item-row').not('#row-template').last(); if ($row.length){ $row.find('.item-name-hidden').val(displayName); $row.find('.item-price').val(price.toFixed(2)); var $vat=$row.find('.item-vatcode'); var vatId=product.vat_id; if ($vat.length && vatId) $vat.val(vatId); var $sel=$row.find('.item-product-select'); var displayText = product.code ? (product.code + ' - ' + displayName) : displayName; if (product.is_service) displayText += ' (usługa)'; var opt = new Option(displayText, product.id, true, true); $sel.append(opt).trigger('change'); $row.find('.item-price,.item-qty,.item-disc,.item-vatcode').trigger('change'); }
            $('#product-create-modal').modal('hide'); $f[0].reset(); toast(data.message || 'Produkt został dodany.'); } else { toast(data.message || 'Nie udało się dodać produktu.'); } })
        .fail(function(xhr){ console.error('product add fail', xhr.status, xhr.responseText); toast('Błąd komunikacji przy dodawaniu produktu.'); });
    });

    // Contractor modal AJAX add
    $('#contractor-create-form').on('submit', function (e) {
        e.preventDefault(); var $f=$(this);
        $.ajax({ url: $f.attr('action'), method: 'POST', data: new FormData(this), processData:false, contentType:false, headers: { 'X-CSRF-Token': csrf, 'Accept':'application/json' } })
        .done(function(data){ if (data && data.success && data.contractor){ var contractor=data.contractor; applyContractor(contractor); $('#contractor-create-modal').modal('hide'); $f[0].reset(); toast(data.message || 'Kontrahent został dodany i przypisany.'); } else { toast(data.message || 'Nie udało się dodać kontrahenta.'); } })
        .fail(function(xhr){ console.error('contractor add fail', xhr.status, xhr.responseText); toast('Błąd komunikacji przy dodawaniu kontrahenta.'); });
    });

    // Catalog modal logic
    var catalogData = [];
    function fetchContractorsForCatalog(){ return $.ajax({ url: contractorUrl, dataType:'json', data:{ q:'', limit:1000, all:'true' }, headers:{ 'Accept':'application/json' } }); }
    function renderCatalog(list){ catalogData = Array.isArray(list) ? list : []; var $tb=$('#contractors-table tbody'); var rows = catalogData.map(function(c){ var name=c.label||c.name||''; var nip=c.nip||''; var addr=$.grep([c.street,c.zip], Boolean).join(', '); var city=c.city||''; return '<tr class="catalog-row" data-json=\''+ JSON.stringify(c).replace(/'/g,'&#39;') +'\'><td>'+ $('<div>').text(name).html() +'</td><td>'+ $('<div>').text(nip).html() +'</td><td>'+ $('<div>').text(addr).html() +'</td><td>'+ $('<div>').text(city).html() +'</td></tr>'; }).join(''); $tb.html(rows || '<tr><td colspan="4" class="text-center text-muted">Brak danych</td></tr>'); $('#catalog-meta').text('Łącznie: ' + catalogData.length); }
    function openCatalog(){ $('#contractor-catalog-modal').modal('show'); if (catalogData.length) return; fetchContractorsForCatalog().done(function(data){ renderCatalog(data||[]); }).fail(function(){ toast('Nie udało się pobrać katalogu kontrahentów.'); }); }
    window.openCatalog = openCatalog;
    $(document).on('input', '#catalog-search', function(){ var term=(this.value||'').toLowerCase().trim(); $('#contractors-table tbody tr').each(function(){ var txt=$(this).text().toLowerCase(); $(this).toggle(txt.indexOf(term)>-1); }); });
    $(document).on('click', '#contractors-table tbody tr.catalog-row', function(){ var raw=$(this).attr('data-json')||'{}'; var decoded=raw.replace(/&#39;/g, "'"); var c={}; try{ c=JSON.parse(decoded); }catch(e){ return; } applyContractor(c); $('#contractor-catalog-modal').modal('hide'); });

    // Odbiorca (modal + snapshot)
    $('#recipient-save-btn').on('click', function(){
        var data = {
            name:$('#recipient-name').val()||'', nip:$('#recipient-nip').val()||'',
            email:$('#recipient-email').val()||'', phone:$('#recipient-phone').val()||'',
            zip:$('#recipient-zip').val()||'', street:$('#recipient-street').val()||'',
            city:$('#recipient-city').val()||'', country:$('#recipient-country').val()||'PL'
        };
        Object.keys(data).forEach(function(k){ var $t=$('[name="invoice_recipient['+k+']"], [name="invoice_recipient.'+k+']'); if ($t.length) $t.val(data[k]).trigger('change'); });
        $('#recipient-snapshot').slideDown(120);
        $('#recipient-create-modal').modal('hide');
    });
    $('#recipient-edit-btn').on('click', function(){
        $('#recipient-name').val($('[name="invoice_recipient[name]"]').val()||'');
        $('#recipient-nip').val($('[name="invoice_recipient[nip]"]').val()||'');
        $('#recipient-email').val($('[name="invoice_recipient[email]"]').val()||'');
        $('#recipient-phone').val($('[name="invoice_recipient[phone]"]').val()||'');
        $('#recipient-zip').val($('[name="invoice_recipient[zip]"]').val()||'');
        $('#recipient-street').val($('[name="invoice_recipient[street]"]').val()||'');
        $('#recipient-city').val($('[name="invoice_recipient[city]"]').val()||'');
        $('#recipient-country').val($('[name="invoice_recipient[country]"]').val()||'PL');
        $('#recipient-create-modal').modal('show');
    });

})();
</script>

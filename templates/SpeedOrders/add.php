<?php
/**
 * Formularz recznego tworzenia / edycji zlecenia transportowego.
 *
 * @var \App\View\AppView            $this
 * @var \App\Model\Entity\SpeedOrder $order
 * @var bool                         $isEdit
 * @var array                        $drivers
 * @var array                        $vehicles
 */
$this->assign('title', $isEdit
    ? __('Edycja zlecenia {0}', h($order->symbol))
    : __('Nowe zlecenie transportowe'));

$formUrl = $isEdit
    ? $this->Url->build(['action' => 'edit', $order->id])
    : $this->Url->build(['action' => 'add']);

$currencies = ['PLN', 'EUR', 'USD', 'GBP', 'CHF', 'CZK', 'NOK', 'SEK', 'DKK', 'HUF'];
$vatRates = [
    '23' => '23%',
    '8'  => '8%',
    '5'  => '5%',
    '0'  => '0%',
    'np' => __('n/p (nie podlega)'),
    'zw' => __('zw (zwolniona)'),
    'oo' => __('Reverse Charge'),
];
$countries = ['PL','DE','CZ','SK','AT','NL','BE','FR','ES','IT','HU','RO','LT','LV','EE','SE','NO','DK','FI','CH','GB','IE','PT','SI','HR','BG'];
$contracts = ['OWN 1', 'OWN 2', 'OWN 3', 'OWN PL 1', 'OWN PL 2', 'OWN X1'];

$currentVatRate = '23';
if ($isEdit && (float)$order->netto > 0) {
    $rate = round(((float)$order->vat / (float)$order->netto) * 100);
    if (in_array((string)$rate, ['0','5','8','23'], true)) $currentVatRate = (string)$rate;
    elseif ((float)$order->vat == 0.0 && (float)$order->netto == (float)$order->brutto) $currentVatRate = 'np';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold">
            <?= $isEdit ? __('Edytuj zlecenie') : __('Nowe zlecenie transportowe') ?>
            <span class="badge bg-primary-subtle text-primary ms-2"><?= h($order->symbol ?? '') ?></span>
        </h4>
        <div class="text-muted small mt-1">
            <?= $isEdit
                ? __('Numer i data dokumentu są niezmienne. Edytujesz pozostałe dane.')
                : __('Ustaw dane zlecenia. Numer zostanie nadany automatycznie w formacie M-NNNN/MM/RRRR.') ?>
        </div>
    </div>
    <div>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="ri-close-line me-1"></i> <?= __('Anuluj') ?>
        </a>
    </div>
</div>

<?= $this->Form->create(null, [
    'url'   => $formUrl,
    'type'  => 'post',
    'id'    => 'form-manual-order',
    'class' => 'row g-3',
]) ?>

<!-- Sekcja 1: Numer + meta -->
<div class="col-12">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Numer zlecenia') ?></label>
                    <input type="text" name="symbol" class="form-control" value="<?= h($order->symbol ?? '') ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Data dokumentu') ?></label>
                    <input type="date" name="date_doc" class="form-control" value="<?= h($order->date_doc ? $order->date_doc->format('Y-m-d') : date('Y-m-d')) ?>" <?= $isEdit ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Kontrakt') ?></label>
                    <input type="text" name="contract" list="contract-list" class="form-control" value="<?= h($order->contract ?? '') ?>" placeholder="OWN 1">
                    <datalist id="contract-list">
                        <?php foreach ($contracts as $c): ?>
                            <option value="<?= h($c) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Nasz nr referencyjny') ?></label>
                    <input type="text" name="our_ref" class="form-control" value="<?= h($order->our_ref ?? '') ?>" maxlength="100" placeholder="REF/2026/001">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sekcja 2: Nabywca -->
<div class="col-12">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-semibold text-uppercase text-muted small">
                    <i class="ri-user-line me-1"></i> <?= __('Zleceniodawca (nabywca)') ?>
                </h6>
                <div class="input-group input-group-sm" style="max-width:340px">
                    <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                    <input type="text" id="buyer-search" class="form-control" placeholder="<?= __('Szukaj kontrahenta (nazwa / NIP)') ?>">
                </div>
            </div>
            <div id="buyer-results" class="list-group mb-2 d-none" style="max-height:180px;overflow-y:auto"></div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('NIP') ?></label>
                    <input type="text" name="buyer_nip" class="form-control" value="<?= h($order->buyer_nip ?? '') ?>" maxlength="30">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Nazwa') ?> *</label>
                    <input type="text" name="buyer_name" class="form-control" value="<?= h($order->buyer_name ?? '') ?>" required maxlength="255">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Email') ?></label>
                    <input type="email" name="buyer_email" class="form-control" value="<?= h($order->buyer_email ?? '') ?>" maxlength="180">
                </div>
                <div class="col-md-5">
                    <label class="form-label small text-muted"><?= __('Ulica') ?></label>
                    <input type="text" name="buyer_street" class="form-control" value="<?= h($order->buyer_street ?? '') ?>" maxlength="255">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Kod pocztowy') ?></label>
                    <input type="text" name="buyer_postal_code" class="form-control" value="<?= h($order->buyer_postal_code ?? '') ?>" maxlength="20">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Miasto') ?></label>
                    <input type="text" name="buyer_city" class="form-control" value="<?= h($order->buyer_city ?? '') ?>" maxlength="120">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Kraj') ?></label>
                    <select name="buyer_country" class="form-select">
                        <?php foreach ($countries as $cc): ?>
                            <option value="<?= h($cc) ?>" <?= ($order->buyer_country ?? 'PL') === $cc ? 'selected' : '' ?>><?= h($cc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sekcja 3: Zaladunek + Rozladunek -->
<div class="col-md-6">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small">
                <i class="ri-truck-line me-1 text-success"></i> <?= __('Załadunek') ?>
            </h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kraj') ?></label>
                    <select name="load_country" class="form-select">
                        <option value=""></option>
                        <?php foreach ($countries as $cc): ?>
                            <option value="<?= h($cc) ?>" <?= ($order->load_country ?? '') === $cc ? 'selected' : '' ?>><?= h($cc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kod pocztowy') ?></label>
                    <input type="text" name="load_postal_code" class="form-control" value="<?= h($order->load_postal_code ?? '') ?>" maxlength="20">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Miasto') ?></label>
                    <input type="text" name="load_city" class="form-control" value="<?= h($order->load_city ?? '') ?>" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('ZaładunekPLANNED_') ?></label>
                    <input type="datetime-local" name="date_deadline" class="form-control" value="<?= h($order->date_deadline ? $order->date_deadline->format('Y-m-d\TH:i') : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('ZaładunekACTUAL_') ?></label>
                    <input type="datetime-local" name="actual_load_at" class="form-control" value="<?= h($order->actual_load_at ? $order->actual_load_at->format('Y-m-d\TH:i') : '') ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="col-md-6">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small">
                <i class="ri-inbox-line me-1 text-danger"></i> <?= __('Rozładunek') ?>
            </h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kraj') ?></label>
                    <select name="unload_country" class="form-select">
                        <option value=""></option>
                        <?php foreach ($countries as $cc): ?>
                            <option value="<?= h($cc) ?>" <?= ($order->unload_country ?? '') === $cc ? 'selected' : '' ?>><?= h($cc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label small text-muted"><?= __('Miasto') ?></label>
                    <input type="text" name="unload_city" class="form-control" value="<?= h($order->unload_city ?? '') ?>" maxlength="100">
                </div>
                <div class="col-md-12">
                    <label class="form-label small text-muted"><?= __('RozładunekNAME_') ?></label>
                    <input type="text" name="unload_name" class="form-control" value="<?= h($order->unload_name ?? '') ?>" maxlength="200" placeholder="Magazyn XYZ Sp. z o.o.">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('RozładunekPLANNED_') ?></label>
                    <input type="datetime-local" name="date_delivery" class="form-control" value="<?= h($order->date_delivery ? $order->date_delivery->format('Y-m-d\TH:i') : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('RozładunekACTUAL_') ?></label>
                    <input type="datetime-local" name="actual_unload_at" class="form-control" value="<?= h($order->actual_unload_at ? $order->actual_unload_at->format('Y-m-d\TH:i') : '') ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sekcja 4: Ladunek -->
<div class="col-12">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small">
                <i class="ri-archive-line me-1"></i> <?= __('Ładunek') ?>
            </h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Nr referencyjny klienta (title1)') ?></label>
                    <input type="text" name="title1" class="form-control" value="<?= h($order->title1 ?? '') ?>" maxlength="255" placeholder="ES-975377">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Opis ładunku (title2)') ?></label>
                    <input type="text" name="title2" class="form-control" value="<?= h($order->title2 ?? '') ?>" maxlength="255" placeholder="Towar paletowy">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('ŁadunekTYPE_') ?></label>
                    <input type="text" name="cargo_type" class="form-control" value="<?= h($order->cargo_type ?? '') ?>" maxlength="120" placeholder="FTL, LTL, ADR">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Rodzaj transportu') ?></label>
                    <input type="text" name="transport_type" class="form-control" value="<?= h($order->transport_type ?? '') ?>" maxlength="100" placeholder="Rodzaj transportuPH_">
                </div>
                <div class="col-md-8">
                    <label class="form-label small text-muted"><?= __('Uwagi') ?></label>
                    <textarea name="notes" class="form-control" rows="2" maxlength="2000"><?= h($order->notes ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sekcja 5: Transport -->
<div class="col-12">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small">
                <i class="ri-truck-fill me-1"></i> <?= __('Transport') ?>
            </h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kierowca') ?></label>
                    <input type="text" name="driver" list="driver-list" class="form-control" value="<?= h($order->driver ?? '') ?>" maxlength="200">
                    <datalist id="driver-list">
                        <?php foreach ($drivers as $d): ?>
                            <option value="<?= h($d['full_name']) ?>"><?= h($d['phone'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Rejestracja pojazdu') ?></label>
                    <input type="text" name="vehicle_reg" list="vehicle-list" class="form-control" value="<?= h($order->vehicle_reg ?? '') ?>" maxlength="50">
                    <datalist id="vehicle-list">
                        <?php foreach ($vehicles as $v): ?>
                            <?php $lbl = trim(($v['plate'] ?? '') . ' - ' . ($v['name'] ?? ''), ' -'); ?>
                            <option value="<?= h($v['plate'] ?? '') ?>"><?= h($lbl) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Przewoźnik (jeśli inny niż my)') ?></label>
                    <input type="text" name="carrier" class="form-control" value="<?= h($order->carrier ?? '') ?>" maxlength="200">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sekcja 6: Finanse -->
<div class="col-12">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small">
                <i class="ri-money-euro-circle-line me-1"></i> <?= __('Finanse') ?>
            </h6>
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Waluta') ?></label>
                    <select name="currency" id="fin-currency" class="form-select">
                        <?php foreach ($currencies as $cur): ?>
                            <option value="<?= h($cur) ?>" <?= ($order->currency ?? 'PLN') === $cur ? 'selected' : '' ?>><?= h($cur) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Kurs') ?></label>
                    <input type="number" step="0.000001" name="exchange_rate" id="fin-rate" class="form-control" value="<?= h($order->exchange_rate ?? '1.000000') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Netto') ?> *</label>
                    <input type="number" step="0.01" min="0" name="netto" id="fin-netto" class="form-control" value="<?= h($order->netto ?? '0.00') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Stawka VAT') ?></label>
                    <select name="vat_rate" id="fin-vat-rate" class="form-select">
                        <?php foreach ($vatRates as $val => $lbl): ?>
                            <option value="<?= h($val) ?>" <?= $currentVatRate === (string)$val ? 'selected' : '' ?>><?= h($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('VAT') ?></label>
                    <input type="text" id="fin-vat" class="form-control" value="<?= h($order->vat ?? '0.00') ?>" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Brutto') ?></label>
                    <input type="text" id="fin-brutto" class="form-control fw-semibold" value="<?= h($order->brutto ?? '0.00') ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Warunki płatności') ?></label>
                    <input type="text" name="payment_terms" class="form-control" value="<?= h($order->payment_terms ?? 'Przelew 30 dni') ?>" maxlength="100">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="col-12 text-end">
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary">
        <?= __('Anuluj') ?>
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line me-1"></i>
        <?= $isEdit ? __('Zapisz zmiany') : __('Utwórz zlecenie') ?>
    </button>
</div>

<?= $this->Form->end() ?>

<script>
(function(){
    'use strict';

    var $netto = document.getElementById('fin-netto');
    var $rate  = document.getElementById('fin-vat-rate');
    var $vat   = document.getElementById('fin-vat');
    var $brut  = document.getElementById('fin-brutto');

    function calc() {
        var n = parseFloat($netto.value) || 0;
        var r = $rate.value;
        var v = 0, b = n;
        if (r === '23' || r === '8' || r === '5' || r === '0') {
            v = Math.round((n * parseFloat(r) / 100) * 100) / 100;
            b = Math.round((n + v) * 100) / 100;
        }
        $vat.value  = v.toFixed(2);
        $brut.value = b.toFixed(2);
    }
    $netto.addEventListener('input', calc);
    $rate.addEventListener('change', calc);

    var $cur = document.getElementById('fin-currency');
    var $rateFx = document.getElementById('fin-rate');
    function onCur() {
        if ($cur.value === 'PLN') {
            $rateFx.value = '1.000000';
            $rateFx.setAttribute('readonly', 'readonly');
        } else {
            $rateFx.removeAttribute('readonly');
        }
    }
    $cur.addEventListener('change', onCur);
    onCur();

    var $search = document.getElementById('buyer-search');
    var $results = document.getElementById('buyer-results');
    var timer = null;

    $search.addEventListener('input', function(){
        clearTimeout(timer);
        var q = $search.value.trim();
        if (q.length < 2) {
            $results.classList.add('d-none');
            $results.innerHTML = '';
            return;
        }
        timer = setTimeout(function(){
            fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'search']) ?>?q=' + encodeURIComponent(q))
                .then(function(r){ return r.json(); })
                .then(function(items){
                    if (!Array.isArray(items) || items.length === 0) {
                        $results.innerHTML = '<div class="list-group-item text-muted small">Brak wyników</div>';
                        $results.classList.remove('d-none');
                        return;
                    }
                    var html = '';
                    items.slice(0, 10).forEach(function(c){
                        html += '<button type="button" class="list-group-item list-group-item-action py-1" ' +
                            'data-nip="' + (c.nip || '') + '" ' +
                            'data-name="' + encodeURIComponent(c.name || '') + '" ' +
                            'data-street="' + encodeURIComponent(c.street || '') + '" ' +
                            'data-zip="' + encodeURIComponent(c.zip || '') + '" ' +
                            'data-city="' + encodeURIComponent(c.city || '') + '" ' +
                            'data-country="' + (c.country || 'PL') + '" ' +
                            'data-email="' + (c.email || '') + '">' +
                            '<strong>' + (c.name || '') + '</strong> ' +
                            '<span class="text-muted small">' + (c.nip ? 'NIP ' + c.nip : '') + ' - ' + (c.city || '') + '</span>' +
                            '</button>';
                    });
                    $results.innerHTML = html;
                    $results.classList.remove('d-none');
                });
        }, 250);
    });

    $results.addEventListener('click', function(e){
        var btn = e.target.closest('button[data-nip]');
        if (!btn) return;
        document.querySelector('input[name="buyer_nip"]').value  = btn.dataset.nip || '';
        document.querySelector('input[name="buyer_name"]').value = decodeURIComponent(btn.dataset.name || '');
        document.querySelector('input[name="buyer_street"]').value = decodeURIComponent(btn.dataset.street || '');
        document.querySelector('input[name="buyer_postal_code"]').value = decodeURIComponent(btn.dataset.zip || '');
        document.querySelector('input[name="buyer_city"]').value = decodeURIComponent(btn.dataset.city || '');
        var $co = document.querySelector('select[name="buyer_country"]');
        if ($co) $co.value = btn.dataset.country || 'PL';
        document.querySelector('input[name="buyer_email"]').value = btn.dataset.email || '';
        $results.classList.add('d-none');
        $search.value = '';
    });
})();
</script>

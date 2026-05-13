<?php
/**
 * @var \App\View\AppView $this
 * @var array<\App\Model\Entity\Vehicle> $vehicles
 */
$this->assign('title', __('Planer tras'));
$calcUrl = $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'calculate']);
$csrf = (string)$this->request->getAttribute('csrfToken');
?>

<?= $this->Html->css('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', ['block' => true]) ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-route-line me-1"></i><?= __('Planer tras') ?>
        <small class="text-muted ms-2" style="font-size:.7em">
            <i class="ri-truck-line"></i> <?= __('HERE Routing v8 · profil truck') ?>
        </small>
    </h4>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header py-2"><strong><i class="ri-pin-distance-line me-1"></i><?= __('Trasa') ?></strong></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small mb-1"><?= __('Załadunek') ?></label>
                    <input type="text" class="form-control" id="from-addr"
                           placeholder="<?= __('np. Kraków, Polska') ?>" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1"><?= __('Rozładunek') ?></label>
                    <input type="text" class="form-control" id="to-addr"
                           placeholder="<?= __('np. Berlin, Niemcy') ?>" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1"><?= __('Pojazd') ?></label>
                    <select class="form-select" id="vehicle-id">
                        <option value=""><?= __('— bez profilu (osobowy) —') ?></option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?= h($v->id) ?>" <?= $v->is_default ? 'selected' : '' ?>>
                                <?= h($v->name) ?>
                                <?php if ($v->plate): ?> (<?= h($v->plate) ?>)<?php endif; ?>
                                <?php if ($v->gross_weight_kg): ?> — <?= number_format($v->gross_weight_kg / 1000, 1, ',', '') ?>t<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($vehicles)): ?>
                        <div class="form-text">
                            <i class="ri-information-line me-1"></i>
                            <a href="<?= $this->Url->build(['controller' => 'Vehicles', 'action' => 'add']) ?>">
                                <?= __('Dodaj pojazd') ?>
                            </a>
                            — <?= __('aby liczyć trasy z profilem truck') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1"><?= __('Unikaj') ?></label>
                    <div class="d-flex gap-3 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input avoid-opt" type="checkbox" value="tollRoad" id="av-toll">
                            <label class="form-check-label small" for="av-toll"><?= __('Płatne drogi') ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input avoid-opt" type="checkbox" value="ferry" id="av-ferry">
                            <label class="form-check-label small" for="av-ferry"><?= __('Promy') ?></label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1"><?= __('Waluta opłat') ?></label>
                    <select class="form-select" id="currency">
                        <option value="EUR">EUR</option>
                        <option value="PLN">PLN</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary w-100" id="btn-calc">
                    <i class="ri-route-fill me-1"></i><?= __('Wyznacz trasę') ?>
                </button>
            </div>
        </div>

        <div class="card shadow-sm mt-3" id="result-card" style="display:none">
            <div class="card-header py-2"><strong><i class="ri-bar-chart-2-line me-1"></i><?= __('Podsumowanie') ?></strong></div>
            <div class="card-body" id="result-body"></div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div id="map" style="height:680px;border-radius:.5rem"></div>
        </div>
    </div>
</div>

<?= $this->Html->script('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', ['block' => true]) ?>

<script>
(function () {
    var map = L.map('map').setView([52.0, 19.0], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    var routeLayer = null, fromMarker = null, toMarker = null;
    var calcUrl = <?= json_encode($calcUrl) ?>;
    var csrf = <?= json_encode($csrf) ?>;

    function fmtNum(v, dec) { return v.toLocaleString('pl-PL', {minimumFractionDigits: dec || 0, maximumFractionDigits: dec || 0}); }
    function fmtDur(min) {
        if (!min) return '—';
        var h = Math.floor(min / 60), m = min % 60;
        return (h > 0 ? h + ' h ' : '') + m + ' min';
    }

    function renderRoute(data) {
        if (routeLayer) map.removeLayer(routeLayer);
        if (fromMarker) map.removeLayer(fromMarker);
        if (toMarker)   map.removeLayer(toMarker);
        var path = (data.decoded_path || []).map(function (p) { return [p.lat, p.lng]; });
        if (path.length === 0) return;
        routeLayer = L.polyline(path, {color: '#2563eb', weight: 5, opacity: .85}).addTo(map);
        fromMarker = L.marker([data.from.lat, data.from.lng]).addTo(map).bindPopup('<?= __('Załadunek') ?>: ' + data.from.label);
        toMarker   = L.marker([data.to.lat, data.to.lng]).addTo(map).bindPopup('<?= __('Rozładunek') ?>: ' + data.to.label);
        map.fitBounds(routeLayer.getBounds(), {padding: [40, 40]});

        var html = '';
        html += '<div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted small"><i class="ri-pin-distance-line me-1"></i><?= __('Dystans') ?></span><strong>' + fmtNum(data.distance_km, 1) + ' km</strong></div>';
        html += '<div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted small"><i class="ri-time-line me-1"></i><?= __('Czas jazdy') ?></span><strong>' + fmtDur(data.duration_min) + '</strong></div>';
        if (data.tolls_total !== null && data.tolls_total !== undefined) {
            html += '<div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted small"><i class="ri-money-euro-circle-line me-1"></i><?= __('Opłaty drogowe (suma)') ?></span><strong>' + fmtNum(data.tolls_total, 2) + ' ' + (data.tolls_currency || 'EUR') + '</strong></div>';
            if (data.tolls_by_country && Object.keys(data.tolls_by_country).length) {
                html += '<div class="mt-2 small text-muted fw-semibold"><?= __('Per kraj') ?>:</div>';
                Object.keys(data.tolls_by_country).forEach(function (cc) {
                    html += '<div class="d-flex justify-content-between py-1 small"><span>🏳️ ' + cc + '</span><span>' + fmtNum(data.tolls_by_country[cc], 2) + ' ' + (data.tolls_currency || 'EUR') + '</span></div>';
                });
            }
        } else {
            html += '<div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted small"><i class="ri-money-euro-circle-line me-1"></i><?= __('Opłaty drogowe') ?></span><span class="text-muted">—</span></div>';
        }
        document.getElementById('result-body').innerHTML = html;
        document.getElementById('result-card').style.display = 'block';
    }

    document.getElementById('btn-calc').addEventListener('click', function () {
        var btn = this;
        var from = document.getElementById('from-addr').value.trim();
        var to   = document.getElementById('to-addr').value.trim();
        if (!from || !to) { alert('<?= __('Podaj punkt początkowy i końcowy.') ?>'); return; }
        var vehicleId = document.getElementById('vehicle-id').value;
        var avoid = Array.from(document.querySelectorAll('.avoid-opt:checked')).map(function (c) { return c.value; });
        var currency = document.getElementById('currency').value;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= __('Liczę…') ?>';
        var fd = new FormData();
        fd.append('from', from);
        fd.append('to', to);
        fd.append('vehicle_id', vehicleId);
        fd.append('currency', currency);
        avoid.forEach(function (a) { fd.append('avoid[]', a); });
        fd.append('_csrfToken', csrf);

        fetch(calcUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            body: fd
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            if (!res.ok || res.data.error) {
                alert(res.data.message || '<?= __('Błąd kalkulacji trasy.') ?>');
                return;
            }
            renderRoute(res.data);
        })
        .catch(function (e) { alert('<?= __('Błąd sieci') ?>: ' + e.message); })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-route-fill me-1"></i><?= __('Wyznacz trasę') ?>';
        });
    });
})();
</script>

<?php
/**
 * @var \App\View\AppView $this
 * @var array<\App\Model\Entity\Vehicle> $vehicles
 * @var string $hereApiKey
 */
$this->assign('title', __('Planer tras'));
$calcUrl = $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'calculate']);
$autoUrl = $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'autosuggest']);
$csrf = (string)$this->request->getAttribute('csrfToken');
?>

<link rel="stylesheet" type="text/css" href="https://js.api.here.com/v3/3.1/mapsjs-ui.css" />
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-core.js"></script>
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-service.js"></script>
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-ui.js"></script>
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-mapevents.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
    .waypoint-row { transition: background .2s; }
    .waypoint-row.sortable-chosen { background: #eff6ff; }
    .waypoint-row .drag-handle { cursor: grab; color: #9ca3af; }
    .waypoint-row .drag-handle:hover { color: #4f46e5; }
    .waypoint-marker {
        width: 24px; height: 24px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        color: white; font-weight: 700; font-size: .72rem; flex-shrink: 0;
    }
    .marker-origin   { background: #16a34a; }
    .marker-via      { background: #f59e0b; }
    .marker-dest     { background: #dc2626; }
    .autosuggest-dropdown {
        position: absolute; z-index: 100; background: white; border: 1px solid #e5e7eb;
        border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.08); max-height: 280px;
        overflow-y: auto; min-width: 280px;
    }
    .autosuggest-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f3f4f6; font-size: .82rem; }
    .autosuggest-item:hover, .autosuggest-item.active { background: #f3f4f6; }
    .autosuggest-item .label { font-weight: 600; color: #111827; }
    .autosuggest-item .country { font-size: .65rem; color: #6b7280; }
    .alt-route-card {
        cursor: pointer; transition: all .15s;
        border-left: 4px solid transparent;
    }
    .alt-route-card.active { border-left-color: #2563eb; background: #eff6ff; }
    .alt-route-card:hover:not(.active) { background: #f9fafb; }
    .instruction-item { padding: 6px 0; border-bottom: 1px solid #f3f4f6; font-size: .82rem; }
    .instruction-icon { width: 28px; height: 28px; flex-shrink: 0; border-radius: 50%; background: #eff6ff; color: #2563eb; display:inline-flex; align-items:center; justify-content:center; }
    #map-wrap { position: relative; }
    .map-overlay-top {
        position: absolute; top: 12px; left: 12px; right: 12px; z-index: 5;
        display: flex; gap: 8px; flex-wrap: wrap; pointer-events: none;
    }
    .map-overlay-top > * { pointer-events: auto; }
    .map-tip { background: rgba(17,24,39,.85); color: white; padding: 6px 12px;
               border-radius: 16px; font-size: .72rem; box-shadow: 0 2px 6px rgba(0,0,0,.2);
               display: inline-flex; align-items: center; gap: 6px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-route-line me-1"></i><?= __('Zaawansowany planer tras') ?>
        <small class="text-muted ms-2" style="font-size:.7em">
            <i class="ri-truck-line"></i> <?= __('HERE Maps · profil truck · multipoint') ?>
        </small>
    </h4>
</div>

<div class="row g-3">
    <!-- ── LEWA KOLUMNA: PARAMETRY ──────────────────────────────────────── -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header py-2 d-flex align-items-center">
                <strong><i class="ri-map-pin-line me-1"></i><?= __('Punkty trasy') ?></strong>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="btn-reverse"
                        title="<?= __('Odwróć trasę') ?>">
                    <i class="ri-arrow-up-down-line"></i>
                </button>
            </div>
            <div class="card-body">
                <div id="waypoints-list">
                    <!-- waypoints rendered by JS -->
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-2" id="btn-add-waypoint">
                    <i class="ri-add-line me-1"></i><?= __('Dodaj przystanek') ?>
                </button>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header py-2"><strong><i class="ri-settings-3-line me-1"></i><?= __('Opcje') ?></strong></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small mb-1"><?= __('Pojazd') ?></label>
                    <select class="form-select form-select-sm" id="vehicle-id">
                        <option value=""><?= __('— bez profilu (osobowy) —') ?></option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?= h($v->id) ?>" <?= $v->is_default ? 'selected' : '' ?>>
                                <?= h($v->name) ?>
                                <?php if ($v->plate): ?> (<?= h($v->plate) ?>)<?php endif; ?>
                                <?php if ($v->gross_weight_kg): ?> — <?= number_format($v->gross_weight_kg / 1000, 1, ',', '') ?>t<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1"><?= __('Unikaj') ?></label>
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input avoid-opt" type="checkbox" value="tollRoad" id="av-toll">
                            <label class="form-check-label small" for="av-toll"><?= __('Płatne drogi') ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input avoid-opt" type="checkbox" value="ferry" id="av-ferry">
                            <label class="form-check-label small" for="av-ferry"><?= __('Promy') ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input avoid-opt" type="checkbox" value="controlledAccessHighway" id="av-hwy">
                            <label class="form-check-label small" for="av-hwy"><?= __('Autostrady') ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input avoid-opt" type="checkbox" value="dirtRoad" id="av-dirt">
                            <label class="form-check-label small" for="av-dirt"><?= __('Drogi gruntowe') ?></label>
                        </div>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small mb-1"><?= __('Waluta') ?></label>
                        <select class="form-select form-select-sm" id="currency">
                            <option value="EUR">EUR</option>
                            <option value="PLN">PLN</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1"><?= __('Alternatywy') ?></label>
                        <select class="form-select form-select-sm" id="alternatives">
                            <option value="0">0</option>
                            <option value="1">1</option>
                            <option value="2" selected>2</option>
                            <option value="3">3</option>
                        </select>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label small mb-1"><?= __('Data/godzina wyjazdu') ?></label>
                    <input type="datetime-local" class="form-control form-control-sm" id="departure-time">
                    <div class="form-text" style="font-size:.7rem">
                        <?= __('Puste = teraz. Wpływa na ruch + ETA.') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3" id="fuel-card">
            <div class="card-header py-2"><strong><i class="ri-gas-station-line me-1"></i><?= __('Kalkulator paliwa') ?></strong></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small mb-1"><?= __('Zużycie (l/100km)') ?></label>
                        <input type="number" step="0.1" min="0" class="form-control form-control-sm" id="fuel-consumption" value="30">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1"><?= __('Cena za litr (PLN)') ?></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="fuel-price" value="6.50">
                    </div>
                </div>
                <div class="mt-2 text-center" id="fuel-result" style="display:none">
                    <div class="text-muted small"><?= __('Koszt paliwa') ?></div>
                    <div class="fs-4 fw-bold text-warning" id="fuel-total">—</div>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-primary w-100 mt-3" id="btn-calc">
            <i class="ri-route-fill me-1"></i><?= __('Wyznacz trasę') ?>
        </button>
    </div>

    <!-- ── ŚRODKOWA KOLUMNA: MAPA + ALTERNATYWY ─────────────────────────── -->
    <div class="col-lg-5">
        <div class="card shadow-sm" id="map-wrap">
            <div class="map-overlay-top">
                <span class="map-tip"><i class="ri-cursor-line"></i> <?= __('Klik na mapie = dodaj przystanek') ?></span>
            </div>
            <div id="map" style="height:560px;border-radius:.5rem;background:#f4f6fa"></div>
        </div>

        <div class="card shadow-sm mt-3" id="alternatives-card" style="display:none">
            <div class="card-header py-2"><strong><i class="ri-git-branch-line me-1"></i><?= __('Alternatywne trasy') ?></strong></div>
            <div class="card-body p-0" id="alternatives-list"></div>
        </div>
    </div>

    <!-- ── PRAWA KOLUMNA: PODSUMOWANIE + INSTRUKCJE ─────────────────────── -->
    <div class="col-lg-3">
        <div class="card shadow-sm" id="summary-card" style="display:none">
            <div class="card-header py-2"><strong><i class="ri-bar-chart-2-line me-1"></i><?= __('Podsumowanie') ?></strong></div>
            <div class="card-body" id="summary-body"></div>
        </div>

        <div class="card shadow-sm mt-3" id="directions-card" style="display:none">
            <div class="card-header py-2 d-flex align-items-center">
                <strong><i class="ri-navigation-line me-1"></i><?= __('Instrukcje') ?></strong>
                <button type="button" class="btn btn-sm btn-link ms-auto p-0" id="btn-toggle-dirs">
                    <i class="ri-arrow-down-s-line"></i>
                </button>
            </div>
            <div class="card-body" id="directions-body" style="max-height:560px;overflow-y:auto"></div>
        </div>
    </div>
</div>

<!-- Template dla wiersza punktu trasy -->
<template id="waypoint-tpl">
    <div class="waypoint-row d-flex align-items-center gap-2 py-2 border-bottom" style="position:relative">
        <i class="ri-drag-move-2-line drag-handle"></i>
        <span class="waypoint-marker">A</span>
        <div class="flex-grow-1" style="position:relative">
            <input type="text" class="form-control form-control-sm waypoint-input" autocomplete="off"
                   placeholder="<?= __('Wpisz adres lub klik na mapę') ?>">
            <div class="autosuggest-dropdown" style="display:none"></div>
        </div>
        <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-remove-wp"
                title="<?= __('Usuń') ?>"><i class="ri-close-line"></i></button>
    </div>
</template>

<script>
(function () {
    var hereKey = <?= json_encode($hereApiKey) ?>;
    var calcUrl = <?= json_encode($calcUrl) ?>;
    var autoUrl = <?= json_encode($autoUrl) ?>;
    var csrf    = <?= json_encode($csrf) ?>;

    // HERE Maps init
    var platform = new H.service.Platform({ apikey: hereKey });
    var defaultLayers = platform.createDefaultLayers();
    var mapEl = document.getElementById('map');
    var map = new H.Map(mapEl, defaultLayers.vector.normal.map, {
        center: { lat: 52.0, lng: 19.0 },
        zoom: 5,
        pixelRatio: window.devicePixelRatio || 1
    });
    window.addEventListener('resize', function () { map.getViewPort().resize(); });
    var behavior = new H.mapevents.Behavior(new H.mapevents.MapEvents(map));
    var ui = H.ui.UI.createDefault(map, defaultLayers, 'pl-PL');

    var routeGroups = []; // grupy dla każdej alternatywy
    var pinsGroup = new H.map.Group();
    map.addObject(pinsGroup);

    // ── Waypoints state ─────────────────────────────────────────────────
    var waypointsEl = document.getElementById('waypoints-list');
    var waypoints = []; // [{ address: '', lat: null, lng: null }]

    function makeMarkerIcon(letter, color) {
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="52" viewBox="0 0 40 52">'
                + '<path d="M20 0C9 0 0 9 0 20c0 14 20 32 20 32s20-18 20-32C40 9 31 0 20 0z" fill="' + color + '" stroke="white" stroke-width="2"/>'
                + '<circle cx="20" cy="20" r="9" fill="white"/>'
                + '<text x="20" y="24" text-anchor="middle" font-family="sans-serif" font-size="12" font-weight="bold" fill="' + color + '">' + letter + '</text>'
                + '</svg>';
        return new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 20, y: 52 } });
    }

    function colorForIdx(idx, total) {
        if (idx === 0) return '#16a34a';
        if (idx === total - 1) return '#dc2626';
        return '#f59e0b';
    }
    function letterForIdx(idx, total) {
        // A, B, C, ... Z, AA, AB
        if (idx === total - 1) return String.fromCharCode(65 + Math.min(25, total - 1));
        return String.fromCharCode(65 + Math.min(25, idx));
    }

    function renderWaypoints() {
        waypointsEl.innerHTML = '';
        var tpl = document.getElementById('waypoint-tpl');
        waypoints.forEach(function (wp, idx) {
            var node = tpl.content.cloneNode(true);
            var row = node.querySelector('.waypoint-row');
            row.dataset.index = idx;
            var marker = row.querySelector('.waypoint-marker');
            marker.textContent = letterForIdx(idx, waypoints.length);
            marker.className = 'waypoint-marker ' + (idx === 0 ? 'marker-origin' : (idx === waypoints.length - 1 ? 'marker-dest' : 'marker-via'));
            marker.style.background = colorForIdx(idx, waypoints.length);
            var input = row.querySelector('.waypoint-input');
            input.value = wp.address || wp.label || '';
            input.addEventListener('input', function () {
                wp.address = input.value;
                wp.lat = null; wp.lng = null;
                runAutosuggest(input, row.querySelector('.autosuggest-dropdown'), wp);
            });
            input.addEventListener('focus', function () {
                if (input.value.length >= 2) runAutosuggest(input, row.querySelector('.autosuggest-dropdown'), wp);
            });
            input.addEventListener('blur', function () {
                setTimeout(function () { row.querySelector('.autosuggest-dropdown').style.display = 'none'; }, 200);
            });
            row.querySelector('.btn-remove-wp').addEventListener('click', function () {
                if (waypoints.length <= 2) {
                    waypoints[idx] = { address: '', lat: null, lng: null };
                } else {
                    waypoints.splice(idx, 1);
                }
                renderWaypoints();
                renderPinsOnMap();
            });
            waypointsEl.appendChild(node);
        });
        new Sortable(waypointsEl, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function (evt) {
                var moved = waypoints.splice(evt.oldIndex, 1)[0];
                waypoints.splice(evt.newIndex, 0, moved);
                renderWaypoints();
                renderPinsOnMap();
            }
        });
    }

    function renderPinsOnMap() {
        pinsGroup.removeAll();
        waypoints.forEach(function (wp, idx) {
            if (wp.lat == null || wp.lng == null) return;
            var icon = makeMarkerIcon(letterForIdx(idx, waypoints.length), colorForIdx(idx, waypoints.length));
            pinsGroup.addObject(new H.map.Marker({ lat: wp.lat, lng: wp.lng }, { icon: icon }));
        });
    }

    // ── Autosuggest ─────────────────────────────────────────────────────
    var autosuggestTimers = new WeakMap();
    function runAutosuggest(input, dropdown, wp) {
        clearTimeout(autosuggestTimers.get(input));
        var q = input.value.trim();
        if (q.length < 2) { dropdown.style.display = 'none'; return; }
        var t = setTimeout(function () {
            var center = map.getCenter();
            fetch(autoUrl + '?q=' + encodeURIComponent(q) + '&lat=' + center.lat + '&lng=' + center.lng, {
                headers: { 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (data) {
                var items = data.items || [];
                if (!items.length) { dropdown.style.display = 'none'; return; }
                dropdown.innerHTML = items.map(function (it, i) {
                    return '<div class="autosuggest-item" data-idx="' + i + '">'
                        + '<div class="label">' + escapeHtml(it.title) + '</div>'
                        + '<div class="text-muted small">' + escapeHtml(it.label) + '</div>'
                        + (it.country ? '<span class="country">🏳️ ' + it.country + '</span>' : '')
                        + '</div>';
                }).join('');
                dropdown.style.display = 'block';
                dropdown.querySelectorAll('.autosuggest-item').forEach(function (el, i) {
                    el.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        var it = items[i];
                        wp.address = it.label;
                        wp.label   = it.label;
                        wp.lat     = it.lat;
                        wp.lng     = it.lng;
                        input.value = it.label;
                        dropdown.style.display = 'none';
                        renderPinsOnMap();
                        if (wp.lat && wp.lng) {
                            map.getViewModel().setLookAtData({ position: { lat: wp.lat, lng: wp.lng }, zoom: 10 }, true);
                        }
                    });
                });
            }).catch(function () { dropdown.style.display = 'none'; });
        }, 280);
        autosuggestTimers.set(input, t);
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Map click → dodaj waypoint ──────────────────────────────────────
    map.addEventListener('tap', function (evt) {
        if (evt.target !== map) return; // klik na marker = nie dodawaj
        var coord = map.screenToGeo(evt.currentPointer.viewportX, evt.currentPointer.viewportY);
        if (!coord) return;
        // Wstaw przed ostatnim punktem (czyli jako via przed dest), albo na koniec jeśli destynacja pusta
        var lastIdx = waypoints.length - 1;
        if (waypoints[lastIdx].lat == null && waypoints[lastIdx].lng == null) {
            waypoints[lastIdx] = { address: '(' + coord.lat.toFixed(5) + ', ' + coord.lng.toFixed(5) + ')', lat: coord.lat, lng: coord.lng };
        } else if (waypoints[0].lat == null && waypoints[0].lng == null) {
            waypoints[0] = { address: '(' + coord.lat.toFixed(5) + ', ' + coord.lng.toFixed(5) + ')', lat: coord.lat, lng: coord.lng };
        } else {
            waypoints.splice(lastIdx, 0, { address: '(' + coord.lat.toFixed(5) + ', ' + coord.lng.toFixed(5) + ')', lat: coord.lat, lng: coord.lng });
        }
        renderWaypoints();
        renderPinsOnMap();
    });

    // ── Akcje przycisków ────────────────────────────────────────────────
    document.getElementById('btn-add-waypoint').addEventListener('click', function () {
        // wstaw przed ostatnim (= przed destynacją)
        var lastIdx = waypoints.length - 1;
        waypoints.splice(lastIdx, 0, { address: '', lat: null, lng: null });
        renderWaypoints();
    });
    document.getElementById('btn-reverse').addEventListener('click', function () {
        waypoints.reverse();
        renderWaypoints();
        renderPinsOnMap();
    });

    // ── Kalkulacja trasy ────────────────────────────────────────────────
    function clearRoutes() {
        routeGroups.forEach(function (g) { map.removeObject(g); });
        routeGroups = [];
    }
    function drawRoute(routeData, opts) {
        opts = opts || {};
        var group = new H.map.Group();
        (routeData.polylines || []).forEach(function (polyStr) {
            try {
                var line = H.geo.LineString.fromFlexiblePolyline(polyStr);
                var outline = new H.map.Polyline(line, {
                    style: { strokeColor: opts.outline || 'rgba(255,255,255,.95)', lineWidth: opts.lineWidth || 10 }
                });
                var stroke = new H.map.Polyline(line, {
                    style: { strokeColor: opts.color || 'rgba(37,99,235,.85)', lineWidth: (opts.lineWidth || 10) - 4 }
                });
                group.addObject(outline);
                group.addObject(stroke);
            } catch (e) { console.error('Polyline decode failed', e); }
        });
        map.addObject(group);
        routeGroups.push(group);
        return group;
    }

    var lastResponse = null;
    var activeAltIdx = 0;

    function renderResult(data) {
        lastResponse = data;
        activeAltIdx = 0;
        clearRoutes();
        if (!data.routes || !data.routes.length) return;

        // Najpierw alternatywy (przygaszone)
        data.routes.forEach(function (r, idx) {
            if (idx === 0) return;
            drawRoute(r, { color: 'rgba(148,163,184,.7)', outline: 'rgba(255,255,255,.7)', lineWidth: 8 });
        });
        // Główna trasa na wierzchu
        drawRoute(data.routes[0], { color: 'rgba(37,99,235,.95)', lineWidth: 11 });

        // Bounding box wszystkich grup + pins
        var allGroup = new H.map.Group();
        routeGroups.forEach(function (g) { g.getObjects().forEach(function (o) { allGroup.addObject(o); }); });
        pinsGroup.getObjects().forEach(function (o) { allGroup.addObject(o); });
        var bbox = allGroup.getBoundingBox();
        if (bbox) {
            map.getViewModel().setLookAtData({ bounds: bbox }, true);
            setTimeout(function () { map.setZoom(Math.max(map.getZoom() - 0.4, 4)); }, 100);
        }

        renderSummary(data.routes[0]);
        renderAlternatives(data.routes);
        renderDirections(data.routes[0]);
        renderFuel(data.routes[0]);
    }

    function fmtNum(v, dec) { return Number(v || 0).toLocaleString('pl-PL', {minimumFractionDigits: dec || 0, maximumFractionDigits: dec || 0}); }
    function fmtDur(min) {
        if (!min) return '—';
        var h = Math.floor(min / 60), m = min % 60;
        return (h > 0 ? h + ' h ' : '') + m + ' min';
    }
    function fmtMeters(m) {
        if (m < 1000) return m + ' m';
        return (m/1000).toFixed(1) + ' km';
    }

    function renderSummary(r) {
        var html = '';
        html += '<div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted small"><i class="ri-pin-distance-line me-1"></i><?= __('Dystans') ?></span><strong>' + fmtNum(r.distance_km, 1) + ' km</strong></div>';
        html += '<div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted small"><i class="ri-time-line me-1"></i><?= __('Czas jazdy') ?></span><strong>' + fmtDur(r.duration_min) + '</strong></div>';
        if (r.tolls_total !== null && r.tolls_total !== undefined) {
            html += '<div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted small"><i class="ri-money-euro-circle-line me-1"></i><?= __('Opłaty drogowe') ?></span><strong>' + fmtNum(r.tolls_total, 2) + ' ' + (r.tolls_currency || 'EUR') + '</strong></div>';
            if (r.tolls_by_country && Object.keys(r.tolls_by_country).length) {
                Object.keys(r.tolls_by_country).forEach(function (cc) {
                    html += '<div class="d-flex justify-content-between py-1 small"><span class="text-muted ms-3">🏳️ ' + cc + '</span><span>' + fmtNum(r.tolls_by_country[cc], 2) + '</span></div>';
                });
            }
        }
        document.getElementById('summary-body').innerHTML = html;
        document.getElementById('summary-card').style.display = 'block';
    }

    function renderAlternatives(routes) {
        var ac = document.getElementById('alternatives-card');
        var list = document.getElementById('alternatives-list');
        if (!routes || routes.length <= 1) { ac.style.display = 'none'; return; }
        list.innerHTML = '';
        routes.forEach(function (r, idx) {
            var name = idx === 0 ? '<?= __('Trasa główna') ?>' : '<?= __('Alternatywa') ?> ' + idx;
            var tolls = r.tolls_total !== null ? fmtNum(r.tolls_total, 2) + ' ' + (r.tolls_currency || 'EUR') : '—';
            var html = '<div class="alt-route-card p-3 border-bottom ' + (idx === 0 ? 'active' : '') + '" data-idx="' + idx + '">'
                + '<div class="d-flex justify-content-between align-items-center mb-1"><strong class="small">' + name + '</strong>'
                + '<span class="badge bg-light text-dark border">' + fmtNum(r.distance_km, 1) + ' km</span></div>'
                + '<div class="d-flex justify-content-between small text-muted"><span><i class="ri-time-line"></i> ' + fmtDur(r.duration_min) + '</span>'
                + '<span><i class="ri-money-euro-circle-line"></i> ' + tolls + '</span></div></div>';
            list.insertAdjacentHTML('beforeend', html);
        });
        list.querySelectorAll('.alt-route-card').forEach(function (el) {
            el.addEventListener('click', function () {
                var idx = parseInt(el.dataset.idx, 10);
                activeAltIdx = idx;
                list.querySelectorAll('.alt-route-card').forEach(function (e) { e.classList.remove('active'); });
                el.classList.add('active');
                // Re-render wszystkich tras z highlight wybranej
                clearRoutes();
                lastResponse.routes.forEach(function (r, i) {
                    if (i === activeAltIdx) return;
                    drawRoute(r, { color: 'rgba(148,163,184,.7)', outline: 'rgba(255,255,255,.7)', lineWidth: 8 });
                });
                drawRoute(lastResponse.routes[activeAltIdx], { color: 'rgba(37,99,235,.95)', lineWidth: 11 });
                renderSummary(lastResponse.routes[activeAltIdx]);
                renderDirections(lastResponse.routes[activeAltIdx]);
                renderFuel(lastResponse.routes[activeAltIdx]);
            });
        });
        ac.style.display = 'block';
    }

    function renderDirections(r) {
        var dc = document.getElementById('directions-card');
        var body = document.getElementById('directions-body');
        if (!r.instructions || !r.instructions.length) {
            dc.style.display = 'none';
            return;
        }
        body.innerHTML = r.instructions.map(function (ins, i) {
            var icon = i === 0 ? 'ri-flag-fill text-success'
                : (i === r.instructions.length - 1 ? 'ri-flag-fill text-danger' : 'ri-arrow-right-up-line');
            return '<div class="d-flex gap-2 instruction-item">'
                + '<div class="instruction-icon"><i class="' + icon + '"></i></div>'
                + '<div class="flex-grow-1">'
                + '<div>' + escapeHtml(ins.text) + '</div>'
                + (ins.distance_m > 0 ? '<div class="text-muted" style="font-size:.7rem">' + fmtMeters(ins.distance_m) + '</div>' : '')
                + '</div></div>';
        }).join('');
        dc.style.display = 'block';
    }

    function renderFuel(r) {
        var cons = parseFloat(document.getElementById('fuel-consumption').value || 0);
        var price = parseFloat(document.getElementById('fuel-price').value || 0);
        if (cons <= 0 || price <= 0 || !r.distance_km) {
            document.getElementById('fuel-result').style.display = 'none';
            return;
        }
        var liters = (r.distance_km / 100) * cons;
        var cost = liters * price;
        document.getElementById('fuel-total').textContent =
            fmtNum(cost, 2) + ' PLN  /  ' + fmtNum(liters, 1) + ' l';
        document.getElementById('fuel-result').style.display = 'block';
    }
    document.getElementById('fuel-consumption').addEventListener('input', function () {
        if (lastResponse && lastResponse.routes[activeAltIdx]) renderFuel(lastResponse.routes[activeAltIdx]);
    });
    document.getElementById('fuel-price').addEventListener('input', function () {
        if (lastResponse && lastResponse.routes[activeAltIdx]) renderFuel(lastResponse.routes[activeAltIdx]);
    });

    // Toggle directions
    document.getElementById('btn-toggle-dirs').addEventListener('click', function (e) {
        var body = document.getElementById('directions-body');
        var ic = e.currentTarget.querySelector('i');
        if (body.style.display === 'none') {
            body.style.display = 'block';
            ic.className = 'ri-arrow-down-s-line';
        } else {
            body.style.display = 'none';
            ic.className = 'ri-arrow-right-s-line';
        }
    });

    document.getElementById('btn-calc').addEventListener('click', function () {
        var pts = waypoints.filter(function (wp) { return wp.address || (wp.lat != null && wp.lng != null); });
        if (pts.length < 2) { alert('<?= __('Podaj co najmniej dwa punkty.') ?>'); return; }

        var btn = this;
        var vehicleId = document.getElementById('vehicle-id').value;
        var avoid = Array.from(document.querySelectorAll('.avoid-opt:checked')).map(function (c) { return c.value; });
        var currency = document.getElementById('currency').value;
        var alternatives = parseInt(document.getElementById('alternatives').value, 10);
        var dep = document.getElementById('departure-time').value;
        var departureTime = dep ? new Date(dep).toISOString() : '';

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= __('Liczę…') ?>';

        var fd = new FormData();
        pts.forEach(function (wp, idx) {
            fd.append('points[' + idx + '][address]', wp.address || '');
            if (wp.lat != null) fd.append('points[' + idx + '][lat]', String(wp.lat));
            if (wp.lng != null) fd.append('points[' + idx + '][lng]', String(wp.lng));
            if (wp.label) fd.append('points[' + idx + '][label]', wp.label);
        });
        fd.append('vehicle_id', vehicleId);
        fd.append('currency', currency);
        fd.append('alternatives', String(alternatives));
        fd.append('instructions', '1');
        if (departureTime) fd.append('departure_time', departureTime);
        avoid.forEach(function (a) { fd.append('avoid[]', a); });
        fd.append('_csrfToken', csrf);

        fetch(calcUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            body: fd
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            if (!res.ok || res.data.error) {
                alert(res.data.message || '<?= __('Błąd kalkulacji trasy.') ?>');
                return;
            }
            // Zapisz aktualne wszystkie waypoints z lat/lng z odpowiedzi
            if (res.data.points && res.data.points.length === pts.length) {
                res.data.points.forEach(function (p, i) {
                    if (waypoints[i]) {
                        waypoints[i].lat = p.lat;
                        waypoints[i].lng = p.lng;
                        if (!waypoints[i].address && p.label) waypoints[i].address = p.label;
                    }
                });
                renderWaypoints();
                renderPinsOnMap();
            }
            renderResult(res.data);
        })
        .catch(function (e) { alert('<?= __('Błąd sieci') ?>: ' + e.message); })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-route-fill me-1"></i><?= __('Wyznacz trasę') ?>';
        });
    });

    // ── Init: 2 puste waypoints ─────────────────────────────────────────
    waypoints = [
        { address: '', lat: null, lng: null },
        { address: '', lat: null, lng: null },
    ];
    renderWaypoints();
})();
</script>

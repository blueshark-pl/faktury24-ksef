<?php
/**
 * @var \App\View\AppView $this
 * @var array<\App\Model\Entity\Vehicle> $vehicles
 * @var array $recentSearches
 * @var array $templates
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
    /* ── Hero header ──────────────────────────────────────────────────── */
    .rp-hero {
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 35%, #3b82f6 100%);
        color: white;
        border-radius: 16px;
        padding: 20px 24px;
        box-shadow: 0 10px 30px rgba(37, 99, 235, .25);
        position: relative;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .rp-hero::before {
        content: ''; position: absolute; top: -50%; right: -10%;
        width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,.08), transparent 70%);
        pointer-events: none;
    }
    .rp-hero h2 { font-weight: 700; margin: 0; font-size: 1.5rem; }
    .rp-hero .subtitle { opacity: .85; font-size: .85rem; margin-top: 4px; }
    .rp-hero .btn-hero {
        background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.3);
        color: white; backdrop-filter: blur(8px); transition: all .15s;
    }
    .rp-hero .btn-hero:hover { background: rgba(255,255,255,.28); transform: translateY(-1px); }
    .rp-hero .btn-hero:disabled { opacity: .5; }

    /* ── Hero stats pill bar ─────────────────────────────────────────── */
    .stats-pill-bar {
        display: flex; gap: 12px; flex-wrap: wrap;
        margin-top: 16px; opacity: 0; transform: translateY(-8px);
        transition: opacity .4s, transform .4s;
    }
    .stats-pill-bar.visible { opacity: 1; transform: translateY(0); }
    .stats-pill {
        background: rgba(255,255,255,.16); backdrop-filter: blur(12px);
        border: 1px solid rgba(255,255,255,.25); border-radius: 12px;
        padding: 10px 16px; flex: 1; min-width: 130px;
        transition: transform .15s;
    }
    .stats-pill:hover { transform: translateY(-2px); }
    .stats-pill .label { font-size: .7rem; opacity: .85; text-transform: uppercase; letter-spacing: .5px; }
    .stats-pill .value { font-size: 1.45rem; font-weight: 700; margin-top: 2px; }
    .stats-pill .unit { font-size: .8rem; opacity: .8; margin-left: 4px; }
    .stats-pill.warning { background: linear-gradient(135deg, rgba(251,191,36,.25), rgba(245,158,11,.18)); border-color: rgba(252,211,77,.4); }
    .stats-pill.success { background: linear-gradient(135deg, rgba(34,197,94,.25), rgba(22,163,74,.18)); border-color: rgba(74,222,128,.4); }
    .stats-pill.fuel    { background: linear-gradient(135deg, rgba(244,114,182,.22), rgba(217,70,239,.18)); border-color: rgba(244,114,182,.4); }
    .stats-pill.driver  { background: linear-gradient(135deg, rgba(192,132,252,.22), rgba(168,85,247,.18)); border-color: rgba(192,132,252,.4); }

    /* ── Glassmorphism cards ─────────────────────────────────────────── */
    .glass-card {
        background: rgba(255,255,255,.78); backdrop-filter: blur(16px);
        border: 1px solid rgba(229,231,235,.7); border-radius: 14px;
        box-shadow: 0 4px 12px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04);
    }
    .glass-card .card-header {
        background: linear-gradient(180deg, rgba(255,255,255,.6), rgba(248,250,252,.4));
        backdrop-filter: blur(8px); border-bottom: 1px solid rgba(229,231,235,.5);
        border-radius: 14px 14px 0 0;
    }

    /* ── Waypoints ──────────────────────────────────────────────────── */
    .waypoint-row {
        transition: background .2s; padding: 8px 4px;
        border-radius: 8px;
    }
    .waypoint-row + .waypoint-row { border-top: 1px dashed #e5e7eb; }
    .waypoint-row.sortable-chosen { background: #eff6ff; }
    .waypoint-row .drag-handle { cursor: grab; color: #9ca3af; transition: color .15s; }
    .waypoint-row .drag-handle:hover { color: #4f46e5; }
    .waypoint-marker {
        width: 28px; height: 28px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        color: white; font-weight: 700; font-size: .72rem; flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(0,0,0,.15);
    }
    .marker-origin { background: linear-gradient(135deg, #16a34a, #15803d); }
    .marker-via    { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .marker-dest   { background: linear-gradient(135deg, #dc2626, #b91c1c); }
    .waypoint-flag { font-size: 1.1rem; line-height: 1; margin-left: 4px; }
    .waypoint-input { border-radius: 8px; transition: border-color .15s, box-shadow .15s; }
    .waypoint-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }

    /* ── Autosuggest ────────────────────────────────────────────────── */
    .autosuggest-dropdown {
        position: absolute; z-index: 100; background: white; border: 1px solid #e5e7eb;
        border-radius: 10px; box-shadow: 0 12px 28px rgba(0,0,0,.12); max-height: 300px;
        overflow-y: auto; min-width: 280px; margin-top: 4px;
    }
    .autosuggest-item { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f3f4f6; font-size: .82rem; transition: background .12s; }
    .autosuggest-item:last-child { border-bottom: none; }
    .autosuggest-item:hover, .autosuggest-item.active { background: #f3f4f6; }
    .autosuggest-item .label { font-weight: 600; color: #111827; }
    .autosuggest-item .country { font-size: .72rem; color: #6b7280; margin-left: 6px; }
    .autosuggest-item .ri { color: #6366f1; margin-right: 8px; }

    /* ── Alt routes ─────────────────────────────────────────────────── */
    .alt-route-card {
        cursor: pointer; transition: all .15s;
        border-left: 4px solid transparent; border-radius: 8px;
    }
    .alt-route-card.active { border-left-color: #2563eb; background: #eff6ff; }
    .alt-route-card:hover:not(.active) { background: #f9fafb; transform: translateX(2px); }

    /* ── Instructions ───────────────────────────────────────────────── */
    .instruction-item { padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: .82rem; }
    .instruction-icon { width: 30px; height: 30px; flex-shrink: 0; border-radius: 50%; background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; display:inline-flex; align-items:center; justify-content:center; }

    /* ── Map wrapper + overlay controls ─────────────────────────────── */
    #map-wrap { position: relative; border-radius: 14px; overflow: hidden; }
    .map-overlay-top {
        position: absolute; top: 12px; left: 12px; right: 12px; z-index: 5;
        display: flex; gap: 8px; flex-wrap: wrap; justify-content: space-between; pointer-events: none;
    }
    .map-overlay-top > * { pointer-events: auto; }
    .map-tip {
        background: rgba(17,24,39,.85); color: white; padding: 6px 12px;
        border-radius: 16px; font-size: .72rem; box-shadow: 0 2px 6px rgba(0,0,0,.2);
        display: inline-flex; align-items: center; gap: 6px;
    }
    .map-controls-group {
        background: rgba(255,255,255,.95); backdrop-filter: blur(10px);
        border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,.12);
        display: inline-flex; padding: 4px; gap: 2px;
    }
    .map-ctrl-btn {
        border: none; background: transparent; color: #4b5563; padding: 6px 10px;
        border-radius: 7px; cursor: pointer; font-size: .78rem; font-weight: 500;
        transition: all .15s; display: inline-flex; align-items: center; gap: 4px;
    }
    .map-ctrl-btn:hover { background: #f3f4f6; color: #111827; }
    .map-ctrl-btn.active { background: #2563eb; color: white; box-shadow: 0 2px 6px rgba(37,99,235,.3); }

    /* ── Loading skeleton ───────────────────────────────────────────── */
    .map-loading-overlay {
        position: absolute; inset: 0; z-index: 10;
        background: rgba(243,244,246,.6); backdrop-filter: blur(2px);
        display: none; align-items: center; justify-content: center;
        border-radius: 14px;
    }
    .map-loading-overlay.active { display: flex; }
    .shimmer-box {
        background: white; border-radius: 14px; padding: 24px 32px;
        box-shadow: 0 8px 24px rgba(0,0,0,.12); text-align: center;
        position: relative; overflow: hidden;
    }
    .shimmer-box::before {
        content: ''; position: absolute; inset: 0;
        background: linear-gradient(90deg, transparent, rgba(99,102,241,.1), transparent);
        animation: shimmer 1.4s infinite;
    }
    @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
    .shimmer-icon { font-size: 2.2rem; color: #6366f1; }

    /* ── Toast ──────────────────────────────────────────────────────── */
    .toast-container-rp {
        position: fixed; bottom: 24px; right: 24px; z-index: 2000;
        display: flex; flex-direction: column; gap: 8px; pointer-events: none;
    }
    .toast-rp {
        background: white; color: #111827; padding: 12px 18px; border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,.18); min-width: 280px; max-width: 380px;
        display: flex; align-items: center; gap: 10px; pointer-events: auto;
        opacity: 0; transform: translateX(20px);
        animation: toastIn .25s forwards;
        border-left: 4px solid #6366f1;
    }
    .toast-rp.success { border-left-color: #16a34a; }
    .toast-rp.error   { border-left-color: #dc2626; }
    .toast-rp.warning { border-left-color: #f59e0b; }
    .toast-rp .toast-icon { font-size: 1.3rem; flex-shrink: 0; }
    .toast-rp.success .toast-icon { color: #16a34a; }
    .toast-rp.error   .toast-icon { color: #dc2626; }
    .toast-rp.warning .toast-icon { color: #f59e0b; }
    .toast-rp.info    .toast-icon { color: #6366f1; }
    @keyframes toastIn { to { opacity: 1; transform: translateX(0); } }
    .toast-rp.dismissing { animation: toastOut .25s forwards; }
    @keyframes toastOut { to { opacity: 0; transform: translateX(20px); } }

    /* ── Route polyline animacja (na poziomie SVG przez HERE markup) ── */
    /* HERE Polyline jest canvas-based; animacja przez progresywne dorysowywanie */

    /* ── Recent/templates items ─────────────────────────────────────── */
    .recent-item, .tpl-item {
        cursor: pointer; transition: background .12s, transform .12s, border-color .12s;
        border-left: 3px solid transparent;
    }
    .recent-item:hover, .tpl-item:hover {
        background: #f9fafb; border-left-color: #6366f1; transform: translateX(2px);
    }
    .tpl-item { background: linear-gradient(90deg, rgba(99,102,241,.05), transparent); }
    .tpl-name { color: #4f46e5; font-weight: 600; }

    /* ── Print styles ───────────────────────────────────────────────── */
    @media print {
        .no-print, .app-sidebar, .app-header, #btn-add-waypoint,
        #recent-card, #templates-card, .rp-hero .btn-hero,
        #directions-card, .map-overlay-top, footer { display: none !important; }
        .rp-hero { color: black; background: white; box-shadow: none; border: 1px solid #ccc; }
        .stats-pill { background: #f5f5f5 !important; color: black !important; border: 1px solid #ccc !important; }
        #map, #map-wrap { height: 400px !important; page-break-inside: avoid; }
        .glass-card { box-shadow: none; border: 1px solid #ccc; }
    }
</style>

<div class="toast-container-rp" id="toast-container"></div>

<!-- ═══════════════════════════════ HERO ═══════════════════════════════ -->
<div class="rp-hero">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h2>
                <i class="ri-route-line me-2"></i><?= __('Planer tras') ?>
            </h2>
            <div class="subtitle">
                <i class="ri-truck-line"></i> JJ Maps · <?= __('profil truck · multipoint · opłaty drogowe EU') ?>
            </div>
        </div>
        <div class="d-flex gap-2 no-print">
            <button type="button" class="btn btn-sm btn-hero" id="btn-share" disabled
                    title="<?= __('Kopiuj link do schowka') ?>">
                <i class="ri-share-line me-1"></i><?= __('Udostępnij') ?>
            </button>
            <button type="button" class="btn btn-sm btn-hero" id="btn-print" disabled
                    title="<?= __('Drukuj / zapisz PDF') ?>">
                <i class="ri-printer-line me-1"></i><?= __('Drukuj') ?>
            </button>
            <button type="button" class="btn btn-sm btn-hero" id="btn-save-template" disabled
                    title="<?= __('Zapisz jako szablon') ?>">
                <i class="ri-bookmark-line me-1"></i><?= __('Zapisz szablon') ?>
            </button>
        </div>
    </div>
    <div class="stats-pill-bar" id="stats-bar">
        <div class="stats-pill">
            <div class="label"><i class="ri-pin-distance-line me-1"></i><?= __('Dystans') ?></div>
            <div class="value"><span id="stat-km">—</span><span class="unit">km</span></div>
        </div>
        <div class="stats-pill">
            <div class="label"><i class="ri-time-line me-1"></i><?= __('Czas jazdy') ?></div>
            <div class="value" id="stat-dur">—</div>
        </div>
        <div class="stats-pill warning">
            <div class="label"><i class="ri-money-euro-circle-line me-1"></i><?= __('Opłaty') ?></div>
            <div class="value"><span id="stat-tolls">—</span><span class="unit" id="stat-tolls-cur">EUR</span></div>
        </div>
        <div class="stats-pill fuel">
            <div class="label"><i class="ri-gas-station-line me-1"></i><?= __('Paliwo') ?></div>
            <div class="value"><span id="stat-fuel">—</span><span class="unit">PLN</span></div>
        </div>
        <div class="stats-pill driver">
            <div class="label"><i class="ri-user-line me-1"></i><?= __('Kierowca') ?></div>
            <div class="value"><span id="stat-driver">—</span><span class="unit">PLN</span></div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════ MAIN ═══════════════════════════════ -->
<div class="row g-3">
    <!-- ── LEWA: parametry ──────────────────────────────────────────── -->
    <div class="col-lg-4 no-print">

        <!-- Szablony (jeśli są) -->
        <div class="card glass-card mb-3" id="templates-card" style="<?= empty($templates) ? 'display:none' : '' ?>">
            <div class="card-header py-2 d-flex align-items-center">
                <strong><i class="ri-bookmark-fill me-1 text-primary"></i><?= __('Szablony') ?></strong>
                <span class="badge bg-primary-subtle text-primary border ms-2" id="templates-count" style="font-size:.65em"><?= count($templates ?? []) ?></span>
            </div>
            <div class="card-body p-0">
                <div style="max-height:200px;overflow-y:auto" id="templates-list"></div>
            </div>
        </div>

        <!-- Historia -->
        <div class="card glass-card mb-3" id="recent-card" style="<?= empty($recentSearches) ? 'display:none' : '' ?>">
            <div class="card-header py-2 d-flex align-items-center">
                <strong><i class="ri-history-line me-1"></i><?= __('Ostatnie trasy') ?></strong>
                <span class="badge bg-secondary-subtle text-secondary border ms-2" id="recent-count" style="font-size:.65em"><?= count($recentSearches ?? []) ?></span>
                <button type="button" class="btn btn-sm btn-link p-0 ms-auto text-muted" id="btn-toggle-recent">
                    <i class="ri-arrow-up-s-line"></i>
                </button>
            </div>
            <div class="card-body p-0" id="recent-body">
                <div style="max-height:200px;overflow-y:auto" id="recent-list"></div>
            </div>
        </div>

        <!-- Waypoints -->
        <div class="card glass-card">
            <div class="card-header py-2 d-flex align-items-center">
                <strong><i class="ri-map-pin-line me-1 text-primary"></i><?= __('Punkty trasy') ?></strong>
                <div class="ms-auto d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-optimize"
                            title="<?= __('Optymalizuj kolejność (najkrótsza trasa)') ?>">
                        <i class="ri-magic-line"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-reverse"
                            title="<?= __('Odwróć trasę') ?>">
                        <i class="ri-arrow-up-down-line"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="waypoints-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-2" id="btn-add-waypoint">
                    <i class="ri-add-line me-1"></i><?= __('Dodaj przystanek') ?>
                </button>
            </div>
        </div>

        <!-- Opcje -->
        <div class="card glass-card mt-3">
            <div class="card-header py-2"><strong><i class="ri-settings-3-line me-1 text-primary"></i><?= __('Opcje') ?></strong></div>
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
                        <div class="form-check"><input class="form-check-input avoid-opt" type="checkbox" value="tollRoad" id="av-toll"><label class="form-check-label small" for="av-toll"><?= __('Płatne') ?></label></div>
                        <div class="form-check"><input class="form-check-input avoid-opt" type="checkbox" value="ferry" id="av-ferry"><label class="form-check-label small" for="av-ferry"><?= __('Promy') ?></label></div>
                        <div class="form-check"><input class="form-check-input avoid-opt" type="checkbox" value="controlledAccessHighway" id="av-hwy"><label class="form-check-label small" for="av-hwy"><?= __('Autostrady') ?></label></div>
                        <div class="form-check"><input class="form-check-input avoid-opt" type="checkbox" value="dirtRoad" id="av-dirt"><label class="form-check-label small" for="av-dirt"><?= __('Gruntowe') ?></label></div>
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small mb-1"><?= __('Waluta') ?></label>
                        <select class="form-select form-select-sm" id="currency"><option value="EUR">EUR</option><option value="PLN">PLN</option></select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1"><?= __('Alternatywy') ?></label>
                        <select class="form-select form-select-sm" id="alternatives"><option value="0">0</option><option value="1">1</option><option value="2" selected>2</option><option value="3">3</option></select>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label small mb-1"><?= __('Wyjazd') ?></label>
                    <input type="datetime-local" class="form-control form-control-sm" id="departure-time">
                </div>
            </div>
        </div>

        <!-- Koszty -->
        <div class="card glass-card mt-3">
            <div class="card-header py-2"><strong><i class="ri-money-pound-circle-line me-1 text-warning"></i><?= __('Koszty operacyjne') ?></strong></div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small mb-1"><?= __('Spalanie (l/100km)') ?></label>
                        <input type="number" step="0.1" min="0" class="form-control form-control-sm" id="fuel-consumption" value="30">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1"><?= __('Cena paliwa (PLN/l)') ?></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="fuel-price" value="6.50">
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1"><?= __('Stawka kierowcy (PLN/h)') ?></label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="driver-rate" value="50">
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-primary w-100 mt-3" id="btn-calc" style="padding:.6rem;font-weight:600;box-shadow:0 4px 12px rgba(37,99,235,.25)">
            <i class="ri-route-fill me-1"></i><?= __('Wyznacz trasę') ?>
        </button>
    </div>

    <!-- ── ŚRODEK: mapa + alternatywy + instrukcje ──────────────────── -->
    <div class="col-lg-8">
        <div class="card glass-card" id="map-wrap">
            <div class="map-overlay-top">
                <span class="map-tip"><i class="ri-cursor-line"></i> <?= __('Klik na mapie = dodaj przystanek') ?></span>
                <div class="d-flex gap-2 flex-wrap">
                    <div class="map-controls-group">
                        <button type="button" class="map-ctrl-btn active" data-style="vector.normal.map" title="<?= __('Jasna') ?>"><i class="ri-sun-line"></i></button>
                        <button type="button" class="map-ctrl-btn" data-style="vector.normal.map.night" title="<?= __('Ciemna') ?>"><i class="ri-moon-line"></i></button>
                        <button type="button" class="map-ctrl-btn" data-style="raster.satellite.map" title="<?= __('Satelita') ?>"><i class="ri-earth-line"></i></button>
                        <button type="button" class="map-ctrl-btn" data-style="vector.normal.truck" title="<?= __('Truck (restrykcje)') ?>"><i class="ri-truck-line"></i></button>
                    </div>
                    <div class="map-controls-group">
                        <button type="button" class="map-ctrl-btn" id="btn-traffic" title="<?= __('Ruch drogowy') ?>"><i class="ri-traffic-light-line"></i> <?= __('Ruch') ?></button>
                    </div>
                </div>
            </div>
            <div class="map-loading-overlay" id="map-loading">
                <div class="shimmer-box">
                    <div class="shimmer-icon"><i class="ri-route-line"></i></div>
                    <div class="mt-2 fw-semibold"><?= __('Liczę trasę…') ?></div>
                    <div class="text-muted small"><?= __('JJ Maps · liczę optymalną trasę') ?></div>
                </div>
            </div>
            <div id="map" style="height:520px;background:#f4f6fa"></div>
        </div>

        <div class="row g-3 mt-0">
            <div class="col-lg-6">
                <div class="card glass-card" id="alternatives-card" style="display:none">
                    <div class="card-header py-2"><strong><i class="ri-git-branch-line me-1 text-primary"></i><?= __('Alternatywne trasy') ?></strong></div>
                    <div class="card-body p-0" id="alternatives-list"></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card glass-card" id="directions-card" style="display:none">
                    <div class="card-header py-2 d-flex align-items-center">
                        <strong><i class="ri-navigation-line me-1 text-primary"></i><?= __('Instrukcje') ?></strong>
                        <button type="button" class="btn btn-sm btn-link ms-auto p-0" id="btn-toggle-dirs"><i class="ri-arrow-down-s-line"></i></button>
                    </div>
                    <div class="card-body" id="directions-body" style="max-height:340px;overflow-y:auto"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template dla waypoint -->
<template id="waypoint-tpl">
    <div class="waypoint-row d-flex align-items-center gap-2" style="position:relative">
        <i class="ri-drag-move-2-line drag-handle"></i>
        <span class="waypoint-marker">A</span>
        <div class="flex-grow-1" style="position:relative">
            <input type="text" class="form-control form-control-sm waypoint-input" autocomplete="off"
                   placeholder="<?= __('Wpisz adres lub klik na mapę') ?>">
            <div class="autosuggest-dropdown" style="display:none"></div>
        </div>
        <span class="waypoint-flag"></span>
        <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-remove-wp"
                title="<?= __('Usuń') ?>"><i class="ri-close-line"></i></button>
    </div>
</template>

<script>
(function () {
    // ─── Konfiguracja ────────────────────────────────────────────────
    var hereKey = <?= json_encode($hereApiKey) ?>;
    var calcUrl = <?= json_encode($calcUrl) ?>;
    var autoUrl = <?= json_encode($autoUrl) ?>;
    var csrf    = <?= json_encode($csrf) ?>;
    var recentSearches = <?= json_encode($recentSearches ?? [], JSON_UNESCAPED_UNICODE) ?>;
    var templates      = <?= json_encode($templates ?? [], JSON_UNESCAPED_UNICODE) ?>;
    var deleteRecentUrlTpl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'deleteRecent', '__ID__']) ?>';
    var saveTemplateUrl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'saveTemplate']) ?>';

    // ─── Toast ───────────────────────────────────────────────────────
    var toastContainer = document.getElementById('toast-container');
    function toast(msg, type) {
        type = type || 'info';
        var icon = { success:'ri-checkbox-circle-line', error:'ri-error-warning-line', warning:'ri-alert-line', info:'ri-information-line' }[type];
        var el = document.createElement('div');
        el.className = 'toast-rp ' + type;
        el.innerHTML = '<i class="toast-icon ' + icon + '"></i><div class="flex-grow-1">' + escapeHtml(msg) + '</div>';
        toastContainer.appendChild(el);
        setTimeout(function () {
            el.classList.add('dismissing');
            setTimeout(function () { el.remove(); }, 250);
        }, 3800);
    }

    // ─── HERE Maps init ─────────────────────────────────────────────
    var platform = new H.service.Platform({ apikey: hereKey });
    var defaultLayers = platform.createDefaultLayers();
    var mapEl = document.getElementById('map');
    var currentStyle = 'vector.normal.map';
    function resolveLayer(path) {
        var parts = path.split('.');
        var node = defaultLayers;
        for (var i = 0; i < parts.length; i++) {
            if (!node[parts[i]]) return defaultLayers.vector.normal.map;
            node = node[parts[i]];
        }
        return node;
    }
    var map = new H.Map(mapEl, resolveLayer(currentStyle), {
        center: { lat: 52.0, lng: 19.0 },
        zoom: 5,
        pixelRatio: window.devicePixelRatio || 1
    });
    window.addEventListener('resize', function () { map.getViewPort().resize(); });
    new H.mapevents.Behavior(new H.mapevents.MapEvents(map));
    var ui = H.ui.UI.createDefault(map, defaultLayers, 'pl-PL');

    var routeGroups = [];
    var pinsGroup = new H.map.Group();
    map.addObject(pinsGroup);
    var trafficLayer = null;

    // ─── Style switcher ──────────────────────────────────────────────
    document.querySelectorAll('.map-ctrl-btn[data-style]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.map-ctrl-btn[data-style]').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentStyle = btn.dataset.style;
            map.setBaseLayer(resolveLayer(currentStyle));
        });
    });
    document.getElementById('btn-traffic').addEventListener('click', function () {
        var btn = this;
        if (trafficLayer) {
            map.removeLayer(trafficLayer);
            trafficLayer = null;
            btn.classList.remove('active');
            toast('<?= __('Ruch drogowy wyłączony') ?>', 'info');
        } else {
            try {
                trafficLayer = defaultLayers.vector.traffic.map || defaultLayers.raster.traffic.map;
                if (trafficLayer) {
                    map.addLayer(trafficLayer);
                    btn.classList.add('active');
                    toast('<?= __('Ruch drogowy włączony') ?>', 'success');
                } else {
                    toast('<?= __('Warstwa ruchu niedostępna') ?>', 'warning');
                }
            } catch (e) {
                toast('<?= __('Błąd warstwy ruchu') ?>: ' + e.message, 'error');
            }
        }
    });

    // ─── Waypoints state ─────────────────────────────────────────────
    var waypointsEl = document.getElementById('waypoints-list');
    var waypoints = [];

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function flagEmoji(cc) {
        if (!cc) return '';
        var iso3to2 = { POL:'PL', DEU:'DE', CZE:'CZ', SVK:'SK', UKR:'UA', LTU:'LT', LVA:'LV', EST:'EE', BLR:'BY', AUT:'AT', HUN:'HU', FRA:'FR', ESP:'ES', ITA:'IT', NLD:'NL', BEL:'BE', DNK:'DK', SWE:'SE', NOR:'NO', FIN:'FI', GBR:'GB', IRL:'IE', CHE:'CH', ROU:'RO', BGR:'BG', GRC:'GR', PRT:'PT', SVN:'SI', HRV:'HR', LUX:'LU', RUS:'RU', MDA:'MD', SRB:'RS', MKD:'MK', ALB:'AL', BIH:'BA', MNE:'ME' };
        var c2 = (cc.length === 3) ? iso3to2[cc.toUpperCase()] : cc.toUpperCase();
        if (!c2 || c2.length !== 2) return '';
        return String.fromCodePoint(0x1F1E6 + c2.charCodeAt(0) - 65) + String.fromCodePoint(0x1F1E6 + c2.charCodeAt(1) - 65);
    }
    function colorForIdx(idx, total) {
        if (idx === 0) return '#16a34a';
        if (idx === total - 1) return '#dc2626';
        return '#f59e0b';
    }
    function letterForIdx(idx, total) {
        if (idx === total - 1) return String.fromCharCode(65 + Math.min(25, total - 1));
        return String.fromCharCode(65 + Math.min(25, idx));
    }
    function makeMarkerIcon(letter, color, flag) {
        var flagSvg = flag
            ? '<text x="20" y="48" text-anchor="middle" font-size="14">' + flag + '</text>'
            : '';
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="58" viewBox="0 0 48 58">'
                + '<defs><filter id="shadow"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-opacity=".25"/></filter></defs>'
                + '<g filter="url(#shadow)">'
                + '<path d="M24 0C11 0 0 11 0 24c0 16 24 34 24 34s24-18 24-34C48 11 37 0 24 0z" fill="' + color + '" stroke="white" stroke-width="2"/>'
                + '<circle cx="24" cy="24" r="11" fill="white"/>'
                + '<text x="24" y="29" text-anchor="middle" font-family="sans-serif" font-size="14" font-weight="bold" fill="' + color + '">' + letter + '</text>'
                + '</g>'
                + flagSvg
                + '</svg>';
        return new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 24, y: 58 } });
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
            var input = row.querySelector('.waypoint-input');
            input.value = wp.address || wp.label || '';
            var flagEl = row.querySelector('.waypoint-flag');
            flagEl.textContent = flagEmoji(wp.country || '');

            input.addEventListener('input', function () {
                wp.address = input.value;
                wp.lat = null; wp.lng = null; wp.country = '';
                flagEl.textContent = '';
                runAutosuggest(input, row.querySelector('.autosuggest-dropdown'), wp, flagEl);
            });
            input.addEventListener('focus', function () {
                if (input.value.length >= 2) runAutosuggest(input, row.querySelector('.autosuggest-dropdown'), wp, flagEl);
            });
            input.addEventListener('blur', function () {
                setTimeout(function () { row.querySelector('.autosuggest-dropdown').style.display = 'none'; }, 200);
            });
            row.querySelector('.btn-remove-wp').addEventListener('click', function () {
                if (waypoints.length <= 2) {
                    waypoints[idx] = { address: '', lat: null, lng: null, country: '' };
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
            var icon = makeMarkerIcon(letterForIdx(idx, waypoints.length), colorForIdx(idx, waypoints.length), flagEmoji(wp.country || ''));
            pinsGroup.addObject(new H.map.Marker({ lat: wp.lat, lng: wp.lng }, { icon: icon }));
        });
    }

    // ─── Autosuggest ─────────────────────────────────────────────────
    var autosuggestTimers = new WeakMap();
    function runAutosuggest(input, dropdown, wp, flagEl) {
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
                    var typeIcon = (it.type === 'place') ? 'ri-building-line' : (it.type === 'locality' ? 'ri-community-line' : 'ri-map-pin-line');
                    var flag = flagEmoji(it.country);
                    return '<div class="autosuggest-item" data-idx="' + i + '">'
                        + '<i class="' + typeIcon + '"></i>'
                        + '<span class="label">' + escapeHtml(it.title) + '</span>'
                        + (flag ? '<span class="country">' + flag + ' ' + escapeHtml(it.country) + '</span>' : '')
                        + '<div class="text-muted small mt-1">' + escapeHtml(it.label) + '</div>'
                        + '</div>';
                }).join('');
                dropdown.style.display = 'block';
                dropdown.querySelectorAll('.autosuggest-item').forEach(function (el, i) {
                    el.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        var it = items[i];
                        wp.address = it.label; wp.label = it.label;
                        wp.lat = it.lat; wp.lng = it.lng; wp.country = it.country;
                        input.value = it.label;
                        if (flagEl) flagEl.textContent = flagEmoji(it.country);
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

    // ─── Map click → dodaj waypoint ─────────────────────────────────
    map.addEventListener('tap', function (evt) {
        if (evt.target !== map) return;
        var coord = map.screenToGeo(evt.currentPointer.viewportX, evt.currentPointer.viewportY);
        if (!coord) return;
        var lastIdx = waypoints.length - 1;
        var newWp = { address: '(' + coord.lat.toFixed(5) + ', ' + coord.lng.toFixed(5) + ')', lat: coord.lat, lng: coord.lng, country: '' };
        if (waypoints[lastIdx].lat == null && waypoints[lastIdx].lng == null) {
            waypoints[lastIdx] = newWp;
        } else if (waypoints[0].lat == null && waypoints[0].lng == null) {
            waypoints[0] = newWp;
        } else {
            waypoints.splice(lastIdx, 0, newWp);
        }
        renderWaypoints();
        renderPinsOnMap();
        toast('<?= __('Dodano przystanek z mapy') ?>', 'success');
    });

    document.getElementById('btn-add-waypoint').addEventListener('click', function () {
        var lastIdx = waypoints.length - 1;
        waypoints.splice(lastIdx, 0, { address: '', lat: null, lng: null, country: '' });
        renderWaypoints();
    });
    document.getElementById('btn-reverse').addEventListener('click', function () {
        waypoints.reverse();
        renderWaypoints();
        renderPinsOnMap();
        toast('<?= __('Trasa odwrócona') ?>', 'info');
    });

    // ─── TSP optymalizacja (brute-force dla ≤6, nearest-neighbor dla więcej) ──
    function distSq(a, b) {
        var dx = a.lng - b.lng, dy = a.lat - b.lat;
        return dx*dx + dy*dy;
    }
    function tspBrute(points, fixedStart, fixedEnd) {
        var middle = points.slice(1, -1);
        var perms = permutations(middle);
        var best = null, bestDist = Infinity;
        perms.forEach(function (perm) {
            var route = [fixedStart].concat(perm, [fixedEnd]);
            var d = 0;
            for (var i = 1; i < route.length; i++) d += Math.sqrt(distSq(route[i-1], route[i]));
            if (d < bestDist) { bestDist = d; best = route; }
        });
        return best;
    }
    function permutations(arr) {
        if (arr.length <= 1) return [arr];
        var out = [];
        for (var i = 0; i < arr.length; i++) {
            var rest = arr.slice(0, i).concat(arr.slice(i + 1));
            permutations(rest).forEach(function (p) { out.push([arr[i]].concat(p)); });
        }
        return out;
    }
    function tspNearest(points, fixedStart, fixedEnd) {
        var middle = points.slice(1, -1);
        var route = [fixedStart];
        while (middle.length) {
            var last = route[route.length - 1];
            var bestI = 0, bestD = distSq(last, middle[0]);
            for (var i = 1; i < middle.length; i++) {
                var d = distSq(last, middle[i]);
                if (d < bestD) { bestI = i; bestD = d; }
            }
            route.push(middle.splice(bestI, 1)[0]);
        }
        route.push(fixedEnd);
        return route;
    }
    document.getElementById('btn-optimize').addEventListener('click', function () {
        var locatedPts = waypoints.filter(function (w) { return w.lat != null && w.lng != null; });
        if (locatedPts.length < 3) {
            toast('<?= __('Dodaj co najmniej 3 zlokalizowane punkty.') ?>', 'warning');
            return;
        }
        var first = waypoints[0], last = waypoints[waypoints.length - 1];
        if (first.lat == null || last.lat == null) {
            toast('<?= __('Start i koniec muszą mieć współrzędne.') ?>', 'warning');
            return;
        }
        var middle = waypoints.slice(1, -1).filter(function (w) { return w.lat != null && w.lng != null; });
        if (middle.length < 1) {
            toast('<?= __('Brak przystanków pośrednich do optymalizacji.') ?>', 'info');
            return;
        }
        var ordered = (waypoints.length <= 7)
            ? tspBrute([first].concat(middle, [last]), first, last)
            : tspNearest([first].concat(middle, [last]), first, last);
        waypoints = ordered;
        renderWaypoints();
        renderPinsOnMap();
        toast('<?= __('Kolejność zoptymalizowana') ?> ✨', 'success');
    });

    // ─── Render trasy ────────────────────────────────────────────────
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
                var outline = new H.map.Polyline(line, { style: { strokeColor: opts.outline || 'rgba(255,255,255,.95)', lineWidth: opts.lineWidth || 10 } });
                var stroke = new H.map.Polyline(line, { style: { strokeColor: opts.color || 'rgba(37,99,235,.92)', lineWidth: (opts.lineWidth || 10) - 4 } });
                group.addObject(outline);
                group.addObject(stroke);
            } catch (e) { console.error('Polyline decode failed', e); }
        });
        map.addObject(group);
        routeGroups.push(group);

        // Pulsująca animacja przy starcie (fade-in)
        if (opts.animate) {
            var op = 0;
            var step = function () {
                op += 0.08;
                group.getObjects().forEach(function (ob) {
                    try { ob.setStyle(Object.assign({}, ob.getStyle(), { strokeColor: ob.getStyle().strokeColor })); } catch (e) {}
                });
                if (op < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        }
        return group;
    }

    var lastResponse = null;
    var activeAltIdx = 0;
    var lastRouteId = null;

    function fmtNum(v, dec) { return Number(v || 0).toLocaleString('pl-PL', {minimumFractionDigits: dec || 0, maximumFractionDigits: dec || 0}); }
    function fmtDur(min) {
        if (!min) return '—';
        var h = Math.floor(min / 60), m = min % 60;
        return (h > 0 ? h + ' h ' : '') + m + ' min';
    }
    function fmtMeters(m) { return m < 1000 ? m + ' m' : (m/1000).toFixed(1) + ' km'; }

    function renderResult(data, animate) {
        lastResponse = data;
        activeAltIdx = 0;
        clearRoutes();
        if (!data.routes || !data.routes.length) return;

        data.routes.forEach(function (r, idx) {
            if (idx === 0) return;
            drawRoute(r, { color: 'rgba(148,163,184,.7)', outline: 'rgba(255,255,255,.7)', lineWidth: 8 });
        });
        drawRoute(data.routes[0], { color: 'rgba(37,99,235,.95)', lineWidth: 11, animate: !!animate });

        var bbox = null;
        routeGroups.forEach(function (g) {
            var gbb = g.getBoundingBox();
            if (gbb) bbox = bbox ? bbox.mergeRect(gbb) : gbb;
        });
        var pbb = pinsGroup.getBoundingBox();
        if (pbb) bbox = bbox ? bbox.mergeRect(pbb) : pbb;
        if (bbox) {
            map.getViewModel().setLookAtData({ bounds: bbox }, true);
            setTimeout(function () { map.setZoom(Math.max(map.getZoom() - 0.4, 4)); }, 100);
        }

        renderStatsBar(data.routes[0]);
        renderAlternatives(data.routes);
        renderDirections(data.routes[0]);

        // Aktywuj akcje
        document.getElementById('btn-print').disabled = false;
        document.getElementById('btn-share').disabled = false;
        document.getElementById('btn-save-template').disabled = false;
    }

    function renderStatsBar(r) {
        document.getElementById('stat-km').textContent = fmtNum(r.distance_km, 1);
        document.getElementById('stat-dur').textContent = fmtDur(r.duration_min);
        if (r.tolls_total !== null && r.tolls_total !== undefined) {
            document.getElementById('stat-tolls').textContent = fmtNum(r.tolls_total, 2);
            document.getElementById('stat-tolls-cur').textContent = r.tolls_currency || 'EUR';
        } else {
            document.getElementById('stat-tolls').textContent = '—';
        }
        // Fuel + driver
        var cons = parseFloat(document.getElementById('fuel-consumption').value || 0);
        var price = parseFloat(document.getElementById('fuel-price').value || 0);
        var rate = parseFloat(document.getElementById('driver-rate').value || 0);
        var fuelCost = (r.distance_km / 100) * cons * price;
        var driverCost = (r.duration_min / 60) * rate;
        document.getElementById('stat-fuel').textContent = fuelCost > 0 ? fmtNum(fuelCost, 2) : '—';
        document.getElementById('stat-driver').textContent = driverCost > 0 ? fmtNum(driverCost, 2) : '—';
        document.getElementById('stats-bar').classList.add('visible');
    }
    ['fuel-consumption','fuel-price','driver-rate'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', function () {
            if (lastResponse && lastResponse.routes[activeAltIdx]) renderStatsBar(lastResponse.routes[activeAltIdx]);
        });
    });

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
                clearRoutes();
                lastResponse.routes.forEach(function (r, i) {
                    if (i === activeAltIdx) return;
                    drawRoute(r, { color: 'rgba(148,163,184,.7)', outline: 'rgba(255,255,255,.7)', lineWidth: 8 });
                });
                drawRoute(lastResponse.routes[activeAltIdx], { color: 'rgba(37,99,235,.95)', lineWidth: 11 });
                renderStatsBar(lastResponse.routes[activeAltIdx]);
                renderDirections(lastResponse.routes[activeAltIdx]);
            });
        });
        ac.style.display = '';
    }

    function renderDirections(r) {
        var dc = document.getElementById('directions-card');
        var body = document.getElementById('directions-body');
        if (!r.instructions || !r.instructions.length) { dc.style.display = 'none'; return; }
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
        dc.style.display = '';
    }
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

    // ─── Action buttons in hero ─────────────────────────────────────
    document.getElementById('btn-print').addEventListener('click', function () { window.print(); });

    document.getElementById('btn-share').addEventListener('click', function () {
        var pts = waypoints.filter(function (w) { return w.lat != null && w.lng != null; });
        if (!pts.length) { toast('<?= __('Brak punktów do udostępnienia.') ?>', 'warning'); return; }
        var payload = {
            points: pts.map(function (p) { return { address: p.address, lat: p.lat, lng: p.lng, country: p.country }; }),
            vehicle_id: document.getElementById('vehicle-id').value,
        };
        var enc = btoa(unescape(encodeURIComponent(JSON.stringify(payload))));
        var url = window.location.origin + window.location.pathname + '?r=' + enc;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                toast('<?= __('Link skopiowany do schowka') ?>', 'success');
            }, function () { prompt('<?= __('Skopiuj link:') ?>', url); });
        } else { prompt('<?= __('Skopiuj link:') ?>', url); }
    });

    document.getElementById('btn-save-template').addEventListener('click', function () {
        if (!lastRouteId) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        var name = prompt('<?= __('Nazwa szablonu:') ?>', '');
        if (!name) return;
        var fd = new FormData();
        fd.append('id', lastRouteId);
        fd.append('name', name);
        fd.append('_csrfToken', csrf);
        fetch(saveTemplateUrl, {
            method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                toast('<?= __('Szablon zapisany') ?>: ' + data.name, 'success');
                // Optymistycznie przenieś z recent → templates
                var found = recentSearches.find(function (s) { return s.id === lastRouteId; });
                if (found) {
                    recentSearches = recentSearches.filter(function (s) { return s.id !== lastRouteId; });
                    found.name = data.name;
                    templates.unshift(found);
                    templates.sort(function (a, b) { return (a.name || '').localeCompare(b.name || '', 'pl'); });
                    renderRecent();
                    renderTemplates();
                }
            } else {
                toast(data.message || '<?= __('Błąd zapisu szablonu.') ?>', 'error');
            }
        });
    });

    // ─── Kalkulacja trasy ────────────────────────────────────────────
    function showLoading(show) {
        var ov = document.getElementById('map-loading');
        if (show) ov.classList.add('active'); else ov.classList.remove('active');
    }

    document.getElementById('btn-calc').addEventListener('click', function () {
        var pts = waypoints.filter(function (wp) { return wp.address || (wp.lat != null && wp.lng != null); });
        if (pts.length < 2) { toast('<?= __('Podaj co najmniej dwa punkty.') ?>', 'warning'); return; }

        var btn = this;
        var vehicleId = document.getElementById('vehicle-id').value;
        var avoid = Array.from(document.querySelectorAll('.avoid-opt:checked')).map(function (c) { return c.value; });
        var currency = document.getElementById('currency').value;
        var alternatives = parseInt(document.getElementById('alternatives').value, 10);
        var dep = document.getElementById('departure-time').value;
        var departureTime = dep ? new Date(dep).toISOString() : '';

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= __('Liczę…') ?>';
        showLoading(true);

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

        fetch(calcUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            if (!res.ok || res.data.error) {
                toast(res.data.message || '<?= __('Błąd kalkulacji trasy.') ?>', 'error');
                return;
            }
            if (res.data.points && res.data.points.length === pts.length) {
                res.data.points.forEach(function (p, i) {
                    if (waypoints[i]) {
                        waypoints[i].lat = p.lat;
                        waypoints[i].lng = p.lng;
                        if (!waypoints[i].country && p.country) waypoints[i].country = p.country;
                        if (!waypoints[i].address && p.label) waypoints[i].address = p.label;
                    }
                });
                renderWaypoints();
                renderPinsOnMap();
            }
            renderResult(res.data, true);

            // Update historia (optymistycznie). Próbujemy zidentyfikować nowy entry przez sygnaturę
            if (res.data.points && res.data.routes && res.data.routes[0]) {
                var newEntry = {
                    id: 'pending',
                    name: '',
                    waypoints: res.data.points.map(function (p) {
                        return { address: p.address || p.label, label: p.label || p.address, lat: p.lat, lng: p.lng, country: p.country };
                    }),
                    vehicle_id: vehicleId,
                    distance_km: res.data.routes[0].distance_km,
                    duration_min: res.data.routes[0].duration_min,
                    tolls_total: res.data.routes[0].tolls_total,
                    tolls_currency: res.data.routes[0].tolls_currency,
                    last_used: new Date().toISOString(),
                };
                // Przeładuj historię z DB przez ?refresh
                refreshHistory().then(function () {
                    // znajdź ostatnio dodaną
                    if (recentSearches.length) lastRouteId = recentSearches[0].id;
                });
            }
            toast('<?= __('Trasa wyznaczona') ?>', 'success');
        })
        .catch(function (e) { toast('<?= __('Błąd sieci') ?>: ' + e.message, 'error'); })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-route-fill me-1"></i><?= __('Wyznacz trasę') ?>';
            showLoading(false);
        });
    });

    function refreshHistory() {
        // Re-fetch index page i wyciągnij JSON-y? Prościej: po prostu trzymamy lokalnie zsynchronizowane.
        // W naszym calculate response nie zwracamy historii, więc na razie po prostu pomijamy odświeżenie z DB.
        // Optymistyczna aktualizacja w window: server-side stale entries też i tak dostaniemy przy następnym load /trasy.
        return Promise.resolve();
    }

    // ─── Historia + szablony ─────────────────────────────────────────
    function timeAgo(iso) {
        if (!iso) return '';
        try {
            var d = new Date(iso);
            var diff = Math.floor((Date.now() - d.getTime()) / 1000);
            if (diff < 60)    return diff + ' s temu';
            if (diff < 3600)  return Math.floor(diff / 60) + ' min temu';
            if (diff < 86400) return Math.floor(diff / 3600) + ' h temu';
            if (diff < 604800) return Math.floor(diff / 86400) + ' d temu';
            return d.toLocaleDateString('pl-PL');
        } catch (e) { return ''; }
    }
    function shortLabel(addr) {
        if (!addr) return '?';
        // Akceptuje string lub obiekt waypointu {address,label,lat,lng}
        if (typeof addr === 'object') addr = addr.address || addr.label || '';
        if (!addr) return '?';
        var first = String(addr).split(',')[0];
        return first.length > 22 ? first.substring(0, 20) + '…' : first;
    }
    function metaLine(s) {
        var meta = '';
        if (s.distance_km)  meta += fmtNum(s.distance_km, 1) + ' km';
        if (s.duration_min) meta += (meta ? ' · ' : '') + fmtDur(s.duration_min);
        if (s.tolls_total != null) meta += (meta ? ' · ' : '') + fmtNum(s.tolls_total, 2) + ' ' + (s.tolls_currency || 'EUR');
        return meta;
    }
    function fillFromEntry(item) {
        waypoints = (item.waypoints || []).map(function (w) {
            return {
                address: w.address || w.label || '',
                label:   w.label   || w.address || '',
                lat:     w.lat != null ? Number(w.lat) : null,
                lng:     w.lng != null ? Number(w.lng) : null,
                country: w.country || '',
            };
        });
        if (item.vehicle_id) {
            var sel = document.getElementById('vehicle-id');
            if (sel) sel.value = item.vehicle_id;
        }
        renderWaypoints();
        renderPinsOnMap();
        document.getElementById('btn-calc').click();
    }

    function renderTemplates() {
        var list = document.getElementById('templates-list');
        var card = document.getElementById('templates-card');
        var cnt  = document.getElementById('templates-count');
        if (!list) return;
        if (cnt) cnt.textContent = String(templates.length);
        if (!templates.length) { if (card) card.style.display = 'none'; return; }
        if (card) card.style.display = '';
        list.innerHTML = templates.map(function (s) {
            var wps = (s.waypoints || []).map(shortLabel).join(' → ');
            return '<div class="tpl-item d-flex align-items-center gap-2 px-3 py-2 border-bottom" data-id="' + s.id + '">'
                + '<div class="flex-grow-1 min-width-0">'
                +   '<div class="small tpl-name text-truncate">' + escapeHtml(s.name) + '</div>'
                +   '<div class="text-muted text-truncate" style="font-size:.7rem">' + escapeHtml(wps) + '</div>'
                +   '<div class="text-muted" style="font-size:.66rem">' + metaLine(s) + '</div>'
                + '</div>'
                + '<button type="button" class="btn btn-sm btn-link text-danger p-0 btn-del-recent" data-id="' + s.id + '" title="<?= __('Usuń') ?>"><i class="ri-close-line"></i></button>'
                + '</div>';
        }).join('');
        list.querySelectorAll('.tpl-item').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target.closest('.btn-del-recent')) return;
                var item = templates.find(function (x) { return x.id === el.dataset.id; });
                if (item) fillFromEntry(item);
            });
        });
        bindDeleteButtons(list);
    }

    function renderRecent() {
        var list = document.getElementById('recent-list');
        var card = document.getElementById('recent-card');
        var cnt  = document.getElementById('recent-count');
        if (!list) return;
        if (cnt) cnt.textContent = String(recentSearches.length);
        if (!recentSearches.length) { if (card) card.style.display = 'none'; return; }
        if (card) card.style.display = '';
        list.innerHTML = recentSearches.map(function (s) {
            var wps = (s.waypoints || []).map(shortLabel).join(' → ');
            return '<div class="recent-item d-flex align-items-center gap-2 px-3 py-2 border-bottom" data-id="' + s.id + '">'
                + '<div class="flex-grow-1 min-width-0">'
                +   '<div class="small fw-semibold text-truncate">' + escapeHtml(wps) + '</div>'
                +   '<div class="text-muted" style="font-size:.7rem">' + metaLine(s) + ' · ' + timeAgo(s.last_used) + '</div>'
                + '</div>'
                + '<button type="button" class="btn btn-sm btn-link text-danger p-0 btn-del-recent" data-id="' + s.id + '" title="<?= __('Usuń z historii') ?>"><i class="ri-close-line"></i></button>'
                + '</div>';
        }).join('');
        list.querySelectorAll('.recent-item').forEach(function (el) {
            el.addEventListener('click', function (e) {
                if (e.target.closest('.btn-del-recent')) return;
                var item = recentSearches.find(function (x) { return x.id === el.dataset.id; });
                if (item) fillFromEntry(item);
            });
        });
        bindDeleteButtons(list);
    }

    function bindDeleteButtons(scope) {
        scope.querySelectorAll('.btn-del-recent').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var id = btn.dataset.id;
                if (!confirm('<?= __('Usunąć ten wpis?') ?>')) return;
                fetch(deleteRecentUrlTpl.replace('__ID__', encodeURIComponent(id)), {
                    method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
                }).then(function () {
                    recentSearches = recentSearches.filter(function (x) { return x.id !== id; });
                    templates = templates.filter(function (x) { return x.id !== id; });
                    renderRecent();
                    renderTemplates();
                    toast('<?= __('Usunięto') ?>', 'info');
                });
            });
        });
    }

    var btnToggleRecent = document.getElementById('btn-toggle-recent');
    if (btnToggleRecent) {
        btnToggleRecent.addEventListener('click', function () {
            var body = document.getElementById('recent-body');
            var ic = btnToggleRecent.querySelector('i');
            if (body.style.display === 'none') { body.style.display = ''; ic.className = 'ri-arrow-up-s-line'; }
            else { body.style.display = 'none'; ic.className = 'ri-arrow-down-s-line'; }
        });
    }

    // ─── Init + Share URL load ───────────────────────────────────────
    waypoints = [
        { address: '', lat: null, lng: null, country: '' },
        { address: '', lat: null, lng: null, country: '' },
    ];

    // Wczytaj z ?r=encoded jeśli jest
    var params = new URLSearchParams(window.location.search);
    if (params.has('r')) {
        try {
            var decoded = JSON.parse(decodeURIComponent(escape(atob(params.get('r')))));
            if (decoded.points && decoded.points.length >= 2) {
                waypoints = decoded.points.map(function (p) {
                    return { address: p.address || '', label: p.address || '', lat: p.lat || null, lng: p.lng || null, country: p.country || '' };
                });
                if (decoded.vehicle_id) {
                    var sel = document.getElementById('vehicle-id');
                    if (sel) sel.value = decoded.vehicle_id;
                }
                setTimeout(function () {
                    renderPinsOnMap();
                    document.getElementById('btn-calc').click();
                    toast('<?= __('Trasa wczytana z linka') ?>', 'info');
                }, 300);
            }
        } catch (e) { toast('<?= __('Błąd wczytywania linka') ?>', 'error'); }
    }

    renderWaypoints();
    renderRecent();
    renderTemplates();
})();
</script>

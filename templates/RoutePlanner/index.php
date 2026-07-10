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
$pricingHistoryUrl = $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'pricingHistory']);
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
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 30%, #2563eb 70%, #3b82f6 100%);
        color: #ffffff !important;
        border-radius: 14px;
        padding: 14px 22px;
        box-shadow: 0 8px 24px rgba(37, 99, 235, .25), 0 2px 4px rgba(15,23,42,.06);
        position: relative;
        margin-bottom: 18px;
        transition: padding .25s;
    }
    /* Wyższe paddingi gdy mamy stats bar (po kalkulacji) */
    .rp-hero:has(.stats-pill-bar.visible) { padding: 20px 26px; }
    /* Gradient effects w osobnym wrapperze z overflow:hidden — żeby dropdowny mogły wychodzić poza hero */
    .rp-hero-bg-effects {
        position: absolute; inset: 0; border-radius: 14px;
        overflow: hidden; pointer-events: none; z-index: 0;
    }
    .rp-hero-bg-effects::before {
        content: ''; position: absolute; top: -60%; right: -12%;
        width: 500px; height: 500px;
        background: radial-gradient(circle, rgba(255,255,255,.10), transparent 70%);
    }
    .rp-hero-bg-effects::after {
        content: ''; position: absolute; bottom: -40%; left: -8%;
        width: 320px; height: 320px;
        background: radial-gradient(circle, rgba(167,139,250,.18), transparent 70%);
    }
    .rp-hero > * { position: relative; z-index: 1; }
    /* Wiersz z buttonami (AI/Eksport) musi być nad stats-pill-bar żeby dropdowny nie znikały */
    .rp-hero > .d-flex { z-index: 10; }
    .rp-hero .dropdown-menu { z-index: 2000 !important; }
    /* Wymuś biały tekst (Bootstrap nadpisuje h2 default color) */
    .rp-hero h2,
    .rp-hero h2 *,
    .rp-hero .subtitle,
    .rp-hero .subtitle *,
    .rp-hero .stats-pill,
    .rp-hero .stats-pill * { color: #ffffff !important; }
    .rp-hero h2 {
        font-weight: 700; margin: 0; font-size: 1.35rem; letter-spacing: -.01em;
        text-shadow: 0 1px 2px rgba(0,0,0,.18);
        position: relative; z-index: 1;
    }
    .rp-hero:has(.stats-pill-bar.visible) h2 { font-size: 1.55rem; }
    .rp-hero .subtitle {
        opacity: .92; font-size: .85rem; margin-top: 6px;
        text-shadow: 0 1px 2px rgba(0,0,0,.15);
        position: relative; z-index: 1;
    }
    .rp-hero .btn-hero {
        background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.32);
        color: #ffffff !important; backdrop-filter: blur(10px); transition: all .18s;
        font-weight: 500; text-shadow: 0 1px 1px rgba(0,0,0,.15);
    }
    .rp-hero .btn-hero:hover:not(:disabled) {
        background: rgba(255,255,255,.26); transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,.18);
    }
    .rp-hero .btn-hero:disabled { opacity: .45; cursor: not-allowed; }
    .rp-hero .btn-hero i { color: #ffffff !important; }
    /* Dropdownów w hero nie obcinaj — Popper position:fixed (data-bs-strategy="fixed") */
    .rp-hero .dropdown-menu { z-index: 1080; }

    /* Flag-icons w Select2 (waluty + kraje wykluczone) */
    .fi { width: 1.2em; height: .9em; display: inline-block; vertical-align: middle; margin-right: .3em; border-radius: 2px;
          box-shadow: 0 0 1px rgba(0,0,0,.4); }
    .select2-results__option .fi { margin-right: .5em; }
    /* Select2 dropdowny doczepione do body — wysoki z-index nad kartami planera */
    .select2-container--open { z-index: 9999 !important; }
    .select2-dropdown { z-index: 9999 !important; }
    /* Select2 dla planera: kompaktowy rozmiar dopasowany do form-control-sm */
    .planer-select2 + .select2-container--default .select2-selection--single {
        height: 31px; border-color: #ced4da; font-size: .875rem;
    }
    .planer-select2 + .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 29px; padding-left: .55rem; padding-right: 1.8rem;
    }
    .planer-select2 + .select2-container--default .select2-selection--single .select2-selection__arrow { height: 29px; }
    .planer-select2 + .select2-container--default .select2-selection--multiple {
        min-height: 31px; border-color: #ced4da; font-size: .8rem; padding: 1px 4px;
    }
    .planer-select2 + .select2-container--default .select2-selection--multiple .select2-selection__choice {
        font-size: .78rem; padding: 1px 6px 1px 4px; line-height: 1.45;
        background: #eef2ff; border-color: #c7d2fe; color: #3730a3; margin-top: 3px;
    }
    .planer-select2 + .select2-container--default .select2-selection--multiple .select2-selection__choice__remove { color: #4f46e5; margin-right: 3px; }
    .planer-select2 + .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
        font-size: .8rem; margin-top: 4px;
    }

    /* Live pulse dot przy "Planer tras" gdy liczy */
    .live-dot {
        display: inline-block; width: 8px; height: 8px; border-radius: 50%;
        background: #4ade80; margin-right: 6px; vertical-align: middle;
        box-shadow: 0 0 0 0 rgba(74, 222, 128, .7);
        animation: livePulse 1.6s infinite;
    }
    @keyframes livePulse {
        0%   { box-shadow: 0 0 0 0 rgba(74,222,128,.65); }
        70%  { box-shadow: 0 0 0 12px rgba(74,222,128,0); }
        100% { box-shadow: 0 0 0 0 rgba(74,222,128,0); }
    }

    /* ── Hero stats pill bar ─────────────────────────────────────────── */
    .stats-pill-bar {
        display: none; gap: 12px; flex-wrap: wrap;
        margin-top: 16px; opacity: 0; transform: translateY(-8px);
        transition: opacity .4s, transform .4s;
    }
    .stats-pill-bar.visible {
        display: flex; opacity: 1; transform: translateY(0);
    }
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
    .stats-pill.eco     { background: linear-gradient(135deg, rgba(74,222,128,.28), rgba(34,197,94,.20)); border-color: rgba(74,222,128,.5); }
    /* #4 — Pill Zysk: 3 stany kolorystyczne (good ≥15% / fair 5-15% / bad <5%) */
    .stats-pill.profit.profit-good { background: linear-gradient(135deg, rgba(34,197,94,.32), rgba(22,163,74,.24)); border-color: rgba(74,222,128,.6); }
    .stats-pill.profit.profit-fair { background: linear-gradient(135deg, rgba(251,191,36,.28), rgba(245,158,11,.20)); border-color: rgba(252,211,77,.5); }
    .stats-pill.profit.profit-bad  { background: linear-gradient(135deg, rgba(239,68,68,.28), rgba(220,38,38,.20));  border-color: rgba(248,113,113,.5); }
    .stats-pill .sub-value {
        font-size: .72rem; opacity: .8; margin-top: 2px;
        text-shadow: 0 1px 1px rgba(0,0,0,.15);
    }

    /* ── Freight card (JJ Price + AI) ─────────────────────────────────── */
    .freight-card {
        margin-top: 14px;
        background: linear-gradient(135deg, rgba(255,255,255,.16), rgba(167,139,250,.18));
        backdrop-filter: blur(14px);
        border: 1px solid rgba(255,255,255,.28);
        border-radius: 14px;
        padding: 14px 18px;
        color: white !important;
        position: relative;
        z-index: 1;
        opacity: 0; transform: translateY(8px);
        transition: opacity .45s .1s, transform .45s .1s;
    }
    .freight-card.visible { opacity: 1; transform: translateY(0); }
    .freight-price {
        font-size: 1.85rem; font-weight: 700; letter-spacing: -.01em;
        text-shadow: 0 1px 2px rgba(0,0,0,.18);
    }
    .freight-unit { font-size: .9rem; opacity: .85; margin-left: 6px; font-weight: 500; }
    .freight-meta { opacity: .85; font-size: .72rem; }

    /* Distance markers */
    .dist-marker-badge {
        background: white; color: #1e3a8a; border: 2px solid #2563eb;
        border-radius: 999px; padding: 2px 10px; font-size: .7rem; font-weight: 700;
        box-shadow: 0 2px 6px rgba(0,0,0,.18);
    }

    /* Section breakdown */
    .leg-row { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; }
    .leg-row:last-child { border-bottom: none; }
    .leg-num { width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg,#dbeafe,#bfdbfe); color: #1e40af; font-weight: 700; display:inline-flex; align-items:center; justify-content:center; font-size: .72rem; }

    /* Hover highlight effect on map (animated dash) */
    @keyframes routePulse {
        0%, 100% { stroke-width: 6; }
        50%      { stroke-width: 9; }
    }

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
        transition: background .2s, transform .15s, box-shadow .15s;
        padding: 10px 6px; border-radius: 10px;
        position: relative;
    }
    .waypoint-row + .waypoint-row { border-top: 1px dashed #e5e7eb; margin-top: 4px; }
    .waypoint-row:hover { background: linear-gradient(90deg, rgba(99,102,241,.04), transparent); }
    .waypoint-row.sortable-chosen { background: #eff6ff; box-shadow: 0 4px 14px rgba(99,102,241,.18); transform: scale(1.01); }
    .waypoint-row .drag-handle { cursor: grab; color: #9ca3af; transition: color .15s; }
    .waypoint-row .drag-handle:hover { color: #4f46e5; }
    .waypoint-row .drag-handle:active { cursor: grabbing; }
    .waypoint-marker {
        width: 30px; height: 30px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        color: white; font-weight: 700; font-size: .74rem; flex-shrink: 0;
        box-shadow: 0 3px 8px rgba(0,0,0,.18);
        position: relative;
    }
    .waypoint-marker::after {
        content: ''; position: absolute; inset: -3px; border-radius: 50%;
        background: inherit; opacity: .15; z-index: -1;
    }
    .marker-origin { background: linear-gradient(135deg, #22c55e, #15803d); }
    .marker-via    { background: linear-gradient(135deg, #fbbf24, #d97706); }
    .marker-dest   { background: linear-gradient(135deg, #ef4444, #b91c1c); }
    .waypoint-flag { font-size: 1.2rem; line-height: 1; margin-left: 4px; }
    .waypoint-input {
        border-radius: 8px; transition: border-color .15s, box-shadow .15s;
        font-weight: 500;
    }
    .waypoint-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
    .waypoint-date {
        border-radius: 6px; border: 1px solid #e5e7eb; color: #6b7280;
        background: #f9fafb; max-width: 200px;
    }
    .waypoint-date:focus { background: white; border-color: #6366f1; }
    .wp-date-icon { font-size: 1rem; }
    .wp-date-label { font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }

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
    <div class="rp-hero-bg-effects"></div>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h2>
                <span class="live-dot"></span>
                <i class="ri-route-line me-2"></i><?= __('Planer tras') ?>
            </h2>
            <div class="subtitle">
                <i class="ri-truck-line"></i> JJ Maps · <?= __('profil truck · multipoint · opłaty drogowe EU') ?>
            </div>
        </div>
        <div class="d-flex gap-2 no-print flex-wrap">
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-hero dropdown-toggle" data-bs-toggle="dropdown"
                        data-bs-strategy="fixed"
                        title="<?= __('AI Asystent') ?>">
                    <i class="ri-sparkling-2-line me-1"></i><?= __('AI') ?>
                </button>
                <ul class="dropdown-menu shadow">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#aiParserModal">
                        <i class="ri-magic-line me-2 text-primary"></i><?= __('Parser trasy z tekstu') ?></a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#aiCargoModal">
                        <i class="ri-truck-line me-2 text-success"></i><?= __('Wizard ładunku') ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item ai-needs-route" href="#" id="btn-ai-pricing-open">
                        <i class="ri-robot-2-line me-2 text-danger"></i><?= __('AI Pricing Advisor') ?></a></li>
                    <li><a class="dropdown-item ai-needs-route" href="#" id="btn-ai-brief-open">
                        <i class="ri-user-voice-line me-2 text-warning"></i><?= __('Brief dla kierowcy') ?></a></li>
                    <li><a class="dropdown-item ai-needs-route" href="#" id="btn-ai-optimizer-open">
                        <i class="ri-compass-3-line me-2 text-info"></i><?= __('Wybierz najlepszą alternatywę') ?></a></li>
                    <li><a class="dropdown-item ai-needs-route" href="#" id="btn-ai-delay-open">
                        <i class="ri-time-line me-2 text-danger"></i><?= __('Predykcja realnego ETA') ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#aiEmailModal">
                        <i class="ri-mail-line me-2 text-secondary"></i><?= __('Odpowiedź na email klienta') ?></a></li>
                </ul>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-hero dropdown-toggle" data-bs-toggle="dropdown" disabled id="btn-export"
                        data-bs-strategy="fixed"
                        title="<?= __('Eksport') ?>">
                    <i class="ri-download-line me-1"></i><?= __('Eksport') ?>
                </button>
                <ul class="dropdown-menu shadow">
                    <li><a class="dropdown-item" href="#" id="btn-export-gpx">
                        <i class="ri-route-line me-2 text-success"></i>GPX <small class="text-muted">(<?= __('Garmin/Sygic') ?>)</small></a></li>
                    <li><a class="dropdown-item" href="#" id="btn-export-kml">
                        <i class="ri-earth-line me-2 text-info"></i>KML <small class="text-muted">(Google Earth)</small></a></li>
                    <li><a class="dropdown-item" href="#" id="btn-export-ical">
                        <i class="ri-calendar-line me-2 text-warning"></i>iCal <small class="text-muted">(Outlook/Google)</small></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" id="btn-embed-link">
                        <i class="ri-code-line me-2 text-primary"></i><?= __('Embed (read-only)') ?></a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-sm btn-hero" id="btn-truck-pois" disabled
                    title="<?= __('Pokaż parkingi i stacje truck-friendly wzdłuż trasy') ?>">
                <i class="ri-truck-line me-1"></i><?= __('Parkingi/Stacje') ?>
            </button>
            <button type="button" class="btn btn-sm btn-hero" id="btn-customer-offer" disabled
                    title="<?= __('Wygeneruj ofertę PDF dla klienta') ?>">
                <i class="ri-file-text-line me-1"></i><?= __('Oferta PDF') ?>
            </button>
            <button type="button" class="btn btn-sm btn-hero" id="btn-track-link" disabled
                    title="<?= __('Wygeneruj link tracking dla klienta') ?>">
                <i class="ri-broadcast-line me-1"></i><?= __('Link dla klienta') ?>
            </button>
            <button type="button" class="btn btn-sm btn-hero" id="btn-cmr" disabled
                    title="<?= __('Generator listu przewozowego CMR') ?>">
                <i class="ri-file-list-3-line me-1"></i>CMR
            </button>
            <button type="button" class="btn btn-sm btn-hero" id="btn-multileg"
                    title="<?= __('Optymalizator wielu zleceń (TSP/PDP)') ?>">
                <i class="ri-shuffle-line me-1"></i><?= __('Multi-leg') ?>
            </button>
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
            <button type="button" class="btn btn-sm btn-success" id="btn-send-offer" disabled
                    title="<?= __('Utwórz ofertę i wyślij do klienta') ?>"
                    data-bs-toggle="modal" data-bs-target="#sendOfferModal">
                <i class="ri-mail-send-line me-1"></i><?= __('Wyślij ofertę') ?>
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
            <div class="sub-value" id="stat-tolls-pln" style="display:none"></div>
        </div>
        <div class="stats-pill fuel">
            <div class="label"><i class="ri-gas-station-line me-1"></i><?= __('Paliwo') ?></div>
            <div class="value"><span id="stat-fuel">—</span><span class="unit">PLN</span></div>
        </div>
        <div class="stats-pill driver">
            <div class="label"><i class="ri-user-line me-1"></i><?= __('Kierowca') ?></div>
            <div class="value"><span id="stat-driver">—</span><span class="unit">PLN</span></div>
        </div>
        <div class="stats-pill eco">
            <div class="label"><i class="ri-leaf-line me-1"></i><?= __('CO₂') ?></div>
            <div class="value"><span id="stat-co2">—</span><span class="unit">kg</span></div>
        </div>
        <div class="stats-pill profit" id="profit-pill" style="display:none">
            <div class="label"><i class="ri-money-dollar-circle-line me-1"></i><?= __('Zysk') ?></div>
            <div class="value"><span id="stat-profit">—</span><span class="unit">PLN</span></div>
            <div class="sub-value" id="stat-margin">—</div>
        </div>
    </div>
    <div class="freight-card" id="freight-card" style="display:none">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="text-uppercase small" style="opacity:.85;letter-spacing:.6px;font-weight:600">
                    <i class="ri-sparkling-2-line me-1"></i><?= __('JJ Price · cena frachtu') ?>
                </div>
                <div class="freight-price"><span id="stat-freight">—</span><span class="freight-unit">PLN</span></div>
                <div class="freight-meta small mt-1" id="freight-breakdown"></div>
            </div>
            <div class="text-end" id="ai-price-block" style="display:none">
                <div class="text-uppercase small" style="opacity:.85;letter-spacing:.6px;font-weight:600">
                    <i class="ri-robot-2-line me-1"></i><?= __('AI sugeruje') ?>
                </div>
                <div class="freight-price"><span id="stat-ai">—</span><span class="freight-unit">PLN</span></div>
                <div class="freight-meta small mt-1" id="ai-basis"></div>
            </div>
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
                <?php if (!empty($combinations)): ?>
                <div class="mb-2">
                    <label class="form-label small mb-1">
                        <i class="ri-links-line text-warning me-1"></i><?= __('Zestaw') ?>
                        <?= $this->Html->link(
                            '<i class="ri-external-link-line"></i>',
                            ['controller' => 'VehicleCombinations', 'action' => 'index'],
                            ['escape' => false, 'class' => 'ms-1 text-muted small', 'title' => __('Zarządzaj zestawami'), 'target' => '_blank']
                        ) ?>
                    </label>
                    <select class="form-select form-select-sm" id="combination-id">
                        <option value=""><?= __('— wybierz zestaw lub składaj ręcznie —') ?></option>
                        <?php foreach ($combinations as $c): ?>
                            <option value="<?= h($c->id) ?>"
                                    data-vehicle-id="<?= h($c->vehicle_id ?? '') ?>"
                                    data-trailer-id="<?= h($c->trailer_id ?? '') ?>"
                                    data-driver-id="<?= h($c->driver_id ?? '') ?>"
                                    <?= $c->is_default ? 'selected' : '' ?>>
                                <?= h($c->name) ?><?= $c->is_default ? ' ★' : '' ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <small class="text-muted"><?= __('Auto-uzupełnia ciągnik, naczepę i kierowcę.') ?></small>
                </div>
                <?php endif ?>
                <div class="mb-2">
                    <label class="form-label small mb-1"><i class="ri-truck-line text-primary me-1"></i><?= __('Ciągnik / pojazd') ?></label>
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
                <?php if (!empty($trailers)): ?>
                <div class="mb-2">
                    <label class="form-label small mb-1"><i class="ri-roadster-line text-info me-1"></i><?= __('Naczepa') ?></label>
                    <select class="form-select form-select-sm" id="trailer-id">
                        <option value=""><?= __('— brak naczepy —') ?></option>
                        <?php foreach ($trailers as $t): ?>
                            <option value="<?= h($t->id) ?>" <?= $t->is_default ? 'selected' : '' ?>>
                                <?= h($t->name) ?>
                                <?php if ($t->plate): ?> (<?= h($t->plate) ?>)<?php endif; ?>
                                <?php if ($t->gross_weight_kg): ?> — <?= number_format($t->gross_weight_kg / 1000, 1, ',', '') ?>t<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (!empty($drivers)): ?>
                <div class="mb-3">
                    <label class="form-label small mb-1"><i class="ri-user-3-line text-success me-1"></i><?= __('Kierowca') ?></label>
                    <select class="form-select form-select-sm" id="driver-id">
                        <option value=""><?= __('— ręczna stawka z Kosztów —') ?></option>
                        <?php foreach ($drivers as $d): ?>
                            <option value="<?= h($d->id) ?>" <?= $d->is_default ? 'selected' : '' ?>>
                                <?= h($d->full_name) ?>
                                <?php if ($d->hourly_rate_pln): ?> — <?= number_format((float)$d->hourly_rate_pln, 0, ',', '') ?> PLN/h<?php endif; ?>
                                <?php if ($d->adr_certified): ?> · ADR<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
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
                        <select class="form-select form-select-sm planer-select2" id="currency">
                            <option value="EUR" data-cc="eu" data-name="<?= __('Euro') ?>" selected>EUR</option>
                            <option value="PLN" data-cc="pl" data-name="<?= __('Złoty polski') ?>">PLN</option>
                            <option value="USD" data-cc="us" data-name="<?= __('Dolar amerykański') ?>">USD</option>
                            <option value="GBP" data-cc="gb" data-name="<?= __('Funt brytyjski') ?>">GBP</option>
                            <option value="CZK" data-cc="cz" data-name="<?= __('Korona czeska') ?>">CZK</option>
                            <option value="CHF" data-cc="ch" data-name="<?= __('Frank szwajcarski') ?>">CHF</option>
                            <option value="NOK" data-cc="no" data-name="<?= __('Korona norweska') ?>">NOK</option>
                            <option value="SEK" data-cc="se" data-name="<?= __('Korona szwedzka') ?>">SEK</option>
                            <option value="DKK" data-cc="dk" data-name="<?= __('Korona duńska') ?>">DKK</option>
                            <option value="HUF" data-cc="hu" data-name="<?= __('Forint węgierski') ?>">HUF</option>
                            <option value="RON" data-cc="ro" data-name="<?= __('Lej rumuński') ?>">RON</option>
                            <option value="UAH" data-cc="ua" data-name="<?= __('Hrywna ukraińska') ?>">UAH</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1"><?= __('Alternatywne trasy') ?></label>
                        <select class="form-select form-select-sm" id="alternatives"><option value="0">0</option><option value="1">1</option><option value="2" selected>2</option><option value="3">3</option></select>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label small mb-1"><?= __('Wyjazd') ?></label>
                    <input type="datetime-local" class="form-control form-control-sm" id="departure-time">
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-12">
                        <label class="form-label small mb-1"><?= __('Klasa ADR') ?></label>
                        <select class="form-select form-select-sm planer-select2 adr-select" id="adr-class">
                            <option value="" data-icon="ri-checkbox-blank-circle-line" data-color="#9ca3af"><?= __('— brak ADR —') ?></option>
                            <option value="1" data-icon="ri-bomb-line" data-color="#dc2626">1 — <?= __('Materiały wybuchowe') ?></option>
                            <option value="2" data-icon="ri-cloud-line" data-color="#16a34a">2 — <?= __('Gazy') ?></option>
                            <option value="3" data-icon="ri-fire-line" data-color="#ea580c">3 — <?= __('Ciecze łatwopalne') ?></option>
                            <option value="4" data-icon="ri-fire-fill" data-color="#b45309">4 — <?= __('Materiały stałe łatwopalne') ?></option>
                            <option value="5" data-icon="ri-flask-line" data-color="#eab308">5 — <?= __('Utleniacze') ?></option>
                            <option value="6" data-icon="ri-skull-2-line" data-color="#111827">6 — <?= __('Toksyczne') ?></option>
                            <option value="7" data-icon="ri-radar-line" data-color="#a16207">7 — <?= __('Radioaktywne') ?></option>
                            <option value="8" data-icon="ri-test-tube-line" data-color="#0ea5e9">8 — <?= __('Korozyjne') ?></option>
                            <option value="9" data-icon="ri-alert-line" data-color="#7c3aed">9 — <?= __('Inne niebezpieczne') ?></option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1"><?= __('Wyklucz kraje') ?></label>
                        <select class="form-select form-select-sm planer-select2" id="exclude-countries" multiple style="font-size:.78rem"
                                data-placeholder="<?= __('Kliknij i wybierz kraje…') ?>">
                            <option value="POL" data-cc="pl"><?= __('Polska') ?></option>
                            <option value="DEU" data-cc="de"><?= __('Niemcy') ?></option>
                            <option value="CZE" data-cc="cz"><?= __('Czechy') ?></option>
                            <option value="SVK" data-cc="sk"><?= __('Słowacja') ?></option>
                            <option value="UKR" data-cc="ua"><?= __('Ukraina') ?></option>
                            <option value="BLR" data-cc="by"><?= __('Białoruś') ?></option>
                            <option value="LTU" data-cc="lt"><?= __('Litwa') ?></option>
                            <option value="LVA" data-cc="lv"><?= __('Łotwa') ?></option>
                            <option value="AUT" data-cc="at"><?= __('Austria') ?></option>
                            <option value="HUN" data-cc="hu"><?= __('Węgry') ?></option>
                            <option value="FRA" data-cc="fr"><?= __('Francja') ?></option>
                            <option value="ITA" data-cc="it"><?= __('Włochy') ?></option>
                            <option value="ESP" data-cc="es"><?= __('Hiszpania') ?></option>
                            <option value="NLD" data-cc="nl"><?= __('Holandia') ?></option>
                            <option value="BEL" data-cc="be"><?= __('Belgia') ?></option>
                            <option value="ROU" data-cc="ro"><?= __('Rumunia') ?></option>
                            <option value="BGR" data-cc="bg"><?= __('Bułgaria') ?></option>
                            <option value="CHE" data-cc="ch"><?= __('Szwajcaria') ?></option>
                        </select>
                    </div>
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
                    <div class="col-12">
                        <label class="form-label small mb-1">
                            <i class="ri-money-dollar-circle-line text-success"></i>
                            <?= __('Cena frachtu od klienta (PLN)') ?>
                        </label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="freight-revenue" placeholder="<?= __('Wpisz aby zobaczyć zysk') ?>">
                        <div class="form-text" style="font-size:.7rem"><?= __('Pusto = bez kalkulacji zysku') ?></div>
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
            <div class="col-lg-4">
                <div class="card glass-card" id="alternatives-card" style="display:none">
                    <div class="card-header py-2 d-flex align-items-center gap-2">
                        <strong><i class="ri-git-branch-line me-1 text-primary"></i><?= __('Alternatywne trasy') ?></strong>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btn-compare-routes" disabled
                                title="<?= __('Zaznacz minimum 2 trasy żeby porównać') ?>">
                            <i class="ri-scales-3-line me-1"></i><?= __('Porównaj') ?>
                        </button>
                    </div>
                    <div class="card-body p-0" id="alternatives-list"></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card glass-card" id="legs-card" style="display:none">
                    <div class="card-header py-2"><strong><i class="ri-route-fill me-1 text-primary"></i><?= __('Etapy') ?></strong></div>
                    <div class="card-body p-0" id="legs-body"></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card glass-card" id="directions-card" style="display:none">
                    <div class="card-header py-2 d-flex align-items-center">
                        <strong><i class="ri-navigation-line me-1 text-primary"></i><?= __('Instrukcje') ?></strong>
                        <button type="button" class="btn btn-sm btn-link ms-auto p-0" id="btn-toggle-dirs"><i class="ri-arrow-down-s-line"></i></button>
                    </div>
                    <div class="card-body" id="directions-body" style="max-height:340px;overflow-y:auto"></div>
                </div>
            </div>
        </div>

        <!-- #7 Cabotage tracker -->
        <div class="row g-3 mt-0">
            <div class="col-lg-12">
                <div class="card glass-card" id="cabotage-card" style="display:none">
                    <div class="card-header py-2 d-flex align-items-center">
                        <strong><i class="ri-truck-line me-1 text-info"></i><?= __('Operacje cabotage') ?></strong>
                        <span class="text-muted small ms-2"><?= __('Limit UE: 3 operacje / 7 dni / kraj (Rozp. 1072/2009)') ?></span>
                        <button type="button" class="btn btn-sm btn-outline-info ms-auto" id="btn-cabotage-add">
                            <i class="ri-add-line me-1"></i><?= __('Dodaj operację') ?>
                        </button>
                    </div>
                    <div class="card-body py-2" id="cabotage-body"></div>
                </div>
            </div>
        </div>

        <!-- #8 Posted workers alert -->
        <div class="row g-3 mt-0">
            <div class="col-lg-12">
                <div class="card glass-card border-warning" id="posted-workers-card" style="display:none">
                    <div class="card-header py-2" style="background:linear-gradient(135deg,#fef3c7,#fde68a)">
                        <strong class="text-warning-emphasis"><i class="ri-passport-line me-1"></i><?= __('Zgłoszenie kierowcy delegowanego') ?></strong>
                        <span class="text-muted small ms-2"><?= __('Posted Workers — wymagane przed wjazdem ≥4h') ?></span>
                    </div>
                    <div class="card-body py-2" id="posted-workers-body"></div>
                </div>
            </div>
        </div>

        <!-- #10 Truck ban kalendarz (zakazy weekendowe/świąteczne) -->
        <div class="row g-3 mt-0">
            <div class="col-lg-12">
                <div class="card glass-card border-danger" id="truckban-card" style="display:none">
                    <div class="card-header py-2" style="background:linear-gradient(135deg,#fef2f2,#fee2e2)">
                        <strong class="text-danger"><i class="ri-forbid-line me-1"></i><?= __('Zakazy dla ciężarówek') ?></strong>
                        <span class="text-muted small ms-2"><?= __('Niedziele i święta — DE/AT/FR/IT (>7.5t)') ?></span>
                    </div>
                    <div class="card-body py-2" id="truckban-body"></div>
                </div>
            </div>
        </div>

        <!-- #3 Tachograph planner -->
        <div class="row g-3 mt-0">
            <div class="col-lg-12">
                <div class="card glass-card" id="tacho-card" style="display:none">
                    <div class="card-header py-2 d-flex align-items-center">
                        <strong><i class="ri-time-line me-1 text-warning"></i><?= __('Plan postojów AETR') ?></strong>
                        <span class="text-muted small ms-2"><?= __('zgodne z UE 561/2006') ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead style="background:#f9fafb;font-size:.78rem">
                                    <tr>
                                        <th>#</th>
                                        <th><?= __('Typ') ?></th>
                                        <th><?= __('Termin') ?></th>
                                        <th><?= __('Pozycja na trasie') ?></th>
                                        <th><?= __('Współrzędne') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="tacho-tbody" style="font-size:.82rem"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- L1+L2: Szczegółowe opłaty + winiety -->
        <div class="row g-3 mt-0">
            <div class="col-lg-12">
                <div class="card glass-card" id="tolls-card" style="display:none">
                    <div class="card-header py-2 d-flex align-items-center flex-wrap gap-2">
                        <strong><i class="ri-coin-line me-1 text-warning"></i><?= __('Szczegółowe opłaty drogowe') ?></strong>
                        <span id="tolls-summary" class="text-muted small ms-2"></span>
                        <button type="button" class="btn btn-sm btn-outline-info" id="btn-toll-categories" title="<?= __('Pokaż klasyfikację pojazdu per kraj') ?>" style="display:none">
                            <i class="ri-information-line me-1"></i><?= __('Klasy pojazdu') ?>
                        </button>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-toll-markers" title="<?= __('Pokaż bramki na mapie') ?>">
                                <i class="ri-map-pin-line me-1"></i><?= __('Bramki na mapie') ?>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-tolls-csv" title="<?= __('Eksport CSV') ?>">
                                <i class="ri-file-excel-2-line me-1"></i>CSV
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="btn-tolls-pdf" title="<?= __('Eksport PDF') ?>">
                                <i class="ri-file-pdf-2-line me-1"></i>PDF
                            </button>
                        </div>
                    </div>
                    <!-- Klasyfikacja pojazdu per kraj (collapse) -->
                    <div id="tolls-categories" class="px-3 pt-3" style="display:none">
                        <div class="alert alert-info py-2 mb-2 small">
                            <div class="mb-2"><strong><i class="ri-truck-line me-1"></i><?= __('Parametry pojazdu wysłane do HERE') ?>:</strong>
                                <span id="tolls-veh-params" class="ms-2"></span>
                            </div>
                            <div><strong><i class="ri-shield-check-line me-1"></i><?= __('Klasyfikacja per kraj') ?>:</strong>
                                <span class="text-muted">(<?= __('orientacyjnie wg standardów krajowych') ?>)</span>
                            </div>
                            <div id="tolls-classes" class="d-flex flex-wrap gap-2 mt-2"></div>
                            <div class="mt-2 small text-muted">
                                <i class="ri-information-line me-1"></i>
                                <?= __('HERE wylicza opłaty automatycznie na podstawie tych parametrów. Jeśli klasa nie zgadza się z oczekiwaniami — sprawdź ustawienia pojazdu (liczba osi, masa, EURO).') ?>
                            </div>
                            <div class="mt-1 small text-muted">
                                <i class="ri-truck-line me-1"></i>
                                <?= __('Pojazd to ZESPÓŁ — wprowadź sumy: liczbę osi (ciągnik + naczepa), DMC zespołu, max nacisk osi. EU normatywny zespół: 16,5 m × 2,55 m × 4 m, 40 t, 11,5 t/oś napędowa (Dyr. 96/53/EC).') ?>
                            </div>
                            <div class="mt-2 small">
                                <strong><i class="ri-external-link-line me-1"></i><?= __('Zweryfikuj stawki na oficjalnych cennikach') ?>:</strong>
                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <a href="https://www.autostrada-a2.pl/Cennik-oplat,4" target="_blank" rel="noopener" class="badge bg-light text-primary text-decoration-none border">🇵🇱 A2 AWSA cennik</a>
                                    <a href="https://www.stalexport-autostrady.pl/cenniki" target="_blank" rel="noopener" class="badge bg-light text-primary text-decoration-none border">🇵🇱 A4 Stalexport</a>
                                    <a href="https://www.etoll.gov.pl/pojazdy-ciezarowe/oplaty/" target="_blank" rel="noopener" class="badge bg-light text-primary text-decoration-none border">🇵🇱 e-TOLL stawki</a>
                                    <a href="https://www.toll-collect.de/de/toll_collect/bezahlen/maut_tarife/maut_tarife.html" target="_blank" rel="noopener" class="badge bg-light text-primary text-decoration-none border">🇩🇪 DE Toll Collect</a>
                                    <a href="https://www.asfinag.at/maut/lkw-maut/" target="_blank" rel="noopener" class="badge bg-light text-primary text-decoration-none border">🇦🇹 AT ASFINAG</a>
                                    <a href="https://mytocz.eu/pl/cennik" target="_blank" rel="noopener" class="badge bg-light text-primary text-decoration-none border">🇨🇿 CZ MYTO</a>
                                </div>
                            </div>
                            <div class="mt-2 small text-warning">
                                <i class="ri-alert-line me-1"></i>
                                <strong>UWAGA:</strong> HERE Routing API nie zwraca informacji o ZASTOSOWANEJ kategorii pojazdu per opłata (pole <code>vehicle_category</code> puste).
                                Stawki mogą się różnić od oficjalnych cenników — porównaj z linkami powyżej dla weryfikacji.
                            </div>
                        </div>
                    </div>

                    <!-- Stawki referencyjne dla PL operatorów (gdy występują) -->
                    <div id="tolls-ref-rates" style="display:none"></div>

                    <!-- Winiety -->
                    <div id="vignettes-section" class="px-3 pt-3 pb-2" style="display:none">
                        <div class="alert alert-warning py-2 mb-2 small d-flex align-items-start gap-2">
                            <i class="ri-sticker-line fs-18"></i>
                            <div>
                                <strong><?= __('Wymagane winiety') ?>:</strong>
                                <?= __('przed wjazdem do tych krajów kup naklejkę/e-winietę:') ?>
                            </div>
                        </div>
                        <div id="vignettes-list" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <!-- Tabela opłat -->
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" id="tolls-table">
                                <thead style="background:#f9fafb;font-size:.78rem">
                                    <tr>
                                        <th><?= __('Kraj') ?></th>
                                        <th><?= __('System') ?></th>
                                        <th><?= __('Odcinek / opłata') ?></th>
                                        <th><?= __('Klasa HERE') ?></th>
                                        <th class="text-end"><?= __('Cena') ?></th>
                                        <th><?= __('Płatność') ?></th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="tolls-tbody" style="font-size:.82rem"></tbody>
                                <tfoot id="tolls-tfoot" style="background:#f9fafb;font-weight:600"></tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- L3: Historia stawek dla klienta na tej trasie (Fala 2A) -->
        <div class="row g-3 mt-0">
            <div class="col-lg-12">
                <div class="card glass-card" id="pricing-history-card" style="display:none">
                    <div class="card-header py-2 d-flex align-items-center flex-wrap gap-2">
                        <strong><i class="ri-history-line me-1 text-info"></i><?= __('Historia stawek dla klienta na tej trasie') ?></strong>
                        <span id="pricing-history-summary" class="text-muted small ms-2"></span>
                        <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Tryb historii">
                                <input type="radio" class="btn-check" name="pricing-history-mode" id="mode-client" value="client" checked>
                                <label class="btn btn-outline-info" for="mode-client" title="<?= __('Historia tylko dla konkretnego klienta (NIP)') ?>">
                                    <i class="ri-user-line me-1"></i><?= __('Ten klient') ?>
                                </label>
                                <input type="radio" class="btn-check" name="pricing-history-mode" id="mode-market" value="market">
                                <label class="btn btn-outline-info" for="mode-market" title="<?= __('Historia z całego rynku niezależnie od klienta') ?>">
                                    <i class="ri-global-line me-1"></i><?= __('Rynek') ?>
                                </label>
                            </div>
                            <input type="text" id="pricing-history-nip" class="form-control form-control-sm"
                                   style="width:150px" placeholder="np. 5271234567" />
                            <button type="button" class="btn btn-sm btn-info text-white" id="btn-pricing-history-fetch">
                                <i class="ri-search-line me-1"></i><?= __('Sprawdź historię') ?>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="pricing-history-loading" class="text-center py-3" style="display:none">
                            <div class="spinner-border spinner-border-sm text-info"></div>
                            <div class="mt-2 small text-muted"><?= __('Szukam podobnych zleceń…') ?></div>
                        </div>
                        <div id="pricing-history-body" style="display:none">
                            <!-- Statystyki -->
                            <div id="pricing-history-stats" class="mb-3"></div>
                            <!-- Alert dumpingu -->
                            <div id="pricing-history-dumping-alert" style="display:none"></div>
                            <!-- TOP klienci (tylko w trybie market) -->
                            <div id="pricing-history-buyers" class="mb-3" style="display:none"></div>
                            <!-- Lista zleceń historycznych -->
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 align-middle">
                                    <thead class="table-light" style="font-size:.78rem">
                                        <tr>
                                            <th><?= __('Data zlec.') ?></th>
                                            <th><?= __('Nr zlec.') ?></th>
                                            <th id="pricing-history-th-buyer" style="display:none"><?= __('Klient') ?></th>
                                            <th><?= __('Trasa') ?></th>
                                            <th><?= __('Faktura') ?></th>
                                            <th class="text-end"><?= __('Kwota (PLN)') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="pricing-history-tbody" style="font-size:.82rem"></tbody>
                                </table>
                            </div>
                            <div class="mt-2 small text-muted">
                                <i class="ri-information-line me-1"></i>
                                <span id="pricing-history-match-info"></span>
                            </div>
                        </div>
                        <div id="pricing-history-empty" class="text-muted small py-3" style="display:none">
                            <i class="ri-inbox-line me-1"></i>
                            <?= __('Brak podobnych zleceń tego klienta w ostatnich 12 miesiącach.') ?>
                        </div>
                        <div id="pricing-history-error" class="alert alert-warning small py-2 mb-0" style="display:none"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════ AI MODALS ═══════════════════════════════ -->

<!-- Modal: Wyślij ofertę do klienta (Fala 2B) -->
<div class="modal fade" id="sendOfferModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden">
            <div class="modal-header" style="background: linear-gradient(135deg, #16a34a, #22c55e); color: white;">
                <h5 class="modal-title"><i class="ri-mail-send-line me-2"></i><?= __('Utwórz i wyślij ofertę do klienta') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="send-offer-form">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label"><?= __('Nazwa planu / oferty (widoczna dla operatora)') ?></label>
                            <input type="text" id="offer-name" class="form-control" placeholder="<?= __('np. HB RTS Warszawa-Berlin lipiec') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('Ważna do') ?></label>
                            <input type="date" id="offer-valid-until" class="form-control">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label"><?= __('Odbiorca (nazwa firmy)') ?></label>
                            <input type="text" id="offer-sent-to-name" class="form-control" placeholder="<?= __('HB RTS Sp. z o.o.') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('NIP klienta') ?> <small class="text-muted"><?= __('(opcjonalny)') ?></small></label>
                            <input type="text" id="offer-contractor-nip" class="form-control" placeholder="5271234567">
                        </div>

                        <div class="col-12">
                            <label class="form-label"><?= __('Email odbiorcy') ?></label>
                            <input type="email" id="offer-sent-to-email" class="form-control" placeholder="kontakt@hbrts.pl" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label"><?= __('Cena netto') ?></label>
                            <input type="number" id="offer-price" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><?= __('Waluta') ?></label>
                            <select id="offer-currency" class="form-select">
                                <option value="PLN">PLN</option>
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                                <option value="GBP">GBP</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">VAT %</label>
                            <input type="number" id="offer-vat-rate" class="form-control" value="23" min="0" step="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?= __('Termin płatności (dni)') ?></label>
                            <input type="number" id="offer-payment-days" class="form-control" value="30" min="1">
                        </div>

                        <div class="col-12">
                            <label class="form-label"><?= __('Temat wiadomości') ?></label>
                            <input type="text" id="offer-subject" class="form-control" value="<?= __('Oferta transportowa') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label"><?= __('Treść wiadomości (opcjonalne — do wiadomości email)') ?></label>
                            <textarea id="offer-message" class="form-control" rows="4" placeholder="<?= __('np. Przesyłam ofertę zgodnie z ustaleniami telefonicznymi z 15.07…') ?>"></textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="offer-send-now" checked>
                                <label class="form-check-label" for="offer-send-now">
                                    <?= __('Wyślij od razu (jeśli odznaczone — zapisze jako szkic)') ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </form>

                <div id="send-offer-alert" class="mt-3" style="display:none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
                <button type="button" class="btn btn-success" id="btn-send-offer-submit">
                    <i class="ri-check-line me-1"></i><?= __('Utwórz ofertę') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- AI Address Parser modal -->
<div class="modal fade" id="aiParserModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white;">
        <h5 class="modal-title"><i class="ri-magic-line me-2"></i><?= __('AI Parser trasy') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-2">
          <i class="ri-information-line me-1"></i>
          <?= __('Wklej tekst od klienta — email, wiadomość, opis trasy. AI wyciągnie waypoints, kody pocztowe, daty, ładunek i ADR.') ?>
        </div>
        <textarea class="form-control" id="ai-parser-input" rows="6"
                  placeholder="<?= __('np. Załadunek 15.05 30-552 Kraków ul. Wielicka 22, dostawa 16.05 do godz. 12:00 12101 Berlin Tempelhofer Damm 1. 24 tony, ADR klasa 3 — diesel.') ?>"></textarea>
        <div class="form-text small mt-1">
          <i class="ri-lightbulb-line me-1 text-warning"></i>
          <?= __('Podaj kody pocztowe gdzie się da — AI je rozpozna i geocoding HERE będzie 10× dokładniejszy. Jeśli nie znasz kodu — AI spróbuje go dopasować na podstawie miasta/ulicy.') ?>
        </div>
        <div id="ai-parser-result" class="mt-3" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
        <button type="button" class="btn btn-primary" id="btn-ai-parser-run">
          <i class="ri-sparkling-2-line me-1"></i><?= __('Analizuj') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- AI Cargo Wizard modal -->
<div class="modal fade" id="aiCargoModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #16a34a, #22c55e); color: white;">
        <h5 class="modal-title"><i class="ri-truck-line me-2"></i><?= __('AI Wizard ładunku') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-2">
          <i class="ri-information-line me-1"></i>
          <?= __('Opisz ładunek — AI podpowie klasę ADR, typ pojazdu i wymagania.') ?>
        </div>
        <input type="text" class="form-control" id="ai-cargo-input"
               placeholder="<?= __('np. beczki z paliwem 12 ton, palety z chemią, mrożone mięso 20t…') ?>">
        <div id="ai-cargo-result" class="mt-3" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
        <button type="button" class="btn btn-success" id="btn-ai-cargo-run">
          <i class="ri-sparkling-2-line me-1"></i><?= __('Analizuj') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- AI Driver Brief modal -->
<div class="modal fade" id="aiBriefModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #ea580c, #f97316); color: white;">
        <h5 class="modal-title"><i class="ri-user-voice-line me-2"></i><?= __('Brief dla kierowcy') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small mb-1"><?= __('Język briefu') ?></label>
          <select class="form-select form-select-sm" id="ai-brief-lang" style="max-width: 200px">
            <option value="pl">🇵🇱 Polski</option>
            <option value="en">🇬🇧 English</option>
            <option value="de">🇩🇪 Deutsch</option>
            <option value="ua">🇺🇦 Українська</option>
            <option value="ru">🇷🇺 Русский</option>
          </select>
        </div>
        <div id="ai-brief-loading" style="display:none" class="text-center py-4">
          <div class="spinner-border text-warning"></div>
          <div class="mt-2 small text-muted"><?= __('Generuję brief…') ?></div>
        </div>
        <pre id="ai-brief-result" class="p-3 rounded" style="display:none;background:#fef3c7;font-family:inherit;font-size:.88rem;line-height:1.6;white-space:pre-wrap;border:1px solid #fde68a"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Zamknij') ?></button>
        <button type="button" class="btn btn-info text-white" id="btn-ai-brief-qr" style="display:none" title="<?= __('Pokaż QR — kierowca zeskanuje telefonem') ?>">
          <i class="ri-qr-code-line me-1"></i><?= __('QR dla kierowcy') ?>
        </button>
        <button type="button" class="btn btn-warning" id="btn-ai-brief-copy" style="display:none">
          <i class="ri-clipboard-line me-1"></i><?= __('Kopiuj') ?>
        </button>
        <button type="button" class="btn btn-warning text-white" id="btn-ai-brief-run">
          <i class="ri-sparkling-2-line me-1"></i><?= __('Generuj') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- QR Brief modal -->
<div class="modal fade" id="qrBriefModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #0891b2, #06b6d4); color: white;">
        <h5 class="modal-title"><i class="ri-qr-code-line me-2"></i><?= __('QR kod dla kierowcy') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <div class="text-muted small mb-3">
          <i class="ri-information-line me-1"></i>
          <?= __('Kierowca skanuje telefonem i otwiera brief — bez drukowania') ?>
        </div>
        <div id="qr-image-wrapper" class="d-flex justify-content-center mb-3" style="min-height:320px;align-items:center;background:#f8fafc;border-radius:8px;padding:16px">
          <div class="spinner-border text-info"></div>
        </div>
        <div class="d-flex gap-2 justify-content-center">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-qr-print">
            <i class="ri-printer-line me-1"></i><?= __('Drukuj') ?>
          </button>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btn-qr-download">
            <i class="ri-download-line me-1"></i><?= __('Pobierz PNG') ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- AI Route Optimizer modal -->
<div class="modal fade" id="aiOptimizerModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #0891b2, #06b6d4); color: white;">
        <h5 class="modal-title"><i class="ri-compass-3-line me-2"></i><?= __('AI — Najlepsza alternatywa') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small mb-1"><?= __('Priorytet') ?></label>
          <select class="form-select form-select-sm" id="ai-optimizer-priority" style="max-width:280px">
            <option value="balanced"><?= __('Zrównoważony (czas + koszt)') ?></option>
            <option value="time"><?= __('Czas (najszybciej)') ?></option>
            <option value="cost"><?= __('Koszt (najtaniej)') ?></option>
            <option value="comfort"><?= __('Komfort (autostrady)') ?></option>
            <option value="risk"><?= __('Bezpieczeństwo (mało granic)') ?></option>
          </select>
        </div>
        <div id="ai-optimizer-loading" style="display:none" class="text-center py-4">
          <div class="spinner-border text-info"></div>
          <div class="mt-2 small text-muted"><?= __('Analizuję alternatywy…') ?></div>
        </div>
        <div id="ai-optimizer-result" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
        <button type="button" class="btn btn-info text-white" id="btn-ai-optimizer-run">
          <i class="ri-sparkling-2-line me-1"></i><?= __('Analizuj') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- AI Email Reply modal -->
<div class="modal fade" id="aiEmailModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #475569, #64748b); color: white;">
        <h5 class="modal-title"><i class="ri-mail-line me-2"></i><?= __('Odpowiedź na email klienta') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-2">
          <i class="ri-information-line me-1"></i>
          <?= __('Wklej treść emaila od klienta. AI rozpozna intencję, wygeneruje odpowiedź PL+EN i wskaże następne kroki.') ?>
        </div>
        <textarea class="form-control" id="ai-email-input" rows="6"
                  placeholder="<?= __('Dzień dobry, proszę o ofertę na transport ładunku z Warszawy do Berlina, 24 tony, paliwo, 17.05…') ?>"></textarea>
        <div id="ai-email-loading" style="display:none" class="text-center py-4">
          <div class="spinner-border text-secondary"></div>
        </div>
        <div id="ai-email-result" class="mt-3" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
        <button type="button" class="btn btn-secondary" id="btn-ai-email-run">
          <i class="ri-sparkling-2-line me-1"></i><?= __('Generuj odpowiedź') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- #9 CMR generator modal -->
<div class="modal fade" id="cmrModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #15803d, #16a34a); color: white;">
        <h5 class="modal-title"><i class="ri-file-list-3-line me-2"></i><?= __('Generator listu CMR') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label small mb-1"><?= __('Język CMR') ?></label>
          <select class="form-select" id="cmr-lang">
            <option value="pl">🇵🇱 PL — Polski</option>
            <option value="en">🇬🇧 EN — English</option>
            <option value="de">🇩🇪 DE — Deutsch</option>
            <option value="ua">🇺🇦 UA — Українська</option>
          </select>
        </div>
        <h6 class="text-muted small mb-2"><?= __('1. Nadawca (Sender)') ?></h6>
        <div class="row g-2 mb-3">
          <div class="col-12"><input type="text" class="form-control form-control-sm" id="cmr-sender-name" placeholder="<?= __('Nazwa firmy') ?>"></div>
          <div class="col-12"><input type="text" class="form-control form-control-sm" id="cmr-sender-addr" placeholder="<?= __('Adres (ulica, kod, miasto, kraj)') ?>"></div>
          <div class="col-6"><input type="text" class="form-control form-control-sm" id="cmr-sender-nip" placeholder="NIP/VAT"></div>
          <div class="col-6"><input type="text" class="form-control form-control-sm" id="cmr-sender-phone" placeholder="<?= __('Telefon') ?>"></div>
        </div>
        <h6 class="text-muted small mb-2"><?= __('2. Odbiorca (Consignee)') ?></h6>
        <div class="row g-2 mb-3">
          <div class="col-12"><input type="text" class="form-control form-control-sm" id="cmr-consignee-name" placeholder="<?= __('Nazwa firmy') ?>"></div>
          <div class="col-12"><input type="text" class="form-control form-control-sm" id="cmr-consignee-addr" placeholder="<?= __('Adres (auto-uzupełniony z dostawy)') ?>"></div>
          <div class="col-6"><input type="text" class="form-control form-control-sm" id="cmr-consignee-nip" placeholder="NIP/VAT"></div>
          <div class="col-6"><input type="text" class="form-control form-control-sm" id="cmr-consignee-phone" placeholder="<?= __('Telefon') ?>"></div>
        </div>
        <h6 class="text-muted small mb-2"><?= __('3. Ładunek') ?></h6>
        <div class="row g-2 mb-3">
          <div class="col-6"><input type="text" class="form-control form-control-sm" id="cmr-goods" placeholder="<?= __('Rodzaj ładunku') ?>"></div>
          <div class="col-3"><input type="number" class="form-control form-control-sm" id="cmr-pieces" placeholder="<?= __('Sztuk') ?>"></div>
          <div class="col-3"><input type="text" class="form-control form-control-sm" id="cmr-packaging" placeholder="<?= __('Opakowanie') ?>"></div>
          <div class="col-6"><input type="number" step="0.01" class="form-control form-control-sm" id="cmr-weight" placeholder="<?= __('Masa brutto (kg)') ?>"></div>
          <div class="col-6"><input type="text" class="form-control form-control-sm" id="cmr-marks" placeholder="<?= __('Znaki i numery') ?>"></div>
        </div>
        <h6 class="text-muted small mb-2"><?= __('4. Instrukcje / inne') ?></h6>
        <div class="row g-2 mb-3">
          <div class="col-12"><input type="text" class="form-control form-control-sm" id="cmr-instructions" placeholder="<?= __('Instrukcje nadawcy') ?>"></div>
          <div class="col-6"><input type="text" class="form-control form-control-sm" id="cmr-cmr-number" placeholder="<?= __('Nr CMR') ?>"></div>
          <div class="col-6"><input type="text" class="form-control form-control-sm" id="cmr-driver" placeholder="<?= __('Kierowca (imię, nazwisko)') ?>"></div>
        </div>
        <div class="form-check small">
          <input type="checkbox" class="form-check-input" id="cmr-adr" checked>
          <label class="form-check-label" for="cmr-adr"><?= __('Auto-wypełnij ADR/pojazd/trasę z planera') ?></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
        <button type="button" class="btn btn-success" id="btn-cmr-generate">
          <i class="ri-printer-line me-1"></i><?= __('Generuj CMR (drukuj/PDF)') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- #5 Multi-leg optimizer modal -->
<div class="modal fade" id="multilegModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #8b5cf6, #a855f7); color: white;">
        <h5 class="modal-title"><i class="ri-shuffle-line me-2"></i><?= __('Optymalizator multi-leg (2-4 ładunki)') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-3">
          <i class="ri-information-line me-1"></i>
          <?= __('Dodaj kilka zleceń (każde = pickup + dropoff). Algorytm znajdzie kolejność z minimum empty miles, respektując zasadę: pickup MUSI być przed dropoff.') ?>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label small mb-1"><i class="ri-home-line me-1"></i><?= __('Punkt startowy (opcjonalnie)') ?></label>
            <input type="text" class="form-control form-control-sm" id="ml-start" placeholder="<?= __('Baza / aktualna pozycja') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small mb-1"><i class="ri-map-pin-line me-1"></i><?= __('Punkt powrotu (opcjonalnie)') ?></label>
            <input type="text" class="form-control form-control-sm" id="ml-end" placeholder="<?= __('Najczęściej ta sama baza') ?>">
          </div>
        </div>

        <div id="ml-loads-list"></div>

        <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2" id="btn-ml-add">
            <i class="ri-add-line me-1"></i><?= __('Dodaj zlecenie') ?>
        </button>

        <div id="ml-loading" style="display:none" class="text-center py-4 mt-3">
          <div class="spinner-border text-primary"></div>
          <div class="mt-2 small text-muted"><?= __('Optymalizuję trasy…') ?></div>
        </div>

        <div id="ml-result" style="display:none" class="mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
        <button type="button" class="btn btn-primary" id="btn-ml-optimize">
          <i class="ri-sparkling-2-line me-1"></i><?= __('Optymalizuj') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- #17 Compare routes modal -->
<div class="modal fade" id="compareModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #4f46e5, #6366f1); color: white;">
        <h5 class="modal-title"><i class="ri-scales-3-line me-2"></i><?= __('Porównanie tras') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="compare-modal-body"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Zamknij') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- #13 AI Delay Prediction modal -->
<div class="modal fade" id="aiDelayModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #dc2626, #ef4444); color: white;">
        <h5 class="modal-title"><i class="ri-time-line me-2"></i><?= __('AI — Predykcja realnego ETA') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="text-muted small mb-2">
          <i class="ri-information-line me-1"></i>
          <?= __('HERE szacuje czas tylko po warunkach drogowych. AI uwzględnia korki, granice, czas załadunku, pogodę, AETR przerwy.') ?>
        </div>
        <div id="ai-delay-loading" style="display:none" class="text-center py-4">
          <div class="spinner-border text-danger"></div>
          <div class="mt-2 small text-muted"><?= __('Analizuję historię i warunki…') ?></div>
        </div>
        <div id="ai-delay-result" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Zamknij') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- AI Pricing modal -->
<div class="modal fade" id="aiPricingModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius: 14px; overflow: hidden">
      <div class="modal-header" style="background: linear-gradient(135deg, #db2777, #ec4899); color: white;">
        <h5 class="modal-title"><i class="ri-robot-2-line me-2"></i><?= __('AI Pricing Advisor') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="ai-pricing-loading" style="display:none" class="text-center py-4">
          <div class="spinner-border text-danger"></div>
          <div class="mt-2 small text-muted"><?= __('Analizuję rynek…') ?></div>
        </div>
        <div id="ai-pricing-result" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Zamknij') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Template dla waypoint -->
<template id="waypoint-tpl">
    <div class="waypoint-row" style="position:relative">
        <div class="d-flex align-items-center gap-2">
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
        <div class="d-flex align-items-center gap-2 mt-1 ps-4">
            <i class="ri-calendar-event-line text-muted small wp-date-icon"></i>
            <input type="datetime-local" class="form-control form-control-sm waypoint-date"
                   style="font-size:.78rem;padding:.18rem .4rem">
            <span class="text-muted wp-date-label" style="font-size:.7rem;min-width:70px">Załadunek</span>
            <span class="wp-eta-badge" style="display:none;font-size:.72rem;font-weight:600;padding:1px 6px;border-radius:8px"></span>
            <span class="wp-weather-badge" style="display:none;font-size:.72rem;padding:1px 8px;border-radius:8px;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe"></span>
        </div>
    </div>
</template>

<script>
(function () {
    // ─── Embed mode: ukryj wszystko poza mapą ──────────────────────
    if (new URLSearchParams(window.location.search).get('embed') === '1') {
        document.body.classList.add('rp-embed-mode');
        var style = document.createElement('style');
        style.textContent = '.rp-embed-mode .rp-hero,'
            + '.rp-embed-mode .app-sidebar,'
            + '.rp-embed-mode .app-header,'
            + '.rp-embed-mode .col-lg-4.no-print,'
            + '.rp-embed-mode #alternatives-card,'
            + '.rp-embed-mode #legs-card,'
            + '.rp-embed-mode #directions-card { display: none !important; }'
            + '.rp-embed-mode .row.g-3 > .col-lg-8 { width: 100% !important; flex: 0 0 100% !important; max-width: 100% !important; }'
            + '.rp-embed-mode #map { height: calc(100vh - 40px) !important; }'
            + '.rp-embed-mode .main-content,.rp-embed-mode .page,.rp-embed-mode body { padding: 0 !important; margin: 0 !important; background: white !important; }';
        document.head.appendChild(style);
    }

    // ─── Konfiguracja ────────────────────────────────────────────────
    var hereKey = <?= json_encode($hereApiKey) ?>;
    var calcUrl = <?= json_encode($calcUrl) ?>;
    var autoUrl = <?= json_encode($autoUrl) ?>;
    var csrf    = <?= json_encode($csrf) ?>;
    var recentSearches = <?= json_encode($recentSearches ?? [], JSON_UNESCAPED_UNICODE) ?>;
    var templates      = <?= json_encode($templates ?? [], JSON_UNESCAPED_UNICODE) ?>;
    var vehiclesData   = <?= json_encode(array_map(function ($v) {
        return [
            'id' => (string)$v->id,
            'name' => (string)$v->name,
            'plate' => (string)$v->plate,
            'rate_per_km'      => $v->rate_per_km !== null ? (float)$v->rate_per_km : null,
            'gross_weight_kg'  => $v->gross_weight_kg,
            'axle_load_kg'     => $v->axle_load_kg     ?? null,
            'height_cm'        => $v->height_cm        ?? null,
            'width_cm'         => $v->width_cm         ?? null,
            'length_cm'        => $v->length_cm        ?? null,
            'axle_count'       => $v->axle_count       ?? null,
            'tunnel_category'  => $v->tunnel_category  ?? null,
            'emission_class'   => $v->emission_class   ?? null,
            'hazardous_goods'  => $v->hazardous_goods  ?? null,
            'combination_type' => $v->combination_type ?? null,
        ];
    }, $vehicles), JSON_UNESCAPED_UNICODE) ?>;
    function getSelectedVehicle() {
        var id = document.getElementById('vehicle-id').value;
        return vehiclesData.find(function (v) { return v.id === id; }) || null;
    }
    var trailersData = <?= json_encode(array_map(function ($t) {
        return [
            'id' => (string)$t->id,
            'name' => (string)$t->name,
            'plate' => (string)$t->plate,
            'type' => (string)$t->type,
            'axle_count' => $t->axle_count ?? null,
            'gross_weight_kg' => $t->gross_weight_kg ?? null,
            'payload_kg' => $t->payload_kg ?? null,
            'length_cm' => $t->length_cm ?? null,
            'width_cm'  => $t->width_cm ?? null,
            'height_cm' => $t->height_cm ?? null,
            'volume_m3' => $t->volume_m3 !== null ? (float)$t->volume_m3 : null,
            'amortization_per_day_pln' => $t->amortization_per_day_pln !== null ? (float)$t->amortization_per_day_pln : null,
            'adr_certified' => (bool)($t->adr_certified ?? false),
        ];
    }, $trailers ?? []), JSON_UNESCAPED_UNICODE) ?>;
    var driversData = <?= json_encode(array_map(function ($d) {
        return [
            'id' => (string)$d->id,
            'full_name' => (string)$d->full_name,
            'hourly_rate_pln' => $d->hourly_rate_pln !== null ? (float)$d->hourly_rate_pln : null,
            'per_diem_pln'    => $d->per_diem_pln !== null ? (float)$d->per_diem_pln : null,
            'km_rate_pln'     => $d->km_rate_pln !== null ? (float)$d->km_rate_pln : null,
            'license_categories' => (string)$d->license_categories,
            'adr_certified' => (bool)($d->adr_certified ?? false),
            'languages' => (string)$d->languages,
            'phone' => (string)$d->phone,
        ];
    }, $drivers ?? []), JSON_UNESCAPED_UNICODE) ?>;
    function getSelectedTrailer() {
        var sel = document.getElementById('trailer-id');
        if (!sel) return null;
        return trailersData.find(function (t) { return t.id === sel.value; }) || null;
    }
    function getSelectedDriver() {
        var sel = document.getElementById('driver-id');
        if (!sel) return null;
        return driversData.find(function (d) { return d.id === sel.value; }) || null;
    }
    // Auto-fill stawki kierowcy gdy user wybierze konkretnego
    document.addEventListener('DOMContentLoaded', function () {
        var driverSel = document.getElementById('driver-id');
        if (driverSel) {
            driverSel.addEventListener('change', function () {
                var d = getSelectedDriver();
                if (d && d.hourly_rate_pln) {
                    var input = document.getElementById('driver-rate');
                    if (input) {
                        input.value = d.hourly_rate_pln;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
            });
        }

        // Auto-fill ciągnik/naczepa/kierowca gdy user wybierze zestaw
        var comboSel = document.getElementById('combination-id');
        if (comboSel) {
            var setSelectValue = function (id, val) {
                var el = document.getElementById(id);
                if (!el) return;
                el.value = val || '';
                el.dispatchEvent(new Event('change', { bubbles: true }));
            };
            var applyCombination = function () {
                var opt = comboSel.options[comboSel.selectedIndex];
                if (!opt || !opt.value) return; // pusta opcja → nie ruszamy nic
                var vId = opt.getAttribute('data-vehicle-id') || '';
                var tId = opt.getAttribute('data-trailer-id') || '';
                var dId = opt.getAttribute('data-driver-id') || '';
                setSelectValue('vehicle-id', vId);
                setSelectValue('trailer-id', tId);
                setSelectValue('driver-id', dId);
            };
            comboSel.addEventListener('change', applyCombination);
            // Wykonaj przy załadowaniu strony jeśli zestaw domyślny został preselectowany
            if (comboSel.value) applyCombination();
        }
    });
    var deleteRecentUrlTpl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'deleteRecent', '__ID__']) ?>';
    var saveTemplateUrl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'saveTemplate']) ?>';
    var revgeocodeUrl  = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'revgeocode']) ?>';

    // ─── localStorage cache dla geocodingu ───────────────────────────
    var GEOCACHE_KEY = 'rp_geocache_v1';
    var GEOCACHE_TTL = 14 * 24 * 3600 * 1000; // 14 dni
    var geoCache = {};
    try {
        var raw = localStorage.getItem(GEOCACHE_KEY);
        if (raw) {
            var parsed = JSON.parse(raw);
            var now = Date.now();
            Object.keys(parsed).forEach(function (k) {
                if (parsed[k] && parsed[k].t && (now - parsed[k].t) < GEOCACHE_TTL) {
                    geoCache[k] = parsed[k];
                }
            });
        }
    } catch (e) {}
    function saveCache() {
        try { localStorage.setItem(GEOCACHE_KEY, JSON.stringify(geoCache)); } catch (e) {}
    }
    function cacheGet(key) {
        var entry = geoCache[key];
        if (!entry) return null;
        if ((Date.now() - entry.t) > GEOCACHE_TTL) { delete geoCache[key]; return null; }
        return entry.v;
    }
    function cacheSet(key, value) {
        geoCache[key] = { t: Date.now(), v: value };
        saveCache();
    }

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
    window.__rpBehavior = new H.mapevents.Behavior(new H.mapevents.MapEvents(map));
    var ui = H.ui.UI.createDefault(map, defaultLayers, 'pl-PL');

    var routeGroups = [];
    var pinsGroup = new H.map.Group();
    map.addObject(pinsGroup);
    var trafficLayer = null;
    var distMarkerGroup = null;
    var borderGroup = null;

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

            // Date input + label
            var dateInput = row.querySelector('.waypoint-date');
            var dateLabel = row.querySelector('.wp-date-label');
            var dateIcon  = row.querySelector('.wp-date-icon');
            dateInput.value = wp.date || '';
            if (idx === 0) {
                dateLabel.textContent = '<?= __('Wyjazd') ?>';
                dateIcon.className = 'ri-arrow-up-circle-line text-success wp-date-icon';
            } else if (idx === waypoints.length - 1) {
                dateLabel.textContent = '<?= __('Dostawa') ?>';
                dateIcon.className = 'ri-arrow-down-circle-line text-danger wp-date-icon';
            } else {
                dateLabel.textContent = '<?= __('Postój') ?>';
                dateIcon.className = 'ri-time-line text-warning wp-date-icon';
            }
            dateInput.addEventListener('input', function () { wp.date = dateInput.value; });

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
                    waypoints[idx] = { address: '', lat: null, lng: null, country: '', date: '' };
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
            var m = new H.map.Marker({ lat: wp.lat, lng: wp.lng }, { icon: icon, volatility: true });
            m.draggable = true;
            m.setData({ wpIdx: idx });
            pinsGroup.addObject(m);
        });
    }

    // ─── Draggable markery: drag z mapy aktualizuje waypoint ─────
    var behavior = window.__rpBehavior; // marker — bo behavior był stworzony wcześniej
    // (behavior już istnieje, używamy referencji z mapy)
    // Ghost marker pokazywany podczas dragu polyline
    window.__rpDragGhost = null;
    function showDragGhost(coord) {
        if (!window.__rpDragGhost) {
            var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">'
                    + '<g><path d="M20 0C9 0 0 9 0 20c0 14 20 30 20 30s20-16 20-30C40 9 31 0 20 0z" fill="#7c3aed" stroke="white" stroke-width="3" opacity=".85"/>'
                    + '<circle cx="20" cy="20" r="8" fill="white"/>'
                    + '<text x="20" y="25" text-anchor="middle" font-size="12" font-weight="bold" fill="#7c3aed">+</text></g></svg>';
            var icon = new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 20, y: 50 } });
            window.__rpDragGhost = new H.map.Marker(coord, { icon: icon, volatility: true });
            map.addObject(window.__rpDragGhost);
        } else {
            window.__rpDragGhost.setGeometry(coord);
        }
    }
    function hideDragGhost() {
        if (window.__rpDragGhost) {
            try { map.removeObject(window.__rpDragGhost); } catch (e) {}
            window.__rpDragGhost = null;
        }
    }

    // Znajdź najlepsze miejsce na wstawienie nowego waypoint'a:
    // segment trasy (między waypoint[i] i waypoint[i+1]) najbliższy do (lat, lng)
    function findBestSegmentInsertIdx(targetLat, targetLng) {
        if (!lastResponse || !lastResponse.routes[activeAltIdx] || !lastResponse.routes[activeAltIdx].sections) return null;
        var sections = lastResponse.routes[activeAltIdx].sections;
        var bestIdx = 0;
        var bestDist = Infinity;
        sections.forEach(function (s, i) {
            // Distance od (targetLat,targetLng) do środka segmentu
            var midLat = (s.from_lat + s.to_lat) / 2;
            var midLng = (s.from_lng + s.to_lng) / 2;
            var d = Math.sqrt(Math.pow(midLat - targetLat, 2) + Math.pow(midLng - targetLng, 2));
            if (d < bestDist) { bestDist = d; bestIdx = i; }
        });
        // Sekcja i = trasa od waypoint[i] do waypoint[i+1] → insert at position i+1
        return bestIdx + 1;
    }

    map.addEventListener('dragstart', function (ev) {
        var target = ev.target;
        if (target instanceof H.map.Marker && target.draggable) {
            window.__rpDraggedMarker = target;
            map.getViewPort().element.style.cursor = 'grabbing';
            if (window.__rpBehavior) window.__rpBehavior.disable(H.mapevents.Behavior.Feature.DRAG_PAN);
        } else if (target instanceof H.map.Polyline) {
            // Drag polyline → dodaj nowy via point
            var d = target.getData();
            if (d && d.kind === 'route-stroke') {
                window.__rpDraggingRoute = true;
                map.getViewPort().element.style.cursor = 'grabbing';
                if (window.__rpBehavior) window.__rpBehavior.disable(H.mapevents.Behavior.Feature.DRAG_PAN);
                var p = ev.currentPointer;
                var coord = map.screenToGeo(p.viewportX, p.viewportY);
                if (coord) showDragGhost(coord);
            }
        }
    });
    map.addEventListener('drag', function (ev) {
        var m = window.__rpDraggedMarker;
        if (m) {
            var p = ev.currentPointer;
            var coord = map.screenToGeo(p.viewportX, p.viewportY);
            if (coord) m.setGeometry(coord);
            return;
        }
        if (window.__rpDraggingRoute) {
            var p = ev.currentPointer;
            var coord = map.screenToGeo(p.viewportX, p.viewportY);
            if (coord) showDragGhost(coord);
        }
    });
    map.addEventListener('dragend', function (ev) {
        var m = window.__rpDraggedMarker;
        if (m) {
        var d = m.getData();
        var geo = m.getGeometry();
        if (d && d.wpIdx != null && waypoints[d.wpIdx]) {
            var idx = d.wpIdx;
            waypoints[idx].lat = geo.lat;
            waypoints[idx].lng = geo.lng;
            // tymczasowy label
            waypoints[idx].address = '(' + geo.lat.toFixed(5) + ', ' + geo.lng.toFixed(5) + ')';
            waypoints[idx].label = waypoints[idx].address;
            renderWaypoints();
            reverseGeocode(geo.lat, geo.lng).then(function (data) {
                if (data && data.label && waypoints[idx]) {
                    waypoints[idx].address = data.label;
                    waypoints[idx].label = data.label;
                    waypoints[idx].country = data.country || '';
                    renderWaypoints();
                    renderPinsOnMap();
                }
            }).catch(function () {});
        }
        window.__rpDraggedMarker = null;
        }
        // Drop polyline → wstaw nowy via point
        if (window.__rpDraggingRoute) {
            window.__rpDraggingRoute = false;
            var p2 = ev.currentPointer;
            var dropCoord = map.screenToGeo(p2.viewportX, p2.viewportY);
            hideDragGhost();
            if (dropCoord) {
                var insertIdx = findBestSegmentInsertIdx(dropCoord.lat, dropCoord.lng);
                if (insertIdx != null && insertIdx > 0 && insertIdx < waypoints.length) {
                    var newWp = {
                        address: '(' + dropCoord.lat.toFixed(5) + ', ' + dropCoord.lng.toFixed(5) + ')',
                        label: '',
                        lat: dropCoord.lat,
                        lng: dropCoord.lng,
                        country: '',
                        date: ''
                    };
                    newWp.label = newWp.address;
                    waypoints.splice(insertIdx, 0, newWp);
                    toast('<?= __('Dodano przystanek — przeliczanie trasy…') ?>', 'info');
                    renderWaypoints();
                    renderPinsOnMap();
                    // Reverse geocode w tle dla nazwy
                    reverseGeocode(dropCoord.lat, dropCoord.lng).then(function (data) {
                        if (data && data.label && waypoints[insertIdx]) {
                            waypoints[insertIdx].address = data.label;
                            waypoints[insertIdx].label = data.label;
                            waypoints[insertIdx].country = data.country || '';
                            renderWaypoints();
                        }
                    }).catch(function () {});
                    // Auto-trigger recalculation
                    setTimeout(function () {
                        var btn = document.getElementById('btn-calc');
                        if (btn && !btn.disabled) btn.click();
                    }, 300);
                }
            }
        }
        map.getViewPort().element.style.cursor = '';
        if (window.__rpBehavior) window.__rpBehavior.enable(H.mapevents.Behavior.Feature.DRAG_PAN);
    });

    // ─── Autosuggest ─────────────────────────────────────────────────
    var autosuggestTimers = new WeakMap();
    function runAutosuggest(input, dropdown, wp, flagEl) {
        clearTimeout(autosuggestTimers.get(input));
        var q = input.value.trim();
        if (q.length < 2) { dropdown.style.display = 'none'; return; }
        var t = setTimeout(function () {
            var center = map.getCenter();
            var cacheKey = 'as:' + q.toLowerCase();
            var cached = cacheGet(cacheKey);
            var prom = cached
                ? Promise.resolve(cached)
                : fetch(autoUrl + '?q=' + encodeURIComponent(q) + '&lat=' + center.lat + '&lng=' + center.lng, {
                      headers: { 'Accept': 'application/json' }
                  }).then(function (r) { return r.json(); }).then(function (d) { cacheSet(cacheKey, d); return d; });
            prom.then(function (data) {
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

    // Uniwersalny autosuggest dla modali (multi-leg, cabotage, CMR).
    // Inputfield potrzebuje wrappera position:relative — wstawi dropdown jako sibling.
    function attachSimpleAutosuggest(input) {
        if (!input || input.dataset.autosuggestBound === '1') return;
        input.dataset.autosuggestBound = '1';
        // Wrapper (jeśli rodzic nie jest relative, zawijamy)
        var parent = input.parentElement;
        if (getComputedStyle(parent).position === 'static') {
            parent.style.position = 'relative';
        }
        var dropdown = document.createElement('div');
        dropdown.className = 'autosuggest-dropdown';
        dropdown.style.display = 'none';
        dropdown.style.left = '0';
        dropdown.style.right = '0';
        dropdown.style.minWidth = '0';
        dropdown.style.top = '100%';
        parent.appendChild(dropdown);

        var timer = null;
        function runQuery() {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 2) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function () {
                var center = map.getCenter();
                var cacheKey = 'as:' + q.toLowerCase();
                var cached = cacheGet(cacheKey);
                var prom = cached
                    ? Promise.resolve(cached)
                    : fetch(autoUrl + '?q=' + encodeURIComponent(q) + '&lat=' + center.lat + '&lng=' + center.lng, {
                          headers: { 'Accept': 'application/json' }
                      }).then(function (r) { return r.json(); }).then(function (d) { cacheSet(cacheKey, d); return d; });
                prom.then(function (data) {
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
                            input.value = it.label;
                            input.dataset.lat = it.lat;
                            input.dataset.lng = it.lng;
                            input.dataset.country = it.country || '';
                            input.dataset.label = it.label;
                            dropdown.style.display = 'none';
                        });
                    });
                }).catch(function () { dropdown.style.display = 'none'; });
            }, 280);
        }
        input.addEventListener('input', function () {
            // Reset zapisanych koordynat gdy user zmienia tekst
            input.dataset.lat = '';
            input.dataset.lng = '';
            runQuery();
        });
        input.addEventListener('focus', function () { if (input.value.length >= 2) runQuery(); });
        input.addEventListener('blur', function () {
            setTimeout(function () { dropdown.style.display = 'none'; }, 200);
        });
    }

    // ─── Reverse geocoding helper z cache ──────────────────────────
    function reverseGeocode(lat, lng) {
        var key = 'rg:' + lat.toFixed(4) + ',' + lng.toFixed(4);
        var cached = cacheGet(key);
        if (cached) return Promise.resolve(cached);
        return fetch(revgeocodeUrl + '?lat=' + lat + '&lng=' + lng, {
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            cacheSet(key, data);
            return data;
        });
    }

    // ─── Map click → dodaj waypoint z reverse geocode ──────────────
    map.addEventListener('tap', function (evt) {
        if (evt.target !== map) return;
        var coord = map.screenToGeo(evt.currentPointer.viewportX, evt.currentPointer.viewportY);
        if (!coord) return;
        var lastIdx = waypoints.length - 1;
        var tmpLabel = '(' + coord.lat.toFixed(5) + ', ' + coord.lng.toFixed(5) + ')';
        var newWp = { address: tmpLabel, label: tmpLabel, lat: coord.lat, lng: coord.lng, country: '', date: '' };
        var insertIdx;
        if (waypoints[lastIdx].lat == null && waypoints[lastIdx].lng == null) {
            insertIdx = lastIdx;
            waypoints[lastIdx] = newWp;
        } else if (waypoints[0].lat == null && waypoints[0].lng == null) {
            insertIdx = 0;
            waypoints[0] = newWp;
        } else {
            insertIdx = lastIdx;
            waypoints.splice(lastIdx, 0, newWp);
        }
        renderWaypoints();
        renderPinsOnMap();
        toast('<?= __('Dodano przystanek z mapy') ?>', 'success');
        // Async reverse geocode → uzupełnij adres + flagę
        reverseGeocode(coord.lat, coord.lng).then(function (data) {
            if (!data || !data.label) return;
            if (waypoints[insertIdx]) {
                waypoints[insertIdx].address = data.label;
                waypoints[insertIdx].label = data.label;
                waypoints[insertIdx].country = data.country || '';
                renderWaypoints();
                renderPinsOnMap();
            }
        }).catch(function () {});
    });

    document.getElementById('btn-add-waypoint').addEventListener('click', function () {
        var lastIdx = waypoints.length - 1;
        waypoints.splice(lastIdx, 0, { address: '', lat: null, lng: null, country: '', date: '' });
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
        if (distMarkerGroup) { map.removeObject(distMarkerGroup); distMarkerGroup = null; }
        if (borderGroup) { map.removeObject(borderGroup); borderGroup = null; }
    }

    function highlightAltOnMap(altIdx, on) {
        routeGroups.forEach(function (g) {
            var d = g.getData() || {};
            if (d.altIdx !== altIdx) return;
            g.getObjects().forEach(function (o, i) {
                var s = o.getStyle();
                if (on) {
                    // outline (parzysty index 0) zostaje biały; stroke (nieparzysty 1) na pomarańczowy
                    if (i % 2 === 1) {
                        o.setStyle({ strokeColor: '#f59e0b', lineWidth: (d.origWidth - 2) });
                    } else {
                        o.setStyle({ strokeColor: 'rgba(255,255,255,1)', lineWidth: d.origWidth + 2 });
                    }
                } else {
                    if (i % 2 === 1) {
                        o.setStyle({ strokeColor: d.origColor, lineWidth: d.origWidth - 4 });
                    } else {
                        o.setStyle({ strokeColor: 'rgba(255,255,255,.7)', lineWidth: d.origWidth });
                    }
                }
            });
        });
    }
    function drawRoute(routeData, opts) {
        opts = opts || {};
        var group = new H.map.Group();
        var lineWidth = opts.lineWidth || 10;
        var strokeColor = opts.color || 'rgba(37,99,235,.92)';
        var outlineColor = opts.outline || 'rgba(255,255,255,.95)';
        var isMain = (opts.altIdx === 0); // tylko główna trasa jest "draggable"
        (routeData.polylines || []).forEach(function (polyStr) {
            try {
                var line = H.geo.LineString.fromFlexiblePolyline(polyStr);
                var outline = new H.map.Polyline(line, { style: { strokeColor: outlineColor, lineWidth: lineWidth } });
                var stroke = new H.map.Polyline(line, { style: { strokeColor: strokeColor, lineWidth: lineWidth - 4 } });
                // Main route: mark stroke as draggable target
                if (isMain) {
                    stroke.setData({ kind: 'route-stroke' });
                    stroke.getStyle().cursor = 'grab';
                }
                group.addObject(outline);
                group.addObject(stroke);
            } catch (e) { console.error('Polyline decode failed', e); }
        });
        group.setData({ altIdx: opts.altIdx, origColor: strokeColor, origWidth: lineWidth });
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

    // Wyświetla informacje o zsumowanym zestawie (ciągnik + naczepa) + ostrzeżenia backendu
    function renderCombinationInfo(combo) {
        var box = document.getElementById('combination-info');
        if (!box) {
            // Wstrzyknij kontener przed statystykami jeśli nie istnieje
            var statsWrap = document.getElementById('stats-bar');
            if (!statsWrap) return;
            box = document.createElement('div');
            box.id = 'combination-info';
            box.className = 'mb-2';
            statsWrap.parentNode.insertBefore(box, statsWrap);
        }
        if (!combo || (!combo.trailer_name && !combo.warnings?.length)) {
            box.innerHTML = '';
            return;
        }
        var html = '';
        // Warningi z backendu (A4)
        if (combo.warnings && combo.warnings.length) {
            html += '<div class="alert alert-warning py-2 small mb-2">';
            html += '<i class="ri-alert-line me-1"></i><strong>Uwaga do zestawu:</strong>';
            html += '<ul class="mb-0 mt-1">';
            combo.warnings.forEach(function(w) {
                html += '<li>' + escapeHtml(w) + '</li>';
            });
            html += '</ul></div>';
        }
        // Info o zsumowanych parametrach (transparent dla usera co idzie do HERE)
        if (combo.trailer_name || combo.combined_from) {
            var parts = [];
            if (combo.total_axle_count)      parts.push('<strong>' + combo.total_axle_count + ' osi</strong>');
            if (combo.total_gross_weight_kg) parts.push((combo.total_gross_weight_kg / 1000).toFixed(1) + 't DMC');
            if (combo.total_length_cm)       parts.push('L=' + (combo.total_length_cm / 100).toFixed(2) + 'm');
            if (combo.total_width_cm)        parts.push('w=' + (combo.total_width_cm / 100).toFixed(2) + 'm');
            if (combo.total_height_cm)       parts.push('h=' + (combo.total_height_cm / 100).toFixed(2) + 'm');
            var trailerLabel = combo.trailer_name ? ' + naczepa "' + escapeHtml(combo.trailer_name) + '"' : '';
            html += '<div class="alert alert-info py-2 small mb-2">';
            html += '<i class="ri-truck-line me-1"></i>';
            html += '<strong>Zestaw łącznie</strong>' + trailerLabel + ': ' + parts.join(' · ');
            if (combo.combined_from) {
                var f = combo.combined_from;
                html += '<div class="text-muted mt-1" style="font-size:.7rem">';
                html += 'Ciągnik: ' + (f.vehicle?.axle_count ?? 0) + ' osi + '
                      + ((f.vehicle?.gross_weight_kg ?? 0) / 1000).toFixed(1) + 't · ';
                html += 'Naczepa: ' + (f.trailer?.axle_count ?? 0) + ' osi + '
                      + ((f.trailer?.gross_weight_kg ?? 0) / 1000).toFixed(1) + 't';
                html += '</div>';
            }
            html += '</div>';
        }
        box.innerHTML = html;
    }

    function renderResult(data, animate) {
        lastResponse = data;
        activeAltIdx = 0;
        clearRoutes();
        if (!data.routes || !data.routes.length) return;

        data.routes.forEach(function (r, idx) {
            if (idx === 0) return;
            drawRoute(r, { color: 'rgba(148,163,184,.7)', outline: 'rgba(255,255,255,.7)', lineWidth: 8, altIdx: idx });
        });
        drawRoute(data.routes[0], { color: 'rgba(37,99,235,.95)', lineWidth: 11, animate: !!animate, altIdx: 0 });

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

        var extra = { eur_pln_rate: data.eur_pln_rate || null, ai_price: data.ai_price || null };
        renderStatsBar(data.routes[0], extra);
        renderCombinationInfo(data.combination);
        renderAlternatives(data.routes);
        renderDirections(data.routes[0]);
        renderLegs(data.routes[0], data.points || []);
        renderDistanceMarkers(data.routes[0]);
        renderBorderCrossings(data.routes[0], data.points || []);
        checkRestLawCompliance(data.routes[0]);
        renderEtaBadges(data.routes[0]);
        renderTollsBreakdown(data.routes[0]);
        renderTruckBans(data.routes[0], data.points || []);
        renderPostedWorkers(data.routes[0], data.points || []);
        renderCabotage(data.points || []);
        fetchAndRenderWeather(data.routes[0], data.points || []);
        preparePricingHistoryPanel(data.points || []);

        // Aktywuj akcje
        document.getElementById('btn-print').disabled = false;
        document.getElementById('btn-share').disabled = false;
        var sendOfferBtn = document.getElementById('btn-send-offer');
        if (sendOfferBtn) sendOfferBtn.disabled = false;
        document.getElementById('btn-truck-pois').disabled = false;
        document.getElementById('btn-customer-offer').disabled = false;
        document.getElementById('btn-track-link').disabled = false;
        document.getElementById('btn-cmr').disabled = false;
        // Aktywuj wizualnie pozycje 'ai-needs-route' w dropdown'ie AI
        if (typeof refreshAiButtons === 'function') refreshAiButtons();
        document.getElementById('btn-save-template').disabled = false;
        enableExportButton();
    }

    // ─── Etapy między waypointami (sections) ───────────────────────
    function renderLegs(route, points) {
        var card = document.getElementById('legs-card');
        var body = document.getElementById('legs-body');
        if (!route.sections || route.sections.length < 1 || !points.length) {
            card.style.display = 'none';
            return;
        }
        body.innerHTML = route.sections.map(function (sect, i) {
            var fromLabel = shortLabel((points[i] || {}).label || (points[i] || {}).address || '?');
            var toLabel   = shortLabel((points[i+1] || {}).label || (points[i+1] || {}).address || '?');
            var marker = String.fromCharCode(65 + Math.min(25, i)) + ' → ' + String.fromCharCode(65 + Math.min(25, i+1));
            return '<div class="leg-row d-flex align-items-center gap-2">'
                + '<span class="leg-num">' + (i + 1) + '</span>'
                + '<div class="flex-grow-1 min-width-0">'
                +   '<div class="small fw-semibold text-truncate">' + escapeHtml(fromLabel) + ' → ' + escapeHtml(toLabel) + '</div>'
                +   '<div class="text-muted" style="font-size:.72rem">' + marker + ' · ' + fmtNum(sect.distance_km, 1) + ' km · ' + fmtDur(sect.duration_min) + '</div>'
                + '</div>'
                + '</div>';
        }).join('');
        card.style.display = '';
    }

    // ─── Distance markers wzdłuż trasy (co 100 km) ─────────────────
    function haversineKm(a, b) {
        var R = 6371;
        var toRad = function (d) { return d * Math.PI / 180; };
        var dLat = toRad(b.lat - a.lat);
        var dLng = toRad(b.lng - a.lng);
        var s = Math.sin(dLat/2);
        var t = Math.sin(dLng/2);
        var x = s*s + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * t*t;
        return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1-x));
    }
    function renderDistanceMarkers(route) {
        if (distMarkerGroup) { map.removeObject(distMarkerGroup); distMarkerGroup = null; }
        if (!route.polylines || !route.polylines.length || !route.distance_km) return;
        // Nie pokazuj dla bardzo krótkich tras
        if (route.distance_km < 60) return;
        var step = 100; // co 100 km
        if (route.distance_km > 1000) step = 200;
        if (route.distance_km > 2000) step = 500;

        // Zbuduj listę wszystkich punktów polylines
        var pts = [];
        route.polylines.forEach(function (p) {
            try {
                var ls = H.geo.LineString.fromFlexiblePolyline(p);
                for (var i = 0; i < ls.getPointCount(); i++) {
                    var pt = ls.extractPoint(i);
                    pts.push({ lat: pt.lat, lng: pt.lng });
                }
            } catch (e) {}
        });
        if (pts.length < 2) return;

        distMarkerGroup = new H.map.Group();
        var cum = 0;
        var nextMark = step;
        for (var i = 1; i < pts.length && nextMark < route.distance_km; i++) {
            var d = haversineKm(pts[i-1], pts[i]);
            while (cum + d >= nextMark && nextMark < route.distance_km) {
                var ratio = (nextMark - cum) / d;
                var lat = pts[i-1].lat + (pts[i].lat - pts[i-1].lat) * ratio;
                var lng = pts[i-1].lng + (pts[i].lng - pts[i-1].lng) * ratio;
                var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="56" height="22" viewBox="0 0 56 22">'
                        + '<rect x="1" y="1" width="54" height="20" rx="10" fill="white" stroke="#2563eb" stroke-width="2"/>'
                        + '<text x="28" y="15" text-anchor="middle" font-family="sans-serif" font-size="11" font-weight="700" fill="#1e3a8a">'
                        + nextMark + ' km</text></svg>';
                var icon = new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 28, y: 11 } });
                distMarkerGroup.addObject(new H.map.Marker({ lat: lat, lng: lng }, { icon: icon }));
                nextMark += step;
            }
            cum += d;
        }
        if (distMarkerGroup.getObjects().length > 0) {
            map.addObject(distMarkerGroup);
        }
    }

    // ─── Border crossings (zmiana kraju w sections) ───────────────────
    var borderWaitTimes = {
        // (cc1,cc2) → minuty oczekiwania (heurystyka)
        'PL-UA': 240, 'UA-PL': 240, 'PL-BY': 180, 'BY-PL': 180,
        'PL-RU': 480, 'RU-PL': 480, 'LT-BY': 180, 'BY-LT': 180,
        'PL-DE': 5,   'DE-PL': 5,   'PL-CZ': 5,   'CZ-PL': 5,
        'PL-SK': 5,   'SK-PL': 5,   'PL-LT': 5,   'LT-PL': 5,
    };
    function ccPair(a, b) { return a + '-' + b; }
    function renderBorderCrossings(route, points) {
        if (borderGroup) { map.removeObject(borderGroup); borderGroup = null; }
        if (!points || points.length < 2) return;
        // Pary z różnych krajów — szacujemy granicę w punkcie środkowym między sąsiednimi waypointami
        borderGroup = new H.map.Group();
        for (var i = 1; i < points.length; i++) {
            var a = points[i-1], b = points[i];
            var ca = (a.country || '').toUpperCase();
            var cb = (b.country || '').toUpperCase();
            if (!ca || !cb || ca === cb) continue;
            // Konwersja ISO 3166-1 alpha-3 → alpha-2 (uproszczona)
            var iso3to2 = { POL:'PL', DEU:'DE', CZE:'CZ', SVK:'SK', UKR:'UA', LTU:'LT', LVA:'LV', BLR:'BY', RUS:'RU', AUT:'AT', HUN:'HU' };
            var c2a = iso3to2[ca] || ca.substring(0,2);
            var c2b = iso3to2[cb] || cb.substring(0,2);
            var midLat = (a.lat + b.lat) / 2;
            var midLng = (a.lng + b.lng) / 2;
            var wait = borderWaitTimes[ccPair(c2a, c2b)] || 30;
            var fa = flagEmoji(ca), fb = flagEmoji(cb);
            var label = fa + '→' + fb;
            var subtitle = '~' + (wait >= 60 ? Math.round(wait/60) + 'h' : wait + ' min');
            var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="70" height="42" viewBox="0 0 70 42">'
                    + '<defs><filter id="bsh"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-opacity=".25"/></filter></defs>'
                    + '<rect x="1" y="1" width="68" height="40" rx="8" fill="white" stroke="#dc2626" stroke-width="2" filter="url(#bsh)"/>'
                    + '<text x="35" y="18" text-anchor="middle" font-family="sans-serif" font-size="14">' + label + '</text>'
                    + '<text x="35" y="33" text-anchor="middle" font-family="sans-serif" font-size="10" font-weight="700" fill="#dc2626">' + subtitle + '</text>'
                    + '</svg>';
            var icon = new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 35, y: 21 } });
            borderGroup.addObject(new H.map.Marker({ lat: midLat, lng: midLng }, { icon: icon }));
        }
        if (borderGroup.getObjects().length > 0) {
            map.addObject(borderGroup);
        } else {
            borderGroup = null;
        }
    }

    // ─── Rest law compliance (przerwy kierowcy AETR/UE 561/2006) ──────
    function checkRestLawCompliance(r) {
        if (!r.duration_min) return;
        var hours = r.duration_min / 60;
        if (hours > 13) {
            toast('<?= __('UWAGA: trasa > 13h jazdy — niemożliwa w jednym dniu wg AETR') ?>', 'error');
        } else if (hours > 9) {
            toast('<?= __('Wymagany odpoczynek dzienny 11h — trasa > 9h jazdy') ?>', 'warning');
        } else if (hours > 4.5) {
            var breaks = Math.floor(hours / 4.5);
            toast('<?= __('Wymagana przerwa 45 min co 4.5h jazdy. Liczba przerw:') ?> ' + breaks, 'info');
        }
        // #3 — Tachograph planner: konkretne punkty postoju
        renderTachoSchedule(r);
    }

    // #3 — Tachograph planner: rozkład przerw 45min/odpoczynków 11h
    // przy konkretnych punktach trasy (lat/lng z sekcji)
    var tachoMarkersGroup = null;
    function computeTachoStops(r) {
        if (!r || !r.sections || r.sections.length === 0) return [];
        var stops = [];
        var driveMin = 0;       // czas jazdy od ostatniej przerwy
        var totalDriveMin = 0;  // czas jazdy od początku doby
        var distFromStart = 0;
        // Czas startu (departure pierwszej sekcji lub teraz)
        var t0 = r.sections[0].departure_time ? new Date(r.sections[0].departure_time) : new Date();
        var simNow = new Date(t0.getTime());

        r.sections.forEach(function (s, idx) {
            var dur = s.duration_min || 0;
            var dist = s.distance_km || 0;
            // Symulacja: w trakcie tej sekcji mogą pojawić się przerwy
            var simDriveMin = 0; // ile minut tej sekcji już przejechaliśmy
            while (simDriveMin < dur) {
                // Pozostały czas do najbliższej obowiązkowej przerwy
                var nextBreakAt = 270; // 4.5h × 60
                var nextDailyRestAt = 540; // 9h × 60
                var toBreak = nextBreakAt - driveMin;
                var toDaily = nextDailyRestAt - totalDriveMin;
                var nextStop = Math.min(toBreak, toDaily);
                var remainOfSection = dur - simDriveMin;
                if (remainOfSection < nextStop) {
                    // Dojedziemy do końca sekcji bez przerwy
                    driveMin += remainOfSection;
                    totalDriveMin += remainOfSection;
                    distFromStart += dist * (remainOfSection / dur);
                    simNow = new Date(simNow.getTime() + remainOfSection * 60000);
                    simDriveMin = dur;
                } else {
                    // W tej sekcji wpadamy w przerwę
                    driveMin += nextStop;
                    totalDriveMin += nextStop;
                    distFromStart += dist * (nextStop / dur);
                    simNow = new Date(simNow.getTime() + nextStop * 60000);
                    simDriveMin += nextStop;
                    // Interpolacja pozycji
                    var pct = simDriveMin / dur;
                    var lat = s.from_lat + (s.to_lat - s.from_lat) * pct;
                    var lng = s.from_lng + (s.to_lng - s.from_lng) * pct;
                    var stopType = totalDriveMin >= nextDailyRestAt ? 'daily11h' : 'break45';
                    stops.push({
                        type: stopType,
                        eta: new Date(simNow.getTime()),
                        distance_km: Math.round(distFromStart),
                        lat: lat, lng: lng,
                        drive_h: (totalDriveMin / 60).toFixed(1)
                    });
                    // Reset
                    if (stopType === 'daily11h') {
                        driveMin = 0; totalDriveMin = 0;
                        simNow = new Date(simNow.getTime() + 11 * 60 * 60000); // +11h
                    } else {
                        driveMin = 0;
                        simNow = new Date(simNow.getTime() + 45 * 60000); // +45 min
                    }
                }
            }
        });
        return stops;
    }

    function renderTachoSchedule(r) {
        var card = document.getElementById('tacho-card');
        var stops = computeTachoStops(r);
        if (!stops.length || (r.duration_min || 0) <= 270) {
            card.style.display = 'none';
            if (tachoMarkersGroup) { map.removeObject(tachoMarkersGroup); tachoMarkersGroup = null; }
            return;
        }
        card.style.display = '';
        var tbody = document.getElementById('tacho-tbody');
        tbody.innerHTML = '';
        stops.forEach(function (s, i) {
            var dt = s.eta.toLocaleString('pl-PL', { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
            var typeBadge = s.type === 'daily11h'
                ? '<span class="badge bg-danger-subtle text-danger border"><i class="ri-hotel-bed-line me-1"></i><?= __('Odpoczynek 11h') ?></span>'
                : '<span class="badge bg-warning-subtle text-warning border"><i class="ri-coffee-line me-1"></i><?= __('Przerwa 45 min') ?></span>';
            tbody.innerHTML +=
                '<tr>'
                + '<td class="text-muted">#' + (i + 1) + '</td>'
                + '<td>' + typeBadge + '</td>'
                + '<td>' + dt + '</td>'
                + '<td>' + s.distance_km + ' km · ' + s.drive_h + ' h jazdy</td>'
                + '<td><a href="https://maps.google.com/?q=' + s.lat.toFixed(5) + ',' + s.lng.toFixed(5) + '" target="_blank" class="text-decoration-none small">'
                + s.lat.toFixed(4) + ', ' + s.lng.toFixed(4) + ' <i class="ri-external-link-line"></i></a></td>'
                + '</tr>';
        });

        // Markery na mapie
        if (tachoMarkersGroup) { map.removeObject(tachoMarkersGroup); tachoMarkersGroup = null; }
        tachoMarkersGroup = new H.map.Group();
        stops.forEach(function (s) {
            var col = s.type === 'daily11h' ? '#dc2626' : '#f59e0b';
            var ico = s.type === 'daily11h' ? '🛏️' : '☕';
            var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="40" viewBox="0 0 32 40">'
                    + '<path d="M16 0C7 0 0 7 0 16c0 11 16 24 16 24s16-13 16-24C32 7 25 0 16 0z" fill="' + col + '" stroke="white" stroke-width="2"/>'
                    + '<circle cx="16" cy="16" r="9" fill="white"/>'
                    + '<text x="16" y="21" text-anchor="middle" font-size="12">' + ico + '</text></svg>';
            var icon = new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 16, y: 40 } });
            var m = new H.map.Marker({ lat: s.lat, lng: s.lng }, { icon: icon });
            m.setData('<strong>' + (s.type === 'daily11h' ? '<?= __('Odpoczynek 11h') ?>' : '<?= __('Przerwa 45 min') ?>') + '</strong>'
                    + '<div class="small text-muted">' + s.eta.toLocaleString('pl-PL') + '</div>'
                    + '<div class="small">' + s.distance_km + ' km od startu</div>');
            tachoMarkersGroup.addObject(m);
        });
        map.addObject(tachoMarkersGroup);
    }

    // Count-up animation dla countera (650ms)
    function animateCounter(el, targetValue, decimals, suffix) {
        var num = Number(targetValue);
        if (!isFinite(num) || num === 0) { el.textContent = '—'; return; }
        decimals = decimals || 0;
        var duration = 650;
        var start = performance.now();
        var initial = parseFloat((el.textContent || '0').replace(/[^\d.,-]/g, '').replace(',', '.')) || 0;
        function step(now) {
            var p = Math.min(1, (now - start) / duration);
            var ease = 1 - Math.pow(1 - p, 3);
            var v = initial + (num - initial) * ease;
            el.textContent = fmtNum(v, decimals);
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    // ─── ETA badges per waypoint (porównanie z datą deklarowaną) ──────
    function renderEtaBadges(route) {
        if (!route || !route.sections) return;
        var rows = waypointsEl.querySelectorAll('.waypoint-row');
        // ETA per waypoint:
        //   wp[0]   = sections[0].departure_time (start)
        //   wp[i]   = sections[i-1].arrival_time (i>=1)
        function fmtDateTime(iso) {
            try {
                var d = new Date(iso);
                if (isNaN(d.getTime())) return '';
                return d.toLocaleString('pl-PL', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
            } catch (_) { return ''; }
        }
        function fmtDeltaMin(min) {
            var abs = Math.abs(min);
            if (abs < 60) return abs + ' min';
            var h = Math.floor(abs / 60), m = abs % 60;
            return h + 'h' + (m ? ' ' + m + 'min' : '');
        }
        rows.forEach(function (row, idx) {
            var badge = row.querySelector('.wp-eta-badge');
            if (!badge) return;
            badge.style.display = 'none';
            var iso = '';
            if (idx === 0 && route.sections[0]) iso = route.sections[0].departure_time;
            else if (route.sections[idx - 1])    iso = route.sections[idx - 1].arrival_time;
            if (!iso) return;
            var etaStr = fmtDateTime(iso);
            var wp = waypoints[idx];
            // Bez deklarowanej daty — pokaż tylko sugerowaną ETA na neutralnie
            if (!wp || !wp.date) {
                badge.style.display = '';
                badge.style.background = '#eef2ff';
                badge.style.color = '#3730a3';
                badge.style.border = '1px solid #c7d2fe';
                badge.title = '<?= __('Sugerowana ETA') ?>';
                badge.innerHTML = '<i class="ri-time-line me-1"></i>ETA ' + etaStr;
                return;
            }
            // Porównaj z deklarowaną
            var declared = new Date(wp.date);
            var predicted = new Date(iso);
            if (isNaN(declared.getTime()) || isNaN(predicted.getTime())) return;
            var diffMin = Math.round((predicted - declared) / 60000);
            var abs = Math.abs(diffMin);
            badge.style.display = '';
            if (abs <= 15) {
                badge.style.background = '#dcfce7';
                badge.style.color = '#166534';
                badge.style.border = '1px solid #86efac';
                badge.innerHTML = '<i class="ri-check-line me-1"></i><?= __('Na czas') ?>';
                badge.title = '<?= __('Predykcja') ?>: ' + etaStr;
            } else if (diffMin > 0) {
                // spóźnienie
                var sev = abs > 60;
                badge.style.background = sev ? '#fee2e2' : '#fef3c7';
                badge.style.color    = sev ? '#991b1b' : '#92400e';
                badge.style.border   = '1px solid ' + (sev ? '#fca5a5' : '#fde68a');
                badge.innerHTML = '<i class="ri-alarm-warning-line me-1"></i><?= __('Spóźnienie') ?> +' + fmtDeltaMin(diffMin);
                badge.title = '<?= __('Predykcja') ?>: ' + etaStr;
            } else {
                // za wcześnie
                badge.style.background = '#dbeafe';
                badge.style.color = '#1e40af';
                badge.style.border = '1px solid #93c5fd';
                badge.innerHTML = '<i class="ri-rewind-line me-1"></i><?= __('Wcześniej') ?> −' + fmtDeltaMin(diffMin);
                badge.title = '<?= __('Predykcja') ?>: ' + etaStr;
            }
        });
    }

    // ═════════════════════════════════════════════════════════════════
    // #2 Truck POI (parkingi/stacje wzdłuż trasy) — HERE Discover
    // ═════════════════════════════════════════════════════════════════
    var truckPoiUrl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'truckPois']) ?>';
    var poiMarkersGroup = null;
    var poiVisible = false;
    var poiCache = null; // { stops: [...], polylineKey: '...' }

    function poiPolylineKey() {
        if (!lastResponse) return '';
        var r = lastResponse.routes[activeAltIdx];
        if (!r || !r.polylines) return '';
        return r.polylines.join('|').substring(0, 100); // skrócony hash
    }

    document.getElementById('btn-truck-pois').addEventListener('click', function () {
        if (!lastResponse) return;
        var r = lastResponse.routes[activeAltIdx];
        if (!r || !r.polylines || !r.polylines.length) { toast('<?= __('Brak polyline trasy.') ?>', 'warning'); return; }

        // Toggle off jeśli już widoczne
        if (poiVisible) {
            poiVisible = false;
            if (poiMarkersGroup) { map.removeObject(poiMarkersGroup); poiMarkersGroup = null; }
            updateTruckPoiBtn();
            return;
        }

        var btn = this;
        var currentKey = poiPolylineKey();
        // Cache: jeśli mieliśmy już wyniki dla tej samej trasy — pokaż
        if (poiCache && poiCache.polylineKey === currentKey) {
            renderPoiMarkers(poiCache.stops);
            poiVisible = true;
            updateTruckPoiBtn();
            return;
        }

        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= __('Szukam…') ?>';

        var fd = new FormData();
        r.polylines.forEach(function (p, idx) { fd.append('polylines[' + idx + ']', p); });
        fd.append('_csrfToken', csrf);
        fetch(truckPoiUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            if (!res.ok || !res.data.ok) {
                toast(res.data.message || '<?= __('Błąd pobierania POI.') ?>', 'error');
                return;
            }
            poiCache = { stops: res.data.stops || [], polylineKey: currentKey };
            renderPoiMarkers(poiCache.stops);
            poiVisible = true;
            toast('<?= __('Znaleziono') ?> ' + poiCache.stops.length + ' <?= __('parkingów/stacji') ?>', 'success');
        })
        .catch(function (e) { toast('<?= __('Błąd:') ?> ' + e.message, 'error'); })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = orig;
            updateTruckPoiBtn();
        });
    });

    function updateTruckPoiBtn() {
        var btn = document.getElementById('btn-truck-pois');
        if (poiVisible) {
            btn.classList.remove('btn-hero');
            btn.classList.add('btn-success');
            btn.innerHTML = '<i class="ri-truck-line me-1"></i><?= __('Ukryj parkingi') ?>';
        } else {
            btn.classList.add('btn-hero');
            btn.classList.remove('btn-success');
            btn.innerHTML = '<i class="ri-truck-line me-1"></i><?= __('Parkingi/Stacje') ?>';
        }
    }

    function renderPoiMarkers(stops) {
        if (poiMarkersGroup) { map.removeObject(poiMarkersGroup); poiMarkersGroup = null; }
        if (!stops || !stops.length) return;
        poiMarkersGroup = new H.map.Group();
        stops.forEach(function (s) {
            var col, ico;
            if (s.type === 'truck_stop') { col = '#16a34a'; ico = '🚛'; }
            else if (s.type === 'fuel_station') { col = '#3b82f6'; ico = '⛽'; }
            else if (s.type === 'parking') { col = '#8b5cf6'; ico = '🅿️'; }
            else { col = '#64748b'; ico = '📍'; }
            var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="40" viewBox="0 0 32 40">'
                    + '<path d="M16 0C7 0 0 7 0 16c0 11 16 24 16 24s16-13 16-24C32 7 25 0 16 0z" fill="' + col + '" stroke="white" stroke-width="2"/>'
                    + '<circle cx="16" cy="16" r="9" fill="white"/>'
                    + '<text x="16" y="21" text-anchor="middle" font-size="13">' + ico + '</text></svg>';
            var icon = new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 16, y: 40 } });
            var m = new H.map.Marker({ lat: s.lat, lng: s.lng }, { icon: icon });
            m.setData('<strong>' + escapeHtml(s.title || '<?= __('POI') ?>') + '</strong>'
                    + '<div class="small text-muted">' + escapeHtml(s.category || '') + '</div>'
                    + '<div class="small">' + escapeHtml(s.address || '') + '</div>'
                    + (s.distance ? '<div class="small text-muted">' + Math.round(s.distance/1000) + ' km od trasy</div>' : ''));
            poiMarkersGroup.addObject(m);
        });
        map.addObject(poiMarkersGroup);
    }

    // ═════════════════════════════════════════════════════════════════
    // #1 Pogoda po trasie (OpenWeatherMap)
    // ═════════════════════════════════════════════════════════════════
    var weatherUrl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'weather']) ?>';

    function fetchAndRenderWeather(route, points) {
        // Dla każdego waypoint pobieramy pogodę — ETA z section'ów
        if (!route || !route.sections || !points || !points.length) return;
        // Buduj listę punktów z lat/lng + data: dla punktu i, ETA z arrival_time poprzedniej sekcji
        var pts = points.map(function (p, i) {
            var iso = '';
            if (i === 0 && route.sections[0]) iso = route.sections[0].departure_time;
            else if (route.sections[i - 1]) iso = route.sections[i - 1].arrival_time;
            return { lat: p.lat, lng: p.lng, date: iso };
        });

        var fd = new FormData();
        pts.forEach(function (p, idx) {
            fd.append('points[' + idx + '][lat]', String(p.lat));
            fd.append('points[' + idx + '][lng]', String(p.lng));
            if (p.date) fd.append('points[' + idx + '][date]', p.date);
        });
        fd.append('_csrfToken', csrf);

        fetch(weatherUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
            if (!data || !data.ok || !data.weather) return;
            renderWeatherBadges(data.weather);
        })
        .catch(function (e) { console.warn('Weather fetch failed:', e); });
    }

    function renderWeatherBadges(weatherArr) {
        var rows = waypointsEl.querySelectorAll('.waypoint-row');
        rows.forEach(function (row, idx) {
            var badge = row.querySelector('.wp-weather-badge');
            if (!badge) return;
            var w = weatherArr[idx];
            if (!w) { badge.style.display = 'none'; return; }
            var iconUrl = w.icon ? 'https://openweathermap.org/img/wn/' + w.icon + '.png' : '';
            var html = '';
            if (iconUrl) html += '<img src="' + iconUrl + '" style="width:20px;height:20px;vertical-align:middle;margin-right:2px">';
            html += '<strong>' + w.temp + '°C</strong>';
            html += ' <span class="text-muted">' + escapeHtml(w.desc) + '</span>';
            if (w.rain > 0.1) html += ' · 🌧️ ' + w.rain + ' mm';
            if (w.snow > 0.1) html += ' · ❄️ ' + w.snow + ' mm';
            if (w.wind >= 8) html += ' · 💨 ' + w.wind + ' m/s';
            badge.innerHTML = html;
            badge.style.display = '';
            badge.title = w.warning || (w.desc + ' · wilg. ' + w.humidity + '% · chmury ' + w.clouds + '%');
            if (w.warning) {
                badge.style.background = '#fef3c7';
                badge.style.color = '#92400e';
                badge.style.border = '1px solid #fde68a';
            } else {
                badge.style.background = '#eff6ff';
                badge.style.color = '#1e40af';
                badge.style.border = '1px solid #bfdbfe';
            }
        });
    }

    // ═════════════════════════════════════════════════════════════════
    // #7 Cabotage tracker — detekcja w planowanej trasie + bieżący stan
    // ═════════════════════════════════════════════════════════════════
    var cabotageUrl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'cabotageStatus']) ?>';
    var cabotageSaveUrl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'cabotageSave']) ?>';
    var cabotageDeleteUrlTpl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'cabotageDelete', '__ID__']) ?>';
    var COMPANY_COUNTRY = 'POL'; // domyślne — firma zarejestrowana w PL

    // Wykryj operacje cabotage w trasie: każdy odcinek waypoint[i]→waypoint[i+1]
    // gdzie OBA punkty są w tym samym kraju, RÓŻNYM niż kraj firmy.
    function detectCabotageInRoute(points) {
        var operations = [];
        for (var i = 0; i < points.length - 1; i++) {
            var a = points[i], b = points[i + 1];
            if (!a.country || !b.country) continue;
            if (a.country !== b.country) continue;
            if (a.country === COMPANY_COUNTRY) continue;
            operations.push({
                country: a.country,
                origin: a.label || a.address || '',
                destination: b.label || b.address || '',
                segment_idx: i,
            });
        }
        return operations;
    }

    function renderCabotage(points) {
        var card = document.getElementById('cabotage-card');
        var body = document.getElementById('cabotage-body');
        var detected = detectCabotageInRoute(points);

        // Fetch historii operacji cabotage z bazy
        var vehicleId = document.getElementById('vehicle-id').value;
        var url = cabotageUrl + (vehicleId ? '?vehicle_id=' + encodeURIComponent(vehicleId) : '');
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var byCountry = (res && res.ok && res.by_country) ? res.by_country : {};
                // Dodaj wykryte operacje z bieżącej trasy do liczników (warunkowo, nie zapisane jeszcze)
                detected.forEach(function (op) {
                    if (!byCountry[op.country]) byCountry[op.country] = { count: 0, operations: [], planned: 0 };
                    byCountry[op.country].planned = (byCountry[op.country].planned || 0) + 1;
                });
                renderCabotageContent(byCountry, detected, card, body);
            })
            .catch(function () {
                // Brak danych z bazy — pokaż tylko wykryte
                var byCountry = {};
                detected.forEach(function (op) {
                    if (!byCountry[op.country]) byCountry[op.country] = { count: 0, operations: [], planned: 0 };
                    byCountry[op.country].planned = (byCountry[op.country].planned || 0) + 1;
                });
                renderCabotageContent(byCountry, detected, card, body);
            });
    }

    function renderCabotageContent(byCountry, detected, card, body) {
        var keys = Object.keys(byCountry);
        if (!keys.length) { card.style.display = 'none'; return; }
        card.style.display = '';

        var rows = '';
        keys.forEach(function (cc) {
            var info = byCountry[cc];
            var current = info.count || 0;
            var planned = info.planned || 0;
            var afterPlan = current + planned;
            var status, color, icon;
            if (afterPlan > 3)       { status = '<?= __('PRZEKROCZONY LIMIT') ?>'; color = 'danger';  icon = 'ri-alarm-warning-fill'; }
            else if (afterPlan === 3){ status = '<?= __('OSTATNIA dopuszczalna') ?>'; color = 'warning'; icon = 'ri-alert-line'; }
            else if (current >= 1)   { status = '<?= __('w trakcie serii') ?>'; color = 'info';    icon = 'ri-information-line'; }
            else                     { status = '<?= __('nowa seria') ?>';     color = 'success'; icon = 'ri-checkbox-circle-line'; }

            var alpha2 = (ISO3_TO_ISO2_WORKERS[cc] || cc.toLowerCase()).substring(0, 2);
            rows += '<div class="d-flex flex-wrap align-items-center gap-3 py-2" style="border-bottom:1px solid #e5e7eb">'
                 +   '<div style="min-width:140px">'
                 +     '<span class="fi fi-' + alpha2 + '" style="font-size:1.4em"></span> '
                 +     '<strong class="ms-1">' + escapeHtml(cc) + '</strong>'
                 +   '</div>'
                 +   '<div class="flex-grow-1">'
                 +     '<div class="small"><strong>' + current + '</strong> <?= __('w ostatnich 7 dniach') ?>'
                 +       (planned > 0 ? ' + <strong class="text-info">' + planned + '</strong> <?= __('planowane') ?>' : '')
                 +       ' / <span class="text-muted">3 <?= __('max') ?></span></div>'
                 +     '<div class="progress mt-1" style="height:6px;max-width:200px">'
                 +       '<div class="progress-bar bg-' + (current >= 3 ? 'danger' : current >= 2 ? 'warning' : 'success') + '" style="width:' + Math.min(100, current * 33.3) + '%"></div>'
                 +       (planned > 0 ? '<div class="progress-bar bg-info opacity-50" style="width:' + Math.min(100 - current * 33.3, planned * 33.3) + '%"></div>' : '')
                 +     '</div>'
                 +   '</div>'
                 +   '<div><span class="badge bg-' + color + '"><i class="' + icon + ' me-1"></i>' + status + '</span></div>'
                 + '</div>';
        });

        // Lista wykrytych operacji w bieżącej trasie z opcją "Zapisz"
        var detectedHtml = '';
        if (detected.length > 0) {
            detectedHtml = '<div class="alert alert-info py-2 mt-2 mb-0 small">'
                + '<strong><i class="ri-radar-line me-1"></i><?= __('Wykryto w planowanej trasie') ?>:</strong>';
            detected.forEach(function (op, i) {
                detectedHtml += '<div class="mt-1 d-flex justify-content-between align-items-center">'
                    + '<span><span class="badge bg-light text-dark border me-1">' + op.country + '</span> '
                    + escapeHtml(op.origin) + ' → ' + escapeHtml(op.destination) + '</span>'
                    + '<button class="btn btn-sm btn-info text-white cab-save-detected" data-idx="' + i + '"'
                    + ' data-country="' + escapeHtml(op.country) + '"'
                    + ' data-origin="' + escapeHtml(op.origin) + '"'
                    + ' data-destination="' + escapeHtml(op.destination) + '">'
                    + '<i class="ri-save-line me-1"></i><?= __('Zapisz') ?></button>'
                    + '</div>';
            });
            detectedHtml += '</div>';
        }

        body.innerHTML = rows + detectedHtml;

        // Bind save-detected handlers
        body.querySelectorAll('.cab-save-detected').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var fd = new FormData();
                fd.append('country', btn.dataset.country);
                fd.append('origin', btn.dataset.origin);
                fd.append('destination', btn.dataset.destination);
                fd.append('source', 'auto_planner');
                fd.append('operation_date', new Date().toISOString().substring(0, 10));
                if (lastResponse && lastResponse.route_search_id) fd.append('route_search_id', lastResponse.route_search_id);
                if (document.getElementById('vehicle-id').value) fd.append('vehicle_id', document.getElementById('vehicle-id').value);
                fd.append('_csrfToken', csrf);
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                fetch(cabotageSaveUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.ok) {
                        toast('<?= __('Operacja cabotage zapisana') ?>', 'success');
                        renderCabotage((lastResponse && lastResponse.points) || []);
                    } else {
                        toast(res.message || '<?= __('Błąd zapisu') ?>', 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ri-save-line me-1"></i><?= __('Zapisz') ?>';
                    }
                });
            });
        });
    }

    // Modal ręcznego dodawania operacji
    document.getElementById('btn-cabotage-add').addEventListener('click', function () {
        var modalHtml = '<div class="modal fade" id="cabotageAddModal" tabindex="-1"><div class="modal-dialog modal-md modal-dialog-centered">'
            + '<div class="modal-content" style="border-radius:14px;overflow:hidden">'
            + '<div class="modal-header" style="background:linear-gradient(135deg,#0891b2,#06b6d4);color:white">'
            + '<h5 class="modal-title"><i class="ri-add-line me-2"></i><?= __('Dodaj operację cabotage') ?></h5>'
            + '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>'
            + '<div class="modal-body">'
            + '<div class="mb-2"><label class="form-label small"><?= __('Kraj (alpha-3)') ?></label>'
            + '<select class="form-select" id="cab-country">'
            + '<option value="DEU">🇩🇪 DEU — Niemcy</option>'
            + '<option value="FRA">🇫🇷 FRA — Francja</option>'
            + '<option value="ITA">🇮🇹 ITA — Włochy</option>'
            + '<option value="AUT">🇦🇹 AUT — Austria</option>'
            + '<option value="NLD">🇳🇱 NLD — Holandia</option>'
            + '<option value="BEL">🇧🇪 BEL — Belgia</option>'
            + '<option value="ESP">🇪🇸 ESP — Hiszpania</option>'
            + '<option value="CZE">🇨🇿 CZE — Czechy</option>'
            + '<option value="SVK">🇸🇰 SVK — Słowacja</option>'
            + '<option value="HUN">🇭🇺 HUN — Węgry</option>'
            + '<option value="ROU">🇷🇴 ROU — Rumunia</option>'
            + '<option value="GBR">🇬🇧 GBR — Wielka Brytania</option>'
            + '</select></div>'
            + '<div class="mb-2"><label class="form-label small"><?= __('Data') ?></label>'
            + '<input type="date" class="form-control" id="cab-date" value="' + new Date().toISOString().substring(0, 10) + '"></div>'
            + '<div class="mb-2"><label class="form-label small"><?= __('Załadunek') ?></label>'
            + '<input type="text" class="form-control" id="cab-origin" placeholder="<?= __('Miasto załadunku') ?>"></div>'
            + '<div class="mb-2"><label class="form-label small"><?= __('Rozładunek') ?></label>'
            + '<input type="text" class="form-control" id="cab-destination" placeholder="<?= __('Miasto rozładunku') ?>"></div>'
            + '<div class="mb-2"><label class="form-label small"><?= __('Notatki') ?></label>'
            + '<input type="text" class="form-control" id="cab-notes" placeholder="<?= __('CMR nr, klient, ...') ?>"></div>'
            + '</div>'
            + '<div class="modal-footer">'
            + '<button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>'
            + '<button type="button" class="btn btn-info text-white" id="btn-cab-save"><?= __('Zapisz') ?></button>'
            + '</div></div></div></div>';
        var existing = document.getElementById('cabotageAddModal');
        if (existing) existing.remove();
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        // Autosuggest dla pól origin/destination
        attachSimpleAutosuggest(document.getElementById('cab-origin'));
        attachSimpleAutosuggest(document.getElementById('cab-destination'));
        new bootstrap.Modal(document.getElementById('cabotageAddModal')).show();
        document.getElementById('btn-cab-save').addEventListener('click', function () {
            var fd = new FormData();
            fd.append('country', document.getElementById('cab-country').value);
            fd.append('operation_date', document.getElementById('cab-date').value);
            fd.append('origin', document.getElementById('cab-origin').value);
            fd.append('destination', document.getElementById('cab-destination').value);
            fd.append('notes', document.getElementById('cab-notes').value);
            fd.append('source', 'manual');
            if (document.getElementById('vehicle-id').value) fd.append('vehicle_id', document.getElementById('vehicle-id').value);
            fd.append('_csrfToken', csrf);
            fetch(cabotageSaveUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.ok) {
                    toast('<?= __('Operacja cabotage zapisana') ?>', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('cabotageAddModal')).hide();
                    renderCabotage((lastResponse && lastResponse.points) || []);
                } else toast(res.message || '<?= __('Błąd zapisu') ?>', 'error');
            });
        });
    });

    // ═════════════════════════════════════════════════════════════════
    // #8 Posted Workers alert — kraje wymagające zgłoszenia kierowcy
    // ═════════════════════════════════════════════════════════════════
    var POSTED_WORKERS_COUNTRIES = {
        'DEU': {
            name: 'Niemcy',  alpha2: 'de',
            law: 'MiLoG (Mindestlohngesetz)',
            portal: 'https://meldeportal-mindestlohn.de/Meldeportal/login',
            min_wage: '€12,82/h (od 2024)',
            advance_h: 4,
            note: 'Zgłoszenie online ≥4h przed wjazdem. Wymagana umowa o pracę w PL. Kara: 30 000 €'
        },
        'FRA': {
            name: 'Francja', alpha2: 'fr',
            law: 'Loi Macron / SIPSI',
            portal: 'https://www.sipsi.travail.gouv.fr/',
            min_wage: '€11,65/h SMIC (od 2024)',
            advance_h: 1,
            note: 'Zgłoszenie SIPSI + przedstawiciel we Francji. Brak zgłoszenia: 4000 € kara'
        },
        'AUT': {
            name: 'Austria', alpha2: 'at',
            law: 'LSD-BG (Lohn- und Sozialdumping)',
            portal: 'https://www.formularservice.gv.at/site/lsdb/ZKO3-1_AT/0/index.html',
            min_wage: '€10–12/h wg taryf branżowych',
            advance_h: 0,
            note: 'Zgłoszenie ZKO3 najpóźniej w dniu wjazdu. Tachograf + listy płac w samochodzie. Kara: do 50 000 €'
        },
        'CHE': {
            name: 'Szwajcaria', alpha2: 'ch',
            law: 'Posted Workers Act',
            portal: 'https://www.entsendung.admin.ch/',
            min_wage: 'CHF 25/h średnio',
            advance_h: 168,
            note: 'Zgłoszenie ≥8 dni przed wjazdem. Wymagane od >8 dni rocznie. Pełne pakiety logu szczególnych. Kara: do 30 000 CHF'
        },
        'NOR': {
            name: 'Norwegia', alpha2: 'no',
            law: 'Posting of Workers Act',
            portal: 'https://www.skatteetaten.no/en/business-and-organisation/foreign/foreign-companies/foreign-employees/',
            min_wage: 'NOK 230/h w transporcie',
            advance_h: 0,
            note: 'Rejestracja Skatteetaten przed pracą. Tachograf + płaca norweska'
        },
        'ITA': {
            name: 'Włochy',  alpha2: 'it',
            law: 'Distacco transnazionale',
            portal: 'https://www.distaccoue.lavoro.gov.it/',
            min_wage: '€7–11/h zgodnie z CCNL',
            advance_h: 24,
            note: 'Zgłoszenie UNIEMENS ≥24h przed wjazdem. CCNL Trasporti'
        },
        'BEL': {
            name: 'Belgia', alpha2: 'be',
            law: 'LIMOSA',
            portal: 'https://www.international.socialsecurity.be/working_in_belgium/en/limosa.html',
            min_wage: 'wg CLA branżowych',
            advance_h: 0,
            note: 'Zgłoszenie LIMOSA — bez tego kara od 1875 €'
        },
        'NLD': {
            name: 'Holandia', alpha2: 'nl',
            law: 'WagwEU / Posting Workers Directive',
            portal: 'https://www.postedworkers.nl/',
            min_wage: '€13,27/h (od lipca 2024)',
            advance_h: 24,
            note: 'Zgłoszenie online min 1 dzień przed. Kara: do 12 000 €'
        }
    };
    var ISO3_TO_ISO2_WORKERS = { POL:'pl', DEU:'de', CZE:'cz', SVK:'sk', UKR:'ua', LTU:'lt', LVA:'lv', BLR:'by', AUT:'at', HUN:'hu', FRA:'fr', ESP:'es', ITA:'it', NLD:'nl', BEL:'be', DNK:'dk', SWE:'se', NOR:'no', FIN:'fi', GBR:'gb', IRL:'ie', CHE:'ch', ROU:'ro', BGR:'bg', GRC:'gr', PRT:'pt', SVN:'si', HRV:'hr', LUX:'lu' };

    function renderPostedWorkers(route, points) {
        var card = document.getElementById('posted-workers-card');
        var body = document.getElementById('posted-workers-body');
        if (!route || !points || points.length < 2) { card.style.display = 'none'; return; }

        // Zbieramy kraje na trasie (waypoints + tolls_by_country)
        var countriesOnRoute = new Set();
        points.forEach(function (p) { if (p.country) countriesOnRoute.add(p.country); });
        Object.keys(route.tolls_by_country || {}).forEach(function (cc) {
            if (cc.length === 2) {
                for (var iso3 in ISO3_TO_ISO2_WORKERS) {
                    if (ISO3_TO_ISO2_WORKERS[iso3] === cc.toLowerCase()) { countriesOnRoute.add(iso3); break; }
                }
            } else {
                countriesOnRoute.add(cc);
            }
        });

        // Wykluczamy kraj startu (zakładamy że firma jest tam zarejestrowana)
        var startCountry = points[0].country || '';
        countriesOnRoute.delete(startCountry);

        // Filtrujemy do tych co wymagają zgłoszenia
        var required = Array.from(countriesOnRoute).filter(function (c) { return POSTED_WORKERS_COUNTRIES[c]; });
        if (!required.length) { card.style.display = 'none'; return; }

        body.innerHTML = required.map(function (cc) {
            var info = POSTED_WORKERS_COUNTRIES[cc];
            var advanceText = info.advance_h === 0
                ? '<?= __('w dniu wjazdu') ?>'
                : info.advance_h < 24 ? info.advance_h + 'h <?= __('przed') ?>'
                : Math.floor(info.advance_h / 24) + ' <?= __('dni przed') ?>';
            return '<div class="d-flex flex-wrap align-items-start gap-3 py-2" style="border-bottom:1px solid #fde68a">'
                 + '<div style="min-width:160px">'
                 +   '<span class="fi fi-' + info.alpha2 + '" style="font-size:1.5em"></span> '
                 +   '<strong class="ms-1">' + escapeHtml(info.name) + '</strong>'
                 +   '<div class="text-muted small">' + escapeHtml(info.law) + '</div>'
                 + '</div>'
                 + '<div class="flex-grow-1">'
                 +   '<div class="small"><strong><?= __('Termin') ?>:</strong> ' + advanceText + '</div>'
                 +   '<div class="small"><strong><?= __('Płaca min.') ?>:</strong> ' + escapeHtml(info.min_wage) + '</div>'
                 +   '<div class="small text-muted mt-1">' + escapeHtml(info.note) + '</div>'
                 + '</div>'
                 + '<div>'
                 +   '<a href="' + info.portal + '" target="_blank" rel="noopener" class="btn btn-sm btn-warning text-dark">'
                 +     '<i class="ri-external-link-line me-1"></i><?= __('Otwórz portal') ?>'
                 +   '</a>'
                 + '</div>'
                 + '</div>';
        }).join('');
        card.style.display = '';
    }

    // ═════════════════════════════════════════════════════════════════
    // #10 Truck ban kalendarz — niedziele i święta z zakazem >7.5t
    // ═════════════════════════════════════════════════════════════════
    // Bazowy zestaw świąt 2026 (uzupełnij w razie potrzeby co rok)
    var TRUCK_BAN_HOLIDAYS = {
        // ISO date → {name, countries: array (kraje gdzie obowiązuje)}
        '2026-01-01': { name: 'Nowy Rok',                      countries: ['DE','AT','FR','IT','CH','PL'] },
        '2026-01-06': { name: 'Trzech Króli',                  countries: ['AT','IT','PL'] },
        '2026-04-03': { name: 'Wielki Piątek',                 countries: ['DE','AT','CH'] },
        '2026-04-05': { name: 'Wielkanoc',                     countries: ['DE','AT','FR','IT','CH','PL'] },
        '2026-04-06': { name: 'Poniedziałek Wielkanocny',      countries: ['DE','AT','FR','IT','CH','PL'] },
        '2026-05-01': { name: 'Święto Pracy',                  countries: ['DE','AT','FR','IT','PL'] },
        '2026-05-03': { name: 'Konstytucja 3 Maja',            countries: ['PL'] },
        '2026-05-08': { name: 'Dzień Zwycięstwa',              countries: ['FR'] },
        '2026-05-14': { name: 'Wniebowstąpienie',              countries: ['DE','AT','FR','CH'] },
        '2026-05-24': { name: 'Zielone Świątki',               countries: ['DE','AT','FR','IT','CH'] },
        '2026-05-25': { name: 'Pon. Zielonoświątkowy',         countries: ['DE','AT','FR','CH'] },
        '2026-06-04': { name: 'Boże Ciało',                    countries: ['DE','AT','PL'] },
        '2026-07-14': { name: 'Święto Narodowe',               countries: ['FR'] },
        '2026-08-15': { name: 'Wniebowzięcie NMP',             countries: ['AT','FR','IT','PL'] },
        '2026-10-03': { name: 'Dzień Jedności Niemiec',        countries: ['DE'] },
        '2026-10-26': { name: 'Święto Narodowe Austrii',       countries: ['AT'] },
        '2026-11-01': { name: 'Wszystkich Świętych',           countries: ['AT','FR','IT','PL'] },
        '2026-11-11': { name: 'Dzień Niepodległości',          countries: ['FR','PL'] },
        '2026-12-08': { name: 'Niepokalane Poczęcie',          countries: ['AT','IT'] },
        '2026-12-25': { name: 'Boże Narodzenie',               countries: ['DE','AT','FR','IT','CH','PL'] },
        '2026-12-26': { name: 'Drugi dzień Św.',               countries: ['DE','AT','IT','CH','PL'] }
    };
    // Kraje z zakazem niedzielnym (DE/AT zazwyczaj 00-22, FR/IT 08-22)
    var SUNDAY_BAN_COUNTRIES = {
        'DEU': { name: 'Niemcy',  hours: '00–22', alpha2: 'de' },
        'AUT': { name: 'Austria', hours: '00–22', alpha2: 'at' },
        'FRA': { name: 'Francja', hours: '22(sob) – 22(niedz.)', alpha2: 'fr' },
        'ITA': { name: 'Włochy',  hours: '08–22', alpha2: 'it' },
        'CHE': { name: 'Szwajcaria', hours: '00–22', alpha2: 'ch' }
    };
    // Konwersja alpha-3 → alpha-2 dla flagi
    var ISO3_TO_ISO2 = { POL:'pl', DEU:'de', CZE:'cz', SVK:'sk', UKR:'ua', LTU:'lt', LVA:'lv', BLR:'by', AUT:'at', HUN:'hu', FRA:'fr', ESP:'es', ITA:'it', NLD:'nl', BEL:'be', DNK:'dk', SWE:'se', NOR:'no', FIN:'fi', GBR:'gb', IRL:'ie', CHE:'ch', ROU:'ro', BGR:'bg', GRC:'gr' };

    function renderTruckBans(route, points) {
        var card = document.getElementById('truckban-card');
        var body = document.getElementById('truckban-body');
        // Bez pojazdu lub trasa krótka — nie pokazuj
        var vehicle = getSelectedVehicle();
        if (!vehicle || !vehicle.gross_weight_kg || vehicle.gross_weight_kg < 7500) {
            card.style.display = 'none';
            return;
        }
        if (!route || !route.sections || !points || points.length < 2) {
            card.style.display = 'none';
            return;
        }
        // Zbieramy kraje na trasie (z waypoints + można wnioskować z tolls_by_country)
        var countriesOnRoute = new Set();
        points.forEach(function (p) { if (p.country) countriesOnRoute.add(p.country); });
        Object.keys(route.tolls_by_country || {}).forEach(function (cc) {
            if (cc.length === 2) {
                // Znajdź alpha-3
                for (var iso3 in ISO3_TO_ISO2) {
                    if (ISO3_TO_ISO2[iso3] === cc.toLowerCase()) { countriesOnRoute.add(iso3); break; }
                }
            } else {
                countriesOnRoute.add(cc);
            }
        });

        // Daty trasy: od pierwszej arrival/departure_time
        var startISO = (route.sections[0] && route.sections[0].departure_time) || null;
        var endISO   = (route.sections[route.sections.length - 1] && route.sections[route.sections.length - 1].arrival_time) || null;
        if (!startISO || !endISO) {
            card.style.display = 'none';
            return;
        }
        var start = new Date(startISO);
        var end   = new Date(endISO);

        // Iteruj dzień po dniu między start a end
        var warnings = [];
        var cur = new Date(start.getFullYear(), start.getMonth(), start.getDate());
        var endDay = new Date(end.getFullYear(), end.getMonth(), end.getDate());
        while (cur <= endDay) {
            var dow = cur.getDay(); // 0 = niedziela
            var iso = cur.toISOString().substring(0, 10);
            var dateLabel = cur.toLocaleDateString('pl-PL', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });

            // Niedziela — sprawdź kraje z banem
            if (dow === 0) {
                var bannedCountries = [];
                countriesOnRoute.forEach(function (cc) {
                    if (SUNDAY_BAN_COUNTRIES[cc]) bannedCountries.push(cc);
                });
                if (bannedCountries.length) {
                    warnings.push({
                        type: 'sunday',
                        date: dateLabel,
                        countries: bannedCountries.map(function (cc) {
                            return { code: cc, info: SUNDAY_BAN_COUNTRIES[cc] };
                        })
                    });
                }
            }

            // Święto
            var hol = TRUCK_BAN_HOLIDAYS[iso];
            if (hol) {
                var matched = [];
                hol.countries.forEach(function (cc2) {
                    // cc2 to alpha-2 — sprawdź czy mamy ten kraj na trasie (alpha-3 lub alpha-2)
                    countriesOnRoute.forEach(function (rc) {
                        if (rc === cc2) matched.push(cc2);
                        if (ISO3_TO_ISO2[rc] === cc2.toLowerCase()) matched.push(cc2);
                    });
                });
                if (matched.length) {
                    warnings.push({
                        type: 'holiday',
                        date: dateLabel,
                        name: hol.name,
                        countries: matched
                    });
                }
            }

            cur.setDate(cur.getDate() + 1);
        }

        if (!warnings.length) {
            card.style.display = 'none';
            return;
        }

        // Render
        body.innerHTML = warnings.map(function (w) {
            var icon = w.type === 'sunday' ? '<i class="ri-calendar-event-line text-danger me-1"></i>' : '<i class="ri-star-fill text-warning me-1"></i>';
            var headline = w.type === 'sunday' ? '<?= __('Zakaz niedzielny') ?>' : '<?= __('Święto') ?> · ' + escapeHtml(w.name);
            var countries = w.countries.map(function (c) {
                if (w.type === 'sunday') {
                    var iso2 = c.info.alpha2;
                    return '<span class="badge bg-danger-subtle text-danger border me-1"><span class="fi fi-' + iso2 + '"></span> ' + escapeHtml(c.info.name) + ' (' + c.info.hours + ')</span>';
                } else {
                    return '<span class="badge bg-warning-subtle text-warning-emphasis border me-1"><span class="fi fi-' + c.toLowerCase() + '"></span> ' + c.toUpperCase() + '</span>';
                }
            }).join(' ');
            return '<div class="d-flex flex-wrap align-items-center gap-2 py-2" style="border-bottom:1px solid #fee2e2">'
                 + '<div style="min-width:200px">' + icon + '<strong>' + headline + '</strong></div>'
                 + '<div style="min-width:180px">' + escapeHtml(w.date) + '</div>'
                 + '<div>' + countries + '</div>'
                 + '</div>';
        }).join('');

        card.style.display = '';
    }

    // ═════════════════════════════════════════════════════════════════
    // L1+L2+L3+L4: Szczegółowe opłaty drogowe (tolls breakdown)
    // ═════════════════════════════════════════════════════════════════
    var tollMarkersGroup = null;       // L3: grupa markerów bramek
    var tollMarkersVisible = false;
    var currentTollsData = null;       // dla CSV/PDF export

    function flagSpan(cc) {
        if (!cc) return '';
        // HERE zwraca alpha-3 (DEU/POL...) lub alpha-2 (DE/PL). Mapujemy oba na alpha-2.
        var iso3to2 = { POL:'pl', DEU:'de', CZE:'cz', SVK:'sk', UKR:'ua', LTU:'lt', LVA:'lv', BLR:'by', AUT:'at', HUN:'hu', FRA:'fr', ESP:'es', ITA:'it', NLD:'nl', BEL:'be', DNK:'dk', SWE:'se', NOR:'no', FIN:'fi', GBR:'gb', IRL:'ie', CHE:'ch', ROU:'ro', BGR:'bg', GRC:'gr', PRT:'pt', SVN:'si', HRV:'hr', LUX:'lu', RUS:'ru', MDA:'md', SRB:'rs', MKD:'mk', ALB:'al', BIH:'ba', MNE:'me' };
        var c2 = (cc.length === 3) ? (iso3to2[cc.toUpperCase()] || '') : cc.toLowerCase();
        if (!c2) return escapeHtml(cc);
        return '<span class="fi fi-' + c2 + '" title="' + escapeHtml(cc) + '"></span> <span class="text-muted small">' + escapeHtml(cc.toUpperCase()) + '</span>';
    }

    function paymentBadge(method) {
        if (!method) return '';
        var iconMap = {
            'cash': ['ri-coin-line', '#fbbf24', 'Gotówka'],
            'creditCard': ['ri-bank-card-line', '#3b82f6', 'Karta'],
            'bankCard': ['ri-bank-card-line', '#3b82f6', 'Karta'],
            'transponder': ['ri-radio-button-line', '#10b981', 'Transponder'],
            'videoToll': ['ri-camera-line', '#8b5cf6', 'Video'],
            'travelCard': ['ri-id-card-line', '#06b6d4', 'Karta podróżna']
        };
        return method.split(',').map(function (m) {
            m = m.trim();
            var ic = iconMap[m];
            if (!ic) return '<span class="badge bg-light text-muted border">' + escapeHtml(m) + '</span>';
            return '<span class="badge border" style="background:' + ic[1] + '15;color:' + ic[1] + ';border-color:' + ic[1] + '40 !important">'
                 + '<i class="' + ic[0] + ' me-1"></i>' + ic[2] + '</span>';
        }).join(' ');
    }

    // Normalizuj wartość emission_class do "EURO N" / "EURO EEV"
    function formatEuro(raw) {
        if (!raw) return '';
        var s = String(raw).toLowerCase().replace(/[\s_\-]+/g, '');
        if (s.includes('eev')) return 'EURO EEV';
        var m = s.match(/(?:euro?|eu|e)?([1-6])$/);
        return m ? 'EURO ' + m[1] : raw.toUpperCase();
    }

    // Klasyfikacja pojazdu per kraj (orientacyjne — HERE może mieć inną logikę)
    // Cache kategorii per typ zestawu (pobierane z /admin/vehicle-type-categories/for-type/{type}).
    // Wypełniane leniwie przez ensureTypeCategoriesLoaded(type).
    var typeCategoriesByType = {};   // { standard: [{country_code, system_name, category_label, notes}, ...], mega: [...] }
    var typeCategoriesLoading = {};  // { standard: Promise<...>, ... }

    function ensureTypeCategoriesLoaded(type) {
        if (!type) return Promise.resolve([]);
        if (typeCategoriesByType[type]) return Promise.resolve(typeCategoriesByType[type]);
        if (typeCategoriesLoading[type]) return typeCategoriesLoading[type];
        var url = '<?= $this->Url->build(['controller' => 'VehicleTypeCategories', 'action' => 'forType']) ?>/' + encodeURIComponent(type);
        typeCategoriesLoading[type] = fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { categories: [] }; })
            .then(function (data) {
                var cats = (data && data.categories) ? data.categories : [];
                typeCategoriesByType[type] = cats;
                return cats;
            })
            .catch(function () {
                typeCategoriesByType[type] = [];
                return [];
            });
        return typeCategoriesLoading[type];
    }

    // Zwraca [{system_name, category_label, notes}, ...] dla danego typu + kraju.
    // Wymaga wcześniejszego ensureTypeCategoriesLoaded(type).
    function typeCategoriesForCountry(type, countryCode) {
        var cats = typeCategoriesByType[type] || [];
        var upper = String(countryCode || '').toUpperCase();
        return cats.filter(function (c) { return String(c.country_code || '').toUpperCase() === upper; });
    }

    function vehicleTollClasses(v) {
        if (!v) return null;
        var axles = v.axle_count || 0;
        var weight = v.gross_weight_kg || 0;
        var weightT = weight / 1000;
        var euroLabel = formatEuro(v.emission_class);

        // Italia (Autostrade): A=lekkie auto, B=van 2osie<2m wys, 3=2-osi heavy/bus, 4=3-osi, 5=4+ osi
        var it;
        if (axles >= 4) it = '5 (4+ osi)';
        else if (axles === 3) it = '4 (3 osi)';
        else if (axles === 2 && weight >= 3500) it = '3 (2 osi ciężki/bus)';
        else if (axles === 2) it = 'B (2 osi ≤3,5t)';
        else it = 'A (lekkie)';

        // Francja (ASFA péages): 1=auto, 2=2-osi 2-3.5t, 3=2-osi >3.5t lub bus, 4=3+ osi ciężarówka, 5=motocykl
        var fr;
        if (axles >= 3) fr = '4 (3+ osi)';
        else if (axles === 2 && weight > 3500) fr = '3 (2 osi >3,5t/bus)';
        else if (weight > 3500) fr = '3 (≥3,5t)';
        else fr = '2 (osob./van)';

        // Niemcy (Maut): kategoria osi: Achsklasse 2, 3, 4, 5, 6+. Plus EURO class i Gewichtsklasse.
        // UWAGA: HERE zwraca stawkę per faktyczna liczba osi — 5 to 5, 6+ to 6+.
        // Nie mieszamy 5 z 6+ w display, bo cennik BAG per Achsklasse może się różnić.
        var de;
        if (axles >= 6)      de = '6+ osi (' + axles + ')';
        else if (axles === 5) de = '5 osi';
        else if (axles === 4) de = '4 osi';
        else if (axles === 3) de = '3 osi';
        else if (axles === 2) de = '2 osi';
        else de = 'brak danych osi';
        if (euroLabel) de += ' · ' + euroLabel;
        // Maut: dodatkowo kategoria masy
        if (weightT >= 18)       de += ' · Gewichtsklasse C (≥18t)';
        else if (weightT >= 7.5) de += ' · Gewichtsklasse B (7,5–18t)';
        else if (weightT >= 3.5) de += ' · Gewichtsklasse A (3,5–7,5t)';

        // Austria (GO-Box): Kategoria A1 (2-osi ≤3,5t), A2 (2-osi >3,5t), 3 (3-osi), 4+ (4+ osi)
        var at;
        if (axles >= 4) at = 'Kat. 4+ (' + axles + ' osi)';
        else if (axles === 3) at = 'Kat. 3 (3 osi)';
        else if (axles === 2 && weight > 3500) at = 'Kat. 2 (2 osi >3,5t)';
        else if (axles === 2) at = 'A1 (2 osi ≤3,5t)';
        else at = 'lekkie';

        // Polska — różne systemy w zależności od OPERATORA:
        //   A2 AWSA (Konin-Świecko): 4 kategorie — kat. 4 = 4+ osi
        //   A1 Stalexport (Toruń-Gdańsk), A4 Stalexport (Katowice-Kraków): 5 kategorii — kat. 5 = >12t 4+ osi
        //   e-TOLL (drogi krajowe + niektóre autostrady GDDKiA): kat. wg masy + EURO
        // Pokazujemy WSZYSTKIE bo trasa może iść przez różne odcinki.
        var pl = [];
        // A2 AWSA — 4 klasy
        if (axles >= 4)            pl.push('A2 AWSA: kat. 4 (' + axles + ' osi)');
        else if (axles === 3)      pl.push('A2 AWSA: kat. 3 (3 osi)');
        else if (weight >= 3500)   pl.push('A2 AWSA: kat. 3 (>3,5t 2 osi)');
        else                       pl.push('A2 AWSA: kat. 2 (osob.)');
        // A1/A4 Stalexport — 5 klas
        if (weightT > 12 && axles >= 4)  pl.push('A1·A4 Stalexport: kat. 5 (>12t · 4+ osi)');
        else if (weightT > 12)           pl.push('A1·A4 Stalexport: kat. 4 (>12t · 2–3 osi)');
        else if (weightT >= 3.5)         pl.push('A1·A4 Stalexport: kat. 3 (3,5–12t)');
        else                             pl.push('A1·A4 Stalexport: kat. 2 (≤3,5t)');
        // e-TOLL — wg masy
        var etoll;
        if (weightT >= 12)        etoll = 'e-TOLL: kat. 3 (≥12t)';
        else if (weightT >= 3.5)  etoll = 'e-TOLL: kat. 2 (3,5–12t)';
        else                      etoll = 'e-TOLL: lekki (≤3,5t, bez opłat)';
        if (euroLabel) etoll += ' · ' + euroLabel;
        pl.push(etoll);

        // Czechy (MYTO CZ) — kategoria osi + EURO. Rozdzielamy 5 od 6+ (cennik może się różnić).
        var cz;
        if (axles >= 6)     cz = '4+ (6+ osi, ' + axles + ')';
        else if (axles === 5) cz = '4 (5 osi)';
        else if (axles === 4) cz = '3 (4 osi)';
        else if (axles === 3) cz = '2 (3 osi)';
        else if (axles === 2) cz = '1 (2 osi)';
        else cz = 'brak';
        if (euroLabel) cz += ' · ' + euroLabel;

        // Szwajcaria (LSVA) — wagomierz; klasa wg EURO + masa
        var ch;
        if (weightT > 0) ch = 'LSVA · ' + weightT.toFixed(1) + 't' + (euroLabel ? ' · ' + euroLabel : '');
        else ch = 'brak';

        return { it: it, fr: fr, de: de, at: at, pl: pl, cz: cz, ch: ch };
    }

    // Sprawdza czy zestaw jest normatywny wg EU Dir. 96/53/EC.
    // Limity zależą od typu zestawu:
    //   standard/mega/fridge: 16,5 m × 2,55/2,60 m × 4,0 m × 40t × 11,5 t/oś
    //   tandem: 18,75 m × 2,55 m × 4,0 m × 40t (drawbar trailer)
    //   solo:   12,0 m × 2,55 m × 4,0 m × 40t
    //   bus:    12-15 m × 2,55 m × 4,0 m × 18t
    function vehicleOversizeStatus(v) {
        if (!v) return null;
        var issues = [];
        // Limity zależne od combination_type
        var maxLength = 1650;  // standard 16,5 m
        var maxWidth = 255;    // standard 2,55 m
        if (v.combination_type === 'tandem') maxLength = 1875;
        if (v.combination_type === 'solo')   maxLength = 1200;
        if (v.combination_type === 'fridge') maxWidth = 260; // chłodnie max 2,60 m
        if (v.combination_type === 'oversize') return []; // user świadomie wybrał

        if (v.length_cm && v.length_cm > maxLength) issues.push('długość >' + (maxLength / 100).toFixed(2) + ' m');
        if (v.width_cm && v.width_cm > maxWidth)    issues.push('szerokość >' + (maxWidth / 100).toFixed(2) + ' m');
        if (v.height_cm && v.height_cm > 400)       issues.push('wysokość >4,00 m');
        if (v.gross_weight_kg && v.gross_weight_kg > 40000) issues.push('DMC >40 t');
        if (v.axle_load_kg && v.axle_load_kg > 11500) issues.push('nacisk osi >11,5 t');
        return issues;
    }

    // Nazwa typu zestawu po polsku
    function combinationTypeLabel(t) {
        var map = {
            'standard': 'Zestaw standard',
            'mega':     'Mega',
            'fridge':   'Chłodnia',
            'tandem':   'Tandem (przyczepa drawbar)',
            'solo':     'Solo (pojedyncza ciężarówka)',
            'bus':      'Autobus',
            'oversize': 'Ponadnormatywny',
        };
        return map[t] || null;
    }

    function renderTollCategories(vehicle) {
        var btn = document.getElementById('btn-toll-categories');
        if (!vehicle) { btn.style.display = 'none'; return; }
        btn.style.display = '';
        var classes = vehicleTollClasses(vehicle);
        if (!classes) return;

        // Status normatywności (EU Dir. 96/53/EC)
        var oversize = vehicleOversizeStatus(vehicle);
        var statusHtml = '';
        if (oversize === null) {
            // brak danych
        } else if (oversize.length === 0) {
            // Określ typ zespołu na podstawie liczby osi i długości
            var combinationHint = '';
            if (vehicle.axle_count >= 4 && vehicle.length_cm >= 1200) {
                combinationHint = ' · <span class="text-muted small">zespół ciągnik + naczepa</span>';
            } else if (vehicle.axle_count >= 2 && vehicle.axle_count <= 3 && vehicle.length_cm <= 1200) {
                combinationHint = ' · <span class="text-muted small">pojedyncza ciężarówka</span>';
            }
            statusHtml = '<span class="badge bg-success-subtle text-success border me-2">'
                       + '<i class="ri-checkbox-circle-line me-1"></i>Pojazd normatywny</span>' + combinationHint;
        } else {
            statusHtml = '<span class="badge bg-warning text-dark border me-2">'
                       + '<i class="ri-alert-line me-1"></i>NIENORMATYWNY</span>'
                       + ' <span class="text-warning small">' + oversize.map(escapeHtml).join(', ') + '</span>'
                       + ' <span class="text-muted small"> — może wymagać zezwolenia / pilota</span>';
        }

        // Parametry pojazdu — tylko te które są
        var paramParts = [];
        if (vehicle.combination_type) {
            var cLabel = combinationTypeLabel(vehicle.combination_type);
            if (cLabel) paramParts.push('<span class="badge bg-primary">🚛 ' + escapeHtml(cLabel) + '</span>');
        }
        if (vehicle.axle_count) paramParts.push('<strong>' + vehicle.axle_count + ' osi</strong>');
        if (vehicle.gross_weight_kg) paramParts.push((vehicle.gross_weight_kg / 1000).toFixed(1) + 't');
        if (vehicle.axle_load_kg) paramParts.push((vehicle.axle_load_kg / 1000).toFixed(1) + 't/oś');
        if (vehicle.height_cm) paramParts.push('h=' + (vehicle.height_cm / 100).toFixed(2) + 'm');
        if (vehicle.length_cm) paramParts.push('L=' + (vehicle.length_cm / 100).toFixed(2) + 'm');
        if (vehicle.width_cm)  paramParts.push('w=' + (vehicle.width_cm / 100).toFixed(2) + 'm');
        if (vehicle.emission_class) {
            // Normalizacja zgodna z HereRoutingService::normalizeEmission()
            var ec = String(vehicle.emission_class).toLowerCase().replace(/[\s_\-]+/g, '');
            var euroNum = null;
            var m = ec.match(/(?:euro?|eu|e)?([1-6])$/);
            if (ec.includes('eev')) {
                paramParts.push('<span class="badge bg-success">EURO EEV</span>');
            } else if (m) {
                euroNum = m[1];
                paramParts.push('<span class="badge bg-success">EURO ' + euroNum + '</span>'
                    + ' <span class="text-muted small">(wysłano: <code>euro' + euroNum + '</code>)</span>');
            } else {
                paramParts.push('<span class="badge bg-warning text-dark" title="HERE nie rozpozna tego formatu">⚠ EURO ' + escapeHtml(vehicle.emission_class) + '</span>');
            }
        }
        if (vehicle.tunnel_category) paramParts.push('Tunel ' + vehicle.tunnel_category);
        if (vehicle.hazardous_goods) paramParts.push('<span class="badge bg-danger-subtle text-danger border">ADR</span>');
        if (!paramParts.length) paramParts.push('<em class="text-muted">brak danych pojazdu — HERE traktuje jako osobowy</em>');
        document.getElementById('tolls-veh-params').innerHTML = paramParts.join(' · ')
            + (statusHtml ? '<div class="mt-1">' + statusHtml + '</div>' : '');

        // Klasy per kraj — wartość może być string lub array (wtedy każdy element to osobna linia)
        var countryMap = [
            ['pl', 'Polska',     classes.pl],
            ['de', 'Niemcy',     classes.de],
            ['cz', 'Czechy',     classes.cz],
            ['at', 'Austria',    classes.at],
            ['ch', 'Szwajcaria', classes.ch],
            ['fr', 'Francja',    classes.fr],
            ['it', 'Włochy',     classes.it],
        ];

        var renderCountries = function () {
            document.getElementById('tolls-classes').innerHTML = countryMap.map(function (cc) {
                var lines = Array.isArray(cc[2]) ? cc[2] : [cc[2]];
                var linesHtml = lines.map(function (l) {
                    return '<div class="small mt-1">' + escapeHtml(l) + '</div>';
                }).join('');

                // Override z bazy — jeśli firma zdefiniowała kategorie dla tego typu+kraju,
                // pokaż je jako autorytatywne (planer je używa zamiast auto-zgadywania).
                var overrideHtml = '';
                if (vehicle.combination_type) {
                    var overrides = typeCategoriesForCountry(vehicle.combination_type, cc[0]);
                    if (overrides.length) {
                        overrideHtml = '<div class="mt-2 pt-2 border-top">'
                                     + '<div class="small text-success fw-bold">'
                                     + '<i class="ri-check-double-line me-1"></i>Zdefiniowane przez firmę</div>'
                                     + overrides.map(function (ov) {
                                         var line = '<strong>' + escapeHtml(ov.system_name) + '</strong> — '
                                                  + escapeHtml(ov.category_label);
                                         if (ov.notes) line += ' <span class="text-muted">(' + escapeHtml(ov.notes) + ')</span>';
                                         return '<div class="small text-success-emphasis">' + line + '</div>';
                                     }).join('')
                                     + '</div>';
                    }
                }

                return '<div class="border rounded p-2 d-flex flex-column" style="min-width:200px;background:white">'
                     + '<div><span class="fi fi-' + cc[0] + '"></span> <strong>' + escapeHtml(cc[1]) + '</strong></div>'
                     + linesHtml
                     + overrideHtml
                     + '</div>';
            }).join('');
        };

        // Renderuj od razu (auto-klasyfikacja), potem doładuj overrides gdy przyjdą z serwera.
        renderCountries();
        if (vehicle.combination_type) {
            ensureTypeCategoriesLoaded(vehicle.combination_type).then(function () {
                renderCountries();
            });
        }
    }

    // Toggle przycisku „Klasy pojazdu"
    (function bindTollCategoriesToggle() {
        var btn = document.getElementById('btn-toll-categories');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var sec = document.getElementById('tolls-categories');
            var visible = sec.style.display !== 'none';
            sec.style.display = visible ? 'none' : '';
            btn.classList.toggle('btn-info', !visible);
            btn.classList.toggle('btn-outline-info', visible);
            btn.classList.toggle('text-white', !visible);
        });
    })();

    function renderTollsBreakdown(route) {
        currentTollsData = null;
        var card = document.getElementById('tolls-card');
        if (!route || !route.tolls_breakdown || !route.tolls_breakdown.length) {
            card.style.display = 'none';
            return;
        }
        currentTollsData = {
            breakdown: route.tolls_breakdown,
            vignettes: route.vignettes || [],
            locations: route.toll_locations || [],
            by_country: route.tolls_by_country || {},
            total: route.tolls_total,
            currency: route.tolls_currency || 'EUR'
        };

        // Klasyfikacja pojazdu per kraj (do toggle'a)
        renderTollCategories(getSelectedVehicle());

        // Summary po nagłówku
        var summary = document.getElementById('tolls-summary');
        var fees = route.tolls_breakdown.filter(function (f) { return !f.is_vignette; });
        var vigs = route.tolls_breakdown.filter(function (f) { return f.is_vignette; });
        var sumParts = [];
        sumParts.push('· ' + fees.length + ' ' + '<?= __('opłat odcinkowych') ?>');
        if (vigs.length) sumParts.push(vigs.length + ' ' + '<?= __('winiet') ?>');
        if (currentTollsData.locations && currentTollsData.locations.length) {
            sumParts.push(currentTollsData.locations.length + ' ' + '<?= __('bramek') ?>');
        }
        summary.innerHTML = sumParts.join(' · ');

        // L2: Winiety
        var vigSec = document.getElementById('vignettes-section');
        var vigList = document.getElementById('vignettes-list');
        if (route.vignettes && route.vignettes.length) {
            vigSec.style.display = '';
            vigList.innerHTML = route.vignettes.map(function (v) {
                var validity = v.pass_validity ? ' · ' + escapeHtml(v.pass_validity) : '';
                return '<div class="card border-warning" style="min-width:200px">'
                     + '<div class="card-body py-2 px-3">'
                     + '<div class="d-flex align-items-center justify-content-between gap-2">'
                     + '<div>' + flagSpan(v.country) + ' <strong class="ms-1">' + escapeHtml(v.system || 'Winieta') + '</strong></div>'
                     + '<span class="fs-6 fw-bold text-warning">' + fmtNum(v.price, 2) + ' ' + escapeHtml(v.currency) + '</span>'
                     + '</div>'
                     + (v.name ? '<div class="text-muted small mt-1">' + escapeHtml(v.name) + '</div>' : '')
                     + (validity ? '<div class="text-muted" style="font-size:.7rem">' + escapeHtml(validity) + '</div>' : '')
                     + '</div></div>';
            }).join('');
        } else {
            vigSec.style.display = 'none';
            vigList.innerHTML = '';
        }

        // Wykryj A2 AWSA i Stalexport — pokaż info porównawcze
        var hasAwsa = route.tolls_breakdown.some(function (f) {
            return /AUTOSTRADA WIELKOPOLSKA|AWSA|A2/i.test(f.system || '');
        });
        var hasStalexport = route.tolls_breakdown.some(function (f) {
            return /STALEXPORT|A4 KAT|A1 KAT/i.test(f.system || '');
        });
        var refSection = document.getElementById('tolls-ref-rates');
        if (refSection) {
            if (hasAwsa || hasStalexport) {
                var refText = '<div class="alert alert-light border py-2 mb-2 small mx-3 mt-2">'
                    + '<strong><i class="ri-information-line me-1"></i><?= __('Stawki referencyjne dla zespołu ciężarowego 5-osi (źródło: cenniki operatorów 2024)') ?></strong>';
                if (hasAwsa) {
                    refText += '<div class="mt-1"><strong>A2 AWSA Świecko-Konin</strong> (pełna trasa ~250 km): '
                            + 'kat. 4 (2-3 osi) ≈ <strong>340 PLN</strong> · '
                            + 'kat. 5 (4+ osi) ≈ <strong>570 PLN</strong></div>';
                }
                if (hasStalexport) {
                    refText += '<div class="mt-1"><strong>A4 Stalexport Kraków-Katowice</strong> (61 km): '
                            + 'kat. 4 (2-3 osi) ≈ <strong>33 PLN</strong> · '
                            + 'kat. 5 (4+ osi) ≈ <strong>55 PLN</strong></div>';
                }
                refText += '<div class="text-muted small mt-1">Sprawdź linki do cenników w sekcji wyżej. Jeśli stawki HERE znacznie odbiegają — to może być stary cennik HERE lub inna kategoria.</div>';
                refText += '</div>';
                refSection.innerHTML = refText;
                refSection.style.display = '';
            } else {
                refSection.style.display = 'none';
            }
        }

        // L1: Tabela opłat
        var tbody = document.getElementById('tolls-tbody');
        tbody.innerHTML = '';
        var targetCurrency = currentTollsData.currency;
        route.tolls_breakdown.forEach(function (f) {
            var nameCell = escapeHtml(f.name || (f.is_vignette ? '<?= __('Winieta') ?>' : '—'));
            if (f.is_vignette) nameCell = '<i class="ri-sticker-line text-warning me-1"></i>' + nameCell;

            // Cena w docelowej walucie (target). Priorytet:
            // 1) converted_price gdy converted_curr === target
            // 2) f.price gdy f.currency === target
            // 3) original (z znakiem ostrzeżenia — brak konwersji)
            var mainPrice, mainCur, origDisplay = '';
            if (f.converted_price && f.converted_curr && f.converted_curr.toUpperCase() === targetCurrency.toUpperCase()) {
                mainPrice = f.converted_price;
                mainCur = f.converted_curr;
                origDisplay = '<div class="text-muted" style="font-size:.7rem">' + fmtNum(f.price, 2) + ' ' + escapeHtml(f.currency) + '</div>';
            } else if (f.currency && f.currency.toUpperCase() === targetCurrency.toUpperCase()) {
                mainPrice = f.price;
                mainCur = f.currency;
            } else {
                // Waluta nie zgodna z target, brak konwersji — pokaż oryginał z ostrzeżeniem
                mainPrice = f.price;
                mainCur = f.currency;
                origDisplay = '<div class="text-warning" style="font-size:.7rem" title="<?= __('Brak konwersji do') ?> ' + escapeHtml(targetCurrency) + '"><i class="ri-error-warning-line"></i></div>';
            }
            var priceCell = '<strong>' + fmtNum(mainPrice, 2) + '</strong> ' + escapeHtml(mainCur) + origDisplay;
            // Klasa HERE (vehicle_category) + pricing method
            // Dla PL/DE HERE NIE zwraca tej informacji (puste pole) — informujemy o tym
            var classCell = '';
            if (f.vehicle_category) {
                classCell = '<span class="badge bg-info-subtle text-info border">' + escapeHtml(f.vehicle_category) + '</span>';
            } else {
                classCell = '<span class="text-muted small" title="<?= __('HERE nie ujawnia kategorii dla tego operatora') ?>">— <i class="ri-question-line"></i></span>';
            }
            if (f.pricing_method) {
                var methodLabel = ({
                    'perKm': 'per km',
                    'perDay': 'dzienne',
                    'perTime': 'czasowe',
                    'flat': 'ryczałt',
                    'vignette': 'winieta'
                }[f.pricing_method]) || f.pricing_method;
                classCell += ' <span class="text-muted small">' + escapeHtml(methodLabel) + '</span>';
            }

            // Dodatkowe info pod nazwą: charged distance + discount + alternatywne opcje winiet
            var extraInfo = '';
            if (f.charged_distance_km) {
                extraInfo += '<div class="text-muted" style="font-size:.7rem"><i class="ri-roadster-line me-1"></i>' + fmtNum(f.charged_distance_km, 1) + ' km naliczonych</div>';
            }
            if (f.discount && f.discount.value) {
                extraInfo += '<div class="text-success" style="font-size:.7rem"><i class="ri-discount-percent-line me-1"></i>Rabat: ' + escapeHtml(f.discount.type) + ' −' + fmtNum(f.discount.value, 2) + '</div>';
            }
            // Alternative options dla winiet (np. NL Eurovignette ma 4 opcje 1d/7d/1m/1y)
            if (f.alternative_options && f.alternative_options.length) {
                var altList = f.alternative_options.map(function (a) {
                    var validity = a.pass_validity ? ' (' + escapeHtml(a.pass_validity) + ')' : '';
                    return fmtNum(a.price, 0) + ' ' + escapeHtml(a.currency) + validity;
                }).join(' · ');
                extraInfo += '<div class="text-muted small mt-1"><i class="ri-information-line me-1"></i><?= __('Inne opcje winiety') ?>: ' + altList + '</div>';
            }
            if (f.pass_validity) {
                extraInfo += '<div class="text-muted small"><i class="ri-time-line me-1"></i><?= __('Ważność') ?>: ' + escapeHtml(f.pass_validity) + '</div>';
            }
            if (f.fare_id) {
                extraInfo += '<div class="text-muted" style="font-size:.65rem;opacity:.6"><code>' + escapeHtml(f.fare_id) + '</code></div>';
            }

            // Override info: wyszarz wiersz jeśli ignore, badge jeśli corrected/flagged
            var rowClass = '';
            var overrideBadge = '';
            if (f.override) {
                var act = f.override.action;
                if (act === 'ignore') {
                    rowClass = ' style="opacity:.45;text-decoration:line-through"';
                    overrideBadge = '<div class="small text-muted mt-1" title="' + escapeHtml(f.override.reason || '') + '">'
                        + '<i class="ri-eye-off-line text-danger me-1"></i><?= __('WYKLUCZONA') ?>'
                        + (f.override.reason ? ' — ' + escapeHtml(f.override.reason.substring(0, 80)) : '')
                        + '</div>';
                } else if (act === 'corrected') {
                    overrideBadge = '<div class="small text-warning mt-1" title="' + escapeHtml(f.override.reason || '') + '">'
                        + '<i class="ri-edit-circle-line me-1"></i><?= __('SKORYGOWANA') ?>: '
                        + fmtNum(f.override.corrected_price, 2) + ' ' + escapeHtml(f.override.corrected_currency)
                        + (f.override.reason ? ' · ' + escapeHtml(f.override.reason.substring(0, 60)) : '')
                        + '</div>';
                } else if (act === 'flagged') {
                    overrideBadge = '<div class="small text-info mt-1" title="' + escapeHtml(f.override.reason || '') + '">'
                        + '<i class="ri-flag-line me-1"></i><?= __('OZNACZONA') ?>'
                        + (f.override.reason ? ' — ' + escapeHtml(f.override.reason.substring(0, 80)) : '')
                        + '</div>';
                }
            }

            // Dropdown menu z opcjami: wyklucz / popraw / oznacz
            var fareJsonAttr = encodeURIComponent(JSON.stringify({
                country: f.country, system: f.system, name: f.name,
                signature: f.fare_signature || '',
                price: f.price, currency: f.currency,
                override_id: f.override ? f.override.id : null,
            }));
            var actionsMenu = '<div class="dropdown">'
                + '<button class="btn btn-sm btn-link p-0 toll-actions-btn" data-bs-toggle="dropdown" data-bs-strategy="fixed">'
                + '<i class="ri-more-2-fill"></i></button>'
                + '<ul class="dropdown-menu dropdown-menu-end shadow-sm">'
                + (f.override
                    ? '<li><a class="dropdown-item small toll-override-clear" href="#" data-id="' + (f.override.id || '') + '" data-fare="' + fareJsonAttr + '">'
                      + '<i class="ri-arrow-go-back-line me-2 text-success"></i><?= __('Cofnij korektę') ?></a></li>'
                      + '<li><hr class="dropdown-divider"></li>'
                    : '')
                + '<li><a class="dropdown-item small toll-action-ignore" href="#" data-fare="' + fareJsonAttr + '">'
                + '<i class="ri-eye-off-line me-2 text-danger"></i><?= __('Wyklucz z sumy') ?></a></li>'
                + '<li><a class="dropdown-item small toll-action-correct" href="#" data-fare="' + fareJsonAttr + '">'
                + '<i class="ri-edit-circle-line me-2 text-warning"></i><?= __('Popraw cenę') ?></a></li>'
                + '<li><a class="dropdown-item small toll-action-flag" href="#" data-fare="' + fareJsonAttr + '">'
                + '<i class="ri-flag-line me-2 text-info"></i><?= __('Zgłoś jako błędną') ?></a></li>'
                + '</ul></div>';

            tbody.innerHTML +=
                '<tr' + rowClass + '>'
                + '<td>' + flagSpan(f.country) + '</td>'
                + '<td><span class="text-truncate d-inline-block" style="max-width:200px" title="' + escapeHtml(f.system) + '">' + escapeHtml(f.system || '—') + '</span></td>'
                + '<td>' + nameCell + extraInfo + overrideBadge + '</td>'
                + '<td>' + classCell + '</td>'
                + '<td class="text-end">' + priceCell + '</td>'
                + '<td>' + paymentBadge(f.payment_method) + '</td>'
                + '<td class="text-end">' + actionsMenu + '</td>'
                + '</tr>';
        });

        // Bind menu handlers (event delegation żeby działało dla nowo wyrenderowanych)
        bindTollOverrideHandlers();

        // Footer: suma per country + total
        var tfoot = document.getElementById('tolls-tfoot');
        var countryRows = Object.keys(route.tolls_by_country || {}).map(function (cc) {
            return '<tr><td>' + flagSpan(cc) + '</td>'
                 + '<td colspan="3" class="text-muted">' + '<?= __('Suma') ?> ' + escapeHtml(cc) + '</td>'
                 + '<td class="text-end">' + fmtNum(route.tolls_by_country[cc], 2) + ' ' + escapeHtml(route.tolls_currency || 'EUR') + '</td>'
                 + '<td colspan="2"></td></tr>';
        }).join('');
        tfoot.innerHTML = countryRows
            + '<tr style="border-top:2px solid #e5e7eb">'
            + '<td colspan="4" class="text-end"><?= __('RAZEM') ?></td>'
            + '<td class="text-end fs-6">' + fmtNum(route.tolls_total || 0, 2) + ' ' + escapeHtml(route.tolls_currency || 'EUR') + '</td>'
            + '<td colspan="2"></td></tr>';

        card.style.display = '';

        // L3: przycisk zawsze widoczny — OSM Overpass zwykle znajdzie bramki nawet gdy HERE nie zwrócił
        // (HERE rzadko zwraca tollLocations; OSM ma barrier=toll_booth dla większości autostrad EU)
        if (tollMarkersVisible) renderTollMarkers(true);
    }

    // ═════════════════════════════════════════════════════════════════
    // Historia stawek klienta (Fala 2A)
    // Cascade query przez RoutePlannerController::pricingHistory:
    //   POZIOM 1: klient + oba miasta LIKE
    //   POZIOM 2: klient + oba kraje + jedno miasto
    //   POZIOM 3: klient + oba kraje (dla dowolnego miasta)
    // ═════════════════════════════════════════════════════════════════
    var pricingHistoryUrl = <?= json_encode($pricingHistoryUrl) ?>;
    var lastCurrentRoutePrice = null; // do porownania dla dumping alertu

    function preparePricingHistoryPanel(points) {
        var card = document.getElementById('pricing-history-card');
        if (!points || points.length < 2) { card.style.display = 'none'; return; }
        card.style.display = '';

        // Wyciagnij pierwszy i ostatni waypoint jako from/to
        var first = points[0];
        var last  = points[points.length - 1];
        var fromCity    = extractCity(first.address || first.label || '');
        var toCity      = extractCity(last.address  || last.label  || '');
        var fromCountry = (first.country || '').toUpperCase().substring(0, 2);
        var toCountry   = (last.country  || '').toUpperCase().substring(0, 2);

        // Zapisz do dataset przycisku zeby fetch mogl je uzyc
        var btn = document.getElementById('btn-pricing-history-fetch');
        btn.dataset.fromCity    = fromCity;
        btn.dataset.toCity      = toCity;
        btn.dataset.fromCountry = fromCountry;
        btn.dataset.toCountry   = toCountry;

        // Wyczysc poprzedni wynik
        document.getElementById('pricing-history-body').style.display = 'none';
        document.getElementById('pricing-history-empty').style.display = 'none';
        document.getElementById('pricing-history-error').style.display = 'none';
        document.getElementById('pricing-history-summary').textContent = fromCity + ' → ' + toCity;
    }

    function extractCity(address) {
        // Prosta heurystyka: pierwszy segment po zip lub pierwsze slowo jako miasto
        if (!address) return '';
        // Prefer format: "12345 Miasto, Ulica"  albo  "Miasto"  albo  "Ulica, Miasto, Kraj"
        var s = String(address).trim();
        // Odetnij kod pocztowy z przodu jesli jest
        s = s.replace(/^\d{2}[-\s]?\d{3}\s+/, ''); // PL: 30-552 Krakow
        s = s.replace(/^\d{5}\s+/, '');            // DE/inne: 12345 Berlin
        // Wez pierwszy segment przed przecinkiem
        var parts = s.split(',');
        return parts[0].trim();
    }

    // Toggle: enable/disable NIP field based on mode
    document.querySelectorAll('input[name="pricing-history-mode"]').forEach(function (r) {
        r.addEventListener('change', function () {
            var isClient = document.getElementById('mode-client').checked;
            var nipInput = document.getElementById('pricing-history-nip');
            nipInput.disabled = !isClient;
            nipInput.placeholder = isClient ? 'np. 5271234567' : '<?= __('cały rynek') ?>';
            if (!isClient) nipInput.value = '';
        });
    });

    document.getElementById('btn-pricing-history-fetch').addEventListener('click', function () {
        var btn = this;
        var mode = document.querySelector('input[name="pricing-history-mode"]:checked').value;
        var nip = (document.getElementById('pricing-history-nip').value || '').replace(/\D+/g, '');

        if (mode === 'client' && nip.length < 5) {
            showPricingHistoryError('<?= __('Podaj NIP klienta (min. 5 cyfr) lub przełącz tryb na Rynek.') ?>');
            return;
        }

        var fd = new FormData();
        if (mode === 'client') fd.append('contractor_nip', nip);
        fd.append('from_city',    btn.dataset.fromCity    || '');
        fd.append('to_city',      btn.dataset.toCity      || '');
        fd.append('from_country', btn.dataset.fromCountry || '');
        fd.append('to_country',   btn.dataset.toCountry   || '');

        document.getElementById('pricing-history-loading').style.display = 'block';
        document.getElementById('pricing-history-body').style.display = 'none';
        document.getElementById('pricing-history-empty').style.display = 'none';
        document.getElementById('pricing-history-error').style.display = 'none';

        fetch(pricingHistoryUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
            body: fd
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                document.getElementById('pricing-history-loading').style.display = 'none';
                if (!res.ok) {
                    showPricingHistoryError(res.message || '<?= __('Nie udało się pobrać historii.') ?>');
                    return;
                }
                renderPricingHistoryResult(res);
            })
            .catch(function (e) {
                document.getElementById('pricing-history-loading').style.display = 'none';
                showPricingHistoryError((e && e.message) || '<?= __('Błąd sieci.') ?>');
            });
    });

    function showPricingHistoryError(msg) {
        var el = document.getElementById('pricing-history-error');
        el.textContent = msg;
        el.style.display = '';
    }

    function renderPricingHistoryResult(res) {
        if (!res.orders || !res.orders.length) {
            document.getElementById('pricing-history-empty').style.display = '';
            return;
        }
        document.getElementById('pricing-history-body').style.display = '';

        var matchInfo = document.getElementById('pricing-history-match-info');
        var matchColor = res.match_level === 1 ? 'text-success' : (res.match_level === 2 ? 'text-info' : 'text-warning');
        matchInfo.className = 'small ' + matchColor;
        matchInfo.innerHTML = '<strong>' + escapeHtml(res.match_label) + '</strong>' +
            (res.query ? ' · <?= __('Trasa') ?>: ' + escapeHtml(res.query.from || '') + ' → ' + escapeHtml(res.query.to || '') : '');

        // Statystyki
        var stats = res.stats;
        var statsBox = document.getElementById('pricing-history-stats');
        if (stats && stats.count > 0) {
            statsBox.innerHTML =
                '<div class="row g-2">' +
                '  <div class="col-md-3"><div class="card border-0 bg-light py-2 px-3"><div class="small text-muted"><?= __('Ilość zleceń') ?></div><div class="fs-5 fw-bold">' + stats.count + '</div></div></div>' +
                '  <div class="col-md-3"><div class="card border-0 bg-light py-2 px-3"><div class="small text-muted"><?= __('Średnia (PLN)') ?></div><div class="fs-5 fw-bold text-primary">' + fmtNum(stats.avg_pln, 2) + '</div></div></div>' +
                '  <div class="col-md-3"><div class="card border-0 bg-light py-2 px-3"><div class="small text-muted"><?= __('Mediana (PLN)') ?></div><div class="fs-5 fw-bold text-primary">' + fmtNum(stats.median_pln, 2) + '</div></div></div>' +
                '  <div class="col-md-3"><div class="card border-0 bg-light py-2 px-3"><div class="small text-muted"><?= __('Zakres') ?></div><div class="fs-6 fw-medium">' + fmtNum(stats.min_pln, 0) + ' – ' + fmtNum(stats.max_pln, 0) + '</div></div></div>' +
                '</div>';
        } else {
            statsBox.innerHTML = '';
        }

        // Alert dumping — porownanie z aktualna cena (jesli mamy)
        var dumpAlert = document.getElementById('pricing-history-dumping-alert');
        if (stats && stats.median_pln > 0 && lastCurrentRoutePrice > 0) {
            var ratio = lastCurrentRoutePrice / stats.median_pln;
            if (ratio < 0.90) {
                var percent = Math.round((1 - ratio) * 100);
                dumpAlert.className = 'alert alert-danger py-2 mb-3';
                dumpAlert.innerHTML = '<i class="ri-alert-line me-1"></i>' +
                    '<strong><?= __('UWAGA') ?>:</strong> ' +
                    '<?= __('Twoja aktualna cena') ?> (' + fmtNum(lastCurrentRoutePrice, 2) + ' PLN) ' +
                    '<?= __('jest') ?> <strong>' + percent + '% ' + '<?= __('niższa od mediany historycznej') ?></strong> ' +
                    '(' + fmtNum(stats.median_pln, 2) + ' PLN). ' +
                    '<?= __('To dumping — przemyśl zanim wyślesz ofertę.') ?>';
                dumpAlert.style.display = '';
            } else if (ratio >= 0.90 && ratio <= 1.10) {
                dumpAlert.className = 'alert alert-success py-2 mb-3';
                dumpAlert.innerHTML = '<i class="ri-check-double-line me-1"></i>' +
                    '<?= __('Twoja cena jest zgodna z medianą historyczną — dobry punkt startowy.') ?>';
                dumpAlert.style.display = '';
            } else {
                dumpAlert.style.display = 'none';
            }
        } else {
            dumpAlert.style.display = 'none';
        }

        // Pokaz/ukryj kolumne "Klient" w zaleznosci od trybu
        var isMarket = res.mode === 'market';
        document.getElementById('pricing-history-th-buyer').style.display = isMarket ? '' : 'none';

        // TOP klienci (tylko w trybie market)
        var buyersBox = document.getElementById('pricing-history-buyers');
        if (isMarket && res.by_buyer && res.by_buyer.length > 0) {
            buyersBox.innerHTML = '<div class="card border-info">' +
                '<div class="card-header py-2 bg-info-subtle"><strong><i class="ri-user-star-line me-1"></i><?= __('TOP klienci na tej trasie') ?></strong></div>' +
                '<div class="card-body p-2">' +
                '<div class="table-responsive"><table class="table table-sm mb-0 align-middle" style="font-size:.82rem">' +
                '<thead class="table-light"><tr><th><?= __('Klient') ?></th><th class="text-end"><?= __('Zleceń') ?></th><th class="text-end"><?= __('Suma (PLN)') ?></th><th class="text-end"><?= __('Śr. (PLN)') ?></th></tr></thead>' +
                '<tbody>' +
                res.by_buyer.map(function (b) {
                    return '<tr>' +
                        '<td>' + escapeHtml(b.buyer_name || b.buyer_nip || '—') +
                        (b.buyer_nip ? ' <small class="text-muted">' + escapeHtml(b.buyer_nip) + '</small>' : '') +
                        '</td>' +
                        '<td class="text-end"><strong>' + b.count + '</strong></td>' +
                        '<td class="text-end">' + fmtNum(b.sum_pln, 2) + '</td>' +
                        '<td class="text-end">' + fmtNum(b.avg_pln, 2) + '</td>' +
                    '</tr>';
                }).join('') +
                '</tbody></table></div></div></div>';
            buyersBox.style.display = '';
        } else {
            buyersBox.style.display = 'none';
        }

        // Lista zlecen
        var tbody = document.getElementById('pricing-history-tbody');
        tbody.innerHTML = res.orders.map(function (o) {
            var invHtml = '<span class="text-muted">—</span>';
            var amountHtml = '<span class="text-muted">—</span>';
            if (o.invoice) {
                invHtml = '<span class="badge bg-primary-subtle text-primary">' + escapeHtml(o.invoice.fullnumber || '') + '</span>' +
                    '<div class="small text-muted mt-1">' + escapeHtml(o.invoice.date || '') + '</div>';
                if (o.invoice.total_pln) {
                    amountHtml = '<strong>' + fmtNum(o.invoice.total_pln, 2) + '</strong>';
                    if ((o.invoice.currency || '').toUpperCase() !== 'PLN') {
                        amountHtml += '<div class="small text-muted">' + fmtNum(o.invoice.total, 2) + ' ' + escapeHtml(o.invoice.currency) + '</div>';
                    }
                }
            }
            var buyerCell = isMarket
                ? '<td class="small">' + escapeHtml(o.buyer_name || o.buyer_nip || '—') +
                  (o.buyer_nip ? '<div class="text-muted" style="font-size:.7rem">NIP ' + escapeHtml(o.buyer_nip) + '</div>' : '') +
                  '</td>'
                : '';
            return '<tr>' +
                '<td class="small">' + escapeHtml(o.date_doc) + '</td>' +
                '<td class="small"><code>' + escapeHtml(o.symbol) + '</code></td>' +
                buyerCell +
                '<td class="small">' +
                    '<div>' + escapeHtml(o.from_city) + ' → ' + escapeHtml(o.to_city) + '</div>' +
                    (o.route_description ? '<div class="text-muted" style="font-size:.72rem">' + escapeHtml(o.route_description) + '</div>' : '') +
                '</td>' +
                '<td>' + invHtml + '</td>' +
                '<td class="text-end">' + amountHtml + '</td>' +
            '</tr>';
        }).join('');
    }

    // ── Toll fee overrides — learning loop (ignore / corrected / flagged) ──
    var tollOverrideSaveUrl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'tollOverrideSave']) ?>';
    var tollOverrideDeleteUrlTpl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'tollOverrideDelete', '__ID__']) ?>';

    function bindTollOverrideHandlers() {
        document.querySelectorAll('.toll-action-ignore').forEach(function (a) {
            if (a.dataset.tollBound) return;
            a.dataset.tollBound = '1';
            a.addEventListener('click', function (e) {
                e.preventDefault();
                openTollOverrideModal('ignore', JSON.parse(decodeURIComponent(a.dataset.fare)));
            });
        });
        document.querySelectorAll('.toll-action-correct').forEach(function (a) {
            if (a.dataset.tollBound) return;
            a.dataset.tollBound = '1';
            a.addEventListener('click', function (e) {
                e.preventDefault();
                openTollOverrideModal('corrected', JSON.parse(decodeURIComponent(a.dataset.fare)));
            });
        });
        document.querySelectorAll('.toll-action-flag').forEach(function (a) {
            if (a.dataset.tollBound) return;
            a.dataset.tollBound = '1';
            a.addEventListener('click', function (e) {
                e.preventDefault();
                openTollOverrideModal('flagged', JSON.parse(decodeURIComponent(a.dataset.fare)));
            });
        });
        document.querySelectorAll('.toll-override-clear').forEach(function (a) {
            if (a.dataset.tollBound) return;
            a.dataset.tollBound = '1';
            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (!confirm('<?= __('Cofnąć korektę?') ?>')) return;
                var id = a.dataset.id;
                if (!id) return;
                var fd = new FormData();
                fd.append('_csrfToken', csrf);
                fetch(tollOverrideDeleteUrlTpl.replace('__ID__', id), {
                    method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (res && res.ok) {
                        toast('<?= __('Korekta cofnięta — przeliczam') ?>', 'success');
                        setTimeout(function () { var btn = document.getElementById('btn-calc'); if (btn) btn.click(); }, 300);
                    }
                });
            });
        });
    }

    function openTollOverrideModal(action, fare) {
        var actionLabels = {
            'ignore':    { title: '<?= __('Wyklucz opłatę z sumy') ?>', color: 'danger', icon: 'ri-eye-off-line' },
            'corrected': { title: '<?= __('Popraw cenę') ?>',           color: 'warning', icon: 'ri-edit-circle-line' },
            'flagged':   { title: '<?= __('Zgłoś błędną opłatę') ?>',    color: 'info', icon: 'ri-flag-line' },
        };
        var info = actionLabels[action];
        var priceField = (action === 'corrected')
            ? '<div class="mb-2">'
              + '<label class="form-label small"><?= __('Prawidłowa cena') ?></label>'
              + '<div class="input-group input-group-sm">'
              +   '<input type="number" step="0.01" class="form-control" id="to-corrected-price" placeholder="0.00">'
              +   '<select class="form-select" id="to-corrected-currency" style="max-width:90px">'
              +     '<option value="PLN" selected>PLN</option><option value="EUR">EUR</option><option value="CZK">CZK</option><option value="CHF">CHF</option><option value="HUF">HUF</option>'
              +   '</select>'
              + '</div>'
              + '<div class="form-text small"><?= __('HERE liczy') ?>: <strong>' + fmtNum(fare.price, 2) + ' ' + escapeHtml(fare.currency) + '</strong></div>'
              + '</div>'
            : '';

        var html = '<div class="modal fade" id="tollOverrideModal" tabindex="-1">'
            + '<div class="modal-dialog modal-md modal-dialog-centered"><div class="modal-content" style="border-radius:14px;overflow:hidden">'
            + '<div class="modal-header" style="background:linear-gradient(135deg,var(--bs-' + info.color + '),var(--bs-' + info.color + '));color:white">'
            +   '<h5 class="modal-title"><i class="' + info.icon + ' me-2"></i>' + info.title + '</h5>'
            +   '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>'
            + '<div class="modal-body">'
            +   '<div class="alert alert-light border py-2 small mb-3">'
            +     '<strong>' + escapeHtml(fare.system || '') + '</strong>'
            +     (fare.name ? ' · ' + escapeHtml(fare.name) : '')
            +     '<div class="text-muted">' + escapeHtml(fare.country) + ' · HERE: ' + fmtNum(fare.price, 2) + ' ' + escapeHtml(fare.currency) + '</div>'
            +   '</div>'
            +   priceField
            +   '<div class="mb-2">'
            +     '<label class="form-label small"><?= __('Uzasadnienie / notatka') ?></label>'
            +     '<textarea class="form-control form-control-sm" rows="3" id="to-reason" placeholder="<?= __('np. AWSA cennik kat. 4 to 73 PLN za odcinek, HERE liczy za dużo') ?>"></textarea>'
            +     '<div class="form-text small text-muted">'
            +       '<i class="ri-lightbulb-line me-1 text-warning"></i>'
            +       '<?= __('Notatka pomoże nam się uczyć — kolejne kalkulacje tej opłaty automatycznie wzięte z Twojej korekty.') ?>'
            +     '</div>'
            +   '</div>'
            + '</div>'
            + '<div class="modal-footer">'
            +   '<button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>'
            +   '<button type="button" class="btn btn-' + info.color + (info.color === 'warning' ? '' : ' text-white') + '" id="btn-to-save">'
            +     '<i class="ri-save-line me-1"></i><?= __('Zapisz') ?></button>'
            + '</div></div></div></div>';
        var ex = document.getElementById('tollOverrideModal');
        if (ex) ex.remove();
        document.body.insertAdjacentHTML('beforeend', html);
        var modal = new bootstrap.Modal(document.getElementById('tollOverrideModal'));
        modal.show();

        document.getElementById('btn-to-save').addEventListener('click', function () {
            var fd = new FormData();
            fd.append('country', fare.country || '');
            fd.append('system', fare.system || '');
            fd.append('name', fare.name || '');
            fd.append('action', action);
            fd.append('original_price', String(fare.price || 0));
            fd.append('original_currency', fare.currency || '');
            if (action === 'corrected') {
                var p = parseFloat(document.getElementById('to-corrected-price').value || 0);
                if (!p) { toast('<?= __('Wpisz prawidłową cenę.') ?>', 'warning'); return; }
                fd.append('corrected_price', String(p));
                fd.append('corrected_currency', document.getElementById('to-corrected-currency').value);
            }
            fd.append('reason', document.getElementById('to-reason').value || '');
            if (lastResponse && lastResponse.route_search_id) {
                fd.append('route_search_id', lastResponse.route_search_id);
            }
            fd.append('_csrfToken', csrf);
            fetch(tollOverrideSaveUrl, {
                method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res && res.ok) {
                    toast('<?= __('Korekta zapisana — przeliczam') ?>', 'success');
                    modal.hide();
                    setTimeout(function () { var btn = document.getElementById('btn-calc'); if (btn) btn.click(); }, 300);
                } else {
                    toast(res.message || '<?= __('Błąd zapisu.') ?>', 'error');
                }
            });
        });
    }

    // ── L3: Markery bramek opłat (OSM Overpass + fallback HERE tollLocations)
    var osmTollUrl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'tollBooths']) ?>';
    var osmBoothsCache = null; // {polylineKey, booths}

    function poiPolylineKeyForTolls() {
        if (!lastResponse) return '';
        var r = lastResponse.routes[activeAltIdx];
        if (!r || !r.polylines) return '';
        return r.polylines.join('|').substring(0, 100);
    }

    function renderTollMarkers(show) {
        if (tollMarkersGroup) { map.removeObject(tollMarkersGroup); tollMarkersGroup = null; }
        if (!show) return;

        // Łączymy 2 źródła: OSM booths (właściwe) + HERE locations (gdy zwrócił)
        var hereLocations = (currentTollsData && currentTollsData.locations) || [];
        var osmBooths = (osmBoothsCache && osmBoothsCache.booths) || [];
        if (!hereLocations.length && !osmBooths.length) return;

        tollMarkersGroup = new H.map.Group();
        // Indeks orientacyjnych cen z HERE breakdown (per country+system) — dla popupu
        var priceByKey = {};
        (currentTollsData ? currentTollsData.breakdown || [] : []).forEach(function (f) {
            if (f.is_vignette) return;
            var k = (f.country || '') + '|' + (f.system || '');
            priceByKey[k] = (priceByKey[k] || 0) + (f.price || 0);
        });

        // SVG ikony per typ
        function makeIcon(color, ico) {
            var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="34" viewBox="0 0 26 34">'
                    + '<path d="M13 0C6 0 0 5 0 13c0 9 13 21 13 21s13-12 13-21C26 5 20 0 13 0z" fill="' + color + '" stroke="white" stroke-width="2"/>'
                    + '<circle cx="13" cy="13" r="8" fill="white"/>'
                    + '<text x="13" y="17" text-anchor="middle" font-size="11">' + ico + '</text></svg>';
            return new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 13, y: 34 } });
        }
        var iconBooth   = makeIcon('#dc2626', '🚧');  // czerwony — fizyczna bramka
        var iconGantry  = makeIcon('#7c3aed', '📡');  // fioletowy — bramka ETC (gantry)
        var iconHere    = makeIcon('#fbbf24', '€');   // żółty — HERE location

        // OSM booths
        osmBooths.forEach(function (b) {
            var icon = (b.type === 'toll_gantry') ? iconGantry : iconBooth;
            var m = new H.map.Marker({ lat: b.lat, lng: b.lng }, { icon: icon });
            var typeName = (b.type === 'toll_gantry')
                ? '<?= __('Bramka ETC (gantry)') ?>'
                : '<?= __('Bramka opłat') ?>';
            var details = '<div class="small">';
            if (b.operator) details += '<div class="text-muted">' + escapeHtml(b.operator) + '</div>';
            if (b.ref)      details += '<div class="text-muted">' + escapeHtml(b.ref) + '</div>';
            details += '</div>';
            m.setData('<strong>' + escapeHtml(b.name || typeName) + '</strong>' + details);
            tollMarkersGroup.addObject(m);
        });

        // HERE locations (jeśli zwróci — głównie IT/FR)
        hereLocations.forEach(function (loc) {
            var m = new H.map.Marker({ lat: loc.lat, lng: loc.lng }, { icon: iconHere });
            var k = (loc.country || '') + '|' + (loc.system || '');
            var p = priceByKey[k] ? (' · ~' + fmtNum(priceByKey[k], 2) + ' ' + (currentTollsData ? currentTollsData.currency : '')) : '';
            m.setData('<strong>' + escapeHtml(loc.name || '<?= __('Bramka') ?>') + '</strong>'
                    + '<div class="small text-muted">' + escapeHtml(loc.system || '') + ' · ' + escapeHtml(loc.country || '') + p + '</div>');
            tollMarkersGroup.addObject(m);
        });
        map.addObject(tollMarkersGroup);
    }

    // L3: toggle handler — pobiera z OSM Overpass + dolewa z HERE
    (function bindTollMarkers() {
        var btn = document.getElementById('btn-toll-markers');
        if (!btn) return;
        btn.addEventListener('click', function () {
            // Toggle off
            if (tollMarkersVisible) {
                tollMarkersVisible = false;
                renderTollMarkers(false);
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-outline-secondary');
                btn.innerHTML = '<i class="ri-map-pin-line me-1"></i><?= __('Bramki na mapie') ?>';
                return;
            }
            if (!lastResponse) return;
            var r = lastResponse.routes[activeAltIdx];
            if (!r || !r.polylines || !r.polylines.length) {
                toast('<?= __('Brak polyline trasy.') ?>', 'warning');
                return;
            }

            // Cache: jeśli mamy już booths dla tej polyline — pokaż od razu
            var currentKey = poiPolylineKeyForTolls();
            if (osmBoothsCache && osmBoothsCache.polylineKey === currentKey) {
                tollMarkersVisible = true;
                renderTollMarkers(true);
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-warning');
                btn.innerHTML = '<i class="ri-map-pin-line me-1"></i><?= __('Ukryj bramki') ?>';
                return;
            }

            // Pobierz OSM booths
            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= __('Szukam bramek…') ?>';

            var fd = new FormData();
            r.polylines.forEach(function (p, idx) { fd.append('polylines[' + idx + ']', p); });
            fd.append('_csrfToken', csrf);

            fetch(osmTollUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (!res.ok || !res.data.ok) {
                    toast(res.data.message || '<?= __('Błąd pobierania bramek.') ?>', 'error');
                    return;
                }
                osmBoothsCache = { polylineKey: currentKey, booths: res.data.booths || [] };
                tollMarkersVisible = true;
                renderTollMarkers(true);
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-warning');
                var total = osmBoothsCache.booths.length + ((currentTollsData && currentTollsData.locations) || []).length;
                if (total === 0) {
                    toast('<?= __('Brak bramek na tej trasie (autostrady bezbramkowe)') ?>', 'info');
                } else {
                    toast('<?= __('Znaleziono') ?> ' + total + ' <?= __('bramek') ?>', 'success');
                }
            })
            .catch(function (e) { toast('<?= __('Błąd:') ?> ' + e.message, 'error'); })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = tollMarkersVisible
                    ? '<i class="ri-map-pin-line me-1"></i><?= __('Ukryj bramki') ?>'
                    : '<i class="ri-map-pin-line me-1"></i><?= __('Bramki na mapie') ?>';
            });
        });
    })();

    // ── L4: Export CSV ───────────────────────────────────────────────
    (function bindTollsCsv() {
        var btn = document.getElementById('btn-tolls-csv');
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (!currentTollsData || !currentTollsData.breakdown.length) return;
            var rows = [['Kraj', 'System', 'Odcinek/opłata', 'Cena', 'Waluta', 'Przeliczona', 'Waluta przelicz.', 'Płatność', 'Winieta?', 'Ważność']];
            currentTollsData.breakdown.forEach(function (f) {
                rows.push([
                    f.country || '',
                    (f.system || '').replace(/"/g, '""'),
                    (f.name || '').replace(/"/g, '""'),
                    (f.price || 0).toFixed(2).replace('.', ','),
                    f.currency || '',
                    f.converted_price ? f.converted_price.toFixed(2).replace('.', ',') : '',
                    f.converted_curr || '',
                    f.payment_method || '',
                    f.is_vignette ? 'TAK' : 'NIE',
                    f.pass_validity || ''
                ]);
            });
            var csv = '﻿' + rows.map(function (r) {
                return r.map(function (c) { return '"' + String(c) + '"'; }).join(';');
            }).join('\n');
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'oplaty-drogowe-' + new Date().toISOString().substring(0, 10) + '.csv';
            a.click();
            URL.revokeObjectURL(url);
            toast('<?= __('CSV pobrany') ?>', 'success');
        });
    })();

    // ── L4: Export PDF (print view) ──────────────────────────────────
    (function bindTollsPdf() {
        var btn = document.getElementById('btn-tolls-pdf');
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (!currentTollsData || !currentTollsData.breakdown.length) return;
            var w = window.open('', '_blank', 'width=900,height=700');
            if (!w) { toast('<?= __('Włącz wyskakujące okna') ?>', 'warning'); return; }
            var pts = (lastResponse && lastResponse.points) || [];
            var routeLabel = pts.map(function (p) { return p.label || p.address || ''; }).filter(Boolean).join(' → ');
            var rows = currentTollsData.breakdown.map(function (f) {
                var conv = f.converted_price ? '<br><small style="color:#6b7280">≈ ' + fmtNum(f.converted_price, 2) + ' ' + f.converted_curr + '</small>' : '';
                return '<tr>'
                    + '<td>' + escapeHtml(f.country || '') + '</td>'
                    + '<td>' + escapeHtml(f.system || '—') + '</td>'
                    + '<td>' + (f.is_vignette ? '🎟️ ' : '') + escapeHtml(f.name || '—') + '</td>'
                    + '<td style="text-align:right">' + fmtNum(f.price, 2) + ' ' + escapeHtml(f.currency) + conv + '</td>'
                    + '<td>' + escapeHtml(f.payment_method || '') + '</td>'
                    + '</tr>';
            }).join('');
            var byCountryRows = Object.keys(currentTollsData.by_country).map(function (cc) {
                return '<tr><td colspan="3" style="text-align:right;color:#6b7280">Suma ' + cc + '</td>'
                     + '<td style="text-align:right"><strong>' + fmtNum(currentTollsData.by_country[cc], 2) + ' ' + currentTollsData.currency + '</strong></td><td></td></tr>';
            }).join('');
            var vigList = currentTollsData.vignettes.length
                ? '<h3 style="margin-top:24px">Wymagane winiety</h3><ul>'
                  + currentTollsData.vignettes.map(function (v) {
                      return '<li>' + escapeHtml(v.country) + ' — ' + escapeHtml(v.system || 'Winieta')
                           + ' · <strong>' + fmtNum(v.price, 2) + ' ' + escapeHtml(v.currency) + '</strong>'
                           + (v.pass_validity ? ' · ' + escapeHtml(v.pass_validity) : '') + '</li>';
                  }).join('') + '</ul>'
                : '';
            w.document.write(
                '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Opłaty drogowe</title>'
                + '<style>'
                + 'body{font-family:Arial,sans-serif;margin:30px;color:#111}'
                + 'h1{font-size:18px;margin:0 0 4px}h2{font-size:14px;color:#6b7280;font-weight:400;margin:0 0 16px}'
                + 'table{border-collapse:collapse;width:100%;font-size:11px}'
                + 'th,td{padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}'
                + 'th{background:#f3f4f6}'
                + 'tfoot td{background:#f9fafb;font-weight:600}'
                + '.total{font-size:14px;font-weight:700;color:#dc2626}'
                + '@media print{button{display:none}}'
                + '</style></head><body>'
                + '<h1>Opłaty drogowe</h1>'
                + '<h2>' + escapeHtml(routeLabel) + '</h2>'
                + '<table><thead><tr><th>Kraj</th><th>System</th><th>Odcinek</th><th style="text-align:right">Cena</th><th>Płatność</th></tr></thead>'
                + '<tbody>' + rows + '</tbody>'
                + '<tfoot>' + byCountryRows + '<tr><td colspan="3" style="text-align:right">RAZEM</td>'
                + '<td style="text-align:right" class="total">' + fmtNum(currentTollsData.total || 0, 2) + ' ' + currentTollsData.currency + '</td><td></td></tr></tfoot>'
                + '</table>'
                + vigList
                + '<p style="margin-top:30px;color:#6b7280;font-size:10px">Wygenerowane: ' + new Date().toLocaleString('pl-PL') + ' · Booklio TMS</p>'
                + '<button onclick="window.print()" style="margin-top:20px;padding:8px 16px">Drukuj / Zapisz PDF</button>'
                + '</body></html>'
            );
            w.document.close();
            setTimeout(function () { w.print(); }, 400);
        });
    })();

    function renderStatsBar(r, extra) {
        extra = extra || {};
        animateCounter(document.getElementById('stat-km'), r.distance_km, 1);
        document.getElementById('stat-dur').textContent = fmtDur(r.duration_min);

        // Tolls + EUR→PLN auto-konwersja
        var tollsPlnEl = document.getElementById('stat-tolls-pln');
        if (r.tolls_total !== null && r.tolls_total !== undefined) {
            animateCounter(document.getElementById('stat-tolls'), r.tolls_total, 2);
            document.getElementById('stat-tolls-cur').textContent = r.tolls_currency || 'EUR';
            // Konwersja: jeśli waluta EUR i mamy kurs NBP → pokaż PLN
            if ((r.tolls_currency || 'EUR') === 'EUR' && extra.eur_pln_rate) {
                var pln = r.tolls_total * extra.eur_pln_rate;
                tollsPlnEl.textContent = '≈ ' + fmtNum(pln, 2) + ' PLN  (NBP ' + fmtNum(extra.eur_pln_rate, 4) + ')';
                tollsPlnEl.style.display = 'block';
            } else {
                tollsPlnEl.style.display = 'none';
            }
        } else {
            document.getElementById('stat-tolls').textContent = '—';
            tollsPlnEl.style.display = 'none';
        }

        var cons = parseFloat(document.getElementById('fuel-consumption').value || 0);
        var price = parseFloat(document.getElementById('fuel-price').value || 0);
        var rate = parseFloat(document.getElementById('driver-rate').value || 0);
        var fuelCost = (r.distance_km / 100) * cons * price;
        var driverCost = (r.duration_min / 60) * rate;
        var liters = (r.distance_km / 100) * cons;
        var co2Kg = liters * 2.68;
        animateCounter(document.getElementById('stat-fuel'),   fuelCost, 2);
        animateCounter(document.getElementById('stat-driver'), driverCost, 2);
        animateCounter(document.getElementById('stat-co2'),    co2Kg, 1);
        document.getElementById('stats-bar').classList.add('visible');

        // Freight card
        renderFreightCard(r, extra);

        // #4 Kalkulator zysku
        renderProfitPill(r, extra, fuelCost, driverCost);
    }

    // #4 — Kalkulator zysku (pill aktualizuje się od razu przy zmianie ceny)
    function renderProfitPill(r, extra, fuelCost, driverCost) {
        var pill = document.getElementById('profit-pill');
        var revInput = document.getElementById('freight-revenue');
        var revenue = parseFloat(revInput.value || 0);
        if (!revenue || revenue <= 0 || !r || !r.distance_km) {
            pill.style.display = 'none';
            return;
        }
        // Koszt opłat w PLN
        var tollsPln = 0;
        if (r.tolls_total) {
            if ((r.tolls_currency || 'EUR') === 'PLN') {
                tollsPln = r.tolls_total;
            } else if (extra && extra.eur_pln_rate) {
                tollsPln = r.tolls_total * extra.eur_pln_rate;
            }
        }
        var totalCost = (fuelCost || 0) + (driverCost || 0) + tollsPln;
        var profit = revenue - totalCost;
        var margin = revenue > 0 ? (profit / revenue) * 100 : 0;
        var perKm = revenue / r.distance_km;

        pill.style.display = '';
        pill.classList.remove('profit-good', 'profit-fair', 'profit-bad');
        if (margin >= 15) pill.classList.add('profit-good');
        else if (margin >= 5) pill.classList.add('profit-fair');
        else pill.classList.add('profit-bad');

        animateCounter(document.getElementById('stat-profit'), profit, 0);
        var marginStr = (margin >= 0 ? '+' : '') + fmtNum(margin, 1) + '%';
        document.getElementById('stat-margin').innerHTML =
            '<i class="ri-percent-line me-1"></i>' + marginStr + '  ·  ' + fmtNum(perKm, 2) + ' PLN/km';
    }

    function renderFreightCard(r, extra) {
        var card = document.getElementById('freight-card');
        var vehicle = getSelectedVehicle();
        if (!vehicle || !vehicle.rate_per_km || !r.distance_km) {
            card.style.display = 'none';
            card.classList.remove('visible');
            return;
        }
        var rate = vehicle.rate_per_km;
        var km = r.distance_km;
        // Tolls in PLN (jeśli EUR i mamy rate)
        var tollsPln = 0;
        if (r.tolls_total) {
            if ((r.tolls_currency || 'EUR') === 'PLN') {
                tollsPln = r.tolls_total;
            } else if (extra.eur_pln_rate) {
                tollsPln = r.tolls_total * extra.eur_pln_rate;
            }
        }
        var distCost = rate * km;
        var freightTotal = distCost + tollsPln;
        animateCounter(document.getElementById('stat-freight'), freightTotal, 2);
        document.getElementById('freight-breakdown').innerHTML =
            fmtNum(rate, 2) + ' PLN/km × ' + fmtNum(km, 1) + ' km = ' + fmtNum(distCost, 2) + ' PLN'
            + (tollsPln > 0 ? '  +  ' + fmtNum(tollsPln, 2) + ' PLN <?= __('opłat') ?>' : '');

        // AI Price
        if (extra.ai_price && extra.ai_price.price > 0) {
            animateCounter(document.getElementById('stat-ai'), extra.ai_price.price, 2);
            document.getElementById('ai-basis').textContent = extra.ai_price.basis || '';
            document.getElementById('ai-price-block').style.display = '';
        } else {
            document.getElementById('ai-price-block').style.display = 'none';
        }
        card.style.display = 'block';
        setTimeout(function () { card.classList.add('visible'); }, 50);
    }
    ['fuel-consumption','fuel-price','driver-rate','freight-revenue'].forEach(function (id) {
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
                + '<div class="d-flex justify-content-between align-items-center mb-1">'
                + '<div class="d-flex align-items-center gap-2">'
                + '<input type="checkbox" class="form-check-input alt-compare-cb" data-idx="' + idx + '"'
                + (idx < 2 ? ' checked' : '') + ' onclick="event.stopPropagation()" title="<?= __('Zaznacz do porównania') ?>">'
                + '<strong class="small">' + name + '</strong></div>'
                + '<span class="badge bg-light text-dark border">' + fmtNum(r.distance_km, 1) + ' km</span></div>'
                + '<div class="d-flex justify-content-between small text-muted"><span><i class="ri-time-line"></i> ' + fmtDur(r.duration_min) + '</span>'
                + '<span><i class="ri-money-euro-circle-line"></i> ' + tolls + '</span></div></div>';
            list.insertAdjacentHTML('beforeend', html);
        });
        // Init Compare button state
        updateCompareButton();
        list.querySelectorAll('.alt-compare-cb').forEach(function (cb) {
            cb.addEventListener('change', updateCompareButton);
        });
        list.querySelectorAll('.alt-route-card').forEach(function (el) {
            el.addEventListener('mouseenter', function () {
                var idx = parseInt(el.dataset.idx, 10);
                if (idx === activeAltIdx) return;
                highlightAltOnMap(idx, true);
            });
            el.addEventListener('mouseleave', function () {
                var idx = parseInt(el.dataset.idx, 10);
                if (idx === activeAltIdx) return;
                highlightAltOnMap(idx, false);
            });
            el.addEventListener('click', function () {
                var idx = parseInt(el.dataset.idx, 10);
                activeAltIdx = idx;
                list.querySelectorAll('.alt-route-card').forEach(function (e) { e.classList.remove('active'); });
                el.classList.add('active');
                clearRoutes();
                lastResponse.routes.forEach(function (r, i) {
                    if (i === activeAltIdx) return;
                    drawRoute(r, { color: 'rgba(148,163,184,.7)', outline: 'rgba(255,255,255,.7)', lineWidth: 8, altIdx: i });
                });
                drawRoute(lastResponse.routes[activeAltIdx], { color: 'rgba(37,99,235,.95)', lineWidth: 11, altIdx: activeAltIdx });
                var extraAlt = { eur_pln_rate: lastResponse.eur_pln_rate || null, ai_price: lastResponse.ai_price || null };
                renderStatsBar(lastResponse.routes[activeAltIdx], extraAlt);
                renderDirections(lastResponse.routes[activeAltIdx]);
                renderLegs(lastResponse.routes[activeAltIdx], lastResponse.points || []);
                renderDistanceMarkers(lastResponse.routes[activeAltIdx]);
                renderBorderCrossings(lastResponse.routes[activeAltIdx], lastResponse.points || []);
            });
        });
        ac.style.display = '';
    }

    // #17 Compare mode — side-by-side porównanie wybranych alternatyw
    function updateCompareButton() {
        var btn = document.getElementById('btn-compare-routes');
        if (!btn) return;
        var checked = document.querySelectorAll('.alt-compare-cb:checked');
        btn.disabled = checked.length < 2;
        btn.title = checked.length < 2
            ? '<?= __('Zaznacz minimum 2 trasy żeby porównać') ?>'
            : '<?= __('Porównaj') ?> ' + checked.length + ' <?= __('trasy') ?>';
    }
    document.getElementById('btn-compare-routes').addEventListener('click', function () {
        var checked = Array.from(document.querySelectorAll('.alt-compare-cb:checked'))
            .map(function (cb) { return parseInt(cb.dataset.idx, 10); });
        if (checked.length < 2 || !lastResponse) return;
        renderCompareModal(checked);
    });

    function renderCompareModal(idxArr) {
        var routes = idxArr.map(function (i) { return { idx: i, route: lastResponse.routes[i] }; });
        if (routes.some(function (r) { return !r.route; })) { toast('<?= __('Brak danych trasy.') ?>', 'error'); return; }

        var cons = parseFloat(document.getElementById('fuel-consumption').value || 0);
        var price = parseFloat(document.getElementById('fuel-price').value || 0);
        var rate = parseFloat(document.getElementById('driver-rate').value || 0);
        var revenue = parseFloat(document.getElementById('freight-revenue').value || 0);
        var eurPln = lastResponse.eur_pln_rate || null;

        function calcCosts(r) {
            var fuel = (r.distance_km / 100) * cons * price;
            var driver = (r.duration_min / 60) * rate;
            var tolls = 0;
            if (r.tolls_total) {
                tolls = (r.tolls_currency === 'PLN') ? r.tolls_total
                      : (eurPln ? r.tolls_total * eurPln : r.tolls_total);
            }
            return { fuel: fuel, driver: driver, tolls: tolls, total: fuel + driver + tolls };
        }

        // Buduj rzędy tabeli
        var rows = [
            { label: '<?= __('Dystans') ?>', icon: 'ri-roadster-line', val: function (r) { return fmtNum(r.distance_km, 1) + ' km'; } },
            { label: '<?= __('Czas jazdy') ?>', icon: 'ri-time-line', val: function (r) { return fmtDur(r.duration_min); } },
            { label: '<?= __('Opłaty') ?>', icon: 'ri-money-euro-circle-line',
              val: function (r) { return r.tolls_total != null ? fmtNum(r.tolls_total, 2) + ' ' + (r.tolls_currency || 'EUR') : '—'; } },
            { label: '<?= __('Paliwo (PLN)') ?>', icon: 'ri-gas-station-line',
              val: function (r) { return fmtNum(calcCosts(r).fuel, 0) + ' PLN'; } },
            { label: '<?= __('Kierowca (PLN)') ?>', icon: 'ri-user-line',
              val: function (r) { return fmtNum(calcCosts(r).driver, 0) + ' PLN'; } },
            { label: '<?= __('Koszt łączny (PLN)') ?>', icon: 'ri-money-pound-circle-line',
              val: function (r) { return '<strong>' + fmtNum(calcCosts(r).total, 0) + ' PLN</strong>'; }, highlight: true },
            { label: '<?= __('Liczba krajów') ?>', icon: 'ri-earth-line',
              val: function (r) { return Object.keys(r.tolls_by_country || {}).length || '—'; } },
        ];
        if (revenue > 0) {
            rows.push({ label: '<?= __('Zysk (PLN)') ?>', icon: 'ri-money-dollar-circle-line',
                val: function (r) {
                    var c = calcCosts(r);
                    var profit = revenue - c.total;
                    var margin = revenue > 0 ? (profit / revenue * 100) : 0;
                    var color = margin >= 15 ? '#16a34a' : (margin >= 5 ? '#ca8a04' : '#dc2626');
                    return '<strong style="color:' + color + '">' + fmtNum(profit, 0) + ' PLN</strong>'
                        + '<div class="small text-muted">' + (margin >= 0 ? '+' : '') + fmtNum(margin, 1) + '%</div>';
                }, highlight: true });
        }

        // Najlepsze/najgorsze per kategoria
        var winners = {
            distance: routes.reduce(function (a, b) { return a.route.distance_km < b.route.distance_km ? a : b; }).idx,
            duration: routes.reduce(function (a, b) { return a.route.duration_min < b.route.duration_min ? a : b; }).idx,
            cost: routes.reduce(function (a, b) { return calcCosts(a.route).total < calcCosts(b.route).total ? a : b; }).idx,
        };

        // Render
        var html = '<table class="table table-bordered table-sm align-middle mb-0">';
        html += '<thead><tr><th></th>';
        routes.forEach(function (r) {
            var name = r.idx === 0 ? '<?= __('Trasa główna') ?>' : '<?= __('Alt.') ?> ' + r.idx;
            html += '<th class="text-center">' + name + '</th>';
        });
        html += '</tr></thead><tbody>';

        rows.forEach(function (row) {
            html += '<tr' + (row.highlight ? ' style="background:#f9fafb"' : '') + '>';
            html += '<td><i class="' + row.icon + ' me-1 text-muted"></i>' + row.label + '</td>';
            routes.forEach(function (r) {
                html += '<td class="text-center">' + row.val(r.route) + '</td>';
            });
            html += '</tr>';
        });

        // Highlight zwycięzców
        html += '<tr style="background:#dcfce7"><td><i class="ri-trophy-line me-1 text-success"></i><?= __('Najkrótsza') ?></td>';
        routes.forEach(function (r) {
            html += '<td class="text-center">' + (r.idx === winners.distance ? '🏆' : '') + '</td>';
        });
        html += '</tr>';
        html += '<tr style="background:#dcfce7"><td><i class="ri-trophy-line me-1 text-success"></i><?= __('Najszybsza') ?></td>';
        routes.forEach(function (r) {
            html += '<td class="text-center">' + (r.idx === winners.duration ? '🏆' : '') + '</td>';
        });
        html += '</tr>';
        html += '<tr style="background:#dcfce7"><td><i class="ri-trophy-line me-1 text-success"></i><?= __('Najtańsza') ?></td>';
        routes.forEach(function (r) {
            html += '<td class="text-center">' + (r.idx === winners.cost ? '🏆' : '') + '</td>';
        });
        html += '</tr>';
        html += '</tbody></table>';

        document.getElementById('compare-modal-body').innerHTML = html;
        new bootstrap.Modal(document.getElementById('compareModal')).show();
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

    // ═══════════════════════════════════════════════════════════════
    // "Wyslij oferte" — Fala 2B
    // Tworzy route_plan (na fly) + route_offer + opcjonalnie wysyla email
    // ═══════════════════════════════════════════════════════════════
    var routeOfferCreateUrl = '<?= $this->Url->build(['controller' => 'RouteOffers', 'action' => 'create']) ?>';
    var routeOfferSendUrlTpl = '<?= $this->Url->build(['controller' => 'RouteOffers', 'action' => 'send', '__ID__']) ?>';

    (function bindSendOfferModal() {
        var modal = document.getElementById('sendOfferModal');
        if (!modal) return;

        // Prefill przy otwarciu — z ostatniej kalkulacji
        modal.addEventListener('show.bs.modal', function () {
            if (!lastResponse || !lastResponse.routes || !lastResponse.routes[0]) return;
            var route = lastResponse.routes[0];
            var pts = lastResponse.points || [];

            // Prefill nazwy planu na bazie waypointow
            var fromCity = extractCity((pts[0] && (pts[0].address || pts[0].label)) || '');
            var toCity   = extractCity((pts[pts.length-1] && (pts[pts.length-1].address || pts[pts.length-1].label)) || '');
            var nameInput = document.getElementById('offer-name');
            if (!nameInput.value && (fromCity || toCity)) {
                nameInput.value = fromCity + ' → ' + toCity;
            }

            // Prefill temat maila
            var subjInput = document.getElementById('offer-subject');
            if (subjInput.value === '<?= __('Oferta transportowa') ?>' && (fromCity || toCity)) {
                subjInput.value = '<?= __('Oferta transportowa') ?> ' + fromCity + ' → ' + toCity;
            }

            // Prefill NIP z panelu historii jesli tam wpisany
            var nipFromHistory = document.getElementById('pricing-history-nip').value;
            if (nipFromHistory && !document.getElementById('offer-contractor-nip').value) {
                document.getElementById('offer-contractor-nip').value = nipFromHistory;
            }

            // Prefill valid_until = +14 dni
            var vu = document.getElementById('offer-valid-until');
            if (!vu.value) {
                var d = new Date();
                d.setDate(d.getDate() + 14);
                vu.value = d.toISOString().substring(0, 10);
            }
        });

        // Submit
        document.getElementById('btn-send-offer-submit').addEventListener('click', function () {
            var alertBox = document.getElementById('send-offer-alert');
            alertBox.style.display = 'none';

            var name  = document.getElementById('offer-name').value.trim();
            var email = document.getElementById('offer-sent-to-email').value.trim();
            var price = parseFloat(document.getElementById('offer-price').value);

            if (!name || !email || !(price > 0)) {
                alertBox.className = 'alert alert-warning';
                alertBox.textContent = '<?= __('Wypełnij nazwę, email i cenę.') ?>';
                alertBox.style.display = '';
                return;
            }

            if (!lastResponse || !lastResponse.routes || !lastResponse.routes[0]) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = '<?= __('Brak wyliczonej trasy.') ?>';
                alertBox.style.display = '';
                return;
            }

            var route = lastResponse.routes[0];
            var pts = lastResponse.points || [];
            var planData = {
                name: name,
                waypoints_json: pts,
                calc_cost_json: {
                    distance_km: route.distance_km,
                    duration_min: route.duration_min,
                    tolls_total: route.tolls_total,
                    tolls_currency: route.tolls_currency,
                    fuel_cost: route.fuel_cost,
                },
                distance_km: route.distance_km,
                duration_min: route.duration_min,
                co2_kg: route.co2_kg || null,
            };

            var body = new FormData();
            body.append('plan_data[name]', planData.name);
            body.append('plan_data[waypoints_json]', JSON.stringify(planData.waypoints_json));
            body.append('plan_data[calc_cost_json]', JSON.stringify(planData.calc_cost_json));
            if (planData.distance_km != null) body.append('plan_data[distance_km]', planData.distance_km);
            if (planData.duration_min != null) body.append('plan_data[duration_min]', planData.duration_min);
            if (planData.co2_kg != null) body.append('plan_data[co2_kg]', planData.co2_kg);
            body.append('sent_to_email', email);
            body.append('sent_to_name',  document.getElementById('offer-sent-to-name').value);
            body.append('subject',       document.getElementById('offer-subject').value);
            body.append('message_body',  document.getElementById('offer-message').value);
            body.append('price',         price);
            body.append('currency',      document.getElementById('offer-currency').value);
            body.append('vat_rate',      document.getElementById('offer-vat-rate').value);
            body.append('payment_days',  document.getElementById('offer-payment-days').value);
            body.append('valid_until',   document.getElementById('offer-valid-until').value);

            var btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span><?= __('Tworzę…') ?>';

            fetch(routeOfferCreateUrl, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                body: body
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) {
                        alertBox.className = 'alert alert-danger';
                        alertBox.textContent = res.error || '<?= __('Błąd utworzenia oferty.') ?>';
                        alertBox.style.display = '';
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ri-check-line me-1"></i><?= __('Utwórz ofertę') ?>';
                        return;
                    }

                    // Fala 4D: pokaz compliance warnings jesli sa
                    if (res.compliance_warnings && res.compliance_warnings.length > 0) {
                        var complianceHtml = '<div class="alert alert-warning py-2 mb-2"><strong><i class="ri-shield-check-line me-1"></i><?= __('Uwagi compliance') ?>:</strong><ul class="mb-0 mt-1 small">';
                        res.compliance_warnings.forEach(function (w) {
                            var icon = w.severity === 'error' ? '🚫' : '⚠️';
                            complianceHtml += '<li>' + icon + ' ' + w.message + '</li>';
                        });
                        complianceHtml += '</ul><div class="small mt-1 text-muted"><?= __('Wpisy zapisane w /ryzyko — musisz zaakceptować przed wysyłką na produkcje.') ?></div></div>';
                        alertBox.innerHTML = complianceHtml;
                        alertBox.className = '';
                        alertBox.style.display = '';
                    }

                    var sendNow = document.getElementById('offer-send-now').checked;
                    if (sendNow) {
                        // Wyslij (POST do send)
                        var sendUrl = routeOfferSendUrlTpl.replace('__ID__', encodeURIComponent(res.offer_id));
                        var sendFd = new FormData();
                        // Cake CSRF wymaga tokenu
                        fetch(sendUrl, {
                            method: 'POST',
                            headers: { 'X-CSRF-Token': csrf, 'Accept': 'text/html' },
                            body: sendFd,
                            redirect: 'manual'
                        })
                        .then(function () {
                            alertBox.className = 'alert alert-success';
                            alertBox.innerHTML = '<i class="ri-check-double-line me-1"></i><?= __('Oferta utworzona i wysłana!') ?> ' +
                                '<a href="' + res.redirect + '" class="alert-link"><?= __('Otwórz ofertę') ?> →</a> · ' +
                                '<a href="' + res.access_url + '" target="_blank" class="alert-link"><?= __('Link dla klienta') ?> →</a>';
                            alertBox.style.display = '';
                            btn.disabled = false;
                            btn.innerHTML = '<i class="ri-check-line me-1"></i><?= __('Utwórz ofertę') ?>';
                        })
                        .catch(function () {
                            alertBox.className = 'alert alert-warning';
                            alertBox.innerHTML = '<?= __('Oferta utworzona ale nie udało się wysłać automatycznie.') ?> ' +
                                '<a href="' + res.redirect + '" class="alert-link"><?= __('Otwórz i wyślij ręcznie') ?> →</a>';
                            alertBox.style.display = '';
                            btn.disabled = false;
                            btn.innerHTML = '<i class="ri-check-line me-1"></i><?= __('Utwórz ofertę') ?>';
                        });
                    } else {
                        alertBox.className = 'alert alert-success';
                        alertBox.innerHTML = '<i class="ri-check-line me-1"></i><?= __('Oferta utworzona (szkic).') ?> ' +
                            '<a href="' + res.redirect + '" class="alert-link"><?= __('Otwórz') ?> →</a>';
                        alertBox.style.display = '';
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ri-check-line me-1"></i><?= __('Utwórz ofertę') ?>';
                    }
                })
                .catch(function (e) {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = '<?= __('Błąd sieci:') ?> ' + ((e && e.message) || e);
                    alertBox.style.display = '';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="ri-check-line me-1"></i><?= __('Utwórz ofertę') ?>';
                });
        });
    })();

    // ─── GPX/KML export ──────────────────────────────────────────────
    function getRoutePoints() {
        if (!lastResponse || !lastResponse.routes || !lastResponse.routes[0]) return [];
        var pts = [];
        (lastResponse.routes[0].polylines || []).forEach(function (p) {
            try {
                var ls = H.geo.LineString.fromFlexiblePolyline(p);
                for (var i = 0; i < ls.getPointCount(); i++) {
                    var pt = ls.extractPoint(i);
                    pts.push({ lat: pt.lat, lng: pt.lng });
                }
            } catch (e) {}
        });
        return pts;
    }
    function downloadFile(filename, content, mime) {
        var blob = new Blob([content], { type: mime });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click();
        setTimeout(function () { document.body.removeChild(a); URL.revokeObjectURL(url); }, 100);
    }
    function buildGpx() {
        var pts = getRoutePoints();
        var wps = (lastResponse.points || []).filter(function (p) { return p.lat && p.lng; });
        var now = new Date().toISOString();
        var xml = '<' + '?xml version="1.0" encoding="UTF-8"?>\n';
        xml += '<gpx version="1.1" creator="JJ Maps Route Planner" xmlns="http://www.topografix.com/GPX/1/1">\n';
        xml += '  <metadata><time>' + now + '</time></metadata>\n';
        wps.forEach(function (p, i) {
            var name = (p.label || p.address || ('Point ' + (i+1))).replace(/[<>&]/g, '');
            xml += '  <wpt lat="' + p.lat + '" lon="' + p.lng + '"><name>' + name + '</name></wpt>\n';
        });
        xml += '  <trk><name>JJ Maps Route</name><trkseg>\n';
        pts.forEach(function (p) { xml += '    <trkpt lat="' + p.lat + '" lon="' + p.lng + '"/>\n'; });
        xml += '  </trkseg></trk>\n</gpx>';
        return xml;
    }
    function buildKml() {
        var pts = getRoutePoints();
        var wps = (lastResponse.points || []).filter(function (p) { return p.lat && p.lng; });
        var xml = '<' + '?xml version="1.0" encoding="UTF-8"?>\n';
        xml += '<kml xmlns="http://www.opengis.net/kml/2.2"><Document>\n';
        xml += '  <name>JJ Maps Route</name>\n';
        wps.forEach(function (p, i) {
            var name = (p.label || p.address || ('Point ' + (i+1))).replace(/[<>&]/g, '');
            xml += '  <Placemark><name>' + name + '</name><Point><coordinates>' + p.lng + ',' + p.lat + ',0</coordinates></Point></Placemark>\n';
        });
        xml += '  <Placemark><name>Trasa</name><Style><LineStyle><color>ff2563eb</color><width>4</width></LineStyle></Style>\n';
        xml += '    <LineString><coordinates>\n';
        pts.forEach(function (p) { xml += '      ' + p.lng + ',' + p.lat + ',0\n'; });
        xml += '    </coordinates></LineString>\n  </Placemark>\n';
        xml += '</Document></kml>';
        return xml;
    }
    document.getElementById('btn-export-gpx').addEventListener('click', function (e) {
        e.preventDefault();
        if (!lastResponse) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        downloadFile('route-' + Date.now() + '.gpx', buildGpx(), 'application/gpx+xml');
        toast('<?= __('GPX wyeksportowany') ?>', 'success');
    });
    document.getElementById('btn-export-kml').addEventListener('click', function (e) {
        e.preventDefault();
        if (!lastResponse) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        downloadFile('route-' + Date.now() + '.kml', buildKml(), 'application/vnd.google-earth.kml+xml');
        toast('<?= __('KML wyeksportowany') ?>', 'success');
    });

    // #15 — iCal/Outlook export
    function fmtIcalDt(d) {
        // YYYYMMDDTHHmmssZ (UTC)
        var dt = (typeof d === 'string') ? new Date(d) : d;
        return dt.getUTCFullYear()
            + String(dt.getUTCMonth() + 1).padStart(2, '0')
            + String(dt.getUTCDate()).padStart(2, '0')
            + 'T' + String(dt.getUTCHours()).padStart(2, '0')
            + String(dt.getUTCMinutes()).padStart(2, '0')
            + String(dt.getUTCSeconds()).padStart(2, '0') + 'Z';
    }
    function icalEscape(s) {
        return String(s || '').replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/,/g, '\\,').replace(/;/g, '\\;');
    }
    function buildIcal() {
        if (!lastResponse) return '';
        var r = lastResponse.routes[activeAltIdx];
        var pts = lastResponse.points || [];
        var now = new Date();
        var stamp = fmtIcalDt(now);
        var uid = 'booklio-' + now.getTime();
        var lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Booklio TMS//Route Planner//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH'
        ];
        // Główne wydarzenie - cała trasa
        var totalLabel = pts.map(function (p) { return p.label || p.address || ''; }).filter(Boolean).join(' → ');
        var startISO = r.sections && r.sections[0] && r.sections[0].departure_time;
        var endISO   = r.sections && r.sections[r.sections.length - 1] && r.sections[r.sections.length - 1].arrival_time;
        if (startISO && endISO) {
            var dur = '~' + Math.round(r.duration_min / 60) + 'h, ' + r.distance_km + ' km';
            lines.push('BEGIN:VEVENT');
            lines.push('UID:' + uid + '-route@booklio.pl');
            lines.push('DTSTAMP:' + stamp);
            lines.push('DTSTART:' + fmtIcalDt(startISO));
            lines.push('DTEND:'   + fmtIcalDt(endISO));
            lines.push('SUMMARY:🚛 ' + icalEscape(totalLabel));
            lines.push('DESCRIPTION:' + icalEscape('Trasa: ' + totalLabel + '\\nDystans: ' + r.distance_km + ' km\\nCzas: ' + dur
                + (r.tolls_total ? '\\nOpłaty: ' + r.tolls_total + ' ' + (r.tolls_currency || 'EUR') : '')));
            lines.push('END:VEVENT');
        }
        // Per waypoint event (1h przy każdym)
        pts.forEach(function (p, idx) {
            var wp = waypoints[idx] || {};
            var iso = '';
            if (idx === 0 && r.sections && r.sections[0]) iso = r.sections[0].departure_time;
            else if (r.sections && r.sections[idx - 1])   iso = r.sections[idx - 1].arrival_time;
            iso = wp.date || iso;
            if (!iso) return;
            var start = new Date(iso);
            var end   = new Date(start.getTime() + 30 * 60000); // +30 min default
            var letter = String.fromCharCode(65 + idx);
            var typeLabel = idx === 0 ? 'Załadunek' : (idx === pts.length - 1 ? 'Dostawa' : 'Postój');
            lines.push('BEGIN:VEVENT');
            lines.push('UID:' + uid + '-wp' + idx + '@booklio.pl');
            lines.push('DTSTAMP:' + stamp);
            lines.push('DTSTART:' + fmtIcalDt(start));
            lines.push('DTEND:'   + fmtIcalDt(end));
            lines.push('SUMMARY:' + icalEscape('📍 ' + letter + '. ' + typeLabel + ' — ' + (p.label || p.address || '')));
            if (p.lat && p.lng) {
                lines.push('GEO:' + p.lat.toFixed(6) + ';' + p.lng.toFixed(6));
                lines.push('LOCATION:' + icalEscape((p.label || p.address || '') + ' (' + p.lat.toFixed(4) + ', ' + p.lng.toFixed(4) + ')'));
            }
            lines.push('DESCRIPTION:' + icalEscape(typeLabel + ' — ' + (p.label || p.address || '')));
            lines.push('END:VEVENT');
        });
        lines.push('END:VCALENDAR');
        // CRLF — zgodnie z RFC 5545
        return lines.join('\r\n');
    }
    // #5 Multi-leg optimization — TSP/PDP dla wielu zleceń
    var mlLoadsCount = 0;
    var multilegUrl = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'optimizeMultileg']) ?>';

    function renderMlLoad(idx) {
        return '<div class="card mb-2" data-load-idx="' + idx + '">'
            + '<div class="card-body py-2">'
            + '<div class="d-flex align-items-center mb-2">'
            +   '<strong class="text-primary"><?= __('Zlecenie') ?> #' + (idx + 1) + '</strong>'
            +   '<input type="text" class="form-control form-control-sm ms-2 ml-load-name" placeholder="<?= __('Nazwa (np. ABC Sp. z o.o.)') ?>" style="max-width:250px">'
            +   '<button type="button" class="btn btn-sm btn-link text-danger ms-auto ml-remove-load"><i class="ri-close-line"></i></button>'
            + '</div>'
            + '<div class="row g-2">'
            +   '<div class="col-md-5">'
            +     '<label class="form-label small mb-1 text-success"><i class="ri-arrow-up-circle-line"></i> <?= __('Załadunek') ?></label>'
            +     '<input type="text" class="form-control form-control-sm ml-pickup" placeholder="<?= __('Adres załadunku') ?>">'
            +   '</div>'
            +   '<div class="col-md-5">'
            +     '<label class="form-label small mb-1 text-danger"><i class="ri-arrow-down-circle-line"></i> <?= __('Rozładunek') ?></label>'
            +     '<input type="text" class="form-control form-control-sm ml-dropoff" placeholder="<?= __('Adres rozładunku') ?>">'
            +   '</div>'
            +   '<div class="col-md-2">'
            +     '<label class="form-label small mb-1"><i class="ri-weight-line"></i> <?= __('Masa (kg)') ?></label>'
            +     '<input type="number" class="form-control form-control-sm ml-weight" placeholder="np. 12000">'
            +   '</div>'
            + '</div></div></div>';
    }

    function addMlLoad() {
        if (mlLoadsCount >= 4) { toast('<?= __('Maksymalnie 4 zlecenia.') ?>', 'warning'); return; }
        document.getElementById('ml-loads-list').insertAdjacentHTML('beforeend', renderMlLoad(mlLoadsCount));
        // Autosuggest dla nowo dodanej pary pickup/dropoff
        var lastCard = document.querySelector('#ml-loads-list [data-load-idx="' + mlLoadsCount + '"]');
        if (lastCard) {
            attachSimpleAutosuggest(lastCard.querySelector('.ml-pickup'));
            attachSimpleAutosuggest(lastCard.querySelector('.ml-dropoff'));
        }
        mlLoadsCount++;
        bindMlRemoveHandlers();
    }
    function bindMlRemoveHandlers() {
        document.querySelectorAll('.ml-remove-load').forEach(function (btn) {
            btn.onclick = function () {
                btn.closest('[data-load-idx]').remove();
                mlLoadsCount--;
                // Renumeracja
                document.querySelectorAll('#ml-loads-list [data-load-idx]').forEach(function (el, i) {
                    el.dataset.loadIdx = i;
                    el.querySelector('strong').textContent = '<?= __('Zlecenie') ?> #' + (i + 1);
                });
            };
        });
    }

    document.getElementById('btn-multileg').addEventListener('click', function () {
        // Reset modala
        document.getElementById('ml-loads-list').innerHTML = '';
        mlLoadsCount = 0;
        document.getElementById('ml-result').style.display = 'none';
        var startInp = document.getElementById('ml-start');
        var endInp = document.getElementById('ml-end');
        startInp.value = ''; startInp.dataset.lat = ''; startInp.dataset.lng = '';
        endInp.value = '';   endInp.dataset.lat = '';   endInp.dataset.lng = '';
        // Autosuggest dla start/end
        attachSimpleAutosuggest(startInp);
        attachSimpleAutosuggest(endInp);
        // Pre-fill 2 zlecenia
        addMlLoad(); addMlLoad();
        new bootstrap.Modal(document.getElementById('multilegModal')).show();
    });

    document.getElementById('btn-ml-add').addEventListener('click', addMlLoad);

    document.getElementById('btn-ml-optimize').addEventListener('click', function () {
        var btn = this;
        var loadCards = document.querySelectorAll('#ml-loads-list [data-load-idx]');
        if (loadCards.length < 1) { toast('<?= __('Dodaj minimum 1 zlecenie.') ?>', 'warning'); return; }

        var loads = [];
        var hasError = false;
        loadCards.forEach(function (card, i) {
            var pInp = card.querySelector('.ml-pickup');
            var dInp = card.querySelector('.ml-dropoff');
            var pickup = pInp.value.trim();
            var dropoff = dInp.value.trim();
            if (!pickup || !dropoff) {
                hasError = true;
                toast('<?= __('Wpisz adresy załadunku/rozładunku dla zlecenia #') ?>' + (i + 1), 'error');
                return;
            }
            loads.push({
                name: card.querySelector('.ml-load-name').value || ('Load ' + (i + 1)),
                pickup: { address: pickup, lat: pInp.dataset.lat || null, lng: pInp.dataset.lng || null },
                dropoff: { address: dropoff, lat: dInp.dataset.lat || null, lng: dInp.dataset.lng || null },
                weight_kg: parseFloat(card.querySelector('.ml-weight').value) || null,
            });
        });
        if (hasError) return;

        var startInp = document.getElementById('ml-start');
        var endInp = document.getElementById('ml-end');
        var startAddr = startInp.value.trim();
        var endAddr = endInp.value.trim();

        var fd = new FormData();
        loads.forEach(function (L, idx) {
            fd.append('loads[' + idx + '][name]', L.name);
            fd.append('loads[' + idx + '][pickup][address]', L.pickup.address);
            if (L.pickup.lat) fd.append('loads[' + idx + '][pickup][lat]', L.pickup.lat);
            if (L.pickup.lng) fd.append('loads[' + idx + '][pickup][lng]', L.pickup.lng);
            fd.append('loads[' + idx + '][dropoff][address]', L.dropoff.address);
            if (L.dropoff.lat) fd.append('loads[' + idx + '][dropoff][lat]', L.dropoff.lat);
            if (L.dropoff.lng) fd.append('loads[' + idx + '][dropoff][lng]', L.dropoff.lng);
            if (L.weight_kg) fd.append('loads[' + idx + '][weight_kg]', String(L.weight_kg));
        });
        if (startAddr) {
            fd.append('start[address]', startAddr);
            if (startInp.dataset.lat) fd.append('start[lat]', startInp.dataset.lat);
            if (startInp.dataset.lng) fd.append('start[lng]', startInp.dataset.lng);
        }
        if (endAddr) {
            fd.append('end[address]', endAddr);
            if (endInp.dataset.lat) fd.append('end[lat]', endInp.dataset.lat);
            if (endInp.dataset.lng) fd.append('end[lng]', endInp.dataset.lng);
        }
        fd.append('_csrfToken', csrf);

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
        document.getElementById('ml-loading').style.display = 'block';
        document.getElementById('ml-result').style.display = 'none';

        fetch(multilegUrl, { method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            document.getElementById('ml-loading').style.display = 'none';
            if (!res || !res.ok) {
                toast((res && res.message) || '<?= __('Błąd optymalizacji') ?>', 'error');
                return;
            }
            renderMlResult(res.data);
        })
        .catch(function (e) { toast('<?= __('Błąd:') ?> ' + e.message, 'error'); })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-sparkling-2-line me-1"></i><?= __('Optymalizuj') ?>';
            document.getElementById('ml-loading').style.display = 'none';
        });
    });

    function renderMlResult(data) {
        var result = document.getElementById('ml-result');
        var best = data.best;
        if (!best) { result.innerHTML = '<div class="alert alert-warning">Brak optymalnego rozwiązania.</div>'; result.style.display = 'block'; return; }

        var savings = data.best_savings_km;
        var savingsPct = data.best_savings_pct;
        var savingsColor = savingsPct >= 10 ? 'success' : savingsPct >= 3 ? 'info' : 'secondary';

        var html = '<div class="alert alert-' + savingsColor + ' py-2">'
            + '<i class="ri-trophy-line me-1"></i>'
            + '<strong><?= __('Oszczędność') ?>: ' + fmtNum(savings, 1) + ' km (' + fmtNum(savingsPct, 1) + '%)</strong>'
            + ' &nbsp;vs naive order: ' + fmtNum(data.baseline_km, 1) + ' km'
            + ' &nbsp;|&nbsp; Optymalna: <strong>' + fmtNum(best.distance_km, 1) + ' km</strong>'
            + ' &nbsp;|&nbsp; Sprawdzono <strong>' + data.orderings_evaluated + '</strong> kombinacji'
            + '</div>';

        html += '<h6 class="mb-2"><i class="ri-route-fill text-primary me-1"></i><?= __('Optymalna kolejność') ?>:</h6>';
        html += '<div class="list-group mb-3">';
        best.waypoints.forEach(function (wp, i) {
            var icon, color, label;
            if (wp.type === 'start')   { icon = 'ri-home-line';            color = 'primary';   label = '<?= __('Start') ?>'; }
            else if (wp.type === 'end'){ icon = 'ri-map-pin-line';         color = 'primary';   label = '<?= __('Powrót') ?>'; }
            else if (wp.type === 'pickup'){ icon = 'ri-arrow-up-circle-line'; color = 'success'; label = '<?= __('ZAŁADUNEK') ?> · ' + escapeHtml(wp.load_name); }
            else { icon = 'ri-arrow-down-circle-line'; color = 'danger'; label = '<?= __('ROZŁADUNEK') ?> · ' + escapeHtml(wp.load_name); }
            html += '<div class="list-group-item d-flex align-items-center">'
                + '<span class="badge bg-' + color + ' me-2">' + (i + 1) + '</span>'
                + '<i class="' + icon + ' text-' + color + ' me-2"></i>'
                + '<div class="flex-grow-1"><strong>' + label + '</strong><div class="text-muted small">' + escapeHtml(wp.address) + '</div></div>'
                + '</div>';
        });
        html += '</div>';

        if (data.alternatives && data.alternatives.length) {
            html += '<details class="mb-3"><summary class="text-muted small"><?= __('Pokaż') ?> ' + data.alternatives.length + ' <?= __('alternatywnych rozwiązań') ?></summary>';
            data.alternatives.forEach(function (alt, i) {
                html += '<div class="ms-3 mt-2 small text-muted">'
                    + '<strong>Alt #' + (i + 1) + ':</strong> ' + fmtNum(alt.distance_km, 1) + ' km '
                    + '(<?= __('oszczędność') ?>: ' + fmtNum(alt.savings_km, 1) + ' km / ' + fmtNum(alt.savings_pct, 1) + '%)<br>'
                    + alt.waypoints.map(function (w) { return w.type === 'pickup' ? 'P' : (w.type === 'dropoff' ? 'D' : (w.type === 'start' ? 'S' : 'E')) + (w.load_name ? ('(' + w.load_name + ')') : ''); }).join(' → ')
                    + '</div>';
            });
            html += '</details>';
        }

        html += '<button class="btn btn-success" id="btn-ml-apply"><i class="ri-check-line me-1"></i><?= __('Zastosuj do planera') ?></button>';
        result.innerHTML = html;
        result.style.display = 'block';

        document.getElementById('btn-ml-apply').addEventListener('click', function () {
            // Zastosuj waypoints (pomiń start i end żeby user mógł je wpisać ręcznie)
            waypoints = best.waypoints.map(function (wp) {
                return {
                    address: wp.address, label: wp.address,
                    lat: wp.lat, lng: wp.lng, country: '', date: ''
                };
            });
            renderWaypoints();
            renderPinsOnMap();
            bootstrap.Modal.getInstance(document.getElementById('multilegModal')).hide();
            toast('<?= __('Zastosowano optymalną kolejność — kliknij Wyznacz trasę') ?>', 'success');
        });
    }

    // #9 CMR generator — multilang print view
    var CMR_L10N = {
        pl: {
            title: 'MIĘDZYNARODOWY SAMOCHODOWY LIST PRZEWOZOWY', subtitle: 'CMR - Konwencja CMR',
            sender: '1. Nadawca', consignee: '2. Odbiorca', deliveryAddr: '3. Miejsce przeznaczenia',
            loadAddr: '4. Miejsce i data załadunku', docs: '5. Dokumenty', marks: '6. Znaki i numery',
            pieces: '7. Liczba sztuk', packaging: '8. Sposób opakowania', goods: '9. Rodzaj towaru',
            statisticalNum: '10. Nr statystyczny', grossWeight: '11. Masa brutto kg', volume: '12. Objętość m³',
            senderInstructions: '13. Instrukcje nadawcy', cashOnDelivery: '14. Postanowienia za pobraniem',
            carrierInstr: '15. Postanowienia szczególne', carrier: '16. Przewoźnik',
            successive: '17. Kolejni przewoźnicy', reservation: '18. Zastrzeżenia',
            specialAgreements: '19. Postanowienia specjalne', cost: '20. Koszty',
            issueDate: '21. Sporządzono w / dnia', signSender: 'Podpis nadawcy',
            signCarrier: 'Podpis przewoźnika', signConsignee: 'Podpis odbiorcy',
            cmrNumber: 'Nr CMR', driver: 'Kierowca', vehicle: 'Pojazd', route: 'Trasa',
            adr: 'ADR', confirmConvention: 'Niniejszy przewóz, nawet jeśli odbywa się na podstawie umowy, podlega Konwencji CMR.'
        },
        en: {
            title: 'INTERNATIONAL CONSIGNMENT NOTE', subtitle: 'CMR - Convention CMR',
            sender: '1. Sender', consignee: '2. Consignee', deliveryAddr: '3. Place of delivery',
            loadAddr: '4. Place and date of taking over the goods', docs: '5. Documents attached',
            marks: '6. Marks and numbers', pieces: '7. Number of packages', packaging: '8. Method of packing',
            goods: '9. Nature of the goods', statisticalNum: '10. Statistical number',
            grossWeight: '11. Gross weight kg', volume: '12. Volume m³',
            senderInstructions: '13. Sender\'s instructions', cashOnDelivery: '14. Cash on delivery',
            carrierInstr: '15. Carrier\'s instructions', carrier: '16. Carrier',
            successive: '17. Successive carriers', reservation: '18. Reservations of carrier',
            specialAgreements: '19. Special agreements', cost: '20. Charges',
            issueDate: '21. Established at / on', signSender: 'Sender\'s signature',
            signCarrier: 'Carrier\'s signature', signConsignee: 'Consignee\'s signature',
            cmrNumber: 'CMR No.', driver: 'Driver', vehicle: 'Vehicle', route: 'Route',
            adr: 'ADR', confirmConvention: 'This carriage is subject to the CMR Convention notwithstanding any contract.'
        },
        de: {
            title: 'INTERNATIONALER FRACHTBRIEF', subtitle: 'CMR - CMR-Konvention',
            sender: '1. Absender', consignee: '2. Empfänger', deliveryAddr: '3. Auslieferungsort',
            loadAddr: '4. Übernahmeort und -datum', docs: '5. Beigefügte Dokumente',
            marks: '6. Zeichen und Nummern', pieces: '7. Anzahl der Packstücke', packaging: '8. Art der Verpackung',
            goods: '9. Bezeichnung des Gutes', statisticalNum: '10. Statistische Nummer',
            grossWeight: '11. Bruttogewicht kg', volume: '12. Umfang m³',
            senderInstructions: '13. Anweisungen des Absenders', cashOnDelivery: '14. Nachnahme',
            carrierInstr: '15. Anweisungen des Frachtführers', carrier: '16. Frachtführer',
            successive: '17. Nachfolgende Frachtführer', reservation: '18. Vorbehalte',
            specialAgreements: '19. Besondere Vereinbarungen', cost: '20. Kosten',
            issueDate: '21. Ausgestellt in / am', signSender: 'Unterschrift Absender',
            signCarrier: 'Unterschrift Frachtführer', signConsignee: 'Unterschrift Empfänger',
            cmrNumber: 'CMR-Nr.', driver: 'Fahrer', vehicle: 'Fahrzeug', route: 'Strecke',
            adr: 'ADR', confirmConvention: 'Diese Beförderung unterliegt trotz einer gegenteiligen Abmachung der CMR-Konvention.'
        },
        ua: {
            title: 'МІЖНАРОДНА АВТОТРАНСПОРТНА НАКЛАДНА', subtitle: 'CMR - Конвенція CMR',
            sender: '1. Відправник', consignee: '2. Одержувач', deliveryAddr: '3. Місце доставки',
            loadAddr: '4. Місце і дата прийому вантажу', docs: '5. Документи',
            marks: '6. Знаки і номери', pieces: '7. Кількість місць', packaging: '8. Вид упаковки',
            goods: '9. Найменування вантажу', statisticalNum: '10. Статистичний номер',
            grossWeight: '11. Маса брутто кг', volume: '12. Об\'єм м³',
            senderInstructions: '13. Інструкції відправника', cashOnDelivery: '14. Накладений платіж',
            carrierInstr: '15. Інструкції перевізника', carrier: '16. Перевізник',
            successive: '17. Наступні перевізники', reservation: '18. Застереження',
            specialAgreements: '19. Особливі умови', cost: '20. Витрати',
            issueDate: '21. Складено в / дата', signSender: 'Підпис відправника',
            signCarrier: 'Підпис перевізника', signConsignee: 'Підпис одержувача',
            cmrNumber: 'Номер CMR', driver: 'Водій', vehicle: 'Транспортний засіб', route: 'Маршрут',
            adr: 'ADR', confirmConvention: 'Це перевезення підлягає Конвенції CMR незалежно від договору.'
        }
    };

    document.getElementById('btn-cmr').addEventListener('click', function () {
        if (!lastResponse) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        // Auto-uzupełnij delivery address z ostatniego waypoint
        var pts = lastResponse.points || [];
        if (pts.length >= 2) {
            var origin = pts[0];
            var dest = pts[pts.length - 1];
            document.getElementById('cmr-consignee-addr').value = dest.label || dest.address || '';
        }
        document.getElementById('cmr-cmr-number').value = 'CMR/' + new Date().getFullYear() + '/' + Math.floor(Math.random() * 9000 + 1000);
        // Autosuggest dla adresów nadawcy/odbiorcy (przydatne gdy user wpisuje ręcznie nowy adres)
        attachSimpleAutosuggest(document.getElementById('cmr-sender-addr'));
        attachSimpleAutosuggest(document.getElementById('cmr-consignee-addr'));
        new bootstrap.Modal(document.getElementById('cmrModal')).show();
    });

    document.getElementById('btn-cmr-generate').addEventListener('click', function () {
        var lang = document.getElementById('cmr-lang').value;
        var L = CMR_L10N[lang] || CMR_L10N.pl;
        var pts = (lastResponse && lastResponse.points) || [];
        var origin = pts[0] || {}, dest = pts[pts.length - 1] || {};
        var vehicle = getSelectedVehicle();
        var autoFill = document.getElementById('cmr-adr').checked;
        var adrClass = document.getElementById('adr-class').value;

        // Wypełnij wartości
        var sender = {
            name: document.getElementById('cmr-sender-name').value || '',
            addr: document.getElementById('cmr-sender-addr').value || '',
            nip: document.getElementById('cmr-sender-nip').value || '',
            phone: document.getElementById('cmr-sender-phone').value || '',
        };
        var consignee = {
            name: document.getElementById('cmr-consignee-name').value || '',
            addr: document.getElementById('cmr-consignee-addr').value || (dest.label || dest.address || ''),
            nip: document.getElementById('cmr-consignee-nip').value || '',
            phone: document.getElementById('cmr-consignee-phone').value || '',
        };
        var loadAddr = origin.label || origin.address || '';
        var goods = document.getElementById('cmr-goods').value || '';
        var pieces = document.getElementById('cmr-pieces').value || '';
        var packaging = document.getElementById('cmr-packaging').value || '';
        var weight = document.getElementById('cmr-weight').value || '';
        var marks = document.getElementById('cmr-marks').value || '';
        var instructions = document.getElementById('cmr-instructions').value || '';
        var cmrNumber = document.getElementById('cmr-cmr-number').value || '';
        var driver = document.getElementById('cmr-driver').value || '';
        var dateToday = new Date().toLocaleDateString(lang === 'pl' ? 'pl-PL' : lang === 'de' ? 'de-DE' : lang === 'ua' ? 'uk-UA' : 'en-GB');

        // ADR section
        var adrSection = '';
        if (autoFill && adrClass) {
            adrSection = '<div class="cell"><div class="lbl">' + L.adr + '</div><div><strong>Klasa ' + adrClass + '</strong></div></div>';
        }

        var routeText = pts.map(function (p, i) { return String.fromCharCode(65+i) + ': ' + (p.label || p.address); }).join(' → ');
        var routeDistance = lastResponse.routes[activeAltIdx].distance_km;
        var routeDuration = lastResponse.routes[activeAltIdx].duration_min;

        var win = window.open('', '_blank', 'width=1000,height=1400');
        if (!win) { toast('<?= __('Włącz wyskakujące okna') ?>', 'warning'); return; }
        win.document.write(
            '<!DOCTYPE html><html lang="' + lang + '"><head><meta charset="utf-8"><title>CMR ' + escapeHtml(cmrNumber) + '</title>'
            + '<style>'
            + 'body{font-family:Arial,sans-serif;margin:15px;color:#111;font-size:10pt}'
            + '.header{text-align:center;border:2px solid #111;padding:10px;margin-bottom:0}'
            + '.header h1{font-size:14pt;margin:0;font-weight:700}'
            + '.header h2{font-size:9pt;margin:2px 0;color:#444;font-weight:400}'
            + '.cmr-num{position:absolute;top:20px;right:30px;font-size:11pt;font-weight:700;color:#dc2626}'
            + '.grid{display:grid;grid-template-columns:1fr 1fr;border:2px solid #111;border-top:0}'
            + '.row{display:grid;border-bottom:1px solid #111}'
            + '.row.two{grid-template-columns:1fr 1fr}'
            + '.row.three{grid-template-columns:1fr 1fr 1fr}'
            + '.row.four{grid-template-columns:1fr 1fr 1fr 1fr}'
            + '.cell{padding:5px 8px;border-right:1px solid #111;min-height:32px}'
            + '.cell:last-child{border-right:0}'
            + '.lbl{font-size:7pt;font-weight:700;color:#555;text-transform:uppercase}'
            + '.cell.tall{min-height:60px}'
            + '.cell.medium{min-height:42px}'
            + '.row:last-child{border-bottom:0}'
            + '.sigs{display:grid;grid-template-columns:1fr 1fr 1fr;border:2px solid #111;border-top:0}'
            + '.sigs .sig{padding:30px 12px 10px;border-right:1px solid #111;text-align:center;font-size:8pt}'
            + '.sigs .sig:last-child{border-right:0}'
            + '.footer{margin-top:10px;font-size:7pt;color:#555;font-style:italic}'
            + 'button{position:fixed;bottom:20px;right:20px;padding:12px 24px;background:#15803d;color:white;border:0;border-radius:8px;font-weight:600;cursor:pointer}'
            + '@media print{button{display:none}body{margin:8mm}}'
            + '</style></head><body>'
            + '<div class="cmr-num">' + escapeHtml(cmrNumber) + '</div>'
            + '<div class="header">'
            +   '<h1>' + L.title + '</h1>'
            +   '<h2>' + L.subtitle + '</h2>'
            + '</div>'
            + '<div class="grid">'
            +   '<div class="cell tall"><div class="lbl">' + L.sender + '</div>'
            +     '<div><strong>' + escapeHtml(sender.name) + '</strong></div>'
            +     '<div>' + escapeHtml(sender.addr) + '</div>'
            +     (sender.nip ? '<div>NIP: ' + escapeHtml(sender.nip) + '</div>' : '')
            +     (sender.phone ? '<div>Tel: ' + escapeHtml(sender.phone) + '</div>' : '')
            +   '</div>'
            +   '<div class="cell tall"><div class="lbl">' + L.consignee + '</div>'
            +     '<div><strong>' + escapeHtml(consignee.name) + '</strong></div>'
            +     '<div>' + escapeHtml(consignee.addr) + '</div>'
            +     (consignee.nip ? '<div>NIP: ' + escapeHtml(consignee.nip) + '</div>' : '')
            +     (consignee.phone ? '<div>Tel: ' + escapeHtml(consignee.phone) + '</div>' : '')
            +   '</div>'
            + '</div>'
            + '<div class="grid" style="border-top:0">'
            +   '<div class="cell medium"><div class="lbl">' + L.loadAddr + '</div><div><strong>' + escapeHtml(loadAddr) + '</strong></div></div>'
            +   '<div class="cell medium"><div class="lbl">' + L.deliveryAddr + '</div><div><strong>' + escapeHtml(consignee.addr) + '</strong></div></div>'
            + '</div>'
            + '<div class="row four" style="border:2px solid #111;border-top:0">'
            +   '<div class="cell medium"><div class="lbl">' + L.marks + '</div><div>' + escapeHtml(marks) + '</div></div>'
            +   '<div class="cell medium"><div class="lbl">' + L.pieces + '</div><div>' + escapeHtml(pieces) + '</div></div>'
            +   '<div class="cell medium"><div class="lbl">' + L.packaging + '</div><div>' + escapeHtml(packaging) + '</div></div>'
            +   '<div class="cell medium"><div class="lbl">' + L.goods + '</div><div>' + escapeHtml(goods) + '</div></div>'
            + '</div>'
            + '<div class="row three" style="border:2px solid #111;border-top:0">'
            +   '<div class="cell medium"><div class="lbl">' + L.grossWeight + '</div><div>' + escapeHtml(weight) + '</div></div>'
            +   '<div class="cell medium"><div class="lbl">' + L.volume + '</div><div></div></div>'
            +   adrSection
            + '</div>'
            + '<div class="grid" style="border-top:0">'
            +   '<div class="cell medium"><div class="lbl">' + L.senderInstructions + '</div><div>' + escapeHtml(instructions) + '</div></div>'
            +   '<div class="cell medium"><div class="lbl">' + L.carrierInstr + '</div><div></div></div>'
            + '</div>'
            + '<div class="grid" style="border-top:0">'
            +   '<div class="cell medium"><div class="lbl">' + L.carrier + '</div>'
            +     '<div><strong>Booklio TMS</strong></div>'
            +     (vehicle ? '<div>' + L.vehicle + ': ' + escapeHtml(vehicle.name) + (vehicle.plate ? ' (' + escapeHtml(vehicle.plate) + ')' : '') + '</div>' : '')
            +     (driver ? '<div>' + L.driver + ': ' + escapeHtml(driver) + '</div>' : '')
            +   '</div>'
            +   '<div class="cell medium"><div class="lbl">' + L.route + '</div>'
            +     '<div style="font-size:8pt">' + escapeHtml(routeText) + '</div>'
            +     '<div style="font-size:7pt;color:#555">' + fmtNum(routeDistance, 1) + ' km · ' + fmtDur(routeDuration) + '</div>'
            +   '</div>'
            + '</div>'
            + '<div class="grid" style="border-top:0">'
            +   '<div class="cell"><div class="lbl">' + L.issueDate + '</div><div><strong>' + escapeHtml(dateToday) + '</strong></div></div>'
            +   '<div class="cell"><div class="lbl">' + L.specialAgreements + '</div><div></div></div>'
            + '</div>'
            + '<div class="sigs">'
            +   '<div class="sig">' + L.signSender + '</div>'
            +   '<div class="sig">' + L.signCarrier + '</div>'
            +   '<div class="sig">' + L.signConsignee + '</div>'
            + '</div>'
            + '<div class="footer">' + L.confirmConvention + '</div>'
            + '<button onclick="window.print()">🖨️ ' + L.title.split(' ').slice(-2).join(' ') + ' / PDF</button>'
            + '</body></html>'
        );
        win.document.close();
        setTimeout(function () { try { win.print(); } catch (e) {} }, 500);
    });

    // #14 — Live tracking link dla klienta
    document.getElementById('btn-track-link').addEventListener('click', function () {
        if (!lastResponse) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        var id = lastResponse.route_search_id;
        if (!id) { toast('<?= __('Trasa nie została zapisana — kalkulacja musi być po zalogowaniu.') ?>', 'warning'); return; }
        var url = window.location.origin + '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'trackView', '__ID__']) ?>'.replace('__ID__', id);
        // Pokazujemy modal z linkiem do kopiowania + QR
        var modalHtml = '<div class="modal fade" id="trackLinkModal" tabindex="-1"><div class="modal-dialog modal-md modal-dialog-centered">'
            + '<div class="modal-content" style="border-radius:14px;overflow:hidden">'
            + '<div class="modal-header" style="background:linear-gradient(135deg,#0891b2,#06b6d4);color:white">'
            + '<h5 class="modal-title"><i class="ri-broadcast-line me-2"></i><?= __('Link dla klienta') ?></h5>'
            + '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>'
            + '<div class="modal-body">'
            + '<div class="text-muted small mb-3"><i class="ri-information-line me-1"></i><?= __('Klient otworzy link na telefonie / komputerze i zobaczy trasę z mapą, ETA, punktami. Bez logowania.') ?></div>'
            + '<div class="input-group mb-3">'
            +   '<input type="text" class="form-control" id="track-url-input" value="' + url + '" readonly>'
            +   '<button class="btn btn-primary" id="btn-copy-track-url"><i class="ri-clipboard-line"></i></button>'
            + '</div>'
            + '<div class="text-center">'
            +   '<img id="track-qr" src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' + encodeURIComponent(url) + '" alt="QR" style="border:1px solid #e5e7eb;border-radius:8px;padding:8px;background:white">'
            +   '<div class="text-muted small mt-2"><?= __('Lub zeskanuj telefonem') ?></div>'
            + '</div></div>'
            + '<div class="modal-footer">'
            + '<a href="' + url + '" target="_blank" class="btn btn-outline-info"><i class="ri-external-link-line me-1"></i><?= __('Otwórz') ?></a>'
            + '<button type="button" class="btn btn-info text-white" id="btn-share-track"><i class="ri-share-line me-1"></i><?= __('Udostępnij') ?></button>'
            + '</div></div></div></div>';
        // Usuń stary jeśli istnieje
        var existing = document.getElementById('trackLinkModal');
        if (existing) existing.remove();
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        new bootstrap.Modal(document.getElementById('trackLinkModal')).show();
        document.getElementById('btn-copy-track-url').addEventListener('click', function () {
            if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () {
                toast('<?= __('Link skopiowany') ?>', 'success');
            });
        });
        document.getElementById('btn-share-track').addEventListener('click', function () {
            if (navigator.share) {
                navigator.share({ title: 'Trasa transportowa', text: 'Śledź trasę:', url: url });
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    toast('<?= __('Link skopiowany — wklej do SMS/WhatsApp') ?>', 'success');
                });
            }
        });
    });

    // #6 — Generuj ofertę PDF dla klienta (print view w nowym oknie z HERE static map)
    document.getElementById('btn-customer-offer').addEventListener('click', function () {
        if (!lastResponse) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        var r = lastResponse.routes[activeAltIdx];
        var pts = lastResponse.points || [];
        if (!r || pts.length < 2) { toast('<?= __('Brak punktów trasy.') ?>', 'warning'); return; }

        // HERE Static Map Image API (mapview)
        // Markery + polyline → URL do PNG
        var mapMarkers = pts.map(function (p, i) {
            return 'poi:' + p.lat.toFixed(5) + ',' + p.lng.toFixed(5) + ';' + String.fromCharCode(65 + i);
        }).join('|');
        // Bbox z punktów + 20% padding
        var minLat = Math.min.apply(null, pts.map(function (p) { return p.lat; }));
        var maxLat = Math.max.apply(null, pts.map(function (p) { return p.lat; }));
        var minLng = Math.min.apply(null, pts.map(function (p) { return p.lng; }));
        var maxLng = Math.max.apply(null, pts.map(function (p) { return p.lng; }));
        var dLat = (maxLat - minLat) * 0.2;
        var dLng = (maxLng - minLng) * 0.2;
        var bbox = (maxLat + dLat).toFixed(5) + ',' + (minLng - dLng).toFixed(5)
                 + ',' + (minLat - dLat).toFixed(5) + ',' + (maxLng + dLng).toFixed(5);
        var mapUrl = 'https://image.maps.hereapi.com/mia/1.6/mapview'
                   + '?apiKey=' + encodeURIComponent(hereKey)
                   + '&bbox=' + encodeURIComponent(bbox)
                   + '&poi=' + encodeURIComponent(pts.map(function (p) { return p.lat.toFixed(5) + ',' + p.lng.toFixed(5); }).join(','))
                   + '&poimarker=' + encodeURIComponent('0;2563EB;FFFFFF')
                   + '&w=800&h=400&style=alps&f=0';

        // Dane do oferty
        var revenue = parseFloat(document.getElementById('freight-revenue').value || 0);
        var cons = parseFloat(document.getElementById('fuel-consumption').value || 0);
        var price = parseFloat(document.getElementById('fuel-price').value || 0);
        var rate = parseFloat(document.getElementById('driver-rate').value || 0);
        var fuel = (r.distance_km / 100) * cons * price;
        var driver = (r.duration_min / 60) * rate;
        var tolls = r.tolls_total || 0;
        var vehicle = getSelectedVehicle();

        // Daty z waypoints
        var dateRangeText = '';
        var w0 = waypoints[0], wN = waypoints[waypoints.length - 1];
        if (w0 && w0.date) dateRangeText += '<?= __('Załadunek') ?>: <strong>' + w0.date.replace('T', ' ') + '</strong>';
        if (wN && wN.date) dateRangeText += ' &nbsp;|&nbsp; <?= __('Dostawa') ?>: <strong>' + wN.date.replace('T', ' ') + '</strong>';

        var wpsList = pts.map(function (p, i) {
            return '<li><strong>' + String.fromCharCode(65 + i) + '.</strong> ' + escapeHtml(p.label || p.address || '') + '</li>';
        }).join('');

        var win = window.open('', '_blank', 'width=900,height=1200');
        if (!win) { toast('<?= __('Włącz wyskakujące okna') ?>', 'warning'); return; }
        win.document.write(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title><?= __('Oferta transportowa') ?></title>'
            + '<style>'
            + 'body{font-family:Arial,sans-serif;margin:0;padding:30px;color:#111;background:white}'
            + '.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;padding-bottom:15px;border-bottom:3px solid #2563eb}'
            + '.logo{font-size:24px;font-weight:700;color:#2563eb}'
            + '.sub{color:#6b7280;font-size:13px;margin-top:4px}'
            + 'h1{font-size:22px;margin:8px 0;color:#111}'
            + 'h2{font-size:14px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin:24px 0 10px}'
            + '.map{width:100%;border-radius:8px;border:1px solid #e5e7eb;margin:12px 0;max-height:400px}'
            + '.box{background:#f9fafb;border-radius:8px;padding:16px 20px;margin:10px 0;border:1px solid #e5e7eb}'
            + '.box.price{background:linear-gradient(135deg,#dbeafe,#eff6ff);border-color:#bfdbfe}'
            + '.row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #e5e7eb}'
            + '.row:last-child{border:0}'
            + '.row .label{color:#6b7280;font-size:13px}'
            + '.row .val{font-weight:600;color:#111}'
            + '.price-big{font-size:36px;font-weight:700;color:#2563eb;text-align:center;padding:10px 0}'
            + '.muted{color:#6b7280;font-size:11px}'
            + 'ul{padding-left:20px;list-style:none}'
            + 'ul li{padding:6px 0;border-bottom:1px solid #e5e7eb}'
            + 'ul li:last-child{border:0}'
            + 'button{position:fixed;bottom:20px;right:20px;padding:12px 24px;background:#2563eb;color:white;border:0;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.15)}'
            + '@media print{button{display:none}body{padding:15px}.box{break-inside:avoid}}'
            + '</style></head><body>'
            + '<div class="header">'
            + '<div><div class="logo">🚛 Booklio TMS</div><div class="sub"><?= __('Ekspert w transporcie międzynarodowym') ?></div></div>'
            + '<div style="text-align:right"><div class="muted"><?= __('Oferta wygenerowana') ?>:</div><div style="font-weight:600">' + new Date().toLocaleString('pl-PL') + '</div></div>'
            + '</div>'
            + '<h1>📋 <?= __('Oferta transportowa') ?></h1>'
            + (dateRangeText ? '<div style="margin:8px 0 16px;color:#374151">' + dateRangeText + '</div>' : '')

            + '<h2>📍 <?= __('Trasa') ?></h2>'
            + '<img src="' + mapUrl + '" class="map" alt="<?= __('Mapa trasy') ?>" onerror="this.style.display=\'none\'">'
            + '<ul>' + wpsList + '</ul>'

            + '<h2>📊 <?= __('Podsumowanie') ?></h2>'
            + '<div class="box">'
            + '<div class="row"><span class="label">📏 <?= __('Dystans') ?></span><span class="val">' + fmtNum(r.distance_km, 1) + ' km</span></div>'
            + '<div class="row"><span class="label">⏱️ <?= __('Czas jazdy') ?></span><span class="val">' + fmtDur(r.duration_min) + '</span></div>'
            + '<div class="row"><span class="label">🚛 <?= __('Pojazd') ?></span><span class="val">' + (vehicle ? escapeHtml(vehicle.name) + (vehicle.plate ? ' (' + escapeHtml(vehicle.plate) + ')' : '') : '<?= __('osobowy') ?>') + '</span></div>'
            + (r.tolls_total ? '<div class="row"><span class="label">💰 <?= __('Opłaty drogowe') ?></span><span class="val">' + fmtNum(r.tolls_total, 2) + ' ' + (r.tolls_currency || 'EUR') + '</span></div>' : '')
            + '</div>'

            + (revenue > 0 ? '<h2>💵 <?= __('Cena') ?></h2><div class="box price"><div class="price-big">' + fmtNum(revenue, 2) + ' PLN</div><div class="muted" style="text-align:center"><?= __('netto, do uzgodnienia') ?></div></div>' : '')

            + '<h2>📋 <?= __('Warunki') ?></h2>'
            + '<div class="box muted">'
            + '• <?= __('Cena obejmuje transport, opłaty drogowe na trasie i ubezpieczenie OCP') ?><br>'
            + '• <?= __('Ważność oferty: 7 dni od daty wygenerowania') ?><br>'
            + '• <?= __('Płatność: przelew 14 dni od daty wystawienia faktury') ?><br>'
            + '• <?= __('Realizacja zgodnie z Konwencją CMR') ?>'
            + '</div>'

            + '<div style="margin-top:30px;padding-top:15px;border-top:1px solid #e5e7eb" class="muted">'
            + '<?= __('Dokument wygenerowany automatycznie przez Booklio TMS — booklio.pl') ?>'
            + '</div>'

            + '<button onclick="window.print()">🖨️ <?= __('Drukuj / Zapisz PDF') ?></button>'
            + '</body></html>'
        );
        win.document.close();
        setTimeout(function () { try { win.print(); } catch (e) {} }, 800);
    });

    document.getElementById('btn-export-ical').addEventListener('click', function (e) {
        e.preventDefault();
        if (!lastResponse) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        var ics = buildIcal();
        if (!ics) { toast('<?= __('Brak danych do eksportu.') ?>', 'warning'); return; }
        downloadFile('route-' + Date.now() + '.ics', ics, 'text/calendar;charset=utf-8');
        toast('<?= __('iCal wyeksportowany — otwórz w Outlook/Google Calendar') ?>', 'success');
    });
    document.getElementById('btn-embed-link').addEventListener('click', function (e) {
        e.preventDefault();
        var pts = waypoints.filter(function (w) { return w.lat != null && w.lng != null; });
        if (!pts.length) { toast('<?= __('Brak punktów.') ?>', 'warning'); return; }
        var payload = {
            points: pts.map(function (p) { return { address: p.address, lat: p.lat, lng: p.lng, country: p.country }; }),
            vehicle_id: document.getElementById('vehicle-id').value,
        };
        var enc = btoa(unescape(encodeURIComponent(JSON.stringify(payload))));
        var url = window.location.origin + window.location.pathname + '?embed=1&r=' + enc;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                toast('<?= __('Embed link skopiowany — wklej w iframe') ?>', 'success');
            }, function () { prompt('<?= __('Skopiuj link:') ?>', url); });
        } else { prompt('<?= __('Skopiuj link:') ?>', url); }
    });

    // Aktywuj export gdy mamy wynik
    function enableExportButton() {
        document.getElementById('btn-export').disabled = false;
    }

    // ═════════════════════════════════════════════════════════════════
    // Select2 dla waluty i wykluczonych krajów (z flagami)
    // ═════════════════════════════════════════════════════════════════
    (function initFlagSelect2() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;

        // Renderery używają data-cc z <option>, więc nie trzeba mapować w JS
        function flagFromOption(option) {
            if (!option || !option.element) return null;
            return option.element.getAttribute('data-cc');
        }
        function tplCurrency(state) {
            if (!state.id) return state.text;
            var $el = jQuery(state.element);
            var cc   = flagFromOption(state) || '';
            var name = $el.attr('data-name') || '';
            var code = (state.id || '').toUpperCase();
            var html = '';
            if (cc) html += '<span class="fi fi-' + cc + '"></span>';
            html += '<strong>' + code + '</strong>';
            if (name) html += ' <span class="text-muted small">' + name + '</span>';
            return jQuery('<span>' + html + '</span>');
        }
        function tplCurrencySelected(state) {
            if (!state.id) return state.text;
            var cc = flagFromOption(state) || '';
            var code = (state.id || '').toUpperCase();
            return jQuery('<span>' + (cc ? '<span class="fi fi-' + cc + '"></span>' : '') + code + '</span>');
        }
        function tplCountry(state) {
            if (!state.id) return state.text;
            var cc = flagFromOption(state) || '';
            return jQuery('<span>' + (cc ? '<span class="fi fi-' + cc + '"></span>' : '') + (state.text || '') + '</span>');
        }

        jQuery('#currency').select2({
            width: '100%',
            minimumResultsForSearch: Infinity,
            templateResult: tplCurrency,
            templateSelection: tplCurrencySelected,
            dropdownParent: jQuery('body')
        });

        jQuery('#exclude-countries').select2({
            width: '100%',
            placeholder: jQuery('#exclude-countries').attr('data-placeholder') || '',
            allowClear: false,
            closeOnSelect: false,
            templateResult: tplCountry,
            templateSelection: tplCountry,
            dropdownParent: jQuery('body')
        });

        // ── ADR Select2 z ikonami Remixicon ──────────────────────────
        function tplAdr(state) {
            if (!state.id && !state.element) return state.text;
            var $el  = state.element ? jQuery(state.element) : null;
            var icon = $el ? $el.attr('data-icon')  : null;
            var col  = $el ? $el.attr('data-color') : null;
            var html = '';
            if (icon) html += '<i class="' + icon + '" style="color:' + (col || '#374151') + ';margin-right:.4em;font-size:1.05em;vertical-align:middle"></i>';
            html += '<span>' + (state.text || '') + '</span>';
            return jQuery('<span>' + html + '</span>');
        }
        jQuery('#adr-class').select2({
            width: '100%',
            minimumResultsForSearch: Infinity,
            templateResult: tplAdr,
            templateSelection: tplAdr,
            dropdownParent: jQuery('body')
        });
    })();

    // ═════════════════════════════════════════════════════════════════
    // AI FICZERY (OpenAI gpt-4o-mini)
    // ═════════════════════════════════════════════════════════════════
    var aiUrls = {
        parse:     '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'aiParseAddress']) ?>',
        cargo:     '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'aiCargoWizard']) ?>',
        pricing:   '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'aiPricing']) ?>',
        brief:     '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'aiDriverBrief']) ?>',
        optimizer: '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'aiRouteOptimizer']) ?>',
        email:     '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'aiEmailReply']) ?>',
        delay:     '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'aiDelayPrediction']) ?>'
    };
    function aiPost(url, payload) {
        var fd = new FormData();
        Object.keys(payload).forEach(function (k) {
            var v = payload[k];
            if (v && typeof v === 'object') {
                fd.append(k, JSON.stringify(v));
            } else {
                fd.append(k, String(v == null ? '' : v));
            }
        });
        fd.append('_csrfToken', csrf);
        return fetch(url, {
            method: 'POST', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
    }

    // ── AI Address Parser ────────────────────────────────────────────
    document.getElementById('btn-ai-parser-run').addEventListener('click', function () {
        var btn = this;
        var text = document.getElementById('ai-parser-input').value.trim();
        if (text.length < 5) { toast('<?= __('Wklej dłuższy tekst.') ?>', 'warning'); return; }
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= __('Analizuję…') ?>';
        var resBox = document.getElementById('ai-parser-result');
        resBox.style.display = 'none';

        aiPost(aiUrls.parse, { text: text }).then(function (res) {
            if (!res.ok || !res.data.ok) {
                toast(res.data.message || '<?= __('Błąd AI.') ?>', 'error');
                return;
            }
            var d = res.data.data;
            var html = '<div class="alert alert-success py-2 mb-2 small"><i class="ri-checkbox-circle-line me-1"></i><?= __('Rozpoznano') ?> ' + (d.points||[]).length + ' <?= __('punktów') ?></div>';
            html += '<ul class="list-group small mb-2">';
            (d.points || []).forEach(function (p, i) {
                var addrParts = [];
                if (p.postal_code) addrParts.push('<span class="badge bg-light text-primary border me-1">' + escapeHtml(p.postal_code) + '</span>');
                addrParts.push(escapeHtml(p.address || ''));
                var countryBadge = p.country
                    ? ' <span class="fi fi-' + escapeHtml(p.country.toLowerCase()) + '" title="' + escapeHtml(p.country) + '"></span>'
                    : '';
                html += '<li class="list-group-item d-flex justify-content-between">'
                     + '<span><strong>' + String.fromCharCode(65+i) + '.</strong> ' + addrParts.join(' ') + countryBadge + '</span>'
                     + (p.date ? '<span class="text-muted">' + escapeHtml(p.date) + '</span>' : '')
                     + '</li>';
            });
            html += '</ul>';
            var meta = [];
            if (d.cargo_weight_kg) meta.push('<strong>' + d.cargo_weight_kg + ' kg</strong>');
            if (d.cargo_description) meta.push(escapeHtml(d.cargo_description));
            if (d.adr_class) meta.push('<span class="badge bg-danger-subtle text-danger border">ADR ' + d.adr_class + '</span>');
            if (d.vehicle_type_hint) meta.push('<span class="badge bg-info-subtle text-info border">' + escapeHtml(d.vehicle_type_hint) + '</span>');
            if (meta.length) html += '<div class="small mb-2"><strong><?= __('Ładunek:') ?></strong> ' + meta.join(' · ') + '</div>';
            if (d.special_notes) html += '<div class="small text-muted"><i class="ri-information-line me-1"></i>' + escapeHtml(d.special_notes) + '</div>';
            html += '<button class="btn btn-primary btn-sm mt-2" id="btn-ai-parser-apply"><i class="ri-check-line me-1"></i><?= __('Zastosuj do formularza') ?></button>';
            resBox.innerHTML = html;
            resBox.style.display = 'block';

            document.getElementById('btn-ai-parser-apply').addEventListener('click', function () {
                if (!d.points || d.points.length < 2) { toast('<?= __('Za mało punktów.') ?>', 'warning'); return; }
                waypoints = d.points.map(function (p) {
                    var date = '';
                    if (p.date) {
                        // Akceptuj YYYY-MM-DDTHH:MM, YYYY-MM-DD, lub dowolny format
                        date = p.date.length >= 16 ? p.date.substring(0, 16) : (p.date.length === 10 ? p.date + 'T08:00' : '');
                    }
                    // Address może już zawierać kod pocztowy — jeśli nie, doklej z osobnego pola
                    var fullAddr = p.address || '';
                    if (p.postal_code && fullAddr.indexOf(p.postal_code) === -1) {
                        // Doklej kod pocztowy przed miastem dla lepszego geocoding'u
                        fullAddr = p.postal_code + ' ' + fullAddr;
                    }
                    return {
                        address: fullAddr,
                        label: fullAddr,
                        lat: null, lng: null,
                        country: p.country ? p.country.toUpperCase() : '',
                        date: date
                    };
                });
                // ADR class
                if (d.adr_class) document.getElementById('adr-class').value = String(d.adr_class);
                renderWaypoints();
                // Geocoding pierwszej kalkulacji odbędzie się przy 'Wyznacz trasę'
                bootstrap.Modal.getInstance(document.getElementById('aiParserModal')).hide();
                toast('<?= __('Trasa wczytana z AI') ?>', 'success');
                // Auto-trigger calc
                setTimeout(function () { document.getElementById('btn-calc').click(); }, 200);
            });
        }).catch(function (e) {
            toast('<?= __('Błąd AI:') ?> ' + e.message, 'error');
        }).finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-sparkling-2-line me-1"></i><?= __('Analizuj') ?>';
        });
    });

    // ── AI Cargo Wizard ──────────────────────────────────────────────
    document.getElementById('btn-ai-cargo-run').addEventListener('click', function () {
        var btn = this;
        var desc = document.getElementById('ai-cargo-input').value.trim();
        if (desc.length < 3) { toast('<?= __('Wpisz opis ładunku.') ?>', 'warning'); return; }
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= __('Analizuję…') ?>';
        var resBox = document.getElementById('ai-cargo-result');
        resBox.style.display = 'none';

        aiPost(aiUrls.cargo, { description: desc }).then(function (res) {
            if (!res.ok || !res.data.ok) { toast(res.data.message || '<?= __('Błąd AI.') ?>', 'error'); return; }
            var d = res.data.data;
            var html = '';
            if (d.summary) html += '<div class="alert alert-info py-2 mb-2 small"><i class="ri-lightbulb-line me-1"></i>' + escapeHtml(d.summary) + '</div>';
            var rows = [];
            if (d.adr_class) rows.push(['<?= __('Klasa ADR') ?>', '<span class="badge bg-danger-subtle text-danger border">' + d.adr_class + (d.adr_class_name ? ' — ' + escapeHtml(d.adr_class_name) : '') + '</span>']);
            if (d.recommended_vehicle) rows.push(['<?= __('Sugerowany pojazd') ?>', '<span class="badge bg-info-subtle text-info border">' + escapeHtml(d.recommended_vehicle) + '</span>']);
            if (d.tonnage_estimate_kg) rows.push(['<?= __('Szacowany tonaż') ?>', '<strong>' + fmtNum(d.tonnage_estimate_kg / 1000, 1) + ' t</strong>']);
            if (rows.length) {
                html += '<table class="table table-sm mb-2">';
                rows.forEach(function (r) { html += '<tr><td class="text-muted">' + r[0] + '</td><td>' + r[1] + '</td></tr>'; });
                html += '</table>';
            }
            if (d.special_requirements && d.special_requirements.length) {
                html += '<div class="small mb-2"><strong><?= __('Wymagania:') ?></strong><ul class="mb-0">'
                     + d.special_requirements.map(function (x) { return '<li>' + escapeHtml(x) + '</li>'; }).join('') + '</ul></div>';
            }
            if (d.warnings && d.warnings.length) {
                html += '<div class="alert alert-warning py-2 mb-2 small"><strong><i class="ri-alert-line me-1"></i><?= __('Ostrzeżenia:') ?></strong><ul class="mb-0">'
                     + d.warnings.map(function (x) { return '<li>' + escapeHtml(x) + '</li>'; }).join('') + '</ul></div>';
            }
            if (d.adr_class) html += '<button class="btn btn-success btn-sm" id="btn-ai-cargo-apply"><i class="ri-check-line me-1"></i><?= __('Ustaw ADR') ?> ' + d.adr_class + '</button>';
            resBox.innerHTML = html;
            resBox.style.display = 'block';
            var applyBtn = document.getElementById('btn-ai-cargo-apply');
            if (applyBtn) applyBtn.addEventListener('click', function () {
                document.getElementById('adr-class').value = String(d.adr_class);
                bootstrap.Modal.getInstance(document.getElementById('aiCargoModal')).hide();
                toast('<?= __('ADR ustawiony') ?>: ' + d.adr_class, 'success');
            });
        }).catch(function (e) { toast('<?= __('Błąd AI:') ?> ' + e.message, 'error'); })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-sparkling-2-line me-1"></i><?= __('Analizuj') ?>';
        });
    });

    // ── AI Pricing Advisor ───────────────────────────────────────────
    document.getElementById('btn-ai-pricing-open').addEventListener('click', function (e) {
        e.preventDefault();
        if (!lastResponse) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        var r = lastResponse.routes[activeAltIdx];
        var vehicle = getSelectedVehicle();
        var countries = (lastResponse.points || []).map(function (p) { return p.country || ''; }).filter(function (x) { return x; });
        var cons = parseFloat(document.getElementById('fuel-consumption').value || 0);
        var price = parseFloat(document.getElementById('fuel-price').value || 0);
        var rate = parseFloat(document.getElementById('driver-rate').value || 0);
        var fuelPln = (r.distance_km / 100) * cons * price;
        var driverPln = (r.duration_min / 60) * rate;
        var tollsPln = r.tolls_total
            ? ((r.tolls_currency === 'EUR' && lastResponse.eur_pln_rate) ? r.tolls_total * lastResponse.eur_pln_rate : r.tolls_total)
            : 0;
        var context = {
            distance_km: r.distance_km,
            duration_min: r.duration_min,
            tolls_pln: Math.round(tollsPln * 100) / 100,
            fuel_pln: Math.round(fuelPln * 100) / 100,
            driver_pln: Math.round(driverPln * 100) / 100,
            vehicle: vehicle ? (vehicle.name + ' ' + (vehicle.gross_weight_kg ? Math.round(vehicle.gross_weight_kg/1000) + 't' : '')) : 'osobowy',
            rate_per_km: vehicle && vehicle.rate_per_km ? vehicle.rate_per_km : null,
            countries: countries.join(','),
            date: new Date().toISOString().substring(0, 10),
            adr_class: document.getElementById('adr-class').value || null,
        };
        var modal = new bootstrap.Modal(document.getElementById('aiPricingModal'));
        modal.show();
        document.getElementById('ai-pricing-loading').style.display = 'block';
        document.getElementById('ai-pricing-result').style.display = 'none';

        aiPost(aiUrls.pricing, { context: context }).then(function (res) {
            document.getElementById('ai-pricing-loading').style.display = 'none';
            if (!res.ok || !res.data.ok) {
                document.getElementById('ai-pricing-result').innerHTML = '<div class="alert alert-danger">' + escapeHtml(res.data.message || '<?= __('Błąd AI.') ?>') + '</div>';
                document.getElementById('ai-pricing-result').style.display = 'block';
                return;
            }
            var d = res.data.data;
            var compBadge = { low: '<span class="badge bg-danger">Nisko</span>', fair: '<span class="badge bg-success">Fair</span>', high: '<span class="badge bg-warning">Wysoko</span>' }[d.competitiveness] || '';
            var html = '<div class="text-center mb-3">'
                     + '<div class="text-muted small text-uppercase" style="letter-spacing:.6px">AI sugerowana cena</div>'
                     + '<div style="font-size:2.8rem;font-weight:700;color:#db2777">' + fmtNum(d.suggested_price_pln, 2) + ' <small class="text-muted" style="font-size:1rem">PLN</small></div>'
                     + '<div class="text-muted">' + fmtNum(d.price_per_km_pln, 2) + ' PLN/km · marża ' + fmtNum(d.margin_percent, 0) + '% · ' + compBadge + '</div>'
                     + '</div>';
            if (d.reasoning) html += '<div class="alert alert-light border mb-2"><i class="ri-quill-pen-line me-1"></i>' + escapeHtml(d.reasoning) + '</div>';
            if (d.factors && d.factors.length) {
                html += '<div class="small fw-semibold mb-1 text-muted"><?= __('Czynniki') ?>:</div>';
                html += '<ul class="small mb-0">' + d.factors.map(function (f) { return '<li>' + escapeHtml(f) + '</li>'; }).join('') + '</ul>';
            }
            document.getElementById('ai-pricing-result').innerHTML = html;
            document.getElementById('ai-pricing-result').style.display = 'block';
        }).catch(function (e) {
            document.getElementById('ai-pricing-loading').style.display = 'none';
            document.getElementById('ai-pricing-result').innerHTML = '<div class="alert alert-danger">' + escapeHtml(e.message) + '</div>';
            document.getElementById('ai-pricing-result').style.display = 'block';
        });
    });

    // ── AI Driver Brief ──────────────────────────────────────────────
    function openBriefModal() {
        var modal = new bootstrap.Modal(document.getElementById('aiBriefModal'));
        modal.show();
    }
    document.getElementById('btn-ai-brief-open').addEventListener('click', function (e) {
        e.preventDefault();
        if (!lastResponse) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        openBriefModal();
    });
    document.getElementById('btn-ai-brief-run').addEventListener('click', function () {
        var btn = this;
        var lang = document.getElementById('ai-brief-lang').value;
        if (!lastResponse) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        var r = lastResponse.routes[activeAltIdx];
        var vehicle = getSelectedVehicle();
        var context = {
            points: (lastResponse.points || []).map(function (p, i) {
                var wp = waypoints[i] || {};
                return { letter: String.fromCharCode(65+i), address: p.label || p.address, country: p.country, date: wp.date || '' };
            }),
            distance_km: r.distance_km,
            duration_min: r.duration_min,
            tolls_total: r.tolls_total,
            tolls_currency: r.tolls_currency,
            sections: r.sections || [],
            vehicle: vehicle ? (vehicle.name + (vehicle.plate ? ' (' + vehicle.plate + ')' : '')) : 'osobowy',
            adr_class: document.getElementById('adr-class').value || null,
        };
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
        document.getElementById('ai-brief-loading').style.display = 'block';
        document.getElementById('ai-brief-result').style.display = 'none';
        document.getElementById('btn-ai-brief-copy').style.display = 'none';

        aiPost(aiUrls.brief, { context: context, language: lang }).then(function (res) {
            document.getElementById('ai-brief-loading').style.display = 'none';
            if (!res.ok || !res.data.ok) {
                toast(res.data.message || '<?= __('Błąd AI.') ?>', 'error');
                return;
            }
            var pre = document.getElementById('ai-brief-result');
            pre.textContent = res.data.brief;
            pre.style.display = 'block';
            document.getElementById('btn-ai-brief-copy').style.display = '';
            document.getElementById('btn-ai-brief-qr').style.display = '';
        }).catch(function (e) {
            document.getElementById('ai-brief-loading').style.display = 'none';
            toast('<?= __('Błąd AI:') ?> ' + e.message, 'error');
        }).finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-sparkling-2-line me-1"></i><?= __('Generuj') ?>';
        });
    });
    document.getElementById('btn-ai-brief-copy').addEventListener('click', function () {
        var text = document.getElementById('ai-brief-result').textContent;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                toast('<?= __('Brief skopiowany') ?>', 'success');
            });
        }
    });

    // #16 — QR brief dla kierowcy
    document.getElementById('btn-ai-brief-qr').addEventListener('click', function () {
        var briefText = document.getElementById('ai-brief-result').textContent;
        if (!briefText) { toast('<?= __('Najpierw wygeneruj brief.') ?>', 'warning'); return; }
        // QR ma limit ~2900 znaków dla Version 40 (ECC L). Trimujemy jeśli za długi.
        var qrPayload = briefText.length > 2500 ? briefText.substring(0, 2497) + '...' : briefText;
        var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=M&format=png&data='
                  + encodeURIComponent(qrPayload);
        var wrapper = document.getElementById('qr-image-wrapper');
        wrapper.innerHTML = '<div class="spinner-border text-info"></div>';
        var img = new Image();
        img.onload = function () {
            wrapper.innerHTML = '';
            img.style.maxWidth = '100%';
            img.style.height = 'auto';
            img.id = 'qr-image';
            wrapper.appendChild(img);
        };
        img.onerror = function () {
            wrapper.innerHTML = '<div class="text-danger"><?= __('Błąd generowania QR') ?></div>';
        };
        img.src = qrUrl;
        new bootstrap.Modal(document.getElementById('qrBriefModal')).show();
    });

    document.getElementById('btn-qr-print').addEventListener('click', function () {
        var img = document.getElementById('qr-image');
        if (!img) return;
        var w = window.open('', '_blank', 'width=500,height=600');
        if (!w) { toast('<?= __('Włącz wyskakujące okna') ?>', 'warning'); return; }
        var pts = (lastResponse && lastResponse.points) || [];
        var routeLabel = pts.map(function (p) { return p.label || p.address || ''; }).filter(Boolean).join(' → ');
        w.document.write(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>QR Brief</title>'
            + '<style>body{font-family:Arial;text-align:center;padding:30px;color:#111}'
            + 'h1{font-size:18px;margin:0 0 8px}h2{font-size:13px;color:#6b7280;font-weight:400;margin:0 0 24px}'
            + 'img{max-width:400px;border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:white}'
            + '.note{margin-top:20px;font-size:11px;color:#6b7280}'
            + '@media print{button{display:none}}</style></head><body>'
            + '<h1>🚛 Brief dla kierowcy</h1>'
            + '<h2>' + (routeLabel ? routeLabel.replace(/</g, '&lt;') : '') + '</h2>'
            + '<img src="' + img.src + '">'
            + '<div class="note">Zeskanuj telefonem aby otworzyć brief · Booklio TMS</div>'
            + '<button onclick="window.print()" style="margin-top:30px;padding:8px 24px">Drukuj</button>'
            + '</body></html>'
        );
        w.document.close();
        setTimeout(function () { w.print(); }, 400);
    });

    document.getElementById('btn-qr-download').addEventListener('click', function () {
        var img = document.getElementById('qr-image');
        if (!img) return;
        var a = document.createElement('a');
        a.href = img.src;
        a.download = 'qr-brief-' + Date.now() + '.png';
        a.target = '_blank';
        a.click();
    });

    // ── #12 AI Route Optimizer ───────────────────────────────────────
    document.getElementById('btn-ai-optimizer-open').addEventListener('click', function (e) {
        e.preventDefault();
        if (!lastResponse || !lastResponse.routes || lastResponse.routes.length < 2) {
            toast('<?= __('Najpierw wyznacz trasę z >=2 alternatywami.') ?>', 'warning');
            return;
        }
        new bootstrap.Modal(document.getElementById('aiOptimizerModal')).show();
    });
    document.getElementById('btn-ai-optimizer-run').addEventListener('click', function () {
        var btn = this;
        if (!lastResponse) return;
        var alts = lastResponse.routes.map(function (r, idx) {
            var cons = parseFloat(document.getElementById('fuel-consumption').value || 0);
            var price = parseFloat(document.getElementById('fuel-price').value || 0);
            var rate = parseFloat(document.getElementById('driver-rate').value || 0);
            var fuelPln = (r.distance_km / 100) * cons * price;
            var driverPln = (r.duration_min / 60) * rate;
            var tollsPln = r.tolls_total
                ? ((r.tolls_currency === 'EUR' && lastResponse.eur_pln_rate) ? r.tolls_total * lastResponse.eur_pln_rate : r.tolls_total)
                : 0;
            return {
                idx: idx,
                distance_km: r.distance_km,
                duration_min: r.duration_min,
                tolls_pln: Math.round(tollsPln),
                fuel_pln: Math.round(fuelPln),
                driver_pln: Math.round(driverPln),
                total_cost_pln: Math.round(tollsPln + fuelPln + driverPln),
                countries: (lastResponse.points || []).map(function (p) { return p.country; }).filter(Boolean).join(','),
                tolls_by_country: r.tolls_by_country || {}
            };
        });
        var criteria = { priority: document.getElementById('ai-optimizer-priority').value };
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
        document.getElementById('ai-optimizer-loading').style.display = 'block';
        document.getElementById('ai-optimizer-result').style.display = 'none';

        aiPost(aiUrls.optimizer, { alternatives: alts, criteria: criteria }).then(function (res) {
            document.getElementById('ai-optimizer-loading').style.display = 'none';
            if (!res.ok || !res.data.ok) { toast(res.data.message || '<?= __('Błąd AI.') ?>', 'error'); return; }
            var d = res.data.data;
            var winner = d.recommended_idx;
            var html = '<div class="alert alert-info py-2 mb-3"><i class="ri-checkbox-circle-line me-1"></i>'
                     + '<strong><?= __('Polecam') ?>: ' + '<?= __('Trasa') ?> ' + (winner === 0 ? '<?= __('główna') ?>' : '<?= __('alternatywa') ?> ' + winner)
                     + '</strong></div>';
            html += '<div class="small mb-3"><i class="ri-quill-pen-line me-1"></i>' + escapeHtml(d.reasoning || '') + '</div>';
            html += '<table class="table table-sm"><thead><tr><th>#</th><th><?= __('Szybkość') ?></th><th><?= __('Koszt') ?></th><th><?= __('Komfort') ?></th><th><?= __('Bezpieczeństwo') ?></th><th><?= __('Razem') ?></th><th></th></tr></thead><tbody>';
            (d.scores || []).forEach(function (s) {
                var isW = s.idx === winner;
                html += '<tr' + (isW ? ' class="table-success"' : '') + '>'
                     + '<td><strong>' + s.idx + '</strong>' + (isW ? ' 🏆' : '') + '</td>'
                     + '<td>' + s.speed + '/10</td>'
                     + '<td>' + s.cost + '/10</td>'
                     + '<td>' + s.comfort + '/10</td>'
                     + '<td>' + s.risk + '/10</td>'
                     + '<td><strong>' + (s.overall || 0).toFixed(1) + '</strong></td>'
                     + '<td class="small text-muted">' + escapeHtml(s.note || '') + '</td>'
                     + '</tr>';
            });
            html += '</tbody></table>';
            html += '<button class="btn btn-info text-white btn-sm" id="btn-ai-optimizer-apply"><i class="ri-check-line me-1"></i><?= __('Zastosuj wybór') ?></button>';
            document.getElementById('ai-optimizer-result').innerHTML = html;
            document.getElementById('ai-optimizer-result').style.display = 'block';
            document.getElementById('btn-ai-optimizer-apply').addEventListener('click', function () {
                // Kliknij odpowiednią alternatywę z listy
                var altCards = document.querySelectorAll('.alt-route-card');
                if (altCards[winner]) altCards[winner].click();
                bootstrap.Modal.getInstance(document.getElementById('aiOptimizerModal')).hide();
                toast('<?= __('Zastosowano rekomendację AI') ?>', 'success');
            });
        }).catch(function (e) { toast('<?= __('Błąd AI:') ?> ' + e.message, 'error'); })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-sparkling-2-line me-1"></i><?= __('Analizuj') ?>';
        });
    });

    // ── #11 AI Email Reply ───────────────────────────────────────────
    document.getElementById('btn-ai-email-run').addEventListener('click', function () {
        var btn = this;
        var emailText = document.getElementById('ai-email-input').value.trim();
        if (emailText.length < 10) { toast('<?= __('Wklej dłuższy email.') ?>', 'warning'); return; }
        var routeCtx = null;
        if (lastResponse && lastResponse.routes && lastResponse.routes[0]) {
            var r = lastResponse.routes[0];
            routeCtx = {
                from: (lastResponse.points || [])[0],
                to: (lastResponse.points || [])[(lastResponse.points || []).length - 1],
                distance_km: r.distance_km,
                duration_min: r.duration_min,
                price_pln: parseFloat(document.getElementById('freight-revenue').value || 0) || null,
            };
        }
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';
        document.getElementById('ai-email-loading').style.display = 'block';
        document.getElementById('ai-email-result').style.display = 'none';

        aiPost(aiUrls.email, { email: emailText, route: routeCtx || {} }).then(function (res) {
            document.getElementById('ai-email-loading').style.display = 'none';
            if (!res.ok || !res.data.ok) { toast(res.data.message || '<?= __('Błąd AI.') ?>', 'error'); return; }
            var d = res.data.data;
            var intentLabels = {
                'quote_request': '<span class="badge bg-primary">📋 <?= __('Zapytanie ofertowe') ?></span>',
                'status_check': '<span class="badge bg-info">📍 <?= __('Sprawdzenie statusu') ?></span>',
                'complaint': '<span class="badge bg-warning">⚠️ <?= __('Reklamacja') ?></span>',
                'other': '<span class="badge bg-secondary">📨 <?= __('Inne') ?></span>'
            };
            var html = '<div class="mb-3">' + (intentLabels[d.intent] || intentLabels.other);
            if (d.next_action) html += ' <span class="text-muted small ms-2">→ ' + escapeHtml(d.next_action) + '</span>';
            html += '</div>';
            html += '<div class="mb-2"><strong>' + escapeHtml(d.subject || '') + '</strong></div>';
            html += '<ul class="nav nav-tabs nav-tabs-sm mb-2">'
                  + '<li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#email-tab-pl">🇵🇱 PL</a></li>'
                  + '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#email-tab-en">🇬🇧 EN</a></li>'
                  + '</ul>'
                  + '<div class="tab-content">'
                  + '<div class="tab-pane fade show active" id="email-tab-pl"><pre class="p-3 bg-light border rounded" style="white-space:pre-wrap;font-family:inherit;font-size:.85rem">' + escapeHtml(d.body_pl || '') + '</pre></div>'
                  + '<div class="tab-pane fade" id="email-tab-en"><pre class="p-3 bg-light border rounded" style="white-space:pre-wrap;font-family:inherit;font-size:.85rem">' + escapeHtml(d.body_en || '') + '</pre></div>'
                  + '</div>';
            html += '<button class="btn btn-secondary btn-sm mt-2" id="btn-ai-email-copy"><i class="ri-clipboard-line me-1"></i><?= __('Kopiuj odpowiedź') ?></button>';
            document.getElementById('ai-email-result').innerHTML = html;
            document.getElementById('ai-email-result').style.display = 'block';
            document.getElementById('btn-ai-email-copy').addEventListener('click', function () {
                var active = document.querySelector('#ai-email-result .tab-pane.active pre');
                var text = active ? active.textContent : (d.body_pl || '');
                if (navigator.clipboard) navigator.clipboard.writeText(text).then(function () {
                    toast('<?= __('Skopiowane') ?>', 'success');
                });
            });
        }).catch(function (e) { toast('<?= __('Błąd AI:') ?> ' + e.message, 'error'); })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-sparkling-2-line me-1"></i><?= __('Generuj odpowiedź') ?>';
        });
    });

    // ── #13 AI Delay Prediction ─────────────────────────────────────
    document.getElementById('btn-ai-delay-open').addEventListener('click', function (e) {
        e.preventDefault();
        if (!lastResponse) { toast('<?= __('Najpierw wyznacz trasę.') ?>', 'warning'); return; }
        var r = lastResponse.routes[activeAltIdx];
        var dep = (r.sections && r.sections[0] && r.sections[0].departure_time)
            ? new Date(r.sections[0].departure_time) : new Date();
        var month = dep.getMonth() + 1;
        var season = (month >= 12 || month <= 2) ? 'zima' : (month <= 5) ? 'wiosna' : (month <= 8) ? 'lato' : 'jesień';
        var dayOfWeek = ['niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota'][dep.getDay()];

        var countries = Object.keys(r.tolls_by_country || {});
        // Dodaj z waypoints
        (lastResponse.points || []).forEach(function (p) {
            if (p.country && countries.indexOf(p.country) === -1) countries.push(p.country);
        });

        // Liczba przekroczeń granic = liczba unikatowych krajów - 1
        var bordersCount = Math.max(0, countries.length - 1);

        var context = {
            distance_km: r.distance_km,
            duration_min_here: r.duration_min,
            departure_time: dep.toISOString(),
            countries: countries.join(','),
            border_crossings: bordersCount,
            adr_class: document.getElementById('adr-class').value || null,
            season: season,
            day_of_week: dayOfWeek,
            hour: dep.getHours(),
            stops_count: (lastResponse.points || []).length,
        };

        new bootstrap.Modal(document.getElementById('aiDelayModal')).show();
        document.getElementById('ai-delay-loading').style.display = 'block';
        document.getElementById('ai-delay-result').style.display = 'none';

        aiPost(aiUrls.delay, { context: context }).then(function (res) {
            document.getElementById('ai-delay-loading').style.display = 'none';
            if (!res.ok || !res.data.ok) {
                toast(res.data.message || '<?= __('Błąd AI.') ?>', 'error');
                return;
            }
            var d = res.data.data;
            var delayClass = d.delay_min > 60 ? 'danger' : d.delay_min > 30 ? 'warning' : d.delay_min > 0 ? 'info' : 'success';
            var hereH = Math.floor(r.duration_min / 60);
            var hereM = r.duration_min % 60;
            var predH = Math.floor(d.predicted_total_min / 60);
            var predM = d.predicted_total_min % 60;
            var delaySign = d.delay_min > 0 ? '+' : '';

            var factorTypeIcons = {
                'traffic': '🚦', 'border': '🛂', 'weather': '🌧️',
                'loading': '📦', 'rest': '😴', 'ban': '⛔'
            };
            var factorsHtml = (d.factors || []).map(function (f) {
                var icon = factorTypeIcons[f.type] || '⏱️';
                return '<tr>'
                    + '<td>' + icon + ' ' + escapeHtml(f.name) + '</td>'
                    + '<td class="text-end"><strong>+' + f.impact_min + ' min</strong></td>'
                    + '</tr>';
            }).join('');

            var html = ''
                + '<div class="row g-3 mb-3">'
                +   '<div class="col-6"><div class="card border-secondary"><div class="card-body text-center py-3">'
                +     '<div class="text-muted small">HERE Maps ETA</div>'
                +     '<div class="display-6 fw-bold">' + hereH + 'h ' + hereM + 'min</div>'
                +     '<div class="text-muted small">' + fmtNum(r.distance_km, 1) + ' km</div>'
                +   '</div></div></div>'
                +   '<div class="col-6"><div class="card border-' + delayClass + '"><div class="card-body text-center py-3">'
                +     '<div class="text-' + delayClass + ' small fw-bold">AI predykcja</div>'
                +     '<div class="display-6 fw-bold text-' + delayClass + '">' + predH + 'h ' + predM + 'min</div>'
                +     '<div class="text-' + delayClass + ' small">' + delaySign + d.delay_min + ' min (' + delaySign + fmtNum(d.delay_pct || 0, 1) + '%)</div>'
                +   '</div></div></div>'
                + '</div>'
                + '<div class="alert alert-light border mb-3 small"><i class="ri-quill-pen-line me-1"></i>' + escapeHtml(d.reasoning || '') + '</div>'
                + (factorsHtml ? '<table class="table table-sm mb-3"><thead><tr><th><?= __('Czynnik') ?></th><th class="text-end"><?= __('Wpływ') ?></th></tr></thead><tbody>' + factorsHtml + '</tbody></table>' : '')
                + (d.recommendation ? '<div class="alert alert-success py-2 small"><i class="ri-lightbulb-line me-1"></i><strong><?= __('Rekomendacja') ?>:</strong> ' + escapeHtml(d.recommendation) + '</div>' : '')
                + '<div class="text-muted small text-end"><?= __('Pewność predykcji') ?>: <span class="badge bg-secondary">' + (d.confidence || 'medium') + '</span></div>';
            document.getElementById('ai-delay-result').innerHTML = html;
            document.getElementById('ai-delay-result').style.display = 'block';
        }).catch(function (e) {
            document.getElementById('ai-delay-loading').style.display = 'none';
            toast('<?= __('Błąd AI:') ?> ' + e.message, 'error');
        });
    });

    // Aktywuj/dezaktywuj AI buttons zależnie od stanu trasy
    function refreshAiButtons() {
        var has = !!lastResponse;
        document.querySelectorAll('.ai-needs-route').forEach(function (el) {
            el.classList.toggle('disabled', !has);
            el.style.opacity = has ? '1' : '.5';
            el.style.pointerEvents = has ? '' : 'none';
        });
    }
    refreshAiButtons();

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
            if (wp.date)  fd.append('points[' + idx + '][date]', wp.date); // C: daty per waypoint
        });
        // A: jeśli hero "Wyjazd" puste, a pierwszy punkt ma datę — użyj jej jako start
        if (!departureTime && pts[0] && pts[0].date) {
            try {
                departureTime = new Date(pts[0].date).toISOString();
            } catch (_) { /* invalid date — pomiń */ }
        }
        fd.append('vehicle_id', vehicleId);
        // Hogis-style: jeśli wybrana naczepa → wysyłaj jej ID do backendu który sumuje wagę/osie z ciągnikiem
        var trailerSel = document.getElementById('trailer-id');
        if (trailerSel && trailerSel.value) fd.append('trailer_id', trailerSel.value);
        var driverSel = document.getElementById('driver-id');
        if (driverSel && driverSel.value) fd.append('driver_id', driverSel.value);
        fd.append('currency', currency);
        fd.append('alternatives', String(alternatives));
        fd.append('instructions', '1');
        if (departureTime) fd.append('departure_time', departureTime);
        avoid.forEach(function (a) { fd.append('avoid[]', a); });
        // ADR class
        var adrClass = document.getElementById('adr-class').value;
        if (adrClass) fd.append('adr_class', adrClass);
        // Wyklucz kraje
        var excludeCountries = Array.from(document.getElementById('exclude-countries').selectedOptions).map(function (o) { return o.value; });
        excludeCountries.forEach(function (cc) { fd.append('exclude_countries[]', cc); });
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
                date:    w.date    || '',
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
        { address: '', lat: null, lng: null, country: '', date: '' },
        { address: '', lat: null, lng: null, country: '', date: '' },
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

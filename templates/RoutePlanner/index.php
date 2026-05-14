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
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" id="btn-embed-link">
                        <i class="ri-code-line me-2 text-primary"></i><?= __('Embed (read-only)') ?></a></li>
                </ul>
            </div>
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
                    <div class="card-header py-2"><strong><i class="ri-git-branch-line me-1 text-primary"></i><?= __('Alternatywne trasy') ?></strong></div>
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
                                        <th class="text-end"><?= __('Cena') ?></th>
                                        <th><?= __('Płatność') ?></th>
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
    </div>
</div>

<!-- ═══════════════════════════════ AI MODALS ═══════════════════════════════ -->

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
          <?= __('Wklej tekst od klienta — email, wiadomość, opis trasy. AI wyciągnie waypoints, daty, ładunek i ADR.') ?>
        </div>
        <textarea class="form-control" id="ai-parser-input" rows="6"
                  placeholder="<?= __('np. Załadunek 15.05 Kraków ul. Wielicka 22, dostawa 16.05 do godz. 12:00 w Berlinie. 24 tony, ADR klasa 3 — diesel.') ?>"></textarea>
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
            'rate_per_km' => $v->rate_per_km !== null ? (float)$v->rate_per_km : null,
            'gross_weight_kg' => $v->gross_weight_kg,
        ];
    }, $vehicles), JSON_UNESCAPED_UNICODE) ?>;
    function getSelectedVehicle() {
        var id = document.getElementById('vehicle-id').value;
        return vehiclesData.find(function (v) { return v.id === id; }) || null;
    }
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
    map.addEventListener('dragstart', function (ev) {
        var target = ev.target;
        if (target instanceof H.map.Marker && target.draggable) {
            window.__rpDraggedMarker = target;
            // Wyłącz pan mapy podczas dragu markera
            map.getViewPort().element.style.cursor = 'grabbing';
            // Behavior zapisany globalnie:
            if (window.__rpBehavior) window.__rpBehavior.disable(H.mapevents.Behavior.Feature.DRAG_PAN);
        }
    });
    map.addEventListener('drag', function (ev) {
        var m = window.__rpDraggedMarker;
        if (!m) return;
        var p = ev.currentPointer;
        var coord = map.screenToGeo(p.viewportX, p.viewportY);
        if (coord) m.setGeometry(coord);
    });
    map.addEventListener('dragend', function (ev) {
        var m = window.__rpDraggedMarker;
        if (!m) return;
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
        (routeData.polylines || []).forEach(function (polyStr) {
            try {
                var line = H.geo.LineString.fromFlexiblePolyline(polyStr);
                var outline = new H.map.Polyline(line, { style: { strokeColor: outlineColor, lineWidth: lineWidth } });
                var stroke = new H.map.Polyline(line, { style: { strokeColor: strokeColor, lineWidth: lineWidth - 4 } });
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
        renderAlternatives(data.routes);
        renderDirections(data.routes[0]);
        renderLegs(data.routes[0], data.points || []);
        renderDistanceMarkers(data.routes[0]);
        renderBorderCrossings(data.routes[0], data.points || []);
        checkRestLawCompliance(data.routes[0]);
        renderEtaBadges(data.routes[0]);
        renderTollsBreakdown(data.routes[0]);
        renderTruckBans(data.routes[0], data.points || []);
        fetchAndRenderWeather(data.routes[0], data.points || []);

        // Aktywuj akcje
        document.getElementById('btn-print').disabled = false;
        document.getElementById('btn-share').disabled = false;
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

        // L1: Tabela opłat
        var tbody = document.getElementById('tolls-tbody');
        tbody.innerHTML = '';
        route.tolls_breakdown.forEach(function (f) {
            var nameCell = escapeHtml(f.name || (f.is_vignette ? '<?= __('Winieta') ?>' : '—'));
            if (f.is_vignette) nameCell = '<i class="ri-sticker-line text-warning me-1"></i>' + nameCell;
            var priceCell = '<strong>' + fmtNum(f.price, 2) + '</strong> ' + escapeHtml(f.currency);
            if (f.converted_price && f.converted_curr && f.converted_curr !== f.currency) {
                priceCell += '<div class="text-muted" style="font-size:.7rem">≈ ' + fmtNum(f.converted_price, 2) + ' ' + escapeHtml(f.converted_curr) + '</div>';
            }
            tbody.innerHTML +=
                '<tr>'
                + '<td>' + flagSpan(f.country) + '</td>'
                + '<td><span class="text-truncate d-inline-block" style="max-width:200px" title="' + escapeHtml(f.system) + '">' + escapeHtml(f.system || '—') + '</span></td>'
                + '<td>' + nameCell + '</td>'
                + '<td class="text-end">' + priceCell + '</td>'
                + '<td>' + paymentBadge(f.payment_method) + '</td>'
                + '</tr>';
        });

        // Footer: suma per country + total
        var tfoot = document.getElementById('tolls-tfoot');
        var countryRows = Object.keys(route.tolls_by_country || {}).map(function (cc) {
            return '<tr><td>' + flagSpan(cc) + '</td>'
                 + '<td colspan="2" class="text-muted">' + '<?= __('Suma') ?> ' + escapeHtml(cc) + '</td>'
                 + '<td class="text-end">' + fmtNum(route.tolls_by_country[cc], 2) + ' ' + escapeHtml(route.tolls_currency || 'EUR') + '</td>'
                 + '<td></td></tr>';
        }).join('');
        tfoot.innerHTML = countryRows
            + '<tr style="border-top:2px solid #e5e7eb">'
            + '<td colspan="3" class="text-end"><?= __('RAZEM') ?></td>'
            + '<td class="text-end fs-6">' + fmtNum(route.tolls_total || 0, 2) + ' ' + escapeHtml(route.tolls_currency || 'EUR') + '</td>'
            + '<td></td></tr>';

        card.style.display = '';

        // L3: ukryj przycisk "Bramki na mapie" jeśli HERE nie zwrócił lokalizacji
        var btnMarkers = document.getElementById('btn-toll-markers');
        if (btnMarkers) {
            btnMarkers.style.display = (currentTollsData.locations.length ? '' : 'none');
        }
        if (tollMarkersVisible) renderTollMarkers(true);
    }

    // ── L3: Markery bramek na mapie (toggle) ─────────────────────────
    function renderTollMarkers(show) {
        if (tollMarkersGroup) { map.removeObject(tollMarkersGroup); tollMarkersGroup = null; }
        if (!show || !currentTollsData || !currentTollsData.locations || !currentTollsData.locations.length) return;
        tollMarkersGroup = new H.map.Group();
        // Buduj indeks opłat per (country, system) żeby pokazać orientacyjną cenę w popupie
        var priceByKey = {};
        (currentTollsData.breakdown || []).forEach(function (f) {
            if (f.is_vignette) return;
            var k = (f.country || '') + '|' + (f.system || '');
            priceByKey[k] = (priceByKey[k] || 0) + (f.price || 0);
        });
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="32" viewBox="0 0 24 32">'
                + '<path d="M12 0C5 0 0 5 0 12c0 8 12 20 12 20s12-12 12-20C24 5 19 0 12 0z" fill="#fbbf24" stroke="white" stroke-width="2"/>'
                + '<text x="12" y="16" text-anchor="middle" font-size="11" font-weight="bold" fill="#92400e">€</text></svg>';
        var icon = new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 12, y: 32 } });
        currentTollsData.locations.forEach(function (loc) {
            var m = new H.map.Marker({ lat: loc.lat, lng: loc.lng }, { icon: icon });
            var k = (loc.country || '') + '|' + (loc.system || '');
            var p = priceByKey[k] ? (' · ~' + fmtNum(priceByKey[k], 2) + ' ' + currentTollsData.currency) : '';
            m.setData('<strong>' + escapeHtml(loc.name || '<?= __('Bramka') ?>') + '</strong>'
                    + '<div class="small text-muted">' + escapeHtml(loc.system || '') + ' · ' + escapeHtml(loc.country || '') + p + '</div>');
            tollMarkersGroup.addObject(m);
        });
        map.addObject(tollMarkersGroup);
    }

    // L3: toggle handler
    (function bindTollMarkers() {
        var btn = document.getElementById('btn-toll-markers');
        if (!btn) return;
        btn.addEventListener('click', function () {
            tollMarkersVisible = !tollMarkersVisible;
            renderTollMarkers(tollMarkersVisible);
            btn.classList.toggle('btn-outline-secondary', !tollMarkersVisible);
            btn.classList.toggle('btn-warning', tollMarkersVisible);
            btn.innerHTML = '<i class="ri-map-pin-line me-1"></i>' + (tollMarkersVisible ? '<?= __('Ukryj bramki') ?>' : '<?= __('Bramki na mapie') ?>');
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
                + '<div class="d-flex justify-content-between align-items-center mb-1"><strong class="small">' + name + '</strong>'
                + '<span class="badge bg-light text-dark border">' + fmtNum(r.distance_km, 1) + ' km</span></div>'
                + '<div class="d-flex justify-content-between small text-muted"><span><i class="ri-time-line"></i> ' + fmtDur(r.duration_min) + '</span>'
                + '<span><i class="ri-money-euro-circle-line"></i> ' + tolls + '</span></div></div>';
            list.insertAdjacentHTML('beforeend', html);
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
        email:     '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'aiEmailReply']) ?>'
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
                html += '<li class="list-group-item d-flex justify-content-between">'
                     + '<span><strong>' + String.fromCharCode(65+i) + '.</strong> ' + escapeHtml(p.address || '') + '</span>'
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
                    return { address: p.address, label: p.address, lat: null, lng: null, country: '', date: date };
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

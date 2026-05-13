<?php
/**
 * @var \App\View\AppView                                  $this
 * @var \App\Model\Entity\SpeedOrder                       $order
 * @var \App\Model\Entity\Invoice|null                     $invoice
 * @var \App\Model\Entity\SpeedOrderAttachment[]           $attachments
 * @var \App\Model\Entity\ClientProfile                    $clientProfile
 * @var string                                             $currentLocale
 */
$this->assign('title', __('Zlecenie') . ' ' . ($order->symbol ?: '#' . $order->id));

$fdate     = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : null;
$fdatetime = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i') : substr((string)$v, 0, 16)) : null;
$fnum      = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';
// Flaga jako <img> z flagcdn.com (działa na Windows/Linux/Mac). ISO 3166-1 alpha-2.
$flag = function (?string $code, int $w = 24, int $h = 18): string {
    $code = strtolower(trim((string)$code));
    if (!preg_match('/^[a-z]{2}$/', $code)) return '';
    return '<img src="https://flagcdn.com/' . $w . 'x' . $h . '/' . $code . '.png"'
         . ' srcset="https://flagcdn.com/' . ($w*2) . 'x' . ($h*2) . '/' . $code . '.png 2x,'
         . ' https://flagcdn.com/' . ($w*3) . 'x' . ($h*3) . '/' . $code . '.png 3x"'
         . ' alt="' . htmlspecialchars(strtoupper($code)) . '"'
         . ' class="rounded" style="width:' . ($w*2/3) . 'px;height:' . ($h*2/3) . 'px;vertical-align:-2px;object-fit:cover">';
};

$rawData = [];
if (!empty($order->raw_json)) {
    $rawData = (array)(json_decode((string)$order->raw_json, true) ?? []);
}

// Status Nordlogis (operacyjny, widoczny dla klienta)
$nlStatusMap = [
    1 => ['label' => __('Przyjęte'),     'cls' => 'bg-warning text-dark',  'icon' => 'ri-inbox-line',          'color' => '#f59e0b'],
    2 => ['label' => __('Zaplanowane'),  'cls' => 'bg-info text-white',    'icon' => 'ri-calendar-check-line', 'color' => '#0ea5e9'],
    3 => ['label' => __('Załadowane'),   'cls' => 'bg-primary text-white', 'icon' => 'ri-truck-line',          'color' => '#6366f1'],
    4 => ['label' => __('Zrealizowane'), 'cls' => 'bg-success text-white', 'icon' => 'ri-checkbox-circle-line','color' => '#22c55e'],
    5 => ['label' => __('Zafakturowane'),'cls' => 'bg-dark text-white',    'icon' => 'ri-file-text-line',      'color' => '#374151'],
];
$nlStatus  = (int)($order->nordlogis_status ?? 1);
$effective = !empty($order->invoice_id) ? 5 : $nlStatus;
$nlCurrent = $nlStatusMap[$effective] ?? $nlStatusMap[1];

// Załadunek / rozładunek
$loadCountry  = (string)($order->load_country     ?? $rawData['GLO_MIE_KRAJ']    ?? '');
$loadCode     = (string)($order->load_postal_code ?? $rawData['GLO_MIE_KOD']     ?? '');
$loadCity     = (string)($order->load_city        ?? $rawData['GLO_MIE_POCZTA']  ?? $order->place_from_name ?? '');
$unloadName   = (string)($order->unload_name      ?? $rawData['GLO_MIE_NAZWA1']  ?? $order->place_to_name   ?? '');
$unloadCity   = (string)($order->unload_city      ?? $rawData['GLO_MIE_MIEJSC']  ?? '');
// Speed API nie podaje kraju rozładunku, a sync ustawia place_to_country = load_country
// (fallback). Wyciągamy kod kraju z prefixu unload_name ("NL, 3925CK" → "NL").
$_extractedUnloadCountry = '';
if (preg_match('/^([A-Z]{2})\s*,/', trim($unloadName), $_m)) {
    $_extractedUnloadCountry = $_m[1];
}
$unloadCountry= $_extractedUnloadCountry ?: (string)($order->unload_country ?? '');
$unloadName   = $_extractedUnloadCountry !== ''
    ? trim((string)preg_replace('/^[A-Z]{2}\s*,\s*/', '', $unloadName))
    : $unloadName;

// Timeline
$today = new \DateTimeImmutable('today');
$tlEvents = [];

// POL/POD potwierdzone tylko gdy istnieje plik z odpowiednią etykietą.
$hasPolFile = false;
$hasPodFile = false;
foreach (($attachments ?? []) as $_att) {
    $_slug = (string)($_att->speed_order_attachment_label->slug ?? '');
    if (str_starts_with($_slug, 'pol_')) $hasPolFile = true;
    elseif (str_starts_with($_slug, 'pod_')) $hasPodFile = true;
}

if ($order->date_doc) {
    $d = $order->date_doc instanceof \DateTimeInterface ? $order->date_doc : new \DateTimeImmutable(substr((string)$order->date_doc, 0, 10));
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => __('Zlecenie przyjęte'), 'sub' => $fdate($order->date_doc), 'icon' => 'ri-file-add-line', 'color' => '#64748b', 'done' => true];
}
if ($order->date_deadline) {
    $d    = $order->date_deadline instanceof \DateTimeInterface ? $order->date_deadline : new \DateTimeImmutable(substr((string)$order->date_deadline, 0, 10));
    $done = $hasPolFile;
    $late = !$done && $d < $today;
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => __('Planowany załadunek'), 'sub' => $fdate($order->date_deadline), 'icon' => 'ri-upload-2-line', 'color' => $late ? '#ef4444' : '#f59e0b', 'done' => $done, 'late' => $late];
}
if ($hasPolFile && $order->pol_at) {
    $d = $order->pol_at instanceof \DateTimeInterface ? $order->pol_at : new \DateTimeImmutable(substr((string)$order->pol_at, 0, 16));
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => __('Załadunek potwierdzony') . ' (POL)', 'sub' => $fdatetime($order->pol_at), 'icon' => 'ri-checkbox-circle-line', 'color' => '#6366f1', 'done' => true];
}
if ($order->date_delivery) {
    $d    = $order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery : new \DateTimeImmutable(substr((string)$order->date_delivery, 0, 10));
    $done = $hasPodFile;
    $late = !$done && $d < $today;
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => __('Planowany rozładunek'), 'sub' => $fdate($order->date_delivery), 'icon' => 'ri-download-2-line', 'color' => $late ? '#ef4444' : '#f59e0b', 'done' => $done, 'late' => $late];
}
if ($hasPodFile && $order->pod_at) {
    $d = $order->pod_at instanceof \DateTimeInterface ? $order->pod_at : new \DateTimeImmutable(substr((string)$order->pod_at, 0, 16));
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => __('Rozładunek potwierdzony') . ' (POD)', 'sub' => $fdatetime($order->pod_at), 'icon' => 'ri-map-pin-2-line', 'color' => '#22c55e', 'done' => true];
}
if ($order->fs_at || $order->invoice_id) {
    $d = $order->fs_at
        ? ($order->fs_at instanceof \DateTimeInterface ? $order->fs_at : new \DateTimeImmutable(substr((string)$order->fs_at, 0, 16)))
        : $today;
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => __('Faktura wystawiona'), 'sub' => $fdatetime($order->fs_at) ?? __('Wystawiona'), 'icon' => 'ri-file-text-line', 'color' => '#374151', 'done' => true];
}
usort($tlEvents, fn($a, $b) => $a['ts'] <=> $b['ts']);

// KPI: czas transportu (deadline → delivery)
$transportDays = null;
if ($order->date_deadline && $order->date_delivery) {
    $d1 = $order->date_deadline instanceof \DateTimeInterface ? $order->date_deadline : new \DateTimeImmutable(substr((string)$order->date_deadline, 0, 10));
    $d2 = $order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery : new \DateTimeImmutable(substr((string)$order->date_delivery, 0, 10));
    $transportDays = $d1->diff($d2)->days;
}

// KPI: opóźnienie / w terminie — dostawa "wykonana" liczona z plikiem POD
$delayDays = null;
$delayState = 'pending'; // pending | ontime | late | overdue
if ($order->date_delivery && $hasPodFile && $order->pod_at) {
    $d1 = $order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery : new \DateTimeImmutable(substr((string)$order->date_delivery, 0, 10));
    $d2 = $order->pod_at instanceof \DateTimeInterface ? $order->pod_at : new \DateTimeImmutable(substr((string)$order->pod_at, 0, 16));
    $diff = $d1->diff($d2);
    $delayDays  = $diff->invert ? -$diff->days : $diff->days;
    $delayState = $delayDays > 0 ? 'late' : 'ontime';
} elseif ($order->date_delivery && !$hasPodFile) {
    $d1 = $order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery : new \DateTimeImmutable(substr((string)$order->date_delivery, 0, 10));
    if ($d1 < $today) {
        $delayDays  = $today->diff($d1)->days;
        $delayState = 'overdue';
    }
}
?>

<style>
/* ── Topbar ── używa zmiennych Zynix theme (light + dark) */
.cp-topbar { background: var(--custom-white); border:1px solid var(--default-border); border-radius:.75rem; padding:.85rem 1.25rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }

/* ── KPI tiles ── */
.kpi-tile { background: var(--custom-white); border:1px solid var(--default-border); border-radius:.75rem; padding:.85rem 1.1rem; display:flex; flex-direction:column; gap:.2rem; transition:box-shadow .15s; height:100%; }
.kpi-tile:hover { box-shadow:0 2px 12px rgba(0,0,0,.08); }
.kpi-tile .kpi-val   { font-size:1.4rem; font-weight:700; line-height:1.1; }
.kpi-tile .kpi-label { font-size:.7rem; color: var(--text-muted); text-transform:uppercase; letter-spacing:.05em; }
.kpi-tile .kpi-sub   { font-size:.72rem; color: var(--text-muted); opacity:.8; }

/* ── Route bar ── delikatny gradient w odcieniach primary, w dark mode ciemniejszy */
.route-bar { display:flex; align-items:center; gap:0; margin:.5rem 0 0; padding:1rem 1.25rem; background: linear-gradient(135deg, rgba(var(--primary-rgb), .08) 0%, rgba(34, 197, 94, .08) 100%); border:1px solid var(--default-border); border-radius:.75rem; }
[data-theme-mode="dark"] .route-bar { background: linear-gradient(135deg, rgba(var(--primary-rgb), .18) 0%, rgba(34, 197, 94, .15) 100%); }
.route-node { flex-shrink:0; min-width:180px; }
.route-node .rn-flag    { font-size:1.6rem; line-height:1; }
.route-node .rn-city    { font-size:.95rem; font-weight:700; color: var(--default-text-color); line-height:1.2; }
.route-node .rn-country { font-size:.75rem; color: var(--text-muted); margin-top:.15rem; }
.route-node .rn-date    { font-size:.78rem; opacity:.85; font-weight:600; margin-top:.35rem; }
.route-line { flex:1; height:4px; background: linear-gradient(to right, #6366f1, #22c55e); border-radius:2px; position:relative; margin:0 1.5rem; min-width:120px; }
.route-line-truck {
    position:absolute;
    top:-30px;
    width:68px;
    height:51px;
    line-height:1;
    z-index:2;
    will-change:left,transform;
    filter:drop-shadow(0 3px 6px rgba(0,0,0,.2));
}
.route-line-truck .truck-img {
    width:100%; height:100%;
    object-fit:contain;
    display:block;
}
/* Stan 2 (Zaplanowane) — czeka przy starcie, lekkie podskakiwanie */
.truck-state-2 { left:-16px; animation: truck-idle 1.8s ease-in-out infinite; }
/* Stan 3 (Załadowane) — w drodze, jedzie tam i z powrotem */
.truck-state-3 { animation: truck-drive 4.5s ease-in-out infinite; }
/* Stan 4/5 (Zrealizowane/Zafakturowane) — playback całej trasy w ~4s + bounce na końcu */
.truck-state-4, .truck-state-5 {
    left:-16px;
    animation: truck-delivery-playback 4s cubic-bezier(.4,0,.2,1) forwards;
}
@keyframes truck-idle {
    0%,100% { transform:translateY(0) rotate(0deg); }
    50%     { transform:translateY(-3px) rotate(-1deg); }
}
@keyframes truck-drive {
    0%   { left:-16px;              transform:translateY(0)   rotate(0deg); }
    8%   { left:-16px;              transform:translateY(-2px) rotate(-2deg); }
    50%  { left:calc(50% - 34px);   transform:translateY(0)   rotate(1deg); }
    92%  { left:calc(100% - 68px);  transform:translateY(-2px) rotate(-1deg); }
    100% { left:calc(100% - 68px);  transform:translateY(0)   rotate(0deg); }
}
@keyframes truck-arrived {
    0%   { transform:translateY(-12px) scale(.5) rotate(-8deg); opacity:0; }
    60%  { transform:translateY(2px)   scale(1.15); opacity:1; }
    100% { transform:translateY(0)     scale(1); }
}
@keyframes truck-delivery-playback {
    0%   { left:-16px;              transform:translateY(0)   rotate(0deg)  scale(1); }
    5%   { left:-16px;              transform:translateY(-2px) rotate(-3deg) scale(1); }
    /* Etap 1: jedzie do POL (środek trasy) */
    20%  { left:calc(20% - 14px);   transform:translateY(-1px) rotate(1deg)  scale(1.02); }
    32%  { left:calc(50% - 34px);   transform:translateY(0)   rotate(-1deg) scale(1); }
    /* Postojek przy POL — załadunek (~0.5s) z lekkim trzęsieniem */
    34%  { left:calc(50% - 34px);   transform:translateY(-2px) rotate(-2deg) scale(1.02); }
    37%  { left:calc(50% - 34px);   transform:translateY(1px)  rotate(2deg)  scale(1); }
    40%  { left:calc(50% - 34px);   transform:translateY(-1px) rotate(-1deg) scale(1.02); }
    43%  { left:calc(50% - 34px);   transform:translateY(0)   rotate(0deg)  scale(1); }
    /* Etap 2: jedzie do mety */
    60%  { left:calc(75% - 50px);   transform:translateY(-1px) rotate(2deg)  scale(1.02); }
    80%  { left:calc(100% - 68px);  transform:translateY(-3px) rotate(-1deg) scale(1); }
    /* Hamowanie z bounce */
    88%  { transform:translateY(3px) scale(1.08) rotate(0deg); }
    100% { left:calc(100% - 68px);  transform:translateY(0)   rotate(0deg)  scale(1); }
}
/* POL pulse podczas postojek (32-43% playbacku = ~1.28s-1.72s) */
.route-line.is-completed .route-pol {
    animation: pol-pulse .9s 1.2s ease-in-out;
}
@keyframes pol-pulse {
    0%,100% { transform:scale(1); box-shadow:none; }
    50%     { transform:scale(1.25); box-shadow:0 0 0 4px rgba(99,102,241,.3); }
}
/* Badge 'Dostarczone DD.MM.YYYY' — pod miastem rozładunku, wjeżdża po dojechaniu trucka */
.route-delivered-badge {
    display:inline-flex;
    align-items:center;
    margin-top:.35rem;
    background:rgba(34,197,94,.15);
    color:#15803d;
    padding:.2rem .55rem;
    border-radius:.35rem;
    font-size:.7rem;
    font-weight:700;
    letter-spacing:.02em;
    border:1px solid rgba(34,197,94,.3);
    opacity:0;
    animation: delivered-fade-in .7s 3.6s cubic-bezier(.34,1.56,.64,1) forwards;
}
.route-delivered-badge i { margin-right:.3rem; font-size:.9em; }
@keyframes delivered-fade-in {
    0%   { opacity:0; transform:translateY(-6px) scale(.85); }
    100% { opacity:1; transform:translateY(0) scale(1); }
}
[data-theme-mode="dark"] .route-delivered-badge {
    background:rgba(34,197,94,.25);
    color:#86efac;
    border-color:rgba(34,197,94,.5);
}
/* Speed lines / kreski prędkości za jadącą ciężarówką */
.truck-state-4 .truck-speedlines,
.truck-state-5 .truck-speedlines {
    position:absolute;
    right:100%;
    top:50%;
    transform:translateY(-50%);
    pointer-events:none;
    opacity:0;
    animation: speedlines-show 4s cubic-bezier(.4,0,.2,1) forwards;
}
.truck-state-4 .truck-speedlines::before,
.truck-state-4 .truck-speedlines::after,
.truck-state-5 .truck-speedlines::before,
.truck-state-5 .truck-speedlines::after {
    content:'';
    position:absolute;
    width:14px; height:2px;
    background:linear-gradient(to left, rgba(99,102,241,.7), transparent);
    border-radius:2px;
}
.truck-state-4 .truck-speedlines::before,
.truck-state-5 .truck-speedlines::before { top:-6px; right:6px; width:16px; }
.truck-state-4 .truck-speedlines::after,
.truck-state-5 .truck-speedlines::after  { top:4px;  right:2px; width:10px; opacity:.7; }
@keyframes speedlines-show {
    0%,5%   { opacity:0; }
    10%,80% { opacity:1; }
    90%,100%{ opacity:0; }
}
/* Konfetti na mecie */
.truck-state-4 .truck-confetti,
.truck-state-5 .truck-confetti {
    position:absolute;
    left:50%; top:-4px;
    transform:translateX(-50%);
    pointer-events:none;
    opacity:0;
    font-size:1rem;
    animation: confetti-burst 1.2s 3.5s ease-out forwards;
}
@keyframes confetti-burst {
    0%   { opacity:0; transform:translate(-50%, 0) scale(.4) rotate(0deg); }
    30%  { opacity:1; transform:translate(-50%, -16px) scale(1.2) rotate(180deg); }
    100% { opacity:0; transform:translate(-50%, -28px) scale(1.4) rotate(360deg); }
}
/* Pulsujący glow na mecie */
.truck-state-4 .truck-arrived-glow,
.truck-state-5 .truck-arrived-glow {
    position:absolute;
    right:-4px; top:50%;
    width:24px; height:24px;
    margin-top:-12px;
    border-radius:50%;
    background:radial-gradient(circle, rgba(34,197,94,.5) 0%, transparent 70%);
    pointer-events:none;
    opacity:0;
    animation: arrival-glow 1.5s 3.4s ease-out infinite;
}
@keyframes arrival-glow {
    0%,100% { opacity:0; transform:scale(.5); }
    50%     { opacity:1; transform:scale(1.4); }
}
/* Puff spalin gdy w drodze */
.truck-smoke {
    position:absolute;
    right:100%;
    top:10px;
    font-size:1rem;
    pointer-events:none;
    opacity:0;
    animation: smoke-puff 1.4s ease-out infinite;
}
.truck-smoke:nth-child(3) { animation-delay:.6s; }
@keyframes smoke-puff {
    0%   { opacity:0;  transform:translate(0,0) scale(.4); }
    30%  { opacity:.65; transform:translate(-6px,-3px) scale(.9); }
    100% { opacity:0;  transform:translate(-18px,-10px) scale(1.4); }
}
/* Checkmark przy dojechaniu */
.truck-check {
    position:absolute;
    left:calc(100% + 6px);
    top:10px;
    color:#22c55e;
    font-weight:900;
    font-size:1.5rem;
    line-height:1;
    /* delay 3.5s — czeka aż truck dojedzie na metę (playback 4s) */
    animation: check-pop .7s 3.5s cubic-bezier(.34,1.8,.64,1) both;
    text-shadow:0 1px 2px rgba(34,197,94,.4);
}
@keyframes check-pop {
    0%   { transform:scale(0) rotate(-90deg); opacity:0; }
    100% { transform:scale(1) rotate(0deg);   opacity:1; }
}
/* Trasa wypełniona — fill zależny od statusu (subtelny overlay) */
.route-line-progress {
    position:absolute; top:0; left:0; bottom:0;
    background:linear-gradient(to right, rgba(99,102,241,.35), rgba(34,197,94,.35));
    border-radius:2px;
    transition:width .8s ease-out;
    pointer-events:none;
}
/* Synchroniczny fill przy playbacku completed-truck */
.route-line.is-completed .route-line-progress {
    width:0%;
    animation: route-fill 4s cubic-bezier(.4,0,.2,1) forwards;
}
@keyframes route-fill {
    0%   { width:0%; }
    80%  { width:100%; }
    100% { width:100%; }
}
.route-pol { color:#fff; background:#6366f1; padding:.1rem .35rem; border-radius:.25rem; font-size:.6rem; font-weight:700; position:absolute; top:-22px; left:0; z-index:1; }
.route-pod { color:#fff; background:#22c55e; padding:.1rem .35rem; border-radius:.25rem; font-size:.6rem; font-weight:700; position:absolute; top:-22px; right:0; z-index:1; }

/* ── Timeline ── */
.tl { position:relative; padding-left:2.4rem; }
.tl::before { content:''; position:absolute; left:.95rem; top:.5rem; bottom:.5rem; width:2px; background: var(--default-border); }
.tl-item { position:relative; margin-bottom:1.1rem; }
.tl-item:last-child { margin-bottom:0; }
.tl-dot  { position:absolute; left:-1.95rem; top:.1rem; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.7rem; z-index:1; color:#fff; }
.tl-dot.done  { box-shadow:0 0 0 3px rgba(34,197,94,.2); }
.tl-dot.late  { animation:cp-pulse-red 1.5s infinite; }
@keyframes cp-pulse-red { 0%,100%{box-shadow:0 0 0 2px rgba(239,68,68,.3)} 50%{box-shadow:0 0 0 6px rgba(239,68,68,.1)} }
.tl-content      { background: rgb(var(--light-rgb)); border:1px solid var(--default-border); border-radius:.5rem; padding:.45rem .75rem; }
.tl-content.done { border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.08); }
.tl-content.late { border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.08); }
[data-theme-mode="dark"] .tl-content.done { background: rgba(34,197,94,.16); }
[data-theme-mode="dark"] .tl-content.late { background: rgba(239,68,68,.18); }

/* ── Attachments grid ── */
.cp-att-tile { background: var(--custom-white); border:1px solid var(--default-border); border-radius:.5rem; padding:.6rem .75rem; display:flex; align-items:center; gap:.6rem; transition:all .15s; }
.cp-att-tile:hover { border-color: rgba(var(--primary-rgb), .55); box-shadow:0 2px 8px rgba(var(--primary-rgb), .12); }
.cp-att-tile .att-icon { width:36px; height:36px; border-radius:.4rem; background: rgb(var(--light-rgb)); display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.cp-att-tile.is-image .att-icon { background: rgba(245, 158, 11, .18); color:#d97706; }
.cp-att-tile.is-pdf   .att-icon { background: rgba(239, 68, 68, .18); color:#dc2626; }
[data-theme-mode="dark"] .cp-att-tile.is-image .att-icon { color:#fbbf24; }
[data-theme-mode="dark"] .cp-att-tile.is-pdf   .att-icon { color:#f87171; }
</style>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- TOPBAR                                                                 -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="cp-topbar shadow-sm">
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary"
       title="<?= __('Wróć do listy') ?>">
        <i class="ri-arrow-left-line"></i>
    </a>

    <div class="d-flex flex-column">
        <span class="fw-bold fs-5 lh-1"><?= h($order->symbol ?: '#' . $order->id) ?></span>
        <?php if ($order->date_doc): ?>
            <span class="text-muted small"><?= h($fdate($order->date_doc)) ?></span>
        <?php endif; ?>
    </div>

    <span class="badge <?= $nlCurrent['cls'] ?> fs-6 px-3">
        <i class="<?= $nlCurrent['icon'] ?> me-1"></i><?= h($nlCurrent['label']) ?>
    </span>

    <?php if ((int)$order->status === 0): ?>
        <span class="badge bg-success-subtle text-success border border-success-subtle">
            <i class="ri-checkbox-circle-line me-1"></i><?= __('Zamknięte') ?>
        </span>
    <?php endif; ?>

    <?php
        $tytParts = array_filter([trim((string)($order->title1 ?? '')), trim((string)($order->title2 ?? ''))]);
        if (!empty($tytParts)):
    ?>
    <span class="badge bg-light border text-secondary" title="<?= __('Numer zlecenia') ?>">
        <i class="ri-file-list-3-line me-1 opacity-50"></i><?= h(implode(' / ', $tytParts)) ?>
    </span>
    <?php endif; ?>

    <div class="ms-auto d-flex gap-2 align-items-center">
        <?php if ($invoice): ?>
            <a href="<?= $this->Url->build(['action' => 'downloadInvoice', $invoice->id]) ?>"
               class="btn btn-sm btn-primary fw-semibold">
                <i class="ri-download-line me-1"></i><?= __('Pobierz fakturę PDF') ?>
            </a>
        <?php endif; ?>
        <!-- Switcher języka przeniesiony do nagłówka (dropdown z flagami) -->
    </div>
</div>

<?= $this->Flash->render() ?>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- KPI TILES                                                              -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="row g-2 mb-3">
    <!-- Status -->
    <div class="col-md-3 col-sm-6">
        <div class="kpi-tile">
            <span class="kpi-label"><?= __('Status') ?></span>
            <span class="kpi-val" style="color:<?= $nlCurrent['color'] ?>">
                <i class="<?= $nlCurrent['icon'] ?> me-1" style="font-size:1.3rem"></i><?= h($nlCurrent['label']) ?>
            </span>
            <span class="kpi-sub">
                <?php if ($hasPodFile && !empty($order->pod_at)): ?>
                    <?= __('Dostarczone') ?> <?= h($fdate($order->pod_at)) ?>
                <?php elseif ($hasPolFile && !empty($order->pol_at)): ?>
                    <?= __('Załadowane') ?> <?= h($fdate($order->pol_at)) ?>
                <?php elseif ($order->date_delivery): ?>
                    <?= __('Plan') ?>: <?= h($fdate($order->date_delivery)) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- Czas transportu -->
    <div class="col-md-3 col-sm-6">
        <div class="kpi-tile">
            <span class="kpi-label"><?= __('Czas transportu') ?></span>
            <span class="kpi-val" style="color:#0ea5e9">
                <?= $transportDays !== null ? (int)$transportDays . ' ' . __('dni') : '—' ?>
            </span>
            <span class="kpi-sub">
                <?php if ($order->date_deadline && $order->date_delivery): ?>
                    <?= h($fdate($order->date_deadline)) ?> → <?= h($fdate($order->date_delivery)) ?>
                <?php else: ?>
                    <?= __('Brak danych') ?>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- Opóźnienie / w terminie -->
    <div class="col-md-3 col-sm-6">
        <?php
            $stateColor = ['pending' => '#94a3b8', 'ontime' => '#22c55e', 'late' => '#f59e0b', 'overdue' => '#ef4444'][$delayState];
            $stateLabel = ['pending' => __('Oczekuje'), 'ontime' => __('W terminie'), 'late' => __('Opóźniony'), 'overdue' => __('Przeterminowany')][$delayState];
            $stateIcon  = ['pending' => 'ri-time-line', 'ontime' => 'ri-checkbox-circle-line', 'late' => 'ri-error-warning-line', 'overdue' => 'ri-alarm-warning-line'][$delayState];
        ?>
        <div class="kpi-tile">
            <span class="kpi-label"><?= __('Realizacja') ?></span>
            <span class="kpi-val" style="color:<?= $stateColor ?>">
                <i class="<?= $stateIcon ?> me-1" style="font-size:1.3rem"></i><?= h($stateLabel) ?>
            </span>
            <span class="kpi-sub">
                <?php if ($delayState === 'late' || $delayState === 'overdue'): ?>
                    <?= sprintf(__('%d dni'), (int)$delayDays) ?>
                <?php elseif ($delayState === 'ontime' && $delayDays !== null && $delayDays < 0): ?>
                    <?= sprintf(__('%d dni przed terminem'), abs((int)$delayDays)) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- Faktura/CMR -->
    <div class="col-md-3 col-sm-6">
        <?php
            $invStateColor = '#94a3b8';
            $invStateLabel = __('Brak faktury');
            $invStateIcon  = 'ri-file-line';
            if ($invoice) {
                if ($invoice->paymentstate === 'paid') {
                    $invStateColor = '#22c55e'; $invStateLabel = __('Opłacona'); $invStateIcon = 'ri-checkbox-circle-line';
                } elseif ($invoice->paymentstate === 'partial') {
                    $invStateColor = '#f59e0b'; $invStateLabel = __('Częściowo'); $invStateIcon = 'ri-time-line';
                } else {
                    $invStateColor = '#ef4444'; $invStateLabel = __('Nieopłacona'); $invStateIcon = 'ri-error-warning-line';
                }
            }
        ?>
        <div class="kpi-tile">
            <span class="kpi-label"><?= __('Faktura') ?></span>
            <span class="kpi-val" style="color:<?= $invStateColor ?>">
                <i class="<?= $invStateIcon ?> me-1" style="font-size:1.3rem"></i><?= h($invStateLabel) ?>
            </span>
            <span class="kpi-sub">
                <?php if ($invoice): ?>
                    <?= h($invoice->fullnumber ?: '—') ?>
                <?php else: ?>
                    <i class="ri-attachment-2 me-1"></i><?= sprintf(__('Załączniki: %d'), count($attachments)) ?>
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- ROUTE BAR                                                              -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<?php if ($loadCity || $unloadCity || $loadCountry || $unloadCountry): ?>
<div class="route-bar shadow-sm mb-3">
    <div class="route-node">
        <div class="d-flex align-items-center gap-2">
            <span class="rn-flag" title="<?= h($loadCountry) ?>">
                <?= $flag($loadCountry, 60, 45) ?: '<i class="ri-truck-line" style="font-size:1.6rem;color:rgb(27,89,152)"></i>' ?>
            </span>
            <div>
                <div class="rn-city"><?= h($loadCity ?: '—') ?></div>
                <?php if ($loadCountry || $loadCode): ?>
                    <div class="rn-country">
                        <?php if ($loadCountry): ?><?= h($loadCountry) ?><?php endif; ?>
                        <?php if ($loadCode): ?> · <?= h($loadCode) ?><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($order->date_deadline): ?>
                    <div class="rn-date"><i class="ri-calendar-line me-1 opacity-50"></i><?= h($fdate($order->date_deadline)) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
        // Truck animation:
        //  1 (Przyjęte)     — brak ciężarówki, brak fillu
        //  2 (Zaplanowane)  — ciężarówka przy starcie, lekkie podskakiwanie
        //  3 (Załadowane)   — w drodze, animacja drive + puff spalin
        //  4 (Zrealizowane) — dojechało, pojawia się z bounce + checkmark
        //  5 (Zafakturowane)— jak 4, sam stan
        $progressPct = match (true) {
            $effective <= 1 => 0,
            $effective === 2 => 10,
            $effective === 3 => 60,
            $effective >= 4  => 100,
            default          => 0,
        };
    ?>
    <?php
        // Data dostarczenia — preferuj rzeczywisty rozładunek, fallback na planowaną.
        $deliveryDateStr = '';
        if ($effective >= 4) {
            $deliveryDateStr = $fdate($order->actual_unload_at ?? null) ?? $fdate($order->date_delivery) ?? '';
        }
    ?>
    <div class="route-line<?= $effective >= 4 ? ' is-completed' : '' ?>">
        <div class="route-line-progress" style="<?= $effective >= 4 ? '' : 'width:' . $progressPct . '%' ?>"></div>
        <?php if ($hasPolFile): ?><span class="route-pol">POL ✓</span><?php endif; ?>
        <?php if ($effective >= 2): ?>
            <span class="route-line-truck truck-state-<?= (int)$effective ?>" aria-label="<?= h($nlCurrent['label']) ?>">
                <img src="/assets/img/truck.png" alt="" class="truck-img">
                <?php if ($effective === 3): ?>
                    <span class="truck-smoke" aria-hidden="true">💨</span>
                    <span class="truck-smoke" aria-hidden="true">💨</span>
                <?php elseif ($effective >= 4): ?>
                    <span class="truck-speedlines" aria-hidden="true"></span>
                    <span class="truck-confetti" aria-hidden="true">🎉</span>
                    <span class="truck-arrived-glow" aria-hidden="true"></span>
                    <span class="truck-check" aria-hidden="true">✓</span>
                <?php endif; ?>
            </span>
        <?php endif; ?>
        <?php if ($hasPodFile): ?><span class="route-pod">POD ✓</span><?php endif; ?>
    </div>

    <div class="route-node text-end">
        <div class="d-flex align-items-center justify-content-end gap-2">
            <div>
                <div class="rn-city"><?= h($unloadName ?: $unloadCity ?: '—') ?></div>
                <?php if ($unloadCountry || $unloadCity): ?>
                    <div class="rn-country">
                        <?php if ($unloadCountry): ?><?= h($unloadCountry) ?><?php endif; ?>
                        <?php if ($unloadCountry && $unloadCity && $unloadCity !== $unloadName): ?> · <?php endif; ?>
                        <?php if ($unloadCity && $unloadCity !== $unloadName): ?><?= h($unloadCity) ?><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($order->date_delivery): ?>
                    <div class="rn-date"><i class="ri-calendar-line me-1 opacity-50"></i><?= h($fdate($order->date_delivery)) ?></div>
                <?php endif; ?>
                <?php if ($effective >= 4 && $deliveryDateStr !== ''): ?>
                    <div class="route-delivered-badge" aria-label="<?= __('Dostarczone') ?> <?= h($deliveryDateStr) ?>">
                        <i class="ri-checkbox-circle-fill"></i><?= __('Dostarczone') ?> <?= h($deliveryDateStr) ?>
                    </div>
                <?php endif; ?>
            </div>
            <span class="rn-flag" title="<?= h($unloadCountry) ?>">
                <?= $flag($unloadCountry, 60, 45) ?: '<i class="ri-flag-line" style="font-size:1.6rem;color:rgb(34,197,94)"></i>' ?>
            </span>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- ── Lewa kolumna: Timeline + Dane transportu + Załączniki ── -->
    <div class="col-lg-8">
        <!-- Timeline -->
        <?php if (!empty($tlEvents)): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2">
                <strong><i class="ri-history-line me-1"></i><?= __('Historia transportu') ?></strong>
            </div>
            <div class="card-body py-3">
                <div class="tl">
                    <?php foreach ($tlEvents as $ev): ?>
                    <?php $cls = (!empty($ev['done']) ? 'done' : '') . (!empty($ev['late']) ? ' late' : ''); ?>
                    <div class="tl-item">
                        <div class="tl-dot <?= $cls ?>" style="background:<?= $ev['color'] ?>">
                            <i class="<?= $ev['icon'] ?>"></i>
                        </div>
                        <div class="tl-content <?= $cls ?>">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <span class="fw-semibold" style="font-size:.88rem"><?= h($ev['label']) ?></span>
                                <?php if (!empty($ev['sub'])): ?>
                                    <span class="text-muted small"><?= h($ev['sub']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Dane transportu -->
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2">
                <strong><i class="ri-truck-line me-1"></i><?= __('Dane transportu') ?></strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php if ($order->title1 || $order->title2 || $order->cargo_type): ?>
                    <div class="col-12">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Tytuł / Ładunek') ?></div>
                        <?php if ($order->title1): ?>
                            <div class="fw-semibold"><?= h($order->title1) ?></div>
                        <?php endif; ?>
                        <?php if ($order->title2): ?>
                            <div class="text-muted"><?= h($order->title2) ?></div>
                        <?php endif; ?>
                        <?php if ($order->cargo_type): ?>
                            <div class="small mt-1"><span class="badge bg-light text-secondary border"><?= h($order->cargo_type) ?></span></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-6">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Załadunek') ?></div>
                        <div class="fw-semibold">
                            <i class="ri-arrow-up-circle-fill text-success me-1"></i>
                            <?= h($loadCity ?: $order->place_from_name ?: '—') ?>
                            <?php if ($loadCountry): ?>
                                <span class="badge bg-light text-secondary border ms-1 d-inline-flex align-items-center gap-1" style="font-size:.65em">
                                    <?= $flag($loadCountry) ?>
                                    <?= h($loadCountry) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($loadCode): ?>
                            <div class="text-muted small mt-1"><?= h($loadCode) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Rozładunek') ?></div>
                        <div class="fw-semibold">
                            <i class="ri-arrow-down-circle-fill text-danger me-1"></i>
                            <?= h($unloadName ?: $unloadCity ?: $order->place_to_name ?: '—') ?>
                            <?php if ($unloadCountry): ?>
                                <span class="badge bg-light text-secondary border ms-1 d-inline-flex align-items-center gap-1" style="font-size:.65em">
                                    <?= $flag($unloadCountry) ?>
                                    <?= h($unloadCountry) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($unloadCity && $unloadCity !== $unloadName): ?>
                            <div class="text-muted small mt-1"><?= h($unloadCity) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($order->route_description): ?>
                    <div class="col-12">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Trasa') ?></div>
                        <div><?= h($order->route_description) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($order->notes): ?>
                    <div class="col-12">
                        <div class="text-muted small text-uppercase mb-1"><?= __('Uwagi') ?></div>
                        <div style="white-space:pre-wrap"><?= h($order->notes) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Załączniki CMR -->
        <div class="card shadow-sm mb-3" id="attachments">
            <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">
                <strong><i class="ri-attachment-2 me-1"></i><?= __('Załączniki (CMR)') ?></strong>
                <span class="badge bg-secondary"><?= count($attachments) ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($attachments)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="ri-folder-open-line" style="font-size:2em;opacity:.4"></i>
                        <div class="small mt-2 fst-italic"><?= __('Brak załączników.') ?></div>
                    </div>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($attachments as $att): ?>
                        <?php
                            $isImage = str_starts_with((string)$att->mime_type, 'image/');
                            $isPdf   = str_contains((string)$att->mime_type, 'pdf');
                            $tileCls = $isImage ? 'is-image' : ($isPdf ? 'is-pdf' : '');
                            $iconCls = $isImage ? 'ri-image-2-line' : ($isPdf ? 'ri-file-pdf-line' : 'ri-file-line');
                            $label   = $att->speed_order_attachment_label?->name ?? '';
                            $size    = $att->file_size ? round((int)$att->file_size / 1024, 1) . ' KB' : '';
                        ?>
                        <div class="col-md-6">
                            <div class="cp-att-tile <?= $tileCls ?>">
                                <div class="att-icon"><i class="<?= $iconCls ?>"></i></div>
                                <div class="flex-grow-1 min-width-0">
                                    <div class="text-truncate fw-semibold" style="font-size:.85rem"
                                         title="<?= h($att->original_name) ?>">
                                        <?= h($att->original_name ?: 'cmr-' . $att->id) ?>
                                    </div>
                                    <div class="text-muted small d-flex gap-2 mt-1">
                                        <?php if ($label): ?>
                                            <span class="badge bg-light text-secondary border" style="font-size:.65em"><?= h($label) ?></span>
                                        <?php endif; ?>
                                        <?php if ($size): ?>
                                            <span style="font-size:.7rem"><?= h($size) ?></span>
                                        <?php endif; ?>
                                        <?php if ($att->created): ?>
                                            <span style="font-size:.7rem"><?= h($att->created->format('d.m.Y')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="<?= $this->Url->build(['action' => 'downloadAttachment', $att->id]) ?>"
                                   class="btn btn-sm btn-outline-primary flex-shrink-0"
                                   title="<?= __('Pobierz') ?>">
                                    <i class="ri-download-line"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Prawa kolumna: Wartość + Faktura ── -->
    <div class="col-lg-4">
        <!-- Wartość zlecenia -->
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2">
                <strong><i class="ri-money-euro-circle-line me-1"></i><?= __('Wartość zlecenia') ?></strong>
            </div>
            <?php
                // Brutto liczymy z netto+VAT — pole \$order->brutto z Speed ERP
                // jest często nieprawidłowe (czasem 0, czasem mniejsze niż netto).
                $netto  = (float)($order->netto ?? 0);
                $vatAmt = (float)($order->vat   ?? 0);
                $brutto = $netto + $vatAmt;
            ?>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small"><?= __('Netto') ?></span>
                    <span class="fw-semibold"><?= $fnum($netto) ?> <?= h($order->currency) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted small"><?= __('VAT') ?></span>
                    <span class="fw-semibold"><?= $fnum($vatAmt) ?> <?= h($order->currency) ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-baseline">
                    <span class="text-muted"><?= __('Brutto') ?></span>
                    <span class="fw-bold fs-5 text-primary"><?= $fnum($brutto) ?> <?= h($order->currency) ?></span>
                </div>
                <?php if ($order->currency !== 'PLN' && $order->exchange_rate && (float)$order->exchange_rate > 0): ?>
                    <div class="text-muted small mt-2">
                        <i class="ri-exchange-line me-1"></i><?= __('Kurs') ?>: <?= number_format((float)$order->exchange_rate, 4, ',', ' ') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Faktura -->
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2">
                <strong><i class="ri-file-text-line me-1"></i><?= __('Faktura') ?></strong>
            </div>
            <div class="card-body">
                <?php if ($invoice): ?>
                    <div class="fw-bold mb-1" style="font-size:1.05rem"><?= h($invoice->fullnumber) ?></div>
                    <div class="text-muted small mb-3">
                        <i class="ri-calendar-line me-1 opacity-50"></i><?= __('Data wystawienia') ?>: <?= h($fdate($invoice->date)) ?>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small"><?= __('Kwota') ?></span>
                        <span class="fw-semibold"><?= $fnum($invoice->total) ?> <?= h($invoice->currency) ?></span>
                    </div>
                    <?php if ($invoice->paymentdate): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small"><?= __('Termin płatności') ?></span>
                        <span><?= h($fdate($invoice->paymentdate)) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php
                        $stMap = [
                            'paid'    => ['cls' => 'bg-success-subtle text-success border-success-subtle', 'lbl' => __('Opłacona'),    'ico' => 'ri-checkbox-circle-fill'],
                            'partial' => ['cls' => 'bg-warning-subtle text-warning border-warning-subtle', 'lbl' => __('Częściowo opłacona'), 'ico' => 'ri-time-line'],
                            'unpaid'  => ['cls' => 'bg-danger-subtle text-danger border-danger-subtle',    'lbl' => __('Nieopłacona'), 'ico' => 'ri-error-warning-line'],
                        ];
                        $st = $stMap[$invoice->paymentstate ?? 'unpaid'] ?? $stMap['unpaid'];
                    ?>
                    <div class="mb-3">
                        <span class="badge <?= $st['cls'] ?> border w-100 py-2" style="font-size:.85rem">
                            <i class="<?= $st['ico'] ?> me-1"></i><?= $st['lbl'] ?>
                        </span>
                    </div>
                    <a href="<?= $this->Url->build(['action' => 'downloadInvoice', $invoice->id]) ?>"
                       class="btn btn-primary w-100">
                        <i class="ri-download-line me-1"></i><?= __('Pobierz fakturę PDF') ?>
                    </a>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="ri-file-line" style="font-size:2.5em;opacity:.3"></i>
                        <div class="small mt-2 fst-italic">
                            <i class="ri-information-line me-1"></i>
                            <?= __('Faktura jeszcze nie została wystawiona.') ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\SpeedOrder $order
 * @var array|null $rawData
 * @var \App\Model\Entity\CostInvoice[] $costInvoices
 * @var \App\Model\Entity\SpeedOrderStatusLog[] $statusLogs
 * @var array<string,string> $logAvatarMap user_id => avatar URL
 * @var \App\Model\Entity\SpeedOrderAttachment[] $attachments
 * @var \App\Model\Entity\SpeedOrderAttachmentLabel[] $attachmentLabels
 * @var bool $isModal
 */

$isModal      = $isModal ?? false;
$logAvatarMap = $logAvatarMap ?? [];
if (!$isModal) {
    $this->assign('title', 'Zlecenie ' . h($order->symbol));
}

// Czy zalogowany user to admin — odblokowuje "tryb force": cofanie statusów,
// odznaczanie POL/POD/FK/FS, edycja actual_* niezależnie od auto-eskalacji.
$_identityRoles = $this->request->getAttribute('identity');
$_isAdminUser   = $_identityRoles
    && ((bool)($_identityRoles->get('is_admin') ?? false)
        || strtolower((string)($_identityRoles->get('role') ?? '')) === 'admin');

$fdate     = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : null;
$fdatetime = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i') : substr((string)$v, 0, 16)) : null;
$fnum      = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';

$nlStatusMap = [
    1 => ['label' => 'Przyjęte',     'cls' => 'bg-warning text-dark',     'icon' => 'ri-inbox-line',             'color' => '#f59e0b'],
    2 => ['label' => 'Zaplanowane',  'cls' => 'bg-info text-white',       'icon' => 'ri-calendar-check-line',    'color' => '#0ea5e9'],
    3 => ['label' => 'Załadowane',   'cls' => 'bg-primary text-white',    'icon' => 'ri-truck-line',             'color' => '#6366f1'],
    4 => ['label' => 'Zrealizowane', 'cls' => 'bg-success text-white',    'icon' => 'ri-checkbox-circle-line',   'color' => '#22c55e'],
    5 => ['label' => 'Zafakturowane','cls' => 'bg-dark text-white',       'icon' => 'ri-file-text-line',         'color' => '#374151'],
];
$speedStatusMap = [1=>'Przyjęte',2=>'Zrealizowane',3=>'Zafakturowane',4=>'Archiwum',5=>'Anulowane'];

$nlStatus  = (int)($order->nordlogis_status ?? 1);
$nlCurrent = $nlStatusMap[$nlStatus] ?? $nlStatusMap[1];
$speedLabel= $speedStatusMap[(int)($order->status ?? 1)] ?? '';
$cur       = h($order->currency ?? 'PLN');

// Flagi POL/POD na podstawie ZAŁĄCZNIKÓW (nie pol_at/pod_at z DB).
// Etykieta slug pol_photo/pol_scan → POL ✓; pod_photo/pod_scan → POD ✓.
$hasPolFile = false;
$hasPodFile = false;
foreach (($attachments ?? []) as $_att) {
    $_slug = (string)($_att->speed_order_attachment_label->slug ?? '');
    if (str_starts_with($_slug, 'pol_')) $hasPolFile = true;
    elseif (str_starts_with($_slug, 'pod_')) $hasPodFile = true;
}

// Załadunek / rozładunek
$loadCountry = (string)($order->load_country     ?? $rawData['GLO_MIE_KRAJ']    ?? '');
$loadCode    = (string)($order->load_postal_code ?? $rawData['GLO_MIE_KOD']     ?? '');
$loadCity    = (string)($order->load_city        ?? $rawData['GLO_MIE_POCZTA']  ?? '');
$unloadName  = (string)($order->unload_name      ?? $rawData['GLO_MIE_NAZWA1']  ?? '');
$unloadCity  = (string)($order->unload_city      ?? $rawData['GLO_MIE_MIEJSC']  ?? '');
// Speed API zwykle zaszywa kod kraju rozładunku w prefixie unload_name ("NL, 3925CK" → "NL").
$_extractedUnloadCountry = '';
if (preg_match('/^([A-Z]{2})\s*,/', trim($unloadName), $_m)) {
    $_extractedUnloadCountry = $_m[1];
}
$unloadCountry=$_extractedUnloadCountry ?: (string)($order->unload_country ?? '');
$unloadName  = $_extractedUnloadCountry !== ''
    ? trim((string)preg_replace('/^[A-Z]{2}\s*,\s*/', '', $unloadName))
    : $unloadName;

// Timeline events
$today = new \DateTimeImmutable('today');

$tlEvents = [];

// 1. Zlecenie przyjęte
if ($order->date_doc) {
    $d = $order->date_doc instanceof \DateTimeInterface ? $order->date_doc : new \DateTimeImmutable(substr((string)$order->date_doc,0,10));
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => 'Zlecenie przyjęte', 'sub' => $order->nick_created ? 'Wystawił: '.$order->nick_created : null, 'icon' => 'ri-file-add-line', 'color' => '#64748b', 'done' => true];
}
// 2. Planowany załadunek (oznaczony jako 'done' gdy istnieje plik POL)
if ($order->date_deadline) {
    $d = $order->date_deadline instanceof \DateTimeInterface ? $order->date_deadline : new \DateTimeImmutable(substr((string)$order->date_deadline,0,10));
    $done = $hasPolFile;
    $late = !$done && $d < $today;
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => 'Planowany załadunek', 'sub' => $fdate($order->date_deadline), 'icon' => 'ri-upload-2-line', 'color' => $late ? '#ef4444' : '#f59e0b', 'done' => $done, 'late' => $late];
}
// Helper: wyciągnij username z logów dla danego pola (ostatni wpis ustawienia)
$logByField = [];
foreach ($statusLogs as $log) {
    if ($log->new_value !== null) { // zmiana "na coś" (zaznaczenie)
        $logByField[$log->field] = $log;
    }
}
$byUser = function(string $field) use ($order, $logByField): ?string {
    // Priorytet: pole *_by w encji (najświeższe)
    $byField = str_replace('_at', '_by', $field);
    $byVal = $order->{$byField} ?? null;
    if ($byVal) return (string)$byVal;
    // Fallback: z logów
    return isset($logByField[$field]) ? (string)($logByField[$field]->username ?? '') : null;
};

// 3a. Rzeczywisty załadunek (z pola actual_load_at — czas operacyjny)
if (!empty($order->actual_load_at)) {
    $d = $order->actual_load_at instanceof \DateTimeInterface ? $order->actual_load_at : new \DateTimeImmutable(substr((string)$order->actual_load_at,0,16));
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => 'Rzeczywisty załadunek',
        'sub' => $fdatetime($order->actual_load_at),
        'icon' => 'ri-truck-line', 'color' => '#0ea5e9', 'done' => true];
}
// 3. POL — załadunek potwierdzony dokumentem (gdy plik istnieje)
if ($hasPolFile && $order->pol_at) {
    $d = $order->pol_at instanceof \DateTimeInterface ? $order->pol_at : new \DateTimeImmutable(substr((string)$order->pol_at,0,16));
    $by = $byUser('pol_at');
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => 'POL — załadunek potwierdzony dokumentem',
        'sub' => $fdatetime($order->pol_at), 'by' => $by,
        'icon' => 'ri-checkbox-circle-line', 'color' => '#6366f1', 'done' => true];
}
// 4. Planowany rozładunek (oznaczony 'done' gdy istnieje plik POD)
if ($order->date_delivery) {
    $d = $order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery : new \DateTimeImmutable(substr((string)$order->date_delivery,0,10));
    $done = $hasPodFile;
    $late = !$done && $d < $today;
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => 'Planowany rozładunek', 'sub' => $fdate($order->date_delivery), 'icon' => 'ri-download-2-line', 'color' => $late ? '#ef4444' : '#f59e0b', 'done' => $done, 'late' => $late];
}
// 4a. Rzeczywisty rozładunek
if (!empty($order->actual_unload_at)) {
    $d = $order->actual_unload_at instanceof \DateTimeInterface ? $order->actual_unload_at : new \DateTimeImmutable(substr((string)$order->actual_unload_at,0,16));
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => 'Rzeczywisty rozładunek',
        'sub' => $fdatetime($order->actual_unload_at),
        'icon' => 'ri-truck-line', 'color' => '#0ea5e9', 'done' => true];
}
// 5. POD — rozładunek potwierdzony dokumentem (gdy plik istnieje)
if ($hasPodFile && $order->pod_at) {
    $d = $order->pod_at instanceof \DateTimeInterface ? $order->pod_at : new \DateTimeImmutable(substr((string)$order->pod_at,0,16));
    $by = $byUser('pod_at');
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => 'POD — rozładunek potwierdzony dokumentem',
        'sub' => $fdatetime($order->pod_at), 'by' => $by,
        'icon' => 'ri-map-pin-2-line', 'color' => '#22c55e', 'done' => true];
}
// 6. FK
if ($order->fk_at) {
    $d = $order->fk_at instanceof \DateTimeInterface ? $order->fk_at : new \DateTimeImmutable(substr((string)$order->fk_at,0,16));
    $by = $byUser('fk_at');
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => 'FK — faktura kosztowa',
        'sub' => $fdatetime($order->fk_at) . (count($costInvoices) ? ' ('.count($costInvoices).' dok.)' : ''),
        'by' => $by, 'icon' => 'ri-receipt-line', 'color' => '#8b5cf6', 'done' => true];
}
// 7. FS / faktura sprzedażowa — istnieje gdy fs_at zaznaczony LUB jest wpis w pivot
if ($order->fs_at || !empty($order->invoices ?? [])) {
    $d = $order->fs_at ? ($order->fs_at instanceof \DateTimeInterface ? $order->fs_at : new \DateTimeImmutable(substr((string)$order->fs_at,0,16))) : $today;
    $by = $byUser('fs_at');
    $tlEvents[] = ['ts' => $d->getTimestamp(), 'label' => 'FS — faktura sprzedażowa',
        'sub' => $fdatetime($order->fs_at ?? null) ?? 'Wystawiona',
        'by' => $by, 'icon' => 'ri-file-text-line', 'color' => '#374151', 'done' => true];
}

usort($tlEvents, fn($a,$b) => $a['ts'] <=> $b['ts']);

// Checkboxy
$checks = [
    ['field'=>'pol_at','byField'=>'pol_by','label'=>'POL','title'=>'Załadunek','desc'=>'Proof of Loading','icon'=>'ri-upload-2-line','color'=>'#6366f1'],
    ['field'=>'pod_at','byField'=>'pod_by','label'=>'POD','title'=>'Rozładunek','desc'=>'Proof of Delivery','icon'=>'ri-download-2-line','color'=>'#22c55e'],
    ['field'=>'fk_at', 'byField'=>'fk_by', 'label'=>'FK', 'title'=>'Faktura kosztowa','desc'=>'Od przewoźnika','icon'=>'ri-receipt-line','color'=>'#8b5cf6'],
    ['field'=>'fs_at', 'byField'=>'fs_by', 'label'=>'FS', 'title'=>'Faktura sprzedażowa','desc'=>'Dla klienta','icon'=>'ri-file-text-line','color'=>'#374151'],
];
foreach ($checks as &$chk) {
    $val = $order->{$chk['field']} ?? null;
    // POL/POD: zielone TYLKO gdy istnieje fizyczny plik z odpowiednią etykietą.
    // FK/FS: dalej z pól datetime jak wcześniej.
    if ($chk['field'] === 'pol_at') {
        $chk['checked'] = $hasPolFile;
    } elseif ($chk['field'] === 'pod_at') {
        $chk['checked'] = $hasPodFile;
    } else {
        $chk['checked'] = !empty($val);
    }
    $chk['date']    = $fdatetime($val);
    $chk['by']      = $byUser($chk['field']);
}
unset($chk);

$missing = array_filter(array_map(fn($c) => $c['checked'] ? null : $c['label'], $checks));

$fvUrl           = $this->Url->build(['controller'=>'Invoices','action'=>strtoupper(trim((string)($order->currency??'PLN')))!=='PLN'?'addCurrency':'addVat','?'=>['from_order_id'=>$order->id]]);
$updateStatusUrl = $this->Url->build(['controller'=>'SpeedOrders','action'=>'updateStatus']);
$assignFkUrl     = $this->Url->build(['controller'=>'CostInvoices','action'=>'assignOrder']);
$unassignFkUrl   = $this->Url->build(['controller'=>'CostInvoices','action'=>'unassignOrder']);
$searchFkUrl     = $this->Url->build(['controller'=>'CostInvoices','action'=>'searchAjax']);
$csrfToken       = $this->request->getAttribute('csrfToken');
?>

<style>
/* ── Topbar ── */
.order-topbar { background:#fff; border-bottom:1px solid #e5e7eb; padding:.75rem 1.25rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
/* ── Status stepper ── */
.stepper { display:flex; align-items:stretch; gap:0; }
.stepper-step { flex:1; display:flex; flex-direction:column; align-items:center; position:relative; cursor:pointer; padding:.5rem .25rem; border:2px solid transparent; border-radius:.5rem; transition:all .15s; }
.stepper-step:hover { background:#f8fafc; }
.stepper-step.active  { border-color:var(--step-color,#6366f1); background:color-mix(in srgb,var(--step-color,#6366f1) 8%,white); }
.stepper-step.past    { opacity:.65; }
.stepper-step.future  { opacity:.4; }
.stepper-dot { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
.stepper-label { font-size:.72rem; font-weight:600; margin-top:.3rem; text-align:center; letter-spacing:.02em; }
.stepper-arrow { display:flex; align-items:center; color:#cbd5e1; font-size:1.2rem; padding:0 .1rem; flex-shrink:0; }

/* ── KPI tiles ── */
.kpi-tile { background:#fff; border:1px solid #e5e7eb; border-radius:.75rem; padding:.85rem 1.1rem; display:flex; flex-direction:column; gap:.2rem; transition:box-shadow .15s; }
.kpi-tile:hover { box-shadow:0 2px 12px rgba(0,0,0,.08); }
.kpi-tile .kpi-val { font-size:1.45rem; font-weight:700; line-height:1; }
.kpi-tile .kpi-label { font-size:.72rem; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; }
.kpi-tile .kpi-sub   { font-size:.75rem; color:#9ca3af; }

/* ── Timeline ── */
.tl { position:relative; padding-left:2.4rem; }
.tl::before { content:''; position:absolute; left:.95rem; top:.5rem; bottom:.5rem; width:2px; background:linear-gradient(to bottom,#e5e7eb 0%,#e5e7eb 100%); }
.tl-item { position:relative; margin-bottom:1.1rem; }
.tl-item:last-child { margin-bottom:0; }
.tl-dot { position:absolute; left:-1.95rem; top:.1rem; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.7rem; z-index:1; }
.tl-dot.done  { box-shadow:0 0 0 3px rgba(34,197,94,.2); }
.tl-dot.late  { animation:pulse-red 1.5s infinite; }
@keyframes pulse-red { 0%,100%{box-shadow:0 0 0 2px rgba(239,68,68,.3)} 50%{box-shadow:0 0 0 6px rgba(239,68,68,.1)} }
.tl-content { background:#f9fafb; border:1px solid #f1f5f9; border-radius:.5rem; padding:.45rem .75rem; }
.tl-content.done  { border-color:#d1fae5; background:#f0fdf4; }
.tl-content.late  { border-color:#fee2e2; background:#fff5f5; }

/* ── Trasa visual ── */
.route-bar { display:flex; align-items:center; gap:0; margin:.5rem 0 1rem; }
.route-node { flex-shrink:0; }
.route-node .rn-flag { font-size:1.3rem; line-height:1; }
.route-node .rn-city { font-size:.78rem; font-weight:600; }
.route-node .rn-date { font-size:.68rem; color:#6b7280; }
.route-line { flex:1; height:3px; background:linear-gradient(to right,#6366f1,#22c55e); border-radius:2px; position:relative; margin:0 .5rem; }
.route-line-truck { position:absolute; top:-9px; left:50%; transform:translateX(-50%); font-size:1.1rem; }
.route-pol { color:#6366f1; font-size:.65rem; font-weight:700; position:absolute; top:6px; left:4px; }
.route-pod { color:#22c55e; font-size:.65rem; font-weight:700; position:absolute; top:6px; right:4px; }

/* ── FK badges ── */
.check-pill { display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .7rem; border-radius:2rem; font-size:.78rem; font-weight:600; cursor:pointer; border:2px solid transparent; transition:all .15s; user-select:none; }
.check-pill.checked  { border-color:var(--pill-color); background:color-mix(in srgb,var(--pill-color) 12%,white); color:var(--pill-color); }
.check-pill.unchecked{ border-color:#e5e7eb; background:#f9fafb; color:#9ca3af; }
.check-pill.unchecked:hover { border-color:var(--pill-color); color:var(--pill-color); }
.stepper-step.locked { opacity:.35 !important; cursor:not-allowed !important; }
.stepper-step.locked:hover { background:transparent; }
.stepper-step.locked .stepper-dot { background:#e5e7eb !important; color:#9ca3af !important; }
</style>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- TOPBAR                                                                 -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="order-topbar rounded-3 shadow-sm mb-3">
    <?php if ($isModal): ?>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('orderViewModal')&&bootstrap.Modal.getInstance(document.getElementById('orderViewModal'))?.hide()">
        <i class="ri-close-line"></i>
    </button>
    <?php else: ?>
    <a href="<?= $this->Url->build(['action'=>'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line"></i>
    </a>
    <?php endif; ?>

    <div class="d-flex flex-column">
        <span class="fw-bold fs-5 lh-1"><?= h($order->symbol) ?></span>
        <?php if ($order->ozn): ?><span class="text-muted small"><?= h($order->ozn) ?></span><?php endif; ?>
    </div>

    <span class="badge <?= $nlCurrent['cls'] ?> fs-6 px-3">
        <i class="<?= $nlCurrent['icon'] ?> me-1"></i><?= $nlCurrent['label'] ?>
    </span>
    <span class="badge bg-light border text-muted" title="Status Speed ERP">Speed: <?= h($speedLabel) ?></span>
    <?php if ($order->is_complete): ?>
    <span class="badge bg-success"><i class="ri-check-double-line me-1"></i>KOMPLETNE</span>
    <?php endif; ?>

    <!-- Separator -->
    <div class="ms-auto d-flex gap-2 align-items-center">
        <?php
            $_hasAnyInvoice = !empty($order->invoices ?? []);
            $_firstInvId    = $_hasAnyInvoice ? $order->invoices[0]->id : null;
        ?>
        <?php if ($_hasAnyInvoice): ?>
        <a href="<?= $this->Url->build(['controller'=>'Invoices','action'=>'view',$_firstInvId]) ?>"
           class="btn btn-sm btn-success">
            <i class="ri-file-text-line me-1"></i>Faktura sprzedażowa
            <?php if (count($order->invoices) > 1): ?>
                <span class="badge bg-light text-success ms-1"><?= count($order->invoices) ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= h($fvUrl) ?>" class="btn btn-sm btn-outline-primary" title="Wystaw kolejną fakturę">
            <i class="ri-add-line"></i>
        </a>
        <?php elseif (empty($order->pod_at)): ?>
        <button class="btn btn-sm btn-outline-secondary" disabled title="Potwierdź POD żeby odblokować fakturowanie">
            <i class="ri-lock-line me-1"></i>Wystaw fakturę
        </button>
        <?php else: ?>
        <a href="<?= h($fvUrl) ?>" class="btn btn-sm btn-primary fw-semibold">
            <i class="ri-file-add-line me-1"></i>Wystaw fakturę
        </a>
        <?php endif; ?>
        <a href="<?= $this->Url->build(['action'=>'index','?'=>['status'=>'przetermin']]) ?>"
           class="btn btn-sm btn-outline-secondary" title="Dashboard">
            <i class="ri-dashboard-line"></i>
        </a>
        <a href="<?= $this->Url->build(['action' => 'add', '?' => ['dup' => $order->id]]) ?>"
           class="btn btn-sm btn-outline-primary" title="<?= __('Utwórz nowe zlecenie z prefillem z tego') ?>">
            <i class="ri-file-copy-line me-1"></i><?= __('Duplikuj') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'pdfConfirmation', $order->id]) ?>"
           class="btn btn-sm btn-outline-danger" title="<?= __('Pobierz PDF potwierdzenia zlecenia') ?>" target="_blank">
            <i class="ri-file-pdf-2-line me-1"></i>PDF
        </a>
        <?php if (($order->source ?? 'speed') === 'manual'): ?>
            <a href="<?= $this->Url->build(['action' => 'edit', $order->id]) ?>"
               class="btn btn-sm btn-outline-info" title="<?= __('Edytuj zlecenie ręczne') ?>">
                <i class="ri-pencil-line me-1"></i><?= __('Edytuj') ?>
            </a>
            <?php if (empty($order->invoice_id)): ?>
                <?= $this->Form->postLink(
                    '<i class="ri-delete-bin-line"></i>',
                    ['action' => 'delete', $order->id],
                    [
                        'escape'   => false,
                        'class'    => 'btn btn-sm btn-outline-danger',
                        'title'    => __('Usuń zlecenie'),
                        'confirm'  => __('Na pewno usunąć zlecenie {0}?', $order->symbol),
                    ]
                ) ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- KPI: 4 kafelki u góry                                                 -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="row g-2 mb-3">
    <?php
    // Czas transportu (deadline → delivery)
    $transportDays = null;
    if ($order->date_deadline && $order->date_delivery) {
        $d1 = $order->date_deadline instanceof \DateTimeInterface ? $order->date_deadline : new \DateTimeImmutable(substr((string)$order->date_deadline,0,10));
        $d2 = $order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery : new \DateTimeImmutable(substr((string)$order->date_delivery,0,10));
        $transportDays = $d1->diff($d2)->days;
    }
    // Opóźnienie (planowana data dostawy vs Rzeczywisty rozładunek) — pad_at to tylko
    // potwierdzenie checkboxu, więc opieramy terminowość na actual_unload_at.
    // Fallback na pod_at (legacy zlecenia) i na "dziś" gdy przeterminowane bez POD.
    $delayDays = null;
    $delayBasis = null; // dla podpisu KPI
    if ($order->date_delivery && !empty($order->actual_unload_at)) {
        $d1 = $order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery : new \DateTimeImmutable(substr((string)$order->date_delivery,0,10));
        $d2 = $order->actual_unload_at instanceof \DateTimeInterface ? $order->actual_unload_at : new \DateTimeImmutable(substr((string)$order->actual_unload_at,0,16));
        $diff = $d1->diff($d2);
        $delayDays = $diff->invert ? -$diff->days : $diff->days;
        $delayBasis = 'actual';
    } elseif ($order->date_delivery && $order->pod_at) {
        // Fallback dla starych zleceń bez actual_unload_at
        $d1 = $order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery : new \DateTimeImmutable(substr((string)$order->date_delivery,0,10));
        $d2 = $order->pod_at instanceof \DateTimeInterface ? $order->pod_at : new \DateTimeImmutable(substr((string)$order->pod_at,0,16));
        $diff = $d1->diff($d2);
        $delayDays = $diff->invert ? -$diff->days : $diff->days;
        $delayBasis = 'pod';
    } elseif ($order->date_delivery && empty($order->pod_at)) {
        $d1 = $order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery : new \DateTimeImmutable(substr((string)$order->date_delivery,0,10));
        if ($d1 < $today) { $delayDays = $today->diff($d1)->days; $delayBasis = 'overdue'; }
    }
    ?>
    <div class="col-6 col-md-3">
        <div class="kpi-tile">
            <div class="kpi-label"><i class="ri-money-dollar-circle-line me-1"></i>Wartość netto</div>
            <div class="kpi-val"><?= $fnum($order->netto) ?></div>
            <div class="kpi-sub"><?= $cur ?><?= ($order->exchange_rate && $order->exchange_rate != 1) ? ' · kurs '.number_format((float)$order->exchange_rate,4,',','') : '' ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-tile">
            <div class="kpi-label"><i class="ri-route-line me-1"></i>Czas transportu</div>
            <div class="kpi-val <?= $transportDays === null ? 'text-muted' : '' ?>">
                <?= $transportDays !== null ? $transportDays.' dni' : '—' ?>
            </div>
            <div class="kpi-sub">
                <?= $fdate($order->date_deadline) ?? '?' ?> → <?= $fdate($order->date_delivery) ?? '?' ?>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-tile">
            <div class="kpi-label"><i class="ri-timer-line me-1"></i>Terminowość</div>
            <?php if ($delayDays === null && empty($order->pod_at) && $order->date_delivery): ?>
            <?php   $d1=($order->date_delivery instanceof \DateTimeInterface?$order->date_delivery:new \DateTimeImmutable(substr((string)$order->date_delivery,0,10))); ?>
            <?php   $daysLeft = (int)$today->diff($d1)->days * ($d1 >= $today ? 1 : -1); ?>
            <div class="kpi-val <?= $daysLeft < 0 ? 'text-danger' : 'text-warning' ?>">
                <?= $daysLeft >= 0 ? 'za '.$daysLeft.'d' : abs($daysLeft).'d po term.' ?>
            </div>
            <div class="kpi-sub">oczekiwanie na POD</div>
            <?php elseif ($delayDays !== null): ?>
            <div class="kpi-val <?= $delayDays <= 0 ? 'text-success' : 'text-danger' ?>">
                <?= $delayDays <= 0 ? ($delayDays==0?'W terminie':abs($delayDays).'d wcześniej') : $delayDays.'d opóźnienia' ?>
            </div>
            <div class="kpi-sub">
                <?php if ($delayBasis === 'actual'): ?>
                    <?= $delayDays <= 0 ? '✔ OK' : '⚠ Opóźnienie' ?> · <span class="text-muted">rzeczywisty rozładunek</span>
                <?php elseif ($delayBasis === 'pod'): ?>
                    <?= $delayDays <= 0 ? '✔ OK' : '⚠ Opóźnienie' ?> · <span class="text-muted">POD (legacy)</span>
                <?php else: ?>
                    <?= $delayDays <= 0 ? '✔ OK' : '⚠ Opóźnienie' ?>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="kpi-val text-muted">—</div>
            <div class="kpi-sub">brak danych</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-tile">
            <div class="kpi-label"><i class="ri-receipt-line me-1"></i>FK / zlecenia rozl.</div>
            <div class="kpi-val <?= count($costInvoices) ? 'text-purple' : 'text-muted' ?>">
                <?= count($costInvoices) ?> FK
            </div>
            <div class="kpi-sub">
                <?php if (count($costInvoices)):
                    $totalFk = array_sum(array_map(fn($ci)=>(float)$ci->brutto, $costInvoices));
                ?>
                Suma: <?= $fnum($totalFk) ?> <?= h($costInvoices[0]->currency ?? 'PLN') ?>
                <?php else: ?>brak przypisanych<?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- STEPPER + POL/POD/FK/FS PILLS                                          -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">

        <!-- Stepper 5 kroków -->
        <div class="stepper mb-4" id="status-stepper">
            <?php foreach ($nlStatusMap as $val => $s):
                $state = $val < $nlStatus ? 'past' : ($val === $nlStatus ? 'active' : 'future');
                // Zrealizowane (4) wymaga Załadowane (3) — nie pozwalamy przeskoczyć.
                // Zafakturowane (5) wymaga Zrealizowane (4) analogicznie.
                $isLocked = ($val === 4 && $nlStatus < 3)
                         || ($val === 5 && $nlStatus < 4);
                $lockTitle = $val === 4 ? 'Najpierw ustaw "Załadowane"' : 'Najpierw ustaw "Zrealizowane"';
                $tipText   = $isLocked ? $lockTitle : ('Ustaw: ' . $s['label']);
            ?>
            <div class="stepper-step <?= $state ?><?= $isLocked ? ' locked' : '' ?>"
                 data-status="<?= $val ?>"
                 <?= $isLocked ? 'data-locked="1"' : '' ?>
                 style="--step-color:<?= $s['color'] ?>" title="<?= h($tipText) ?>">
                <div class="stepper-dot" style="background:<?= $state !== 'future' ? $s['color'] : '#e5e7eb' ?>;color:<?= $state!=='future'?'white':'#9ca3af' ?>">
                    <i class="<?= $s['icon'] ?>"></i>
                </div>
                <div class="stepper-label" style="color:<?= $state==='active'?$s['color']:($state==='future'?'#9ca3af':'#6b7280') ?>">
                    <?= h($s['label']) ?>
                </div>
            </div>
            <?php if ($val < 5): ?>
            <div class="stepper-arrow"><i class="ri-arrow-right-s-line"></i></div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($_isAdminUser): ?>
        <!-- Tryb admin: force-update (cofnij status, odznacz POL/POD/FK/FS bez auto-eskalacji) -->
        <div class="d-flex justify-content-center mb-2">
            <label class="check-pill <?= 'unchecked' ?>" id="forceModeLabel"
                   style="--pill-color:#dc2626; cursor:pointer"
                   title="Tylko admin: pozwala cofnąć status oraz odznaczyć POL/POD/FK/FS bez auto-eskalacji">
                <input type="checkbox" class="d-none" id="force-mode-toggle">
                <i class="ri-shield-flash-line"></i>
                <span class="d-flex flex-column gap-0" style="line-height:1.2">
                    <span><strong>Tryb admin — wsteczne zmiany</strong></span>
                    <span class="opacity-50" style="font-weight:400;font-size:.65rem">
                        Wyłączony — auto-eskalacja statusu aktywna
                    </span>
                </span>
            </label>
        </div>
        <?php endif; ?>

        <!-- Rzeczywisty załadunek / rozładunek (datetime, edytowalne) -->
        <?php
            $fmtLocal = function ($v): string {
                if (!$v) return '';
                if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d\TH:i');
                return str_replace(' ', 'T', substr((string)$v, 0, 16));
            };
            // Po Załadowane (3+) blokujemy edycję actual_load_at — to data faktu;
            // po Zrealizowane (4+) blokujemy actual_unload_at. Admin może zawsze.
            $lockLoad   = $nlStatus >= 3 && !$_isAdminUser;
            $lockUnload = $nlStatus >= 4 && !$_isAdminUser;
        ?>
        <style>
            .actual-time-input.is-pending { border-color:#f59e0b; box-shadow:0 0 0 .15rem rgba(245,158,11,.18); }
            .actual-time-input.is-saved   { border-color:#22c55e; box-shadow:0 0 0 .15rem rgba(34,197,94,.18); }
            .actual-time-input.is-error   { border-color:#ef4444; box-shadow:0 0 0 .15rem rgba(239,68,68,.18); }
            .actual-time-input:disabled   { background:#f3f4f6; cursor:not-allowed; opacity:.85; }
        </style>
        <div class="d-flex gap-2 flex-wrap justify-content-center mb-2" id="actual-times-row">
            <div class="actual-time-box" style="background:rgba(14,165,233,.07);border:1px solid rgba(14,165,233,.25);border-radius:10px;padding:8px 12px;min-width:240px">
                <div class="small text-muted mb-1 d-flex align-items-center gap-1">
                    <i class="ri-truck-line text-info"></i> <strong>Rzeczywisty załadunek</strong>
                    <?php if ($lockLoad): ?>
                        <i class="ri-lock-line ms-auto text-muted" title="Zablokowane — status 'Załadowane' lub wyżej. Skontaktuj się z administratorem aby zmienić."></i>
                    <?php endif; ?>
                </div>
                <input type="datetime-local" class="form-control form-control-sm actual-time-input"
                       data-field="actual_load_at"
                       value="<?= h($fmtLocal($order->actual_load_at ?? null)) ?>"
                       <?= $lockLoad ? 'disabled' : '' ?>>
                <?php if (!empty($order->date_deadline)): ?>
                    <div class="text-muted" style="font-size:.7em">Planowany: <?= h($fdate($order->date_deadline)) ?></div>
                <?php endif; ?>
            </div>
            <div class="actual-time-box" style="background:rgba(14,165,233,.07);border:1px solid rgba(14,165,233,.25);border-radius:10px;padding:8px 12px;min-width:240px">
                <div class="small text-muted mb-1 d-flex align-items-center gap-1">
                    <i class="ri-truck-line text-info"></i> <strong>Rzeczywisty rozładunek</strong>
                    <?php if ($lockUnload): ?>
                        <i class="ri-lock-line ms-auto text-muted" title="Zablokowane — status 'Zrealizowane'. Skontaktuj się z administratorem aby zmienić."></i>
                    <?php endif; ?>
                </div>
                <input type="datetime-local" class="form-control form-control-sm actual-time-input"
                       data-field="actual_unload_at"
                       value="<?= h($fmtLocal($order->actual_unload_at ?? null)) ?>"
                       <?= $lockUnload ? 'disabled' : '' ?>>
                <?php if (!empty($order->date_delivery)): ?>
                    <div class="text-muted" style="font-size:.7em">Planowany: <?= h($fdate($order->date_delivery)) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Przyciski "Zatwierdź i oznacz jako…" -->
        <?php
            $plannedLoadDate   = $order->date_deadline instanceof \DateTimeInterface ? $order->date_deadline->format('Y-m-d') : substr((string)($order->date_deadline ?? ''), 0, 10);
            $plannedUnloadDate = $order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery->format('Y-m-d') : substr((string)($order->date_delivery ?? ''), 0, 10);

            // Tryby przycisków:
            //  - 'set'    — domyślny, oznacz jako załadowany/zrealizowany
            //  - 'reset'  — admin: cofnij oznaczenie (zeruje _at, cofa status)
            //  - 'locked' — non-admin, już zaznaczone (button disabled)
            $polMode = !empty($order->pol_at)
                ? ($_isAdminUser ? 'reset' : 'locked')
                : 'set';
            $podMode = !empty($order->pod_at)
                ? ($_isAdminUser ? 'reset' : 'locked')
                : (empty($order->pol_at) ? 'blocked' : 'set');
        ?>
        <div class="d-flex gap-2 flex-wrap justify-content-center mb-3" id="confirm-buttons-row">
            <button type="button"
                    class="btn btn-sm <?= $polMode === 'reset' ? 'btn-outline-warning' : 'btn-outline-primary' ?> confirm-and-mark-btn"
                    data-target="pol"
                    data-mode="<?= $polMode ?>"
                    data-pod-set="<?= !empty($order->pod_at) ? '1' : '0' ?>"
                    data-time-field="actual_load_at"
                    data-planned-date="<?= h($plannedLoadDate ?? '') ?>"
                    <?= $polMode === 'locked' ? 'disabled title="Już oznaczone jako załadowane"' : '' ?>>
                <?php if ($polMode === 'reset'): ?>
                    <i class="ri-arrow-go-back-line me-1"></i>Cofnij oznaczenie <strong>załadowany</strong>
                <?php else: ?>
                    <i class="ri-check-double-line me-1"></i>Zatwierdź i oznacz jako <strong>załadowany</strong>
                <?php endif; ?>
            </button>
            <button type="button"
                    class="btn btn-sm <?= $podMode === 'reset' ? 'btn-outline-warning' : 'btn-outline-success' ?> confirm-and-mark-btn"
                    data-target="pod"
                    data-mode="<?= $podMode ?>"
                    data-time-field="actual_unload_at"
                    data-planned-date="<?= h($plannedUnloadDate ?? '') ?>"
                    <?php if ($podMode === 'locked'): ?>
                        disabled title="Już oznaczone jako zrealizowane"
                    <?php elseif ($podMode === 'blocked'): ?>
                        disabled title="Najpierw oznacz jako załadowany"
                    <?php endif; ?>>
                <?php if ($podMode === 'reset'): ?>
                    <i class="ri-arrow-go-back-line me-1"></i>Cofnij oznaczenie <strong>zrealizowany</strong>
                <?php else: ?>
                    <i class="ri-flag-2-line me-1"></i>Zatwierdź i oznacz jako <strong>zrealizowany</strong>
                <?php endif; ?>
                <?php if ($podMode === 'blocked'): ?>
                    <i class="ri-lock-line ms-1 opacity-75" style="font-size:.85em"></i>
                <?php endif; ?>
            </button>
        </div>

        <!-- POL/POD/FK/FS pills -->
        <div class="d-flex gap-2 flex-wrap justify-content-center" id="checks-row">
            <?php foreach ($checks as $chk): ?>
            <label class="check-pill <?= $chk['checked'] ? 'checked' : 'unchecked' ?>"
                   style="--pill-color:<?= $chk['color'] ?>"
                   title="<?= h($chk['desc']) ?>"
                   for="chk-<?= $chk['field'] ?>">
                <input type="checkbox" class="d-none check-toggle"
                       id="chk-<?= $chk['field'] ?>"
                       data-field="<?= $chk['field'] ?>"
                       <?= $chk['checked'] ? 'checked' : '' ?>>
                <i class="<?= $chk['icon'] ?>"></i>
                <span class="d-flex flex-column gap-0" style="line-height:1.2">
                    <span><?= $chk['label'] ?> — <?= $chk['title'] ?></span>
                    <?php if ($chk['date']): ?>
                        <span class="opacity-75" style="font-size:.65rem;font-weight:400"><?= h($chk['date']) ?></span>
                        <?php if ($chk['by']): ?>
                        <span class="opacity-60" style="font-size:.62rem;font-weight:400">
                            <i class="ri-user-line"></i> <?= h($chk['by']) ?>
                        </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="opacity-50" style="font-weight:400;font-size:.65rem">niezaznaczone</span>
                    <?php endif; ?>
                </span>
            </label>
            <?php endforeach; ?>
        </div>

        <!-- Legenda skrótów dokumentów -->
        <div class="mt-2 d-flex justify-content-center">
            <button type="button" class="btn btn-link btn-sm text-muted p-0" style="font-size:.72rem;text-decoration:none"
                    data-bs-toggle="collapse" data-bs-target="#pills-legend" aria-expanded="false">
                <i class="ri-information-line me-1"></i>Co oznaczają POL / POD / FK / FS?
            </button>
        </div>
        <div class="collapse" id="pills-legend">
            <div class="mt-2 p-2 rounded" style="background:#f8fafc;border:1px solid #e5e7eb;font-size:.78rem">
                <div class="d-flex flex-column gap-1">
                    <div><strong style="color:#6366f1">POL</strong> — <em>Proof of Loading</em> — dokument potwierdzający <strong>załadunek</strong> towaru (zdjęcie, skan CMR ładunkowy itp.).</div>
                    <div><strong style="color:#22c55e">POD</strong> — <em>Proof of Delivery</em> — dokument potwierdzający <strong>rozładunek</strong> i odbiór towaru przez klienta (podpisany CMR).</div>
                    <div><strong style="color:#8b5cf6">FK</strong> — <em>Faktura kosztowa</em> — faktura od <strong>przewoźnika</strong> (nasz koszt transportu).</div>
                    <div><strong style="color:#374151">FS</strong> — <em>Faktura sprzedażowa</em> — faktura wystawiona <strong>dla klienta</strong> (nasz przychód).</div>
                </div>
                <div class="mt-2 pt-2 border-top text-muted" style="font-size:.7rem">
                    <i class="ri-lightbulb-line me-1"></i>To są <strong>dokumenty</strong>, nie statusy zlecenia. Status (Przyjęte → Zaplanowane → Załadowane → Zrealizowane → Zafakturowane) zmienia się na powyższym stepperze lub przyciskami "Zatwierdź i oznacz jako…".
                </div>
            </div>
        </div>

        <!-- Dokumenty tylko elektronicznie -->
        <div class="d-flex justify-content-center mt-2">
            <label class="check-pill <?= !empty($order->docs_electronic_only) ? 'checked' : 'unchecked' ?>"
                   style="--pill-color:#0ea5e9"
                   title="Dokumenty tylko elektronicznie — brak wysyłki papierowej"
                   for="chk-docs-electronic">
                <input type="checkbox" class="d-none check-toggle"
                       id="chk-docs-electronic"
                       data-field="docs_electronic"
                       <?= !empty($order->docs_electronic_only) ? 'checked' : '' ?>>
                <i class="ri-mail-forbid-line"></i>
                <span class="d-flex flex-column gap-0" style="line-height:1.2">
                    <span>Dokumenty tylko elektronicznie</span>
                    <span class="opacity-50" style="font-weight:400;font-size:.65rem">
                        <?= !empty($order->docs_electronic_only) ? 'Brak wysyłki papierowej' : 'niezaznaczone' ?>
                    </span>
                </span>
            </label>
        </div>

        <!-- Alerty -->
        <?php if (empty($order->pod_at) && empty($order->invoices ?? [])): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 mt-3 mb-0 py-2">
            <i class="ri-lock-line fs-5"></i>
            <div><strong>Blokada fakturowania</strong> — potwierdź POD żeby odblokować wystawienie faktury sprzedażowej.</div>
        </div>
        <?php endif; ?>
        <?php if (!$order->is_complete && count($missing)): ?>
        <div class="alert alert-light border mt-2 mb-0 py-2 d-flex align-items-center gap-2">
            <i class="ri-information-line text-muted"></i>
            <div class="text-muted small">Brakuje do zamknięcia zlecenia: <strong><?= implode(' · ', $missing) ?></strong></div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- TRASA WIZUALNA + TIMELINE                                              -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-3">

<div class="col-lg-8">
<div class="card border-0 shadow-sm h-100">
    <div class="card-header fw-semibold bg-white border-bottom d-flex align-items-center gap-2">
        <i class="ri-map-2-line text-primary"></i>Trasa transportu
    </div>
    <div class="card-body">

        <!-- Wizualna linia trasy -->
        <div class="route-bar mb-3">
            <!-- Załadunek -->
            <div class="route-node text-center" style="min-width:90px">
                <?php if ($loadCountry): ?>
                <div class="rn-flag"><?php
                    $cc = strtolower($loadCountry);
                    $map2 = ['pl'=>'🇵🇱','de'=>'🇩🇪','fr'=>'🇫🇷','nl'=>'🇳🇱','be'=>'🇧🇪','cz'=>'🇨🇿','sk'=>'🇸🇰','hu'=>'🇭🇺','ro'=>'🇷🇴','ua'=>'🇺🇦','gb'=>'🇬🇧','it'=>'🇮🇹','es'=>'🇪🇸','at'=>'🇦🇹','lt'=>'🇱🇹','lv'=>'🇱🇻','ee'=>'🇪🇪','se'=>'🇸🇪','no'=>'🇳🇴','dk'=>'🇩🇰','fi'=>'🇫🇮'];
                    echo $map2[$cc] ?? '🏳️';
                ?></div>
                <?php endif; ?>
                <div class="rn-city fw-bold"><?= h($loadCity ?: $loadCountry ?: '—') ?></div>
                <?php if ($loadCode): ?><div class="rn-date"><?= h($loadCode) ?></div><?php endif; ?>
                <div class="rn-date text-warning fw-semibold"><?= $fdate($order->date_deadline) ?? '' ?></div>
                <?php if ($order->pol_at): ?>
                <span class="badge bg-success mt-1" style="font-size:.62rem">✔ POL</span>
                <?php else: ?>
                <span class="badge bg-light border text-muted mt-1" style="font-size:.62rem">POL pending</span>
                <?php endif; ?>
            </div>

            <!-- Linia z ciężarówką -->
            <div class="route-line flex-grow-1 mx-3" style="position:relative;top:0">
                <div class="route-line-truck">🚛</div>
                <?php if ($order->pol_at): ?><div class="route-pol">POL ✔</div><?php endif; ?>
                <?php if ($order->pod_at): ?><div class="route-pod">✔ POD</div><?php endif; ?>
            </div>

            <!-- Rozładunek -->
            <div class="route-node text-center" style="min-width:90px">
                <?php if ($unloadCountry): ?>
                <div class="rn-flag"><?php
                    $cc2 = strtolower($unloadCountry);
                    echo $map2[$cc2] ?? '🏳️';
                ?></div>
                <?php endif; ?>
                <div class="rn-city fw-bold"><?= h($unloadCity ?: $unloadCountry ?: '—') ?></div>
                <?php if ($unloadName): ?><div class="rn-date"><?= h($unloadName) ?></div><?php endif; ?>
                <div class="rn-date text-success fw-semibold"><?= $fdate($order->date_delivery) ?? '' ?></div>
                <?php if ($order->pod_at): ?>
                <span class="badge bg-success mt-1" style="font-size:.62rem">✔ POD</span>
                <?php else: ?>
                <span class="badge bg-light border text-muted mt-1" style="font-size:.62rem">POD pending</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Transport -->
        <div class="row g-2">
            <?php if ($order->transport_type || $order->vehicle_reg): ?>
            <div class="col-auto">
                <div class="d-flex align-items-center gap-2 bg-light rounded px-3 py-2">
                    <i class="ri-truck-line text-primary fs-5"></i>
                    <div>
                        <?php if ($order->transport_type): ?><div class="fw-semibold small"><?= h($order->transport_type) ?></div><?php endif; ?>
                        <?php if ($order->vehicle_reg): ?><div class="text-muted small"><?= h($order->vehicle_reg) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($order->driver): ?>
            <div class="col-auto">
                <div class="d-flex align-items-center gap-2 bg-light rounded px-3 py-2">
                    <i class="ri-user-line text-muted fs-5"></i>
                    <div>
                        <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Kierowca</div>
                        <div class="fw-semibold small"><?= h($order->driver) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($order->carrier): ?>
            <div class="col-auto">
                <div class="d-flex align-items-center gap-2 bg-light rounded px-3 py-2">
                    <i class="ri-building-line text-muted fs-5"></i>
                    <div>
                        <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Przewoźnik</div>
                        <div class="fw-semibold small"><?= h($order->carrier) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($order->cargo_type): ?>
            <div class="col-auto">
                <div class="d-flex align-items-center gap-2 bg-light rounded px-3 py-2">
                    <i class="ri-box-3-line text-muted fs-5"></i>
                    <div>
                        <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Ładunek</div>
                        <div class="fw-semibold small"><?= h($order->cargo_type) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($order->route_description): ?>
        <div class="mt-2 text-muted small"><i class="ri-map-pin-line me-1"></i><?= h($order->route_description) ?></div>
        <?php endif; ?>

    </div>
</div>
</div>

<!-- Timeline -->
<div class="col-lg-4">
<div class="card border-0 shadow-sm h-100">
    <div class="card-header fw-semibold bg-white border-bottom d-flex align-items-center gap-2">
        <i class="ri-history-line text-primary"></i>Timeline zlecenia
    </div>
    <div class="card-body py-3">
        <?php if (empty($tlEvents)): ?>
        <div class="text-muted small text-center">Brak danych timeline.</div>
        <?php else: ?>
        <div class="tl">
            <?php foreach ($tlEvents as $ev): ?>
            <div class="tl-item">
                <div class="tl-dot <?= ($ev['done']??false)?'done':'' ?> <?= ($ev['late']??false)?'late':'' ?>"
                     style="background:<?= $ev['color'] ?>;color:white">
                    <i class="<?= $ev['icon'] ?>" style="font-size:.65rem"></i>
                </div>
                <div class="tl-content <?= ($ev['done']??false)?'done':'' ?> <?= ($ev['late']??false)?'late':'' ?>">
                    <div class="fw-semibold" style="font-size:.8rem"><?= h($ev['label']) ?></div>
                    <?php if ($ev['sub']): ?>
                    <div class="text-muted" style="font-size:.72rem"><?= h($ev['sub']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($ev['by'])): ?>
                    <div style="font-size:.68rem;color:#6b7280;margin-top:.15rem">
                        <i class="ri-user-line me-1"></i><?= h($ev['by']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($ev['late']??false): ?>
                    <div class="text-danger" style="font-size:.68rem;font-weight:600"><i class="ri-alarm-warning-line me-1"></i>Przeterminowane</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

</div><!-- /row trasa + timeline -->

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- DANE SZCZEGÓŁOWE: zleceniodawca + finansowe                           -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-3">

<div class="col-lg-4">
<div class="card border-0 shadow-sm h-100">
    <div class="card-header fw-semibold bg-white border-bottom">
        <i class="ri-building-4-line me-1 text-muted"></i>Zleceniodawca
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <tr><th class="ps-3 text-muted fw-normal" style="width:42%">Nazwa</th>
                    <td class="fw-semibold"><?= h($order->buyer_name) ?></td></tr>
                <?php if ($order->buyer_nip): ?>
                <tr><th class="ps-3 text-muted fw-normal">NIP</th>
                    <td><code><?= h($order->buyer_nip) ?></code></td></tr>
                <?php endif; ?>
                <?php if ($order->buyer_street): ?>
                <tr><th class="ps-3 text-muted fw-normal">Adres</th>
                    <td><?= h($order->buyer_street) ?></td></tr>
                <?php endif; ?>
                <?php if ($order->buyer_postal_code || $order->buyer_city): ?>
                <tr><th class="ps-3 text-muted fw-normal">Miasto</th>
                    <td><?= h(trim($order->buyer_postal_code.' '.$order->buyer_city)) ?></td></tr>
                <?php endif; ?>
                <?php if ($order->buyer_country): ?>
                <tr><th class="ps-3 text-muted fw-normal">Kraj</th>
                    <td><?= h($order->buyer_country) ?></td></tr>
                <?php endif; ?>
                <?php if ($order->buyer_email): ?>
                <tr><th class="ps-3 text-muted fw-normal">E-mail</th>
                    <td><a href="mailto:<?= h($order->buyer_email) ?>"><?= h($order->buyer_email) ?></a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="col-lg-4">
<div class="card border-0 shadow-sm h-100">
    <div class="card-header fw-semibold bg-white border-bottom">
        <i class="ri-file-list-3-line me-1 text-muted"></i>Dane zlecenia
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <?php if ($order->our_ref): ?>
                <tr><th class="ps-3 text-muted fw-normal" style="width:50%">Nasz ref.</th>
                    <td class="fw-semibold"><?= h($order->our_ref) ?></td></tr>
                <?php endif; ?>
                <?php if ($order->title1): ?>
                <tr><th class="ps-3 text-muted fw-normal">Tytuł</th>
                    <td><?= h($order->title1) ?></td></tr>
                <?php endif; ?>
                <tr><th class="ps-3 text-muted fw-normal">Data dok.</th>
                    <td><?= $fdate($order->date_doc) ?? '—' ?></td></tr>
                <?php if ($order->payment_terms): ?>
                <tr><th class="ps-3 text-muted fw-normal">Płatność</th>
                    <td><?= h($order->payment_terms) ?></td></tr>
                <?php endif; ?>
                <tr><th class="ps-3 text-muted fw-normal">Wystawił</th>
                    <td><?= h($order->nick_created) ?></td></tr>
                <?php if ($order->speed_modified_at): ?>
                <tr><th class="ps-3 text-muted fw-normal">Sync Speed</th>
                    <td class="text-muted small"><?= $fdatetime($order->speed_modified_at) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<div class="col-lg-4">
<div class="card border-0 shadow-sm h-100">
    <div class="card-header fw-semibold bg-white border-bottom">
        <i class="ri-money-dollar-circle-line me-1 text-muted"></i>Finansowe
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <?php
                    // Brutto liczymy z netto+VAT — pole brutto ze Speed ERP
                    // jest często nieprawidłowe (czasem 0, czasem niezgodne).
                    $_brutto = (float)($order->netto ?? 0) + (float)($order->vat ?? 0);
                ?>
                <tr><th class="ps-3 text-muted fw-normal" style="width:45%">Netto</th>
                    <td><?= $fnum($order->netto) ?> <span class="text-muted"><?= $cur ?></span></td></tr>
                <tr><th class="ps-3 text-muted fw-normal">VAT</th>
                    <td><?= $fnum($order->vat) ?> <span class="text-muted"><?= $cur ?></span></td></tr>
                <tr><th class="ps-3 text-muted fw-normal">Brutto</th>
                    <td class="fw-bold fs-6"><?= $fnum($_brutto) ?> <span class="fw-normal text-muted"><?= $cur ?></span></td></tr>
                <?php if ($order->exchange_rate && $order->exchange_rate != 1): ?>
                <tr><th class="ps-3 text-muted fw-normal">Kurs</th>
                    <td><?= number_format((float)$order->exchange_rate,6,',','') ?><?= $order->exchange_table?' <span class="text-muted small">('.h($order->exchange_table).')</span>':'' ?></td></tr>
                <?php endif; ?>
                <?php if (count($costInvoices)):
                    $sumFk = array_sum(array_map(fn($ci)=>(float)$ci->brutto,$costInvoices));
                    $margin = (float)$order->netto - $sumFk;
                ?>
                <tr class="table-light"><th class="ps-3 text-muted fw-normal">Suma FK</th>
                    <td class="text-danger fw-semibold">−<?= $fnum($sumFk) ?></td></tr>
                <tr class="<?= $margin >= 0 ? 'table-success' : 'table-danger' ?>">
                    <th class="ps-3 fw-semibold">Marża</th>
                    <td class="fw-bold"><?= $fnum($margin) ?> <span class="fw-normal text-muted"><?= $cur ?></span></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

</div><!-- /row details -->

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- FAKTURY KOSZTOWE (FK)                                                  -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold bg-white border-bottom d-flex align-items-center gap-2">
        <i class="ri-receipt-line text-purple"></i>Faktury kosztowe (FK)
        <span class="badge bg-primary-subtle text-primary ms-1"><?= count($costInvoices) ?></span>
        <button class="btn btn-sm btn-outline-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalFkSearch">
            <i class="ri-link me-1"></i>Przypisz FK
        </button>
    </div>
    <div class="card-body p-0">
    <?php if (empty($costInvoices)): ?>
        <div class="text-center text-muted py-4 small">
            <i class="ri-receipt-line fs-2 d-block mb-1 opacity-25"></i>
            Brak przypisanych faktur kosztowych.
            <div class="mt-2">
                <a href="<?= $this->Url->build(['controller'=>'CostInvoices','action'=>'importKsef']) ?>" class="btn btn-sm btn-outline-primary me-1">
                    <i class="ri-government-line me-1"></i>Importuj z KSeF
                </a>
                <a href="<?= $this->Url->build(['controller'=>'CostInvoices','action'=>'add']) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-add-line me-1"></i>Dodaj ręcznie
                </a>
            </div>
        </div>
    <?php else: ?>
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Numer</th>
                    <th>Przewoźnik</th>
                    <th>Miesiąc</th>
                    <th>Data wyst.</th>
                    <th class="text-end">Brutto</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($costInvoices as $ci):
                $ciSt = ['received'=>'Otrzymana','verified'=>'Zweryfikowana','paid'=>'Zapłacona'][$ci->status]??$ci->status;
                $ciStCls = ['received'=>'bg-secondary-subtle text-secondary','verified'=>'bg-info-subtle text-info','paid'=>'bg-success-subtle text-success'][$ci->status]??'bg-light text-dark';
                $ciNum = $ci->invoice_number ?: ($ci->ksef_number ? '(KSeF)' : '—');
            ?>
            <tr data-ci-id="<?= $ci->id ?>">
                <td class="ps-3 fw-semibold">
                    <a href="<?= $this->Url->build(['controller'=>'CostInvoices','action'=>'view',$ci->id]) ?>"
                       class="text-decoration-none"><?= h($ciNum) ?></a>
                    <?php if ($ci->source === 'ksef'): ?>
                    <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:.62rem">KSeF</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.82rem"><?= h($ci->contractor_name) ?></td>
                <td><span class="badge bg-dark-subtle text-dark" style="font-size:.7rem"><?= h($ci->accounting_month) ?></span></td>
                <td style="font-size:.82rem"><?= h($fdate($ci->issue_date) ?? '—') ?></td>
                <td class="text-end fw-semibold" style="font-size:.82rem">
                    <?= $fnum($ci->brutto) ?> <span class="text-muted fw-normal"><?= h($ci->currency) ?></span>
                </td>
                <td><span class="badge <?= $ciStCls ?>" style="font-size:.7rem"><?= $ciSt ?></span></td>
                <td class="text-end">
                    <button class="btn btn-xs btn-outline-danger py-0 px-1 btn-fk-unassign"
                            data-ci-id="<?= $ci->id ?>" data-num="<?= h($ciNum) ?>" title="Odepnij">
                        <i class="ri-unlink"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- FAKTURA SPRZEDAŻOWA                                                    -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<?php
    // M:N — wszystkie faktury powiązane ze zleceniem (z pivota).
    // $order->invoices załadowany przez SpeedOrdersController::view/viewModal.
    $salesInvoices = $order->invoices ?? [];
?>
<?php if (!empty($salesInvoices)): ?>
<div class="card border-success shadow-sm mb-3">
    <div class="card-header fw-semibold text-success bg-success-subtle border-bottom d-flex align-items-center gap-2">
        <i class="ri-file-text-line"></i>
        Faktury sprzedażowe
        <span class="badge bg-success ms-1"><?= count($salesInvoices) ?></span>
        <?php
            // Link "Wystaw kolejną" zawsze dostępny w prawej części headera
            $curCode = strtoupper(trim((string)($order->currency ?? 'PLN')));
            $addAction = $curCode !== '' && $curCode !== 'PLN' ? 'addCurrency' : 'addVat';
            $addUrl = $this->Url->build(['controller' => 'Invoices', 'action' => $addAction, '?' => ['from_order_id' => $order->id]]);
        ?>
        <a href="<?= h($addUrl) ?>" class="btn btn-sm btn-outline-success ms-auto">
            <i class="ri-add-line me-1"></i>Wystaw kolejną
        </a>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:.85rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Numer faktury</th>
                    <th>Data wystawienia</th>
                    <th>Kwota</th>
                    <th>Status płatności</th>
                    <th class="pe-3 text-end">Akcje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($salesInvoices as $inv): ?>
                <?php
                    $payCls = $inv->paymentstate === 'paid'
                        ? 'success' : ($inv->paymentstate === 'partial' ? 'warning' : 'danger');
                    $payLbl = $inv->paymentstate === 'paid'
                        ? 'Opłacona' : ($inv->paymentstate === 'partial' ? 'Częściowo' : 'Nieopłacona');
                ?>
                <tr>
                    <td class="ps-3 fw-semibold">
                        <i class="ri-file-text-line text-success me-1"></i><?= h($inv->fullnumber ?: substr($inv->id, 0, 8)) ?>
                    </td>
                    <td><?= $inv->date instanceof \DateTimeInterface ? $inv->date->format('d.m.Y') : '—' ?></td>
                    <td><?= number_format((float)($inv->total ?? 0), 2, ',', ' ') ?> <?= h($inv->currency ?? '') ?></td>
                    <td>
                        <span class="badge bg-<?= $payCls ?>-subtle text-<?= $payCls ?> border border-<?= $payCls ?>-subtle">
                            <?= $payLbl ?>
                        </span>
                    </td>
                    <td class="pe-3 text-end">
                        <a href="<?= $this->Url->build(['controller' => 'Invoices', 'action' => 'view', $inv->id]) ?>"
                           class="btn btn-sm btn-outline-success" title="Otwórz fakturę">
                            <i class="ri-external-link-line"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- MAPA TRASY (HERE Maps JS SDK + Routing v8)                              -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<?php if (!empty($order->place_from_name) && !empty($order->place_to_name)): ?>
<?php $_hereKey = (string)\Cake\Core\Configure::read('Here.apiKey'); ?>
<link rel="stylesheet" type="text/css" href="https://js.api.here.com/v3/3.1/mapsjs-ui.css" />
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-core.js"></script>
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-service.js"></script>
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-ui.js"></script>
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-mapevents.js"></script>
<div class="card border-0 shadow-sm mb-3" id="route-card">
    <div class="card-header fw-semibold bg-white border-bottom d-flex align-items-center gap-2 flex-wrap">
        <i class="ri-route-line text-primary"></i> <?= __('Trasa i koszty') ?>
        <span id="route-summary-mini" class="text-muted small ms-auto"></span>
        <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="btn-calc-route">
            <i class="ri-route-fill me-1"></i><?= __('Wyznacz trasę') ?>
        </button>
    </div>
    <div class="card-body p-0">
        <div id="route-map" style="height:420px;border-radius:0 0 .5rem .5rem;background:#f4f6fa;position:relative">
            <div id="route-map-placeholder" class="d-flex h-100 align-items-center justify-content-center text-muted small">
                <i class="ri-route-line me-2" style="font-size:1.6rem"></i>
                <?= __('Kliknij "Wyznacz trasę" aby zobaczyć trasę na mapie') ?>
            </div>
        </div>
        <div id="route-details" class="p-3 border-top" style="display:none"></div>
    </div>
</div>
<script>
(function () {
    var hereKey = <?= json_encode($_hereKey) ?>;
    var orderId = <?= json_encode($order->id) ?>;
    var url     = '<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'forOrder', '__ID__']) ?>'.replace('__ID__', encodeURIComponent(orderId));
    var $btn    = document.getElementById('btn-calc-route');
    var $mapEl  = document.getElementById('route-map');
    var $mini   = document.getElementById('route-summary-mini');
    var $details = document.getElementById('route-details');

    var map = null, routeGroup = null;
    function ensureMap() {
        if (map) return;
        var ph = document.getElementById('route-map-placeholder');
        if (ph) ph.remove();
        var platform = new H.service.Platform({ apikey: hereKey });
        var defaultLayers = platform.createDefaultLayers();
        map = new H.Map($mapEl, defaultLayers.vector.normal.map, {
            center: { lat: 52.0, lng: 19.0 },
            zoom: 5,
            pixelRatio: window.devicePixelRatio || 1
        });
        window.addEventListener('resize', function () { map.getViewPort().resize(); });
        new H.mapevents.Behavior(new H.mapevents.MapEvents(map));
        H.ui.UI.createDefault(map, defaultLayers, 'pl-PL');
        routeGroup = new H.map.Group();
        map.addObject(routeGroup);
    }
    function fmtNum(v, dec) { return v.toLocaleString('pl-PL', {minimumFractionDigits: dec || 0, maximumFractionDigits: dec || 0}); }
    function fmtDur(min) {
        if (!min) return '—';
        var h = Math.floor(min/60), m = min%60;
        return (h > 0 ? h + 'h ' : '') + m + 'min';
    }
    function makeMarker(lat, lng, color, label) {
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="48" viewBox="0 0 36 48">'
                + '<path d="M18 0C8 0 0 8 0 18c0 13 18 30 18 30s18-17 18-30C36 8 28 0 18 0z" fill="' + color + '"/>'
                + '<circle cx="18" cy="18" r="7" fill="white"/>'
                + '<text x="18" y="22" text-anchor="middle" font-family="sans-serif" font-size="11" font-weight="bold" fill="' + color + '">' + label + '</text>'
                + '</svg>';
        var icon = new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 18, y: 48 } });
        return new H.map.Marker({ lat: lat, lng: lng }, { icon: icon });
    }
    function render(data) {
        ensureMap();
        routeGroup.removeAll();
        if (!data.polyline) return;
        var line;
        try {
            line = H.geo.LineString.fromFlexiblePolyline(data.polyline);
        } catch (e) {
            console.error('Polyline decode failed', e);
            alert('<?= __('Błąd dekodowania trasy.') ?>');
            return;
        }
        var outline = new H.map.Polyline(line, { style: { strokeColor: 'rgba(255,255,255,.95)', lineWidth: 10 } });
        var routeLine = new H.map.Polyline(line, { style: { strokeColor: 'rgba(37,99,235,.85)', lineWidth: 6 } });
        routeGroup.addObject(outline);
        routeGroup.addObject(routeLine);
        routeGroup.addObject(makeMarker(data.from.lat, data.from.lng, '#16a34a', 'A'));
        routeGroup.addObject(makeMarker(data.to.lat, data.to.lng, '#dc2626', 'B'));
        var bbox = routeGroup.getBoundingBox();
        if (bbox) {
            map.getViewModel().setLookAtData({ bounds: bbox }, true);
            setTimeout(function () { map.setZoom(Math.max(map.getZoom() - 0.4, 4)); }, 100);
        }

        $mini.textContent = fmtNum(data.distance_km, 1) + ' km · ' + fmtDur(data.duration_min)
            + (data.tolls_total !== null ? ' · ' + fmtNum(data.tolls_total, 2) + ' ' + data.tolls_currency : '');

        var html = '<div class="row g-3">';
        html += '<div class="col-md-3 text-center"><div class="text-muted small"><?= __('Dystans') ?></div><div class="fs-4 fw-bold text-primary">' + fmtNum(data.distance_km, 1) + ' <small class="text-muted">km</small></div></div>';
        html += '<div class="col-md-3 text-center"><div class="text-muted small"><?= __('Czas jazdy') ?></div><div class="fs-4 fw-bold">' + fmtDur(data.duration_min) + '</div></div>';
        if (data.tolls_total !== null) {
            html += '<div class="col-md-3 text-center"><div class="text-muted small"><?= __('Opłaty drogowe') ?></div><div class="fs-4 fw-bold text-warning">' + fmtNum(data.tolls_total, 2) + ' <small class="text-muted">' + data.tolls_currency + '</small></div></div>';
        }
        if (data.vehicle) {
            html += '<div class="col-md-3 text-center"><div class="text-muted small"><?= __('Pojazd') ?></div><div class="small fw-semibold">' + data.vehicle.name + (data.vehicle.plate ? '<br><span class="badge bg-light text-dark border" style="font-family:monospace">' + data.vehicle.plate + '</span>' : '') + '</div></div>';
        }
        html += '</div>';
        if (data.tolls_by_country && Object.keys(data.tolls_by_country).length) {
            html += '<div class="mt-3 pt-3 border-top"><div class="small fw-semibold text-muted mb-2"><?= __('Opłaty per kraj') ?></div><div class="d-flex gap-2 flex-wrap">';
            Object.keys(data.tolls_by_country).forEach(function (cc) {
                html += '<span class="badge bg-warning-subtle text-warning border">🏳️ ' + cc + ': ' + fmtNum(data.tolls_by_country[cc], 2) + ' ' + data.tolls_currency + '</span>';
            });
            html += '</div></div>';
        }
        $details.innerHTML = html;
        $details.style.display = 'block';
    }
    $btn.addEventListener('click', function () {
        $btn.disabled = true;
        $btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> <?= __('Liczę…') ?>';
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (!res.ok || res.data.error) {
                    alert(res.data.message || '<?= __('Błąd kalkulacji trasy.') ?>');
                    return;
                }
                render(res.data);
            })
            .catch(function (e) { alert('<?= __('Błąd sieci') ?>: ' + e.message); })
            .finally(function () {
                $btn.disabled = false;
                $btn.innerHTML = '<i class="ri-route-fill me-1"></i><?= __('Wyznacz trasę') ?>';
            });
    });
})();
</script>
<?php endif; ?>

<!-- GLightbox — CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- MULTI-STOP - dodatkowe stopy w trasie -->
<?php if (!empty($order->speed_order_stops)): ?>
<div class="card border-0 shadow-sm mb-3" id="stops-card">
    <div class="card-header py-2 bg-white d-flex align-items-center gap-2 border-bottom">
        <i class="ri-route-fill text-warning"></i>
        <span class="fw-semibold">Dodatkowe stopy w trasie</span>
        <span class="badge bg-warning-subtle text-warning ms-1"><?= count($order->speed_order_stops) ?></span>
    </div>
    <div class="card-body">
        <?php
        $stopTypeLabels = ['pickup' => 'Załadunek', 'delivery' => 'Rozładunek', 'transit' => 'Postój'];
        $stopTypeColors = ['pickup' => 'success', 'delivery' => 'danger', 'transit' => 'secondary'];
        $stopTypeIcons  = ['pickup' => 'ri-truck-line', 'delivery' => 'ri-inbox-line', 'transit' => 'ri-pause-line'];
        ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Typ</th>
                        <th>Miejsce</th>
                        <th>Planowany</th>
                        <th>Rzeczywisty</th>
                        <th>Uwagi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->speed_order_stops as $stop): ?>
                        <?php
                        $t = $stop->stop_type ?? 'delivery';
                        $lbl = $stopTypeLabels[$t] ?? $t;
                        $col = $stopTypeColors[$t] ?? 'secondary';
                        $ico = $stopTypeIcons[$t] ?? 'ri-map-pin-line';
                        ?>
                        <tr>
                            <td class="text-muted"><?= (int)$stop->stop_index ?></td>
                            <td><span class="badge bg-<?= h($col) ?>-subtle text-<?= h($col) ?>"><i class="<?= h($ico) ?> me-1"></i><?= h($lbl) ?></span></td>
                            <td>
                                <?php if ($stop->place_name): ?><strong><?= h($stop->place_name) ?></strong><br><?php endif; ?>
                                <span class="small">
                                    <?= h(trim(($stop->country_code ?? '') . ' ' . ($stop->postal_code ?? '') . ' ' . ($stop->city ?? ''))) ?>
                                </span>
                                <?php if ($stop->contact_name || $stop->contact_phone): ?>
                                    <div class="small text-muted"><i class="ri-user-line me-1"></i><?= h($stop->contact_name) ?> <?= h($stop->contact_phone) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= $stop->planned_at ? h($stop->planned_at->format('Y-m-d H:i')) : '-' ?></td>
                            <td class="small"><?= $stop->actual_at ? h($stop->actual_at->format('Y-m-d H:i')) : ($stop->completed_at ? '<span class="text-success">✓ ' . h($stop->completed_at->format('Y-m-d H:i')) . '</span>' : '-') ?></td>
                            <td class="small"><?= h($stop->cargo_notes ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- APPROVAL WORKFLOW -->
<?php
$approvalStatus = $order->approval_status ?? 'not_required';
if ($approvalStatus !== 'not_required'):
    $identity = $this->getRequest()->getAttribute('identity');
    $userRole = (string)($identity?->get('role') ?? '');
    $isMgr = in_array($userRole, ['spedycja_manager', 'sales_manager'], true)
        || (bool)($identity?->get('is_admin') ?? false);
    $statusMap = [
        'pending'  => ['level' => 'warning', 'icon' => 'ri-time-line',  'label' => 'Oczekuje akceptacji managera'],
        'approved' => ['level' => 'success', 'icon' => 'ri-shield-check-line', 'label' => 'Zaakceptowane'],
        'rejected' => ['level' => 'danger',  'icon' => 'ri-close-circle-line', 'label' => 'Odrzucone'],
    ];
    $st = $statusMap[$approvalStatus] ?? $statusMap['pending'];
?>
<div class="card border-0 shadow-sm mb-3" id="approval-card">
    <div class="card-body">
        <div class="alert alert-<?= h($st['level']) ?> mb-<?= $isMgr && $approvalStatus === 'pending' ? '3' : '0' ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <i class="<?= h($st['icon']) ?> me-1"></i>
                    <strong><?= h($st['label']) ?></strong>
                    <?php if ($order->approved_at): ?>
                        <div class="small mt-1 text-muted">
                            <?= h($order->approved_at->format('Y-m-d H:i')) ?>
                            <?php if ($order->approval_note): ?>
                                &middot; <?= h($order->approval_note) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($isMgr && $approvalStatus === 'pending'): ?>
            <div class="row g-2">
                <div class="col-md-8">
                    <input type="text" id="approval-note" class="form-control form-control-sm" placeholder="Komentarz (wymagany dla odrzucenia)">
                </div>
                <div class="col-md-4 text-end">
                    <?= $this->Form->postLink(
                        '<i class="ri-check-line me-1"></i>Akceptuj',
                        ['action' => 'approve', $order->id],
                        [
                            'escape' => false,
                            'class' => 'btn btn-sm btn-success',
                            'confirm' => 'Zaakceptować zlecenie?',
                            'data' => ['note' => ''],
                            'onclick' => "this.form.querySelector('input[name=note]').value = document.getElementById('approval-note').value; return confirm('Zaakceptować zlecenie?');",
                            'block' => 'note_hidden',
                        ]
                    ) ?>
                    <?= $this->Form->postLink(
                        '<i class="ri-close-line me-1"></i>Odrzuć',
                        ['action' => 'reject', $order->id],
                        [
                            'escape' => false,
                            'class' => 'btn btn-sm btn-danger',
                            'onclick' => "var n = document.getElementById('approval-note').value.trim(); if (!n) { alert('Podaj powód odrzucenia'); return false; } this.form.querySelector('input[name=note]').value = n; return confirm('Odrzucić zlecenie?');",
                        ]
                    ) ?>
                </div>
            </div>
            <script>
            (function(){
                // Wstrzykiwanie ukrytego pola note do wszystkich formow postLink dla approve/reject
                document.querySelectorAll('form[action*="zaakceptuj"], form[action*="odrzuc"]').forEach(function(f){
                    if (!f.querySelector('input[name=note]')) {
                        var i = document.createElement('input');
                        i.type = 'hidden'; i.name = 'note'; i.value = '';
                        f.appendChild(i);
                    }
                });
            })();
            </script>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- NOTATKI WEWNETRZNE                                                        -->
<div class="card border-0 shadow-sm mb-3" id="notes-card">
    <div class="card-header py-2 bg-white d-flex align-items-center gap-2 border-bottom">
        <i class="ri-chat-3-line text-primary"></i>
        <span class="fw-semibold">Notatki wewnętrzne</span>
        <span class="badge bg-primary-subtle text-primary ms-1"><?= count($notes ?? []) ?></span>
    </div>
    <div class="card-body">
        <?= $this->Form->create(null, ['url' => ['action' => 'noteAdd', $order->id], 'type' => 'post', 'class' => 'mb-3']) ?>
            <div class="row g-2">
                <div class="col-md-2">
                    <?= $this->Form->select('note_type', [
                        'note'       => 'Notatka',
                        'reminder'   => 'Przypomnienie',
                        'phone_call' => 'Rozmowa tel.',
                        'email'      => 'Email',
                    ], ['class' => 'form-select form-select-sm', 'value' => 'note']) ?>
                </div>
                <div class="col-md-8">
                    <?= $this->Form->textarea('body', ['class' => 'form-control form-control-sm', 'rows' => 1, 'placeholder' => 'Nowa notatka...', 'required' => true]) ?>
                </div>
                <div class="col-md-2 text-end">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="ri-add-line me-1"></i>Dodaj
                    </button>
                </div>
            </div>
        <?= $this->Form->end() ?>

        <?php if (empty($notes)): ?>
            <div class="text-muted small text-center py-3">Brak notatek. Dodaj pierwszą powyżej.</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notes as $note): ?>
                    <?php
                    $typeIcon = [
                        'note' => 'ri-sticky-note-line',
                        'system' => 'ri-settings-line',
                        'reminder' => 'ri-alarm-line',
                        'phone_call' => 'ri-phone-line',
                        'email' => 'ri-mail-line',
                    ][$note->note_type] ?? 'ri-chat-3-line';
                    $typeColor = [
                        'note' => 'primary',
                        'system' => 'secondary',
                        'reminder' => 'warning',
                        'phone_call' => 'success',
                        'email' => 'info',
                    ][$note->note_type] ?? 'primary';
                    $authorName = $note->user
                        ? trim(($note->user->first_name ?? '') . ' ' . ($note->user->last_name ?? '')) ?: ($note->user->username ?? '?')
                        : 'system';
                    ?>
                    <div class="list-group-item px-0 py-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="small">
                                    <i class="<?= h($typeIcon) ?> text-<?= h($typeColor) ?> me-1"></i>
                                    <strong><?= h($authorName) ?></strong>
                                    <span class="text-muted ms-2" style="font-size:.72rem">
                                        <?= h($note->created->format('Y-m-d H:i')) ?>
                                    </span>
                                    <span class="badge bg-<?= h($typeColor) ?>-subtle text-<?= h($typeColor) ?> ms-1" style="font-size:.65rem"><?= h($note->note_type) ?></span>
                                </div>
                                <div class="mt-1 small" style="white-space: pre-wrap"><?= h($note->body) ?></div>
                            </div>
                            <div>
                                <?= $this->Form->postLink(
                                    '<i class="ri-delete-bin-line"></i>',
                                    ['action' => 'noteDelete', $note->id],
                                    [
                                        'escape' => false,
                                        'class' => 'btn btn-sm btn-link text-danger p-0',
                                        'title' => 'Usuń',
                                        'confirm' => 'Usunąć notatkę?',
                                    ]
                                ) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ZAŁĄCZNIKI CMR                                                          -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="card border-0 shadow-sm mb-3" id="cmr-card">
  <div class="card-header fw-semibold bg-white border-bottom d-flex align-items-center gap-2">
    <i class="ri-attachment-2 text-primary"></i>Dokumenty CMR
    <span class="badge bg-primary-subtle text-primary ms-1" id="cmr-count"><?= count($attachments) ?></span>
    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="cmr-upload-btn">
      <i class="ri-upload-cloud-line me-1"></i>Dodaj plik
    </button>
  </div>
  <div class="card-body">

    <!-- Strefa drag & drop -->
    <div id="cmr-dropzone"
         class="border border-2 border-dashed rounded-3 d-flex flex-column align-items-center justify-content-center text-muted mb-3"
         style="min-height:120px;cursor:pointer;border-color:#cbd5e1!important;transition:background .2s;display:none!important">
      <i class="ri-cloud-upload-line" style="font-size:2rem"></i>
      <div class="mt-1 small">Przeciągnij pliki lub <span class="text-primary text-decoration-underline">kliknij</span> aby wybrać</div>
      <div class="text-muted" style="font-size:.72rem">JPG, PNG, WEBP, GIF, PDF · max 15 MB</div>
      <input type="file" id="cmr-file-input" multiple accept="image/*,application/pdf" class="d-none">
    </div>

    <!-- Formularz etykiety (pojawia się przy wyborze pliku) -->
    <div id="cmr-label-form" class="mb-3 d-none">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <label class="small fw-semibold text-nowrap mb-0">Etykieta:</label>
        <select id="cmr-label-select" class="form-select form-select-sm" style="width:auto;min-width:180px">
          <option value="">— brak etykiety —</option>
          <?php foreach ($attachmentLabels as $lbl): ?>
          <option value="<?= $lbl->id ?>"><?= h($lbl->name) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-sm btn-primary" id="cmr-upload-confirm">
          <i class="ri-upload-2-line me-1"></i>Wyślij
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="cmr-upload-cancel">Anuluj</button>
        <span id="cmr-selected-name" class="small text-muted fst-italic"></span>
      </div>
      <div id="cmr-progress-wrap" class="mt-2 d-none">
        <div class="progress" style="height:6px"><div class="progress-bar progress-bar-striped progress-bar-animated" id="cmr-progress-bar" style="width:0%"></div></div>
      </div>
    </div>

    <!-- Galeria istniejących załączników -->
    <div id="cmr-gallery" class="row g-2">
      <?php foreach ($attachments as $att): ?>
      <?php
        $isImg = str_starts_with($att->mime_type ?? '', 'image/');
        $url   = '/' . ltrim(str_replace('\\', '/', $att->file_path), '/');
        $labelName = $att->speed_order_attachment_label->name ?? 'Brak etykiety';
        $glType    = $isImg ? 'image' : 'inline';
        $glHref    = $isImg ? h($url) : '#pdf-inline-' . $att->id;
      ?>
      <?php if (!$isImg): ?>
      <div id="pdf-inline-<?= $att->id ?>" style="display:none">
        <object data="<?= h($url) ?>" type="application/pdf" style="width:90vw;height:82vh;display:block">
          <p class="p-3">Twoja przeglądarka nie obsługuje podglądu PDF. <a href="<?= h($url) ?>" target="_blank">Pobierz plik</a></p>
        </object>
      </div>
      <?php endif; ?>
      <div class="col-6 col-md-3 col-lg-2" id="cmr-att-<?= $att->id ?>">
        <div class="card h-100 border shadow-sm cmr-thumb position-relative">
          <a href="<?= $glHref ?>"
             class="cmr-lightbox d-flex align-items-center justify-content-center overflow-hidden"
             style="height:110px;background:#f8f9fa"
             data-gallery="cmr-gallery-<?= (int)$order->id ?>"
             data-type="<?= $glType ?>"
             data-title="<?= h($att->original_name) ?> — <?= h($labelName) ?>"
             data-description="<?= h($att->uploaded_by ?? '') ?><?= $att->uploaded_by ? ' · ' : '' ?><?= $att->created instanceof \DateTimeInterface ? $att->created->format('d.m.Y H:i') : substr((string)$att->created, 0, 16) ?>">
            <?php if ($isImg): ?>
            <img src="<?= h($url) ?>" class="w-100 h-100" style="object-fit:cover" alt="<?= h($att->original_name) ?>">
            <?php else: ?>
            <i class="ri-file-pdf-2-line text-danger" style="font-size:2.5rem"></i>
            <?php endif; ?>
          </a>
          <div class="card-body p-1">
            <div class="small fw-semibold text-truncate" title="<?= h($att->original_name) ?>"><?= h($att->original_name) ?></div>
            <span class="badge bg-primary-subtle text-primary" style="font-size:.65rem"><?= h($labelName) ?></span>
            <div class="text-muted mt-1" style="font-size:.65rem">
              <?= $att->created instanceof \DateTimeInterface ? $att->created->format('d.m.Y H:i') : substr((string)$att->created, 0, 16) ?>
              <?php if ($att->uploaded_by): ?> · <?= h($att->uploaded_by) ?><?php endif; ?>
            </div>
          </div>
          <button type="button"
                  class="btn btn-sm btn-danger cmr-delete-btn position-absolute"
                  style="top:4px;right:4px;padding:2px 6px;font-size:.7rem;opacity:.85"
                  data-id="<?= $att->id ?>"
                  data-name="<?= h($att->original_name) ?>"
                  title="Usuń">
            <i class="ri-delete-bin-line"></i>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (empty($attachments)): ?>
    <p class="text-muted small mb-0" id="cmr-empty-msg">Brak załączników. Dodaj pierwsze dokumenty CMR.</p>
    <?php endif; ?>
  </div>
</div>

<!-- GLightbox — JS -->
<script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
<script>
(function () {
  const orderId   = <?= (int)$order->id ?>;
  const uploadUrl = '/zlecenia/' + orderId + '/upload-attachment';
  const deleteUrl = (attId) => '/zlecenia/' + orderId + '/delete-attachment/' + attId;
  const csrfToken = document.querySelector('meta[name="csrfToken"]')?.content ?? '';
  const galleryAttr = 'cmr-gallery-' + orderId;

  const dropzone    = document.getElementById('cmr-dropzone');
  const fileInput   = document.getElementById('cmr-file-input');
  const labelForm   = document.getElementById('cmr-label-form');
  const labelSelect = document.getElementById('cmr-label-select');
  const uploadBtn   = document.getElementById('cmr-upload-btn');
  const confirmBtn  = document.getElementById('cmr-upload-confirm');
  const cancelBtn   = document.getElementById('cmr-upload-cancel');
  const selectedName= document.getElementById('cmr-selected-name');
  const progressWrap= document.getElementById('cmr-progress-wrap');
  const progressBar = document.getElementById('cmr-progress-bar');
  const gallery     = document.getElementById('cmr-gallery');
  const countBadge  = document.getElementById('cmr-count');
  const emptyMsg    = document.getElementById('cmr-empty-msg');

  let pendingFiles = [];
  let lightbox = null;

  function initLightbox() {
    if (lightbox) lightbox.destroy();
    lightbox = GLightbox({ selector: '.cmr-lightbox', touchNavigation: true, loop: true, zoomable: true, width: '92vw', height: '88vh' });
  }
  initLightbox();

  // Pokaż/ukryj dropzone
  function showDropzone(show) {
    dropzone.style.setProperty('display', show ? 'flex' : 'none', 'important');
  }

  uploadBtn.addEventListener('click', () => {
    showDropzone(true);
    labelForm.classList.add('d-none');
    pendingFiles = [];
    fileInput.value = '';
  });

  dropzone.addEventListener('click', () => fileInput.click());

  dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.style.background = '#f0f7ff'; });
  dropzone.addEventListener('dragleave', () => { dropzone.style.background = ''; });
  dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.style.background = '';
    handleFiles(Array.from(e.dataTransfer.files));
  });

  fileInput.addEventListener('change', () => handleFiles(Array.from(fileInput.files)));

  function handleFiles(files) {
    if (!files.length) return;
    pendingFiles = files;
    showDropzone(false);
    labelForm.classList.remove('d-none');
    selectedName.textContent = files.length === 1 ? files[0].name : files.length + ' pliki';
  }

  cancelBtn.addEventListener('click', () => {
    showDropzone(false);
    labelForm.classList.add('d-none');
    pendingFiles = [];
    fileInput.value = '';
  });

  confirmBtn.addEventListener('click', () => uploadFiles());

  async function uploadFiles() {
    if (!pendingFiles.length) return;
    const labelId = labelSelect.value;
    confirmBtn.disabled = true;
    progressWrap.classList.remove('d-none');
    progressBar.style.width = '0%';

    for (let i = 0; i < pendingFiles.length; i++) {
      const file = pendingFiles[i];
      const fd   = new FormData();
      fd.append('file', file);
      fd.append('label_id', labelId);
      fd.append('_csrfToken', csrfToken);

      progressBar.style.width = Math.round((i / pendingFiles.length) * 80) + '%';

      try {
        const resp = await fetch(uploadUrl, { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.ok) {
          appendThumb(data);
          if (data.pol_at) updateStatusPill('pol_at', data.pol_at);
          if (data.pod_at) updateStatusPill('pod_at', data.pod_at);
          if (data.nordlogis_status) {
            const nsLabel = nlLabels[data.nordlogis_status] || ('Status ' + data.nordlogis_status);
            Swal.fire({ icon: 'success', title: 'Status zlecenia: ' + nsLabel, toast: true, position: 'top-end', timer: 2500, showConfirmButton: false, timerProgressBar: true })
              .then(() => location.reload());
          }
        } else {
          Swal.fire({ icon: 'error', title: 'Błąd', text: data.error ?? 'Nieznany błąd', toast: true, position: 'top-end', timer: 4000, showConfirmButton: false });
        }
      } catch (err) {
        Swal.fire({ icon: 'error', title: 'Błąd sieci', text: String(err), toast: true, position: 'top-end', timer: 4000, showConfirmButton: false });
      }
    }

    progressBar.style.width = '100%';
    setTimeout(() => {
      progressWrap.classList.add('d-none');
      progressBar.style.width = '0%';
      confirmBtn.disabled = false;
      labelForm.classList.add('d-none');
      pendingFiles = [];
      fileInput.value = '';
      initLightbox();
    }, 600);
  }

  // Aktualizuje wizualnie pill POL/POD po automatycznym ustawieniu statusu przez upload
  function updateStatusPill(field, dateVal) {
    const input = document.querySelector('.check-toggle[data-field="' + field + '"]');
    if (!input) return;
    const pill = input.closest('.check-pill');
    if (!pill || pill.classList.contains('checked')) return;

    // Oznacz jako checked
    pill.classList.remove('unchecked');
    pill.classList.add('checked');
    input.checked = true;

    // Zaktualizuj tekst daty w pillecie
    const spans = pill.querySelectorAll('span.opacity-50, span.opacity-75');
    const d = new Date(dateVal);
    const fmt = d.toLocaleDateString('pl-PL', {day:'2-digit', month:'2-digit', year:'numeric'})
              + ' ' + d.toLocaleTimeString('pl-PL', {hour:'2-digit', minute:'2-digit'});
    spans.forEach(s => {
      if (s.classList.contains('opacity-50')) {
        s.classList.remove('opacity-50');
        s.classList.add('opacity-75');
        s.style.fontSize = '.65rem';
        s.style.fontWeight = '400';
        s.textContent = fmt;
      }
    });

    // Toast informacyjny
    const label = field === 'pol_at' ? 'POL' : 'POD';
    Swal.fire({ icon: 'success', title: 'Status ' + label + ' ustawiony — ' + fmt, toast: true, position: 'top-end', timer: 3000, showConfirmButton: false, timerProgressBar: true });
  }

  function appendThumb(data) {
    if (emptyMsg) emptyMsg.style.display = 'none';

    const isImg   = data.mime_type?.startsWith('image/');
    const url     = '/' + data.file_path.replace(/^\//, '');
    const label   = data.label ?? 'Brak etykiety';
    const glType  = isImg ? 'image' : 'inline';
    const glHref  = isImg ? url : '#pdf-inline-' + data.id;
    const desc    = (data.uploaded_by ? data.uploaded_by + ' · ' : '') + data.created;

    if (!isImg) {
      const pdfCont = document.createElement('div');
      pdfCont.id = 'pdf-inline-' + data.id;
      pdfCont.style.display = 'none';
      pdfCont.innerHTML = `<object data="${url}" type="application/pdf" style="width:90vw;height:82vh;display:block"><p class="p-3">Twoja przeglądarka nie obsługuje podglądu PDF. <a href="${url}" target="_blank">Pobierz plik</a></p></object>`;
      document.body.appendChild(pdfCont);
    }

    const col = document.createElement('div');
    col.className = 'col-6 col-md-3 col-lg-2';
    col.id = 'cmr-att-' + data.id;
    col.innerHTML = `
      <div class="card h-100 border shadow-sm cmr-thumb position-relative">
        <a href="${glHref}"
           class="cmr-lightbox d-flex align-items-center justify-content-center overflow-hidden"
           style="height:110px;background:#f8f9fa"
           data-gallery="${galleryAttr}"
           data-type="${glType}"
           data-title="${data.original_name} — ${label}"
           data-description="${desc}">
          ${isImg
            ? `<img src="${url}" class="w-100 h-100" style="object-fit:cover">`
            : `<i class="ri-file-pdf-2-line text-danger" style="font-size:2.5rem"></i>`}
        </a>
        <div class="card-body p-1">
          <div class="small fw-semibold text-truncate" title="${data.original_name}">${data.original_name}</div>
          <span class="badge bg-primary-subtle text-primary" style="font-size:.65rem">${label}</span>
          <div class="text-muted mt-1" style="font-size:.65rem">${data.created}${data.uploaded_by ? ' · ' + data.uploaded_by : ''}</div>
        </div>
        <button type="button" class="btn btn-sm btn-danger cmr-delete-btn position-absolute"
                style="top:4px;right:4px;padding:2px 6px;font-size:.7rem;opacity:.85"
                data-id="${data.id}" data-name="${data.original_name}" title="Usuń">
          <i class="ri-delete-bin-line"></i>
        </button>
      </div>`;
    gallery.appendChild(col);
    updateCount(1);
  }

  // Usuwanie — obsługa przez delegację, z pominięciem kliknięcia w link lightboxa
  gallery.addEventListener('click', async e => {
    const btn = e.target.closest('.cmr-delete-btn');
    if (!btn) return;
    e.stopPropagation();

    const attId = btn.dataset.id;
    const name  = btn.dataset.name;

    const res = await Swal.fire({
      title: 'Usunąć załącznik?',
      text: name,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      confirmButtonText: 'Tak, usuń',
      cancelButtonText: 'Anuluj',
    });
    if (!res.isConfirmed) return;

    const fd = new FormData();
    fd.append('_csrfToken', csrfToken);
    try {
      const resp = await fetch(deleteUrl(attId), { method: 'POST', body: fd });
      const data = await resp.json();
      if (data.ok) {
        document.getElementById('cmr-att-' + attId)?.remove();
        updateCount(-1);
        initLightbox();
        if (!gallery.querySelector('[id^="cmr-att-"]')) {
          const msg = document.createElement('p');
          msg.id = 'cmr-empty-msg';
          msg.className = 'text-muted small mb-0';
          msg.textContent = 'Brak załączników. Dodaj pierwsze dokumenty CMR.';
          gallery.parentElement.appendChild(msg);
        }
      } else {
        Swal.fire({ icon: 'error', title: 'Błąd', text: data.error ?? 'Nieznany błąd' });
      }
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Błąd sieci', text: String(err) });
    }
  });

  function updateCount(delta) {
    const cur = parseInt(countBadge.textContent || '0', 10);
    countBadge.textContent = Math.max(0, cur + delta);
  }
})();
</script>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- UWAGI                                                                  -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<?php if ($order->notes): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold bg-white border-bottom"><i class="ri-sticky-note-line me-1 text-warning"></i>Uwagi</div>
    <div class="card-body"><p class="mb-0" style="white-space:pre-wrap"><?= h($order->notes) ?></p></div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- SUROWE DANE SPEED (zwijalne)                                           -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<?php if ($rawData): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
        <span class="text-muted small">Surowe dane Speed ERP (GLO_*)</span>
        <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="collapse" data-bs-target="#raw-json">
            Pokaż / Ukryj
        </button>
    </div>
    <div class="collapse" id="raw-json">
        <div class="card-body p-0">
            <table class="table table-sm mb-0 font-monospace small">
                <tbody>
                    <?php foreach ($rawData as $k=>$v): ?>
                    <tr>
                        <th class="ps-3 text-nowrap text-muted fw-normal" style="width:200px"><?= h($k) ?></th>
                        <td><?= h((string)($v??'')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: wyszukiwarka FK -->
<div class="modal fade" id="modalFkSearch" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ri-receipt-line me-1"></i>Przypisz fakturę kosztową</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" id="fk-search-q" class="form-control form-control-sm"
                       placeholder="Numer faktury, NIP, przewoźnik…">
            </div>
            <div class="col-md-4">
                <input type="month" id="fk-search-month" class="form-control form-control-sm" value="<?= date('Y-m') ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100" id="btn-fk-search">
                    <i class="ri-search-line"></i>
                </button>
            </div>
        </div>
        <div id="fk-search-results">
            <div class="text-muted small text-center py-3">Wpisz frazę lub wybierz miesiąc i kliknij Szukaj.</div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="<?= $this->Url->build(['controller'=>'CostInvoices','action'=>'add']) ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="ri-add-line me-1"></i>Dodaj FK
        </a>
        <a href="<?= $this->Url->build(['controller'=>'CostInvoices','action'=>'importKsef']) ?>" class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="ri-government-line me-1"></i>Importuj z KSeF
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- HISTORIA ZMIAN STATUSÓW                                               -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<?php if (!empty($statusLogs)): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold bg-white border-bottom d-flex align-items-center gap-2"
         style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#status-log-body">
        <i class="ri-history-line text-secondary"></i>
        Historia zmian statusów
        <span class="badge bg-secondary-subtle text-secondary ms-1"><?= count($statusLogs) ?></span>
        <i class="ri-arrow-down-s-line ms-auto text-muted" id="log-toggle-icon"></i>
    </div>
    <div class="collapse" id="status-log-body">
    <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:.78rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:150px">Data i godzina</th>
                    <th style="width:140px">Pole</th>
                    <th>Zmiana</th>
                    <th>Powód / notatka</th>
                    <th style="width:180px"><i class="ri-user-line me-1"></i>Użytkownik</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $fieldLabels = [
                'pol_at'          => 'POL — Załadunek',
                'pod_at'          => 'POD — Rozładunek',
                'fk_at'           => 'FK — Faktura kosztowa',
                'fs_at'           => 'FS — Faktura sprzedażowa',
                'nordlogis_status'=> 'Status Nordlogis',
            ];
            $nlLabels = [1=>'Przyjęte',2=>'Zaplanowane',3=>'Załadowane',4=>'Zrealizowane',5=>'Zafakturowane'];
            $reasonLabels = [
                'downtime'        => 'Przestój',
                'cargo_not_ready' => 'Brak gotowości towaru',
                'driver_late'     => 'Opóźnienie kierowcy',
                'no_avisation'    => 'Brak awizacji',
            ];
            foreach (array_reverse($statusLogs) as $log):
                $fieldLabel = $fieldLabels[$log->field] ?? h($log->field);
                $isCheck    = str_ends_with($log->field, '_at');
                $isSet      = ($log->new_value !== null);
                if ($isCheck) {
                    $changeHtml = $isSet
                        ? '<span class="badge bg-success-subtle text-success border">✔ Zaznaczono</span> <span class="text-muted">' . h($log->new_value) . '</span>'
                        : '<span class="badge bg-secondary-subtle text-secondary border">✗ Odznaczono</span>';
                } elseif ($log->field === 'nordlogis_status') {
                    $oldLabel = $nlLabels[(int)$log->old_value] ?? $log->old_value;
                    $newLabel = $nlLabels[(int)$log->new_value] ?? $log->new_value;
                    $changeHtml = '<span class="text-muted">' . h($oldLabel) . '</span>'
                        . ' <i class="ri-arrow-right-line mx-1 text-muted"></i>'
                        . '<span class="fw-semibold">' . h($newLabel) . '</span>';
                } else {
                    $changeHtml = h($log->old_value) . ' → ' . h($log->new_value);
                }
                $createdStr = $log->created instanceof \DateTimeInterface
                    ? $log->created->format('d.m.Y H:i:s')
                    : substr((string)($log->created ?? ''), 0, 19);
            ?>
            <tr>
                <td class="ps-3 text-muted"><?= h($createdStr) ?></td>
                <td class="fw-semibold"><?= $fieldLabel ?></td>
                <td><?= $changeHtml ?></td>
                <td>
                    <?php
                        $logReason = (string)($log->reason ?? '');
                        $logNote   = (string)($log->note ?? '');
                    ?>
                    <?php if ($logReason !== ''): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                            <i class="ri-alert-line me-1"></i><?= h($reasonLabels[$logReason] ?? $logReason) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($logNote !== ''): ?>
                        <div class="text-muted mt-1" style="font-size:.72rem;white-space:pre-wrap"><?= h($logNote) ?></div>
                    <?php endif; ?>
                    <?php if ($logReason === '' && $logNote === ''): ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($log->username): ?>
                        <?php
                            $logAvatar = !empty($log->user_id) ? ($logAvatarMap[(string)$log->user_id] ?? null) : null;
                            $logInitial = mb_strtoupper(mb_substr((string)$log->username, 0, 1));
                        ?>
                        <span class="d-inline-flex align-items-center gap-1">
                            <?php if ($logAvatar): ?>
                                <img src="<?= h($logAvatar) ?>" alt=""
                                     style="width:20px;height:20px;border-radius:50%;object-fit:cover;border:1px solid #e5e7eb">
                            <?php else: ?>
                                <span style="width:20px;height:20px;border-radius:50%;background:#e5e7eb;color:#6b7280;display:inline-flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:700"><?= h($logInitial) ?></span>
                            <?php endif; ?>
                            <span class="text-dark"><?= h($log->username) ?></span>
                        </span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </div>
</div>
<?php endif; ?>

<?php
// W trybie modala (AJAX) wstrzykujemy script INLINE — bo $this->fetch('scriptBottom')
// nie jest wywoływane bez layoutu, a DOMContentLoaded już dawno fired na parent page.
// Używamy IIFE z natychmiastowym uruchomieniem zamiast addEventListener('DOMContentLoaded').
if (!$isModal):
    $this->append('scriptBottom');
endif;
?>
<script>
(function () {
    var orderId      = <?= (int)$order->id ?>;
    var updateUrl    = '<?= $updateStatusUrl ?>';
    var assignFkUrl  = '<?= $assignFkUrl ?>';
    var unassignFkUrl= '<?= $unassignFkUrl ?>';
    var searchFkUrl  = '<?= $searchFkUrl ?>';
    var csrfToken    = '<?= h($csrfToken) ?>';
    var nlLabels     = <?= json_encode(array_map(fn($s)=>$s['label'],$nlStatusMap)) ?>;

    // ── Tryb admin force ──
    var forceToggle = document.getElementById('force-mode-toggle');
    var forceLabel  = document.getElementById('forceModeLabel');
    function isForce() { return !!(forceToggle && forceToggle.checked); }
    if (forceToggle) {
        forceToggle.addEventListener('change', function () {
            var on = forceToggle.checked;
            forceLabel.classList.toggle('checked', on);
            forceLabel.classList.toggle('unchecked', !on);
            var sub = forceLabel.querySelector('.opacity-50');
            if (sub) sub.textContent = on
                ? 'WŁ — możesz cofnąć status i odznaczyć POL/POD/FK/FS'
                : 'Wyłączony — auto-eskalacja statusu aktywna';
        });
    }

    function post(url, payload) {
        // Dla updateUrl + włączonego trybu force dorzucamy force=1 (server ignoruje
        // dla nie-adminów). NIE dorzucamy do innych endpointów (assignFk itp.).
        if (url === updateUrl && isForce()) {
            payload = Object.assign({}, payload, { force: 1 });
        }
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload),
        }).then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }

    // Własny toast — Swal toast w trybie modala ma rozjechaną animację ikony (puste kółko).
    function showToast(msg, ok) {
        var host = document.getElementById('app-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'app-toast-host';
            host.style.cssText = 'position:fixed;right:1rem;bottom:1rem;z-index:11000;display:flex;flex-direction:column;gap:.5rem;pointer-events:none;';
            document.body.appendChild(host);
        }
        var t = document.createElement('div');
        t.style.cssText = 'pointer-events:auto;background:#fff;border:1px solid '
            + (ok ? '#22c55e' : '#ef4444') + ';border-left:4px solid '
            + (ok ? '#22c55e' : '#ef4444')
            + ';border-radius:.5rem;padding:.6rem .9rem;box-shadow:0 4px 12px rgba(0,0,0,.12);'
            + 'min-width:240px;max-width:360px;display:flex;align-items:center;gap:.6rem;'
            + 'font-size:.85rem;color:#1f2937;opacity:0;transform:translateX(20px);'
            + 'transition:opacity .2s,transform .2s;';
        t.innerHTML =
            '<i class="' + (ok ? 'ri-checkbox-circle-fill' : 'ri-error-warning-fill') + '" '
            + 'style="font-size:1.2rem;color:' + (ok ? '#22c55e' : '#ef4444') + ';flex-shrink:0"></i>'
            + '<span style="flex:1;line-height:1.3">' + String(msg).replace(/[<>&]/g, function (c) {
                return { '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c];
            }) + '</span>';
        host.appendChild(t);
        requestAnimationFrame(function () { t.style.opacity = '1'; t.style.transform = 'translateX(0)'; });
        setTimeout(function () {
            t.style.opacity = '0';
            t.style.transform = 'translateX(20px)';
            setTimeout(function () { t.remove(); }, 250);
        }, 2500);
    }

    // ── Stepper: zmiana statusu ──
    document.querySelectorAll('.stepper-step').forEach(function(btn) {
        btn.addEventListener('click', function() {
            // Zablokowany krok — nie wysyłamy, tylko toast wyjaśniający.
            if (this.dataset.locked === '1') {
                showToast(this.getAttribute('title') || 'Niedostępne — wykonaj wcześniejsze kroki', false);
                return;
            }
            var ns = parseInt(this.dataset.status);
            post(updateUrl, { id: orderId, nordlogis_status: ns }).then(function(data) {
                if (data.success) {
                    showToast('Status: ' + (nlLabels[ns]||ns), true);
                    setTimeout(function(){ location.reload(); }, 700);
                } else { showToast(data.error||'Błąd', false); }
            }).catch(function(e){ showToast('Błąd: '+e.message, false); });
        });
    });

    // ── Pills: POL/POD/FK/FS ──
    document.querySelectorAll('.check-toggle').forEach(function(chk) {
        chk.addEventListener('change', function() {
            var self  = this;
            var key   = this.dataset.field.replace('_at','');
            var field = this.dataset.field;
            var val   = this.checked;
            var payload = { id: orderId };
            payload[key] = val;
            post(updateUrl, payload).then(function(data) {
                if (data.success) {
                    showToast((val?'✔ ':'✖ ') + key.toUpperCase() + (val?' zaznaczony':' odznaczony'), true);
                    // Zaktualizuj pill bez reload
                    var label = self.closest('label');
                    if (label) {
                        label.classList.toggle('checked', val);
                        label.classList.toggle('unchecked', !val);
                        // Zaktualizuj tekst daty i usera
                        var span = label.querySelector('span.d-flex');
                        if (span) {
                            var dateSpan = span.querySelectorAll('span')[1];
                            var bySpan   = span.querySelectorAll('span')[2];
                            if (val) {
                                var atVal = data[field];
                                var byVal = data[field.replace('_at','_by')];
                                if (dateSpan) {
                                    dateSpan.textContent = atVal ? atVal.substring(0,16).replace('T',' ') : '';
                                    dateSpan.className = 'opacity-75';
                                    dateSpan.style.fontSize = '.65rem';
                                    dateSpan.style.fontWeight = '400';
                                }
                                if (byVal) {
                                    if (!bySpan) {
                                        bySpan = document.createElement('span');
                                        span.appendChild(bySpan);
                                    }
                                    bySpan.innerHTML = '<i class="ri-user-line"></i> ' + byVal;
                                    bySpan.className = 'opacity-60';
                                    bySpan.style.fontSize = '.62rem';
                                    bySpan.style.fontWeight = '400';
                                }
                            } else {
                                if (dateSpan) { dateSpan.textContent = 'niezaznaczone'; dateSpan.className = 'opacity-50'; dateSpan.style.fontSize = '.65rem'; dateSpan.style.fontWeight = '400'; }
                                if (bySpan)   { bySpan.textContent = ''; }
                            }
                        }
                    }
                    // Odśwież sekcję historii po chwili (logi są server-side)
                    setTimeout(function(){ location.reload(); }, 1500);
                } else {
                    self.checked = !val;
                    showToast(data.error||'Błąd', false);
                }
            }).catch(function(e){ self.checked=!val; showToast('Błąd: '+e.message,false); });
        });
    });

    // ── Rzeczywisty załadunek / rozładunek (datetime-local inputs, debounced save) ──
    document.querySelectorAll('.actual-time-input').forEach(function (inp) {
        var saveTimer = null;
        var lastSaved = (inp.value || '').trim();
        function setState(state) {
            inp.classList.remove('is-pending', 'is-saved', 'is-error');
            if (state) inp.classList.add(state);
        }
        function save() {
            var field = inp.dataset.field;
            var val   = (inp.value || '').trim();
            // Pomijaj walidacyjne pośrednie wartości typu '2026-05-13T' bez godziny.
            // Akceptujemy pełne YYYY-MM-DDTHH:MM albo pusty (= wyczyść).
            if (val !== '' && !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(val)) {
                return;
            }
            if (val === lastSaved) {
                return;
            }
            var payload = { id: orderId };
            payload[field] = val;
            setState('is-pending');
            post(updateUrl, payload).then(function (data) {
                if (data.success) {
                    lastSaved = val;
                    setState('is-saved');
                    var label = field === 'actual_load_at' ? 'Rzeczywisty załadunek' : 'Rzeczywisty rozładunek';
                    showToast('✔ ' + label + ' ' + (val ? 'zapisany' : 'wyczyszczony'), true);
                    setTimeout(function () { setState(null); }, 1500);
                } else {
                    setState('is-error');
                    showToast(data.error || 'Błąd zapisu', false);
                }
            }).catch(function (e) {
                setState('is-error');
                showToast('Błąd: ' + e.message, false);
            });
        }
        function scheduleSave(delay) {
            if (saveTimer) clearTimeout(saveTimer);
            saveTimer = setTimeout(save, delay);
        }
        // input — fires gdy datepicker zmienia wartość (Chrome/Edge/Firefox);
        // change — fires po blur/Enter (fallback);
        // blur   — wymuszamy zapis natychmiast gdy user wychodzi z pola.
        inp.addEventListener('input',  function () { scheduleSave(600); });
        inp.addEventListener('change', function () { scheduleSave(150); });
        inp.addEventListener('blur',   function () {
            if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
            save();
        });
    });

    // ── Przyciski "Zatwierdź i oznacz jako załadowany/zrealizowany" ──
    // Jeśli data rzeczywista zgodna z planowaną (dzień) — wysyłamy od razu.
    // Inaczej Swal z dropdownem powodu + obowiązkową notatką.
    var reasonOptions = {
        'pol': {
            'downtime':       'Przestój na załadunku',
            'cargo_not_ready':'Brak gotowości towaru',
            'driver_late':    'Opóźnienie w przyjeździe na załadunek',
            'no_avisation':   'Brak awizacji',
        },
        'pod': {
            'downtime':       'Przestój na rozładunku',
            'cargo_not_ready':'Brak gotowości towaru',
            'driver_late':    'Opóźnienie w przyjeździe na rozładunek',
            'no_avisation':   'Brak awizacji',
        }
    };
    function doConfirmMark(target, payload) {
        // POL pill + status 3 (Załadowane) lub POD pill + status 4 (Zrealizowane).
        // Wysyłamy ns jawnie, bo applyAutoNlStatus zależy od plików (nie pól _at),
        // więc bez pliku status by się nie zmienił. Tu user chce "zatwierdzić" status.
        payload[target] = 1;
        payload.nordlogis_status = target === 'pol' ? 3 : 4;
        post(updateUrl, payload).then(function (data) {
            if (data.success) {
                showToast('✔ Oznaczono jako ' + (target === 'pol' ? 'załadowane' : 'zrealizowane'), true);
                setTimeout(function () {
                    // W trybie modala — zamykamy go (parent się sam nie reloaduje,
                    // ale przy następnym otwarciu zobaczysz świeży stan).
                    var modalEl = document.querySelector('#orderViewModal.show');
                    if (modalEl && window.bootstrap) {
                        var inst = bootstrap.Modal.getInstance(modalEl);
                        if (inst) { inst.hide(); return; }
                    }
                    location.reload();
                }, 1000);
            } else {
                showToast(data.error || 'Błąd zapisu', false);
            }
        }).catch(function (e) { showToast('Błąd: ' + e.message, false); });
    }
    document.querySelectorAll('.confirm-and-mark-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target      = btn.dataset.target;          // 'pol' | 'pod'
            var mode        = btn.dataset.mode || 'set';   // 'set' | 'reset' | 'locked' | 'blocked'
            var timeField   = btn.dataset.timeField;        // 'actual_load_at' | 'actual_unload_at'
            var plannedDate = (btn.dataset.plannedDate || '').slice(0, 10); // YYYY-MM-DD
            var inp         = document.querySelector('.actual-time-input[data-field="' + timeField + '"]');
            var actualVal   = inp ? (inp.value || '').trim() : '';
            var actualDate  = actualVal.slice(0, 10);

            // ── Tryb RESET (admin) — cofa oznaczenie i status ──
            if (mode === 'reset') {
                var label    = target === 'pol' ? 'załadowany' : 'zrealizowany';
                var newNs    = target === 'pol' ? 2 : 3; // cofamy o jeden krok stepperu
                var newNsLbl = target === 'pol' ? 'Zaplanowane' : 'Załadowane';
                // Cofając POL musimy też zeszyć POD jeśli był (inaczej stan niespójny:
                // dostawa bez załadunku). POD jest osobno cofany własnym przyciskiem.
                var alsoClearPod = target === 'pol' && btn.dataset.podSet === '1';
                var doReset = function () {
                    var payload = {
                        id: orderId,
                        force: 1,                  // admin reverse — pomija applyAutoNlStatus
                        nordlogis_status: newNs,
                    };
                    payload[target] = 0;            // zeruj _at + _by
                    if (alsoClearPod) payload.pod = 0;
                    post(updateUrl, payload).then(function (data) {
                        if (data.success) {
                            showToast('↩ Cofnięto oznaczenie "' + label + '"', true);
                            setTimeout(function () {
                                var modalEl = document.querySelector('#orderViewModal.show');
                                if (modalEl && window.bootstrap) {
                                    var inst = bootstrap.Modal.getInstance(modalEl);
                                    if (inst) { inst.hide(); return; }
                                }
                                location.reload();
                            }, 900);
                        } else {
                            showToast(data.error || 'Błąd zapisu', false);
                        }
                    }).catch(function (e) { showToast('Błąd: ' + e.message, false); });
                };
                if (window.Swal) {
                    Swal.fire({
                        title: 'Cofnąć oznaczenie "' + label + '"?',
                        html:
                            '<p style="text-align:left;font-size:.9rem">Status zlecenia zostanie cofnięty na '
                            + '<strong>' + newNsLbl + '</strong>, a data oznaczenia wyzerowana.</p>'
                            + (alsoClearPod
                                ? '<p style="text-align:left;font-size:.85rem;color:#b45309"><i class="ri-alert-line me-1"></i>'
                                  + 'Oznaczenie <strong>"zrealizowany"</strong> również zostanie cofnięte '
                                  + '(nie można mieć dostawy bez załadunku).</p>'
                                : '')
                            + '<p style="text-align:left;font-size:.85rem;color:#9ca3af">Akcja jest logowana — '
                            + 'pojawi się w historii zmian z Twoim username.</p>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Tak, cofnij',
                        cancelButtonText:  'Anuluj',
                        confirmButtonColor: '#f59e0b',
                    }).then(function (res) { if (res && res.isConfirmed) doReset(); });
                } else {
                    if (window.confirm('Cofnąć oznaczenie "' + label + '"? Status wróci do "' + newNsLbl + '".')) {
                        doReset();
                    }
                }
                return;
            }

            // Stała część payloadu — zawsze wysyłamy faktyczną datetime żeby zapisać ją
            // razem z oznaczeniem (UX: button = "zatwierdź czas i oznacz").
            var basePayload = { id: orderId };
            if (actualVal) basePayload[timeField] = actualVal;

            // Daty się zgadzają (lub user nie wpisał faktycznej daty == nieinformacyjne,
            // ale planowane > 0 → pytamy o powód jak przy mismatchu).
            if (actualDate && plannedDate && actualDate === plannedDate) {
                doConfirmMark(target, basePayload);
                return;
            }
            // Brak faktycznej daty albo mismatch — wymuszamy uzasadnienie.
            if (!window.Swal) {
                var note = window.prompt('Daty się nie zgadzają. Wpisz powód (obowiązkowe):');
                if (!note || !note.trim()) return;
                basePayload.note = note.trim();
                doConfirmMark(target, basePayload);
                return;
            }
            var optMap = reasonOptions[target] || {};
            var optsHtml = '<option value="">— Wybierz powód —</option>';
            Object.keys(optMap).forEach(function (k) {
                optsHtml += '<option value="' + k + '">' + optMap[k] + '</option>';
            });
            var planTxt   = plannedDate || '—';
            var actualTxt = actualDate || '<em class="text-muted">nie podano</em>';
            Swal.fire({
                title: 'Daty się nie zgadzają',
                html:
                    '<div style="text-align:left;font-size:.9rem">'
                    + '<p class="mb-2">Planowana data: <strong>' + planTxt + '</strong><br>'
                    + 'Rzeczywista data: <strong>' + actualTxt + '</strong></p>'
                    + '<label class="form-label small mb-1">Powód</label>'
                    + '<select id="swal-reason" class="form-select form-select-sm mb-2">' + optsHtml + '</select>'
                    + '<label class="form-label small mb-1">Notatka <span class="text-muted">(opcjonalna gdy wybrany powód)</span></label>'
                    + '<textarea id="swal-note" class="form-control form-control-sm" rows="3" placeholder="Dodatkowy opis…"></textarea>'
                    + '</div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Zatwierdź i zapisz',
                cancelButtonText:  'Anuluj',
                confirmButtonColor: target === 'pol' ? '#6366f1' : '#22c55e',
                focusConfirm: false,
                preConfirm: function () {
                    var reason = (document.getElementById('swal-reason') || {}).value || '';
                    var note   = ((document.getElementById('swal-note') || {}).value || '').trim();
                    if (!reason && !note) {
                        Swal.showValidationMessage('Wybierz powód lub wpisz notatkę');
                        return false;
                    }
                    return { reason: reason, note: note };
                }
            }).then(function (res) {
                if (!res || !res.value) return;
                if (res.value.reason) basePayload.reason = res.value.reason;
                if (res.value.note)   basePayload.note   = res.value.note;
                doConfirmMark(target, basePayload);
            });
        });
    });

    // ── Dokumenty tylko elektronicznie ──
    var docsElChk = document.getElementById('chk-docs-electronic');
    if (docsElChk) {
        docsElChk.addEventListener('change', function() {
            var self = this;
            var val  = this.checked;
            post(updateUrl, { id: orderId, docs_electronic_only: val ? 1 : 0 }).then(function(data) {
                if (data.success) {
                    showToast(val ? '✔ Dokumenty tylko elektronicznie' : '✖ Wysyłka papierowa odblokowana', true);
                    var label = self.closest('label');
                    if (label) {
                        label.classList.toggle('checked', val);
                        label.classList.toggle('unchecked', !val);
                        var subSpan = label.querySelector('.opacity-50');
                        if (subSpan) subSpan.textContent = val ? 'Brak wysyłki papierowej' : 'niezaznaczone';
                    }
                } else {
                    self.checked = !val;
                    showToast(data.error || 'Błąd', false);
                }
            }).catch(function(e){ self.checked = !val; showToast('Błąd: ' + e.message, false); });
        });
    }

    // ── FK: wyszukiwarka ──
    var fkStatusLabels = { received:'Otrzymana', verified:'Zweryfikowana', paid:'Zapłacona' };

    document.getElementById('btn-fk-search')?.addEventListener('click', function() {
        var q     = document.getElementById('fk-search-q').value;
        var month = document.getElementById('fk-search-month').value;
        document.getElementById('fk-search-results').innerHTML = '<div class="text-center py-3 text-muted small"><i class="ri-loader-4-line"></i> Szukam…</div>';

        fetch(searchFkUrl + '?' + new URLSearchParams({q:q, month:month}))
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.success || !data.results.length) {
                document.getElementById('fk-search-results').innerHTML =
                    '<div class="text-muted small text-center py-3">Brak wyników. <a href="/koszty/import-ksef" target="_blank">Importuj z KSeF</a></div>';
                return;
            }
            var rows = data.results.map(function(ci) {
                var num = ci.invoice_number || ci.ksef_number || '(brak numeru)';
                var st  = fkStatusLabels[ci.status] || ci.status;
                var brutto = parseFloat(ci.brutto||0).toLocaleString('pl-PL',{minimumFractionDigits:2});
                return '<tr>'
                    + '<td class="ps-2 fw-semibold" style="font-size:.82rem">' + num + '</td>'
                    + '<td style="font-size:.82rem">' + (ci.contractor_name||'—') + '</td>'
                    + '<td><span class="badge bg-dark-subtle text-dark" style="font-size:.68rem">' + (ci.accounting_month||'—') + '</span></td>'
                    + '<td style="font-size:.82rem">' + (ci.issue_date||'—') + '</td>'
                    + '<td class="text-end fw-semibold" style="font-size:.82rem">' + brutto + ' ' + ci.currency + '</td>'
                    + '<td><button class="btn btn-sm btn-primary py-0 btn-assign-fk" data-ci=\'' + JSON.stringify(ci) + '\'>Przypisz</button></td>'
                    + '</tr>';
            }).join('');
            document.getElementById('fk-search-results').innerHTML =
                '<table class="table table-sm table-hover mb-0">'
                + '<thead class="table-light"><tr><th class="ps-2">Numer</th><th>Przewoźnik</th><th>Miesiąc</th><th>Data</th><th class="text-end">Brutto</th><th></th></tr></thead>'
                + '<tbody>' + rows + '</tbody></table>';

            document.querySelectorAll('.btn-assign-fk').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var ci = JSON.parse(this.dataset.ci);
                    post(assignFkUrl, { cost_invoice_id: ci.id, speed_order_id: orderId })
                    .then(function(data) {
                        if (data.success) {
                            showToast('Przypisano: ' + (ci.invoice_number||ci.ksef_number), true);
                            bootstrap.Modal.getInstance(document.getElementById('modalFkSearch'))?.hide();
                            setTimeout(function(){ location.reload(); }, 600);
                        } else { showToast(data.error||'Błąd', false); }
                    }).catch(function(e){ showToast('Błąd: '+e.message,false); });
                });
            });
        })
        .catch(function(e){
            document.getElementById('fk-search-results').innerHTML =
                '<div class="alert alert-danger py-2">Błąd: ' + e.message + '</div>';
        });
    });

    // ── FK: odpinanie ──
    document.querySelectorAll('.btn-fk-unassign').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var ciId = parseInt(this.dataset.ciId);
            var num  = this.dataset.num;
            if (!confirm('Odpiąć fakturę ' + num + '?')) return;
            post(unassignFkUrl, { cost_invoice_id: ciId, speed_order_id: orderId })
            .then(function(data) {
                if (data.success) {
                    var tr = document.querySelector('tr[data-ci-id="'+ciId+'"]');
                    if (tr) tr.remove();
                    showToast('Odpięto FK', true);
                    setTimeout(function(){ location.reload(); }, 600);
                } else { showToast(data.error||'Błąd', false); }
            }).catch(function(e){ showToast('Błąd: '+e.message,false); });
        });
    });

    // Trigger wyszukiwania po Enter w polu
    document.getElementById('fk-search-q')?.addEventListener('keydown', function(e){
        if (e.key === 'Enter') document.getElementById('btn-fk-search').click();
    });

    // Auto-scroll do sekcji ?focus=attachments (po "Zapisz + dodaj CMR" z formularza)
    (function(){
        var qs = new URLSearchParams(window.location.search);
        if (qs.get('focus') === 'attachments') {
            var el = document.getElementById('cmr-card');
            if (el) {
                setTimeout(function(){
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    el.style.boxShadow = '0 0 0 3px rgba(13,110,253,.35)';
                    el.style.transition = 'box-shadow 1.2s ease';
                    setTimeout(function(){ el.style.boxShadow = ''; }, 2500);
                }, 300);
            }
        }
    })();
})();
</script>
<?php if (!$isModal): ?>
<?php $this->end(); ?>
<?php endif; ?>

<?php
/**
 * @var \App\View\AppView                              $this
 * @var \Cake\ORM\ResultSet|\App\Model\Entity\SpeedOrder[] $orders
 * @var \App\Model\Entity\ClientProfile                $clientProfile
 * @var array<int, array{id:int,mime:string,name:string}> $cmrMap
 * @var array<string, \App\Model\Entity\Invoice>       $invoiceMap
 * @var int                                            $total
 * @var int                                            $page
 * @var int                                            $pages
 * @var int                                            $limit
 * @var string                                         $q
 * @var string                                         $status
 * @var string                                         $invState
 * @var string                                         $cmrState
 * @var string                                         $currency
 * @var string                                         $dateFrom
 * @var string                                         $dateTo
 * @var string                                         $sort
 * @var array{count:int}                               $stats
 * @var string[]                                       $currencyOptions
 * @var string                                         $currentLocale
 */
$this->assign('title', __('Zlecenia transportowe'));

// ── Helpery formatowania ──────────────────────────────────────────────────
$fmtDate = function (?string $iso): string {
    if (!$iso) return '';
    try { return (new \DateTimeImmutable($iso))->format('d.m.Y'); }
    catch (\Throwable) { return ''; }
};
$raw = function ($order): array {
    if (empty($order->raw_json)) return [];
    return (array)(json_decode((string)$order->raw_json, true) ?? []);
};
// Flaga emoji z 2-literowego ISO 3166-1 alpha-2 (PL → 🇵🇱, DE → 🇩🇪 itd.)
$flag = function (?string $code): string {
    $code = strtoupper(trim((string)$code));
    if (!preg_match('/^[A-Z]{2}$/', $code)) return '';
    $a = 0x1F1E6; // Regional Indicator Symbol "A"
    return mb_chr($a + ord($code[0]) - ord('A'), 'UTF-8')
         . mb_chr($a + ord($code[1]) - ord('A'), 'UTF-8');
};

// ── Status Nordlogis (operacyjny) ─────────────────────────────────────────
$nlStatusMap = [
    1 => ['label' => __('Przyjęte'),     'cls' => 'bg-warning-subtle text-warning',  'icon' => 'ri-inbox-line'],
    2 => ['label' => __('Zaplanowane'),  'cls' => 'bg-info-subtle text-info',         'icon' => 'ri-calendar-check-line'],
    3 => ['label' => __('Załadowane'),   'cls' => 'bg-primary-subtle text-primary',   'icon' => 'ri-truck-line'],
    4 => ['label' => __('Zrealizowane'), 'cls' => 'bg-success-subtle text-success',   'icon' => 'ri-checkbox-circle-line'],
    5 => ['label' => __('Zafakturowane'),'cls' => 'bg-secondary-subtle text-secondary','icon' => 'ri-file-text-line'],
];
$nlStatusBadge = function (int $s) use ($nlStatusMap): string {
    $m = $nlStatusMap[$s] ?? ['label' => (string)$s, 'cls' => 'bg-light text-dark border', 'icon' => 'ri-question-line'];
    return '<span class="badge ' . $m['cls'] . '"><i class="' . $m['icon'] . ' me-1"></i>'
         . htmlspecialchars($m['label']) . '</span>';
};
$checkBadge = function (?string $dt, string $label): string {
    if ($dt) {
        return '<span class="badge bg-success-subtle text-success me-1" title="' . htmlspecialchars($label) . ': '
             . substr($dt, 0, 10) . '">✔ ' . $label . '</span>';
    }
    return '<span class="badge bg-light text-muted border me-1" title="' . __('Brak') . ' ' . htmlspecialchars($label) . '">· ' . $label . '</span>';
};

// Ile filtrów aktywnych (poza q)
$activeFilters = (int)($status !== '')
              + (int)($invState !== '')
              + (int)($cmrState !== '')
              + (int)($currency !== '')
              + (int)($dateFrom !== '')
              + (int)($dateTo !== '');

$kept = array_filter([
    'q'         => $q,
    'status'    => $status,
    'inv'       => $invState,
    'cmr'       => $cmrState,
    'currency'  => $currency,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'sort'      => $sort,
], fn($v) => $v !== '' && $v !== null);
$mergeUrl = fn(array $extra) => array_filter(array_merge($kept, $extra), fn($v) => $v !== '' && $v !== null);

$today = date('Y-m-d');
?>

<!-- Nagłówek -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="ri-truck-line me-2"></i><?= __('Zlecenia transportowe') ?></h4>
        <div class="text-muted small mt-1">
            <i class="ri-building-line me-1"></i>
            <?= h($clientProfile->company_name ?: $clientProfile->nip) ?>
            <span class="ms-2 badge bg-light text-secondary border">NIP <?= h($clientProfile->nip) ?></span>
        </div>
    </div>
    <!-- Switcher języka przeniesiony do nagłówka (dropdown z flagami) -->
</div>

<?= $this->Flash->render() ?>

<!-- Pasek statusu (tabs) -->
<ul class="nav nav-pills nav-fill mb-3 small" style="background:#f1f5f9;border-radius:.5rem;padding:.25rem">
    <li class="nav-item">
        <a class="nav-link <?= $status === ''        ? 'active' : '' ?> py-1"
           href="<?= $this->Url->build(['action' => 'index', '?' => $mergeUrl(['status' => null, 'page' => null])]) ?>">
            <i class="ri-list-check me-1"></i><?= __('Wszystkie') ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $status === 'active'  ? 'active' : '' ?> py-1"
           href="<?= $this->Url->build(['action' => 'index', '?' => $mergeUrl(['status' => 'active', 'page' => null])]) ?>">
            <i class="ri-time-line me-1"></i><?= __('Aktywne') ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $status === 'closed'  ? 'active' : '' ?> py-1"
           href="<?= $this->Url->build(['action' => 'index', '?' => $mergeUrl(['status' => 'closed', 'page' => null])]) ?>">
            <i class="ri-checkbox-circle-line me-1"></i><?= __('Zamknięte') ?>
        </a>
    </li>
</ul>

<!-- Filtry -->
<form method="get" action="<?= $this->Url->build(['action' => 'index']) ?>"
      class="card shadow-sm mb-3" id="filterForm">
    <input type="hidden" name="status" value="<?= h($status) ?>">
    <div class="card-body py-2 px-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                    <input type="text" name="q" value="<?= h($q) ?>"
                           class="form-control" placeholder="<?= __('Szukaj: numer, tytuł, trasa…') ?>">
                </div>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" value="<?= h($dateFrom) ?>"
                       class="form-control form-control-sm" title="<?= __('Data od') ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" value="<?= h($dateTo) ?>"
                       class="form-control form-control-sm" title="<?= __('Data do') ?>">
            </div>
            <div class="col-md-1">
                <select name="currency" class="form-select form-select-sm">
                    <option value=""><?= __('Waluta') ?></option>
                    <?php foreach ($currencyOptions as $c): ?>
                        <option value="<?= h($c) ?>" <?= $currency === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="inv" class="form-select form-select-sm">
                    <option value=""><?= __('Faktura: wszystkie') ?></option>
                    <option value="with"    <?= $invState === 'with'    ? 'selected' : '' ?>><?= __('Z fakturą') ?></option>
                    <option value="without" <?= $invState === 'without' ? 'selected' : '' ?>><?= __('Bez faktury') ?></option>
                    <option value="paid"    <?= $invState === 'paid'    ? 'selected' : '' ?>><?= __('Opłacone') ?></option>
                    <option value="unpaid"  <?= $invState === 'unpaid'  ? 'selected' : '' ?>><?= __('Nieopłacone') ?></option>
                </select>
            </div>
            <div class="col-md-1">
                <select name="cmr" class="form-select form-select-sm">
                    <option value=""><?= __('CMR') ?></option>
                    <option value="with"    <?= $cmrState === 'with'    ? 'selected' : '' ?>><?= __('Z CMR') ?></option>
                    <option value="without" <?= $cmrState === 'without' ? 'selected' : '' ?>><?= __('Bez CMR') ?></option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-primary btn-sm w-100" type="submit" title="<?= __('Filtruj') ?>">
                    <i class="ri-filter-3-line"></i>
                </button>
                <?php if ($q !== '' || $activeFilters > 0): ?>
                    <a href="<?= $this->Url->build(['action' => 'index']) ?>"
                       class="btn btn-outline-secondary btn-sm" title="<?= __('Wyczyść') ?>">
                        <i class="ri-close-line"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($activeFilters > 0 || $q !== ''): ?>
        <div class="d-flex flex-wrap gap-1 mt-2 pt-2 border-top">
            <span class="text-muted small me-1"><?= __('Filtry') ?>:</span>
            <?php
            $chip = function (string $label, string $value, array $clearParams) use ($mergeUrl) {
                $url = \Cake\Routing\Router::url(['action' => 'index', '?' => $mergeUrl(array_merge($clearParams, ['page' => null]))]);
                return '<a href="' . $url . '" class="badge bg-light text-secondary border d-inline-flex align-items-center gap-1" style="font-size:.7rem;text-decoration:none">'
                     . '<span>' . h($label) . ': <strong>' . h($value) . '</strong></span>'
                     . '<i class="ri-close-line"></i></a>';
            };
            if ($q !== '')        echo $chip(__('Szukaj'), $q, ['q' => null]);
            if ($invState !== '') {
                $invLabels = ['with' => __('Z fakturą'), 'without' => __('Bez faktury'), 'paid' => __('Opłacone'), 'unpaid' => __('Nieopłacone')];
                echo $chip(__('Faktura'), $invLabels[$invState] ?? $invState, ['inv' => null]);
            }
            if ($cmrState !== '') {
                $cmrLabels = ['with' => __('Z CMR'), 'without' => __('Bez CMR')];
                echo $chip(__('CMR'), $cmrLabels[$cmrState] ?? $cmrState, ['cmr' => null]);
            }
            if ($currency !== '') echo $chip(__('Waluta'), $currency, ['currency' => null]);
            if ($dateFrom !== '') echo $chip(__('Od'),    $dateFrom, ['date_from' => null]);
            if ($dateTo !== '')   echo $chip(__('Do'),    $dateTo,   ['date_to' => null]);
            ?>
        </div>
        <?php endif; ?>
    </div>
</form>

<!-- Statystyki + sortowanie -->
<div class="d-flex flex-wrap gap-3 mb-3 align-items-center">
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
            <i class="ri-truck-line me-1"></i><?= number_format($total, 0, ',', ' ') ?>
        </span>
        <span class="text-muted small"><?= __('zleceń') ?></span>
    </div>
    <div class="ms-auto d-flex align-items-center gap-1">
        <span class="text-muted small"><?= __('Sortuj') ?>:</span>
        <select class="form-select form-select-sm" style="width:auto" onchange="window.location.href=this.value">
            <?php
            $sortOptions = [
                'date_desc'     => __('Data dok. ↓ (najnowsze)'),
                'date_asc'      => __('Data dok. ↑ (najstarsze)'),
                'delivery_desc' => __('Data rozładunku ↓'),
                'delivery_asc'  => __('Data rozładunku ↑'),
            ];
            foreach ($sortOptions as $val => $label):
                $url = $this->Url->build(['action' => 'index', '?' => $mergeUrl(['sort' => $val, 'page' => null])]);
            ?>
                <option value="<?= h($url) ?>" <?= $sort === $val ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Tabela -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle client-orders-table" style="font-size:.875rem">
            <thead class="table-light border-bottom-2">
                <tr>
                    <th class="ps-3" style="width:160px"><?= __('Zlecenie') ?></th>
                    <th style="width:200px"><?= __('Załadunek') ?></th>
                    <th style="width:200px"><?= __('Rozładunek') ?></th>
                    <th style="width:160px"><?= __('Status') ?></th>
                    <th><?= __('Faktura') ?></th>
                    <th class="pe-3 text-end" style="width:140px"><?= __('Akcje') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders) || count($orders->toArray()) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="ri-inbox-2-line" style="font-size:2.5em"></i><br>
                            <div class="mt-2"><?= __('Brak zleceń pasujących do filtrów.') ?></div>
                            <?php if ($activeFilters > 0 || $q !== ''): ?>
                                <a href="<?= $this->Url->build(['action' => 'index']) ?>"
                                   class="btn btn-sm btn-outline-secondary mt-2">
                                    <i class="ri-refresh-line me-1"></i><?= __('Wyczyść filtry') ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <?php
                        $r = $raw($order);
                        $cmrs    = $cmrMap[$order->id] ?? [];
                        $invoice = $order->invoice_id ? ($invoiceMap[(string)$order->invoice_id] ?? null) : null;

                        $dateDoc = $order->date_doc instanceof \DateTimeInterface
                            ? $order->date_doc->format('d.m.Y')
                            : (substr((string)$order->date_doc, 0, 10) ?: '');

                        // Załadunek — kolumny po sync lub fallback raw_json
                        $loadCountry = (string)($order->load_country     ?? $r['GLO_MIE_KRAJ']   ?? '');
                        $loadCode    = (string)($order->load_postal_code ?? $r['GLO_MIE_KOD']    ?? '');
                        $loadCity    = (string)($order->load_city        ?? $r['GLO_MIE_POCZTA'] ?? '');
                        $loadDate    = $fmtDate(!empty($order->date_deadline)
                            ? ($order->date_deadline instanceof \DateTimeInterface ? $order->date_deadline->format('Y-m-d') : (string)$order->date_deadline)
                            : trim((string)($r['GLO_DATA_TER'] ?? '')));

                        // Rozładunek
                        $unloadName    = (string)($order->unload_name    ?? $r['GLO_MIE_NAZWA1'] ?? $order->place_to_name ?? '');
                        $unloadCity    = (string)($order->unload_city    ?? $r['GLO_MIE_MIEJSC'] ?? '');
                        $unloadCountry = (string)($order->unload_country ?? $order->place_to_country ?? '');
                        $unloadDate    = $fmtDate(!empty($order->date_delivery)
                            ? ($order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery->format('Y-m-d') : (string)$order->date_delivery)
                            : trim((string)($r['GLO_DATA_ZAK'] ?? '')));

                        // Status
                        $nlStatus    = (int)($order->nordlogis_status ?? 1);
                        $effectiveNl = !empty($order->invoice_id) ? 5 : $nlStatus;
                        $polAt = $order->pol_at instanceof \DateTimeInterface ? $order->pol_at->format('Y-m-d H:i') : (string)($order->pol_at ?? '');
                        $podAt = $order->pod_at instanceof \DateTimeInterface ? $order->pod_at->format('Y-m-d H:i') : (string)($order->pod_at ?? '');

                        // Przeterminowane
                        $delivStr = $order->date_delivery instanceof \DateTimeInterface
                            ? $order->date_delivery->format('Y-m-d')
                            : substr((string)($order->date_delivery ?? ''), 0, 10);
                        if ($delivStr === '') { $delivStr = substr(trim((string)($r['GLO_DATA_ZAK'] ?? '')), 0, 10); }
                        $isOverdue = ($delivStr !== '' && $delivStr < $today && empty($order->pod_at) && empty($order->invoice_id));

                        $tytParts = array_filter([trim((string)($order->title1 ?? '')), trim((string)($order->title2 ?? ''))]);
                    ?>
                    <tr class="<?= $isOverdue ? 'row-overdue' : '' ?>">
                        <!-- Zlecenie: symbol + data + tytuł -->
                        <td class="ps-3 align-top pt-3 text-nowrap">
                            <a href="<?= $this->Url->build(['action' => 'view', $order->id]) ?>"
                               class="fw-semibold text-decoration-none d-block"><?= h($order->symbol ?: '#' . $order->id) ?></a>
                            <?php if ($dateDoc !== ''): ?>
                                <span class="text-muted" style="font-size:.72rem"><?= h($dateDoc) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($tytParts)): ?>
                                <div class="text-primary mt-1" style="font-size:.72rem"
                                     title="<?= __('Numer zlecenia') ?>">
                                    <i class="ri-file-list-3-line me-1 opacity-50"></i><?= h(implode(' / ', $tytParts)) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ((int)$order->status === 0): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle mt-1" style="font-size:.65em">
                                    <i class="ri-checkbox-circle-line me-1"></i><?= __('Zamknięte') ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Załadunek -->
                        <td class="align-top pt-3">
                            <?php if ($loadCountry || $loadCode || $loadCity || $loadDate): ?>
                                <div class="place-block">
                                    <div class="place-label"><?= __('Zał.') ?></div>
                                    <?php if ($loadDate): ?><div class="place-date"><?= h($loadDate) ?></div><?php endif; ?>
                                    <?php if ($loadCountry): ?>
                                        <div class="place-country">
                                            <span class="badge bg-light text-secondary border d-inline-flex align-items-center gap-1" style="font-size:.65em">
                                                <span style="font-size:1.1em;line-height:1"><?= $flag($loadCountry) ?></span>
                                                <?= h($loadCountry) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php $loadPlace = trim(implode(' ', array_filter([$loadCode, $loadCity])));
                                          if ($loadPlace !== ''): ?>
                                        <div class="place-city"><?= h($loadPlace) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Rozładunek -->
                        <td class="align-top pt-3">
                            <?php if ($unloadName || $unloadCity || $unloadDate || $unloadCountry): ?>
                                <div class="place-block">
                                    <div class="place-label"><?= __('Rozł.') ?></div>
                                    <?php if ($unloadDate): ?><div class="place-date"><?= h($unloadDate) ?></div><?php endif; ?>
                                    <?php if ($unloadCountry): ?>
                                        <div class="place-country">
                                            <span class="badge bg-light text-secondary border d-inline-flex align-items-center gap-1" style="font-size:.65em">
                                                <span style="font-size:1.1em;line-height:1"><?= $flag($unloadCountry) ?></span>
                                                <?= h($unloadCountry) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php $unloadPlace = trim(implode(', ', array_filter([$unloadName, $unloadCity])));
                                          if ($unloadPlace !== ''): ?>
                                        <div class="place-city"><?= h($unloadPlace) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status: Nordlogis + POL/POD -->
                        <td class="align-top pt-3">
                            <?= $nlStatusBadge($effectiveNl) ?>
                            <div class="mt-1">
                                <?= $checkBadge($polAt, 'POL') ?>
                                <?= $checkBadge($podAt, 'POD') ?>
                            </div>
                            <?php if ($isOverdue): ?>
                                <div class="text-danger mt-1" style="font-size:.7em">
                                    <i class="ri-error-warning-line me-1"></i><?= __('Przeterminowane') ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Faktura -->
                        <td class="align-top pt-3">
                            <?php if ($invoice): ?>
                                <?php
                                    $stCls = $invoice->paymentstate === 'paid'
                                        ? 'text-success' : ($invoice->paymentstate === 'partial' ? 'text-warning' : 'text-danger');
                                    $stIco = $invoice->paymentstate === 'paid'
                                        ? 'ri-checkbox-circle-fill' : ($invoice->paymentstate === 'partial' ? 'ri-time-line' : 'ri-error-warning-line');
                                    $stLbl = $invoice->paymentstate === 'paid'
                                        ? __('Opłacona') : ($invoice->paymentstate === 'partial' ? __('Częściowo opłacona') : __('Nieopłacona'));
                                ?>
                                <div class="fw-semibold" style="font-size:.78rem"><?= h($invoice->fullnumber ?: '—') ?></div>
                                <div class="<?= $stCls ?> mt-1" style="font-size:.7rem">
                                    <i class="<?= $stIco ?> me-1"></i><?= $stLbl ?>
                                </div>
                                <?php if ($invoice->date): ?>
                                    <div class="text-muted" style="font-size:.7rem">
                                        <i class="ri-calendar-line me-1 opacity-50"></i>
                                        <?= $invoice->date instanceof \DateTimeInterface ? $invoice->date->format('d.m.Y') : substr((string)$invoice->date, 0, 10) ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small fst-italic">— <?= __('brak') ?> —</span>
                            <?php endif; ?>
                        </td>

                        <!-- Akcje -->
                        <td class="pe-3 text-end align-top pt-2 text-nowrap">
                            <a href="<?= $this->Url->build(['action' => 'view', $order->id]) ?>"
                               class="btn btn-sm btn-outline-primary" title="<?= __('Szczegóły zlecenia') ?>">
                                <i class="ri-eye-line"></i>
                            </a>
                            <?php if (!empty($cmrs)): ?>
                                <a href="<?= $this->Url->build(['action' => 'view', $order->id]) ?>#attachments"
                                   class="btn btn-sm btn-outline-success position-relative ms-1"
                                   title="<?= sprintf(__('CMR — %d plików'), count($cmrs)) ?>">
                                    <i class="ri-attachment-2"></i>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success" style="font-size:.55em"><?= count($cmrs) ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($invoice): ?>
                                <a href="<?= $this->Url->build(['action' => 'downloadInvoice', $order->invoice_id]) ?>"
                                   class="btn btn-sm btn-outline-secondary ms-1"
                                   title="<?= sprintf(__('Pobierz fakturę %s'), h($invoice->fullnumber ?: '')) ?>">
                                    <i class="ri-file-pdf-line"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center py-2">
        <small class="text-muted">
            <?= ($page - 1) * $limit + 1 ?>–<?= min($page * $limit, $total) ?> <?= __('z') ?> <?= number_format($total, 0, ',', ' ') ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <?= $this->Html->link('‹',
                            ['action' => 'index', '?' => $mergeUrl(['page' => $page - 1])],
                            ['class' => 'page-link']) ?>
                    </li>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <?= $this->Html->link((string)$p,
                            ['action' => 'index', '?' => $mergeUrl(['page' => $p])],
                            ['class' => 'page-link']) ?>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <?= $this->Html->link('›',
                            ['action' => 'index', '?' => $mergeUrl(['page' => $page + 1])],
                            ['class' => 'page-link']) ?>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<style>
/* Bloki załadunku/rozładunku — styl z SpeedOrders */
.client-orders-table .place-block { line-height: 1.3; }
.client-orders-table .place-block .place-label   { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; opacity: .55; margin-bottom: .15rem; }
.client-orders-table .place-block .place-date    { font-size: .78rem; color: #475569; font-weight: 600; }
.client-orders-table .place-block .place-country { font-size: .7rem; opacity: .85; margin-top: .15rem; }
.client-orders-table .place-block .place-city    { font-size: .8rem; font-weight: 500; color: #1e293b; margin-top: .1rem; }

/* Wiersze przeterminowane */
.client-orders-table tr.row-overdue td { background: #fef2f2; }
.client-orders-table tr.row-overdue:hover td { background: #fee2e2; }
</style>

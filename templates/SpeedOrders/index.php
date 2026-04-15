<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $orders
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var int $limit
 * @var string $search
 * @var string $status
 * @var string $currency
 * @var string $amountMin
 * @var string $amountMax
 * @var string $deliveryFrom
 * @var string $deliveryTo
 * @var array $cmrMap   speed_order_id => [{id, path, mime, name, label}]
 */

$this->assign('title', 'Zlecenia Speed');

// Status Speed ERP (importowany)
$speedStatusMap = [
    1 => ['label' => 'Przyjęte',     'cls' => 'bg-secondary-subtle text-secondary'],
    2 => ['label' => 'Zrealizowane', 'cls' => 'bg-success-subtle text-success'],
    3 => ['label' => 'Zafakturowane','cls' => 'bg-primary-subtle text-primary'],
    4 => ['label' => 'Archiwum',     'cls' => 'bg-light text-muted border'],
    5 => ['label' => 'Anulowane',    'cls' => 'bg-danger-subtle text-danger'],
];

// Status operacyjny Nordlogis
$nlStatusMap = [
    1 => ['label' => 'Przyjęte',     'cls' => 'bg-warning-subtle text-warning',  'icon' => 'ri-inbox-line'],
    2 => ['label' => 'Zaplanowane',  'cls' => 'bg-info-subtle text-info',         'icon' => 'ri-calendar-check-line'],
    3 => ['label' => 'Załadowane',   'cls' => 'bg-purple-subtle text-purple',     'icon' => 'ri-truck-line'],
    4 => ['label' => 'Zrealizowane', 'cls' => 'bg-success-subtle text-success',   'icon' => 'ri-checkbox-circle-line'],
    5 => ['label' => 'Zafakturowane','cls' => 'bg-orange-subtle text-orange',     'icon' => 'ri-file-text-line'],
];

$nlStatusBadge = function(int $s, ?string $speedLabel = null) use ($nlStatusMap): string {
    $m = $nlStatusMap[$s] ?? ['label' => (string)$s, 'cls' => 'bg-light text-dark border', 'icon' => 'ri-question-line'];
    $title = $speedLabel ? ' title="Speed: ' . htmlspecialchars($speedLabel) . '"' : '';
    return '<span class="badge ' . $m['cls'] . '"' . $title . '>'
        . '<i class="' . $m['icon'] . ' me-1"></i>'
        . htmlspecialchars($m['label'])
        . '</span>';
};

// Checkboxy techniczne — ikony
$checkIcon = function(?string $dt, string $label): string {
    if ($dt) {
        return '<span class="badge bg-success-subtle text-success me-1" title="' . htmlspecialchars($label) . ': ' . substr($dt, 0, 10) . '">✔ ' . $label . '</span>';
    }
    return '<span class="badge bg-light text-muted border me-1" title="Brak ' . htmlspecialchars($label) . '">· ' . $label . '</span>';
};

// Helper: parsuj raw_json → tablica
$raw = function($order): array {
    if (empty($order->raw_json)) return [];
    return (array)(json_decode((string)$order->raw_json, true) ?? []);
};

// Helper: formatuj datę ISO → d.m.Y lub tylko d.m (bez roku jeśli bieżący)
$fmtDate = function(?string $iso): string {
    if (!$iso) return '';
    try {
        $d = new \DateTimeImmutable($iso);
        return $d->format('d.m.Y');
    } catch (\Throwable) { return ''; }
};

// Grupowanie zleceń: kontrahent (NIP lub nazwa) → data rozładunku GLO_DATA_ZAK (date_delivery)
$groups = [];
foreach ($orders as $order) {
    $contractorKey = !empty($order->buyer_nip)
        ? $order->buyer_nip
        : ($order->buyer_name ?? 'brak');

    // Data rozładunku — priorytet: raw_json GLO_DATA_ZAK, fallback: date_delivery z encji
    $deliveryDate = '';
    $rawOrder = [];
    if (!empty($order->raw_json)) {
        $rawOrder = (array)(json_decode((string)$order->raw_json, true) ?? []);
    }
    $gloDataZak = trim((string)($rawOrder['GLO_DATA_ZAK'] ?? ''));
    if ($gloDataZak !== '') {
        try {
            $deliveryDate = (new \DateTimeImmutable($gloDataZak))->format('Y-m-d');
        } catch (\Throwable) {}
    }
    if ($deliveryDate === '' && !empty($order->date_delivery)) {
        $deliveryDate = $order->date_delivery instanceof \DateTimeInterface
            ? $order->date_delivery->format('Y-m-d')
            : substr((string)$order->date_delivery, 0, 10);
    }

    $groupKey = $contractorKey . '||' . $deliveryDate;
    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = [
            'contractor_name' => $order->buyer_name ?? '',
            'contractor_nip'  => $order->buyer_nip ?? '',
            'delivery_date'   => $deliveryDate,
            'currency'        => strtoupper(trim((string)($order->currency ?? 'PLN'))),
            'orders'          => [],
        ];
    }
    $groups[$groupKey]['orders'][] = $order;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">Zlecenia <span class="text-muted fs-6 fw-normal">Speed ERP</span></h4>
    <div class="d-flex gap-2">
        <a href="<?= $this->Url->build(['action' => 'dashboard']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-dashboard-line me-1"></i> Control Tower
        </a>
        <a href="<?= $this->Url->build(['action' => 'exportCsv', '?' => ['q' => $search, 'status' => $status, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'delivery_from' => $deliveryFrom, 'delivery_to' => $deliveryTo]]) ?>"
           class="btn btn-sm btn-outline-success" title="Eksportuj widoczne zlecenia do CSV">
            <i class="ri-download-2-line me-1"></i> CSV
        </a>
        <button class="btn btn-primary btn-sm" id="btn-sync-orders">
            <i class="ri-refresh-line me-1"></i> Synchronizuj ze Speed
        </button>
    </div>
</div>

<!-- Belka zaznaczonych — przyklejona do dołu strony -->
<div id="selection-bar" class="d-none">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <i class="ri-checkbox-multiple-line fs-5 text-primary"></i>
        <div>
            <span class="fw-semibold text-primary" id="sel-count">0</span>
            <span class="text-muted"> zlece&#324;</span>
            <span class="ms-2 text-muted small" id="sel-groups-info"></span>
        </div>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="btn-clear-selection">
                <i class="ri-close-line me-1"></i>Odznacz wszystko
            </button>
            <button class="btn btn-sm btn-primary fw-semibold" id="btn-invoke-invoices">
                <i class="ri-file-add-line me-1"></i>Wystaw faktury
            </button>
        </div>
    </div>
</div>
<style>
#selection-bar {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    z-index: 1000;   /* poniżej modalu Bootstrap (1055) */
    background: #fff;
    color: #212529;
    padding: .6rem 1.5rem;
    box-shadow: 0 -2px 12px rgba(0,0,0,.12);
    border-top: 2px solid #dee2e6;
    animation: slideUp .2s ease;
}
#selection-bar.d-none { display: none !important; }
@keyframes slideUp {
    from { transform: translateY(100%); opacity: 0; }
    to   { transform: translateY(0);   opacity: 1; }
}
body { padding-bottom: 68px; }
</style>

<!-- Sync modal -->
<div class="modal fade" id="syncModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Synchronizacja zleceń</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="sync-status" class="mb-2 text-muted small"></div>
        <div class="progress">
          <div id="sync-bar" class="progress-bar progress-bar-striped progress-bar-animated"
               role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div id="sync-result" class="mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal potwierdzenia faktur -->
<div class="modal fade" id="invoiceConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="invoiceConfirmModalTitle"><i class="ri-file-add-line me-2"></i>Wystaw faktury ze zleceń</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="invoice-confirm-phase-preview">
          <p class="text-muted mb-3 small" id="invoice-grouping-hint">
              Zlecenia są grupowane według <strong>zleceniodawcy i daty rozładunku</strong> —
              tylko takie mogą trafić na jedną fakturę. Każda grupa = jedna faktura, każde zlecenie = osobna pozycja.
          </p>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="chk-merge-contractor">
            <label class="form-check-label small" for="chk-merge-contractor">
              Połącz zlecenia tego samego kontrahenta <span class="text-muted">(ignoruj datę rozładunku)</span>
            </label>
          </div>
          <div id="invoice-groups-preview"></div>
        </div>
        <div id="invoice-confirm-phase-links" class="d-none">
          <p class="text-muted small mb-3">Kliknij każdy link żeby otworzyć formularz faktury, lub użyj przycisku poniżej.</p>
          <div id="invoice-links-list"></div>
        </div>
      </div>
      <div class="modal-footer" id="invoice-confirm-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
        <button type="button" class="btn btn-success" id="btn-confirm-invoices">
            <i class="ri-file-add-line me-1"></i> Utwórz faktury
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$hasFilters = $search !== '' || $status !== '' || $currency !== '' || $amountMin !== '' || $amountMax !== '' || $deliveryFrom !== '' || $deliveryTo !== '';
$quickFilters = [
    ['label' => 'Wszystkie',        'status' => '',           'icon' => 'ri-list-check',          'cls' => 'secondary'],
    ['label' => 'Bez POD',          'status' => 'brak_pod',   'icon' => 'ri-alarm-warning-line',  'cls' => 'warning'],
    ['label' => 'Bez FK',           'status' => 'brak_fk',    'icon' => 'ri-file-damage-line',    'cls' => 'danger'],
    ['label' => 'Niezafakturowane', 'status' => 'niezafakt',  'icon' => 'ri-file-add-line',       'cls' => 'primary'],
    ['label' => 'Przeterminowane',  'status' => 'przetermin', 'icon' => 'ri-time-line',           'cls' => 'danger'],
];
?>

<!-- Panel filtrów -->
<form method="get" id="orders-filter-form">
<input type="hidden" name="limit" value="<?= $limit ?>">
<div class="card custom-card mb-3">
  <div class="card-body p-2">

    <!-- Wiersz 1: Szukaj + Status + Szybkie filtry -->
    <div class="row g-2 align-items-center mb-2">
      <div class="col-md-4">
        <div class="input-group input-group-sm">
          <span class="input-group-text bg-light border-end-0"><i class="ri-search-line text-muted"></i></span>
          <input type="text" name="q" class="form-control form-control-sm border-start-0"
                 placeholder="Symbol, nabywca, trasa, tytuł…" value="<?= h($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
          <option value="">Wszystkie statusy</option>
          <optgroup label="Status Nordlogis">
          <?php foreach ($nlStatusMap as $val => $s): ?>
          <option value="nl_<?= $val ?>" <?= $status === 'nl_' . $val ? 'selected' : '' ?>>
            <?= h($s['label']) ?>
          </option>
          <?php endforeach; ?>
          </optgroup>
          <optgroup label="Status Speed ERP">
          <?php foreach ($speedStatusMap as $val => $s): ?>
          <option value="sp_<?= $val ?>" <?= $status === 'sp_' . $val ? 'selected' : '' ?>>
            <?= h($s['label']) ?>
          </option>
          <?php endforeach; ?>
          </optgroup>
        </select>
      </div>
      <div class="col-md-5">
        <div class="d-flex gap-1 flex-wrap">
          <?php foreach ($quickFilters as $qf):
            $isActive = ($status === $qf['status']);
            $cls = $isActive ? "btn-{$qf['cls']} text-white" : "btn-outline-{$qf['cls']}";
          ?>
          <a href="<?= $this->Url->build(['action' => 'index', '?' => ['status' => $qf['status'], 'q' => $search, 'currency' => $currency, 'amount_min' => $amountMin, 'amount_max' => $amountMax, 'delivery_from' => $deliveryFrom, 'delivery_to' => $deliveryTo, 'limit' => $limit]]) ?>"
             class="btn btn-sm <?= $cls ?>">
            <i class="<?= $qf['icon'] ?> me-1"></i><?= h($qf['label']) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Wiersz 2: Waluta + Kwota + Rozładunek + Przyciski -->
    <div class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label form-label-sm text-muted mb-1 d-block" style="font-size:.72rem;letter-spacing:.04em;text-transform:uppercase">
          <i class="ri-money-euro-circle-line me-1"></i>Waluta
        </label>
        <div class="d-flex gap-1">
          <?php foreach (['EUR', 'PLN'] as $cur): ?>
          <button type="submit" name="currency" value="<?= $currency === $cur ? '' : $cur ?>"
                  class="btn btn-sm <?= $currency === $cur ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= $cur ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-auto">
        <label class="form-label form-label-sm text-muted mb-1 d-block" style="font-size:.72rem;letter-spacing:.04em;text-transform:uppercase">
          <i class="ri-coin-line me-1"></i>Kwota netto
        </label>
        <div class="d-flex gap-1 align-items-center">
          <input type="number" name="amount_min" class="form-control form-control-sm" style="width:110px"
                 placeholder="Od" step="0.01" min="0" value="<?= h($amountMin) ?>">
          <span class="text-muted small">—</span>
          <input type="number" name="amount_max" class="form-control form-control-sm" style="width:110px"
                 placeholder="Do" step="0.01" min="0" value="<?= h($amountMax) ?>">
        </div>
      </div>
      <div class="col-auto">
        <label class="form-label form-label-sm text-muted mb-1 d-block" style="font-size:.72rem;letter-spacing:.04em;text-transform:uppercase">
          <i class="ri-truck-line me-1"></i>Rozładunek
        </label>
        <div class="d-flex gap-1 align-items-center">
          <input type="date" name="delivery_from" class="form-control form-control-sm" style="width:140px"
                 value="<?= h($deliveryFrom) ?>" title="Od">
          <span class="text-muted small">—</span>
          <input type="date" name="delivery_to" class="form-control form-control-sm" style="width:140px"
                 value="<?= h($deliveryTo) ?>" title="Do">
        </div>
      </div>
      <div class="col-auto ms-auto d-flex gap-2 align-items-end">
        <?php if ($hasFilters): ?>
        <a href="<?= $this->Url->build(['action' => 'index', '?' => ['limit' => $limit]]) ?>"
           class="btn btn-sm btn-outline-secondary" title="Wyczyść filtry">
          <i class="ri-close-line me-1"></i>Wyczyść
        </a>
        <?php endif; ?>
        <button type="submit" class="btn btn-sm btn-primary px-3">
          <i class="ri-search-line me-1"></i>Filtruj
        </button>
      </div>
    </div>

  </div>
</div>
</form>

<!-- Pasek wyników + limit -->
<div class="d-flex align-items-center gap-3 mb-2">
  <p class="text-muted small mb-0">
    Znaleziono: <strong><?= number_format($total, 0, ',', ' ') ?></strong> zleceń
    <?php if ($total > $limit): ?>(strona <?= $page ?> z <?= $pages ?>)<?php endif; ?>
    &mdash; <?= count($groups) ?> grup
    <?php if ($hasFilters): ?>
    <span class="badge bg-primary-subtle text-primary ms-1"><i class="ri-filter-3-line me-1"></i>Filtry aktywne</span>
    <?php endif; ?>
  </p>
  <div class="d-flex align-items-center gap-1 ms-auto">
    <label class="small text-muted text-nowrap">Na stronę</label>
    <select name="limit" class="form-select form-select-sm" style="width:auto"
            onchange="const u=new URL(location.href);u.searchParams.set('limit',this.value);u.searchParams.set('page','1');location.href=u.toString()">
      <?php foreach ([25, 50, 100, 200, 500] as $opt): ?>
      <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<style>
.orders-table th, .orders-table td { font-size: .8rem; vertical-align: middle; }
.orders-table .group-header td { padding: .35rem .5rem; }
.orders-table .order-row td { padding: .3rem .5rem; }
.place-block { line-height: 1.3; }
.place-block .place-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; opacity: .6; }
.place-block .place-date { font-size: .75rem; color: #6c757d; }
.place-block .place-city { font-size: .8rem; font-weight: 500; }
.place-block .place-country { font-size: .7rem; opacity: .7; }
</style>

<div class="table-responsive">
<table class="table table-hover table-sm align-middle mb-0 orders-table" id="orders-table">
    <thead class="table-light">
        <tr>
            <th style="width:28px">
                <input type="checkbox" class="form-check-input" id="chk-all"
                    title="Zaznacz wszystko (każda grupa zleceniodawca+data rozładunku = osobna faktura)">
            </th>
            <th>Symbol / Data dok.</th>
            <th>Zleceniodawca</th>
            <th>Załadunek</th>
            <th>Rozładunek</th>
            <th>Kierowca / Przewoźnik</th>
            <th class="text-end">Netto</th>
            <th>Wal.</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if (count($groups) === 0): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">
            <?= $total === 0
                ? 'Brak zleceń w bazie. Kliknij „Synchronizuj ze Speed", aby pobrać dane.'
                : 'Brak wyników dla podanych kryteriów.' ?>
        </td></tr>
    <?php else: ?>
        <?php $groupIdx = 0; foreach ($groups as $groupKey => $group): $groupIdx++;
            $groupColors = [
                'bg-primary-subtle', 'bg-success-subtle', 'bg-warning-subtle',
                'bg-info-subtle', 'bg-danger-subtle', 'bg-secondary-subtle',
            ];
            $gc = $groupColors[($groupIdx - 1) % count($groupColors)];
            $groupOrders  = $group['orders'];
            $groupCurrency = $group['currency'];
            $groupDelivery = $group['delivery_date'];
            $groupIds = array_map(fn($o) => $o->id, $groupOrders);
        ?>
        <!-- ── Nagłówek grupy ── -->
        <tr class="<?= $gc ?> group-header" data-group="<?= h($groupKey) ?>">
            <td>
                <input type="checkbox" class="form-check-input chk-group"
                    data-group="<?= h($groupKey) ?>"
                    data-contractor="<?= h($group['contractor_name']) ?>"
                    data-nip="<?= h($group['contractor_nip']) ?>"
                    data-delivery="<?= h($groupDelivery) ?>"
                    data-currency="<?= h($groupCurrency) ?>"
                    data-ids="<?= h(implode(',', $groupIds)) ?>">
            </td>
            <td colspan="7">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <strong class="text-dark"><?= h($group['contractor_name']) ?></strong>
                    <?php if ($group['contractor_nip']): ?>
                        <span class="badge bg-light text-dark border"><?= h($group['contractor_nip']) ?></span>
                    <?php endif; ?>
                    <?php if ($groupDelivery): ?>
                        <span class="badge bg-dark-subtle text-dark">
                            <i class="ri-truck-line me-1"></i><?= h($fmtDate($groupDelivery)) ?>
                        </span>
                    <?php endif; ?>
                    <span class="badge bg-secondary-subtle text-secondary"><?= count($groupOrders) ?> zlec.</span>
                    <?php if ($groupCurrency !== 'PLN'): ?>
                        <span class="badge bg-info-subtle text-info"><?= h($groupCurrency) ?></span>
                    <?php endif; ?>
                </div>
            </td>
            <td class="text-end">
                <button class="btn btn-xs btn-outline-secondary py-0 px-1 btn-toggle-group"
                    data-group="<?= h($groupKey) ?>" title="Zwiń/rozwiń">
                    <i class="ri-arrow-up-s-line"></i>
                </button>
            </td>
        </tr>

        <!-- ── Wiersze grupy ── -->
        <?php $today = date('Y-m-d'); foreach ($groupOrders as $order):
            $r = $raw($order);
            $cur = strtoupper(trim((string)($order->currency ?? 'PLN')));
            $fvAction = ($cur !== '' && $cur !== 'PLN') ? 'addCurrency' : 'addVat';
            $fvUrl = $this->Url->build([
                'controller' => 'Invoices',
                'action'     => $fvAction,
                '?'          => ['from_order_id' => $order->id],
            ]);

            // Data dokumentu
            $dateDoc = '';
            if (!empty($order->date_doc)) {
                $dateDoc = $order->date_doc instanceof \DateTimeInterface
                    ? $order->date_doc->format('d.m.Y')
                    : substr((string)$order->date_doc, 0, 10);
            }

            // Załadunek — z kolumn (po sync) lub fallback raw_json
            $loadCountry = (string)($order->load_country     ?? $r['GLO_MIE_KRAJ']   ?? '');
            $loadCode    = (string)($order->load_postal_code ?? $r['GLO_MIE_KOD']    ?? '');
            $loadCity    = (string)($order->load_city        ?? $r['GLO_MIE_POCZTA'] ?? '');
            $loadDate    = $fmtDate(!empty($order->date_deadline)
                ? ($order->date_deadline instanceof \DateTimeInterface ? $order->date_deadline->format('Y-m-d') : (string)$order->date_deadline)
                : trim((string)($r['GLO_DATA_TER'] ?? '')));

            // Rozładunek — z kolumn lub fallback raw_json
            $unloadName    = (string)($order->unload_name    ?? $r['GLO_MIE_NAZWA1'] ?? '');
            $unloadCity    = (string)($order->unload_city    ?? $r['GLO_MIE_MIEJSC'] ?? '');
            $unloadCountry = (string)($order->unload_country ?? '');
            $unloadDate    = $fmtDate(!empty($order->date_delivery)
                ? ($order->date_delivery instanceof \DateTimeInterface ? $order->date_delivery->format('Y-m-d') : (string)$order->date_delivery)
                : trim((string)($r['GLO_DATA_ZAK'] ?? '')));

            // Kierowca / przewoźnik — z kolumn lub fallback raw_json
            $driver  = (string)($order->driver  ?? $r['GLO_JEDNOSTKA'] ?? '');
            $carrier = (string)($order->carrier  ?? $r['GLO_NAZ8']      ?? '');

            // Status Nordlogis + Speed
            $nlStatus    = (int)($order->nordlogis_status ?? 1);
            $speedStatus = (int)($order->status ?? 1);
            $speedLabel  = $speedStatusMap[$speedStatus]['label'] ?? (string)$speedStatus;
        ?>
        <?php
            // Przeterminowane: data rozładunku < dziś i brak POD i brak faktury
            $delivStr = $order->date_delivery instanceof \DateTimeInterface
                ? $order->date_delivery->format('Y-m-d')
                : substr((string)($order->date_delivery ?? ''), 0, 10);
            if ($delivStr === '') $delivStr = substr(trim((string)($r['GLO_DATA_ZAK'] ?? '')), 0, 10);
            $isOverdue = ($delivStr !== '' && $delivStr < $today && empty($order->pod_at) && empty($order->invoice_id));
        ?>
        <tr class="order-row <?= $isOverdue ? 'table-danger row-overdue' : '' ?>"
            data-group="<?= h($groupKey) ?>" data-id="<?= $order->id ?>"
            <?= $isOverdue ? 'title="Przeterminowane — brak POD po terminie rozładunku"' : '' ?>>

            <td>
                <input type="checkbox" class="form-check-input chk-order"
                    value="<?= $order->id ?>"
                    data-group="<?= h($groupKey) ?>"
                    data-contractor="<?= h($order->buyer_name) ?>"
                    data-nip="<?= h($order->buyer_nip) ?>"
                    data-delivery="<?= h($group['delivery_date']) ?>"
                    data-currency="<?= h($cur) ?>"
                    data-symbol="<?= h($order->symbol) ?>">
            </td>

            <!-- Symbol + data dok. + nr zleceń kontrahenta -->
            <td class="text-nowrap">
                <a href="<?= $this->Url->build(['action' => 'view', $order->id]) ?>"
                   class="fw-semibold text-decoration-none d-block"><?= h($order->symbol) ?></a>
                <?php if ($dateDoc): ?>
                    <span class="text-muted" style="font-size:.72rem"><?= h($dateDoc) ?></span>
                <?php endif; ?>
                <?php
                    $tyt1 = trim((string)($order->title1 ?? ''));
                    $tyt2 = trim((string)($order->title2 ?? ''));
                    $tytParts = array_filter([$tyt1, $tyt2]);
                    if ($tytParts): ?>
                    <div class="text-primary" style="font-size:.72rem" title="Nr zlecenia kontrahenta">
                        <i class="ri-file-list-3-line me-1 opacity-50"></i><?= h(implode(' / ', $tytParts)) ?>
                    </div>
                <?php endif; ?>
            </td>

            <!-- Nabywca -->
            <td>
                <div class="fw-medium" style="font-size:.8rem"><?= h($order->buyer_name) ?></div>
                <?php if (!empty($order->buyer_nip)): ?>
                    <div class="text-muted" style="font-size:.72rem"><?= h($order->buyer_nip) ?></div>
                <?php endif; ?>
            </td>

            <!-- Załadunek -->
            <td>
                <?php if ($loadCountry || $loadCode || $loadCity || $loadDate): ?>
                <div class="place-block">
                    <div class="place-label">Zał.</div>
                    <?php if ($loadDate): ?><div class="place-date"><?= h($loadDate) ?></div><?php endif; ?>
                    <?php if ($loadCountry): ?><div class="place-country"><?= h($loadCountry) ?></div><?php endif; ?>
                    <?php $loadPlace = implode(' ', array_filter([$loadCode, $loadCity]));
                          if ($loadPlace): ?><div class="place-city"><?= h($loadPlace) ?></div><?php endif; ?>
                </div>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>

            <!-- Rozładunek -->
            <td>
                <?php if ($unloadName || $unloadCity || $unloadDate || $unloadCountry): ?>
                <div class="place-block">
                    <div class="place-label">Rozł.</div>
                    <?php if ($unloadDate): ?><div class="place-date"><?= h($unloadDate) ?></div><?php endif; ?>
                    <?php if ($unloadCountry): ?><div class="place-country"><?= h($unloadCountry) ?></div><?php endif; ?>
                    <?php $unloadPlace = implode(', ', array_filter([$unloadName, $unloadCity]));
                          if ($unloadPlace): ?><div class="place-city"><?= h($unloadPlace) ?></div><?php endif; ?>
                </div>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>

            <!-- Kierowca / przewoźnik -->
            <td>
                <?php if ($driver): ?>
                    <div style="font-size:.8rem"><i class="ri-user-line me-1 opacity-50"></i><?= h($driver) ?></div>
                <?php endif; ?>
                <?php if ($carrier): ?>
                    <div class="text-muted" style="font-size:.75rem"><i class="ri-truck-line me-1 opacity-50"></i><?= h($carrier) ?></div>
                <?php endif; ?>
                <?php if (!$driver && !$carrier): ?><span class="text-muted">—</span><?php endif; ?>
            </td>

            <!-- Netto -->
            <td class="text-end text-nowrap fw-medium" style="font-size:.8rem">
                <?= $order->netto !== null ? number_format((float)$order->netto, 2, ',', ' ') : '—' ?>
            </td>

            <!-- Waluta -->
            <td class="text-nowrap" style="font-size:.78rem">
                <?php $c = h($order->currency ?? 'PLN'); ?>
                <?= $c !== 'PLN' ? '<span class="badge bg-info-subtle text-info">' . $c . '</span>' : $c ?>
            </td>

            <!-- Status Nordlogis + POL/POD + Speed w tooltipie -->
            <td>
                <?= $nlStatusBadge(!empty($order->invoice_id) ? 5 : $nlStatus, $speedLabel) ?>
                <div class="mt-1">
                    <?= $checkIcon(
                        $order->pol_at instanceof \DateTimeInterface ? $order->pol_at->format('Y-m-d H:i') : (string)($order->pol_at ?? ''),
                        'POL'
                    ) ?>
                    <?= $checkIcon(
                        $order->pod_at instanceof \DateTimeInterface ? $order->pod_at->format('Y-m-d H:i') : (string)($order->pod_at ?? ''),
                        'POD'
                    ) ?>
                </div>
            </td>

            <!-- Akcje -->
            <td class="text-end text-nowrap">
                <?php if (!empty($cmrMap[$order->id])): ?>
                <button type="button"
                        class="btn btn-xs btn-outline-info py-0 px-1 cmr-list-btn"
                        title="CMR (<?= count($cmrMap[$order->id]) ?>)"
                        data-order-id="<?= $order->id ?>"
                        data-cmr="<?= h(json_encode($cmrMap[$order->id])) ?>">
                    <i class="ri-image-2-line"></i>
                    <span style="font-size:.65rem"><?= count($cmrMap[$order->id]) ?></span>
                </button>
                <?php endif; ?>
                <a href="<?= $this->Url->build(['action' => 'view', $order->id]) ?>"
                   class="btn btn-xs btn-outline-secondary py-0 px-1" title="Szczegóły">
                    <i class="ri-eye-line"></i>
                </a>
                <?php if (!empty($order->invoice_id)): ?>
                <a href="<?= $this->Url->build(['controller' => 'Invoices', 'action' => 'view', $order->invoice_id]) ?>"
                   class="btn btn-xs btn-outline-success py-0 px-1 ms-1" title="Pokaż fakturę">
                    <i class="ri-file-text-line"></i>
                </a>
                <?php else: ?>
                <a href="<?= h($fvUrl) ?>"
                   class="btn btn-xs btn-outline-primary py-0 px-1 ms-1"
                   title="Wystaw fakturę">
                    <i class="ri-file-add-line"></i>
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3">
<ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
        <a class="page-link" href="<?= $this->Url->build(['action' => 'index', '?' => array_merge(
            $search !== '' ? ['q' => $search] : [],
            $status !== '' ? ['status' => $status] : [],
            $dateFrom !== '' ? ['date_from' => $dateFrom] : [],
            $dateTo !== '' ? ['date_to' => $dateTo] : [],
            $deliveryFrom !== '' ? ['delivery_from' => $deliveryFrom] : [],
            $deliveryTo !== '' ? ['delivery_to' => $deliveryTo] : [],
            ['page' => $p, 'limit' => $limit]
        )]) ?>"><?= $p ?></a>
    </li>
    <?php endfor; ?>
</ul>
</nav>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ===== SYNC =====
    const btnSync   = document.getElementById('btn-sync-orders');
    const syncModal = new bootstrap.Modal(document.getElementById('syncModal'));
    const bar       = document.getElementById('sync-bar');
    const syncStatus = document.getElementById('sync-status');
    const result    = document.getElementById('sync-result');
    const csrfToken = '<?= $this->request->getAttribute('csrfToken') ?>';
    const syncUrl   = '<?= $this->Url->build(['action' => 'sync']) ?>';
    const batchUrl  = '<?= $this->Url->build(['action' => 'createBatchInvoices']) ?>';

    btnSync.addEventListener('click', function () {
        bar.style.width = '0%'; bar.setAttribute('aria-valuenow', 0);
        syncStatus.textContent = 'Uruchamianie synchronizacji…';
        result.innerHTML = ''; syncModal.show();

        let totalSaved = 0, totalUpdated = 0, totalErrors = [], totalPages = 1;

        function syncPage(page) {
            syncStatus.textContent = 'Pobieranie strony ' + page + ' z ' + totalPages + '…';
            const pct = totalPages > 1 ? Math.round((page - 1) / totalPages * 100) : 50;
            bar.style.width = pct + '%'; bar.setAttribute('aria-valuenow', pct);
            fetch(syncUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ page }),
            }).then(r => r.json()).then(data => {
                if (!data.success) {
                    result.innerHTML = '<div class="alert alert-danger">' + esc(data.error || 'Nieznany błąd') + '</div>';
                    bar.classList.add('bg-danger'); bar.classList.remove('progress-bar-animated'); return;
                }
                totalPages   = data.totalPages || 1;
                totalSaved  += data.saved   || 0;
                totalUpdated += data.updated || 0;
                if (data.errors && data.errors.length) totalErrors = totalErrors.concat(data.errors);
                if (page < totalPages) { syncPage(page + 1); } else {
                    bar.style.width = '100%'; bar.setAttribute('aria-valuenow', 100);
                    bar.classList.remove('progress-bar-animated');
                    let html = '<div class="alert alert-success mb-2">Zakończono! Nowe: <strong>' + totalSaved + '</strong>, zaktualizowane: <strong>' + totalUpdated + '</strong>.</div>';
                    if (totalErrors.length) html += '<div class="alert alert-warning small">Błędy (' + totalErrors.length + '):<br>' + totalErrors.slice(0, 10).map(e => esc(e)).join('<br>') + '</div>';
                    result.innerHTML = html; syncStatus.textContent = 'Gotowe.';
                }
            }).catch(err => {
                result.innerHTML = '<div class="alert alert-danger">Błąd sieci: ' + esc(err.message) + '</div>';
                bar.classList.add('bg-danger'); bar.classList.remove('progress-bar-animated');
            });
        }
        syncPage(1);
    });

    // ===== ZAZNACZANIE =====
    const selectionBar  = document.getElementById('selection-bar');
    const selCount      = document.getElementById('sel-count');
    const selGroupsInfo = document.getElementById('sel-groups-info');
    const btnClear      = document.getElementById('btn-clear-selection');
    const btnInvoke     = document.getElementById('btn-invoke-invoices');
    const chkAll        = document.getElementById('chk-all');

    function getCheckedOrders() {
        return [...document.querySelectorAll('.chk-order:checked')];
    }

    function updateSelectionBar() {
        const checked = getCheckedOrders();
        const count = checked.length;
        const grps = {};
        checked.forEach(chk => {
            const g = chk.dataset.group;
            if (!grps[g]) grps[g] = { contractor: chk.dataset.contractor, delivery: chk.dataset.delivery, n: 0 };
            grps[g].n++;
        });
        const groupCount = Object.keys(grps).length;
        selCount.textContent = count;
        if (count > 0) {
            selectionBar.classList.remove('d-none');
            // Opis grup — każda to osobna faktura (kontrahent + data rozładunku)
            const parts = Object.values(grps).map(function(g) {
                const name = (g.contractor || '').split(' ').slice(0, 2).join(' ');
                const date = g.delivery ? ' / ' + g.delivery : '';
                return name + date + ' (' + g.n + ' zlec.)';
            });
            const label = groupCount + ' ' + (groupCount === 1 ? 'faktura' : groupCount < 5 ? 'faktury' : 'faktur') + ': ';
            selGroupsInfo.textContent = label + parts.join('  •  ');
        } else {
            selectionBar.classList.add('d-none');
        }
        const allOrders = document.querySelectorAll('.chk-order');
        chkAll.indeterminate = count > 0 && count < allOrders.length;
        chkAll.checked = count > 0 && count === allOrders.length;
    }

    chkAll.addEventListener('change', function () {
        document.querySelectorAll('.chk-order, .chk-group').forEach(chk => { chk.checked = chkAll.checked; });
        updateSelectionBar();
    });

    document.querySelectorAll('.chk-group').forEach(function (groupChk) {
        groupChk.addEventListener('change', function () {
            const group = this.dataset.group;
            document.querySelectorAll('.chk-order[data-group="' + group + '"]').forEach(chk => {
                chk.checked = groupChk.checked;
            });
            updateSelectionBar();
        });
    });

    document.querySelectorAll('.chk-order').forEach(function (chk) {
        chk.addEventListener('change', function () {
            const group = this.dataset.group;
            const groupChks = [...document.querySelectorAll('.chk-order[data-group="' + group + '"]')];
            const groupCheck = document.querySelector('.chk-group[data-group="' + group + '"]');
            if (groupCheck) {
                const n = groupChks.filter(c => c.checked).length;
                groupCheck.indeterminate = n > 0 && n < groupChks.length;
                groupCheck.checked = n === groupChks.length;
            }
            updateSelectionBar();
        });
    });

    btnClear.addEventListener('click', function () {
        document.querySelectorAll('.chk-order, .chk-group').forEach(chk => { chk.checked = false; chk.indeterminate = false; });
        chkAll.checked = false; chkAll.indeterminate = false;
        updateSelectionBar();
    });

    // Zwiń/rozwiń grupę
    document.querySelectorAll('.btn-toggle-group').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const group = this.dataset.group;
            const rows = document.querySelectorAll('.order-row[data-group="' + group + '"]');
            const icon = this.querySelector('i');
            const isHidden = rows[0] && rows[0].style.display === 'none';
            rows.forEach(r => { r.style.display = isHidden ? '' : 'none'; });
            icon.className = isHidden ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line';
        });
    });

    // ===== MODAL FAKTUR =====
    const confirmModal = new bootstrap.Modal(document.getElementById('invoiceConfirmModal'));
    const previewDiv   = document.getElementById('invoice-groups-preview');

    function buildGroupsFromSelection(mergeByContractor) {
        const checked = getCheckedOrders();
        const grps = {};
        checked.forEach(chk => {
            // Klucz grupowania: kontrahent+waluta (merge) lub pełny group key
            const contractorKey = (chk.dataset.nip || chk.dataset.contractor || '') + '||' + (chk.dataset.currency || 'PLN');
            const g = mergeByContractor ? contractorKey : chk.dataset.group;
            if (!grps[g]) grps[g] = {
                contractor: chk.dataset.contractor,
                nip: chk.dataset.nip,
                delivery: mergeByContractor ? '' : chk.dataset.delivery,
                deliveries: [],
                currency: chk.dataset.currency,
                ids: [], symbols: [],
            };
            grps[g].ids.push(chk.value);
            grps[g].symbols.push(chk.dataset.symbol || chk.value);
            if (chk.dataset.delivery && grps[g].deliveries.indexOf(chk.dataset.delivery) === -1) {
                grps[g].deliveries.push(chk.dataset.delivery);
            }
        });
        return grps;
    }

    // Reset modalu do trybu preview przy każdym otwarciu
    document.getElementById('invoiceConfirmModal').addEventListener('show.bs.modal', function() {
        document.getElementById('invoice-confirm-phase-preview').classList.remove('d-none');
        document.getElementById('invoice-confirm-phase-links').classList.add('d-none');
        document.getElementById('invoiceConfirmModalTitle').innerHTML = '<i class="ri-file-add-line me-2"></i>Wystaw faktury ze zleceń';
        document.getElementById('invoice-confirm-footer').innerHTML =
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>'
            + '<button type="button" class="btn btn-success" id="btn-confirm-invoices"><i class="ri-file-add-line me-1"></i> Utwórz faktury</button>';
        // Re-attach listener po odtworzeniu przycisku
        const btn = document.getElementById('btn-confirm-invoices');
        if (btn) btn.addEventListener('click', doConfirmInvoices);
    });

    function renderInvoicePreview() {
        const merge = document.getElementById('chk-merge-contractor')?.checked || false;
        const grps = buildGroupsFromSelection(merge);
        const list = Object.values(grps);
        if (list.length === 0) { previewDiv.innerHTML = ''; return; }

        const hint = document.getElementById('invoice-grouping-hint');
        if (hint) {
            hint.innerHTML = merge
                ? 'Zlecenia s\u0105 grupowane wed\u0142ug <strong>zleceniodawcy</strong> (r\u00f3\u017cne daty roz\u0142adunku po\u0142\u0105czone). Ka\u017cda grupa = jedna faktura.'
                : 'Zlecenia s\u0105 grupowane wed\u0142ug <strong>zleceniodawcy i daty roz\u0142adunku</strong> \u2014 tylko takie mog\u0105 trafi\u0107 na jedn\u0105 faktur\u0119. Ka\u017cda grupa = jedna faktura, ka\u017cde zlecenie = osobna pozycja.';
        }

        let html = '';
        list.forEach(function (g, i) {
            const deliveryBadges = (g.deliveries && g.deliveries.length > 0)
                ? g.deliveries.map(d => '<span class="badge bg-secondary-subtle text-secondary">' + esc(d) + '</span>').join(' ')
                : (g.delivery ? '<span class="badge bg-secondary-subtle text-secondary">' + esc(g.delivery) + '</span>' : '');

            html += '<div class="card mb-2"><div class="card-body py-2">'
                + '<div class="d-flex align-items-center gap-2 flex-wrap mb-1">'
                + '<span class="badge bg-primary">Faktura ' + (i + 1) + '</span>'
                + '<strong>' + esc(g.contractor) + '</strong>'
                + (g.nip ? ' <span class="text-muted small">' + esc(g.nip) + '</span>' : '')
                + ' ' + deliveryBadges
                + (g.currency !== 'PLN' ? ' <span class="badge bg-info-subtle text-info">' + esc(g.currency) + '</span>' : '')
                + '</div>'
                + '<div class="text-muted small">Pozycji: ' + g.ids.length + ' \u2014 ' + g.symbols.map(s => '<code>' + esc(s) + '</code>').join(', ') + '</div>'
                + '</div></div>';
        });
        previewDiv.innerHTML = html;
    }

    btnInvoke.addEventListener('click', function () {
        const grps = buildGroupsFromSelection(false);
        if (Object.keys(grps).length === 0) return;
        // Reset toggle do domy\u015blnego trybu
        const chkMerge = document.getElementById('chk-merge-contractor');
        if (chkMerge) chkMerge.checked = false;
        renderInvoicePreview();
        confirmModal.show();
    });

    // Toggle merge-by-contractor: od\u015bwie\u017c podgl\u0105d w modalu
    document.getElementById('chk-merge-contractor')?.addEventListener('change', renderInvoicePreview);

    function doConfirmInvoices() {
        const merge = document.getElementById('chk-merge-contractor')?.checked || false;
        const grps = buildGroupsFromSelection(merge);
        const payload = Object.values(grps).map(g => ({
            order_ids: g.ids.map(Number),
            contractor: g.contractor,
            nip: g.nip,
            delivery_date: g.deliveries ? g.deliveries.join(', ') : (g.delivery || ''),
            currency: g.currency,
        }));

        const $btn = document.getElementById('btn-confirm-invoices');
        if ($btn) { $btn.disabled = true; $btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Tworzenie…'; }

        fetch(batchUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ groups: payload }),
        }).then(r => r.json()).then(data => {
            const $btn2 = document.getElementById('btn-confirm-invoices');
            if ($btn2) { $btn2.disabled = false; $btn2.innerHTML = '<i class="ri-file-add-line me-1"></i> Utwórz faktury'; }

            if (!data.success || !data.invoices || !data.invoices.length) {
                confirmModal.hide();
                Swal.fire({ icon: 'error', title: 'Błąd', text: data.error || 'Nie udało się utworzyć faktur.' });
                return;
            }

            const invs = data.invoices;

            // Przełącz modal w tryb "linki"
            document.getElementById('invoice-confirm-phase-preview').classList.add('d-none');
            document.getElementById('invoice-confirm-phase-links').classList.remove('d-none');
            document.getElementById('invoiceConfirmModalTitle').innerHTML = '<i class="ri-links-line me-2"></i>Gotowe — ' + invs.length + ' ' + (invs.length === 1 ? 'faktura' : invs.length < 5 ? 'faktury' : 'faktur') + ' do wystawienia';

            // Buduj listę linków
            let linksHtml = '<div class="list-group">';
            invs.forEach(function(inv, i) {
                linksHtml += '<a href="' + esc(inv.url) + '" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2">'
                    + '<span class="badge bg-primary me-1">' + (i + 1) + '</span>'
                    + '<div class="flex-grow-1">'
                    + '<div class="fw-medium small">' + esc(inv.contractor || '—') + '</div>'
                    + (inv.delivery_date ? '<div class="text-muted" style="font-size:.72rem">Rozładunek: ' + esc(inv.delivery_date) + '</div>' : '')
                    + '</div>'
                    + '<span class="badge bg-secondary-subtle text-secondary">' + (inv.count || 1) + ' zlec.</span>'
                    + (inv.currency && inv.currency !== 'PLN' ? '<span class="badge bg-info-subtle text-info ms-1">' + esc(inv.currency) + '</span>' : '')
                    + '<i class="ri-external-link-line ms-2 opacity-50"></i>'
                    + '</a>';
            });
            linksHtml += '</div>';
            document.getElementById('invoice-links-list').innerHTML = linksHtml;

            // Zamień footer
            const footer = document.getElementById('invoice-confirm-footer');
            footer.innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>'
                + (invs.length > 1
                    ? '<button type="button" class="btn btn-outline-primary" id="btn-open-all-sequentially"><i class="ri-external-link-line me-1"></i>Otwórz wszystkie</button>'
                    : '');

            // Obsługa "Otwórz wszystkie" — sekwencyjnie z opóźnieniem (przeglądarki nie blokują po interakcji użytkownika)
            const btnOpenAll = document.getElementById('btn-open-all-sequentially');
            if (btnOpenAll) {
                btnOpenAll.addEventListener('click', function() {
                    btnOpenAll.disabled = true;
                    invs.forEach(function(inv, i) {
                        setTimeout(function() { if (inv.url) window.open(inv.url, '_blank'); }, i * 300);
                    });
                });
            }
        }).catch(err => {
            confirmModal.hide();
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = '<i class="ri-file-add-line me-1"></i> Utwórz faktury';
            Swal.fire({ icon: 'error', title: 'Błąd sieci', text: err.message });
        });
    }

    // Podepnij listener na startowy przycisk Potwierdź
    const btnConfirm = document.getElementById('btn-confirm-invoices');
    if (btnConfirm) btnConfirm.addEventListener('click', doConfirmInvoices);

    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str || '')));
        return d.innerHTML;
    }
});
</script>

<!-- GLightbox — CMR na liście zleceń -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">
<script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
<script>
(function () {
    let cmrLightbox = null;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.cmr-list-btn');
        if (!btn) return;

        const files = JSON.parse(btn.dataset.cmr || '[]');
        if (!files.length) return;

        const elements = files.map(f => {
            const isImg = (f.mime || '').startsWith('image/');
            const url   = '/' + f.path.replace(/^\//, '');
            return {
                href:        isImg ? url : url,
                type:        isImg ? 'image' : 'iframe',
                title:       f.name + (f.label ? ' — ' + f.label : ''),
                description: f.label || '',
            };
        });

        if (cmrLightbox) cmrLightbox.destroy();
        cmrLightbox = GLightbox({ elements, touchNavigation: true, loop: elements.length > 1, zoomable: true });
        cmrLightbox.open();
    });
})();
</script>

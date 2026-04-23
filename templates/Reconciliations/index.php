<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Invoice[] $invoices
 * @var object[] $legacyInvoices
 * @var int $legacyTotal
 * @var int $legacyPages
 * @var int $legacyPage
 * @var string $sourceFilter
 * @var object|null $lastSync
 * @var array $bankByInvoice
 * @var array $speedByInvoice
 * @var array $stats
 * @var string $search
 * @var string $status
 * @var string $dateFrom
 * @var string $dateTo
 * @var string $typeFilter
 * @var string $sort
 * @var string $dir
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var int $limit
 * @var string $title
 */
$this->assign('title', $title ?? 'Rozliczenia');

/** @var string $baseAction */
$baseAction ??= 'index';
/** @var string $lockSource */
$lockSource ??= '';

$today = new \DateTime('today');

// ── Pomocniki formatowania ───────────────────────────────────────────────────
$fdate = function ($v): string {
    if (!$v) return '—';
    if ($v instanceof \DateTimeInterface) return $v->format('d.m.Y');
    // Cake\I18n\Date też ma format()
    if (is_object($v) && method_exists($v, 'format')) return $v->format('d.m.Y');
    $s = substr((string)$v, 0, 10);
    if (!$s) return '—';
    // Zamień Y-m-d → d.m.Y
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) return $m[3] . '.' . $m[2] . '.' . $m[1];
    return $s;
};

$fnum = function ($v, string $currency = 'PLN'): string {
    if ($v === null || $v === '') return '—';
    return number_format((float)$v, 2, ',', ' ') . ' ' . h($currency);
};

// Oblicz termin płatności od daty dostawy (speed order) — używane tylko do wyświetlenia w kolumnie
// payment_days = paymentdate - date (w dniach)
$shipDueDate = function (?\App\Model\Entity\Invoice $invoice, ?object $speedOrder): ?string {
    if ($invoice === null || $speedOrder === null) return null;
    $invoiceDate   = $invoice->date;
    $paymentDate   = $invoice->paymentdate;
    if (!$invoiceDate || !$paymentDate) return null;
    if (!$invoiceDate instanceof \DateTimeInterface) {
        $invoiceDate = new \DateTime(substr((string)$invoiceDate, 0, 10));
    }
    if (!$paymentDate instanceof \DateTimeInterface) {
        $paymentDate = new \DateTime(substr((string)$paymentDate, 0, 10));
    }
    $paymentDays = (int)$invoiceDate->diff($paymentDate)->days;

    // Preferuj date_delivery, fallback date_ship
    $deliveryDate = $speedOrder->date_delivery ?? $speedOrder->date_ship ?? null;
    if (!$deliveryDate) return null;
    if (!$deliveryDate instanceof \DateTimeInterface) {
        $deliveryDate = new \DateTime(substr((string)$deliveryDate, 0, 19));
    }
    $shipDue = clone $deliveryDate;
    $shipDue->modify('+' . $paymentDays . ' days');
    return $shipDue->format('Y-m-d');
};

// Oblicz termin płatności od daty wysyłki dokumentów (invoices.sent_at + dni płatności)
// To jest EFEKTYWNY termin — priorytetowy dla oceny przeterminowania
$sentBasedDue = function (?\App\Model\Entity\Invoice $invoice): ?string {
    if ($invoice === null) return null;
    $sentAt      = $invoice->sent_at ?? null;
    $invoiceDate = $invoice->date;
    $paymentDate = $invoice->paymentdate;
    if (!$sentAt || !$invoiceDate || !$paymentDate) return null;

    // Normalizuj do mutowalnych DateTime (sent_at i date są Cake\I18n\Date/DateTime → DateTimeImmutable,
    // modify() na immutable zwraca nowy obiekt zamiast modyfikować w miejscu — stąd używamy DateTime)
    $toMutable = static function ($v, int $substLen = 10): \DateTime {
        if ($v instanceof \DateTimeInterface) {
            return \DateTime::createFromFormat('Y-m-d', $v->format('Y-m-d'));
        }
        $s = substr((string)$v, 0, $substLen);
        return \DateTime::createFromFormat('d.m.Y', $s)
            ?: \DateTime::createFromFormat('Y-m-d', $s)
            ?: new \DateTime($s);
    };

    $invoiceDt = $toMutable($invoiceDate);
    $paymentDt = $toMutable($paymentDate);
    $sentDt    = $toMutable($sentAt, 19);

    $paymentDays = (int)$invoiceDt->diff($paymentDt)->days;
    $sentDt->modify('+' . $paymentDays . ' days');
    return $sentDt->format('Y-m-d');
};

// Badge statusu płatności
$paymentBadge = function (?string $state, ?string $paymentdate, string $today): string {
    if ($state === 'paid') {
        return '<span class="badge bg-success-subtle text-success border border-success-subtle">Zapłacona</span>';
    }
    if ($state === 'partial') {
        if ($paymentdate && $paymentdate < $today) {
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Częściowo — po terminie</span>';
        }
        return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Częściowo</span>';
    }
    // unpaid
    if ($paymentdate && $paymentdate < $today) {
        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Przeterminowana</span>';
    }
    return '<span class="badge bg-light text-secondary border">Do zapłaty</span>';
};

// Badge transakcji bankowej
$bankBadge = function (?object $bt): string {
    if ($bt === null) return '';
    if ($bt->match_status === 'matched') {
        return '<span class="badge bg-success-subtle text-success border border-success-subtle" title="Przelew dopasowany"><i class="ri-checkbox-circle-line me-1"></i>Przelew</span>';
    }
    return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle" title="Przelew do potwierdzenia"><i class="ri-alert-line me-1"></i>Przelew?</span>';
};

// Pomocnik URL z aktualnymi filtrami
$currentUrl = function (array $extra = []) use ($baseAction, $search, $status, $dateFrom, $dateTo, $dueDateFrom, $dueDateTo, $currencyFilter, $amountFrom, $amountTo, $bankAccountFilter, $typeFilter, $sourceFilter, $sort, $dir, $limit, $page): array {
    $base = [
        'q'            => $search,
        'status'       => $status,
        'date_from'    => $dateFrom,
        'date_to'      => $dateTo,
        'due_from'     => $dueDateFrom,
        'due_to'       => $dueDateTo,
        'currency'     => $currencyFilter,
        'amount_from'  => $amountFrom,
        'amount_to'    => $amountTo,
        'bank_account' => $bankAccountFilter,
        'type'         => $typeFilter,
        'source'       => $sourceFilter,
        'sort'         => $sort,
        'dir'          => $dir,
        'limit'        => $limit,
        'page'         => $page,
    ];
    $merged = array_merge($base, $extra);
    $params = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return ['action' => $baseAction, '?' => $params];
};

// Pomocnik sortowania (link z odwróconym kierunkiem)
$sortLink = function (string $col, string $label) use ($sort, $dir, $currentUrl): string {
    $newDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $icon   = '';
    if ($sort === $col) {
        $icon = $dir === 'asc'
            ? ' <i class="ri-arrow-up-s-line"></i>'
            : ' <i class="ri-arrow-down-s-line"></i>';
    }
    $url = $currentUrl(['sort' => $col, 'dir' => $newDir, 'page' => 1]);
    return '<a href="' . \Cake\Routing\Router::url($url) . '" class="text-dark text-decoration-none fw-semibold">'
        . h($label) . $icon . '</a>';
};

$todayStr = $today->format('Y-m-d');

// Badge typów — identyczna kolorystyka jak na liście faktur
$typeBadge = function (string $type): string {
    $map = [
        'vat'              => ['bg-primary',   'VAT'],
        'novat'            => ['bg-secondary',  'Rachunek'],
        'currency'         => ['bg-info',       'Walutowa'],
        'proforma'         => ['bg-info',       'Proforma'],
        'advance'          => ['bg-warning',    'Zaliczka'],
        'final'            => ['bg-dark',       'Końcowa'],
        'correction'       => ['bg-danger',     'Korekta'],
        'margin'           => ['bg-success',    'Marża'],
        'rental'           => ['bg-success',    'Najem'],
        'oss'              => ['bg-purple',     'OSS'],
        'internal'         => ['bg-dark',       'Wewnętrzna'],
        'internalEvidence' => ['bg-dark',       'Dowód wewn.'],
    ];
    [$cls, $label] = $map[$type] ?? ['bg-light text-dark', h($type)];
    return '<span class="badge ' . $cls . '">' . $label . '</span>';
};
?>

<!-- Nagłówek -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">Rozliczenia <span class="text-muted fs-6 fw-normal">faktury · wpłaty · przelewy</span></h4>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-outline-warning"
                data-bs-toggle="modal" data-bs-target="#legacySyncModal"
                title="Synchronizuj faktury z zewnętrznego systemu">
            <i class="ri-refresh-line me-1"></i>Arch. legacy
            <?php if ($lastSync !== null): ?>
                <span class="ms-1 text-muted" style="font-size:.7rem">
                    <?php
                    $ls = $lastSync->synced_at;
                    $lsStr = $ls instanceof \DateTimeInterface ? $ls->format('d.m.Y H:i') : substr((string)$ls, 0, 16);
                    echo h($lsStr);
                    ?>
                </span>
            <?php endif; ?>
        </button>
        <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Invoices', 'action' => 'index']) ?>"
           class="btn btn-sm btn-outline-secondary">
            <i class="ri-file-list-line me-1"></i> Lista faktur
        </a>
        <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'BankTransactions', 'action' => 'transactions', '?' => ['status' => 'unmatched']]) ?>"
           class="btn btn-sm btn-outline-secondary">
            <i class="ri-bank-line me-1"></i> Niedopasowane przelewy
        </a>
    </div>
</div>

<?= $this->Flash->render() ?>

<!-- Kafelki statystyk -->
<?php if ($stats['count'] > 0): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="ri-file-list-3-line fs-5 text-primary"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1"><?= $stats['count'] ?></div>
                    <div class="text-muted small">Faktur łącznie</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $this->Url->build($currentUrl(['status' => 'overdue', 'page' => 1])) ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 <?= $stats['overdueCount'] > 0 ? 'border border-danger' : '' ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="ri-error-warning-line fs-5 text-danger"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 <?= $stats['overdueCount'] > 0 ? 'text-danger' : '' ?>">
                        <?= number_format($stats['overdue'], 0, ',', ' ') ?> zł
                    </div>
                    <div class="text-muted small">Przeterminowane (<?= $stats['overdueCount'] ?>)</div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $this->Url->build($currentUrl(['status' => 'unpaid', 'page' => 1])) ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="ri-time-line fs-5 text-warning"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1">
                        <?= number_format($stats['totalRemaining'], 0, ',', ' ') ?> zł
                    </div>
                    <div class="text-muted small">Do zapłaty</div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $this->Url->build($currentUrl(['status' => 'paid', 'page' => 1])) ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                    <i class="ri-checkbox-circle-line fs-5 text-success"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 text-success">
                        <?= number_format($stats['totalPaid'], 0, ',', ' ') ?> zł
                    </div>
                    <div class="text-muted small">Zapłacono</div>
                </div>
            </div>
        </div>
        </a>
    </div>
</div>
<?php endif; ?>

<?php
// Liczba aktywnych filtrów (dla badge)
$activeFilterCount = 0;
if ($search !== '')            $activeFilterCount++;
if ($dateFrom !== '')          $activeFilterCount++;
if ($dateTo !== '')            $activeFilterCount++;
if ($dueDateFrom !== '')       $activeFilterCount++;
if ($dueDateTo !== '')         $activeFilterCount++;
if ($currencyFilter !== '')    $activeFilterCount++;
if ($amountFrom !== '')        $activeFilterCount++;
if ($amountTo !== '')          $activeFilterCount++;
if ($bankAccountFilter !== '') $activeFilterCount++;
if ($typeFilter !== '')        $activeFilterCount++;
if ($sourceFilter !== '' && $lockSource === '') $activeFilterCount++;
if ($status !== '')            $activeFilterCount++;
?>

<!-- Filtry -->
<div class="card shadow-sm mb-3" id="rec-filter-card">
    <div class="card-header py-2 d-flex align-items-center gap-2 bg-white border-bottom">
        <i class="ri-filter-3-line text-primary"></i>
        <span class="fw-semibold small">Filtry</span>
        <?php if ($activeFilterCount > 0): ?>
            <span class="badge bg-primary rounded-pill ms-1"><?= $activeFilterCount ?></span>
        <?php endif; ?>
        <button class="btn btn-link btn-sm text-muted ms-auto p-0 pe-1" type="button"
                data-bs-toggle="collapse" data-bs-target="#rec-filter-body" aria-expanded="true">
            <i class="ri-arrow-up-s-line" id="rec-filter-chevron"></i>
        </button>
    </div>
    <div class="collapse show" id="rec-filter-body">
    <div class="card-body py-2 px-3">
        <!-- Presety daty -->
        <div class="d-flex flex-wrap gap-1 mb-2">
            <span class="small text-muted me-1 align-self-center">Okres:</span>
            <button type="button" class="btn btn-xs btn-outline-secondary date-preset" style="font-size:.73rem;padding:1px 8px"
                    data-preset="this_month">Ten miesiąc</button>
            <button type="button" class="btn btn-xs btn-outline-secondary date-preset" style="font-size:.73rem;padding:1px 8px"
                    data-preset="prev_month">Poprzedni miesiąc</button>
            <button type="button" class="btn btn-xs btn-outline-secondary date-preset" style="font-size:.73rem;padding:1px 8px"
                    data-preset="this_quarter">Ten kwartał</button>
            <button type="button" class="btn btn-xs btn-outline-secondary date-preset" style="font-size:.73rem;padding:1px 8px"
                    data-preset="prev_quarter">Poprzedni kwartał</button>
            <button type="button" class="btn btn-xs btn-outline-secondary date-preset" style="font-size:.73rem;padding:1px 8px"
                    data-preset="this_year">Ten rok</button>
        </div>
        <form id="rec-filter-form" method="get" action="<?= $this->Url->build(['action' => $baseAction]) ?>">
            <div class="row g-2 align-items-end">
                <!-- Szukaj -->
                <div class="col-12 col-md-4">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Szukaj</label>
                    <input type="text" name="q" id="rec-q" value="<?= h($search) ?>" class="form-control form-control-sm"
                           placeholder="Numer faktury, kontrahent, NIP…">
                </div>
                <!-- Waluta -->
                <div class="col-6 col-md-1">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Waluta</label>
                    <select name="currency" class="form-select form-select-sm">
                        <option value="">Wszystkie</option>
                        <option value="PLN" <?= $currencyFilter === 'PLN' ? 'selected' : '' ?>>PLN</option>
                        <option value="EUR" <?= $currencyFilter === 'EUR' ? 'selected' : '' ?>>EUR</option>
                        <option value="USD" <?= $currencyFilter === 'USD' ? 'selected' : '' ?>>USD</option>
                        <option value="GBP" <?= $currencyFilter === 'GBP' ? 'selected' : '' ?>>GBP</option>
                        <option value="CHF" <?= $currencyFilter === 'CHF' ? 'selected' : '' ?>>CHF</option>
                        <option value="CZK" <?= $currencyFilter === 'CZK' ? 'selected' : '' ?>>CZK</option>
                    </select>
                </div>
                <!-- Typ -->
                <div class="col-6 col-md-1">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Typ</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Wszystkie</option>
                        <option value="vat"        <?= $typeFilter === 'vat'        ? 'selected' : '' ?>>FV VAT</option>
                        <option value="novat"      <?= $typeFilter === 'novat'      ? 'selected' : '' ?>>FV bez VAT</option>
                        <option value="currency"   <?= $typeFilter === 'currency'   ? 'selected' : '' ?>>Walutowa</option>
                        <option value="proforma"   <?= $typeFilter === 'proforma'   ? 'selected' : '' ?>>Proforma</option>
                        <option value="advance"    <?= $typeFilter === 'advance'    ? 'selected' : '' ?>>Zaliczkowa</option>
                        <option value="final"      <?= $typeFilter === 'final'      ? 'selected' : '' ?>>Końcowa</option>
                        <option value="correction" <?= $typeFilter === 'correction' ? 'selected' : '' ?>>Korekta</option>
                        <option value="margin"     <?= $typeFilter === 'margin'     ? 'selected' : '' ?>>Marża</option>
                        <option value="rental"     <?= $typeFilter === 'rental'     ? 'selected' : '' ?>>Najem</option>
                    </select>
                </div>
                <!-- Źródło -->
                <?php if ($lockSource === ''): ?>
                <div class="col-6 col-md-1">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Źródło</label>
                    <select name="source" class="form-select form-select-sm">
                        <option value=""       <?= $sourceFilter === ''       ? 'selected' : '' ?>>Wszystkie</option>
                        <option value="system" <?= $sourceFilter === 'system' ? 'selected' : '' ?>>System</option>
                        <option value="legacy" <?= $sourceFilter === 'legacy' ? 'selected' : '' ?>>Archiwum</option>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="source" value="<?= h($lockSource) ?>">
                <?php endif; ?>
                <!-- Rachunek bankowy -->
                <?php if (!empty($companyBankAccounts)): ?>
                <div class="col-12 col-md-3">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">
                        <i class="ri-bank-line me-1"></i>Rachunek bankowy
                    </label>
                    <select name="bank_account" class="form-select form-select-sm">
                        <option value="">— wszystkie —</option>
                        <?php foreach ($companyBankAccounts as $cba):
                            $cbaLabel = h($cba->label ?: $cba->bank_name ?: '');
                            $cbaIban  = h($cba->iban ?? '');
                            $cbaCur   = h($cba->currency ?? 'PLN');
                            $ibanShort = strlen($cba->iban ?? '') > 8
                                ? '…' . substr(preg_replace('/[\s\-]/', '', $cba->iban), -8)
                                : h($cba->iban ?? '');
                            $selected = ($bankAccountFilter !== '' && str_contains(
                                preg_replace('/[\s\-]/', '', $cba->iban ?? ''),
                                preg_replace('/[\s\-]/', '', $bankAccountFilter)
                            )) ? 'selected' : '';
                        ?>
                            <option value="<?= $cbaIban ?>" <?= $selected ?>>
                                <?= $cbaLabel ? "$cbaLabel · $ibanShort · $cbaCur" : "$ibanShort · $cbaCur" ?>
                                <?= $cba->is_default ? '★' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="row g-2 align-items-end mt-0">
                <!-- Data wystawienia od–do -->
                <div class="col-6 col-md-auto">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Wystawiona od</label>
                    <input type="date" name="date_from" id="rec-date-from" value="<?= h($dateFrom) ?>" class="form-control form-control-sm" style="min-width:130px">
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">do</label>
                    <input type="date" name="date_to" id="rec-date-to" value="<?= h($dateTo) ?>" class="form-control form-control-sm" style="min-width:130px">
                </div>
                <!-- Termin płatności od–do -->
                <div class="col-6 col-md-auto">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Termin od</label>
                    <input type="date" name="due_from" value="<?= h($dueDateFrom) ?>" class="form-control form-control-sm" style="min-width:130px">
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">do</label>
                    <input type="date" name="due_to" value="<?= h($dueDateTo) ?>" class="form-control form-control-sm" style="min-width:130px">
                </div>
                <!-- Kwota brutto od–do -->
                <div class="col-6 col-md-auto">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Kwota od</label>
                    <input type="number" name="amount_from" value="<?= h($amountFrom) ?>" min="0" step="0.01"
                           class="form-control form-control-sm" placeholder="0,00" style="min-width:90px">
                </div>
                <div class="col-6 col-md-auto">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">do</label>
                    <input type="number" name="amount_to" value="<?= h($amountTo) ?>" min="0" step="0.01"
                           class="form-control form-control-sm" placeholder="999 999" style="min-width:90px">
                </div>
                <!-- Limit -->
                <div class="col-auto">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Na str.</label>
                    <select name="limit" class="form-select form-select-sm" style="min-width:65px">
                        <?php foreach ([25, 50, 100, 200] as $l): ?>
                            <option value="<?= $l ?>" <?= $limit == $l ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="sort" value="<?= h($sort) ?>">
                <input type="hidden" name="dir"  value="<?= h($dir) ?>">
                <!-- Przyciski -->
                <div class="col-auto ms-auto d-flex gap-1 align-items-end">
                    <?php if ($activeFilterCount > 0): ?>
                        <a href="<?= $this->Url->build(['action' => 'index']) ?>"
                           class="btn btn-sm btn-outline-danger" title="Wyczyść wszystkie filtry">
                            <i class="ri-close-circle-line me-1"></i>Wyczyść (<?= $activeFilterCount ?>)
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="ri-search-line me-1"></i>Szukaj
                    </button>
                </div>
            </div>
        </form>
    </div>
    </div>
</div>

<!-- Szybkie filtry statusu -->
<?php if ($lockSource !== 'legacy'): ?>
<div class="d-flex flex-wrap gap-1 mb-3">
    <?php
    $statusFilters = [
        ''         => ['label' => 'Wszystkie', 'class' => 'secondary'],
        'unpaid'   => ['label' => 'Do zapłaty', 'class' => 'secondary'],
        'partial'  => ['label' => 'Częściowe', 'class' => 'warning'],
        'paid'     => ['label' => 'Zapłacone', 'class' => 'success'],
        'overdue'  => ['label' => '<i class="ri-error-warning-line me-1"></i>Przeterminowane', 'class' => 'danger'],
    ];
    foreach ($statusFilters as $val => $opt):
        $active = ($status === $val);
        $variant = $active ? "btn-{$opt['class']}" : "btn-outline-{$opt['class']}";
    ?>
        <a href="<?= $this->Url->build($currentUrl(['status' => $val, 'page' => 1])) ?>"
           class="btn btn-sm <?= $variant ?>"><?= $opt['label'] ?></a>
    <?php endforeach; ?>
</div>

<!-- Tabela -->
<?php if (empty($invoices)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="ri-file-search-line fs-1 text-muted mb-3 d-block"></i>
            <h5 class="text-muted">Brak faktur spełniających kryteria</h5>
            <?php if ($search !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary btn-sm mt-2">
                    <i class="ri-close-line me-1"></i> Wyczyść filtry
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle" id="rec-table">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="min-width:130px"><?= $sortLink('fullnumber', 'Nr faktury') ?></th>
                    <th style="min-width:160px">Kontrahent</th>
                    <th class="text-center" style="width:55px">Typ</th>
                    <th class="text-nowrap"><?= $sortLink('date', 'Wystawiona') ?></th>
                    <th class="text-nowrap"><?= $sortLink('paymentdate', 'Termin') ?></th>
                    <th class="text-end text-nowrap"><?= $sortLink('total', 'Brutto') ?></th>
                    <th class="text-end text-nowrap"><?= $sortLink('remaining', 'Pozostało') ?></th>
                    <th style="min-width:140px">Status</th>
                    <th style="min-width:100px">Przelew</th>
                    <th style="min-width:100px">Zlecenia</th>
                    <th class="pe-3 text-end" style="min-width:110px">Akcje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($invoices as $invoice):
                $bt    = $bankByInvoice[(string)$invoice->id] ?? null;
                $state = $invoice->paymentstate ?? 'unpaid';

                // Korekty do tej faktury
                $invCorrs = $correctionsByParentId[(string)$invoice->id] ?? [];
                $corrSum  = array_sum(array_map(fn($c) => (float)$c->total, $invCorrs));
                // Różnica: > 0 = dopłata, < 0 = redukcja, = 0 = zeruje fakturę
                $corrDiff = !empty($invCorrs) ? round($corrSum - (float)$invoice->total, 2) : 0.0;
                // Efektywne remaining po korekcie
                $netRemaining = !empty($invCorrs) ? max(0.0, (float)$invoice->remaining + $corrDiff) : (float)$invoice->remaining;

                // Normalizuj paymentdate do Y-m-d niezależnie od formatu zwróconego przez ORM
                $rawPd = $invoice->paymentdate;
                if ($rawPd instanceof \DateTimeInterface) {
                    $pdateStr = $rawPd->format('Y-m-d');
                } elseif ($rawPd) {
                    $s = (string)$rawPd;
                    $dt = \DateTime::createFromFormat('d.m.Y', substr($s, 0, 10))
                       ?: \DateTime::createFromFormat('Y-m-d', substr($s, 0, 10));
                    $pdateStr = $dt ? $dt->format('Y-m-d') : null;
                } else {
                    $pdateStr = null;
                }

                // Efektywny termin płatności (priorytet dla oceny przeterminowania):
                // sent_at + dni płatności (data wysyłki dokumentów z labelki),
                // fallback: normalny termin z faktury
                $sentDue      = $sentBasedDue($invoice);
                $effectiveDue = $sentDue ?? $pdateStr;

                // Kolorowanie wiersza — na podstawie efektywnego terminu
                $rowClass = '';
                if ($state !== 'paid' && $effectiveDue && $effectiveDue < $todayStr) {
                    $rowClass = 'table-danger';
                }

                $currency = $invoice->currency ?? 'PLN';
                $contractorName = $invoice->invoice_contractor->name ?? '—';
            ?>
                <tr class="<?= $rowClass ?>" data-invoice-id="<?= h($invoice->id) ?>">
                    <!-- Nr faktury -->
                    <td class="ps-3">
                        <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Invoices', 'action' => 'view', $invoice->id]) ?>"
                           class="fw-semibold text-decoration-none text-dark">
                            <?= h($invoice->fullnumber ?? '—') ?>
                        </a>
                        <?php if ($invoice->sent_at): ?>
                            <span class="ms-1 text-info" title="Dokumenty wysłane pocztą: <?= $fdate($invoice->sent_at) ?>">
                                <i class="ri-mail-send-line"></i>
                            </span>
                        <?php endif; ?>
                        <?php foreach ($invCorrs as $corr): ?>
                            <div class="mt-1">
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle"
                                      style="font-size:.6rem"
                                      title="Korekta <?= h($corr->fullnumber) ?> z dn. <?= $fdate($corr->date) ?> · kwota: <?= number_format((float)$corr->total, 2, ',', ' ') ?> <?= h($corr->currency ?? $currency) ?>">
                                    <i class="ri-arrow-go-back-line me-1"></i>KOR: <?= h($corr->fullnumber) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <!-- Kontrahent -->
                    <td style="max-width:200px">
                        <div class="d-flex align-items-center gap-1">
                            <span class="small text-truncate" title="<?= h($contractorName) ?>"><?= h($contractorName) ?></span>
                            <button type="button"
                                    class="btn btn-xs p-0 border-0 text-muted flex-shrink-0 btn-contractor-info"
                                    data-invoice-id="<?= h($invoice->id) ?>"
                                    title="Szczegóły kontrahenta"
                                    style="line-height:1">
                                <i class="ri-user-line fs-6"></i>
                            </button>
                        </div>
                    </td>
                    <!-- Typ -->
                    <td class="text-center">
                        <?= $typeBadge($invoice->type ?? '') ?>
                    </td>
                    <!-- Data wystawienia -->
                    <td class="text-nowrap small text-muted"><?= $fdate($invoice->date) ?></td>
                    <!-- Termin z faktury -->
                    <td class="text-nowrap small">
                        <?php if ($pdateStr): ?>
                            <?php
                            // Kolumna pokazuje termin z faktury — "dni temu" dotyczy tej samej daty.
                            // Kolor wiersza i badge statusu używają $effectiveDue (od sent_at).
                            $invPast  = $pdateStr < $todayStr && $state !== 'paid';
                            $invToday = $pdateStr === $todayStr && $state !== 'paid';
                            $cls      = $invPast ? 'text-danger fw-semibold' : ($invToday ? 'text-warning fw-semibold' : 'text-muted');
                            ?>
                            <span class="<?= $cls ?>">
                                <?= $fdate($invoice->paymentdate) ?>
                                <?php if ($invPast): ?>
                                    <span class="ms-1 small text-danger">
                                        (<?= (int)(new \DateTime($pdateStr))->diff($today)->days ?> dni temu)
                                    </span>
                                <?php elseif ($invToday): ?>
                                    <span class="ms-1 badge bg-warning-subtle text-warning border border-warning-subtle small">dziś</span>
                                <?php endif; ?>
                            </span>
                            <?php if ($sentDue && $sentDue !== $pdateStr): ?>
                                <div class="text-muted" style="font-size:0.7rem" title="Termin liczony od daty wysyłki dokumentów">
                                    ef: <?= $fdate($sentDue) ?>
                                    <?php if ($sentDue < $todayStr && $state !== 'paid'): ?>
                                        <span class="text-danger">
                                            (<?= (int)(new \DateTime($sentDue))->diff($today)->days ?> dni temu)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <!-- Brutto -->
                    <td class="text-end text-nowrap small fw-semibold">
                        <?= number_format((float)$invoice->total, 2, ',', ' ') ?> <?= h($currency) ?>
                        <?php if (!empty($invCorrs)): ?>
                            <?php if (abs($corrDiff) < 0.01): ?>
                                <?php /* różnica = 0, korekta zeruje fakturę — sam badge wystarczy */ ?>
                            <?php elseif ($corrDiff > 0): ?>
                                <div class="text-success fw-normal" style="font-size:.75em"
                                     title="Korekta zwiększa kwotę">
                                    +<?= number_format($corrDiff, 2, ',', ' ') ?> <?= h($currency) ?>
                                </div>
                            <?php else: ?>
                                <div class="text-danger fw-normal" style="font-size:.75em"
                                     title="Korekta zmniejsza kwotę">
                                    <?= number_format($corrDiff, 2, ',', ' ') ?> <?= h($currency) ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <!-- Pozostało -->
                    <td class="text-end text-nowrap small">
                        <?php if ($state === 'paid'): ?>
                            <span class="text-success">0,00 <?= h($currency) ?></span>
                        <?php else: ?>
                            <span class="<?= $netRemaining > 0 ? 'fw-semibold text-dark' : 'text-muted' ?>">
                                <?= number_format($netRemaining, 2, ',', ' ') ?> <?= h($currency) ?>
                            </span>
                        <?php endif; ?>
                        <?php if ((float)$invoice->alreadypaid > 0 && $state !== 'paid'): ?>
                            <div class="text-muted" style="font-size:0.7rem">
                                wpłacono: <?= number_format((float)$invoice->alreadypaid, 2, ',', ' ') ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <!-- Status -->
                    <td><?= $paymentBadge($state, $effectiveDue ?: null, $todayStr) ?></td>
                    <!-- Przelew -->
                    <td>
                        <?= $bankBadge($bt) ?>
                        <?php if ($bt !== null): ?>
                            <div style="font-size:0.7rem" class="text-muted mt-1">
                                <?= number_format((float)$bt->amount, 2, ',', ' ') ?> PLN
                                · <?= $fdate($bt->value_date) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <!-- Zlecenia Speed -->
                    <td>
                        <?php $orders = $speedByInvoice[(string)$invoice->id] ?? []; ?>
                        <?php if (empty($orders)): ?>
                            <span class="text-muted small">—</span>
                        <?php elseif (count($orders) === 1):
                            $so = $orders[0]; ?>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary py-0 px-1 btn-view-order-modal"
                                    data-order-id="<?= h($so->id) ?>"
                                    title="<?= h($so->symbol ?? '') ?> · dostawa: <?= $fdate($so->date_delivery ?? $so->date_ship) ?>">
                                <i class="ri-truck-line me-1"></i><span class="small"><?= h($so->symbol ?? '—') ?></span>
                            </button>
                        <?php else: ?>
                            <div class="dropdown">
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary py-0 px-1 dropdown-toggle"
                                        data-bs-toggle="dropdown"
                                        title="<?= count($orders) ?> zlecenia">
                                    <i class="ri-truck-line me-1"></i><span class="small"><?= count($orders) ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <?php foreach ($orders as $so): ?>
                                        <li>
                                            <button type="button"
                                                    class="dropdown-item small btn-view-order-modal"
                                                    data-order-id="<?= h($so->id) ?>">
                                                <i class="ri-truck-line me-1 text-muted"></i>
                                                <?= h($so->symbol ?? '—') ?>
                                                <span class="text-muted ms-1"><?= $fdate($so->date_delivery ?? $so->date_ship) ?></span>
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </td>
                    <!-- Akcje -->
                    <td class="pe-3 text-end">
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                            <!-- Dodaj wpłatę -->
                            <button type="button"
                                    class="btn btn-sm btn-outline-success"
                                    title="Dodaj wpłatę"
                                    data-bs-toggle="modal"
                                    data-bs-target="#paymentModal"
                                    data-invoice-id="<?= h($invoice->id) ?>"
                                    data-invoice-number="<?= h($invoice->fullnumber) ?>"
                                    data-invoice-remaining="<?= h($invoice->remaining) ?>"
                                    data-invoice-currency="<?= h($currency) ?>">
                                <i class="ri-add-line"></i>
                            </button>
                            <!-- Pokaż fakturę -->
                            <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Invoices', 'action' => 'view', $invoice->id]) ?>"
                               class="btn btn-sm btn-outline-primary" title="Szczegóły faktury">
                                <i class="ri-eye-line"></i>
                            </a>
                            <?php if ($bt !== null && $bt->match_status === 'proposed'): ?>
                                <!-- Potwierdź przelew -->
                                <?= $this->Form->postLink(
                                    '<i class="ri-check-line"></i>',
                                    ['plugin' => false, 'controller' => 'BankTransactions', 'action' => 'confirmMatch', $bt->id],
                                    [
                                        'class'   => 'btn btn-sm btn-outline-warning',
                                        'escape'  => false,
                                        'title'   => 'Potwierdź dopasowanie przelewu',
                                        'data'    => ['redirect' => $this->Url->build($currentUrl())],
                                    ]
                                ) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Stopka tabeli: info + paginacja -->
    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-top gap-2">
        <div class="text-muted small">
            Wyświetlono <?= count($invoices) ?> z <?= $total ?> faktur
        </div>
        <?php if ($pages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $this->Url->build($currentUrl(['page' => max(1, $page - 1)])) ?>">‹</a>
                </li>
                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);
                if ($start > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= $this->Url->build($currentUrl(['page' => 1])) ?>">1</a></li>
                    <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->Url->build($currentUrl(['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($end < $pages): ?>
                    <?php if ($end < $pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= $this->Url->build($currentUrl(['page' => $pages])) ?>"><?= $pages ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $this->Url->build($currentUrl(['page' => min($pages, $page + 1)])) ?>">›</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; // lockSource !== 'legacy' ?>

<?php
// ── Sekcja faktur archiwalnych (legacy) ─────────────────────────────────────
if (!empty($legacyInvoices) || ($sourceFilter === 'legacy')):
    $legacyCurrentUrl = function (array $extra = []) use ($baseAction, $search, $status, $dateFrom, $dateTo, $typeFilter, $sourceFilter, $sort, $dir, $limit, $legacyPage): array {
        $base = [
            'q'         => $search,
            'status'    => $status,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'type'      => $typeFilter,
            'source'    => $sourceFilter,
            'sort'      => $sort,
            'dir'       => $dir,
            'limit'     => $limit,
            'lpage'     => $legacyPage,
        ];
        $merged = array_merge($base, $extra);
        $params = array_filter($merged, fn($v) => $v !== '' && $v !== null);
        return ['action' => $baseAction, '?' => $params];
    };
?>
<div class="mt-4">
    <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="mb-0 fw-semibold text-secondary">
            <i class="ri-archive-line me-1"></i>Faktury archiwalne
            <span class="badge bg-secondary ms-1"><?= $legacyTotal ?></span>
        </h6>
        <span class="text-muted small">— ze starego systemu (rejestr <?= !empty($lastSync) ? h($lastSync->rejestr) : '130' ?>)</span>
        <?php if ($lastSync !== null): ?>
            <span class="small text-muted fst-italic ms-auto">
                <i class="ri-time-line me-1"></i>Ostatnia sync:
                <?php
                $ls = $lastSync->synced_at;
                echo h($ls instanceof \DateTimeInterface ? $ls->format('d.m.Y H:i') : substr((string)$ls, 0, 16));
                ?>
            </span>
        <?php endif; ?>
    </div>

<?php if (empty($legacyInvoices)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-4">
            <i class="ri-archive-line fs-2 text-muted mb-2 d-block"></i>
            <p class="text-muted mb-2">Brak faktur archiwalnych w wybranym zakresie.</p>
            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#legacySyncModal">
                <i class="ri-refresh-line me-1"></i>Synchronizuj archiwum
            </button>
        </div>
    </div>
<?php else: ?>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle" id="legacy-rec-table">
            <thead class="table-secondary">
                <tr>
                    <th class="ps-3" style="min-width:140px">Nr faktury</th>
                    <th style="min-width:160px">Kontrahent</th>
                    <th class="text-center" style="width:70px">Waluta</th>
                    <th class="text-nowrap">Data</th>
                    <th class="text-nowrap">Termin</th>
                    <th class="text-end text-nowrap">Brutto</th>
                    <th class="text-end text-nowrap">Pozostało</th>
                    <th style="min-width:140px">Status</th>
                    <th style="min-width:100px">Teczka / ref.</th>
                    <th class="text-end pe-3" style="width:60px">Akcje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($legacyInvoices as $leg):
                $legState   = (string)($leg->paymentstate ?? 'unpaid');
                $legPdate   = null;
                if ($leg->paymentdate !== null) {
                    try { $legPdate = $leg->paymentdate->format('Y-m-d'); } catch (\Throwable $e) {
                        $s = substr((string)$leg->paymentdate, 0, 10);
                        $legPdate = $s ?: null;
                    }
                }
                // Fallback: oblicz termin z pola platnosc gdy paymentdate brak
                $displayPdate = $legPdate;
                if (!$displayPdate && !empty($leg->platnosc)) {
                    if (preg_match('/(\d+)\s*dni/i', $leg->platnosc, $m)) {
                        $days = (int)$m[1];
                        try { $dateStr = $leg->date->format('Y-m-d'); } catch (\Throwable $e) {
                            $dateStr = substr((string)$leg->date, 0, 10);
                        }
                        if (!empty($dateStr)) {
                            $calc = \DateTime::createFromFormat('Y-m-d', $dateStr);
                            if ($calc) {
                                $calc->modify("+{$days} days");
                                $displayPdate = $calc->format('Y-m-d');
                            }
                        }
                    }
                }
                $legEdok = !empty($leg->platnosc) && stripos($leg->platnosc, 'elektronicz') !== false;

                $legTotal   = (float)($leg->total ?? 0);
                $legRemain  = (float)($leg->remaining ?? 0);
                $legPaid    = (float)($leg->alreadypaid ?? 0);
                $legCur     = (string)($leg->currency ?? 'PLN');
                $legRate    = (float)($leg->exchange_rate ?? 0);
                $legNetto   = (float)($leg->netto ?? 0);
                $legRemainWal = (float)($leg->remaining_wal ?? 0);

                // Nadpłata: pozostające saldo ujemne
                $legIsOverpaid = $legCur !== 'PLN'
                    ? ($legRemainWal < -0.01)
                    : ($legRemain < -0.01);

                $rowClass = '';
                if ($legIsOverpaid) {
                    $rowClass = 'table-warning';
                } elseif ($legState !== 'paid' && $displayPdate && $displayPdate < $todayStr) {
                    $rowClass = 'table-danger';
                }
            ?>
                <tr class="<?= $rowClass ?>">
                    <!-- Nr faktury -->
                    <td class="ps-3">
                        <span class="fw-semibold text-dark">
                            <?= h($leg->fullnumber ?? '—') ?>
                        </span>
                        <span class="ms-1 badge bg-secondary" style="font-size:.65rem">Arch.</span>
                    </td>
                    <!-- Kontrahent -->
                    <td style="max-width:200px">
                        <div class="text-truncate small" title="<?= h($leg->contractor_name ?? '') ?>">
                            <?= h($leg->contractor_name ?? '—') ?>
                        </div>
                        <?php if (!empty($leg->contractor_nip)): ?>
                            <div class="text-muted" style="font-size:.7rem">NIP: <?= h($leg->contractor_nip) ?></div>
                        <?php endif; ?>
                    </td>
                    <!-- Waluta -->
                    <td class="text-center">
                        <span class="badge <?= $legCur !== 'PLN' ? 'bg-info' : 'bg-light text-dark border' ?>"><?= h($legCur) ?></span>
                        <?php if ($legCur !== 'PLN' && ($leg->total_wal ?? 0) > 0): ?>
                            <div class="text-muted" style="font-size:.65rem"><?= number_format((float)$leg->total_wal, 2, ',', ' ') ?></div>
                        <?php endif; ?>
                    </td>
                    <!-- Data -->
                    <td class="text-nowrap small text-muted"><?= $fdate($leg->date) ?></td>
                    <!-- Termin -->
                    <td class="text-nowrap small">
                        <?php if ($displayPdate): ?>
                            <?php
                            $legPast  = $displayPdate < $todayStr && $legState !== 'paid';
                            $legToday = $displayPdate === $todayStr && $legState !== 'paid';
                            $cls      = $legPast ? 'text-danger fw-semibold' : ($legToday ? 'text-warning fw-semibold' : 'text-muted');
                            ?>
                            <span class="<?= $cls ?>"><?= $fdate($displayPdate) ?></span>
                            <?php if ($legPast): ?>
                                <span class="ms-1 small text-danger">(<?= (int)(new \DateTime($displayPdate))->diff($today)->days ?> dni)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                        <?php if ($legEdok): ?>
                            <span class="badge bg-info-subtle text-info border border-info-subtle ms-1" style="font-size:.6rem" title="<?= h($leg->platnosc) ?>">e-dok</span>
                        <?php endif; ?>
                    </td>
                    <!-- Brutto -->
                    <td class="text-end text-nowrap small fw-semibold">
                        <?php if ($legCur !== 'PLN' && $legTotal > 0): ?>
                            <div><?= number_format($legTotal, 2, ',', ' ') ?> <?= h($legCur) ?></div>
                            <?php
                                $legVatPln  = ($legTotal - $legNetto) * $legRate;
                            ?>
                            <?php if ($legRate > 0): ?>
                                <div class="text-muted" style="font-size:.7rem"><?= number_format($legTotal * $legRate, 2, ',', ' ') ?> PLN</div>
                                <?php if ($legVatPln > 0.001): ?>
                                    <div class="text-muted" style="font-size:.7rem">VAT: <?= number_format($legVatPln, 2, ',', ' ') ?> PLN</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= number_format($legTotal, 2, ',', ' ') ?> PLN
                        <?php endif; ?>
                    </td>
                    <!-- Pozostało + lokalne wpłaty -->
                    <?php
                        $legLocalPayments = $legacyPaymentsByInvoiceId[(string)$leg->id] ?? [];
                        // VAT w walucie obcej (proporcja: legRemainWal jest netto, skalujemy do brutto)
                        $legVatWal = ($legCur !== 'PLN' && $legTotal > 0 && $legNetto > 0.001)
                            ? $legRemainWal * ($legTotal - $legNetto) / $legTotal
                            : 0.0;
                        // Gross EUR remaining = net_remaining * total / netto
                        // (legRemainWal to NET w walucie, legTotal/legNetto = gross/net dla pełnej faktury)
                        $legRemainBruttoWal = ($legCur !== 'PLN' && $legNetto > 0.001)
                            ? $legRemainWal * $legTotal / $legNetto
                            : 0.0;
                    ?>
                    <td class="text-end text-nowrap small">
                        <?php if ($legIsOverpaid): ?>
                            <span class="badge bg-danger text-white">Nadpłata</span>
                            <?php if ($legCur !== 'PLN'): ?>
                                <div class="text-danger fw-semibold" style="font-size:.8rem"><?= number_format(abs($legRemainBruttoWal ?: $legRemainWal), 2, ',', ' ') ?> <?= h($legCur) ?></div>
                            <?php else: ?>
                                <div class="text-danger fw-semibold" style="font-size:.8rem"><?= number_format(abs($legRemain), 2, ',', ' ') ?> PLN</div>
                            <?php endif; ?>
                        <?php elseif ($legState === 'paid'): ?>
                            <span class="text-success">0,00 <?= h($legCur !== 'PLN' ? $legCur : 'PLN') ?></span>
                        <?php else: ?>
                            <?php if ($legCur !== 'PLN' && $legRemainWal > 0): ?>
                                <div class="fw-semibold text-dark"><?= number_format($legRemainBruttoWal, 2, ',', ' ') ?> <?= h($legCur) ?></div>
                                <?php if ($legRemainWal > 0.001): ?>
                                    <div class="text-muted" style="font-size:.7rem">netto: <?= number_format($legRemainWal, 2, ',', ' ') ?> <?= h($legCur) ?></div>
                                <?php endif; ?>
                                <?php if ($legVatWal > 0.001): ?>
                                    <div class="text-muted" style="font-size:.7rem">VAT: <?= number_format($legVatWal, 2, ',', ' ') ?> <?= h($legCur) ?></div>
                                <?php endif; ?>
                                <?php if ($legRate > 0): ?>
                                    <div class="text-muted" style="font-size:.7rem"><?= number_format($legRemainWal * $legRate, 2, ',', ' ') ?> PLN</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="<?= $legRemain > 0 ? 'fw-semibold text-dark' : 'text-muted' ?>">
                                    <?= number_format($legRemain, 2, ',', ' ') ?> PLN
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($legPaid > 0 && !$legIsOverpaid && $legState !== 'paid'): ?>
                            <div class="text-muted" style="font-size:.7rem">wpłacono: <?= number_format($legPaid, 2, ',', ' ') ?> PLN</div>
                        <?php endif; ?>
                        <?php foreach ($legLocalPayments as $lp): ?>
                            <div class="d-flex align-items-center justify-content-end gap-1 mt-1">
                                <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:.67rem"
                                      title="Lokalna wpłata z <?= h($lp->payment_date) ?>">
                                    <i class="ri-money-euro-circle-line"></i>
                                    <?= number_format((float)$lp->amount, 2, ',', ' ') ?> PLN
                                </span>
                                <?= $this->Form->create(null, ['url' => ['action' => 'deleteLegacyPayment', $lp->id], 'method' => 'post', 'style' => 'display:inline']) ?>
                                <input type="hidden" name="redirect" value="<?= h($this->Url->build($currentUrl())) ?>">
                                <button type="submit" class="btn btn-link btn-sm p-0 text-danger"
                                        style="font-size:.8rem;line-height:1"
                                        onclick="return confirm('Usun\u0105\u0107 t\u0119 wp\u0142at\u0119?')"
                                        title="Usuń wpłatę">&#x2715;</button>
                                <?= $this->Form->end() ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <!-- Status -->
                    <td><?= $paymentBadge($legState, $displayPdate, $todayStr) ?></td>
                    <!-- Teczka / referencja -->
                    <td class="small text-muted" style="min-width:100px">
                        <?php if (!empty($leg->teczka)): ?>
                            <div><i class="ri-folder-user-line me-1"></i><?= h($leg->teczka) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($leg->glo_tyt1)): ?>
                            <div style="font-size:.7rem"><?= h($leg->glo_tyt1) ?></div>
                        <?php endif; ?>
                        <?php if (empty($leg->teczka) && empty($leg->glo_tyt1)): ?>
                            —
                        <?php endif; ?>
                    </td>
                    <!-- Akcje -->
                    <td class="text-end pe-3">
                        <?php if ($legState !== 'paid'): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-success"
                                data-bs-toggle="modal"
                                data-bs-target="#paymentModal"
                                data-invoice-source="legacy"
                                data-invoice-id="<?= h($leg->id) ?>"
                                data-invoice-number="<?= h($leg->fullnumber) ?>"
                                data-invoice-remaining="<?= h($legRemain) ?>"
                                data-invoice-remaining-wal="<?= h(round($legCur !== 'PLN' && $legNetto > 0.001 ? $legRemainBruttoWal : (float)($leg->remaining_wal ?? 0), 2)) ?>"
                                data-invoice-currency="<?= h($legCur) ?>"
                                title="Dodaj wpłatę archiwalną">
                            <i class="ri-add-circle-line"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Stopka: info + paginacja legacy -->
    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-top gap-2">
        <div class="text-muted small">
            Archiwum: wyświetlono <?= count($legacyInvoices) ?> z <?= $legacyTotal ?> dokumentów
        </div>
        <?php if ($legacyPages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $legacyPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $this->Url->build($legacyCurrentUrl(['lpage' => max(1, $legacyPage - 1)])) ?>">‹</a>
                </li>
                <?php
                $lStart = max(1, $legacyPage - 2);
                $lEnd   = min($legacyPages, $legacyPage + 2);
                for ($i = $lStart; $i <= $lEnd; $i++):
                ?>
                    <li class="page-item <?= $i === $legacyPage ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->Url->build($legacyCurrentUrl(['lpage' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $legacyPage >= $legacyPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $this->Url->build($legacyCurrentUrl(['lpage' => min($legacyPages, $legacyPage + 1)])) ?>">›</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Modal: Synchronizacja faktur archiwalnych (legacy API)
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="legacySyncModal" tabindex="-1" aria-labelledby="legacySyncModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="legacySyncModalLabel">
          <i class="ri-refresh-line me-2 text-warning"></i>Synchronizacja archiwum legacy
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">
          Pobiera faktury z zewnętrznego systemu (api nordlogis) i zapisuje je lokalnie.
          Dane z API są <strong>źródłem prawdy</strong> — nadpisują istniejące rekordy.
          Zmiany stanu płatności są logowane.
        </p>

        <div class="row g-2">
          <div class="col-4">
            <label class="form-label small fw-semibold">Rejestr</label>
            <select id="syncRejestr" class="form-select form-select-sm">
              <option value="130">130 — FSK</option>
              <option value="131">131</option>
              <option value="132">132</option>
              <option value="133">133</option>
              <option value="134">134</option>
            </select>
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold">Rok</label>
            <select id="syncRok" class="form-select form-select-sm">
              <?php for ($y = (int)date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $y === (int)date('Y') ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold">Miesiąc</label>
            <select id="syncMc" class="form-select form-select-sm">
              <option value="">— cały rok —</option>
              <?php
              $miesice = ['01'=>'Styczeń','02'=>'Luty','03'=>'Marzec','04'=>'Kwiecień','05'=>'Maj','06'=>'Czerwiec',
                          '07'=>'Lipiec','08'=>'Sierpień','09'=>'Wrzesień','10'=>'Październik','11'=>'Listopad','12'=>'Grudzień'];
              foreach ($miesice as $num => $name):
              ?>
                <option value="<?= $num ?>" <?= $num == date('m') ? 'selected' : '' ?>>
                  <?= $num ?> — <?= $name ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Wynik synchronizacji -->
        <div id="syncResult" class="mt-3" style="display:none"></div>

        <?php if ($lastSync !== null): ?>
        <div class="mt-3 p-2 bg-light rounded border small">
          <strong>Ostatnia synchronizacja:</strong>
          <?php
          $ls    = $lastSync->synced_at;
          $lsStr = $ls instanceof \DateTimeInterface ? $ls->format('d.m.Y H:i') : substr((string)$ls, 0, 16);
          ?>
          <?= h($lsStr) ?> —
          rejestr <?= h($lastSync->rejestr) ?>,
          <?= $lastSync->rok ?>/<?= $lastSync->mc ? str_pad($lastSync->mc, 2, '0', STR_PAD_LEFT) : 'cały rok' ?>,
          pobrano <?= (int)$lastSync->records_fetched ?> dok.,
          <?php if ($lastSync->status === 'error'): ?>
            <span class="text-danger"><i class="ri-error-warning-line me-1"></i>Błąd: <?= h($lastSync->error_message ?? '') ?></span>
          <?php else: ?>
            <span class="text-success"><i class="ri-checkbox-circle-line me-1"></i>OK</span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Zamknij</button>
        <button type="button" id="btnRunSync" class="btn btn-warning btn-sm">
          <i class="ri-refresh-line me-1"></i>Synchronizuj
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
    'use strict';
    var btn     = document.getElementById('btnRunSync');
    var result  = document.getElementById('syncResult');
    var csrfMeta = document.querySelector('meta[name="csrfToken"]');
    var csrfInput = document.querySelector('input[name="_csrfToken"]');
    var csrf    = (csrfMeta && csrfMeta.content) || (csrfInput && csrfInput.value) || '';

    if (!btn) return;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Synchronizuję…';
        result.style.display = 'none';

        var rejestr = document.getElementById('syncRejestr').value;
        var rok     = document.getElementById('syncRok').value;
        var mc      = document.getElementById('syncMc').value;

        var body    = new URLSearchParams();
        body.append('rejestr', rejestr);
        body.append('rok', rok);
        body.append('mc', mc);

        fetch('<?= $this->Url->build(['controller' => 'Reconciliations', 'action' => 'syncLegacy']) ?>', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line me-1"></i>Synchronizuj';
            result.style.display = '';

            if (data.error) {
                result.innerHTML = '<div class="alert alert-danger small py-2">'
                    + '<i class="ri-error-warning-line me-2"></i>' + data.error + '</div>';
                return;
            }

            var changesHtml = '';
            if (data.changed > 0) {
                changesHtml = '<div class="mt-2"><strong class="small">Zmiany stanu płatności (' + data.changed + '):</strong>'
                    + '<ul class="mb-0 small">';
                (data.changes || []).forEach(function (c) {
                    changesHtml += '<li><code>' + c.fullnumber + '</code>: '
                        + '<span class="badge bg-secondary me-1">' + c.from + '</span>'
                        + '→ <span class="badge bg-primary">' + c.to + '</span>'
                        + ' (pozostało: ' + parseFloat(c.remaining || 0).toFixed(2) + ' PLN)</li>';
                });
                changesHtml += '</ul></div>';
            }

            result.innerHTML = '<div class="alert alert-success small py-2">'
                + '<i class="ri-checkbox-circle-line me-2"></i>' + data.message
                + changesHtml
                + '</div>';

            // Odśwież stronę po 2s
            setTimeout(function () { location.reload(); }, 2000);
        })
        .catch(function (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-refresh-line me-1"></i>Synchronizuj';
            result.style.display = '';
            result.innerHTML = '<div class="alert alert-danger small py-2">'
                + '<i class="ri-error-warning-line me-2"></i>Błąd połączenia: ' + err.message + '</div>';
        });
    });
}());
</script>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Modal: Rozlicz fakturę (przelewy + ręczna wpłata)
════════════════════════════════════════════════════════════════════════════ -->
<style>
#paymentModal .modal-content { border-radius: .75rem; overflow: hidden; }
#paymentModal .tx-col { display: flex; flex-direction: column; min-height: 0; max-height: 72vh; }
#paymentModal .tx-col-body { overflow-y: auto; flex: 1 1 0; }
#paymentModal .pay-col { background: #f8fafc; border-left: 1px solid #dee2e6; }
#bankTxFilterBar { background: #fff; border: 1px solid #dee2e6; border-radius: .5rem; padding: .45rem .6rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
#bankTxFilterBar .form-control, #bankTxFilterBar .form-select { border: none; box-shadow: none; background: transparent; font-size: .82rem; }
#bankTxFilterBar .form-control:focus, #bankTxFilterBar .form-select:focus { outline: none; box-shadow: none; }
#bankTxFilterBar .divider { width: 1px; background: #dee2e6; align-self: stretch; margin: 0 .3rem; }
.sort-btn { background: none; border: 1px solid transparent; border-radius: .35rem; padding: 2px 7px; font-size: .78rem; cursor: pointer; color: #6c757d; transition: all .15s; white-space: nowrap; }
.sort-btn:hover { background: #e9ecef; color: #212529; }
.sort-btn.active { background: #e8f0fe; border-color: #93b8fb; color: #1a56db; font-weight: 600; }
#bankTxTable thead th { font-size: .78rem; font-weight: 600; letter-spacing: .02em; color: #6c757d; text-transform: uppercase; border-bottom: 2px solid #dee2e6; padding: .4rem .5rem; }
#bankTxTable tbody tr:hover { background: #f8f9ff; }
#bankTxTable tbody td { padding: .35rem .5rem; vertical-align: middle; }
</style>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header px-4 py-3 border-bottom">
                <div class="d-flex align-items-center gap-3 flex-grow-1 min-width-0">
                    <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px">
                        <i class="ri-bank-card-line text-primary"></i>
                    </div>
                    <div class="min-width-0">
                        <div class="fw-semibold lh-1" id="paymentModalLabel">Rozlicz fakturę</div>
                        <div class="text-muted small text-truncate mt-1" id="modalInvoiceName" style="max-width:400px">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close ms-3" data-bs-dismiss="modal"></button>
            </div>

            <?= $this->Form->create(null, [
                'url'    => ['action' => 'addPayment'],
                'method' => 'post',
                'id'     => 'paymentForm',
            ]) ?>

            <!-- Body: 2 kolumny -->
            <div class="modal-body p-0">
                <input type="hidden" name="invoice_id" id="modalInvoiceId">
                <input type="hidden" name="legacy_invoice_id" id="modalLegacyInvoiceId">
                <input type="hidden" name="redirect" value="<?= h($this->Url->build($currentUrl())) ?>">

                <div class="row g-0" style="min-height:420px">

                    <!-- ── Lewa: przelewy ───────────────────────────────── -->
                    <div class="col-lg-7 tx-col">
                        <div class="px-4 pt-3 pb-2 border-bottom bg-white d-flex align-items-center gap-2 flex-shrink-0">
                            <i class="ri-swap-line text-primary fs-6"></i>
                            <span class="fw-semibold small">Przelewy kontrahenta</span>
                            <span id="bankTxSpinner" class="spinner-border spinner-border-sm text-muted ms-1" role="status" style="display:none!important"></span>
                            <span class="badge bg-primary-subtle text-primary rounded-pill ms-auto small px-2" id="bankTxHeaderCount">—</span>
                        </div>
                        <div class="tx-col-body px-3 py-2" id="bankTxSection">
                            <div class="text-muted small fst-italic py-3 text-center">
                                <span class="spinner-border spinner-border-sm me-2"></span>Ładowanie przelewów…
                            </div>
                        </div>
                    </div>

                    <!-- ── Prawa: ręczna wpłata ─────────────────────────── -->
                    <div class="col-lg-5 pay-col d-flex flex-column">
                        <div class="px-4 pt-3 pb-2 border-bottom d-flex align-items-center gap-2 flex-shrink-0">
                            <i class="ri-pencil-line text-success fs-6"></i>
                            <span class="fw-semibold small">Ręczna wpłata</span>
                        </div>
                        <div class="px-4 py-3 flex-grow-1">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.04em">Kwota <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="ri-money-euro-circle-line text-muted"></i></span>
                                    <input type="number" step="0.01" min="0.01" name="amount" id="modalAmount"
                                           class="form-control" required placeholder="0,00">
                                </div>
                                <div class="form-text small mt-1" id="modalRemaining"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.04em">Data wpłaty</label>
                                <input type="date" name="payment_date" id="modalPaymentDate"
                                       class="form-control"
                                       value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.04em">Metoda</label>
                                <select name="payment_method" class="form-select">
                                    <option value="transfer">💳 Przelew bankowy</option>
                                    <option value="cash">💵 Gotówka</option>
                                    <option value="card">💳 Karta płatnicza</option>
                                    <option value="other">Inne</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.04em">Opis</label>
                                <input type="text" name="description" class="form-control"
                                       placeholder="np. nr referencyjny, uwagi…">
                            </div>
                        </div>
                        <div class="px-4 py-3 border-top bg-white d-flex gap-2 justify-content-end flex-shrink-0">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="ri-save-line me-1"></i>Zapisz wpłatę
                            </button>
                        </div>
                    </div>

                </div><!-- /row -->
            </div><!-- /modal-body -->
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<!-- Ukryty formularz do powiązania transakcji bankowej z fakturą -->
<?= $this->Form->create(null, [
    'id'     => 'linkTxForm',
    'method' => 'post',
    'url'    => '#',
    'style'  => 'display:none',
]) ?>
<input type="hidden" name="invoice_id" id="linkTxInvoiceId">
<input type="hidden" name="redirect"   value="<?= h($this->Url->build($currentUrl())) ?>">
<?= $this->Form->end() ?>

<script>
(function () {
    'use strict';

    var currentInvoiceId = null;

    // Rachunki bankowe firmy — do etykiet w filtrze konta
    var companyBankAccounts = <?= json_encode(array_map(function ($cba) {
        $iban = preg_replace('/[\s\-]/', '', $cba->iban ?? '');
        return [
            'iban'      => $iban,
            'raw_iban'  => $cba->iban ?? '',
            'label'     => $cba->label ?: ($cba->bank_name ?: ''),
            'currency'  => $cba->currency ?? 'PLN',
            'is_default'=> (bool)$cba->is_default,
        ];
    }, $companyBankAccounts ?? []), JSON_UNESCAPED_UNICODE) ?>;

    function getBankLabel(accountNumber) {
        if (!accountNumber) return '';
        var clean = accountNumber.replace(/[\s\-]/g, '');
        for (var i = 0; i < companyBankAccounts.length; i++) {
            var cba = companyBankAccounts[i];
            if (cba.iban === clean || cba.raw_iban === accountNumber) {
                return (cba.label || '') + (cba.label ? ' · ' : '') + clean.slice(-8) + ' · ' + cba.currency;
            }
        }
        return clean ? ('…' + clean.slice(-8)) : '';
    }

    // ── helpers ───────────────────────────────────────────────────────────────
    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtAmount(v) {
        return parseFloat(v || 0).toFixed(2).replace('.', ',');
    }

    // ── Renderowanie jednego wiersza transakcji ───────────────────────────────
    function renderTxRow(tx, isLinked, isLegacy) {
        var statusBadge, actionCol;

        if (isLinked) {
            statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle">'
                        + '<i class="ri-check-line me-1"></i>Powiązany</span>';
            actionCol   = '<span class="text-success"><i class="ri-checkbox-circle-line"></i></span>';
        } else if (isLegacy) {
            if (tx.amount_match) {
                statusBadge = '<span class="badge bg-success text-white border border-success">'
                            + '<i class="ri-checkbox-circle-line me-1"></i>Kwota pasuje</span>';
            } else {
                var diffTxt = (tx.amount_diff != null && tx.amount_diff > 0)
                    ? ' <small class="opacity-75 fw-normal">Δ\u202f' + fmtAmount(tx.amount_diff) + '</small>' : '';
                statusBadge = '<span class="badge bg-secondary-subtle text-secondary border">Kandydat</span>' + diffTxt;
            }
            actionCol = '<button type="button" class="btn btn-sm btn-outline-primary py-0 btn-use-tx"'
                      + ' data-tx-amount="' + esc(tx.amount) + '"'
                      + ' data-tx-date="' + esc(tx.value_date) + '"'
                      + ' title="Wpisz tę kwotę i datę w formularz wpłaty">'
                      + '<i class="ri-arrow-left-up-line me-1"></i>Użyj</button>';
        } else if (tx.match_status === 'proposed') {
            statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">'
                        + '<i class="ri-alert-line me-1"></i>Sugerowany</span>';
            actionCol   = '<button type="button" class="btn btn-sm btn-success py-0 btn-link-tx"'
                        + ' data-tx-id="' + esc(tx.id) + '"'
                        + ' data-tx-amount="' + esc(tx.amount) + '"'
                        + ' data-tx-date="' + esc(tx.value_date) + '">'
                        + '<i class="ri-link me-1"></i>Powiąż</button>';
        } else {
            statusBadge = '<span class="badge bg-secondary-subtle text-secondary border">Wolny</span>';
            actionCol   = '<button type="button" class="btn btn-sm btn-outline-success py-0 btn-link-tx"'
                        + ' data-tx-id="' + esc(tx.id) + '"'
                        + ' data-tx-amount="' + esc(tx.amount) + '"'
                        + ' data-tx-date="' + esc(tx.value_date) + '">'
                        + '<i class="ri-link me-1"></i>Powiąż</button>';
        }

        var invTag = tx.parsed_inv
            ? '<span class="badge bg-light text-dark border me-1" title="Nr faktury z tytułu">'
              + esc(tx.parsed_inv) + '</span>'
            : '';

        var titleHtml = tx.title
            ? '<div class="text-muted text-truncate" style="font-size:.72em;max-width:200px" title="' + esc(tx.title) + '">'
              + '<i class="ri-file-text-line me-1 opacity-50"></i>' + esc(tx.title) + '</div>'
            : '';

        var accountHtml = tx.account_number
            ? '<div class="text-muted" style="font-size:.72em" title="Rachunek: ' + esc(tx.account_number) + '">'
              + '<i class="ri-bank-line me-1 opacity-50"></i>' + esc(getBankLabel(tx.account_number) || ('…' + tx.account_number.replace(/[\s\-]/g,'').slice(-8))) + '</div>'
            : '';

        var cleanAccount = (tx.account_number || '').replace(/[\s\-]/g, '');

        return '<tr data-account="' + esc(cleanAccount) + '">'
            + '<td class="text-nowrap small">' + esc(tx.value_date || '—') + '</td>'
            + '<td class="text-end text-nowrap small fw-semibold">' + fmtAmount(tx.amount) + '</td>'
            + '<td class="small" style="max-width:200px">'
            +   '<div class="text-truncate" title="' + esc(tx.party_name) + '">' + esc(tx.party_name || '—') + '</div>'
            +   titleHtml
            +   accountHtml
            + '</td>'
            + '<td class="small">' + invTag + statusBadge + '</td>'
            + '<td class="text-end">' + actionCol + '</td>'
            + '</tr>';
    }

    // ── Renderowanie sekcji przelewów ─────────────────────────────────────────
    function renderBankTransactions(data) {
        var container = document.getElementById('bankTxSection');
        var spinner   = document.getElementById('bankTxSpinner');
        if (spinner) spinner.style.display = 'none';

        var isLegacy = !!data.legacy;

        if (!data.nip && !data.contractor) {
            container.innerHTML = '<div class="alert alert-light py-2 small mb-0">'
                + '<i class="ri-information-line me-1 text-muted"></i>'
                + 'Brak danych kontrahenta — nie można automatycznie wyszukać przelewów.'
                + '</div>';
            return;
        }

        var linked     = data.linked     || [];
        var candidates = data.candidates || [];

        if (!linked.length && !candidates.length) {
            var searchKey = data.contractor || data.nip || '?';
            container.innerHTML = '<div class="text-muted small fst-italic py-1">'
                + '<i class="ri-search-line me-1"></i>'
                + 'Brak pasujących przelewów dla: <strong>' + esc(searchKey) + '</strong>'
                + '</div>';
            return;
        }

        var rows = '';
        var note = isLegacy
            ? '<div class="alert alert-info py-1 px-2 small mb-2 border"><i class="ri-archive-line me-1"></i>Faktura archiwalna — przelewy widoczne informacyjnie. Rozlicz przez lokalne wpłaty (przycisk + w tabeli).</div>'
            : '';

        if (isLegacy && (data.ref_amount > 0 || data.ref_amount_wal > 0)) {
            var refCur = data.ref_currency || 'PLN';
            var refDisplay;
            if (refCur !== 'PLN' && data.ref_amount_wal > 0) {
                refDisplay = '<strong class="text-dark">' + fmtAmount(data.ref_amount_wal) + '\u202f' + esc(refCur) + '</strong>'
                           + ' <span class="opacity-75">(' + fmtAmount(data.ref_amount) + '\u202fPLN)</span>';
            } else {
                refDisplay = '<strong class="text-dark">' + fmtAmount(data.ref_amount) + '\u202fPLN</strong>';
            }
            note += '<div class="d-flex align-items-center gap-2 small text-muted mb-2 px-1 border-start border-primary ps-2">'
                  + '<i class="ri-scales-line text-primary"></i>'
                  + 'Kwota referencyjna: ' + refDisplay
                  + ' — podświetlam przelewy z pasującą kwotą.</div>';
        }

        // ── Dane do filtrowania / sortowania ─────────────────────────────────
        var allTxsData = [];
        linked.forEach(function (tx)    { allTxsData.push({ tx: tx, isLinked: true,  isLegacy: isLegacy }); });
        candidates.forEach(function (tx){ allTxsData.push({ tx: tx, isLinked: false, isLegacy: isLegacy }); });

        var totalCount = allTxsData.length;

        // Unikalne konta
        var uniqueAccounts = [];
        var seenAccounts   = {};
        allTxsData.forEach(function (item) {
            var clean = (item.tx.account_number || '').replace(/[\s\-]/g, '');
            if (clean && !seenAccounts[clean]) {
                seenAccounts[clean] = true;
                uniqueAccounts.push({ clean: clean, raw: item.tx.account_number });
            }
        });

        // Stan sortowania (domyślnie: data desc)
        var sortCol = 'date';
        var sortAsc = false;

        // ── Buduj toolbar filtrów ─────────────────────────────────────────────
        var accountOpts = '<option value="">Wszystkie rachunki</option>';
        uniqueAccounts.forEach(function (acc) {
            accountOpts += '<option value="' + esc(acc.clean) + '">'
                + esc(getBankLabel(acc.raw) || ('…' + acc.clean.slice(-8))) + '</option>';
        });
        var hasAccounts = uniqueAccounts.length > 1 || companyBankAccounts.length > 0;

        container.innerHTML = note
            // ── Filter bar ──────────────────────────────────────────────────
            + '<div id="bankTxFilterBar" class="mb-2">'
            +   '<div class="d-flex align-items-center gap-0 flex-wrap">'
            // Szukaj
            +     '<div class="d-flex align-items-center flex-grow-1 pe-2" style="min-width:160px">'
            +       '<i class="ri-search-line text-muted me-2 flex-shrink-0" style="font-size:.85rem"></i>'
            +       '<input type="text" id="bankTxFilter" class="form-control" placeholder="Szukaj nadawcy, tytułu, daty…" autocomplete="off">'
            +     '</div>'
            +     '<div class="divider"></div>'
            // Kwota od–do
            +     '<div class="d-flex align-items-center gap-1 px-2">'
            +       '<i class="ri-money-euro-circle-line text-muted flex-shrink-0" style="font-size:.85rem" title="Zakres kwot"></i>'
            +       '<input type="number" id="bankTxAmtFrom" class="form-control text-end" placeholder="od" min="0" step="0.01" style="width:76px">'
            +       '<span class="text-muted px-1" style="font-size:.8rem">–</span>'
            +       '<input type="number" id="bankTxAmtTo" class="form-control" placeholder="do" min="0" step="0.01" style="width:76px">'
            +     '</div>'
            // Rachunek (jeśli dostępny)
            + (hasAccounts
                ? '<div class="divider"></div>'
                  + '<div class="d-flex align-items-center gap-1 px-2" style="min-width:140px">'
                  +   '<i class="ri-bank-line text-muted flex-shrink-0" style="font-size:.85rem"></i>'
                  +   '<select id="bankTxAccountFilter" class="form-select">' + accountOpts + '</select>'
                  + '</div>'
                : '')
            +     '<div class="divider"></div>'
            // Clear + count
            +     '<div class="d-flex align-items-center gap-2 ps-2">'
            +       '<button type="button" id="bankTxClearBtn" class="btn btn-sm btn-link text-danger p-0 lh-1" style="display:none;font-size:.78rem" title="Wyczyść filtry">'
            +         '<i class="ri-close-circle-line me-1"></i>wyczyść'
            +       '</button>'
            +       '<span class="badge bg-primary-subtle text-primary rounded-pill px-2" id="bankTxCount" style="font-size:.75rem">' + totalCount + '</span>'
            +     '</div>'
            +   '</div>'
            // Sortowanie
            +   '<div class="d-flex align-items-center gap-1 mt-2 pt-2 border-top">'
            +     '<span class="text-muted me-1" style="font-size:.72rem;letter-spacing:.03em;text-transform:uppercase">Sortuj:</span>'
            +     '<button type="button" id="sort-btn-date"   class="sort-btn active"><i class="ri-calendar-line me-1"></i>Data<span id="bth-date-icon" class="ms-1">↓</span></button>'
            +     '<button type="button" id="sort-btn-amount" class="sort-btn"><i class="ri-coins-line me-1"></i>Kwota<span id="bth-amount-icon" class="ms-1 opacity-50">⇅</span></button>'
            +   '</div>'
            + '</div>'
            // ── Tabela ────────────────────────────────────────────────────
            + '<div class="table-responsive">'
            + '<table class="table table-hover mb-0" id="bankTxTable">'
            + '<thead><tr>'
            + '<th>Data</th>'
            + '<th class="text-end">Kwota</th>'
            + '<th>Nadawca / Tytuł</th>'
            + '<th>Status</th>'
            + '<th></th>'
            + '</tr></thead>'
            + '<tbody id="bankTxTbody"></tbody>'
            + '</table></div>'
            + '<div id="bankTxNoResults" class="text-muted small fst-italic py-3 text-center" style="display:none">'
            + '<i class="ri-search-line me-1"></i>Brak wyników dla wybranych filtrów.'
            + '</div>';

        // ── Funkcja filtrowania + sortowania → re-render tbody ────────────────
        var filterInput   = document.getElementById('bankTxFilter');
        var accountFilter = document.getElementById('bankTxAccountFilter');
        var amtFrom       = document.getElementById('bankTxAmtFrom');
        var amtTo         = document.getElementById('bankTxAmtTo');
        var countBadge    = document.getElementById('bankTxCount');
        var headerCount   = document.getElementById('bankTxHeaderCount');
        var noResults     = document.getElementById('bankTxNoResults');
        var tbody         = document.getElementById('bankTxTbody');
        var clearBtn      = document.getElementById('bankTxClearBtn');
        var sortBtnDate   = document.getElementById('sort-btn-date');
        var sortBtnAmt    = document.getElementById('sort-btn-amount');

        function updateSortButtons() {
            if (sortBtnDate) {
                sortBtnDate.className = 'sort-btn' + (sortCol === 'date' ? ' active' : '');
                var di = document.getElementById('bth-date-icon');
                if (di) { di.textContent = sortCol === 'date' ? (sortAsc ? '↑' : '↓') : '⇅'; di.className = 'ms-1' + (sortCol !== 'date' ? ' opacity-50' : ''); }
            }
            if (sortBtnAmt) {
                sortBtnAmt.className = 'sort-btn' + (sortCol === 'amount' ? ' active' : '');
                var ai = document.getElementById('bth-amount-icon');
                if (ai) { ai.textContent = sortCol === 'amount' ? (sortAsc ? '↑' : '↓') : '⇅'; ai.className = 'ms-1' + (sortCol !== 'amount' ? ' opacity-50' : ''); }
            }
        }

        function applyTxFilters() {
            var q       = filterInput   ? filterInput.value.toLowerCase().trim() : '';
            var account = accountFilter ? accountFilter.value : '';
            var minAmt  = amtFrom && amtFrom.value !== '' ? parseFloat(amtFrom.value) : null;
            var maxAmt  = amtTo   && amtTo.value   !== '' ? parseFloat(amtTo.value)   : null;
            var hasFilter = q || account || minAmt !== null || maxAmt !== null;

            // Badge "wyczyść"
            if (clearBtn) clearBtn.style.display = hasFilter ? '' : 'none';

            // Filtruj
            var filtered = allTxsData.filter(function (item) {
                var tx = item.tx;
                var cleanAcc = (tx.account_number || '').replace(/[\s\-]/g, '');
                var searchText = [tx.value_date, tx.party_name, tx.title, tx.parsed_inv, cleanAcc].join(' ').toLowerCase();
                return (!q       || searchText.indexOf(q) !== -1)
                    && (!account || cleanAcc === account)
                    && (minAmt === null || tx.amount >= minAmt)
                    && (maxAmt === null || tx.amount <= maxAmt);
            });

            // Sortuj
            filtered.sort(function (a, b) {
                var va = sortCol === 'amount' ? a.tx.amount : a.tx.value_date;
                var vb = sortCol === 'amount' ? b.tx.amount : b.tx.value_date;
                if (va < vb) return sortAsc ? -1 : 1;
                if (va > vb) return sortAsc ?  1 : -1;
                return 0;
            });

            // Re-render
            var html = '';
            filtered.forEach(function (item) { html += renderTxRow(item.tx, item.isLinked, item.isLegacy); });
            if (tbody) tbody.innerHTML = html;

            var n = filtered.length;
            if (countBadge) countBadge.textContent = n;
            if (headerCount) headerCount.textContent = n + ' / ' + totalCount;
            if (noResults)   noResults.style.display = (n === 0) ? '' : 'none';

            wireTxButtons();
        }

        // Sortowanie — przyciski
        if (sortBtnDate) sortBtnDate.addEventListener('click', function () {
            if (sortCol === 'date') { sortAsc = !sortAsc; } else { sortCol = 'date'; sortAsc = false; }
            updateSortButtons(); applyTxFilters();
        });
        if (sortBtnAmt) sortBtnAmt.addEventListener('click', function () {
            if (sortCol === 'amount') { sortAsc = !sortAsc; } else { sortCol = 'amount'; sortAsc = false; }
            updateSortButtons(); applyTxFilters();
        });

        // Clear all
        if (clearBtn) clearBtn.addEventListener('click', function () {
            if (filterInput)   filterInput.value   = '';
            if (accountFilter) accountFilter.value = '';
            if (amtFrom)       amtFrom.value       = '';
            if (amtTo)         amtTo.value         = '';
            applyTxFilters();
        });

        if (filterInput)   filterInput.addEventListener('input',  applyTxFilters);
        if (accountFilter) accountFilter.addEventListener('change', applyTxFilters);
        if (amtFrom)       amtFrom.addEventListener('input',  applyTxFilters);
        if (amtTo)         amtTo.addEventListener('input',    applyTxFilters);

        // Pierwszy render
        updateSortButtons();
        applyTxFilters();
        if (filterInput) filterInput.focus();

        function wireTxButtons() {
            if (!isLegacy) {
                container.querySelectorAll('.btn-link-tx').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var txId = this.dataset.txId;
                        var form = document.getElementById('linkTxForm');
                        document.getElementById('linkTxInvoiceId').value = currentInvoiceId;
                        form.action = '/wyciagi/confirm-match/' + txId;
                        form.submit();
                    });
                });
            }
            container.querySelectorAll('.btn-use-tx').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var amtField  = document.getElementById('modalAmount');
                    var dateField = document.getElementById('modalPaymentDate');
                    if (amtField)  amtField.value  = parseFloat(this.dataset.txAmount).toFixed(2);
                    if (dateField) dateField.value  = this.dataset.txDate;
                    if (amtField)  amtField.focus();
                });
            });
        }
    }

    // ── Pobieranie przelewów po otwarciu modala ───────────────────────────────
    function loadBankTransactions(invoiceId) {
        var container = document.getElementById('bankTxSection');
        var spinner   = document.getElementById('bankTxSpinner');
        container.innerHTML = '<div class="text-muted small fst-italic">Ładowanie przelewów…</div>';
        if (spinner) spinner.style.removeProperty('display');

        fetch('/rozliczenia/bank-transactions/' + invoiceId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(renderBankTransactions)
        .catch(function () {
            if (spinner) spinner.style.display = 'none';
            document.getElementById('bankTxSection').innerHTML =
                '<div class="text-danger small"><i class="ri-error-warning-line me-1"></i>'
                + 'Nie udało się załadować przelewów.</div>';
        });
    }

    function loadLegacyBankTransactions(legacyInvoiceId) {
        var container = document.getElementById('bankTxSection');
        var spinner   = document.getElementById('bankTxSpinner');
        container.innerHTML = '<div class="text-muted small fst-italic">Ładowanie przelewów kontrahenta…</div>';
        if (spinner) spinner.style.removeProperty('display');

        fetch('/rozliczenia/legacy-bank-transactions/' + legacyInvoiceId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(renderBankTransactions)
        .catch(function () {
            if (spinner) spinner.style.display = 'none';
            document.getElementById('bankTxSection').innerHTML =
                '<div class="text-danger small"><i class="ri-error-warning-line me-1"></i>'
                + 'Nie udało się załadować przelewów.</div>';
        });
    }

    // ── Inicjalizacja modala ──────────────────────────────────────────────────
    var urlAddPayment       = '<?= $this->Url->build(['action' => 'addPayment']) ?>';
    var urlAddLegacyPayment = '<?= $this->Url->build(['action' => 'addLegacyPayment']) ?>';

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('paymentModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function (event) {
            var btn       = event.relatedTarget;
            var source    = btn.dataset.invoiceSource || 'system';
            currentInvoiceId    = btn.dataset.invoiceId;
            var number         = btn.dataset.invoiceNumber;
            var remaining      = parseFloat(btn.dataset.invoiceRemaining || '0');    // zawsze PLN
            var remainingWal   = parseFloat(btn.dataset.invoiceRemainingWal || '0'); // waluta obca
            var currency       = btn.dataset.invoiceCurrency || 'PLN';

            var form         = document.getElementById('paymentForm');
            var bankSection  = document.getElementById('bankTxSection');

            document.getElementById('modalInvoiceName').textContent = number || '—';

            // Tekst pomocniczy "Pozostało" + pre-fill kwoty
            var remainingEl = document.getElementById('modalRemaining');
            var amountField = document.getElementById('modalAmount');

            if (remaining <= 0 && remainingWal <= 0) {
                remainingEl.textContent = 'Faktura opłacona w całości';
                amountField.value = '';
            } else if (currency !== 'PLN' && remainingWal > 0) {
                // Dla faktur walutowych: pokazuj kwotę w walucie faktury.
                // Pola remaining/total w legacy nie są wiarygodnymi wartościami PLN.
                remainingEl.innerHTML = 'Pozostało: <strong>' + fmtAmount(remainingWal) + '\u00a0' + currency + '</strong>';
                amountField.value = remainingWal.toFixed(2);
            } else {
                remainingEl.textContent = 'Pozostało: ' + fmtAmount(remaining) + '\u00a0PLN';
                amountField.value = remaining > 0 ? remaining.toFixed(2) : '';
            }

            if (source === 'legacy') {
                form.action = urlAddLegacyPayment;
                document.getElementById('modalInvoiceId').value       = '';
                document.getElementById('modalLegacyInvoiceId').value = currentInvoiceId;
                loadLegacyBankTransactions(currentInvoiceId);
            } else {
                form.action = urlAddPayment;
                document.getElementById('modalInvoiceId').value       = currentInvoiceId;
                document.getElementById('modalLegacyInvoiceId').value = '';
                loadBankTransactions(currentInvoiceId);
            }
        });
    });
}());
</script>

<!-- ── Modal podglądu zlecenia Speed (identyczny mechanizm jak na liście zleceń) ── -->
<div class="modal fade" id="orderViewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header py-2 border-bottom">
        <h6 class="modal-title flex-grow-1 text-truncate me-3" id="orderViewModalTitle">
          <i class="ri-eye-line me-1"></i>Podgląd zlecenia
        </h6>
        <a href="#" id="orderViewModalLink" class="btn btn-sm btn-outline-primary me-2 flex-shrink-0" target="_blank" title="Otwórz w nowej karcie">
          <i class="ri-external-link-line me-1"></i>Pełny widok
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body p-0" id="orderViewModalBody" style="overflow-y:auto">
        <div class="d-flex justify-content-center align-items-center" style="min-height:300px">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Ładowanie…</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
    const viewModalEl = document.getElementById('orderViewModal');
    const viewModal   = new bootstrap.Modal(viewModalEl);
    const modalBody   = document.getElementById('orderViewModalBody');
    const modalTitle  = document.getElementById('orderViewModalTitle');
    const modalLink   = document.getElementById('orderViewModalLink');
    const baseViewUrl = '<?= $this->Url->build(['plugin' => false, 'controller' => 'SpeedOrders', 'action' => 'viewModal', '__ID__']) ?>'.replace('__ID__', '');
    const fullViewUrl = '<?= $this->Url->build(['plugin' => false, 'controller' => 'SpeedOrders', 'action' => 'view', '__ID__']) ?>'.replace('__ID__', '');

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-view-order-modal');
        if (!btn) return;
        e.preventDefault();
        const orderId = btn.dataset.orderId;
        if (!orderId) return;

        modalBody.innerHTML = '<div class="d-flex justify-content-center align-items-center" style="min-height:300px">'
            + '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie…</span></div></div>';
        modalTitle.innerHTML = '<i class="ri-eye-line me-1"></i>Ładowanie…';
        modalLink.href = fullViewUrl + orderId;
        viewModal.show();

        fetch(baseViewUrl + orderId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(function (html) {
                modalBody.innerHTML = '<div class="p-3">' + html + '</div>';
                modalTitle.innerHTML = '<i class="ri-eye-line me-1"></i>Podgląd zlecenia';
                modalBody.querySelectorAll('script').forEach(function (old) {
                    const s = document.createElement('script');
                    old.src ? (s.src = old.src) : (s.textContent = old.textContent);
                    old.parentNode.replaceChild(s, old);
                });
            })
            .catch(function (err) {
                modalBody.innerHTML = '<div class="alert alert-danger m-4">'
                    + '<i class="ri-error-warning-line me-2"></i>Nie udało się załadować zlecenia: ' + err.message + '</div>';
            });
    });

    viewModalEl.addEventListener('hidden.bs.modal', function () {
        modalBody.innerHTML = '';
    });
}());
</script>

<!-- ── Modal kontrahenta ─────────────────────────────────────────────────── -->
<div class="modal fade" id="contractorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="contractorModalTitle"><i class="ri-user-line me-1"></i>Kontrahent</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="contractorModalBody">
        <div class="d-flex justify-content-center py-4">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie…</span></div>
        </div>
      </div>
      <div class="modal-footer py-2" id="contractorModalFooter" style="display:none!important"></div>
    </div>
  </div>
</div>
<script>
(function () {
    const modalEl    = document.getElementById('contractorModal');
    const modal      = new bootstrap.Modal(modalEl);
    const body       = document.getElementById('contractorModalBody');
    const footer     = document.getElementById('contractorModalFooter');
    const title      = document.getElementById('contractorModalTitle');
    const infoUrl    = '<?= $this->Url->build(['controller' => 'Reconciliations', 'action' => 'contractorInfo', '__ID__']) ?>'.replace('__ID__', '');
    const createUrl  = '<?= $this->Url->build(['controller' => 'Reconciliations', 'action' => 'createContractorFromInvoice', '__ID__']) ?>'.replace('__ID__', '');
    const viewUrl    = '<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'view', '__ID__']) ?>'.replace('__ID__', '');
    const csrfToken  = document.querySelector('meta[name="csrfToken"]')?.content
                    || document.querySelector('input[name="_csrfToken"]')?.value || '';

    function row(label, value) {
        if (!value) return '';
        return '<tr><th class="text-muted fw-normal small pe-3" style="white-space:nowrap">' + label + '</th>'
             + '<td class="small">' + value + '</td></tr>';
    }

    function renderContractor(data) {
        const ic = data.invoice_contractor;
        const c  = data.contractor;

        title.innerHTML = '<i class="ri-user-line me-1"></i>' + (ic.name || 'Kontrahent');

        let html = '';

        if (c) {
            // Kontrahent znaleziony w bazie
            html += '<div class="alert alert-success py-2 small mb-3">'
                  + '<i class="ri-checkbox-circle-line me-1"></i>Kontrahent jest w bazie danych.'
                  + '</div>';
            html += '<table class="table table-sm table-borderless mb-0">';
            html += row('Nazwa',    c.name);
            html += row('NIP',      c.nip);
            html += row('E-mail',   c.email);
            html += row('Telefon',  c.phone);
            html += row('Adres',    [c.street, c.postal_code ? c.postal_code + ' ' + c.city : c.city].filter(Boolean).join(', '));
            html += row('Kraj',     c.country);
            html += '</table>';
            footer.style.removeProperty('display');
            footer.innerHTML = '<a href="' + viewUrl + c.id + '" target="_blank" class="btn btn-sm btn-outline-primary">'
                + '<i class="ri-external-link-line me-1"></i>Otwórz kartę kontrahenta</a>'
                + '<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Zamknij</button>';
        } else {
            // Kontrahent nie istnieje w bazie
            html += '<div class="alert alert-warning py-2 small mb-3">'
                  + '<i class="ri-error-warning-line me-1"></i>Kontrahent nie jest jeszcze w bazie danych.'
                  + '</div>';
            html += '<p class="small text-muted mb-2">Dane z faktury <strong>' + (data.fullnumber || '') + '</strong>:</p>';
            html += '<table class="table table-sm table-borderless mb-0">';
            html += row('Nazwa',   ic.name);
            html += row('NIP',     ic.nip);
            html += row('E-mail',  ic.email);
            html += row('Telefon', ic.phone);
            html += row('Adres',   [ic.street, ic.zip ? ic.zip + ' ' + ic.city : ic.city].filter(Boolean).join(', '));
            html += '</table>';
            footer.style.removeProperty('display');
            footer.innerHTML = '<button type="button" id="btnCreateContractor" class="btn btn-sm btn-success" data-invoice-id="' + data.invoice_id + '">'
                + '<i class="ri-user-add-line me-1"></i>Utwórz kontrahenta</button>'
                + '<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Zamknij</button>';
        }

        body.innerHTML = html;
    }

    // Otwórz modal po kliknięciu ikonki użytkownika
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-contractor-info');
        if (!btn) return;
        const invoiceId = btn.dataset.invoiceId;
        if (!invoiceId) return;

        body.innerHTML = '<div class="d-flex justify-content-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        footer.style.display = 'none';
        footer.innerHTML = '';
        title.innerHTML = '<i class="ri-user-line me-1"></i>Ładowanie…';
        modal.show();

        fetch(infoUrl + invoiceId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    body.innerHTML = '<div class="alert alert-danger small">' + data.error + '</div>';
                } else {
                    renderContractor(data);
                }
            })
            .catch(function (err) {
                body.innerHTML = '<div class="alert alert-danger small">Błąd: ' + err.message + '</div>';
            });
    });

    // Utwórz kontrahenta
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('#btnCreateContractor');
        if (!btn) return;
        const invoiceId = btn.dataset.invoiceId;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Tworzenie…';

        fetch(createUrl + invoiceId, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-user-add-line me-1"></i>Utwórz kontrahenta';
                body.innerHTML += '<div class="alert alert-danger small mt-2">' + data.error + '</div>';
            } else {
                body.innerHTML = '<div class="alert alert-success small"><i class="ri-checkbox-circle-line me-1"></i>'
                    + (data.already_existed ? 'Kontrahent już był w bazie.' : 'Kontrahent został dodany do bazy.') + '</div>';
                footer.innerHTML = '<a href="' + viewUrl + data.contractor_id + '" target="_blank" class="btn btn-sm btn-outline-primary">'
                    + '<i class="ri-external-link-line me-1"></i>Otwórz kartę kontrahenta</a>'
                    + '<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Zamknij</button>';
            }
        })
        .catch(function (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-user-add-line me-1"></i>Utwórz kontrahenta';
            body.innerHTML += '<div class="alert alert-danger small mt-2">Błąd: ' + err.message + '</div>';
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        body.innerHTML = '';
        footer.style.display = 'none';
        footer.innerHTML = '';
    });
}());
</script>

<script>
/* ── Rozliczenia: presety daty + localStorage ──────────────────────────── */
(function () {
    'use strict';

    // Presety daty — wypełniają pola date_from / date_to
    var dateFrom = document.getElementById('rec-date-from');
    var dateTo   = document.getElementById('rec-date-to');

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

    function applyPreset(preset) {
        var now   = new Date();
        var y     = now.getFullYear();
        var m     = now.getMonth(); // 0-based
        var from, to;

        switch (preset) {
            case 'this_month':
                from = new Date(y, m, 1);
                to   = new Date(y, m + 1, 0);
                break;
            case 'prev_month':
                from = new Date(y, m - 1, 1);
                to   = new Date(y, m, 0);
                break;
            case 'this_quarter':
                var q = Math.floor(m / 3);
                from  = new Date(y, q * 3, 1);
                to    = new Date(y, q * 3 + 3, 0);
                break;
            case 'prev_quarter':
                var pq = Math.floor(m / 3) - 1;
                var py = y;
                if (pq < 0) { pq = 3; py = y - 1; }
                from  = new Date(py, pq * 3, 1);
                to    = new Date(py, pq * 3 + 3, 0);
                break;
            case 'this_year':
                from = new Date(y, 0, 1);
                to   = new Date(y, 11, 31);
                break;
            default: return;
        }
        if (dateFrom) dateFrom.value = ymd(from);
        if (dateTo)   dateTo.value   = ymd(to);
    }

    document.querySelectorAll('.date-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyPreset(this.dataset.preset);
            // Highlight active preset
            document.querySelectorAll('.date-preset').forEach(function (b) {
                b.classList.remove('btn-secondary');
                b.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-secondary');
        });
    });

    // Collapse chevron
    var filterBody = document.getElementById('rec-filter-body');
    var chevron    = document.getElementById('rec-filter-chevron');
    if (filterBody && chevron) {
        filterBody.addEventListener('hide.bs.collapse', function () {
            chevron.className = 'ri-arrow-down-s-line';
        });
        filterBody.addEventListener('show.bs.collapse', function () {
            chevron.className = 'ri-arrow-up-s-line';
        });
    }

    // ── localStorage: zapamiętaj i przywróć stan formularza ──────────────
    var LS_KEY    = 'rec_filters_v1';
    var form      = document.getElementById('rec-filter-form');
    var hasParams = window.location.search.length > 1; // czy są params w URL

    function saveFilters() {
        if (!form) return;
        var data = {};
        form.querySelectorAll('input[name]:not([type=hidden]), select[name]').forEach(function (el) {
            data[el.name] = el.value;
        });
        try { localStorage.setItem(LS_KEY, JSON.stringify(data)); } catch (e) {}
    }

    function restoreFilters() {
        if (!form || hasParams) return; // URL już zawiera filtry — nie nadpisuj
        var raw  = null;
        try { raw = localStorage.getItem(LS_KEY); } catch (e) {}
        if (!raw) return;
        var data;
        try { data = JSON.parse(raw); } catch (e) { return; }
        form.querySelectorAll('input[name]:not([type=hidden]), select[name]').forEach(function (el) {
            if (data[el.name] !== undefined && data[el.name] !== '') {
                el.value = data[el.name];
            }
        });
    }

    // Przywróć przy ładowaniu (tylko gdy brak params w URL)
    restoreFilters();

    // Zapisz przy submit
    if (form) {
        form.addEventListener('submit', function () { saveFilters(); });
    }

    // Wyczyść localStorage gdy kliknięto "Wyczyść"
    document.querySelectorAll('a[href="<?= $this->Url->build(['action' => 'index']) ?>"]').forEach(function (a) {
        a.addEventListener('click', function () {
            try { localStorage.removeItem(LS_KEY); } catch (e) {}
        });
    });
}());
</script>

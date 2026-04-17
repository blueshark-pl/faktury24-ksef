<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Invoice[] $invoices
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
$this->assign('title', 'Rozliczenia');

$today = new \DateTime('today');

// ── Pomocniki formatowania ───────────────────────────────────────────────────
$fdate = function ($v): string {
    if (!$v) return '—';
    if ($v instanceof \DateTimeInterface) return $v->format('d.m.Y');
    $s = substr((string)$v, 0, 10);
    return $s ?: '—';
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

    if (!$invoiceDate instanceof \DateTimeInterface) {
        $invoiceDate = new \DateTime(substr((string)$invoiceDate, 0, 10));
    }
    if (!$paymentDate instanceof \DateTimeInterface) {
        $paymentDate = new \DateTime(substr((string)$paymentDate, 0, 10));
    }
    if (!$sentAt instanceof \DateTimeInterface) {
        $sentAt = new \DateTime(substr((string)$sentAt, 0, 19));
    }

    $paymentDays = (int)$invoiceDate->diff($paymentDate)->days;
    $due = clone $sentAt;
    $due->modify('+' . $paymentDays . ' days');
    return $due->format('Y-m-d');
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
$currentUrl = function (array $extra = []) use ($search, $status, $dateFrom, $dateTo, $typeFilter, $sort, $dir, $limit, $page): array {
    $base = [
        'q'         => $search,
        'status'    => $status,
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
        'type'      => $typeFilter,
        'sort'      => $sort,
        'dir'       => $dir,
        'limit'     => $limit,
        'page'      => $page,
    ];
    $merged = array_merge($base, $extra);
    $params = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return ['action' => 'index', '?' => $params];
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

// Nazwy typów faktur
$typeLabels = [
    'vat'        => 'FV VAT',
    'novat'      => 'FV',
    'currency'   => 'FV walut.',
    'proforma'   => 'Proforma',
    'advance'    => 'Zaliczk.',
    'final'      => 'Końcowa',
    'correction' => 'Korekta',
    'margin'     => 'Marża',
    'rental'     => 'Najem',
    'oss'        => 'OSS',
    'internal'   => 'Wewn.',
];
?>

<!-- Nagłówek -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">Rozliczenia <span class="text-muted fs-6 fw-normal">faktury · wpłaty · przelewy</span></h4>
    <div class="d-flex gap-2">
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

<!-- Filtry -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= $this->Url->build(['action' => 'index']) ?>" class="row g-2 align-items-end">
            <!-- Szukaj -->
            <div class="col-12 col-md-4">
                <input type="text" name="q" value="<?= h($search) ?>" class="form-control form-control-sm"
                       placeholder="Szukaj: numer faktury, kontrahent, NIP…">
            </div>
            <!-- Data od–do -->
            <div class="col-6 col-md-2">
                <input type="date" name="date_from" value="<?= h($dateFrom) ?>" class="form-control form-control-sm" title="Data wystawienia od">
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="date_to" value="<?= h($dateTo) ?>" class="form-control form-control-sm" title="Data wystawienia do">
            </div>
            <!-- Typ -->
            <div class="col-6 col-md-1">
                <select name="type" class="form-select form-select-sm">
                    <option value="">Typ</option>
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
            <!-- Limit -->
            <div class="col-4 col-md-1">
                <select name="limit" class="form-select form-select-sm">
                    <?php foreach ([25, 50, 100, 200] as $l): ?>
                        <option value="<?= $l ?>" <?= $limit == $l ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="sort" value="<?= h($sort) ?>">
            <input type="hidden" name="dir"  value="<?= h($dir) ?>">
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="ri-search-line me-1"></i>Szukaj</button>
                <?php if ($search !== '' || $status !== '' || $dateFrom !== '' || $dateTo !== '' || $typeFilter !== ''): ?>
                    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary ms-1">
                        <i class="ri-close-line"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Szybkie filtry statusu -->
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
                    <th class="pe-3 text-end" style="min-width:110px">Akcje</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($invoices as $invoice):
                $bt    = $bankByInvoice[(string)$invoice->id] ?? null;
                $state = $invoice->paymentstate ?? 'unpaid';

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
                    </td>
                    <!-- Kontrahent -->
                    <td class="text-truncate" style="max-width:200px" title="<?= h($contractorName) ?>">
                        <span class="small"><?= h($contractorName) ?></span>
                    </td>
                    <!-- Typ -->
                    <td class="text-center">
                        <span class="badge bg-secondary-subtle text-secondary border small">
                            <?= h($typeLabels[$invoice->type] ?? h($invoice->type ?? '')) ?>
                        </span>
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
                    </td>
                    <!-- Pozostało -->
                    <td class="text-end text-nowrap small">
                        <?php if ($state === 'paid'): ?>
                            <span class="text-success">0,00 <?= h($currency) ?></span>
                        <?php else: ?>
                            <span class="<?= (float)$invoice->remaining > 0 ? 'fw-semibold text-dark' : 'text-muted' ?>">
                                <?= number_format((float)$invoice->remaining, 2, ',', ' ') ?> <?= h($currency) ?>
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

<!-- ═══════════════════════════════════════════════════════════════════════════
     Modal: Rozlicz fakturę (przelewy + ręczna wpłata)
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">
                    <i class="ri-bank-card-line me-2 text-primary"></i>Rozlicz fakturę
                    <span id="modalInvoiceName" class="fw-normal text-muted fs-6 ms-2">—</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <?= $this->Form->create(null, [
                'url'    => ['action' => 'addPayment'],
                'method' => 'post',
                'id'     => 'paymentForm',
            ]) ?>
            <div class="modal-body pb-2">
                <input type="hidden" name="invoice_id" id="modalInvoiceId">
                <input type="hidden" name="redirect"   value="<?= h($this->Url->build($currentUrl())) ?>">

                <!-- ── Przelewy bankowe kontrahenta ─────────────────────── -->
                <div class="mb-1">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="fw-semibold small"><i class="ri-bank-line me-1 text-primary"></i>Przelewy bankowe kontrahenta</span>
                        <span id="bankTxSpinner" class="spinner-border spinner-border-sm text-muted" role="status" style="display:none!important"></span>
                    </div>
                    <div id="bankTxSection">
                        <div class="text-muted small fst-italic">Ładowanie…</div>
                    </div>
                </div>

                <hr class="my-3">

                <!-- ── Ręczna wpłata ─────────────────────────────────────── -->
                <p class="fw-semibold small text-muted mb-2">
                    <i class="ri-edit-line me-1"></i>Lub dodaj wpłatę ręcznie
                </p>

                <div class="row g-3">
                    <div class="col-7">
                        <label class="form-label fw-semibold small">Kwota <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="modalAmount"
                               class="form-control form-control-sm" required placeholder="0.00">
                        <div class="form-text" id="modalRemaining"></div>
                    </div>
                    <div class="col-5">
                        <label class="form-label fw-semibold small">Data wpłaty</label>
                        <input type="date" name="payment_date" id="modalPaymentDate"
                               class="form-control form-control-sm"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-6">
                        <label class="form-label fw-semibold small">Metoda</label>
                        <select name="payment_method" class="form-select form-select-sm">
                            <option value="transfer">Przelew</option>
                            <option value="cash">Gotówka</option>
                            <option value="card">Karta</option>
                            <option value="other">Inne</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Opis (opcjonalnie)</label>
                        <input type="text" name="description" class="form-control form-control-sm"
                               placeholder="np. nr referencyjny przelewu">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="ri-save-line me-1"></i>Zapisz ręczną wpłatę
                </button>
            </div>
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
    function renderTxRow(tx, isLinked) {
        var statusBadge, actionCol;

        if (isLinked) {
            statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle">'
                        + '<i class="ri-check-line me-1"></i>Powiązany</span>';
            actionCol   = '<span class="text-success"><i class="ri-checkbox-circle-line"></i></span>';
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

        return '<tr>'
            + '<td class="text-nowrap small">' + esc(tx.value_date || '—') + '</td>'
            + '<td class="text-end text-nowrap small fw-semibold">' + fmtAmount(tx.amount) + '</td>'
            + '<td class="small text-truncate" style="max-width:160px" title="' + esc(tx.party_name) + '">'
            +   esc(tx.party_name || '—') + '</td>'
            + '<td class="small">' + invTag + statusBadge + '</td>'
            + '<td class="text-end">' + actionCol + '</td>'
            + '</tr>';
    }

    // ── Renderowanie sekcji przelewów ─────────────────────────────────────────
    function renderBankTransactions(data) {
        var container = document.getElementById('bankTxSection');
        var spinner   = document.getElementById('bankTxSpinner');
        if (spinner) spinner.style.display = 'none';

        if (!data.nip) {
            container.innerHTML = '<div class="alert alert-light py-2 small mb-0">'
                + '<i class="ri-information-line me-1 text-muted"></i>'
                + 'Kontrahent nie ma przypisanego NIP-u — nie można automatycznie wyszukać przelewów.'
                + '</div>';
            return;
        }

        var linked     = data.linked     || [];
        var candidates = data.candidates || [];

        if (!linked.length && !candidates.length) {
            container.innerHTML = '<div class="text-muted small fst-italic py-1">'
                + '<i class="ri-search-line me-1"></i>'
                + 'Brak przelewów dla NIP: <strong>' + esc(data.nip) + '</strong>'
                + '</div>';
            return;
        }

        var rows = '';
        linked.forEach(function (tx) { rows += renderTxRow(tx, true); });
        candidates.forEach(function (tx) { rows += renderTxRow(tx, false); });

        container.innerHTML = '<div class="table-responsive">'
            + '<table class="table table-sm table-hover mb-0 align-middle" style="font-size:.82rem">'
            + '<thead class="table-light">'
            + '<tr>'
            + '<th>Data</th>'
            + '<th class="text-end">Kwota</th>'
            + '<th>Nadawca</th>'
            + '<th>Status</th>'
            + '<th></th>'
            + '</tr>'
            + '</thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table></div>';

        // Obsługa przycisku Powiąż
        container.querySelectorAll('.btn-link-tx').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var txId   = this.dataset.txId;
                var form   = document.getElementById('linkTxForm');
                document.getElementById('linkTxInvoiceId').value = currentInvoiceId;
                form.action = '/wyciagi/confirm-match/' + txId;
                form.submit();
            });
        });
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

    // ── Inicjalizacja modala ──────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('paymentModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function (event) {
            var btn       = event.relatedTarget;
            currentInvoiceId    = btn.dataset.invoiceId;
            var number    = btn.dataset.invoiceNumber;
            var remaining = parseFloat(btn.dataset.invoiceRemaining || '0');
            var currency  = btn.dataset.invoiceCurrency || 'PLN';

            document.getElementById('modalInvoiceId').value        = currentInvoiceId;
            document.getElementById('modalInvoiceName').textContent = number || '—';
            document.getElementById('modalAmount').value            = remaining > 0
                ? remaining.toFixed(2) : '';
            document.getElementById('modalRemaining').textContent   = remaining > 0
                ? 'Pozostało: ' + fmtAmount(remaining) + ' ' + currency
                : 'Faktura opłacona w całości';

            loadBankTransactions(currentInvoiceId);
        });
    });
}());
</script>

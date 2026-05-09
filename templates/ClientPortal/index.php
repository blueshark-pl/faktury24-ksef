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
 * @var array{count:int,sum_brutto:float|null,currency:string} $stats
 * @var string[]                                       $currencyOptions
 * @var string                                         $currentLocale
 */
$this->assign('title', __('Zlecenia transportowe'));

$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';
$fnum  = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';

// Ile filtrów jest aktywnych (oprócz q)?
$activeFilters = (int)($status !== '')
              + (int)($invState !== '')
              + (int)($cmrState !== '')
              + (int)($currency !== '')
              + (int)($dateFrom !== '')
              + (int)($dateTo !== '');

// Helper: link z zachowaniem aktualnych filtrów + nadpisanie
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
    <div class="d-flex align-items-center gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="<?= __('Język') ?>">
            <a href="<?= $this->Url->build(['action' => 'setLocale', 'pl']) ?>"
               class="btn btn-outline-secondary <?= $currentLocale === 'pl' ? 'active' : '' ?>" title="Polski">PL</a>
            <a href="<?= $this->Url->build(['action' => 'setLocale', 'en']) ?>"
               class="btn btn-outline-secondary <?= $currentLocale === 'en' ? 'active' : '' ?>" title="English">EN</a>
        </div>
    </div>
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

<!-- Pasek filtrów -->
<form method="get" action="<?= $this->Url->build(['action' => 'index']) ?>"
      class="card shadow-sm mb-3" id="filterForm">
    <input type="hidden" name="status" value="<?= h($status) ?>">
    <div class="card-body py-2 px-3">
        <div class="row g-2 align-items-center">
            <!-- Szukaj -->
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                    <input type="text" name="q" value="<?= h($q) ?>"
                           class="form-control" placeholder="<?= __('Szukaj: numer, tytuł, trasa…') ?>">
                </div>
            </div>
            <!-- Daty od/do -->
            <div class="col-md-2">
                <input type="date" name="date_from" value="<?= h($dateFrom) ?>"
                       class="form-control form-control-sm" title="<?= __('Data od') ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" value="<?= h($dateTo) ?>"
                       class="form-control form-control-sm" title="<?= __('Data do') ?>">
            </div>
            <!-- Waluta -->
            <div class="col-md-1">
                <select name="currency" class="form-select form-select-sm">
                    <option value=""><?= __('Waluta') ?></option>
                    <?php foreach ($currencyOptions as $c): ?>
                        <option value="<?= h($c) ?>" <?= $currency === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Faktura -->
            <div class="col-md-2">
                <select name="inv" class="form-select form-select-sm">
                    <option value=""><?= __('Faktura: wszystkie') ?></option>
                    <option value="with"    <?= $invState === 'with'    ? 'selected' : '' ?>><?= __('Z fakturą') ?></option>
                    <option value="without" <?= $invState === 'without' ? 'selected' : '' ?>><?= __('Bez faktury') ?></option>
                    <option value="paid"    <?= $invState === 'paid'    ? 'selected' : '' ?>><?= __('Opłacone') ?></option>
                    <option value="unpaid"  <?= $invState === 'unpaid'  ? 'selected' : '' ?>><?= __('Nieopłacone') ?></option>
                </select>
            </div>
            <!-- CMR -->
            <div class="col-md-1">
                <select name="cmr" class="form-select form-select-sm">
                    <option value=""><?= __('CMR') ?></option>
                    <option value="with"    <?= $cmrState === 'with'    ? 'selected' : '' ?>><?= __('Z CMR') ?></option>
                    <option value="without" <?= $cmrState === 'without' ? 'selected' : '' ?>><?= __('Bez CMR') ?></option>
                </select>
            </div>
            <!-- Akcje -->
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

        <!-- Aktywne filtry jako chips -->
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
            if ($currency !== '') echo $chip(__('Waluta'),    $currency, ['currency' => null]);
            if ($dateFrom !== '') echo $chip(__('Od'),       $dateFrom, ['date_from' => null]);
            if ($dateTo !== '')   echo $chip(__('Do'),       $dateTo,   ['date_to' => null]);
            ?>
        </div>
        <?php endif; ?>
    </div>
</form>

<!-- Statystyki -->
<div class="d-flex flex-wrap gap-3 mb-3 align-items-center">
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
            <i class="ri-truck-line me-1"></i><?= number_format($total, 0, ',', ' ') ?>
        </span>
        <span class="text-muted small"><?= __('zleceń') ?></span>
    </div>
    <?php if ($stats['sum_brutto'] !== null): ?>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                <i class="ri-money-euro-circle-line me-1"></i><?= $fnum($stats['sum_brutto']) ?> <?= h($stats['currency']) ?>
            </span>
            <span class="text-muted small"><?= __('suma brutto (po filtrach)') ?></span>
        </div>
    <?php endif; ?>
    <!-- Sortowanie -->
    <div class="ms-auto d-flex align-items-center gap-1">
        <span class="text-muted small"><?= __('Sortuj') ?>:</span>
        <select class="form-select form-select-sm" style="width:auto" onchange="window.location.href=this.value">
            <?php
            $sortOptions = [
                'date_desc'   => __('Data ↓ (najnowsze)'),
                'date_asc'    => __('Data ↑ (najstarsze)'),
                'amount_desc' => __('Kwota ↓'),
                'amount_asc'  => __('Kwota ↑'),
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
        <table class="table table-hover mb-0 align-middle" style="font-size:.875rem">
            <thead class="table-light border-bottom-2">
                <tr>
                    <th class="ps-3" style="width:110px"><?= __('Data') ?></th>
                    <th style="width:140px"><?= __('Numer') ?></th>
                    <th><?= __('Trasa') ?></th>
                    <th style="width:140px"><?= __('Tytuł') ?></th>
                    <th class="text-end" style="width:130px"><?= __('Kwota') ?></th>
                    <th style="width:100px" class="text-center"><?= __('Status') ?></th>
                    <th class="pe-3 text-end" style="width:200px"><?= __('Akcje') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders) || count($orders->toArray()) === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
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
                    <?php foreach ($orders as $o): ?>
                    <?php
                        $cmrs    = $cmrMap[$o->id] ?? [];
                        $invoice = $o->invoice_id ? ($invoiceMap[(string)$o->invoice_id] ?? null) : null;
                        $rowAccent = (int)$o->status === 0 ? 'tx-row-closed' : 'tx-row-active';
                    ?>
                    <tr class="<?= $rowAccent ?>">
                        <td class="ps-3 align-top text-nowrap pt-3">
                            <div class="fw-semibold"><?= $fdate($o->date_doc) ?></div>
                            <?php if ($o->date_delivery): ?>
                                <div class="text-muted small mt-1">
                                    <i class="ri-truck-line"></i> <?= $fdate($o->date_delivery) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="align-top pt-3">
                            <a href="<?= $this->Url->build(['action' => 'view', $o->id]) ?>"
                               class="fw-semibold text-decoration-none"><?= h($o->symbol ?: '—') ?></a>
                            <?php if ($o->teczka): ?>
                                <div class="text-muted small mt-1"><?= h($o->teczka) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="align-top pt-3" style="max-width:340px">
                            <?php if ($o->place_from_name || $o->place_to_name): ?>
                                <div class="lh-sm">
                                    <span class="text-success">
                                        <i class="ri-arrow-up-circle-fill"></i>
                                    </span>
                                    <?= h($o->place_from_name ?: '?') ?>
                                    <?php if ($o->place_from_country): ?>
                                        <span class="badge bg-light text-secondary border ms-1" style="font-size:.65em"><?= h($o->place_from_country) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="lh-sm mt-1">
                                    <span class="text-danger">
                                        <i class="ri-arrow-down-circle-fill"></i>
                                    </span>
                                    <?= h($o->place_to_name ?: '?') ?>
                                    <?php if ($o->place_to_country): ?>
                                        <span class="badge bg-light text-secondary border ms-1" style="font-size:.65em"><?= h($o->place_to_country) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($o->route_description): ?>
                                <div class="text-muted small"><?= h($o->route_description) ?></div>
                            <?php else: ?>
                                <span class="text-muted fst-italic small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-top pt-3" style="max-width:160px">
                            <?php if ($o->title1): ?>
                                <div class="text-truncate" title="<?= h($o->title1) ?>"><?= h($o->title1) ?></div>
                            <?php endif; ?>
                            <?php if ($o->title2): ?>
                                <div class="text-muted small text-truncate" title="<?= h($o->title2) ?>"><?= h($o->title2) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end align-top pt-3 text-nowrap">
                            <div class="fw-bold"><?= $fnum($o->brutto) ?></div>
                            <div class="text-muted small"><?= h($o->currency) ?> <?= __('brutto') ?></div>
                            <?php if ($invoice): ?>
                                <?php
                                    $stCls = $invoice->paymentstate === 'paid'
                                        ? 'text-success' : ($invoice->paymentstate === 'partial' ? 'text-warning' : 'text-danger');
                                    $stIco = $invoice->paymentstate === 'paid'
                                        ? 'ri-checkbox-circle-fill' : ($invoice->paymentstate === 'partial' ? 'ri-time-line' : 'ri-error-warning-line');
                                ?>
                                <div class="<?= $stCls ?> mt-1" style="font-size:.7em">
                                    <i class="<?= $stIco ?> me-1"></i>
                                    <?= h($invoice->fullnumber ?: '') ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center align-top pt-3">
                            <?php if ((int)$o->status === 0): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="ri-checkbox-circle-line me-1"></i><?= __('Zamknięte') ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                    <i class="ri-time-line me-1"></i><?= __('Aktywne') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-3 text-end align-top pt-2">
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                <a href="<?= $this->Url->build(['action' => 'view', $o->id]) ?>"
                                   class="btn btn-sm btn-outline-primary" title="<?= __('Szczegóły zlecenia') ?>">
                                    <i class="ri-eye-line"></i>
                                </a>
                                <?php if (!empty($cmrs)): ?>
                                    <a href="<?= $this->Url->build(['action' => 'view', $o->id]) ?>#attachments"
                                       class="btn btn-sm btn-outline-success position-relative"
                                       title="<?= sprintf(__('CMR — %d plików'), count($cmrs)) ?>">
                                        <i class="ri-attachment-2"></i>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success" style="font-size:.55em"><?= count($cmrs) ?></span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($invoice): ?>
                                    <a href="<?= $this->Url->build(['action' => 'downloadInvoice', $o->invoice_id]) ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="<?= sprintf(__('Pobierz fakturę %s'), h($invoice->fullnumber ?: '')) ?>">
                                        <i class="ri-file-pdf-line"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
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
.tx-row-active  { border-left: 3px solid #3b82f6; }
.tx-row-closed  { border-left: 3px solid #cbd5e1; }
.tx-row-active  td:first-child,
.tx-row-closed  td:first-child { padding-left: calc(.75rem - 3px) !important; }
</style>

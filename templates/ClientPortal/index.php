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
 * @var string                                         $currentLocale
 */
$this->assign('title', __('Zlecenia transportowe'));

$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';
$fnum  = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold"><?= __('Zlecenia transportowe') ?></h4>
        <div class="text-muted small mt-1">
            <i class="ri-building-line me-1"></i>
            <?= h($clientProfile->company_name ?: $clientProfile->nip) ?>
            <span class="ms-2 badge bg-light text-secondary border">NIP <?= h($clientProfile->nip) ?></span>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <!-- Przełącznik języka -->
        <div class="btn-group btn-group-sm" role="group" aria-label="<?= __('Język') ?>">
            <a href="<?= $this->Url->build(['action' => 'setLocale', 'pl']) ?>"
               class="btn btn-outline-secondary <?= $currentLocale === 'pl' ? 'active' : '' ?>"
               title="Polski">PL</a>
            <a href="<?= $this->Url->build(['action' => 'setLocale', 'en']) ?>"
               class="btn btn-outline-secondary <?= $currentLocale === 'en' ? 'active' : '' ?>"
               title="English">EN</a>
        </div>
    </div>
</div>

<?= $this->Flash->render() ?>

<!-- Wyszukiwarka -->
<form method="get" action="<?= $this->Url->build(['action' => 'index']) ?>"
      class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <input type="text" name="q" value="<?= h($q) ?>"
           class="form-control form-control-sm" style="max-width:320px;"
           placeholder="<?= __('Szukaj: numer, tytuł, trasa…') ?>">
    <button class="btn btn-sm btn-primary" type="submit">
        <i class="ri-search-line"></i>
    </button>
    <?php if ($q !== ''): ?>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>"
           class="btn btn-sm btn-outline-secondary" title="<?= __('Wyczyść') ?>">
            <i class="ri-close-line"></i>
        </a>
    <?php endif; ?>
    <span class="text-muted small ms-auto">
        <?php if ($total > 0): ?>
            <?= sprintf(__('%s zleceń'), number_format($total, 0, ',', ' ')) ?>
        <?php endif; ?>
    </span>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" style="font-size:.875rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:110px"><?= __('Data') ?></th>
                    <th style="width:140px"><?= __('Numer') ?></th>
                    <th><?= __('Trasa') ?></th>
                    <th style="width:130px"><?= __('Tytuł') ?></th>
                    <th class="text-end" style="width:130px"><?= __('Kwota') ?></th>
                    <th style="width:90px"><?= __('Status') ?></th>
                    <th class="pe-3 text-end" style="width:200px"><?= __('Akcje') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders) || count($orders->toArray()) === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="ri-truck-line" style="font-size:2em"></i><br>
                            <?= __('Brak zleceń do wyświetlenia.') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                    <?php
                        $cmrs    = $cmrMap[$o->id] ?? [];
                        $invoice = $o->invoice_id ? ($invoiceMap[(string)$o->invoice_id] ?? null) : null;
                    ?>
                    <tr>
                        <td class="ps-3 align-top text-nowrap pt-3">
                            <div class="fw-semibold"><?= $fdate($o->date_doc) ?></div>
                            <?php if ($o->date_delivery): ?>
                                <div class="text-muted small mt-1">
                                    <i class="ri-truck-line"></i> <?= $fdate($o->date_delivery) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="align-top pt-3">
                            <div class="fw-semibold"><?= h($o->symbol ?: '—') ?></div>
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
                        </td>
                        <td class="align-top pt-3">
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
                                       class="btn btn-sm btn-outline-success"
                                       title="<?= sprintf(__('CMR — %d plików'), count($cmrs)) ?>">
                                        <i class="ri-attachment-2"></i>
                                        <span class="badge bg-success" style="font-size:.65em"><?= count($cmrs) ?></span>
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
                            ['action' => 'index', '?' => array_filter(['q' => $q, 'page' => $page - 1])],
                            ['class' => 'page-link']) ?>
                    </li>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <?= $this->Html->link((string)$p,
                            ['action' => 'index', '?' => array_filter(['q' => $q, 'page' => $p])],
                            ['class' => 'page-link']) ?>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <?= $this->Html->link('›',
                            ['action' => 'index', '?' => array_filter(['q' => $q, 'page' => $page + 1])],
                            ['class' => 'page-link']) ?>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php
/**
 * Dashboard operatora - live tracking aktywnych zlecen.
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $orders
 * @var array $lastEvents
 * @var array $stats
 * @var string $filterDriver
 * @var string $filterContract
 * @var string $filterCountry
 * @var array $contractsList
 */
$this->assign('title', __('Tracking - aktywne zlecenia'));

$eventTypeLabels = [
    'departure' => ['label' => 'Wyjazd', 'icon' => 'ri-truck-line', 'color' => 'primary'],
    'arrival'   => ['label' => 'Przyjazd', 'icon' => 'ri-map-pin-line', 'color' => 'info'],
    'loading_started'   => ['label' => 'Ładuje', 'icon' => 'ri-upload-line', 'color' => 'warning'],
    'loading_completed' => ['label' => 'Załadowano', 'icon' => 'ri-check-line', 'color' => 'success'],
    'unloading_started' => ['label' => 'Rozładowuje', 'icon' => 'ri-download-line', 'color' => 'warning'],
    'unloading_completed' => ['label' => 'Rozładowano', 'icon' => 'ri-check-double-line', 'color' => 'success'],
    'border_crossed'    => ['label' => 'Granica', 'icon' => 'ri-flag-line', 'color' => 'info'],
    'delay_reported'    => ['label' => 'Opóźnienie', 'icon' => 'ri-alarm-warning-line', 'color' => 'danger'],
    'pod_uploaded'      => ['label' => 'POD upload', 'icon' => 'ri-file-check-line', 'color' => 'success'],
    'cmr_signed'        => ['label' => 'CMR podpis', 'icon' => 'ri-quill-pen-line', 'color' => 'success'],
    'incident'          => ['label' => 'Incydent', 'icon' => 'ri-error-warning-line', 'color' => 'danger'],
    'note'              => ['label' => 'Notatka', 'icon' => 'ri-sticky-note-line', 'color' => 'secondary'],
];
?>

<meta http-equiv="refresh" content="60">

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold">
            <i class="ri-radar-line me-1 text-primary"></i><?= __('Tracking zleceń w trasie') ?>
        </h4>
        <small class="text-muted"><?= __('Auto-refresh co 60 s. Aktywne zlecenia: Załadowane + Zrealizowane bez POD.') ?></small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-list-check me-1"></i><?= __('Lista') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'kanban']) ?>" class="btn btn-sm btn-outline-primary">
            <i class="ri-kanban-view me-1"></i>Kanban
        </a>
    </div>
</div>

<!-- KPI -->
<div class="row g-2 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2 px-3">
                <div class="small text-muted">Wszystkich w trasie</div>
                <div class="fs-4 fw-bold"><?= (int)$stats['total'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left:3px solid #f59e0b !important">
            <div class="card-body py-2 px-3">
                <div class="small text-muted">Ładowanie</div>
                <div class="fs-4 fw-bold text-warning"><?= (int)$stats['loading'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left:3px solid #10b981 !important">
            <div class="card-body py-2 px-3">
                <div class="small text-muted">W trasie</div>
                <div class="fs-4 fw-bold text-success"><?= (int)$stats['in_transit'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="border-left:3px solid #ef4444 !important">
            <div class="card-body py-2 px-3">
                <div class="small text-muted">Opóźnione</div>
                <div class="fs-4 fw-bold text-danger"><?= (int)$stats['delayed'] ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filtry -->
<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="driver" class="form-control form-control-sm" value="<?= h($filterDriver) ?>" placeholder="<?= __('Kierowca') ?>">
    </div>
    <div class="col-md-3">
        <select name="contract" class="form-select form-select-sm">
            <option value="">— <?= __('Kontrakt') ?> —</option>
            <?php foreach ($contractsList as $c): ?>
                <option value="<?= h($c) ?>" <?= $filterContract === $c ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <input type="text" name="country" class="form-control form-control-sm" value="<?= h($filterCountry) ?>" placeholder="<?= __('Kraj (PL/DE/...)') ?>" maxlength="2">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="ri-filter-line me-1"></i>Filtruj</button>
    </div>
    <?php if ($filterDriver || $filterContract || $filterCountry): ?>
    <div class="col-md-2">
        <a href="<?= $this->Url->build(['action' => 'tracking']) ?>" class="btn btn-sm btn-outline-secondary w-100">
            <i class="ri-close-line me-1"></i>Wyczyść
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- Lista zlecen -->
<?php if ($orders->count() === 0): ?>
    <div class="alert alert-info text-center">
        <i class="ri-truck-line me-1"></i><?= __('Brak aktywnych zleceń w trasie.') ?>
    </div>
<?php else: ?>
    <div class="row g-2">
        <?php foreach ($orders as $order): ?>
            <?php $ev = $lastEvents[$order->id] ?? null; ?>
            <?php $evMeta = $ev ? ($eventTypeLabels[$ev->event_type] ?? ['label' => $ev->event_type, 'icon' => 'ri-time-line', 'color' => 'secondary']) : null; ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <a href="<?= $this->Url->build(['action' => 'view', $order->id]) ?>" class="fw-semibold text-decoration-none">
                                    <?= h($order->symbol) ?>
                                </a>
                                <span class="badge <?= ($order->source ?? 'speed') === 'manual' ? 'bg-info-subtle text-info' : 'bg-secondary-subtle text-secondary' ?> ms-1" style="font-size:.55rem">
                                    <?= ($order->source ?? 'speed') === 'manual' ? 'M' : 'S' ?>
                                </span>
                                <div class="small text-muted"><?= h($order->buyer_name ?? '—') ?></div>
                            </div>
                            <span class="badge bg-<?= (int)$order->nordlogis_status === 4 ? 'success' : 'warning' ?>-subtle text-<?= (int)$order->nordlogis_status === 4 ? 'success' : 'warning' ?>">
                                <?= (int)$order->nordlogis_status === 4 ? 'Zrealizowane' : 'W trasie' ?>
                            </span>
                        </div>

                        <!-- Route -->
                        <div class="small mb-2 py-1 px-2 rounded" style="background:#f8fafc">
                            <i class="ri-map-pin-2-line text-success me-1"></i><?= h(($order->load_country ?? '') . ' ' . ($order->load_city ?? '')) ?>
                            <i class="ri-arrow-right-line mx-1 text-muted"></i>
                            <i class="ri-flag-2-line text-danger me-1"></i><?= h(($order->unload_country ?? '') . ' ' . ($order->unload_city ?? '')) ?>
                        </div>

                        <!-- Last event -->
                        <?php if ($ev && $evMeta): ?>
                            <div class="p-2 rounded mb-2" style="background:#eef2ff">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div>
                                            <i class="<?= h($evMeta['icon']) ?> text-<?= h($evMeta['color']) ?> me-1"></i>
                                            <strong><?= h($evMeta['label']) ?></strong>
                                            <span class="small text-muted ms-1"><?= h($ev->happened_at?->format('m-d H:i')) ?></span>
                                        </div>
                                        <?php if ($ev->location_address): ?>
                                            <div class="small text-muted mt-1"><i class="ri-map-pin-line me-1"></i><?= h(mb_strimwidth($ev->location_address, 0, 60, '…')) ?></div>
                                        <?php endif; ?>
                                        <?php if ((int)($ev->delay_minutes ?? 0) > 0): ?>
                                            <div class="small text-danger mt-1">
                                                <i class="ri-alarm-warning-line me-1"></i>
                                                Opóźnienie: <?= (int)$ev->delay_minutes ?>min
                                                <?= $ev->delay_reason ? ' — ' . h($ev->delay_reason) : '' ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($ev->notes): ?>
                                            <div class="small mt-1"><?= h(mb_strimwidth($ev->notes, 0, 80, '…')) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($ev->photo_path): ?>
                                        <a href="<?= h($ev->photo_path) ?>" target="_blank" class="ms-2">
                                            <i class="ri-image-line text-primary"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary py-1 px-2 mb-2 small">
                                <i class="ri-question-line me-1"></i><?= __('Brak eventów. Kierowca nie zgłaszał.') ?>
                            </div>
                        <?php endif; ?>

                        <!-- Meta -->
                        <div class="small text-muted d-flex flex-wrap gap-2">
                            <?php if ($order->driver): ?>
                                <span><i class="ri-user-line me-1"></i><?= h($order->driver) ?></span>
                            <?php endif; ?>
                            <?php if ($order->vehicle_reg): ?>
                                <span><i class="ri-truck-line me-1"></i><?= h($order->vehicle_reg) ?></span>
                            <?php endif; ?>
                            <?php if ($order->date_delivery): ?>
                                <span><i class="ri-time-line me-1"></i>ETA: <?= h($order->date_delivery->format('m-d H:i')) ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="mt-2 d-flex gap-1">
                            <a href="/trip-events/zlecenie/<?= $order->id ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
                                <i class="ri-time-line me-1"></i>Timeline
                            </a>
                            <a href="<?= $this->Url->build(['action' => 'view', $order->id]) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="ri-eye-line"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

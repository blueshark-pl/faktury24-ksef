<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\SpeedOrder $order
 * @var array $timeline
 * @var string $driverUrl
 */
$this->assign('title', __('Timeline zlecenia :s', [':s' => $order->symbol ?? '#' . $order->id]));

$typeLabels = \App\Model\Table\TripEventsTable::EVENT_TYPES;
$typeIcons = [
    'departure'           => 'ri-arrow-right-line',
    'arrival'             => 'ri-map-pin-line',
    'loading_started'     => 'ri-arrow-up-box-line',
    'loading_completed'   => 'ri-checkbox-circle-line',
    'unloading_started'   => 'ri-arrow-down-box-line',
    'unloading_completed' => 'ri-checkbox-circle-line',
    'border_crossed'      => 'ri-flag-line',
    'delay_reported'      => 'ri-alarm-warning-line',
    'pod_uploaded'        => 'ri-file-check-line',
    'cmr_signed'          => 'ri-file-list-3-line',
    'incident'            => 'ri-error-warning-line',
    'note'                => 'ri-sticky-note-line',
];

$fmt = static fn ($dt) => $dt instanceof \DateTimeInterface ? $dt->format('d.m.Y H:i') : (string)$dt;
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1">
            <?= __('Timeline zlecenia') ?>
            <code class="ms-2"><?= h($order->symbol ?? '#' . $order->id) ?></code>
        </h1>
        <p class="text-muted small mb-0">
            <?= h($order->place_from_name ?? '') ?> → <?= h($order->place_to_name ?? '') ?>
            <?php if (!empty($order->driver)): ?>
                · <i class="ri-user-line me-1"></i><?= h($order->driver) ?>
            <?php endif ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-info text-white"
                data-bs-toggle="modal" data-bs-target="#driverLinkModal">
            <i class="ri-smartphone-line me-1"></i><?= __('Link dla kierowcy') ?>
        </button>
        <button type="button" class="btn btn-sm btn-primary"
                data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="ri-add-line me-1"></i><?= __('Dodaj event') ?>
        </button>
    </div>
</div>

<?= $this->Flash->render() ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><?= __('Historia zdarzeń') ?> (<?= count($timeline) ?>)</div>
            <div class="card-body">
                <?php if (empty($timeline)): ?>
                    <p class="text-muted mb-0"><?= __('Brak zdarzeń. Dodaj pierwszy lub udostępnij link kierowcy.') ?></p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($timeline as $t):
                            $icon = $typeIcons[$t->event_type] ?? 'ri-circle-line';
                            $badge = $t->source === 'driver_mobile' ? 'bg-success' : ($t->source === 'gps_track' ? 'bg-info' : 'bg-secondary');
                        ?>
                            <div class="d-flex mb-3 pb-3 border-bottom">
                                <div class="me-3">
                                    <div style="width:40px;height:40px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center">
                                        <i class="<?= $icon ?> fs-5 text-primary"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <strong><?= h($typeLabels[$t->event_type] ?? $t->event_type) ?></strong>
                                        <span class="badge <?= $badge ?>"><?= h($t->source) ?></span>
                                        <span class="text-muted small ms-auto"><?= h($fmt($t->happened_at)) ?></span>
                                    </div>
                                    <?php if (!empty($t->driver)): ?>
                                        <div class="small text-muted"><i class="ri-user-line me-1"></i><?= h($t->driver->full_name ?? '') ?></div>
                                    <?php elseif (!empty($t->reported_by_name)): ?>
                                        <div class="small text-muted"><i class="ri-user-line me-1"></i><?= h($t->reported_by_name) ?></div>
                                    <?php endif ?>
                                    <?php if (!empty($t->delay_minutes)): ?>
                                        <div class="mt-1 small text-warning">
                                            <i class="ri-alarm-line me-1"></i>
                                            <?= __('Opóźnienie :n min', [':n' => $t->delay_minutes]) ?>
                                            <?php if (!empty($t->delay_reason)): ?>· <?= h($t->delay_reason) ?><?php endif ?>
                                        </div>
                                    <?php endif ?>
                                    <?php if (!empty($t->notes)): ?>
                                        <div class="mt-1 small"><?= h($t->notes) ?></div>
                                    <?php endif ?>
                                    <?php if (!empty($t->photo_path)): ?>
                                        <div class="mt-2">
                                            <a href="/<?= h($t->photo_path) ?>" target="_blank">
                                                <img src="/<?= h($t->photo_path) ?>" style="max-width:200px;border-radius:6px" class="border">
                                            </a>
                                        </div>
                                    <?php endif ?>
                                    <?php if (!empty($t->location_lat)): ?>
                                        <div class="mt-1 small text-muted">
                                            <i class="ri-map-pin-line me-1"></i>
                                            <?= number_format((float)$t->location_lat, 4) ?>, <?= number_format((float)$t->location_lng, 4) ?>
                                        </div>
                                    <?php endif ?>
                                </div>
                                <div class="ms-2">
                                    <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>',
                                        ['action' => 'delete', $t->id],
                                        ['class' => 'btn btn-sm btn-link text-danger', 'escape' => false,
                                         'confirm' => __('Usunąć event?')]) ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><?= __('Zlecenie') ?></div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5"><?= __('Symbol') ?></dt>
                    <dd class="col-7"><code><?= h($order->symbol ?? '') ?></code></dd>
                    <dt class="col-5"><?= __('Tytuł') ?></dt>
                    <dd class="col-7"><?= h(trim(($order->title1 ?? '') . ' ' . ($order->title2 ?? ''))) ?></dd>
                    <dt class="col-5"><?= __('Załadunek') ?></dt>
                    <dd class="col-7">
                        <?= h($order->place_from_name ?? '') ?>
                        <?php if (!empty($order->date_load)): ?>
                            <br><small class="text-muted"><?= h($fmt($order->date_load)) ?></small>
                        <?php endif ?>
                    </dd>
                    <dt class="col-5"><?= __('Rozładunek') ?></dt>
                    <dd class="col-7">
                        <?= h($order->place_to_name ?? '') ?>
                        <?php if (!empty($order->date_delivery)): ?>
                            <br><small class="text-muted"><?= h($fmt($order->date_delivery)) ?></small>
                        <?php endif ?>
                    </dd>
                    <?php if (!empty($order->driver)): ?>
                        <dt class="col-5"><?= __('Kierowca') ?></dt>
                        <dd class="col-7"><?= h($order->driver) ?></dd>
                    <?php endif ?>
                    <?php if (!empty($order->vehicle_reg)): ?>
                        <dt class="col-5"><?= __('Pojazd') ?></dt>
                        <dd class="col-7"><?= h($order->vehicle_reg) ?></dd>
                    <?php endif ?>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Dodaj event operatora -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= $this->Form->create(null, ['url' => ['action' => 'addEvent']]) ?>
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Dodaj event') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?= $this->Form->hidden('speed_order_id', ['value' => $order->id]) ?>

                <div class="mb-3">
                    <label class="form-label"><?= __('Typ zdarzenia') ?></label>
                    <select name="event_type" class="form-select" required>
                        <?php foreach ($typeLabels as $val => $lbl): ?>
                            <option value="<?= h($val) ?>"><?= h($lbl) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label"><?= __('Kiedy') ?></label>
                    <input type="datetime-local" name="happened_at" class="form-control"
                           value="<?= (new \DateTime())->format('Y-m-d\TH:i') ?>">
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label"><?= __('Opóźnienie (min)') ?></label>
                        <input type="number" name="delay_minutes" class="form-control" min="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label"><?= __('Powód opóźnienia') ?></label>
                        <input type="text" name="delay_reason" class="form-control"
                               placeholder="<?= __('korki / kontrola / awaria') ?>">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label"><?= __('Notatka') ?></label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('Dodaj') ?></button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<!-- Modal: Link dla kierowcy -->
<div class="modal fade" id="driverLinkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('Link dla kierowcy') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    <?= __('Przekaż ten link kierowcy SMS lub przez WhatsApp. Nie wymaga logowania.') ?>
                </p>
                <input type="text" class="form-control" value="<?= h($driverUrl) ?>" readonly onclick="this.select()">
                <button class="btn btn-outline-primary w-100 mt-2" onclick="navigator.clipboard.writeText('<?= h($driverUrl) ?>').then(() => this.innerHTML = '<i class=&quot;ri-check-line&quot;></i> <?= __('Skopiowano') ?>')">
                    <i class="ri-clipboard-line me-1"></i><?= __('Kopiuj link') ?>
                </button>
                <hr>
                <p class="small text-muted mb-2"><?= __('Lub zeskanuj QR:') ?></p>
                <div class="text-center">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($driverUrl) ?>"
                         alt="QR" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</div>

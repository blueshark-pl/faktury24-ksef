<?php
/**
 * @var \App\View\AppView $this
 * @var object $order
 * @var array $timeline
 * @var string $token
 */
$this->assign('title', __('Zlecenie :s', [':s' => $order->symbol ?? '']));

$typeLabels = \App\Model\Table\TripEventsTable::EVENT_TYPES;
$fmt = static fn ($dt) => $dt instanceof \DateTimeInterface ? $dt->format('d.m H:i') : (string)$dt;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zlecenie <?= h($order->symbol ?? '') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css">
    <style>
        body { background: #f3f4f6; padding: 10px; font-family: system-ui, -apple-system, sans-serif; padding-bottom: 90px; }
        .driver-header { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 16px; border-radius: 12px; margin-bottom: 12px; }
        .action-btn { display: block; width: 100%; padding: 16px; background: white; border: none; border-radius: 12px; text-align: left; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .action-btn:active { background: #f9fafb; }
        .action-btn .icon { display: inline-flex; width: 40px; height: 40px; border-radius: 50%; align-items: center; justify-content: center; background: #eff6ff; color: #1e40af; margin-right: 10px; font-size: 20px; vertical-align: middle; }
        .action-btn.danger .icon { background: #fef2f2; color: #dc2626; }
        .action-btn.warning .icon { background: #fffbeb; color: #d97706; }
        .action-btn.success .icon { background: #f0fdf4; color: #16a34a; }
        .timeline-item { background: white; padding: 12px; border-radius: 8px; margin-bottom: 8px; }
        .fab { position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; border-radius: 50%; background: #3b82f6; color: white; border: none; box-shadow: 0 6px 16px rgba(59,130,246,0.5); font-size: 26px; z-index: 100; }
    </style>
</head>
<body>

<div class="driver-header">
    <div style="opacity: 0.7; font-size: 12px;">ZLECENIE</div>
    <div style="font-size: 20px; font-weight: 700;"><?= h($order->symbol ?? '#' . $order->id) ?></div>
    <div class="mt-1" style="font-size: 14px;">
        <i class="ri-map-pin-line"></i> <?= h($order->place_from_name ?? '') ?><br>
        <i class="ri-arrow-down-line"></i> <?= h($order->place_to_name ?? '') ?>
    </div>
    <?php if (!empty($order->date_load) || !empty($order->date_delivery)): ?>
        <div class="mt-2" style="font-size: 13px; opacity: 0.85;">
            <?php if (!empty($order->date_load)): ?>
                <div>ZAŁ: <?= h($fmt($order->date_load)) ?></div>
            <?php endif ?>
            <?php if (!empty($order->date_delivery)): ?>
                <div>ROZ: <?= h($fmt($order->date_delivery)) ?></div>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>

<?php if (!empty($this->request->getSession()->consume('Flash.flash'))): ?>
<?php endif ?>
<?= $this->Flash->render() ?>

<div class="mb-3">
    <div class="text-muted small mb-2 fw-medium"><?= __('Zgłoś status') ?>:</div>

    <button class="action-btn" data-bs-toggle="modal" data-bs-target="#quickEventModal" data-type="loading_completed">
        <span class="icon success"><i class="ri-check-line"></i></span>
        <span class="fw-medium"><?= __('Załadowano') ?></span>
    </button>
    <button class="action-btn" data-bs-toggle="modal" data-bs-target="#quickEventModal" data-type="unloading_completed">
        <span class="icon success"><i class="ri-check-double-line"></i></span>
        <span class="fw-medium"><?= __('Rozładowano') ?></span>
    </button>
    <button class="action-btn" data-bs-toggle="modal" data-bs-target="#quickEventModal" data-type="border_crossed">
        <span class="icon"><i class="ri-flag-line"></i></span>
        <span class="fw-medium"><?= __('Przekroczyłem granicę') ?></span>
    </button>
    <button class="action-btn warning" data-bs-toggle="modal" data-bs-target="#quickEventModal" data-type="delay_reported">
        <span class="icon warning"><i class="ri-alarm-warning-line"></i></span>
        <span class="fw-medium"><?= __('Zgłoś opóźnienie') ?></span>
    </button>
    <button class="action-btn" data-bs-toggle="modal" data-bs-target="#quickEventModal" data-type="pod_uploaded" data-with-photo="1">
        <span class="icon"><i class="ri-camera-line"></i></span>
        <span class="fw-medium"><?= __('Wyślij zdjęcie POD/CMR') ?></span>
    </button>
    <button class="action-btn danger" data-bs-toggle="modal" data-bs-target="#quickEventModal" data-type="incident">
        <span class="icon danger"><i class="ri-error-warning-line"></i></span>
        <span class="fw-medium"><?= __('Incydent (wypadek, uszkodzenie)') ?></span>
    </button>
</div>

<?php if (!empty($timeline)): ?>
<div class="mt-4">
    <div class="text-muted small mb-2 fw-medium"><?= __('Historia') ?>:</div>
    <?php foreach (array_reverse($timeline) as $t): ?>
        <div class="timeline-item">
            <div class="d-flex justify-content-between align-items-center">
                <strong><?= h($typeLabels[$t->event_type] ?? $t->event_type) ?></strong>
                <small class="text-muted"><?= h($fmt($t->happened_at)) ?></small>
            </div>
            <?php if (!empty($t->notes)): ?>
                <div class="small mt-1"><?= h($t->notes) ?></div>
            <?php endif ?>
            <?php if (!empty($t->delay_minutes)): ?>
                <div class="small text-warning mt-1">
                    <i class="ri-alarm-line"></i> <?= (int)$t->delay_minutes ?> min
                    <?php if ($t->delay_reason): ?>· <?= h($t->delay_reason) ?><?php endif ?>
                </div>
            <?php endif ?>
            <?php if (!empty($t->photo_path)): ?>
                <div class="mt-2">
                    <a href="/<?= h($t->photo_path) ?>" target="_blank">
                        <img src="/<?= h($t->photo_path) ?>" style="max-width:100%;border-radius:6px">
                    </a>
                </div>
            <?php endif ?>
        </div>
    <?php endforeach ?>
</div>
<?php endif ?>

<!-- Modal: Quick event -->
<div class="modal fade" id="quickEventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="/kierowca/<?= h($token) ?>/event" enctype="multipart/form-data" id="quick-event-form">
                <input type="hidden" name="event_type" id="event_type_input">
                <input type="hidden" name="location_lat" id="location_lat">
                <input type="hidden" name="location_lng" id="location_lng">

                <div class="modal-header">
                    <h5 class="modal-title" id="event-modal-title"><?= __('Zgłoś status') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('Twoje imię i nazwisko') ?></label>
                        <input type="text" name="driver_name" class="form-control" required
                               autocomplete="name" placeholder="Jan Kowalski">
                    </div>

                    <div id="delay-fields" style="display:none">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label"><?= __('Opóźnienie (min)') ?></label>
                                <input type="number" name="delay_minutes" class="form-control" min="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?= __('Powód') ?></label>
                                <select name="delay_reason" class="form-select">
                                    <option value=""><?= __('— wybierz —') ?></option>
                                    <option value="korki">korki</option>
                                    <option value="kontrola">kontrola drogowa</option>
                                    <option value="kolejka_na_granicy">kolejka na granicy</option>
                                    <option value="awaria">awaria</option>
                                    <option value="pogoda">pogoda</option>
                                    <option value="inne">inne</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="photo-fields" style="display:none" class="mt-3">
                        <label class="form-label"><?= __('Zdjęcie (POD/CMR/dokument)') ?></label>
                        <input type="file" name="photo" accept="image/*" capture="environment" class="form-control">
                    </div>

                    <div class="mt-3">
                        <label class="form-label"><?= __('Notatka (opcjonalna)') ?></label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('Wyślij') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('quickEventModal').addEventListener('show.bs.modal', function (e) {
        var trg = e.relatedTarget;
        var type = trg.dataset.type;
        var withPhoto = trg.dataset.withPhoto === '1';
        var isDelay = type === 'delay_reported';

        document.getElementById('event_type_input').value = type;
        document.getElementById('event-modal-title').textContent = trg.querySelector('.fw-medium').textContent;
        document.getElementById('delay-fields').style.display = isDelay ? '' : 'none';
        document.getElementById('photo-fields').style.display = withPhoto ? '' : 'none';
    });

    // Geolokalizacja (jesli user pozwoli)
    if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(function (pos) {
            document.getElementById('location_lat').value = pos.coords.latitude;
            document.getElementById('location_lng').value = pos.coords.longitude;
        }, function () {}, { timeout: 5000, maximumAge: 60000 });
    }
</script>
</body>
</html>

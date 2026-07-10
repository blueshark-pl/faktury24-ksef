<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $events
 * @var array $stats
 * @var string $severity
 * @var bool $showDismissed
 * @var int $days
 */
$this->assign('title', __('Ryzyko / Compliance'));

$typeLabels = [
    'cabotage_limit'         => 'Kabotaż — limit',
    'cabotage_hard_limit'    => 'Kabotaż — przekroczenie',
    'adr_missing'            => 'Brak certyfikatu ADR',
    'driver_hours_exceeded'  => 'Przekroczenie godzin kierowcy',
    'weekly_rest_missing'    => 'Brak odpoczynku tygodniowego',
    'daily_rest_missing'     => 'Brak odpoczynku dobowego',
    'oversize_no_permit'     => 'Ponadgabaryt bez zezwolenia',
    'sanction_country'       => 'Kraj sankcji',
    'expired_inspection'     => 'Wygasłe badanie techniczne',
    'expired_insurance'      => 'Wygasłe ubezpieczenie',
    'missing_permit'         => 'Brak zezwolenia',
    'other'                  => 'Inne',
];

$severityCls = [
    'info'    => 'bg-info text-white',
    'warning' => 'bg-warning text-dark',
    'error'   => 'bg-danger text-white',
];

$fmt = static fn ($dt) => $dt instanceof \DateTimeInterface ? $dt->format('d.m.Y H:i') : (string)$dt;
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= __('Ryzyko / Compliance') ?></h1>
        <p class="text-muted small mb-0">
            <?= __('Ostrzeżenia wygenerowane automatycznie z planera. „Akceptuję ryzyko" wymaga uzasadnienia do audytu.') ?>
        </p>
    </div>
</div>

<!-- Statystyki -->
<div class="row g-2 mb-3">
    <div class="col-md-4">
        <div class="card border-danger">
            <div class="card-body py-2">
                <div class="small text-muted"><?= __('Aktywne błędy') ?></div>
                <div class="fs-3 fw-bold text-danger"><?= (int)$stats['errors'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body py-2">
                <div class="small text-muted"><?= __('Aktywne ostrzeżenia') ?></div>
                <div class="fs-3 fw-bold text-warning"><?= (int)$stats['warnings'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body py-2">
                <div class="small text-muted"><?= __('Wszystkie aktywne') ?></div>
                <div class="fs-3 fw-bold"><?= (int)$stats['total_active'] ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filtry -->
<div class="card mb-3">
    <div class="card-body py-2">
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'd-flex gap-2 flex-wrap align-items-end']) ?>
            <div>
                <label class="form-label small mb-1"><?= __('Poziom') ?></label>
                <div class="d-flex gap-1">
                    <?php foreach (['' => __('Wszystkie'), 'error' => __('Błędy'), 'warning' => __('Ostrzeżenia'), 'info' => __('Info')] as $val => $lbl):
                        $active = $severity === $val;
                    ?>
                        <button type="submit" name="severity" value="<?= h($val) ?>"
                                class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <?= h($lbl) ?>
                        </button>
                    <?php endforeach ?>
                </div>
            </div>
            <div>
                <label class="form-check-label small mb-1"><?= __('Zaakceptowane') ?></label>
                <div>
                    <label class="form-check">
                        <input type="checkbox" name="dismissed" value="1" class="form-check-input"
                               <?= $showDismissed ? 'checked' : '' ?>
                               onchange="this.form.submit()">
                        <?= __('pokaż też zaakceptowane') ?>
                    </label>
                </div>
            </div>
            <?= $this->Form->hidden('days', ['value' => $days]) ?>
        <?= $this->Form->end() ?>
    </div>
</div>

<?php if (count($events) === 0): ?>
    <div class="alert alert-success">
        <i class="ri-check-double-line me-1"></i>
        <?= __('Brak aktywnych ryzyk. Wszystko OK.') ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:80px"></th>
                        <th><?= __('Typ ryzyka') ?></th>
                        <th><?= __('Opis') ?></th>
                        <th><?= __('Powiązanie') ?></th>
                        <th><?= __('Wykryto') ?></th>
                        <th class="text-end" style="width:100px"><?= __('Akcja') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $e):
                        $sCls = $severityCls[$e->severity] ?? 'bg-secondary text-white';
                    ?>
                        <tr class="<?= $e->is_dismissed ? 'text-muted' : '' ?>">
                            <td>
                                <span class="badge <?= $sCls ?>"><?= h(strtoupper($e->severity ?? '')) ?></span>
                            </td>
                            <td>
                                <strong><?= h($typeLabels[$e->event_type] ?? $e->event_type) ?></strong>
                            </td>
                            <td class="small"><?= h($e->description) ?></td>
                            <td class="small">
                                <?php if (!empty($e->driver)): ?>
                                    <i class="ri-user-line text-muted me-1"></i><?= h($e->driver->full_name) ?>
                                <?php endif ?>
                                <?php if (!empty($e->vehicle)): ?>
                                    <?php if (!empty($e->driver)): ?><br><?php endif ?>
                                    <i class="ri-truck-line text-muted me-1"></i><?= h($e->vehicle->name) ?>
                                <?php endif ?>
                                <?php if (!empty($e->trailer)): ?>
                                    <br><i class="ri-roadster-line text-muted me-1"></i><?= h($e->trailer->name) ?>
                                <?php endif ?>
                            </td>
                            <td class="small text-nowrap"><?= h($fmt($e->detected_at)) ?></td>
                            <td class="text-end">
                                <?php if (!$e->is_dismissed): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                            data-bs-toggle="modal" data-bs-target="#dismissModal-<?= h($e->id) ?>">
                                        <?= __('Akceptuję') ?>
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-secondary" title="<?= h($e->dismissal_reason ?? '') ?>">
                                        <i class="ri-check-line"></i> <?= __('Zaakcept.') ?>
                                    </span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($events as $e): if ($e->is_dismissed) continue; ?>
        <!-- Modal: akceptuj ryzyko -->
        <div class="modal fade" id="dismissModal-<?= h($e->id) ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <?= $this->Form->create(null, ['url' => ['action' => 'dismiss', $e->id]]) ?>
                    <div class="modal-header">
                        <h5 class="modal-title"><?= __('Akceptuj ryzyko') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning small">
                            <?= h($e->description) ?>
                        </div>
                        <label class="form-label"><?= __('Uzasadnienie (do audytu ITD)') ?>:</label>
                        <textarea name="reason" class="form-control" rows="3" required
                                  placeholder="<?= __('np. Klient zaakceptował ryzyko na piśmie, mamy zezwolenie z 15.07.…') ?>"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
                        <button type="submit" class="btn btn-warning">
                            <i class="ri-check-line me-1"></i><?= __('Akceptuję ryzyko') ?>
                        </button>
                    </div>
                    <?= $this->Form->end() ?>
                </div>
            </div>
        </div>
    <?php endforeach ?>

    <div class="mt-3">
        <ul class="pagination pagination-sm">
            <?= $this->Paginator->prev() ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next() ?>
        </ul>
    </div>
<?php endif ?>

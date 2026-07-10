<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $logs
 * @var iterable $drivers
 * @var string $driverId
 * @var string $week
 */
$this->assign('title', __('Czas pracy kierowców'));

$fmtMin = static function ($m) {
    $m = (int)$m;
    $h = intdiv($m, 60);
    $mm = $m % 60;
    return sprintf('%dh %02dm', $h, $mm);
};
$fmtDate = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d.m.Y') : (string)$v;
$sourceLabels = [
    'tachograph' => 'Tachograf',
    'manual'     => 'Ręczny',
    'estimated'  => 'Oszacowany',
    'import_ddd' => 'Import DDD',
    'import_csv' => 'Import CSV',
];
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= __('Czas pracy kierowców') ?></h1>
        <p class="text-muted small mb-0">
            <?= __('Log jazdy + odpoczynku wg UE 561/2006. Kluczowe: 56h/tydzień, 90h/2 tyg.') ?>
        </p>
    </div>
    <?= $this->Html->link(
        '<i class="ri-add-line"></i> ' . __('Nowy wpis'),
        ['action' => 'add'],
        ['class' => 'btn btn-primary btn-sm', 'escape' => false]
    ) ?>
</div>

<!-- Filtr -->
<div class="card mb-3">
    <div class="card-body py-2">
        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'row g-2 align-items-end']) ?>
            <div class="col-md-4">
                <label class="form-label small mb-1"><?= __('Kierowca') ?></label>
                <select name="driver_id" class="form-select form-select-sm">
                    <option value=""><?= __('— wszyscy —') ?></option>
                    <?php foreach ($drivers as $d): ?>
                        <option value="<?= h($d->id) ?>" <?= $driverId === (string)$d->id ? 'selected' : '' ?>>
                            <?= h($d->full_name) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1"><?= __('Tydzień ISO') ?></label>
                <input type="text" name="week" class="form-control form-control-sm"
                       placeholder="2026-W29" value="<?= h($week) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><?= __('Filtruj') ?></button>
            </div>
        <?= $this->Form->end() ?>
    </div>
</div>

<?php if (count($logs) === 0): ?>
    <div class="alert alert-info"><?= __('Brak wpisów.') ?></div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= __('Data') ?></th>
                        <th><?= __('Kierowca') ?></th>
                        <th class="text-end"><?= __('Jazda') ?></th>
                        <th class="text-end"><?= __('Odpoczynek') ?></th>
                        <th class="text-end"><?= __('Inna praca') ?></th>
                        <th class="text-end"><?= __('Dyżur') ?></th>
                        <th><?= __('Tydzień') ?></th>
                        <th><?= __('Źródło') ?></th>
                        <th class="text-end" style="width:110px"><?= __('Akcje') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="small"><?= h($fmtDate($l->log_date)) ?></td>
                            <td class="small"><?= h($l->driver->full_name ?? '—') ?></td>
                            <td class="text-end small">
                                <span class="<?= $l->driving_min > 540 ? 'text-warning fw-bold' : '' ?>"><?= h($fmtMin($l->driving_min)) ?></span>
                            </td>
                            <td class="text-end small"><?= h($fmtMin($l->rest_min)) ?></td>
                            <td class="text-end small"><?= h($fmtMin($l->other_work_min)) ?></td>
                            <td class="text-end small"><?= h($fmtMin($l->availability_min)) ?></td>
                            <td class="small"><code><?= h($l->week_iso) ?></code></td>
                            <td class="small">
                                <span class="badge bg-light text-dark border">
                                    <?= h($sourceLabels[$l->source] ?? $l->source) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?= $this->Html->link('<i class="ri-edit-line"></i>', ['action' => 'edit', $l->id],
                                    ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false]) ?>
                                <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>', ['action' => 'delete', $l->id],
                                    ['class' => 'btn btn-sm btn-outline-danger', 'escape' => false,
                                     'confirm' => __('Usunąć wpis?')]) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">
        <ul class="pagination pagination-sm">
            <?= $this->Paginator->prev() ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next() ?>
        </ul>
    </div>
<?php endif ?>

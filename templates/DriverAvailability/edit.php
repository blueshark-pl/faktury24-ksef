<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Driver $driver
 * @var array<int,\App\Model\Entity\DriverAvailability> $byDow
 */
$this->assign('title', __('Dostępność: :name', [':name' => $driver->full_name ?? '']));

$dayNames = [
    1 => __('Poniedziałek'),
    2 => __('Wtorek'),
    3 => __('Środa'),
    4 => __('Czwartek'),
    5 => __('Piątek'),
    6 => __('Sobota'),
    7 => __('Niedziela'),
];

$fmtTime = static fn ($v) => $v instanceof \DateTimeInterface ? $v->format('H:i') : (substr((string)$v, 0, 5) ?: '');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h4 mb-1"><?= __('Wzorzec dostępności') ?></h1>
        <div class="text-muted small">
            <i class="ri-user-line me-1"></i><?= h($driver->full_name) ?>
            <?php if ($driver->adr_certified): ?>
                <span class="badge bg-danger-subtle text-danger ms-2">ADR</span>
            <?php endif ?>
        </div>
    </div>
    <?= $this->Html->link(__('← Powrót'), ['action' => 'index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>

<?= $this->Form->create(null, ['url' => ['action' => 'edit', $driver->id]]) ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= __('Dzień') ?></th>
                        <th><?= __('Start') ?></th>
                        <th><?= __('Koniec') ?></th>
                        <th><?= __('Max godzin') ?></th>
                        <th class="text-center"><?= __('Międzynar.') ?></th>
                        <th class="text-center">ADR</th>
                        <th class="text-center"><?= __('Nocą') ?></th>
                        <th class="text-center"><?= __('Weekend') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dayNames as $dow => $name):
                        $r = $byDow[$dow] ?? null;
                    ?>
                        <tr class="<?= in_array($dow, [6, 7], true) ? 'table-warning-subtle' : '' ?>">
                            <td><strong><?= h($name) ?></strong></td>
                            <td>
                                <input type="time" name="days[<?= $dow ?>][shift_start]" class="form-control form-control-sm"
                                       value="<?= h($fmtTime($r?->shift_start)) ?>"
                                       placeholder="<?= __('pusto = wolne') ?>">
                            </td>
                            <td>
                                <input type="time" name="days[<?= $dow ?>][shift_end]" class="form-control form-control-sm"
                                       value="<?= h($fmtTime($r?->shift_end)) ?>">
                            </td>
                            <td>
                                <input type="number" name="days[<?= $dow ?>][max_hours_this_day]"
                                       class="form-control form-control-sm" style="width:80px"
                                       min="0" max="24" placeholder="—"
                                       value="<?= h($r?->max_hours_this_day ?? '') ?>">
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="days[<?= $dow ?>][accepts_international]" value="1"
                                       class="form-check-input" <?= (!$r || $r->accepts_international) ? 'checked' : '' ?>>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="days[<?= $dow ?>][accepts_adr]" value="1"
                                       class="form-check-input" <?= ($r && $r->accepts_adr) ? 'checked' : '' ?>>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="days[<?= $dow ?>][accepts_night]" value="1"
                                       class="form-check-input" <?= (!$r || $r->accepts_night) ? 'checked' : '' ?>>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="days[<?= $dow ?>][accepts_weekend]" value="1"
                                       class="form-check-input" <?= ($r && $r->accepts_weekend) ? 'checked' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-light">
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i><?= __('Zapisz wzorzec') ?>
        </button>
        <?= $this->Html->link(__('Anuluj'), ['action' => 'index'], ['class' => 'btn btn-link']) ?>
    </div>
</div>
<?= $this->Form->end() ?>

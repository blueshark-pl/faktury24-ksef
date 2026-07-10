<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Collection\CollectionInterface $schedules
 * @var \Cake\Collection\CollectionInterface $drivers
 * @var \DateTime $from
 * @var \DateTime $to
 */
$this->assign('title', __('Grafik kierowców'));

$typeLabels = [
    'assignment' => ['Zlecenie',   'bg-primary'],
    'time_off'   => ['Urlop',      'bg-warning text-dark'],
    'sickness'   => ['L4',         'bg-danger'],
    'training'   => ['Szkolenie',  'bg-info'],
    'blocked'    => ['Niedostępny','bg-secondary'],
];

$fmt = static fn ($dt) => $dt instanceof \DateTimeInterface ? $dt->format('d.m Y H:i') : (string)$dt;
$prevWeek = (clone $from)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $from)->modify('+7 days')->format('Y-m-d');
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= __('Grafik kierowców') ?></h1>
        <p class="text-muted small mb-0">
            <?= __('Kto zajęty w oknie :from — :to. Do planowania: kliknij „Nowy wpis" i wybierz kierowcę + zakres.', [
                ':from' => $from->format('d.m.Y'),
                ':to'   => $to->format('d.m.Y'),
            ]) ?>
        </p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <?= $this->Html->link('<i class="ri-arrow-left-s-line"></i>', ['action' => 'index', '?' => ['from' => $prevWeek]], ['escape' => false, 'class' => 'btn btn-sm btn-outline-secondary', 'title' => __('Poprzednie 2 tyg.')]) ?>
        <?= $this->Html->link('<i class="ri-calendar-line me-1"></i>' . __('Dzisiaj'), ['action' => 'index'], ['escape' => false, 'class' => 'btn btn-sm btn-outline-secondary']) ?>
        <?= $this->Html->link('<i class="ri-arrow-right-s-line"></i>', ['action' => 'index', '?' => ['from' => $nextWeek]], ['escape' => false, 'class' => 'btn btn-sm btn-outline-secondary', 'title' => __('Następne 2 tyg.')]) ?>
        <?= $this->Html->link(
            '<i class="ri-add-line"></i> ' . __('Nowy wpis'),
            ['action' => 'add'],
            ['class' => 'btn btn-primary btn-sm', 'escape' => false]
        ) ?>
    </div>
</div>

<?php if ($schedules->count() === 0): ?>
    <div class="alert alert-info">
        <?= __('Brak wpisów grafiku w tym oknie. Kliknij „Nowy wpis" żeby zablokować kierowcę.') ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= __('Kierowca') ?></th>
                        <th><?= __('Od') ?></th>
                        <th><?= __('Do') ?></th>
                        <th><?= __('Typ') ?></th>
                        <th><?= __('Powiązanie') ?></th>
                        <th><?= __('Notatka') ?></th>
                        <th class="text-end" style="width:120px"><?= __('Akcje') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $s):
                        [$typeLabel, $typeCls] = $typeLabels[(string)$s->entry_type] ?? [(string)$s->entry_type, 'bg-secondary'];
                    ?>
                        <tr>
                            <td><?= h($s->driver->full_name ?? '—') ?></td>
                            <td><?= h($fmt($s->starts_at)) ?></td>
                            <td><?= h($fmt($s->ends_at)) ?></td>
                            <td><span class="badge <?= $typeCls ?>"><?= h($typeLabel) ?></span></td>
                            <td class="small">
                                <?php if (!empty($s->speed_order)): ?>
                                    <i class="ri-file-list-3-line text-muted me-1"></i>
                                    <?= h($s->speed_order->symbol ?? '') ?>
                                <?php elseif (!empty($s->route_plan)): ?>
                                    <i class="ri-route-line text-muted me-1"></i>
                                    <?= h($s->route_plan->name ?? '') ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>
                            <td class="small text-muted"><?= h($s->notes ?? '') ?></td>
                            <td class="text-end">
                                <?= $this->Html->link('<i class="ri-edit-line"></i>', ['action' => 'edit', $s->id],
                                    ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => __('Edytuj')]) ?>
                                <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>', ['action' => 'delete', $s->id],
                                    ['class' => 'btn btn-sm btn-outline-danger', 'escape' => false, 'title' => __('Usuń'),
                                     'confirm' => __('Usunąć wpis grafiku?')]) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

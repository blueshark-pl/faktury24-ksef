<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Collection\CollectionInterface $combinations
 */
$this->assign('title', __('Zestawy pojazd + naczepa + kierowca'));
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h4 mb-1"><?= __('Zestawy pojazdów') ?></h1>
        <p class="text-muted small mb-0">
            <?= __('Nazwane kombinacje ciągnik + naczepa + kierowca. W planerze tras wybierasz cały zestaw jednym kliknięciem.') ?>
        </p>
    </div>
    <?= $this->Html->link(
        '<i class="ri-add-line"></i> ' . __('Nowy zestaw'),
        ['action' => 'add'],
        ['class' => 'btn btn-primary', 'escape' => false]
    ) ?>
</div>

<?php if (empty($combinations->count())): ?>
    <div class="alert alert-info">
        <?= __('Brak zdefiniowanych zestawów. Dodaj pierwszy, aby móc szybko wybrać go w planerze tras.') ?>
    </div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:60px"></th>
                    <th><?= __('Nazwa zestawu') ?></th>
                    <th><?= __('Ciągnik') ?></th>
                    <th><?= __('Naczepa') ?></th>
                    <th><?= __('Kierowca') ?></th>
                    <th style="width:80px"><?= __('Aktywne') ?></th>
                    <th style="width:120px" class="text-end"><?= __('Akcje') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($combinations as $c): ?>
                    <tr>
                        <td class="text-center">
                            <?php if ($c->is_default): ?>
                                <i class="ri-star-fill text-warning" title="<?= __('Domyślny') ?>"></i>
                            <?php endif ?>
                        </td>
                        <td><strong><?= h($c->name) ?></strong></td>
                        <td>
                            <?php if (!empty($c->vehicle)): ?>
                                <div><?= h($c->vehicle->name ?? '') ?></div>
                                <?php if (!empty($c->vehicle->plate)): ?>
                                    <span class="badge bg-secondary-subtle text-body"><?= h($c->vehicle->plate) ?></span>
                                <?php endif ?>
                                <?php if (!empty($c->vehicle->axle_count)): ?>
                                    <span class="text-muted small ms-1"><?= h((int)$c->vehicle->axle_count) ?>-osi</span>
                                <?php endif ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if (!empty($c->trailer)): ?>
                                <div><?= h($c->trailer->name ?? '') ?></div>
                                <?php if (!empty($c->trailer->plate)): ?>
                                    <span class="badge bg-secondary-subtle text-body"><?= h($c->trailer->plate) ?></span>
                                <?php endif ?>
                                <?php if (!empty($c->trailer->type)): ?>
                                    <span class="text-muted small ms-1"><?= h($c->trailer->type) ?></span>
                                <?php endif ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if (!empty($c->driver)): ?>
                                <?= h(trim(($c->driver->first_name ?? '') . ' ' . ($c->driver->last_name ?? ''))) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if ($c->is_active): ?>
                                <span class="badge bg-success"><?= __('Tak') ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= __('Nie') ?></span>
                            <?php endif ?>
                        </td>
                        <td class="text-end">
                            <?= $this->Html->link(
                                '<i class="ri-edit-line"></i>',
                                ['action' => 'edit', $c->id],
                                ['class' => 'btn btn-sm btn-outline-secondary', 'escape' => false, 'title' => __('Edytuj')]
                            ) ?>
                            <?= $this->Form->postLink(
                                '<i class="ri-delete-bin-line"></i>',
                                ['action' => 'delete', $c->id],
                                [
                                    'class' => 'btn btn-sm btn-outline-danger',
                                    'escape' => false,
                                    'title' => __('Usuń'),
                                    'confirm' => __('Usunąć zestaw „:name"?', [':name' => $c->name]),
                                ]
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif ?>

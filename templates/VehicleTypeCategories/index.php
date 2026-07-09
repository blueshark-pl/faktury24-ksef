<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Collection\CollectionInterface|\App\Model\Entity\VehicleTypeCategory[] $categories
 * @var array<string, \App\Model\Entity\VehicleTypeCategory[]> $grouped
 */
$this->assign('title', __('Kategorie typów pojazdu — klasyfikacja tolls'));

$typeLabels = [
    'standard'  => __('Standard (ciągnik 4x2 + naczepa 3-os., >18t)'),
    'mega'      => __('Mega'),
    'fridge'    => __('Chłodnia'),
    'tandem'    => __('Tandem (ciągnik + przyczepa)'),
    'solo'      => __('Solo'),
    'bus'       => __('Bus/Van'),
    'oversize'  => __('Ponadgabaryt'),
];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h4 mb-1"><?= __('Kategorie typów pojazdu') ?></h1>
        <p class="text-muted small mb-0">
            <?= __('Mapowanie typu zestawu (Standard/Mega/…) na kategorię w konkretnym systemie tolls (np. „A2 AWSA = kat. 4"). Planer tras użyje tej mapy zamiast zgadywać po ilości osi.') ?>
        </p>
    </div>
    <?= $this->Html->link(
        '<i class="ri-add-line"></i> ' . __('Dodaj mapowanie'),
        ['action' => 'add'],
        ['class' => 'btn btn-primary', 'escape' => false]
    ) ?>
</div>

<?php if (empty($grouped)): ?>
    <div class="alert alert-info">
        <?= __('Brak zdefiniowanych mapowań. Bez nich planer tras zgaduje kategorię po ilości osi/DMC.') ?>
    </div>
<?php else: ?>
    <?php foreach ($grouped as $typeCode => $rows): ?>
        <div class="card mb-3">
            <div class="card-header bg-light">
                <strong><?= h($typeLabels[$typeCode] ?? $typeCode) ?></strong>
                <span class="badge bg-secondary ms-2"><?= count($rows) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width:70px"><?= __('Kraj') ?></th>
                            <th><?= __('System') ?></th>
                            <th><?= __('Kategoria') ?></th>
                            <th><?= __('Notatki') ?></th>
                            <th style="width:80px"><?= __('Aktywne') ?></th>
                            <th style="width:120px" class="text-end"><?= __('Akcje') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $c): ?>
                            <tr>
                                <td><span class="badge bg-info-subtle text-info-emphasis"><?= h($c->country_code) ?></span></td>
                                <td><?= h($c->system_name) ?></td>
                                <td><strong><?= h($c->category_label) ?></strong></td>
                                <td class="small text-muted"><?= h($c->notes ?? '') ?></td>
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
                                            'confirm' => __('Usunąć „:label"?', [':label' => $c->category_label]),
                                        ]
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach ?>
<?php endif ?>

<?php
/** @var \App\View\AppView $this */
/** @var array $items */
$this->assign('title', __('Rodzaje taboru'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="ri-truck-line text-primary"></i> <?= __('Rodzaje taboru') ?></h4>
    <div>
        <a href="<?= $this->Url->build(['controller' => 'Leads', 'action' => 'kanban']) ?>" class="btn btn-sm btn-outline-secondary"><i class="ri-arrow-left-line"></i> <?= __('Wróć do CRM') ?></a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-success"><i class="ri-add-line"></i> <?= __('Nowy rodzaj') ?></a>
    </div>
</div>

<div class="card"><div class="card-body">
    <?php if (empty($items)): ?>
        <div class="text-muted text-center py-4"><?= __('Brak rodzajów taboru. Migracja seed dodaje domyślnie: Frigo, Tautliner, Mega, Tandem, Gabaryt.') ?></div>
    <?php else: ?>
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th><?= __('Nazwa') ?></th><th class="text-end"><?= __('Sortowanie') ?></th><th class="text-end"></th></tr></thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td><span class="badge bg-secondary" style="font-size: 12px;"><?= h($it->name) ?></span></td>
                    <td class="text-end"><?= (int)$it->sort_order ?></td>
                    <td class="text-end">
                        <a href="<?= $this->Url->build(['action' => 'edit', $it->id]) ?>" class="btn btn-sm btn-outline-primary"><i class="ri-pencil-line"></i></a>
                        <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>', ['action' => 'delete', $it->id],
                            ['escape' => false, 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('Usunąć „{0}"? Leady stracą ten tabor.', $it->name)]) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div></div>

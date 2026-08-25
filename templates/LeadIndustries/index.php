<?php
/**
 * @var \App\View\AppView $this
 * @var array $items
 */
$this->assign('title', __('Branze leadow'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-bold"><i class="ri-briefcase-line text-primary"></i> <?= __('Branże leadów') ?></h4>
    <div>
        <a href="<?= $this->Url->build(['controller' => 'Leads', 'action' => 'kanban']) ?>" class="btn btn-sm btn-outline-secondary"><i class="ri-arrow-left-line"></i> <?= __('Wróć do CRM') ?></a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-success"><i class="ri-add-line"></i> <?= __('Nowa branża') ?></a>
    </div>
</div>

<div class="card"><div class="card-body">
    <?php if (empty($items)): ?>
        <div class="text-muted text-center py-4"><?= __('Brak branż. Dodaj: hutnicza, piekarnia, spożywcza, farmacja, przemysł ciężki itd.') ?></div>
    <?php else: ?>
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th><?= __('Nazwa') ?></th><th class="text-end"><?= __('Sortowanie') ?></th><th class="text-end"></th></tr></thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td class="fw-semibold"><?= h($it->name) ?></td>
                    <td class="text-end"><?= (int)$it->sort_order ?></td>
                    <td class="text-end">
                        <a href="<?= $this->Url->build(['action' => 'edit', $it->id]) ?>" class="btn btn-sm btn-outline-primary"><i class="ri-pencil-line"></i></a>
                        <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>', ['action' => 'delete', $it->id],
                            ['escape' => false, 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('Usunąć „{0}"? Leady z tą branżą stracą powiązanie.', $it->name)]) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div></div>

<?php
/**
 * @var \App\View\AppView $this
 * @var array $labels
 */
$this->assign('title', __('CRM – Etykiety'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-bold"><i class="ri-price-tag-3-line text-primary"></i> <?= __('Etykiety leadów') ?></h4>
        <div class="small text-muted"><?= __('Trello-style kolorowe etykiety - przypisz do leadów w Kanban/detalu.') ?></div>
    </div>
    <div>
        <a href="<?= $this->Url->build(['controller' => 'Leads', 'action' => 'kanban']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-arrow-left-line"></i> <?= __('Wróć do Kanban') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-success">
            <i class="ri-add-line"></i> <?= __('Nowa etykieta') ?>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($labels)): ?>
            <div class="text-center py-4 text-muted">
                <?= __('Brak etykiet. Kliknij "Nowa etykieta" aby dodać pierwszą (np. „ADR", „Pilne", „Duży kontrakt").') ?>
            </div>
        <?php else: ?>
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th><?= __('Etykieta') ?></th>
                        <th><?= __('Kolor') ?></th>
                        <th class="text-end"><?= __('Sortowanie') ?></th>
                        <th class="text-end"><?= __('Akcje') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($labels as $lbl): ?>
                    <tr>
                        <td>
                            <span style="display: inline-block; padding: 4px 12px; border-radius: 4px; background: <?= h($lbl->color) ?>; color: #fff; font-size: 13px; font-weight: 600;">
                                <?= h($lbl->name) ?>
                            </span>
                        </td>
                        <td><code><?= h($lbl->color) ?></code></td>
                        <td class="text-end"><?= (int)$lbl->sort_order ?></td>
                        <td class="text-end">
                            <a href="<?= $this->Url->build(['action' => 'edit', $lbl->id]) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="ri-pencil-line"></i>
                            </a>
                            <?= $this->Form->postLink(
                                '<i class="ri-delete-bin-line"></i>',
                                ['action' => 'delete', $lbl->id],
                                ['escape' => false, 'class' => 'btn btn-sm btn-outline-danger',
                                 'confirm' => __('Usunąć etykietę „{0}"? Zniknie ze wszystkich leadów.', $lbl->name)]
                            ) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

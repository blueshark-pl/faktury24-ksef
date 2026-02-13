<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Vat> $vats
 */
?>
<div class="vats index content">
    <?= $this->Html->link(__('New Vat'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Vats') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('name') ?></th>
                    <th><?= $this->Paginator->sort('rate') ?></th>
                    <th><?= $this->Paginator->sort('deleted') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vats as $vat): ?>
                <tr>
                    <td><?= h($vat->id) ?></td>
                    <td><?= h($vat->name) ?></td>
                    <td><?= $this->Number->format($vat->rate) ?></td>
                    <td><?= h($vat->deleted) ?></td>
                    <td><?= h($vat->created) ?></td>
                    <td><?= h($vat->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $vat->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $vat->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $vat->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $vat->id),
                            ]
                        ) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
    </div>
</div>
<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\InvoiceContractor> $invoiceContractors
 */
?>
<div class="invoiceContractors index content">
    <?= $this->Html->link(__('New Invoice Contractor'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Invoice Contractors') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('invoice_id') ?></th>
                    <th><?= $this->Paginator->sort('name') ?></th>
                    <th><?= $this->Paginator->sort('nip') ?></th>
                    <th><?= $this->Paginator->sort('street') ?></th>
                    <th><?= $this->Paginator->sort('city') ?></th>
                    <th><?= $this->Paginator->sort('zip') ?></th>
                    <th><?= $this->Paginator->sort('country') ?></th>
                    <th><?= $this->Paginator->sort('account_number') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceContractors as $invoiceContractor): ?>
                <tr>
                    <td><?= h($invoiceContractor->id) ?></td>
                    <td><?= $invoiceContractor->hasValue('invoice') ? $this->Html->link($invoiceContractor->invoice->currency, ['controller' => 'Invoices', 'action' => 'view', $invoiceContractor->invoice->id]) : '' ?></td>
                    <td><?= h($invoiceContractor->name) ?></td>
                    <td><?= h($invoiceContractor->nip) ?></td>
                    <td><?= h($invoiceContractor->street) ?></td>
                    <td><?= h($invoiceContractor->city) ?></td>
                    <td><?= h($invoiceContractor->zip) ?></td>
                    <td><?= h($invoiceContractor->country) ?></td>
                    <td><?= h($invoiceContractor->account_number) ?></td>
                    <td><?= h($invoiceContractor->created) ?></td>
                    <td><?= h($invoiceContractor->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $invoiceContractor->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $invoiceContractor->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $invoiceContractor->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $invoiceContractor->id),
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
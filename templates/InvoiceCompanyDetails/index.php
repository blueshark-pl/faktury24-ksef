<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\InvoiceCompanyDetail> $invoiceCompanyDetails
 */
?>
<div class="invoiceCompanyDetails index content">
    <?= $this->Html->link(__('New Invoice Company Detail'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Invoice Company Details') ?></h3>
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
                    <th><?= $this->Paginator->sort('bank_account') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceCompanyDetails as $invoiceCompanyDetail): ?>
                <tr>
                    <td><?= h($invoiceCompanyDetail->id) ?></td>
                    <td><?= $invoiceCompanyDetail->hasValue('invoice') ? $this->Html->link($invoiceCompanyDetail->invoice->currency, ['controller' => 'Invoices', 'action' => 'view', $invoiceCompanyDetail->invoice->id]) : '' ?></td>
                    <td><?= h($invoiceCompanyDetail->name) ?></td>
                    <td><?= h($invoiceCompanyDetail->nip) ?></td>
                    <td><?= h($invoiceCompanyDetail->street) ?></td>
                    <td><?= h($invoiceCompanyDetail->city) ?></td>
                    <td><?= h($invoiceCompanyDetail->zip) ?></td>
                    <td><?= h($invoiceCompanyDetail->country) ?></td>
                    <td><?= h($invoiceCompanyDetail->bank_account) ?></td>
                    <td><?= h($invoiceCompanyDetail->created) ?></td>
                    <td><?= h($invoiceCompanyDetail->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $invoiceCompanyDetail->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $invoiceCompanyDetail->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $invoiceCompanyDetail->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $invoiceCompanyDetail->id),
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
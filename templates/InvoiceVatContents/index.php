<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\InvoiceVatContent> $invoiceVatContents
 */
?>
<div class="invoiceVatContents index content">
    <?= $this->Html->link(__('New Invoice Vat Content'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Invoice Vat Contents') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('invoice_id') ?></th>
                    <th><?= $this->Paginator->sort('vat_code_id') ?></th>
                    <th><?= $this->Paginator->sort('netto') ?></th>
                    <th><?= $this->Paginator->sort('tax') ?></th>
                    <th><?= $this->Paginator->sort('brutto') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceVatContents as $invoiceVatContent): ?>
                <tr>
                    <td><?= h($invoiceVatContent->id) ?></td>
                    <td><?= $invoiceVatContent->hasValue('invoice') ? $this->Html->link($invoiceVatContent->invoice->currency, ['controller' => 'Invoices', 'action' => 'view', $invoiceVatContent->invoice->id]) : '' ?></td>
                    <td><?= h($invoiceVatContent->vat_code_id) ?></td>
                    <td><?= $this->Number->format($invoiceVatContent->netto) ?></td>
                    <td><?= $this->Number->format($invoiceVatContent->tax) ?></td>
                    <td><?= $this->Number->format($invoiceVatContent->brutto) ?></td>
                    <td><?= h($invoiceVatContent->created) ?></td>
                    <td><?= h($invoiceVatContent->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $invoiceVatContent->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $invoiceVatContent->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $invoiceVatContent->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $invoiceVatContent->id),
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
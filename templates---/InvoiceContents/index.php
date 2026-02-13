<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\InvoiceContent> $invoiceContents
 */
?>
<div class="invoiceContents index content">
    <?= $this->Html->link(__('New Invoice Content'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Invoice Contents') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('invoice_id') ?></th>
                    <th><?= $this->Paginator->sort('vat_code_id') ?></th>
                    <th><?= $this->Paginator->sort('name') ?></th>
                    <th><?= $this->Paginator->sort('quantity') ?></th>
                    <th><?= $this->Paginator->sort('unit') ?></th>
                    <th><?= $this->Paginator->sort('price') ?></th>
                    <th><?= $this->Paginator->sort('discount_percent') ?></th>
                    <th><?= $this->Paginator->sort('netto') ?></th>
                    <th><?= $this->Paginator->sort('brutto') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceContents as $invoiceContent): ?>
                <tr>
                    <td><?= h($invoiceContent->id) ?></td>
                    <td><?= $invoiceContent->hasValue('invoice') ? $this->Html->link($invoiceContent->invoice->currency, ['controller' => 'Invoices', 'action' => 'view', $invoiceContent->invoice->id]) : '' ?></td>
                    <td><?= h($invoiceContent->vat_code_id) ?></td>
                    <td><?= h($invoiceContent->name) ?></td>
                    <td><?= $this->Number->format($invoiceContent->quantity) ?></td>
                    <td><?= h($invoiceContent->unit) ?></td>
                    <td><?= $this->Number->format($invoiceContent->price) ?></td>
                    <td><?= $this->Number->format($invoiceContent->discount_percent) ?></td>
                    <td><?= $this->Number->format($invoiceContent->netto) ?></td>
                    <td><?= $this->Number->format($invoiceContent->brutto) ?></td>
                    <td><?= h($invoiceContent->created) ?></td>
                    <td><?= h($invoiceContent->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $invoiceContent->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $invoiceContent->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $invoiceContent->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $invoiceContent->id),
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
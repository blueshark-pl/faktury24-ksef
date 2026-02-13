<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\InvoiceSeries> $invoiceSeries
 */
?>
<div class="invoiceSeries index content">
    <?= $this->Html->link(__('New Invoice Series'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Invoice Series') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('company_id') ?></th>
                    <th><?= $this->Paginator->sort('invoice_series_type_id') ?></th>
                    <th><?= $this->Paginator->sort('invoice_series_period_id') ?></th>
                    <th><?= $this->Paginator->sort('is_default') ?></th>
                    <th><?= $this->Paginator->sort('name') ?></th>
                    <th><?= $this->Paginator->sort('series_template') ?></th>
                    <th><?= $this->Paginator->sort('starting_number') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceSeries as $invoiceSeries): ?>
                <tr>
                    <td><?= h($invoiceSeries->id) ?></td>
                    <td><?= $invoiceSeries->hasValue('company') ? $this->Html->link($invoiceSeries->company->name, ['controller' => 'Companies', 'action' => 'view', $invoiceSeries->company->id]) : '' ?></td>
                    <td><?= $invoiceSeries->hasValue('invoice_series_type') ? $this->Html->link($invoiceSeries->invoice_series_type->name, ['controller' => 'InvoiceSeriesTypes', 'action' => 'view', $invoiceSeries->invoice_series_type->id]) : '' ?></td>
                    <td><?= $invoiceSeries->hasValue('invoice_series_period') ? $this->Html->link($invoiceSeries->invoice_series_period->name, ['controller' => 'InvoiceSeriesPeriods', 'action' => 'view', $invoiceSeries->invoice_series_period->id]) : '' ?></td>
                    <td><?= h($invoiceSeries->is_default) ?></td>
                    <td><?= h($invoiceSeries->name) ?></td>
                    <td><?= h($invoiceSeries->series_template) ?></td>
                    <td><?= $this->Number->format($invoiceSeries->starting_number) ?></td>
                    <td><?= h($invoiceSeries->created) ?></td>
                    <td><?= h($invoiceSeries->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $invoiceSeries->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $invoiceSeries->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $invoiceSeries->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $invoiceSeries->id),
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
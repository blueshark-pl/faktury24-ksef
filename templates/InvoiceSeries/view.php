<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceSeries $invoiceSeries
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Invoice Series'), ['action' => 'edit', $invoiceSeries->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Invoice Series'), ['action' => 'delete', $invoiceSeries->id], ['confirm' => __('Are you sure you want to delete # {0}?', $invoiceSeries->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Invoice Series'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Invoice Series'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceSeries view content">
            <h3><?= h($invoiceSeries->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($invoiceSeries->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Company') ?></th>
                    <td><?= $invoiceSeries->hasValue('company') ? $this->Html->link($invoiceSeries->company->name, ['controller' => 'Companies', 'action' => 'view', $invoiceSeries->company->id]) : '' ?></td>
                </tr>
                <tr>
                    <th><?= __('Invoice Series Type') ?></th>
                    <td><?= $invoiceSeries->hasValue('invoice_series_type') ? $this->Html->link($invoiceSeries->invoice_series_type->name, ['controller' => 'InvoiceSeriesTypes', 'action' => 'view', $invoiceSeries->invoice_series_type->id]) : '' ?></td>
                </tr>
                <tr>
                    <th><?= __('Invoice Series Period') ?></th>
                    <td><?= $invoiceSeries->hasValue('invoice_series_period') ? $this->Html->link($invoiceSeries->invoice_series_period->name, ['controller' => 'InvoiceSeriesPeriods', 'action' => 'view', $invoiceSeries->invoice_series_period->id]) : '' ?></td>
                </tr>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($invoiceSeries->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Series Template') ?></th>
                    <td><?= h($invoiceSeries->series_template) ?></td>
                </tr>
                <tr>
                    <th><?= __('Starting Number') ?></th>
                    <td><?= $this->Number->format($invoiceSeries->starting_number) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($invoiceSeries->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($invoiceSeries->modified) ?></td>
                </tr>
                <tr>
                    <th><?= __('Is Default') ?></th>
                    <td><?= $invoiceSeries->is_default ? __('Yes') : __('No'); ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceSeries $invoiceSeries
 * @var \Cake\Collection\CollectionInterface|string[] $companies
 * @var \Cake\Collection\CollectionInterface|string[] $invoiceSeriesTypes
 * @var \Cake\Collection\CollectionInterface|string[] $invoiceSeriesPeriods
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('List Invoice Series'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceSeries form content">
            <?= $this->Form->create($invoiceSeries) ?>
            <fieldset>
                <legend><?= __('Add Invoice Series') ?></legend>
                <?php
                    echo $this->Form->control('company_id', ['options' => $companies]);
                    echo $this->Form->control('invoice_series_type_id', ['options' => $invoiceSeriesTypes, 'empty' => true]);
                    echo $this->Form->control('invoice_series_period_id', ['options' => $invoiceSeriesPeriods, 'empty' => true]);
                    echo $this->Form->control('is_default');
                    echo $this->Form->control('name');
                    echo $this->Form->control('series_template');
                    echo $this->Form->control('starting_number');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

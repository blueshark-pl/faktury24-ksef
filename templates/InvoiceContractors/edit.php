<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceContractor $invoiceContractor
 * @var string[]|\Cake\Collection\CollectionInterface $invoices
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $invoiceContractor->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $invoiceContractor->id), 'class' => 'side-nav-item']
            ) ?>
            <?= $this->Html->link(__('List Invoice Contractors'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceContractors form content">
            <?= $this->Form->create($invoiceContractor) ?>
            <fieldset>
                <legend><?= __('Edit Invoice Contractor') ?></legend>
                <?php
                    echo $this->Form->control('invoice_id', ['options' => $invoices]);
                    echo $this->Form->control('name');
                    echo $this->Form->control('nip');
                    echo $this->Form->control('street');
                    echo $this->Form->control('city');
                    echo $this->Form->control('zip');
                    echo $this->Form->control('country');
                    echo $this->Form->control('account_number');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

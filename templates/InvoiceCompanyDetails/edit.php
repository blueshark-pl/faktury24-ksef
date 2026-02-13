<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceCompanyDetail $invoiceCompanyDetail
 * @var string[]|\Cake\Collection\CollectionInterface $invoices
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $invoiceCompanyDetail->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $invoiceCompanyDetail->id), 'class' => 'side-nav-item']
            ) ?>
            <?= $this->Html->link(__('List Invoice Company Details'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceCompanyDetails form content">
            <?= $this->Form->create($invoiceCompanyDetail) ?>
            <fieldset>
                <legend><?= __('Edit Invoice Company Detail') ?></legend>
                <?php
                    echo $this->Form->control('invoice_id', ['options' => $invoices]);
                    echo $this->Form->control('name');
                    echo $this->Form->control('nip');
                    echo $this->Form->control('street');
                    echo $this->Form->control('city');
                    echo $this->Form->control('zip');
                    echo $this->Form->control('country');
                    echo $this->Form->control('bank_account');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

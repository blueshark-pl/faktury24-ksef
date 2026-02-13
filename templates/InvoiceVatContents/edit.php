<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceVatContent $invoiceVatContent
 * @var string[]|\Cake\Collection\CollectionInterface $invoices
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $invoiceVatContent->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $invoiceVatContent->id), 'class' => 'side-nav-item']
            ) ?>
            <?= $this->Html->link(__('List Invoice Vat Contents'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceVatContents form content">
            <?= $this->Form->create($invoiceVatContent) ?>
            <fieldset>
                <legend><?= __('Edit Invoice Vat Content') ?></legend>
                <?php
                    echo $this->Form->control('invoice_id', ['options' => $invoices]);
                    echo $this->Form->control('vat_code_id');
                    echo $this->Form->control('netto');
                    echo $this->Form->control('tax');
                    echo $this->Form->control('brutto');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceVatContent $invoiceVatContent
 * @var \Cake\Collection\CollectionInterface|string[] $invoices
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('List Invoice Vat Contents'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceVatContents form content">
            <?= $this->Form->create($invoiceVatContent) ?>
            <fieldset>
                <legend><?= __('Add Invoice Vat Content') ?></legend>
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

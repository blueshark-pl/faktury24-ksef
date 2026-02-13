<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceContent $invoiceContent
 * @var string[]|\Cake\Collection\CollectionInterface $invoices
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $invoiceContent->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $invoiceContent->id), 'class' => 'side-nav-item']
            ) ?>
            <?= $this->Html->link(__('List Invoice Contents'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceContents form content">
            <?= $this->Form->create($invoiceContent) ?>
            <fieldset>
                <legend><?= __('Edit Invoice Content') ?></legend>
                <?php
                    echo $this->Form->control('invoice_id', ['options' => $invoices]);
                    echo $this->Form->control('vat_code_id');
                    echo $this->Form->control('name');
                    echo $this->Form->control('product_desc');
                    echo $this->Form->control('quantity');
                    echo $this->Form->control('unit');
                    echo $this->Form->control('price');
                    echo $this->Form->control('discount_percent');
                    echo $this->Form->control('netto');
                    echo $this->Form->control('brutto');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

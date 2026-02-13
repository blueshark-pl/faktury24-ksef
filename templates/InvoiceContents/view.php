<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceContent $invoiceContent
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Invoice Content'), ['action' => 'edit', $invoiceContent->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Invoice Content'), ['action' => 'delete', $invoiceContent->id], ['confirm' => __('Are you sure you want to delete # {0}?', $invoiceContent->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Invoice Contents'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Invoice Content'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceContents view content">
            <h3><?= h($invoiceContent->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($invoiceContent->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Invoice') ?></th>
                    <td><?= $invoiceContent->hasValue('invoice') ? $this->Html->link($invoiceContent->invoice->currency, ['controller' => 'Invoices', 'action' => 'view', $invoiceContent->invoice->id]) : '' ?></td>
                </tr>
                <tr>
                    <th><?= __('Vat Code Id') ?></th>
                    <td><?= h($invoiceContent->vat_code_id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($invoiceContent->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Unit') ?></th>
                    <td><?= h($invoiceContent->unit) ?></td>
                </tr>
                <tr>
                    <th><?= __('Quantity') ?></th>
                    <td><?= $this->Number->format($invoiceContent->quantity) ?></td>
                </tr>
                <tr>
                    <th><?= __('Price') ?></th>
                    <td><?= $this->Number->format($invoiceContent->price) ?></td>
                </tr>
                <tr>
                    <th><?= __('Discount Percent') ?></th>
                    <td><?= $this->Number->format($invoiceContent->discount_percent) ?></td>
                </tr>
                <tr>
                    <th><?= __('Netto') ?></th>
                    <td><?= $this->Number->format($invoiceContent->netto) ?></td>
                </tr>
                <tr>
                    <th><?= __('Brutto') ?></th>
                    <td><?= $this->Number->format($invoiceContent->brutto) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($invoiceContent->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($invoiceContent->modified) ?></td>
                </tr>
            </table>
            <div class="text">
                <strong><?= __('Product Desc') ?></strong>
                <blockquote>
                    <?= $this->Text->autoParagraph(h($invoiceContent->product_desc)); ?>
                </blockquote>
            </div>
        </div>
    </div>
</div>
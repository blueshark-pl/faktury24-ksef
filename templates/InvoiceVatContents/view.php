<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceVatContent $invoiceVatContent
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Invoice Vat Content'), ['action' => 'edit', $invoiceVatContent->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Invoice Vat Content'), ['action' => 'delete', $invoiceVatContent->id], ['confirm' => __('Are you sure you want to delete # {0}?', $invoiceVatContent->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Invoice Vat Contents'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Invoice Vat Content'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceVatContents view content">
            <h3><?= h($invoiceVatContent->id) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($invoiceVatContent->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Invoice') ?></th>
                    <td><?= $invoiceVatContent->hasValue('invoice') ? $this->Html->link($invoiceVatContent->invoice->currency, ['controller' => 'Invoices', 'action' => 'view', $invoiceVatContent->invoice->id]) : '' ?></td>
                </tr>
                <tr>
                    <th><?= __('Vat Code Id') ?></th>
                    <td><?= h($invoiceVatContent->vat_code_id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Netto') ?></th>
                    <td><?= $this->Number->format($invoiceVatContent->netto) ?></td>
                </tr>
                <tr>
                    <th><?= __('Tax') ?></th>
                    <td><?= $this->Number->format($invoiceVatContent->tax) ?></td>
                </tr>
                <tr>
                    <th><?= __('Brutto') ?></th>
                    <td><?= $this->Number->format($invoiceVatContent->brutto) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($invoiceVatContent->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($invoiceVatContent->modified) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
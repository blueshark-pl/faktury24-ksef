<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceContractor $invoiceContractor
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Invoice Contractor'), ['action' => 'edit', $invoiceContractor->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Invoice Contractor'), ['action' => 'delete', $invoiceContractor->id], ['confirm' => __('Are you sure you want to delete # {0}?', $invoiceContractor->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Invoice Contractors'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Invoice Contractor'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceContractors view content">
            <h3><?= h($invoiceContractor->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($invoiceContractor->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Invoice') ?></th>
                    <td><?= $invoiceContractor->hasValue('invoice') ? $this->Html->link($invoiceContractor->invoice->currency, ['controller' => 'Invoices', 'action' => 'view', $invoiceContractor->invoice->id]) : '' ?></td>
                </tr>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($invoiceContractor->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Nip') ?></th>
                    <td><?= h($invoiceContractor->nip) ?></td>
                </tr>
                <tr>
                    <th><?= __('Street') ?></th>
                    <td><?= h($invoiceContractor->street) ?></td>
                </tr>
                <tr>
                    <th><?= __('City') ?></th>
                    <td><?= h($invoiceContractor->city) ?></td>
                </tr>
                <tr>
                    <th><?= __('Zip') ?></th>
                    <td><?= h($invoiceContractor->zip) ?></td>
                </tr>
                <tr>
                    <th><?= __('Country') ?></th>
                    <td><?= h($invoiceContractor->country) ?></td>
                </tr>
                <tr>
                    <th><?= __('Account Number') ?></th>
                    <td><?= h($invoiceContractor->account_number) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($invoiceContractor->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($invoiceContractor->modified) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
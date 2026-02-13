<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\InvoiceCompanyDetail $invoiceCompanyDetail
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Invoice Company Detail'), ['action' => 'edit', $invoiceCompanyDetail->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Invoice Company Detail'), ['action' => 'delete', $invoiceCompanyDetail->id], ['confirm' => __('Are you sure you want to delete # {0}?', $invoiceCompanyDetail->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Invoice Company Details'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Invoice Company Detail'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="invoiceCompanyDetails view content">
            <h3><?= h($invoiceCompanyDetail->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($invoiceCompanyDetail->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Invoice') ?></th>
                    <td><?= $invoiceCompanyDetail->hasValue('invoice') ? $this->Html->link($invoiceCompanyDetail->invoice->currency, ['controller' => 'Invoices', 'action' => 'view', $invoiceCompanyDetail->invoice->id]) : '' ?></td>
                </tr>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($invoiceCompanyDetail->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Nip') ?></th>
                    <td><?= h($invoiceCompanyDetail->nip) ?></td>
                </tr>
                <tr>
                    <th><?= __('Street') ?></th>
                    <td><?= h($invoiceCompanyDetail->street) ?></td>
                </tr>
                <tr>
                    <th><?= __('City') ?></th>
                    <td><?= h($invoiceCompanyDetail->city) ?></td>
                </tr>
                <tr>
                    <th><?= __('Zip') ?></th>
                    <td><?= h($invoiceCompanyDetail->zip) ?></td>
                </tr>
                <tr>
                    <th><?= __('Country') ?></th>
                    <td><?= h($invoiceCompanyDetail->country) ?></td>
                </tr>
                <tr>
                    <th><?= __('Bank Account') ?></th>
                    <td><?= h($invoiceCompanyDetail->bank_account) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($invoiceCompanyDetail->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($invoiceCompanyDetail->modified) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
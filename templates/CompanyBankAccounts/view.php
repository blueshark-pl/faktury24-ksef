<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\CompanyBankAccount $companyBankAccount
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Company Bank Account'), ['action' => 'edit', $companyBankAccount->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Company Bank Account'), ['action' => 'delete', $companyBankAccount->id], ['confirm' => __('Are you sure you want to delete # {0}?', $companyBankAccount->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Company Bank Accounts'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Company Bank Account'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="companyBankAccounts view content">
            <h3><?= h($companyBankAccount->label) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($companyBankAccount->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Company Id') ?></th>
                    <td><?= h($companyBankAccount->company_id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Iban') ?></th>
                    <td><?= h($companyBankAccount->iban) ?></td>
                </tr>
                <tr>
                    <th><?= __('Bank Name') ?></th>
                    <td><?= h($companyBankAccount->bank_name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Currency') ?></th>
                    <td><?= h($companyBankAccount->currency) ?></td>
                </tr>
                <tr>
                    <th><?= __('Label') ?></th>
                    <td><?= h($companyBankAccount->label) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($companyBankAccount->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($companyBankAccount->modified) ?></td>
                </tr>
                <tr>
                    <th><?= __('Is Default') ?></th>
                    <td><?= $companyBankAccount->is_default ? __('Yes') : __('No'); ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
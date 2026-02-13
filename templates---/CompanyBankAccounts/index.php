<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\CompanyBankAccount> $companyBankAccounts
 */
?>
<div class="companyBankAccounts index content">
    <?= $this->Html->link(__('New Company Bank Account'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Company Bank Accounts') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('company_id') ?></th>
                    <th><?= $this->Paginator->sort('iban') ?></th>
                    <th><?= $this->Paginator->sort('bank_name') ?></th>
                    <th><?= $this->Paginator->sort('currency') ?></th>
                    <th><?= $this->Paginator->sort('is_default') ?></th>
                    <th><?= $this->Paginator->sort('label') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companyBankAccounts as $companyBankAccount): ?>
                <tr>
                    <td><?= h($companyBankAccount->id) ?></td>
                    <td><?= h($companyBankAccount->company_id) ?></td>
                    <td><?= h($companyBankAccount->iban) ?></td>
                    <td><?= h($companyBankAccount->bank_name) ?></td>
                    <td><?= h($companyBankAccount->currency) ?></td>
                    <td><?= h($companyBankAccount->is_default) ?></td>
                    <td><?= h($companyBankAccount->label) ?></td>
                    <td><?= h($companyBankAccount->created) ?></td>
                    <td><?= h($companyBankAccount->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $companyBankAccount->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $companyBankAccount->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $companyBankAccount->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $companyBankAccount->id),
                            ]
                        ) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
    </div>
</div>
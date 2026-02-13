<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\Company> $companies
 */
?>
<div class="companies index content">
    <?= $this->Html->link(__('New Company'), ['action' => 'add'], ['class' => 'button float-right']) ?>
    <h3><?= __('Companies') ?></h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('id') ?></th>
                    <th><?= $this->Paginator->sort('name') ?></th>
                    <th><?= $this->Paginator->sort('altname') ?></th>
                    <th><?= $this->Paginator->sort('nip') ?></th>
                    <th><?= $this->Paginator->sort('regon') ?></th>
                    <th><?= $this->Paginator->sort('country') ?></th>
                    <th><?= $this->Paginator->sort('postal_code') ?></th>
                    <th><?= $this->Paginator->sort('city') ?></th>
                    <th><?= $this->Paginator->sort('street') ?></th>
                    <th><?= $this->Paginator->sort('local_number') ?></th>
                    <th><?= $this->Paginator->sort('phone') ?></th>
                    <th><?= $this->Paginator->sort('bank_name') ?></th>
                    <th><?= $this->Paginator->sort('bank_account') ?></th>
                    <th><?= $this->Paginator->sort('logo_url') ?></th>
                    <th><?= $this->Paginator->sort('issuer') ?></th>
                    <th><?= $this->Paginator->sort('vat_payer') ?></th>
                    <th><?= $this->Paginator->sort('register_date') ?></th>
                    <th><?= $this->Paginator->sort('subscription_end') ?></th>
                    <th><?= $this->Paginator->sort('is_active') ?></th>
                    <th><?= $this->Paginator->sort('invoice_template') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th><?= $this->Paginator->sort('modified') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                <tr>
                    <td><?= h($company->id) ?></td>
                    <td><?= h($company->name) ?></td>
                    <td><?= h($company->altname) ?></td>
                    <td><?= h($company->nip) ?></td>
                    <td><?= h($company->regon) ?></td>
                    <td><?= h($company->country) ?></td>
                    <td><?= h($company->postal_code) ?></td>
                    <td><?= h($company->city) ?></td>
                    <td><?= h($company->street) ?></td>
                    <td><?= h($company->local_number) ?></td>
                    <td><?= h($company->phone) ?></td>
                    <td><?= h($company->bank_name) ?></td>
                    <td><?= h($company->bank_account) ?></td>
                    <td><?= h($company->logo_url) ?></td>
                    <td><?= h($company->issuer) ?></td>
                    <td><?= h($company->vat_payer) ?></td>
                    <td><?= h($company->register_date) ?></td>
                    <td><?= h($company->subscription_end) ?></td>
                    <td><?= h($company->is_active) ?></td>
                    <td><?= h($company->invoice_template) ?></td>
                    <td><?= h($company->created) ?></td>
                    <td><?= h($company->modified) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $company->id]) ?>
                        <?= $this->Html->link(__('Edit'), ['action' => 'edit', $company->id]) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $company->id],
                            [
                                'method' => 'delete',
                                'confirm' => __('Are you sure you want to delete # {0}?', $company->id),
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
<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Contractor $contractor
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Contractor'), ['action' => 'edit', $contractor->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Contractor'), ['action' => 'delete', $contractor->id], ['confirm' => __('Are you sure you want to delete # {0}?', $contractor->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Contractors'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Contractor'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="contractors view content">
            <h3><?= h($contractor->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($contractor->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Company') ?></th>
                    <td><?= $contractor->hasValue('company') ? $this->Html->link($contractor->company->name, ['controller' => 'Companies', 'action' => 'view', $contractor->company->id]) : '' ?></td>
                </tr>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($contractor->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Altname') ?></th>
                    <td><?= h($contractor->altname) ?></td>
                </tr>
                <tr>
                    <th><?= __('Nip') ?></th>
                    <td><?= h($contractor->nip) ?></td>
                </tr>
                <tr>
                    <th><?= __('Regon') ?></th>
                    <td><?= h($contractor->regon) ?></td>
                </tr>
                <tr>
                    <th><?= __('Country') ?></th>
                    <td><?= h($contractor->country) ?></td>
                </tr>
                <tr>
                    <th><?= __('Postal Code') ?></th>
                    <td><?= h($contractor->postal_code) ?></td>
                </tr>
                <tr>
                    <th><?= __('City') ?></th>
                    <td><?= h($contractor->city) ?></td>
                </tr>
                <tr>
                    <th><?= __('Street') ?></th>
                    <td><?= h($contractor->street) ?></td>
                </tr>
                <tr>
                    <th><?= __('Local Number') ?></th>
                    <td><?= h($contractor->local_number) ?></td>
                </tr>
                <tr>
                    <th><?= __('Phone') ?></th>
                    <td><?= h($contractor->phone) ?></td>
                </tr>
                <tr>
                    <th><?= __('Email') ?></th>
                    <td><?= h($contractor->email) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($contractor->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($contractor->modified) ?></td>
                </tr>
                <tr>
                    <th><?= __('Eu Vat') ?></th>
                    <td><?= $contractor->eu_vat ? __('Yes') : __('No'); ?></td>
                </tr>
                <tr>
                    <th><?= __('Is Active') ?></th>
                    <td><?= $contractor->is_active ? __('Yes') : __('No'); ?></td>
                </tr>
            </table>
            <div class="text">
                <strong><?= __('Notes') ?></strong>
                <blockquote>
                    <?= $this->Text->autoParagraph(h($contractor->notes)); ?>
                </blockquote>
            </div>
            <div class="related">
                <h4><?= __('Related Contractor Bank Accounts') ?></h4>
                <?php if (!empty($contractor->contractor_bank_accounts)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Contractor Id') ?></th>
                            <th><?= __('Iban') ?></th>
                            <th><?= __('Bank Name') ?></th>
                            <th><?= __('Currency') ?></th>
                            <th><?= __('Is Default') ?></th>
                            <th><?= __('Label') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($contractor->contractor_bank_accounts as $contractorBankAccount) : ?>
                        <tr>
                            <td><?= h($contractorBankAccount->id) ?></td>
                            <td><?= h($contractorBankAccount->contractor_id) ?></td>
                            <td><?= h($contractorBankAccount->iban) ?></td>
                            <td><?= h($contractorBankAccount->bank_name) ?></td>
                            <td><?= h($contractorBankAccount->currency) ?></td>
                            <td><?= h($contractorBankAccount->is_default) ?></td>
                            <td><?= h($contractorBankAccount->label) ?></td>
                            <td><?= h($contractorBankAccount->created) ?></td>
                            <td><?= h($contractorBankAccount->modified) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'ContractorBankAccounts', 'action' => 'view', $contractorBankAccount->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'ContractorBankAccounts', 'action' => 'edit', $contractorBankAccount->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'ContractorBankAccounts', 'action' => 'delete', $contractorBankAccount->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $contractorBankAccount->id),
                                    ]
                                ) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
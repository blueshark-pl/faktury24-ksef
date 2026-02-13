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
            <?= $this->Html->link(__('List Company Bank Accounts'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="companyBankAccounts form content">
            <?= $this->Form->create($companyBankAccount) ?>
            <fieldset>
                <legend><?= __('Add Company Bank Account') ?></legend>
                <?php
                    echo $this->Form->control('company_id');
                    echo $this->Form->control('iban');
                    echo $this->Form->control('bank_name');
                    echo $this->Form->control('currency');
                    echo $this->Form->control('is_default');
                    echo $this->Form->control('label');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

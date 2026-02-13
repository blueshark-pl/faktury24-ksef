<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Company $company
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('List Companies'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="companies form content">
            <?= $this->Form->create($company) ?>
            <fieldset>
                <legend><?= __('Add Company') ?></legend>
                <?php
                    echo $this->Form->control('name');
                    echo $this->Form->control('altname');
                    echo $this->Form->control('nip');
                    echo $this->Form->control('regon');
                    echo $this->Form->control('country');
                    echo $this->Form->control('postal_code');
                    echo $this->Form->control('city');
                    echo $this->Form->control('street');
                    echo $this->Form->control('local_number');
                    echo $this->Form->control('phone');
                    echo $this->Form->control('bank_name');
                    echo $this->Form->control('bank_account');
                    echo $this->Form->control('logo_url');
                    echo $this->Form->control('issuer');
                    echo $this->Form->control('vat_payer');
                    echo $this->Form->control('register_date', ['empty' => true]);
                    echo $this->Form->control('subscription_end', ['empty' => true]);
                    echo $this->Form->control('is_active');
                    echo $this->Form->control('invoice_template');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

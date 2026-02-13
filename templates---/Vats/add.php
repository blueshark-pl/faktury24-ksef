<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Vat $vat
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('List Vats'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="vats form content">
            <?= $this->Form->create($vat) ?>
            <fieldset>
                <legend><?= __('Add Vat') ?></legend>
                <?php
                    echo $this->Form->control('name');
                    echo $this->Form->control('rate');
                    echo $this->Form->control('deleted');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

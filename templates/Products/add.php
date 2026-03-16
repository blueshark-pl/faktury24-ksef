<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Product $product
 * @var \Cake\Collection\CollectionInterface|string[] $companies
 * @var \Cake\Collection\CollectionInterface|string[] $units
 * @var \Cake\Collection\CollectionInterface|string[] $vats
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('List Products'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="products form content">
            <?= $this->Form->create($product) ?>
            <fieldset>
                <legend><?= __('Add Product') ?></legend>
                <?php
                    echo $this->Form->control('company_id', ['options' => $companies]);
                    echo $this->Form->control('code');
                    echo $this->Form->control('name');
                    echo $this->Form->control('description');
                    echo $this->Form->control('is_service');
                    echo $this->Form->control('unit_id', ['options' => $units]);
                    echo $this->Form->control('vat_id', ['options' => $vats]);
                    echo $this->Form->control('net_price');
                    echo $this->Form->control('currency');
                    echo $this->Form->control('pkwiu');
                    echo $this->Form->control('gtu_code');
                    echo $this->Form->control('barcode');
                ?>

                <hr>
                <h5>Pola KSeF / klasyfikacje</h5>
                <?php
                    echo $this->Form->control('gtin', ['label' => 'GTIN (kod handlowy)', 'required' => false]);
                    echo $this->Form->control('cn_code', ['label' => 'Kod CN (Nomenklatura Scalona)', 'required' => false]);
                    echo $this->Form->control('pkob', ['label' => 'PKOB (klasyfikacja obiektów budowlanych)', 'required' => false]);
                    echo $this->Form->control('is_attachment15', ['label' => 'Towar/usługa z załącznika 15 (obowiązkowy MPP)', 'type' => 'checkbox']);
                    echo $this->Form->control('excise_amount', ['label' => 'Kwota podatku akcyzowego', 'type' => 'number', 'step' => '0.01', 'required' => false]);
                    echo $this->Form->control('procedure_marking', [
                        'label' => 'Oznaczenie procedury',
                        'type' => 'select',
                        'options' => [
                            '' => '— brak —',
                            'WSTO_EE' => 'WSTO_EE – sprzedaż na odległość (UE)',
                            'IED' => 'IED – dostawa przez interfejs elektroniczny',
                            'TT_D' => 'TT_D – transakcja trójstronna uproszczona',
                            'I_42' => 'I_42 – WDT po imporcie (proc. celna 42)',
                            'I_63' => 'I_63 – WDT po imporcie (proc. celna 63)',
                            'B_SPV' => 'B_SPV – transfer bonu jednego przeznaczenia',
                            'B_SPV_DOSTAWA' => 'B_SPV_DOSTAWA – dostawa dot. bonu jedn. przezn.',
                            'B_MPV_PROWIZJA' => 'B_MPV_PROWIZJA – prowizja dot. bonu różn. przezn.',
                        ],
                        'empty' => false,
                    ]);
                    echo $this->Form->control('is_active');
                    echo $this->Form->control('deleted');
                ?>
            </fieldset>
            <?= $this->Form->button(__('Submit')) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Unit $unit
 */
?>
<div class="row">
    <aside class="column">
        <div class="side-nav">
            <h4 class="heading"><?= __('Actions') ?></h4>
            <?= $this->Html->link(__('Edit Unit'), ['action' => 'edit', $unit->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Unit'), ['action' => 'delete', $unit->id], ['confirm' => __('Are you sure you want to delete # {0}?', $unit->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Units'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Unit'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="units view content">
            <h3><?= h($unit->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($unit->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= $this->Number->format($unit->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($unit->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($unit->modified) ?></td>
                </tr>
                <tr>
                    <th><?= __('Deleted') ?></th>
                    <td><?= $unit->deleted ? __('Yes') : __('No'); ?></td>
                </tr>
            </table>
            <div class="related">
                <h4><?= __('Related Products') ?></h4>
                <?php if (!empty($unit->products)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Company Id') ?></th>
                            <th><?= __('Code') ?></th>
                            <th><?= __('Name') ?></th>
                            <th><?= __('Description') ?></th>
                            <th><?= __('Is Service') ?></th>
                            <th><?= __('Unit Id') ?></th>
                            <th><?= __('Vat Id') ?></th>
                            <th><?= __('Net Price') ?></th>
                            <th><?= __('Currency') ?></th>
                            <th><?= __('Pkwiu') ?></th>
                            <th><?= __('Gtu Code') ?></th>
                            <th><?= __('Barcode') ?></th>
                            <th><?= __('Is Active') ?></th>
                            <th><?= __('Deleted') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($unit->products as $product) : ?>
                        <tr>
                            <td><?= h($product->id) ?></td>
                            <td><?= h($product->company_id) ?></td>
                            <td><?= h($product->code) ?></td>
                            <td><?= h($product->name) ?></td>
                            <td><?= h($product->description) ?></td>
                            <td><?= h($product->is_service) ?></td>
                            <td><?= h($product->unit_id) ?></td>
                            <td><?= h($product->vat_id) ?></td>
                            <td><?= h($product->net_price) ?></td>
                            <td><?= h($product->currency) ?></td>
                            <td><?= h($product->pkwiu) ?></td>
                            <td><?= h($product->gtu_code) ?></td>
                            <td><?= h($product->barcode) ?></td>
                            <td><?= h($product->is_active) ?></td>
                            <td><?= h($product->deleted) ?></td>
                            <td><?= h($product->created) ?></td>
                            <td><?= h($product->modified) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'Products', 'action' => 'view', $product->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'Products', 'action' => 'edit', $product->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'Products', 'action' => 'delete', $product->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $product->id),
                                    ]
                                ) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="related">
                <h4><?= __('Related Services') ?></h4>
                <?php if (!empty($unit->services)) : ?>
                <div class="table-responsive">
                    <table>
                        <tr>
                            <th><?= __('Id') ?></th>
                            <th><?= __('Company Id') ?></th>
                            <th><?= __('Unit Id') ?></th>
                            <th><?= __('Vat Id') ?></th>
                            <th><?= __('Name') ?></th>
                            <th><?= __('Net Price') ?></th>
                            <th><?= __('Description') ?></th>
                            <th><?= __('Created') ?></th>
                            <th><?= __('Modified') ?></th>
                            <th><?= __('Gs Uuid') ?></th>
                            <th><?= __('Gs Bu Uuid') ?></th>
                            <th><?= __('Gs Name') ?></th>
                            <th><?= __('Gs Unit') ?></th>
                            <th><?= __('Gs Net Price') ?></th>
                            <th><?= __('Gs Vat Uuid') ?></th>
                            <th><?= __('Gs Rvr Pkwiu') ?></th>
                            <th><?= __('Gs Rvr Add Info') ?></th>
                            <th><?= __('Gs Addedby') ?></th>
                            <th><?= __('Gs Addedip') ?></th>
                            <th><?= __('Gs Addedon') ?></th>
                            <th><?= __('Gs Modifiedby') ?></th>
                            <th><?= __('Gs Modifiedip') ?></th>
                            <th><?= __('Gs Modifiedon') ?></th>
                            <th><?= __('Gs Deletedby') ?></th>
                            <th><?= __('Gs Deletedip') ?></th>
                            <th><?= __('Gs Deletedon') ?></th>
                            <th><?= __('Gs Service Id') ?></th>
                            <th><?= __('Gs Service Rabat') ?></th>
                            <th><?= __('Gs Service Uwagi') ?></th>
                            <th><?= __('Gs Service Special Type') ?></th>
                            <th><?= __('Gs Service Service Type') ?></th>
                            <th class="actions"><?= __('Actions') ?></th>
                        </tr>
                        <?php foreach ($unit->services as $service) : ?>
                        <tr>
                            <td><?= h($service->id) ?></td>
                            <td><?= h($service->company_id) ?></td>
                            <td><?= h($service->unit_id) ?></td>
                            <td><?= h($service->vat_id) ?></td>
                            <td><?= h($service->name) ?></td>
                            <td><?= h($service->net_price) ?></td>
                            <td><?= h($service->description) ?></td>
                            <td><?= h($service->created) ?></td>
                            <td><?= h($service->modified) ?></td>
                            <td><?= h($service->gs_uuid) ?></td>
                            <td><?= h($service->gs_bu_uuid) ?></td>
                            <td><?= h($service->gs_name) ?></td>
                            <td><?= h($service->gs_unit) ?></td>
                            <td><?= h($service->gs_net_price) ?></td>
                            <td><?= h($service->gs_vat_uuid) ?></td>
                            <td><?= h($service->gs_rvr_pkwiu) ?></td>
                            <td><?= h($service->gs_rvr_add_info) ?></td>
                            <td><?= h($service->gs_addedby) ?></td>
                            <td><?= h($service->gs_addedip) ?></td>
                            <td><?= h($service->gs_addedon) ?></td>
                            <td><?= h($service->gs_modifiedby) ?></td>
                            <td><?= h($service->gs_modifiedip) ?></td>
                            <td><?= h($service->gs_modifiedon) ?></td>
                            <td><?= h($service->gs_deletedby) ?></td>
                            <td><?= h($service->gs_deletedip) ?></td>
                            <td><?= h($service->gs_deletedon) ?></td>
                            <td><?= h($service->gs_service_id) ?></td>
                            <td><?= h($service->gs_service_rabat) ?></td>
                            <td><?= h($service->gs_service_uwagi) ?></td>
                            <td><?= h($service->gs_service_special_type) ?></td>
                            <td><?= h($service->gs_service_service_type) ?></td>
                            <td class="actions">
                                <?= $this->Html->link(__('View'), ['controller' => 'Services', 'action' => 'view', $service->id]) ?>
                                <?= $this->Html->link(__('Edit'), ['controller' => 'Services', 'action' => 'edit', $service->id]) ?>
                                <?= $this->Form->postLink(
                                    __('Delete'),
                                    ['controller' => 'Services', 'action' => 'delete', $service->id],
                                    [
                                        'method' => 'delete',
                                        'confirm' => __('Are you sure you want to delete # {0}?', $service->id),
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
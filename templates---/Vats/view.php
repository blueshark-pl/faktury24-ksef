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
            <?= $this->Html->link(__('Edit Vat'), ['action' => 'edit', $vat->id], ['class' => 'side-nav-item']) ?>
            <?= $this->Form->postLink(__('Delete Vat'), ['action' => 'delete', $vat->id], ['confirm' => __('Are you sure you want to delete # {0}?', $vat->id), 'class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('List Vats'), ['action' => 'index'], ['class' => 'side-nav-item']) ?>
            <?= $this->Html->link(__('New Vat'), ['action' => 'add'], ['class' => 'side-nav-item']) ?>
        </div>
    </aside>
    <div class="column column-80">
        <div class="vats view content">
            <h3><?= h($vat->name) ?></h3>
            <table>
                <tr>
                    <th><?= __('Id') ?></th>
                    <td><?= h($vat->id) ?></td>
                </tr>
                <tr>
                    <th><?= __('Name') ?></th>
                    <td><?= h($vat->name) ?></td>
                </tr>
                <tr>
                    <th><?= __('Rate') ?></th>
                    <td><?= $this->Number->format($vat->rate) ?></td>
                </tr>
                <tr>
                    <th><?= __('Created') ?></th>
                    <td><?= h($vat->created) ?></td>
                </tr>
                <tr>
                    <th><?= __('Modified') ?></th>
                    <td><?= h($vat->modified) ?></td>
                </tr>
                <tr>
                    <th><?= __('Deleted') ?></th>
                    <td><?= $vat->deleted ? __('Yes') : __('No'); ?></td>
                </tr>
            </table>
            <div class="related">
                <h4><?= __('Related Services') ?></h4>
                <?php if (!empty($vat->services)) : ?>
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
                        <?php foreach ($vat->services as $service) : ?>
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
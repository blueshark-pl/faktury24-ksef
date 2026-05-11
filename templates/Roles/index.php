<?php
/**
 * @var \App\View\AppView                $this
 * @var \Cake\ORM\ResultSet              $roles
 * @var array<string, int>               $countByCode
 */
$this->assign('title', __('Role i uprawnienia'));
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="ri-shield-user-line me-1"></i><?= __('Role i uprawnienia') ?></h4>
        <div class="text-muted small mt-1"><?= __('Zarządzanie rolami systemowymi i własnymi. Każdej roli przypisujesz zestaw uprawnień.') ?></div>
    </div>
    <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-primary btn-sm">
        <i class="ri-add-line me-1"></i><?= __('Dodaj rolę') ?>
    </a>
</div>

<?= $this->Flash->render() ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" style="font-size:.875rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:240px"><?= __('Nazwa') ?></th>
                    <th><?= __('Kod') ?></th>
                    <th style="width:80px" class="text-center"><?= __('Typ') ?></th>
                    <th style="width:120px" class="text-center"><?= __('Uprawnień') ?></th>
                    <th style="width:120px" class="text-center"><?= __('Użytkowników') ?></th>
                    <th style="width:90px"  class="text-center"><?= __('Aktywna') ?></th>
                    <th class="pe-3 text-end" style="width:160px"><?= __('Akcje') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($roles) || count($roles->toArray()) === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="ri-shield-line" style="font-size:2em"></i><br>
                            <?= __('Brak ról.') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($roles as $r):
                        $cnt = $countByCode[$r->code] ?? 0;
                        $permsCnt = is_iterable($r->permissions) ? count((array)$r->permissions) : 0;
                    ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-semibold"><?= h($r->name) ?></div>
                            <?php if ($r->description): ?>
                                <div class="text-muted small mt-1"><?= h($r->description) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><code><?= h($r->code) ?></code></td>
                        <td class="text-center">
                            <?php if ($r->is_system): ?>
                                <span class="badge bg-info-transparent" title="<?= __('Rola systemowa — nie można usunąć') ?>"><i class="ri-shield-check-line"></i> <?= __('systemowa') ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary-transparent"><?= __('własna') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php if ($r->code === 'admin'): ?>
                            <span class="badge bg-warning-transparent" title="<?= __('Admin ma wildcard w permissions.php') ?>">*</span>
                        <?php else: ?>
                            <?= (int)$permsCnt ?>
                        <?php endif; ?></td>
                        <td class="text-center"><?= (int)$cnt ?></td>
                        <td class="text-center">
                            <?php if ($r->is_active): ?>
                                <i class="ri-checkbox-circle-fill text-success" title="<?= __('Aktywna') ?>"></i>
                            <?php else: ?>
                                <i class="ri-close-circle-fill text-muted" title="<?= __('Nieaktywna') ?>"></i>
                            <?php endif; ?>
                        </td>
                        <td class="pe-3 text-end">
                            <a href="<?= $this->Url->build(['action' => 'edit', $r->id]) ?>" class="btn btn-sm btn-outline-primary me-1" title="<?= __('Edytuj') ?>">
                                <i class="ri-edit-line"></i>
                            </a>
                            <?php if (!$r->is_system): ?>
                                <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>',
                                    ['action' => 'delete', $r->id],
                                    [
                                        'class'   => 'btn btn-sm btn-outline-danger',
                                        'escape'  => false,
                                        'confirm' => __('Usunąć rolę {0}? Tej operacji nie można cofnąć.', $r->name),
                                    ]) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

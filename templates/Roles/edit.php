<?php
/**
 * @var \App\View\AppView                           $this
 * @var \App\Model\Entity\Role                      $role
 * @var array<string, \App\Model\Entity\Permission[]> $permissionsByCategory
 * @var int[]                                       $selectedPermissionIds
 */
$this->assign('title', __('Edycja roli: {0}', $role->name));

$selectedSet = array_flip($selectedPermissionIds);

$catLabels = [
    'invoices'      => __('Faktury sprzedaży'),
    'contractors'   => __('Kontrahenci'),
    'speed_orders'  => __('Zlecenia transportowe'),
    'crm'           => __('CRM Leady'),
    'cost_invoices' => __('Faktury kosztowe'),
    'finance'       => __('Rozliczenia, bank, KSeF'),
    'fleet'         => __('Flota pojazdów'),
    'route'         => __('Planer tras i oferty'),
    'reports'       => __('Raporty i compliance'),
    'fuel_cards'    => __('Karty paliwowe E100'),
    'support'       => __('Zgłoszenia i zadania'),
    'admin'         => __('Administracja'),
    'portal'        => __('Portal klienta'),
    'general'       => __('Inne'),
];
$catIcons = [
    'invoices'      => 'ri-file-list-3-line',
    'contractors'   => 'ri-building-line',
    'speed_orders'  => 'ri-truck-line',
    'crm'           => 'ri-user-search-line',
    'cost_invoices' => 'ri-bill-line',
    'finance'       => 'ri-bank-line',
    'fleet'         => 'ri-roadster-line',
    'route'         => 'ri-route-line',
    'reports'       => 'ri-bar-chart-line',
    'fuel_cards'    => 'ri-gas-station-line',
    'support'       => 'ri-customer-service-2-line',
    'admin'         => 'ri-shield-keyhole-line',
    'portal'        => 'ri-global-line',
    'general'       => 'ri-folder-line',
];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold">
            <i class="ri-shield-user-line me-1"></i>
            <?= __('Edycja roli') ?>: <code><?= h($role->code) ?></code>
            <?php if ($role->is_system): ?>
                <span class="badge bg-info-transparent ms-1"><?= __('systemowa') ?></span>
            <?php endif; ?>
        </h4>
        <div class="text-muted small mt-1"><?= h($role->name) ?></div>
    </div>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i><?= __('Wróć') ?>
    </a>
</div>

<?= $this->Flash->render() ?>

<?= $this->Form->create($role) ?>

<div class="row g-3">
    <!-- Lewa kolumna: dane podstawowe -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent">
                <strong><?= __('Dane podstawowe') ?></strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label"><?= __('Kod') ?></label>
                    <?php if ($role->is_system): ?>
                        <input type="text" class="form-control" value="<?= h($role->code) ?>" disabled>
                        <div class="form-text"><?= __('Kod roli systemowej jest zablokowany.') ?></div>
                    <?php else: ?>
                        <?= $this->Form->control('code', ['label' => false, 'class' => 'form-control', 'value' => $role->code]) ?>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label"><?= __('Nazwa') ?> <span class="text-danger">*</span></label>
                    <?= $this->Form->control('name', ['label' => false, 'class' => 'form-control', 'required' => true]) ?>
                </div>

                <div class="mb-3">
                    <label class="form-label"><?= __('Opis') ?></label>
                    <?= $this->Form->control('description', ['label' => false, 'class' => 'form-control', 'type' => 'textarea', 'rows' => 3]) ?>
                </div>

                <div class="form-check mb-3">
                    <?= $this->Form->checkbox('is_active', ['class' => 'form-check-input', 'id' => 'is_active', 'checked' => $role->is_active]) ?>
                    <label class="form-check-label" for="is_active"><?= __('Aktywna') ?></label>
                </div>
            </div>
        </div>
    </div>

    <!-- Prawa kolumna: matrix uprawnień -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <strong><?= __('Uprawnienia') ?></strong>
                <?php if (in_array($role->code, ['admin', 'pracownik_administracyjny'], true)): ?>
                    <span class="badge bg-warning-transparent">
                        <i class="ri-information-line"></i> <?= __('Ta rola ma wildcard w permissions.php — uprawnienia z DB są ignorowane') ?>
                    </span>
                <?php else: ?>
                    <div class="d-flex gap-2 small">
                        <a href="#" id="selectAllPerms" class="text-decoration-none"><i class="ri-checkbox-multiple-line"></i> <?= __('Zaznacz wszystko') ?></a>
                        <span class="text-muted">|</span>
                        <a href="#" id="clearAllPerms" class="text-decoration-none"><i class="ri-checkbox-blank-line"></i> <?= __('Wyczyść') ?></a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (in_array($role->code, ['admin', 'pracownik_administracyjny'], true)): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="ri-shield-star-line me-1"></i>
                        <?php if ($role->code === 'admin'): ?>
                            <?= __('Rola admin ma wpis wildcard (*) w pliku permissions.php i automatycznie ma dostęp do wszystkiego. Zmiana uprawnień w DB nie ma wpływu.') ?>
                        <?php else: ?>
                            <?= __('Rola pracownik_administracyjny ma wildcard w permissions.php — dostęp do wszystkiego jak dotychczasowy „user". Aby ograniczyć zakres, usuń jej wildcard w permissions.php i przypisz konkretne uprawnienia poniżej.') ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($permissionsByCategory as $cat => $perms):
                        $label = $catLabels[$cat] ?? ucfirst($cat);
                        $icon  = $catIcons[$cat]  ?? 'ri-folder-line';
                    ?>
                    <div class="perm-cat mb-3" data-category="<?= h($cat) ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                            <strong class="text-uppercase small text-muted">
                                <i class="<?= h($icon) ?> me-1"></i><?= h($label) ?>
                            </strong>
                            <a href="#" class="cat-toggle small text-decoration-none" title="<?= __('Zaznacz wszystkie w tej kategorii') ?>">
                                <i class="ri-checkbox-multiple-line"></i>
                            </a>
                        </div>
                        <div class="row g-2">
                            <?php foreach ($perms as $p): $checked = isset($selectedSet[(int)$p->id]); ?>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input perm-cb"
                                           name="permissions[]" value="<?= (int)$p->id ?>"
                                           id="perm-<?= (int)$p->id ?>"
                                           <?= $checked ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="perm-<?= (int)$p->id ?>">
                                        <?= h($p->name) ?>
                                        <div class="text-muted small"><code><?= h($p->code) ?></code></div>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary"><i class="ri-save-line me-1"></i><?= __('Zapisz zmiany') ?></button>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary">
        <?= __('Anuluj') ?>
    </a>
</div>

<?= $this->Form->end() ?>

<script>
(function () {
    'use strict';
    var $ = function (sel, root) { return (root || document).querySelectorAll(sel); };

    var selectAll = document.getElementById('selectAllPerms');
    var clearAll  = document.getElementById('clearAllPerms');
    if (selectAll) selectAll.addEventListener('click', function (e) {
        e.preventDefault();
        $('input.perm-cb').forEach(function (cb) { cb.checked = true; });
    });
    if (clearAll) clearAll.addEventListener('click', function (e) {
        e.preventDefault();
        $('input.perm-cb').forEach(function (cb) { cb.checked = false; });
    });
    $('.cat-toggle').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var cat = a.closest('.perm-cat');
            var cbs = cat.querySelectorAll('input.perm-cb');
            var allChecked = Array.prototype.every.call(cbs, function (c) { return c.checked; });
            cbs.forEach(function (c) { c.checked = !allChecked; });
        });
    });
})();
</script>

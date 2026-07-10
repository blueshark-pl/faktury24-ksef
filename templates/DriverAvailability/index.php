<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $drivers
 * @var array $summary
 */
$this->assign('title', __('Dostępność kierowców'));
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= __('Wzorce dostępności kierowców') ?></h1>
        <p class="text-muted small mb-0">
            <?= __('Ustaw stałe godziny pracy per kierowca (PN-ND). Planer użyje tego do filtrowania „kto może jechać".') ?>
        </p>
    </div>
</div>

<?php if (count($drivers) === 0): ?>
    <div class="alert alert-info">
        <?= __('Brak kierowców. Dodaj kierowców w :link.', [
            ':link' => $this->Html->link(__('module Kierowcy'), ['controller' => 'Drivers', 'action' => 'index']),
        ]) ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?= __('Kierowca') ?></th>
                        <th class="text-center"><?= __('Aktywne dni') ?></th>
                        <th><?= __('Preferencje') ?></th>
                        <th class="text-end"><?= __('Akcja') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($drivers as $d):
                        $s = $summary[(string)$d->id] ?? ['defined_days' => 0, 'active_days' => 0, 'accepts_adr' => false, 'accepts_intl' => false];
                        $noPatern = $s['defined_days'] === 0;
                    ?>
                        <tr>
                            <td>
                                <?= h($d->full_name) ?>
                                <?php if ($d->adr_certified): ?>
                                    <span class="badge bg-danger-subtle text-danger ms-1">ADR</span>
                                <?php endif ?>
                            </td>
                            <td class="text-center">
                                <?php if ($noPatern): ?>
                                    <span class="badge bg-warning text-dark"><?= __('brak wzorca') ?></span>
                                <?php else: ?>
                                    <strong><?= (int)$s['active_days'] ?></strong>
                                    <span class="text-muted small">/ 7</span>
                                <?php endif ?>
                            </td>
                            <td class="small">
                                <?php if (!$noPatern): ?>
                                    <?php if ($s['accepts_adr']): ?><span class="badge bg-danger-subtle text-danger">ADR</span><?php endif ?>
                                    <?php if ($s['accepts_intl']): ?><span class="badge bg-info-subtle text-info">MIĘDZY.</span><?php endif ?>
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <?= $this->Html->link(
                                    ($noPatern ? '<i class="ri-add-line"></i> ' : '<i class="ri-edit-line"></i> ') . ($noPatern ? __('Ustaw wzorzec') : __('Edytuj')),
                                    ['action' => 'edit', $d->id],
                                    ['class' => 'btn btn-sm btn-outline-primary', 'escape' => false]
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

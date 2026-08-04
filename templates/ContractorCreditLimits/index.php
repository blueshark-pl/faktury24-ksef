<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ContractorCreditLimit[] $rows
 */
$this->assign('title', __('Limity kredytowe klientów'));
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold"><?= __('Limity kredytowe klientów') ?></h4>
    <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-primary">
        <i class="ri-add-line me-1"></i><?= __('Nowy limit') ?>
    </a>
</div>

<?php if (count($rows) === 0): ?>
    <div class="alert alert-info">
        <?= __('Brak zdefiniowanych limitów. Dodaj pierwszy limit kredytowy dla klienta.') ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= __('NIP') ?></th>
                    <th><?= __('Kontrahent') ?></th>
                    <th class="text-end"><?= __('Limit (PLN)') ?></th>
                    <th class="text-center"><?= __('Warning %') ?></th>
                    <th class="text-center"><?= __('Blokada') ?></th>
                    <th class="text-end"><?= __('Akcje') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><code><?= h($r->contractor_nip) ?></code></td>
                        <td><?= h($r->contractor?->name ?: '-') ?></td>
                        <td class="text-end fw-semibold"><?= number_format((float)$r->credit_limit_pln, 2, ',', ' ') ?></td>
                        <td class="text-center"><?= h($r->warning_threshold_pct) ?>%</td>
                        <td class="text-center">
                            <?php if ($r->is_blocked): ?>
                                <span class="badge bg-danger"><i class="ri-lock-line me-1"></i><?= __('Zablokowany') ?></span>
                            <?php else: ?>
                                <span class="badge bg-success"><i class="ri-shield-check-line me-1"></i>OK</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= $this->Url->build(['action' => 'edit', $r->id]) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="ri-pencil-line"></i>
                            </a>
                            <?= $this->Form->postLink(
                                '<i class="ri-delete-bin-line"></i>',
                                ['action' => 'delete', $r->id],
                                ['escape' => false, 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('Usunąć limit?')]
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

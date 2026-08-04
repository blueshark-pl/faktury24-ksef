<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ContractorCreditLimit $limit
 * @var bool $isEdit
 */
$this->assign('title', $isEdit ? __('Edycja limitu') : __('Nowy limit kredytowy'));
$formUrl = $isEdit
    ? $this->Url->build(['action' => 'edit', $limit->id])
    : $this->Url->build(['action' => 'add']);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold"><?= $isEdit ? __('Edytuj limit') : __('Nowy limit kredytowy') ?></h4>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-close-line me-1"></i><?= __('Anuluj') ?>
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?= $this->Form->create($limit, ['url' => $formUrl]) ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('NIP kontrahenta') ?> *</label>
                    <input type="text" name="contractor_nip" class="form-control" value="<?= h($limit->contractor_nip ?? '') ?>" required maxlength="30">
                    <div class="small text-muted mt-1"><?= __('Bez separatorów. Może być zagraniczny (np. DE123456789).') ?></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Limit kredytowy (PLN)') ?> *</label>
                    <input type="number" step="0.01" min="0" name="credit_limit_pln" class="form-control" value="<?= h($limit->credit_limit_pln ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Próg ostrzeżenia (%)') ?></label>
                    <input type="number" min="10" max="100" name="warning_threshold_pct" class="form-control" value="<?= h($limit->warning_threshold_pct ?? 80) ?>">
                    <div class="small text-muted mt-1"><?= __('Powyżej tego % wykorzystania limitu → yellow alert (default 80%).') ?></div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_blocked" value="1" class="form-check-input" id="is-blocked" <?= !empty($limit->is_blocked) ? 'checked' : '' ?>>
                        <label for="is-blocked" class="form-check-label"><?= __('Blokuj klienta (nowe zlecenia będą oznaczane jako zablokowane)') ?></label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted"><?= __('Powód blokady (opcjonalnie)') ?></label>
                    <input type="text" name="block_reason" class="form-control" value="<?= h($limit->block_reason ?? '') ?>" maxlength="500">
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted"><?= __('Uwagi') ?></label>
                    <textarea name="notes" class="form-control" rows="2"><?= h($limit->notes ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-3 text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line me-1"></i><?= __('Zapisz') ?>
                </button>
            </div>
        <?= $this->Form->end() ?>
    </div>
</div>

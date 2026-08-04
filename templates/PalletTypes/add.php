<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\PalletType $pallet
 * @var bool $isEdit
 */
$this->assign('title', $isEdit ? __('Edycja palety') : __('Nowa paleta'));
$formUrl = $isEdit
    ? $this->Url->build(['action' => 'edit', $pallet->id])
    : $this->Url->build(['action' => 'add']);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold"><?= $isEdit ? __('Edytuj paletę') : __('Nowa paleta') ?></h4>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-close-line me-1"></i><?= __('Anuluj') ?>
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?= $this->Form->create($pallet, ['url' => $formUrl]) ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Kod') ?> *</label>
                    <input type="text" name="code" class="form-control" value="<?= h($pallet->code ?? '') ?>" required maxlength="30" placeholder="EUR, H1, CR3, COMBO-285-BD-5R">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Nazwa') ?> *</label>
                    <input type="text" name="name" class="form-control" value="<?= h($pallet->name ?? '') ?>" required maxlength="150">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Producent') ?></label>
                    <input type="text" name="manufacturer" class="form-control" value="<?= h($pallet->manufacturer ?? '') ?>" maxlength="50" placeholder="TOSCA, EPAL, CHEP, IFCO">
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Długość (mm)') ?></label>
                    <input type="number" min="0" step="1" name="length_mm" class="form-control" value="<?= h($pallet->length_mm ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Szerokość (mm)') ?></label>
                    <input type="number" min="0" step="1" name="width_mm" class="form-control" value="<?= h($pallet->width_mm ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Wysokość (mm)') ?></label>
                    <input type="number" min="0" step="1" name="height_mm" class="form-control" value="<?= h($pallet->height_mm ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Waga pustej (kg)') ?></label>
                    <input type="number" min="0" step="0.01" name="weight_empty_kg" class="form-control" value="<?= h($pallet->weight_empty_kg ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Nośność (kg)') ?></label>
                    <input type="number" min="0" step="1" name="load_capacity_kg" class="form-control" value="<?= h($pallet->load_capacity_kg ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Materiał') ?></label>
                    <select name="material" class="form-select">
                        <option value=""></option>
                        <?php foreach (['wood' => 'Drewno', 'plastic' => 'Plastik', 'metal' => 'Metal', 'composite' => 'Kompozyt', 'cardboard' => 'Karton'] as $v => $lbl): ?>
                            <option value="<?= h($v) ?>" <?= ($pallet->material ?? '') === $v ? 'selected' : '' ?>><?= h($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Kolor') ?></label>
                    <input type="text" name="color" class="form-control" value="<?= h($pallet->color ?? '') ?>" maxlength="30" placeholder="niebieski, natural...">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="is_pooling" value="1" class="form-check-input" id="pt-pooling" <?= !empty($pallet->is_pooling) ? 'checked' : '' ?>>
                        <label for="pt-pooling" class="form-check-label"><?= __('Pooling (wynajem/zwrot)') ?></label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="pt-active" <?= !empty($pallet->is_active) || !$isEdit ? 'checked' : '' ?>>
                        <label for="pt-active" class="form-check-label"><?= __('Aktywna') ?></label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Zdjęcie (URL lub ścieżka)') ?></label>
                    <input type="text" name="image_path" class="form-control" value="<?= h($pallet->image_path ?? '') ?>" maxlength="500" placeholder="/img/pallets/eur.jpg lub https://...">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Link do producenta / dokumentacji') ?></label>
                    <input type="url" name="external_url" class="form-control" value="<?= h($pallet->external_url ?? '') ?>" maxlength="500">
                </div>

                <div class="col-12">
                    <label class="form-label small text-muted"><?= __('Opis / uwagi') ?></label>
                    <textarea name="description" class="form-control" rows="3"><?= h($pallet->description ?? '') ?></textarea>
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

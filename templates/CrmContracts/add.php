<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\CrmContract $entity
 * @var bool $isEdit
 */
$this->assign('title', $isEdit ? __('Edytuj kontrakt') : __('Nowy kontrakt'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-<?= $isEdit ? 'edit' : 'add' ?>-line me-1"></i>
        <?= $isEdit ? __('Edytuj kontrakt') : __('Nowy kontrakt') ?>
    </h4>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line"></i> <?= __('Wróć') ?>
    </a>
</div>

<?= $this->Form->create($entity) ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><?= __('Nazwa + klient') ?></h6>
                <div class="mb-2">
                    <label class="form-label small"><?= __('Nazwa kontraktu') ?> *</label>
                    <input name="name" required class="form-control" value="<?= h($entity->name) ?>"
                           placeholder="np. Mondi Simet PL→DE 2026">
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small">NIP klienta *</label>
                        <input name="contractor_nip" required class="form-control text-uppercase"
                               value="<?= h($entity->contractor_nip) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small"><?= __('Nazwa klienta') ?></label>
                        <input name="contractor_name" class="form-control" value="<?= h($entity->contractor_name) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><?= __('Trasa (opcjonalna - zostaw puste dla kontraktu globalnego)') ?></h6>
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label small"><?= __('Od kraj') ?></label>
                        <input name="from_country" maxlength="2" class="form-control text-uppercase"
                               value="<?= h($entity->from_country) ?>" placeholder="PL">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small"><?= __('Kod') ?></label>
                        <input name="from_postal_code" class="form-control" value="<?= h($entity->from_postal_code) ?>">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small"><?= __('Miasto') ?></label>
                        <input name="from_city" class="form-control" value="<?= h($entity->from_city) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small"><?= __('Do kraj') ?></label>
                        <input name="to_country" maxlength="2" class="form-control text-uppercase"
                               value="<?= h($entity->to_country) ?>" placeholder="DE">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small"><?= __('Kod') ?></label>
                        <input name="to_postal_code" class="form-control" value="<?= h($entity->to_postal_code) ?>">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small"><?= __('Miasto') ?></label>
                        <input name="to_city" class="form-control" value="<?= h($entity->to_city) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><?= __('Cennik') ?></h6>
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label small"><?= __('Cena netto') ?> *</label>
                        <input name="price_netto" type="number" step="0.01" required class="form-control"
                               value="<?= h($entity->price_netto) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small"><?= __('Waluta') ?></label>
                        <input name="currency" maxlength="3" class="form-control text-uppercase"
                               value="<?= h($entity->currency ?? 'PLN') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">VAT%</label>
                        <input name="vat_rate" type="number" min="0" max="100" class="form-control"
                               value="<?= h($entity->vat_rate ?? 23) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small"><?= __('Termin') ?></label>
                        <input name="payment_days" type="number" min="0" class="form-control"
                               value="<?= h($entity->payment_days ?? 30) ?>">
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label small"><?= __('Typ pojazdu (opcja)') ?></label>
                    <select name="required_vehicle_type" class="form-select">
                        <option value=""><?= __('— dowolny —') ?></option>
                        <?php foreach (['plandeka','mega','chlodnia','adr','oversize','bus'] as $v): ?>
                            <option value="<?= $v ?>" <?= $entity->required_vehicle_type === $v ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><?= __('Ważność + wolumen') ?></h6>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Ważny od') ?></label>
                        <input name="valid_from" type="date" class="form-control"
                               value="<?= $entity->valid_from ? $entity->valid_from->format('Y-m-d') : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Ważny do') ?></label>
                        <input name="valid_to" type="date" class="form-control"
                               value="<?= $entity->valid_to ? $entity->valid_to->format('Y-m-d') : '' ?>">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-4">
                        <label class="form-label small"><?= __('Wolumen (opcja)') ?></label>
                        <input name="committed_volume" type="number" min="0" class="form-control"
                               value="<?= h($entity->committed_volume) ?>" placeholder="np. 10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small"><?= __('Okres') ?></label>
                        <select name="volume_period" class="form-select">
                            <option value="month" <?= ($entity->volume_period ?? 'month') === 'month' ? 'selected' : '' ?>>miesiąc</option>
                            <option value="quarter" <?= $entity->volume_period === 'quarter' ? 'selected' : '' ?>>kwartał</option>
                            <option value="year" <?= $entity->volume_period === 'year' ? 'selected' : '' ?>>rok</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                   id="act" <?= ($entity->is_active ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="act"><?= __('Aktywny') ?></label>
                        </div>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label small"><?= __('Notatka') ?></label>
                    <textarea name="notes" class="form-control" rows="2"><?= h($entity->notes) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 text-end">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary"><?= __('Anuluj') ?></a>
        <button type="submit" class="btn btn-success"><i class="ri-save-line"></i> <?= __('Zapisz') ?></button>
    </div>
</div>
<?= $this->Form->end() ?>

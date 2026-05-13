<?php
/** @var \App\View\AppView $this @var \App\Model\Entity\TransportAddress $entity */
$this->assign('title', 'Nowy adres');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold"><i class="ri-map-pin-add-line me-1"></i>Nowy adres</h4>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i>Wróć
    </a>
</div>

<?= $this->Flash->render() ?>

<div class="card shadow-sm">
    <div class="card-body" style="max-width:720px">
        <?= $this->Form->create($entity) ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Nazwa miejsca <span class="text-danger">*</span></label>
                <?= $this->Form->control('name', ['label' => false, 'class' => 'form-control', 'required' => true, 'placeholder' => 'np. ABC sp.z o.o. Magazyn Gdańsk']) ?>
            </div>
            <div class="col-md-8">
                <label class="form-label">Adres (ulica + numer)</label>
                <?= $this->Form->control('address', ['label' => false, 'class' => 'form-control', 'placeholder' => 'np. Magazynowa 12']) ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Typ</label>
                <select name="address_type" class="form-select">
                    <option value="both"      <?= ($entity->address_type ?? 'both') === 'both'      ? 'selected' : '' ?>>Oba</option>
                    <option value="loading"   <?= ($entity->address_type ?? '')   === 'loading'   ? 'selected' : '' ?>>Załadunek</option>
                    <option value="unloading" <?= ($entity->address_type ?? '')   === 'unloading' ? 'selected' : '' ?>>Rozładunek</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kod pocztowy</label>
                <?= $this->Form->control('postal_code', ['label' => false, 'class' => 'form-control', 'placeholder' => '00-000']) ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Miasto</label>
                <?= $this->Form->control('city', ['label' => false, 'class' => 'form-control']) ?>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kraj (ISO 2)</label>
                <?= $this->Form->control('country', ['label' => false, 'class' => 'form-control', 'maxlength' => 5, 'value' => $entity->country ?? 'PL']) ?>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <?= $this->Form->checkbox('is_active', ['class' => 'form-check-input', 'id' => 'is_active', 'checked' => $entity->is_active ?? true]) ?>
                    <label class="form-check-label" for="is_active">Aktywny (widoczny w autocomplete)</label>
                </div>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary"><i class="ri-save-line me-1"></i>Zapisz</button>
            <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary">Anuluj</a>
        </div>
        <?= $this->Form->end() ?>
    </div>
</div>

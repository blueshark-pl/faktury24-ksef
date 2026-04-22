<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\E100Account $account
 * @var string $title
 */
$this->assign('title', $title);
$isEdit = !$account->isNew();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-gas-station-line me-1 text-warning"></i>
        <?= $isEdit ? 'Edytuj konto E100' : 'Dodaj konto E100' ?>
    </h4>
    <?= $this->Html->link('<i class="ri-arrow-left-line me-1"></i> Powrót', ['action' => 'accounts'],
        ['class' => 'btn btn-outline-secondary btn-sm', 'escape' => false]) ?>
</div>

<?= $this->Form->create(null, ['url' => $isEdit ? ['action' => 'editAccount', $account->id] : ['action' => 'addAccount'], 'method' => 'post']) ?>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white fw-semibold small">Dane konta</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Nazwa konta <span class="text-danger">*</span></label>
                <input type="text" name="label" class="form-control form-control-sm"
                       value="<?= h($account->label ?? '') ?>"
                       placeholder="np. E100 Główne, Flota nr 1"
                       required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Login E100 <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control form-control-sm"
                       value="<?= h($account->username ?? '') ?>"
                       autocomplete="off"
                       required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">
                    Hasło E100 <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?>
                </label>
                <div class="input-group input-group-sm">
                    <input type="password" name="password_plain" id="pwd-field" class="form-control form-control-sm"
                           autocomplete="new-password"
                           <?= $isEdit ? '' : 'required' ?>
                           placeholder="<?= $isEdit ? 'Pozostaw puste — bez zmian' : '' ?>">
                    <button class="btn btn-outline-secondary" type="button" id="toggle-pwd">
                        <i class="ri-eye-line"></i>
                    </button>
                </div>
                <?php if ($isEdit): ?>
                    <div class="form-text">Zostaw puste jeśli hasło się nie zmieniło.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is-active"
                           <?= ($account->is_active ?? true) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="is-active">Konto aktywne</label>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isEdit && $account->client_code): ?>
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-semibold small">Informacje z E100</div>
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-sm-3">Kod klienta</dt>
                <dd class="col-sm-9"><?= h($account->client_code) ?></dd>
                <dt class="col-sm-3">Firma</dt>
                <dd class="col-sm-9"><?= h($account->fullname ?? '—') ?></dd>
                <dt class="col-sm-3">Waluta domyślna</dt>
                <dd class="col-sm-9"><?= h($account->defcur ?? 'EUR') ?></dd>
                <dt class="col-sm-3">Token ważny do</dt>
                <dd class="col-sm-9"><?= $account->token_expires_at ? $account->token_expires_at->format('d.m.Y H:i') : '—' ?></dd>
                <dt class="col-sm-3">Ostatnia sync</dt>
                <dd class="col-sm-9"><?= $account->last_sync_at ? $account->last_sync_at->format('d.m.Y H:i') : 'Nigdy' ?></dd>
            </dl>
        </div>
    </div>
<?php endif; ?>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary btn-sm">
        <i class="ri-save-line me-1"></i> <?= $isEdit ? 'Zapisz zmiany' : 'Dodaj konto' ?>
    </button>
    <?= $this->Html->link('Anuluj', ['action' => 'accounts'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>

<?= $this->Form->end() ?>

<script>
document.getElementById('toggle-pwd')?.addEventListener('click', function () {
    const f = document.getElementById('pwd-field');
    const icon = this.querySelector('i');
    if (f.type === 'password') {
        f.type = 'text';
        icon.className = 'ri-eye-off-line';
    } else {
        f.type = 'password';
        icon.className = 'ri-eye-line';
    }
});
</script>

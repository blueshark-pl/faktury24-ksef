<?php
/**
 * @var \App\View\AppView                  $this
 * @var \CakeDC\Users\Model\Entity\User    $user
 * @var \App\Model\Entity\ClientProfile    $profile
 */
$this->assign('title', 'Dodaj klienta');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="ri-arrow-left-line me-1"></i>Wróć do listy
        </a>
        <h4 class="mb-0 fw-semibold"><i class="ri-user-add-line me-1"></i>Nowy klient portalu</h4>
    </div>
</div>

<?= $this->Flash->render() ?>

<?= $this->Form->create(null, ['url' => ['action' => 'add'], 'class' => 'needs-validation', 'novalidate' => 'novalidate']) ?>

<div class="row g-3">
    <!-- Konto -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-2">
                <strong><i class="ri-shield-user-line me-1"></i>Konto użytkownika</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small text-muted">E-mail (login) <span class="text-danger">*</span></label>
                    <?= $this->Form->control('email', [
                        'type'    => 'email',
                        'class'   => 'form-control',
                        'label'   => false,
                        'required' => true,
                        'autocomplete' => 'off',
                    ]) ?>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Imię</label>
                        <?= $this->Form->control('first_name', ['type' => 'text', 'class' => 'form-control', 'label' => false]) ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Nazwisko</label>
                        <?= $this->Form->control('last_name', ['type' => 'text', 'class' => 'form-control', 'label' => false]) ?>
                    </div>
                </div>
                <hr class="my-3">
                <div class="mb-3">
                    <label class="form-label small text-muted">Hasło <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <?= $this->Form->control('password', [
                            'type'  => 'text',
                            'class' => 'form-control',
                            'label' => false,
                            'required' => true,
                            'minlength' => 8,
                            'autocomplete' => 'new-password',
                        ]) ?>
                        <button type="button" class="btn btn-outline-secondary" id="genPwd" title="Wygeneruj losowe hasło">
                            <i class="ri-refresh-line"></i>
                        </button>
                    </div>
                    <div class="form-text">Min. 8 znaków. Hasło będzie widoczne — zapisz je przed zapisem formularza.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profil firmy -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-2">
                <strong><i class="ri-building-line me-1"></i>Profil firmy klienta</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small text-muted">NIP <span class="text-danger">*</span></label>
                    <?= $this->Form->control('nip', [
                        'type'  => 'text',
                        'class' => 'form-control',
                        'label' => false,
                        'required' => true,
                        'minlength' => 5,
                        'placeholder' => 'np. 5252344078 lub DE123456789',
                    ]) ?>
                    <div class="form-text">
                        Krajowy lub zagraniczny — wpisz dokładnie tak, jak figuruje w Speed ERP
                        (<code>speed_orders.buyer_nip</code>). NIP-y zagraniczne z prefiksem kraju (np. <code>DE</code>, <code>FR</code>).
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted">Nazwa firmy</label>
                    <?= $this->Form->control('company_name', [
                        'type'  => 'text',
                        'class' => 'form-control',
                        'label' => false,
                    ]) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted">Domyślny język portalu</label>
                    <select name="locale" class="form-select">
                        <option value="pl" selected>Polski (PL)</option>
                        <option value="en">English (EN)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line me-1"></i>Utwórz klienta
    </button>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary">Anuluj</a>
</div>

<?= $this->Form->end() ?>

<script>
document.getElementById('genPwd')?.addEventListener('click', function () {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
    var pwd = '';
    for (var i = 0; i < 12; i++) pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    var input = document.querySelector('input[name="password"]');
    if (input) { input.value = pwd; input.focus(); input.select(); }
});
</script>

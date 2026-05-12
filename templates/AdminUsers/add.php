<?php
/**
 * @var \App\View\AppView                $this
 * @var \CakeDC\Users\Model\Entity\User  $user
 * @var \App\Model\Entity\ClientProfile  $profile
 * @var \Cake\ORM\ResultSet              $rolesList
 */
$this->assign('title', __('Nowy użytkownik'));
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="ri-user-add-line me-1"></i><?= __('Nowy użytkownik') ?></h4>
        <div class="text-muted small mt-1"><?= __('Wybierz rolę — dla klienta uzupełnij dodatkowo NIP i nazwę firmy.') ?></div>
    </div>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i><?= __('Wróć') ?>
    </a>
</div>

<?= $this->Flash->render() ?>

<div class="card shadow-sm">
    <div class="card-body" style="max-width: 720px">
        <?= $this->Form->create(null, ['type' => 'post']) ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= __('E-mail') ?> <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required autocomplete="off"
                       value="<?= h($this->request->getData('email')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= __('Login') ?> <small class="text-muted">(<?= __('opcjonalnie — domyślnie e-mail') ?>)</small></label>
                <input type="text" name="username" class="form-control" autocomplete="off"
                       value="<?= h($this->request->getData('username')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label"><?= __('Imię') ?></label>
                <input type="text" name="first_name" class="form-control"
                       value="<?= h($this->request->getData('first_name')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= __('Nazwisko') ?></label>
                <input type="text" name="last_name" class="form-control"
                       value="<?= h($this->request->getData('last_name')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label"><?= __('Hasło') ?> <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                <div class="form-text"><?= __('Min. 8 znaków.') ?></div>
            </div>

            <div class="col-md-6">
                <label class="form-label"><?= __('Rola') ?> <span class="text-danger">*</span></label>
                <select name="role" id="role-select" class="form-select" required>
                    <?php $sel = $this->request->getData('role') ?: 'user'; ?>
                    <?php foreach ($rolesList as $r): ?>
                        <option value="<?= h($r->code) ?>" <?= $sel === $r->code ? 'selected' : '' ?>>
                            <?= h($r->name) ?> <small class="text-muted">(<?= h($r->code) ?>)</small>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Firma — dla pracowników (admin/user/asystent/...) — ignorowane dla klienta -->
            <div class="col-md-12 employee-fields">
                <label class="form-label"><?= __('Firma') ?></label>
                <select name="company_id" class="form-select">
                    <option value=""><?= __('— bez firmy (user przejdzie onboarding) —') ?></option>
                    <?php $selCompany = $this->request->getData('company_id'); ?>
                    <?php foreach ($companiesList as $c): ?>
                        <option value="<?= h($c->id) ?>" <?= $selCompany === $c->id ? 'selected' : '' ?>>
                            <?= h($c->name) ?><?= $c->nip ? ' — NIP ' . h($c->nip) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= __('Pracownicy są przypisywani do firmy z której będą wystawiać faktury. Możesz zostawić puste — wtedy user wybierze firmę w onboardingu.') ?></div>
            </div>

            <!-- Pola klienta — pokazują się tylko gdy role=client -->
            <div class="col-12 client-fields" style="display:none">
                <hr>
                <div class="text-muted small mb-2">
                    <i class="ri-information-line me-1"></i><?= __('Dane dla profilu klienta portalu') ?>
                </div>
            </div>
            <div class="col-md-6 client-fields" style="display:none">
                <label class="form-label"><?= __('NIP') ?></label>
                <input type="text" name="nip" class="form-control" maxlength="30"
                       value="<?= h($this->request->getData('nip')) ?>"
                       placeholder="<?= __('np. 1234567890 lub DE123456789') ?>">
                <div class="form-text"><?= __('PL lub zagraniczny — bez czyszczenia.') ?></div>
            </div>
            <div class="col-md-6 client-fields" style="display:none">
                <label class="form-label"><?= __('Nazwa firmy') ?></label>
                <input type="text" name="company_name" class="form-control"
                       value="<?= h($this->request->getData('company_name')) ?>">
            </div>
            <div class="col-md-3 client-fields" style="display:none">
                <label class="form-label"><?= __('Język') ?></label>
                <select name="locale" class="form-select">
                    <option value="pl">PL</option>
                    <option value="en">EN</option>
                </select>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary"><i class="ri-save-line me-1"></i><?= __('Utwórz konto') ?></button>
            <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary">
                <?= __('Anuluj') ?>
            </a>
        </div>

        <?= $this->Form->end() ?>
    </div>
</div>

<script>
(function () {
    var sel = document.getElementById('role-select');
    var clientFields   = document.querySelectorAll('.client-fields');
    var employeeFields = document.querySelectorAll('.employee-fields');

    function toggle() {
        var isClient = sel.value === 'client';
        clientFields.forEach(function (el)   { el.style.display = isClient ? '' : 'none'; });
        employeeFields.forEach(function (el) { el.style.display = isClient ? 'none' : ''; });
    }
    sel.addEventListener('change', toggle);
    toggle();
})();
</script>

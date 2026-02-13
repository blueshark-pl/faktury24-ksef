<div class="row">
    <!-- Left nav to match profile.php -->
    <div class="col-xl-3">
        <div class="card custom-card">
            <div class="card-body">
                <ul class="nav flex-column gap-1 nav-pills tab-style-7" role="tablist">
                    <li class="nav-item me-0" role="presentation">
                        <a class="nav-link d-inline-flex w-100 mb-2 bg-light" href="<?= $this->Url->build(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'profile']) ?>">Konto</a>
                    </li>
                    <li class="nav-item me-0" role="presentation">
                        <a class="nav-link d-inline-flex w-100 mb-0 bg-light active" aria-current="page">Bezpieczeństwo</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right content: change password card -->
    <div class="col-xl-9">
        <?= $this->Flash->render('auth') ?>
        <?= $this->Flash->render() ?>

        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div class="card-title">Zmień hasło</div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Ustaw nowe hasło dla swojego konta.</p>
                <?= $this->Form->create($user, ['class' => 'needs-validation', 'novalidate' => true]) ?>

                <div class="row gy-3">
                    <?php if (!empty($validatePassword)) : ?>
                        <div class="col-12">
                            <?= $this->Form->control('current_password', [
                                'type' => 'password',
                                'required' => true,
                                'label' => 'Obecne hasło',
                                'class' => 'form-control'
                            ]); ?>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <?= $this->Form->control('password', [
                            'type' => 'password',
                            'required' => true,
                            'id' => 'new-password',
                            'label' => 'Nowe hasło',
                            'class' => 'form-control'
                        ]); ?>
                        <?php if (\Cake\Core\Configure::read('Users.passwordMeter.enabled')) : ?>
                            <div class="mt-2">
                                <?= $this->User->addPasswordMeter() ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <?= $this->Form->control('password_confirm', [
                            'type' => 'password',
                            'required' => true,
                            'label' => 'Potwierdź hasło',
                            'class' => 'form-control'
                        ]); ?>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end mt-4">
                    <a href="<?= $this->Url->build(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'profile']) ?>" class="btn btn-secondary btn-wave">Anuluj</a>
                    <button id="btn-submit" class="btn btn-primary btn-wave">
                        <span class="submit-text">Zapisz</span>
                        <span class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>

                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const form = document.querySelector('form.needs-validation');
    const submitBtn = document.getElementById('btn-submit');
    if (!form || !submitBtn) return;
    form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.querySelector('.spinner-border')?.classList.remove('d-none');
        submitBtn.querySelector('.submit-text').textContent = 'Zapisywanie...';
    }, false);
})();
</script>

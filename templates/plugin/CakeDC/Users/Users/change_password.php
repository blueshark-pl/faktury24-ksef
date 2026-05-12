<?php
/**
 * Widok: Zmiana hasła / Reset hasła (CakeDC/Users)
 * Plik: templates/plugin/CakeDC/Users/Users/change_password.php
 *
 * @var \CakeDC\Users\Model\Entity\User $user
 * @var bool $validatePassword
 */

use Cake\Core\Configure;

$isResetFlow = empty($validatePassword);
if ($isResetFlow) {
        $this->setLayout('auth');
    $this->assign('title', __('Reset hasła'));
}
?>

<?php if ($isResetFlow): ?>
    <div class="card custom-card shadow-none my-auto">
        <div class="card-body p-5">

            <?= $this->Flash->render('auth') ?>
            <?= $this->Flash->render() ?>

            <div class="d-flex align-items-center justify-content-center mb-3">
                <span class="auth-icon" aria-hidden="true">
                    <i class="ri-key-2-line fs-1 text-primary"></i>
                </span>
            </div>

            <p class="h4 fw-semibold mb-0 text-center"><?= __('Ustaw nowe hasło') ?></p>
            <p class="mb-4 text-muted fw-normal text-center"><?= __('Wpisz nowe hasło dla swojego konta.') ?></p>

            <div class="row gy-3">
                <div class="col-xl-12">
                    <?= $this->Form->create($user, [
                        'id' => 'change-password-form',
                        'class' => 'needs-validation',
                        'novalidate' => true,
                    ]) ?>

                    <label for="new-password" class="form-label text-default"><?= __('Nowe hasło') ?></label>
                    <div class="position-relative mb-3">
                        <?= $this->Form->control('password', [
                            'type' => 'password',
                            'required' => true,
                            'id' => 'new-password',
                            'label' => false,
                            'class' => 'form-control form-control-lg',
                            'placeholder' => __('Ustaw nowe hasło'),
                            'autocomplete' => 'new-password',
                        ]); ?>
                    </div>

                    <?php if (Configure::read('Users.passwordMeter.enabled')): ?>
                        <div class="mb-3">
                            <?= $this->User->addPasswordMeter() ?>
                        </div>
                    <?php endif; ?>

                    <label for="password-confirm" class="form-label text-default"><?= __('Potwierdź hasło') ?></label>
                    <div class="position-relative mb-3">
                        <?= $this->Form->control('password_confirm', [
                            'type' => 'password',
                            'required' => true,
                            'id' => 'password-confirm',
                            'label' => false,
                            'class' => 'form-control form-control-lg',
                            'placeholder' => __('Powtórz hasło'),
                            'autocomplete' => 'new-password',
                        ]); ?>
                    </div>

                    <div class="d-grid mt-4">
                        <?= $this->Form->button(
                            __('Zapisz'),
                            [
                                'class' => 'btn btn-primary btn-lg d-inline-flex align-items-center justify-content-center gap-2',
                                'id' => 'changePasswordBtn',
                                'type' => 'submit',
                                'data-loading-text' => __('Zapisywanie...'),
                                'disabled' => true,
                            ]
                        ) ?>
                    </div>

                    <?= $this->Form->end() ?>

                    <div class="text-center mt-3">
                        <p class="text-muted mb-0">
                            <?= __('Wróć do') ?>
                            <?= $this->Html->link(
                                __('logowania'),
                                ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'login'],
                                ['class' => 'text-primary fw-medium']
                            ) ?>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $this->Html->scriptBlock(<<<'JS'
    (function(){
        const form      = document.getElementById('change-password-form');
        const p1        = document.getElementById('new-password');
        const p2        = document.getElementById('password-confirm');
        const btn       = document.getElementById('changePasswordBtn');
        let submitting  = false;

        function valid(){
            const a = (p1?.value || '');
            const b = (p2?.value || '');
            return a.length > 0 && b.length > 0 && a === b;
        }

        function toggle(){ if (btn) btn.disabled = !valid(); }

        function setLoading(isLoading){
            if (!btn) return;
            if (isLoading) {
                btn.disabled = true;
                btn.dataset.originalText = btn.dataset.originalText || btn.textContent.trim();
                btn.textContent = btn.getAttribute('data-loading-text') || 'Zapisywanie...';
                const sp = document.createElement('span');
                sp.className = 'spinner-border spinner-border-sm';
                sp.setAttribute('role','status');
                sp.setAttribute('aria-hidden','true');
                sp.style.marginLeft = '0.5rem';
                btn.appendChild(sp);
            } else {
                const label = btn.dataset.originalText || 'Zapisz';
                btn.textContent = label;
                toggle();
            }
        }

        [p1, p2].filter(Boolean).forEach(el => {
            ['input','keyup','change'].forEach(ev => el.addEventListener(ev, toggle));
            el.addEventListener('paste', () => setTimeout(toggle,0));
        });
        toggle();

        form?.addEventListener('submit', (e) => {
            if (!valid()) { e.preventDefault(); return; }
            if (submitting) return;
            submitting = true;
            setLoading(true);
        });

        window.addEventListener('pageshow', () => {
            submitting = false;
            setLoading(false);
        });
    })();
    JS, ['block' => 'bottom']);
    ?>

<?php else: ?>
    <div class="row">
            <!-- Left nav to match profile.php -->
            <div class="col-xl-3">
                    <div class="card custom-card">
                            <div class="card-body">
                            <?= $this->element('Users/settings_nav') ?>
                            </div>
                    </div>
            </div>

            <!-- Right content: change password card -->
            <div class="col-xl-9">
                    <?= $this->Flash->render('auth') ?>
                    <?= $this->Flash->render() ?>

                    <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                    <div class="card-title"><?= __('Zmień hasło') ?></div>
                            </div>
                            <div class="card-body">
                                    <p class="text-muted mb-3"><?= __('Ustaw nowe hasło dla swojego konta.') ?></p>
                                    <?= $this->Form->create($user, ['class' => 'needs-validation', 'novalidate' => true]) ?>

                                    <div class="row gy-3">
                                            <div class="col-12">
                                                    <?= $this->Form->control('current_password', [
                                                            'type' => 'password',
                                                            'required' => true,
                                                            'label' => __('Obecne hasło'),
                                                            'class' => 'form-control'
                                                    ]); ?>
                                            </div>

                                            <div class="col-12">
                                                    <?= $this->Form->control('password', [
                                                            'type' => 'password',
                                                            'required' => true,
                                                            'id' => 'new-password',
                                                            'label' => __('Nowe hasło'),
                                                            'class' => 'form-control'
                                                    ]); ?>
                                                    <?php if (Configure::read('Users.passwordMeter.enabled')) : ?>
                                                            <div class="mt-2">
                                                                    <?= $this->User->addPasswordMeter() ?>
                                                            </div>
                                                    <?php endif; ?>
                                            </div>

                                            <div class="col-12">
                                                    <?= $this->Form->control('password_confirm', [
                                                            'type' => 'password',
                                                            'required' => true,
                                                            'label' => __('Potwierdź hasło'),
                                                            'class' => 'form-control'
                                                    ]); ?>
                                            </div>
                                    </div>

                                    <div class="d-flex gap-2 justify-content-end mt-4">
                                            <a href="<?= $this->Url->build(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'profile']) ?>" class="btn btn-secondary btn-wave"><?= __('Anuluj') ?></a>
                                            <button id="btn-submit" class="btn btn-primary btn-wave">
                                                    <span class="submit-text"><?= __('Zapisz') ?></span>
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
                    submitBtn.querySelector('.submit-text').textContent = <?= json_encode(__('Zapisywanie...')) ?>;
            }, false);
    })();
    </script>
<?php endif; ?>

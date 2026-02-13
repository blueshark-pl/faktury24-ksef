<?php
/**
 * Widok: Rejestracja (CakeDC/Users)
 * Plik: templates/plugin/CakeDC/Users/Users/register.php
 *
 * Layout: 'auth'
 *
 * @var \CakeDC\Users\Model\Entity\User $user
 */

use Cake\Core\Configure;

$this->setLayout('auth');
$this->assign('title', __d('cake_d_c/users', 'Register'));
?>
<div class="card custom-card shadow-none my-auto">
  <div class="card-body p-5">

    <?= $this->Flash->render('auth') ?>
    <?= $this->Flash->render() ?>

    <div class="d-flex align-items-center justify-content-center mb-3">
      <span class="auth-icon" aria-hidden="true">
        <i class="ri-user-add-line fs-1 text-primary"></i>
      </span>
    </div>

    <p class="h4 fw-semibold mb-0 text-center"><?= __('Załóż konto') ?></p>
    <p class="mb-0 text-muted fw-normal text-center"><?= __('Wpisz dane i ustaw hasło, aby dokończyć rejestrację.') ?></p>

    <div class="row gy-3 mt-3">
      <div class="col-xl-12">

        <?= $this->Form->create($user, [
          'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'register'],
          'id' => 'register-form',
          'class' => 'needs-validation',
          'novalidate' => true,
        ]) ?>

        <?php
          // U nas login używa pola `username` jako adresu e-mail.
          // Trzymamy zgodność z CakeDC, ale nie chcemy dublować pól w UI.
        ?>
        <?= $this->Form->control('username', [
          'type' => 'hidden',
          'id' => 'usernameHidden',
          'label' => false,
        ]) ?>

        <label for="reg-email" class="form-label text-default"><?= __('Adres e-mail') ?></label>
        <div class="position-relative mb-3">
          <?= $this->Form->control('email', [
            'id' => 'reg-email',
            'type' => 'email',
            'label' => false,
            'class' => 'form-control form-control-lg',
            'placeholder' => __('Wpisz adres e-mail'),
            'required' => true,
            'autocomplete' => 'email',
            'inputmode' => 'email',
          ]) ?>
        </div>

        <label for="reg-password" class="form-label text-default"><?= __('Hasło') ?></label>
        <div class="position-relative mb-3">
          <?= $this->Form->control('password', [
            // CakeDC password meter expects `#new-password`
            'id' => 'new-password',
            'type' => 'password',
            'label' => false,
            'class' => 'form-control form-control-lg',
            'placeholder' => __('Ustaw hasło'),
            'required' => true,
            'autocomplete' => 'new-password',
          ]) ?>
        </div>

        <?php if (Configure::read('Users.passwordMeter.enabled')): ?>
          <div class="mb-3">
            <?= $this->User->addPasswordMeter() ?>
          </div>
        <?php endif; ?>

        <label for="reg-password-confirm" class="form-label text-default"><?= __('Potwierdź hasło') ?></label>
        <div class="position-relative mb-3">
          <?= $this->Form->control('password_confirm', [
            'id' => 'reg-password-confirm',
            'type' => 'password',
            'label' => false,
            'class' => 'form-control form-control-lg',
            'placeholder' => __('Powtórz hasło'),
            'required' => true,
            'autocomplete' => 'new-password',
          ]) ?>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label for="reg-first-name" class="form-label text-default"><?= __('Imię') ?></label>
            <?= $this->Form->control('first_name', [
              'id' => 'reg-first-name',
              'label' => false,
              'class' => 'form-control form-control-lg',
              'placeholder' => __('Imię'),
              'required' => true,
              'autocomplete' => 'given-name',
            ]) ?>
          </div>
          <div class="col-12 col-md-6">
            <label for="reg-last-name" class="form-label text-default"><?= __('Nazwisko') ?></label>
            <?= $this->Form->control('last_name', [
              'id' => 'reg-last-name',
              'label' => false,
              'class' => 'form-control form-control-lg',
              'placeholder' => __('Nazwisko'),
              'required' => true,
              'autocomplete' => 'family-name',
            ]) ?>
          </div>
        </div>

        <?php if (Configure::read('Users.Tos.required')): ?>
          <div class="form-check mt-3">
            <?= $this->Form->checkbox('tos', [
              'hiddenField' => false,
              'required' => true,
              'id' => 'reg-tos',
              'class' => 'form-check-input',
            ]) ?>
            <?php
              $regLink = $this->Html->link(
                __('Regulamin'),
                '#',
                [
                  'class' => 'text-primary fw-medium',
                  'data-bs-toggle' => 'modal',
                  'data-bs-target' => '#regulaminModal',
                  'role' => 'button',
                ]
              );
              $ppLink = $this->Html->link(
                __('Politykę prywatności'),
                '#',
                [
                  'class' => 'text-primary fw-medium',
                  'data-bs-toggle' => 'modal',
                  'data-bs-target' => '#privacyModal',
                  'role' => 'button',
                ]
              );
              echo $this->Form->label(
                'tos',
                __('Akceptuję {0} oraz {1}.', $regLink, $ppLink),
                ['class' => 'form-check-label', 'escape' => false]
              );
            ?>
          </div>
        <?php endif; ?>

        <?php if (Configure::read('Users.reCaptcha.registration')): ?>
          <div class="mt-3">
            <?= $this->User->addReCaptcha() ?>
          </div>
        <?php endif; ?>

        <div class="d-grid mt-4">
          <?= $this->User->button(
            __('Utwórz konto'),
            [
              'class' => 'btn btn-primary btn-lg d-inline-flex align-items-center justify-content-center gap-2',
              'id' => 'registerBtn',
              'data-loading-text' => __('Tworzenie konta...'),
              'disabled' => true,
            ]
          ) ?>
        </div>

        <?= $this->Form->end() ?>

        <div class="text-center mt-3">
          <p class="text-muted mb-0">
            <?= __('Masz już konto?') ?>
            <?= $this->Html->link(
              __('Zaloguj się'),
              ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'login'],
              ['class' => 'text-primary fw-medium']
            ) ?>
          </p>
        </div>

      </div>
    </div>
  </div>
</div>

<?php
$this->Html->scriptBlock(<<<'JS'
(function(){
  const form        = document.getElementById('register-form');
  const emailInput  = document.getElementById('reg-email');
  const userHidden  = document.getElementById('usernameHidden');
  const passInput   = document.getElementById('new-password');
  const pass2Input  = document.getElementById('reg-password-confirm');
  const firstName   = document.getElementById('reg-first-name');
  const lastName    = document.getElementById('reg-last-name');
  const tosInput    = document.getElementById('reg-tos');
  const btn         = document.getElementById('registerBtn');
  let submitting    = false;

  function syncUsername(){
    if (!userHidden || !emailInput) return;
    userHidden.value = (emailInput.value || '').trim();
  }

  function validEmail(){
    const v = (emailInput?.value || '').trim();
    return v.length > 3 && v.includes('@');
  }

  function validPasswords(){
    const p1 = (passInput?.value || '');
    const p2 = (pass2Input?.value || '');
    return p1.length > 0 && p2.length > 0 && p1 === p2;
  }

  function validName(){
    const fn = (firstName?.value || '').trim();
    const ln = (lastName?.value || '').trim();
    return fn.length > 0 && ln.length > 0;
  }

  function validTos(){
    if (!tosInput) return true;
    return !!tosInput.checked;
  }

  function valid(){
    return validEmail() && validPasswords() && validName() && validTos();
  }

  function toggle(){
    syncUsername();
    if (!btn) return;
    btn.disabled = !valid();
  }

  function setLoading(isLoading){
    if (!btn) return;
    if (isLoading) {
      btn.disabled = true;
      btn.dataset.originalText = btn.dataset.originalText || btn.textContent.trim();
      btn.textContent = btn.getAttribute('data-loading-text') || 'Tworzenie konta...';
      const sp = document.createElement('span');
      sp.className = 'spinner-border spinner-border-sm';
      sp.setAttribute('role','status');
      sp.setAttribute('aria-hidden','true');
      sp.style.marginLeft = '0.5rem';
      btn.appendChild(sp);
    } else {
      const label = btn.dataset.originalText || 'Utwórz konto';
      btn.textContent = label;
      toggle();
    }
  }

  [emailInput, passInput, pass2Input, firstName, lastName, tosInput].filter(Boolean).forEach(el => {
    ['input','keyup','change'].forEach(ev => el.addEventListener(ev, toggle));
    el.addEventListener('paste', () => setTimeout(toggle,0));
  });

  toggle();

  form?.addEventListener('submit', (e) => {
    toggle();
    if (!valid()) {
      e.preventDefault();
      return;
    }
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

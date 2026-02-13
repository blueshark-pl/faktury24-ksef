<?php
/**
 * Widok: Request Reset Password (CakeDC/Users)
 * Plik: templates/plugin/CakeDC/Users/Users/request_reset_password.php
 *
 * Akcja: UsersController::requestResetPassword()
 * Layout: 'auth'
 *
 * @var \CakeDC\Users\Model\Entity\User $user
 */
$this->setLayout('auth');
$this->assign('title', __('Reset hasła'));
?>
<div class="card custom-card shadow-none my-auto">
  <div class="card-body p-5">

    <?= $this->Flash->render('auth') ?>
    <?= $this->Flash->render() ?>

    <div class="d-flex align-items-center justify-content-center mb-3">
      <span class="auth-icon" aria-hidden="true">
        <i class="ri-lock-password-line fs-1 text-primary"></i>
      </span>
    </div>

    <p class="h4 fw-semibold mb-0 text-center">
      <?= __('Zresetuj hasło') ?>
    </p>
    <p class="mb-4 text-muted fw-normal text-center">
      <?= __('Podaj adres e-mail, aby otrzymać link do ustawienia nowego hasła.') ?>
    </p>

    <div class="row gy-3">
      <div class="col-xl-12">
        <?= $this->Form->create($user, [
          'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'requestResetPassword'],
          'id' => 'reset-form',
          'class' => 'needs-validation',
          'novalidate' => true,
        ]) ?>

        <label for="reference" class="form-label text-default"><?= __('Adres e-mail') ?></label>
        <div class="position-relative mb-3">
          <?= $this->Form->control('reference', [
            'id' => 'reference',
            'label' => false,
            'class' => 'form-control form-control-lg',
            'type' => 'email',
            'placeholder' => __('Wpisz adres e-mail'),
            'required' => true,
            'autocomplete' => 'email',
            'inputmode' => 'email',
          ]) ?>
        </div>

        <div class="d-grid mt-2">
          <?= $this->Form->button(
            __('Wyślij'),
            [
              'class' => 'btn btn-primary btn-lg d-inline-flex align-items-center justify-content-center gap-2',
              'id' => 'resetBtn',
              'type' => 'submit',
              'escapeTitle' => true,
              'data-loading-text' => __('Wysyłanie...'),
              'disabled' => true,
            ]
          ) ?>
        </div>

        <?= $this->Form->end() ?>

        <div class="text-center mt-3">
          <p class="text-muted mb-0">
            <?= __('Pamiętasz hasło?') ?>
            <?= $this->Html->link(
              __('Zaloguj się'),
              ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'login'],
              ['class' => 'text-primary fw-medium']
            ) ?>
          </p>
          <?php if (\Cake\Core\Configure::read('Users.Registration.active')): ?>
            <p class="text-muted mb-0 mt-1">
              <?= __('Nie masz jeszcze konta?') ?>
              <?= $this->Html->link(
                __('Zarejestruj się'),
                ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'register'],
                ['class' => 'text-primary']
              ) ?>.
            </p>
          <?php endif; ?>
        </div>

      </div>
    </div>

    <div class="text-center">
      <p class="fs-12 text-danger mt-3 mb-0">
        <sup><i class="ri-asterisk"></i></sup>
        <?= __('Jeśli nie prosiłeś o reset hasła, zignoruj tę wiadomość.') ?>
      </p>
    </div>
  </div>
</div>

<?php
// JS: walidacja + spinner
$this->Html->scriptBlock(<<<'JS'
(function(){
  const form      = document.getElementById('reset-form');
  const input     = document.getElementById('reference');
  const submitBtn = document.getElementById('resetBtn');
  let submitting  = false;

  function valid(){ return !!(input && input.value && input.value.trim().length > 0); }
  function toggle(){ if (submitBtn) submitBtn.disabled = !valid(); }

  function setLoading(isLoading){
    if (!submitBtn) return;
    if (isLoading) {
      submitBtn.disabled = true;
      submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent.trim();
      submitBtn.textContent = submitBtn.getAttribute('data-loading-text') || 'Wysyłanie...';
      const sp = document.createElement('span');
      sp.className = 'spinner-border spinner-border-sm';
      sp.setAttribute('role','status'); sp.setAttribute('aria-hidden','true');
      sp.style.marginLeft = '0.5rem'; submitBtn.appendChild(sp);
    } else {
      const label = submitBtn.dataset.originalText || 'Wyślij';
      submitBtn.textContent = label; toggle();
    }
  }

  if (input) {
    ['input','keyup','change'].forEach(ev => input.addEventListener(ev, toggle));
    input.addEventListener('paste', () => setTimeout(toggle,0));
    toggle();
  }

  if (form) {
    form.addEventListener('submit', e => {
      if (!valid()){ e.preventDefault(); return; }
      if (submitting) return;
      submitting = true; setLoading(true);
    });
  }

  window.addEventListener('pageshow', ()=>{ submitting=false; setLoading(false); });
})();
JS, ['block' => 'bottom']);

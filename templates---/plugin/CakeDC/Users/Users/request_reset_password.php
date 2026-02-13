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
$this->assign('title', __d('cake_d_c/users', 'Reset Password'));
?>
<div class="card custom-card shadow-none my-auto">
  <div class="card-body p-5">

    <?= $this->Flash->render('auth') ?>
    <?= $this->Flash->render() ?>

    <div class="d-flex align-items-center justify-content-center mb-3">
      <span class="auth-icon" aria-hidden="true">
        <!-- Ikona -->
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
          <path fill="#6446fe" d="M8 14h48a4 4 0 0 1 4 4v28a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V18a4 4 0 0 1 4-4zm0 4v2.34l24 14.4 24-14.4V18H8zm48 28V25.26L33.35 38.4a4 4 0 0 1-4.7 0L8 25.26V46h48z"/>
        </svg>
      </span>
    </div>

    <p class="h4 fw-semibold mb-0 text-center">
      <?= __d('cake_d_c/users', 'Reset Password') ?>
    </p>
    <p class="mb-4 text-muted fw-normal text-center">
      <?= __d('cake_d_c/users', 'Please enter your email or username to reset your password') ?>
    </p>

    <div class="row gy-3">
      <div class="col-xl-12">
        <?= $this->Form->create($user, [
          'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'requestResetPassword'],
          'id' => 'reset-form',
          'class' => 'needs-validation',
          'novalidate' => true,
        ]) ?>

        <label for="reference" class="form-label text-default">
          <?= __d('cake_d_c/users', 'Email or Username') ?>
        </label>
        <div class="position-relative mb-3">
          <?= $this->Form->control('reference', [
            'id' => 'reference',
            'label' => false,
            'class' => 'form-control form-control-lg',
            'placeholder' => __d('cake_d_c/users', 'Enter your email or username'),
            'required' => true,
            'autocomplete' => 'username email',
          ]) ?>
        </div>

        <div class="d-grid mt-2">
          <?= $this->Form->button(
            __d('cake_d_c/users', 'Submit'),
            [
              'class' => 'btn btn-primary btn-lg d-inline-flex align-items-center justify-content-center gap-2',
              'id' => 'resetBtn',
              'type' => 'submit',
              'escapeTitle' => true,
              'data-loading-text' => __d('cake_d_c/users', 'Sending...'),
              'disabled' => true,
            ]
          ) ?>
        </div>

        <?= $this->Form->end() ?>

        <!-- 🔙 powrót do logowania -->
        <!-- 🔙 powrót do logowania -->
<div class="text-center mt-3">
  <?= $this->Html->link(
    __d('cake_d_c/users', 'Back to login'),
    ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'login'],
    ['class' => 'text-primary fw-medium']
  ) ?>
</div>

      </div>
    </div>

    <div class="text-center">
      <p class="fs-12 text-danger mt-3 mb-0">
        <sup><i class="ri-asterisk"></i></sup>
        <?= __d('cake_d_c/users', 'If you did not request a reset, you can ignore this message.') ?>
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
      submitBtn.textContent = submitBtn.getAttribute('data-loading-text') || 'Sending...';
      const sp = document.createElement('span');
      sp.className = 'spinner-border spinner-border-sm';
      sp.setAttribute('role','status'); sp.setAttribute('aria-hidden','true');
      sp.style.marginLeft = '0.5rem'; submitBtn.appendChild(sp);
    } else {
      const label = submitBtn.dataset.originalText || 'Submit';
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

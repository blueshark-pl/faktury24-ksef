<?php
/**
 * Widok: Request One-Time Login Link (CakeDC/Users)
 * Plik: templates/plugin/CakeDC/Users/Users/request_login_link.php
 *
 * Layout: 'auth'
 */
$this->setLayout('auth');
$this->assign('title', __('Logowanie linkiem'));
?>

<div class="card custom-card shadow-none my-auto">
  <div class="card-body p-5">

    <?= $this->Flash->render('auth') ?>
    <?= $this->Flash->render() ?>

    <div class="d-flex align-items-center justify-content-center mb-3">
      <span class="auth-icon" aria-hidden="true">
        <i class="ri-mail-send-line fs-1 text-primary"></i>
      </span>
    </div>

    <p class="h4 fw-semibold mb-0 text-center">
      <?= __('Logowanie linkiem') ?>
    </p>
    <p class="mb-4 text-muted fw-normal text-center">
      <?= __('Podaj adres e-mail, a wyślemy jednorazowy link do logowania.') ?>
    </p>

    <div class="row gy-3">
      <div class="col-xl-12">
        <?= $this->Form->create(null, [
          'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'requestLoginLink'],
          'id' => 'loginlink-form',
          'class' => 'needs-validation',
          'novalidate' => true,
        ]) ?>

        <label for="email" class="form-label text-default">
          <?= __('Adres e-mail') ?>
        </label>
        <div class="position-relative mb-3">
          <?= $this->Form->control('email', [
            'id' => 'email',
            'type' => 'email',
            'label' => false,
            'class' => 'form-control form-control-lg',
            'placeholder' => __('Wpisz adres e-mail'),
            'required' => true,
            'autocomplete' => 'email',
          ]) ?>
        </div>

        <?php if (\Cake\Core\Configure::read('Users.reCaptcha.login')): ?>
          <div class="mb-3">
            <?= $this->User->addReCaptcha() ?>
          </div>
        <?php endif; ?>

        <div class="d-grid mt-2">
          <?= $this->Form->button(
            __('Wyślij link'),
            [
              'class' => 'btn btn-primary btn-lg d-inline-flex align-items-center justify-content-center gap-2',
              'id' => 'loginLinkBtn',
              'type' => 'submit',
              'escapeTitle' => true,
              'data-loading-text' => __('Wysyłanie...'),
              'disabled' => true,
            ]
          ) ?>
        </div>

        <?= $this->Form->end() ?>

        <!-- 🔙 powrót do logowania -->
        <div class="text-center mt-3">
          <p class="text-muted mb-0">
            <?= __('Wróć do') ?>
            <?= $this->Html->link(
              __('logowania'),
              ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'login'],
              ['class' => 'text-primary fw-medium']
            ) ?>.
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
  </div>
</div>

<?php
// JS: aktywacja submit dopiero gdy email wpisany + spinner
$this->Html->scriptBlock(<<<'JS'
(function(){
  const form      = document.getElementById('loginlink-form');
  const input     = document.getElementById('email');
  const submitBtn = document.getElementById('loginLinkBtn');
  let submitting  = false;

  function valid(){ return !!(input && input.value && input.value.trim().length > 3); }
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
      const label = submitBtn.dataset.originalText || 'Wyślij link';
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

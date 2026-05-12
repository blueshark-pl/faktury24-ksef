<?php
/**
 * Widok: Logowanie (CakeDC/Users)
 * Plik: templates/Auth/login.php
 *
 * Layout: 'auth'
 */
use Cake\Core\Configure;

$this->setLayout('auth');
$this->assign('title', __('Zaloguj się'));

// zbierz social przyciski raz
$socialButtons = (array)($this->User->socialLoginList() ?? []);
$hasSocial = !empty(array_filter($socialButtons, fn($s) => trim((string)$s) !== ''));
?>
<div class="card custom-card shadow-none my-auto">
  <div class="card-body p-5">

    <?= $this->Flash->render('auth') ?>
    <?= $this->Flash->render() ?>
    <div class="d-flex align-items-center justify-content-center mb-3">
        <span class="auth-icon" id="loginAvatarBox">
            <!-- Domyślnie ikona ; JS podstawi avatar z localStorage jeśli jest -->
            <i class="ri-shield-user-line fs-1 text-primary" id="loginAvatarIcon"></i>
            <img id="loginAvatarImg" src="" alt="" style="display:none;width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid rgba(var(--primary-rgb),.25);box-shadow:0 6px 22px rgba(15,23,42,.15)">
        </span>
    </div>
    <p class="h4 fw-semibold mb-0 text-center"><?= __('Zaloguj się') ?></p>
    <p class="mb-0 text-muted fw-normal text-center" id="loginGreet"><?= __('Witaj ponownie! Zaloguj się, aby kontynuować.') ?></p>

    <script>
    (function () {
        try {
            var avatar = localStorage.getItem('lastLoginAvatar');
            var email  = localStorage.getItem('lastLoginEmail');
            if (avatar) {
                var img  = document.getElementById('loginAvatarImg');
                var icon = document.getElementById('loginAvatarIcon');
                if (img && icon) {
                    img.onload  = function () { icon.style.display = 'none'; img.style.display = 'block'; };
                    img.onerror = function () { /* zostaje ikona, czyścimy stary URL */
                        try { localStorage.removeItem('lastLoginAvatar'); } catch (e) {}
                    };
                    img.src = avatar;
                }
            }
            // Prefill emaila — jeśli istnieje user input
            if (email) {
                var uInput = document.getElementById('username');
                if (uInput && !uInput.value) { uInput.value = email; }
            }
        } catch (e) {}
    })();
    </script>

    <div class="row gy-3 mt-3">
      <div class="col-xl-12">

        <?= $this->Form->create(null, [
          'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'login'],
          'id' => 'login-form',
          'class' => 'needs-validation',
          'novalidate' => true
        ]) ?>

        <label for="username" class="form-label text-default">
          <?= __('Adres e-mail') ?>
        </label>
        <div class="position-relative mb-3">
          <?= $this->Form->control('username', [
            'id' => 'username',
            'type' => 'email',
            'label' => false,
            'class' => 'form-control form-control-lg',
            'placeholder' => __('Wpisz adres e-mail'),
            'required' => true,
            'autocomplete' => 'email',
            'inputmode' => 'email'
          ]) ?>
        </div>

        <label for="signin-password" class="form-label text-default d-block">
          <?= __('Hasło') ?>
          <?= $this->Html->link(
                __('Nie pamiętasz hasła?'),
                ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'requestResetPassword'],
                ['class' => 'float-end link-danger op-5 fw-medium fs-12']
          ) ?>
        </label>

        <div class="position-relative">
          <?= $this->Form->control('password', [
            'id' => 'signin-password',
            'type' => 'password',
            'label' => false,
            'class' => 'form-control form-control-lg',
            'placeholder' => __('Wpisz hasło'),
            'required' => true,
            'autocomplete' => 'current-password'
          ]) ?>
          <a href="javascript:void(0);" class="show-password-button text-muted"
             onclick="createpassword('signin-password',this)" id="button-addon2" aria-label="toggle password">
            <i class="ri-eye-off-line align-middle"></i>
          </a>
        </div>

        <?php if (Configure::read('Users.reCaptcha.login')): ?>
          <div class="mt-3">
            <?= $this->User->addReCaptcha() ?>
          </div>
        <?php endif; ?>

        <div class="mt-2 mb-3">
          <?php if (Configure::read('Users.RememberMe.active')): ?>
            <div class="form-check">
              <?= $this->Form->control(Configure::read('Users.Key.Data.rememberMe'), [
                'type'    => 'checkbox',
                'label'   => __('Zapamiętaj mnie'),
                'checked' => (bool)Configure::read('Users.RememberMe.checked'),
                'hiddenField' => false,
                'id'      => 'rememberMe',
                'class'   => 'form-check-input'
              ]) ?>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($hasSocial): ?>
          <div class="text-center my-3 authentication-barrier">
            <span class="op-4 fs-11"><?= __('Lub zaloguj się przez') ?></span>
          </div>
          <div class="d-flex align-items-center justify-content-center gap-3 mb-3 flex-wrap">
            <?= implode(' ', $socialButtons); ?>
          </div>
        <?php endif; ?>

        <div class="d-grid mt-4">
          <?= $this->User->button(
            __('Zaloguj się'),
            [
              'class' => 'btn btn-primary btn-lg d-inline-flex align-items-center justify-content-center gap-2',
              'id' => 'loginBtn',
              'data-loading-text' => __('Logowanie...'),
              'disabled' => true, // odblokujemy w JS po wypełnieniu pól
            ]
          ) ?>
        </div>

        <?= $this->Form->end() ?>

        <div class="text-center mt-3">
          <?php
            $registrationActive = Configure::read('Users.Registration.active');
            if ($registrationActive) {
              echo '<p class="text-muted mb-0">'
                . __('Nie masz jeszcze konta?') . ' '
                . $this->Html->link(
                    __('Zarejestruj się'),
                    ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'register'],
                    ['class' => 'text-primary']
                  )
                . '.</p>';
            }
          ?>
        </div>

      </div>
    </div>
  </div>
</div>

<?php
// Fallback dla show-password (jeśli nie masz assets/js/show-password.js)
$this->Html->scriptBlock(<<<'JS'
window.createpassword = window.createpassword || function(id, btn){
  var inp = document.getElementById(id);
  if(!inp) return;
  if(inp.type === 'password'){ inp.type = 'text'; btn && btn.classList && btn.classList.add('active'); }
  else { inp.type = 'password'; btn && btn.classList && btn.classList.remove('active'); }
};
JS, ['block' => 'bottom']);

// JS: aktywacja/loader przy logowaniu
$this->Html->scriptBlock(<<<'JS'
(function(){
  const form      = document.getElementById('login-form');
  const userInput = document.getElementById('username');
  const passInput = document.getElementById('signin-password');
  const btn       = document.getElementById('loginBtn');
  let submitting  = false;

  function valid() {
    return userInput && passInput &&
           userInput.value.trim().length > 0 &&
           passInput.value.trim().length > 0;
  }

  function toggle() {
    if (!btn) return;
    btn.disabled = !valid();
  }

  function setLoading(isLoading){
    if (!btn) return;
    if (isLoading) {
      btn.disabled = true;
      btn.dataset.originalText = btn.dataset.originalText || btn.textContent.trim();
      btn.textContent = btn.getAttribute('data-loading-text') || 'Logowanie...';
      const sp = document.createElement('span');
      sp.className = 'spinner-border spinner-border-sm';
      sp.setAttribute('role','status'); sp.setAttribute('aria-hidden','true');
      sp.style.marginLeft = '0.5rem';
      btn.appendChild(sp);
    } else {
      // Tylko jeśli wcześniej zapisaliśmy oryginalny tekst — nie nadpisuj tym co PHP wyrenderował.
      if (btn.dataset.originalText) {
        btn.textContent = btn.dataset.originalText;
      }
      toggle();
    }
  }

  ['input','keyup','change'].forEach(ev => {
    userInput?.addEventListener(ev, toggle);
    passInput?.addEventListener(ev, toggle);
  });
  passInput?.addEventListener('paste', () => setTimeout(toggle,0));
  userInput?.addEventListener('paste', () => setTimeout(toggle,0));
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

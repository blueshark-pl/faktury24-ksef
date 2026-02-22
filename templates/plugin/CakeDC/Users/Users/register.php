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
$this->assign('title', __('Rejestracja'));
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
          // `username` jest ukryte i synchronizowane z `email` w JS.
          // Musi być odblokowane dla FormProtection, aby nie powodowało "Tampered field".
          'unlockedFields' => ['username'],
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

        <?php $this->Form->unlockField('username'); ?>

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

        <label for="reg-company-nip" class="form-label text-default"><?= __('NIP (opcjonalnie, przyspiesza onboarding)') ?></label>
        <div class="position-relative mb-2">
          <div class="input-group">
            <?= $this->Form->control('additional_data.onboarding_prefill.nip', [
              'id' => 'reg-company-nip',
              'label' => false,
              'class' => 'form-control form-control-lg',
              'placeholder' => __('np. 6571234567'),
              'maxlength' => 10,
              'inputmode' => 'numeric',
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <button class="btn btn-outline-secondary" type="button" id="reg-gus-fetch">
              <span class="spinner-border spinner-border-sm me-1 d-none" id="reg-gus-spin"></span>
              <i class="ri-database-2-line me-1"></i><?= __('Pobierz z GUS') ?>
            </button>
          </div>
          <div class="form-text"><?= __('Po rejestracji dane firmy zostaną wstępnie uzupełnione w onboardingu.') ?></div>
        </div>

        <div id="reg-gus-preview" class="alert alert-light border small d-none mb-3"></div>

        <?= $this->Form->hidden('additional_data.onboarding_prefill.name', ['id' => 'reg-onb-name']) ?>
        <?= $this->Form->hidden('additional_data.onboarding_prefill.street', ['id' => 'reg-onb-street']) ?>
        <?= $this->Form->hidden('additional_data.onboarding_prefill.postal_code', ['id' => 'reg-onb-postal-code']) ?>
        <?= $this->Form->hidden('additional_data.onboarding_prefill.city', ['id' => 'reg-onb-city']) ?>
        <?= $this->Form->hidden('additional_data.onboarding_prefill.country', ['id' => 'reg-onb-country']) ?>

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
  const nipInput    = document.getElementById('reg-company-nip');
  const gusBtn      = document.getElementById('reg-gus-fetch');
  const gusSpin     = document.getElementById('reg-gus-spin');
  const gusPreview  = document.getElementById('reg-gus-preview');
  const onbName     = document.getElementById('reg-onb-name');
  const onbStreet   = document.getElementById('reg-onb-street');
  const onbPostal   = document.getElementById('reg-onb-postal-code');
  const onbCity     = document.getElementById('reg-onb-city');
  const onbCountry  = document.getElementById('reg-onb-country');
  const btn         = document.getElementById('registerBtn');
  let submitting    = false;
  const csrfToken   = document.querySelector('meta[name="csrfToken"]')?.getAttribute('content') || '';

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

  function setOnboardingPrefill(c){
    if (onbName) onbName.value = (c?.name || '').trim();
    if (onbStreet) onbStreet.value = (c?.street || '').trim();
    if (onbPostal) onbPostal.value = (c?.zip || '').trim();
    if (onbCity) onbCity.value = (c?.city || '').trim();
    if (onbCountry) onbCountry.value = (c?.country || 'PL').trim();
  }

  function setGusPreview(text, isError){
    if (!gusPreview) return;
    gusPreview.classList.remove('d-none', 'alert-light', 'alert-danger');
    gusPreview.classList.add(isError ? 'alert-danger' : 'alert-light');
    gusPreview.textContent = text;
  }

  nipInput?.addEventListener('input', () => {
    nipInput.value = (nipInput.value || '').replace(/\D/g, '').slice(0, 10);
  });

  gusBtn?.addEventListener('click', async () => {
    const nip = (nipInput?.value || '').replace(/\D/g, '');
    if (nip.length !== 10) {
      setGusPreview('Podaj prawidłowy NIP (10 cyfr).', true);
      return;
    }

    try {
      gusBtn.disabled = true;
      gusSpin?.classList.remove('d-none');

      const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'gusLookup']) ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({ nip }),
      });

      const data = await res.json();
      if (!data?.success) {
        setGusPreview(data?.message || 'Nie udało się pobrać danych z GUS.', true);
        return;
      }

      const c = data.contractor || {};
      setOnboardingPrefill(c);
      setGusPreview('Pobrano dane z GUS. W onboardingu pola firmy będą już uzupełnione.', false);
    } catch (e) {
      setGusPreview('Błąd połączenia z GUS.', true);
    } finally {
      gusBtn.disabled = false;
      gusSpin?.classList.add('d-none');
    }
  });

  [emailInput, passInput, pass2Input, firstName, lastName, tosInput, nipInput].filter(Boolean).forEach(el => {
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

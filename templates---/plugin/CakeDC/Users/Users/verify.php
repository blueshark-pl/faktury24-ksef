<?php
/**
 * Widok: Verify Two Factor (6 pól po 1 cyfrze)
 * Plik: templates/plugin/CakeDC/Users/Users/verify.php
 *
 * Layout: 'auth'
 */
$this->setLayout('auth');
$this->assign('title', __d('cake_d_c/users', 'Verify Your Account'));
?>
<div class="card custom-card shadow-none my-auto">
  <div class="card-body p-5">

    <?= $this->Flash->render('auth') ?>
    <?= $this->Flash->render() ?>

    <div class="d-flex align-items-center justify-content-center mb-3">
      <span class="auth-icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path fill="#6446fe" d="M35,41H23a3,3,0,0,1-3-3V30a3,3,0,0,1,3-3H35a3,3,0,0,1,3,3v8A3,3,0,0,1,35,41Z"></path><path fill="#6446fe" d="M28 37a1 1 0 0 1-.71-.29l-2-2a1 1 0 0 1 1.42-1.42L28 34.59l3.29-3.3a1 1 0 0 1 1.42 1.42l-4 4A1 1 0 0 1 28 37z"></path></svg>
      </span>
    </div>

    <p class="h4 fw-semibold mb-0 text-center">
      <?= __d('cake_d_c/users', 'Verify Your Account') ?>
    </p>
    <p class="mb-4 text-muted fw-normal text-center">
      <?= __d('cake_d_c/users', 'Enter the 6-digit code from your authenticator app.') ?>
    </p>

    <div class="row gy-3">
      <div class="col-xl-12">
       <?= $this->Form->create(null, [
  'url' => ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'verify'],
  'id' => 'twofactor-form',
  'class' => 'needs-validation',
  'novalidate' => true,
  'unlockedFields' => ['code'], // <— to załatwia “Tampered field”
]) ?>
<?php $this->Form->unlockField('code'); ?>


        <?php if (!empty($secretDataUri)): ?>
          <div class="text-center mb-3">
            <img src="<?= h($secretDataUri) ?>" alt="<?= __d('cake_d_c/users', 'Authenticator QR Code') ?>" class="img-fluid"/>
            <p class="small text-muted mt-2">
              <?= __d('cake_d_c/users', 'Scan this QR code in your authenticator app, then enter the code below.') ?>
            </p>
          </div>
        <?php endif; ?>

        <!-- ukryte pole wymagane przez CakeDC/Users -->
        <?= $this->Form->control('code', ['type' => 'hidden', 'id' => 'totpCode', 'label' => false]) ?>

        <label class="form-label text-default d-block mb-2">
          <?= __d('cake_d_c/users', 'Verification Code') ?>
        </label>

        <div class="row g-2 justify-content-center mb-2" inputmode="numeric" aria-label="<?= __d('cake_d_c/users', '6-digit verification code') ?>">
          <?php for ($i = 1; $i <= 6; $i++): ?>
            <div class="col-2 col-sm-2 col-md-2" style="max-width:80px;">
              <input
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="1"
                autocomplete="one-time-code"
                class="form-control form-control-lg text-center otp-digit"
                id="otp<?= $i ?>"
                data-index="<?= $i ?>"
                aria-label="<?= __d('cake_d_c/users', 'Digit {0}', $i) ?>"
              >
            </div>
          <?php endfor; ?>
        </div>

        <p class="text-center small text-muted mb-3">
          <?= __d('cake_d_c/users', 'Tip: You can paste the whole 6-digit code — we will split it automatically.') ?>
        </p>

        <div class="d-grid mt-2">
            <?= $this->Form->button(
                __d('cake_d_c/users', 'Verify'),
                [
                'class' => 'btn btn-primary btn-lg d-inline-flex align-items-center justify-content-center gap-2',
                'id' => 'verifyBtn',
                'escapeTitle' => true,
                'type' => 'submit',
                'data-loading-text' => __d('cake_d_c/users', 'Verifying...'),
                'disabled' => true,   // ⬅ startowo zablokowany
                ]
            ) ?>
        </div>

        <?= $this->Form->end() ?>
      </div>
    </div>

    <div class="text-center">
      <p class="fs-12 text-danger mt-3 mb-0">
        <sup><i class="ri-asterisk"></i></sup>
        <?= __d('cake_d_c/users', 'Don’t share the verification code with anyone!') ?>
      </p>
    </div>
  </div>
</div>
<?php
$this->Html->scriptBlock(<<<'JS'
(function(){
  const digits    = Array.from(document.querySelectorAll('.otp-digit'));
  const hidden    = document.getElementById('totpCode');        // <input type="hidden" name="code" ...>
  const form      = document.getElementById('twofactor-form');
  const submitBtn = document.getElementById('verifyBtn');
  let submitted   = false;

  function setHiddenFromDigits(){
    hidden.value = digits.map(i => i.value.replace(/\D/g,'')).join('');
  }

  function focusIndex(idx){
    const el = digits[idx];
    if (el) el.focus();
  }

  function allFilled(){
    return digits.every(d => d.value && /\d/.test(d.value));
  }

  function toggleSubmitState(){
    setHiddenFromDigits();
    if (!submitBtn) return;
    submitBtn.disabled = !/^\d{6}$/.test(hidden.value);
  }

  function setLoading(isLoading){
    if (!submitBtn) return;
    if (isLoading) {
      submitBtn.disabled = true;
      submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent.trim();
      submitBtn.textContent = submitBtn.getAttribute('data-loading-text') || 'Verifying...';
      const sp = document.createElement('span');
      sp.className = 'spinner-border spinner-border-sm';
      sp.setAttribute('role','status');
      sp.setAttribute('aria-hidden','true');
      sp.style.marginLeft = '0.5rem';
      submitBtn.appendChild(sp);
    } else {
      // przywróć stan przycisku wg aktualnej walidacji
      const label = submitBtn.dataset.originalText || 'Verify';
      submitBtn.textContent = label;
      toggleSubmitState();
    }
  }

  function maybeAutoSubmit(){
    toggleSubmitState();
    if (submitted) return;
    if (!/^\d{6}$/.test(hidden.value)) return;
    submitted = true;
    setLoading(true);
    if (form.requestSubmit) form.requestSubmit(submitBtn || undefined);
    else if (submitBtn) submitBtn.click();
    else form.submit();
  }

  // Start: focus i stan przycisku
  if (digits[0]) digits[0].focus();
  toggleSubmitState();

  digits.forEach((el, i) => {
    el.addEventListener('input', (e) => {
      e.target.value = e.target.value.replace(/\D/g,'').slice(0,1);
      if (e.target.value.length === 1 && i < digits.length - 1) {
        focusIndex(i+1);
      }
      toggleSubmitState();
      // Auto-submit gdy wpisano ostatnią cyfrę i wszystkie pola pełne
      if (i === digits.length - 1 && allFilled()) {
        maybeAutoSubmit();
      }
    });

    el.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !e.target.value && i > 0) {
        focusIndex(i-1);
      }
      if (e.key === 'ArrowLeft')  { e.preventDefault(); focusIndex(Math.max(0, i-1)); }
      if (e.key === 'ArrowRight') { e.preventDefault(); focusIndex(Math.min(digits.length-1, i+1)); }
      if (e.key === 'Enter') {
        e.preventDefault();
        maybeAutoSubmit();
      }
    });

    el.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text') || '';
      const numbers = text.replace(/\D/g,'').slice(0, digits.length).split('');
      digits.forEach((d, idx) => d.value = numbers[idx] || '');
      toggleSubmitState();
      if (allFilled()) {
        maybeAutoSubmit();
      } else {
        const nextEmpty = digits.findIndex(d => !d.value);
        if (nextEmpty >= 0) focusIndex(nextEmpty);
      }
    });
  });

  // Ręczny klik — pokaż spinner tylko gdy kod jest poprawny
  if (submitBtn) {
    submitBtn.addEventListener('click', () => {
      toggleSubmitState();
      if (/^\d{6}$/.test(hidden.value)) {
        setLoading(true);
        submitted = true;
      }
    });
  }

  // Walidacja przy submit
  form.addEventListener('submit', (e) => {
    toggleSubmitState();
    if (!/^\d{6}$/.test(hidden.value)) {
      e.preventDefault();
      submitted = false;
      setLoading(false);
      digits[0]?.focus();
    }
  });

  // Na powrót z błędem (Flash) — zdejmij spinner i ustaw disabled wg stanu pól
  window.addEventListener('pageshow', () => {
    submitted = false;
    setLoading(false);
  });
})();
JS, ['block' => 'bottom']);

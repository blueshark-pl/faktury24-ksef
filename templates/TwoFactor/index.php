<?php
/** @var \Cake\View\View $this */

$this->assign('title', 'Uwierzytelnianie dwuskładnikowe (2FA)');
?>

<div class="row">
  <div class="col-xl-3">
    <div class="card custom-card">
      <div class="card-body">
        <?= $this->element('Users/settings_nav') ?>
      </div>
    </div>
  </div>

  <div class="col-xl-9">
    <div class="card custom-card">
      <div class="card-header justify-content-between">
        <div class="card-title">Uwierzytelnianie dwuskładnikowe (2FA)</div>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">
          2FA jest opcjonalne. Jeśli je włączysz, podczas logowania będziesz proszony o kod z aplikacji (np. Google Authenticator).
        </p>

        <?php if (!empty($isEnabled)): ?>
          <div class="alert alert-success">
            <strong>2FA jest włączone</strong> dla Twojego konta.
          </div>

          <?= $this->Form->create(null, ['url' => ['action' => 'disable']]) ?>
            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Na pewno wyłączyć 2FA?');">
              Wyłącz 2FA
            </button>
          <?= $this->Form->end() ?>

        <?php elseif (!empty($isInSetup)): ?>
          <div class="alert alert-warning">
            <strong>Konfiguracja w toku.</strong> Zeskanuj kod QR i wpisz kod z aplikacji.
          </div>

          <?php if (!empty($qrDataUri)): ?>
            <div class="mb-3">
              <img src="<?= h($qrDataUri) ?>" alt="Kod QR 2FA" style="max-width: 240px;" class="border rounded p-2 bg-white" />
            </div>
          <?php endif; ?>

          <?= $this->Form->create(null, ['url' => ['action' => 'verify']]) ?>
            <div class="row g-2 align-items-end">
              <div class="col-sm-6">
                <?= $this->Form->control('code', [
                  'label' => 'Kod z aplikacji',
                  'class' => 'form-control',
                  'autocomplete' => 'one-time-code',
                  'inputmode' => 'numeric',
                  'required' => true,
                ]) ?>
              </div>
              <div class="col-sm-6">
                <button type="submit" class="btn btn-primary">Zweryfikuj i włącz</button>
              </div>
            </div>
          <?= $this->Form->end() ?>

          <hr class="my-4" />

          <div class="d-flex gap-2 flex-wrap">
            <?= $this->Form->create(null, ['url' => ['action' => 'disable']]) ?>
              <button type="submit" class="btn btn-outline-secondary">Anuluj konfigurację</button>
            <?= $this->Form->end() ?>

            <a class="btn btn-outline-primary" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" rel="noopener">Google Authenticator (Android)</a>
            <a class="btn btn-outline-primary" href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank" rel="noopener">Google Authenticator (iOS)</a>
          </div>

        <?php else: ?>
          <div class="alert alert-secondary">
            2FA jest obecnie <strong>wyłączone</strong> dla Twojego konta.
          </div>

          <?= $this->Form->create(null, ['url' => ['action' => 'enable']]) ?>
            <button type="submit" class="btn btn-primary">
              Rozpocznij konfigurację 2FA
            </button>
          <?= $this->Form->end() ?>

          <div class="mt-3 d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" rel="noopener">Google Authenticator (Android)</a>
            <a class="btn btn-outline-primary" href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank" rel="noopener">Google Authenticator (iOS)</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

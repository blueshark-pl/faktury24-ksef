<?php
/** @var \Cake\View\View $this */
/** @var string $newToken */
/** @var string $tokenName */

$this->assign('title', 'Token API wygenerowany');
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
      <div class="card-header">
        <div class="card-title"><i class="ri-key-2-line me-2 text-success"></i> Token wygenerowany</div>
      </div>
      <div class="card-body">

        <div class="alert alert-warning d-flex gap-2" role="alert">
          <i class="ri-alert-line fs-5 flex-shrink-0 mt-1"></i>
          <div>
            <strong>Zapisz token teraz.</strong> Ze względów bezpieczeństwa nie będzie on wyświetlony ponownie.
          </div>
        </div>

        <p class="mb-1 text-muted small">Token: <strong><?= h($tokenName) ?></strong></p>

        <div class="input-group mb-3">
          <input type="text" id="api-token-value" class="form-control form-control-lg font-monospace"
                 value="<?= h($newToken) ?>" readonly>
          <button class="btn btn-outline-secondary" type="button" id="copy-token-btn" title="Kopiuj">
            <i class="ri-file-copy-line"></i>
          </button>
        </div>

        <p class="text-muted small mb-4">
          Użyj tego tokenu w nagłówku HTTP każdego zapytania do API:<br>
          <code>Authorization: Bearer <?= h($newToken) ?></code>
        </p>

        <?= $this->Html->link(
          '<i class="ri-arrow-left-line me-1"></i> Powrót do listy tokenów',
          ['action' => 'index'],
          ['class' => 'btn btn-secondary', 'escape' => false]
        ) ?>

      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('copy-token-btn').addEventListener('click', function() {
  var input = document.getElementById('api-token-value');
  navigator.clipboard.writeText(input.value).then(function() {
    var btn = document.getElementById('copy-token-btn');
    btn.innerHTML = '<i class="ri-check-line"></i>';
    setTimeout(function() { btn.innerHTML = '<i class="ri-file-copy-line"></i>'; }, 2000);
  });
});
</script>

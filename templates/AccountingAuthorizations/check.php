<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AccountingAuthorization $auth
 * @var array $result
 */
$this->assign('title', __('Integracja księgowa – sprawdzenie połączenia'));
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2"><?= __('Integracja z systemem księgowym') ?></h1>
    <nav><ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><?= $this->Html->link(__('Integracja księgowa'), ['action'=>'index']) ?></li>
      <li class="breadcrumb-item active"><?= __('Weryfikacja połączenia') ?></li>
    </ol></nav>
  </div>
  <div class="btn-list">
    <?= $this->Html->link('<i class="ri-arrow-go-back-line align-middle"></i> '.__('Wróć do listy'), ['action'=>'index'], [
      'escape'=>false,'class'=>'btn btn-secondary-light btn-wave'
    ]) ?>
    <?= $this->Html->link('<i class="ri-eye-line align-middle"></i> '.__('Podgląd tokenu'), ['action'=>'view', $auth->id], [
      'escape'=>false,'class'=>'btn btn-primary-light btn-wave'
    ]) ?>
  </div>
</div>

<div class="card custom-card border-0 shadow-sm mb-3">
  <div class="card-header bg-light d-flex align-items-center">
    <i class="ri-information-line text-primary fs-5 me-2"></i>
    <div class="card-title mb-0"><?= __('Co oznacza to sprawdzenie?') ?></div>
  </div>
  <div class="card-body text-muted small">
    <p class="mb-2">
      To szybki test połączenia pomiędzy <strong>Twoim kontem w systemie Faktury24</strong> 
      a <strong>zewnętrznym systemem księgowym</strong> (np. PartnersC, wFirma, inFakt itp.).
    </p>
    <p class="mb-2">
      System wysyła krótkie zapytanie, aby sprawdzić, czy zapisany <strong>token autoryzacyjny</strong> działa poprawnie
      i czy możliwa jest bezpieczna wymiana danych (np. wysyłka faktur lub pobranie dokumentów).
    </p>
    <p class="mb-0">
      Wynik połączenia pokazuje, czy integracja działa prawidłowo.
    </p>
  </div>
</div>

<div class="card custom-card border-0 shadow-sm">
  <div class="card-header bg-light d-flex align-items-center">
    <i class="ri-plug-line text-primary fs-5 me-2"></i>
    <div class="card-title mb-0"><?= __('Wynik sprawdzenia połączenia') ?></div>
  </div>

  <div class="card-body">
    <dl class="row mb-4">
      <dt class="col-sm-4 col-lg-3"><?= __('System księgowy') ?></dt>
      <dd class="col-sm-8 col-lg-9"><?= h(ucfirst($result['provider'])) ?></dd>

      <dt class="col-sm-4 col-lg-3"><?= __('Adres usługi') ?></dt>
      <dd class="col-sm-8 col-lg-9"><code><?= h($result['endpoint']) ?></code></dd>

      <dt class="col-sm-4 col-lg-3"><?= __('Status połączenia') ?></dt>
      <dd class="col-sm-8 col-lg-9">
        <?php if (!empty($result['ok'])): ?>
          <span class="badge bg-success-subtle text-success px-3 py-2 fs-6">
            <i class="ri-checkbox-circle-line me-1"></i><?= __('Połączenie aktywne') ?>
          </span>
          <p class="small text-muted mt-2 mb-0">
            ✅ Token jest prawidłowy, a połączenie z systemem księgowym działa poprawnie.
          </p>
        <?php else: ?>
          <span class="badge bg-danger-subtle text-danger px-3 py-2 fs-6">
            <i class="ri-close-circle-line me-1"></i><?= __('Brak połączenia') ?>
          </span>
          <p class="small text-muted mt-2 mb-0">
            ⚠️ Nie udało się uzyskać poprawnej odpowiedzi z systemu księgowego.<br>
            Sprawdź, czy token jest aktualny lub czy po stronie systemu zewnętrznego nie ma przerwy technicznej.
          </p>
        <?php endif; ?>
      </dd>
    </dl>

    <div class="border-top pt-3">
      <h6 class="fw-semibold mb-2"><?= __('Szczegóły techniczne') ?></h6>
      <dl class="row small">
        <dt class="col-sm-4 col-lg-3"><?= __('Kod odpowiedzi (HTTP)') ?></dt>
        <dd class="col-sm-8 col-lg-9"><?= $result['http_code'] !== null ? (int)$result['http_code'] : '—' ?></dd>

        <dt class="col-sm-4 col-lg-3"><?= __('Komunikat serwera') ?></dt>
        <dd class="col-sm-8 col-lg-9"><?= h($result['error'] ?: ($result['ok'] ? __('Połączenie poprawne.') : __('Brak danych.'))) ?></dd>
      </dl>
    </div>

    <?php if (!empty($result['body'])): ?>
      <div class="border-top pt-3 mt-3">
        <h6 class="fw-semibold mb-2"><?= __('Szczegóły odpowiedzi systemu') ?></h6>
        <pre class="bg-body-tertiary p-3 rounded border small" style="white-space:pre-wrap; word-break:break-word;">
<?= h(json_encode($result['body'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) ?>
        </pre>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card custom-card mt-4 border-0 shadow-sm">
  <div class="card-header bg-light d-flex align-items-center">
    <i class="ri-shield-check-line text-primary fs-5 me-2"></i>
    <div class="card-title mb-0"><?= __('Bezpieczeństwo danych') ?></div>
  </div>
  <div class="card-body small text-muted">
    <ul class="mb-0">
      <li>Twój token jest przechowywany w postaci zaszyfrowanej i wykorzystywany tylko do autoryzacji połączenia.</li>
      <li>System nie udostępnia tokenu innym użytkownikom ani osobom trzecim.</li>
      <li>Połączenie odbywa się szyfrowanym kanałem (HTTPS).</li>
      <li>W każdej chwili możesz dezaktywować token i dodać nowy.</li>
    </ul>
  </div>
</div>

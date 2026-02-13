<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\KsefAuthorization $auth
 */
$this->assign('title', __('Integracja KSeF – dodaj token'));
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">
      <?= __('Integracja z Krajowym Systemem eFaktur (KSeF)') ?>
    </h1>
    <nav>
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><?= $this->Html->link(__('Integracje'), ['controller'=>'Integrations','action'=>'index']) ?></li>
        <li class="breadcrumb-item"><?= $this->Html->link(__('KSeF'), ['action'=>'index']) ?></li>
        <li class="breadcrumb-item active"><?= __('Dodaj token') ?></li>
      </ol>
    </nav>
  </div>

  <div class="btn-list">
    <?= $this->Html->link('<i class="ri-arrow-go-back-line align-middle"></i> '.__('Lista tokenów'), ['action'=>'index'], [
      'escape'=>false,'class'=>'btn btn-secondary-light btn-wave'
    ]) ?>
  </div>
</div>

<?= $this->Form->create($auth, ['novalidate'=>true, 'autocomplete'=>'off']) ?>

<div class="row g-3">
  <div class="col-12 col-xl-8">
    <div class="card custom-card border-primary shadow-sm">
      <div class="card-header bg-primary text-white d-flex align-items-center">
        <i class="ri-key-line me-2 fs-5"></i>
        <div class="card-title mb-0"><?= __('Połączenie z KSeF – wklej swój token') ?></div>
      </div>
      <div class="card-body">
        <p class="text-muted mb-4">
          Aby umożliwić systemowi <strong>Faktury24</strong> komunikację z <strong>Krajowym Systemem e-Faktur</strong>,
          należy uzyskać <strong>token autoryzacyjny</strong> w portalu Ministerstwa Finansów i wkleić go poniżej.
        </p>

        <ol class="small mb-4">
          <li>Wejdź na stronę <a href="https://ksef.mf.gov.pl" target="_blank" rel="noopener"><strong>https://ksef.mf.gov.pl</strong></a></li>
          <li>Zaloguj się jako <em>Podmiot gospodarczy</em>.</li>
          <li>W zakładce <strong>„Zarządzanie tokenami”</strong> utwórz nowy token dla integracji z Faktury24.</li>
          <li>Skopiuj wygenerowany token i wklej go w poniższe pole.</li>
        </ol>

        <div class="row g-3">
          <div class="col-12">
            <?= $this->Form->control('token', [
              'label'=>false,
              'type'=>'text',
              'class'=>'form-control form-control-lg text-monospace',
              'placeholder'=>__('Wklej tutaj token autoryzacyjny z portalu KSeF'),
              'required'=>true,
              'maxlength'=>512,
              'autocomplete'=>'off',
            ]) ?>
            <div class="form-text mt-2">
              <?= __('Token zostanie bezpiecznie zaszyfrowany w bazie danych. 
                System zapamięta tylko ostatnie 4 znaki dla Twojej kontroli.') ?>
            </div>
            <div class="alert alert-secondary mt-4 small d-flex align-items-start" role="alert">
  <i class="ri-shield-check-line fs-5 text-primary me-2"></i>
  <div>
    <strong>Bezpieczeństwo przechowywania:</strong><br>
    Token autoryzacyjny jest zapisywany w systemie w formie zaszyfrowanej,
    z wykorzystaniem indywidualnego klucza ochrony aplikacji.
    W bazie danych nie są przechowywane żadne dane umożliwiające jego bezpośrednie odczytanie.
    Widoczny dla użytkownika pozostaje wyłącznie skrót kontrolny ostatnich znaków tokenu.
  </div>
</div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- KARTA ADMINISTRACYJNA -->
  <div class="col-12 col-xl-4">
    <div class="card custom-card border-warning shadow-sm">
      <div class="card-header bg-warning-subtle d-flex align-items-center">
        <i class="ri-alert-line text-warning me-2 fs-5"></i>
        <div class="card-title mb-0"><?= __('Sekcja administracyjna (testowo)') ?></div>
      </div>
      <div class="card-body">
        <div class="alert alert-warning small">
          <strong><?= __('Uwaga:') ?></strong><br>
          <?= __('Poniższe pola służą wyłącznie do testów i będą <strong>usunięte</strong> w środowisku produkcyjnym.') ?>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <?= $this->Form->control('environment', [
              'label'=>__('Środowisko'),
              'type'=>'select',
              'options'=>['prod'=>__('Produkcyjne'), 'test'=>__('Testowe')],
              'default'=>'prod',
              'class'=>'form-select'
            ]) ?>
          </div>

          <div class="col-12">
            <?= $this->Form->control('auth_method', [
              'label'=>__('Metoda autoryzacji'),
              'type'=>'select',
              'empty'=>__('— wybierz —'),
              'options'=>[
                'pz'=>__('Podmiot zaufany (PZ)'),
                'qualified_sign'=>__('Podpis kwalifikowany'),
                'qualified_seal'=>__('Pieczęć kwalifikowana'),
              ],
              'class'=>'form-select'
            ]) ?>
          </div>

          <div class="col-12 col-md-6">
            <?= $this->Form->control('valid_from', [
              'label'=>__('Ważny od'),
              'type'=>'datetime-local',
              'empty'=>true,
              'class'=>'form-control'
            ]) ?>
          </div>

          <div class="col-12 col-md-6">
            <?= $this->Form->control('expires_at', [
              'label'=>__('Wygasa'),
              'type'=>'datetime-local',
              'empty'=>true,
              'class'=>'form-control'
            ]) ?>
          </div>

          <div class="col-12">
            <?= $this->Form->control('scopes', [
              'label'=>__('Zakres uprawnień (JSON)'),
              'type'=>'textarea',
              'rows'=>3,
              'class'=>'form-control',
              'placeholder'=>'{"invoices:send":true,"invoices:query":true}'
            ]) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Sticky dolny pasek -->
<div class="card border-0 shadow position-sticky bottom-0 z-3 mt-3" style="background: var(--bs-body-bg);">
  <div class="card-body py-2 d-flex gap-2 justify-content-end">
    <?= $this->Form->button('<i class="ri-save-3-line align-middle me-1"></i> '.__('Zapisz token'), [
      'escapeTitle'=>false,'class'=>'btn btn-primary btn-wave'
    ]) ?>
    <?= $this->Html->link('<i class="ri-close-line align-middle me-1"></i> '.__('Anuluj'), ['action'=>'index'], [
      'escape'=>false,'class'=>'btn btn-secondary-light btn-wave'
    ]) ?>
  </div>
</div>

<?= $this->Form->end() ?>
<div class="card custom-card mt-4 border-0 shadow-sm">
  <div class="card-header bg-light d-flex align-items-center">
    <i class="ri-information-line text-primary fs-5 me-2"></i>
    <div class="card-title mb-0"><?= __('Jak działa integracja z KSeF') ?></div>
  </div>

  <div class="card-body">
    <p class="text-muted">
      Integracja z <strong>Krajowym Systemem e-Faktur (KSeF)</strong> umożliwia bezpośrednią wymianę faktur
      pomiędzy systemem <strong>Faktury24</strong> a platformą Ministerstwa Finansów.
      Dzięki temu proces wystawiania, przesyłania i pobierania faktur jest w pełni zautomatyzowany.
    </p>

    <h6 class="fw-semibold mt-4 mb-2">
      <i class="ri-numbers-line me-1 text-primary"></i> Krok po kroku
    </h6>

    <ol class="mb-4">
      <li><strong>Wygeneruj token</strong> w portalu <a href="https://ksef.mf.gov.pl" target="_blank">ksef.mf.gov.pl</a> jako podmiot gospodarczy.</li>
      <li><strong>Wklej token</strong> w powyższym formularzu – zostanie on bezpiecznie zapisany i przypisany do Twojej firmy.</li>
      <li>System <strong>Faktury24</strong> wykorzysta token wyłącznie w celu komunikacji z KSeF (np. wysyłka faktur, pobieranie statusów).</li>
      <li>W każdej chwili możesz <strong>cofnąć lub zmienić token</strong> – poprzednie zostaną automatycznie dezaktywowane.</li>
    </ol>

    <div class="alert alert-secondary small d-flex align-items-start">
      <i class="ri-shield-check-line fs-5 text-primary me-2"></i>
      <div>
        <strong>Bezpieczeństwo danych:</strong><br>
        Tokeny są przechowywane w postaci zaszyfrowanej, przy użyciu indywidualnego klucza ochrony aplikacji.
        System nie przechowuje informacji umożliwiających ich bezpośrednie odczytanie,
        a w interfejsie widoczna jest wyłącznie końcówka tokenu dla weryfikacji użytkownika.
      </div>
    </div>

    <h6 class="fw-semibold mt-4 mb-2">
      <i class="ri-lock-line me-1 text-primary"></i> Zasady bezpieczeństwa
    </h6>

    <ul class="text-muted small">
      <li>Każdy token jest przypisany do konkretnej firmy i użytkownika.</li>
      <li>Klucze zabezpieczające przechowywane są poza bazą danych w środowisku aplikacyjnym.</li>
      <li>Dostęp do tokenów mają wyłącznie upoważnieni użytkownicy o roli administracyjnej.</li>
      <li>System automatycznie wyłącza poprzednie tokeny przy dodaniu nowego.</li>
    </ul>

    <p class="small mt-4 mb-0 text-muted">
      <i class="ri-question-line me-1"></i>
      Więcej informacji o KSeF znajdziesz na stronie Ministerstwa Finansów:
      <a href="https://www.gov.pl/web/kas/krajowy-system-e-faktur" target="_blank">
        gov.pl/web/kas/krajowy-system-e-faktur
      </a>
    </p>
  </div>
</div>

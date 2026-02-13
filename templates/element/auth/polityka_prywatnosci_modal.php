<?php
/**
 * Element: Polityka prywatności (modal)
 * Plik: templates/element/auth/polityka_prywatnosci_modal.php
 */

$privacyText = <<<'TXT'
§ 9 POLITYKA PRYWATNOŚCI

1. Polityka Prywatności obowiązuje wszystkie osoby odwiedzające Serwis. Usługodawca zastrzega sobie prawo do wprowadzenia ewentualnych zmian w opublikowanej polityce prywatności.

2. W ramach świadczonych usług, Usługodawca może zbierać i przetwarzać dane niezbędne do świadczenia swoich usług związanych z charakterem działalności i wynikających z dostępnych funkcji aplikacji. Usługodawca oświadcza, że używa plików cookies, które pozwalają na identyfikację użytkownika aby tym samym umożliwić i usprawnić korzystanie z naszej aplikacji. W każdej chwili Użytkownik może zmodyfikować ustawienia w przeglądarce internetowej tym samym uniemożliwić zapisywanie plików cookies. Serwis zapewnia, że zapisywanie na komputerze użytkownika bądź każdej innej osoby odwiedzającej serwis plików cookies nie powoduje zmian konfiguracyjnych w komputerze i oprogramowaniu.

3. Usługodawca oświadcza, że gromadzi informacje o osobach odwiedzających stronę wyłącznie na swoje potrzeby, a udostępnianie tychże informacji osobom trzecim możliwe jest tylko, gdy wymagają tego przepisy prawa i w celu świadczenia usług lecz po uzyskaniu stosownej zgody od Użytkownika;

4. Usługodawca zastrzega sobie prawo do sporządzania statystyk charakteryzujących populację Użytkowników bądź innych osób odwiedzających Serwis w celu wykorzystania danych do nawiązywanej współpracy z potencjalnymi Klientami/ partnerami firmy.

5. W ramach realizacji Usługi przetwarzane są następujące dane osobowe Użytkowników:

5.1. nazwisko i imię lub nazwa firmy Użytkownika,

5.2. numer ewidencyjny NIP lub inny numer ewidencyjny Użytkownika,

5.3. miejsce zamieszkania Użytkownika,

5.4. adres poczty elektronicznej Użytkownika,

5.5. numer telefonu kontaktowego posiadanego przez Użytkownika.

6. Operator zastrzega sobie prawo do zmiany zakresu świadczonych usług oraz dodawania i usuwania usług.

7. Udostępnianie danych osobowych przez Użytkowników ma charakter dobrowolny, jednak jest to warunek konieczny do realizacji Usługi. Dane są udostępniane przez Użytkownika na etapie jego rejestracji w Serwisie oraz na etapie dokonywania korekty lub aktualizacji danych.
TXT;

$raw = trim((string)$privacyText);

// Split into points (1–7) while keeping the header/introduction as preamble.
// Result: [preamble, num1, content1, num2, content2, ...]
$chunks = preg_split('/^\s*(\d+)\.\s+/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);

$preamble = trim((string)($chunks[0] ?? ''));
$sections = [];

for ($i = 1; $i < count($chunks); $i += 2) {
  $num = trim((string)($chunks[$i] ?? ''));
  $content = trim((string)($chunks[$i + 1] ?? ''));
  if ($num === '' || $content === '') {
    continue;
  }
  if (!preg_match('/^(?:[1-7])$/', $num)) {
    continue;
  }
  $sections[] = [
    'id' => 'privacy-pt-' . $num,
    'toc' => __('Punkt') . ' ' . $num,
    'heading' => __('Punkt') . ' ' . $num,
    'content' => $content,
  ];
}
?>

<style>
  #privacyModal .modal-content{
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    box-shadow: 0 30px 90px rgba(15, 23, 42, 0.22);
    overflow: hidden;
  }
  #privacyModal .modal-header{
    background: linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
    border-bottom: 1px solid rgba(15, 23, 42, 0.10);
  }
  #privacyModal .modal-title{
    font-weight: 800;
    letter-spacing: 0.01em;
  }
  #privacyModal .modal-body{
    color: #0b0f19;
    background: #fff;
  }
  #privacyModal .pp-preamble{
    font-size: 14px;
    line-height: 1.65;
    color: #0b0f19;
  }
  #privacyModal .pp-section{
    padding-top: 8px;
    margin-top: 18px;
    border-top: 1px solid rgba(15, 23, 42, 0.08);
  }
  #privacyModal .pp-section h3{
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 10px;
  }
  #privacyModal .pp-text{
    font-size: 14px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #0b0f19;
  }

  #privacyModal .pp-toc-title{
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(15, 23, 42, 0.65);
  }
  #privacyModal .pp-toc a{
    display: block;
    padding: 8px 10px;
    border-radius: 10px;
    color: rgba(15, 23, 42, 0.90);
    text-decoration: none;
  }
  #privacyModal .pp-toc a:hover{
    background: rgba(var(--primary-rgb), 0.10);
  }

  @media (min-width: 992px){
    #privacyModal .pp-toc{
      position: sticky;
      top: 0;
    }
  }
</style>

<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="privacyModalLabel"><?= __('Polityka prywatności') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Zamknij') ?>"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-12 col-lg-4">
            <div class="pp-toc">
              <div class="pp-toc-title mb-2"><?= __('Spis punktów') ?></div>
              <?php foreach ($sections as $sec): ?>
                <a href="#<?= h($sec['id']) ?>" data-pp-scroll="#<?= h($sec['id']) ?>"><?= h($sec['toc']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-12 col-lg-8">
            <?php if ($preamble !== ''): ?>
              <div class="pp-preamble mb-3" style="white-space: pre-wrap;">
                <?= h($preamble) ?>
              </div>
            <?php endif; ?>

            <?php foreach ($sections as $sec): ?>
              <section class="pp-section" id="<?= h($sec['id']) ?>">
                <h3><?= h($sec['heading']) ?></h3>
                <div class="pp-text"><?= h($sec['content']) ?></div>
              </section>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __('Zamknij') ?></button>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    function scrollModalBody(modalEl, targetEl){
      const body = modalEl.querySelector('.modal-body');
      if (!body) return;

      const bodyRect = body.getBoundingClientRect();
      const targetRect = targetEl.getBoundingClientRect();
      const top = (targetRect.top - bodyRect.top) + body.scrollTop - 8;

      body.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    document.addEventListener('click', function(e){
      const link = e.target && e.target.closest && e.target.closest('#privacyModal [data-pp-scroll]');
      if (!link) return;

      e.preventDefault();
      const selector = link.getAttribute('data-pp-scroll');
      const modalEl = document.getElementById('privacyModal');
      if (!modalEl || !selector) return;

      const targetEl = modalEl.querySelector(selector);
      if (!targetEl) return;
      scrollModalBody(modalEl, targetEl);
    });
  })();
</script>

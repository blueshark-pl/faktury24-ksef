<?php
/**
 * Element: Regulamin (modal) – LEVEL: kancelaria prawna
 * Plik: templates/element/auth/regulamin_modal.php
 * CakePHP 5 + Bootstrap 5
 */

$regulaminText = <<<'TXT'
REGULAMIN ŚWIADCZENIA USŁUG DROGĄ ELEKTRONICZNĄ – FAKTURY24
Wersja: 2.0
Data wejścia w życie: 13.02.2026

Niniejszy Regulamin określa zasady korzystania z Serwisu Faktury24 dostępnego w szczególności pod adresami: faktury24.com oraz faktury.partnersc.com (dalej: „Serwis”).

§ 1 Usługodawca

1. Usługodawcą jest:
   Biuro Rachunkowe „PARTNER” s.c.
   Iwona Morawska, Paweł Maciak
   01-402 Warszawa, ul. Ciołka 10
   NIP: 527-251-12-37, REGON: 140584751
   e-mail: kontakt@faktury24.com
   Infolinia: 801 002 292 (pon.–pt. 7:00–15:00)

§ 2 Definicje

1. Użytkownik – osoba korzystająca z Serwisu.
2. Klient – podmiot, na rzecz którego Użytkownik korzysta z Serwisu.
3. Konto – indywidualny dostęp do Serwisu.
4. Panel – część Serwisu dostępna po zalogowaniu.
5. Usługi – funkcjonalności udostępniane w Serwisie.
6. KSeF – Krajowy System e-Faktur.
7. Regulamin – niniejszy dokument.

§ 3 Zawarcie umowy

1. Umowa zostaje zawarta z chwilą:
   a) utworzenia Konta,
   b) akceptacji Regulaminu.
2. Regulamin jest udostępniany nieodpłatnie w Serwisie.
3. Prawem właściwym jest prawo polskie.

§ 4 Wymagania techniczne

1. Dostęp do Internetu i aktualna przeglądarka internetowa.
2. Aktywny adres e-mail.

§ 5 Konto i bezpieczeństwo

1. Użytkownik zobowiązuje się do:
   a) zachowania poufności danych logowania,
   b) nieudostępniania Konta osobom nieuprawnionym,
   c) aktualizacji danych.
2. Użytkownik ponosi odpowiedzialność za działania w ramach swojego Konta.

§ 6 Charakter Serwisu

1. Serwis działa jako:
   a) publiczny SaaS,
   b) narzędzie dla klientów biura rachunkowego.
2. Zakres funkcji może się różnić zależnie od planu.

§ 7 Zakres usług

1. Serwis umożliwia w szczególności:
   a) wystawianie dokumentów sprzedażowych,
   b) zarządzanie kontrahentami,
   c) raportowanie i eksport,
   d) integrację z KSeF (jeżeli dostępna).

§ 8 KSeF

1. Serwis generuje XML na podstawie danych Użytkownika.
2. Serwis:
   a) nie przechowuje certyfikatów ani tokenów KSeF,
   b) nie cache’uje UPO,
   c) może przechowywać metadane operacyjne.
3. Użytkownik odpowiada za poprawność danych.

§ 9 Opłaty

1. Korzystanie może być odpłatne.
2. Szczegóły określa cennik lub umowa indywidualna.
3. Faktury mogą być doręczane elektronicznie.

§ 10 Odpowiedzialność

1. Usługodawca nie gwarantuje nieprzerwanego działania.
2. Nie ponosi odpowiedzialności za:
   a) błędne dane Użytkownika,
   b) niedostępność KSeF,
   c) zdarzenia niezależne.

§ 11 Blokada i rozwiązanie

1. Usługodawca może zablokować Konto w razie naruszenia Regulaminu.
2. Użytkownik może rozwiązać umowę poprzez usunięcie Konta.

§ 12 Reklamacje

1. Reklamacje należy kierować na: kontakt@faktury24.com.
2. Odpowiedź w terminie do 30 dni.

§ 13 Polityka prywatności

1. Zasady przetwarzania danych przez Usługodawcę jako Administratora określa Polityka Prywatności.

§ 14 Powierzenie przetwarzania danych (DPA)

1. W zakresie, w jakim Użytkownik wprowadza do Serwisu dane osobowe swoich kontrahentów, Usługodawca działa jako podmiot przetwarzający.
2. Zastosowanie ma Umowa Powierzenia Przetwarzania Danych Osobowych (DPA), stanowiąca Załącznik nr 1 do niniejszego Regulaminu.
3. Akceptacja Regulaminu oznacza akceptację DPA.

§ 15 Postanowienia końcowe

1. Usługodawca może zmienić Regulamin z ważnych przyczyn.
2. Zmiany publikowane są w Serwisie.
TXT;

$raw = (string)$regulaminText;

$chunks = preg_split('/^\s*§\s*(\d+)\s+([^\r\n]+)\s*$/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
$preamble = trim((string)($chunks[0] ?? ''));
$sections = [];

for ($i = 1; $i < count($chunks); $i += 3) {
  $num = trim((string)($chunks[$i] ?? ''));
  $title = trim((string)($chunks[$i + 1] ?? ''));
  $content = trim((string)($chunks[$i + 2] ?? ''));
  if ($num === '' || $title === '' || $content === '') continue;

  $sections[] = [
    'id' => 'reg-par-' . $num,
    'toc' => '§ ' . $num . ' — ' . $title,
    'heading' => '§ ' . $num . ' ' . $title,
    'content' => $content,
  ];
}
?>

<style>
  #regulaminModal .modal-content{
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.10);
    box-shadow: 0 34px 110px rgba(15, 23, 42, 0.22);
    overflow: hidden;
    background: #fff;
  }
  #regulaminModal .modal-header{
    background: linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
    border-bottom: 1px solid rgba(15, 23, 42, 0.10);
  }
  #regulaminModal .modal-title{
    font-weight: 900;
    letter-spacing: 0.01em;
  }
  #regulaminModal .modal-body{
    color: #0b0f19;
    background: #fff;
  }

  /* Preamble rendered as paragraphs (prevents big blank gaps) */
  #regulaminModal .reg-preamble{
    font-size: 14px;
    line-height: 1.75;
    color: #0b0f19;
    margin: 0 0 10px;
  }

  #regulaminModal .reg-section{
    padding-top: 14px;
    margin-top: 18px;
    border-top: 1px solid rgba(15, 23, 42, 0.08);
  }
  #regulaminModal .reg-section h3{
    font-size: 18px;
    font-weight: 900;
    margin: 0 0 10px;
    letter-spacing: 0.01em;
  }
  #regulaminModal .reg-text{
    font-size: 14px;
    line-height: 1.8;
    white-space: pre-wrap;
    color: #0b0f19;
  }

  #regulaminModal .reg-toc{
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 14px;
    padding: 12px;
    background: rgba(248,250,252,0.6);
  }
  #regulaminModal .reg-toc-title{
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.10em;
    text-transform: uppercase;
    color: rgba(15, 23, 42, 0.65);
  }
  #regulaminModal .reg-toc a{
    display: block;
    padding: 9px 10px;
    border-radius: 10px;
    color: rgba(15, 23, 42, 0.92);
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    line-height: 1.35;
  }
  #regulaminModal .reg-toc a:hover{
    background: rgba(var(--primary-rgb), 0.10);
  }

  #regulaminModal .reg-meta{
    font-size: 12px;
    color: rgba(15, 23, 42, 0.65);
    margin-top: 6px;
    line-height: 1.5;
  }

  @media (min-width: 992px){
    #regulaminModal .reg-toc{
      position: sticky;
      top: 0;
    }
  }
</style>

<div class="modal fade" id="regulaminModal" tabindex="-1" aria-labelledby="regulaminModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="regulaminModalLabel"><?= __('Regulamin') ?></h5>
          <div class="reg-meta"><?= h('Faktury24 — wersja 2.0, obowiązuje od 13.02.2026') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Zamknij') ?>"></button>
      </div>

      <div class="modal-body">
        <div class="row g-4">
          <div class="col-12 col-lg-4">
            <div class="reg-toc">
              <div class="reg-toc-title mb-2"><?= __('Spis paragrafów') ?></div>
              <?php foreach ($sections as $sec): ?>
                <a href="#<?= h($sec['id']) ?>" data-reg-scroll="#<?= h($sec['id']) ?>"><?= h($sec['toc']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="col-12 col-lg-8">
            <?php if (trim($preamble) !== ''): ?>
              <?php foreach (preg_split("/\r\n|\n|\r/", (string)$preamble) as $line): ?>
                <?php if (trim((string)$line) !== ''): ?>
                  <p class="reg-preamble"><?= h($line) ?></p>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>

            <?php foreach ($sections as $sec): ?>
              <section class="reg-section" id="<?= h($sec['id']) ?>">
                <h3><?= h($sec['heading']) ?></h3>
                <div class="reg-text"><?= h($sec['content']) ?></div>
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
      const top = (targetRect.top - bodyRect.top) + body.scrollTop - 10;

      body.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    document.addEventListener('click', function(e){
      const link = e.target && e.target.closest && e.target.closest('#regulaminModal [data-reg-scroll]');
      if (!link) return;

      e.preventDefault();
      const selector = link.getAttribute('data-reg-scroll');
      const modalEl = document.getElementById('regulaminModal');
      if (!modalEl || !selector) return;

      const targetEl = modalEl.querySelector(selector);
      if (!targetEl) return;

      scrollModalBody(modalEl, targetEl);
    });
  })();
</script>

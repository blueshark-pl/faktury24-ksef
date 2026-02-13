<?php
/**
 * Element: Polityka prywatności (modal) – LEVEL: kancelaria prawna
 * Plik: templates/element/auth/polityka_prywatnosci_modal.php
 * CakePHP 5 + Bootstrap 5
 */

$privacyText = <<<'TXT'
POLITYKA PRYWATNOŚCI FAKTURY24
Wersja: 1.0
Data wejścia w życie: 13.02.2026

Niniejsza Polityka Prywatności określa zasady przetwarzania danych osobowych oraz wykorzystywania plików cookies i podobnych technologii w ramach Serwisu Faktury24 (dalej: „Serwis”). Dokument ma zastosowanie do Użytkowników Serwisu, w tym osób działających w imieniu Klientów (np. przedsiębiorców), a także osób odwiedzających Serwis.

§ 1 Definicje

1. Administrator – Biuro Rachunkowe „PARTNER” s.c. Iwona Morawska, Paweł Maciak, 01-402 Warszawa, ul. Ciołka 10, NIP: 527-251-12-37, REGON: 140584751.
2. Użytkownik – osoba korzystająca z Serwisu (także działająca w imieniu podmiotu gospodarczego).
3. Klient – podmiot (np. przedsiębiorca), na rzecz którego Użytkownik korzysta z Serwisu.
4. KSeF – Krajowy System e-Faktur (system teleinformatyczny administracji publicznej).
5. Dane konta – dane związane z założeniem i utrzymaniem konta w Serwisie (np. identyfikatory, dane kontaktowe, uprawnienia, historia operacji).
6. Dane dokumentowe – dane zawarte w dokumentach wprowadzanych do Serwisu (np. dane kontrahentów, dane faktur).
7. UPO – urzędowe poświadczenie odbioru dokumentu elektronicznego w KSeF.

§ 2 Administrator danych i kontakt

1. Administratorem danych osobowych jest:
   Biuro Rachunkowe „PARTNER” s.c. Iwona Morawska, Paweł Maciak
   01-402 Warszawa, ul. Ciołka 10
   NIP: 527-251-12-37, REGON: 140584751
   e-mail: kontakt@faktury24.com
   Infolinia: 801 002 292 (pon.–pt. 7:00–15:00)
   (dalej: „Administrator”).
2. W sprawach dotyczących przetwarzania danych osobowych można kontaktować się z Administratorem pod adresem e-mail: kontakt@faktury24.com.

§ 3 Role RODO w modelu „biuro rachunkowe + SaaS”

1. Administrator przetwarza dane w dwóch podstawowych modelach:
   a) Model SaaS (publiczny) – Użytkownik zakłada konto i korzysta z Serwisu jako usługi elektronicznej.
   b) Model obsługi księgowej – Użytkownik/ Klient korzysta z Serwisu w związku z obsługą księgową świadczoną przez Administratora.
2. W Modelu SaaS Administrator co do zasady działa jako administrator danych w zakresie:
   a) Danych konta,
   b) danych rozliczeniowych związanych z płatnościami/abonamentem (jeżeli dotyczy),
   c) logów i bezpieczeństwa.
3. W Modelu obsługi księgowej Administrator może działać:
   a) jako administrator danych – w zakresie wynikającym z realizacji usług księgowych i obowiązków prawnych, albo
   b) jako podmiot przetwarzający – gdy przetwarza dane w imieniu Klienta na podstawie odrębnej umowy (np. umowy powierzenia).
4. Szczegółowy zakres ról i odpowiedzialności w relacji z Klientem może wynikać z umów zawartych z Klientem (np. umowy o świadczenie usług, regulaminu, umowy powierzenia).

§ 4 Zakres danych i źródła danych

1. Dane mogą pochodzić bezpośrednio od Użytkownika (np. rejestracja, profil, konfiguracja, wprowadzanie danych do dokumentów).
2. Przetwarzane mogą być w szczególności:
   a) Dane identyfikacyjne (np. imię i nazwisko, nazwa firmy, NIP, REGON, KRS – jeżeli podane),
   b) Dane kontaktowe (np. e-mail, numer telefonu),
   c) Dane adresowe (np. adres siedziby/korespondencyjny – jeżeli podane),
   d) Dane dokumentowe (np. dane kontrahentów, treść dokumentów księgowych, dane faktur),
   e) Dane techniczne (np. adres IP, dane przeglądarki/urządzenia, znaczniki czasu),
   f) Dane eksploatacyjne i bezpieczeństwa (np. logi działań, historia operacji w Serwisie).

§ 5 KSeF – zasady przetwarzania danych w ramach integracji

1. Serwis umożliwia przygotowanie faktury ustrukturyzowanej (XML) na podstawie danych wprowadzonych przez Użytkownika w Serwisie.
2. W zakresie korzystania z KSeF, Serwis może przetwarzać:
   a) treść faktury ustrukturyzowanej (XML) wygenerowanej z danych wprowadzonych przez Użytkownika,
   b) metadane techniczne operacji (np. identyfikatory żądań, statusy, daty operacji),
   c) identyfikatory/numery zwrócone przez KSeF (np. numer KSeF – jeżeli dostępny).
3. Administrator nie przechowuje certyfikatów ani tokenów KSeF.
4. Użytkownik, aby korzystać z funkcji KSeF, udostępnia Serwisowi uprawnienia do wystawiania faktur w KSeF (po stronie KSeF), w zakresie wymaganym do działania funkcji.
5. Serwis nie przechowuje (nie cache’uje) UPO – poświadczenia mogą być pobierane bezpośrednio z KSeF na żądanie Użytkownika.
6. Administrator może prowadzić rejestry/logi operacji związanych z KSeF w zakresie niezbędnym do bezpieczeństwa, rozliczalności i diagnostyki.

§ 6 Cele i podstawy prawne przetwarzania

Dane osobowe mogą być przetwarzane w następujących celach:

1. Świadczenie usług drogą elektroniczną (utworzenie konta, logowanie, obsługa funkcji Serwisu, wsparcie) – na podstawie niezbędności do wykonania umowy lub działań przed jej zawarciem.
2. Wypełnianie obowiązków prawnych (np. podatkowych/księgowych – w zakresie mającym zastosowanie) – na podstawie obowiązku prawnego.
3. Zapewnienie bezpieczeństwa Serwisu, zapobieganie nadużyciom, prowadzenie logów i audytu (w tym rozliczalność operacji KSeF wykonywanych przez Użytkownika) – na podstawie prawnie uzasadnionego interesu Administratora.
4. Ustalanie, dochodzenie lub obrona roszczeń – na podstawie prawnie uzasadnionego interesu Administratora.
5. Analityka i statystyka korzystania z Serwisu oraz rozwój funkcji – na podstawie prawnie uzasadnionego interesu Administratora; w zakresie wymagającym zgody (np. cookies analityczne) – na podstawie zgody.
6. Marketing własnych usług (jeżeli dotyczy) – na podstawie zgody lub prawnie uzasadnionego interesu (w zależności od formy i kanału kontaktu oraz ustawień Użytkownika).

§ 7 Odbiorcy danych i podmioty przetwarzające

1. Dane mogą być przekazywane podmiotom przetwarzającym działającym na zlecenie Administratora (np. dostawcy hostingu/chmury, dostawcy usług IT, dostawcy poczty, narzędzia helpdesk, dostawcy SMS) – wyłącznie w zakresie niezbędnym do działania Serwisu i na podstawie umów powierzenia.
2. W zakresie integracji KSeF dane z faktury ustrukturyzowanej są przekazywane do KSeF zgodnie z dyspozycją Użytkownika i w ramach udostępnionych przez niego uprawnień.
3. Dane mogą być udostępniane organom publicznym wyłącznie w przypadkach przewidzianych przepisami prawa.

§ 8 Przekazywanie danych poza EOG

1. Administrator co do zasady nie zamierza przekazywać danych poza Europejski Obszar Gospodarczy (EOG).
2. Jeżeli jednak dojdzie do przekazania danych poza EOG (np. w związku z wykorzystaniem dostawcy infrastruktury/oprogramowania), Administrator zapewni zastosowanie wymaganych prawem zabezpieczeń (np. standardowe klauzule umowne), o ile mają zastosowanie.

§ 9 Okres przechowywania danych (retencja)

1. Dane konta przetwarzane są przez okres posiadania konta w Serwisie, a następnie przez okres niezbędny do:
   a) obsługi rozliczeń i ewentualnych reklamacji,
   b) obrony i dochodzenia roszczeń,
   c) zapewnienia bezpieczeństwa i rozliczalności (logi).
2. Dane dokumentowe przechowywane są przez okres korzystania z funkcji Serwisu, a w zakresie usług księgowych lub obowiązków prawnych – zgodnie z właściwymi przepisami lub postanowieniami umów z Klientem.
3. Logi bezpieczeństwa i audytu przechowywane są przez okres uzasadniony bezpieczeństwem i rozliczalnością, z uwzględnieniem zasady minimalizacji.

§ 10 Prawa osób, których dane dotyczą

1. Osobie, której dane dotyczą, przysługują prawa:
   a) dostępu do danych,
   b) sprostowania,
   c) usunięcia (w przypadkach przewidzianych prawem),
   d) ograniczenia przetwarzania,
   e) przenoszenia danych,
   f) sprzeciwu wobec przetwarzania (gdy podstawą jest prawnie uzasadniony interes),
   g) cofnięcia zgody – gdy przetwarzanie odbywa się na podstawie zgody (cofnięcie nie wpływa na zgodność z prawem przetwarzania sprzed cofnięcia).
2. W celu realizacji praw należy skontaktować się z Administratorem: kontakt@faktury24.com.
3. Osobie, której dane dotyczą, przysługuje prawo wniesienia skargi do Prezesa Urzędu Ochrony Danych Osobowych.

§ 11 Cookies i podobne technologie (w tym zgody)

1. Serwis wykorzystuje pliki cookies i podobne technologie w celu:
   a) zapewnienia działania Serwisu (np. utrzymanie sesji, logowanie) – cookies niezbędne,
   b) zapewnienia bezpieczeństwa,
   c) zapamiętania preferencji,
   d) analityki i marketingu (jeżeli stosowane) – z zastrzeżeniem zgód, gdy wymagane.
2. Użytkownik może zarządzać cookies w ustawieniach przeglądarki oraz – jeżeli Serwis udostępnia – w narzędziu ustawień cookies.
3. Ograniczenie stosowania cookies może wpłynąć na działanie niektórych funkcji Serwisu.

§ 12 Bezpieczeństwo danych

1. Administrator stosuje środki techniczne i organizacyjne adekwatne do ryzyk, w tym w szczególności:
   a) szyfrowanie transmisji (TLS),
   b) kontrolę dostępu i uprawnień,
   c) mechanizmy uwierzytelniania,
   d) rejestrowanie zdarzeń (logi) i monitoring bezpieczeństwa.
2. Administrator nie przechowuje certyfikatów ani tokenów KSeF.

§ 13 Zmiany Polityki Prywatności

1. Administrator może zmieniać niniejszą Politykę w przypadku zmian przepisów, funkcji Serwisu lub sposobu świadczenia usług.
2. Nowa wersja Polityki jest publikowana w Serwisie i obowiązuje od daty wskazanej w Polityce.
TXT;

$raw = trim((string)$privacyText);

/**
 * Split by paragraphs "§ <num>"
 * Keep preamble in chunks[0].
 */
$chunks = preg_split('/^\s*§\s*(\d+)\s+/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);

$preamble = trim((string)($chunks[0] ?? ''));
$sections = [];

for ($i = 1; $i < count($chunks); $i += 2) {
    $num = trim((string)($chunks[$i] ?? ''));
    $content = trim((string)($chunks[$i + 1] ?? ''));

    if ($num === '' || $content === '') {
        continue;
    }

    // Extract title from the first line of the section content
    $lines = preg_split("/\r\n|\n|\r/", $content);
    $titleLine = trim((string)($lines[0] ?? ''));
    $title = $titleLine !== '' ? $titleLine : ('§ ' . $num);

    // Remove the title line from content if it is a header-like line (no numbering at start)
    // Example: "Administrator danych i kontakt"
    if ($titleLine !== '' && !preg_match('/^\d+[.)]\s+/', $titleLine)) {
        array_shift($lines);
        // Remove a single empty line after header if present
        if (isset($lines[0]) && trim($lines[0]) === '') {
            array_shift($lines);
        }
        $contentBody = trim(implode("\n", $lines));
    } else {
        $contentBody = $content;
    }

    $sections[] = [
        'id' => 'privacy-par-' . $num,
        'toc' => '§ ' . $num . ' — ' . $title,
        'heading' => '§ ' . $num . ' ' . $title,
        'content' => $contentBody,
    ];
}
?>

<style>
  #privacyModal .modal-content{
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.10);
    box-shadow: 0 34px 110px rgba(15, 23, 42, 0.22);
    overflow: hidden;
    background: #fff;
  }

  #privacyModal .modal-header{
    background: linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
    border-bottom: 1px solid rgba(15, 23, 42, 0.10);
  }

  #privacyModal .modal-title{
    font-weight: 900;
    letter-spacing: 0.01em;
  }

  #privacyModal .modal-body{
    color: #0b0f19;
    background: #fff;
  }

  #privacyModal .pp-preamble{
    font-size: 14px;
    line-height: 1.75;
    color: #0b0f19;
    white-space: pre-wrap;
  }

  #privacyModal .pp-section{
    padding-top: 14px;
    margin-top: 18px;
    border-top: 1px solid rgba(15, 23, 42, 0.08);
  }

  #privacyModal .pp-section h3{
    font-size: 18px;
    font-weight: 900;
    margin: 0 0 10px;
    letter-spacing: 0.01em;
  }

  #privacyModal .pp-text{
    font-size: 14px;
    line-height: 1.8;
    white-space: pre-wrap;
    color: #0b0f19;
  }

  #privacyModal .pp-toc-title{
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.10em;
    text-transform: uppercase;
    color: rgba(15, 23, 42, 0.65);
  }

  #privacyModal .pp-toc{
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 14px;
    padding: 12px;
    background: rgba(248,250,252,0.6);
  }

  #privacyModal .pp-toc a{
    display: block;
    padding: 9px 10px;
    border-radius: 10px;
    color: rgba(15, 23, 42, 0.92);
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    line-height: 1.35;
  }

  #privacyModal .pp-toc a:hover{
    background: rgba(var(--primary-rgb), 0.10);
  }

  #privacyModal .pp-meta{
    font-size: 12px;
    color: rgba(15, 23, 42, 0.65);
    margin-top: 8px;
    line-height: 1.5;
  }

  @media (min-width: 992px){
    #privacyModal .pp-toc{
      position: sticky;
      top: 0;
    }
  }

  /* Optional: nicer print */
  @media print {
    #privacyModal .modal-dialog,
    #privacyModal .modal-content {
      box-shadow: none !important;
      border: none !important;
    }
    #privacyModal .pp-toc { display: none !important; }
  }
</style>

<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="privacyModalLabel"><?= __('Polityka prywatności') ?></h5>
          <div class="pp-meta">
            <?= h('Faktury24 — wersja 1.0, obowiązuje od 13.02.2026') ?>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Zamknij') ?>"></button>
      </div>

      <div class="modal-body">
        <div class="row g-4">
          <div class="col-12 col-lg-4">
            <div class="pp-toc">
              <div class="pp-toc-title mb-2"><?= __('Spis paragrafów') ?></div>
              <?php foreach ($sections as $sec): ?>
                <a href="#<?= h($sec['id']) ?>" data-pp-scroll="#<?= h($sec['id']) ?>"><?= h($sec['toc']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="col-12 col-lg-8">
            <?php if ($preamble !== ''): ?>
            <?php foreach (preg_split("/\r\n|\n|\r/", $preamble) as $line): ?>
                <?php if (trim($line) !== ''): ?>
                <p class="pp-preamble mb-2"><?= h($line) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
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
      const top = (targetRect.top - bodyRect.top) + body.scrollTop - 10;

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

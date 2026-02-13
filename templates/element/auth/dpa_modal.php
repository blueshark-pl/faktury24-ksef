<?php
/**
 * Element: DPA (modal)
 * Plik: templates/element/auth/dpa_modal.php
 */

$modalId = 'dpaModal';

$dpaText = <<<'TXT'
ZAŁĄCZNIK NR 1
UMOWA POWIERZENIA PRZETWARZANIA DANYCH OSOBOWYCH (DPA)

§ 1 Strony

Administratorem danych jest Użytkownik Serwisu.
Procesorem jest:
Biuro Rachunkowe „PARTNER” s.c.
ul. Ciołka 10, 01-402 Warszawa
NIP: 527-251-12-37
kontakt@faktury24.com

§ 2 Zakres powierzenia

1. Administrator powierza Procesorowi dane osobowe wprowadzane do Serwisu.
2. Przetwarzanie odbywa się wyłącznie w celu świadczenia usług Serwisu.

§ 3 Kategorie danych

1. Dane identyfikacyjne (np. imię, nazwisko, nazwa, NIP).
2. Dane adresowe.
3. Dane kontaktowe.
4. Dane finansowe i dokumentowe.

§ 4 Obowiązki Procesora

1. Przetwarzanie wyłącznie na polecenie Administratora.
2. Zachowanie poufności.
3. Stosowanie środków bezpieczeństwa zgodnych z art. 32 RODO.
4. Informowanie o naruszeniach danych bez zbędnej zwłoki.

§ 5 Podpowierzenie

1. Administrator wyraża zgodę na korzystanie z podwykonawców IT i hostingowych.
2. Procesor zapewnia odpowiedni poziom ochrony danych.

§ 6 KSeF

1. Serwis generuje XML na podstawie danych Administratora.
2. Nie przechowuje tokenów ani certyfikatów.
3. Nie cache’uje UPO.

§ 7 Zakończenie

1. Po zakończeniu umowy dane zostają usunięte lub udostępnione do pobrania.
2. DPA stanowi integralną część Regulaminu.
TXT;

$raw = (string)$dpaText;
$chunks = preg_split('/^\s*§\s*(\d+)\s+([^\r\n]+)\s*$/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);

$preamble = trim((string)($chunks[0] ?? ''));
$sections = [];

for ($i = 1; $i < count($chunks); $i += 3) {
  $num = trim((string)($chunks[$i] ?? ''));
  $title = trim((string)($chunks[$i + 1] ?? ''));
  $content = trim((string)($chunks[$i + 2] ?? ''));
  if ($num === '' || $title === '' || $content === '') continue;

  $sections[] = [
    'id' => 'dpa-par-' . $num,
    'toc' => '§ ' . $num . ' — ' . $title,
    'heading' => '§ ' . $num . ' ' . $title,
    'content' => $content,
  ];
}
?>

<style>
  #<?= h($modalId) ?> .modal-content{
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.10);
    box-shadow: 0 34px 110px rgba(15, 23, 42, 0.22);
    overflow: hidden;
    background: #fff;
  }
  #<?= h($modalId) ?> .modal-header{
    background: linear-gradient(180deg, rgba(248,250,252,1) 0%, rgba(255,255,255,1) 100%);
    border-bottom: 1px solid rgba(15, 23, 42, 0.10);
  }
  #<?= h($modalId) ?> .modal-title{
    font-weight: 900;
    letter-spacing: 0.01em;
  }
  #<?= h($modalId) ?> .modal-body{
    color: #0b0f19;
    background: #fff;
  }

  #<?= h($modalId) ?> .reg-preamble{
    font-size: 14px;
    line-height: 1.75;
    color: #0b0f19;
    margin: 0 0 10px;
  }

  #<?= h($modalId) ?> .reg-section{
    padding-top: 14px;
    margin-top: 18px;
    border-top: 1px solid rgba(15, 23, 42, 0.08);
  }
  #<?= h($modalId) ?> .reg-section h3{
    font-size: 18px;
    font-weight: 900;
    margin: 0 0 10px;
    letter-spacing: 0.01em;
  }
  #<?= h($modalId) ?> .reg-text{
    font-size: 14px;
    line-height: 1.8;
    white-space: pre-wrap;
    color: #0b0f19;
  }

  #<?= h($modalId) ?> .reg-toc{
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 14px;
    padding: 12px;
    background: rgba(248,250,252,0.6);
  }
  #<?= h($modalId) ?> .reg-toc-title{
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.10em;
    text-transform: uppercase;
    color: rgba(15, 23, 42, 0.65);
  }
  #<?= h($modalId) ?> .reg-toc a{
    display: block;
    padding: 9px 10px;
    border-radius: 10px;
    color: rgba(15, 23, 42, 0.92);
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    line-height: 1.35;
  }
  #<?= h($modalId) ?> .reg-toc a:hover{
    background: rgba(var(--primary-rgb), 0.10);
  }

  #<?= h($modalId) ?> .reg-meta{
    font-size: 12px;
    color: rgba(15, 23, 42, 0.65);
    margin-top: 6px;
    line-height: 1.5;
  }

  @media (min-width: 992px){
    #<?= h($modalId) ?> .reg-toc{
      position: sticky;
      top: 0;
    }
  }
</style>

<div class="modal fade" id="<?= h($modalId) ?>" tabindex="-1" aria-labelledby="<?= h($modalId) ?>Label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="<?= h($modalId) ?>Label">Załącznik nr 1 (DPA)</h5>
          <div class="reg-meta"><?= h('Umowa Powierzenia Przetwarzania Danych Osobowych') ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>

      <div class="modal-body">
        <div class="row g-4">
          <div class="col-12 col-lg-4">
            <div class="reg-toc">
              <div class="reg-toc-title mb-2">Spis paragrafów</div>
              <?php foreach ($sections as $sec): ?>
                <a href="#<?= h($sec['id']) ?>" data-dpa-scroll="#<?= h($sec['id']) ?>"><?= h($sec['toc']) ?></a>
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
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zamknij</button>
        <button type="button" class="btn btn-primary" data-back-to-regulamin="1">Wróć do Regulaminu</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    function scrollModalBody(modalEl, targetEl){
      const body = modalEl.querySelector('.modal-body');
      if (!body || !targetEl) return;

      const bodyRect = body.getBoundingClientRect();
      const targetRect = targetEl.getBoundingClientRect();
      const top = (targetRect.top - bodyRect.top) + body.scrollTop - 10;

      body.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    document.addEventListener('click', function(e){
      const link = e.target && e.target.closest && e.target.closest('#<?= h($modalId) ?> [data-dpa-scroll]');
      if (!link) return;

      e.preventDefault();
      const selector = link.getAttribute('data-dpa-scroll');
      const modalEl = document.getElementById('<?= h($modalId) ?>');
      if (!modalEl) return;
      const targetEl = modalEl.querySelector(selector);
      if (!targetEl) return;

      scrollModalBody(modalEl, targetEl);
    });

    document.addEventListener('click', function(e){
      const trigger = e.target && e.target.closest && e.target.closest('#<?= h($modalId) ?> [data-back-to-regulamin]');
      if (!trigger) return;

      e.preventDefault();
      if (!window.bootstrap || !bootstrap.Modal) return;

      const dpaEl = document.getElementById('<?= h($modalId) ?>');
      const regulaminEl = document.getElementById('regulaminModal');
      if (!dpaEl || !regulaminEl) return;

      const dpaModal = bootstrap.Modal.getOrCreateInstance(dpaEl);
      const regulaminModal = bootstrap.Modal.getOrCreateInstance(regulaminEl);

      const showRegAfterHide = function () {
        dpaEl.removeEventListener('hidden.bs.modal', showRegAfterHide);
        regulaminModal.show();
      };

      dpaEl.addEventListener('hidden.bs.modal', showRegAfterHide);
      dpaModal.hide();
    });
  })();
</script>

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
  #<?= h($modalId) ?> .legal-shell{
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 1rem;
  }
  @media (max-width: 992px){
    #<?= h($modalId) ?> .legal-shell{ grid-template-columns: 1fr; }
  }
  #<?= h($modalId) ?> .legal-toc{
    position: sticky;
    top: .5rem;
    align-self: start;
    max-height: calc(100vh - 260px);
    overflow: auto;
    border: 1px solid rgba(0,0,0,.08);
    border-radius: .75rem;
    padding: .75rem;
    background: rgba(255,255,255,.65);
    backdrop-filter: blur(6px);
  }
  #<?= h($modalId) ?> .legal-toc a{
    display: block;
    padding: .35rem .5rem;
    border-radius: .5rem;
    text-decoration: none;
    color: inherit;
  }
  #<?= h($modalId) ?> .legal-toc a:hover{ background: rgba(0,0,0,.04); }
  #<?= h($modalId) ?> .legal-content section{
    padding-top: .25rem;
    margin-bottom: 1rem;
  }
  #<?= h($modalId) ?> .legal-content h3{
    font-size: 1.05rem;
    margin: 0 0 .5rem;
  }
  #<?= h($modalId) ?> .legal-text{
    white-space: pre-wrap;
    line-height: 1.45;
  }
</style>

<div class="modal fade" id="<?= h($modalId) ?>" tabindex="-1" aria-labelledby="<?= h($modalId) ?>Label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="<?= h($modalId) ?>Label">Załącznik nr 1 – DPA</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>

      <div class="modal-body">
        <div class="legal-shell">
          <nav class="legal-toc" aria-label="Spis treści DPA">
            <div class="small text-muted mb-2">Spis treści</div>
            <?php if ($preamble !== ''): ?>
              <a href="#" data-dpa-scroll="#dpa-preamble">Wstęp</a>
            <?php endif; ?>
            <?php foreach ($sections as $sec): ?>
              <a href="#" data-dpa-scroll="#<?= h($sec['id']) ?>"><?= h($sec['toc']) ?></a>
            <?php endforeach; ?>
          </nav>

          <div class="legal-content">
            <?php if ($preamble !== ''): ?>
              <section id="dpa-preamble" class="mb-4">
                <div class="legal-text"><?= h($preamble) ?></div>
              </section>
            <?php endif; ?>

            <?php foreach ($sections as $sec): ?>
              <section class="dpa-section" id="<?= h($sec['id']) ?>">
                <h3><?= h($sec['heading']) ?></h3>
                <div class="legal-text"><?= h($sec['content']) ?></div>
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
      const delta = (targetRect.top - bodyRect.top) + body.scrollTop - 8;
      body.scrollTo({ top: delta, behavior: 'smooth' });
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

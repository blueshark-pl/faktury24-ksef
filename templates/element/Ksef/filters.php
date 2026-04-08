<?php
/**
 * KSeF filters element
 * Expects:
 * - string $currentAction
 * - string $peerAction
 * - string $storageKey
 */
$req   = $this->getRequest();
$env   = (string)$req->getQuery('env', 'test');
$from  = (string)$req->getQuery('from', '');
$to    = (string)$req->getQuery('to', '');
$q     = (string)$req->getQuery('q', '');
$ksef  = (string)$req->getQuery('ksef', '');
$inv   = (string)$req->getQuery('inv', '');
$seller= (string)$req->getQuery('seller_nip', '');
$buyer = (string)$req->getQuery('buyer_nip', '');
$debug = $req->getQuery('debug') !== null;

// Oblicz aktualny rok/miesiąc do podświetlenia aktywnego presetu
$curYear  = (int)date('Y');
$curMonth = (int)date('m');
$monthNames = ['', 'Sty', 'Lut', 'Mar', 'Kwi', 'Maj', 'Cze', 'Lip', 'Sie', 'Wrz', 'Paź', 'Lis', 'Gru'];

// Sprawdzenie czy wybrany zakres odpowiada dokładnie miesiącowi
$activePresetYear = null;
$activePresetMonth = null;
if ($from !== '' && $to !== '') {
    try {
        $fd = new \DateTimeImmutable($from);
        $td = new \DateTimeImmutable($to);
        if ((int)$fd->format('j') === 1 && $fd->format('Y-m') === $td->format('Y-m')) {
            $lastDay = (int)$fd->format('t');
            if ((int)$td->format('j') === $lastDay) {
                $activePresetYear = (int)$fd->format('Y');
                $activePresetMonth = (int)$fd->format('n');
            }
        }
    } catch (\Throwable $e) {}
}
?>

<style>
.ksef-filter-months { display: flex; flex-wrap: wrap; gap: .25rem; }
.ksef-filter-months .btn { min-width: 3rem; font-size: .75rem; padding: .2rem .4rem; }
.ksef-filter-year-nav { display: flex; align-items: center; gap: .5rem; }
.ksef-filter-year-nav .year-label { font-weight: 600; font-size: .875rem; min-width: 3rem; text-align: center; }
</style>

<div class="card shadow-sm mb-4">
  <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
    <span class="fw-semibold small"><i class="ri-filter-3-line me-1"></i>Filtry</span>
    <a class="btn btn-sm btn-outline-info" href="<?= $this->Url->build(['action' => $peerAction, '?' => $req->getQueryParams()]) ?>">
      <i class="ri-arrow-left-right-line me-1"></i>Zobacz <?= $peerAction === 'issued' ? 'wystawione' : 'otrzymane' ?>
    </a>
  </div>
  <div class="card-body py-3">
    <form id="ksef-filter-form" method="get">
      <input type="hidden" name="env" value="<?= h($env) ?>" />
      <input type="hidden" name="from" value="<?= h($from) ?>" />
      <input type="hidden" name="to" value="<?= h($to) ?>" />

      <!-- Rok / Miesiąc -->
      <div class="d-flex align-items-center flex-wrap gap-3 mb-3">
        <div class="ksef-filter-year-nav">
          <button type="button" class="btn btn-sm btn-outline-secondary year-prev" title="Poprzedni rok"><i class="ri-arrow-left-s-line"></i></button>
          <span class="year-label" id="filter-year"><?= $activePresetYear ?? $curYear ?></span>
          <button type="button" class="btn btn-sm btn-outline-secondary year-next" title="Następny rok"><i class="ri-arrow-right-s-line"></i></button>
        </div>
        <div class="ksef-filter-months" id="filter-months">
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <?php
              $isActive = ($activePresetYear !== null && $activePresetMonth === $m);
              $isFuture = ($curYear === ($activePresetYear ?? $curYear) && $m > $curMonth);
            ?>
            <button type="button"
              class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?> month-btn"
              data-month="<?= $m ?>"
              <?= $isFuture ? '' : '' ?>><?= $monthNames[$m] ?></button>
          <?php endfor; ?>
        </div>
        <div class="d-flex gap-1 ms-2">
          <button type="button" class="btn btn-xs btn-outline-secondary quick-preset" data-preset="today">Dziś</button>
          <button type="button" class="btn btn-xs btn-outline-secondary quick-preset" data-preset="yesterday">Wczoraj</button>
          <button type="button" class="btn btn-xs btn-outline-secondary quick-preset" data-preset="last7">7 dni</button>
          <button type="button" class="btn btn-xs btn-outline-secondary quick-preset" data-preset="quarter">Kwartał</button>
          <button type="button" class="btn btn-xs btn-outline-secondary quick-preset" data-preset="year">Cały rok</button>
        </div>
      </div>

      <!-- Zakres dat + szukanie -->
      <div class="row g-2 align-items-end">
        <div class="col-6 col-md-auto" style="min-width:140px">
          <label class="form-label small mb-1">Od</label>
          <input type="date" class="form-control form-control-sm" id="filter-from-visible" value="<?= h($from) ?>" />
        </div>
        <div class="col-6 col-md-auto" style="min-width:140px">
          <label class="form-label small mb-1">Do</label>
          <input type="date" class="form-control form-control-sm" id="filter-to-visible" value="<?= h($to) ?>" />
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label small mb-1">Szukaj</label>
          <input type="search" name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="Nr faktury, NIP, nazwa..." />
        </div>
        <div class="col-6 col-md-auto" style="min-width:140px">
          <label class="form-label small mb-1">NIP sprzedawcy</label>
          <input type="text" name="seller_nip" value="<?= h($seller) ?>" class="form-control form-control-sm" placeholder="np. 5213456789" />
        </div>
        <div class="col-auto d-flex gap-2 align-items-end">
          <button class="btn btn-sm btn-primary" type="submit"><i class="ri-search-line me-1"></i>Filtruj</button>
          <a class="btn btn-sm btn-outline-secondary" href="<?= $this->Url->build(['action' => $currentAction, '?' => ['env' => $env]]) ?>">Wyczyść</a>
        </div>
      </div>

      <!-- Zaawansowane (ukryte domyślnie) -->
      <div class="collapse mt-2" id="ksef-adv-filters">
        <div class="row g-2 align-items-end">
          <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Numer KSeF</label>
            <input type="text" name="ksef" value="<?= h($ksef) ?>" class="form-control form-control-sm" />
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Numer faktury</label>
            <input type="text" name="inv" value="<?= h($inv) ?>" class="form-control form-control-sm" />
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small mb-1">NIP nabywcy</label>
            <input type="text" name="buyer_nip" value="<?= h($buyer) ?>" class="form-control form-control-sm" />
          </div>
        </div>
      </div>
      <?php $hasAdvanced = ($ksef !== '' || $inv !== '' || $buyer !== ''); ?>
      <div class="mt-2">
        <button type="button" class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="collapse" data-bs-target="#ksef-adv-filters">
          <i class="ri-equalizer-line me-1"></i>Więcej filtrów
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const form = document.getElementById('ksef-filter-form');
  if (!form) return;
  const hiddenFrom = form.querySelector('input[name="from"]');
  const hiddenTo   = form.querySelector('input[name="to"]');
  const visFrom    = document.getElementById('filter-from-visible');
  const visTo      = document.getElementById('filter-to-visible');
  const yearLabel  = document.getElementById('filter-year');
  const monthBtns  = form.querySelectorAll('.month-btn');
  let selectedYear = parseInt(yearLabel.textContent, 10) || <?= $curYear ?>;

  const pad = n => (n < 10 ? '0' : '') + n;
  const fmt = dt => dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate());

  function syncHidden() {
    hiddenFrom.value = visFrom.value;
    hiddenTo.value   = visTo.value;
  }
  visFrom.addEventListener('change', syncHidden);
  visTo.addEventListener('change', syncHidden);

  function setRange(fromStr, toStr) {
    visFrom.value = fromStr;
    visTo.value   = toStr;
    hiddenFrom.value = fromStr;
    hiddenTo.value   = toStr;
  }

  function highlightMonth() {
    monthBtns.forEach(btn => {
      const m = parseInt(btn.dataset.month, 10);
      const f = hiddenFrom.value;
      const t = hiddenTo.value;
      let active = false;
      if (f && t) {
        const fd = new Date(f + 'T00:00:00');
        const firstDay = new Date(selectedYear, m - 1, 1);
        const lastDay  = new Date(selectedYear, m, 0);
        active = (fd.getTime() === firstDay.getTime() &&
                  new Date(t + 'T00:00:00').getTime() === lastDay.getTime());
      }
      btn.className = 'btn btn-sm ' + (active ? 'btn-primary' : 'btn-outline-secondary') + ' month-btn';
    });
  }

  // Klik na miesiąc → ustaw zakres i submit
  monthBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const m = parseInt(btn.dataset.month, 10);
      const first = new Date(selectedYear, m - 1, 1);
      const last  = new Date(selectedYear, m, 0);
      setRange(fmt(first), fmt(last));
      highlightMonth();
      form.submit();
    });
  });

  // Nawigacja rok
  form.querySelector('.year-prev')?.addEventListener('click', () => {
    selectedYear--;
    yearLabel.textContent = selectedYear;
    highlightMonth();
  });
  form.querySelector('.year-next')?.addEventListener('click', () => {
    selectedYear++;
    yearLabel.textContent = selectedYear;
    highlightMonth();
  });

  // Quick presets
  form.querySelectorAll('.quick-preset').forEach(btn => {
    btn.addEventListener('click', () => {
      const p = btn.dataset.preset;
      const d = new Date();
      if (p === 'today') {
        const s = fmt(d); setRange(s, s);
      } else if (p === 'yesterday') {
        d.setDate(d.getDate() - 1); const s = fmt(d); setRange(s, s);
      } else if (p === 'last7') {
        const to = fmt(d); d.setDate(d.getDate() - 6); setRange(fmt(d), to);
      } else if (p === 'quarter') {
        const qm = Math.floor(d.getMonth() / 3) * 3;
        setRange(fmt(new Date(selectedYear, qm, 1)), fmt(new Date(selectedYear, qm + 3, 0)));
      } else if (p === 'year') {
        setRange(selectedYear + '-01-01', selectedYear + '-12-31');
      }
      highlightMonth();
      form.submit();
    });
  });

  // Auto-open advanced filters if they have values
  <?php if ($hasAdvanced): ?>
    const adv = document.getElementById('ksef-adv-filters');
    if (adv && window.bootstrap) new bootstrap.Collapse(adv, { toggle: true });
    else if (adv) adv.classList.add('show');
  <?php endif; ?>
})();
</script>

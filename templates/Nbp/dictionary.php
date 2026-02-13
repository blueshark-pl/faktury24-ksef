<?php
/**
 * @var \App\View\AppView $this
 * @var \DateTimeInterface $from
 * @var \DateTimeInterface $to
 */
$this->assign('title', 'Słownik Walutowy NBP');
?>

<!-- Optional SVG flags (if you want to add flags to currency select later) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons/css/flag-icons.min.css">

<div class="page-header d-flex align-items-center justify-content-between my-4">
  <div>
    <h1 class="page-title fw-semibold fs-18 mb-1">Słownik Walutowy NBP</h1>
    <div class="text-muted">Przeglądaj średnie kursy NBP (tabele A/B), filtruj po walucie i zakresie dat, eksportuj do CSV.</div>
  </div>
</div>

<div class="card custom-card">
  <div class="card-body">
    <div class="nbp-chart-wrap mb-3">
      <canvas id="nbp-chart" height="140" style="width:100%;"></canvas>
    </div>
    <div class="row g-3 align-items-end">
      <div class="col-lg-4 col-md-6">
        <label class="form-label mb-1">Waluta</label>
        <select id="nbp-currency" class="form-select" data-placeholder="Wyszukaj walutę (kod lub nazwa)"></select>
      </div>
      <div class="col-lg-3 col-md-3">
        <label class="form-label mb-1">Od</label>
        <input type="date" id="nbp-from" class="form-control" value="<?= h($from->format('Y-m-d')) ?>">
      </div>
      <div class="col-lg-3 col-md-3">
        <label class="form-label mb-1">Do</label>
        <input type="date" id="nbp-to" class="form-control" value="<?= h($to->format('Y-m-d')) ?>">
      </div>
      <div class="col-lg-2 col-md-12 d-grid">
        <button id="nbp-run" class="btn btn-primary"><i class="ri-search-line me-1"></i> Pokaż</button>
      </div>
    </div>
    <div class="row g-3 align-items-end mt-2">
      <div class="col-lg-4 col-md-6">
        <label class="form-label mb-1">Porównaj z (opcjonalnie)</label>
        <select id="nbp-currency-2" class="form-select" data-placeholder="Wybierz walutę do porównania"></select>
      </div>
      <div class="col-lg-8 col-md-6">
        <label class="form-label mb-1">Ulubione</label>
        <div id="nbp-fav-list" class="d-flex flex-wrap gap-1"></div>
        <button type="button" class="btn btn-sm btn-outline-warning mt-1" id="nbp-fav-add">
          <i class="ri-star-line me-1"></i> Dodaj bieżącą
        </button>
      </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
      <div class="btn-group btn-group-sm" role="group" aria-label="Zakres czasu">
        <button type="button" class="btn btn-outline-secondary nbp-preset" data-days="7">7 dni</button>
        <button type="button" class="btn btn-outline-secondary nbp-preset" data-days="30">30 dni</button>
        <button type="button" class="btn btn-outline-secondary nbp-preset" data-days="90">90 dni</button>
      </div>
      <div class="form-check form-check-inline ms-1">
        <input class="form-check-input" type="checkbox" id="nbp-ma7" checked>
        <label class="form-check-label small" for="nbp-ma7">Średnia 7</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" id="nbp-ma30">
        <label class="form-check-label small" for="nbp-ma30">Średnia 30</label>
      </div>
      <div class="btn-group btn-group-sm ms-1" role="group" aria-label="Agregacja">
        <input type="radio" class="btn-check" name="nbp-agg" id="nbp-agg-d" autocomplete="off" checked>
        <label class="btn btn-outline-secondary" for="nbp-agg-d">Dziennie</label>
        <input type="radio" class="btn-check" name="nbp-agg" id="nbp-agg-w" autocomplete="off">
        <label class="btn btn-outline-secondary" for="nbp-agg-w">Tyg.</label>
        <input type="radio" class="btn-check" name="nbp-agg" id="nbp-agg-m" autocomplete="off">
        <label class="btn btn-outline-secondary" for="nbp-agg-m">Mies.</label>
      </div>
      <div class="btn-group btn-group-sm ms-1" role="group" aria-label="Metoda agregacji">
        <input type="radio" class="btn-check" name="nbp-aggm" id="nbp-aggm-avg" autocomplete="off" checked>
        <label class="btn btn-outline-secondary" for="nbp-aggm-avg">Śr.</label>
        <input type="radio" class="btn-check" name="nbp-aggm" id="nbp-aggm-close" autocomplete="off">
        <label class="btn btn-outline-secondary" for="nbp-aggm-close">Close</label>
        <input type="radio" class="btn-check" name="nbp-aggm" id="nbp-aggm-median" autocomplete="off">
        <label class="btn btn-outline-secondary" for="nbp-aggm-median">Med.</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" id="nbp-norm">
        <label class="form-check-label small" for="nbp-norm">Indeks 100</label>
      </div>
      <button type="button" class="btn btn-sm btn-outline-success" id="nbp-export">
        <i class="ri-download-2-line me-1"></i> Eksport CSV
      </button>
      <button type="button" class="btn btn-sm btn-outline-success" id="nbp-export-compare" disabled>
        <i class="ri-download-2-line me-1"></i> Eksport CSV (porównanie)
      </button>
      <button type="button" class="btn btn-sm btn-outline-success" id="nbp-export-ratio" disabled>
        <i class="ri-download-2-line me-1"></i> Eksport CSV (A/B)
      </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="nbp-export-png">
          <i class="ri-image-line me-1"></i> Eksport PNG
        </button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="nbp-copy-table">
        <i class="ri-clipboard-line me-1"></i> Kopiuj tabelę
      </button>
      <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Tryb porównania">
        <input type="radio" class="btn-check" name="nbp-mode" id="nbp-mode-overlay" autocomplete="off" checked>
        <label class="btn btn-outline-secondary" for="nbp-mode-overlay">Nakładka</label>
        <input type="radio" class="btn-check" name="nbp-mode" id="nbp-mode-ratio" autocomplete="off">
        <label class="btn btn-outline-secondary" for="nbp-mode-ratio">Stosunek</label>
      </div>
      <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Akcje porównania">
        <button type="button" class="btn btn-outline-secondary" id="nbp-swap"><i class="ri-swap-line me-1"></i> Zamień</button>
        <button type="button" class="btn btn-outline-secondary" id="nbp-copylink"><i class="ri-links-line me-1"></i> Kopiuj link</button>
      </div>
      <div class="ms-2 small nbp-legend d-flex align-items-center gap-2">
        <span><span class="nbp-dot nbp-dot-primary"></span> Główna</span>
        <span><span class="nbp-dot nbp-dot-compare"></span> Porównanie</span>
      </div>
      <div class="btn-group btn-group-sm ms-2" role="group" aria-label="Pomoc">
        <button type="button" class="btn btn-outline-info" id="nbp-help" title="Legenda / pomoc" aria-label="Legenda / pomoc">
          <i class="ri-information-line"></i>
        </button>
      </div>
      <div class="ms-auto d-flex align-items-center gap-2">
        <div id="nbp-meta" class="small text-muted"></div>
        <div id="nbp-spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status"></div>
      </div>
    </div>

    <div id="nbp-info" class="alert alert-info d-none mt-3 mb-0 py-2 px-3">
      <i class="ri-information-line me-1"></i>
      <span>PLN to waluta bazowa – przyjmujemy kurs 1.0000 dla dni roboczych.</span>
    </div>

    <!-- Popover z legendą (własny, lekki) -->
    <div id="nbp-help-popover" class="nbp-popover d-none" role="dialog" aria-modal="false" aria-labelledby="nbp-help-title">
      <div id="nbp-help-title" class="fw-semibold mb-1">Legenda funkcji</div>
      <ul class="mb-2 ps-3 small">
        <li><strong>Zakres</strong>: przyciski 7/30/90 dni szybkie ustawienie dat.</li>
        <li><strong>Średnie 7/30</strong>: nakładane linie wygładzające (MA).</li>
        <li><strong>Indeks 100</strong>: skaluje serię tak, by pierwszy punkt = 100 (tylko w trybie Nakładka).</li>
        <li><strong>Agregacja</strong>: Dziennie / Tyg. / Mies. + metoda <em>Śr.</em> (średnia) lub <em>Close</em> (ostatnia wartość okresu).</li>
        <li><strong>Porównaj z</strong>: druga waluta; tryb <em>Nakładka</em> rysuje dwie serie, <em>Stosunek</em> pokazuje A/B.</li>
        <li><strong>Zamień</strong>: podmienia waluty główną i porównania.</li>
        <li><strong>Kopiuj link</strong>: zapisuje link ze stanem (waluty, daty, tryb, indeks, agregacja).</li>
        <li><strong>Eksport</strong>: CSV (z Δ i Δ%), PNG (zrzut wykresu).</li>
        <li><strong>Tabela</strong>: Kurs, Δ (różnica do poprzedniego punktu), Δ% (zmiana %). Kolory: zielony wzrost, czerwony spadek.</li>
        <li><strong>Wykres</strong>: tooltip z kursem + Δ/Δ%; weekendy (Dziennie) delikatnie podcieniowane.</li>
      </ul>
      <div class="d-flex align-items-center justify-content-between small">
        <div class="text-muted">W trybie Stosunek indeks 100 nie działa; tabela pokazuje serię główną.</div>
        <label class="ms-2 d-inline-flex align-items-center gap-1">
          <input type="checkbox" id="nbp-help-remember" class="form-check-input" style="margin-top:0;"> Zapamiętaj
        </label>
      </div>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-striped table-hover align-middle" id="nbp-table">
        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
          <tr>
            <th style="width: 160px;">Data</th>
            <th class="text-end" style="width: 160px;">Kurs</th>
            <th class="text-end" style="width: 140px;">Δ</th>
            <th class="text-end" style="width: 140px;">Δ%</th>
            <th>Źródło</th>
          </tr>
        </thead>
        <tbody id="nbp-tbody">
          <tr><td colspan="5" class="text-muted text-center">Wybierz walutę i zakres dat, a następnie kliknij „Pokaż”.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
  /* Select2 tweaks to match Bootstrap form-controls */
  .select2-container--default .select2-selection--single{ position:relative; height:38px; border-color:#ced4da; }
  .select2-container--default .select2-selection--single .select2-selection__rendered{ line-height:36px; padding-left:.5rem; padding-right:2.75rem; }
  .select2-container--default .select2-selection--single .select2-selection__arrow{ height:36px; right:.75rem; }
  .select2-container--default .select2-selection--single .select2-selection__placeholder{ color:#6c757d; line-height:36px; }
  /* Keep clear button from overlapping arrow and text (affects the 2nd select which has allowClear) */
  .select2-container--default .select2-selection--single .select2-selection__clear{
    position:absolute; right:2.3rem; top:50%; transform:translateY(-50%);
    margin:0; padding:0 .1rem; color:#6c757d; font-weight:400; line-height:1; z-index:1;
  }
  /* Focus ring to match Bootstrap */
  .select2-container--default.select2-container--focus .select2-selection--single{
    border-color:#86b7fe; outline:0; box-shadow:0 0 0 .2rem rgba(13,110,253,.25);
  }
  .select2-dropdown{ border-color:#ced4da; box-shadow: 0 .25rem .75rem rgba(16,24,40,.08); }
  .select2-results__option{ padding:.375rem .5rem; }
  #nbp-table td:nth-child(2), #nbp-table th:nth-child(2){ font-variant-numeric: tabular-nums; }
  #nbp-table td:nth-child(3), #nbp-table th:nth-child(3){ font-variant-numeric: tabular-nums; }
  #nbp-table td:nth-child(4), #nbp-table th:nth-child(4){ font-variant-numeric: tabular-nums; }
  #nbp-table td:nth-child(2), #nbp-table td:nth-child(3), #nbp-table td:nth-child(4){ text-align: right; }
  .select2-container--open { z-index: 1055; }
  .select2-results{ max-height: 260px; }
  .nbp-chip{ display:inline-flex; align-items:center; gap:.25rem; background:#eef6ff; color:#0d6efd; border:1px solid #d7e9ff; border-radius:999px; padding:.1rem .5rem; font-size:.75rem; }
  .nbp-chip-fav{ background:#fff7e6; color:#d48806; border-color:#ffe8b3; }
  .nbp-chip .nbp-chip-x{ cursor:pointer; display:inline-flex; align-items:center; padding-left:.25rem; color:inherit; opacity:.8; }
  .nbp-muted{ color:#6c757d; }
  .nbp-head{ display:flex; align-items:center; gap:.5rem; }
  .nbp-head .cur{ font-weight:600; }
  .nbp-head .name{ color:#6c757d; }
  .nbp-flag{ margin-right:.35rem; vertical-align:-0.1em; }
  .nbp-alert{ font-size:.9rem; }
  .nbp-chart-wrap{ position: relative; height: 160px; }
  #nbp-chart{ display:block; width:100%; height:140px; }
  #nbp-tooltip{ position:absolute; pointer-events:none; background:#fff; border:1px solid #d0d5dd; border-radius:6px; padding:.25rem .5rem; font-size:.8rem; color:#101828; box-shadow:0 8px 24px rgba(16,24,40,.12); transform:translate(-50%, -120%); white-space:nowrap; }
  .nbp-dot{ display:inline-block; width:10px; height:10px; border-radius:50%; vertical-align:middle; margin-right:.25rem; }
  .nbp-dot-primary{ background:#0d6efd; }
  .nbp-dot-compare{ background:#ef4444; }
  /* Optional fine-tune list spacing in help box */
  #nbp-help-box ul{ margin-bottom: .25rem; }
  #nbp-help-box li{ margin-bottom: .125rem; }
  /* Lightweight popover styling */
  .nbp-popover{ position:absolute; min-width: 300px; max-width: 420px; background:#fff; border:1px solid #d0d5dd; border-radius:8px; box-shadow:0 12px 28px rgba(16,24,40,.18); padding:.5rem .75rem; z-index:1060; }
  .nbp-popover:focus{ outline: none; }
</style>

<script>
(function(){
  const $cur = $('#nbp-currency');
  const $from = $('#nbp-from');
  const $to = $('#nbp-to');
  const $run = $('#nbp-run');
  const $tbody = $('#nbp-tbody');
  const $meta = $('#nbp-meta');
  const $spinner = $('#nbp-spinner');
  const $info = $('#nbp-info');
  let lastData = null;
  let lastData2 = null;
  let resizeTimer = null;
  let mouse = { x: 0, y: 0, over: false };
  let showMA7 = true, showMA30 = false, normalize = false;
  let brush = { active:false, x0:0, x1:0 };
  let lastPlotSeries = [];
  const $cur2 = $('#nbp-currency-2');
  function getMode(){ return $('#nbp-mode-ratio').is(':checked') ? 'ratio' : 'overlay'; }
  function getAgg(){ if ($('#nbp-agg-w').is(':checked')) return 'w'; if ($('#nbp-agg-m').is(':checked')) return 'm'; return 'd'; }
  function getAggMethod(){ if ($('#nbp-aggm-close').is(':checked')) return 'close'; if ($('#nbp-aggm-median').is(':checked')) return 'median'; return 'avg'; }

  // Currency select with Select2 (AJAX to existing endpoint)
  if ($.fn && $.fn.select2){
    // Currency → country code map for flags
    var currencyToCountry = { 'PLN':'pl','EUR':'eu','USD':'us','GBP':'gb','CZK':'cz','CHF':'ch','JPY':'jp','CNY':'cn','SEK':'se','NOK':'no','DKK':'dk','AUD':'au','CAD':'ca','NZD':'nz','HUF':'hu','RON':'ro','BGN':'bg','TRY':'tr','ZAR':'za','UAH':'ua','HRK':'hr','ISK':'is','RSD':'rs','BRL':'br','MXN':'mx','ILS':'il','INR':'in','KRW':'kr','HKD':'hk','SGD':'sg','THB':'th','PHP':'ph','MYR':'my','IDR':'id','AED':'ae','SAR':'sa','QAR':'qa','KWD':'kw','MAD':'ma','TND':'tn','EGP':'eg','BHD':'bh','NGN':'ng','VND':'vn','LKR':'lk','PKR':'pk','GEL':'ge','AMD':'am','AZN':'az','BYN':'by','MKD':'mk','BAM':'ba','ALL':'al','KZT':'kz','UZS':'uz' };
    function flagHtml(code){ var cc = currencyToCountry[String(code||'').toUpperCase()]; return cc? ('<span class="fi fi-'+cc+' nbp-flag"></span>') : ''; }
    $cur.select2({
      placeholder: $cur.data('placeholder') || 'Wyszukaj walutę',
      width: '100%',
      ajax: {
        url: '<?= $this->Url->build(["controller"=>"Invoices","action"=>"nbpCurrencies","_ext"=>"json"]) ?>',
        dataType: 'json', delay: 200, cache: true,
        data: function(params){ return { q: params.term || '' }; },
        processResults: function(data){
          if (data && data.success && Array.isArray(data.results)) return { results: data.results };
          return { results: [] };
        }
      },
      templateResult: function (d){ if(!d.id) return d.text; var code=(d.code||d.id||'').toUpperCase(); return $('<div>'+ flagHtml(code) +'<strong>'+code+'</strong> <span class="text-muted">'+ (d.name||'') +'</span></div>')[0]; },
      templateSelection: function (d){ if(!d || !d.id) return d && d.text ? d.text : ''; var code=(d.id||'').toUpperCase(); return $('<span>'+ flagHtml(code) + code +'</span>')[0]; }
    });
    // Compare select
    $cur2.select2({
      placeholder: $cur2.data('placeholder') || 'Wybierz walutę do porównania',
      allowClear: true,
      width: '100%',
      ajax: {
        url: '<?= $this->Url->build(["controller"=>"Invoices","action"=>"nbpCurrencies","_ext"=>"json"]) ?>',
        dataType: 'json', delay: 200, cache: true,
        data: function(params){ return { q: params.term || '' }; },
        processResults: function(data){ if (data && data.success && Array.isArray(data.results)) return { results: data.results }; return { results: [] }; }
      },
      templateResult: function (d){ if(!d.id) return d.text; var code=(d.code||d.id||'').toUpperCase(); return $('<div>'+ flagHtml(code) +'<strong>'+code+'</strong> <span class="text-muted">'+ (d.name||'') +'</span></div>')[0]; },
      templateSelection: function (d){ if(!d || !d.id) return d && d.text ? d.text : ''; var code=(d.id||'').toUpperCase(); return $('<span>'+ flagHtml(code) + code +'</span>')[0]; }
    });
    // Auto-refresh chart/table when comparison changes (if primary is selected)
  $cur2.on('change', function(){ if ($cur.val()) { fetchRates(); } updateActionState && updateActionState(); });
  }

  function setLoading(on){ $spinner.toggleClass('d-none', !on); $run.prop('disabled', on); }

  function fmt4(n){ try{ return Number(n).toLocaleString('pl-PL', { minimumFractionDigits: 4, maximumFractionDigits: 4 }); }catch(_){ return (typeof n==='number')? n.toFixed(4): String(n); } }

  function applyPreset(days){
    const to = new Date();
    const from = new Date(to.getTime() - (parseInt(days,10)||0)*24*3600*1000);
    const iso = d => d.toISOString().slice(0,10);
    $('#nbp-to').val(iso(to));
    $('#nbp-from').val(iso(from));
  }

  $('.nbp-preset').on('click', function(){ var d=$(this).data('days'); if(d){ applyPreset(d); } });

  function renderRows(rows){
    if (!rows.length){
      $tbody.html('<tr><td colspan="5" class="text-center text-muted">Brak danych w wybranym okresie</td></tr>');
      return;
    }
    let prev = null;
    const html = rows.map(function(r){
      const d = r.effectiveDate || '';
      const v = +r.mid || 0;
      const mid = fmt4(v);
      let delta = '';
      let deltaPct = '';
      if (prev != null && isFinite(prev) && prev !== 0){
        const dVal = v - prev;
        const pVal = (v/prev - 1) * 100;
        const s = dVal>0? '+': (dVal<0? '':'');
        const sp = pVal>0? '+': (pVal<0? '':'');
        const cls = dVal>0? 'text-success': (dVal<0? 'text-danger': 'text-muted');
        delta = '<span class="'+cls+'">'+ s + fmt4(dVal) +'</span>';
        deltaPct = '<span class="'+cls+'">'+ sp + pVal.toFixed(2) + '%</span>';
      } else {
        delta = '<span class="text-muted">—</span>';
        deltaPct = '<span class="text-muted">—</span>';
      }
      prev = v;
      return '<tr><td>'+ d +'</td><td>'+ mid +'</td><td>'+ delta +'</td><td>'+ deltaPct +'</td><td>NBP</td></tr>';
    }).join('');
    $tbody.html(html);
  }

  // Tiny canvas chart (no external deps)
  function renderChart(rows, rows2, mode){
    const canvas = document.getElementById('nbp-chart');
    if (!canvas) return;
    // Resize for crisp lines on DPR
    const parent = canvas.parentElement;
    const dpr = window.devicePixelRatio || 1;
    const cssW = parent.clientWidth;
    const cssH = 140;
    canvas.width = Math.max(300, Math.floor(cssW * dpr));
    canvas.height = Math.floor(cssH * dpr);
    const ctx = canvas.getContext('2d');
    ctx.setTransform(1,0,0,1,0,0);
    ctx.scale(dpr, dpr);
    // Clear
    ctx.clearRect(0,0,cssW,cssH);
    // Empty state
    if (!Array.isArray(rows) || rows.length === 0){
      ctx.fillStyle = '#6c757d';
      ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial';
      ctx.fillText('Brak danych do wykresu', 8, 18);
      return;
    }
    // If ratio mode, ignore rows2 for scaling; otherwise include both for Y scale
    const vals = rows.map(r => +r.mid || 0);
    const vals2 = (Array.isArray(rows2) && mode==='overlay') ? rows2.map(r => +r.mid || 0) : [];
    const min = Math.min.apply(null, vals);
    const max = Math.max.apply(null, vals);
    const minAll = vals2.length ? Math.min(min, Math.min.apply(null, vals2)) : min;
    const maxAll = vals2.length ? Math.max(max, Math.max.apply(null, vals2)) : max;
    const padL = 8, padR = 8, padT = 8, padB = 8;
    const W = cssW - padL - padR;
    const H = cssH - padT - padB;
    const n = vals.length;
    const xFor = i => padL + (n===1 ? W/2 : (i*(W/(n-1))));
    const yFor = v => padT + (H - (maxAll===minAll ? H/2 : ((v - minAll)/(maxAll - minAll))*H));
    // Weekend shading (daily aggregation only)
    try{
      const aggMode = (typeof getAgg === 'function') ? getAgg() : 'd';
      if (aggMode === 'd'){
        const seg = (n===1 ? W : (W/(n-1)));
        ctx.fillStyle = 'rgba(16,24,40,0.045)';
        let prevDate = null;
        for (let i=0;i<n;i++){
          const r = rows[i]; if (!r || !r.effectiveDate) continue;
          const d = toDateUTC(r.effectiveDate);
          const day = d.getUTCDay(); // 0=Sun,6=Sat
          if (day===0 || day===6){
            const cx = xFor(i);
            const x0 = Math.max(padL, cx - seg/2);
            const x1 = Math.min(cssW - padR, cx + seg/2);
            ctx.fillRect(x0, padT, (x1-x0), H);
          }
          // Holiday / no-quotation markers for gaps > 3 days (beyond weekend)
          if (prevDate){
            const diffDays = Math.round((d - prevDate)/86400000);
            if (diffDays > 3){
              const cx = xFor(i);
              ctx.strokeStyle = 'rgba(239,68,68,0.6)';
              ctx.lineWidth = 1;
              ctx.beginPath();
              ctx.moveTo(cx, padT);
              ctx.lineTo(cx, padT + 6);
              ctx.stroke();
            }
          }
          prevDate = d;
        }
      }
    }catch(_){ }
    // Grid (light)
    ctx.strokeStyle = 'rgba(16,24,40,0.08)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    for (let k=0;k<=4;k++){
      const y = padT + (k*(H/4));
      ctx.moveTo(padL, y); ctx.lineTo(cssW - padR, y);
    }
    ctx.stroke();
    // Area fill + primary line
    const path = new Path2D();
    path.moveTo(xFor(0), yFor(vals[0]));
    for (let i=1;i<n;i++) path.lineTo(xFor(i), yFor(vals[i]));
    path.lineTo(xFor(n-1), cssH - padB);
    path.lineTo(xFor(0), cssH - padB);
    path.closePath();
    const grad = ctx.createLinearGradient(0, padT, 0, cssH - padB);
    grad.addColorStop(0, 'rgba(13,110,253,0.18)');
    grad.addColorStop(1, 'rgba(13,110,253,0.03)');
    ctx.fillStyle = grad;
    ctx.fill(path);
    ctx.strokeStyle = '#0d6efd';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(xFor(0), yFor(vals[0]));
    for (let i=1;i<n;i++) ctx.lineTo(xFor(i), yFor(vals[i]));
    ctx.stroke();

    // Compare overlay line
    if (mode === 'overlay' && Array.isArray(rows2) && rows2.length){
      const n2 = rows2.length;
      const xFor2 = i => padL + (n2===1 ? W/2 : (i*(W/(n-1)))); // align by primary spacing
      ctx.strokeStyle = '#ef4444';
      ctx.lineWidth = 1.6;
      ctx.beginPath();
      ctx.moveTo(xFor2(0), yFor(vals2[0]));
      for (let i=1;i<n2;i++) ctx.lineTo(xFor2(i), yFor(vals2[i]));
      ctx.stroke();
    }

    // Moving averages
    function sma(arr, win){
      let out = new Array(arr.length).fill(NaN); let sum=0, q=[];
      for(let i=0;i<arr.length;i++){ sum+=arr[i]; q.push(arr[i]); if(q.length>win){ sum-=q.shift(); }
        if(q.length===win){ out[i]=sum/win; }
      }
      return out;
    }
    if (mode === 'overlay' && showMA7){
      const a7 = sma(vals, 7);
      ctx.strokeStyle = '#22c55e'; ctx.lineWidth = 1.5; ctx.beginPath();
      for(let i=0;i<n;i++){ const v=a7[i]; if(!isFinite(v)) continue; const x=xFor(i), y=yFor(v); if (ctx.currentPathEmpty){ ctx.moveTo(x,y); } else { ctx.lineTo(x,y); } }
      ctx.stroke(); ctx.currentPathEmpty=false;
    }
    if (mode === 'overlay' && showMA30){
      const a30 = sma(vals, 30);
      ctx.strokeStyle = '#f59e0b'; ctx.lineWidth = 1.5; ctx.beginPath();
      for(let i=0;i<n;i++){ const v=a30[i]; if(!isFinite(v)) continue; const x=xFor(i), y=yFor(v); if (ctx.currentPathEmpty){ ctx.moveTo(x,y); } else { ctx.lineTo(x,y); } }
      ctx.stroke(); ctx.currentPathEmpty=false;
    }
    // Last point marker
    const lx = xFor(n-1), ly = yFor(vals[n-1]);
    ctx.fillStyle = '#0d6efd';
    ctx.beginPath(); ctx.arc(lx, ly, 3, 0, Math.PI*2); ctx.fill();
    // Value badge
  const label = fmt4(vals[n-1]);
    ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial';
    const tw = ctx.measureText(label).width + 10;
    const th = 18;
    const bx = Math.min(cssW - padR - tw, Math.max(padL, lx - tw/2));
    const by = Math.max(padT, ly - th - 6);
    ctx.fillStyle = 'rgba(13,110,253,0.12)';
    ctx.strokeStyle = '#0d6efd';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.roundRect(bx, by, tw, th, 6); ctx.fill(); ctx.stroke();
    ctx.fillStyle = '#0d6efd';
    ctx.fillText(label, bx + 5, by + th - 5);
    // Min/Max labels (small)
    ctx.fillStyle = '#6c757d';
    ctx.fillText('min: '+fmt4(min), padL, cssH - 4);
    const maxTxt = 'max: '+fmt4(max);
    const mW = ctx.measureText(maxTxt).width;
    ctx.fillText(maxTxt, cssW - padR - mW, cssH - 4);

    // Brush selection overlay (if active)
    if (brush && brush.active){
      const x0 = Math.min(brush.x0, brush.x1);
      const x1 = Math.max(brush.x0, brush.x1);
      const bx0 = Math.max(padL, Math.min(cssW - padR, x0));
      const bx1 = Math.max(padL, Math.min(cssW - padR, x1));
      ctx.fillStyle = 'rgba(13,110,253,0.15)';
      ctx.fillRect(bx0, padT, bx1 - bx0, H);
      ctx.strokeStyle = 'rgba(13,110,253,0.8)';
      ctx.lineWidth = 1;
      ctx.strokeRect(bx0, padT, bx1 - bx0, H);
    }

    // Hover / crosshair
    const tip = document.getElementById('nbp-tooltip') || (function(){ var d=document.createElement('div'); d.id='nbp-tooltip'; parent.appendChild(d); return d; })();
    tip.style.display = mouse.over ? 'block' : 'none';
    if (mouse.over){
      const relX = Math.max(padL, Math.min(cssW - padR, mouse.x));
      // Find nearest index
      let bestI = 0, bestDX = Infinity;
      for (let i=0;i<n;i++){ const dx = Math.abs(xFor(i) - relX); if (dx < bestDX){ bestDX = dx; bestI = i; } }
      const cx = xFor(bestI), cy = yFor(vals[bestI]);
      // Crosshair
      ctx.strokeStyle = 'rgba(16,24,40,0.2)'; ctx.lineWidth = 1; ctx.setLineDash([3,3]);
      ctx.beginPath(); ctx.moveTo(cx, padT); ctx.lineTo(cx, cssH - padB); ctx.stroke(); ctx.setLineDash([]);
      // Tooltip
  const date = (Array.isArray(rows) && rows[bestI] && rows[bestI].effectiveDate) || '';
      const prev1 = bestI>0 ? vals[bestI-1] : null;
      const d1 = (prev1!=null && isFinite(prev1) && prev1!==0) ? (vals[bestI] - prev1) : null;
      const p1 = (prev1!=null && isFinite(prev1) && prev1!==0) ? ((vals[bestI]/prev1 - 1) * 100) : null;
      if (mode === 'overlay' && Array.isArray(rows2) && rows2.length){
        const v2 = (rows2[bestI] && rows2[bestI].mid) ? rows2[bestI].mid : null;
        const prev2 = (bestI>0 && rows2[bestI-1] && isFinite(rows2[bestI-1].mid)) ? rows2[bestI-1].mid : null;
        const d2 = (v2!=null && prev2!=null && isFinite(prev2) && prev2!==0) ? (v2 - prev2) : null;
        const p2 = (v2!=null && prev2!=null && isFinite(prev2) && prev2!==0) ? ((v2/prev2 - 1) * 100) : null;
        tip.textContent = date + '  |  ' + fmt4(vals[bestI]) + (d1!=null? (' (' + (d1>0?'+':'') + fmt4(d1) + ', ' + (p1>0?'+':'') + (p1).toFixed(2) + '%)') : '') +
                          (v2!=null ? ('  •  ' + fmt4(v2) + (d2!=null? (' (' + (d2>0?'+':'') + fmt4(d2) + ', ' + (p2>0?'+':'') + (p2).toFixed(2) + '%)') : '')) : '');
      } else {
        tip.textContent = date + '  |  ' + fmt4(vals[bestI]) + (d1!=null? (' (' + (d1>0?'+':'') + fmt4(d1) + ', ' + (p1>0?'+':'') + (p1).toFixed(2) + '%)') : '');
      }
      tip.style.left = cx + 'px';
      tip.style.top = cy + 'px';
    }
  }

  function normSeries(rows){
    if (!Array.isArray(rows) || rows.length===0) return rows||[];
    let base = null;
    for (let i=0;i<rows.length;i++){ const v = +rows[i].mid; if (isFinite(v) && v !== 0){ base = v; break; } }
    if (base==null || base===0) return rows;
    return rows.map(r => ({ effectiveDate: r.effectiveDate, mid: (+r.mid||0)/base*100 }));
  }

  function toDateUTC(s){ return new Date(s + 'T00:00:00Z'); }
  function isoYearWeek(d){
    // d is Date in UTC
    const date = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()));
    // Thursday in current week decides the year
    const dayNum = (date.getUTCDay()+6)%7; // 0=Monday
    date.setUTCDate(date.getUTCDate() - dayNum + 3);
    const firstThursday = new Date(Date.UTC(date.getUTCFullYear(),0,4));
    const dayDiff = (date - firstThursday) / 86400000;
    const week = 1 + Math.floor(dayDiff / 7);
    const year = date.getUTCFullYear();
    return { year, week };
  }
  function pad2(n){ return (n<10?'0':'') + n; }
  function aggregateRows(rows, agg, method){
    if (!Array.isArray(rows) || rows.length===0 || agg==='d') return rows||[];
    const map = new Map();
    const ord = [];
    for (const r of rows){
      const d = r.effectiveDate; const v = +r.mid; if (!d || !isFinite(v)) continue;
      const dt = toDateUTC(d);
      let key, label;
      if (agg==='w'){
        const iw = isoYearWeek(dt); key = 'W'+iw.year+'-'+pad2(iw.week); label = d; // we update label to last in bucket
      } else { // 'm'
        key = 'M'+dt.getUTCFullYear()+'-'+pad2(dt.getUTCMonth()+1); label = d;
      }
      if (!map.has(key)){ map.set(key, { sum:0, cnt:0, vals:[], lastDate: label, lastVal: v }); ord.push(key); }
      const obj = map.get(key); obj.sum += v; obj.cnt += 1; obj.vals.push(v); obj.lastDate = label; obj.lastVal = v;
    }
    const out = [];
    for (const key of ord){
      const o = map.get(key);
      let mid;
      if (method==='close') mid = o.lastVal;
      else if (method==='median'){
        const arr = (o.vals||[]).slice().sort((a,b)=>a-b);
        if (arr.length===0) mid = 0; else {
          const m = Math.floor(arr.length/2);
          mid = (arr.length%2===0) ? ((arr[m-1]+arr[m])/2) : arr[m];
        }
      } else { mid = (o.cnt? (o.sum/o.cnt) : 0); }
      out.push({ effectiveDate: o.lastDate, mid });
    }
    return out;
  }

  function renderFromLast(){
    const mode = getMode();
    const agg = getAgg();
    const aggm = getAggMethod();
    let rows = (lastData && lastData.rates) ? lastData.rates : [];
    let rows2 = (lastData2 && lastData2.rates) ? lastData2.rates : null;
    if (mode === 'ratio' && rows && rows2){
      let ratio = computeRatio(rows, rows2);
      ratio = aggregateRows(ratio, agg, aggm);
      lastPlotSeries = ratio;
      renderChart(ratio, null, 'ratio');
      // Table uses primary with aggregation (no normalization in ratio)
      const tableSeries = aggregateRows(rows, agg, aggm);
      renderRows(tableSeries);
    } else {
      // Overlay mode: aggregate first, then optionally normalize
      let r1 = aggregateRows(rows, agg, aggm);
      let r2 = rows2 ? aggregateRows(rows2, agg, aggm) : null;
      if (normalize){ r1 = normSeries(r1); if (r2) r2 = normSeries(r2); }
      lastPlotSeries = r1;
      renderChart(r1, r2, 'overlay');
      renderRows(r1);
    }
  }

  async function fetchRates(){
    const sel = $cur.val();
    if (!sel){ alert('Wybierz walutę.'); return; }
    const code = String(sel).toUpperCase();
    const from = $from.val();
    const to   = $to.val();
    setLoading(true);
    try{
      const baseUrl = '<?= $this->Url->build(["controller"=>"Nbp","action"=>"rates","_ext"=>"json"]) ?>';
      const url1 = baseUrl + '?code=' + encodeURIComponent(code) + '&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
      const res1 = await fetch(url1, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
      const json1 = await res1.json();
      lastData = json1;
      // Optional compare
      const code2 = String($cur2.val()||'').toUpperCase();
      lastData2 = null;
      if (code2 && code2 !== code){
        const url2 = baseUrl + '?code=' + encodeURIComponent(code2) + '&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
        const res2 = await fetch(url2, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
        const json2 = await res2.json();
        if (json2 && json2.success !== false){ lastData2 = json2; }
      }
      if (!json1 || json1.success === false){
        $meta.text(json && json.message ? json.message : 'Brak danych');
        renderRows([]);
        $info.addClass('d-none');
        renderChart([]);
        return;
      }
      const rows = Array.isArray(json1.rates) ? json1.rates : [];
  const header = (json1.currency||code) + (json1.name? (' – ' + json1.name) : '');
  const agg = getAgg(); const aggm = getAggMethod();
  const aggLabel = (agg==='w' ? 'Tyg.' : (agg==='m' ? 'Mies.' : 'Dziennie'));
  const aggmLabel = (aggm==='close' ? 'Close' : (aggm==='median' ? 'Med.' : 'Śr.'));
  const tag = 'Tabela: ' + (json1.table||'') + ' • Zakres: ' + (json1.from||from) + ' → ' + (json1.to||to) + ' • Łącznie: ' + rows.length +
      ' • Agregacja: ' + aggLabel + ' (' + aggmLabel + ')';
      let comp = '';
      if (lastData2){ comp = ' • Porównanie: ' + (lastData2.currency||'') + (lastData2.name? (' – ' + lastData2.name) : ''); }
  // Build meta chips (mode, normalization, aggregation+method, last value)
  const modeNow = getMode();
  let chips = '<span class="nbp-chip">'+ (modeNow==='ratio'?'Stosunek':'Nakładka') +'</span>';
  if ($('#nbp-norm').is(':checked') && modeNow!=='ratio') chips += ' <span class="nbp-chip">Indeks 100</span>';
  chips += ' <span class="nbp-chip">'+aggLabel+' ('+aggmLabel+')</span>';
  // compute last chip from plotted primary series
  (function(){
    try{
      let series;
      if (modeNow==='ratio' && lastData2){
        const baseRatio = computeRatio(rows, (lastData2.rates||[]));
        series = aggregateRows(baseRatio, agg, aggm);
      } else {
        series = aggregateRows(rows, agg, aggm);
        if ($('#nbp-norm').is(':checked')) series = normSeries(series);
      }
      if (Array.isArray(series) && series.length){
        const n = series.length; const v = +series[n-1].mid||0; const prev = (n>1? +series[n-2].mid : null);
        let d='—', p='—';
        if (prev!=null && isFinite(prev) && prev!==0){ const dd=v-prev; const pp=(v/prev-1)*100; d=(dd>0?'+':'')+fmt4(dd); p=(pp>0?'+':'')+pp.toFixed(2)+'%'; }
        const chipText = 'Ostatni: '+fmt4(v)+' ('+d+', '+p+') '+(series[n-1].effectiveDate||'');
        chips += ' <span class="nbp-chip">'+chipText+'</span>';
      }
    }catch(_){ }
  })();
  $meta.html('<span class="nbp-head"><span class="cur">'+ header +'</span> <span class="nbp-chip">'+ (json1.table||'') +'</span> '+chips+' <span class="name nbp-muted">'+ tag + comp +'</span></span>');
      $info.toggleClass('d-none', String(code) !== 'PLN');
      // Render table based on current normalize/mode
      if (getMode() === 'ratio' && lastData2){
        // In ratio mode, keep table on primary (raw or normalized? choose raw for clarity)
        renderRows(rows);
      } else {
        renderRows(normalize ? (function(){
          const ns = []; let base=null; for(let i=0;i<rows.length;i++){ const v=+rows[i].mid; if(base==null && isFinite(v) && v!==0) base=v; if(base){ ns.push({ effectiveDate: rows[i].effectiveDate, mid: (v||0)/base*100 }); } else { ns.push({ effectiveDate: rows[i].effectiveDate, mid: v }); } }
          return ns; })() : rows);
      }
      const mode = getMode();
      if (mode === 'ratio' && lastData2){
        const ratio = computeRatio(rows, lastData2.rates||[]);
        renderChart(ratio, null, 'ratio');
      } else {
        let r1 = rows, r2 = lastData2 ? (lastData2.rates||[]) : null;
        if (normalize){ r1 = normSeries(rows); if (r2) r2 = normSeries(r2); }
        renderChart(r1, r2, 'overlay');
      }
    } catch(err){
      console.error('NBP rates error', err);
      $meta.text('Błąd zapytania');
      renderRows([]);
      $info.addClass('d-none');
      renderChart([], null, 'overlay');
    } finally {
      setLoading(false);
      if (typeof updateActionState === 'function') updateActionState();
    }
  }

  function computeRatio(rowsA, rowsB){
    // Build maps by date
    const mapB = new Map();
    (rowsB||[]).forEach(r => { if (r && r.effectiveDate) mapB.set(r.effectiveDate, +r.mid || 0); });
    const out = [];
    (rowsA||[]).forEach(r => {
      const a = +r.mid || 0; const b = mapB.get(r.effectiveDate);
      if (typeof b === 'number' && isFinite(b) && b !== 0){ out.push({ effectiveDate: r.effectiveDate, mid: a / b }); }
    });
    return out;
  }

  function getDisplayPrimarySeries(){
    const mode = getMode();
    const agg = getAgg();
    const aggm = getAggMethod();
    const data = lastData;
    const base = (data && Array.isArray(data.rates)) ? data.rates : [];
    if (mode === 'ratio'){
      // For CSV in ratio mode, export aggregated primary raw (no normalization)
      return aggregateRows(base, agg, aggm);
    } else {
      let r = aggregateRows(base, agg, aggm);
      if (normalize) r = normSeries(r);
      return r;
    }
  }

  function exportCsv(){
    const data = lastData;
    if (!data || !data.rates || !data.rates.length){
      alert('Brak danych do eksportu.');
      return;
    }
    const rows = getDisplayPrimarySeries();
    // compute deltas
    let prev = null;
    const header = ['data','kurs','delta','delta_pct','waluta','tabela'];
    const lines = [header.join(',')].concat(rows.map(r => {
      const v = +r.mid || 0;
      let d=''; let p='';
      if (prev!=null && isFinite(prev) && prev!==0){ d = (v - prev).toFixed(4); p = ((v/prev - 1)*100).toFixed(2) + '%'; } else { d=''; p=''; }
      prev = v;
      return [r.effectiveDate, v.toFixed(4), d, p, (data.currency||''), (data.table||'')].join(',');
    }));
    const blob = new Blob(["\uFEFF" + lines.join('\n')], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'nbp_'+ (data.currency||'CUR') + '_' + (data.from||'') + '_'+ (data.to||'') + '.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function exportCsvCompare(){
    if (!lastData2 || !lastData2.rates || !lastData2.rates.length){ alert('Brak danych porównania.'); return; }
    const mode = getMode();
    const agg = getAgg();
    const aggm = getAggMethod();
    let r = aggregateRows(lastData2.rates, agg, aggm);
    if (mode==='overlay' && normalize){ r = normSeries(r); }
    let prev = null;
    const header = ['data','kurs','delta','delta_pct','waluta','tabela'];
    const lines = [header.join(',')].concat(r.map(row => {
      const v = +row.mid || 0;
      let d=''; let p='';
      if (prev!=null && isFinite(prev) && prev!==0){ d=(v-prev).toFixed(4); p=((v/prev-1)*100).toFixed(2)+'%'; }
      prev = v;
      return [row.effectiveDate, v.toFixed(4), d, p, (lastData2.currency||''), (lastData2.table||'')].join(',');
    }));
    const blob = new Blob(["\uFEFF" + lines.join('\n')], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'nbp_'+ (lastData2.currency||'CUR') + '_' + (lastData2.from||'') + '_'+ (lastData2.to||'') + '.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function exportCsvRatio(){
    if (!lastData || !lastData2 || !lastData.rates || !lastData2.rates){ alert('Brak danych do stosunku A/B.'); return; }
    const agg = getAgg();
    const aggm = getAggMethod();
    let ratio = computeRatio(lastData.rates, lastData2.rates);
    ratio = aggregateRows(ratio, agg, aggm);
    let prev = null;
    const header = ['data','wartosc_A_div_B','delta','delta_pct','para'];
    const pair = (lastData.currency||'A') + '/' + (lastData2.currency||'B');
    const lines = [header.join(',')].concat(ratio.map(row => {
      const v = +row.mid || 0;
      let d=''; let p='';
      if (prev!=null && isFinite(prev) && prev!==0){ d=(v-prev).toFixed(6); p=((v/prev-1)*100).toFixed(2)+'%'; }
      prev = v;
      return [row.effectiveDate, v.toFixed(6), d, p, pair].join(',');
    }));
    const blob = new Blob(["\uFEFF" + lines.join('\n')], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'nbp_ratio_'+ (lastData.currency||'A') + '_' + (lastData2.currency||'B') + '_' + ($('#nbp-from').val()||'') + '_'+ ($('#nbp-to').val()||'') + '.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function copyTable(){
    const $rows = $('#nbp-table thead tr, #nbp-tbody tr');
    if (!$rows.length){ alert('Brak danych do skopiowania.'); return; }
    const lines = [];
    $rows.each(function(){
      const cols = [];
      $(this).children('th,td').each(function(){
        let t = $(this).text().trim().replace(/\s+/g,' ');
        cols.push(t);
      });
      lines.push(cols.join('\t'));
    });
    const text = lines.join('\n');
    (async function(){
      try{
        if (navigator.clipboard && navigator.clipboard.writeText){ await navigator.clipboard.writeText(text); }
        else { const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); }
        const $btn = $('#nbp-copy-table'); const prev = $btn.html(); $btn.html('<i class="ri-check-line me-1"></i> Skopiowano'); setTimeout(()=> $btn.html(prev), 1200);
      }catch(e){ console.warn('Copy failed', e); }
    })();
  }

  function updateActionState(){
    const hasComp = !!($('#nbp-currency-2').val());
    $('#nbp-export-compare').prop('disabled', !hasComp);
    $('#nbp-export-ratio').prop('disabled', !hasComp);
  }

  $('#nbp-export').on('click', exportCsv);
  $('#nbp-export-compare').on('click', exportCsvCompare);
  $('#nbp-export-ratio').on('click', exportCsvRatio);
  $('#nbp-export-png').on('click', function(){
    try{
      const canvas = document.getElementById('nbp-chart'); if (!canvas) return;
      // Compose PNG with a meta header
      const dpr = window.devicePixelRatio||1;
      const metaParts = [];
      const code = String($('#nbp-currency').val()||'CUR').toUpperCase();
      const code2 = String($('#nbp-currency-2').val()||'').toUpperCase();
      const mode = getMode();
      const agg = getAgg(); const aggm = getAggMethod();
      const aggLabel = (agg==='w' ? 'Tyg.' : (agg==='m' ? 'Mies.' : 'Dziennie')) + ' (' + (aggm==='close'?'Close':(aggm==='median'?'Med.':'Śr.')) + ')';
      const normOn = ($('#nbp-norm').is(':checked') && mode!=='ratio');
      metaParts.push('NBP '+code+(lastData&&lastData.name?(' – '+lastData.name):''));
      if (code2) metaParts.push('vs '+code2);
      metaParts.push(($('#nbp-from').val()||'')+' → '+($('#nbp-to').val()||''));
      metaParts.push('Tryb: '+(mode==='ratio'?'Stosunek':'Nakładka'));
      metaParts.push('Agregacja: '+aggLabel);
      if (normOn) metaParts.push('Indeks 100');
      const header = metaParts.join(' • ');
      const headerH = 28;
      const out = document.createElement('canvas');
      out.width = canvas.width; out.height = canvas.height + Math.floor(headerH*dpr);
      const ctx = out.getContext('2d');
      ctx.fillStyle = '#ffffff'; ctx.fillRect(0,0,out.width,out.height);
      ctx.scale(dpr, dpr);
      ctx.fillStyle = '#101828'; ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial';
      ctx.fillText(header, 8, (headerH-10));
      ctx.setTransform(1,0,0,1,0,0);
      ctx.drawImage(canvas, 0, Math.floor(headerH*dpr));
      const link = document.createElement('a');
      link.href = out.toDataURL('image/png');
      const codeDL = code;
      link.download = 'nbp_chart_'+ codeDL +'.png';
      document.body.appendChild(link); link.click(); document.body.removeChild(link);
    }catch(e){ console.warn('PNG export failed', e); }
  });
  $('#nbp-copy-table').on('click', copyTable);
  $run.on('click', fetchRates);
  // Auto-fetch when date range changes and currency selected
  $('#nbp-from, #nbp-to').on('change', function(){ if ($('#nbp-currency').val()) { fetchRates(); } });
  // Redraw chart on resize (debounced)
  window.addEventListener('resize', function(){
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(renderFromLast, 120);
  });
  // Initial empty chart
  renderChart([]);
  if (typeof updateActionState === 'function') updateActionState();

  // Default load EUR on first open
  if (!$cur.val()){
    var opt = new Option('EUR', 'EUR', true, true);
    $cur.append(opt).trigger('change');
    // Trigger initial fetch with default dates
    setTimeout(fetchRates, 0);
  }

  // Hover handling for chart
  (function(){
    const canvas = document.getElementById('nbp-chart');
    if (!canvas) return;
    const parent = canvas.parentElement;
    function rel(ev){
      const rect = canvas.getBoundingClientRect();
      mouse.x = ev.clientX - rect.left; mouse.y = ev.clientY - rect.top; mouse.over = true; renderFromLast();
    }
    canvas.addEventListener('mousemove', rel);
    canvas.addEventListener('mouseenter', function(){ mouse.over = true; renderFromLast(); });
    canvas.addEventListener('mouseleave', function(){ mouse.over = false; renderFromLast(); });
    // Brush interactions for selecting date range
    canvas.addEventListener('mousedown', function(ev){
      const rect = canvas.getBoundingClientRect();
      brush.active = true; brush.x0 = ev.clientX - rect.left; brush.x1 = brush.x0; renderFromLast();
    });
    window.addEventListener('mousemove', function(ev){ if(!brush.active) return; const rect = canvas.getBoundingClientRect(); brush.x1 = ev.clientX - rect.left; renderFromLast(); });
    window.addEventListener('mouseup', function(){
      if (!brush.active) return;
      const was = { x0: brush.x0, x1: brush.x1 };
      brush.active = false; renderFromLast();
      try{
        const x0 = Math.min(was.x0, was.x1); const x1 = Math.max(was.x0, was.x1);
        const series = Array.isArray(lastPlotSeries) ? lastPlotSeries : [];
        if (series.length < 2) return;
        const parent = canvas.parentElement; const cssW = parent.clientWidth; const padL=8, padR=8; const W = cssW - padL - padR; const n = series.length;
        const xFor = i => padL + (n===1 ? W/2 : (i*(W/(n-1))));
        function nearest(px){ let bi=0,bd=Infinity; for(let i=0;i<n;i++){ const d=Math.abs(xFor(i)-px); if(d<bd){bd=d; bi=i;} } return bi; }
        const i0 = nearest(x0), i1 = nearest(x1);
        const a = Math.min(i0,i1), b = Math.max(i0,i1);
        const dFrom = series[a] && series[a].effectiveDate; const dTo = series[b] && series[b].effectiveDate;
        if (dFrom && dTo){ $('#nbp-from').val(dFrom); $('#nbp-to').val(dTo); if ($('#nbp-currency').val()) { fetchRates(); } }
      }catch(_){ }
    });
    // Also update on scroll to keep tooltip position sensible
    parent.addEventListener('scroll', function(){ if(mouse.over) renderFromLast(); });
  })();

  // MA toggles
  $('#nbp-ma7').on('change', function(){ showMA7 = !!this.checked; renderFromLast(); });
  $('#nbp-ma30').on('change', function(){ showMA30 = !!this.checked; renderFromLast(); });
  $('input[name="nbp-mode"]').on('change', renderFromLast);
  $('#nbp-norm').on('change', function(){ normalize = !!this.checked; renderFromLast(); });
  $('input[name="nbp-agg"]').on('change', renderFromLast);
  $('input[name="nbp-aggm"]').on('change', renderFromLast);
  // Popover: help/legend
  (function(){
    const $btn = $('#nbp-help');
    const $pop = $('#nbp-help-popover');
    const LS_KEY = 'nbp_help_open';
    function place(){
      const btn = $btn[0]; if (!$pop.length || !btn) return;
      const rect = btn.getBoundingClientRect();
      const scrollX = window.pageXOffset || document.documentElement.scrollLeft;
      const scrollY = window.pageYOffset || document.documentElement.scrollTop;
      const top = rect.bottom + scrollY + 8;
      let left = rect.left + scrollX;
      // Keep within viewport
      const maxLeft = scrollX + document.documentElement.clientWidth - $pop.outerWidth() - 8;
      if (left > maxLeft) left = Math.max(8 + scrollX, maxLeft);
      $pop.css({ top: top + 'px', left: left + 'px' });
    }
    function open(){ $pop.removeClass('d-none'); place(); setTimeout(()=>{ document.addEventListener('click', onDocClick, { capture:true, once:false }); window.addEventListener('keydown', onKey); window.addEventListener('resize', onResize); window.addEventListener('scroll', onScroll, true); }, 0); }
    function close(){ $pop.addClass('d-none'); document.removeEventListener('click', onDocClick, { capture:true }); window.removeEventListener('keydown', onKey); window.removeEventListener('resize', onResize); window.removeEventListener('scroll', onScroll, true); }
    function toggle(){ if ($pop.hasClass('d-none')) { open(); } else { close(); } }
    function onDocClick(ev){ if ($pop.hasClass('d-none')) return; const t=ev.target; if ($pop[0].contains(t) || $btn[0].contains(t)) return; close(); }
    function onKey(ev){ if (ev.key === 'Escape'){ close(); } }
    function onResize(){ if (!$pop.hasClass('d-none')) place(); }
    function onScroll(){ if (!$pop.hasClass('d-none')) place(); }
    $btn.on('click', function(e){ e.preventDefault(); toggle(); if (!$pop.hasClass('d-none')) { try{ if ($('#nbp-help-remember').is(':checked')) localStorage.setItem(LS_KEY,'1'); }catch(_){ } } });
    // Remembered state
    try{ if (localStorage.getItem(LS_KEY) === '1'){ open(); } }catch(_){ }
    // Remember checkbox toggling
    $(document).on('change', '#nbp-help-remember', function(){ try{ if (this.checked) localStorage.setItem(LS_KEY,'1'); else localStorage.removeItem(LS_KEY); }catch(_){ } });
  })();

  // Swap currencies (primary <-> compare) then fetch
  $('#nbp-swap').on('click', function(){
    var a = $cur.val();
    var b = $cur2.val();
    // Swap values
    if (b) { $cur.val(b).trigger('change'); } else { $cur.val(null).trigger('change'); }
    if (a) { $cur2.val(a).trigger('change'); } else { $cur2.val(null).trigger('change'); }
    if ($cur.val()) { fetchRates(); }
    if (typeof updateActionState === 'function') updateActionState();
  });

  // Copy deep link reflecting current UI state
  $('#nbp-copylink').on('click', async function(){
    try{
      const params = new URLSearchParams();
      const code = String($('#nbp-currency').val()||'').toUpperCase();
      const code2 = String($('#nbp-currency-2').val()||'').toUpperCase();
      const from = $('#nbp-from').val()||''; const to = $('#nbp-to').val()||'';
      const mode = getMode();
  const agg = (function(){ if ($('#nbp-agg-w').is(':checked')) return 'w'; if ($('#nbp-agg-m').is(':checked')) return 'm'; return 'd'; })();
  const aggm = $('#nbp-aggm-close').is(':checked') ? 'close' : ($('#nbp-aggm-median').is(':checked') ? 'median' : 'avg');
      if (code) params.set('code', code);
      if (code2 && code2 !== code) params.set('code2', code2);
      if (from) params.set('from', from);
      if (to) params.set('to', to);
      if (mode && mode !== 'overlay') params.set('mode', mode);
      if ($('#nbp-norm').is(':checked') && mode !== 'ratio') params.set('norm','1');
      if (agg && agg !== 'd') params.set('agg', agg);
  if (aggm && aggm !== 'avg') params.set('aggm', aggm);
      const url = window.location.origin + window.location.pathname + (params.toString()? ('?'+params.toString()):'');
      if (navigator.clipboard && navigator.clipboard.writeText){
        await navigator.clipboard.writeText(url);
      } else {
        const ta = document.createElement('textarea');
        ta.value = url; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
      }
      const $btn = $('#nbp-copylink'); const prev = $btn.html();
      $btn.html('<i class="ri-check-line me-1"></i> Skopiowano');
      setTimeout(()=> $btn.html(prev), 1200);
    }catch(e){ console.warn('Copy link failed', e); }
  });

  // Deep-link: read code/from/to from URL on load; update URL after fetch
  (function(){
    const params = new URLSearchParams(window.location.search);
    const code = (params.get('code')||'').toUpperCase();
    const code2 = (params.get('code2')||'').toUpperCase();
    const mode = (params.get('mode')||'overlay');
    const norm = (params.get('norm')||'')
      .toString().toLowerCase();
    const normOn = (norm==='1' || norm==='true' || norm==='t');
    const agg = (params.get('agg')||'d');
  const aggm = (params.get('aggm')||'avg');
    const fromQ = params.get('from');
    const toQ = params.get('to');
    let needFetch = false;
    if (code){ var opt = new Option(code, code, true, true); $cur.append(opt).trigger('change'); needFetch = true; }
    if (code2){ var opt2 = new Option(code2, code2, true, true); $cur2.append(opt2).trigger('change'); needFetch = true; }
    if (mode === 'ratio'){ $('#nbp-mode-ratio').prop('checked', true); } else { $('#nbp-mode-overlay').prop('checked', true); }
    if (normOn){ $('#nbp-norm').prop('checked', true).trigger('change'); }
    if (agg === 'w'){ $('#nbp-agg-w').prop('checked', true); }
    else if (agg === 'm'){ $('#nbp-agg-m').prop('checked', true); }
    else { $('#nbp-agg-d').prop('checked', true); }
  if (aggm === 'close'){ $('#nbp-aggm-close').prop('checked', true); }
  else if (aggm === 'median'){ $('#nbp-aggm-median').prop('checked', true); }
  else { $('#nbp-aggm-avg').prop('checked', true); }
    if (fromQ){ $('#nbp-from').val(fromQ); needFetch = true; }
    if (toQ){ $('#nbp-to').val(toQ); needFetch = true; }
    if (needFetch){ setTimeout(fetchRates, 0); }
  })();

  // After each fetch, keep URL in sync
  (function(){
    const origFetch = fetchRates;
    fetchRates = async function(){
      await origFetch.apply(this, arguments);
      try{
        const params = new URLSearchParams(window.location.search);
        const code = String($('#nbp-currency').val()||'').toUpperCase();
        const code2 = String($('#nbp-currency-2').val()||'').toUpperCase();
        const from = $('#nbp-from').val()||''; const to = $('#nbp-to').val()||'';
        const mode = getMode();
        const norm = $('#nbp-norm').is(':checked');
        const agg = (function(){ if ($('#nbp-agg-w').is(':checked')) return 'w'; if ($('#nbp-agg-m').is(':checked')) return 'm'; return 'd'; })();
  const aggm = $('#nbp-aggm-close').is(':checked') ? 'close' : ($('#nbp-aggm-median').is(':checked') ? 'median' : 'avg');
        if (code) params.set('code', code); else params.delete('code');
        if (code2 && code2 !== code) params.set('code2', code2); else params.delete('code2');
        if (from) params.set('from', from); else params.delete('from');
        if (to) params.set('to', to); else params.delete('to');
        if (mode && mode !== 'overlay') params.set('mode', mode); else params.delete('mode');
        if (norm && mode !== 'ratio') params.set('norm', '1'); else params.delete('norm');
        if (agg && agg !== 'd') params.set('agg', agg); else params.delete('agg');
        if (aggm && aggm !== 'avg') params.set('aggm', aggm); else params.delete('aggm');
        const url = window.location.pathname + '?' + params.toString();
        window.history.replaceState({}, '', url);
      }catch(_){ }
    };
  })();

  // Favorites stored in DB (company-scoped)
  (function(){
    const urls = {
      list: '<?= $this->Url->build(["controller"=>"Nbp","action"=>"favorites","_ext"=>"json"]) ?>',
      add: '<?= $this->Url->build(["controller"=>"Nbp","action"=>"favoritesAdd","_ext"=>"json"]) ?>',
      remove: '<?= $this->Url->build(["controller"=>"Nbp","action"=>"favoritesRemove","_ext"=>"json"]) ?>'
    };
    function getCsrfToken(){
      try{
        var m = document.querySelector('meta[name="csrfToken"]');
        if (m && m.content) return m.content;
      }catch(_){ }
      try{
        var match = document.cookie.match(/(?:^|; )csrfToken=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
      }catch(_){ return ''; }
    }
    async function load(){
      try{
        const res = await fetch(urls.list, { headers: { 'Accept':'application/json' }, credentials: 'same-origin' });
        const json = await res.json();
        render(Array.isArray(json.favorites) ? json.favorites : []);
      }catch(e){ console.warn('favorites load failed', e); render([]); }
    }
    function render(list){
      const $c = $('#nbp-fav-list'); if (!$c.length) return;
      if (!list.length){ $c.html('<span class="text-muted small">Brak ulubionych</span>'); return; }
      $c.empty();
      list.forEach(code => {
        const $chip = $('<span class="nbp-chip nbp-chip-fav" data-code="'+code+'">'+code+'<span class="nbp-chip-x" title="Usuń">×</span></span>');
        $c.append($chip);
      });
    }
    async function add(code){
      if(!code) return;
      try{
        const body = new URLSearchParams({ code: String(code).toUpperCase() });
        const res = await fetch(urls.add, {
          method:'POST',
          headers:{ 'Accept':'application/json', 'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrfToken() },
          body,
          credentials:'same-origin'
        });
        await res.json();
        await load();
      }catch(e){ console.warn('favorites add failed', e); }
    }
    async function remove(code){
      if(!code) return;
      try{
        const body = new URLSearchParams({ code: String(code).toUpperCase() });
        const res = await fetch(urls.remove, {
          method:'POST',
          headers:{ 'Accept':'application/json', 'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-Token': getCsrfToken() },
          body,
          credentials:'same-origin'
        });
        await res.json();
        await load();
      }catch(e){ console.warn('favorites remove failed', e); }
    }
    $(document).on('click', '#nbp-fav-add', function(){ const code = String($('#nbp-currency').val()||'').toUpperCase(); if(!code){ alert('Najpierw wybierz walutę.'); return; } add(code); });
    $(document).on('click', '#nbp-fav-list .nbp-chip', function(ev){
      const $t = $(ev.target);
      const $chip = $(this);
      const code = String($chip.data('code')||'').toUpperCase();
      if ($t.hasClass('nbp-chip-x')){ remove(code); return; }
      if (code){ var opt = new Option(code, code, true, true); $('#nbp-currency').append(opt).trigger('change'); setTimeout(fetchRates, 0); }
    });
    load();
  })();
})();
</script>

<?php
/**
 * Element: sekcja „Identyfikatory UE / zagraniczne" nabywcy — wariant PEŁNY.
 *
 * Flagi (countrySelect) dla prefiksu VAT-UE i kodu kraju NrID, blokada „Brak (spoza UE)"
 * (czyści i blokuje Numer VAT-UE + EORI — KodUE i NrVatUE to para XSD), spójne style blokad.
 *
 * Samowystarczalny: ładuje countrySelect z CDN (raz na stronę), definiuje słowniki
 * CS_PL_NAMES/CS_EU_CODES (raz), inicjalizuje pickery po załadowaniu biblioteki
 * i udostępnia window.applyIntlIds(c) do prefillu z JS (bezpieczny przed initem).
 *
 * Zmienne:
 *   $cc — encja InvoiceContractor (lub null) do wartości startowych.
 *
 * Pola formularza (spójne z add.php — kontroler czyta invoice_contractor[...]):
 *   vat_prefix (hidden), vat_eu, eori, tax_id_other, tax_id_other_country (hidden)
 */
$cc = $cc ?? null;
$__vatPrefix = (string)($cc->vat_prefix ?? '');
$__vatEu     = (string)($cc->vat_eu ?? '');
$__eori      = (string)($cc->eori ?? '');
$__taxOther  = (string)($cc->tax_id_other ?? '');
$__taxOtherC = (string)($cc->tax_id_other_country ?? '');
$__vpIsNone  = strtoupper($__vatPrefix) === 'NONE';
$__hasIntl   = (($__vatPrefix !== '' && !$__vpIsNone) || $__vatEu !== '' || $__eori !== '' || $__taxOther !== '' || $__taxOtherC !== '');
?>
<div class="col-12">
  <div class="d-flex align-items-center gap-2 mt-1">
    <small class="text-muted">Identyfikatory UE / zagraniczne</small>
    <div class="form-check form-switch mb-0">
      <input class="form-check-input" type="checkbox" id="snapshot-intl-toggle"<?= $__hasIntl ? ' checked' : '' ?>>
      <label class="form-check-label small" for="snapshot-intl-toggle">Wypełnij</label>
    </div>
  </div>
</div>
<div class="col-12<?= $__hasIntl ? '' : ' d-none' ?>" id="snapshot-intl-fields">
  <div class="row g-2">
    <div class="col-12 col-md-3">
      <input type="hidden" name="invoice_contractor[vat_prefix]" id="inv-vat-prefix-hidden" value="<?= h($__vpIsNone ? '' : $__vatPrefix) ?>">
      <div id="inv-vat-prefix-wrapper">
        <input type="text" id="inv-vat-prefix-ui" class="form-control form-control-sm" placeholder="Prefiks VAT UE" autocomplete="off">
      </div>
      <div class="form-check mt-1">
        <input class="form-check-input" type="checkbox" id="inv-vat-prefix-none">
        <label class="form-check-label small text-muted" for="inv-vat-prefix-none">Brak (spoza UE)</label>
      </div>
    </div>
    <div class="col-12 col-md-5">
      <input type="text" id="inv-vat-eu-field" name="invoice_contractor[vat_eu]" class="form-control form-control-sm" maxlength="32"
        placeholder="Numer VAT-UE (np. 123456789)" value="<?= h($__vatEu) ?>">
    </div>
    <div class="col-12 col-md-4">
      <input type="text" name="invoice_contractor[eori]" class="form-control form-control-sm" maxlength="32"
        placeholder="EORI (np. PL1234567890)" value="<?= h($__eori) ?>">
    </div>
    <div class="col-12 col-md-8">
      <input type="text" name="invoice_contractor[tax_id_other]" class="form-control form-control-sm" maxlength="64"
        placeholder="Inny identyfikator podatkowy" value="<?= h($__taxOther) ?>">
    </div>
    <div class="col-12 col-md-4">
      <input type="hidden" name="invoice_contractor[tax_id_other_country]" id="inv-tax-id-country-hidden" value="<?= h($__taxOtherC) ?>">
      <input type="text" id="inv-tax-id-country-ui" class="form-control form-control-sm" placeholder="Kod kraju (NrID)" autocomplete="off">
    </div>
  </div>
</div>

<style>
  /* ── Identyfikatory UE / zagraniczne: flagi + ładne blokady ── */
  .country-select .flag { background-image: url('https://cdnjs.cloudflare.com/ajax/libs/country-select-js/2.1.1/img/flags.png') !important; }
  @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    .country-select .flag { background-image: url('https://cdnjs.cloudflare.com/ajax/libs/country-select-js/2.1.1/img/flags@2x.png') !important; }
  }
  #snapshot-intl-fields .country-select { width: 100%; }
  #snapshot-intl-fields .country-select input[type="text"] { height: calc(1.5em + .5rem + 2px); border-radius: .25rem; }
  #snapshot-intl-fields input, #inv-vat-prefix-wrapper { transition: background-color .15s ease, opacity .15s ease, filter .15s ease; }
  #snapshot-intl-fields input:disabled, #snapshot-intl-fields input[readonly] {
    background-color: #f1f3f5 !important; color: #9aa0a6 !important; cursor: not-allowed !important; box-shadow: none !important;
  }
  #inv-vat-prefix-wrapper.pe-none { opacity: .7 !important; filter: grayscale(1); cursor: not-allowed; }
  #inv-vat-prefix-wrapper.pe-none .country-select input[type="text"] { background-color: #f1f3f5 !important; color: #9aa0a6 !important; cursor: not-allowed !important; }
  #inv-vat-prefix-wrapper.pe-none .selected-flag { cursor: not-allowed !important; }
  #inv-vat-prefix-none:checked ~ label { color: #6b7280; font-weight: 600; }
</style>

<script>
/* Loader countrySelect (raz na stronę) */
if (!window.__ctrCSLoaded) {
  window.__ctrCSLoaded = true;
  (function(){
    if (!document.querySelector('link[href*="countrySelect"]')) {
      var l = document.createElement('link');
      l.rel = 'stylesheet';
      l.href = 'https://cdnjs.cloudflare.com/ajax/libs/country-select-js/2.1.1/css/countrySelect.css';
      l.integrity = 'sha512-WPc1lYhwI/V+DbzjPRw98rLrQznhpPZ7C/d7K6Vc5s7Sxw2zEk4xLodZwPP0SQ3aLJsBbuaYF0iovbFs2zzKlw==';
      l.crossOrigin = 'anonymous';
      document.head.appendChild(l);
    }
    if (!document.querySelector('script[src*="countrySelect"]')) {
      var s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/country-select-js/2.1.1/js/countrySelect.min.js';
      s.integrity = 'sha512-criuU34pNQDOIx2XSSIhHSvjfQcek130Y9fivItZPVfH7paZDEdtAMtwZxyPq/r2pyr9QpctipDFetLpUdKY4g==';
      s.crossOrigin = 'anonymous';
      document.head.appendChild(s);
    }
  })();
}
window.CS_PL_NAMES = window.CS_PL_NAMES || {
  af:'Afganistan',al:'Albania',dz:'Algieria',ad:'Andora',ao:'Angola',ag:'Antigua i Barbuda',
  sa:'Arabia Saudyjska',ar:'Argentyna',am:'Armenia',au:'Australia',at:'Austria',az:'Azerbejdżan',
  bs:'Bahamy',bh:'Bahrajn',bd:'Bangladesz',bb:'Barbados',be:'Belgia',bz:'Belize',bj:'Benin',
  bt:'Bhutan',by:'Białoruś',bo:'Boliwia',ba:'Bośnia i Hercegowina',bw:'Botswana',br:'Brazylia',
  bn:'Brunei',bg:'Bułgaria',bf:'Burkina Faso',bi:'Burundi',cl:'Chile',cn:'Chiny',hr:'Chorwacja',
  cy:'Cypr',td:'Czad',me:'Czarnogóra',cz:'Czechy',dk:'Dania',cd:'Dem. Republika Konga',
  dj:'Dżibuti',dm:'Dominika',do:'Dominikana',eg:'Egipt',ec:'Ekwador',er:'Erytrea',ee:'Estonia',
  et:'Etiopia',fj:'Fidżi',ph:'Filipiny',fi:'Finlandia',fr:'Francja',ga:'Gabon',gm:'Gambia',
  gh:'Ghana',gr:'Grecja',gd:'Grenada',ge:'Gruzja',gy:'Gujana',gt:'Gwatemala',gn:'Gwinea',
  gw:'Gwinea Bissau',gq:'Gwinea Równikowa',ht:'Haiti',es:'Hiszpania',hn:'Honduras',in:'Indie',
  id:'Indonezja',iq:'Irak',ir:'Iran',ie:'Irlandia',is:'Islandia',il:'Izrael',jm:'Jamajka',
  jp:'Japonia',ye:'Jemen',jo:'Jordania',kh:'Kambodża',cm:'Kamerun',ca:'Kanada',qa:'Katar',
  kz:'Kazachstan',ke:'Kenia',kg:'Kirgistan',ki:'Kiribati',co:'Kolumbia',km:'Komory',cg:'Kongo',
  kp:'Korea Północna',kr:'Korea Południowa',kw:'Kuwejt',la:'Laos',ls:'Lesotho',lb:'Liban',
  lr:'Liberia',ly:'Libia',li:'Liechtenstein',lt:'Litwa',lu:'Luksemburg',lv:'Łotwa',
  mk:'Macedonia Północna',mg:'Madagaskar',mw:'Malawi',mv:'Malediwy',my:'Malezja',ml:'Mali',
  mt:'Malta',ma:'Maroko',mr:'Mauretania',mu:'Mauritius',mx:'Meksyk',fm:'Mikronezja',
  md:'Mołdawia',mc:'Monako',mn:'Mongolia',mz:'Mozambik',mm:'Myanmar',na:'Namibia',nr:'Nauru',
  np:'Nepal',nl:'Niderlandy',de:'Niemcy',ne:'Niger',ng:'Nigeria',ni:'Nikaragua',no:'Norwegia',
  nz:'Nowa Zelandia',om:'Oman',pk:'Pakistan',pw:'Palau',pa:'Panama',pg:'Papua Nowa Gwinea',
  py:'Paragwaj',pe:'Peru',pl:'Polska',pt:'Portugalia',za:'Republika Południowej Afryki',
  cf:'Republika Środkowoafrykańska',ru:'Rosja',ro:'Rumunia',rw:'Rwanda',ws:'Samoa',
  sm:'San Marino',sn:'Senegal',rs:'Serbia',sc:'Seszele',sl:'Sierra Leone',sg:'Singapur',
  sk:'Słowacja',si:'Słowenia',so:'Somalia',lk:'Sri Lanka',us:'Stany Zjednoczone',
  sz:'Eswatini',sd:'Sudan',ss:'Sudan Południowy',sr:'Surinam',sy:'Syria',ch:'Szwajcaria',
  se:'Szwecja',tj:'Tadżykistan',th:'Tajlandia',tz:'Tanzania',tl:'Timor Wschodni',tg:'Togo',
  to:'Tonga',tt:'Trynidad i Tobago',tn:'Tunezja',tr:'Turcja',tm:'Turkmenistan',tv:'Tuvalu',
  ug:'Uganda',ua:'Ukraina',uy:'Urugwaj',uz:'Uzbekistan',vu:'Vanuatu',ve:'Wenezuela',
  hu:'Węgry',gb:'Wielka Brytania',vn:'Wietnam',it:'Włochy',ci:'Wybrzeże Kości Słoniowej',
  xi:'Irlandia Północna (XI)',zm:'Zambia',zw:'Zimbabwe',ae:'Zjednoczone Emiraty Arabskie'
};
window.CS_EU_CODES = window.CS_EU_CODES || [
  'at','be','bg','cy','cz','de','dk','ee','es','fi','fr','gr','hr','hu','ie',
  'it','lt','lu','lv','mt','nl','pl','pt','ro','se','si','sk','xi'
];

(function initIntlIdsSection(){
  if (!(window.jQuery && jQuery.fn && jQuery.fn.countrySelect)) {
    var waited = 0;
    var t = setInterval(function(){
      waited++;
      if (window.jQuery && jQuery.fn && jQuery.fn.countrySelect) { clearInterval(t); initIntlIdsSection(); }
      else if (waited > 160) clearInterval(t);
    }, 50);
    return;
  }
  var $ = jQuery;
  var PL = window.CS_PL_NAMES || {};

  // lock=true blokuje parę KodUE/NrVatUE. EORI: przy ręcznym „Brak" czyścimy i blokujemy,
  // ale gdy preserveEori i EORI ma wartość — zostaje AKTYWNE (disabled nie submituje →
  // blokada z wartością gubiłaby EORI kontrahenta spoza UE przy zapisie).
  function lockVatFields(lock, preserveEori){
    $('#inv-vat-prefix-wrapper').toggleClass('pe-none', lock);
    var $eori = $('[name="invoice_contractor[eori]"]');
    if (lock) {
      $('#inv-vat-eu-field').val('').prop('disabled', true);
      if (preserveEori && ($eori.val()||'').trim() !== '') { $eori.prop('disabled', false); }
      else { $eori.val('').prop('disabled', true); }
    } else {
      $('#inv-vat-eu-field').prop('disabled', false);
      $eori.prop('disabled', false);
    }
  }

  // ── Prefiks VAT-UE (flaga) ──
  var $vpUI = $('#inv-vat-prefix-ui'), $vpH = $('#inv-vat-prefix-hidden'), $vpNone = $('#inv-vat-prefix-none');
  if ($vpUI.length && !$vpUI.data('cs-inited')) {
    $vpUI.data('cs-inited', true);
    var initVp = ($vpH.val() || '').toLowerCase();
    $vpUI.countrySelect({ defaultCountry: initVp || 'pl', preferredCountries: ['pl','de','fr','cz','sk','nl','be','it','es','se'], localizedCountries: PL });
    $vpUI.closest('.country-select').css({ display: 'block', width: '100%' });
    try { var d0 = $vpUI.countrySelect('getSelectedCountryData'); if (d0 && d0.iso2 && initVp) $vpH.val(d0.iso2.toUpperCase()); } catch(e){}
    $vpUI.on('change', function(){
      try {
        var d = $vpUI.countrySelect('getSelectedCountryData');
        if (d && d.iso2) { $vpH.val(d.iso2.toUpperCase()); $vpNone.prop('checked', false); lockVatFields(false); }
      } catch(e){}
    });
    if (!initVp) { try { $vpUI.countrySelect('selectCountry', ''); } catch(e){} $vpH.val(''); }
    // Stan startowy „Brak (spoza UE)": sekcja widoczna + prefiks pusty → zablokuj parę
    // KodUE/NrVatUE (EORI z wartością zostaje aktywne — preserveEori).
    var sectionVisible = !$('#snapshot-intl-fields').hasClass('d-none');
    if (!initVp && sectionVisible) { $vpNone.prop('checked', true); lockVatFields(true, true); }
    $vpNone.on('change', function(){
      if ($vpNone.is(':checked')) {
        try { $vpUI.countrySelect('selectCountry', ''); } catch(e){}
        $vpH.val('');
        lockVatFields(true, false); // ręczna decyzja usera — pełne czyszczenie
      } else {
        lockVatFields(false);
      }
    });
  }

  // ── Kod kraju NrID (flaga, wszystkie kraje) ──
  var $tcUI = $('#inv-tax-id-country-ui'), $tcH = $('#inv-tax-id-country-hidden');
  if ($tcUI.length && !$tcUI.data('cs-inited')) {
    $tcUI.data('cs-inited', true);
    var initTc = ($tcH.val() || '').toLowerCase();
    $tcUI.countrySelect({ defaultCountry: initTc || 'pl', preferredCountries: ['pl','de','cz','sk','gb','us','se','no','ch'], localizedCountries: PL });
    $tcUI.closest('.country-select').css({ display: 'block', width: '100%' });
    try { var d1 = $tcUI.countrySelect('getSelectedCountryData'); if (d1 && d1.iso2 && initTc) $tcH.val(d1.iso2.toUpperCase()); } catch(e){}
    $tcUI.on('change', function(){
      try { var d = $tcUI.countrySelect('getSelectedCountryData'); if (d && d.iso2) $tcH.val(d.iso2.toUpperCase()); } catch(e){}
    });
    if (!initTc) { try { $tcUI.countrySelect('selectCountry', ''); } catch(e){} $tcH.val(''); }
  }

  // ── Przełącznik sekcji ──
  $(document).off('change.intlIds', '#snapshot-intl-toggle').on('change.intlIds', '#snapshot-intl-toggle', function(){
    $('#snapshot-intl-fields').toggleClass('d-none', !this.checked);
  });
})();

/**
 * Prefill sekcji z JS (np. dane kontrahenta z oryginału korekty / katalogu).
 * Bezpieczny przed initem widgetów — ponawia aż countrySelect będzie gotowy.
 * c: { vat_prefix, vat_eu, eori, tax_id_other, tax_id_other_country }
 */
window.applyIntlIds = function(c){
  c = c || {};
  var $ = window.jQuery;
  if (!$) { setTimeout(function(){ window.applyIntlIds(c); }, 120); return; }
  var vpRaw = String(c.vat_prefix || '');
  var vpIsNone = vpRaw.toUpperCase() === 'NONE';
  var vpClean = vpIsNone ? '' : vpRaw.toUpperCase();
  var tc = String(c.tax_id_other_country || '').toUpperCase();
  $('#inv-vat-prefix-hidden').val(vpClean);
  $('#inv-vat-eu-field').val(c.vat_eu || '');
  $('[name="invoice_contractor[eori]"]').val(c.eori || '');
  $('[name="invoice_contractor[tax_id_other]"]').val(c.tax_id_other || '');
  $('#inv-tax-id-country-hidden').val(tc);
  var hasIntl = !!(vpClean || c.vat_eu || c.eori || c.tax_id_other || tc);
  $('#snapshot-intl-toggle').prop('checked', hasIntl);
  $('#snapshot-intl-fields').toggleClass('d-none', !hasIntl);
  (function widgets(tries){
    if (!(window.jQuery && jQuery.fn && jQuery.fn.countrySelect && $('#inv-vat-prefix-ui').data('cs-inited'))) {
      if ((tries||0) < 40) setTimeout(function(){ widgets((tries||0)+1); }, 150);
      return;
    }
    try { $('#inv-vat-prefix-ui').countrySelect('selectCountry', vpClean.toLowerCase()); } catch(e){}
    try { $('#inv-tax-id-country-ui').countrySelect('selectCountry', tc.toLowerCase()); } catch(e){}
    // selectCountry mógł nadpisać hidden przez handler change — przywróć docelowe wartości
    $('#inv-vat-prefix-hidden').val(vpClean);
    $('#inv-tax-id-country-hidden').val(tc);
    var isNone = !vpClean;
    var $eori = $('[name="invoice_contractor[eori]"]');
    $('#inv-vat-prefix-none').prop('checked', hasIntl && isNone);
    $('#inv-vat-prefix-wrapper').toggleClass('pe-none', hasIntl && isNone);
    if (hasIntl && isNone) {
      // para KodUE/NrVatUE: bez prefiksu numer VAT-UE musi być pusty i zablokowany;
      // EORI z wartością zostaje AKTYWNE (disabled nie submituje — nie gubimy danych).
      $('#inv-vat-eu-field').val('').prop('disabled', true);
      if (($eori.val()||'').trim() === '') { $eori.prop('disabled', true); }
      else { $eori.prop('disabled', false); }
    } else {
      $('#inv-vat-eu-field').prop('disabled', false);
      $eori.prop('disabled', false);
    }
  })(0);
};
</script>

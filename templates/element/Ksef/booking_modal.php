<?php
/**
 * Booking modal for selecting items from a KSeF invoice.
 * Expected to be included from received/issued views.
 * Props: env
 */
$env = isset($env) ? (string)$env : 'test';
?>
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bookingModalLabel">Wybierz pozycje do księgowania</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
              <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
                <div>
                  <label class="form-label small mb-1">Domyślny rodzaj kosztu</label>
                  <select id="booking-default-cost" class="form-select form-select-sm">
                    <option value="">— wybierz —</option>
                  </select>
                </div>
                <div class="pb-1">
                  <button type="button" id="booking-apply-all" class="btn btn-sm btn-outline-primary">Zastosuj do wszystkich</button>
                </div>
              </div>
        <div id="booking-recent" class="mb-2 d-flex flex-wrap gap-2"></div>
        <div class="d-flex flex-wrap align-items-end gap-2 mb-2" id="booking-toolbar">
          <div class="flex-grow-1">
            <label class="form-label small mb-1" for="booking-search">Filtr pozycji</label>
            <input id="booking-search" type="search" class="form-control form-control-sm" placeholder="Szukaj po nazwie/kwocie...">
          </div>
          <div class="pb-1 d-flex align-items-center gap-2">
            <button id="booking-select-all" type="button" class="btn btn-sm btn-outline-secondary">Zaznacz wszystkie</button>
            <button id="booking-unselect-all" type="button" class="btn btn-sm btn-outline-secondary">Odznacz wszystkie</button>
            <button id="booking-toggle-compact" type="button" class="btn btn-sm btn-outline-secondary">Widok: kompaktowy</button>
            <button id="booking-expand-all" type="button" class="btn btn-sm btn-outline-secondary">Rozwiń wszystkie</button>
            <button id="booking-collapse-all" type="button" class="btn btn-sm btn-outline-secondary">Zwiń wszystkie</button>
            <span id="booking-counter" class="text-muted small"></span>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge bg-secondary-subtle border text-secondary">KSeF: <span id="booking-ksef"></span></span>
          <span class="badge bg-secondary-subtle border text-secondary">ENV: <span id="booking-env"><?= h($env) ?></span></span>
        </div>
        <div id="booking-spinner" class="d-flex align-items-center gap-2">
          <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
          <span>Ładowanie pozycji…</span>
        </div>
        <ol id="booking-list" class="list-group list-group-numbered mt-3"></ol>
        <div id="booking-info" class="mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zamknij</button>
        <button type="button" id="booking-save" class="btn btn-primary">Zapisz pozycje</button>
      </div>
    </div>
  </div>
</div>

<!-- Select2 (jQuery-based) for searchable selects; loaded only if not already present -->
<script>
(function(){
  function loadScript(src, cb){ var s=document.createElement('script'); s.src=src; s.onload=cb; document.head.appendChild(s); }
  function loadStyle(href){ var l=document.createElement('link'); l.rel='stylesheet'; l.href=href; document.head.appendChild(l); }
  if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)){
    if (!window.jQuery){
      loadScript('https://code.jquery.com/jquery-3.7.1.min.js', function(){
        loadStyle('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        loadScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', function(){});
      });
    } else {
      loadStyle('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
      loadScript('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', function(){});
    }
  }
})();
</script>
<!-- Bootstrap Icons: load once if not present -->
<script>
(function(){
  try {
    if (!document.querySelector('link[href*="bootstrap-icons"]')) {
      var l=document.createElement('link');
      l.rel='stylesheet';
      l.href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css';
      document.head.appendChild(l);
    }
  } catch(_) {}
})();
</script>
<style>
  #booking-list .list-group-item{ border-radius:.5rem; border-width:1px; }
  #booking-list .list-group-item .form-label{ margin-bottom:.25rem; }
  /* Ensure selects have enough room so caret/clear don't overlap */
  .booking-cost-type{ min-width:16rem; }
  .select2-container{ min-width:16rem !important; }
  .select2-container--default .select2-selection--single.select2-selection--sm{ min-height:31px; padding:2px .5rem; }
  .select2-selection.select2-selection--single{ height:auto; }
  .select2-container .select2-selection__rendered{ line-height:28px; }
  .select2-container .select2-selection__arrow{ height:28px; }
  .badge-chip{ border:1px solid var(--bs-border-color); background-color: var(--bs-secondary-bg); color: var(--bs-secondary-color); padding: .25rem .5rem; border-radius: 50rem; cursor:pointer; }
  .badge-chip:hover{ background-color: var(--bs-secondary-bg-subtle); }
  .is-invalid + .select2 .select2-selection{ border-color: var(--bs-danger) !important; }
  .select2-container--default .select2-selection--single .select2-selection__placeholder{ color:#6c757d; }
  /* Key-value details layout tweaks */
  .kv-grid{ display:flex; flex-wrap:wrap; gap:.5rem 1rem; }
  .kv-grid .kv-pair{ display:flex; align-items:baseline; gap:.25rem; min-width: 220px; }
  .kv-pair .text-muted{ margin-right:.125rem; white-space:nowrap; }
  .kv-value-number{ text-align:right; font-variant-numeric: tabular-nums; }
  .badge-num{ display:inline-block; min-width: 7ch; text-align:right; font-variant-numeric: tabular-nums; }
  /* Collapsible details */
  #booking-list.compact .item-details{ display:none; }
  #booking-list.compact li.open .item-details{ display:block; }
  #booking-list .toggle-details{ color: var(--bs-link-color); text-decoration: none; cursor: pointer; }
  #booking-list .toggle-details:hover{ text-decoration: underline; }
  #booking-list .item-header{ }
  /* Subtle dividers between items */
  #booking-list .list-group-item{ border:0; border-bottom:1px solid var(--bs-border-color); border-radius:0; transition: background-color .12s ease; }
  #booking-list .list-group-item:first-child{ border-top:1px solid var(--bs-border-color); }
  /* Hover background */
  #booking-list .list-group-item:hover{ background-color: var(--bs-tertiary-bg); }
  /* Chevron animation */
  .toggle-details i.bi{ transition: transform .15s ease; }
  #booking-list li.open .toggle-details i.bi{ transform: rotate(180deg); }
</style>

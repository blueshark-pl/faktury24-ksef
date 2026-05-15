<?php
/** @var \App\View\AppView $this */
/** @var iterable<\App\Model\Entity\Invoice> $drafts */
/** @var array<string, bool> $personMap */

$this->assign('title', 'Faktury robocze');

$money = static function ($amount, string $currency = 'PLN'): string {
    return number_format((float)$amount, 2, ',', ' ') . ' ' . strtoupper($currency ?: 'PLN');
};

$typeLabels = [
    'vat' => 'VAT',
    'novat' => 'Bez VAT',
    'proforma' => 'Proforma',
    'advance' => 'Zaliczka',
    'correction' => 'Korekta',
    'margin' => 'Marża',
    'internal' => 'Wewnętrzna',
    'oss' => 'OSS',
    'currency' => 'Walutowa',
    'final' => 'Końcowa',
];

$editActionByType = [
    'vat' => 'editVat',
    'currency' => 'editCurrency',
    'novat' => 'editNoVat',
    'proforma' => 'editProforma',
    'advance' => 'editAdvance',
    'correction' => 'editCorrection',
    'margin' => 'editMargin',
    'internal' => 'editInternal',
    'internalevidence' => 'editInternalEvidence',
    'oss' => 'editOss',
];

  $todayDate = (new \DateTimeImmutable('today'))->format('Y-m-d');
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Faktury robocze</h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item"><a href="<?= $this->Url->build(['action' => 'index']) ?>">Faktury</a></li>
      <li class="breadcrumb-item active" aria-current="page">Robocze</li>
    </ol>
  </div>
  <div>
    <?= $this->Html->link('<i class="ri-list-check-2 me-1"></i> Wszystkie faktury', ['action' => 'index'], ['class' => 'btn btn-outline-secondary', 'escape' => false]) ?>
  </div>
</div>

<div class="card">
  <div class="card-header justify-content-between flex-wrap gap-2">
    <div class="card-title d-flex align-items-center gap-2 mb-0">
      Niewysłane dokumenty robocze
      <?php if ($q): ?>
        <span class="badge bg-primary-transparent"><i class="ri-search-line me-1"></i><?= h($q) ?></span>
      <?php endif; ?>
      <?php if ($type): ?>
        <span class="badge bg-secondary-transparent"><?= h($typeLabels[$type] ?? strtoupper($type)) ?></span>
      <?php endif; ?>
    </div>
    <?= $this->Form->create(null, [
      'type' => 'get', 'class' => 'd-flex flex-wrap gap-2 ms-2',
      'role' => 'search', 'aria-label' => 'Filtry roboczych', 'id' => 'drafts-filters-form'
    ]) ?>
      <div class="position-relative">
        <i class="ri-search-line position-absolute" style="left:10px;top:50%;transform:translateY(-50%);color:#9aa0ac;pointer-events:none"></i>
        <input id="drafts-live-search" name="q" type="search"
               class="form-control form-control-sm ps-4"
               placeholder="Szukaj: numer / kontrahent / NIP"
               value="<?= h($q) ?>"
               style="min-width:240px"
               aria-label="Szukaj">
      </div>
      <?= $this->Form->control('type', [
        'type' => 'select', 'label' => false, 'empty' => 'Wszystkie typy',
        'options' => $typeLabels, 'value' => $type,
        'class' => 'form-select form-select-sm',
        'onchange' => 'this.form.requestSubmit()'
      ]) ?>
      <?= $this->Form->control('from', [
        'type' => 'date', 'label' => false, 'value' => $from,
        'class' => 'form-control form-control-sm', 'aria-label' => 'Data od'
      ]) ?>
      <?= $this->Form->control('to', [
        'type' => 'date', 'label' => false, 'value' => $to,
        'class' => 'form-control form-control-sm', 'aria-label' => 'Data do'
      ]) ?>
      <?= $this->Form->control('limit', [
        'type' => 'select', 'label' => false, 'value' => $limit,
        'options' => [10 => '10 / stronę', 20 => '20 / stronę', 50 => '50 / stronę', 100 => '100 / stronę'],
        'class' => 'form-select form-select-sm',
        'onchange' => 'this.form.requestSubmit()'
      ]) ?>
      <div class="btn-group btn-group-sm">
        <button class="btn btn-primary btn-wave" type="submit" title="Zastosuj filtry">
          <i class="ri-search-line me-1"></i>Filtruj
        </button>
        <?= $this->Html->link('<i class="ri-refresh-line"></i>', ['action' => 'drafts'], [
          'class' => 'btn btn-light', 'escape' => false, 'title' => 'Wyczyść filtry'
        ]) ?>
      </div>
    <?= $this->Form->end() ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0" id="drafts-table">
        <thead>
          <tr>
            <th>Status</th>
            <th>Numer</th>
            <th>Typ</th>
            <th>Kontrahent</th>
            <th>Data</th>
            <th class="text-end">Kwota</th>
            <th class="text-end">Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($drafts as $inv): ?>
            <?php
              $typeKey = strtolower((string)($inv->type ?? ''));
              $editAction = $editActionByType[$typeKey] ?? 'edit';
              $contractor = $inv->invoice_contractor ?? null;
              $issueDateValue = $inv->date ? $inv->date->format('Y-m-d') : '';
                $sendConfirmText = 'Wysłać tę fakturę roboczą do KSeF teraz?';
                if ($issueDateValue !== '' && $issueDateValue !== $todayDate) {
                  $sendConfirmText .= "\n\nUwaga: data faktury ($issueDateValue) jest inna niż dzisiaj ($todayDate). Przed wysyłką zostanie zmieniona na dzisiejszą.";
                }
            ?>
            <tr>
              <td><span class="badge bg-warning">Robocza</span></td>
              <td>
                <span class="d-inline-flex align-items-center gap-2">
                  <?= $this->Html->link(h($inv->fullnumber ?: ('ROB-' . substr((string)$inv->id, 0, 8))), ['action' => 'view', $inv->id], ['class' => 'fw-semibold draft-number-link', 'data-id' => $inv->id]) ?>
                  <?php if (empty($inv->fullnumber)): ?>
                    <button type="button"
                            class="btn btn-link btn-sm p-0 js-preview-number"
                            data-id="<?= h($inv->id) ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="Sprawdź przewidywany numer">
                      <i class="ri-eye-line text-primary"></i>
                    </button>
                  <?php endif; ?>
                </span>
              </td>
              <td>
                <span class="badge bg-secondary"><?= h($typeLabels[$typeKey] ?? strtoupper($typeKey)) ?></span>
              </td>
              <td>
                <?= h($contractor->name ?? '—') ?>
              </td>
              <td><?= $inv->date?->format('d.m.Y') ?></td>
              <td class="text-end"><strong><?= h($money($inv->total ?? 0, (string)($inv->currency ?? 'PLN'))) ?></strong></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group">
                  <?= $this->Html->link('Edytuj', ['action' => $editAction, $inv->id], ['class' => 'btn btn-outline-secondary']) ?>
                  <?php
                    // Przycisk "Przenieś do faktur" (bez KSeF) pokazujemy gdy:
                    // - kontrahent z katalogu ma is_person=1 (osoba fizyczna), LUB
                    // - snapshot kontrahenta nie ma NIP (heurystyka: typowo osoba fizyczna).
                    $cid = (string)($inv->contractor_id ?? '');
                    $isPersonInvoice = ($cid !== '' && !empty($personMap[$cid]))
                        || empty(trim((string)($contractor->nip ?? '')));
                  ?>
                  <?php if ($isPersonInvoice): ?>
                    <?= $this->Form->postLink(
                      '<i class="ri-arrow-right-line me-1"></i>Przenieś do faktur',
                      ['action' => 'promoteToIssued', $inv->id],
                      [
                        'escape'  => false,
                        'class'   => 'btn btn-outline-success',
                        'confirm' => 'Przenieść tę fakturę na listę faktur z nadanym numerem? Faktura NIE zostanie wysłana do KSeF.',
                      ]
                    ) ?>
                  <?php endif; ?>
                  <?= $this->Form->postLink('Usuń', ['action' => 'delete', $inv->id], ['class' => 'btn btn-outline-danger', 'confirm' => 'Usunąć tę fakturę roboczą?']) ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (!count($drafts)): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Brak faktur roboczych.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer">
    <ul class="pagination mb-0">
      <?= $this->Paginator->first('«') ?>
      <?= $this->Paginator->prev('‹') ?>
      <?= $this->Paginator->numbers() ?>
      <?= $this->Paginator->next('›') ?>
      <?= $this->Paginator->last('»') ?>
    </ul>
  </div>
</div>

<script>
(function() {
  // === AJAX-refresh tabeli draftów ===
  // Pobiera aktualny URL, parsuje HTML, podmienia tylko <tbody>. Zachowuje paginację i filtry.
  async function refreshDraftsTable() {
    const tbody = document.querySelector('#drafts-table tbody');
    if (!tbody) return;
    tbody.style.opacity = '0.5';
    tbody.style.pointerEvents = 'none';
    try {
      const res = await fetch(window.location.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin'
      });
      if (!res.ok) throw new Error('http ' + res.status);
      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const newTbody = doc.querySelector('#drafts-table tbody');
      if (!newTbody) throw new Error('Brak tbody w odpowiedzi');
      tbody.replaceWith(newTbody);
      // Re-init tooltipów dla nowych ikon „podgląd numeru"
      document.querySelectorAll('#drafts-table .js-preview-number[data-bs-toggle="tooltip"]').forEach(el => {
        if (el.dataset.tipBound) return;
        el.dataset.tipBound = '1';
        try { new bootstrap.Tooltip(el); } catch {}
      });
    } finally {
      const refreshed = document.querySelector('#drafts-table tbody');
      if (refreshed) { refreshed.style.opacity = ''; refreshed.style.pointerEvents = ''; }
    }
  }

  // Submit formularza filtrów → AJAX (zamiast pełnego reload)
  const filtersForm = document.getElementById('drafts-filters-form');
  filtersForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(filtersForm);
    const params = new URLSearchParams();
    for (const [k, v] of fd.entries()) {
      if (v !== '' && v !== null && v !== undefined) params.set(k, String(v));
    }
    const newUrl = filtersForm.action.split('?')[0] + (params.toString() ? ('?' + params.toString()) : '');
    try { history.pushState({}, '', newUrl); } catch {}
    try { await refreshDraftsTable(); } catch { window.location.href = newUrl; }
  });

  // Live search z debounce 400ms — zachowuje pozostałe filtry
  const search = document.getElementById('drafts-live-search');
  if (search) {
    let t;
    search.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => filtersForm?.requestSubmit(), 400);
    });
  }

  // Back/Forward button
  window.addEventListener('popstate', () => {
    try { refreshDraftsTable(); } catch {}
  });

  // Inicjalizuj tooltipy Bootstrap dla ikon „przewidywany numer"
  document.querySelectorAll('.js-preview-number[data-bs-toggle="tooltip"]').forEach(function (el) {
    try { new bootstrap.Tooltip(el); } catch {}
  });

  // Delegowany handler — klik w ikonkę 👁 obok ROB-xxxx
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('.js-preview-number');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    const id = btn.dataset.id;
    if (!id) return;
    if (btn.dataset.loading === '1') return;
    btn.dataset.loading = '1';

    // Ikona → spinner na czas fetch
    const icon = btn.querySelector('i');
    const originalHtml = icon ? icon.outerHTML : '';
    if (icon) icon.outerHTML = '<span class="spinner-border spinner-border-sm text-primary" style="width:.8rem;height:.8rem"></span>';

    try {
      const res = await fetch('<?= $this->Url->build(['controller' => 'Invoices', 'action' => 'previewKsefNumber']) ?>/' + encodeURIComponent(id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!data.success || !data.fullnumber) {
        throw new Error(data.error || 'Nie udało się obliczyć numeru.');
      }
      // Zamień link „ROB-xxx" na przewidywany numer + badge „podgląd"
      const link = document.querySelector('.draft-number-link[data-id="' + id + '"]');
      if (link) {
        const wrapper = link.parentElement;
        wrapper.innerHTML = '<span class="fw-semibold">' + data.fullnumber.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) +
          '</span> <span class="badge bg-info-subtle text-info" data-bs-toggle="tooltip" title="Numer przewidywany — może się zmienić jeśli inna faktura zostanie wysłana wcześniej">podgląd</span>';
        // Re-init tooltipy dla nowego badge
        wrapper.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => { try { new bootstrap.Tooltip(el); } catch {} });
      }
    } catch (err) {
      // Przywróć ikonę i pokaż błąd
      if (icon) {
        btn.innerHTML = originalHtml;
      }
      if (typeof window.showToast === 'function') {
        window.showToast(err.message || 'Nie udało się sprawdzić numeru.', 'danger');
      } else {
        alert(err.message || 'Nie udało się sprawdzić numeru.');
      }
    } finally {
      btn.dataset.loading = '';
    }
  });
})();
</script>


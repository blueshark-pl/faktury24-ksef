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
  <div class="card-header d-flex align-items-center justify-content-between">
    <div class="card-title mb-0">Niewysłane dokumenty robocze</div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
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


<?php
/**
 * Admin: logi usuniętych faktur
 *
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $logs
 */
$this->assign('title', 'Logi usuniętych faktur');

$q    = trim((string)$this->request->getQuery('q'));
$from = $this->request->getQuery('from');
$to   = $this->request->getQuery('to');

$typeLabel = function(string $type): string {
    return match($type) {
        'vat'        => 'VAT',
        'proforma'   => 'Proforma',
        'novat'      => 'Bez VAT',
        'correction' => 'Korekta',
        'advance'    => 'Zaliczkowa',
        'final'      => 'Końcowa',
        'margin'     => 'Marżowa',
        'currency'   => 'Walutowa',
        'rental'     => 'Najem',
        default      => h($type),
    };
};
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Logi usuniętych faktur</h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item"><a href="<?= $this->Url->build(['action' => 'adminInvoices']) ?>">Admin — faktury</a></li>
      <li class="breadcrumb-item active">Logi usunięć</li>
    </ol>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <?= $this->Form->create(null, [
      'type'  => 'get',
      'url'   => ['action' => 'adminDeletionLogs'],
      'class' => 'd-flex flex-wrap gap-2 align-items-end',
    ]) ?>
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Szukaj: numer, firma, kontrahent, admin…" value="<?= h($q) ?>" style="width:260px">
      <input type="date" name="from" class="form-control form-control-sm" value="<?= h((string)$from) ?>" title="Data usunięcia od" style="width:140px">
      <input type="date" name="to"   class="form-control form-control-sm" value="<?= h((string)$to) ?>"   title="Data usunięcia do" style="width:140px">
      <div class="btn-group btn-group-sm">
        <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i>Filtruj</button>
        <?= $this->Html->link('<i class="ri-refresh-line"></i>', ['action' => 'adminDeletionLogs'], ['escape' => false, 'class' => 'btn btn-light', 'title' => 'Wyczyść']) ?>
      </div>
    <?= $this->Form->end() ?>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0 fs-13">
        <thead class="table-light">
          <tr>
            <th style="width:16%"><?= $this->Paginator->sort('AdminDeletionLogs.company_name', 'Firma') ?></th>
            <th style="width:14%"><?= $this->Paginator->sort('AdminDeletionLogs.fullnumber', 'Numer') ?></th>
            <th style="width:7%">Typ</th>
            <th style="width:16%">Nabywca</th>
            <th style="width:7%">Data fakt.</th>
            <th style="width:9%" class="text-end">Kwota</th>
            <th style="width:14%"><?= $this->Paginator->sort('AdminDeletionLogs.deleted_by_username', 'Usunął') ?></th>
            <th style="width:11%"><?= $this->Paginator->sort('AdminDeletionLogs.created', 'Kiedy usunięto') ?></th>
            <th style="width:6%" class="text-center">Snapshot</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td class="text-truncate" style="max-width:0">
              <span class="fw-medium d-block text-truncate"><?= h((string)($log->company_name ?? '—')) ?></span>
            </td>
            <td class="fw-semibold text-nowrap"><?= h((string)($log->fullnumber ?: '—')) ?></td>
            <td class="text-nowrap"><?= $typeLabel((string)($log->invoice_type ?? '')) ?></td>
            <td class="text-truncate" style="max-width:0">
              <span class="d-block text-truncate"><?= h((string)($log->contractor_name ?? '—')) ?></span>
              <?php if (!empty($log->contractor_nip)): ?>
                <small class="text-muted"><?= h($log->contractor_nip) ?></small>
              <?php endif ?>
            </td>
            <td class="text-nowrap"><?= h((string)($log->invoice_date ?? '—')) ?></td>
            <td class="text-end fw-medium text-nowrap">
              <?= $log->total !== null ? number_format((float)$log->total, 2, ',', ' ') . ' ' . h($log->currency ?? 'PLN') : '—' ?>
            </td>
            <td class="text-truncate" style="max-width:0">
              <span class="d-block text-truncate" title="<?= h((string)($log->deleted_by_username ?? '')) ?>">
                <?= h((string)($log->deleted_by_username ?? '—')) ?>
              </span>
            </td>
            <td class="text-nowrap">
              <?php if (!empty($log->created)): ?>
                <?= h(date('Y-m-d', strtotime((string)$log->created))) ?>
                <br><small class="text-muted"><?= h(date('H:i:s', strtotime((string)$log->created))) ?></small>
              <?php else: ?>—<?php endif ?>
            </td>
            <td class="text-center">
              <?php if (!empty($log->snapshot)): ?>
                <button type="button" class="btn btn-xs btn-outline-secondary px-1 py-0 btn-snapshot"
                  data-id="<?= h($log->id) ?>"
                  data-number="<?= h((string)($log->fullnumber ?: $log->invoice_id)) ?>"
                  title="Podgląd snapshotu">
                  <i class="ri-code-line"></i>
                </button>
              <?php endif ?>
            </td>
          </tr>
          <?php endforeach ?>
          <?php if (iterator_to_array($logs->getIterator(), false) === []): ?>
          <tr>
            <td colspan="9" class="text-center text-muted py-4">
              <i class="ri-inbox-line fs-3 d-block mb-1 opacity-50"></i>Brak wpisów
            </td>
          </tr>
          <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Paginacja -->
  <?php
    $this->Paginator->setTemplates([
      'first'        => '<li class="page-item"><a class="page-link" href="{{url}}" title="Pierwsza" data-bs-toggle="tooltip"><i class="ri-skip-left-line"></i></a></li>',
      'prevActive'   => '<li class="page-item"><a class="page-link" href="{{url}}" title="Poprzednia" data-bs-toggle="tooltip"><i class="ri-arrow-left-line"></i></a></li>',
      'prevDisabled' => '<li class="page-item disabled"><a class="page-link" href="javascript:void(0);" tabindex="-1"><i class="ri-arrow-left-line"></i></a></li>',
      'number'       => '<li class="page-item"><a class="page-link" href="{{url}}">{{text}}</a></li>',
      'current'      => '<li class="page-item active" aria-current="page"><a class="page-link" href="javascript:void(0);">{{text}}</a></li>',
      'nextActive'   => '<li class="page-item"><a class="page-link" href="{{url}}" title="Następna" data-bs-toggle="tooltip"><i class="ri-arrow-right-line"></i></a></li>',
      'nextDisabled' => '<li class="page-item disabled"><a class="page-link" href="javascript:void(0);" tabindex="-1"><i class="ri-arrow-right-line"></i></a></li>',
      'last'         => '<li class="page-item"><a class="page-link" href="{{url}}" title="Ostatnia" data-bs-toggle="tooltip"><i class="ri-skip-right-line"></i></a></li>',
      'ellipsis'     => '<li class="page-item disabled"><span class="page-link">…</span></li>',
    ]);
    $__p = $this->Paginator->params();
    $__pageCount = (int)($__p['pageCount'] ?? 1);
    $__pageN     = (int)($__p['page'] ?? 1);
    $__currentN  = (int)($__p['current'] ?? 0);
    $__countN    = (int)($__p['count'] ?? 0);
  ?>
  <?php if ($__pageCount > 1): ?>
  <div class="card-footer">
    <div class="mx-auto">
      <nav aria-label="Nawigacja stron">
        <ul class="pagination justify-content-center">
          <?= $this->Paginator->first(__('Pierwsza')) ?>
          <?= $this->Paginator->prev(__('Poprzednia')) ?>
          <?= $this->Paginator->numbers() ?>
          <?= $this->Paginator->next(__('Następna')) ?>
          <?= $this->Paginator->last(__('Ostatnia')) ?>
        </ul>
      </nav>
    </div>
    <p class="text-muted small text-center mb-0">
      Strona <?= $__pageN ?> z <?= $__pageCount ?>, łącznie <?= $__countN ?> wpisów
    </p>
  </div>
  <?php else: ?>
  <div class="card-footer text-center text-muted small">
    <?= $__countN === 0 ? 'Brak wpisów' : ('Łącznie ' . $__countN . ' ' . ($__countN === 1 ? 'wpis' : ($__countN < 5 ? 'wpisy' : 'wpisów'))) ?>
  </div>
  <?php endif ?>
</div>

<!-- Modal: snapshot JSON -->
<div class="modal fade" id="snapshotModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="ri-code-line me-2"></i>Snapshot faktury
          <span class="text-muted fw-normal small ms-2" id="snapshot-number"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <pre id="snapshot-pre" class="m-0 p-3" style="font-size:.78rem;max-height:70vh;overflow:auto;background:#f8f9fa;border:none"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-copy-snapshot">
          <i class="ri-clipboard-line me-1"></i>Kopiuj JSON
        </button>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>

<?php
// Snapshoty jako mapa id → JSON (renderowane server-side, bez dodatkowego AJAX)
$snapshotMap = [];
foreach ($logs as $log) {
    if (!empty($log->snapshot)) {
        $snapshotMap[(string)$log->id] = $log->snapshot;
    }
}
?>
<script>
var __snapshots = <?= json_encode($snapshotMap, JSON_UNESCAPED_UNICODE) ?>;

document.body.addEventListener('click', function(e) {
  var btn = e.target.closest('.btn-snapshot');
  if (!btn) return;

  var id     = btn.dataset.id;
  var number = btn.dataset.number;
  var raw    = __snapshots[id] || '{}';

  var pretty;
  try { pretty = JSON.stringify(JSON.parse(raw), null, 2); }
  catch(ex) { pretty = raw; }

  document.getElementById('snapshot-number').textContent = number ? ('— ' + number) : '';
  document.getElementById('snapshot-pre').textContent = pretty;

  var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('snapshotModal'));
  modal.show();
});

document.getElementById('btn-copy-snapshot').addEventListener('click', function() {
  var text = document.getElementById('snapshot-pre').textContent;
  navigator.clipboard.writeText(text).then(function() {
    var btn = document.getElementById('btn-copy-snapshot');
    btn.innerHTML = '<i class="ri-check-line me-1"></i>Skopiowano';
    setTimeout(function() { btn.innerHTML = '<i class="ri-clipboard-line me-1"></i>Kopiuj JSON'; }, 2000);
  });
});
</script>

<?php
/**
 * Admin: lista szkiców faktur wszystkich użytkowników
 *
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $invoices
 */
$this->assign('title', 'Szkice faktur');
echo $this->Html->meta('csrfToken', $this->request->getAttribute('csrfToken'));

$q    = trim((string)$this->request->getQuery('q'));
$from = $this->request->getQuery('from');
$to   = $this->request->getQuery('to');
$type = $this->request->getQuery('type');

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
    <h1 class="page-title fw-medium fs-18 mb-2">Szkice faktur</h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item"><a href="<?= $this->Url->build(['action' => 'adminInvoices']) ?>">Admin — faktury</a></li>
      <li class="breadcrumb-item active">Szkice</li>
    </ol>
  </div>
</div>

<div class="card">
  <!-- Filtry -->
  <div class="card-header">
    <?= $this->Form->create(null, [
      'type'  => 'get',
      'url'   => ['action' => 'adminDrafts'],
      'class' => 'd-flex flex-wrap gap-2 align-items-end',
    ]) ?>
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Szukaj: firma, kontrahent…" value="<?= h($q) ?>" style="width:220px">
      <select name="type" class="form-select form-select-sm" style="width:130px">
        <option value="">Typ faktury</option>
        <?php foreach (['vat'=>'VAT','proforma'=>'Proforma','novat'=>'Bez VAT','correction'=>'Korekta','advance'=>'Zaliczkowa','final'=>'Końcowa','margin'=>'Marżowa','currency'=>'Walutowa','rental'=>'Najem'] as $val => $lbl): ?>
          <option value="<?= $val ?>"<?= $type === $val ? ' selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach ?>
      </select>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= h((string)$from) ?>" title="Utworzono od" style="width:140px">
      <input type="date" name="to"   class="form-control form-control-sm" value="<?= h((string)$to) ?>"   title="Utworzono do" style="width:140px">
      <div class="btn-group btn-group-sm">
        <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i>Filtruj</button>
        <?= $this->Html->link('<i class="ri-refresh-line"></i>', ['action' => 'adminDrafts'], ['escape' => false, 'class' => 'btn btn-light', 'title' => 'Wyczyść filtry']) ?>
      </div>
    <?= $this->Form->end() ?>
  </div>

  <!-- Tabela -->
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0 fs-13">
        <thead class="table-light">
          <tr>
            <th style="width:22%"><?= $this->Paginator->sort('Companies.name', 'Firma') ?></th>
            <th style="width:16%"><?= $this->Paginator->sort('Invoices.fullnumber', 'Numer / roboczy') ?></th>
            <th style="width:8%"><?= $this->Paginator->sort('Invoices.type', 'Typ') ?></th>
            <th style="width:22%"><?= $this->Paginator->sort('InvoiceContractors.name', 'Nabywca') ?></th>
            <th style="width:14%"><?= $this->Paginator->sort('Invoices.created', 'Utworzono') ?></th>
            <th style="width:10%" class="text-end">Kwota netto</th>
            <th style="width:8%" class="text-end">Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty(iterator_to_array($invoices, false))): ?>
            <?php $invoices->rewind(); ?>
          <?php endif ?>
          <?php foreach ($invoices as $inv): ?>
          <?php
            $company    = $inv->company ?? null;
            $contractor = $inv->invoice_contractor ?? null;
            $total      = number_format((float)($inv->total ?? 0), 2, ',', ' ') . ' ' . ($inv->currency ?? 'PLN');
          ?>
          <tr>
            <td class="text-truncate" style="max-width:0">
              <span class="fw-medium d-block text-truncate"><?= h((string)($company->name ?? '—')) ?></span>
              <?php if (!empty($company->nip)): ?>
                <small class="text-muted">NIP <?= h($company->nip) ?></small>
              <?php endif ?>
            </td>
            <td class="text-nowrap">
              <?php if (!empty($inv->fullnumber)): ?>
                <span class="fw-semibold"><?= h($inv->fullnumber) ?></span>
              <?php else: ?>
                <span class="text-muted fst-italic">szkic</span>
              <?php endif ?>
            </td>
            <td class="text-nowrap"><?= $typeLabel((string)($inv->type ?? '')) ?></td>
            <td class="text-truncate" style="max-width:0">
              <span class="d-block text-truncate"><?= h((string)($contractor->name ?? '—')) ?></span>
              <?php if (!empty($contractor->nip)): ?>
                <small class="text-muted"><?= h($contractor->nip) ?></small>
              <?php endif ?>
            </td>
            <td class="text-nowrap">
              <?php if (!empty($inv->created)): ?>
                <?= h(date('Y-m-d', strtotime((string)$inv->created))) ?>
                <br><small class="text-muted"><?= h(date('H:i', strtotime((string)$inv->created))) ?></small>
              <?php else: ?>
                —
              <?php endif ?>
            </td>
            <td class="text-end fw-medium text-nowrap"><?= $total ?></td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-xs btn-outline-danger btn-admin-delete px-1 py-0"
                data-id="<?= h($inv->id) ?>"
                data-number="<?= h((string)($inv->fullnumber ?: ('szkic #' . $inv->id))) ?>"
                title="Usuń szkic">
                <i class="ri-delete-bin-line"></i>
              </button>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Paginacja -->
  <?php
    $this->Paginator->setTemplates([
      'first'        => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Pierwsza" title="Pierwsza" data-bs-toggle="tooltip"><i class="ri-skip-left-line"></i></a></li>',
      'prevActive'   => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Poprzednia" title="Poprzednia" data-bs-toggle="tooltip"><i class="ri-arrow-left-line"></i></a></li>',
      'prevDisabled' => '<li class="page-item disabled"><a class="page-link disabled" href="javascript:void(0);" tabindex="-1" aria-disabled="true"><i class="ri-arrow-left-line"></i></a></li>',
      'number'       => '<li class="page-item"><a class="page-link" href="{{url}}">{{text}}</a></li>',
      'current'      => '<li class="page-item active" aria-current="page"><a class="page-link" href="javascript:void(0);">{{text}}</a></li>',
      'nextActive'   => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Następna" title="Następna" data-bs-toggle="tooltip"><i class="ri-arrow-right-line"></i></a></li>',
      'nextDisabled' => '<li class="page-item disabled"><a class="page-link disabled" href="javascript:void(0);" tabindex="-1" aria-disabled="true"><i class="ri-arrow-right-line"></i></a></li>',
      'last'         => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Ostatnia" title="Ostatnia" data-bs-toggle="tooltip"><i class="ri-skip-right-line"></i></a></li>',
      'ellipsis'     => '<li class="page-item disabled"><span class="page-link">…</span></li>',
    ]);
    $__pgParams  = $this->Paginator->params();
    $__pageCount = (int)($__pgParams['pageCount'] ?? 1);
    $__pageN     = (int)($__pgParams['page'] ?? 1);
    $__currentN  = (int)($__pgParams['current'] ?? 0);
    $__countN    = (int)($__pgParams['count'] ?? 0);
    $__word      = function($n){ $n=(int)$n; $n10=$n%10; $n100=$n%100; if($n===1) return 'szkic'; if($n10>=2&&$n10<=4&&($n100<12||$n100>14)) return 'szkice'; return 'szkiców'; };
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
    <div class="col-lg-12 text-center">
      <p class="text-muted small mb-0">
        Strona <?= $__pageN ?> z <?= $__pageCount ?>, wyświetlono <?= $__currentN ?> z <?= $__countN ?> <?= $__word($__countN) ?>
      </p>
    </div>
  </div>
  <?php else: ?>
  <div class="card-footer text-center text-muted small">
    <?= $__countN === 0 ? 'Brak szkiców' : ('Wyświetlono ' . $__countN . ' ' . $__word($__countN)) ?>
  </div>
  <?php endif ?>
</div>

<script>
document.querySelectorAll('.btn-admin-delete').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var id     = this.dataset.id;
    var number = this.dataset.number;
    var row    = this.closest('tr');

    Swal.fire({
      title: 'Usuń szkic',
      html:
        '<p class="mb-3">Usuwasz szkic <strong>' + number + '</strong>.<br>Ta operacja jest nieodwracalna.</p>' +
        '<label class="form-label fw-semibold">Kod administratora</label>' +
        '<input type="text" style="display:none" aria-hidden="true">' +
        '<input type="password" id="swal-admin-code" class="swal2-input" placeholder="Wpisz kod…" autocomplete="new-password">',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: '<i class="ri-delete-bin-line me-1"></i>Usuń',
      cancelButtonText: 'Anuluj',
      confirmButtonColor: '#dc3545',
      focusConfirm: false,
      preConfirm: function() {
        var code = document.getElementById('swal-admin-code').value;
        if (!code) { Swal.showValidationMessage('Wpisz kod administratora.'); return false; }
        return code;
      }
    }).then(function(result) {
      if (!result.isConfirmed) return;

      var csrfToken = document.querySelector('meta[name="csrfToken"]')?.content || '';
      var deleteUrl = '<?= $this->Url->build(['controller' => 'Invoices', 'action' => 'adminDelete', '_base' => false]) ?>/' + id + '/delete';

      Swal.fire({ title: 'Usuwanie…', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

      fetch(deleteUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken },
        body: 'admin_code=' + encodeURIComponent(result.value),
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          Swal.fire({ icon: 'success', title: 'Usunięto', text: data.message, timer: 1800, showConfirmButton: false });
          if (row) row.remove();
        } else {
          Swal.fire({ icon: 'error', title: 'Błąd', text: data.message });
        }
      })
      .catch(function() {
        Swal.fire({ icon: 'error', title: 'Błąd', text: 'Nie udało się połączyć z serwerem.' });
      });
    });
  });
});
</script>

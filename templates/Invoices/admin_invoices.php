<?php
/**
 * Admin: lista faktur wszystkich użytkowników
 *
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $invoices
 */
$this->assign('title', 'Faktury użytkowników');
echo $this->Html->meta('csrfToken', $this->request->getAttribute('csrfToken'));

$q     = trim((string)$this->request->getQuery('q'));
$from  = $this->request->getQuery('from');
$to    = $this->request->getQuery('to');
$type  = $this->request->getQuery('type');
$state = $this->request->getQuery('state');

$badge = function(string $state): string {
    return match($state) {
        'paid'    => '<span class="badge bg-success">Opłacona</span>',
        'unpaid'  => '<span class="badge bg-danger">Nieopłacona</span>',
        'partial' => '<span class="badge bg-warning text-dark">Częściowa</span>',
        'overdue' => '<span class="badge bg-dark">Przeterminowana</span>',
        default   => '<span class="badge bg-secondary">' . h($state) . '</span>',
    };
};

$typeLabel = function(string $type): string {
    return match($type) {
        'vat'         => 'VAT',
        'proforma'    => 'Proforma',
        'credit_note' => 'Nota uznaniowa',
        'novat'       => 'Bez VAT',
        'correction'  => 'Korekta',
        'advance'     => 'Zaliczkowa',
        'final'       => 'Końcowa',
        'margin'      => 'Marżowa',
        'currency'    => 'Walutowa',
        'rental'      => 'Najem',
        default       => h($type),
    };
};
?>

<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Faktury użytkowników</h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item active">Admin — faktury</li>
    </ol>
  </div>
</div>

<div class="card">
  <!-- Filtry -->
  <div class="card-header">
    <?= $this->Form->create(null, [
      'type' => 'get',
      'url'  => ['action' => 'adminInvoices'],
      'class' => 'd-flex flex-wrap gap-2 align-items-end',
    ]) ?>
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Szukaj: firma, numer, kontrahent…" value="<?= h($q) ?>" style="width:220px">
      <select name="type" class="form-select form-select-sm" style="width:130px">
        <option value="">Typ faktury</option>
        <?php foreach (['vat'=>'VAT','proforma'=>'Proforma','novat'=>'Bez VAT','correction'=>'Korekta','advance'=>'Zaliczkowa','final'=>'Końcowa','margin'=>'Marżowa','currency'=>'Walutowa','rental'=>'Najem'] as $val => $lbl): ?>
          <option value="<?= $val ?>"<?= $type === $val ? ' selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach ?>
      </select>
      <select name="state" class="form-select form-select-sm" style="width:150px">
        <option value="">Status płatności</option>
        <option value="unpaid"<?= $state === 'unpaid' ? ' selected' : '' ?>>Nieopłacona</option>
        <option value="partial"<?= $state === 'partial' ? ' selected' : '' ?>>Częściowa</option>
        <option value="paid"<?= $state === 'paid' ? ' selected' : '' ?>>Opłacona</option>
        <option value="overdue"<?= $state === 'overdue' ? ' selected' : '' ?>>Przeterminowana</option>
      </select>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= h((string)$from) ?>" title="Data od" style="width:140px">
      <input type="date" name="to"   class="form-control form-control-sm" value="<?= h((string)$to) ?>"   title="Data do"  style="width:140px">
      <div class="btn-group btn-group-sm">
        <button type="submit" class="btn btn-primary"><i class="ri-search-line me-1"></i>Filtruj</button>
        <?= $this->Html->link('<i class="ri-refresh-line"></i>', ['action' => 'adminInvoices'], ['escape' => false, 'class' => 'btn btn-light', 'title' => 'Wyczyść filtry']) ?>
      </div>
    <?= $this->Form->end() ?>
  </div>

  <!-- Tabela -->
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0 fs-13">
        <thead class="table-light">
          <tr>
            <th style="width:18%"><?= $this->Paginator->sort('Companies.name', 'Firma') ?></th>
            <th style="width:14%"><?= $this->Paginator->sort('Invoices.fullnumber', 'Numer') ?></th>
            <th style="width:7%"><?= $this->Paginator->sort('Invoices.type', 'Typ') ?></th>
            <th style="width:18%"><?= $this->Paginator->sort('InvoiceContractors.name', 'Nabywca') ?></th>
            <th style="width:8%"><?= $this->Paginator->sort('Invoices.date', 'Data') ?> / <?= $this->Paginator->sort('Invoices.created', 'Utw.') ?></th>
            <th style="width:10%" class="text-end"><?= $this->Paginator->sort('Invoices.total', 'Kwota') ?></th>
            <th style="width:9%"><?= $this->Paginator->sort('Invoices.paymentstate', 'Status') ?></th>
            <th style="width:8%">KSeF</th>
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
            <td class="fw-semibold text-nowrap"><?= h((string)($inv->fullnumber ?? '—')) ?></td>
            <td class="text-nowrap"><?= $typeLabel((string)($inv->type ?? '')) ?></td>
            <td class="text-truncate" style="max-width:0">
              <span class="d-block text-truncate"><?= h((string)($contractor->name ?? '—')) ?></span>
              <?php if (!empty($contractor->nip)): ?>
                <small class="text-muted"><?= h($contractor->nip) ?></small>
              <?php endif ?>
            </td>
            <td class="text-nowrap">
              <?= h((string)($inv->date ?? '')) ?>
              <?php if (!empty($inv->created)): ?>
                <br><small class="text-muted"><?= h(date('Y-m-d H:i', strtotime((string)$inv->created))) ?></small>
              <?php endif ?>
            </td>
            <td class="text-end fw-medium text-nowrap"><?= $total ?></td>
            <td><?= $badge((string)($inv->paymentstate ?? '')) ?></td>
            <td>
              <?php
                $ks  = trim((string)($inv->ksef_status ?? ''));
                $ksMap = [
                  'accepted' => ['bg-success',  'Przyjęta'],
                  'approved' => ['bg-success',  'Przyjęta'],
                  'sent'     => ['bg-info',      'Wysłana'],
                  'queued'   => ['bg-secondary', 'Kolejka'],
                  'rejected' => ['bg-danger',    'Odrzucona'],
                  'error'    => ['bg-danger',    'Błąd'],
                ];
                if ($ks === '') {
                  echo '<span class="badge bg-light text-muted border">—</span>';
                } elseif (preg_match('/^\d{3}$/', $ks)) {
                  echo (int)$ks === 200
                    ? '<span class="badge bg-success">OK</span>'
                    : '<span class="badge bg-danger">' . h($ks) . '</span>';
                } elseif (isset($ksMap[strtolower($ks)])) {
                  [$cls, $lbl] = $ksMap[strtolower($ks)];
                  echo '<span class="badge ' . $cls . '">' . $lbl . '</span>';
                } else {
                  echo '<span class="badge bg-light text-dark">' . h($ks) . '</span>';
                }
              ?>
            </td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-xs btn-outline-secondary inv-ksef-log-btn px-1 py-0"
                data-id="<?= h($inv->id) ?>"
                data-number="<?= h((string)($inv->fullnumber ?? $inv->id)) ?>"
                title="Historia KSeF">
                <i class="ri-history-line"></i>
              </button>
              <button type="button" class="btn btn-xs btn-outline-danger btn-admin-delete px-1 py-0"
                data-id="<?= h($inv->id) ?>"
                data-number="<?= h((string)($inv->fullnumber ?? $inv->id)) ?>"
                title="Usuń fakturę">
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
      'prevDisabled' => '<li class="page-item disabled"><a class="page-link disabled" href="javascript:void(0);" tabindex="-1" aria-disabled="true" aria-label="Poprzednia" title="Poprzednia" data-bs-toggle="tooltip"><i class="ri-arrow-left-line"></i></a></li>',
      'number'       => '<li class="page-item"><a class="page-link" href="{{url}}">{{text}}</a></li>',
      'current'      => '<li class="page-item active" aria-current="page"><a class="page-link" href="javascript:void(0);">{{text}}</a></li>',
      'nextActive'   => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Następna" title="Następna" data-bs-toggle="tooltip"><i class="ri-arrow-right-line"></i></a></li>',
      'nextDisabled' => '<li class="page-item disabled"><a class="page-link disabled" href="javascript:void(0);" tabindex="-1" aria-disabled="true" aria-label="Następna" title="Następna" data-bs-toggle="tooltip"><i class="ri-arrow-right-line"></i></a></li>',
      'last'         => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Ostatnia" title="Ostatnia" data-bs-toggle="tooltip"><i class="ri-skip-right-line"></i></a></li>',
      'ellipsis'     => '<li class="page-item disabled"><span class="page-link">…</span></li>',
    ]);
    $__pgParams   = $this->Paginator->params();
    $__pageCount  = (int)($__pgParams['pageCount'] ?? 1);
    $__pageN      = (int)($__pgParams['page'] ?? 1);
    $__currentN   = (int)($__pgParams['current'] ?? 0);
    $__countN     = (int)($__pgParams['count'] ?? 0);
    $__accWord    = function($n){ $n=(int)$n; $n10=$n%10; $n100=$n%100; if($n===1) return 'fakturę'; if($n10>=2&&$n10<=4&&($n100<12||$n100>14)) return 'faktury'; return 'faktur'; };
    $__genWord    = function($n){ return ((int)$n===1)?'faktury':'faktur'; };
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
        Strona <?= $__pageN ?> z <?= $__pageCount ?>, wyświetlono <?= $__currentN ?> <?= $__accWord($__currentN) ?> z <?= $__countN ?> <?= $__genWord($__countN) ?>
      </p>
    </div>
  </div>
  <?php else: ?>
  <div class="card-footer text-center text-muted small">
    <?php if ($__countN === 0): ?>
      Brak faktur
    <?php else: ?>
      Wyświetlono <?= $__countN ?> <?= $__accWord($__countN) ?>
    <?php endif ?>
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
      title: 'Usuń fakturę',
      html:
        '<p class="mb-3">Usuwasz fakturę <strong>' + number + '</strong>.<br>Ta operacja jest nieodwracalna.</p>' +
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
        if (!code) {
          Swal.showValidationMessage('Wpisz kod administratora.');
          return false;
        }
        return code;
      }
    }).then(function(result) {
      if (!result.isConfirmed) return;

      var code     = result.value;
      var csrfToken = document.querySelector('meta[name="csrfToken"]')?.content || '';
      var deleteUrl = '<?= $this->Url->build(['controller' => 'Invoices', 'action' => 'adminDelete', '_base' => false]) ?>/' + id + '/delete';

      Swal.fire({ title: 'Usuwanie…', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

      fetch(deleteUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': csrfToken,
        },
        body: 'admin_code=' + encodeURIComponent(code),
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

<!-- Modal: Historia KSeF -->
<div class="modal fade" id="ksefLogsModal" tabindex="-1" aria-labelledby="ksefLogsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ksefLogsModalLabel">
          <i class="ri-history-line me-2"></i>Historia KSeF
          <span class="text-muted fw-normal small ms-2" id="ksef-log-invoice-number"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="ksef-log-status-bar" class="alert alert-light py-2 px-3 mb-3 d-none">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>
              <span class="text-muted small">Status KSeF:</span>
              <span id="ksef-log-status-badge" class="badge ms-1"></span>
            </div>
            <div id="ksef-log-number-wrap" class="d-none">
              <span class="text-muted small">Numer KSeF:</span>
              <code id="ksef-log-ksef-number" class="ms-1 small user-select-all"></code>
            </div>
          </div>
        </div>
        <div id="ksef-log-spinner" class="text-center py-4">
          <div class="spinner-border text-primary" role="status"></div>
          <div class="text-muted small mt-2">Ładowanie historii…</div>
        </div>
        <div id="ksef-log-empty" class="text-center text-muted py-4 d-none">
          <i class="ri-inbox-line fs-1 d-block mb-2 opacity-50"></i>
          Brak wpisów w historii KSeF dla tej faktury.
        </div>
        <div id="ksef-log-timeline" class="d-none">
          <ul class="list-unstyled mb-0" id="ksef-log-list"></ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>

<style>
#ksef-log-list li { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #f0f0f0; }
#ksef-log-list li:last-child { border-bottom:none; }
#ksef-log-list .kl-icon { flex-shrink:0; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; }
#ksef-log-list .kl-body { flex:1; min-width:0; }
#ksef-log-list .kl-label { font-weight:600; font-size:.875rem; }
#ksef-log-list .kl-time  { font-size:.75rem; color:#888; white-space:nowrap; }
#ksef-log-list .kl-msg   { font-size:.8rem; color:#555; margin-top:2px; word-break:break-word; }
#ksef-log-list .kl-meta  { font-size:.75rem; color:#999; margin-top:2px; }
</style>

<script>
(function () {
  var ksefLogModal = document.getElementById('ksefLogsModal');
  var bsModal = null;

  var colorMap = {
    success:   { bg:'#d1fae5', color:'#065f46' },
    danger:    { bg:'#fee2e2', color:'#991b1b' },
    warning:   { bg:'#fef3c7', color:'#92400e' },
    info:      { bg:'#dbeafe', color:'#1e40af' },
    secondary: { bg:'#f1f5f9', color:'#475569' },
  };
  var statusLabels = {
    sent:    { text:'Wysłano',     cls:'bg-success' },
    error:   { text:'Błąd',        cls:'bg-danger' },
    pending: { text:'Oczekuje',    cls:'bg-warning text-dark' },
    blocked: { text:'Zablokowano', cls:'bg-secondary' },
  };

  function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  document.body.addEventListener('click', function (e) {
    var btn = e.target.closest('.inv-ksef-log-btn');
    if (!btn) return;
    e.preventDefault();

    var id     = btn.dataset.id;
    var number = btn.dataset.number;

    document.getElementById('ksef-log-invoice-number').textContent = number ? ('— ' + number) : '';
    document.getElementById('ksef-log-status-bar').classList.add('d-none');
    document.getElementById('ksef-log-spinner').classList.remove('d-none');
    document.getElementById('ksef-log-empty').classList.add('d-none');
    document.getElementById('ksef-log-timeline').classList.add('d-none');
    document.getElementById('ksef-log-list').innerHTML = '';
    document.getElementById('ksef-log-number-wrap').classList.add('d-none');

    if (!bsModal) bsModal = new bootstrap.Modal(ksefLogModal);
    bsModal.show();

    fetch('/invoices/ksef-send-logs/' + id + '.json', {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      document.getElementById('ksef-log-spinner').classList.add('d-none');
      if (!data.success) { document.getElementById('ksef-log-empty').classList.remove('d-none'); return; }

      var sm = statusLabels[data.ksef_status] || { text: data.ksef_status || 'Brak', cls: 'bg-light text-muted border' };
      var badge = document.getElementById('ksef-log-status-badge');
      badge.textContent = sm.text;
      badge.className = 'badge ms-1 ' + sm.cls;
      document.getElementById('ksef-log-status-bar').classList.remove('d-none');
      if (data.ksef_number) {
        document.getElementById('ksef-log-ksef-number').textContent = data.ksef_number;
        document.getElementById('ksef-log-number-wrap').classList.remove('d-none');
      }

      if (!data.logs || !data.logs.length) { document.getElementById('ksef-log-empty').classList.remove('d-none'); return; }

      var ul = document.getElementById('ksef-log-list');
      data.logs.forEach(function (log) {
        var p = colorMap[log.color] || colorMap.secondary;
        var meta = [];
        if (log.env)         meta.push('Środowisko: <strong>' + escHtml(log.env) + '</strong>');
        if (log.status_code) meta.push('Kod: <strong>' + escHtml(log.status_code) + '</strong>');
        if (log.ksef_number) meta.push('Nr KSeF: <strong>' + escHtml(log.ksef_number) + '</strong>');

        var li = document.createElement('li');
        li.innerHTML =
          '<div class="kl-icon" style="background:' + p.bg + ';color:' + p.color + '">'
            + '<i class="' + escHtml(log.icon) + '"></i></div>'
          + '<div class="kl-body">'
            + '<div class="d-flex align-items-baseline gap-2 flex-wrap">'
              + '<span class="kl-label" style="color:' + p.color + '">' + escHtml(log.label) + '</span>'
              + '<span class="kl-time">' + escHtml(log.created) + '</span>'
            + '</div>'
            + (log.message ? '<div class="kl-msg">' + escHtml(log.message) + '</div>' : '')
            + (meta.length  ? '<div class="kl-meta">' + meta.join(' &nbsp;·&nbsp; ') + '</div>' : '')
          + '</div>';
        ul.appendChild(li);
      });
      document.getElementById('ksef-log-timeline').classList.remove('d-none');
    })
    .catch(function () {
      document.getElementById('ksef-log-spinner').classList.add('d-none');
      document.getElementById('ksef-log-empty').classList.remove('d-none');
    });
  });
})();
</script>

<?php
/**
 * @var \App\View\AppView $this
 * @var array $invoices
 * @var array $stats
 */
$this->assign('title', 'Faktury wystawione (KSeF)');
$env = (string)$this->getRequest()->getQuery('env', 'test');
?>

<?= $this->element('Ksef/filters', [
  'currentAction' => 'issued',
  'peerAction' => 'received',
  'storageKey' => 'ksef_filters_issued',
]) ?>

<?= $this->element('Ksef/info') ?>
<?= $this->element('Ksef/legend') ?>

<div class="table-responsive">
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>Data</th>
        <th>Numer</th>
        <th>Nabywca</th>
        <th>NIP</th>
    <th class="text-end">Kwota brutto</th>
    <th>Waluta</th>
    <th>Status</th>
    <th>Plik</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($invoices)): ?>
        <?php foreach ($invoices as $row): ?>
          <tr>
            <td><?= $row['date']?->i18nFormat('yyyy-MM-dd') ?? '' ?></td>
            <td>
              <div><?= h($row['fullnumber'] ?? '') ?></div>
              <?php if (!empty($row['fullnumber'])): ?>
                <div class="small mt-1">
                  <button type="button" class="btn btn-xs btn-outline-secondary copy-inv" data-inv="<?= h($row['fullnumber']) ?>" title="Kopiuj numer faktury">Kopiuj nr</button>
                </div>
              <?php endif; ?>
              <?php if (!empty($row['ksef_number'])): ?>
                <div class="small text-muted mt-1 d-flex align-items-center gap-2">
                  <span class="badge bg-secondary-subtle border text-secondary">KSeF: <?= h($row['ksef_number']) ?></span>
                  <button type="button" class="btn btn-xs btn-outline-secondary copy-ksef" data-ksef="<?= h($row['ksef_number']) ?>" title="Kopiuj numer KSeF">Kopiuj</button>
                </div>
              <?php endif; ?>
            </td>
            <td><?= h($row['InvoiceContractors']['name'] ?? '') ?></td>
            <td><?= h($row['InvoiceContractors']['tax_id'] ?? '') ?></td>
            <td class="text-end"><?= number_format((float)($row['total'] ?? 0), 2, ',', ' ') ?></td>
            <td><?= h($row['currency'] ?? 'PLN') ?></td>
            <td>
              <div class="d-flex flex-wrap gap-1">
                <?php if (!empty($row['invoiceType'])): ?>
                  <span class="badge bg-secondary-subtle border text-secondary"><?= h($row['invoiceType']) ?></span>
                <?php endif; ?>
                <?php if (!empty($row['invoicingMode'])): ?>
                  <span class="badge bg-secondary-subtle border text-secondary"><?= h($row['invoicingMode']) ?></span>
                <?php endif; ?>
                <?php if (!empty($row['isSelfInvoicing'])): ?>
                  <span class="badge bg-info-subtle border text-info">Self-billing</span>
                <?php endif; ?>
                <?php if (!empty($row['hasAttachment'])): ?>
                  <span class="badge bg-secondary-subtle border text-secondary" title="Załączniki">📎</span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <?php if (!empty($row['ksef_number'])): ?>
                <div class="btn-group btn-group-sm" role="group">
                  <button type="button" class="btn btn-outline-secondary preview-xml" data-ksef="<?= h($row['ksef_number']) ?>">Podgląd</button>
                  <a class="btn btn-outline-primary" href="<?= $this->Url->build(['action' => 'download', $row['ksef_number'], '?' => ['env' => $env]]) ?>">Pobierz XML</a>
                </div>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="8" class="text-center text-muted py-4">Brak wyników dla wybranych filtrów.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$page = max(1, (int)$this->getRequest()->getQuery('page', 1));
$prevUrl = $this->Url->build(['action' => 'issued'] + array_merge($this->getRequest()->getQueryParams(), ['page' => max(1, $page - 1)]));
$nextUrl = $this->Url->build(['action' => 'issued'] + array_merge($this->getRequest()->getQueryParams(), ['page' => $page + 1]));
?>
<div class="d-flex justify-content-between my-3">
  <a class="btn btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= $page <= 1 ? '#' : $prevUrl ?>">&laquo; Poprzednia</a>
  <span class="text-muted">Strona <?= (int)$page ?></span>
  <a class="btn btn-outline-secondary" href="<?= $nextUrl ?>">Następna &raquo;</a>
</div>

<?= $this->element('Ksef/xml_modal', ['env' => $env]) ?>

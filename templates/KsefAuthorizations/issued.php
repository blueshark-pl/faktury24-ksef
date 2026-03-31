<?php
/**
 * @var \App\View\AppView $this
 * @var array $invoices
 * @var array $stats
 */
$this->assign('title', 'Faktury wystawione (KSeF)');
$env = (string)$this->getRequest()->getQuery('env', 'prod');
?>

<?= $this->element('Ksef/filters', [
  'currentAction' => 'issued',
  'peerAction' => 'received',
  'storageKey' => 'ksef_filters_issued',
]) ?>

<?= $this->element('Ksef/info') ?>
<?= $this->element('Ksef/legend') ?>

<!-- INFO: połączenie z KSeF jest konfigurowane certyfikatem/tokenem w ustawieniach integracji. -->

<?php if (!empty($ksefTraceEnabled)): ?>
  <?php
    $diag = is_array($ksefDiag ?? null) ? $ksefDiag : [];
    $trace = is_array($ksefTrace ?? null) ? $ksefTrace : [];
  ?>
  <details class="mb-3" open>
    <summary class="fw-semibold">Debug KSeF (krok po kroku)</summary>
    <div class="small text-muted mt-2">
      <?php if (!empty($diag)): ?>
        <div><strong>Env:</strong> <?= h((string)($diag['environment'] ?? '')) ?></div>
        <div><strong>API URL:</strong> <?= h((string)($diag['apiUrl'] ?? '')) ?></div>
        <div><strong>Auth:</strong> <?= h((string)($diag['authMethod'] ?? '')) ?>, <strong>NIP:</strong> <?= h((string)($diag['identifierNip'] ?? '')) ?> (<?= h((string)($diag['identifierSource'] ?? '')) ?>)</div>
        <?php if (!empty($diag['certPresent'])): ?>
          <div>
            <strong>Cert:</strong> <?= h((string)($diag['certFile'] ?? '')) ?>
            (source: <?= h((string)($diag['certSource'] ?? '')) ?>,
            companyId: <?= h((string)($diag['certCompanyId'] ?? '')) ?>,
            readable: <?= ($diag['certReadable'] ?? null) === true ? 'yes' : (($diag['certReadable'] ?? null) === false ? 'no' : 'n/a') ?>,
            used: <?= !empty($diag['certUsed']) ? 'yes' : 'no' ?>)
            <?php if (!empty($diag['certOriginalFile']) && (string)$diag['certOriginalFile'] !== (string)($diag['certFile'] ?? '')): ?>
              <div class="text-muted">original: <?= h((string)$diag['certOriginalFile']) ?></div>
            <?php endif; ?>
            <?php if (!empty($diag['certOpenSslErrors']) && is_array($diag['certOpenSslErrors'])): ?>
              <details class="mt-1">
                <summary>OpenSSL errors</summary>
                <pre class="mb-0" style="white-space: pre-wrap;"><?= h(implode("\n", array_map('strval', $diag['certOpenSslErrors']))) ?></pre>
              </details>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if (empty($trace)): ?>
      <div class="alert alert-secondary mt-2 mb-0">Brak trace (nie wykonano requestów HTTP albo trace wyłączony).</div>
    <?php else: ?>
      <div class="table-responsive mt-2">
        <table class="table table-sm table-striped align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 140px;">Czas</th>
              <th style="width: 90px;">Metoda</th>
              <th>URL</th>
              <th style="width: 90px;">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($trace as $step): ?>
              <?php
                $status = $step['status'] ?? null;
                $isError = isset($step['exceptionClass']) || (is_int($status) && $status >= 400);
              ?>
              <tr class="<?= $isError ? 'table-danger' : '' ?>">
                <td><?= h((string)($step['ts'] ?? '')) ?></td>
                <td><?= h((string)($step['method'] ?? '')) ?></td>
                <td>
                  <div class="text-break"><?= h((string)($step['uri'] ?? '')) ?></div>
                  <?php if (!empty($step['contentType'])): ?>
                    <div class="text-muted">CT: <?= h((string)$step['contentType']) ?></div>
                  <?php endif; ?>
                  <?php if (isset($step['exceptionClass'])): ?>
                    <div class="text-danger"><strong><?= h((string)$step['exceptionClass']) ?>:</strong> <?= h((string)($step['exceptionMessage'] ?? '')) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($step['requestBody'])): ?>
                    <details class="mt-1">
                      <summary>Request body</summary>
                      <pre class="mb-0" style="white-space: pre-wrap;"><?= h((string)$step['requestBody']) ?></pre>
                    </details>
                  <?php endif; ?>
                  <?php if (!empty($step['responseBody'])): ?>
                    <details class="mt-1">
                      <summary>Response body</summary>
                      <pre class="mb-0" style="white-space: pre-wrap;"><?= h((string)$step['responseBody']) ?></pre>
                    </details>
                  <?php endif; ?>
                </td>
                <td><?= $status !== null ? h((string)$status) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="small text-muted mt-2">Wskazówka: aby włączyć trace dodaj parametr <code>ksef_trace=1</code> do URL. Tokeny w logu są automatycznie redaktowane.</div>
    <?php endif; ?>
  </details>
<?php endif; ?>

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

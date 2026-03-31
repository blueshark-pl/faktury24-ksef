<?php
/**
 * @var \App\View\AppView $this
 * @var array $items
 */
$this->assign('title', 'Uprawnienia (KSeF) – personal grants');
$env = (string)$this->getRequest()->getQuery('env', 'prod');
$page = max(1, (int)($page ?? $this->getRequest()->getQuery('page', 1)));
$limit = (int)($limit ?? $this->getRequest()->getQuery('limit', 10));
$hasMore = (bool)($hasMore ?? false);
$asNip = (string)($asNip ?? $this->getRequest()->getQuery('as_nip', ''));

$permissionTypesValue = $this->getRequest()->getQuery('permission_types');
if (is_array($permissionTypesValue)) {
  $permissionTypesValue = implode(',', array_values(array_filter($permissionTypesValue, fn($v) => is_string($v) && $v !== '')));
}
$permissionTypesValue = (string)($permissionTypesValue ?? '');
?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-sm btn-outline-secondary" href="<?= $this->Url->build(['action' => 'received', '?' => ['env' => $env]]) ?>">Faktury otrzymane</a>
  <a class="btn btn-sm btn-outline-secondary" href="<?= $this->Url->build(['action' => 'issued', '?' => ['env' => $env]]) ?>">Faktury wystawione</a>
  <a class="btn btn-sm btn-primary" href="<?= $this->Url->build(['action' => 'personalGrants', '?' => $this->getRequest()->getQueryParams()]) ?>">Personal grants</a>
  <a class="btn btn-sm btn-outline-primary" href="<?= $this->Url->build(['action' => 'authorizationsGrants', '?' => ['env' => $env, 'as_nip' => $asNip]]) ?>">Authorizations grants</a>
</div>

<form method="get" class="card card-body mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-2">
      <label class="form-label">Env</label>
      <select name="env" class="form-select">
        <option value="test" <?= $env === 'test' ? 'selected' : '' ?>>test</option>
        <option value="prod" <?= $env === 'prod' ? 'selected' : '' ?>>prod</option>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label">As NIP (opcjonalnie)</label>
      <input name="as_nip" class="form-control" value="<?= h($asNip) ?>" placeholder="np. 1234567890" />
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Page</label>
      <input name="page" type="number" min="1" class="form-control" value="<?= (int)$page ?>" />
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Limit</label>
      <input name="limit" type="number" min="1" max="100" class="form-control" value="<?= (int)$limit ?>" />
    </div>

    <div class="col-12 col-md-3">
      <label class="form-label">Context NIP</label>
      <input name="context_nip" class="form-control" value="<?= h((string)$this->getRequest()->getQuery('context_nip', '')) ?>" />
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Target type</label>
      <select name="target_type" class="form-select">
        <?php $tt = (string)$this->getRequest()->getQuery('target_type', ''); ?>
        <option value="" <?= $tt === '' ? 'selected' : '' ?>>—</option>
        <option value="Nip" <?= $tt === 'Nip' ? 'selected' : '' ?>>Nip</option>
        <option value="InternalId" <?= $tt === 'InternalId' ? 'selected' : '' ?>>InternalId</option>
        <option value="AllPartners" <?= $tt === 'AllPartners' ? 'selected' : '' ?>>AllPartners</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Target value</label>
      <input name="target_value" class="form-control" value="<?= h((string)$this->getRequest()->getQuery('target_value', '')) ?>" />
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Permission state</label>
      <input name="permission_state" class="form-control" value="<?= h((string)$this->getRequest()->getQuery('permission_state', '')) ?>" placeholder="np. Active" />
    </div>
    <div class="col-12 col-md-5">
      <label class="form-label">Permission types (CSV)</label>
      <input name="permission_types" class="form-control" value="<?= h($permissionTypesValue) ?>" placeholder="InvoiceRead,InvoiceWrite" />
    </div>

    <div class="col-12 col-md-2">
      <button class="btn btn-primary w-100" type="submit">Szukaj</button>
    </div>
  </div>

  <div class="form-text mt-2">Włącz trace: dodaj <code>ksef_trace=1</code> do URL.</div>
</form>

<?= $this->element('Ksef/info') ?>

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
          <div><strong>Cert:</strong> <?= h((string)($diag['certFile'] ?? '')) ?> (source: <?= h((string)($diag['certSource'] ?? '')) ?>)</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if (empty($trace)): ?>
      <div class="alert alert-secondary mt-2 mb-0">Brak trace.</div>
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
                  <?php if (isset($step['exceptionClass'])): ?>
                    <div class="text-danger"><strong><?= h((string)$step['exceptionClass']) ?>:</strong> <?= h((string)($step['exceptionMessage'] ?? '')) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= $status !== null ? h((string)$status) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </details>
<?php endif; ?>

<div class="table-responsive">
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th style="width: 220px;">Id</th>
        <th>Scope/Type</th>
        <th>State</th>
        <th>Context</th>
        <th>Target</th>
        <th>Opis</th>
        <th style="width: 140px;">Raw</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($items)): ?>
        <?php foreach ($items as $row): ?>
          <?php
            $id = (string)($row['id'] ?? $row['permissionId'] ?? $row['operationId'] ?? '');
            $scope = (string)($row['permissionScope'] ?? $row['permissionType'] ?? $row['permission'] ?? '');
            $state = (string)($row['permissionState'] ?? $row['state'] ?? '');
            $desc = (string)($row['description'] ?? '');
            $ctx = $row['contextIdentifier'] ?? null;
            $tgt = $row['targetIdentifier'] ?? null;
          ?>
          <tr>
            <td class="text-break"><?= h($id) ?></td>
            <td><?= h($scope) ?></td>
            <td><?= h($state) ?></td>
            <td class="text-break"><?php if (is_array($ctx)) { echo h((string)($ctx['type'] ?? '')) . ': ' . h((string)($ctx['value'] ?? '')); } ?></td>
            <td class="text-break"><?php if (is_array($tgt)) { echo h((string)($tgt['type'] ?? '')) . ': ' . h((string)($tgt['value'] ?? '')); } ?></td>
            <td class="text-break"><?= h($desc) ?></td>
            <td>
              <details>
                <summary>JSON</summary>
                <pre class="mb-0" style="white-space: pre-wrap;"><?= h(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '') ?></pre>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="text-center text-muted py-4">Brak wyników.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
$qs = $this->getRequest()->getQueryParams();
$prevUrl = $this->Url->build(['action' => 'personalGrants', '?' => array_merge($qs, ['page' => max(1, $page - 1)])]);
$nextUrl = $this->Url->build(['action' => 'personalGrants', '?' => array_merge($qs, ['page' => $page + 1])]);
?>
<div class="d-flex justify-content-between my-3">
  <a class="btn btn-outline-secondary<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= $page <= 1 ? '#' : $prevUrl ?>">&laquo; Poprzednia</a>
  <span class="text-muted">Strona <?= (int)$page ?><?= $hasMore ? ' (hasMore)' : '' ?></span>
  <a class="btn btn-outline-secondary<?= !$hasMore ? ' disabled' : '' ?>" href="<?= !$hasMore ? '#' : $nextUrl ?>">Następna &raquo;</a>
</div>

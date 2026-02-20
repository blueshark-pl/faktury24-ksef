<?php
/** @var \App\View\AppView $this */
/** @var iterable<\App\Model\Entity\Invoice> $drafts */

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
            <th>Planowana wysyłka</th>
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
              $planned = $inv->planned_ksef_send_at;
              $plannedValue = '';
              if ($planned) {
                  $plannedValue = is_object($planned) && method_exists($planned, 'format')
                      ? $planned->format('Y-m-d')
                      : (string)$planned;
              }
            ?>
            <tr>
              <td><span class="badge bg-warning">Robocza</span></td>
              <td>
                <?= $this->Html->link(h($inv->fullnumber ?: ('ROB-' . substr((string)$inv->id, 0, 8))), ['action' => 'view', $inv->id], ['class' => 'fw-semibold']) ?>
              </td>
              <td>
                <span class="badge bg-secondary"><?= h($typeLabels[$typeKey] ?? strtoupper($typeKey)) ?></span>
              </td>
              <td>
                <?= h($contractor->name ?? '—') ?>
              </td>
              <td><?= $inv->date?->format('d.m.Y') ?></td>
              <td>
                <?= $this->Form->create(null, ['url' => ['action' => 'scheduleDraft', $inv->id], 'class' => 'd-flex gap-2 align-items-center']) ?>
                  <input type="date" name="planned_ksef_send_at" class="form-control form-control-sm" value="<?= h($plannedValue) ?>" style="max-width: 170px;">
                  <button type="submit" class="btn btn-sm btn-outline-primary">Zaplanuj</button>
                <?= $this->Form->end() ?>
              </td>
              <td class="text-end"><strong><?= h($money($inv->total ?? 0, (string)($inv->currency ?? 'PLN'))) ?></strong></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group">
                  <?= $this->Html->link('Edytuj', ['action' => $editAction, $inv->id], ['class' => 'btn btn-outline-secondary']) ?>
                  <?= $this->Form->postLink('Wyślij teraz', ['action' => 'sendDraftNow', $inv->id], ['class' => 'btn btn-outline-success', 'confirm' => 'Wysłać tę fakturę roboczą do KSeF teraz?']) ?>
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

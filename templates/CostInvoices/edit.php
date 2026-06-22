<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\CostInvoice $invoice
 */
$isEdit = !empty($invoice->id);
$this->assign('title', $isEdit ? 'Edytuj fakturę kosztową' : 'Dodaj fakturę kosztową');
$fval = fn(string $field) => h((string)($invoice->$field ?? ''));
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i> Lista
    </a>
    <h4 class="mb-0 fw-semibold ms-2">
        <?= $isEdit ? 'Edytuj fakturę kosztową' : 'Nowa faktura kosztowa' ?>
    </h4>
</div>

<?= $this->Form->create($invoice, [
    'url'     => $isEdit ? ['action' => 'edit', $invoice->id] : ['action' => 'add'],
    'enctype' => 'multipart/form-data',
    'class'   => 'needs-validation',
]) ?>

<div class="row g-3">

<!-- Dane podstawowe -->
<div class="col-lg-8">
<div class="card">
    <div class="card-header fw-semibold">Dane faktury</div>
    <div class="card-body">
        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label fw-medium">Numer faktury</label>
                <input type="text" name="invoice_number" class="form-control"
                       value="<?= $fval('invoice_number') ?>" placeholder="np. FV/2026/03/001">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-medium">Miesiąc rozliczeniowy <span class="text-danger">*</span></label>
                <input type="month" name="accounting_month" class="form-control" required
                       value="<?= $fval('accounting_month') ?>">
                <div class="form-text">Miesiąc do którego należy faktura (niezależnie od daty wystawienia)</div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-medium">Data wystawienia</label>
                <input type="date" name="issue_date" class="form-control"
                       value="<?= $fval('issue_date') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-medium">Data wpływu</label>
                <input type="date" name="receipt_date" class="form-control"
                       value="<?= $fval('receipt_date') ?: date('Y-m-d') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-medium">Termin płatności</label>
                <input type="date" name="payment_date" class="form-control"
                       value="<?= $fval('payment_date') ?>"
                       placeholder="opcjonalnie">
                <small class="text-muted">Deadline — kontrola przeterminowanych w panelu.</small>
            </div>

        </div>
    </div>
</div>

<!-- Przewoźnik -->
<div class="card mt-3">
    <div class="card-header fw-semibold">Przewoźnik</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label fw-medium">Nazwa przewoźnika</label>
                <input type="text" name="contractor_name" class="form-control"
                       value="<?= $fval('contractor_name') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-medium">NIP</label>
                <input type="text" name="contractor_nip" class="form-control"
                       value="<?= $fval('contractor_nip') ?>" placeholder="0000000000">
            </div>
        </div>
    </div>
</div>

<!-- Kwoty -->
<div class="card mt-3">
    <div class="card-header fw-semibold">Kwoty</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-medium">Netto</label>
                <input type="number" name="netto" class="form-control" step="0.01" min="0"
                       value="<?= h((string)($invoice->netto ?? '0.00')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium">VAT</label>
                <input type="number" name="vat" class="form-control" step="0.01" min="0"
                       value="<?= h((string)($invoice->vat ?? '0.00')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium">Brutto</label>
                <input type="number" name="brutto" class="form-control" step="0.01" min="0"
                       value="<?= h((string)($invoice->brutto ?? '0.00')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-medium">Waluta</label>
                <select name="currency" class="form-select">
                    <?php foreach (['PLN','EUR','USD','GBP','CHF','CZK','HUF','RON','BGN','HRK'] as $c): ?>
                    <option value="<?= $c ?>" <?= ($invoice->currency ?? 'PLN') === $c ? 'selected' : '' ?>>
                        <?= $c ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- Auto-calc brutto -->
        <div class="mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-calc-brutto">
                <i class="ri-calculator-line me-1"></i> Oblicz brutto (netto + VAT)
            </button>
        </div>
    </div>
</div>

<!-- Uwagi -->
<div class="card mt-3">
    <div class="card-header fw-semibold">Uwagi</div>
    <div class="card-body">
        <textarea name="notes" class="form-control" rows="3"
                  placeholder="Opcjonalne uwagi…"><?= $fval('notes') ?></textarea>
    </div>
</div>
</div>

<!-- Panel boczny -->
<div class="col-lg-4">

<!-- Status -->
<div class="card">
    <div class="card-header fw-semibold">Status i źródło</div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label fw-medium">Status</label>
            <select name="status" class="form-select">
                <option value="received" <?= ($invoice->status ?? '') === 'received' ? 'selected' : '' ?>>Otrzymana</option>
                <option value="verified" <?= ($invoice->status ?? '') === 'verified' ? 'selected' : '' ?>>Zweryfikowana</option>
                <option value="paid"     <?= ($invoice->status ?? '') === 'paid'     ? 'selected' : '' ?>>Zapłacona</option>
            </select>
        </div>
        <input type="hidden" name="source" value="<?= $fval('source') ?: 'manual' ?>">
        <div class="small text-muted">
            Źródło: <strong><?= ($invoice->source ?? 'manual') === 'ksef' ? 'KSeF' : 'Ręczna' ?></strong>
        </div>
        <?php if ($invoice->ksef_number): ?>
        <div class="small text-muted mt-1">
            KSeF: <code><?= h(substr($invoice->ksef_number, 0, 40)) ?>…</code>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload PDF -->
<div class="card mt-3">
    <div class="card-header fw-semibold">Dokument PDF</div>
    <div class="card-body">
        <?php if ($invoice->pdf_path): ?>
        <div class="mb-2">
            <a href="/<?= h($invoice->pdf_path) ?>" target="_blank" class="btn btn-sm btn-outline-danger w-100">
                <i class="ri-file-pdf-line me-1"></i> Pokaż obecny PDF
            </a>
        </div>
        <div class="small text-muted mb-2">Wgraj nowy plik żeby zastąpić:</div>
        <?php endif; ?>
        <input type="file" name="pdf_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        <div class="form-text">Opcjonalnie: skan lub PDF faktury</div>
    </div>
</div>

<!-- Zapisz -->
<div class="card mt-3 border-0">
    <div class="card-body p-0 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="ri-save-line me-1"></i> <?= $isEdit ? 'Zapisz zmiany' : 'Zapisz fakturę' ?>
        </button>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary">
            Anuluj
        </a>
    </div>
</div>

</div>
</div>

<?= $this->Form->end() ?>

<?php $this->append('scriptBottom'); ?>
<script>
document.getElementById('btn-calc-brutto').addEventListener('click', function() {
    var netto  = parseFloat(document.querySelector('[name="netto"]').value) || 0;
    var vat    = parseFloat(document.querySelector('[name="vat"]').value) || 0;
    document.querySelector('[name="brutto"]').value = (netto + vat).toFixed(2);
});
</script>
<?php $this->end(); ?>

<?php
$fieldName = $fieldName ?? 'template';
$options = $options ?? [
    'default' => 'Domyślny',
    'compact' => 'Kompaktowy',
    'pro' => 'PRO',
];

$selected = (string)($selected ?? $this->request->getData($fieldName) ?? '');
if ($selected === '' && isset($invoice) && is_object($invoice) && !empty($invoice->{$fieldName})) {
    $selected = (string)$invoice->{$fieldName};
}
if ($selected === '' || !array_key_exists($selected, $options)) {
    $selected = 'default';
}

$pickerId = 'template-picker-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
?>

<div class="invoice-template-picker">
  <label class="form-label">Szablon wydruku</label>
  <div class="row g-2">
    <?php foreach ($options as $value => $label): ?>
      <?php $id = $pickerId . '-' . $value; ?>
      <div class="col-md-4">
        <input
          type="radio"
          class="btn-check"
          name="<?= h($fieldName) ?>"
          id="<?= h($id) ?>"
          value="<?= h($value) ?>"
          autocomplete="off"
          <?= $selected === $value ? 'checked' : '' ?>
        >
        <label class="btn btn-outline-secondary w-100 text-start p-2" for="<?= h($id) ?>">
          <span class="fw-semibold d-block mb-2"><?= h($label) ?></span>
          <span class="d-block border rounded bg-white p-2 template-thumb template-thumb-<?= h($value) ?>">
            <span class="d-block bg-light border rounded mb-1" style="height:6px; width:60%;"></span>
            <span class="d-block bg-light border rounded mb-1" style="height:6px; width:85%;"></span>
            <span class="d-block bg-light border rounded" style="height:6px; width:70%;"></span>
          </span>
        </label>
      </div>
    <?php endforeach; ?>
  </div>
  <small class="text-muted d-block mt-1">Wybierz układ wydruku faktury na PDF.</small>
</div>

<style>
  .invoice-template-picker .btn-check:checked + .btn {
    border-color: #0d6efd;
    box-shadow: 0 0 0 .15rem rgba(13,110,253,.2);
  }
  .invoice-template-picker .template-thumb {
    min-height: 70px;
  }
  .invoice-template-picker .template-thumb-compact {
    transform: scale(.95);
    transform-origin: top left;
  }
  .invoice-template-picker .template-thumb-pro {
    background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
  }
</style>
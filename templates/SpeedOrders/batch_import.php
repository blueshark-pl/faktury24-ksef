<?php
/**
 * @var \App\View\AppView $this
 * @var array|null $preview
 * @var array $errors
 * @var array $errorRows
 * @var int $importedCount
 * @var string|null $csvText
 */
$this->assign('title', __('Import zleceń z CSV'));
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-upload-cloud-2-line me-1 text-primary"></i>
        <?= __('Import zleceń z pliku CSV') ?>
    </h4>
    <div class="d-flex gap-2">
        <a href="<?= $this->Url->build(['action' => 'batchImportTemplate']) ?>" class="btn btn-sm btn-outline-success">
            <i class="ri-download-2-line me-1"></i><?= __('Pobierz szablon CSV') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-close-line me-1"></i><?= __('Anuluj') ?>
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div><?= h($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($preview === null): ?>
    <!-- Formularz uploadu -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?= $this->Form->create(null, ['url' => ['action' => 'batchImport'], 'type' => 'file']) ?>
                <div class="mb-3">
                    <label class="form-label"><?= __('Plik CSV') ?></label>
                    <input type="file" name="csv" accept=".csv,text/csv" class="form-control" required>
                    <div class="small text-muted mt-1">
                        <?= __('Separator: `,` `;` lub tab (auto-detect). Kodowanie: UTF-8 lub Windows-1250. Max 5 MB. Pierwsza linia = header.') ?>
                    </div>
                </div>
                <div class="alert alert-info small">
                    <strong><?= __('Wymagane kolumny:') ?></strong>
                    <code>buyer_name</code>, <code>load_city</code>, <code>unload_city</code>, <code>netto</code>
                    <br>
                    <strong><?= __('Opcjonalne:') ?></strong>
                    <code>buyer_nip</code>, <code>buyer_email</code>, <code>load_country</code>, <code>unload_country</code>,
                    <code>date_deadline</code>, <code>date_delivery</code>, <code>currency</code> (default PLN),
                    <code>title1</code>, <code>title2</code>, <code>cargo_type</code>, <code>driver</code>, <code>vehicle_reg</code>,
                    <code>contract</code>, <code>payment_terms</code>, <code>cargo_weight_kg</code>, <code>cargo_pallets</code>,
                    <code>adr_class</code>, <code>incoterms</code>, <code>notes</code>
                    <br>
                    <a href="<?= $this->Url->build(['action' => 'batchImportTemplate']) ?>"><?= __('Pobierz szablon CSV') ?></a>
                    <?= __('z przykładowym rekordem.') ?>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-eye-line me-1"></i><?= __('Wczytaj i pokaż podgląd') ?>
                </button>
            <?= $this->Form->end() ?>
        </div>
    </div>
<?php else: ?>
    <!-- Preview -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="alert alert-info d-flex justify-content-between align-items-center">
                <span>
                    <i class="ri-eye-line me-1"></i>
                    <?= __('Podgląd: {0} wierszy do zaimportowania.', count($preview)) ?>
                    <?php if (count($errorRows) > 0): ?>
                        <span class="text-danger ms-2">
                            <i class="ri-error-warning-line me-1"></i>
                            <?= __('{0} wierszy z błędami — zostaną pominięte.', count($errorRows)) ?>
                        </span>
                    <?php endif; ?>
                </span>
            </div>

            <?php if (!empty($errorRows)): ?>
                <h6 class="fw-semibold text-danger"><?= __('Błędy walidacji:') ?></h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered small">
                        <thead class="table-danger">
                            <tr><th>Wiersz</th><th>Błąd</th><th>Dane</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($errorRows, 0, 20) as $er): ?>
                                <tr>
                                    <td><?= h($er['row']) ?></td>
                                    <td class="text-danger"><?= h($er['error']) ?></td>
                                    <td class="small text-muted"><?= h(json_encode($er['data'], JSON_UNESCAPED_UNICODE)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h6 class="fw-semibold"><?= __('Pierwsze 10 wierszy poprawnych:') ?></h6>
            <div class="table-responsive mb-3" style="max-height:400px; overflow-y:auto">
                <table class="table table-sm table-hover small">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>#</th>
                            <th>Klient</th>
                            <th>NIP</th>
                            <th>Trasa</th>
                            <th>Netto</th>
                            <th>Waluta</th>
                            <th>Data zał.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($preview, 0, 10) as $idx => $r): ?>
                            <tr>
                                <td><?= $idx + 2 ?></td>
                                <td><strong><?= h($r['buyer_name'] ?? '') ?></strong></td>
                                <td><code><?= h($r['buyer_nip'] ?? '') ?></code></td>
                                <td><?= h(($r['load_city'] ?? '') . ' → ' . ($r['unload_city'] ?? '')) ?></td>
                                <td class="text-end"><?= h($r['netto'] ?? '') ?></td>
                                <td><?= h($r['currency'] ?? 'PLN') ?></td>
                                <td><?= h($r['date_deadline'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= $this->Form->create(null, ['url' => ['action' => 'batchImport']]) ?>
                <input type="hidden" name="confirm" value="1">
                <input type="hidden" name="csv_text" value="<?= h($csvText ?? '') ?>">
                <div class="text-end">
                    <a href="<?= $this->Url->build(['action' => 'batchImport']) ?>" class="btn btn-outline-secondary">
                        <i class="ri-arrow-left-line me-1"></i><?= __('Wróć') ?>
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="ri-check-double-line me-1"></i>
                        <?= __('Potwierdź i zaimportuj {0} zleceń', count($preview) - count($errorRows)) ?>
                    </button>
                </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
<?php endif; ?>

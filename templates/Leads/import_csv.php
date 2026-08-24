<?php
/**
 * @var \App\View\AppView $this
 * @var array|null $preview
 * @var array $errors
 * @var array $errorRows
 * @var int $importedCount
 * @var string|null $csvText
 */
$this->assign('title', __('Import leadów z CSV'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-upload-cloud-2-line me-1 text-primary"></i>
        <?= __('Import leadów z pliku CSV') ?>
    </h4>
    <div class="d-flex gap-2">
        <a href="<?= $this->Url->build(['action' => 'importCsvTemplate']) ?>" class="btn btn-sm btn-outline-success">
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
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?= $this->Form->create(null, ['url' => ['action' => 'importCsv'], 'type' => 'file']) ?>
                <div class="mb-3">
                    <label class="form-label"><?= __('Plik CSV') ?></label>
                    <input type="file" name="csv" accept=".csv,text/csv" class="form-control" required>
                    <div class="small text-muted mt-1">
                        <?= __('Separator: `,` `;` lub tab (auto-detect). Kodowanie: UTF-8 lub Windows-1250. Max 5 MB. Pierwsza linia = header.') ?>
                    </div>
                </div>
                <div class="alert alert-info small">
                    <strong><?= __('Wymagane:') ?></strong> <code>company_name</code> (lub „Nazwa firmy")<br>
                    <strong><?= __('Rozpoznawane nagłówki (PL/EN, case-insensitive):') ?></strong>
                    <code>nip</code>, <code>kraj</code>/<code>country_code</code>,
                    <code>kod</code>/<code>postal_code</code>, <code>miasto</code>/<code>city</code>,
                    <code>ulica</code>/<code>street</code>, <code>osoba kontaktowa</code>/<code>contact_person</code>,
                    <code>stanowisko</code>/<code>contact_role</code>,
                    <code>tel</code>/<code>phone</code>, <code>email</code>/<code>adres mailowy</code>,
                    <code>rodzaj kontaktu</code>/<code>contact_channel</code>,
                    <code>gałąź</code>/<code>branch_type</code>, <code>etap</code>/<code>stage</code>,
                    <code>skuteczność</code>/<code>probability</code>, <code>wartość</code>/<code>value_pln</code>,
                    <code>flag_contact/inquiry/offer/order</code> (checkboxy: 1/tak/x/true → true),
                    <code>notatka</code>/<code>note</code>, <code>następna akcja</code>/<code>next_action_description</code>.
                    <br>
                    <?= __('Dedup: pomijamy leady z NIP który już istnieje w bazie tej firmy.') ?>
                    <br>
                    <a href="<?= $this->Url->build(['action' => 'importCsvTemplate']) ?>"><?= __('Pobierz szablon CSV') ?></a>
                    <?= __('z przykładowym rekordem SILESIAN FLOUR.') ?>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-eye-line me-1"></i><?= __('Wczytaj i pokaż podgląd') ?>
                </button>
            <?= $this->Form->end() ?>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="alert alert-info d-flex justify-content-between align-items-center">
                <span>
                    <i class="ri-eye-line me-1"></i>
                    <?= sprintf(__('Podgląd: %d wierszy do zaimportowania.'), count($preview)) ?>
                    <?php if (count($errorRows) > 0): ?>
                        <span class="text-danger ms-2">
                            <i class="ri-error-warning-line me-1"></i>
                            <?= sprintf(__('%d wierszy z błędami — zostaną pominięte.'), count($errorRows)) ?>
                        </span>
                    <?php endif; ?>
                </span>
            </div>

            <?php if (!empty($errorRows)): ?>
                <details class="mb-3">
                    <summary class="text-danger fw-semibold" style="cursor:pointer;">
                        <i class="ri-alert-line"></i> <?= sprintf(__('Wiersze z błędami: %d'), count($errorRows)) ?>
                    </summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered small">
                            <thead class="table-light">
                                <tr><th><?= __('Wiersz') ?></th><th><?= __('Błąd') ?></th><th><?= __('Dane') ?></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($errorRows as $er): ?>
                                    <tr class="table-danger">
                                        <td><?= (int)$er['row'] ?></td>
                                        <td><?= h($er['error']) ?></td>
                                        <td class="small text-muted"><?= h(substr(json_encode($er['data'], JSON_UNESCAPED_UNICODE), 0, 200)) ?>…</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>

            <div class="table-responsive" style="max-height:400px; overflow:auto;">
                <table class="table table-sm table-hover small align-middle">
                    <thead class="table-light">
                        <tr>
                            <th><?= __('Firma') ?></th><th>NIP</th><th><?= __('Kraj/Miasto') ?></th>
                            <th><?= __('Osoba') ?></th><th><?= __('Kontakt') ?></th>
                            <th><?= __('Etap') ?></th><th><?= __('Wart.') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($preview, 0, 20) as $r): ?>
                            <tr>
                                <td class="fw-semibold"><?= h($r['company_name'] ?? '') ?></td>
                                <td><code><?= h($r['nip'] ?? '') ?></code></td>
                                <td><?= h(strtoupper((string)($r['country_code'] ?? ''))) ?> · <?= h($r['city'] ?? '') ?></td>
                                <td><?= h($r['contact_person'] ?? '') ?></td>
                                <td class="small text-muted"><?= h($r['phone'] ?? '') ?><br><?= h($r['email'] ?? '') ?></td>
                                <td><?= h($r['stage'] ?? 'new') ?></td>
                                <td class="text-end"><?= h($r['value_pln'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($preview) > 20): ?>
                            <tr><td colspan="7" class="text-center text-muted">…<?= sprintf(__('+%d więcej wierszy'), count($preview) - 20) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?= $this->Form->create(null, ['url' => ['action' => 'importCsv'], 'class' => 'mt-3']) ?>
                <input type="hidden" name="confirm" value="1">
                <textarea name="csv_text" class="d-none"><?= h($csvText ?? '') ?></textarea>
                <button type="submit" class="btn btn-success">
                    <i class="ri-check-line me-1"></i><?= sprintf(__('Zaimportuj %d leadów'), count($preview) - count($errorRows)) ?>
                </button>
                <a href="<?= $this->Url->build(['action' => 'importCsv']) ?>" class="btn btn-outline-secondary ms-2">
                    <i class="ri-arrow-left-line me-1"></i><?= __('Wybierz inny plik') ?>
                </a>
            <?= $this->Form->end() ?>
        </div>
    </div>
<?php endif; ?>

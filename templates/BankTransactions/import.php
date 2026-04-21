<?php
/**
 * @var \App\View\AppView $this
 * @var string $title
 */
$this->assign('title', $title ?? 'Import wyciągu MT940');
?>

<!-- Nagłówek -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">Wyciągi bankowe <span class="text-muted fs-6 fw-normal">MT940</span></h4>
</div>

<!-- Zakładki -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'index']) ?>">
            <i class="ri-archive-line me-1"></i> Importy
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'transactions']) ?>">
            <i class="ri-exchange-line me-1"></i> Wszystkie transakcje
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="#">
            <i class="ri-upload-2-line me-1"></i> Importuj MT940
        </a>
    </li>
</ul>

<?= $this->Flash->render() ?>

<div class="row justify-content-start">
    <div class="col-xl-6 col-lg-8">

        <?= $this->Form->create(null, [
            'url'     => ['action' => 'import'],
            'enctype' => 'multipart/form-data',
            'id'      => 'import-form',
        ]) ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="mb-4">
                    <label for="mt940_file" class="form-label fw-semibold">Plik MT940</label>
                    <input type="file"
                           class="form-control"
                           id="mt940_file"
                           name="mt940_file"
                           accept=".sta,.mt940,.txt,.csv,.940"
                           required>
                    <div class="form-text text-muted mt-1">
                        Obsługiwane banki: PKO BP, mBank, ING, Santander, BNP Paribas, Alior, Pekao i inne
                        eksportujące standard SWIFT MT940.
                    </div>
                </div>

                <div class="alert alert-info py-2 px-3 mb-0">
                    <i class="ri-information-line me-1"></i>
                    Transakcje już wcześniej zaimportowane zostają automatycznie pominięte —
                    nie ma ryzyka duplikatów przy wielokrotnym imporcie tego samego pliku.
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <?= $this->Html->link('Anuluj', ['action' => 'index'], ['class' => 'btn btn-outline-secondary']) ?>
                <button type="submit" class="btn btn-primary" id="import-submit-btn">
                    <i class="ri-upload-2-line me-1"></i> Importuj wyciąg
                </button>
            </div>
        </div>

        <?= $this->Form->end() ?>

        <div class="card mt-4 border-0 bg-light">
            <div class="card-body py-3">
                <h6 class="mb-2 text-muted fw-semibold"><i class="ri-question-line me-1"></i>Jak pobrać plik MT940?</h6>
                <ul class="mb-0 small text-muted ps-3">
                    <li><strong>PKO BP:</strong> iPKO → Historia → Eksport → Format MT940</li>
                    <li><strong>mBank:</strong> Historia rachunku → Eksportuj wyciąg → MT940</li>
                    <li><strong>ING:</strong> Konto → Historia → Pobierz wyciąg → MT940</li>
                    <li><strong>Santander:</strong> Historia transakcji → Eksport → MT940 (.sta)</li>
                    <li><strong>BNP Paribas:</strong> eGO → Rachunki → Wyciąg MT940</li>
                    <li><strong>Alior Bank:</strong> Historia → Eksport → MT940</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<script>
document.getElementById('import-form').addEventListener('submit', function () {
    var btn = document.getElementById('import-submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importowanie…';
});
</script>

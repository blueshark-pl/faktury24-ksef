<?php
/** @var \App\View\AppView $this */
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12 col-lg-10 col-xl-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Wgraj certyfikat KSeF</h5>
                    <span class="badge <?= ($defaultEnv ?? 'prod') === 'prod' ? 'bg-danger' : 'bg-secondary' ?>">
                        <?= h(strtoupper((string)($defaultEnv ?? 'prod'))) ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if (isset($certPresent) && $certPresent): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="ti ti-certificate me-2"></i>
                            <div>Certyfikat dla wybranego środowiska jest już zapisany. Możesz wgrać nowy, aby podmienić.</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning d-flex align-items-center" role="alert">
                            <i class="ti ti-alert-triangle me-2"></i>
                            <div>Brak zapisanego certyfikatu dla wybranego środowiska.</div>
                        </div>
                    <?php endif; ?>

                                <?= $this->Form->create(null, ['type' => 'file', 'class' => 'row g-3']) ?>

                    <div class="col-12 col-md-6">
                        <?= $this->Form->control('environment', [
                            'type' => 'select',
                            'options' => $environments ?? ['test' => 'Test', 'prod' => 'Production'],
                            'label' => 'Środowisko',
                            'value' => $defaultEnv ?? 'test',
                            'class' => 'form-select'
                        ]) ?>
                    </div>

                                <?= $this->Form->hidden('variant', ['value' => 'single', 'id' => 'variantField']) ?>

                                <div class="col-12">
                                    <ul class="nav nav-pills mb-3" id="certVariantTab" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="v1-tab" data-bs-toggle="tab" data-bs-target="#v1" type="button" role="tab" aria-controls="v1" aria-selected="true">
                                                Wariant 1: pojedynczy plik
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="v2-tab" data-bs-toggle="tab" data-bs-target="#v2" type="button" role="tab" aria-controls="v2" aria-selected="false">
                                                Wariant 2: para .key + .crt
                                            </button>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="certVariantTabContent">
                                        <div class="tab-pane fade show active" id="v1" role="tabpanel" aria-labelledby="v1-tab" tabindex="0">
                                            <div class="border rounded-3 p-3 mb-2">
                                                <div class="d-flex align-items-center mb-2">
                                                    <h6 class="mb-0">Wariant 1: pojedynczy plik (.p12 lub .pem)</h6>
                                                    <span class="badge bg-primary ms-2">Rekomendowane: .p12</span>
                                                </div>
                                                <div class="row g-3">
                                                    <div class="col-12 col-md-6">
                                                        <?= $this->Form->control('certificate', [
                                                            'type' => 'file',
                                                            'label' => 'Plik certyfikatu (.p12 lub .pem)',
                                                            'class' => 'form-control'
                                                        ]) ?>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <?= $this->Form->control('passphrase', [
                                                            'type' => 'password',
                                                            'label' => 'Hasło (jeśli wymagane)',
                                                            'class' => 'form-control',
                                                            'autocomplete' => 'new-password'
                                                        ]) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="v2" role="tabpanel" aria-labelledby="v2-tab" tabindex="0">
                                            <div class="border rounded-3 p-3">
                                                <h6 class="mb-2">Wariant 2: para plików (.key + .crt)</h6>
                                                <div class="row g-3">
                                                    <div class="col-12 col-md-6">
                                                        <?= $this->Form->control('private_key', [
                                                            'type' => 'file',
                                                            'label' => 'Klucz prywatny (.key)',
                                                            'class' => 'form-control'
                                                        ]) ?>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <?= $this->Form->control('public_cert', [
                                                            'type' => 'file',
                                                            'label' => 'Certyfikat publiczny (.crt)',
                                                            'class' => 'form-control'
                                                        ]) ?>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <?= $this->Form->control('private_key_passphrase', [
                                                            'type' => 'password',
                                                            'label' => 'Hasło do klucza prywatnego (.key), jeśli zaszyfrowany',
                                                            'class' => 'form-control',
                                                            'autocomplete' => 'new-password'
                                                        ]) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                    <div class="col-12 d-flex align-items-center justify-content-between">
                        <small class="text-muted">Możesz wgrać jeden plik (.p12 albo .pem) lub parę (.key + .crt). W przypadku pary plików aplikacja utworzy połączony PEM do użycia z KSeF.</small>
                        <?= $this->Form->button(__('Zapisz'), ['class' => 'btn btn-primary']) ?>
                    </div>

                                <?= $this->Form->end() ?>
                                            <?= $this->Html->scriptBlock('document.addEventListener("DOMContentLoaded", function(){var input=document.getElementById("variantField");var tabButtons=document.querySelectorAll("#certVariantTab button[data-bs-toggle=\'tab\']");tabButtons.forEach(function(btn){btn.addEventListener("shown.bs.tab", function(e){var target=e.target.getAttribute("data-bs-target");if(input){input.value=(target==="#v1")?"single":"pair";}});});});', ['block' => 'scriptBottom']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

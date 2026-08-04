<?php
/**
 * Formularz recznego tworzenia / edycji zlecenia transportowego.
 * FALA 1 UX: sticky action bar, autosave localStorage, skroty klawiszowe,
 * accordion dla mniej uzywanych pol, prefill z ostatniego zlecenia klienta,
 * hint "ostatnie w miesiacu", obsluga ?dup={id}.
 *
 * @var \App\View\AppView            $this
 * @var \App\Model\Entity\SpeedOrder $order
 * @var bool                         $isEdit
 * @var array                        $drivers
 * @var array                        $vehicles
 * @var array                        $recentInMonth  [{id, symbol, date_doc, buyer_name}]
 */
$this->assign('title', $isEdit
    ? __('Edycja zlecenia {0}', h($order->symbol))
    : __('Nowe zlecenie transportowe'));

$formUrl = $isEdit
    ? $this->Url->build(['action' => 'edit', $order->id])
    : $this->Url->build(['action' => 'add']);

$dupId = (int)$this->request->getQuery('dup');

$currencies = ['PLN', 'EUR', 'USD', 'GBP', 'CHF', 'CZK', 'NOK', 'SEK', 'DKK', 'HUF'];
$vatRates = [
    '23' => '23%',
    '8'  => '8%',
    '5'  => '5%',
    '0'  => '0%',
    'np' => __('n/p (nie podlega)'),
    'zw' => __('zw (zwolniona)'),
    'oo' => __('Reverse Charge'),
];
$countries = ['PL','DE','CZ','SK','AT','NL','BE','FR','ES','IT','HU','RO','LT','LV','EE','SE','NO','DK','FI','CH','GB','IE','PT','SI','HR','BG'];
$contracts = ['OWN 1', 'OWN 2', 'OWN 3', 'OWN PL 1', 'OWN PL 2', 'OWN X1'];

$currentVatRate = '23';
if ($isEdit && (float)$order->netto > 0) {
    $rate = round(((float)$order->vat / (float)$order->netto) * 100);
    if (in_array((string)$rate, ['0','5','8','23'], true)) $currentVatRate = (string)$rate;
    elseif ((float)$order->vat == 0.0 && (float)$order->netto == (float)$order->brutto) $currentVatRate = 'np';
}

// Klucz autosave — per URL zeby nie mieszac add vs edit
$autosaveKey = 'so_form_' . ($isEdit ? 'edit_' . $order->id : 'add');

// CSRF token do AJAX POST (bez tego CakePHP zwraca 403)
$csrfToken = (string)$this->request->getAttribute('csrfToken');
?>

<style>
.so-form-wrap { padding-bottom: 90px; }
.so-sticky-bar {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1030;
    background: #fff; border-top: 1px solid #e5e7eb;
    box-shadow: 0 -4px 12px rgba(0,0,0,.06);
    padding: .75rem 1.5rem;
}
.so-sticky-bar .container-fluid { max-width: 1400px; margin: 0 auto; }
.so-section-card { transition: box-shadow .15s ease; }
.so-section-card:focus-within { box-shadow: 0 0 0 3px rgba(13,110,253,.08); }
.so-section-title { letter-spacing: .5px; }
.so-hint { font-size: .72rem; color: #6c757d; }
.so-badge-recent { background:#eef2ff;color:#4338ca;padding:.15rem .5rem;border-radius:.35rem;font-size:.7rem;text-decoration:none;margin-right:.35rem;display:inline-block; }
.so-badge-recent:hover { background:#dbeafe; color:#3730a3; }
.so-autosave-indicator { position: fixed; top: 70px; right: 20px; z-index: 1020; opacity: 0; transition: opacity .3s ease; }
.so-autosave-indicator.show { opacity: 1; }
kbd { background:#f3f4f6;border:1px solid #d1d5db;border-radius:.2rem;padding:.05rem .35rem;font-size:.7rem;color:#374151; }
.so-lastclient-suggestion { background: linear-gradient(90deg,#ecfdf5 0%,#f0fdfa 100%); border-left: 3px solid #10b981; padding: .6rem .9rem; border-radius: .35rem; }
</style>

<div class="so-form-wrap">

<!-- Header + hint -->
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold">
            <?= $isEdit ? __('Edytuj zlecenie') : __('Nowe zlecenie transportowe') ?>
            <span class="badge bg-primary-subtle text-primary ms-2"><?= h($order->symbol ?? '') ?></span>
            <?php if ($dupId): ?>
                <span class="badge bg-warning-subtle text-warning ms-1">
                    <i class="ri-file-copy-line me-1"></i><?= __('Duplikat') ?>
                </span>
            <?php endif; ?>
        </h4>
        <div class="text-muted small mt-1">
            <?= $isEdit
                ? __('Numer i data dokumentu są niezmienne. Edytujesz pozostałe dane.')
                : __('Ustaw dane zlecenia. Numer zostanie nadany automatycznie w formacie M-NNNN/MM/RRRR.') ?>
        </div>

        <?php if (!$isEdit && !empty($recentInMonth)): ?>
            <div class="mt-2">
                <span class="so-hint me-2"><?= __('Ostatnie w tym miesiącu:') ?></span>
                <?php foreach ($recentInMonth as $r): ?>
                    <a class="so-badge-recent"
                       href="<?= $this->Url->build(['action' => 'view', $r['id']]) ?>"
                       title="<?= h($r['buyer_name'] ?? '') ?>">
                        <?= h($r['symbol']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2 align-items-start flex-column">
        <?php if (!$isEdit): ?>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#so-ai-modal">
            <i class="ri-sparkling-2-line me-1"></i> <?= __('AI: wklej email lub screenshot') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#so-plan-modal">
            <i class="ri-route-line me-1"></i> <?= __('Załaduj z planera tras') ?>
        </button>
        <?php endif; ?>
        <div class="so-hint text-end pt-1">
            <div><kbd>Ctrl</kbd>+<kbd>S</kbd> <?= __('zapisz') ?></div>
            <?php if (!$isEdit): ?>
                <div><kbd>Ctrl</kbd>+<kbd>Enter</kbd> <?= __('zapisz + dodaj kolejne') ?></div>
            <?php endif; ?>
            <div><kbd>Esc</kbd> <?= __('anuluj') ?></div>
        </div>
    </div>
</div>

<!-- MODAL: Wybor planu tras -->
<div class="modal fade" id="so-plan-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ri-route-line me-1 text-primary"></i>
                    <?= __('Wybierz plan trasy do załadowania') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="so-plan-status" class="alert alert-info py-2 px-3 small"><?= __('Ładowanie planów...') ?></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th><?= __('Nazwa') ?></th>
                                <th><?= __('Status') ?></th>
                                <th><?= __('Trasa') ?></th>
                                <th><?= __('Km') ?></th>
                                <th><?= __('Cena') ?></th>
                                <th><?= __('Klient') ?></th>
                                <th><?= __('Utworzono') ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="so-plan-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Wolne zasoby w oknie -->
<div class="modal fade" id="so-free-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ri-calendar-check-line me-1 text-primary"></i>
                    <?= __('Wolne zasoby w oknie czasowym') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="so-free-status" class="alert alert-info py-2 px-3 small"></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-semibold small text-uppercase text-muted"><?= __('Wolni kierowcy') ?></h6>
                        <div id="so-free-drivers" class="list-group"></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold small text-uppercase text-muted"><?= __('Wolne pojazdy') ?></h6>
                        <div id="so-free-vehicles" class="list-group"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: AI parser email/screenshot -->
<div class="modal fade" id="so-ai-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ri-sparkling-2-line me-1 text-primary"></i>
                    <?= __('AI parser: wklej email/SMS lub screenshot zapytania') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-muted"><?= __('Treść wiadomości (email/SMS/WhatsApp)') ?></label>
                    <textarea id="so-ai-text" class="form-control" rows="6" placeholder="<?= __('Wklej tutaj tekst wiadomości od klienta...') ?>"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted"><?= __('LUB screenshot (JPG/PNG, max 5 MB)') ?></label>
                    <input type="file" id="so-ai-image" accept="image/png,image/jpeg,image/webp" class="form-control">
                    <div class="so-hint mt-1"><?= __('Możesz też wkleić obraz (Ctrl+V) po kliknięciu w textarea powyżej.') ?></div>
                    <div id="so-ai-preview" class="mt-2"></div>
                </div>
                <div id="so-ai-status" class="alert alert-info py-2 px-3 mb-0 d-none small"></div>
                <div id="so-ai-result" class="mt-2 d-none">
                    <div class="alert alert-success py-2 px-3 mb-2 small">
                        <i class="ri-check-line me-1"></i>
                        <?= __('AI wyciągnęło dane. Confidence:') ?>
                        <strong id="so-ai-conf">-</strong>%
                    </div>
                    <div class="so-hint" id="so-ai-note"></div>
                    <div id="so-ai-summary" class="small mt-2"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?= __('Anuluj') ?>
                </button>
                <button type="button" class="btn btn-primary" id="so-ai-btn-parse">
                    <i class="ri-magic-line me-1"></i><?= __('Przeanalizuj i wypełnij') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Autosave indicator -->
<div id="so-autosave" class="so-autosave-indicator">
    <span class="badge bg-success-subtle text-success shadow-sm">
        <i class="ri-check-line me-1"></i><span id="so-autosave-txt"><?= __('Zapisano lokalnie') ?></span>
    </span>
</div>

<?= $this->Form->create(null, [
    'url'   => $formUrl,
    'type'  => 'post',
    'id'    => 'form-manual-order',
    'class' => 'row g-3',
    'novalidate' => 'novalidate',
]) ?>

<input type="hidden" name="save_and_new"     id="save_and_new"     value="">
<input type="hidden" name="save_and_attach"  id="save_and_attach"  value="">
<input type="hidden" name="save_and_invoice" id="save_and_invoice" value="">

<!-- SEKCJA 1: Numer / meta -->
<div class="col-12">
    <div class="card border-0 shadow-sm so-section-card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted so-section-title text-uppercase"><?= __('Numer zlecenia') ?></label>
                    <input type="text" name="symbol" class="form-control fw-semibold" value="<?= h($order->symbol ?? '') ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted so-section-title text-uppercase"><?= __('Data dokumentu') ?></label>
                    <input type="date" name="date_doc" class="form-control" value="<?= h($order->date_doc ? $order->date_doc->format('Y-m-d') : date('Y-m-d')) ?>" <?= $isEdit ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted so-section-title text-uppercase"><?= __('Kontrakt') ?></label>
                    <input type="text" name="contract" list="contract-list" class="form-control" value="<?= h($order->contract ?? '') ?>" placeholder="OWN 1">
                    <datalist id="contract-list">
                        <?php foreach ($contracts as $c): ?>
                            <option value="<?= h($c) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted so-section-title text-uppercase"><?= __('Nr referencyjny klienta') ?></label>
                    <input type="text" name="title1" class="form-control" value="<?= h($order->title1 ?? '') ?>" maxlength="255" placeholder="ES-975377">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SEKCJA 2: Nabywca -->
<div class="col-12">
    <div class="card border-0 shadow-sm so-section-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-semibold text-uppercase text-muted small so-section-title">
                    <i class="ri-user-line me-1 text-primary"></i> <?= __('Zleceniodawca (nabywca)') ?>
                </h6>
                <div class="input-group input-group-sm" style="max-width:340px">
                    <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                    <input type="text" id="buyer-search" class="form-control" placeholder="<?= __('Szukaj kontrahenta (nazwa / NIP)') ?>">
                </div>
            </div>
            <div id="buyer-results" class="list-group mb-2 d-none" style="max-height:200px;overflow-y:auto"></div>

            <!-- Mini-profil klienta (historia wspolpracy) -->
            <div id="so-profile-wrap" class="mb-2 d-none">
                <div class="card border-0" style="background:#f8fafc;border-left:3px solid #6366f1 !important">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold small text-uppercase text-muted">
                                <i class="ri-user-star-line me-1 text-primary"></i><?= __('Profil klienta (12 mies)') ?>
                            </span>
                            <span class="so-hint" id="so-profile-last"></span>
                        </div>
                        <div class="row g-2 small">
                            <div class="col-md-2"><span class="so-hint">Zlecenia:</span> <strong id="so-profile-cnt">-</strong></div>
                            <div class="col-md-3"><span class="so-hint">Śr. netto:</span> <strong id="so-profile-avg">-</strong></div>
                            <div class="col-md-3"><span class="so-hint">Suma:</span> <strong id="so-profile-sum">-</strong></div>
                            <div class="col-md-2"><span class="so-hint">DSO:</span> <strong id="so-profile-dso">-</strong></div>
                            <div class="col-md-2 text-end"><a href="#" id="so-profile-toggle" class="small text-decoration-none"><?= __('szczegóły') ?></a></div>
                        </div>
                        <div id="so-profile-detail" class="mt-2 pt-2 border-top d-none">
                            <div class="so-hint mb-1" id="so-profile-route"></div>
                            <div id="so-profile-recent"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prefill z ostatniego zlecenia klienta -->
            <div id="so-lastclient-box" class="so-lastclient-suggestion mb-2 d-none">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><i class="ri-history-line me-1"></i><?= __('Znaleźliśmy ostatnie zlecenie dla tego klienta') ?></strong>
                        <div class="so-hint mt-1" id="so-lastclient-info"></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-success" id="so-lastclient-use">
                            <i class="ri-magic-line me-1"></i><?= __('Użyj jako szablon') ?>
                        </button>
                        <button type="button" class="btn-close" id="so-lastclient-close" aria-label="close"></button>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted d-flex justify-content-between align-items-center">
                        <span><?= __('NIP') ?></span>
                        <button type="button" id="so-btn-gus" class="btn btn-sm btn-link p-0 text-decoration-none" style="font-size:.72rem" title="<?= __('Pobierz dane z GUS po NIP (PL, 10 cyfr)') ?>">
                            <i class="ri-download-2-line"></i> GUS
                        </button>
                    </label>
                    <input type="text" name="buyer_nip" id="buyer-nip" class="form-control" value="<?= h($order->buyer_nip ?? '') ?>" maxlength="30">
                    <div id="so-gus-msg" class="so-hint mt-1"></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Nazwa') ?> *</label>
                    <input type="text" name="buyer_name" class="form-control" value="<?= h($order->buyer_name ?? '') ?>" required maxlength="255">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Email') ?></label>
                    <input type="email" name="buyer_email" class="form-control" value="<?= h($order->buyer_email ?? '') ?>" maxlength="180">
                </div>
                <div class="col-md-5">
                    <label class="form-label small text-muted"><?= __('Ulica') ?></label>
                    <input type="text" name="buyer_street" class="form-control" value="<?= h($order->buyer_street ?? '') ?>" maxlength="255">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Kod pocztowy') ?></label>
                    <input type="text" name="buyer_postal_code" class="form-control" value="<?= h($order->buyer_postal_code ?? '') ?>" maxlength="20">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Miasto') ?></label>
                    <input type="text" name="buyer_city" class="form-control" value="<?= h($order->buyer_city ?? '') ?>" maxlength="120">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Kraj') ?></label>
                    <select name="buyer_country" class="form-select">
                        <?php foreach ($countries as $cc): ?>
                            <option value="<?= h($cc) ?>" <?= ($order->buyer_country ?? 'PL') === $cc ? 'selected' : '' ?>><?= h($cc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SEKCJA 3: Zaladunek + Rozladunek -->
<div class="col-md-6">
    <div class="card border-0 shadow-sm so-section-card h-100" style="border-left:3px solid #10b981 !important">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small so-section-title">
                <i class="ri-truck-line me-1 text-success"></i> <?= __('Załadunek') ?>
            </h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kraj') ?></label>
                    <select name="load_country" class="form-select">
                        <option value=""></option>
                        <?php foreach ($countries as $cc): ?>
                            <option value="<?= h($cc) ?>" <?= ($order->load_country ?? '') === $cc ? 'selected' : '' ?>><?= h($cc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kod pocztowy') ?></label>
                    <input type="text" name="load_postal_code" class="form-control" value="<?= h($order->load_postal_code ?? '') ?>" maxlength="20">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Miasto') ?></label>
                    <input type="text" name="load_city" class="form-control" value="<?= h($order->load_city ?? '') ?>" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Planowana data') ?></label>
                    <input type="datetime-local" name="date_deadline" class="form-control" value="<?= h($order->date_deadline ? $order->date_deadline->format('Y-m-d\TH:i') : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Czas rzeczywisty') ?></label>
                    <input type="datetime-local" name="actual_load_at" class="form-control" value="<?= h($order->actual_load_at ? $order->actual_load_at->format('Y-m-d\TH:i') : '') ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="col-md-6">
    <div class="card border-0 shadow-sm so-section-card h-100" style="border-left:3px solid #ef4444 !important">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small so-section-title">
                <i class="ri-inbox-line me-1 text-danger"></i> <?= __('Rozładunek') ?>
            </h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kraj') ?></label>
                    <select name="unload_country" class="form-select">
                        <option value=""></option>
                        <?php foreach ($countries as $cc): ?>
                            <option value="<?= h($cc) ?>" <?= ($order->unload_country ?? '') === $cc ? 'selected' : '' ?>><?= h($cc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label small text-muted"><?= __('Miasto') ?></label>
                    <input type="text" name="unload_city" class="form-control" value="<?= h($order->unload_city ?? '') ?>" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Planowana data') ?></label>
                    <input type="datetime-local" name="date_delivery" class="form-control" value="<?= h($order->date_delivery ? $order->date_delivery->format('Y-m-d\TH:i') : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Czas rzeczywisty') ?></label>
                    <input type="datetime-local" name="actual_unload_at" class="form-control" value="<?= h($order->actual_unload_at ? $order->actual_unload_at->format('Y-m-d\TH:i') : '') ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SEKCJA 4: Ladunek -->
<div class="col-12">
    <div class="card border-0 shadow-sm so-section-card">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small so-section-title">
                <i class="ri-archive-line me-1"></i> <?= __('Ładunek') ?>
            </h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Opis ładunku') ?></label>
                    <input type="text" name="title2" class="form-control" value="<?= h($order->title2 ?? '') ?>" maxlength="255" placeholder="Towar paletowy">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Typ frachtu') ?></label>
                    <input type="text" name="cargo_type" class="form-control" value="<?= h($order->cargo_type ?? '') ?>" maxlength="120" placeholder="FTL, LTL, ADR">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Rodzaj transportu') ?></label>
                    <input type="text" name="transport_type" class="form-control" value="<?= h($order->transport_type ?? '') ?>" maxlength="100" placeholder="plandeka, chłodnia">
                </div>
                <div class="col-md-12">
                    <label class="form-label small text-muted"><?= __('Uwagi') ?></label>
                    <textarea name="notes" class="form-control" rows="2" maxlength="2000"><?= h($order->notes ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WIDGET: Warnings kolizji grafika -->
<div class="col-12" id="so-conflict-wrap" style="display:none">
    <div class="alert py-2 px-3 mb-0 small" id="so-conflict-alert"></div>
</div>

<!-- SEKCJA 5: Transport -->
<div class="col-12">
    <div class="card border-0 shadow-sm so-section-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-semibold text-uppercase text-muted small so-section-title">
                    <i class="ri-truck-fill me-1"></i> <?= __('Transport') ?>
                </h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="so-free-btn" title="<?= __('Znajdź wolnych w oknie z dat załadunku/rozładunku') ?>">
                    <i class="ri-calendar-check-line me-1"></i><?= __('Znajdź wolne zasoby') ?>
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kierowca') ?></label>
                    <input type="text" name="driver" list="driver-list" class="form-control" value="<?= h($order->driver ?? '') ?>" maxlength="200">
                    <datalist id="driver-list">
                        <?php foreach ($drivers as $d): ?>
                            <option value="<?= h($d['full_name']) ?>"><?= h($d['phone'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Rejestracja pojazdu') ?></label>
                    <input type="text" name="vehicle_reg" list="vehicle-list" class="form-control" value="<?= h($order->vehicle_reg ?? '') ?>" maxlength="50">
                    <datalist id="vehicle-list">
                        <?php foreach ($vehicles as $v): ?>
                            <?php $lbl = trim(($v['plate'] ?? '') . ' - ' . ($v['name'] ?? ''), ' -'); ?>
                            <option value="<?= h($v['plate'] ?? '') ?>"><?= h($lbl) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Przewoźnik (jeśli inny niż my)') ?></label>
                    <input type="text" name="carrier" class="form-control" value="<?= h($order->carrier ?? '') ?>" maxlength="200">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SEKCJA 6: Finanse -->
<div class="col-12">
    <div class="card border-0 shadow-sm so-section-card" style="border-left:3px solid #0d6efd !important">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small so-section-title">
                <i class="ri-money-euro-circle-line me-1 text-primary"></i> <?= __('Finanse') ?>
            </h6>
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Waluta') ?></label>
                    <select name="currency" id="fin-currency" class="form-select">
                        <?php foreach ($currencies as $cur): ?>
                            <option value="<?= h($cur) ?>" <?= ($order->currency ?? 'PLN') === $cur ? 'selected' : '' ?>><?= h($cur) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Kurs') ?></label>
                    <input type="number" step="0.000001" name="exchange_rate" id="fin-rate" class="form-control" value="<?= h($order->exchange_rate ?? '1.000000') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Netto') ?> *</label>
                    <input type="number" step="0.01" min="0" name="netto" id="fin-netto" class="form-control fw-semibold" value="<?= h($order->netto ?? '0.00') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Stawka VAT') ?></label>
                    <select name="vat_rate" id="fin-vat-rate" class="form-select">
                        <?php foreach ($vatRates as $val => $lbl): ?>
                            <option value="<?= h($val) ?>" <?= $currentVatRate === (string)$val ? 'selected' : '' ?>><?= h($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('VAT') ?></label>
                    <input type="text" id="fin-vat" class="form-control text-muted" value="<?= h($order->vat ?? '0.00') ?>" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Brutto') ?></label>
                    <input type="text" id="fin-brutto" class="form-control fw-bold fs-6" value="<?= h($order->brutto ?? '0.00') ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Warunki płatności') ?></label>
                    <input type="text" name="payment_terms" class="form-control" value="<?= h($order->payment_terms ?? 'Przelew 30 dni') ?>" maxlength="100">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WIDGET: Live kalkulator trasy HERE -->
<div class="col-12" id="so-here-wrap" style="display:none">
    <div class="card border-0 shadow-sm so-section-card" style="border-left:3px solid #6366f1 !important">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 fw-semibold text-uppercase text-muted small so-section-title">
                    <i class="ri-map-pin-line me-1 text-primary"></i> <?= __('Sugestia trasy (HERE Maps)') ?>
                    <span class="so-hint ms-2" id="so-here-status"></span>
                </h6>
                <div class="d-flex gap-1 align-items-center">
                    <label class="so-hint mb-0"><?= __('Stawka EUR/km:') ?></label>
                    <input type="number" step="0.01" min="0.1" max="5" id="so-here-rate" class="form-control form-control-sm" value="1.20" style="width:80px">
                </div>
            </div>
            <div id="so-here-alert" class="alert alert-warning py-2 px-3 mb-2 d-none small"></div>
            <div class="row g-3">
                <div class="col-md-2">
                    <div class="p-2 rounded" style="background:#f0f7ff">
                        <div class="so-hint"><?= __('Dystans') ?></div>
                        <div class="fs-5 fw-semibold text-primary" id="so-here-km">-</div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="p-2 rounded" style="background:#f0f7ff">
                        <div class="so-hint"><?= __('Czas jazdy') ?></div>
                        <div class="fs-5 fw-semibold" id="so-here-time">-</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 rounded" style="background:#fef3c7">
                        <div class="so-hint"><?= __('Tolls (EUR)') ?></div>
                        <div class="fs-5 fw-semibold" id="so-here-tolls">-</div>
                        <div class="so-hint" id="so-here-tolls-detail"></div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="p-2 rounded" style="background:#ecfdf5">
                        <div class="so-hint"><?= __('Sugestia ceny (km × stawka + tolls)') ?></div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <div class="fs-4 fw-bold text-success" id="so-here-price">-</div>
                            <button type="button" class="btn btn-sm btn-outline-success" id="so-here-apply" disabled>
                                <i class="ri-magic-line me-1"></i><?= __('Ustaw jako netto') ?>
                            </button>
                        </div>
                        <div class="so-hint mt-1" id="so-here-price-detail"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WIDGET: Historia stawek + alert dumpingu -->
<div class="col-12" id="so-pricing-wrap" style="display:none">
    <div class="card border-0 shadow-sm so-section-card" id="so-pricing-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 fw-semibold text-uppercase text-muted small so-section-title">
                    <i class="ri-line-chart-line me-1 text-warning"></i> <?= __('Historia stawek dla tej trasy') ?>
                    <span class="badge bg-secondary-subtle text-secondary ms-1" id="so-pricing-mode">-</span>
                </h6>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary active" id="so-pricing-tab-client"><?= __('Ten klient') ?></button>
                    <button type="button" class="btn btn-outline-secondary" id="so-pricing-tab-market"><?= __('Rynek') ?></button>
                </div>
            </div>

            <div id="so-pricing-alert" class="alert py-2 px-3 mb-2 d-none small"></div>

            <div class="row g-3">
                <div class="col-md-3">
                    <div class="p-2 rounded" style="background:#f8fafc">
                        <div class="so-hint"><?= __('Ilość zleceń') ?></div>
                        <div class="fs-5 fw-semibold" id="so-pricing-count">-</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 rounded" style="background:#f8fafc">
                        <div class="so-hint"><?= __('Mediana (PLN)') ?></div>
                        <div class="fs-5 fw-semibold text-primary" id="so-pricing-median">-</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 rounded" style="background:#f8fafc">
                        <div class="so-hint"><?= __('Średnia (PLN)') ?></div>
                        <div class="fs-5 fw-semibold" id="so-pricing-avg">-</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-2 rounded" style="background:#f8fafc">
                        <div class="so-hint"><?= __('Min - Max (PLN)') ?></div>
                        <div class="fs-6 fw-semibold" id="so-pricing-minmax">-</div>
                    </div>
                </div>
            </div>

            <div id="so-pricing-orders" class="mt-2 small"></div>
            <div id="so-pricing-buyers" class="mt-2 small d-none"></div>
        </div>
    </div>
</div>

<!-- SEKCJA 7: Wiecej opcji (accordion) -->
<div class="col-12">
    <div class="accordion accordion-flush border rounded shadow-sm" id="so-more">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed py-2 small fw-semibold text-uppercase text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#so-more-body">
                    <i class="ri-settings-3-line me-2"></i><?= __('Więcej opcji') ?>
                </button>
            </h2>
            <div id="so-more-body" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small text-muted"><?= __('Nasz nr referencyjny') ?></label>
                            <input type="text" name="our_ref" class="form-control" value="<?= h($order->our_ref ?? '') ?>" maxlength="100" placeholder="REF/2026/001">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small text-muted"><?= __('Nazwa miejsca rozładunku (opcjonalnie)') ?></label>
                            <input type="text" name="unload_name" class="form-control" value="<?= h($order->unload_name ?? '') ?>" maxlength="200" placeholder="Magazyn XYZ Sp. z o.o.">
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="mb-2 small text-uppercase text-muted so-section-title"><i class="ri-scales-3-line me-1"></i><?= __('Wymiary / waga ładunku') ?></h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small text-muted"><?= __('Waga (kg)') ?></label>
                            <input type="number" min="0" step="1" name="cargo_weight_kg" class="form-control" value="<?= h($order->cargo_weight_kg ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted"><?= __('Objętość (m³)') ?></label>
                            <input type="number" min="0" step="0.01" name="cargo_volume_m3" class="form-control" value="<?= h($order->cargo_volume_m3 ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted"><?= __('LDM') ?></label>
                            <input type="number" min="0" step="0.1" name="cargo_ldm" class="form-control" value="<?= h($order->cargo_ldm ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted"><?= __('Palety (szt.)') ?></label>
                            <input type="number" min="0" step="1" name="cargo_pallets" class="form-control" value="<?= h($order->cargo_pallets ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted"><?= __('Typ palet') ?></label>
                            <select name="cargo_pallet_type" class="form-select">
                                <option value=""></option>
                                <?php foreach (['EUR','PLA','BOX','DISP','INNE'] as $pt): ?>
                                    <option value="<?= h($pt) ?>" <?= ($order->cargo_pallet_type ?? '') === $pt ? 'selected' : '' ?>><?= h($pt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="mb-2 small text-uppercase text-muted so-section-title"><i class="ri-alarm-warning-line me-1"></i><?= __('ADR / temperatura') ?></h6>
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label small text-muted"><?= __('ADR klasa') ?></label>
                            <select name="adr_class" class="form-select">
                                <option value=""></option>
                                <?php foreach (['1','2','3','4.1','4.2','4.3','5.1','5.2','6.1','6.2','7','8','9'] as $ac): ?>
                                    <option value="<?= h($ac) ?>" <?= ($order->adr_class ?? '') === $ac ? 'selected' : '' ?>><?= h($ac) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted"><?= __('Nr UN') ?></label>
                            <input type="text" name="adr_un" class="form-control" value="<?= h($order->adr_un ?? '') ?>" maxlength="10" placeholder="UN1203">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted"><?= __('Temp. min (°C)') ?></label>
                            <input type="number" step="0.1" name="temperature_min" class="form-control" value="<?= h($order->temperature_min ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted"><?= __('Temp. max (°C)') ?></label>
                            <input type="number" step="0.1" name="temperature_max" class="form-control" value="<?= h($order->temperature_max ?? '') ?>">
                        </div>
                    </div>

                    <hr class="my-3">
                    <h6 class="mb-2 small text-uppercase text-muted so-section-title"><i class="ri-file-text-line me-1"></i><?= __('Warunki + dokumenty') ?></h6>
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label small text-muted"><?= __('INCOTERMS') ?></label>
                            <select name="incoterms" class="form-select">
                                <option value=""></option>
                                <?php foreach (['EXW','FCA','FAS','FOB','CFR','CIF','CPT','CIP','DAP','DPU','DDP'] as $ic): ?>
                                    <option value="<?= h($ic) ?>" <?= ($order->incoterms ?? '') === $ic ? 'selected' : '' ?>><?= h($ic) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted"><?= __('Miejsce INCOTERMS') ?></label>
                            <input type="text" name="incoterms_place" class="form-control" value="<?= h($order->incoterms_place ?? '') ?>" maxlength="100" placeholder="Hamburg, Germany">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted"><?= __('Nr CMR') ?></label>
                            <input type="text" name="cmr_number" class="form-control" value="<?= h($order->cmr_number ?? '') ?>" maxlength="50">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted"><?= __('Ubezp. wartość') ?></label>
                            <input type="number" min="0" step="0.01" name="insurance_value" class="form-control" value="<?= h($order->insurance_value ?? '') ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small text-muted"><?= __('Waluta') ?></label>
                            <input type="text" name="insurance_currency" class="form-control" value="<?= h($order->insurance_currency ?? '') ?>" maxlength="5" placeholder="EUR">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->Form->end() ?>
</div><!-- /so-form-wrap -->

<!-- STICKY ACTION BAR -->
<div class="so-sticky-bar">
    <div class="container-fluid d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div class="text-muted small d-flex align-items-center gap-3 flex-wrap">
            <span id="so-brutto-preview" class="fw-semibold text-dark">-</span>
            <span class="opacity-25">|</span>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="checkbox" name="send_email" id="so-send-email" value="1" form="form-manual-order">
                <label class="form-check-label small" for="so-send-email">
                    <i class="ri-mail-send-line me-1"></i><?= __('Wyślij email do klienta po zapisie') ?>
                </label>
            </div>
            <span class="opacity-25 d-none d-lg-inline">|</span>
            <span class="d-none d-lg-inline"><?= __('Auto-zapis co 30 s') ?></span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary" id="so-btn-cancel">
                <i class="ri-close-line me-1"></i><?= __('Anuluj') ?>
            </a>
            <?php if (!$isEdit): ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" id="so-btn-save-new">
                        <i class="ri-add-line me-1"></i><?= __('Zapisz + kolejne') ?>
                    </button>
                    <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <button type="button" class="dropdown-item" id="so-btn-save-attach">
                                <i class="ri-attachment-2 me-1"></i><?= __('Zapisz + dodaj CMR') ?>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item" id="so-btn-save-invoice">
                                <i class="ri-file-add-line me-1"></i><?= __('Zapisz + wystaw fakturę') ?>
                            </button>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
            <button type="button" class="btn btn-primary fw-semibold" id="so-btn-save">
                <i class="ri-save-line me-1"></i>
                <?= $isEdit ? __('Zapisz zmiany') : __('Utwórz zlecenie') ?>
            </button>
        </div>
    </div>
</div>

<script>
(function(){
    'use strict';

    var $form = document.getElementById('form-manual-order');
    var $saveNewInput = document.getElementById('save_and_new');
    var AUTOSAVE_KEY = <?= json_encode($autosaveKey) ?>;
    var AUTOSAVE_MS = 30000;
    var IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
    var CSRF = <?= json_encode($csrfToken) ?>;

    // ===== VAT auto-calc + brutto preview w sticky bar =====
    var $netto = document.getElementById('fin-netto');
    var $rate  = document.getElementById('fin-vat-rate');
    var $vat   = document.getElementById('fin-vat');
    var $brut  = document.getElementById('fin-brutto');
    var $cur   = document.getElementById('fin-currency');
    var $bpv   = document.getElementById('so-brutto-preview');

    function calc() {
        var n = parseFloat($netto.value) || 0;
        var r = $rate.value;
        var v = 0, b = n;
        if (r === '23' || r === '8' || r === '5' || r === '0') {
            v = Math.round((n * parseFloat(r) / 100) * 100) / 100;
            b = Math.round((n + v) * 100) / 100;
        }
        $vat.value  = v.toFixed(2);
        $brut.value = b.toFixed(2);
        $bpv.textContent = b.toFixed(2) + ' ' + $cur.value;
    }
    $netto.addEventListener('input', calc);
    $rate.addEventListener('change', calc);
    $cur.addEventListener('change', calc);
    calc();

    // ===== Currency change: exchange_rate readonly for PLN =====
    var $rateFx = document.getElementById('fin-rate');
    function onCur() {
        if ($cur.value === 'PLN') {
            $rateFx.value = '1.000000';
            $rateFx.setAttribute('readonly', 'readonly');
        } else {
            $rateFx.removeAttribute('readonly');
        }
    }
    $cur.addEventListener('change', onCur);
    onCur();

    // ===== Skróty klawiszowe =====
    document.addEventListener('keydown', function(e){
        var ctrl = e.ctrlKey || e.metaKey;
        if (ctrl && e.key === 's') {
            e.preventDefault();
            document.getElementById('so-btn-save').click();
        } else if (ctrl && e.key === 'Enter' && !IS_EDIT) {
            e.preventDefault();
            var b = document.getElementById('so-btn-save-new');
            if (b) b.click();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            document.getElementById('so-btn-cancel').click();
        }
    });

    // ===== Sticky bar buttons -> submit =====
    document.getElementById('so-btn-save').addEventListener('click', function(){
        $saveNewInput.value = '';
        $form.submit();
    });
    var $btnSaveNew = document.getElementById('so-btn-save-new');
    if ($btnSaveNew) {
        $btnSaveNew.addEventListener('click', function(){
            $saveNewInput.value = '1';
            $form.submit();
        });
    }
    var $btnSaveAttach = document.getElementById('so-btn-save-attach');
    if ($btnSaveAttach) {
        $btnSaveAttach.addEventListener('click', function(){
            document.getElementById('save_and_attach').value = '1';
            $form.submit();
        });
    }
    var $btnSaveInvoice = document.getElementById('so-btn-save-invoice');
    if ($btnSaveInvoice) {
        $btnSaveInvoice.addEventListener('click', function(){
            document.getElementById('save_and_invoice').value = '1';
            $form.submit();
        });
    }
    document.getElementById('so-btn-cancel').addEventListener('click', function(e){
        if (!confirm('Wpisane dane zostaną utracone. Czy na pewno wyjść?')) {
            e.preventDefault();
        }
    });

    // Wyczysc autosave po udanym submit (nie zapisujemy juz starych danych)
    $form.addEventListener('submit', function(){
        try { localStorage.removeItem(AUTOSAVE_KEY); } catch(e){}
    });

    // ===== Autosave co 30 s =====
    var $indicator = document.getElementById('so-autosave');
    var $indicatorTxt = document.getElementById('so-autosave-txt');
    var isDirty = false;
    $form.addEventListener('input', function(){ isDirty = true; });
    $form.addEventListener('change', function(){ isDirty = true; });

    function autosave() {
        if (!isDirty) return;
        var payload = {};
        var fd = new FormData($form);
        fd.forEach(function(v, k){ payload[k] = v; });
        try {
            localStorage.setItem(AUTOSAVE_KEY, JSON.stringify({ ts: Date.now(), data: payload }));
            $indicatorTxt.textContent = 'Zapisano lokalnie (' + new Date().toLocaleTimeString() + ')';
            $indicator.classList.add('show');
            setTimeout(function(){ $indicator.classList.remove('show'); }, 2000);
            isDirty = false;
        } catch(e){}
    }
    setInterval(autosave, AUTOSAVE_MS);

    // ===== Odzysk po zamknieciu przegladarki =====
    (function tryRecover(){
        if (IS_EDIT) return; // edit ma prawdziwe dane z DB
        try {
            var raw = localStorage.getItem(AUTOSAVE_KEY);
            if (!raw) return;
            var obj = JSON.parse(raw);
            if (!obj || !obj.data) return;
            var age = Math.round((Date.now() - obj.ts) / 60000);
            if (age > 60 * 24) { // > 24h stale
                localStorage.removeItem(AUTOSAVE_KEY);
                return;
            }
            if (!confirm('Znaleziono zapisane dane formularza. Przywrócić? (' + age + ' min temu)?')) {
                localStorage.removeItem(AUTOSAVE_KEY);
                return;
            }
            Object.keys(obj.data).forEach(function(k){
                var el = $form.elements[k];
                if (el && el.type !== 'file' && el.name !== 'symbol') el.value = obj.data[k];
            });
            calc();
        } catch(e){}
    })();

    // ===== Autocomplete kontrahenta =====
    var $search = document.getElementById('buyer-search');
    var $results = document.getElementById('buyer-results');
    var timer = null;

    $search.addEventListener('input', function(){
        clearTimeout(timer);
        var q = $search.value.trim();
        if (q.length < 2) {
            $results.classList.add('d-none');
            $results.innerHTML = '';
            return;
        }
        timer = setTimeout(function(){
            fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'search']) ?>?q=' + encodeURIComponent(q))
                .then(function(r){ return r.json(); })
                .then(function(items){
                    if (!Array.isArray(items) || items.length === 0) {
                        $results.innerHTML = '<div class="list-group-item text-muted small">Brak wyników</div>';
                        $results.classList.remove('d-none');
                        return;
                    }
                    var html = '';
                    items.slice(0, 10).forEach(function(c){
                        html += '<button type="button" class="list-group-item list-group-item-action py-1" ' +
                            'data-nip="' + (c.nip || '') + '" ' +
                            'data-name="' + encodeURIComponent(c.name || '') + '" ' +
                            'data-street="' + encodeURIComponent(c.street || '') + '" ' +
                            'data-zip="' + encodeURIComponent(c.zip || '') + '" ' +
                            'data-city="' + encodeURIComponent(c.city || '') + '" ' +
                            'data-country="' + (c.country || 'PL') + '" ' +
                            'data-email="' + (c.email || '') + '">' +
                            '<strong>' + (c.name || '') + '</strong> ' +
                            '<span class="text-muted small">' + (c.nip ? 'NIP ' + c.nip : '') + ' - ' + (c.city || '') + '</span>' +
                            '</button>';
                    });
                    $results.innerHTML = html;
                    $results.classList.remove('d-none');
                });
        }, 250);
    });

    $results.addEventListener('click', function(e){
        var btn = e.target.closest('button[data-nip]');
        if (!btn) return;
        var nip = btn.dataset.nip || '';
        document.querySelector('input[name="buyer_nip"]').value  = nip;
        document.querySelector('input[name="buyer_name"]').value = decodeURIComponent(btn.dataset.name || '');
        document.querySelector('input[name="buyer_street"]').value = decodeURIComponent(btn.dataset.street || '');
        document.querySelector('input[name="buyer_postal_code"]').value = decodeURIComponent(btn.dataset.zip || '');
        document.querySelector('input[name="buyer_city"]').value = decodeURIComponent(btn.dataset.city || '');
        var $co = document.querySelector('select[name="buyer_country"]');
        if ($co) $co.value = btn.dataset.country || 'PL';
        document.querySelector('input[name="buyer_email"]').value = btn.dataset.email || '';
        $results.classList.add('d-none');
        $search.value = '';
        checkLastForBuyer(nip);
        fetchBuyerProfile(nip);
    });

    // ===== MINI-PROFIL KLIENTA (historia wspolpracy) =====
    var $pfWrap    = document.getElementById('so-profile-wrap');
    var $pfCnt     = document.getElementById('so-profile-cnt');
    var $pfAvg     = document.getElementById('so-profile-avg');
    var $pfSum     = document.getElementById('so-profile-sum');
    var $pfDso     = document.getElementById('so-profile-dso');
    var $pfLast    = document.getElementById('so-profile-last');
    var $pfRoute   = document.getElementById('so-profile-route');
    var $pfRecent  = document.getElementById('so-profile-recent');
    var $pfDetail  = document.getElementById('so-profile-detail');
    var $pfToggle  = document.getElementById('so-profile-toggle');

    $pfToggle.addEventListener('click', function(e){
        e.preventDefault();
        $pfDetail.classList.toggle('d-none');
    });

    function fetchBuyerProfile(nip) {
        var digits = (nip || '').replace(/\D+/g, '');
        if (digits.length < 5) { $pfWrap.classList.add('d-none'); return; }
        fetch('<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'buyerProfileJson']) ?>?nip=' + encodeURIComponent(digits),
              { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.ok || !j.found) { $pfWrap.classList.add('d-none'); return; }
            var s = j.stats;
            $pfCnt.textContent = s.orders_12m;
            $pfAvg.textContent = s.avg_net.toLocaleString('pl-PL', {minimumFractionDigits: 0}) + ' zł';
            $pfSum.textContent = s.sum_net.toLocaleString('pl-PL', {minimumFractionDigits: 0}) + ' zł';
            $pfDso.textContent = s.dso_days !== null ? s.dso_days + ' dni' : '-';
            $pfLast.textContent = s.last_order ? 'Ostatnio: ' + s.last_order : '';
            $pfRoute.innerHTML = s.top_route
                ? '<strong>TOP trasa:</strong> ' + s.top_route + ' (' + s.top_route_cnt + 'x)'
                : '';
            var recent = (j.recent || []).map(function(o){
                return '<a href="/zlecenia/view/' + o.id + '" target="_blank" class="badge bg-white border text-dark me-1 mb-1 text-decoration-none">' +
                       '<code>' + o.symbol + '</code> · ' + o.date_doc + ' · ' + o.route + ' · <strong>' +
                       o.amount.toLocaleString('pl-PL') + ' ' + o.currency + '</strong></a>';
            }).join(' ');
            $pfRecent.innerHTML = recent
                ? '<div class="so-hint mb-1">Ostatnie zlecenia:</div>' + recent
                : '';
            $pfWrap.classList.remove('d-none');
        }).catch(function(){ $pfWrap.classList.add('d-none'); });
    }

    // ===== Prefill z ostatniego zlecenia klienta =====
    var $lcBox = document.getElementById('so-lastclient-box');
    var $lcInfo = document.getElementById('so-lastclient-info');
    var $lcUse = document.getElementById('so-lastclient-use');
    var $lcClose = document.getElementById('so-lastclient-close');
    var lastOrderData = null;
    var lastNipChecked = '';

    function checkLastForBuyer(nip) {
        if (IS_EDIT) return; // w edycji nie proponujemy prefilla
        var digits = (nip || '').replace(/\D+/g, '');
        if (digits.length < 5 || digits === lastNipChecked) return;
        lastNipChecked = digits;
        fetch('<?= $this->Url->build(['action' => 'lastForBuyerJson']) ?>?nip=' + encodeURIComponent(digits))
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.ok || !j.found) { $lcBox.classList.add('d-none'); return; }
                lastOrderData = j.order;
                var info = j.order.symbol + ' (' + (j.order.date_doc || '') + ') — ' +
                    (j.order.load_city || '') + ' -> ' + (j.order.unload_city || '') +
                    ' — ' + (j.order.netto || 0).toFixed(2) + ' ' + (j.order.currency || '');
                $lcInfo.textContent = info;
                $lcBox.classList.remove('d-none');
            })
            .catch(function(){});
    }

    var $nipInput = document.getElementById('buyer-nip');
    $nipInput.addEventListener('blur', function(){ checkLastForBuyer($nipInput.value); fetchBuyerProfile($nipInput.value); });
    // Sprawdz od razu jesli NIP juz jest wypelniony (np. duplikat)
    if ($nipInput.value.trim()) { checkLastForBuyer($nipInput.value); fetchBuyerProfile($nipInput.value); }

    $lcUse.addEventListener('click', function(){
        if (!lastOrderData) return;
        var fields = ['contract','load_country','load_postal_code','load_city',
                      'unload_country','unload_city','unload_name','title2','cargo_type',
                      'transport_type','currency','payment_terms'];
        fields.forEach(function(f){
            var el = $form.elements[f];
            if (el && lastOrderData[f] !== null && lastOrderData[f] !== '') el.value = lastOrderData[f];
        });
        if ($form.elements.netto && lastOrderData.netto) $form.elements.netto.value = lastOrderData.netto;
        calc();
        onCur();
        $lcBox.classList.add('d-none');
    });
    $lcClose.addEventListener('click', function(){ $lcBox.classList.add('d-none'); });

    // =====================================================================
    // HERE AUTOCOMPLETE MIAST (load_city, unload_city, buyer_city)
    // =====================================================================
    function attachCityAutocomplete(inputName, countryFieldName, postalFieldName) {
        var $inp = $form.elements[inputName];
        if (!$inp) return;
        // Wrapper dropdownu
        var wrap = document.createElement('div');
        wrap.style.cssText = 'position:absolute;z-index:1050;background:#fff;border:1px solid #d1d5db;border-radius:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);max-height:240px;overflow-y:auto;min-width:260px;display:none';
        document.body.appendChild(wrap);
        var timer = null;

        function positionDropdown() {
            var r = $inp.getBoundingClientRect();
            wrap.style.left = (r.left + window.scrollX) + 'px';
            wrap.style.top  = (r.bottom + window.scrollY + 2) + 'px';
            wrap.style.width = r.width + 'px';
        }

        function render(items) {
            if (!items || !items.length) { wrap.style.display = 'none'; return; }
            var html = '';
            items.slice(0, 8).forEach(function(it, idx){
                var city = it.city || it.title || '';
                var zip  = it.postal_code || '';
                var cc   = it.country || '';
                html += '<div class="so-city-opt py-1 px-2" data-idx="' + idx + '" ' +
                        'style="cursor:pointer;border-bottom:1px solid #f3f4f6">' +
                        '<div><strong>' + city + '</strong> ' +
                        (cc ? '<span class="badge bg-secondary-subtle text-secondary" style="font-size:.65rem">' + cc + '</span>' : '') +
                        '</div>' +
                        '<div class="so-hint">' + (it.label || '') + (zip ? ' · ' + zip : '') + '</div>' +
                        '</div>';
            });
            wrap.innerHTML = html;
            wrap.dataset.items = JSON.stringify(items);
            positionDropdown();
            wrap.style.display = 'block';
        }

        $inp.addEventListener('input', function(){
            clearTimeout(timer);
            var q = $inp.value.trim();
            if (q.length < 2) { wrap.style.display = 'none'; return; }
            timer = setTimeout(function(){
                fetch('<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'citiesJson']) ?>?q=' + encodeURIComponent(q))
                    .then(function(r){ return r.json(); })
                    .then(function(j){ if (j.ok) render(j.items || []); })
                    .catch(function(){});
            }, 250);
        });

        $inp.addEventListener('blur', function(){
            // Delay zeby click w dropdown zdazyl zadzialac
            setTimeout(function(){ wrap.style.display = 'none'; }, 150);
        });

        wrap.addEventListener('mousedown', function(e){
            var opt = e.target.closest('.so-city-opt');
            if (!opt) return;
            var items = JSON.parse(wrap.dataset.items || '[]');
            var it = items[parseInt(opt.dataset.idx, 10)];
            if (!it) return;
            $inp.value = it.city || it.title || $inp.value;
            if (countryFieldName && $form.elements[countryFieldName] && it.country) {
                $form.elements[countryFieldName].value = it.country;
            }
            if (postalFieldName && $form.elements[postalFieldName] && it.postal_code) {
                $form.elements[postalFieldName].value = it.postal_code;
            }
            wrap.style.display = 'none';
            // Trigger change zeby zadzialal pricingHistory / autosave
            $inp.dispatchEvent(new Event('change', { bubbles: true }));
        });

        window.addEventListener('resize', positionDropdown);
        window.addEventListener('scroll', positionDropdown, true);
    }
    attachCityAutocomplete('load_city',    'load_country',   'load_postal_code');
    attachCityAutocomplete('unload_city',  'unload_country', null);
    attachCityAutocomplete('buyer_city',   'buyer_country',  'buyer_postal_code');

    // =====================================================================
    // GUS LOOKUP po NIP (PL, 10 cyfr) -> prefill danych firmy
    // =====================================================================
    var $gusBtn = document.getElementById('so-btn-gus');
    var $gusMsg = document.getElementById('so-gus-msg');

    function doGusLookup(nip) {
        var digits = (nip || '').replace(/\D+/g, '');
        if (digits.length !== 10) {
            $gusMsg.innerHTML = '<span class="text-danger">NIP musi mieć dokładnie 10 cyfr (PL)</span>';
            return;
        }
        $gusMsg.innerHTML = '<span class="text-muted"><i class="ri-loader-4-line spin"></i> Pobieram z GUS…</span>';
        var fd = new FormData();
        fd.append('nip', digits);
        fd.append('_csrfToken', CSRF);
        fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'gusLookup']) ?>', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.success) {
                $gusMsg.innerHTML = '<span class="text-danger">' + (j.message || 'Błąd pobierania z GUS') + '</span>';
                return;
            }
            var c = j.contractor;
            if (c.name)   $form.elements.buyer_name.value = c.name;
            if (c.street) $form.elements.buyer_street.value = c.street;
            if (c.zip)    $form.elements.buyer_postal_code.value = c.zip;
            if (c.city)   $form.elements.buyer_city.value = c.city;
            if ($form.elements.buyer_country) $form.elements.buyer_country.value = 'PL';
            var vatBadge = j.vat && j.vat.statusVat === 'Czynny'
                ? '<span class="badge bg-success-subtle text-success ms-1">VAT czynny</span>'
                : (j.vat && j.vat.statusVat
                    ? '<span class="badge bg-warning-subtle text-warning ms-1">VAT ' + j.vat.statusVat + '</span>'
                    : '');
            $gusMsg.innerHTML = '<span class="text-success"><i class="ri-check-line"></i> Pobrano z GUS</span> ' + vatBadge +
                ' <button type="button" class="btn btn-link btn-sm p-0 ms-1" id="so-save-contractor" style="font-size:.72rem">' +
                '<i class="ri-add-line"></i> Zapisz jako kontrahenta</button>';
            document.getElementById('so-save-contractor').addEventListener('click', function(){
                var btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="ri-loader-4-line spin"></i> zapisuje...';
                var cfd = new FormData();
                cfd.append('_csrfToken', CSRF);
                cfd.append('nip',         c.nip || '');
                cfd.append('name',        c.name || '');
                cfd.append('street',      c.street || '');
                cfd.append('postal_code', c.zip || '');
                cfd.append('city',        c.city || '');
                cfd.append('country',     'PL');
                fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'add']) ?>', {
                    method: 'POST',
                    body: cfd,
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                })
                .then(function(r){ return r.json(); })
                .then(function(cj){
                    if (cj.success !== false) {
                        btn.innerHTML = '<i class="ri-check-line text-success"></i> zapisano w bazie';
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ri-error-warning-line text-danger"></i> ' + (cj.message || 'blad');
                    }
                })
                .catch(function(){
                    btn.disabled = false;
                    btn.innerHTML = '<i class="ri-error-warning-line text-danger"></i> blad';
                });
            });
            checkLastForBuyer(digits);
            schedulePricingCheck();
        })
        .catch(function(){
            $gusMsg.innerHTML = '<span class="text-danger">Błąd pobierania z GUS</span>';
        });
    }
    $gusBtn.addEventListener('click', function(){ doGusLookup($nipInput.value); });

    // =====================================================================
    // LIVE PRICING HISTORY + ALERT DUMPINGU
    // =====================================================================
    var $pWrap    = document.getElementById('so-pricing-wrap');
    var $pMode    = document.getElementById('so-pricing-mode');
    var $pAlert   = document.getElementById('so-pricing-alert');
    var $pCount   = document.getElementById('so-pricing-count');
    var $pMedian  = document.getElementById('so-pricing-median');
    var $pAvg     = document.getElementById('so-pricing-avg');
    var $pMinmax  = document.getElementById('so-pricing-minmax');
    var $pOrders  = document.getElementById('so-pricing-orders');
    var $pBuyers  = document.getElementById('so-pricing-buyers');
    var $tabClient = document.getElementById('so-pricing-tab-client');
    var $tabMarket = document.getElementById('so-pricing-tab-market');

    var pricingMode = 'client';
    var pricingTimer = null;

    function schedulePricingCheck() {
        clearTimeout(pricingTimer);
        pricingTimer = setTimeout(fetchPricing, 400);
    }

    function fetchPricing() {
        var fromCity    = $form.elements.load_city.value.trim();
        var toCity      = $form.elements.unload_city.value.trim();
        var fromCountry = $form.elements.load_country.value.trim();
        var toCountry   = $form.elements.unload_country.value.trim();
        var buyerNip    = $form.elements.buyer_nip.value.trim();
        if (!fromCity && !toCity && !fromCountry && !toCountry) {
            $pWrap.style.display = 'none';
            return;
        }
        var payload = new FormData();
        payload.append('_csrfToken', CSRF);
        if (pricingMode === 'client' && buyerNip) payload.append('contractor_nip', buyerNip);
        if (fromCity)    payload.append('from_city', fromCity);
        if (toCity)      payload.append('to_city', toCity);
        if (fromCountry) payload.append('from_country', fromCountry);
        if (toCountry)   payload.append('to_country', toCountry);

        fetch('<?= $this->Url->build(['controller' => 'RoutePlanner', 'action' => 'pricingHistory']) ?>', {
            method: 'POST',
            body: payload,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.ok) { $pWrap.style.display = 'none'; return; }
            renderPricing(j);
        })
        .catch(function(){ $pWrap.style.display = 'none'; });
    }

    function fmtPln(n) {
        return (Math.round(Number(n || 0) * 100) / 100).toLocaleString('pl-PL', {minimumFractionDigits: 2});
    }

    function renderPricing(j) {
        var stats  = j.stats || {};
        var orders = j.orders || [];
        var buyers = j.by_buyer || [];
        var count  = stats.count || 0;
        if (count === 0) {
            $pWrap.style.display = 'block';
            $pMode.textContent = j.mode === 'market' ? 'rynek' : 'ten klient';
            $pAlert.className = 'alert alert-secondary py-2 px-3 mb-2 small';
            $pAlert.innerHTML = '<i class="ri-information-line me-1"></i>' + (j.match_label || 'Brak danych historycznych');
            $pAlert.classList.remove('d-none');
            $pCount.textContent = '0';
            $pMedian.textContent = '-';
            $pAvg.textContent = '-';
            $pMinmax.textContent = '-';
            $pOrders.innerHTML = '';
            $pBuyers.classList.add('d-none');
            return;
        }
        $pWrap.style.display = 'block';
        $pMode.textContent = j.mode === 'market' ? 'rynek' : 'ten klient';
        $pCount.textContent = count;
        $pMedian.textContent = fmtPln(stats.median);
        $pAvg.textContent    = fmtPln(stats.avg);
        $pMinmax.textContent = fmtPln(stats.min) + ' - ' + fmtPln(stats.max);

        // Alert dumpingu - porownaj aktualna cene (PLN) z mediana
        var netto   = parseFloat($form.elements.netto.value) || 0;
        var currency = $form.elements.currency.value;
        var rate    = parseFloat($form.elements.exchange_rate.value) || 1;
        var pln     = currency === 'PLN' ? netto : netto * rate;
        var median  = Number(stats.median || 0);

        if (pln > 0 && median > 0) {
            var ratio = pln / median;
            if (ratio < 0.90) {
                $pAlert.className = 'alert alert-danger py-2 px-3 mb-2 small';
                $pAlert.innerHTML = '<i class="ri-alert-line me-1"></i><strong>DUMPING</strong> — ' +
                    Math.round(ratio * 100) + '% mediany (' + fmtPln(median) + ' PLN). DUMPINGHINT_';
            } else if (ratio > 1.10) {
                $pAlert.className = 'alert alert-success py-2 px-3 mb-2 small';
                $pAlert.innerHTML = '<i class="ri-trophy-line me-1"></i>' + Math.round(ratio * 100) +
                    '% mediany (' + fmtPln(median) + ' PLN) — powyżej rynku, świetnie!';
            } else {
                $pAlert.className = 'alert alert-primary py-2 px-3 mb-2 small';
                $pAlert.innerHTML = '<i class="ri-check-line me-1"></i>Cena zgodna z rynkiem (' +
                    Math.round(ratio * 100) + '% mediany).';
            }
            $pAlert.classList.remove('d-none');
        } else {
            $pAlert.classList.add('d-none');
        }

        // Lista ostatnich zlecen (top 5)
        var oh = '<div class="fw-semibold text-muted small mb-1">Ostatnie zlecenia:</div><ul class="list-unstyled small mb-0">';
        orders.slice(0, 5).forEach(function(o){
            var price = o.invoice && o.invoice.total_pln
                ? fmtPln(o.invoice.total_pln) + ' PLN'
                : (o.invoice ? (fmtPln(o.invoice.amount) + ' ' + (o.invoice.currency || '')) : '-');
            var buyer = j.mode === 'market' && o.buyer_name ? '<span class="text-muted">' + o.buyer_name + '</span> — ' : '';
            oh += '<li>' + buyer + '<code>' + (o.symbol || '') + '</code> ' + (o.date_doc || '') +
                ' — <strong>' + price + '</strong></li>';
        });
        oh += '</ul>';
        $pOrders.innerHTML = oh;

        // TOP klienci (tylko market mode)
        if (j.mode === 'market' && buyers.length > 0) {
            var bh = '<div class="fw-semibold text-muted small mb-1">TOP klienci na tej trasie:</div><div class="d-flex flex-wrap gap-2">';
            buyers.slice(0, 5).forEach(function(b){
                bh += '<span class="badge bg-light text-dark border">' +
                    '<strong>' + (b.buyer_name || '(brak)') + '</strong> — ' + b.count + 'x, avg ' +
                    fmtPln(b.avg_pln) + ' PLN</span>';
            });
            bh += '</div>';
            $pBuyers.innerHTML = bh;
            $pBuyers.classList.remove('d-none');
        } else {
            $pBuyers.classList.add('d-none');
        }
    }

    // Trigger na zmianie kluczowych pol
    ['load_city','unload_city','load_country','unload_country','buyer_nip'].forEach(function(f){
        if ($form.elements[f]) {
            $form.elements[f].addEventListener('change', schedulePricingCheck);
            $form.elements[f].addEventListener('blur', schedulePricingCheck);
        }
    });
    // Netto/kurs/waluta zmiana -> re-render alertu dumpingu (bez nowego fetcha)
    ['netto','exchange_rate','currency'].forEach(function(f){
        if ($form.elements[f]) $form.elements[f].addEventListener('change', schedulePricingCheck);
    });

    // Tabs client/market
    $tabClient.addEventListener('click', function(){
        pricingMode = 'client';
        $tabClient.classList.add('active');
        $tabMarket.classList.remove('active');
        fetchPricing();
    });
    $tabMarket.addEventListener('click', function(){
        pricingMode = 'market';
        $tabMarket.classList.add('active');
        $tabClient.classList.remove('active');
        fetchPricing();
    });

    // Initial check jesli trasy juz sa (edycja lub duplikat)
    if ($form.elements.load_city.value || $form.elements.unload_city.value) {
        setTimeout(fetchPricing, 500);
        setTimeout(fetchHereRoute, 800);
    }

    // =====================================================================
    // LIVE KALKULATOR HERE (km, duration, tolls, sugestia ceny)
    // =====================================================================
    var $hWrap     = document.getElementById('so-here-wrap');
    var $hStatus   = document.getElementById('so-here-status');
    var $hKm       = document.getElementById('so-here-km');
    var $hTime     = document.getElementById('so-here-time');
    var $hTolls    = document.getElementById('so-here-tolls');
    var $hTollsD   = document.getElementById('so-here-tolls-detail');
    var $hPrice    = document.getElementById('so-here-price');
    var $hPriceD   = document.getElementById('so-here-price-detail');
    var $hApply    = document.getElementById('so-here-apply');
    var $hAlert    = document.getElementById('so-here-alert');
    var $hRate     = document.getElementById('so-here-rate');
    var hereTimer  = null;
    var hereLastResult = null;

    function scheduleHereRoute() {
        clearTimeout(hereTimer);
        hereTimer = setTimeout(fetchHereRoute, 800);
    }

    function fmtMin(min) {
        if (!min) return '-';
        var h = Math.floor(min / 60);
        var m = min % 60;
        return (h > 0 ? h + 'h ' : '') + m + 'm';
    }

    function fetchHereRoute() {
        var fromCity    = $form.elements.load_city.value.trim();
        var toCity      = $form.elements.unload_city.value.trim();
        var fromCountry = $form.elements.load_country.value.trim();
        var toCountry   = $form.elements.unload_country.value.trim();
        var currency    = $form.elements.currency.value;
        var rate        = parseFloat($hRate.value) || 1.20;

        if (!fromCity || !toCity) {
            $hWrap.style.display = 'none';
            return;
        }

        $hWrap.style.display = 'block';
        $hStatus.innerHTML = '<i class="ri-loader-4-line spin"></i> obliczam…';
        $hAlert.classList.add('d-none');

        var fd = new FormData();
        fd.append('_csrfToken', CSRF);
        fd.append('from_city', fromCity);
        fd.append('to_city', toCity);
        if (fromCountry) fd.append('from_country', fromCountry);
        if (toCountry)   fd.append('to_country', toCountry);
        fd.append('currency', currency);
        fd.append('rate_per_km', rate);

        fetch('<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'routeCalcJson']) ?>', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            $hStatus.textContent = '';
            if (!j.ok) {
                $hAlert.className = 'alert alert-warning py-2 px-3 mb-2 small';
                $hAlert.textContent = j.error || 'Nie udalo sie obliczyc trasy';
                $hAlert.classList.remove('d-none');
                $hApply.disabled = true;
                return;
            }
            hereLastResult = j;
            $hKm.textContent   = j.distance_km.toLocaleString('pl-PL') + ' km';
            $hTime.textContent = fmtMin(j.duration_min);
            $hTolls.textContent = j.tolls_total_eur.toFixed(2) + ' €';
            var tollsD = Object.keys(j.tolls_by_country || {}).map(function(c){
                return c + ': ' + j.tolls_by_country[c].toFixed(2) + ' €';
            }).join(', ');
            $hTollsD.textContent = tollsD;
            $hPrice.textContent = j.suggested_price.toLocaleString('pl-PL') + ' ' + j.suggested_currency;
            $hPriceD.textContent = j.distance_km + ' km × ' + j.rate_per_km + ' € + ' +
                j.tolls_total_eur.toFixed(2) + ' € tolls (car mode, orientacyjnie)';
            $hApply.disabled = false;

            // Automatyczna sugestia w polu netto tylko jesli puste (nie nadpisujemy)
            if (!$form.elements.netto.value || parseFloat($form.elements.netto.value) === 0) {
                $hAlert.className = 'alert alert-info py-2 px-3 mb-2 small';
                $hAlert.innerHTML = '<i class="ri-lightbulb-line me-1"></i>Pole "Netto" jest puste — kliknij <strong>Ustaw jako netto</strong> zeby uzyc sugestii.';
                $hAlert.classList.remove('d-none');
            }
        })
        .catch(function(e){
            $hStatus.textContent = '';
            $hAlert.className = 'alert alert-danger py-2 px-3 mb-2 small';
            $hAlert.textContent = 'Blad HERE: ' + e.message;
            $hAlert.classList.remove('d-none');
            $hApply.disabled = true;
        });
    }

    $hApply.addEventListener('click', function(){
        if (!hereLastResult) return;
        $form.elements.netto.value = hereLastResult.suggested_price;
        calc(); // przelicz VAT/brutto
        schedulePricingCheck(); // odswiez alert dumpingu
        $hAlert.className = 'alert alert-success py-2 px-3 mb-2 small';
        $hAlert.innerHTML = '<i class="ri-check-line me-1"></i>Netto ustawione na ' +
            hereLastResult.suggested_price + ' ' + hereLastResult.suggested_currency;
        $hAlert.classList.remove('d-none');
    });

    $hRate.addEventListener('change', scheduleHereRoute);

    // Trigger HERE po zmianie trasy
    ['load_city','unload_city','load_country','unload_country','currency'].forEach(function(f){
        if ($form.elements[f]) {
            $form.elements[f].addEventListener('change', scheduleHereRoute);
        }
    });

    // =====================================================================
    // ZALADUJ Z PLANERA TRAS
    // =====================================================================
    var $planModal = document.getElementById('so-plan-modal');
    var $planStatus = document.getElementById('so-plan-status');
    var $planTbody = document.getElementById('so-plan-tbody');
    var plansData = [];

    if ($planModal) {
        $planModal.addEventListener('shown.bs.modal', function(){
            if (plansData.length > 0) return; // cached
            $planStatus.className = 'alert alert-info py-2 px-3 small';
            $planStatus.textContent = 'Ładowanie planów...';
            fetch('<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'routePlansJson']) ?>', {
                credentials: 'same-origin', headers: { 'Accept': 'application/json' }
            })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.ok) {
                    $planStatus.className = 'alert alert-danger py-2 px-3 small';
                    $planStatus.textContent = j.error || 'Błąd ładowania';
                    return;
                }
                plansData = j.plans || [];
                if (plansData.length === 0) {
                    $planStatus.className = 'alert alert-warning py-2 px-3 small';
                    $planStatus.textContent = 'Brak zapisanych planów tras. Utwórz plan w /planer-tras.';
                    return;
                }
                $planStatus.className = 'alert alert-success py-2 px-3 small';
                $planStatus.textContent = 'Znaleziono ' + plansData.length + ' planów. Kliknij na plan aby załadować.';
                var html = '';
                plansData.forEach(function(p, idx){
                    var statusBadge = {
                        'draft': 'secondary', 'offered': 'info', 'accepted': 'success',
                        'rejected': 'danger', 'converted': 'primary', 'archived': 'dark'
                    }[p.status] || 'secondary';
                    var price = p.accepted_price || p.suggested_price;
                    html += '<tr>' +
                        '<td>' + (p.name || '') + '</td>' +
                        '<td><span class="badge bg-' + statusBadge + '-subtle text-' + statusBadge + '">' + p.status + '</span></td>' +
                        '<td class="small">' + (p.route || '-') + '</td>' +
                        '<td>' + (p.distance_km !== null ? p.distance_km + ' km' : '-') + '</td>' +
                        '<td>' + (price ? price.toLocaleString('pl-PL') + ' ' + (p.currency || 'PLN') : '-') + '</td>' +
                        '<td class="small">' + (p.contractor_name || '<span class="text-muted">-</span>') + '</td>' +
                        '<td class="small text-muted">' + (p.created || '') + '</td>' +
                        '<td><button type="button" class="btn btn-sm btn-primary so-plan-pick" data-idx="' + idx + '"><i class="ri-download-2-line me-1"></i>Załaduj</button></td>' +
                        '</tr>';
                });
                $planTbody.innerHTML = html;
            });
        });
    }

    document.addEventListener('click', function(e){
        var btn = e.target.closest('.so-plan-pick');
        if (!btn) return;
        var p = plansData[parseInt(btn.dataset.idx, 10)];
        if (!p) return;
        // Prefill z planu
        var wp = p.waypoints || [];
        var first = wp[0] || {};
        var last  = wp[wp.length - 1] || {};
        if (first.city && $form.elements.load_city && !$form.elements.load_city.value) {
            $form.elements.load_city.value = first.city;
        }
        if (first.country && $form.elements.load_country) {
            $form.elements.load_country.value = first.country;
        }
        if (last.city && $form.elements.unload_city && !$form.elements.unload_city.value) {
            $form.elements.unload_city.value = last.city;
        }
        if (last.country && $form.elements.unload_country) {
            $form.elements.unload_country.value = last.country;
        }
        if (p.planned_start_at && $form.elements.date_deadline) {
            $form.elements.date_deadline.value = p.planned_start_at.replace(' ', 'T');
        }
        if (p.planned_end_at && $form.elements.date_delivery) {
            $form.elements.date_delivery.value = p.planned_end_at.replace(' ', 'T');
        }
        var price = p.accepted_price || p.suggested_price;
        if (price && $form.elements.netto && !parseFloat($form.elements.netto.value)) {
            $form.elements.netto.value = price;
            if (p.currency) $form.elements.currency.value = p.currency;
        }
        if (p.contractor_nip && $form.elements.buyer_nip && !$form.elements.buyer_nip.value) {
            $form.elements.buyer_nip.value = p.contractor_nip;
        }
        if (p.contractor_name && $form.elements.buyer_name && !$form.elements.buyer_name.value) {
            $form.elements.buyer_name.value = p.contractor_name;
        }
        calc();
        onCur();
        schedulePricingCheck();
        scheduleHereRoute();
        scheduleConflictCheck();
        var m = bootstrap.Modal.getInstance($planModal);
        if (m) m.hide();
    });

    // =====================================================================
    // WARNINGS: kolizja grafika kierowcy/pojazdu
    // =====================================================================
    var $conflictWrap  = document.getElementById('so-conflict-wrap');
    var $conflictAlert = document.getElementById('so-conflict-alert');
    var conflictTimer  = null;

    function scheduleConflictCheck() {
        clearTimeout(conflictTimer);
        conflictTimer = setTimeout(fetchConflicts, 600);
    }

    function fetchConflicts() {
        var driver   = ($form.elements.driver.value || '').trim();
        var vehicle  = ($form.elements.vehicle_reg.value || '').trim();
        var start    = $form.elements.date_deadline.value;
        var end      = $form.elements.date_delivery.value;
        var buyerNip = ($form.elements.buyer_nip.value || '').trim();
        var loadCity = ($form.elements.load_city.value || '').trim();

        // Fire jesli mamy okno + (driver | vehicle | (buyer+load dla duplikatu))
        if (!start || !end) { $conflictWrap.style.display = 'none'; return; }
        if (!driver && !vehicle && !(buyerNip && loadCity)) {
            $conflictWrap.style.display = 'none'; return;
        }

        var fd = new FormData();
        fd.append('_csrfToken', CSRF);
        if (driver)   fd.append('driver_name', driver);
        if (vehicle)  fd.append('vehicle_plate', vehicle);
        if (buyerNip) fd.append('buyer_nip', buyerNip);
        if (loadCity) fd.append('load_city', loadCity);
        fd.append('start', start);
        fd.append('end', end);

        fetch('<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'conflictCheckJson']) ?>', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.ok) { $conflictWrap.style.display = 'none'; return; }

            var parts = [];

            // 1. Kolizje grafika
            if (j.has_conflicts) {
                var h = '<div class="fw-semibold"><i class="ri-alert-line me-1"></i>Wykryto kolizje grafika (' + j.conflicts.length + '):</div><ul class="mb-0 mt-1 ps-3">';
                j.conflicts.forEach(function(c){
                    var label = c.kind === 'driver' ? 'Kierowca' : 'Pojazd';
                    var lnk = c.linked ? ' → <span class="text-muted">' + c.linked + '</span>' : '';
                    h += '<li>' + label + ' <strong>' + c.entity + '</strong> — ' + c.entry_type +
                         ' (' + c.starts_at + ' → ' + c.ends_at + ')' + lnk + '</li>';
                });
                h += '</ul>';
                parts.push({ level: 'warning', html: h });
            } else if (driver || vehicle) {
                parts.push({ level: 'success', html: '<i class="ri-check-line me-1"></i><strong>Grafik wolny</strong> — kierowca/pojazd dostepny w tym oknie.' });
            }

            // 2. Compliance issues
            if (j.has_compliance) {
                var h = '<div class="fw-semibold"><i class="ri-shield-cross-line me-1"></i>Compliance (' + j.compliance_issues.length + '):</div><ul class="mb-0 mt-1 ps-3">';
                j.compliance_issues.forEach(function(c){
                    h += '<li>' + c.msg + '</li>';
                });
                h += '</ul>';
                parts.push({ level: 'danger', html: h });
            }

            // 3. Duplikat hint
            if (j.duplicate_hint) {
                var d = j.duplicate_hint;
                var h = '<i class="ri-file-copy-line me-1"></i><strong>Mozliwy duplikat:</strong> ' +
                        '<a href="/zlecenia/view/' + d.id + '" target="_blank">' + d.symbol + '</a>' +
                        ' (' + (d.date_doc || '') + ') dla <strong>' + (d.buyer_name || '') + '</strong>' +
                        ' — trasa: ' + (d.route || '');
                parts.push({ level: 'info', html: h });
            }

            if (parts.length === 0) {
                $conflictWrap.style.display = 'none';
                return;
            }

            // Render wszystkich czesci - najwyzszy severity wygrywa dla ramki
            var highestLevel = 'success';
            var order = ['success','info','warning','danger'];
            parts.forEach(function(p){ if (order.indexOf(p.level) > order.indexOf(highestLevel)) highestLevel = p.level; });
            var html = parts.map(function(p){
                return '<div class="mb-1">' + p.html + '</div>';
            }).join('');
            $conflictWrap.style.display = 'block';
            $conflictAlert.className = 'alert alert-' + highestLevel + ' py-2 px-3 mb-0 small';
            $conflictAlert.innerHTML = html;
        })
        .catch(function(){ $conflictWrap.style.display = 'none'; });
    }

    // Trigger po zmianie: kierowcy/pojazdu/dat + buyer_nip/load_city (dla duplikatu)
    ['driver','vehicle_reg','date_deadline','date_delivery','buyer_nip','load_city'].forEach(function(f){
        if ($form.elements[f]) {
            $form.elements[f].addEventListener('change', scheduleConflictCheck);
        }
    });

    // =====================================================================
    // WOLNE ZASOBY w oknie czasowym
    // =====================================================================
    var $freeBtn      = document.getElementById('so-free-btn');
    var $freeStatus   = document.getElementById('so-free-status');
    var $freeDrivers  = document.getElementById('so-free-drivers');
    var $freeVehicles = document.getElementById('so-free-vehicles');

    $freeBtn.addEventListener('click', function(){
        var start = $form.elements.date_deadline.value;
        var end   = $form.elements.date_delivery.value;
        if (!start || !end) {
            alert('Ustaw najpierw daty załadunku i rozładunku');
            return;
        }
        var modal = new bootstrap.Modal(document.getElementById('so-free-modal'));
        modal.show();
        $freeStatus.className = 'alert alert-info py-2 px-3 small';
        $freeStatus.textContent = 'Ładowanie...';
        $freeDrivers.innerHTML = '';
        $freeVehicles.innerHTML = '';

        fetch('<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'freeResourcesJson']) ?>' +
              '?start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end),
              { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.ok) {
                $freeStatus.className = 'alert alert-danger py-2 px-3 small';
                $freeStatus.textContent = j.error || 'Blad';
                return;
            }
            $freeStatus.className = 'alert alert-success py-2 px-3 small';
            $freeStatus.innerHTML = 'Okno: <strong>' + start + '</strong> → <strong>' + end + '</strong>. Znaleziono ' +
                j.drivers.length + ' kierowców i ' + j.vehicles.length + ' pojazdów.';
            if (j.drivers.length === 0) {
                $freeDrivers.innerHTML = '<div class="text-muted small p-2">Brak wolnych kierowców</div>';
            } else {
                j.drivers.forEach(function(d){
                    var adr = d.adr_certified ? '<span class="badge bg-warning-subtle text-warning ms-1">ADR</span>' : '';
                    $freeDrivers.insertAdjacentHTML('beforeend',
                        '<button type="button" class="list-group-item list-group-item-action py-2 so-free-pick-driver" data-name="' +
                        d.full_name.replace(/"/g, '&quot;') + '">' +
                        '<strong>' + d.full_name + '</strong>' + adr +
                        (d.phone ? ' <span class="text-muted small">' + d.phone + '</span>' : '') +
                        '</button>');
                });
            }
            if (j.vehicles.length === 0) {
                $freeVehicles.innerHTML = '<div class="text-muted small p-2">Brak wolnych pojazdów</div>';
            } else {
                j.vehicles.forEach(function(v){
                    $freeVehicles.insertAdjacentHTML('beforeend',
                        '<button type="button" class="list-group-item list-group-item-action py-2 so-free-pick-vehicle" data-plate="' +
                        (v.plate || '').replace(/"/g, '&quot;') + '">' +
                        '<strong>' + (v.plate || '?') + '</strong> — ' + v.name +
                        (v.type ? ' <span class="badge bg-secondary-subtle text-secondary ms-1">' + v.type + '</span>' : '') +
                        '</button>');
                });
            }
        });
    });

    document.addEventListener('click', function(e){
        var dBtn = e.target.closest('.so-free-pick-driver');
        if (dBtn) {
            $form.elements.driver.value = dBtn.dataset.name;
            scheduleConflictCheck();
            var m = bootstrap.Modal.getInstance(document.getElementById('so-free-modal'));
            if (m) m.hide();
            return;
        }
        var vBtn = e.target.closest('.so-free-pick-vehicle');
        if (vBtn) {
            $form.elements.vehicle_reg.value = vBtn.dataset.plate;
            scheduleConflictCheck();
            var m = bootstrap.Modal.getInstance(document.getElementById('so-free-modal'));
            if (m) m.hide();
        }
    });

    // =====================================================================
    // AI PARSER emaila / screenshot -> auto-fill formularza
    // =====================================================================
    var $aiText    = document.getElementById('so-ai-text');
    var $aiImage   = document.getElementById('so-ai-image');
    var $aiPreview = document.getElementById('so-ai-preview');
    var $aiStatus  = document.getElementById('so-ai-status');
    var $aiResult  = document.getElementById('so-ai-result');
    var $aiConf    = document.getElementById('so-ai-conf');
    var $aiNote    = document.getElementById('so-ai-note');
    var $aiSummary = document.getElementById('so-ai-summary');
    var $aiBtn     = document.getElementById('so-ai-btn-parse');
    var aiImageB64 = null;

    // Podglad screenshot
    if ($aiImage) {
        $aiImage.addEventListener('change', function(e){
            var f = e.target.files[0];
            if (!f) { aiImageB64 = null; $aiPreview.innerHTML = ''; return; }
            if (f.size > 5 * 1024 * 1024) {
                $aiStatus.className = 'alert alert-danger py-2 px-3 mb-0 small';
                $aiStatus.textContent = 'Plik za duzy (max 5 MB)';
                $aiStatus.classList.remove('d-none');
                return;
            }
            var reader = new FileReader();
            reader.onload = function(ev){
                aiImageB64 = ev.target.result;
                $aiPreview.innerHTML = '<img src="' + aiImageB64 + '" style="max-width:200px;max-height:120px;border:1px solid #d1d5db;border-radius:.3rem">';
            };
            reader.readAsDataURL(f);
        });
    }

    // Ctrl+V wklejanie obrazu do textarea
    if ($aiText) {
        $aiText.addEventListener('paste', function(e){
            var items = (e.clipboardData || {}).items || [];
            for (var i = 0; i < items.length; i++) {
                if (items[i].type && items[i].type.indexOf('image') === 0) {
                    var f = items[i].getAsFile();
                    if (f) {
                        var reader = new FileReader();
                        reader.onload = function(ev){
                            aiImageB64 = ev.target.result;
                            $aiPreview.innerHTML = '<img src="' + aiImageB64 + '" style="max-width:200px;max-height:120px;border:1px solid #d1d5db;border-radius:.3rem"><div class="so-hint mt-1">Obraz wklejony ze schowka</div>';
                        };
                        reader.readAsDataURL(f);
                        e.preventDefault();
                        return;
                    }
                }
            }
        });
    }

    if ($aiBtn) {
        $aiBtn.addEventListener('click', function(){
            var text = ($aiText.value || '').trim();
            if (!text && !aiImageB64) {
                $aiStatus.className = 'alert alert-warning py-2 px-3 mb-0 small';
                $aiStatus.textContent = 'Wklej email lub dodaj screenshot';
                $aiStatus.classList.remove('d-none');
                return;
            }
            $aiBtn.disabled = true;
            $aiStatus.className = 'alert alert-info py-2 px-3 mb-0 small';
            $aiStatus.innerHTML = '<i class="ri-loader-4-line spin me-1"></i>Analiza AI... (10-20 sek)';
            $aiStatus.classList.remove('d-none');
            $aiResult.classList.add('d-none');

            var fd = new FormData();
            fd.append('_csrfToken', CSRF);
            if (text) fd.append('text', text);
            if (aiImageB64) fd.append('image_base64', aiImageB64);

            fetch('<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'aiParseOrderJson']) ?>', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' },
            })
            .then(function(r){ return r.json(); })
            .then(function(j){
                $aiBtn.disabled = false;
                if (!j.ok) {
                    $aiStatus.className = 'alert alert-danger py-2 px-3 mb-0 small';
                    $aiStatus.textContent = 'Blad AI: ' + (j.error || 'nieznany');
                    return;
                }
                $aiStatus.classList.add('d-none');
                var d = j.data || {};
                var conf = d.confidence || 0;
                $aiConf.textContent = conf;
                $aiNote.textContent = d.note || '';
                $aiResult.classList.remove('d-none');

                // Prefill formularza
                var mapping = {
                    'buyer_nip': 'buyer_nip',
                    'buyer_name': 'buyer_name',
                    'buyer_email': 'buyer_email',
                    'buyer_city': 'buyer_city',
                    'buyer_country': 'buyer_country',
                    'load_country': 'load_country',
                    'load_city': 'load_city',
                    'load_postal_code': 'load_postal_code',
                    'date_deadline': 'date_deadline',
                    'unload_country': 'unload_country',
                    'unload_city': 'unload_city',
                    'unload_name': 'unload_name',
                    'date_delivery': 'date_delivery',
                    'title1': 'title1',
                    'title2': 'title2',
                    'cargo_type': 'cargo_type',
                    'transport_type': 'transport_type',
                    'notes': 'notes',
                    'netto': 'netto',
                    'currency': 'currency',
                    'payment_terms': 'payment_terms',
                    'cargo_weight_kg': 'cargo_weight_kg',
                    'cargo_volume_m3': 'cargo_volume_m3',
                    'cargo_ldm': 'cargo_ldm',
                    'cargo_pallets': 'cargo_pallets',
                    'cargo_pallet_type': 'cargo_pallet_type',
                    'adr_class': 'adr_class',
                    'adr_un': 'adr_un',
                    'temperature_min': 'temperature_min',
                    'temperature_max': 'temperature_max',
                    'incoterms': 'incoterms',
                    'incoterms_place': 'incoterms_place',
                    'cmr_number': 'cmr_number',
                };
                var filled = [];
                Object.keys(mapping).forEach(function(k){
                    var v = d[k];
                    if (v === null || v === undefined || v === '') return;
                    var el = $form.elements[mapping[k]];
                    if (el) {
                        el.value = v;
                        filled.push(k + ': ' + v);
                    }
                });
                $aiSummary.innerHTML = '<strong>Wypelnione pola:</strong><br>' + filled.join('<br>');
                calc(); // przelicz VAT/brutto z nowego netto
                onCur(); // update kurs jesli waluta zmieniona
                schedulePricingCheck();
                scheduleHereRoute();
                if (d.buyer_nip) { checkLastForBuyer(d.buyer_nip); fetchBuyerProfile(d.buyer_nip); }

                // Zamknij modal po 3 sek
                setTimeout(function(){
                    var modal = bootstrap.Modal.getInstance(document.getElementById('so-ai-modal'));
                    if (modal) modal.hide();
                }, 3000);
            })
            .catch(function(e){
                $aiBtn.disabled = false;
                $aiStatus.className = 'alert alert-danger py-2 px-3 mb-0 small';
                $aiStatus.textContent = 'Blad polaczenia: ' + e.message;
            });
        });
    }

})();
</script>
<style>
.spin { animation: so-spin 1s linear infinite; display: inline-block; }
@keyframes so-spin { from { transform: rotate(0deg);} to { transform: rotate(360deg);} }
</style>

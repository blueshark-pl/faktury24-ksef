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

<?php if (!empty($hereApiKey)): ?>
<link rel="stylesheet" type="text/css" href="https://js.api.here.com/v3/3.1/mapsjs-ui.css" />
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-core.js"></script>
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-service.js"></script>
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-ui.js"></script>
<script type="text/javascript" src="https://js.api.here.com/v3/3.1/mapsjs-mapevents.js"></script>
<?php endif; ?>
<!-- pdf.js - do renderowania PDF zlecen jako obrazy dla AI Vision -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.7.76/pdf.min.mjs" type="module"></script>
<script type="module">
    // pdf.js jako ES module - eksportujemy globalnie zeby dostep z regular skryptu
    import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.7.76/pdf.min.mjs';
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.7.76/pdf.worker.min.mjs';
    window.pdfjsLib = pdfjsLib;
</script>

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
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#so-tpl-modal">
                <i class="ri-bookmark-line me-1"></i> <?= __('Szablony zleceń') ?>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="so-tpl-save-btn" title="<?= __('Zapisz aktualne dane jako szablon') ?>">
                <i class="ri-bookmark-3-line"></i>
            </button>
        </div>
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

<!-- MODAL: Szablony zlecen -->
<div class="modal fade" id="so-tpl-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ri-bookmark-line me-1 text-primary"></i>
                    <?= __('Szablony zleceń') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="so-tpl-status" class="alert alert-info py-2 px-3 small"><?= __('Ładowanie...') ?></div>
                <div id="so-tpl-list" class="list-group"></div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Zapisz jako szablon -->
<div class="modal fade" id="so-tpl-save-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ri-bookmark-3-line me-1 text-primary"></i>
                    <?= __('Zapisz jako szablon') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small"><?= __('Nazwa szablonu') ?> *</label>
                    <input type="text" id="so-tpl-name" class="form-control" placeholder="HB RTS standard NL->DE" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small"><?= __('Opis (opcjonalnie)') ?></label>
                    <textarea id="so-tpl-desc" class="form-control" rows="2"></textarea>
                </div>
                <div id="so-tpl-save-status" class="small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Anuluj</button>
                <button type="button" class="btn btn-primary" id="so-tpl-save-confirm">
                    <i class="ri-save-line me-1"></i>Zapisz szablon
                </button>
            </div>
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
                    <label class="form-label small text-muted">
                        <?= __('LUB screenshoty / PDF-y (można wgrać kilka jednocześnie, max 15 MB/plik, do 10 stron łącznie)') ?>
                    </label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="file" id="so-ai-image" accept="image/png,image/jpeg,image/webp,application/pdf,.pdf" class="form-control" multiple>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="so-ai-clear" title="Wyczyść wszystkie">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                    <div class="so-hint mt-1">
                        <?= __('Możesz też wkleić obraz (Ctrl+V) w textarea powyżej. Wybór wielu plików: Ctrl/Shift + klik. PDF-y automatycznie renderujemy do 3 pierwszych stron każdy.') ?>
                    </div>
                    <div id="so-ai-preview" class="mt-2 d-flex flex-wrap gap-2"></div>
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

<!-- TSL FLOW QUICK NAV (klient -> trasa -> ladunek -> transport -> cena) -->
<div class="col-12 mb-2">
    <div class="d-flex gap-1 flex-wrap so-flow-nav p-2 rounded" style="background:#f8fafc;border:1px solid #e5e7eb">
        <a href="#sec-buyer"    class="btn btn-sm btn-outline-primary flex-grow-1" style="min-width:110px">
            <span class="badge bg-primary text-white me-1">1</span> <?= __('Klient') ?>
        </a>
        <a href="#sec-route"    class="btn btn-sm btn-outline-success flex-grow-1" style="min-width:110px">
            <span class="badge bg-success text-white me-1">2</span> <?= __('Trasa + daty') ?>
        </a>
        <a href="#sec-cargo"    class="btn btn-sm btn-outline-warning flex-grow-1" style="min-width:110px">
            <span class="badge bg-warning text-dark me-1">3</span> <?= __('Ładunek') ?>
        </a>
        <a href="#sec-transport" class="btn btn-sm btn-outline-info flex-grow-1" style="min-width:110px">
            <span class="badge bg-info text-dark me-1">4</span> <?= __('Transport') ?>
        </a>
        <a href="#sec-finance"  class="btn btn-sm btn-outline-primary flex-grow-1" style="min-width:110px">
            <span class="badge bg-primary text-white me-1">5</span> <?= __('Cena') ?>
        </a>
    </div>
</div>
<style>
.so-flow-nav a { font-weight: 500; font-size: .82rem; text-decoration: none; }
.so-flow-nav a:hover { background: rgba(13,110,253,.08); }
html { scroll-behavior: smooth; scroll-padding-top: 80px; }
.so-step-badge { display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 50%; font-size: .72rem; font-weight: 700; margin-right: 6px; }
</style>

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
<div class="col-12" id="sec-buyer">
    <div class="card border-0 shadow-sm so-section-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-semibold text-uppercase text-muted small so-section-title">
                    <span class="so-step-badge bg-primary text-white">1</span>
                    <i class="ri-user-line me-1 text-primary"></i> <?= __('Zleceniodawca (nabywca)') ?>
                </h6>
                <div class="input-group input-group-sm" style="max-width:340px">
                    <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                    <input type="text" id="buyer-search" class="form-control" placeholder="<?= __('Szukaj kontrahenta (nazwa / NIP)') ?>">
                </div>
            </div>
            <div id="buyer-results" class="list-group mb-2 d-none" style="max-height:200px;overflow-y:auto"></div>

            <!-- Kredyt klienta -->
            <div id="so-credit-wrap" class="mb-2 d-none"></div>

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
<div class="col-md-6" id="sec-route">
    <div class="card border-0 shadow-sm so-section-card h-100" style="border-left:3px solid #10b981 !important">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small so-section-title">
                <span class="so-step-badge bg-success text-white">2</span>
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
                    <label class="form-label small text-muted">
                        <?= __('Miasto') ?>
                        <?php if (!empty($order->load_lat) && !empty($order->load_lng)): ?>
                            <a href="https://www.google.com/maps?q=<?= h($order->load_lat) ?>,<?= h($order->load_lng) ?>" target="_blank" class="text-success ms-1" title="Otwórz w Google Maps" style="font-size:.72rem">
                                <i class="ri-map-pin-2-line"></i>
                            </a>
                        <?php endif; ?>
                    </label>
                    <input type="text" name="load_city" class="form-control" value="<?= h($order->load_city ?? '') ?>" maxlength="100">
                </div>
                <div class="col-md-12">
                    <label class="form-label small text-muted"><?= __('Adres (ulica + numer)') ?> *</label>
                    <input type="text" name="load_address" class="form-control" value="<?= h($order->load_address ?? '') ?>" maxlength="255" placeholder="np. Wielicka 22 lub Magazyn IKEA, brama 5">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Planowana data') ?></label>
                    <input type="datetime-local" name="date_deadline" class="form-control" value="<?= h($order->date_deadline ? $order->date_deadline->format('Y-m-d\TH:i') : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Czas rzeczywisty') ?></label>
                    <input type="datetime-local" name="actual_load_at" class="form-control" value="<?= h($order->actual_load_at ? $order->actual_load_at->format('Y-m-d\TH:i') : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Okno od (godz.)') ?></label>
                    <input type="time" name="load_time_from" class="form-control form-control-sm" value="<?= h($order->load_time_from ? (string)$order->load_time_from : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Okno do (godz.)') ?></label>
                    <input type="time" name="load_time_to" class="form-control form-control-sm" value="<?= h($order->load_time_to ? (string)$order->load_time_to : '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kontakt na miejscu (imię)') ?></label>
                    <input type="text" name="load_contact_name" class="form-control form-control-sm" value="<?= h($order->load_contact_name ?? '') ?>" maxlength="120">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Telefon') ?></label>
                    <input type="tel" name="load_contact_phone" class="form-control form-control-sm" value="<?= h($order->load_contact_phone ?? '') ?>" maxlength="40" placeholder="+48 123 456 789">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Email') ?></label>
                    <input type="email" name="load_contact_email" class="form-control form-control-sm" value="<?= h($order->load_contact_email ?? '') ?>" maxlength="180">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('GPS lat') ?></label>
                    <input type="number" step="0.0000001" name="load_lat" class="form-control form-control-sm" value="<?= h($order->load_lat ?? '') ?>" placeholder="np. 52.229676">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('GPS lng') ?></label>
                    <input type="number" step="0.0000001" name="load_lng" class="form-control form-control-sm" value="<?= h($order->load_lng ?? '') ?>" placeholder="np. 21.012229">
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
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kod pocztowy') ?></label>
                    <input type="text" name="unload_postal_code" class="form-control" value="<?= h($order->unload_postal_code ?? '') ?>" maxlength="20">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">
                        <?= __('Miasto') ?>
                        <?php if (!empty($order->unload_lat) && !empty($order->unload_lng)): ?>
                            <a href="https://www.google.com/maps?q=<?= h($order->unload_lat) ?>,<?= h($order->unload_lng) ?>" target="_blank" class="text-success ms-1" title="Otwórz w Google Maps" style="font-size:.72rem">
                                <i class="ri-map-pin-2-line"></i>
                            </a>
                        <?php endif; ?>
                    </label>
                    <input type="text" name="unload_city" class="form-control" value="<?= h($order->unload_city ?? '') ?>" maxlength="100">
                </div>
                <div class="col-md-12">
                    <label class="form-label small text-muted"><?= __('Adres (ulica + numer)') ?> *</label>
                    <input type="text" name="unload_address" class="form-control" value="<?= h($order->unload_address ?? '') ?>" maxlength="255" placeholder="np. Magazyn XYZ, Przemysłowa 15, brama 3">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Planowana data') ?></label>
                    <input type="datetime-local" name="date_delivery" class="form-control" value="<?= h($order->date_delivery ? $order->date_delivery->format('Y-m-d\TH:i') : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Czas rzeczywisty') ?></label>
                    <input type="datetime-local" name="actual_unload_at" class="form-control" value="<?= h($order->actual_unload_at ? $order->actual_unload_at->format('Y-m-d\TH:i') : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Okno od (godz.)') ?></label>
                    <input type="time" name="unload_time_from" class="form-control form-control-sm" value="<?= h($order->unload_time_from ? (string)$order->unload_time_from : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('Okno do (godz.)') ?></label>
                    <input type="time" name="unload_time_to" class="form-control form-control-sm" value="<?= h($order->unload_time_to ? (string)$order->unload_time_to : '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Kontakt na miejscu (imię)') ?></label>
                    <input type="text" name="unload_contact_name" class="form-control form-control-sm" value="<?= h($order->unload_contact_name ?? '') ?>" maxlength="120">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Telefon') ?></label>
                    <input type="tel" name="unload_contact_phone" class="form-control form-control-sm" value="<?= h($order->unload_contact_phone ?? '') ?>" maxlength="40">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted"><?= __('Email') ?></label>
                    <input type="email" name="unload_contact_email" class="form-control form-control-sm" value="<?= h($order->unload_contact_email ?? '') ?>" maxlength="180">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('GPS lat') ?></label>
                    <input type="number" step="0.0000001" name="unload_lat" class="form-control form-control-sm" value="<?= h($order->unload_lat ?? '') ?>" placeholder="np. 52.229676">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted"><?= __('GPS lng') ?></label>
                    <input type="number" step="0.0000001" name="unload_lng" class="form-control form-control-sm" value="<?= h($order->unload_lng ?? '') ?>" placeholder="np. 21.012229">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SEKCJA 3b: Multi-stop (opcjonalne stopy posrednie) -->
<div class="col-12">
    <div class="card border-0 shadow-sm so-section-card" style="border-left:3px solid #f59e0b !important">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-semibold text-uppercase text-muted small so-section-title">
                    <i class="ri-route-fill me-1 text-warning"></i> <?= __('Dodatkowe stopy w trasie (multi-stop)') ?>
                    <span class="so-hint ms-1">opcjonalnie</span>
                </h6>
                <button type="button" class="btn btn-sm btn-outline-warning" id="so-stops-add">
                    <i class="ri-add-line me-1"></i><?= __('Dodaj stop') ?>
                </button>
            </div>
            <div id="so-stops-list">
                <?php
                $existingStops = [];
                if ($isEdit && !empty($order->speed_order_stops)) {
                    $existingStops = $order->speed_order_stops;
                }
                ?>
                <?php foreach ($existingStops as $sIdx => $stop): ?>
                    <div class="so-stop-row border rounded p-2 mb-2" data-idx="<?= $sIdx ?>">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-1">
                                <label class="form-label small text-muted mb-0"><?= __('Typ') ?></label>
                                <select name="speed_order_stops[<?= $sIdx ?>][stop_type]" class="form-select form-select-sm">
                                    <option value="pickup"   <?= $stop->stop_type === 'pickup'   ? 'selected' : '' ?>>Załad.</option>
                                    <option value="delivery" <?= $stop->stop_type === 'delivery' ? 'selected' : '' ?>>Rozład.</option>
                                    <option value="transit"  <?= $stop->stop_type === 'transit'  ? 'selected' : '' ?>>Transit</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small text-muted mb-0"><?= __('Kraj') ?></label>
                                <input type="text" name="speed_order_stops[<?= $sIdx ?>][country_code]" class="form-control form-control-sm" value="<?= h($stop->country_code ?? '') ?>" maxlength="5">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small text-muted mb-0"><?= __('Kod') ?></label>
                                <input type="text" name="speed_order_stops[<?= $sIdx ?>][postal_code]" class="form-control form-control-sm" value="<?= h($stop->postal_code ?? '') ?>" maxlength="20">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0"><?= __('Miasto') ?></label>
                                <input type="text" name="speed_order_stops[<?= $sIdx ?>][city]" class="form-control form-control-sm" value="<?= h($stop->city ?? '') ?>" maxlength="120">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0"><?= __('Miejsce/adres') ?></label>
                                <input type="text" name="speed_order_stops[<?= $sIdx ?>][place_name]" class="form-control form-control-sm" value="<?= h($stop->place_name ?? '') ?>" maxlength="200">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0"><?= __('Planowany czas') ?></label>
                                <input type="datetime-local" name="speed_order_stops[<?= $sIdx ?>][planned_at]" class="form-control form-control-sm" value="<?= h($stop->planned_at ? $stop->planned_at->format('Y-m-d\TH:i') : '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0"><?= __('Uwagi/palety') ?></label>
                                <input type="text" name="speed_order_stops[<?= $sIdx ?>][cargo_notes]" class="form-control form-control-sm" value="<?= h($stop->cargo_notes ?? '') ?>">
                            </div>
                            <div class="col-md-1 text-end">
                                <input type="hidden" name="speed_order_stops[<?= $sIdx ?>][id]" value="<?= h($stop->id ?? '') ?>">
                                <input type="hidden" name="speed_order_stops[<?= $sIdx ?>][stop_index]" value="<?= $sIdx + 1 ?>" class="so-stop-idx">
                                <?php if (!empty($stop->lat) && !empty($stop->lng)): ?>
                                    <a href="https://www.google.com/maps?q=<?= h($stop->lat) ?>,<?= h($stop->lng) ?>" target="_blank" class="btn btn-sm btn-outline-success mb-1" title="Otwórz w Google Maps">
                                        <i class="ri-map-pin-2-line"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-danger so-stop-remove">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </div>
                            <!-- Drugi rząd: okno + kontakt (analogicznie do pickup/delivery) -->
                            <div class="col-md-1"><label class="form-label small text-muted mb-0"><?= __('Okno od') ?></label>
                                <input type="time" name="speed_order_stops[<?= $sIdx ?>][time_from]" class="form-control form-control-sm" value="<?= h($stop->time_from ? (string)$stop->time_from : '') ?>">
                            </div>
                            <div class="col-md-1"><label class="form-label small text-muted mb-0"><?= __('Okno do') ?></label>
                                <input type="time" name="speed_order_stops[<?= $sIdx ?>][time_to]" class="form-control form-control-sm" value="<?= h($stop->time_to ? (string)$stop->time_to : '') ?>">
                            </div>
                            <div class="col-md-2"><label class="form-label small text-muted mb-0"><?= __('Kontakt imię') ?></label>
                                <input type="text" name="speed_order_stops[<?= $sIdx ?>][contact_name]" class="form-control form-control-sm" value="<?= h($stop->contact_name ?? '') ?>" maxlength="120">
                            </div>
                            <div class="col-md-2"><label class="form-label small text-muted mb-0"><?= __('Telefon') ?></label>
                                <input type="tel" name="speed_order_stops[<?= $sIdx ?>][contact_phone]" class="form-control form-control-sm" value="<?= h($stop->contact_phone ?? '') ?>" maxlength="40">
                            </div>
                            <div class="col-md-2"><label class="form-label small text-muted mb-0"><?= __('Email') ?></label>
                                <input type="email" name="speed_order_stops[<?= $sIdx ?>][contact_email]" class="form-control form-control-sm" value="<?= h($stop->contact_email ?? '') ?>" maxlength="180">
                            </div>
                            <div class="col-md-1"><label class="form-label small text-muted mb-0"><?= __('GPS lat') ?></label>
                                <input type="number" step="0.0000001" name="speed_order_stops[<?= $sIdx ?>][lat]" class="form-control form-control-sm so-stop-lat" value="<?= h($stop->lat ?? '') ?>" placeholder="52.229...">
                            </div>
                            <div class="col-md-1"><label class="form-label small text-muted mb-0"><?= __('GPS lng') ?></label>
                                <input type="number" step="0.0000001" name="speed_order_stops[<?= $sIdx ?>][lng]" class="form-control form-control-sm so-stop-lng" value="<?= h($stop->lng ?? '') ?>" placeholder="21.012...">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="so-stops-empty" class="text-muted small text-center py-2" <?= !empty($existingStops) ? 'style="display:none"' : '' ?>>
                <?= __('Brak dodatkowych stopów. Zwykłe A → B (bez multi-stop). Kliknij „Dodaj stop" aby dodać pośredni załadunek lub rozładunek.') ?>
            </div>
        </div>
    </div>
</div>

<!-- SEKCJA 4: Ladunek -->
<div class="col-12" id="sec-cargo">
    <div class="card border-0 shadow-sm so-section-card">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small so-section-title">
                <span class="so-step-badge bg-warning text-dark">3</span>
                <i class="ri-archive-line me-1 text-warning"></i> <?= __('Ładunek') ?>
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
                    <label class="form-label small text-muted"><?= __('Wymagany typ pojazdu') ?></label>
                    <select name="required_vehicle_type" class="form-select">
                        <option value=""></option>
                        <?php foreach ([
                            'plandeka' => 'Plandeka',
                            'mega' => 'Mega',
                            'chlodnia' => 'Chłodnia',
                            'cysterna' => 'Cysterna',
                            'wywrotka' => 'Wywrotka',
                            'kontener' => 'Kontener',
                            'bus' => 'Bus / dostawcze',
                            'platforma' => 'Platforma / niskopodwoziowe',
                            'oversize' => 'Ponadgabaryt',
                        ] as $val => $lbl): ?>
                            <option value="<?= h($val) ?>" <?= ($order->required_vehicle_type ?? '') === $val ? 'selected' : '' ?>><?= h($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('Rodzaj transportu (dodatkowy)') ?></label>
                    <input type="text" name="transport_type" class="form-control" value="<?= h($order->transport_type ?? '') ?>" maxlength="100" placeholder="np. z windą, ADR">
                </div>
                <!-- Palety wymienne -->
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="pallets_exchange" value="1" class="form-check-input" id="so-pallets-ex" <?= !empty($order->pallets_exchange) ? 'checked' : '' ?>>
                        <label for="so-pallets-ex" class="form-check-label small"><?= __('Palety wymienne (EUR/EPAL)') ?></label>
                    </div>
                </div>
                <div class="col-md-2" id="so-pallets-ex-count-wrap" style="<?= !empty($order->pallets_exchange) ? '' : 'display:none' ?>">
                    <label class="form-label small text-muted"><?= __('Ilość do wymiany') ?></label>
                    <input type="number" min="0" step="1" name="pallets_exchange_count" class="form-control form-control-sm" value="<?= h($order->pallets_exchange_count ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Zwrot CMR (dni)') ?></label>
                    <input type="number" min="0" max="90" step="1" name="docs_return_days" class="form-control form-control-sm" value="<?= h($order->docs_return_days ?? '') ?>" placeholder="7">
                </div>
                <div class="col-md-12">
                    <label class="form-label small text-muted"><?= __('Uwagi ogólne (widoczne w email do klienta)') ?></label>
                    <textarea name="notes" class="form-control" rows="2" maxlength="2000"><?= h($order->notes ?? '') ?></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label small text-muted">
                        <i class="ri-user-star-line me-1 text-warning"></i>
                        <?= __('Instrukcje dla kierowcy (kod bramy, wjazd, EPI...) — NIE widoczne w email klienta') ?>
                    </label>
                    <textarea name="driver_instructions" class="form-control" rows="2" maxlength="2000" placeholder="Wjazd od tyłu, kod bramy: 1234, EPI: kamizelka + kask, godziny 07:00-15:00"><?= h($order->driver_instructions ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WIDGET: Warnings kolizji grafika -->
<div class="col-12" id="so-conflict-wrap" style="display:none">
    <div class="alert py-2 px-3 mb-0 small" id="so-conflict-alert"></div>
</div>

<!-- SEKCJA 4b: Cargo items (pozycje ladunku) -->
<div class="col-12">
    <div class="card border-0 shadow-sm so-section-card" style="border-left:3px solid #8b5cf6 !important">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-semibold text-uppercase text-muted small so-section-title">
                    <i class="ri-inbox-archive-line me-1 text-warning"></i> <?= __('Pozycje ładunku (cargo manifest)') ?>
                    <span class="so-hint ms-1">opcjonalnie</span>
                </h6>
                <button type="button" class="btn btn-sm btn-outline-warning" id="so-cargo-add">
                    <i class="ri-add-line me-1"></i><?= __('Dodaj pozycję') ?>
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0" id="so-cargo-list">
                    <thead class="table-light small">
                        <tr>
                            <th style="width:60px">Kod</th>
                            <th>Nazwa / produkt</th>
                            <th style="width:130px">Paleta</th>
                            <th class="text-center" style="width:50px" title="Dry">Dry</th>
                            <th class="text-center" style="width:50px" title="Wrapping">Wrap</th>
                            <th class="text-center" style="width:50px" title="Strapping">Strap</th>
                            <th class="text-center" style="width:60px" title="Sort Only">Sort</th>
                            <th class="text-center" style="width:70px">Stack</th>
                            <th class="text-center" style="width:80px">Adv qty</th>
                            <th class="text-center" style="width:80px">Real qty</th>
                            <th class="text-end" style="width:90px">Waga kg</th>
                            <th style="width:60px">j.m.</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $existingCargo = [];
                        if ($isEdit && !empty($order->speed_order_cargo_items)) {
                            $existingCargo = $order->speed_order_cargo_items;
                        }
                        ?>
                        <?php foreach ($existingCargo as $cIdx => $ci): ?>
                            <tr class="so-cargo-row" data-idx="<?= $cIdx ?>">
                                <td><input type="text" name="speed_order_cargo_items[<?= $cIdx ?>][product_code]" class="form-control form-control-sm" value="<?= h($ci->product_code ?? '') ?>" maxlength="60"></td>
                                <td><input type="text" name="speed_order_cargo_items[<?= $cIdx ?>][product_name]" class="form-control form-control-sm" value="<?= h($ci->product_name ?? '') ?>" maxlength="255"></td>
                                <td>
                                    <select name="speed_order_cargo_items[<?= $cIdx ?>][pallet_type_id]" class="form-select form-select-sm">
                                        <option value=""></option>
                                        <?php foreach ($palletTypes as $pt): ?>
                                            <option value="<?= h($pt['id']) ?>" data-code="<?= h($pt['code']) ?>" <?= ($ci->pallet_type_id ?? '') === $pt['id'] ? 'selected' : '' ?>>
                                                <?= h($pt['code']) ?><?= $pt['manufacturer'] ? ' (' . h($pt['manufacturer']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="speed_order_cargo_items[<?= $cIdx ?>][pallet_code]" value="<?= h($ci->pallet_code ?? '') ?>">
                                </td>
                                <td class="text-center"><input type="checkbox" name="speed_order_cargo_items[<?= $cIdx ?>][is_dry]" value="1" class="form-check-input" <?= !empty($ci->is_dry) ? 'checked' : '' ?>></td>
                                <td class="text-center"><input type="checkbox" name="speed_order_cargo_items[<?= $cIdx ?>][is_wrapped]" value="1" class="form-check-input" <?= !empty($ci->is_wrapped) ? 'checked' : '' ?>></td>
                                <td class="text-center"><input type="checkbox" name="speed_order_cargo_items[<?= $cIdx ?>][is_strapped]" value="1" class="form-check-input" <?= !empty($ci->is_strapped) ? 'checked' : '' ?>></td>
                                <td class="text-center"><input type="checkbox" name="speed_order_cargo_items[<?= $cIdx ?>][is_sort_only]" value="1" class="form-check-input" <?= !empty($ci->is_sort_only) ? 'checked' : '' ?>></td>
                                <td><input type="number" min="0" step="1" name="speed_order_cargo_items[<?= $cIdx ?>][stack_height]" class="form-control form-control-sm text-center" value="<?= h($ci->stack_height ?? '') ?>"></td>
                                <td><input type="number" min="0" step="1" name="speed_order_cargo_items[<?= $cIdx ?>][qty_advised]" class="form-control form-control-sm text-center" value="<?= h($ci->qty_advised ?? '') ?>"></td>
                                <td><input type="number" min="0" step="1" name="speed_order_cargo_items[<?= $cIdx ?>][qty_real]" class="form-control form-control-sm text-center" value="<?= h($ci->qty_real ?? '') ?>"></td>
                                <td><input type="number" min="0" step="0.001" name="speed_order_cargo_items[<?= $cIdx ?>][weight_kg]" class="form-control form-control-sm text-end" value="<?= h($ci->weight_kg ?? '') ?>"></td>
                                <td>
                                    <select name="speed_order_cargo_items[<?= $cIdx ?>][unit]" class="form-select form-select-sm">
                                        <?php foreach (['szt','kg','m3','palety','kartony','opak.'] as $u): ?>
                                            <option value="<?= h($u) ?>" <?= ($ci->unit ?? 'szt') === $u ? 'selected' : '' ?>><?= h($u) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="hidden" name="speed_order_cargo_items[<?= $cIdx ?>][id]" value="<?= h($ci->id ?? '') ?>">
                                    <button type="button" class="btn btn-sm btn-outline-danger so-cargo-remove"><i class="ri-delete-bin-line"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot id="so-cargo-summary" class="table-light" <?= empty($existingCargo) ? 'style="display:none"' : '' ?>>
                        <tr>
                            <th colspan="8" class="text-end">Suma:</th>
                            <th class="text-center" id="so-cargo-sum-adv">-</th>
                            <th class="text-center" id="so-cargo-sum-real">-</th>
                            <th class="text-end" id="so-cargo-sum-weight">-</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div id="so-cargo-empty" class="text-muted small text-center py-2" <?= !empty($existingCargo) ? 'style="display:none"' : '' ?>>
                <?= __('Brak pozycji ładunku. Dodaj pierwszą pozycję manifestu (kod, nazwa, ilości, waga).') ?>
            </div>
        </div>
    </div>
</div>

<!-- SEKCJA 5: Transport -->
<div class="col-12" id="sec-transport">
    <div class="card border-0 shadow-sm so-section-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 fw-semibold text-uppercase text-muted small so-section-title">
                    <span class="so-step-badge bg-info text-dark">4</span>
                    <i class="ri-truck-fill me-1 text-info"></i> <?= __('Transport') ?>
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
<div class="col-12" id="sec-finance">
    <div class="card border-0 shadow-sm so-section-card" style="border-left:3px solid #0d6efd !important">
        <div class="card-body">
            <h6 class="mb-3 fw-semibold text-uppercase text-muted small so-section-title">
                <span class="so-step-badge bg-primary text-white">5</span>
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
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Termin (dni)') ?></label>
                    <input type="number" min="0" max="180" step="1" name="payment_days" id="so-payment-days" class="form-control" value="<?= h($order->payment_days ?? 30) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Do zapłaty') ?></label>
                    <input type="text" id="so-payment-due" class="form-control text-muted" value="<?= h($order->payment_due_date ? $order->payment_due_date->format('Y-m-d') : '') ?>" readonly title="Auto: date_doc + payment_days">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('Warunki (opis)') ?></label>
                    <input type="text" name="payment_terms" class="form-control" value="<?= h($order->payment_terms ?? '') ?>" maxlength="100" placeholder="np. Przelew, kompensata">
                </div>
                <div class="col-12" id="so-approval-hint" style="display:none">
                    <div class="alert alert-warning py-2 px-3 mb-0 small">
                        <i class="ri-shield-user-line me-1"></i>
                        <?php $threshold = (int)(\Cake\Core\Configure::read('Orders.approvalThresholdPln') ?? 10000); ?>
                        <strong><?= __('Wymaga akceptacji managera') ?></strong> —
                        <?= __('brutto przekracza próg {0} PLN. Po zapisie status będzie "Oczekuje akceptacji" aż manager zatwierdzi.', number_format($threshold, 0, ',', ' ')) ?>
                    </div>
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
                <!-- Lewa: KPI + cena -->
                <div class="col-lg-5">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="p-2 rounded" style="background:#f0f7ff">
                                <div class="so-hint"><?= __('Dystans') ?></div>
                                <div class="fs-5 fw-semibold text-primary" id="so-here-km">-</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded" style="background:#f0f7ff">
                                <div class="so-hint"><?= __('Czas jazdy') ?></div>
                                <div class="fs-5 fw-semibold" id="so-here-time">-</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-2 rounded" style="background:#fef3c7">
                                <div class="so-hint"><?= __('Tolls (EUR)') ?></div>
                                <div class="fs-5 fw-semibold" id="so-here-tolls">-</div>
                                <div class="so-hint" id="so-here-tolls-detail"></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-2 rounded" style="background:#ecfdf5">
                                <div class="so-hint"><?= __('Sugestia ceny (km × stawka + tolls)') ?></div>
                                <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
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

                <!-- Prawa: mapa HERE -->
                <div class="col-lg-7">
                    <?php if (!empty($hereApiKey)): ?>
                        <div id="so-here-map" style="width:100%;height:320px;background:#e5e7eb;border-radius:.35rem;overflow:hidden"></div>
                    <?php else: ?>
                        <div class="p-3 text-center text-muted" style="background:#f8fafc;border-radius:.35rem;height:320px;display:flex;align-items:center;justify-content:center">
                            <div><i class="ri-map-pin-line" style="font-size:2rem"></i><br><small>Konfiguruj HERE API key aby zobaczyć mapę</small></div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Legacy placeholder (dummy) -->
                <div class="d-none">
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

    var APPROVAL_THRESHOLD_PLN = <?= (int)(\Cake\Core\Configure::read('Orders.approvalThresholdPln') ?? 10000) ?>;
    var $approvalHint = document.getElementById('so-approval-hint');
    var $rateFx = document.getElementById('fin-rate'); // musi byc PRZED calc() bo calc() go uzywa

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

        // Approval hint: pokazuj gdy brutto (PLN) > threshold
        if ($approvalHint) {
            var rate = ($rateFx && $rateFx.value) ? (parseFloat($rateFx.value) || 1) : 1;
            var bruttoPln = $cur.value === 'PLN' ? b : b * rate;
            $approvalHint.style.display = (APPROVAL_THRESHOLD_PLN > 0 && bruttoPln > APPROVAL_THRESHOLD_PLN) ? '' : 'none';
        }
    }
    $netto.addEventListener('input', calc);
    $rate.addEventListener('change', calc);
    $cur.addEventListener('change', calc);
    calc();

    // ===== Payment due date - auto-fill z date_doc + payment_days =====
    var $paymentDays = document.getElementById('so-payment-days');
    var $paymentDue  = document.getElementById('so-payment-due');
    function recalcPaymentDue() {
        if (!$paymentDays || !$paymentDue) return;
        var days = parseInt($paymentDays.value, 10) || 0;
        var docDate = $form.elements.date_doc.value;
        if (!docDate || days <= 0) { $paymentDue.value = ''; return; }
        try {
            var d = new Date(docDate + 'T00:00:00');
            d.setDate(d.getDate() + days);
            $paymentDue.value = d.toISOString().slice(0, 10);
        } catch(e){}
    }
    if ($paymentDays) $paymentDays.addEventListener('input', recalcPaymentDue);
    $form.elements.date_doc.addEventListener('change', recalcPaymentDue);
    recalcPaymentDue();

    // ===== Palety wymienne toggle =====
    var $palletsEx = document.getElementById('so-pallets-ex');
    var $palletsExCount = document.getElementById('so-pallets-ex-count-wrap');
    if ($palletsEx && $palletsExCount) {
        $palletsEx.addEventListener('change', function(){
            $palletsExCount.style.display = $palletsEx.checked ? '' : 'none';
        });
    }

    // ===== Walidacja wagi cargo vs DMC pojazdu =====
    // Aktualnie: przy zmianie cargo_weight_kg lub required_vehicle_type
    // pokazuj hint (nie blokujemy zapisu - tylko warning)
    var $cargoWeight = $form.elements.cargo_weight_kg;
    var $reqVType    = $form.elements.required_vehicle_type;
    if ($cargoWeight && $reqVType) {
        // Typowe limity DMC per typ (heurystyka)
        var vehLimits = {
            'plandeka': 24000, 'mega': 24000, 'chlodnia': 20000,
            'cysterna': 24000, 'wywrotka': 24000, 'kontener': 24000,
            'bus': 3500, 'platforma': 40000, 'oversize': 50000,
        };
        function checkWeightLimit() {
            var w = parseInt($cargoWeight.value, 10) || 0;
            var t = $reqVType.value;
            var limit = vehLimits[t] || 0;
            var existing = document.getElementById('so-weight-warn');
            if (existing) existing.remove();
            if (limit > 0 && w > 0 && w > limit) {
                var msg = document.createElement('div');
                msg.id = 'so-weight-warn';
                msg.className = 'small text-danger mt-1';
                msg.innerHTML = '<i class="ri-error-warning-line me-1"></i>Waga ' + w.toLocaleString('pl-PL') +
                                ' kg przekracza limit typowy dla ' + t + ' (~' + limit.toLocaleString('pl-PL') + ' kg)';
                $cargoWeight.parentNode.appendChild(msg);
            }
        }
        $cargoWeight.addEventListener('input', checkWeightLimit);
        $reqVType.addEventListener('change', checkWeightLimit);
        checkWeightLimit();
    }

    // ===== Currency change: exchange_rate = kurs NBP z dnia dokumentu =====
    var lastFetchedRateKey = ''; // 'EUR|2026-08-04' cache

    function fetchNbpRate(code, dateStr) {
        // Wolamy z zakresem ostatnich 7 dni (obejmuje weekend/swieta) -> bierzemy ostatni
        var toDate   = new Date(dateStr + 'T00:00:00');
        var fromDate = new Date(toDate.getTime() - 7 * 86400000);
        var from = fromDate.toISOString().slice(0, 10);
        var to   = dateStr;
        var key  = code + '|' + to;
        if (key === lastFetchedRateKey) return; // avoid duplicate fetch

        $rateFx.classList.add('bg-light');
        fetch('/nbp/rates?code=' + encodeURIComponent(code) + '&from=' + from + '&to=' + to,
              { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function(r){ return r.json(); })
        .then(function(j){
            $rateFx.classList.remove('bg-light');
            if (!j.success || !j.rates || j.rates.length === 0) return;
            var last = j.rates[j.rates.length - 1];
            $rateFx.value = parseFloat(last.mid).toFixed(6);
            $rateFx.title = 'Kurs NBP ' + code + ' z ' + last.effectiveDate + ' (tabela ' + (j.table || '') + ')';
            lastFetchedRateKey = key;
        })
        .catch(function(){ $rateFx.classList.remove('bg-light'); });
    }

    function onCur() {
        if ($cur.value === 'PLN') {
            $rateFx.value = '1.000000';
            $rateFx.setAttribute('readonly', 'readonly');
            $rateFx.title = 'PLN = kurs 1.0';
        } else {
            $rateFx.removeAttribute('readonly');
            var dateStr = ($form.elements.date_doc.value || '').slice(0, 10) || new Date().toISOString().slice(0, 10);
            fetchNbpRate($cur.value, dateStr);
        }
    }
    $cur.addEventListener('change', onCur);
    $form.elements.date_doc.addEventListener('change', function(){
        if ($cur.value !== 'PLN') onCur();
    });
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
        fetchCredit(nip);
    });

    // ===== KREDYT KLIENTA (limit + saldo nieoplaconych faktur) =====
    var $creditWrap = document.getElementById('so-credit-wrap');
    function fetchCredit(nip) {
        var digits = (nip || '').replace(/\D+/g, '');
        if (digits.length < 5) { $creditWrap.classList.add('d-none'); return; }
        fetch('<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'creditCheckJson']) ?>?nip=' + encodeURIComponent(digits),
              { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.ok || !j.found) { $creditWrap.classList.add('d-none'); return; }
            // Nic ciekawego jesli brak limitu i brak zaleglosci
            if (!j.has_limit && j.unpaid_count === 0) { $creditWrap.classList.add('d-none'); return; }

            var fmt = function(v){ return (Math.round(v * 100) / 100).toLocaleString('pl-PL', {minimumFractionDigits: 2}); };
            var html = '';
            var statusMap = {
                'ok':          {level: 'success', icon: 'ri-shield-check-line', label: 'Kredyt OK'},
                'warning':     {level: 'warning', icon: 'ri-alert-line',        label: 'Uwaga - kredyt zblizony do limitu'},
                'exceeded':    {level: 'danger',  icon: 'ri-error-warning-line',label: 'Przekroczony limit kredytowy'},
                'blocked':     {level: 'danger',  icon: 'ri-lock-line',         label: 'Klient zablokowany'},
                'has_overdue': {level: 'warning', icon: 'ri-time-line',         label: 'Nieoplacone faktury (bez limitu)'},
            };
            var s = statusMap[j.status] || statusMap['ok'];
            html = '<div class="alert alert-' + s.level + ' py-2 px-3 mb-0 small">' +
                '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">' +
                '<div><i class="' + s.icon + ' me-1"></i><strong>' + s.label + '</strong>';
            if (j.has_limit) {
                html += ' &middot; ' + fmt(j.unpaid_pln) + ' / ' + fmt(j.credit_limit) + ' PLN';
                if (j.used_pct !== null) html += ' (' + j.used_pct + '%)';
            } else {
                html += ' &middot; nieoplacone: ' + fmt(j.unpaid_pln) + ' PLN';
            }
            if (j.unpaid_count > 0) html += ' &middot; ' + j.unpaid_count + ' faktur';
            if (j.overdue_count > 0) html += ' <span class="badge bg-danger">' + j.overdue_count + ' przeterminowanych</span>';
            html += '</div>';
            if (j.has_limit && j.available_pln !== null) {
                html += '<div class="text-muted small">dostepne: <strong>' + fmt(j.available_pln) + ' PLN</strong></div>';
            }
            html += '</div>';
            if (j.block_reason) {
                html += '<div class="small mt-1 text-muted">Powod blokady: ' + j.block_reason + '</div>';
            }
            html += '</div>';
            $creditWrap.innerHTML = html;
            $creditWrap.classList.remove('d-none');
        }).catch(function(){ $creditWrap.classList.add('d-none'); });
    }

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
    $nipInput.addEventListener('blur', function(){ checkLastForBuyer($nipInput.value); fetchBuyerProfile($nipInput.value); fetchCredit($nipInput.value); });
    // Sprawdz od razu jesli NIP juz jest wypelniony (np. duplikat)
    if ($nipInput.value.trim()) { checkLastForBuyer($nipInput.value); fetchBuyerProfile($nipInput.value); fetchCredit($nipInput.value); }

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
    // HERE AUTOCOMPLETE MIAST (load/unload/buyer/multi-stop)
    // Przyjmuje selektor CSS lub konkretny element - dziala zarowno dla
    // static form fields jak i dynamicznych wierszy multi-stop.
    // =====================================================================
    function attachCityAutocomplete($inp, opts) {
        opts = opts || {};
        if (typeof $inp === 'string') $inp = $form.elements[$inp] || document.querySelector($inp);
        if (!$inp || $inp.dataset.acAttached === '1') return;
        $inp.dataset.acAttached = '1';

        // Helper: znajdz powiazane pole (name / element / callback)
        function getRelated(spec) {
            if (!spec) return null;
            if (typeof spec === 'string') return $form.elements[spec] || null;
            if (typeof spec === 'function') return spec($inp);
            return spec; // element
        }

        var wrap = document.createElement('div');
        wrap.style.cssText = 'position:absolute;z-index:1050;background:#fff;border:1px solid #d1d5db;border-radius:.3rem;box-shadow:0 4px 12px rgba(0,0,0,.1);max-height:240px;overflow-y:auto;min-width:260px;display:none';
        document.body.appendChild(wrap);
        var timer = null;

        function positionDropdown() {
            var r = $inp.getBoundingClientRect();
            wrap.style.left = (r.left + window.scrollX) + 'px';
            wrap.style.top  = (r.bottom + window.scrollY + 2) + 'px';
            wrap.style.width = Math.max(r.width, 260) + 'px';
        }

        function render(items) {
            if (!items || !items.length) { wrap.style.display = 'none'; return; }
            var html = '';
            items.slice(0, 8).forEach(function(it, idx){
                var city = it.city || it.title || '';
                var zip  = it.postal_code || '';
                var cc   = it.country || '';
                var hasCoords = it.lat && it.lng;
                html += '<div class="so-city-opt py-1 px-2" data-idx="' + idx + '" ' +
                        'style="cursor:pointer;border-bottom:1px solid #f3f4f6">' +
                        '<div><strong>' + city + '</strong> ' +
                        (cc ? '<span class="badge bg-secondary-subtle text-secondary" style="font-size:.65rem">' + cc + '</span>' : '') +
                        (hasCoords ? ' <i class="ri-map-pin-2-line text-success" title="GPS" style="font-size:.7rem"></i>' : '') +
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
            setTimeout(function(){ wrap.style.display = 'none'; }, 150);
        });

        wrap.addEventListener('mousedown', function(e){
            var opt = e.target.closest('.so-city-opt');
            if (!opt) return;
            var items = JSON.parse(wrap.dataset.items || '[]');
            var it = items[parseInt(opt.dataset.idx, 10)];
            if (!it) return;
            $inp.value = it.city || it.title || $inp.value;
            var $co = getRelated(opts.country);
            if ($co && it.country) $co.value = it.country;
            var $zip = getRelated(opts.postal);
            if ($zip && it.postal_code) $zip.value = it.postal_code;
            var $lat = getRelated(opts.lat);
            if ($lat && it.lat) $lat.value = it.lat;
            var $lng = getRelated(opts.lng);
            if ($lng && it.lng) $lng.value = it.lng;
            wrap.style.display = 'none';
            $inp.dispatchEvent(new Event('change', { bubbles: true }));
        });

        window.addEventListener('resize', positionDropdown);
        window.addEventListener('scroll', positionDropdown, true);
    }

    // Static form fields (load/unload/buyer)
    attachCityAutocomplete('load_city', {
        country: 'load_country', postal: 'load_postal_code',
        lat: 'load_lat', lng: 'load_lng',
    });
    attachCityAutocomplete('unload_city', {
        country: 'unload_country', postal: 'unload_postal_code',
        lat: 'unload_lat', lng: 'unload_lng',
    });
    attachCityAutocomplete('buyer_city', {
        country: 'buyer_country', postal: 'buyer_postal_code',
    });

    // Multi-stop: attach autocomplete do wszystkich istniejacych wierszy + do nowo dodawanych
    // Uwaga: dostep przez DOM bezposrednio - $stopsAdd/$stopsList sa
    // definiowane pozniej w sekcji MULTI-STOP JS.
    window.__soAttachStopRowAutocomplete = function attachStopRowAutocomplete(row) {
        var $city    = row.querySelector('input[name$="[city]"]');
        var $country = row.querySelector('input[name$="[country_code]"], select[name$="[country_code]"]');
        var $postal  = row.querySelector('input[name$="[postal_code]"]');
        var $lat     = row.querySelector('.so-stop-lat');
        var $lng     = row.querySelector('.so-stop-lng');
        if (!$city) return;
        attachCityAutocomplete($city, {
            country: $country, postal: $postal, lat: $lat, lng: $lng,
        });
    };
    // Attach istniejacych stopow (edit mode)
    document.querySelectorAll('#so-stops-list .so-stop-row').forEach(window.__soAttachStopRowAutocomplete);
    // Attach nowo dodanych - hook przez MutationObserver na so-stops-list.
    // Rozwiazuje race condition: nie polegamy na $stopsAdd (jeszcze niezdef.).
    var $stopsListEl = document.getElementById('so-stops-list');
    if ($stopsListEl && window.MutationObserver) {
        new MutationObserver(function(mutations){
            mutations.forEach(function(m){
                m.addedNodes.forEach(function(node){
                    if (node.nodeType === 1 && node.classList && node.classList.contains('so-stop-row')) {
                        window.__soAttachStopRowAutocomplete(node);
                    }
                });
            });
        }).observe($stopsListEl, { childList: true });
    }

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
    // LIVE MAPKA HERE (JS SDK 3.1)
    // =====================================================================
    var HERE_KEY = <?= json_encode($hereApiKey ?? '') ?>;
    var herePlatform = null, hereMap = null, hereMapUI = null, hereBehavior = null;
    var hereMapEl = document.getElementById('so-here-map');
    var currentPolyline = null;
    var currentMarkers = [];

    function initHereMap() {
        if (!hereMapEl || !HERE_KEY || !window.H || herePlatform) return;
        try {
            herePlatform = new H.service.Platform({ apikey: HERE_KEY });
            var layers = herePlatform.createDefaultLayers();
            hereMap = new H.Map(hereMapEl, layers.vector.normal.map, {
                center: { lat: 52.0, lng: 19.0 }, zoom: 5,
                pixelRatio: window.devicePixelRatio || 1,
            });
            window.addEventListener('resize', function(){ hereMap.getViewPort().resize(); });
            hereBehavior = new H.mapevents.Behavior(new H.mapevents.MapEvents(hereMap));
            hereMapUI = H.ui.UI.createDefault(hereMap, layers);
        } catch (e) {
            console.error('HERE map init error:', e);
        }
    }

    function clearMap() {
        if (!hereMap) return;
        if (currentPolyline) { try { hereMap.removeObject(currentPolyline); } catch(e){} currentPolyline = null; }
        currentMarkers.forEach(function(m){ try { hereMap.removeObject(m); } catch(e){} });
        currentMarkers = [];
    }

    function drawRouteOnMap(polylineStr, fromLL, toLL) {
        if (!hereMap || !polylineStr) return;
        clearMap();
        try {
            var lineString = H.geo.LineString.fromFlexiblePolyline(polylineStr);
            currentPolyline = new H.map.Polyline(lineString, {
                style: { strokeColor: '#3b82f6', lineWidth: 5 }
            });
            hereMap.addObject(currentPolyline);
            // Markers
            if (fromLL) {
                var m1 = new H.map.Marker({ lat: fromLL.lat, lng: fromLL.lng });
                hereMap.addObject(m1);
                currentMarkers.push(m1);
            }
            if (toLL) {
                var m2 = new H.map.Marker({ lat: toLL.lat, lng: toLL.lng });
                hereMap.addObject(m2);
                currentMarkers.push(m2);
            }
            // Fit to route
            hereMap.getViewModel().setLookAtData({ bounds: currentPolyline.getBoundingBox() });
        } catch (e) {
            console.error('HERE drawRoute error:', e);
        }
    }

    // Deferred init - odpal HERE gdy skrypty sie zaladuja
    (function waitForHere(tries){
        if (tries <= 0) return;
        if (window.H && window.H.service) initHereMap();
        else setTimeout(function(){ waitForHere(tries - 1); }, 200);
    })(50);

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

            // Rysuj polyline na mapie
            if (j.polyline && j.from && j.to) {
                drawRouteOnMap(j.polyline, {lat: j.from.lat, lng: j.from.lng}, {lat: j.to.lat, lng: j.to.lng});
            }
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
    // KABOTAZ CHECK (UE 1072/2009)
    // =====================================================================
    var cabotageTimer = null;
    function scheduleCabotageCheck() {
        clearTimeout(cabotageTimer);
        cabotageTimer = setTimeout(fetchCabotage, 700);
    }
    function fetchCabotage() {
        var plate    = ($form.elements.vehicle_reg.value || '').trim();
        var loadCty  = ($form.elements.load_country.value || '').trim();
        var unlCty   = ($form.elements.unload_country.value || '').trim();
        var date     = ($form.elements.date_deadline.value || '').slice(0, 10);
        if (!plate || !loadCty || loadCty !== unlCty) { return; }

        var url = '<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'cabotageCheckJson']) ?>' +
            '?vehicle_plate=' + encodeURIComponent(plate) +
            '&load_country=' + encodeURIComponent(loadCty) +
            '&unload_country=' + encodeURIComponent(unlCty) +
            (date ? '&date=' + encodeURIComponent(date) : '');
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (!j.ok || !j.applies) return;
            // Dodaj do conflictAlert jako oddzielny wpis
            var levelMap = {
                'allowed':        'success',
                'warning':        'warning',
                'limit_exceeded': 'danger',
                'no_entry':       'danger',
                'window_expired': 'warning',
            };
            var iconMap = {
                'allowed':        'ri-shield-check-line',
                'warning':        'ri-alert-line',
                'limit_exceeded': 'ri-close-shield-line',
                'no_entry':       'ri-forbid-line',
                'window_expired': 'ri-time-line',
            };
            var lvl = levelMap[j.status] || 'secondary';
            var ico = iconMap[j.status] || 'ri-truck-line';
            var body = '<i class="' + ico + ' me-1"></i>' +
                '<strong>Kabotaż (UE 1072/2009):</strong> ' + j.msg;
            if (j.entry) {
                body += '<div class="small text-muted">Wjazd międzynarodowy: <code>' + j.entry.symbol + '</code> (' + j.entry.date + ')' +
                        (j.window_end ? ' · okno do <strong>' + j.window_end + '</strong>' : '') + '</div>';
            }
            if (j.cabotage_orders && j.cabotage_orders.length > 0) {
                body += '<div class="small">Wykonane: ' +
                    j.cabotage_orders.map(function(o){ return '<code>' + o.symbol + '</code>'; }).join(', ') + '</div>';
            }
            $conflictWrap.style.display = 'block';
            // Utrzymaj poprzedni content + dopisz kabotaż
            var prev = $conflictAlert.innerHTML;
            var newBlock = '<div class="mt-2 pt-2 border-top">' + body + '</div>';
            // Jesli poprzedni content byl inny alert, dokonaj podwyzszenia klasy
            if (!prev.includes('Kabotaż')) {
                $conflictAlert.innerHTML = prev + newBlock;
            } else {
                // Zamien tylko sekcje kabotazu
                $conflictAlert.innerHTML = prev.replace(/<div class="mt-2 pt-2 border-top">[\s\S]*?<\/div>\s*$/, newBlock);
            }
            // Podnies severity ramki jesli kabotaz danger
            if (lvl === 'danger' && !$conflictAlert.className.includes('alert-danger')) {
                $conflictAlert.className = 'alert alert-danger py-2 px-3 mb-0 small';
            } else if (lvl === 'warning' && !$conflictAlert.className.match(/alert-(danger|warning)/)) {
                $conflictAlert.className = 'alert alert-warning py-2 px-3 mb-0 small';
            }
        }).catch(function(){});
    }

    // Trigger po zmianie: pojazd + kraje
    ['vehicle_reg','load_country','unload_country','date_deadline'].forEach(function(f){
        if ($form.elements[f]) {
            $form.elements[f].addEventListener('change', scheduleCabotageCheck);
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
    var $aiClear   = document.getElementById('so-ai-clear');
    var aiImageB64 = null;       // legacy - nadal wypelniamy dla back-compat
    var aiPdfPages = [];         // WSZYSTKIE strony (PDF + obrazy) jako dataURL
    var aiFileList = [];         // Metadata plikow: {name, type, pageCount}
    var MAX_PAGES  = 10;         // Twardy limit stron (max_tokens budget)
    var MAX_FILE_MB = 15;

    // Render pojedynczej strony PDF na canvas -> dataURL PNG
    function renderPdfPage(pdf, pageNum, maxW) {
        return pdf.getPage(pageNum).then(function(page){
            var viewport = page.getViewport({ scale: 1 });
            var scale = Math.min(maxW / viewport.width, 2.0);
            var scaled = page.getViewport({ scale: scale });
            var canvas = document.createElement('canvas');
            canvas.width = scaled.width;
            canvas.height = scaled.height;
            var ctx = canvas.getContext('2d');
            return page.render({ canvasContext: ctx, viewport: scaled }).promise.then(function(){
                return canvas.toDataURL('image/png');
            });
        });
    }

    function updatePreview() {
        var html = '';
        aiPdfPages.forEach(function(dataUrl, i){
            var meta = aiFileList[i] || {};
            html += '<div style="text-align:center;position:relative;">' +
                    '<img src="' + dataUrl + '" style="max-width:140px;max-height:180px;border:1px solid #d1d5db;border-radius:.3rem;background:#fff">' +
                    '<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 so-ai-page-rm" data-idx="' + i + '" style="width:22px;height:22px;padding:0;line-height:1;transform:translate(30%,-30%);border-radius:50%">×</button>' +
                    '<div class="so-hint text-truncate" style="max-width:140px" title="' + (meta.name || '?') + '">' + (meta.name || '?') + '</div>' +
                    '<div class="so-hint">' + (meta.info || 'obraz') + '</div>' +
                    '</div>';
        });
        $aiPreview.innerHTML = html;
        aiImageB64 = aiPdfPages[0] || null; // back-compat
    }

    async function processPdfFile(f) {
        if (!window.pdfjsLib) {
            throw new Error('Biblioteka pdf.js jeszcze się ładuje');
        }
        var buf = await f.arrayBuffer();
        var pdf = await pdfjsLib.getDocument({ data: buf }).promise;
        var toRender = Math.min(pdf.numPages, 3);
        var pages = [];
        for (var i = 1; i <= toRender; i++) {
            if (aiPdfPages.length + pages.length >= MAX_PAGES) break;
            var dataUrl = await renderPdfPage(pdf, i, 1600);
            pages.push(dataUrl);
        }
        return { pages: pages, totalPages: pdf.numPages };
    }

    function readImageFile(f) {
        return new Promise(function(resolve, reject){
            var reader = new FileReader();
            reader.onload = function(ev){ resolve(ev.target.result); };
            reader.onerror = reject;
            reader.readAsDataURL(f);
        });
    }

    async function handleFiles(fileList) {
        if (!fileList || !fileList.length) return;
        $aiStatus.className = 'alert alert-info py-2 px-3 mb-0 small';
        $aiStatus.innerHTML = '<i class="ri-loader-4-line spin me-1"></i>Przetwarzanie ' + fileList.length + ' plik(ów)...';
        $aiStatus.classList.remove('d-none');

        for (var f of fileList) {
            if (aiPdfPages.length >= MAX_PAGES) {
                $aiStatus.className = 'alert alert-warning py-2 px-3 mb-0 small';
                $aiStatus.textContent = 'Osiągnięto limit ' + MAX_PAGES + ' stron - pominięto pozostałe pliki.';
                break;
            }
            if (f.size > MAX_FILE_MB * 1024 * 1024) {
                $aiStatus.className = 'alert alert-warning py-2 px-3 mb-0 small';
                $aiStatus.textContent = 'Pominięto ' + f.name + ' (za duży, max ' + MAX_FILE_MB + ' MB)';
                continue;
            }
            try {
                if (f.type === 'application/pdf' || /\.pdf$/i.test(f.name)) {
                    var r = await processPdfFile(f);
                    r.pages.forEach(function(url, i){
                        aiPdfPages.push(url);
                        aiFileList.push({ name: f.name, type: 'pdf', info: 'PDF str.' + (i+1) + '/' + r.totalPages });
                    });
                } else {
                    var url = await readImageFile(f);
                    aiPdfPages.push(url);
                    aiFileList.push({ name: f.name, type: 'image', info: 'obraz' });
                }
            } catch (e) {
                console.error('Blad przetworzenia ' + f.name + ':', e);
            }
        }
        updatePreview();
        var pdfCount = aiFileList.filter(function(m){ return m.type === 'pdf'; }).length;
        var imgCount = aiFileList.filter(function(m){ return m.type === 'image'; }).length;
        $aiStatus.className = 'alert alert-success py-2 px-3 mb-0 small';
        $aiStatus.textContent = 'Gotowe: ' + aiPdfPages.length + ' stron (' + pdfCount + ' PDF, ' + imgCount + ' zrzutów). Kliknij "Przeanalizuj".';
    }

    if ($aiImage) {
        $aiImage.addEventListener('change', function(e){
            handleFiles(e.target.files);
            $aiImage.value = ''; // reset input zeby ten sam plik dwa razy dziala
        });
    }

    // Click w minuse na thumbnail -> usun strone
    $aiPreview.addEventListener('click', function(e){
        var btn = e.target.closest('.so-ai-page-rm');
        if (!btn) return;
        var i = parseInt(btn.dataset.idx, 10);
        aiPdfPages.splice(i, 1);
        aiFileList.splice(i, 1);
        updatePreview();
    });

    // Clear all
    if ($aiClear) {
        $aiClear.addEventListener('click', function(){
            aiPdfPages = [];
            aiFileList = [];
            aiImageB64 = null;
            $aiPreview.innerHTML = '';
            $aiStatus.classList.add('d-none');
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
            if (!text && !aiImageB64 && aiPdfPages.length === 0) {
                $aiStatus.className = 'alert alert-warning py-2 px-3 mb-0 small';
                $aiStatus.textContent = 'Wklej email lub dodaj screenshot/PDF';
                $aiStatus.classList.remove('d-none');
                return;
            }
            $aiBtn.disabled = true;
            $aiStatus.className = 'alert alert-info py-2 px-3 mb-0 small';
            var pagesCount = aiPdfPages.length;
            $aiStatus.innerHTML = '<i class="ri-loader-4-line spin me-1"></i>Analiza AI' +
                (pagesCount > 0 ? ' (' + pagesCount + ' stron PDF)' : '') + '... (10-30 sek)';
            $aiStatus.classList.remove('d-none');
            $aiResult.classList.add('d-none');

            var fd = new FormData();
            fd.append('_csrfToken', CSRF);
            if (text) fd.append('text', text);
            // Wszystkie strony (PDF+obrazy) idą jako image_pages[]
            // (backend uzywa pierwszej jako primary + reszty jako extraImages)
            aiPdfPages.forEach(function(pageDataUrl){
                fd.append('image_pages[]', pageDataUrl);
            });

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
                    'load_address': 'load_address',
                    'date_deadline': 'date_deadline',
                    'unload_country': 'unload_country',
                    'unload_city': 'unload_city',
                    'unload_postal_code': 'unload_postal_code',
                    'unload_address': 'unload_address',
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
                    'payment_days': 'payment_days',
                    'required_vehicle_type': 'required_vehicle_type',
                    'pallets_exchange_count': 'pallets_exchange_count',
                    'docs_return_days': 'docs_return_days',
                    'load_time_from': 'load_time_from',
                    'load_time_to': 'load_time_to',
                    'unload_time_from': 'unload_time_from',
                    'unload_time_to': 'unload_time_to',
                    'load_contact_name': 'load_contact_name',
                    'load_contact_phone': 'load_contact_phone',
                    'load_contact_email': 'load_contact_email',
                    'unload_contact_name': 'unload_contact_name',
                    'unload_contact_phone': 'unload_contact_phone',
                    'unload_contact_email': 'unload_contact_email',
                    'driver_instructions': 'driver_instructions',
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
                // Special: pallets_exchange boolean checkbox
                if (typeof d.pallets_exchange === 'boolean' || d.pallets_exchange === 1 || d.pallets_exchange === 'true') {
                    var $ex = document.getElementById('so-pallets-ex');
                    if ($ex) {
                        $ex.checked = !!d.pallets_exchange;
                        $ex.dispatchEvent(new Event('change'));
                        filled.push('pallets_exchange: ' + $ex.checked);
                    }
                }

                // Special: stops[] - multi-stop trasy (LTL, TOSCA, itp)
                // Pierwszy pickup -> primary load_*, ostatni delivery -> primary unload_*,
                // reszta -> speed_order_stops[] table (JS multi-stop)
                if (Array.isArray(d.stops) && d.stops.length > 0) {
                    var stops = d.stops.filter(function(s){ return s && (s.city || s.address || s.postal_code); });
                    if (stops.length > 0) {
                        var pickups = stops.filter(function(s){ return s.stop_type === 'pickup' || s.stop_type === 'loading'; });
                        var deliveries = stops.filter(function(s){ return s.stop_type === 'delivery' || s.stop_type === 'unloading'; });
                        var firstPickup   = pickups[0] || stops[0];
                        var lastDelivery  = deliveries[deliveries.length - 1] || stops[stops.length - 1];

                        // Primary load
                        if (firstPickup) {
                            if (firstPickup.country_code)    $form.elements.load_country.value = firstPickup.country_code;
                            if (firstPickup.postal_code)     $form.elements.load_postal_code.value = firstPickup.postal_code;
                            if (firstPickup.city)            $form.elements.load_city.value = firstPickup.city;
                            if (firstPickup.address)         $form.elements.load_address.value = firstPickup.address;
                            if (firstPickup.planned_at)      $form.elements.date_deadline.value = firstPickup.planned_at;
                            if (firstPickup.time_from)       $form.elements.load_time_from.value = firstPickup.time_from;
                            if (firstPickup.time_to)         $form.elements.load_time_to.value = firstPickup.time_to;
                            if (firstPickup.contact_name)    $form.elements.load_contact_name.value = firstPickup.contact_name;
                            if (firstPickup.contact_phone)   $form.elements.load_contact_phone.value = firstPickup.contact_phone;
                        }
                        // Primary unload
                        if (lastDelivery) {
                            if (lastDelivery.country_code)   $form.elements.unload_country.value = lastDelivery.country_code;
                            if (lastDelivery.postal_code)    $form.elements.unload_postal_code.value = lastDelivery.postal_code;
                            if (lastDelivery.city)           $form.elements.unload_city.value = lastDelivery.city;
                            if (lastDelivery.address)        $form.elements.unload_address.value = lastDelivery.address;
                            if (lastDelivery.place_name)     $form.elements.unload_name.value = lastDelivery.place_name;
                            if (lastDelivery.planned_at)     $form.elements.date_delivery.value = lastDelivery.planned_at;
                            if (lastDelivery.time_from)      $form.elements.unload_time_from.value = lastDelivery.time_from;
                            if (lastDelivery.time_to)        $form.elements.unload_time_to.value = lastDelivery.time_to;
                            if (lastDelivery.contact_name)   $form.elements.unload_contact_name.value = lastDelivery.contact_name;
                            if (lastDelivery.contact_phone)  $form.elements.unload_contact_phone.value = lastDelivery.contact_phone;
                        }
                        // Middle stops -> speed_order_stops[]
                        var middle = stops.filter(function(s){
                            return s !== firstPickup && s !== lastDelivery;
                        });
                        middle.forEach(function(stop){
                            var idx = stopIdx++;
                            $stopsList.insertAdjacentHTML('beforeend', stopRowHtml(idx));
                            var row = $stopsList.querySelector('.so-stop-row[data-idx="' + idx + '"]');
                            if (!row) return;
                            function setF(sel, val) {
                                var el = row.querySelector(sel);
                                if (el && val !== null && val !== undefined && val !== '') el.value = val;
                            }
                            var stopType = stop.stop_type;
                            if (stopType === 'loading') stopType = 'pickup';
                            if (stopType === 'unloading') stopType = 'delivery';
                            setF('select[name$="[stop_type]"]', stopType || 'delivery');
                            setF('[name$="[country_code]"]', stop.country_code);
                            setF('[name$="[postal_code]"]', stop.postal_code);
                            setF('[name$="[city]"]', stop.city);
                            setF('[name$="[place_name]"]', stop.place_name || stop.address);
                            setF('[name$="[planned_at]"]', stop.planned_at);
                            setF('[name$="[time_from]"]', stop.time_from);
                            setF('[name$="[time_to]"]', stop.time_to);
                            setF('[name$="[contact_name]"]', stop.contact_name);
                            setF('[name$="[contact_phone]"]', stop.contact_phone);
                            setF('[name$="[contact_email]"]', stop.contact_email);
                            setF('[name$="[cargo_notes]"]', stop.cargo_notes);
                            setF('.so-stop-lat', stop.lat);
                            setF('.so-stop-lng', stop.lng);
                        });
                        // Odswiez licznik + trigger autocomplete attach dla nowych wierszy
                        renumberStops();
                        filled.push('stops: ' + stops.length + ' punktow (' + (middle.length ? middle.length + ' posrednich' : 'A→B') + ')');
                    }
                }

                // Special: cargo_items[] - tablica pozycji ladunku
                if (Array.isArray(d.cargo_items) && d.cargo_items.length > 0) {
                    // Wyczysc obecne wiersze tylko jesli sa puste, inaczej append
                    var existingRows = $cargoList.querySelectorAll('.so-cargo-row').length;
                    d.cargo_items.forEach(function(ci){
                        var idx = cargoIdx++;
                        $cargoList.insertAdjacentHTML('beforeend', cargoRowHtml(idx));
                        var row = $cargoList.querySelector('.so-cargo-row[data-idx="' + idx + '"]');
                        if (!row) return;
                        function setF(sel, val) {
                            var el = row.querySelector(sel);
                            if (el && val !== null && val !== undefined) el.value = val;
                        }
                        function setChk(sel, val) {
                            var el = row.querySelector(sel);
                            if (el) el.checked = !!val;
                        }
                        setF('[name$="[product_code]"]', ci.product_code || '');
                        setF('[name$="[product_name]"]', ci.product_name || '');
                        // Paleta: auto-match dropdown po pallet_code + zapisz fallback do hidden
                        if (ci.pallet_code) {
                            var codeUp = String(ci.pallet_code).toUpperCase();
                            var sel = row.querySelector('select[name$="[pallet_type_id]"]');
                            if (sel) {
                                var matched = false;
                                Array.from(sel.options).forEach(function(opt){
                                    if (opt.dataset.code && opt.dataset.code.toUpperCase() === codeUp) {
                                        sel.value = opt.value;
                                        matched = true;
                                    }
                                });
                            }
                            var hid = row.querySelector('[name$="[pallet_code]"]');
                            if (hid) hid.value = ci.pallet_code;
                        }
                        setChk('[name$="[is_dry]"]', ci.is_dry);
                        setChk('[name$="[is_wrapped]"]', ci.is_wrapped);
                        setChk('[name$="[is_strapped]"]', ci.is_strapped);
                        setChk('[name$="[is_sort_only]"]', ci.is_sort_only);
                        setF('[name$="[stack_height]"]', ci.stack_height);
                        setF('[name$="[qty_advised]"]', ci.qty_advised);
                        setF('[name$="[qty_real]"]', ci.qty_real);
                        setF('[name$="[weight_kg]"]', ci.weight_kg);
                        setF('[name$="[unit]"]', ci.unit || 'szt');
                    });
                    recalcCargoSummary();
                    filled.push('cargo_items: ' + d.cargo_items.length + ' pozycji');
                }
                $aiSummary.innerHTML = '<strong>Wypelnione pola:</strong><br>' + filled.join('<br>');
                calc(); // przelicz VAT/brutto z nowego netto
                onCur(); // update kurs jesli waluta zmieniona
                schedulePricingCheck();
                scheduleHereRoute();
                if (d.buyer_nip) { checkLastForBuyer(d.buyer_nip); fetchBuyerProfile(d.buyer_nip); fetchCredit(d.buyer_nip); }

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

    // =====================================================================
    // MULTI-STOP: dodawanie/usuwanie stopow posrednich
    // =====================================================================
    var $stopsList  = document.getElementById('so-stops-list');
    var $stopsAdd   = document.getElementById('so-stops-add');
    var $stopsEmpty = document.getElementById('so-stops-empty');
    var stopIdx     = $stopsList.querySelectorAll('.so-stop-row').length;

    function stopRowHtml(idx) {
        return '<div class="so-stop-row border rounded p-2 mb-2" data-idx="' + idx + '">' +
            '<div class="row g-2 align-items-end">' +
                '<div class="col-md-1"><label class="form-label small text-muted mb-0">Typ</label>' +
                '<select name="speed_order_stops[' + idx + '][stop_type]" class="form-select form-select-sm">' +
                '<option value="delivery">Rozład.</option><option value="pickup">Załad.</option><option value="transit">Transit</option>' +
                '</select></div>' +
                '<div class="col-md-1"><label class="form-label small text-muted mb-0">Kraj</label>' +
                '<input type="text" name="speed_order_stops[' + idx + '][country_code]" class="form-control form-control-sm" maxlength="5" placeholder="PL"></div>' +
                '<div class="col-md-1"><label class="form-label small text-muted mb-0">Kod</label>' +
                '<input type="text" name="speed_order_stops[' + idx + '][postal_code]" class="form-control form-control-sm" maxlength="20"></div>' +
                '<div class="col-md-2"><label class="form-label small text-muted mb-0">Miasto</label>' +
                '<input type="text" name="speed_order_stops[' + idx + '][city]" class="form-control form-control-sm" maxlength="120"></div>' +
                '<div class="col-md-2"><label class="form-label small text-muted mb-0">Miejsce/adres</label>' +
                '<input type="text" name="speed_order_stops[' + idx + '][place_name]" class="form-control form-control-sm" maxlength="200"></div>' +
                '<div class="col-md-2"><label class="form-label small text-muted mb-0">Planowany czas</label>' +
                '<input type="datetime-local" name="speed_order_stops[' + idx + '][planned_at]" class="form-control form-control-sm"></div>' +
                '<div class="col-md-2"><label class="form-label small text-muted mb-0">Uwagi/palety</label>' +
                '<input type="text" name="speed_order_stops[' + idx + '][cargo_notes]" class="form-control form-control-sm"></div>' +
                '<div class="col-md-1 text-end">' +
                '<input type="hidden" name="speed_order_stops[' + idx + '][stop_index]" value="' + (idx + 1) + '" class="so-stop-idx">' +
                '<button type="button" class="btn btn-sm btn-outline-danger so-stop-remove"><i class="ri-delete-bin-line"></i></button>' +
                '</div>' +
                // Drugi rzad: okno + kontakt + GPS
                '<div class="col-md-1"><label class="form-label small text-muted mb-0">Okno od</label>' +
                '<input type="time" name="speed_order_stops[' + idx + '][time_from]" class="form-control form-control-sm"></div>' +
                '<div class="col-md-1"><label class="form-label small text-muted mb-0">Okno do</label>' +
                '<input type="time" name="speed_order_stops[' + idx + '][time_to]" class="form-control form-control-sm"></div>' +
                '<div class="col-md-2"><label class="form-label small text-muted mb-0">Kontakt imię</label>' +
                '<input type="text" name="speed_order_stops[' + idx + '][contact_name]" class="form-control form-control-sm" maxlength="120"></div>' +
                '<div class="col-md-2"><label class="form-label small text-muted mb-0">Telefon</label>' +
                '<input type="tel" name="speed_order_stops[' + idx + '][contact_phone]" class="form-control form-control-sm" maxlength="40"></div>' +
                '<div class="col-md-2"><label class="form-label small text-muted mb-0">Email</label>' +
                '<input type="email" name="speed_order_stops[' + idx + '][contact_email]" class="form-control form-control-sm" maxlength="180"></div>' +
                '<div class="col-md-1"><label class="form-label small text-muted mb-0">GPS lat</label>' +
                '<input type="number" step="0.0000001" name="speed_order_stops[' + idx + '][lat]" class="form-control form-control-sm so-stop-lat" placeholder="52.229..."></div>' +
                '<div class="col-md-1"><label class="form-label small text-muted mb-0">GPS lng</label>' +
                '<input type="number" step="0.0000001" name="speed_order_stops[' + idx + '][lng]" class="form-control form-control-sm so-stop-lng" placeholder="21.012..."></div>' +
            '</div></div>';
    }

    function renumberStops() {
        $stopsList.querySelectorAll('.so-stop-row').forEach(function(row, i){
            var idxInput = row.querySelector('.so-stop-idx');
            if (idxInput) idxInput.value = i + 1;
        });
        var hasStops = $stopsList.querySelectorAll('.so-stop-row').length > 0;
        $stopsEmpty.style.display = hasStops ? 'none' : '';
    }

    $stopsAdd.addEventListener('click', function(){
        $stopsList.insertAdjacentHTML('beforeend', stopRowHtml(stopIdx++));
        renumberStops();
    });

    $stopsList.addEventListener('click', function(e){
        var rm = e.target.closest('.so-stop-remove');
        if (rm) {
            rm.closest('.so-stop-row').remove();
            renumberStops();
        }
    });

    // =====================================================================
    // CARGO ITEMS - pozycje ladunku
    // =====================================================================
    var $cargoList  = document.querySelector('#so-cargo-list tbody');
    var $cargoAdd   = document.getElementById('so-cargo-add');
    var $cargoEmpty = document.getElementById('so-cargo-empty');
    var $cargoSummary = document.getElementById('so-cargo-summary');
    var $sumAdv     = document.getElementById('so-cargo-sum-adv');
    var $sumReal    = document.getElementById('so-cargo-sum-real');
    var $sumWeight  = document.getElementById('so-cargo-sum-weight');
    var cargoIdx    = $cargoList.querySelectorAll('.so-cargo-row').length;

    // Build palety options HTML (raz - cache)
    var PALLET_OPTIONS = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'code' => $p['code'], 'mfr' => $p['manufacturer'] ?? ''], $palletTypes ?? [])) ?>;
    var palletOptsHtml = '<option value=""></option>';
    PALLET_OPTIONS.forEach(function(p){
        palletOptsHtml += '<option value="' + p.id + '" data-code="' + p.code + '">' + p.code + (p.mfr ? ' (' + p.mfr + ')' : '') + '</option>';
    });

    function cargoRowHtml(idx) {
        return '<tr class="so-cargo-row" data-idx="' + idx + '">' +
            '<td><input type="text" name="speed_order_cargo_items[' + idx + '][product_code]" class="form-control form-control-sm" maxlength="60" placeholder="17"></td>' +
            '<td><input type="text" name="speed_order_cargo_items[' + idx + '][product_name]" class="form-control form-control-sm" maxlength="255" placeholder="COMBO 285 BD 5R"></td>' +
            '<td><select name="speed_order_cargo_items[' + idx + '][pallet_type_id]" class="form-select form-select-sm">' + palletOptsHtml + '</select>' +
                '<input type="hidden" name="speed_order_cargo_items[' + idx + '][pallet_code]" value=""></td>' +
            '<td class="text-center"><input type="checkbox" name="speed_order_cargo_items[' + idx + '][is_dry]" value="1" class="form-check-input"></td>' +
            '<td class="text-center"><input type="checkbox" name="speed_order_cargo_items[' + idx + '][is_wrapped]" value="1" class="form-check-input"></td>' +
            '<td class="text-center"><input type="checkbox" name="speed_order_cargo_items[' + idx + '][is_strapped]" value="1" class="form-check-input"></td>' +
            '<td class="text-center"><input type="checkbox" name="speed_order_cargo_items[' + idx + '][is_sort_only]" value="1" class="form-check-input"></td>' +
            '<td><input type="number" min="0" step="1" name="speed_order_cargo_items[' + idx + '][stack_height]" class="form-control form-control-sm text-center"></td>' +
            '<td><input type="number" min="0" step="1" name="speed_order_cargo_items[' + idx + '][qty_advised]" class="form-control form-control-sm text-center so-cargo-qty-adv"></td>' +
            '<td><input type="number" min="0" step="1" name="speed_order_cargo_items[' + idx + '][qty_real]" class="form-control form-control-sm text-center so-cargo-qty-real"></td>' +
            '<td><input type="number" min="0" step="0.001" name="speed_order_cargo_items[' + idx + '][weight_kg]" class="form-control form-control-sm text-end so-cargo-weight"></td>' +
            '<td><select name="speed_order_cargo_items[' + idx + '][unit]" class="form-select form-select-sm">' +
                '<option value="szt" selected>szt</option><option>kg</option><option>m3</option><option>palety</option><option>kartony</option><option>opak.</option>' +
            '</select></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger so-cargo-remove"><i class="ri-delete-bin-line"></i></button></td>' +
            '</tr>';
    }

    function recalcCargoSummary() {
        var rows = $cargoList.querySelectorAll('.so-cargo-row');
        var totalAdv = 0, totalReal = 0, totalW = 0, hasData = false;
        rows.forEach(function(row){
            var adv  = parseFloat(row.querySelector('[name$="[qty_advised]"]')?.value || 0) || 0;
            var real = parseFloat(row.querySelector('[name$="[qty_real]"]')?.value || 0) || 0;
            var w    = parseFloat(row.querySelector('[name$="[weight_kg]"]')?.value || 0) || 0;
            totalAdv += adv; totalReal += real; totalW += w;
            if (adv || real || w) hasData = true;
        });
        $sumAdv.textContent = hasData && totalAdv > 0 ? totalAdv.toLocaleString('pl-PL') : '-';
        $sumReal.textContent = hasData && totalReal > 0 ? totalReal.toLocaleString('pl-PL') : '-';
        $sumWeight.textContent = hasData && totalW > 0 ? totalW.toLocaleString('pl-PL', {maximumFractionDigits: 2}) + ' kg' : '-';
        $cargoSummary.style.display = rows.length > 0 ? '' : 'none';
        $cargoEmpty.style.display = rows.length > 0 ? 'none' : '';

        // Auto-fill cargo_weight_kg z sumy jesli puste albo user chce (opcjonalnie - komentuj z uwagi)
        var $cargoWeight = $form.elements.cargo_weight_kg;
        if ($cargoWeight && !$cargoWeight.dataset.userTouched && totalW > 0) {
            $cargoWeight.value = Math.round(totalW);
        }
    }

    $cargoAdd.addEventListener('click', function(){
        $cargoList.insertAdjacentHTML('beforeend', cargoRowHtml(cargoIdx++));
        recalcCargoSummary();
    });

    $cargoList.addEventListener('click', function(e){
        var rm = e.target.closest('.so-cargo-remove');
        if (rm) {
            rm.closest('.so-cargo-row').remove();
            recalcCargoSummary();
        }
    });

    // Live sum on input
    $cargoList.addEventListener('input', function(e){
        if (e.target && e.target.matches('input[type=number]')) {
            recalcCargoSummary();
        }
    });

    // Mark cargo_weight_kg as user-touched to prevent auto-overwrite
    if ($form.elements.cargo_weight_kg) {
        $form.elements.cargo_weight_kg.addEventListener('input', function(){
            this.dataset.userTouched = '1';
        });
    }

    // Initial calc
    recalcCargoSummary();

    // =====================================================================
    // TEMPLATES ZLECEN (szablony)
    // =====================================================================
    var $tplModal    = document.getElementById('so-tpl-modal');
    var $tplStatus   = document.getElementById('so-tpl-status');
    var $tplList     = document.getElementById('so-tpl-list');
    var $tplSaveBtn  = document.getElementById('so-tpl-save-btn');
    var $tplSaveModal = document.getElementById('so-tpl-save-modal');
    var $tplName     = document.getElementById('so-tpl-name');
    var $tplDesc     = document.getElementById('so-tpl-desc');
    var $tplSaveConfirm = document.getElementById('so-tpl-save-confirm');
    var $tplSaveStatus = document.getElementById('so-tpl-save-status');

    // Pola do zapisu w templacie (skopiuj z aktualnego formularza)
    var TPL_FIELDS = [
        'contract', 'buyer_nip', 'buyer_name', 'buyer_email', 'buyer_street',
        'buyer_postal_code', 'buyer_city', 'buyer_country',
        'load_country', 'load_postal_code', 'load_city', 'load_address',
        'load_time_from', 'load_time_to', 'load_contact_name', 'load_contact_phone', 'load_contact_email',
        'unload_country', 'unload_postal_code', 'unload_city', 'unload_address', 'unload_name',
        'unload_time_from', 'unload_time_to', 'unload_contact_name', 'unload_contact_phone', 'unload_contact_email',
        'title2', 'cargo_type', 'transport_type', 'required_vehicle_type',
        'cargo_weight_kg', 'cargo_volume_m3', 'cargo_ldm', 'cargo_pallets', 'cargo_pallet_type',
        'pallets_exchange', 'pallets_exchange_count', 'docs_return_days',
        'adr_class', 'adr_un', 'temperature_min', 'temperature_max',
        'incoterms', 'incoterms_place',
        'driver', 'vehicle_reg', 'carrier',
        'currency', 'netto', 'payment_terms', 'payment_days',
        'notes', 'driver_instructions',
    ];

    function collectTemplatePayload() {
        var out = {};
        TPL_FIELDS.forEach(function(f){
            var el = $form.elements[f];
            if (el && el.value) out[f] = el.value;
        });
        return out;
    }

    function applyTemplate(payload) {
        Object.keys(payload).forEach(function(k){
            var el = $form.elements[k];
            if (el && payload[k]) el.value = payload[k];
        });
        calc(); onCur();
        schedulePricingCheck(); scheduleHereRoute(); scheduleConflictCheck();
        if (payload.buyer_nip) { checkLastForBuyer(payload.buyer_nip); fetchBuyerProfile(payload.buyer_nip); }
    }

    if ($tplModal) {
        $tplModal.addEventListener('shown.bs.modal', function(){
            $tplStatus.className = 'alert alert-info py-2 px-3 small';
            $tplStatus.textContent = 'Ładowanie...';
            $tplList.innerHTML = '';
            fetch('<?= $this->Url->build(['controller' => 'SpeedOrders', 'action' => 'templatesListJson']) ?>',
                  { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (!j.ok) {
                    $tplStatus.className = 'alert alert-danger py-2 px-3 small';
                    $tplStatus.textContent = j.error || 'Blad';
                    return;
                }
                if (!j.templates || j.templates.length === 0) {
                    $tplStatus.className = 'alert alert-warning py-2 px-3 small';
                    $tplStatus.textContent = 'Brak zapisanych szablonów. Wypełnij formularz i kliknij ikonę zakładki obok "Szablony zleceń" żeby zapisać nowy.';
                    return;
                }
                $tplStatus.className = 'alert alert-success py-2 px-3 small';
                $tplStatus.textContent = 'Znaleziono ' + j.templates.length + ' szablonów. Kliknij "Załaduj" aby prefillować.';
                var html = '';
                j.templates.forEach(function(t){
                    var fav = t.is_favorite ? 'ri-bookmark-fill text-warning' : 'ri-bookmark-line text-muted';
                    var routeHint = '';
                    if (t.payload && t.payload.load_city) {
                        routeHint = '<span class="text-muted">' + t.payload.load_city +
                                    (t.payload.unload_city ? ' → ' + t.payload.unload_city : '') + '</span>';
                    }
                    var buyerHint = t.payload && t.payload.buyer_name
                        ? '<span class="badge bg-light text-dark border ms-1">' + t.payload.buyer_name + '</span>' : '';
                    html += '<div class="list-group-item d-flex justify-content-between align-items-start" data-tpl-id="' + t.id + '">' +
                        '<div class="flex-grow-1 me-2">' +
                            '<div><strong>' + t.name + '</strong> ' + buyerHint + '</div>' +
                            '<div class="small">' + routeHint +
                                (t.description ? '<br><em class="text-muted">' + t.description + '</em>' : '') +
                                '<span class="text-muted ms-2">użyty ' + t.usage_count + 'x' +
                                (t.last_used_at ? ' · ost. ' + t.last_used_at : '') + '</span></div>' +
                        '</div>' +
                        '<div class="btn-group btn-group-sm">' +
                            '<button type="button" class="btn btn-sm btn-outline-warning so-tpl-fav" data-id="' + t.id + '" title="Ulubione"><i class="' + fav + '"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-outline-danger so-tpl-del" data-id="' + t.id + '" title="Usuń"><i class="ri-delete-bin-line"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-primary so-tpl-apply" data-id="' + t.id + '"><i class="ri-download-2-line me-1"></i>Załaduj</button>' +
                        '</div>' +
                        '</div>';
                });
                $tplList.innerHTML = html;

                // Cache payloadow do applyTemplate
                $tplList.dataset.payloads = JSON.stringify(j.templates.reduce(function(acc, t){
                    acc[t.id] = t.payload; return acc;
                }, {}));
            });
        });
    }

    $tplList.addEventListener('click', function(e){
        var applyBtn = e.target.closest('.so-tpl-apply');
        if (applyBtn) {
            var id = applyBtn.dataset.id;
            var payloads = JSON.parse($tplList.dataset.payloads || '{}');
            applyTemplate(payloads[id] || {});
            // increment usage_count
            var fd = new FormData(); fd.append('_csrfToken', CSRF);
            fetch('/zlecenia/szablony/' + id + '/uzyj', {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' }
            });
            var m = bootstrap.Modal.getInstance($tplModal);
            if (m) m.hide();
            return;
        }
        var favBtn = e.target.closest('.so-tpl-fav');
        if (favBtn) {
            var id = favBtn.dataset.id;
            var fd = new FormData(); fd.append('_csrfToken', CSRF);
            fetch('/zlecenia/szablony/' + id + '/favorite', {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' }
            })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (j.ok) {
                    var icon = favBtn.querySelector('i');
                    icon.className = j.is_favorite ? 'ri-bookmark-fill text-warning' : 'ri-bookmark-line text-muted';
                }
            });
            return;
        }
        var delBtn = e.target.closest('.so-tpl-del');
        if (delBtn) {
            if (!confirm('Usunąć szablon?')) return;
            var id = delBtn.dataset.id;
            var fd = new FormData(); fd.append('_csrfToken', CSRF);
            fetch('/zlecenia/szablony/' + id + '/usun', {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' }
            })
            .then(function(r){ return r.json(); })
            .then(function(j){
                if (j.ok) {
                    var row = delBtn.closest('.list-group-item');
                    if (row) row.remove();
                }
            });
        }
    });

    // Save current form as template
    $tplSaveBtn.addEventListener('click', function(){
        var payload = collectTemplatePayload();
        if (Object.keys(payload).length === 0) {
            alert('Formularz jest pusty - wypełnij dane przed zapisem jako szablon');
            return;
        }
        // Prefill name z klienta+trasy jesli sa
        var suggestedName = '';
        if (payload.buyer_name) suggestedName = payload.buyer_name;
        if (payload.load_city && payload.unload_city) {
            suggestedName += (suggestedName ? ' · ' : '') + payload.load_city + '-' + payload.unload_city;
        }
        $tplName.value = suggestedName;
        $tplDesc.value = '';
        $tplSaveStatus.innerHTML = '';
        var m = new bootstrap.Modal($tplSaveModal);
        m.show();
    });

    $tplSaveConfirm.addEventListener('click', function(){
        var name = $tplName.value.trim();
        if (!name) {
            $tplSaveStatus.innerHTML = '<span class="text-danger">Podaj nazwę</span>';
            return;
        }
        var payload = collectTemplatePayload();
        var fd = new FormData();
        fd.append('_csrfToken', CSRF);
        fd.append('name', name);
        fd.append('description', $tplDesc.value.trim());
        fd.append('payload_json', JSON.stringify(payload));
        $tplSaveConfirm.disabled = true;
        $tplSaveStatus.innerHTML = '<i class="ri-loader-4-line spin"></i> zapisuje...';
        fetch('/zlecenia/szablony/zapisz', {
            method: 'POST', body: fd, credentials: 'same-origin',
            headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' }
        })
        .then(function(r){ return r.json(); })
        .then(function(j){
            $tplSaveConfirm.disabled = false;
            if (j.ok) {
                $tplSaveStatus.innerHTML = '<span class="text-success"><i class="ri-check-line"></i> Zapisano!</span>';
                setTimeout(function(){
                    var m = bootstrap.Modal.getInstance($tplSaveModal);
                    if (m) m.hide();
                }, 1500);
            } else {
                $tplSaveStatus.innerHTML = '<span class="text-danger">Błąd: ' + (j.error || 'nieznany') + '</span>';
            }
        })
        .catch(function(e){
            $tplSaveConfirm.disabled = false;
            $tplSaveStatus.innerHTML = '<span class="text-danger">Błąd: ' + e.message + '</span>';
        });
    });

})();
</script>
<style>
.spin { animation: so-spin 1s linear infinite; display: inline-block; }
@keyframes so-spin { from { transform: rotate(0deg);} to { transform: rotate(360deg);} }
</style>

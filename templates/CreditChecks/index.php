<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $records
 * @var string $tab
 * @var string $search
 * @var string $dateFrom
 * @var string $dateTo
 * @var array $counts
 * @var array $stats
 * @var array $statusLabels
 * @var array $adviceTypes
 * @var array $adviceTypeDescriptions
 * @var array $adviceReasonDescriptions
 * @var array $errorTypes
 * @var \App\Model\Entity\CreditCheck|null $lastSync
 */

$this->assign('title', 'Kredyt kupiecki');
$csrf = (string)$this->request->getAttribute('csrfToken');

// Krótkie etykiety kodów CCCR*
$reasonShortLabels = [
    'CCCR1'  => 'Sprzeciw RODO',
    'CCCR2'  => 'Oddział zagr.',
    'CCCR3'  => 'Nowa firma',
    'CCCR4'  => 'Zakończona dz.',
    'CCCR5'  => 'Dane płatnicze',
    'CCCR6'  => 'Dane finansowe',
    'CCCR7'  => 'Wcześniejszy raport',
    'CCCR9'  => 'Brak dok. fin.',
    'CCCR10' => 'Kraj poza zakresem',
    'CCCR11' => 'Brak danych',
    'CCCR12' => 'Zdarzenie prawne',
    'CCCR13' => 'Ryzyko upadłości',
    'CCCR14' => 'Prawna niewypłac.',
    'CCCR15' => 'Postęp. układowe',
    'CCCR16' => 'Zawarcie układu',
    'CCCR17' => 'Upadłość z układem',
    'CCCR18' => 'Zatwierdzenie',
    'CCCR19' => 'Sanacja',
    'CCCR20' => 'Postęp. upadłościowe',
    'CCCR21' => 'Zatw. układu',
    'CCCR22' => 'Przysp. postęp. układowe',
    'CCCR23' => 'Restrukturyzacja',
    'CCCR24' => 'Upadłość',
    'CCCR25' => 'Sytuacja gospodarcza',
];

$activeFilterCount = ($search !== '') ? 1 : 0;
if ($tab !== 'done') $activeFilterCount++;
if (!empty($dateFrom)) $activeFilterCount++;
if (!empty($dateTo)) $activeFilterCount++;

// Etykiety tabów do tytułów przycisków (bez odwołania do prywatnej stałej controllera)
$tabLabels = [
    'done'      => 'Opinie wydane',
    'waiting'   => 'Oczekujące',
    'no-advice' => 'Brak opinii',
    'error'     => 'Błędy',
];
?>

<!-- Nagłówek -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold">
            <i class="ri-shield-check-line me-1 text-primary"></i>
            Kredyt kupiecki
            <span class="text-muted fs-6 fw-normal">Allianz Trade / Syntesys</span>
        </h4>
        <?php if ($lastSync): ?>
            <small class="text-muted">Ostatni sync: <?= $lastSync->synced_at->format('d.m.Y H:i') ?></small>
        <?php endif ?>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button id="btn-check-opinion" class="btn btn-sm btn-success">
            <i class="ri-search-eye-line me-1"></i>Sprawdź opinię
        </button>
        <button id="btn-sync" class="btn btn-sm btn-primary" data-list="all">
            <i class="ri-refresh-line me-1"></i>Synchronizuj wszystko
        </button>
    </div>
</div>

<!-- Alerty sync -->
<div id="sync-alert" class="d-none mb-3"></div>

<!-- Kafelki statystyk -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px">
                    <i class="ri-shield-check-line fs-5 text-primary"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1"><?= $stats['total'] ?></div>
                    <div class="text-muted small">Rekordów łącznie</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $this->Url->build(['action' => 'index', '?' => ['tab' => 'done', 'search' => $search ?: null]]) ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px">
                    <i class="ri-checkbox-circle-line fs-5 text-success"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 text-success"><?= $stats['done'] ?></div>
                    <div class="text-muted small">Opinie wydane</div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $this->Url->build(['action' => 'index', '?' => ['tab' => 'done', 'search' => $search ?: null]]) ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 <?= $stats['expired'] > 0 ? 'border border-danger-subtle' : '' ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px">
                    <i class="ri-time-line fs-5 text-warning"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 <?= $stats['expiringSoon'] > 0 ? 'text-warning' : '' ?>">
                        <?= $stats['expiringSoon'] ?>
                    </div>
                    <div class="text-muted small">
                        Wygasa &lt;30 dni
                        <?php if ($stats['expired'] > 0): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1"><?= $stats['expired'] ?> wygasłe</span>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $this->Url->build(['action' => 'index', '?' => ['tab' => 'error', 'search' => $search ?: null]]) ?>" class="text-decoration-none">
        <div class="card border-0 shadow-sm h-100 <?= $stats['errors'] > 0 ? 'border border-danger-subtle' : '' ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px">
                    <i class="ri-error-warning-line fs-5 text-danger"></i>
                </div>
                <div>
                    <div class="fs-4 fw-bold lh-1 <?= $stats['errors'] > 0 ? 'text-danger' : '' ?>"><?= $stats['errors'] ?></div>
                    <div class="text-muted small">Błędy</div>
                </div>
            </div>
        </div>
        </a>
    </div>
</div>

<!-- Filtry -->
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2 bg-white border-bottom">
        <i class="ri-filter-3-line text-primary"></i>
        <span class="fw-semibold small">Filtry</span>
        <?php if ($activeFilterCount > 0): ?>
            <span class="badge bg-primary rounded-pill ms-1"><?= $activeFilterCount ?></span>
        <?php endif ?>
        <button class="btn btn-link btn-sm text-muted ms-auto p-0 pe-1" type="button"
                data-bs-toggle="collapse" data-bs-target="#cc-filter-body" aria-expanded="true">
            <i class="ri-arrow-up-s-line" id="cc-filter-chevron"></i>
        </button>
    </div>
    <div class="collapse show" id="cc-filter-body">
    <div class="card-body py-2 px-3">
        <form id="cc-filter-form" method="get" action="<?= $this->Url->build(['action' => 'index']) ?>">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Szukaj</label>
                    <input type="text" name="search" value="<?= h($search) ?>"
                           class="form-control form-control-sm"
                           placeholder="NIP, VAT EU, nazwa kontrahenta…">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Data od</label>
                    <input type="date" name="date_from" value="<?= h($dateFrom ?? '') ?>" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Data do</label>
                    <input type="date" name="date_to" value="<?= h($dateTo ?? '') ?>" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label form-label-sm text-muted mb-0" style="font-size:.72rem">Status</label>
                    <select name="tab" class="form-select form-select-sm">
                        <option value="done"      <?= $tab === 'done'      ? 'selected' : '' ?>>Opinie wydane (<?= $counts['done'] ?>)</option>
                        <option value="waiting"   <?= $tab === 'waiting'   ? 'selected' : '' ?>>Oczekujące (<?= $counts['waiting'] ?>)</option>
                        <option value="no-advice" <?= $tab === 'no-advice' ? 'selected' : '' ?>>Brak opinii (<?= $counts['no-advice'] ?>)</option>
                        <option value="error"     <?= $tab === 'error'     ? 'selected' : '' ?>>Błędy (<?= $counts['error'] ?>)</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="ri-search-line me-1"></i>Filtruj
                    </button>
                    <?php if ($activeFilterCount > 0): ?>
                        <a href="<?= $this->Url->build(['action' => 'index']) ?>"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="ri-close-line me-1"></i>Wyczyść
                        </a>
                    <?php endif ?>
                    <!-- Przyciski szybkiego przełączania tabów -->
                    <div class="ms-auto d-flex gap-1 flex-wrap">
                        <?php foreach ([
                            'done'      => ['success', 'ri-checkbox-circle-line'],
                            'waiting'   => ['warning',   'ri-time-line'],
                            'no-advice' => ['secondary', 'ri-question-line'],
                            'error'     => ['danger',    'ri-error-warning-line'],
                        ] as $t => [$color, $icon]): ?>
                            <a href="<?= $this->Url->build(['action' => 'index', '?' => ['tab' => $t, 'search' => $search ?: null]]) ?>"
                               class="btn btn-xs btn-<?= $tab === $t ? $color : 'outline-' . $color ?>"
                               style="font-size:.73rem;padding:2px 8px"
                               title="<?= h($tabLabels[$t] ?? $t) ?>">
                                <i class="<?= $icon ?>"></i>
                                <span class="ms-1" style="font-size:.72rem"><?= h($tabLabels[$t] ?? $t) ?></span>
                                <span class="badge <?= $tab === $t ? 'bg-white text-' . $color : 'bg-' . $color ?> ms-1" style="font-size:.65rem"><?= $counts[$t] ?></span>
                            </a>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
    </div>
</div>

<!-- Tabela -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 text-muted fw-normal" style="width:80px">ID</th>
                        <th>NIP / VAT EU</th>
                        <th>Kontrahent</th>
                        <?php if ($tab === 'done'): ?>
                            <th>Opinia</th>
                            <th>Ważna do</th>
                        <?php elseif ($tab === 'error'): ?>
                            <th>Błąd</th>
                        <?php elseif ($tab === 'no-advice'): ?>
                            <th>Status</th>
                        <?php elseif ($tab === 'waiting'): ?>
                            <th>Status</th>
                        <?php endif ?>
                        <th>Złożono</th>
                        <th>Przez</th>
                        <th class="text-end pe-3" style="width:60px">Szczeg.</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($records->isEmpty()): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="ri-inbox-line fs-2 d-block mb-2 opacity-50"></i>
                            Brak rekordów<?= $search ? ' dla <strong>' . h($search) . '</strong>' : '' ?>.
                            <br>
                            <button class="btn btn-sm btn-outline-primary mt-3" id="btn-sync-tab"
                                    data-list="<?= h($tab) ?>">
                                <i class="ri-refresh-line me-1"></i>Synchronizuj teraz
                            </button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $rec): ?>
                        <tr>
                            <td class="ps-3 text-muted" style="font-size:.75rem"><?= h($rec->external_id) ?></td>

                            <!-- NIP -->
                            <td>
                                <?php $nip = $rec->identifier ?: $rec->client_vat_eu ?: null ?>
                                <?php if ($nip): ?>
                                    <span class="font-monospace small fw-semibold"><?= h($nip) ?></span>
                                    <?php if ($rec->identifier && $rec->client_vat_eu && $rec->client_vat_eu !== $rec->identifier): ?>
                                        <br><small class="text-muted font-monospace"><?= h($rec->client_vat_eu) ?></small>
                                    <?php endif ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                                <?php if ($rec->country && $rec->country !== 'PL'): ?>
                                    <span class="badge bg-light text-secondary border ms-1" style="font-size:.65rem"><?= h($rec->country) ?></span>
                                <?php endif ?>
                            </td>

                            <!-- Kontrahent -->
                            <td>
                                <?php if (!empty($rec->contractor_id) && !empty($rec->contractor)): ?>
                                    <?= $this->Html->link(
                                        h($rec->contractor->name ?? $rec->contractor_id),
                                        ['controller' => 'Contractors', 'action' => 'view', $rec->contractor_id],
                                        ['class' => 'text-decoration-none fw-semibold']
                                    ) ?>
                                    <?php if (!empty($rec->client_city)): ?>
                                        <br><small class="text-muted"><?= h($rec->client_city) ?></small>
                                    <?php endif ?>
                                <?php elseif (!empty($rec->client_name)): ?>
                                    <span><?= h($rec->client_name) ?></span>
                                    <?php if (!empty($rec->client_city)): ?>
                                        <br><small class="text-muted"><?= h($rec->client_city) ?></small>
                                    <?php endif ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>

                            <?php if ($tab === 'done'): ?>
                                <!-- Opinia -->
                                <td>
                                    <?php if ($rec->advice_type_code): ?>
                                        <?php $at = $adviceTypes[$rec->advice_type_code] ?? ['label' => $rec->advice_type_code, 'badge' => 'secondary'] ?>
                                        <?php $atDesc = $adviceTypeDescriptions[$rec->advice_type_code] ?? null ?>
                                        <span class="badge bg-<?= $at['badge'] ?>"
                                            <?= $atDesc ? 'title="' . h($atDesc) . '" data-bs-toggle="tooltip"' : '' ?>>
                                            <?= h($at['label']) ?>
                                        </span>
                                        <?php if ($rec->advice_reason_code): ?>
                                            <?php
                                                $crCode  = $rec->advice_reason_code;
                                                $crShort = $reasonShortLabels[$crCode] ?? $crCode;
                                                $crDesc  = $adviceReasonDescriptions[$crCode] ?? null;
                                            ?>
                                            <br>
                                            <small class="text-muted"
                                                style="font-size:.72rem"
                                                <?= $crDesc ? 'title="[' . h($crCode) . '] ' . h($crDesc) . '" data-bs-toggle="tooltip"' : '' ?>>
                                                <?= h($crShort) ?>
                                            </small>
                                        <?php endif ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif ?>
                                </td>
                                <!-- Ważna do -->
                                <td class="text-nowrap">
                                    <?php if ($rec->advice_valid_to): ?>
                                        <?php $isExpired = $rec->advice_valid_to->isPast() ?>
                                        <?php
                                            $validToStr = $rec->advice_valid_to->format('Y-m-d');
                                            $todayTs    = mktime(0, 0, 0);
                                            $validToTs  = (int)strtotime($validToStr);
                                            $daysLeft   = (int)round(($validToTs - $todayTs) / 86400);
                                            $isSoon     = !$isExpired && $daysLeft <= 30;
                                        ?>
                                        <span class="<?= $isExpired ? 'text-danger fw-semibold' : ($isSoon ? 'text-warning fw-semibold' : 'text-success') ?>">
                                            <?php if ($isExpired): ?><i class="ri-error-warning-line me-1"></i><?php elseif ($isSoon): ?><i class="ri-alarm-warning-line me-1"></i><?php endif ?>
                                            <?= $rec->advice_valid_to->format('d.m.Y') ?>
                                        </span>
                                        <?php if ($isSoon && !$isExpired): ?>
                                            <br><small class="text-muted" style="font-size:.7rem">za <?= $daysLeft ?> dni</small>
                                        <?php endif ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif ?>
                                </td>

                            <?php elseif ($tab === 'error'): ?>
                                <td>
                                    <?php if ($rec->error_type_code): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle"
                                              <?php if (!empty($errorTypes[$rec->error_type_code])): ?>
                                                  title="<?= h($errorTypes[$rec->error_type_code]) ?>" data-bs-toggle="tooltip"
                                              <?php endif ?>>
                                            <?= h($rec->error_type_code) ?>
                                        </span>
                                        <?php if (!empty($errorTypes[$rec->error_type_code])): ?>
                                            <br><small class="text-muted" style="font-size:.72rem"><?= h($errorTypes[$rec->error_type_code]) ?></small>
                                        <?php endif ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif ?>
                                </td>

                            <?php elseif ($tab === 'no-advice' || $tab === 'waiting'): ?>
                                <td>
                                    <?php if ($rec->status_code): ?>
                                        <code class="small text-muted"><?= h($rec->status_code) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif ?>
                                </td>
                            <?php endif ?>

                            <!-- Data -->
                            <td class="text-nowrap" style="font-size:.82rem">
                                <?php if ($rec->advice_created_at): ?>
                                    <?= $rec->advice_created_at->format('d.m.Y') ?>
                                    <br><small class="text-muted"><?= $rec->advice_created_at->format('H:i') ?></small>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>

                            <!-- Przez -->
                            <td>
                                <small class="text-muted" style="font-size:.75rem"><?= h($rec->created_by ?? '—') ?></small>
                            </td>

                            <!-- Akcje (tylko szczegóły — bez usuwania) -->
                            <td class="text-end pe-3">
                                <?php if ($tab === 'done' && $rec->advice_json): ?>
                                    <button class="btn btn-xs btn-outline-secondary btn-advice-details"
                                            data-json="<?= h($rec->advice_json) ?>"
                                            data-company="<?= h($rec->client_name ?? '') ?>"
                                            data-nip="<?= h($rec->identifier ?? '') ?>"
                                            title="Szczegóły opinii"
                                            data-bs-toggle="tooltip">
                                        <i class="ri-eye-line"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                <?php endif ?>
                </tbody>
            </table>
        </div>

        <!-- Paginacja -->
        <div class="px-3 py-2">
            <?= $this->element('pagination') ?>
        </div>
    </div>
</div>

<!-- Modal: szczegóły opinii -->
<div class="modal fade" id="adviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-shield-check-line me-2 text-primary"></i>Szczegóły opinii</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3" id="advice-modal-body">
                <!-- wypełniany przez JS -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
(function () {
    'use strict';

    const syncUrl         = <?= json_encode($this->Url->build(['action' => 'sync'])) ?>;
    const checkOpinionUrl = <?= json_encode($this->Url->build(['action' => 'checkOpinion'])) ?>;
    const csrfToken       = <?= json_encode($csrf) ?>;

    // ── Mapy pomocnicze kodów Syntesys (PL) ─────────────────────────────────
    const STATUS_PL = {
        'WITH_OPINION':   'Opinia wydana',
        'PROCESSING':     'W trakcie przetwarzania',
        'NO_OPINION':     'Brak opinii',
        'BUSINESS_ERROR': 'Błąd biznesowy',
    };
    const CCAT_LABEL = { 'CCAT1': 'TAK', 'CCAT2': 'NIE', 'CCAT3': 'Brak opinii' };
    const CCAT_BADGE = { 'CCAT1': 'success', 'CCAT2': 'danger', 'CCAT3': 'secondary' };
    const CCAT_DESC  = {
        'CCAT1': 'Ubezpieczyciel wyraża zgodę na współpracę z danym klientem (limit automatyczny).',
        'CCAT2': 'Ubezpieczyciel nie wyraża zgody na współpracę z danym klientem (limit automatyczny).',
    };
    const CCCR_SHORT = {
        'CCCR1':  'Sprzeciw RODO',          'CCCR2':  'Oddział zagraniczny',
        'CCCR3':  'Nowa firma',              'CCCR4':  'Zakończona działalność',
        'CCCR5':  'Dane płatnicze',          'CCCR6':  'Dane finansowe',
        'CCCR7':  'Wcześniejszy raport',     'CCCR9':  'Brak dok. finansowych',
        'CCCR10': 'Kraj poza zakresem',      'CCCR11': 'Brak wystarczających danych',
        'CCCR12': 'Zdarzenie prawne',        'CCCR13': 'Ryzyko upadłości',
        'CCCR14': 'Prawna niewypłacalność',  'CCCR15': 'Postęp. układowe',
        'CCCR16': 'Zawarcie układu',         'CCCR17': 'Upadłość z układem',
        'CCCR18': 'Zatwierdzenie układu',    'CCCR19': 'Sanacja',
        'CCCR20': 'Postęp. upadłościowe',    'CCCR21': 'Zatw. układu',
        'CCCR22': 'Przysp. postęp. układowe','CCCR23': 'Restrukturyzacja',
        'CCCR24': 'Upadłość',                'CCCR25': 'Sytuacja gospodarcza',
    };

    function htmlEsc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function fmtDate(s) {
        return s ? s.slice(0,10).split('-').reverse().join('.') : null;
    }
    function fmtDateTime(s) {
        if (!s) return null;
        return s.slice(0,10).split('-').reverse().join('.') + (s.length > 10 ? ' ' + s.slice(11,16) : '');
    }
    function tdRow(label, val) {
        return `<tr><td class="text-muted pe-3 text-nowrap align-top" style="min-width:110px">${label}</td><td>${val}</td></tr>`;
    }

    /** Buduje HTML szczegółów opinii z obiektu JSON (obsługuje oba formaty). */
    function buildAdviceHtml(json, company, nip) {
        if (!json) return '<p class="text-muted">Brak danych.</p>';

        let status, typeCode, reasonCode, validTo, advCreated, errorTypeCode;
        if (json.status && json.advice) {
            // Pełny format z checkOpinion
            status        = json.status;
            const adv     = json.advice || {};
            typeCode      = adv.typeCode    || null;
            reasonCode    = adv.reasonCode  || null;
            validTo       = adv.validTo     || null;
            advCreated    = adv.created     || json.created || null;
            errorTypeCode = json.errorTypeCode || null;
            company       = company || json.companyName || '—';
        } else {
            // Stary format (tylko advice sub-object z sync)
            typeCode      = json.typeCode   || null;
            reasonCode    = json.reasonCode || null;
            validTo       = json.validTo    || null;
            advCreated    = json.created    || null;
            status        = null;
            errorTypeCode = null;
        }

        const badge    = CCAT_BADGE[typeCode]  || 'secondary';
        const label    = CCAT_LABEL[typeCode]  || typeCode  || '—';
        const desc     = CCAT_DESC[typeCode]   || '';
        const iconCls  = badge === 'success' ? 'ri-checkbox-circle-fill' :
                         badge === 'danger'  ? 'ri-close-circle-fill' : 'ri-question-line';

        let html = `<div class="text-center mb-3">
            <span class="badge bg-${badge} px-3 py-2 rounded-3" style="font-size:1.15rem">
                <i class="${iconCls} me-1"></i>${htmlEsc(label)}
            </span>
            <div class="text-muted small mt-1"><code>${htmlEsc(typeCode || '—')}</code></div>
            ${desc ? `<div class="text-muted small mt-1 fst-italic" style="max-width:320px;margin:0 auto">${htmlEsc(desc)}</div>` : ''}
        </div>`;

        let rows = '';
        if (company)      rows += tdRow('Firma',         `<strong>${htmlEsc(company)}</strong>`);
        if (nip)          rows += tdRow('NIP',           `<code>${htmlEsc(nip)}</code>`);
        if (status)       rows += tdRow('Status',        htmlEsc(STATUS_PL[status] || status));
        if (reasonCode)   rows += tdRow('Powód odmowy',  `<span class="badge bg-warning text-dark me-1">${htmlEsc(reasonCode)}</span>${htmlEsc(CCCR_SHORT[reasonCode] || reasonCode)}`);
        if (advCreated)   rows += tdRow('Data wydania',  htmlEsc(fmtDateTime(advCreated) || ''));
        if (validTo)      rows += tdRow('Ważna do',      `<strong class="text-${badge === 'danger' ? 'danger' : 'success'}">${htmlEsc(fmtDate(validTo) || validTo)}</strong>`);
        if (errorTypeCode)rows += tdRow('Kod błędu',     `<span class="badge bg-danger">${htmlEsc(errorTypeCode)}</span>`);

        if (rows) {
            html += `<table class="table table-sm table-borderless mb-0 mx-auto" style="max-width:380px">${rows}</table>`;
        }
        return html;
    }

    function showAlert(type, html) {
        const el = document.getElementById('sync-alert');
        el.className = 'alert alert-' + type + ' alert-dismissible fade show';
        el.innerHTML = html + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        el.classList.remove('d-none');
    }

    function runSync(list, btn) {
        const origHtml = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Synchronizuję…';

        showAlert('info', '<i class="ri-refresh-line me-1"></i>Trwa synchronizacja z Syntesys — może potrwać do 2 minut…');

        fetch(syncUrl, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken,
            },
            body: 'list=' + encodeURIComponent(list),
        })
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(data => {
            if (data.success) {
                showAlert('success',
                    '<i class="ri-check-line me-1"></i>' +
                    (data.message || 'Synchronizacja zakończona') +
                    (data.inserted > 0 || data.updated > 0
                        ? ' (' + data.inserted + ' nowych, ' + data.updated + ' zaktualizowanych)'
                        : '') +
                    '<br><small class="text-muted">Odświeżam stronę…</small>'
                );
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('danger', '<i class="ri-error-warning-line me-1"></i>' + (data.message || 'Błąd synchronizacji'));
                btn.disabled  = false;
                btn.innerHTML = origHtml;
            }
        })
        .catch(err => {
            showAlert('danger', '<i class="ri-error-warning-line me-1"></i>Błąd połączenia: ' + err.message);
            btn.disabled  = false;
            btn.innerHTML = origHtml;
        });
    }

    // Główny przycisk sync (wszystko)
    const btnAll = document.getElementById('btn-sync');
    if (btnAll) {
        btnAll.addEventListener('click', () => runSync(btnAll.dataset.list || 'all', btnAll));
    }

    // Przycisk sync w pustej tabeli (tylko aktywny tab)
    const btnTab = document.getElementById('btn-sync-tab');
    if (btnTab) {
        btnTab.addEventListener('click', () => runSync(btnTab.dataset.list || 'all', btnTab));
    }

    // ── Sprawdź opinię ──────────────────────────────────────────
    const btnCheckOpinion = document.getElementById('btn-check-opinion');
    if (btnCheckOpinion) {
        btnCheckOpinion.addEventListener('click', async () => {
            // Krok 1: zapytaj o NIP
            const { value: nip, isConfirmed } = await Swal.fire({
                title: 'Sprawdź opinię Allianz Trade',
                input: 'text',
                inputLabel: 'NIP kontrahenta',
                inputPlaceholder: 'np. 1234567890',
                inputAttributes: { maxlength: 15, autocomplete: 'off' },
                showCancelButton: true,
                confirmButtonText: 'Sprawdź',
                cancelButtonText: 'Anuluj',
                confirmButtonColor: '#198754',
                inputValidator: v => {
                    const digits = v.replace(/\D/g, '');
                    if (digits.length < 9 || digits.length > 15) return 'Podaj NIP (9–15 cyfr)';
                },
            });
            if (!isConfirmed || !nip) return;

            // Krok 2: loading
            Swal.fire({
                title: 'Trwa sprawdzanie…',
                html: '<p class="text-muted mb-0">Logowanie do Syntesys i wypełnianie formularza.<br>Może potrwać do 90 sekund.</p>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading(),
            });

            // Krok 3: wywołaj endpoint
            let data;
            try {
                const resp = await fetch(checkOpinionUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: 'nip=' + encodeURIComponent(nip.replace(/\D/g, '')),
                });
                data = await resp.json();
            } catch (err) {
                Swal.fire('Błąd połączenia', err.message, 'error');
                return;
            }

            if (!data.success) {
                Swal.fire('Błąd', data.message || 'Nieznany błąd', 'error');
                return;
            }

            // Krok 4: pokaż wynik
            const r = data.result || {};
            const adv = r.advice || {};
            const typeCode   = adv.typeCode   || r.typeCode   || '—';
            const reasonCode = adv.reasonCode || r.reasonCode || null;
            const validTo    = adv.validTo    || r.validTo    || null;
            const advCreated = adv.created    || r.created    || null;
            const company    = r.companyName  || r.identifier || '—';
            const status     = r.status       || '—';

            const badge   = CCAT_BADGE[typeCode]  || 'secondary';
            const label   = CCAT_LABEL[typeCode]  || typeCode;
            const desc    = CCAT_DESC[typeCode]   || '';
            const iconCls = badge === 'success' ? 'ri-checkbox-circle-fill' :
                            badge === 'danger'  ? 'ri-close-circle-fill' : 'ri-question-line';

            let reasonRow = '';
            if (reasonCode) {
                reasonRow = tdRow('Powód odmowy', `<span class="badge bg-warning text-dark me-1">${htmlEsc(reasonCode)}</span>${htmlEsc(CCCR_SHORT[reasonCode] || reasonCode)}`);
            }

            const resultHtml = `
                <div class="text-center mb-3">
                    <span class="badge bg-${badge} px-3 py-2 rounded-3" style="font-size:1.15rem">
                        <i class="${iconCls} me-1"></i>${htmlEsc(label)}
                    </span>
                    <div class="text-muted small mt-1"><code>${htmlEsc(typeCode)}</code></div>
                    ${desc ? `<div class="text-muted small mt-1 fst-italic" style="max-width:320px;margin:0 auto">${htmlEsc(desc)}</div>` : ''}
                </div>
                <table class="table table-sm table-borderless text-start mx-auto mb-0" style="max-width:380px">
                    ${tdRow('Firma',       `<strong>${htmlEsc(company)}</strong>`)}
                    ${tdRow('Status',      htmlEsc(STATUS_PL[status] || status))}
                    ${reasonRow}
                    ${validTo    ? tdRow('Ważna do',     `<strong class="text-${badge === 'danger' ? 'danger' : 'success'}">${htmlEsc(fmtDate(validTo) || validTo)}</strong>`) : ''}
                    ${advCreated ? tdRow('Data wydania', htmlEsc(fmtDateTime(advCreated) || '')) : ''}
                </table>`;

            const swalIcon = badge === 'success' ? 'success' : badge === 'danger' ? 'error' : 'info';

            const { isConfirmed: doRefresh } = await Swal.fire({
                title: 'Wynik zapytania kredytowego',
                html: resultHtml,
                icon: swalIcon,
                confirmButtonText: '<i class="ri-refresh-line me-1"></i>OK i odśwież stronę',
                cancelButtonText: 'Zamknij',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                width: '500px',
            });

            // Krok 5: opcjonalne odświeżenie po zamknięciu (dane już zapisane przez PHP)
            if (doRefresh) {
                location.reload();
            }
        });
    }

    // Modal szczegółów opinii
    document.querySelectorAll('.btn-advice-details').forEach(btn => {
        btn.addEventListener('click', () => {
            const bodyEl = document.getElementById('advice-modal-body');
            try {
                const json    = JSON.parse(btn.dataset.json || '{}');
                const company = btn.dataset.company || '';
                const nip     = btn.dataset.nip     || '';
                bodyEl.innerHTML = buildAdviceHtml(json, company, nip);
            } catch (_) {
                bodyEl.innerHTML = '<pre class="bg-light rounded p-3 small" style="white-space:pre-wrap;word-break:break-all">' + htmlEsc(btn.dataset.json || '') + '</pre>';
            }
            new bootstrap.Modal(document.getElementById('adviceModal')).show();
        });
    });

    // Tooltips Bootstrap
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el, { placement: 'top', trigger: 'hover' });
    });
})();
</script>

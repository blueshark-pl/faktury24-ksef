<?php
// paging
$paging = $this->getRequest()->getAttribute('paging')['Contractors'] ?? [];
$total  = $paging['count'] ?? null;

/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface|\Cake\Collection\CollectionInterface $contractors
 */
$this->assign('title', 'Kontrahenci');

// query params
$q       = trim((string)$this->request->getQuery('q'));
$active  = $this->request->getQuery('active'); // '1' / '0' / null
$limit   = (int)($this->request->getQuery('limit') ?? 25);
$limit   = $limit > 0 ? $limit : 25;

// zakres X–Y do stopki
$page    = (int)($paging['page']    ?? 1);
$perPage = (int)($paging['perPage'] ?? 0);
$current = (int)($paging['current'] ?? 0);
$start   = $current ? (($page - 1) * $perPage) + 1 : 0;
$end     = $current ? ($start + $current - 1)       : 0;

// ładne szablony paginatora
$this->Paginator->setTemplates([
  'current'      => '<li class="page-item active" aria-current="page"><a class="page-link" href="#">{{text}}</a></li>',
  'number'       => '<li class="page-item"><a class="page-link" href="{{url}}">{{text}}</a></li>',
  'ellipsis'     => '<li class="page-item disabled"><span class="page-link">…</span></li>',
  'prevActive'   => '<li class="page-item"><a rel="prev" class="page-link" href="{{url}}" aria-label="Poprzednia"><i class="ri-arrow-left-s-line"></i></a></li>',
  'prevDisabled' => '<li class="page-item disabled"><span class="page-link" aria-label="Poprzednia"><i class="ri-arrow-left-s-line"></i></span></li>',
  'nextActive'   => '<li class="page-item"><a rel="next" class="page-link" href="{{url}}" aria-label="Następna"><i class="ri-arrow-right-s-line"></i></a></li>',
  'nextDisabled' => '<li class="page-item disabled"><span class="page-link" aria-label="Następna"><i class="ri-arrow-right-s-line"></i></span></li>',
  'first'        => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Pierwsza"><i class="ri-skip-left-line"></i></a></li>',
  'last'         => '<li class="page-item"><a class="page-link" href="{{url}}" aria-label="Ostatnia"><i class="ri-skip-right-line"></i></a></li>',
]);

$identity  = $this->getRequest()->getAttribute('identity');
$companyId = $identity?->get('company_id');
?>

<!-- Start::page-header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Kontrahenci</h1>
    <nav>
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
        <li class="breadcrumb-item active" aria-current="page">Kontrahenci</li>
      </ol>
    </nav>
  </div>
  <div class="btn-list">
      <?= $this->Html->link(
        '<i class="ri-upload-cloud-line align-middle me-1"></i> Eksport CSV',
        ['action' => 'export', '?' => $this->request->getQueryParams()],
        [
          'id' => 'export-csv-btn',
          'class' => 'btn btn-secondary-light btn-wave me-0',
          'escape' => false,
          'data-bs-toggle' => 'tooltip',
          'title' => 'Eksportuj z uwzględnieniem filtrów'
        ]
      ) ?>

    <button class="btn btn-primary btn-wave" data-bs-toggle="modal" data-bs-target="#contractor-create">
      <i class="ri-add-line me-1"></i> Dodaj kontrahenta
    </button>
  </div>
</div>
<!-- End::page-header -->

<div class="row">
  <div class="col-xl-12">
    <div class="card custom-card">
      <div class="card-header justify-content-between flex-wrap gap-2">
        <div class="card-title d-flex align-items-center gap-2">
          Lista kontrahentów
          <?php if ($total !== null): ?>
            <span class="badge bg-light text-default rounded ms-1 fs-12 align-middle"><?= (int)$total ?></span>
          <?php endif; ?>
          <?php if ($q): ?>
            <span class="badge bg-primary-transparent">
              <i class="ri-search-line me-1"></i><?= h($q) ?>
            </span>
          <?php endif; ?>
          <?php if ($active !== null && $active !== ''): ?>
            <span class="badge bg-<?= $active === '1' ? 'success' : 'danger' ?>-transparent">
              <?= $active === '1' ? 'Aktywni' : 'Nieaktywni' ?>
            </span>
          <?php endif; ?>
        </div>

        <?= $this->Form->create(null, ['type' => 'get', 'class' => 'd-flex flex-wrap gap-2 ms-2', 'role' => 'search', 'aria-label' => 'Filtry kontrahentów', 'id' => 'contractor-filters-form']) ?>
          <div class="position-relative">
            <i class="ri-search-line position-absolute" style="left:10px;top:50%;transform:translateY(-50%);color:#9aa0ac;pointer-events:none"></i>
            <input id="live-search" name="q" class="form-control form-control-sm ps-4" type="search"
                   placeholder="Szukaj: nazwa / NIP / email / miasto"
                   value="<?= h($q) ?>"
                   style="min-width:260px"
                   data-current-url="<?= h($this->Url->build(['action' => 'index'])) ?>"
                   aria-label="Szukaj">
          </div>
          <?= $this->Form->control('active', [
            'type' => 'select',
            'label' => false,
            'empty' => 'Status',
            'options' => ['1' => 'Aktywni', '0' => 'Nieaktywni'],
            'value' => $active,
            'class' => 'form-select form-select-sm',
            'onchange' => 'this.form.requestSubmit()',
            'aria-label' => 'Status'
          ]) ?>
          <?= $this->Form->control('limit', [
            'type' => 'select',
            'label' => false,
            'value' => $limit,
            'options' => [10 => '10 / stronę', 25 => '25 / stronę', 50 => '50 / stronę', 100 => '100 / stronę'],
            'class' => 'form-select form-select-sm',
            'onchange' => 'this.form.requestSubmit()',
            'aria-label' => 'Wiersze na stronę'
          ]) ?>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-primary btn-wave" type="submit" title="Zastosuj filtry">
              <i class="ri-search-line me-1"></i>Filtruj
            </button>
            <?= $this->Html->link('<i class="ri-refresh-line"></i>', ['action' => 'index'], [
              'class' => 'btn btn-light', 'escape' => false, 'title' => 'Wyczyść filtry'
            ]) ?>
          </div>
        <?= $this->Form->end() ?>
      </div>

      <div class="card-body p-0">
        <!-- Bulk-actions toolbar — widoczny tylko gdy zaznaczono >=1 kontrahenta -->
        <div id="bulk-actions-toolbar" class="d-none align-items-center justify-content-between gap-2 px-3 py-2 border-bottom" style="background: rgba(148, 212, 55, .08);">
          <div class="small fw-semibold">
            <i class="ri-checkbox-multiple-line me-1 text-primary"></i>
            <span id="bulk-actions-count">0</span> zaznaczonych
            <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-muted" id="bulk-actions-clear">Odznacz</button>
          </div>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-success-light" id="bulk-activate" title="Ustaw status: Aktywny">
              <i class="ri-check-line me-1"></i>Aktywuj
            </button>
            <button type="button" class="btn btn-secondary-light" id="bulk-deactivate" title="Ustaw status: Nieaktywny">
              <i class="ri-close-line me-1"></i>Dezaktywuj
            </button>
            <button type="button" class="btn btn-primary-light" id="bulk-export" title="Eksport CSV zaznaczonych">
              <i class="ri-download-line me-1"></i>Eksport CSV
            </button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="contractors-table">
            <thead class="bg-body-tertiary position-sticky top-0" style="z-index:1;">
              <tr>
                <th style="width:32px;">
                  <input class="form-check-input" type="checkbox" id="check-all">
                </th>
                <th><?= $this->Paginator->sort('name', 'Kontrahent') ?></th>
                <th>Identyfikator</th>
                <th>Kontakt</th>
                <th class="text-end">Saldo</th>
                <th><?= $this->Paginator->sort('is_active', 'Status') ?></th>
                <th class="text-end">Akcje</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($contractors as $c): ?>
              <tr>
                <td>
                  <input class="form-check-input row-check" type="checkbox" value="<?= h($c->id) ?>">
                </td>
                <?php
                  $isPerson = isset($c->is_person) ? ((int)$c->is_person === 1) : (!($c->name) && ($c->first_name || $c->last_name));
                  $primary  = $isPerson ? trim(($c->first_name ?? '').' '.($c->last_name ?? '')) : ($c->name ?: $c->altname ?: '—');
                  $vatPfx   = (string)($c->vat_prefix ?? '');
                  $hasEUVat = $vatPfx !== '' && $vatPfx !== 'NONE' && !empty($c->vat_eu);
                  $hasNrID  = $vatPfx === 'NONE' || (!empty($c->tax_id_other_country) && !empty($c->tax_id_other));
                  // Kraj do flagi przy nazwie: country adresu, lub fallback z vat_prefix/tax_id_other_country
                  $flagCC   = strtolower(trim((string)($c->country ?? '')));
                  if ($flagCC === '' && $vatPfx !== '' && $vatPfx !== 'NONE') $flagCC = strtolower($vatPfx);
                  if ($flagCC === '' && !empty($c->tax_id_other_country))     $flagCC = strtolower((string)$c->tax_id_other_country);
                ?>
                <td>
                  <div class="d-flex flex-column">
                    <strong class="d-inline-flex align-items-center gap-2">
                      <?= h($primary) ?>
                      <?php if ($isPerson): ?>
                        <span class="badge bg-info-transparent">Osoba</span>
                      <?php else: ?>
                        <span class="badge bg-primary-transparent">Firma</span>
                      <?php endif; ?>
                    </strong>
                    <?php
                      $addrLine1 = trim((string)($c->street ?? ''));
                      $addrLine2 = trim(trim((string)($c->postal_code ?? '')) . ' ' . trim((string)($c->city ?? '')));
                      $countryUpper = $c->country ? strtoupper((string)$c->country) : '';
                    ?>
                    <?php if ($addrLine1 !== ''): ?>
                      <small class="text-muted"><?= h($addrLine1) ?></small>
                    <?php endif; ?>
                    <small class="text-muted d-inline-flex align-items-center gap-1">
                      <?php if ($flagCC !== ''): ?>
                        <span class="fi fi-<?= h($flagCC) ?>" style="line-height:1; border-radius:2px"></span>
                      <?php else: ?>
                        <i class="ri-map-pin-2-line"></i>
                      <?php endif; ?>
                      <?= h(trim($addrLine2 . ($countryUpper ? ' · ' . $countryUpper : ''), ' ·')) ?: '—' ?>
                    </small>
                  </div>
                </td>
                <td>
                  <?php if (!$isPerson && $hasEUVat): ?>
                    <div class="d-flex flex-column">
                      <small class="text-muted d-inline-flex align-items-center gap-1">
                        <span class="fi fi-<?= h(strtolower($vatPfx)) ?>" style="border-radius:2px"></span>
                        VAT-UE
                      </small>
                      <span class="d-inline-flex align-items-center gap-1">
                        <code class="bg-body-secondary px-1 py-0 rounded"><?= h($vatPfx . $c->vat_eu) ?></code>
                        <button class="btn btn-link btn-sm p-0" data-copy="<?= h($vatPfx . $c->vat_eu) ?>" title="Kopiuj VAT-UE"><i class="ri-file-copy-line"></i></button>
                      </span>
                      <?php if (!empty($c->eori)): ?>
                        <small class="text-muted">EORI: <?= h($c->eori) ?></small>
                      <?php endif; ?>
                    </div>
                  <?php elseif (!$isPerson && $hasNrID && !empty($c->tax_id_other)): ?>
                    <div class="d-flex flex-column">
                      <small class="text-muted d-inline-flex align-items-center gap-1">
                        <?php if (!empty($c->tax_id_other_country)): ?>
                          <span class="fi fi-<?= h(strtolower((string)$c->tax_id_other_country)) ?>" style="border-radius:2px"></span>
                        <?php endif; ?>
                        NrID<?= !empty($c->tax_id_other_country) ? ' ('.h(strtoupper((string)$c->tax_id_other_country)).')' : '' ?>
                      </small>
                      <span class="d-inline-flex align-items-center gap-1">
                        <code class="bg-body-secondary px-1 py-0 rounded"><?= h($c->tax_id_other) ?></code>
                        <button class="btn btn-link btn-sm p-0" data-copy="<?= h($c->tax_id_other) ?>" title="Kopiuj identyfikator"><i class="ri-file-copy-line"></i></button>
                      </span>
                    </div>
                  <?php elseif (!empty($c->nip)): ?>
                    <div class="d-flex flex-column">
                      <small class="text-muted d-inline-flex align-items-center gap-1">
                        <span class="fi fi-pl" style="border-radius:2px"></span>
                        NIP (PL)
                      </small>
                      <span class="d-inline-flex align-items-center gap-1">
                        <code class="bg-body-secondary px-1 py-0 rounded"><?= h($c->nip) ?></code>
                        <button class="btn btn-link btn-sm p-0" data-copy="<?= h($c->nip) ?>" title="Kopiuj NIP"><i class="ri-file-copy-line"></i></button>
                      </span>
                    </div>
                  <?php elseif (!empty($c->pesel)): ?>
                    <div class="d-flex flex-column">
                      <small class="text-muted">PESEL</small>
                      <span class="d-inline-flex align-items-center gap-1">
                        <code class="bg-body-secondary px-1 py-0 rounded"><?= h($c->pesel) ?></code>
                        <button class="btn btn-link btn-sm p-0" data-copy="<?= h($c->pesel) ?>" title="Kopiuj PESEL"><i class="ri-file-copy-line"></i></button>
                      </span>
                    </div>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                  <div class="d-flex flex-column gap-1">
                    <?php if ($c->email): ?>
                      <span class="d-inline-flex align-items-center gap-1">
                        <i class="ri-mail-line text-muted"></i>
                        <?= $this->Html->link(h($c->email), 'mailto:' . $c->email, ['escape' => false]) ?>
                        <button class="btn btn-link btn-sm p-0 copy-btn" data-copy="<?= h($c->email) ?>" title="Kopiuj e-mail"><i class="ri-file-copy-line"></i></button>
                      </span>
                    <?php endif; ?>
                    <?php if ($c->phone): ?>
                      <span class="d-inline-flex align-items-center gap-1">
                        <i class="ri-phone-line text-muted"></i>
                        <?= h($c->phone) ?>
                        <button class="btn btn-link btn-sm p-0 copy-btn" data-copy="<?= h($c->phone) ?>" title="Kopiuj telefon"><i class="ri-file-copy-line"></i></button>
                      </span>
                    <?php endif; ?>
                    <?php if (!$c->email && !$c->phone): ?>—<?php endif; ?>
                  </div>
                </td>
                <td class="text-end">
                  <?php
                    $bal = $balances[$c->id] ?? null;
                    if ($bal && (float)$bal['amount'] > 0):
                  ?>
                    <button type="button"
                            class="btn btn-link p-0 text-decoration-none js-balance-click"
                            data-id="<?= h($c->id) ?>"
                            data-name="<?= h($c->name ?: (trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: ('#'.$c->id))) ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="left"
                            title="Pokaż nieopłacone faktury">
                      <span class="fw-semibold text-danger">
                        <?= number_format((float)$bal['amount'], 2, ',', ' ') ?>
                        <small class="text-muted fw-normal"><?= h($bal['currency']) ?></small>
                      </span>
                      <?php if (!empty($bal['multi'])): ?>
                        <i class="ri-error-warning-line text-warning" title="Faktury w wielu walutach — suma w tabeli tylko dla jednej waluty"></i>
                      <?php endif; ?>
                    </button>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php $isActive = (int)$c->is_active === 1; ?>
                  <span class="d-inline-flex align-items-center gap-2" title="<?= $isActive ? 'Aktywny' : 'Nieaktywny' ?>">
                    <span class="status-dot <?= $isActive ? 'status-dot-active' : 'status-dot-inactive' ?>"></span>
                    <small class="text-muted"><?= $isActive ? 'Aktywny' : 'Nieaktywny' ?></small>
                  </span>
                </td>
                <td class="text-end">
                  <?php $displayName = h($c->name ?: (trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: $c->altname ?: ('#'.$c->id))); ?>
                  <div class="d-inline-flex align-items-center gap-1 row-actions">
                    <button class="btn btn-sm btn-success-light btn-icon js-edit"
                            data-id="<?= $c->id ?>"
                            data-name="<?= h($c->name) ?>"
                            title="Edytuj">
                      <i class="ri-pencil-line"></i>
                    </button>
                    <div class="dropdown">
                      <button class="btn btn-sm btn-light btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Więcej akcji">
                        <i class="ri-more-2-fill"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li>
                          <button type="button" class="dropdown-item d-flex align-items-center gap-2 js-details"
                                  data-id="<?= $c->id ?>" data-name="<?= $displayName ?>">
                            <i class="ri-information-line text-info"></i>Szczegóły
                          </button>
                        </li>
                        <li>
                          <button type="button" class="dropdown-item d-flex align-items-center gap-2 js-recipients"
                                  data-id="<?= $c->id ?>" data-name="<?= $displayName ?>">
                            <i class="ri-user-3-line text-secondary"></i>Odbiorcy
                          </button>
                        </li>
                        <li>
                          <button type="button" class="dropdown-item d-flex align-items-center gap-2 js-invoices"
                                  data-id="<?= $c->id ?>" data-name="<?= $displayName ?>"
                                  data-url="<?= $this->Url->build(['/contractors','invoices', $c->id]) ?>">
                            <i class="ri-file-list-line text-primary"></i>Faktury
                          </button>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                          <button type="button" class="dropdown-item d-flex align-items-center gap-2 text-danger js-delete-contractor"
                                  data-url="<?= $this->Url->build(['action' => 'delete', $c->id]) ?>"
                                  data-id="<?= $c->id ?>" data-name="<?= $displayName ?>">
                            <i class="ri-delete-bin-line"></i>Usuń
                          </button>
                        </li>
                      </ul>
                    </div>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>

              <?php if (count($contractors) === 0): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-5">
                  <div class="mb-2" style="font-size:32px;opacity:.3">📭</div>
                  Brak wyników. Zmień filtry lub dodaj pierwszego kontrahenta.
                  <div class="mt-3">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#contractor-create">
                      <i class="ri-add-line me-1"></i> Dodaj kontrahenta
                    </button>
                  </div>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card-footer border-top-0">
        <div class="d-flex align-items-center w-100 gap-3 flex-wrap">
          <div class="small">
            <?= $this->Paginator->counter('Strona {{page}} z {{pages}}, wyświetlono {{current}} / {{count}}') ?>
          </div>
          <div class="ms-auto">
            <nav aria-label="Paginacja" class="pagination-style-5">
              <ul class="pagination mb-0">
                <li class="page-item"><?= $this->Paginator->prev('Prev', ['tag' => 'a'], null, ['class' => 'page-link disabled', 'disabledTag' => 'a']) ?></li>
                <?= $this->Paginator->numbers(['modulus' => 3, 'templates' => [
                      'number' => '<li class="page-item"><a class="page-link" href="{{url}}">{{text}}</a></li>',
                      'current' => '<li class="page-item active"><a class="page-link" href="#">{{text}}</a></li>',
                ]]) ?>
                <li class="page-item"><?= $this->Paginator->next('Next', ['tag' => 'a'], null, ['class' => 'page-link disabled', 'disabledTag' => 'a']) ?></li>
              </ul>
            </nav>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Modal: Faktury kontrahenta -->
<div class="modal fade" id="contractor-invoices" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-0 pb-2">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
          <div class="cd-avatar">
            <i class="ri-file-list-3-line"></i>
          </div>
          <div class="flex-grow-1 min-width-0">
            <h6 class="modal-title mb-0 text-truncate">Faktury — <span id="ci-name"></span></h6>
            <small class="text-muted" id="ci-count">—</small>
          </div>
          <div class="form-check m-0">
            <input class="form-check-input" type="checkbox" id="ci-unsettled">
            <label class="form-check-label small" for="ci-unsettled">Tylko nierozliczone</label>
          </div>
        </div>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body pt-0 position-relative">
        <div id="ci-loader" class="cd-loader d-none" aria-live="polite">
          <div class="text-center">
            <div class="spinner-border text-primary" role="status" style="width:2.25rem;height:2.25rem;"></div>
            <div class="small text-muted mt-2">Ładowanie faktur…</div>
          </div>
        </div>
        <div class="table-responsive" style="max-height:60vh">
          <table class="table table-sm align-middle cd-inv-table mb-0">
            <thead>
              <tr>
                <th>Numer</th>
                <th>Data</th>
                <th class="text-end">Brutto</th>
                <th class="text-end">Pozostało</th>
                <th>Status</th>
                <th class="text-end">Akcje</th>
              </tr>
            </thead>
            <tbody id="ci-tbody">
              <tr><td colspan="6" class="text-center text-muted py-4">Brak danych</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>
<!-- Modal: Ustawienia kontrahenta -->
<div class="modal fade" id="contractor-settings" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="ri-settings-3-line me-2"></i>Ustawienia – <span id="cs-name"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <form id="cs-form" class="vstack gap-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="cs-share">
            <label class="form-check-label" for="cs-share">Udostępnianie faktur (link)</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="cs-sms">
            <label class="form-check-label" for="cs-sms">Powiadomienia SMS</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="cs-email">
            <label class="form-check-label" for="cs-email">Powiadomienia e-mail</label>
          </div>
          <div>
            <label class="form-label">Dołączaj dokument faktury</label>
            <select id="cs-attach" class="form-select">
              <option value="inherit">Z ustawień głównych</option>
              <option value="yes">Tak</option>
              <option value="no">Nie</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
        <button class="btn btn-primary" id="cs-save"><i class="ri-save-line me-1"></i>Zapisz</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Szczegóły kontrahenta -->
<div class="modal fade" id="contractor-details" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
          <div id="cd-avatar" class="cd-avatar">
            <i class="ri-building-2-line"></i>
          </div>
          <div class="flex-grow-1 min-width-0">
            <h6 class="modal-title mb-1 text-truncate">
              <span id="cd-name"></span>
              <span id="cd-type-badge" class="badge bg-primary-transparent ms-1"></span>
            </h6>
            <div class="small text-muted d-inline-flex align-items-center gap-2">
              <span id="cd-status-pill" class="d-inline-flex align-items-center gap-1">
                <span class="status-dot status-dot-active"></span>
                <span id="cd-status-text">Aktywny</span>
              </span>
            </div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>

      <div class="modal-body pt-2 position-relative">
        <!-- Loader podczas pobierania danych -->
        <div id="cd-loader" class="cd-loader d-none" aria-live="polite">
          <div class="text-center">
            <div class="spinner-border text-primary" role="status" style="width:2.25rem;height:2.25rem;"></div>
            <div class="small text-muted mt-2">Ładowanie szczegółów…</div>
          </div>
        </div>
        <!-- Sekcje informacyjne -->
        <div class="row g-3 mb-3">
          <!-- Identyfikator -->
          <div class="col-md-6">
            <div class="cd-card h-100">
              <div class="cd-card-header"><i class="ri-shield-user-line"></i>Identyfikator</div>
              <div class="cd-card-body" id="cd-ident"></div>
            </div>
          </div>
          <!-- Kontakt -->
          <div class="col-md-6">
            <div class="cd-card h-100">
              <div class="cd-card-header"><i class="ri-contacts-line"></i>Kontakt</div>
              <div class="cd-card-body">
                <div class="cd-row" id="cd-email-row">
                  <i class="ri-mail-line text-muted"></i>
                  <span id="cd-email" class="flex-grow-1">—</span>
                </div>
                <div class="cd-row" id="cd-phone-row">
                  <i class="ri-phone-line text-muted"></i>
                  <span id="cd-phone" class="flex-grow-1">—</span>
                </div>
              </div>
            </div>
          </div>
          <!-- Adres -->
          <div class="col-12">
            <div class="cd-card">
              <div class="cd-card-header"><i class="ri-map-pin-2-line"></i>Adres</div>
              <div class="cd-card-body" id="cd-address-body">
                <span id="cd-address" class="d-block">—</span>
                <span class="d-inline-flex align-items-center gap-1 mt-1 text-muted small">
                  <span id="cd-country-flag"></span>
                  <span id="cd-country">—</span>
                </span>
              </div>
            </div>
          </div>
          <!-- Notatki -->
          <div class="col-12 d-none" id="cd-notes-wrap">
            <div class="cd-card">
              <div class="cd-card-header"><i class="ri-sticky-note-line"></i>Notatki</div>
              <div class="cd-card-body" id="cd-notes"></div>
            </div>
          </div>
        </div>

        <!-- Tabs: Odbiorcy / Faktury / Statystyki -->
        <ul class="nav nav-tabs nav-tabs-soft mb-3" role="tablist">
          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cd-tab-recipients" type="button"><i class="ri-user-3-line me-1"></i>Odbiorcy</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cd-tab-invoices" type="button"><i class="ri-file-list-line me-1"></i>Faktury</button></li>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cd-tab-stats" type="button" id="cd-tab-stats-btn"><i class="ri-bar-chart-2-line me-1"></i>Statystyki</button></li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="cd-tab-recipients" role="tabpanel">
            <div class="d-flex justify-content-end mb-2">
              <button class="btn btn-sm btn-outline-primary" id="cd-load-recipients"><i class="ri-refresh-line me-1"></i>Wczytaj odbiorców</button>
            </div>
            <div id="cd-recipients-loader" class="text-muted small d-none">Ładowanie…</div>
            <div id="cd-recipients-list" class="vstack gap-2"></div>
          </div>
          <div class="tab-pane fade" id="cd-tab-invoices" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" id="cd-inv-unsettled">
                <label class="form-check-label small" for="cd-inv-unsettled">Tylko nierozliczone</label>
              </div>
              <button class="btn btn-sm btn-outline-primary" id="cd-load-invoices"><i class="ri-refresh-line me-1"></i>Wczytaj faktury</button>
            </div>
            <div id="cd-invoices-loader" class="text-muted small d-none">Ładowanie…</div>
            <div class="table-responsive" style="max-height:380px">
              <table class="table table-sm align-middle cd-inv-table mb-0">
                <thead>
                  <tr>
                    <th>Numer</th>
                    <th>Data</th>
                    <th class="text-end">Brutto</th>
                    <th class="text-end">Pozostało</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="cd-invoices-tbody"><tr><td colspan="5" class="text-center text-muted py-3">Kliknij „Wczytaj faktury".</td></tr></tbody>
              </table>
            </div>
          </div>
          <div class="tab-pane fade" id="cd-tab-stats" role="tabpanel">
            <div id="cd-stats-loader" class="text-center text-muted py-4 d-none">
              <span class="spinner-border spinner-border-sm me-2"></span>Liczę statystyki…
            </div>
            <div id="cd-stats-body" class="d-none"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
 </div>

<!-- Modal: Add/Edit kontrahent (z GUS lookup) -->
<div class="modal fade" id="contractor-create" tabindex="-1" aria-hidden="true" data-mode="add">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="contractor-modal-title">Dodaj kontrahenta</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>

      <?= $this->Form->create(null, [
            'url' => ['controller' => 'Contractors', 'action' => 'add'],
            'class' => 'needs-validation', 'novalidate' => true, 'id' => 'contractor-form'
      ]) ?>
      <?= $this->Form->hidden('id', ['id' => 'contractor-id']) ?>
      <?= $this->Form->hidden('_method', ['value' => 'POST', 'id' => 'contractor-method']) ?>

      <div class="modal-body position-relative">
        <!-- Loader podczas wczytywania danych do edycji -->
        <div id="cc-loader" class="cd-loader d-none" aria-live="polite">
          <div class="text-center">
            <div class="spinner-border text-primary" role="status" style="width:2.25rem;height:2.25rem;"></div>
            <div class="small text-muted mt-2">Wczytuję dane kontrahenta…</div>
          </div>
        </div>
        <?= $this->Form->hidden('company_id', ['value' => $companyId]) ?>

        <div class="vstack gap-3">
          <!-- Przełącznik typu kontrahenta (firma / osoba) — chip-picker -->
          <div class="border rounded p-3 mb-3" id="type-switch-section">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small">Typ kontrahenta</strong>
            </div>
            <!-- ukryty checkbox = źródło prawdy dla istniejącego JS -->
            <input type="checkbox" id="use-pesel" class="d-none">
            <div class="btn-group btn-group-sm flex-wrap mb-2" role="group" aria-label="Typ kontrahenta" id="type-chips">
              <button type="button" class="btn btn-outline-primary active" data-type="company">
                <i class="ri-building-line me-1"></i> Firma
              </button>
              <button type="button" class="btn btn-outline-primary" data-type="person">
                <i class="ri-user-line me-1"></i> Osoba fizyczna
              </button>
            </div>
            <small class="text-muted d-block" id="type-chips-hint">Dla firmy podaj nazwę i NIP / VAT-UE / NrID.</small>
            <!-- Explicit flag submitted to backend; JS synchronizuje z #use-pesel -->
            <input type="hidden" name="is_person" id="is-person-hidden" value="0">
          </div>

          <!-- Sekcja: Dane podstawowe -->
          <div class="border rounded p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small">Dane podstawowe</strong>
              <!-- Aktywność przeniesiona tutaj po prawej -->
              <div class="form-check form-switch mb-0 ms-auto">
                <?= $this->Form->control('is_active', [
                  'type' => 'checkbox', 'label' => 'Aktywny', 'checked' => true,
                  'class' => 'form-check-input',
                  'templates' => ['inputContainer' => '<div class="form-check">{{content}}</div>']
                ]) ?>
              </div>
            </div>
            <div class="row g-3">
              <!-- Pola: firma -->
              <div id="company-fields" class="row g-3">
                <div class="col-12">
                  <div class="form-group">
                    <?= $this->Form->control('name', [
                      'label' => 'Nazwa*', 'class' => 'form-control',
                      'placeholder' => 'np. ACME Sp. z o.o.',
                      'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                    ]) ?>
                    <div class="help-slot"></div>
                  </div>
                </div>
              </div>
              <!-- Pola: osoba fizyczna -->
              <div id="person-fields" class="row g-3 d-none">
                <div class="col-md-6">
                  <div class="form-group">
                    <?= $this->Form->control('first_name', [
                      'label' => 'Imię*', 'class' => 'form-control',
                      'placeholder' => 'Jan',
                      'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                    ]) ?>
                    <div class="help-slot"></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <?= $this->Form->control('last_name', [
                      'label' => 'Nazwisko*', 'class' => 'form-control',
                      'placeholder' => 'Kowalski',
                      'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                    ]) ?>
                    <div class="help-slot"></div>
                  </div>
                </div>
                <div class="col-12 d-none" id="nip-toggle-row">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="show-nip">
                    <label class="form-check-label" for="show-nip">Podaj NIP (opcjonalnie)</label>
                  </div>
                </div>
                <div class="col-md-6 d-none" id="person-nip-group">
                  <div class="form-group">
                    <label for="person-nip" class="form-label">NIP</label>
                    <input type="text" id="person-nip" class="form-control" maxlength="10" placeholder="6571234567">
                    <div class="help-slot"></div>
                  </div>
                </div>
              </div>

              <!-- NIP-group przeniesiony do sekcji "Identyfikacja kontrahenta" niżej -->

              <div class="col-12 d-none" id="pesel-toggle-row">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="show-pesel">
                  <label class="form-check-label" for="show-pesel">Podaj PESEL (opcjonalnie)</label>
                </div>
              </div>
              <div class="col-md-6 d-none" id="pesel-group">
                <div class="form-group">
                  <?= $this->Form->control('pesel', [
                    'label' => 'PESEL', 'id' => 'pesel',
                    'class' => 'form-control', 'maxlength' => 11,
                    'placeholder' => '12345678901',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot">
                    <small class="text-muted">PESEL będzie wykorzystywany wyłącznie w celu realizacji obowiązków podatkowych i księgowych.</small>
                  </div>
                </div>
              </div>

              <!-- status aktywny przeniesiony do nagłówka sekcji -->
            </div>
          </div>

          <!-- Sekcja: Dane kontaktowe -->
          <div class="border rounded p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small">Dane kontaktowe</strong>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="form-group">
                  <?= $this->Form->control('email', [
                    'label' => 'Email', 'class' => 'form-control', 'type' => 'email',
                    'placeholder' => 'biuro@firma.pl',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot"></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <?= $this->Form->control('phone', [
                    'label' => ['text' => 'Telefon', 'class' => 'd-block'],
                    'class' => 'form-control', 'id' => 'phone',
                    // 'placeholder' => '+48 600 000 000',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sekcja: Identyfikacja kontrahenta (chip picker) -->
          <div class="border rounded p-3" id="identification-section">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small">Identyfikacja kontrahenta</strong>
            </div>

            <!-- Chip-picker: 3 warianty identyfikatora -->
            <div class="btn-group btn-group-sm flex-wrap mb-3" role="group" aria-label="Typ identyfikatora" id="id-type-chips">
              <input type="hidden" name="id_type" id="id-type-hidden" value="nip_pl">
              <button type="button" class="btn btn-outline-primary active" data-id-type="nip_pl">
                <i class="ri-flag-line me-1"></i> NIP (PL)
              </button>
              <button type="button" class="btn btn-outline-primary" data-id-type="vat_eu">
                <i class="ri-global-line me-1"></i> VAT UE
              </button>
              <button type="button" class="btn btn-outline-primary" data-id-type="non_eu">
                <i class="ri-earth-line me-1"></i> Spoza UE
              </button>
            </div>

            <!-- Panel: NIP PL -->
            <div class="id-panel" data-id-panel="nip_pl">
              <div class="row g-2">
                <div class="col-md-8" id="nip-group">
                  <label for="nip" class="form-label small mb-1">NIP polski</label>
                  <div class="input-group">
                    <?= $this->Form->control('nip', [
                      'label' => false, 'id' => 'nip',
                      'class' => 'form-control', 'maxlength' => 10,
                      'placeholder' => '6571234567',
                      'templates' => ['inputContainer' => '{{content}}']
                    ]) ?>
                    <button class="btn btn-outline-secondary" type="button" id="gus-fetch">
                      <span class="spinner-border spinner-border-sm me-1 d-none" id="gus-spin"></span>
                      <i class="ri-database-2-line me-1"></i> Pobierz z GUS
                    </button>
                  </div>
                  <div class="help-slot">
                    <small class="text-muted">Automatycznie uzupełni adres i nazwę z rejestru GUS.</small>
                  </div>
                </div>
              </div>
            </div>

            <!-- Panel: VAT UE -->
            <div class="id-panel d-none" data-id-panel="vat_eu">
              <div class="alert alert-info py-2 px-3 mb-2 small d-flex align-items-start gap-2" role="alert">
                <i class="ri-information-line mt-1"></i>
                <span>Dotyczy także <strong>polskich firm</strong> rozliczających transakcje wewnątrzwspólnotowe — wybierz <strong>PL</strong> jako prefiks.</span>
              </div>
              <div class="row g-2">
                <div class="col-md-3">
                  <label class="form-label small mb-1">Prefiks UE</label>
                  <input type="hidden" name="vat_prefix" id="vat-prefix-hidden" value="">
                  <div id="vat-prefix-wrapper">
                    <input type="text" id="vat-prefix-ui" class="form-control form-control-sm" placeholder="Wybierz kraj UE">
                  </div>
                </div>
                <div class="col-md-5">
                  <label class="form-label small mb-1">Numer VAT-UE</label>
                  <div class="input-group input-group-sm">
                    <input type="text" name="vat_eu" id="modal-vat-eu"
                           class="form-control form-control-sm" maxlength="32"
                           placeholder="np. 123456789">
                    <button type="button" class="btn btn-outline-secondary" id="vies-check-btn" title="Sprawdź w VIES (rejestr Komisji Europejskiej)">
                      <span class="spinner-border spinner-border-sm d-none" id="vies-spin" role="status"></span>
                      <i class="ri-shield-check-line" id="vies-icon"></i> VIES
                    </button>
                  </div>
                  <div class="form-text small mt-1 d-none" id="vies-result"></div>
                </div>
                <div class="col-md-4">
                  <label class="form-label small mb-1">EORI (opcjonalnie)</label>
                  <input type="text" name="eori" id="modal-eori"
                         class="form-control form-control-sm" maxlength="32"
                         placeholder="np. PL1234567890">
                </div>
              </div>
            </div>

            <!-- Panel: Spoza UE -->
            <div class="id-panel d-none" data-id-panel="non_eu">
              <div class="row g-2">
                <div class="col-md-4">
                  <label class="form-label small mb-1 d-block">Kraj (NrID)</label>
                  <input type="hidden" name="tax_id_other_country" id="modal-tax-id-other-country" value="">
                  <input type="text" id="modal-tax-id-other-country-ui"
                         class="form-control form-control-sm" placeholder="Wybierz kraj">
                </div>
                <div class="col-md-8">
                  <label class="form-label small mb-1">Identyfikator podatkowy</label>
                  <input type="text" name="tax_id_other" id="modal-tax-id-other"
                         class="form-control form-control-sm" maxlength="64"
                         placeholder="np. 12-3456789">
                </div>
              </div>
            </div>
          </div>
          <!-- /Identyfikacja -->

          <!-- Sekcja: Dane adresowe -->
          <div class="border rounded p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
              <strong class="small">Dane adresowe</strong>
            </div>
            <div class="row g-3">
              <div class="col-md-3">
                <div class="form-group">
                  <?= $this->Form->hidden('country', [ 'value' => 'PL', 'id' => 'country-hidden' ]) ?>
                  <label for="country-ui" class="form-label mb-0 d-block">Kraj</label>
                  <input type="text" id="country-ui" class="form-control" placeholder="Wybierz kraj">
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <?= $this->Form->control('city', [
                    'label' => 'Miejscowość', 'class' => 'form-control',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot"></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <?= $this->Form->control('street', [
                    'label' => 'Ulica i nr', 'class' => 'form-control',
                    'placeholder' => 'ul. i nr',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot"></div>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <?= $this->Form->control('postal_code', [
                    'label' => 'Kod pocztowy', 'class' => 'form-control',
                    'placeholder' => '00-000',
                    'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
                  ]) ?>
                  <div class="help-slot"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Notatki -->
          <div class="form-group">
            <?= $this->Form->control('notes', [
              'label' => 'Notatki', 'type' => 'textarea', 'rows' => 2, 'class' => 'form-control',
              'templates' => ['inputContainer' => '<div class="">{{content}}</div>']
            ]) ?>
            <div class="help-slot"></div>
          </div>

          
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', [
          'class' => 'btn btn-primary', 'escapeTitle' => false
        ]) ?>
      </div>
      <?= $this->Form->end() ?>
    </div>
  </div>
</div>

<style>
  /* stabilny layout modala */
  #contractor-create .form-group { display: block; }
  #contractor-create .help-slot { min-height: 1rem; line-height: 1rem; }
  #contractor-create .form-text { margin-top: .25rem; }
  #contractor-create .input-group > .form-control { min-width: 0; }
  #contractor-create .modal-body .row > [class*="col-"] { align-self: start; }

  /* Country-select dropdown musi wystawać poza modal — bez clippingu */
  #contractor-create .modal-dialog { overflow: visible; }
  #contractor-create .modal-content { overflow: visible; }
  #contractor-create .modal-body { overflow: visible; }
  .country-select .country-list { z-index: 9999 !important; max-height: 220px; overflow-y: auto; min-width: 260px !important; width: auto !important; }

  /* premium paginacja */
  .pagination .page-link { min-width: 2rem; text-align: center; }
  .pagination .page-item.active .page-link { box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }

  /* kompaktowa tabela */
  .table-compact .table td, .table-compact .table th { padding-top:.4rem; padding-bottom:.4rem; }

  /* === Tabela kontrahentów: hairline borders + hover row + status dot === */
  #contractors-table { border-collapse: separate; border-spacing: 0; }
  #contractors-table thead th {
    border-bottom: 1px solid rgba(15, 23, 42, .08);
    background: #fafbfd;
    font-weight: 600;
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .3px;
    color: #64748b;
  }
  #contractors-table tbody td { border-top: 1px solid rgba(15, 23, 42, .05); }
  #contractors-table tbody tr:first-child td { border-top: 0; }
  #contractors-table tbody tr {
    cursor: pointer;
    transition: background-color 120ms ease, box-shadow 120ms ease;
    position: relative;
  }
  #contractors-table tbody tr:hover {
    background-color: rgba(148, 212, 55, .06);
    box-shadow: inset 3px 0 0 0 var(--primary-color, #94d437);
  }
  /* Wiersz jest klikalny, a interaktywne elementy w nim mają pointer */
  #contractors-table .row-actions { cursor: default; }
  #contractors-table button,
  #contractors-table .dropdown-item,
  #contractors-table a,
  #contractors-table .row-check { cursor: pointer; }

  /* Kolumna "Kontrahent" — łamanie długich nazw, pozostałe kolumny no-wrap */
  #contractors-table tbody td:nth-child(2) {
    max-width: 360px;
    white-space: normal;
    word-break: break-word;
  }
  #contractors-table tbody td:nth-child(2) strong { word-break: break-word; }
  #contractors-table tbody td:not(:nth-child(2)) { white-space: nowrap; }
  #contractors-table thead th:not(:nth-child(2)) { white-space: nowrap; }

  /* Utility — używana w markupie modali, Bootstrap 5 jej nie ma */
  .min-width-0 { min-width: 0 !important; }

  /* Tytuł modala szczegółów: długie nazwy łam normalnie zamiast wychodzić poza ramkę */
  #contractor-details .modal-header,
  #contractor-invoices .modal-header { min-width: 0; }
  #contractor-details .modal-title,
  #contractor-invoices .modal-title { white-space: normal; word-break: break-word; min-width: 0; }
  #contractor-details .modal-title #cd-name,
  #contractor-invoices .modal-title #ci-name { word-break: break-word; }

  /* status dot */
  .status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 0 3px rgba(0,0,0,0);
  }
  .status-dot-active   { background: #22c55e; box-shadow: 0 0 0 3px rgba(34, 197, 94, .15); }
  .status-dot-inactive { background: #94a3b8; box-shadow: 0 0 0 3px rgba(148, 163, 184, .15); }

  /* === Modal: Szczegóły kontrahenta === */
  .cd-avatar {
    width: 48px; height: 48px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 50%;
    background: rgba(148, 212, 55, .12);
    color: var(--primary-color, #94d437);
    font-size: 24px;
    flex-shrink: 0;
  }
  .cd-avatar.is-person { background: rgba(56, 189, 248, .12); color: #38bdf8; }
  #contractor-details .cd-card {
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 12px;
    background: #fff;
    overflow: hidden;
  }
  #contractor-details .cd-card-header {
    background: #fafbfd;
    padding: .5rem .75rem;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: #64748b;
    font-weight: 600;
    border-bottom: 1px solid rgba(15, 23, 42, .06);
    display: flex; align-items: center; gap: .4rem;
  }
  #contractor-details .cd-card-header i { font-size: 14px; color: var(--primary-color, #94d437); }
  #contractor-details .cd-card-body { padding: .75rem; font-size: .9rem; }
  #contractor-details .cd-card-body code {
    font-size: .9rem;
    background: rgba(148, 212, 55, .08);
    color: #475569;
    padding: 2px 6px;
    border-radius: 4px;
  }
  #contractor-details .cd-row {
    display: flex; align-items: center; gap: .5rem;
    padding: .25rem 0;
  }
  #contractor-details .cd-row + .cd-row { border-top: 1px dashed rgba(15, 23, 42, .06); }
  #contractor-details .copy-btn { color: #94a3b8; }
  #contractor-details .copy-btn:hover { color: var(--primary-color, #94d437); }

  /* Soft tabs */
  .nav-tabs-soft { border-bottom: 1px solid rgba(15, 23, 42, .08); }
  .nav-tabs-soft .nav-link {
    border: 0;
    color: #64748b;
    font-size: .85rem;
    font-weight: 500;
    padding: .5rem 1rem;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
  }
  .nav-tabs-soft .nav-link.active {
    color: var(--primary-color, #94d437);
    border-bottom-color: var(--primary-color, #94d437);
    background: transparent;
  }
  .nav-tabs-soft .nav-link:hover { color: var(--primary-color, #94d437); }

  /* Statystyki kontrahenta — karty z liczbami + mini bar chart */
  #contractor-details .cd-stat-card {
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 10px;
    padding: .9rem 1rem;
    background: #fff;
    height: 100%;
  }
  #contractor-details .cd-stat-label {
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .3px;
    color: #64748b;
    font-weight: 600;
    margin-bottom: .25rem;
  }
  #contractor-details .cd-stat-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
  }
  #contractor-details .cd-stat-sub {
    font-size: .72rem;
    color: #94a3b8;
    margin-top: .25rem;
  }
  #contractor-details .stats-bars {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    height: 120px;
    padding-top: 8px;
  }
  #contractor-details .stats-bar-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    cursor: default;
  }
  #contractor-details .stats-bar {
    width: 100%;
    max-width: 32px;
    background: linear-gradient(180deg, var(--primary-color, #94d437) 0%, #84c02e 100%);
    border-radius: 4px 4px 0 0;
    transition: opacity .15s ease;
    margin-top: auto;
  }
  #contractor-details .stats-bar-wrap:hover .stats-bar { opacity: .75; }
  #contractor-details .stats-bar-label {
    font-size: .65rem;
    color: #94a3b8;
    margin-top: 4px;
  }

  /* Loader overlay w modalach */
  .cd-loader {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(2px);
    z-index: 1056;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: inherit;
  }

  /* Tabela faktur w szczegółach kontrahenta + w modalu Faktury */
  .cd-inv-table thead th {
    background: #fafbfd;
    border-bottom: 1px solid rgba(15, 23, 42, .08);
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .3px;
    color: #64748b;
    font-weight: 600;
    padding: .5rem .6rem;
  }
  .cd-inv-table tbody td {
    padding: .55rem .6rem;
    border-top: 1px solid rgba(15, 23, 42, .05);
    font-size: .88rem;
  }
  .cd-inv-table tbody tr:first-child td { border-top: 0; }
  .cd-inv-table tbody tr:hover { background: rgba(148, 212, 55, .05); }
  .cd-inv-table a.inv-num {
    color: #475569;
    font-weight: 600;
    text-decoration: none;
    border-bottom: 1px dashed rgba(15, 23, 42, .2);
  }
  .cd-inv-table a.inv-num:hover {
    color: var(--primary-color, #94d437);
    border-bottom-color: var(--primary-color, #94d437);
  }
  .cd-inv-table .pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 600;
  }
  .cd-inv-table .pill-paid  { background: rgba(34, 197, 94, .12); color: #16a34a; }
  .cd-inv-table .pill-open  { background: rgba(249, 115, 22, .12); color: #ea580c; }
  .cd-inv-table .pill-draft { background: rgba(100, 116, 139, .12); color: #475569; border: 1px dashed rgba(100, 116, 139, .35); }
  .cd-inv-table .pill-error { background: rgba(239, 68, 68, .12);  color: #dc2626; }
  .cd-inv-table .pill-sent  { background: rgba(56, 189, 248, .12); color: #0284c7; }
  .cd-inv-table .text-remaining { color: #ea580c; font-weight: 600; }
  .cd-inv-table .text-zero      { color: #94a3b8; }
  .cd-inv-table .inv-type {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: .68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .3px;
    background: rgba(15, 23, 42, .06);
    color: #64748b;
  }
  .cd-inv-table tr.inv-draft { background: rgba(100, 116, 139, .03); }
  .cd-inv-table tr.inv-draft .inv-num { font-style: italic; }

  /* Country Select JS flag sprite override to ensure CDN paths */
  .country-select .flag { background-image: url('https://cdnjs.cloudflare.com/ajax/libs/country-select-js/2.1.1/img/flags.png') !important; }
  @media only screen and (-webkit-min-device-pixel-ratio: 2),
         only screen and (min--moz-device-pixel-ratio: 2),
         only screen and (-o-min-device-pixel-ratio: 2/1),
         only screen and (min-device-pixel-ratio: 2),
         only screen and (min-resolution: 192dpi),
         only screen and (min-resolution: 2dppx) {
    .country-select .flag { background-image: url('https://cdnjs.cloudflare.com/ajax/libs/country-select-js/2.1.1/img/flags@2x.png') !important; }
  }
</style>
<!-- intl-tel-input CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/css/intlTelInput.css">
<!-- Country Select JS CSS (cdnjs with SRI) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/country-select-js/2.1.1/css/countrySelect.css" integrity="sha512-WPc1lYhwI/V+DbzjPRw98rLrQznhpPZ7C/d7K6Vc5s7Sxw2zEk4xLodZwPP0SQ3aLJsBbuaYF0iovbFs2zzKlw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<!-- Preload flag sprites -->
<link rel="preload" as="image" href="https://cdnjs.cloudflare.com/ajax/libs/country-select-js/2.1.1/img/flags.png">
<link rel="preload" as="image" href="https://cdnjs.cloudflare.com/ajax/libs/country-select-js/2.1.1/img/flags@2x.png">

<!-- Toaster -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080">
  <div id="toast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>
<!-- Modal: Postęp eksportu -->
<div class="modal fade" id="exportProgressModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="ri-upload-cloud-line me-2"></i>Eksport CSV</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <small class="text-muted" id="export-status">Przygotowywanie…</small>
          <small class="text-muted" id="export-percent">0%</small>
        </div>
        <div class="progress" role="progressbar" aria-label="Postęp eksportu" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar progress-bar-striped progress-bar-animated" id="export-progress" style="width:0%"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Ukryj</button>
        <button type="button" class="btn btn-outline-danger" id="export-cancel-btn">
          <i class="ri-close-line me-1"></i> Anuluj eksport
        </button>
      </div>
    </div>
  </div>
</div>
<style>
  #exportProgressModal .progress { height: .75rem; }
  #exportProgressModal .modal-footer .btn { min-width: 120px; }
</style>
<?php // scripts ?>
<!-- intl-tel-input JS -->
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/js/intlTelInput.min.js"></script>
<!-- jQuery (required by Country Select JS) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<!-- Country Select JS (cdnjs with SRI) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/country-select-js/2.1.1/js/countrySelect.min.js" integrity="sha512-criuU34pNQDOIx2XSSIhHSvjfQcek130Y9fivItZPVfH7paZDEdtAMtwZxyPq/r2pyr9QpctipDFetLpUdKY4g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  // CSRF z <meta name="csrfToken" ...> w layout
  const CSRF_TOKEN = document.querySelector('meta[name="csrfToken"]')?.getAttribute('content') || '';

  // density toggle
  const table = document.getElementById('contractors-table');
  const setDensity = (dense) => {
    table.classList.toggle('table-sm', dense);
    document.body.classList.toggle('table-compact', dense);
  };
  document.getElementById('density-normal')?.addEventListener('click', () => setDensity(false));
  document.getElementById('density-compact')?.addEventListener('click', () => setDensity(true));

  // select all + indeterminate + bulk-actions toolbar (delegowane na document — przeżywa AJAX refresh)
  const checkAll = document.getElementById('check-all');
  const bulkToolbar     = document.getElementById('bulk-actions-toolbar');
  const bulkCountEl     = document.getElementById('bulk-actions-count');
  const bulkClearBtn    = document.getElementById('bulk-actions-clear');
  const bulkActivateBtn = document.getElementById('bulk-activate');
  const bulkDeactivateBtn = document.getElementById('bulk-deactivate');
  const bulkExportBtn   = document.getElementById('bulk-export');

  function getRowChecks() { return document.querySelectorAll('#contractors-table .row-check'); }
  function getSelectedIds() {
    return [...getRowChecks()].filter(x => x.checked).map(x => x.value);
  }
  function syncBulkUI() {
    const rows = getRowChecks();
    const selected = [...rows].filter(x => x.checked).length;
    if (checkAll) {
      checkAll.indeterminate = selected > 0 && selected < rows.length;
      checkAll.checked = selected === rows.length && rows.length > 0;
    }
    if (bulkToolbar) {
      bulkToolbar.classList.toggle('d-none', selected === 0);
      bulkToolbar.classList.toggle('d-flex', selected > 0);
    }
    if (bulkCountEl) bulkCountEl.textContent = String(selected);
  }
  // Delegacja na document — `.row-check` ocaleje po AJAX-refresh tbody
  document.addEventListener('change', (e) => {
    if (e.target.classList?.contains('row-check')) syncBulkUI();
  });
  checkAll?.addEventListener('change', () => {
    getRowChecks().forEach(ch => ch.checked = checkAll.checked);
    syncBulkUI();
  });
  bulkClearBtn?.addEventListener('click', () => {
    getRowChecks().forEach(ch => ch.checked = false);
    syncBulkUI();
  });

  // Bulk: aktywuj / dezaktywuj
  async function bulkSetActive(active) {
    const ids = getSelectedIds();
    if (!ids.length) return;
    const label = active ? 'Aktywuj' : 'Dezaktywuj';
    const conf = await Swal.fire({
      title: `${label} ${ids.length} ${ids.length===1?'kontrahenta':'kontrahentów'}?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#94d437',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Tak',
      cancelButtonText: 'Anuluj'
    });
    if (!conf.isConfirmed) return;
    try {
      const fd = new FormData();
      ids.forEach(id => fd.append('ids[]', id));
      fd.set('active', active ? '1' : '0');
      const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'bulkSetActive']) ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
        body: fd
      });
      const data = await res.json();
      if (data.success) {
        showToast((active ? 'Aktywowano ' : 'Dezaktywowano ') + data.updated + ' ' + (data.updated===1?'kontrahenta.':'kontrahentów.'), 'success');
        try { await refreshContractorsTable(); } catch {}
        syncBulkUI();
      } else {
        showToast(data.message || 'Operacja nie powiodła się.', 'danger');
      }
    } catch (e) {
      showToast('Błąd połączenia z serwerem.', 'danger');
    }
  }
  bulkActivateBtn?.addEventListener('click', () => bulkSetActive(true));
  bulkDeactivateBtn?.addEventListener('click', () => bulkSetActive(false));

  // Bulk: eksport CSV zaznaczonych — zbuduj URL z ids[]
  bulkExportBtn?.addEventListener('click', () => {
    const ids = getSelectedIds();
    if (!ids.length) return;
    const url = new URL('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'export']) ?>', window.location.origin);
    ids.forEach(id => url.searchParams.append('ids[]', id));
    window.location.href = url.toString();
  });

  syncBulkUI();

  // Inicjalizuj dropdowny w tabeli z popperConfig.strategy='fixed' — menu wychodzi poza
  // klipujący .table-responsive i działa poprawnie nawet gdy jest tylko 1 wiersz w tabeli.
  function bindTableDropdowns() {
    document.querySelectorAll('#contractors-table [data-bs-toggle="dropdown"]').forEach(toggle => {
      if (toggle.dataset.ddBound) return;
      toggle.dataset.ddBound = '1';
      try {
        const existing = bootstrap.Dropdown.getInstance(toggle);
        if (existing) existing.dispose();
        new bootstrap.Dropdown(toggle, {
          popperConfig: (cfg) => ({ ...cfg, strategy: 'fixed' })
        });
      } catch {}
    });
  }
  // Inicjalizuje tooltipy Bootstrap dla elementów dodanych po refreshu AJAX
  function bindTableTooltips() {
    document.querySelectorAll('#contractors-table [data-bs-toggle="tooltip"]').forEach(el => {
      if (el.dataset.tipBound) return;
      el.dataset.tipBound = '1';
      try {
        const existing = bootstrap.Tooltip.getInstance(el);
        if (existing) existing.dispose();
        new bootstrap.Tooltip(el);
      } catch {}
    });
  }
  bindTableDropdowns();
  bindTableTooltips();

  // Back/Forward button — gdy user nawiguje przez historię (pushState), odśwież tabelę
  window.addEventListener('popstate', () => {
    try { refreshContractorsTable(); } catch {}
  });

  // Submit formularza filtrów → AJAX-refresh tabeli zamiast przeładowania strony.
  // Aktualizujemy też URL (history.pushState) żeby paginacja, bookmark i back-button działały.
  const filtersForm = document.getElementById('contractor-filters-form');
  filtersForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(filtersForm);
    const params = new URLSearchParams();
    for (const [k, v] of fd.entries()) {
      if (v !== '' && v !== null && v !== undefined) params.set(k, String(v));
    }
    const newUrl = filtersForm.action.split('?')[0] + (params.toString() ? ('?' + params.toString()) : '');
    try {
      history.pushState({}, '', newUrl);
    } catch {}
    try {
      await refreshContractorsTable();
    } catch {
      window.location.href = newUrl;
    }
  });

  // Odśwież tabelę kontrahentów przez AJAX — pobiera aktualny URL strony, parsuje HTML
  // i podmienia tylko <tbody>. Zachowuje paginację, filtry i stan przewijania.
  async function refreshContractorsTable() {
    const tbody = document.querySelector('#contractors-table tbody');
    if (!tbody) return;
    // wskaźnik wizualny — przyciemnienie i kursor wait
    tbody.style.opacity = '0.5';
    tbody.style.pointerEvents = 'none';
    try {
      const res = await fetch(window.location.href, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        credentials: 'same-origin'
      });
      if (!res.ok) throw new Error('http ' + res.status);
      const html = await res.text();
      const doc  = new DOMParser().parseFromString(html, 'text/html');
      const newTbody = doc.querySelector('#contractors-table tbody');
      if (!newTbody) throw new Error('Brak tbody w odpowiedzi');
      tbody.replaceWith(newTbody);
      // re-bind action handlers do nowych przycisków w tabeli
      document.querySelectorAll('#contractors-table .js-edit').forEach(bindEditHandler);
      document.querySelectorAll('#contractors-table .js-details').forEach(bindDetailsHandler);
      document.querySelectorAll('#contractors-table .js-recipients').forEach(bindRecipientsHandler);
      document.querySelectorAll('#contractors-table .js-invoices').forEach(bindInvoicesHandler);
      document.querySelectorAll('#contractors-table .js-settings').forEach(bindSettingsHandler);
      document.querySelectorAll('#contractors-table .js-delete-contractor').forEach(bindDeleteHandler);
      bindTableDropdowns();
      bindTableTooltips();
    } finally {
      const refreshed = document.querySelector('#contractors-table tbody');
      if (refreshed) { refreshed.style.opacity = ''; refreshed.style.pointerEvents = ''; }
      // Po podmianie tbody — nowe checkboxy są puste, więc count = 0; ukryj toolbar
      try { syncBulkUI(); } catch {}
    }
  }

  // copy buttons — delegacja na document, działa też dla elementów dodanych dynamicznie
  const toastEl = document.getElementById('toast');
  const toastBody = document.getElementById('toast-body');
  const toast = new bootstrap.Toast(toastEl, { delay: 1800 });

  // Helper: wyświetla toast w wybranym wariancie kolorystycznym.
  // variant: 'dark' (domyślny), 'success', 'danger', 'warning', 'info'
  function showToast(msg, variant = 'dark') {
    if (!toastEl) return;
    // Usuń poprzednie text-bg-* klasy
    toastEl.classList.remove('text-bg-dark','text-bg-success','text-bg-danger','text-bg-warning','text-bg-info','text-bg-primary');
    toastEl.classList.add('text-bg-' + variant);
    toastBody.textContent = String(msg ?? '');
    toast.show();
  }
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.copy-btn, [data-copy]');
    if (!btn || !btn.dataset.copy) return;
    e.preventDefault();
    e.stopPropagation();
    try {
      await navigator.clipboard.writeText(btn.dataset.copy);
      toastBody.textContent = 'Skopiowano: ' + btn.dataset.copy;
      toast.show();
    } catch {
      toastBody.textContent = 'Nie udało się skopiować';
      toast.show();
    }
  });

  // Live search (debounce 400ms) → AJAX-refresh tabeli zamiast przeładowania strony.
  const search = document.getElementById('live-search');
  if (search) {
    let t;
    const baseUrl = search.dataset.currentUrl;
    search.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(async () => {
        const url = new URL(baseUrl, window.location.origin);
        // Zachowaj aktualne wartości pozostałych filtrów (active, limit) z formularza
        const ff = document.getElementById('contractor-filters-form');
        if (ff) {
          const fd = new FormData(ff);
          for (const [k, v] of fd.entries()) {
            if (k === 'q') continue;
            if (v !== '' && v !== null && v !== undefined) url.searchParams.set(k, String(v));
          }
        }
        if (search.value.trim() !== '') url.searchParams.set('q', search.value.trim());
        try { history.pushState({}, '', url.toString()); } catch {}
        try { await refreshContractorsTable(); } catch { window.location.href = url.toString(); }
      }, 400);
    });
  }

  // modal + formularz
  const modalEl   = document.getElementById('contractor-create');
  const modal     = new bootstrap.Modal(modalEl);
  const form      = document.getElementById('contractor-form');
  const titleEl   = document.getElementById('contractor-modal-title');
  const idField   = document.getElementById('contractor-id');
  const methodFld = document.getElementById('contractor-method');

  modalEl?.addEventListener('shown.bs.modal', () => {
    modalEl.querySelector('input[name="name"]')?.focus();
  });

  // Usunięto stary listener z `form.classList.add('was-validated')` — Bootstrap pokazywał
  // zielone obramowanie na wszystkich :valid polach, czego nie chcemy. HTML5 validity
  // sprawdzana jest w głównym submit handlerze (niżej) i błędy lecą przez SweetAlert.

  // --- Walidacja NIP/PESEL + przełącznik trybu ---
  function onlyDigits(v) { return (v || '').replace(/\D+/g,''); }
  function validateNIP(nip) {
    const s = onlyDigits(nip);
    if (s.length !== 10) return false;
    const weights = [6,5,7,2,3,4,5,6,7];
    const digits = s.split('').map(d => Number(d));
    const sum = weights.reduce((acc,w,i) => acc + w * digits[i], 0);
    const control = sum % 11;
    return control === digits[9];
  }
  function validatePESEL(pesel) {
    const s = onlyDigits(pesel);
    if (s.length !== 11) return false;
    const w = [1,3,7,9,1,3,7,9,1,3];
    const d = s.split('').map(x => Number(x));
    const sum = w.reduce((acc,wi,i) => acc + wi * d[i], 0);
    const ctrl = (10 - (sum % 10)) % 10;
    return ctrl === d[10];
  }
  function setInvalid(input, msg) {
    if (!input) return;
    input.classList.add('is-invalid');
    let fb = input.nextElementSibling;
    if (!fb || !fb.classList.contains('invalid-feedback')) {
      fb = document.createElement('div');
      fb.className = 'invalid-feedback';
      input.insertAdjacentElement('afterend', fb);
    }
    fb.textContent = msg;
  }
  function clearInvalid(input) {
    if (!input) return;
    input.classList.remove('is-invalid');
    const fb = input.nextElementSibling;
    if (fb && fb.classList.contains('invalid-feedback')) fb.remove();
  }

  const usePesel   = document.getElementById('use-pesel');
  const nipGroup   = document.getElementById('nip-group');
  const peselGroup = document.getElementById('pesel-group');
  const peselToggleRow = document.getElementById('pesel-toggle-row');
  const showPesel = document.getElementById('show-pesel');
  const nipToggleRow = document.getElementById('nip-toggle-row');
  const showNip = document.getElementById('show-nip');
  const personNipGroup = document.getElementById('person-nip-group');
  const personNipInput = document.getElementById('person-nip');
  const companyFields = document.getElementById('company-fields');
  const personFields  = document.getElementById('person-fields');
  const nipInput   = document.getElementById('nip');
  const peselInput = document.getElementById('pesel');
  const gusBtn     = document.getElementById('gus-fetch');
  // privacy consent/basis removed per requirements
  const countryHidden  = document.getElementById('country-hidden');
  const countryUI      = document.getElementById('country-ui');
  // intl IDs / chip picker
  const idTypeHidden     = document.getElementById('id-type-hidden');
  const idTypeChipsRoot  = document.getElementById('id-type-chips');
  const idPanels         = document.querySelectorAll('[data-id-panel]');
  const vatPfxHidden     = document.getElementById('vat-prefix-hidden');
  const vatPfxUI         = document.getElementById('vat-prefix-ui');
  const vatPfxWrap       = document.getElementById('vat-prefix-wrapper');
  const modalVatEu       = document.getElementById('modal-vat-eu');
  const modalEori        = document.getElementById('modal-eori');
  const modalTaxOther    = document.getElementById('modal-tax-id-other');
  const modalTaxOtherC   = document.getElementById('modal-tax-id-other-country');
  const modalTaxOtherCUI = document.getElementById('modal-tax-id-other-country-ui');
  const cityInput      = form?.querySelector('input[name="city"]');
  const streetInput    = form?.querySelector('input[name="street"]');
  const postalInput    = form?.querySelector('input[name="postal_code"]');
  const phoneInput     = document.getElementById('phone');
  // recipient UI
  const addRecipientToggle = document.getElementById('add-recipient');
  const recipientFields     = document.getElementById('recipient-fields');
  const recipientName       = document.getElementById('recipient-name');
  const recipientEmail      = document.getElementById('recipient-email');
  const recipientNip        = document.getElementById('recipient-nip');
  const recipientGusBtn     = document.getElementById('recipient-gus-fetch');
  const recipientGusSpin    = document.getElementById('recipient-gus-spin');
  const recipientPhone      = document.getElementById('recipient-phone');
  const recipientCity       = document.getElementById('recipient-city');
  const recipientStreet     = document.getElementById('recipient-street');
  const recipientPostal     = document.getElementById('recipient-postal');

  // Pole telefonu pozostaje plainem (bez intl-tel-input/flag) — użytkownik wpisuje numer w dowolnym formacie.
  let iti = null;
  let rti = null;

  // CountrySelect dla "Kraj kontrahenta" — niezależne od telefonu.
  if (window.jQuery && jQuery.fn.countrySelect && countryUI) {
    const $ui = jQuery(countryUI);
    $ui.countrySelect({ defaultCountry: (countryHidden?.value || 'PL').toLowerCase() });
    try {
      const init = $ui.countrySelect('getSelectedCountryData');
      if (init?.iso2) {
        countryHidden.value = init.iso2.toUpperCase();
        // Nadpisz widoczne pole polską nazwą (plugin domyślnie wstawia angielską)
        const plName = plCountryName(init.iso2);
        if (plName) countryUI.value = plName;
      }
    } catch {}
    $ui.on('change', () => {
      try {
        const d = $ui.countrySelect('getSelectedCountryData');
        if (d?.iso2) {
          countryHidden.value = d.iso2.toUpperCase();
          const plName = plCountryName(d.iso2);
          if (plName) countryUI.value = plName;
          applyCountryRequirements();
        }
      } catch {}
    });
  }

  function applyModeUI() {
    const peselMode = !!usePesel?.checked; // person mode
    // Synchronizuj ukryte pole is_person z checkboxem (jawna flaga dla backendu)
    const isPersonHidden = document.getElementById('is-person-hidden');
    if (isPersonHidden) isPersonHidden.value = peselMode ? '1' : '0';
    // Sync chip-pickera typu kontrahenta z checkboxem (pokrycie wszystkich wejść: reset, edit, programowo)
    document.getElementById('type-chips')?.querySelectorAll('button[data-type]').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.type === (peselMode ? 'person' : 'company'));
    });
    const hintEl = document.getElementById('type-chips-hint');
    if (hintEl) {
      hintEl.textContent = peselMode
        ? 'W trybie osoby fizycznej podaj imię i nazwisko. PESEL jest opcjonalny.'
        : 'Dla firmy podaj nazwę i NIP / VAT-UE / NrID.';
    }
    // toggle groups
    // Company mode: company NIP (with GUS) visible; Person mode: hide company NIP, show person NIP when toggled
    if (!peselMode) {
      nipGroup?.classList.remove('d-none');
      personNipGroup?.classList.add('d-none');
    } else {
      nipGroup?.classList.add('d-none');
      personNipGroup?.classList.toggle('d-none', !showNip?.checked);
    }
    peselGroup?.classList.toggle('d-none', !peselMode);
    companyFields?.classList.toggle('d-none', peselMode);
    personFields?.classList.toggle('d-none', !peselMode);
    // Sekcja "Identyfikacja kontrahenta" (chip-picker) — tylko dla firmy
    document.getElementById('identification-section')?.classList.toggle('d-none', peselMode);
    // required flags — NIP wymagany tylko dla firmy w trybie "NIP PL"
    const currIdType = document.getElementById('id-type-hidden')?.value || 'nip_pl';
    if (nipInput)   nipInput.required = !peselMode && currIdType === 'nip_pl';
    if (peselInput) peselInput.required = false; // PESEL optional
    // names requirement: company vs person
    const nameInput     = form?.querySelector('input[name="name"]');
    const altnameInput  = form?.querySelector('input[name="altname"]');
    const firstName     = form?.querySelector('input[name="first_name"]');
    const lastName      = form?.querySelector('input[name="last_name"]');
    if (nameInput)    nameInput.required    = !peselMode;
    if (altnameInput) altnameInput.required = false;
    if (firstName)    firstName.required    = peselMode;
    if (lastName)     lastName.required     = peselMode;
    // GUS: never allow in person mode (no GUS for individuals)
    if (gusBtn) gusBtn.disabled = peselMode ? true : false;
    // PESEL: show toggle w person mode; pole peselGroup widoczne gdy showPesel zaznaczony LUB gdy PESEL już wpisany
    peselToggleRow?.classList.toggle('d-none', !peselMode);
    if (peselMode) {
      const hasPesel = (peselInput?.value || '').trim() !== '';
      if (showPesel && hasPesel) showPesel.checked = true;
      peselGroup?.classList.toggle('d-none', !(showPesel?.checked || hasPesel));
    } else {
      peselGroup?.classList.add('d-none');
    }
    // NIP toggle visible only in person mode
    nipToggleRow?.classList.toggle('d-none', !peselMode);
    if (!peselMode) { if (showNip) showNip.checked = false; }
    // recipient available only for company mode: hide entire section in PESEL mode
    document.getElementById('recipient-section')?.classList.toggle('d-none', peselMode);
    document.getElementById('recipient-toggle-row')?.classList.toggle('d-none', peselMode);
    if (peselMode) { if (addRecipientToggle) addRecipientToggle.checked = false; recipientFields?.classList.add('d-none'); }
    // defaults for legal basis
    // privacy controls removed
    // clear previous errors
    clearInvalid(nipInput); clearInvalid(peselInput);
    clearInvalid(nameInput); clearInvalid(firstName); clearInvalid(lastName);

    // In edit mode: always hide recipient section regardless of type
    try {
      const mode = modalEl?.dataset?.mode;
      if (mode === 'edit') {
        const rs = document.getElementById('recipient-section');
        const rt = document.getElementById('recipient-toggle-row');
        const rf = document.getElementById('recipient-fields');
        const ar = document.getElementById('add-recipient');
        rs?.classList.add('d-none');
        rt?.classList.add('d-none');
        rf?.classList.add('d-none');
        if (ar) { ar.checked = false; ar.disabled = true; }
      }
    } catch {}

    // Adres staje się wymagany dla osoby (każdy kraj) — przelicz po zmianie trybu
    try { applyCountryRequirements(); } catch {}
  }
  // toggle recipient extra fields
  addRecipientToggle?.addEventListener('change', () => {
    recipientFields?.classList.toggle('d-none', !addRecipientToggle.checked);
  });
  // toggle optional PESEL field
  showPesel?.addEventListener('change', () => {
    peselGroup?.classList.toggle('d-none', !showPesel.checked);
  });
  // toggle optional NIP field (person mode)
  showNip?.addEventListener('change', () => {
    const peselMode = !!usePesel?.checked;
    personNipGroup?.classList.toggle('d-none', !showNip.checked);
    nipGroup?.classList.add('d-none');
    if (gusBtn) gusBtn.disabled = peselMode ? true : false; // GUS never for person
  });

  // Live validation for person NIP (optional)
  personNipInput?.addEventListener('input', () => {
    const v = (personNipInput.value || '').replace(/\D+/g,'');
    personNipInput.value = v;
    clearInvalid(personNipInput);
    if (v.length === 10 && !validateNIP(v)) {
      setInvalid(personNipInput, 'Nieprawidłowy NIP — sprawdź sumę kontrolną.');
    }
  });
  personNipInput?.addEventListener('blur', () => {
    const v = (personNipInput.value || '').replace(/\D+/g,'');
    if (v && !validateNIP(v)) setInvalid(personNipInput, 'Nieprawidłowy NIP — 10 cyfr i poprawna kontrola.');
  });

  // Live validation for recipient NIP
  recipientNip?.addEventListener('input', () => {
    const v = (recipientNip.value || '').replace(/\D+/g,'');
    recipientNip.value = v;
    clearInvalid(recipientNip);
    if (v.length === 10 && !validateNIP(v)) {
      setInvalid(recipientNip, 'Nieprawidłowy NIP odbiorcy — sprawdź sumę kontrolną.');
    }
  });
  recipientNip?.addEventListener('blur', () => {
    const v = (recipientNip.value || '').replace(/\D+/g,'');
    if (v && !validateNIP(v)) setInvalid(recipientNip, 'Nieprawidłowy NIP odbiorcy — 10 cyfr i poprawna kontrola.');
  });

  // GUS fetch for recipient
  recipientGusBtn?.addEventListener('click', async () => {
    const nip = (recipientNip?.value || '').replace(/\D+/g,'');
    if (!nip || nip.length !== 10) {
      toastBody.textContent = 'Podaj prawidłowy NIP odbiorcy (10 cyfr).';
      toast.show();
      return;
    }
    try {
      recipientGusBtn.disabled = true; recipientGusSpin.classList.remove('d-none');
      const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'gusLookup']) ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN},
        body: JSON.stringify({ nip })
      });
      const data = await res.json();
      if (data.success) {
        const c = data.contractor || {};
        if (recipientName)  recipientName.value  = c.name || recipientName.value;
        if (recipientCity)  recipientCity.value  = c.city || recipientCity.value;
        if (recipientPostal)recipientPostal.value= c.zip || recipientPostal.value;
        if (recipientStreet)recipientStreet.value= c.street || recipientStreet.value;
        toastBody.textContent = 'Pobrano dane odbiorcy z GUS.';
        toast.show();
      } else {
        toastBody.textContent = data.message || 'Brak danych w GUS dla NIP odbiorcy.';
        toast.show();
      }
    } catch (err) {
      toastBody.textContent = 'Błąd pobierania z GUS dla odbiorcy.';
      toast.show();
    } finally {
      recipientGusBtn.disabled = false; recipientGusSpin.classList.add('d-none');
    }
  });

  // privacy quote removed

  usePesel?.addEventListener('change', applyModeUI);

  // Chip-picker typu kontrahenta (Firma / Osoba fizyczna)
  const typeChipsRoot = document.getElementById('type-chips');
  const typeChipsHint = document.getElementById('type-chips-hint');
  function setContractorType(type) {
    const isPerson = type === 'person';
    if (usePesel && !usePesel.disabled) usePesel.checked = isPerson;
    typeChipsRoot?.querySelectorAll('button[data-type]').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.type === type);
    });
    if (typeChipsHint) {
      typeChipsHint.textContent = isPerson
        ? 'W trybie osoby fizycznej podaj imię i nazwisko. PESEL jest opcjonalny.'
        : 'Dla firmy podaj nazwę i NIP / VAT-UE / NrID.';
    }
    applyModeUI();
  }
  typeChipsRoot?.querySelectorAll('button[data-type]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (usePesel?.disabled) return; // edycja — typ zablokowany
      setContractorType(btn.dataset.type);
    });
  });

  applyModeUI();
  // Country-specific required fields (PL requires city/street/postal_code)
  function applyCountryRequirements() {
    // Adres ZAWSZE wymagany — zarówno dla firmy jak i osoby fizycznej, niezależnie od kraju.
    const addrRequired = true;
    if (cityInput)   cityInput.required   = addrRequired;
    if (streetInput) streetInput.required = addrRequired;
    if (postalInput) postalInput.required = addrRequired;
    // visual cue: add/remove star next to labels
    const mark = (name, req) => {
      const lbl = form?.querySelector(`label[for="${name}"]`);
      if (!lbl) return;
      let star = lbl.querySelector('.req-star');
      if (req) {
        if (!star) {
          star = document.createElement('span');
          star.className = 'req-star text-danger ms-1';
          star.textContent = '*';
          lbl.appendChild(star);
        }
      } else {
        star?.remove();
      }
    };
    mark('city', addrRequired);
    mark('street', addrRequired);
    mark('postal-code', addrRequired);
    mark('postal_code', addrRequired);
  }
  // country UI change is already wired above; ensure requirements applied on load
  applyCountryRequirements();
  // ensure default UI after modal opens/resets
  modalEl?.addEventListener('shown.bs.modal', applyModeUI);

  // Kraje członkowskie UE (ISO 3166-1 alpha-2, lowercase) — używane do ograniczenia listy prefiksów VAT
  // i wykluczenia z listy "Kod kraju (NrID)" (NrID = identyfikator spoza UE)
  const EU_COUNTRIES = ['at','be','bg','hr','cy','cz','dk','ee','fi','fr','de','gr','hu','ie','it','lv','lt','lu','mt','nl','pl','pt','ro','sk','si','es','se'];

  // ── Identyfikatory międzynarodowe – countrySelect + handlers ──
  if (window.jQuery && jQuery.fn.countrySelect && modalTaxOtherCUI) {
    try {
      jQuery(modalTaxOtherCUI).countrySelect({ defaultCountry: '', responsiveDropdown: true, excludeCountries: EU_COUNTRIES });
      jQuery(modalTaxOtherCUI).on('change', function () {
        try {
          const d = jQuery(modalTaxOtherCUI).countrySelect('getSelectedCountryData');
          if (d && d.iso2 && modalTaxOtherC) modalTaxOtherC.value = d.iso2.toUpperCase();
          if (d && d.iso2) {
            const plName = plCountryName(d.iso2);
            if (plName) modalTaxOtherCUI.value = plName;
          }
        } catch {}
      });
    } catch {}
  }

  // Weryfikacja VAT-UE w VIES (Komisja Europejska)
  const viesBtn = document.getElementById('vies-check-btn');
  const viesSpin = document.getElementById('vies-spin');
  const viesIcon = document.getElementById('vies-icon');
  const viesResult = document.getElementById('vies-result');
  viesBtn?.addEventListener('click', async () => {
    const prefix = (vatPfxHidden?.value || '').trim().toUpperCase();
    const number = (modalVatEu?.value || '').trim();
    if (!prefix || prefix === 'NONE') {
      showToast('Wybierz najpierw Prefiks UE (kraj).', 'warning');
      return;
    }
    if (!number) {
      showToast('Podaj Numer VAT-UE do weryfikacji.', 'warning');
      return;
    }
    viesBtn.disabled = true;
    viesSpin?.classList.remove('d-none');
    viesIcon?.classList.add('d-none');
    viesResult?.classList.add('d-none');
    try {
      const fd = new FormData();
      fd.set('prefix', prefix);
      fd.set('number', number);
      const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'viesCheck']) ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
        body: fd
      });
      const data = await res.json();
      if (!viesResult) return;
      viesResult.classList.remove('d-none', 'text-success', 'text-danger', 'text-warning');
      if (data.success && data.valid) {
        viesResult.classList.add('text-success');
        let html = '<i class="ri-checkbox-circle-fill"></i> Aktywny w VIES';
        if (data.name) html += ' — <strong>' + data.name.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</strong>';
        if (data.address) html += '<br><span class="text-muted">' + data.address.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</span>';
        viesResult.innerHTML = html;
      } else if (data.success && !data.valid) {
        viesResult.classList.add('text-danger');
        viesResult.innerHTML = '<i class="ri-close-circle-fill"></i> Niezarejestrowany w VIES — sprawdź numer.';
      } else {
        viesResult.classList.add('text-warning');
        viesResult.innerHTML = '<i class="ri-error-warning-fill"></i> ' + (data.message || 'Nie udało się sprawdzić w VIES.');
      }
    } catch (e) {
      if (viesResult) {
        viesResult.classList.remove('d-none');
        viesResult.classList.add('text-warning');
        viesResult.innerHTML = '<i class="ri-error-warning-fill"></i> Błąd połączenia z VIES.';
      }
    } finally {
      viesBtn.disabled = false;
      viesSpin?.classList.add('d-none');
      viesIcon?.classList.remove('d-none');
    }
  });

  // CountrySelect dla prefiksu VAT-UE (tylko kraje UE)
  if (window.jQuery && jQuery.fn.countrySelect && vatPfxUI) {
    try {
      jQuery(vatPfxUI).countrySelect({
        defaultCountry: '',
        responsiveDropdown: true,
        onlyCountries: EU_COUNTRIES,
      });
      jQuery(vatPfxUI).on('change', function () {
        try {
          const d = jQuery(vatPfxUI).countrySelect('getSelectedCountryData');
          if (d && d.iso2) {
            vatPfxHidden.value = d.iso2.toUpperCase();
            const plName = plCountryName(d.iso2);
            if (plName) vatPfxUI.value = plName;
          }
        } catch {}
      });
    } catch {}
  }

  // ── Chip-picker typu identyfikatora ──
  // Wartości: 'nip_pl' | 'vat_eu' | 'non_eu'
  function setIdType(type) {
    const valid = ['nip_pl','vat_eu','non_eu'];
    if (!valid.includes(type)) type = 'nip_pl';
    if (idTypeHidden) idTypeHidden.value = type;

    // Highlight chip
    idTypeChipsRoot?.querySelectorAll('button[data-id-type]').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.idType === type);
    });

    // Pokaż/ukryj panele
    idPanels.forEach(p => p.classList.toggle('d-none', p.dataset.idPanel !== type));

    // Wyczyść pola nieaktywnych paneli
    if (type !== 'nip_pl') {
      const nipEl = document.getElementById('nip');
      if (nipEl) nipEl.value = '';
    }
    if (type !== 'vat_eu') {
      if (vatPfxHidden) vatPfxHidden.value = type === 'non_eu' ? 'NONE' : '';
      try { if (window.jQuery && jQuery.fn.countrySelect && vatPfxUI) jQuery(vatPfxUI).countrySelect('selectCountry', ''); } catch {}
      if (modalVatEu) modalVatEu.value = '';
      if (modalEori)  modalEori.value  = '';
    }
    if (type !== 'non_eu') {
      if (modalTaxOther)  modalTaxOther.value  = '';
      if (modalTaxOtherC) modalTaxOtherC.value = '';
      try { if (window.jQuery && jQuery.fn.countrySelect && modalTaxOtherCUI) jQuery(modalTaxOtherCUI).countrySelect('selectCountry', ''); } catch {}
    }

    // Przelicz required (NIP wymagany tylko dla nip_pl w trybie firmy)
    try { applyModeUI(); } catch {}
  }

  // PL-locale display names dla kodów ISO2 (Intl.DisplayNames — fallback do angielskiej nazwy z plugina)
  let __plRegionNames = null;
  try { __plRegionNames = new Intl.DisplayNames(['pl'], { type: 'region' }); } catch {}
  function plCountryName(iso2) {
    const code = String(iso2 || '').toUpperCase();
    if (!code) return '';
    if (__plRegionNames) {
      try {
        const n = __plRegionNames.of(code);
        if (n && n !== code) return n;
      } catch {}
    }
    return '';
  }

  // Helper: ustaw kraj w countrySelect i wymuś pokazanie polskiej nazwy w widocznym polu
  function setCountryWithName(uiEl, iso2) {
    if (!uiEl || !window.jQuery || !jQuery.fn.countrySelect) return;
    try {
      const $ui = jQuery(uiEl);
      $ui.countrySelect('selectCountry', String(iso2 || '').toLowerCase());
      const plName = plCountryName(iso2);
      if (plName) {
        uiEl.value = plName;
      } else {
        const d = $ui.countrySelect('getSelectedCountryData');
        if (d && d.name) uiEl.value = d.name;
      }
    } catch {}
  }

  // Czy w aktywnym panelu są jakieś wpisane dane (które stracimy przy zmianie chipa)?
  function currentPanelHasData() {
    const curr = idTypeHidden?.value || 'nip_pl';
    const v = (el) => (el?.value || '').trim();
    if (curr === 'nip_pl') {
      return v(document.getElementById('nip')) !== '';
    }
    if (curr === 'vat_eu') {
      return v(vatPfxHidden) !== '' || v(modalVatEu) !== '' || v(modalEori) !== '';
    }
    if (curr === 'non_eu') {
      return v(modalTaxOther) !== '' || v(modalTaxOtherC) !== '';
    }
    return false;
  }

  idTypeChipsRoot?.querySelectorAll('button[data-id-type]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const targetType = btn.dataset.idType;
      const currType = idTypeHidden?.value || 'nip_pl';
      if (targetType === currType) return; // bez zmian
      if (currentPanelHasData() && typeof Swal !== 'undefined') {
        const result = await Swal.fire({
          title: 'Zmienić typ identyfikatora?',
          text: 'Dane wpisane w bieżącej zakładce zostaną wyczyszczone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#94d437',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Tak, zmień',
          cancelButtonText: 'Anuluj'
        });
        if (!result.isConfirmed) return;
      }
      setIdType(targetType);
    });
  });

  // live validation
  nipInput?.addEventListener('input', () => {
    clearInvalid(nipInput);
  });
  nipInput?.addEventListener('blur', () => {
    // NIP checksum validation intentionally skipped — supports foreign VAT numbers
  });

  peselInput?.addEventListener('input', () => {
    const v = onlyDigits(peselInput.value);
    peselInput.value = v;
    clearInvalid(peselInput);
    if (v.length === 11 && !validatePESEL(v)) {
      setInvalid(peselInput, 'Nieprawidłowy PESEL — sprawdź cyfrę kontrolną.');
    }
  });
  peselInput?.addEventListener('blur', () => {
    const v = onlyDigits(peselInput.value);
    if (v && !validatePESEL(v)) setInvalid(peselInput, 'Nieprawidłowy PESEL — wymaga 11 cyfr i poprawnej kontroli.');
  });

  // Oznacz pola jako "tknięte" — jeśli user kliknął w PESEL/NIP osoby i wyszedł bez wypełnienia,
  // submit handler to wyłapie i zasygnalizuje błąd.
  [peselInput, personNipInput].forEach(el => {
    if (!el) return;
    el.addEventListener('focus', () => { el.dataset.touched = '1'; });
  });

  // helper ustawiania wartości
  function setVal(selector, value) {
    const el = form?.querySelector(selector);
    if (el) el.value = value ?? '';
  }

  // reset do dodawania
  function resetFormToAdd() {
    form.reset();
    idField.value = '';
    methodFld.value = 'POST';
    form.setAttribute('action', '<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'add']) ?>');
    modalEl.dataset.mode = 'add';
    titleEl.textContent = 'Dodaj kontrahenta';
    // Tryb dodawania — nigdy nie pokazujemy loadera (brak fetch danych)
    document.getElementById('cc-loader')?.classList.add('d-none');
    // wyczyść stany walidacji — żadnych zielonych/czerwonych podświetleń przy nowym otwarciu
    form.classList.remove('was-validated');
    form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(i => i.remove());
    // Wyczyść `touched` flagi PESEL/NIP osoby — nowe otwarcie modala startuje świeżo
    [peselInput, personNipInput].forEach(el => { if (el) delete el.dataset.touched; });
    // Resetuj widoczny "Kraj" na polską nazwę (form.reset() może wrócić do angielskiej z plugina)
    try {
      if (window.jQuery && jQuery.fn.countrySelect && countryUI) {
        jQuery(countryUI).countrySelect('selectCountry', 'pl');
        const plName = plCountryName('pl');
        if (plName) countryUI.value = plName;
        if (countryHidden) countryHidden.value = 'PL';
      }
    } catch {}
    // Odblokuj wszystkie pola firm/osoby/NIP/GUS — edycja osoby/firmy zostawia disabled na
    // niewłaściwych polach, co przy następnym Dodaj/Edytuj pozostawia np. NIP/nazwę szare.
    document.querySelectorAll(
      '#company-fields input, #person-fields input, #nip-group input, #person-nip-group input, #gus-fetch, #show-nip, #show-pesel'
    ).forEach(el => {
      if (el instanceof HTMLInputElement || el instanceof HTMLButtonElement) {
        el.disabled = false;
      }
    });
    // Re-enable and show type toggle for add mode
    const usePeselEl = document.getElementById('use-pesel');
    if (usePeselEl) {
      usePeselEl.disabled = false;
      // odblokuj chip-picker w trybie add
      document.getElementById('type-chips')?.classList.remove('d-none');
      // Default to company mode in add
      usePeselEl.checked = false;
    }
    // Ensure recipient section is restored for add mode
    const recipientSection   = document.getElementById('recipient-section');
    const recipientToggleRow = document.getElementById('recipient-toggle-row');
    const recipientFields    = document.getElementById('recipient-fields');
    const addRecipientToggle = document.getElementById('add-recipient');
    recipientSection?.classList.remove('d-none');
    recipientToggleRow?.classList.remove('d-none');
    recipientFields?.classList.add('d-none');
    if (addRecipientToggle) { addRecipientToggle.disabled = false; addRecipientToggle.checked = false; }
    // Reset sekcji identyfikatorów — domyślny chip = NIP PL
    if (vatPfxHidden) vatPfxHidden.value = '';
    if (modalVatEu) modalVatEu.value = '';
    if (modalEori)  modalEori.value  = '';
    if (modalTaxOther) modalTaxOther.value = '';
    if (modalTaxOtherC) modalTaxOtherC.value = '';
    try { if (window.jQuery && jQuery.fn.countrySelect) jQuery(vatPfxUI).countrySelect('selectCountry', ''); } catch {}
    try { if (window.jQuery && jQuery.fn.countrySelect && modalTaxOtherCUI) jQuery(modalTaxOtherCUI).countrySelect('selectCountry', ''); } catch {}
    setIdType('nip_pl');
    // Ensure UI reflects default add mode
    try { applyModeUI(); } catch {}
  }

  // otwarcie w trybie add (klik w przycisk, który ma data-bs-target="#contractor-create")
  document.querySelectorAll('[data-bs-target="#contractor-create"]').forEach(btn => {
    btn.addEventListener('click', resetFormToAdd);
  });

  // klik edycji w wierszu
  document.querySelectorAll('.js-edit').forEach(bindEditHandler);
  function bindEditHandler(btn) {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      if (!id) return;

      resetFormToAdd();
      titleEl.textContent = 'Edytuj: ' + (btn.dataset.name || ('#' + id));
      modalEl.dataset.mode = 'edit';
      idField.value = id;
      methodFld.value = 'PUT';
      form.setAttribute('action', '<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'edit']) ?>/' + id);

      // Otwórz modal natychmiast + pokaż loader przed fetch danych
      const ccLoader = document.getElementById('cc-loader');
      ccLoader?.classList.remove('d-none');
      modal.show();

          // Hide recipient section entirely during edit
          const recipientSection = document.getElementById('recipient-section');
          const recipientToggleRow = document.getElementById('recipient-toggle-row');
          const recipientFields = document.getElementById('recipient-fields');
          const addRecipientToggle = document.getElementById('add-recipient');
          recipientSection?.classList.add('d-none');
          recipientToggleRow?.classList.add('d-none');
          recipientFields?.classList.add('d-none');
          if (addRecipientToggle) { addRecipientToggle.checked = false; addRecipientToggle.disabled = true; }

          // Also hide type switch section during edit
          const typeSwitchSection = document.getElementById('type-switch-section');
          typeSwitchSection?.classList.add('d-none');

      try {
        const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'viewJson']) ?>/' + id, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.success) throw new Error('Brak danych');

        const c = data.contractor || {};
        setVal('input[name="name"]',        c.name);
        setVal('input[name="altname"]',     c.altname);
        setVal('input[name="first_name"]',  c.first_name);
        setVal('input[name="last_name"]',   c.last_name);
        setVal('input[name="nip"]',         c.nip);
        setVal('input[name="pesel"]',       c.pesel);
        setVal('input[name="email"]',       c.email);
        setVal('input[name="phone"]',       c.phone);
        setVal('input[name="country"]',     c.country || 'PL');
        setVal('input[name="postal_code"]', c.postal_code);
        setVal('input[name="city"]',        c.city);
        setVal('input[name="street"]',      c.street);
        // local_number removed; captured in street field
        const notes = form.querySelector('textarea[name="notes"]'); if (notes) notes.value = c.notes ?? '';
        // UWAGA: CakePHP renderuje 2 inputy is_active — ukryty (=0) i checkbox (=1). Targetujemy explicit checkbox.
        const chk = form.querySelector('input[type="checkbox"][name="is_active"]'); if (chk) chk.checked = Number(c.is_active) === 1;
        // Wartości identyfikatorów do paneli
        const vpIsNone = c.vat_prefix === 'NONE';
        if (vatPfxHidden) vatPfxHidden.value = vpIsNone ? 'NONE' : (c.vat_prefix || '');
        if (vpIsNone) {
          setCountryWithName(vatPfxUI, '');
        } else if (c.vat_prefix) {
          setCountryWithName(vatPfxUI, c.vat_prefix);
        }
        if (modalVatEu) modalVatEu.value = c.vat_eu || '';
        if (modalEori)  modalEori.value  = c.eori  || '';
        if (modalTaxOther)  modalTaxOther.value  = c.tax_id_other || '';
        if (modalTaxOtherC) modalTaxOtherC.value = c.tax_id_other_country || '';
        if (c.tax_id_other_country) setCountryWithName(modalTaxOtherCUI, c.tax_id_other_country);

        // Wybór chipu na podstawie zapisanych danych:
        // - vat_prefix='NONE' lub tax_id_other ustawione → 'non_eu'
        // - inne vat_prefix lub vat_eu ustawione → 'vat_eu'
        // - reszta → 'nip_pl'
        let savedIdType = 'nip_pl';
        if (vpIsNone || c.tax_id_other || c.tax_id_other_country) savedIdType = 'non_eu';
        else if ((c.vat_prefix && c.vat_prefix !== 'NONE') || c.vat_eu || c.eori) savedIdType = 'vat_eu';
        setIdType(savedIdType);
        // apply privacy fields and mode
        const usePeselEl = document.getElementById('use-pesel');
        // Preferuj jawną flagę c.is_person, fallback na heurystykę po polach
        const isPerson = (c.is_person === 1 || c.is_person === true)
          || (!!(c.pesel && !c.nip))
          || (!!c.first_name || !!c.last_name);
        if (usePeselEl) {
          // In edit mode, permanently lock the original type:
          usePeselEl.checked = isPerson;
          // Disable and hide the toggle so user cannot switch modes during edit
          usePeselEl.disabled = true;
          // schowaj chip-picker w trybie edycji — typ kontrahenta zablokowany
          document.getElementById('type-chips')?.classList.add('d-none');
          // Jeśli osoba ma PESEL — odkryj switch + pole PESEL (applyModeUI zostawi to widoczne)
          if (isPerson && c.pesel) {
            if (showPesel) showPesel.checked = true;
            peselGroup?.classList.remove('d-none');
          }
          // Jeśli osoba ma NIP — odkryj opcjonalny NIP osoby i wpisz wartość do #person-nip
          if (isPerson && c.nip) {
            if (showNip) showNip.checked = true;
            personNipGroup?.classList.remove('d-none');
            const personNip = document.getElementById('person-nip');
            if (personNip) personNip.value = c.nip;
          }
          // Reflect UI for locked mode
          try { applyModeUI(); } catch {}
        }
        // Additionally lock opposite fields per original choice
        if (isPerson) {
          // Person: disable company fields and NIP group, hide GUS
          document.querySelectorAll('#company-fields input, #nip-group input, #gus-fetch').forEach(el => {
            if (el instanceof HTMLInputElement) el.disabled = true;
            else if (el instanceof HTMLButtonElement) el.disabled = true;
          });
        } else {
          // Company: disable person fields and optional person NIP controls
          document.querySelectorAll('#person-fields input, #person-nip-group input, #show-nip').forEach(el => {
            if (el instanceof HTMLInputElement) el.disabled = true;
          });
        }
        
        // sync country UI
        try {
          const countryHidden = document.getElementById('country-hidden');
          const countryUI = document.getElementById('country-ui');
          if (countryHidden && c.country) countryHidden.value = c.country;
          if (c.country) setCountryWithName(countryUI, c.country);
          if (window.itiPhone && c.country) {
            window.itiPhone.setCountry((c.country || 'PL').toLowerCase());
          }
          applyCountryRequirements();
        } catch {}
      } catch (e) {
        toastBody.textContent = 'Nie udało się wczytać danych kontrahenta.';
        toast.show();
      } finally {
        document.getElementById('cc-loader')?.classList.add('d-none');
      }
    });
  }

  // klik szczegółów w wierszu
  const detailsModalEl = document.getElementById('contractor-details');
  const detailsModal   = detailsModalEl ? new bootstrap.Modal(detailsModalEl) : null;
  const cdName   = document.getElementById('cd-name');
  const cdIdent  = document.getElementById('cd-ident');
  const cdEmail  = document.getElementById('cd-email');
  const cdPhone  = document.getElementById('cd-phone');
  const cdAddr   = document.getElementById('cd-address');
  const cdCountry= document.getElementById('cd-country');
  const cdStatus = document.getElementById('cd-status');
  const cdNotes  = document.getElementById('cd-notes');
  // Klik w wiersz tabeli (poza akcjami/checkboxami/linkami) — otwórz szczegóły kontrahenta.
  // Delegacja na `document` (a nie tbody), bo tbody jest podmieniany w refreshContractorsTable()
  // i listener wpięty do starego tbody przepada razem z DOM-em.
  document.addEventListener('click', (e) => {
    if (!e.target.closest('#contractors-table tbody')) return;
    if (e.target.closest('.row-actions, .row-check, a, button, .dropdown-menu, .copy-btn')) return;
    const row = e.target.closest('tr');
    const detailsBtn = row?.querySelector('.js-details');
    if (detailsBtn) detailsBtn.click();
  });

  document.querySelectorAll('.js-details').forEach(bindDetailsHandler);
  function bindDetailsHandler(btn) {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      cdName.textContent = btn.dataset.name || ('#' + id);
      // Otwórz modal natychmiast + pokaż loader, zanim ruszy fetch
      const cdLoader = document.getElementById('cd-loader');
      cdLoader?.classList.remove('d-none');
      detailsModal?.show();
      try {
        const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'viewJson']) ?>/' + id, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        const c = data.contractor || {};
        const escHtml = (s) => String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
        const flagOf = (iso) => iso ? `<span class="fi fi-${escHtml(String(iso).toLowerCase())}" style="border-radius:2px"></span>` : '';

        // Avatar + typ
        const isPerson = Number(c.is_person) === 1 || (!c.name && (c.first_name || c.last_name));
        const avatar = document.getElementById('cd-avatar');
        avatar.classList.toggle('is-person', isPerson);
        avatar.innerHTML = `<i class="${isPerson ? 'ri-user-line' : 'ri-building-2-line'}"></i>`;
        const typeBadge = document.getElementById('cd-type-badge');
        if (typeBadge) {
          typeBadge.textContent = isPerson ? 'Osoba' : 'Firma';
          typeBadge.className = 'badge ms-1 ' + (isPerson ? 'bg-info-transparent' : 'bg-primary-transparent');
        }

        // Status pill
        const isActive = Number(c.is_active) === 1;
        const dot = document.querySelector('#cd-status-pill .status-dot');
        if (dot) {
          dot.classList.toggle('status-dot-active', isActive);
          dot.classList.toggle('status-dot-inactive', !isActive);
        }
        const statusText = document.getElementById('cd-status-text');
        if (statusText) statusText.textContent = isActive ? 'Aktywny' : 'Nieaktywny';

        // Identyfikator (kontekstowy: VAT-UE / NrID / NIP PL / PESEL)
        const vatPfx = String(c.vat_prefix || '');
        const hasEUVat = vatPfx && vatPfx !== 'NONE' && c.vat_eu;
        const hasNrID  = vatPfx === 'NONE' || (c.tax_id_other_country && c.tax_id_other);
        let identHtml = '<span class="text-muted">—</span>';
        if (!isPerson && hasEUVat) {
          const val = vatPfx + (c.vat_eu || '');
          identHtml = `
            <div class="d-flex align-items-center gap-2 mb-1">
              ${flagOf(vatPfx)} <small class="text-muted">VAT-UE</small>
            </div>
            <div class="d-flex align-items-center gap-2">
              <code>${escHtml(val)}</code>
              <button class="btn btn-link btn-sm p-0 copy-btn" data-copy="${escHtml(val)}" title="Kopiuj"><i class="ri-file-copy-line"></i></button>
            </div>
            ${c.eori ? `<div class="small text-muted mt-2">EORI: <code>${escHtml(c.eori)}</code></div>` : ''}
          `;
        } else if (!isPerson && hasNrID && c.tax_id_other) {
          identHtml = `
            <div class="d-flex align-items-center gap-2 mb-1">
              ${flagOf(c.tax_id_other_country)} <small class="text-muted">NrID${c.tax_id_other_country?(' ('+escHtml(String(c.tax_id_other_country).toUpperCase())+')'):''}</small>
            </div>
            <div class="d-flex align-items-center gap-2">
              <code>${escHtml(c.tax_id_other)}</code>
              <button class="btn btn-link btn-sm p-0 copy-btn" data-copy="${escHtml(c.tax_id_other)}" title="Kopiuj"><i class="ri-file-copy-line"></i></button>
            </div>
          `;
        } else if (c.nip) {
          identHtml = `
            <div class="d-flex align-items-center gap-2 mb-1">
              ${flagOf('pl')} <small class="text-muted">NIP (PL)</small>
            </div>
            <div class="d-flex align-items-center gap-2">
              <code>${escHtml(c.nip)}</code>
              <button class="btn btn-link btn-sm p-0 copy-btn" data-copy="${escHtml(c.nip)}" title="Kopiuj"><i class="ri-file-copy-line"></i></button>
            </div>
          `;
        } else if (c.pesel) {
          identHtml = `
            <div class="small text-muted mb-1">PESEL</div>
            <div class="d-flex align-items-center gap-2">
              <code>${escHtml(c.pesel)}</code>
              <button class="btn btn-link btn-sm p-0 copy-btn" data-copy="${escHtml(c.pesel)}" title="Kopiuj"><i class="ri-file-copy-line"></i></button>
            </div>
          `;
        }
        cdIdent.innerHTML = identHtml;

        // Kontakt
        const emailRow = document.getElementById('cd-email-row');
        if (c.email) {
          emailRow.classList.remove('d-none');
          cdEmail.innerHTML = `<a href="mailto:${escHtml(c.email)}">${escHtml(c.email)}</a> <button class="btn btn-link btn-sm p-0 copy-btn" data-copy="${escHtml(c.email)}" title="Kopiuj"><i class="ri-file-copy-line"></i></button>`;
        } else {
          emailRow.classList.add('d-none');
        }
        const phoneRow = document.getElementById('cd-phone-row');
        if (c.phone) {
          phoneRow.classList.remove('d-none');
          cdPhone.innerHTML = `${escHtml(c.phone)} <button class="btn btn-link btn-sm p-0 copy-btn" data-copy="${escHtml(c.phone)}" title="Kopiuj"><i class="ri-file-copy-line"></i></button>`;
        } else {
          phoneRow.classList.add('d-none');
        }
        if (!c.email && !c.phone) {
          emailRow.classList.remove('d-none');
          cdEmail.innerHTML = '<span class="text-muted">— brak danych kontaktowych —</span>';
        }

        // Adres + kraj z flagą
        const addrLine1 = (c.street || '').trim();
        const addrLine2 = `${(c.postal_code || '').trim()} ${(c.city || '').trim()}`.trim();
        const addrHtml = [addrLine1, addrLine2].filter(Boolean).join('<br>');
        cdAddr.innerHTML = addrHtml || '<span class="text-muted">—</span>';
        const cf = document.getElementById('cd-country-flag');
        if (cf) cf.innerHTML = c.country ? flagOf(c.country) : '';
        cdCountry.textContent = c.country ? String(c.country).toUpperCase() : '—';

        // Notatki (chowamy całą sekcję jeśli puste)
        const notesWrap = document.getElementById('cd-notes-wrap');
        if (c.notes && String(c.notes).trim() !== '') {
          notesWrap?.classList.remove('d-none');
          cdNotes.textContent = c.notes;
        } else {
          notesWrap?.classList.add('d-none');
          cdNotes.textContent = '';
        }
        // prepare load buttons
        const loadRecBtn = document.getElementById('cd-load-recipients');
        const recLoader  = document.getElementById('cd-recipients-loader');
        const recList    = document.getElementById('cd-recipients-list');
        const loadInvBtn = document.getElementById('cd-load-invoices');
        const invLoader  = document.getElementById('cd-invoices-loader');
        const invTBody   = document.getElementById('cd-invoices-tbody');
        const invUnsettled = document.getElementById('cd-inv-unsettled');
        // reset views
        if (recList) recList.innerHTML = '';
        if (invTBody) invTBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Brak danych</td></tr>';
        // bind recipients load
        loadRecBtn.onclick = async () => {
          recLoader?.classList.remove('d-none');
          try {
            const resR = await fetch('<?= $this->Url->build(['controller' => 'Recipients', 'action' => 'byContractor']) ?>/' + encodeURIComponent(id), { headers: { 'X-Requested-With':'XMLHttpRequest' }});
            const dR = await resR.json();
            const items = dR.recipients || [];
            if (!items.length) { recList.innerHTML = '<div class="text-muted small">Brak odbiorców.</div>'; return; }
            recList.innerHTML = items.map(r => `
              <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                <div>
                  <strong>${r.name || '(bez nazwy)'}</strong> ${r.is_active?'<span class="badge bg-success-transparent ms-1">Aktywny</span>':'<span class="badge bg-body-secondary ms-1">Nieaktywny</span>'}
                  <div class="small text-muted">${r.nip?('NIP: '+r.nip+' • '):''}${r.email || ''} ${r.phone?(' • '+r.phone):''}</div>
                </div>
                <div class="small">${[r.street, r.postal_code, r.city].filter(Boolean).join(', ')}</div>
              </div>`).join('');
          } catch {
            recList.innerHTML = '<div class="text-danger small">Błąd ładowania odbiorców.</div>';
          } finally {
            recLoader?.classList.add('d-none');
          }
        };
        // bind invoices load
        const money = (v) => (v===null||v===undefined)?'—':Number(v).toLocaleString('pl-PL',{minimumFractionDigits:2,maximumFractionDigits:2});
        const typeLabels = {
          vat: 'VAT', proforma: 'Proforma', advance: 'Zaliczka', final: 'Końcowa',
          correction: 'Korekta', currency: 'Walutowa', novat: 'Bez VAT',
          margin: 'Marża', rental: 'Najem', oss: 'OSS', internal: 'Wewnętrzna',
          internal_evidence: 'Ewidencja wewn.'
        };
        const renderInv = (list) => {
          if (!list.length) { invTBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Brak faktur dla tego kontrahenta.</td></tr>'; return; }
          invTBody.innerHTML = list.map(i => {
            const wf = String(i.workflow_status || 'issued').toLowerCase();
            const isDraft = wf === 'draft';
            const isError = wf === 'error';
            const paid = (i.paymentstate && String(i.paymentstate).toLowerCase() === 'paid') || Number(i.remaining || 0) <= 0;
            let statusHtml = '';
            if (isError) {
              statusHtml = '<span class="pill pill-error"><i class="ri-error-warning-line"></i>Błąd</span>';
            } else if (isDraft) {
              statusHtml = '<span class="pill pill-draft"><i class="ri-draft-line"></i>Szkic</span>';
            } else if (paid) {
              statusHtml = '<span class="pill pill-paid"><i class="ri-check-line"></i>Opłacona</span>';
            } else {
              statusHtml = '<span class="pill pill-open"><i class="ri-time-line"></i>Do zapłaty</span>';
            }
            const remainNum = Number(i.remaining || 0);
            const remainCls = remainNum > 0 ? 'text-remaining' : 'text-zero';
            const num = i.fullnumber || (isDraft ? '— (szkic)' : ('#' + (i.id || '')));
            const cur = i.currency || 'PLN';
            const typeKey = String(i.type || '').toLowerCase();
            const typeLabel = typeLabels[typeKey] || (typeKey ? typeKey.toUpperCase() : '');
            const typeTag = typeLabel ? `<span class="inv-type">${escHtml(typeLabel)}</span>` : '';
            const rowCls = isDraft ? 'inv-draft' : '';
            return `<tr class="${rowCls}">
              <td>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                  <a href="<?= $this->Url->build(['controller' => 'Invoices', 'action' => 'view']) ?>/${escHtml(i.id)}" class="inv-num">${escHtml(num)}</a>
                  ${typeTag}
                </div>
              </td>
              <td><span class="text-muted">${i.date ? (new Date(i.date)).toLocaleDateString('pl-PL') : '—'}</span></td>
              <td class="text-end"><strong>${money(i.total)}</strong> <small class="text-muted">${escHtml(cur)}</small></td>
              <td class="text-end ${remainCls}">${money(i.remaining)}</td>
              <td>${statusHtml}</td>
            </tr>`;
          }).join('');
        };
        const loadInvoices = async () => {
          invLoader?.classList.remove('d-none');
          const url = new URL('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'invoices']) ?>/' + id, window.location.origin);
          if (invUnsettled?.checked) url.searchParams.set('unsettled', '1');
          try {
            const resI = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
            const dI = await resI.json();
            renderInv(dI.invoices || []);
          } catch {
            invTBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Błąd ładowania.</td></tr>';
          } finally {
            invLoader?.classList.add('d-none');
          }
        };
        loadInvBtn.onclick = loadInvoices;
        // UWAGA: używamy `onchange` (replaces previous) zamiast `addEventListener`,
        // bo bindDetailsHandler odpala się przy KAŻDYM otwarciu modala — addEventListener
        // by się kumulowało i przy 1 kliknięciu checkboxa loadInvoices leciałby N-razy.
        if (invUnsettled) invUnsettled.onchange = loadInvoices;

        // === Statystyki (lazy-load przy pierwszym otwarciu taba) ===
        const statsTabBtn = document.getElementById('cd-tab-stats-btn');
        const statsLoader = document.getElementById('cd-stats-loader');
        const statsBody   = document.getElementById('cd-stats-body');
        let statsLoaded = false;
        const fmtMoney = (n, cur='PLN') => Number(n||0).toLocaleString('pl-PL',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' ' + cur;
        const renderStats = (stats) => {
          if (!stats) return '';
          // Mini bar chart dla 12 miesięcy (CSS-only)
          const maxMonth = Math.max(...stats.byMonth.map(m => m.total), 1);
          const barsHtml = stats.byMonth.map(m => {
            const h = Math.max(2, Math.round((m.total / maxMonth) * 100));
            return `<div class="stats-bar-wrap" title="${escHtml(m.month)}: ${fmtMoney(m.total)} (${m.count} szt.)">
                      <div class="stats-bar" style="height:${h}%"></div>
                      <div class="stats-bar-label">${escHtml(m.month.slice(5))}</div>
                    </div>`;
          }).join('');
          const topHtml = stats.topProducts.length
            ? stats.topProducts.map((p, i) => `<li class="d-flex justify-content-between"><span><strong>${i+1}.</strong> ${escHtml(p.name)}</span><span class="text-muted">${fmtMoney(p.revenue)}</span></li>`).join('')
            : '<li class="text-muted">Brak danych — kontrahent jeszcze nie ma faktur z pozycjami.</li>';
          const avgDaysTxt = stats.avgPaymentDays !== null && stats.avgPaymentDays !== undefined
            ? `${stats.avgPaymentDays} dni`
            : '<span class="text-muted">—</span>';
          return `
            <div class="row g-3 mb-3">
              <div class="col-md-3">
                <div class="cd-stat-card">
                  <div class="cd-stat-label">Liczba faktur</div>
                  <div class="cd-stat-value">${stats.totalInvoices}</div>
                  <div class="cd-stat-sub">${stats.ytdCount} w bieżącym roku</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="cd-stat-card">
                  <div class="cd-stat-label">Łączna wartość</div>
                  <div class="cd-stat-value">${fmtMoney(stats.totalAmount)}</div>
                  <div class="cd-stat-sub">YTD: ${fmtMoney(stats.ytdAmount)}</div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="cd-stat-card">
                  <div class="cd-stat-label">Opłacone</div>
                  <div class="cd-stat-value text-success">${fmtMoney(stats.totalPaid)}</div>
                  <div class="cd-stat-sub">Do zapłaty: <span class="text-danger">${fmtMoney(stats.totalRemaining)}</span></div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="cd-stat-card">
                  <div class="cd-stat-label">Średni czas płatności</div>
                  <div class="cd-stat-value">${avgDaysTxt}</div>
                  <div class="cd-stat-sub">na podstawie opłaconych</div>
                </div>
              </div>
            </div>
            <div class="cd-card mb-3">
              <div class="cd-card-header"><i class="ri-bar-chart-2-line"></i>Sprzedaż po miesiącach (12 mies.)</div>
              <div class="cd-card-body">
                <div class="stats-bars">${barsHtml || '<span class="text-muted">Brak danych</span>'}</div>
              </div>
            </div>
            <div class="cd-card">
              <div class="cd-card-header"><i class="ri-trophy-line"></i>Top 3 produkty / usługi</div>
              <div class="cd-card-body">
                <ul class="list-unstyled mb-0">${topHtml}</ul>
              </div>
            </div>
          `;
        };
        const loadStats = async () => {
          if (statsLoaded) return;
          statsLoader?.classList.remove('d-none');
          statsBody?.classList.add('d-none');
          try {
            const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'stats']) ?>/' + encodeURIComponent(id), { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
            const data = await res.json();
            if (data.success && data.stats) {
              statsBody.innerHTML = renderStats(data.stats);
              statsBody.classList.remove('d-none');
              statsLoaded = true;
            } else {
              statsBody.innerHTML = '<div class="text-muted text-center py-3">Brak statystyk.</div>';
              statsBody.classList.remove('d-none');
            }
          } catch {
            statsBody.innerHTML = '<div class="text-danger text-center py-3">Błąd ładowania statystyk.</div>';
            statsBody.classList.remove('d-none');
          } finally {
            statsLoader?.classList.add('d-none');
          }
        };
        statsTabBtn?.addEventListener('shown.bs.tab', loadStats);

        // auto-load related data on open
        try { loadRecBtn?.click(); } catch {}
        try { await loadInvoices(); } catch {}
      } catch {
        toastBody.textContent = 'Nie udało się wczytać szczegółów.'; toast.show();
      } finally {
        cdLoader?.classList.add('d-none');
      }
    });
  }

  // === Modal: Lista odbiorców ===
  const recipientsModalEl = document.getElementById('recipientsModal');
  const recipientsModal   = recipientsModalEl ? new bootstrap.Modal(recipientsModalEl) : null;
  let currentRecipientsContractorId = null;

  function renderRecipientsList(items){
    const body = document.getElementById('recipientsModalBody');
    if (!body) return;
    if (!items.length) { body.innerHTML = '<div class="text-muted">Brak odbiorców dla tego kontrahenta.</div>'; return; }
    body.innerHTML = items.map(r => `
      <div class=\"d-flex justify-content-between align-items-center border-bottom py-2\">
        <div>
          <div><strong>${r.name || '(bez nazwy)'}</strong> ${r.is_active ? '<span class=\"badge bg-success ms-2\">Aktywny</span>' : '<span class=\"badge bg-secondary ms-2\">Nieaktywny</span>'}</div>
          <div class=\"small text-muted\">${r.nip ? 'NIP: '+r.nip+' • ' : ''}${r.email || ''} ${r.phone ? ' • '+r.phone : ''}</div>
          <div class=\"small\">${[r.street, r.postal_code, r.city].filter(Boolean).join(', ')}</div>
        </div>
        <div class=\"btn-group btn-group-sm\">
          <button type=\"button\" class=\"btn btn-outline-primary\" data-rec-id=\"${r.id}\" data-action=\"edit\"><i class=\"ri-edit-2-line\"></i></button>
          <button type=\"button\" class=\"btn btn-outline-danger\" data-rec-id=\"${r.id}\" data-action=\"delete\"><i class=\"ri-delete-bin-6-line\"></i></button>
        </div>
      </div>
    `).join('');
    body.querySelectorAll('[data-action="edit"]').forEach(btn => btn.addEventListener('click', () => openRecipientForm('edit', btn.getAttribute('data-rec-id'))));
    body.querySelectorAll('[data-action="delete"]').forEach(btn => btn.addEventListener('click', () => deleteRecipient(btn.getAttribute('data-rec-id'))));
  }

  document.querySelectorAll('.js-recipients').forEach(bindRecipientsHandler);
  function bindRecipientsHandler(btn) {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', async () => {
      currentRecipientsContractorId = btn.dataset.id;
      const nameEl = document.getElementById('recipientsModalContractor');
      if (nameEl) nameEl.textContent = btn.dataset.name || ('#' + currentRecipientsContractorId);
      try {
        const body = document.getElementById('recipientsModalBody');
        if (body) body.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Ładowanie...</div>';
        recipientsModal?.show();
        const res = await fetch('<?= $this->Url->build(['controller' => 'Recipients', 'action' => 'byContractor']) ?>/' + encodeURIComponent(currentRecipientsContractorId), {
          method: 'GET', headers: { 'X-Requested-With':'XMLHttpRequest' }
        });
        const data = await res.json();
        renderRecipientsList(data.recipients || []);
      } catch {
        const body = document.getElementById('recipientsModalBody');
        if (body) body.innerHTML = '<div class="text-danger">Nie udało się pobrać odbiorców.</div>';
      }
    });
  }

  // === Modal: Formularz odbiorcy (dodaj/edytuj) ===
  const recipientFormModalEl = document.getElementById('recipientFormModal');
  const recipientFormModal   = recipientFormModalEl ? new bootstrap.Modal(recipientFormModalEl) : null;
  document.getElementById('recipientsModalAdd')?.addEventListener('click', () => openRecipientForm('add'));

  async function openRecipientForm(mode, recId){
    const title = document.getElementById('recipientFormTitle');
    const form  = document.getElementById('recipientForm');
    if (!form) return;
    form.reset();
    form.dataset.mode = mode;
    form.dataset.recId = recId || '';
    title.textContent = mode === 'add' ? 'Dodaj odbiorcę' : 'Edytuj odbiorcę';
    const rcNameEl = document.getElementById('recipientFormContractor');
    const headerName = document.querySelector(`.js-recipients[data-id="${currentRecipientsContractorId}"]`)?.dataset.name;
    if (rcNameEl) rcNameEl.textContent = headerName || ('#' + (currentRecipientsContractorId||''));
    // Telefon odbiorcy — pole bez flagi, dowolny format.
    if (window.itiRecipientFormPhone && typeof window.itiRecipientFormPhone.destroy === 'function') {
      try { window.itiRecipientFormPhone.destroy(); } catch {}
    }
    window.itiRecipientFormPhone = null;

    // NIP live validation for recipient form modal
    try {
      const rNip = document.getElementById('recipientFormNip');
      const rGusBtn = document.getElementById('recipientFormGusFetch');
      const rGusSpin= document.getElementById('recipientFormGusSpin');
      if (rNip) {
        rNip.addEventListener('input', () => {
          const v = (rNip.value || '').replace(/\D+/g,'');
          rNip.value = v;
          clearInvalid(rNip);
          if (v.length === 10 && !validateNIP(v)) {
            setInvalid(rNip, 'Nieprawidłowy NIP odbiorcy — sprawdź sumę kontrolną.');
          }
        });
        rNip.addEventListener('blur', () => {
          const v = (rNip.value || '').replace(/\D+/g,'');
          if (v && !validateNIP(v)) setInvalid(rNip, 'Nieprawidłowy NIP odbiorcy — 10 cyfr i poprawna kontrola.');
        });
      }
      // GUS fetch for recipient in modal
      if (rGusBtn) {
        rGusBtn.onclick = async () => {
          const nip = (rNip?.value || '').replace(/\D+/g,'');
          if (!nip || nip.length !== 10) {
            toastBody.textContent = 'Podaj prawidłowy NIP odbiorcy (10 cyfr).';
            toast.show();
            return;
          }
          try {
            rGusBtn.disabled = true; rGusSpin?.classList.remove('d-none');
            const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'gusLookup']) ?>', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN},
              body: JSON.stringify({ nip })
            });
            const data = await res.json();
            if (data.success) {
              const c = data.contractor || {};
              form.elements['name'].value        = c.name || form.elements['name'].value;
              form.elements['city'].value        = c.city || form.elements['city'].value;
              form.elements['postal_code'].value = c.zip || form.elements['postal_code'].value;
              form.elements['street'].value      = c.street || form.elements['street'].value;
              toastBody.textContent = 'Pobrano dane odbiorcy z GUS.';
              toast.show();
            } else {
              toastBody.textContent = data.message || 'Brak danych w GUS dla NIP odbiorcy.';
              toast.show();
            }
          } catch (err) {
            toastBody.textContent = 'Błąd pobierania z GUS dla odbiorcy.';
            toast.show();
          } finally {
            rGusBtn.disabled = false; rGusSpin?.classList.add('d-none');
          }
        };
      }
    } catch {}
    if (mode === 'edit' && recId) {
      try {
        const res = await fetch('<?= $this->Url->build(['controller' => 'Recipients', 'action' => 'view']) ?>/' + encodeURIComponent(recId), { headers: { 'X-Requested-With':'XMLHttpRequest' }});
        const d = await res.json();
        const r = d.recipient || {};
        form.elements['name'].value = r.name || '';
        form.elements['email'].value = r.email || '';
        form.elements['nip'].value = r.nip || '';
        form.elements['phone'].value = r.phone || '';
        // set checkbox is_active state
        const ia = form.querySelector('input[name="is_active"]');
        if (ia) ia.checked = Number(r.is_active) !== 0;
        form.elements['city'].value = r.city || '';
        form.elements['street'].value = r.street || '';
        form.elements['postal_code'].value = r.postal_code || '';
      } catch {}
    }
    recipientFormModal?.show();
  }

  document.getElementById('recipientFormSave')?.addEventListener('click', async () => {
    const form = document.getElementById('recipientForm');
    if (!form) return;
    const mode = form.dataset.mode;
    const recId= form.dataset.recId;
    const fd = new FormData(form);
    fd.set('contractor_id', currentRecipientsContractorId || '');
    // normalize phone to E.164 using intl-tel-input if present
    try {
      const rPhone = document.getElementById('recipientFormPhone');
      if (window.itiRecipientFormPhone && rPhone) {
        const e164 = window.itiRecipientFormPhone.getNumber();
        if (e164) fd.set('phone', e164);
      }
    } catch {}
    // normalize NIP digits only
    try {
      const rNip = document.getElementById('recipientFormNip');
      if (rNip) fd.set('nip', (rNip.value || '').replace(/\D+/g,''));
    } catch {}
    const url = mode === 'add'
      ? '<?= $this->Url->build(['controller' => 'Recipients', 'action' => 'add']) ?>'
      : '<?= $this->Url->build(['controller' => 'Recipients', 'action' => 'edit']) ?>/' + encodeURIComponent(recId);
    try {
      const res = await fetch(url, { method: 'POST', headers: { 'X-Requested-With':'XMLHttpRequest','X-CSRF-Token': CSRF_TOKEN }, body: fd });
      const data = await res.json();
      if (data.success) {
        recipientFormModal?.hide();
        // odśwież listę
        const res2 = await fetch('<?= $this->Url->build(['controller' => 'Recipients', 'action' => 'byContractor']) ?>/' + encodeURIComponent(currentRecipientsContractorId), { headers: { 'X-Requested-With':'XMLHttpRequest' }});
        const data2 = await res2.json();
        renderRecipientsList(data2.recipients || []);
      } else {
        // highlight invalid fields
        try {
          // clear existing
          form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
          form.querySelectorAll('.invalid-feedback').forEach(i => i.remove());
          if (data.errors) {
            for (const [field, msgs] of Object.entries(data.errors)) {
              const input = form.querySelector(`[name="${field}"]`);
              if (input) {
                input.classList.add('is-invalid');
                let fb = input.nextElementSibling;
                if (!fb || !fb.classList.contains('invalid-feedback')) {
                  fb = document.createElement('div');
                  fb.className = 'invalid-feedback';
                  input.insertAdjacentElement('afterend', fb);
                }
                fb.textContent = Object.values(msgs).flat().join(', ');
              }
            }
          }
        } catch {}
        const msg = data.message || 'Błąd zapisu odbiorcy';
        toastBody.textContent = msg; toast.show();
      }
    } catch (e) {
      toastBody.textContent = 'Nie udało się zapisać odbiorcy.'; toast.show();
    }
  });

  async function deleteRecipient(recId){
    try {
      const result = await Swal.fire({
        title: 'Usunąć odbiorcę?',
        text: 'Tej operacji nie można cofnąć.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Usuń',
        cancelButtonText: 'Anuluj'
      });
      if (!result.isConfirmed) return;
      const res = await fetch('<?= $this->Url->build(['controller' => 'Recipients', 'action' => 'delete']) ?>/' + encodeURIComponent(recId), {
        method: 'POST', headers: { 'X-Requested-With':'XMLHttpRequest','X-CSRF-Token': CSRF_TOKEN }
      });
      const data = await res.json();
      if (data.success) {
        await Swal.fire({ title: 'Usunięto', icon: 'success', timer: 900, showConfirmButton: false });
        const res2 = await fetch('<?= $this->Url->build(['controller' => 'Recipients', 'action' => 'byContractor']) ?>/' + encodeURIComponent(currentRecipientsContractorId), { headers: { 'X-Requested-With':'XMLHttpRequest' }});
        const data2 = await res2.json();
        renderRecipientsList(data2.recipients || []);
      } else {
        await Swal.fire({ title: 'Błąd', text: data.message || 'Nie udało się usunąć odbiorcy.', icon: 'error' });
      }
    } catch (e) {
      await Swal.fire({ title: 'Błąd', text: 'Błąd usuwania odbiorcy.', icon: 'error' });
    }
  }

  // submit formularza – AJAX (add/edit według data-mode)
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    // UWAGA: NIE robimy `checkValidity` jako wczesnego return — wszystkie sprawdzenia
    // (HTML5 required, chip-specific, format e-mail/tel) zbieramy w jednym bloku niżej.

    // Custom NIP/PESEL compliance checks
    const mode = modalEl.dataset.mode || 'add';
    const peselMode = !!usePesel?.checked;
    if (peselMode) {
      // Zbieramy problemy w listę; jeśli niepuste → SweetAlert
      const personErrors = [];

      // Imię i nazwisko — wymagane dla osoby
      const firstVal = (form.querySelector('input[name="first_name"]')?.value || '').trim();
      const lastVal  = (form.querySelector('input[name="last_name"]')?.value  || '').trim();
      if (!firstVal) personErrors.push('Imię jest wymagane.');
      if (!lastVal)  personErrors.push('Nazwisko jest wymagane.');

      // PESEL: opcjonalny, ale jeśli podany musi być poprawny (suma kontrolna 11 cyfr).
      // Jeśli user kliknął w pole (touched) i wyszedł pusty → też zasygnalizuj.
      const peselVal = onlyDigits(peselInput?.value || '');
      clearInvalid(peselInput);
      if (peselVal && !validatePESEL(peselVal)) {
        personErrors.push('Nieprawidłowy PESEL — wymagane 11 cyfr i poprawna cyfra kontrolna.');
      } else if (!peselVal && (peselInput?.dataset?.touched === '1' || showPesel?.checked)) {
        personErrors.push('PESEL został wybrany ale niewypełniony — wpisz numer lub odznacz „Podaj PESEL".');
      }

      // NIP osoby: opcjonalny; jeśli podany — checksum.
      // Jeśli kliknięte/odsłonięte ale puste → zasygnalizuj.
      const personNipVal = onlyDigits(personNipInput?.value || '');
      clearInvalid(personNipInput);
      if (personNipVal && !validateNIP(personNipVal)) {
        personErrors.push('Nieprawidłowy NIP — wymagane 10 cyfr i poprawna suma kontrolna.');
      } else if (!personNipVal && (personNipInput?.dataset?.touched === '1' || showNip?.checked)) {
        personErrors.push('NIP został wybrany ale niewypełniony — wpisz numer lub odznacz „Podaj NIP".');
      }

      // Email: opcjonalny, ale jeśli podany musi być prawidłowym adresem
      const emailVal = (form.querySelector('input[name="email"]')?.value || '').trim();
      if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(emailVal)) {
        personErrors.push('Nieprawidłowy adres e-mail.');
      }

      // Telefon: opcjonalny; loose international (cyfry, spacje, +, -, nawiasy; min 7 cyfr)
      const phoneVal = (phoneInput?.value || '').trim();
      if (phoneVal) {
        const phoneDigits = phoneVal.replace(/\D+/g, '');
        const phoneChars  = /^[\d\s+\-().]+$/.test(phoneVal);
        if (!phoneChars || phoneDigits.length < 7 || phoneDigits.length > 15) {
          personErrors.push('Nieprawidłowy numer telefonu (akceptowane cyfry, +, -, spacje, nawiasy; 7–15 cyfr).');
        }
      }

      // Adres — wymagany dla osoby (każdy kraj)
      const pCountryVal = (countryHidden?.value || '').trim();
      const pCityVal    = (cityInput?.value    || '').trim();
      const pStreetVal  = (streetInput?.value  || '').trim();
      const pPostalVal  = (postalInput?.value  || '').trim();
      if (!pCountryVal) personErrors.push('Wybierz kraj.');
      if (!pCityVal)    personErrors.push('Podaj miejscowość.');
      if (!pStreetVal)  personErrors.push('Podaj ulicę i numer.');
      if (!pPostalVal)  personErrors.push('Podaj kod pocztowy.');

      if (personErrors.length) {
        if (typeof Swal !== 'undefined') {
          const htmlList = '<ul class="text-start mb-0 ps-3">' + personErrors.map(m => '<li>' + m.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</li>').join('') + '</ul>';
          Swal.fire({
            icon: 'warning',
            title: 'Popraw dane osoby fizycznej',
            html: htmlList,
            confirmButtonColor: '#94d437',
            confirmButtonText: 'OK'
          });
        } else {
          toastBody.textContent = personErrors[0]; toast.show();
        }
        return;
      }
    } else {
      // === Walidacja kontrahenta typu FIRMA ===
      const companyErrors = [];
      // Czytamy chip-picker z DWÓCH źródeł żeby uniknąć rozjazdu: aktywny przycisk + ukryty input.
      // Aktywny przycisk ma priorytet (DOM truth) — hidden bywa rozsynchronizowany przez plugin.
      const activeChipBtn = document.querySelector('#id-type-chips button[data-id-type].active');
      const currIdType = activeChipBtn?.dataset?.idType
                       || idTypeHidden?.value
                       || 'nip_pl';

      // Nazwa firmy — wymagana
      const nameVal = (form.querySelector('input[name="name"]')?.value || '').trim();
      if (!nameVal) {
        companyErrors.push('Nazwa firmy jest wymagana.');
      }

      // Email — opcjonalny, walidacja formatu jeśli podany
      const emailVal = (form.querySelector('input[name="email"]')?.value || '').trim();
      if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(emailVal)) {
        companyErrors.push('Nieprawidłowy adres e-mail.');
      }

      // Telefon — opcjonalny, walidacja luźna international-friendly
      const phoneVal = (phoneInput?.value || '').trim();
      if (phoneVal) {
        const phoneDigits = phoneVal.replace(/\D+/g, '');
        const phoneChars  = /^[\d\s+\-().]+$/.test(phoneVal);
        if (!phoneChars || phoneDigits.length < 7 || phoneDigits.length > 15) {
          companyErrors.push('Nieprawidłowy numer telefonu (akceptowane cyfry, +, -, spacje, nawiasy; 7–15 cyfr).');
        }
      }

      // Identyfikator — zależnie od wybranego chipa
      if (currIdType === 'nip_pl') {
        const nipVal = onlyDigits(nipInput?.value || '');
        if (!nipVal) {
          companyErrors.push('Wybrano „NIP PL" — podaj polski NIP (10 cyfr).');
        } else if (!validateNIP(nipVal)) {
          companyErrors.push('Nieprawidłowy NIP polski — sprawdź 10 cyfr i sumę kontrolną.');
        }
      } else if (currIdType === 'vat_eu') {
        // UWAGA: czytamy WIDOCZNY input vat-prefix-ui — plugin countrySelect bywa zafałszowany
        // (auto-wybór pierwszego kraju z onlyCountries gdy defaultCountry: ''). Widoczne pole .value
        // jest puste dopóki user fizycznie nie wybierze kraju z dropdownu.
        const vatPfxUIVal = (vatPfxUI?.value || '').trim();
        const vatPfxVal   = (vatPfxHidden?.value || '').trim();
        const vatEuVal    = (modalVatEu?.value || '').trim();
        if (!vatPfxUIVal || !vatPfxVal || vatPfxVal === 'NONE') {
          companyErrors.push('Wybrano „VAT UE" — wybierz Prefiks UE (kraj).');
        }
        if (!vatEuVal) {
          companyErrors.push('Wybrano „VAT UE" — podaj Numer VAT-UE.');
        }
        // EORI opcjonalny
      } else if (currIdType === 'non_eu') {
        // Tu też: weryfikuj widoczny input dla kraju NrID (tax-id-other-country-ui)
        const taxIdCountryUI = (modalTaxOtherCUI?.value || '').trim();
        const taxIdCountry   = (modalTaxOtherC?.value || '').trim();
        const taxIdValue     = (modalTaxOther?.value || '').trim();
        if (!taxIdCountryUI || !taxIdCountry) {
          companyErrors.push('Wybrano „Spoza UE" — wybierz Kraj NrID.');
        }
        if (!taxIdValue) {
          companyErrors.push('Wybrano „Spoza UE" — podaj Identyfikator podatkowy.');
        }
      }

      // Adres — wymagany dla firmy (każdy kraj)
      const cityVal   = (cityInput?.value   || '').trim();
      const streetVal = (streetInput?.value || '').trim();
      const postalVal = (postalInput?.value || '').trim();
      const countryVal= (countryHidden?.value || '').trim();
      if (!countryVal) companyErrors.push('Wybierz kraj kontrahenta.');
      if (!cityVal)    companyErrors.push('Podaj miejscowość.');
      if (!streetVal)  companyErrors.push('Podaj ulicę i numer.');
      if (!postalVal)  companyErrors.push('Podaj kod pocztowy.');

      if (companyErrors.length) {
        if (typeof Swal !== 'undefined') {
          const htmlList = '<ul class="text-start mb-0 ps-3">' + companyErrors.map(m => '<li>' + m.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</li>').join('') + '</ul>';
          Swal.fire({
            icon: 'warning',
            title: 'Popraw dane firmy',
            html: htmlList,
            confirmButtonColor: '#94d437',
            confirmButtonText: 'OK'
          });
        } else {
          toastBody.textContent = companyErrors[0]; toast.show();
        }
        return;
      }
    }
    const action = form.getAttribute('action');
    // Wymuś sync ukrytego vat_prefix z aktualnym wyborem countrySelect — gdyby 'change' się nie odpalił
    try {
      if (window.jQuery && jQuery.fn.countrySelect && vatPfxUI && vatPfxHidden) {
        const d = jQuery(vatPfxUI).countrySelect('getSelectedCountryData');
        if (d && d.iso2) vatPfxHidden.value = d.iso2.toUpperCase();
      }
      if (window.jQuery && jQuery.fn.countrySelect && modalTaxOtherCUI && modalTaxOtherC) {
        const dc = jQuery(modalTaxOtherCUI).countrySelect('getSelectedCountryData');
        if (dc && dc.iso2) modalTaxOtherC.value = dc.iso2.toUpperCase();
      }
    } catch {}

    const fd = new FormData(form);
    // Czyszczenie pól zależnie od wybranego wariantu identyfikatora — wymuś spójność danych
    const idType = idTypeHidden?.value || 'nip_pl';
    if (idType === 'nip_pl') {
      fd.set('vat_prefix', '');
      fd.set('vat_eu', '');
      fd.set('eori', '');
      fd.set('tax_id_other', '');
      fd.set('tax_id_other_country', '');
    } else if (idType === 'vat_eu') {
      fd.set('nip', '');
      fd.set('tax_id_other', '');
      fd.set('tax_id_other_country', '');
      // jeszcze raz wymusza vat_prefix z hidden (gdyby FormData złapała stary stan)
      if (vatPfxHidden?.value) fd.set('vat_prefix', vatPfxHidden.value);
    } else if (idType === 'non_eu') {
      fd.set('nip', '');
      fd.set('vat_prefix', 'NONE');
      fd.set('vat_eu', '');
      fd.set('eori', '');
    }
    // normalize digits-only values
    if (nipInput) fd.set('nip', (nipInput.value || '').trim());
    if (peselInput) fd.set('pesel', onlyDigits(peselInput.value || ''));
    // normalize phone to E.164
    if (iti && phoneInput) {
      const e164 = iti.getNumber();
      if (e164) fd.set('phone', e164);
    }
    // include person NIP in payload for person mode
    if (usePesel?.checked && personNipInput) {
      const vn = (personNipInput.value || '').trim();
      if (vn) fd.set('nip', vn);
    }

    try {
      // If adding recipient, validate recipient NIP client-side first (strict checksum)
      if (addRecipientToggle?.checked && !usePesel?.checked) {
        const rn = (recipientNip?.value || '').replace(/\D+/g,'');
        clearInvalid(recipientNip);
        if (rn && !validateNIP(rn)) {
          setInvalid(recipientNip, 'Nieprawidłowy NIP odbiorcy — sprawdź 10 cyfr i sumę kontrolną.');
          toastBody.textContent = 'Nieprawidłowy NIP odbiorcy.'; toast.show();
          return;
        }
      }

      // Pokaż przejściowy SweetAlert "Zapisywanie…" — ze spinnerem, bez przycisku zamykania
      let savingSwalOpen = false;
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: mode === 'add' ? 'Zapisuję kontrahenta…' : 'Zapisuję zmiany…',
          html: '<div class="text-muted small">Sprawdzam poprawność danych i zapisuję na serwerze.</div>',
          allowOutsideClick: false,
          allowEscapeKey: false,
          showConfirmButton: false,
          didOpen: () => Swal.showLoading()
        });
        savingSwalOpen = true;
      }

      const res = await fetch(action, {
        method: 'POST', // _method=PUT dla edycji
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      });
      const data = await res.json();

      // Zamknij overlay zapisu
      if (savingSwalOpen && typeof Swal !== 'undefined') Swal.close();

      // wyczyść stare błędy
      form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
      form.querySelectorAll('.invalid-feedback').forEach(i => i.remove());

      if (data.success) {
                // create recipient if requested and company mode
                if (addRecipientToggle?.checked && !usePesel?.checked) {
                  try {
                    const rfd = new FormData();
                    rfd.set('contractor_id', String(data.contractor?.id || idField.value));
                    rfd.set('name', recipientName?.value || '');
                    rfd.set('nip', (recipientNip?.value || '').replace(/\D+/g,''));
                    rfd.set('email', recipientEmail?.value || '');
                    // normalize recipient phone to E.164 if possible
                    if (rti && recipientPhone) {
                      const e164r = rti.getNumber();
                      if (e164r) rfd.set('phone', e164r);
                      else rfd.set('phone', recipientPhone?.value || '');
                    } else {
                      rfd.set('phone', recipientPhone?.value || '');
                    }
                    rfd.set('city', recipientCity?.value || '');
                    rfd.set('street', recipientStreet?.value || '');
                    rfd.set('postal_code', recipientPostal?.value || '');
                    const rRes = await fetch('<?= $this->Url->build(['controller' => 'Recipients', 'action' => 'add']) ?>', {
                      method: 'POST',
                      headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
                      body: rfd
                    });
                    try {
                      const rData = await rRes.json();
                      if (!rData.success) {
                        const msg = rData.errors
                          ? Object.values(rData.errors).map(e => Object.values(e).join(', ')).join(' | ')
                          : (rData.message || 'Nie udało się zapisać odbiorcy.');
                        console.warn('Recipient save failed:', rData);
                        toastBody.textContent = msg;
                        toast.show();
                      }
                    } catch (e) {
                      console.warn('Recipient save response parse error', e);
                    }
                  } catch {}
                }
        showToast(mode === 'add' ? 'Dodano kontrahenta.' : 'Zapisano zmiany.', 'success');
        bootstrap.Modal.getInstance(modalEl)?.hide();

        // Po dodaniu LUB edycji — odśwież tabelę kontrahentów przez AJAX
        // (zachowujemy aktualny stan paginacji/filtrów z URL, bez przeładowania strony)
        try {
          await refreshContractorsTable();
        } catch {
          // fallback: jak coś pójdzie nie tak, przeładuj
          window.location.reload();
        }

      } else {
        // Czyścimy ewentualne stare podświetlenia (legacy)
        form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(i => i.remove());

        // Buduj czytelną listę błędów dla SweetAlert
        const errorMessages = [];
        if (data.errors && typeof data.errors === 'object') {
          for (const msgs of Object.values(data.errors)) {
            // msgs to obiekt typu { ruleName: 'tekst błędu' }
            for (const msg of Object.values(msgs)) {
              if (msg) errorMessages.push(String(msg));
            }
          }
        }

        if (typeof Swal !== 'undefined') {
          const htmlList = errorMessages.length
            ? '<ul class="text-start mb-0 ps-3">' + errorMessages.map(m => '<li>' + (m.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')) + '</li>').join('') + '</ul>'
            : '<div class="text-muted">' + (data.message || 'Sprawdź dane formularza.') + '</div>';
          Swal.fire({
            icon: 'error',
            title: 'Nie udało się zapisać kontrahenta',
            html: htmlList,
            confirmButtonColor: '#94d437',
            confirmButtonText: 'Rozumiem'
          });
        } else {
          toastBody.textContent = (data.message || 'Błąd zapisu') + (errorMessages.length ? ' — ' + errorMessages.join('; ') : '');
          toast.show();
        }
      }
    } catch (err) {
      if (typeof Swal !== 'undefined') {
        // jeśli overlay "Zapisuję…" jeszcze otwarty — zostanie zastąpiony tym alertem
        Swal.fire({ icon: 'error', title: 'Błąd połączenia', text: 'Nie udało się połączyć z serwerem.', confirmButtonColor: '#94d437' });
      } else {
        showToast('Błąd połączenia z serwerem.', 'danger');
      }
    }
  });

  // GUS fetch
  const btnGus = document.getElementById('gus-fetch');
  const spin   = document.getElementById('gus-spin');
  btnGus?.addEventListener('click', async () => {
    const nipInput = form.querySelector('input[name="nip"]');
    const nip = (nipInput?.value || '').replace(/\D+/g,'');
    if (!nip || nip.length !== 10) {
      toastBody.textContent = 'Podaj prawidłowy NIP (10 cyfr).';
      toast.show();
      return;
    }
    try {
      btnGus.disabled = true; spin.classList.remove('d-none');
      const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'gusLookup']) ?>', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN},
        body: JSON.stringify({ nip })
      });
      const data = await res.json();
      if (data.success) {
        const c = data.contractor || {};
        setVal('input[name="name"]',        c.name);
        setVal('input[name="city"]',        c.city);
        setVal('input[name="postal_code"]', c.zip);
        setVal('input[name="street"]',      c.street);
        // Country: oprócz ukrytego inputa trzeba zsynchronizować widoczne UI countrySelect
        const isoCountry = (c.country || 'PL').toUpperCase();
        if (countryHidden) countryHidden.value = isoCountry;
        setCountryWithName(countryUI, isoCountry);
        try { applyCountryRequirements(); } catch {}
        toastBody.textContent = 'Pobrano dane z GUS.';
        toast.show();
      } else {
        toastBody.textContent = data.message || 'Brak danych w GUS.';
        toast.show();
      }
    } catch (err) {
      toastBody.textContent = 'Błąd pobierania z GUS.';
      toast.show();
    } finally {
      btnGus.disabled = false; spin.classList.add('d-none');
    }
  });

    // --- Eksport CSV z modalem postępu ---
  const exportBtn   = document.getElementById('export-csv-btn');
  const exportModalEl = document.getElementById('exportProgressModal');
  const exportModal = exportModalEl ? new bootstrap.Modal(exportModalEl) : null;
  const exportBar   = document.getElementById('export-progress');
  const exportPct   = document.getElementById('export-percent');
  const exportStat  = document.getElementById('export-status');
  const exportCancel= document.getElementById('export-cancel-btn');

  let exportAbortCtrl = null;

  function setProgress(pct, label) {
    const v = Math.max(0, Math.min(100, Math.round(pct)));
    exportBar.style.width = v + '%';
    exportBar.setAttribute('aria-valuenow', v);
    exportPct.textContent = v + '%';
    if (label) exportStat.textContent = label;
  }

  function resetExportUI() {
    setProgress(0, 'Przygotowywanie…');
    exportBar.classList.add('progress-bar-animated');
    exportCancel.disabled = false;
  }

  function parseFilename(contentDisposition) {
    if (!contentDisposition) return null;
    // szukamy filename*=UTF-8''nazwa.csv lub filename="nazwa.csv"
    const star = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(contentDisposition);
    if (star?.[1]) return decodeURIComponent(star[1].replace(/["']/g,''));
    const plain = /filename\s*=\s*"?([^";]+)"?/i.exec(contentDisposition);
    return plain?.[1] || null;
  }

  exportBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (!exportModal) { window.location.href = exportBtn.href; return; }

    resetExportUI();
    exportModal.show();

    exportAbortCtrl = new AbortController();
    const { signal } = exportAbortCtrl;

    try {
      exportStat.textContent = 'Łączenie z serwerem…';
      const res = await fetch(exportBtn.href, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal
      });

      if (!res.ok) throw new Error('Błąd pobierania (' + res.status + ')');

      const total = Number(res.headers.get('Content-Length')) || 0;
      const disp  = res.headers.get('Content-Disposition');
      const filename = parseFilename(disp) || 'export.csv';

      // Strumieniowe czytanie i liczenie postępu (jeśli znamy rozmiar)
      const reader = res.body.getReader();
      const chunks = [];
      let received = 0;

      exportStat.textContent = total ? 'Pobieranie pliku…' : 'Pobieranie (rozmiar nieznany)…';

      if (!total) {
        // indeterminate – zostaw animację, podbijaj % kosmetycznie do 90%
        let fake = 0;
        const t = setInterval(() => {
          if (fake < 90) { fake += 2; setProgress(fake); }
        }, 180);
        // czytaj do końca
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          chunks.push(value);
        }
        clearInterval(t);
        setProgress(95);
      } else {
        // znamy długość – licz prawdziwy postęp
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          chunks.push(value);
          received += value.length || value.byteLength || 0;
          setProgress((received / total) * 100);
        }
      }

      exportStat.textContent = 'Finalizowanie…';
      const blob = new Blob(chunks, { type: 'text/csv;charset=utf-8' });
      setProgress(100, 'Gotowe. Zapisuję plik…');

      // Zapis
      const url = URL.createObjectURL(blob);
      const a   = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);

      // Miły komunikat i domknięcie
      toastBody.textContent = 'Eksport zakończony: ' + filename;
      toast.show();

      // Nie zamykam agresywnie — pozwól użytkownikowi kliknąć „Ukryj”
      // ale można też auto-zamknąć po chwili:
      setTimeout(() => { try { exportModal.hide(); } catch {} }, 800);

    } catch (err) {
      if (signal.aborted) {
        exportStat.textContent = 'Anulowano.';
        setProgress(0);
        toastBody.textContent = 'Eksport anulowany.';
        toast.show();
      } else {
        exportStat.textContent = 'Wystąpił błąd.';
        exportBar.classList.remove('progress-bar-animated');
        toastBody.textContent = 'Błąd eksportu: ' + (err?.message || 'nieznany');
        toast.show();
      }
    } finally {
      exportCancel.disabled = true;
      exportAbortCtrl = null;
    }
  });

  exportCancel?.addEventListener('click', () => {
    if (exportAbortCtrl) {
      exportCancel.disabled = true;
      exportAbortCtrl.abort();
    }
  });
  // === Modal: Faktury kontrahenta ===
  const invModalEl = document.getElementById('contractor-invoices');
  const invModal   = invModalEl ? new bootstrap.Modal(invModalEl) : null;
  const invNameEl  = document.getElementById('ci-name');
  const invTBody   = document.getElementById('ci-tbody');
  const invCount   = document.getElementById('ci-count');
  const invUnsettled = document.getElementById('ci-unsettled');
  const invLoader  = document.getElementById('ci-loader');

  let currentContractorId = null;

  function money(v) {
    if (v === null || v === undefined) return '—';
    return Number(v).toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function badge(ok, textOk='OK', textNo='NIE') {
    return ok
      ? `<span class="badge bg-success-transparent">${textOk}</span>`
      : `<span class="badge bg-body-secondary text-muted">${textNo}</span>`;
  }

  const invTypeLabels = {
    vat: 'VAT', proforma: 'Proforma', advance: 'Zaliczka', final: 'Końcowa',
    correction: 'Korekta', currency: 'Walutowa', novat: 'Bez VAT',
    margin: 'Marża', rental: 'Najem', oss: 'OSS', internal: 'Wewnętrzna',
    internal_evidence: 'Ewidencja wewn.'
  };
  const escHtmlInv = (s) => String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  async function loadInvoices(unsettled) {
    if (!currentContractorId) return;
    invLoader.classList.remove('d-none');
    invTBody.innerHTML = '';
    invCount.textContent = '—';

    const url = new URL('<?= $this->Url->build(['controller'=>'Contractors','action'=>'invoices']) ?>/' + currentContractorId, window.location.origin);
    if (unsettled) url.searchParams.set('unsettled', '1');

    try {
      const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Błąd');

      const list = data.invoices || [];
      if (list.length === 0) {
        invTBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Brak faktur dla tego kontrahenta.</td></tr>';
        invCount.textContent = '0 pozycji';
        return;
      }

      const rows = list.map(i => {
        const wf = String(i.workflow_status || 'issued').toLowerCase();
        const isDraft = wf === 'draft';
        const isError = wf === 'error';
        const paid = (i.paymentstate && String(i.paymentstate).toLowerCase() === 'paid') || Number(i.remaining || 0) <= 0;
        let statusHtml = '';
        if (isError) {
          statusHtml = '<span class="pill pill-error"><i class="ri-error-warning-line"></i>Błąd</span>';
        } else if (isDraft) {
          statusHtml = '<span class="pill pill-draft"><i class="ri-draft-line"></i>Szkic</span>';
        } else if (paid) {
          statusHtml = '<span class="pill pill-paid"><i class="ri-check-line"></i>Opłacona</span>';
        } else {
          statusHtml = '<span class="pill pill-open"><i class="ri-time-line"></i>Do zapłaty</span>';
        }
        const remainNum = Number(i.remaining || 0);
        const remainCls = remainNum > 0 ? 'text-remaining' : 'text-zero';
        const num = i.fullnumber || (isDraft ? '— (szkic)' : ('#' + (i.id || '')));
        const cur = i.currency || 'PLN';
        const typeKey = String(i.type || '').toLowerCase();
        const typeLabel = invTypeLabels[typeKey] || (typeKey ? typeKey.toUpperCase() : '');
        const typeTag = typeLabel ? `<span class="inv-type">${escHtmlInv(typeLabel)}</span>` : '';
        const rowCls = isDraft ? 'inv-draft' : '';

        return `<tr class="${rowCls}">
          <td>
            <div class="d-flex align-items-center gap-1 flex-wrap">
              <a href="<?= $this->Url->build(['controller' => 'Invoices', 'action' => 'view']) ?>/${escHtmlInv(i.id)}" class="inv-num">${escHtmlInv(num)}</a>
              ${typeTag}
            </div>
          </td>
          <td><span class="text-muted">${i.date ? (new Date(i.date)).toLocaleDateString('pl-PL') : '—'}</span></td>
          <td class="text-end"><strong>${money(i.total)}</strong> <small class="text-muted">${escHtmlInv(cur)}</small></td>
          <td class="text-end ${remainCls}">${money(i.remaining)}</td>
          <td>${statusHtml}</td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-light btn-icon" href="<?= $this->Url->build(['controller' => 'Invoices', 'action' => 'view']) ?>/${escHtmlInv(i.id)}" title="Podgląd"><i class="ri-eye-line"></i></a>
            <a class="btn btn-sm btn-light btn-icon" href="<?= $this->Url->build(['controller' => 'Invoices', 'action' => 'print']) ?>/${escHtmlInv(i.id)}?download=1" title="Pobierz PDF"><i class="ri-file-pdf-2-line"></i></a>
          </td>
        </tr>`;
      }).join('');

      invTBody.innerHTML = rows;
      invCount.textContent = `${list.length} ${list.length===1?'pozycja':'pozycji'}`;

    } catch (e) {
      invTBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Błąd wczytywania.</td></tr>';
    } finally {
      invLoader.classList.add('d-none');
    }
  }

  document.querySelectorAll('.js-invoices').forEach(bindInvoicesHandler);
  function bindInvoicesHandler(btn) {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', () => {
      currentContractorId = btn.dataset.id;
      invNameEl.textContent = btn.dataset.name || ('#' + currentContractorId);
      invUnsettled.checked = false;
      invModal?.show();
      loadInvoices(false);
    });
  }
  invUnsettled?.addEventListener('change', () => loadInvoices(invUnsettled.checked));

  // Klik w saldo (kolumna w tabeli) → otwiera modal Faktury kontrahenta z pre-checked "Tylko nierozliczone".
  // Delegacja na document — przeżywa AJAX-refresh tbody.
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-balance-click');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    currentContractorId = btn.dataset.id;
    invNameEl.textContent = btn.dataset.name || ('#' + currentContractorId);
    invUnsettled.checked = true; // pre-check „Tylko nierozliczone"
    invModal?.show();
    loadInvoices(true);
  });

  // === Modal: Ustawienia kontrahenta ===
  const setModalEl = document.getElementById('contractor-settings');
  const setModal   = setModalEl ? new bootstrap.Modal(setModalEl) : null;
  const csName     = document.getElementById('cs-name');
  const csShare    = document.getElementById('cs-share');
  const csSms      = document.getElementById('cs-sms');
  const csEmail    = document.getElementById('cs-email');
  const csAttach   = document.getElementById('cs-attach');
  const csSave     = document.getElementById('cs-save');

  async function loadSettings(contractorId) {
    const url = '<?= $this->Url->build(['/contractors-settings/view']) ?>/' + contractorId;
    const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
    const data = await res.json();
    const s = data.settings || {};
    csShare.checked = !!s.share_invoices;
    csSms.checked   = !!s.notify_sms;
    csEmail.checked = s.notify_email !== false; // domyślnie true
    csAttach.value  = s.attach_invoice_pdf_mode || 'inherit';
  }

  async function saveSettings(contractorId) {
    const url = '<?= $this->Url->build(['/contractors-settings/save']) ?>/' + contractorId;
    const fd = new FormData();
    fd.set('share_invoices', csShare.checked ? '1':'0');
    fd.set('notify_sms',     csSms.checked ? '1':'0');
    fd.set('notify_email',   csEmail.checked ? '1':'0');
    fd.set('attach_invoice_pdf_mode', csAttach.value);

    const res = await fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
      body: fd
    });
    const data = await res.json();
    if (!data.success) throw new Error('Błąd zapisu');
  }

document.querySelectorAll('.js-settings').forEach(bindSettingsHandler);
function bindSettingsHandler(btn) {
  if (btn.dataset.bound) return;
  btn.dataset.bound = '1';
  btn.addEventListener('click', async () => {
    currentContractorId = btn.dataset.id;
    csName.textContent  = btn.dataset.name || ('#' + currentContractorId);
    try {
      const url = new URL(btn.dataset.viewUrl, window.location.origin);
      const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
      const data = await res.json();
      const s = data.settings || {};
      csShare.checked = !!s.share_invoices;
      csSms.checked   = !!s.notify_sms;
      csEmail.checked = s.notify_email !== false;
      csAttach.value  = s.attach_invoice_pdf_mode || 'inherit';
      setModal?.show();
    } catch {
      toastBody.textContent = 'Nie udało się wczytać ustawień.'; toast.show();
    }
  });
}

csSave?.addEventListener('click', async () => {
  const btn = document.querySelector(`.js-settings[data-id="${currentContractorId}"]`);
  if (!btn) return;
  try {
    const url = new URL(btn.dataset.saveUrl, window.location.origin);
    const fd = new FormData();
    fd.set('share_invoices', csShare.checked ? '1':'0');
    fd.set('notify_sms',     csSms.checked ? '1':'0');
    fd.set('notify_email',   csEmail.checked ? '1':'0');
    fd.set('attach_invoice_pdf_mode', csAttach.value);

    const res = await fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN },
      body: fd
    });
    const data = await res.json();
    if (!data.success) throw new Error();
    toastBody.textContent = 'Zapisano ustawienia.'; toast.show();
    setModal?.hide();
  } catch {
    toastBody.textContent = 'Błąd zapisu ustawień.'; toast.show();
  }
});

  // SweetAlert2 delete contractor
  document.querySelectorAll('.js-delete-contractor').forEach(bindDeleteHandler);
  function bindDeleteHandler(btn) {
    if (btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', async () => {
      const name = btn.dataset.name || ('#' + btn.dataset.id);
      const url  = btn.dataset.url;
      try {
        const result = await Swal.fire({
          title: 'Usunąć kontrahenta?',
          html: `<div>Kontrahent: <strong>${name}</strong></div><div class="text-muted small">Tej operacji nie można cofnąć.</div>`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#6c757d',
          confirmButtonText: 'Usuń',
          cancelButtonText: 'Anuluj'
        });
        if (!result.isConfirmed) return;
        const res = await fetch(url, {
          method: 'POST',
          headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-Token': CSRF_TOKEN }
        });
        let ok = res.ok;
        let data = null;
        try { data = await res.json(); ok = data?.success ?? ok; } catch {}
        if (ok) {
          await Swal.fire({ title: 'Usunięto', icon: 'success', timer: 900, showConfirmButton: false });
          // odśwież tabelę przez AJAX zamiast tylko usuwać wiersz (paginacja może wymagać dosunięcia z następnej strony)
          try { await refreshContractorsTable(); } catch { window.location.reload(); }
        } else {
          await Swal.fire({ title: 'Błąd', text: (data?.message || 'Nie udało się usunąć kontrahenta.'), icon: 'error' });
        }
      } catch (e) {
        await Swal.fire({ title: 'Błąd', text: 'Błąd żądania usunięcia.', icon: 'error' });
      }
    });
  }

});
</script>

<!-- Modal: Lista odbiorców -->
<div class="modal fade" id="recipientsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="ri-user-3-line me-2"></i>Odbiorcy – <span id="recipientsModalContractor"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <div id="recipientsModalBody" class="vstack gap-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary" id="recipientsModalAdd"><i class="ri-add-line me-1"></i>Dodaj odbiorcę</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
  </div>

<!-- Modal: Formularz odbiorcy -->
<div class="modal fade" id="recipientFormModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="recipientFormTitle">Dodaj odbiorcę – <span id="recipientFormContractor"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <form id="recipientForm" class="vstack gap-2">
          <input type="hidden" name="contractor_id" />
          <div class="form-check form-switch align-self-end mb-2">
            <input class="form-check-input" type="checkbox" id="recipient_is_active" name="is_active" checked>
            <label class="form-check-label" for="recipient_is_active">Aktywny</label>
          </div>
          <div>
            <label class="form-label">Nazwa</label>
            <input type="text" name="name" class="form-control" />
          </div>
          <div>
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" />
          </div>
          <div>
            <label class="form-label">Telefon</label>
            <input type="text" name="phone" class="form-control" id="recipientFormPhone" />
          </div>
          <div>
            <label class="form-label">NIP</label>
            <div class="input-group">
              <input type="text" name="nip" class="form-control" maxlength="10" id="recipientFormNip" />
              <button class="btn btn-outline-secondary" type="button" id="recipientFormGusFetch">
                <span class="spinner-border spinner-border-sm me-1 d-none" id="recipientFormGusSpin"></span>
                <i class="ri-database-2-line me-1"></i> Pobierz z GUS
              </button>
            </div>
          </div>
          <div class="row g-2">
            <div class="col">
              <label class="form-label">Miasto</label>
              <input type="text" name="city" class="form-control" />
            </div>
            <div class="col">
              <label class="form-label">Kod pocztowy</label>
              <input type="text" name="postal_code" class="form-control" />
            </div>
            <div class="col-12">
              <label class="form-label">Ulica i nr</label>
              <input type="text" name="street" class="form-control" />
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <button type="button" class="btn btn-primary" id="recipientFormSave"><i class="ri-save-line me-1"></i>Zapisz</button>
      </div>
    </div>
  </div>
</div>

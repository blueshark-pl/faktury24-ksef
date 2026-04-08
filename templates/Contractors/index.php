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
    <button class="btn btn-outline-secondary btn-wave" data-bs-toggle="offcanvas" data-bs-target="#filtersOffcanvas">
      <i class="ri-filter-3-line align-middle me-1"></i> Filtry
    </button>
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

    <button class="btn btn-outline-info btn-wave" id="btn-import-f24">
      <i class="ri-download-cloud-2-line align-middle me-1"></i> Importuj z faktury24
    </button>
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

        <div class="d-flex flex-wrap gap-2 align-items-center">
          <!-- density -->
          <div class="btn-group btn-group-sm" role="group" aria-label="Gęstość">
            <button class="btn btn-light" id="density-normal" title="Standard">
              <i class="ri-layout-2-line"></i>
            </button>
            <button class="btn btn-light" id="density-compact" title="Kompaktowa">
              <i class="ri-layout-row-line"></i>
            </button>
          </div>

          <!-- rows per page -->
          <?= $this->Form->create(null, ['type' => 'get', 'class' => 'd-flex align-items-center gap-2']) ?>
            <?= $this->Form->hidden('q', ['value' => $q]) ?>
            <?= $this->Form->hidden('active', ['value' => $active]) ?>
            <label class="small text-muted">Na stronę</label>
            <?= $this->Form->control('limit', [
              'type' => 'select',
              'label' => false,
              'value' => $limit,
              'options' => [10=>10,25=>25,50=>50,100=>100],
              'class' => 'form-select form-select-sm',
              'onchange' => 'this.form.submit()'
            ]) ?>
          <?= $this->Form->end() ?>

          <!-- quick search -->
          <div class="position-relative">
            <i class="ri-search-line position-absolute" style="left:10px;top:8px;color:#9aa0ac"></i>
            <input id="live-search" class="form-control form-control-sm ps-4" type="search"
                   placeholder="Szukaj: nazwa / NIP / email / miasto"
                   value="<?= h($q) ?>"
                   data-current-url="<?= h($this->Url->build(['action' => 'index'])) ?>">
          </div>

          <!-- clear -->
          <?= $this->Html->link('<i class="ri-refresh-line"></i>', ['action' => 'index'], [
                'class' => 'btn btn-light btn-sm', 'escape' => false, 'title' => 'Wyczyść filtry'
          ]) ?>
        </div>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 70vh;">
          <table class="table table-hover text-nowrap align-middle mb-0" id="contractors-table">
            <thead class="bg-body-tertiary position-sticky top-0" style="z-index:1;">
              <tr>
                <th style="width:32px;">
                  <input class="form-check-input" type="checkbox" id="check-all">
                </th>
                <th><?= $this->Paginator->sort('name', 'Nazwa') ?></th>
                <th><?= $this->Paginator->sort('nip', 'Identyfikator') ?></th>
                <th><?= $this->Paginator->sort('email', 'Email') ?></th>
                <th><?= $this->Paginator->sort('phone', 'Telefon') ?></th>
                <th><?= $this->Paginator->sort('city', 'Miasto') ?></th>
                <th><?= $this->Paginator->sort('country', 'Kraj') ?></th>
                <th><?= $this->Paginator->sort('is_active', 'Status') ?></th>
                <th><?= $this->Paginator->sort('created', 'Dodano') ?></th>
                <th class="text-end">Akcje</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($contractors as $c): ?>
              <tr>
                <td>
                  <input class="form-check-input row-check" type="checkbox" value="<?= (int)$c->id ?>">
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="d-flex flex-column">
                      <?php
                        // Render primary label: company name or person full name
                        $isPerson = isset($c->is_person) ? ((int)$c->is_person === 1) : (!($c->name) && ($c->first_name || $c->last_name));
                        $primary = $isPerson ? trim(($c->first_name ?? '').' '.($c->last_name ?? '')) : ($c->name ?: $c->altname ?: '—');
                      ?>
                      <strong class="d-inline-flex align-items-center gap-2">
                        <?= h($primary) ?>
                        <?php if ($isPerson): ?>
                          <span class="badge bg-info-transparent">Osoba</span>
                        <?php else: ?>
                          <span class="badge bg-primary-transparent">Firma</span>
                        <?php endif; ?>
                      </strong>
                      <?php if (!$isPerson && !empty($c->altname)): ?>
                        <small class="text-muted"><?= h($c->altname) ?></small>
                      <?php endif; ?>
                      <?php if ($c->street || $c->postal_code || $c->city): ?>
                        <small class="text-muted">
                          <i class="ri-map-pin-2-line me-1"></i>
                          <?= h(trim(($c->street ?? '').', '.trim(($c->postal_code ?? '').' '.($c->city ?? ''), ' ,'), ' ,')) ?>
                        </small>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td>
                  <?php if ($c->nip): ?>
                    <span class="d-inline-flex align-items-center gap-2" title="NIP">
                      <small class="text-muted">NIP</small>
                      <span class="d-inline-flex align-items-center gap-1">
                        <code class="bg-body-secondary px-1 py-0 rounded"><?= h($c->nip) ?></code>
                        <button class="btn btn-link btn-sm p-0" data-copy="<?= h($c->nip) ?>" title="Kopiuj NIP">
                          <i class="ri-file-copy-line"></i>
                        </button>
                      </span>
                    </span>
                  <?php elseif (!empty($c->pesel)): ?>
                    <span class="d-inline-flex align-items-center gap-2" title="PESEL">
                      <small class="text-muted">PESEL</small>
                      <span class="d-inline-flex align-items-center gap-1">
                        <code class="bg-body-secondary px-1 py-0 rounded"><?= h($c->pesel) ?></code>
                        <button class="btn btn-link btn-sm p-0" data-copy="<?= h($c->pesel) ?>" title="Kopiuj PESEL">
                          <i class="ri-file-copy-line"></i>
                        </button>
                      </span>
                    </span>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                  <?php if ($c->email): ?>
                    <i class="ri-mail-line me-1 text-muted"></i>
                    <?= $this->Html->link(h($c->email), 'mailto:' . $c->email) ?>
                    <button class="btn btn-link btn-sm p-0 ms-1 copy-btn" data-copy="<?= h($c->email) ?>" title="Kopiuj e-mail">
                      <i class="ri-file-copy-line"></i>
                    </button>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                  <?php if ($c->phone): ?>
                    <i class="ri-phone-line me-1 text-muted"></i><?= h($c->phone) ?>
                    <button class="btn btn-link btn-sm p-0 ms-1 copy-btn" data-copy="<?= h($c->phone) ?>" title="Kopiuj telefon">
                      <i class="ri-file-copy-line"></i>
                    </button>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= h($c->city ?: '—') ?></td>
                <td><?= h($c->country ?: '—') ?></td>
                <td>
                  <?php if ((int)$c->is_active === 1): ?>
                    <span class="badge bg-success-transparent"><i class="ri-check-line me-1"></i>Aktywny</span>
                  <?php else: ?>
                    <span class="badge bg-danger-transparent"><i class="ri-close-line me-1"></i>Nieaktywny</span>
                  <?php endif; ?>
                </td>
                <td><span class="text-muted"><?= $c->created?->format('Y-m-d') ?></span></td>
                <td class="text-end">
                  <div class="btn-list">
                    <button class="btn btn-sm btn-info-light btn-icon js-details"
                            data-id="<?= $c->id ?>"
                            data-name="<?= h($c->name ?: (trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: $c->altname ?: ('#'.$c->id))) ?>"
                            title="Szczegóły">
                      <i class="ri-information-line"></i>
                    </button>
                    <button class="btn btn-sm btn-secondary-light btn-icon js-recipients"
                            data-id="<?= $c->id ?>"
                            data-name="<?= h($c->name ?: $c->altname ?: ('#'.$c->id)) ?>"
                            title="Odbiorcy">
                      <i class="ri-user-3-line"></i>
                    </button>
                    
                    <button class="btn btn-sm btn-success-light btn-icon js-edit"
                            data-id="<?= $c->id ?>"
                            data-name="<?= h($c->name) ?>"
                            title="Edytuj">
                      <i class="ri-pencil-line"></i>
                    </button>
<button
  class="btn btn-sm btn-info-light btn-icon js-invoices"
  data-id="<?= $c->id ?>"
  data-name="<?= h($c->name ?: $c->altname ?: ('#'.$c->id)) ?>"
  data-url="<?= $this->Url->build(['/contractors','invoices', $c->id]) ?>"
  title="Faktury">
  <i class="ri-file-list-line"></i>
</button>


<button
  class="btn btn-sm btn-warning-light btn-icon js-settings"
  data-id="<?= $c->id ?>"
  data-name="<?= h($c->name ?: $c->altname ?: ('#'.$c->id)) ?>"
  data-view-url="<?= $this->Url->build(['controller' => 'ContractorsSettings', 'action' => 'view', $c->id]) ?>"
  data-save-url="<?= $this->Url->build(['controller' => 'ContractorsSettings', 'action' => 'save', $c->id]) ?>"
  title="Ustawienia">
  <i class="ri-settings-3-line"></i>
</button>


                    <button
                      class="btn btn-sm btn-danger-light btn-icon js-delete-contractor"
                      data-url="<?= $this->Url->build(['action' => 'delete', $c->id]) ?>"
                      data-id="<?= $c->id ?>"
                      data-name="<?= h($c->name ?: $c->altname ?: ('#'.$c->id)) ?>"
                      title="Usuń">
                      <i class="ri-delete-bin-line"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>

              <?php if (count($contractors) === 0): ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-5">
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

<!-- Offcanvas: Filtry -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="filtersOffcanvas" aria-labelledby="filtersOffcanvasLabel">
  <div class="offcanvas-header">
    <h5 id="filtersOffcanvasLabel"><i class="ri-filter-3-line me-1"></i> Filtry</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Zamknij"></button>
  </div>
  <div class="offcanvas-body">
    <?= $this->Form->create(null, ['type' => 'get', 'class' => 'vstack gap-3']) ?>
      <?= $this->Form->control('q', [
        'label' => 'Szukaj', 'value' => $q,
        'placeholder' => 'nazwa / NIP / email / miasto',
        'class' => 'form-control'
      ]) ?>
      <?= $this->Form->control('active', [
        'type' => 'select',
        'label' => 'Status',
        'empty' => 'Wszyscy',
        'options' => ['1' => 'Aktywni', '0' => 'Nieaktywni'],
        'default' => $active,
        'class' => 'form-select'
      ]) ?>
      <?= $this->Form->control('limit', [
        'type' => 'select', 'label' => 'Na stronę', 'value' => $limit,
        'options' => [10=>10,25=>25,50=>50,100=>100], 'class' => 'form-select'
      ]) ?>
      <div class="d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit"><i class="ri-search-line me-1"></i> Filtruj</button>
        <?= $this->Html->link('Wyczyść', ['action' => 'index'], ['class' => 'btn btn-light w-100']) ?>
      </div>
    <?= $this->Form->end() ?>
  </div>
</div>
<!-- Modal: Faktury kontrahenta -->
<div class="modal fade" id="contractor-invoices" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="ri-file-list-line me-2"></i>Faktury – <span id="ci-name"></span></h6>
        <div class="form-check ms-3">
          <input class="form-check-input" type="checkbox" id="ci-unsettled">
          <label class="form-check-label small" for="ci-unsettled">Tylko nierozliczone</label>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body p-0">
        <div id="ci-loader" class="p-4 text-center text-muted d-none">Ładowanie…</div>
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead class="bg-body-tertiary">
              <tr>
                <th>Numer</th>
                <th>Data</th>
                <th>Typ</th>
                <th class="text-end">Netto</th>
                <th class="text-end">VAT</th>
                <th class="text-end">Brutto</th>
                <th class="text-end">Zapłacono</th>
                <th class="text-end">Pozostało</th>
                <th>Status</th>
                <th>Akcje</th>
              </tr>
            </thead>
            <tbody id="ci-tbody">
              <tr><td colspan="10" class="text-center text-muted py-4">Brak danych</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <small class="text-muted me-auto" id="ci-count"></small>
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
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="ri-information-line me-2"></i>Szczegóły – <span id="cd-name"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <div class="vstack gap-2" id="cd-body">
          <div><small class="text-muted">Identyfikator:</small> <span id="cd-ident"></span></div>
          <div><small class="text-muted">Email:</small> <span id="cd-email"></span></div>
          <div><small class="text-muted">Telefon:</small> <span id="cd-phone"></span></div>
          <div><small class="text-muted">Adres:</small> <span id="cd-address"></span></div>
          <div><small class="text-muted">Kraj:</small> <span id="cd-country"></span></div>
          <div><small class="text-muted">Status:</small> <span id="cd-status"></span></div>
          <div><small class="text-muted">Notatki:</small> <div id="cd-notes" class="border rounded p-2 bg-body-tertiary"></div></div>
          <div class="mt-3">
            <div class="d-flex align-items-center justify-content-between">
              <strong class="small"><i class="ri-user-3-line me-1"></i>Odbiorcy</strong>
              <button class="btn btn-sm btn-outline-primary" id="cd-load-recipients"><i class="ri-refresh-line me-1"></i>Odśwież</button>
            </div>
            <div id="cd-recipients-loader" class="text-muted small d-none">Ładowanie…</div>
            <div id="cd-recipients-list" class="vstack gap-2"></div>
          </div>
          <div class="mt-3">
            <div class="d-flex align-items-center justify-content-between">
              <strong class="small"><i class="ri-file-list-line me-1"></i>Faktury</strong>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="cd-inv-unsettled">
                <label class="form-check-label small" for="cd-inv-unsettled">Tylko nierozliczone</label>
              </div>
              <button class="btn btn-sm btn-outline-secondary" id="cd-load-invoices"><i class="ri-refresh-line me-1"></i>Odśwież</button>
            </div>
            <div id="cd-invoices-loader" class="text-muted small d-none">Ładowanie…</div>
            <div class="table-responsive">
              <table class="table table-sm align-middle"><thead class="bg-body-tertiary"><tr>
                <th>Numer</th><th>Data</th><th>Typ</th><th class="text-end">Netto</th><th class="text-end">VAT</th><th class="text-end">Brutto</th><th class="text-end">Zapłacono</th><th class="text-end">Pozostało</th><th>Status</th>
              </tr></thead><tbody id="cd-invoices-tbody"><tr><td colspan="9" class="text-center text-muted">Brak danych</td></tr></tbody></table>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
 </div>

<?= $this->element('Contractors/modal_create') ?>

<style>
  /* stabilny layout modala */
  #contractor-create .modal-dialog { max-height: calc(100vh - 3.5rem); margin-top: 1.75rem; margin-bottom: 1.75rem; }
  #contractor-create .modal-content { max-height: calc(100vh - 3.5rem); overflow: hidden; }
  #contractor-create form {
    display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden;
  }
  #contractor-create .form-group { display: block; }
  #contractor-create .help-slot { min-height: 1rem; line-height: 1rem; }
  #contractor-create .form-text { margin-top: .25rem; }
  #contractor-create .input-group > .form-control { min-width: 0; }
  #contractor-create .modal-body .row > [class*="col-"] { align-self: start; }
  /* modal header icon */
  #contractor-create .modal-header { align-items: flex-start; }
  #contractor-create .avatar { width: 2.5rem; height: 2.5rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  #contractor-create .modal-header .modal-title { font-size: .95rem; font-weight: 600; }
  #contractor-create .modal-header small { font-size: .78rem; }

  /* scrollbar zawsze widoczny w modal-body */
  #contractor-create .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: scroll; }
  #contractor-create .modal-body::-webkit-scrollbar { width: 8px; }
  #contractor-create .modal-body::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
  #contractor-create .modal-body::-webkit-scrollbar-thumb { background: #adb5bd; border-radius: 4px; }
  #contractor-create .modal-body::-webkit-scrollbar-thumb:hover { background: #6c757d; }

  /* premium paginacja */
  .pagination .page-link { min-width: 2rem; text-align: center; }
  .pagination .page-item.active .page-link { box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }

  /* kompaktowa tabela */
  .table-compact .table td, .table-compact .table th { padding-top:.4rem; padding-bottom:.4rem; }

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
<!-- Modal: Import z faktury24.com -->
<div class="modal fade" id="importF24Modal" tabindex="-1" aria-labelledby="importF24ModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <div class="avatar bg-info-transparent rounded" style="width:2.5rem;height:2.5rem;display:flex;align-items:center;justify-content:center;">
            <i class="ri-download-cloud-2-line fs-18 text-info"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0" id="importF24ModalLabel">Import z faktury24.com</h5>
            <small class="text-muted">Kontrahenci przypisani do NIP Twojej firmy w starym systemie</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>

      <div class="modal-body p-0">
        <!-- Stan: ładowanie -->
        <div id="f24-loading" class="d-flex flex-column align-items-center justify-content-center py-5 gap-3">
          <div class="spinner-border text-info" role="status" style="width:2.5rem;height:2.5rem;"></div>
          <div class="text-muted">Pobieranie kontrahentów z faktury24.com…</div>
        </div>

        <!-- Stan: błąd -->
        <div id="f24-error" class="d-none p-4">
          <div class="alert alert-danger mb-0" id="f24-error-msg"></div>
        </div>

        <!-- Stan: wyniki -->
        <div id="f24-results" class="d-none">
          <div class="px-4 pt-3 pb-2 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
              <span class="fw-semibold" id="f24-count-label">0 kontrahentów</span>
              <span class="text-muted ms-2 small" id="f24-already-label"></span>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="f24-check-all">
                <label class="form-check-label small" for="f24-check-all">Zaznacz wszystkich nieimportowanych</label>
              </div>
              <span class="badge bg-primary-transparent text-primary" id="f24-selected-badge">0 zaznaczonych</span>
            </div>
          </div>

          <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
            <table class="table table-hover table-sm align-middle mb-0">
              <thead class="table-light sticky-top">
                <tr>
                  <th style="width:2.5rem;"></th>
                  <th>Nazwa</th>
                  <th>NIP</th>
                  <th>Adres</th>
                  <th>E-mail</th>
                  <th style="width:8rem;text-align:center;">Status</th>
                </tr>
              </thead>
              <tbody id="f24-tbody"></tbody>
            </table>
          </div>

          <!-- Pasek postępu importu -->
          <div id="f24-import-progress" class="d-none px-4 py-3 border-top">
            <div class="d-flex justify-content-between mb-1">
              <small class="text-muted" id="f24-progress-label">Importowanie…</small>
              <small class="text-muted" id="f24-progress-pct">0%</small>
            </div>
            <div class="progress" style="height:.5rem;">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" id="f24-progress-bar" style="width:0%"></div>
            </div>
          </div>

          <!-- Wynik importu -->
          <div id="f24-import-result" class="d-none px-4 py-3 border-top"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
        <button type="button" class="btn btn-info d-none" id="f24-btn-import" disabled>
          <i class="ri-download-cloud-2-line me-1"></i>
          <span id="f24-btn-import-label">Importuj zaznaczone</span>
        </button>
      </div>
    </div>
  </div>
</div>
<style>
  #importF24Modal .sticky-top { top: 0; z-index: 1; }
  #importF24Modal tbody tr.already-imported td { opacity: .5; }
</style>

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
  // tooltips (container:body prevents clipping inside modals)
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el =>
    new bootstrap.Tooltip(el, { container: 'body', trigger: 'hover focus' })
  );
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

  // select all + indeterminate
  const all = document.getElementById('check-all');
  const rows = document.querySelectorAll('.row-check');
  const refreshIndeterminate = () => {
    const checked = [...rows].filter(x => x.checked).length;
    all.indeterminate = checked > 0 && checked < rows.length;
    all.checked = checked === rows.length && rows.length > 0;
  };
  if (all) {
    all.addEventListener('change', () => {
      rows.forEach(ch => ch.checked = all.checked);
      refreshIndeterminate();
    });
    rows.forEach(ch => ch.addEventListener('change', refreshIndeterminate));
  }

  // copy buttons
  const toastEl = document.getElementById('toast');
  const toastBody = document.getElementById('toast-body');
  const toast = new bootstrap.Toast(toastEl, { delay: 1500 });
  document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(btn.dataset.copy);
        toastBody.textContent = 'Skopiowano: ' + btn.dataset.copy;
        toast.show();
      } catch (e) {
        toastBody.textContent = 'Nie udało się skopiować';
        toast.show();
      }
    });
  });

  // live search (debounce -> 400ms)
  const search = document.getElementById('live-search');
  if (search) {
    let t;
    const baseUrl = search.dataset.currentUrl;
    const current = new URL(window.location.href);
    const active = current.searchParams.get('active') ?? '';
    const limit  = current.searchParams.get('limit') ?? '';
    search.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        const url = new URL(baseUrl, window.location.origin);
        if (search.value.trim() !== '') url.searchParams.set('q', search.value.trim());
        if (active !== '') url.searchParams.set('active', active);
        if (limit  !== '') url.searchParams.set('limit', limit);
        window.location.href = url.toString();
      }, 400);
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
  document.querySelectorAll('.js-details').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      cdName.textContent = btn.dataset.name || ('#' + id);
      try {
        const res = await fetch('<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'viewJson']) ?>/' + id, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        const c = data.contractor || {};
        const ident = c.nip ? `NIP ${c.nip}` : (c.pesel ? `PESEL ${c.pesel}` : '—');
        cdIdent.textContent = ident;
        cdEmail.textContent = c.email || '—';
        cdPhone.textContent = c.phone || '—';
        const addr = (c.street || c.postal_code || c.city)
          ? `${c.street ?? ''}, ${(c.postal_code ?? '')} ${(c.city ?? '')}`.trim().replace(/^,|, $/g,'') : '—';
        cdAddr.textContent = addr;
        cdCountry.textContent = c.country || '—';
        cdStatus.innerHTML = Number(c.is_active) === 1
          ? '<span class="badge bg-success-transparent"><i class="ri-check-line me-1"></i>Aktywny</span>'
          : '<span class="badge bg-danger-transparent"><i class="ri-close-line me-1"></i>Nieaktywny</span>';
        cdNotes.textContent = c.notes || '';
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
        if (invTBody) invTBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Brak danych</td></tr>';
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
        const renderInv = (list) => {
          if (!list.length) { invTBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Brak faktur.</td></tr>'; return; }
          invTBody.innerHTML = list.map(i => {
            const status = (i.paymentstate && i.paymentstate.toLowerCase()==='paid')
              ? '<span class="badge bg-success-transparent"><i class="ri-check-line me-1"></i>Opłacona</span>'
              : '<span class="badge bg-warning-transparent"><i class="ri-time-line me-1"></i>Otwarta</span>';
            return `<tr>
              <td><code>${i.fullnumber ?? i.id}</code></td>
              <td>${i.date ? (new Date(i.date)).toLocaleDateString('pl-PL') : '—'}</td>
              <td>${i.type ?? '—'}</td>
              <td class="text-end">${money(i.netto)}</td>
              <td class="text-end">${money(i.tax)}</td>
              <td class="text-end"><strong>${money(i.total)}</strong></td>
              <td class="text-end">${money(i.alreadypaid)}</td>
              <td class="text-end">${money(i.remaining)}</td>
              <td>${status}</td>
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
            invTBody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Błąd ładowania.</td></tr>';
          } finally {
            invLoader?.classList.add('d-none');
          }
        };
        loadInvBtn.onclick = loadInvoices;
        invUnsettled?.addEventListener('change', loadInvoices);
        // auto-load related data on open
        try { loadRecBtn?.click(); } catch {}
        try { await loadInvoices(); } catch {}
        detailsModal?.show();
      } catch {
        toastBody.textContent = 'Nie udało się wczytać szczegółów.'; toast.show();
      }
    });
  });

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

  document.querySelectorAll('.js-recipients').forEach(btn => {
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
  });

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
    // Initialize intl-tel-input for recipient form phone
    try {
      const rPhone = document.getElementById('recipientFormPhone');
      if (window.intlTelInput && rPhone) {
        // destroy previous instance if any
        if (window.itiRecipientFormPhone && typeof window.itiRecipientFormPhone.destroy === 'function') {
          try { window.itiRecipientFormPhone.destroy(); } catch {}
        }
        window.itiRecipientFormPhone = window.intlTelInput(rPhone, {
          initialCountry: (document.getElementById('country-hidden')?.value || 'PL').toLowerCase(),
          preferredCountries: ['pl','de','cz','sk','gb','us'],
          separateDialCode: true,
          nationalMode: true,
          utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@17.0.19/build/js/utils.js',
        });
        try { rPhone.setAttribute('placeholder', ''); } catch {}
        rPhone.addEventListener('blur', () => {
          // simple validation feedback
          const itiLocal = window.itiRecipientFormPhone;
          const input = rPhone;
          if (!input) return;
          clearInvalid(input);
          if (input.value && itiLocal && !itiLocal.isValidNumber()) {
            setInvalid(input, 'Nieprawidłowy numer telefonu odbiorcy.');
          }
        });
      }
    } catch {}

    // NIP live validation for recipient form modal
    try {
      const rNip = document.getElementById('recipientFormNip');
      const rGusBtn = document.getElementById('recipientFormGusFetch');
      const rGusSpin= document.getElementById('recipientFormGusSpin');
      if (rNip) {
        rNip.addEventListener('input', () => {
          clearInvalid(rNip);
        });
        rNip.addEventListener('blur', () => {
          clearInvalid(rNip);
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
    try {
      const rNip = document.getElementById('recipientFormNip');
      if (rNip) fd.set('nip', (rNip.value || '').trim());
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
  const form = document.getElementById('recipientForm');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    // HTML5 validity first
    if (!form.checkValidity()) { e.stopPropagation(); form.classList.add('was-validated'); return; }

    // Custom NIP/PESEL compliance checks
    const peselMode = !!usePesel?.checked;
    if (peselMode) {
      const v = onlyDigits(peselInput?.value || '');
      clearInvalid(peselInput);
      if (v && !validatePESEL(v)) {
        setInvalid(peselInput, 'Nieprawidłowy PESEL — 11 cyfr i poprawna kontrola.');
        toastBody.textContent = 'Nieprawidłowy PESEL.'; toast.show();
        return;
      }
      clearInvalid(personNipInput);
    } else {
      const v = nipInput?.value?.trim() || '';
      clearInvalid(nipInput);
      if (!v) {
        setInvalid(nipInput, 'NIP jest wymagany dla przedsiębiorcy.');
        toastBody.textContent = 'Podaj NIP.'; toast.show();
        return;
      }
    }

    const mode = modalEl.dataset.mode || 'add';
    const action = form.getAttribute('action');
    const fd = new FormData(form);
    if (!useCorrespondenceAddress?.checked) {
      ['correspondence_city', 'correspondence_street', 'correspondence_postal_code', 'correspondence_country'].forEach((f) => {
        fd.set(f, '');
      });
    }
    // send NIP as-is (supports foreign VAT numbers); normalize PESEL digits only
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
      if (recipientNip) clearInvalid(recipientNip);

      const res = await fetch(action, {
        method: 'POST', // _method=PUT dla edycji
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      });
      const data = await res.json();

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
        toastBody.textContent = mode === 'add' ? 'Dodano kontrahenta.' : 'Zapisano zmiany.';
        toast.show();
        bootstrap.Modal.getInstance(modalEl)?.hide();

        if (mode === 'edit' && data.contractor) {
          // Podmień wiersz w tabeli
          const row = document.querySelector(`.row-check[value="${data.contractor.id}"]`)?.closest('tr');
          if (row) {
            const nameCell = row.querySelector('td:nth-child(2) .d-flex.flex-column');
            if (nameCell) {
              const strong = nameCell.querySelector('strong');
              if (strong) strong.textContent = data.contractor.name || data.contractor.altname || '—';
              const alt = nameCell.querySelector('small.text-muted');
              if (data.contractor.altname) {
                if (alt) alt.textContent = data.contractor.altname;
                else {
                  const s = document.createElement('small');
                  s.className = 'text-muted';
                  s.textContent = data.contractor.altname;
                  nameCell.appendChild(s);
                }
              } else if (alt) alt.remove();

              const addr = (data.contractor.street || data.contractor.postal_code || data.contractor.city)
                ? `${data.contractor.street ?? ''}, ${(data.contractor.postal_code ?? '')} ${(data.contractor.city ?? '')}`.trim().replace(/^,|, $/g,'')
                : '';
              const addrEl = nameCell.querySelector('small.text-muted i.ri-map-pin-2-line')?.parentElement
                           || nameCell.querySelector('small.text-muted:last-child');
              if (addr) {
                if (addrEl && addrEl.querySelector('i.ri-map-pin-2-line')) {
                  addrEl.innerHTML = `<i class="ri-map-pin-2-line me-1"></i>${addr}`;
                } else {
                  const s = document.createElement('small');
                  s.className = 'text-muted';
                  s.innerHTML = `<i class="ri-map-pin-2-line me-1"></i>${addr}`;
                  nameCell.appendChild(s);
                }
              } else if (addrEl) addrEl.remove();
            }

            // NIP
            const nipCell = row.querySelector('td:nth-child(3)');
            if (nipCell) {
              nipCell.innerHTML = data.contractor.nip
                ? `<span class="d-inline-flex align-items-center gap-1">
                     <code class="bg-body-secondary px-1 py-0 rounded">${data.contractor.nip}</code>
                     <button class="btn btn-link btn-sm p-0 copy-btn" data-copy="${data.contractor.nip}" title="Kopiuj NIP">
                       <i class="ri-file-copy-line"></i>
                     </button>
                   </span>` : '—';
            }

            // Email
            const emailCell = row.querySelector('td:nth-child(4)');
            if (emailCell) {
              emailCell.innerHTML = data.contractor.email
                ? `<i class="ri-mail-line me-1 text-muted"></i>
                   <a href="mailto:${data.contractor.email}">${data.contractor.email}</a>
                   <button class="btn btn-link btn-sm p-0 ms-1 copy-btn" data-copy="${data.contractor.email}" title="Kopiuj e-mail">
                     <i class="ri-file-copy-line"></i>
                   </button>` : '—';
            }

            // Telefon
            const phoneCell = row.querySelector('td:nth-child(5)');
            if (phoneCell) {
              phoneCell.innerHTML = data.contractor.phone
                ? `<i class="ri-phone-line me-1 text-muted"></i>${data.contractor.phone}
                   <button class="btn btn-link btn-sm p-0 ms-1 copy-btn" data-copy="${data.contractor.phone}" title="Kopiuj telefon">
                     <i class="ri-file-copy-line"></i>
                   </button>` : '—';
            }

            // Miasto / Kraj / Status
            const cityCell    = row.querySelector('td:nth-child(6)'); if (cityCell)    cityCell.textContent = data.contractor.city || '—';
            const countryCell = row.querySelector('td:nth-child(7)'); if (countryCell) countryCell.textContent = data.contractor.country || '—';
            const statusCell  = row.querySelector('td:nth-child(8)');
            if (statusCell) {
              statusCell.innerHTML = Number(data.contractor.is_active) === 1
                ? '<span class="badge bg-success-transparent"><i class="ri-check-line me-1"></i>Aktywny</span>'
                : '<span class="badge bg-danger-transparent"><i class="ri-close-line me-1"></i>Nieaktywny</span>';
            }

            // rebind kopiowania po podmianie
            row.querySelectorAll('.copy-btn').forEach(btn => {
              btn.addEventListener('click', async () => {
                try {
                  await navigator.clipboard.writeText(btn.dataset.copy);
                  toastBody.textContent = 'Skopiowano: ' + btn.dataset.copy;
                  toast.show();
                } catch {
                  toastBody.textContent = 'Nie udało się skopiować';
                  toast.show();
                }
              });
            });
          }
        } else {
          // po dodaniu – odśwież listę (najprościej)
          window.location.reload();
        }

      } else {
        toastBody.textContent = data.message || 'Błąd zapisu.';
        toast.show();
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
      }
    } catch (err) {
      toastBody.textContent = 'Błąd połączenia z serwerem.';
      toast.show();
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
        const vat = data.vat || {};
        setVal('input[name="name"]',        c.name);
        setVal('input[name="city"]',        c.city);
        setVal('input[name="postal_code"]', c.zip);
        setVal('input[name="country"]',     c.country || 'PL');
        setVal('input[name="street"]',      c.street);
        showContractorVatStatus(vat);
        toastBody.textContent = 'Pobrano dane z GUS.';
        toast.show();
      } else {
        checkContractorVatStatus(nip);
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

  async function loadInvoices(unsettled) {
    if (!currentContractorId) return;
    invLoader.classList.remove('d-none');
    invTBody.innerHTML = '';
    invCount.textContent = '';

    const url = new URL('<?= $this->Url->build(['controller'=>'Contractors','action'=>'invoices']) ?>/' + currentContractorId, window.location.origin);
    if (unsettled) url.searchParams.set('unsettled', '1');

    try {
      const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Błąd');

      const list = data.invoices || [];
      if (list.length === 0) {
        invTBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Brak faktur.</td></tr>';
        invCount.textContent = '0 pozycji';
        return;
      }

      const rows = list.map(i => {
        const status = (i.paymentstate && i.paymentstate.toLowerCase()==='paid')
          ? '<span class="badge bg-success-transparent"><i class="ri-check-line me-1"></i>Opłacona</span>'
          : '<span class="badge bg-warning-transparent"><i class="ri-time-line me-1"></i>Otwarta</span>';

        const flags = [
          i.is_sent ? '<i class="ri-send-plane-2-line" title="Wysłana"></i>' : '',
          i.is_print ? '<i class="ri-printer-line" title="Drukowana"></i>' : '',
          i.is_api ? '<i class="ri-plug-line" title="API"></i>' : ''
        ].filter(Boolean).join(' ');

        return `<tr>
          <td><code>${i.fullnumber ?? i.id}</code></td>
          <td>${i.date ? (new Date(i.date)).toLocaleDateString('pl-PL') : '—'}</td>
          <td>${i.type ?? '—'}</td>
          <td class="text-end">${money(i.netto)}</td>
          <td class="text-end">${money(i.tax)}</td>
          <td class="text-end"><strong>${money(i.total)}</strong></td>
          <td class="text-end">${money(i.alreadypaid)}</td>
          <td class="text-end">${money(i.remaining)}</td>
          <td>${status} <span class="ms-2 text-muted">${flags}</span></td>
          <td class="text-nowrap">
            <a class="btn btn-xs btn-primary-light" href="<?= $this->Url->build(['/invoices/view']) ?>/${i.id}" title="Podgląd"><i class="ri-eye-line"></i></a>
            <a class="btn btn-xs btn-secondary-light" href="<?= $this->Url->build(['/invoices/pdf']) ?>/${i.id}" title="PDF"><i class="ri-file-pdf-2-line"></i></a>
          </td>
        </tr>`;
      }).join('');

      invTBody.innerHTML = rows;
      invCount.textContent = `${list.length} ${list.length===1?'pozycja':'pozycji'}`;

    } catch (e) {
      invTBody.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Błąd wczytywania.</td></tr>';
    } finally {
      invLoader.classList.add('d-none');
    }
  }

  document.querySelectorAll('.js-invoices').forEach(btn => {
    btn.addEventListener('click', () => {
      currentContractorId = btn.dataset.id;
      invNameEl.textContent = btn.dataset.name || ('#' + currentContractorId);
      invUnsettled.checked = false;
      invModal?.show();
      loadInvoices(false);
    });
  });
  invUnsettled?.addEventListener('change', () => loadInvoices(invUnsettled.checked));

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

document.querySelectorAll('.js-settings').forEach(btn => {
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
});

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

  // === Import z faktury24.com ===
  (function(){
    const btnOpen    = document.getElementById('btn-import-f24');
    const modalEl    = document.getElementById('importF24Modal');
    if (!btnOpen || !modalEl) return;
    const bsModal    = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });

    const elLoading  = document.getElementById('f24-loading');
    const elError    = document.getElementById('f24-error');
    const elErrorMsg = document.getElementById('f24-error-msg');
    const elResults  = document.getElementById('f24-results');
    const tbody      = document.getElementById('f24-tbody');
    const checkAll   = document.getElementById('f24-check-all');
    const selBadge   = document.getElementById('f24-selected-badge');
    const countLabel = document.getElementById('f24-count-label');
    const alreadyLbl = document.getElementById('f24-already-label');
    const btnImport  = document.getElementById('f24-btn-import');
    const btnImpLbl  = document.getElementById('f24-btn-import-label');
    const progWrap   = document.getElementById('f24-import-progress');
    const progBar    = document.getElementById('f24-progress-bar');
    const progLabel  = document.getElementById('f24-progress-label');
    const progPct    = document.getElementById('f24-progress-pct');
    const resultDiv  = document.getElementById('f24-import-result');

    const FETCH_URL  = '<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'importFetch']) ?>';
    const IMPORT_URL = '<?= $this->Url->build(['controller' => 'Contractors', 'action' => 'importBatch']) ?>';

    let allRows = [];

    function show(el) { ['f24-loading','f24-error','f24-results'].forEach(id => document.getElementById(id)?.classList.add('d-none')); el?.classList.remove('d-none'); }

    function updateSelected() {
      const checked = tbody.querySelectorAll('input[type="checkbox"]:checked').length;
      selBadge.textContent = checked + ' zaznaczonych';
      if (btnImport) {
        btnImport.disabled = checked === 0;
        if (btnImpLbl) btnImpLbl.textContent = checked > 0 ? 'Importuj zaznaczonych (' + checked + ')' : 'Importuj zaznaczone';
      }
      // indeterminate on "check all"
      const total = tbody.querySelectorAll('input[type="checkbox"]:not(:disabled)').length;
      checkAll.indeterminate = checked > 0 && checked < total;
      checkAll.checked = total > 0 && checked === total;
    }

    function renderRows(rows) {
      allRows = rows;
      const alreadyCount = rows.filter(r => r.already_imported).length;
      countLabel.textContent = rows.length + ' kontrahentów';
      alreadyLbl.textContent = alreadyCount > 0 ? '(' + alreadyCount + ' już zaimportowanych)' : '';

      tbody.innerHTML = rows.map((r, i) => {
        const addr = [r.street, r.postal_code + ' ' + r.city].filter(Boolean).join(', ');
        const badgeHtml = r.already_imported
          ? '<span class="badge bg-secondary-transparent text-secondary">Już w bazie</span>'
          : '<span class="badge bg-success-transparent text-success">Nowy</span>';
        return `<tr class="${r.already_imported ? 'already-imported' : ''}" data-idx="${i}">
          <td class="text-center">
            <input type="checkbox" class="form-check-input f24-row-check" data-idx="${i}"
              ${r.already_imported ? 'disabled title="Już istnieje w bazie"' : ''}>
          </td>
          <td><strong>${escHtml(r.name)}</strong></td>
          <td><code>${escHtml(r.nip)}</code></td>
          <td class="small text-muted">${escHtml(addr)}</td>
          <td class="small">${escHtml(r.email)}</td>
          <td class="text-center">${badgeHtml}</td>
        </tr>`;
      }).join('');

      tbody.querySelectorAll('.f24-row-check').forEach(cb => cb.addEventListener('change', updateSelected));
      updateSelected();
    }

    function escHtml(s) {
      return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    checkAll?.addEventListener('change', () => {
      tbody.querySelectorAll('.f24-row-check:not(:disabled)').forEach(cb => cb.checked = checkAll.checked);
      updateSelected();
    });

    async function fetchContractors() {
      show(elLoading);
      btnImport?.classList.add('d-none');
      if (resultDiv) { resultDiv.innerHTML = ''; resultDiv.classList.add('d-none'); }
      if (progWrap) progWrap.classList.add('d-none');
      try {
        const resp = await fetch(FETCH_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await resp.json();
        if (!data.success) {
          elErrorMsg.textContent = data.error || 'Nieznany błąd.';
          show(elError);
          return;
        }
        if (!data.rows || data.rows.length === 0) {
          elErrorMsg.textContent = 'Brak kontrahentów do zaimportowania (stary system nie zwrócił wyników dla NIP tej firmy).';
          show(elError);
          return;
        }
        renderRows(data.rows);
        show(elResults);
        btnImport?.classList.remove('d-none');
      } catch (e) {
        elErrorMsg.textContent = 'Błąd połączenia: ' + (e?.message || e);
        show(elError);
      }
    }

    btnOpen.addEventListener('click', () => {
      bsModal.show();
      fetchContractors();
    });

    btnImport?.addEventListener('click', async () => {
      const selected = [];
      tbody.querySelectorAll('.f24-row-check:checked').forEach(cb => {
        const idx = parseInt(cb.dataset.idx, 10);
        if (!isNaN(idx) && allRows[idx]) selected.push(allRows[idx]);
      });
      if (!selected.length) return;

      btnImport.disabled = true;
      checkAll.disabled = true;
      if (progWrap) {
        progWrap.classList.remove('d-none');
        progBar.style.width = '0%';
        progPct.textContent = '0%';
        progLabel.textContent = 'Importowanie ' + selected.length + ' kontrahentów…';
      }

      // Animacja postępu (nie znamy kroków, fake progress do 90%)
      let fake = 0;
      const t = setInterval(() => {
        if (fake < 88) { fake += 3; progBar.style.width = fake + '%'; progPct.textContent = fake + '%'; }
      }, 120);

      try {
        const fd = new FormData();
        fd.append('rows', JSON.stringify(selected));
        // CakePHP wymaga danych jako JSON w body lub jako FormData — wysyłamy JSON
        const resp = await fetch(IMPORT_URL, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': CSRF_TOKEN,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ rows: selected }),
        });
        const data = await resp.json();

        clearInterval(t);
        progBar.style.width = '100%';
        progPct.textContent = '100%';
        progBar.classList.remove('progress-bar-animated');

        if (resultDiv) {
          resultDiv.classList.remove('d-none');
          if (data.success) {
            const errHtml = data.errors?.length
              ? '<ul class="mb-0 mt-2 small">' + data.errors.map(e => '<li>' + escHtml(e) + '</li>').join('') + '</ul>'
              : '';
            resultDiv.innerHTML = `<div class="alert alert-success mb-0">
              <i class="ri-check-circle-line me-1"></i>
              <strong>Zaimportowano: ${data.imported}</strong>${data.skipped > 0 ? ', pominięto (duplikaty): ' + data.skipped : ''}
              ${errHtml}
            </div>`;
          } else {
            resultDiv.innerHTML = `<div class="alert alert-danger mb-0"><i class="ri-error-warning-line me-1"></i>${escHtml(data.error || 'Błąd importu.')}</div>`;
          }
        }

        // Odśwież zaznaczone checkboxy — oznacz jako zaimportowane
        if (data.success && data.imported > 0) {
          tbody.querySelectorAll('.f24-row-check:checked').forEach(cb => {
            const tr = cb.closest('tr');
            if (tr) tr.classList.add('already-imported');
            cb.checked = false;
            cb.disabled = true;
          });
          updateSelected();
          // badge przy rzędach "nowy" → "już w bazie"
          tbody.querySelectorAll('tr.already-imported td:last-child').forEach(td => {
            if (td.innerHTML.includes('Nowy')) td.innerHTML = '<span class="badge bg-secondary-transparent text-secondary">Już w bazie</span>';
          });
        }
      } catch (e) {
        clearInterval(t);
        if (resultDiv) {
          resultDiv.classList.remove('d-none');
          resultDiv.innerHTML = `<div class="alert alert-danger mb-0">Błąd: ${escHtml(e?.message || e)}</div>`;
        }
      } finally {
        btnImport.disabled = false;
        checkAll.disabled = false;
      }
    });

    // Przeładuj przy ponownym otwarciu
    modalEl.addEventListener('hidden.bs.modal', () => {
      allRows = [];
      if (tbody) tbody.innerHTML = '';
    });
  })();

  // SweetAlert2 delete contractor
  document.querySelectorAll('.js-delete-contractor').forEach(btn => {
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
          // remove row from table
          const row = btn.closest('tr');
          row?.parentElement?.removeChild(row);
        } else {
          await Swal.fire({ title: 'Błąd', text: (data?.message || 'Nie udało się usunąć kontrahenta.'), icon: 'error' });
        }
      } catch (e) {
        await Swal.fire({ title: 'Błąd', text: 'Błąd żądania usunięcia.', icon: 'error' });
      }
    });
  });

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
              <input type="text" name="nip" class="form-control" maxlength="20" id="recipientFormNip" />
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

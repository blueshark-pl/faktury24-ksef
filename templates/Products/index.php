<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface|\Cake\Collection\CollectionInterface $products
 * @var array<int,string> $units    // [id => name]
 * @var array<string,string> $vats  // [uuid => "Podstawowa (23.00%)"] lub podobnie
 */

$this->assign('title', 'Produkty / Usługi');

// paging
$paging  = $this->getRequest()->getAttribute('paging')['Products'] ?? [];
$total   = $paging['count'] ?? null;

// query params
$q        = trim((string)$this->request->getQuery('q'));
$active   = $this->request->getQuery('active');   // '1' / '0' / null
$service  = $this->request->getQuery('service');  // '1' / '0' / null
$unitId   = $this->request->getQuery('unit_id');  // int|null
$vatId    = $this->request->getQuery('vat_id');   // uuid|null
$limit    = (int)($this->request->getQuery('limit') ?? 25);
$limit    = $limit > 0 ? $limit : 25;

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

// pomoc: mapy do wyświetlania
$unitsMap = $units ?? [];
$vatsMap  = $vats  ?? [];

// waluty – prosta lista (możesz podmienić z DB)
$currencies = ['PLN' => 'PLN', 'EUR' => 'EUR', 'USD' => 'USD'];
?>

<!-- Start::page-header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Produkty / Usługi</h1>
    <nav>
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
        <li class="breadcrumb-item active" aria-current="page">Produkty</li>
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

    <button class="btn btn-outline-info btn-wave" id="btn-import-f24-products">
      <i class="ri-download-cloud-2-line align-middle me-1"></i> Importuj z faktury24
    </button>
    <button class="btn btn-primary btn-wave" data-bs-toggle="modal" data-bs-target="#product-create">
      <i class="ri-add-line me-1"></i> Dodaj produkt/usługę
    </button>
  </div>
</div>
<!-- End::page-header -->

<div class="row">
  <div class="col-xl-12">
    <div class="card custom-card">
      <div class="card-header justify-content-between flex-wrap gap-2">
        <div class="card-title d-flex align-items-center gap-2">
          Lista produktów
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
              <?= $active === '1' ? 'Aktywne' : 'Nieaktywne' ?>
            </span>
          <?php endif; ?>
          <?php if ($service !== null && $service !== ''): ?>
            <span class="badge bg-info-transparent">
              <?= $service === '1' ? 'Usługi' : 'Produkty' ?>
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
            <?= $this->Form->hidden('service', ['value' => $service]) ?>
            <?= $this->Form->hidden('unit_id', ['value' => $unitId]) ?>
            <?= $this->Form->hidden('vat_id', ['value' => $vatId]) ?>
            <label class="small text-muted">Na stronę</label>
            <?= $this->Form->control('limit', [
              'type' => 'select','label' => false,'value' => $limit,
              'options' => [10=>10,25=>25,50=>50,100=>100],
              'class' => 'form-select form-select-sm',
              'onchange' => 'this.form.submit()'
            ]) ?>
          <?= $this->Form->end() ?>

          <!-- quick search -->
          <div class="position-relative">
            <i class="ri-search-line position-absolute" style="left:10px;top:8px;color:#9aa0ac"></i>
            <input id="live-search" class="form-control form-control-sm ps-4" type="search"
                   placeholder="Szukaj: nazwa / kod / barcode / PKWiU"
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
          <table class="table table-hover text-nowrap align-middle mb-0" id="products-table">
            <thead class="bg-body-tertiary position-sticky top-0" style="z-index:1;">
              <tr>
                <th style="width:32px;">
                  <input class="form-check-input" type="checkbox" id="check-all">
                </th>
                <th><?= $this->Paginator->sort('name', 'Nazwa') ?></th>
                <th><?= $this->Paginator->sort('code', 'Kod') ?></th>
                <th><?= $this->Paginator->sort('is_service', 'Typ') ?></th>
                <th><?= $this->Paginator->sort('unit_id', 'Jm') ?></th>
                <th><?= $this->Paginator->sort('vat_id', 'VAT') ?></th>
                <th><?= $this->Paginator->sort('net_price', 'Cena netto') ?></th>
                <th><?= $this->Paginator->sort('currency', 'Waluta') ?></th>
                <th><?= $this->Paginator->sort('is_active', 'Status') ?></th>
                <th><?= $this->Paginator->sort('created', 'Dodano') ?></th>
                <th class="text-end">Akcje</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
              <tr>
                <td><input class="form-check-input row-check" type="checkbox" value="<?= h($p->id) ?>"></td>
                <td>
                  <div class="d-flex flex-column">
                    <strong><?= h($p->name ?: '—') ?></strong>
                    <?php if ($p->pkwiu || $p->gtu_code || $p->barcode): ?>
                      <small class="text-muted">
                        <?php if ($p->pkwiu): ?><span class="me-2">PKWiU: <?= h($p->pkwiu) ?></span><?php endif; ?>
                        <?php if ($p->gtu_code): ?><span class="me-2">GTU: <?= h($p->gtu_code) ?></span><?php endif; ?>
                        <?php if ($p->barcode): ?><i class="ri-barcode-line me-1"></i><?= h($p->barcode) ?><?php endif; ?>
                      </small>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <?php if ($p->code): ?>
                    <span class="d-inline-flex align-items-center gap-1">
                      <code class="bg-body-secondary px-1 py-0 rounded"><?= h($p->code) ?></code>
                      <button class="btn btn-link btn-sm p-0 copy-btn" data-copy="<?= h($p->code) ?>" title="Kopiuj kod">
                        <i class="ri-file-copy-line"></i>
                      </button>
                    </span>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td>
                  <?= (int)$p->is_service === 1
                        ? '<span class="badge bg-info-transparent">Usługa</span>'
                        : '<span class="badge bg-secondary-transparent">Produkt</span>' ?>
                </td>
                <td><?= h($unitsMap[$p->unit_id] ?? $p->unit_id) ?></td>
                <td><?= h($vatsMap[$p->vat_id] ?? '—') ?></td>
                <td class="text-end"><?= number_format((float)$p->net_price, 2, ',', ' ') ?></td>
                <td><?= h($p->currency ?: 'PLN') ?></td>
                <td>
                  <?php if ((int)$p->is_active === 1): ?>
                    <span class="badge bg-success-transparent"><i class="ri-check-line me-1"></i>Aktywne</span>
                  <?php else: ?>
                    <span class="badge bg-danger-transparent"><i class="ri-close-line me-1"></i>Nieaktywne</span>
                  <?php endif; ?>
                </td>
                <td><span class="text-muted"><?= $p->created?->format('Y-m-d') ?></span></td>
                <td class="text-end">
                  <div class="btn-list">
                    <button class="btn btn-sm btn-primary-light btn-icon js-product-view"
                            data-id="<?= h($p->id) ?>"
                            title="Podgląd">
                      <i class="ri-eye-line"></i>
                    </button>
                    <button class="btn btn-sm btn-success-light btn-icon js-edit"
                            data-id="<?= h($p->id) ?>"
                            data-name="<?= h($p->name) ?>"
                            title="Edytuj">
                      <i class="ri-pencil-line"></i>
                    </button>
                    <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>', ['action' => 'delete', $p->id], [
                      'confirm' => 'Usunąć ten produkt/usługę?',
                      'class' => 'btn btn-sm btn-danger-light btn-icon',
                      'escape' => false,
                      'title' => 'Usuń'
                    ]) ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>

              <?php if (count($products) === 0): ?>
              <tr>
                <td colspan="11" class="text-center text-muted py-5">
                  <div class="mb-2" style="font-size:32px;opacity:.3">📦</div>
                  Brak wyników. Zmień filtry lub dodaj pierwszy produkt/usługę.
                  <div class="mt-3">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#product-create">
                      <i class="ri-add-line me-1"></i> Dodaj
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
        'placeholder' => 'nazwa / kod / barcode / PKWiU / GTU',
        'class' => 'form-control'
      ]) ?>

      <?= $this->Form->control('active', [
        'type' => 'select','label' => 'Status','empty' => 'Wszystkie',
        'options' => ['1' => 'Aktywne', '0' => 'Nieaktywne'],
        'default' => $active,'class' => 'form-select'
      ]) ?>

      <?= $this->Form->control('service', [
        'type' => 'select','label' => 'Typ','empty' => 'Produkty i usługi',
        'options' => ['1' => 'Tylko usługi', '0' => 'Tylko produkty'],
        'default' => $service,'class' => 'form-select'
      ]) ?>

      <?= $this->Form->control('unit_id', [
        'type' => 'select','label' => 'Jednostka','empty' => 'Wszystkie',
        'options' => $unitsMap,'default' => $unitId,'class' => 'form-select'
      ]) ?>

      <?= $this->Form->control('vat_id', [
        'type' => 'select','label' => 'Stawka VAT','empty' => 'Wszystkie',
        'options' => $vatsMap,'default' => $vatId,'class' => 'form-select'
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

<!-- Modal: Add/Edit produkt/usługa -->
<div class="modal fade" id="product-create" tabindex="-1" aria-hidden="true" data-mode="add" data-bs-scroll="true" data-bs-backdrop="false">
  <div class="modal-dialog modal-dialog-scrollable modal-xl">
    <div class="modal-content">

      <div class="modal-header border-bottom-0 pb-0 align-items-start">
        <div class="flex-grow-1 min-width-0">
          <h6 class="modal-title mb-0" id="product-modal-title">
            <i class="ri-price-tag-3-line me-1 text-primary"></i>
            <span id="product-modal-title-text">Dodaj produkt / usługę</span>
          </h6>
          <p class="text-muted small mb-0 mt-1" id="product-modal-subtitle">Wypełnij pola i kliknij Zapisz.</p>
        </div>
        <div class="d-flex align-items-center gap-1 ms-auto ps-2 flex-shrink-0">
          <button class="btn btn-sm btn-ghost-secondary rounded-circle" type="button"
            data-bs-toggle="collapse" data-bs-target="#product-help"
            title="Wskazówki" aria-label="Wskazówki" style="width:2rem;height:2rem;padding:0">
            <i class="ri-information-line fs-6"></i>
          </button>
          <button class="btn btn-sm btn-ghost-secondary rounded-circle" type="button"
            data-bs-toggle="modal" data-bs-target="#gtu-legend-modal"
            title="Legenda GTU" aria-label="Legenda GTU" style="width:2rem;height:2rem;padding:0">
            <i class="ri-book-open-line fs-6"></i>
          </button>
          <button type="button" class="btn-close ms-1" data-bs-dismiss="modal" aria-label="Zamknij"></button>
        </div>
      </div>

      <?= $this->Form->create(null, [
        'url'       => ['controller' => 'Products', 'action' => 'add'],
        'class'     => 'needs-validation',
        'novalidate'=> true,
        'id'        => 'product-form',
      ]) ?>
      <?= $this->Form->hidden('id',      ['id' => 'product-id']) ?>
      <?= $this->Form->hidden('_method', ['value' => 'POST', 'id' => 'product-method']) ?>
      <?= $this->Form->hidden('company_id', ['value' => $companyId]) ?>

      <div class="modal-body pt-2">

        <!-- Alert błędów zapisu -->
        <div id="prod-form-alert" class="alert alert-danger d-none py-2 px-3 mb-3" role="alert">
          <div class="d-flex align-items-start gap-2">
            <i class="ri-error-warning-line fs-5 flex-shrink-0 mt-1"></i>
            <div id="prod-form-alert-body" class="small"></div>
          </div>
        </div>

        <!-- Wskazówki (collapse) -->
        <div id="product-help" class="collapse mb-3">
          <div class="alert alert-info d-flex gap-3 mb-0 py-2">
            <i class="ri-lightbulb-flash-line fs-5 mt-1 flex-shrink-0"></i>
            <div class="small">
              <strong>Wskazówki:</strong>
              <ul class="mb-1 ps-3">
                <li><kbd>Tab</kbd> – przejście między polami, <kbd>Enter</kbd> – zapis formularza.</li>
                <li><strong>GTU</strong> ustaw wyłącznie gdy wymagane przez JPK_V7 / KSeF.</li>
                <li>Podgląd brutto liczy się automatycznie z wybranej stawki VAT.</li>
                <li>Przycisk <i class="ri-magic-line"></i> generuje kod SKU z nazwy produktu.</li>
              </ul>
            </div>
          </div>
        </div>

        <?php
        $extractVatRate = function (string $label): float {
          if (preg_match('/(\d+[.,]?\d*)\s*%?/', $label, $m)) {
            return (float)str_replace(',', '.', $m[1]);
          }
          return 0.0;
        };
        $gtuOptions = [
          ''       => '— brak GTU —',
          'GTU_01' => 'GTU_01 – Napoje alkoholowe',
          'GTU_02' => 'GTU_02 – Paliwa silnikowe',
          'GTU_03' => 'GTU_03 – Oleje opałowe / smarowe',
          'GTU_04' => 'GTU_04 – Wyroby tytoniowe',
          'GTU_05' => 'GTU_05 – Odpady',
          'GTU_06' => 'GTU_06 – Urządzenia elektroniczne',
          'GTU_07' => 'GTU_07 – Pojazdy oraz części',
          'GTU_08' => 'GTU_08 – Metale szlachetne i nieszlachetne',
          'GTU_09' => 'GTU_09 – Leki i wyroby medyczne',
          'GTU_10' => 'GTU_10 – Budynki, budowle i grunty',
          'GTU_11' => 'GTU_11 – Prawa do emisji CO₂',
          'GTU_12' => 'GTU_12 – Usługi niematerialne (IT, prawne, doradcze…)',
          'GTU_13' => 'GTU_13 – Transport i gospodarka magazynowa',
        ];
        ?>

        <!-- ── Sekcja 1: Identyfikacja ─────────────────────── -->
        <p class="text-uppercase text-muted fw-semibold small mb-2" style="letter-spacing:.06em">
          <i class="ri-barcode-box-line me-1"></i>Identyfikacja
        </p>
        <div class="row g-3 mb-3">
          <div class="col-lg-8">
            <label class="form-label fw-semibold">Nazwa <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required
              placeholder="np. Konsultacja IT / Papier A4 80g">
            <div class="invalid-feedback">Podaj nazwę produktu lub usługi.</div>
          </div>
          <div class="col-lg-4">
            <label class="form-label fw-semibold">Kod / SKU</label>
            <div class="input-group">
              <input type="text" name="code" id="code" class="form-control" maxlength="64"
                placeholder="SKU-001 (lub wygeneruj)">
              <button class="btn btn-outline-secondary" type="button" id="code-gen"
                data-bs-toggle="tooltip" title="Generuj kod z nazwy">
                <i class="ri-magic-line"></i>
              </button>
            </div>
          </div>
          <div class="col-lg-4">
            <label class="form-label fw-semibold">Typ</label>
            <div class="d-flex gap-3 mt-1">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="is_service" value="0"
                  id="type-product" checked>
                <label class="form-check-label" for="type-product">
                  <i class="ri-box-3-line me-1"></i>Produkt
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="is_service" value="1"
                  id="type-service">
                <label class="form-check-label" for="type-service">
                  <i class="ri-tools-line me-1"></i>Usługa
                </label>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <label class="form-label fw-semibold">Kod kreskowy</label>
            <input type="text" name="barcode" class="form-control" placeholder="EAN-13 / UPC">
          </div>
          <div class="col-lg-4 d-flex align-items-end">
            <div class="form-check form-switch mb-1">
              <input class="form-check-input" type="checkbox" role="switch"
                name="is_active" value="1" id="prod-is-active" checked>
              <label class="form-check-label" for="prod-is-active">Produkt aktywny</label>
            </div>
          </div>
        </div>

        <hr class="my-3">

        <!-- ── Sekcja 2: Cena ──────────────────────────────── -->
        <p class="text-uppercase text-muted fw-semibold small mb-2" style="letter-spacing:.06em">
          <i class="ri-money-dollar-circle-line me-1"></i>Cena i jednostka
        </p>
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="form-label fw-semibold">Jednostka miary <span class="text-danger">*</span></label>
            <select name="unit_id" class="form-select" required id="prod-unit-id">
              <?php foreach ($unitsMap as $uid => $uname): ?>
                <option value="<?= h($uid) ?>"><?= h($uname) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Waluta</label>
            <select name="currency" class="form-select" id="prod-currency">
              <?php foreach ($currencies as $cur => $curlabel): ?>
                <option value="<?= h($cur) ?>"><?= h($curlabel) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Cena netto <span class="text-danger">*</span></label>
            <input type="number" name="net_price" id="prod-net-price" step="0.01" min="0"
              class="form-control text-end" required placeholder="0.00">
            <div class="invalid-feedback">Podaj cenę netto.</div>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Stawka VAT <span class="text-danger">*</span></label>
            <select name="vat_id" id="prod-vat-id" class="form-select" required>
              <?php foreach ($vatsMap as $vid => $vlabel): ?>
                <option value="<?= h($vid) ?>"
                  data-rate="<?= h(number_format($extractVatRate((string)$vlabel), 2, '.', '')) ?>">
                  <?= h($vlabel) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted small">Podgląd:</span>
              <span class="badge bg-secondary-subtle text-secondary border" id="preview-vat">VAT: —</span>
              <span class="badge bg-primary-subtle text-primary border" id="preview-gross">Brutto: —</span>
            </div>
          </div>
        </div>

        <hr class="my-3">

        <!-- ── Sekcja 3: Klasyfikacja ─────────────────────── -->
        <p class="text-uppercase text-muted fw-semibold small mb-2" style="letter-spacing:.06em">
          <i class="ri-list-check-3 me-1"></i>Klasyfikacja
        </p>
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">PKWiU
              <i class="ri-question-line text-muted ms-1" data-bs-toggle="tooltip"
                title="Polska Klasyfikacja Wyrobów i Usług – wymagana w JPK_VAT."></i>
            </label>
            <input type="text" name="pkwiu" class="form-control" placeholder="np. 62.01.10.0">
          </div>
          <div class="col-md-8">
            <label class="form-label fw-semibold d-flex align-items-center gap-1">
              Kod GTU
              <i class="ri-question-line text-muted" data-bs-toggle="tooltip"
                title="Grupa Towarowo-Usługowa – wymagana w JPK_V7/KSeF gdy dotyczy."></i>
            </label>
            <select name="gtu_code" class="form-select" id="gtu-code">
              <?php foreach ($gtuOptions as $gval => $glabel): ?>
                <option value="<?= h($gval) ?>"><?= h($glabel) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text"><span class="text-muted">Opis: </span><span id="gtu-desc">—</span></div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Opis</label>
            <textarea name="description" rows="2" class="form-control"
              placeholder="Krótki opis dla celów wewnętrznych lub fakturowych"></textarea>
          </div>
        </div>

        <!-- ── Sekcja 4: KSeF / JPK (zwijana) ───────────── -->
        <div class="border rounded-2 overflow-hidden">
          <button type="button"
            class="btn btn-light d-flex align-items-center gap-2 w-100 rounded-0 border-0 px-3 py-2 text-start"
            data-bs-toggle="collapse" data-bs-target="#prod-ksef-collapse"
            aria-expanded="false" id="prod-ksef-toggle">
            <i class="ri-arrow-right-s-line text-muted" id="prod-ksef-arrow" style="transition:transform .2s; flex-shrink:0"></i>
            <span class="fw-semibold small">Pola KSeF / JPK</span>
            <span class="badge bg-secondary-subtle text-secondary ms-1">opcjonalne</span>
            <span class="ms-auto text-muted small d-none d-md-inline">GTIN, CN, PKOB, akcyza, procedura</span>
          </button>
          <div class="collapse" id="prod-ksef-collapse">
            <div class="p-3 bg-body-tertiary border-top">
              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label small fw-semibold">GTIN / EAN
                    <i class="ri-question-line text-muted ms-1" data-bs-toggle="tooltip"
                      title="Unikatowy kod handlowy produktu (EAN-8, EAN-13, UPC-A). Np. 5901234123457."></i>
                  </label>
                  <input type="text" name="gtin" class="form-control form-control-sm"
                    placeholder="5901234123457">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold">Kod CN
                    <i class="ri-question-line text-muted ms-1" data-bs-toggle="tooltip"
                      title="Nomenklatura Scalona – dla obrotu międzynarodowego (VAT-7, Intrastat)."></i>
                  </label>
                  <input type="text" name="cn_code" class="form-control form-control-sm"
                    placeholder="8471 30 00">
                </div>
                <div class="col-md-4">
                  <label class="form-label small fw-semibold">PKOB
                    <i class="ri-question-line text-muted ms-1" data-bs-toggle="tooltip"
                      title="Polska Klasyfikacja Obiektów Budowlanych – dla budynków i budowli."></i>
                  </label>
                  <input type="text" name="pkob" class="form-control form-control-sm"
                    placeholder="1110">
                </div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-4">
                  <label class="form-label small fw-semibold">Kwota akcyzy
                    <i class="ri-question-line text-muted ms-1" data-bs-toggle="tooltip"
                      title="Kwota podatku akcyzowego zawarta w cenie jednostkowej (paliwa, alkohol, tytoń)."></i>
                  </label>
                  <div class="input-group input-group-sm">
                    <input type="number" name="excise_amount" step="0.01" min="0"
                      class="form-control text-end" placeholder="0.00">
                    <span class="input-group-text">PLN</span>
                  </div>
                </div>
                <div class="col-md-8">
                  <label class="form-label small fw-semibold">Oznaczenie procedury FA(3)
                    <i class="ri-question-line text-muted ms-1" data-bs-toggle="tooltip"
                      title="Oznaczenia wymagane w KSeF / JPK_VAT dla określonych typów transakcji."></i>
                  </label>
                  <select name="procedure_marking" class="form-select form-select-sm">
                    <option value="">— brak —</option>
                    <option value="MR_T">MR_T – metoda kasowa (dostawa towarów)</option>
                    <option value="MR_UZ">MR_UZ – metoda kasowa (świadczenie usług)</option>
                    <option value="EE">EE – energia elektryczna, gaz, usługi dystrybucji</option>
                    <option value="TP">TP – podmioty powiązane (art. 32 ustawy VAT)</option>
                    <option value="TT_WNT">TT_WNT – WNT w transakcji trójstronnej uproszczonej</option>
                    <option value="TT_D">TT_D – dostawa w transakcji trójstronnej uproszczonej</option>
                    <option value="I_42">I_42 – WDT po imporcie w procedurze celnej 42</option>
                    <option value="I_63">I_63 – WDT po imporcie w procedurze celnej 63</option>
                    <option value="B_SPV">B_SPV – transfer bonu jednego przeznaczenia</option>
                    <option value="B_SPV_DOSTAWA">B_SPV_DOSTAWA – dostawa dot. bonu jednego przeznaczenia</option>
                    <option value="B_MPV_PROWIZJA">B_MPV_PROWIZJA – prowizja dot. bonu różnego przeznaczenia</option>
                    <option value="MPP">MPP – mechanizm podzielonej płatności</option>
                  </select>
                </div>
              </div>
              <div class="row g-3">
                <div class="col-12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                      name="is_attachment15" value="1" id="is-attachment15">
                    <label class="form-check-label small" for="is-attachment15">
                      Towar / usługa z <strong>załącznika nr 15</strong> ustawy o VAT
                      <span class="badge bg-warning-subtle text-warning border ms-1">MPP</span>
                      <i class="ri-question-line text-muted ms-1" data-bs-toggle="tooltip"
                        title="Zaznacz, gdy produkt/usługa figuruje w załączniku nr 15 – podlega obowiązkowemu mechanizmowi podzielonej płatności."></i>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /modal-body -->

      <div class="modal-footer border-top-0 pt-0">
        <div class="me-auto small text-muted d-none d-md-block">
          <i class="ri-shield-keyhole-line me-1"></i>
          Cena i VAT są kopiowane do faktury w momencie wystawienia.
        </div>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i>Anuluj
        </button>
        <button type="submit" class="btn btn-primary" id="product-submit-btn">
          <span class="spinner-border spinner-border-sm d-none me-1" id="prod-form-spinner" role="status"></span>
          <i class="ri-save-line me-1" id="prod-form-icon"></i>
          <span id="prod-form-label">Zapisz</span>
        </button>
      </div>

      <?= $this->Form->end() ?>
    </div>
  </div>
</div>

<!-- Modal: Legenda GTU -->
<div class="modal fade" id="gtu-legend-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="ri-book-open-line me-2"></i>Legenda GTU (skrót)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="bg-body-tertiary">
              <tr><th>Kod</th><th>Nazwa skrócona</th><th>Opis</th></tr>
            </thead>
            <tbody id="gtu-legend-tbody"></tbody>
          </table>
        </div>
        <p class="small text-muted mt-3 mb-0">
          Sprawdź zgodność przypisania GTU z aktualnymi wytycznymi JPK_V7/KSeF.
        </p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
      </div>
    </div>
  </div>
</div>

<style>
  /* Tabela */
  .pagination .page-link { min-width: 2rem; text-align: center; }
  .pagination .page-item.active .page-link { box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }
  .table-compact .table td,
  .table-compact .table th { padding-top: .35rem; padding-bottom: .35rem; }

  /* Modal produktu */
  #product-create #prod-currency { max-width: 90px; }
  #product-create #prod-net-price { text-align: right; }
  /* form między modal-header a modal-footer musi być flex-child żeby scrollable działało */
  #product-create .modal-dialog { max-height: calc(100vh - 3.5rem); margin-top: 1.75rem; margin-bottom: 1.75rem; }
  #product-create .modal-content { max-height: calc(100vh - 3.5rem); overflow: hidden; }
  #product-create #product-form { display: flex; flex-direction: column; flex: 1 1 auto; overflow: hidden; min-height: 0; }
  #product-create .modal-body { padding-top: .75rem; flex: 1 1 auto; overflow-y: scroll; min-height: 0; }
  #product-create .modal-body::-webkit-scrollbar { width: 8px; }
  #product-create .modal-body::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
  #product-create .modal-body::-webkit-scrollbar-thumb { background: #adb5bd; border-radius: 4px; }
  #product-create .modal-body::-webkit-scrollbar-thumb:hover { background: #6c757d; }
  #product-create hr { border-color: var(--bs-border-color-translucent); }
  #product-create #prod-ksef-collapse .bg-body-tertiary { background-color: var(--bs-tertiary-bg) !important; }
  #product-create .badge { vertical-align: middle; }

  /* Sekcje w modalu */
  #product-create p[style*="letter-spacing"] {
    border-bottom: 1px solid var(--bs-border-color-translucent);
    padding-bottom: .25rem;
  }
</style>

<script>
// === Podgląd brutto + GTU opis (uruchamia się przed głównym skryptem) ===
document.addEventListener('DOMContentLoaded', () => {
  const netInput  = document.getElementById('prod-net-price');
  const vatSelect = document.getElementById('prod-vat-id');
  const curSelect = document.getElementById('prod-currency');
  const badgeVat  = document.getElementById('preview-vat');
  const badgeGross= document.getElementById('preview-gross');
  const gtuSelect = document.getElementById('gtu-code');
  const gtuDescEl = document.getElementById('gtu-desc');

  const GTU_LEGEND = {
    GTU_01: {name:'Napoje alkoholowe',    desc:'Dostawa napojów alkoholowych o określonej zawartości alkoholu.'},
    GTU_02: {name:'Paliwa silnikowe',     desc:'Paliwa, gaz i inne wyroby energetyczne z art. 103 ust. 5aa uVAT.'},
    GTU_03: {name:'Oleje opałowe/smarowe',desc:'Wybrane oleje i środki smarowe (wg zał. 13 uVAT).'},
    GTU_04: {name:'Wyroby tytoniowe',     desc:'Tytoń, papierosy, susz tytoniowy (art. 99a uVAT).'},
    GTU_05: {name:'Odpady',               desc:'Towary z poz. 79–91 zał. 15 uVAT (m.in. złom, makulatura).'},
    GTU_06: {name:'Urządzenia elektroniczne', desc:'Konsole, laptopy, tablety, telefony (poz. 7–9, 59–63 zał. 15).'},
    GTU_07: {name:'Pojazdy i części',     desc:'Pojazdy CN 8701–8708 oraz wybrane części samochodowe.'},
    GTU_08: {name:'Metale szlachetne',    desc:'Złoto, srebro, platyna i stopy (wg zał. 12 i 15 uVAT).'},
    GTU_09: {name:'Leki i wyroby med.',   desc:'Produkty lecznicze, leki, wyroby medyczne (ustawa refundacyjna).'},
    GTU_10: {name:'Budynki i grunty',     desc:'Budynki, budowle i grunty – dostawa i najem.'},
    GTU_11: {name:'Uprawnienia CO₂',      desc:'Przenoszenie uprawnień do emisji gazów cieplarnianych.'},
    GTU_12: {name:'Usługi niematerialne', desc:'Doradcze, zarządcze, prawne, reklamowe, IT, szkoleniowe, B+R.'},
    GTU_13: {name:'Transport i magazyn',  desc:'Usługi transportowe i gospodarka magazynowa.'},
  };

  // Wypełnij legendę GTU
  const legendTbody = document.getElementById('gtu-legend-tbody');
  if (legendTbody) {
    legendTbody.innerHTML = Object.entries(GTU_LEGEND).map(([k, it]) =>
      `<tr><td><code>${k}</code></td><td class="fw-semibold">${it.name}</td><td class="text-muted small">${it.desc}</td></tr>`
    ).join('');
  }

  // Opis GTU pod selectem
  function refreshGtuDesc() {
    if (!gtuDescEl) return;
    const code = gtuSelect?.value || '';
    gtuDescEl.textContent = code && GTU_LEGEND[code]
      ? `${GTU_LEGEND[code].name} – ${GTU_LEGEND[code].desc}`
      : '—';
  }
  gtuSelect?.addEventListener('change', refreshGtuDesc);
  refreshGtuDesc();

  // Podgląd brutto
  function round2(n) { return Math.round((n + Number.EPSILON) * 100) / 100; }
  function money(n)  { return Number.isNaN(n) ? '—' : n.toLocaleString('pl-PL', {minimumFractionDigits:2, maximumFractionDigits:2}); }
  function getVatRate() {
    const opt = vatSelect?.selectedOptions?.[0];
    const rate = parseFloat(opt?.getAttribute('data-rate') || '0');
    return Number.isFinite(rate) ? rate : 0;
  }
  function refreshGross() {
    const net   = parseFloat((netInput?.value || '0').replace(',', '.')) || 0;
    const vat   = getVatRate();
    const gross = round2(net * (1 + vat / 100));
    const cur   = curSelect?.value || '';
    if (badgeVat)   badgeVat.textContent   = `VAT: ${vat.toFixed(2)}%`;
    if (badgeGross) badgeGross.textContent = `Brutto: ${money(gross)} ${cur}`;
  }

  ['input', 'change', 'blur'].forEach(ev => netInput?.addEventListener(ev, refreshGross));
  vatSelect?.addEventListener('change', refreshGross);
  curSelect?.addEventListener('change', refreshGross);
  refreshGross();

  // Po otwarciu modalu: fokus + przelicz
  document.getElementById('product-create')?.addEventListener('shown.bs.modal', () => {
    document.getElementById('product-form')?.querySelector('input[name="name"]')?.focus();
    refreshGross();
    refreshGtuDesc();
  });
});
</script>

<!-- Toaster -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:1080">
  <div id="toast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toast-body"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<!-- Modal: Postęp eksportu (identyczny jak u kontrahentów) -->
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

<?php // scripts – tabela, filtry, edycja, eksport ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // tooltips – container:body żeby nie uciekały wewnątrz modalu
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el =>
    new bootstrap.Tooltip(el, { container: 'body', trigger: 'hover focus' })
  );
  // CSRF
  const CSRF_TOKEN = document.querySelector('meta[name="csrfToken"]')?.getAttribute('content') || '';

  // toast
  const toastEl = document.getElementById('toast');
  const toastBody = document.getElementById('toast-body');
  const toast = new bootstrap.Toast(toastEl, { delay: 1500 });

  // density toggle
  const table = document.getElementById('products-table');
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
  document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(btn.dataset.copy);
        toastBody.textContent = 'Skopiowano: ' + btn.dataset.copy; toast.show();
      } catch { toastBody.textContent = 'Nie udało się skopiować'; toast.show(); }
    });
  });

  // live search (debounce -> 400ms)
  const search = document.getElementById('live-search');
  if (search) {
    let t;
    const baseUrl = search.dataset.currentUrl;
    const current = new URL(window.location.href);
    const active   = current.searchParams.get('active')   ?? '';
    const service  = current.searchParams.get('service')  ?? '';
    const unit_id  = current.searchParams.get('unit_id')  ?? '';
    const vat_id   = current.searchParams.get('vat_id')   ?? '';
    const limit    = current.searchParams.get('limit')    ?? '';
    search.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        const url = new URL(baseUrl, window.location.origin);
        if (search.value.trim() !== '') url.searchParams.set('q', search.value.trim());
        if (active   !== '') url.searchParams.set('active',  active);
        if (service  !== '') url.searchParams.set('service', service);
        if (unit_id  !== '') url.searchParams.set('unit_id', unit_id);
        if (vat_id   !== '') url.searchParams.set('vat_id',  vat_id);
        if (limit    !== '') url.searchParams.set('limit',   limit);
        window.location.href = url.toString();
      }, 400);
    });
  }

  // modal + formularz
  const modalEl    = document.getElementById('product-create');
  const modal      = new bootstrap.Modal(modalEl);
  const form       = document.getElementById('product-form');
  const titleEl    = document.getElementById('product-modal-title-text');
  const subtitleEl = document.getElementById('product-modal-subtitle');
  const idField    = document.getElementById('product-id');
  const methodFld  = document.getElementById('product-method');
  const codeGen    = document.getElementById('code-gen');
  const submitBtn  = document.getElementById('product-submit-btn');
  const submitSpinner = document.getElementById('prod-form-spinner');
  const submitIcon    = document.getElementById('prod-form-icon');
  const submitLabel   = document.getElementById('prod-form-label');
  const formAlert     = document.getElementById('prod-form-alert');
  const formAlertBody = document.getElementById('prod-form-alert-body');

  function showFormError(errors) {
    const fieldLabels = {
      name: 'Nazwa', net_price: 'Cena netto', vat_id: 'Stawka VAT',
      unit_id: 'Jednostka miary', currency: 'Waluta', code: 'Kod / symbol',
      pkwiu: 'PKWiU', gtu_code: 'Kod GTU', barcode: 'Kod kreskowy',
      gtin: 'GTIN', cn_code: 'Kod CN', excise_amount: 'Podatek akcyzowy',
      company_id: 'Produkt',
    };
    function translateMsg(text) {
      return text
        .replace(/This value is already in use/gi, 'Ta wartość jest już zajęta')
        .replace(/This field cannot be left empty/gi, 'To pole jest wymagane')
        .replace(/The provided value is invalid/gi, 'Nieprawidłowa wartość')
        .replace(/is required/gi, 'jest wymagane');
    }
    let html = '';
    if (errors && typeof errors === 'object') {
      const lines = Object.entries(errors).map(([field, msg]) => {
        const text = typeof msg === 'string' ? msg : Object.values(msg).flat().join(', ');
        const translated = translateMsg(text);
        if (field === '_') return `<li>${translated}</li>`;
        const label = fieldLabels[field] || null;
        return label ? `<li><strong>${label}:</strong> ${translated}</li>` : `<li>${translated}</li>`;
      });
      html = lines.length === 1
        ? `<span>${lines[0].replace(/<\/?li>/g, '')}</span>`
        : `<ul class="mb-0 ps-3">${lines.join('')}</ul>`;
    }
    formAlertBody.innerHTML = html || 'Nie udało się zapisać. Sprawdź formularz.';
    formAlert.classList.remove('d-none');
    formAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function hideFormError() {
    formAlert.classList.add('d-none');
    formAlertBody.innerHTML = '';
  }

  // Generator SKU
  codeGen?.addEventListener('click', () => {
    const name = (form.querySelector('input[name="name"]')?.value || '').trim();
    const base = (name || 'ITEM').toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g,'').slice(0, 16);
    const rand = Math.random().toString(36).slice(2, 6).toUpperCase();
    form.querySelector('#code').value = [base, rand].filter(Boolean).join('-');
  });

  // Pomocnicze
  function setVal(selector, value) {
    const el = form?.querySelector(selector);
    if (el) el.value = value ?? '';
  }
  function setChecked(selector, on) {
    const el = form?.querySelector(selector);
    if (el) el.checked = !!Number(on);
  }
  function setRadio(name, value) {
    form?.querySelectorAll(`input[name="${name}"]`).forEach(r => {
      r.checked = (r.value === String(value ?? '0'));
    });
  }
  function setBtnLoading(loading) {
    if (!submitBtn) return;
    submitBtn.disabled = loading;
    submitSpinner?.classList.toggle('d-none', !loading);
    submitIcon?.classList.toggle('d-none', loading);
    if (submitLabel) submitLabel.textContent = loading ? 'Zapisuję…' : 'Zapisz';
  }

  // Obrót chevron sekcji KSeF
  document.getElementById('prod-ksef-collapse')?.addEventListener('show.bs.collapse', () => {
    document.getElementById('prod-ksef-arrow')?.style.setProperty('transform', 'rotate(90deg)');
  });
  document.getElementById('prod-ksef-collapse')?.addEventListener('hide.bs.collapse', () => {
    document.getElementById('prod-ksef-arrow')?.style.setProperty('transform', 'rotate(0deg)');
  });

  // Reset → tryb ADD
  function resetFormToAdd() {
    form.reset();
    idField.value   = '';
    methodFld.value = 'POST';
    form.setAttribute('action', '<?= $this->Url->build(['controller' => 'Products', 'action' => 'add']) ?>');
    modalEl.dataset.mode    = 'add';
    titleEl.textContent     = 'Dodaj produkt / usługę';
    if (subtitleEl) subtitleEl.textContent = 'Wypełnij pola i kliknij Zapisz.';
    form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
    form.querySelectorAll('.server-invalid-feedback').forEach(i => i.remove());
    form.classList.remove('was-validated');
    hideFormError();
    setBtnLoading(false);
    // radio na "Produkt" domyślnie
    setRadio('is_service', '0');
    // zwiń sekcję KSeF
    const ksefEl = document.getElementById('prod-ksef-collapse');
    if (ksefEl) bootstrap.Collapse.getOrCreateInstance(ksefEl, { toggle: false }).hide();
  }
  document.querySelectorAll('[data-bs-target="#product-create"]').forEach(btn => {
    btn.addEventListener('click', resetFormToAdd);
  });

  // Klik edycji (ładowanie danych)
  document.querySelectorAll('.js-edit').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id; if (!id) return;
      resetFormToAdd();
      titleEl.textContent = 'Edytuj produkt / usługę';
      if (subtitleEl) subtitleEl.textContent = btn.dataset.name || ('ID: ' + id);
      modalEl.dataset.mode = 'edit';
      idField.value   = id;
      methodFld.value = 'PUT';
      form.setAttribute('action', '<?= $this->Url->build(['controller' => 'Products', 'action' => 'edit']) ?>/' + id);

      // Pokaż modal od razu (skeleton)
      modal.show();

      try {
        const res  = await fetch('<?= $this->Url->build(['controller' => 'Products', 'action' => 'viewJson']) ?>/' + id, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.success) throw new Error('Brak danych');
        const p = data.product || {};

        // Podstawowe
        setVal('input[name="name"]',    p.name);
        setVal('input[name="code"]',    p.code);
        setVal('input[name="barcode"]', p.barcode);
        setVal('input[name="pkwiu"]',   p.pkwiu);
        const desc = form.querySelector('textarea[name="description"]');
        if (desc) desc.value = p.description ?? '';

        // Cena
        setVal('input[name="net_price"]',  p.net_price);
        setVal('select[name="currency"]',  p.currency || 'PLN');
        setVal('select[name="unit_id"]',   p.unit_id);
        setVal('select[name="vat_id"]',    p.vat_id);

        // Klasyfikacja
        setVal('select[name="gtu_code"]', p.gtu_code);

        // Typ (radio) + aktywność (switch)
        setRadio('is_service', p.is_service ? '1' : '0');
        setChecked('input[name="is_active"]', p.is_active);

        // Pola KSeF
        setVal('input[name="gtin"]',              p.gtin);
        setVal('input[name="cn_code"]',           p.cn_code);
        setVal('input[name="pkob"]',              p.pkob);
        setVal('input[name="excise_amount"]',     p.excise_amount != null ? p.excise_amount : '');
        setVal('select[name="procedure_marking"]', p.procedure_marking);
        setChecked('input[name="is_attachment15"]', p.is_attachment15);

        // Odśwież podgląd brutto i opis GTU
        document.getElementById('prod-vat-id')?.dispatchEvent(new Event('change'));
        document.getElementById('prod-net-price')?.dispatchEvent(new Event('input'));
        document.getElementById('gtu-code')?.dispatchEvent(new Event('change'));

        // Jeśli któreś pole KSeF jest wypełnione – rozwiń sekcję
        if (p.gtin || p.cn_code || p.pkob || p.excise_amount || p.procedure_marking || p.is_attachment15) {
          const ksefEl = document.getElementById('prod-ksef-collapse');
          if (ksefEl) bootstrap.Collapse.getOrCreateInstance(ksefEl, { toggle: false }).show();
        }

      } catch (err) {
        toastBody.textContent = 'Nie udało się wczytać danych produktu. Odśwież stronę i spróbuj ponownie.'; toast.show();
        console.error('Product load error:', err);
        bootstrap.Modal.getInstance(modalEl)?.hide();
      }
    });
  });

  // Submit (AJAX)
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) { e.stopPropagation(); form.classList.add('was-validated'); return; }

    const mode   = modalEl.dataset.mode || 'add';
    const action = form.getAttribute('action');
    const fd     = new FormData(form);

    setBtnLoading(true);

    try {
      const res  = await fetch(action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      });
      const data = await res.json();

      form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
      form.querySelectorAll('.server-invalid-feedback').forEach(i => i.remove());

      if (data.success) {
        hideFormError();
        toastBody.textContent = data.message || (mode === 'add' ? 'Dodano produkt/usługę.' : 'Zapisano zmiany.'); toast.show();
        bootstrap.Modal.getInstance(modalEl)?.hide();
        window.location.reload();
      } else {
        showFormError(data.errors || { '_': data.message || 'Nie udało się zapisać.' });
        setBtnLoading(false);
      }
    } catch (err) {
      setBtnLoading(false);
      showFormError({ '_': 'Błąd połączenia z serwerem. Spróbuj ponownie.' });
      console.error('Product save error:', err);
    }
  });

  // === Eksport CSV (stream + progress) ===
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
      const res = await fetch(exportBtn.href, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal });
      if (!res.ok) throw new Error('Błąd pobierania (' + res.status + ')');

      const total = Number(res.headers.get('Content-Length')) || 0;
      const disp  = res.headers.get('Content-Disposition');
      const filename = parseFilename(disp) || 'produkty.csv';

      const reader = res.body.getReader();
      const chunks = [];
      let received = 0;

      exportStat.textContent = total ? 'Pobieranie pliku…' : 'Pobieranie (rozmiar nieznany)…';

      if (!total) {
        let fake = 0;
        const t = setInterval(() => { if (fake < 90) { fake += 2; setProgress(fake); } }, 180);
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          chunks.push(value);
        }
        clearInterval(t);
        setProgress(95);
      } else {
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

      const url = URL.createObjectURL(blob);
      const a   = document.createElement('a');
      a.href = url; a.download = filename;
      document.body.appendChild(a); a.click(); a.remove();
      URL.revokeObjectURL(url);

      toastBody.textContent = 'Eksport zakończony: ' + filename; toast.show();
      setTimeout(() => { try { exportModal.hide(); } catch {} }, 800);

    } catch (err) {
      if (signal.aborted) {
        exportStat.textContent = 'Anulowano.'; setProgress(0);
        toastBody.textContent = 'Eksport anulowany.'; toast.show();
      } else {
        exportStat.textContent = 'Wystąpił błąd.';
        exportBar.classList.remove('progress-bar-animated');
        toastBody.textContent = 'Błąd eksportu: ' + (err?.message || 'nieznany'); toast.show();
      }
    } finally {
      exportCancel.disabled = true; exportAbortCtrl = null;
    }
  });

  exportCancel?.addEventListener('click', () => {
    if (exportAbortCtrl) { exportCancel.disabled = true; exportAbortCtrl.abort(); }
  });

  // === Modal: Podgląd produktu/usługi ===
  (function(){
    const modalEl = document.getElementById('productViewModal');
    if (!modalEl) return;
    const bsModal = new bootstrap.Modal(modalEl);

    const elName        = document.getElementById('pv-name');
    const elType        = document.getElementById('pv-type');
    const elStatus      = document.getElementById('pv-status');
    const elCode        = document.getElementById('pv-code');
    const elBarcode     = document.getElementById('pv-barcode');
    const elUnit        = document.getElementById('pv-unit');
    const elVat         = document.getElementById('pv-vat');
    const elPrice       = document.getElementById('pv-price');
    const elCurrency    = document.getElementById('pv-currency');
    const elPkwiu       = document.getElementById('pv-pkwiu');
    const elGtu         = document.getElementById('pv-gtu');
    const elGtin        = document.getElementById('pv-gtin');
    const elCn          = document.getElementById('pv-cn');
    const elPkob        = document.getElementById('pv-pkob');
    const elExcise      = document.getElementById('pv-excise');
    const elProcedure   = document.getElementById('pv-procedure');
    const elAttach15    = document.getElementById('pv-attach15');
    const elDesc        = document.getElementById('pv-desc');
    const elDescRow     = document.getElementById('pv-desc-row');
    const elCreated     = document.getElementById('pv-created');
    const elEditBtn     = document.getElementById('pv-edit-btn');
    const elLoader      = document.getElementById('pv-loader');
    const elBody        = document.getElementById('pv-body');

    const VIEWJSON_URL = '<?= $this->Url->build(['controller' => 'Products', 'action' => 'viewJson']) ?>/';

    function dash(v) { return (v !== null && v !== undefined && v !== '') ? v : '—'; }
    function money(v, cur) {
      const n = parseFloat(v);
      if (isNaN(n)) return '—';
      return n.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (cur || 'PLN');
    }

    document.querySelectorAll('.js-product-view').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;

        // Reset i pokaż skeleton
        if (elLoader) elLoader.classList.remove('d-none');
        if (elBody)   elBody.classList.add('d-none');
        bsModal.show();

        try {
          const res  = await fetch(VIEWJSON_URL + encodeURIComponent(id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
          const data = await res.json();
          if (!data.success) throw new Error(data.message || 'Błąd');
          const p = data.product;

          if (elName)   elName.textContent  = p.name || '—';
          if (elType)   elType.innerHTML    = p.is_service
            ? '<span class="badge bg-info-transparent"><i class="ri-tools-line me-1"></i>Usługa</span>'
            : '<span class="badge bg-secondary-transparent"><i class="ri-box-3-line me-1"></i>Produkt</span>';
          if (elStatus) elStatus.innerHTML  = p.is_active
            ? '<span class="badge bg-success-transparent"><i class="ri-check-line me-1"></i>Aktywny</span>'
            : '<span class="badge bg-danger-transparent"><i class="ri-close-line me-1"></i>Nieaktywny</span>';
          if (elCode)     elCode.textContent     = dash(p.code);
          if (elBarcode)  elBarcode.textContent  = dash(p.barcode);
          if (elUnit)     elUnit.textContent     = dash(data.unit_name);
          if (elVat)      elVat.textContent      = dash(data.vat_label);
          if (elPrice)    elPrice.textContent    = money(p.net_price, p.currency);
          if (elCurrency) elCurrency.textContent = dash(p.currency);
          if (elPkwiu)    elPkwiu.textContent    = dash(p.pkwiu);
          if (elGtu)      elGtu.textContent      = dash(p.gtu_code);
          if (elGtin)     elGtin.textContent     = dash(p.gtin);
          if (elCn)       elCn.textContent       = dash(p.cn_code);
          if (elPkob)     elPkob.textContent     = dash(p.pkob);
          if (elExcise)   elExcise.textContent   = p.excise_amount !== null && p.excise_amount !== undefined ? p.excise_amount : '—';
          if (elProcedure)elProcedure.textContent= dash(p.procedure_marking);
          if (elAttach15) elAttach15.textContent = p.is_attachment15 ? 'Tak' : 'Nie';
          if (elDesc) {
            if (p.description) {
              elDesc.textContent = p.description;
              elDescRow?.classList.remove('d-none');
            } else {
              elDescRow?.classList.add('d-none');
            }
          }
          if (elCreated) elCreated.textContent = p.created ? p.created.substring(0, 10) : '—';
          if (elEditBtn) {
            elEditBtn.dataset.id   = id;
            elEditBtn.dataset.name = p.name || '';
          }

          if (elLoader) elLoader.classList.add('d-none');
          if (elBody)   elBody.classList.remove('d-none');
        } catch (e) {
          if (elLoader) elLoader.innerHTML = '<div class="alert alert-danger m-3">Nie udało się wczytać danych produktu.</div>';
        }
      });
    });

    // Przycisk "Edytuj" w modalu — otwiera modal edycji
    elEditBtn?.addEventListener('click', () => {
      bsModal.hide();
      setTimeout(() => {
        document.querySelector(`.js-edit[data-id="${elEditBtn.dataset.id}"]`)?.click();
      }, 300);
    });
  })();

  // === Import z faktury24.com ===
  (function(){
    const btnOpen    = document.getElementById('btn-import-f24-products');
    const modalEl    = document.getElementById('importF24ProductsModal');
    if (!btnOpen || !modalEl) return;
    const bsModal    = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });

    const elLoading  = document.getElementById('pf24-loading');
    const elError    = document.getElementById('pf24-error');
    const elErrorMsg = document.getElementById('pf24-error-msg');
    const elResults  = document.getElementById('pf24-results');
    const tbody      = document.getElementById('pf24-tbody');
    const checkAll   = document.getElementById('pf24-check-all');
    const selBadge   = document.getElementById('pf24-selected-badge');
    const countLabel = document.getElementById('pf24-count-label');
    const alreadyLbl = document.getElementById('pf24-already-label');
    const btnImport  = document.getElementById('pf24-btn-import');
    const btnImpLbl  = document.getElementById('pf24-btn-import-label');
    const progWrap   = document.getElementById('pf24-import-progress');
    const progBar    = document.getElementById('pf24-progress-bar');
    const progLabel  = document.getElementById('pf24-progress-label');
    const progPct    = document.getElementById('pf24-progress-pct');
    const resultDiv  = document.getElementById('pf24-import-result');

    const FETCH_URL  = '<?= $this->Url->build(['controller' => 'Products', 'action' => 'importFetch']) ?>';
    const IMPORT_URL = '<?= $this->Url->build(['controller' => 'Products', 'action' => 'importBatch']) ?>';

    let allRows = [];

    function showPanel(el) {
      ['pf24-loading','pf24-error','pf24-results'].forEach(id => document.getElementById(id)?.classList.add('d-none'));
      el?.classList.remove('d-none');
    }

    function escHtml(s) {
      return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function updateSelected() {
      const checked = tbody.querySelectorAll('input[type="checkbox"]:checked').length;
      selBadge.textContent = checked + ' zaznaczonych';
      if (btnImport) {
        btnImport.disabled = checked === 0;
        if (btnImpLbl) btnImpLbl.textContent = checked > 0 ? 'Importuj zaznaczone (' + checked + ')' : 'Importuj zaznaczone';
      }
      const total = tbody.querySelectorAll('input[type="checkbox"]:not(:disabled)').length;
      checkAll.indeterminate = checked > 0 && checked < total;
      checkAll.checked = total > 0 && checked === total;
    }

    function formatPrice(p) {
      const n = parseFloat(p);
      if (isNaN(n)) return '—';
      return n.toLocaleString('pl-PL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' zł';
    }

    function renderRows(rows) {
      allRows = rows;
      const alreadyCount = rows.filter(r => r.already_imported).length;
      countLabel.textContent = rows.length + ' pozycji';
      alreadyLbl.textContent = alreadyCount > 0 ? '(' + alreadyCount + ' już zaimportowanych)' : '';

      tbody.innerHTML = rows.map((r, i) => {
        const typeIcon = r.is_service
          ? '<span class="badge bg-info-transparent text-info"><i class="ri-tools-line me-1"></i>Usługa</span>'
          : '<span class="badge bg-primary-transparent text-primary"><i class="ri-box-3-line me-1"></i>Towar</span>';
        const vatBadge = r.vat_label
          ? '<span class="badge bg-light text-dark">' + escHtml(r.vat_label) + '</span>'
          : (r.vat_rate !== '' && r.vat_rate !== null ? '<span class="badge bg-light text-dark">' + escHtml(r.vat_rate) + '%</span>' : '');
        const statusBadge = r.already_imported
          ? '<span class="badge bg-secondary-transparent text-secondary">Już w bazie</span>'
          : '<span class="badge bg-success-transparent text-success">Nowy</span>';
        const meta = [
          r.unit_name ? escHtml(r.unit_name) : '',
        ].filter(Boolean).join(' · ');

        return `<tr class="${r.already_imported ? 'already-imported' : ''}" data-idx="${i}">
          <td class="text-center">
            <input type="checkbox" class="form-check-input pf24-row-check" data-idx="${i}"
              ${r.already_imported ? 'disabled title="Już istnieje w bazie"' : ''}>
          </td>
          <td>
            <div class="fw-semibold">${escHtml(r.name)}</div>
            ${meta ? '<div class="small text-muted">' + meta + '</div>' : ''}
            ${r.description ? '<div class="small text-muted fst-italic">' + escHtml(r.description.substring(0,80)) + (r.description.length > 80 ? '…' : '') + '</div>' : ''}
          </td>
          <td>${typeIcon}</td>
          <td class="text-end">${formatPrice(r.net_price)}</td>
          <td class="text-center">${vatBadge}</td>
          <td class="text-center">${statusBadge}</td>
        </tr>`;
      }).join('');

      tbody.querySelectorAll('.pf24-row-check').forEach(cb => cb.addEventListener('change', updateSelected));
      updateSelected();
    }

    checkAll?.addEventListener('change', () => {
      tbody.querySelectorAll('.pf24-row-check:not(:disabled)').forEach(cb => cb.checked = checkAll.checked);
      updateSelected();
    });

    async function fetchProducts() {
      showPanel(elLoading);
      btnImport?.classList.add('d-none');
      if (resultDiv) { resultDiv.innerHTML = ''; resultDiv.classList.add('d-none'); }
      if (progWrap) progWrap.classList.add('d-none');
      try {
        const resp = await fetch(FETCH_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await resp.json();
        if (!data.success) {
          elErrorMsg.textContent = data.error || 'Nieznany błąd.';
          showPanel(elError);
          return;
        }
        if (!data.rows || data.rows.length === 0) {
          elErrorMsg.textContent = 'Stary system nie zwrócił żadnych towarów/usług dla NIP tej firmy.';
          showPanel(elError);
          return;
        }
        renderRows(data.rows);
        showPanel(elResults);
        btnImport?.classList.remove('d-none');
      } catch (e) {
        elErrorMsg.textContent = 'Błąd połączenia: ' + (e?.message || e);
        showPanel(elError);
      }
    }

    btnOpen.addEventListener('click', () => {
      bsModal.show();
      fetchProducts();
    });

    btnImport?.addEventListener('click', async () => {
      const selected = [];
      tbody.querySelectorAll('.pf24-row-check:checked').forEach(cb => {
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
        progLabel.textContent = 'Importowanie ' + selected.length + ' pozycji…';
      }

      let fake = 0;
      const t = setInterval(() => {
        if (fake < 88) { fake += 3; progBar.style.width = fake + '%'; progPct.textContent = fake + '%'; }
      }, 120);

      try {
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

        if (data.success && data.imported > 0) {
          tbody.querySelectorAll('.pf24-row-check:checked').forEach(cb => {
            cb.closest('tr')?.classList.add('already-imported');
            cb.checked = false;
            cb.disabled = true;
          });
          tbody.querySelectorAll('tr.already-imported td:last-child').forEach(td => {
            if (td.innerHTML.includes('Nowy')) td.innerHTML = '<span class="badge bg-secondary-transparent text-secondary">Już w bazie</span>';
          });
          updateSelected();
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

    modalEl.addEventListener('hidden.bs.modal', () => {
      allRows = [];
      if (tbody) tbody.innerHTML = '';
    });
  })();
});
</script>

<!-- Modal: Import z faktury24.com (produkty/usługi) -->
<div class="modal fade" id="importF24ProductsModal" tabindex="-1" aria-labelledby="importF24ProductsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <div class="avatar bg-info-transparent rounded" style="width:2.5rem;height:2.5rem;display:flex;align-items:center;justify-content:center;">
            <i class="ri-download-cloud-2-line fs-18 text-info"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0" id="importF24ProductsModalLabel">Import towarów/usług z faktury24.com</h5>
            <small class="text-muted">Pozycje przypisane do NIP Twojej firmy w starym systemie</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>

      <div class="modal-body p-0">
        <div id="pf24-loading" class="d-flex flex-column align-items-center justify-content-center py-5 gap-3">
          <div class="spinner-border text-info" role="status" style="width:2.5rem;height:2.5rem;"></div>
          <div class="text-muted">Pobieranie towarów/usług z faktury24.com…</div>
        </div>

        <div id="pf24-error" class="d-none p-4">
          <div class="alert alert-danger mb-0" id="pf24-error-msg"></div>
        </div>

        <div id="pf24-results" class="d-none">
          <div class="px-4 pt-3 pb-2 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
              <span class="fw-semibold" id="pf24-count-label">0 pozycji</span>
              <span class="text-muted ms-2 small" id="pf24-already-label"></span>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="pf24-check-all">
                <label class="form-check-label small" for="pf24-check-all">Zaznacz wszystkich nieimportowanych</label>
              </div>
              <span class="badge bg-primary-transparent text-primary" id="pf24-selected-badge">0 zaznaczonych</span>
            </div>
          </div>

          <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
            <table class="table table-hover table-sm align-middle mb-0">
              <thead class="table-light sticky-top">
                <tr>
                  <th style="width:2.5rem;"></th>
                  <th>Nazwa</th>
                  <th style="width:7rem;">Typ</th>
                  <th style="width:9rem;" class="text-end">Cena netto</th>
                  <th style="width:5rem;" class="text-center">VAT</th>
                  <th style="width:8rem;" class="text-center">Status</th>
                </tr>
              </thead>
              <tbody id="pf24-tbody"></tbody>
            </table>
          </div>

          <div id="pf24-import-progress" class="d-none px-4 py-3 border-top">
            <div class="d-flex justify-content-between mb-1">
              <small class="text-muted" id="pf24-progress-label">Importowanie…</small>
              <small class="text-muted" id="pf24-progress-pct">0%</small>
            </div>
            <div class="progress" style="height:.5rem;">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" id="pf24-progress-bar" style="width:0%"></div>
            </div>
          </div>

          <div id="pf24-import-result" class="d-none px-4 py-3 border-top"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
        <button type="button" class="btn btn-info d-none" id="pf24-btn-import" disabled>
          <i class="ri-download-cloud-2-line me-1"></i>
          <span id="pf24-btn-import-label">Importuj zaznaczone</span>
        </button>
      </div>
    </div>
  </div>
</div>
<style>
  #importF24ProductsModal .sticky-top { top: 0; z-index: 1; }
  #importF24ProductsModal tbody tr.already-imported td { opacity: .5; }
</style>

<!-- Modal: Podgląd produktu/usługi -->
<div class="modal fade" id="productViewModal" tabindex="-1" aria-labelledby="productViewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0" id="productViewModalLabel">
            <span id="pv-name">…</span>
          </h5>
          <div class="d-flex align-items-center gap-2 mt-1" id="pv-badges">
            <span id="pv-type"></span>
            <span id="pv-status"></span>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
      </div>
      <div class="modal-body">
        <!-- Skeleton loader -->
        <div id="pv-loader" class="d-flex align-items-center gap-2 py-4 justify-content-center">
          <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
          <span class="text-muted">Wczytywanie…</span>
        </div>
        <!-- Treść -->
        <div id="pv-body" class="d-none">
          <div class="row g-3">
            <!-- Kolumna 1: cena i jednostka -->
            <div class="col-md-6">
              <div class="card border-0 bg-body-secondary h-100">
                <div class="card-body">
                  <h6 class="text-muted fw-normal mb-3"><i class="ri-price-tag-3-line me-1"></i>Cena i sprzedaż</h6>
                  <dl class="row mb-0 small">
                    <dt class="col-6 text-muted fw-normal">Cena netto</dt>
                    <dd class="col-6 fw-semibold mb-2" id="pv-price">—</dd>
                    <dt class="col-6 text-muted fw-normal">Waluta</dt>
                    <dd class="col-6 mb-2" id="pv-currency">—</dd>
                    <dt class="col-6 text-muted fw-normal">Stawka VAT</dt>
                    <dd class="col-6 mb-2" id="pv-vat">—</dd>
                    <dt class="col-6 text-muted fw-normal">Jednostka miary</dt>
                    <dd class="col-6 mb-2" id="pv-unit">—</dd>
                    <dt class="col-6 text-muted fw-normal">Kod</dt>
                    <dd class="col-6 mb-2" id="pv-code">—</dd>
                    <dt class="col-6 text-muted fw-normal">Barcode</dt>
                    <dd class="col-6 mb-0" id="pv-barcode">—</dd>
                  </dl>
                </div>
              </div>
            </div>
            <!-- Kolumna 2: klasyfikacja KSeF/JPK -->
            <div class="col-md-6">
              <div class="card border-0 bg-body-secondary h-100">
                <div class="card-body">
                  <h6 class="text-muted fw-normal mb-3"><i class="ri-file-list-3-line me-1"></i>Klasyfikacja KSeF/JPK</h6>
                  <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">PKWiU</dt>
                    <dd class="col-7 mb-2" id="pv-pkwiu">—</dd>
                    <dt class="col-5 text-muted fw-normal">Kod GTU</dt>
                    <dd class="col-7 mb-2" id="pv-gtu">—</dd>
                    <dt class="col-5 text-muted fw-normal">GTIN</dt>
                    <dd class="col-7 mb-2" id="pv-gtin">—</dd>
                    <dt class="col-5 text-muted fw-normal">CN</dt>
                    <dd class="col-7 mb-2" id="pv-cn">—</dd>
                    <dt class="col-5 text-muted fw-normal">PKOB</dt>
                    <dd class="col-7 mb-2" id="pv-pkob">—</dd>
                    <dt class="col-5 text-muted fw-normal">Kwota akcyzy</dt>
                    <dd class="col-7 mb-2" id="pv-excise">—</dd>
                    <dt class="col-5 text-muted fw-normal">Procedura</dt>
                    <dd class="col-7 mb-2" id="pv-procedure">—</dd>
                    <dt class="col-5 text-muted fw-normal">Zał. 15</dt>
                    <dd class="col-7 mb-0" id="pv-attach15">—</dd>
                  </dl>
                </div>
              </div>
            </div>
            <!-- Opis (pełna szerokość, ukryty jeśli pusty) -->
            <div class="col-12 d-none" id="pv-desc-row">
              <div class="card border-0 bg-body-secondary">
                <div class="card-body">
                  <h6 class="text-muted fw-normal mb-2"><i class="ri-align-left me-1"></i>Opis</h6>
                  <p class="mb-0 small" id="pv-desc" style="white-space:pre-wrap;"></p>
                </div>
              </div>
            </div>
            <!-- Stopka -->
            <div class="col-12">
              <small class="text-muted">Dodano: <span id="pv-created">—</span></small>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Zamknij</button>
        <button type="button" class="btn btn-success-light" id="pv-edit-btn" data-id="" data-name="">
          <i class="ri-pencil-line me-1"></i>Edytuj
        </button>
      </div>
    </div>
  </div>
</div>

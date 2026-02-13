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
                    <?= $this->Html->link('<i class="ri-eye-line"></i>', ['action' => 'view', $p->id], [
                      'class' => 'btn btn-sm btn-primary-light btn-icon', 'escape' => false, 'title' => 'Podgląd'
                    ]) ?>
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

<!-- Modal: Add/Edit produkt/usługa (boosted & fixed) -->
<div class="modal fade" id="product-create" tabindex="-1" aria-hidden="true" data-mode="add">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="product-modal-title">Dodaj produkt/usługę</h6>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#product-help">
            <i class="ri-information-line me-1"></i> Instrukcja
          </button>
          <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#gtu-legend-modal">
            <i class="ri-book-open-line me-1"></i> Legenda GTU
          </button>
          <button type="button" class="btn-close ms-1" data-bs-dismiss="modal" aria-label="Zamknij"></button>
        </div>
      </div>

      <?= $this->Form->create(null, [
            'url' => ['controller' => 'Products', 'action' => 'add'],
            'class' => 'needs-validation', 'novalidate' => true, 'id' => 'product-form'
      ]) ?>
      <?= $this->Form->hidden('id', ['id' => 'product-id']) ?>
      <?= $this->Form->hidden('_method', ['value' => 'POST', 'id' => 'product-method']) ?>

      <div class="modal-body">
        <?= $this->Form->hidden('company_id', ['value' => $companyId]) ?>

        <!-- Instrukcja -->
        <div id="product-help" class="collapse mb-3">
          <div class="alert alert-info d-flex gap-3 mb-0">
            <i class="ri-lightbulb-flash-line fs-4"></i>
            <div class="small">
              <strong>Wskazówki:</strong>
              <ul class="mb-2 ps-3">
                <li><kbd>Tab</kbd> – przechodzenie po polach, <kbd>Enter</kbd> – zapis.</li>
                <li><strong>GTU</strong> ustaw tylko, gdy wymagane (JPK_V7/KSeF). Skorzystaj z <em>Legendą GTU</em>.</li>
                <li>Podgląd <em>brutto</em> liczy się z wybranej stawki VAT.</li>
                <li>Kod (SKU) możesz wygenerować klikając różdżkę.</li>
              </ul>
              <span class="text-muted">Legenda GTU powinna być zgodna z aktualnymi przepisami.</span>
            </div>
          </div>
        </div>

        <div class="row g-3 align-items-start">
          <div class="col-lg-8">
            <?= $this->Form->control('name', [
              'label' => 'Nazwa*', 'required' => true, 'class' => 'form-control',
              'placeholder' => 'np. Konsultacja IT / Papier A4'
            ]) ?>
          </div>
          <div class="col-lg-4">
            <label for="code" class="form-label">Kod (SKU)</label>
            <div class="input-group">
              <?= $this->Form->control('code', [
                'label' => false, 'id' => 'code', 'class' => 'form-control', 'maxlength' => 64,
                'placeholder' => 'SKU/ID wewnętrzne', 'templates' => ['inputContainer' => '{{content}}']
              ]) ?>
              <button class="btn btn-outline-secondary" type="button" id="code-gen" title="Generuj">
                <i class="ri-magic-line"></i>
              </button>
            </div>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('unit_id', [
              'type' => 'select','label' => 'Jednostka*','options' => $unitsMap,
              'empty' => false,'class' => 'form-select', 'required' => true
            ]) ?>
          </div>

          <?php
          // Funkcja: wyciągnięcie liczby procentowej z etykiety (fallback 0 dla ZW/NP/OO)
          $extractVatRate = function (string $label): float {
            if (preg_match('/(\d+[.,]?\d*)\s*%?/', $label, $m)) {
              return (float)str_replace(',', '.', $m[1]);
            }
            if (preg_match('/\b(ZW|NP|OO)\b/i', $label)) {
              return 0.0;
            }
            return 0.0;
          };
          ?>
          <!-- VAT w MODALU -->
<div class="col-md-4">
  <label for="prod-vat-id" class="form-label">Stawka VAT*</label>
  <select name="vat_id" id="prod-vat-id" class="form-select" required>
    <?php foreach ($vatsMap as $vid => $vlabel): ?>
      <?php $rate = $extractVatRate((string)$vlabel); ?>
      <option value="<?= h($vid) ?>" data-rate="<?= h(number_format($rate, 2, '.', '')) ?>">
        <?= h($vlabel) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

<!-- NETTO + WALUTA w MODALU -->
<div class="col-md-4">
  <div class="input-group input-group-sm">
    <?= $this->Form->control('net_price', [
      'label' => 'Cena netto*','type' => 'number','step' => '0.01','min' => '0',
      'required' => true,'class' => 'form-control text-end', 'id' => 'prod-net-price',
      'templates' => ['inputContainer' => '<div class="w-100">{{content}}</div>']
    ]) ?>
    <?= $this->Form->control('currency', [
      'label' => false, 'type' => 'select','options' => $currencies,
      'default' => 'PLN', 'class' => 'form-select flex-grow-0 w-auto', 'id' => 'prod-currency',
      'templates' => ['inputContainer' => '{{content}}']
    ]) ?>
  </div>
  <div class="form-text mt-1">
    <span class="me-2">Podgląd:</span>
    <span class="badge bg-secondary" id="preview-vat">VAT: 0.00%</span>
    <span class="badge bg-primary" id="preview-gross">Brutto: —</span>
  </div>
</div>


          <div class="col-md-4">
            <div class="form-check mb-0">
              <?= $this->Form->control('is_service', [
                'type' => 'checkbox', 'label' => 'To jest usługa', 'checked' => false,
                'class' => 'form-check-input me-2',
                'templates' => ['inputContainer' => '<div class="form-check">{{content}}</div>']
              ]) ?>
            </div>
          </div>

          <div class="col-md-4">
            <label for="gtu_code" class="form-label d-flex align-items-center gap-2">
              GTU
              <i class="ri-question-line text-muted" data-bs-toggle="tooltip" title="Wybierz grupę towarowo-usługową (jeśli dotyczy)"></i>
            </label>
            <?php
              $gtuOptions = [
                '' => '— brak —',
                'GTU_01' => 'GTU_01 – Napoje alkoholowe',
                'GTU_02' => 'GTU_02 – Paliwa',
                'GTU_03' => 'GTU_03 – Oleje smarowe',
                'GTU_04' => 'GTU_04 – Wyroby tytoniowe',
                'GTU_05' => 'GTU_05 – Odpady',
                'GTU_06' => 'GTU_06 – Urządzenia elektroniczne',
                'GTU_07' => 'GTU_07 – Pojazdy oraz części',
                'GTU_08' => 'GTU_08 – Metale szlachetne i nieszlachetne',
                'GTU_09' => 'GTU_09 – Leki i wyroby medyczne',
                'GTU_10' => 'GTU_10 – Budynki, budowle i grunty',
                'GTU_11' => 'GTU_11 – Środki transportu i części',
                'GTU_12' => 'GTU_12 – Usługi niematerialne (doradcze, prawne, marketingowe, badawcze)',
                'GTU_13' => 'GTU_13 – Transport i gospodarka magazynowa',
              ];
            ?>
            <?= $this->Form->control('gtu_code', [
              'type' => 'select','label' => false,'options' => $gtuOptions,
              'class' => 'form-select','id' => 'gtu-code'
            ]) ?>
            <div class="form-text">
              <span class="text-muted">Opis: </span><span id="gtu-desc" class="text-body-secondary">—</span>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-check mb-0">
              <?= $this->Form->control('is_active', [
                'type' => 'checkbox', 'label' => 'Aktywne', 'checked' => true,
                'class' => 'form-check-input me-2',
                'templates' => ['inputContainer' => '<div class="form-check">{{content}}</div>']
              ]) ?>
            </div>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('barcode', [
              'label' => 'Kod kreskowy', 'class' => 'form-control',
              'placeholder' => 'EAN/UPC'
            ]) ?>
          </div>

          <div class="col-md-4">
            <?= $this->Form->control('pkwiu', [
              'label' => 'PKWiU', 'class' => 'form-control',
              'placeholder' => 'np. 62.02.30.0'
            ]) ?>
          </div>

          <div class="col-12">
            <?= $this->Form->control('description', [
              'label' => 'Opis', 'type' => 'textarea', 'rows' => 3, 'class' => 'form-control',
              'placeholder' => 'Krótki opis dla wewnętrznego użytku'
            ]) ?>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <div class="me-auto small text-muted">
          <i class="ri-shield-keyhole-line me-1"></i>Dane pozycji faktury kopiują cenę i VAT z produktu w momencie wystawienia.
        </div>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Anuluj</button>
        <?= $this->Form->button('<i class="ri-save-line me-1"></i> Zapisz', [
          'class' => 'btn btn-primary', 'escapeTitle' => false
        ]) ?>
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
  #product-create .form-group { display:block; }
  #product-create #currency { max-width: 92px; min-width: 72px; }
  #product-create #net-price { text-align: right; }
  .pagination .page-link { min-width: 2rem; text-align: center; }
  .pagination .page-item.active .page-link { box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }
  .table-compact .table td, .table-compact .table th { padding-top:.4rem; padding-bottom:.4rem; }
  #product-create .form-text .badge { vertical-align: middle; }
</style>

<script>
// === Wzbogacenia modala produktów: GTU + brutto z data-rate ===
document.addEventListener('DOMContentLoaded', () => {
  // Tooltips
  document.querySelectorAll('#product-create [data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
document.getElementById('product-create')?.addEventListener('shown.bs.modal', refreshGrossPreview);

  const modalEl   = document.getElementById('product-create');
  const form      = document.getElementById('product-form');

const netInput  = document.getElementById('prod-net-price');
const vatSelect = document.getElementById('prod-vat-id');
const currency  = document.getElementById('prod-currency');
  const badgeVat  = document.getElementById('preview-vat');
  const badgeGross= document.getElementById('preview-gross');

  // GTU legenda / opis
  const gtuSelect = document.getElementById('gtu-code');
  const gtuDescEl = document.getElementById('gtu-desc');
  const GTU_LEGEND = {
    'GTU_01': {name:'Napoje alkoholowe', desc:'Dostawa napojów alkoholowych.'},
    'GTU_02': {name:'Paliwa', desc:'Paliwa silnikowe, gaz, wybrane wyroby energetyczne.'},
    'GTU_03': {name:'Oleje smarowe', desc:'Wybrane oleje/środki smarowe.'},
    'GTU_04': {name:'Wyroby tytoniowe', desc:'Papierosy, tytoń, wyroby tytoniowe.'},
    'GTU_05': {name:'Odpady', desc:'Wybrane odpady (w tym złom).'},
    'GTU_06': {name:'Elektronika', desc:'Telefony, konsole, laptopy, dyski i podobne.'},
    'GTU_07': {name:'Pojazdy i części', desc:'Wybrane pojazdy oraz części samochodowe.'},
    'GTU_08': {name:'Metale', desc:'Metale szlachetne/nieszlachetne w wybranych postaciach.'},
    'GTU_09': {name:'Leki/wyroby med.', desc:'Wybrane produkty lecznicze/medyczne.'},
    'GTU_10': {name:'Nieruchomości', desc:'Dostawa budynków, budowli i gruntów.'},
    'GTU_11': {name:'Środki transportu', desc:'Wybrane środki transportu i części.'},
    'GTU_12': {name:'Usługi niematerialne', desc:'Doradcze, prawne, księgowe, reklamowe, badawcze itd.'},
    'GTU_13': {name:'Transport/magazyn', desc:'Usługi transportowe i magazynowe.'}
  };
  const legendTbody = document.getElementById('gtu-legend-tbody');
  if (legendTbody) {
    legendTbody.innerHTML = Object.keys(GTU_LEGEND).sort().map(k => {
      const it = GTU_LEGEND[k];
      return `<tr><td><code>${k}</code></td><td>${it.name}</td><td class="text-muted">${it.desc}</td></tr>`;
    }).join('');
  }
  function refreshGtuDesc() {
    const code = gtuSelect?.value || '';
    if (!code || !GTU_LEGEND[code]) { gtuDescEl.textContent = '—'; return; }
    gtuDescEl.textContent = `${GTU_LEGEND[code].name} – ${GTU_LEGEND[code].desc}`;
  }
  gtuSelect?.addEventListener('change', refreshGtuDesc);
  refreshGtuDesc();

  // Kasa
  function round2(n){ return Math.round((n + Number.EPSILON) * 100)/100; }
  function money(n){ return Number.isNaN(n) ? '—' : n.toLocaleString('pl-PL',{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function getVatPercent(){
    const opt = vatSelect?.selectedOptions?.[0];
    if (!opt) return 0;
    const rate = parseFloat(opt.getAttribute('data-rate') || '0');
    return Number.isFinite(rate) ? rate : 0;
  }
  function refreshGrossPreview(){
    const net = parseFloat((netInput?.value || '0').replace(',', '.')) || 0;
    const vat = getVatPercent();
    const gross = round2(net * (1 + vat/100));
    const cur = currency?.value || '';
    if (badgeVat)   badgeVat.textContent   = `VAT: ${vat.toFixed(2)}%`;
    if (badgeGross) badgeGross.textContent = `Brutto: ${money(gross)} ${cur}`;
  }
['input','change','blur'].forEach(ev => {
  document.getElementById('prod-net-price')?.addEventListener(ev, refreshGrossPreview);
});
document.getElementById('prod-vat-id')?.addEventListener('change', refreshGrossPreview);
document.getElementById('prod-currency')?.addEventListener('change', refreshGrossPreview);

  refreshGrossPreview();

  // Fokus + przeliczenie po otwarciu
  modalEl?.addEventListener('shown.bs.modal', () => {
    modalEl.querySelector('input[name="name"]')?.focus();
    refreshGrossPreview();
  });

  // Prosta walidacja
  form?.addEventListener('submit', (e) => {
    if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
    form.classList.add('was-validated');
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
  // tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
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
  const modalEl   = document.getElementById('product-create');
  const modal     = new bootstrap.Modal(modalEl);
  const form      = document.getElementById('product-form');
  const titleEl   = document.getElementById('product-modal-title');
  const idField   = document.getElementById('product-id');
  const methodFld = document.getElementById('product-method');
  const codeGen   = document.getElementById('code-gen');

  // generator prostego SKU
  codeGen?.addEventListener('click', () => {
    const name = (form.querySelector('input[name="name"]')?.value || '').trim();
    const base = (name || 'ITEM').toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g,'').slice(0,16);
    const rand = Math.random().toString(36).slice(2, 6).toUpperCase();
    const code = [base, rand].filter(Boolean).join('-');
    const codeInput = document.getElementById('code');
    if (codeInput) codeInput.value = code;
  });

  form?.addEventListener('submit', (e) => {
    if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
    form.classList.add('was-validated');
  });

  function setVal(selector, value) {
    const el = form?.querySelector(selector);
    if (el) el.value = value ?? '';
  }
  function setChecked(selector, on) {
    const el = form?.querySelector(selector);
    if (el) el.checked = !!Number(on);
  }

  // reset -> ADD
  function resetFormToAdd() {
    form.reset();
    idField.value = '';
    methodFld.value = 'POST';
    form.setAttribute('action', '<?= $this->Url->build(['controller' => 'Products', 'action' => 'add']) ?>');
    modalEl.dataset.mode = 'add';
    titleEl.textContent = 'Dodaj produkt/usługę';
    form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(i => i.remove());
  }
  document.querySelectorAll('[data-bs-target="#product-create"]').forEach(btn => {
    btn.addEventListener('click', resetFormToAdd);
  });

  // klik edycji
  document.querySelectorAll('.js-edit').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id; if (!id) return;
      resetFormToAdd();
      titleEl.textContent = 'Edytuj: ' + (btn.dataset.name || ('#' + id));
      modalEl.dataset.mode = 'edit';
      idField.value = id;
      methodFld.value = 'PUT';
      form.setAttribute('action', '<?= $this->Url->build(['controller' => 'Products', 'action' => 'edit']) ?>/' + id);

      try {
        const res = await fetch('<?= $this->Url->build(['controller' => 'Products', 'action' => 'viewJson']) ?>/' + id, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.success) throw new Error('Brak danych');
        const p = data.product || {};

        setVal('input[name="name"]', p.name);
        setVal('input[name="code"]', p.code);
        setVal('input[name="net_price"]', p.net_price);
        setVal('select[name="currency"]', p.currency || 'PLN');
        setVal('select[name="unit_id"]', p.unit_id);
        setVal('select[name="vat_id"]', p.vat_id);
        setVal('input[name="barcode"]', p.barcode);
        setVal('input[name="pkwiu"]', p.pkwiu);
        setVal('select[name="gtu_code"]', p.gtu_code);
        const desc = form.querySelector('textarea[name="description"]'); if (desc) desc.value = p.description ?? '';
        setChecked('input[name="is_service"]', p.is_service);
        setChecked('input[name="is_active"]', p.is_active);

        modal.show();

        // po podstawieniu danych odśwież podgląd brutto i opis GTU
        const ev = new Event('change');
        document.getElementById('vat-id')?.dispatchEvent(ev);
        document.getElementById('net-price')?.dispatchEvent(new Event('input'));
        document.getElementById('gtu-code')?.dispatchEvent(ev);

      } catch {
        toastBody.textContent = 'Nie udało się wczytać danych produktu.'; toast.show();
      }
    });
  });

  // submit (AJAX)
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) { e.stopPropagation(); form.classList.add('was-validated'); return; }

    const mode = modalEl.dataset.mode || 'add';
    const action = form.getAttribute('action');
    const fd = new FormData(form);

    try {
      const res = await fetch(action, {
        method: 'POST', // _method=PUT dla edycji
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      });
      const data = await res.json();

      // wyczyść błędy
      form.querySelectorAll('.is-invalid').forEach(i => i.classList.remove('is-invalid'));
      form.querySelectorAll('.invalid-feedback').forEach(i => i.remove());

      if (data.success) {
        toastBody.textContent = mode === 'add' ? 'Dodano produkt/usługę.' : 'Zapisano zmiany.'; toast.show();
        bootstrap.Modal.getInstance(modalEl)?.hide();
        // odśwież – najszybciej zsynchronizuje mapy Jm/VAT
        window.location.reload();
      } else {
        toastBody.textContent = data.message || 'Błąd zapisu.'; toast.show();
        if (data.errors) {
          for (const [field, msgs] of Object.entries(data.errors)) {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
              input.classList.add('is-invalid');
              let fb = input.nextElementSibling;
              if (!fb || !fb.classList.contains('invalid-feedback')) {
                fb = document.createElement('div'); fb.className = 'invalid-feedback';
                input.insertAdjacentElement('afterend', fb);
              }
              fb.textContent = Object.values(msgs).flat().join(', ');
            }
          }
        }
      }
    } catch {
      toastBody.textContent = 'Błąd połączenia z serwerem.'; toast.show();
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
});
</script>

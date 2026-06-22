<?php
/**
 * @var \App\View\AppView $this
 * @var array $ksefInvoices
 * @var string|null $ksefError
 * @var int $ksefTotal
 * @var int $ksefPage
 * @var array $existingKsefNumbers  ksef_number => ksef_number (flip'd)
 * @var string $ksefEnv  prod|test (resolved przez controller)
 * @var string $fromUsed YYYY-MM-DD (zastosowany filtr daty od; default 1. dzień bieżącego miesiąca)
 * @var string $toUsed   YYYY-MM-DD (zastosowany filtr daty do; default ostatni dzień bieżącego miesiąca)
 */
$this->assign('title', 'Import z KSeF');
$csrfToken    = $this->request->getAttribute('csrfToken');
$doImportUrl  = $this->Url->build(['action' => 'doImportKsef']);
$indexUrl     = $this->Url->build(['action' => 'index']);
$fdate = fn($v) => $v ? substr((string)$v, 0, 10) : '—';
$fnum  = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';
?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="<?= $indexUrl ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i> Lista faktur kosztowych
    </a>
    <h4 class="mb-0 fw-semibold ms-2">
        <i class="ri-government-line me-1 text-primary"></i> Import z KSeF — faktury otrzymane
    </h4>
</div>

<!-- Filtry KSeF -->
<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Szukaj (numer, NIP, przewoźnik)…"
               value="<?= h($this->request->getQuery('q', '')) ?>">
    </div>
    <div class="col-md-2">
        <input type="date" name="from" class="form-control form-control-sm"
               value="<?= h($fromUsed ?? '') ?>" title="Data wystawienia od (KSeF wymaga zakresu)">
    </div>
    <div class="col-md-2">
        <input type="date" name="to" class="form-control form-control-sm"
               value="<?= h($toUsed ?? '') ?>" title="Data wystawienia do">
    </div>
    <div class="col-md-2">
        <select name="env" class="form-select form-select-sm" title="Środowisko KSeF (domyślnie produkcja)">
            <option value="prod" <?= ($ksefEnv ?? 'prod') === 'prod' ? 'selected' : '' ?>>Produkcja</option>
            <option value="test" <?= ($ksefEnv ?? 'prod') === 'test' ? 'selected' : '' ?>>Test</option>
        </select>
    </div>
    <div class="col-md-3 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-outline-primary flex-grow-1">
            <i class="ri-search-line"></i> Filtruj
        </button>
        <?php if (!empty($ksefInvoices)): ?>
        <button type="button" class="btn btn-sm btn-primary" id="btn-import-selected">
            <i class="ri-download-2-line me-1"></i> Importuj zaznaczone
        </button>
        <?php endif; ?>
    </div>
</form>

<div class="alert alert-light border small py-2 mb-2 d-flex align-items-center gap-2">
    <i class="ri-calendar-line text-primary"></i>
    <span>Środowisko: <strong><?= h($ksefEnv ?? 'prod') ?></strong>
        · Okres: <strong><?= h($fromUsed ?? '') ?></strong> → <strong><?= h($toUsed ?? '') ?></strong>
        <?php if (($fromUsed ?? '') === date('Y-m-01') && ($toUsed ?? '') === date('Y-m-t')): ?>
            <span class="badge bg-info-subtle text-info border ms-1" style="font-size:.65rem">bieżący miesiąc (default)</span>
        <?php endif; ?>
    </span>
</div>

<?php if ($ksefError): ?>
<?php $isAuthError = stripos($ksefError, 'unauthor') !== false || stripos($ksefError, '401') !== false; ?>
<div class="alert alert-danger">
    <i class="ri-error-warning-line me-1"></i>
    <strong>Błąd KSeF:</strong> <?= h($ksefError) ?>
    <?php if ($isAuthError): ?>
        <div class="small mt-2">
            <i class="ri-information-line me-1"></i>
            <strong>Brak ważnej sesji KSeF.</strong> Token autoryzacyjny mógł wygasnąć
            lub certyfikat nie jest skonfigurowany. Sprawdź status w
            <a href="/ksef-authorizations" class="alert-link">Autoryzacje KSeF</a>
            i zaloguj się ponownie, a następnie wróć tutaj.
        </div>
    <?php endif; ?>
</div>
<?php elseif (empty($ksefInvoices)): ?>
<div class="alert alert-info">
    <i class="ri-information-line me-1"></i>
    Brak faktur w KSeF dla podanych kryteriów lub brak autoryzacji KSeF.
    <div class="small text-muted mt-1">
        Zakres: <?= h($fromUsed ?? '') ?> → <?= h($toUsed ?? '') ?>. Spróbuj zmienić zakres dat lub środowisko.
    </div>
</div>
<?php else: ?>

<div class="d-flex align-items-center gap-3 mb-2">
    <span class="text-muted small">
        Znaleziono <strong><?= $ksefTotal ?></strong> faktur, pokazano <strong><?= count($ksefInvoices) ?></strong>
    </span>
    <div class="ms-auto d-flex gap-2 align-items-center">
        <label class="form-check-label small text-muted">
            <input type="checkbox" id="chk-select-all" class="form-check-input me-1">
            Zaznacz wszystkie nowe
        </label>
    </div>
</div>

<div id="import-result" class="mb-2"></div>

<div class="table-responsive">
<table class="table table-hover table-sm align-middle mb-0" id="ksef-table">
    <thead class="table-light">
        <tr>
            <th style="width:32px"></th>
            <th>Numer faktury</th>
            <th>Przewoźnik (wystawca)</th>
            <th>Data wyst.</th>
            <th class="text-end">Kwota</th>
            <th>Wal.</th>
            <th>KSeF</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($ksefInvoices as $inv):
        $ksefNum = (string)($inv['ksef_number'] ?? '');
        $fullNum = (string)($inv['fullnumber'] ?? $inv['invoiceNumber'] ?? '');
        $ctrName = (string)($inv['InvoiceContractors']['name'] ?? '');
        $ctrNip  = (string)($inv['InvoiceContractors']['tax_id'] ?? '');
        $date    = (string)($inv['date'] ?? '');
        $total   = (float)($inv['total'] ?? 0);
        $cur     = strtoupper((string)($inv['currency'] ?? 'PLN'));
        $alreadyImported = isset($existingKsefNumbers[$ksefNum]) && $ksefNum !== '';
        $rowJson = h(json_encode($inv, JSON_UNESCAPED_UNICODE));
    ?>
    <tr class="<?= $alreadyImported ? 'table-success' : '' ?>" data-ksef="<?= h($ksefNum) ?>">
        <td>
            <?php if ($alreadyImported): ?>
                <i class="ri-checkbox-circle-fill text-success" title="Już zaimportowana"></i>
            <?php else: ?>
                <input type="checkbox" class="form-check-input chk-invoice"
                       value="<?= h($ksefNum) ?>"
                       data-row='<?= $rowJson ?>'>
            <?php endif; ?>
        </td>
        <td class="fw-semibold" style="font-size:.82rem">
            <?= h($fullNum ?: '—') ?>
        </td>
        <td style="font-size:.82rem">
            <div><?= h($ctrName) ?></div>
            <?php if ($ctrNip): ?>
            <div class="text-muted" style="font-size:.72rem"><?= h($ctrNip) ?></div>
            <?php endif; ?>
        </td>
        <td class="text-nowrap" style="font-size:.82rem"><?= h($fdate($date)) ?></td>
        <td class="text-end fw-semibold text-nowrap" style="font-size:.82rem">
            <?= $fnum($total) ?>
        </td>
        <td style="font-size:.78rem"><?= h($cur) ?></td>
        <td style="font-size:.68rem; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
            <span class="text-muted" title="<?= h($ksefNum) ?>"><?= h(substr($ksefNum, 0, 25)) ?>…</span>
        </td>
        <td>
            <?php if ($alreadyImported): ?>
            <span class="badge bg-success-subtle text-success" style="font-size:.72rem">Zaimportowana</span>
            <?php else: ?>
            <span class="badge bg-secondary-subtle text-secondary" style="font-size:.72rem">Nowa</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- Paginacja KSeF -->
<?php if ($ksefTotal > 25): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php
        $totalPages = (int)ceil($ksefTotal / 25);
        for ($p = 1; $p <= min($totalPages, 10); $p++):
        ?>
        <li class="page-item <?= $p === $ksefPage ? 'active' : '' ?>">
            <a class="page-link" href="<?= $this->Url->build(['action' => 'importKsef', '?' => array_merge($this->request->getQueryParams(), ['page' => $p])]) ?>">
                <?= $p ?>
            </a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

<?php $this->append('scriptBottom'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var doImportUrl = '<?= $doImportUrl ?>';
    var csrfToken   = '<?= h($csrfToken) ?>';

    // Zaznacz wszystkie nowe
    var chkAll = document.getElementById('chk-select-all');
    if (chkAll) {
        chkAll.addEventListener('change', function() {
            document.querySelectorAll('.chk-invoice').forEach(function(c) {
                c.checked = chkAll.checked;
            });
        });
    }

    // Importuj zaznaczone
    var btnImport = document.getElementById('btn-import-selected');
    if (btnImport) {
        btnImport.addEventListener('click', function() {
            var checked = Array.from(document.querySelectorAll('.chk-invoice:checked'));
            if (checked.length === 0) {
                alert('Zaznacz przynajmniej jedną fakturę.');
                return;
            }

            var items = checked.map(function(c) {
                try { return JSON.parse(c.dataset.row); } catch(e) { return null; }
            }).filter(Boolean);

            btnImport.disabled = true;
            btnImport.innerHTML = '<i class="ri-loader-4-line me-1"></i> Importowanie…';

            fetch(doImportUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ items: items }),
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var el = document.getElementById('import-result');
                if (data.success) {
                    el.innerHTML = '<div class="alert alert-success py-2">'
                        + '<i class="ri-checkbox-circle-line me-1"></i>'
                        + 'Zaimportowano: <strong>' + data.saved + '</strong>, '
                        + 'pominięto (duplikaty): <strong>' + data.skipped + '</strong>'
                        + (data.errors.length ? ' | Błędy: ' + data.errors.join(', ') : '')
                        + '</div>';
                    // Odznacz zaimportowane wiersze
                    checked.forEach(function(c) {
                        var tr = c.closest('tr');
                        c.replaceWith('<i class="ri-checkbox-circle-fill text-success"></i>');
                        tr.classList.add('table-success');
                    });
                } else {
                    el.innerHTML = '<div class="alert alert-danger py-2">' + (data.error || 'Błąd importu') + '</div>';
                }
            })
            .catch(function(err) {
                document.getElementById('import-result').innerHTML =
                    '<div class="alert alert-danger py-2">Błąd połączenia: ' + err.message + '</div>';
            })
            .finally(function() {
                btnImport.disabled = false;
                btnImport.innerHTML = '<i class="ri-download-2-line me-1"></i> Importuj zaznaczone';
            });
        });
    }
});
</script>
<?php $this->end(); ?>

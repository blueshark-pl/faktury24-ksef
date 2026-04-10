<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $invoices
 * @var int $total, $page, $pages, $limit
 * @var string $search, $month, $status, $source
 * @var string[] $months
 */
$this->assign('title', 'Faktury kosztowe');

$statusMap = [
    'received' => ['label' => 'Otrzymana',    'cls' => 'bg-secondary-subtle text-secondary'],
    'verified' => ['label' => 'Zweryfikowana','cls' => 'bg-info-subtle text-info'],
    'paid'     => ['label' => 'Zapłacona',    'cls' => 'bg-success-subtle text-success'],
];
$sourceMap = [
    'ksef'   => ['label' => 'KSeF',   'cls' => 'bg-primary-subtle text-primary',   'icon' => 'ri-government-line'],
    'manual' => ['label' => 'Ręczna', 'cls' => 'bg-warning-subtle text-warning',   'icon' => 'ri-edit-line'],
];
$fnum = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';
$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">Faktury kosztowe <span class="text-muted fs-6 fw-normal">od przewoźników</span></h4>
    <div class="d-flex gap-2">
        <a href="<?= $this->Url->build(['action' => 'importKsef']) ?>" class="btn btn-sm btn-outline-primary">
            <i class="ri-government-line me-1"></i> Importuj z KSeF
        </a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-primary">
            <i class="ri-add-line me-1"></i> Dodaj ręcznie
        </a>
    </div>
</div>

<!-- Filtry -->
<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Szukaj (numer, przewoźnik, NIP, KSeF)…" value="<?= h($search) ?>">
    </div>
    <div class="col-md-2">
        <select name="month" class="form-select form-select-sm">
            <option value="">-- Miesiąc --</option>
            <?php foreach ($months as $m): ?>
            <option value="<?= h($m) ?>" <?= $month === $m ? 'selected' : '' ?>>
                <?= h($m) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
            <option value="">-- Status --</option>
            <?php foreach ($statusMap as $k => $s): ?>
            <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($s['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="source" class="form-select form-select-sm">
            <option value="">-- Źródło --</option>
            <option value="ksef"   <?= $source === 'ksef'   ? 'selected' : '' ?>>KSeF</option>
            <option value="manual" <?= $source === 'manual' ? 'selected' : '' ?>>Ręczna</option>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-outline-primary flex-grow-1">
            <i class="ri-search-line"></i> Filtruj
        </button>
        <?php if ($search || $month || $status || $source): ?>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary" title="Wyczyść">
            <i class="ri-close-line"></i>
        </a>
        <?php endif; ?>
    </div>
</form>

<p class="text-muted small mb-2">
    Znaleziono: <strong><?= number_format($total, 0, ',', ' ') ?></strong> faktur kosztowych
    <?php if ($total > $limit): ?>(strona <?= $page ?> z <?= $pages ?>)<?php endif; ?>
</p>

<div class="table-responsive">
<table class="table table-hover table-sm align-middle mb-0">
    <thead class="table-light">
        <tr>
            <th>Numer</th>
            <th>Przewoźnik</th>
            <th>Miesiąc rozl.</th>
            <th>Data wyst.</th>
            <th class="text-end">Brutto</th>
            <th>Wal.</th>
            <th>Źródło</th>
            <th>Status</th>
            <th>Zlecenia</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if ($total === 0): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">
            Brak faktur kosztowych. <a href="<?= $this->Url->build(['action' => 'importKsef']) ?>">Importuj z KSeF</a> lub
            <a href="<?= $this->Url->build(['action' => 'add']) ?>">dodaj ręcznie</a>.
        </td></tr>
    <?php else: ?>
        <?php foreach ($invoices as $inv):
            $src = $sourceMap[$inv->source] ?? ['label' => $inv->source, 'cls' => 'bg-light text-dark', 'icon' => 'ri-file-line'];
            $st  = $statusMap[$inv->status] ?? ['label' => $inv->status, 'cls' => 'bg-light text-dark'];
            $num = $inv->invoice_number ?: ($inv->ksef_number ? '(KSeF)' : '—');
        ?>
        <tr>
            <td class="fw-semibold">
                <a href="<?= $this->Url->build(['action' => 'view', $inv->id]) ?>" class="text-decoration-none">
                    <?= h($num) ?>
                </a>
                <?php if ($inv->ksef_number && $inv->invoice_number): ?>
                <div class="text-muted" style="font-size:.7rem"><?= h(substr($inv->ksef_number, 0, 30)) ?>…</div>
                <?php endif; ?>
            </td>
            <td>
                <div style="font-size:.82rem"><?= h($inv->contractor_name) ?></div>
                <?php if ($inv->contractor_nip): ?>
                <div class="text-muted" style="font-size:.72rem"><?= h($inv->contractor_nip) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($inv->accounting_month): ?>
                <span class="badge bg-dark-subtle text-dark"><?= h($inv->accounting_month) ?></span>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
            <td class="text-nowrap" style="font-size:.82rem"><?= $fdate($inv->issue_date) ?></td>
            <td class="text-end fw-semibold text-nowrap" style="font-size:.82rem">
                <?= $fnum($inv->brutto) ?>
            </td>
            <td style="font-size:.78rem"><?= h($inv->currency ?? 'PLN') ?></td>
            <td>
                <span class="badge <?= $src['cls'] ?>" style="font-size:.72rem">
                    <i class="<?= $src['icon'] ?> me-1"></i><?= $src['label'] ?>
                </span>
            </td>
            <td>
                <span class="badge <?= $st['cls'] ?>" style="font-size:.72rem"><?= $st['label'] ?></span>
            </td>
            <td class="text-center">
                <?php
                // Liczba powiązanych zleceń — pobieramy osobno przez contains
                // Tutaj uproszczone, pełna liczba widoczna w view
                ?>
                <a href="<?= $this->Url->build(['action' => 'view', $inv->id]) ?>"
                   class="text-muted small">szczegóły</a>
            </td>
            <td class="text-end text-nowrap">
                <a href="<?= $this->Url->build(['action' => 'view', $inv->id]) ?>"
                   class="btn btn-xs btn-outline-secondary py-0 px-1" title="Szczegóły">
                    <i class="ri-eye-line"></i>
                </a>
                <a href="<?= $this->Url->build(['action' => 'edit', $inv->id]) ?>"
                   class="btn btn-xs btn-outline-secondary py-0 px-1 ms-1" title="Edytuj">
                    <i class="ri-edit-line"></i>
                </a>
                <?php if ($inv->pdf_path): ?>
                <a href="/<?= h($inv->pdf_path) ?>" target="_blank"
                   class="btn btn-xs btn-outline-danger py-0 px-1 ms-1" title="PDF">
                    <i class="ri-file-pdf-line"></i>
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>

<!-- Paginacja -->
<?php if ($pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= $this->Url->build(['action' => 'index', '?' => compact('search','month','status','source') + ['page' => $p]]) ?>">
                <?= $p ?>
            </a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

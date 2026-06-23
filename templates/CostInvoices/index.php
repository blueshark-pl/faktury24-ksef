<?php
/**
 * @var \App\View\AppView $this
 * @var iterable $invoices
 * @var int $total, $page, $pages, $limit
 * @var string $search, $month, $status, $source, $paymentState, $hasOrder
 * @var string $dateFrom, $dateTo, $contractorNip
 * @var int $costStatusF
 * @var string[] $months
 * @var array $contractors
 * @var array $stats
 * @var array $orderCounts
 * @var array $costStatusLabels  1..9 => label
 * @var array $costStatusColors  1..9 => color (warning|primary|success|info|danger|dark|secondary|orange)
 * @var string $today
 */
$csrfToken = $this->request->getAttribute('csrfToken');
$setCostStatusUrl = $this->Url->build(['action' => 'setCostStatus']);
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
$fnum  = fn($v) => $v !== null ? number_format((float)$v, 2, ',', ' ') : '—';
$fnum0 = fn($v) => $v !== null ? number_format((float)$v, 0, ',', ' ') : '—';
$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';

$activeFilters = ($paymentState !== '' ? 1 : 0) + ($hasOrder !== '' ? 1 : 0)
    + ($dateFrom !== '' ? 1 : 0) + ($dateTo !== '' ? 1 : 0)
    + ($contractorNip !== '' ? 1 : 0);
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">Faktury kosztowe <span class="text-muted fs-6 fw-normal">od przewoźników</span></h4>
    <div class="d-flex gap-2">
        <button type="button" id="btn-sync-ksef-auto" class="btn btn-sm btn-success"
                title="Pobierz nowe faktury z KSeF (ostatnie 7 dni, z dedup)">
            <i class="ri-refresh-line me-1"></i> Pobierz z KSeF (auto)
        </button>
        <a href="<?= $this->Url->build(['action' => 'importKsef']) ?>" class="btn btn-sm btn-outline-primary">
            <i class="ri-government-line me-1"></i> Importuj z KSeF (ręcznie)
        </a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-primary">
            <i class="ri-add-line me-1"></i> Dodaj ręcznie
        </a>
    </div>
</div>
<div id="sync-result" class="mb-2"></div>

<?php $this->append('scriptBottom'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('btn-sync-ksef-auto');
    var resultBox = document.getElementById('sync-result');
    if (!btn) return;
    var csrfToken = '<?= h($this->request->getAttribute('csrfToken')) ?>';
    var url = '<?= $this->Url->build(['action' => 'syncKsefAuto']) ?>';

    // ── Dropdown cost_status — zmiana workflow przez AJAX ───────────
    document.addEventListener('click', function(ev) {
        var pick = ev.target.closest('.cost-status-pick');
        if (!pick) return;
        ev.preventDefault();
        var newStatus = parseInt(pick.dataset.status, 10);
        var id = parseInt(pick.dataset.costInvoiceId, 10);
        if (!id || newStatus < 1 || newStatus > 9) return;

        // Optymistyczna aktualizacja UI — toggle button w tym samym dropdown
        var dropdown = pick.closest('.dropdown');
        var toggle = dropdown?.querySelector('.cost-status-toggle');

        fetch('<?= $setCostStatusUrl ?>', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id: id, cost_status: newStatus })
        })
        .then(function(r) { return r.text().then(function(txt) {
            var d; try { d = JSON.parse(txt); } catch (e) { throw new Error('Nie JSON: ' + txt.substring(0, 100)); }
            return d;
        }); })
        .then(function(d) {
            if (d.success) {
                if (toggle) toggle.textContent = d.cost_status_label;
                // Wymuś reload żeby badge + filtr + sort się odświeżyły
                setTimeout(function(){ location.reload(); }, 400);
            } else {
                alert(d.error || 'Błąd zmiany statusu');
            }
        })
        .catch(function(e) { alert('Błąd: ' + e.message); });
    });

    btn.addEventListener('click', function() {
        var days = prompt('Pobrać faktury z KSeF za ile dni wstecz?', '7');
        if (days === null) return;
        days = parseInt(days, 10);
        if (!days || days < 1 || days > 90) {
            alert('Zakres 1-90 dni.');
            return;
        }

        btn.disabled = true;
        var orig = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>pobieram…';
        resultBox.innerHTML = '<div class="alert alert-info py-2"><i class="ri-loader-line me-1"></i> Pobieram z KSeF (do 20 stron, może chwilę potrwać)…</div>';

        fetch(url + '?days=' + days, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(function(r) {
            return r.text().then(function(txt) {
                var parsed;
                try { parsed = JSON.parse(txt); } catch (e) {
                    // Response nie jest JSON — pokaz surowy snippet zeby user zobaczyl
                    var snippet = (txt || '').substring(0, 300);
                    throw new Error('Niepoprawna odpowiedź serwera (nie JSON): ' + snippet);
                }
                return { ok: r.ok, body: parsed };
            });
        })
        .then(function(res) {
            btn.disabled = false;
            btn.innerHTML = orig;
            var d = res.body || {};
            if (d.errors && d.errors.length) {
                var html = '<div class="alert alert-warning py-2">';
                html += '<i class="ri-alert-line me-1"></i>';
                html += '<strong>Pobrano: ' + (d.fetched||0) + ' · Zapisano: ' + (d.saved||0) + ' · Pominięto: ' + (d.skipped||0) + '</strong>';
                html += '<div class="small mt-1">Zakres: ' + (d.range?.from || '?') + ' → ' + (d.range?.to || '?') + ' (' + (d.env||'?') + ')</div>';
                html += '<div class="small mt-2 text-danger">Błędy:<ul class="mb-0">';
                d.errors.forEach(function(e) {
                    var s = String(e).replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    if (s.length > 200) s = s.substring(0, 200) + '…';
                    html += '<li>' + s + '</li>';
                });
                html += '</ul></div></div>';
                resultBox.innerHTML = html;
            } else {
                var msg = 'Pobrano ' + (d.fetched||0) + ', zapisano <strong>' + (d.saved||0) + '</strong>, pominięto (już w bazie) ' + (d.skipped||0);
                resultBox.innerHTML = '<div class="alert alert-success py-2"><i class="ri-checkbox-circle-line me-1"></i>' + msg + '. Zakres: ' + (d.range?.from || '?') + ' → ' + (d.range?.to || '?') + '.</div>';
                if ((d.saved||0) > 0) {
                    setTimeout(function(){ location.reload(); }, 1500);
                }
            }
        })
        .catch(function(e) {
            btn.disabled = false;
            btn.innerHTML = orig;
            resultBox.innerHTML = '<div class="alert alert-danger py-2"><i class="ri-error-warning-line me-1"></i>Błąd: ' + e.message + '</div>';
        });
    });
});
</script>
<?php $this->end(); ?>

<!-- Mini-stats -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2 d-flex flex-wrap align-items-center gap-4">
        <div>
            <div class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">Faktur</div>
            <div class="fw-bold fs-5"><?= $fnum0($stats['count']) ?></div>
        </div>
        <div class="vr"></div>
        <div>
            <div class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">Brutto łącznie</div>
            <div class="fw-bold fs-5">
                <?= $fnum0($stats['total_pln']) ?> <small class="text-muted">PLN</small>
                <?php if ($stats['total_eur'] > 0): ?>
                    · <?= $fnum0($stats['total_eur']) ?> <small class="text-muted">EUR</small>
                <?php endif; ?>
            </div>
        </div>
        <div class="vr"></div>
        <div>
            <div class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">Zapłacono</div>
            <div class="fw-bold fs-5 text-success">
                <?= $fnum0($stats['paid_pln']) ?> <small class="text-muted">PLN</small>
                <?php if ($stats['paid_eur'] > 0): ?>
                    · <?= $fnum0($stats['paid_eur']) ?> <small class="text-muted">EUR</small>
                <?php endif; ?>
            </div>
        </div>
        <div class="vr"></div>
        <div>
            <div class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">Pozostało</div>
            <div class="fw-bold fs-5 <?= $stats['remaining_pln'] + $stats['remaining_eur'] > 0 ? 'text-danger' : '' ?>">
                <?= $fnum0($stats['remaining_pln']) ?> <small class="text-muted">PLN</small>
                <?php if ($stats['remaining_eur'] > 0): ?>
                    · <?= $fnum0($stats['remaining_eur']) ?> <small class="text-muted">EUR</small>
                <?php endif; ?>
            </div>
        </div>
        <?php if (($stats['overdue_count'] ?? 0) > 0): ?>
        <div class="vr"></div>
        <div>
            <div class="text-muted text-uppercase" style="font-size:.62rem;letter-spacing:.05em">Przeterminowane</div>
            <div class="fw-bold fs-5 text-danger">
                <?= (int)$stats['overdue_count'] ?> ·
                <?= $fnum0($stats['overdue_pln']) ?> <small class="text-muted">PLN</small>
                <?php if ($stats['overdue_eur'] > 0): ?>
                    · <?= $fnum0($stats['overdue_eur']) ?> <small class="text-muted">EUR</small>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Filtry -->
<form method="get" class="card shadow-sm mb-3">
<div class="card-body p-2">
    <div class="row g-2">
        <div class="col-md-3">
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Szukaj (numer, przewoźnik, NIP, KSeF)…" value="<?= h($search) ?>">
        </div>
        <div class="col-md-2">
            <select name="contractor_nip" class="form-select form-select-sm">
                <option value="">— Kontrahent —</option>
                <?php foreach ($contractors as $c): ?>
                <option value="<?= h($c['contractor_nip']) ?>" <?= $contractorNip === $c['contractor_nip'] ? 'selected' : '' ?>>
                    <?= h(mb_strimwidth($c['contractor_name'] ?: $c['contractor_nip'], 0, 35, '…')) ?> (<?= (int)$c['cnt'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="month" class="form-select form-select-sm">
                <option value="">— Miesiąc rozl. —</option>
                <?php foreach ($months as $m): ?>
                <option value="<?= h($m) ?>" <?= $month === $m ? 'selected' : '' ?>><?= h($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">— Status —</option>
                <?php foreach ($statusMap as $k => $s): ?>
                <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($s['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="source" class="form-select form-select-sm">
                <option value="">— Źródło —</option>
                <option value="ksef"   <?= $source === 'ksef'   ? 'selected' : '' ?>>KSeF</option>
                <option value="manual" <?= $source === 'manual' ? 'selected' : '' ?>>Ręczna</option>
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1" title="Filtruj">
                <i class="ri-filter-3-line"></i>
            </button>
        </div>
    </div>
    <div class="row g-2 mt-1">
        <div class="col-md-2">
            <input type="date" name="date_from" class="form-control form-control-sm"
                   value="<?= h($dateFrom) ?>" placeholder="Od" title="Data wystawienia od">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_to" class="form-control form-control-sm"
                   value="<?= h($dateTo) ?>" placeholder="Do" title="Data wystawienia do">
        </div>
        <div class="col-md-3">
            <select name="payment_state" class="form-select form-select-sm">
                <option value="">— Stan płatności —</option>
                <option value="unpaid"   <?= $paymentState === 'unpaid'   ? 'selected' : '' ?>>Nieopłacone</option>
                <option value="partial"  <?= $paymentState === 'partial'  ? 'selected' : '' ?>>Częściowo opłacone</option>
                <option value="paid"     <?= $paymentState === 'paid'     ? 'selected' : '' ?>>Opłacone</option>
                <option value="overdue"  <?= $paymentState === 'overdue'  ? 'selected' : '' ?>>Przeterminowane</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="has_order" class="form-select form-select-sm">
                <option value="">— Powiązanie ze zleceniem —</option>
                <option value="with"    <?= $hasOrder === 'with'    ? 'selected' : '' ?>>Powiązane ze zleceniem</option>
                <option value="without" <?= $hasOrder === 'without' ? 'selected' : '' ?>>Bez zlecenia</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="cost_status" class="form-select form-select-sm">
                <option value="">— Status FV (workflow) —</option>
                <?php foreach ($costStatusLabels as $sv => $sl): ?>
                <option value="<?= $sv ?>" <?= $costStatusF === $sv ? 'selected' : '' ?>><?= h($sl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex justify-content-end">
            <?php if ($search || $month || $status || $source || $paymentState || $hasOrder || $dateFrom || $dateTo || $contractorNip): ?>
            <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="ri-close-line me-1"></i> Wyczyść
                <?php if ($activeFilters > 0): ?>
                    <span class="badge bg-primary"><?= $activeFilters ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
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
            <th>Miesiąc</th>
            <th>Data wyst.</th>
            <th>Termin</th>
            <th class="text-end">Brutto</th>
            <th>Wal.</th>
            <th>Płatność</th>
            <th>Źródło</th>
            <th>Status</th>
            <th style="min-width:170px" title="Workflow FV (1-9): od 'Do potwierdzenia' do 'Do wyjaśnienia'">Status FV</th>
            <th>Zlec.</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if ($total === 0): ?>
        <tr><td colspan="13" class="text-center text-muted py-4">
            Brak faktur kosztowych. <a href="<?= $this->Url->build(['action' => 'importKsef']) ?>">Importuj z KSeF</a> lub
            <a href="<?= $this->Url->build(['action' => 'add']) ?>">dodaj ręcznie</a>.
        </td></tr>
    <?php else: ?>
        <?php foreach ($invoices as $inv):
            $src = $sourceMap[$inv->source] ?? ['label' => $inv->source, 'cls' => 'bg-light text-dark', 'icon' => 'ri-file-line'];
            $st  = $statusMap[$inv->status] ?? ['label' => $inv->status, 'cls' => 'bg-light text-dark'];
            $num = $inv->invoice_number ?: ($inv->ksef_number ? '(KSeF)' : '—');
            $brutto = (float)($inv->brutto ?? 0);
            $paid   = (float)($inv->paid_amount ?? 0);
            $remain = max(0, round($brutto - $paid, 2));
            $isPaid = $inv->status === 'paid';
            $isPartial = !$isPaid && $paid > 0;

            $pdStr = $inv->payment_date instanceof \DateTimeInterface
                ? $inv->payment_date->format('Y-m-d')
                : substr((string)($inv->payment_date ?? ''), 0, 10);
            $isOverdue = !$isPaid && $pdStr && $pdStr < $today;

            $orderCount = $orderCounts[(int)$inv->id] ?? 0;
        ?>
        <tr class="<?= $isOverdue ? 'table-danger-subtle' : '' ?>">
            <td class="fw-semibold">
                <a href="<?= $this->Url->build(['action' => 'view', $inv->id]) ?>" class="text-decoration-none">
                    <?= h($num) ?>
                </a>
                <?php if ($inv->ksef_number && $inv->invoice_number): ?>
                <div class="text-muted" style="font-size:.7rem"><?= h(substr($inv->ksef_number, 0, 28)) ?>…</div>
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
            <td class="text-nowrap" style="font-size:.82rem">
                <?php if ($pdStr): ?>
                    <span class="<?= $isOverdue ? 'text-danger fw-semibold' : '' ?>"><?= $fdate($pdStr) ?></span>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
            <td class="text-end fw-semibold text-nowrap" style="font-size:.82rem">
                <?= $fnum($brutto) ?>
            </td>
            <td style="font-size:.78rem"><?= h($inv->currency ?? 'PLN') ?></td>
            <td style="min-width:130px">
                <?php if ($isPaid): ?>
                    <span class="badge bg-success-subtle text-success border" style="font-size:.7rem">
                        <i class="ri-checkbox-circle-line me-1"></i>Opłacona
                    </span>
                <?php elseif ($isPartial): ?>
                    <?php $pct = $brutto > 0 ? round(($paid / $brutto) * 100) : 0; ?>
                    <span class="badge bg-warning-subtle text-warning border" style="font-size:.7rem">
                        <i class="ri-time-line me-1"></i><?= $pct ?>% (<?= $fnum0($paid) ?> z <?= $fnum0($brutto) ?>)
                    </span>
                <?php elseif ($isOverdue): ?>
                    <?php $daysOver = (int)floor((strtotime($today) - strtotime($pdStr)) / 86400); ?>
                    <span class="badge bg-danger-subtle text-danger border" style="font-size:.7rem">
                        <i class="ri-error-warning-line me-1"></i>Przeterm. <?= $daysOver ?> dni
                    </span>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.7rem">Do zapłaty</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="badge <?= $src['cls'] ?>" style="font-size:.72rem">
                    <i class="<?= $src['icon'] ?> me-1"></i><?= $src['label'] ?>
                </span>
            </td>
            <td>
                <span class="badge <?= $st['cls'] ?>" style="font-size:.72rem"><?= $st['label'] ?></span>
            </td>
            <!-- Status FV (workflow 1-9, dropdown jak w received) -->
            <td>
                <?php
                $cs = (int)($inv->cost_status ?? 1);
                $csLbl = $costStatusLabels[$cs] ?? '—';
                $csCol = $costStatusColors[$cs] ?? 'secondary';
                ?>
                <div class="dropdown" data-cost-invoice-id="<?= (int)$inv->id ?>">
                    <button type="button"
                            class="btn btn-sm bg-<?= h($csCol) ?>-subtle text-<?= h($csCol) ?> border w-100 text-start dropdown-toggle cost-status-toggle"
                            data-bs-toggle="dropdown"
                            data-current-status="<?= $cs ?>"
                            style="font-size:.68rem;line-height:1.2;padding:.18em .45em;white-space:normal"
                            title="Kliknij aby zmienić status workflow">
                        <?= h($csLbl) ?>
                    </button>
                    <ul class="dropdown-menu shadow-sm cost-status-dropdown" data-cost-invoice-id="<?= (int)$inv->id ?>">
                        <?php foreach ($costStatusLabels as $sv => $sl):
                            $sc = $costStatusColors[$sv] ?? 'secondary';
                            $dotColor = match($sc) {
                                'warning' => '#f59e0b', 'primary' => '#3b82f6',
                                'success' => '#22c55e', 'info' => '#06b6d4',
                                'danger' => '#ef4444', 'dark' => '#1f2937',
                                'secondary' => '#6b7280', 'orange' => '#f97316',
                                default => '#9ca3af',
                            };
                        ?>
                        <li>
                            <button type="button"
                                    class="dropdown-item d-flex align-items-center gap-2 cost-status-pick <?= $sv === $cs ? 'fw-semibold' : '' ?>"
                                    data-status="<?= $sv ?>"
                                    data-cost-invoice-id="<?= (int)$inv->id ?>"
                                    style="font-size:.78rem">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $dotColor ?>"></span>
                                <?= h($sl) ?>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </td>
            <td class="text-center">
                <?php if ($orderCount > 0): ?>
                    <span class="badge bg-primary-subtle text-primary border" title="Powiązanych zleceń"><?= $orderCount ?></span>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
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
<?php
$keptForUrl = array_filter([
    'q'              => $search, 'month' => $month, 'status' => $status, 'source' => $source,
    'payment_state'  => $paymentState, 'has_order' => $hasOrder,
    'date_from'      => $dateFrom, 'date_to' => $dateTo,
    'contractor_nip' => $contractorNip,
    'cost_status'    => $costStatusF >= 1 ? $costStatusF : null,
], fn($v) => $v !== '' && $v !== null);
?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= $this->Url->build(['action' => 'index', '?' => $keptForUrl + ['page' => $p]]) ?>">
                <?= $p ?>
            </a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

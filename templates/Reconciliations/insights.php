<?php
/**
 * @var \App\View\AppView $this
 * @var array $topDebtors
 * @var int   $debtorsTotal
 * @var array $debtorTotals
 * @var array $paymentDays
 * @var array $capital
 * @var array $recentUnmatched
 * @var array $notifications
 */

$this->assign('title', 'Insights — rozliczenia');

$fnum = static fn ($v) => number_format((float)$v, 2, ',', ' ');
$fnum0 = static fn ($v) => number_format((float)$v, 0, ',', ' ');
$fdate = static function ($v): string {
    if ($v === null) return '—';
    if ($v instanceof \DateTimeInterface) return $v->format('d.m.Y');
    if (is_object($v) && method_exists($v, 'format')) return $v->format('d.m.Y');
    return substr((string)$v, 0, 10);
};
?>

<div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <h4 class="mb-0">
            <i class="ri-bar-chart-2-line text-primary me-1"></i>
            Insights — rozliczenia
        </h4>
        <span class="badge bg-light text-muted border">Analityka + powiadomienia</span>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>"
           class="btn btn-sm btn-outline-secondary ms-auto">
            <i class="ri-arrow-left-line me-1"></i>Powrót do rozliczeń
        </a>
    </div>

    <!-- ── Powiadomienia "Did you know?" ──────────────────────────────────── -->
    <?php if (!empty($notifications)): ?>
        <div class="card mb-3 shadow-sm">
            <div class="card-header bg-warning-subtle py-2 d-flex align-items-center gap-2">
                <i class="ri-notification-3-line text-warning"></i>
                <strong>Powiadomienia (<?= count($notifications) ?>)</strong>
                <small class="text-muted ms-auto">Heurystyki analityczne — sprawdź co wymaga uwagi</small>
            </div>
            <div class="card-body p-0">
                <?php foreach ($notifications as $n): ?>
                    <?php $color = $n['type'] === 'warning' ? 'warning' : ($n['type'] === 'danger' ? 'danger' : 'info'); ?>
                    <div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom small">
                        <i class="<?= h($n['icon']) ?> text-<?= $color ?> fs-6 flex-shrink-0 mt-1"></i>
                        <div>
                            <strong><?= h($n['title']) ?></strong>
                            <span class="text-muted">— <?= h($n['text']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- ── Top dłużnicy ──────────────────────────────────────────────── -->
        <div class="col-lg-12">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex align-items-center gap-2 flex-wrap">
                    <i class="ri-user-warning-line text-danger"></i>
                    <strong>Top dłużnicy</strong>
                    <span class="badge bg-secondary-subtle text-secondary"><?= (int)$debtorsTotal ?></span>
                    <small class="text-muted ms-auto">sortowane wg PLN-ekwiwalent</small>
                </div>

                <!-- Globalne sumy per waluta -->
                <?php if (!empty($debtorTotals)): ?>
                    <div class="px-3 py-2 bg-light border-bottom d-flex flex-wrap gap-3 align-items-center">
                        <span class="small text-muted">Łącznie niezapłacone:</span>
                        <?php
                            // PLN najpierw, EUR drugi, reszta dalej
                            $orderedKeys = array_unique(array_merge(['PLN', 'EUR'], array_keys($debtorTotals)));
                            foreach ($orderedKeys as $curr) {
                                if (!isset($debtorTotals[$curr])) continue;
                                $isPln = $curr === 'PLN';
                                $cls   = $isPln ? 'text-primary' : 'text-success';
                        ?>
                            <div class="d-flex flex-column">
                                <span class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em"><?= h($curr) ?></span>
                                <span class="fw-bold <?= $cls ?>"><?= $fnum($debtorTotals[$curr]) ?>&nbsp;<?= h($curr) ?></span>
                            </div>
                        <?php } ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($topDebtors)): ?>
                    <div class="card-body text-muted small fst-italic">Brak dłużników — wszystko zapłacone 🎉</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 small" id="debtorsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:30px">#</th>
                                    <th>Kontrahent</th>
                                    <th class="text-end" style="width:60px">Faktury</th>
                                    <th class="text-end" style="width:80px">Przeterm.</th>
                                    <th class="text-end">Niezapłacone</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $renderDebtorRow = function ($d, $i) use ($fnum) { ?>
                                    <tr>
                                        <td class="text-muted"><?= $i + 1 ?></td>
                                        <td class="text-truncate" style="max-width:280px" title="<?= h($d['name']) ?>">
                                            <?= h($d['name']) ?: '—' ?>
                                            <?php if (($d['nip'] ?? '') !== '_unknown_'): ?>
                                                <div class="text-muted" style="font-size:.7em">NIP <?= h($d['nip']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= (int)$d['unpaid_count'] ?></td>
                                        <td class="text-end">
                                            <?php if (($d['overdue_count'] ?? 0) > 0): ?>
                                                <span class="badge bg-danger-subtle text-danger border" style="font-size:.65rem">
                                                    <?= (int)$d['overdue_count'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php
                                                $orderedKeys = array_unique(array_merge(['PLN', 'EUR'], array_keys($d['by_currency'])));
                                                $parts = [];
                                                foreach ($orderedKeys as $c) {
                                                    if (!isset($d['by_currency'][$c])) continue;
                                                    $isPln = $c === 'PLN';
                                                    $cls   = $isPln ? 'text-primary' : 'text-success';
                                                    $parts[] = '<span class="fw-semibold ' . $cls . '">' . $fnum($d['by_currency'][$c]) . '&nbsp;' . h($c) . '</span>';
                                                }
                                                echo implode(' <span class="text-muted">+</span> ', $parts);
                                            ?>
                                        </td>
                                    </tr>
                                <?php };
                                foreach ($topDebtors as $i => $d) $renderDebtorRow($d, $i);
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($debtorsTotal > count($topDebtors)): ?>
                        <div class="card-footer py-2 text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnLoadMoreDebtors"
                                    data-offset="<?= count($topDebtors) ?>"
                                    data-total="<?= (int)$debtorsTotal ?>">
                                <i class="ri-arrow-down-line me-1"></i>
                                Załaduj więcej (<?= $debtorsTotal - count($topDebtors) ?> pozostało)
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Najwolniej płacący ────────────────────────────────────────── -->
        <div class="col-lg-12">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-time-line text-warning"></i>
                    <strong>Najdłuższy czas zapłaty</strong>
                    <small class="text-muted ms-auto">średnia dni od wystawienia · min. 3 wpłaty</small>
                </div>
                <?php if (empty($paymentDays)): ?>
                    <div class="card-body text-muted small fst-italic">Brak danych — za mało potwierdzonych wpłat.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Kontrahent</th>
                                    <th class="text-end">Próbka</th>
                                    <th class="text-end">Śr. dni</th>
                                    <th class="text-end">Suma PLN</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentDays as $p): ?>
                                    <tr>
                                        <td class="text-truncate" style="max-width:200px" title="<?= h($p['name']) ?>">
                                            <?= h($p['name']) ?: '—' ?>
                                            <div class="text-muted" style="font-size:.7em">NIP <?= h($p['nip']) ?></div>
                                        </td>
                                        <td class="text-end"><?= (int)$p['sample_size'] ?></td>
                                        <td class="text-end">
                                            <?php
                                                $days = (int)round((float)$p['avg_days']);
                                                $cls  = $days > 30 ? 'text-danger fw-bold' : ($days > 14 ? 'text-warning' : 'text-success');
                                            ?>
                                            <span class="<?= $cls ?>"><?= $days ?> dni</span>
                                        </td>
                                        <td class="text-end text-muted"><?= $fnum0($p['total_paid']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Kapitał w czasie (chart) ──────────────────────────────────── -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-line-chart-line text-primary"></i>
                    <strong>Kapitał w czasie (ostatnie 12 miesięcy)</strong>
                    <small class="text-muted ms-auto">wystawiono · zapłacono · pozostało (w PLN, raw sum)</small>
                </div>
                <div class="card-body">
                    <?php if (empty($capital)): ?>
                        <div class="text-muted small fst-italic">Brak danych.</div>
                    <?php else: ?>
                        <canvas id="capitalChart" style="max-height:260px"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Ostatnie niewykorzystane przelewy ─────────────────────────── -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex align-items-center gap-2">
                    <i class="ri-exchange-line text-info"></i>
                    <strong>Ostatnie niewykorzystane przelewy</strong>
                    <small class="text-muted ms-auto">±30 dni, max 15 — kandydaci do powiązania</small>
                </div>
                <?php if (empty($recentUnmatched)): ?>
                    <div class="card-body text-muted small fst-italic">Wszystkie przelewy są już powiązane 👌</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Nadawca</th>
                                    <th>Tytuł / parsed_inv</th>
                                    <th class="text-end">Kwota</th>
                                    <th class="text-end">Akcja</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUnmatched as $t): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= h($fdate($t->value_date)) ?></td>
                                        <td class="text-truncate" style="max-width:200px" title="<?= h($t->party_name ?? '') ?>">
                                            <?= h($t->party_name ?? '—') ?>
                                        </td>
                                        <td class="text-muted text-truncate" style="max-width:260px;font-size:.72rem">
                                            <?= h(mb_substr($t->title ?? '', 0, 80)) ?>
                                            <?php if (!empty($t->parsed_inv)): ?>
                                                <span class="badge bg-info-subtle text-info border ms-1" style="font-size:.6em">
                                                    <?= h($t->parsed_inv) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            <?= $fnum($t->amount) ?> <?= h($t->currency ?? 'PLN') ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'BankTransactions', 'action' => 'transactions', '?' => ['q' => $t->parsed_inv ?: ($t->party_name ?? '')]]) ?>"
                                               class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size:.7rem"
                                               title="Otwórz w transakcjach">
                                                <i class="ri-arrow-right-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // ── "Załaduj więcej" dla listy dłużników ──────────────────────────────
    var btn = document.getElementById('btnLoadMoreDebtors');
    if (!btn) return;
    var tbody = document.querySelector('#debtorsTable tbody');
    if (!tbody) return;

    function fmtAmount(v) {
        return parseFloat(v || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ').replace('.', ',');
    }
    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function renderRow(d, i) {
        var orderedKeys = Array.from(new Set(['PLN', 'EUR'].concat(Object.keys(d.by_currency || {}))));
        var parts = [];
        orderedKeys.forEach(function (c) {
            if (!d.by_currency || d.by_currency[c] === undefined) return;
            var cls = c === 'PLN' ? 'text-primary' : 'text-success';
            parts.push('<span class="fw-semibold ' + cls + '">' + fmtAmount(d.by_currency[c]) + ' ' + esc(c) + '</span>');
        });
        var amountsHtml = parts.join(' <span class="text-muted">+</span> ');
        var nipHtml = (d.nip && d.nip !== '_unknown_')
            ? '<div class="text-muted" style="font-size:.7em">NIP ' + esc(d.nip) + '</div>'
            : '';
        var overdueHtml = (d.overdue_count > 0)
            ? '<span class="badge bg-danger-subtle text-danger border" style="font-size:.65rem">' + d.overdue_count + '</span>'
            : '<span class="text-muted">—</span>';

        return '<tr>'
             + '<td class="text-muted">' + (i + 1) + '</td>'
             + '<td class="text-truncate" style="max-width:280px" title="' + esc(d.name) + '">' + (esc(d.name) || '—') + nipHtml + '</td>'
             + '<td class="text-end">' + d.unpaid_count + '</td>'
             + '<td class="text-end">' + overdueHtml + '</td>'
             + '<td class="text-end">' + amountsHtml + '</td>'
             + '</tr>';
    }

    btn.addEventListener('click', function () {
        if (this.disabled) return;
        var offset = parseInt(this.dataset.offset, 10) || 0;
        var origHtml = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Ładowanie…';

        fetch('/rozliczenia/ksef/insights/top-debtors?offset=' + offset + '&limit=10', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            (d.debtors || []).forEach(function (deb, idx) {
                tbody.insertAdjacentHTML('beforeend', renderRow(deb, offset + idx));
            });
            var newOffset = offset + (d.debtors || []).length;
            btn.dataset.offset = newOffset;
            if (!d.has_more) {
                btn.remove();
            } else {
                btn.disabled = false;
                var remaining = (d.total || 0) - newOffset;
                btn.innerHTML = '<i class="ri-arrow-down-line me-1"></i>Załaduj więcej (' + remaining + ' pozostało)';
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            alert('Błąd ładowania danych');
        });
    });
})();
</script>

<?php if (!empty($capital)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    var ctx = document.getElementById('capitalChart');
    if (!ctx) return;
    var data = <?= json_encode($capital, JSON_UNESCAPED_UNICODE) ?>;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(function (r) { return r.month; }),
            datasets: [
                { label: 'Wystawiono', data: data.map(r => parseFloat(r.billed_total)), backgroundColor: 'rgba(59, 130, 246, .7)' },
                { label: 'Zapłacono',  data: data.map(r => parseFloat(r.paid_total)),   backgroundColor: 'rgba(22, 163, 74, .7)' },
                { label: 'Pozostało',  data: data.map(r => parseFloat(r.remaining_total)), backgroundColor: 'rgba(220, 38, 38, .7)' },
            ]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('pl-PL') } } },
            plugins: { legend: { position: 'top', align: 'end' } }
        }
    });
})();
</script>
<?php endif; ?>

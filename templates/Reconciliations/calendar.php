<?php
/**
 * @var \App\View\AppView $this
 * @var string $ym
 * @var int    $year
 * @var int    $month
 * @var string $mode
 * @var array  $weeks
 * @var array  $byDate
 * @var string $firstDay
 * @var string $lastDay
 * @var string $today
 * @var string $prevYM
 * @var string $nextYM
 */

$this->assign('title', 'Kalendarz rozliczeń');

$polishMonths = ['','styczeń','luty','marzec','kwiecień','maj','czerwiec',
                 'lipiec','sierpień','wrzesień','październik','listopad','grudzień'];
$polishDays = ['Pon','Wt','Śr','Czw','Pt','Sob','Nd'];

$fnum  = static fn ($v) => number_format((float)$v, 2, ',', ' ');
$fnum0 = static fn ($v) => number_format((float)$v, 0, ',', ' ');

// Helper: stats dla danego dnia
$dayStats = function (string $date) use ($byDate) {
    $list = $byDate[$date] ?? [];
    $paid = 0; $overdue = 0; $pending = 0;
    $totalPln = 0; $totalEur = 0;
    foreach ($list as $i) {
        if ($i['paymentstate'] === 'paid') $paid++;
        elseif ($i['is_overdue']) $overdue++;
        else $pending++;
        $curr = strtoupper($i['currency'] ?: 'PLN');
        if ($curr === 'EUR') $totalEur += (float)$i['remaining'];
        else $totalPln += (float)$i['remaining'];
    }
    return compact('list', 'paid', 'overdue', 'pending', 'totalPln', 'totalEur');
};
?>

<div class="container-fluid py-3">
    <!-- ── Toolbar ────────────────────────────────────────────────────────── -->
    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <h4 class="mb-0">
            <i class="ri-calendar-line text-primary me-1"></i>
            Kalendarz rozliczeń
        </h4>

        <!-- Month nav -->
        <div class="btn-group" role="group">
            <a href="<?= $this->Url->build(['action' => 'calendar', $prevYM, '?' => ['mode' => $mode]]) ?>"
               class="btn btn-sm btn-outline-secondary" title="Poprzedni miesiąc">
                <i class="ri-arrow-left-s-line"></i>
            </a>
            <span class="btn btn-sm btn-light disabled fw-semibold" style="min-width:180px">
                <?= h(ucfirst($polishMonths[$month])) ?> <?= $year ?>
            </span>
            <a href="<?= $this->Url->build(['action' => 'calendar', $nextYM, '?' => ['mode' => $mode]]) ?>"
               class="btn btn-sm btn-outline-secondary" title="Następny miesiąc">
                <i class="ri-arrow-right-s-line"></i>
            </a>
            <a href="<?= $this->Url->build(['action' => 'calendar', '?' => ['mode' => $mode]]) ?>"
               class="btn btn-sm btn-outline-primary" title="Bieżący miesiąc">
                Dziś
            </a>
        </div>

        <!-- Mode toggle -->
        <div class="btn-group" role="group">
            <a href="<?= $this->Url->build(['action' => 'calendar', $ym, '?' => ['mode' => 'paymentdate']]) ?>"
               class="btn btn-sm <?= $mode === 'paymentdate' ? 'btn-primary' : 'btn-outline-secondary' ?>"
               title="Wg terminu z faktury">
                <i class="ri-file-list-3-line"></i> Termin z faktury
            </a>
            <a href="<?= $this->Url->build(['action' => 'calendar', $ym, '?' => ['mode' => 'effective']]) ?>"
               class="btn btn-sm <?= $mode === 'effective' ? 'btn-primary' : 'btn-outline-secondary' ?>"
               title="Wg efektywnego terminu od daty wysyłki dokumentów">
                <i class="ri-truck-line"></i> Efektywny (od wysyłki)
            </a>
        </div>

        <!-- Back link -->
        <a href="<?= $this->Url->build(['action' => 'index']) ?>"
           class="btn btn-sm btn-outline-secondary ms-auto">
            <i class="ri-arrow-left-line me-1"></i>Powrót
        </a>
    </div>

    <!-- Info -->
    <div class="alert alert-info py-2 small mb-3">
        <i class="ri-information-line me-1"></i>
        Tryb: <strong><?= $mode === 'effective' ? 'Efektywny termin' : 'Termin z faktury' ?></strong> —
        <?php if ($mode === 'effective'): ?>
            faktury z polem <code>sent_at</code> mają termin liczony od daty wysyłki + dni płatności (jeśli brak <code>sent_at</code> używamy <code>paymentdate</code>).
        <?php else: ?>
            tylko <code>paymentdate</code> z faktury (klasyczna data).
        <?php endif; ?>
    </div>

    <!-- ── Calendar grid ──────────────────────────────────────────────────── -->
    <div class="card shadow-sm">
        <div class="card-body p-2">
            <table class="table table-bordered mb-0 calendar-table" style="table-layout:fixed">
                <thead>
                    <tr class="bg-light">
                        <?php foreach ($polishDays as $d): ?>
                            <th class="text-center text-muted py-1" style="width:14.28%;font-size:.85rem"><?= h($d) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weeks as $week): ?>
                        <tr>
                            <?php foreach ($week as $date):
                                $stats = $dayStats($date);
                                $dayNum = (int)substr($date, -2);
                                $isCurrentMonth = (int)substr($date, 5, 2) === $month;
                                $isToday = $date === $today;
                                $hasInvoices = !empty($stats['list']);
                                $hasOverdue  = $stats['overdue'] > 0;
                                $hasPending  = $stats['pending'] > 0;
                                $allPaid     = $hasInvoices && $stats['overdue'] === 0 && $stats['pending'] === 0;

                                $cellClass = 'cal-cell align-top';
                                if (!$isCurrentMonth) $cellClass .= ' bg-light text-muted';
                                if ($isToday)         $cellClass .= ' cal-today';
                                if ($hasInvoices)    $cellClass .= ' cal-clickable';
                            ?>
                                <td class="<?= $cellClass ?>"
                                    <?php if ($hasInvoices): ?>
                                        data-date="<?= h($date) ?>"
                                        data-day-label="<?= h(date('d.m.Y', strtotime($date))) ?>"
                                        style="cursor:pointer"
                                    <?php endif; ?>>

                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <span class="cal-day-num <?= $isToday ? 'fw-bold text-primary' : '' ?>"
                                              style="font-size:.85rem"><?= $dayNum ?></span>
                                        <?php if ($hasInvoices): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.62rem">
                                                <?= count($stats['list']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($hasInvoices): ?>
                                        <!-- Kropki statusu -->
                                        <div class="d-flex gap-1 mb-1 flex-wrap">
                                            <?php if ($stats['overdue'] > 0): ?>
                                                <span class="cal-dot bg-danger" title="Przeterminowane: <?= $stats['overdue'] ?>">
                                                    <?= $stats['overdue'] ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($stats['pending'] > 0): ?>
                                                <span class="cal-dot bg-warning text-dark" title="Oczekujące: <?= $stats['pending'] ?>">
                                                    <?= $stats['pending'] ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($stats['paid'] > 0): ?>
                                                <span class="cal-dot bg-success" title="Zapłacone: <?= $stats['paid'] ?>">
                                                    <?= $stats['paid'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Suma dnia -->
                                        <?php if ($stats['totalPln'] > 0 || $stats['totalEur'] > 0): ?>
                                            <div class="cal-sum" style="font-size:.7rem;line-height:1.2">
                                                <?php if ($stats['totalPln'] > 0): ?>
                                                    <div class="text-primary fw-semibold"><?= $fnum0($stats['totalPln']) ?> PLN</div>
                                                <?php endif; ?>
                                                <?php if ($stats['totalEur'] > 0): ?>
                                                    <div class="text-success fw-semibold"><?= $fnum0($stats['totalEur']) ?> EUR</div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Legenda -->
    <div class="mt-3 d-flex gap-3 flex-wrap small text-muted">
        <span><span class="cal-dot bg-danger d-inline-block">×</span> przeterminowane</span>
        <span><span class="cal-dot bg-warning text-dark d-inline-block">×</span> oczekujące</span>
        <span><span class="cal-dot bg-success d-inline-block">×</span> zapłacone</span>
        <span><span class="badge bg-secondary-subtle text-secondary border">N</span> łączna liczba</span>
    </div>
</div>

<!-- ── Modal: faktury z wybranego dnia ──────────────────────────────────── -->
<div class="modal fade" id="calendarDayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="calendarDayTitle">Faktury z dnia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="calendarDayBody"></div>
        </div>
    </div>
</div>

<style>
.calendar-table td.cal-cell {
    height: 110px;
    padding: 6px 8px;
    vertical-align: top;
    transition: background-color .15s;
}
.calendar-table td.cal-clickable:hover {
    background-color: #eff6ff !important;
}
.calendar-table td.cal-today {
    background-color: rgba(13, 110, 253, .06);
    border: 2px solid #0d6efd !important;
}
.cal-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 4px;
    border-radius: 11px;
    font-size: .68rem;
    font-weight: 700;
    color: #fff;
}
.cal-dot.bg-warning { color: #78350f !important; }
.cal-sum {
    border-top: 1px dashed #e5e7eb;
    padding-top: 4px;
    margin-top: 2px;
}
</style>

<script>
(function () {
    'use strict';
    var modal = new bootstrap.Modal(document.getElementById('calendarDayModal'));
    var byDate = <?= json_encode($byDate, JSON_UNESCAPED_UNICODE) ?>;

    function fmtAmount(v) {
        return parseFloat(v || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ').replace('.', ',');
    }
    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    var stateLabels = { 'paid': 'Zapłacona', 'partial': 'Częściowo', 'unpaid': 'Do zapłaty' };
    var stateColors = { 'paid': 'success', 'partial': 'warning', 'unpaid': 'secondary' };

    function renderDayModal(date, label) {
        var list = byDate[date] || [];
        document.getElementById('calendarDayTitle').textContent = 'Faktury z dnia ' + label + ' (' + list.length + ')';

        if (!list.length) {
            document.getElementById('calendarDayBody').innerHTML = '<div class="p-3 text-muted small fst-italic">Brak faktur na ten dzień.</div>';
            return;
        }

        var html = '<div class="table-responsive"><table class="table table-sm table-hover small mb-0">'
                 + '<thead class="table-light"><tr>'
                 + '<th>Faktura</th><th>Kontrahent</th><th>Termin fakturowy</th><th>Termin ef.</th>'
                 + '<th class="text-end">Brutto</th><th class="text-end">Pozostało</th><th>Status</th>'
                 + '</tr></thead><tbody>';

        list.forEach(function (inv) {
            var stateLbl = stateLabels[inv.paymentstate] || inv.paymentstate;
            var stateCol = (inv.is_overdue && inv.paymentstate !== 'paid') ? 'danger' : (stateColors[inv.paymentstate] || 'secondary');
            var effHtml  = inv.effective_due
                ? (inv.effective_due !== inv.paymentdate
                    ? '<span class="text-info">' + esc(inv.effective_due) + '</span>'
                    : '<span class="text-muted">—</span>')
                : '<span class="text-muted">—</span>';

            html += '<tr>'
                  + '<td><a href="/invoices/view/' + esc(inv.id) + '" class="text-dark fw-semibold text-decoration-none">' + esc(inv.fullnumber) + '</a></td>'
                  + '<td class="text-truncate" style="max-width:220px" title="' + esc(inv.contractor) + '">' + (esc(inv.contractor) || '—') + '</td>'
                  + '<td class="text-nowrap small">' + esc(inv.paymentdate || '—') + '</td>'
                  + '<td class="text-nowrap small">' + effHtml + '</td>'
                  + '<td class="text-end small">' + fmtAmount(inv.total) + ' ' + esc(inv.currency) + '</td>'
                  + '<td class="text-end small fw-semibold">' + fmtAmount(inv.remaining) + ' ' + esc(inv.currency) + '</td>'
                  + '<td><span class="badge bg-' + stateCol + '-subtle text-' + stateCol + ' border" style="font-size:.65rem">' + esc(stateLbl) + (inv.is_overdue && inv.paymentstate !== 'paid' ? ' (przeterm.)' : '') + '</span></td>'
                  + '</tr>';
        });
        html += '</tbody></table></div>';
        document.getElementById('calendarDayBody').innerHTML = html;
    }

    document.querySelectorAll('.calendar-table td.cal-clickable').forEach(function (td) {
        td.addEventListener('click', function () {
            var d = this.dataset.date;
            var l = this.dataset.dayLabel;
            if (!d) return;
            renderDayModal(d, l);
            modal.show();
        });
    });
})();
</script>

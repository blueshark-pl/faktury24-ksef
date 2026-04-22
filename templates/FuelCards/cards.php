<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $accounts
 * @var \Cake\ORM\ResultSet $cardsQuery
 * @var string $title
 */
$this->assign('title', $title);
$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i') : substr((string)$v, 0, 16)) : '—';
$fnum  = fn($v, $d = 2) => $v !== null ? number_format((float)$v, $d, ',', ' ') : '—';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-bank-card-2-line me-1 text-warning"></i> Karty paliwowe E100
    </h4>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'index']) ?>">
            <i class="ri-list-check me-1"></i> Transakcje
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="<?= $this->Url->build(['action' => 'cards']) ?>">
            <i class="ri-bank-card-2-line me-1"></i> Karty
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'limits']) ?>">
            <i class="ri-funds-line me-1"></i> Limity
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'stations']) ?>">
            <i class="ri-map-pin-2-line me-1"></i> Stacje
        </a>
    </li>
</ul>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle small">
        <thead class="table-light">
            <tr>
                <th>Numer karty</th>
                <th>Nr skrócony</th>
                <th>Pojazd</th>
                <th>Konto</th>
                <th class="text-end">Transakcji</th>
                <th class="text-end">Suma EUR</th>
                <th>Ostatnia transakcja</th>
                <th>Status (E100)</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cardsQuery as $row): ?>
                <tr>
                    <td class="font-monospace" style="font-size:.75rem">
                        <?= h($row->card ?? '—') ?>
                    </td>
                    <td><?= h($row->card_shortname ?? '—') ?></td>
                    <td>
                        <?php if ($row->auto): ?>
                            <span class="badge bg-light text-dark border"><?= h($row->auto) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-muted"><?= h($row->e100_account_id ?? '—') ?></td>
                    <td class="text-end"><?= $fnum($row->tx_count ?? 0, 0) ?></td>
                    <td class="text-end fw-semibold"><?= $fnum($row->total_sum ?? 0) ?></td>
                    <td><?= $fdate($row->last_date ?? null) ?></td>
                    <td>
                        <span id="card-status-<?= md5((string)$row->card) ?>" class="text-muted small">
                            <i class="ri-loader-4-line spin"></i>
                        </span>
                    </td>
                    <td class="text-nowrap">
                        <!-- Przycisk blokady -->
                        <button type="button"
                                class="btn btn-outline-danger btn-sm btn-block-card"
                                data-card="<?= h($row->card ?? '') ?>"
                                data-account-id="<?= h($row->e100_account_id ?? '') ?>"
                                title="Zablokuj kartę">
                            <i class="ri-lock-line"></i>
                        </button>
                        <!-- Filtru transakcji po karcie -->
                        <?= $this->Html->link(
                            '<i class="ri-list-check"></i>',
                            ['action' => 'index', '?' => ['card' => $row->card]],
                            ['class' => 'btn btn-outline-secondary btn-sm ms-1', 'escape' => false, 'title' => 'Pokaż transakcje']
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal blokady karty -->
<div class="modal fade" id="blockCardModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="ri-lock-line me-1"></i> Blokada karty</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Czy na pewno zablokować kartę:</p>
                <strong id="block-card-number" class="font-monospace"></strong>
                <div id="block-result" class="mt-2 d-none alert"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Anuluj</button>
                <button class="btn btn-danger btn-sm" id="btn-block-confirm">
                    <i class="ri-lock-line me-1"></i> Zablokuj
                </button>
            </div>
        </div>
    </div>
</div>

<style>.spin { animation: spin 1s linear infinite; } @keyframes spin { from{transform:rotate(0deg)}to{transform:rotate(360deg)} }</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = '<?= $this->request->getAttribute('csrfToken') ?>';

    // Pobierz statusy kart (info)
    document.querySelectorAll('.btn-block-card').forEach(function (btn) {
        const card      = btn.dataset.card;
        const accountId = btn.dataset.accountId;
        const statusEl  = document.getElementById('card-status-' + md5(card));

        if (!card || !statusEl) return;

        fetch('<?= $this->Url->build(['action' => 'cardInfo']) ?>?card=' + encodeURIComponent(card) + '&account_id=' + encodeURIComponent(accountId))
            .then(r => r.json())
            .then(d => {
                if (d.success && d.info) {
                    const status = d.info.status || 'Unknown';
                    const cls    = status === 'Active' ? 'text-success' : 'text-danger';
                    statusEl.innerHTML = '<span class="' + cls + '">' + status + '</span>';
                } else {
                    statusEl.innerHTML = '<span class="text-muted">N/A</span>';
                }
            })
            .catch(() => { statusEl.innerHTML = '<span class="text-muted">—</span>'; });
    });

    // Blokada karty
    let blockCard = '', blockAccountId = '';
    document.querySelectorAll('.btn-block-card').forEach(function (btn) {
        btn.addEventListener('click', function () {
            blockCard      = this.dataset.card;
            blockAccountId = this.dataset.accountId;
            document.getElementById('block-card-number').textContent = blockCard;
            document.getElementById('block-result').className = 'mt-2 d-none alert';
            document.getElementById('block-result').textContent = '';
            new bootstrap.Modal(document.getElementById('blockCardModal')).show();
        });
    });

    document.getElementById('btn-block-confirm')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        const fd = new FormData();
        fd.append('_csrfToken', csrfToken);
        fd.append('card_number', blockCard);
        fd.append('account_id', blockAccountId);

        fetch('<?= $this->Url->build(['action' => 'blockCard']) ?>', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                const el = document.getElementById('block-result');
                el.classList.remove('d-none', 'alert-success', 'alert-danger');
                el.classList.add('alert', d.success ? 'alert-success' : 'alert-danger');
                el.textContent = d.message;
                btn.disabled = false;
            })
            .catch(() => { btn.disabled = false; });
    });

    function md5(str) {
        // Prosty hash dla ID elementu (nie kryptograficzny)
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = ((hash << 5) - hash) + str.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash).toString(16);
    }
});
</script>

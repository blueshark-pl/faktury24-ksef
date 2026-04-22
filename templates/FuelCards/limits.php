<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $accounts
 * @var string $title
 */
$this->assign('title', $title);
$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i') : substr((string)$v, 0, 16)) : '—';
$fnum  = fn($v, $d = 2) => $v !== null ? number_format((float)$v, $d, ',', ' ') : '—';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-funds-line me-1 text-primary"></i> Limity klientów E100
    </h4>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'index']) ?>">
            <i class="ri-list-check me-1"></i> Transakcje
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'cards']) ?>">
            <i class="ri-bank-card-2-line me-1"></i> Karty
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" href="<?= $this->Url->build(['action' => 'limits']) ?>">
            <i class="ri-funds-line me-1"></i> Limity
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="<?= $this->Url->build(['action' => 'stations']) ?>">
            <i class="ri-map-pin-2-line me-1"></i> Stacje
        </a>
    </li>
</ul>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold py-2">
                <i class="ri-search-2-line me-1"></i> Sprawdź / zmień limit
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Konto E100</label>
                    <select class="form-select form-select-sm" id="limit-account-id">
                        <option value="">— wybierz konto —</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= h($acc->id) ?>">
                                <?= h($acc->label ?: $acc->username) ?>
                                <?php if ($acc->client_code): ?>
                                    (<?= h($acc->client_code) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Kod klienta (client_code)</label>
                    <input type="text" class="form-control form-control-sm" id="limit-client-code"
                           placeholder="Pozostaw puste = konto domyślne">
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" id="btn-get-limit">
                        <i class="ri-refresh-line me-1"></i> Pobierz limit
                    </button>
                </div>
                <div id="limit-result" class="mt-3 d-none">
                    <hr>
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="rounded border p-2">
                                <div class="small text-muted">Aktualny limit</div>
                                <div class="fw-bold fs-5" id="lim-credit">—</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="rounded border p-2">
                                <div class="small text-muted">Waluta</div>
                                <div class="fw-bold fs-5" id="lim-currency">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 text-center mt-1">
                        <div class="col-6">
                            <div class="rounded border p-2">
                                <div class="small text-muted">Zużyto</div>
                                <div id="lim-used">—</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="rounded border p-2">
                                <div class="small text-muted">Dostępne</div>
                                <div id="lim-available">—</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="limit-error" class="alert alert-danger mt-2 d-none"></div>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header bg-light fw-semibold py-2">
                <i class="ri-edit-line me-1"></i> Ustaw nowy limit
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Konto E100</label>
                    <select class="form-select form-select-sm" id="set-account-id">
                        <option value="">— wybierz konto —</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= h($acc->id) ?>">
                                <?= h($acc->label ?: $acc->username) ?>
                                <?php if ($acc->client_code): ?>
                                    (<?= h($acc->client_code) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Kod klienta (client_code)</label>
                    <input type="text" class="form-control form-control-sm" id="set-client-code"
                           placeholder="Pozostaw puste = konto domyślne">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nowy limit (wartość w walucie konta)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                           id="set-credit-value" placeholder="np. 5000.00">
                </div>
                <button class="btn btn-primary btn-sm" id="btn-set-limit">
                    <i class="ri-save-line me-1"></i> Zapisz limit
                </button>
                <div id="set-result" class="mt-2 d-none alert"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = '<?= $this->request->getAttribute('csrfToken') ?>';

    document.getElementById('btn-get-limit')?.addEventListener('click', function () {
        const accountId  = document.getElementById('limit-account-id').value;
        const clientCode = document.getElementById('limit-client-code').value;
        if (!accountId) { alert('Wybierz konto E100'); return; }

        const btn = this;
        btn.disabled = true;
        document.getElementById('limit-result').classList.add('d-none');
        document.getElementById('limit-error').classList.add('d-none');

        let url = '<?= $this->Url->build(['action' => 'getLimit']) ?>?account_id=' + encodeURIComponent(accountId);
        if (clientCode) url += '&client_code=' + encodeURIComponent(clientCode);

        fetch(url)
            .then(r => r.json())
            .then(d => {
                btn.disabled = false;
                if (d.success) {
                    const l = d.limit || {};
                    document.getElementById('lim-credit').textContent   = l.credit     != null ? l.credit    : '—';
                    document.getElementById('lim-currency').textContent = l.defcur      || '—';
                    document.getElementById('lim-used').textContent     = l.creditUsed != null ? l.creditUsed : '—';
                    document.getElementById('lim-available').textContent = l.creditAvailable != null ? l.creditAvailable : '—';
                    document.getElementById('limit-result').classList.remove('d-none');
                } else {
                    const errEl = document.getElementById('limit-error');
                    errEl.textContent = d.error || 'Błąd pobierania limitu.';
                    errEl.classList.remove('d-none');
                }
            })
            .catch(e => {
                btn.disabled = false;
                const errEl = document.getElementById('limit-error');
                errEl.textContent = 'Błąd sieci: ' + e.message;
                errEl.classList.remove('d-none');
            });
    });

    document.getElementById('btn-set-limit')?.addEventListener('click', function () {
        const accountId  = document.getElementById('set-account-id').value;
        const clientCode = document.getElementById('set-client-code').value;
        const credit     = document.getElementById('set-credit-value').value;
        if (!accountId) { alert('Wybierz konto E100'); return; }
        if (credit === '' || isNaN(parseFloat(credit))) { alert('Podaj wartość limitu'); return; }

        const btn = this;
        btn.disabled = true;
        const resultEl = document.getElementById('set-result');
        resultEl.className = 'mt-2 d-none alert';

        const fd = new FormData();
        fd.append('_csrfToken', csrfToken);
        fd.append('account_id', accountId);
        if (clientCode) fd.append('client_code', clientCode);
        fd.append('credit', credit);

        fetch('<?= $this->Url->build(['action' => 'setLimit']) ?>', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                btn.disabled = false;
                resultEl.classList.remove('d-none');
                resultEl.classList.add(d.success ? 'alert-success' : 'alert-danger');
                resultEl.textContent = d.message || (d.success ? 'Limit zapisany.' : 'Błąd zapisu.');
            })
            .catch(e => {
                btn.disabled = false;
                resultEl.classList.remove('d-none');
                resultEl.classList.add('alert-danger');
                resultEl.textContent = 'Błąd sieci: ' + e.message;
            });
    });
});
</script>

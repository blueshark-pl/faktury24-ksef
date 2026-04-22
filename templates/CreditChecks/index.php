<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Datasource\ResultSetInterface $records
 * @var string $tab
 * @var string $search
 * @var array $counts
 * @var array $statusLabels
 * @var array $adviceTypes
 * @var array $errorTypes
 * @var \App\Model\Entity\CreditCheck|null $lastSync
 */

$this->assign('title', 'Kredyt kupiecki');
$csrf = (string)$this->request->getAttribute('csrfToken');
?>

<div class="page-header">
    <div class="page-leftheader">
        <h4 class="page-title mb-0">
            <i class="ri-shield-check-line me-1 text-primary"></i>
            Kredyt kupiecki (Allianz Trade)
        </h4>
        <?php if ($lastSync): ?>
            <small class="text-muted ms-2">
                Ostatni sync: <?= $lastSync->synced_at->format('d.m.Y H:i') ?>
            </small>
        <?php endif ?>
    </div>
    <div class="page-rightheader">
        <button id="btn-sync" class="btn btn-primary btn-sm" data-list="all">
            <i class="ri-refresh-line me-1"></i>Synchronizuj wszystko
        </button>
    </div>
</div>

<!-- Alerty sync -->
<div id="sync-alert" class="d-none mb-3"></div>

<!-- Taby -->
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="creditTabs">

            <li class="nav-item">
                <?= $this->Html->link(
                    '<i class="ri-checkbox-circle-line me-1"></i>Opinie wydane'
                    . ' <span class="badge bg-success ms-1">' . $counts['done'] . '</span>',
                    ['action' => 'index', '?' => ['tab' => 'done', 'search' => $search ?: null]],
                    ['escape' => false, 'class' => 'nav-link ' . ($tab === 'done' ? 'active' : '')]
                ) ?>
            </li>

            <li class="nav-item">
                <?= $this->Html->link(
                    '<i class="ri-time-line me-1"></i>Oczekujące'
                    . ' <span class="badge bg-warning text-dark ms-1">' . $counts['waiting'] . '</span>',
                    ['action' => 'index', '?' => ['tab' => 'waiting', 'search' => $search ?: null]],
                    ['escape' => false, 'class' => 'nav-link ' . ($tab === 'waiting' ? 'active' : '')]
                ) ?>
            </li>

            <li class="nav-item">
                <?= $this->Html->link(
                    '<i class="ri-question-line me-1"></i>Brak opinii'
                    . ' <span class="badge bg-secondary ms-1">' . $counts['no-advice'] . '</span>',
                    ['action' => 'index', '?' => ['tab' => 'no-advice', 'search' => $search ?: null]],
                    ['escape' => false, 'class' => 'nav-link ' . ($tab === 'no-advice' ? 'active' : '')]
                ) ?>
            </li>

            <li class="nav-item">
                <?= $this->Html->link(
                    '<i class="ri-error-warning-line me-1"></i>Błędy'
                    . ' <span class="badge bg-danger ms-1">' . $counts['error'] . '</span>',
                    ['action' => 'index', '?' => ['tab' => 'error', 'search' => $search ?: null]],
                    ['escape' => false, 'class' => 'nav-link ' . ($tab === 'error' ? 'active' : '')]
                ) ?>
            </li>

        </ul>
    </div>

    <div class="card-body">

        <!-- Wyszukiwarka -->
        <form method="get" action="<?= $this->Url->build(['action' => 'index']) ?>" class="mb-3">
            <input type="hidden" name="tab" value="<?= h($tab) ?>">
            <div class="input-group" style="max-width: 380px">
                <input type="text" name="search" value="<?= h($search) ?>"
                       class="form-control form-control-sm" placeholder="Szukaj po NIP…">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="ri-search-line"></i>
                </button>
                <?php if ($search): ?>
                    <a href="<?= $this->Url->build(['action' => 'index', '?' => ['tab' => $tab]]) ?>"
                       class="btn btn-sm btn-outline-danger">
                        <i class="ri-close-line"></i>
                    </a>
                <?php endif ?>
            </div>
        </form>

        <!-- Tabela -->
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>NIP</th>
                        <th>Kontrahent</th>
                        <?php if ($tab === 'done'): ?>
                            <th>Opinia</th>
                            <th>Najnowsza</th>
                        <?php elseif ($tab === 'error'): ?>
                            <th>Błąd</th>
                        <?php elseif ($tab === 'no-advice'): ?>
                            <th>Status</th>
                        <?php endif ?>
                        <th>Złożono</th>
                        <th>Przez</th>
                        <th class="text-end">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($records->isEmpty()): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            <i class="ri-inbox-line fs-3 d-block mb-1"></i>
                            Brak rekordów<?= $search ? ' dla zapytania <strong>' . h($search) . '</strong>' : '' ?>.
                            <br>
                            <button class="btn btn-sm btn-primary mt-2" id="btn-sync-tab"
                                    data-list="<?= h($tab) ?>">
                                <i class="ri-refresh-line me-1"></i>Synchronizuj teraz
                            </button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $rec): ?>
                        <tr>
                            <td class="text-muted small"><?= $rec->external_id ?></td>

                            <!-- NIP -->
                            <td>
                                <span class="font-monospace"><?= h($rec->identifier ?? '—') ?></span>
                                <?php if ($rec->country): ?>
                                    <small class="text-muted">(<?= h($rec->country) ?>)</small>
                                <?php endif ?>
                            </td>

                            <!-- Kontrahent -->
                            <td>
                                <?php if (!empty($rec->contractor_id) && !empty($rec->contractor)): ?>
                                    <?= $this->Html->link(
                                        h($rec->contractor->name ?? $rec->contractor_id),
                                        ['controller' => 'Contractors', 'action' => 'view', $rec->contractor_id],
                                        ['class' => 'text-decoration-none']
                                    ) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif ?>
                            </td>

                            <?php if ($tab === 'done'): ?>
                                <!-- Opinia -->
                                <td>
                                    <?php if ($rec->advice_type_code): ?>
                                        <?php $at = $adviceTypes[$rec->advice_type_code] ?? ['label' => $rec->advice_type_code, 'badge' => 'secondary'] ?>
                                        <span class="badge bg-<?= $at['badge'] ?>"><?= $at['label'] ?></span>
                                        <?php if ($rec->advice_reason_code): ?>
                                            <br><small class="text-muted fs-10"><?= h($rec->advice_reason_code) ?></small>
                                        <?php endif ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif ?>
                                </td>
                                <!-- Najnowsza -->
                                <td class="text-center">
                                    <?php if ($rec->latest_advice_with_opinion): ?>
                                        <i class="ri-check-line text-success" title="Najnowsza opinia z limitem"></i>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif ?>
                                </td>

                            <?php elseif ($tab === 'error'): ?>
                                <!-- Błąd -->
                                <td>
                                    <?php if ($rec->error_type_code): ?>
                                        <code class="text-danger small"><?= h($rec->error_type_code) ?></code>
                                        <br>
                                        <small class="text-muted">
                                            <?= h($errorTypes[$rec->error_type_code] ?? $rec->error_type_code) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif ?>
                                </td>

                            <?php elseif ($tab === 'no-advice'): ?>
                                <!-- Status -->
                                <td>
                                    <?php if ($rec->status_code): ?>
                                        <code class="small"><?= h($rec->status_code) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif ?>
                                </td>
                            <?php endif ?>

                            <!-- Data -->
                            <td class="text-nowrap">
                                <?php if ($rec->advice_created_at): ?>
                                    <?= $rec->advice_created_at->format('d.m.Y') ?>
                                    <br><small class="text-muted"><?= $rec->advice_created_at->format('H:i') ?></small>
                                <?php else: ?>
                                    —
                                <?php endif ?>
                            </td>

                            <!-- Przez -->
                            <td>
                                <small class="text-muted"><?= h($rec->created_by ?? '—') ?></small>
                            </td>

                            <!-- Akcje -->
                            <td class="text-end text-nowrap">
                                <?php if ($tab === 'done' && $rec->advice_json): ?>
                                    <button class="btn btn-xs btn-outline-secondary btn-advice-details"
                                            data-json="<?= h($rec->advice_json) ?>"
                                            title="Szczegóły opinii">
                                        <i class="ri-eye-line"></i>
                                    </button>
                                <?php endif ?>
                                <?= $this->Form->postLink(
                                    '<i class="ri-delete-bin-line"></i>',
                                    ['action' => 'delete', $rec->id],
                                    [
                                        'escape'  => false,
                                        'class'   => 'btn btn-xs btn-outline-danger',
                                        'confirm' => 'Usunąć ten rekord?',
                                        'title'   => 'Usuń',
                                    ]
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                <?php endif ?>
                </tbody>
            </table>
        </div>

        <!-- Paginacja -->
        <?= $this->element('common/pagination') ?>

    </div>
</div>

<!-- Modal: szczegóły opinii -->
<div class="modal fade" id="adviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Szczegóły opinii</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="advice-json-pre" class="bg-light rounded p-3 small" style="white-space:pre-wrap;word-break:break-all;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const syncUrl  = <?= json_encode($this->Url->build(['action' => 'sync'])) ?>;
    const csrfToken = <?= json_encode($csrf) ?>;

    function showAlert(type, html) {
        const el = document.getElementById('sync-alert');
        el.className = 'alert alert-' + type + ' alert-dismissible fade show';
        el.innerHTML = html + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        el.classList.remove('d-none');
    }

    function runSync(list, btn) {
        const origHtml = btn.innerHTML;
        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Synchronizuję…';

        showAlert('info', '<i class="ri-refresh-line me-1"></i>Trwa synchronizacja z Syntesys — może potrwać do 2 minut…');

        fetch(syncUrl, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken,
            },
            body: 'list=' + encodeURIComponent(list),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAlert('success',
                    '<i class="ri-check-line me-1"></i>' +
                    (data.message || 'Synchronizacja zakończona') +
                    (data.inserted > 0 || data.updated > 0
                        ? ' (' + data.inserted + ' nowych, ' + data.updated + ' zaktualizowanych)'
                        : '') +
                    '<br><small class="text-muted">Odświeżam stronę…</small>'
                );
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('danger', '<i class="ri-error-warning-line me-1"></i>' + (data.message || 'Błąd synchronizacji'));
                btn.disabled  = false;
                btn.innerHTML = origHtml;
            }
        })
        .catch(err => {
            showAlert('danger', '<i class="ri-error-warning-line me-1"></i>Błąd połączenia: ' + err.message);
            btn.disabled  = false;
            btn.innerHTML = origHtml;
        });
    }

    // Główny przycisk sync (wszystko)
    const btnAll = document.getElementById('btn-sync');
    if (btnAll) {
        btnAll.addEventListener('click', () => runSync(btnAll.dataset.list || 'all', btnAll));
    }

    // Przycisk sync w pustej tabeli (tylko aktywny tab)
    const btnTab = document.getElementById('btn-sync-tab');
    if (btnTab) {
        btnTab.addEventListener('click', () => runSync(btnTab.dataset.list || 'all', btnTab));
    }

    // Modal szczegółów opinii
    document.querySelectorAll('.btn-advice-details').forEach(btn => {
        btn.addEventListener('click', () => {
            try {
                const json = JSON.parse(btn.dataset.json);
                document.getElementById('advice-json-pre').textContent = JSON.stringify(json, null, 2);
            } catch (_) {
                document.getElementById('advice-json-pre').textContent = btn.dataset.json;
            }
            new bootstrap.Modal(document.getElementById('adviceModal')).show();
        });
    });
})();
</script>

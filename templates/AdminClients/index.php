<?php
/**
 * @var \App\View\AppView                                  $this
 * @var \Cake\ORM\ResultSet                                $users
 * @var array<string, \App\Model\Entity\ClientProfile>     $profileMap
 * @var array<string, int>                                 $orderCountMap
 * @var int                                                $total
 * @var int                                                $page
 * @var int                                                $pages
 * @var int                                                $limit
 * @var string                                             $q
 */
$this->assign('title', 'Klienci portalu');

$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="ri-user-2-line me-1"></i>Klienci portalu</h4>
        <div class="text-muted small mt-1">Konta z rolą <code>client</code> — dostęp do <code>/portal</code></div>
    </div>
    <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-primary btn-sm">
        <i class="ri-add-line me-1"></i>Dodaj klienta
    </a>
</div>

<?= $this->Flash->render() ?>

<form method="get" action="<?= $this->Url->build(['action' => 'index']) ?>"
      class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <input type="text" name="q" value="<?= h($q) ?>"
           class="form-control form-control-sm" style="max-width:300px"
           placeholder="Szukaj: e-mail, login, imię, nazwisko…">
    <button class="btn btn-sm btn-primary"><i class="ri-search-line"></i></button>
    <?php if ($q !== ''): ?>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>"
           class="btn btn-sm btn-outline-secondary"><i class="ri-close-line"></i></a>
    <?php endif; ?>
    <span class="text-muted small ms-auto">
        <?php if ($total > 0): ?><?= number_format($total, 0, ',', ' ') ?> klientów<?php endif; ?>
    </span>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" style="font-size:.875rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:230px">Klient</th>
                    <th>Firma / NIP</th>
                    <th style="width:120px" class="text-center">Zleceń</th>
                    <th style="width:80px" class="text-center">Aktywny</th>
                    <th style="width:120px">Utworzono</th>
                    <th class="pe-3 text-end" style="width:140px">Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users) || count($users->toArray()) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="ri-user-search-line" style="font-size:2em"></i><br>
                            Brak klientów<?= $q !== '' ? ' dla podanych kryteriów' : '' ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <?php
                        $profile = $profileMap[(string)$u->id] ?? null;
                        $cnt     = $profile ? ($orderCountMap[(string)$profile->nip] ?? 0) : 0;
                        $name    = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                    ?>
                    <tr>
                        <td class="ps-3 align-top pt-3">
                            <div class="fw-semibold"><?= h($u->email) ?></div>
                            <?php if ($name !== ''): ?>
                                <div class="text-muted small"><?= h($name) ?></div>
                            <?php endif; ?>
                            <?php if ($u->username && $u->username !== $u->email): ?>
                                <div class="text-muted small mt-1">
                                    <span class="badge bg-light text-secondary border" style="font-size:.65em">
                                        login: <?= h($u->username) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="align-top pt-3">
                            <?php if ($profile): ?>
                                <?php if ($profile->company_name): ?>
                                    <div class="fw-semibold"><?= h($profile->company_name) ?></div>
                                <?php endif; ?>
                                <div class="text-muted small">
                                    <span class="badge bg-light text-secondary border">NIP <?= h($profile->nip) ?></span>
                                </div>
                            <?php else: ?>
                                <span class="text-danger small fst-italic">
                                    <i class="ri-error-warning-line me-1"></i>Brak profilu
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center align-top pt-3">
                            <?php if ($cnt > 0): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                    <i class="ri-truck-line me-1"></i><?= $cnt ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center align-top pt-3">
                            <?php if ($u->active): ?>
                                <i class="ri-checkbox-circle-fill text-success" title="Aktywny" style="font-size:1.2em"></i>
                            <?php else: ?>
                                <i class="ri-close-circle-fill text-danger" title="Nieaktywny" style="font-size:1.2em"></i>
                            <?php endif; ?>
                        </td>
                        <td class="align-top pt-3 text-nowrap">
                            <span class="text-muted small"><?= $fdate($u->created) ?></span>
                        </td>
                        <td class="pe-3 text-end align-top pt-2">
                            <div class="d-inline-flex gap-1">
                                <a href="<?= $this->Url->build(['action' => 'edit', $u->id]) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Edytuj">
                                    <i class="ri-edit-line"></i>
                                </a>
                                <?= $this->Form->postLink(
                                    '<i class="ri-delete-bin-line"></i>',
                                    ['action' => 'delete', $u->id],
                                    [
                                        'class'   => 'btn btn-sm btn-outline-danger',
                                        'escape'  => false,
                                        'title'   => 'Usuń klienta',
                                        'confirm' => 'Czy na pewno usunąć klienta ' . h($u->email) . '? Tej operacji nie można cofnąć.',
                                    ]
                                ) ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center py-2">
        <small class="text-muted">
            <?= ($page - 1) * $limit + 1 ?>–<?= min($page * $limit, $total) ?> z <?= number_format($total, 0, ',', ' ') ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <?= $this->Html->link('‹',
                            ['action' => 'index', '?' => array_filter(['q' => $q, 'page' => $page - 1])],
                            ['class' => 'page-link']) ?>
                    </li>
                <?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <?= $this->Html->link((string)$p,
                            ['action' => 'index', '?' => array_filter(['q' => $q, 'page' => $p])],
                            ['class' => 'page-link']) ?>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <?= $this->Html->link('›',
                            ['action' => 'index', '?' => array_filter(['q' => $q, 'page' => $page + 1])],
                            ['class' => 'page-link']) ?>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

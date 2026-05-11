<?php
/**
 * @var \App\View\AppView                              $this
 * @var \Cake\ORM\ResultSet                            $users
 * @var array<string, \App\Model\Entity\ClientProfile> $profileMap
 * @var \Cake\ORM\ResultSet                            $rolesList
 * @var array<string, string>                          $roleNameByCode
 * @var string                                         $roleFilter
 * @var string                                         $q
 * @var ?string                                        $active
 * @var int                                            $total
 * @var int                                            $page
 * @var int                                            $pages
 * @var int                                            $limit
 */
$this->assign('title', __('Użytkownicy'));

$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y') : substr((string)$v, 0, 10)) : '—';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="ri-team-line me-1"></i><?= __('Użytkownicy') ?></h4>
        <div class="text-muted small mt-1"><?= __('Wszyscy użytkownicy systemu — pracownicy i klienci portalu.') ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $this->Url->build(['controller' => 'Roles', 'action' => 'index']) ?>" class="btn btn-outline-primary btn-sm">
            <i class="ri-shield-user-line me-1"></i><?= __('Role i uprawnienia') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-primary btn-sm">
            <i class="ri-user-add-line me-1"></i><?= __('Dodaj użytkownika') ?>
        </a>
    </div>
</div>

<?= $this->Flash->render() ?>

<form method="get" action="<?= $this->Url->build(['action' => 'index']) ?>"
      class="d-flex flex-wrap gap-2 mb-3 align-items-center">

    <select name="role" class="form-select form-select-sm" style="max-width:220px">
        <option value=""><?= __('Wszystkie role') ?></option>
        <?php foreach ($rolesList as $r): ?>
            <option value="<?= h($r->code) ?>" <?= $roleFilter === $r->code ? 'selected' : '' ?>>
                <?= h($r->name) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="active" class="form-select form-select-sm" style="max-width:160px">
        <option value=""><?= __('Wszystkie') ?></option>
        <option value="1" <?= $active === '1' ? 'selected' : '' ?>><?= __('Aktywni') ?></option>
        <option value="0" <?= $active === '0' ? 'selected' : '' ?>><?= __('Nieaktywni') ?></option>
    </select>

    <input type="text" name="q" value="<?= h($q) ?>"
           class="form-control form-control-sm" style="max-width:300px"
           placeholder="<?= __('Szukaj: e-mail, login, imię, nazwisko…') ?>">
    <button class="btn btn-sm btn-primary"><i class="ri-search-line"></i></button>
    <?php if ($q !== '' || $roleFilter !== '' || $active === '0' || $active === '1'): ?>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>"
           class="btn btn-sm btn-outline-secondary"><i class="ri-close-line"></i></a>
    <?php endif; ?>
    <span class="text-muted small ms-auto">
        <?php if ($total > 0): ?><?= number_format($total, 0, ',', ' ') ?> <?= __('użytkowników') ?><?php endif; ?>
    </span>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle" style="font-size:.875rem">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:280px"><?= __('Użytkownik') ?></th>
                    <th style="width:180px"><?= __('Rola') ?></th>
                    <th><?= __('Firma / NIP') ?></th>
                    <th style="width:80px"  class="text-center"><?= __('Aktywny') ?></th>
                    <th style="width:120px"><?= __('Utworzono') ?></th>
                    <th class="pe-3 text-end" style="width:140px"><?= __('Akcje') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users) || count($users->toArray()) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="ri-user-search-line" style="font-size:2em"></i><br>
                            <?= __('Brak użytkowników{0}.', ($q !== '' || $roleFilter !== '') ? ' ' . __('dla podanych kryteriów') : '') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u):
                        $profile  = $profileMap[(string)$u->id] ?? null;
                        $name     = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? ''));
                        $roleName = $roleNameByCode[(string)$u->role] ?? $u->role;
                    ?>
                    <tr>
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($u->avatar)): ?>
                                    <img src="<?= h($u->avatar) ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover">
                                <?php else: ?>
                                    <div class="bg-primary-transparent text-primary fw-semibold d-inline-flex align-items-center justify-content-center"
                                         style="width:32px;height:32px;border-radius:50%;font-size:.8rem">
                                        <?= h(mb_strtoupper(mb_substr($u->email ?: '?', 0, 1))) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-semibold"><?= h($u->email) ?></div>
                                    <?php if ($name !== ''): ?>
                                        <div class="text-muted small"><?= h($name) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($u->role === 'admin'): ?>
                                <span class="badge bg-danger-transparent"><i class="ri-shield-star-line"></i> <?= h($roleName) ?></span>
                            <?php elseif ($u->role === 'client'): ?>
                                <span class="badge bg-info-transparent"><i class="ri-global-line"></i> <?= h($roleName) ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary-transparent"><i class="ri-user-line"></i> <?= h($roleName) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($profile): ?>
                                <?php if ($profile->company_name): ?>
                                    <div class="fw-semibold"><?= h($profile->company_name) ?></div>
                                <?php endif; ?>
                                <div class="text-muted small"><i class="ri-hashtag"></i> <?= h($profile->nip) ?></div>
                            <?php elseif ($u->company_id): ?>
                                <span class="text-muted small"><?= __('powiązany z firmą') ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($u->active): ?>
                                <i class="ri-checkbox-circle-fill text-success"></i>
                            <?php else: ?>
                                <i class="ri-close-circle-fill text-muted"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?= $fdate($u->created) ?>
                        </td>
                        <td class="pe-3 text-end">
                            <a href="<?= $this->Url->build(['action' => 'edit', $u->id]) ?>"
                               class="btn btn-sm btn-outline-primary me-1" title="<?= __('Edytuj') ?>">
                                <i class="ri-edit-line"></i>
                            </a>
                            <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>',
                                ['action' => 'delete', $u->id],
                                [
                                    'class'   => 'btn btn-sm btn-outline-danger',
                                    'escape'  => false,
                                    'confirm' => __('Usunąć użytkownika {0}? Tej operacji nie można cofnąć.', $u->email),
                                ]) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="card-footer d-flex justify-content-center">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($p = 1; $p <= $pages; $p++):
                        $url = $this->Url->build(['action' => 'index', '?' => [
                            'q' => $q, 'role' => $roleFilter, 'active' => $active, 'page' => $p,
                        ]]);
                    ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a href="<?= h($url) ?>" class="page-link"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

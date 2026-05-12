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
                    <th class="pe-3 text-end text-nowrap" style="width:160px"><?= __('Akcje') ?></th>
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
                                    <a href="<?= h($u->avatar) ?>"
                                       class="user-avatar-lightbox"
                                       data-glightbox="title: <?= h(trim($name ?: $u->email)) ?>"
                                       title="<?= __('Powiększ zdjęcie') ?>">
                                        <img src="<?= h($u->avatar) ?>" alt=""
                                             style="width:32px;height:32px;border-radius:50%;object-fit:cover;cursor:zoom-in;transition:transform .15s ease,box-shadow .15s ease"
                                             onmouseover="this.style.transform='scale(1.06)';this.style.boxShadow='0 4px 12px rgba(15,23,42,.18)'"
                                             onmouseout="this.style.transform='';this.style.boxShadow=''">
                                    </a>
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
                        <td class="pe-3 text-end text-nowrap">
                            <div class="btn-group btn-group-sm" role="group" aria-label="<?= __('Akcje') ?>">
                                <button type="button" class="btn btn-outline-success"
                                        data-bs-toggle="modal" data-bs-target="#welcomeEmailModal"
                                        data-user-id="<?= h($u->id) ?>"
                                        data-user-email="<?= h($u->email) ?>"
                                        data-user-role="<?= h($u->role) ?>"
                                        title="<?= __('Wyślij e-mail powitalny') ?>">
                                    <i class="ri-mail-send-line"></i>
                                </button>
                                <a href="<?= $this->Url->build(['action' => 'edit', $u->id]) ?>"
                                   class="btn btn-outline-primary" title="<?= __('Edytuj') ?>">
                                    <i class="ri-edit-line"></i>
                                </a>
                                <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>',
                                    ['action' => 'delete', $u->id],
                                    [
                                        'class'   => 'btn btn-outline-danger',
                                        'escape'  => false,
                                        'confirm' => __('Usunąć użytkownika {0}? Tej operacji nie można cofnąć.', $u->email),
                                        'title'   => __('Usuń'),
                                    ]) ?>
                            </div>
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

<!-- Modal: Wyślij e-mail powitalny (PL/EN) -->
<div class="modal fade" id="welcomeEmailModal" tabindex="-1" aria-labelledby="welcomeEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="welcomeEmailForm" method="post" action="">
                <input type="hidden" name="_csrfToken" value="<?= h($this->request->getAttribute('csrfToken')) ?>">

                <div class="modal-header">
                    <h5 class="modal-title" id="welcomeEmailModalLabel">
                        <i class="ri-mail-send-line me-1"></i><?= __('Wyślij e-mail powitalny') ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Zamknij') ?>"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3"><?= __('Treść e-maila dopasuje się do roli użytkownika. Mail zawiera link do ustawienia nowego hasła (polityka bezpieczeństwa) ważny 7 dni.') ?></p>

                    <dl class="row mb-3 small">
                        <dt class="col-sm-3"><?= __('Odbiorca') ?></dt>
                        <dd class="col-sm-9"><strong id="welcomeUserEmail">—</strong></dd>
                        <dt class="col-sm-3"><?= __('Rola') ?></dt>
                        <dd class="col-sm-9"><code id="welcomeUserRole">—</code></dd>
                    </dl>

                    <label class="form-label fw-semibold"><?= __('Język wiadomości') ?></label>
                    <div class="d-flex gap-2">
                        <label class="form-check form-check-inline border rounded p-2 px-3 flex-grow-1" style="cursor:pointer">
                            <input type="radio" class="form-check-input me-2" name="lang" value="pl" checked>
                            <img src="/assets/images/flags/poland_flag.jpg" alt="PL" style="width:20px;height:20px;border-radius:50%;object-fit:cover;margin-right:6px;vertical-align:middle">
                            <strong>Polski</strong>
                        </label>
                        <label class="form-check form-check-inline border rounded p-2 px-3 flex-grow-1" style="cursor:pointer">
                            <input type="radio" class="form-check-input me-2" name="lang" value="en">
                            <img src="/assets/images/flags/uk_flag.jpg" alt="EN" style="width:20px;height:20px;border-radius:50%;object-fit:cover;margin-right:6px;vertical-align:middle">
                            <strong>English</strong>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __('Anuluj') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-send-plane-line me-1"></i><?= __('Wyślij e-mail') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('welcomeEmailModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        if (!btn) return;
        var userId = btn.getAttribute('data-user-id');
        var email  = btn.getAttribute('data-user-email');
        var role   = btn.getAttribute('data-user-role');
        modal.querySelector('#welcomeEmailForm').action = '/admin/uzytkownicy/powitanie/' + userId;
        modal.querySelector('#welcomeUserEmail').textContent = email || '—';
        modal.querySelector('#welcomeUserRole').textContent  = role  || '—';
    });
})();
</script>

<!-- GLightbox: powiększenie zdjęć użytkowników po kliku -->
<link rel="stylesheet" href="/assets/libs/glightbox/css/glightbox.min.css">
<script src="/assets/libs/glightbox/js/glightbox.min.js"></script>
<script>
(function () {
    if (typeof GLightbox === 'undefined') return;
    GLightbox({
        selector: '.user-avatar-lightbox',
        touchNavigation: true,
        loop: false,
        zoomable: true,
        openEffect: 'zoom',
        closeEffect: 'zoom'
    });
})();
</script>

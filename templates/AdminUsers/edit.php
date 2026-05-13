<?php
/**
 * @var \App\View\AppView                $this
 * @var \CakeDC\Users\Model\Entity\User  $user
 * @var \App\Model\Entity\ClientProfile  $profile
 * @var \Cake\ORM\ResultSet              $rolesList
 * @var int                              $orderCount
 */
$this->assign('title', __('Edycja: {0}', $user->email));

$fdate = fn($v) => $v ? ($v instanceof \DateTimeInterface ? $v->format('d.m.Y H:i') : (string)$v) : '—';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="ri-user-settings-line me-1"></i><?= __('Edycja użytkownika') ?></h4>
        <div class="text-muted small mt-1"><?= h($user->email) ?></div>
    </div>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line me-1"></i><?= __('Wróć') ?>
    </a>
</div>

<?= $this->Flash->render() ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent"><strong><?= __('Dane konta') ?></strong></div>
            <div class="card-body">
                <?= $this->Form->create(null, ['type' => 'post']) ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><?= __('E-mail') ?></label>
                        <input type="email" class="form-control" value="<?= h($user->email) ?>" disabled>
                        <div class="form-text"><?= __('E-mail jest loginem — zmiana nieobsługiwana z poziomu admina.') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= __('Rola') ?></label>
                        <select name="role" id="role-select" class="form-select" required>
                            <?php foreach ($rolesList as $r): ?>
                                <option value="<?= h($r->code) ?>" <?= $user->role === $r->code ? 'selected' : '' ?>>
                                    <?= h($r->name) ?> <small class="text-muted">(<?= h($r->code) ?>)</small>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><?= __('Imię') ?></label>
                        <input type="text" name="first_name" class="form-control" value="<?= h($user->first_name) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= __('Nazwisko') ?></label>
                        <input type="text" name="last_name" class="form-control" value="<?= h($user->last_name) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><?= __('Telefon') ?></label>
                        <input type="tel" name="phone" class="form-control" maxlength="32"
                               value="<?= h($user->phone) ?>"
                               placeholder="<?= __('np. +48 600 100 200') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label"><?= __('Nowe hasło') ?> <small class="text-muted">(<?= __('zostaw puste żeby nie zmieniać') ?>)</small></label>
                        <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="active" value="1"
                                   id="active" <?= $user->active ? 'checked' : '' ?>>
                            <label class="form-check-label" for="active"><?= __('Konto aktywne') ?></label>
                        </div>
                    </div>

                    <!-- Firma — dla pracowników (admin/user/asystent/...) — ignorowane dla klienta -->
                    <div class="col-md-12 employee-fields" style="display:<?= $user->role === 'client' ? 'none' : '' ?>">
                        <label class="form-label"><?= __('Firma') ?></label>
                        <select name="company_id" id="company-select" class="form-select" data-placeholder="<?= __('— bez firmy (user przejdzie onboarding) —') ?>">
                            <option value=""></option>
                            <?php foreach ($companiesList as $c): ?>
                                <option value="<?= h($c->id) ?>" <?= $user->company_id === $c->id ? 'selected' : '' ?>>
                                    <?= h($c->name) ?><?= $c->nip ? ' — NIP ' . h($c->nip) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?= __('Pracownicy są przypisywani do firmy z której będą wystawiać faktury.') ?></div>
                    </div>

                    <!-- Pola klienta -->
                    <div class="col-12 client-fields" style="display:<?= $user->role === 'client' ? '' : 'none' ?>">
                        <hr>
                        <div class="text-muted small mb-2">
                            <i class="ri-information-line me-1"></i><?= __('Dane profilu klienta portalu') ?>
                        </div>
                    </div>
                    <div class="col-md-6 client-fields" style="display:<?= $user->role === 'client' ? '' : 'none' ?>">
                        <label class="form-label"><?= __('NIP') ?></label>
                        <input type="text" name="nip" class="form-control" maxlength="30" value="<?= h($profile->nip ?? '') ?>">
                    </div>
                    <div class="col-md-6 client-fields" style="display:<?= $user->role === 'client' ? '' : 'none' ?>">
                        <label class="form-label"><?= __('Nazwa firmy') ?></label>
                        <input type="text" name="company_name" class="form-control" value="<?= h($profile->company_name ?? '') ?>">
                    </div>
                    <div class="col-md-3 client-fields" style="display:<?= $user->role === 'client' ? '' : 'none' ?>">
                        <label class="form-label"><?= __('Język') ?></label>
                        <select name="locale" class="form-select">
                            <option value="pl" <?= ($profile->locale ?? 'pl') === 'pl' ? 'selected' : '' ?>>PL</option>
                            <option value="en" <?= ($profile->locale ?? 'pl') === 'en' ? 'selected' : '' ?>>EN</option>
                        </select>
                    </div>

                    <!-- Opiekun klienta + zastępstwo -->
                    <?php
                        $employeesList = $employeesList ?? [];
                        $employeeOption = function (array $e) {
                            $name = trim(((string)$e['first_name']) . ' ' . ((string)$e['last_name']));
                            return ($name !== '' ? $name : $e['email']) . ' — ' . $e['email'];
                        };
                        $caretakerId  = $profile->caretaker_user_id  ?? '';
                        $substituteId = $profile->substitute_user_id ?? '';
                        $fmtDate = function ($d) {
                            if (!$d) return '';
                            if ($d instanceof \DateTimeInterface) return $d->format('Y-m-d');
                            return substr((string)$d, 0, 10);
                        };
                    ?>
                    <div class="col-12 client-fields" style="display:<?= $user->role === 'client' ? '' : 'none' ?>">
                        <hr>
                        <div class="text-muted small mb-2">
                            <i class="ri-user-heart-line me-1"></i><?= __('Opiekun klienta i zastępstwo') ?>
                        </div>
                    </div>
                    <div class="col-md-6 client-fields" style="display:<?= $user->role === 'client' ? '' : 'none' ?>">
                        <label class="form-label"><?= __('Opiekun główny') ?></label>
                        <select name="caretaker_user_id" id="caretaker-select" class="form-select" data-placeholder="<?= __('— brak opiekuna —') ?>">
                            <option value=""></option>
                            <?php foreach ($employeesList as $e): ?>
                                <option value="<?= h($e['id']) ?>" <?= $caretakerId === $e['id'] ? 'selected' : '' ?>>
                                    <?= h($employeeOption($e)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 client-fields" style="display:<?= $user->role === 'client' ? '' : 'none' ?>">
                        <label class="form-label"><?= __('Zastępca (na czas urlopu)') ?></label>
                        <select name="substitute_user_id" id="substitute-select" class="form-select" data-placeholder="<?= __('— bez zastępcy —') ?>">
                            <option value=""></option>
                            <?php foreach ($employeesList as $e): ?>
                                <option value="<?= h($e['id']) ?>" <?= $substituteId === $e['id'] ? 'selected' : '' ?>>
                                    <?= h($employeeOption($e)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 client-fields" style="display:<?= $user->role === 'client' ? '' : 'none' ?>">
                        <label class="form-label"><?= __('Zastępstwo od') ?></label>
                        <input type="date" name="substitute_from" class="form-control" value="<?= h($fmtDate($profile->substitute_from ?? null)) ?>">
                    </div>
                    <div class="col-md-3 client-fields" style="display:<?= $user->role === 'client' ? '' : 'none' ?>">
                        <label class="form-label"><?= __('Zastępstwo do') ?></label>
                        <input type="date" name="substitute_to" class="form-control" value="<?= h($fmtDate($profile->substitute_to ?? null)) ?>">
                    </div>
                    <?php if (($profile->is_substitute_active ?? false) && $user->role === 'client'): ?>
                        <div class="col-12 client-fields">
                            <div class="alert alert-info py-2 mb-0 small">
                                <i class="ri-information-line me-1"></i>
                                <?= __('Zastępstwo jest aktualnie aktywne — wiadomości i powiadomienia dot. tego klienta trafiają do zastępcy.') ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary"><i class="ri-save-line me-1"></i><?= __('Zapisz zmiany') ?></button>
                    <?= $this->Form->postLink('<i class="ri-delete-bin-line me-1"></i>' . __('Usuń konto'),
                        ['action' => 'delete', $user->id],
                        [
                            'class'   => 'btn btn-outline-danger ms-auto',
                            'escape'  => false,
                            'confirm' => __('Usunąć użytkownika {0}? Tej operacji nie można cofnąć.', $user->email),
                        ]) ?>
                </div>

                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Avatar użytkownika -->
        <?php
            $avatarUrl = !empty($user->avatar) ? (string)$user->avatar : '';
            $avatarOk  = $avatarUrl !== '';
            if ($avatarOk && str_starts_with($avatarUrl, '/files/avatars/')) {
                $diskPath = WWW_ROOT . ltrim($avatarUrl, '/');
                if (!is_file($diskPath)) {
                    $avatarOk = false;
                } else {
                    $avatarUrl .= '?v=' . filemtime($diskPath);
                }
            }
            $initial = mb_strtoupper(mb_substr(($user->first_name ?: $user->email), 0, 1));
            $csrf = $this->request->getAttribute('csrfToken');
        ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-transparent"><strong><?= __('Zdjęcie profilowe') ?></strong></div>
            <div class="card-body text-center">
                <div id="avatar-preview-wrap" class="d-flex justify-content-center mb-3">
                    <?php if ($avatarOk): ?>
                        <img id="avatar-preview" src="<?= h($avatarUrl) ?>" alt=""
                             style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #e5e7eb">
                    <?php else: ?>
                        <div id="avatar-preview-initials"
                             style="width:120px;height:120px;border-radius:50%;background:rgba(var(--primary-rgb),.15);color:rgb(var(--primary-rgb));display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:700;border:3px solid #e5e7eb">
                            <?= h($initial) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 justify-content-center">
                    <label class="btn btn-sm btn-primary mb-0" for="avatar-file-input">
                        <i class="ri-upload-2-line me-1"></i><?= __('Wgraj nowy') ?>
                    </label>
                    <input type="file" id="avatar-file-input" accept="image/jpeg,image/png,image/webp" class="d-none">
                    <button type="button" id="avatar-delete-btn" class="btn btn-sm btn-outline-danger"
                            <?= !$avatarOk ? 'style="display:none"' : '' ?>>
                        <i class="ri-delete-bin-line me-1"></i><?= __('Usuń') ?>
                    </button>
                </div>
                <div class="form-text mt-2 small">
                    <?= __('JPG / PNG / WebP. Obraz zostanie wykadrowany na kwadrat 400×400.') ?>
                </div>
                <div id="avatar-status" class="mt-2 small" style="min-height:1.2em"></div>
            </div>
        </div>

        <script>
        (function () {
            var userId    = <?= json_encode((string)$user->id) ?>;
            var csrfToken = <?= json_encode((string)$csrf) ?>;
            var uploadUrl = '<?= $this->Url->build(['action' => 'uploadAvatar', $user->id]) ?>';
            var deleteUrl = '<?= $this->Url->build(['action' => 'deleteAvatar', $user->id]) ?>';

            var fileInput = document.getElementById('avatar-file-input');
            var delBtn    = document.getElementById('avatar-delete-btn');
            var wrap      = document.getElementById('avatar-preview-wrap');
            var status    = document.getElementById('avatar-status');

            function setStatus(msg, ok) {
                if (!status) return;
                status.textContent = msg || '';
                status.className   = 'mt-2 small ' + (ok ? 'text-success' : (msg ? 'text-danger' : ''));
            }

            function replacePreviewWithImage(url) {
                wrap.innerHTML = '<img id="avatar-preview" src="' + url + '?v=' + Date.now()
                    + '" alt="" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #e5e7eb">';
                if (delBtn) delBtn.style.display = '';
            }
            function replacePreviewWithInitials() {
                wrap.innerHTML = '<div style="width:120px;height:120px;border-radius:50%;background:rgba(var(--primary-rgb),.15);color:rgb(var(--primary-rgb));display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:700;border:3px solid #e5e7eb"><?= h($initial) ?></div>';
                if (delBtn) delBtn.style.display = 'none';
            }

            fileInput?.addEventListener('change', function () {
                var f = fileInput.files && fileInput.files[0];
                if (!f) return;
                setStatus('<?= __('Wysyłam…') ?>', true);
                var fd = new FormData();
                fd.append('avatar', f);
                fetch(uploadUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                  .then(function (res) {
                      if (res.body && res.body.ok) {
                          replacePreviewWithImage(res.body.avatar);
                          setStatus('<?= __('Zapisano.') ?>', true);
                      } else {
                          setStatus((res.body && res.body.error) || '<?= __('Błąd podczas zapisu.') ?>', false);
                      }
                  })
                  .catch(function (e) { setStatus('<?= __('Błąd sieci') ?>: ' + e.message, false); })
                  .finally(function () { fileInput.value = ''; });
            });

            delBtn?.addEventListener('click', function () {
                if (!window.confirm('<?= __('Usunąć avatar tego użytkownika?') ?>')) return;
                setStatus('<?= __('Usuwam…') ?>', true);
                fetch(deleteUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                  .then(function (res) {
                      if (res.body && res.body.ok) {
                          replacePreviewWithInitials();
                          setStatus('<?= __('Usunięto.') ?>', true);
                      } else {
                          setStatus((res.body && res.body.error) || '<?= __('Błąd usuwania.') ?>', false);
                      }
                  })
                  .catch(function (e) { setStatus('<?= __('Błąd sieci') ?>: ' + e.message, false); });
            });
        })();
        </script>

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-transparent"><strong><?= __('Akcje e-mailowe') ?></strong></div>
            <div class="card-body">
                <button type="button" class="btn btn-outline-success w-100"
                        data-bs-toggle="modal" data-bs-target="#welcomeEmailModal"
                        data-user-id="<?= h($user->id) ?>"
                        data-user-email="<?= h($user->email) ?>"
                        data-user-role="<?= h($user->role) ?>">
                    <i class="ri-mail-send-line me-1"></i><?= __('Wyślij e-mail powitalny') ?>
                </button>
                <div class="form-text mt-2 small">
                    <?= __('Mail zawiera link do ustawienia hasła (ważny 7 dni). Treść dopasowuje się do roli.') ?>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-transparent"><strong><?= __('Informacje') ?></strong></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-5"><?= __('ID') ?></dt>
                    <dd class="col-sm-7"><code style="font-size:.75rem"><?= h($user->id) ?></code></dd>

                    <dt class="col-sm-5"><?= __('Login') ?></dt>
                    <dd class="col-sm-7"><?= h($user->username) ?></dd>

                    <dt class="col-sm-5"><?= __('Utworzono') ?></dt>
                    <dd class="col-sm-7"><?= $fdate($user->created) ?></dd>

                    <dt class="col-sm-5"><?= __('Ostatnio modyf.') ?></dt>
                    <dd class="col-sm-7"><?= $fdate($user->modified) ?></dd>

                    <?php if ($user->role === 'client' && $orderCount): ?>
                        <dt class="col-sm-5"><?= __('Zleceń (NIP)') ?></dt>
                        <dd class="col-sm-7"><strong><?= (int)$orderCount ?></strong></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>

<!-- Historia wysyłki e-maili do użytkownika -->
<?php
$logsArr = isset($emailLogs) ? $emailLogs->toArray() : [];
$typeLabels = [
    'welcome'           => __('Powitalny'),
    'reset_password'    => __('Reset hasła'),
    'validation'        => __('Aktywacja'),
    'social_validation' => __('Social — aktywacja'),
    'onetime_token'     => __('Jednorazowe logowanie'),
];
$typeIcons = [
    'welcome'           => 'ri-mail-send-line text-success',
    'reset_password'    => 'ri-lock-password-line text-warning',
    'validation'        => 'ri-mail-check-line text-info',
    'social_validation' => 'ri-account-circle-line text-info',
    'onetime_token'     => 'ri-key-2-line text-primary',
];
?>
<div class="row mt-3">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <strong><i class="ri-history-line me-1"></i><?= __('Historia wysyłki e-maili') ?></strong>
                <?php if (count($logsArr)): ?>
                    <span class="badge bg-secondary-transparent"><?= count($logsArr) ?> <?= __('ostatnich') ?></span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.85rem">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:180px"><?= __('Data') ?></th>
                            <th style="width:180px"><?= __('Typ') ?></th>
                            <th style="width:60px"  class="text-center"><?= __('Język') ?></th>
                            <th><?= __('Temat') ?></th>
                            <th style="width:90px"  class="text-center"><?= __('Status') ?></th>
                            <th class="pe-3" style="width:180px"><?= __('Wysłał') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logsArr)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="ri-mail-line" style="font-size:1.4em"></i><br>
                                    <?= __('Brak wysyłek do tego użytkownika.') ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logsArr as $log):
                                $typeLabel = $typeLabels[$log->email_type] ?? $log->email_type;
                                $typeIcon  = $typeIcons[$log->email_type]  ?? 'ri-mail-line text-muted';
                            ?>
                            <tr>
                                <td class="ps-3 text-muted small">
                                    <?= $log->created ? $log->created->i18nFormat('yyyy-MM-dd HH:mm') : '—' ?>
                                </td>
                                <td>
                                    <i class="<?= h($typeIcon) ?> me-1"></i><?= h($typeLabel) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary-transparent"><?= h(strtoupper($log->lang)) ?></span>
                                </td>
                                <td class="small"><?= h($log->subject ?? '—') ?></td>
                                <td class="text-center">
                                    <?php if ($log->status === 'sent'): ?>
                                        <span class="badge bg-success-transparent"><i class="ri-check-line"></i> <?= __('Wysłano') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-transparent" title="<?= h($log->error_message ?? '') ?>">
                                            <i class="ri-close-line"></i> <?= __('Błąd') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 small text-muted">
                                    <?= h($log->sender_email ?: '—') ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var sel = document.getElementById('role-select');
    var clientFields   = document.querySelectorAll('.client-fields');
    var employeeFields = document.querySelectorAll('.employee-fields');
    function toggle() {
        var isClient = sel.value === 'client';
        clientFields.forEach(function (el)   { el.style.display = isClient ? '' : 'none'; });
        employeeFields.forEach(function (el) { el.style.display = isClient ? 'none' : ''; });
    }
    sel.addEventListener('change', toggle);

    // Select2 dla pól: firma, opiekun, zastępca
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        var $j = window.jQuery;
        var s2lang = {
            searching:   function () { return <?= json_encode(__('Szukam…')) ?>; },
            noResults:   function () { return <?= json_encode(__('Brak wyników')) ?>; },
            inputTooShort: function () { return ''; }
        };
        ['#company-select', '#caretaker-select', '#substitute-select'].forEach(function (sel) {
            var $el = $j(sel);
            if ($el.length) {
                $el.select2({
                    width: '100%',
                    allowClear: true,
                    placeholder: $el.data('placeholder') || '',
                    language: s2lang
                });
            }
        });
    }
})();
</script>

<!-- Modal: Wyślij e-mail powitalny (PL/EN) -->
<div class="modal fade" id="welcomeEmailModal" tabindex="-1" aria-labelledby="welcomeEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="welcomeEmailForm" method="post" action="">
                <input type="hidden" name="_csrfToken" value="<?= h($this->request->getAttribute('csrfToken')) ?>">

                <div class="modal-header">
                    <h5 class="modal-title">
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

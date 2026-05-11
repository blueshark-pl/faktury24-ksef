    <?php
    // Zmienne z CakeDC Users: $user (Entity), $isCurrentUser (bool), $avatarPlaceholder (opcjonalnie)
    $avatarUrl = !empty($user->avatar) ? $user->avatar : ($avatarPlaceholder ?? 'https://ssl.gstatic.com/accounts/ui/avatar_2x.png');
    ?>
    <div class="row">
        <div class="col-xl-3">
            <div class="card custom-card">
                <div class="card-body">
                    <?= $this->element('Users/settings_nav') ?>
                </div>
            </div>
        </div>

        <div class="col-xl-9">
            <div class="card custom-card">
                <div class="p-3 border-bottom border-top border-block-end-dashed tab-content">
                    <div class="tab-pane show active overflow-hidden p-0 border-0" id="account-pane" role="tabpanel" aria-labelledby="account" tabindex="0">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-1">
                            <div class="fw-semibold d-block fs-15">Ustawienia konta</div>
                            <?php if (!empty($isCurrentUser)) : ?>
                                <?= $this->Html->link('Edytuj profil', ['controller' => 'Users', 'action' => 'profile'], ['class' => 'btn btn-outline-primary btn-sm']); ?>
                            <?php endif; ?>
                        </div>

                        <div class="row gy-4">
                            <div class="col-xl-12">
                                <div class="d-flex align-items-start flex-wrap gap-3">
                                    <div>
                                        <span class="avatar avatar-xxl rounded-circle overflow-hidden border">
                                            <?= $this->Html->image($avatarUrl, ['alt' => 'avatar', 'width' => '128', 'height' => '128']); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="fw-medium d-block mb-1">
                                            <?= __d('cake_d_c/users', '{0} {1}', h($user->first_name ?? ''), h($user->last_name ?? '')); ?>
                                        </span>
                                        <span class="text-muted d-block">
                                            <?= h($user->username ?? '') ?> · <?= h($user->email ?? '') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6">
                                <label class="form-label">Nazwa użytkownika</label>
                                <p class="form-control-plaintext border rounded px-2 py-1 bg-light mb-0"><?= h($user->username ?? '') ?></p>
                            </div>
                            <div class="col-xl-6">
                                <label class="form-label">E-mail</label>
                                <p class="form-control-plaintext border rounded px-2 py-1 bg-light mb-0"><?= h($user->email ?? '') ?></p>
                                <div class="form-text text-muted mt-1">
                                    Jeśli Twój adres e-mail uległ zmianie, skontaktuj się z naszym supportem:
                                    <a href="mailto:kontakt@booklio.pl?subject=Zmiana%20adresu%20e-mail">kontakt@booklio.pl</a>
                                </div>
                            </div>
                            <div class="col-xl-6">
                                <label class="form-label">Imię</label>
                                <p class="form-control-plaintext border rounded px-2 py-1 bg-light mb-0"><?= h($user->first_name ?? '') ?></p>
                            </div>
                            <div class="col-xl-6">
                                <label class="form-label">Nazwisko</label>
                                <p class="form-control-plaintext border rounded px-2 py-1 bg-light mb-0"><?= h($user->last_name ?? '') ?></p>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row gy-3">
                            <div class="col-xl-12">
                                <h6 class="mb-2">Konta społecznościowe</h6>
                                <?= $this->User->socialConnectLinkList($user->social_accounts ?? []) ?>
                                <?php if (!empty($user->social_accounts)) : ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Avatar</th>
                                                    <th>Dostawca</th>
                                                    <th>Link</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($user->social_accounts as $socialAccount): ?>
                                                    <?php $escapedUsername = h($socialAccount->username); ?>
                                                    <?php $linkText = empty($escapedUsername) ? __('Link do {0}', h($socialAccount->provider)) : h($socialAccount->username); ?>
                                                    <tr>
                                                        <td><?= $this->Html->image($socialAccount->avatar, ['width' => '40', 'height' => '40', 'class' => 'rounded']); ?></td>
                                                        <td><?= h($socialAccount->provider) ?></td>
                                                        <td>
                                                            <?php if ($socialAccount->link && $socialAccount->link !== '#'): ?>
                                                                <?= $this->Html->link($linkText, $socialAccount->link, ['target' => '_blank', 'rel' => 'noopener']) ?>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Brak podłączonych kont społecznościowych.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>


                    <div id="security-section" class="mt-4 pt-4 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-1">
                            <div class="fw-semibold d-block fs-15"><i class="ri-shield-keyhole-line me-1"></i><?= __('Bezpieczeństwo') ?></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-1">
                            <div class="fw-medium d-block"><?= __('Zmień hasło') ?></div>
                        </div>
                        <p class="text-muted mb-2"><?= __('Możesz zmienić hasło przyciskiem poniżej.') ?></p>
                        <?php if (!empty($isCurrentUser)) : ?>
                            <?= $this->Html->link(__('Zmień hasło'), ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'changePassword'], ['class' => 'btn btn-primary btn-sm']); ?>
                        <?php endif; ?>

                        <?php if (!empty($isCurrentUser)): ?>
                        <hr class="my-4">

                        <!-- ── PIN bezpieczeństwa (do odblokowania ekranu po bezczynności) ── -->
                        <?php $hasPin = !empty($user->pin_hash); ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                            <div class="fw-semibold d-block fs-15">
                                <i class="ri-lock-password-line me-1"></i><?= __('PIN bezpieczeństwa') ?>
                                <?php if ($hasPin): ?>
                                    <span class="badge bg-success-subtle text-success ms-2"><?= __('Ustawiony') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary ms-2"><?= __('Brak') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="text-muted mb-3" style="font-size:.85rem">
                            <?= __('PIN to szybsza alternatywa dla hasła — używany do odblokowania ekranu po bezczynności. Wystarczą 4–6 cyfr.') ?>
                        </p>

                        <form id="pinForm" autocomplete="off" novalidate>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted mb-1"><?= __('Nowy PIN (4–6 cyfr)') ?></label>
                                    <input type="password" id="pinNew" class="form-control" inputmode="numeric"
                                           pattern="[0-9]{4,6}" minlength="4" maxlength="6"
                                           placeholder="••••" autocomplete="new-password">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small text-muted mb-1"><?= __('Aktualne hasło (potwierdzenie)') ?></label>
                                    <input type="password" id="pinPwd" class="form-control" autocomplete="current-password">
                                </div>
                                <div class="col-md-3 d-flex gap-1">
                                    <button type="submit" id="pinSaveBtn" class="btn btn-primary btn-sm flex-grow-1">
                                        <i class="ri-save-line me-1"></i>
                                        <?= $hasPin ? __('Zmień') : __('Ustaw') ?>
                                    </button>
                                    <?php if ($hasPin): ?>
                                    <button type="button" id="pinDeleteBtn" class="btn btn-outline-danger btn-sm"
                                            title="<?= __('Usuń PIN') ?>">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div id="pinMsg" class="mt-2 small" hidden></div>
                        </form>

                        <script>
                        (function () {
                            var csrf = '<?= h($this->request->getAttribute('csrfToken') ?? '') ?>';
                            var form = document.getElementById('pinForm');
                            var msg  = document.getElementById('pinMsg');
                            var btnDel = document.getElementById('pinDeleteBtn');

                            function showMsg(text, ok) {
                                msg.textContent = text;
                                msg.className = 'mt-2 small ' + (ok ? 'text-success' : 'text-danger');
                                msg.hidden = false;
                            }

                            function postJson(url, data, cb) {
                                var body = new URLSearchParams(Object.assign({_csrfToken: csrf}, data));
                                fetch(url, {
                                    method: 'POST',
                                    headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest',
                                               'Content-Type': 'application/x-www-form-urlencoded' },
                                    credentials: 'same-origin',
                                    body: body.toString(),
                                }).then(function (r) { return r.json(); }).then(cb)
                                  .catch(function () { showMsg('<?= __('Błąd połączenia.') ?>', false); });
                            }

                            form.addEventListener('submit', function (e) {
                                e.preventDefault();
                                var pin = document.getElementById('pinNew').value;
                                var pwd = document.getElementById('pinPwd').value;
                                if (!/^\d{4,6}$/.test(pin)) {
                                    showMsg('<?= __('PIN musi mieć 4–6 cyfr.') ?>', false);
                                    return;
                                }
                                if (!pwd) {
                                    showMsg('<?= __('Wpisz aktualne hasło.') ?>', false);
                                    return;
                                }
                                postJson('/set-pin', { new_pin: pin, current_password: pwd }, function (d) {
                                    if (d.success) {
                                        showMsg('<?= __('PIN zapisany. Odśwież stronę aby zobaczyć zmianę statusu.') ?>', true);
                                        document.getElementById('pinNew').value = '';
                                        document.getElementById('pinPwd').value = '';
                                    } else {
                                        showMsg(d.error || '<?= __('Błąd zapisu.') ?>', false);
                                    }
                                });
                            });

                            if (btnDel) {
                                btnDel.addEventListener('click', function () {
                                    var pwd = prompt('<?= __('Aby usunąć PIN podaj aktualne hasło:') ?>');
                                    if (!pwd) return;
                                    postJson('/delete-pin', { current_password: pwd }, function (d) {
                                        if (d.success) {
                                            showMsg('<?= __('PIN usunięty. Odśwież stronę.') ?>', true);
                                        } else {
                                            showMsg(d.error || '<?= __('Błąd usuwania.') ?>', false);
                                        }
                                    });
                                });
                            }
                        })();
                        </script>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-footer border-top-0 d-flex justify-content-end">
                    <a href="<?= $this->Url->build('/') ?>" class="btn btn-secondary btn-wave"><?= __('Powrót') ?></a>
                </div>
            </div>
        </div>
    </div>
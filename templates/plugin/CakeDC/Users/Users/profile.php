    <?php
    // Zmienne z CakeDC Users: $user (Entity), $isCurrentUser (bool), $avatarPlaceholder (opcjonalnie)
    $avatarUrl = !empty($user->avatar) ? $user->avatar : ($avatarPlaceholder ?? 'https://ssl.gstatic.com/accounts/ui/avatar_2x.png');
    ?>
    <div class="row">
        <div class="col-xl-3">
            <div class="card custom-card">
                <div class="card-body">
                    <ul class="nav flex-column gap-1 nav-pills tab-style-7" role="tablist">
                        <li class="nav-item me-0" role="presentation">
                            <a class="nav-link d-inline-flex w-100 mb-2 bg-light active" id="account" data-bs-toggle="tab" role="tab" data-bs-target="#account-pane">Konto</a>
                        </li>
                        <li class="nav-item me-0" role="presentation">
                            <a class="nav-link bg-light d-inline-flex w-100 mb-0" href="<?= $this->Url->build(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'changePassword']) ?>">Bezpieczeństwo</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">Zmień hasło</div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">Zaktualizuj hasło, aby chronić swoje konto.</p>
                    <?php if (!empty($isCurrentUser)) : ?>
                        <?= $this->Html->link(
                            'Zmień hasło',
                            ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'changePassword'],
                            ['class' => 'btn btn-primary btn-wave btn-sm']
                        ); ?>
                    <?php else: ?>
                        <span class="text-muted small">Tylko zalogowany użytkownik może zmienić swoje hasło.</span>
                    <?php endif; ?>
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


                    <div class="tab-pane overflow-hidden p-0 border-0" id="security-tab-pane" role="tabpanel" aria-labelledby="security-tab" tabindex="0">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-1">
                            <div class="fw-semibold d-block fs-15"><?= __('Bezpieczeństwo') ?></div>
                        </div>
                        <p class="text-muted mb-2">Możesz zmienić hasło przyciskiem po lewej stronie.</p>
                        <?php if (!empty($isCurrentUser)) : ?>
                            <?= $this->Html->link('Zmień hasło', ['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'changePassword'], ['class' => 'btn btn-primary btn-sm']); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-footer border-top-0 d-flex justify-content-end">
                    <a href="<?= $this->Url->build('/') ?>" class="btn btn-secondary btn-wave"><?= __('Powrót') ?></a>
                </div>
            </div>
        </div>
    </div>
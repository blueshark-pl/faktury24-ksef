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

<script>
(function () {
    var sel = document.getElementById('role-select');
    var clientFields = document.querySelectorAll('.client-fields');
    function toggle() {
        var isClient = sel.value === 'client';
        clientFields.forEach(function (el) { el.style.display = isClient ? '' : 'none'; });
    }
    sel.addEventListener('change', toggle);
})();
</script>

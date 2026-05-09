<?php
/**
 * @var \App\View\AppView                  $this
 * @var \CakeDC\Users\Model\Entity\User    $user
 * @var \App\Model\Entity\ClientProfile    $profile
 * @var int                                $orderCount
 */
$this->assign('title', 'Edytuj klienta');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="ri-arrow-left-line me-1"></i>Wróć do listy
        </a>
        <h4 class="mb-0 fw-semibold">
            <i class="ri-user-settings-line me-1"></i>Edytuj klienta:
            <span class="text-primary"><?= h($user->email) ?></span>
        </h4>
    </div>
    <div class="text-end">
        <span class="badge bg-info-subtle text-info border border-info-subtle">
            <i class="ri-truck-line me-1"></i>Zleceń: <?= (int)$orderCount ?>
        </span>
    </div>
</div>

<?= $this->Flash->render() ?>

<?= $this->Form->create(null, ['url' => ['action' => 'edit', $user->id], 'class' => 'needs-validation', 'novalidate' => 'novalidate']) ?>

<div class="row g-3">
    <!-- Konto -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-2">
                <strong><i class="ri-shield-user-line me-1"></i>Konto użytkownika</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small text-muted">E-mail</label>
                    <input type="email" class="form-control" value="<?= h($user->email) ?>" disabled>
                    <div class="form-text">E-mail nie może być zmieniony.</div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Imię</label>
                        <input type="text" name="first_name" class="form-control" value="<?= h($user->first_name) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Nazwisko</label>
                        <input type="text" name="last_name" class="form-control" value="<?= h($user->last_name) ?>">
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input type="hidden" name="active" value="0">
                    <input class="form-check-input" type="checkbox" name="active" value="1"
                           id="activeCheck" <?= $user->active ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activeCheck">
                        Konto aktywne (klient może się logować)
                    </label>
                </div>
                <hr class="my-3">
                <div class="mb-2">
                    <label class="form-label small text-muted">Nowe hasło (zostaw puste, by nie zmieniać)</label>
                    <div class="input-group">
                        <input type="text" name="password" class="form-control" placeholder="(bez zmian)"
                               minlength="8" autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" id="genPwd" title="Wygeneruj losowe hasło">
                            <i class="ri-refresh-line"></i>
                        </button>
                    </div>
                    <div class="form-text">Min. 8 znaków. Po zapisie hasło zostanie zhashowane.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profil firmy -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-2">
                <strong><i class="ri-building-line me-1"></i>Profil firmy klienta</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small text-muted">NIP <span class="text-danger">*</span></label>
                    <input type="text" name="nip" class="form-control" required minlength="9"
                           value="<?= h($profile->nip ?? '') ?>">
                    <div class="form-text">Po tym NIP-ie portal dopasowuje zlecenia (<code>speed_orders.buyer_nip</code>).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted">Nazwa firmy</label>
                    <input type="text" name="company_name" class="form-control"
                           value="<?= h($profile->company_name ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted">Domyślny język portalu</label>
                    <select name="locale" class="form-select">
                        <option value="pl" <?= ($profile->locale ?? 'pl') === 'pl' ? 'selected' : '' ?>>Polski (PL)</option>
                        <option value="en" <?= ($profile->locale ?? '') === 'en' ? 'selected' : '' ?>>English (EN)</option>
                    </select>
                </div>
                <hr class="my-3">
                <div class="d-flex justify-content-between align-items-center text-muted small">
                    <span><i class="ri-time-line me-1"></i>Utworzono: <?= $user->created ? $user->created->format('d.m.Y H:i') : '—' ?></span>
                    <?php if ($user->last_login): ?>
                        <span><i class="ri-login-circle-line me-1"></i>Ost. logowanie: <?= $user->last_login->format('d.m.Y H:i') ?></span>
                    <?php else: ?>
                        <span class="text-warning"><i class="ri-error-warning-line me-1"></i>Nigdy nie zalogowany</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 d-flex gap-2 justify-content-between">
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i>Zapisz zmiany
        </button>
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary">Anuluj</a>
    </div>
    <?= $this->Form->postLink(
        '<i class="ri-delete-bin-line me-1"></i>Usuń klienta',
        ['action' => 'delete', $user->id],
        [
            'class'   => 'btn btn-outline-danger',
            'escape'  => false,
            'confirm' => 'Usunąć klienta ' . h($user->email) . '? Tej operacji nie można cofnąć.',
        ]
    ) ?>
</div>

<?= $this->Form->end() ?>

<script>
document.getElementById('genPwd')?.addEventListener('click', function () {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
    var pwd = '';
    for (var i = 0; i < 12; i++) pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    var input = document.querySelector('input[name="password"]');
    if (input) { input.value = pwd; input.focus(); input.select(); }
});
</script>

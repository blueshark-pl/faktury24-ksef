<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\CrmEmailAccount $entity
 * @var bool $isEdit
 */
$this->assign('title', $isEdit ? __('Edytuj skrzynkę') : __('Nowa skrzynka IMAP'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold">
        <i class="ri-<?= $isEdit ? 'edit' : 'add' ?>-line me-1"></i>
        <?= $isEdit ? __('Edytuj skrzynkę IMAP') : __('Nowa skrzynka IMAP') ?>
    </h4>
    <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line"></i> <?= __('Wróć') ?>
    </a>
</div>

<div class="alert alert-info small">
    <strong><?= __('Ważne:') ?></strong>
    <?= __('Dla Gmail używaj') ?> <a href="https://myaccount.google.com/apppasswords" target="_blank">app-specific password</a>,
    <?= __('nie zwykłego. Dla Outlook/O365 - password OAuth lub app password w Modern Authentication.') ?>
    <?= __('Hasło jest szyfrowane AES-256 z klucza <code>Security.salt</code>.') ?>
</div>

<?= $this->Form->create($entity) ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><?= __('Konto') ?></h6>
                <div class="mb-2">
                    <label class="form-label small"><?= __('Nazwa (dla listy)') ?> *</label>
                    <input name="label" required class="form-control" value="<?= h($entity->label) ?>"
                           placeholder="np. Sprzedaż – Krzysztof">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Email / Login *</label>
                    <input name="username" type="email" required class="form-control" value="<?= h($entity->username) ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small"><?= __('Hasło') ?> <?= $isEdit ? '<span class="text-muted small">(zostaw puste żeby nie zmieniać)</span>' : '*' ?></label>
                    <input name="password" type="password" class="form-control" <?= $isEdit ? '' : 'required' ?>
                           autocomplete="new-password" placeholder="<?= $isEdit ? '••••••••' : '' ?>">
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Folder') ?></label>
                        <input name="folder" class="form-control" value="<?= h($entity->folder ?? 'INBOX') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small"><?= __('Sync co ile min') ?></label>
                        <input name="sync_frequency_min" type="number" min="5" max="1440" class="form-control"
                               value="<?= h($entity->sync_frequency_min ?? 5) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><?= __('Serwer IMAP') ?></h6>
                <div class="row g-2">
                    <div class="col-md-8">
                        <label class="form-label small">IMAP host *</label>
                        <input name="imap_host" required class="form-control" value="<?= h($entity->imap_host) ?>"
                               placeholder="imap.gmail.com">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Port *</label>
                        <input name="imap_port" type="number" required class="form-control" value="<?= h($entity->imap_port ?? 993) ?>">
                    </div>
                </div>
                <div class="mt-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="use_ssl" value="1"
                               id="ssl" <?= ($entity->use_ssl ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ssl">SSL/TLS</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                               id="act" <?= ($entity->is_active ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="act"><?= __('Aktywna (cron będzie pollować)') ?></label>
                    </div>
                </div>

                <hr>
                <div class="small text-muted">
                    <strong><?= __('Popularne konfiguracje:') ?></strong><br>
                    Gmail: <code>imap.gmail.com:993 SSL</code><br>
                    O365/Outlook: <code>outlook.office365.com:993 SSL</code><br>
                    Home.pl: <code>imap.home.pl:993 SSL</code>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 text-end">
        <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary"><?= __('Anuluj') ?></a>
        <button type="submit" class="btn btn-success"><i class="ri-save-line"></i> <?= __('Zapisz') ?></button>
    </div>
</div>
<?= $this->Form->end() ?>

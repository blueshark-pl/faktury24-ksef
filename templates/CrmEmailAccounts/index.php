<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\CrmEmailAccount> $rows
 */
$this->assign('title', __('Skrzynki IMAP dla CRM'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1 fw-semibold"><i class="ri-mail-line me-1 text-primary"></i><?= __('Skrzynki email CRM (IMAP)') ?></h4>
        <div class="text-muted small">
            <?= __('Cron pobiera co 5 min nowe emaile, matchuje po adresie z leadem i loguje activity <code>email_in</code>.') ?><br>
            <?= __('Uruchom manualnie:') ?> <code>bin/cake crm_email_poll</code>
        </div>
    </div>
    <div class="btn-group">
        <a href="<?= $this->Url->build(['action' => 'googleAuth']) ?>" class="btn btn-sm text-white" style="background:#EA4335;">
            <i class="ri-google-fill me-1"></i><?= __('Podłącz Gmail') ?>
        </a>
        <a href="<?= $this->Url->build(['action' => 'add']) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ri-mail-settings-line me-1"></i><?= __('IMAP manualnie') ?>
        </a>
    </div>
</div>

<?php if (!function_exists('imap_open')): ?>
    <div class="alert alert-info">
        <i class="ri-information-line"></i>
        <strong><?= __('PHP <code>imap</code> extension niedostępny.') ?></strong>
        <?= __('Ale możesz podłączyć skrzynkę Gmail przez OAuth 2.0 (nie wymaga rozszerzenia)') ?> —
        kliknij <strong>„Podłącz Gmail"</strong> powyżej.
        <div class="small text-muted mt-1">
            <?= __('Dla innych skrzynek (Outlook, home.pl, cyberfolks) włącz ext-imap w panelu hostingu (Advanced Features → Select PHP Version).') ?>
        </div>
    </div>
<?php endif; ?>

<?php
    $googleConfigured = (string)\Cake\Core\Configure::read('Google.clientId', '') !== '';
    if (!$googleConfigured):
?>
    <div class="alert alert-warning small">
        <i class="ri-alert-line"></i>
        <strong>Gmail OAuth nie skonfigurowany</strong> — dodaj w <code>config/app_local.php</code>:
        <pre class="mb-0 mt-1" style="font-size:11px;">'Google' =&gt; [
    'clientId'     =&gt; 'XXX.apps.googleusercontent.com',
    'clientSecret' =&gt; 'XXX',
    'redirectUri'  =&gt; '<?= h(rtrim((string)\Cake\Core\Configure::read('App.fullBaseUrl'), '/')) ?>/crm/email-accounts/google-callback',
],</pre>
        <div class="mt-1">
            Setup: <a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a>
            → nowy projekt → Enable Gmail API → OAuth consent screen → Credentials → OAuth 2.0 Client ID (Web application)
            → skopiuj Client ID i Secret + dodaj Authorized redirect URI podany wyżej.
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle small">
            <thead class="table-light">
                <tr>
                    <th><?= __('Nazwa') ?></th>
                    <th><?= __('Auth') ?></th>
                    <th><?= __('Skrzynka') ?></th>
                    <th><?= __('Host / Port') ?></th>
                    <th><?= __('Właściciel') ?></th>
                    <th class="text-end"><?= __('Sync') ?></th>
                    <th><?= __('Ostatni sync') ?></th>
                    <th><?= __('Status') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) === 0): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><?= __('Brak skonfigurowanych skrzynek.') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r):
                        $isOauth = ($r->auth_type ?? 'imap') === 'gmail_oauth';
                    ?>
                        <tr>
                            <td class="fw-semibold"><?= h($r->label) ?></td>
                            <td>
                                <?php if ($isOauth): ?>
                                    <span class="badge" style="background:#EA4335; color:#fff;">
                                        <i class="ri-google-fill"></i> Gmail OAuth
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">IMAP</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r->username) ?><br><small class="text-muted"><?= h($r->folder) ?></small></td>
                            <td class="small">
                                <?php if ($isOauth): ?>
                                    <span class="text-muted">via Gmail API v1</span>
                                <?php else: ?>
                                    <?= h($r->imap_host) ?>:<?= (int)$r->imap_port ?>
                                    <?= $r->use_ssl ? '<span class="badge bg-success-subtle text-success">SSL</span>' : '' ?>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= h(trim(($r->user?->first_name ?? '') . ' ' . ($r->user?->last_name ?? ''))) ?: '—' ?></td>
                            <td class="text-end small">
                                <?= (int)$r->messages_synced_total ?> msg<br>
                                <?= (int)$r->activities_created_total ?> act
                            </td>
                            <td class="small">
                                <?php if ($r->last_synced_at): ?>
                                    <?= h($r->last_synced_at->format('d.m.Y H:i')) ?>
                                    <?php if ($r->last_error): ?>
                                        <div class="text-danger small" title="<?= h($r->last_error) ?>">
                                            <i class="ri-error-warning-line"></i> <?= h(mb_substr($r->last_error, 0, 40)) ?>…
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted"><?= __('nigdy') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r->is_active): ?>
                                    <span class="badge bg-success"><?= __('Aktywna') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= __('Wyłączona') ?></span>
                                <?php endif; ?>
                                <div class="small text-muted mt-1"><?= (int)$r->sync_frequency_min ?> min</div>
                            </td>
                            <td class="text-end">
                                <?= $this->Form->postLink('<i class="ri-play-line"></i>',
                                    ['action' => 'test', $r->id],
                                    ['escape' => false, 'class' => 'btn btn-sm btn-outline-info',
                                     'title' => 'Testuj połączenie']) ?>
                                <a href="<?= $this->Url->build(['action' => 'edit', $r->id]) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="ri-pencil-line"></i>
                                </a>
                                <?= $this->Form->postLink('<i class="ri-delete-bin-line"></i>',
                                    ['action' => 'delete', $r->id],
                                    ['escape' => false, 'class' => 'btn btn-sm btn-outline-danger',
                                     'confirm' => __('Usunąć konto?')]) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

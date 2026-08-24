<?php
/**
 * @var \App\View\AppView $this
 */
$this->assign('title', __('CRM Admin Tools'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0 fw-semibold"><i class="ri-tools-line me-1 text-primary"></i><?= __('CRM Admin Tools') ?></h4>
    <a href="<?= $this->Url->build(['controller' => 'CrmEmailAccounts', 'action' => 'index']) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="ri-arrow-left-line"></i> <?= __('Wróć') ?>
    </a>
</div>

<div class="alert alert-info small">
    <i class="ri-information-line"></i> Webowe uruchamianie migracji, cache clear i cron commands - bez potrzeby SSH.
    Wszystkie linki otwieraj w nowej karcie (Ctrl+Click) żeby móc łatwo powrócić.
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold"><i class="ri-database-2-line"></i> Baza danych</h6>
                <div class="d-grid gap-2">
                    <a href="<?= $this->Url->build(['action' => 'migrationStatus']) ?>" class="btn btn-outline-primary" target="_blank">
                        <i class="ri-file-list-3-line"></i> Migration status
                    </a>
                    <?= $this->Form->postLink(
                        '<i class="ri-arrow-up-circle-line"></i> Uruchom pending migracje',
                        ['action' => 'migrate'],
                        ['escape' => false, 'class' => 'btn btn-primary',
                         'target' => '_blank',
                         'confirm' => 'Uruchomic migracje? Doda brakujace tabele/kolumny.']
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold"><i class="ri-refresh-line"></i> Cache</h6>
                <div class="d-grid gap-2">
                    <?= $this->Form->postLink(
                        '<i class="ri-delete-bin-line"></i> Wyczysc wszystkie cache',
                        ['action' => 'clearCache'],
                        ['escape' => false, 'class' => 'btn btn-warning',
                         'target' => '_blank',
                         'confirm' => 'Wyczyscic cake cache + tmp/cache + opcache?']
                    ) ?>
                    <div class="small text-muted">Cake Cache, ORM schema cache, opcache_reset, tmp/cache/models,persistent,views</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold"><i class="ri-mail-check-line"></i> Email sync</h6>
                <div class="d-grid gap-2">
                    <?= $this->Form->postLink(
                        '<i class="ri-refresh-line"></i> Poll emails NOW',
                        ['action' => 'runCron', 'crm_email_poll'],
                        ['escape' => false, 'class' => 'btn btn-success',
                         'target' => '_blank',
                         'confirm' => 'Uruchomic crm_email_poll teraz?']
                    ) ?>
                    <div class="small text-muted">
                        Odpowiednik <code>bin/cake crm_email_poll</code>. Pobiera nowe wiadomosci z aktywnych kont Gmail OAuth + IMAP i tworzy activities dla matched leadow.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold"><i class="ri-cpu-line"></i> Workflows engine</h6>
                <div class="d-grid gap-2">
                    <?= $this->Form->postLink(
                        '<i class="ri-play-circle-line"></i> Run workflows NOW',
                        ['action' => 'runCron', 'crm_workflow_run'],
                        ['escape' => false, 'class' => 'btn btn-info',
                         'target' => '_blank',
                         'confirm' => 'Uruchomic crm_workflow_run teraz?']
                    ) ?>
                    <div class="small text-muted">
                        <code>bin/cake crm_workflow_run</code>. Odpali aktywne workflows (create_task, send_email, change_stage).
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold"><i class="ri-mail-send-line"></i> Digest zadań</h6>
                <div class="d-grid gap-2">
                    <?= $this->Form->postLink(
                        '<i class="ri-mail-line"></i> Wysli digest do handlowcow',
                        ['action' => 'runCron', 'crm_tasks_digest'],
                        ['escape' => false, 'class' => 'btn btn-outline-info',
                         'target' => '_blank',
                         'confirm' => 'Wyslac email digest z zadaniami do wszystkich handlowcow?']
                    ) ?>
                    <div class="small text-muted">
                        <code>bin/cake crm_tasks_digest</code>. Wysli email z listą zadań na dzis + zapomniane leady.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="fw-bold"><i class="ri-alarm-warning-line"></i> Vehicle alerts</h6>
                <div class="d-grid gap-2">
                    <?= $this->Form->postLink(
                        '<i class="ri-notification-3-line"></i> Wysli alerty o wygasajacych badaniach',
                        ['action' => 'runCron', 'alerts'],
                        ['escape' => false, 'class' => 'btn btn-outline-warning',
                         'target' => '_blank',
                         'confirm' => 'Wyslac alerty vehicle_maintenance?']
                    ) ?>
                    <div class="small text-muted">
                        <code>bin/cake alerts</code>. Sprawdzi wygasajace badania techniczne / OC pojazdow i wysli email.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<hr>

<div class="alert alert-secondary small mt-3">
    <strong>Setup crontab (jednorazowo na cyberfolks DirectAdmin → Cron Jobs):</strong>
    <pre class="mt-2 mb-0" style="font-size:11px;">
*/5  * * * *  cd <?= h(ROOT) ?> && /usr/bin/php bin/cake.php crm_email_poll > /dev/null 2>&1
*/10 * * * *  cd <?= h(ROOT) ?> && /usr/bin/php bin/cake.php crm_workflow_run > /dev/null 2>&1
0 7  * * 1-5  cd <?= h(ROOT) ?> && /usr/bin/php bin/cake.php crm_tasks_digest --stale-days=14 > /dev/null 2>&1
0 8  * * *    cd <?= h(ROOT) ?> && /usr/bin/php bin/cake.php alerts > /dev/null 2>&1</pre>
</div>

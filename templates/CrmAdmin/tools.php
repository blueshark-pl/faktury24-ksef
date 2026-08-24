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

<?php if (!empty($gitInfo['available'])): ?>
    <div class="alert alert-secondary small mb-3" style="background:#2d3140; color:#d4d4d4; border-color:#444;">
        <i class="ri-git-branch-line"></i>
        <strong>Aktualnie zainstalowany:</strong>
        <code style="color:#4ec9b0;"><?= h($gitInfo['commit'] ?? '?') ?></code>
        · branch <code style="color:#dcdcaa;"><?= h($gitInfo['branch'] ?? '?') ?></code>
        · <?= h($gitInfo['date'] ?? '?') ?><br>
        <span style="color:#9cdcfe;"><?= h($gitInfo['message'] ?? '') ?></span>
    </div>
<?php endif; ?>

<!-- Git Pull (na gorze, najwazniejsze) -->
<div class="card mb-3" style="border-left: 4px solid #94C81F;">
    <div class="card-body">
        <h6 class="fw-bold"><i class="ri-download-cloud-line"></i> Deploy najnowszej wersji</h6>
        <div class="d-flex gap-2 flex-wrap">
            <?= $this->Form->postLink(
                '<i class="ri-git-pull-request-line"></i> Git Pull',
                ['action' => 'gitPull'],
                ['escape' => false, 'class' => 'btn btn-success',
                 'target' => '_blank',
                 'confirm' => 'Uruchomic git pull na serwerze?']
            ) ?>
            <?= $this->Form->postLink(
                '<i class="ri-refresh-line"></i> Git Reset --HARD (brute force)',
                ['action' => 'gitPull', '?' => ['force' => 1]],
                ['escape' => false, 'class' => 'btn btn-danger',
                 'target' => '_blank',
                 'confirm' => 'UWAGA: git reset --hard USUNIE wszelkie lokalne zmiany + wymusi resync z GitHub. Uzyj tylko gdy git pull nie wgrywa poprawnie plikow!']
            ) ?>
            <a href="<?= $this->Url->build(['action' => 'fileCheck']) ?>" class="btn btn-outline-info" target="_blank">
                <i class="ri-file-search-line"></i> File Check (CrmEmailPollCommand.php)
            </a>
            <?= $this->Form->postLink(
                '<i class="ri-nuclear-line"></i> Nuclear Clear (opcache + composer + touch)',
                ['action' => 'nuclearClear'],
                ['escape' => false, 'class' => 'btn btn-warning',
                 'target' => '_blank',
                 'confirm' => 'Brute force cache reset - opcache + touch wszystkich .php + composer autoload?']
            ) ?>
        </div>
        <div class="small text-muted mt-2">
            <strong>Git Pull</strong> - zwykły merge z remote.<br>
            <strong>Reset --HARD</strong> - gdy pliki wgraly sie CZESCIOWO (np. metoda ma wywolanie ale nie ma definicji).<br>
            <strong>File Check</strong> - pokazuje ktore FALA metody sa w pliku (weryfikacja czy deploy przeszedl).<br>
            <strong>Nuclear Clear</strong> - opcache_reset + touch wszystkich .php + composer autoload. UZYWAJ PO git pull!
        </div>
    </div>
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

    <div class="col-md-12">
        <div class="card" style="border-left: 4px solid #FF9800;">
            <div class="card-body">
                <h6 class="fw-bold"><i class="ri-search-eye-line"></i> Diagnostyka leadow / emaila (FALA 14 troubleshoot)</h6>
                <form method="get" action="<?= $this->Url->build(['action' => 'findLead']) ?>" target="_blank" class="row g-2 mb-2">
                    <div class="col-md-8">
                        <input type="email" name="email" class="form-control" placeholder="marius.werth@wiegand-logistik.de" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="ri-user-search-line"></i> Znajdz lead po emailu
                        </button>
                    </div>
                </form>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?= $this->Url->build(['action' => 'analyzeLastEmail']) ?>" class="btn btn-primary" target="_blank">
                        <i class="ri-radar-line"></i> Analizuj ostatni email (FALA 15 debug)
                    </a>
                    <?= $this->Form->postLink(
                        '<i class="ri-restart-line"></i> Reset Gmail history_id (fresh sync ostatnie 30 dni)',
                        ['action' => 'resetGmailHistory'],
                        ['escape' => false, 'class' => 'btn btn-outline-warning',
                         'target' => '_blank',
                         'confirm' => 'Zresetowac history_id wszystkich kont Gmail? Kolejny sync pobierze ostatnie 30 dni maili (max 100).']
                    ) ?>
                </div>
                <div class="small text-muted mt-2">
                    <strong>Analizuj ostatni email</strong> - bierze OSTATNI email z <code>crm_email_messages</code> (mozesz filtrowac <code>?lead_id=...</code>) i pokazuje krok-po-kroku dlaczego FALA 15 nie utworzyla quote_request: body length / sygnaly heurystyki / GPT response z listą shipments.<br>
                    <strong>Znajdz lead</strong> - sprawdza czy lead z podanym emailem istnieje (exact + LIKE) + porownuje bytes (wykrywa spacje/wielkosc liter). Pokazuje tez ostatnie maile w <code>crm_email_messages</code>.<br>
                    <strong>Reset history_id</strong> - gdy Gmail juz przetworzyl maila w poprzednim polling'u i teraz go nie widzisz. Wymus fresh sync.
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
                    <?= $this->Form->postLink(
                        '<i class="ri-refresh-line"></i> Poll emails NOW (FORCE - ignoruj cooldown)',
                        ['action' => 'runCron', 'crm_email_poll', '?' => ['force' => 1]],
                        ['escape' => false, 'class' => 'btn btn-warning',
                         'target' => '_blank',
                         'confirm' => 'Uruchomic z --force? Ignoruje 5-minutowy cooldown miedzy sync-ami.']
                    ) ?>
                    <div class="small text-muted">
                        Standardowy poll respektuje <code>sync_frequency_min</code> (default 5 min).
                        Uzyj <strong>FORCE</strong> gdy poprzedni sync wywalil sie z bledem i chcesz od razu sprobowac ponownie.
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

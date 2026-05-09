<?php
/**
 * templates/layout/zynix.php (CakePHP 5)
 * Minimalne różnice vs plain HTML:
 * - assety przez Html->css()/script() z ['block'=>true] i renderowane na dole
 * - metadane i tytuł przez $this->fetch()
 * - CSRF meta dla frontu
 */
use Cake\Core\Configure;

/** @var \Cake\View\View $this */
$title = $this->fetch('title') ?: 'ZYNIX – Admin & Dashboard';
$lang  = 'pl';
$dir   = 'ltr';
$isDemo = (bool)(Configure::read('App.demo') ?? false);
$appVersion = trim((string)(Configure::read('App.version') ?? ''));
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" dir="<?= h($dir) ?>"
      data-nav-layout="vertical"
      data-theme-mode="light"
      data-header-styles="light"
      data-width="fullwidth"
      data-menu-styles="light"
      data-toggled="close">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= h($title) ?></title>

    <?php
    // Opis/autor/keywords – możesz nadpisać blokiem 'meta'
    echo $this->fetch('meta');

    // Favicon
    echo $this->Html->meta('icon', $this->Url->assetUrl('/assets/images/brand-logos/favicon.ico'), ['type' => 'image/x-icon']);

    // CSRF token dla JS (jeśli używasz CsrfProtectionMiddleware)
    $csrf = $this->request->getAttribute('csrfToken');
    if ($csrf) {
        echo $this->Html->meta(['name' => 'csrfToken', 'content' => $csrf]);
    }

    // ---------- CSS ----------
    echo $this->Html->css([
        '/assets/libs/bootstrap/css/bootstrap.min.css',
        '/assets/css/styles.css',
        '/assets/css/icons.css',
        '/assets/libs/node-waves/waves.min.css',
        '/assets/libs/simplebar/simplebar.min.css',
        '/assets/libs/flatpickr/flatpickr.min.css',
        '/assets/libs/@simonwep/pickr/themes/nano.min.css',
        '/assets/libs/choices.js/public/assets/styles/choices.min.css',
        '/assets/libs/@tarekraafat/autocomplete.js/css/autoComplete.css',
        '/assets/libs/prismjs/themes/prism-coy.min.css',
    ], ['block' => true]);

    // Dodatkowe style z widoków: $this->assign('css', '...') albo $this->Html->css([...], ['block'=>true]);
    echo $this->fetch('css');
    // Local override: ensure proper hover contrast (background + text) in sidebar
    ?>
    <style>
      /* Show hand cursor on tab-style nav items */
      .nav-pills.tab-style-7 .nav-link { cursor: pointer; }
      .nav-pills.tab-style-7 .nav-item { cursor: pointer; }
    </style>
    <?php

       
        // ---------- JS na dole (lepsza wydajność) ----------
        echo $this->Html->script([
            'https://code.jquery.com/jquery-3.7.1.min.js',
            '/assets/libs/@popperjs/core/umd/popper.min.js',
            '/assets/libs/bootstrap/js/bootstrap.bundle.min.js']);

    echo $this->fetch('script'); // jeśli ktoś dodał skrypty w head przez $this->Html->script(..., ['block'=>true])

    // ---------- JS w <head> (jeśli coś MUSI być wcześnie) ----------
    // Załaduj Select2 bez 'defer', aby był dostępny dla inline scriptów w body.
    echo $this->Html->script('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
    // Pozostałe skrypty mogą być z 'defer'.
    $this->Html->script([
        '/assets/js/main.js',
    ], ['block' => 'headScripts', 'defer' => true]);
    echo $this->fetch('headScripts');
    echo $this->fetch('scriptBlocks'); // jeśli ktoś dodał skrypty w head przez $this->Html->script(..., ['block'=>true])
    ?>
    <?= $this->Html->css('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css') ?>
    <?= $this->Html->css('https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css') ?>
    <?= $this->Html->script('https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js') ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons/css/flag-icons.min.css">

</head>

<body>
    <!-- Start Switcher -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="switcher-canvas" aria-labelledby="offcanvasRightLabel">
        <div class="offcanvas-header border-bottom d-block p-0 position-relative">
          <img src="/img/ksef-post.jpg" alt="KSeF" class="w-100" style="display:block;object-fit:cover;" />
          <button type="button" class="btn-close position-absolute" data-bs-dismiss="offcanvas" aria-label="Close" style="top:8px; right:8px;"></button>
        </div>
        <div class="offcanvas-body">
            <?php
            // Pasek statusu KSeF – przeniesiony do offcanvas
            $status = $this->getRequest()->getSession()->read('Ksef.status');
            $state = 'unknown';
            $active = null;
            $envSide = null;
            $timeStr = null;
            $fullTimeStr = null;
            $lastError = null;
            $usingMaster = false;
            $usingMasterMode = '';
            $identifierNip = '';
            $checkKind = '';
            $permissionType = '';
            if (is_array($status)) {
                $active = (bool)($status['active'] ?? false);
                $envSide = $status['env'] ?? null;
                $lastError = $status['lastError'] ?? null;
                $checkKind = (string)($status['checkKind'] ?? '');
                $permissionType = (string)($status['permissionType'] ?? '');
                $ts = (int)($status['ts'] ?? 0);
                if ($ts > 0) {
                    try {
                        $dt = (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                        $timeStr = $dt->format('H:i');
                        $fullTimeStr = $dt->format('Y-m-d H:i:s');
                    } catch (\Throwable $e) {
                        $timeStr = null;
                        $fullTimeStr = null;
                    }
                }
                $state = $active ? 'active' : 'inactive';
                $usingMaster = (bool)($status['usingMaster'] ?? false);
                $usingMasterMode = (string)($status['usingMasterMode'] ?? '');
                $identifierNip = (string)($status['identifierNip'] ?? '');
            }
            $classes = [
                'active'   => 'bg-success-subtle border-success text-success',
                'inactive' => 'bg-danger-subtle border-danger text-danger',
                'unknown'  => 'bg-secondary-subtle border-secondary text-secondary',
            ][$state] ?? 'bg-secondary-subtle border-secondary text-secondary';
            $dotColor = [
                'active'   => '#22c55e',
                'inactive' => '#ef4444',
                'unknown'  => '#9ca3af',
            ][$state] ?? '#9ca3af';
            $dotClass = 'ksef-dot' . ($state === 'active' ? ' pulse' : '');
            $tooltip = '';
            if ($state === 'active') {
                $tooltip = 'Ostatnie potwierdzenie: ' . ($fullTimeStr ?: 'brak');
            } elseif ($state === 'inactive') {
                $tooltip = 'Błąd: ' . (is_string($lastError) ? $lastError : 'Nieznany') . ' \nOstatnia próba: ' . ($fullTimeStr ?: 'brak');
            } else {
                $tooltip = 'Połączenie nie było jeszcze weryfikowane w tej sesji.';
            }
            ?>
            <div class="px-2 pb-3">
                            <div id="ksef-ajax-unknown" class="rounded-3 border p-3 mb-3 border-danger<?= $state === 'unknown' ? '' : ' d-none' ?>">
                <div class="d-flex align-items-start gap-3">
                  <div class="pt-1">
                                        <span class="ksef-dot" style="width:12px;height:12px;border-radius:50%;display:inline-block;background: #9ca3af;"></span>
                  </div>
                  <div class="flex-grow-1">
                                        <h6 class="mb-1">KSeF (InvoiceWrite): Niezweryfikowano</h6>
                                        <p class="mb-2 text-muted">Nie sprawdzono jeszcze w tej sesji, czy masz aktywne uprawnienie <strong>InvoiceWrite</strong> (wystawianie faktur) dla firmy. Certyfikaty są obsługiwane centralnie (MASTER).</p>
                    <div class="d-flex flex-wrap gap-2">
                      <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ksefVideoModal">
                        Instrukcja wideo (YouTube)
                      </button>
                      <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => 'prod']]) ?>"
                         class="btn btn-outline-secondary btn-sm">
                        Podgląd odebranych
                      </a>
                                            <button type="button" class="btn btn-outline-primary btn-sm" data-ksef-invoicewrite-refresh>
                                                Sprawdź InvoiceWrite
                                            </button>
                    </div>
                  </div>
                </div>
              </div>

                            <div id="ksef-ajax-inactive" class="rounded-3 p-3 mb-3<?= $state === 'inactive' ? '' : ' d-none' ?>">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <span class="ksef-dot" style="width:12px;height:12px;border-radius:50%;display:inline-block;background: #ef4444;"></span>
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span id="ksef-ajax-inactive-title" class="text-danger fw-semibold">Brak InvoiceWrite</span>
                                        <span id="ksef-ajax-inactive-error" class="text-muted small"<?= (!empty($lastError) && is_string($lastError)) ? '' : ' style="display:none"' ?>>Błąd: <?= h((string)$lastError) ?></span>
                                        <span id="ksef-ajax-inactive-ts" class="text-muted small"<?= $fullTimeStr ? '' : ' style="display:none"' ?>>Ostatnia próba: <strong><?= h((string)$fullTimeStr) ?></strong></span>
                  </div>
                                    <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-ksef-invoicewrite-refresh>Odśwież</button>
                                    </div>
                </div>
              </div>

                            <div id="ksef-ajax-active" class="rounded-3 p-3 mb-3<?= $state === 'active' ? '' : ' d-none' ?>">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <span class="ksef-dot pulse" style="width:12px;height:12px;border-radius:50%;display:inline-block;background: #22c55e;"></span>
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span id="ksef-ajax-active-title" class="text-success fw-semibold">InvoiceWrite: aktywne</span>
                                        <span id="ksef-ajax-env" class="text-muted small"<?= $envSide ? '' : ' style="display:none"' ?>>Środowisko: <span id="ksef-ajax-env-value"><?= h(strtoupper((string)$envSide)) ?></span></span>
                                        <span id="ksef-ajax-active-ts" class="text-muted small"<?= $fullTimeStr ? '' : ' style="display:none"' ?>>Ostatnie potwierdzenie: <strong><?= h((string)$fullTimeStr) ?></strong></span>
                                        <span id="ksef-ajax-master" class="text-warning small"<?= $usingMaster ? '' : ' style="display:none"' ?> data-bs-toggle="tooltip" data-bs-placement="top" title="Używany certyfikat master (tryb: <?= h($usingMasterMode ?: 'nieznany') ?>)<?= $identifierNip ? (' – NIP: ' . h($identifierNip)) : '' ?>.">MASTER</span>
                  </div>
                                    <div class="w-100"></div>
                                    <div class="text-muted small">
                                        Faktury24 mają uprawnienia.
                                    </div>
                  <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-ksef-invoicewrite-refresh>Odśwież status</button>
                  </div>
                </div>
              </div>
              
                <style>
                    @keyframes ksefPulse { 0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34,197,94,0.6);} 70% { transform: scale(1.15); box-shadow: 0 0 0 8px rgba(34,197,94,0);} 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34,197,94,0);} }
                    .ksef-dot.pulse { animation: ksefPulse 1.8s ease-in-out infinite; }
                </style>
            </div>
            <!-- Usunięto pozostałe elementy switchera, pozostawiono tylko status KSeF -->
        </div>
    </div>
    <!-- End Switcher -->


    <!-- Loader -->
    <!-- <div id="loader" >
        <img src="../assets/images/media/loader.svg" alt="">
    </div> -->
    <!-- Loader -->

    <div class="page">

            <!-- app-header -->
            <header class="app-header sticky" id="header">

                <!-- Start::main-header-container -->
                <div class="main-header-container container-fluid">

                    <!-- Start::header-content-left -->
                    <div class="header-content-left">

                        <!-- Start::header-element -->
                        <div class="header-element">
                            <div class="horizontal-logo">
                                <a href="/" class="header-logo">
                                    <img src="/assets/images/brand-logos/desktop-logo.png" alt="logo" class="desktop-logo">
                                    <img src="/assets/images/brand-logos/toggle-logo.png" alt="logo" class="toggle-logo">
                                    <img src="/assets/images/brand-logos/desktop-dark.png" alt="logo" class="desktop-dark">
                                    <img src="/assets/images/brand-logos/toggle-dark.png" alt="logo" class="toggle-dark">
                                </a>
                            </div>
                        </div>
                        <!-- End::header-element -->

                        <!-- Start::header-element -->
                        <div class="header-element">
                            <!-- Start::header-link -->
                            <a aria-label="Hide Sidebar" class="sidemenu-toggle header-link" data-bs-toggle="sidebar"
                                href="javascript:void(0);">
                                <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon menu-btn" width="32" height="32"
                                    fill="#000000" viewBox="0 0 256 256">
                                    <path
                                        d="M224,128a8,8,0,0,1-8,8H40a8,8,0,0,1,0-16H216A8,8,0,0,1,224,128ZM40,72H216a8,8,0,0,0,0-16H40a8,8,0,0,0,0,16ZM216,184H40a8,8,0,0,0,0,16H216a8,8,0,0,0,0-16Z">
                                    </path>
                                </svg>
                                <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon menu-btn-close" width="32"
                                    height="32" fill="#000000" viewBox="0 0 256 256">
                                    <path
                                        d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z">
                                    </path>
                                </svg>
                            </a>
                            <!-- End::header-link -->
                        </div>
                        <!-- End::header-element -->


                    </div>
                    <!-- End::header-content-left -->

                    <!-- Start::header-content-right -->
                    <ul class="header-content-right">


                        <!-- Start::header-element -->
                        <li class="header-element dropdown">
                            <!-- Start::header-link|dropdown-toggle -->
                            <?php
                            // Ustal nazwę aktualnie zalogowanego użytkownika do wyświetlenia w headerze
                            $identityHeader = $this->request->getAttribute('identity') ?? null;
                            $first = trim((string)($identityHeader?->get('first_name') ?? ''));
                            $last  = trim((string)($identityHeader?->get('last_name') ?? ''));
                            $full  = trim($first . ' ' . $last);
                            $displayName = (string)(
                                $identityHeader?->get('name')
                                ?? $identityHeader?->get('full_name')
                                ?? ($full !== '' ? $full : null)
                                ?? $identityHeader?->get('email')
                                ?? $identityHeader?->get('username')
                                ?? 'Użytkownik'
                            );
                            ?>
                            <a href="javascript:void(0);" class="header-link dropdown-toggle" id="mainHeaderProfile"
                                data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                <div class="d-flex align-items-center">
                                    <div class="d-xl-block d-none lh-1">
                                        Jesteś zalogowany jako, <?php $nameToShow = ($first !== '' || $last !== '') ? $full : $displayName; ?>
                                        <span class="fw-medium lh-1"><?= h($nameToShow) ?></span>
                                    </div>
                                </div>
                            </a>
                            <!-- End::header-link|dropdown-toggle -->
                            <ul class="main-header-dropdown dropdown-menu pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end"
                                aria-labelledby="mainHeaderProfile">
                                <li>
                                    <div class="py-2 px-3 text-center"> <span class="fw-semibold"> <?= h($nameToShow) ?> </span> <span
                                            class="d-block fs-12 text-muted">Właściciel</span> </div>
                                </li>
                                <li><a class="dropdown-item d-flex align-items-center" href="<?= $this->Url->build(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'profile']) ?>"><i
                                            class="ti ti-user text-primary me-2 fs-16"></i>Mój profil</a>
                                </li>
                                <li><a class="dropdown-item d-flex align-items-center" href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Companies', 'action' => 'edit']) ?>"><i
                                            class="ti ti-settings text-info me-2 fs-16"></i>Moja firma</a>
                                </li>
                                <!-- <li><a class="dropdown-item d-flex align-items-center" href="/"><i
                                            class="ti ti-headset text-warning me-2 fs-16"></i>Wsparcie</a>
                                </li> -->
                                <li class="py-2 px-3"><a class="btn btn-primary btn-sm w-100" href="/logout">Wyloguj się</a>
                                </li>
                            </ul>
                        </li>
                        <!-- End::header-element -->

                        <!-- Start::header-element -->
                        <li class="header-element">
                          <!-- Start::header-link|switcher-icon (custom image) -->
                          <?php
                          // Ustal stan KSeF dla ikony w headerze
                          $ksefStatus = $this->getRequest()->getSession()->read('Ksef.status');
                          $ksefActive = false;
                          if (is_array($ksefStatus)) {
                              $ksefActive = (bool)($ksefStatus['active'] ?? false);
                          }
                          // Tooltip dla ikony – wyjaśnienie działania
                          $ksefHeaderTooltip = $ksefActive
                              ? 'Otwiera panel KSeF (status aktywny)'
                              : 'Otwiera panel KSeF i konfigurację (status nieaktywny)';
                          // Styl wyszarzenia, gdy nieaktywny
                              $ksefImgStyle = 'width:100%;height:32px;object-fit:cover;';
                          if (!$ksefActive) {
                              $ksefImgStyle .= 'opacity: 0.6;';
                          }
                          ?>
                          <!-- <a href="javascript:void(0);" class="header-link switcher-icon" data-bs-toggle="offcanvas"
                            data-bs-target="#switcher-canvas">
                            <img src="/img/ksef-post.jpg" alt="KSeF" class="rounded"
                                 style="<?= h($ksefImgStyle) ?>"
                                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                                 title="<?= h($ksefHeaderTooltip) ?>" />
                          </a> -->
                          <!-- End::header-link|switcher-icon -->
                        </li>
                        <!-- End::header-element -->

                    </ul>
                    <!-- End::header-content-right -->

                </div>
                <!-- End::main-header-container -->

            </header>
            <!-- /app-header -->
            <!-- Start::app-sidebar -->
            <aside class="app-sidebar sticky" id="sidebar">

                <!-- Start::main-sidebar-header -->
                <div class="main-sidebar-header">
                    <a href="/" class="header-logo">
                        <!-- <img src="/img/logo-faktury24.png" alt="logo" class="desktop-logo"> -->
                        <img src="/assets/images/brand-logos/toggle-dark.png" alt="logo" class="toggle-dark">
                        <img src="/assets/images/brand-logos/desktop-dark.png" alt="logo" class="desktop-dark">
                        <img src="/assets/images/brand-logos/toggle-logo.png" alt="logo" class="toggle-logo">
                    </a>
                </div>
                <!-- End::main-sidebar-header -->

                <!-- Start::main-sidebar -->
                                                <div class="main-sidebar " id="sidebar-scroll">

                    <!-- Start::nav -->
                    <?php
                    // ── Sidebar active-state helpers ──────────────────────────────────────
                    $_navCtrl   = strtolower((string)($this->request->getParam('controller') ?? ''));
                    $_navAction = strtolower((string)($this->request->getParam('action') ?? ''));
                    $_navPlugin = strtolower((string)($this->request->getParam('plugin') ?? ''));

                    /**
                     * Returns 'active' when current request matches controller+action+optional query params.
                     * Pass $action='' to match any action within the controller.
                     */
                    $navActive = function(string $ctrl, string $action = '', array $query = []) use ($_navCtrl, $_navAction, $_navPlugin): string {
                        if ($_navPlugin !== '') return '';
                        if (strtolower($ctrl) !== $_navCtrl) return '';
                        if ($action !== '' && strtolower($action) !== $_navAction) return '';
                        foreach ($query as $k => $v) {
                            if ((string)($this->request->getQuery($k) ?? '') !== (string)$v) return '';
                        }
                        return 'active';
                    };

                    /**
                     * Returns class string for a has-sub <li>.
                     * - open   : any child of the given controllers/actions is currently active
                     * - active : same condition (motyw ZYNIX expects active on the <li> too, not just open)
                     *
                     * $match is either:
                     *   - array of controller names (strings) — open when current ctrl is in the list
                     *   - callable returning bool              — open when callable returns true
                     */
                    $liClass = function(array|callable $match) use ($_navCtrl, $_navPlugin): string {
                        if ($_navPlugin !== '') return 'slide has-sub';
                        $isOpen = is_callable($match)
                            ? (bool)$match()
                            : in_array($_navCtrl, $match, true);
                        return $isOpen ? 'slide has-sub open active' : 'slide has-sub';
                    };
                    ?>
                    <nav class="main-menu-container nav nav-pills flex-column sub-open">
                        <div class="slide-left" id="slide-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"> <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path> </svg>
                        </div>
                        <ul class="main-menu">
                            <?php if (($currentRole ?? '') === 'client'): ?>
                                <!-- ── SIDEBAR: Portal klienta ──────────────────────────────── -->
                                <li class="slide__category"><span class="category-name"><?= __('Portal klienta') ?></span></li>
                                <li class="slide">
                                    <?= $this->Html->link(
                                        '<i class="ri-truck-line side-menu__icon" style="font-size:1.25rem;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center"></i>'
                                        . '<span class="side-menu__label">' . h(__('Zlecenia transportowe')) . '</span>',
                                        ['controller' => 'ClientPortal', 'action' => 'index'],
                                        [
                                            'class'  => trim('side-menu__item ' . $navActive('clientportal', 'index')),
                                            'escape' => false,
                                        ]
                                    ) ?>
                                </li>
                            <?php else: ?>
                            <!-- Start::slide__category -->
                            <li class="slide__category"><span class="category-name">Faktury24</span></li>
                            <!-- End::slide__category -->
                            <!-- Fakturowanie -->
                            <li class="<?= $liClass(['invoices', 'nbp', 'legacyinvoices']) ?>">
                            <a href="javascript:void(0);" class="side-menu__item">
                                <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                <!-- ikona dokumentu -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><path d="M96,40H160l40,40V208a8,8,0,0,1-8,8H96a8,8,0,0,1-8-8V48A8,8,0,0,1,96,40Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><polyline points="160 40 160 80 200 80" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><line x1="112" y1="120" x2="184" y2="120" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><line x1="112" y1="152" x2="184" y2="152" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg>
                                <span class="side-menu__label">Fakturowanie</span>
                            </a>
                            <ul class="slide-menu child1">
                                <li class="slide">
                                <?= $this->Html->link(
                                        'Lista faktur',
                                        ['plugin' => false, 'controller' => 'Invoices', 'action' => 'index'],
                                        ['class' => trim('side-menu__item ' . $navActive('invoices', 'index'))]
                                ) ?>
                                </li>

                                <!-- Quick create submenu for specific invoice types -->
                                <?php
                                $_addActions = ['addvat','addnovat','addproforma','addcurrency','addadvance','addmargin','addrental','addinternal','addinternalevidence','addoss'];
                                ?>
                                <li class="<?= $liClass(function() use ($_navCtrl, $_navAction, $_addActions) {
                                    return $_navCtrl === 'invoices' && in_array($_navAction, $_addActions, true);
                                }) ?>">
                                    <a href="javascript:void(0);" class="side-menu__item">
                                        Wystaw fakturę
                                        <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                    </a>
                                    <ul class="slide-menu child2">
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura VAT',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'addVat'],
                                                ['class' => trim('side-menu__item ' . $navActive('invoices', 'addVat'))]
                                            ) ?>
                                        </li>
                                        <!-- <li class="slide">
                                            <?= $this->Html->link(
                                                'Rachunek',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'addNoVat'],
                                                ['class' => trim('side-menu__item ' . $navActive('invoices', 'addNoVat'))]
                                            ) ?>
                                        </li> -->
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Proforma / Oferta',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'addProforma'],
                                                ['class' => trim('side-menu__item ' . $navActive('invoices', 'addProforma'))]
                                            ) ?>
                                        </li>
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura walutowa',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'addCurrency'],
                                                ['class' => trim('side-menu__item ' . $navActive('invoices', 'addCurrency'))]
                                            ) ?>
                                        </li>
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura zaliczkowa',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'addAdvance'],
                                                ['class' => trim('side-menu__item ' . $navActive('invoices', 'addAdvance'))]
                                            ) ?>
                                        </li>
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura marża',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'addMargin'],
                                                ['class' => trim('side-menu__item ' . $navActive('invoices', 'addMargin'))]
                                            ) ?>
                                        </li>
                                        <?php if (!empty($rentalEnabled)): ?>
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Najem prywatny',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'addRental'],
                                                ['class' => trim('side-menu__item ' . $navActive('invoices', 'addRental'))]
                                            ) ?>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </li>
                                <li class="slide">
                                    <?= $this->Html->link(
                                            'Słownik Walutowy NBP',
                                            ['plugin' => false, 'controller' => 'Nbp', 'action' => 'dictionary'],
                                            ['class' => 'side-menu__item ' . $navActive('nbp', 'dictionary')]
                                    ) ?>
                                </li>
                                <li class="slide">
                                    <?= $this->Html->link(
                                            'Archiwum (faktury24)',
                                            ['plugin' => false, 'controller' => 'LegacyInvoices', 'action' => 'index'],
                                            ['class' => 'side-menu__item ' . $navActive('legacyinvoices', 'index')]
                                    ) ?>
                                </li>
                            </ul>
                            </li>

                            <!-- Kontrahenci -->
                            <li class="<?= $liClass(['contractors']) ?>">
                            <a href="javascript:void(0);" class="side-menu__item">
                                <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><circle cx="96" cy="96" r="40" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><path d="M16,208a80,80,0,0,1,160,0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><circle cx="192" cy="72" r="24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg>
                                <span class="side-menu__label">Kontrahenci</span>
                            </a>
                            <ul class="slide-menu child1">
                                <li class="slide">
                                    <?= $this->Html->link(
                                            'Lista kontrahentów',
                                            ['plugin' => false, 'controller' => 'Contractors', 'action' => 'index'],
                                            ['class' => 'side-menu__item ' . $navActive('contractors', 'index')]
                                    ) ?>
                                </li>
                            </ul>
                            </li>

                            <!-- Zlecenia Speed -->
                            <li class="<?= $liClass(['speedorders']) ?>">
                            <a href="javascript:void(0);" class="side-menu__item">
                                <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                <i class="ri-truck-line side-menu__icon"></i>
                                <span class="side-menu__label">Zlecenia</span>
                            </a>
                            <ul class="slide-menu child1">
                                <li class="slide">
                                    <?= $this->Html->link(
                                            'Lista zleceń',
                                            ['plugin' => false, 'controller' => 'SpeedOrders', 'action' => 'index'],
                                            ['class' => 'side-menu__item ' . $navActive('speedorders', 'index')]
                                    ) ?>
                                </li>
                            </ul>
                            </li>

                            <!-- Towary i usługi -->
                            <li class="<?= $liClass(['products']) ?>">
                            <a href="javascript:void(0);" class="side-menu__item">
                                <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><rect x="32" y="56" width="80" height="80" rx="8" stroke="currentColor" fill="none" stroke-width="16"/><rect x="144" y="56" width="80" height="80" rx="8" stroke="currentColor" fill="none" stroke-width="16"/><rect x="32" y="160" width="192" height="40" rx="8" stroke="currentColor" fill="none" stroke-width="16"/></svg>
                                <span class="side-menu__label">Towary i usługi</span>
                            </a>
                            <ul class="slide-menu child1">
                                <li class="slide">
                                    <?= $this->Html->link(
                                            'Lista towarów i usług',
                                            ['plugin' => false, 'controller' => 'Products', 'action' => 'index'],
                                            ['class' => 'side-menu__item ' . $navActive('products', 'index')]
                                    ) ?>
                                </li>
                            </ul>
                            </li>

                            <!-- Wyciągi bankowe -->
                            <li class="<?= $liClass(['banktransactions']) ?>">
                            <a href="javascript:void(0);" class="side-menu__item">
                                <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                <i class="ri-bank-line side-menu__icon"></i>
                                <span class="side-menu__label">Wyciągi bankowe</span>
                            </a>
                            <ul class="slide-menu child1">
                                <li class="slide">
                                    <?= $this->Html->link(
                                            'Lista importów',
                                            ['plugin' => false, 'controller' => 'BankTransactions', 'action' => 'index'],
                                            ['class' => 'side-menu__item ' . $navActive('banktransactions', 'index')]
                                    ) ?>
                                </li>
                                <li class="slide">
                                    <?= $this->Html->link(
                                            'Wszystkie transakcje',
                                            ['plugin' => false, 'controller' => 'BankTransactions', 'action' => 'transactions'],
                                            ['class' => 'side-menu__item ' . $navActive('banktransactions', 'transactions')]
                                    ) ?>
                                </li>
                                <li class="slide">
                                    <?= $this->Html->link(
                                            'Importuj MT940',
                                            ['plugin' => false, 'controller' => 'BankTransactions', 'action' => 'import'],
                                            ['class' => 'side-menu__item ' . $navActive('banktransactions', 'import')]
                                    ) ?>
                                </li>
                            </ul>
                            </li>

                            <!-- Rozliczenia -->
                            <li class="<?= $liClass(['reconciliations']) ?>">
                            <a href="javascript:void(0);" class="side-menu__item">
                                <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                <i class="ri-scales-3-line side-menu__icon"></i>
                                <span class="side-menu__label">Rozliczenia</span>
                            </a>
                            <ul class="slide-menu child1">
                                <li class="slide">
                                    <?= $this->Html->link(
                                        'KSeF (nowe)',
                                        ['plugin' => false, 'controller' => 'Reconciliations', 'action' => 'indexKsef'],
                                        ['class' => 'side-menu__item ' . $navActive('reconciliations', 'indexKsef')]
                                    ) ?>
                                </li>
                                <li class="slide">
                                    <?= $this->Html->link(
                                        'Speed (archiwalne)',
                                        ['plugin' => false, 'controller' => 'Reconciliations', 'action' => 'indexSpeed'],
                                        ['class' => 'side-menu__item ' . $navActive('reconciliations', 'indexSpeed')]
                                    ) ?>
                                </li>
                            </ul>
                            </li>

                            <!-- Kredyt kupiecki -->
                            <li class="slide <?= $navActive('creditchecks', 'index') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-shield-check-line side-menu__icon"></i>
                                    <span class="side-menu__label">Kredyt kupiecki</span>',
                                    ['plugin' => false, 'controller' => 'CreditChecks', 'action' => 'index'],
                                    ['escape' => false, 'class' => 'side-menu__item ' . $navActive('creditchecks', 'index')]
                                ) ?>
                            </li>

                            <!-- Karty paliwowe E100 -->
                            <li class="slide <?= $navActive('fuelcards', 'index') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-gas-station-line side-menu__icon"></i>
                                    <span class="side-menu__label">Karty paliwowe</span>',
                                    ['plugin' => false, 'controller' => 'FuelCards', 'action' => 'index'],
                                    ['escape' => false, 'class' => 'side-menu__item ' . $navActive('fuelcards', 'index')]
                                ) ?>
                            </li>

                            <!-- Księgowość -->
                            <li class="slide__category"><span class="category-name">Księgowość</span></li>
                            <li class="<?= $liClass(['ksefauthorizations']) ?>">
                            <a href="javascript:void(0);" class="side-menu__item">
                                <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><path d="M32,72H224V184a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><polyline points="32 72 128 136 224 72" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg>
                                <span class="side-menu__label">Dokumenty</span>
                            </a>
                            <ul class="slide-menu child1">
                                <li class="slide">
                                    <?= $this->Html->link(
                                            'Otrzymane z KSeF',
                                            ['plugin' => false, 'controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => 'prod']],
                                            ['class' => 'side-menu__item ' . $navActive('ksefauthorizations', 'received', ['env' => 'prod'])]
                                    ) ?>
                                </li>
                            </ul>
                            </li>

                            <!-- <li class="slide">
                                <?= $this->Html->link(
                                    '<svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 256 256">
                                    <rect width="256" height="256" fill="none"/>
                                    <circle cx="88" cy="108" r="36" fill="none" stroke="currentColor" stroke-width="16"/>
                                    <path d="M16,208a72,72,0,0,1,144,0" fill="none" stroke="currentColor" stroke-width="16"/>
                                    <circle cx="196" cy="76" r="24" fill="none" stroke="currentColor" stroke-width="16"/>
                                    </svg>
                                    <span class="side-menu__label">Twoi klienci</span>',
                                    ['plugin' => false, 'controller' => 'AccountingAuthorizations', 'action' => 'check'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                                </li>
                            <li class="slide has-sub">
                            <a href="javascript:void(0);" class="side-menu__item">
                                <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><path d="M40,216H216" stroke="currentColor" fill="none" stroke-width="16"/><rect x="56" y="120" width="40" height="64" rx="4" stroke="currentColor" fill="none" stroke-width="16"/><rect x="108" y="88" width="40" height="96" rx="4" stroke="currentColor" fill="none" stroke-width="16"/><rect x="160" y="56" width="40" height="128" rx="4" stroke="currentColor" fill="none" stroke-width="16"/></svg>
                                <span class="side-menu__label">Raporty</span>
                            </a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="/raporty" class="side-menu__item">Ogólne</a></li>
                                <li class="slide"><a href="/raport_preferencyjnego_zus" class="side-menu__item">Preferencyjny ZUS</a></li>
                            </ul>
                            </li> -->

                            <!-- Zgłoszenia support – dla wszystkich użytkowników -->
                            <li class="slide__category"><span class="category-name">Pomoc</span></li>
                            <li class="slide <?= $navActive('SupportTickets', 'index') || $navActive('SupportTickets', 'add') || $navActive('SupportTickets', 'view') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-customer-service-2-line side-menu__icon"></i>
                                    <span class="side-menu__label">Zgłoszenia i uwagi</span>',
                                    ['plugin' => false, 'controller' => 'SupportTickets', 'action' => 'index'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li>

                            <?php
                            // Sekcja administracyjna – widoczna tylko dla administratorów
                            $identity = $this->request->getAttribute('identity') ?? null;
                            $isAdmin  = (bool)($identity?->get('is_admin') ?? false);
                            $role     = strtolower((string)($identity?->get('role') ?? ''));
                            if ($isAdmin || $role === 'admin'):
                            ?>
                            <!-- Administracja -->
                            <li class="slide__category"><span class="category-name">Administracja</span></li>
                            <li class="slide <?= $navActive('AdminClients', 'index') || $navActive('AdminClients', 'add') || $navActive('AdminClients', 'edit') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-user-2-line side-menu__icon"></i>
                                    <span class="side-menu__label">Klienci portalu</span>',
                                    ['plugin' => false, 'controller' => 'AdminClients', 'action' => 'index'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li>
                            <li class="slide <?= $navActive('Invoices', 'adminInvoices') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-file-list-3-line side-menu__icon"></i>
                                    <span class="side-menu__label">Faktury użytkowników</span>',
                                    ['plugin' => false, 'controller' => 'Invoices', 'action' => 'adminInvoices'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li>
                            <li class="slide <?= $navActive('Invoices', 'adminDrafts') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-draft-line side-menu__icon"></i>
                                    <span class="side-menu__label">Szkice faktur</span>',
                                    ['plugin' => false, 'controller' => 'Invoices', 'action' => 'adminDrafts'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li>
                            <li class="slide <?= $navActive('Invoices', 'adminDeletionLogs') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-delete-bin-2-line side-menu__icon"></i>
                                    <span class="side-menu__label">Logi usunięć</span>',
                                    ['plugin' => false, 'controller' => 'Invoices', 'action' => 'adminDeletionLogs'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li>
                            <li class="slide <?= $navActive('Invoices', 'adminSupport') || $navActive('Invoices', 'adminSupportView') ?>">
                                <?php
                                $adminSupportNewCount = 0;
                                try {
                                    $adminSupportNewCount = (int)$this->fetchTable('SupportTickets')->find()->where(['status' => 'nowe'])->count();
                                } catch (\Throwable) {}
                                ?>
                                <?= $this->Html->link(
                                    '<i class="ri-customer-service-2-line side-menu__icon"></i>
                                    <span class="side-menu__label">Zgłoszenia support</span>'
                                    . ($adminSupportNewCount > 0 ? ' <span class="badge bg-danger ms-1">' . $adminSupportNewCount . '</span>' : ''),
                                    ['plugin' => false, 'controller' => 'Invoices', 'action' => 'adminSupport'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li>
                            <li class="slide <?= $navActive('Tasks', 'index') || $navActive('Tasks', 'view') || $navActive('Tasks', 'add') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-kanban-view side-menu__icon"></i>
                                    <span class="side-menu__label">Tablica Kanban</span>',
                                    ['plugin' => false, 'controller' => 'Tasks', 'action' => 'index'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li>
                            <?php endif; ?>

                            <?php endif; /* end: not client role */ ?>
                        </ul>
                        <div class="slide-right" id="slide-right"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"> <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"></path> </svg></div>
                    </nav>
                    <!-- End::nav -->

                </div>
                <!-- End::main-sidebar -->



            </aside>
            <!-- End::app-sidebar -->

            <!--APP-CONTENT START-->
            <div class="main-content app-content">
                <div class="container-fluid mt-2">
                                        <?php
                                            $ksefModeEnabled = isset($ksefModeEnabled) ? (bool)$ksefModeEnabled : true;
                                            $ksefStatusTop = $this->getRequest()->getSession()->read('Ksef.status');
                                            $permLabel = 'wymagane';
                                            $permClass = 'warning';
                                            if (is_array($ksefStatusTop)) {
                                                $activeTop = (bool)($ksefStatusTop['active'] ?? false);
                                                $stateTop = (string)($ksefStatusTop['state'] ?? '');
                                                if ($activeTop) {
                                                    $permLabel = 'OK';
                                                    $permClass = 'success';
                                                } elseif ($stateTop === 'inactive') {
                                                    $permLabel = 'brak';
                                                    $permClass = 'danger';
                                                }
                                            }
                                        ?>
                                        <?php if (($currentRole ?? '') !== 'client'): /* banner KSeF nie dotyczy klientów portalu */ ?>
                                        <?php if ($ksefModeEnabled): ?>
                                        <div class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="fw-semibold">Tryb KSeF:</span>
                                                <span class="badge bg-success">WŁ.</span>
                                                <span class="fw-semibold ms-2">Uprawnienia KSeF:</span>
                                                <span id="ksef-perm-badge" class="badge bg-<?= h($permClass) ?>"><?= h($permLabel) ?></span>
                                            </div>
                                            <a class="btn btn-sm btn-outline-dark" href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Companies', 'action' => 'edit']) ?>">Ustawienia firmy</a>
                                        </div>
                                        <?php $draftInvoicesCount = (int)($draftInvoicesCount ?? 0); ?>
                                        <?php if ($draftInvoicesCount > 0): ?>
                                            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
                                                <div>
                                                    Masz <strong><?= $draftInvoicesCount ?></strong> roboczych faktur niewysłanych do KSeF.
                                                </div>
                                                <a class="btn btn-sm btn-outline-warning" href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Invoices', 'action' => 'drafts']) ?>">Przejdź do roboczych</a>
                                            </div>
                                        <?php endif; // draftInvoicesCount ?>
                                        <?php else: ?>
                                        <div class="alert alert-secondary d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="fw-semibold">Tryb KSeF:</span>
                                                <span class="badge bg-danger">WYŁ.</span>
                                                <span class="small text-muted ms-2">Faktury nie są wysyłane do KSeF. Aby włączyć, przejdź do ustawień firmy.</span>
                                            </div>
                                            <a class="btn btn-sm btn-outline-dark" href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Companies', 'action' => 'edit']) ?>">Ustawienia firmy</a>
                                        </div>
                                        <?php endif; // ksefModeEnabled ?>
                                        <?php endif; // !client ?>
                                        <?php if ($isDemo): ?>
                                            <div class="alert alert-info d-flex align-items-start" role="alert">
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold">Wersja demo</div>
                                                    <div class="small">Dostępne jest wyłącznie wystawianie dokumentu: Faktura VAT.</div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                    <?php if (($currentRole ?? '') !== 'client'): /* banner weryfikacji nie dotyczy klientów portalu */ ?>
                    <div id="verification-banner" class="alert alert-danger alert-dismissible fade show shadow-sm pe-5" role="alert" style="display:none!important">
                        <button type="button" class="btn-close" id="verification-banner-close" aria-label="Zamknij"><i class="ri-close-line"></i></button>
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0"></i>
                            <div>
                                <strong>Prosimy o weryfikację danych!</strong><br>
                                Sprawdź i uzupełnij: <strong>Rachunek bankowy</strong>, <strong>Serie numeracji</strong> oraz <strong>Dane firmy</strong>.
                                Dołożyliśmy wszelkich starań, aby dane zostały zaimportowane poprawnie &mdash; jednak jeśli były niepełne lub nie spełniały wymogów systemu MF / KSeF, prosimy o ich ponowne wprowadzenie.
                                <span class="d-flex gap-2 mt-2 flex-wrap">
                                    <a class="btn btn-sm btn-outline-danger" href="/firma/edycja">Przejdź do ustawień firmy</a>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <script>
                    (function () {
                        var key = 'verificationBannerDismissed';
                        var el = document.getElementById('verification-banner');
                        if (el && !localStorage.getItem(key)) {
                            el.style.removeProperty('display');
                        }
                        var btn = document.getElementById('verification-banner-close');
                        if (btn) {
                            btn.addEventListener('click', function () {
                                localStorage.setItem(key, '1');
                                el.style.setProperty('display', 'none', 'important');
                            });
                        }
                    })();
                    </script>
                    <?= $this->Flash->render() ?>
                    <?= $this->fetch('content') ?>
                </div>
            </div>
            <!--APP-CONTENT CLOSE-->

        
        <!-- Footer Start -->
        <footer class="footer mt-auto py-3 bg-white text-center">
            <div class="container">
                
                <span class="text-muted"> Copyright © <span id="year"></span> <a href="https://booklio.pl/" target="_blank">
                        <span class="fw-medium text-primary">Booklio.pl</span>
                    </a> All
                    rights
                    reserved
                    <?php if ($appVersion !== ''): ?>
                      <span class="mx-2">•</span>
                      Wersja: <?= h($appVersion) ?>
                    <?php endif; ?>
                                        <?= $this->cell('KsefStatus') ?>
                                                                                <?= $this->cell('KsefAuthContext') ?>
                </span>
            </div>
        </footer>
        <!-- Footer End -->
        <div class="modal fade" id="header-responsive-search" tabindex="-1" aria-labelledby="header-responsive-search" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="input-group">
                            <input type="text" class="form-control border-end-0" placeholder="Search Anything ..."
                                aria-label="Search Anything ..." aria-describedby="button-addon2">
                            <button class="btn btn-primary" type="button"
                                id="button-addon2"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    
    <!-- Scroll To Top -->
    <div class="scrollToTop">
        <span class="arrow lh-1"><i class="ti ti-arrow-big-up fs-16"></i></span>
    </div>
    <div id="responsive-overlay"></div>
    <!-- Scroll To Top -->

    <?= $this->fetch('ksefModals') ?>
    
    <!-- Modal: Instrukcja KSeF (YouTube) -->
    <div class="modal fade" id="ksefVideoModal" tabindex="-1" aria-labelledby="ksefVideoModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title" id="ksefVideoModalLabel">Instrukcja integracji KSeF</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0">
            <div class="ratio ratio-16x9">
              <iframe id="ksefVideoFrame" src="" title="Instrukcja KSeF" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php
        // ---------- JS na dole (lepsza wydajność) ----------
        echo $this->Html->script([
            '/assets/js/defaultmenu.min.js',
            '/assets/libs/node-waves/waves.min.js',
            '/assets/js/sticky.js',
            '/assets/libs/simplebar/simplebar.min.js',
            '/assets/js/simplebar.js',
            '/assets/libs/@tarekraafat/autocomplete.js/autoComplete.min.js',
            '/assets/libs/@simonwep/pickr/pickr.es5.min.js',
            '/assets/libs/flatpickr/flatpickr.min.js',
            '/assets/js/custom-switcher.min.js',
            '/assets/libs/prismjs/prism.js',
            '/assets/js/prism-custom.js',
            '/assets/libs/choices.js/public/assets/scripts/choices.min.js',
            '/assets/js/alerts.js',
            '/assets/js/custom.js',
        ], ['block' => 'scriptBottom']);

        // Skrypty dokładane z widoków/kontrolek:
 
        echo $this->fetch('scriptBottom');

        // Jeśli potrzebujesz jeszcze osobnego bloku:
        echo $this->fetch('postScripts');
        ?>
        <script>
          // Inicjuj tooltips Bootstrap jeśli dostępny, oraz obsłuż modal z wideo
          document.addEventListener('DOMContentLoaded', function() {
            if (window.bootstrap && typeof bootstrap.Tooltip === 'function') {
              document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){
                new bootstrap.Tooltip(el);
              });
            }
            // Obsługa modala z instrukcją YouTube
            var videoModal = document.getElementById('ksefVideoModal');
            var frame = document.getElementById('ksefVideoFrame');
            if (videoModal && window.bootstrap) {
              videoModal.addEventListener('show.bs.modal', function () {
                // TODO: Podmień na właściwy materiał instruktażowy
                var ytUrl = 'https://www.youtube.com/embed/dQw4w9WgXcQ';
                if (frame) frame.src = ytUrl;
              });
              videoModal.addEventListener('hidden.bs.modal', function () {
                if (frame) frame.src = '';
              });
            }
          });
        </script>

                <?php
                    $identityLayout = $this->request->getAttribute('identity');
                    $companyIdLayout = (string)($identityLayout?->get('company_id') ?? '');
                    $ksefAjaxUrl = $this->Url->build(['plugin' => false, 'controller' => 'KsefAuthorizations', 'action' => 'statusAjax']);
                    $ksefClientTtl = (int)(Configure::read('Ksef.statusClientCacheSeconds') ?? 300);
                    if ($ksefClientTtl < 30) { $ksefClientTtl = 30; }
                    if ($ksefClientTtl > 3600) { $ksefClientTtl = 3600; }
                ?>
                <script>
                    (function() {
                        function byId(id) { return document.getElementById(id); }
                        function nowSec() { return Math.floor(Date.now() / 1000); }
                        function pad2(n) { return (n < 10 ? '0' : '') + n; }

                        function formatTs(ts) {
                            try {
                                var d = new Date(ts * 1000);
                                return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()) + ' ' + pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds());
                            } catch (e) {
                                return '';
                            }
                        }

                        function setVisible(el, visible) {
                            if (!el) return;
                            if (visible) {
                                el.classList.remove('d-none');
                            } else {
                                el.classList.add('d-none');
                            }
                        }

                        function setInlineVisible(el, visible) {
                            if (!el) return;
                            el.style.display = visible ? '' : 'none';
                        }

                        function updateTooltip(el, title) {
                            if (!el) return;
                            el.setAttribute('title', title || '');
                            if (window.bootstrap && typeof bootstrap.Tooltip === 'function') {
                                var inst = bootstrap.Tooltip.getInstance(el);
                                if (inst && typeof inst.setContent === 'function') {
                                    inst.setContent({ '.tooltip-inner': title || '' });
                                }
                            }
                        }

                        function applyStatusToOffcanvas(status) {
                            if (!status || typeof status !== 'object') return;
                            var state = 'unknown';
                            if (Object.prototype.hasOwnProperty.call(status, 'active')) {
                                state = status.active ? 'active' : 'inactive';
                            }

                            setVisible(byId('ksef-ajax-unknown'), state === 'unknown');
                            setVisible(byId('ksef-ajax-inactive'), state === 'inactive');
                            setVisible(byId('ksef-ajax-active'), state === 'active');

                            var ts = status.ts ? parseInt(status.ts, 10) : 0;
                            var fullTs = ts > 0 ? formatTs(ts) : '';
                            var lastError = (typeof status.lastError === 'string') ? status.lastError.trim() : '';
                            var env = (typeof status.env === 'string' && status.env) ? status.env : '';

                            // inactive
                            var inactiveErr = byId('ksef-ajax-inactive-error');
                            if (inactiveErr) {
                                inactiveErr.textContent = lastError ? ('Błąd: ' + lastError) : '';
                                setInlineVisible(inactiveErr, !!lastError);
                            }
                            var inactiveTs = byId('ksef-ajax-inactive-ts');
                            if (inactiveTs) {
                                inactiveTs.innerHTML = fullTs ? ('Ostatnia próba: <strong>' + fullTs + '</strong>') : '';
                                setInlineVisible(inactiveTs, !!fullTs);
                            }

                            // active
                            var activeTs = byId('ksef-ajax-active-ts');
                            if (activeTs) {
                                activeTs.innerHTML = fullTs ? ('Ostatnie potwierdzenie: <strong>' + fullTs + '</strong>') : '';
                                setInlineVisible(activeTs, !!fullTs);
                            }
                            var envWrap = byId('ksef-ajax-env');
                            var envVal = byId('ksef-ajax-env-value');
                            if (envVal) envVal.textContent = env ? env.toUpperCase() : '';
                            setInlineVisible(envWrap, !!env);

                            var master = !!status.usingMaster;
                            var masterMode = (typeof status.usingMasterMode === 'string') ? status.usingMasterMode : '';
                            var nip = (typeof status.identifierNip === 'string') ? status.identifierNip : '';
                            var masterEl = byId('ksef-ajax-master');
                            if (masterEl) {
                                setInlineVisible(masterEl, master);
                                var t = 'Używany certyfikat master' + (masterMode ? (' (tryb: ' + masterMode + ')') : '') + (nip ? (' – NIP: ' + nip) : '') + '.';
                                updateTooltip(masterEl, t);
                            }
                        }

                        function setBadgeLoading() {
                            var badge = byId('ksef-perm-badge');
                            if (badge) {
                                badge.className = 'badge bg-secondary';
                                badge.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:.6rem;height:.6rem;border-width:.15em" role="status" aria-hidden="true"></span> sprawdzam…';
                            }
                        }

                        function applyStatusToFooter(status) {
                            if (!status || typeof status !== 'object') return;

                            // Top-bar badge
                            var badge = byId('ksef-perm-badge');
                            if (badge) {
                                if (status.active === true) {
                                    badge.className = 'badge bg-success';
                                    badge.textContent = 'OK';
                                } else {
                                    badge.className = 'badge bg-danger';
                                    badge.textContent = 'brak';
                                }
                            }

                            var ctx = byId('ksef-auth-context');
                            var connSep = byId('ksef-auth-conn-sep');
                            var conn = byId('ksef-auth-conn');
                            if (!conn || !connSep) return;

                            var active = !!status.active;
                            var text = active ? 'InvoiceWrite: OK' : 'InvoiceWrite: brak';
                            conn.textContent = text;
                            conn.classList.remove('text-success', 'text-danger', 'text-muted', 'text-warning');
                            conn.classList.add(active ? 'text-success' : 'text-danger');
                            setInlineVisible(connSep, true);
                            setInlineVisible(conn, true);

                            var msgSep = byId('ksef-auth-invoicewrite-msg-sep');
                            var msg = byId('ksef-auth-invoicewrite-msg');
                            if (msgSep && msg) {
                                setInlineVisible(msgSep, active);
                                setInlineVisible(msg, active);
                            }

                            var lines = [];
                            if (status.ts) {
                                var ts = parseInt(status.ts, 10) || 0;
                                if (ts > 0) lines.push('Ostatnia diagnoza: ' + formatTs(ts));
                            }
                            if (!active && typeof status.lastError === 'string' && status.lastError.trim()) {
                                lines.push('Błąd: ' + status.lastError.trim());
                            }

                            if (ctx && lines.length) {
                                var base = ctx.getAttribute('title') || '';
                                // Usuń poprzedni blok (heurystycznie po "Ostatnia diagnoza")
                                var cut = base.split('\n\nOstatnia diagnoza:')[0];
                                var merged = (cut ? cut.trim() : '');
                                var extra = lines.join('\n');
                                ctx.setAttribute('title', merged ? (merged + '\n\n' + extra) : extra);
                            }
                        }

                        var baseUrl = <?= json_encode($ksefAjaxUrl, JSON_UNESCAPED_SLASHES) ?>;
                        var companyId = <?= json_encode($companyIdLayout, JSON_UNESCAPED_SLASHES) ?>;
                        var clientTtl = <?= (int)$ksefClientTtl ?>;

                        function getEnv() {
                            var ctx = byId('ksef-auth-context');
                            var env = ctx ? (ctx.getAttribute('data-ksef-env') || '') : '';
                            if (!env) {
                                env = <?= json_encode((string)($envSide ?: 'prod'), JSON_UNESCAPED_SLASHES) ?>;
                            }
                            env = (env === 'prod') ? 'prod' : 'test';
                            return env;
                        }

                        function cacheKey(env) {
                            return 'ksef:status:InvoiceWrite:' + (companyId || '-') + ':' + env;
                        }

                        function readCached(env) {
                            try {
                                var raw = localStorage.getItem(cacheKey(env));
                                if (!raw) return null;
                                var data = JSON.parse(raw);
                                if (!data || typeof data !== 'object') return null;
                                if (!data.savedAt || !data.status) return null;
                                if ((nowSec() - parseInt(data.savedAt, 10)) > clientTtl) return null;
                                return data.status;
                            } catch (e) {
                                return null;
                            }
                        }

                        function writeCached(env, status) {
                            try {
                                localStorage.setItem(cacheKey(env), JSON.stringify({ savedAt: nowSec(), status: status }));
                            } catch (e) {
                                // ignore
                            }
                        }

                        var inFlight = false;
                        function setButtonsBusy(busy) {
                            document.querySelectorAll('[data-ksef-invoicewrite-refresh]').forEach(function(btn) {
                                btn.disabled = !!busy;
                            });
                        }

                        function refresh(force) {
                            var env = getEnv();

                            // Cache (client): localStorage z TTL.
                            // Cache (server): statusAjax bez `force=1` może zwrócić wynik z sesji.
                            if (!force) {
                                var cached = readCached(env);
                                if (cached) {
                                    applyStatusToOffcanvas(cached);
                                    applyStatusToFooter(cached);
                                    return Promise.resolve({ cached: true, status: cached });
                                }
                            }

                            if (inFlight) return Promise.resolve({ inFlight: true });
                            inFlight = true;
                            setButtonsBusy(true);
                            setBadgeLoading();

                            var url = baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + 'env=' + encodeURIComponent(env);
                            if (force) url += '&force=1';

                            return fetch(url, { credentials: 'same-origin' })
                                .then(function(r) { return r.json(); })
                                .then(function(json) {
                                    if (json && json.status) {
                                        writeCached(env, json.status);
                                        applyStatusToOffcanvas(json.status);
                                        applyStatusToFooter(json.status);
                                    }
                                    return json;
                                })
                                .catch(function() {
                                    return null;
                                })
                                .finally(function() {
                                    inFlight = false;
                                    setButtonsBusy(false);
                                });
                        }

                        document.addEventListener('DOMContentLoaded', function() {
                            if (!byId('ksef-auth-context')) {
                                return;
                            }
                            // Always AJAX + cache: pokaż natychmiast z localStorage, a w razie potrzeby pobierz.
                            refresh(false);

                            // Manual refresh
                            document.querySelectorAll('[data-ksef-invoicewrite-refresh]').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    refresh(true);
                                });
                            });

                            // Przy otwarciu offcanvas – odśwież (z cache/localStorage, a nie zawsze z serwera)
                            var offcanvasEl = document.getElementById('switcher-canvas');
                            if (offcanvasEl) {
                                offcanvasEl.addEventListener('shown.bs.offcanvas', function() {
                                    refresh(false);
                                });
                            }
                        });
                    })();
                </script>

    <!-- Toast container: lewy dolny róg -->
    <div class="position-fixed bottom-0 start-0 p-3" style="z-index:11000" id="f24-toast-container"></div>
    <script>
    window.showToast = function(message, type, delay) {
        type = type || 'success';
        delay = delay || 4000;
        var id = 'toast-' + Date.now();
        var icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
        var icon = icons[type] || icons.info;
        var html = '<div id="' + id + '" class="toast align-items-center text-bg-' + type + ' border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">'
            + '<div class="d-flex">'
            + '<div class="toast-body d-flex align-items-center gap-2">'
            + '<i class="bi ' + icon + ' flex-shrink-0"></i>'
            + '<span>' + message + '</span>'
            + '</div>'
            + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Zamknij"></button>'
            + '</div>'
            + '</div>';
        var container = document.getElementById('f24-toast-container');
        if (!container) return;
        container.insertAdjacentHTML('beforeend', html);
        var el = document.getElementById(id);
        if (el && window.bootstrap && bootstrap.Toast) {
            var t = new bootstrap.Toast(el, { delay: delay });
            t.show();
            el.addEventListener('hidden.bs.toast', function() { el.remove(); });
        }
    };
    </script>

</body>
</html>
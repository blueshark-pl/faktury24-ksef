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
            if (is_array($status)) {
                $active = (bool)($status['active'] ?? false);
                $envSide = $status['env'] ?? null;
                $lastError = $status['lastError'] ?? null;
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
              <?php if ($state === 'unknown'): ?>
              <div class="rounded-3 border p-3 mb-3 border-danger">
                <div class="d-flex align-items-start gap-3">
                  <div class="pt-1">
                    <span class="ksef-dot" style="width:12px;height:12px;border-radius:50%;display:inline-block;background: <?= h($dotColor) ?>;"></span>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-1">Połączenie z KSeF: Niezweryfikowano</h6>
                    <p class="mb-2 text-muted">Nie sprawdzono jeszcze połączenia w tej sesji. Skorzystaj z krótkiej instrukcji wideo, aby poprawnie przygotować integrację i pliki certyfikatu.</p>
                    <div class="d-flex flex-wrap gap-2">
                      <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ksefVideoModal">
                        Instrukcja wideo (YouTube)
                      </button>
                      <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => 'test']]) ?>"
                         class="btn btn-outline-secondary btn-sm">
                        Podgląd odebranych
                      </a>
                    </div>
                  </div>
                </div>
              </div>
              <?php endif; ?>
              <?php if ($state === 'inactive'): ?>
              <div class="rounded-3 p-3 mb-3">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <span class="ksef-dot" style="width:12px;height:12px;border-radius:50%;display:inline-block;background: <?= h($dotColor) ?>;"></span>
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-danger fw-semibold">Niedostępne</span>
                    <?php if (!empty($lastError) && is_string($lastError)): ?>
                      <span class="text-muted small">Błąd: <?= h($lastError) ?></span>
                    <?php endif; ?>
                    <?php if ($fullTimeStr): ?>
                      <span class="text-muted small">Ostatnia próba: <strong><?= h($fullTimeStr) ?></strong></span>
                    <?php endif; ?>
                  </div>
                  <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ksefVideoModal">Instrukcja wideo</button>
                    <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => 'test']]) ?>" class="btn btn-outline-secondary btn-sm">Podgląd odebranych (test)</a>
                  </div>
                </div>
              </div>
              <?php endif; ?>
              <?php if ($state === 'active'): ?>
              <div class="rounded-3 p-3 mb-3">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                  <span class="ksef-dot pulse" style="width:12px;height:12px;border-radius:50%;display:inline-block;background: <?= h($dotColor) ?>;"></span>
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-success fw-semibold">Aktywne</span>
                    <?php if ($envSide): ?>
                      <span class="text-muted small">Środowisko: <?= h(strtoupper((string)$envSide)) ?></span>
                    <?php endif; ?>
                    <?php if ($fullTimeStr): ?>
                      <span class="text-muted small">Ostatnie potwierdzenie: <strong><?= h($fullTimeStr) ?></strong></span>
                    <?php endif; ?>
                    <?php if ($usingMaster): ?>
                      <span class="text-warning small" data-bs-toggle="tooltip" data-bs-placement="top" title="Używany certyfikat master (tryb: <?= h($usingMasterMode ?: 'nieznany') ?>)<?= $identifierNip ? (' – NIP: ' . h($identifierNip)) : '' ?>.">MASTER</span>
                    <?php endif; ?>
                  </div>
                  <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
                    <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => $envSide ?: 'test']]) ?>" class="btn btn-outline-success btn-sm">Odebrane dokumenty</a>
                    <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'KsefAuthorizations', 'action' => 'status', '?' => ['env' => $envSide ?: 'test']]) ?>" class="btn btn-outline-secondary btn-sm">Odśwież status</a>
                  </div>
                </div>
              </div>
              <?php endif; ?>
              
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
                                    <img src="../assets/images/brand-logos/desktop-logo.png" alt="logo" class="desktop-logo">
                                    <img src="../assets/images/brand-logos/toggle-logo.png" alt="logo" class="toggle-logo">
                                        <?php if (!$isDemo): ?>
                                          <li class="slide">
                                              <?= $this->Html->link(
                                                  'Faktura bez VAT',
                                                  ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'novat']],
                                                  ['class' => 'side-menu__item']
                                              ) ?>
                                          </li>
                                          <li class="slide">
                                              <?= $this->Html->link(
                                                  'Proforma / Oferta',
                                                  ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'proforma']],
                                                  ['class' => 'side-menu__item']
                                              ) ?>
                                          </li>
                                          <li class="slide">
                                              <?= $this->Html->link(
                                                  'Faktura walutowa',
                                                  ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'currency']],
                                                  ['class' => 'side-menu__item']
                                              ) ?>
                                          </li>
                                          <li class="slide">
                                              <?= $this->Html->link(
                                                  'Faktura zaliczkowa',
                                                  ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'advance']],
                                                  ['class' => 'side-menu__item']
                                              ) ?>
                                          </li>
                                          <li class="slide">
                                              <?= $this->Html->link(
                                                  'Faktura marża',
                                                  ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'margin']],
                                                  ['class' => 'side-menu__item']
                                              ) ?>
                                          </li>
                                        <?php else: ?>
                                          <li class="slide">
                                            <span class="side-menu__item text-muted small">Wersja demo: tylko Faktura VAT</span>
                                          </li>
                                        <?php endif; ?>
                                        <!-- <li class="slide">
                                              <?= $this->Html->link(
                                                  'Faktura korygująca',
                                                  ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'correction']],
                                                  ['class' => 'side-menu__item']
                                              ) ?>
                                          </li> -->
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
                          <a href="javascript:void(0);" class="header-link switcher-icon" data-bs-toggle="offcanvas"
                            data-bs-target="#switcher-canvas">
                            <img src="/img/ksef-post.jpg" alt="KSeF" class="rounded"
                                 style="<?= h($ksefImgStyle) ?>"
                                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                                 title="<?= h($ksefHeaderTooltip) ?>" />
                          </a>
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
                        <img src="/img/logo-faktury24.png" alt="logo" class="desktop-logo">
                        <img src="../assets/images/brand-logos/toggle-dark.png" alt="logo" class="toggle-dark">
                        <img src="../assets/images/brand-logos/desktop-dark.png" alt="logo" class="desktop-dark">
                        <img src="../assets/images/brand-logos/toggle-logo.png" alt="logo" class="toggle-logo">
                    </a>
                </div>
                <!-- End::main-sidebar-header -->

                <!-- Start::main-sidebar -->
                                                <div class="main-sidebar " id="sidebar-scroll">

                    <!-- Start::nav -->
                    <nav class="main-menu-container nav nav-pills flex-column sub-open">
                        <div class="slide-left" id="slide-left">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"> <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path> </svg>
                        </div>
                        <ul class="main-menu">
                            <!-- Start::slide__category -->
                            <li class="slide__category"><span class="category-name">Faktury24</span></li>
                            <!-- End::slide__category -->
                            <!-- Otrzymane -->
                            <li class="slide">
                                <?= $this->Html->link(
                                    '<svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><path d="M32,72H224V184a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><polyline points="32 72 128 136 224 72" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg>
                                    <span class="side-menu__label">Otrzymane</span>',
                                    ['plugin' => false, 'controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => 'test']],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li>
                            <!-- Fakturowanie -->
                            <li class="slide has-sub">
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
                                        ['class' => 'side-menu__item']
                                ) ?>
                                </li>

                                
                                <!-- Quick create submenu for specific invoice types -->
                                <li class="slide has-sub">
                                    <a href="javascript:void(0);" class="side-menu__item">
                                        Wystaw fakturę
                                        <i class="ri-arrow-right-s-line side-menu__angle"></i>
                                    </a>
                                    <ul class="slide-menu child2">
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura VAT',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'vat']],
                                                ['class' => 'side-menu__item']
                                            ) ?>
                                        </li>
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura bez VAT',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'novat']],
                                                ['class' => 'side-menu__item']
                                            ) ?>
                                        </li>
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Proforma / Oferta',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'proforma']],
                                                ['class' => 'side-menu__item']
                                            ) ?>
                                        </li>
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura walutowa',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'currency']],
                                                ['class' => 'side-menu__item']
                                            ) ?>
                                        </li>
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura zaliczkowa',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'advance']],
                                                ['class' => 'side-menu__item']
                                            ) ?>
                                        </li>
                                        <!-- <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura korygująca',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'correction']],
                                                ['class' => 'side-menu__item']
                                            ) ?>
                                        </li> -->
                                        <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura marża',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'margin']],
                                                ['class' => 'side-menu__item']
                                            ) ?>
                                        </li>
                                        <!-- <li class="slide">
                                            <?= $this->Html->link(
                                                'Faktura OSS',
                                                ['plugin' => false, 'controller' => 'Invoices', 'action' => 'add', '?' => ['type' => 'oss']],
                                                ['class' => 'side-menu__item']
                                            ) ?>
                                        </li> -->
                                    </ul>
                                </li>
                                <li class="slide">
                                    <?= $this->Html->link(
                                            'Słownik Walutowy NBP',
                                            ['plugin' => false, 'controller' => 'Nbp', 'action' => 'dictionary'],
                                            ['class' => 'side-menu__item']
                                    ) ?>
                                </li>
                            </ul>
                            </li>

                            <!-- Kontrahenci -->
                            <li class="slide has-sub">
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
                                            ['class' => 'side-menu__item']
                                    ) ?>
                                </li>
                            </ul>
                            </li>

                            <!-- Towary i usługi -->
                            <li class="slide has-sub">
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
                                            ['class' => 'side-menu__item']
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

                            <?php
                            // Sekcja administracyjna – widoczna tylko dla administratorów
                            $identity = $this->request->getAttribute('identity') ?? null;
                            $isAdmin  = (bool)($identity?->get('is_admin') ?? false);
                            $role     = strtolower((string)($identity?->get('role') ?? ''));
                            if ($isAdmin || $role === 'admin'):
                            ?>
                            <!-- Administracja -->
                            <!-- <li class="slide__category"><span class="category-name">Administracja</span></li> -->
                            <!-- <li class="slide">
                                <?= $this->Html->link(
                                    '<svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><path d="M40,64H216" stroke="currentColor" fill="none" stroke-width="16"/><path d="M40,128H216" stroke="currentColor" fill="none" stroke-width="16"/><path d="M40,192H216" stroke="currentColor" fill="none" stroke-width="16"/></svg>
                                    <span class="side-menu__label">Kategorie kosztów</span>',
                                    ['plugin' => false, 'controller' => 'CostCategories', 'action' => 'index'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li> -->
                            <?php endif; ?>

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
                                        <?php if ($isDemo): ?>
                                            <div class="alert alert-info d-flex align-items-start" role="alert">
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold">Wersja demo</div>
                                                    <div class="small">Dostępne jest wyłącznie wystawianie dokumentu: Faktura VAT.</div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                    <?= $this->Flash->render() ?>
                    <?= $this->fetch('content') ?>
                </div>
            </div>
            <!--APP-CONTENT CLOSE-->

        
        <!-- Footer Start -->
        <footer class="footer mt-auto py-3 bg-white text-center">
            <div class="container">
                
                <span class="text-muted"> Copyright © <span id="year"></span> <a href="https://partnersc.com/" target="_blank">
                        <span class="fw-medium text-primary">Partner S.C.</span>
                    </a> All
                    rights
                    reserved
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
    
</body>
</html>
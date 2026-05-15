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
    ?>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="faktury24" />
    <link rel="manifest" href="/site.webmanifest" />
    <?php

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
      /* Override koloru primary (94d437 = 148, 212, 55) */
      :root { --primary-rgb: 148, 212, 55; }
      .btn-primary,
      .btn-primary:focus,
      .btn-primary:active {
        background-color: #94d437 !important;
        border-color: #94d437 !important;
        color: #fff !important;
      }
      .btn-primary:hover {
        background-color: #84c02e !important;
        border-color: #84c02e !important;
        color: #fff !important;
      }
      .btn-primary:disabled,
      .btn-primary.disabled {
        background-color: #94d437 !important;
        border-color: #94d437 !important;
        opacity: .65;
      }

      /* Powiększone logo w sidebarze (desktop) */
      .app-sidebar .main-sidebar-header .header-logo .desktop-logo,
      .app-sidebar .main-sidebar-header .header-logo .desktop-dark {
        height: 2.1rem;
        line-height: 2.1rem;
      }

      /* Show hand cursor on tab-style nav items */
      .nav-pills.tab-style-7 .nav-link { cursor: pointer; }
      .nav-pills.tab-style-7 .nav-item { cursor: pointer; }

      /* Zawsze widoczny scrollbar poziomy w table-responsive */
      .table-responsive {
        overflow-x: scroll !important;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: #a7cf4c #e8f5d0;
      }
      .table-responsive::-webkit-scrollbar {
        height: 10px;
      }
      .table-responsive::-webkit-scrollbar-track {
        background: #e8f5d0 !important;
        border-radius: 5px !important;
      }
      .table-responsive::-webkit-scrollbar-thumb {
        background-color: #a7cf4c !important;
        border-radius: 5px !important;
        min-width: 40px !important;
        border: 2px solid #e8f5d0 !important;
      }
      .table-responsive::-webkit-scrollbar-thumb:hover {
        background-color: #8ab83a !important;
      }
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

                    /* === Docked KSeF status pill (navbar) === */
                    #ksef-navbar-slot { opacity: 0; visibility: hidden; transform: translateY(-4px); transition: opacity .4s ease, transform .4s ease; pointer-events: none; }
                    #ksef-navbar-slot.is-visible { opacity: 1; visibility: visible; transform: none; pointer-events: auto; }
                    .ksef-pill {
                        background: #fff;
                        border: 1px solid #e5e7eb;
                        color: #475569;
                        font-size: .78rem;
                        line-height: 1;
                        transition: background .2s ease, border-color .2s ease, color .2s ease, box-shadow .2s ease;
                    }
                    .ksef-pill:hover { background: #f8fafc; border-color: #cbd5e1; color:#1e293b; box-shadow: 0 1px 3px rgba(15,23,42,.06); }
                    .ksef-pill-icon { font-size: .95rem; color:#64748b; }
                    .ksef-pill-text { letter-spacing:.2px; }
                    .ksef-pill-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 0 rgba(34,197,94,0.55); animation: ksefPulse 2s ease-in-out infinite; }
                    .ksef-pill[data-ksef-perm="warning"] .ksef-pill-dot { background: #f59f00; }
                    .ksef-pill[data-ksef-perm="danger"]  .ksef-pill-dot { background: #dc3545; box-shadow:none; animation:none; }
                    .ksef-pill[data-ksef-mode="off"] .ksef-pill-icon { color:#94a3b8; }

                    /* element animowany w trakcie docku */
                    #ksef-status-alert.is-flying {
                        position: fixed !important;
                        margin: 0 !important;
                        z-index: 1090;
                        box-shadow: 0 18px 40px -10px rgba(15, 23, 42, .35);
                        transition:
                            top .85s cubic-bezier(.65,.05,.2,1),
                            left .85s cubic-bezier(.65,.05,.2,1),
                            width .85s cubic-bezier(.65,.05,.2,1),
                            height .85s cubic-bezier(.65,.05,.2,1),
                            padding .85s ease,
                            border-radius .85s ease,
                            opacity .55s ease .35s,
                            transform .85s cubic-bezier(.34,1.56,.64,1);
                        overflow: hidden;
                        will-change: top,left,width,height,transform,opacity;
                    }
                    #ksef-status-alert.is-flying.is-shrinking {
                        padding: 0 !important;
                        border-radius: 999px !important;
                        opacity: 0;
                        transform: scale(.4);
                    }
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
                                    <img src="/img/faktury24_logo.png" alt="logo" class="desktop-logo">
                                    <img src="/img/faktury24_logo.png" alt="logo" class="toggle-logo">
                                    <img src="/img/faktury24_logo.png" alt="logo" class="desktop-dark">
                                    <img src="/img/faktury24_logo.png" alt="logo" class="toggle-dark">
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

                        <!-- Start::header-element: global search -->
                        <div class="header-element header-search-wrap" style="position:relative">
                            <div class="input-group" style="min-width:280px;max-width:420px">
                                <span class="input-group-text bg-white border-end-0" style="border-radius:8px 0 0 8px">
                                    <i class="ri-search-2-line text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="global-search-input" placeholder="Szukaj faktur, kontrahentów, towarów…" autocomplete="off" style="border-radius:0 8px 8px 0;font-size:.85rem">
                            </div>
                            <div id="global-search-results" class="shadow-lg border bg-white rounded" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:6px;max-height:420px;overflow-y:auto;z-index:1050;min-width:380px"></div>
                        </div>
                        <!-- End::header-element: global search -->

                    </div>
                    <!-- End::header-content-left -->

                    <!-- Start::header-content-right -->
                    <ul class="header-content-right">

                        <?php
                            // Czy aktualnie zalogowana tożsamość ma uprawnienia admina?
                            $idHdr = $this->request->getAttribute('identity') ?? null;
                            $isAdminHdr = (bool)($idHdr?->get('is_admin') ?? false)
                                || strtolower((string)($idHdr?->get('role') ?? '')) === 'admin';
                            $csrfHdr = $this->getRequest()->getAttribute('csrfToken');
                            // Ostatnio przeglądane (z bazy danych, per user)
                            $recentInvoices = [];
                            $recentContractors = [];
                            $userIdHdr = (string)($idHdr?->getIdentifier() ?? '');
                            if ($userIdHdr !== '') {
                                try {
                                    /** @var \App\Model\Table\RecentlyViewedTable $RecentlyViewedTbl */
                                    $RecentlyViewedTbl = $this->fetchTable('RecentlyViewed');
                                    $recentInvoices    = $RecentlyViewedTbl->listForUser($userIdHdr, 'invoices', 5);
                                    $recentContractors = $RecentlyViewedTbl->listForUser($userIdHdr, 'contractors', 5);
                                } catch (\Throwable) {}
                            }
                            $hasRecent = !empty($recentInvoices) || !empty($recentContractors);
                        ?>

                        <?php
                            // Slot na zadokowany status KSeF — renderowany zawsze, widoczność zarządzana z JS (cookie + animacja docku)
                            $ksefSlotMode = !empty($ksefModeEnabled) ? 'on' : 'off';
                            $ksefSlotPerm = 'success';
                            $ksefSlotLabel = 'OK';
                            $ksefSlotStatus = $this->getRequest()->getSession()->read('Ksef.status');
                            if ($ksefSlotMode === 'on' && is_array($ksefSlotStatus)) {
                                $activeSlot = (bool)($ksefSlotStatus['active'] ?? false);
                                $stateSlot = (string)($ksefSlotStatus['state'] ?? '');
                                if ($activeSlot) { $ksefSlotPerm = 'success'; $ksefSlotLabel = 'OK'; }
                                elseif ($stateSlot === 'inactive') { $ksefSlotPerm = 'danger'; $ksefSlotLabel = 'brak'; }
                                else { $ksefSlotPerm = 'warning'; $ksefSlotLabel = 'wymagane'; }
                            } elseif ($ksefSlotMode === 'off') {
                                $ksefSlotPerm = 'danger';
                                $ksefSlotLabel = 'WYŁ.';
                            }
                            $ksefPillTitle = $ksefSlotMode === 'on'
                                ? 'KSeF włączony — uprawnienia: ' . $ksefSlotLabel
                                : 'KSeF wyłączony — faktury nie są wysyłane do KSeF';
                        ?>
                        <?php $ksefAlertDockedHdr = (($_COOKIE['ksef_alert_docked'] ?? '') === '1'); ?>
                        <!-- Start::header-element: docked KSeF status -->
                        <li class="header-element header-ksef me-2 <?= $ksefAlertDockedHdr ? 'is-visible' : '' ?>"
                            id="ksef-navbar-slot" <?= $ksefAlertDockedHdr ? '' : 'aria-hidden="true"' ?>>
                            <a href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Companies', 'action' => 'edit']) ?>"
                               class="ksef-pill d-inline-flex align-items-center gap-2 px-2 py-1 rounded-pill text-decoration-none"
                               title="<?= h($ksefPillTitle) ?>"
                               data-bs-toggle="tooltip"
                               data-ksef-mode="<?= h($ksefSlotMode) ?>"
                               data-ksef-perm="<?= h($ksefSlotPerm) ?>">
                                <i class="ri-shield-check-line ksef-pill-icon"></i>
                                <span class="ksef-pill-text fw-semibold small">KSeF</span>
                                <span class="ksef-pill-dot" aria-hidden="true"></span>
                            </a>
                        </li>
                        <!-- End::header-element: docked KSeF status -->

                        <!-- Start::header-element: CTA wystaw fakturę -->
                        <li class="header-element header-quick-add dropdown">
                            <a href="javascript:void(0);" class="btn btn-primary btn-sm dropdown-toggle d-inline-flex align-items-center gap-1 fw-semibold" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius:8px;padding:.4rem .8rem;font-size:.85rem">
                                <i class="ri-add-line"></i>
                                <span class="d-none d-md-inline">Wystaw fakturę</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end p-0 shadow" style="min-width:240px">
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="/invoices/add-vat"><i class="ri-file-list-3-line text-primary"></i><span>Faktura VAT</span></a></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="/invoices/add-proforma"><i class="ri-file-text-line text-info"></i><span>Proforma</span></a></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="/invoices/add-advance"><i class="ri-coin-line text-warning"></i><span>Zaliczka</span></a></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="/invoices/add-correction"><i class="ri-edit-2-line text-danger"></i><span>Korekta</span></a></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="/invoices/add-currency"><i class="ri-exchange-dollar-line text-success"></i><span>Walutowa</span></a></li>
                                <li><a class="dropdown-item d-flex align-items-center gap-2 py-2" href="/invoices/add-no-vat"><i class="ri-file-forbid-line text-muted"></i><span>Bez VAT</span></a></li>
                            </ul>
                        </li>
                        <!-- End::header-element: CTA -->

                        <?php if ($hasRecent): ?>
                        <!-- Start::header-element: recently viewed -->
                        <li class="header-element header-recent dropdown">
                            <a href="javascript:void(0);" class="header-link dropdown-toggle no-caret" data-bs-toggle="dropdown" aria-expanded="false" title="Ostatnio przeglądane" aria-label="Ostatnio przeglądane">
                                <i class="ri-history-line header-link-icon" style="font-size:22px"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end p-0 shadow" style="min-width:320px;max-width:380px">
                                <li class="px-3 py-2 border-bottom small text-muted fw-semibold text-uppercase" style="font-size:.65rem;letter-spacing:.5px">
                                    <i class="ri-history-line me-1"></i>Ostatnio przeglądane
                                </li>
                                <?php if (!empty($recentInvoices)): ?>
                                <li class="px-3 py-1 small text-muted fw-semibold bg-light"><i class="ri-file-list-3-line me-1"></i>Faktury</li>
                                <?php foreach ($recentInvoices as $r): ?>
                                <li>
                                    <a class="dropdown-item d-flex align-items-start gap-2 py-2" href="<?= h($r['url']) ?>">
                                        <i class="ri-file-list-3-line text-primary mt-1"></i>
                                        <span class="flex-grow-1 min-width-0">
                                            <span class="fw-medium d-block text-truncate"><?= h($r['label']) ?></span>
                                            <?php if (!empty($r['sub'])): ?><span class="small text-muted d-block text-truncate"><?= h($r['sub']) ?></span><?php endif; ?>
                                        </span>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (!empty($recentContractors)): ?>
                                <li class="px-3 py-1 small text-muted fw-semibold bg-light"><i class="ri-user-3-line me-1"></i>Kontrahenci</li>
                                <?php foreach ($recentContractors as $r): ?>
                                <li>
                                    <a class="dropdown-item d-flex align-items-start gap-2 py-2" href="<?= h($r['url']) ?>">
                                        <i class="ri-user-3-line text-success mt-1"></i>
                                        <span class="flex-grow-1 min-width-0">
                                            <span class="fw-medium d-block text-truncate"><?= h($r['label']) ?></span>
                                            <?php if (!empty($r['sub'])): ?><span class="small text-muted d-block text-truncate"><?= h($r['sub']) ?></span><?php endif; ?>
                                        </span>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <!-- End::header-element: recently viewed -->
                        <?php endif; ?>

                        <!-- Start::header-element: fullscreen -->
                        <li class="header-element header-fullscreen">
                            <a href="javascript:void(0);" class="header-link" onclick="openFullscreen()" title="Pełny ekran" aria-label="Pełny ekran">
                                <svg xmlns="http://www.w3.org/2000/svg" class="full-screen-open header-link-icon" viewBox="0 0 256 256" width="22" height="22">
                                    <rect width="256" height="256" fill="none"></rect>
                                    <polyline points="168 48 208 48 208 88" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <polyline points="88 208 48 208 48 168" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <polyline points="208 168 208 208 168 208" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <polyline points="48 88 48 48 88 48" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                </svg>
                                <svg xmlns="http://www.w3.org/2000/svg" class="full-screen-close header-link-icon d-none" viewBox="0 0 256 256" width="22" height="22">
                                    <rect width="256" height="256" fill="none"></rect>
                                    <polyline points="160 48 208 48 208 96" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <line x1="144" y1="112" x2="208" y2="48" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line>
                                    <polyline points="96 208 48 208 48 160" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <line x1="112" y1="144" x2="48" y2="208" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line>
                                </svg>
                            </a>
                        </li>
                        <!-- End::header-element: fullscreen -->

                        <?php if ($isAdminHdr): ?>
                        <!-- Start::header-element: impersonate (admin only) -->
                        <li class="header-element header-impersonate dropdown">
                            <a href="javascript:void(0);" class="header-link dropdown-toggle no-caret" data-bs-auto-close="outside" data-bs-toggle="dropdown" aria-expanded="false" id="impersonateDropdown" title="Wcielenie w użytkownika" aria-label="Wcielenie w użytkownika">
                                <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" viewBox="0 0 256 256" width="22" height="22">
                                    <rect width="256" height="256" fill="none"></rect>
                                    <circle cx="108" cy="100" r="44" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></circle>
                                    <path d="M28,224c14.78-25.55,46-44,80-44s65.22,18.45,80,44" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></path>
                                    <polyline points="184 16 200 32 184 48" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <polyline points="232 80 216 64 232 48" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <path d="M200,32a32,32,0,0,1,32,32" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></path>
                                    <path d="M216,64a32,32,0,0,1-32-32" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></path>
                                </svg>
                            </a>
                            <ul class="main-header-dropdown dropdown-menu dropdown-menu-end p-0 shadow" aria-labelledby="impersonateDropdown" style="min-width:340px;max-width:420px;">
                                <li class="px-3 py-2 border-bottom">
                                    <div class="small text-muted mb-1 fw-semibold">
                                        <i class="ri-spy-line me-1"></i>Wcielenie w użytkownika
                                    </div>
                                    <input type="text" class="form-control form-control-sm" id="imp-search-input" placeholder="Login, e-mail lub imię…" autocomplete="off">
                                </li>
                                <li>
                                    <div id="imp-search-results" style="max-height:380px;overflow-y:auto">
                                        <div class="p-3 text-muted small text-center">Zacznij wpisywać…</div>
                                    </div>
                                </li>
                            </ul>
                        </li>
                        <!-- End::header-element: impersonate -->
                        <?php endif; ?>

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
                        <img src="/img/faktury24_logo.png" alt="logo" class="desktop-logo">
                        <img src="/img/faktury24_logo.png" alt="logo" class="toggle-dark">
                        <img src="/img/faktury24_logo.png" alt="logo" class="desktop-dark">
                        <img src="/img/faktury24_logo.png" alt="logo" class="toggle-logo">
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
                            <!-- Start::slide__category -->
                            <li class="slide__category"><span class="category-name">Faktury24</span></li>
                            <!-- End::slide__category -->
                            <!-- Otrzymane -->
                            <!-- <li class="slide">
                                <?= $this->Html->link(
                                    '<svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"/><path d="M32,72H224V184a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/><polyline points="32 72 128 136 224 72" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"/></svg>
                                    <span class="side-menu__label">Otrzymane</span>',
                                    ['plugin' => false, 'controller' => 'KsefAuthorizations', 'action' => 'received', '?' => ['env' => 'prod']],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li> -->
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
                            <li class="slide">
                                <a href="javascript:void(0);" class="side-menu__item" data-bs-toggle="modal" data-bs-target="#manualPdfModal">
                                    <i class="ri-book-2-line side-menu__icon"></i>
                                    <span class="side-menu__label">Instrukcja obsługi</span>
                                </a>
                            </li>
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
                <?php
                    // Banner impersonacji — pod navbarem, w obszarze contentu
                    $impOriginalId = $this->getRequest()->getSession()->read('Impersonation.original_user_id');
                    if (!empty($impOriginalId)):
                        $impIdentity = $this->getRequest()->getAttribute('identity');
                        $impFirst = trim((string)($impIdentity?->get('first_name') ?? ''));
                        $impLast  = trim((string)($impIdentity?->get('last_name') ?? ''));
                        $impEmail = (string)($impIdentity?->get('email') ?? '');
                        $impLabel = trim($impFirst . ' ' . $impLast);
                        if ($impLabel === '') { $impLabel = $impEmail; }
                        $csrfImp = $this->getRequest()->getAttribute('csrfToken');
                ?>
                <div id="impersonation-banner" class="alert alert-warning border-0 rounded-0 mb-0 d-flex align-items-center justify-content-center gap-3 py-2"
                     role="alert">
                    <i class="ri-spy-line"></i>
                    <span>Wcieliłeś się w <strong><?= h($impLabel) ?></strong><?php if ($impEmail && $impEmail !== $impLabel): ?> (<?= h($impEmail) ?>)<?php endif; ?>.</span>
                    <form action="/admin/impersonate/stop" method="post" class="m-0">
                        <?php if ($csrfImp): ?><input type="hidden" name="_csrfToken" value="<?= h($csrfImp) ?>"><?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-warning fw-semibold">
                            <i class="ri-logout-box-r-line me-1"></i>Wróć do swojego konta
                        </button>
                    </form>
                </div>
                <?php endif; ?>
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
                                        <?php $ksefAlertDocked = (($_COOKIE['ksef_alert_docked'] ?? '') === '1'); ?>
                                        <?php if (!$ksefAlertDocked): ?>
                                        <?php if ($ksefModeEnabled): ?>
                                        <div id="ksef-status-alert" data-ksef-mode="on" data-ksef-perm="<?= h($permClass) ?>" data-ksef-perm-label="<?= h($permLabel) ?>"
                                             class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
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
                                        <div id="ksef-status-alert" data-ksef-mode="off"
                                             class="alert alert-secondary d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="fw-semibold">Tryb KSeF:</span>
                                                <span class="badge bg-danger">WYŁ.</span>
                                                <span class="small text-muted ms-2">Faktury nie są wysyłane do KSeF. Aby włączyć, przejdź do ustawień firmy.</span>
                                            </div>
                                            <a class="btn btn-sm btn-outline-dark" href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Companies', 'action' => 'edit']) ?>">Ustawienia firmy</a>
                                        </div>
                                        <?php endif; // ksefModeEnabled ?>
                                        <?php endif; // !ksefAlertDocked ?>
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

    <!-- Modal: Instrukcja obsługi (PDF) -->
    <div class="modal fade" id="manualPdfModal" tabindex="-1" aria-labelledby="manualPdfModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
          <div class="modal-header d-flex align-items-center justify-content-between">
            <h6 class="modal-title mb-0" id="manualPdfModalLabel">
              <i class="ti ti-book-2 me-2 text-primary"></i>Instrukcja obsługi
            </h6>
            <div class="d-flex align-items-center gap-3 ms-3">
              <a href="/faktury24_manual.pdf" download class="btn btn-outline-primary btn-sm">
                <i class="ti ti-download me-1"></i>Pobierz PDF
              </a>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
          </div>
          <div class="modal-body p-0">
            <iframe id="manualPdfFrame" src="/faktury24_manual.pdf" width="100%" style="height:78vh;border:0;" title="Instrukcja obsługi"></iframe>
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
            '/assets/libs/prismjs/prism.js',
            '/assets/js/prism-custom.js',
            '/assets/libs/choices.js/public/assets/scripts/choices.min.js',
            '/assets/js/alerts.js',
        ], ['block' => 'scriptBottom']);

        $this->Html->scriptBlock(<<<'JS'
        (function () {
          // Bootstrap tooltips
          document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
          });
          // Bootstrap popovers
          document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
            new bootstrap.Popover(el);
          });
          // Autocomplete (header search) — inicjalizuj tylko gdy pole istnieje
          if (typeof autoComplete === 'function' && document.querySelector('#header-search')) {
            const ac = new autoComplete({
              selector: '#header-search',
              data: { src: [], cache: true },
              resultItem: { highlight: true },
              events: {
                input: {
                  selection: (event) => { ac.input.value = event.detail.selection.value; }
                }
              }
            });
          }
        })();

        // Globalna wyszukiwarka w navbarze
        (function () {
          const input   = document.getElementById('global-search-input');
          const results = document.getElementById('global-search-results');
          if (!input || !results) return;
          let timer = null, lastQ = '', items = [], cursor = -1;

          function esc(s) {
            return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
          }
          function highlight(text, q) {
            if (!q) return esc(text);
            const t = String(text || '');
            const idx = t.toLowerCase().indexOf(q.toLowerCase());
            if (idx < 0) return esc(t);
            return esc(t.slice(0, idx))
              + '<mark style="background:#fff3a0;padding:0">' + esc(t.slice(idx, idx + q.length)) + '</mark>'
              + esc(t.slice(idx + q.length));
          }
          function fmtMoney(n, cur) {
            const num = Number(n || 0);
            return num.toLocaleString('pl-PL', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ' + (cur || 'PLN');
          }
          function hide() { results.style.display = 'none'; items = []; cursor = -1; }
          function show() { results.style.display = 'block'; }

          function render(data, q) {
            const sections = [];
            items = [];

            function pushSection(label, icon, list, renderItem) {
              if (!list || !list.length) return;
              sections.push('<div class="px-3 py-1 small fw-semibold text-uppercase text-muted bg-light border-bottom" style="font-size:.65rem;letter-spacing:.5px"><i class="' + icon + ' me-1"></i>' + esc(label) + '</div>');
              list.forEach(it => {
                items.push(it.url);
                sections.push(renderItem(it));
              });
            }

            pushSection('Faktury', 'ri-file-list-3-line', data.invoices, (it) => (
              '<a href="' + esc(it.url) + '" class="gs-item d-flex align-items-center gap-2 px-3 py-2 text-decoration-none text-dark border-bottom" style="cursor:pointer">'
              + '<div class="bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center border" style="width:32px;height:32px;border-radius:50%;flex-shrink:0"><i class="ri-file-list-3-line"></i></div>'
              + '<div class="flex-grow-1 min-width-0">'
              +   '<div class="fw-semibold small text-truncate">' + highlight(it.fullnumber || '(brak numeru)', q) + (it.contractor ? ' · ' + highlight(it.contractor, q) : '') + '</div>'
              +   '<div class="text-muted text-truncate" style="font-size:.72rem">' + esc(it.date || '') + '</div>'
              + '</div>'
              + '<div class="text-end small text-nowrap" style="font-size:.7rem">' + fmtMoney(it.total, it.currency) + '</div>'
              + '</a>'
            ));

            pushSection('Kontrahenci', 'ri-user-3-line', data.contractors, (it) => (
              '<a href="' + esc(it.url) + '" class="gs-item d-flex align-items-center gap-2 px-3 py-2 text-decoration-none text-dark border-bottom" style="cursor:pointer">'
              + '<div class="bg-success-subtle text-success d-inline-flex align-items-center justify-content-center border" style="width:32px;height:32px;border-radius:50%;flex-shrink:0"><i class="ri-user-3-line"></i></div>'
              + '<div class="flex-grow-1 min-width-0">'
              +   '<div class="fw-semibold small text-truncate">' + highlight(it.name, q) + '</div>'
              +   '<div class="text-muted text-truncate" style="font-size:.72rem">' + (it.nip ? 'NIP ' + esc(it.nip) : '') + (it.city ? (it.nip ? ' · ' : '') + esc(it.city) : '') + '</div>'
              + '</div>'
              + '</a>'
            ));

            pushSection('Towary / usługi', 'ri-shopping-bag-3-line', data.products, (it) => (
              '<a href="' + esc(it.url) + '" class="gs-item d-flex align-items-center gap-2 px-3 py-2 text-decoration-none text-dark border-bottom" style="cursor:pointer">'
              + '<div class="bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center border" style="width:32px;height:32px;border-radius:50%;flex-shrink:0"><i class="ri-shopping-bag-3-line"></i></div>'
              + '<div class="flex-grow-1 min-width-0">'
              +   '<div class="fw-semibold small text-truncate">' + highlight(it.name, q) + '</div>'
              +   (it.code ? '<div class="text-muted text-truncate" style="font-size:.72rem">' + esc(it.code) + '</div>' : '')
              + '</div>'
              + '<div class="text-end small text-nowrap" style="font-size:.7rem">' + fmtMoney(it.price, it.currency) + '</div>'
              + '</a>'
            ));

            if (items.length === 0) {
              results.innerHTML = '<div class="p-3 text-muted small text-center">Brak wyników dla „' + esc(q) + '".</div>';
            } else {
              sections.push('<div class="px-3 py-1 small text-muted text-center border-top" style="font-size:.68rem"><i class="ri-keyboard-line me-1"></i>↑↓ aby nawigować · Enter aby otworzyć · Esc aby zamknąć</div>');
              results.innerHTML = sections.join('');
            }
            show();
            cursor = -1;
          }

          async function doSearch(q) {
            if (q.length < 2) { hide(); return; }
            try {
              const res = await fetch('/search?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
              if (!res.ok) throw new Error('http ' + res.status);
              const data = await res.json();
              render(data, q);
            } catch (e) {
              results.innerHTML = '<div class="p-3 text-danger small text-center">Błąd wyszukiwania.</div>';
              show();
            }
          }

          function markActive() {
            results.querySelectorAll('.gs-item').forEach((el, i) => {
              if (i === cursor) {
                el.style.background = '#f1f5f9';
                el.scrollIntoView({ block: 'nearest' });
              } else {
                el.style.background = '';
              }
            });
          }

          input.addEventListener('input', () => {
            const q = input.value.trim();
            if (q === lastQ) return;
            lastQ = q;
            clearTimeout(timer);
            timer = setTimeout(() => doSearch(q), 200);
          });

          input.addEventListener('keydown', (e) => {
            if (results.style.display === 'none' || !items.length) return;
            if (e.key === 'ArrowDown') {
              e.preventDefault();
              cursor = (cursor + 1) % items.length;
              markActive();
            } else if (e.key === 'ArrowUp') {
              e.preventDefault();
              cursor = (cursor - 1 + items.length) % items.length;
              markActive();
            } else if (e.key === 'Enter') {
              if (cursor >= 0 && items[cursor]) {
                e.preventDefault();
                window.location.href = items[cursor];
              }
            } else if (e.key === 'Escape') {
              hide();
              input.blur();
            }
          });

          document.addEventListener('click', (e) => {
            if (!e.target.closest('#global-search-input') && !e.target.closest('#global-search-results')) {
              hide();
            }
          });
        })();

        // Impersonate (admin) — autocomplete + start
        (function () {
          const input  = document.getElementById('imp-search-input');
          const results= document.getElementById('imp-search-results');
          if (!input || !results) return;
          const csrf = (document.querySelector('meta[name="csrfToken"]') || {}).content || '';
          let timer = null;
          let lastQ = '';

          function render(html) { results.innerHTML = html; }
          function escapeHtml(s) { return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

          async function search(q) {
            if (q.length < 2) { render('<div class="p-3 text-muted small text-center">Zacznij wpisywać…</div>'); return; }
            try {
              const res = await fetch('/admin/impersonate/search?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
              if (!res.ok) throw new Error('http ' + res.status);
              const data = await res.json();
              const items = Array.isArray(data.items) ? data.items : [];
              if (items.length === 0) { render('<div class="p-3 text-muted small text-center">Brak wyników.</div>'); return; }
              const rows = items.map(u => {
                const name    = u.name && u.name.trim() ? u.name : u.email;
                const company = u.company ? '<div class="small text-muted">' + escapeHtml(u.company) + '</div>' : '';
                const badge   = u.is_admin ? ' <span class="badge bg-warning-subtle text-warning ms-1">admin</span>' : '';
                return '<form action="/admin/impersonate/start/' + encodeURIComponent(u.id) + '" method="post" class="m-0">'
                     + (csrf ? '<input type="hidden" name="_csrfToken" value="' + escapeHtml(csrf) + '">' : '')
                     + '<button type="submit" class="dropdown-item d-flex align-items-start gap-2 py-2">'
                     + '<i class="ri-user-line mt-1"></i>'
                     + '<span class="text-start flex-grow-1">'
                     + '<span class="fw-medium">' + escapeHtml(name) + badge + '</span>'
                     + '<div class="small text-muted">' + escapeHtml(u.email) + '</div>'
                     + company
                     + '</span></button></form>';
              }).join('');
              render(rows);
            } catch (e) {
              render('<div class="p-3 text-danger small text-center">Błąd wyszukiwania.</div>');
            }
          }

          input.addEventListener('input', () => {
            const q = input.value.trim();
            if (q === lastQ) return;
            lastQ = q;
            clearTimeout(timer);
            timer = setTimeout(() => search(q), 200);
          });
        })();

        // Fullscreen toggle (wywoływane z onclick="openFullscreen()")
        window.openFullscreen = function () {
          var elem = document.documentElement;
          var open  = document.querySelector('.full-screen-open');
          var close = document.querySelector('.full-screen-close');
          var isFs = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
          if (!isFs) {
            (elem.requestFullscreen || elem.webkitRequestFullscreen || elem.msRequestFullscreen).call(elem);
            if (close) { close.classList.add('d-block'); close.classList.remove('d-none'); }
            if (open)  open.classList.add('d-none');
          } else {
            (document.exitFullscreen || document.webkitExitFullscreen || document.msExitFullscreen).call(document);
            if (close) { close.classList.remove('d-block'); close.classList.add('d-none'); }
            if (open)  { open.classList.remove('d-none'); open.classList.add('d-block'); }
          }
        };
        JS, ['block' => 'scriptBottom']);

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

    <!-- KSeF status — auto-dock do navbar po 5s + zapamiętanie w cookie -->
    <script>
    (function() {
      const COOKIE = 'ksef_alert_docked';
      const cookieGet = (name) => document.cookie.split(';').map(s => s.trim()).find(c => c.startsWith(name + '='))?.split('=')[1];
      const cookieSet = (name, value, days) => {
        const exp = new Date(Date.now() + (days||365) * 86400000).toUTCString();
        document.cookie = name + '=' + value + '; expires=' + exp + '; path=/; SameSite=Lax';
      };
      function showPill(slot) {
        if (!slot) return;
        slot.classList.add('is-visible');
        slot.removeAttribute('aria-hidden');
        // Init tooltip on pill
        const pill = slot.querySelector('[data-bs-toggle="tooltip"]');
        if (pill && window.bootstrap?.Tooltip) {
          try { bootstrap.Tooltip.getOrCreateInstance(pill); } catch {}
        }
      }
      function runDock(alert, slot) {
        // Target & source rects
        slot.style.visibility = 'hidden';
        slot.classList.add('is-visible');         // temporarily to measure
        const toRect = slot.getBoundingClientRect();
        slot.classList.remove('is-visible');
        slot.style.visibility = '';

        const fromRect = alert.getBoundingClientRect();
        if (!fromRect.width || !toRect.width) {
          // fallback — bez animacji
          alert.remove();
          showPill(slot);
          cookieSet(COOKIE, '1');
          return;
        }

        // Faza 1: zamień alert w "fixed" w jego obecnej pozycji
        alert.style.top = fromRect.top + 'px';
        alert.style.left = fromRect.left + 'px';
        alert.style.width = fromRect.width + 'px';
        alert.style.height = fromRect.height + 'px';
        alert.classList.add('is-flying');

        // Force reflow
        // eslint-disable-next-line no-unused-expressions
        alert.offsetHeight;

        // Faza 2: animacja do navbar slot
        alert.style.top = toRect.top + 'px';
        alert.style.left = toRect.left + 'px';
        alert.style.width = Math.max(toRect.width, 40) + 'px';
        alert.style.height = Math.max(toRect.height, 28) + 'px';
        // dodaj shrink po ~250ms żeby zniknięcie tekstu nie było wcześniej niż lot
        setTimeout(() => alert.classList.add('is-shrinking'), 300);

        // Faza 3: po zakończeniu animacji — pokaż prawdziwy pill, usuń alert, zapisz cookie
        setTimeout(() => {
          alert.remove();
          showPill(slot);
          cookieSet(COOKIE, '1');
        }, 950);
      }

      function init() {
        const alert = document.getElementById('ksef-status-alert');
        const slot  = document.getElementById('ksef-navbar-slot');
        if (!slot) return;

        const docked = cookieGet(COOKIE) === '1';
        if (docked) {
          if (alert) alert.remove();
          showPill(slot);
          return;
        }
        if (!alert) {
          // Brak alertu (np. dla widoku, gdzie nie został wyrenderowany) — pokaż pill
          showPill(slot);
          return;
        }
        // Po 5s — animowany dock
        setTimeout(() => runDock(alert, slot), 5000);
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
      } else {
        init();
      }
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

<script>
// Globalny handler: zamknij Bootstrap Popover po kliknięciu poza nim
document.addEventListener('click', function(e) {
  document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function(el) {
    if (el.contains(e.target) || e.target.closest('.popover')) return;
    var p = window.bootstrap && bootstrap.Popover.getInstance(el);
    if (p) p.hide();
  });
});
</script>

</body>
</html>
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
      style="--primary-rgb: 27, 89, 152;"
      data-toggled="close">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= h($title) ?></title>

    <!-- Wczytanie zapisanego trybu motywu PRZED CSS — eliminuje flash jasnego motywu przy dark mode -->
    <script>
    (function () {
        try {
            var mode = localStorage.getItem('themeMode');
            if (mode === 'dark') {
                var h = document.documentElement;
                h.setAttribute('data-theme-mode',   'dark');
                h.setAttribute('data-header-styles','dark');
                h.setAttribute('data-menu-styles',  'dark');
            }
        } catch (e) {}

        // ── Pre-lock: jeśli ktoś F5-uje gdy ekran był zablokowany, blokujemy stronę
        // natychmiast (przed załadowaniem CSS) żeby nie było okna interakcji 2-5s.
        try {
            if (localStorage.getItem('bookliio_locked_at')) {
                document.documentElement.classList.add('sl-active', 'sl-prelocked');
            }
        } catch (e) {}
    })();
    </script>

    <!-- Pre-lock CSS — ukrywa wszystko (zostaje tylko modal) zanim załaduje się styles.css.
         Inline w head = aplikuje się natychmiast po parsowaniu, jeszcze przed renderem body. -->
    <style id="sl-pre-style">
        html.sl-prelocked, html.sl-prelocked body {
            overflow: hidden !important;
            height: 100% !important;
            background: #0f172a !important;
        }
        html.sl-prelocked body { visibility: hidden !important; pointer-events: none !important; }
        /* Override HTML attr [hidden] — wymusza display:none. Pokażmy modal natychmiast. */
        html.sl-prelocked .screen-lock[hidden],
        html.sl-prelocked .screen-lock {
            display: flex !important;
            visibility: visible !important;
            pointer-events: auto !important;
        }
        html.sl-prelocked .screen-lock * { visibility: visible !important; pointer-events: auto !important; }
        /* Stub-overlay (gdyby JS jeszcze nie podstawił modala) */
        html.sl-prelocked::before {
            content: ""; position: fixed; inset: 0; z-index: 99998;
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(8px);
        }
    </style>

    <?php
    // Opis/autor/keywords – możesz nadpisać blokiem 'meta'
    echo $this->fetch('meta');

    // Favicon (Booklio TMS)
    ?>
    <link rel="icon"           type="image/png"     sizes="96x96" href="/favicon-96x96.png">
    <link rel="icon"           type="image/svg+xml"               href="/favicon.svg">
    <link rel="shortcut icon"                                     href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180"                  href="/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-title" content="Booklio TMS">
    <link rel="manifest"                                          href="/site.webmanifest">
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
      /* Show hand cursor on tab-style nav items */
      .nav-pills.tab-style-7 .nav-link { cursor: pointer; }
      .nav-pills.tab-style-7 .nav-item { cursor: pointer; }

      /* Logo w sidebarze — wyższe niż domyślny theme (lepsza czytelność szerokiego logo 1894×585) */
      .app-sidebar .main-sidebar-header .header-logo img { height: 2.5rem !important; }

      /* Tryb ciemny/jasny — pokazuj właściwą ikonę w toggle */
      html:not([data-theme-mode="dark"]) #theme-toggle .dark-mode-show  { display: none !important; }
      html[data-theme-mode="dark"]       #theme-toggle .light-mode-show { display: none !important; }

      /* Dropdown języka — ukryj strzałkę bootstrap (jak w Zynix country-selector) */
      .header-element.country-selector .dropdown-toggle.no-caret::after { display: none !important; }
      .header-element.country-selector .main-header-dropdown { min-width: 180px; }

      /* ── Widget sesji w navbar (countdown + zablokuj) ─────────────────── */
      .session-widget {
          display: inline-flex;
          align-items: center;
          gap: 0;
          padding: .25rem .35rem .25rem .65rem;
          line-height: 1;
          border-radius: 999px;
          background: rgba(var(--primary-rgb), .07);
          border: 1px solid rgba(var(--primary-rgb), .14);
          transition: background .15s, border-color .15s, box-shadow .15s;
      }
      .session-widget:hover {
          background: rgba(var(--primary-rgb), .12);
          border-color: rgba(var(--primary-rgb), .25);
          box-shadow: 0 2px 8px rgba(var(--primary-rgb), .08);
      }
      .session-widget .sw-countdown {
          display: inline-flex;
          align-items: center;
          gap: .4rem;
          color: var(--default-text-color);
          padding-right: .55rem;
          line-height: 1;
      }
      .session-widget .sw-icon {
          font-size: 1rem;
          color: rgb(var(--primary-rgb));
          opacity: .8;
      }
      .session-widget .sw-time {
          font-variant-numeric: tabular-nums;
          font-weight: 600;
          font-size: .85rem;
          letter-spacing: .02em;
          min-width: 38px;
          text-align: right;
      }
      /* Stany kolorystyczne (sterowane przez JS dodający klasy do #lockCountdownBox) */
      #lockCountdownBox.text-warning .sw-icon,
      #lockCountdownBox.text-warning .sw-time { color: #d97706; }
      #lockCountdownBox.text-danger  .sw-icon,
      #lockCountdownBox.text-danger  .sw-time { color: #dc3545; }

      .session-widget .sw-divider {
          width: 1px;
          height: 18px;
          background: rgba(var(--primary-rgb), .25);
      }
      .session-widget .sw-lock-btn {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          width: 24px;
          height: 24px;
          margin-left: .35rem;
          border-radius: 50%;
          color: rgb(var(--primary-rgb));
          font-size: .95rem;
          line-height: 1;
          transition: background .15s, color .15s, transform .1s;
      }
      .session-widget .sw-lock-btn:hover {
          background: rgb(var(--primary-rgb));
          color: #fff;
          transform: scale(1.06);
      }
      .session-widget .sw-lock-btn:active {
          transform: scale(0.95);
      }

      /* Dark mode — lekko inne tło żeby było czytelne */
      [data-theme-mode="dark"] .session-widget {
          background: rgba(var(--primary-rgb), .14);
          border-color: rgba(var(--primary-rgb), .28);
      }
      [data-theme-mode="dark"] .session-widget:hover {
          background: rgba(var(--primary-rgb), .22);
      }
      [data-theme-mode="dark"] .session-widget .sw-divider {
          background: rgba(255, 255, 255, .14);
      }

      /* Schowaj na bardzo wąskich ekranach (poniżej tabletu) */
      @media (max-width: 575.98px) {
          .session-widget { display: none; }
      }

      /* ── Widget opiekuna klienta w sidebarze ─────────────────────────────── */
      /* Aside ma position:fixed → widget absolute przyklejony do dołu.
         main-sidebar ma padding-block-end:5rem co zapewnia, że content nie
         wjeżdża pod widget. */
      .app-sidebar { position: fixed; }  /* gwarantujemy że jest reference dla absolute */
      .sidebar-caretaker {
          position: absolute;
          left: 0;
          right: 0;
          bottom: 0;
          padding: 12px;
          background: var(--menu-bg);
          border-top: 1px solid var(--menu-border-color);
          z-index: 5;
      }
      /* Większy padding-bottom na main-sidebar żeby ostatnie pozycje nie były pod widgetem */
      .main-sidebar.has-caretaker-widget {
          padding-block-end: 13rem !important;
      }
      .sidebar-caretaker__label {
          font-size: 11px;
          letter-spacing: .4px;
          text-transform: uppercase;
          color: rgba(var(--menu-prime-color), .55);
          margin: 0 0 8px;
          padding: 0 4px;
          font-weight: 700;
      }
      .sidebar-caretaker__label i { margin-right: 4px; color: rgb(var(--primary-rgb)); }

      .sidebar-caretaker__card {
          display: flex;
          flex-direction: column;
          align-items: center;
          text-align: center;
          gap: 8px;
          padding: 14px 12px 12px;
          background: rgba(var(--primary-rgb), .06);
          border: 1px solid rgba(var(--primary-rgb), .15);
          border-radius: 14px;
          transition: background .15s, border-color .15s;
      }
      .sidebar-caretaker__card:hover {
          background: rgba(var(--primary-rgb), .10);
          border-color: rgba(var(--primary-rgb), .25);
      }
      .sidebar-caretaker__avatar {
          flex-shrink: 0;
          width: 72px; height: 72px;
          border-radius: 50%;
          object-fit: cover;
          border: 3px solid rgba(var(--primary-rgb), .25);
          box-shadow: 0 6px 18px rgba(15, 23, 42, .12);
      }
      .sidebar-caretaker__avatar--initials {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          background: rgb(var(--primary-rgb));
          color: #fff;
          font-weight: 700;
          font-size: 28px;
          border-color: transparent;
      }
      .sidebar-caretaker__info {
          width: 100%;
          min-width: 0;
      }
      .sidebar-caretaker__name {
          font-weight: 700;
          font-size: 14px;
          color: rgba(var(--menu-prime-color), .94);
          line-height: 1.25;
          margin-bottom: 2px;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
      }
      .sidebar-caretaker__contact {
          display: block;
          font-size: 11.5px;
          color: rgba(var(--menu-prime-color), .65);
          text-decoration: none;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
          margin-top: 2px;
      }
      .sidebar-caretaker__contact i { color: rgb(var(--primary-rgb)); opacity: .8; }
      .sidebar-caretaker__contact:hover { color: rgb(var(--primary-rgb)); text-decoration: underline; }

      .sidebar-caretaker__badge {
          display: inline-flex;
          align-items: center;
          gap: 3px;
          margin-top: 6px;
          padding: 2px 8px;
          font-size: 10.5px;
          font-weight: 600;
          background: rgba(245, 158, 11, .15);
          color: #b45309;
          border-radius: 999px;
      }

      /* Zwijany sidebar: chowamy widget — i tak nie zmieści się */
      [data-toggled="icon-overlay-close"]    .sidebar-caretaker,
      [data-toggled="icon-text-close"]       .sidebar-caretaker,
      [data-toggled="icon-hover-menu-close"] .sidebar-caretaker,
      [data-toggled="close"][data-nav-style="icontext-menu"] .sidebar-caretaker,
      [data-toggled="menu-click-closed"]     .sidebar-caretaker {
          display: none;
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
                                        Booklio TMS ma uprawnienia.
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

                        <?php
                            // ── Język portalu (tylko dla klienta — pracownicy mają PL hardcoded) ──
                            $identityLang = $this->request->getAttribute('identity') ?? null;
                            $roleLang     = strtolower((string)($identityLang?->get('role') ?? ''));
                            $currLang     = $this->request->getSession()->read('Config.locale') === 'en' ? 'en' : 'pl';
                            $langs = [
                                'pl' => ['label' => 'Polski',  'cc' => 'PL', 'flag' => '/assets/images/flags/poland_flag.jpg'],
                                'en' => ['label' => 'English', 'cc' => 'UK', 'flag' => '/assets/images/flags/uk_flag.jpg'],
                            ];
                            $setLocaleUrl = function (string $code) {
                                return \Cake\Routing\Router::url([
                                    'plugin' => false,
                                    'controller' => 'ClientPortal',
                                    'action'     => 'setLocale',
                                    $code,
                                ]);
                            };
                        ?>
                        <?php if ($identityLang): /* Dla każdego zalogowanego — admin, user, client */ ?>
                        <!-- Start::header-element | Wybór języka (Zynix country-selector) -->
                        <li class="header-element country-selector dropdown">
                            <a href="javascript:void(0);" class="header-link dropdown-toggle no-caret"
                               data-bs-auto-close="outside" data-bs-toggle="dropdown"
                               id="languageDropdown" aria-expanded="false"
                               title="<?= __('Język') ?>" aria-label="Toggle language">
                                <!-- Ikona "translate" jak w Zynix -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="header-link-icon" viewBox="0 0 256 256">
                                    <rect width="256" height="256" fill="none"></rect>
                                    <polyline points="240 216 184 104 128 216" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <line x1="144" y1="184" x2="224" y2="184" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line>
                                    <line x1="96" y1="32" x2="96" y2="56" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line>
                                    <line x1="32" y1="56" x2="160" y2="56" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line>
                                    <path d="M128,56a96,96,0,0,1-96,96" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></path>
                                    <path d="M69.47,88A96,96,0,0,0,160,152" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></path>
                                </svg>
                            </a>
                            <ul class="main-header-dropdown dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                                <?php foreach ($langs as $code => $info): ?>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center justify-content-between <?= $code === $currLang ? 'active' : '' ?>"
                                       href="<?= h($setLocaleUrl($code)) ?>">
                                        <div class="d-flex align-items-center">
                                            <span class="avatar avatar-rounded avatar-xs lh-1 me-2">
                                                <img src="<?= h($info['flag']) ?>" alt="<?= h($info['cc']) ?>">
                                            </span>
                                            <?= h($info['label']) ?>
                                        </div>
                                        <span class="text-muted fs-12">(<?= h($info['cc']) ?>)</span>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                        <!-- End::header-element -->
                        <?php endif; ?>

                        <?php if ($identityLang): /* Sekcja sesji — widoczna tylko dla zalogowanego */ ?>
                        <!-- Start::header-element | Widget sesji (countdown + lock) -->
                        <li class="header-element">
                            <div class="session-widget" title="<?= __('Sesja zablokuje się po bezczynności') ?>">
                                <span class="sw-countdown" id="lockCountdownBox">
                                    <i class="ri-time-line sw-icon"></i>
                                    <span id="lockCountdownText" class="sw-time">--:--</span>
                                </span>
                                <span class="sw-divider"></span>
                                <a href="javascript:void(0)" id="lockNowBtn" class="sw-lock-btn"
                                   title="<?= __('Zablokuj teraz') ?>" aria-label="<?= __('Zablokuj teraz') ?>">
                                    <i class="ri-lock-line"></i>
                                </a>
                            </div>
                        </li>
                        <!-- End::header-element -->
                        <?php endif; ?>

                        <!-- Start::header-element | Pełny ekran (Zynix style) -->
                        <li class="header-element header-fullscreen">
                            <a href="javascript:void(0);" class="header-link" id="fullscreenBtn"
                               title="<?= __('Pełny ekran') ?>" aria-label="<?= __('Pełny ekran') ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="full-screen-open header-link-icon" viewBox="0 0 256 256">
                                    <rect width="256" height="256" fill="none"></rect>
                                    <polyline points="168 48 208 48 208 88" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <polyline points="88 208 48 208 48 168" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <polyline points="208 168 208 208 168 208" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <polyline points="48 88 48 48 88 48" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                </svg>
                                <svg xmlns="http://www.w3.org/2000/svg" class="full-screen-close header-link-icon" viewBox="0 0 256 256" style="display:none">
                                    <rect width="256" height="256" fill="none"></rect>
                                    <polyline points="160 48 208 48 208 96" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <line x1="144" y1="112" x2="208" y2="48" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line>
                                    <polyline points="96 208 48 208 48 160" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></polyline>
                                    <line x1="112" y1="144" x2="48" y2="208" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16"></line>
                                </svg>
                            </a>
                        </li>
                        <!-- End::header-element -->

                        <!-- Start::header-element | Tryb ciemny / jasny -->
                        <li class="header-element">
                            <a class="header-link layout-setting" href="javascript:void(0);"
                               id="theme-toggle"
                               title="Przełącz tryb jasny / ciemny" aria-label="Toggle theme mode">
                                <i class="ri-moon-line header-link-icon dark-mode-show"></i>
                                <i class="ri-sun-line header-link-icon light-mode-show"></i>
                            </a>
                        </li>
                        <!-- End::header-element -->

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
                            $nameToShow = ($first !== '' || $last !== '') ? $full : $displayName;
                            // Pobierz avatar zalogowanego usera (jeśli ma)
                            $hdrAvatarUrl = null;
                            try {
                                $r = \Cake\ORM\TableRegistry::getTableLocator()->get('Users')->find()
                                    ->select(['avatar'])
                                    ->where(['id' => (string)$identityHeader?->getIdentifier()])
                                    ->disableHydration()
                                    ->first();
                                if (!empty($r['avatar'])) {
                                    $hdrAvatarUrl = (string)$r['avatar'];
                                    if (str_starts_with($hdrAvatarUrl, '/files/avatars/')) {
                                        $diskPath = WWW_ROOT . ltrim($hdrAvatarUrl, '/');
                                        if (is_file($diskPath)) {
                                            $hdrAvatarUrl .= '?v=' . filemtime($diskPath);
                                        } else {
                                            // Plik pod URL z bazy fizycznie nie istnieje (np. nie zsynchronizowany
                                            // na prod) — pomijamy, żeby browser nie generował 404 + nie zaśmiecał logu.
                                            $hdrAvatarUrl = null;
                                        }
                                    }
                                }
                            } catch (\Throwable) {}
                            ?>
                            <a href="javascript:void(0);" class="header-link dropdown-toggle" id="mainHeaderProfile"
                                data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($hdrAvatarUrl): ?>
                                        <img src="<?= h($hdrAvatarUrl) ?>" alt="" class="rounded-circle hdr-avatar-trigger"
                                             style="width:34px;height:34px;object-fit:cover;border:2px solid rgba(var(--primary-rgb),.25)">
                                    <?php else: ?>
                                        <span class="rounded-circle d-inline-flex align-items-center justify-content-center hdr-avatar-trigger"
                                              style="width:34px;height:34px;background:rgba(var(--primary-rgb),.12);color:rgb(var(--primary-rgb));font-weight:700">
                                            <?= h(strtoupper(mb_substr($nameToShow ?: '?', 0, 1))) ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="d-xl-block d-none lh-1">
                                        <span class="text-muted" style="font-size:.72rem"><?= __('Zalogowany jako') ?></span>
                                        <span class="fw-medium d-block lh-sm"><?= h($nameToShow) ?></span>
                                    </div>
                                </div>
                            </a>
                            <!-- End::header-link|dropdown-toggle -->
                            <ul class="main-header-dropdown dropdown-menu pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end"
                                aria-labelledby="mainHeaderProfile">
                                <li>
                                    <?php
                                        $isAdminHeader = (bool)($identityHeader?->get('is_admin') ?? false);
                                        $roleHeader    = strtolower((string)($identityHeader?->get('role') ?? ''));
                                        $roleLabel = match (true) {
                                            $isAdminHeader || $roleHeader === 'admin' => __('Administrator'),
                                            $roleHeader === 'client'                  => __('Klient'),
                                            $roleHeader === 'user'                    => __('Właściciel'),
                                            $roleHeader === ''                        => __('Użytkownik'),
                                            default                                   => ucfirst($roleHeader),
                                        };
                                    ?>
                                    <div class="py-3 px-3 text-center">
                                        <div class="mb-2">
                                            <?php if ($hdrAvatarUrl): ?>
                                                <img src="<?= h($hdrAvatarUrl) ?>" alt=""
                                                     class="rounded-circle"
                                                     style="width:64px;height:64px;object-fit:cover;border:3px solid rgba(var(--primary-rgb),.25);box-shadow:0 4px 12px rgba(15,23,42,.12)">
                                            <?php else: ?>
                                                <span class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                                      style="width:64px;height:64px;background:rgba(var(--primary-rgb),.12);color:rgb(var(--primary-rgb));font-size:1.6rem;font-weight:700;border:3px solid rgba(var(--primary-rgb),.18)">
                                                    <?= h(strtoupper(mb_substr($nameToShow ?: '?', 0, 1))) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="fw-semibold d-block"><?= h($nameToShow) ?></span>
                                        <span class="d-block fs-12 text-muted"><?= h($roleLabel) ?></span>
                                    </div>
                                </li>
                                <li><a class="dropdown-item d-flex align-items-center" href="<?= $this->Url->build(['plugin' => 'CakeDC/Users', 'controller' => 'Users', 'action' => 'profile']) ?>"><i
                                            class="ti ti-user text-primary me-2 fs-16"></i><?= __('Mój profil') ?></a>
                                </li>
                                <?php if ($roleHeader !== 'client'): /* klient nie ma "Moja firma" — to ustawienia naszej firmy */ ?>
                                <li><a class="dropdown-item d-flex align-items-center" href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Companies', 'action' => 'edit']) ?>"><i
                                            class="ti ti-settings text-info me-2 fs-16"></i><?= __('Moja firma') ?></a>
                                </li>
                                <?php endif; ?>
                                <li class="py-2 px-3"><a class="btn btn-primary btn-sm w-100" href="/logout"><?= __('Wyloguj się') ?></a>
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
                        <!-- Rozwinięty sidebar: szerokie logo 1894×585 — wersja light vs dark -->
                        <img src="/img/logo.png"           alt="Booklio TMS" class="desktop-logo">
                        <img src="/img/logo-white.png"     alt="Booklio TMS" class="desktop-dark">
                        <!-- Zwinięty sidebar: kwadratowa ikona 180×180 -->
                        <img src="/apple-touch-icon.png"   alt="Booklio TMS" class="toggle-logo">
                        <img src="/apple-touch-icon.png"   alt="Booklio TMS" class="toggle-dark">
                    </a>
                </div>
                <!-- End::main-sidebar-header -->

                <!-- Start::main-sidebar -->
                                                <?php $_hasCaretakerSidebar = (($currentRole ?? '') === 'client' && !empty($clientCaretaker)); ?>
                                                <div class="main-sidebar <?= $_hasCaretakerSidebar ? 'has-caretaker-widget' : '' ?>" id="sidebar-scroll">

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
                                        ['plugin' => false, 'controller' => 'ClientPortal', 'action' => 'index'],
                                        [
                                            'class'  => trim('side-menu__item ' . $navActive('clientportal', 'index')),
                                            'escape' => false,
                                        ]
                                    ) ?>
                                </li>
                            <?php else: ?>
                            <?php
                                // Asystent spedytora widzi tylko Kontrahentów i Zlecenia.
                                // Wszystkie inne sekcje (fakturowanie, finanse, księgowość itp.)
                                // są dla niego ukryte.
                                $_isAssistant = (($currentRole ?? '') === 'asystent_spedytora');
                            ?>
                            <!-- Start::slide__category -->
                            <li class="slide__category"><span class="category-name">Booklio TMS</span></li>
                            <!-- End::slide__category -->
                            <?php if (!$_isAssistant): ?>
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
                            <?php endif; /* !$_isAssistant — koniec Fakturowanie */ ?>

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

                            <!-- Słownik adresów transportowych -->
                            <li class="slide <?= $navActive('TransportAddresses', 'index') || $navActive('TransportAddresses', 'add') || $navActive('TransportAddresses', 'edit') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-map-pin-line side-menu__icon"></i><span class="side-menu__label">Słownik adresów</span>',
                                    ['plugin' => false, 'controller' => 'TransportAddresses', 'action' => 'index'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li>

                            <?php if (!$_isAssistant): /* asystent_spedytora — koniec menu, reszta ukryta */ ?>
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

                            <?php endif; /* !$_isAssistant — koniec sekcji TMS/Pomoc */ ?>

                            <?php
                            // Sekcja administracyjna – widoczna tylko dla administratorów
                            $identity = $this->request->getAttribute('identity') ?? null;
                            $isAdmin  = (bool)($identity?->get('is_admin') ?? false);
                            $role     = strtolower((string)($identity?->get('role') ?? ''));
                            if ($isAdmin || $role === 'admin'):
                            ?>
                            <!-- Administracja -->
                            <li class="slide__category"><span class="category-name">Administracja</span></li>
                            <li class="slide <?= $navActive('AdminUsers', 'index') || $navActive('AdminUsers', 'add') || $navActive('AdminUsers', 'edit') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-team-line side-menu__icon"></i>
                                    <span class="side-menu__label">' . __('Użytkownicy') . '</span>',
                                    ['plugin' => false, 'controller' => 'AdminUsers', 'action' => 'index'],
                                    ['escape' => false, 'class' => 'side-menu__item']
                                ) ?>
                            </li>
                            <li class="slide <?= $navActive('Roles', 'index') || $navActive('Roles', 'add') || $navActive('Roles', 'edit') ?>">
                                <?= $this->Html->link(
                                    '<i class="ri-shield-user-line side-menu__icon"></i>
                                    <span class="side-menu__label">' . __('Role i uprawnienia') . '</span>',
                                    ['plugin' => false, 'controller' => 'Roles', 'action' => 'index'],
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

                <?php /* ── Widget: opiekun klienta (tylko dla zalogowanego klienta z opiekunem) ─ */ ?>
                <?php if (($currentRole ?? '') === 'client' && !empty($clientCaretaker)): ?>
                    <?php
                        $_ct       = $clientCaretaker;
                        $_initial  = mb_strtoupper(mb_substr($_ct['name'] !== '' ? $_ct['name'] : $_ct['email'], 0, 1));
                        $_mailto   = $_ct['email'] !== '' ? 'mailto:' . $_ct['email'] : '';
                    ?>
                    <div class="sidebar-caretaker">
                        <div class="sidebar-caretaker__label">
                            <i class="ri-user-heart-line"></i> <?= __('Twój opiekun') ?>
                        </div>
                        <div class="sidebar-caretaker__card">
                            <?php if (!empty($_ct['avatar'])): ?>
                                <img src="<?= h($_ct['avatar']) ?>" alt="" class="sidebar-caretaker__avatar">
                            <?php else: ?>
                                <div class="sidebar-caretaker__avatar sidebar-caretaker__avatar--initials">
                                    <?= h($_initial) ?>
                                </div>
                            <?php endif; ?>
                            <div class="sidebar-caretaker__info">
                                <div class="sidebar-caretaker__name" title="<?= h($_ct['name']) ?>"><?= h($_ct['name']) ?></div>
                                <a href="<?= h($_mailto) ?>" class="sidebar-caretaker__contact" title="<?= h($_ct['email']) ?>">
                                    <i class="ri-mail-line me-1"></i><?= h($_ct['email']) ?>
                                </a>
                                <?php if (!empty($_ct['phone'])):
                                    $_telLink = 'tel:' . preg_replace('/[^+\d]/', '', $_ct['phone']);
                                ?>
                                    <a href="<?= h($_telLink) ?>" class="sidebar-caretaker__contact" title="<?= h($_ct['phone']) ?>">
                                        <i class="ri-phone-line me-1"></i><?= h($_ct['phone']) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($_ct['is_substitute']): ?>
                                    <span class="sidebar-caretaker__badge" title="<?= __('Aktualnie obsługuje Cię zastępca głównego opiekuna') ?>">
                                        <i class="ri-time-line"></i> <?= __('zastępstwo') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </aside>
            <!-- End::app-sidebar -->

            <!--APP-CONTENT START-->
            <div class="main-content app-content">
                <div class="container-fluid mt-2">
                                        <?php
                                            // Pasek "Tryb KSeF" usunięty na życzenie — zostaje tylko
                                            // notyfikacja o roboczych fakturach dla pracowników.
                                            $draftInvoicesCount = (int)($draftInvoicesCount ?? 0);
                                        ?>
                                        <?php if (($currentRole ?? '') !== 'client' && $draftInvoicesCount > 0): ?>
                                            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
                                                <div>
                                                    Masz <strong><?= $draftInvoicesCount ?></strong> roboczych faktur niewysłanych do KSeF.
                                                </div>
                                                <a class="btn btn-sm btn-outline-warning" href="<?= $this->Url->build(['plugin' => false, 'controller' => 'Invoices', 'action' => 'drafts']) ?>">Przejdź do roboczych</a>
                                            </div>
                                        <?php endif; ?>
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
            // '/assets/js/custom-switcher.min.js',  // theme demo — wymaga elementów switcher (kolorów, layoutu) których nie używamy
            '/assets/libs/prismjs/prism.js',
            '/assets/js/prism-custom.js',
            '/assets/libs/choices.js/public/assets/scripts/choices.min.js',
            '/assets/js/alerts.js',
            // '/assets/js/custom.js',  // theme demo — odpowiednik niżej inline (tylko tooltips, bez crashujących elementów)
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

    <!-- Bootstrap tooltips init (przeniesione z theme custom.js — bez crashujących na brakujące elementy) -->
    <script>
    (function () {
        if (typeof bootstrap === 'undefined') return;
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (el) {
            try { new bootstrap.Tooltip(el); } catch (e) {}
        });
    })();
    </script>

    <!-- Toggle: pełny ekran (Fullscreen API) — przełącza między klasycznym a fullscreen widokiem -->
    <script>
    (function () {
        var btn = document.getElementById('fullscreenBtn');
        if (!btn) return;
        var iconOpen  = btn.querySelector('.full-screen-open');
        var iconClose = btn.querySelector('.full-screen-close');

        btn.addEventListener('click', function () {
            try {
                if (!document.fullscreenElement) {
                    (document.documentElement.requestFullscreen
                     || document.documentElement.webkitRequestFullscreen
                     || function () {}).call(document.documentElement);
                } else {
                    (document.exitFullscreen
                     || document.webkitExitFullscreen
                     || function () {}).call(document);
                }
            } catch (e) {}
        });

        function syncIcons() {
            var isFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
            if (iconOpen)  iconOpen.style.display  = isFs ? 'none' : '';
            if (iconClose) iconClose.style.display = isFs ? '' : 'none';
        }
        document.addEventListener('fullscreenchange',       syncIcons);
        document.addEventListener('webkitfullscreenchange', syncIcons);
        syncIcons();
    })();
    </script>

    <!-- Toggle: tryb ciemny / jasny (Zynix theme) — atrybut data-* na <html> + localStorage -->
    <script>
    (function () {
        var btn = document.getElementById('theme-toggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var h = document.documentElement;
            var isDark = h.getAttribute('data-theme-mode') === 'dark';
            if (isDark) {
                h.setAttribute('data-theme-mode',   'light');
                h.setAttribute('data-header-styles','light');
                h.setAttribute('data-menu-styles',  'light');
                localStorage.setItem('themeMode', 'light');
            } else {
                h.setAttribute('data-theme-mode',   'dark');
                h.setAttribute('data-header-styles','dark');
                h.setAttribute('data-menu-styles',  'dark');
                localStorage.setItem('themeMode', 'dark');
            }
        });
    })();
    </script>

    <!-- Screen lock — modal po bezczynności (tylko dla zalogowanych) -->
    <?= $this->element('lock_screen') ?>

    <?php
    // Zapisujemy avatar i e-mail zalogowanego usera do localStorage,
    // żeby ekran logowania mógł go pokazać przy ponownym logowaniu.
    $idForAvatar = $this->request->getAttribute('identity');
    if ($idForAvatar):
        $myEmail = (string)($idForAvatar->get('email') ?? '');
        $myAv    = null;
        try {
            $r = \Cake\ORM\TableRegistry::getTableLocator()->get('Users')->find()
                ->select(['avatar'])
                ->where(['id' => (string)$idForAvatar->getIdentifier()])
                ->disableHydration()
                ->first();
            $_av = $r['avatar'] ?? null;
            // Zapisz do localStorage tylko jeśli plik fizycznie istnieje — żeby ekran
            // logowania nie próbował wczytać brakującego avatara i nie generował 404.
            if ($_av && str_starts_with((string)$_av, '/files/avatars/')) {
                $diskPath = WWW_ROOT . ltrim((string)$_av, '/');
                if (is_file($diskPath)) {
                    $myAv = (string)$_av;
                }
            } elseif ($_av) {
                $myAv = (string)$_av;
            }
        } catch (\Throwable) {}
    ?>
    <script>
    (function () {
        try {
            <?php if (!empty($myAv)): ?>
            localStorage.setItem('lastLoginAvatar', <?= json_encode($myAv) ?>);
            localStorage.setItem('lastLoginEmail',  <?= json_encode($myEmail) ?>);
            <?php else: ?>
            localStorage.removeItem('lastLoginAvatar');
            <?php endif; ?>
        } catch (e) {}
    })();
    </script>
    <?php endif; ?>

</body>
</html>
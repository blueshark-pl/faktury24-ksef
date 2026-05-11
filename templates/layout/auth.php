<?php
/**
 * Layout: Auth
 * Plik: templates/layout/auth.php
 *
 * Zakłada, że statyczne pliki są w /webroot/assets/...
 */

use Cake\Core\Configure;



$this->assign('title', $this->fetch('title') ?: 'Sign In');

$appVersion = trim((string)(Configure::read('App.version') ?? ''));
$authColumnClass = (string)($authColumnClass ?? 'col-xxl-4 col-xl-5 col-lg-6 col-md-8 col-sm-10 col-12');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr"
      data-nav-layout="vertical"
      data-vertical-style="overlay"
      data-theme-mode="light"
      data-header-styles="light"
      data-menu-styles="light"
       style="--primary-rgb: 27, 89, 152;"
      data-toggled="close">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= h($this->fetch('title')) ?> | faktury24.com</title>
    <?= $this->Html->meta('description', 'Faktury24.com') ?>
    <?= $this->Html->meta('author', 'Faktury24.com') ?>
    <?= $this->Html->meta('keywords', 'faktury, faktury24, faktury online, faktury elektroniczne, ksef') ?>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= $this->Url->assetUrl('assets/images/brand-logos/favicon.ico') ?>"/>

    <!-- Main Theme Js (head) -->
    <?= $this->Html->script($this->Url->assetUrl('assets/js/authentication-main.js'), ['block' => true]) ?>

    <!-- Bootstrap Css -->
    <?= $this->Html->css($this->Url->assetUrl('assets/libs/bootstrap/css/bootstrap.min.css')) ?>

    <!-- Style Css -->
    <?= $this->Html->css($this->Url->assetUrl('assets/css/styles.css')) ?>

    <!-- Icons Css -->
    <?= $this->Html->css($this->Url->assetUrl('assets/css/icons.css')) ?>

    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') /* head scripts if any */ ?>
</head>
<body class="position-relative">

    <!-- Switcher -->
    <?= $this->element('auth/switcher') ?>

    <!-- Auth Wrapper -->
    <div class="d-flex align-items-center justify-content-center authentication">
      <div class="auth-bg-glow" aria-hidden="true"></div>
      <div class="auth-bg-dots" aria-hidden="true"></div>

      <div class="container">
        <div class="row justify-content-center">
          <div class="<?= h($authColumnClass) ?>">
            <div class="mb-3 d-flex justify-content-center auth-logo">
              <a href="/" class="d-inline-flex align-items-center gap-2 text-decoration-none" aria-label="Faktury24">
                <!-- <img src="/img/logo-faktury24.png" alt="Faktury24"> -->
              </a>
            </div>
            <?= $this->cell('KsefStatus::banner') ?>
            <?= $this->fetch('content') ?>
          </div>
        </div>
      </div>

      <div class="auth-footer" role="contentinfo">
        <div class="auth-footer-inner">
          Copyright © Booklio.pl All rights reserved
          <?php if ($appVersion !== ''): ?>
            <span class="mx-2">•</span>
            <span>Wersja: <?= h($appVersion) ?></span>
          <?php endif; ?>
          <?= $this->cell('KsefStatus') ?>
        </div>
      </div>
    </div>

    <?= $this->element('auth/dpa_modal') ?>
    <?= $this->fetch('ksefModals') ?>

    <style>
      @property --a {
        syntax: '<angle>';
        inherits: false;
        initial-value: 0deg;
      }

      /* Główny kolor przewodni: #1b5998 (rgb 27,89,152) — transport/logistyka */
      .authentication{
        min-height: 100vh;
        position: relative;
        isolation: isolate;
        overflow: hidden;
        padding: 24px 0 84px;
        background:
          radial-gradient(1100px 700px at 12% 8%, rgba(27, 89, 152, 0.18), transparent 60%),
          radial-gradient(900px 650px at 92% 18%, rgba(27, 89, 152, 0.12), transparent 60%),
          radial-gradient(800px 520px at 50% 105%, rgba(56, 189, 248, 0.10), transparent 62%),
          linear-gradient(180deg, #eaf2fb 0%, #dbe7f5 45%, #f4f8fc 100%) !important;
      }

      .authentication::before{
        content:"";
        position:absolute;
        inset:0;
        pointer-events:none;
        z-index: 0;
        background:
          radial-gradient(900px 620px at 15% 20%, rgba(27, 89, 152, 0.10), transparent 62%),
          radial-gradient(820px 620px at 85% 15%, rgba(27, 89, 152, 0.08), transparent 62%),
          radial-gradient(1200px 900px at 50% 110%, rgba(15, 23, 42, 0.08), transparent 60%);
        box-shadow:
          inset 0 0 220px rgba(27, 89, 152, 0.10),
          inset 0 0 40px rgba(15, 23, 42, 0.06);
      }

      .authentication .auth-bg-glow{
        position:absolute;
        inset:-20%;
        z-index: 0;
        pointer-events:none;
        background:
          radial-gradient(700px 520px at 18% 18%, rgba(27, 89, 152, 0.22), transparent 60%),
          radial-gradient(620px 520px at 78% 28%, rgba(56, 189, 248, 0.18), transparent 62%),
          radial-gradient(640px 520px at 62% 78%, rgba(27, 89, 152, 0.14), transparent 62%);
        filter: blur(22px) saturate(125%);
        opacity: 0.85;
        transform: translate3d(0,0,0);
        animation: authGlowDrift 22s ease-in-out infinite alternate;
      }

      @keyframes authGlowDrift{
        0%   { transform: translate3d(-1.5%, -1%, 0) scale(1.02); }
        100% { transform: translate3d(1.5%, 1%, 0) scale(1.04); }
      }

      /* Warstwa "logistyczna": siatka dróg/mapy + delikatny SVG pattern z trasą i ikonami */
      .authentication .auth-bg-dots{
        position:absolute;
        inset:-2px;
        z-index: 1;
        pointer-events:none;
        display:block;
        background-image:
          /* siatka mapy (poziomo i pionowo) */
          linear-gradient(rgba(27, 89, 152, 0.06) 1px, transparent 1px),
          linear-gradient(90deg, rgba(27, 89, 152, 0.06) 1px, transparent 1px),
          /* drobne kropki — przypinki na mapie */
          radial-gradient(circle at 1px 1px, rgba(27, 89, 152, 0.32) 1.15px, transparent 2.05px),
          radial-gradient(circle at 2px 2px, rgba(27, 89, 152, 0.14) 1.45px, transparent 3.3px);
        background-size: 56px 56px, 56px 56px, 28px 28px, 84px 84px;
        background-position: 0 0, 0 0, 0 0, 14px 22px;
        opacity: 0.55;
        filter: saturate(140%) contrast(110%);
        -webkit-mask-image: radial-gradient(closest-side at 50% 45%, rgba(0,0,0,1) 0%, rgba(0,0,0,0.85) 70%, rgba(0,0,0,0) 100%);
        mask-image: radial-gradient(closest-side at 50% 45%, rgba(0,0,0,1) 0%, rgba(0,0,0,0.85) 70%, rgba(0,0,0,0) 100%);
        animation: authDotsFloat 30s linear infinite;
      }

      @keyframes authDotsFloat{
        to { background-position: 120px 80px, 120px 80px, 140px 100px, 154px 102px; }
      }

      /* Warstwa SVG z ikonami logistyki (ciężarówka, kompas, łańcuch dostaw) — bardzo delikatna */
      .authentication::after{
        content:"";
        position:absolute;
        inset:0;
        z-index: 1;
        pointer-events:none;
        opacity: 0.10;
        background-repeat: no-repeat;
        background-position: 6% 86%, 92% 12%, 88% 78%, 10% 14%;
        background-size: 180px, 140px, 120px, 110px;
        background-image:
          /* Ciężarówka — lewy dół */
          url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231b5998' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'><path d='M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2'/><path d='M15 18H9'/><path d='M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14'/><circle cx='17' cy='18' r='2'/><circle cx='7' cy='18' r='2'/></svg>"),
          /* Kompas — prawy góra */
          url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231b5998' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><polygon points='16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76'/></svg>"),
          /* Pakunek — prawy dół */
          url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231b5998' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'><path d='M16.5 9.4 7.5 4.21'/><path d='M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'/><polyline points='3.29 7 12 12 20.71 7'/><line x1='12' x2='12' y1='22' y2='12'/></svg>"),
          /* Pin lokalizacji — lewy góra */
          url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231b5998' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'><path d='M20 10c0 7-8 12-8 12s-8-5-8-12a8 8 0 0 1 16 0z'/><circle cx='12' cy='10' r='3'/></svg>");
      }

      .authentication > .container{
        position: relative;
        z-index: 3;
      }

      .authentication .auth-logo{
        position: relative;
        z-index: 4;
        margin-bottom: 12px !important;
      }

      .authentication .auth-logo a{
        position: relative;
        padding: 6px 2px;
        border-radius: 16px;
        outline: none;
      }

      .authentication .auth-logo a::before{
        content: "";
        position: absolute;
        left: 50%;
        top: 52%;
        width: 108%;
        height: 108%;
        transform: translate(-50%, -50%);
        pointer-events: none;
        z-index: -1;
        background:
          radial-gradient(closest-side, rgba(27, 89, 152, 0.14), transparent 72%),
          radial-gradient(closest-side, rgba(56, 189, 248, 0.08), transparent 74%);
        filter: blur(10px);
        opacity: 0.6;
      }

      .authentication .auth-logo a:focus-visible{
        box-shadow: 0 0 0 0.25rem rgba(27, 89, 152, 0.22);
      }

      .authentication .auth-logo img{
        display: block;
        width: clamp(120px, 34vw, 180px);
        max-height: 44px;
        height: auto;
        filter: drop-shadow(0 10px 26px rgba(15, 23, 42, 0.18));
        transition: transform 180ms ease, filter 180ms ease;
      }

      @media (hover:hover) and (pointer:fine){
        .authentication .auth-logo a:hover img{
          transform: translateY(-1px);
          filter: drop-shadow(0 14px 32px rgba(15, 23, 42, 0.20));
        }
      }

      .authentication .card.custom-card{
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.92) !important;
        -webkit-backdrop-filter: blur(10px) saturate(140%);
                backdrop-filter: blur(10px) saturate(140%);
        box-shadow: 0 30px 90px rgba(15, 23, 42, 0.18), 0 12px 28px rgba(15, 23, 42, 0.10) !important;
        border: 1px solid rgba(15, 23, 42, 0.08) !important;
        transition: transform 220ms ease, box-shadow 220ms ease;
      }

        @media (hover:hover) and (pointer:fine){
          .authentication .card.custom-card:hover{
            transform: translateY(-3px);
            box-shadow: 0 44px 120px rgba(15, 23, 42, 0.22), 0 16px 36px rgba(15, 23, 42, 0.12) !important;
          }
        }

        /* subtle inner highlight */
        .authentication .card.custom-card::after{
          content:"";
          position:absolute;
          inset:0;
          pointer-events:none;
          background:
            radial-gradient(800px 240px at 50% 0%, rgba(27, 89, 152, 0.12), transparent 55%),
            radial-gradient(700px 240px at 0% 20%, rgba(56, 189, 248, 0.10), transparent 58%);
          opacity: 0.85;
        }

      .authentication .card.custom-card::before{
        content:"";
        position:absolute;
        inset:0;
        padding:2px;
        border-radius: inherit;
        pointer-events:none;
        --a: 0deg;
        background: conic-gradient(
          from var(--a),
          transparent 0deg,
          rgba(56, 189, 248, 0.12) 14deg,
          rgba(var(--primary-rgb), 0.22) 22deg,
          rgba(var(--primary-rgb), 0.58) 30deg,
          rgba(var(--primary-rgb), 0.20) 38deg,
          transparent 48deg,
          transparent 360deg
        );
        -webkit-mask:
          linear-gradient(#000 0 0) content-box,
          linear-gradient(#000 0 0);
        -webkit-mask-composite: xor;
                mask-composite: exclude;
        filter: drop-shadow(0 0 10px rgba(var(--primary-rgb), .18));
        animation: borderCometSec 10s linear infinite;
      }

      .authentication .form-control:focus,
      .authentication .form-select:focus{
          border-color: rgba(27, 89, 152, 0.50) !important;
          box-shadow:
            0 0 0 0.25rem rgba(27, 89, 152, 0.20),
            0 10px 24px rgba(15, 23, 42, 0.10) !important;
        }

        /* Respect reduced motion */
        @media (prefers-reduced-motion: reduce){
          .authentication .auth-bg-glow,
          .authentication .auth-bg-dots,
          .authentication .card.custom-card::before{
            animation: none !important;
          }
          .authentication .card.custom-card{
            transition: none !important;
          }
        }

      @keyframes borderCometSec{
        to { --a: 360deg; }
      }

      .auth-footer{
        position: absolute;
        left: 0;
        right: 0;
        bottom: 16px;
        z-index: 3;
        display: flex;
        justify-content: center;
        padding: 0 12px;
      }

      .auth-footer-inner{
        font-size: 12px;
        color: rgba(15, 23, 42, 0.65);
        background: rgba(255, 255, 255, 0.55);
        border: 1px solid rgba(15, 23, 42, 0.10);
        border-radius: 999px;
        padding: 8px 12px;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        box-shadow: 0 12px 26px rgba(15, 23, 42, 0.10);
        text-align: center;
      }

      .auth-footer-inner a{
        color: inherit;
        text-decoration: underline;
        text-underline-offset: 3px;
      }

      .auth-footer-inner a:hover{
        color: rgba(15, 23, 42, 0.85);
      }
    </style>
    <!-- Bootstrap JS -->
    <?= $this->Html->script($this->Url->assetUrl('assets/libs/bootstrap/js/bootstrap.bundle.min.js'), ['block' => 'bottom']) ?>


    <!-- Cover Password (opcjonalnie – jeśli używasz createpassword()) -->
    <?= $this->Html->script($this->Url->assetUrl('assets/js/cover-password.js'), ['block' => 'bottom']) ?>

    <!-- Show Password JS -->
    <?= $this->Html->script($this->Url->assetUrl('assets/js/show-password.js'), ['block' => 'bottom']) ?>

    <?= $this->fetch('bottom') /* legacy block */ ?>
    <?= $this->fetch('script') /* if someone used 'script' for bottom */ ?>

</body>
</html>

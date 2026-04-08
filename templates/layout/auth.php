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
       style="--primary-rgb: 148, 200, 31;"
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
          Copyright © Partner S.C. All rights reserved
          <?php if ($appVersion !== ''): ?>
            <span class="mx-2">•</span>
            <span>Wersja: <?= h($appVersion) ?></span>
          <?php endif; ?>
          <?= $this->cell('KsefStatus') ?>
          <span class="mx-2">•</span>
          <a href="#" role="button" data-bs-toggle="modal" data-bs-target="#regulaminModal"><?= __('Regulamin') ?></a>
          <span class="mx-2">•</span>
          <a href="#" role="button" data-bs-toggle="modal" data-bs-target="#privacyModal"><?= __('Polityka prywatności') ?></a>
        </div>
      </div>
    </div>

    <?= $this->element('auth/regulamin_modal') ?>
    <?= $this->element('auth/polityka_prywatnosci_modal') ?>
    <?= $this->element('auth/dpa_modal') ?>
    <?= $this->fetch('ksefModals') ?>

    <style>
      @property --a {
        syntax: '<angle>';
        inherits: false;
        initial-value: 0deg;
      }

      .authentication{
        min-height: 100vh;
        position: relative;
        isolation: isolate;
        overflow: hidden;
        padding: 24px 0 84px;
        background:
          radial-gradient(1100px 700px at 12% 8%, rgba(138, 32, 140, 0.10), transparent 60%),
          radial-gradient(900px 650px at 92% 18%, rgba(77, 170, 72, 0.10), transparent 60%),
          radial-gradient(800px 520px at 50% 105%, rgba(56, 189, 248, 0.08), transparent 62%),
          linear-gradient(180deg, #f7f8fe 0%, #eef2ff 45%, #ffffff 100%) !important;
      }

      .authentication::before{
        content:"";
        position:absolute;
        inset:0;
        pointer-events:none;
        z-index: 0;
        background:
          radial-gradient(900px 620px at 15% 20%, rgba(34, 197, 94, 0.10), transparent 62%),
          radial-gradient(820px 620px at 85% 15%, rgba(249, 115, 22, 0.10), transparent 62%),
          radial-gradient(1200px 900px at 50% 110%, rgba(15, 23, 42, 0.06), transparent 60%);
        box-shadow:
          inset 0 0 220px rgba(15, 23, 42, 0.10),
          inset 0 0 40px rgba(15, 23, 42, 0.06);
      }

      .authentication .auth-bg-glow{
        position:absolute;
        inset:-20%;
        z-index: 0;
        pointer-events:none;
        background:
          radial-gradient(700px 520px at 18% 18%, rgba(34, 197, 94, 0.18), transparent 60%),
          radial-gradient(620px 520px at 78% 28%, rgba(56, 189, 248, 0.12), transparent 62%),
          radial-gradient(640px 520px at 62% 78%, rgba(138, 32, 140, 0.14), transparent 62%);
        filter: blur(22px) saturate(135%);
        opacity: 0.9;
        transform: translate3d(0,0,0);
        animation: authGlowDrift 22s ease-in-out infinite alternate;
      }

      @keyframes authGlowDrift{
        0%   { transform: translate3d(-1.5%, -1%, 0) scale(1.02); }
        100% { transform: translate3d(1.5%, 1%, 0) scale(1.04); }
      }

      .authentication .auth-bg-dots{
        position:absolute;
        inset:-2px;
        z-index: 1;
        pointer-events:none;
        display:block;
        background-image:
          radial-gradient(circle at 1px 1px, rgba(34, 197, 94, 0.34) 1.15px, transparent 2.05px),
          radial-gradient(circle at 2px 2px, rgba(34, 197, 94, 0.16) 1.45px, transparent 3.3px);
        background-size: 22px 22px, 64px 64px;
        background-position: 0 0, 12px 18px;
        opacity: 0.62;
        filter: saturate(160%) contrast(118%);
        -webkit-mask-image: radial-gradient(closest-side at 50% 45%, rgba(0,0,0,1) 0%, rgba(0,0,0,0.85) 70%, rgba(0,0,0,0) 100%);
        mask-image: radial-gradient(closest-side at 50% 45%, rgba(0,0,0,1) 0%, rgba(0,0,0,0.85) 70%, rgba(0,0,0,0) 100%);
        animation: authDotsFloat 18s linear infinite;
      }

      @keyframes authDotsFloat{
        to { background-position: 120px 80px; }
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
          radial-gradient(closest-side, rgba(34, 197, 94, 0.12), transparent 72%),
          radial-gradient(closest-side, rgba(56, 189, 248, 0.06), transparent 74%);
        filter: blur(10px);
        opacity: 0.55;
      }

      .authentication .auth-logo a:focus-visible{
        box-shadow: 0 0 0 0.25rem rgba(34, 197, 94, 0.18);
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
            radial-gradient(800px 240px at 50% 0%, rgba(34, 197, 94, 0.10), transparent 55%),
            radial-gradient(700px 240px at 0% 20%, rgba(56, 189, 248, 0.08), transparent 58%);
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
          border-color: rgba(34, 197, 94, 0.45) !important;
          box-shadow:
            0 0 0 0.25rem rgba(34, 197, 94, 0.18),
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

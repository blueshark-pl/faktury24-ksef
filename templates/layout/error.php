<?php
/**
 * @var \App\View\AppView $this
 */
use Cake\Core\Configure;

$appVersion = trim((string)(Configure::read('App.version') ?? ''));
?>
<!DOCTYPE html>
<html lang="pl"
      data-theme-mode="light"
      style="--primary-rgb: 27, 89, 152;">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Błąd — Booklio TMS</title>

    <!-- Favicon -->
    <link rel="icon"           type="image/png"     sizes="96x96" href="/favicon-96x96.png">
    <link rel="icon"           type="image/svg+xml"               href="/favicon.svg">
    <link rel="shortcut icon"                                     href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180"                  href="/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-title" content="Booklio TMS">
    <link rel="manifest"                                          href="/site.webmanifest">

    <?= $this->Html->css([
        '/assets/libs/bootstrap/css/bootstrap.min.css',
        '/assets/css/styles.css',
        '/assets/css/icons.css',
    ]) ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <style>
      /* Tło w odcieniach Booklio + delikatne motywy logistyczne */
      body.err-body {
        min-height: 100vh;
        position: relative;
        isolation: isolate;
        overflow-x: hidden;
        background:
          radial-gradient(900px 600px at 12% 10%, rgba(27, 89, 152, .16), transparent 60%),
          radial-gradient(800px 540px at 88% 20%, rgba(27, 89, 152, .10), transparent 60%),
          radial-gradient(700px 460px at 50% 110%, rgba(56, 189, 248, .08), transparent 62%),
          linear-gradient(180deg, #eaf2fb 0%, #dbe7f5 45%, #f4f8fc 100%);
      }
      /* Siatka mapy + 4 ikony logistyki w rogach */
      body.err-body::before {
        content: ""; position: fixed; inset: 0; pointer-events: none; z-index: 0;
        background-image:
          linear-gradient(rgba(27, 89, 152, 0.06) 1px, transparent 1px),
          linear-gradient(90deg, rgba(27, 89, 152, 0.06) 1px, transparent 1px),
          radial-gradient(circle at 1px 1px, rgba(27, 89, 152, 0.30) 1.15px, transparent 2.05px);
        background-size: 56px 56px, 56px 56px, 28px 28px;
        opacity: 0.45;
        -webkit-mask-image: radial-gradient(closest-side at 50% 40%, rgba(0,0,0,1) 0%, rgba(0,0,0,0.7) 70%, rgba(0,0,0,0) 100%);
                mask-image: radial-gradient(closest-side at 50% 40%, rgba(0,0,0,1) 0%, rgba(0,0,0,0.7) 70%, rgba(0,0,0,0) 100%);
      }
      body.err-body::after {
        content: ""; position: fixed; inset: 0; pointer-events: none; z-index: 0;
        opacity: 0.10;
        background-repeat: no-repeat;
        background-position: 6% 84%, 92% 12%, 88% 78%, 10% 14%;
        background-size: 170px, 130px, 110px, 105px;
        background-image:
          url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231b5998' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'><path d='M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2'/><path d='M15 18H9'/><path d='M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14'/><circle cx='17' cy='18' r='2'/><circle cx='7' cy='18' r='2'/></svg>"),
          url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231b5998' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'/><polygon points='16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76'/></svg>"),
          url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231b5998' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'><path d='M16.5 9.4 7.5 4.21'/><path d='M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z'/><polyline points='3.29 7 12 12 20.71 7'/><line x1='12' x2='12' y1='22' y2='12'/></svg>"),
          url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231b5998' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'><path d='M20 10c0 7-8 12-8 12s-8-5-8-12a8 8 0 0 1 16 0z'/><circle cx='12' cy='10' r='3'/></svg>");
      }

      .err-wrap { position: relative; z-index: 2; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 12px 84px; }
      .err-card {
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(27, 89, 152, 0.12);
        border-radius: 1.25rem;
        padding: 2.5rem 2rem;
        max-width: 560px;
        width: 100%;
        text-align: center;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .14), 0 8px 22px rgba(27, 89, 152, .10);
        backdrop-filter: blur(8px) saturate(140%);
        -webkit-backdrop-filter: blur(8px) saturate(140%);
      }
      .err-logo { max-width: 180px; height: auto; margin-bottom: 1.25rem; opacity: .92; }
      .err-visual {
        position: relative;
        display: inline-block;
        margin-bottom: 1rem;
      }
      .err-visual .err-code {
        font-size: 5.5rem;
        font-weight: 800;
        color: rgb(27, 89, 152);
        line-height: 1;
        letter-spacing: -.04em;
        text-shadow: 0 4px 18px rgba(27, 89, 152, .25);
      }
      .err-visual .err-icon-overlay {
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        font-size: 3.5rem;
        opacity: .12;
        color: rgb(27, 89, 152);
        z-index: -1;
      }
      .err-title { font-size: 1.35rem; font-weight: 700; color: #1e293b; margin-bottom: .5rem; }
      .err-desc  { color: rgba(15, 23, 42, .65); margin-bottom: 1.25rem; }
      .err-chip {
        display: inline-flex; align-items: center; gap: .4rem;
        background: rgba(27, 89, 152, .08);
        border: 1px solid rgba(27, 89, 152, .18);
        color: rgb(27, 89, 152);
        font-weight: 600; font-size: .78rem;
        padding: .35rem .85rem;
        border-radius: 2rem;
        margin-bottom: 1rem;
      }
      .err-contact { color: rgba(15, 23, 42, .55); font-size: .8rem; margin-top: 1.25rem; }
      .err-contact a { color: rgb(27, 89, 152); font-weight: 600; text-decoration: none; }
      .err-contact a:hover { text-decoration: underline; }
      .err-actions { display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; }
      .btn-primary-booklio {
        background: rgb(27, 89, 152); border-color: rgb(27, 89, 152); color: #fff;
      }
      .btn-primary-booklio:hover { background: rgb(20, 71, 124); border-color: rgb(20, 71, 124); color: #fff; }

      .auth-footer { position: fixed; left: 0; right: 0; bottom: 16px; z-index: 3; display: flex; justify-content: center; padding: 0 12px; }
      .auth-footer-inner {
        font-size: 12px;
        color: rgba(15, 23, 42, .65);
        background: rgba(255, 255, 255, .65);
        border: 1px solid rgba(27, 89, 152, .12);
        border-radius: 999px;
        padding: 8px 14px;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        box-shadow: 0 12px 26px rgba(15, 23, 42, .10);
        text-align: center;
      }
      .auth-footer-inner a { color: inherit; text-decoration: underline; text-underline-offset: 3px; }
      .auth-footer-inner a:hover { color: rgb(27, 89, 152); }
    </style>
</head>
<body class="err-body">
    <div class="err-wrap">
        <div class="err-card">
            <img src="/img/logo.png" alt="Booklio TMS" class="err-logo">
            <?= $this->Flash->render() ?>
            <?= $this->fetch('content') ?>
        </div>
    </div>
    <div class="auth-footer" role="contentinfo">
        <div class="auth-footer-inner">
            Copyright © Booklio.pl All rights reserved
            <?php if ($appVersion !== ''): ?>
                <span class="mx-2">•</span>
                <span>Wersja: <?= h($appVersion) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?= $this->Html->script('/assets/libs/bootstrap/js/bootstrap.bundle.min.js') ?>
</body>
</html>

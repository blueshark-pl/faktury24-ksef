<?php
/**
 * Layout: Auth
 * Plik: templates/layout/auth.php
 *
 * Zakłada, że statyczne pliki są w /webroot/assets/...
 */
$this->assign('title', $this->fetch('title') ?: 'Sign In');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr"
      data-nav-layout="vertical"
      data-vertical-style="overlay"
      data-theme-mode="light"
      data-header-styles="light"
      data-menu-styles="light"
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
        <div class="col-xl-9 col-md-6 col-11">
            <div class="row authentication-cover-main mx-0 border rounded bg-white">
                <!-- Left cover -->
                <div class="col-xxl-6 col-xl-5 col-lg-12 d-xl-block d-none px-0">
                    <?= $this->element('auth/cover_left') ?>
                </div>

                <!-- Right content (page-specific view goes here) -->
                <div class="col-xxl-6 col-xl-7">
                    <div class="row justify-content-center align-items-center h-100">
                        <div class="col-xxl-8 col-xl-9 col-lg-10 col-md-10 col-sm-10 col-12">
                            <?= $this->fetch('content') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
     </div>
    <style>
        /* Register typed custom property for smooth angle animation */
        @property --a {
          syntax: '<angle>';
          inherits: false;
          initial-value: 0deg;
        }

        .authentication-cover-main{
  position: relative;
  overflow: hidden;
}

.authentication-cover-main::before{
  content:"";
  position:absolute;
  inset:0;
  padding:2px;                 /* grubość obrysu */
  border-radius: inherit;
  pointer-events:none;
  --a: 0deg;

  /* KOMETA: krótka głowa + dłuższy ogon z malejącą alfą */
  background: conic-gradient(
    from var(--a),
    transparent 0deg,
 rgba(138,32,140,0.10) 10deg,
    rgba(108, 140, 32, 0.22) 18deg,
    rgba(37, 140, 32, 0.45) 26deg,
    rgba(77, 170, 72, 0.95) 34deg, /* GŁOWA – FIOLET */
    transparent 42deg,
    transparent 360deg
  );

  /* wycięcie środka => zostaje tylko obrys */
  -webkit-mask:
    linear-gradient(#000 0 0) content-box,
    linear-gradient(#000 0 0);
  -webkit-mask-composite: xor;
          mask-composite: exclude;

  /* delikatny „glow” ogona */
  filter: drop-shadow(0 0 8px rgba(138,32,140,.25));

  animation: borderCometSec 6s linear infinite;
}

@keyframes borderComet{
  0%   { --a: 0deg;   opacity: 0; }
  5%   { opacity: 1; }           /* pojawia się */
  40%  { --a: 360deg; opacity: 1; } /* przelot */
  45%  { opacity: 0; }           /* znika */
  100% { --a: 360deg; opacity: 0; } /* PAUZA */
}
@keyframes borderCometSec{
  to { --a: 360deg; }
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

<?php
/**
 * @var \App\View\AppView $this
 */
use Cake\Core\Configure;

$appVersion = trim((string)(Configure::read('App.version') ?? ''));
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Błąd — Faktury24</title>
    <?= $this->Html->meta('icon') ?>
    <?= $this->Html->css([
        '/assets/libs/bootstrap/css/bootstrap.min.css',
        '/assets/css/styles.css',
        '/assets/css/icons.css',
    ]) ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <style>
      .auth-footer{
        position: fixed;
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
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center" style="min-height:100vh;align-items:center;">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <?= $this->Flash->render() ?>
                        <?= $this->fetch('content') ?>
                    </div>
                </div>
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
        </div>
    </div>
</body>
</html>

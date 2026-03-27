<?php
/**
 * @var \App\View\AppView $this
 */
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
</body>
</html>

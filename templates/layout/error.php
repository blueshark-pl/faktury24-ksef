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
    <?= $this->Html->meta('icon', $this->Url->assetUrl('/assets/images/brand-logos/favicon.ico'), ['type' => 'image/x-icon']) ?>
    <?= $this->Html->css([
        '/assets/libs/bootstrap/css/bootstrap.min.css',
        '/assets/css/styles.css',
        '/assets/css/icons.css',
    ]) ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .error-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 0; }
        .error-card { border: none; border-radius: 1rem; overflow: hidden; }
        .error-card .card-body { padding: 3rem 2.5rem; }
        .error-icon { width: 120px; height: 120px; margin: 0 auto 1.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .error-icon.warn { background: rgba(255, 193, 7, 0.12); }
        .error-icon.danger { background: rgba(220, 53, 69, 0.10); }
        .error-icon i { font-size: 3rem; }
        .error-code-badge { font-family: 'Courier New', monospace; font-size: 0.85rem; letter-spacing: 1px; padding: 0.5rem 1.2rem; border-radius: 2rem; background: #f8f9fa; border: 1px solid #e9ecef; color: #495057; display: inline-block; }
        .error-title { font-weight: 700; color: #2d3748; }
        .error-subtitle { color: #718096; font-size: 1.05rem; line-height: 1.6; }
        .error-footer { font-size: 0.82rem; color: #a0aec0; }
        .error-actions .btn { padding: 0.6rem 1.5rem; border-radius: 0.5rem; font-weight: 500; }
    </style>
</head>
<body>
    <div class="error-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7 col-sm-10">
                    <div class="card error-card shadow-lg">
                        <div class="card-body text-center">
                            <?= $this->Flash->render() ?>
                            <?= $this->fetch('content') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

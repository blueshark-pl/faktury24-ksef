<?php
/** @var string $error */
$this->assign('title', 'Nieprawidłowy link');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body style="background:#f3f4f6;padding:20px">
<div class="container" style="max-width:500px">
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <div style="font-size:60px">🔒</div>
            <h4 class="mt-3">Nieprawidłowy link</h4>
            <p class="text-muted"><?= h($error ?? 'Link wygasł lub jest nieprawidłowy. Skontaktuj się z dyspozytorem.') ?></p>
        </div>
    </div>
</div>
</body>
</html>

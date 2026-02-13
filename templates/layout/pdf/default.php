<?php
// Default PDF layout for CakePdf (Dompdf)
// Provides basic styling for invoice print views.
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($this->fetch('title') ?: 'Faktura') ?></title>
    <style>
        /* Minimal base only; full styles live in the view (print.php) */
        html, body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color: #111; }
        body { font-size: 12px; line-height: 1.35; }
        table { border-collapse: collapse; width: 100%; }
        .page-break { page-break-after: always; }
        
    </style>
    <style>
@page {
  margin: 12mm;
  @bottom-right {
    content: "Strona " counter(page) " z " counter(pages);
    font-size: 9px;
    color: #777;
  }
}
</style>

    <?= $this->fetch('css') ?>
</head>
<body>
    <?= $this->fetch('content') ?>
    <?= $this->fetch('script') ?>
</body>
</html>

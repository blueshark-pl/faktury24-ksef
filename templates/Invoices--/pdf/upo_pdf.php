<?php
/**
 * @var \App\View\AppView $this
 * @var array $upo Parsed UPO fields
 * @var string $upoXml Raw UPO XML
 * @var string $ksefNumber
 * @var string $sessionRef
 * @var string $environment
 */
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8" />
    <title>UPO — KSeF</title>
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color: #111; }
        .header { text-align:center; margin-bottom: 12px; }
        .header h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #666; }
        .section { border: 1px solid #ddd; padding: 10px 12px; margin-bottom: 10px; border-radius: 4px; }
        .grid { display: table; width: 100%; border-collapse: collapse; }
        .row { display: table-row; }
        .cell { display: table-cell; padding: 3px 6px; vertical-align: top; }
        .cell.label { width: 35%; color: #444; }
        .code { font-family: "DejaVu Sans Mono", Consolas, monospace; font-size: 11px; white-space: pre-wrap; word-break: break-word; }
        .footer { margin-top: 16px; font-size: 10px; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Urzędowe Poświadczenie Odbioru (UPO)</h1>
        <div class="muted">Krajowy System e‑Faktur — środowisko: <?= h(strtoupper((string)$environment)) ?></div>
    </div>

    <div class="section">
        <div class="grid">
            <div class="row">
                <div class="cell label">Numer KSeF</div>
                <div class="cell"><strong><?= h((string)$ksefNumber) ?></strong></div>
            </div>
            <div class="row">
                <div class="cell label">Numer referencyjny sesji</div>
                <div class="cell"><?= h((string)($sessionRef ?: '—')) ?></div>
            </div>
        </div>
        <div class="muted" style="margin-top:8px;">Uproszczony podgląd UPO — poniżej szkielet XML (bez pełnej treści).</div>
    </div>

    <div class="section">
        <div class="muted" style="margin-bottom:6px;">Szkielet XML</div>
        <div class="code"><pre><code><?= h((string)($xmlSkeleton ?? '')) ?></code></pre></div>
    </div>

    <div class="footer">
        Dokument wygenerowano lokalnie na podstawie UPO z KSeF. Układ zbliżony do wzoru MF; w przypadku rozbieżności decyduje treść UPO XML.
    </div>
</body>
</html>

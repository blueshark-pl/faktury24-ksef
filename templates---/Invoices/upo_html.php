<?php
/** @var \App\View\AppView $this */
/** @var string $ksefNumber */
/** @var string $sessionRef */
/** @var string $environment */
/** @var null|string $xml */
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UPO – podgląd (<?= h(strtoupper($environment)) ?>)</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; margin: 1.5rem; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem 1.25rem; max-width: 900px; }
        .muted { color: #6b7280; font-size: .9rem; }
        .grid { display: grid; grid-template-columns: 220px 1fr; gap: .5rem 1rem; }
        .key { color: #374151; }
        .val { color: #111827; font-weight: 600; }
        details { margin-top: 1rem; }
        details > summary { cursor: pointer; user-select: none; }
        pre { background: #0b1020; color: #e5e7eb; padding: 1rem; border-radius: 6px; overflow: auto; max-height: 50vh; }
        code { font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: .85rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="muted">UPO – Urzędowe Poświadczenie Odbioru</div>
        <h2 style="margin:.25rem 0 1rem 0;">Podgląd (<?= h(strtoupper($environment)) ?>)</h2>
        <div class="grid">
            <div class="key">Numer KSeF</div>
            <div class="val"><?= h($ksefNumber) ?></div>
            <div class="key">Referencja sesji</div>
            <div class="val"><?= $sessionRef !== '' ? h($sessionRef) : '<span class="muted">brak</span>' ?></div>
        </div>

        <?php if (!empty($xml)): ?>
            <details>
                <summary>Pokaż surowy XML UPO</summary>
                <pre><code><?= h($xml) ?></code></pre>
            </details>
        <?php else: ?>
            <p class="muted" style="margin-top:1rem;">Brak treści XML do podglądu (UPO mogło zostać zwrócone jako PDF lub nie jest jeszcze dostępne).</p>
        <?php endif; ?>
    </div>
</body>
</html>

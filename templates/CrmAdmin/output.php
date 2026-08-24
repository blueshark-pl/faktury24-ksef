<?php
/**
 * @var \App\View\AppView $this
 * @var string $title
 * @var string $output
 */
$this->assign('title', $title ?? 'Output');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= h($title ?? 'Admin output') ?></title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; margin: 0; }
        h4 { color: #4ec9b0; border-bottom: 1px solid #444; padding-bottom: 8px; }
        pre { background: #252526; padding: 15px; border-radius: 6px; overflow-x: auto;
              font-size: 13px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
        .top-bar { position: sticky; top: 0; background: #1e1e1e; padding: 10px 0; }
        .btn { display: inline-block; padding: 6px 14px; background: #0e639c; color: #fff;
               text-decoration: none; border-radius: 4px; margin-right: 8px; font-size: 13px; }
        .btn:hover { background: #1177bb; }
        .btn-secondary { background: #444; }
    </style>
</head>
<body>
    <div class="top-bar">
        <h4><?= h($title) ?></h4>
        <a href="<?= $this->Url->build(['controller' => 'CrmAdmin', 'action' => 'tools']) ?>" class="btn btn-secondary">← Wróć do Admin Tools</a>
        <a href="javascript:location.reload()" class="btn">🔄 Refresh</a>
        <a href="javascript:window.close()" class="btn btn-secondary">Zamknij</a>
    </div>
    <pre><?= h($output) ?></pre>
</body>
</html>

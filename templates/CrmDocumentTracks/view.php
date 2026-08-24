<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\CrmDocumentTrack $track
 * @var string $pdfUrl
 */
$this->assign('title', $track->document_name ?: 'Dokument');
?>
<style>
    body { margin: 0; background: #1e2028; font-family: system-ui, sans-serif; }
    .doc-header { background: #2d3140; color: #fff; padding: 12px 20px;
        display: flex; justify-content: space-between; align-items: center; }
    .doc-name { font-weight: 600; }
    .doc-download { background: #94C81F; color: #fff; padding: 6px 14px; border-radius: 6px;
        text-decoration: none; font-size: 13px; font-weight: 600; }
    .doc-download:hover { background: #6b8f14; color: #fff; }
    embed, iframe { width: 100%; height: calc(100vh - 60px); border: none; }
</style>

<div class="doc-header">
    <span class="doc-name">
        <span style="opacity:0.6; font-size:12px;">📄</span> <?= h($track->document_name ?: 'document.pdf') ?>
    </span>
    <a href="<?= h($pdfUrl) ?>" download class="doc-download">
        Pobierz PDF
    </a>
</div>

<iframe src="<?= h($pdfUrl) ?>" title="<?= h($track->document_name) ?>"></iframe>

<script>
// Heartbeat co 10s - liczy total_time_seconds
(function() {
    var lastPing = Date.now();
    setInterval(function() {
        if (document.visibilityState !== 'visible') return;
        var now = Date.now();
        if (now - lastPing < 9000) return;
        lastPing = now;
        fetch('/doc/<?= h($track->hash) ?>/heartbeat', {
            method: 'POST',
            credentials: 'omit',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_csrfToken='
        }).catch(function() {});
    }, 10000);
})();
</script>

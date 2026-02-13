<?php
/**
 * KSeF XML preview modal and common scripts
 * Expects: $env (string)
 */
?>
<style>
#xmlPreviewModal { position: fixed; inset:0; background: rgba(0,0,0,.5); display:none; }
#xmlPreviewModal.show { display:block; }
#xmlPreviewModal .modal-dialog { max-width: 90vw; margin: 5vh auto; }
#xmlPreviewModal .modal-content { background:#fff; border-radius:.5rem; overflow:hidden; }
#xmlPreviewModal pre { max-height:70vh; overflow:auto; margin:0; padding:1rem; background:#0f172a; color:#e2e8f0; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
#xmlPreviewModal .tok-tag { color:#93c5fd; }
#xmlPreviewModal .tok-attr { color:#a7f3d0; }
#xmlPreviewModal .tok-val { color:#fca5a5; }
#xmlPreviewModal .tok-comm { color:#9ca3af; font-style: italic; }
</style>
<div id="xmlPreviewModal" role="dialog" aria-modal="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header d-flex justify-content-between align-items-center p-2 px-3">
        <strong>Podgląd XML</strong>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-close-modal>&times;</button>
      </div>
      <pre id="xmlPreviewBody">&nbsp;</pre>
      <div class="p-2 px-3 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-close-modal>Zamknij</button>
      </div>
    </div>
  </div>
</div>

<script>
// Kopiuj numer KSeF i faktury
document.addEventListener('click', function(e){
  const ksefBtn = e.target.closest('.copy-ksef');
  if (ksefBtn) {
    // If icon-only variant is used (icon-clip or contains <i>), let specialized handler take over
    if (ksefBtn.classList.contains('icon-clip') || ksefBtn.querySelector('i')) return;
    const val = ksefBtn.getAttribute('data-ksef') || '';
    if (!val) return;
    navigator.clipboard.writeText(val).then(() => {
      ksefBtn.textContent = 'Skopiowano';
      setTimeout(() => { ksefBtn.textContent = 'Kopiuj'; }, 1200);
    });
    return;
  }
  const invBtn = e.target.closest('.copy-inv');
  if (invBtn) {
    if (invBtn.classList.contains('icon-clip') || invBtn.querySelector('i')) return;
    const val = invBtn.getAttribute('data-inv') || '';
    if (!val) return;
    navigator.clipboard.writeText(val).then(() => {
      invBtn.textContent = 'Skopiowano';
      setTimeout(() => { invBtn.textContent = 'Kopiuj nr'; }, 1200);
    });
  }
});

// Prosty pretty-print i pseudo-highlight XML
function escapeHtml(s){
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function formatXml(xml){
  let formatted = '';
  const reg = /(>)(<)(\/*)/g;
  xml = xml.replace(reg,'$1\n$2$3');
  let pad = 0;
  xml.split(/\n/).forEach((node)=>{
    if (node.match(/^\s*<\//)) pad = Math.max(pad-1,0);
    formatted += '  '.repeat(pad) + node + '\n';
    if (node.match(/^\s*<[^!?][^>]*[^\/]>/)) pad++;
  });
  return formatted.trim();
}
function highlightXml(xml){
  let s = escapeHtml(formatXml(xml));
  // comments
  s = s.replace(/(&lt;!--[\s\S]*?--&gt;)/g, '<span class="tok-comm">$1<\/span>');
  // tags + attributes
  s = s.replace(/(&lt;\/?)([A-Za-z0-9_.:\-]+)([^&]*?)(&gt;)/g, function(_, open, tag, attrs, close){
    // attributes: key="value"
    attrs = attrs.replace(/([A-Za-z_:][A-Za-z0-9_.:\-]*)(=)("[^"]*"|'[^']*')/g, '<span class="tok-attr">$1<\/span>$2<span class="tok-val">$3<\/span>');
    return open + '<span class="tok-tag">' + tag + '<\/span>' + attrs + close;
  });
  return s;
}

// Podgląd XML w modalu
document.addEventListener('click', function(e){
  const btn = e.target.closest('.preview-xml');
  if (!btn) return;
  const ksef = btn.getAttribute('data-ksef');
  const env = <?= json_encode($env ?? 'test') ?>;
  const url = <?= json_encode($this->Url->build(['controller' => 'KsefAuthorizations','action' => 'preview','_full' => true])) ?> + '/' + encodeURIComponent(ksef) + '?env=' + encodeURIComponent(env);
  const pre = document.getElementById('xmlPreviewBody');
  if (pre) pre.textContent = 'Ładowanie...';
  fetch(url).then(r => r.text()).then(t => {
    if (pre) pre.innerHTML = highlightXml(t);
  });
  const modal = document.getElementById('xmlPreviewModal');
  if (modal) modal.classList.add('show');
});

document.addEventListener('click', function(e){
  if (e.target.matches('[data-close-modal]')) {
    const modal = document.getElementById('xmlPreviewModal');
    if (modal) modal.classList.remove('show');
  }
});
</script>

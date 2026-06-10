<?php
/**
 * @var \App\View\AppView $this
 * @var array $state
 * @var bool $active
 * @var string $flagPath
 */
$this->assign('title', 'Przerwa techniczna');

// ISO → format pola datetime-local (YYYY-MM-DDTHH:MM)
$toLocal = function ($iso): string {
    $iso = trim((string)($iso ?? ''));
    if ($iso === '') {
        return '';
    }
    try {
        return (new \DateTimeImmutable($iso))->format('Y-m-d\TH:i');
    } catch (\Throwable) {
        return '';
    }
};

$enabled       = !empty($state['enabled']);
$message       = (string)($state['message'] ?? '');
$noticeMessage = (string)($state['notice_message'] ?? '');
$from          = $toLocal($state['from'] ?? null);
$to            = $toLocal($state['to'] ?? null);
$noticeFrom    = $toLocal($state['notice_from'] ?? null);
$allowCron     = array_key_exists('allow_cron', (array)$state) ? !empty($state['allow_cron']) : true;
?>
<div class="container py-4" style="max-width:880px;">

  <!-- Nagłówek + status -->
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
      <h3 class="mb-1"><i class="ri-tools-line me-1"></i> Przerwa techniczna</h3>
      <p class="text-muted mb-0 small">Steruj dostępnością serwisu dla użytkowników.</p>
    </div>
    <?php if ($active): ?>
      <span class="badge bg-danger fs-6 px-3 py-2"><i class="ri-lock-line me-1"></i> AKTYWNA teraz</span>
    <?php elseif ($enabled): ?>
      <span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="ri-time-line me-1"></i> Zaplanowana</span>
    <?php else: ?>
      <span class="badge bg-success fs-6 px-3 py-2"><i class="ri-check-line me-1"></i> Wyłączona</span>
    <?php endif; ?>
  </div>

  <?= $this->Form->create(null, ['url' => ['action' => 'save'], 'id' => 'maint-form']) ?>

  <!-- Karta: włącznik -->
  <div class="card shadow-sm mb-3 <?= $enabled ? 'border-warning' : '' ?>">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h5 class="mb-1">Tryb przerwy technicznej</h5>
        <p class="text-muted mb-0 small">
          Gdy aktywny: użytkownicy widzą stronę 503, portal/API → 503 JSON.
          <strong>Admini</strong> i <strong>crony z kluczem</strong> działają dalej.
        </p>
      </div>
      <div class="form-check form-switch m-0" style="transform:scale(1.4);transform-origin:right center;">
        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
      </div>
    </div>
  </div>

  <!-- Karta: komunikaty + podgląd -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-light fw-semibold"><i class="ri-chat-3-line me-1"></i> Komunikaty</div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label" for="notice_message">Baner <strong>zapowiedzi</strong> (przed przerwą)</label>
        <textarea class="form-control" id="notice_message" name="notice_message" rows="2" placeholder="Dziś o 22:00 planujemy krótką przerwę techniczną."><?= h($noticeMessage) ?></textarea>
        <div class="form-text">Żółty baner widoczny dla zalogowanych przed startem przerwy.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="message">Komunikat <strong>w trakcie</strong> przerwy (strona 503)</label>
        <textarea class="form-control" id="message" name="message" rows="2" placeholder="Trwają prace techniczne, wrócimy ok. 22:30."><?= h($message) ?></textarea>
        <div class="form-text">Treść na stronie blokady, gdy przerwa jest aktywna.</div>
      </div>

      <!-- Podgląd na żywo -->
      <label class="form-label small text-muted mb-1">Podgląd:</label>
      <div id="prev-banner" class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-2" style="display:none!important;">
        <span style="font-size:18px;">⚠️</span>
        <div>
          <strong>Zaplanowana przerwa techniczna</strong><span id="prev-win"></span>
          <div class="small opacity-75" id="prev-notice-msg"></div>
        </div>
      </div>
      <div class="border rounded p-3 text-center bg-light">
        <div style="font-size:30px;line-height:1;">🛠️</div>
        <div class="fw-semibold mt-1">Przerwa techniczna</div>
        <div class="small text-muted" id="prev-503-msg"><?= h($message ?: 'Trwają prace techniczne. Prosimy spróbować ponownie za chwilę.') ?></div>
      </div>
    </div>
  </div>

  <!-- Karta: harmonogram -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-light fw-semibold"><i class="ri-calendar-schedule-line me-1"></i> Harmonogram (opcjonalnie)</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="notice_from">Baner zapowiedzi od</label>
          <input type="datetime-local" class="form-control" id="notice_from" name="notice_from" value="<?= h($noticeFrom) ?>">
          <div class="form-text">Od kiedy pokazać baner.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="from">Start blokady</label>
          <input type="datetime-local" class="form-control" id="from" name="from" value="<?= h($from) ?>">
          <div class="form-text">Puste = od razu po włączeniu.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="to">Koniec blokady</label>
          <input type="datetime-local" class="form-control" id="to" name="to" value="<?= h($to) ?>">
          <div class="form-text">Po tym czasie auto-wyłączenie.</div>
        </div>
      </div>
      <div class="mt-3 d-flex flex-wrap gap-2">
        <span class="small text-muted align-self-center">Szybko:</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-quick="30">za 30 min, na 30 min</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-quick="60">za 1 h, na 1 h</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-quick="now15">teraz, na 15 min</button>
      </div>
      <div class="alert alert-info small mt-3 mb-0">
        Kolejność: <strong>baner zapowiedzi od</strong> → <strong>start blokady</strong> → <strong>koniec blokady</strong>.
        Przed „startem" widać baner, między „start" a „koniec" — strona 503.
      </div>
    </div>
  </div>

  <!-- Karta: opcje -->
  <div class="card shadow-sm mb-3">
    <div class="card-header bg-light fw-semibold"><i class="ri-settings-3-line me-1"></i> Opcje</div>
    <div class="card-body">
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" role="switch" id="allow_cron" name="allow_cron" value="1" <?= $allowCron ? 'checked' : '' ?>>
        <label class="form-check-label" for="allow_cron">Pozwól cronom działać w trakcie prac (kolejka maili, planowane wysyłki KSeF)</label>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2 mb-2">
    <button type="submit" class="btn btn-primary px-4"><i class="ri-save-line me-1"></i> Zapisz</button>
    <a href="/" class="btn btn-outline-secondary">Powrót</a>
  </div>
  <p class="text-muted small mb-0">Plik stanu: <code><?= h($flagPath) ?></code></p>

  <?= $this->Form->end() ?>
</div>

<script>
(function () {
  var elNoticeMsg = document.getElementById('notice_message');
  var elMsg       = document.getElementById('message');
  var elFrom      = document.getElementById('from');
  var elTo        = document.getElementById('to');
  var elNoticeFr  = document.getElementById('notice_from');
  var prevBanner  = document.getElementById('prev-banner');
  var prevWin     = document.getElementById('prev-win');
  var prevNoticeMsg = document.getElementById('prev-notice-msg');
  var prev503     = document.getElementById('prev-503-msg');

  function fmt(v) {
    if (!v) return '';
    try {
      var d = new Date(v);
      if (isNaN(d.getTime())) return '';
      var p = function (n) { return ('0' + n).slice(-2); };
      return p(d.getDate()) + '.' + p(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
    } catch (e) { return ''; }
  }

  function refresh() {
    // Baner zapowiedzi
    var win = '';
    var f = fmt(elFrom.value), t = fmt(elTo.value);
    if (f || t) { win = ' — ' + (f + (t ? ' – ' + t : '')); }
    prevWin.textContent = win;
    prevNoticeMsg.textContent = elNoticeMsg.value || '';
    prevNoticeMsg.style.display = elNoticeMsg.value ? '' : 'none';
    prevBanner.style.setProperty('display', 'flex', 'important');

    // Strona 503
    prev503.textContent = elMsg.value || 'Trwają prace techniczne. Prosimy spróbować ponownie za chwilę.';
  }

  [elNoticeMsg, elMsg, elFrom, elTo, elNoticeFr].forEach(function (el) {
    if (el) { el.addEventListener('input', refresh); el.addEventListener('change', refresh); }
  });
  refresh();

  // Szybkie ustawienia harmonogramu
  function toLocalInput(d) {
    var p = function (n) { return ('0' + n).slice(-2); };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
  }
  document.querySelectorAll('[data-quick]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var now = new Date();
      var mode = btn.getAttribute('data-quick');
      var start, end, notice;
      if (mode === 'now15') {
        start = new Date(now.getTime());
        end   = new Date(now.getTime() + 15 * 60000);
        notice = new Date(now.getTime());
      } else {
        var mins = parseInt(mode, 10) || 30;
        notice = new Date(now.getTime());
        start  = new Date(now.getTime() + mins * 60000);
        end    = new Date(start.getTime() + mins * 60000);
      }
      elNoticeFr.value = toLocalInput(notice);
      elFrom.value     = toLocalInput(start);
      elTo.value       = toLocalInput(end);
      refresh();
    });
  });
})();
</script>

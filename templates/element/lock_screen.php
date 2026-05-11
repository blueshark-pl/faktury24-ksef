<?php
/**
 * Screen lock — modal fullscreen pokazywany po bezczynności.
 * Wymaga zalogowanego identity; nie renderuje się gdy lock wyłączony w config.
 *
 * @var \App\View\AppView $this
 */
use Cake\Core\Configure;

$cfg = (array)(Configure::read('Security.screenLock') ?? []);
if (empty($cfg['enabled'])) { return; }

$identity = $this->request->getAttribute('identity');
if (!$identity) { return; }

$idleSec    = (int)($cfg['idleSeconds']    ?? 300);
$warningSec = (int)($cfg['warningSeconds'] ?? 30);
$maxFails   = (int)($cfg['maxFailures']    ?? 3);

$first = trim((string)($identity->get('first_name') ?? ''));
$last  = trim((string)($identity->get('last_name')  ?? ''));
$name  = trim($first . ' ' . $last);
if ($name === '') {
    $name = (string)($identity->get('email') ?? $identity->get('username') ?? '');
}
$csrf = (string)($this->request->getAttribute('csrfToken') ?? '');
?>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- Screen lock — fullscreen modal po bezczynności                        -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div id="screenLock" class="screen-lock" aria-hidden="true" hidden>
    <div class="screen-lock-card shadow-lg">
        <img src="/img/logo.png" alt="Booklio TMS" class="sl-logo">
        <div class="sl-icon">
            <i class="ri-lock-line"></i>
        </div>
        <h2 class="sl-title"><?= __('Sesja zablokowana') ?></h2>
        <p class="sl-greet"><?= __('Witaj') ?>, <strong><?= h($name) ?></strong></p>
        <p class="sl-desc"><?= __('Z powodu bezczynności ekran został zablokowany. Wpisz hasło lub PIN, aby kontynuować pracę.') ?></p>

        <form id="screenLockForm" autocomplete="off" novalidate>
            <div class="position-relative mb-2">
                <input type="password" id="sl-credential" class="form-control form-control-lg text-center"
                       placeholder="<?= __('Hasło lub PIN') ?>" required autocomplete="off"
                       autocapitalize="off" spellcheck="false">
                <button type="button" class="btn-toggle-pw" id="slTogglePw" tabindex="-1"
                        title="<?= __('Pokaż / ukryj') ?>">
                    <i class="ri-eye-line"></i>
                </button>
            </div>
            <div id="sl-error" class="sl-error" hidden></div>
            <button type="submit" class="btn btn-primary btn-lg w-100" id="slSubmit">
                <i class="ri-lock-unlock-line me-1"></i><?= __('Odblokuj') ?>
            </button>
        </form>

        <div class="sl-actions mt-3">
            <a href="/logout" class="text-muted small"><i class="ri-logout-box-line me-1"></i><?= __('Wyloguj się') ?></a>
        </div>
    </div>
</div>

<!-- Pre-warning toast: pokazuje się 30s przed lockiem -->
<div id="screenLockWarning" class="screen-lock-warn shadow" hidden>
    <i class="ri-time-line me-2"></i>
    <span><?= __('Sesja zablokuje się za') ?>: <strong id="slCountdown">0</strong>s</span>
</div>

<style>
.screen-lock {
    position: fixed; inset: 0; z-index: 100000;
    display: flex; align-items: center; justify-content: center;
    background: rgba(15, 23, 42, .72);
    backdrop-filter: blur(8px) saturate(140%);
    -webkit-backdrop-filter: blur(8px) saturate(140%);
    animation: sl-fade .25s ease-out;
    padding: 24px;
}
@keyframes sl-fade { from { opacity: 0; } to { opacity: 1; } }

.screen-lock-card {
    background: var(--custom-white, #fff);
    border: 1px solid var(--default-border, rgba(255,255,255,.1));
    border-radius: 1.25rem;
    padding: 2rem 1.75rem;
    width: 100%; max-width: 420px;
    text-align: center;
    box-shadow: 0 30px 90px rgba(0,0,0,.45);
}
.sl-logo  { max-width: 140px; height: auto; opacity: .9; margin-bottom: 1rem; }
.sl-icon  {
    width: 64px; height: 64px;
    margin: 0 auto 1rem; border-radius: 50%;
    background: rgba(var(--primary-rgb, 27,89,152), .12);
    color: rgb(var(--primary-rgb, 27,89,152));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
}
.sl-title { font-size: 1.3rem; font-weight: 700; color: var(--default-text-color, #1e293b); margin-bottom: .35rem; }
.sl-greet { color: var(--text-muted, #6b7280); margin-bottom: .35rem; font-size: .9rem; }
.sl-desc  { color: var(--text-muted, #6b7280); margin-bottom: 1.25rem; font-size: .82rem; }

.position-relative .btn-toggle-pw {
    position: absolute; right: .55rem; top: 50%; transform: translateY(-50%);
    background: transparent; border: 0; color: var(--text-muted, #6b7280); font-size: 1.1rem;
    padding: .25rem .4rem; cursor: pointer;
}
.position-relative .btn-toggle-pw:hover { color: rgb(var(--primary-rgb, 27,89,152)); }

.sl-error {
    background: rgba(220, 53, 69, .1);
    border: 1px solid rgba(220, 53, 69, .3);
    color: #dc3545; border-radius: .5rem;
    padding: .5rem .75rem;
    font-size: .82rem; margin-bottom: .75rem;
}

.sl-actions a { text-decoration: none; }
.sl-actions a:hover { color: rgb(var(--primary-rgb, 27,89,152)) !important; }

/* Pre-warning toast (prawy dolny róg) */
.screen-lock-warn {
    position: fixed; bottom: 24px; right: 24px; z-index: 99999;
    background: #fff7ed; border: 1px solid #fed7aa;
    color: #c2410c; padding: .65rem 1rem;
    border-radius: .6rem;
    font-size: .85rem; font-weight: 500;
    display: flex; align-items: center;
    animation: sl-slide-in .3s ease-out;
}
@keyframes sl-slide-in {
    from { transform: translateY(20px); opacity: 0; }
    to   { transform: translateY(0); opacity: 1; }
}
</style>

<script>
(function () {
    'use strict';

    var IDLE_MS      = <?= $idleSec ?> * 1000;
    var WARNING_MS   = <?= $warningSec ?> * 1000;
    var MAX_FAILS    = <?= $maxFails ?>;
    var STORAGE_KEY  = 'bookliio_locked_at';
    var CSRF_TOKEN   = <?= json_encode($csrf) ?>;
    var URL_UNLOCK   = '<?= $this->Url->build(['controller' => 'Security', 'action' => 'unlock']) ?>';

    var lockEl    = document.getElementById('screenLock');
    var warnEl    = document.getElementById('screenLockWarning');
    var countdown = document.getElementById('slCountdown');
    var form      = document.getElementById('screenLockForm');
    var input     = document.getElementById('sl-credential');
    var errEl     = document.getElementById('sl-error');
    var togglePw  = document.getElementById('slTogglePw');
    if (!lockEl || !form) return;

    var idleTimer = null;
    var warnTimer = null;
    var failCount = 0;
    var isLocked  = false;

    // ── Lock / unlock UI ─────────────────────────────────────────────────
    function showLock() {
        if (isLocked) return;
        isLocked = true;
        hideWarn();
        lockEl.hidden = false;
        lockEl.removeAttribute('aria-hidden');
        lockEl.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        // localStorage sync — inne karty też locknij
        try { localStorage.setItem(STORAGE_KEY, String(Date.now())); } catch (e) {}
        setTimeout(function () { if (input) input.focus(); }, 80);
    }
    function hideLock() {
        if (!isLocked) return;
        isLocked = false;
        lockEl.hidden = true;
        lockEl.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        errEl.hidden = true;
        if (input) input.value = '';
        failCount = 0;
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
        resetIdleTimer();
    }
    function showWarn(secLeft) {
        if (isLocked) return;
        warnEl.hidden = false;
        if (countdown) countdown.textContent = String(secLeft);
    }
    function hideWarn() { warnEl.hidden = true; }

    // ── Timer bezczynności ───────────────────────────────────────────────
    function resetIdleTimer() {
        if (isLocked) return;
        if (idleTimer) clearTimeout(idleTimer);
        if (warnTimer) clearTimeout(warnTimer);
        hideWarn();

        // Pre-warning: pokaż toast (IDLE - WARNING) sekund przed lockiem
        var preMs = Math.max(0, IDLE_MS - WARNING_MS);
        warnTimer = setTimeout(function () {
            var secLeft = Math.round(WARNING_MS / 1000);
            showWarn(secLeft);
            // odliczanie
            var counterInt = setInterval(function () {
                if (isLocked || warnEl.hidden) { clearInterval(counterInt); return; }
                secLeft--;
                if (secLeft > 0) showWarn(secLeft); else clearInterval(counterInt);
            }, 1000);
        }, preMs);

        idleTimer = setTimeout(showLock, IDLE_MS);
    }

    // ── Listenery aktywności (debounce żeby nie reset co milisekundę) ───
    var lastReset = 0;
    function onActivity() {
        if (isLocked) return;
        var now = Date.now();
        if (now - lastReset < 1000) return;
        lastReset = now;
        resetIdleTimer();
    }
    ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart', 'click'].forEach(function (ev) {
        document.addEventListener(ev, onActivity, { passive: true });
    });

    // ── Multi-tab sync: gdy jedna karta zablokowana, inne też ───────────
    window.addEventListener('storage', function (e) {
        if (e.key !== STORAGE_KEY) return;
        if (e.newValue && !isLocked) showLock();
        if (!e.newValue && isLocked) hideLock();
    });
    // Na starcie sprawdź czy inna karta już zablokowała
    try {
        if (localStorage.getItem(STORAGE_KEY)) showLock();
    } catch (e) {}

    // ── Toggle widoczność hasła ─────────────────────────────────────────
    if (togglePw) {
        togglePw.addEventListener('click', function () {
            input.type = input.type === 'password' ? 'text' : 'password';
            this.firstElementChild.className = input.type === 'password' ? 'ri-eye-line' : 'ri-eye-off-line';
        });
    }

    // ── Submit formularza ───────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var credential = input.value;
        if (!credential) return;

        var btn = document.getElementById('slSubmit');
        btn.disabled = true;
        errEl.hidden = true;

        var body = new URLSearchParams({
            credential: credential,
            _csrfToken: CSRF_TOKEN,
        });

        fetch(URL_UNLOCK, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token':     CSRF_TOKEN,
                'Content-Type':     'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin',
            body: body.toString(),
        })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (d) {
            btn.disabled = false;
            if (d.success) {
                hideLock();
                return;
            }
            if (d.logout) {
                // Twardy logout
                window.location.href = '/logout';
                return;
            }
            errEl.textContent = d.error || 'Nieprawidłowe dane.';
            if (typeof d.attempts_left === 'number') {
                errEl.textContent += ' (' + d.attempts_left + ' prób pozostało)';
            }
            errEl.hidden = false;
            input.select();
        })
        .catch(function () {
            btn.disabled = false;
            errEl.textContent = 'Błąd połączenia z serwerem.';
            errEl.hidden = false;
        });
    });

    // ── Start ────────────────────────────────────────────────────────────
    resetIdleTimer();
})();
</script>

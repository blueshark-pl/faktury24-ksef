/**
 * CRM Push Notifications init (browser subscribe).
 * Rejestruje service worker + zapisuje subscription.
 *
 * Auto-init: wywoluje sie po DOMContentLoaded gdy user zalogowany.
 * User dostaje browser prompt "Zezwol na notyfikacje" tylko RAZ.
 */
(function() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    if (Notification.permission === 'denied') return;

    var VAPID_PUBLIC_KEY = null;
    var CSRF = document.querySelector('meta[name=csrfToken]')?.getAttribute('content') || '';

    function urlB64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
        return out;
    }

    function subscribe(registration) {
        if (!VAPID_PUBLIC_KEY) return;
        registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlB64ToUint8Array(VAPID_PUBLIC_KEY)
        }).then(function(sub) {
            var subJson = sub.toJSON();
            var fd = new FormData();
            fd.append('endpoint', subJson.endpoint);
            fd.append('keys[p256dh]', subJson.keys.p256dh);
            fd.append('keys[auth]', subJson.keys.auth);
            fetch('/crm/push/subscribe', {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'X-CSRF-Token': CSRF, 'Accept': 'application/json' }
            }).then(function(r) { return r.json(); }).then(function(j) {
                if (j.ok) console.log('CRM Push: subscription saved');
            });
        }).catch(function(err) {
            console.warn('CRM Push subscribe failed:', err.message);
        });
    }

    // Init: sprawdz status z serwera + rejestruj SW
    fetch('/crm/push/status', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
    .then(function(r) { return r.json(); }).then(function(j) {
        if (!j.ok || !j.vapid_public_key || j.migration_missing) return;
        VAPID_PUBLIC_KEY = j.vapid_public_key;
        navigator.serviceWorker.register('/sw-crm-push.js').then(function(reg) {
            if (Notification.permission === 'granted') {
                // Juz autoryzowane - subscribe od razu
                subscribe(reg);
            } else if (Notification.permission === 'default' && !j.has_subscription) {
                // Zaproponuj po 5s zeby nie irytowac od razu
                setTimeout(function() {
                    Notification.requestPermission().then(function(perm) {
                        if (perm === 'granted') subscribe(reg);
                    });
                }, 5000);
            }
        }).catch(function(err) {
            console.warn('CRM Push SW register failed:', err.message);
        });
    });
})();

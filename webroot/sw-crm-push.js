/**
 * Service Worker dla CRM Push Notifications.
 * Wywolywany przez browser przy Push Notification.
 */
self.addEventListener('push', function(event) {
    var data = { title: 'Booklio CRM', body: 'Nowe powiadomienie', url: '/crm' };
    try { if (event.data) data = Object.assign(data, event.data.json()); } catch (e) {}
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/assets/images/brand-logos/favicon.ico',
            badge: '/assets/images/brand-logos/favicon.ico',
            tag: data.tag || 'crm-notification',
            renotify: true,
            data: { url: data.url || '/crm' }
        })
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    var url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/crm';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.indexOf(url) !== -1 && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});

/* ZIWAIT service worker: shows new-order push notifications and opens the order on click. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let payload = {};
    try { payload = event.data ? event.data.json() : {}; } catch (e) { payload = {}; }

    const title = payload.title || 'New order';
    const options = {
        body: payload.body || '',
        tag: payload.tag || 'ziwait-order',
        renotify: true,
        requireInteraction: true,
        data: payload.data || {},
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    // Only same-origin URLs are opened.
    const target = new URL((event.notification.data && event.notification.data.url) || '/dashboard/orders', self.location.origin);
    if (target.origin !== self.location.origin) return;

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
        for (const client of clients) {
            if ('focus' in client) { client.navigate(target.href); return client.focus(); }
        }
        return self.clients.openWindow(target.href);
    }));
});

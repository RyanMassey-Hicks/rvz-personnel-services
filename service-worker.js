/**
 * RVZ Personnel Services — PWA service worker.
 * Cache-first for static assets (CSS/JS/icons), network-first for everything
 * else (job listings, applications, etc. must never show stale/offline data
 * as if it were current).
 */
const CACHE_NAME = 'rvz-static-v4';
const STATIC_ASSETS = [
    '/assets/css/style.css',
    '/assets/js/cookie-consent.js',
    '/manifest.json',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS)).catch(() => {})
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    const isStatic = STATIC_ASSETS.some((path) => url.pathname === path) || /\.(css|js|png|jpg|jpeg|webp|svg|woff2?)$/.test(url.pathname);

    if (isStatic) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((res) => {
                const clone = res.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                return res;
            }))
        );
        return;
    }

    // Network-first for everything dynamic (job pages, dashboard, etc.) —
    // never serve a stale application/pipeline page from cache.
    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});

/**
 * Real OS-level push notifications — fires even when no RVZ tab is open, as
 * long as the browser process is running (or, on platforms that support it,
 * even after it's fully closed, via the OS's own push wake-up). The payload
 * is whatever includes/webpush.php's webpush_notify_user() sent: JSON
 * {title, body, link}.
 */
self.addEventListener('push', (event) => {
    let data = { title: 'RVZ Personnel Services', body: 'You have a new notification.', link: '/' };
    if (event.data) {
        try { data = Object.assign(data, event.data.json()); } catch (e) { /* fall back to defaults above */ }
    }
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/assets/img/icon-192.png',
            badge: '/assets/img/icon-192.png',
            data: { link: data.link || '/' },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const link = (event.notification.data && event.notification.data.link) || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url === link && 'focus' in client) return client.focus();
            }
            if (self.clients.openWindow) return self.clients.openWindow(link);
        })
    );
});

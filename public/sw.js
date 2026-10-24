/* Heirloom service worker.
 *
 * Strategy:
 *  - Built assets (/build/*), icons and fonts: cache-first (they are content-hashed).
 *  - Page navigations: network-first, falling back to the cached copy, then to /offline.
 *  - Private family files (/media-file/*), form posts and anything cross-origin are never cached.
 */
const VERSION = 'heirloom-v1';
const STATIC = `${VERSION}-static`;
const PAGES = `${VERSION}-pages`;
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(PAGES).then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' }))).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

const isStatic = (url) => url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/fonts/') || url.pathname === '/favicon.ico';
const neverCache = (url) => url.pathname.startsWith('/media-file/') || url.pathname.startsWith('/media-file') || url.pathname.includes('/export/') || url.pathname === '/logout';

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin || neverCache(url)) return;

    if (isStatic(url)) {
        event.respondWith(
            caches.open(STATIC).then((cache) =>
                cache.match(request).then((hit) => hit || fetch(request).then((res) => {
                    if (res.ok) cache.put(request, res.clone());
                    return res;
                }))
            )
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((res) => {
                    if (res.ok && res.type === 'basic') {
                        const copy = res.clone();
                        caches.open(PAGES).then((cache) => cache.put(request, copy));
                    }
                    return res;
                })
                .catch(() => caches.match(request).then((hit) => hit || caches.match(OFFLINE_URL)))
        );
    }
});

self.addEventListener('message', (event) => {
    if (event.data === 'skipWaiting') self.skipWaiting();
});

/*
 * PowerNest Estate service worker.
 *
 * Deliberately conservative: only static, versioned assets are cached. Pages, API calls,
 * Livewire requests, documents, QR codes, payments and tokens always go to the network,
 * so nothing private or security-relevant is ever served from cache, and nothing is
 * queued for later (an offline scan or payment can never look verified).
 */
const VERSION = 'pne-v1';
const STATIC_CACHE = `${VERSION}-static`;
const PRECACHE = ['/offline.html', '/icons/favicon.svg', '/icons/icon-192.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

const isStaticAsset = (url) =>
    url.origin === self.location.origin && (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/icons/'));

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return; // Never intercept writes.
    }

    const url = new URL(request.url);

    if (isStaticAsset(url)) {
        // Hashed build files are immutable: cache-first.
        event.respondWith(
            caches.match(request).then((cached) =>
                cached ||
                fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                }),
            ),
        );
        return;
    }

    if (request.mode === 'navigate') {
        // Network only; show the static offline page when there is no connection.
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
    }
});

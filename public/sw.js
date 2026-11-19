// Service worker voor Laptop Monitoring.
//
// Pagina's met monitoringgegevens worden bewust NIET gecachet: ze moeten actueel zijn
// en bevatten privegegevens. Zonder verbinding krijg je een offlinepagina te zien.
// Alleen de vaste bestanden van de app (iconen, manifest, offlinepagina) worden bewaard.

const CACHE = 'monitoring-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/favicon.ico',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Pagina's: altijd eerst het netwerk, bij geen verbinding de offlinepagina.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Vaste bestanden: uit de cache als ze er al in staan.
    if (PRECACHE.includes(url.pathname)) {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
    }
});
